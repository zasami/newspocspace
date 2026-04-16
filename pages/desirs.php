<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$moisCourant = date('Y-m');

// Désirs ponctuels du mois en cours
$desirs = Db::fetchAll(
    "SELECT d.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
            ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur
     FROM desirs d
     LEFT JOIN users u2 ON u2.id = d.valide_par
     LEFT JOIN horaires_types ht ON ht.id = d.horaire_type_id
     WHERE d.user_id = ? AND d.mois_cible = ?
     ORDER BY d.date_souhaitee DESC",
    [$uid, $moisCourant]
);

// Horaires types actifs
$horaires = Db::fetchAll(
    "SELECT id, code, nom, heure_debut, heure_fin, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code"
);

// Config max désirs par mois
$maxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: 4);

// Désirs permanents
$_permanentsRaw = Db::fetchAll(
    "SELECT dp.*, ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
            dp2.jour_semaine AS ancien_jour_semaine, dp2.type AS ancien_type,
            dp2.horaire_type_id AS ancien_horaire_type_id, dp2.detail AS ancien_detail,
            ht2.code AS ancien_horaire_code, ht2.nom AS ancien_horaire_nom, ht2.couleur AS ancien_horaire_couleur
     FROM desirs_permanents dp
     LEFT JOIN horaires_types ht ON ht.id = dp.horaire_type_id
     LEFT JOIN desirs_permanents dp2 ON dp2.id = dp.replaces_id
     LEFT JOIN horaires_types ht2 ON ht2.id = dp2.horaire_type_id
     WHERE dp.user_id = ?
     ORDER BY dp.jour_semaine",
    [$uid]
);
$_pendingIds = array_column(
    Db::fetchAll("SELECT replaces_id FROM desirs_permanents WHERE user_id = ? AND replaces_id IS NOT NULL AND statut = 'en_attente'", [$uid]),
    'replaces_id'
);
$permanents = array_map(function($p) use ($_pendingIds) {
    $p['has_pending_modification'] = in_array($p['id'], $_pendingIds) ? 1 : 0;
    return $p;
}, $_permanentsRaw);

// Filtrer les permanents visibles (exclure modifs refusées)
$permanentsVisibles = array_filter($permanents, fn($p) => !($p['statut'] === 'refuse' && $p['replaces_id']));

// Stats calculées
$nonRefused = array_filter($desirs, fn($d) => empty($d['permanent_id']) && $d['statut'] !== 'refuse');
$nEnAttente = count(array_filter($nonRefused, fn($d) => $d['statut'] === 'en_attente'));
$nValides   = count(array_filter($nonRefused, fn($d) => $d['statut'] === 'valide'));
$permActifs = count(array_filter($permanents, fn($p) => $p['is_active'] && $p['statut'] === 'valide' && !$p['replaces_id']));
$permAttente = count(array_filter($permanents, fn($p) => $p['statut'] === 'en_attente' && !$p['replaces_id']));
$permQuota  = count(array_filter($permanents, fn($p) => !$p['replaces_id'] && $p['statut'] !== 'refuse' && $p['is_active'] !== 0));
$consumed   = count($nonRefused) + $permQuota;
$remaining  = max(0, $maxDesirs - $consumed);

$joursComplets = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$joursShort    = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
$monthNames    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
[$anneeCur, $moisCurNum] = explode('-', $moisCourant);
$moisLabel = $monthNames[(int)$moisCurNum - 1] . ' ' . $anneeCur;
?>
<div class="page-header">
  <h1 class="page-title"><i class="bi bi-star"></i> Mes Desirs</h1>
  <p class="text-muted small">Soumettez jusqu'a <?= (int)$maxDesirs ?> desirs par mois</p>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-3">
    <?= render_stat_card('Desirs restants', $remaining . '/' . $maxDesirs, 'bi-calendar-check', 'teal',
        $remaining === 0
            ? 'quota atteint'
            : count($nonRefused) . ' ponctuel' . (count($nonRefused) > 1 ? 's' : '') . ' + ' . $permQuota . ' perm.'
    ) ?>
    <?= render_stat_card('En attente', $nEnAttente, 'bi-hourglass-split', 'orange', 'de validation') ?>
    <?= render_stat_card('Valides', $nValides, 'bi-check-circle', 'green', 'ce mois') ?>
    <?= render_stat_card('Permanents', $permActifs, 'bi-pin-angle-fill', 'purple',
        $permAttente > 0 ? $permAttente . ' en attente' : 'actifs'
    ) ?>
