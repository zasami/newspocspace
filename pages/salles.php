<?php
/**
 * SPA Employee — Réservation de salles
 * Vue semaine des disponibilités + formulaire de réservation
 */
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$salles = Db::fetchAll("SELECT id, nom, description, capacite, equipements, couleur FROM salles WHERE is_active = 1 ORDER BY ordre, nom");

// Réservations de la semaine courante
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$reservations = Db::fetchAll(
    "SELECT r.id, r.salle_id, r.user_id, r.titre, r.description, r.date_jour,
            r.heure_debut, r.heure_fin, u.prenom, u.nom AS user_nom
     FROM reservations_salles r
     JOIN users u ON u.id = r.user_id
     WHERE r.date_jour BETWEEN ? AND ? AND r.statut = 'confirmee'
     ORDER BY r.date_jour, r.heure_debut",
    [$monday, $sunday]
);

// Mes réservations à venir
$mesResas = Db::fetchAll(
    "SELECT r.*, s.nom AS salle_nom, s.couleur AS salle_couleur
     FROM reservations_salles r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.user_id = ? AND r.date_jour >= CURDATE() AND r.statut = 'confirmee'
     ORDER BY r.date_jour, r.heure_debut
     LIMIT 10",
    [$uid]
);
?>

<div class="page-wrap">
  <?= render_page_header('Réservation de salles', 'bi-door-open', 'home', 'Accueil') ?>

  <!-- Stats -->
  <div class="row g-3 mb-3">
    <?= render_stat_card('Salles disponibles', count($salles), 'bi-door-open', 'teal') ?>
    <?= render_stat_card('Mes réservations', count($mesResas), 'bi-calendar-check', 'green') ?>
  </div>

  <!-- Legend -->
  <div class="d-flex flex-wrap gap-2 mb-3" id="slLegend">
    <?php foreach ($salles as $s): ?>
    <span class="d-flex align-items-center gap-1" style="font-size:.78rem;color:var(--cl-text-muted)">
      <span style="width:12px;height:12px;border-radius:3px;background:<?= h($s['couleur']) ?>;display:inline-block"></span>
      <?= h($s['nom']) ?> <span style="opacity:.6">(<?= (int)$s['capacite'] ?>p)</span>
    </span>
    <?php endforeach ?>
  </div>

  <!-- Toolbar -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <button class="btn btn-sm btn-outline-secondary" id="slPrev"><i class="bi bi-chevron-left"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="slNext"><i class="bi bi-chevron-right"></i></button>
    <strong id="slWeekLabel" style="font-size:.95rem"></strong>
    <button class="btn btn-sm btn-outline-secondary" id="slToday">Aujourd'hui</button>
    <select class="form-select form-select-sm" id="slSalleFilter" style="max-width:180px">
      <option value="">Toutes les salles</option>
      <?php foreach ($salles as $s): ?>
      <option value="<?= h($s['id']) ?>"><?= h($s['nom']) ?></option>
      <?php endforeach ?>
    </select>
    <button class="btn btn-sm btn-dark ms-auto" id="slNewBtn"><i class="bi bi-plus-lg"></i> Réserver</button>
  </div>

  <!-- Timeline grid -->
  <div style="overflow-x:auto;background:var(--cl-surface,#fff);border-radius:12px;border:1.5px solid var(--cl-border-light,#F0EDE8);max-height:calc(100vh - 340px);overflow-y:auto">
    <div id="slGrid" style="display:grid;min-width:700px"></div>
  </div>

  <!-- Mes réservations à venir -->
  <?php if ($mesResas): ?>
  <h6 class="mt-4 mb-2"><i class="bi bi-calendar-check"></i> Mes prochaines réservations</h6>
  <div class="d-flex flex-column gap-2" id="slMyResas">
    <?php foreach ($mesResas as $r): ?>
    <div class="d-flex align-items-center gap-3 p-2" style="background:var(--cl-surface,#fff);border-radius:8px;border:1px solid var(--cl-border-light,#F0EDE8)">
      <div style="width:8px;height:36px;border-radius:4px;background:<?= h($r['salle_couleur']) ?>;flex-shrink:0"></div>
      <div class="flex-grow-1">
        <div style="font-weight:600;font-size:.85rem"><?= h($r['titre']) ?></div>
        <div style="font-size:.75rem;color:var(--cl-text-muted)"><?= h($r['salle_nom']) ?> · <?= fmt_date_fr($r['date_jour'], 'D d.m') ?> · <?= $r['journee_entiere'] ? 'Journée entière' : h(substr($r['heure_debut'],0,5)) . ' — ' . h(substr($r['heure_fin'],0,5)) ?></div>
      </div>
      <button class="btn btn-sm btn-outline-danger sl-cancel-resa" data-id="<?= h($r['id']) ?>" title="Annuler"><i class="bi bi-x-lg"></i></button>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</div>

<!-- Modal Réservation -->
<div class="modal fade" id="slResaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Réserver une salle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Salle *</label>
          <select class="form-select form-select-sm" id="slResaSalle">
            <?php foreach ($salles as $s): ?>
            <option value="<?= h($s['id']) ?>"><?= h($s['nom']) ?> (<?= (int)$s['capacite'] ?> pers.)</option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Titre / Objet *</label>
          <input type="text" class="form-control form-control-sm" id="slResaTitre" placeholder="Ex: Réunion d'équipe">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.82rem">Description</label>
          <textarea class="form-control form-control-sm" id="slResaDesc" rows="2" placeholder="Détails (optionnel)"></textarea>
        </div>
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="slResaJournee">
            <label class="form-check-label fw-semibold" for="slResaJournee" style="font-size:.82rem">Journée entière</label>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-sm-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date *</label>
            <input type="date" class="form-control form-control-sm" id="slResaDate">
          </div>
          <div class="col-sm-4" id="slResaDebutWrap">
            <label class="form-label fw-semibold" style="font-size:.82rem">Début *</label>
            <input type="time" class="form-control form-control-sm" id="slResaDebut" value="08:00" step="900">
          </div>
          <div class="col-sm-4" id="slResaFinWrap">
            <label class="form-label fw-semibold" style="font-size:.82rem">Fin *</label>
            <input type="time" class="form-control form-control-sm" id="slResaFin" value="09:00" step="900">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark" id="slResaSaveBtn">Réserver</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Détail -->
<div class="modal fade" id="slDetailModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="slDetailTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="slDetailBody"></div>
      <div class="modal-footer" id="slDetailFooter" style="display:none">
        <button type="button" class="btn btn-sm btn-outline-danger" id="slDetailCancelBtn"><i class="bi bi-x-lg"></i> Annuler ma réservation</button>
      </div>
    </div>
  </div>
</div>

<script id="__ss_ssr__" type="application/json">
<?= json_encode([
    'salles' => $salles,
    'reservations' => $reservations,
    'mesResas' => $mesResas,
    'userId' => $uid,
    'monday' => $monday,
], JSON_HEX_TAG) ?>
</script>
