<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<!-- Sondages Page - Employee -->
<div class="split-view">

  <!-- LEFT: Sondage List -->
  <div class="split-view-left">
    <div class="split-view-header">
      <h4><i class="bi bi-clipboard2-check"></i> Sondages</h4>
      <small>Répondez aux sondages ouverts</small>
    </div>

    <div id="sondageListContainer" class="split-view-list">
      <div class="split-view-loading">
        <span class="spinner-border spinner-border-sm"></span>
      </div>
    </div>
  </div>

  <!-- RIGHT: Sondage Detail / Answer Form -->
  <div class="split-view-right">
    <div id="sondageDetailPanel" class="split-view-detail">
      <div class="split-view-empty">
        <i class="bi bi-clipboard2-check"></i>
        <p>Sélectionnez un sondage pour y répondre</p>
      </div>
    </div>
  </div>

</div>
