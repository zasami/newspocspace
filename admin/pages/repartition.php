<?php
// ─── Données serveur — semaine courante ──────────────────────────────────────
$dto = new DateTime();
$dow = (int)$dto->format('N');
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
            hm.id AS home_module_id, hm.code AS home_module_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules hm ON hm.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY f.ordre, u.nom"
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
                pa.horaire_type_id, pa.module_id, pa.groupe_id, pa.etage_id,
                pa.statut, pa.notes, pa.updated_at,
                u.prenom AS user_prenom, u.nom AS user_nom,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.code AS module_code,
                g.code AS groupe_code,
                e.code AS etage_code
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN groupes g ON g.id = pa.groupe_id
         LEFT JOIN etages e ON e.id = pa.etage_id
         WHERE pa.planning_id IN ($phPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
        $qParams
    );
}

// Modified IDs
$repModifiedIds = [];
if ($repAssignments) {
    $aIds = array_column($repAssignments, 'assignation_id');
    $phA = implode(',', array_fill(0, count($aIds), '?'));
    $modRows = Db::fetchAll("SELECT DISTINCT planning_assignation_id FROM planning_modifications WHERE planning_assignation_id IN ($phA)", $aIds);
    $repModifiedIds = array_column($modRows, 'planning_assignation_id');
}

// Absences
$repAbsences = Db::fetchAll(
    "SELECT user_id, date_debut, date_fin, type, motif FROM absences WHERE statut = 'valide' AND date_debut <= ? AND date_fin >= ? ORDER BY date_debut",
    [$weekEnd, $weekStart]
);

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
    <button class="btn btn-outline-secondary btn-sm" id="repPrevWeek" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
    <h6 class="mb-0 fw-semibold rep-week-label" id="repWeekLabel"><?= h($weekLabel) ?></h6>
    <button class="btn btn-outline-secondary btn-sm" id="repNextWeek" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
    <button class="btn btn-outline-primary btn-sm" id="repToday">Aujourd'hui</button>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="btn-group btn-group-sm" id="repViewToggle">
      <button class="btn btn-outline-secondary active" data-view="week" title="Vue semaine"><i class="bi bi-calendar-week"></i></button>
      <button class="btn btn-outline-secondary" data-view="day" title="Vue jour"><i class="bi bi-calendar-day"></i></button>
    </div>
    <button class="btn btn-outline-secondary btn-sm" id="repToggleEdit" title="Mode édition"><i class="bi bi-pencil-square"></i> Éditer</button>
    <button class="btn btn-outline-secondary btn-sm" id="repLegendBtn" title="Légende horaires"><i class="bi bi-info-circle"></i></button>
    <input type="date" class="form-control form-control-sm" style="width:160px" id="repDatePicker" value="<?= h($weekStart) ?>">
    <button class="btn btn-outline-secondary btn-sm" id="repPrint" title="Imprimer"><i class="bi bi-printer"></i></button>
  </div>
</div>

<div id="repPlanningStatus" class="mb-2" style="font-size:.8rem">
  <?php if (!empty($repPlannings)):
    $colors = ['brouillon' => 'secondary', 'provisoire' => 'info', 'final' => 'success'];
    $badges = array_map(fn($p) => '<span class="badge bg-' . ($colors[$p['statut']] ?? 'secondary') . ' me-1">' . h($p['mois_annee']) . ' : ' . h($p['statut']) . '</span>', $repPlannings);
    echo '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' . implode('', $badges);
  else: ?>
    <span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette période</span>
  <?php endif; ?>
</div>

<!-- Légende modal -->
<div class="modal fade" id="repLegendModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-clock me-1"></i>Légende des horaires</h6>
        <button type="button" class="btn-close" style="font-size:.6rem" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <?php foreach ($repHoraires as $h): ?>
          <div class="col-6 col-md-4">
            <div class="rep-legend-card">
              <span class="rep-badge" style="background:<?= htmlspecialchars($h['couleur'] ?? '#6c757d') ?>;font-size:.75rem;padding:2px 8px"><?= htmlspecialchars($h['code']) ?></span>
              <span class="rep-legend-card-time"><?= htmlspecialchars(substr($h['heure_debut'] ?? '', 0, 5)) ?> - <?= htmlspecialchars(substr($h['heure_fin'] ?? '', 0, 5)) ?></span>
              <?php if (!empty($h['duree_effective'])): ?>
              <span class="rep-legend-card-dur"><?= htmlspecialchars($h['duree_effective']) ?>h eff.</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="repGrid" class="rep-grid-container">
  <div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Chargement...</div>
</div>

<!-- Edit popover -->
<div id="repEditPopover" class="rep-edit-popover" style="display:none;">
  <div class="rep-edit-popover-header">
    <span id="repEditTitle"></span>
    <button type="button" class="btn-close" style="font-size:.6rem;padding:.3rem" id="repEditClose"></button>
  </div>
  <div class="rep-edit-popover-body">
    <div class="mb-2"><label class="rep-edit-label">Horaire</label><select id="repEditHoraire" class="form-select form-select-sm"></select></div>
    <div class="mb-2"><label class="rep-edit-label">Module</label><select id="repEditModule" class="form-select form-select-sm"></select></div>
    <div class="mb-2"><label class="rep-edit-label">Étage / Groupe</label><select id="repEditGroupe" class="form-select form-select-sm"></select></div>
    <div class="mb-2"><label class="rep-edit-label">Statut</label>
      <select id="repEditStatut" class="form-select form-select-sm">
        <option value="present">Présent</option><option value="absent">Absent</option>
        <option value="remplace">Remplacé</option><option value="interim">Intérim</option>
        <option value="entraide">Entraide</option><option value="repos">Repos</option>
        <option value="vacant">Vacant</option>
      </select>
    </div>
    <div class="mb-2"><label class="rep-edit-label">Notes</label><input type="text" id="repEditNotes" class="form-control form-control-sm" maxlength="500"></div>
    <div class="d-flex gap-1">
      <button class="btn btn-sm btn-success flex-fill" id="repEditSave"><i class="bi bi-check-lg"></i> OK</button>
      <button class="btn btn-sm btn-outline-warning" id="repEditAbsent" title="Marquer absent"><i class="bi bi-person-x"></i></button>
      <button class="btn btn-sm btn-outline-danger" id="repEditDelete" title="Supprimer"><i class="bi bi-trash"></i></button>
    </div>
  </div>
