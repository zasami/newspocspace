<?php
require_once __DIR__ . '/../core/Notification.php';

/**
 * Employee PV API actions
 */

function get_pv_list()
{
    global $params;
    require_auth();

    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min((int)($params['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $offset = ($page - 1) * $limit;

    // Filters
    $filters = ['pv.is_public = 1', 'pv.is_active = 1'];
    $bindings = [];

    // Hide brouillon PVs for non-admin users
    $user = $_SESSION['ss_user'];
    if (!in_array($user['role'], ['admin', 'direction', 'responsable'])) {
        $filters[] = "pv.statut != 'brouillon'";
    }
    
    if (!empty($params['module_id'])) {
        $filters[] = 'pv.module_id = ?';
        $bindings[] = $params['module_id'];
    }
    if (!empty($params['search'])) {
        $filters[] = '(pv.titre LIKE ? OR pv.contenu LIKE ?)';
        $search = '%' . $params['search'] . '%';
        $bindings[] = $search;
        $bindings[] = $search;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $filters);

    $list = Db::fetchAll(
        "SELECT pv.id, pv.titre, pv.description, pv.created_by, pv.module_id, pv.statut, pv.is_public, 
                pv.participants, pv.contenu, pv.created_at, pv.updated_at, pv.note, pv.allow_comments,
                u.prenom, u.nom,
                f.code AS fonction_code,
                m.code AS module_code, m.nom AS module_nom
         FROM pv
         LEFT JOIN users u ON u.id = pv.created_by
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN modules m ON m.id = pv.module_id
         {$whereClause}
         ORDER BY pv.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($bindings, [$limit, $offset])
    );

    // Get total count
    $totalResult = Db::fetch(
        "SELECT COUNT(*) as cnt FROM pv {$whereClause}",
        $bindings
    );
    $total = $totalResult['cnt'] ?? 0;

    // Parse JSON fields
    foreach ($list as &$item) {
        $item['participants'] = !empty($item['participants']) ? json_decode($item['participants'], true) : [];
        $item['tags'] = !empty($item['tags']) ? json_decode($item['tags'], true) : [];
    }

    respond([
        'success' => true,
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit),
    ]);
}

function get_pv()
{
    global $params;
    require_auth();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch(
        "SELECT pv.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                f.code AS fonction_code, f.nom AS fonction_nom,
                m.code AS module_code, m.nom AS module_nom
         FROM pv
         LEFT JOIN users u ON u.id = pv.created_by
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN modules m ON m.id = pv.module_id
         WHERE pv.id = ? AND (pv.is_public = 1 OR pv.created_by = ?)",
        [$pvId, $_SESSION['ss_user']['id']]
    );

    if (!$pv) not_found('PV non trouvé');

    // Parse JSON fields
    $pv['participants'] = !empty($pv['participants']) ? json_decode($pv['participants'], true) : [];
    $pv['tags'] = !empty($pv['tags']) ? json_decode($pv['tags'], true) : [];

    // Get comments
    $comments = Db::fetchAll(
        "SELECT c.*, u.prenom, u.nom, u.photo, u.fonction_id, f.code AS fonction_code
         FROM pv_comments c
         JOIN users u ON c.user_id = u.id
         LEFT JOIN fonctions f ON u.fonction_id = f.id
         WHERE c.pv_id = ? AND c.is_active = 1
         ORDER BY c.created_at ASC",
        [$pvId]
    );

    // Add likes for each comment
    $currentUserId = $_SESSION['ss_user']['id'];
    foreach ($comments as &$c) {
        $c['likes'] = Db::fetchAll(
            "SELECT l.user_id, u.prenom, u.nom, u.photo
             FROM pv_comment_likes l
             JOIN users u ON u.id = l.user_id
             WHERE l.comment_id = ?
             ORDER BY l.created_at ASC",
            [$c['id']]
        );
        $c['liked_by_me'] = !empty(array_filter($c['likes'], fn($l) => $l['user_id'] === $currentUserId));
    }
    unset($c);

    respond([
        'success' => true,
        'pv' => $pv,
        'comments' => $comments
    ]);
}

function get_pv_refs()
{
    global $params;
    require_auth();

    $modules = Db::fetchAll(
        "SELECT id, nom, code FROM modules ORDER BY ordre, nom"
    );

    respond([
        'success' => true,
        'modules' => $modules,
    ]);
}

function get_recent_pv()
{
    global $params;
    require_auth();

    $limit = min((int)($params['limit'] ?? 5), 20);

    $list = Db::fetchAll(
        "SELECT id, titre, statut, created_at, prenom, nom
         FROM (
           SELECT pv.*, u.prenom, u.nom, ROW_NUMBER() OVER (ORDER BY pv.created_at DESC) as rn
           FROM pv
           LEFT JOIN users u ON u.id = pv.created_by
           WHERE pv.is_public = 1
           LIMIT ?
         ) AS recent
         ORDER BY created_at DESC",
        [$limit * 5]
    );

    respond([
        'success' => true,
        'recent' => array_slice($list, 0, $limit),
    ]);
}

function comment_pv()
{
    global $params;
    require_auth();

    $pvId = $params['pv_id'] ?? '';
    $contenu = $params['contenu'] ?? '';
    if (empty($pvId)) bad_request('ID requis');
    if (empty(trim(strip_tags($contenu)))) bad_request('Contenu vide');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');
    if (!$pv['allow_comments']) bad_request('Commentaires désactivés');

    $id = Uuid::v4();
    $userId = $_SESSION['ss_user']['id'];

    Db::exec(
        "INSERT INTO pv_comments (id, pv_id, user_id, contenu) VALUES (?, ?, ?, ?)",
        [$id, $pvId, $userId, $contenu]
    );

    // Notify PV creator if different from commenter
    if ($pv['created_by'] && $pv['created_by'] !== $userId) {
        $commenter = Db::fetch("SELECT prenom, nom FROM users WHERE id = ?", [$userId]);
        $name = ($commenter['prenom'] ?? '') . ' ' . ($commenter['nom'] ?? '');
        Notification::create($pv['created_by'], 'pv_commentaire', 'Nouveau commentaire',
            trim($name) . " a commenté le PV « {$pv['titre']} ».", 'pv');
    }

    respond([
        'success' => true,
        'message' => 'Commentaire ajouté'
    ]);
}

function toggle_pv_comment_like()
{
    global $params;
    require_auth();

    $commentId = $params['comment_id'] ?? '';
    if (!$commentId) bad_request('comment_id requis');

    $userId = $_SESSION['ss_user']['id'];

    $existing = Db::fetch("SELECT id FROM pv_comment_likes WHERE comment_id = ? AND user_id = ?", [$commentId, $userId]);
    if ($existing) {
        Db::exec("DELETE FROM pv_comment_likes WHERE id = ?", [$existing['id']]);
        respond(['success' => true, 'liked' => false]);
    } else {
        Db::exec("INSERT INTO pv_comment_likes (id, comment_id, user_id) VALUES (?, ?, ?)", [Uuid::v4(), $commentId, $userId]);
        respond(['success' => true, 'liked' => true]);
    }
}

function rate_pv()
{
    global $params;
    require_auth();

    $pvId = $params['pv_id'] ?? '';
    $note = isset($params['note']) ? (int)$params['note'] : 0;
    
    if (empty($pvId)) bad_request('ID requis');
    if ($note < 1 || $note > 5) bad_request('Note invalide');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');
    if (!$pv['allow_comments']) bad_request('Notes désactivées');

    Db::exec("UPDATE pv SET note = ? WHERE id = ?", [$note, $pvId]);

    respond([
        'success' => true,
        'message' => 'Note enregistrée'
    ]);
}
