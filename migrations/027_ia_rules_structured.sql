-- 027: Structured rules for planning generation
-- Adds structured constraint types + user targeting to ia_human_rules

ALTER TABLE ia_human_rules
  ADD COLUMN rule_type VARCHAR(30) DEFAULT NULL
    COMMENT 'shift_only, shift_exclude, module_only, module_exclude, no_weekend, max_days_week, NULL=legacy free-text',
  ADD COLUMN rule_params JSON DEFAULT NULL
    COMMENT 'Type-specific params: {"shift_codes":[]}, {"module_ids":[]}, {"max_days":N}',
  ADD COLUMN target_mode VARCHAR(20) NOT NULL DEFAULT 'all'
    COMMENT 'all=everyone, users=specific users, fonction=by fonction code',
  ADD COLUMN target_fonction_code VARCHAR(10) DEFAULT NULL
    COMMENT 'Fonction code when target_mode=fonction (AS, INF, ASSC, etc.)';

CREATE TABLE IF NOT EXISTS ia_rule_users (
    rule_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    PRIMARY KEY (rule_id, user_id),
    INDEX idx_rule_users_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
