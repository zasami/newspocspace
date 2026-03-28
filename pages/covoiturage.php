<?php
require_once __DIR__ . "/../init.php";
if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }

$ztUser = $_SESSION["zt_user"];

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
         WHERE pa.planning_id = ? AND pa.user_id = ? AND pa.date_jour IN (" . implode(',', array_fill(0, count($weekDays), '?')) . ")
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
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1><i class="bi bi-car-front"></i> Covoiturage</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-sm" id="covBuddiesBtn" style="background:var(--zt-accent-bg);color:var(--zt-text);font-weight:500;">
      <i class="bi bi-people"></i> Mes collègues <span class="badge bg-dark ms-1" id="covBuddyCount">0</span>
    </button>
    <button class="btn btn-sm btn-outline-secondary" id="covPrintBtn"><i class="bi bi-printer"></i></button>
  </div>
</div>

<!-- Buddy Manager Panel (collapsible) -->
<div id="covBuddyPanel" class="mb-3" style="display:none;">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-people-fill"></i> Ma liste de covoiturage</h6>
        <button class="btn btn-sm btn-light" id="covCloseBuddyPanel"><i class="bi bi-x-lg"></i></button>
      </div>
      <!-- Search to add -->
      <div class="input-group input-group-sm mb-3">
        <span class="input-group-text" style="background:#fff;border-right:none;"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" id="covBuddySearch" placeholder="Rechercher un collègue..." style="border-left:none;">
      </div>
      <div id="covSearchResults" style="display:none;" class="mb-3"></div>
      <!-- Current buddies -->
      <div id="covBuddyList">
        <div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 align-items-center mb-3 flex-wrap">
  <button class="btn btn-sm btn-outline-secondary" id="covPrevWeek"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold" id="covWeekLabel" style="min-width:180px;text-align:center"></span>
  <button class="btn btn-sm btn-outline-secondary" id="covNextWeek"><i class="bi bi-chevron-right"></i></button>
  <button class="btn btn-sm btn-outline-primary ms-2" id="covTodayBtn">Aujourd'hui</button>
</div>

<!-- No buddies alert -->
<div id="covNoBuddiesAlert" class="mb-3" style="display:none;">
  <div class="alert mb-0 d-flex align-items-center gap-3" style="background:#FFF8F4;border:1px solid #E8E5E0;border-left:4px solid var(--zt-teal);">
    <i class="bi bi-people" style="font-size:1.5rem;color:var(--zt-teal);"></i>
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

<style>
.cov-day-card{border-radius:10px;padding:0.75rem;border:1px solid var(--zt-border-light,#e5e5e5);cursor:pointer;transition:all .15s;text-align:center}
.cov-day-card:hover{border-color:var(--zt-teal,#191918);box-shadow:0 2px 8px rgba(0,0,0,.06)}
.cov-day-card.active{border-color:var(--zt-teal);background:var(--zt-accent-bg)}
.cov-day-card.rest{opacity:.4;cursor:default}
.cov-day-name{font-size:0.75rem;text-transform:uppercase;color:var(--zt-text-muted);margin-bottom:2px}
.cov-day-date{font-size:0.9rem;font-weight:600;margin-bottom:4px}
.cov-day-shift{font-size:0.75rem;padding:2px 6px;border-radius:4px;display:inline-block}
.cov-day-count{font-size:0.72rem;color:var(--zt-text-muted);margin-top:3px}
.cov-match-item{display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0;border-bottom:1px solid var(--zt-border-light,#eee)}
.cov-match-item:last-child{border-bottom:none}
.cov-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.8rem;flex-shrink:0}
.cov-match-badge{font-size:0.7rem;padding:2px 6px;border-radius:4px;font-weight:500}
.cov-match-exact{background:#e8f5e9;color:#2e7d32}
.cov-match-overlap{background:#fff3e0;color:#e65100}
/* Buddy list */
.cov-buddy-item{display:flex;align-items:center;gap:0.75rem;padding:8px 0;border-bottom:1px solid var(--zt-border-light,#eee)}
.cov-buddy-item:last-child{border-bottom:none}
.cov-search-item{display:flex;align-items:center;gap:0.75rem;padding:8px 10px;border-radius:8px;cursor:pointer;transition:background .15s}
.cov-search-item:hover{background:var(--zt-accent-bg)}
.cov-search-item.is-buddy{opacity:.5;cursor:default}
</style>
<script type="application/json" id="__zt_ssr__"><?php echo json_encode($ssrData, JSON_UNESCAPED_UNICODE); ?></script>
