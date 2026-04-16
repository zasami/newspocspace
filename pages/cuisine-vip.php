<div class="page-header cv-page-header">
  <h1 class="cuis-h3-inline"><i class="bi bi-star"></i> Table VIP</h1>
  <div class="cv-hint-pill">
    <span>Tapez <kbd class="cv-kbd">@</kbd> + nom dans la recherche pour ajouter</span>
  </div>
</div>

<!-- Accompagnateurs -->
<div id="cvAccompRow" class="mb-3"></div>

<!-- Actions -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <button class="btn btn-sm btn-primary cv-new-session-btn" id="cvNewSession"><i class="bi bi-plus-lg"></i> Planifier un repas VIP</button>
  <div class="zs-select" id="cvHistorySelect" data-placeholder="Historique des repas"></div>
  <span class="badge cv-session-badge" id="cvSessionBadge"></span>
</div>

<!-- Modal planifier repas VIP -->
<div class="modal fade" id="cvNewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered cuis-modal-dialog-xs">
    <div class="modal-content cuis-modal-flex">
      <div class="modal-header cuis-modal-header-fix">
        <div>
          <h5 class="modal-title"><i class="bi bi-star"></i> Planifier un repas VIP</h5>
          <small class="text-muted">Choisissez la date du prochain repas VIP</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center cuis-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body cuis-modal-body-scroll">
        <div class="mb-2">
          <label class="form-label small fw-bold">Date du repas</label>
          <input type="date" class="form-control" id="cvNewDate">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Repas</label>
          <div class="zs-select" id="cvNewRepas" data-placeholder="Midi"></div>
        </div>
      </div>
      <div class="modal-footer d-flex cuis-modal-footer-fix">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="cvNewSaveBtn"><i class="bi bi-check-lg"></i> Planifier</button>
      </div>
    </div>
  </div>
</div>

<!-- Menu VIP -->
<div id="cvMenuCard" class="mb-3"></div>

<!-- Résidents invités -->
<div id="cvResidentsBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>
