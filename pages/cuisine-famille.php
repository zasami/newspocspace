<div class="page-header">
  <h1><i class="bi bi-house-heart"></i> Réservations famille</h1>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <input type="date" class="form-control form-control-sm cf-date-input" id="cfDate">
  <select class="form-select form-select-sm cf-repas-select" id="cfRepas">
    <option value="midi">Midi</option>
    <option value="soir">Soir</option>
  </select>
  <button class="btn btn-sm btn-primary" id="cfAddBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>
</div>

<div id="cfBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>

<!-- Modal ajout réservation famille -->
<div class="modal fade" id="cfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered cuis-modal-dialog-cf">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nouvelle réservation famille</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cfEditId">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" id="cfFormDate">
          </div>
          <div class="col-6">
            <label class="form-label">Repas</label>
            <select class="form-select" id="cfFormRepas">
              <option value="midi">Midi</option>
              <option value="soir">Soir</option>
            </select>
          </div>
        </div>
        <div class="mb-2 position-relative">
          <label class="form-label">Résident</label>
          <input type="text" class="form-control" id="cfResidentSearch" placeholder="Chercher un résident..." autocomplete="off">
          <input type="hidden" id="cfResidentId">
          <div class="cuis-autocomplete-list" id="cfResidentResults"></div>
        </div>
        <div class="mb-2 position-relative">
          <label class="form-label">Visiteur</label>
          <input type="text" class="form-control" id="cfVisiteurSearch" placeholder="Nom du visiteur..." autocomplete="off">
          <input type="hidden" id="cfVisiteurId">
          <div class="cuis-autocomplete-list" id="cfVisiteurResults"></div>
          <div class="form-check mt-1 d-none" id="cfSaveVisiteurWrap">
            <input type="checkbox" class="form-check-input" id="cfSaveVisiteur">
            <label class="form-check-label small" for="cfSaveVisiteur">Enregistrer ce visiteur</label>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4">
            <label class="form-label">Nb personnes</label>
            <input type="number" class="form-control" id="cfNb" min="1" max="20" value="1">
          </div>
          <div class="col-8">
            <label class="form-label">Remarques</label>
            <input type="text" class="form-control" id="cfRemarques" placeholder="Allergies, régime...">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="cfSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>
