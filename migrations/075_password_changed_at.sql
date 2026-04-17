-- Track when each user last changed their password (used to invalidate old sessions)
ALTER TABLE `users`
  ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `password`;

-- Initialize existing users with current timestamp so pre-existing sessions
-- remain valid after deployment.
UPDATE `users` SET `password_changed_at` = NOW() WHERE `password_changed_at` IS NULL;

INSERT INTO `schema_migrations` (`version`, `applied_at`) VALUES ('075', NOW())
  ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);
