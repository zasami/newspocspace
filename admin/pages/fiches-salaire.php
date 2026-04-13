<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$fsInitYear = (int) date('Y');
$fsFiches = Db::fetchAll(
    "SELECT fs.id, fs.user_id, fs.annee, fs.mois, fs.original_name, fs.size, fs.created_at,
            u.prenom, u.nom, u.employee_id,
            f.code AS fonction_code,
            m.nom AS module_nom, m.code AS module_code,
            up.prenom AS uploaded_by_prenom, up.nom AS uploaded_by_nom
     FROM fiches_salaire fs
     JOIN users u ON u.id = fs.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     LEFT JOIN users up ON up.id = fs.uploaded_by
     WHERE fs.annee = ?
     ORDER BY fs.annee DESC, fs.mois DESC, u.nom, u.prenom",
    [$fsInitYear]
);
$fsUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.employee_id, f.code AS fonction_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1
     ORDER BY u.nom, u.prenom",
    []
);
?>
<!-- Title + buttons row -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0"><i class="bi bi-receipt"></i> Fiches de salaire</h5>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-primary" id="fsBulkUploadBtn">
      <i class="bi bi-cloud-arrow-up"></i> Import en lot
    </button>
    <button class="btn btn-sm btn-success" id="fsUploadBtn">
      <i class="bi bi-plus-lg"></i> Ajouter une fiche
    </button>
  </div>
</div>
<!-- Filters row -->
<div class="d-flex gap-2 align-items-center flex-wrap mb-3">
  <div class="zs-select fs-filter-auto" id="fsUserFilter" data-placeholder="Tous les collaborateurs"></div>
  <div class="zs-select fs-filter-auto" id="fsMoisFilter" data-placeholder="Tous les mois"></div>
  <div class="d-flex align-items-center gap-1">
    <button class="btn btn-sm btn-outline-secondary" id="fsPrevYear"><i class="bi bi-chevron-left"></i></button>
    <span class="fw-bold" id="fsYear"></span>
    <button class="btn btn-sm btn-outline-secondary" id="fsNextYear"><i class="bi bi-chevron-right"></i></button>
  </div>
</div>

<!-- Stats cards -->
<div class="row g-2 mb-3" id="fsStats">
  <div class="col-6 col-md-3">
    <div class="card text-center p-2">
      <div class="text-muted small">Fiches ce mois</div>
      <div class="fw-bold fs-5" id="fsStatMonth">-</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-2">
      <div class="text-muted small">Total année</div>
      <div class="fw-bold fs-5" id="fsStatYear">-</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-2">
      <div class="text-muted small">Collaborateurs couverts</div>
      <div class="fw-bold fs-5" id="fsStatUsers">-</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-2">
      <div class="text-muted small">Collaborateurs sans fiche</div>
      <div class="fw-bold fs-5 text-warning" id="fsStatMissing">-</div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collaborateur</th>
          <th>Fonction</th>
          <th>Période</th>
          <th>Fichier</th>
          <th>Taille</th>
          <th>Ajouté le</th>
          <th>Par</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="fsTableBody">
        <tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Upload modal (single) -->
