-- Desirs permanents: un employé peut configurer jusqu'à 4 désirs récurrents
-- Ex: "tous les mercredis = horaire A3" → auto-validé chaque mois
CREATE TABLE IF NOT EXISTS `desirs_permanents` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `jour_semaine` TINYINT NOT NULL COMMENT '0=dim, 1=lun, 2=mar, 3=mer, 4=jeu, 5=ven, 6=sam',
  `type` ENUM('jour_off','horaire_special') NOT NULL DEFAULT 'horaire_special',
  `horaire_type_id` CHAR(36) DEFAULT NULL,
  `detail` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dp_user` (`user_id`),
  CONSTRAINT `fk_dp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dp_horaire` FOREIGN KEY (`horaire_type_id`) REFERENCES `horaires_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien dans desirs vers le désir permanent source
ALTER TABLE `desirs` ADD COLUMN `permanent_id` CHAR(36) DEFAULT NULL AFTER `horaire_type_id`;
ALTER TABLE `desirs` ADD KEY `idx_desirs_permanent` (`permanent_id`);
