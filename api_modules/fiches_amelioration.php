<?php
/**
 * Collab API — Fiches d'amélioration continue
 *
 * Anonymat STRICT : si is_anonymous=1, auteur_id=NULL en DB (non traçable, même admin).
 */

const FA_CATEGORIES = ['securite', 'qualite_soins', 'organisation', 'materiel', 'communication', 'autre'];
const FA_CRITICITES = ['faible', 'moyenne', 'haute'];
const FA_VISIBILITIES = ['private', 'public', 'targeted'];
const FA_STATUTS = ['soumise', 'en_revue', 'en_cours', 'realisee', 'rejetee'];

const FA_TYPES = ['incident','dysfonctionnement','suggestion','non_conformite','plainte','presque_accident'];
const FA_PERSONNES = ['resident','collaborateur','visiteur','prestataire'];

/**
 * Soumettre une nouvelle fiche
 */
function submit_fiche_amelioration()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $isAnonymous = !empty($params['is_anonymous']) ? 1 : 0;
    $isDraft     = !empty($params['is_draft']) ? 1 : 0;
    $visibility = in_array($params['visibility'] ?? '', FA_VISIBILITIES) ? $params['visibility'] : 'private';
    $categorie  = in_array($params['categorie'] ?? '', FA_CATEGORIES) ? $params['categorie'] : 'autre';
    $criticite  = in_array($params['criticite'] ?? '', FA_CRITICITES) ? $params['criticite'] : 'moyenne';
    $typeEvt    = in_array($params['type_evenement'] ?? '', FA_TYPES) ? $params['type_evenement'] : 'suggestion';
    require_once __DIR__ . '/../core/HtmlSanitize.php';
    // Allow class attribute on ul/li so we can store <ul class="checklist"><li class="checked">…</li></ul>
    $richOpts = ['allow_attrs' => [
        'a'    => ['href', 'title', 'target', 'rel'],
        'span' => ['class'], 'div' => ['class'],
        'ul'   => ['class'], 'li'  => ['class'],
        'code' => ['class'], 'pre' => ['class'],
        'th'   => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        '*'    => [],
    ]];
    $titre = Sanitize::text($params['titre'] ?? '', 255);
    $description = HtmlSanitize::clean((string)($params['description'] ?? ''), $richOpts);
    $suggestion = HtmlSanitize::clean((string)($params['suggestion'] ?? ''), $richOpts);
    $mesures = HtmlSanitize::clean((string)($params['mesures_immediates'] ?? ''), $richOpts);
    if (mb_strlen($description) > 20000) $description = mb_substr($description, 0, 20000);
    if (mb_strlen($suggestion) > 20000) $suggestion = mb_substr($suggestion, 0, 20000);
    if (mb_strlen($mesures) > 20000) $mesures = mb_substr($mesures, 0, 20000);
    $lieu = Sanitize::text($params['lieu_precis'] ?? '', 255);
    $dateEvt = Sanitize::date($params['date_evenement'] ?? '');
    $heureEvt = Sanitize::time($params['heure_evenement'] ?? '');
    $uniteId = (string)($params['unite_module_id'] ?? '');
    if ($uniteId && strlen($uniteId) !== 36) $uniteId = '';
    $personnes = array_values(array_intersect(FA_PERSONNES, explode(',', (string)($params['personnes_concernees_types'] ?? ''))));
    $concernesIds = is_array($params['concernes_ids'] ?? null) ? $params['concernes_ids'] : [];

    if (!$titre) bad_request('Titre requis');
    if (!$isDraft && !$description) bad_request('Description requise');

    // Reference auto-générée : FAC-YYYY-NNN
    $year = date('Y');
    $nb = (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE YEAR(created_at) = ?", [$year]);
    $reference = sprintf('FAC-%d-%03d', $year, $nb + 1);

    $id = Uuid::v4();

    Db::exec(
        "INSERT INTO fiches_amelioration
         (id, reference_code, auteur_id, is_anonymous, visibility, type_evenement, personnes_concernees_types,
          unite_module_id, titre, categorie, criticite, description, suggestion,
          date_evenement, heure_evenement, lieu_precis, mesures_immediates,
          statut, is_draft)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $reference,
            $isAnonymous ? null : $userId,
            $isAnonymous, $visibility, $typeEvt,
            $personnes ? implode(',', $personnes) : null,
            $uniteId ?: null,
            $titre, $categorie, $criticite,
            $description, $suggestion ?: null,
            $dateEvt ?: null, $heureEvt ?: null,
            $lieu ?: null, $mesures ?: null,
            'soumise', $isDraft,
        ]
    );

    // Users concernés (targeted only)
    if ($visibility === 'targeted' && $concernesIds) {
        $stmt = Db::connect()->prepare("INSERT IGNORE INTO fiches_amelioration_concernes (fiche_id, user_id) VALUES (?, ?)");
        foreach ($concernesIds as $uid) {
            if (is_string($uid) && strlen($uid) === 36) $stmt->execute([$id, $uid]);
        }
    }

    respond(['success' => true, 'id' => $id, 'reference' => $reference, 'message' => $isDraft ? 'Brouillon enregistré' : 'Fiche soumise']);
}

