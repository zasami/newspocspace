<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
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
    <div class="modal-content" style="border-radius:var(--zt-radius, 12px);overflow:hidden">
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
    <div class="modal-content" style="border-radius:var(--zt-radius, 12px);overflow:hidden">
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

<style>
#app-content { padding: 0.5rem; padding-top: 1rem; }

.planning-toolbar {
  display: flex; justify-content: space-between; align-items: center;
  gap: .5rem; flex-wrap: wrap; margin-bottom: 0.5rem;
}
.planning-week-nav {
  display: flex; align-items: center; justify-content: center; padding: .5rem 0;
}

/* ── View switch (carousel toggle) ── */
.plan-switch {
  position: relative;
  display: inline-flex;
  background: #f0eee6;
  border: 1px solid #e8e6dd;
  border-radius: 8px;
  padding: 3px;
  height: 34px;
  gap: 0;
}
.plan-switch-bg {
  position: absolute;
  top: 3px;
  left: 3px;
  height: calc(100% - 6px);
  width: calc(50% - 3px);
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,.08);
  transition: transform .25s ease;
  pointer-events: none;
  z-index: 0;
}
.plan-switch-bg.right {
  transform: translateX(100%);
}
.plan-switch-btn {
  position: relative;
  z-index: 1;
  background: none;
  border: none;
  padding: 0 14px;
  font-size: .82rem;
  font-weight: 500;
  color: #888;
  cursor: pointer;
  transition: color .2s;
  white-space: nowrap;
  line-height: 28px;
}
.plan-switch-btn.active {
  color: #1a1a1a;
  font-weight: 600;
}
.plan-switch-btn:hover:not(.active) {
  color: #555;
}

/* ── Grid wrapper ── */
.pg-wrap {
  overflow-x: auto;
  overflow-y: auto;
  max-height: calc(100vh - 130px);
  border: 1px solid #e8e5e0;
  border-radius: 6px;
  padding-bottom: 10px;
}

/* ── Table ── */
.pg {
  border-collapse: collapse;
  width: 100%;
  font-size: .76rem;
  background: #fff;
}
.pg th, .pg td {
  border: 1px solid #f0ede8;
  padding: 4px 6px;
  text-align: center;
  white-space: nowrap;
  height: 36px;
}

/* ── Header ── */
.pg thead th {
  position: sticky;
  top: 0;
  z-index: 10;
  background: #f9f7f4;
  font-weight: 600;
  font-size: .72rem;
  text-transform: uppercase;
}

