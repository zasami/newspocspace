-- Wiki / Annonces Phase 3 : Analytics, Knowledge gaps, Read receipts, Workflow

-- ─────────────────────────────────────────────────────────
-- 1. ANALYTICS — page views (wiki + annonces)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wiki_page_views (
    id CHAR(36) PRIMARY KEY,
    page_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wpv_page (page_id),
    INDEX idx_wpv_user (user_id),
    INDEX idx_wpv_date (viewed_at),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annonce_views (
    id CHAR(36) PRIMARY KEY,
    annonce_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_av_ann (annonce_id),
    INDEX idx_av_user (user_id),
    INDEX idx_av_date (viewed_at),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 2. KNOWLEDGE GAPS — search log (toutes recherches wiki)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wiki_search_log (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    q VARCHAR(255) NOT NULL,
    results_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wsl_q (q),
    INDEX idx_wsl_zero (results_count, created_at),
    INDEX idx_wsl_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 3. READ RECEIPTS — accusés de lecture sur annonces critiques
-- ─────────────────────────────────────────────────────────
ALTER TABLE annonces
    ADD COLUMN requires_ack TINYINT(1) DEFAULT 0 AFTER epingle,
    ADD COLUMN ack_target_role VARCHAR(50) DEFAULT NULL AFTER requires_ack;

CREATE TABLE IF NOT EXISTS annonce_acks (
    annonce_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    acked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (annonce_id, user_id),
    INDEX idx_ack_user (user_id),
    FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 4. WORKFLOW — brouillon → review → publié pour wiki
-- ─────────────────────────────────────────────────────────
ALTER TABLE wiki_pages
    ADD COLUMN status ENUM('brouillon','review','publie') NOT NULL DEFAULT 'publie' AFTER visible,
    ADD COLUMN review_requested_at DATETIME DEFAULT NULL AFTER status,
    ADD COLUMN review_requested_by CHAR(36) DEFAULT NULL AFTER review_requested_at,
    ADD INDEX idx_wp_status (status);

CREATE TABLE IF NOT EXISTS wiki_page_reviews (
    id CHAR(36) PRIMARY KEY,
    page_id CHAR(36) NOT NULL,
    reviewer_id CHAR(36) NOT NULL,
    decision ENUM('approved','changes_requested','commented') NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wpr_page (page_id),
    INDEX idx_wpr_reviewer (reviewer_id),
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
