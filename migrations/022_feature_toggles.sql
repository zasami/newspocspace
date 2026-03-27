-- Feature toggles: default all enabled
INSERT IGNORE INTO ems_config (config_key, config_value) VALUES
('feature_desirs', '1'),
('feature_multi_modules', '1'),
('feature_civilistes', '0'),
('feature_absences', '1'),
('feature_changements', '1'),
('feature_sondages', '1'),
('feature_pv', '1'),
('feature_emails', '1'),
('feature_documents', '1'),
('feature_votes', '1'),
('feature_covoiturage', '0'),
('feature_fiches_salaire', '0');
