<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1>Bonjour <span id="chUserName"></span></h1>
  <p>Tableau de bord cuisine</p>
</div>

<!-- Stats cards -->
<div class="stats-grid" id="chStats">
  <div class="stat-card">
    <div class="stat-icon teal"><i class="bi bi-house-heart"></i></div>
    <div>
      <div class="stat-value" id="chStatFamille">—</div>
      <div class="stat-label">Réservations famille</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-egg-fried"></i></div>
    <div>
      <div class="stat-value" id="chStatMenu">—</div>
      <div class="stat-label">Commandes menu</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="bi bi-flower1"></i></div>
    <div>
      <div class="stat-value" id="chStatSalade">—</div>
      <div class="stat-label">Commandes salade</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="bi bi-journal-check"></i></div>
    <div>
      <div class="stat-value" id="chStatMenusSaisis">—</div>
      <div class="stat-label">Menus saisis / semaine</div>
    </div>
  </div>
</div>

<!-- Menu semaine cards -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 style="margin:0"><i class="bi bi-journal-text"></i> Menus de la semaine</h3>
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-sm btn-outline-secondary" id="chMenuPrev"><i class="bi bi-chevron-left"></i></button>
    <span id="chMenuWeekLabel" style="font-weight:600;min-width:180px;text-align:center"></span>
    <button class="btn btn-sm btn-outline-secondary" id="chMenuNext"><i class="bi bi-chevron-right"></i></button>
    <div class="dropdown ms-2">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer"></i> Imprimer</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#" id="chPrintDay"><i class="bi bi-calendar-day"></i> Menu du jour</a></li>
        <li><a class="dropdown-item" href="#" id="chPrintWeek"><i class="bi bi-calendar-week"></i> Menu de la semaine</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="ch-week-grid" id="chMenuCards"></div>

<!-- Commandes du jour -->
<div class="card mt-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 style="margin:0"><i class="bi bi-receipt"></i> Commandes du jour</h3>
    <div class="d-flex align-items-center gap-2">
      <select class="form-select form-select-sm" id="chRepas" style="width:auto">
        <option value="midi">Midi</option>
        <option value="soir">Soir</option>
      </select>
      <button class="btn btn-sm btn-primary" id="chAddCmdBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>
      <button class="btn btn-sm btn-outline-secondary" id="chPrintCommandes" title="Imprimer commandes"><i class="bi bi-printer"></i></button>
    </div>
  </div>
  <div class="card-body" id="chCommandesBody" style="max-height:400px;overflow-y:auto">
    <div class="empty-state"><i class="bi bi-receipt"></i><p>Chargement...</p></div>
  </div>
</div>

<!-- Modal saisie/édition menu -->
<div class="modal fade" id="chMenuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title" id="chModalTitle">Menu</h5>
          <small class="text-muted" id="chModalSubtitle"></small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <input type="hidden" id="chEditDate">
        <input type="hidden" id="chEditRepas">
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-cup-hot"></i> Entrée</label>
          <input type="text" class="form-control" id="chEditEntree" placeholder="Ex: Soupe de légumes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-egg-fried"></i> Plat principal <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="chEditPlat" placeholder="Ex: Poulet rôti aux herbes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-flower1"></i> Salade</label>
          <input type="text" class="form-control" id="chEditSalade" placeholder="Ex: Salade verte vinaigrette">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-grid-3x3"></i> Accompagnement</label>
            <input type="text" class="form-control" id="chEditAccomp" placeholder="Ex: Riz basmati">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-cake2"></i> Dessert</label>
            <input type="text" class="form-control" id="chEditDessert" placeholder="Ex: Tarte aux pommes">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold"><i class="bi bi-info-circle"></i> Remarques</label>
          <textarea class="form-control" id="chEditRemarques" rows="2" placeholder="Allergènes, options végé..."></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="chEditSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal "Réutiliser ce menu" — choix du jour cible -->
<div class="modal fade" id="chReuseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Réutiliser ce menu</h5>
          <small class="text-muted">Copier le menu vers un autre jour</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div class="mb-2">
          <label class="form-label small fw-bold">Date cible</label>
          <input type="date" class="form-control" id="chReuseDate">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Repas</label>
          <select class="form-select" id="chReuseRepas">
            <option value="midi">Midi</option>
            <option value="soir">Soir</option>
          </select>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="chReuseSaveBtn"><i class="bi bi-check-lg"></i> Copier</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal ajouter commande -->
