<?php
/**
 * Admin API — Mur social (wall management, moderation, config)
 */

function admin_get_mur_config() {
    require_responsable();

    $rows = Db::fetchAll("SELECT config_key, config_value FROM mur_config");
    $config = [];
    foreach ($rows as $r) {
        $config[$r['config_key']] = $r['config_value'];
    }

    respond(['success' => true, 'config' => $config]);
}

function admin_save_mur_config() {
    require_admin();
    global $params;

    $allowed = [
        'moderation_enabled', 'allow_anonymous_comments', 'allow_private_posts',
        'allow_comments', 'allow_likes', 'max_posts_per_day', 'post_categories',
        'hero_title', 'hero_subtitle',
    ];

    $updates = $params['config'] ?? [];
    if (!is_array($updates)) bad_request('Invalid config');

    foreach ($updates as $key => $value) {
        if (!in_array($key, $allowed)) continue;
        $val = Sanitize::text($value);
        Db::exec(
            "INSERT INTO mur_config (config_key, config_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)",
            [$key, $val]
        );
    }

    respond(['success' => true, 'message' => 'Configuration sauvegardée']);
}

function admin_get_mur_posts() {
    require_responsable();
    global $params;

    $status = $params['status'] ?? 'all';
    $page   = max(1, (int)($params['page'] ?? 1));
    $limit  = min(50, max(10, (int)($params['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "p.deleted_at IS NULL";
    $binds = [];

    if ($status === 'pending') {
        $where .= " AND p.status = 'pending'";
    } elseif ($status === 'approved') {
        $where .= " AND p.status = 'approved'";
    } elseif ($status === 'rejected') {
        $where .= " AND p.status = 'rejected'";
    }

    $total = (int) Db::getOne("SELECT COUNT(*) FROM mur_posts p WHERE $where", $binds);

    $posts = Db::fetchAll(
        "SELECT p.*, u.prenom, u.nom, u.photo AS avatar_url,
                f.nom AS fonction_nom
         FROM mur_posts p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE $where
         ORDER BY p.is_pinned DESC, p.created_at DESC
         LIMIT $limit OFFSET $offset",
        $binds
    );

    respond([
        'success' => true,
        'posts' => $posts,
        'total' => $total,
        'page' => $page,
        'total_pages' => max(1, ceil($total / $limit)),
    ]);
}

function admin_moderate_mur_post() {
    require_responsable();
    global $params;

    $id     = $params['id'] ?? '';
    $action = $params['moderation'] ?? ''; // 'approve' or 'reject'
    if (!$id) bad_request('ID manquant');
    if (!in_array($action, ['approve', 'reject'])) bad_request('Action invalide');

    $status = $action === 'approve' ? 'approved' : 'rejected';
    Db::exec("UPDATE mur_posts SET status = ? WHERE id = ?", [$status, $id]);

    respond(['success' => true, 'message' => $action === 'approve' ? 'Post approuvé' : 'Post rejeté']);
}

function admin_delete_mur_post() {
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID manquant');

    Db::exec("UPDATE mur_posts SET deleted_at = NOW() WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Post supprimé']);
}

function admin_pin_mur_post() {
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID manquant');

    $current = Db::getOne("SELECT is_pinned FROM mur_posts WHERE id = ?", [$id]);
    if ($current === null) not_found();

    $newVal = $current ? 0 : 1;
    Db::exec("UPDATE mur_posts SET is_pinned = ? WHERE id = ?", [$newVal, $id]);

    respond(['success' => true, 'pinned' => (bool) $newVal]);
}

function admin_delete_mur_comment() {
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID manquant');

    $postId = Db::getOne("SELECT post_id FROM mur_comments WHERE id = ? AND deleted_at IS NULL", [$id]);
    if (!$postId) not_found();

    Db::exec("UPDATE mur_comments SET deleted_at = NOW() WHERE id = ?", [$id]);
    Db::exec("UPDATE mur_posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?", [$postId]);

    respond(['success' => true, 'message' => 'Commentaire supprimé']);
}

function admin_upload_mur_hero() {
    require_admin();

    if (empty($_FILES['hero_image']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier');
    }

    $file = $_FILES['hero_image'];
    if ($file['size'] > 5 * 1024 * 1024) bad_request('Max 5 Mo');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        bad_request('Format non supporté (JPG, PNG, WebP)');
    }

    $uploadDir = __DIR__ . '/../../storage/mur/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'hero_' . time() . '.webp';
    $destPath = $uploadDir . $filename;

    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
    };
    if (!$img) bad_request('Impossible de lire l\'image');
    imagewebp($img, $destPath, 82);
    imagedestroy($img);

    $url = '/zerdatime/storage/mur/' . $filename;
    Db::exec(
        "INSERT INTO mur_config (config_key, config_value) VALUES ('hero_image', ?)
         ON DUPLICATE KEY UPDATE config_value = ?",
        [$url, $url]
    );

    respond(['success' => true, 'url' => $url, 'message' => 'Image hero mise à jour']);
}

function admin_get_mur_stats() {
    require_responsable();

    $stats = Db::fetch(
        "SELECT
            (SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'approved') AS total_posts,
            (SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'pending') AS pending_posts,
            (SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND created_at >= CURDATE()) AS posts_today,
            (SELECT COUNT(*) FROM mur_comments WHERE deleted_at IS NULL) AS total_comments,
            (SELECT COUNT(*) FROM mur_likes) AS total_likes"
    );

    respond([
        'success' => true,
        'total_posts'   => (int) $stats['total_posts'],
        'pending_posts' => (int) $stats['pending_posts'],
        'posts_today'   => (int) $stats['posts_today'],
        'total_comments'=> (int) $stats['total_comments'],
        'total_likes'   => (int) $stats['total_likes'],
    ]);
}