/**
 * Liste des fiches visibles par l'utilisateur :
 *  - Tab "mes"       → fiches dont auteur_id = moi (exclut anonymes puisque auteur_id = NULL)
 *  - Tab "publiques" → visibility = public (toutes)
 *  - Tab "concerne"  → fiches où je suis dans concernes
 */
function get_fiches_amelioration()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $tab = in_array($params['tab'] ?? '', ['mes', 'publiques', 'concerne']) ? $params['tab'] : 'mes';

    if ($tab === 'mes') {
        $rows = Db::fetchAll(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments
             FROM fiches_amelioration f
             WHERE f.auteur_id = ?
             ORDER BY f.created_at DESC",
            [$userId]
        );
    } elseif ($tab === 'publiques') {
        $rows = Db::fetchAll(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments,
                    u.prenom AS auteur_prenom, u.nom AS auteur_nom
             FROM fiches_amelioration f
             LEFT JOIN users u ON u.id = f.auteur_id
             WHERE f.visibility = 'public'
             ORDER BY f.created_at DESC
             LIMIT 200"
        );
    } else { // concerne
        $rows = Db::fetchAll(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM fiches_amelioration_commentaires WHERE fiche_id = f.id) AS nb_comments,
                    u.prenom AS auteur_prenom, u.nom AS auteur_nom
             FROM fiches_amelioration f
             INNER JOIN fiches_amelioration_concernes c ON c.fiche_id = f.id AND c.user_id = ?
             LEFT JOIN users u ON u.id = f.auteur_id
             ORDER BY f.created_at DESC",
            [$userId]
        );
    }

    // Anonymiser l'auteur pour les fiches anonymes
    foreach ($rows as &$r) {
        if ($r['is_anonymous']) {
            $r['auteur_prenom'] = 'Anonyme';
            $r['auteur_nom'] = '';
            $r['auteur_id'] = null;
        }
    }

    respond(['success' => true, 'fiches' => $rows]);
}

/**
 * Détail d'une fiche + commentaires + RDVs + pièces jointes.
 * Accessible si :
 *  - l'auteur n'est pas anonyme et c'est moi
 *  - visibility = public
 *  - je suis dans concernes
 */
function get_fiche_amelioration_detail()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $fiche = Db::fetch("SELECT * FROM fiches_amelioration WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    $canAccess = false;
    if ($fiche['auteur_id'] === $userId) $canAccess = true;
    if ($fiche['visibility'] === 'public') $canAccess = true;
    if ($fiche['visibility'] === 'targeted') {
        $concerne = Db::fetch(
            "SELECT 1 FROM fiches_amelioration_concernes WHERE fiche_id = ? AND user_id = ?",
            [$id, $userId]
        );
        if ($concerne) $canAccess = true;
    }
    if (!$canAccess) forbidden('Accès refusé');

    // Auteur (masqué si anonyme)
    $auteur = null;
    if (!$fiche['is_anonymous'] && $fiche['auteur_id']) {
        $auteur = Db::fetch("SELECT id, prenom, nom, photo FROM users WHERE id = ?", [$fiche['auteur_id']]);
    }

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

    // Users concernés
    $concernes = [];
    if ($fiche['visibility'] === 'targeted') {
        $concernes = Db::fetchAll(
                "SELECT u.id, u.prenom, u.nom, u.photo
             FROM fiches_amelioration_concernes c
             JOIN users u ON u.id = c.user_id
             WHERE c.fiche_id = ?",
            [$id]
        );
    }

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

    // Masque auteur sur l'objet fiche si anonyme
    if ($fiche['is_anonymous']) $fiche['auteur_id'] = null;

    respond([
        'success' => true,
        'fiche' => $fiche,
        'auteur' => $auteur,
        'comments' => $comments,
        'concernes' => $concernes,
        'attachments' => $attachments,
        'rdvs' => $rdvs,
        'is_my_fiche' => ($fiche['auteur_id'] === $userId),
    ]);
}

/**
 * Ajouter un commentaire à une fiche
 */
function add_fiche_amelioration_comment()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['fiche_id'] ?? '';
    $content = Sanitize::text($params['content'] ?? '', 3000);
    $isAnonymous = !empty($params['is_anonymous']) ? 1 : 0;

    if (!$id || !$content) bad_request('Paramètres manquants');

    $fiche = Db::fetch("SELECT * FROM fiches_amelioration WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    // Check access (même règles que detail)
    $canAccess = false;
    if ($fiche['auteur_id'] === $userId) $canAccess = true;
    if ($fiche['visibility'] === 'public') $canAccess = true;
    if ($fiche['visibility'] === 'targeted') {
        $c = Db::fetch("SELECT 1 FROM fiches_amelioration_concernes WHERE fiche_id = ? AND user_id = ?", [$id, $userId]);
        if ($c) $canAccess = true;
    }
    if (!$canAccess) forbidden('Accès refusé');

    $cid = Uuid::v4();
    Db::exec(
        "INSERT INTO fiches_amelioration_commentaires (id, fiche_id, auteur_id, is_anonymous, role, content)
         VALUES (?, ?, ?, ?, 'user', ?)",
        [$cid, $id, $isAnonymous ? null : $userId, $isAnonymous, $content]
    );

    respond(['success' => true, 'id' => $cid]);
}

