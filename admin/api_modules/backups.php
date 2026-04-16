<?php
/**
 * Module Sauvegarde & Restauration
 * Per-user (admin) + Global backups
 */

define('BACKUP_DIR', __DIR__ . '/../../data/backups');

// Tables sauvegardées pour un backup per-user
define('USER_BACKUP_TABLES', [
    'documents',
    'document_versions',
    'document_access',
    'messages',
    'message_recipients',
    'message_attachments',
    'email_externe_cache',
    'email_externe_contacts',
]);

// Tables exclues du backup global (tables de cache/sessions/rate-limits)
define('GLOBAL_EXCLUDE_TABLES', [
    'backups',
    'rate_limits',
    'famille_rate_limits',
    'famille_sessions',
    'connexions',
    'email_externe_cache',
]);

/* ─────────────── Schema Compatibility ─────────────── */

/**
 * Get current schema version number
 */
function get_current_schema_version(): int
{
    $v = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'schema_version'");
    return $v ? (int) $v : 0;
}

/**
 * Snapshot the structure of all tables (column names, types, keys)
 */
function snapshot_schema(array $tables): array
{
    $schema = [];
    foreach ($tables as $table) {
        $cols = Db::fetchAll("SHOW COLUMNS FROM `$table`");
        $schema[$table] = [];
        foreach ($cols as $col) {
            $schema[$table][$col['Field']] = [
                'type' => $col['Type'],
                'null' => $col['Null'],
                'key' => $col['Key'],
                'default' => $col['Default'],
            ];
        }
    }
    return $schema;
}

/**
 * Compare backup schema with current schema and return compatibility report
 */
function check_backup_compatibility(array $manifest): array
{
    $currentVersion = get_current_schema_version();
    $backupVersion = $manifest['schema_version'] ?? 0;
    $backupSchema = $manifest['schema_snapshot'] ?? [];
    $backupAppVersion = $manifest['app_version'] ?? 'unknown';

    $report = [
        'compatible' => true,
        'backup_schema_version' => $backupVersion,
        'current_schema_version' => $currentVersion,
        'backup_app_version' => $backupAppVersion,
        'current_app_version' => defined('APP_VERSION') ? APP_VERSION : 'unknown',
        'version_match' => $backupVersion === $currentVersion,
        'warnings' => [],
        'errors' => [],
        'table_diffs' => [],
    ];

    // Backup is newer than current system — cannot restore
    if ($backupVersion > $currentVersion) {
        $report['compatible'] = false;
        $report['errors'][] = "La sauvegarde (v{$backupVersion}) est plus récente que le système actuel (v{$currentVersion}). Mettez à jour SpocSpace avant de restaurer.";
        return $report;
    }

    // Backup is older — check column differences
    if ($backupVersion < $currentVersion) {
        $report['warnings'][] = "La sauvegarde (v{$backupVersion}) est antérieure au système actuel (v{$currentVersion}). Les données seront adaptées automatiquement.";
    }

    // No schema snapshot in old backups
    if (empty($backupSchema)) {
        if ($backupVersion > 0) {
            $report['warnings'][] = "Pas de snapshot de schéma dans cette sauvegarde. Compatibilité non vérifiable.";
        }
        return $report;
    }

    // Compare each table's columns
    foreach ($backupSchema as $table => $backupCols) {
        // Check if table still exists
        $exists = Db::getOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
        if (!$exists) {
            $report['table_diffs'][$table] = ['status' => 'table_removed', 'message' => "Table '$table' n'existe plus dans le système actuel"];
            $report['warnings'][] = "Table '$table' supprimée — ces données seront ignorées";
            continue;
        }

        $currentCols = Db::fetchAll("SHOW COLUMNS FROM `$table`");
        $currentColNames = array_column($currentCols, 'Field');
        $backupColNames = array_keys($backupCols);

        $added = array_diff($currentColNames, $backupColNames);   // new columns in current (not in backup)
        $removed = array_diff($backupColNames, $currentColNames); // columns in backup but not in current
        $common = array_intersect($backupColNames, $currentColNames);

        // Type changes
        $typeChanges = [];
        $currentColMap = [];
        foreach ($currentCols as $c) $currentColMap[$c['Field']] = $c;
        foreach ($common as $col) {
            $bType = $backupCols[$col]['type'] ?? '';
            $cType = $currentColMap[$col]['Type'] ?? '';
            if ($bType !== $cType) {
                $typeChanges[$col] = ['from' => $bType, 'to' => $cType];
            }
        }

        if ($added || $removed || $typeChanges) {
            $diff = ['added_columns' => array_values($added), 'removed_columns' => array_values($removed), 'type_changes' => $typeChanges];
            $report['table_diffs'][$table] = $diff;

            if ($removed) {
                $report['warnings'][] = "Table '$table' : colonnes supprimées depuis le backup : " . implode(', ', $removed) . " — ces colonnes seront ignorées";
            }
            if ($added) {
                $report['warnings'][] = "Table '$table' : nouvelles colonnes ajoutées : " . implode(', ', $added) . " — valeurs par défaut utilisées";
            }
        }
    }

    // Check for new tables in current that backup doesn't know about
    $backupTables = array_keys($backupSchema);
    $currentTables = array_column(Db::fetchAll("SHOW TABLES"), array_key_first(Db::fetchAll("SHOW TABLES")[0]));
    $newTables = array_diff($currentTables, $backupTables, GLOBAL_EXCLUDE_TABLES);
    if ($newTables) {
        $report['warnings'][] = count($newTables) . " nouvelles tables depuis la sauvegarde — non affectées par la restauration";
    }

    return $report;
}

