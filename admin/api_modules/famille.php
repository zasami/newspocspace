<?php
/**
 * Admin API — Espace Famille management
 * Upload activités, médical, galerie (staff only — famille is read-only)
 */

/**
 * Resolve a file_path from DB to an absolute path inside uploads/famille/,
 * rejecting any traversal attempt. Returns absolute path or null.
 * Accepted input shape: "uploads/famille/<resident>/<sub>/<uuid>.enc".
 */
function _famille_resolve_path(?string $filePath): ?string
{
    if (!$filePath || !is_string($filePath)) return null;
    if (strpos($filePath, "\0") !== false) return null;
    if (strpos($filePath, '\\') !== false) return null;
    // Must start exactly with the expected relative prefix
    if (strpos($filePath, 'uploads/famille/') !== 0) return null;
    // Reject traversal
    $parts = explode('/', $filePath);
    foreach ($parts as $p) {
        if ($p === '..' || $p === '.' || $p === '') return null;
    }
    $absBase = realpath(__DIR__ . '/../../uploads/famille');
    if ($absBase === false) return null;
    $absBase = rtrim(str_replace('\\', '/', $absBase), '/');

    $abs = __DIR__ . '/../../' . $filePath;
    // Resolve highest existing ancestor (file may or may not exist)
    $dir = dirname($abs);
    if (!is_dir($dir)) return null;
    $dirReal = realpath($dir);
    if ($dirReal === false) return null;
    $dirReal = rtrim(str_replace('\\', '/', $dirReal), '/');
    if (strpos($dirReal . '/', $absBase . '/') !== 0) return null;

    return $dirReal . '/' . basename($abs);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Encryption key management
// ═══════════════════════════════════════════════════════════════════════════════

function admin_famille_setup_key()
{
    global $params;
    require_responsable();

    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('resident_id requis');

    $resident = Db::fetch("SELECT id FROM residents WHERE id = ?", [$residentId]);
    if (!$resident) not_found('Résident introuvable');

    // Client sends the wrapped key (encrypted by PBKDF2(code_acces))
    $encryptedKey = $params['encrypted_key'] ?? '';
    $salt = $params['salt'] ?? '';
    $iv = $params['iv'] ?? '';

    if (!$encryptedKey || !$salt || !$iv) bad_request('Clé de chiffrement incomplète');

    // Upsert
    $existing = Db::fetch("SELECT id FROM famille_encryption_keys WHERE resident_id = ?", [$residentId]);
    if ($existing) {
        Db::exec(
            "UPDATE famille_encryption_keys SET encrypted_key = ?, salt = ?, iv = ?, updated_at = NOW() WHERE resident_id = ?",
            [$encryptedKey, $salt, $iv, $residentId]
        );
    } else {
        Db::exec(
            "INSERT INTO famille_encryption_keys (id, resident_id, encrypted_key, salt, iv) VALUES (?, ?, ?, ?, ?)",
            [Uuid::v4(), $residentId, $encryptedKey, $salt, $iv]
        );
    }

    respond(['success' => true, 'message' => 'Clé de chiffrement enregistrée']);
}

function admin_famille_get_key()
{
    global $params;
    require_responsable();

    $residentId = $params['resident_id'] ?? '';
    $key = Db::fetch("SELECT encrypted_key, salt, iv FROM famille_encryption_keys WHERE resident_id = ?", [$residentId]);
    respond(['success' => true, 'key' => $key]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Activités
// ═══════════════════════════════════════════════════════════════════════════════

function admin_famille_get_activites()
{
    global $params;
    require_responsable();

    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('resident_id requis');

    $activites = Db::fetchAll(
        "SELECT a.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                (SELECT COUNT(*) FROM famille_activite_photos WHERE activite_id = a.id) AS nb_photos
         FROM famille_activites a
         LEFT JOIN users u ON u.id = a.created_by
         WHERE a.resident_id = ?
         ORDER BY a.date_activite DESC",
        [$residentId]
    );

    respond(['success' => true, 'activites' => $activites]);
}

function admin_famille_save_activite()
{
    global $params;
    $user = require_responsable();

    $id = $params['id'] ?? '';
    $residentId = $params['resident_id'] ?? '';
    $titre = trim($params['titre'] ?? '');
    $description = trim($params['description'] ?? '');
    $dateActivite = $params['date_activite'] ?? date('Y-m-d');

    if (!$residentId || !$titre) bad_request('Champs requis manquants');

    if ($id) {
        Db::exec(
            "UPDATE famille_activites SET titre = ?, description = ?, date_activite = ? WHERE id = ? AND resident_id = ?",
            [$titre, $description, $dateActivite, $id, $residentId]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO famille_activites (id, resident_id, titre, description, date_activite, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $residentId, $titre, $description, $dateActivite, $user['id']]
        );
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Activité enregistrée']);
}

function admin_famille_delete_activite()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    // Delete photos files (path-safe)
    $photos = Db::fetchAll("SELECT file_path FROM famille_activite_photos WHERE activite_id = ?", [$id]);
    foreach ($photos as $p) {
        $path = _famille_resolve_path($p['file_path'] ?? null);
        if ($path && is_file($path)) @unlink($path);
    }

    Db::exec("DELETE FROM famille_activites WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Activité supprimée']);
}

function admin_famille_upload_activite_photo()
{
    require_responsable();

    $activiteId = $_POST['activite_id'] ?? '';
    $encryptedIv = $_POST['encrypted_iv'] ?? '';

    if (!$activiteId || !$encryptedIv) bad_request('Paramètres manquants');

    $activite = Db::fetch("SELECT resident_id FROM famille_activites WHERE id = ?", [$activiteId]);
    if (!$activite) not_found('Activité introuvable');

    if (empty($_FILES['file'])) bad_request('Aucun fichier');

    $file = $_FILES['file'];
    $residentId = $activite['resident_id'];
    $dir = 'uploads/famille/' . $residentId . '/activites';
    $absDir = __DIR__ . '/../../' . $dir;
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);

    $fileName = $_POST['file_name'] ?? $file['name'];
    $storedName = Uuid::v4() . '.enc';
    $destPath = $dir . '/' . $storedName;

    move_uploaded_file($file['tmp_name'], $absDir . '/' . $storedName);

    $photoId = Uuid::v4();
    $ordre = (int) Db::getOne("SELECT COALESCE(MAX(ordre), 0) + 1 FROM famille_activite_photos WHERE activite_id = ?", [$activiteId]);
    Db::exec(
        "INSERT INTO famille_activite_photos (id, activite_id, file_path, file_name, encrypted_iv, ordre) VALUES (?, ?, ?, ?, ?, ?)",
        [$photoId, $activiteId, $destPath, $fileName, $encryptedIv, $ordre]
    );

    respond(['success' => true, 'id' => $photoId, 'message' => 'Photo ajoutée']);
}

function admin_famille_delete_photo()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $type = $params['type'] ?? ''; // activite_photo, medical_fichier, galerie_photo

    $table = '';
    if ($type === 'activite_photo') $table = 'famille_activite_photos';
    elseif ($type === 'medical_fichier') $table = 'famille_medical_fichiers';
    elseif ($type === 'galerie_photo') $table = 'famille_galerie_photos';
    else bad_request('Type invalide');

    $row = Db::fetch("SELECT file_path FROM $table WHERE id = ?", [$id]);
    if (!$row) not_found('Fichier introuvable');

    $path = _famille_resolve_path($row['file_path'] ?? null);
    if ($path && is_file($path)) @unlink($path);

    Db::exec("DELETE FROM $table WHERE id = ?", [$id]);

    // If this was a gallery cover photo, replace with the next available photo
    if ($type === 'galerie_photo') {
        $album = Db::fetch("SELECT id FROM famille_galerie WHERE cover_photo_id = ?", [$id]);
        if ($album) {
            $next = Db::getOne("SELECT id FROM famille_galerie_photos WHERE galerie_id = ? ORDER BY ordre ASC LIMIT 1", [$album['id']]);
            Db::exec("UPDATE famille_galerie SET cover_photo_id = ? WHERE id = ?", [$next, $album['id']]);
        }
    }

    respond(['success' => true, 'message' => 'Fichier supprimé']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Suivi médical
// ═══════════════════════════════════════════════════════════════════════════════

function admin_famille_get_medical()
{
    global $params;
    require_responsable();

    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('resident_id requis');

    $items = Db::fetchAll(
        "SELECT m.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                (SELECT COUNT(*) FROM famille_medical_fichiers WHERE medical_id = m.id) AS nb_fichiers
         FROM famille_medical m
         LEFT JOIN users u ON u.id = m.created_by
         WHERE m.resident_id = ?
         ORDER BY m.date_avis DESC",
        [$residentId]
    );

    foreach ($items as &$item) {
        $item['fichiers'] = Db::fetchAll(
            "SELECT id, file_name, file_type, encrypted_iv, size FROM famille_medical_fichiers WHERE medical_id = ? ORDER BY created_at ASC",
            [$item['id']]
        );
    }
    unset($item);

    respond(['success' => true, 'medical' => $items]);
}

function admin_famille_save_medical()
{
    global $params;
    $user = require_responsable();

    $id = $params['id'] ?? '';
    $residentId = $params['resident_id'] ?? '';
    $titre = trim($params['titre'] ?? '');
    $contenuChiffre = $params['contenu_chiffre'] ?? null;
    $contentIv = $params['content_iv'] ?? null;
    $dateAvis = $params['date_avis'] ?? date('Y-m-d');
    $type = in_array($params['type'] ?? '', ['avis', 'rapport', 'ordonnance', 'autre']) ? $params['type'] : 'avis';

    if (!$residentId || !$titre) bad_request('Champs requis manquants');

    if ($id) {
        Db::exec(
            "UPDATE famille_medical SET titre = ?, contenu_chiffre = ?, content_iv = ?, date_avis = ?, type = ? WHERE id = ? AND resident_id = ?",
            [$titre, $contenuChiffre, $contentIv, $dateAvis, $type, $id, $residentId]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO famille_medical (id, resident_id, titre, contenu_chiffre, content_iv, date_avis, type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $residentId, $titre, $contenuChiffre, $contentIv, $dateAvis, $type, $user['id']]
        );
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Avis médical enregistré']);
}

function admin_famille_delete_medical()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $fichiers = Db::fetchAll("SELECT file_path FROM famille_medical_fichiers WHERE medical_id = ?", [$id]);
    foreach ($fichiers as $f) {
        $path = _famille_resolve_path($f['file_path'] ?? null);
        if ($path && is_file($path)) @unlink($path);
    }

    Db::exec("DELETE FROM famille_medical WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Avis médical supprimé']);
}

function admin_famille_upload_medical_fichier()
{
    require_responsable();

    $medicalId = $_POST['medical_id'] ?? '';
    $encryptedIv = $_POST['encrypted_iv'] ?? '';

    if (!$medicalId || !$encryptedIv) bad_request('Paramètres manquants');

    $medical = Db::fetch("SELECT resident_id FROM famille_medical WHERE id = ?", [$medicalId]);
    if (!$medical) not_found('Avis médical introuvable');

    if (empty($_FILES['file'])) bad_request('Aucun fichier');

    $file = $_FILES['file'];
    $residentId = $medical['resident_id'];
    $dir = 'uploads/famille/' . $residentId . '/medical';
    $absDir = __DIR__ . '/../../' . $dir;
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);

    $fileName = $_POST['file_name'] ?? $file['name'];
    $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
    $storedName = Uuid::v4() . '.enc';
    $destPath = $dir . '/' . $storedName;

    move_uploaded_file($file['tmp_name'], $absDir . '/' . $storedName);

    $fichierId = Uuid::v4();
    Db::exec(
        "INSERT INTO famille_medical_fichiers (id, medical_id, file_path, file_name, file_type, encrypted_iv, size) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$fichierId, $medicalId, $destPath, $fileName, $fileType, $encryptedIv, $file['size']]
    );

    respond(['success' => true, 'id' => $fichierId, 'message' => 'Fichier ajouté']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Galerie
// ═══════════════════════════════════════════════════════════════════════════════

function admin_famille_get_galerie()
{
    global $params;
    require_responsable();

    $residentId = $params['resident_id'] ?? '';
    if (!$residentId) bad_request('resident_id requis');

    $albums = Db::fetchAll(
        "SELECT g.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                (SELECT COUNT(*) FROM famille_galerie_photos WHERE galerie_id = g.id) AS nb_photos
         FROM famille_galerie g
         LEFT JOIN users u ON u.id = g.created_by
         WHERE g.resident_id = ?
         ORDER BY g.date_galerie DESC",
        [$residentId]
    );

    foreach ($albums as &$album) {
        $album['photos'] = Db::fetchAll(
            "SELECT id, file_name, encrypted_iv, legende, ordre FROM famille_galerie_photos WHERE galerie_id = ? ORDER BY ordre ASC",
            [$album['id']]
        );
    }
    unset($album);

    respond(['success' => true, 'albums' => $albums]);
}

function admin_famille_save_album()
{
    global $params;
    $user = require_responsable();

    $id = $params['id'] ?? '';
    $residentId = $params['resident_id'] ?? '';
    $titre = trim($params['titre'] ?? '');
    $dateGalerie = $params['date_galerie'] ?? date('Y-m-d');
    $annee = (int) ($params['annee'] ?? date('Y'));
    $coverPhotoId = $params['cover_photo_id'] ?? null;

    if (!$residentId || !$titre) bad_request('Champs requis manquants');

    if ($id) {
        Db::exec(
            "UPDATE famille_galerie SET titre = ?, date_galerie = ?, annee = ?, cover_photo_id = ? WHERE id = ? AND resident_id = ?",
            [$titre, $dateGalerie, $annee, $coverPhotoId, $id, $residentId]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO famille_galerie (id, resident_id, titre, date_galerie, annee, cover_photo_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id, $residentId, $titre, $dateGalerie, $annee, $coverPhotoId, $user['id']]
        );
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Album enregistré']);
}

function admin_famille_delete_album()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $photos = Db::fetchAll("SELECT file_path FROM famille_galerie_photos WHERE galerie_id = ?", [$id]);
    foreach ($photos as $p) {
        $path = _famille_resolve_path($p['file_path'] ?? null);
        if ($path && is_file($path)) @unlink($path);
    }

    Db::exec("DELETE FROM famille_galerie WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Album supprimé']);
}

function admin_famille_serve_galerie_photo()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('id requis');

    $row = Db::fetch("SELECT file_path FROM famille_galerie_photos WHERE id = ?", [$id]);
    if (!$row || !$row['file_path']) not_found();

    $path = _famille_resolve_path($row['file_path'] ?? null);
    if (!$path || !is_file($path)) not_found();

    // Serve raw encrypted file
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

function admin_famille_upload_galerie_photo()
{
    require_responsable();

    $galerieId = $_POST['galerie_id'] ?? '';
    $encryptedIv = $_POST['encrypted_iv'] ?? '';

    if (!$galerieId || !$encryptedIv) bad_request('Paramètres manquants');

    $album = Db::fetch("SELECT resident_id FROM famille_galerie WHERE id = ?", [$galerieId]);
    if (!$album) not_found('Album introuvable');

    if (empty($_FILES['file'])) bad_request('Aucun fichier');

    $file = $_FILES['file'];
    $residentId = $album['resident_id'];
    $dir = 'uploads/famille/' . $residentId . '/galerie';
    $absDir = __DIR__ . '/../../' . $dir;
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);

    $fileName = $_POST['file_name'] ?? $file['name'];
    $legende = $_POST['legende'] ?? '';
    $storedName = Uuid::v4() . '.enc';
    $destPath = $dir . '/' . $storedName;

    move_uploaded_file($file['tmp_name'], $absDir . '/' . $storedName);

    $photoId = Uuid::v4();
    $ordre = (int) Db::getOne("SELECT COALESCE(MAX(ordre), 0) + 1 FROM famille_galerie_photos WHERE galerie_id = ?", [$galerieId]);
    Db::exec(
        "INSERT INTO famille_galerie_photos (id, galerie_id, file_path, file_name, encrypted_iv, legende, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$photoId, $galerieId, $destPath, $fileName, $encryptedIv, $legende, $ordre]
    );

    // Auto-set cover if first photo
    $album = Db::fetch("SELECT cover_photo_id FROM famille_galerie WHERE id = ?", [$galerieId]);
    if (!$album['cover_photo_id']) {
        Db::exec("UPDATE famille_galerie SET cover_photo_id = ? WHERE id = ?", [$photoId, $galerieId]);
    }

    respond(['success' => true, 'id' => $photoId, 'message' => 'Photo ajoutée']);
}

function admin_famille_set_cover()
{
    global $params;
    require_responsable();

    $albumId = $params['album_id'] ?? '';
    $photoId = $params['photo_id'] ?? '';

    if (!$albumId || !$photoId) bad_request('Paramètres manquants');

    Db::exec("UPDATE famille_galerie SET cover_photo_id = ? WHERE id = ?", [$photoId, $albumId]);
    respond(['success' => true, 'message' => 'Cover définie']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// List residents (for admin selector)
// ═══════════════════════════════════════════════════════════════════════════════

function admin_famille_get_residents()
{
    require_responsable();

    $residents = Db::fetchAll(
        "SELECT r.id, r.nom, r.prenom, r.chambre, r.etage, r.correspondant_email, r.is_active,
                (SELECT COUNT(*) FROM famille_encryption_keys WHERE resident_id = r.id) AS has_key
         FROM residents r WHERE r.is_active = 1
         ORDER BY r.nom, r.prenom"
    );

    respond(['success' => true, 'residents' => $residents]);
}
