<?php
/**
 * Auth API actions
 */

function login()
{
    global $params;
    $email = $params['email'] ?? '';
    $password = $params['password'] ?? '';

    if (!$email || !$password) {
        bad_request('Email et mot de passe requis');
    }

    // Rate limiting : max 10 tentatives par minute par IP
    check_rate_limit('login');

    $result = Auth::login($email, $password);
    if ($result['success']) {
        // Regenerate CSRF
        $_SESSION['zt_csrf_token'] = bin2hex(random_bytes(32));
        $result['csrf'] = $_SESSION['zt_csrf_token'];
    }
    respond($result);
}

function logout()
{
    Auth::logout();
    respond(['success' => true]);
}

function me()
{
    $user = Auth::me();
    if (!$user) {
        unauthorized();
    }

    // Get modules
    $user['modules'] = Db::fetchAll(
        "SELECT m.id, m.nom, m.code, um.is_principal
         FROM user_modules um
         JOIN modules m ON m.id = um.module_id
         WHERE um.user_id = ?
         ORDER BY um.is_principal DESC, m.ordre",
        [$user['id']]
    );

    respond(['success' => true, 'user' => $user]);
}

function request_reset()
{
    global $params;
    $email = $params['email'] ?? '';

    // Rate limiting : max 5 demandes de reset par minute par IP
    check_rate_limit('request_reset', 5);

    $result = Auth::requestReset($email);
    respond($result);
}

function reset_password()
{
    global $params;
    $token = $params['token'] ?? '';
    $password = $params['password'] ?? '';
    $result = Auth::resetPassword($token, $password);
    respond($result);
}

function update_profile()
{
    global $params;
    $user = require_auth();

    $telephone = Sanitize::phone($params['telephone'] ?? '');

    Db::exec(
        "UPDATE users SET telephone = ? WHERE id = ?",
        [$telephone, $user['id']]
    );

    respond(['success' => true, 'message' => 'Profil mis à jour']);
}

function update_password()
{
    global $params;
    $user = require_auth();

    $current = $params['current_password'] ?? '';
    $newPass = $params['new_password'] ?? '';

    $pwError = validate_password_strength($newPass);
    if ($pwError) {
        bad_request($pwError);
    }

    $dbUser = Db::fetch("SELECT password, password_temp_hash FROM users WHERE id = ?", [$user['id']]);

    // Accept current normal password OR temp password
    $currentOk = password_verify($current, $dbUser['password']);
    if (!$currentOk && !empty($dbUser['password_temp_hash'])) {
        $currentOk = password_verify($current, $dbUser['password_temp_hash']);
    }
    if (!$currentOk) {
        bad_request('Mot de passe actuel incorrect');
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    Db::exec(
        "UPDATE users SET password = ?, password_temp_hash = NULL, password_temp_expires = NULL WHERE id = ?",
        [$hash, $user['id']]
    );

    // Clear session flags
    unset($_SESSION['zt_must_change_password'], $_SESSION['zt_temp_password_expires']);

    respond(['success' => true, 'message' => 'Mot de passe mis à jour']);
}

function upload_avatar()
{
    $user = require_auth();

    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur d\'upload');
    }

    $file = $_FILES['avatar'];
    if ($file['size'] > 3 * 1024 * 1024) bad_request('Fichier trop volumineux (max 3 Mo)');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        bad_request('Format non supporté (JPG, PNG, GIF, WebP)');
    }

    $uploadDir = __DIR__ . '/../storage/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Convert to WebP, square crop centered
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/gif' => imagecreatefromgif($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default => null,
    };
    if (!$src) bad_request('Impossible de lire l\'image');

    $w = imagesx($src);
    $h = imagesy($src);
    $size = min($w, $h);
    $x = ($w - $size) / 2;
    $y = ($h - $size) / 2;

    $dest = imagecreatetruecolor(256, 256);
    imagecopyresampled($dest, $src, 0, 0, (int)$x, (int)$y, 256, 256, $size, $size);
    imagedestroy($src);

    $filename = $user['id'] . '_' . time() . '.webp';
    $destPath = $uploadDir . $filename;
    imagewebp($dest, $destPath, 85);
    imagedestroy($dest);

    // Delete old avatar if exists
    $oldPhoto = Db::getOne("SELECT photo FROM users WHERE id = ?", [$user['id']]);
    if ($oldPhoto) {
        $oldPath = __DIR__ . '/../' . ltrim($oldPhoto, '/zerdatime/');
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $photoUrl = '/zerdatime/storage/avatars/' . $filename;
    Db::exec("UPDATE users SET photo = ? WHERE id = ?", [$photoUrl, $user['id']]);

    // Update session
    $_SESSION['zt_user']['photo'] = $photoUrl;

    respond(['success' => true, 'photo_url' => $photoUrl, 'message' => 'Photo mise à jour']);
}
