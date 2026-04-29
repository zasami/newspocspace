<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$initResidents = Db::fetchAll(
    "SELECT * FROM residents WHERE is_active = 1 ORDER BY nom, prenom"
);
?>
<style>
.res-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: #f0ede8; }
.res-avatar-initials { width: 36px; height: 36px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 600; flex-shrink: 0; }
.res-row { cursor: pointer; }
.res-row:hover { background: rgba(25,25,24,.02); }
.res-photo-zone { width: 100px; height: 100px; border-radius: 50%; border: 2px dashed var(--cl-border); display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; position: relative; background: var(--cl-bg); margin: 0 auto 12px; }
.res-photo-zone img { width: 100%; height: 100%; object-fit: cover; }
.res-photo-zone:hover { border-color: var(--cl-accent); }
.res-photo-zone .res-photo-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.4); color: #fff; display: none; align-items: center; justify-content: center; font-size: 1.2rem; }
.res-photo-zone:hover .res-photo-overlay { display: flex; }
.res-photo-del { position: absolute; top: -4px; right: -4px; width: 22px; height: 22px; border-radius: 50%; background: #C53030; color: #fff; border: 2px solid #fff; font-size: .6rem; cursor: pointer; display: none; align-items: center; justify-content: center; z-index: 2; }
.res-photo-zone:hover .res-photo-del { display: flex; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-badge"></i> Résidents</h4>
  <button class="btn btn-primary btn-sm" id="resAddBtn"><i class="bi bi-plus-lg"></i> Ajouter un résident</button>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
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
            <th style="width:50px"></th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Chambre</th>
            <th>Étage</th>
            <th>Correspondant</th>
            <th>Code accès</th>
            <th>VIP</th>
            <th>Actif</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody id="resTableBody"></tbody>
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
          <!-- Photo -->
          <div class="res-photo-zone" id="resPhotoZone" title="Cliquer pour changer la photo">
            <div class="res-photo-overlay"><i class="bi bi-camera"></i></div>
            <button type="button" class="res-photo-del" id="resPhotoDel" title="Supprimer la photo"><i class="bi bi-x"></i></button>
            <span id="resPhotoPlaceholder" style="font-size:2rem;color:var(--cl-text-muted)"><i class="bi bi-camera"></i></span>
          </div>
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
    const ssrResidents = <?= json_encode(array_values($initResidents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let allResidents = ssrResidents;
    let modal = null;
    let currentEditResident = null;
    const modalEl = document.getElementById('resModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);

    const tbody = document.getElementById('resTableBody');

    document.getElementById('resVip')?.addEventListener('change', (e) => {
        document.getElementById('resMenuSpecialWrap').style.display = e.target.checked ? 'block' : 'none';
    });

    // ── Photo URL helper (plain, no encryption) ──
    function photoUrl(r) {
        if (!r.photo_path) return null;
        return '/newspocspace/admin/api.php?action=admin_serve_resident_photo&id=' + encodeURIComponent(r.id) + '&t=' + (r.updated_at || Date.now());
    }

    // ── Render table ──
    function renderResidents(residents) {
        allResidents = residents;
        tbody.innerHTML = '';
        if (!residents?.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Aucun résident</td></tr>';
            return;
        }

        residents.forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'res-row';
            if (!r.is_active) tr.style.opacity = '0.5';

            const initials = ((r.prenom?.[0] || '') + (r.nom?.[0] || '')).toUpperCase();
            const corrName = [r.correspondant_prenom, r.correspondant_nom].filter(Boolean).join(' ');
            const pUrl = photoUrl(r);

            tr.innerHTML =
                '<td>' + (pUrl
                    ? '<img class="res-avatar" src="' + escapeHtml(pUrl) + '" alt="" onerror="this.style.display=\'none\'">'
                    : '<div class="res-avatar-initials">' + escapeHtml(initials) + '</div>') + '</td>' +
                '<td><strong>' + escapeHtml(r.nom) + '</strong></td>' +
                '<td>' + escapeHtml(r.prenom) + '</td>' +
                '<td>' + escapeHtml(r.chambre || '-') + '</td>' +
                '<td>' + escapeHtml(r.etage || '-') + '</td>' +
                '<td>' + (corrName ? escapeHtml(corrName) + (r.correspondant_email ? '<br><small class="text-muted">' + escapeHtml(r.correspondant_email) + '</small>' : '') : '-') + '</td>' +
                '<td>' + (r.code_acces ? '<code class="small">' + escapeHtml(r.code_acces) + '</code>' : '-') + '</td>' +
                '<td>' + (r.is_vip == 1 ? '<span class="badge bg-warning text-dark">VIP</span>' : '-') + '</td>' +
                '<td>' + (r.is_active == 1 ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>') + '</td>' +
                '<td class="res-actions"></td>';

            // Click on row → open edit
            tr.addEventListener('click', (e) => {
                if (e.target.closest('.res-actions')) return;
                openEdit(r);
            });

            // Toggle button in actions col
            const actionTd = tr.querySelector('.res-actions');
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'btn btn-sm ' + (r.is_active == 1 ? 'btn-outline-danger' : 'btn-outline-success');
            toggleBtn.innerHTML = r.is_active == 1 ? '<i class="bi bi-person-slash"></i>' : '<i class="bi bi-person-check"></i>';
            toggleBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await adminApiPost('admin_toggle_resident', { id: r.id });
                load();
            });
            actionTd.appendChild(toggleBtn);

            tbody.appendChild(tr);
        });
    }

    async function load() {
        const search = document.getElementById('topbarSearchInput')?.value || '';
        const showInactive = document.getElementById('resShowInactive')?.checked ? 1 : 0;
        const res = await adminApiPost('admin_get_residents', { search, show_inactive: showInactive });
        if (!res.success) return;
        renderResidents(res.residents);
    }

    // ── Modal open/edit ──
    function openEdit(r) {
        currentEditResident = r;
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

        // Photo in modal
        const zone = document.getElementById('resPhotoZone');
        const placeholder = document.getElementById('resPhotoPlaceholder');
        const delBtn = document.getElementById('resPhotoDel');
        zone.querySelectorAll('img').forEach(i => i.remove());
        placeholder.style.display = '';
        delBtn.style.display = 'none';

        const pUrl = photoUrl(r);
        if (r && pUrl) {
            const img = document.createElement('img');
            img.src = pUrl;
            img.onerror = () => img.remove();
            zone.insertBefore(img, zone.firstChild);
            placeholder.style.display = 'none';
            delBtn.style.display = '';
        }

        modal?.show();
    }

    document.getElementById('resAddBtn')?.addEventListener('click', () => openEdit(null));

    // ── Photo upload (click zone) — plain upload, no E2EE ──
    document.getElementById('resPhotoZone')?.addEventListener('click', (e) => {
        if (e.target.closest('.res-photo-del')) return;
        const resId = document.getElementById('resEditId').value;
        if (!resId) { showToast('Enregistrez d\'abord le résident', 'error'); return; }

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) return;

            const r = currentEditResident;
            if (!r) return;

            const fd = new FormData();
            fd.append('action', 'admin_upload_resident_photo');
            fd.append('file', file);
            fd.append('resident_id', resId);

            const csrfToken = (window.__SS_ADMIN__?.csrfToken || '');
            const resp = await fetch('/newspocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: fd
            });
            const res = await resp.json();

            if (res.success) {
                r.photo_path = 'set';
                r.photo_iv = null;

                // Update modal preview
                const zone = document.getElementById('resPhotoZone');
                zone.querySelectorAll('img').forEach(i => i.remove());
                const img = document.createElement('img');
                img.src = photoUrl(r);
                zone.insertBefore(img, zone.firstChild);
                document.getElementById('resPhotoPlaceholder').style.display = 'none';
                document.getElementById('resPhotoDel').style.display = '';

                showToast('Photo enregistrée', 'success');
                load();
            } else {
                showToast(res.message || 'Erreur', 'error');
            }
        });
        input.click();
    });

    // ── Photo delete ──
    document.getElementById('resPhotoDel')?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const resId = document.getElementById('resEditId').value;
        if (!resId) return;
        if (!await adminConfirm({ title: 'Supprimer la photo ?', text: 'Cette action est irréversible.', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer' })) return;

        const res = await adminApiPost('admin_delete_resident_photo', { id: resId });
        if (res.success) {
            const zone = document.getElementById('resPhotoZone');
            zone.querySelectorAll('img').forEach(i => i.remove());
            document.getElementById('resPhotoPlaceholder').style.display = '';
            document.getElementById('resPhotoDel').style.display = 'none';
            if (currentEditResident) {
                currentEditResident.photo_path = null;
                currentEditResident.photo_iv = null;
            }
            showToast('Photo supprimée', 'success');
            load();
        }
    });

    // ── Save ──
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
    document.getElementById('topbarSearchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 300);
    });
    document.getElementById('resShowInactive')?.addEventListener('change', load);

    renderResidents(ssrResidents);

    window.initResidentsPage = () => {};
})();
</script>
