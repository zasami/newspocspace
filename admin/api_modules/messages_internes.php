<?php
/**
 * Messagerie interne — Admin API actions
 */

function admin_get_all_messages()
{
    require_responsable();
    global $params;
    $adminId = $_SESSION['ss_user']['id'];
    $page = max(1, (int)($params['page'] ?? 1));
    $search = Sanitize::text($params['search'] ?? '', 100);
    $tab = $params['tab'] ?? 'all'; // all | inbox | sent
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $where = "e.is_draft = 0";
    $binds = [];

    if ($tab === 'inbox') {
        $where .= " AND EXISTS (SELECT 1 FROM message_recipients er WHERE er.email_id = e.id AND er.user_id = ? AND er.deleted = 0)";
        $binds[] = $adminId;
    } elseif ($tab === 'sent') {
        $where .= " AND e.from_user_id = ? AND e.sender_deleted = 0";
        $binds[] = $adminId;
    }

    if ($search) {
        $where .= " AND (e.sujet LIKE ? OR e.contenu LIKE ? OR uf.prenom LIKE ? OR uf.nom LIKE ?)";
        $like = '%' . $search . '%';
        $binds = array_merge($binds, [$like, $like, $like, $like]);
    }

    $total = (int)Db::getOne(
        "SELECT COUNT(*) FROM messages e JOIN users uf ON uf.id = e.from_user_id WHERE $where",
        $binds
    );

    $emails = Db::fetchAll(
        "SELECT e.id, e.sujet, e.contenu, e.from_user_id, e.thread_id, e.created_at,
                uf.prenom AS from_prenom, uf.nom AS from_nom,
                (SELECT GROUP_CONCAT(CONCAT(u2.prenom, ' ', u2.nom) SEPARATOR ', ')
                 FROM message_recipients er2 JOIN users u2 ON u2.id = er2.user_id
                 WHERE er2.email_id = e.id AND er2.type = 'to') AS to_names,
                (SELECT COUNT(*) FROM message_attachments ea WHERE ea.email_id = e.id) AS nb_attachments,
                (SELECT COUNT(*) FROM message_recipients er3 WHERE er3.email_id = e.id AND er3.lu = 0) AS nb_unread,
                COALESCE((SELECT er4.lu FROM message_recipients er4 WHERE er4.email_id = e.id AND er4.user_id = ? LIMIT 1), 1) AS my_read
         FROM messages e
         JOIN users uf ON uf.id = e.from_user_id
         WHERE $where
         ORDER BY e.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge([$adminId], $binds, [$limit, $offset])
    );

    respond(['success' => true, 'messages' => $emails, 'total' => $total, 'page' => $page]);
}

function admin_get_message_detail()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $email = Db::fetch(
        "SELECT e.*, uf.prenom AS from_prenom, uf.nom AS from_nom, uf.email AS from_email
         FROM messages e
         JOIN users uf ON uf.id = e.from_user_id
         WHERE e.id = ?",
        [$id]
    );
    if (!$email) not_found('Email non trouvé');

    // Mark as read for the current admin user
    $adminId = $_SESSION['ss_user']['id'];
    Db::exec(
        "UPDATE message_recipients SET lu = 1, lu_at = NOW() WHERE email_id = ? AND user_id = ? AND lu = 0",
        [$id, $adminId]
    );

    $recipients = Db::fetchAll(
        "SELECT er.type, er.user_id, er.lu, er.lu_at, u.prenom, u.nom, u.email
         FROM message_recipients er
         JOIN users u ON u.id = er.user_id
         WHERE er.email_id = ?",
        [$id]
    );

    $attachments = Db::fetchAll(
        "SELECT id, filename, original_name, mime_type, size FROM message_attachments WHERE email_id = ?",
        [$id]
    );

    $thread = [];
    if ($email['thread_id']) {
        $thread = Db::fetchAll(
            "SELECT e.id, e.sujet, e.contenu, e.from_user_id, e.created_at, e.parent_id,
                    uf.prenom AS from_prenom, uf.nom AS from_nom
             FROM messages e
             JOIN users uf ON uf.id = e.from_user_id
             WHERE e.thread_id = ? AND e.is_draft = 0
             ORDER BY e.created_at ASC",
            [$email['thread_id']]
        );
    }

    respond([
        'success' => true,
        'email' => $email,
        'recipients' => $recipients,
        'attachments' => $attachments,
        'thread' => $thread,
    ]);
}

function admin_get_message_contacts()
{
    require_responsable();

    $contacts = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, u.fonction_nom,
                COALESCE(m.nom, 'Sans module') AS module_nom,
                COALESCE(m.ordre, 999) AS module_ordre
         FROM users u
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE u.is_active = 1
         ORDER BY module_ordre, m.nom, u.nom, u.prenom"
    );

    respond(['success' => true, 'contacts' => $contacts]);
}

