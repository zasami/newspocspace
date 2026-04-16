<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
$planModules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
?>
<div class="planning-toolbar">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <div class="d-flex align-items-center">
      <button class="btn btn-outline btn-sm plan-btn-nav plan-btn-left" id="planPrevMonth"><i class="bi bi-chevron-left"></i></button>
      <input type="month" class="form-control form-control-sm plan-month-input" id="planMois">
      <button class="btn btn-outline btn-sm plan-btn-nav plan-btn-right" id="planNextMonth"><i class="bi bi-chevron-right"></i></button>
    </div>
    <div class="plan-switch" id="planViewMode">
      <div class="plan-switch-bg" id="planSwitchBg"></div>
      <button class="plan-switch-btn active" data-val="week">Semaine</button>
      <button class="plan-switch-btn" data-val="month">Mois</button>
    </div>
  </div>
  <div id="planHolidayBar" class="pg-holiday-bar"></div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <select class="form-control form-control-sm plan-rows-sel" id="planRowsFilter">
      <option value="0">Toutes les lignes</option>
      <option value="10">10 lignes</option>
      <option value="20" selected>20 lignes</option>
      <option value="50">50 lignes</option>
    </select>
    <button class="btn btn-outline btn-sm plan-btn-34" id="planFilterBtn">
      <i class="bi bi-funnel"></i> Filtrer
      <span id="planFilterCount" class="plan-filter-badge" hidden>0</span>
    </button>
    <div class="btn-group btn-group-sm plan-btn-group-34">
      <button class="btn btn-outline" id="planPrintBtn" title="Imprimer"><i class="bi bi-printer"></i></button>
      <button class="btn btn-outline" id="planEmailBtn" title="Email"><i class="bi bi-envelope"></i></button>
    </div>
  </div>
</div>

<div class="planning-week-nav" id="planWeekNav" hidden>
  <button class="btn btn-sm btn-outline-secondary" id="planPrevWeek"><i class="bi bi-chevron-left"></i></button>
  <span id="planWeekLabel" class="fw-600 mx-2"></span>
  <button class="btn btn-sm btn-outline-secondary" id="planNextWeek"><i class="bi bi-chevron-right"></i></button>
</div>

<div id="planningContent" class="planning-loading">
  <span class="spinner"></span> Chargement…
</div>

<!-- Filter Modal (pattern SpocCare) -->
<div class="modal fade" id="planFilterModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered plan-filter-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-funnel"></i> Filtrer les collègues</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto home-close-btn" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal-body plan-filter-body">
        <input type="text" class="form-control form-control-sm mb-3" id="planFilterSearch" placeholder="Rechercher par nom...">
        <div class="mb-3">
          <label class="form-label plan-filter-label">Par fonction</label>
          <div class="d-flex gap-1 flex-wrap" id="planFilterFonctions"></div>
        </div>
        <div class="mb-3">
          <label class="form-label plan-filter-label">Par module</label>
          <div class="d-flex gap-1 flex-wrap" id="planFilterModules"></div>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-bold small"><span id="planFilterSelectedCount">0</span> sélectionné(s)</span>
          <div class="d-flex gap-1">
            <button class="btn btn-outline btn-sm plan-filter-mini-btn" id="planFilterSelectAll">Tout</button>
            <button class="btn btn-outline btn-sm plan-filter-mini-btn plan-filter-mini-danger" id="planFilterDeselectAll">Aucun</button>
          </div>
        </div>
        <div id="planFilterList" class="plan-filter-list"></div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="planFilterApply">
          <i class="bi bi-check-lg"></i> Appliquer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="planEmailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-envelope"></i> Envoyer le planning</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto home-close-btn" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Destinataire</label>
          <input type="email" class="form-control form-control-sm" id="planEmailTo" placeholder="email@exemple.com">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Message (optionnel)</label>
          <textarea class="form-control form-control-sm" id="planEmailMsg" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary ms-auto" id="planEmailSend">
          <i class="bi bi-send"></i> Envoyer
        </button>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="__ss_ssr__"><?= json_encode(['modules' => $planModules], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
