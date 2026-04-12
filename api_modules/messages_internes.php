<?php
/**
 * Email interne — API actions (employee)
 */

/**
 * Boîte de réception : emails reçus (non supprimés)
 */
function get_inbox()
{
    $user = require_auth();
    $page = max(1, (int)($GLOBALS['params']['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $total = (int)Db::getOne(
        "SELECT COUNT(DISTINCT e.id) FROM messages e
         JOIN message_recipients er ON er.email_id = e.id
         WHERE er.user_id = ? AND er.deleted = 0 AND e.is_draft = 0",
        [$user['id']]
    );

    $emails = Db::fetchAll(
        "SELECT e.id, e.sujet, e.contenu, e.thread_id, e.parent_id,
                e.from_user_id, e.created_at,
                er.lu, er.lu_at, er.archived,
                uf.prenom AS from_prenom, uf.nom AS from_nom,
                (SELECT COUNT(*) FROM message_attachments ea WHERE ea.email_id = e.id) AS nb_attachments
         FROM messages e
         JOIN message_recipients er ON er.email_id = e.id
         JOIN users uf ON uf.id = e.from_user_id
         WHERE er.user_id = ? AND er.deleted = 0 AND e.is_draft = 0
         ORDER BY e.created_at DESC
         LIMIT ? OFFSET ?",
        [$user['id'], $limit, $offset]
    );

    respond(['success' => true, 'emails' => $emails, 'total' => $total, 'page' => $page]);
}

/**
 * Emails envoyés
 */
function get_sent()
{
    $user = require_auth();
    $page = max(1, (int)($GLOBALS['params']['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $total = (int)Db::getOne(
        "SELECT COUNT(*) FROM messages e
         WHERE e.from_user_id = ? AND e.sender_deleted = 0 AND e.is_draft = 0",
        [$user['id']]
    );

    $emails = Db::fetchAll(
        "SELECT e.id, e.sujet, e.contenu, e.thread_id, e.created_at,
                (SELECT COUNT(*) FROM message_attachments ea WHERE ea.email_id = e.id) AS nb_attachments,
                (SELECT GROUP_CONCAT(CONCAT(u2.prenom, ' ', u2.nom) SEPARATOR ', ')
                 FROM message_recipients er2 JOIN users u2 ON u2.id = er2.user_id
                 WHERE er2.email_id = e.id AND er2.type = 'to') AS to_names
         FROM messages e
         WHERE e.from_user_id = ? AND e.sender_deleted = 0 AND e.is_draft = 0
         ORDER BY e.created_at DESC
         LIMIT ? OFFSET ?",
        [$user['id'], $limit, $offset]
    );

    respond(['success' => true, 'emails' => $emails, 'total' => $total, 'page' => $page]);
}

/**
 * Détail d'un email complet (avec fil de discussion)
 */
function get_message_detail()
{
    global $params;
    $user = require_auth();
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    // Vérifier accès
    $email = Db::fetch(
        "SELECT e.* , uf.prenom AS from_prenom, uf.nom AS from_nom
         FROM messages e
         JOIN users uf ON uf.id = e.from_user_id
         WHERE e.id = ?",
        [$id]
    );
    if (!$email) not_found('Email non trouvé');

    // L'utilisateur doit être expéditeur ou destinataire
    $isRecipient = Db::getOne(
        "SELECT COUNT(*) FROM message_recipients WHERE email_id = ? AND user_id = ?",
        [$id, $user['id']]
    );
    if ($email['from_user_id'] !== $user['id'] && !$isRecipient) {
        forbidden('Accès non autorisé');
    }

    // Marquer comme lu si destinataire
    if ($isRecipient) {
        Db::exec(
            "UPDATE message_recipients SET lu = 1, lu_at = NOW() WHERE email_id = ? AND user_id = ? AND lu = 0",
            [$id, $user['id']]
        );
    }

    // Destinataires
    $recipients = Db::fetchAll(
        "SELECT er.type, er.user_id, u.prenom, u.nom
         FROM message_recipients er
         JOIN users u ON u.id = er.user_id
         WHERE er.email_id = ?",
        [$id]
    );

    // Pièces jointes
    $attachments = Db::fetchAll(
        "SELECT id, filename, original_name, mime_type, size FROM message_attachments WHERE email_id = ?",
        [$id]
    );

    // Fil de discussion (thread)
    $thread = [];
    if ($email['thread_id']) {
        $thread = Db::fetchAll(
            "SELECT e.id, e.sujet, e.contenu, e.from_user_id, e.created_at, e.parent_id,
                    uf.prenom AS from_prenom, uf.nom AS from_nom,
                    (SELECT COUNT(*) FROM message_attachments ea WHERE ea.email_id = e.id) AS nb_attachments
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

/**
 * Envoyer un email (nouveau ou réponse)
 */
function send_message()
{
    global $params;
    $user = require_auth();

    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = $params['contenu'] ?? '';
    $contenu = mb_substr(strip_tags($contenu, '<p><br><strong><em><ul><ol><li><a><blockquote>'), 0, 10000);
    $toIds = $params['to'] ?? [];
    $ccIds = $params['cc'] ?? [];
    $parentId = $params['parent_id'] ?? null;
    $draftId = $params['draft_id'] ?? null;

    // Debug: log received recipients
    error_log('[MSG_DEBUG] send_message from=' . $user['id'] . ' to=' . json_encode($toIds) . ' cc=' . json_encode($ccIds) . ' sujet=' . $sujet);

    if (!$sujet) bad_request('Sujet requis');
    if (!$contenu) bad_request('Contenu requis');
    if (empty($toIds) || !is_array($toIds)) bad_request('Au moins un destinataire requis');

    // Valider les destinataires
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

    // Si c'est un brouillon existant, le publier
    if ($draftId) {
        $draft = Db::fetch("SELECT id FROM messages WHERE id = ? AND from_user_id = ? AND is_draft = 1", [$draftId, $user['id']]);
        if ($draft) {
            Db::exec("UPDATE messages SET sujet = ?, contenu = ?, is_draft = 0, created_at = NOW() WHERE id = ?", [$sujet, $contenu, $draftId]);
            Db::exec("DELETE FROM message_recipients WHERE email_id = ?", [$draftId]);
            foreach ($toIds as $uid) {
                Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')", [Uuid::v4(), $draftId, $uid]);
            }
            foreach ($ccIds as $uid) {
                Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'cc')", [Uuid::v4(), $draftId, $uid]);
            }
            respond(['success' => true, 'message' => 'Email envoyé', 'id' => $draftId]);
            return;
        }
    }

    // Déterminer le thread_id
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
        [$emailId, $parentId, $threadId, $user['id'], $sujet, $contenu]
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

/**
 * Upload pièce jointe
 */
function upload_message_attachment()
{
    $user = require_auth();

    $emailId = $_POST['email_id'] ?? '';
    if (!$emailId) bad_request('email_id requis');

    // Vérifier que l'email existe et appartient à l'expéditeur
    $email = Db::fetch("SELECT id, from_user_id FROM messages WHERE id = ?", [$emailId]);
    if (!$email || $email['from_user_id'] !== $user['id']) {
        forbidden('Accès non autorisé');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant ou invalide');
    }

    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxSize) {
        bad_request('Fichier trop volumineux (max 5 Mo)');
    }

    // Types autorisés
    $allowed = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'text/plain', 'text/csv',
    ];
    if (!in_array($file['type'], $allowed, true)) {
        bad_request('Type de fichier non autorisé');
    }

    // Vérifier le vrai MIME type avec finfo (pas celui du client)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowed, true)) {
        bad_request('Type de fichier non autorisé (' . $realMime . ')');
    }

    $storageDir = __DIR__ . '/../storage/emails/';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

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
        [$attId, $emailId, $filename, $originalName, $realMime, $file['size']]
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

/**
 * Télécharger une pièce jointe
 */
function download_attachment()
{
    global $params;
    $user = require_auth();

    $attId = $params['id'] ?? '';
    if (!$attId) bad_request('ID requis');

    $att = Db::fetch(
        "SELECT ea.*, e.from_user_id FROM message_attachments ea
         JOIN messages e ON e.id = ea.email_id
         WHERE ea.id = ?",
        [$attId]
    );
    if (!$att) not_found('Pièce jointe non trouvée');

    // Vérifier accès
    $isRecipient = Db::getOne(
        "SELECT COUNT(*) FROM message_recipients WHERE email_id = ? AND user_id = ?",
        [$att['email_id'], $user['id']]
    );
    if ($att['from_user_id'] !== $user['id'] && !$isRecipient) {
        forbidden('Accès non autorisé');
    }

    $path = __DIR__ . '/../storage/emails/' . basename($att['filename']);
    if (!file_exists($path)) not_found('Fichier non trouvé');

    // Servir le fichier directement via PHP (storage/ bloqué par .htaccess)
    header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
    if (str_starts_with($att['mime_type'] ?? '', 'image/')) {
        header('Content-Disposition: inline; filename="' . basename($att['original_name']) . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . basename($att['original_name']) . '"');
    }
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

/**
 * Supprimer un email (soft delete)
 */
function delete_message()
{
    global $params;
    $user = require_auth();
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $email = Db::fetch("SELECT from_user_id FROM messages WHERE id = ?", [$id]);
    if (!$email) not_found('Email non trouvé');

    // Supprimer côté destinataire
    Db::exec(
        "UPDATE message_recipients SET deleted = 1 WHERE email_id = ? AND user_id = ?",
        [$id, $user['id']]
    );

    // Supprimer côté expéditeur
    if ($email['from_user_id'] === $user['id']) {
        Db::exec("UPDATE messages SET sender_deleted = 1 WHERE id = ?", [$id]);
    }

    respond(['success' => true]);
}

/**
 * Compter les non-lus (pour badge navbar)
 */
function get_unread_count()
{
    $user = require_auth();

    $count = (int)Db::getOne(
        "SELECT COUNT(*) FROM message_recipients er
         JOIN messages e ON e.id = er.email_id
         WHERE er.user_id = ? AND er.lu = 0 AND er.deleted = 0 AND e.is_draft = 0",
        [$user['id']]
    );

    respond(['success' => true, 'count' => $count]);
}

/**
 * Liste des contacts (pour le compose)
 */
function get_message_contacts()
{
    $user = require_auth();

    $contacts = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, u.fonction_nom,
                COALESCE(m.nom, 'Sans module') AS module_nom,
                COALESCE(m.ordre, 999) AS module_ordre
         FROM users u
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE u.is_active = 1 AND u.id != ?
         ORDER BY module_ordre, m.nom, u.nom, u.prenom",
        [$user['id']]
    );

    respond(['success' => true, 'contacts' => $contacts]);
}

/**
 * Sauvegarder un brouillon (upsert)
 */
function save_draft()
{
    global $params;
    $user = require_auth();

    $draftId = $params['draft_id'] ?? null;
    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = $params['contenu'] ?? '';
    $contenu = mb_substr(strip_tags($contenu, '<p><br><strong><em><ul><ol><li><a><blockquote>'), 0, 10000);
    $toIds = $params['to'] ?? [];
    $ccIds = $params['cc'] ?? [];
    $parentId = $params['parent_id'] ?? null;

    if ($draftId) {
        // Vérifier que le brouillon existe et appartient à l'utilisateur
        $existing = Db::fetch("SELECT id FROM messages WHERE id = ? AND from_user_id = ? AND is_draft = 1", [$draftId, $user['id']]);
        if ($existing) {
            Db::exec("UPDATE messages SET sujet = ?, contenu = ?, updated_at = NOW() WHERE id = ?", [$sujet, $contenu, $draftId]);
            // Supprimer anciens destinataires et réinsérer
            Db::exec("DELETE FROM message_recipients WHERE email_id = ?", [$draftId]);
            foreach (($toIds ?: []) as $uid) {
                Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')", [Uuid::v4(), $draftId, $uid]);
            }
            foreach (($ccIds ?: []) as $uid) {
                Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'cc')", [Uuid::v4(), $draftId, $uid]);
            }
            respond(['success' => true, 'draft_id' => $draftId]);
            return;
        }
    }

    // Créer nouveau brouillon
    $id = Uuid::v4();
    $threadId = $id;
    if ($parentId) {
        $parent = Db::fetch("SELECT thread_id, id FROM messages WHERE id = ?", [$parentId]);
        if ($parent) $threadId = $parent['thread_id'] ?: $parent['id'];
    }

    Db::exec(
        "INSERT INTO messages (id, parent_id, thread_id, from_user_id, sujet, contenu, is_draft) VALUES (?, ?, ?, ?, ?, ?, 1)",
        [$id, $parentId, $threadId, $user['id'], $sujet, $contenu]
    );

    foreach (($toIds ?: []) as $uid) {
        Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')", [Uuid::v4(), $id, $uid]);
    }
    foreach (($ccIds ?: []) as $uid) {
        Db::exec("INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'cc')", [Uuid::v4(), $id, $uid]);
    }

    respond(['success' => true, 'draft_id' => $id]);
}

/**
 * Supprimer un brouillon
 */
function delete_draft()
{
    global $params;
    $user = require_auth();
    $id = $params['draft_id'] ?? '';
    if (!$id) bad_request('draft_id requis');

    $draft = Db::fetch("SELECT id FROM messages WHERE id = ? AND from_user_id = ? AND is_draft = 1", [$id, $user['id']]);
    if (!$draft) not_found('Brouillon non trouvé');

    Db::exec("DELETE FROM message_recipients WHERE email_id = ?", [$id]);
    Db::exec("DELETE FROM message_attachments WHERE email_id = ?", [$id]);
    Db::exec("DELETE FROM messages WHERE id = ?", [$id]);

    respond(['success' => true]);
}
