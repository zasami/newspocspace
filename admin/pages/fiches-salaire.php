<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$fsInitYear = (int) date('Y');
$fsFiches = Db::fetchAll(
    "SELECT fs.id, fs.user_id, fs.annee, fs.mois, fs.original_name, fs.size, fs.created_at,
            u.prenom, u.nom, u.employee_id, u.photo,
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
          <th>Période</th>
          <th>Fichier</th>
          <th>Taille</th>
          <th>Ajouté le</th>
          <th>Par</th>
          <th></th>
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
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-arrow-up"></i> Import en lot</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: Dropzone -->
        <div id="fsBulkStep1">
          <div class="fs-bulk-info">
            <i class="bi bi-magic"></i>
            <div>
              <strong>Détection automatique</strong> depuis le nom du fichier<br>
              <span class="text-muted">Format attendu : <code>NOM_Prenom_YYYY_M.pdf</code> ou <code>NOM_Prenom_YYYY_M_V1.pdf</code></span>
            </div>
          </div>
          <div class="fs-dropzone fs-bulk-dropzone" id="fsBulkDropzone">
            <input type="file" id="fsBulkFiles" accept="application/pdf" multiple class="d-none">
            <i class="bi bi-cloud-arrow-up fs-bulk-drop-icon"></i>
            <p class="mb-1 fw-semibold">Glissez vos fichiers PDF ici</p>
            <p class="text-muted small mb-0">ou <span class="text-primary fs-browse-link">parcourir</span></p>
          </div>
        </div>

        <!-- Step 2: Preview table (after files selected) -->
        <div id="fsBulkStep2" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold" id="fsBulkPreviewTitle"></span>
            <button class="btn btn-sm btn-outline-secondary" id="fsBulkReselect"><i class="bi bi-arrow-counterclockwise"></i> Changer les fichiers</button>
          </div>

          <!-- Stat cards -->
          <div class="fs-bulk-stats" id="fsBulkSummary"></div>

          <!-- Preview table -->
          <div class="fs-bulk-preview-wrap">
            <table class="table table-sm mb-0 fs-bulk-table">
              <thead>
                <tr>
                  <th style="width:28px"></th>
                  <th>Fichier</th>
                  <th>Collaborateur</th>
                  <th>Période</th>
                  <th style="width:60px">Statut</th>
                </tr>
              </thead>
              <tbody id="fsBulkPreviewBody"></tbody>
            </table>
          </div>
        </div>

        <!-- Step 3: Progress (during import) -->
        <div id="fsBulkStep3" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold" id="fsBulkProgressLabel">Importation en cours...</span>
            <span class="small text-muted" id="fsBulkProgressCounter">0 / 0</span>
          </div>
          <div class="progress mb-2" style="height:8px;border-radius:4px">
            <div class="progress-bar bg-success" id="fsBulkProgressBar" style="width:0%;transition:width .3s"></div>
          </div>
          <div class="fs-bulk-current" id="fsBulkCurrentFile"></div>
          <div class="fs-bulk-log" id="fsBulkLog" style="max-height:250px;overflow-y:auto"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" id="fsBulkCloseBtn">Fermer</button>
        <button class="btn btn-primary" id="fsBulkSubmit" style="display:none"><i class="bi bi-upload"></i> Importer <span id="fsBulkSubmitCount"></span></button>
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

        // Check duplicate
        let isDup = false;
        if (matchedUser && detectedAnnee && detectedMois) {
            const byPeriod = fsFiches.find(ex => ex.user_id === matchedUser.id && ex.annee == detectedAnnee && ex.mois == detectedMois);
            const byName = fsFiches.find(ex => ex.user_id === matchedUser.id && ex.original_name === filename);
            if (byPeriod || byName) {
                isDup = true;
                const reason = byPeriod ? `${MOIS_NOMS[detectedMois]} ${detectedAnnee}` : 'Fichier identique';
                items.push(`<div class="fs-detect-item fs-detect-warn" style="grid-column:1/-1"><span class="fs-detect-label">Attention</span><span class="fs-detect-value" style="color:#e65100"><i class="bi bi-exclamation-triangle"></i> Fiche deja existante (${reason})</span></div>`);
            }
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
        document.getElementById('fsBulkStep1').style.display = '';
        document.getElementById('fsBulkStep2').style.display = 'none';
        document.getElementById('fsBulkStep3').style.display = 'none';
        document.getElementById('fsBulkSubmit').style.display = 'none';
        document.getElementById('fsBulkFiles').value = '';
        bulkParsedFiles = [];
        new bootstrap.Modal('#fsBulkModal').show();
    });

    // Bulk dropzone
    const bulkDZ = document.getElementById('fsBulkDropzone');
    const bulkFileInput = document.getElementById('fsBulkFiles');
    if (bulkDZ) {
        bulkDZ.addEventListener('click', () => bulkFileInput.click());
        bulkDZ.addEventListener('dragover', e => { e.preventDefault(); bulkDZ.classList.add('fs-dropzone-active'); });
        bulkDZ.addEventListener('dragleave', () => bulkDZ.classList.remove('fs-dropzone-active'));
        bulkDZ.addEventListener('drop', e => {
            e.preventDefault();
            bulkDZ.classList.remove('fs-dropzone-active');
            const files = [...e.dataTransfer.files].filter(f => f.type === 'application/pdf');
            if (!files.length) { showToast('Seuls les fichiers PDF sont acceptés', 'error'); return; }
            // Transfer to input (can't set .files for multi, store separately)
            bulkDroppedFiles = files;
            parseBulkFiles(files);
        });
        bulkFileInput.addEventListener('change', () => {
            bulkDroppedFiles = null;
            if (bulkFileInput.files.length) parseBulkFiles([...bulkFileInput.files]);
        });
    }

    document.getElementById('fsBulkReselect')?.addEventListener('click', () => {
        document.getElementById('fsBulkStep1').style.display = '';
        document.getElementById('fsBulkStep2').style.display = 'none';
        document.getElementById('fsBulkSubmit').style.display = 'none';
        document.getElementById('fsBulkFiles').value = '';
        bulkParsedFiles = [];
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
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Aucune fiche de salaire</td></tr>';
        return;
    }

    // Group by user
    const groups = {};
    const groupOrder = [];
    filtered.forEach(f => {
        if (!groups[f.user_id]) {
            groups[f.user_id] = { user: f, fiches: [] };
            groupOrder.push(f.user_id);
        }
        groups[f.user_id].fiches.push(f);
    });

    function avatar(f) {
        if (f.photo) return `<img src="${esc(f.photo)}" class="fs-avatar">`;
        const initials = (f.prenom?.[0] || '') + (f.nom?.[0] || '');
        return `<div class="fs-avatar fs-avatar-initials">${esc(initials.toUpperCase())}</div>`;
    }

    let html = '';
    groupOrder.forEach(uid => {
        const g = groups[uid];
        const u = g.user;
        const count = g.fiches.length;

        // Group header row
        html += `<tr class="fs-group-header" data-group="${uid}">
            <td colspan="7">
                <div class="fs-group-user">
                    <span class="fs-group-toggle"><span class="fs-toggle-icon"></span></span>
                    ${avatar(u)}
                    <div>
                        <strong>${esc(u.nom)} ${esc(u.prenom)}</strong>
                        ${u.employee_id ? `<span class="text-muted ms-1">${esc(u.employee_id)}</span>` : ''}
                        ${u.fonction_code ? `<span class="badge bg-secondary ms-1">${esc(u.fonction_code)}</span>` : ''}
                    </div>
                    <span class="fs-group-count">${count} fiche${count > 1 ? 's' : ''}</span>
                </div>
            </td>
        </tr>`;

        // Fiche rows (hidden by default)
        g.fiches.forEach(f => {
            html += `<tr class="fs-fiche-row" data-parent="${uid}" style="display:none">
                <td class="ps-5">${MOIS_NOMS[f.mois] || f.mois} ${f.annee}</td>
                <td><i class="bi bi-file-pdf text-danger"></i> ${esc(f.original_name)}</td>
                <td>${formatSize(f.size)}</td>
                <td>${formatDate(f.created_at)}</td>
                <td>${f.uploaded_by_prenom ? esc(f.uploaded_by_prenom[0] + '. ' + f.uploaded_by_nom) : '-'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" data-action="view" data-id="${f.id}" title="Voir"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${f.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </td>
                <td></td>
            </tr>`;
        });
    });

    tbody.innerHTML = html;

    // Collapse/expand groups
    tbody.querySelectorAll('.fs-group-header').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
            if (e.target.closest('[data-action]')) return;
            const uid = row.dataset.group;
            const rows = tbody.querySelectorAll(`tr[data-parent="${uid}"]`);
            const isOpen = row.classList.contains('fs-open');
            row.classList.toggle('fs-open');
            rows.forEach(r => r.style.display = isOpen ? 'none' : '');
        });
    });
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

