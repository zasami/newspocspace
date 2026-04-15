<div class="page-header">
  <h1><i class="bi bi-receipt"></i> Commandes collaborateurs</h1>
</div>

<!-- Stats + Ajouter sur la même ligne -->
<div class="cr-stats-row">
  <div class="cr-stat-card" id="crStatCouverts">
    <div class="cr-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-people-fill"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Couverts</div></div>
  </div>
  <div class="cr-stat-card" id="crStatMenu">
    <div class="cr-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-egg-fried"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Menu</div></div>
  </div>
  <div class="cr-stat-card" id="crStatSalade">
    <div class="cr-stat-icon" style="background:#D0C4D8;color:#5B4B6B"><i class="bi bi-flower1"></i></div>
    <div><div class="cr-stat-value">—</div><div class="cr-stat-label">Salade</div></div>
  </div>
  <div class="cr-stat-add" id="crAddBtn">
    <i class="bi bi-plus-lg"></i>
    <span>Ajouter commande</span>
  </div>
</div>

<!-- Filtres -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <input type="date" class="form-control" id="crDate" style="max-width:180px;border-radius:8px;border:1.5px solid #E0DCD4;height:38px;font-size:.88rem">
  <div class="zs-select" id="crRepasSelect" data-placeholder="Repas" style="width:130px"></div>
  <button class="btn btn-sm btn-outline-secondary ms-auto" id="crPrintBtn" style="border-radius:8px;height:38px;padding:0 16px"><i class="bi bi-printer"></i> Imprimer</button>
</div>

<!-- Liste commandes -->
<div id="crBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>

<!-- Modal ajouter commande -->
<div class="modal fade" id="crModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter une commande</h5>
          <small class="text-muted">Saisir une commande pour un collaborateur</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <!-- Recherche collaborateur -->
        <div style="margin-bottom:1rem;position:relative">
          <label class="form-label" style="font-weight:600">Collaborateur</label>
          <input type="text" class="form-control" id="crUserSearch" placeholder="Chercher par nom ou prénom..." autocomplete="off">
          <input type="hidden" id="crUserId">
          <div class="cuis-autocomplete-list" id="crUserResults" style="max-height:200px;overflow-y:auto"></div>
        </div>
        <!-- Choix menu/salade -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Choix du repas</label>
          <div style="display:flex;gap:0.5rem">
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-teal);border-radius:10px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
              <input type="radio" name="crChoix" value="menu" checked style="display:none">
              <i class="bi bi-egg-fried" style="font-size:1.2rem;color:var(--ss-orange)"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Menu du jour</div></div>
            </label>
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-border);border-radius:10px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="crChoix" value="salade" style="display:none">
              <i class="bi bi-flower1" style="font-size:1.2rem;color:#16A34A"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Salade</div></div>
            </label>
          </div>
        </div>
        <!-- Nb personnes -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Nombre de personnes</label>
          <select class="form-select" id="crNb">
            <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
          </select>
        </div>
        <!-- Paiement -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Mode de paiement</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-teal);border-radius:8px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
              <input type="radio" name="crPaiement" value="salaire" checked style="accent-color:var(--ss-teal)"> <i class="bi bi-wallet2"></i> Retenue salaire
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="crPaiement" value="caisse" style="accent-color:var(--ss-teal)"> <i class="bi bi-cash-coin"></i> Cash caisse
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="crPaiement" value="carte" style="accent-color:var(--ss-teal)"> <i class="bi bi-credit-card"></i> Carte
            </label>
          </div>
        </div>
        <!-- Remarques (tag input) -->
        <div style="margin-bottom:0">
          <label class="form-label" style="font-weight:600">Demande spéciale <small class="text-muted">(optionnel)</small></label>
          <input type="hidden" id="crRemarques">
          <div class="cr-tag-input" id="crTagWrap">
            <div class="cr-tag-list" id="crTagList"></div>
            <input type="text" class="cr-tag-text" id="crTagText" placeholder="Tapez ou cliquez ci-dessous..." maxlength="100">
          </div>
          <div style="display:flex;gap:0.35rem;flex-wrap:wrap;margin-top:0.5rem">
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
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark ms-auto" id="crSaveBtn"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
      </div>
    </div>
  </div>
</div>


