<?php

function admin_get_messages()
{
    require_responsable();
    // Messages to direction (to_user_id IS NULL) + messages to current admin
    $userId = $_SESSION['zt_user']['id'];
    $messages = Db::fetchAll(
        "SELECT m.*, uf.prenom AS from_prenom, uf.nom AS from_nom
         FROM messages m
         JOIN users uf ON uf.id = m.from_user_id
         WHERE m.to_user_id IS NULL OR m.to_user_id = ?
         ORDER BY m.created_at DESC
         LIMIT 100",
        [$userId]
    );

    respond(['success' => true, 'messages' => $messages]);
}

function admin_get_unread_counts()
{
    $user = require_auth();
    $userId = $user['id'];

    // Internal messages (email_recipients table — current messaging system)
    $unreadMessages = (int) Db::getOne(
        "SELECT COUNT(*) FROM email_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0",
        [$userId]
    );

    // External email — IMAP unread (only if configured)
    $unreadEmail = 0;
    $hasEmailConfig = Db::fetch(
        "SELECT imap_host, imap_port, imap_encryption, username, encrypted_password, password_iv
         FROM email_externe_config WHERE user_id = ? AND is_active = 1",
        [$userId]
    );
    if ($hasEmailConfig) {
        try {
            require_once __DIR__ . '/../../core/Mailer.php';
            $password = Mailer::decryptPassword($hasEmailConfig['encrypted_password'], $hasEmailConfig['password_iv']);
            $flags = '';
            if ($hasEmailConfig['imap_encryption'] === 'ssl') $flags = '/imap/ssl/validate-cert';
            elseif ($hasEmailConfig['imap_encryption'] === 'tls') $flags = '/imap/tls';
            else $flags = '/imap/notls';
            $mailbox = '{' . $hasEmailConfig['imap_host'] . ':' . $hasEmailConfig['imap_port'] . $flags . '}INBOX';
            $imap = @imap_open($mailbox, $hasEmailConfig['username'], $password, 0, 1);
            if ($imap) {
                $status = imap_status($imap, $mailbox, SA_UNSEEN);
                if ($status) $unreadEmail = $status->unseen ?? 0;
                @imap_close($imap);
            }
        } catch (\Throwable $e) {
            // Silently fail — don't block the badge
        }
    }

    respond([
        'success' => true,
        'unread_messages' => $unreadMessages,
        'unread_email' => $unreadEmail,
    ]);
}

function admin_reply_message()
{
    global $params;
    $admin = require_responsable();

    $toUserId = $params['to_user_id'] ?? '';
    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = Sanitize::text($params['contenu'] ?? '', 2000);

    if (!$toUserId || !$sujet || !$contenu) bad_request('Champs requis');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO messages (id, from_user_id, to_user_id, sujet, contenu) VALUES (?, ?, ?, ?, ?)",
        [$id, $admin['id'], $toUserId, $sujet, $contenu]
    );

    respond(['success' => true, 'message' => 'Réponse envoyée']);
}
