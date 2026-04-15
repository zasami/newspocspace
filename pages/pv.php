<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$pvRefModules = Db::fetchAll("SELECT id, nom, code FROM modules ORDER BY ordre, nom");
?>
<!-- PV Page - Split View (List + Detail) -->
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css">
<link rel="stylesheet" href="/spocspace/admin/assets/css/emoji-picker.css">
<div class="split-view">


  <!-- LEFT: PV List (Inbox style) -->
  <div class="split-view-left">
    <!-- Header with filters -->
    <div class="split-view-header">
      <h4><i class="bi bi-file-earmark-text"></i> Procès-Verbaux</h4>
      <select class="form-select form-select-sm" id="pvModuleFilter">
        <option value="">Tous les modules</option>
      </select>
    </div>

    <!-- PV List -->
    <div id="pvListContainer" class="split-view-list">
      <div class="split-view-loading">
        <span class="spinner-border spinner-border-sm"></span>
      </div>
    </div>

    <!-- Pagination -->
    <div class="split-view-footer">
      <span id="pvCount">—</span> PV
    </div>
  </div>

  <!-- RIGHT: PV Detail Panel -->
  <div class="split-view-right">
    <div id="pvDetailPanel" class="split-view-detail">
      <div class="split-view-empty">
        <i class="bi bi-file-earmark-text"></i>
        <p>Sélectionnez un PV pour voir les détails</p>
      </div>
    </div>
  </div>

</div>



<script type="application/json" id="__ss_ssr__"><?= json_encode(['modules' => $pvRefModules], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>

