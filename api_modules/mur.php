<?php
/**
 * Employee API — Mur social (wall posts, comments, likes)
 */

// ── Helper: load wall config ──
function _mur_config(?string $key = null) {
    static $cfg = null;
    if ($cfg === null) {
        $rows = Db::fetchAll("SELECT config_key, config_value FROM mur_config");
        $cfg = [];
        foreach ($rows as $r) $cfg[$r['config_key']] = $r['config_value'];
    }
    return $key !== null ? ($cfg[$key] ?? null) : $cfg;
}

// ── Get wall config (public) ──
function get_mur_config() {
    require_auth();
    $cfg = _mur_config();
    // Get EMS logo as default hero avatar
    $emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '/zerdatime/logo.png';
    $emsNom  = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'zerdaTime';

    respond([
        'success' => true,
        'config' => [
            'allow_comments'        => $cfg['allow_comments'] ?? '1',
            'allow_likes'           => $cfg['allow_likes'] ?? '1',
            'allow_anonymous_comments' => $cfg['allow_anonymous_comments'] ?? '0',
            'allow_private_posts'   => $cfg['allow_private_posts'] ?? '0',
            'post_categories'       => $cfg['post_categories'] ?? 'general,info,evenement,social',
            'hero_image'            => $cfg['hero_image'] ?? '',
            'hero_title'            => $cfg['hero_title'] ?? 'Mur social',
            'hero_subtitle'         => $cfg['hero_subtitle'] ?? '',
            'ems_logo'              => $emsLogo,
            'ems_name'              => $emsNom,
        ],
    ]);
}

// ── Get feed ──
function get_mur_feed() {
    $user = require_auth();
    global $params;

    $page  = max(1, (int)($params['page'] ?? 1));
    $limit = min(50, max(5, (int)($params['limit'] ?? 15)));
    $offset = ($page - 1) * $limit;

    $total = (int) Db::getOne(
        "SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'approved'"
    );

    $posts = Db::fetchAll(
        "SELECT p.id, p.user_id, p.body, p.category, p.is_anonymous, p.is_pinned,
                p.likes_count, p.comments_count, p.created_at,
                u.prenom, u.nom, u.photo AS avatar_url,
                f.nom AS fonction_nom,
                (SELECT COUNT(*) FROM mur_likes l WHERE l.target_type = 'post' AND l.target_id = p.id AND l.user_id = ?) AS is_liked
         FROM mur_posts p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE p.deleted_at IS NULL AND p.status = 'approved'
         ORDER BY p.is_pinned DESC, p.created_at DESC
         LIMIT $limit OFFSET $offset",
        [$user['id']]
    );

    // Fetch media for all posts in one query
    $postIds = array_column($posts, 'id');
    $mediaByPost = [];
    if ($postIds) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $allMedia = Db::fetchAll(
            "SELECT id, post_id, url, type FROM mur_media WHERE post_id IN ($placeholders) ORDER BY created_at ASC",
            $postIds
        );
        foreach ($allMedia as $m) {
            $mediaByPost[$m['post_id']][] = $m;
        }
    }

    // Mask author for anonymous posts (unless own post)
    foreach ($posts as &$post) {
        $post['is_liked'] = (int) $post['is_liked'] > 0;
        $post['media'] = $mediaByPost[$post['id']] ?? [];
        if ($post['is_anonymous'] && $post['user_id'] !== $user['id']) {
            $post['prenom'] = 'Anonyme';
            $post['nom'] = '';
            $post['avatar_url'] = null;
            $post['fonction_nom'] = null;
            $post['user_id'] = null;
        }
    }
    unset($post);

    respond([
        'success' => true,
        'posts' => $posts,
        'total' => $total,
        'page' => $page,
        'total_pages' => max(1, ceil($total / $limit)),
    ]);
}

// ── Create post ──
function create_mur_post() {
    $user = require_auth();
    global $params;

    $body = Sanitize::text(trim($params['body'] ?? ''));
    $category = $params['category'] ?? 'general';
    $isAnonymous = !empty($params['is_anonymous']) ? 1 : 0;

    if ($body === '') bad_request('Le contenu est requis');

    $cfg = _mur_config();

    // Validate category
    $validCategories = array_map('trim', explode(',', $cfg['post_categories'] ?? 'general'));
    if (!in_array($category, $validCategories)) $category = 'general';

    // Check daily limit
    $maxPerDay = (int)($cfg['max_posts_per_day'] ?? 10);
    $todayCount = (int) Db::getOne(
        "SELECT COUNT(*) FROM mur_posts WHERE user_id = ? AND deleted_at IS NULL AND DATE(created_at) = CURDATE()",
        [$user['id']]
    );
    if ($todayCount >= $maxPerDay) {
        bad_request("Limite de $maxPerDay posts par jour atteinte");
    }

    // Determine initial status
    $status = ($cfg['moderation_enabled'] ?? '0') === '1' ? 'pending' : 'approved';

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO mur_posts (id, user_id, body, category, is_anonymous, status) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $user['id'], $body, $category, $isAnonymous, $status]
    );

    $msg = $status === 'pending' ? 'Post soumis, en attente de validation' : 'Post publié';
    respond(['success' => true, 'message' => $msg, 'id' => $id, 'status' => $status]);
}

