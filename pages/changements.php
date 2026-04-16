<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$currentMois = date('Y-m');

// ─── Mon planning du mois courant ─────────────────────────────────────────────
$planning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$currentMois]);
$myPlanning = [];
if ($planning) {
    $myPlanning = Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.date_jour, pa.statut AS assign_statut,
                ht.id AS horaire_type_id, ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.couleur, ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom, m.code AS module_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.user_id = ?
         ORDER BY pa.date_jour",
        [$planning['id'], $uid]
    );
}

// ─── Collègues ────────────────────────────────────────────────────────────────
$userRow = Db::fetch("SELECT fonction_id FROM users WHERE id = ?", [$uid]);
$fonctionId = $userRow['fonction_id'] ?? null;
$allCollegues = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.fonction_id,
            f.nom AS fonction_nom, f.code AS fonction_code,
            m.nom AS module_nom, m.code AS module_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN (
         SELECT pa.user_id, pa.module_id
         FROM planning_assignations pa
         WHERE pa.module_id IS NOT NULL
         ORDER BY pa.date_jour DESC
         LIMIT 1000
     ) last_pa ON last_pa.user_id = u.id
     LEFT JOIN modules m ON m.id = last_pa.module_id
     WHERE u.id != ? AND u.is_active = 1
     GROUP BY u.id
     ORDER BY u.nom, u.prenom",
    [$uid]
);

// ─── Mes changements ─────────────────────────────────────────────────────────
$changements = Db::fetchAll(
    "SELECT ch.*,
            ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom,
            ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom,
            ht_dem.code AS horaire_demandeur_code, ht_dem.nom AS horaire_demandeur_nom, ht_dem.couleur AS horaire_demandeur_couleur,
            ht_dest.code AS horaire_destinataire_code, ht_dest.nom AS horaire_destinataire_nom, ht_dest.couleur AS horaire_destinataire_couleur,
            m_dem.nom AS module_demandeur_nom, m_dest.nom AS module_destinataire_nom,
            ut.prenom AS traite_par_prenom, ut.nom AS traite_par_nom
     FROM changements_horaire ch
     JOIN users ud ON ud.id = ch.demandeur_id
     JOIN users ude ON ude.id = ch.destinataire_id
     JOIN planning_assignations pa_dem ON pa_dem.id = ch.assignation_demandeur_id
     JOIN planning_assignations pa_dest ON pa_dest.id = ch.assignation_destinataire_id
     LEFT JOIN horaires_types ht_dem ON ht_dem.id = pa_dem.horaire_type_id
     LEFT JOIN horaires_types ht_dest ON ht_dest.id = pa_dest.horaire_type_id
     LEFT JOIN modules m_dem ON m_dem.id = pa_dem.module_id
     LEFT JOIN modules m_dest ON m_dest.id = pa_dest.module_id
     LEFT JOIN users ut ON ut.id = ch.traite_par
     WHERE ch.demandeur_id = ? OR ch.destinataire_id = ?
     ORDER BY ch.created_at DESC",
    [$uid, $uid]
);

// ─── Stats ────────────────────────────────────────────────────────────────────
$nTotal     = count($changements);
$nAttente   = count(array_filter($changements, fn($c) => $c['statut'] === 'en_attente_collegue'));
$nAttenteAdmin = count(array_filter($changements, fn($c) => $c['statut'] === 'confirme_collegue'));
$nValide    = count(array_filter($changements, fn($c) => $c['statut'] === 'valide'));
$nRefuse    = count(array_filter($changements, fn($c) => $c['statut'] === 'refuse'));

// ─── Helpers ──────────────────────────────────────────────────────────────────
$STATUT_MAP = [
    'en_attente_collegue' => ['badge-pending', 'En attente'],
    'confirme_collegue'   => ['badge-info',    'Attente admin'],
    'valide'              => ['badge-success',  'Validé'],
    'refuse'              => ['badge-refused',  'Refusé'],
];

function chg_badge_html($code, $couleur, $moduleName = null) {
    $bg = h($couleur ?: '#6c757d');
    $html = '<span class="badge" style="background:' . $bg . ';color:#fff">' . h($code ?: '?') . '</span>';
    if ($moduleName) $html .= ' <small class="text-muted">' . h($moduleName) . '</small>';
    return $html;
}

function chg_fmt_datetime($dateStr) {
    if (!$dateStr) return '';
    try {
        $d = new DateTime($dateStr);
        return $d->format('j') . ' ' . mb_strtolower(strftime('%b', $d->getTimestamp())) . ' à ' . $d->format('H:i');
    } catch (Exception $e) {
        return h($dateStr);
    }
}
?>

<?= render_page_header("Changements d'horaire", 'bi-arrow-left-right') ?>

