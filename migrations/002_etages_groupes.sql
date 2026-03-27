-- Terrassière - Étages et groupes
-- Basé sur le planning réel Sem 12

SET NAMES utf8mb4;

-- Get module IDs for insertion
SET @m1 = (SELECT id FROM modules WHERE code = 'M1');
SET @m2 = (SELECT id FROM modules WHERE code = 'M2');
SET @m3 = (SELECT id FROM modules WHERE code = 'M3');
SET @m4 = (SELECT id FROM modules WHERE code = 'M4');

-- Étages
INSERT INTO etages (id, module_id, nom, code, ordre) VALUES
(UUID(), @m1, 'Étage 1', 'E1', 1),
(UUID(), @m1, 'Étage 2', 'E2', 2),
(UUID(), @m2, 'Étage 3', 'E3', 3),
(UUID(), @m2, 'Étage 4', 'E4', 4),
(UUID(), @m3, 'Étage 5', 'E5', 5),
(UUID(), @m3, 'Étage 6', 'E6', 6);

-- Groupes (2 par étage: a et b)
SET @e1 = (SELECT id FROM etages WHERE code = 'E1');
SET @e2 = (SELECT id FROM etages WHERE code = 'E2');
SET @e3 = (SELECT id FROM etages WHERE code = 'E3');
SET @e4 = (SELECT id FROM etages WHERE code = 'E4');
SET @e5 = (SELECT id FROM etages WHERE code = 'E5');
SET @e6 = (SELECT id FROM etages WHERE code = 'E6');

INSERT INTO groupes (id, etage_id, nom, code, ordre) VALUES
(UUID(), @e1, 'Groupe 1-A', '1-a', 1),
(UUID(), @e1, 'Groupe 1-B', '1-b', 2),
(UUID(), @e2, 'Groupe 2-A', '2-a', 3),
(UUID(), @e2, 'Groupe 2-B', '2-b', 4),
(UUID(), @e3, 'Groupe 3-A', '3-a', 5),
(UUID(), @e3, 'Groupe 3-B', '3-b', 6),
(UUID(), @e4, 'Groupe 4-A', '4-a', 7),
(UUID(), @e4, 'Groupe 4-B', '4-b', 8),
(UUID(), @e5, 'Groupe 5-A', '5-a', 9),
(UUID(), @e5, 'Groupe 5-B', '5-b', 10),
(UUID(), @e6, 'Groupe 6-A', '6-a', 11),
(UUID(), @e6, 'Groupe 6-B', '6-b', 12);
