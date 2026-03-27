-- Temporary password for admin-initiated resets
ALTER TABLE users
  ADD COLUMN password_temp_hash VARCHAR(255) DEFAULT NULL AFTER password,
  ADD COLUMN password_temp_expires DATETIME DEFAULT NULL AFTER password_temp_hash;