/**
 * Répondre à une proposition de RDV (accepter / refuser)
 */
function respond_fiche_amelioration_rdv()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $rdvId = $params['rdv_id'] ?? '';
    $action = $params['action_response'] ?? '';
    $responseText = Sanitize::text($params['response'] ?? '', 1000);

    if (!in_array($action, ['acceptee', 'refusee'])) bad_request('Action invalide');
    if (!$rdvId) bad_request('rdv_id requis');

    $rdv = Db::fetch(
        "SELECT r.*, f.auteur_id AS fiche_auteur
         FROM fiches_amelioration_rdv r
         JOIN fiches_amelioration f ON f.id = r.fiche_id
         WHERE r.id = ?",
        [$rdvId]
    );
    if (!$rdv) not_found('RDV introuvable');

    // Seul l'auteur (non-anonyme) peut répondre
    if ($rdv['fiche_auteur'] !== $userId) forbidden('Seul l\'auteur peut répondre');

    Db::exec(
        "UPDATE fiches_amelioration_rdv SET statut = ?, user_response = ?, responded_at = NOW() WHERE id = ?",
        [$action, $responseText ?: null, $rdvId]
    );

    respond(['success' => true]);
}

/**
 * Upload une pièce jointe sur une fiche
 */
function upload_fiche_amelioration_attachment()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $ficheId = $_POST['fiche_id'] ?? $params['fiche_id'] ?? '';
    if (!$ficheId) bad_request('fiche_id requis');

    $fiche = Db::fetch("SELECT auteur_id FROM fiches_amelioration WHERE id = ?", [$ficheId]);
    if (!$fiche) not_found('Fiche introuvable');
    if ($fiche['auteur_id'] !== $userId) forbidden('Seul l\'auteur peut ajouter des pièces');

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur upload');
    }

    require_once __DIR__ . '/../core/FileSecurity.php';
    $err = FileSecurity::validateUpload($_FILES['file'], 'Fichier', FileSecurity::ALLOW_DOCUMENT, 10 * 1024 * 1024);
    if ($err) bad_request($err);

    $uploadDir = __DIR__ . '/../storage/fiches_amelioration/' . $ficheId . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = Uuid::v4() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        bad_request('Impossible de sauvegarder le fichier');
    }

    $attId = Uuid::v4();
    Db::exec(
        "INSERT INTO fiches_amelioration_attachments (id, fiche_id, filename, original_name, mime_type, size_bytes)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$attId, $ficheId, $filename, $_FILES['file']['name'], mime_content_type($dest), filesize($dest)]
    );

    respond(['success' => true, 'id' => $attId]);
}

/**
 * Télécharger une pièce jointe
 */
function download_fiche_amelioration_attachment()
{
    require_auth();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('id requis');

    $att = Db::fetch(
        "SELECT a.*, f.auteur_id, f.visibility
         FROM fiches_amelioration_attachments a
         JOIN fiches_amelioration f ON f.id = a.fiche_id
         WHERE a.id = ?",
        [$attId]
    );
    if (!$att) not_found('Pièce introuvable');

    $canAccess = ($att['auteur_id'] === $userId) || ($att['visibility'] === 'public');
    if (!$canAccess && $att['visibility'] === 'targeted') {
        $c = Db::fetch("SELECT 1 FROM fiches_amelioration_concernes WHERE fiche_id = ? AND user_id = ?", [$att['fiche_id'] ?? '', $userId]);
        if ($c) $canAccess = true;
    }
    if (!$canAccess) forbidden('Accès refusé');

    $path = __DIR__ . '/../storage/fiches_amelioration/' . $att['fiche_id'] . '/' . $att['filename'];
    if (!file_exists($path)) not_found('Fichier manquant');

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: inline; filename="' . basename($att['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/**
 * Rechercher des utilisateurs pour targeted visibility
 */
function search_fiche_amelioration_users()
{
    require_auth();
    global $params;
    $q = Sanitize::text($params['q'] ?? '', 100);
    if (strlen($q) < 2) respond(['success' => true, 'users' => []]);

    $like = '%' . $q . '%';
    $users = Db::fetchAll(
        "SELECT id, prenom, nom, photo
         FROM users
         WHERE (prenom LIKE ? OR nom LIKE ? OR CONCAT(prenom, ' ', nom) LIKE ?) AND is_active = 1
         ORDER BY prenom LIMIT 20",
        [$like, $like, $like]
    );
    respond(['success' => true, 'users' => $users]);
}
