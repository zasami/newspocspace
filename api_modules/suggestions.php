<?php
/**
 * Collab API — Suggestions & Demandes de développement
 *
 * Co-construction EMS : collaborateurs proposent des idées, votent,
 * commentent. Admin/direction suit via le back-office.
 */

const SUG_SERVICES   = ['aide_soignant','infirmier','infirmier_chef','animation','cuisine','technique','admin','rh','direction','qualite','autre'];
const SUG_CATEGORIES = ['formulaire','fonctionnalite','amelioration','alerte','bug','question'];
const SUG_URGENCES   = ['critique','eleve','moyen','faible'];
const SUG_FREQUENCES = ['multi_jour','quotidien','hebdo','mensuel','ponctuel'];
const SUG_BENEFICES  = ['gain_temps','reduction_erreurs','tracabilite','conformite','confort_resident','securite'];

/**
 * Vérifie que le module est activé côté EMS, sinon 404 discret.
 */
function _sug_require_enabled(): void
{
    $flag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
    if ($flag !== '1') not_found('Module désactivé');
}

/**
 * Génère une référence SUG-YYYY-NNN (unique par année).
 */
function _sug_next_reference(): string
{
    $year = date('Y');
    $nb = (int) Db::getOne(
        "SELECT COUNT(*) FROM suggestions WHERE YEAR(created_at) = ?",
        [$year]
    );
    return sprintf('SUG-%d-%03d', $year, $nb + 1);
}

/**
 * Liste des suggestions (tout le monde voit tout).
 * Filtres : service, categorie, statut, search. Tri : votes | date | urgence.
 */
function get_suggestions()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId   = $_SESSION['ss_user']['id'];
    $service  = in_array($params['service'] ?? '', SUG_SERVICES) ? $params['service'] : '';
    $cat      = in_array($params['categorie'] ?? '', SUG_CATEGORIES) ? $params['categorie'] : '';
    $statut   = in_array($params['statut'] ?? '', ['nouvelle','etudiee','planifiee','en_dev','livree','refusee']) ? $params['statut'] : '';
    $search   = Sanitize::text($params['search'] ?? '', 100);
    $sort     = in_array($params['sort'] ?? '', ['votes','date','urgence']) ? $params['sort'] : 'votes';
    $tab      = in_array($params['tab'] ?? '', ['tous','mes','votes']) ? $params['tab'] : 'tous';

    $where = ['1=1'];
    $binds = [];
    if ($service) { $where[] = 's.service = ?';   $binds[] = $service; }
    if ($cat)     { $where[] = 's.categorie = ?'; $binds[] = $cat; }
    if ($statut)  { $where[] = 's.statut = ?';    $binds[] = $statut; }
    if ($search)  {
        $where[] = '(s.titre LIKE ? OR s.description LIKE ? OR s.reference_code LIKE ?)';
        $like = '%' . $search . '%';
        $binds[] = $like; $binds[] = $like; $binds[] = $like;
    }
    if ($tab === 'mes') {
        $where[] = 's.auteur_id = ?'; $binds[] = $userId;
    } elseif ($tab === 'votes') {
        $where[] = 'EXISTS (SELECT 1 FROM suggestions_votes v WHERE v.suggestion_id = s.id AND v.user_id = ?)';
        $binds[] = $userId;
    }

    $orderBy = match ($sort) {
        'date'    => 's.created_at DESC',
        'urgence' => "FIELD(s.urgence, 'critique','eleve','moyen','faible'), s.votes_count DESC",
        default   => 's.votes_count DESC, s.created_at DESC',
    };

    $rows = Db::fetchAll(
        "SELECT s.*,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo,
                EXISTS(SELECT 1 FROM suggestions_votes v WHERE v.suggestion_id = s.id AND v.user_id = ?) AS has_voted
         FROM suggestions s
         LEFT JOIN users u ON u.id = s.auteur_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY $orderBy
         LIMIT 500",
        array_merge([$userId], $binds)
    );

    respond(['success' => true, 'suggestions' => $rows]);
}

