-- Photo E2EE pour les résidents
ALTER TABLE `residents`
  ADD COLUMN `photo_path` VARCHAR(500) DEFAULT NULL AFTER `menu_special`,
  ADD COLUMN `photo_iv` VARCHAR(64) DEFAULT NULL AFTER `photo_path`;
