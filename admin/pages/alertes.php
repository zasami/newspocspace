<style>
/* ── Alertes page styles ── */

/* Table column widths */
.al-col-prio    { width: 5%; }
.al-col-titre   { width: 30%; }
.al-col-cible   { width: 15%; }
.al-col-createur{ width: 10%; }
.al-col-date    { width: 10%; }
.al-col-expire  { width: 10%; }
.al-col-lu      { width: 10%; }
.al-col-actions { width: 10%; text-align: center; }

/* Reusable badge colors (config-ia palette) */
.al-badge-red    { background: #E2B8AE; color: #7B3B2C; }
.al-badge-green  { background: #bcd2cb; color: #2d4a43; }
.al-badge-grey   { background: #C8C4BE; color: #5A5550; }
.al-badge-blue   { background: #B8C9D4; color: #3B4F6B; }

/* Color utilities */
.al-color-green  { color: #2d4a43; }
.al-color-red    { color: #7B3B2C; }

/* Clickable row */
.al-row-clickable { cursor: pointer; }

/* Unread row background */
.al-row-unread { background: #fdf8f6; }

/* Progress bar track + fill */
.al-progress-track { flex: 1; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
.al-progress-fill  { height: 100%; border-radius: 3px; }
.al-progress-fill--green { background: #bcd2cb; }
.al-progress-fill--beige { background: #D4C4A8; }
.al-progress-fill--red   { background: #E2B8AE; }
.al-progress-label { min-width: 32px; }

/* Stop propagation cell */
.al-actions-cell { text-align: center; }

/* Icon toggle button (info / close circle) */
.al-btn-icon {
    width: 30px; height: 30px; border-radius: 50%;
    border: 1px solid #e5e7eb; background: #f9fafb;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0; transition: all .2s;
}
.al-btn-close-circle {
    width: 32px; height: 32px; border-radius: 50%;
    border: 1px solid #e5e7eb;
}
.al-icon-sm { font-size: .85rem; }
.al-icon-sm-muted { font-size: .85rem; color: #666; }

/* Modal body scroll */
.al-modal-scroll { overflow-y: auto; max-height: 65vh; }

/* Info banner */
.al-info-banner { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.al-info-text   { font-size: .82rem; }

/* Border separator */
.al-border-bottom { border-bottom: 1px solid #e5e7eb; }

/* Filter pill buttons */
.al-filter-pill { border-radius: 2rem; font-size: .8rem; }
.al-filter-badge { font-size: .65rem; }

/* Search input */
.al-search-input { max-width: 200px; font-size: .8rem; }

/* Detail table */
.al-detail-table { font-size: .82rem; }
.al-detail-thead { position: sticky; top: 0; background: #fff; z-index: 1; }
.al-col-collab   { width: 35%; }
.al-col-fonct    { width: 20%; }
.al-col-login    { width: 20%; }
.al-col-lecture   { width: 25%; }

/* Avatar (img + initials fallback) */
.al-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
}
.al-avatar-initials {
    width: 32px; height: 32px; border-radius: 50%;
    background: #B8C9D4; color: #3B4F6B;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 600; flex-shrink: 0;
}

/* Small text utility */
.al-text-xs  { font-size: .75rem; }
.al-text-sm  { font-size: .85rem; }
.al-text-empty { font-size: .85rem; }

/* Message collapsible box (collapsed by default) */
.al-msg-box {
    margin-top: 0; padding: 0 1rem;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    font-size: .85rem; line-height: 1.6; color: #333; white-space: pre-wrap;
    max-height: 0; overflow: hidden; opacity: 0; border-width: 0;
    transition: max-height .35s ease, opacity .3s ease, margin-top .35s ease, padding .35s ease, border-width .35s ease;
}
.al-msg-box.is-open {
    opacity: 1; margin-top: .75rem; padding: .75rem 1rem; border-width: 1px;
}

/* Hidden utility (display:none toggle) */
.al-hidden { display: none; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><i class="bi bi-megaphone"></i> Alertes & Annonces</h5>
  <button class="btn btn-primary btn-sm" id="btnNewAlert">
    <i class="bi bi-plus-lg"></i> Nouvelle alerte
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead>
        <tr>
          <th class="al-col-prio">Prio.</th>
          <th class="al-col-titre">Titre</th>
          <th class="al-col-cible">Cible</th>
          <th class="al-col-createur">Créé par</th>
          <th class="al-col-date">Date</th>
          <th class="al-col-expire">Expire</th>
          <th class="al-col-lu">Lu par</th>
          <th class="al-col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="alertsBody">
        <tr><td colspan="8" class="text-center py-4"><span class="admin-spinner"></span> Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail modal — read status per user -->
<div class="modal fade" id="alertDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center">
        <h6 class="modal-title mb-0" id="alertDetailTitle"><i class="bi bi-megaphone"></i> Détail alerte</h6>
        <button type="button" class="btn btn-sm ms-2 al-btn-icon" id="btnToggleAlertMsg" title="Voir le message"><i class="bi bi-info-lg al-icon-sm-muted"></i></button>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center al-btn-close-circle" data-bs-dismiss="modal"><i class="bi bi-x-lg al-icon-sm"></i></button>
      </div>
      <div class="modal-body p-0 al-modal-scroll">
        <!-- Alert info banner -->
        <div id="alertDetailInfo" class="px-3 py-3 al-info-banner"></div>
        <!-- Filter tabs -->
        <div class="d-flex align-items-center gap-2 px-3 py-2 al-border-bottom">
          <button class="btn btn-sm py-1 px-3 active al-filter-pill" data-filter="all">Tous <span id="countAll" class="badge rounded-pill ms-1 al-filter-badge al-badge-grey">0</span></button>
          <button class="btn btn-sm py-1 px-3 al-filter-pill" data-filter="read">Lu <span id="countRead" class="badge rounded-pill ms-1 al-filter-badge al-badge-green">0</span></button>
          <button class="btn btn-sm py-1 px-3 al-filter-pill" data-filter="unread">Non lu <span id="countUnread" class="badge rounded-pill ms-1 al-filter-badge al-badge-red">0</span></button>
          <input type="text" class="form-control form-control-sm ms-auto al-search-input" id="alertDetailSearch" placeholder="Rechercher...">
        </div>
        <!-- Users list -->
        <div id="alertDetailUsers" class="p-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Create/Edit modal -->
<div class="modal fade" id="alertModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-megaphone"></i> Nouvelle alerte</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Titre *</label>
          <input type="text" class="form-control form-control-sm" id="alertTitle" maxlength="255">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Message *</label>
          <textarea class="form-control form-control-sm" id="alertMessage" rows="4"></textarea>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Priorité</label>
            <div class="zs-select" id="alertPriority" data-placeholder="Priorité"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Expire le</label>
            <input type="date" class="form-control form-control-sm" id="alertExpires">
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Cible</label>
            <div class="zs-select" id="alertTarget" data-placeholder="Cible"></div>
          </div>
          <div class="col-md-6 al-hidden" id="alertTargetValueGroup">
            <label class="form-label">Valeur</label>
            <div class="zs-select" id="alertTargetValue" data-placeholder="Valeur"></div>
          </div>
        </div>
        <div class="zt-info-bar small al-hidden" id="alertHauteInfo">
          <i class="bi bi-exclamation-triangle"></i>
          Les alertes de <strong>haute importance</strong> affichent un modal rouge obligatoire à chaque connexion de l'employé tant qu'il n'a pas confirmé la lecture.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="alertSaveBtn">
          <i class="bi bi-send"></i> Envoyer l'alerte
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let modulesData = [];
    let fonctionsData = [];

    async function initAlertesPage() {
        // Load refs
        const refsRes = await adminApiPost('admin_get_planning_refs');
        modulesData = refsRes.modules || [];
        fonctionsData = refsRes.fonctions || [];

        loadAlerts();

        const modal = new bootstrap.Modal(document.getElementById('alertModal'));

        // Init zerdaSelect components
        zerdaSelect.init('#alertPriority', [
            { value: 'normale', label: 'Normale' },
            { value: 'haute', label: 'Haute importance' }
        ], { value: 'normale', onSelect: (val) => {
            document.getElementById('alertHauteInfo').classList.toggle('al-hidden', val !== 'haute');
        }});

        zerdaSelect.init('#alertTarget', [
            { value: 'all', label: 'Tous les collaborateurs' },
            { value: 'module', label: 'Par module' },
            { value: 'fonction', label: 'Par fonction' }
        ], { value: 'all', onSelect: (val) => {
            const group = document.getElementById('alertTargetValueGroup');
            if (val === 'module') {
                group.classList.remove('al-hidden');
                zerdaSelect.destroy('#alertTargetValue');
                zerdaSelect.init('#alertTargetValue', modulesData.map(m => ({ value: m.id, label: escapeHtml(m.code) + ' — ' + escapeHtml(m.nom) })), { value: modulesData[0]?.id || '' });
            } else if (val === 'fonction') {
                group.classList.remove('al-hidden');
                zerdaSelect.destroy('#alertTargetValue');
                zerdaSelect.init('#alertTargetValue', fonctionsData.map(f => ({ value: f.code, label: escapeHtml(f.code) + ' — ' + escapeHtml(f.nom) })), { value: fonctionsData[0]?.code || '' });
            } else {
                group.classList.add('al-hidden');
            }
        }});

        zerdaSelect.init('#alertTargetValue', [], { value: '' });

        document.getElementById('btnNewAlert').addEventListener('click', () => {
            document.getElementById('alertTitle').value = '';
            document.getElementById('alertMessage').value = '';
            zerdaSelect.setValue('#alertPriority', 'normale');
            document.getElementById('alertExpires').value = '';
            zerdaSelect.setValue('#alertTarget', 'all');
            document.getElementById('alertTargetValueGroup').classList.add('al-hidden');
            document.getElementById('alertHauteInfo').classList.add('al-hidden');
            modal.show();
        });

        // Save
        document.getElementById('alertSaveBtn').addEventListener('click', async () => {
            const title = document.getElementById('alertTitle').value.trim();
            const message = document.getElementById('alertMessage').value.trim();
            const priority = zerdaSelect.getValue('#alertPriority');
            const target = zerdaSelect.getValue('#alertTarget');
            const targetValue = zerdaSelect.getValue('#alertTargetValue');
            const expiresAt = document.getElementById('alertExpires').value;

            if (!title || !message) { showToast('Titre et message requis', 'error'); return; }

            const btn = document.getElementById('alertSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const res = await adminApiPost('admin_create_alert', {
                title, message, priority, target,
                target_value: target !== 'all' ? targetValue : '',
                expires_at: expiresAt || null,
            });

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> Envoyer l\'alerte';

            if (res.success) {
                showToast('Alerte envoyée', 'success');
                modal.hide();
                loadAlerts();
            } else {
                showToast(res.message || 'Erreur', 'error');
            }
        });
    }

    async function loadAlerts() {
        const res = await adminApiPost('admin_get_alerts');
        const tbody = document.getElementById('alertsBody');

        if (!res.success || !res.alerts?.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Aucune alerte</td></tr>';
            return;
        }

        tbody.innerHTML = res.alerts.map(a => {
            const isHaute = a.priority === 'haute';
            const prioBadge = isHaute
                ? '<span class="badge al-badge-red">Haute</span>'
                : '<span class="badge al-badge-grey">Normale</span>';
            const targetLabel = a.target === 'all' ? 'Tous'
                : a.target === 'module' ? 'Module: ' + (a.target_value || '?')
                : 'Fonction: ' + (a.target_value || '?');
            const expiry = a.expires_at ? new Date(a.expires_at).toLocaleDateString('fr-FR') : '—';
            const readPct = a.total_users > 0 ? Math.round((a.read_count / a.total_users) * 100) : 0;
            const barClass = readPct > 80 ? 'al-progress-fill--green' : readPct > 40 ? 'al-progress-fill--beige' : 'al-progress-fill--red';
            const activeClass = a.is_active ? '' : 'text-muted text-decoration-line-through';

            return `<tr class="${activeClass} al-row-clickable" data-action="detail" data-id="${a.id}">
                <td>${prioBadge}</td>
                <td><strong>${escapeHtml(a.title)}</strong></td>
                <td><small>${escapeHtml(targetLabel)}</small></td>
                <td><small>${escapeHtml(a.creator_prenom + ' ' + a.creator_nom)}</small></td>
                <td><small>${new Date(a.created_at).toLocaleDateString('fr-FR')}</small></td>
                <td><small>${expiry}</small></td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="al-progress-track">
                            <div class="al-progress-fill ${barClass}" style="width:${readPct}%"></div>
                        </div>
                        <small class="al-progress-label">${a.read_count}/${a.total_users}</small>
                    </div>
                </td>
                <td class="al-actions-cell" data-stop-propagation>
                    <button class="btn btn-xs btn-outline-${a.is_active ? 'warning' : 'success'} me-1" data-action="toggle" data-id="${a.id}" title="${a.is_active ? 'Désactiver' : 'Activer'}">
                        <i class="bi bi-${a.is_active ? 'pause' : 'play'}"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-danger" data-action="delete" data-id="${a.id}" title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    // Event delegation for toggle/delete/detail + stop-propagation replacement
    document.getElementById('alertsBody').addEventListener('click', async (e) => {
        // Replace inline onclick="event.stopPropagation()" with data-attr delegation
        if (e.target.closest('[data-stop-propagation]') && !e.target.closest('[data-action="toggle"]') && !e.target.closest('[data-action="delete"]')) {
            e.stopPropagation();
            return;
        }

        const row = e.target.closest('[data-action]');
        if (!row) return;
        const action = row.dataset.action;
        const id = row.dataset.id;
        if (!id) return;

        if (action === 'toggle' || action === 'delete') {
            e.stopPropagation();
        }

        if (action === 'detail') {
            openAlertDetail(id);
        } else if (action === 'toggle') {
            row.disabled = true;
            await adminApiPost('admin_toggle_alert', { id });
            loadAlerts();
        } else if (action === 'delete') {
            if (!await adminConfirm({ title: 'Supprimer l\'alerte', text: 'Cette action est irréversible. Tous les accusés de lecture seront également supprimés.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
            row.disabled = true;
            const res = await adminApiPost('admin_delete_alert', { id });
            if (res.success) {
                showToast('Alerte supprimée', 'success');
                loadAlerts();
            }
        }
    });

    // ─── Alert detail modal ───
    const detailModal = new bootstrap.Modal(document.getElementById('alertDetailModal'));
    let detailUsersData = [];
    let detailFilter = 'all';

    async function openAlertDetail(id) {
        const container = document.getElementById('alertDetailUsers');
        container.innerHTML = '<div class="text-center py-4"><span class="admin-spinner"></span></div>';
        document.getElementById('alertDetailSearch').value = '';
        const msgBox = document.getElementById('alertDetailMsgBox');
        if (msgBox) { msgBox.classList.remove('is-open'); msgBox.style.maxHeight = '0'; msgBox.dataset.open = '0'; }
        detailFilter = 'all';
        document.querySelectorAll('#alertDetailModal [data-filter]').forEach(b => b.classList.remove('active'));
        document.querySelector('#alertDetailModal [data-filter="all"]').classList.add('active');
        detailModal.show();

        const res = await adminApiPost('admin_get_alert_reads', { id });
        if (!res.success) { container.innerHTML = '<div class="text-center py-4 text-muted">Erreur de chargement</div>'; return; }

        const a = res.alert;
        const isHaute = a.priority === 'haute';
        const targetLabel = a.target === 'all' ? 'Tous les collaborateurs'
            : a.target === 'module' ? 'Module : ' + (a.target_value || '?')
            : 'Fonction : ' + (a.target_value || '?');

        document.getElementById('alertDetailTitle').innerHTML = '<i class="bi bi-megaphone"></i> ' + escapeHtml(a.title);
        document.getElementById('alertDetailInfo').innerHTML = `
            <div class="d-flex flex-wrap gap-3 align-items-center al-info-text">
                <span>${isHaute
                    ? '<span class="badge al-badge-red"><i class="bi bi-exclamation-triangle"></i> Haute</span>'
                    : '<span class="badge al-badge-grey">Normale</span>'}</span>
                <span class="text-muted"><i class="bi bi-bullseye"></i> ${escapeHtml(targetLabel)}</span>
                <span class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(a.creator_prenom + ' ' + a.creator_nom)}</span>
                <span class="text-muted"><i class="bi bi-calendar"></i> ${new Date(a.created_at).toLocaleDateString('fr-FR', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})}</span>
                <span class="ms-auto fw-semibold ${res.read_count === res.total_users ? 'al-color-green' : 'al-color-red'}">
                    <i class="bi bi-eye"></i> ${res.read_count} / ${res.total_users} lu${res.read_count > 1 ? 's' : ''}
                </span>
            </div>
            <div id="alertDetailMsgBox" class="al-msg-box" data-open="0">${escapeHtml(a.message || '')}</div>`;

        detailUsersData = res.users;
        document.getElementById('countAll').textContent = res.total_users;
        document.getElementById('countRead').textContent = res.read_count;
        document.getElementById('countUnread').textContent = res.total_users - res.read_count;

        renderDetailUsers();
    }

    function renderDetailUsers() {
        const search = document.getElementById('alertDetailSearch').value.toLowerCase().trim();
        const container = document.getElementById('alertDetailUsers');

        let filtered = detailUsersData;
        if (detailFilter === 'read') filtered = filtered.filter(u => u.read_at);
        if (detailFilter === 'unread') filtered = filtered.filter(u => !u.read_at);
        if (search) filtered = filtered.filter(u =>
            (u.prenom + ' ' + u.nom + ' ' + u.email + ' ' + (u.fonction_nom || '')).toLowerCase().includes(search)
        );

        if (!filtered.length) {
            container.innerHTML = '<div class="text-center py-4 text-muted al-text-empty">Aucun collaborateur trouvé</div>';
            return;
        }

        container.innerHTML = '<table class="table table-sm table-hover mb-0 al-detail-table">'
            + '<thead class="al-detail-thead"><tr>'
            + '<th class="al-col-collab">Collaborateur</th>'
            + '<th class="al-col-fonct">Fonction</th>'
            + '<th class="al-col-login">Dernière connexion</th>'
            + '<th class="al-col-lecture">Lecture alerte</th>'
            + '</tr></thead><tbody>'
            + filtered.map(u => {
                const hasRead = !!u.read_at;
                const readBadge = hasRead
                    ? `<span class="badge al-badge-green"><i class="bi bi-check-circle"></i> Lu</span>
                       <span class="text-muted ms-1">${formatDateTime(u.read_at)}</span>`
                    : '<span class="badge al-badge-red"><i class="bi bi-x-circle"></i> Non lu</span>';
                const lastLogin = u.last_login
                    ? formatDateTime(u.last_login)
                    : '<span class="text-muted">Jamais</span>';
                const initials = (u.prenom?.[0] || '') + (u.nom?.[0] || '');
                const avatar = u.photo
                    ? `<img src="${escapeHtml(u.photo)}" class="al-avatar">`
                    : `<div class="al-avatar-initials">${escapeHtml(initials.toUpperCase())}</div>`;

                return `<tr class="${!hasRead ? 'al-row-unread' : ''}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${avatar}
                            <div>
                                <div class="fw-medium">${escapeHtml(u.prenom + ' ' + u.nom)}</div>
                                <div class="text-muted al-text-xs">${escapeHtml(u.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td>${u.fonction_nom ? escapeHtml(u.fonction_nom) : '<span class="text-muted">—</span>'}</td>
                    <td>${lastLogin}</td>
                    <td>${readBadge}</td>
                </tr>`;
            }).join('')
            + '</tbody></table>';
    }

    function formatDateTime(str) {
        if (!str) return '—';
        const d = new Date(str);
        return d.toLocaleDateString('fr-FR', {day:'numeric',month:'short'})
            + ' ' + d.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'});
    }

    // Toggle message box — uses classList + one dynamic maxHeight
    document.getElementById('btnToggleAlertMsg').addEventListener('click', () => {
        const box = document.getElementById('alertDetailMsgBox');
        if (!box) return;
        const isOpen = box.dataset.open === '1';
        if (isOpen) {
            box.classList.remove('is-open');
            box.style.maxHeight = '0';
            box.dataset.open = '0';
        } else {
            box.style.maxHeight = box.scrollHeight + 24 + 'px';
            box.classList.add('is-open');
            box.dataset.open = '1';
        }
    });

    // Filter buttons — event delegation on modal
    document.getElementById('alertDetailModal').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-filter]');
        if (!btn) return;
        detailFilter = btn.dataset.filter;
        document.querySelectorAll('#alertDetailModal [data-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderDetailUsers();
    });

    // Search input
    document.getElementById('alertDetailSearch').addEventListener('input', () => renderDetailUsers());

    window.initAlertesPage = initAlertesPage;
})();
</script>
