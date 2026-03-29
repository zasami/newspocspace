<?php
/**
 * Per-user permission system (whitelist-by-default)
 * No row = accessible. granted=0 = denied.
 */
class Permission
{
    const ALL_KEYS = [
        // Pages
        'page_planning'    => 'Planning',
        'page_repartition' => 'Répartition',
        'page_desirs'      => 'Désirs',
        'page_vacances'    => 'Vacances',
        'page_absences'    => 'Absences',
        'page_changements' => 'Changements',
        'page_collegues'   => 'Collègues',
        'page_covoiturage' => 'Covoiturage',
        'page_messages'    => 'Messages',
        'page_votes'       => 'Votes',
        'page_pv'          => 'Procès-Verbaux',
        'page_sondages'    => 'Sondages',
        'page_documents'   => 'Documents',
        'page_fiches_salaire' => 'Fiches de salaire',
        'page_cuisine'     => 'Cuisine',
        // Cuisine sub-permissions
        'cuisine_saisie_menu'          => 'Saisir/modifier menus',
        'cuisine_reservations_collab'  => 'Réservations collaborateurs',
        'cuisine_reservations_famille' => 'Réservations famille',
        'cuisine_table_vip'            => 'Table VIP',
    ];

    // Page key => permission key mapping (for sidebar filtering)
    const PAGE_MAP = [
        'planning'       => 'page_planning',
        'repartition'    => 'page_repartition',
        'desirs'         => 'page_desirs',
        'vacances'       => 'page_vacances',
        'absences'       => 'page_absences',
        'changements'    => 'page_changements',
        'collegues'      => 'page_collegues',
        'covoiturage'    => 'page_covoiturage',
        'messages'       => 'page_messages',
        'votes'          => 'page_votes',
        'pv'             => 'page_pv',
        'sondages'       => 'page_sondages',
        'documents'      => 'page_documents',
        'fiches-salaire' => 'page_fiches_salaire',
        'cuisine'        => 'page_cuisine',
        'cuisine-menus'        => 'cuisine_saisie_menu',
        'cuisine-reservations' => 'cuisine_reservations_collab',
        'cuisine-famille'      => 'cuisine_reservations_famille',
        'cuisine-vip'          => 'cuisine_table_vip',
    ];

    /**
     * Check if user has a specific permission
     * No row = granted (whitelist-by-default)
     */
    public static function check(string $userId, string $key): bool
    {
        $granted = Db::getOne(
            "SELECT granted FROM user_permissions WHERE user_id = ? AND permission_key = ?",
            [$userId, $key]
        );
        return $granted === null || (int) $granted === 1;
    }

    /**
     * Get list of denied permission keys for a user
     */
    public static function getDenied(string $userId): array
    {
        $rows = Db::fetchAll(
            "SELECT permission_key FROM user_permissions WHERE user_id = ? AND granted = 0",
            [$userId]
        );
        return array_column($rows, 'permission_key');
    }

    /**
     * Get full permission matrix for a user (for admin UI)
     */
    public static function getAll(string $userId): array
    {
        $rows = Db::fetchAll(
            "SELECT permission_key, granted FROM user_permissions WHERE user_id = ?",
            [$userId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['permission_key']] = (int) $r['granted'];
        }
        // Build full matrix: no row = granted (1)
        $result = [];
        foreach (self::ALL_KEYS as $key => $label) {
            $result[$key] = [
                'label'   => $label,
                'granted' => $map[$key] ?? 1,
            ];
        }
        return $result;
    }

    /**
     * Bulk upsert permissions for a user
     * $perms = ['page_planning' => 1, 'page_desirs' => 0, ...]
     */
    public static function setForUser(string $userId, array $perms): void
    {
        foreach ($perms as $key => $granted) {
            if (!isset(self::ALL_KEYS[$key])) continue;
            $granted = (int) $granted;
            Db::exec(
                "INSERT INTO user_permissions (user_id, permission_key, granted)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                [$userId, $key, $granted]
            );
        }
    }
}
