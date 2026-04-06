<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$repDto = new DateTime();
$repDow = (int)$repDto->format('N');
$repDto->modify('-' . ($repDow - 1) . ' days');
$repWeekStart = $repDto->format('Y-m-d');
$repDtoEnd = clone $repDto;
$repDtoEnd->modify('+6 days');
$repWeekEnd = $repDtoEnd->format('Y-m-d');
$repWeekNum = (int)$repDto->format('W');
$repYear = (int)$repDto->format('o');

$repModules = Db::fetchAll("SELECT id, nom, code, ordre FROM modules ORDER BY ordre");
foreach ($repModules as &$_m) {
    $_etages = Db::fetchAll("SELECT id, nom, code, ordre FROM etages WHERE module_id = ? ORDER BY ordre", [$_m['id']]);
    foreach ($_etages as &$_e) { $_e['groupes'] = Db::fetchAll("SELECT id, nom, code, ordre FROM groupes WHERE etage_id = ? ORDER BY ordre", [$_e['id']]); }
    unset($_e);
    $_m['etages'] = $_etages;
}
unset($_m);
$repHoraires = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");
$repFonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");
$repUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom,
            f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            hm.id AS home_module_id, hm.code AS home_module_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules hm ON hm.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY f.ordre, u.nom"
);
$repMoisStart = $repDto->format('Y-m');
$repMoisEnd = $repDtoEnd->format('Y-m');
$repMoisList = array_unique([$repMoisStart, $repMoisEnd]);
$repPh = implode(',', array_fill(0, count($repMoisList), '?'));
$repPlannings = Db::fetchAll("SELECT id, mois_annee, statut FROM plannings WHERE mois_annee IN ($repPh)", $repMoisList);
$repPlanningIds = array_column($repPlannings, 'id');
$repAssignments = [];
if ($repPlanningIds) {
    $repPhPlan = implode(',', array_fill(0, count($repPlanningIds), '?'));
    $repAssignments = Db::fetchAll(
        "SELECT pa.date_jour, pa.user_id, pa.module_id, pa.groupe_id, pa.etage_id,
                pa.statut, pa.notes,
                u.prenom AS user_prenom, u.nom AS user_nom,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.code AS module_code,
                g.code AS groupe_code,
                COALESCE(e2.code, e.code) AS etage_code
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN groupes g ON g.id = pa.groupe_id
         LEFT JOIN etages e ON e.id = g.etage_id
         LEFT JOIN etages e2 ON e2.id = pa.etage_id
         WHERE pa.planning_id IN ($repPhPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
        array_merge($repPlanningIds, [$repWeekStart, $repWeekEnd])
    );
}
$repFrDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$repDays = [];
for ($repI = 0; $repI < 7; $repI++) {
    $repD = clone $repDto;
    $repD->modify("+$repI days");
    $repDays[] = [
        'date'  => $repD->format('Y-m-d'),
        'label' => $repFrDays[$repI] . ' ' . $repD->format('d'),
        'short' => $repFrDays[$repI],
        'is_weekend' => in_array($repD->format('N'), ['6', '7']),
    ];
}
?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
  <div>
    <h1><i class="bi bi-grid-3x3-gap"></i> Répartition</h1>
    <p id="repWeekLabel" class="text-muted" style="margin:0">Chargement...</p>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem">
    <div class="btn-group btn-group-sm" id="repViewToggle">
      <button class="btn btn-outline-secondary active" data-view="week" title="Vue semaine"><i class="bi bi-calendar-week"></i></button>
      <button class="btn btn-outline-secondary" data-view="day" title="Vue jour"><i class="bi bi-calendar-day"></i></button>
    </div>
    <button class="btn btn-sm btn-outline-secondary" id="repPrevWeek"><i class="bi bi-chevron-left"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="repToday">Aujourd'hui</button>
    <button class="btn btn-sm btn-outline-secondary" id="repNextWeek"><i class="bi bi-chevron-right"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="repInfoBtn" title="Horaires"><i class="bi bi-clock"></i></button>
  </div>
</div>

<!-- Horaires modal -->
<div class="modal fade" id="repHorairesModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock me-1"></i>Horaires types</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border);display:flex;align-items:center;justify-content:center" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:.85rem"></i></button>
      </div>
      <div class="modal-body" style="padding:0;max-height:60vh;overflow-y:auto" id="repHorairesBody"></div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button></div>
    </div>
  </div>
</div>

<!-- Grid -->
<div id="repGrid"></div>

