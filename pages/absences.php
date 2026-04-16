<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];

// ─── Données serveur ──────────────────────────────────────────────────────────
$absences = Db::fetchAll(
    "SELECT a.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
            ur.prenom AS remplacement_prenom, ur.nom AS remplacement_nom
     FROM absences a
     LEFT JOIN users u2 ON u2.id = a.valide_par
     LEFT JOIN users ur ON ur.id = a.remplacement_user_id
     WHERE a.user_id = ?
     ORDER BY a.date_debut DESC",
    [$uid]
);

// ─── Stats ────────────────────────────────────────────────────────────────────
$nTotal     = count($absences);
$nAttente   = count(array_filter($absences, fn($a) => $a['statut'] === 'en_attente'));
$nValide    = count(array_filter($absences, fn($a) => $a['statut'] === 'valide'));
$nRefuse    = count(array_filter($absences, fn($a) => $a['statut'] === 'refuse'));
$nJustifie  = count(array_filter($absences, fn($a) => !empty($a['justificatif_path']) || !empty($a['justifie'])));

// Helpers badge
$TYPE_BADGES = [
    'vacances'      => ['badge-info',    'Vacances'],
    'maladie'       => ['badge-refused', 'Maladie'],
    'accident'      => ['badge-refused', 'Accident'],
    'conge_special' => ['badge-purple',  'Congé spécial'],
    'formation'     => ['badge-info',    'Formation'],
    'autre'         => ['badge-pending', 'Autre'],
];
$STATUT_BADGES = [
    'en_attente' => ['badge-pending', 'En attente'],
    'valide'     => ['badge-success', 'Validé'],
    'refuse'     => ['badge-refused', 'Refusé'],
];
?>

<!-- Lightbox -->
<div id="ztLightbox" class="ss-lightbox ss-lightbox-hidden">
  <div class="ss-lightbox-overlay"></div>
  <div class="ss-lightbox-content">
    <button class="ss-lightbox-close" type="button"><i class="bi bi-x-lg"></i></button>
    <div class="ss-lightbox-title" id="ztLbTitle"></div>
    <div class="ss-lightbox-stage" id="ztLbStage"></div>
    <div class="ss-lightbox-toolbar d-none" id="ztLbToolbar">
      <button type="button" class="ss-lb-btn" id="ztLbZoomOut"><i class="bi bi-zoom-out"></i></button>
      <span class="ss-lb-zoom" id="ztLbZoomLevel">100%</span>
      <button type="button" class="ss-lb-btn" id="ztLbZoomIn"><i class="bi bi-zoom-in"></i></button>
      <span class="abs-toolbar-sep"></span>
      <button type="button" class="ss-lb-btn" id="ztLbReset"><i class="bi bi-arrows-angle-contract"></i></button>
    </div>
  </div>
</div>

<?= render_page_header('Mes Absences', 'bi-calendar-x') ?>

<!-- Stats cards -->
<div class="row g-3 mb-3">
    <?= render_stat_card('Total', $nTotal, 'bi-calendar-x', 'neutral') ?>
    <?= render_stat_card('En attente', $nAttente, 'bi-clock-history', 'orange', $nAttente ? 'à traiter' : null) ?>
    <?= render_stat_card('Validées', $nValide, 'bi-check-circle', 'teal') ?>
    <?= render_stat_card('Refusées', $nRefuse, 'bi-x-circle', 'red') ?>
    <?= render_stat_card('Justifiées', $nJustifie, 'bi-file-earmark-check', 'green', $nTotal ? 'sur ' . $nTotal : null) ?>
</div>

<div class="abs-layout">
  <!-- Formulaire -->
  <div class="card abs-form-card">
    <div class="card-header">
      <h3>Nouvelle demande</h3>
    </div>
    <div class="card-body">
      <form id="absenceForm">
        <div class="form-group">
          <label class="form-label">Type d'absence</label>
          <select class="form-control" id="absenceType" required>
            <option value="">— Choisir —</option>
            <option value="vacances">Vacances</option>
            <option value="maladie">Maladie</option>
            <option value="accident">Accident</option>
            <option value="conge_special">Congé spécial</option>
            <option value="formation">Formation</option>
            <option value="autre">Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date de début</label>
          <input type="date" class="form-control" id="absenceDateDebut" required>
        </div>
        <div class="form-group">
          <label class="form-label">Date de fin</label>
          <input type="date" class="form-control" id="absenceDateFin" required>
        </div>
        <div class="form-group">
          <label class="form-label">Commentaire (optionnel)</label>
          <textarea class="form-control" id="absenceCommentaire" placeholder="Informations complémentaires..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Justificatif (optionnel)</label>
          <input type="file" id="absenceJustificatif" accept="image/*,.pdf" class="d-none">
          <div id="absenceDropZone" class="abs-dropzone">
            <div class="abs-dropzone-content" id="absDropContent">
              <i class="bi bi-cloud-arrow-up"></i>
              <span>Glissez ou cliquez ici pour uploader votre justificatif</span>
              <small>Certificat médical, attestation... (JPG, PNG, PDF, max 10 Mo)</small>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send"></i> Soumettre
        </button>
      </form>
    </div>
  </div>

  <!-- Liste -->
  <div class="card abs-list-card">
    <div class="card-header">
      <h3>Mes demandes</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Du</th>
              <th>Au</th>
              <th>Statut</th>
              <th>Justifié</th>
              <th>Remplacement</th>
            </tr>
          </thead>
          <tbody id="absencesTableBody">
            <?php if (!$absences): ?>
              <tr><td colspan="6" class="text-center text-muted abs-empty-cell">Aucune absence enregistrée</td></tr>
            <?php else: foreach ($absences as $a):
              $tb = $TYPE_BADGES[$a['type']] ?? ['badge-info', $a['type']];
              $sb = $STATUT_BADGES[$a['statut']] ?? ['badge-info', $a['statut']];

              $path = $a['justificatif_path'] ?? '';
              $name = $a['justificatif_name'] ?? 'Justificatif';
              $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
              $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif']);
              $isPdf   = $ext === 'pdf';
              $fileType = $isImage ? 'image' : ($isPdf ? 'pdf' : 'other');
            ?>
              <tr>
                <td><span class="badge <?= h($tb[0]) ?>"><?= h($tb[1]) ?></span></td>
                <td><?= h(fmt_date_fr($a['date_debut'])) ?></td>
                <td><?= h(fmt_date_fr($a['date_fin'])) ?></td>
                <td><span class="badge <?= h($sb[0]) ?>"><?= h($sb[1]) ?></span></td>
                <td>
                  <?php if ($path): ?>
                    <a href="#" class="justif-link" data-url="<?= h($path) ?>" data-name="<?= h($name) ?>" data-type="<?= h($fileType) ?>" title="<?= h($name) ?>"><i class="bi bi-check-lg abs-icon-ok"></i></a>
                  <?php elseif (!empty($a['justifie'])): ?>
                    <i class="bi bi-check-lg abs-icon-ok"></i>
                  <?php else: ?>
                    <i class="bi bi-x-lg abs-icon-no"></i>
                  <?php endif ?>
                </td>
                <td>
                  <?php if (!empty($a['remplacement_prenom'])): ?>
                    <?= h($a['remplacement_prenom'] . ' ' . $a['remplacement_nom']) ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach; endif ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
