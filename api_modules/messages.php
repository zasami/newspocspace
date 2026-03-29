<?php
/**
 * Messages API actions (employee SPA — simple messaging)
 */

function get_mes_messages()
{
    $user = require_auth();
    $uid = $user['id'];

    $messages = Db::fetchAll(
        "SELECT m.id, m.sujet, m.contenu, m.from_user_id, m.created_at,
                uf.prenom AS from_prenom, uf.nom AS from_nom,
                (SELECT GROUP_CONCAT(CONCAT(u2.prenom, ' ', u2.nom) SEPARATOR ', ')
                 FROM message_recipients mr JOIN users u2 ON u2.id = mr.user_id
                 WHERE mr.email_id = m.id AND mr.type = 'to') AS to_names,
                COALESCE((SELECT mr3.lu FROM message_recipients mr3 WHERE mr3.email_id = m.id AND mr3.user_id = ? LIMIT 1), 1) AS lu
         FROM messages m
         JOIN users uf ON uf.id = m.from_user_id
         WHERE m.is_draft = 0
           AND (m.from_user_id = ? OR EXISTS (SELECT 1 FROM message_recipients mr2 WHERE mr2.email_id = m.id AND mr2.user_id = ? AND mr2.deleted = 0))
         ORDER BY m.created_at DESC
         LIMIT 50",
        [$uid, $uid, $uid]
    );

    respond(['success' => true, 'messages' => $messages]);
}

function send_message()
{
    global $params;
    $user = require_auth();

    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = Sanitize::text($params['contenu'] ?? '', 2000);

    if (!$sujet || !$contenu) {
        bad_request('Sujet et contenu requis');
    }

    // Send to direction/admin users
    $admins = Db::fetchAll("SELECT id FROM users WHERE role IN ('admin','direction') AND is_active = 1");
    if (empty($admins)) {
        bad_request('Aucun administrateur trouvé');
    }

    $emailId = Uuid::v4();
    $threadId = $emailId;

    Db::exec(
        "INSERT INTO messages (id, thread_id, from_user_id, sujet, contenu)
         VALUES (?, ?, ?, ?, ?)",
        [$emailId, $threadId, $user['id'], $sujet, $contenu]
    );

    foreach ($admins as $admin) {
        Db::exec(
            "INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')",
            [Uuid::v4(), $emailId, $admin['id']]
        );
    }

    respond(['success' => true, 'message' => 'Message envoyé', 'id' => $emailId]);
}

function mark_message_read()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec(
        "UPDATE message_recipients SET lu = 1, lu_at = NOW() WHERE email_id = ? AND user_id = ? AND lu = 0",
        [$id, $user['id']]
    );

    respond(['success' => true]);
}
