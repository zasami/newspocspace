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
