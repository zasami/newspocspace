<?php
// ─── Données serveur — semaine courante ──────────────────────────────────────
$dto = new DateTime();
$dow = (int)$dto->format('N'); // 1=Mon
$dto->modify('-' . ($dow - 1) . ' days');
$weekStart = $dto->format('Y-m-d');

$dtoStart = new DateTime($weekStart);
$dtoEnd = clone $dtoStart;
$dtoEnd->modify('+6 days');
$weekEnd = $dtoEnd->format('Y-m-d');

$weekNum = (int)$dtoStart->format('W');
$year    = (int)$dtoStart->format('o');

$frMonths = [
    1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
    5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
    9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
];

$startDay   = (int)$dtoStart->format('j');
$endDay     = (int)$dtoEnd->format('j');
$startMonth = $frMonths[(int)$dtoStart->format('n')];
$endMonth   = $frMonths[(int)$dtoEnd->format('n')];

if ($dtoStart->format('n') === $dtoEnd->format('n')) {
    $weekLabel = "Semaine $weekNum — $startDay au $endDay $endMonth $year";
} else {
    $weekLabel = "Semaine $weekNum — $startDay $startMonth au $endDay $endMonth $year";
}

$weekIso = "$year-W" . str_pad($weekNum, 2, '0', STR_PAD_LEFT);

$repModules = Db::fetchAll("SELECT id, nom, code, ordre FROM modules ORDER BY ordre");
foreach ($repModules as &$mod) {
    $etages = Db::fetchAll("SELECT id, nom, code, ordre FROM etages WHERE module_id = ? ORDER BY ordre", [$mod['id']]);
    foreach ($etages as &$etage) {
        $etage['groupes'] = Db::fetchAll("SELECT id, nom, code, ordre FROM groupes WHERE etage_id = ? ORDER BY ordre", [$etage['id']]);
    }
    unset($etage);
    $mod['etages'] = $etages;
}
unset($mod);

$repHoraires  = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");
$repFonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");

$repUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.employee_id,
            f.id AS fonction_id, f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            hm.id AS home_module_id, hm.code AS home_module_code, hm.nom AS home_module_nom, hm.ordre AS home_module_ordre
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules hm ON hm.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY hm.ordre, f.ordre, u.nom"
);

$moisStart = $dtoStart->format('Y-m');
$moisEnd   = $dtoEnd->format('Y-m');
$moisList  = array_unique([$moisStart, $moisEnd]);
$phMois    = implode(',', array_fill(0, count($moisList), '?'));
$repPlannings = Db::fetchAll("SELECT id, mois_annee, statut FROM plannings WHERE mois_annee IN ($phMois)", $moisList);
$planningIds  = array_column($repPlannings, 'id');

$repAssignments = [];
if ($planningIds) {
    $phPlan  = implode(',', array_fill(0, count($planningIds), '?'));
    $qParams = array_merge($planningIds, [$weekStart, $weekEnd]);
    $repAssignments = Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.planning_id, pa.date_jour, pa.user_id,
                pa.horaire_type_id, pa.statut, pa.notes, pa.updated_at,
                u.prenom AS user_prenom, u.nom AS user_nom,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.code AS module_code, m.id AS module_id,
                g.code AS groupe_code, g.id AS groupe_id,
                e.code AS etage_code, e.id AS etage_id
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN groupes g ON g.id = pa.groupe_id
         LEFT JOIN etages e ON e.id = g.etage_id
         WHERE pa.planning_id IN ($phPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
        $qParams
    );
}

$frDays  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$repDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $dtoStart;
    $d->modify("+$i days");
    $repDays[] = [
        'date'       => $d->format('Y-m-d'),
        'label'      => $frDays[$i] . ' ' . $d->format('d'),
        'short'      => $frDays[$i],
        'is_weekend' => in_array($d->format('N'), ['6', '7']),
    ];
}
?>
<!-- Week navigator -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-outline-secondary btn-sm" id="repPrevWeek" title="Semaine precedente">
      <i class="bi bi-chevron-left"></i>
    </button>
    <h6 class="mb-0 fw-semibold rep-week-label" id="repWeekLabel"><?= h($weekLabel) ?></h6>
    <button class="btn btn-outline-secondary btn-sm" id="repNextWeek" title="Semaine suivante">
      <i class="bi bi-chevron-right"></i>
    </button>
    <button class="btn btn-outline-primary btn-sm" id="repToday" title="Semaine courante">Aujourd'hui</button>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-outline-secondary btn-sm" id="repToggleEdit" title="Mode édition">
      <i class="bi bi-pencil-square"></i> Éditer
    </button>
    <input type="date" class="form-control form-control-sm rep-date-picker" id="repDatePicker" value="<?= h($weekStart) ?>">
    <button class="btn btn-outline-secondary btn-sm" id="repPrint" title="Imprimer">
      <i class="bi bi-printer"></i>
    </button>
  </div>
</div>

<!-- Planning status -->
<div id="repPlanningStatus" class="mb-2 rep-planning-status">
  <?php if (!empty($repPlannings)):
    $colors = ['brouillon' => 'secondary', 'provisoire' => 'info', 'final' => 'success'];
    $badges = array_map(fn($p) => '<span class="badge bg-' . ($colors[$p['statut']] ?? 'secondary') . ' me-1">' . h($p['mois_annee']) . ' : ' . h($p['statut']) . '</span>', $repPlannings);
    echo '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' . implode('', $badges);
  else: ?>
    <span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette periode</span>
  <?php endif; ?>
</div>

<!-- Legend -->
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center rep-legend" id="repLegend">
  <span class="text-muted fw-medium">Légende :</span>
  <?php foreach ($repHoraires as $h): ?>
    <span class="rep-legend-item">
      <span class="rep-badge" style="background:<?= htmlspecialchars($h['couleur'] ?? '#6c757d') ?>"><?= htmlspecialchars($h['code']) ?></span>
      <span class="text-muted"><?= htmlspecialchars(substr($h['heure_debut'] ?? '', 0, 5)) ?>-<?= htmlspecialchars(substr($h['heure_fin'] ?? '', 0, 5)) ?></span>
    </span>
  <?php endforeach; ?>
