<?php
/**
 * Admin API — Fiches d'amélioration continue (suivi)
 *
 * Rappel anonymat STRICT : si fiche.is_anonymous = 1, auteur_id = NULL en DB,
 * donc même l'admin ne peut pas retrouver l'auteur.
 */

const ADM_FA_STATUTS = ['soumise', 'en_revue', 'en_cours', 'realisee', 'rejetee'];
const ADM_FA_CATEGORIES = ['securite', 'qualite_soins', 'organisation', 'materiel', 'communication', 'autre'];
const ADM_FA_CRITICITES = ['faible', 'moyenne', 'haute'];
const ADM_FA_RDV_STATUTS = ['proposee', 'acceptee', 'refusee', 'effectuee', 'annulee'];

/**
 * Liste des fiches avec filtres + stats
 */
function admin_list_fiches_amelioration()
{
    require_responsable();
    global $params;

    $statut     = in_array($params['statut'] ?? '', ADM_FA_STATUTS) ? $params['statut'] : '';
    $categorie  = in_array($params['categorie'] ?? '', ADM_FA_CATEGORIES) ? $params['categorie'] : '';
    $criticite  = in_array($params['criticite'] ?? '', ADM_FA_CRITICITES) ? $params['criticite'] : '';
    $search     = Sanitize::text($params['search'] ?? '', 100);

    $where = ['1 = 1'];
    $binds = [];
    if ($statut)    { $where[] = 'f.statut = ?';    $binds[] = $statut; }
    if ($categorie) { $where[] = 'f.categorie = ?'; $binds[] = $categorie; }
    if ($criticite) { $where[] = 'f.criticite = ?'; $binds[] = $criticite; }
    if ($search) {
        $where[] = '(f.titre LIKE ? OR f.description LIKE ?)';
        $binds[] = '%' . $search . '%';
        $binds[] = '%' . $search . '%';
    }

    $rows = Db::fetchAll(
        "SELECT f.*,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo,
                (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments,
                (SELECT COUNT(*) FROM fiches_amelioration_rdv WHERE fiche_id = f.id) AS nb_rdvs
         FROM fiches_amelioration f
         LEFT JOIN users u ON u.id = f.auteur_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY FIELD(f.criticite, 'haute', 'moyenne', 'faible'), f.created_at DESC
         LIMIT 500",
        $binds
    );

    // Masque auteur si anonyme
    foreach ($rows as &$r) {
        if ($r['is_anonymous']) {
            $r['auteur_prenom'] = 'Anonyme';
            $r['auteur_nom'] = '';
            $r['auteur_photo'] = null;
            $r['auteur_id'] = null;
        }
    }

    // Stats
    $stats = [
        'total'     => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration"),
        'soumise'   => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'soumise'"),
        'en_revue'  => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_revue'"),
        'en_cours'  => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_cours'"),
        'realisee'  => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'realisee'"),
        'rejetee'   => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'rejetee'"),
        'haute'     => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE criticite = 'haute' AND statut NOT IN ('realisee','rejetee')"),
    ];

    respond(['success' => true, 'fiches' => $rows, 'stats' => $stats]);
}

/**
 * Détail d'une fiche (côté admin)
 */
function admin_get_fiche_amelioration()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $fiche = Db::fetch("SELECT * FROM fiches_amelioration WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    // Auteur — masqué si anonyme (même pour admin en anonymat strict)
    $auteur = null;
    if (!$fiche['is_anonymous'] && $fiche['auteur_id']) {
        $auteur = Db::fetch("SELECT id, prenom, nom, email, photo FROM users WHERE id = ?", [$fiche['auteur_id']]);
    }
    if ($fiche['is_anonymous']) $fiche['auteur_id'] = null;

    // Commentaires
    $comments = Db::fetchAll(
        "SELECT c.*, u.prenom, u.nom, u.photo
         FROM fiches_amelioration_commentaires c
         LEFT JOIN users u ON u.id = c.auteur_id
         WHERE c.fiche_id = ?
         ORDER BY c.created_at ASC",
        [$id]
    );
    foreach ($comments as &$c) {
        if ($c['is_anonymous']) {
            $c['prenom'] = 'Anonyme'; $c['nom'] = ''; $c['photo'] = null; $c['auteur_id'] = null;
        }
    }

    // Concernés
    $concernes = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo
         FROM fiches_amelioration_concernes c
         JOIN users u ON u.id = c.user_id
         WHERE c.fiche_id = ?",
        [$id]
    );

    // Attachments
    $attachments = Db::fetchAll(
        "SELECT id, original_name, mime_type, size_bytes, created_at
         FROM fiches_amelioration_attachments WHERE fiche_id = ?",
        [$id]
    );

    // RDVs
    $rdvs = Db::fetchAll(
        "SELECT r.*, u.prenom AS admin_prenom, u.nom AS admin_nom
         FROM fiches_amelioration_rdv r
         LEFT JOIN users u ON u.id = r.proposed_by
         WHERE r.fiche_id = ?
         ORDER BY r.date_proposed ASC",
        [$id]
    );

    respond([
        'success' => true,
        'fiche' => $fiche,
        'auteur' => $auteur,
        'comments' => $comments,
        'concernes' => $concernes,
        'attachments' => $attachments,
        'rdvs' => $rdvs,
    ]);
}

