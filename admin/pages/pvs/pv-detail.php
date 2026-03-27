<!-- PV Detail Page -->
<style>
/* PV Detail styles */
.pv-detail-content {
  min-height: 300px;
  border: 1px solid #dee2e6;
  padding: 15px;
  border-radius: 4px;
  background: #f9f9f9;
}
.btn-pv-primary {
  background-color: #1B2A4A;
  border: none;
  color: white;
  font-weight: 600;
}
.btn-pv-primary:hover {
  background-color: #162240;
  color: white;
}
.pv-status-badge {
  background-color: #FFC107;
  color: #1B2A4A;
  font-weight: 600;
}
</style>

<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-file-earmark-text"></i> <span id="detailTitle">Chargement...</span>
    </h1>
    <button class="btn btn-outline-secondary btn-sm" id="btnBackToList">
      <i class="bi bi-arrow-left"></i> Liste
    </button>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <!-- Content -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Contenu du PV</h6>
        </div>
        <div class="card-body">
          <div id="detailContent" class="pt-3 pv-detail-content">
            —
          </div>
        </div>
      </div>

      <!-- Edit -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Éditer le contenu</h6>
        </div>
        <div class="card-body">
          <textarea class="form-control" id="editContent" rows="6" placeholder="Contenu du PV..."></textarea>
          <div class="mt-3">
            <button class="btn btn-sm btn-pv-primary" id="btnSaveContent">
              <i class="bi bi-check-lg"></i> Sauvegarder
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Side Info -->
    <div class="col-lg-4">
      <!-- Info Card -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Informations</h6>
        </div>
        <div class="card-body small">
          <div class="mb-3">
            <label class="form-label fw-bold">Titre</label>
            <input type="text" class="form-control form-control-sm" id="detailTitleInput">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Description</label>
            <textarea class="form-control form-control-sm" id="detailDescription" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Statut</label>
            <span class="badge pv-status-badge" id="detailStatus">—</span>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Créateur</label>
            <div id="detailCreator">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Module</label>
            <div id="detailModule">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Créé le</label>
            <div id="detailDate">—</div>
          </div>
          <button class="btn btn-sm w-100 btn-pv-primary" id="btnUpdateInfo">
            <i class="bi bi-check-lg"></i> Mettre à jour les infos
          </button>
        </div>
      </div>

      <!-- Participants -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Participants</h6>
        </div>
        <div class="card-body small" id="detailParticipants">
          —
        </div>
      </div>

      <!-- Actions -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Actions</h6>
        </div>
        <div class="card-body">
          <button class="btn btn-sm btn-warning w-100 mb-2" id="btnReRecord">
            <i class="bi bi-arrow-repeat"></i> Re-enregistrer
          </button>
          <button class="btn btn-sm btn-danger w-100" id="btnDeletePv">
            <i class="bi bi-trash"></i> Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let pvId = null;
let pvData = null;

document.addEventListener('DOMContentLoaded', async () => {
  pvId = AdminURL.currentId();

  if (!pvId) {
    toast('PV non trouvé');
    window.history.back();
    return;
  }

  const result = await adminApiPost('admin_get_pv', { id: pvId });
  if (!result.success) {
    toast('Erreur: ' + result.message);
    window.history.back();
    return;
  }

  pvData = result.pv;
  loadPvDetail();

  // Button handlers
  document.getElementById('btnBackToList').addEventListener('click', () => AdminURL.go('pv'));
  document.getElementById('btnSaveContent').addEventListener('click', saveContent);
  document.getElementById('btnUpdateInfo').addEventListener('click', updateInfo);
  document.getElementById('btnReRecord').addEventListener('click', () => {
    window.location.href = AdminURL.page('pv-record', pvId);
  });
  document.getElementById('btnDeletePv').addEventListener('click', deletePv);
});

function loadPvDetail() {
  document.getElementById('detailTitle').textContent = pvData.titre;
  document.getElementById('detailTitleInput').value = pvData.titre;
  document.getElementById('detailDescription').value = pvData.description || '';
  document.getElementById('detailStatus').textContent = pvData.statut;
  document.getElementById('detailStatus').className = `badge bg-${pvData.statut === 'finalisé' ? 'success' : pvData.statut === 'enregistrement' ? 'warning' : 'secondary'}`;
  document.getElementById('detailCreator').textContent = (pvData.creator_prenom || '?') + ' ' + (pvData.creator_nom || '');
  document.getElementById('detailModule').textContent = pvData.module_nom ? pvData.module_nom + ' (' + pvData.module_code + ')' : '—';
  document.getElementById('detailDate').textContent = new Date(pvData.created_at).toLocaleString('fr-FR');
  document.getElementById('detailContent').textContent = pvData.contenu || '(Pas de contenu)';
  document.getElementById('editContent').value = pvData.contenu || '';

  // Participants
  const participants = pvData.participants || [];
  if (participants.length > 0) {
    document.getElementById('detailParticipants').innerHTML = 
      participants.map(p => `<div class="mb-2"><i class="bi bi-person-fill"></i> ${p.prenom} ${p.nom}</div>`).join('');
  }
}

async function saveContent() {
  const content = document.getElementById('editContent').value;
  const btn = document.currentTarget;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

  try {
    const r = await adminApiPost('admin_update_pv', {
      id: pvId,
      contenu: content,
    });

    if (r.success) {
      toast('Contenu sauvegardé');
      pvData.contenu = content;
      document.getElementById('detailContent').textContent = content;
    } else {
      toast(r.message || 'Erreur');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Sauvegarder';
  }
}

async function updateInfo() {
  const btn = document.currentTarget;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

  try {
    const r = await adminApiPost('admin_update_pv', {
      id: pvId,
      titre: document.getElementById('detailTitleInput').value,
      description: document.getElementById('detailDescription').value,
    });

    if (r.success) {
      toast('Infos mises à jour');
      pvData.titre = document.getElementById('detailTitleInput').value;
      pvData.description = document.getElementById('detailDescription').value;
      loadPvDetail();
    } else {
      toast(r.message || 'Erreur');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Mettre à jour les infos';
  }
}

async function deletePv() {
  if (!confirm('Supprimer ce PV? Cette action est irréversible.')) return;

  const r = await adminApiPost('admin_delete_pv', { id: pvId });
  if (r.success) {
    toast('PV supprimé');
    window.location.href = AdminURL.page('pv');
  } else {
    toast(r.message || 'Erreur');
  }
}
</script>