/**
 * Adapt INSERT SQL from backup to match current schema
 * Handles: removed columns (strip them), added columns (let DB use defaults)
 */
function adapt_sql_to_current_schema(string $sqlContent, string $table): string
{
    // Check if table exists
    $exists = Db::getOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
    if (!$exists) return ''; // table removed, skip

    // Get current columns
    $currentCols = Db::fetchAll("SHOW COLUMNS FROM `$table`");
    $currentColNames = array_column($currentCols, 'Field');

    // Parse the INSERT statement to get column list
    // Format: INSERT INTO `table` (`col1`, `col2`, ...) VALUES ...
    if (!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`\s+\(([^)]+)\)/i', $sqlContent, $m)) {
        return $sqlContent; // can't parse, return as-is
    }

    $backupColStr = $m[1];
    preg_match_all('/`([^`]+)`/', $backupColStr, $colMatches);
    $backupCols = $colMatches[1];

    // Find columns to remove (in backup but not in current)
    $removedIndexes = [];
    foreach ($backupCols as $i => $col) {
        if (!in_array($col, $currentColNames)) {
            $removedIndexes[] = $i;
        }
    }

    if (empty($removedIndexes)) return $sqlContent; // no adaptation needed

    // Build new column list (without removed columns)
    $newCols = [];
    foreach ($backupCols as $i => $col) {
        if (!in_array($i, $removedIndexes)) $newCols[] = $col;
    }
    $newColStr = '`' . implode('`, `', $newCols) . '`';

    // Rebuild: replace column list
    $pattern = '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`\s+\()([^)]+)(\))/i';
    $sqlContent = preg_replace($pattern, '${1}' . $newColStr . '${3}', $sqlContent);

    // Now we need to remove the values at the removed indexes from each value tuple
    // This is complex with regex, so we parse the VALUES section
    $sqlContent = preg_replace_callback(
        '/\(([^)]+)\)/',
        function ($match) use ($removedIndexes) {
            // Parse values carefully (handle quoted strings with commas)
            $vals = parse_sql_values($match[1]);
            if ($vals === null) return $match[0]; // can't parse, keep as-is
            $newVals = [];
            foreach ($vals as $i => $v) {
                if (!in_array($i, $removedIndexes)) $newVals[] = $v;
            }
            return '(' . implode(', ', $newVals) . ')';
        },
        $sqlContent
    );

    return $sqlContent;
}

/**
 * Parse SQL value list respecting quoted strings
 */
function parse_sql_values(string $valueStr): ?array
{
    $values = [];
    $current = '';
    $inQuote = false;
    $escaped = false;

    for ($i = 0; $i < strlen($valueStr); $i++) {
        $ch = $valueStr[$i];
        if ($escaped) {
            $current .= $ch;
            $escaped = false;
            continue;
        }
        if ($ch === '\\') {
            $current .= $ch;
            $escaped = true;
            continue;
        }
        if ($ch === "'" && !$inQuote) {
            $inQuote = true;
            $current .= $ch;
            continue;
        }
        if ($ch === "'" && $inQuote) {
            $current .= $ch;
            // Check for '' escape
            if ($i + 1 < strlen($valueStr) && $valueStr[$i + 1] === "'") {
                $current .= "'";
                $i++;
                continue;
            }
            $inQuote = false;
            continue;
        }
        if ($ch === ',' && !$inQuote) {
            $values[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $values[] = trim($current);

    return $values;
}

/* ─────────────── Helpers ─────────────── */

function backup_dir_user(string $userId): string
{
    $dir = BACKUP_DIR . '/users/' . $userId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function backup_dir_global(): string
{
    $dir = BACKUP_DIR . '/global';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

/**
 * Dump rows of a table as INSERT statements (filtered or full)
 */
function dump_table_sql(string $table, ?string $whereClause = null, array $whereParams = []): string
{
    $sql = "SELECT * FROM `$table`";
    if ($whereClause) $sql .= " WHERE $whereClause";

    $rows = Db::fetchAll($sql, $whereParams);
    if (empty($rows)) return '';

    $columns = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $columns) . '`';

    $pdo = Db::connect();
    $inserts = [];
    foreach ($rows as $row) {
        $vals = [];
        foreach ($row as $v) {
            $vals[] = $v === null ? 'NULL' : $pdo->quote($v);
        }
        $inserts[] = '(' . implode(', ', $vals) . ')';
    }

    $out = "-- Table: $table (" . count($rows) . " rows)\n";
    $out .= "INSERT INTO `$table` ($colList) VALUES\n";
    $out .= implode(",\n", $inserts) . ";\n\n";

    return $out;
}

/**
 * Create a ZIP backup and register it in DB
 */
function create_backup_zip(string $type, ?string $userId, string $createdBy): array
{
    $timestamp = date('Y-m-d_His');
    $id = Uuid::v4();

    if ($type === 'user') {
        $dir = backup_dir_user($userId);
        $filename = "backup_user_{$timestamp}.zip";
    } else {
        $dir = backup_dir_global();
        $filename = "backup_global_{$timestamp}.zip";
    }

    $zipPath = $dir . '/' . $filename;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        bad_request('Impossible de créer l\'archive ZIP');
    }

    $rowCounts = [];
    $tablesIncluded = [];

    if ($type === 'user') {
        // Per-user: only user-related data
        foreach (USER_BACKUP_TABLES as $table) {
            $where = null;
            $params = [];

            // Determine the user column for this table
            switch ($table) {
                case 'documents':
                    $where = "uploaded_by = ?";
                    $params = [$userId];
                    break;
                case 'document_versions':
                    $where = "document_id IN (SELECT id FROM documents WHERE uploaded_by = ?)";
                    $params = [$userId];
                    break;
                case 'document_access':
                    $where = "document_id IN (SELECT id FROM documents WHERE uploaded_by = ?)";
                    $params = [$userId];
                    break;
                case 'messages':
                    $where = "from_user_id = ? OR id IN (SELECT email_id FROM message_recipients WHERE user_id = ?)";
                    $params = [$userId, $userId];
                    break;
                case 'message_recipients':
                    $where = "user_id = ? OR email_id IN (SELECT id FROM messages WHERE from_user_id = ?)";
                    $params = [$userId, $userId];
                    break;
                case 'message_attachments':
                    $where = "email_id IN (SELECT id FROM messages WHERE from_user_id = ? UNION SELECT email_id FROM message_recipients WHERE user_id = ?)";
                    $params = [$userId, $userId];
                    break;
                case 'email_externe_contacts':
                    $where = "created_by = ?";
                    $params = [$userId];
                    break;
                default:
                    $where = "1=0";
            }

            $sql = dump_table_sql($table, $where, $params);
            if ($sql) {
                $zip->addFromString($table . '.sql', $sql);
                $tablesIncluded[] = $table;
                // Count rows
                $countSql = "SELECT COUNT(*) FROM `$table` WHERE $where";
                $rowCounts[$table] = (int) Db::getOne($countSql, $params);
            }
        }

        // Copy user's uploaded files
        $docs = Db::fetchAll(
            "SELECT id, filepath FROM documents WHERE uploaded_by = ? AND filepath IS NOT NULL AND filepath != ''",
            [$userId]
        );
        $storageBase = __DIR__ . '/../../storage/';
        foreach ($docs as $doc) {
            $fullPath = $storageBase . $doc['filepath'];
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, 'files/' . basename($doc['filepath']));
            }
        }
    } else {
        // Global: all tables except excluded
        $allTables = Db::fetchAll("SHOW TABLES");
        $dbName = Db::getOne("SELECT DATABASE()");
        $colKey = 'Tables_in_' . $dbName;

        foreach ($allTables as $t) {
            $tableName = $t[$colKey] ?? reset($t);
            if (in_array($tableName, GLOBAL_EXCLUDE_TABLES)) continue;

            $sql = dump_table_sql($tableName);
            if ($sql) {
                $zip->addFromString($tableName . '.sql', $sql);
                $tablesIncluded[] = $tableName;
                $rowCounts[$tableName] = (int) Db::getOne("SELECT COUNT(*) FROM `$tableName`");
            }
        }

        // Copy ALL uploaded files
        $storageDirs = ['documents', 'avatars', 'fiches_salaire', 'justificatifs', 'logos', 'marquage', 'mur', 'pv', 'wiki', 'candidatures', 'emails'];
        $storageBase = __DIR__ . '/../../storage/';
        foreach ($storageDirs as $subdir) {
            $path = $storageBase . $subdir;
            if (!is_dir($path)) continue;
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $relPath = 'files/' . $subdir . '/' . $iter->getSubPathName();
                    $zip->addFile($file->getPathname(), $relPath);
                }
            }
        }
    }

    // Add manifest with schema snapshot for compatibility
    $manifest = [
        'id' => $id,
        'type' => $type,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $createdBy,
        'app_version' => defined('APP_VERSION') ? APP_VERSION : 'unknown',
        'schema_version' => get_current_schema_version(),
        'tables' => $tablesIncluded,
        'row_counts' => $rowCounts,
        'schema_snapshot' => snapshot_schema($tablesIncluded),
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $zip->close();

    // Compute checksum
    $checksum = hash_file('sha256', $zipPath);
    $fileSize = filesize($zipPath);

    // Add checksum file
    $zip2 = new ZipArchive();
    if ($zip2->open($zipPath) === true) {
        $zip2->addFromString('checksum.sha256', $checksum . '  ' . $filename);
        $zip2->close();
    }

    // Register in DB
    Db::exec(
        "INSERT INTO backups (id, user_id, type, filename, file_size, tables_included, row_counts, checksum_sha256, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $userId, $type, $filename, $fileSize, json_encode($tablesIncluded), json_encode($rowCounts), $checksum, $createdBy]
    );

    return [
        'id' => $id,
        'filename' => $filename,
        'file_size' => $fileSize,
        'tables_included' => $tablesIncluded,
        'row_counts' => $rowCounts,
        'checksum' => $checksum,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * Enforce per-user backup limit (delete oldest if over max)
 */
function enforce_user_backup_limit(string $userId): void
{
    $max = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'backup_max_per_user'") ?: 5);
    $backups = Db::fetchAll(
        "SELECT id, filename FROM backups WHERE user_id = ? AND type = 'user' ORDER BY created_at DESC",
        [$userId]
    );

    if (count($backups) > $max) {
        $toDelete = array_slice($backups, $max);
        foreach ($toDelete as $b) {
            $path = backup_dir_user($userId) . '/' . $b['filename'];
            if (file_exists($path)) unlink($path);
            Db::exec("DELETE FROM backups WHERE id = ?", [$b['id']]);
        }
    }
}

/**
 * Read manifest from a backup ZIP
 */
function read_backup_manifest(string $zipPath): ?array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return null;
    $json = $zip->getFromName('manifest.json');
    $zip->close();
    return $json ? json_decode($json, true) : null;
}

/**
 * Extract SQL data from a backup ZIP for a specific table
 */
function extract_backup_table_data(string $zipPath, string $table): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return [];

    $sql = $zip->getFromName($table . '.sql');
    $zip->close();
    if (!$sql) return [];

    // Parse INSERT statements to get row data
    // We'll re-query the current state instead for comparison
    return ['raw_sql' => $sql, 'has_data' => true];
}

/* ─────────────── API Actions ─────────────── */

/**
 * Create a per-user backup
 */
function admin_create_backup()
{
    require_admin();
    $userId = $_SESSION['ss_user']['id'];

    $result = create_backup_zip('user', $userId, $userId);
    enforce_user_backup_limit($userId);

    respond(['success' => true, 'backup' => $result]);
}

/**
 * List backups (per-user or global)
 */
function admin_list_backups()
{
    require_admin();
    global $params;

    $type = $params['type'] ?? 'user';

    if ($type === 'user') {
        $userId = $_SESSION['ss_user']['id'];
        $backups = Db::fetchAll(
            "SELECT b.*, u.prenom, u.nom FROM backups b
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.user_id = ? AND b.type = 'user'
             ORDER BY b.created_at DESC",
            [$userId]
        );
    } else {
        $backups = Db::fetchAll(
            "SELECT b.*, u.prenom, u.nom FROM backups b
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.type = 'global'
             ORDER BY b.created_at DESC"
        );
    }

    // Parse JSON fields
    foreach ($backups as &$b) {
        $b['tables_included'] = json_decode($b['tables_included'], true) ?: [];
        $b['row_counts'] = json_decode($b['row_counts'], true) ?: [];
        $b['file_size_human'] = format_file_size($b['file_size']);
    }

    respond(['success' => true, 'backups' => $backups]);
}

function format_file_size(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' Go';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' Ko';
    return $bytes . ' o';
}

/**
 * Compare a backup with current state
 */
function admin_compare_backup()
{
    require_admin();
    global $params;

    $backupId = $params['backup_id'] ?? '';
    if (!$backupId) bad_request('backup_id requis');

    $backup = Db::fetch("SELECT * FROM backups WHERE id = ?", [$backupId]);
    if (!$backup) not_found('Sauvegarde introuvable');

    // Security: user can only compare own backups
    if ($backup['type'] === 'user' && $backup['user_id'] !== $_SESSION['ss_user']['id']) {
        forbidden();
    }

    $dir = $backup['type'] === 'user'
        ? backup_dir_user($backup['user_id'])
        : backup_dir_global();
    $zipPath = $dir . '/' . $backup['filename'];

    if (!file_exists($zipPath)) {
        bad_request('Fichier de sauvegarde introuvable sur le disque');
    }

    // Verify checksum
    $currentChecksum = hash_file('sha256', $zipPath);
    if ($currentChecksum !== $backup['checksum_sha256']) {
        bad_request('Intégrité compromise : le hash SHA-256 ne correspond pas');
    }

    $manifest = read_backup_manifest($zipPath);
    if (!$manifest) bad_request('Manifest illisible');

    $diff = [];
    $tables = json_decode($backup['tables_included'], true) ?: [];
    $backupCounts = json_decode($backup['row_counts'], true) ?: [];

    foreach ($tables as $table) {
        // Current count
        if ($backup['type'] === 'user') {
            $userId = $backup['user_id'];
            switch ($table) {
                case 'documents':
                    $currentCount = (int) Db::getOne("SELECT COUNT(*) FROM documents WHERE uploaded_by = ?", [$userId]);
                    break;
                case 'messages':
                    $currentCount = (int) Db::getOne(
                        "SELECT COUNT(*) FROM messages WHERE from_user_id = ? OR id IN (SELECT email_id FROM message_recipients WHERE user_id = ?)",
                        [$userId, $userId]
                    );
                    break;
                case 'email_externe_contacts':
                    $currentCount = (int) Db::getOne("SELECT COUNT(*) FROM email_externe_contacts WHERE created_by = ?", [$userId]);
                    break;
                default:
                    $currentCount = (int) Db::getOne("SELECT COUNT(*) FROM `$table`");
            }
        } else {
            $currentCount = (int) Db::getOne("SELECT COUNT(*) FROM `$table`");
        }

        $backupCount = $backupCounts[$table] ?? 0;
        $delta = $backupCount - $currentCount;

        $diff[$table] = [
            'backup_count' => $backupCount,
            'current_count' => $currentCount,
            'delta' => $delta,
            'status' => $delta > 0 ? 'added' : ($delta < 0 ? 'removed' : 'same'),
        ];
    }

    // Check compatibility
    $compatibility = check_backup_compatibility($manifest);

    respond([
        'success' => true,
        'backup_id' => $backupId,
        'backup_date' => $backup['created_at'],
        'diff' => $diff,
        'compatibility' => $compatibility,
    ]);
}

/**
 * Restore a per-user backup (merge or overwrite)
 */
function admin_restore_backup()
{
    require_admin();
    global $params;

    $backupId = $params['backup_id'] ?? '';
    $mode = $params['mode'] ?? 'merge'; // merge | overwrite
    if (!$backupId) bad_request('backup_id requis');
    if (!in_array($mode, ['merge', 'overwrite'])) bad_request('Mode invalide');

    $backup = Db::fetch("SELECT * FROM backups WHERE id = ?", [$backupId]);
    if (!$backup) not_found('Sauvegarde introuvable');

    if ($backup['type'] !== 'user') {
        bad_request('Utilisez admin_restore_global_backup pour les sauvegardes globales');
    }

    // Security: user can only restore own backups
    if ($backup['user_id'] !== $_SESSION['ss_user']['id']) {
        forbidden();
    }

    $dir = backup_dir_user($backup['user_id']);
    $zipPath = $dir . '/' . $backup['filename'];
    if (!file_exists($zipPath)) bad_request('Fichier introuvable');

    // Verify checksum
    $currentChecksum = hash_file('sha256', $zipPath);
    if ($currentChecksum !== $backup['checksum_sha256']) {
        bad_request('Intégrité compromise');
    }

    // Check compatibility
    $manifest = read_backup_manifest($zipPath);
    $compat = check_backup_compatibility($manifest ?: []);
    if (!$compat['compatible']) {
        bad_request(implode(' ', $compat['errors']));
    }

    // Auto-backup current state before restore
    $autoBackup = create_backup_zip('user', $backup['user_id'], $_SESSION['ss_user']['id']);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) bad_request('Impossible d\'ouvrir l\'archive');

    $pdo = Db::connect();
    $pdo->beginTransaction();

    try {
        $tables = json_decode($backup['tables_included'], true) ?: [];

        if ($mode === 'overwrite') {
            // Delete current user data first
            $userId = $backup['user_id'];
            foreach (array_reverse($tables) as $table) {
                switch ($table) {
                    case 'documents':
                        Db::exec("DELETE FROM document_access WHERE document_id IN (SELECT id FROM documents WHERE uploaded_by = ?)", [$userId]);
                        Db::exec("DELETE FROM document_versions WHERE document_id IN (SELECT id FROM documents WHERE uploaded_by = ?)", [$userId]);
                        Db::exec("DELETE FROM documents WHERE uploaded_by = ?", [$userId]);
                        break;
                    case 'messages':
                        Db::exec("DELETE FROM message_attachments WHERE email_id IN (SELECT id FROM messages WHERE from_user_id = ?)", [$userId]);
                        Db::exec("DELETE FROM message_recipients WHERE user_id = ? OR email_id IN (SELECT id FROM messages WHERE from_user_id = ?)", [$userId, $userId]);
                        Db::exec("DELETE FROM messages WHERE from_user_id = ?", [$userId]);
                        break;
                    case 'email_externe_contacts':
                        Db::exec("DELETE FROM email_externe_contacts WHERE created_by = ?", [$userId]);
                        break;
                    case 'document_versions':
                    case 'document_access':
                    case 'message_recipients':
                    case 'message_attachments':
                        // Already handled above
                        break;
                }
            }
        }

        // Import SQL from backup (with schema adaptation)
        foreach ($tables as $table) {
            $sqlContent = $zip->getFromName($table . '.sql');
            if (!$sqlContent) continue;

            // Extract pure INSERT statements
            $lines = explode("\n", $sqlContent);
            $insertSql = '';
            foreach ($lines as $line) {
                if (strpos($line, '--') === 0) continue;
                $insertSql .= $line . "\n";
            }
            $insertSql = trim($insertSql);
            if (!$insertSql) continue;

            // Adapt SQL if schema differs (remove old columns, etc.)
            if (!$compat['version_match']) {
                $insertSql = adapt_sql_to_current_schema($insertSql, $table);
                if (!$insertSql) continue; // table removed
            }

            if ($mode === 'merge') {
                $insertSql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $insertSql);
            }

            $pdo->exec($insertSql);
        }

        // Restore files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'files/') === 0 && $name !== 'files/') {
                $destPath = __DIR__ . '/../../storage/' . substr($name, 6);
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                file_put_contents($destPath, $zip->getFromIndex($i));
            }
        }

        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        $zip->close();
        bad_request('Erreur lors de la restauration : ' . $e->getMessage());
    }

    $zip->close();

    respond([
        'success' => true,
        'mode' => $mode,
        'auto_backup_id' => $autoBackup['id'],
        'adapted' => !$compat['version_match'],
        'warnings' => $compat['warnings'],
        'message' => $mode === 'merge'
            ? 'Restauration partielle terminée (éléments manquants ajoutés)'
            : 'Restauration complète terminée (données écrasées)',
    ]);
}

