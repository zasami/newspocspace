<?php

function admin_get_connexions() {
    global $params;
    $date = $params['date'] ?? date('Y-m-d');
    $period = $params['period'] ?? 'day'; // day, week, month

    $where = "1=1";
    $binds = [];

    if ($period === 'day') {
        $where .= " AND DATE(c.created_at) = ?";
        $binds[] = $date;
    } elseif ($period === 'week') {
        $where .= " AND c.created_at >= DATE_SUB(?, INTERVAL 7 DAY)";
        $binds[] = $date . ' 23:59:59';
    } elseif ($period === 'month') {
        $where .= " AND c.created_at >= DATE_SUB(?, INTERVAL 30 DAY)";
        $binds[] = $date . ' 23:59:59';
    }

    if (!empty($params['search'])) {
        $s = '%' . $params['search'] . '%';
        $where .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR c.ip_address LIKE ?)";
        $binds = array_merge($binds, [$s, $s, $s]);
    }

    $rows = Db::fetchAll(
        "SELECT c.id, c.user_id, c.ip_address, c.user_agent, c.created_at,
                u.nom, u.prenom, u.email, u.role, u.photo, f.nom AS fonction_nom
         FROM connexions c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE $where
         ORDER BY c.created_at DESC
         LIMIT 500",
        $binds
    );

    // Stats
    $statsWhere = $period === 'day' ? "DATE(c.created_at) = ?" : ($period === 'week' ? "c.created_at >= DATE_SUB(?, INTERVAL 7 DAY)" : "c.created_at >= DATE_SUB(?, INTERVAL 30 DAY)");
    $statsBinds = [$period === 'day' ? $date : $date . ' 23:59:59'];

    $stats = Db::fetch(
        "SELECT COUNT(*) AS total_connexions,
                COUNT(DISTINCT c.user_id) AS users_uniques,
                COUNT(DISTINCT c.ip_address) AS ips_uniques
         FROM connexions c WHERE $statsWhere",
        $statsBinds
    );

    // Top users
    $topUsers = Db::fetchAll(
        "SELECT c.user_id, u.nom, u.prenom, u.photo, COUNT(*) AS nb
         FROM connexions c JOIN users u ON u.id = c.user_id
         WHERE $statsWhere
         GROUP BY c.user_id, u.nom, u.prenom, u.photo ORDER BY nb DESC LIMIT 10",
        $statsBinds
    );

    // Connexions par heure (chart)
    $parHeure = Db::fetchAll(
        "SELECT HOUR(c.created_at) AS heure, COUNT(*) AS nb
         FROM connexions c WHERE $statsWhere
         GROUP BY HOUR(c.created_at) ORDER BY heure",
        $statsBinds
    );

    respond([
        'success' => true,
        'connexions' => $rows,
        'stats' => $stats ?: ['total_connexions' => 0, 'users_uniques' => 0, 'ips_uniques' => 0],
        'top_users' => $topUsers,
        'par_heure' => $parHeure,
    ]);
}
