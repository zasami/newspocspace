-- Add etage_id directly to planning_assignations
-- This allows showing the assigned étage on the repartition view
-- even when groupes are not configured (groupes are optional sub-divisions of an étage)

ALTER TABLE `planning_assignations`
  ADD COLUMN `etage_id` CHAR(36) DEFAULT NULL AFTER `groupe_id`,
  ADD CONSTRAINT `fk_pa_etage` FOREIGN KEY (`etage_id`) REFERENCES `etages`(`id`) ON DELETE SET NULL;

CREATE INDEX `idx_pa_etage` ON `planning_assignations` (`etage_id`);
