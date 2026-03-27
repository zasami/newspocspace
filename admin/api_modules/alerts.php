<?php
/**
 * Admin Alerts API — Broadcast high-importance messages
 */

function admin_get_alerts()
{
    require_responsable();
    global $params;

    $page = max(1, (int)($params['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $total = (int)Db::getOne("SELECT COUNT(*) FROM alerts");

    $alerts = Db::fetchAll(
        "SELECT a.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                (SELECT COUNT(*) FROM alert_reads ar WHERE ar.alert_id = a.id) AS read_count,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) AS total_users
         FROM alerts a
         JOIN users u ON u.id = a.created_by
         ORDER BY a.created_at DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    respond(['success' => true, 'alerts' => $alerts, 'total' => $total, 'page' => $page]);
}

function admin_create_alert()
{
    $admin = require_admin();
    global $params;

    $title = Sanitize::text($params['title'] ?? '', 255);
    $message = mb_substr(strip_tags($params['message'] ?? '', '<p><br><strong><em><ul><ol><li>'), 0, 5000);
    $priority = in_array($params['priority'] ?? '', ['normale', 'haute']) ? $params['priority'] : 'normale';
    $target = in_array($params['target'] ?? '', ['all', 'module', 'fonction']) ? $params['target'] : 'all';
    $targetValue = Sanitize::text($params['target_value'] ?? '', 100);
    $expiresAt = $params['expires_at'] ?? null;

    if (!$title) bad_request('Titre requis');
    if (!$message) bad_request('Message requis');

    if ($expiresAt) {
        $dt = \DateTime::createFromFormat('Y-m-d', $expiresAt);
        if (!$dt) $expiresAt = null;
        else $expiresAt = $dt->format('Y-m-d') . ' 23:59:59';
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO alerts (id, title, message, priority, target, target_value, created_by, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $title, $message, $priority, $target, $targetValue ?: null, $admin['id'], $expiresAt]
    );

    // Also create notifications for targeted users (best-effort)
    try {
        $userIds = [];
        if ($target === 'all') {
            $userIds = array_column(Db::fetchAll("SELECT id FROM users WHERE is_active = 1"), 'id');
        } elseif ($target === 'module') {
            $userIds = array_column(Db::fetchAll(
                "SELECT DISTINCT user_id AS id FROM user_modules WHERE module_id = ?", [$targetValue]
            ), 'id');
        } elseif ($target === 'fonction') {
            $userIds = array_column(Db::fetchAll(
                "SELECT id FROM users WHERE is_active = 1 AND fonction_id = (SELECT id FROM fonctions WHERE code = ? LIMIT 1)", [$targetValue]
            ), 'id');
        }

        if (!empty($userIds)) {
            Notification::createBulk($userIds, 'alert', $title, $message, '');
        }
    } catch (\Throwable $e) {
        // Notifications are best-effort — alert was already created
        error_log('Alert notification error: ' . $e->getMessage());
    }

    respond(['success' => true, 'message' => 'Alerte créée', 'id' => $id]);
}

function admin_get_alert_reads()
{
    require_responsable();
    global $params;

    $alertId = $params['id'] ?? '';
    if (!$alertId) bad_request('ID requis');

    $alert = Db::fetch("SELECT a.*, u.prenom AS creator_prenom, u.nom AS creator_nom
                         FROM alerts a JOIN users u ON u.id = a.created_by
                         WHERE a.id = ?", [$alertId]);
    if (!$alert) not_found('Alerte introuvable');

    // Get targeted users based on alert target
    $target = $alert['target'];
    $targetValue = $alert['target_value'];

    if ($target === 'module' && $targetValue) {
        $users = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, u.email, u.last_login, u.photo,
                    f.nom AS fonction_nom, f.code AS fonction_code,
                    ar.read_at
             FROM users u
             JOIN user_modules um ON um.user_id = u.id AND um.module_id = ?
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             LEFT JOIN alert_reads ar ON ar.alert_id = ? AND ar.user_id = u.id
             WHERE u.is_active = 1
             ORDER BY ar.read_at IS NULL DESC, ar.read_at ASC, u.nom ASC",
            [$targetValue, $alertId]
        );
    } elseif ($target === 'fonction' && $targetValue) {
        $users = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, u.email, u.last_login, u.photo,
                    f.nom AS fonction_nom, f.code AS fonction_code,
                    ar.read_at
             FROM users u
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             LEFT JOIN alert_reads ar ON ar.alert_id = ? AND ar.user_id = u.id
             WHERE u.is_active = 1 AND f.code = ?
             ORDER BY ar.read_at IS NULL DESC, ar.read_at ASC, u.nom ASC",
            [$alertId, $targetValue]
        );
    } else {
        $users = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, u.email, u.last_login, u.photo,
                    f.nom AS fonction_nom, f.code AS fonction_code,
                    ar.read_at
             FROM users u
             LEFT JOIN fonctions f ON f.id = u.fonction_id
             LEFT JOIN alert_reads ar ON ar.alert_id = ? AND ar.user_id = u.id
             WHERE u.is_active = 1
             ORDER BY ar.read_at IS NULL DESC, ar.read_at ASC, u.nom ASC",
            [$alertId]
        );
    }

    $readCount = 0;
    foreach ($users as $u) {
        if ($u['read_at']) $readCount++;
    }

    respond([
        'success' => true,
        'alert' => $alert,
        'users' => $users,
        'read_count' => $readCount,
        'total_users' => count($users),
    ]);
}

function admin_toggle_alert()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("UPDATE alerts SET is_active = NOT is_active WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_delete_alert()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM alert_reads WHERE alert_id = ?", [$id]);
    Db::exec("DELETE FROM alerts WHERE id = ?", [$id]);
    respond(['success' => true]);
}
