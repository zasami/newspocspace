-- Migration 030: PV validation workflow
ALTER TABLE pv
  ADD COLUMN validation_required TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived,
  ADD COLUMN validation_role VARCHAR(20) DEFAULT NULL COMMENT 'responsable|admin|direction' AFTER validation_required,
  ADD COLUMN validated_by CHAR(36) DEFAULT NULL AFTER validation_role,
  ADD COLUMN validated_at DATETIME DEFAULT NULL AFTER validated_by;

-- Extend statut enum to include 'en_validation'
ALTER TABLE pv MODIFY COLUMN statut ENUM('brouillon','enregistrement','en_validation','finalisé') DEFAULT 'brouillon';
