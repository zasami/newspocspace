<?php
$userId = $_GET['id'] ?? '';
if (!$userId) {
    echo '<div class="alert alert-danger">ID utilisateur manquant</div>';
    return;
}
?>
<a href="<?= admin_url('users') ?>" class="btn btn-outline-secondary btn-sm mb-3">
  <i class="bi bi-arrow-left"></i> Retour
</a>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Informations du collaborateur</h6></div>
      <div class="card-body">
        <form id="editUserForm">
          <input type="hidden" id="editUserId" value="<?= h($userId) ?>">
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Prénom</label>
              <input type="text" class="form-control" id="editPrenom" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input type="text" class="form-control" id="editNom" required>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="editEmail" required>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Fonction</label>
              <div class="zs-select" id="editFonction" data-placeholder="— Choisir —"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Taux %</label>
              <input type="number" class="form-control" id="editTaux" min="20" max="100" step="5">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Rôle</label>
              <div class="zs-select" id="editRole" data-placeholder="Collaborateur"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contrat</label>
              <div class="zs-select" id="editContrat" data-placeholder="CDI"></div>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Téléphone</label>
              <input type="tel" class="form-control" id="editTelephone">
            </div>
            <div class="col-md-6">
              <label class="form-label">N° Employé</label>
              <input type="text" class="form-control" id="editEmployeeId">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Date d'entrée</label>
              <input type="date" class="form-control" id="editDateEntree">
            </div>
            <div class="col-md-4">
              <label class="form-label">Fin contrat</label>
              <input type="date" class="form-control" id="editDateFin">
            </div>
            <div class="col-md-4">
              <label class="form-label">Solde vacances</label>
              <input type="number" class="form-control" id="editVacances" step="0.5">
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-2">
            <i class="bi bi-check-lg"></i> Enregistrer
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Avatar -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Photo</h6></div>
      <div class="card-body text-center">
        <div id="avatarPreview" class="ue-avatar-preview mb-3">
          <span class="ue-avatar-initials" id="avatarInitials"></span>
        </div>
        <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
        <button class="btn btn-outline-primary btn-sm" id="avatarUploadBtn">
          <i class="bi bi-camera"></i> Changer la photo
        </button>
        <button class="btn btn-outline-danger btn-sm d-none" id="avatarDeleteBtn">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>

    <!-- Sécurité -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Sécurité</h6></div>
      <div class="card-body">
        <button class="btn btn-warning btn-sm" id="resetPwdBtn">
          <i class="bi bi-key"></i> Réinitialiser le mot de passe
        </button>
        <p class="small text-muted mt-2" id="newPwdDisplay"></p>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="card border-danger border-opacity-25">
      <div class="card-header bg-danger bg-opacity-10"><h6 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle"></i> Zone dangereuse</h6></div>
      <div class="card-body">
        <p class="small text-muted mb-2" id="userStatusText">Chargement...</p>
        <button class="btn btn-outline-danger btn-sm" id="toggleUserBtn">
          <i class="bi bi-person-slash"></i> <span id="toggleUserLabel">Désactiver</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