/**
 * Détail d'une suggestion + commentaires publics + attachments.
 */
function get_suggestion_detail()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $sug = Db::fetch(
        "SELECT s.*,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.photo AS auteur_photo,
                EXISTS(SELECT 1 FROM suggestions_votes v WHERE v.suggestion_id = s.id AND v.user_id = ?) AS has_voted
         FROM suggestions s
         LEFT JOIN users u ON u.id = s.auteur_id
         WHERE s.id = ?",
        [$userId, $id]
    );
    if (!$sug) not_found('Suggestion introuvable');

    $comments = Db::fetchAll(
        "SELECT c.id, c.content, c.role, c.created_at,
                u.id AS user_id, u.prenom, u.nom, u.photo
         FROM suggestions_commentaires c
         LEFT JOIN users u ON u.id = c.auteur_id
         WHERE c.suggestion_id = ? AND c.visibility = 'public'
         ORDER BY c.created_at ASC",
        [$id]
    );

    $attachments = Db::fetchAll(
        "SELECT id, original_name, mime_type, size_bytes, kind, created_at
         FROM suggestions_attachments WHERE suggestion_id = ?
         ORDER BY created_at ASC",
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

    respond([
        'success' => true,
        'suggestion' => $sug,
        'comments' => $comments,
        'attachments' => $attachments,
        'history' => $history,
        'is_mine' => ($sug['auteur_id'] === $userId),
    ]);
}

/**
 * Création d'une suggestion.
 */
function create_suggestion()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];

    $titre     = Sanitize::text($params['titre'] ?? '', 255);
    $service   = in_array($params['service'] ?? '', SUG_SERVICES) ? $params['service'] : 'autre';
    $cat       = in_array($params['categorie'] ?? '', SUG_CATEGORIES) ? $params['categorie'] : 'fonctionnalite';
    $urgence   = in_array($params['urgence'] ?? '', SUG_URGENCES) ? $params['urgence'] : 'moyen';
    $frequence = in_array($params['frequence'] ?? '', SUG_FREQUENCES) ? $params['frequence'] : null;
    $desc      = Sanitize::text($params['description'] ?? '', 10000);
    $benefIn   = is_array($params['benefices'] ?? null) ? $params['benefices'] : [];
    $benefices = array_values(array_intersect(SUG_BENEFICES, $benefIn));

    if (!$titre)  bad_request('Titre requis');
    if (!$desc)   bad_request('Description requise');
    if (mb_strlen($titre) < 4) bad_request('Titre trop court');
    if (mb_strlen($desc) < 10) bad_request('Description trop courte');

    $id  = Uuid::v4();
    $ref = _sug_next_reference();

    Db::exec(
        "INSERT INTO suggestions
         (id, reference_code, auteur_id, titre, service, categorie, urgence, frequence,
          description, benefices, statut)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nouvelle')",
        [
            $id, $ref, $userId, $titre, $service, $cat, $urgence, $frequence,
            $desc, $benefices ? implode(',', $benefices) : null,
        ]
    );

    // Historique initial
    Db::exec(
        "INSERT INTO suggestions_statut_history (id, suggestion_id, old_statut, new_statut, changed_by)
         VALUES (?, ?, NULL, 'nouvelle', ?)",
        [Uuid::v4(), $id, $userId]
    );

    respond(['success' => true, 'id' => $id, 'reference' => $ref]);
}

/**
 * Édition par l'auteur tant que statut = 'nouvelle'.
 */