<div class="modal fade" id="fsUploadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-receipt"></i> Ajouter une fiche de salaire</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: File first -->
        <div class="mb-3">
          <label class="form-label">Fichier PDF</label>
          <div class="fs-dropzone" id="fsDropzone">
            <input type="file" id="fsUploadFile" accept="application/pdf" class="d-none">
            <i class="bi bi-file-earmark-pdf fs-pdf-icon"></i>
            <p class="mb-1 fw-semibold">Glissez votre fichier PDF ici</p>
            <p class="text-muted small mb-0">ou <span class="text-primary fs-browse-link">parcourir</span></p>
            <div id="fsDropzoneFile" class="d-none mt-2 small text-success fw-semibold"></div>
          </div>
        </div>

        <!-- Detection banner -->
        <div class="fs-detect-banner" id="fsDetectBanner" style="display:none">
          <div class="fs-detect-header"><i class="bi bi-magic"></i> Détection automatique depuis le nom du fichier</div>
          <div class="fs-detect-grid" id="fsDetectGrid"></div>
        </div>

        <!-- Fields (hidden by default, shown on click) -->
        <div id="fsManualFieldsToggle" style="display:none;margin-bottom:10px">
          <button type="button" class="fs-manual-toggle" id="fsShowManualBtn">
            <i class="bi bi-pencil-square"></i> En cas d'erreur de détection, cliquez ici pour corriger manuellement
          </button>
        </div>
        <div id="fsManualFields" style="display:none">
          <div class="mb-3">
            <label class="form-label">Collaborateur</label>
            <div class="zs-select" id="fsUploadUser" data-placeholder="-- Sélectionner --"></div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Année</label>
              <input type="number" class="form-control" id="fsUploadAnnee" min="2000" max="2100">
            </div>
            <div class="col-6">
              <label class="form-label">Mois</label>
              <div class="zs-select" id="fsUploadMois" data-placeholder="Mois"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-success" id="fsUploadSubmit"><i class="bi bi-upload"></i> Téléverser</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk upload modal  -->
<div class="modal fade" id="fsBulkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-arrow-up"></i> Import en lot</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small mb-3">
          <i class="bi bi-info-circle"></i>
          Nommez les fichiers <strong>NOM_Prenom.pdf</strong> ou <strong>ID_employé.pdf</strong> pour une détection automatique.
          Tous les fichiers seront associés à l'année/mois sélectionnés.
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Année</label>
            <input type="number" class="form-control" id="fsBulkAnnee" min="2000" max="2100">
          </div>
          <div class="col-6">
            <label class="form-label">Mois</label>
            <div class="zs-select" id="fsBulkMois" data-placeholder="Mois"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Fichiers PDF</label>
          <input type="file" class="form-control" id="fsBulkFiles" accept="application/pdf" multiple>
          <div class="form-text" id="fsBulkCount"></div>
        </div>

        <!-- Progress area (hidden until import starts) -->
        <div id="fsBulkProgress" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold" id="fsBulkProgressLabel">Importation en cours...</span>
            <span class="small text-muted" id="fsBulkProgressCounter">0 / 0</span>
          </div>
          <div class="progress mb-2" style="height:8px;border-radius:4px">
            <div class="progress-bar bg-success" id="fsBulkProgressBar" style="width:0%;transition:width .3s"></div>
          </div>
          <div class="fs-bulk-current" id="fsBulkCurrentFile"></div>
          <div class="fs-bulk-log" id="fsBulkLog" style="max-height:200px;overflow-y:auto"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" id="fsBulkCloseBtn">Fermer</button>
        <button class="btn btn-primary" id="fsBulkSubmit"><i class="bi bi-upload"></i> Importer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