/**
 * Delete a backup
 */
function admin_delete_backup()
{
    require_admin();
    global $params;

    $backupId = $params['backup_id'] ?? '';
    if (!$backupId) bad_request('backup_id requis');

    $backup = Db::fetch("SELECT * FROM backups WHERE id = ?", [$backupId]);
    if (!$backup) not_found('Sauvegarde introuvable');

    // Security: user can only delete own user backups
    if ($backup['type'] === 'user' && $backup['user_id'] !== $_SESSION['ss_user']['id']) {
        forbidden();
    }

    $dir = $backup['type'] === 'user'
        ? backup_dir_user($backup['user_id'])
        : backup_dir_global();
    $zipPath = $dir . '/' . $backup['filename'];

    if (file_exists($zipPath)) unlink($zipPath);
    Db::exec("DELETE FROM backups WHERE id = ?", [$backupId]);

    respond(['success' => true]);
}

/**
 * Create a global backup (manual trigger)
 */
function admin_create_global_backup()
{
    require_admin();

    $result = create_backup_zip('global', null, $_SESSION['ss_user']['id']);

    // Enforce global retention
    enforce_global_retention();

    respond(['success' => true, 'backup' => $result]);
}

function enforce_global_retention(): void
{
    $maxDays = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'backup_global_retention_days'") ?: 14);
    $cutoff = date('Y-m-d H:i:s', strtotime("-$maxDays days"));

    $old = Db::fetchAll(
        "SELECT id, filename FROM backups WHERE type = 'global' AND created_at < ? ORDER BY created_at ASC",
        [$cutoff]
    );

    foreach ($old as $b) {
        $path = backup_dir_global() . '/' . $b['filename'];
        if (file_exists($path)) unlink($path);
        Db::exec("DELETE FROM backups WHERE id = ?", [$b['id']]);
    }
}

