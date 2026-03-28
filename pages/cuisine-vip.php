<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
  <h1 style="margin:0"><i class="bi bi-star"></i> Table VIP</h1>
  <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1.2rem;background:#F0EDE8;border-radius:50px;font-size:.82rem;color:#4A4840">
    <span>Tapez <kbd style="background:#E8E5E0;padding:1px 6px;border-radius:4px;font-size:.78rem;font-weight:600">@</kbd> + nom dans la recherche pour ajouter</span>
  </div>
</div>

<!-- Accompagnateurs -->
<div id="cvAccompRow" class="mb-3"></div>

<!-- Actions -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <button class="btn btn-sm btn-primary" id="cvNewSession" style="border-radius:8px"><i class="bi bi-plus-lg"></i> Planifier un repas VIP</button>
  <div class="zs-select" id="cvHistorySelect" data-placeholder="Historique des repas" style="width:260px"></div>
  <span class="badge" id="cvSessionBadge" style="background:#bcd2cb;color:#2d4a43;font-size:.8rem;padding:6px 14px;border-radius:8px"></span>
</div>

<!-- Modal planifier repas VIP -->
<div class="modal fade" id="cvNewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-star"></i> Planifier un repas VIP</h5>
          <small class="text-muted">Choisissez la date du prochain repas VIP</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div class="mb-2">
          <label class="form-label small fw-bold">Date du repas</label>
          <input type="date" class="form-control" id="cvNewDate">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Repas</label>
          <div class="zs-select" id="cvNewRepas" data-placeholder="Midi"></div>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
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
