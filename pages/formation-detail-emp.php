<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$userId = $_SESSION['ss_user']['id'];
$formId = $_GET['id'] ?? '';

$formation = $formId ? Db::fetch(
    "SELECT * FROM formations WHERE id = ?", [$formId]
) : null;

if (!$formation) {
    echo '<div class="page-wrap">';
    echo render_page_header('Formation introuvable', 'bi-question-circle', 'formations', 'Mes formations');
    echo '<div class="alert alert-warning">La formation demandée n\'existe pas ou a été supprimée.</div>';
    echo '</div>';
    return;
}

// Participation user
$myParticipation = Db::fetch(
    "SELECT id, statut, certificat_url FROM formation_participants
     WHERE formation_id = ? AND user_id = ?",
    [$formId, $userId]
);

// Collègues inscrits
$collegues = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.photo, u.email, fn.nom AS fonction, p.statut
     FROM formation_participants p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE p.formation_id = ? AND u.id != ? AND u.is_active = 1
     ORDER BY u.nom, u.prenom",
    [$formId, $userId]
);

// Adresse EMS
$emsAdresse = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_adresse'") ?: '';
$emsNpa     = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_npa'") ?: '';
$emsVille   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_ville'") ?: '';
$emsAdresseFull = trim($emsAdresse . ', ' . $emsNpa . ' ' . $emsVille, ', ');

// Adresse user perso
$userAdr = Db::fetch(
    "SELECT adresse_rue, adresse_complement, adresse_cp, adresse_ville
     FROM users WHERE id = ?", [$userId]
);
$userAdrFull = '';
if ($userAdr && $userAdr['adresse_rue']) {
    $userAdrFull = trim($userAdr['adresse_rue'] . ', ' . $userAdr['adresse_cp'] . ' ' . $userAdr['adresse_ville'], ', ');
}

// Date affichage
$dateAffichee = $formation['date_debut']
    ? DateTime::createFromFormat('Y-m-d', $formation['date_debut'])->format('d/m/Y')
    : 'Date à confirmer';
$dateFinAffichee = $formation['date_fin'] && $formation['date_fin'] !== $formation['date_debut']
    ? DateTime::createFromFormat('Y-m-d', $formation['date_fin'])->format('d/m/Y')
    : null;

// Liens Google Maps
$lieuFormation = $formation['lieu'] ?: '';
$gmapView = $lieuFormation ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($lieuFormation) : '';
$gmapFromEms = ($lieuFormation && $emsAdresseFull)
    ? 'https://www.google.com/maps/dir/?api=1&travelmode=transit&origin=' . urlencode($emsAdresseFull) . '&destination=' . urlencode($lieuFormation)
    : '';
$gmapFromMe = ($lieuFormation && $userAdrFull)
    ? 'https://www.google.com/maps/dir/?api=1&travelmode=transit&origin=' . urlencode($userAdrFull) . '&destination=' . urlencode($lieuFormation)
    : '';
