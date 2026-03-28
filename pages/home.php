<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['zt_user']['id'];
$homeCurrentMois = date('Y-m');
$homeDesirCount = (int) Db::getOne(
    "SELECT COUNT(*) FROM desirs WHERE user_id = ? AND mois_cible = ?",
    [$uid, $homeCurrentMois]
);
$homeMaxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: 4);
$homeUnread = (int) Db::getOne(
    "SELECT COUNT(*) FROM email_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0",
    [$uid]
);
?>
<div class="page-header">
  <h1>Bonjour <span id="homeUserName"></span></h1>
  <p>Voici votre tableau de bord</p>
</div>

<div class="stats-grid" id="homeStats">
  <div class="stat-card">
    <div class="stat-icon teal"><i class="bi bi-calendar3"></i></div>
    <div>
      <div class="stat-value" id="statNextShift">—</div>
      <div class="stat-label">Prochain service</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-star"></i></div>
    <div>
      <div class="stat-value" id="statDesirs">—</div>
      <div class="stat-label">Désirs ce mois</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="bi bi-calendar-x"></i></div>
    <div>
      <div class="stat-value" id="statVacances">—</div>
      <div class="stat-label">Jours vacances restants</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="bi bi-envelope"></i></div>
    <div>
      <div class="stat-value" id="statMessages">—</div>
      <div class="stat-label">Messages non lus</div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 flex-wrap" style="align-items:flex-start">
  <!-- Mon planning de la semaine -->
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0"><i class="bi bi-calendar-week"></i> Ma semaine</h3>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <button class="btn btn-sm btn-outline-secondary" id="homePrevWeek" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
        <span id="homeWeekLabel" style="font-size:0.85rem;font-weight:600;min-width:160px;text-align:center"></span>
        <button class="btn btn-sm btn-outline-secondary" id="homeNextWeek" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div class="card-body" id="homeWeekPlanning">
      <div class="empty-state">
        <i class="bi bi-calendar3"></i>
        <p>Aucun planning disponible</p>
      </div>
    </div>
  </div>

  <!-- Menu de la semaine -->
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0"><i class="bi bi-egg-fried"></i> Menu du midi</h3>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <button class="btn btn-sm btn-outline-secondary" id="menuPrevWeek" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
        <span id="menuWeekLabel" style="font-size:0.85rem;font-weight:600;min-width:80px;text-align:center"></span>
        <button class="btn btn-sm btn-outline-secondary" id="menuNextWeek" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div class="card-body" id="homeMenus">
      <div class="empty-state" style="padding:2rem">
        <i class="bi bi-egg-fried"></i>
        <p>Aucun menu disponible cette semaine</p>
      </div>
    </div>
  </div>

  <!-- Modal réservation -->
  <div class="modal fade" id="menuReservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="menuReservationTitle">Réserver un repas</h5>
          <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
        </div>
        <div class="modal-body" style="max-height:60vh;overflow-y:auto">
          <div id="menuDetailContent"></div>
          <hr style="border-color:var(--zt-border-light);margin:1rem 0">
          <form id="menuReservationForm"></form>
        </div>
        <div class="modal-footer d-flex" id="menuReservationFooter">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal confirmation annulation commande -->
  <div class="modal fade" id="menuCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle" style="color:#c0392b;font-size:1.1rem"></i>
            <span class="fw-semibold">Annuler la commande</span>
          </div>
          <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
        </div>
        <div class="modal-body">
          <p style="margin:0;font-size:0.92rem">Êtes-vous sûr de vouloir annuler votre commande ? Cette action est irréversible.</p>
        </div>
        <div class="modal-footer d-flex">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Non, garder</button>
          <button type="button" class="btn btn-sm btn-danger ms-auto" id="menuCancelConfirmBtn"><i class="bi bi-x-circle"></i> Oui, annuler</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="__zt_ssr__"><?= json_encode([
    'desir_count' => $homeDesirCount,
    'max_desirs'  => $homeMaxDesirs,
    'unread_count' => $homeUnread,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