function admin_send_message()
{
    global $params;
    $admin = require_responsable();

    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = $params['contenu'] ?? '';
    $contenu = mb_substr(strip_tags($contenu, '<p><br><strong><em><ul><ol><li><a><blockquote>'), 0, 10000);
    $toIds = $params['to'] ?? [];
    $ccIds = $params['cc'] ?? [];
    $parentId = $params['parent_id'] ?? null;

    if (!$sujet) bad_request('Sujet requis');
    if (!$contenu) bad_request('Contenu requis');
    if (empty($toIds) || !is_array($toIds)) bad_request('Au moins un destinataire requis');

    $allIds = array_unique(array_merge($toIds, $ccIds));
    $placeholders = implode(',', array_fill(0, count($allIds), '?'));
    $validUsers = Db::fetchAll(
        "SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1",
        $allIds
    );
    $validIds = array_column($validUsers, 'id');

    $toIds = array_values(array_intersect($toIds, $validIds));
    $ccIds = array_values(array_intersect($ccIds, $validIds));
    if (empty($toIds)) bad_request('Destinataire(s) invalide(s)');

    $threadId = null;
    if ($parentId) {
        $parent = Db::fetch("SELECT thread_id, id FROM messages WHERE id = ?", [$parentId]);
        if ($parent) {
            $threadId = $parent['thread_id'] ?: $parent['id'];
        }
    }

    $emailId = Uuid::v4();
    if (!$threadId && !$parentId) {
        $threadId = $emailId;
    }

    Db::exec(
        "INSERT INTO messages (id, parent_id, thread_id, from_user_id, sujet, contenu)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$emailId, $parentId, $threadId, $admin['id'], $sujet, $contenu]
    );

    foreach ($toIds as $uid) {
        Db::exec(
            "INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')",
            [Uuid::v4(), $emailId, $uid]
        );
    }
    foreach ($ccIds as $uid) {
        Db::exec(
            "INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'cc')",
            [Uuid::v4(), $emailId, $uid]
        );
    }

    respond(['success' => true, 'message' => 'Email envoyé', 'id' => $emailId]);
}

function admin_delete_message()
{
    global $params;
    require_admin();
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $email = Db::fetch("SELECT id FROM messages WHERE id = ?", [$id]);
    if (!$email) not_found('Email non trouvé');

    // Hard delete for admin
    Db::exec("DELETE FROM message_attachments WHERE email_id = ?", [$id]);
    Db::exec("DELETE FROM message_recipients WHERE email_id = ?", [$id]);
    Db::exec("DELETE FROM messages WHERE id = ?", [$id]);

    respond(['success' => true]);
}

function admin_get_message_stats()
{
    require_responsable();

    $total = (int)Db::getOne("SELECT COUNT(*) FROM messages WHERE is_draft = 0");
    $today = (int)Db::getOne("SELECT COUNT(*) FROM messages WHERE is_draft = 0 AND DATE(created_at) = CURDATE()");
    $week = (int)Db::getOne("SELECT COUNT(*) FROM messages WHERE is_draft = 0 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $userId = $_SESSION['ss_user']['id'] ?? '';
    $unread = (int)Db::getOne("SELECT COUNT(DISTINCT email_id) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$userId]);
    $attachments = (int)Db::getOne("SELECT COUNT(*) FROM message_attachments");

    respond(['success' => true, 'stats' => compact('total', 'today', 'week', 'unread', 'attachments')]);
}

function admin_download_message_attachment()
{
    require_responsable();
    global $params;

    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('ID requis');

    $att = Db::fetch(
        "SELECT * FROM message_attachments WHERE id = ?",
        [$attId]
    );
    if (!$att) not_found('Pièce jointe non trouvée');

    $path = __DIR__ . '/../../storage/emails/' . basename($att['filename']);
    if (!file_exists($path)) not_found('Fichier non trouvé');

    header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
    if (!str_starts_with($att['mime_type'] ?? '', 'image/')) {
        header('Content-Disposition: attachment; filename="' . basename($att['original_name']) . '"');
    } else {
        header('Content-Disposition: inline; filename="' . basename($att['original_name']) . '"');
    }
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

function admin_upload_message_attachment()
{
    $admin = require_responsable();

    $emailId = $_POST['email_id'] ?? '';
    if (!$emailId) bad_request('email_id requis');

    $email = Db::fetch("SELECT id, from_user_id FROM messages WHERE id = ?", [$emailId]);
    if (!$email || $email['from_user_id'] !== $admin['id']) {
        forbidden('Accès non autorisé');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant ou invalide');
    }

    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) bad_request('Fichier trop volumineux (max 5 Mo)');

    $allowed = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'text/plain', 'text/csv',
    ];
    if (!in_array($file['type'], $allowed, true)) bad_request('Type de fichier non autorisé');

    $storageDir = __DIR__ . '/../../storage/emails/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $filename = $emailId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde');
    }

    $attId = Uuid::v4();
    $originalName = mb_substr(basename($file['name']), 0, 255);

    Db::exec(
        "INSERT INTO message_attachments (id, email_id, filename, original_name, mime_type, size)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$attId, $emailId, $filename, $originalName, $file['type'], $file['size']]
    );

    respond([
        'success' => true,
        'attachment' => [
            'id' => $attId,
            'original_name' => $originalName,
            'mime_type' => $file['type'],
            'size' => $file['size'],
        ],
    ]);
}

function admin_get_unread_counts()
{
    $user = require_auth();
    $userId = $user['id'];

    // Internal messages (message_recipients table)
    $unreadMessages = (int) Db::getOne(
        "SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0",
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
        } catch (\Throwable $e) {}
    }

    respond([
        'success' => true,
        'unread_messages' => $unreadMessages,
        'unread_email' => $unreadEmail,
    ]);
}
