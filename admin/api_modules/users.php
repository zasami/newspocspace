<?php

function admin_get_users()
{
    require_responsable();
    global $params;
    $search = $params['search'] ?? '';
    $module = $params['module_id'] ?? '';

    $sql = "SELECT u.*, f.nom AS fonction_nom, f.code AS fonction_code
            FROM users u
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            WHERE 1=1";
    $p = [];

    if ($search) {
        $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
        $like = "%$search%";
        $p = array_merge($p, [$like, $like, $like]);
    }

    if ($module) {
        $sql .= " AND u.id IN (SELECT user_id FROM user_modules WHERE module_id = ?)";
        $p[] = $module;
    }

    $sql .= " ORDER BY u.nom, u.prenom";

    $users = Db::fetchAll($sql, $p);

    // Get modules for each user
    foreach ($users as &$u) {
        unset($u['password'], $u['reset_token'], $u['reset_expires']);
        $u['modules'] = Db::fetchAll(
            "SELECT m.id, m.nom, m.code, um.is_principal
             FROM user_modules um JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = ?",
            [$u['id']]
        );
    }

    respond(['success' => true, 'users' => $users]);
}

function admin_search_users()
{
    require_responsable();
    global $params;
    $q = trim($params['q'] ?? '');
    if (mb_strlen($q) < 2) {
        respond(['success' => true, 'users' => []]);
        return;
    }

    $like = "%$q%";
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.employee_id, f.code AS fonction_code,
                (SELECT GROUP_CONCAT(m.code SEPARATOR ', ')
                 FROM user_modules um JOIN modules m ON m.id = um.module_id
                 WHERE um.user_id = u.id) AS modules_codes
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE (u.nom LIKE ? OR u.prenom LIKE ? OR u.employee_id LIKE ?
                OR CONCAT(u.prenom, ' ', u.nom) LIKE ?)
         ORDER BY u.nom, u.prenom
         LIMIT 8",
        [$like, $like, $like, $like]
    );

    respond(['success' => true, 'users' => $users]);
}

function admin_get_user()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $user = Db::fetch(
        "SELECT u.*, f.nom AS fonction_nom
         FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.id = ?",
        [$id]
    );
    if (!$user) not_found('Utilisateur non trouvé');

    unset($user['password'], $user['reset_token'], $user['reset_expires']);

    $user['modules'] = Db::fetchAll(
        "SELECT m.id, m.nom, m.code, um.is_principal
         FROM user_modules um JOIN modules m ON m.id = um.module_id
         WHERE um.user_id = ?",
        [$id]
    );

    respond(['success' => true, 'user' => $user]);
}

function admin_create_user()
{
    global $params;
    require_admin();

    $email = Sanitize::email($params['email'] ?? '');
    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $prenom = Sanitize::text($params['prenom'] ?? '', 100);
    $taux = Sanitize::float($params['taux'] ?? 100);
    $fonctionId = $params['fonction_id'] ?? null;
    $role = $params['role'] ?? 'collaborateur';
    $typeContrat = $params['type_contrat'] ?? 'CDI';
    $telephone = Sanitize::phone($params['telephone'] ?? '');

    if (!$email || !$nom || !$prenom) {
        bad_request('Email, nom et prénom requis');
    }

    // Check email unique
    $existing = Db::getOne("SELECT COUNT(*) FROM users WHERE email = ?", [$email]);
    if ($existing > 0) {
        bad_request('Cet email est déjà utilisé');
    }

    // Auto-generate employee_id: EMS-XXXX (next available number)
    $lastNum = Db::getOne("SELECT MAX(CAST(SUBSTRING(employee_id, 5) AS UNSIGNED)) FROM users WHERE employee_id LIKE 'EMS-%'");
    $nextNum = ($lastNum ?: 0) + 1;
    $employeeId = 'EMS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Temporary password (24h expiry) — no permanent password set
    $tempPassword = 'Zt' . random_int(100000, 999999) . '!';
    $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO users (id, employee_id, email, password, password_temp_hash, password_temp_expires, nom, prenom, telephone, fonction_id, taux, type_contrat, role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $employeeId, $email, $placeholderHash, $tempHash, $expires, $nom, $prenom, $telephone, $fonctionId ?: null, $taux, $typeContrat, $role]
    );

    // Assign modules
    $moduleIds = $params['module_ids'] ?? [];
    $principalModule = $params['principal_module_id'] ?? '';
    foreach ($moduleIds as $mId) {
        Db::exec(
            "INSERT INTO user_modules (user_id, module_id, is_principal) VALUES (?, ?, ?)",
            [$id, $mId, $mId === $principalModule ? 1 : 0]
        );
    }

    // Send welcome email
    $loginUrl = APP_URL . '/login';
    $subject = 'Bienvenue sur zerdaTime — Vos identifiants';
    $body = "Bonjour $prenom,\n\n"
          . "Votre compte zerdaTime a été créé.\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "N° Employé : $employeeId\n"
          . "Email : $email\n"
          . "Mot de passe temporaire : $tempPassword\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
          . "⚠ Ce mot de passe expire dans 24 heures.\n"
          . "Connectez-vous et changez-le immédiatement depuis votre profil.\n\n"
          . "Lien de connexion : $loginUrl\n\n"
          . "Cordialement,\n"
          . "L'équipe zerdaTime";
    $headers = "From: noreply@terrassiere.ch\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($email, $subject, $body, $headers);

    respond([
        'success' => true,
        'message' => "Collaborateur créé ($employeeId). Email de bienvenue envoyé.",
        'id' => $id,
        'employee_id' => $employeeId,
    ]);
}

