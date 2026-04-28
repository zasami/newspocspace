<?php
// Wrapper theme default — réutilise la page .care.php (visuellement riche, théoriquement même contenu).
// La page .care.php est sélectionnée automatiquement par le dispatcher de admin/index.php
// quand l'utilisateur est en theme-care. Sinon, on l'inclut quand même ici pour avoir
// le même contenu disponible.
include __DIR__ . '/rh-formations-pluriannuel.care.php';
