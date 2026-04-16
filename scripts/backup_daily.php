#!/usr/bin/env php
<?php
/**
 * Sauvegarde globale quotidienne automatique
 *
 * Cron: 0 3 * * * php /path/to/spocspace/scripts/backup_daily.php
 *
 * - Cree une sauvegarde globale
 * - Applique la retention (14 jours quotidiens, 8 semaines hebdomadaires)
 * - Copie en hebdomadaire le dimanche
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../admin/api_modules/backups.php';

echo date('Y-m-d H:i:s') . " — Demarrage sauvegarde quotidienne\n";

// Create system user ID for automated backups
$systemUserId = 'system-cron';

try {
    $result = create_backup_zip('global', null, $systemUserId);
    echo "Sauvegarde creee : " . $result['filename'] . " (" . format_file_size($result['file_size']) . ")\n";
    echo "Tables : " . count($result['tables_included']) . "\n";

    // Weekly copy on Sundays
    if ((int) date('w') === 0) {
        $weekNum = date('Y-\WW');
        $weeklyFilename = "backup_weekly_{$weekNum}.zip";
        $globalDir = backup_dir_global();
        $src = $globalDir . '/' . $result['filename'];
        $dst = $globalDir . '/' . $weeklyFilename;

        if (file_exists($src) && !file_exists($dst)) {
            copy($src, $dst);
            // Register weekly backup in DB
            Db::exec(
                "INSERT INTO backups (id, user_id, type, filename, file_size, tables_included, row_counts, checksum_sha256, created_by)
                 VALUES (?, NULL, 'global', ?, ?, ?, ?, ?, ?)",
                [
                    Uuid::v4(),
                    $weeklyFilename,
                    $result['file_size'],
                    json_encode($result['tables_included']),
                    json_encode($result['row_counts']),
                    $result['checksum'],
                    $systemUserId,
                ]
            );
            echo "Copie hebdomadaire : $weeklyFilename\n";
        }
    }

    // Enforce retention
    enforce_global_retention();
    echo "Retention appliquee.\n";

} catch (\Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}

echo date('Y-m-d H:i:s') . " — Termine.\n";
