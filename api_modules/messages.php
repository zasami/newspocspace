<?php
/**
 * Messages API actions
 */

function get_mes_messages()
{
    $user = require_auth();

    $messages = Db::fetchAll(
        "SELECT m.*, uf.prenom AS from_prenom, uf.nom AS from_nom,
                ut.prenom AS to_prenom, ut.nom AS to_nom
         FROM messages m
         JOIN users uf ON uf.id = m.from_user_id
         LEFT JOIN users ut ON ut.id = m.to_user_id
         WHERE m.from_user_id = ? OR m.to_user_id = ?
         ORDER BY m.created_at DESC
         LIMIT 50",
        [$user['id'], $user['id']]
    );

    respond(['success' => true, 'messages' => $messages]);
}

function send_message()
{
    global $params;
    $user = require_auth();

    $sujet = Sanitize::text($params['sujet'] ?? '', 255);
    $contenu = Sanitize::text($params['contenu'] ?? '', 2000);
    $toUserId = $params['to_user_id'] ?? null;

    if (!$sujet || !$contenu) {
        bad_request('Sujet et contenu requis');
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO messages (id, from_user_id, to_user_id, sujet, contenu)
         VALUES (?, ?, ?, ?, ?)",
        [$id, $user['id'], $toUserId, $sujet, $contenu]
    );

    respond(['success' => true, 'message' => 'Message envoyé', 'id' => $id]);
}

function mark_message_read()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec(
        "UPDATE messages SET lu = 1, lu_at = NOW() WHERE id = ? AND to_user_id = ?",
        [$id, $user['id']]
    );

    respond(['success' => true]);
}
