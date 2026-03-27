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

/* Participants list in modal */
#pvFormParticipantsList .form-check {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  margin: 0;
}
#pvFormParticipantsList .form-check-input {
  margin: 0;
  flex-shrink: 0;
}
#pvFormParticipantsList .form-check-label {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.4;
  padding: 0;
}
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
          <select class="form-select form-select-sm" id="pvModuleFilter">
            <option value="">Tous les modules</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Étage</label>
          <select class="form-select form-select-sm" id="pvEtageFilter">
            <option value="">Tous les étages</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Fonction</label>
          <select class="form-select form-select-sm" id="pvFonctionFilter">
            <option value="">Toutes les fonctions</option>
          </select>
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
            <th style="width: 40%;">Titre</th>
            <th style="width: 15%;">Créator</th>
            <th style="width: 15%;">Module / Étage</th>
            <th style="width: 12%;">Date</th>
            <th style="width: 10%;">Statut</th>
            <th style="width: 8%; text-align: center;">Actions</th>
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
            <select class="form-select form-select-sm" id="pvFormModule">
              <option value="">—</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Étage</label>
            <select class="form-select form-select-sm" id="pvFormEtage">
              <option value="">—</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Fonction concernée</label>
          <select class="form-select form-select-sm" id="pvFormFonction">
            <option value="">—</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Participants</label>
          <div class="border rounded p-3 bg-light" id="pvFormParticipantsList" style="max-height: 200px; overflow-y: auto;">
            <!-- Filled by JS -->
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" id="btnSaveCreatePv" style="background-color: #16A34A; border: none; color: white; font-weight: 600;">
          <i class="bi bi-arrow-right"></i> Créer et enregistrer
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
document.addEventListener('DOMContentLoaded', async function() {
  const refs = await adminApiPost('admin_get_pv_refs', {});
  if (!refs.success) return;

  // Fill selects
  const modules = refs.modules || [];
  const etages = refs.etages || [];
  const fonctions = refs.fonctions || [];

  const fillSelect = (el, items, labelKey = 'nom', valueKey = 'id') => {
    if (!el) return;
    const val = el.value;
    el.innerHTML = '<option value="">—</option>';
    items.forEach(item => {
      el.innerHTML += `<option value="${item[valueKey]}">${item[labelKey]} (${item.code})</option>`;
    });
    el.value = val;
  };

  fillSelect(document.getElementById('pvModuleFilter'), modules);
  fillSelect(document.getElementById('pvEtageFilter'), etages, 'nom');
  fillSelect(document.getElementById('pvFonctionFilter'), fonctions);
  fillSelect(document.getElementById('pvFormModule'), modules);
  fillSelect(document.getElementById('pvFormFonction'), fonctions);

  const pvEtageFilter = document.getElementById('pvEtageFilter');
  const pvFormEtage = document.getElementById('pvFormEtage');
  document.getElementById('pvModuleFilter').addEventListener('change', e => {
    const modId = e.target.value;
    const modEtages = etages.filter(et => et.module_id === modId);
    fillSelect(pvEtageFilter, modEtages);
    fillSelect(pvFormEtage, modEtages);
  });

  // Build a fonction_id → code lookup
  const fonctionMap = {};
  (refs.fonctions || []).forEach(f => { fonctionMap[f.id] = f.code || f.nom; });

  // Participants checkbox list
  const fillParticipants = () => {
    const list = document.getElementById('pvFormParticipantsList');
    list.innerHTML = '';
    (refs.users || []).forEach(u => {
      const fonctionLabel = fonctionMap[u.fonction_id] || '';
      const div = document.createElement('div');
      div.className = 'form-check';
      div.innerHTML = `
          <input class="form-check-input pv-participant" type="checkbox" value="${u.id}" id="pvParticipant_${u.id}">
          <label class="form-check-label" for="pvParticipant_${u.id}">
            ${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}${fonctionLabel ? ' <small class="text-muted">(' + escapeHtml(fonctionLabel) + ')</small>' : ''}
          </label>
      `;
      list.appendChild(div);
    });
  };
  fillParticipants();

  // Load PV list
  const loadPvList = async (page = 1) => {
    const params = {
      page,
      module_id: document.getElementById('pvModuleFilter').value,
      etage_id: document.getElementById('pvEtageFilter').value,
      fonction_id: document.getElementById('pvFonctionFilter').value,
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

      const tr = document.createElement('tr');
      tr.style.cursor = 'pointer';
      tr.dataset.pvId = pv.id;
      tr.innerHTML = `
          <td>${statusIcon}<strong>${escapeHtml(pv.titre)}</strong></td>
          <td>${escapeHtml(pv.prenom || '?')} ${escapeHtml(pv.nom || '')}</td>
          <td>${pv.module_code ? escapeHtml(pv.module_code) : '—'} ${pv.etage_code ? '/ ' + escapeHtml(pv.etage_code) : ''}</td>
          <td><small>${new Date(pv.created_at).toLocaleDateString('fr-FR')}</small></td>
          <td><span class="badge bg-${statusBadge}">${escapeHtml(pv.statut)}</span></td>
          <td style="text-align:center;">
            <button class="btn btn-xs btn-outline-danger pv-delete-btn" title="Supprimer">
              <i class="bi bi-trash"></i>
            </button>
          </td>
      `;
      tr.addEventListener('click', () => goToPvDetail(pv.id));
      tr.querySelector('.pv-delete-btn').addEventListener('click', (e) => { e.stopPropagation(); deletePv(pv.id); });
      tbody.appendChild(tr);
    });

    document.getElementById('pvCount').textContent = result.total;

    // Pagination
    const pag = document.getElementById('pvPagination');
    pag.innerHTML = '';
    for (let p = 1; p <= result.pages; p++) {
      const li = document.createElement('li');
      li.className = 'page-item' + (p === page ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = p;
      a.addEventListener('click', (e) => { e.preventDefault(); loadPvList(p); });
      li.appendChild(a);
      pag.appendChild(li);
    }
  };

  window.goToPvDetail = (pvId) => {
    window.location.href = AdminURL.page('pv-detail', pvId);
  };

  window.deletePv = async (pvId) => {
    if (!confirm('Supprimer ce PV?')) return;
    const r = await adminApiPost('admin_delete_pv', { id: pvId });
    if (r.success) { toast('PV supprimé'); loadPvList(); }
    else toast(r.message || 'Erreur');
  };

  window.loadPvList = loadPvList;

  // Filters
  document.getElementById('pvModuleFilter').addEventListener('change', () => loadPvList());
  document.getElementById('pvEtageFilter').addEventListener('change', () => loadPvList());
  document.getElementById('pvFonctionFilter').addEventListener('change', () => loadPvList());
  document.getElementById('pvSearch').addEventListener('keyup', () => loadPvList());
  document.getElementById('btnFilterClear').addEventListener('click', () => {
    document.getElementById('pvModuleFilter').value = '';
    document.getElementById('pvEtageFilter').value = '';
    document.getElementById('pvFonctionFilter').value = '';
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
      module_id: document.getElementById('pvFormModule').value,
      etage_id: document.getElementById('pvFormEtage').value,
      fonction_id: document.getElementById('pvFormFonction').value,
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
