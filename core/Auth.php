<?php
/**
 * Authentication
 */
class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['ss_user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['ss_user'] ?? null;
    }

    public static function userId(): ?string
    {
        return $_SESSION['ss_user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['ss_user']['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['admin', 'direction']);
    }

    public static function isResponsable(): bool
    {
        return in_array(self::role(), ['admin', 'direction', 'responsable']);
    }

    public static function login(string $email, string $password): array
    {
        $email = Sanitize::email($email);
        if (!$email) {
            return ['success' => false, 'message' => 'Email invalide'];
        }

        $user = Db::fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if (!$user) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }

        // Check normal password first, then temp password
        $mustChangePassword = false;
        $normalMatch = password_verify($password, $user['password']);
        $tempMatch = false;

        if (!empty($user['password_temp_hash'])) {
            $tempMatch = password_verify($password, $user['password_temp_hash']);
            // Check if temp password has expired
            if ($tempMatch && !empty($user['password_temp_expires']) && strtotime($user['password_temp_expires']) < time()) {
                // Clear expired temp password
                Db::exec("UPDATE users SET password_temp_hash = NULL, password_temp_expires = NULL WHERE id = ?", [$user['id']]);
                return ['success' => false, 'message' => 'Le mot de passe temporaire a expiré. Contactez un administrateur.'];
            }
        }

        if (!$normalMatch && !$tempMatch) {
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
        }

        if ($tempMatch && !$normalMatch) {
            $mustChangePassword = true;
        }

        // Régénérer l'ID de session pour éviter la fixation de session
        session_regenerate_id(true);

        // Update last login
        Db::exec("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Set session
        $_SESSION['ss_user'] = [
            'id'         => $user['id'],
            'email'      => $user['email'],
            'nom'        => $user['nom'],
            'prenom'     => $user['prenom'],
            'role'       => $user['role'],
            'fonction_id'=> $user['fonction_id'],
            'taux'       => $user['taux'],
            'photo'      => $user['photo'],
            'type_employe' => $user['type_employe'] ?? 'interne',
            'denied_perms' => Permission::getDenied($user['id']),
        ];
        $_SESSION['ss_last_activity'] = time();

        if ($mustChangePassword) {
            $_SESSION['ss_must_change_password'] = true;
            $_SESSION['ss_temp_password_expires'] = $user['password_temp_expires'];
        }

        $result = ['success' => true, 'user' => $_SESSION['ss_user']];
        if ($mustChangePassword) {
            $result['must_change_password'] = true;
            $result['temp_password_expires'] = $user['password_temp_expires'];
        }
        return $result;
    }

    public static function logout(): void
    {
        unset($_SESSION['ss_user'], $_SESSION['ss_last_activity']);
    }

    public static function me(): ?array
    {
        if (!self::check()) return null;
        $user = Db::fetch(
            "SELECT u.*, f.nom AS fonction_nom
             FROM users u
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             WHERE u.id = ? AND u.is_active = 1",
            [self::userId()]
        );
        if (!$user) return null;
        unset($user['password']);
        return $user;
    }

    public static function requestReset(string $email): array
    {
        $email = Sanitize::email($email);
        $user = Db::fetch("SELECT id FROM users WHERE email = ? AND is_active = 1", [$email]);
        if (!$user) {
            return ['success' => true, 'message' => 'Si ce compte existe, un email a été envoyé'];
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        Db::exec(
            "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
            [$token, $expires, $user['id']]
        );

        // Envoi de l'email de réinitialisation
        $resetUrl = APP_URL . '/login?reset=' . $token;
        $subject = 'Réinitialisation de votre mot de passe — SpocSpace';
        $body = "Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\n"
               . "Cliquez sur le lien suivant (valable 1 heure) :\n" . $resetUrl . "\n\n"
               . "Si vous n'avez pas fait cette demande, ignorez cet email.\n\n"
               . "Cordialement,\nSpocSpace";
        $headers = "From: noreply@terrassiere.ch\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($email, $subject, $body, $headers);

        return ['success' => true, 'message' => 'Si ce compte existe, un email a été envoyé'];
    }

    public static function resetPassword(string $token, string $newPassword): array
    {
        $pwError = validate_password_strength($newPassword);
        if ($pwError) {
            return ['success' => false, 'message' => $pwError];
        }

        $user = Db::fetch(
            "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()",
            [$token]
        );
        if (!$user) {
            return ['success' => false, 'message' => 'Lien expiré ou invalide'];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Db::exec(
            "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$hash, $user['id']]
        );

        return ['success' => true, 'message' => 'Mot de passe mis à jour'];
    }
}