/* ── Col: Nom (sticky left) ── */
.pg .c-name {
  position: sticky;
  left: 0;
  z-index: 5;
  background: #fff;
  text-align: left;
  width: 150px;
  min-width: 150px;
  max-width: 150px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pg thead .c-name { z-index: 20; background: #f9f7f4; }

/* ── Col: Fonction (sticky left after name) ── */
.pg .c-fn {
  position: sticky;
  left: 150px;
  z-index: 5;
  background: #fff;
  width: 46px;
  min-width: 46px;
  max-width: 46px;
  font-size: .62rem;
  font-weight: 600;
  color: #495057;
  overflow: hidden;
}
.pg thead .c-fn { z-index: 20; background: #f9f7f4; }
.pg .c-fn .fn-tag {
  display: inline-block;
  padding: 1px 4px;
  border-radius: 3px;
  background: #e9ecef;
  font-size: .6rem;
  font-weight: 600;
  line-height: 1.3;
}

/* ── Col: Taux (sticky left after fn) ── */
.pg .c-taux {
  position: sticky;
  left: 196px;
  z-index: 5;
  background: #f7f6f2;
  width: 34px;
  min-width: 34px;
  max-width: 34px;
  font-size: .68rem;
  font-weight: 500;
  color: #666;
}
.pg thead .c-taux { z-index: 20; background: #efece6; }

/* ── Col: Total (sticky right) ── */
.pg .c-tot {
  position: sticky;
  right: 0;
  z-index: 5;
  background: #f9f7f4;
  font-weight: 600;
  width: 58px;
  min-width: 58px;
  max-width: 58px;
  border-left: 2px solid #ddd9d0;
  box-shadow: -3px 0 6px rgba(0,0,0,.06);
}
.pg thead .c-tot { z-index: 20; }

/* ── Day cells ── */
.pg .c-day {
  min-width: 42px;
}
.pg .c-day .sc {
  display: inline-block;
  padding: 3px 6px;
  border-radius: 4px;
  color: #fff;
  font-weight: 700;
  font-size: .72rem;
  line-height: 1.2;
}
.pg .c-day.absent { background: #fff3f3; }
.pg .c-day.repos  { background: #f5f5f5; }
.pg .c-day.we     { background: #f8f6f2; }
.pg thead .we     { background: #f0ede6 !important; }
.pg thead .we .we-day-num { color: #aaa; }

/* ── Holiday cells ── */
.pg .c-day.holiday     { border-left: 2px solid #e8b84b; border-right: 2px solid #e8b84b; }
.pg thead .holiday     { border-left: 2px solid #e8b84b; border-right: 2px solid #e8b84b; border-top: 2px solid #e8b84b; }
.pg tbody tr:last-child .c-day.holiday { border-bottom: 2px solid #e8b84b; }
.pg .holiday-icon {
  display: block;
  font-size: .55rem;
  line-height: 1;
  margin-top: 1px;
}

/* ── Holiday bar (inside toolbar) ── */
.pg-holiday-bar {
  display: flex;
  align-items: center;
  gap: 5px;
  overflow-x: auto;
  flex: 1;
  min-width: 0;
}
.pg-holiday-bar:empty { display: none; }
.pg-holiday-bar .hb-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: transparent;
  border: 1px solid #d5d2c8;
  border-radius: 6px;
  font-size: .74rem;
  font-weight: 500;
  color: #1a1a1a;
  white-space: nowrap;
  flex-shrink: 0;
  height: 34px;
}
.pg-holiday-bar .hb-item .hb-icon { font-size: .85rem; }
.pg-holiday-bar .hb-item .hb-days {
  font-size: .62rem;
  color: #888;
  font-weight: 600;
}

/* ── Module separator ── */
.pg .mod td {
  background: #1a1a1a !important;
  color: #fff;
  font-weight: 700;
  font-size: .75rem;
  text-align: left;
  padding: 5px;
  border-color: #333;
}
.pg .mod .c-name { background: #1a1a1a !important; color: #fff; }
.pg .mod .c-fn   { background: #1a1a1a !important; color: #fff; }
.pg .mod .c-taux { background: #1a1a1a !important; color: #fff; text-align: center; }
.pg .mod .c-tot  { background: #1a1a1a !important; border-left-color: #333; }

/* ── Row hover ── */
.pg tbody tr:not(.mod):hover td { background: #f5f3ee; }
.pg tbody tr:not(.mod):hover .c-name { background: #edeae3; }
.pg tbody tr:not(.mod):hover .c-fn   { background: #edeae3; }
.pg tbody tr:not(.mod):hover .c-taux { background: #e8e5de; }
.pg tbody tr:not(.mod):hover .c-tot  { background: #edeae3; }

/* ── My row (connected user) — overrides everything including weekends ── */
.pg .me td,
.pg .me td.c-day,
.pg .me td.c-day.we { background: #ede8d8; }
.pg .me .c-name { background: #e5dfc9; font-weight: 600; }
.pg .me .c-fn   { background: #e5dfc9; }
.pg .me .c-taux { background: #ddd7c1; }
.pg .me .c-tot  { background: #e5dfc9; }

.h-over  { color: #dc3545; }
.h-under { color: #ffc107; }
.h-ok    { color: #28a745; }

/* ── Filter active banner ── */
.pg-filter-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  margin-bottom: 8px;
  background: #f9f7f2;
  border: 1px solid #e8e6dd;
  border-radius: 8px;
  font-size: .82rem;
  color: #1a1a1a;
}
.pg-filter-banner strong { font-weight: 600; }
.pg-filter-banner .pg-filter-clear {
  margin-left: auto;
  padding: 3px 10px;
  border: 1px solid #e8e6dd;
  border-radius: 6px;
  background: #fff;
  font-size: .78rem;
  font-weight: 500;
  color: #555;
  cursor: pointer;
  transition: all .15s;
}
.pg-filter-banner .pg-filter-clear:hover {
  background: #f0eee6;
  color: #1a1a1a;
}

/* ── Filter modal: fonction tags ── */
.pf-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: 1px solid #e8e6dd;
  border-radius: 6px;
  background: #fff;
  font-size: .8rem;
  font-weight: 500;
  color: #555;
  cursor: pointer;
  transition: all .2s;
}
.pf-tag:hover {
  background: #f0eee6;
  border-color: #d5d2c8;
  color: #1a1a1a;
}
.pf-tag.active {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}
.pf-tag.active .pf-tag-count {
  background: rgba(255,255,255,.2);
  color: #fff;
}
.pf-tag .pf-tag-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 5px;
  border-radius: 10px;
  background: #e9ecef;
  font-size: .68rem;
  font-weight: 600;
  color: #495057;
}

/* ── Filter modal: module tabs ── */
.pf-mod-tab {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 12px;
  border: 1px solid #e8e6dd;
  border-radius: 8px;
  background: #f0eee6;
  font-size: .78rem;
  font-weight: 600;
  color: #1a1a1a;
  cursor: pointer;
  transition: all .2s;
}
.pf-mod-tab:hover {
  background: #e8e5dc;
  border-color: #ccc8be;
}
.pf-mod-tab.active {
  background: #1a1a1a;
  border-color: #1a1a1a;
  color: #fff;
}
.pf-mod-tab.active .pf-tag-count {
  background: rgba(255,255,255,.2);
  color: #fff;
}
.pf-mod-tab .pf-tag-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 5px;
  border-radius: 10px;
  background: #1a1a1a;
  color: #fff;
  font-size: .68rem;
  font-weight: 600;
}

/* ── Filter modal: user list ── */
.pf-user {
  display: flex;
  align-items: center;
  padding: 8px 10px;
  border-bottom: 1px solid #f5f3ef;
  cursor: pointer;
  transition: background .15s;
  border-radius: 4px;
  margin-bottom: 1px;
}
.pf-user:hover {
  background: #f9f8f5;
}
.pf-user.selected {
  background: rgba(40, 167, 69, .07);
}
.pf-user .pf-user-name {
  flex: 1;
  font-weight: 500;
  font-size: .85rem;
  color: #1a1a1a;
}
.pf-user .pf-user-fn {
  font-size: .7rem;
  font-weight: 600;
  color: #888;
  margin-left: 6px;
}
.pf-user .pf-user-mod {
  font-size: .7rem;
  color: #aaa;
  margin-left: auto;
  margin-right: 8px;
}
.pf-user .pf-check {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity .15s;
}
.pf-user.selected .pf-check {
  opacity: 1;
  background: rgba(40, 167, 69, .15);
  color: #28a745;
}

/* ── Planning status overlay ── */
.pg-status-wrap {
  position: relative;
}
.pg-status-wrap .pg-wrap {
  opacity: .15;
  pointer-events: none;
  filter: blur(1px);
}
.pg-status-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 30;
}
.pg-status-card {
  background: #fff;
  border: 1px solid #e8e6dd;
  border-radius: 14px;
  padding: 2rem 2.5rem;
  text-align: center;
  max-width: 420px;
  width: 90%;
  box-shadow: 0 8px 32px rgba(0,0,0,.08);
}
.pg-status-icon {
  font-size: 2.5rem;
  margin-bottom: .75rem;
}
.pg-status-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: .25rem;
}
.pg-status-sub {
  font-size: .82rem;
  color: #888;
  margin-bottom: 1.25rem;
}
.pg-progress-track {
  display: flex;
  align-items: center;
  gap: 0;
  margin: 0 auto;
  max-width: 340px;
}
.pg-progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  position: relative;
}
.pg-progress-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e8e6dd;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .6rem;
  font-weight: 700;
  color: #aaa;
  position: relative;
  z-index: 2;
  transition: all .3s;
}
.pg-progress-step.done .pg-progress-dot {
  background: #28a745;
  color: #fff;
}
.pg-progress-step.active .pg-progress-dot {
  background: #f0c040;
  color: #1a1a1a;
  box-shadow: 0 0 0 4px rgba(240,192,64,.25);
}
.pg-progress-label {
  font-size: .62rem;
  font-weight: 600;
  color: #999;
  margin-top: 5px;
  white-space: nowrap;
}
.pg-progress-step.done .pg-progress-label { color: #28a745; }
.pg-progress-step.active .pg-progress-label { color: #1a1a1a; font-weight: 700; }
.pg-progress-line {
  flex: 1;
  height: 3px;
  background: #e8e6dd;
  margin: 0 -2px;
  margin-bottom: 18px;
  position: relative;
  z-index: 1;
}
.pg-progress-line.done { background: #28a745; }

/* ── Mobile ── */
@media (max-width: 768px) {
  .planning-toolbar { flex-direction: column; align-items: stretch; }
  .pg { font-size: .68rem; }
  .pg .c-name { width: 110px; min-width: 110px; max-width: 110px; }
  .pg .c-fn   { left: 110px; width: 38px; min-width: 38px; max-width: 38px; }
  .pg .c-taux { left: 148px; width: 30px; min-width: 30px; max-width: 30px; }
  .pg .c-day  { min-width: 30px; }
  .pg .c-tot  { width: 48px; min-width: 48px; max-width: 48px; }
}
</style>

<script type="application/json" id="__zt_ssr__"><?= json_encode(['modules' => $planModules], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
