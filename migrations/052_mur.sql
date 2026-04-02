-- =============================================================================
-- 052 — Mur social (wall) : posts, commentaires, likes, config
-- =============================================================================

-- ── Configuration du mur ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mur_config (
    config_key   VARCHAR(50) PRIMARY KEY,
    config_value TEXT NOT NULL,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mur_config (config_key, config_value) VALUES
    ('moderation_enabled',    '0'),        -- 0 = publication directe, 1 = validation admin requise
    ('allow_anonymous_comments', '0'),     -- 0 = commentaires identifiés, 1 = anonyme autorisé
    ('allow_private_posts',   '0'),        -- 0 = posts pro uniquement, 1 = posts perso tolérés
    ('allow_comments',        '1'),        -- 0 = commentaires désactivés
    ('allow_likes',           '1'),        -- 0 = likes désactivés
    ('max_posts_per_day',     '10'),       -- limite par user par jour
    ('post_categories',       'general,info,evenement,social') -- catégories disponibles
ON DUPLICATE KEY UPDATE config_key = config_key;

-- ── Posts ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mur_posts (
    id          CHAR(36) PRIMARY KEY,
    user_id     CHAR(36) NOT NULL,
    body        TEXT NOT NULL,
    category    VARCHAR(30) NOT NULL DEFAULT 'general',
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    is_pinned   TINYINT(1) NOT NULL DEFAULT 0,
    likes_count INT UNSIGNED NOT NULL DEFAULT 0,
    comments_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mur_posts_status (status, deleted_at, created_at),
    INDEX idx_mur_posts_user (user_id, deleted_at),
    INDEX idx_mur_posts_pinned (is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Commentaires ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mur_comments (
    id          CHAR(36) PRIMARY KEY,
    post_id     CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    body        TEXT NOT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME DEFAULT NULL,
    FOREIGN KEY (post_id) REFERENCES mur_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mur_comments_post (post_id, deleted_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Likes ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mur_likes (
    id          CHAR(36) PRIMARY KEY,
    target_type ENUM('post','comment') NOT NULL DEFAULT 'post',
    target_id   CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_mur_like (target_type, target_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mur_likes_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
