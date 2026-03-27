-- Migration 029: Add archive + transcription_brute to PV table
ALTER TABLE pv ADD COLUMN transcription_brute LONGTEXT DEFAULT NULL AFTER contenu;
ALTER TABLE pv ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
ALTER TABLE pv ADD KEY idx_pv_archived (is_archived);
