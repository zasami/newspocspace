<?php
/**
 * Admin API — Suggestions (suivi + changement de statut + commentaires internes + stats)
 *
 * Accès restreint à 'admin' et 'direction' uniquement (pas responsable).
 */

const ADM_SUG_STATUTS = ['nouvelle','etudiee','planifiee','en_dev','livree','refusee'];

function _adm_sug_require_admin(): void
{
    $user = require_auth();
    if (!in_array($user['role'], ['admin', 'direction'])) {
        forbidden('Accès direction requis');
    }
}

/**
 * Liste avec filtres + stats
 */
function admin_list_suggestions()
{
    _adm_sug_require_admin();
    global $params;

    $statut   = in_array($params['statut'] ?? '', ADM_SUG_STATUTS) ? $params['statut'] : '';
    $service  = Sanitize::text($params['service'] ?? '', 50);
    $cat      = Sanitize::text($params['categorie'] ?? '', 50);
    $urgence  = Sanitize::text($params['urgence'] ?? '', 20);
    $search   = Sanitize::text($params['search'] ?? '', 100);

    $where = ['1=1'];
    $binds = [];
    if ($statut)  { $where[] = 's.statut = ?';    $binds[] = $statut; }
    if ($service) { $where[] = 's.service = ?';   $binds[] = $service; }
    if ($cat)     { $where[] = 's.categorie = ?'; $binds[] = $cat; }
    if ($urgence) { $where[] = 's.urgence = ?';   $binds[] = $urgence; }
    if ($search) {
        $where[] = '(s.titre LIKE ? OR s.description LIKE ? OR s.reference_code LIKE ?)';
        $like = '%' . $search . '%';
        $binds[] = $like; $binds[] = $like; $binds[] = $like;
    }

    $rows = Db::fetchAll(
        "SELECT s.*,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo
         FROM suggestions s
         LEFT JOIN users u ON u.id = s.auteur_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY FIELD(s.urgence, 'critique','eleve','moyen','faible'),
                  s.votes_count DESC,
                  s.created_at DESC
         LIMIT 500",
        $binds
    );

    $stats = [
        'total'     => (int) Db::getOne("SELECT COUNT(*) FROM suggestions"),
        'nouvelle'  => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'nouvelle'"),
        'etudiee'   => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'etudiee'"),
        'planifiee' => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'planifiee'"),
        'en_dev'    => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'en_dev'"),
        'livree'    => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'livree'"),
        'refusee'   => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE statut = 'refusee'"),
        'critique'  => (int) Db::getOne("SELECT COUNT(*) FROM suggestions WHERE urgence = 'critique' AND statut NOT IN ('livree','refusee')"),
    ];

    // Top 10 les plus votées
    $top = Db::fetchAll(
        "SELECT s.id, s.reference_code, s.titre, s.votes_count, s.statut, s.urgence
         FROM suggestions s
         WHERE s.statut NOT IN ('livree','refusee')
         ORDER BY s.votes_count DESC, s.created_at DESC
         LIMIT 10"
    );

    // Répartition par service / catégorie
    $byService = Db::fetchAll(
        "SELECT service, COUNT(*) AS n FROM suggestions GROUP BY service ORDER BY n DESC"
    );
    $byCategorie = Db::fetchAll(
        "SELECT categorie, COUNT(*) AS n FROM suggestions GROUP BY categorie ORDER BY n DESC"
    );

    respond([
        'success' => true,
        'suggestions' => $rows,
        'stats' => $stats,
        'top' => $top,
        'by_service' => $byService,
        'by_categorie' => $byCategorie,
    ]);
}

/**
 * Détail admin (inclut commentaires internes admin_only)
 */
function admin_get_suggestion()
{
    _adm_sug_require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $sug = Db::fetch(
        "SELECT s.*, u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo, u.email AS auteur_email
         FROM suggestions s
         LEFT JOIN users u ON u.id = s.auteur_id
         WHERE s.id = ?",
        [$id]
    );
    if (!$sug) not_found('Suggestion introuvable');

    $comments = Db::fetchAll(
        "SELECT c.id, c.content, c.role, c.visibility, c.created_at,
                u.prenom, u.nom, u.photo
         FROM suggestions_commentaires c
         LEFT JOIN users u ON u.id = c.auteur_id
         WHERE c.suggestion_id = ?
         ORDER BY c.created_at ASC",
        [$id]
    );

    $attachments = Db::fetchAll(
        "SELECT id, original_name, mime_type, size_bytes, kind, created_at
         FROM suggestions_attachments WHERE suggestion_id = ? ORDER BY created_at ASC",
        [$id]
    );

    $history = Db::fetchAll(
        "SELECT h.*, u.prenom, u.nom
         FROM suggestions_statut_history h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.suggestion_id = ?
         ORDER BY h.created_at ASC",
        [$id]
    );

    // Votants (pour notifier lors d'une livraison)
    $voters = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo
         FROM suggestions_votes v
         JOIN users u ON u.id = v.user_id
         WHERE v.suggestion_id = ?
         ORDER BY v.created_at ASC",
        [$id]
    );

    respond([
        'success' => true,
        'suggestion' => $sug,
        'comments' => $comments,
        'attachments' => $attachments,
        'history' => $history,
        'voters' => $voters,
    ]);
}

