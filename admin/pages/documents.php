<style>
/* ─── Documents Admin ─── */
.doc-toolbar { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1.5rem; }
.doc-toolbar-filter { width:auto; min-width:180px; }

.doc-services-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem; }
.doc-service-card {
    border:1px solid #e2e0d5; border-radius:1rem; padding:1.5rem; cursor:pointer;
    transition:all .2s; background:#fff; position:relative; overflow:hidden;
    display:flex; flex-direction:column; min-height:160px;
}
.doc-services-grid.has-active .doc-service-card:not(.active) { background:var(--cl-bg, #F7F5F2); }
.doc-services-grid:hover .doc-service-card { background:var(--cl-bg, #F7F5F2); }
.doc-services-grid:hover .doc-service-card:hover { background:#fff; }
.doc-service-card.active { background:#fff; border-color:#c5c3b8; }
.doc-service-card .service-icon {
    width:48px; height:48px; border-radius:.5rem; display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; margin-bottom:.75rem; background:#e8e6dc; color:#d6d4cb;
}
.doc-service-card .service-name { font-weight:600; font-size:1.05rem; color:#2c2c2c; line-height:1.35; margin-bottom:auto; }
.doc-service-card .service-count { font-size:.82rem; color:#6c757d; margin-top:1rem; }

.doc-list-table { width:100%; }
.doc-list-table th { font-size:.75rem; text-transform:uppercase; color:#6c757d; font-weight:600; padding:.5rem .75rem; border-bottom:2px solid #e2e8f0; }
.doc-list-table td { padding:.65rem .75rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.doc-list-table tr:hover { background:rgba(13,110,253,.02); }
.doc-list-table .col-actions { width:140px; }

.doc-file-icon { width:40px; height:40px; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.doc-file-icon.pdf { background:rgba(220,53,69,.1); color:#dc3545; }
.doc-file-icon.word { background:rgba(13,110,253,.1); color:#0d6efd; }
.doc-file-icon.excel { background:rgba(25,135,84,.1); color:#198754; }
.doc-file-icon.image { background:rgba(111,66,193,.1); color:#6f42c1; }
.doc-file-icon.ppt { background:rgba(253,126,20,.1); color:#fd7e14; }
.doc-file-icon.other { background:rgba(108,117,125,.1); color:#6c757d; }

.doc-title-cell { display:flex; align-items:center; gap:.75rem; }
.doc-title-cell .doc-info .doc-name { font-weight:500; font-size:.9rem; }
.doc-title-cell .doc-info .doc-desc { font-size:.78rem; color:#6c757d; max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.badge-service { font-size:.72rem; padding:.25rem .5rem; border-radius:.35rem; font-weight:500; }
.badge-visible { background:rgba(25,135,84,.1); color:#198754; }
.badge-hidden { background:rgba(220,53,69,.1); color:#dc3545; }
.badge-restricted { background:rgba(253,126,20,.1); color:#fd7e14; }

.doc-actions .btn { padding:.25rem .5rem; font-size:.8rem; }

.doc-file-size-label { font-size:.75rem; }

.doc-color-input { width:40px; padding:2px; }

.doc-color-swatch { width:16px; height:16px; border-radius:4px; display:inline-block; flex-shrink:0; }

/* Upload modal */
#uploadArea { border:2px dashed #dee2e6; border-radius:.75rem; padding:2rem; text-align:center; cursor:pointer; transition:all .2s; }
#uploadArea:hover, #uploadArea.dragover { border-color:var(--bs-primary); background:rgba(13,110,253,.03); }
#uploadArea .upload-icon { font-size:2.5rem; color:#adb5bd; margin-bottom:.5rem; }
#uploadArea p { color:#6c757d; margin:0; font-size:.9rem; }
#uploadFilePreview { margin-top:1rem; }

/* Access modal */
.access-rule-row { display:flex; align-items:center; gap:.5rem; padding:.5rem 0; border-bottom:1px solid #f1f5f9; }
.access-rule-row:last-child { border:none; }
</style>

<!-- Toolbar -->
<div class="doc-toolbar">
    <div class="zs-select doc-toolbar-filter" id="docServiceFilter" data-placeholder="Tous les services"></div>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#serviceModal">
        <i class="bi bi-gear"></i> Gérer services
    </button>
    <div class="ms-auto"></div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-cloud-upload"></i> Téléverser
    </button>
</div>

<!-- Service cards -->
<div class="doc-services-grid" id="servicesGrid"></div>

<!-- Documents table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0" id="docListTitle"><i class="bi bi-folder2-open"></i> Tous les documents</h6>
        <small class="text-muted" id="docCount"></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="doc-list-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Service</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="docTableBody">
                    <tr><td colspan="6" class="text-center py-4 text-muted">Chargement...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-upload text-primary"></i> Téléverser un document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="uploadTitre" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Service <span class="text-danger">*</span></label>
                        <div class="zs-select" id="uploadService" data-placeholder="Choisir un service..."></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="uploadDescription" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div id="uploadArea">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p><strong>Cliquez ou glissez</strong> un fichier ici</p>
                        <small class="text-muted">PDF, Word, Excel, PowerPoint, images — max 20 Mo</small>
                        <input type="file" id="uploadFile" class="d-none"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.svg,.txt,.csv">
                    </div>
                    <div id="uploadFilePreview" class="d-none">
                        <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                            <i class="bi bi-file-earmark fs-4 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small" id="uploadFileName"></div>
                                <div class="text-muted doc-file-size-label" id="uploadFileSize"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="uploadFileClear"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-sm btn-primary" id="uploadSubmitBtn" disabled>
                    <i class="bi bi-upload"></i> Téléverser
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Service Management Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear text-secondary"></i> Gérer les services</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="servicesList" class="mb-3"></div>
                <hr>
                <h6>Ajouter un service</h6>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="newServiceName" placeholder="Nom du service" maxlength="100">
                    <input type="color" class="form-control form-control-sm form-control-color doc-color-input" id="newServiceColor" value="#6c757d">
                    <button class="btn btn-sm btn-primary" id="addServiceBtn"><i class="bi bi-plus"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Access Control Modal -->
<div class="modal fade" id="accessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-lock text-warning"></i> Contrôle d'accès</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Définissez qui peut voir ce document. Par défaut, tous les collaborateurs y ont accès.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Bloquer l'accès par rôle</label>
                    <div id="accessRolesSection"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Bloquer l'accès par service</label>
                    <div id="accessServicesSection"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveAccessBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Modifier le document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editDocId">
                <div class="mb-3">
                    <label class="form-label">Titre</label>
                    <input type="text" class="form-control" id="editTitre" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Service</label>
                    <div class="zs-select" id="editService" data-placeholder="Service"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="editDescription" rows="2" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-sm btn-primary" id="editSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function(){
    let services = [];
    let currentFilter = '';
    let searchTimeout = null;
    let currentAccessDocId = null;

    // ═══ Helpers ═══
    function fileIcon(mime) {
        if (mime?.includes('pdf')) return { cls: 'pdf', icon: 'bi-file-earmark-pdf-fill' };
        if (mime?.includes('word') || mime?.includes('document')) return { cls: 'word', icon: 'bi-file-earmark-word-fill' };
        if (mime?.includes('excel') || mime?.includes('sheet') || mime?.includes('csv')) return { cls: 'excel', icon: 'bi-file-earmark-excel-fill' };
        if (mime?.includes('presentation') || mime?.includes('powerpoint')) return { cls: 'ppt', icon: 'bi-file-earmark-ppt-fill' };
        if (mime?.includes('image')) return { cls: 'image', icon: 'bi-file-earmark-image-fill' };
        return { cls: 'other', icon: 'bi-file-earmark-fill' };
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' Ko';
        return (bytes/(1024*1024)).toFixed(1) + ' Mo';
    }

    function formatDate(d) {
        return new Date(d).toLocaleDateString('fr-CH', { day:'numeric', month:'short', year:'numeric' });
    }

    // ═══ Load services ═══
    async function loadServices() {
        const res = await adminApiPost('admin_get_document_services');
        if (!res.success) return;
        services = res.services || [];
        renderServiceCards();
        populateServiceSelects();
    }

    function renderServiceCards() {
        const grid = document.getElementById('servicesGrid');
        if (!grid) return;

        // "All" card
        let html = `
            <div class="doc-service-card ${!currentFilter ? 'active' : ''}" data-service="">
                <div class="service-icon">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <div class="service-name">Tous les documents</div>
                <div class="service-count">${services.reduce((s,c) => s + parseInt(c.doc_count||0), 0)} documents</div>
            </div>`;

        services.filter(s => s.actif != 0).forEach(s => {
            html += `
                <div class="doc-service-card ${currentFilter === s.id ? 'active' : ''}" data-service="${escapeHtml(s.id)}">
                    <div class="service-icon">
                        <i class="bi bi-${escapeHtml(s.icone)}"></i>
                    </div>
                    <div class="service-name">${escapeHtml(s.nom)}</div>
                    <div class="service-count">${s.doc_count || 0} documents</div>
                </div>`;
        });

        grid.innerHTML = html;
        grid.classList.toggle('has-active', !!currentFilter);

        grid.querySelectorAll('.doc-service-card').forEach(card => {
            card.addEventListener('click', () => {
                currentFilter = card.dataset.service || '';
                zerdaSelect.setValue('#docServiceFilter', currentFilter);
                loadDocuments();
                renderServiceCards();
            });
        });
    }

    function populateServiceSelects() {
        const opts = services.filter(s => s.actif != 0).map(s => ({
            value: s.id, label: s.nom
        }));

        ['docServiceFilter', 'uploadService', 'editService'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            zerdaSelect.destroy(el);
            const placeholder = el.dataset.placeholder || '';
            const callbacks = {};
            if (id === 'docServiceFilter') {
                callbacks.onSelect = (val) => { currentFilter = val; renderServiceCards(); loadDocuments(); };
            } else if (id === 'uploadService') {
                callbacks.onSelect = () => checkUploadReady();
            }
            zerdaSelect.init(el, opts, { value: '', search: opts.length > 6, ...callbacks });
        });

        // Restore filter
        if (currentFilter) zerdaSelect.setValue('#docServiceFilter', currentFilter);
    }

    // ═══ Load documents ═══
    async function loadDocuments() {
        const search = document.getElementById('topbarSearchInput')?.value?.trim() || '';
        const res = await adminApiPost('admin_get_documents', { service_id: currentFilter, search });
        if (!res.success) return;

        const docs = res.documents || [];
        const tbody = document.getElementById('docTableBody');
        const title = document.getElementById('docListTitle');
        const count = document.getElementById('docCount');

        if (currentFilter) {
            const svc = services.find(s => s.id === currentFilter);
            if (svc) title.innerHTML = `<i class="bi bi-${escapeHtml(svc.icone)}" style="color:${escapeHtml(svc.couleur)}"></i> ${escapeHtml(svc.nom)}`;
        } else {
            title.innerHTML = '<i class="bi bi-folder2-open"></i> Tous les documents';
        }
        count.textContent = `${res.total || 0} document(s)`;

        if (!docs.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-folder2-open fs-2 d-block mb-2"></i>Aucun document</td></tr>';
            return;
        }

        tbody.innerHTML = docs.map(d => {
            const fi = fileIcon(d.mime_type);
            const statusBadge = d.visible == 1
                ? (d.restrictions > 0 ? '<span class="badge badge-restricted"><i class="bi bi-shield-exclamation"></i> Restreint</span>' : '<span class="badge badge-visible"><i class="bi bi-eye"></i> Visible</span>')
                : '<span class="badge badge-hidden"><i class="bi bi-eye-slash"></i> Masqu\u00e9</span>';

            return `<tr>
                <td>
                    <div class="doc-title-cell">
                        <div class="doc-file-icon ${fi.cls}"><i class="bi ${fi.icon}"></i></div>
                        <div class="doc-info">
                            <div class="doc-name">${escapeHtml(d.titre)}</div>
                            <div class="doc-desc">${escapeHtml(d.original_name)}${d.description ? ' \u2014 ' + escapeHtml(d.description) : ''}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge-service" style="background:${escapeHtml(d.service_couleur)}15;color:${escapeHtml(d.service_couleur)}">${escapeHtml(d.service_nom)}</span></td>
                <td class="text-muted small">${formatSize(d.size)}</td>
                <td class="text-muted small">${formatDate(d.created_at)}</td>
                <td>${statusBadge}</td>
                <td class="doc-actions">
                    <div class="d-flex gap-1">
                        <a href="/zerdatime/admin/api.php?action=admin_serve_document&id=${encodeURIComponent(d.id)}" target="_blank" class="btn btn-outline-primary" title="Voir"><i class="bi bi-eye"></i></a>
                        <button class="btn btn-outline-secondary" title="Modifier" data-action="edit" data-id="${escapeHtml(d.id)}" data-titre="${escapeHtml(d.titre)}" data-service-id="${escapeHtml(d.service_id)}" data-description="${escapeHtml(d.description||'')}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-warning" title="Acc\u00e8s" data-action="access" data-id="${escapeHtml(d.id)}"><i class="bi bi-shield-lock"></i></button>
                        <button class="btn btn-outline-${d.visible == 1 ? 'secondary' : 'success'}" title="${d.visible == 1 ? 'Masquer' : 'Rendre visible'}" data-action="toggle-vis" data-id="${escapeHtml(d.id)}"><i class="bi bi-${d.visible == 1 ? 'eye-slash' : 'eye'}"></i></button>
                        <button class="btn btn-outline-danger" title="Supprimer" data-action="delete" data-id="${escapeHtml(d.id)}" data-titre="${escapeHtml(d.titre)}"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ═══ Document table event delegation ═══
    document.getElementById('docTableBody')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;

        if (action === 'edit') {
            document.getElementById('editDocId').value = id;
            document.getElementById('editTitre').value = btn.dataset.titre;
            zerdaSelect.setValue('#editService', btn.dataset.serviceId);
            document.getElementById('editDescription').value = btn.dataset.description;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        } else if (action === 'access') {
            openAccess(id);
        } else if (action === 'toggle-vis') {
            toggleVis(id);
        } else if (action === 'delete') {
            deletDoc(id, btn.dataset.titre);
        }
    });

    // ═══ Search (via topbar input) ═══
    const topbarInput = document.getElementById('topbarSearchInput');
    if (topbarInput) {
        topbarInput.placeholder = 'Rechercher un document...';
        topbarInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadDocuments, 300);
        });
    }

    // docServiceFilter change is handled by onSelect in populateServiceSelects

    // ═══ File upload ═══
    const uploadArea = document.getElementById('uploadArea');
    const uploadFile = document.getElementById('uploadFile');
    const uploadPreview = document.getElementById('uploadFilePreview');

    uploadArea?.addEventListener('click', () => uploadFile?.click());
    uploadArea?.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea?.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
    uploadArea?.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            uploadFile.files = e.dataTransfer.files;
            showFilePreview(e.dataTransfer.files[0]);
        }
    });

    uploadFile?.addEventListener('change', () => {
        if (uploadFile.files.length) showFilePreview(uploadFile.files[0]);
    });

    function showFilePreview(file) {
        document.getElementById('uploadFileName').textContent = file.name;
        document.getElementById('uploadFileSize').textContent = formatSize(file.size);
        uploadArea?.classList.add('d-none');
        uploadPreview?.classList.remove('d-none');
        checkUploadReady();
    }

    document.getElementById('uploadFileClear')?.addEventListener('click', () => {
        uploadFile.value = '';
        uploadArea?.classList.remove('d-none');
        uploadPreview?.classList.add('d-none');
        checkUploadReady();
    });

    function checkUploadReady() {
        const btn = document.getElementById('uploadSubmitBtn');
        const ready = document.getElementById('uploadTitre')?.value.trim() &&
                      zerdaSelect.getValue('#uploadService') &&
                      uploadFile?.files?.length;
        if (btn) btn.disabled = !ready;
    }

    document.getElementById('uploadTitre')?.addEventListener('input', checkUploadReady);
    // uploadService change is handled by onSelect in populateServiceSelects

    document.getElementById('uploadSubmitBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('uploadSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Envoi...';

        const fd = new FormData();
        fd.append('action', 'admin_upload_document');
        fd.append('titre', document.getElementById('uploadTitre').value.trim());
        fd.append('description', document.getElementById('uploadDescription').value.trim());
        fd.append('service_id', zerdaSelect.getValue('#uploadService'));
        fd.append('file', uploadFile.files[0]);

        try {
            const res = await fetch('/zerdatime/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__ZT_ADMIN__?.csrfToken || '' },
                body: fd,
            });
            const json = await res.json();
            if (json.csrf) window.__ZT_ADMIN__.csrfToken = json.csrf;

            if (json.success) {
                toast('Document t\u00e9l\u00e9vers\u00e9 !', 'success');
                bootstrap.Modal.getInstance(document.getElementById('uploadModal'))?.hide();
                document.getElementById('uploadForm').reset();
                uploadArea?.classList.remove('d-none');
                uploadPreview?.classList.add('d-none');
                loadServices();
                loadDocuments();
            } else {
                toast(json.message || 'Erreur', 'error');
            }
        } catch (e) {
            toast('Erreur r\u00e9seau', 'error');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> T\u00e9l\u00e9verser';
    });

    // ═══ Edit ═══
    document.getElementById('editSaveBtn')?.addEventListener('click', async () => {
        const id = document.getElementById('editDocId').value;
        const res = await adminApiPost('admin_update_document', {
            id,
            titre: document.getElementById('editTitre').value.trim(),
            service_id: zerdaSelect.getValue('#editService'),
            description: document.getElementById('editDescription').value.trim(),
        });
        if (res.success) {
            toast('Document mis \u00e0 jour');
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            loadDocuments();
        } else {
            toast(res.message || 'Erreur', 'error');
        }
    });

    // ═══ Toggle visibility ═══
    async function toggleVis(id) {
        const res = await adminApiPost('admin_toggle_document_visibility', { id });
        if (res.success) {
            toast(res.message);
            loadDocuments();
        }
    }

    // ═══ Delete ═══
    async function deletDoc(id, titre) {
        const ok = await adminConfirm({
            title: 'Supprimer le document',
            text: `Voulez-vous vraiment supprimer <strong>${escapeHtml(titre)}</strong> ? Cette action est irr\u00e9versible.`,
            icon: 'bi-trash', type: 'danger', okText: 'Supprimer',
        });
        if (!ok) return;
        const res = await adminApiPost('admin_delete_document', { id });
        if (res.success) {
            toast('Document supprim\u00e9');
            loadServices();
            loadDocuments();
        }
    }

    // ═══ Access control ═══
    async function openAccess(docId) {
        currentAccessDocId = docId;
        const res = await adminApiPost('admin_get_document_access', { document_id: docId });
        const rules = res.rules || [];

        const roles = ['collaborateur', 'responsable'];
        const rolesHtml = roles.map(r => {
            const blocked = rules.some(ru => ru.role === r && ru.acces === 'bloque');
            return `<div class="form-check">
                <input class="form-check-input access-role-check" type="checkbox" value="${r}" id="accessRole_${r}" ${blocked ? 'checked' : ''}>
                <label class="form-check-label" for="accessRole_${r}">Bloquer : <strong>${r === 'collaborateur' ? 'Collaborateurs' : 'Responsables'}</strong></label>
            </div>`;
        }).join('');
        document.getElementById('accessRolesSection').innerHTML = rolesHtml;

        const servicesHtml = services.filter(s => s.actif != 0).map(s => {
            const blocked = rules.some(ru => ru.service_id === s.id && ru.acces === 'bloque');
            return `<div class="form-check">
                <input class="form-check-input access-service-check" type="checkbox" value="${s.id}" id="accessSvc_${s.id}" ${blocked ? 'checked' : ''}>
                <label class="form-check-label" for="accessSvc_${s.id}"><i class="bi bi-${escapeHtml(s.icone)}" style="color:${escapeHtml(s.couleur)}"></i> ${escapeHtml(s.nom)}</label>
            </div>`;
        }).join('');
        document.getElementById('accessServicesSection').innerHTML = servicesHtml;

        new bootstrap.Modal(document.getElementById('accessModal')).show();
    }

    document.getElementById('saveAccessBtn')?.addEventListener('click', async () => {
        const rules = [];
        document.querySelectorAll('.access-role-check:checked').forEach(cb => {
            rules.push({ role: cb.value, acces: 'bloque' });
        });
        document.querySelectorAll('.access-service-check:checked').forEach(cb => {
            rules.push({ service_id: cb.value, acces: 'bloque' });
        });

        const res = await adminApiPost('admin_set_document_access', { document_id: currentAccessDocId, rules });
        if (res.success) {
            toast('Acc\u00e8s mis \u00e0 jour');
            bootstrap.Modal.getInstance(document.getElementById('accessModal'))?.hide();
            loadDocuments();
        }
    });

    // ═══ Services management ═══
    function renderServicesList() {
        const container = document.getElementById('servicesList');
        if (!container) return;
        if (!services.length) { container.innerHTML = '<p class="text-muted">Aucun service</p>'; return; }

        container.innerHTML = services.map(s => `
            <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                <span class="doc-color-swatch" style="background:${escapeHtml(s.couleur)}"></span>
                <i class="bi bi-${escapeHtml(s.icone)}" style="color:${escapeHtml(s.couleur)}"></i>
                <span class="flex-grow-1 small fw-semibold">${escapeHtml(s.nom)}</span>
                <span class="text-muted small">${s.doc_count || 0} docs</span>
                <button class="btn btn-sm btn-outline-${s.actif == 1 ? 'warning' : 'success'}" title="${s.actif == 1 ? 'D\u00e9sactiver' : 'Activer'}"
                    data-action="toggle-service" data-id="${escapeHtml(s.id)}" data-actif="${s.actif == 1 ? 0 : 1}">
                    <i class="bi bi-${s.actif == 1 ? 'eye-slash' : 'eye'}"></i>
                </button>
            </div>
        `).join('');
    }

    // Services list event delegation
    document.getElementById('servicesList')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="toggle-service"]');
        if (!btn) return;
        toggleService(btn.dataset.id, parseInt(btn.dataset.actif));
    });

    // Watch for service modal show
    document.getElementById('serviceModal')?.addEventListener('show.bs.modal', renderServicesList);

    async function toggleService(id, actif) {
        const res = await adminApiPost('admin_update_service', { id, actif });
        if (res.success) {
            toast(res.message);
            await loadServices();
            renderServicesList();
        }
    }

    document.getElementById('addServiceBtn')?.addEventListener('click', async () => {
        const nom = document.getElementById('newServiceName')?.value.trim();
        const couleur = document.getElementById('newServiceColor')?.value || '#6c757d';
        if (!nom) return toast('Nom requis', 'error');

        const res = await adminApiPost('admin_create_service', { nom, couleur });
        if (res.success) {
            toast('Service cr\u00e9\u00e9');
            document.getElementById('newServiceName').value = '';
            await loadServices();
            renderServicesList();
        } else {
            toast(res.message || 'Erreur', 'error');
        }
    });

    // ═══ Init ═══
    loadServices().then(loadDocuments);
})();
</script>