</div>

<!-- Move to module modal -->
<div class="modal fade" id="repMoveModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-arrows-move me-1"></i><span id="repMoveTitle">Déplacer</span></h6>
        <button type="button" class="btn-close" style="font-size:.6rem" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-2">
        <p class="mb-2" style="font-size:.8rem;color:#555" id="repMoveSubtitle"></p>
        <div id="repMoveOptions" class="d-flex flex-column gap-1"></div>
      </div>
      <div class="modal-footer py-1">
        <button class="btn btn-sm btn-light" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Absence modal -->
<div class="modal fade" id="repAbsenceModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-person-x me-1"></i>Marquer absent</h6><button type="button" class="btn-close" style="font-size:.6rem" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="rep-edit-label">Type</label>
          <select id="repAbsType" class="form-select form-select-sm">
            <option value="maladie">Maladie</option><option value="vacances">Vacances</option>
            <option value="accident">Accident</option><option value="formation">Formation</option>
            <option value="conge_special">Congé spécial</option><option value="autre">Autre</option>
          </select>
        </div>
        <div class="mb-2"><label class="rep-edit-label">Motif</label><input type="text" id="repAbsMotif" class="form-control form-control-sm" maxlength="500"></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="repAbsMulti"><label class="form-check-label rep-edit-label" for="repAbsMulti">Plusieurs jours</label></div>
        <div id="repAbsMultiDates" style="display:none"><div class="row g-1"><div class="col-6"><label class="rep-edit-label">Du</label><input type="date" id="repAbsDebut" class="form-control form-control-sm"></div><div class="col-6"><label class="rep-edit-label">Au</label><input type="date" id="repAbsFin" class="form-control form-control-sm"></div></div></div>
      </div>
      <div class="modal-footer py-1"><button class="btn btn-sm btn-warning" id="repAbsSave"><i class="bi bi-check-lg"></i> Confirmer</button></div>
    </div>
  </div>
</div>

<style>
.rep-grid-container { padding-bottom: 10px; }
.rep-legend-item { display: inline-flex; align-items: center; gap: 3px; }
.rep-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: .62rem; font-weight: 600; color: #fff; white-space: nowrap; line-height: 1.3; }

