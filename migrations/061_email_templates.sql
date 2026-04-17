-- Email Templates CMS : templates personnalisables pour emails automatiques

CREATE TABLE IF NOT EXISTS email_templates (
    id CHAR(36) PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(500),
    subject VARCHAR(255) NOT NULL,
    header_color VARCHAR(10) DEFAULT '#2d4a43',
    header_text_color VARCHAR(10) DEFAULT '#ffffff',
    show_logo TINYINT(1) DEFAULT 1,
    header_title VARCHAR(255),
    header_subtitle VARCHAR(255),
    blocks JSON,
    footer_text TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by CHAR(36) DEFAULT NULL,
    INDEX idx_et_key (template_key),
    INDEX idx_et_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
