<!-- PV Detail Page -->
<?php $pvId = $_GET['id'] ?? ''; ?>

<!-- Back -->
<a href="<?= admin_url('pv') ?>" class="btn btn-outline-secondary btn-sm mb-3">
  <i class="bi bi-arrow-left"></i> Liste des PV
</a>

<!-- Header -->
<div class="card mb-3" id="pvdHeader">
  <div class="card-body d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div class="flex-grow-1" style="min-width:0">
      <h1 class="h5 fw-bold mb-2" id="detailTitle">Chargement...</h1>
      <div class="d-flex flex-wrap align-items-center gap-1" id="detailMeta">
        <span class="badge text-bg-secondary" id="detailStatus">&mdash;</span>
      </div>
    </div>
    <div class="d-flex gap-2 flex-shrink-0 align-items-start">
      <button class="btn btn-outline-secondary btn-sm" id="btnPrintPv" title="Imprimer">
        <i class="bi bi-printer"></i>
      </button>
      <button class="btn btn-outline-secondary btn-sm" id="btnReRecord" title="Re-enregistrer">
        <i class="bi bi-mic"></i> Enregistrer
      </button>
      <button class="btn btn-primary btn-sm" id="btnFinalize" style="display:none">
        <i class="bi bi-check-circle"></i> Finaliser
      </button>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Main content col -->
  <div class="col-lg-8">
    <!-- Content display -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between py-2">
        <h6 class="mb-0 fw-bold small d-flex align-items-center gap-1">
          <i class="bi bi-file-text"></i> Contenu du PV
        </h6>
        <button class="btn btn-outline-primary btn-sm rounded-pill" id="btnToggleEdit">
          <i class="bi bi-pencil"></i> Modifier
        </button>
      </div>
      <div class="card-body" id="detailContent" style="min-height:200px;white-space:pre-wrap;word-break:break-word;line-height:1.75;font-size:.92rem"></div>
    </div>

    <!-- Edit panel (hidden) -->
    <div class="card mb-3" id="editPanel" style="display:none">
      <div class="card-header bg-body-secondary py-2">
        <h6 class="mb-0 fw-bold small d-flex align-items-center gap-1">
          <i class="bi bi-pencil-square"></i> Édition du contenu
        </h6>
      </div>
      <div class="card-body p-0">
        <textarea class="form-control border-0 rounded-0" id="editContent" placeholder="Contenu du PV..." style="min-height:250px;resize:vertical;font-size:.9rem;line-height:1.7"></textarea>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary btn-sm rounded-pill px-3" id="btnSaveContent">
          <i class="bi bi-check-lg"></i> Sauvegarder
        </button>
        <button class="btn btn-outline-secondary btn-sm" id="btnCancelEdit">Annuler</button>
      </div>
    </div>
  </div>

  <!-- Sidebar col -->
  <div class="col-lg-4">
    <!-- Informations -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <small class="fw-bold text-uppercase text-secondary d-flex align-items-center gap-1">
          <i class="bi bi-info-circle"></i> Informations
        </small>
      </div>
      <div class="card-body">
        <div class="list-group list-group-flush">
          <div class="list-group-item px-0 d-flex align-items-start gap-2">
            <small class="fw-semibold text-uppercase text-muted" style="min-width:70px;flex-shrink:0;padding-top:5px;font-size:.72rem;letter-spacing:.3px">Titre</small>
            <div class="flex-grow-1">
              <input type="text" class="form-control form-control-sm" id="detailTitleInput">
            </div>
          </div>
          <div class="list-group-item px-0 d-flex align-items-start gap-2">
            <small class="fw-semibold text-uppercase text-muted" style="min-width:70px;flex-shrink:0;padding-top:5px;font-size:.72rem;letter-spacing:.3px">Desc.</small>
            <div class="flex-grow-1">
              <textarea class="form-control form-control-sm" id="detailDescription" rows="2"></textarea>
            </div>
          </div>
          <div class="list-group-item px-0 d-flex align-items-start gap-2">
            <small class="fw-semibold text-uppercase text-muted" style="min-width:70px;flex-shrink:0;padding-top:5px;font-size:.72rem;letter-spacing:.3px">Créateur</small>
            <span class="flex-grow-1" id="detailCreator">&mdash;</span>
          </div>
          <div class="list-group-item px-0 d-flex align-items-start gap-2">
            <small class="fw-semibold text-uppercase text-muted" style="min-width:70px;flex-shrink:0;padding-top:5px;font-size:.72rem;letter-spacing:.3px">Module</small>
            <span class="flex-grow-1" id="detailModule">&mdash;</span>
          </div>
          <div class="list-group-item px-0 border-bottom-0 d-flex align-items-start gap-2">
            <small class="fw-semibold text-uppercase text-muted" style="min-width:70px;flex-shrink:0;padding-top:5px;font-size:.72rem;letter-spacing:.3px">Date</small>
            <span class="flex-grow-1" id="detailDate">&mdash;</span>
          </div>
        </div>
        <button class="btn btn-primary btn-sm w-100 mt-3 rounded-pill" id="btnUpdateInfo">
          <i class="bi bi-check-lg"></i> Mettre à jour
        </button>
      </div>
    </div>

    <!-- Participants -->
    <div class="card mb-3">
      <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <small class="fw-bold text-uppercase text-secondary d-flex align-items-center gap-1">
          <i class="bi bi-people"></i> Participants
        </small>
        <small class="text-muted" id="detailParticipantsCount"></small>
      </div>
      <div class="card-body" id="detailParticipants">
        <span class="text-muted small">Aucun participant</span>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="card border-danger mb-3">
      <div class="card-header bg-danger bg-opacity-10 text-danger py-2">
        <small class="fw-bold text-uppercase d-flex align-items-center gap-1">
          <i class="bi bi-exclamation-triangle"></i> Zone danger
        </small>
      </div>
      <div class="card-body">
        <button class="btn btn-outline-danger btn-sm w-100 rounded-pill" id="btnDeletePv">
          <i class="bi bi-trash"></i> Supprimer ce PV
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const pvId = '<?= h($pvId) ?>';
    let pvData = null;

    async function initPvdetailPage() {
        if (!pvId) { showToast('PV non trouvé', 'error'); return; }

        const result = await adminApiPost('admin_get_pv', { id: pvId });
        if (!result.success) {
            showToast('Erreur: ' + (result.message || 'PV non trouvé'), 'error');
            return;
        }

        pvData = result.pv;
        renderDetail();

        // Toggle edit
        document.getElementById('btnToggleEdit').addEventListener('click', () => {
            const panel = document.getElementById('editPanel');
            const isHidden = panel.style.display === 'none';
            panel.style.display = isHidden ? '' : 'none';
            document.getElementById('editContent').value = pvData.contenu || '';
            if (isHidden) document.getElementById('editContent').focus();
        });
        document.getElementById('btnCancelEdit').addEventListener('click', () => {
            document.getElementById('editPanel').style.display = 'none';
        });

        document.getElementById('btnSaveContent').addEventListener('click', saveContent);
        document.getElementById('btnUpdateInfo').addEventListener('click', updateInfo);
        document.getElementById('btnReRecord').addEventListener('click', () => {
            window.location.href = AdminURL.page('pv-record', pvId);
        });
        document.getElementById('btnDeletePv').addEventListener('click', deletePv);
        document.getElementById('btnFinalize')?.addEventListener('click', finalizePv);

        // Print
        document.getElementById('btnPrintPv')?.addEventListener('click', () => {
            const title = escapeHtml(pvData.titre);
            const content = document.getElementById('detailContent')?.innerText || '';
            const win = window.open('', '_blank');
            win.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
              '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;color:#333;line-height:1.7}' +
              'h1{font-size:1.3rem;border-bottom:2px solid #333;padding-bottom:8px}' +
              '.info{color:#666;font-size:0.85rem;margin-bottom:1.5rem}' +
              '@media print{body{margin:20px}}</style></head>' +
              '<body><h1>' + title + '</h1>' +
              '<div class="info">Date: ' + new Date(pvData.created_at).toLocaleDateString('fr-CH') + ' — SpocSpace</div>' +
              '<pre style="white-space:pre-wrap;font-family:inherit">' + escapeHtml(content) + '</pre></body></html>');
            win.document.close();
            win.print();
        });
    }

    function renderDetail() {
        document.getElementById('detailTitle').textContent = pvData.titre;
        document.getElementById('detailTitleInput').value = pvData.titre;
        document.getElementById('detailDescription').value = pvData.description || '';

        // Status badge
        const statusClasses = {
            'finalisé': 'text-bg-success',
            'enregistrement': 'text-bg-warning',
            'brouillon': 'text-bg-secondary'
        };
        const statusEl = document.getElementById('detailStatus');
        statusEl.textContent = pvData.statut;
        statusEl.className = 'badge ' + (statusClasses[pvData.statut] || 'text-bg-secondary');

        // Show finalize button if not finalized
        const finalizeBtn = document.getElementById('btnFinalize');
        finalizeBtn.style.display = pvData.statut !== 'finalisé' ? '' : 'none';

        // Meta badges
        const metaEl = document.getElementById('detailMeta');
        let metaHtml = statusEl.outerHTML;
        if (pvData.module_code) metaHtml += ' <span class="badge text-bg-light border">' + escapeHtml(pvData.module_code) + '</span>';
        if (pvData.etage_code) metaHtml += ' <span class="badge text-bg-light border">' + escapeHtml(pvData.etage_code) + '</span>';
        metaHtml += ' <small class="text-muted">' + new Date(pvData.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) + '</small>';
        metaEl.innerHTML = metaHtml;

        // Info sidebar
        document.getElementById('detailCreator').textContent = (pvData.creator_prenom || '?') + ' ' + (pvData.creator_nom || '');
        document.getElementById('detailModule').textContent = pvData.module_nom ? pvData.module_nom + ' (' + pvData.module_code + ')' : '—';
        document.getElementById('detailDate').textContent = new Date(pvData.created_at).toLocaleString('fr-FR');

        // Content
        document.getElementById('detailContent').textContent = pvData.contenu || '';

        // Participants
        const participants = pvData.participants || [];
        const partEl = document.getElementById('detailParticipants');
        const countEl = document.getElementById('detailParticipantsCount');
        if (countEl) countEl.textContent = participants.length > 0 ? participants.length : '';

        if (participants.length > 0) {
            partEl.innerHTML = '<div class="d-flex flex-wrap gap-1">' +
                participants.map(p =>
                    '<span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 fw-normal py-1 px-2"><i class="bi bi-person-fill"></i> ' + escapeHtml(p.prenom) + ' ' + escapeHtml(p.nom) + '</span>'
                ).join('') +
            '</div>';
        } else {
            partEl.innerHTML = '<span class="text-muted small">Aucun participant</span>';
        }
    }

    async function saveContent() {
        const content = document.getElementById('editContent').value;
        const btn = document.getElementById('btnSaveContent');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const r = await adminApiPost('admin_update_pv', { id: pvId, contenu: content });
            if (r.success) {
                showToast('Contenu sauvegardé', 'success');
                pvData.contenu = content;
                document.getElementById('detailContent').textContent = content;
                document.getElementById('editPanel').style.display = 'none';
            } else {
                showToast(r.message || 'Erreur', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Sauvegarder';
        }
    }

    async function updateInfo() {
        const btn = document.getElementById('btnUpdateInfo');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const r = await adminApiPost('admin_update_pv', {
                id: pvId,
                titre: document.getElementById('detailTitleInput').value,
                description: document.getElementById('detailDescription').value,
            });
            if (r.success) {
                showToast('Infos mises à jour', 'success');
                pvData.titre = document.getElementById('detailTitleInput').value;
                pvData.description = document.getElementById('detailDescription').value;
                renderDetail();
            } else {
                showToast(r.message || 'Erreur', 'error');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Mettre à jour';
        }
    }

    async function finalizePv() {
        if (!await adminConfirm({ title: 'Finaliser le PV', text: 'Le PV sera marqué comme finalisé. Vous pourrez toujours le modifier.', icon: 'bi-check-circle', type: 'success', okText: 'Finaliser' })) return;
        const r = await adminApiPost('admin_finalize_pv', { id: pvId });
        if (r.success) {
            showToast('PV finalisé', 'success');
            pvData.statut = 'finalisé';
            renderDetail();
        } else {
            showToast(r.message || 'Erreur', 'error');
        }
    }

    async function deletePv() {
        if (!await adminConfirm({ title: 'Supprimer le PV', text: 'Cette action est irréversible.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
        const r = await adminApiPost('admin_delete_pv', { id: pvId });
        if (r.success) {
            showToast('PV supprimé', 'success');
            AdminURL.go('pv');
        } else {
            showToast(r.message || 'Erreur', 'error');
        }
    }

    window.initPvdetailPage = initPvdetailPage;
})();
</script>
