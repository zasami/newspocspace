<?php
require_once __DIR__ . "/../init.php";
if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }

// SSR: fetch document services
$ssrServices = Db::fetchAll(
    "SELECT id, nom, slug, icone, couleur
     FROM document_services
     WHERE actif = 1
     ORDER BY ordre, nom"
);

$ssrData = ['services' => $ssrServices];
?>
<div class="page-header">
  <h1><i class="bi bi-folder2-open"></i> Documents</h1>
  <p>Consultez et téléchargez les documents de l'établissement</p>
</div>

<!-- Service filter cards -->
<div id="docServiceCards" class="doc-service-cards-row" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;"></div>

<!-- Documents grid -->
<div id="docGrid" class="doc-grid">
  <div class="page-loading"><span class="spinner"></span></div>
</div>


<script type="application/json" id="__ss_ssr__"><?php echo json_encode($ssrData, JSON_UNESCAPED_UNICODE); ?></script>
