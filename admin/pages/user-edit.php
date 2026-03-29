<?php
$userId = $_GET['id'] ?? '';
if (!$userId) {
    echo '<div class="alert alert-danger">ID utilisateur manquant</div>';
    return;
}

$editUser = Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id WHERE u.id = ?",
    [$userId]
);
if (!$editUser) {
    echo '<div class="alert alert-danger">Utilisateur non trouvé</div>';
    return;
}
unset($editUser['password'], $editUser['reset_token'], $editUser['reset_expires']);
$editUser['modules'] = Db::fetchAll(
    "SELECT m.id, m.nom, m.code, um.is_principal FROM user_modules um JOIN modules m ON m.id = um.module_id WHERE um.user_id = ?",
    [$userId]
);

$editFonctions = Db::fetchAll("SELECT id, code, nom, ordre FROM fonctions ORDER BY ordre");
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
              <input type="text" class="form-control" id="editPrenom" required value="<?= h($editUser['prenom'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input type="text" class="form-control" id="editNom" required value="<?= h($editUser['nom'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="editEmail" required value="<?= h($editUser['email'] ?? '') ?>">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Fonction</label>
              <div class="zs-select" id="editFonction" data-placeholder="— Choisir —"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Taux %</label>
              <input type="number" class="form-control" id="editTaux" min="20" max="100" step="5" value="<?= h($editUser['taux'] ?? 100) ?>">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Rôle</label>
              <div class="zs-select" id="editRole" data-placeholder="Collaborateur"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Contrat</label>
              <div class="zs-select" id="editContrat" data-placeholder="CDI"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Type employé</label>
              <div class="zs-select" id="editTypeEmploye" data-placeholder="Interne"></div>
            </div>
          </div>
          <div class="card card-body bg-light p-2 mb-2" id="editExterneOverrides" style="display:<?= ($editUser['type_employe'] ?? '') === 'externe' ? 'block' : 'none' ?>">
            <small class="fw-semibold text-muted mb-1 d-block"><i class="bi bi-gear"></i> Overrides externe</small>
            <div class="d-flex flex-wrap gap-3">
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="editInclPlanning" <?= ($editUser['include_planning'] ?? 0) == 1 ? 'checked' : '' ?>>
                <label class="form-check-label small" for="editInclPlanning">Inclure dans le planning</label>
              </div>
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="editInclVacances" <?= ($editUser['include_vacances'] ?? 0) == 1 ? 'checked' : '' ?>>
                <label class="form-check-label small" for="editInclVacances">Inclure dans les vacances</label>
              </div>
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="editInclDesirs" <?= ($editUser['include_desirs'] ?? 0) == 1 ? 'checked' : '' ?>>
                <label class="form-check-label small" for="editInclDesirs">Inclure dans les désirs</label>
              </div>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Téléphone</label>
              <input type="tel" class="form-control" id="editTelephone" value="<?= h($editUser['telephone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">N° Employé</label>
              <input type="text" class="form-control" id="editEmployeeId" value="<?= h($editUser['employee_id'] ?? '') ?>">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Date d'entrée</label>
              <input type="date" class="form-control" id="editDateEntree" value="<?= h($editUser['date_entree'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Fin contrat</label>
              <input type="date" class="form-control" id="editDateFin" value="<?= h($editUser['date_fin_contrat'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Solde vacances</label>
              <input type="number" class="form-control" id="editVacances" step="0.5" value="<?= h($editUser['solde_vacances'] ?? 0) ?>">
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

    <!-- Accès zerdaTime -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-check"></i> Accès zerdaTime</h6></div>
      <div class="card-body">
        <p class="small text-muted mb-2">Gérez les pages et fonctionnalités accessibles par ce collaborateur.</p>
        <button class="btn btn-outline-primary btn-sm" id="openPermBtn"><i class="bi bi-sliders"></i> Configurer les accès</button>
      </div>
    </div>

    <!-- Modal Permissions -->
    <div class="modal fade" id="uePermModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width:620px">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shield-check"></i> Accès zerdaTime</h5>
            <button type="button" class="confirm-close-btn" data-bs-dismiss="modal" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="modal-body" id="uePermBody">
            <p class="text-muted small">Chargement...</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            <button type="button" class="btn btn-sm btn-primary" id="uePermSaveBtn"><i class="bi bi-check-lg"></i> Sauvegarder</button>
          </div>
        </div>
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

    const u = <?= json_encode($editUser, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const fonctions = <?= json_encode(array_values($editFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    // Form fields are pre-filled by PHP; init selects from injected data
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

    zerdaSelect.init('#editTypeEmploye', [
        { value: 'interne', label: 'Interne' },
        { value: 'externe', label: 'Externe' },
    ], { value: u.type_employe || 'interne', onSelect: toggleExterneOverrides });

    function toggleExterneOverrides(val) {
        document.getElementById('editExterneOverrides').style.display = (val || zerdaSelect.getValue('#editTypeEmploye')) === 'externe' ? 'block' : 'none';
    }
    toggleExterneOverrides(u.type_employe);

    document.getElementById('editInclPlanning').checked = u.include_planning == 1;
    document.getElementById('editInclVacances').checked = u.include_vacances == 1;
    document.getElementById('editInclDesirs').checked = u.include_desirs == 1;

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
            type_employe: zerdaSelect.getValue('#editTypeEmploye') || 'interne',
            include_planning: document.getElementById('editInclPlanning').checked ? 1 : null,
            include_vacances: document.getElementById('editInclVacances').checked ? 1 : null,
            include_desirs: document.getElementById('editInclDesirs').checked ? 1 : null,
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

    // ── Permissions ──
    document.getElementById('openPermBtn')?.addEventListener('click', () => loadPermissionsModal(id));

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

async function loadPermissionsModal(userId) {
    const panel = document.getElementById('uePermBody');
    const modalEl = document.getElementById('uePermModal');
    if (!panel || !modalEl) return;

    const permModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    panel.innerHTML = '<p class="text-muted small">Chargement...</p>';
    permModal.show();

    const res = await adminApiPost('admin_get_user_permissions', { user_id: userId });
    if (!res.success || !res.permissions) {
        panel.innerHTML = '<p class="text-danger small">Erreur chargement permissions</p>';
        return;
    }

    const perms = res.permissions;
    const pageEntries = Object.entries(perms).filter(([k]) => k.startsWith('page_'));
    const cuisineEntries = Object.entries(perms).filter(([k]) => k.startsWith('cuisine_'));

    // Presets definition
    const presets = [
        { id: 'standard', label: 'Standard', desc: 'Accès complet à toutes les pages', icon: 'bi-person-check',
          keys: null },
        { id: 'cuisine', label: 'Cuisine complet', desc: 'Cuisine + menus + réservations', icon: 'bi-egg-fried',
          keys: ['page_cuisine','cuisine_saisie_menu','cuisine_reservations_collab','cuisine_reservations_famille','cuisine_table_vip','page_messages'] },
        { id: 'hotellerie', label: 'Hôtellerie', desc: 'Cuisine + réservations famille', icon: 'bi-building',
          keys: ['page_cuisine','cuisine_reservations_famille','cuisine_reservations_collab','page_messages'] },
        { id: 'none', label: 'Aucun accès', desc: 'Tout désactivé', icon: 'bi-slash-circle',
          keys: [] },
    ];

    function detectActivePreset() {
        for (const p of presets) {
            if (p.keys === null) {
                if ([...panel.querySelectorAll('input[data-key]')].every(el => el.checked)) return p.id;
            } else if (p.keys.length === 0) {
                if ([...panel.querySelectorAll('input[data-key]')].every(el => !el.checked)) return p.id;
            } else {
                const match = [...panel.querySelectorAll('input[data-key]')].every(el =>
                    p.keys.includes(el.dataset.key) ? el.checked : !el.checked
                );
                if (match) return p.id;
            }
        }
        return null;
    }

    function updatePresetCards() {
        const active = detectActivePreset();
        panel.querySelectorAll('.perm-preset-card').forEach(card => {
            const isActive = card.dataset.preset === active;
            card.classList.toggle('perm-preset-active', isActive);
        });
    }

    function setAll(val) {
        panel.querySelectorAll('input[data-key]').forEach(el => { el.checked = val; });
        updatePresetCards();
    }

    function applyPreset(keys) {
        if (keys === null) { setAll(true); return; }
        panel.querySelectorAll('input[data-key]').forEach(el => {
            el.checked = keys.includes(el.dataset.key);
        });
        updatePresetCards();
    }

    // Build HTML
    panel.innerHTML =
        // Preset cards
        '<div class="perm-presets-grid">'
        + presets.map(p =>
            '<div class="perm-preset-card" data-preset="' + p.id + '">'
            + '<div class="perm-preset-check"><i class="bi bi-check-lg"></i></div>'
            + '<i class="bi ' + p.icon + ' perm-preset-icon"></i>'
            + '<div class="perm-preset-label">' + p.label + '</div>'
            + '<div class="perm-preset-desc">' + p.desc + '</div>'
            + '</div>'
        ).join('')
        + '</div>'

        // Two columns
        + '<div class="perm-columns">'

        // Left: Général
        + '<div class="perm-col">'
        + '<div class="perm-col-title">Général</div>'
        + pageEntries.map(([key, info]) =>
            '<div class="form-check form-switch">'
            + '<input type="checkbox" class="form-check-input" id="uep_' + key + '" data-key="' + key + '"' + (info.granted === 1 ? ' checked' : '') + '>'
            + '<label class="form-check-label small" for="uep_' + key + '">' + escapeHtml(info.label) + '</label>'
            + '</div>'
        ).join('')
        + '</div>'

        // Right: Cuisine
        + '<div class="perm-col">'
        + '<div class="perm-col-title">Cuisine</div>'
        + cuisineEntries.map(([key, info]) =>
            '<div class="form-check form-switch">'
            + '<input type="checkbox" class="form-check-input" id="uep_' + key + '" data-key="' + key + '"' + (info.granted === 1 ? ' checked' : '') + '>'
            + '<label class="form-check-label small" for="uep_' + key + '">' + escapeHtml(info.label) + '</label>'
            + '</div>'
        ).join('')
        + '</div>'

        + '</div>';

    // Preset click handlers
    panel.querySelectorAll('.perm-preset-card').forEach(card => {
        card.addEventListener('click', () => {
            const p = presets.find(x => x.id === card.dataset.preset);
            if (p) applyPreset(p.keys);
        });
    });

    // Switch change → update preset highlight
    panel.querySelectorAll('input[data-key]').forEach(el => {
        el.addEventListener('change', updatePresetCards);
    });

    updatePresetCards();

    // Save handler
    document.getElementById('uePermSaveBtn').onclick = async () => {
        const data = {};
        panel.querySelectorAll('input[data-key]').forEach(el => {
            data[el.dataset.key] = el.checked ? 1 : 0;
        });
        const r = await adminApiPost('admin_save_user_permissions', { user_id: userId, permissions: data });
        if (r.success) {
            showToast('Accès mis à jour', 'success');
            permModal.hide();
        } else {
            showToast(r.message || 'Erreur', 'error');
        }
    };
}

window.initUsereditPage = initUsereditPage;
</script>

<style>
.ue-avatar-preview {
    width: 96px; height: 96px; border-radius: 50%; margin: 0 auto;
    background: var(--zt-teal, #2a9d8f); display: flex; align-items: center; justify-content: center;
    overflow: hidden; border: 3px solid var(--cl-border, #e0e0e0);
}
.ue-avatar-preview .ue-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.ue-avatar-preview .ue-avatar-initials { color: #fff; font-size: 1.8rem; font-weight: 700; }

/* Permissions modal — Preset cards */
.perm-presets-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;
}
.perm-preset-card {
    position: relative; border: 1.5px solid var(--cl-border, #E8E5E0); border-radius: 12px;
    padding: 14px 10px 12px; text-align: center; cursor: pointer;
    transition: all .2s; background: var(--cl-bg, #F7F5F2);
}
.perm-preset-card:hover { border-color: var(--cl-border-hover, #D4D0CA); background: var(--cl-surface, #fff); }
.perm-preset-active { border-color: #1A1A1A !important; background: var(--cl-surface, #fff) !important; }
.perm-preset-check {
    position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%;
    background: #1A1A1A; color: #fff; font-size: .65rem; display: none;
    align-items: center; justify-content: center;
}
.perm-preset-active .perm-preset-check { display: flex; }
.perm-preset-icon { font-size: 1.3rem; color: var(--cl-text-secondary); display: block; margin-bottom: 6px; }
.perm-preset-active .perm-preset-icon { color: #1A1A1A; }
.perm-preset-label { font-weight: 600; font-size: .82rem; margin-bottom: 2px; }
.perm-preset-desc { font-size: .7rem; color: var(--cl-text-muted); line-height: 1.3; }

/* Two columns */
.perm-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.perm-col-title {
    font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px;
    color: var(--cl-text-secondary); padding-bottom: 6px; margin-bottom: 8px;
    border-bottom: 1.5px solid var(--cl-border);
}
</style>
