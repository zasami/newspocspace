<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$docServices = Db::fetchAll(
    "SELECT s.*, (SELECT COUNT(*) FROM documents d WHERE d.service_id = s.id) AS doc_count
     FROM document_services s ORDER BY s.ordre, s.nom"
);
?>
<style>
/* ── Service cards ── */
.doc-services { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-bottom: 20px; }
.doc-svc-card {
  position: relative; border: 1.5px solid var(--cl-border-light, #F0EDE8); border-radius: 12px; padding: 14px 16px;
  cursor: pointer; transition: all .18s; background: var(--cl-bg); display: flex; align-items: center; gap: 12px;
}
.doc-svc-card:hover { background: var(--cl-surface); border-color: var(--cl-border-hover); }
.doc-svc-card.active { background: var(--cl-surface); border-color: var(--svc-color); box-shadow: 0 0 0 1px var(--svc-color); }
.doc-svc-check {
  position: absolute; top: 6px; right: 6px; width: 18px; height: 18px; border-radius: 50%;
  background: var(--svc-color); color: #fff; font-size: .55rem; display: none;
  align-items: center; justify-content: center;
}
.doc-svc-card.active .doc-svc-check { display: flex; }
.doc-svc-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); transition: all .18s; }
.doc-svc-card.active .doc-svc-icon { background: var(--svc-bg); color: var(--svc-color); }
.doc-svc-name { font-weight: 600; font-size: .88rem; line-height: 1.2; }
.doc-svc-count { font-size: .72rem; color: var(--cl-text-muted); }

/* ── Table ── */
.doc-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.doc-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; }
.doc-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.doc-table tr:hover td { background: var(--cl-bg); }

