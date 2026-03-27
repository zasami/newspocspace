<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1>Bonjour <span id="chUserName"></span></h1>
  <p>Tableau de bord cuisine</p>
</div>

<!-- Stats cards -->
<div class="stats-grid" id="chStats">
  <div class="stat-card">
    <div class="stat-icon teal"><i class="bi bi-people"></i></div>
    <div>
      <div class="stat-value" id="chStatCouverts">—</div>
      <div class="stat-label">Couverts aujourd'hui</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-egg-fried"></i></div>
    <div>
      <div class="stat-value" id="chStatMenu">—</div>
      <div class="stat-label">Menu</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="bi bi-flower1"></i></div>
    <div>
      <div class="stat-value" id="chStatSalade">—</div>
      <div class="stat-label">Salade</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="bi bi-journal-check"></i></div>
    <div>
      <div class="stat-value" id="chStatMenusSaisis">—</div>
      <div class="stat-label">Menus saisis cette sem.</div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 flex-wrap" style="align-items:flex-start">

  <!-- Commandes du jour -->
  <div class="card" style="flex:1; min-width:320px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0"><i class="bi bi-receipt"></i> Commandes du jour</h3>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <select class="form-select form-select-sm" id="chRepas" style="width:auto">
          <option value="midi">Midi</option>
          <option value="soir">Soir</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="chPrintBtn" title="Imprimer"><i class="bi bi-printer"></i></button>
      </div>
    </div>
    <div class="card-body" id="chCommandesBody" style="max-height:500px;overflow-y:auto">
      <div class="empty-state"><i class="bi bi-receipt"></i><p>Chargement...</p></div>
    </div>
  </div>

  <!-- Menu de la semaine -->
  <div class="card" style="flex:1; min-width:320px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0"><i class="bi bi-journal-text"></i> Menus de la semaine</h3>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <button class="btn btn-sm btn-outline-secondary" id="chMenuPrev"><i class="bi bi-chevron-left"></i></button>
        <span id="chMenuWeekLabel" style="font-size:0.85rem;font-weight:600;min-width:80px;text-align:center"></span>
        <button class="btn btn-sm btn-outline-secondary" id="chMenuNext"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div class="card-body" id="chMenusBody" style="max-height:500px;overflow-y:auto">
      <div class="empty-state"><i class="bi bi-journal-text"></i><p>Chargement...</p></div>
    </div>
  </div>

</div>

<!-- Modal édition menu -->
<div class="modal fade" id="chMenuEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:500px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="chMenuEditTitle">Modifier le menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="chEditDate">
        <input type="hidden" id="chEditRepas">
        <div class="mb-2">
          <label class="form-label small fw-bold">Entrée</label>
          <input type="text" class="form-control form-control-sm" id="chEditEntree">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Plat principal <span class="text-danger">*</span></label>
          <input type="text" class="form-control form-control-sm" id="chEditPlat">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Salade</label>
          <input type="text" class="form-control form-control-sm" id="chEditSalade">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Accompagnement</label>
          <input type="text" class="form-control form-control-sm" id="chEditAccomp">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Dessert</label>
          <input type="text" class="form-control form-control-sm" id="chEditDessert">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Remarques</label>
          <input type="text" class="form-control form-control-sm" id="chEditRemarques">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="chEditSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>