/**
 * Changer le statut d'une fiche (déclenche notif email si auteur non-anonyme)
 */
function admin_update_fiche_amelioration_statut()
{
    $admin = require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    if (!$id) bad_request('id requis');
    if (!in_array($statut, ADM_FA_STATUTS)) bad_request('Statut invalide');

    $fiche = Db::fetch("SELECT * FROM fiches_amelioration WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    $resolvedSql = in_array($statut, ['realisee', 'rejetee']) ? ', resolved_at = NOW()' : '';
    Db::exec("UPDATE fiches_amelioration SET statut = ?" . $resolvedSql . " WHERE id = ?", [$statut, $id]);

    // Notif email à l'auteur si non anonyme
    if (!$fiche['is_anonymous'] && $fiche['auteur_id']) {
        $auteur = Db::fetch("SELECT email, prenom, nom FROM users WHERE id = ?", [$fiche['auteur_id']]);
        if ($auteur && $auteur['email']) {
            require_once __DIR__ . '/../../core/EmailTemplate.php';
            @EmailTemplate::send('fiche_amelioration_statut', $auteur['email'], [
                'prenom'    => $auteur['prenom'] ?? '',
                'nom'       => $auteur['nom'] ?? '',
                'email'     => $auteur['email'],
                'titre'     => $fiche['titre'],
                'statut'    => _fa_statut_label($statut),
                'url_fiche' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.zkriva.com') . '/spocspace/#/fiches-amelioration',
            ], $admin['id']);
        }
    }

    respond(['success' => true]);
}

function _fa_statut_label(string $s): string
{
    return match ($s) {
        'soumise'  => 'Soumise',
        'en_revue' => 'En revue',
        'en_cours' => 'En cours de traitement',
        'realisee' => 'Réalisée',
        'rejetee'  => 'Rejetée',
        default    => $s,
    };
}

/**
 * Ajouter un commentaire admin
 */
function admin_add_fiche_amelioration_comment()
{
    $admin = require_responsable();
    global $params;

    require_once __DIR__ . '/../../core/HtmlSanitize.php';
    $id = $params['fiche_id'] ?? '';
    $richOpts = ['allow_attrs' => [
        'a' => ['href','title','target','rel'], 'span' => ['class'], 'div' => ['class'],
        'ul' => ['class'], 'li' => ['class'], 'code' => ['class'], 'pre' => ['class'],
        'th' => ['colspan','rowspan'], 'td' => ['colspan','rowspan'], '*' => [],
    ]];
    $content = HtmlSanitize::clean((string)($params['content'] ?? ''), $richOpts);
    if (mb_strlen($content) > 10000) $content = mb_substr($content, 0, 10000);
    $textOnly = trim(strip_tags($content));
    if (!$id || !$textOnly) bad_request('Paramètres manquants');

    $fiche = Db::fetch("SELECT id FROM fiches_amelioration WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    $cid = Uuid::v4();
    Db::exec(
        "INSERT INTO fiches_amelioration_commentaires (id, fiche_id, auteur_id, is_anonymous, role, content)
         VALUES (?, ?, ?, 0, 'admin', ?)",
        [$cid, $id, $admin['id'], $content]
    );

    respond(['success' => true, 'id' => $cid]);
}

/**
 * Proposer un RDV à l'auteur (pas possible si anonyme strict)
 */
function admin_propose_fiche_amelioration_rdv()
{
    $admin = require_responsable();
    global $params;

    $ficheId = $params['fiche_id'] ?? '';
    $dateProposed = $params['date_proposed'] ?? '';
    $lieu = Sanitize::text($params['lieu'] ?? '', 255);
    $notes = Sanitize::text($params['admin_notes'] ?? '', 2000);

    if (!$ficheId || !$dateProposed) bad_request('Paramètres manquants');

    $fiche = Db::fetch("SELECT auteur_id, is_anonymous, titre FROM fiches_amelioration WHERE id = ?", [$ficheId]);
    if (!$fiche) not_found('Fiche introuvable');
    if ($fiche['is_anonymous'] || !$fiche['auteur_id']) {
        bad_request("Impossible de proposer un RDV : l'auteur est anonyme");
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO fiches_amelioration_rdv (id, fiche_id, proposed_by, date_proposed, lieu, admin_notes, statut)
         VALUES (?, ?, ?, ?, ?, ?, 'proposee')",
        [$id, $ficheId, $admin['id'], $dateProposed, $lieu ?: null, $notes ?: null]
    );

    // Notif email
    $auteur = Db::fetch("SELECT email, prenom, nom FROM users WHERE id = ?", [$fiche['auteur_id']]);
    if ($auteur && $auteur['email']) {
        require_once __DIR__ . '/../../core/EmailTemplate.php';
        @EmailTemplate::send('fiche_amelioration_rdv', $auteur['email'], [
            'prenom'        => $auteur['prenom'] ?? '',
            'nom'           => $auteur['nom'] ?? '',
            'email'         => $auteur['email'],
            'titre'         => $fiche['titre'],
            'date_rdv'      => date('d.m.Y à H:i', strtotime($dateProposed)),
            'lieu'          => $lieu ?: 'à préciser',
            'admin_notes'   => $notes ?: '',
            'url_fiche'     => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.zkriva.com') . '/spocspace/#/fiches-amelioration',
        ], $admin['id']);
    }

    respond(['success' => true, 'id' => $id]);
}

/**
 * Mettre à jour un RDV (effectué, annulé)
 */
function admin_update_fiche_amelioration_rdv()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    if (!$id) bad_request('id requis');
    if (!in_array($statut, ADM_FA_RDV_STATUTS)) bad_request('Statut RDV invalide');

    Db::exec("UPDATE fiches_amelioration_rdv SET statut = ? WHERE id = ?", [$statut, $id]);
    respond(['success' => true]);
}

/**
 * Télécharger une pièce jointe (admin)
 */
function admin_download_fiche_amelioration_attachment()
{
    require_responsable();
    global $params;

    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('id requis');

    $att = Db::fetch("SELECT * FROM fiches_amelioration_attachments WHERE id = ?", [$attId]);
    if (!$att) not_found('Pièce introuvable');

    $path = __DIR__ . '/../../storage/fiches_amelioration/' . $att['fiche_id'] . '/' . $att['filename'];
    if (!file_exists($path)) not_found('Fichier manquant');

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: inline; filename="' . basename($att['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/**
 * Supprimer une fiche (admin uniquement)
 */
function admin_delete_fiche_amelioration()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    // Supprime fichiers physiques
    $atts = Db::fetchAll("SELECT filename FROM fiches_amelioration_attachments WHERE fiche_id = ?", [$id]);
    foreach ($atts as $a) {
        $p = __DIR__ . '/../../storage/fiches_amelioration/' . $id . '/' . $a['filename'];
        if (file_exists($p)) @unlink($p);
    }
    $dir = __DIR__ . '/../../storage/fiches_amelioration/' . $id;
    if (is_dir($dir)) @rmdir($dir);

    Db::exec("DELETE FROM fiches_amelioration WHERE id = ?", [$id]);
    respond(['success' => true]);
}