const MOIS_NOMS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const MOIS_OPTIONS = MOIS_NOMS.slice(1).map((m, i) => ({ value: String(i + 1), label: m }));
let fsYear = <?= json_encode($fsInitYear) ?>;
let fsUsers = <?= json_encode(array_values($fsUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
let fsFiches = <?= json_encode(array_values($fsFiches), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

function initFichesSalairePage() {
    document.getElementById('fsYear').textContent = fsYear;

    // Init month filter selects
    zerdaSelect.init('#fsMoisFilter', MOIS_OPTIONS, { value: '', onSelect: renderTable });
    zerdaSelect.init('#fsUploadMois', MOIS_OPTIONS, { value: String(new Date().getMonth() + 1) });
    zerdaSelect.init('#fsBulkMois', MOIS_OPTIONS, { value: String(new Date().getMonth() + 1) });

    // Event delegation for table action buttons
    document.getElementById('fsTableBody')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        if (btn.dataset.action === 'view') viewFiche(id);
        else if (btn.dataset.action === 'delete') deleteFiche(id);
    });

    document.getElementById('fsPrevYear')?.addEventListener('click', () => { fsYear--; document.getElementById('fsYear').textContent = fsYear; loadFiches(); });
    document.getElementById('fsNextYear')?.addEventListener('click', () => { fsYear++; document.getElementById('fsYear').textContent = fsYear; loadFiches(); });

    document.getElementById('fsUploadBtn')?.addEventListener('click', () => {
        document.getElementById('fsUploadAnnee').value = fsYear;
        zerdaSelect.setValue('#fsUploadMois', String(new Date().getMonth() + 1));
        zerdaSelect.setValue('#fsUploadUser', '');
        document.getElementById('fsUploadFile').value = '';
        document.getElementById('fsDropzoneFile').classList.add('d-none');
        document.getElementById('fsDetectBanner').style.display = 'none';
        document.getElementById('fsManualFieldsToggle').style.display = 'none';
        document.getElementById('fsManualFields').style.display = '';
        new bootstrap.Modal('#fsUploadModal').show();
    });

    // Dropzone for single upload
    const dropzone = document.getElementById('fsDropzone');
    const fileInput = document.getElementById('fsUploadFile');
    if (dropzone) {
        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('fs-dropzone-active'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('fs-dropzone-active'));
        dropzone.addEventListener('drop', e => {
            e.preventDefault();
            dropzone.classList.remove('fs-dropzone-active');
            const files = e.dataTransfer.files;
            if (files.length && files[0].type === 'application/pdf') {
                fileInput.files = files;
                showDropzoneFile(files[0].name);
            } else {
                showToast('Seuls les fichiers PDF sont acceptés', 'error');
            }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) showDropzoneFile(fileInput.files[0].name);
        });
    }
    function showDropzoneFile(name) {
        const el = document.getElementById('fsDropzoneFile');
        el.textContent = '✓ ' + name;
        el.classList.remove('d-none');
        detectFromFilename(name);
    }

    /**
     * Parse filename patterns:
     *   NOM_Prenom_YYYY_M_V1.pdf
     *   NOM_Prenom_YYYY_MM.pdf
     *   NOM_Prenom_YYYYMM.pdf
     *   NOM-Prenom-YYYY-MM.pdf
     *   NOM Prenom YYYY MM.pdf
     *   NOM_Prenom.pdf (no date)
     */
    function detectFromFilename(filename) {
        const banner = document.getElementById('fsDetectBanner');
        const grid = document.getElementById('fsDetectGrid');
        const base = filename.replace(/\.pdf$/i, '').replace(/[_\s]+V\d+$/i, '');

        // Split by _ - or spaces
        const parts = base.split(/[_\-\s]+/).filter(Boolean);

        let detectedNom = null, detectedPrenom = null, detectedAnnee = null, detectedMois = null;
        let matchedUser = null;

        // Try to find year (4 digits 20xx) and month
        const remaining = [];
        for (const p of parts) {
            const n = parseInt(p);
            if (/^\d{6}$/.test(p)) {
                // YYYYMM format
                detectedAnnee = parseInt(p.substring(0, 4));
                detectedMois = parseInt(p.substring(4, 6));
            } else if (/^\d{4}$/.test(p) && n >= 2000 && n <= 2100) {
                detectedAnnee = n;
            } else if (/^\d{1,2}$/.test(p) && n >= 1 && n <= 12 && detectedAnnee) {
                detectedMois = n;
            } else {
                remaining.push(p);
            }
        }

        // First remaining = nom, second = prenom
        if (remaining.length >= 2) {
            detectedNom = remaining[0];
            detectedPrenom = remaining[1];
        } else if (remaining.length === 1) {
            detectedNom = remaining[0];
        }

        // Try to match user
        if (detectedNom) {
            const nomLower = detectedNom.toLowerCase();
            const prenomLower = (detectedPrenom || '').toLowerCase();
            matchedUser = fsUsers.find(u => {
                const uNom = u.nom.toLowerCase();
                const uPrenom = u.prenom.toLowerCase();
                // Exact match nom + prenom
                if (uNom === nomLower && prenomLower && uPrenom === prenomLower) return true;
                // Match by employee_id
                if (u.employee_id && u.employee_id.toLowerCase() === nomLower) return true;
                // Partial match (nom contains)
                if (prenomLower && uNom.includes(nomLower) && uPrenom.includes(prenomLower)) return true;
                return false;
            });
            // Fallback: fuzzy match nom only
            if (!matchedUser && nomLower.length >= 3) {
                matchedUser = fsUsers.find(u => u.nom.toLowerCase() === nomLower);
            }
        }

        // Render banner
        const items = [];
        if (detectedNom || detectedPrenom) {
            const cls = matchedUser ? 'fs-detect-ok' : 'fs-detect-warn';
            const val = matchedUser
                ? `${esc(matchedUser.nom)} ${esc(matchedUser.prenom)}`
                : `${esc(detectedNom || '?')} ${esc(detectedPrenom || '?')} <span style="color:#f57f17;font-size:.7rem">(non trouvé)</span>`;
            items.push(`<div class="fs-detect-item ${cls}"><span class="fs-detect-label">Collaborateur</span><span class="fs-detect-value">${val}</span></div>`);
        }
        if (detectedAnnee) {
            items.push(`<div class="fs-detect-item fs-detect-ok"><span class="fs-detect-label">Année</span><span class="fs-detect-value">${detectedAnnee}</span></div>`);
        }
        if (detectedMois) {
            items.push(`<div class="fs-detect-item fs-detect-ok"><span class="fs-detect-label">Mois</span><span class="fs-detect-value">${MOIS_NOMS[detectedMois] || detectedMois}</span></div>`);
        }

        // Auto-fill form fields
        if (matchedUser) zerdaSelect.setValue('#fsUploadUser', matchedUser.id);
        if (detectedAnnee) document.getElementById('fsUploadAnnee').value = detectedAnnee;
        if (detectedMois) zerdaSelect.setValue('#fsUploadMois', String(detectedMois));

        const allDetected = matchedUser && detectedAnnee && detectedMois;
        if (items.length) {
            grid.innerHTML = items.join('');
            banner.style.display = '';
        } else {
            banner.style.display = 'none';
        }

        // If all detected → hide manual fields, show toggle link
        if (allDetected) {
            document.getElementById('fsManualFields').style.display = 'none';
            document.getElementById('fsManualFieldsToggle').style.display = '';
        } else {
            // Partial detection or none → show fields
            document.getElementById('fsManualFields').style.display = '';
            document.getElementById('fsManualFieldsToggle').style.display = 'none';
        }
    }

    document.getElementById('fsBulkUploadBtn')?.addEventListener('click', () => {
        document.getElementById('fsBulkAnnee').value = fsYear;
        zerdaSelect.setValue('#fsBulkMois', String(new Date().getMonth() + 1));
        document.getElementById('fsBulkFiles').value = '';
        document.getElementById('fsBulkCount').textContent = '';
        document.getElementById('fsBulkProgress').style.display = 'none';
        document.getElementById('fsBulkLog').innerHTML = '';
        document.getElementById('fsBulkSubmit').style.display = '';
        new bootstrap.Modal('#fsBulkModal').show();
    });

    document.getElementById('fsBulkFiles')?.addEventListener('change', e => {
        const n = e.target.files.length;
        document.getElementById('fsBulkCount').textContent = n ? `${n} fichier(s) sélectionné(s)` : '';
    });

    document.getElementById('fsUploadSubmit')?.addEventListener('click', uploadSingle);
    document.getElementById('fsBulkSubmit')?.addEventListener('click', uploadBulk);

    document.getElementById('fsShowManualBtn')?.addEventListener('click', () => {
        document.getElementById('fsManualFields').style.display = '';
        document.getElementById('fsManualFieldsToggle').style.display = 'none';
    });

    // Init filters from injected data
    const userOpts = fsUsers.map(u => ({
        value: u.id,
        label: `${u.nom} ${u.prenom}${u.fonction_code ? ' (' + u.fonction_code + ')' : ''}`
    }));
    zerdaSelect.init('#fsUserFilter', userOpts, { value: '', search: true, onSelect: renderTable });
    const uploadUserOpts = fsUsers.map(u => ({ value: u.id, label: `${u.nom} ${u.prenom}` }));
    zerdaSelect.init('#fsUploadUser', uploadUserOpts, { value: '', search: true });
    renderTable();
    updateStats();
}