let bulkParsedFiles = [];
let bulkDroppedFiles = null;

function parseBulkFiles(files) {
    bulkParsedFiles = files.map(file => {
        const base = file.name.replace(/\.pdf$/i, '').replace(/[_\s]+V\d+$/i, '');
        const parts = base.split(/[_\-\s]+/).filter(Boolean);
        let nom = null, prenom = null, annee = null, mois = null, user = null;
        const remaining = [];
        for (const p of parts) {
            const n = parseInt(p);
            if (/^\d{6}$/.test(p)) { annee = parseInt(p.substring(0, 4)); mois = parseInt(p.substring(4, 6)); }
            else if (/^\d{4}$/.test(p) && n >= 2000 && n <= 2100) { annee = n; }
            else if (/^\d{1,2}$/.test(p) && n >= 1 && n <= 12 && annee) { mois = n; }
            else { remaining.push(p); }
        }
        if (remaining.length >= 2) { nom = remaining[0]; prenom = remaining[1]; }
        else if (remaining.length === 1) { nom = remaining[0]; }

        if (nom) {
            const nL = nom.toLowerCase(), pL = (prenom || '').toLowerCase();
            user = fsUsers.find(u => {
                const uN = u.nom.toLowerCase(), uP = u.prenom.toLowerCase();
                if (uN === nL && pL && uP === pL) return true;
                if (u.employee_id && u.employee_id.toLowerCase() === nL) return true;
                if (!pL && nL.length >= 3 && uN === nL) return true;
                return false;
            });
        }
        // Check duplicate: same user+period OR same original filename
        let duplicate = false;
        let dupReason = '';
        if (user && annee && mois) {
            const byPeriod = fsFiches.find(ex => ex.user_id === user.id && ex.annee == annee && ex.mois == mois);
            if (byPeriod) { duplicate = true; dupReason = `${MOIS_NOMS[mois]} ${annee} existe`; }
        }
        if (!duplicate && user) {
            const byName = fsFiches.find(ex => ex.user_id === user.id && ex.original_name === file.name);
            if (byName) { duplicate = true; dupReason = 'Fichier identique'; }
        }
        return { file, nom, prenom, annee, mois, user, duplicate, dupReason, ok: !!(user && annee && mois && !duplicate) };
    });

    // Render preview
    const body = document.getElementById('fsBulkPreviewBody');
    const okCount = bulkParsedFiles.filter(f => f.ok).length;
    const dupCount = bulkParsedFiles.filter(f => f.duplicate).length;
    const warnCount = bulkParsedFiles.length - okCount;

    body.innerHTML = bulkParsedFiles.map((f, i) => {
        let icon, rowClass, statusBadge;
        if (f.duplicate) {
            icon = '<i class="bi bi-arrow-repeat text-secondary"></i>';
            rowClass = 'table-light';
            statusBadge = `<span class="fb-badge" style="background:#e9ecef;color:#6c757d" title="${esc(f.dupReason)}"><i class="bi bi-dash"></i> Existe</span>`;
        } else if (f.ok) {
            icon = '<i class="bi bi-check-circle-fill text-success"></i>';
            rowClass = '';
            statusBadge = '<span class="fb-badge fb-badge-ok"><i class="bi bi-check"></i> OK</span>';
        } else {
            icon = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
            rowClass = 'table-warning';
            statusBadge = '<span class="fb-badge fb-badge-warn"><i class="bi bi-dash"></i> Incomplet</span>';
        }
        return `<tr class="${rowClass}" style="${f.duplicate ? 'opacity:.6;text-decoration:line-through' : ''}">
            <td class="text-center">${icon}</td>
            <td><div class="fb-file"><i class="bi bi-file-earmark-pdf"></i><span title="${esc(f.file.name)}">${esc(f.file.name)}</span></div></td>
            <td>${f.user
                ? `<span class="fb-user-ok">${esc(f.user.nom)} ${esc(f.user.prenom)}</span>`
                : `<span class="fb-user-warn">${esc(f.nom || '?')} ${esc(f.prenom || '?')} - non trouve</span>`}</td>
            <td>${f.annee && f.mois ? `${MOIS_NOMS[f.mois]} ${f.annee}` : '<span class="text-muted">?</span>'}</td>
            <td>${statusBadge}</td>
        </tr>`;
    }).join('');

    document.getElementById('fsBulkPreviewTitle').innerHTML =
        `<i class="bi bi-files"></i> ${bulkParsedFiles.length} fichier${bulkParsedFiles.length > 1 ? 's' : ''} analysé${bulkParsedFiles.length > 1 ? 's' : ''}`;

    const incompleteCount = bulkParsedFiles.filter(f => !f.ok && !f.duplicate).length;
    let statsHtml = `
        <div class="fs-bulk-stat stat-total">
            <div class="fs-bulk-stat-value">${bulkParsedFiles.length}</div>
            <div class="fs-bulk-stat-label"><i class="bi bi-files"></i> Fichiers</div>
        </div>
        <div class="fs-bulk-stat stat-ok">
            <div class="fs-bulk-stat-value">${okCount}</div>
            <div class="fs-bulk-stat-label"><i class="bi bi-check-circle"></i> A importer</div>
        </div>`;
    if (dupCount) statsHtml += `
        <div class="fs-bulk-stat stat-dup">
            <div class="fs-bulk-stat-value">${dupCount}</div>
            <div class="fs-bulk-stat-label"><i class="bi bi-arrow-repeat"></i> Deja importes</div>
        </div>`;
    if (incompleteCount) statsHtml += `
        <div class="fs-bulk-stat stat-warn">
            <div class="fs-bulk-stat-value">${incompleteCount}</div>
            <div class="fs-bulk-stat-label"><i class="bi bi-exclamation-triangle"></i> Incomplets</div>
        </div>`;
    document.getElementById('fsBulkSummary').innerHTML = statsHtml;

    document.getElementById('fsBulkStep1').style.display = 'none';
    document.getElementById('fsBulkStep2').style.display = '';
    document.getElementById('fsBulkSubmitCount').textContent = `(${okCount})`;
    document.getElementById('fsBulkSubmit').style.display = okCount > 0 ? '' : 'none';
}