.doc-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.doc-icon-pdf  { background: #E2B8AE; color: #7B3B2C; }
.doc-icon-word { background: #B8C9D4; color: #3B4F6B; }
.doc-icon-xls  { background: #bcd2cb; color: #2d4a43; }
.doc-icon-ppt  { background: #D4C4A8; color: #6B5B3E; }
.doc-icon-img  { background: #D0C4D8; color: #5B4B6B; }
.doc-icon-other { background: var(--cl-bg); color: var(--cl-text-muted); }

.doc-name-cell { display: flex; align-items: center; gap: 12px; }
.doc-name { font-weight: 600; font-size: .88rem; }
.doc-desc { font-size: .75rem; color: var(--cl-text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-badge-svc { font-size: .72rem; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
.doc-badge-visible   { background: #bcd2cb; color: #2d4a43; }
.doc-badge-hidden    { background: #E2B8AE; color: #7B3B2C; }
.doc-badge-archived  { background: #C8C4BE; color: #555; }
.doc-version-badge   { font-size: .62rem; background: #B8C9D4; color: #3B4F6B; padding: 1px 6px; border-radius: 8px; font-weight: 600; margin-left: 4px; }

/* ── Version history modal ── */
.doc-ver-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.doc-ver-item:last-child { border: none; }
.doc-ver-badge { min-width: 36px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; }
.doc-ver-current { background: #bcd2cb; color: #2d4a43; }
.doc-ver-old     { background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); }

/* ── Word preview in lightbox ── */
.doc-lb-word-wrap {
    width: 95vw; max-width: 1000px; height: 92vh;
    display: flex; flex-direction: column;
    background: var(--cl-surface, #fff); border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,.4); overflow: hidden;
}
.doc-lb-word-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); flex-shrink: 0;
}
.doc-lb-word-header h6 { margin: 0; font-weight: 700; font-size: .95rem; display: flex; align-items: center; gap: 8px; flex: 1; }
.doc-lb-word-close {
    width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--cl-border, #e5e7eb);
    background: var(--cl-bg, #f9fafb); cursor: pointer; display: flex; align-items: center;
    justify-content: center; font-size: .85rem; color: var(--cl-text-muted); transition: all .15s; flex-shrink: 0;
}
.doc-lb-word-close:hover { background: var(--cl-border-light); color: var(--cl-text); }
.doc-lb-word-body {
    flex: 1; overflow-y: auto; padding: 24px;
    background: var(--cl-bg, #F7F5F2);
}
.doc-lb-word-body .docx-wrapper,
.doc-lb-word-body > div { background: var(--cl-bg, #F7F5F2) !important; padding: 20px !important; display: flex; flex-direction: column; align-items: center; gap: 20px; }
.doc-lb-word-body .docx-wrapper > section,
.doc-lb-word-body .docx-wrapper section.docx,
.docx-preview-body-wrapper > section,
.docx-preview-body-wrapper > article {
    background: #fff !important; box-shadow: 0 2px 10px rgba(0,0,0,.08) !important;
    margin: 0 auto 20px !important; border-radius: 8px !important; border: none !important;
}
/* Kill any grey/dark wrapper from the lib */
.doc-lb-word-body [style*="background"] { background: var(--cl-bg, #F7F5F2) !important; }
.docx-preview-body-wrapper { background: var(--cl-bg, #F7F5F2) !important; }
/* Zoom slider warm color */
.doc-lb-word-footer input[type="range"] { accent-color: #bcd2cb; }
.doc-lb-word-footer input[type="range"]::-webkit-slider-runnable-track { background: var(--cl-border-light, #E8E5E0); }
.doc-lb-word-footer input[type="range"]::-moz-range-track { background: var(--cl-border-light, #E8E5E0); }
.doc-lb-word-footer {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    padding: 10px 20px; border-top: 1px solid var(--cl-border-light, #F0EDE8); flex-shrink: 0;
}
@media (max-width: 900px) {
    .doc-lb-word-wrap { width: 100vw; height: 100vh; border-radius: 0; }
}
.doc-lb-word-loading { text-align: center; padding: 60px 20px; color: #999; }
.doc-badge-restricted { background: #D4C4A8; color: #6B5B3E; }
.doc-row-actions { display: flex; gap: 2px; }
.doc-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .88rem; transition: all .12s; display: flex; align-items: center; justify-content: center; }
.doc-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.doc-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

/* ── Upload area ── */
.doc-upload-zone { border: 2px dashed var(--cl-border); border-radius: 12px; padding: 28px 20px; text-align: center; cursor: pointer; transition: all .2s; color: var(--cl-text-muted); }
.doc-upload-zone:hover, .doc-upload-zone.dragover { border-color: var(--cl-accent); background: rgba(25,25,24,.02); }
.doc-upload-zone i { font-size: 2rem; opacity: .3; display: block; margin-bottom: 6px; }

/* ── Services list in modal ── */
.doc-svc-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.doc-svc-row:last-child { border: none; }
.doc-color-dot { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }

/* ── Clickable name ── */
.doc-name-link { cursor: pointer; }
.doc-name-link:hover .doc-name { color: var(--cl-accent); text-decoration: underline; }

/* ── Empty ── */
.doc-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.doc-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }

/* ── Lightbox ── */
.doc-lb { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; animation: docLbIn .2s ease; }
.doc-lb-hidden { display: none !important; }
.doc-lb-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.82); backdrop-filter: blur(8px); }
.doc-lb-stage { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; z-index: 1; }
.doc-lb-stage img { max-width: 90vw; max-height: calc(100vh - 100px); object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
.doc-lb-stage iframe { width: 85vw; height: calc(100vh - 100px); border: none; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); background: #fff; }
.doc-lb-close { position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,.12); border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; z-index: 10; backdrop-filter: blur(8px); transition: background .2s; }
.doc-lb-close:hover { background: rgba(255,255,255,.22); }
.doc-lb-title { position: absolute; top: 16px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.12); color: #fff; padding: 8px 20px; border-radius: 20px; font-size: .85rem; font-weight: 600; backdrop-filter: blur(8px); z-index: 10; max-width: 60vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.doc-lb-dl, .doc-lb-fs { position: absolute; top: 16px; background: rgba(255,255,255,.12); border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; z-index: 10; backdrop-filter: blur(8px); text-decoration: none; transition: background .2s; }
.doc-lb-dl { right: 70px; }
.doc-lb-fs { right: 120px; }
.doc-lb-dl:hover, .doc-lb-fs:hover { background: rgba(255,255,255,.22); color: #fff; }
.doc-lb-fs.d-none { display: none !important; }
@keyframes docLbIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<!-- Header -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <button class="btn btn-sm btn-outline-secondary" id="btnManageServices"><i class="bi bi-palette"></i> Services</button>
  <button class="btn btn-sm btn-outline-secondary" id="btnToggleArchived"><i class="bi bi-archive"></i> Voir archives</button>
  <div class="ms-auto"></div>
  <button class="btn btn-sm btn-primary" id="btnUploadDoc"><i class="bi bi-cloud-upload"></i> Téléverser</button>
</div>

<!-- Service cards -->
<div class="doc-services" id="svcGrid"></div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center py-2">
    <h6 class="mb-0" id="docListTitle"><i class="bi bi-folder2-open"></i> Tous les documents</h6>
    <small class="text-muted" id="docCount"></small>
  </div>
  <div class="card-body p-0">
    <table class="doc-table">
      <thead><tr><th>Document</th><th>Service</th><th>Taille</th><th>Date</th><th>Statut</th><th></th></tr></thead>
      <tbody id="docBody"><tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></td></tr></tbody>
    </table>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Téléverser un document</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <div class="mb-3"><label class="form-label small fw-bold">Titre *</label><input class="form-control form-control-sm" id="upTitre" maxlength="255"></div>
        <div class="mb-3"><label class="form-label small fw-bold">Service *</label><div class="zs-select" id="upService" data-placeholder="Choisir..."></div></div>
        <div class="mb-3"><label class="form-label small fw-bold">Description</label><textarea class="form-control form-control-sm" id="upDesc" rows="2" maxlength="2000"></textarea></div>
        <div class="doc-upload-zone" id="upZone">
          <i class="bi bi-cloud-arrow-up"></i>
          <p class="mb-0"><strong>Cliquez ou glissez</strong> un fichier ici</p>
          <small>PDF, Word, Excel, images — max 20 Mo</small>
          <input type="file" id="upFile" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.svg,.txt,.csv">
        </div>
        <div id="upPreview" class="d-none mt-2">
          <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:var(--cl-bg)">
            <i class="bi bi-file-earmark" style="font-size:1.3rem"></i>
            <div style="flex:1;min-width:0"><div class="small fw-semibold" id="upFileName"></div><div class="text-muted" style="font-size:.72rem" id="upFileSize"></div></div>
            <button type="button" class="doc-row-btn danger" id="upFileClear"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary" id="upSubmit" disabled><i class="bi bi-upload"></i> Téléverser</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier le document</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <input type="hidden" id="editDocId">
        <div class="mb-3"><label class="form-label small fw-bold">Titre</label><input class="form-control form-control-sm" id="editTitre" maxlength="255"></div>
        <div class="mb-3"><label class="form-label small fw-bold">Service</label><div class="zs-select" id="editService" data-placeholder="Service"></div></div>
        <div class="mb-3"><label class="form-label small fw-bold">Description</label><textarea class="form-control form-control-sm" id="editDesc" rows="2" maxlength="2000"></textarea></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary" id="editSave"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Services Modal -->
<div class="modal fade" id="svcModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-palette"></i> Gérer les services</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <div id="svcList" class="mb-3"></div>
        <hr>
        <label class="form-label small fw-bold">Ajouter un service</label>
        <div class="d-flex gap-2">
          <input class="form-control form-control-sm" id="newSvcName" placeholder="Nom du service" maxlength="100">
          <input type="color" class="form-control form-control-sm form-control-color" id="newSvcColor" value="#6B5B3E" style="width:40px;padding:2px">
          <button class="btn btn-sm btn-primary" id="addSvcBtn"><i class="bi bi-plus"></i></button>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Access Modal -->
<div class="modal fade" id="accessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Contrôle d'accès</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <p class="text-muted small">Bloquez l'accès par rôle ou service. Par défaut, tout le monde y a accès.</p>
        <label class="form-label small fw-bold">Bloquer par rôle</label>
        <div id="accessRoles" class="mb-3"></div>
        <label class="form-label small fw-bold">Bloquer par service</label>
        <div id="accessSvcs"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary" id="saveAccess"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const ssrServices = <?= json_encode(array_values($docServices), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let services = ssrServices;
    let currentFilter = '';
    let currentAccessDocId = null;
    let searchTimeout = null;

    // ── Helpers ──
    function fIcon(mime) {
        if (mime?.includes('pdf')) return { cls: 'doc-icon-pdf', icon: 'bi-file-earmark-pdf-fill' };
        if (mime?.includes('word') || mime?.includes('document')) return { cls: 'doc-icon-word', icon: 'bi-file-earmark-word-fill' };
        if (mime?.includes('excel') || mime?.includes('sheet') || mime?.includes('csv')) return { cls: 'doc-icon-xls', icon: 'bi-file-earmark-excel-fill' };
        if (mime?.includes('presentation') || mime?.includes('powerpoint')) return { cls: 'doc-icon-ppt', icon: 'bi-file-earmark-ppt-fill' };
        if (mime?.includes('image')) return { cls: 'doc-icon-img', icon: 'bi-file-earmark-image-fill' };
        return { cls: 'doc-icon-other', icon: 'bi-file-earmark-fill' };
    }
    function fmtSize(b) { if (b<1024) return b+' o'; if (b<1048576) return (b/1024).toFixed(1)+' Ko'; return (b/1048576).toFixed(1)+' Mo'; }
    function fmtDate(d) { return new Date(d).toLocaleDateString('fr-CH',{day:'numeric',month:'short',year:'numeric'}); }

    // ── Services ──
    async function loadSvcs() {
        const r = await adminApiPost('admin_get_document_services');
        if (r.success) services = r.services || [];
        renderSvcCards();
        initSelects();
    }

    // Warm color palette (cycles like stats page)
    const warmPalette = [
        { bg: '#bcd2cb', color: '#2d4a43' }, // teal
        { bg: '#B8C9D4', color: '#3B4F6B' }, // blue
        { bg: '#D4C4A8', color: '#6B5B3E' }, // orange
        { bg: '#E2B8AE', color: '#7B3B2C' }, // red
        { bg: '#D0C4D8', color: '#5B4B6B' }, // purple
    ];

    function renderSvcCards() {
        const g = document.getElementById('svcGrid');
        const total = services.reduce((s,c) => s + parseInt(c.doc_count||0), 0);
        const allPalette = warmPalette[0]; // teal for "Tous"
        let h = '<div class="doc-svc-card' + (!currentFilter ? ' active' : '') + '" data-svc="" style="--svc-bg:' + allPalette.bg + ';--svc-color:' + allPalette.color + '">'
            + '<div class="doc-svc-check"><i class="bi bi-check-lg"></i></div>'
            + '<div class="doc-svc-icon"><i class="bi bi-grid-fill"></i></div>'
            + '<div><div class="doc-svc-name">Tous</div><div class="doc-svc-count">' + total + ' docs</div></div></div>';
        services.filter(s => s.actif != 0).forEach((s, i) => {
            const pal = warmPalette[(i + 1) % warmPalette.length];
            h += '<div class="doc-svc-card' + (currentFilter === s.id ? ' active' : '') + '" data-svc="' + s.id + '" style="--svc-bg:' + pal.bg + ';--svc-color:' + pal.color + '">'
                + '<div class="doc-svc-check"><i class="bi bi-check-lg"></i></div>'
                + '<div class="doc-svc-icon"><i class="bi bi-' + escapeHtml(s.icone) + '"></i></div>'
                + '<div><div class="doc-svc-name">' + escapeHtml(s.nom) + '</div><div class="doc-svc-count">' + (s.doc_count||0) + ' docs</div></div></div>';
        });
        g.innerHTML = h;
        g.querySelectorAll('.doc-svc-card').forEach(c => c.addEventListener('click', () => {
            currentFilter = c.dataset.svc || '';
            renderSvcCards();
            loadDocs();
        }));
    }

    function initSelects() {
        const opts = services.filter(s => s.actif != 0).map(s => ({ value: s.id, label: s.nom }));
        ['upService','editService'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            zerdaSelect.destroy(el);
            zerdaSelect.init(el, opts, { search: opts.length > 5 });
        });
    }

    // ── Archive toggle ──
    let showArchived = false;
    document.getElementById('btnToggleArchived')?.addEventListener('click', function() {
        showArchived = !showArchived;
        this.classList.toggle('btn-outline-secondary', !showArchived);
        this.classList.toggle('btn-secondary', showArchived);
        this.innerHTML = showArchived ? '<i class="bi bi-archive-fill"></i> Masquer archives' : '<i class="bi bi-archive"></i> Voir archives';
        loadDocs();
    });

    // ── Documents ──
    async function loadDocs() {
        const q = document.getElementById('topbarSearchInput')?.value?.trim() || '';
        const r = await adminApiPost('admin_get_documents', { service_id: currentFilter, search: q, show_archived: showArchived ? 1 : 0 });
        if (!r.success) return;
        const docs = r.documents || [];
        const tbody = document.getElementById('docBody');
        const title = document.getElementById('docListTitle');
        document.getElementById('docCount').textContent = (r.total||0) + ' document(s)';

        if (currentFilter) {
            const svc = services.find(s => s.id === currentFilter);
            title.innerHTML = svc ? '<i class="bi bi-' + escapeHtml(svc.icone) + '" style="color:' + escapeHtml(svc.couleur) + '"></i> ' + escapeHtml(svc.nom) : 'Documents';
        } else {
            title.innerHTML = '<i class="bi bi-folder2-open"></i> Tous les documents';
        }

        if (!docs.length) { tbody.innerHTML = '<tr><td colspan="6"><div class="doc-empty"><i class="bi bi-folder2-open"></i><p>Aucun document</p></div></td></tr>'; return; }

        tbody.innerHTML = docs.map(d => {
            const fi = fIcon(d.mime_type);
            const st = d.visible == 1
                ? (d.restrictions > 0 ? '<span class="doc-badge-svc doc-badge-restricted"><i class="bi bi-shield-exclamation"></i> Restreint</span>' : '<span class="doc-badge-svc doc-badge-visible"><i class="bi bi-eye"></i> Visible</span>')
                : '<span class="doc-badge-svc doc-badge-hidden"><i class="bi bi-eye-slash"></i> Masqué</span>';
            const url = '/zerdatime/admin/api.php?action=admin_serve_document&id=' + encodeURIComponent(d.id);
            const isArchived = !!d.archived_at;
            const vBadge = d.version > 1 ? ' <span class="doc-version-badge">v' + d.version + '</span>' : '';
            const archBadge = isArchived ? '<span class="doc-badge-svc doc-badge-archived"><i class="bi bi-archive"></i> Archivé</span>' : st;
            return '<tr' + (isArchived ? ' style="opacity:.6"' : '') + '>'
                + '<td><div class="doc-name-cell doc-name-link" data-act="view" data-url="' + url + '" data-mime="' + escapeHtml(d.mime_type||'') + '" data-titre="' + escapeHtml(d.titre) + '"><div class="doc-icon ' + fi.cls + '"><i class="bi ' + fi.icon + '"></i></div>'
                + '<div><div class="doc-name">' + escapeHtml(d.titre) + vBadge + '</div><div class="doc-desc">' + escapeHtml(d.original_name) + (d.description ? ' — ' + escapeHtml(d.description) : '') + '</div></div></div></td>'
                + '<td><span class="doc-badge-svc" style="background:' + escapeHtml(d.service_couleur) + '15;color:' + escapeHtml(d.service_couleur) + '">' + escapeHtml(d.service_nom) + '</span></td>'
                + '<td class="text-muted small">' + fmtSize(d.size) + '</td>'
                + '<td class="text-muted small">' + fmtDate(d.created_at) + '</td>'
                + '<td>' + archBadge + '</td>'
                + '<td><div class="doc-row-actions">'
                + '<button class="doc-row-btn" title="Voir" data-act="view" data-url="' + url + '" data-mime="' + escapeHtml(d.mime_type||'') + '" data-titre="' + escapeHtml(d.titre) + '"><i class="bi bi-eye"></i></button>'
                + '<button class="doc-row-btn" title="Nouvelle version" data-act="newver" data-id="' + d.id + '" data-titre="' + escapeHtml(d.titre) + '"><i class="bi bi-cloud-upload"></i></button>'
                + '<button class="doc-row-btn" title="Historique versions" data-act="versions" data-id="' + d.id + '" data-titre="' + escapeHtml(d.titre) + '"><i class="bi bi-clock-history"></i></button>'
                + '<button class="doc-row-btn" title="Modifier" data-act="edit" data-id="' + d.id + '" data-titre="' + escapeHtml(d.titre) + '" data-svc="' + d.service_id + '" data-desc="' + escapeHtml(d.description||'') + '"><i class="bi bi-pencil"></i></button>'
                + '<button class="doc-row-btn" title="Accès" data-act="access" data-id="' + d.id + '"><i class="bi bi-shield-lock"></i></button>'
                + (isArchived
                    ? '<button class="doc-row-btn" title="Restaurer" data-act="restore" data-id="' + d.id + '"><i class="bi bi-arrow-counterclockwise"></i></button>'
                    : '<button class="doc-row-btn" title="Archiver" data-act="archive" data-id="' + d.id + '"><i class="bi bi-archive"></i></button>')
                + '<button class="doc-row-btn danger" title="Supprimer" data-act="del" data-id="' + d.id + '" data-titre="' + escapeHtml(d.titre) + '"><i class="bi bi-trash3"></i></button>'
                + '</div></td></tr>';
        }).join('');
    }

    // ── Table actions ──
    document.getElementById('docBody')?.addEventListener('click', e => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        e.stopPropagation();
        if (b.dataset.act === 'view') { e.preventDefault(); viewDoc(b.dataset.url, b.dataset.mime, b.dataset.titre); }
        else if (b.dataset.act === 'edit') { document.getElementById('editDocId').value = b.dataset.id; document.getElementById('editTitre').value = b.dataset.titre; zerdaSelect.setValue('#editService', b.dataset.svc); document.getElementById('editDesc').value = b.dataset.desc; new bootstrap.Modal(document.getElementById('editModal')).show(); }
        else if (b.dataset.act === 'access') openAccess(b.dataset.id);
        else if (b.dataset.act === 'toggle') toggleVis(b.dataset.id);
        else if (b.dataset.act === 'archive') archiveDoc(b.dataset.id);
        else if (b.dataset.act === 'restore') restoreDoc(b.dataset.id);
        else if (b.dataset.act === 'newver') openNewVersion(b.dataset.id, b.dataset.titre);
        else if (b.dataset.act === 'versions') openVersions(b.dataset.id, b.dataset.titre);
        else if (b.dataset.act === 'del') delDoc(b.dataset.id, b.dataset.titre);
    });

    // ── Lightbox ──
    function viewDoc(url, mime, titre) {
        const isImage = mime && mime.startsWith('image/');
        const isPdf = mime && mime.includes('pdf');
        const isWord = mime && (mime.includes('word') || mime.includes('msword'));
        const isExcel = mime && (mime.includes('excel') || mime.includes('spreadsheet'));
        const isText = mime && (mime.includes('text/plain') || mime.includes('text/csv'));

        if (!isImage && !isPdf && !isWord && !isText) {
            const a = document.createElement('a');
            a.href = url; a.download = ''; a.click();
            return;
        }

        let lb = document.getElementById('docLightbox');
        if (!lb) {
            lb = document.createElement('div');
            lb.id = 'docLightbox';
            lb.className = 'doc-lb';
            lb.innerHTML = '<div class="doc-lb-overlay"></div>'
                + '<button class="doc-lb-close"><i class="bi bi-x-lg"></i></button>'
                + '<div class="doc-lb-title" id="docLbTitle"></div>'
                + '<button class="doc-lb-fs d-none" id="docLbFs" title="Plein écran"><i class="bi bi-arrows-fullscreen"></i></button>'
                + '<a class="doc-lb-dl" id="docLbDl" title="Télécharger"><i class="bi bi-download"></i></a>'
                + '<div class="doc-lb-stage" id="docLbStage"></div>';
            document.body.appendChild(lb);
        }

        document.getElementById('docLbTitle').textContent = titre;
        document.getElementById('docLbDl').href = url;
        document.getElementById('docLbDl').setAttribute('target', '_blank');
        const stage = document.getElementById('docLbStage');

        const fsBtn = document.getElementById('docLbFs');

        if (isImage) {
            stage.innerHTML = '<img src="' + url + '" alt="' + escapeHtml(titre) + '" draggable="false">';
            fsBtn.classList.add('d-none');
        } else if (isPdf) {
            stage.innerHTML = '<iframe id="docLbIframe" src="' + url + '#toolbar=1&view=FitH" allowfullscreen style="border:none;width:100%;height:100%"></iframe>';
            fsBtn.classList.remove('d-none');
        } else if (isWord) {
            stage.innerHTML = '<div class="doc-lb-word-wrap">'
                + '<div class="doc-lb-word-header"><h6><i class="bi bi-file-earmark-word" style="color:#2B579A"></i> ' + escapeHtml(titre) + '</h6><button class="doc-lb-word-close" id="docxClose"><i class="bi bi-x-lg"></i></button></div>'
                + '<div class="doc-lb-word-body"><div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm"></span> Chargement du document...</div><div id="docxContainer"></div></div>'
                + '<div class="doc-lb-word-footer">'
                + '<button class="btn btn-sm btn-outline-secondary" id="docxZoomOut" title="Dézoomer"><i class="bi bi-zoom-out"></i></button>'
                + '<input type="range" id="docxZoomSlider" min="50" max="200" value="100" style="width:100px;accent-color:#2d4a43">'
                + '<button class="btn btn-sm btn-outline-secondary" id="docxZoomIn" title="Zoomer"><i class="bi bi-zoom-in"></i></button>'
                + '<span id="docxZoomVal" style="font-size:.78rem;font-weight:600;min-width:40px;text-align:center">100%</span>'
                + '<div style="flex:1"></div>'
                + '<button class="btn btn-sm btn-outline-secondary" id="docxPrint"><i class="bi bi-file-earmark-pdf"></i> Exporter en PDF</button>'
                + '<a href="' + url + '" download class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Télécharger</a>'
                + '<button class="btn btn-sm btn-light" id="docxCloseFooter">Fermer</button>'
                + '</div>'
                + '</div>';
            fsBtn.classList.add('d-none');
            (async () => {
                try {
                    if (!window.docx) {
                        // Load jszip first (dependency), then docx-preview
                        if (!window.JSZip) {
                            await new Promise((resolve, reject) => {
                                const s = document.createElement('script');
                                s.src = '/zerdatime/assets/js/vendor/jszip.min.js';
                                s.onload = resolve; s.onerror = reject;
                                document.head.appendChild(s);
                            });
                        }
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = '/zerdatime/assets/js/vendor/docx-preview.min.js';
                            s.onload = resolve; s.onerror = reject;
                            document.head.appendChild(s);
                        });
                    }
                    const resp = await fetch(url);
                    const blob = await resp.blob();
                    const container = document.getElementById('docxContainer');
                    const loadingEl = stage.querySelector('.doc-lb-word-body > .text-center');
                    if (loadingEl) loadingEl.remove();
                    await window.docx.renderAsync(blob, container, null, {
                        className: 'docx-preview-body',
                        inWrapper: true,
                        ignoreWidth: false,
                        ignoreHeight: false,
                        ignoreFonts: false,
                        breakPages: true,
                        renderHeaders: true,
                        renderFooters: true,
                        renderFootnotes: true,
                    });
                    // Bind close buttons
                    document.getElementById('docxClose')?.addEventListener('click', closeLb);
                    document.getElementById('docxCloseFooter')?.addEventListener('click', closeLb);
                    // Print
                    document.getElementById('docxPrint')?.addEventListener('click', () => {
                        const w = window.open('','_blank');
                        w.document.write('<html><head><title>' + escapeHtml(titre) + '</title><style>body{margin:0;padding:20px;font-family:system-ui}@media print{@page{size:A4;margin:15mm}}</style></head><body>' + container.innerHTML + '</body></html>');
                        w.document.close();
                        w.onload = () => w.print();
                    });
                    // Zoom slider
                    let zoom = 100;
                    document.getElementById('docxZoomIn')?.addEventListener('click', () => { zoom = Math.min(200, zoom + 10); applyZoom(); });
                    document.getElementById('docxZoomOut')?.addEventListener('click', () => { zoom = Math.max(50, zoom - 10); applyZoom(); });
                    document.getElementById('docxZoomSlider')?.addEventListener('input', (e) => { zoom = parseInt(e.target.value); applyZoom(); });
                    function applyZoom() {
                        container.style.transform = 'scale(' + (zoom/100) + ')';
                        container.style.transformOrigin = 'top center';
                        document.getElementById('docxZoomVal').textContent = zoom + '%';
                        document.getElementById('docxZoomSlider').value = zoom;
                    }
                } catch(e) {
                    const wrap = stage.querySelector('.doc-lb-word-wrap');
                    if (wrap) wrap.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle" style="font-size:2rem"></i><p>Impossible de prévisualiser</p><a href="' + url + '" download class="btn btn-sm btn-primary mt-2"><i class="bi bi-download"></i> Télécharger</a></div>';
                }
            })();
        } else if (isText) {
            stage.innerHTML = '<div class="doc-lb-word-wrap"><div class="doc-lb-word-loading"><span class="spinner-border spinner-border-sm"></span></div></div>';
            fsBtn.classList.add('d-none');
            fetch(url).then(r => r.text()).then(text => {
                const wrap = stage.querySelector('.doc-lb-word-wrap');
                if (wrap) wrap.innerHTML = '<div class="doc-lb-word-content"><pre style="white-space:pre-wrap;font-size:.88rem">' + escapeHtml(text) + '</pre></div>';
            });
        }

        lb.classList.remove('doc-lb-hidden');
        document.body.style.overflow = 'hidden';

        const ac = new AbortController();
        const sig = { signal: ac.signal };
        function closeLb() { lb.classList.add('doc-lb-hidden'); document.body.style.overflow = ''; ac.abort(); }
        lb.querySelector('.doc-lb-close').addEventListener('click', closeLb, sig);
        lb.querySelector('.doc-lb-overlay').addEventListener('click', closeLb, sig);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLb(); }, sig);
        fsBtn.addEventListener('click', () => {
            const iframe = document.getElementById('docLbIframe');
            if (iframe) {
                if (iframe.requestFullscreen) iframe.requestFullscreen();
                else if (iframe.webkitRequestFullscreen) iframe.webkitRequestFullscreen();
            }
        }, sig);
    }

    // ── Topbar search ──
    document.getElementById('topbarSearchInput')?.addEventListener('input', () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(loadDocs, 300); });

    // ── Upload ──
    const upZone = document.getElementById('upZone');
    const upFile = document.getElementById('upFile');
    const upPreview = document.getElementById('upPreview');
    document.getElementById('btnUploadDoc')?.addEventListener('click', () => new bootstrap.Modal(document.getElementById('uploadModal')).show());
    upZone?.addEventListener('click', () => upFile?.click());
    upZone?.addEventListener('dragover', e => { e.preventDefault(); upZone.classList.add('dragover'); });
    upZone?.addEventListener('dragleave', () => upZone.classList.remove('dragover'));
    upZone?.addEventListener('drop', e => { e.preventDefault(); upZone.classList.remove('dragover'); if (e.dataTransfer.files[0]) { upFile.files = e.dataTransfer.files; showPrev(e.dataTransfer.files[0]); } });
    upFile?.addEventListener('change', () => { if (upFile.files[0]) showPrev(upFile.files[0]); });
    document.getElementById('upFileClear')?.addEventListener('click', () => { upFile.value=''; upZone?.classList.remove('d-none'); upPreview?.classList.add('d-none'); chkReady(); });
    document.getElementById('upTitre')?.addEventListener('input', chkReady);

    function showPrev(f) { document.getElementById('upFileName').textContent=f.name; document.getElementById('upFileSize').textContent=fmtSize(f.size); upZone?.classList.add('d-none'); upPreview?.classList.remove('d-none'); chkReady(); }
    function chkReady() { document.getElementById('upSubmit').disabled = !(document.getElementById('upTitre')?.value.trim() && zerdaSelect.getValue('#upService') && upFile?.files?.length); }

    document.getElementById('upSubmit')?.addEventListener('click', async () => {
        const btn = document.getElementById('upSubmit');
        btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Envoi...';
        const fd = new FormData();
        fd.append('action','admin_upload_document'); fd.append('titre',document.getElementById('upTitre').value.trim());
        fd.append('description',document.getElementById('upDesc').value.trim()); fd.append('service_id',zerdaSelect.getValue('#upService'));
        fd.append('file',upFile.files[0]);
        try {
            const res = await fetch('/zerdatime/admin/api.php',{method:'POST',headers:{'X-CSRF-Token':window.__ZT_ADMIN__?.csrfToken||''},body:fd});
            const j = await res.json(); if (j.csrf) window.__ZT_ADMIN__.csrfToken=j.csrf;
            if (j.success) { showToast('Document téléversé','success'); bootstrap.Modal.getInstance(document.getElementById('uploadModal'))?.hide(); document.getElementById('upTitre').value=''; document.getElementById('upDesc').value=''; upFile.value=''; upZone?.classList.remove('d-none'); upPreview?.classList.add('d-none'); loadSvcs(); loadDocs(); }
            else showToast(j.message||'Erreur','error');
        } catch(e) { showToast('Erreur réseau','error'); }
        btn.disabled=false; btn.innerHTML='<i class="bi bi-upload"></i> Téléverser';
    });

    // ── Edit ──
    document.getElementById('editSave')?.addEventListener('click', async () => {
        const r = await adminApiPost('admin_update_document',{id:document.getElementById('editDocId').value,titre:document.getElementById('editTitre').value.trim(),service_id:zerdaSelect.getValue('#editService'),description:document.getElementById('editDesc').value.trim()});
        if (r.success) { showToast('Modifié','success'); bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide(); loadDocs(); }
        else showToast(r.message||'Erreur','error');
    });

    // ── Toggle / Delete / Archive / Restore ──
    async function toggleVis(id) { const r = await adminApiPost('admin_toggle_document_visibility',{id}); if (r.success) { showToast(r.message,'success'); loadDocs(); } }

    async function archiveDoc(id) {
        const r = await adminApiPost('admin_archive_document',{id});
        if (r.success) { showToast('Document archivé','success'); loadSvcs(); loadDocs(); }
    }

    async function restoreDoc(id) {
        const r = await adminApiPost('admin_restore_document',{id});
        if (r.success) { showToast('Document restauré','success'); loadSvcs(); loadDocs(); }
    }

    async function delDoc(id,titre) {
        if (!await adminConfirm({title:'Supprimer définitivement',text:'Supprimer <strong>'+escapeHtml(titre)+'</strong> et toutes ses versions ? Cette action est irréversible.',icon:'bi-trash3',type:'danger',okText:'Supprimer'})) return;
        const r = await adminApiPost('admin_delete_document',{id, permanent: true}); if (r.success) { showToast('Supprimé','success'); loadSvcs(); loadDocs(); }
    }

    // ── New version upload ──
    function openNewVersion(docId, titre) {
        let modal = document.getElementById('newVerModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'newVerModal'; modal.className = 'modal fade'; modal.tabIndex = -1;
            modal.innerHTML = `<div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Nouvelle version</h5><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Document : <strong id="newVerTitre"></strong></p>
                    <div class="mb-3"><label class="form-label small fw-bold">Note de version</label><input type="text" class="form-control form-control-sm" id="newVerNote" placeholder="Ex: Mise à jour mars 2026"></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Fichier *</label><input type="file" class="form-control form-control-sm" id="newVerFile"></div>
                    <input type="hidden" id="newVerDocId">
                </div>
                <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="newVerSave"><i class="bi bi-upload"></i> Téléverser</button></div>
            </div></div>`;
            document.body.appendChild(modal);
            document.getElementById('newVerSave').addEventListener('click', async () => {
                const file = document.getElementById('newVerFile').files[0];
                if (!file) { showToast('Fichier requis','error'); return; }
                const fd = new FormData();
                fd.append('action','admin_upload_document');
                fd.append('document_id', document.getElementById('newVerDocId').value);
                fd.append('titre','_'); fd.append('service_id','_'); // not used for versions
                fd.append('version_note', document.getElementById('newVerNote').value);
                fd.append('file', file);
                const r = await fetch('/zerdatime/admin/api.php', {method:'POST', headers:{'X-CSRF-Token':window.__ZT_ADMIN__?.csrfToken||''}, body:fd}).then(r=>r.json());
                if (r.success) { showToast(r.message,'success'); bootstrap.Modal.getInstance(modal)?.hide(); loadDocs(); }
                else showToast(r.message||'Erreur','error');
            });
        }
        document.getElementById('newVerTitre').textContent = titre;
        document.getElementById('newVerDocId').value = docId;
        document.getElementById('newVerNote').value = '';
        document.getElementById('newVerFile').value = '';
        new bootstrap.Modal(modal).show();
    }

    // ── Version history ──
    async function openVersions(docId, titre) {
        let modal = document.getElementById('versionsModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'versionsModal'; modal.className = 'modal fade'; modal.tabIndex = -1;
            modal.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Historique des versions</h5><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
                <div class="modal-body" id="versionsBody"></div>
                <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
            </div></div>`;
            document.body.appendChild(modal);
        }
        document.getElementById('versionsBody').innerHTML = '<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        new bootstrap.Modal(modal).show();

        const r = await adminApiPost('admin_get_document_versions',{document_id:docId});
        if (!r.success) { document.getElementById('versionsBody').innerHTML = '<p class="text-danger">Erreur</p>'; return; }

        const cur = r.current;
        const vers = r.versions || [];

        let h = '<p class="small text-muted mb-3">Document : <strong>' + escapeHtml(titre) + '</strong></p>';

        // Current version
        h += '<div class="doc-ver-item">';
        h += '<span class="doc-ver-badge doc-ver-current">v' + cur.version + '</span>';
        h += '<div class="flex-grow-1"><strong>' + escapeHtml(cur.original_name) + '</strong> <span class="text-muted small">(' + fmtSize(cur.size) + ')</span>';
        h += '<br><span class="text-muted small">Par ' + escapeHtml((cur.prenom||'')+' '+(cur.nom||'')) + ' — ' + fmtDate(cur.updated_at) + '</span></div>';
        h += '<span class="badge" style="background:#bcd2cb;color:#2d4a43;font-size:.7rem">Actuelle</span>';
        h += '<a href="/zerdatime/admin/api.php?action=admin_serve_document&id=' + encodeURIComponent(docId) + '" target="_blank" class="doc-row-btn" title="Voir"><i class="bi bi-eye"></i></a>';
        h += '</div>';

        if (!vers.length) {
            h += '<p class="text-center text-muted py-3 small">Aucune version précédente</p>';
        } else {
            vers.forEach(v => {
                const vUrl = '/zerdatime/admin/api.php?action=admin_serve_document_version&id=' + encodeURIComponent(v.id);
                h += '<div class="doc-ver-item">';
                h += '<span class="doc-ver-badge doc-ver-old">v' + v.version + '</span>';
                h += '<div class="flex-grow-1"><strong>' + escapeHtml(v.original_name) + '</strong> <span class="text-muted small">(' + fmtSize(v.size) + ')</span>';
                if (v.note) h += '<br><em class="small text-muted">' + escapeHtml(v.note) + '</em>';
                h += '<br><span class="text-muted small">Par ' + escapeHtml((v.prenom||'')+' '+(v.nom||'')) + ' — ' + fmtDate(v.created_at) + '</span></div>';
                h += '<a href="' + vUrl + '" target="_blank" class="doc-row-btn" title="Voir"><i class="bi bi-eye"></i></a>';
                h += '<button class="doc-row-btn" title="Restaurer cette version" data-restore-ver="' + v.id + '"><i class="bi bi-arrow-counterclockwise"></i></button>';
                h += '</div>';
            });
        }
        document.getElementById('versionsBody').innerHTML = h;

        // Bind restore version clicks
        document.getElementById('versionsBody').querySelectorAll('[data-restore-ver]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const vr = await adminApiPost('admin_restore_document_version',{version_id:btn.dataset.restoreVer});
                if (vr.success) { showToast(vr.message,'success'); bootstrap.Modal.getInstance(modal)?.hide(); loadDocs(); }
                else showToast(vr.message||'Erreur','error');
            });
        });
    }

    // ── Access ──
    async function openAccess(docId) {
        currentAccessDocId = docId;
        const r = await adminApiPost('admin_get_document_access',{document_id:docId});
        const rules = r.rules||[];
        document.getElementById('accessRoles').innerHTML = ['collaborateur','responsable'].map(role => {
            const blocked = rules.some(ru => ru.role===role && ru.acces==='bloque');
            return '<div class="form-check form-switch"><input class="form-check-input ac-role" type="checkbox" value="'+role+'" '+(blocked?'checked':'')+'><label class="form-check-label small">'+(role==='collaborateur'?'Collaborateurs':'Responsables')+'</label></div>';
        }).join('');
        document.getElementById('accessSvcs').innerHTML = services.filter(s=>s.actif!=0).map(s => {
            const blocked = rules.some(ru => ru.service_id===s.id && ru.acces==='bloque');
            return '<div class="form-check form-switch"><input class="form-check-input ac-svc" type="checkbox" value="'+s.id+'" '+(blocked?'checked':'')+'><label class="form-check-label small"><i class="bi bi-'+escapeHtml(s.icone)+'" style="color:'+escapeHtml(s.couleur)+'"></i> '+escapeHtml(s.nom)+'</label></div>';
        }).join('');
        new bootstrap.Modal(document.getElementById('accessModal')).show();
    }
    document.getElementById('saveAccess')?.addEventListener('click', async () => {
        const rules=[];
        document.querySelectorAll('.ac-role:checked').forEach(c => rules.push({role:c.value,acces:'bloque'}));
        document.querySelectorAll('.ac-svc:checked').forEach(c => rules.push({service_id:c.value,acces:'bloque'}));
        const r = await adminApiPost('admin_set_document_access',{document_id:currentAccessDocId,rules});
        if (r.success) { showToast('Accès mis à jour','success'); bootstrap.Modal.getInstance(document.getElementById('accessModal'))?.hide(); loadDocs(); }
    });

    // ── Services management ──
    document.getElementById('btnManageServices')?.addEventListener('click', () => { renderSvcList(); new bootstrap.Modal(document.getElementById('svcModal')).show(); });
    function renderSvcList() {
        const c = document.getElementById('svcList');
        if (!services.length) { c.innerHTML='<p class="text-muted small">Aucun service</p>'; return; }
        c.innerHTML = services.map(s => '<div class="doc-svc-row">'
            + '<span class="doc-color-dot" style="background:'+escapeHtml(s.couleur)+'"></span>'
            + '<i class="bi bi-'+escapeHtml(s.icone)+'" style="color:'+escapeHtml(s.couleur)+'"></i>'
            + '<span class="small fw-semibold" style="flex:1">'+escapeHtml(s.nom)+'</span>'
            + '<span class="text-muted small">'+(s.doc_count||0)+' docs</span>'
            + '<button class="doc-row-btn" data-toggle-svc="'+s.id+'" data-actif="'+(s.actif==1?0:1)+'" title="'+(s.actif==1?'Désactiver':'Activer')+'"><i class="bi bi-'+(s.actif==1?'eye-slash':'eye')+'"></i></button>'
            + '</div>').join('');
        c.querySelectorAll('[data-toggle-svc]').forEach(b => b.addEventListener('click', async () => {
            await adminApiPost('admin_update_service',{id:b.dataset.toggleSvc,actif:parseInt(b.dataset.actif)});
            await loadSvcs(); renderSvcList();
        }));
    }
    document.getElementById('addSvcBtn')?.addEventListener('click', async () => {
        const nom = document.getElementById('newSvcName')?.value.trim();
        if (!nom) { showToast('Nom requis','error'); return; }
        const r = await adminApiPost('admin_create_service',{nom,couleur:document.getElementById('newSvcColor')?.value||'#6B5B3E'});
        if (r.success) { showToast('Service créé','success'); document.getElementById('newSvcName').value=''; await loadSvcs(); renderSvcList(); }
    });

    // ── Init ──
    renderSvcCards();
    initSelects();
    loadDocs();
})();
</script>
