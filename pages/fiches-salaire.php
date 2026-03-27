<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1><i class="bi bi-receipt"></i> Fiches de salaire</h1>
</div>

<div class="d-flex gap-2 align-items-center mb-3">
  <button class="btn btn-sm btn-outline-secondary" id="fsPrevYear"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold" id="fsYearLabel" style="min-width:50px;text-align:center"></span>
  <button class="btn btn-sm btn-outline-secondary" id="fsNextYear"><i class="bi bi-chevron-right"></i></button>
</div>

<div id="fsGrid" class="row g-3">
  <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
</div>

<style>
.fiche-card{border-radius:12px;padding:1rem;display:flex;align-items:center;gap:0.75rem;cursor:pointer;transition:transform .15s,box-shadow .15s;border:1px solid var(--zt-border-light,#e5e5e5)}
.fiche-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
.fiche-card .fiche-icon{width:44px;height:44px;border-radius:10px;background:#fce4ec;color:#c62828;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.fiche-card .fiche-info{flex:1;min-width:0}
.fiche-card .fiche-period{font-weight:600;font-size:0.95rem}
.fiche-card .fiche-meta{font-size:0.78rem;color:var(--zt-text-muted,#888)}
.fiche-empty-month{border-radius:12px;padding:1rem;display:flex;align-items:center;gap:0.75rem;border:1px dashed var(--zt-border-light,#ddd);opacity:.5}
.fiche-empty-month .fiche-icon{width:44px;height:44px;border-radius:10px;background:#f5f5f5;color:#bbb;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
</style>