function update_suggestion()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $sug = Db::fetch("SELECT auteur_id, statut FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');
    if ($sug['auteur_id'] !== $userId) forbidden('Seul l\'auteur peut modifier');
    if ($sug['statut'] !== 'nouvelle') forbidden('Suggestion déjà traitée, modification impossible');

    $titre   = Sanitize::text($params['titre'] ?? '', 255);
    $service = in_array($params['service'] ?? '', SUG_SERVICES) ? $params['service'] : 'autre';
    $cat     = in_array($params['categorie'] ?? '', SUG_CATEGORIES) ? $params['categorie'] : 'fonctionnalite';
    $urgence = in_array($params['urgence'] ?? '', SUG_URGENCES) ? $params['urgence'] : 'moyen';
    $frequence = in_array($params['frequence'] ?? '', SUG_FREQUENCES) ? $params['frequence'] : null;
    $desc    = Sanitize::text($params['description'] ?? '', 10000);
    $benefIn = is_array($params['benefices'] ?? null) ? $params['benefices'] : [];
    $benefices = array_values(array_intersect(SUG_BENEFICES, $benefIn));

    if (!$titre || !$desc) bad_request('Titre et description requis');

    Db::exec(
        "UPDATE suggestions SET titre = ?, service = ?, categorie = ?, urgence = ?,
                                frequence = ?, description = ?, benefices = ?
         WHERE id = ?",
        [$titre, $service, $cat, $urgence, $frequence, $desc,
         $benefices ? implode(',', $benefices) : null, $id]
    );

    respond(['success' => true]);
}

/**
 * Supprimer sa propre suggestion (seulement si statut = nouvelle et aucun vote).
 */
function delete_suggestion()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $sug = Db::fetch("SELECT auteur_id, statut, votes_count FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');
    if ($sug['auteur_id'] !== $userId) forbidden('Seul l\'auteur peut supprimer');
    if ($sug['statut'] !== 'nouvelle') forbidden('Suggestion déjà traitée');
    if ((int)$sug['votes_count'] > 0) forbidden('Suggestion déjà votée');

    Db::exec("DELETE FROM suggestions WHERE id = ?", [$id]);
    respond(['success' => true]);
}

/**
 * Toggle vote (+1 / -1). 1 user = 1 vote par suggestion.
 */
function toggle_suggestion_vote()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $sug = Db::fetch("SELECT id FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');

    $existing = Db::fetch(
        "SELECT 1 FROM suggestions_votes WHERE suggestion_id = ? AND user_id = ?",
        [$id, $userId]
    );

    if ($existing) {
        Db::exec("DELETE FROM suggestions_votes WHERE suggestion_id = ? AND user_id = ?", [$id, $userId]);
        Db::exec("UPDATE suggestions SET votes_count = GREATEST(votes_count - 1, 0) WHERE id = ?", [$id]);
        $voted = false;
    } else {
        Db::exec(
            "INSERT INTO suggestions_votes (suggestion_id, user_id) VALUES (?, ?)",
            [$id, $userId]
        );
        Db::exec("UPDATE suggestions SET votes_count = votes_count + 1 WHERE id = ?", [$id]);
        $voted = true;
    }

    $count = (int) Db::getOne("SELECT votes_count FROM suggestions WHERE id = ?", [$id]);
    respond(['success' => true, 'voted' => $voted, 'votes_count' => $count]);
}

/**
 * Ajouter un commentaire public.
 */
function add_suggestion_comment()
{
    require_auth();
    _sug_require_enabled();
    require_once __DIR__ . '/../core/HtmlSanitize.php';
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $id = $params['id'] ?? '';
    $content = HtmlSanitize::clean((string)($params['content'] ?? ''));
    if (mb_strlen($content) > 5000) $content = mb_substr($content, 0, 5000);

    if (!$id || !$content) bad_request('Paramètres manquants');
    $plain = trim(strip_tags($content));
    if (mb_strlen($plain) < 2) bad_request('Commentaire trop court');

    $sug = Db::fetch("SELECT id FROM suggestions WHERE id = ?", [$id]);
    if (!$sug) not_found('Suggestion introuvable');

    $role = in_array($_SESSION['ss_user']['role'] ?? '', ['admin','direction']) ? 'admin' : 'user';

    $cid = Uuid::v4();
    Db::exec(
        "INSERT INTO suggestions_commentaires
         (id, suggestion_id, auteur_id, role, visibility, content)
         VALUES (?, ?, ?, ?, 'public', ?)",
        [$cid, $id, $userId, $role, $content]
    );
    Db::exec("UPDATE suggestions SET comments_count = comments_count + 1 WHERE id = ?", [$id]);

    respond(['success' => true, 'id' => $cid]);
}

