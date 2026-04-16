<div class="page-header">
  <h1><i class="bi bi-receipt"></i> Commandes collaborateurs</h1>
</div>

<!-- Stats + Ajouter sur la même ligne -->
<div class="cr-stats-row">
  <div class="cr-stat-card" id="crStatCouverts">
    <div class="cr-stat-icon cr-stat-icon-couverts"><i class="bi bi-people-fill"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Couverts</div></div>
  </div>
  <div class="cr-stat-card" id="crStatMenu">
    <div class="cr-stat-icon cr-stat-icon-menu"><i class="bi bi-egg-fried"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Menu</div></div>
  </div>
  <div class="cr-stat-card" id="crStatSalade">
    <div class="cr-stat-icon cr-stat-icon-salade"><i class="bi bi-flower1"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Salade</div></div>
  </div>
  <div class="cr-stat-add" id="crAddBtn">
    <i class="bi bi-plus-lg"></i>
    <span>Ajouter commande</span>
  </div>
</div>

<!-- Filtres -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <input type="date" class="form-control cr-date-input" id="crDate">
  <div class="zs-select" id="crRepasSelect" data-placeholder="Repas"></div>
  <button class="btn btn-sm btn-outline-secondary ms-auto cr-print-btn" id="crPrintBtn"><i class="bi bi-printer"></i> Imprimer</button>
</div>

<!-- Liste commandes -->
<div id="crBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>

<!-- Modal ajouter commande -->
<div class="modal fade" id="crModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered cuis-modal-dialog-md">
    <div class="modal-content cuis-modal-flex">
      <div class="modal-header cuis-modal-header-fix">
        <div>
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter une commande</h5>
          <small class="text-muted">Saisir une commande pour un collaborateur</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center cuis-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body cuis-modal-body-scroll">
        <!-- Recherche collaborateur -->
        <div class="cuis-field-group-rel">
          <label class="form-label cuis-label-bold">Collaborateur</label>
          <input type="text" class="form-control" id="crUserSearch" placeholder="Chercher par nom ou prénom..." autocomplete="off">
          <input type="hidden" id="crUserId">
          <div class="cuis-autocomplete-list cuis-autocomplete-scroll" id="crUserResults"></div>
        </div>
        <!-- Choix menu/salade -->
        <div class="cuis-field-group">
          <label class="form-label cuis-label-bold">Choix du repas</label>
          <div class="cuis-choix-row">
            <label class="menu-choix-option active">
              <input type="radio" name="crChoix" value="menu" checked>
              <i class="bi bi-egg-fried cuis-choix-icon cuis-choix-icon-menu"></i>
              <div><div class="cuis-choix-label">Menu du jour</div></div>
            </label>
            <label class="menu-choix-option">
              <input type="radio" name="crChoix" value="salade">
              <i class="bi bi-flower1 cuis-choix-icon cuis-choix-icon-salade"></i>
              <div><div class="cuis-choix-label">Salade</div></div>
            </label>
          </div>
        </div>
        <!-- Nb personnes -->
        <div class="cuis-field-group">
          <label class="form-label cuis-label-bold">Nombre de personnes</label>
          <select class="form-select" id="crNb">
            <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
          </select>
        </div>
        <!-- Paiement -->
        <div class="cuis-field-group">
          <label class="form-label cuis-label-bold">Mode de paiement</label>
          <div class="cuis-pay-row">
            <label class="menu-pay-option active">
              <input type="radio" name="crPaiement" value="salaire" checked> <i class="bi bi-wallet2"></i> Retenue salaire
            </label>
            <label class="menu-pay-option">
              <input type="radio" name="crPaiement" value="caisse"> <i class="bi bi-cash-coin"></i> Cash caisse
            </label>
            <label class="menu-pay-option">
              <input type="radio" name="crPaiement" value="carte"> <i class="bi bi-credit-card"></i> Carte
            </label>
          </div>
        </div>
        <!-- Remarques (tag input) -->
        <div class="cuis-field-group-last">
          <label class="form-label cuis-label-bold">Demande spéciale <small class="text-muted">(optionnel)</small></label>
          <input type="hidden" id="crRemarques">
          <div class="cr-tag-input" id="crTagWrap">
            <div class="cr-tag-list" id="crTagList"></div>
            <input type="text" class="cr-tag-text" id="crTagText" placeholder="Tapez ou cliquez ci-dessous..." maxlength="100">
          </div>
          <div class="cuis-quick-tags">
            <span class="cr-quick-tag" data-tag="Sans viande">Sans viande</span>
            <span class="cr-quick-tag" data-tag="Sans porc">Sans porc</span>
            <span class="cr-quick-tag" data-tag="Sans gluten">Sans gluten</span>
            <span class="cr-quick-tag" data-tag="Sans lactose">Sans lactose</span>
            <span class="cr-quick-tag" data-tag="Végétarien">Végétarien</span>
            <span class="cr-quick-tag" data-tag="Allergie noix">Allergie noix</span>
            <span class="cr-quick-tag" data-tag="Halal">Halal</span>
            <span class="cr-quick-tag" data-tag="Mixé">Mixé</span>
          </div>
        </div>

      </div>
      <div class="modal-footer d-flex cuis-modal-footer-fix">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark ms-auto" id="crSaveBtn"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
      </div>
    </div>
  </div>
</div>

