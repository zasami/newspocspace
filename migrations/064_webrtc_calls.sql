-- WebRTC calls — signaling & history
CREATE TABLE IF NOT EXISTS calls (
    id CHAR(36) PRIMARY KEY,
    from_user_id CHAR(36) NOT NULL,
    to_user_id CHAR(36) NOT NULL,
    status ENUM('ringing', 'accepted', 'rejected', 'ended', 'missed') NOT NULL DEFAULT 'ringing',
    media ENUM('audio', 'video') NOT NULL DEFAULT 'audio',
    sdp_offer LONGTEXT DEFAULT NULL,
    sdp_answer LONGTEXT DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    answered_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL,
    duration_sec INT DEFAULT NULL,
    INDEX idx_to_status (to_user_id, status, started_at),
    INDEX idx_from_status (from_user_id, status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ICE candidates exchanged during negotiation
CREATE TABLE IF NOT EXISTS call_ice (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    call_id CHAR(36) NOT NULL,
    from_user_id CHAR(36) NOT NULL,
    candidate LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_call_consumed (call_id, consumed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
