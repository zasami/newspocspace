<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$vacYear = (int) date('Y');
$vacDebut = "$vacYear-01-01";
$vacFin = "$vacYear-12-31";

// Collaborateurs actifs
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

// Absences vacances de l'annee
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

// Periodes bloquees
$vacBloquees = Db::fetchAll(
    "SELECT id, date_debut, date_fin, motif FROM periodes_bloquees
     WHERE date_debut <= ? AND date_fin >= ?
     ORDER BY date_debut",
    [$vacFin, $vacDebut]
);

// Modules
$vacModules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");

// Mon solde
$vacMoi = Db::fetch("SELECT solde_vacances FROM users WHERE id = ?", [$uid]);
$vacMonSolde = floatval($vacMoi['solde_vacances'] ?? 27);
$vacJoursUtilises = (int) Db::getOne(
    "SELECT COALESCE(SUM(DATEDIFF(LEAST(date_fin, ?), GREATEST(date_debut, ?)) + 1), 0)
     FROM absences
     WHERE user_id = ? AND type = 'vacances' AND statut IN ('valide', 'en_attente')
       AND date_debut <= ? AND date_fin >= ?",
    [$vacFin, $vacDebut, $uid, $vacFin, $vacDebut]
);
$vacRestant = $vacMonSolde - $vacJoursUtilises;

// Stats
$mesVacances = array_filter($vacAbsences, fn($a) => $a['user_id'] === $uid);
$nValides   = count(array_filter($mesVacances, fn($a) => $a['statut'] === 'valide'));
$nEnAttente = count(array_filter($mesVacances, fn($a) => $a['statut'] === 'en_attente'));

// Trouver mon info
$meUser = null;
foreach ($vacUsers as $u) { if ($u['id'] === $uid) { $meUser = $u; break; } }
$monNom = $meUser ? h($meUser['prenom']) . ' ' . h($meUser['nom']) . ' -- ' . h($meUser['fonction_code'] ?? '') : 'Mon planning';
?>
<!-- Header -->
<div class="vac-header">
  <div class="vac-header-left">
    <h1 class="vac-title"><i class="bi bi-sun"></i> Vacances</h1>
  </div>
  <div class="vac-header-right">
    <div class="vac-solde<?= $vacRestant <= 5 ? ' low' : '' ?>" id="vacSolde">
      <div class="vac-solde-label">Solde restant</div>
      <div class="vac-solde-value" id="vacSoldeValue"><?= (int) round($vacRestant) ?>j</div>
      <div class="vac-solde-detail" id="vacSoldeDetail"><?= (int) round($vacJoursUtilises) ?> pris / <?= (int) round($vacMonSolde) ?> total</div>
    </div>
  </div>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-3">
    <?= render_stat_card('Solde restant', (int) round($vacRestant) . 'j', 'bi-sun', $vacRestant <= 5 ? 'red' : 'teal', (int) round($vacJoursUtilises) . ' pris / ' . (int) round($vacMonSolde) . ' total') ?>
    <?= render_stat_card('Validees', $nValides, 'bi-check-circle', 'green', $nValides > 0 ? 'periodes confirmees' : null) ?>
    <?= render_stat_card('En attente', $nEnAttente, 'bi-hourglass-split', 'orange', $nEnAttente > 0 ? 'en cours de validation' : null) ?>
    <?= render_stat_card('Total demandes', count($mesVacances), 'bi-calendar-range', 'purple', 'cette annee') ?>
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

<!-- SECTION 1: Ma ligne de depot -->
<div class="vac-my-section">
  <div class="vac-my-label"><i class="bi bi-person-fill"></i> <span id="vacMyName"><?= $monNom ?></span></div>
  <div class="vac-my-topbar">
    <div class="vac-drag-hint" id="vacDragHint"><i class="bi bi-mouse"></i> Glissez pour selectionner</div>
    <div class="vac-legend-inline">
      <span class="vac-leg"><span class="vac-sw vac-sw-valide"></span> Validees</span>
      <span class="vac-leg"><span class="vac-sw vac-sw-attente"></span> En attente</span>
      <span class="vac-leg"><span class="vac-sw vac-sw-blocked"></span> Bloque</span>
      <span class="vac-leg"><span class="vac-sw vac-sw-today"></span> Aujourd'hui</span>
    </div>
  </div>
  <div class="vac-drag-info" id="vacDragInfo" style="display:none">
    <i class="bi bi-arrows-expand"></i> <span id="vacDragText"></span>
    <button class="btn btn-sm btn-outline-secondary ms-auto" id="vacDragCancel"><i class="bi bi-x-lg"></i></button>
  </div>
  <div id="vacMyGrid"></div>
</div>

<!-- SECTION 2: Consultation collegues -->
<div class="vac-team-section">
  <div class="vac-team-header">
    <span class="vac-team-label"><i class="bi bi-people"></i> Equipe</span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <select class="form-select form-select-sm vac-module-filter" id="vacModuleFilter">
        <option value="">Tous les modules</option>
        <?php foreach ($vacModules as $m): ?>
          <option value="<?= h($m['id']) ?>"><?= h($m['code']) ?> -- <?= h($m['nom']) ?></option>
        <?php endforeach ?>
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

<!-- MODAL: Saisie manuelle -->
<div class="modal fade" id="vacFormModal" tabindex="-1" aria-labelledby="vacFormModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vacFormModalLabel"><i class="bi bi-calendar-plus"></i> Deposer des vacances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Date de debut</label>
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
        <button type="button" class="btn btn-success btn-sm" id="vacFormSubmit"><i class="bi bi-check-lg"></i> Deposer</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Confirmer drag -->
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

<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'success'        => true,
    'annee'          => $vacYear,
    'users'          => $vacUsers,
    'absences'       => $vacAbsences,
    'bloquees'       => $vacBloquees,
    'modules'        => $vacModules,
    'mon_solde'      => $vacMonSolde,
    'jours_utilises' => $vacJoursUtilises,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
