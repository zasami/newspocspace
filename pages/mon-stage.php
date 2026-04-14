<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; } ?>
<div class="mst-wrap">
    <div class="mst-header">
        <h2 class="mst-title"><i class="bi bi-journal-text"></i> Mon stage</h2>
    </div>
    <div id="mstContent"><div class="text-muted">Chargement…</div></div>
</div>

<!-- Modal report -->
<div class="modal fade" id="mstReportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mstReportTitle">Nouveau report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mstReportId">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-semibold">Type</label>
            <select id="mstRType" class="form-select form-select-sm">
              <option value="quotidien">Quotidien</option>
              <option value="hebdo">Hebdomadaire</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" id="mstRDate" class="form-control form-control-sm">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Titre (optionnel)</label>
          <input type="text" id="mstRTitre" class="form-control form-control-sm" placeholder="Ex. Accompagnement toilettes matinales">
        </div>
        <div class="mst-section-label">
          <i class="bi bi-check2-square"></i> Tâches réalisées aujourd'hui
          <span class="text-muted small">(coche tout ce que tu as fait)</span>
        </div>
        <div id="mstTachesList" class="mst-taches-list"><div class="text-muted small">Chargement du catalogue…</div></div>
        <label class="form-label small fw-semibold mt-3">Contenu du rapport *</label>
        <div id="mstREditor" class="mst-editor-wrap"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnSaveDraft">Brouillon</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnSubmitReport">Soumettre</button>
      </div>
    </div>
  </div>
</div>
