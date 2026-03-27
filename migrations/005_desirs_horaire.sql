-- Add horaire_type_id to desirs for linking horaire spécial requests
ALTER TABLE `desirs` ADD COLUMN `horaire_type_id` CHAR(36) DEFAULT NULL AFTER `detail`;
ALTER TABLE `desirs` ADD CONSTRAINT `fk_desirs_horaire` FOREIGN KEY (`horaire_type_id`) REFERENCES `horaires_types`(`id`) ON DELETE SET NULL;
