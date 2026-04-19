-- Migration 080 : Auth par mot de passe pour les dossiers d'admission
-- Remplace le magic link par un système email + mot de passe
-- Prépare l'accès futur à l'espace famille (après acceptation)

ALTER TABLE admissions_candidats
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER token_acces,
  ADD COLUMN password_reset_token CHAR(36) NULL AFTER password_hash,
  ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token,
  ADD COLUMN last_login_at DATETIME NULL AFTER password_reset_expires;

-- Un email = un dossier (évite doublons)
-- On se base sur ref_email qui est obligatoire
CREATE INDEX idx_adm_ref_email_unique ON admissions_candidats(ref_email);
