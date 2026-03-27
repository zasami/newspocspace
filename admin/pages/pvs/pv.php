<!-- PV List Page -->
<style>
/* PV Badge Colors - Theme aligned */
.badge.bg-success { background-color: #16A34A !important; }
.badge.bg-warning { background-color: #FFC107 !important; color: #1B2A4A !important; font-weight: 600; }
.badge.bg-secondary { background-color: #6B6B6B !important; }
.badge.bg-danger { background-color: #DC2626 !important; }

/* Table hover */
#pvTable tbody tr { transition: background-color 0.2s; }
#pvTable tbody tr:hover { background-color: rgba(25, 25, 24, 0.04); }

/* Button styling */
.btn-outline-danger { color: #DC2626; border-color: #DC2626; }
.btn-outline-danger:hover { background-color: #DC2626; border-color: #DC2626; color: white; }

#btnCreatePv {
  background-color: #F5F5DC;
  border: 1px solid #DCDCDC;
  color: #1B2A4A;
  font-weight: 600;
  transition: all 0.2s;
}
#btnCreatePv:hover {
  background-color: #EAEAD2;
  border-color: #C0C0C0;
}

/* Column widths */
.pv-col-titre { width: 40%; }
.pv-col-creator { width: 15%; }
.pv-col-module { width: 15%; }
.pv-col-date { width: 12%; }
.pv-col-statut { width: 10%; }
.pv-col-actions { width: 8%; text-align: center; }

/* Row & cell styles */
.pv-row-clickable { cursor: pointer; }
.pv-cell-center { text-align: center; }

/* Participants list */
.pv-participants-scroll { max-height: 200px; overflow-y: auto; }

/* Save button (modal) */
.btn-pv-save { background-color: #16A34A; border: none; color: white; font-weight: 600; }
.btn-pv-save:hover { background-color: #15803D; }
</style>

<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-file-earmark-text"></i> Procès-Verbaux
    </h1>
    <button class="btn" id="btnCreatePv">
      <i class="bi bi-plus-lg"></i> Nouveau PV
    </button>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label small">Recherche</label>
          <input type="text" class="form-control form-control-sm" id="pvSearch" placeholder="Titre, description...">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Module</label>
          <div class="zs-select" id="pvModuleFilter" data-placeholder="Tous les modules"></div>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Étage</label>
          <div class="zs-select" id="pvEtageFilter" data-placeholder="Tous les étages"></div>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Fonction</label>
          <div class="zs-select" id="pvFonctionFilter" data-placeholder="Toutes les fonctions"></div>
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-sm btn-outline-secondary" id="btnFilterClear">
          <i class="bi bi-arrow-clockwise"></i> Réinitialiser
        </button>
      </div>
    </div>
  </div>

  <!-- PV List Table -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="pvTable">
        <thead class="table-light">
          <tr>
            <th class="pv-col-titre">Titre</th>
            <th class="pv-col-creator">Créator</th>
            <th class="pv-col-module">Module / Étage</th>
            <th class="pv-col-date">Date</th>
            <th class="pv-col-statut">Statut</th>
            <th class="pv-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody id="pvTableBody">
          <tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-center text-muted small">
      <span id="pvCount">—</span> procès-verbaux
    </div>
  </div>

  <!-- Pagination -->
  <div class="d-flex justify-content-center mt-4">
    <nav>
      <ul class="pagination pagination-sm" id="pvPagination"></ul>
    </nav>
  </div>
</div>

<!-- Modal: Create PV -->
<div class="modal fade" id="modalCreatePv" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> Nouveau PV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Titre *</label>
          <input type="text" class="form-control form-control-sm" id="pvFormTitre" placeholder="Ex: Réunion d'équipe - Mars 2026">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Description</label>
          <textarea class="form-control form-control-sm" id="pvFormDescription" rows="3" placeholder="Contexte, ordre du jour..."></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small">Module</label>
            <div class="zs-select" id="pvFormModule" data-placeholder="—"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Étage</label>
            <div class="zs-select" id="pvFormEtage" data-placeholder="—"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Fonction concernée</label>
          <div class="zs-select" id="pvFormFonction" data-placeholder="—"></div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Participants</label>
          <div class="border rounded p-3 bg-light pv-participants-scroll" id="pvFormParticipantsList">
            <!-- Filled by JS -->
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-pv-save" id="btnSaveCreatePv">
          <i class="bi bi-arrow-right"></i> Créer et enregistrer
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
  const refs = await adminApiPost('admin_get_pv_refs', {});
  if (!refs.success) return;

  // Fill selects
  const modules = refs.modules || [];
  const etages = refs.etages || [];
  const fonctions = refs.fonctions || [];

  const buildOptions = (items, labelKey = 'nom', valueKey = 'id') => {
    const opts = [{ value: '', label: '—' }];
    items.forEach(item => opts.push({ value: item[valueKey], label: item[labelKey] + ' (' + item.code + ')' }));
    return opts;
  };

  const initZs = (id, items, labelKey = 'nom', valueKey = 'id', onSelectCb) => {
    const el = document.getElementById(id);
    if (!el) return;
    const opts = buildOptions(items, labelKey, valueKey);
    zerdaSelect.init(el, opts, { onSelect: onSelectCb, value: '', search: false, width: 'auto' });
  };

  initZs('pvModuleFilter', modules, 'nom', 'id', () => {
    const modId = zerdaSelect.getValue('#pvModuleFilter');
    const modEtages = etages.filter(et => et.module_id === modId);
    zerdaSelect.destroy(document.getElementById('pvEtageFilter'));
    initZs('pvEtageFilter', modEtages, 'nom', 'id', () => loadPvList());
    zerdaSelect.destroy(document.getElementById('pvFormEtage'));
    initZs('pvFormEtage', modEtages);
    loadPvList();
  });
  initZs('pvEtageFilter', etages, 'nom', 'id', () => loadPvList());
  initZs('pvFonctionFilter', fonctions, 'nom', 'id', () => loadPvList());
  initZs('pvFormModule', modules);
  initZs('pvFormFonction', fonctions);
  initZs('pvFormEtage', etages);

  // Participants checkbox list
  const fillParticipants = () => {
    const list = document.getElementById('pvFormParticipantsList');
    list.innerHTML = '';
    (refs.users || []).forEach(u => {
      list.innerHTML += `
        <div class="form-check">
          <input class="form-check-input pv-participant" type="checkbox" value="${u.id}" id="pvParticipant_${u.id}">
          <label class="form-check-label" for="pvParticipant_${u.id}">
            ${u.prenom} ${u.nom} <small class="text-muted">${u.fonction_id || '?'}</small>
          </label>
        </div>
      `;
    });
  };
  fillParticipants();

  // Load PV list
  const loadPvList = async (page = 1) => {
    const params = {
      page,
      module_id: zerdaSelect.getValue('#pvModuleFilter'),
      etage_id: zerdaSelect.getValue('#pvEtageFilter'),
      fonction_id: zerdaSelect.getValue('#pvFonctionFilter'),
      search: document.getElementById('pvSearch').value,
    };
    const result = await adminApiPost('admin_get_pv_list', params);
    if (!result.success) { toast('Erreur'); return; }

    const tbody = document.getElementById('pvTableBody');
    tbody.innerHTML = '';
    (result.list || []).forEach(pv => {
      const statusBadge = pv.statut === 'finalisé' ? 'success' : pv.statut === 'enregistrement' ? 'warning' : 'secondary';

      let statusIcon = '<i class="bi bi-file-earmark-text text-secondary me-2"></i>';
      if (pv.statut === 'finalisé') {
          statusIcon = '<i class="bi bi-check-circle-fill text-success me-2"></i>';
      } else if (pv.statut === 'enregistrement' || pv.statut === 'brouillon') {
          statusIcon = '<i class="bi bi-pencil-square text-warning me-2"></i>';
      }

      tbody.innerHTML += `
        <tr class="pv-row-clickable" data-pv-id="${pv.id}">
          <td>${statusIcon}<strong>${escapeHtml(pv.titre)}</strong></td>
          <td>${pv.prenom || '?'} ${pv.nom || ''}</td>
          <td>${pv.module_code ? pv.module_code : '—'} ${pv.etage_code ? '/ ' + pv.etage_code : ''}</td>
          <td><small>${new Date(pv.created_at).toLocaleDateString('fr-FR')}</small></td>
          <td><span class="badge bg-${statusBadge}">${pv.statut}</span></td>
          <td class="pv-cell-center">
            <button class="btn btn-xs btn-outline-danger" data-delete-pv="${pv.id}" title="Supprimer">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `;
    });

    document.getElementById('pvCount').textContent = result.total;

    // Pagination
    const pag = document.getElementById('pvPagination');
    pag.innerHTML = '';
    for (let p = 1; p <= result.pages; p++) {
      const active = p === page ? 'active' : '';
      pag.innerHTML += `<li class="page-item ${active}"><a class="page-link" href="#" data-pv-page="${p}">${p}</a></li>`;
    }
  };

  // Event delegation for table rows, delete buttons, and pagination
  document.getElementById('pvTable').addEventListener('click', async (e) => {
    const deleteBtn = e.target.closest('[data-delete-pv]');
    if (deleteBtn) {
      e.stopPropagation();
      const pvId = deleteBtn.getAttribute('data-delete-pv');
      if (!confirm('Supprimer ce PV?')) return;
      const r = await adminApiPost('admin_delete_pv', { id: pvId });
      if (r.success) { toast('PV supprimé'); loadPvList(); }
      else toast(r.message || 'Erreur');
      return;
    }
    const row = e.target.closest('[data-pv-id]');
    if (row) {
      window.location.href = AdminURL.page('pv-detail', row.getAttribute('data-pv-id'));
    }
  });

  document.getElementById('pvPagination').addEventListener('click', (e) => {
    e.preventDefault();
    const link = e.target.closest('[data-pv-page]');
    if (link) loadPvList(parseInt(link.getAttribute('data-pv-page'), 10));
  });

  window.loadPvList = loadPvList;

  // Filters (change events handled by zerdaSelect onSelect callbacks)
  document.getElementById('pvSearch').addEventListener('keyup', () => loadPvList());
  document.getElementById('btnFilterClear').addEventListener('click', () => {
    zerdaSelect.setValue(document.getElementById('pvModuleFilter'), '');
    zerdaSelect.setValue(document.getElementById('pvEtageFilter'), '');
    zerdaSelect.setValue(document.getElementById('pvFonctionFilter'), '');
    document.getElementById('pvSearch').value = '';
    loadPvList();
  });

  // Create PV dialog
  const modalCreatePv = new bootstrap.Modal(document.getElementById('modalCreatePv'));
  document.getElementById('btnCreatePv').addEventListener('click', () => modalCreatePv.show());

  document.getElementById('btnSaveCreatePv').addEventListener('click', async () => {
    const titre = document.getElementById('pvFormTitre').value.trim();
    if (!titre) { toast('Titre requis'); return; }

    const participants = Array.from(document.querySelectorAll('.pv-participant:checked')).map(c => {
      const u = (refs.users || []).find(u => u.id === c.value);
      return { id: u.id, prenom: u.prenom, nom: u.nom };
    });

    const data = {
      titre,
      description: document.getElementById('pvFormDescription').value,
      module_id: zerdaSelect.getValue('#pvFormModule'),
      etage_id: zerdaSelect.getValue('#pvFormEtage'),
      fonction_id: zerdaSelect.getValue('#pvFormFonction'),
      participants,
    };

    const r = await adminApiPost('admin_create_pv', data);
    if (r.success) {
      toast('PV créé');
      modalCreatePv.hide();
      window.location.href = AdminURL.page('pv-record', r.id);
    } else {
      toast(r.message || 'Erreur');
    }
  });

  // Initial load
  loadPvList();
});
</script>
