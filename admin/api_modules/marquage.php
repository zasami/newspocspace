<?php
/**
 * Marquage lingerie — API module
 */

function admin_get_marquages() {
    global $params;
    $where = "1=1";
    $binds = [];

    if (!empty($params['resident_id'])) {
        $where .= " AND m.resident_id = ?";
        $binds[] = $params['resident_id'];
    }
    if (!empty($params['statut'])) {
        $where .= " AND m.statut = ?";
        $binds[] = $params['statut'];
    }
    if (!empty($params['search'])) {
        $where .= " AND (r.nom LIKE ? OR r.prenom LIKE ? OR r.chambre LIKE ?)";
        $s = '%' . $params['search'] . '%';
        $binds = array_merge($binds, [$s, $s, $s]);
    }

    $rows = Db::fetchAll(
        "SELECT m.*, r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre,
                u.prenom AS user_prenom, u.nom AS user_nom,
                cu.prenom AS completed_prenom, cu.nom AS completed_nom
         FROM marquages m
         JOIN residents r ON r.id = m.resident_id
         JOIN users u ON u.id = m.user_id
         LEFT JOIN users cu ON cu.id = m.completed_by
         WHERE $where
         ORDER BY m.created_at DESC
         LIMIT 200",
        $binds
    );

    // Stats
    $stats = Db::fetch(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(m.statut = 'en_cours'), 0) AS en_cours,
            COALESCE(SUM(m.statut = 'marqué'), 0) AS marques,
            COALESCE(SUM(m.statut = 'terminé'), 0) AS termines,
            COUNT(DISTINCT m.resident_id) AS residents_count,
            COUNT(DISTINCT r.chambre) AS chambres_count
         FROM marquages m
         LEFT JOIN residents r ON r.id = m.resident_id"
    );
    if (!$stats) $stats = ['total'=>0,'en_cours'=>0,'marques'=>0,'termines'=>0,'residents_count'=>0,'chambres_count'=>0];

    respond(['marquages' => $rows, 'stats' => $stats]);
}

function admin_create_marquage() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    $action = $params['action_type'] ?? 'marquer';
    $quantite = max(1, intval($params['quantite'] ?? 1));
    $description = trim($params['description'] ?? '');
    $userId = $_SESSION['admin']['id'];

    if (!$residentId) bad_request('Résident requis');

    $allowed = ['marquer','laver','repasser','reparer','autre'];
    if (!in_array($action, $allowed)) $action = 'marquer';

    $id = Uuid::v4();

    Db::exec(
        "INSERT INTO marquages (id, resident_id, user_id, action, quantite, description)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $residentId, $userId, $action, $quantite, $description]
    );

    respond(['id' => $id, 'message' => 'Marquage créé']);
}

function admin_upload_marquage_photo() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $row = Db::fetch("SELECT id, photo_path FROM marquages WHERE id = ?", [$id]);
    if (!$row) not_found();

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier photo requis');
    }

    $file = $_FILES['photo'];
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) bad_request('Photo trop volumineuse (max 10 Mo)');

    $allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMimes)) bad_request('Format image non supporté');

    $dir = __DIR__ . '/../../storage/marquage/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . $filename);

    // Append to existing photos (comma-separated)
    $existing = $row['photo_path'] ? explode(',', $row['photo_path']) : [];
    $existing[] = $filename;
    $newPath = implode(',', $existing);
    Db::exec("UPDATE marquages SET photo_path = ? WHERE id = ?", [$newPath, $id]);

    respond(['message' => 'Photo téléversée', 'photo' => $filename, 'all_photos' => $existing]);
}

function admin_serve_marquage_photo() {
    global $params;
    $file = basename($params['file'] ?? '');
    if (!$file) {
        // Fallback: serve first photo by marquage id
        $id = $params['id'] ?? '';
        $row = Db::fetch("SELECT photo_path FROM marquages WHERE id = ?", [$id]);
        if (!$row || !$row['photo_path']) not_found();
        $file = explode(',', $row['photo_path'])[0];
    }

    $path = __DIR__ . '/../../storage/marquage/' . $file;
    if (!file_exists($path)) not_found();

    $mime = mime_content_type($path);
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
}

function admin_update_marquage_statut() {
    global $params;
    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    if (!$id || !$statut) bad_request('ID et statut requis');

    $allowed = ['en_cours','marqué','terminé'];
    if (!in_array($statut, $allowed)) bad_request('Statut invalide');

    $completedBy = in_array($statut, ['marqué','terminé']) ? $_SESSION['admin']['id'] : null;
    $completedAt = in_array($statut, ['marqué','terminé']) ? date('Y-m-d H:i:s') : null;

    Db::exec(
        "UPDATE marquages SET statut = ?, completed_by = ?, completed_at = ? WHERE id = ?",
        [$statut, $completedBy, $completedAt, $id]
    );

    respond(['message' => 'Statut mis à jour']);
}

function admin_delete_marquage() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $row = Db::fetch("SELECT photo_path FROM marquages WHERE id = ?", [$id]);
    if (!$row) not_found();

    if ($row['photo_path']) {
        foreach (explode(',', $row['photo_path']) as $photo) {
            $path = __DIR__ . '/../../storage/marquage/' . $photo;
            if (file_exists($path)) @unlink($path);
        }
    }

    Db::exec("DELETE FROM marquages WHERE id = ?", [$id]);
    respond(['message' => 'Marquage supprimé']);
}

function admin_get_marquage_history() {
    global $params;
    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('Résident requis');

    $rows = Db::fetchAll(
        "SELECT m.*, u.prenom AS user_prenom, u.nom AS user_nom,
                cu.prenom AS completed_prenom, cu.nom AS completed_nom
         FROM marquages m
         JOIN users u ON u.id = m.user_id
         LEFT JOIN users cu ON cu.id = m.completed_by
         WHERE m.resident_id = ?
         ORDER BY m.created_at DESC
         LIMIT 100",
        [$residentId]
    );

    $stats = Db::fetch(
        "SELECT COUNT(*) AS total,
                SUM(statut = 'en_cours') AS en_cours,
                SUM(statut = 'marqué') AS marques,
                SUM(statut = 'terminé') AS termines
         FROM marquages WHERE resident_id = ?",
        [$residentId]
    );

    respond(['history' => $rows, 'stats' => $stats]);
}