<!-- Stats cards -->
<div class="row g-3 mb-3">
    <?= render_stat_card('Total', $nTotal, 'bi-arrow-left-right', 'neutral') ?>
    <?= render_stat_card('Attente collègue', $nAttente, 'bi-clock-history', 'orange', $nAttente ? 'à confirmer' : null) ?>
    <?= render_stat_card('Attente admin', $nAttenteAdmin, 'bi-hourglass-split', 'purple', $nAttenteAdmin ? 'validation direction' : null) ?>
    <?= render_stat_card('Validés', $nValide, 'bi-check-circle', 'teal') ?>
    <?= render_stat_card('Refusés', $nRefuse, 'bi-x-circle', 'red') ?>
</div>

<!-- ── Top row: calendrier + liste demandes ── -->
<div class="chg-top-row">
  <!-- Mon calendrier -->
  <div class="card chg-top-cal">
    <div class="card-header">
      <h3><i class="bi bi-calendar3"></i> Mon planning</h3>
    </div>
    <div class="card-body">
      <div class="chg-cal-nav">
        <button class="btn btn-sm btn-outline-secondary" id="chgMyPrev"><i class="bi bi-chevron-left"></i></button>
        <span class="chg-cal-month" id="chgMyMonth"></span>
        <button class="btn btn-sm btn-outline-secondary" id="chgMyNext"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="chg-calendar" id="chgMyCal"></div>
      <div class="chg-cal-hint chg-hidden" id="chgMyHint">
        <i class="bi bi-hand-index"></i> Cliquez sur un jour pour proposer un échange
      </div>
    </div>
  </div>

  <!-- Mes demandes -->
  <div class="card chg-top-list">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3><i class="bi bi-list-check"></i> Mes demandes</h3>
      <span class="badge badge-neutral" id="chgListCount"><?= $nTotal ?></span>
    </div>
    <div class="card-body chg-list-body">
      <div id="changementsList">
        <?php if (!$changements): ?>
          <div class="text-center text-muted py-4">Aucune demande</div>
        <?php else: foreach ($changements as $ch):
          $iAmDemandeur    = $ch['demandeur_id'] === $uid;
          $iAmDestinataire = $ch['destinataire_id'] === $uid;
          $sb = $STATUT_MAP[$ch['statut']] ?? ['badge-info', $ch['statut']];

          $demandeurName    = h($ch['demandeur_prenom']) . ' ' . h($ch['demandeur_nom']);
          $destinataireName = h($ch['destinataire_prenom']) . ' ' . h($ch['destinataire_nom']);

          $horDem  = chg_badge_html($ch['horaire_demandeur_code'], $ch['horaire_demandeur_couleur'], $ch['module_demandeur_nom']);
          $horDest = chg_badge_html($ch['horaire_destinataire_code'], $ch['horaire_destinataire_couleur'], $ch['module_destinataire_nom']);

          $dateDem  = $ch['date_demandeur'] ?? $ch['date_jour'] ?? '';
          $dateDest = $ch['date_destinataire'] ?? $ch['date_jour'] ?? '';

          $hasDetails = !empty($ch['motif']) || !empty($ch['raison_refus']);

          $createdDate = '';
          if (!empty($ch['created_at'])) {
              try { $createdDate = (new DateTime($ch['created_at']))->format('j M à H:i'); } catch (Exception $e) {}
          }
          $updatedDate = '';
          if (!empty($ch['updated_at'])) {
              try { $updatedDate = (new DateTime($ch['updated_at']))->format('j M à H:i'); } catch (Exception $e) {}
          }

          $roleTag = $iAmDemandeur
              ? '<span class="chg-role-tag demand">Demandé</span>'
              : '<span class="chg-role-tag invite">Reçu</span>';
        ?>
          <div class="chg-item">
            <div class="chg-item-header">
              <div class="chg-item-date">
                <i class="bi bi-calendar3"></i>
                <?= h(fmt_date_fr($dateDem)) ?> <i class="bi bi-arrow-left-right chg-date-arrow"></i> <?= h(fmt_date_fr($dateDest)) ?>
              </div>
              <?= $roleTag ?>
              <span class="badge <?= h($sb[0]) ?>"><?= h($sb[1]) ?></span>
            </div>
            <div class="chg-exchange<?php if ($hasDetails) echo ' chg-exchange-has-details' ?>">
              <div class="chg-person">
                <div class="chg-person-name"><?= $demandeurName ?><?php if ($iAmDemandeur): ?> <span class="chg-person-you">(vous)</span><?php endif ?></div>
                <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> <?= $horDem ?></div>
              </div>
              <div class="chg-arrow"><i class="bi bi-arrow-left-right"></i></div>
              <div class="chg-person">
                <div class="chg-person-name"><?= $destinataireName ?><?php if ($iAmDestinataire): ?> <span class="chg-person-you">(vous)</span><?php endif ?></div>
                <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> <?= $horDest ?></div>
              </div>
              <?php if ($hasDetails): ?>
                <button class="chg-details-toggle" data-toggle-details="<?= h($ch['id']) ?>" title="Voir les détails"><i class="bi bi-plus-lg"></i></button>
              <?php endif ?>
            </div>
            <?php if ($ch['statut'] === 'en_attente_collegue' && $iAmDestinataire): ?>
              <div class="d-flex gap-1 mt-2">
                <button class="btn btn-success btn-sm" data-confirm="<?= h($ch['id']) ?>"><i class="bi bi-check-lg"></i> Accepter</button>
                <button class="btn btn-danger btn-sm" data-refuse="<?= h($ch['id']) ?>"><i class="bi bi-x-lg"></i> Refuser</button>
              </div>
            <?php elseif ($ch['statut'] === 'en_attente_collegue' && $iAmDemandeur): ?>
              <div class="d-flex gap-1 mt-2">
                <button class="btn btn-light btn-sm" data-annuler="<?= h($ch['id']) ?>"><i class="bi bi-trash"></i> Annuler</button>
              </div>
            <?php endif ?>
            <?php if ($hasDetails): ?>
              <div class="chg-details" id="chgDetails-<?= h($ch['id']) ?>">
                <?php if (!empty($ch['motif'])): ?>
                  <div class="chg-info"><i class="bi bi-chat-dots"></i><span><?= h($ch['motif']) ?></span><?php if ($createdDate): ?><span class="chg-info-date"><?= h($createdDate) ?></span><?php endif ?></div>
                <?php endif ?>
                <?php if (!empty($ch['raison_refus'])): ?>
                  <div class="chg-info refus"><i class="bi bi-chat-left-text"></i><span><?= h($ch['raison_refus']) ?></span><?php if ($updatedDate): ?><span class="chg-info-date"><?= h($updatedDate) ?></span><?php endif ?></div>
                <?php endif ?>
              </div>
            <?php endif ?>
          </div>
        <?php endforeach; endif ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Slidedown: collègue + son planning ── -->