</div>

<!-- Planning grid container -->
<div id="repGrid" class="rep-grid-container">
  <div class="text-center py-5 text-muted">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    Chargement de la répartition...
  </div>
</div>

<!-- Edit popover (hidden, positioned via JS) -->
<div id="repEditPopover" class="rep-edit-popover" style="display:none;">
  <div class="rep-edit-popover-header">
    <span id="repEditTitle"></span>
    <button type="button" class="btn-close btn-close-sm" id="repEditClose"></button>
  </div>
  <div class="rep-edit-popover-body">
    <div class="mb-2">
      <label class="form-label rep-edit-label">Horaire</label>
      <select id="repEditHoraire" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label class="form-label rep-edit-label">Module</label>
      <select id="repEditModule" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label class="form-label rep-edit-label">Étage / Groupe</label>
      <select id="repEditGroupe" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label class="form-label rep-edit-label">Statut</label>
      <select id="repEditStatut" class="form-select form-select-sm">
        <option value="present">Présent</option>
        <option value="absent">Absent</option>
        <option value="remplace">Remplacé</option>
        <option value="interim">Intérim</option>
        <option value="entraide">Entraide</option>
        <option value="repos">Repos</option>
        <option value="vacant">Vacant</option>
      </select>
    </div>
    <div class="mb-2">
      <label class="form-label rep-edit-label">Notes</label>
      <input type="text" id="repEditNotes" class="form-control form-control-sm" maxlength="500" placeholder="Notes...">
    </div>
    <div class="d-flex gap-1">
      <button class="btn btn-sm btn-success flex-fill" id="repEditSave"><i class="bi bi-check-lg"></i> Enregistrer</button>
      <button class="btn btn-sm btn-outline-warning" id="repEditAbsent" title="Marquer absent"><i class="bi bi-person-x"></i></button>
      <button class="btn btn-sm btn-outline-danger" id="repEditDelete" title="Supprimer"><i class="bi bi-trash"></i></button>
    </div>
  </div>
</div>

<!-- Absence modal -->
<div class="modal fade" id="repAbsenceModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-person-x me-1"></i>Marquer absent</h6>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label rep-edit-label">Type d'absence</label>
          <select id="repAbsType" class="form-select form-select-sm">
            <option value="maladie">Maladie</option>
            <option value="vacances">Vacances</option>
            <option value="accident">Accident</option>
            <option value="formation">Formation</option>
            <option value="conge_special">Congé spécial</option>
            <option value="autre">Autre</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label rep-edit-label">Motif (optionnel)</label>
          <input type="text" id="repAbsMotif" class="form-control form-control-sm" maxlength="500">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="repAbsMulti">
          <label class="form-check-label rep-edit-label" for="repAbsMulti">Plusieurs jours</label>
        </div>
        <div id="repAbsMultiDates" style="display:none;">
          <div class="row g-1">
            <div class="col-6">
              <label class="form-label rep-edit-label">Du</label>
              <input type="date" id="repAbsDebut" class="form-control form-control-sm">
            </div>
            <div class="col-6">
              <label class="form-label rep-edit-label">Au</label>
              <input type="date" id="repAbsFin" class="form-control form-control-sm">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-1">
        <button class="btn btn-sm btn-warning" id="repAbsSave"><i class="bi bi-check-lg"></i> Confirmer</button>
      </div>
    </div>
  </div>
</div>

<!-- Styles -->
<style>
/* Layout helpers */
.rep-week-label { min-width: 260px; text-align: center; }
.rep-date-picker { width: 160px; }
.rep-planning-status { font-size: 0.8rem; }
.rep-legend { font-size: 0.75rem; }

