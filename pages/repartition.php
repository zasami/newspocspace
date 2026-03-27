<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
  <div>
    <h1><i class="bi bi-grid-3x3-gap"></i> Répartition</h1>
    <p id="repWeekLabel" class="text-muted" style="margin:0">Chargement...</p>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem">
    <button class="btn btn-sm btn-outline-secondary" id="repPrevWeek" title="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
    <button class="btn btn-sm btn-outline-secondary" id="repToday" title="Cette semaine">Aujourd'hui</button>
    <button class="btn btn-sm btn-outline-secondary" id="repNextWeek" title="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
  </div>
</div>

<!-- Module tabs + bouton horaires -->
<div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem">
  <div id="repModuleTabs" style="display:flex;gap:0.4rem;flex-wrap:wrap;flex:1"></div>
  <button class="btn btn-sm btn-outline-secondary" id="repInfoBtn" style="display:inline-flex;align-items:center;gap:0.4rem;border-radius:8px;flex-shrink:0" title="Détail des horaires"><i class="bi bi-clock"></i> Horaires</button>
</div>

<!-- Modal horaires -->
<div class="modal fade" id="repHorairesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock"></i> Horaires types</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--zt-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="padding:0;max-height:60vh;overflow-y:auto" id="repHorairesBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Grid -->
<div class="card">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table class="table table-sm" style="margin:0;font-size:0.85rem" id="repTable">
      <thead id="repHead"></thead>
      <tbody id="repBody">
        <tr><td colspan="8" class="text-center py-4 text-muted">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>
