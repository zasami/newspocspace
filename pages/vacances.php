<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['zt_user']['id'];
$vacYear = (int) date('Y');
$vacDebut = "$vacYear-01-01";
$vacFin = "$vacYear-12-31";

$vacUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.taux, u.solde_vacances,
            f.code AS fonction_code, f.nom AS fonction_nom,
            m.id AS module_id, m.code AS module_code, m.nom AS module_nom, m.ordre AS module_ordre
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY m.ordre, f.ordre, u.nom"
);
$vacAbsences = Db::fetchAll(
    "SELECT a.id, a.user_id, a.date_debut, a.date_fin, a.type, a.statut,
            u.prenom, u.nom
     FROM absences a
     JOIN users u ON u.id = a.user_id
     WHERE a.type = 'vacances'
       AND a.date_debut <= ? AND a.date_fin >= ?
       AND a.statut IN ('valide', 'en_attente')
     ORDER BY a.date_debut",
    [$vacFin, $vacDebut]
);
$vacBloquees = Db::fetchAll(
    "SELECT id, date_debut, date_fin, motif FROM periodes_bloquees
     WHERE date_debut <= ? AND date_fin >= ?
     ORDER BY date_debut",
    [$vacFin, $vacDebut]
);
$vacModules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
$vacMoi = Db::fetch("SELECT solde_vacances FROM users WHERE id = ?", [$uid]);
$vacMonSolde = floatval($vacMoi['solde_vacances'] ?? 27);
$vacJoursUtilises = (int) Db::getOne(
    "SELECT COALESCE(SUM(DATEDIFF(LEAST(date_fin, ?), GREATEST(date_debut, ?)) + 1), 0)
     FROM absences
     WHERE user_id = ? AND type = 'vacances' AND statut IN ('valide', 'en_attente')
       AND date_debut <= ? AND date_fin >= ?",
    [$vacFin, $vacDebut, $uid, $vacFin, $vacDebut]
);
?>
<!-- Header -->
<div class="vac-header">
  <div class="vac-header-left">
    <h1 class="vac-title"><i class="bi bi-sun"></i> Vacances</h1>
  </div>
  <div class="vac-header-right">
    <div class="vac-solde" id="vacSolde">
      <div class="vac-solde-label">Solde restant</div>
      <div class="vac-solde-value" id="vacSoldeValue">&mdash;</div>
      <div class="vac-solde-detail" id="vacSoldeDetail"></div>
    </div>
  </div>
</div>

<!-- Controls -->
<div class="vac-controls">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <button class="btn btn-sm btn-outline-secondary" id="vacPrevMonth"><i class="bi bi-chevron-left"></i></button>
    <span class="vac-current-month" id="vacCurrentMonth"></span>
    <button class="btn btn-sm btn-outline-secondary" id="vacNextMonth"><i class="bi bi-chevron-right"></i></button>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <button class="btn btn-primary btn-sm" id="vacFormBtn">
      <i class="bi bi-plus-lg"></i> Saisir manuellement
    </button>
  </div>
</div>

<!-- Month pills -->
<div class="vac-month-pills" id="vacMonthPills"></div>

<!-- ══════ SECTION 1: Ma ligne de dépôt ══════ -->
<div class="vac-my-section">
  <div class="vac-my-label"><i class="bi bi-person-fill"></i> <span id="vacMyName">Mon planning</span></div>
  <div class="vac-my-topbar">
    <div class="vac-drag-hint" id="vacDragHint"><i class="bi bi-mouse"></i> Glissez pour sélectionner</div>
    <div class="vac-legend-inline">
      <span class="vac-leg"><span class="vac-sw" style="background:#a5d6a7"></span> Validées</span>
      <span class="vac-leg"><span class="vac-sw" style="background:#ffe082"></span> En attente</span>
      <span class="vac-leg"><span class="vac-sw vac-sw-blocked"></span> Bloqué</span>
      <span class="vac-leg"><span class="vac-sw vac-sw-today"></span> Aujourd'hui</span>
    </div>
  </div>
  <div class="vac-drag-info" id="vacDragInfo" style="display:none">
    <i class="bi bi-arrows-expand"></i> <span id="vacDragText"></span>
    <button class="btn btn-sm btn-outline-secondary ms-auto" id="vacDragCancel"><i class="bi bi-x-lg"></i></button>
  </div>
  <div id="vacMyGrid"></div>
