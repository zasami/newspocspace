-- Module Formations côté SPA employé
-- 1) Adresse personnelle des collaborateurs (pour calcul itinéraires)
-- 2) Table formation_souhaits : exprimer envie de participer au catalogue

ALTER TABLE users
  ADD COLUMN adresse_rue        VARCHAR(255) NULL AFTER theme_preference,
  ADD COLUMN adresse_complement VARCHAR(255) NULL AFTER adresse_rue,
  ADD COLUMN adresse_cp         VARCHAR(20)  NULL AFTER adresse_complement,
  ADD COLUMN adresse_ville      VARCHAR(120) NULL AFTER adresse_cp;

CREATE TABLE formation_souhaits (
  id              CHAR(36)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
  user_id         CHAR(36)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  formation_id    CHAR(36)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  message         TEXT         NULL,
  statut          ENUM('en_attente','accepte','refuse','annule') NOT NULL DEFAULT 'en_attente',
  match_fonction  TINYINT(1)   NOT NULL DEFAULT 0,
  reviewed_by     CHAR(36)     NULL,
  reviewed_at     DATETIME     NULL,
  reviewer_note   TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fs_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_form (user_id, formation_id),
  KEY idx_fs_statut (statut),
  KEY idx_fs_form (formation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
