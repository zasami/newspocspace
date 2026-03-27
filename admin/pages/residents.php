<?php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-badge"></i> Résidents</h4>
  <button class="btn btn-primary btn-sm" id="resAddBtn"><i class="bi bi-plus-lg"></i> Ajouter un résident</button>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
    <input type="text" class="form-control form-control-sm" id="resSearch" placeholder="Rechercher..." style="max-width:250px">
    <div class="form-check form-switch ms-auto">
      <input type="checkbox" class="form-check-input" id="resShowInactive">
      <label class="form-check-label small" for="resShowInactive">Afficher inactifs</label>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Chambre</th>
            <th>Étage</th>
            <th>Correspondant</th>
            <th>Code accès</th>
            <th>VIP</th>
            <th>Actif</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="resTableBody">
          <tr><td colspan="9" class="text-center text-muted py-3">Chargement...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal création/édition -->
<div class="modal fade" id="resModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title" id="resModalTitle">Nouveau résident</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <form id="resForm">
          <input type="hidden" id="resEditId">
          <h6 class="text-muted small mb-2"><i class="bi bi-person"></i> Résident</h6>
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label">Nom</label>
              <input type="text" class="form-control" id="resNom" required>
            </div>
            <div class="col-4">
              <label class="form-label">Prénom</label>
              <input type="text" class="form-control" id="resPrenom" required>
            </div>
            <div class="col-4">
              <label class="form-label">Date naissance</label>
              <input type="date" class="form-control" id="resDdn">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label">Chambre</label>
              <input type="text" class="form-control" id="resChambre">
            </div>
            <div class="col-4">
              <label class="form-label">Étage</label>
              <input type="text" class="form-control" id="resEtage">
            </div>
            <div class="col-4">
              <label class="form-label">Code accès</label>
              <input type="text" class="form-control" id="resCode" placeholder="Auto" readonly>
            </div>
          </div>
          <hr class="my-2">
          <h6 class="text-muted small mb-2"><i class="bi bi-person-lines-fill"></i> Correspondant / Tuteur</h6>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Nom</label>
              <input type="text" class="form-control" id="resCorrNom">
            </div>
            <div class="col-6">
              <label class="form-label">Prénom</label>
              <input type="text" class="form-control" id="resCorrPrenom">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="resCorrEmail" placeholder="email@exemple.com">
            </div>
            <div class="col-6">
              <label class="form-label">Téléphone</label>
              <input type="tel" class="form-control" id="resCorrTel">
            </div>
          </div>
          <hr class="my-2">
          <div class="form-check form-switch mb-2">
            <input type="checkbox" class="form-check-input" id="resVip">
            <label class="form-check-label" for="resVip">Table VIP</label>
          </div>
          <div class="mb-2" id="resMenuSpecialWrap" style="display:none">
            <label class="form-label">Menu spécial VIP</label>
            <textarea class="form-control" id="resMenuSpecial" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="resSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let modal = null;
    const modalEl = document.getElementById('resModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);

    const tbody = document.getElementById('resTableBody');

    // VIP toggle shows menu_special
    document.getElementById('resVip')?.addEventListener('change', (e) => {
        document.getElementById('resMenuSpecialWrap').style.display = e.target.checked ? 'block' : 'none';
    });

    async function load() {
        const search = document.getElementById('resSearch')?.value || '';
        const showInactive = document.getElementById('resShowInactive')?.checked ? 1 : 0;
        const res = await adminApiPost('admin_get_residents', { search, show_inactive: showInactive });
        if (!res.success) return;

        tbody.innerHTML = '';
        if (!res.residents?.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Aucun résident</td></tr>';
            return;
        }

        res.residents.forEach(r => {
            const tr = document.createElement('tr');
            if (!r.is_active) tr.style.opacity = '0.5';
            const corrName = [r.correspondant_prenom, r.correspondant_nom].filter(Boolean).join(' ');
            tr.innerHTML = `<td><strong>${escapeHtml(r.nom)}</strong></td>
                <td>${escapeHtml(r.prenom)}</td>
                <td>${escapeHtml(r.chambre || '-')}</td>
                <td>${escapeHtml(r.etage || '-')}</td>
                <td>${corrName ? escapeHtml(corrName) + (r.correspondant_email ? '<br><small class="text-muted">' + escapeHtml(r.correspondant_email) + '</small>' : '') : '-'}</td>
                <td>${r.code_acces ? '<code class="small">' + escapeHtml(r.code_acces) + '</code>' : '-'}</td>
                <td>${r.is_vip == 1 ? '<span class="badge bg-warning text-dark">VIP</span>' : '-'}</td>
                <td>${r.is_active == 1 ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>'}</td>
                <td></td>`;

            const actionTd = tr.lastElementChild;
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-sm btn-outline-primary me-1';
            editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            editBtn.addEventListener('click', () => openEdit(r));
            actionTd.appendChild(editBtn);

            const toggleBtn = document.createElement('button');
            toggleBtn.className = `btn btn-sm ${r.is_active == 1 ? 'btn-outline-danger' : 'btn-outline-success'}`;
            toggleBtn.innerHTML = r.is_active == 1 ? '<i class="bi bi-person-slash"></i>' : '<i class="bi bi-person-check"></i>';
            toggleBtn.addEventListener('click', async () => {
                await adminApiPost('admin_toggle_resident', { id: r.id });
                load();
            });
            actionTd.appendChild(toggleBtn);

            tbody.appendChild(tr);
        });
    }

    function openEdit(r) {
        document.getElementById('resModalTitle').textContent = r ? 'Modifier le résident' : 'Nouveau résident';
        document.getElementById('resEditId').value = r?.id || '';
        document.getElementById('resNom').value = r?.nom || '';
        document.getElementById('resPrenom').value = r?.prenom || '';
        document.getElementById('resDdn').value = r?.date_naissance || '';
        document.getElementById('resChambre').value = r?.chambre || '';
        document.getElementById('resEtage').value = r?.etage || '';
        document.getElementById('resCode').value = r?.code_acces || '';
        document.getElementById('resCorrNom').value = r?.correspondant_nom || '';
        document.getElementById('resCorrPrenom').value = r?.correspondant_prenom || '';
        document.getElementById('resCorrEmail').value = r?.correspondant_email || '';
        document.getElementById('resCorrTel').value = r?.correspondant_telephone || '';
        document.getElementById('resVip').checked = r?.is_vip == 1;
        document.getElementById('resMenuSpecial').value = r?.menu_special || '';
        document.getElementById('resMenuSpecialWrap').style.display = r?.is_vip == 1 ? 'block' : 'none';
        modal?.show();
    }

    document.getElementById('resAddBtn')?.addEventListener('click', () => openEdit(null));

    document.getElementById('resSaveBtn')?.addEventListener('click', async () => {
        const id = document.getElementById('resEditId').value;
        const data = {
            nom: document.getElementById('resNom').value,
            prenom: document.getElementById('resPrenom').value,
            date_naissance: document.getElementById('resDdn').value,
            chambre: document.getElementById('resChambre').value,
            etage: document.getElementById('resEtage').value,
            correspondant_nom: document.getElementById('resCorrNom').value,
            correspondant_prenom: document.getElementById('resCorrPrenom').value,
            correspondant_email: document.getElementById('resCorrEmail').value,
            correspondant_telephone: document.getElementById('resCorrTel').value,
            is_vip: document.getElementById('resVip').checked ? 1 : 0,
            menu_special: document.getElementById('resMenuSpecial').value,
        };

        let res;
        if (id) {
            data.id = id;
            res = await adminApiPost('admin_update_resident', data);
        } else {
            res = await adminApiPost('admin_create_resident', data);
        }

        if (res.success) {
            showToast(res.message || 'OK', 'success');
            modal?.hide();
            load();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    let searchTimer = null;
    document.getElementById('resSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 300);
    });
    document.getElementById('resShowInactive')?.addEventListener('change', load);

    load();
})();
</script>
