<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
<div class="page-header">
  <h1><i class="bi bi-folder2-open"></i> Documents</h1>
  <p>Consultez et téléchargez les documents de l'établissement</p>
</div>

<!-- Service filter cards -->
<div id="docServiceCards" class="doc-service-cards-row" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;"></div>

<!-- Documents grid -->
<div id="docGrid" class="doc-grid">
  <div class="page-loading"><span class="spinner"></span></div>
</div>

<style>
/* ─── Document service filter pills ─── */
.doc-filter-pill {
    display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .85rem;
    border-radius:2rem; border:1px solid var(--zt-border-light); background:var(--zt-bg);
    cursor:pointer; font-size:.82rem; font-weight:500; transition:all .2s;
    white-space:nowrap;
}
.doc-filter-pill:hover { border-color:var(--zt-accent); }
.doc-filter-pill.active { background:var(--zt-accent); color:#fff; border-color:var(--zt-accent); }
.doc-filter-pill .pill-icon { font-size:.9rem; }
.doc-filter-pill .pill-count { font-size:.72rem; opacity:.7; margin-left:.15rem; }

/* ─── Documents grid ─── */
.doc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem; }

/* ─── Document card ─── */
.doc-card {
    background:var(--zt-card-bg, #fff); border:1px solid var(--zt-border-light); border-radius:.75rem;
    padding:1.25rem; transition:all .2s; cursor:pointer; position:relative; overflow:hidden;
}
.doc-card:hover { border-color:var(--zt-accent); transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,.06); }
.doc-card .doc-card-top { display:flex; align-items:flex-start; gap:.75rem; margin-bottom:.75rem; }
.doc-card .doc-icon-box {
    width:48px; height:48px; border-radius:.6rem; display:flex; align-items:center; justify-content:center;
    font-size:1.5rem; flex-shrink:0;
}
.doc-card .doc-icon-box.pdf { background:rgba(220,53,69,.1); color:#dc3545; }
.doc-card .doc-icon-box.word { background:rgba(13,110,253,.1); color:#0d6efd; }
.doc-card .doc-icon-box.excel { background:rgba(25,135,84,.1); color:#198754; }
.doc-card .doc-icon-box.ppt { background:rgba(253,126,20,.1); color:#fd7e14; }
.doc-card .doc-icon-box.image { background:rgba(111,66,193,.1); color:#6f42c1; }
.doc-card .doc-icon-box.other { background:rgba(108,117,125,.1); color:#6c757d; }

.doc-card .doc-card-title { font-weight:600; font-size:.9rem; line-height:1.3; margin-bottom:.15rem; }
.doc-card .doc-card-filename { font-size:.75rem; color:var(--zt-text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; }

.doc-card .doc-card-desc { font-size:.8rem; color:var(--zt-text-secondary); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:.75rem; min-height:2.2rem; }

.doc-card .doc-card-footer { display:flex; align-items:center; justify-content:space-between; }
.doc-card .doc-card-meta { font-size:.72rem; color:var(--zt-text-muted); display:flex; align-items:center; gap:.75rem; }
.doc-card .doc-service-badge {
    display:inline-flex; align-items:center; gap:.25rem; font-size:.7rem; padding:.15rem .5rem;
    border-radius:.3rem; font-weight:500;
}
.doc-card .doc-card-actions { display:flex; gap:.35rem; }
.doc-card .doc-action-btn {
    width:30px; height:30px; border-radius:.35rem; border:1px solid var(--zt-border-light);
    background:transparent; display:flex; align-items:center; justify-content:center;
    font-size:.85rem; color:var(--zt-text-secondary); cursor:pointer; transition:all .15s;
}
.doc-card .doc-action-btn:hover { background:var(--zt-accent); color:#fff; border-color:var(--zt-accent); }

.doc-empty-state { grid-column:1/-1; text-align:center; padding:3rem 1rem; color:var(--zt-text-muted); }
.doc-empty-state i { font-size:3rem; display:block; margin-bottom:.5rem; opacity:.4; }
</style>
