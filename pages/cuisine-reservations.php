<div class="page-header">
  <h1><i class="bi bi-receipt"></i> Commandes collaborateurs</h1>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <input type="date" class="form-control form-control-sm" id="crDate" style="max-width:180px">
  <select class="form-select form-select-sm" id="crRepas" style="max-width:120px">
    <option value="midi">Midi</option>
    <option value="soir">Soir</option>
  </select>
  <button class="btn btn-sm btn-primary" id="crAddBtn"><i class="bi bi-plus-lg"></i> Ajouter commande</button>
  <button class="btn btn-sm btn-outline-secondary ms-auto" id="crPrintBtn"><i class="bi bi-printer"></i> Imprimer</button>
</div>

<!-- Stats badges -->
<div class="d-flex gap-2 mb-3 flex-wrap" id="crStats"></div>

<!-- Liste commandes -->
<div id="crBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>

<!-- Modal ajouter commande -->
<div class="modal fade" id="crModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter une commande</h5>
          <small class="text-muted">Saisir une commande pour un collaborateur</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div class="mb-2 position-relative">
          <label class="form-label small fw-bold">Collaborateur</label>
          <input type="text" class="form-control" id="crUserSearch" placeholder="Chercher un collaborateur..." autocomplete="off">
          <input type="hidden" id="crUserId">
          <div class="cuis-autocomplete-list" id="crUserResults"></div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Choix</label>
          <div class="d-flex gap-2">
            <label class="btn btn-sm btn-outline-primary flex-fill text-center" style="cursor:pointer">
              <input type="radio" name="crChoix" value="menu" checked class="d-none"> <i class="bi bi-egg-fried"></i> Menu
            </label>
            <label class="btn btn-sm btn-outline-success flex-fill text-center" style="cursor:pointer">
              <input type="radio" name="crChoix" value="salade" class="d-none"> <i class="bi bi-flower1"></i> Salade
            </label>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-bold">Nb personnes</label>
            <input type="number" class="form-control" id="crNb" min="1" max="10" value="1">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold">Paiement</label>
            <select class="form-select" id="crPaiement">
              <option value="salaire">Salaire</option>
              <option value="caisse">Cash</option>
              <option value="carte">Carte</option>
            </select>
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Remarques</label>
          <input type="text" class="form-control" id="crRemarques" placeholder="Allergies, sans porc...">
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="crSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>
