<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['zt_user']['id'];
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
<style>
.abs-dropzone {
  border: 2px dashed var(--zt-border, #d1cfc9); border-radius: 10px; padding: 24px 16px;
  text-align: center; cursor: pointer; transition: all .25s ease;
  background: var(--zt-bg-secondary, #f9f7f4);
}
.abs-dropzone:hover { border-color: var(--zt-accent, #2d4a43); background: rgba(188,210,203,.15); }
.abs-dropzone.abs-dropzone-drag { border-color: var(--zt-accent, #2d4a43); background: rgba(188,210,203,.25); transform: scale(1.01); }
.abs-dropzone-content { display: flex; flex-direction: column; align-items: center; gap: 6px; color: var(--zt-text-secondary, #6B6B69); }
.abs-dropzone-content i { font-size: 2rem; color: var(--zt-accent, #2d4a43); opacity: .6; }
.abs-dropzone-content span { font-size: .88rem; font-weight: 500; }
.abs-dropzone-content small { font-size: .75rem; opacity: .7; }
.abs-dropzone-file { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
.abs-dropzone-file i { font-size: 1.4rem; color: var(--zt-accent, #2d4a43); }
.abs-dropzone-file .abs-file-info { flex: 1; text-align: left; }
.abs-dropzone-file .abs-file-info .abs-file-name { font-size: .85rem; font-weight: 600; color: var(--zt-text, #1A1A18); }
.abs-dropzone-file .abs-file-info .abs-file-size { font-size: .75rem; color: var(--zt-text-secondary, #6B6B69); }
.abs-dropzone-file .abs-file-remove { background: none; border: none; color: #7B3B2C; cursor: pointer; font-size: 1.1rem; padding: 4px; border-radius: 50%; transition: background .2s; }
.abs-dropzone-file .abs-file-remove:hover { background: #E2B8AE; }

.zt-lightbox { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; display: flex; align-items: center; justify-content: center; animation: ztLbFadeIn .3s ease; }
.zt-lightbox-hidden { display: none !important; }
.zt-lightbox-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.8); backdrop-filter: blur(10px); }
.zt-lightbox-content { position: relative; width: 100%; height: 100%; overflow: hidden; }
.zt-lightbox-stage { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; cursor: default; user-select: none; }
.zt-lightbox-stage img { max-width: 90vw; max-height: calc(100vh - 120px); width: auto; height: auto; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); will-change: transform; }
.zt-lightbox-stage iframe { width: 85vw; height: calc(100vh - 120px); border: none; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); background: #fff; }
.zt-lightbox-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,.1); border: none; color: #fff; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .3s; backdrop-filter: blur(10px); z-index: 10; font-size: 24px; }
.zt-lightbox-close:hover { background: rgba(255,255,255,.2); transform: scale(1.1); }
.zt-lightbox-title { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.15); color: #fff; padding: 10px 24px; border-radius: 24px; font-size: 15px; font-weight: 600; backdrop-filter: blur(10px); z-index: 11; }
.zt-lightbox-toolbar { position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 4px; background: rgba(30,30,30,.85); backdrop-filter: blur(12px); border-radius: 999px; padding: 6px 16px; z-index: 12; }
.zt-lb-btn { width: 40px; height: 40px; border: none; background: transparent; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; transition: background .2s; }
.zt-lb-btn:hover { background: rgba(255,255,255,.15); }
.zt-lb-zoom { color: #fff; font-size: 14px; font-weight: 600; min-width: 48px; text-align: center; user-select: none; }
.zt-lightbox-stage.zt-zoomed { cursor: grab; }
.zt-lightbox-stage.zt-dragging { cursor: grabbing !important; }
@keyframes ztLbFadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<!-- Lightbox -->
<div id="ztLightbox" class="zt-lightbox zt-lightbox-hidden">
  <div class="zt-lightbox-overlay"></div>
  <div class="zt-lightbox-content">
    <button class="zt-lightbox-close" type="button"><i class="bi bi-x-lg"></i></button>
    <div class="zt-lightbox-title" id="ztLbTitle"></div>
    <div class="zt-lightbox-stage" id="ztLbStage"></div>
    <div class="zt-lightbox-toolbar" id="ztLbToolbar" style="display:none;">
      <button type="button" class="zt-lb-btn" id="ztLbZoomOut"><i class="bi bi-zoom-out"></i></button>
      <span class="zt-lb-zoom" id="ztLbZoomLevel">100%</span>
      <button type="button" class="zt-lb-btn" id="ztLbZoomIn"><i class="bi bi-zoom-in"></i></button>
      <span style="width:1px;height:24px;background:rgba(255,255,255,.25);margin:0 4px;"></span>
      <button type="button" class="zt-lb-btn" id="ztLbReset"><i class="bi bi-arrows-angle-contract"></i></button>
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
<script type="application/json" id="__zt_ssr__"><?= json_encode(['absences' => $absInitData], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
