-- ── Demandes de changement d'horaire entre collègues ──
-- Workflow : A demande → B confirme → Admin valide/refuse

CREATE TABLE IF NOT EXISTS `changements_horaire` (
  `id` CHAR(36) NOT NULL,
  `demandeur_id` CHAR(36) NOT NULL COMMENT 'Collègue A qui initie la demande',
  `destinataire_id` CHAR(36) NOT NULL COMMENT 'Collègue B à qui on propose l échange',
  `planning_id` CHAR(36) NOT NULL,
  `date_jour` DATE NOT NULL COMMENT 'Date concernée',
  `assignation_demandeur_id` CHAR(36) NOT NULL COMMENT 'Assignation actuelle de A (horaire X)',
  `assignation_destinataire_id` CHAR(36) NOT NULL COMMENT 'Assignation actuelle de B (horaire Y)',
  `motif` TEXT DEFAULT NULL COMMENT 'Motif de la demande (optionnel)',
  `statut` ENUM('en_attente_collegue','confirme_collegue','valide','refuse') DEFAULT 'en_attente_collegue',
  `refuse_par` ENUM('collegue','admin') DEFAULT NULL COMMENT 'Qui a refusé',
  `raison_refus` TEXT DEFAULT NULL COMMENT 'Raison du refus (optionnel)',
  `confirme_at` DATETIME DEFAULT NULL COMMENT 'Date confirmation par collègue B',
  `traite_par` CHAR(36) DEFAULT NULL COMMENT 'Admin qui a validé/refusé',
  `traite_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ch_demandeur` (`demandeur_id`),
  KEY `idx_ch_destinataire` (`destinataire_id`),
  KEY `idx_ch_statut` (`statut`),
  KEY `idx_ch_date` (`date_jour`),
  CONSTRAINT `fk_ch_demandeur` FOREIGN KEY (`demandeur_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ch_destinataire` FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ch_planning` FOREIGN KEY (`planning_id`) REFERENCES `plannings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ch_assign_dem` FOREIGN KEY (`assignation_demandeur_id`) REFERENCES `planning_assignations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ch_assign_dest` FOREIGN KEY (`assignation_destinataire_id`) REFERENCES `planning_assignations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ch_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
