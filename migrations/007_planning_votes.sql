-- Planning vote system: multiple planning proposals, employee votes, admin validation

CREATE TABLE IF NOT EXISTS planning_proposals (
    id CHAR(36) PRIMARY KEY,
    mois_annee VARCHAR(7) NOT NULL,
    label VARCHAR(100) NOT NULL DEFAULT 'Proposition',
    snapshot LONGTEXT NULL COMMENT 'JSON snapshot of assignations',
    statut ENUM('ouvert','ferme','valide','rejete') DEFAULT 'ouvert',
    votes_pour INT DEFAULT 0,
    votes_contre INT DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proposals_mois (mois_annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planning_votes (
    id CHAR(36) PRIMARY KEY,
    proposal_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    vote ENUM('pour','contre') NOT NULL,
    commentaire TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (proposal_id, user_id),
    FOREIGN KEY (proposal_id) REFERENCES planning_proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