async function loadFiches() {
    document.getElementById('fsTableBody').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</td></tr>';
    const res = await adminApiPost('admin_get_fiches_salaire', { annee: fsYear });
    if (!res.success) {
        document.getElementById('fsTableBody').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Erreur de chargement</td></tr>';
        return;
    }

    fsFiches = res.fiches || [];
    fsUsers = res.users || [];

    // Populate user filter
    const curVal = zerdaSelect.getValue('#fsUserFilter');
    const userOpts = fsUsers.map(u => ({
        value: u.id,
        label: `${u.nom} ${u.prenom}${u.fonction_code ? ' (' + u.fonction_code + ')' : ''}`
    }));
    zerdaSelect.destroy('#fsUserFilter');
    zerdaSelect.init('#fsUserFilter', userOpts, { value: curVal, search: true, onSelect: renderTable });

    // Populate modal user select
    const uploadUserOpts = fsUsers.map(u => ({ value: u.id, label: `${u.nom} ${u.prenom}` }));
    zerdaSelect.destroy('#fsUploadUser');
    zerdaSelect.init('#fsUploadUser', uploadUserOpts, { value: '', search: true });

    renderTable();
    updateStats();
}

function renderTable() {
    const tbody = document.getElementById('fsTableBody');
    const filterUser = zerdaSelect.getValue('#fsUserFilter');
    const filterMois = zerdaSelect.getValue('#fsMoisFilter');

    let filtered = fsFiches;
    if (filterUser) filtered = filtered.filter(f => f.user_id === filterUser);
    if (filterMois) filtered = filtered.filter(f => f.mois == filterMois);

    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Aucune fiche de salaire</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(f => `
        <tr>
            <td><strong>${esc(f.nom)} ${esc(f.prenom)}</strong> ${f.employee_id ? '<small class="text-muted">' + esc(f.employee_id) + '</small>' : ''}</td>
            <td>${f.fonction_code ? '<span class="badge bg-secondary">' + esc(f.fonction_code) + '</span>' : '-'}</td>
            <td>${MOIS_NOMS[f.mois] || f.mois} ${f.annee}</td>
            <td><i class="bi bi-file-pdf text-danger"></i> ${esc(f.original_name)}</td>
            <td>${formatSize(f.size)}</td>
            <td>${formatDate(f.created_at)}</td>
            <td>${f.uploaded_by_prenom ? esc(f.uploaded_by_prenom) + ' ' + esc(f.uploaded_by_nom) : '-'}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" data-action="view" data-id="${f.id}" title="Voir"><i class="bi bi-eye"></i></button>
                <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${f.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function updateStats() {
    const now = new Date();
    const curMonth = now.getMonth() + 1;
    const curYear = now.getFullYear();

    const thisMonth = fsFiches.filter(f => f.annee == curYear && f.mois == curMonth).length;
    const totalYear = fsFiches.length;
    const coveredUsers = new Set(fsFiches.map(f => f.user_id)).size;
    const missing = Math.max(0, fsUsers.length - coveredUsers);

    document.getElementById('fsStatMonth').textContent = thisMonth;
    document.getElementById('fsStatYear').textContent = totalYear;
    document.getElementById('fsStatUsers').textContent = coveredUsers;
    document.getElementById('fsStatMissing').textContent = missing;
}

async function uploadSingle() {
    const userId = zerdaSelect.getValue('#fsUploadUser');
    const annee = document.getElementById('fsUploadAnnee').value;
    const mois = zerdaSelect.getValue('#fsUploadMois');
    const file = document.getElementById('fsUploadFile').files[0];

    if (!userId || !file) { alert('Veuillez remplir tous les champs'); return; }

    const fd = new FormData();
    fd.append('action', 'admin_upload_fiche_salaire');
    fd.append('user_id', userId);
    fd.append('annee', annee);
    fd.append('mois', mois);
    fd.append('file', file);

    const btn = document.getElementById('fsUploadSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Upload...';

    try {
        const resp = await fetch('/spocspace/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
            body: fd,
        });
        const res = await resp.json();
        if (res.success) {
            bootstrap.Modal.getInstance('#fsUploadModal')?.hide();
            showToast(res.message, 'success');
            await loadFiches();
        } else {
            alert(res.message || res.error || 'Erreur');
        }
    } catch (e) {
        alert('Erreur réseau');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Téléverser';
    }
}

async function uploadBulk() {
    const annee = document.getElementById('fsBulkAnnee').value;
    const mois = zerdaSelect.getValue('#fsBulkMois');
    const fileList = document.getElementById('fsBulkFiles').files;

    if (!fileList.length) { alert('Sélectionnez des fichiers'); return; }
    if (!annee || !mois) { alert('Sélectionnez année et mois'); return; }

    const files = Array.from(fileList);
    const total = files.length;
    let uploaded = 0, skipped = 0, errors = 0;

    const btn = document.getElementById('fsBulkSubmit');
    const closeBtn = document.getElementById('fsBulkCloseBtn');
    btn.disabled = true;
    btn.style.display = 'none';
    closeBtn.disabled = true;

    const progressArea = document.getElementById('fsBulkProgress');
    const progressBar = document.getElementById('fsBulkProgressBar');
    const progressLabel = document.getElementById('fsBulkProgressLabel');
    const progressCounter = document.getElementById('fsBulkProgressCounter');
    const currentFile = document.getElementById('fsBulkCurrentFile');
    const log = document.getElementById('fsBulkLog');

    progressArea.style.display = '';
    progressBar.style.width = '0%';
    progressCounter.textContent = `0 / ${total}`;
    log.innerHTML = '';

    /**
     * Detect user from filename (reuse same logic as single upload)
     */
    function detectUserFromName(filename) {
        const base = filename.replace(/\.pdf$/i, '').replace(/[_\s]+V\d+$/i, '');
        const parts = base.split(/[_\-\s]+/).filter(Boolean);
        const remaining = [];
        for (const p of parts) {
            const n = parseInt(p);
            if (/^\d{4,6}$/.test(p) || (/^\d{1,2}$/.test(p) && n >= 1 && n <= 12)) continue;
            remaining.push(p);
        }
        if (remaining.length < 1) return null;
        const nomLower = remaining[0].toLowerCase();
        const prenomLower = (remaining[1] || '').toLowerCase();
        return fsUsers.find(u => {
            const uN = u.nom.toLowerCase(), uP = u.prenom.toLowerCase();
            if (uN === nomLower && prenomLower && uP === prenomLower) return true;
            if (u.employee_id && u.employee_id.toLowerCase() === nomLower) return true;
            if (!prenomLower && nomLower.length >= 3 && uN === nomLower) return true;
            return false;
        });
    }

    for (let i = 0; i < total; i++) {
        const file = files[i];
        const pct = Math.round(((i) / total) * 100);
        progressBar.style.width = pct + '%';
        progressCounter.textContent = `${i + 1} / ${total}`;
        progressLabel.textContent = `Importation en cours... (${i + 1}/${total})`;

        // Show current file
        currentFile.innerHTML = `<span class="spinner-border"></span><i class="bi bi-file-earmark-pdf"></i><span class="fs-bc-name">${esc(file.name)}</span>`;

        const matchedUser = detectUserFromName(file.name);

        if (!matchedUser) {
            skipped++;
            log.innerHTML += `<div class="fs-bulk-log-item log-skip"><i class="bi bi-exclamation-triangle"></i><span>${esc(file.name)}</span><small>Collaborateur non trouvé</small></div>`;
            log.scrollTop = log.scrollHeight;
            continue;
        }

        // Upload one file via existing single upload API
        const fd = new FormData();
        fd.append('action', 'admin_upload_fiche_salaire');
        fd.append('user_id', matchedUser.id);
        fd.append('annee', annee);
        fd.append('mois', mois);
        fd.append('file', file);

        try {
            const resp = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: fd,
            });
            const res = await resp.json();
            if (res.success) {
                uploaded++;
                log.innerHTML += `<div class="fs-bulk-log-item log-ok"><i class="bi bi-check-circle-fill"></i><span>${esc(file.name)}</span><small>${esc(matchedUser.nom)} ${esc(matchedUser.prenom)}</small></div>`;
            } else {
                skipped++;
                log.innerHTML += `<div class="fs-bulk-log-item log-skip"><i class="bi bi-dash-circle"></i><span>${esc(file.name)}</span><small>${esc(res.message || 'Erreur')}</small></div>`;
            }
        } catch (e) {
            errors++;
            log.innerHTML += `<div class="fs-bulk-log-item log-err"><i class="bi bi-x-circle-fill"></i><span>${esc(file.name)}</span><small>Erreur réseau</small></div>`;
        }
        log.scrollTop = log.scrollHeight;
    }

    // Done
    progressBar.style.width = '100%';
    currentFile.innerHTML = '';
    progressLabel.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> Terminé : <strong>${uploaded}</strong> importé${uploaded > 1 ? 's' : ''}` +
        (skipped ? `, <strong>${skipped}</strong> ignoré${skipped > 1 ? 's' : ''}` : '') +
        (errors ? `, <strong class="text-danger">${errors}</strong> erreur${errors > 1 ? 's' : ''}` : '');

    btn.disabled = false;
    btn.style.display = '';
    btn.innerHTML = '<i class="bi bi-upload"></i> Importer';
    closeBtn.disabled = false;
    await loadFiches();
}

function viewFiche(id) {
    window.open(`/spocspace/admin/api.php?action=admin_serve_fiche_salaire&id=${id}`, '_blank');
}

async function deleteFiche(id) {
    if (!confirm('Supprimer cette fiche de salaire ?')) return;
    const res = await adminApiPost('admin_delete_fiche_salaire', { id });
    if (res.success) {
        showToast(res.message, 'success');
        await loadFiches();
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1048576).toFixed(1) + ' Mo';
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('fr-CH');
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

initFichesSalairePage();
</script>
<style>
.fs-dropzone{border:2px dashed #ccc;border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#fafafa}
.fs-dropzone:hover,.fs-dropzone-active{border-color:#0d6efd;background:rgba(13,110,253,.04)}
.fs-filter-auto{width:auto}
.fs-pdf-icon{font-size:2.5rem;color:#c62828}
.fs-browse-link{cursor:pointer;text-decoration:underline}
.fs-detect-banner{background:#f0faf0;border:1px solid #c8e6c9;border-radius:10px;padding:12px;margin-bottom:14px;animation:fsDetectIn .3s ease}
@keyframes fsDetectIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.fs-detect-header{font-size:.82rem;font-weight:600;color:#2e7d32;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.fs-detect-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.fs-detect-item{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:6px 10px;font-size:.8rem}
.fs-detect-item .fs-detect-label{font-size:.68rem;text-transform:uppercase;color:#999;letter-spacing:.3px;display:block}
.fs-detect-item .fs-detect-value{font-weight:600;color:#212529}
.fs-detect-item.fs-detect-ok{border-color:#a5d6a7;background:#f1f8e9}
.fs-detect-item.fs-detect-warn{border-color:#ffe082;background:#fffde7}
.fs-manual-toggle{background:none;border:none;color:#999;font-size:.78rem;cursor:pointer;padding:0;display:flex;align-items:center;gap:5px;transition:color .15s}
.fs-manual-toggle:hover{color:#2e7d32;text-decoration:underline}
.fs-bulk-current{background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:8px 12px;margin-bottom:8px;font-size:.82rem;display:flex;align-items:center;gap:8px;min-height:38px}
.fs-bulk-current i{color:#c62828;font-size:1.1rem}
.fs-bulk-current .fs-bc-name{flex:1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fs-bulk-current .spinner-border{width:16px;height:16px;border-width:2px;color:#2d4a43}
.fs-bulk-log{display:flex;flex-direction:column;gap:3px}
.fs-bulk-log-item{display:flex;align-items:center;gap:6px;padding:3px 8px;border-radius:4px;font-size:.78rem}
.fs-bulk-log-item.log-ok{background:#f1f8e9;color:#2e7d32}
.fs-bulk-log-item.log-skip{background:#fff8e1;color:#e65100}
.fs-bulk-log-item.log-err{background:#fde2e0;color:#c62828}
.fs-bulk-log-item .bi{font-size:.85rem;flex-shrink:0}
.fs-bulk-log-item span{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>