/**
 * Changer le statut (+ motif + sprint optionnel).
 */
function admin_update_suggestion_statut()
{
    _adm_sug_require_admin();
    require_once __DIR__ . '/../../core/HtmlSanitize.php';
    global $params;

    $userId  = $_SESSION['ss_user']['id'];
    $id      = $params['id'] ?? '';
    $statut  = in_array($params['statut'] ?? '', ADM_SUG_STATUTS) ? $params['statut'] : '';
    $motif   = HtmlSanitize::clean((string)($params['motif'] ?? ''));
    if (mb_strlen($motif) > 5000) $motif = mb_substr($motif, 0, 5000);
    if (trim(strip_tags($motif)) === '') $motif = '';
    $sprint  = Sanitize::text($params['sprint'] ?? '', 64);

    if (!$id || !$statut) bad_request('Paramètres manquants');

    $sug = Db::fetch("SELECT statut FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');

    $oldStatut = $sug['statut'];
    $resolvedAt = in_array($statut, ['livree','refusee']) ? date('Y-m-d H:i:s') : null;

    Db::exec(
        "UPDATE suggestions SET statut = ?, motif_admin = ?, sprint = ?, resolved_at = ? WHERE id = ?",
        [$statut, $motif ?: null, $sprint ?: null, $resolvedAt, $id]
    );

    if ($oldStatut !== $statut) {
        Db::exec(
            "INSERT INTO suggestions_statut_history (id, suggestion_id, old_statut, new_statut, changed_by, motif)
             VALUES (?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $id, $oldStatut, $statut, $userId, $motif ?: null]
        );
    }

    respond(['success' => true]);
}

/**
 * Commentaire admin (public ou admin_only).
 */
function admin_add_suggestion_comment()
{
    _adm_sug_require_admin();
    require_once __DIR__ . '/../../core/HtmlSanitize.php';
    global $params;

    $userId   = $_SESSION['ss_user']['id'];
    $id       = $params['id'] ?? '';
    $content  = HtmlSanitize::clean((string)($params['content'] ?? ''));
    if (mb_strlen($content) > 5000) $content = mb_substr($content, 0, 5000);
    $internal = !empty($params['internal']) ? 1 : 0;

    if (!$id || trim(strip_tags($content)) === '') bad_request('Paramètres manquants');

    $sug = Db::fetch("SELECT id FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');

    $cid = Uuid::v4();
    Db::exec(
        "INSERT INTO suggestions_commentaires
         (id, suggestion_id, auteur_id, role, visibility, content)
         VALUES (?, ?, ?, 'admin', ?, ?)",
        [$cid, $id, $userId, $internal ? 'admin_only' : 'public', $content]
    );
    if (!$internal) {
        Db::exec("UPDATE suggestions SET comments_count = comments_count + 1 WHERE id = ?", [$id]);
    }

    respond(['success' => true, 'id' => $cid]);
}

/**
 * Supprimer une suggestion (admin uniquement).
 */
function admin_delete_suggestion()
{
    _adm_sug_require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    // Cleanup fichiers
    $dir = __DIR__ . '/../../storage/suggestions/' . $id . '/';
    if (is_dir($dir)) {
        foreach (glob($dir . '*') as $f) @unlink($f);
        @rmdir($dir);
    }

    Db::exec("DELETE FROM suggestions WHERE id = ?", [$id]);
    respond(['success' => true]);
}

/**
 * Télécharger une pièce jointe (admin).
 */
function admin_download_suggestion_attachment()
{
    _adm_sug_require_admin();
    global $params;

    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('id requis');

    $att = Db::fetch("SELECT * FROM suggestions_attachments WHERE id = ?", [$attId]);
    if (!$att) not_found('Pièce introuvable');

    $path = __DIR__ . '/../../storage/suggestions/' . $att['suggestion_id'] . '/' . $att['filename'];
    if (!file_exists($path)) not_found('Fichier manquant');

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: ' . safe_content_disposition($att['original_name'], 'inline'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
