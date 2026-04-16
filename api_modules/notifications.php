<?php

require_once __DIR__ . '/../core/Notification.php';

function get_notifications()
{
    $user = require_auth();
    global $params;

    $limit = min(intval($params['limit'] ?? 30), 50);
    $offset = max(intval($params['offset'] ?? 0), 0);

    $notifications = Notification::getForUser($user['id'], $limit, $offset);
    $unread = Notification::unreadCount($user['id']);

    respond([
        'success' => true,
        'notifications' => $notifications,
        'unread' => $unread,
    ]);
}

function get_notifications_count()
{
    $user = require_auth();
    $unread = Notification::unreadCount($user['id']);
    respond(['success' => true, 'unread' => $unread]);
}

function mark_notification_read()
{
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Notification::markRead($id, $user['id']);
    respond(['success' => true]);
}

function mark_all_notifications_read()
{
    $user = require_auth();
    Notification::markAllRead($user['id']);
    respond(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
}

function archive_notification()
{
    $user = require_auth();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("UPDATE notifications SET is_archived = 1, is_read = 1 WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    respond(['success' => true]);
}

function archive_all_read_notifications()
{
    $user = require_auth();
    Db::exec("UPDATE notifications SET is_archived = 1 WHERE user_id = ? AND is_read = 1 AND is_archived = 0", [$user['id']]);
    respond(['success' => true, 'message' => 'Notifications lues archivées']);
}

/**
 * Single poll endpoint — returns all badge counts + new alerts in one request
 */
function get_poll_data()
{
    $user = require_auth();
    $uid = $user['id'];

    // Notification unread count
    $unreadNotifs = Notification::unreadCount($uid);

    // Messages unread count
    $unreadMessages = (int) Db::getOne(
        "SELECT COUNT(*) FROM message_recipients er
         JOIN messages e ON e.id = er.email_id
         WHERE er.user_id = ? AND er.lu = 0 AND er.deleted = 0 AND e.is_draft = 0",
        [$uid]
    );

    // Annonces pending ack count
    $pendingAck = (int) Db::getOne(
        "SELECT COUNT(*) FROM annonces a
         WHERE a.requires_ack = 1 AND a.visible = 1 AND a.archived_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM annonce_acks ak WHERE ak.annonce_id = a.id AND ak.user_id = ?)",
        [$uid]
    );

    // Pending alerts (for live toasts)
    $alerts = Db::fetchAll(
        "SELECT a.id, a.title, a.message, a.priority, a.created_at,
                u.prenom AS creator_prenom, u.nom AS creator_nom
         FROM alerts a
         JOIN users u ON u.id = a.created_by
         WHERE a.is_active = 1
           AND (a.expires_at IS NULL OR a.expires_at > NOW())
           AND NOT EXISTS (SELECT 1 FROM alert_reads ar WHERE ar.alert_id = a.id AND ar.user_id = ?)
           AND (
               a.target = 'all'
               OR (a.target = 'module' AND a.target_value IN (SELECT module_id FROM user_modules WHERE user_id = ?))
               OR (a.target = 'role' AND a.target_value = ?)
           )
         ORDER BY a.priority = 'haute' DESC, a.created_at DESC",
        [$uid, $uid, $user['role'] ?? 'collaborateur']
    );

    respond([
        'success' => true,
        'unread_notifs' => $unreadNotifs,
        'unread_messages' => $unreadMessages,
        'pending_ack' => $pendingAck,
        'alerts' => $alerts,
    ]);
}
