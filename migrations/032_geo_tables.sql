-- Pays
CREATE TABLE IF NOT EXISTS geo_pays (
    code VARCHAR(2) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    indicatif VARCHAR(5) DEFAULT NULL,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO geo_pays (code, nom, indicatif, sort_order) VALUES
('CH', 'Suisse', '+41', 1),
('FR', 'France', '+33', 2);

-- Cantons / Régions
CREATE TABLE IF NOT EXISTS geo_regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pays_code VARCHAR(2) NOT NULL,
    code VARCHAR(10) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (pays_code) REFERENCES geo_pays(code),
    UNIQUE KEY uk_pays_code (pays_code, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cantons suisses (26)
INSERT INTO geo_regions (pays_code, code, nom, sort_order) VALUES
('CH', 'AG', 'Argovie', 1),
('CH', 'AI', 'Appenzell Rhodes-Intérieures', 2),
('CH', 'AR', 'Appenzell Rhodes-Extérieures', 3),
('CH', 'BE', 'Berne', 4),
('CH', 'BL', 'Bâle-Campagne', 5),
('CH', 'BS', 'Bâle-Ville', 6),
('CH', 'FR', 'Fribourg', 7),
('CH', 'GE', 'Genève', 8),
('CH', 'GL', 'Glaris', 9),
('CH', 'GR', 'Grisons', 10),
('CH', 'JU', 'Jura', 11),
('CH', 'LU', 'Lucerne', 12),
('CH', 'NE', 'Neuchâtel', 13),
('CH', 'NW', 'Nidwald', 14),
('CH', 'OW', 'Obwald', 15),
('CH', 'SG', 'Saint-Gall', 16),
('CH', 'SH', 'Schaffhouse', 17),
('CH', 'SO', 'Soleure', 18),
('CH', 'SZ', 'Schwyz', 19),
('CH', 'TG', 'Thurgovie', 20),
('CH', 'TI', 'Tessin', 21),
('CH', 'UR', 'Uri', 22),
('CH', 'VD', 'Vaud', 23),
('CH', 'VS', 'Valais', 24),
('CH', 'ZG', 'Zoug', 25),
('CH', 'ZH', 'Zurich', 26);

-- Régions françaises (13 métropolitaines)
INSERT INTO geo_regions (pays_code, code, nom, sort_order) VALUES
('FR', 'ARA', 'Auvergne-Rhône-Alpes', 1),
('FR', 'BFC', 'Bourgogne-Franche-Comté', 2),
('FR', 'BRE', 'Bretagne', 3),
('FR', 'CVL', 'Centre-Val de Loire', 4),
('FR', 'COR', 'Corse', 5),
('FR', 'GES', 'Grand Est', 6),
('FR', 'HDF', 'Hauts-de-France', 7),
('FR', 'IDF', 'Île-de-France', 8),
('FR', 'NOR', 'Normandie', 9),
('FR', 'NAQ', 'Nouvelle-Aquitaine', 10),
('FR', 'OCC', 'Occitanie', 11),
('FR', 'PDL', 'Pays de la Loire', 12),
('FR', 'PAC', 'Provence-Alpes-Côte d''Azur', 13);

-- Villes principales
CREATE TABLE IF NOT EXISTS geo_villes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_id INT NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    FOREIGN KEY (region_id) REFERENCES geo_regions(id),
    INDEX idx_region (region_id),
    INDEX idx_cp (code_postal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Villes suisses principales
INSERT INTO geo_villes (region_id, code_postal, nom)
SELECT r.id, v.cp, v.nom FROM geo_regions r
JOIN (
    SELECT 'GE' AS code, '1200' AS cp, 'Genève' AS nom UNION ALL
    SELECT 'GE', '1201', 'Genève' UNION ALL
    SELECT 'GE', '1202', 'Genève' UNION ALL
    SELECT 'GE', '1203', 'Genève' UNION ALL
    SELECT 'GE', '1204', 'Genève' UNION ALL
    SELECT 'GE', '1205', 'Genève' UNION ALL
    SELECT 'GE', '1206', 'Genève' UNION ALL
    SELECT 'GE', '1207', 'Genève' UNION ALL
    SELECT 'GE', '1208', 'Genève' UNION ALL
    SELECT 'GE', '1209', 'Petit-Lancy' UNION ALL
    SELECT 'GE', '1212', 'Grand-Lancy' UNION ALL
    SELECT 'GE', '1213', 'Onex' UNION ALL
    SELECT 'GE', '1214', 'Vernier' UNION ALL
    SELECT 'GE', '1215', 'Genève 15 Aéroport' UNION ALL
    SELECT 'GE', '1216', 'Cointrin' UNION ALL
    SELECT 'GE', '1217', 'Meyrin' UNION ALL
    SELECT 'GE', '1218', 'Le Grand-Saconnex' UNION ALL
    SELECT 'GE', '1219', 'Le Lignon' UNION ALL
    SELECT 'GE', '1220', 'Les Avanchets' UNION ALL
    SELECT 'GE', '1222', 'Vésenaz' UNION ALL
    SELECT 'GE', '1223', 'Cologny' UNION ALL
    SELECT 'GE', '1224', 'Chêne-Bougeries' UNION ALL
    SELECT 'GE', '1225', 'Chêne-Bourg' UNION ALL
    SELECT 'GE', '1226', 'Thônex' UNION ALL
    SELECT 'GE', '1227', 'Carouge' UNION ALL
    SELECT 'GE', '1228', 'Plan-les-Ouates' UNION ALL
    SELECT 'GE', '1231', 'Conches' UNION ALL
    SELECT 'GE', '1232', 'Confignon' UNION ALL
    SELECT 'GE', '1233', 'Bernex' UNION ALL
    SELECT 'GE', '1234', 'Vessy' UNION ALL
    SELECT 'GE', '1236', 'Cartigny' UNION ALL
    SELECT 'GE', '1241', 'Puplinge' UNION ALL
    SELECT 'GE', '1242', 'Satigny' UNION ALL
    SELECT 'GE', '1243', 'Presinge' UNION ALL
    SELECT 'GE', '1245', 'Collonge-Bellerive' UNION ALL
    SELECT 'GE', '1246', 'Corsier' UNION ALL
    SELECT 'GE', '1247', 'Anières' UNION ALL
    SELECT 'GE', '1248', 'Hermance' UNION ALL
    SELECT 'GE', '1251', 'Gy' UNION ALL
    SELECT 'GE', '1253', 'Vandœuvres' UNION ALL
    SELECT 'GE', '1254', 'Jussy' UNION ALL
    SELECT 'GE', '1255', 'Veyrier' UNION ALL
    SELECT 'GE', '1256', 'Troinex' UNION ALL
    SELECT 'GE', '1257', 'La Croix-de-Rozon' UNION ALL
    SELECT 'GE', '1258', 'Perly' UNION ALL
    SELECT 'VD', '1000', 'Lausanne' UNION ALL
    SELECT 'VD', '1003', 'Lausanne' UNION ALL
    SELECT 'VD', '1004', 'Lausanne' UNION ALL
    SELECT 'VD', '1005', 'Lausanne' UNION ALL
    SELECT 'VD', '1006', 'Lausanne' UNION ALL
    SELECT 'VD', '1007', 'Lausanne' UNION ALL
    SELECT 'VD', '1010', 'Lausanne' UNION ALL
    SELECT 'VD', '1018', 'Lausanne' UNION ALL
    SELECT 'VD', '1260', 'Nyon' UNION ALL
    SELECT 'VD', '1400', 'Yverdon-les-Bains' UNION ALL
    SELECT 'VD', '1800', 'Vevey' UNION ALL
    SELECT 'VD', '1820', 'Montreux' UNION ALL
    SELECT 'VD', '1110', 'Morges' UNION ALL
    SELECT 'VD', '1020', 'Renens' UNION ALL
    SELECT 'VD', '1030', 'Bussigny' UNION ALL
    SELECT 'VD', '1012', 'Lausanne Chailly' UNION ALL
    SELECT 'VD', '1008', 'Prilly' UNION ALL
    SELECT 'VD', '1009', 'Pully' UNION ALL
    SELECT 'BE', '3000', 'Berne' UNION ALL
    SELECT 'BE', '3001', 'Berne' UNION ALL
    SELECT 'BE', '2500', 'Bienne' UNION ALL
    SELECT 'BE', '3600', 'Thoune' UNION ALL
    SELECT 'ZH', '8000', 'Zurich' UNION ALL
    SELECT 'ZH', '8001', 'Zurich' UNION ALL
    SELECT 'ZH', '8400', 'Winterthour' UNION ALL
    SELECT 'BS', '4000', 'Bâle' UNION ALL
    SELECT 'BS', '4001', 'Bâle' UNION ALL
    SELECT 'LU', '6000', 'Lucerne' UNION ALL
    SELECT 'SG', '9000', 'Saint-Gall' UNION ALL
    SELECT 'TI', '6900', 'Lugano' UNION ALL
    SELECT 'TI', '6500', 'Bellinzone' UNION ALL
    SELECT 'FR', '1700', 'Fribourg' UNION ALL
    SELECT 'NE', '2000', 'Neuchâtel' UNION ALL
    SELECT 'VS', '1950', 'Sion' UNION ALL
    SELECT 'VS', '1920', 'Martigny' UNION ALL
    SELECT 'JU', '2800', 'Delémont' UNION ALL
    SELECT 'SO', '4500', 'Soleure' UNION ALL
    SELECT 'AG', '5000', 'Aarau' UNION ALL
    SELECT 'TG', '8500', 'Frauenfeld' UNION ALL
    SELECT 'SH', '8200', 'Schaffhouse' UNION ALL
    SELECT 'GR', '7000', 'Coire' UNION ALL
    SELECT 'GL', '8750', 'Glaris' UNION ALL
    SELECT 'ZG', '6300', 'Zoug' UNION ALL
    SELECT 'SZ', '6430', 'Schwyz' UNION ALL
    SELECT 'NW', '6370', 'Stans' UNION ALL
    SELECT 'OW', '6060', 'Sarnen' UNION ALL
    SELECT 'UR', '6460', 'Altdorf' UNION ALL
    SELECT 'AI', '9050', 'Appenzell' UNION ALL
    SELECT 'AR', '9100', 'Herisau' UNION ALL
    SELECT 'BL', '4410', 'Liestal'
) v ON v.code = r.code
WHERE r.pays_code = 'CH';

-- Villes françaises principales (frontalières + grandes villes)
INSERT INTO geo_villes (region_id, code_postal, nom)
SELECT r.id, v.cp, v.nom FROM geo_regions r
JOIN (
    SELECT 'ARA' AS code, '74100' AS cp, 'Annemasse' AS nom UNION ALL
    SELECT 'ARA', '74000', 'Annecy' UNION ALL
    SELECT 'ARA', '01210', 'Ferney-Voltaire' UNION ALL
    SELECT 'ARA', '01220', 'Divonne-les-Bains' UNION ALL
    SELECT 'ARA', '01170', 'Gex' UNION ALL
    SELECT 'ARA', '74160', 'Saint-Julien-en-Genevois' UNION ALL
    SELECT 'ARA', '69000', 'Lyon' UNION ALL
    SELECT 'ARA', '69001', 'Lyon 1er' UNION ALL
    SELECT 'ARA', '69002', 'Lyon 2e' UNION ALL
    SELECT 'ARA', '69003', 'Lyon 3e' UNION ALL
    SELECT 'ARA', '38000', 'Grenoble' UNION ALL
    SELECT 'ARA', '42000', 'Saint-Étienne' UNION ALL
    SELECT 'ARA', '63000', 'Clermont-Ferrand' UNION ALL
    SELECT 'ARA', '73000', 'Chambéry' UNION ALL
    SELECT 'ARA', '01000', 'Bourg-en-Bresse' UNION ALL
    SELECT 'ARA', '74200', 'Thonon-les-Bains' UNION ALL
    SELECT 'ARA', '74500', 'Évian-les-Bains' UNION ALL
    SELECT 'IDF', '75001', 'Paris 1er' UNION ALL
    SELECT 'IDF', '75008', 'Paris 8e' UNION ALL
    SELECT 'IDF', '75015', 'Paris 15e' UNION ALL
    SELECT 'IDF', '92000', 'Nanterre' UNION ALL
    SELECT 'IDF', '93000', 'Bobigny' UNION ALL
    SELECT 'IDF', '94000', 'Créteil' UNION ALL
    SELECT 'PAC', '13000', 'Marseille' UNION ALL
    SELECT 'PAC', '06000', 'Nice' UNION ALL
    SELECT 'PAC', '83000', 'Toulon' UNION ALL
    SELECT 'OCC', '31000', 'Toulouse' UNION ALL
    SELECT 'OCC', '34000', 'Montpellier' UNION ALL
    SELECT 'NAQ', '33000', 'Bordeaux' UNION ALL
    SELECT 'PDL', '44000', 'Nantes' UNION ALL
    SELECT 'BRE', '35000', 'Rennes' UNION ALL
    SELECT 'NOR', '76000', 'Rouen' UNION ALL
    SELECT 'HDF', '59000', 'Lille' UNION ALL
    SELECT 'GES', '67000', 'Strasbourg' UNION ALL
    SELECT 'GES', '57000', 'Metz' UNION ALL
    SELECT 'BFC', '21000', 'Dijon' UNION ALL
    SELECT 'BFC', '25000', 'Besançon' UNION ALL
    SELECT 'CVL', '37000', 'Tours' UNION ALL
    SELECT 'CVL', '45000', 'Orléans' UNION ALL
    SELECT 'COR', '20000', 'Ajaccio'
) v ON v.code = r.code
WHERE r.pays_code = 'FR';
