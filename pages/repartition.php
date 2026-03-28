<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
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
$repHoraires = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");
$repFonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");
$repUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom,
            f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            hm.id AS home_module_id, hm.code AS home_module_code, hm.nom AS home_module_nom
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
        "SELECT pa.date_jour, pa.user_id, pa.statut,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.code AS module_code, m.id AS module_id
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id IN ($repPhPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre",
        array_merge($repPlanningIds, [$repWeekStart, $repWeekEnd])
    );
}
$repFrDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$repDays = [];
for ($repI = 0; $repI < 7; $repI++) {
    $repD = clone $repDto;
    $repD->modify("+$repI days");
    $repDays[] = [
        'date'       => $repD->format('Y-m-d'),
        'label'      => $repFrDays[$repI] . ' ' . $repD->format('d'),
        'short'      => $repFrDays[$repI],
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
    <button class="btn btn-sm btn-outline-secondary" id="repPrevWeek" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="repToday" title="Cette semaine">Aujourd'hui</button>
    <button class="btn btn-sm btn-outline-secondary" id="repNextWeek" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
  </div>
</div>

<!-- Module tabs + bouton horaires -->
<div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem">
  <div id="repModuleTabs" style="display:flex;gap:0.4rem;flex-wrap:wrap;flex:1"></div>
  <button class="btn btn-sm btn-outline-secondary" id="repInfoBtn" style="display:inline-flex;align-items:center;gap:0.4rem;border-radius:8px;flex-shrink:0" title="Détail des horaires"><i class="bi bi-clock"></i> Horaires</button>
</div>

<!-- Modal horaires -->
<div class="modal fade" id="repHorairesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock"></i> Horaires types</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="padding:0;max-height:60vh;overflow-y:auto" id="repHorairesBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Grid -->
<div class="card">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table class="table table-sm" style="margin:0;font-size:0.85rem" id="repTable">
      <thead id="repHead"></thead>
      <tbody id="repBody">
        <tr><td colspan="8" class="text-center py-4 text-muted">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>
<script type="application/json" id="__zt_ssr__"><?= json_encode([
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