/* Module sections — each scrollable independently */
.rep-module-section { margin-bottom: 1.2rem; }
.rep-module-inner { min-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; cursor: grab; }
.rep-module-inner:focus { outline: none; box-shadow: 0 0 0 2px rgba(45,74,67,.3); border-radius: 0 0 4px 4px; }
.rep-module-header { padding: .4rem .75rem; font-weight: 600; font-size: .85rem; color: #fff; border-radius: 4px 4px 0 0; display: flex; align-items: center; gap: .5rem; }
.rep-module-header .badge { font-size: .68rem; background: rgba(255,255,255,.25); }

/* Table — 3 sub-columns per day: Nom | Horaire | Étage */
.rep-table { width: 100%; min-width: 1600px; border-collapse: separate; border-spacing: 0; font-size: .74rem; }
.rep-table th, .rep-table td { border: 1px solid #dee2e6; padding: .2rem .3rem; vertical-align: middle; background: #fff; }
.rep-table thead th { background: #fff; font-weight: 600; text-align: center; position: sticky; top: 0; z-index: 2; font-size: .7rem; }
.rep-table th.col-fn { width: 70px; position: sticky; left: 0; z-index: 4; background: #fff; }
.rep-table th.col-slot { width: 55px; position: sticky; left: 70px; z-index: 4; background: #fff; }
.rep-table th.col-day-head { text-align: center; font-size: .72rem; border-bottom: 0; }
.rep-table th.col-day-head.weekend { background: #fff; }
.rep-table th.col-sub-nom { width: 140px; font-size: .64rem; color: #888; font-weight: 500; }
/* Day view — all modules aligned left in a column */
.rep-grid-container.rep-grid-day { display: flex; flex-direction: column; align-items: flex-start; }
.rep-day-section { width: auto; max-width: 100%; }
.rep-day-section .rep-module-inner { min-width: 0; overflow-x: visible; cursor: default; }
.rep-table.rep-day-view { min-width: 0; width: auto; }
.rep-table.rep-day-view th.col-fn { width: 80px; }
.rep-table.rep-day-view th.col-slot { width: 55px; }
.rep-table.rep-day-view th.col-sub-nom { width: 200px; }
.rep-table.rep-day-view td.cell-nom { max-width: 200px; }
.rep-table.rep-day-view th.col-sub-hor { width: 50px; }
.rep-table.rep-day-view th.col-sub-et { width: 50px; }
.rep-table.rep-day-view th.col-day-head { font-size: .82rem; padding: .4rem .5rem; }
.rep-table th.col-sub-hor { width: 38px; font-size: .64rem; color: #888; font-weight: 500; text-align: center; }
.rep-table th.col-sub-et { width: 35px; font-size: .64rem; color: #888; font-weight: 500; text-align: center; }

/* Day group border — thicker left border on first col of each day */
.rep-table td.day-first, .rep-table th.day-first { border-left: 2px solid #adb5bd; }

/* Function cell — sticky left */
.rep-table td.cell-fn { font-weight: 700; font-size: .68rem; color: #333; background: #fff; text-align: center; border-right: 2px solid #ccc; position: sticky; left: 0; z-index: 1; }
.rep-table td.cell-slot { font-size: .66rem; font-weight: 600; color: #555; background: #fff; text-align: center; white-space: nowrap; position: sticky; left: 70px; z-index: 1; }
.rep-table tr.fn-group-first td { border-top: 2px solid #adb5bd; }

/* Nom cell */
.rep-table td.cell-nom { font-size: .72rem; font-weight: 600; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; position: relative; }
.rep-table td.cell-nom.weekend { background: #fff; }
.rep-table td.cell-nom.empty-cell { color: #d0d0d0; font-weight: 400; }
.rep-table td.cell-nom.rep-absent-cell { text-decoration: line-through; opacity: .55; }

/* Horaire cell */
.rep-table td.cell-hor { text-align: center; padding: .15rem .1rem; }
.rep-table td.cell-hor.weekend { background: #fff; }

/* Étage cell */
.rep-table td.cell-et { text-align: center; font-size: .66rem; font-weight: 600; color: #555; }
.rep-table td.cell-et.weekend { background: #fff; }

/* Modified indicator on nom cell */
.rep-table td.cell-nom.rep-modified::after { content: ''; position: absolute; top: 2px; right: 2px; width: 6px; height: 6px; background: #FF9800; border-radius: 50%; }

/* Absence icon */
.rep-abs-icon { width: 14px; height: 14px; vertical-align: middle; opacity: .7; }

/* Notes tooltip indicator */
.rep-note-dot { color: #999; font-size: .6rem; vertical-align: super; cursor: help; }

/* Edit mode — all 3 cells of a day group are clickable */
.rep-edit-mode .rep-table td.cell-nom,
.rep-edit-mode .rep-table td.cell-hor,
.rep-edit-mode .rep-table td.cell-et { cursor: pointer; transition: background .15s; }
.rep-edit-mode .rep-table td.cell-nom:hover,
.rep-edit-mode .rep-table td.cell-hor:hover,
.rep-edit-mode .rep-table td.cell-et:hover { background: rgba(188,210,203,.15) !important; }
#repToggleEdit.active { background: #2d4a43; border-color: #2d4a43; color: #fff; }

/* Drag & drop — on nom cell */
.rep-edit-mode .rep-table td.cell-nom[draggable="true"] { cursor: grab; }
.rep-table td.cell-nom.rep-drag-over { background: rgba(188,210,203,.2) !important; outline: 2px dashed #2d4a43; outline-offset: -2px; }
.rep-module-header.rep-drag-over-mod { outline: 3px dashed rgba(255,255,255,.6); outline-offset: -3px; opacity: .85; }
.rep-table td.cell-nom.rep-dragging { opacity: .4; }

/* Edit popover */
.rep-edit-popover { position: absolute; z-index: 1050; background: #fff; border: 1px solid #dee2e6; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,.15); width: 260px; font-size: .8rem; }
.rep-edit-popover-header { display: flex; align-items: center; justify-content: space-between; padding: .4rem .6rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 8px 8px 0 0; font-weight: 600; font-size: .76rem; }
.rep-edit-popover-body { padding: .5rem .6rem; }
.rep-edit-label { font-size: .68rem; font-weight: 600; color: #555; margin-bottom: 2px; display: block; }

/* Legend cards */
.rep-legend-card { display: flex; flex-direction: column; align-items: center; gap: 3px; padding: .5rem .4rem; border: 1px solid #e9ecef; border-radius: 8px; background: #fdfdfd; text-align: center; }
.rep-legend-card-time { font-size: .78rem; font-weight: 600; color: #333; }
.rep-legend-card-dur { font-size: .65rem; color: #888; }

/* Move modal option buttons */
.rep-move-opt { display: flex; align-items: center; gap: .5rem; padding: .45rem .75rem; border: 1px solid #dee2e6; border-radius: 8px; background: #fdfdfd; cursor: pointer; font-size: .82rem; font-weight: 500; transition: all .15s; }
.rep-move-opt:hover { background: #bcd2cb; border-color: #2d4a43; color: #2d4a43; }
.rep-move-opt .rep-move-code { font-weight: 700; min-width: 40px; }

/* Module colors */
.rep-mod-M1 { background: #2196F3; } .rep-mod-M2 { background: #4CAF50; }
.rep-mod-M3 { background: #FF9800; } .rep-mod-M4 { background: #9C27B0; }
.rep-mod-NUIT { background: #37474F; } .rep-mod-POOL { background: #795548; }
.rep-mod-RS { background: #607D8B; } .rep-mod-DEFAULT { background: #6c757d; }

@media print {
  .admin-sidebar, .admin-topbar, .sidebar-overlay, #repPrevWeek, #repNextWeek, #repToday, #repDatePicker, #repPrint, #repToggleEdit, #repPlanningStatus, #repEditPopover, #repAbsenceModal, .topbar-search, .topbar-right { display: none !important; }
  .admin-main { margin-left: 0 !important; } .admin-content { padding: .5rem !important; }
  .rep-grid-container { overflow: visible; } .rep-module-inner { overflow: visible; min-width: 0; } .rep-table { min-width: 0; font-size: .64rem; }
  .rep-module-section { page-break-inside: avoid; }
  .rep-badge, .rep-module-header, .rep-table td.cell-fn, .rep-table td.cell-nom.weekend, .rep-table td.cell-hor.weekend, .rep-table td.cell-et.weekend, .rep-table th.col-day-head.weekend { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>

<script<?= nonce() ?>>
(function() {

  let currentWeekISO = <?= json_encode($weekIso) ?>;
  let editMode = false;
  let editingCell = null;
  let viewMode = 'week'; // 'week' or 'day'
  let selectedDay = dateToStr(new Date());
  let dragData = null;

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
    modified_ids: <?= json_encode($repModifiedIds, JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    absences: <?= json_encode(array_values($repAbsences), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
  };

  // ─── Helpers ───
  function dateToStr(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function getMondayOfISOWeek(iso) {
    const m = iso.match(/^(\d{4})-W(\d{2})$/);
    if (!m) return null;
    const jan4 = new Date(parseInt(m[1]), 0, 4);
    const dow = jan4.getDay() || 7;
    const w1Mon = new Date(jan4);
    w1Mon.setDate(jan4.getDate() - dow + 1);
    const t = new Date(w1Mon);
    t.setDate(w1Mon.getDate() + (parseInt(m[2]) - 1) * 7);
    return t;
  }

  // Horaire sort key (by start time)
  const horaireOrder = {};
  (data.horaires || []).forEach(function(h) {
    horaireOrder[h.id] = h.heure_debut || '99:99';
  });

  // ─── Build absence index ───
  function buildAbsIdx() {
    const idx = {};
    (data.absences || []).forEach(function(a) {
      const s = new Date(a.date_debut), e = new Date(a.date_fin);
      for (let d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
        idx[a.user_id + '|' + dateToStr(d)] = a.type;
      }
    });
    return idx;
  }

  // ─── Build slot-based structure ───
  function buildSections() {
    const days = data.days || [];
    const dateList = days.map(d => d.date);
    const modifiedSet = new Set(data.modified_ids || []);
    const absIdx = buildAbsIdx();

    // Group assignments by assigned module_id
    const byMod = {};
    (data.assignments || []).forEach(function(a) {
      const mid = a.module_id || '_NONE';
      if (!byMod[mid]) byMod[mid] = [];
      byMod[mid].push(a);
    });

    // Get function metadata
    const fnMap = {};
    (data.fonctions || []).forEach(function(f) { fnMap[f.code] = f; });

    // Evening horaire codes (S3, S4, D4, C2)
    const soirCodes = new Set(['S3', 'S4', 'D4', 'C2']);

    function buildModuleSlots(mod, assigns) {
      // Group by fonction_code
      const byFn = {};
      assigns.forEach(function(a) {
        const fc = a.fonction_code || '_NONE';
        if (!byFn[fc]) byFn[fc] = [];
        byFn[fc].push(a);
      });

      // Sort function codes by ordre
      const fnCodes = Object.keys(byFn).sort(function(a, b) {
        return ((fnMap[a] || {}).ordre || 99) - ((fnMap[b] || {}).ordre || 99);
      });

      const sections = [];

      fnCodes.forEach(function(fc) {
        const fnAssigns = byFn[fc];
        const fnInfo = fnMap[fc] || { nom: fc, ordre: 99 };
        let slots = [];

        if (fc === 'AS' && mod && mod.etages && mod.etages.length > 0) {
          // AS: one slot per étage-groupe + soir slot
          const usedIds = new Set();

          mod.etages.forEach(function(et) {
            (et.groupes || []).forEach(function(gr) {
              const slotDays = {};
              dateList.forEach(function(dt) {
                // Find assignment for this étage-groupe on this date
                const match = fnAssigns.find(function(a) {
                  return a.date_jour === dt && a.groupe_id === gr.id && !soirCodes.has(a.horaire_code);
                });
                if (match) {
                  slotDays[dt] = match;
                  usedIds.add(match.assignation_id);
                }
              });
              slots.push({ label: et.code.replace('E', '') + '-' + gr.code.replace(/^\d+-/, ''), days: slotDays });
            });
          });

          // Soir slot(s): remaining assignments not matched to a groupe
          const soirAssigns = fnAssigns.filter(a => !usedIds.has(a.assignation_id));
          if (soirAssigns.length > 0) {
            // Group soir by day, may have multiple per day
            const soirByDay = {};
            soirAssigns.forEach(function(a) {
              if (!soirByDay[a.date_jour]) soirByDay[a.date_jour] = [];
              soirByDay[a.date_jour].push(a);
            });
            const maxSoir = Math.max(...Object.values(soirByDay).map(arr => arr.length), 0);
            for (let s = 0; s < maxSoir; s++) {
              const slotDays = {};
              dateList.forEach(function(dt) {
                if (soirByDay[dt] && soirByDay[dt][s]) slotDays[dt] = soirByDay[dt][s];
              });
              slots.push({ label: 'Soir' + (maxSoir > 1 ? ' ' + (s + 1) : ''), days: slotDays });
            }
          }
        } else {
          // Generic: determine slots by max per day, sorted by horaire start
          const byDay = {};
          dateList.forEach(function(dt) {
            byDay[dt] = fnAssigns.filter(a => a.date_jour === dt)
              .sort(function(a, b) {
                return (horaireOrder[a.horaire_type_id] || '99').localeCompare(horaireOrder[b.horaire_type_id] || '99');
              });
          });
          const maxSlots = Math.max(...dateList.map(dt => (byDay[dt] || []).length), 0);
          for (let s = 0; s < maxSlots; s++) {
            const slotDays = {};
            dateList.forEach(function(dt) {
              if (byDay[dt] && byDay[dt][s]) slotDays[dt] = byDay[dt][s];
            });
            slots.push({ label: maxSlots > 1 ? String(s + 1) : '', days: slotDays });
          }
        }

        if (slots.length > 0) {
          sections.push({ code: fc, nom: fnInfo.nom || fc, slots: slots });
        }
      });

      return sections;
    }

    // Build ordered module sections
    const result = [];
    const modules = data.modules || [];

    // RS/RUV section (collect RS + RUV from all modules)
    const rsAssigns = (data.assignments || []).filter(a => a.fonction_code === 'RS' || a.fonction_code === 'RUV');
    if (rsAssigns.length > 0) {
      const rsSections = buildModuleSlots(null, rsAssigns);
      result.push({ module: { id: '', code: 'RS', nom: 'RS / RUVs', etages: [] }, functions: rsSections });
    }

    // Regular modules
    modules.forEach(function(mod) {
      const assigns = (byMod[mod.id] || []).filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
      if (assigns.length === 0) return;
      const fnSections = buildModuleSlots(mod, assigns);
      if (fnSections.length > 0) {
        result.push({ module: mod, functions: fnSections });
      }
    });

    // Unassigned (no module)
    if (byMod['_NONE']) {
      const noneAssigns = byMod['_NONE'].filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
      if (noneAssigns.length > 0) {
        const fnSections = buildModuleSlots(null, noneAssigns);
        result.push({ module: { id: '', code: 'POOL', nom: 'Pool / Non assigné', etages: [] }, functions: fnSections });
      }
    }

    return { sections: result, modifiedSet, absIdx };
  }

  // ─── Build data attributes string for a cell ───
  function cellDataAttrs(a, date) {
    let s = ' data-date="' + date + '"';
    if (a) {
      s += ' data-assignation-id="' + (a.assignation_id || '') + '"';
      s += ' data-planning-id="' + (a.planning_id || '') + '"';
      s += ' data-user-id="' + (a.user_id || '') + '"';
      s += ' data-horaire-type-id="' + (a.horaire_type_id || '') + '"';
      s += ' data-module-id="' + (a.module_id || '') + '"';
      s += ' data-groupe-id="' + (a.groupe_id || '') + '"';
      s += ' data-etage-id="' + (a.etage_id || '') + '"';
      s += ' data-statut="' + (a.statut || 'present') + '"';
      s += ' data-notes="' + escapeHtml(a.notes || '') + '"';
      s += ' data-updated-at="' + (a.updated_at || '') + '"';
      s += ' data-user-name="' + escapeHtml((a.user_prenom || '') + ' ' + (a.user_nom || '')) + '"';
    }
    return s;
  }

  // French day+date labels for header
  const frFullDays = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
  const frMonthsFull = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

  function dayHeaderLabel(d) {
    const dt = new Date(d.date);
    const dow = (dt.getDay() + 6) % 7; // 0=Mon
    const day = dt.getDate();
    const mon = frMonthsFull[dt.getMonth() + 1];
    const yr = dt.getFullYear();
    return frFullDays[dow] + ', ' + day + ' ' + mon + ' ' + yr;
  }

  // ─── Render grid — 3 sub-columns per day ───
  function renderGrid() {
    const { sections, modifiedSet, absIdx } = buildSections();
    let days = data.days || [];
    const isDayView = viewMode === 'day';
    if (isDayView && selectedDay) {
      days = days.filter(d => d.date === selectedDay);
      if (!days.length && (data.days || []).length) days = [data.days[0]];
    }
    if (sections.length === 0) {
      document.getElementById('repGrid').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i>Aucune donnée pour cette semaine.</div>';
      return;
    }

    const modColors = { M1:'rep-mod-M1', M2:'rep-mod-M2', M3:'rep-mod-M3', M4:'rep-mod-M4', NUIT:'rep-mod-NUIT', POOL:'rep-mod-POOL', RS:'rep-mod-RS' };

    let html = '';
    sections.forEach(function(sec) {
      const mod = sec.module;
      const colorCls = modColors[mod.code] || 'rep-mod-DEFAULT';
      let totalSlots = 0;
      sec.functions.forEach(fn => totalSlots += fn.slots.length);

      html += '<div class="rep-module-section' + (isDayView ? ' rep-day-section' : '') + '" data-section-module-id="' + (mod.id || '') + '">';
      html += '<div class="rep-module-header ' + colorCls + '" data-drop-module-id="' + (mod.id || '') + '" data-drop-module-code="' + escapeHtml(mod.code) + '">';
      html += '<i class="bi bi-building"></i> ' + escapeHtml(mod.nom || mod.code);
      html += ' <span class="badge">' + totalSlots + ' poste(s)</span></div>';
      html += '<div class="rep-module-inner">';

      html += '<table class="rep-table' + (isDayView ? ' rep-day-view' : '') + '">';

      // ── 2-row header: day names (colspan=3) then sub-headers ──
      html += '<thead><tr>';
      html += '<th class="col-fn" rowspan="2">Fonction</th>';
      html += '<th class="col-slot" rowspan="2">Poste</th>';
      days.forEach(function(d) {
        const weCls = d.is_weekend ? ' weekend' : '';
        html += '<th class="col-day-head day-first' + weCls + '" colspan="3">' + dayHeaderLabel(d) + '</th>';
      });
      html += '</tr><tr>';
      days.forEach(function(d) {
        html += '<th class="col-sub-nom day-first"></th>';
        html += '<th class="col-sub-hor">Horaire</th>';
        html += '<th class="col-sub-et">Étage</th>';
      });
      html += '</tr></thead><tbody>';

      // ── Data rows ──
      sec.functions.forEach(function(fn) {
        fn.slots.forEach(function(slot, si) {
          const trCls = si === 0 ? ' class="fn-group-first"' : '';
          html += '<tr' + trCls + '>';

          if (si === 0) {
            html += '<td class="cell-fn" rowspan="' + fn.slots.length + '">' + escapeHtml(fn.nom) + '</td>';
          }

          html += '<td class="cell-slot">' + escapeHtml(slot.label) + '</td>';

          days.forEach(function(d) {
            const a = slot.days[d.date] || null;
            const we = d.is_weekend ? ' weekend' : '';
            const attrs = cellDataAttrs(a, d.date);
            const modCls = a && a.assignation_id && modifiedSet.has(a.assignation_id) ? ' rep-modified' : '';
            const absCls = a && a.statut === 'absent' ? ' rep-absent-cell' : '';

            // ── Nom cell ──
            let nomContent = '';
            if (a) {
              const absType = absIdx[a.user_id + '|' + a.date_jour] || null;
              if (a.statut === 'absent' && absType) {
                if (absType === 'vacances') nomContent += '<img src="/spocspace/assets/webp/vacances_1.webp" class="rep-abs-icon"> ';
                else { const icons = {maladie:'bi-bandaid',accident:'bi-exclamation-triangle',formation:'bi-mortarboard',conge_special:'bi-calendar-heart'}; nomContent += '<i class="bi ' + (icons[absType]||'bi-dash-circle') + ' rep-abs-icon"></i> '; }
              }
              nomContent += escapeHtml((a.user_prenom || '') + ' ' + (a.user_nom || ''));
              if (a.notes) nomContent += '<span class="rep-note-dot" title="' + escapeHtml(a.notes) + '">*</span>';
            } else {
              nomContent = '';
            }
            const dragAttr = a && a.assignation_id ? ' draggable="true"' : '';
            html += '<td class="cell-nom day-first' + we + modCls + absCls + (a ? '' : ' empty-cell') + '"' + attrs + dragAttr + '>' + nomContent + '</td>';

            // ── Horaire cell ──
            let horContent = '';
            if (a && a.horaire_code) {
              const bg = a.horaire_couleur || '#6c757d';
              horContent = '<span class="rep-badge" style="background:' + bg + '">' + escapeHtml(a.horaire_code) + '</span>';
            }
            html += '<td class="cell-hor' + we + '"' + attrs + '>' + horContent + '</td>';

            // ── Étage cell ──
            let etContent = '';
            if (a) {
              if (a.etage_code && a.groupe_code) etContent = escapeHtml(a.etage_code.replace('E','') + '-' + a.groupe_code.replace(/^\d+-/,''));
              else if (a.groupe_code) etContent = escapeHtml(a.groupe_code);
              else if (a.etage_code) etContent = escapeHtml(a.etage_code.replace('E',''));
            }
            html += '<td class="cell-et' + we + '"' + attrs + '>' + etContent + '</td>';
          });

          html += '</tr>';
        });
      });

      html += '</tbody></table></div></div>';
    });

    const gridEl = document.getElementById('repGrid');
    gridEl.innerHTML = html;
    gridEl.classList.toggle('rep-grid-day', isDayView);
  }

  // ─── Load week via API ───
  async function loadWeek(weekOrDate) {
    const params = {};
    if (weekOrDate && weekOrDate.includes('-W')) params.semaine = weekOrDate;
    else if (weekOrDate) params.date = weekOrDate;

    document.getElementById('repGrid').innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Chargement...</div>';

    const res = await adminApiPost('admin_get_repartition', params);
    if (!res.success) {
      document.getElementById('repGrid').innerHTML = '<div class="alert alert-danger">Erreur : ' + escapeHtml(res.message || 'Erreur') + '</div>';
      return;
    }

    data = res;
    currentWeekISO = res.week_iso;
    document.getElementById('repWeekLabel').textContent = res.week_label;
    document.getElementById('repDatePicker').value = res.week_start;

    const statusEl = document.getElementById('repPlanningStatus');
    if (res.plannings && res.plannings.length) {
      const colors = { brouillon: 'secondary', provisoire: 'info', final: 'success' };
      statusEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' + res.plannings.map(p =>
        '<span class="badge bg-' + (colors[p.statut] || 'secondary') + ' me-1">' + escapeHtml(p.mois_annee) + ' : ' + escapeHtml(p.statut) + '</span>'
      ).join('');
    } else {
      statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette période</span>';
    }

    hideEditPopover();
    if (typeof updateLabel === 'function') updateLabel();
    renderGrid();
  }

  // ─── Edit mode toggle ───
  document.getElementById('repToggleEdit').addEventListener('click', function() {
    editMode = !editMode;
    this.classList.toggle('active', editMode);
    this.innerHTML = editMode ? '<i class="bi bi-eye"></i> Lecture' : '<i class="bi bi-pencil-square"></i> Éditer';
    document.getElementById('repGrid').classList.toggle('rep-edit-mode', editMode);
    if (!editMode) hideEditPopover();
  });

  // ─── Edit popover ───
  let _popScrollHandler = null;

  function positionPopover(cellEl) {
    const pop = document.getElementById('repEditPopover');
    const rect = cellEl.getBoundingClientRect();
    const sy = window.pageYOffset || document.documentElement.scrollTop;
    const sx = window.pageXOffset || document.documentElement.scrollLeft;
    let top = rect.bottom + sy + 4, left = rect.left + sx;
    if (rect.bottom + 310 > window.innerHeight) top = rect.top + sy - 310;
    if (rect.left + 260 > window.innerWidth) left = window.innerWidth + sx - 270;
    if (left < sx + 10) left = sx + 10;
    pop.style.top = top + 'px';
    pop.style.left = left + 'px';
  }

  function showEditPopover(cellEl) {
    const pop = document.getElementById('repEditPopover');
    document.getElementById('repEditTitle').textContent = (editingCell.user_name || 'Nouveau') + ' — ' + editingCell.date;

    // Populate selects
    const hSel = document.getElementById('repEditHoraire');
    hSel.innerHTML = '<option value="">—</option>';
    (data.horaires || []).forEach(h => { hSel.innerHTML += '<option value="' + h.id + '">' + escapeHtml(h.code) + ' (' + (h.heure_debut||'').substring(0,5) + '-' + (h.heure_fin||'').substring(0,5) + ')</option>'; });

    const mSel = document.getElementById('repEditModule');
    mSel.innerHTML = '<option value="">—</option>';
    (data.modules || []).forEach(m => { mSel.innerHTML += '<option value="' + m.id + '">' + escapeHtml(m.code + ' — ' + m.nom) + '</option>'; });

    hSel.value = editingCell.horaire_type_id || '';
    mSel.value = editingCell.module_id || '';
    populateGroupeSelect(editingCell.module_id || '');
    document.getElementById('repEditGroupe').value = editingCell.groupe_id || '';
    document.getElementById('repEditStatut').value = editingCell.statut || 'present';
    document.getElementById('repEditNotes').value = editingCell.notes || '';

    document.getElementById('repEditDelete').style.display = editingCell.assignation_id ? '' : 'none';
    document.getElementById('repEditAbsent').style.display = editingCell.assignation_id ? '' : 'none';

    pop.style.display = 'block';
    positionPopover(cellEl);
    if (_popScrollHandler) window.removeEventListener('scroll', _popScrollHandler, true);
    _popScrollHandler = () => positionPopover(cellEl);
    window.addEventListener('scroll', _popScrollHandler, true);
  }

  function hideEditPopover() {
    document.getElementById('repEditPopover').style.display = 'none';
    editingCell = null;
    if (_popScrollHandler) { window.removeEventListener('scroll', _popScrollHandler, true); _popScrollHandler = null; }
  }

  function populateGroupeSelect(moduleId) {
    const sel = document.getElementById('repEditGroupe');
    sel.innerHTML = '<option value="">—</option>';
    if (!moduleId) return;
    const mod = (data.modules || []).find(m => m.id === moduleId);
    if (!mod) return;
    (mod.etages || []).forEach(et => {
      (et.groupes || []).forEach(gr => {
        sel.innerHTML += '<option value="' + gr.id + '" data-etage-id="' + et.id + '">' + escapeHtml(et.code + '-' + gr.code) + '</option>';
      });
      if (!et.groupes || et.groupes.length === 0) {
        sel.innerHTML += '<option value="" data-etage-id="' + et.id + '">' + escapeHtml(et.code) + '</option>';
      }
    });
  }

  document.getElementById('repEditModule').addEventListener('change', function() { populateGroupeSelect(this.value); });
  document.getElementById('repEditClose').addEventListener('click', hideEditPopover);
  document.addEventListener('mousedown', function(e) {
    const pop = document.getElementById('repEditPopover');
    if (pop.style.display === 'none') return;
    if (pop.contains(e.target) || e.target.closest('td.cell-nom, td.cell-hor, td.cell-et')) return;
    hideEditPopover();
  });

  // Cell click — works on cell-nom, cell-hor, or cell-et
  document.getElementById('repGrid').addEventListener('click', function(e) {
    if (!editMode) return;
    const cell = e.target.closest('td.cell-nom, td.cell-hor, td.cell-et');
    if (!cell || !cell.dataset.date) return;
    editingCell = {
      assignation_id: cell.dataset.assignationId || '',
      planning_id: cell.dataset.planningId || '',
      user_id: cell.dataset.userId || '',
      date: cell.dataset.date || '',
      horaire_type_id: cell.dataset.horaireTypeId || '',
      module_id: cell.dataset.moduleId || '',
      groupe_id: cell.dataset.groupeId || '',
      etage_id: cell.dataset.etageId || '',
      statut: cell.dataset.statut || 'present',
      notes: cell.dataset.notes || '',
      updated_at: cell.dataset.updatedAt || '',
      user_name: cell.dataset.userName || '',
      cellEl: cell,
    };
    showEditPopover(cell);
  });

  // Save
  document.getElementById('repEditSave').addEventListener('click', async function() {
    if (!editingCell) return;
    const gSel = document.getElementById('repEditGroupe');
    const opt = gSel.options[gSel.selectedIndex];
    const p = {
      assignation_id: editingCell.assignation_id || '',
      planning_id: editingCell.planning_id || '',
      user_id: editingCell.user_id,
      date_jour: editingCell.date,
      horaire_type_id: document.getElementById('repEditHoraire').value || null,
      module_id: document.getElementById('repEditModule').value || null,
      groupe_id: gSel.value || null,
      etage_id: opt ? (opt.dataset.etageId || null) : null,
      statut: document.getElementById('repEditStatut').value,
      notes: document.getElementById('repEditNotes').value,
      expected_updated_at: editingCell.updated_at || null,
    };
    this.disabled = true;
    const res = await adminApiPost('admin_save_repartition_cell', p);
    this.disabled = false;
    if (res.conflict) { toast('Conflit. Rechargement...', 'error'); loadWeek(currentWeekISO); return; }
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Enregistré', 'success'); hideEditPopover(); loadWeek(currentWeekISO);
  });

  // Delete
  document.getElementById('repEditDelete').addEventListener('click', async function() {
    if (!editingCell || !editingCell.assignation_id || !confirm('Supprimer ?')) return;
    this.disabled = true;
    const res = await adminApiPost('admin_delete_repartition_cell', { assignation_id: editingCell.assignation_id });
    this.disabled = false;
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Supprimé', 'success'); hideEditPopover(); loadWeek(currentWeekISO);
  });

  // Absent
  let absenceModal = null;
  document.getElementById('repEditAbsent').addEventListener('click', function() {
    if (!editingCell || !editingCell.assignation_id) return;
    document.getElementById('repAbsDebut').value = editingCell.date;
    document.getElementById('repAbsFin').value = editingCell.date;
    document.getElementById('repAbsMulti').checked = false;
    document.getElementById('repAbsMultiDates').style.display = 'none';
    document.getElementById('repAbsMotif').value = '';
    if (!absenceModal) absenceModal = new bootstrap.Modal(document.getElementById('repAbsenceModal'));
    absenceModal.show();
  });
  document.getElementById('repAbsMulti').addEventListener('change', function() {
    document.getElementById('repAbsMultiDates').style.display = this.checked ? 'block' : 'none';
  });
  document.getElementById('repAbsSave').addEventListener('click', async function() {
    if (!editingCell || !editingCell.assignation_id) return;
    const multi = document.getElementById('repAbsMulti').checked;
    const p = { assignation_id: editingCell.assignation_id, absence_type: document.getElementById('repAbsType').value, motif: document.getElementById('repAbsMotif').value };
    if (multi) { p.date_debut = document.getElementById('repAbsDebut').value; p.date_fin = document.getElementById('repAbsFin').value; if (!p.date_debut || !p.date_fin) { toast('Dates requises', 'error'); return; } }
    this.disabled = true;
    const res = await adminApiPost('admin_mark_absent_repartition', p);
    this.disabled = false;
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Absence enregistrée', 'success');
    if (absenceModal) absenceModal.hide(); hideEditPopover(); loadWeek(currentWeekISO);
  });

  // ─── Move helpers ───
  let moveModal = null;
  let _moveDragData = null;

  async function doMove(targetModId, etageId, groupeId, modCode) {
    const dd = _moveDragData || dragData;
    if (!dd) return;
    const res = await adminApiPost('admin_save_repartition_cell', {
      assignation_id: dd.assignation_id, planning_id: dd.planning_id, user_id: dd.user_id,
      date_jour: dd.date, horaire_type_id: dd.horaire_type_id || null,
      module_id: targetModId, groupe_id: groupeId, etage_id: etageId,
      statut: dd.statut || 'present', notes: dd.notes || '',
      expected_updated_at: dd.updated_at || null,
    });
    _moveDragData = null;
    if (res.conflict) { toast('Conflit. Rechargement...', 'error'); loadWeek(currentWeekISO); return; }
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Déplacé vers ' + modCode, 'success'); loadWeek(currentWeekISO);
  }

  function showMoveModal(tMod, opts, targetModId) {
    _moveDragData = dragData ? { ...dragData } : null;
    document.getElementById('repMoveTitle').textContent = 'Déplacer vers ' + tMod.code;
    document.getElementById('repMoveSubtitle').textContent = (dragData?.user_name || 'Employé') + ' — Choisir étage / groupe :';

    const container = document.getElementById('repMoveOptions');
    container.innerHTML = '';
    opts.forEach(function(opt) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'rep-move-opt';
      btn.innerHTML = '<span class="rep-move-code">' + escapeHtml(opt.label) + '</span>';
      btn.addEventListener('click', async function() {
        if (moveModal) moveModal.hide();
        await doMove(targetModId, opt.etageId, opt.groupeId, tMod.code);
      });
      container.appendChild(btn);
    });

    if (!moveModal) moveModal = new bootstrap.Modal(document.getElementById('repMoveModal'));
    moveModal.show();
  }

  // ─── Drag & Drop ───
  const grid = document.getElementById('repGrid');
  grid.addEventListener('dragstart', function(e) {
    if (!editMode) { e.preventDefault(); return; }
    const cell = e.target.closest('td.cell-nom');
    if (!cell || !cell.dataset.assignationId) { e.preventDefault(); return; }
    cell.classList.add('rep-dragging');
    dragData = { assignation_id: cell.dataset.assignationId, planning_id: cell.dataset.planningId, user_id: cell.dataset.userId, date: cell.dataset.date, horaire_type_id: cell.dataset.horaireTypeId, module_id: cell.dataset.moduleId, groupe_id: cell.dataset.groupeId, etage_id: cell.dataset.etageId, statut: cell.dataset.statut, notes: cell.dataset.notes, updated_at: cell.dataset.updatedAt, user_name: cell.dataset.userName };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragData.assignation_id);
  });
  grid.addEventListener('dragend', function(e) {
    const cell = e.target.closest('td.cell-nom');
    if (cell) cell.classList.remove('rep-dragging');
    document.querySelectorAll('.rep-drag-over,.rep-drag-over-mod').forEach(el => el.classList.remove('rep-drag-over', 'rep-drag-over-mod'));
    dragData = null;
  });
  grid.addEventListener('dragover', function(e) {
    if (!editMode || !dragData) return;
    if (e.target.closest('.rep-module-header[data-drop-module-id]') || e.target.closest('td.cell-nom')) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
  });
  grid.addEventListener('dragenter', function(e) {
    if (!editMode || !dragData) return;
    document.querySelectorAll('.rep-drag-over,.rep-drag-over-mod').forEach(el => el.classList.remove('rep-drag-over', 'rep-drag-over-mod'));
    const mh = e.target.closest('.rep-module-header[data-drop-module-id]');
    if (mh) mh.classList.add('rep-drag-over-mod');
    const cell = e.target.closest('td.cell-nom');
    if (cell) cell.classList.add('rep-drag-over');
  });
  grid.addEventListener('drop', async function(e) {
    e.preventDefault();
    if (!editMode || !dragData) return;
    document.querySelectorAll('.rep-drag-over,.rep-drag-over-mod').forEach(el => el.classList.remove('rep-drag-over', 'rep-drag-over-mod'));

    let targetModId = null;
    const mh = e.target.closest('.rep-module-header[data-drop-module-id]');
    const cell = e.target.closest('td.cell-nom, td.cell-hor, td.cell-et');
    if (mh) targetModId = mh.dataset.dropModuleId;
    else if (cell) { const sec = cell.closest('.rep-module-section'); if (sec) targetModId = sec.dataset.sectionModuleId; }
    if (!targetModId || targetModId === dragData.module_id) { dragData = null; return; }

    const tMod = (data.modules || []).find(m => m.id === targetModId);
    if (!tMod) { dragData = null; return; }

    let opts = [];
    (tMod.etages || []).forEach(et => {
      (et.groupes || []).forEach(gr => opts.push({ etageId: et.id, groupeId: gr.id, label: et.code + '-' + gr.code }));
      if (!et.groupes || !et.groupes.length) opts.push({ etageId: et.id, groupeId: null, label: et.code });
    });

    let eId = null, gId = null;
    if (opts.length === 1) {
      eId = opts[0].etageId; gId = opts[0].groupeId;
      await doMove(targetModId, eId, gId, tMod.code);
    } else if (opts.length > 1) {
      showMoveModal(tMod, opts, targetModId);
    } else {
      await doMove(targetModId, null, null, tMod.code);
    }
    dragData = null;
  });

  // ─── Mouse drag-to-scroll + keyboard arrows on module sections ───
  (function() {
    const grid = document.getElementById('repGrid');
    let dragEl = null, startX = 0, startScroll = 0;

    // Mouse drag to scroll
    grid.addEventListener('mousedown', function(e) {
      const sec = e.target.closest('.rep-module-inner');
      if (!sec || e.target.closest('.rep-edit-popover') || e.target.closest('select') || e.target.closest('input') || e.target.closest('button')) return;
      // Don't interfere with cell drag in edit mode
      if (editMode && e.target.closest('td.cell-nom[draggable]')) return;
      dragEl = sec;
      startX = e.pageX;
      startScroll = sec.scrollLeft;
      sec.style.cursor = 'grabbing';
      sec.style.userSelect = 'none';
      sec.style.scrollBehavior = 'auto';
      e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
      if (!dragEl) return;
      dragEl.scrollLeft = startScroll - (e.pageX - startX);
    });
    document.addEventListener('mouseup', function() {
      if (!dragEl) return;
      dragEl.style.cursor = '';
      dragEl.style.userSelect = '';
      dragEl.style.scrollBehavior = '';
      dragEl = null;
    });

    // Keyboard arrows: focus a module inner, then left/right scrolls it
    grid.addEventListener('click', function(e) {
      const sec = e.target.closest('.rep-module-inner');
      if (sec) sec.setAttribute('tabindex', '-1'), sec.focus({ preventScroll: true });
    });
    grid.addEventListener('keydown', function(e) {
      const sec = e.target.closest('.rep-module-inner');
      if (!sec) return;
      const step = 200;
      if (e.key === 'ArrowRight') { sec.scrollLeft += step; e.preventDefault(); }
      else if (e.key === 'ArrowLeft') { sec.scrollLeft -= step; e.preventDefault(); }
    });
  })();

  // ─── Legend modal ───
  let legendModal = null;
  document.getElementById('repLegendBtn').addEventListener('click', function() {
    if (!legendModal) legendModal = new bootstrap.Modal(document.getElementById('repLegendModal'));
    legendModal.show();
  });

  // ─── View toggle (week / day) ───
  function updateLabel() {
    if (viewMode === 'day' && selectedDay) {
      const dt = new Date(selectedDay + 'T00:00:00');
      const dow = (dt.getDay() + 6) % 7;
      document.getElementById('repWeekLabel').textContent =
        frFullDays[dow] + ' ' + dt.getDate() + ' ' + frMonthsFull[dt.getMonth() + 1] + ' ' + dt.getFullYear();
    } else {
      document.getElementById('repWeekLabel').textContent = data.week_label || '';
    }
  }

  document.querySelectorAll('#repViewToggle button').forEach(function(btn) {
    btn.addEventListener('click', function() {
      viewMode = btn.dataset.view;
      document.querySelectorAll('#repViewToggle button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (viewMode === 'day' && !selectedDay) selectedDay = dateToStr(new Date());
      updateLabel();
      renderGrid();
    });
  });

  // ─── Navigation ───
  function navigateStep(dir) {
    if (viewMode === 'day') {
      // Day by day
      const d = new Date(selectedDay + 'T00:00:00');
      d.setDate(d.getDate() + dir);
      selectedDay = dateToStr(d);
      // Check if crossed week boundary
      const allDates = (data.days || []).map(x => x.date);
      if (allDates.indexOf(selectedDay) === -1) {
        loadWeek(selectedDay);
      } else {
        updateLabel();
        renderGrid();
      }
    } else {
      const mon = getMondayOfISOWeek(currentWeekISO);
      if (mon) { mon.setDate(mon.getDate() + dir * 7); loadWeek(dateToStr(mon)); }
    }
  }

  document.getElementById('repPrevWeek').addEventListener('click', () => navigateStep(-1));
  document.getElementById('repNextWeek').addEventListener('click', () => navigateStep(1));
  document.getElementById('repToday').addEventListener('click', () => { selectedDay = dateToStr(new Date()); loadWeek(null); });
  document.getElementById('repDatePicker').addEventListener('change', e => { if (e.target.value) { selectedDay = e.target.value; loadWeek(e.target.value); } });
  document.getElementById('repPrint').addEventListener('click', () => window.print());

  // ─── Init ───
  window.initRepartitionPage = function() { renderGrid(); };

})();
</script>