/* Empty cell dash */
.rep-empty-dash { color: #d0d0d0; }

/* Empty state */
.rep-empty-state-icon { font-size: 2rem; opacity: .3; display: block; margin-bottom: 8px; }

.rep-grid-container {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 10px;
}

/* Module section */
.rep-module-section {
  margin-bottom: 1.5rem;
}
.rep-module-header {
  padding: 0.45rem 0.75rem;
  font-weight: 600;
  font-size: 0.88rem;
  color: #fff;
  border-radius: 4px 4px 0 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.rep-module-header .badge {
  font-size: 0.7rem;
  background: rgba(255,255,255,0.25);
}

/* Table */
.rep-table {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
  font-size: 0.78rem;
  table-layout: fixed;
}
.rep-table th,
.rep-table td {
  border: 1px solid #dee2e6;
  padding: 0.25rem 0.4rem;
  vertical-align: middle;
}
.rep-table thead th {
  background: #f1f3f5;
  font-weight: 600;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 2;
  font-size: 0.73rem;
}
.rep-table th.col-fn {
  width: 90px;
  text-align: left;
}
.rep-table th.col-name {
  width: 130px;
  text-align: left;
}
.rep-table th.col-day {
  width: calc((100% - 220px) / 7);
}

/* Function cell (rowspan) */
.rep-table td.cell-fn {
  font-weight: 600;
  font-size: 0.73rem;
  color: #333;
  background: #f8f9fa;
  vertical-align: middle;
  text-align: center;
  border-right: 2px solid #ccc;
}

/* Employee name */
.rep-table td.cell-name {
  font-size: 0.75rem;
  color: #222;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background: #fdfdfd;
  font-weight: 500;
}

/* Day cells */
.rep-table td.cell-day {
  text-align: center;
  min-height: 28px;
  position: relative;
}
.rep-table td.cell-day.weekend {
  background: #fefcf3;
}
.rep-table td.cell-day.empty-cell {
  color: #d0d0d0;
}

/* Edit mode: clickable cells */
.rep-edit-mode .rep-table td.cell-day {
  cursor: pointer;
  transition: background 0.15s;
}
.rep-edit-mode .rep-table td.cell-day:hover {
  background: #e8f5e9 !important;
  outline: 2px solid #4CAF50;
  outline-offset: -2px;
}

/* Drag & drop */
.rep-edit-mode .rep-table td.cell-day[draggable="true"] {
  cursor: grab;
}
.rep-edit-mode .rep-table td.cell-day[draggable="true"]:active {
  cursor: grabbing;
}
.rep-table td.cell-day.rep-drag-over {
  background: #bbdefb !important;
  outline: 2px dashed #1976D2;
  outline-offset: -2px;
}
.rep-module-header.rep-drag-over-mod {
  outline: 3px dashed #fff;
  outline-offset: -3px;
  opacity: 0.85;
}
.rep-table td.cell-day.rep-dragging {
  opacity: 0.4;
}

/* Modified cell indicator */
.rep-table td.cell-day.rep-modified {
  position: relative;
}
.rep-table td.cell-day.rep-modified::after {
  content: '';
  position: absolute;
  top: 2px;
  right: 2px;
  width: 6px;
  height: 6px;
  background: #FF9800;
  border-radius: 50%;
}

/* Function group border */
.rep-table tr.fn-group-first td {
  border-top: 2px solid #adb5bd;
}

/* Horaire badge */
.rep-badge {
  display: inline-block;
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 0.66rem;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  line-height: 1.3;
}

/* Etage / groupe code (combined as "1-B") */
.rep-etage {
  font-size: 0.62rem;
  color: #555;
  font-weight: 600;
  margin-left: 2px;
}
/* Module tag (shown when employee works outside home module) */
.rep-mod-tag {
  font-size: 0.6rem;
  color: #c0392b;
  font-style: italic;
  font-weight: 600;
  margin-left: 2px;
}

/* Notes indicator */
.rep-notes {
  font-size: 0.58rem;
  color: #999;
  display: block;
  line-height: 1.1;
  max-width: 80px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin: 0 auto;
}

/* Absent */
.rep-absent {
  text-decoration: line-through;
  opacity: 0.5;
}

/* Absence icon in cell */
.rep-abs-icon {
  width: 16px;
  height: 16px;
  vertical-align: middle;
  opacity: 0.7;
}

/* Module colors */
.rep-mod-M1  { background: #2196F3; }
.rep-mod-M2  { background: #4CAF50; }
.rep-mod-M3  { background: #FF9800; }
.rep-mod-M4  { background: #9C27B0; }
.rep-mod-NUIT { background: #37474F; }
.rep-mod-POOL { background: #795548; }
.rep-mod-RS  { background: #607D8B; }
.rep-mod-DEFAULT { background: #6c757d; }

/* Legend badges */
.rep-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}

/* Edit toggle active state */
#repToggleEdit.active {
  background: #4CAF50;
  border-color: #4CAF50;
  color: #fff;
}

/* Edit popover — absolute to body, repositioned on scroll */
.rep-edit-popover {
  position: absolute;
  z-index: 1050;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  width: 260px;
  font-size: 0.8rem;
}
.rep-edit-popover-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.4rem 0.6rem;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  border-radius: 8px 8px 0 0;
  font-weight: 600;
  font-size: 0.78rem;
}
.rep-edit-popover-body {
  padding: 0.5rem 0.6rem;
}
.rep-edit-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: #555;
  margin-bottom: 2px;
}
.btn-close-sm {
  font-size: 0.6rem;
  padding: 0.3rem;
}

/* Print */
@media print {
  .admin-sidebar, .admin-topbar, .sidebar-overlay,
  #repPrevWeek, #repNextWeek, #repToday, #repDatePicker, #repPrint, #repToggleEdit,
  #repPlanningStatus, #repEditPopover, #repAbsenceModal,
  .topbar-search, .topbar-right { display: none !important; }
  .admin-main { margin-left: 0 !important; }
  .admin-content { padding: 0.5rem !important; }
  .rep-grid-container { overflow: visible; }
  .rep-table { min-width: 0; font-size: 0.68rem; }
  .rep-module-section { page-break-inside: avoid; }
  .rep-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-module-header { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-table td.cell-day.weekend { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-table td.cell-fn { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>

<script<?= nonce() ?>>
(function() {

  let currentWeekISO = <?= json_encode($weekIso) ?>;
  let editMode = false;
  let editingCell = null; // { assignation_id, planning_id, user_id, date, updated_at, ... }
  let data = {
    success: true,
    week_start: <?= json_encode($weekStart) ?>,
    week_end: <?= json_encode($weekEnd) ?>,
    week_label: <?= json_encode($weekLabel) ?>,
    week_iso: <?= json_encode($weekIso) ?>,
    days: <?= json_encode(array_values($repDays), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modules: <?= json_encode(array_values($repModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    fonctions: <?= json_encode(array_values($repFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    horaires: <?= json_encode(array_values($repHoraires), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    plannings: <?= json_encode(array_values($repPlannings), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    assignments: <?= json_encode(array_values($repAssignments), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    users: <?= json_encode(array_values($repUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modified_ids: [],
    absences: [],
  };

  // ─── ISO week helpers ───
  function dateToStr(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function getMondayOfISOWeek(isoWeek) {
    const m = isoWeek.match(/^(\d{4})-W(\d{2})$/);
    if (!m) return null;
    const yr = parseInt(m[1]), wk = parseInt(m[2]);
    const jan4 = new Date(yr, 0, 4);
    const dow = jan4.getDay() || 7;
    const week1Mon = new Date(jan4);
    week1Mon.setDate(jan4.getDate() - dow + 1);
    const target = new Date(week1Mon);
    target.setDate(week1Mon.getDate() + (wk - 1) * 7);
    return target;
  }

  // ─── Load data ───
  async function loadWeek(weekOrDate) {
    const params = {};
    if (weekOrDate && weekOrDate.includes('-W')) {
      params.semaine = weekOrDate;
    } else if (weekOrDate) {
      params.date = weekOrDate;
    }

    document.getElementById('repGrid').innerHTML =
      '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Chargement...</div>';

    const res = await adminApiPost('admin_get_repartition', params);
    if (!res.success) {
      document.getElementById('repGrid').innerHTML =
        '<div class="alert alert-danger">Erreur : ' + escapeHtml(res.message || 'Erreur inconnue') + '</div>';
      return;
    }

    data = res;
    currentWeekISO = res.week_iso;
    document.getElementById('repWeekLabel').textContent = res.week_label;
    document.getElementById('repDatePicker').value = res.week_start;

    // Planning status
    const statusEl = document.getElementById('repPlanningStatus');
    if (res.plannings && res.plannings.length) {
      const colors = { brouillon: 'secondary', provisoire: 'info', final: 'success' };
      const badges = res.plannings.map(p =>
        '<span class="badge bg-' + (colors[p.statut] || 'secondary') + ' me-1">' +
        escapeHtml(p.mois_annee) + ' : ' + escapeHtml(p.statut) + '</span>'
      ).join('');
      statusEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' + badges;
    } else {
      statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette periode</span>';
    }

    hideEditPopover();
    renderLegend();
    renderGrid();
  }

  // ─── Legend ───
  function renderLegend() {
    const el = document.getElementById('repLegend');
    let html = '<span class="text-muted fw-medium">Légende :</span>';
    (data.horaires || []).forEach(function(h) {
      const bg = h.couleur || '#6c757d';
      html += '<span class="rep-legend-item">' +
        '<span class="rep-badge" style="background:' + escapeHtml(bg) + '">' + escapeHtml(h.code) + '</span>' +
        '<span class="text-muted">' + escapeHtml((h.heure_debut || '').substring(0, 5)) + '-' + escapeHtml((h.heure_fin || '').substring(0, 5)) + '</span>' +
        '</span>';
    });
    el.innerHTML = html;
  }

  // ─── Build absence index (user_id+date → type) ───
  function buildAbsenceIndex() {
    const idx = {};
    (data.absences || []).forEach(function(a) {
      const start = new Date(a.date_debut);
      const end = new Date(a.date_fin);
      for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const ds = dateToStr(d);
        const key = a.user_id + '|' + ds;
        idx[key] = a.type;
      }
    });
    return idx;
  }

  // ─── Build employee-centric index grouped by HOME module ───
  function buildIndex() {
    var assignByUser = {};
    (data.assignments || []).forEach(function(a) {
      var uid = a.user_id;
      if (!assignByUser[uid]) assignByUser[uid] = {};
      var dt = a.date_jour;
      if (!assignByUser[uid][dt]) assignByUser[uid][dt] = [];
      assignByUser[uid][dt].push(a);
    });

    var idx = {};
    (data.users || []).forEach(function(u) {
      var mc  = u.home_module_code || '_NONE';
      var fc  = u.fonction_code || '_NONE';
      var uid = u.id;

      if (!idx[mc]) idx[mc] = {};
      if (!idx[mc][fc]) idx[mc][fc] = {};

      idx[mc][fc][uid] = {
        user_id: uid,
        prenom: u.prenom,
        nom: u.nom,
        employee_id: u.employee_id,
        fonction_code: fc,
        fonction_nom: u.fonction_nom || fc,
        fonction_ordre: u.fonction_ordre || 999,
        home_module_code: mc,
        home_module_nom: u.home_module_nom || '',
        home_module_ordre: u.home_module_ordre || 999,
        home_module_id: u.home_module_id || null,
        days: assignByUser[uid] || {}
      };
    });

    return idx;
  }

  // ─── Absence icon helper ───
  function absenceIcon(type) {
    if (type === 'vacances') {
      return '<img src="/newspocspace/assets/webp/vacances_1.webp" class="rep-abs-icon" alt="V" title="Vacances">';
    }
    const icons = {
      maladie: 'bi-bandaid', accident: 'bi-exclamation-triangle',
      formation: 'bi-mortarboard', conge_special: 'bi-calendar-heart',
      autre: 'bi-question-circle'
    };
    const ic = icons[type] || 'bi-dash-circle';
    return '<i class="bi ' + ic + ' rep-abs-icon" title="' + escapeHtml(type) + '"></i>';
  }

  // ─── Render a single day cell for one employee ───
  function renderDayCell(entries, homeModuleCode, userId, date, absIdx) {
    // Check for absence
    const absKey = userId + '|' + date;
    const absType = absIdx[absKey] || null;

    if (!entries || entries.length === 0) {
      if (absType) {
        return absenceIcon(absType);
      }
      return '<span class="rep-empty-dash">—</span>';
    }
    return entries.map(function(a) {
      var bg   = a.horaire_couleur || '#6c757d';
      var code = escapeHtml(a.horaire_code || '?');
      var cls  = a.statut === 'absent' ? ' rep-absent' : '';
      var html = '<span class="' + cls + '">';

      if (a.statut === 'absent' && absType) {
        html += absenceIcon(absType) + ' ';
      }

      html += '<span class="rep-badge" style="background:' + bg + '">' + code + '</span>';
      if (a.etage_code || a.groupe_code) {
        var loc = a.etage_code && a.groupe_code
          ? escapeHtml(a.etage_code) + '-' + escapeHtml(a.groupe_code)
          : escapeHtml(a.etage_code || a.groupe_code);
        html += '<span class="rep-etage">' + loc + '</span>';
      }
      if (a.module_code && homeModuleCode && a.module_code !== homeModuleCode && homeModuleCode !== '_NONE') {
        html += '<span class="rep-mod-tag">' + escapeHtml(a.module_code) + '</span>';
      }
      html += '</span>';
      if (a.notes) {
        html += '<span class="rep-notes" title="' + escapeHtml(a.notes) + '">' + escapeHtml(a.notes) + '</span>';
      }
      return html;
    }).join(' ');
  }

  // ─── Day column headers ───
  function dayHeaders() {
    return (data.days || []).map(function(d) {
      var cls = d.is_weekend ? 'col-day weekend' : 'col-day';
      return '<th class="' + cls + '">' + escapeHtml(d.label) + '</th>';
    }).join('');
  }

  // ─── Render one module section ───
  function renderModuleSection(mod, modData, days, absIdx, modifiedSet) {
    var modColors = {
      'M1': 'rep-mod-M1', 'M2': 'rep-mod-M2', 'M3': 'rep-mod-M3',
      'M4': 'rep-mod-M4', 'NUIT': 'rep-mod-NUIT', 'POOL': 'rep-mod-POOL',
      'RS': 'rep-mod-RS'
    };
    var colorCls = modColors[mod.code] || 'rep-mod-DEFAULT';

    var fnCodes = Object.keys(modData);
    var fnMeta = {};
    fnCodes.forEach(function(fc) {
      var firstUser = modData[fc][Object.keys(modData[fc])[0]];
      fnMeta[fc] = {
        code: fc,
        nom: firstUser ? firstUser.fonction_nom : fc,
        ordre: firstUser ? firstUser.fonction_ordre : 999
      };
    });
    fnCodes.sort(function(a, b) { return (fnMeta[a].ordre || 999) - (fnMeta[b].ordre || 999); });

    var empCount = 0;
    fnCodes.forEach(function(fc) { empCount += Object.keys(modData[fc]).length; });

    var html = '<div class="rep-module-section" data-section-module-id="' + (mod.id || '') + '">';
    html += '<div class="rep-module-header ' + colorCls + '" data-drop-module-id="' + (mod.id || '') + '" data-drop-module-code="' + escapeHtml(mod.code || '') + '">' +
      '<i class="bi bi-building"></i> ' + escapeHtml(mod.nom || mod.code) +
      ' <span class="badge">' + empCount + ' employé(s)</span></div>';
    html += '<table class="rep-table"><thead><tr>' +
      '<th class="col-fn">Fonction</th>' +
      '<th class="col-name">Employé</th>' +
      dayHeaders() +
      '</tr></thead><tbody>';

    fnCodes.forEach(function(fc) {
      var users = Object.values(modData[fc]).sort(function(a, b) {
        return (a.nom || '').localeCompare(b.nom || '');
      });

      users.forEach(function(u, i) {
        var trCls = i === 0 ? ' class="fn-group-first"' : '';
        html += '<tr' + trCls + '>';

        if (i === 0) {
          html += '<td class="cell-fn" rowspan="' + users.length + '">' +
            escapeHtml(fnMeta[fc].nom || fc) + '</td>';
        }

        var fullName = (u.prenom || '') + ' ' + (u.nom || '');
        html += '<td class="cell-name" title="' + escapeHtml(fullName) + '">' + escapeHtml(fullName) + '</td>';

        days.forEach(function(d) {
          var entries = u.days[d.date] || [];
          var weCls   = d.is_weekend ? ' weekend' : '';
          var emptCls = entries.length === 0 ? ' empty-cell' : '';

          // Check if any entry in this cell has been modified
          var modCls = '';
          entries.forEach(function(e) {
            if (e.assignation_id && modifiedSet.has(e.assignation_id)) {
              modCls = ' rep-modified';
            }
          });

          // Data attributes for edit mode
          var firstEntry = entries[0] || null;
          var dataAttrs = ' data-user-id="' + u.user_id + '" data-date="' + d.date + '"';
          if (firstEntry) {
            dataAttrs += ' data-assignation-id="' + (firstEntry.assignation_id || '') + '"';
            dataAttrs += ' data-planning-id="' + (firstEntry.planning_id || '') + '"';
            dataAttrs += ' data-horaire-type-id="' + (firstEntry.horaire_type_id || '') + '"';
            dataAttrs += ' data-module-id="' + (firstEntry.module_id || '') + '"';
            dataAttrs += ' data-groupe-id="' + (firstEntry.groupe_id || '') + '"';
            dataAttrs += ' data-etage-id="' + (firstEntry.etage_id || '') + '"';
            dataAttrs += ' data-statut="' + (firstEntry.statut || 'present') + '"';
            dataAttrs += ' data-notes="' + escapeHtml(firstEntry.notes || '') + '"';
            dataAttrs += ' data-updated-at="' + (firstEntry.updated_at || '') + '"';
          }
          dataAttrs += ' data-user-name="' + escapeHtml(fullName) + '"';
          dataAttrs += ' data-home-module-id="' + (u.home_module_id || '') + '"';

          var dragAttr = firstEntry && firstEntry.assignation_id ? ' draggable="true"' : '';
          html += '<td class="cell-day' + weCls + emptCls + modCls + '"' + dataAttrs + dragAttr + '>' +
            renderDayCell(entries, u.home_module_code, u.user_id, d.date, absIdx) + '</td>';
        });

        html += '</tr>';
      });
    });

    html += '</tbody></table></div>';
    return html;
  }

  // ─── Render the full grid ───
  function renderGrid() {
    var idx    = buildIndex();
    var absIdx = buildAbsenceIndex();
    var modifiedSet = new Set(data.modified_ids || []);
    var modules   = data.modules || [];
    var days      = data.days || [];
    var html      = '';

    var rsRuvCodes = ['RS', 'RUV'];
    var rsData = {};
    Object.keys(idx).forEach(function(mc) {
      rsRuvCodes.forEach(function(fc) {
        if (idx[mc][fc]) {
          if (!rsData[fc]) rsData[fc] = {};
          Object.keys(idx[mc][fc]).forEach(function(uid) {
            rsData[fc][uid] = idx[mc][fc][uid];
          });
        }
      });
    });

    if (Object.keys(rsData).length > 0) {
      html += renderModuleSection(
        { code: 'RS', nom: 'Direction / Responsables' },
        rsData, days, absIdx, modifiedSet
      );
    }

    if (idx['_NONE']) {
      var poolData = {};
      Object.keys(idx['_NONE']).forEach(function(fc) {
        if (rsRuvCodes.indexOf(fc) === -1) {
          poolData[fc] = idx['_NONE'][fc];
        }
      });
      if (Object.keys(poolData).length > 0) {
        html += renderModuleSection(
          { code: 'POOL', nom: 'Pool / Non assigné' },
          poolData, days, absIdx, modifiedSet
        );
      }
    }

    modules.forEach(function(mod) {
      var modData = idx[mod.code];
      if (!modData) return;

      var filtered = {};
      Object.keys(modData).forEach(function(fc) {
        if (rsRuvCodes.indexOf(fc) === -1) {
          filtered[fc] = modData[fc];
        }
      });

      if (Object.keys(filtered).length === 0) return;

      html += renderModuleSection(mod, filtered, days, absIdx, modifiedSet);
    });

    document.getElementById('repGrid').innerHTML = html ||
      '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x rep-empty-state-icon"></i>Aucune donnée pour cette semaine.</div>';
  }

  // ─── Edit mode toggle ───
  const toggleBtn = document.getElementById('repToggleEdit');
  toggleBtn.addEventListener('click', function() {
    editMode = !editMode;
    toggleBtn.classList.toggle('active', editMode);
    toggleBtn.innerHTML = editMode
      ? '<i class="bi bi-eye"></i> Lecture'
      : '<i class="bi bi-pencil-square"></i> Éditer';
    document.getElementById('repGrid').classList.toggle('rep-edit-mode', editMode);
    if (!editMode) hideEditPopover();
  });

  // ─── Cell click → edit popover ───
  document.getElementById('repGrid').addEventListener('click', function(e) {
    if (!editMode) return;
    const cell = e.target.closest('td.cell-day');
    if (!cell) return;

    const userId = cell.dataset.userId;
    const date   = cell.dataset.date;
    if (!userId || !date) return;

    editingCell = {
      assignation_id:  cell.dataset.assignationId || '',
      planning_id:     cell.dataset.planningId || '',
      user_id:         userId,
      date:            date,
      horaire_type_id: cell.dataset.horaireTypeId || '',
      module_id:       cell.dataset.moduleId || '',
      groupe_id:       cell.dataset.groupeId || '',
      etage_id:        cell.dataset.etageId || '',
      statut:          cell.dataset.statut || 'present',
      notes:           cell.dataset.notes || '',
      updated_at:      cell.dataset.updatedAt || '',
      user_name:       cell.dataset.userName || '',
      home_module_id:  cell.dataset.homeModuleId || '',
      cellEl:          cell,
    };

    showEditPopover(cell);
  });

  // ─── Populate module → étage/groupe cascading selects ───
  function populateModuleSelect() {
    const sel = document.getElementById('repEditModule');
    sel.innerHTML = '<option value="">— Aucun —</option>';
    (data.modules || []).forEach(function(m) {
      sel.innerHTML += '<option value="' + m.id + '">' + escapeHtml(m.code + ' — ' + m.nom) + '</option>';
    });
  }

  function populateGroupeSelect(moduleId) {
    const sel = document.getElementById('repEditGroupe');
    sel.innerHTML = '<option value="">— Aucun —</option>';
    if (!moduleId) return;

    const mod = (data.modules || []).find(m => m.id === moduleId);
    if (!mod) return;

    (mod.etages || []).forEach(function(et) {
      (et.groupes || []).forEach(function(gr) {
        sel.innerHTML += '<option value="' + gr.id + '" data-etage-id="' + et.id + '">'
          + escapeHtml(et.code + '-' + gr.code) + '</option>';
      });
      // If no groupes, show étage only (selectable as étage with no groupe)
      if (!et.groupes || et.groupes.length === 0) {
        sel.innerHTML += '<option value="" data-etage-id="' + et.id + '">'
          + escapeHtml(et.code) + ' (étage)</option>';
      }
    });
  }

  function populateHoraireSelect() {
    const sel = document.getElementById('repEditHoraire');
    sel.innerHTML = '<option value="">— Aucun —</option>';
    (data.horaires || []).forEach(function(h) {
      const time = (h.heure_debut || '').substring(0, 5) + '-' + (h.heure_fin || '').substring(0, 5);
      sel.innerHTML += '<option value="' + h.id + '">' + escapeHtml(h.code) + ' (' + time + ')</option>';
    });
  }

  // ─── Show / Hide edit popover (follows cell on scroll) ───
  let _popScrollHandler = null;

  function positionPopover(cellEl) {
    const pop = document.getElementById('repEditPopover');
    const rect = cellEl.getBoundingClientRect();
    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
    const scrollX = window.pageXOffset || document.documentElement.scrollLeft;

    let top = rect.bottom + scrollY + 4;
    let left = rect.left + scrollX;

    // Keep in viewport vertically
    if (rect.bottom + 310 > window.innerHeight) {
      top = rect.top + scrollY - 310;
    }
    // Keep in viewport horizontally
    if (rect.left + 260 > window.innerWidth) {
      left = window.innerWidth + scrollX - 270;
    }
    if (left < scrollX + 10) left = scrollX + 10;

    pop.style.top = top + 'px';
    pop.style.left = left + 'px';
  }

  function showEditPopover(cellEl) {
    const pop = document.getElementById('repEditPopover');

    // Title
    document.getElementById('repEditTitle').textContent =
      (editingCell.user_name || 'Employé') + ' — ' + editingCell.date;

    // Populate selects
    populateHoraireSelect();
    populateModuleSelect();

    // Set current values
    document.getElementById('repEditHoraire').value = editingCell.horaire_type_id || '';
    document.getElementById('repEditModule').value = editingCell.module_id || '';
    populateGroupeSelect(editingCell.module_id || '');
    document.getElementById('repEditGroupe').value = editingCell.groupe_id || '';
    document.getElementById('repEditStatut').value = editingCell.statut || 'present';
    document.getElementById('repEditNotes').value = editingCell.notes || '';

    // Show/hide delete button based on whether assignation exists
    document.getElementById('repEditDelete').style.display = editingCell.assignation_id ? '' : 'none';
    document.getElementById('repEditAbsent').style.display = editingCell.assignation_id ? '' : 'none';

    // Position and show
    pop.style.display = 'block';
    positionPopover(cellEl);

    // Track scroll to reposition
    if (_popScrollHandler) {
      window.removeEventListener('scroll', _popScrollHandler, true);
    }
    _popScrollHandler = function() { positionPopover(cellEl); };
    window.addEventListener('scroll', _popScrollHandler, true);
  }

  function hideEditPopover() {
    document.getElementById('repEditPopover').style.display = 'none';
    editingCell = null;
    if (_popScrollHandler) {
      window.removeEventListener('scroll', _popScrollHandler, true);
      _popScrollHandler = null;
    }
  }

  // Module change → update groupe select
  document.getElementById('repEditModule').addEventListener('change', function() {
    populateGroupeSelect(this.value);
  });

  // Close popover
  document.getElementById('repEditClose').addEventListener('click', hideEditPopover);

  // Close popover on outside click
  document.addEventListener('mousedown', function(e) {
    const pop = document.getElementById('repEditPopover');
    if (pop.style.display === 'none') return;
    if (pop.contains(e.target)) return;
    if (e.target.closest('td.cell-day')) return; // clicking another cell will reopen
    hideEditPopover();
  });

  // ─── Save cell ───
  document.getElementById('repEditSave').addEventListener('click', async function() {
    if (!editingCell) return;

    const groupeSel = document.getElementById('repEditGroupe');
    const selectedOpt = groupeSel.options[groupeSel.selectedIndex];
    const etageId = selectedOpt ? (selectedOpt.dataset.etageId || '') : '';

    const params = {
      assignation_id:  editingCell.assignation_id || '',
      planning_id:     editingCell.planning_id || '',
      user_id:         editingCell.user_id,
      date_jour:       editingCell.date,
      horaire_type_id: document.getElementById('repEditHoraire').value || null,
      module_id:       document.getElementById('repEditModule').value || null,
      groupe_id:       groupeSel.value || null,
      etage_id:        etageId || null,
      statut:          document.getElementById('repEditStatut').value,
      notes:           document.getElementById('repEditNotes').value,
      expected_updated_at: editingCell.updated_at || null,
    };

    this.disabled = true;
    const res = await adminApiPost('admin_save_repartition_cell', params);
    this.disabled = false;

    if (res.conflict) {
      toast('Conflit : cette cellule a été modifiée. Rechargement...', 'error');
      loadWeek(currentWeekISO);
      return;
    }

    if (!res.success) {
      toast(res.message || 'Erreur', 'error');
      return;
    }

    toast('Enregistré', 'success');
    hideEditPopover();
    loadWeek(currentWeekISO);
  });

  // ─── Delete cell ───
  document.getElementById('repEditDelete').addEventListener('click', async function() {
    if (!editingCell || !editingCell.assignation_id) return;
    if (!confirm('Supprimer cette assignation ?')) return;

    this.disabled = true;
    const res = await adminApiPost('admin_delete_repartition_cell', {
      assignation_id: editingCell.assignation_id,
    });
    this.disabled = false;

    if (!res.success) {
      toast(res.message || 'Erreur', 'error');
      return;
    }

    toast('Supprimé', 'success');
    hideEditPopover();
    loadWeek(currentWeekISO);
  });

  // ─── Mark absent button → open absence modal ───
  let absenceModal = null;
  document.getElementById('repEditAbsent').addEventListener('click', function() {
    if (!editingCell || !editingCell.assignation_id) return;
    document.getElementById('repAbsDebut').value = editingCell.date;
    document.getElementById('repAbsFin').value = editingCell.date;
    document.getElementById('repAbsMulti').checked = false;
    document.getElementById('repAbsMultiDates').style.display = 'none';
    document.getElementById('repAbsMotif').value = '';
    if (!absenceModal) {
      absenceModal = new bootstrap.Modal(document.getElementById('repAbsenceModal'));
    }
    absenceModal.show();
  });

  // Toggle multi-day dates
  document.getElementById('repAbsMulti').addEventListener('change', function() {
    document.getElementById('repAbsMultiDates').style.display = this.checked ? 'block' : 'none';
  });

  // Save absence
  document.getElementById('repAbsSave').addEventListener('click', async function() {
    if (!editingCell || !editingCell.assignation_id) return;

    const multi = document.getElementById('repAbsMulti').checked;
    const params = {
      assignation_id: editingCell.assignation_id,
      absence_type: document.getElementById('repAbsType').value,
      motif: document.getElementById('repAbsMotif').value,
    };

    if (multi) {
      params.date_debut = document.getElementById('repAbsDebut').value;
      params.date_fin = document.getElementById('repAbsFin').value;
      if (!params.date_debut || !params.date_fin) {
        toast('Dates requises', 'error');
        return;
      }
    }

    this.disabled = true;
    const res = await adminApiPost('admin_mark_absent_repartition', params);
    this.disabled = false;

    if (!res.success) {
      toast(res.message || 'Erreur', 'error');
      return;
    }

    toast('Absence enregistrée', 'success');
    if (absenceModal) absenceModal.hide();
    hideEditPopover();
    loadWeek(currentWeekISO);
  });

  // ─── Drag & Drop ───
  let dragData = null;

  document.getElementById('repGrid').addEventListener('dragstart', function(e) {
    if (!editMode) { e.preventDefault(); return; }
    const cell = e.target.closest('td.cell-day');
    if (!cell || !cell.dataset.assignationId) { e.preventDefault(); return; }

    cell.classList.add('rep-dragging');
    dragData = {
      assignation_id:  cell.dataset.assignationId,
      planning_id:     cell.dataset.planningId,
      user_id:         cell.dataset.userId,
      date:            cell.dataset.date,
      horaire_type_id: cell.dataset.horaireTypeId,
      module_id:       cell.dataset.moduleId,
      groupe_id:       cell.dataset.groupeId,
      etage_id:        cell.dataset.etageId,
      statut:          cell.dataset.statut,
      notes:           cell.dataset.notes,
      updated_at:      cell.dataset.updatedAt,
      user_name:       cell.dataset.userName,
    };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragData.assignation_id);
  });

  document.getElementById('repGrid').addEventListener('dragend', function(e) {
    const cell = e.target.closest('td.cell-day');
    if (cell) cell.classList.remove('rep-dragging');
    // Remove all drag-over highlights
    document.querySelectorAll('.rep-drag-over, .rep-drag-over-mod').forEach(el => {
      el.classList.remove('rep-drag-over', 'rep-drag-over-mod');
    });
    dragData = null;
  });

  document.getElementById('repGrid').addEventListener('dragover', function(e) {
    if (!editMode || !dragData) return;
    const modHeader = e.target.closest('.rep-module-header[data-drop-module-id]');
    const cell = e.target.closest('td.cell-day');
    if (modHeader || cell) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    }
  });

  document.getElementById('repGrid').addEventListener('dragenter', function(e) {
    if (!editMode || !dragData) return;
    const modHeader = e.target.closest('.rep-module-header[data-drop-module-id]');
    const cell = e.target.closest('td.cell-day');
    // Clear previous highlights
    document.querySelectorAll('.rep-drag-over, .rep-drag-over-mod').forEach(el => {
      el.classList.remove('rep-drag-over', 'rep-drag-over-mod');
    });
    if (modHeader) modHeader.classList.add('rep-drag-over-mod');
    if (cell) cell.classList.add('rep-drag-over');
  });

  document.getElementById('repGrid').addEventListener('dragleave', function(e) {
    const modHeader = e.target.closest('.rep-module-header');
    if (modHeader) modHeader.classList.remove('rep-drag-over-mod');
    const cell = e.target.closest('td.cell-day');
    if (cell) cell.classList.remove('rep-drag-over');
  });

  document.getElementById('repGrid').addEventListener('drop', async function(e) {
    e.preventDefault();
    if (!editMode || !dragData) return;

    // Remove highlights
    document.querySelectorAll('.rep-drag-over, .rep-drag-over-mod').forEach(el => {
      el.classList.remove('rep-drag-over', 'rep-drag-over-mod');
    });

    // Determine target module
    let targetModuleId = null;
    let targetModuleCode = '';
    const modHeader = e.target.closest('.rep-module-header[data-drop-module-id]');
    const cell = e.target.closest('td.cell-day');

    if (modHeader) {
      targetModuleId = modHeader.dataset.dropModuleId;
      targetModuleCode = modHeader.dataset.dropModuleCode;
    } else if (cell) {
      // Find the module section this cell belongs to
      const section = cell.closest('.rep-module-section');
      if (section) targetModuleId = section.dataset.sectionModuleId;
    }

    if (!targetModuleId || targetModuleId === dragData.module_id) {
      dragData = null;
      return; // same module, nothing to do
    }

    // Find target module and its étages/groupes
    const targetMod = (data.modules || []).find(m => m.id === targetModuleId);
    if (!targetMod) { dragData = null; return; }

    // Build list of étage-groupe options
    let groupeOptions = [];
    (targetMod.etages || []).forEach(function(et) {
      if (et.groupes && et.groupes.length > 0) {
        et.groupes.forEach(function(gr) {
          groupeOptions.push({ etageId: et.id, groupeId: gr.id, label: et.code + '-' + gr.code });
        });
      } else {
        groupeOptions.push({ etageId: et.id, groupeId: null, label: et.code + ' (étage)' });
      }
    });

    let chosenEtageId = null;
    let chosenGroupeId = null;

    if (groupeOptions.length === 0) {
      // Module has no étages/groupes — just move to module
    } else if (groupeOptions.length === 1) {
      // Only one option — auto-select
      chosenEtageId = groupeOptions[0].etageId;
      chosenGroupeId = groupeOptions[0].groupeId;
    } else {
      // Multiple options — quick prompt
      const labels = groupeOptions.map((g, i) => (i + 1) + '. ' + g.label).join('\n');
      const choice = prompt(
        'Déplacer ' + dragData.user_name + ' vers ' + targetMod.code + '\n\nChoisir étage/groupe :\n' + labels + '\n\n(Entrez le numéro)'
      );
      if (!choice) { dragData = null; return; }
      const idx = parseInt(choice) - 1;
      if (idx < 0 || idx >= groupeOptions.length) { dragData = null; return; }
      chosenEtageId = groupeOptions[idx].etageId;
      chosenGroupeId = groupeOptions[idx].groupeId;
    }

    // Save the move
    const params = {
      assignation_id:  dragData.assignation_id,
      planning_id:     dragData.planning_id,
      user_id:         dragData.user_id,
      date_jour:       dragData.date,
      horaire_type_id: dragData.horaire_type_id || null,
      module_id:       targetModuleId,
      groupe_id:       chosenGroupeId,
      etage_id:        chosenEtageId,
      statut:          dragData.statut || 'present',
      notes:           dragData.notes || '',
      expected_updated_at: dragData.updated_at || null,
    };

    const res = await adminApiPost('admin_save_repartition_cell', params);
    dragData = null;

    if (res.conflict) {
      toast('Conflit détecté. Rechargement...', 'error');
      loadWeek(currentWeekISO);
      return;
    }
    if (!res.success) {
      toast(res.message || 'Erreur', 'error');
      return;
    }

    toast('Déplacé vers ' + targetMod.code, 'success');
    loadWeek(currentWeekISO);
  });

  // ─── Event listeners — navigation ───
  document.getElementById('repPrevWeek').addEventListener('click', function() {
    if (!currentWeekISO) return;
    var mon = getMondayOfISOWeek(currentWeekISO);
    if (mon) {
      mon.setDate(mon.getDate() - 7);
      loadWeek(dateToStr(mon));
    }
  });

  document.getElementById('repNextWeek').addEventListener('click', function() {
    if (!currentWeekISO) return;
    var mon = getMondayOfISOWeek(currentWeekISO);
    if (mon) {
      mon.setDate(mon.getDate() + 7);
      loadWeek(dateToStr(mon));
    }
  });

  document.getElementById('repToday').addEventListener('click', function() {
    loadWeek(null);
  });

  document.getElementById('repDatePicker').addEventListener('change', function(e) {
    if (e.target.value) loadWeek(e.target.value);
  });

  document.getElementById('repPrint').addEventListener('click', function() {
    window.print();
  });

  // ─── Init — render synchronously from injected data ───
  function initRepartitionPage() {
    renderGrid();
  }

  window.initRepartitionPage = initRepartitionPage;

})();
</script>
