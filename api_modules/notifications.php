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
