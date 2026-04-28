-- Normalise les chemins photo : tous doivent être préfixés par /spocspace/
-- (l'app est servie sous /spocspace/, donc /storage/... ne résout pas)
--
-- AVANT : /storage/avatars/abc.webp  (404)
-- APRÈS : /spocspace/storage/avatars/abc.webp  (OK)

UPDATE users
SET photo = CONCAT('/spocspace', photo)
WHERE photo LIKE '/storage/%';
