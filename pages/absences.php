<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['ss_user']['id'];
$absInitData = Db::fetchAll(
    "SELECT a.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
            ur.prenom AS remplacement_prenom, ur.nom AS remplacement_nom
     FROM absences a
     LEFT JOIN users u2 ON u2.id = a.valide_par
     LEFT JOIN users ur ON ur.id = a.remplacement_user_id
     WHERE a.user_id = ?
     ORDER BY a.date_debut DESC",
    [$uid]
);
?>


<!-- Lightbox -->
<div id="ztLightbox" class="ss-lightbox ss-lightbox-hidden">
  <div class="ss-lightbox-overlay"></div>
  <div class="ss-lightbox-content">
    <button class="ss-lightbox-close" type="button"><i class="bi bi-x-lg"></i></button>
    <div class="ss-lightbox-title" id="ztLbTitle"></div>
    <div class="ss-lightbox-stage" id="ztLbStage"></div>
    <div class="ss-lightbox-toolbar" id="ztLbToolbar" style="display:none;">
      <button type="button" class="ss-lb-btn" id="ztLbZoomOut"><i class="bi bi-zoom-out"></i></button>
      <span class="ss-lb-zoom" id="ztLbZoomLevel">100%</span>
      <button type="button" class="ss-lb-btn" id="ztLbZoomIn"><i class="bi bi-zoom-in"></i></button>
      <span style="width:1px;height:24px;background:rgba(255,255,255,.25);margin:0 4px;"></span>
      <button type="button" class="ss-lb-btn" id="ztLbReset"><i class="bi bi-arrows-angle-contract"></i></button>
    </div>
  </div>
</div>

<div class="page-header">
  <h1><i class="bi bi-calendar-x"></i> Mes Absences</h1>
  <p>Demandes de vacances, maladie, et autres absences</p>
</div>

<div class="d-flex gap-2 flex-wrap">
  <!-- Formulaire -->
  <div class="card" style="flex:0 0 360px;">
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
          <input type="file" id="absenceJustificatif" accept="image/*,.pdf" style="display:none;">
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
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header">
      <h3>Mes demandes</h3>
    </div>
    <div class="card-body" style="padding:0;">
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
            <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="__ss_ssr__"><?= json_encode(['absences' => $absInitData], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