</div>

<!-- ══════ SECTION 2: Consultation collègues ══════ -->
<div class="vac-team-section">
  <div class="vac-team-header">
    <span class="vac-team-label"><i class="bi bi-people"></i> Équipe</span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <select class="form-select form-select-sm" id="vacModuleFilter" style="width:170px">
        <option value="">Tous les modules</option>
      </select>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-outline-secondary vac-size-btn" id="vacSize--1" title="Petit">
          <i class="bi bi-zoom-out"></i> <span class="d-none d-sm-inline">S</span>
        </button>
        <button type="button" class="btn btn-outline-secondary vac-size-btn active" id="vacSize-0" title="Moyen">
          <i class="bi bi-zoom-100"></i> <span class="d-none d-sm-inline">M</span>
        </button>
        <button type="button" class="btn btn-outline-secondary vac-size-btn" id="vacSize-1" title="Grand">
          <i class="bi bi-zoom-in"></i> <span class="d-none d-sm-inline">L</span>
        </button>
      </div>
    </div>
  </div>
  <div id="vacTeamGrid"></div>
</div>


<!-- ═══ MODAL: Saisie manuelle ═══ -->
<div class="modal fade" id="vacFormModal" tabindex="-1" aria-labelledby="vacFormModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vacFormModalLabel"><i class="bi bi-calendar-plus"></i> Déposer des vacances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Date de début</label>
          <input type="date" class="form-control form-control-sm" id="vacFormDebut">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Date de fin</label>
          <input type="date" class="form-control form-control-sm" id="vacFormFin">
        </div>
        <div class="alert alert-info py-1 px-2 small" id="vacFormInfo" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-success btn-sm" id="vacFormSubmit"><i class="bi bi-check-lg"></i> Déposer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Confirmer drag ═══ -->
<div class="modal fade" id="vacConfirmModal" tabindex="-1" aria-labelledby="vacConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vacConfirmModalLabel"><i class="bi bi-calendar-check"></i> Confirmer vos vacances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <p class="fw-medium mb-2" id="vacConfirmText"></p>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-bold">Du</label>
            <input type="date" class="form-control form-control-sm" id="vacConfirmDebut">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold">Au</label>
            <input type="date" class="form-control form-control-sm" id="vacConfirmFin">
          </div>
        </div>
        <div class="alert alert-info py-1 px-2 small" id="vacConfirmInfo" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-success btn-sm" id="vacConfirmSubmit"><i class="bi bi-check-lg"></i> Confirmer</button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Layout ── */
