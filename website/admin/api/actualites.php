<?php
/**
 * Website Admin API — Gestion actualités et activités à venir
 */
require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');

// Auth
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$userId = $_SESSION['ss_user']['id'];

// Multipart vs JSON
$isMultipart = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
if ($isMultipart) {
    $input = $_POST;
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}
$action = $input['action'] ?? ($_GET['action'] ?? '');

// CSRF
$readActions = ['list', 'get', 'list_activites'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $readActions, true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');
    if (empty($_SESSION['ss_csrf_token']) || !$csrfToken || !hash_equals($_SESSION['ss_csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

// ── Helpers ──────────────────────────────────────────────

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return $text ?: 'actualite-' . substr(md5(uniqid('', true)), 0, 8);
}

function unique_slug(string $base, ?string $excludeId = null): string {
    $slug = $base;
    $i = 1;
    while (true) {
        $q = $excludeId
            ? "SELECT COUNT(*) FROM website_actualites WHERE slug = ? AND id <> ?"
            : "SELECT COUNT(*) FROM website_actualites WHERE slug = ?";
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $exists = (int) Db::getOne($q, $params);
        if ($exists === 0) return $slug;
        $i++;
        $slug = $base . '-' . $i;
    }
}

function upload_file(array $file, string $subdir, array $allowedMimes, int $maxSize): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max ' . round($maxSize / 1024 / 1024) . ' MB)']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Type de fichier non autorisé ($mime)"]);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    if (!$ext) {
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'bin',
        };
    }

    $uploadDir = __DIR__ . '/../../assets/uploads/' . $subdir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $destPath = $uploadDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de sauvegarde du fichier']);
        exit;
    }

    return "/spocspace/website/assets/uploads/$subdir/" . $name;
}

function delete_uploaded_file(?string $url): void {
    if (!$url) return;
    if (!str_starts_with($url, '/spocspace/website/assets/uploads/')) return;
    $path = __DIR__ . '/../../' . substr($url, strlen('/spocspace/website/'));
    if (is_file($path)) @unlink($path);
}

// ── Routes ────────────────────────────────────────────────