</div>

<div class="d-flex gap-2 flex-wrap">
  <!-- Formulaire -->
  <div class="card" id="desirFormCard">
    <div class="card-header">
      <h3>Nouveau desir</h3>
    </div>
    <div class="card-body">
      <!-- Mode toggle -->
      <div class="form-group">
        <div class="mode-toggle">
          <button type="button" class="mode-toggle-btn active" data-mode="ponctuel">
            <i class="bi bi-calendar-event"></i> Ce mois
          </button>
          <button type="button" class="mode-toggle-btn" data-mode="permanent">
            <i class="bi bi-pin-angle"></i> Permanent
          </button>
        </div>
      </div>

      <!-- PONCTUEL: Calendar picker -->
      <div id="ponctuelFields">
        <div class="form-group">
          <label class="form-label d-flex justify-content-between align-items-center gap-2">
            <button type="button" id="calPrevBtn" class="desir-cal-nav" title="Mois precedent"><i class="bi bi-chevron-left"></i></button>
            <span id="calMonthLabel" class="flex-grow-1 text-center"></span>
            <button type="button" id="calNextBtn" class="desir-cal-nav" title="Mois suivant"><i class="bi bi-chevron-right"></i></button>
            <span class="desir-count-info" id="calCountInfo"></span>
          </label>
          <div class="desir-calendar" id="desirCalendar"></div>
        </div>
        <div class="alert alert-info desir-banner-readonly ss-hide" id="desirReadOnlyBanner">
          <i class="bi bi-eye"></i> <strong>Consultation historique</strong> -- les desirs ne peuvent etre crees que pour le mois courant + 1
        </div>
        <div class="alert alert-info desir-banner-info">
          <i class="bi bi-info-circle"></i> <strong>Badge solde</strong> en haut affiche les jours restants pour ce mois
        </div>
      </div>

      <!-- PERMANENT: Jour semaine -->
      <div id="permanentFields" class="ss-hide">
        <div class="form-group">
          <label class="form-label">Jour de la semaine</label>
          <select class="form-control" id="desirJourSemaine">
            <option value="1">Lundi</option>
            <option value="2">Mardi</option>
            <option value="3">Mercredi</option>
            <option value="4">Jeudi</option>
            <option value="5">Vendredi</option>
            <option value="6">Samedi</option>
            <option value="0">Dimanche</option>
          </select>
        </div>
        <div class="alert alert-warning desir-banner-perm">
          <i class="bi bi-pin-angle-fill"></i> Sera applique <strong>chaque mois</strong> automatiquement une fois valide par votre responsable.
        </div>
      </div>

      <!-- Type -->
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-control" id="desirType">
          <option value="jour_off">Jour off</option>
          <option value="horaire_special">Horaire special</option>
        </select>
      </div>

      <!-- Horaire visual picker -->
      <div class="form-group ss-hide" id="desirHoraireGroup">
        <label class="form-label">Horaire souhaite</label>
        <div id="horairesList" class="horaires-grid">
          <?php foreach ($horaires as $hor):
              $c = h($hor['couleur'] ?: '#1a1a1a');
              $fmtD = substr($hor['heure_debut'] ?? '', 0, 5);
              $fmtF = substr($hor['heure_fin'] ?? '', 0, 5);
          ?>
            <button type="button" class="horaire-chip" data-id="<?= h($hor['id']) ?>" data-tooltip="<?= h($hor['nom'] ?? '') ?>">
                <span class="horaire-chip-code" data-bg="<?= $c ?>"><?= h($hor['code'] ?? '') ?></span>
                <span class="horaire-chip-time"><?= h($fmtD) ?><br><?= h($fmtF) ?></span>
            </button>
          <?php endforeach ?>
        </div>
        <input type="hidden" id="desirHoraireId">
      </div>

      <!-- Detail -->
      <div class="form-group ss-hide" id="desirDetailGroup">
        <label class="form-label">Commentaire (optionnel)</label>
        <textarea class="form-control" id="desirDetail" placeholder="Ex: rendez-vous medical..." rows="2"></textarea>
      </div>

      <button type="button" class="btn btn-dark desir-submit-btn" id="desirSubmitBtn" disabled>
        <i class="bi bi-send"></i> Soumettre
      </button>
    </div>
  </div>

  <!-- Right panel: liste du mois + permanents -->
  <div class="desir-right-panel">
    <div class="card">
      <div class="card-header">
        <h3 id="desirsListTitle">Desirs -- <?= h($moisLabel) ?></h3>
      </div>
      <!-- Detail du desir selectionne -->
      <div id="desirDetailPanel" class="desir-detail-panel ss-hide"></div>
      <div class="card-body p-0">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th class="desir-col-when">Date</th>
                <th class="desir-col-type">Type</th>
                <th class="desir-col-horaire">Horaire</th>
                <th class="desir-col-detail">Detail</th>
                <th class="desir-col-statut">Statut</th>
                <th class="desir-col-actions">Actions</th>
              </tr>
            </thead>
            <tbody id="desirsTableBody">
              <?php if (empty($desirs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucun desir pour ce mois</td></tr>
              <?php else: foreach ($desirs as $d):
                  $isPerm = !empty($d['permanent_id']);
                  $date = new DateTime($d['date_souhaitee']);
                  $dateFmt = $joursShort[(int)$date->format('w')] . ' ' . (int)$date->format('d');
                  $typeBadge = $d['type'] === 'jour_off'
                      ? '<span class="badge badge-info">Jour off</span>'
                      : '<span class="badge badge-purple">Horaire</span>';
                  $permIcon = $isPerm
                      ? ' <span class="badge desir-badge-perm"><i class="bi bi-pin-angle-fill"></i></span>'
                      : '';
                  $horaireCell = '';
                  if ($d['horaire_code']) {
                      $c = h($d['horaire_couleur'] ?: '#9B51E0');
                      $horaireCell = '<span class="badge des-horaire-badge" data-bg="' . $c . '">' . h($d['horaire_code']) . '</span>';
                  }
              ?>
                <tr class="desir-table-row" data-date-row="<?= h($d['date_souhaitee']) ?>">
                  <td><?= h($dateFmt) ?></td>
                  <td><?= $typeBadge ?><?= $permIcon ?></td>
                  <td><?= $horaireCell ?></td>
                  <td><small><?= h($d['detail'] ?? '') ?></small></td>
                  <td><?= render_statut_badge($d['statut']) ?></td>
                  <td>
                    <?php if ($d['statut'] === 'en_attente'): ?>
                      <?php if (!$isPerm): ?>
                        <button class="btn btn-sm btn-desir-edit me-1" data-edit-desir="<?= h($d['id']) ?>" data-date="<?= h($d['date_souhaitee']) ?>" data-type="<?= h($d['type']) ?>" data-horaire="<?= h($d['horaire_type_id'] ?? '') ?>" data-detail="<?= h($d['detail'] ?? '') ?>" title="Modifier"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-desir-delete" data-delete-desir="<?= h($d['id']) ?>" title="Supprimer"><i class="bi bi-trash"></i></button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-desir-edit" data-edit-permanent="<?= h($d['permanent_id']) ?>" data-jour="<?= h($d['jour_semaine'] ?? '') ?>" data-type="<?= h($d['type']) ?>" data-horaire="<?= h($d['horaire_type_id'] ?? '') ?>" data-detail="<?= h($d['detail'] ?? '') ?>" title="Modifier"><i class="bi bi-pencil"></i></button>
                      <?php endif ?>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach; endif ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-header desir-perm-header">
        <h3><i class="bi bi-pin-angle"></i> Mes desirs permanents</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th class="desir-col-when">Jour</th>
                <th class="desir-col-type">Type</th>
                <th class="desir-col-horaire">Horaire</th>
                <th class="desir-col-detail">Detail</th>
                <th class="desir-col-statut">Statut</th>
                <th class="desir-col-actions">Actions</th>
              </tr>
            </thead>
            <tbody id="permanentsTableBody">
              <?php if (empty($permanentsVisibles)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Aucun desir permanent</td></tr>
              <?php else: foreach ($permanentsVisibles as $p):
                  $jour = $joursComplets[$p['jour_semaine']] ?? '?';
                  $typeBadge = $p['type'] === 'jour_off'
                      ? '<span class="badge badge-info">Jour off</span>'
                      : '<span class="badge badge-purple">Horaire</span>';
                  $horaireCell = '';
                  if ($p['horaire_code']) {
                      $c = h($p['horaire_couleur'] ?: '#9B51E0');
                      $horaireCell = '<span class="badge des-horaire-badge" data-bg="' . $c . '">' . h($p['horaire_code']) . '</span>';
                  }
                  $detailCell = $p['detail'] ? '<small>' . h($p['detail']) . '</small>' : '<small class="text-muted">--</small>';

                  // Statut + actions
                  $statutHtml = '';
                  $actionsHtml = '';
                  $editBtn = '<button class="btn btn-sm btn-desir-edit me-1" data-edit-perm="' . h($p['id']) . '" data-jour="' . (int)$p['jour_semaine'] . '" data-type="' . h($p['type']) . '" data-horaire="' . h($p['horaire_type_id'] ?? '') . '" data-detail="' . h($p['detail'] ?? '') . '" title="Modifier"><i class="bi bi-pencil"></i></button>';
                  $deleteBtn = '<button class="btn btn-sm btn-desir-delete" data-del-perm="' . h($p['id']) . '" title="Supprimer"><i class="bi bi-trash"></i></button>';

                  if ($p['statut'] === 'en_attente' && $p['replaces_id']) {
                      $statutHtml = '<span class="badge desir-badge-modif"><i class="bi bi-pencil-square"></i> Modification en attente</span>';
                      $actionsHtml = '<button class="btn btn-sm btn-desir-delete" data-cancel-modif="' . h($p['id']) . '" title="Annuler la modification"><i class="bi bi-x-lg"></i></button>';
                  } elseif ($p['statut'] === 'en_attente') {
                      $statutHtml = '<span class="badge desir-badge-pending"><i class="bi bi-hourglass-split"></i> En attente</span>';
                      $actionsHtml = $editBtn . $deleteBtn;
                  } elseif ($p['is_active'] && $p['statut'] === 'valide') {
                      $statutHtml = '<span class="badge badge-success">Actif</span>';
                      if ($p['has_pending_modification']) {
                          $statutHtml .= ' <span class="badge desir-badge-modif-small"><i class="bi bi-pencil-square"></i> Modif. en attente</span>';
                      }
                      $actionsHtml = $deleteBtn;
                  } elseif ($p['statut'] === 'refuse') {
                      $statutHtml = '<span class="badge badge-danger">Refuse</span>';
                      if (!empty($p['commentaire_chef'])) {
                          $statutHtml .= ' <small class="text-muted" title="' . h($p['commentaire_chef']) . '"><i class="bi bi-chat-dots"></i></small>';
                      }
                  } else {
                      $statutHtml = '<span class="badge badge-secondary">Inactif</span>';
                  }
              ?>
                <tr>
                  <td><strong><?= h($jour) ?></strong></td>
                  <td><?= $typeBadge ?></td>
                  <td><?= $horaireCell ?></td>
                  <td><?= $detailCell ?></td>
                  <td><?= $statutHtml ?></td>
                  <td><?= $actionsHtml ?></td>
                </tr>
              <?php endforeach; endif ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal confirmation suppression -->
<div class="desir-confirm-overlay ss-hide" id="desirConfirmModal">
  <div class="desir-confirm-box">
    <div class="desir-confirm-header">
      <div class="desir-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <h3 id="desirConfirmTitle">Supprimer ce desir ?</h3>
    </div>
    <p id="desirConfirmMessage" class="desir-confirm-msg">Cette action est irreversible.</p>
    <div class="desir-confirm-actions">
      <button type="button" class="btn-desir-cancel" id="desirConfirmCancel">Annuler</button>
      <button type="button" class="btn-desir-confirm" id="desirConfirmOk"><i class="bi bi-trash"></i> Supprimer</button>
    </div>
  </div>
</div>

<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'desirs'     => $desirs,
    'permanents' => $permanents,
    'horaires'   => $horaires,
    'max_desirs' => $maxDesirs,
    'mois'       => $moisCourant,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