.vac-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem}
.vac-header-left{display:flex;align-items:center;gap:.75rem}
.vac-header-right{display:flex;align-items:center;gap:.75rem}
.vac-title{font-size:1.1rem;font-weight:700;margin:0;color:var(--zt-navy,#1B2A4A)}

/* Solde */
.vac-solde{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:8px;padding:5px 12px;text-align:center}
.vac-solde-label{font-size:.6rem;text-transform:uppercase;letter-spacing:.04em;color:#388e3c;font-weight:600}
.vac-solde-value{font-size:1.3rem;font-weight:800;color:#2e7d32;line-height:1.1}
.vac-solde-detail{font-size:.6rem;color:#558b2f}
.vac-solde.low{background:linear-gradient(135deg,#fff3e0,#ffe0b2)}
.vac-solde.low .vac-solde-value{color:#e65100}
.vac-solde.low .vac-solde-label,.vac-solde.low .vac-solde-detail{color:#bf360c}

.vac-controls{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.4rem}
.vac-current-month{font-size:1rem;font-weight:700;min-width:140px;text-align:center;color:var(--zt-navy,#1B2A4A)}

.vac-month-pills{display:flex;gap:3px;margin-bottom:.5rem;overflow-x:auto;padding:2px 0}
.vac-month-pill{border:1px solid #dee2e6;background:#fff;border-radius:4px;padding:2px 9px;font-size:.68rem;font-weight:600;cursor:pointer;white-space:nowrap}
.vac-month-pill:hover{background:#e3f2fd;border-color:#90caf9}
.vac-month-pill.active{background:var(--zt-navy,#1B2A4A);color:#fff;border-color:var(--zt-navy,#1B2A4A)}

/* ══ Section: Ma ligne ══ */
.vac-my-section{background:#f5f4ed;border:2px solid #eceae2;border-radius:10px;padding:10px 12px 8px;margin-bottom:12px}
.vac-my-label{font-size:.85rem;font-weight:700;color:#1a1a1a;margin-bottom:2px}
.vac-my-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:4px;margin-bottom:6px}
.vac-drag-hint{font-size:.7rem;color:#66bb6a}
.vac-legend-inline{display:flex;align-items:center;gap:8px;font-size:.65rem;color:#555;flex-shrink:0}
.vac-drag-info{background:linear-gradient(90deg,#ff9800,#f57c00);color:#fff;padding:5px 12px;font-size:.78rem;font-weight:500;display:flex;align-items:center;gap:6px;border-radius:5px;margin-bottom:6px}

/* My grid — large cells */
.vac-my-wrap{overflow-x:auto;border-radius:6px;padding-bottom:10px}
.vac-my-table{border-collapse:separate;border-spacing:0;width:100%;user-select:none;-webkit-user-select:none}
.vac-my-table th{background:#e8f5e9;border:1px solid #c8e6c9;padding:4px 2px;text-align:center;font-size:.72rem;font-weight:600;color:#2e7d32;white-space:nowrap}
.vac-my-table th.th-we{background:#fff8e1;color:#856404;border-color:#ffe082}
.vac-my-table th.th-today{background:#faf5eb;color:#8d6e27;font-weight:700}
.vac-my-table td.mc{min-width:44px;height:44px;border:1px solid #c8e6c9;background:#fff;position:relative;cursor:crosshair;transition:all .15s;text-align:center;vertical-align:middle}
.vac-my-table td.mc:hover{background:#e3f2fd;border-color:#90caf9}
.vac-my-table td.mc.we{background:#fffbf0;cursor:default}
.vac-my-table td.mc.blocked{background:repeating-linear-gradient(45deg,#ffcdd2,#ffcdd2 2px,#fff 2px,#fff 5px);cursor:not-allowed}
.vac-my-table td.mc.today{background:repeating-linear-gradient(135deg,#faf5eb,#faf5eb 3px,#f5eed8 3px,#f5eed8 5px)}
.vac-my-table td.mc.vv{background:#a5d6a7;cursor:default}
.vac-my-table td.mc.va{background:#ffe082;cursor:pointer}
.vac-my-table td.mc.drag-hl{background:#ffcc80!important;outline:2px solid #ff9800;outline-offset:-2px}
.vac-my-table td.mc .my-lbl{font-size:.6rem;font-weight:700;color:#1b5e20}
.vac-my-table td.mc.va .my-lbl{color:#e65100}
.vac-my-table td.mc .my-del{position:absolute;top:-5px;right:-5px;z-index:6;width:16px;height:16px;border-radius:50%;background:#fff;color:#d32f2f;font-size:.6rem;display:none;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.3);cursor:pointer;line-height:1;border:1px solid #ffcdd2;transition:all .2s}
.vac-my-table td.mc:hover .my-del{display:flex}
.vac-my-table td.mc.del-confirm{background:#ffcdd2!important;border-color:#e53935!important;animation:vac-pulse-red .6s ease-in-out infinite alternate}
.vac-my-table td.mc.del-confirm .my-del{display:flex;background:#d32f2f;color:#fff;border-color:#b71c1c;width:20px;height:20px;font-size:.7rem}
.vac-my-table td.mc.del-confirm .my-lbl{color:#c62828}
@keyframes vac-pulse-red{from{background:#ffcdd2}to{background:#ef9a9a}}

/* ══ Section: Équipe ══ */
.vac-team-section{margin-bottom:8px}
.vac-team-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;gap:8px;flex-wrap:wrap}
.vac-team-label{font-size:.85rem;font-weight:700;color:var(--zt-navy,#1B2A4A)}
.vac-size-btn{font-size:.85rem;padding:.25rem .4rem}
.vac-size-btn.active{background:var(--zt-navy,#1B2A4A);color:#fff;border-color:var(--zt-navy,#1B2A4A)}
.vac-team-wrap{overflow-x:auto;border:1px solid #e0e0e0;border-radius:6px;max-height:calc(100vh - 280px);overflow-y:auto}
.vac-team-table{border-collapse:separate;border-spacing:0;width:100%;font-size:.72rem;user-select:none;-webkit-user-select:none}
.vac-team-table th,.vac-team-table td{border:1px solid #ececec;padding:1px 3px;text-align:center;white-space:nowrap;vertical-align:middle}
.vac-team-table thead th{position:sticky;top:0;z-index:10;background:#f8f9fa;font-weight:600;font-size:.68rem}
.vac-team-table .col-user{position:sticky;left:0;z-index:5;background:#fff;text-align:left;min-width:150px;max-width:180px;font-weight:500;padding:1px 5px;overflow:hidden;text-overflow:ellipsis}
.vac-team-table thead .col-user{z-index:15;background:#f8f9fa}
.vac-team-table .module-sep td{background:#2c3e50;color:#fff;font-weight:700;font-size:.75rem;text-align:left;padding:3px 8px;border-color:#2c3e50}
.vac-team-table .fn-badge{display:inline-block;font-size:.6rem;padding:0 3px;border-radius:3px;background:#e9ecef;color:#495057;font-weight:600;margin-right:2px;vertical-align:middle}
.vac-team-table .tc{min-width:30px;height:22px;position:relative;cursor:default;transition:all .15s}
.vac-team-table .tc.we{background:#fffbf0}
.vac-team-table .tc.today{background:repeating-linear-gradient(135deg,#faf5eb,#faf5eb 3px,#f5eed8 3px,#f5eed8 5px)}
.vac-team-table .tc.vv{background:#a5d6a7}
.vac-team-table .tc.va{background:#ffe082}

/* Cell sizing */
#vacTeamGrid.size-small .vac-team-table .tc{min-width:20px;height:16px;padding:0 1px}
#vacTeamGrid.size-small .vac-team-table .col-user{min-width:100px;max-width:120px}
#vacTeamGrid.size-large .vac-team-table .tc{min-width:45px;height:32px;padding:0 4px}
#vacTeamGrid.size-large .vac-team-table .col-user{min-width:170px;max-width:200px}
#vacTeamGrid.size-large .t-lbl{font-size:.6rem}
.vac-team-table .th-we{background:#fff8e1!important;color:#856404}
.vac-team-table .th-today{background:#faf5eb!important;color:#8d6e27;font-weight:700}
.vac-team-table .myrow td{background:rgba(25,135,84,.07)}
.vac-team-table .myrow .col-user{background:rgba(25,135,84,.12);font-weight:700;color:#1b5e20}
.vac-team-table .myrow td:first-child{border-left:3px solid #43a047}
.vac-team-table .t-lbl{position:absolute;top:0;left:0;bottom:0;display:flex;align-items:center;padding:0 2px;font-size:.52rem;font-weight:700;color:#1b5e20;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;z-index:2}
.vac-team-table .t-lbl.att{color:#e65100}
.vac-my-table tr.del-confirm td { background: #ffcdd2 !important; border-color: #e53935 !important; animation: vac-pulse-red .6s ease-in-out infinite alternate; }
.vac-my-table tr.del-confirm td.mc .my-del { display: flex !important; background: #d32f2f; color: #fff; border-color: #b71c1c; width: 20px; height: 20px; font-size: .7rem; }

/* ── Legend ── */
.vac-leg{display:flex;align-items:center;gap:3px}
.vac-sw{display:inline-block;width:14px;height:10px;border:1px solid #bbb;border-radius:2px}
.vac-sw-blocked{background:repeating-linear-gradient(45deg,#ffcdd2,#ffcdd2 2px,#fff 2px,#fff 5px)}
.vac-sw-today{background:repeating-linear-gradient(135deg,#faf5eb,#faf5eb 3px,#f5eed8 3px,#f5eed8 5px)}
.vac-empty{text-align:center;padding:2rem;color:#999}

@media(max-width:768px){
  .vac-header{flex-direction:column;align-items:stretch}
  .vac-controls{flex-direction:column;align-items:stretch}
  .vac-my-table td.mc{min-width:32px;height:36px}
  .vac-team-table .tc{min-width:22px}
  .vac-team-table .col-user{min-width:100px;max-width:120px}
}
</style>
<script type="application/json" id="__zt_ssr__"><?= json_encode([
    'success'        => true,
    'annee'          => $vacYear,
    'users'          => $vacUsers,
    'absences'       => $vacAbsences,
    'bloquees'       => $vacBloquees,
    'modules'        => $vacModules,
    'mon_solde'      => $vacMonSolde,
    'jours_utilises' => $vacJoursUtilises,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
