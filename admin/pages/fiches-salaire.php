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
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
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
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
        <div id="fsBulkResult" class="d-none">
          <div class="alert alert-success small" id="fsBulkSuccess"></div>
          <div class="alert alert-warning small d-none" id="fsBulkSkipped"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
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
        document.getElementById('fsUploadFile').value = '';
        document.getElementById('fsDropzoneFile').classList.add('d-none');
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
    }

    document.getElementById('fsBulkUploadBtn')?.addEventListener('click', () => {
        document.getElementById('fsBulkAnnee').value = fsYear;
        zerdaSelect.setValue('#fsBulkMois', String(new Date().getMonth() + 1));
        document.getElementById('fsBulkFiles').value = '';
        document.getElementById('fsBulkCount').textContent = '';
        document.getElementById('fsBulkResult').classList.add('d-none');
        new bootstrap.Modal('#fsBulkModal').show();
    });

    document.getElementById('fsBulkFiles')?.addEventListener('change', e => {
        const n = e.target.files.length;
        document.getElementById('fsBulkCount').textContent = n ? `${n} fichier(s) sélectionné(s)` : '';
    });

    document.getElementById('fsUploadSubmit')?.addEventListener('click', uploadSingle);
    document.getElementById('fsBulkSubmit')?.addEventListener('click', uploadBulk);

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
        const resp = await fetch('/zerdatime/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: fd,
        });
        const res = await resp.json();
        if (res.success) {
            bootstrap.Modal.getInstance('#fsUploadModal')?.hide();
            showToast(res.message, 'success');
            await loadFiches();
        } else {
            alert(res.error || 'Erreur');
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
    const files = document.getElementById('fsBulkFiles').files;

    if (!files.length) { alert('Sélectionnez des fichiers'); return; }

    const fd = new FormData();
    fd.append('action', 'admin_bulk_upload_fiches');
    fd.append('annee', annee);
    fd.append('mois', mois);
    for (let i = 0; i < files.length; i++) {
        fd.append('files[]', files[i]);
    }

    const btn = document.getElementById('fsBulkSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';

    try {
        const resp = await fetch('/zerdatime/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: fd,
        });
        const res = await resp.json();
        const resultDiv = document.getElementById('fsBulkResult');
        resultDiv.classList.remove('d-none');

        document.getElementById('fsBulkSuccess').textContent = res.message || `${res.uploaded} fiche(s) importée(s)`;

        const skippedDiv = document.getElementById('fsBulkSkipped');
        if (res.skipped?.length) {
            skippedDiv.classList.remove('d-none');
            skippedDiv.innerHTML = '<strong>Fichiers ignorés :</strong><ul class="mb-0">' +
                res.skipped.map(s => `<li>${esc(s)}</li>`).join('') + '</ul>';
        } else {
            skippedDiv.classList.add('d-none');
        }

        await loadFiches();
    } catch (e) {
        alert('Erreur réseau');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Importer';
    }
}

function viewFiche(id) {
    window.open(`/zerdatime/admin/api.php?action=admin_serve_fiche_salaire&id=${id}`, '_blank');
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
</style>
