-- Add justificatif file path to absences table
ALTER TABLE absences ADD COLUMN justificatif_path VARCHAR(255) DEFAULT NULL AFTER justifie;
ALTER TABLE absences ADD COLUMN justificatif_name VARCHAR(255) DEFAULT NULL AFTER justificatif_path;
