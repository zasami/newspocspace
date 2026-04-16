<?php
require_once __DIR__ . "/../init.php";
if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }

$ssrCategories = Db::fetchAll("SELECT id, nom, slug, icone, couleur FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
$ssrData = ['categories' => $ssrCategories];
?>
<div class="page-header">
  <h1><i class="bi bi-book"></i> Base de connaissances</h1>
  <p>Protocoles, procédures et documentation interne</p>
</div>

<!-- Category filters -->
<div id="wikiCatFilters" class="wk-filters"></div>

<!-- Tag filters + Favoris -->
<div id="wikiTagFilters" class="wk-tag-filters"></div>

<!-- Pages grid -->
<div id="wikiGrid"></div>
<div id="wikiEmpty" class="page-empty ss-hide">
  <i class="bi bi-book wk-empty-icon"></i>
  Aucune page dans cette catégorie
</div>

<!-- Read view -->
<div id="wikiReadView" class="ss-hide">
  <div class="mb-3">
    <button class="btn btn-light btn-sm" id="wikiBackBtn"><i class="bi bi-arrow-left"></i> Retour</button>
  </div>
  <div id="wikiReadPanel" class="wiki-read-panel"></div>
</div>



<script type="application/json" id="__ss_ssr__"><?= json_encode($ssrData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?></script>