<style>
/* Module sections — scrollable */
.rep-section { margin-bottom: 1rem; }
.rep-section-header { padding: .4rem .75rem; font-weight: 600; font-size: .85rem; color: #fff; border-radius: 6px 6px 0 0; display: flex; align-items: center; gap: .5rem; }
.rep-section-header .badge { font-size: .68rem; background: rgba(255,255,255,.25); }
.rep-section-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; cursor: grab; border: 1px solid var(--ss-border-light, #e9ecef); border-top: 0; border-radius: 0 0 6px 6px; }
.rep-section-scroll:focus { outline: none; box-shadow: 0 0 0 2px rgba(45,74,67,.3); }

.rep-tbl { width: 100%; min-width: 1500px; border-collapse: separate; border-spacing: 0; font-size: .76rem; }
.rep-tbl th, .rep-tbl td { border: 1px solid #dee2e6; padding: .2rem .3rem; vertical-align: middle; }
.rep-tbl thead th { background: var(--ss-bg, #f1f3f5); font-weight: 600; text-align: center; position: sticky; top: 0; z-index: 2; font-size: .7rem; }
.rep-tbl th.col-fn { width: 70px; position: sticky; left: 0; z-index: 4; background: var(--ss-bg, #f1f3f5); }
.rep-tbl th.col-slot { width: 50px; position: sticky; left: 70px; z-index: 4; background: var(--ss-bg, #f1f3f5); }
.rep-tbl th.col-day-head { font-size: .72rem; border-bottom: 0; }
.rep-tbl th.col-day-head.weekend { background: rgba(254,252,243,.5); }
.rep-tbl th.col-sub { font-size: .64rem; color: #888; font-weight: 500; border-top: 0; text-align: center; }
.rep-tbl th.col-sub-nom { width: 110px; }
.rep-tbl th.col-sub-hor { width: 38px; }
.rep-tbl th.col-sub-et { width: 35px; }
.rep-tbl td.day-first { border-left: 2px solid #adb5bd; }

.rep-tbl td.cell-fn { font-weight: 700; font-size: .68rem; color: var(--ss-text, #333); background: var(--ss-bg, #f8f9fa); text-align: center; border-right: 2px solid #ccc; position: sticky; left: 0; z-index: 1; }
.rep-tbl td.cell-slot { font-size: .66rem; font-weight: 600; color: #555; background: var(--ss-bg-card, #fdfdfd); text-align: center; position: sticky; left: 70px; z-index: 1; }
.rep-tbl tr.fn-first td { border-top: 2px solid #adb5bd; }

.rep-tbl td.cell-nom { font-size: .72rem; font-weight: 600; color: var(--ss-text, #222); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
.rep-tbl td.cell-nom.weekend { background: rgba(254,252,243,.3); }
.rep-tbl td.cell-hor { text-align: center; padding: .15rem .1rem; }
.rep-tbl td.cell-hor.weekend { background: rgba(254,252,243,.3); }
.rep-tbl td.cell-et { text-align: center; font-size: .66rem; font-weight: 600; color: #555; }
.rep-tbl td.cell-et.weekend { background: rgba(254,252,243,.3); }

.rep-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: .62rem; font-weight: 600; color: #fff; white-space: nowrap; line-height: 1.3; }
.rep-note-dot { color: #999; font-size: .6rem; vertical-align: super; }

/* Module colors */
.rep-c-M1 { background: #2196F3; } .rep-c-M2 { background: #4CAF50; }
.rep-c-M3 { background: #FF9800; } .rep-c-M4 { background: #9C27B0; }
.rep-c-NUIT { background: #37474F; } .rep-c-POOL { background: #795548; }
.rep-c-RS { background: #607D8B; } .rep-c-DEFAULT { background: #6c757d; }

/* Day view — single day column wider */
.rep-tbl.rep-day-view th.col-sub-nom { width: 180px; }

@media print {
  .page-header, #repInfoBtn, #repViewToggle { display: none !important; }
  .rep-section-scroll { overflow: visible; }
  .rep-tbl { min-width: 0; font-size: .64rem; }
  .rep-badge, .rep-section-header { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>

<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'success'     => true,
    'week_start'  => $repWeekStart,
    'week_end'    => $repWeekEnd,
    'week_num'    => $repWeekNum,
    'year'        => $repYear,
    'days'        => $repDays,
    'modules'     => $repModules,
    'horaires'    => $repHoraires,
    'fonctions'   => $repFonctions,
    'users'       => $repUsers,
    'assignments' => $repAssignments,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
