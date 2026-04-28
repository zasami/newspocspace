-- ═══════════════════════════════════════════════════════════════
-- Migration 099 — Préfixe /spocspace/ sur tous les chemins d'images
-- ═══════════════════════════════════════════════════════════════
-- L'app est servie sous /spocspace/ donc les chemins absolus
-- /storage/, /assets/, /uploads/ doivent être préfixés.
-- (Migration 097 avait déjà fait users.photo, on complète avec
-- mur_media, residents, evenements, wiki_pages.)

-- Mur social
UPDATE `mur_media`
   SET `url` = CONCAT('/spocspace', `url`)
 WHERE `url` LIKE '/storage/%' AND `url` NOT LIKE '/spocspace/%';

UPDATE `mur_media`
   SET `url` = CONCAT('/spocspace', `url`)
 WHERE `url` LIKE '/assets/%' AND `url` NOT LIKE '/spocspace/%';

UPDATE `mur_media`
   SET `url` = CONCAT('/spocspace', `url`)
 WHERE `url` LIKE '/uploads/%' AND `url` NOT LIKE '/spocspace/%';

-- Résidents
UPDATE `residents`
   SET `photo_url` = CONCAT('/spocspace', `photo_url`)
 WHERE `photo_url` LIKE '/storage/%' AND `photo_url` NOT LIKE '/spocspace/%';

UPDATE `residents`
   SET `photo_url` = CONCAT('/spocspace', `photo_url`)
 WHERE `photo_url` LIKE '/assets/%' AND `photo_url` NOT LIKE '/spocspace/%';

UPDATE `residents`
   SET `photo_url` = CONCAT('/spocspace', `photo_url`)
 WHERE `photo_url` LIKE '/uploads/%' AND `photo_url` NOT LIKE '/spocspace/%';

-- Évènements
UPDATE `evenements`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/storage/%' AND `image_url` NOT LIKE '/spocspace/%';

UPDATE `evenements`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/assets/%' AND `image_url` NOT LIKE '/spocspace/%';

UPDATE `evenements`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/uploads/%' AND `image_url` NOT LIKE '/spocspace/%';

-- Wiki
UPDATE `wiki_pages`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/storage/%' AND `image_url` NOT LIKE '/spocspace/%';

UPDATE `wiki_pages`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/assets/%' AND `image_url` NOT LIKE '/spocspace/%';

UPDATE `wiki_pages`
   SET `image_url` = CONCAT('/spocspace', `image_url`)
 WHERE `image_url` LIKE '/uploads/%' AND `image_url` NOT LIKE '/spocspace/%';
