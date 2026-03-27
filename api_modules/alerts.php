<?php
/**
 * Employee-side Alerts API — Check and acknowledge high-importance alerts
 */

function get_pending_alerts()
{
    $user = require_auth();

    // Get active, non-expired alerts that the user hasn't read
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
               OR (a.target = 'fonction' AND a.target_value = (SELECT f.code FROM fonctions f WHERE f.id = ?))
           )
         ORDER BY a.priority DESC, a.created_at DESC",
        [$user['id'], $user['id'], $user['fonction_id'] ?? '']
    );

    respond(['success' => true, 'alerts' => $alerts]);
}

function mark_alert_read()
{
    $user = require_auth();
    global $params;

    $alertId = $params['alert_id'] ?? '';
    if (!$alertId) bad_request('ID requis');

    // Check it exists
    $alert = Db::fetch("SELECT id FROM alerts WHERE id = ?", [$alertId]);
    if (!$alert) not_found('Alerte non trouvée');

    // Insert read record (ignore duplicate)
    $existing = Db::fetch(
        "SELECT id FROM alert_reads WHERE alert_id = ? AND user_id = ?",
        [$alertId, $user['id']]
    );

    if (!$existing) {
        Db::exec(
            "INSERT INTO alert_reads (id, alert_id, user_id) VALUES (?, ?, ?)",
            [Uuid::v4(), $alertId, $user['id']]
        );
    }

    respond(['success' => true]);
}
