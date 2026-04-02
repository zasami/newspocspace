<?php
/**
 * System migration script - Run database migrations
 */
require_once __DIR__ . '/init.php';

echo "=== SpocSpace Database Migrations ===\n\n";

try {
    $pdo = Db::connect();
    
    // Check if PV table exists
    $result = Db::getOne("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pv'", [DB_NAME]);
    
    if ($result === 0) {
        echo "Creating PV table...\n";
        
        // Use raw SQL via connection
        $sql = "
        CREATE TABLE IF NOT EXISTS `pv` (
          `id` CHAR(36) NOT NULL,
          `titre` VARCHAR(255) NOT NULL,
          `description` TEXT,
          `created_by` CHAR(36) NOT NULL,
          `module_id` CHAR(36) DEFAULT NULL,
          `etage_id` CHAR(36) DEFAULT NULL,
          `fonction_filter_id` CHAR(36) DEFAULT NULL COMMENT 'Fonction concernée',
          `contenu` LONGTEXT DEFAULT NULL COMMENT 'Texte transcrit',
          `audio_path` VARCHAR(255) DEFAULT NULL COMMENT 'Chemin fichier audio',
          `participants` JSON DEFAULT NULL COMMENT 'Liste des participants {id, prenom, nom, fonction}',
          `tags` JSON DEFAULT NULL COMMENT 'Tags de classification',
          `statut` ENUM('brouillon','enregistrement','finalisé') DEFAULT 'brouillon',
          `is_public` TINYINT(1) DEFAULT 1 COMMENT 'Visible par tous les collaborateurs',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_pv_created_by` (`created_by`),
          KEY `idx_pv_module` (`module_id`),
          KEY `idx_pv_etage` (`etage_id`),
          KEY `idx_pv_fonction` (`fonction_filter_id`),
          KEY `idx_pv_statut` (`statut`),
          KEY `idx_pv_created_at` (`created_at`),
          CONSTRAINT `fk_pv_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_pv_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_pv_etage` FOREIGN KEY (`etage_id`) REFERENCES `etages`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_pv_fonction` FOREIGN KEY (`fonction_filter_id`) REFERENCES `fonctions`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        echo "✓ PV table created successfully\n";
    } else {
        echo "ℹ PV table already exists\n";
    }

    // Create storage directory for PV audio files
    $storagePath = __DIR__ . '/storage/pv';
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
        echo "✓ Storage directory created: $storagePath\n";
    }

    echo "\n✓ All migrations completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