async function initUsereditPage() {
    document.querySelectorAll('#editPrenom, #editNom').forEach(
        el => el.addEventListener('blur', (e) => { e.target.value = e.target.value.trim().replace(/\b\w/g, c => c.toUpperCase()); })
    );

    const id = document.getElementById('editUserId').value;
    if (!id) return;

    const res = await adminApiPost('admin_get_user', { id });
    if (!res.success || !res.user) return;

    const u = res.user;
    document.getElementById('editPrenom').value = u.prenom || '';
    document.getElementById('editNom').value = u.nom || '';
    document.getElementById('editEmail').value = u.email || '';
    document.getElementById('editTaux').value = u.taux || 100;
    document.getElementById('editTelephone').value = u.telephone || '';
    document.getElementById('editEmployeeId').value = u.employee_id || '';
    document.getElementById('editDateEntree').value = u.date_entree || '';
    document.getElementById('editDateFin').value = u.date_fin_contrat || '';
    document.getElementById('editVacances').value = u.solde_vacances || 0;

    // Load fonctions from DB and init zerdaSelects
    const refs = await adminApiPost('admin_get_planning_refs');
    const fonctions = refs.fonctions || [];

    zerdaSelect.init('#editFonction', [
        { value: '', label: '— Aucune —' },
        ...fonctions.map(f => ({ value: f.id, label: f.nom || f.code }))
    ], { value: u.fonction_id || '', search: fonctions.length > 6 });

    zerdaSelect.init('#editRole', [
        { value: 'collaborateur', label: 'Collaborateur' },
        { value: 'responsable', label: 'Responsable' },
        { value: 'admin', label: 'Admin' },
        { value: 'direction', label: 'Direction' },
    ], { value: u.role || 'collaborateur' });

    zerdaSelect.init('#editContrat', [
        { value: 'CDI', label: 'CDI' },
        { value: 'CDD', label: 'CDD' },
        { value: 'stagiaire', label: 'Stagiaire' },
        { value: 'civiliste', label: 'Civiliste' },
        { value: 'interim', label: 'Intérim' },
    ], { value: u.type_contrat || 'CDI' });

    document.getElementById('editUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            id,
            prenom: document.getElementById('editPrenom').value,
            nom: document.getElementById('editNom').value,
            email: document.getElementById('editEmail').value,
            taux: document.getElementById('editTaux').value,
            role: zerdaSelect.getValue('#editRole') || 'collaborateur',
            type_contrat: zerdaSelect.getValue('#editContrat') || 'CDI',
            fonction_id: zerdaSelect.getValue('#editFonction') || '',
            telephone: document.getElementById('editTelephone').value,
            employee_id: document.getElementById('editEmployeeId').value,
            date_entree: document.getElementById('editDateEntree').value,
            date_fin_contrat: document.getElementById('editDateFin').value,
            solde_vacances: document.getElementById('editVacances').value,
        };

        const res = await adminApiPost('admin_update_user', data);
        if (res.success) {
            showToast('Collaborateur mis à jour', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    document.getElementById('resetPwdBtn')?.addEventListener('click', async () => {
        if (!await adminConfirm({ title: 'Réinitialiser le mot de passe', text: 'Un mot de passe temporaire (24h) sera envoyé par email.', icon: 'bi-key', type: 'warning', okText: 'Réinitialiser' })) return;
        const res = await adminApiPost('admin_reset_user_password', { id });
        if (res.success) {
            document.getElementById('newPwdDisplay').textContent = res.message;
            showToast(res.message, 'success');
        }
    });

    // Toggle active/inactive
    const isActive = u.is_active == 1;
    function updateToggleUI(active) {
        const label = document.getElementById('toggleUserLabel');
        const btn = document.getElementById('toggleUserBtn');
        const txt = document.getElementById('userStatusText');
        if (active) {
            txt.textContent = 'Ce collaborateur est actif. La désactivation bloquera son accès à zerdaTime.';
            label.textContent = 'Désactiver le compte';
            btn.innerHTML = '<i class="bi bi-person-slash"></i> ' + label.textContent;
            btn.classList.remove('btn-outline-success');
            btn.classList.add('btn-outline-danger');
        } else {
            txt.textContent = 'Ce collaborateur est désactivé. Il ne peut plus se connecter.';
            label.textContent = 'Réactiver le compte';
            btn.innerHTML = '<i class="bi bi-person-check"></i> ' + label.textContent;
            btn.classList.remove('btn-outline-danger');
            btn.classList.add('btn-outline-success');
        }
    }
    updateToggleUI(isActive);

    document.getElementById('toggleUserBtn')?.addEventListener('click', async () => {
        const currentlyActive = document.getElementById('toggleUserLabel').textContent.includes('Désactiver');
        const confirmOpts = currentlyActive
            ? { title: 'Désactiver ce collaborateur ?', text: 'Il ne pourra plus se connecter à zerdaTime.', icon: 'bi-person-slash', type: 'danger', okText: 'Désactiver' }
            : { title: 'Réactiver ce collaborateur ?', text: 'Il pourra à nouveau se connecter.', icon: 'bi-person-check', type: 'success', okText: 'Réactiver' };
        if (!await adminConfirm(confirmOpts)) return;
        const res = await adminApiPost('admin_toggle_user', { id });
        if (res.success) {
            updateToggleUI(!currentlyActive);
            showToast(currentlyActive ? 'Collaborateur désactivé' : 'Collaborateur réactivé', 'success');
        }
    });

    // ── Avatar ──
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarInitials = document.getElementById('avatarInitials');
    const avatarFileInput = document.getElementById('avatarFileInput');
    const avatarDeleteBtn = document.getElementById('avatarDeleteBtn');

    function setAvatarPreview(url) {
        if (url) {
            avatarPreview.innerHTML = `<img src="${url}" alt="Avatar" class="ue-avatar-img">`;
            avatarDeleteBtn.classList.remove('d-none');
        } else {
            const p = document.getElementById('editPrenom')?.value || '';
            const n = document.getElementById('editNom')?.value || '';
            const ini = ((p[0] || '') + (n[0] || '')).toUpperCase();
            avatarPreview.innerHTML = `<span class="ue-avatar-initials">${escapeHtml(ini)}</span>`;
            avatarDeleteBtn.classList.add('d-none');
        }
    }

    // Set initial avatar
    setAvatarPreview(u.photo || '');

    document.getElementById('avatarUploadBtn').addEventListener('click', () => avatarFileInput.click());

    avatarFileInput.addEventListener('change', async () => {
        const file = avatarFileInput.files[0];
        if (!file) return;

        // Instant local preview
        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Avatar" class="ue-avatar-img">`;
        };
        reader.readAsDataURL(file);

        // Upload
        const fd = new FormData();
        fd.append('action', 'admin_upload_user_avatar');
        fd.append('user_id', id);
        fd.append('avatar', file);

        try {
            const resp = await fetch('/zerdatime/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__ZT_ADMIN__?.csrfToken || '' },
                body: fd,
            });
            const res2 = await resp.json();
            if (res2.success) {
                setAvatarPreview(res2.photo_url);
                showToast('Photo mise à jour', 'success');
            } else {
                showToast(res2.message || 'Erreur upload', 'error');
                setAvatarPreview(u.photo || '');
            }
        } catch (e) {
            showToast('Erreur réseau', 'error');
            setAvatarPreview(u.photo || '');
        }
        avatarFileInput.value = '';
    });

    avatarDeleteBtn.addEventListener('click', async () => {
        if (!await adminConfirm({ title: 'Supprimer la photo', text: 'Voulez-vous supprimer la photo de ce collaborateur ?', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
        const res2 = await adminApiPost('admin_delete_user_avatar', { user_id: id });
        if (res2.success) {
            u.photo = '';
            setAvatarPreview('');
            showToast('Photo supprimée', 'success');
        }
    });
}

window.initUsereditPage = initUsereditPage;
</script>

<style>
.ue-avatar-preview {
    width: 96px; height: 96px; border-radius: 50%; margin: 0 auto;
    background: var(--zt-teal, #2a9d8f); display: flex; align-items: center; justify-content: center;
    overflow: hidden; border: 3px solid var(--cl-border, #e0e0e0);
}
.ue-avatar-preview .ue-avatar-img {
    width: 100%; height: 100%; object-fit: cover;
}
.ue-avatar-preview .ue-avatar-initials {
    color: #fff; font-size: 1.8rem; font-weight: 700;
}
</style>
