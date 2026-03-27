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

<!-- Module tabs -->
<div id="repModuleTabs" style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem"></div>

<!-- Legend -->
<div id="repLegend" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem"></div>

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
