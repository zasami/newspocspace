-- Add employee type (interne/externe) and planning override flags
ALTER TABLE `users`
  ADD COLUMN `type_employe` ENUM('interne','externe') NOT NULL DEFAULT 'interne' AFTER `role`,
  ADD COLUMN `include_planning` TINYINT(1) DEFAULT NULL AFTER `type_employe`,
  ADD COLUMN `include_vacances` TINYINT(1) DEFAULT NULL AFTER `include_planning`,
  ADD COLUMN `include_desirs` TINYINT(1) DEFAULT NULL AFTER `include_vacances`;

-- NULL = follow type default (interne=included, externe=excluded)
-- 1 = force include, 0 = force exclude

-- Set existing cuisine users as externe
UPDATE `users` u
  INNER JOIN `fonctions` f ON f.id = u.fonction_id
  SET u.type_employe = 'externe'
  WHERE f.code IN ('CHEF', 'CUIS', 'HOT');