switch ($action) {

// ══ ACTUALITÉS ══
case 'list':
    $rows = Db::fetchAll(
        "SELECT id, slug, titre, type, extrait, cover_url, video_url, images, epingle, is_visible, published_at, created_at, updated_at
         FROM website_actualites
         ORDER BY epingle DESC, COALESCE(published_at, created_at) DESC"
    );
    foreach ($rows as &$r) {
        $r['images'] = $r['images'] ? (json_decode($r['images'], true) ?: []) : [];
    }
    echo json_encode(['success' => true, 'actualites' => $rows]);
    break;

case 'get':
    $id = $input['id'] ?? '';
    $row = Db::fetch("SELECT * FROM website_actualites WHERE id = ?", [$id]);
    if (!$row) { http_response_code(404); echo json_encode(['success' => false]); exit; }
    $row['images'] = $row['images'] ? (json_decode($row['images'], true) ?: []) : [];
    echo json_encode(['success' => true, 'actualite' => $row]);
    break;

case 'save':
    $id = trim($input['id'] ?? '');
    $titre = trim($input['titre'] ?? '');
    $type = $input['type'] ?? 'texte';
    $extrait = trim($input['extrait'] ?? '');
    $contenu = $input['contenu'] ?? '';
    $cover_url = trim($input['cover_url'] ?? '');
    $video_url = trim($input['video_url'] ?? '');
    $video_poster = trim($input['video_poster'] ?? '');
    $imagesArr = $input['images'] ?? [];
    if (is_string($imagesArr)) $imagesArr = json_decode($imagesArr, true) ?: [];
    $epingle = !empty($input['epingle']) ? 1 : 0;
    $is_visible = !empty($input['is_visible']) ? 1 : 0;
    $published_at = trim($input['published_at'] ?? '') ?: null;

    if (!$titre) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Titre requis']); exit; }
    if (!in_array($type, ['photo','video','affiche','texte','galerie'], true)) $type = 'texte';

    $imagesJson = $imagesArr ? json_encode(array_values($imagesArr), JSON_UNESCAPED_SLASHES) : null;

    if ($id) {
        // UPDATE
        $baseSlug = slugify($titre);
        $slug = unique_slug($baseSlug, $id);
        Db::exec(
            "UPDATE website_actualites SET
                slug = ?, titre = ?, type = ?, extrait = ?, contenu = ?,
                cover_url = ?, video_url = ?, video_poster = ?, images = ?,
                epingle = ?, is_visible = ?, published_at = ?, updated_by = ?
             WHERE id = ?",
            [$slug, $titre, $type, $extrait ?: null, $contenu ?: null,
             $cover_url ?: null, $video_url ?: null, $video_poster ?: null, $imagesJson,
             $epingle, $is_visible, $published_at, $userId, $id]
        );
    } else {
        // INSERT
        $id = Uuid::v4();
        $baseSlug = slugify($titre);
        $slug = unique_slug($baseSlug);
        $publishedAtVal = $published_at ?: ($is_visible ? date('Y-m-d H:i:s') : null);
        Db::exec(
            "INSERT INTO website_actualites
                (id, slug, titre, type, extrait, contenu, cover_url, video_url, video_poster, images,
                 epingle, is_visible, published_at, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $slug, $titre, $type, $extrait ?: null, $contenu ?: null,
             $cover_url ?: null, $video_url ?: null, $video_poster ?: null, $imagesJson,
             $epingle, $is_visible, $publishedAtVal, $userId, $userId]
        );
    }

    echo json_encode(['success' => true, 'id' => $id, 'slug' => $slug]);
    break;

case 'delete':
    $id = $input['id'] ?? '';
    $row = Db::fetch("SELECT cover_url, video_url, video_poster, images FROM website_actualites WHERE id = ?", [$id]);
    if ($row) {
        delete_uploaded_file($row['cover_url']);
        delete_uploaded_file($row['video_url']);
        delete_uploaded_file($row['video_poster']);
        if ($row['images']) {
            foreach ((json_decode($row['images'], true) ?: []) as $img) {
                delete_uploaded_file($img);
            }
        }
    }
    Db::exec("DELETE FROM website_actualites WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    break;

case 'toggle_pin':
    Db::exec("UPDATE website_actualites SET epingle = 1 - epingle WHERE id = ?", [$input['id'] ?? '']);
    echo json_encode(['success' => true]);
    break;

case 'toggle_visible':
    Db::exec("UPDATE website_actualites SET is_visible = 1 - is_visible, published_at = COALESCE(published_at, NOW()) WHERE id = ?", [$input['id'] ?? '']);
    echo json_encode(['success' => true]);
    break;

// ══ UPLOADS ══
case 'upload_image':
    if (!isset($_FILES['file'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Aucun fichier']); exit; }
    $url = upload_file(
        $_FILES['file'],
        'actualites',
        ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        15 * 1024 * 1024
    );
    echo json_encode(['success' => true, 'url' => $url]);
    break;

case 'upload_video':
    if (!isset($_FILES['file'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Aucun fichier']); exit; }
    $url = upload_file(
        $_FILES['file'],
        'actualites',
        ['video/mp4', 'video/webm', 'video/quicktime'],
        200 * 1024 * 1024
    );
    echo json_encode(['success' => true, 'url' => $url]);
    break;

// ══ ACTIVITÉS À VENIR ══
case 'list_activites':
    $rows = Db::fetchAll(
        "SELECT * FROM website_activites_venir ORDER BY date_activite ASC, heure_debut ASC, sort_order ASC"
    );
    echo json_encode(['success' => true, 'activites' => $rows]);
    break;

case 'save_activite':
    $id = trim($input['id'] ?? '');
    $titre = trim($input['titre'] ?? '');
    $description = trim($input['description'] ?? '');
    $date_activite = trim($input['date_activite'] ?? '');
    $heure_debut = trim($input['heure_debut'] ?? '') ?: null;
    $heure_fin = trim($input['heure_fin'] ?? '') ?: null;
    $lieu = trim($input['lieu'] ?? '');
    $image_url = trim($input['image_url'] ?? '');
    $icone = trim($input['icone'] ?? 'bi-calendar-event');
    $couleur = trim($input['couleur'] ?? '#2E7D32');
    $is_visible = !empty($input['is_visible']) ? 1 : 0;

    if (!$titre || !$date_activite) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Titre et date requis']);
        exit;
    }

    if ($id) {
        Db::exec(
            "UPDATE website_activites_venir SET
                titre=?, description=?, date_activite=?, heure_debut=?, heure_fin=?,
                lieu=?, image_url=?, icone=?, couleur=?, is_visible=?
             WHERE id=?",
            [$titre, $description ?: null, $date_activite, $heure_debut, $heure_fin,
             $lieu ?: null, $image_url ?: null, $icone, $couleur, $is_visible, $id]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO website_activites_venir
                (id, titre, description, date_activite, heure_debut, heure_fin, lieu, image_url, icone, couleur, is_visible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $titre, $description ?: null, $date_activite, $heure_debut, $heure_fin,
             $lieu ?: null, $image_url ?: null, $icone, $couleur, $is_visible]
        );
    }
    echo json_encode(['success' => true, 'id' => $id]);
    break;

case 'delete_activite':
    $id = $input['id'] ?? '';
    $row = Db::fetch("SELECT image_url FROM website_activites_venir WHERE id = ?", [$id]);
    if ($row) delete_uploaded_file($row['image_url']);
    Db::exec("DELETE FROM website_activites_venir WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    break;

default:
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
