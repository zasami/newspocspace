<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$planModules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
?>
<!-- Planning Toolbar -->
<div class="planning-toolbar">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <div class="d-flex align-items-center gap-0">
      <button class="btn btn-outline btn-sm" id="planPrevMonth" style="height:34px;border-radius:6px 0 0 6px;border-right:0;padding:0 8px"><i class="bi bi-chevron-left"></i></button>
      <input type="month" class="form-control form-control-sm" id="planMois" style="width:155px;height:34px;border-radius:0">
      <button class="btn btn-outline btn-sm" id="planNextMonth" style="height:34px;border-radius:0 6px 6px 0;border-left:0;padding:0 8px"><i class="bi bi-chevron-right"></i></button>
    </div>
    <div class="plan-switch" id="planViewMode">
      <div class="plan-switch-bg" id="planSwitchBg"></div>
      <button class="plan-switch-btn active" data-val="week">Semaine</button>
      <button class="plan-switch-btn" data-val="month">Mois</button>
    </div>
  </div>
  <div id="planHolidayBar" class="pg-holiday-bar"></div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <select class="form-control form-control-sm" id="planRowsFilter" style="width:auto;height:34px">
      <option value="0">Toutes les lignes</option>
      <option value="10">10 lignes</option>
      <option value="20" selected>20 lignes</option>
      <option value="50">50 lignes</option>
    </select>
    <button class="btn btn-outline btn-sm" id="planFilterBtn" style="height:34px">
      <i class="bi bi-funnel"></i> Filtrer <span id="planFilterCount" class="badge ms-1" style="display:none;background:#d5d2c8;color:#1a1a1a">0</span>
    </button>
    <div class="btn-group btn-group-sm" style="height:34px">
      <button class="btn btn-outline" id="planPrintBtn" title="Imprimer" style="height:34px"><i class="bi bi-printer"></i></button>
      <button class="btn btn-outline" id="planEmailBtn" title="Email" style="height:34px"><i class="bi bi-envelope"></i></button>
    </div>
  </div>
</div>

<!-- Week nav -->
<div class="planning-week-nav" id="planWeekNav" style="display:none">
  <button class="btn btn-sm btn-outline-secondary" id="planPrevWeek"><i class="bi bi-chevron-left"></i></button>
  <span id="planWeekLabel" class="fw-600 mx-2"></span>
  <button class="btn btn-sm btn-outline-secondary" id="planNextWeek"><i class="bi bi-chevron-right"></i></button>
</div>

<!-- Planning grid -->
<div id="planningContent" class="planning-loading">
  <span class="spinner"></span> Chargement...
</div>

<!-- Filter Modal -->
<div class="modal fade" id="planFilterModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content" style="border-radius:var(--ss-radius, 12px);overflow:hidden">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-funnel"></i> Filtrer les collègues</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1rem 1.25rem">
        <input type="text" class="form-control form-control-sm mb-3" id="planFilterSearch" placeholder="Rechercher par nom...">

        <!-- Fonctions tags -->
        <div class="mb-3">
          <label class="form-label fw-bold small text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Par fonction</label>
          <div class="d-flex gap-1 flex-wrap" id="planFilterFonctions"></div>
        </div>

        <!-- Modules tabs -->
        <div class="mb-3">
          <label class="form-label fw-bold small text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Par module</label>
          <div class="d-flex gap-1 flex-wrap" id="planFilterModules"></div>
        </div>

        <hr style="border-color:#e8e6dd">

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-bold small"><span id="planFilterSelectedCount">0</span> sélectionné(s)</span>
          <div class="d-flex gap-1">
            <button class="btn btn-outline btn-sm" id="planFilterSelectAll" style="font-size:.75rem;padding:2px 10px">Tout</button>
            <button class="btn btn-outline btn-sm" id="planFilterDeselectAll" style="font-size:.75rem;padding:2px 10px;color:#dc3545;border-color:#f5c6cb">Aucun</button>
          </div>
        </div>
        <div id="planFilterList" style="max-height:calc(100vh - 380px);min-height:250px;overflow-y:auto;background:#f9f8f5;border:1px solid #e8e6dd;border-radius:8px;padding:4px 0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-dark" id="planFilterApply"><i class="bi bi-check-lg"></i> Appliquer</button>
      </div>
    </div>
  </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="planEmailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:var(--ss-radius, 12px);overflow:hidden">
      <div class="modal-header"><h6 class="modal-title"><i class="bi bi-envelope"></i> Envoyer le planning</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Destinataire</label>
          <input type="email" class="form-control" id="planEmailTo" placeholder="email@exemple.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Message (optionnel)</label>
          <textarea class="form-control" id="planEmailMsg" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-dark btn-sm" id="planEmailSend"><i class="bi bi-send"></i> Envoyer</button>
      </div>
    </div>
  </div>
</div>



<script type="application/json" id="__ss_ssr__"><?= json_encode(['modules' => $planModules], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
