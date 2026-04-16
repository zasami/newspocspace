<?php
require_once __DIR__ ."/../init.php";
if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }

$ztUser = $_SESSION["ss_user"];

// SSR: buddy count
$buddies = Db::fetchAll(
"SELECT cb.buddy_id FROM covoiturage_buddies cb WHERE cb.user_id = ?",
    [$ztUser['id']]
);
$buddyIds = array_column($buddies, 'buddy_id');
$buddyCount = count($buddyIds);

// SSR: current week data
$today = date('Y-m-d');
$dt = new DateTime($today);
$dow = (int)$dt->format('N');
$monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = (clone $monday)->modify("+$i days")->format('Y-m-d');
}

$moisSet = [];
foreach ($weekDays as $wd) { $moisSet[substr($wd, 0, 7)] = true; }

$weekResults = [];
foreach (array_keys($moisSet) as $mois) {
    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) continue;
    $myAssigns = Db::fetchAll(
"SELECT pa.date_jour, pa.horaire_type_id, ht.code AS horaire_code, ht.heure_debut, ht.heure_fin
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         WHERE pa.planning_id = ? AND pa.user_id = ? AND pa.date_jour IN (" . implode(',', array_fill(0, count($weekDays), '?')) .")
           AND pa.horaire_type_id IS NOT NULL",
        array_merge([$planning['id'], $ztUser['id']], $weekDays)
    );
    foreach ($myAssigns as $ma) {
        $count = 0;
        if (!empty($buddyIds)) {
            $ph = implode(',', array_fill(0, count($buddyIds), '?'));
            $count = (int)Db::getOne(
"SELECT COUNT(*) FROM planning_assignations
                 WHERE planning_id = ? AND date_jour = ? AND user_id IN ($ph)
                   AND horaire_type_id = ? AND statut IN ('present','entraide')",
                array_merge([$planning['id'], $ma['date_jour']], $buddyIds, [$ma['horaire_type_id']])
            );
        }
        $weekResults[$ma['date_jour']] = [
            'horaire' => $ma['horaire_code'],
            'debut' => $ma['heure_debut'],
            'fin' => $ma['heure_fin'],
            'same_shift_count' => $count,
        ];
    }
}

$ssrData = [
    'buddy_count' => $buddyCount,
    'has_buddies' => $buddyCount > 0,
    'week_start' => $weekDays[0],
    'days' => $weekResults,
];
?>
<div class="page-header cov-page-header">
  <h1><i class="bi bi-car-front"></i> Covoiturage</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-sm cov-buddies-btn" id="covBuddiesBtn">
      <i class="bi bi-people"></i> Mes collègues <span class="badge bg-dark ms-1" id="covBuddyCount">0</span>
    </button>
    <button class="btn btn-sm btn-outline-secondary" id="covPrintBtn"><i class="bi bi-printer"></i></button>
  </div>
</div>

<!-- Buddy Manager Panel (collapsible) -->
<div id="covBuddyPanel" class="mb-3" style="display:none">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-people-fill"></i> Ma liste de covoiturage</h6>
        <button class="btn btn-sm btn-light" id="covCloseBuddyPanel"><i class="bi bi-x-lg"></i></button>
      </div>
      <!-- Search to add -->
      <div class="input-group input-group-sm mb-3">
        <span class="input-group-text cov-search-icon"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control cov-search-input" id="covBuddySearch" placeholder="Rechercher un collègue...">
      </div>
      <div id="covSearchResults" class="mb-3" style="display:none"></div>
      <!-- Current buddies -->
      <div id="covBuddyList">
        <div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 align-items-center mb-3 flex-wrap">
  <button class="btn btn-sm btn-outline-secondary" id="covPrevWeek"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold cov-week-label" id="covWeekLabel"></span>
  <button class="btn btn-sm btn-outline-secondary" id="covNextWeek"><i class="bi bi-chevron-right"></i></button>
  <button class="btn btn-sm btn-outline-primary ms-2" id="covTodayBtn">Aujourd'hui</button>
</div>

<!-- No buddies alert -->
<div id="covNoBuddiesAlert" class="mb-3" style="display:none">
  <div class="alert mb-0 d-flex align-items-center gap-3 cov-alert-hint">
    <i class="bi bi-people cov-alert-hint-icon"></i>
    <div>
      <strong>Ajoutez vos collègues de covoiturage</strong>
      <div class="small text-muted">Cliquez sur « Mes collègues » pour ajouter les personnes avec qui vous faites du covoiturage. Les croisements d'horaires s'afficheront ensuite ici.</div>
    </div>
  </div>
</div>

<!-- Week overview -->
<div class="row g-2 mb-3" id="covWeekGrid"></div>

<!-- Day detail -->
<div id="covDayDetail" class="d-none">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span id="covDayTitle"></span>
      <span class="badge" id="covMyShift"></span>
    </div>
    <div class="card-body" id="covMatchList">
    </div>
  </div>
</div>


<script type="application/json" id="__ss_ssr__"><?php echo json_encode($ssrData, JSON_UNESCAPED_UNICODE); ?></script>