?>
<div class="page-wrap">
  <?= render_page_header(
      $formation['titre'],
      'bi-mortarboard',
      'formations',
      'Mes formations'
  ) ?>

  <div class="row g-3">
    <!-- Colonne principale -->
    <div class="col-lg-8">

      <!-- Hero formation -->
      <div class="card mb-3">
        <?php if ($formation['image_url']): ?>
          <img src="<?= h($formation['image_url']) ?>" alt="" style="width:100%;max-height:240px;object-fit:cover;border-radius:12px 12px 0 0">
        <?php endif ?>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
            <h3 class="mb-0"><?= h($formation['titre']) ?></h3>
            <?php if ($myParticipation): ?>
              <?php
                $statBadge = match($myParticipation['statut']) {
                    'inscrit' => ['Inscrit', 'primary'],
                    'present' => ['Présent', 'success'],
                    'valide'  => ['Validée', 'success'],
                    'absent'  => ['Absent',  'danger'],
                    default   => ['—', 'secondary'],
                };
              ?>
              <span class="badge text-bg-<?= $statBadge[1] ?> fs-6"><?= h($statBadge[0]) ?></span>
            <?php endif ?>
          </div>

          <div class="d-flex gap-3 flex-wrap text-muted small mb-3">
            <span><i class="bi bi-calendar3"></i> <?= h($dateAffichee) ?><?= $dateFinAffichee ? ' → ' . h($dateFinAffichee) : '' ?></span>
            <?php if ($formation['duree_heures']): ?>
              <span><i class="bi bi-clock"></i> <?= h($formation['duree_heures']) ?>h</span>
            <?php endif ?>
            <?php if ($formation['type']): ?>
              <span><i class="bi bi-tag"></i> <?= h(ucfirst($formation['type'])) ?></span>
            <?php endif ?>
            <?php if ($formation['modalite']): ?>
              <span><i class="bi bi-display"></i> <?= h($formation['modalite']) ?></span>
            <?php endif ?>
            <?php if ($formation['max_participants']): ?>
              <span><i class="bi bi-people"></i> Max <?= h($formation['max_participants']) ?> places</span>
            <?php endif ?>
          </div>

          <?php if ($formation['description']): ?>
            <div class="mb-3">
              <strong class="d-block small text-muted text-uppercase mb-1">Description</strong>
              <div><?= nl2br(h($formation['description'])) ?></div>
            </div>
          <?php endif ?>

          <?php if ($formation['objectifs']): ?>
            <div class="mb-3">
              <strong class="d-block small text-muted text-uppercase mb-1">Objectifs</strong>
              <div><?= nl2br(h($formation['objectifs'])) ?></div>
            </div>
          <?php endif ?>

          <?php if ($formation['public_cible']): ?>
            <div class="mb-3">
              <strong class="d-block small text-muted text-uppercase mb-1">Public cible</strong>
              <div><?= nl2br(h($formation['public_cible'])) ?></div>
            </div>
          <?php endif ?>

          <?php if ($formation['intervenants']): ?>
            <div class="mb-3">
              <strong class="d-block small text-muted text-uppercase mb-1">Intervenants</strong>
              <div><?= nl2br(h($formation['intervenants'])) ?></div>
            </div>
          <?php endif ?>

          <?php if ($formation['source_url']): ?>
            <div>
              <a href="<?= h($formation['source_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right"></i> Voir la fiche FEGEMS originale
              </a>
            </div>
          <?php endif ?>
        </div>
      </div>

      <!-- Plan d'accès -->
      <?php if ($lieuFormation): ?>
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Plan d'accès</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <strong>Adresse :</strong> <?= h($lieuFormation) ?>
          </div>

          <!-- Carte embed -->
          <div class="ratio ratio-16x9 mb-3 rounded overflow-hidden">
            <iframe
              src="https://www.google.com/maps?q=<?= urlencode($lieuFormation) ?>&output=embed"
              style="border:0"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen></iframe>
          </div>

          <!-- Boutons itinéraire -->
          <div class="d-flex gap-2 flex-wrap">
            <?php if ($gmapView): ?>
              <a href="<?= h($gmapView) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-map"></i> Voir sur Google Maps
              </a>
            <?php endif ?>
            <?php if ($gmapFromEms): ?>
              <a href="<?= h($gmapFromEms) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-bus-front"></i> Itinéraire depuis l'EMS (transports)
              </a>
            <?php endif ?>
            <?php if ($gmapFromMe): ?>
              <a href="<?= h($gmapFromMe) ?>" target="_blank" class="btn btn-sm btn-primary">
                <i class="bi bi-house"></i> Itinéraire depuis chez moi
              </a>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" id="addAdresseBtn" type="button">
                <i class="bi bi-plus-lg"></i> Ajouter mon adresse perso
              </button>
            <?php endif ?>
          </div>

          <?php if (!$gmapFromMe): ?>
            <div class="alert alert-info small mt-3 mb-0">
              <i class="bi bi-info-circle"></i>
              Renseignez votre adresse personnelle pour calculer un itinéraire depuis chez vous.
              <a href="?page=profile" data-link="profile">Aller dans Mon profil</a>.
            </div>
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

    </div>

    <!-- Colonne droite -->
    <div class="col-lg-4">

      <!-- Collègues inscrits -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="bi bi-people"></i> Collègues inscrits</h5>
          <span class="text-muted small"><?= count($collegues) ?></span>
        </div>
        <?php if ($collegues): ?>
          <div class="list-group list-group-flush">
            <?php foreach ($collegues as $c):
              $initials = strtoupper(mb_substr($c['prenom'], 0, 1) . mb_substr($c['nom'], 0, 1));
            ?>
              <div class="list-group-item d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input m-0 covoit-cb" value="<?= h($c['id']) ?>" data-name="<?= h($c['prenom'] . ' ' . $c['nom']) ?>">
                <?php if ($c['photo']): ?>
                  <img src="<?= h($c['photo']) ?>" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover">
                <?php else: ?>
                  <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:linear-gradient(135deg,#bcd2cb,#2d4a43);color:#fff;font-weight:600;font-size:.78rem"><?= h($initials) ?></div>
                <?php endif ?>
                <div class="flex-grow-1 min-width-0">
                  <div class="fw-bold small"><?= h($c['prenom'] . ' ' . $c['nom']) ?></div>
                  <div class="text-muted" style="font-size:.74rem"><?= h($c['fonction'] ?: '—') ?></div>
                </div>
              </div>
            <?php endforeach ?>
          </div>
          <div class="card-body">
            <button class="btn btn-primary btn-sm w-100" id="covoitBtn" disabled>
              <i class="bi bi-car-front"></i> Organiser un covoiturage
              <span class="badge bg-light text-dark ms-1" id="covoitCount">0</span>
            </button>
            <div class="text-muted small text-center mt-2">
              Cochez les collègues à inviter au covoiturage.
            </div>
          </div>
        <?php else: ?>
          <div class="card-body text-muted small text-center py-4">
            <i class="bi bi-people" style="font-size:1.6rem;opacity:.3;display:block;margin-bottom:6px"></i>
            Aucun collègue inscrit pour le moment.
          </div>
        <?php endif ?>
      </div>

      <!-- Tarifs si dispo -->
      <?php if ($formation['tarif_membres'] || $formation['tarif_non_membres']): ?>
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Tarifs</h5></div>
        <div class="card-body small">
          <?php if ($formation['tarif_membres']): ?>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Membres FEGEMS</span>
              <strong><?= h($formation['tarif_membres']) ?></strong>
            </div>
          <?php endif ?>
          <?php if ($formation['tarif_non_membres']): ?>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Non-membres</span>
              <strong><?= h($formation['tarif_non_membres']) ?></strong>
            </div>
          <?php endif ?>
          <?php if ($formation['tarif_externes']): ?>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Externes</span>
              <strong><?= h($formation['tarif_externes']) ?></strong>
            </div>
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

    </div>
  </div>
</div>

<!-- Modal covoiturage -->
<div class="modal fade" id="covoitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-car-front"></i> Organiser un covoiturage</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Vous proposez un covoiturage à :</p>
        <div id="covoitNamesPreview" class="mb-3"></div>

        <label class="form-label small">Message (optionnel)</label>
        <textarea class="form-control" id="covoitMessage" rows="4"
                  placeholder="Ex: Je pars de l'EMS à 7h30, j'ai 3 places. RDV au parking ?"></textarea>

        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-info-circle"></i>
          Un message sera envoyé via la messagerie interne aux collègues sélectionnés.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary btn-sm" id="covoitSendBtn">
          <i class="bi bi-send"></i> Envoyer la proposition
        </button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="formationId" value="<?= h($formId) ?>">
