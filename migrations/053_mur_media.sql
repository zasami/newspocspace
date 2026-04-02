-- =============================================================================
-- 053 — Mur social : media (images/vidéos) + hero config
-- =============================================================================

-- ── Media attachments for posts ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mur_media (
    id          CHAR(36) PRIMARY KEY,
    post_id     CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    type        ENUM('image','video') NOT NULL DEFAULT 'image',
    filename    VARCHAR(255) NOT NULL,
    url         VARCHAR(500) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES mur_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mur_media_post (post_id),
    INDEX idx_mur_media_user (user_id, type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hero config keys ─────────────────────────────────────────────────────────
INSERT INTO mur_config (config_key, config_value) VALUES
    ('hero_image', ''),
    ('hero_title', 'Mur social'),
    ('hero_subtitle', 'Votre réseau interne — partagez, échangez et restez connectés avec vos collègues')
ON DUPLICATE KEY UPDATE config_key = config_key;
