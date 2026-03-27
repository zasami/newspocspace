-- Add correspondent/family access fields to residents
ALTER TABLE `residents`
  ADD COLUMN `date_naissance` DATE DEFAULT NULL AFTER `etage`,
  ADD COLUMN `correspondant_nom` VARCHAR(100) DEFAULT NULL AFTER `date_naissance`,
  ADD COLUMN `correspondant_prenom` VARCHAR(100) DEFAULT NULL AFTER `correspondant_nom`,
  ADD COLUMN `correspondant_email` VARCHAR(200) DEFAULT NULL AFTER `correspondant_prenom`,
  ADD COLUMN `correspondant_telephone` VARCHAR(30) DEFAULT NULL AFTER `correspondant_email`,
  ADD COLUMN `code_acces` VARCHAR(50) DEFAULT NULL AFTER `correspondant_telephone`,
  ADD KEY `idx_residents_code` (`code_acces`),
  ADD KEY `idx_residents_email` (`correspondant_email`);
