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