// ── Update post ──
function update_mur_post() {
    $user = require_auth();
    global $params;

    $id   = $params['id'] ?? '';
    $body = Sanitize::text(trim($params['body'] ?? ''));
    if (!$id) bad_request('ID manquant');
    if ($body === '') bad_request('Le contenu est requis');

    $post = Db::fetch("SELECT user_id FROM mur_posts WHERE id = ? AND deleted_at IS NULL", [$id]);
    if (!$post) not_found();
    if ($post['user_id'] !== $user['id']) forbidden();

    Db::exec("UPDATE mur_posts SET body = ?, updated_at = NOW() WHERE id = ?", [$body, $id]);
    respond(['success' => true, 'message' => 'Post modifié']);
}

// ── Delete own post ──
function delete_mur_post() {
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID manquant');

    $post = Db::fetch("SELECT user_id FROM mur_posts WHERE id = ? AND deleted_at IS NULL", [$id]);
    if (!$post) not_found();

    // Allow owner or admin/responsable
    if ($post['user_id'] !== $user['id'] && !in_array($user['role'], ['admin', 'direction', 'responsable'])) {
        forbidden();
    }

    Db::exec("UPDATE mur_posts SET deleted_at = NOW() WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Post supprimé']);
}

// ── Toggle like ──
function toggle_mur_like() {
    $user = require_auth();
    global $params;

    if ((_mur_config('allow_likes') ?? '1') !== '1') {
        bad_request('Likes désactivés');
    }

    $targetType = $params['target_type'] ?? 'post';
    $targetId   = $params['target_id'] ?? '';
    if (!$targetId) bad_request('ID manquant');
    if (!in_array($targetType, ['post', 'comment'])) bad_request('Type invalide');

    $existing = Db::getOne(
        "SELECT id FROM mur_likes WHERE target_type = ? AND target_id = ? AND user_id = ?",
        [$targetType, $targetId, $user['id']]
    );

    if ($existing) {
        Db::exec("DELETE FROM mur_likes WHERE id = ?", [$existing]);
        if ($targetType === 'post') {
            Db::exec("UPDATE mur_posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?", [$targetId]);
        }
        $liked = false;
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO mur_likes (id, target_type, target_id, user_id) VALUES (?, ?, ?, ?)",
            [$id, $targetType, $targetId, $user['id']]
        );
        if ($targetType === 'post') {
            Db::exec("UPDATE mur_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);
        }
        $liked = true;
    }

    $count = (int) Db::getOne(
        "SELECT COUNT(*) FROM mur_likes WHERE target_type = ? AND target_id = ?",
        [$targetType, $targetId]
    );

    respond(['success' => true, 'liked' => $liked, 'count' => $count]);
}

// ── Get comments ──
function get_mur_comments() {
    $user = require_auth();
    global $params;

    $postId = $params['post_id'] ?? '';
    if (!$postId) bad_request('post_id manquant');

    $comments = Db::fetchAll(
        "SELECT c.id, c.user_id, c.body, c.is_anonymous, c.created_at,
                u.prenom, u.nom, u.photo AS avatar_url
         FROM mur_comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.deleted_at IS NULL
         ORDER BY c.created_at ASC",
        [$postId]
    );

    foreach ($comments as &$c) {
        if ($c['is_anonymous'] && $c['user_id'] !== $user['id']) {
            $c['prenom'] = 'Anonyme';
            $c['nom'] = '';
            $c['avatar_url'] = null;
            $c['user_id'] = null;
        }
    }
    unset($c);

    respond(['success' => true, 'comments' => $comments]);
}

// ── Add comment ──
function add_mur_comment() {
    $user = require_auth();
    global $params;

    if ((_mur_config('allow_comments') ?? '1') !== '1') {
        bad_request('Commentaires désactivés');
    }

    $postId = $params['post_id'] ?? '';
    $body   = Sanitize::text(trim($params['body'] ?? ''));
    $isAnonymous = !empty($params['is_anonymous']) ? 1 : 0;

    if (!$postId) bad_request('post_id manquant');
    if ($body === '') bad_request('Le commentaire est vide');

    // Check anonymous permission
    if ($isAnonymous && (_mur_config('allow_anonymous_comments') ?? '0') !== '1') {
        $isAnonymous = 0;
    }

    // Verify post exists
    $exists = Db::getOne("SELECT id FROM mur_posts WHERE id = ? AND deleted_at IS NULL AND status = 'approved'", [$postId]);
    if (!$exists) not_found();

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO mur_comments (id, post_id, user_id, body, is_anonymous) VALUES (?, ?, ?, ?, ?)",
        [$id, $postId, $user['id'], $body, $isAnonymous]
    );
    Db::exec("UPDATE mur_posts SET comments_count = comments_count + 1 WHERE id = ?", [$postId]);

    respond([
        'success' => true,
        'message' => 'Commentaire ajouté',
        'comment' => [
            'id' => $id,
            'user_id' => $isAnonymous ? null : $user['id'],
            'prenom' => $isAnonymous ? 'Anonyme' : $user['prenom'],
            'nom' => $isAnonymous ? '' : $user['nom'],
            'avatar_url' => $isAnonymous ? null : ($user['avatar_url'] ?? null),
            'body' => $body,
            'is_anonymous' => $isAnonymous,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

// ── Delete own comment ──
function delete_mur_comment() {
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID manquant');

    $comment = Db::fetch("SELECT user_id, post_id FROM mur_comments WHERE id = ? AND deleted_at IS NULL", [$id]);
    if (!$comment) not_found();

    if ($comment['user_id'] !== $user['id'] && !in_array($user['role'], ['admin', 'direction', 'responsable'])) {
        forbidden();
    }

    Db::exec("UPDATE mur_comments SET deleted_at = NOW() WHERE id = ?", [$id]);
    Db::exec("UPDATE mur_posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?", [$comment['post_id']]);

    respond(['success' => true, 'message' => 'Commentaire supprimé']);
}

// ── Upload media with post ──
function upload_mur_media() {
    $user = require_auth();
    global $params;

    $postId = $params['post_id'] ?? '';
    if (!$postId) bad_request('post_id manquant');

    // Verify ownership
    $post = Db::fetch("SELECT user_id FROM mur_posts WHERE id = ? AND deleted_at IS NULL", [$postId]);
    if (!$post || $post['user_id'] !== $user['id']) forbidden();

    $uploadDir = __DIR__ . '/../storage/mur/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $uploaded = [];
    for ($i = 0; $i < 4; $i++) {
        $key = "file_$i";
        if (empty($_FILES[$key]['tmp_name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;

        $file = $_FILES[$key];
        if ($file['size'] > 8 * 1024 * 1024) continue; // 8MB max

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) continue;

        $filename = $postId . '_' . $i . '_' . time() . '.webp';
        $destPath = $uploadDir . $filename;

        $img = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            'image/gif'  => imagecreatefromgif($file['tmp_name']),
            default => null,
        };
        if (!$img) continue;
        imagewebp($img, $destPath, 82);
        imagedestroy($img);

        $url = '/zerdatime/storage/mur/' . $filename;
        $mediaId = Uuid::v4();
        Db::exec(
            "INSERT INTO mur_media (id, post_id, user_id, type, filename, url) VALUES (?, ?, ?, 'image', ?, ?)",
            [$mediaId, $postId, $user['id'], $filename, $url]
        );
        $uploaded[] = ['id' => $mediaId, 'url' => $url];
    }

    respond(['success' => true, 'media' => $uploaded, 'count' => count($uploaded)]);
}

// ── Get recent media (gallery) ──
function get_mur_gallery() {
    require_auth();
    global $params;

    $limit = min(20, max(4, (int)($params['limit'] ?? 8)));

    $media = Db::fetchAll(
        "SELECT m.id, m.url, m.type, m.created_at, m.post_id,
                u.prenom, u.nom
         FROM mur_media m
         JOIN users u ON u.id = m.user_id
         JOIN mur_posts p ON p.id = m.post_id AND p.deleted_at IS NULL AND p.status = 'approved'
         ORDER BY m.created_at DESC
         LIMIT $limit"
    );

    respond(['success' => true, 'media' => $media]);
}

// ── Wall stats ──
function get_mur_stats() {
    require_auth();

    $stats = Db::fetch(
        "SELECT
            (SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'approved') AS total_posts,
            (SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'approved' AND created_at >= CURDATE()) AS posts_today,
            (SELECT COUNT(DISTINCT user_id) FROM mur_posts WHERE deleted_at IS NULL AND status = 'approved') AS contributors"
    );

    respond([
        'success' => true,
        'total_posts'  => (int) $stats['total_posts'],
        'posts_today'  => (int) $stats['posts_today'],
        'contributors' => (int) $stats['contributors'],
    ]);
}
