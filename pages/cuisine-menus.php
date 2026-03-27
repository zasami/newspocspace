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
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
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

<style>
.cm-week-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 0.75rem;
}
.cm-card {
  background: var(--zt-bg-card);
  border: 1px solid var(--zt-border-light);
  border-radius: var(--zt-radius-md);
  overflow: hidden;
  transition: box-shadow 0.15s;
}
.cm-card:hover { box-shadow: var(--zt-shadow-md); }
.cm-card.is-today { border-left: 3px solid var(--zt-teal); }
.cm-card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.6rem 1rem; background: var(--zt-bg-subtle, #f8f9fa);
  border-bottom: 1px solid var(--zt-border-light);
}
.cm-card-day { font-weight: 700; font-size: 0.9rem; }
.cm-card-body { padding: 0.75rem 1rem; }
.cm-meal { margin-bottom: 0.75rem; }
.cm-meal:last-child { margin-bottom: 0; }
.cm-meal-label {
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 4px;
}
.cm-meal-label.midi { color: var(--zt-teal); }
.cm-meal-label.soir { color: #7c5bbf; }
.cm-meal-items { font-size: 0.84rem; color: var(--zt-text-secondary); line-height: 1.5; }
.cm-meal-items strong { color: var(--zt-text); }
.cm-meal-empty { font-size: 0.82rem; color: var(--zt-text-muted); font-style: italic; }
.cm-meal-remark { font-size: 0.75rem; color: var(--zt-text-muted); font-style: italic; margin-top: 2px; }
.cm-meal-stats {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 0.65rem; background: var(--zt-accent-bg); color: var(--zt-text-secondary);
  padding: 1px 8px; border-radius: 10px; border: 1px solid var(--zt-border-light);
}
.cm-card--empty {
  border: 2px dashed var(--zt-border);
  background: transparent;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 160px; cursor: pointer; transition: border-color 0.15s, background 0.15s;
}
.cm-card--empty:hover { border-color: var(--zt-teal); background: var(--zt-accent-bg); }
.cm-card--empty .cm-add-icon { font-size: 2rem; color: var(--zt-text-muted); margin-bottom: 0.3rem; }
.cm-card--empty:hover .cm-add-icon { color: var(--zt-teal); }
.cm-card-actions { display: flex; gap: 0.25rem; }
.cm-card-actions .btn { font-size: 0.72rem; padding: 0.2rem 0.5rem; }
.cm-divider { border: none; border-top: 1px dashed var(--zt-border-light); margin: 0.5rem 0; }
</style>