/**
 * Restore a global backup (requires access code)
 */
function admin_restore_global_backup()
{
    require_admin();
    global $params;

    $backupId = $params['backup_id'] ?? '';
    $accessCode = $params['access_code'] ?? '';
    $mode = $params['mode'] ?? 'overwrite';
    if (!$backupId) bad_request('backup_id requis');
    if (!$accessCode) bad_request('Code d\'accès requis');

    // Rate limit check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $attempts = (int) Db::getOne(
        "SELECT COUNT(*) FROM rate_limits WHERE action_key = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        ['global_restore', $ip]
    );
    if ($attempts >= 3) {
        bad_request('Trop de tentatives. Réessayez dans 1 heure.');
    }

    // Log attempt
    Db::exec(
        "INSERT INTO rate_limits (id, action_key, ip_address, created_at) VALUES (?, ?, ?, NOW())",
        [Uuid::v4(), 'global_restore', $ip]
    );

    // Verify access code
    $storedHash = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'backup_global_access_code'");
    if (!$storedHash || !password_verify($accessCode, $storedHash)) {
        bad_request('Code d\'accès incorrect');
    }

    $backup = Db::fetch("SELECT * FROM backups WHERE id = ? AND type = 'global'", [$backupId]);
    if (!$backup) not_found('Sauvegarde globale introuvable');

    $zipPath = backup_dir_global() . '/' . $backup['filename'];
    if (!file_exists($zipPath)) bad_request('Fichier introuvable');

    // Verify checksum
    if (hash_file('sha256', $zipPath) !== $backup['checksum_sha256']) {
        bad_request('Intégrité compromise');
    }

    // Check compatibility
    $manifest = read_backup_manifest($zipPath);
    $compat = check_backup_compatibility($manifest ?: []);
    if (!$compat['compatible']) {
        bad_request(implode(' ', $compat['errors']));
    }

    // Auto-backup before restore
    $autoBackup = create_backup_zip('global', null, $_SESSION['ss_user']['id']);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) bad_request('Impossible d\'ouvrir l\'archive');

    $pdo = Db::connect();
    $pdo->beginTransaction();

    try {
        $tables = json_decode($backup['tables_included'], true) ?: [];

        if ($mode === 'overwrite') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                // Check table exists before truncate
                $tableExists = Db::getOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
                if ($tableExists) {
                    $pdo->exec("TRUNCATE TABLE `$table`");
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        foreach ($tables as $table) {
            $sqlContent = $zip->getFromName($table . '.sql');
            if (!$sqlContent) continue;

            $lines = explode("\n", $sqlContent);
            $insertSql = '';
            foreach ($lines as $line) {
                if (strpos($line, '--') === 0) continue;
                $insertSql .= $line . "\n";
            }
            $insertSql = trim($insertSql);
            if (!$insertSql) continue;

            // Adapt SQL if schema differs
            if (!$compat['version_match']) {
                $insertSql = adapt_sql_to_current_schema($insertSql, $table);
                if (!$insertSql) continue;
            }

            if ($mode === 'merge') {
                $insertSql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $insertSql);
            }

            $pdo->exec($insertSql);
        }

        // Restore files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'files/') === 0 && $name !== 'files/') {
                $destPath = __DIR__ . '/../../storage/' . substr($name, 6);
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                file_put_contents($destPath, $zip->getFromIndex($i));
            }
        }

        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        $zip->close();
        bad_request('Erreur restauration globale : ' . $e->getMessage());
    }

    $zip->close();

    respond([
        'success' => true,
        'mode' => $mode,
        'auto_backup_id' => $autoBackup['id'],
        'adapted' => !$compat['version_match'],
        'warnings' => $compat['warnings'],
        'message' => 'Restauration globale terminée',
    ]);
}

