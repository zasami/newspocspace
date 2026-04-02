<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$usersRaw = Db::fetchAll(
    "SELECT u.*, f.nom AS fonction_nom, f.code AS fonction_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     ORDER BY u.nom, u.prenom"
);
foreach ($usersRaw as &$u) {
    unset($u['password'], $u['reset_token'], $u['reset_expires']);
    $u['modules'] = Db::fetchAll(
        "SELECT m.id, m.nom, m.code, um.is_principal
         FROM user_modules um JOIN modules m ON m.id = um.module_id
         WHERE um.user_id = ?",
        [$u['id']]
    );
}
unset($u);

$fonctions = Db::fetchAll("SELECT id, code, nom, ordre FROM fonctions ORDER BY ordre");
?>
<style>
/* Shared action button base */
.btn-user-edit,
.btn-user-deactivate,
.btn-user-activate {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-user-edit:hover { background: #B8C9D4; color: #3B4F6B; border-color: #B8C9D4; }
.btn-user-deactivate:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }
.btn-user-activate:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }

/* Page header */
.users-page-title { font-weight: 700; color: var(--cl-text); }
.users-page-icon { color: #3B4F6B; }

/* Filter bar */
.filter-bar-body { padding: .75rem 1rem; }
.filter-col-fonction { min-width: 160px; }
.filter-col-taux { min-width: 120px; }
.filter-col-module { min-width: 140px; }
.filter-col-role { min-width: 140px; }
.filter-col-statut { min-width: 120px; }
.filter-col-pagesize { min-width: 80px; }
.filter-label { font-size: .75rem; font-weight: 600; }
.filter-divider { width: 1px; height: 36px; background: var(--cl-border, #e5e7eb); align-self: flex-end; margin: 0 4px; }
.btn-filter-reset { height: 36px; padding: 0 12px; }

/* Pagination */
.pagination-bar { border-top: 1px solid var(--cl-border, #e5e7eb); }
.pagination-ellipsis { line-height: 31px; }

/* Modal close button */
.btn-modal-close { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.btn-modal-close i { font-size: .85rem; }

/* User avatar */
.user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.user-avatar-initials { width: 32px; height: 32px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 600; flex-shrink: 0; }

/* Table row clickable */
.tr-clickable { cursor: pointer; }

/* Role badges */
.badge-ss-admin { background: #E2B8AE; color: #7B3B2C; }
.badge-ss-direction { background: #D0C4D8; color: #5B4B6B; }
.badge-ss-responsable { background: #D4C4A8; color: #6B5B3E; }
.badge-ss-collaborateur { background: #B8C9D4; color: #3B4F6B; }

/* Status icons */
.text-ss-green { color: #2d4a43; }
.text-ss-red { color: #7B3B2C; }
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1 users-page-title"><i class="bi bi-people-fill users-page-icon"></i> Liste des collaborateurs</h4>
    <small class="text-muted" id="usersCount"></small>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
    <i class="bi bi-plus-lg"></i> Nouveau collaborateur
  </button>
</div>

<!-- Filtres -->
<div class="card mb-3">
  <div class="card-body filter-bar-body">
    <div class="d-flex gap-2 flex-wrap align-items-end">
      <div class="filter-col-fonction">
        <label class="form-label mb-1 filter-label">Fonction</label>
        <div class="zs-select" id="filterFonction" data-placeholder="Toutes"></div>
      </div>
      <div class="filter-col-taux">
        <label class="form-label mb-1 filter-label">Taux</label>
        <div class="zs-select" id="filterTaux" data-placeholder="Tous"></div>
      </div>
      <div class="filter-col-module">
        <label class="form-label mb-1 filter-label">Module</label>
        <div class="zs-select" id="filterModule" data-placeholder="Tous"></div>
      </div>
      <div class="filter-col-role">
        <label class="form-label mb-1 filter-label">Rôle</label>
        <div class="zs-select" id="filterRole" data-placeholder="Tous"></div>
      </div>
      <div class="filter-col-statut">
        <label class="form-label mb-1 filter-label">Statut</label>
        <div class="zs-select" id="filterStatut" data-placeholder="Tous"></div>
      </div>
      <div class="filter-divider"></div>
      <div class="filter-col-pagesize">
        <label class="form-label mb-1 filter-label">Lignes</label>
        <div class="zs-select" id="filterPageSize" data-placeholder="30"></div>
      </div>
      <div>
        <label class="form-label mb-1 filter-label">&nbsp;</label>
        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 btn-filter-reset" id="filterReset"><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collaborateur</th>
          <th>ID</th>
          <th>Fonction</th>
          <th>Taux</th>
          <th>Module</th>
          <th>Rôle</th>
          <th>Actif</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="usersTableBody">
        <tr><td colspan="8" class="text-center py-4 text-muted">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center px-3 py-2 pagination-bar">
    <small class="text-muted" id="paginationInfo"></small>
    <div class="d-flex gap-1" id="paginationBtns"></div>
  </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog modal-info">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center">
        <h5 class="modal-title mb-0">Nouveau collaborateur</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center btn-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <form id="createUserForm">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Prénom *</label>
              <input type="text" class="form-control" name="prenom" required>
            </div>
            <div class="col-6">
              <label class="form-label">Nom *</label>
              <input type="text" class="form-control" name="nom" required>
            </div>
          </div>
          <div class="mb-2 mt-2">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Fonction</label>
              <div class="zs-select" id="newUserFonction" data-placeholder="— Choisir —"></div>
            </div>
            <div class="col-6">
              <label class="form-label">Taux %</label>
              <input type="number" class="form-control" name="taux" value="100" min="20" max="100" step="5">
            </div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Rôle</label>
              <div class="zs-select" id="newUserRole" data-placeholder="Collaborateur"></div>
            </div>
            <div class="col-6">
              <label class="form-label">Contrat</label>
              <div class="zs-select" id="newUserContrat" data-placeholder="CDI"></div>
            </div>
          </div>
          <div class="mb-2 mt-2">
            <label class="form-label">Téléphone</label>
            <input type="tel" class="form-control" name="telephone">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
let allUsers = <?= json_encode(array_values($usersRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
let filteredUsers = [];
let currentPage = 1;
let PAGE_SIZE = 30;

function capitalizeOnBlur(e) {
    e.target.value = e.target.value.trim().replace(/\b\w/g, c => c.toUpperCase());
}

function initUsersPage() {
    document.querySelectorAll('#createUserForm input[name="prenom"], #createUserForm input[name="nom"]').forEach(
        el => el.addEventListener('blur', capitalizeOnBlur)
    );

    // Event delegation
    document.getElementById('usersTableBody').addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-toggle-user]');
        if (toggleBtn) { e.preventDefault(); toggleUser(toggleBtn.dataset.toggleUser); return; }
        const delBtn = e.target.closest('[data-delete-user]');
        if (delBtn) { e.preventDefault(); deleteUserPermanently(delBtn.dataset.deleteUser); return; }
        if (e.target.closest('a, button')) return;
        const tr = e.target.closest('tr[data-user-href]');
        if (tr) window.location.href = tr.dataset.userHref;
    });

    // Init filter selects
    zerdaSelect.init('#filterTaux', [
        { value: '', label: 'Tous' },
        { value: '100', label: '100%' },
        { value: '80', label: '80%' },
        { value: '60', label: '60%' },
        { value: '50', label: '50%' },
        { value: 'lt50', label: '< 50%' },
    ], { onSelect: applyFilters });

    zerdaSelect.init('#filterRole', [
        { value: '', label: 'Tous' },
        { value: 'collaborateur', label: 'Collaborateur' },
        { value: 'responsable', label: 'Responsable' },
        { value: 'admin', label: 'Admin' },
        { value: 'direction', label: 'Direction' },
    ], { onSelect: applyFilters });

    zerdaSelect.init('#filterStatut', [
        { value: '', label: 'Tous' },
        { value: '1', label: 'Actifs' },
        { value: '0', label: 'Inactifs' },
    ], { value: '1', onSelect: applyFilters });

    // Populate fonction + module filters from injected data
    populateFilterOptions();

    // Page size selector
    zerdaSelect.init('#filterPageSize', [
        { value: '15', label: '15' },
        { value: '30', label: '30' },
        { value: '50', label: '50' },
        { value: '100', label: '100' },
    ], { value: '30', onSelect: (val) => { PAGE_SIZE = parseInt(val) || 30; currentPage = 1; renderPage(); } });

    // Pagination clicks
    document.getElementById('paginationBtns').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn && !btn.disabled) {
            currentPage = parseInt(btn.dataset.page);
            renderPage();
        }
    });

    document.getElementById('filterReset')?.addEventListener('click', () => {
        zerdaSelect.setValue('#filterFonction', '');
        zerdaSelect.setValue('#filterTaux', '');
        zerdaSelect.setValue('#filterModule', '');
        zerdaSelect.setValue('#filterRole', '');
        zerdaSelect.setValue('#filterStatut', '');
        zerdaSelect.setValue('#filterPageSize', '30');
        PAGE_SIZE = 30;
        applyFilters();
    });

    // Init modal fonctions select from injected data
    const fonctionsData = <?= json_encode(array_values($fonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    zerdaSelect.init('#newUserFonction', [
        { value: '', label: '— Aucune —' },
        ...fonctionsData.map(f => ({ value: f.id, label: f.nom || f.code }))
    ], { search: fonctionsData.length > 6 });

    zerdaSelect.init('#newUserRole', [
        { value: 'collaborateur', label: 'Collaborateur' },
        { value: 'responsable', label: 'Responsable' },
        { value: 'admin', label: 'Admin' },
        { value: 'direction', label: 'Direction' },
    ], { value: 'collaborateur' });

    zerdaSelect.init('#newUserContrat', [
        { value: 'CDI', label: 'CDI' },
        { value: 'CDD', label: 'CDD' },
        { value: 'stagiaire', label: 'Stagiaire' },
        { value: 'civiliste', label: 'Civiliste' },
        { value: 'interim', label: 'Intérim' },
    ], { value: 'CDI' });

    // Apply initial filters (statut=1 par défaut)
    applyFilters();

    document.getElementById('topbarSearchInput')?.addEventListener('input', () => {
        applyFilters();
    });

    document.getElementById('createUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd);
        data.fonction_id = zerdaSelect.getValue('#newUserFonction');
        data.role = zerdaSelect.getValue('#newUserRole') || 'collaborateur';
        data.type_contrat = zerdaSelect.getValue('#newUserContrat') || 'CDI';

        const res = await adminApiPost('admin_create_user', data);
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
            showToast(res.message || 'Collaborateur créé', 'success');
            e.target.reset();
            zerdaSelect.setValue('#newUserFonction', '');
            zerdaSelect.setValue('#newUserRole', 'collaborateur');
            zerdaSelect.setValue('#newUserContrat', 'CDI');
            await loadUsers();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });
}

function populateFilterOptions() {
    // Fonctions
    const fonctions = [...new Set(allUsers.map(u => u.fonction_nom).filter(Boolean))].sort();
    const fVal = zerdaSelect.getValue('#filterFonction') || '';
    zerdaSelect.init('#filterFonction', [
        { value: '', label: 'Toutes' },
        ...fonctions.map(f => ({ value: f, label: f }))
    ], { value: fVal, onSelect: applyFilters, search: fonctions.length > 6 });

    // Modules
    const modules = [...new Set(allUsers.flatMap(u => (u.modules || []).map(m => m.code)).filter(Boolean))].sort();
    const mVal = zerdaSelect.getValue('#filterModule') || '';
    zerdaSelect.init('#filterModule', [
        { value: '', label: 'Tous' },
        ...modules.map(m => ({ value: m, label: m }))
    ], { value: mVal, onSelect: applyFilters, search: modules.length > 6 });
}

function applyFilters() {
    const search = (document.getElementById('topbarSearchInput')?.value || '').toLowerCase();
    const fonction = zerdaSelect.getValue('#filterFonction') || '';
    const taux = zerdaSelect.getValue('#filterTaux') || '';
    const module = zerdaSelect.getValue('#filterModule') || '';
    const role = zerdaSelect.getValue('#filterRole') || '';
    const statut = zerdaSelect.getValue('#filterStatut') || '';

    let filtered = allUsers;

    if (search) filtered = filtered.filter(u => (u.nom + ' ' + u.prenom + ' ' + u.email + ' ' + (u.employee_id || '')).toLowerCase().includes(search));
    if (fonction) filtered = filtered.filter(u => u.fonction_nom === fonction);
    if (taux === 'lt50') filtered = filtered.filter(u => Math.round(u.taux) < 50);
    else if (taux) filtered = filtered.filter(u => Math.round(u.taux) === parseInt(taux));
    if (module) filtered = filtered.filter(u => (u.modules || []).some(m => m.code === module));
    if (role) filtered = filtered.filter(u => u.role === role);
    if (statut !== '') filtered = filtered.filter(u => String(u.is_active ? 1 : 0) === statut);

    filteredUsers = filtered;
    currentPage = 1;
    document.getElementById('usersCount').textContent = filtered.length + ' collaborateur' + (filtered.length > 1 ? 's' : '') + (filtered.length !== allUsers.length ? ' / ' + allUsers.length + ' total' : '');
    renderPage();
}

function renderPage() {
    const total = filteredUsers.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageUsers = filteredUsers.slice(start, start + PAGE_SIZE);

    renderUsers(pageUsers);

    const infoEl = document.getElementById('paginationInfo');
    if (total === 0) {
        infoEl.textContent = '';
    } else {
        infoEl.textContent = `${start + 1}–${Math.min(start + PAGE_SIZE, total)} sur ${total}`;
    }

    const btns = document.getElementById('paginationBtns');
    if (totalPages <= 1) { btns.innerHTML = ''; return; }

    let html = '';
    html += `<button class="btn btn-sm btn-outline-secondary" ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}"><i class="bi bi-chevron-left"></i></button>`;

    const range = getPaginationRange(currentPage, totalPages);
    range.forEach(p => {
        if (p === '...') {
            html += '<span class="px-1 text-muted pagination-ellipsis">…</span>';
        } else {
            html += `<button class="btn btn-sm ${p === currentPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`;
        }
    });

    html += `<button class="btn btn-sm btn-outline-secondary" ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}"><i class="bi bi-chevron-right"></i></button>`;
    btns.innerHTML = html;
}

function getPaginationRange(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [];
    pages.push(1);
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
}

function renderAvatar(user) {
    if (user.photo) {
        return `<img src="${escapeHtml(user.photo)}" class="user-avatar">`;
    }
    const initials = ((user.prenom?.[0] || '') + (user.nom?.[0] || '')).toUpperCase();
    return `<div class="user-avatar-initials">${initials}</div>`;
}

function roleBadgeClass(role) {
    const map = { admin: 'badge-ss-admin', direction: 'badge-ss-direction', responsable: 'badge-ss-responsable', collaborateur: 'badge-ss-collaborateur' };
    return map[role] || map.collaborateur;
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Aucun collaborateur</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const modules = (u.modules || []).map(m => m.code).join(', ') || '—';
        return `<tr data-user-href="${AdminURL.page('user-edit', u.id)}" class="tr-clickable">
          <td>
            <div class="d-flex align-items-center gap-2">
              ${renderAvatar(u)}
              <div>
                <strong>${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</strong>
                <br><small class="text-muted">${escapeHtml(u.email)}</small>
              </div>
            </div>
          </td>
          <td><small class="text-muted">${escapeHtml(u.employee_id || '—')}</small></td>
          <td>${escapeHtml(u.fonction_nom || '—')}</td>
          <td>${Math.round(u.taux)}%</td>
          <td><small>${escapeHtml(modules)}</small></td>
          <td><span class="badge ${roleBadgeClass(u.role)}">${escapeHtml(u.role)}</span></td>
          <td>${u.is_active ? '<i class="bi bi-check-lg text-ss-green"></i>' : '<i class="bi bi-x-lg text-ss-red"></i>'}</td>
          <td>
            <div class="d-flex gap-1">
              <a href="${AdminURL.page('user-edit', u.id)}" class="btn btn-sm btn-user-edit" title="Modifier"><i class="bi bi-pencil"></i></a>
              <button class="btn btn-sm btn-user-${u.is_active ? 'deactivate' : 'activate'}" data-toggle-user="${u.id}" title="${u.is_active ? 'Désactiver' : 'Activer'}">
                <i class="bi bi-${u.is_active ? 'pause' : 'play'}"></i>
              </button>
              ${u.email.toLowerCase() === 'zaghbani.sami@gmail.com' ? `<button class="btn btn-sm btn-user-deactivate" data-delete-user="${u.id}" title="Supprimer définitivement"><i class="bi bi-trash"></i></button>` : ''}
            </div>
          </td>
        </tr>`;
    }).join('');
}

async function loadUsers() {
    const res = await adminApiPost('admin_get_users');
    allUsers = res.users || [];
    populateFilterOptions();
    applyFilters();
}

async function toggleUser(id) {
    const ok = await adminConfirm({
        title: 'Changer le statut',
        text: 'Voulez-vous vraiment modifier le statut de ce collaborateur ?',
        icon: 'bi-toggle-on',
        type: 'warning',
        okText: 'Confirmer',
        cancelText: 'Annuler'
    });
    if (!ok) return;
    const res = await adminApiPost('admin_toggle_user', { id });
    if (res.success) {
        showToast('Statut modifié', 'success');
        await loadUsers();
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}

// DEV ONLY — suppression définitive (à retirer après tests)
async function deleteUserPermanently(id) {
    const ok = await adminConfirm({
        title: 'Supprimer définitivement',
        text: 'Cette action est <strong>irréversible</strong>. Toutes les données de ce collaborateur seront supprimées (absences, désirs, plannings, messages...).',
        icon: 'bi-trash',
        type: 'danger',
        okText: 'Supprimer définitivement',
        cancelText: 'Annuler'
    });
    if (!ok) return;
    const res = await adminApiPost('admin_delete_user_permanent', { id });
    if (res.success) {
        showToast('Collaborateur supprimé', 'success');
        await loadUsers();
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}

window.initUsersPage = initUsersPage;
window.toggleUser = toggleUser;
</script>