async function uploadBulk() {
    const readyFiles = bulkParsedFiles.filter(f => f.ok);
    const fileList = readyFiles;

    if (!readyFiles.length) return;

    const total = readyFiles.length;
    let uploaded = 0, skipped = 0, errors = 0;

    const btn = document.getElementById('fsBulkSubmit');
    const closeBtn = document.getElementById('fsBulkCloseBtn');
    btn.disabled = true;
    btn.style.display = 'none';
    closeBtn.disabled = true;

    // Switch to step 3
    document.getElementById('fsBulkStep2').style.display = 'none';
    const step3 = document.getElementById('fsBulkStep3');
    step3.style.display = '';

    const progressBar = document.getElementById('fsBulkProgressBar');
    const progressLabel = document.getElementById('fsBulkProgressLabel');
    const progressCounter = document.getElementById('fsBulkProgressCounter');
    const currentFile = document.getElementById('fsBulkCurrentFile');
    const log = document.getElementById('fsBulkLog');

    progressBar.style.width = '0%';
    progressCounter.textContent = `0 / ${total}`;
    log.innerHTML = '';

    for (let i = 0; i < total; i++) {
        const f = readyFiles[i];
        const pct = Math.round((i / total) * 100);
        progressBar.style.width = pct + '%';
        progressCounter.textContent = `${i + 1} / ${total}`;
        progressLabel.textContent = `Importation en cours... (${i + 1}/${total})`;

        currentFile.innerHTML = `<span class="spinner-border"></span><i class="bi bi-file-earmark-pdf"></i><span class="fs-bc-name">${esc(f.file.name)}</span>`;

        const fd = new FormData();
        fd.append('action', 'admin_upload_fiche_salaire');
        fd.append('user_id', f.user.id);
        fd.append('annee', f.annee);
        fd.append('mois', f.mois);
        fd.append('file', f.file);

        try {
            const resp = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: fd,
            });
            const res = await resp.json();
            if (res.success) {
                uploaded++;
                log.innerHTML += `<div class="fs-bulk-log-item log-ok"><i class="bi bi-check-circle-fill"></i><span>${esc(f.file.name)}</span><small>${esc(f.user.nom)} ${esc(f.user.prenom)} - ${MOIS_NOMS[f.mois]} ${f.annee}</small></div>`;
            } else {
                skipped++;
                log.innerHTML += `<div class="fs-bulk-log-item log-skip"><i class="bi bi-dash-circle"></i><span>${esc(f.file.name)}</span><small>${esc(res.message || 'Erreur')}</small></div>`;
            }
        } catch (e) {
            errors++;
            log.innerHTML += `<div class="fs-bulk-log-item log-err"><i class="bi bi-x-circle-fill"></i><span>${esc(f.file.name)}</span><small>Erreur réseau</small></div>`;
        }
        log.scrollTop = log.scrollHeight;
    }

    // Done
    progressBar.style.width = '100%';
    currentFile.innerHTML = '';
    progressLabel.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> Terminé : <strong>${uploaded}</strong> importé${uploaded > 1 ? 's' : ''}` +
        (skipped ? `, <strong>${skipped}</strong> ignoré${skipped > 1 ? 's' : ''}` : '') +
        (errors ? `, <strong class="text-danger">${errors}</strong> erreur${errors > 1 ? 's' : ''}` : '');

    closeBtn.disabled = false;

    // Toast summary
    if (uploaded > 0) showToast(`${uploaded} fiche${uploaded > 1 ? 's' : ''} importee${uploaded > 1 ? 's' : ''}`, 'success');
    else if (skipped > 0) showToast(`Aucune fiche importee (${skipped} ignoree${skipped > 1 ? 's' : ''})`, 'warning');

    await loadFiches();
}

function viewFiche(id) {
    window.open(`/spocspace/admin/api.php?action=admin_serve_fiche_salaire&id=${id}`, '_blank');
}

async function deleteFiche(id) {
    // Find fiche details for confirmation
    const fiche = fsFiches.find(f => f.id === id);
    const label = fiche ? `${esc(fiche.prenom)} ${esc(fiche.nom)} - ${MOIS_NOMS[fiche.mois]} ${fiche.annee}` : '';

    const confirmed = await new Promise(resolve => {
        const existing = document.getElementById('fsDeleteConfirmModal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'fsDeleteConfirmModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-trash3 text-danger me-2"></i>Supprimer la fiche de salaire</h5>
                        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Voulez-vous vraiment supprimer cette fiche de salaire ?</p>
                        ${label ? `<div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:10px 14px;font-size:.88rem"><i class="bi bi-file-earmark-pdf text-danger me-2"></i><strong>${label}</strong></div>` : ''}
                        <p class="text-muted small mt-2 mb-0">Cette action est irr\u00e9versible. Le fichier sera d\u00e9finitivement supprim\u00e9.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-danger" id="fsDeleteConfirmOk"><i class="bi bi-trash3"></i> Supprimer</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        modal.querySelector('#fsDeleteConfirmOk').addEventListener('click', () => { bsModal.hide(); resolve(true); });
        modal.addEventListener('hidden.bs.modal', () => { modal.remove(); resolve(false); });
        bsModal.show();
    });

    if (!confirmed) return;
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
.fs-dropzone{border:2px dashed #ccc;border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s,box-shadow .2s;background:#fafafa}
.fs-dropzone:hover,.fs-dropzone-active{border-color:#C4A882;background:rgba(196,168,130,.06);box-shadow:0 0 0 3px rgba(196,168,130,.12)}
.fs-filter-auto{width:auto}
.fs-pdf-icon{font-size:2.5rem;color:#c62828}
.fs-browse-link{cursor:pointer;text-decoration:underline}
.fs-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0}
.fs-avatar-initials{display:flex;align-items:center;justify-content:center;background:#bcd2cb;color:#2d4a43;font-size:.78rem;font-weight:700;letter-spacing:.5px}
.fs-group-header td{background:#F7F5F2 !important;border-bottom:1px solid #e9ecef;padding:10px 12px !important}
.fs-group-user{display:flex;align-items:center;gap:10px}
.fs-group-user strong{font-size:.9rem}
.fs-group-toggle{width:22px;height:22px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:50%;border:1.5px solid #ccc;transition:all .25s;cursor:pointer}
.fs-group-header:hover .fs-group-toggle{border-color:#999}
.fs-toggle-icon{position:relative;width:10px;height:10px}
.fs-toggle-icon::before,.fs-toggle-icon::after{content:'';position:absolute;background:#999;border-radius:1px;transition:transform .3s cubic-bezier(.4,0,.2,1),opacity .3s}
.fs-toggle-icon::before{width:10px;height:1.5px;top:50%;left:0;transform:translateY(-50%)}
.fs-toggle-icon::after{width:1.5px;height:10px;left:50%;top:0;transform:translateX(-50%)}
.fs-group-header.fs-open .fs-toggle-icon::after{transform:translateX(-50%) rotate(90deg);opacity:0}
.fs-group-header.fs-open .fs-group-toggle{border-color:#2d4a43;background:rgba(188,210,203,.15)}
.fs-group-header.fs-open .fs-toggle-icon::before,.fs-group-header.fs-open .fs-toggle-icon::after{background:#2d4a43}
.fs-group-count{margin-left:auto;font-size:.75rem;color:#999;background:#fff;border:1px solid #e9ecef;padding:2px 10px;border-radius:20px}
.fs-fiche-row td{font-size:.85rem;border-bottom:1px solid #f1f3f5;vertical-align:middle}
.fs-detect-banner{background:#f4f9f6;border:1px solid #bcd2cb;border-radius:10px;padding:12px;margin-bottom:14px;animation:fsDetectIn .3s ease}
@keyframes fsDetectIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.fs-detect-header{font-size:.82rem;font-weight:600;color:#2d4a43;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.fs-detect-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.fs-detect-item{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:6px 10px;font-size:.8rem}
.fs-detect-item .fs-detect-label{font-size:.68rem;text-transform:uppercase;color:#999;letter-spacing:.3px;display:block}
.fs-detect-item .fs-detect-value{font-weight:600;color:#212529}
.fs-detect-item.fs-detect-ok{border-color:#bcd2cb;background:#f4f9f6}
.fs-detect-item.fs-detect-warn{border-color:#ffe082;background:#fffde7}
.fs-manual-toggle{background:none;border:none;color:#999;font-size:.78rem;cursor:pointer;padding:0;display:flex;align-items:center;gap:5px;transition:color .15s}
.fs-manual-toggle:hover{color:#2e7d32;text-decoration:underline}
.fs-bulk-current{background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:8px 12px;margin-bottom:8px;font-size:.82rem;display:flex;align-items:center;gap:8px;min-height:38px}
.fs-bulk-current i{color:#c62828;font-size:1.1rem}
.fs-bulk-current .fs-bc-name{flex:1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fs-bulk-current .spinner-border{width:16px;height:16px;border-width:2px;color:#2d4a43}
.fs-bulk-log{display:flex;flex-direction:column;gap:3px}
.fs-bulk-log-item{display:flex;align-items:center;gap:6px;padding:3px 8px;border-radius:4px;font-size:.78rem}
.fs-bulk-log-item.log-ok{background:#f4f9f6;color:#2d4a43}
.fs-bulk-log-item.log-skip{background:#fff8e1;color:#e65100}
.fs-bulk-log-item.log-err{background:#fde2e0;color:#c62828}
.fs-bulk-log-item .bi{font-size:.85rem;flex-shrink:0}
.fs-bulk-log-item span{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fs-bulk-info{display:flex;gap:10px;align-items:flex-start;background:#f4f9f6;border:1px solid #bcd2cb;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.82rem;color:#2d4a43}
.fs-bulk-info code{background:rgba(45,74,67,.08);padding:1px 5px;border-radius:3px;font-size:.78rem;color:#2d4a43}
.fs-bulk-info .bi{font-size:1.2rem;margin-top:2px;flex-shrink:0;color:#2d4a43}
.fs-bulk-dropzone{padding:2.5rem 2rem;min-height:160px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.fs-bulk-drop-icon{font-size:2.8rem;color:#C4A882;opacity:.6;margin-bottom:8px}
.fs-bulk-preview-wrap{max-height:300px;overflow-y:auto;border:1px solid #e9ecef;border-radius:8px;margin-bottom:10px}
.fs-bulk-table{font-size:.82rem}
.fs-bulk-table th{background:#f8f9fa;position:sticky;top:0;z-index:1;font-weight:600;font-size:.72rem;text-transform:uppercase;color:#6c757d;letter-spacing:.3px}
.fs-bulk-table td{vertical-align:middle}
.fs-bulk-table .fb-file{display:flex;align-items:center;gap:6px;font-size:.8rem}
.fs-bulk-table .fb-file i{color:#c62828;font-size:1rem}
.fs-bulk-table .fb-file span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px}
.fb-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:8px;font-size:.7rem;font-weight:600}
.fb-badge-ok{background:#e8f2ee;color:#2d4a43}
.fb-badge-warn{background:#fff3e0;color:#e65100}
.fb-user-ok{color:#212529;font-weight:500}
.fb-user-warn{color:#e65100;font-style:italic}
.fs-bulk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:12px}
.fs-bulk-stat{text-align:center;padding:12px 8px;border-radius:10px;border:1px solid #e9ecef;background:#fff}
.fs-bulk-stat-value{font-size:1.5rem;font-weight:700;line-height:1.1}
.fs-bulk-stat-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.3px;color:#6c757d;margin-top:3px;font-weight:500}
.fs-bulk-stat.stat-ok{border-color:#bcd2cb;background:#f4f9f6}
.fs-bulk-stat.stat-ok .fs-bulk-stat-value{color:#2d4a43}
.fs-bulk-stat.stat-dup{border-color:#e0e0e0;background:#f5f5f5}
.fs-bulk-stat.stat-dup .fs-bulk-stat-value{color:#757575}
.fs-bulk-stat.stat-warn{border-color:#ffe0b2;background:#fff8e1}
.fs-bulk-stat.stat-warn .fs-bulk-stat-value{color:#e65100}
.fs-bulk-stat.stat-total{border-color:#d6cfc4;background:#F7F5F2}
.fs-bulk-stat.stat-total .fs-bulk-stat-value{color:#1A1A1A}
</style>