<div class="chg-slidedown chg-slide-closed" id="chgSlidedown">
  <div class="chg-slide-header" id="chgSlideHeader">
    <div class="chg-slide-title">
      <i class="bi bi-arrow-left-right"></i>
      Échange du <strong id="chgSlideDate"></strong>
      <span id="chgSlideBadge"></span>
    </div>
    <button class="btn btn-sm btn-light" id="chgSlideClose"><i class="bi bi-x-lg"></i></button>
  </div>

  <div class="chg-slide-row">
    <!-- Liste collègues -->
    <div class="chg-slide-left">
      <div class="chg-slide-search">
        <i class="bi bi-search chg-search-icon"></i>
        <input type="text" class="form-control chg-search-input" id="chgColSearch" placeholder="Rechercher un collègue...">
      </div>
      <div class="chg-colleague-list" id="chgColList"></div>
    </div>

    <!-- Planning collègue ou placeholder -->
    <div class="chg-slide-right" id="chgSlideRight">
      <div class="chg-slide-placeholder" id="chgSlidePlaceholder">
        <i class="bi bi-person-plus"></i>
        <span>Sélectionnez un collègue dans la liste pour afficher son planning</span>
      </div>
      <div class="chg-hidden" id="chgColPanel">
        <div class="chg-col-panel-header" id="chgColPanelHeader"></div>
        <div class="chg-cal-nav">
          <button class="btn btn-sm btn-outline-secondary" id="chgColPrev"><i class="bi bi-chevron-left"></i></button>
          <span class="chg-cal-month" id="chgColMonth"></span>
          <button class="btn btn-sm btn-outline-secondary" id="chgColNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="chg-calendar" id="chgColCal"></div>
        <div class="chg-col-hint">
          <i class="bi bi-hand-index"></i> Cliquez sur le jour à <strong>prendre</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal confirmation ── -->
<div class="modal fade" id="chgConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable chg-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3" id="chgConfirmHeader">
          <i class="bi bi-arrow-left-right"></i>
          <span class="fw-semibold">Confirmer l'échange</span>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center chg-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="chgConfirmBody">
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm chg-btn-envoyer ms-auto px-3" id="chgSubmitBtn"><i class="bi bi-send"></i> Envoyer la demande</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal refus ── -->
<div class="modal fade" id="refusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered chg-modal-dialog-sm">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-x-circle"></i>
          <span class="fw-semibold">Refuser le changement</span>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center chg-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Raison du refus (optionnel)</label>
          <textarea class="form-control" id="refusRaison" rows="2" placeholder="Expliquer la raison..." maxlength="500"></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm chg-btn-refuser ms-auto px-3" id="refusConfirmBtn"><i class="bi bi-x-circle"></i> Refuser</button>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'my_planning'    => $myPlanning,
    'collegues'      => $allCollegues,
    'changements'    => $changements,
    'my_fonction_id' => $fonctionId,
    'current_mois'   => $currentMois,
], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
