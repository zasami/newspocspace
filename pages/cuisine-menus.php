<div class="page-header">
  <h1><i class="bi bi-journal-text"></i> Menus de la semaine</h1>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-outline-secondary" id="cmPrev"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold" id="cmWeekLabel"></span>
  <button class="btn btn-sm btn-outline-secondary" id="cmNext"><i class="bi bi-chevron-right"></i></button>
  <div class="dropdown ms-auto">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer"></i> Imprimer</button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item" href="#" id="cmPrintDay"><i class="bi bi-calendar-day"></i> Menu du jour</a></li>
      <li><a class="dropdown-item" href="#" id="cmPrintWeek"><i class="bi bi-calendar-week"></i> Toute la semaine</a></li>
    </ul>
  </div>
</div>

<div class="cm-week-grid" id="cmBody">
  <div class="text-center py-4" style="grid-column:1/-1"><span class="spinner"></span></div>
</div>

<!-- Modal saisie menu -->
<div class="modal fade" id="cmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title" id="cmModalTitle">Menu</h5>
          <small class="text-muted" id="cmModalSub"></small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <input type="hidden" id="cmDate">
        <input type="hidden" id="cmRepas">
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-cup-hot"></i> Entrée</label>
          <input type="text" class="form-control" id="cmEntree" placeholder="Ex: Soupe de légumes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-egg-fried"></i> Plat principal <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="cmPlat" placeholder="Ex: Poulet rôti aux herbes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-flower1"></i> Salade</label>
          <input type="text" class="form-control" id="cmSalade" placeholder="Ex: Salade verte vinaigrette">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-grid-3x3"></i> Accompagnement</label>
            <input type="text" class="form-control" id="cmAccomp" placeholder="Ex: Riz basmati">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-cake2"></i> Dessert</label>
            <input type="text" class="form-control" id="cmDessert" placeholder="Ex: Tarte aux pommes">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold"><i class="bi bi-info-circle"></i> Remarques</label>
          <textarea class="form-control" id="cmRemarques" rows="2" placeholder="Allergènes, options végé..."></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="cmSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>