<div class="modal fade" id="chCmdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter une commande</h5>
          <small class="text-muted" id="chCmdSubtitle">Saisir une commande pour un collaborateur</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <!-- Recherche collaborateur -->
        <div style="margin-bottom:1rem;position:relative">
          <label class="form-label" style="font-weight:600">Collaborateur</label>
          <input type="text" class="form-control" id="chCmdUserSearch" placeholder="Chercher par nom ou prénom..." autocomplete="off">
          <input type="hidden" id="chCmdUserId">
          <div class="cuis-autocomplete-list" id="chCmdUserResults" style="max-height:200px;overflow-y:auto"></div>
        </div>
        <!-- Choix menu/salade -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Choix du repas</label>
          <div style="display:flex;gap:0.5rem">
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--zt-teal);border-radius:10px;cursor:pointer;transition:all 0.15s;background:var(--zt-accent-bg)">
              <input type="radio" name="chCmdChoix" value="menu" checked style="display:none">
              <i class="bi bi-egg-fried" style="font-size:1.2rem;color:var(--zt-orange)"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Menu du jour</div></div>
            </label>
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--zt-border);border-radius:10px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdChoix" value="salade" style="display:none">
              <i class="bi bi-flower1" style="font-size:1.2rem;color:#16A34A"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Salade</div></div>
            </label>
          </div>
        </div>
        <!-- Nb personnes -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Nombre de personnes</label>
          <select class="form-select" id="chCmdNb">
            <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
          </select>
        </div>
        <!-- Paiement -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Mode de paiement</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--zt-teal);border-radius:8px;cursor:pointer;transition:all 0.15s;background:var(--zt-accent-bg)">
              <input type="radio" name="chCmdPaiement" value="salaire" checked style="accent-color:var(--zt-teal)"> <i class="bi bi-wallet2"></i> Retenue salaire
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--zt-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdPaiement" value="caisse" style="accent-color:var(--zt-teal)"> <i class="bi bi-cash-coin"></i> Cash caisse
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--zt-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdPaiement" value="carte" style="accent-color:var(--zt-teal)"> <i class="bi bi-credit-card"></i> Carte
            </label>
          </div>
        </div>
        <!-- Remarques -->
        <div style="margin-bottom:0">
          <label class="form-label" style="font-weight:600">Demande spéciale <small class="text-muted">(optionnel)</small></label>
          <input type="text" class="form-control" id="chCmdRemarques" placeholder="Ex: sans viande, allergie noix..." maxlength="500">
          <div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-top:0.5rem">
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans viande">Sans viande</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans porc">Sans porc</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans gluten">Sans gluten</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans lactose">Sans lactose</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Végétarien">Végétarien</button>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark ms-auto" id="chCmdSaveBtn"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
      </div>
    </div>
  </div>
</div>

<style>
.ch-week-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}
.ch-day-card {
  background: var(--zt-bg-card);
  border: 1px solid var(--zt-border-light);
  border-radius: var(--zt-radius-md);
  padding: 1rem;
  box-shadow: var(--zt-shadow-sm);
  transition: box-shadow 0.15s, transform 0.15s;
  position: relative;
}
.ch-day-card:hover { box-shadow: var(--zt-shadow-md); transform: translateY(-1px); }
.ch-day-card.is-today { border-left: 3px solid var(--zt-teal); }
.ch-day-card--empty {
  background: transparent;
  border: 2px dashed var(--zt-border);
  box-shadow: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 140px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.ch-day-card--empty:hover {
  border-color: var(--zt-teal);
  background: var(--zt-accent-bg);
}
.ch-day-card--empty:hover .ch-add-icon { color: var(--zt-teal); }
.ch-add-icon {
  font-size: 2rem;
  color: var(--zt-text-muted);
  transition: color 0.15s;
}
.ch-day-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}
.ch-day-name {
  font-weight: 700;
  font-size: 0.92rem;
}
.ch-day-date {
  font-size: 0.75rem;
  color: var(--zt-text-muted);
}
.ch-repas-block {
  padding: 0.4rem 0;
  border-top: 1px solid var(--zt-border-light);
}
.ch-repas-block:first-of-type { border-top: none; }
.ch-repas-tag {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  margin-bottom: 0.25rem;
}
.ch-repas-tag.midi { background: #fef3c7; color: #92400e; }
.ch-repas-tag.soir { background: #1e293b; color: #e2e8f0; }
.ch-menu-plat { font-weight: 600; font-size: 0.88rem; margin-bottom: 0.15rem; }
.ch-menu-detail { font-size: 0.78rem; color: var(--zt-text-secondary); }
.ch-menu-actions {
  display: flex;
  gap: 0.25rem;
  margin-top: 0.4rem;
}
.ch-menu-actions .btn { font-size: 0.7rem; padding: 0.15rem 0.4rem; }
.ch-couv-badge {
  font-size: 0.68rem;
  padding: 0.1rem 0.45rem;
  border-radius: 10px;
  background: var(--zt-accent-bg);
  color: var(--zt-text-secondary);
  border: 1px solid var(--zt-border-light);
}
</style>
