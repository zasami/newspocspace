-- Préférence de thème par utilisateur
-- Valeurs : 'default' (actuel), 'sombre' (dark), 'care' (nouveau DS Spoc Care)

ALTER TABLE users
  ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'default'
  AFTER cct;

-- Index pour stats rapides
CREATE INDEX idx_users_theme ON users(theme_preference);
