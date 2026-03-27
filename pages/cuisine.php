<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1><i class="bi bi-egg-fried"></i> Cuisine</h1>
  <p>Gestion des menus, réservations et table VIP</p>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs cuis-tabs" id="cuisineTabs" role="tablist">
  <li class="nav-item" role="presentation" data-cuis-perm="cuisine_saisie_menu">
    <button class="nav-link" id="tab-saisie" data-bs-toggle="tab" data-bs-target="#pane-saisie" type="button" role="tab">
      <i class="bi bi-pencil-square"></i> Saisie de menu
    </button>
  </li>
  <li class="nav-item" role="presentation" data-cuis-perm="cuisine_reservations_collab">
    <button class="nav-link" id="tab-collab" data-bs-toggle="tab" data-bs-target="#pane-collab" type="button" role="tab">
      <i class="bi bi-people"></i> Réservations collaborateurs
    </button>
  </li>
  <li class="nav-item" role="presentation" data-cuis-perm="cuisine_reservations_famille">
    <button class="nav-link" id="tab-famille" data-bs-toggle="tab" data-bs-target="#pane-famille" type="button" role="tab">
      <i class="bi bi-house-heart"></i> Réservations famille
    </button>
  </li>
  <li class="nav-item" role="presentation" data-cuis-perm="cuisine_table_vip">
    <button class="nav-link" id="tab-vip" data-bs-toggle="tab" data-bs-target="#pane-vip" type="button" role="tab">
      <i class="bi bi-star"></i> Table VIP
    </button>
  </li>
</ul>

<div class="tab-content" id="cuisineTabContent">
  <!-- Tab 1: Saisie de menu -->
  <div class="tab-pane fade" id="pane-saisie" role="tabpanel">
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 style="margin:0"><i class="bi bi-calendar-week"></i> Menus de la semaine</h3>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="cuisMenuPrev"><i class="bi bi-chevron-left"></i></button>
          <span id="cuisMenuWeekLabel" style="font-size:0.85rem;font-weight:600;min-width:160px;text-align:center"></span>
          <button class="btn btn-sm btn-outline-secondary" id="cuisMenuNext"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
      <div class="card-body" id="cuisMenuBody">
        <div class="empty-state"><i class="bi bi-egg-fried"></i><p>Chargement...</p></div>
      </div>
    </div>
  </div>

  <!-- Tab 2: Réservations collaborateurs -->
  <div class="tab-pane fade" id="pane-collab" role="tabpanel">
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 style="margin:0"><i class="bi bi-people"></i> Réservations collaborateurs</h3>
        <div class="d-flex align-items-center gap-2">
          <input type="date" class="form-control form-control-sm" id="cuisCollabDate" style="width:auto">
          <select class="form-select form-select-sm" id="cuisCollabRepas" style="width:auto">
            <option value="midi">Midi</option>
            <option value="soir">Soir</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="cuisCollabPrint" title="Imprimer"><i class="bi bi-printer"></i></button>
        </div>
      </div>
      <div class="card-body" id="cuisCollabBody">
        <div class="empty-state"><i class="bi bi-people"></i><p>Sélectionnez une date</p></div>
      </div>
    </div>
  </div>

  <!-- Tab 3: Réservations famille -->
  <div class="tab-pane fade" id="pane-famille" role="tabpanel">
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 style="margin:0"><i class="bi bi-house-heart"></i> Réservations famille</h3>
        <div class="d-flex align-items-center gap-2">
          <input type="date" class="form-control form-control-sm" id="cuisFamilleDate" style="width:auto">
          <select class="form-select form-select-sm" id="cuisFamilleRepas" style="width:auto">
            <option value="midi">Midi</option>
            <option value="soir">Soir</option>
          </select>
          <button class="btn btn-sm btn-primary" id="cuisFamilleAddBtn"><i class="bi bi-plus-lg"></i> Nouvelle réservation</button>
        </div>
      </div>
      <div class="card-body" id="cuisFamilleBody">
        <div class="empty-state"><i class="bi bi-house-heart"></i><p>Sélectionnez une date</p></div>
      </div>
    </div>
  </div>

  <!-- Tab 4: Table VIP -->
  <div class="tab-pane fade" id="pane-vip" role="tabpanel">
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 style="margin:0"><i class="bi bi-star"></i> Table VIP — Résidents</h3>
        <button class="btn btn-sm btn-outline-primary" id="cuisVipAddBtn"><i class="bi bi-plus-lg"></i> Ajouter un résident VIP</button>
      </div>
      <div class="card-body" id="cuisVipBody">
        <div class="empty-state"><i class="bi bi-star"></i><p>Chargement...</p></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal réservation famille -->
<div class="modal fade" id="cuisFamilleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cuisFamilleModalTitle">Nouvelle réservation famille</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body">
        <form id="cuisFamilleForm">
          <input type="hidden" id="cuisFamilleId">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" id="cuisFamilleFormDate" required>
            </div>
            <div class="col-6">
              <label class="form-label">Repas</label>
              <select class="form-select" id="cuisFamilleFormRepas">
                <option value="midi">Midi</option>
                <option value="soir">Soir</option>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Résident</label>
            <input type="text" class="form-control" id="cuisFamilleResidentSearch" placeholder="Rechercher un résident..." autocomplete="off">
            <input type="hidden" id="cuisFamilleResidentId">
            <div class="cuis-autocomplete-list" id="cuisResidentResults"></div>
          </div>
          <div class="mb-2">
            <label class="form-label">Visiteur</label>
            <input type="text" class="form-control" id="cuisFamilleVisiteurSearch" placeholder="Rechercher ou saisir un nom..." autocomplete="off">
            <input type="hidden" id="cuisFamilleVisiteurId">
            <div class="cuis-autocomplete-list" id="cuisVisiteurResults"></div>
            <div class="form-check mt-1" id="cuisSaveVisiteurWrap" style="display:none">
              <input type="checkbox" class="form-check-input" id="cuisSaveVisiteur">
              <label class="form-check-label small" for="cuisSaveVisiteur">Enregistrer ce visiteur pour la prochaine fois</label>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Nb personnes</label>
              <input type="number" class="form-control" id="cuisFamilleNb" value="1" min="1" max="20">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Remarques</label>
            <textarea class="form-control" id="cuisFamilleRemarques" rows="2" maxlength="2000"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="cuisFamilleSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal ajout VIP -->
<div class="modal fade" id="cuisVipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter un résident VIP</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control" id="cuisVipResidentSearch" placeholder="Rechercher un résident..." autocomplete="off">
        <div class="cuis-autocomplete-list" id="cuisVipResidentResults"></div>
      </div>
    </div>
  </div>
</div>