/**
 * Upload d'une pièce jointe.
 */
function upload_suggestion_attachment()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $sugId = $_POST['suggestion_id'] ?? $params['suggestion_id'] ?? '';
    if (!$sugId) bad_request('suggestion_id requis');

    $sug = Db::fetch("SELECT auteur_id FROM suggestions WHERE id = ?", [$sugId]);
    if (!$sug) not_found('Suggestion introuvable');
    if ($sug['auteur_id'] !== $userId) forbidden('Seul l\'auteur peut ajouter des pièces');

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur upload');
    }

    require_once __DIR__ . '/../core/FileSecurity.php';
    $err = FileSecurity::validateUpload($_FILES['file'], 'Fichier', FileSecurity::ALLOW_DOCUMENT, 10 * 1024 * 1024);
    if ($err) bad_request($err);

    // Détermine le kind
    $mime = mime_content_type($_FILES['file']['tmp_name']) ?: ($_FILES['file']['type'] ?? '');
    $kind = 'document';
    if (str_starts_with($mime, 'image/'))      $kind = 'photo';
    elseif (str_starts_with($mime, 'audio/'))  $kind = 'audio';

    $uploadDir = __DIR__ . '/../storage/suggestions/' . $sugId . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = Uuid::v4() . ($ext ? '.' . $ext : '');
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        bad_request('Impossible de sauvegarder le fichier');
    }

    $attId = Uuid::v4();
    Db::exec(
        "INSERT INTO suggestions_attachments
         (id, suggestion_id, filename, original_name, mime_type, size_bytes, kind)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$attId, $sugId, $filename, $_FILES['file']['name'], $mime ?: 'application/octet-stream', filesize($dest), $kind]
    );

    respond(['success' => true, 'id' => $attId, 'kind' => $kind]);
}

/**
 * Télécharger une pièce jointe (visible par tous les auth).
 */
function download_suggestion_attachment()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('id requis');

    $att = Db::fetch("SELECT * FROM suggestions_attachments WHERE id = ?", [$attId]);
    if (!$att) not_found('Pièce introuvable');

    $path = __DIR__ . '/../storage/suggestions/' . $att['suggestion_id'] . '/' . $att['filename'];
    if (!file_exists($path)) not_found('Fichier manquant');

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: ' . safe_content_disposition($att['original_name'], 'inline'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/**
 * Supprimer sa propre pièce jointe (avant traitement).
 */
function delete_suggestion_attachment()
{
    require_auth();
    _sug_require_enabled();
    global $params;

    $userId = $_SESSION['ss_user']['id'];
    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('id requis');

    $att = Db::fetch(
        "SELECT a.*, s.auteur_id, s.statut
         FROM suggestions_attachments a
         JOIN suggestions s ON s.id = a.suggestion_id
         WHERE a.id = ?",
        [$attId]
    );
    if (!$att) not_found('Pièce introuvable');
    if ($att['auteur_id'] !== $userId) forbidden('Seul l\'auteur peut supprimer');
    if ($att['statut'] !== 'nouvelle') forbidden('Suggestion déjà traitée');

    $path = __DIR__ . '/../storage/suggestions/' . $att['suggestion_id'] . '/' . $att['filename'];
    if (file_exists($path)) @unlink($path);
    Db::exec("DELETE FROM suggestions_attachments WHERE id = ?", [$attId]);

    respond(['success' => true]);
}