function admin_update_user()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fields = [];
    $values = [];

    $allowed = ['nom', 'prenom', 'email', 'telephone', 'taux', 'fonction_id', 'role', 'type_contrat',
                'employee_id', 'date_entree', 'date_fin_contrat', 'solde_vacances'];

    foreach ($allowed as $field) {
        if (isset($params[$field])) {
            $fields[] = "$field = ?";
            $values[] = $params[$field] ?: null;
        }
    }

    if ($fields) {
        $values[] = $id;
        Db::exec("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?", $values);
    }

    // Update modules
    if (isset($params['module_ids'])) {
        Db::exec("DELETE FROM user_modules WHERE user_id = ?", [$id]);
        $principalModule = $params['principal_module_id'] ?? '';
        foreach ($params['module_ids'] as $mId) {
            Db::exec(
                "INSERT INTO user_modules (user_id, module_id, is_principal) VALUES (?, ?, ?)",
                [$id, $mId, $mId === $principalModule ? 1 : 0]
            );
        }
    }

    respond(['success' => true, 'message' => 'Collaborateur mis à jour']);
}

function admin_upload_user_avatar()
{
    global $params;
    require_admin();

    $userId = $params['user_id'] ?? ($_POST['user_id'] ?? '');
    if (!$userId) bad_request('ID utilisateur requis');

    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur d\'upload');
    }

    $file = $_FILES['avatar'];
    if ($file['size'] > 3 * 1024 * 1024) bad_request('Fichier trop volumineux (max 3 Mo)');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        bad_request('Format non supporté');
    }

    $uploadDir = __DIR__ . '/../../storage/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

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

    $filename = $userId . '_' . time() . '.webp';
    imagewebp($dest, $uploadDir . $filename, 85);
    imagedestroy($dest);

    $photoUrl = '/zerdatime/storage/avatars/' . $filename;
    Db::exec("UPDATE users SET photo = ? WHERE id = ?", [$photoUrl, $userId]);

    respond(['success' => true, 'photo_url' => $photoUrl, 'message' => 'Photo mise à jour']);
}

function admin_delete_user_avatar()
{
    global $params;
    require_admin();

    $userId = $params['user_id'] ?? '';
    if (!$userId) bad_request('ID requis');

    $user = Db::fetch("SELECT photo FROM users WHERE id = ?", [$userId]);
    if (!$user) not_found('Utilisateur non trouvé');

    // Delete file if exists
    if ($user['photo']) {
        $path = __DIR__ . '/../../' . ltrim($user['photo'], '/zerdatime/');
        if (file_exists($path)) unlink($path);
    }

    Db::exec("UPDATE users SET photo = NULL WHERE id = ?", [$userId]);
    respond(['success' => true, 'message' => 'Photo supprimée']);
}

function admin_flag_photo()
{
    global $params;
    require_admin();

    $userId = $params['user_id'] ?? '';
    if (!$userId) bad_request('ID requis');

    // Remove current photo
    Db::exec("UPDATE users SET photo = NULL WHERE id = ?", [$userId]);

    respond(['success' => true, 'message' => 'Photo signalée et supprimée']);
}

function admin_toggle_user()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE users SET is_active = NOT is_active WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Statut modifié']);
}

function admin_reset_user_password()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $user = Db::fetch("SELECT id, email, prenom, nom FROM users WHERE id = ?", [$id]);
    if (!$user) not_found('Utilisateur introuvable');

    // Generate temporary password (valid 24h)
    $tempPassword = 'Zt' . random_int(100000, 999999) . '!';
    $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    Db::exec(
        "UPDATE users SET password_temp_hash = ?, password_temp_expires = ? WHERE id = ?",
        [$tempHash, $expires, $id]
    );

    // Send email
    $prenom = $user['prenom'];
    $subject = 'zerdaTime — Mot de passe temporaire';
    $body = "Bonjour $prenom,\n\n"
          . "Un administrateur a réinitialisé votre mot de passe.\n\n"
          . "Votre mot de passe temporaire : $tempPassword\n\n"
          . "⚠ Ce mot de passe expire dans 24 heures.\n"
          . "Connectez-vous et changez-le immédiatement depuis votre profil.\n\n"
          . "Lien de connexion : " . APP_URL . "/login\n\n"
          . "Cordialement,\nzerdaTime";
    $headers = "From: noreply@terrassiere.ch\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($user['email'], $subject, $body, $headers);

    respond([
        'success' => true,
        'message' => 'Mot de passe temporaire envoyé par email à ' . h($user['email']),
        'new_password' => $tempPassword,
    ]);
}

// DEV ONLY — suppression définitive d'un utilisateur (à retirer après tests)
function admin_delete_user_permanent()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $user = Db::fetch("SELECT id, email FROM users WHERE id = ?", [$id]);
    if (!$user) not_found('Utilisateur introuvable');

    // Supprimer toutes les données liées (ignore tables manquantes)
    $tables = [
        "DELETE FROM absences WHERE user_id = ?",
        "DELETE FROM desirs WHERE user_id = ?",
        "DELETE FROM messages WHERE sender_id = ? OR recipient_id = ?",
        "DELETE FROM planning_assignations WHERE user_id = ?",
        "DELETE FROM user_modules WHERE user_id = ?",
        "DELETE FROM notifications WHERE user_id = ?",
        "DELETE FROM rate_limits WHERE user_id = ?",
    ];
    foreach ($tables as $sql) {
        try {
            $paramCount = substr_count($sql, '?');
            Db::exec($sql, $paramCount === 2 ? [$id, $id] : [$id]);
        } catch (\Throwable $e) { /* table may not exist */ }
    }
    Db::exec("DELETE FROM users WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Utilisateur supprimé définitivement']);
}
