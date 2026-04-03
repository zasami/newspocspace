<?php

function admin_get_system_info()
{
    require_admin();

    respond([
        'success' => true,
        'version' => APP_SEMVER,
        'build' => APP_VERSION,
        'build_date' => APP_BUILD_DATE,
        'app_name' => APP_NAME,
        'php_version' => PHP_VERSION,
        'db_name' => DB_NAME,
        'server' => php_uname('s') . ' ' . php_uname('r'),
    ]);
}

function admin_get_dashboard_stats()
{
    require_responsable();
    $totalUsers = Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $totalAbsences = Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
    $totalDesirs = Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
    $userId = $_SESSION['ss_user']['id'] ?? '';
    $totalMessages = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$userId]);

    $absencesParType = Db::fetchAll(
        "SELECT type, COUNT(*) as total FROM absences WHERE statut = 'valide' AND date_fin >= CURDATE() GROUP BY type"
    );

    $recentAbsences = Db::fetchAll(
        "SELECT a.*, u.prenom, u.nom, u.photo
         FROM absences a
         JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC
         LIMIT 10"
    );

    // Monthly absences for chart (current year)
    $year = date('Y');
    $absencesParMois = Db::fetchAll(
        "SELECT MONTH(date_debut) as mois, COUNT(*) as total
         FROM absences
         WHERE YEAR(date_debut) = ?
         GROUP BY MONTH(date_debut)
         ORDER BY mois",
        [$year]
    );

    respond([
        'success' => true,
        'stats' => [
            'total_users' => (int)$totalUsers,
            'pending_absences' => (int)$totalAbsences,
            'pending_desirs' => (int)$totalDesirs,
            'unread_messages' => (int)$totalMessages,
        ],
        'absences_par_type' => $absencesParType,
        'absences_par_mois' => $absencesParMois,
        'recent_absences' => $recentAbsences,
    ]);
}

function admin_session_ping()
{
    respond(['success' => true, 'ts' => time()]);
}