/**
 * Set or update global access code
 */
function admin_set_backup_access_code()
{
    require_admin();
    global $params;

    $code = $params['code'] ?? '';
    if (strlen($code) < 6) bad_request('Le code doit faire au moins 6 caractères');

    $hash = password_hash($code, PASSWORD_DEFAULT);
    Db::exec(
        "UPDATE ems_config SET config_value = ? WHERE config_key = 'backup_global_access_code'",
        [$hash]
    );

    respond(['success' => true, 'message' => 'Code d\'accès mis à jour']);
}

/**
 * Check if global access code is configured
 */
function admin_check_backup_access_code()
{
    require_admin();
    $code = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'backup_global_access_code'");
    respond(['success' => true, 'configured' => !empty($code)]);
}

/**
 * Download a backup file
 */
function admin_download_backup()
{
    require_admin();
    global $params;

    $backupId = $params['backup_id'] ?? '';
    if (!$backupId) bad_request('backup_id requis');

    $backup = Db::fetch("SELECT * FROM backups WHERE id = ?", [$backupId]);
    if (!$backup) not_found();

    if ($backup['type'] === 'user' && $backup['user_id'] !== $_SESSION['ss_user']['id']) {
        forbidden();
    }

    $dir = $backup['type'] === 'user'
        ? backup_dir_user($backup['user_id'])
        : backup_dir_global();
    $zipPath = $dir . '/' . $backup['filename'];

    if (!file_exists($zipPath)) not_found('Fichier introuvable');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
}
