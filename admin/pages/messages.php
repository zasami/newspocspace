<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$emailContacts = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, u.photo, f.nom AS fonction_nom,
            COALESCE(m.nom, 'Sans module') AS module_nom,
            COALESCE(m.ordre, 999) AS module_ordre
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY module_ordre, m.nom, u.nom, u.prenom"
);

$emailStatsTotal       = (int) Db::getOne("SELECT COUNT(*) FROM messages WHERE is_draft = 0");
$emailStatsToday       = (int) Db::getOne("SELECT COUNT(*) FROM messages WHERE is_draft = 0 AND DATE(created_at) = CURDATE()");
$emailStatsUnread      = (int) Db::getOne(
    "SELECT COUNT(DISTINCT mr.email_id) FROM message_recipients mr
     JOIN messages m ON m.id = mr.email_id
     WHERE mr.user_id = ? AND mr.lu = 0 AND mr.deleted = 0 AND m.is_draft = 0",
    [$_SESSION['ss_user']['id'] ?? '']
);
$emailStatsAttachments = (int) Db::getOne("SELECT COUNT(*) FROM message_attachments");
$emailStatsTrash       = (int) Db::getOne(
    "SELECT COUNT(*) FROM messages e WHERE e.is_draft = 0 AND (
        (e.sender_deleted = 1 AND e.from_user_id = ?)
        OR EXISTS (SELECT 1 FROM message_recipients er WHERE er.email_id = e.id AND er.user_id = ? AND er.deleted = 1)
    )", [$_SESSION['ss_user']['id'] ?? '', $_SESSION['ss_user']['id'] ?? '']
);
?>
<!-- Admin Emails Page — Split-view email client -->
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">

<style>
/* Page header */
.email-page-header { margin-bottom:1.25rem; display:flex; align-items:flex-start; justify-content:space-between; }
.email-page-title { font-weight:700; margin:0; }
.email-stats-line { font-size:.85rem; margin-top:.25rem; }

/* Empty state */
.email-empty-padded { padding:60px 20px; }
.email-empty-icon { font-size:2.5rem; opacity:.2; display:block; margin-bottom:8px; }
.email-empty-icon--sm { font-size:1.5rem; opacity:.3; }
.email-empty-text { font-size:.88rem; }

/* Detail: recipients */
.email-recipient-line { font-size:0.82rem; }

/* Attachments */
.email-att-title { font-size:.85rem; }
.email-att-grid { display:flex; flex-wrap:wrap; gap:10px; }
.email-att-card { text-decoration:none; color:inherit; width:130px; border:1px solid #dee2e6; border-radius:8px; overflow:hidden; background:#fff; transition:box-shadow .2s; }
.email-att-thumb { height:80px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f8f9fa; }
.email-att-thumb img { width:100%; height:100%; object-fit:cover; }
.email-att-thumb .bi { font-size:1.8rem; }
.email-att-info { padding:6px 8px; border-top:1px solid #eee; }
.email-att-name { font-size:.72rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.email-att-size { font-size:.65rem; color:#999; }

/* Thread */
.email-thread-title { font-size:0.82rem; }
.email-thread-meta { font-size:0.8rem; }
.email-thread-body { font-size:0.85rem; opacity:0.85; }

/* Compose panel visibility */
.compose-panel--visible { display:flex; }
.compose-panel--hidden { display:none; }

/* Compose search dropdown */
.compose-search-wrap { flex:1;min-width:0;position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:4px }
.compose-search-input { border:none;outline:none;font-size:.88rem;padding:2px 0;flex:1;min-width:80px;background:transparent;color:var(--cl-text);font-family:inherit }
.compose-search-input::placeholder { color:#aaa }
.compose-search-dropdown { position:absolute;top:calc(100% + 4px);left:-14px;right:-14px;z-index:999;background:#fff;border:1px solid var(--cl-border);border-radius:10px;max-height:260px;overflow-y:auto;display:none;box-shadow:0 8px 24px rgba(0,0,0,.14) }
.compose-search-dropdown.open { display:block }
.compose-search-dropdown .csd-group { padding:6px 12px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--cl-muted);background:#fafaf8 }
.compose-search-dropdown .csd-group:first-child { border-radius:10px 10px 0 0 }
.compose-search-dropdown .csd-item { padding:7px 12px;font-size:.87rem;cursor:pointer;transition:background .1s;display:flex;align-items:center;gap:10px }
.compose-search-dropdown .csd-item:hover,.compose-search-dropdown .csd-item.active { background:rgba(25,25,24,.05) }
.compose-search-dropdown .csd-item small { color:var(--cl-muted) }
.compose-search-dropdown .csd-empty { padding:12px;font-size:.84rem;color:var(--cl-muted);text-align:center }
.compose-search-dropdown .csd-avatar { width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0 }
.compose-search-dropdown .csd-initials { width:28px;height:28px;border-radius:50%;background:#E8E5E0;color:#6B6B6B;font-size:.7rem;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0 }
</style>

<!-- Grand titre stats + bouton nouveau -->
<div class="email-page-header">
  <div>
    <h4 class="email-page-title"><i class="bi bi-envelope"></i> Messages</h4>
    <div id="emailStatsLine" class="text-muted email-stats-line"><?= $emailStatsTotal ?> messages · <?= $emailStatsToday ?> aujourd'hui · <?= $emailStatsUnread ?> non lu(s) · <?= $emailStatsAttachments ?> pièce(s) jointe(s)</div>
  </div>
  <button class="btn btn-sm btn-primary" id="btnAdminCompose" title="Nouveau message">
    <i class="bi bi-pencil-square me-1"></i> Nouveau
  </button>
</div>

<div class="adm-email-split">

  <!-- LEFT: Email List -->
  <div class="adm-email-left">
    <div class="adm-email-left-header">
      <div class="email-tabs" id="emailTabs">
        <div class="email-tabs-slider" id="emailTabsSlider"></div>
        <button class="email-tab active" data-tab="inbox">Réception <span class="email-unread-badge" id="badgeInbox"><?= $emailStatsUnread > 0 ? $emailStatsUnread : '' ?></span></button>
        <button class="email-tab" data-tab="sent">Envoyés</button>
        <button class="email-tab" data-tab="trash"><i class="bi bi-trash3"></i> Corbeille <span class="email-trash-badge" id="badgeTrash"><?= $emailStatsTrash > 0 ? $emailStatsTrash : '' ?></span></button>
      </div>
    </div>

    <div id="emailListContainer" class="adm-email-list">
      <div class="adm-email-empty"><span class="spinner-border spinner-border-sm"></span></div>
    </div>

    <div class="adm-email-left-footer">
      <button class="email-pag-arrow" id="emailPrev" disabled><i class="bi bi-arrow-left"></i></button>
      <div class="email-pag-dots" id="emailPagDots"></div>
      <button class="email-pag-arrow" id="emailNext" disabled><i class="bi bi-arrow-right"></i></button>
    </div>
  </div>

  <!-- RIGHT: Email Detail -->
  <div class="adm-email-right">
    <div id="emailDetailCard" class="adm-email-detail">
      <div class="adm-email-empty email-empty-padded">
        <i class="bi bi-envelope-open email-empty-icon"></i>
        <p class="mb-0 email-empty-text">Sélectionnez un message pour le lire</p>
      </div>
    </div>
  </div>
</div>

<!-- Gmail-style Compose Panel (bottom-right) -->
<div class="compose-panel" id="adminComposePanel">
  <div class="compose-panel-header" id="adminComposePanelHeader">
    <span class="compose-panel-title" id="adminComposePanelTitle">Nouveau message</span>
    <div class="compose-panel-header-actions">
      <button type="button" class="compose-panel-header-btn" id="adminComposeMinimize" title="Réduire"><i class="bi bi-dash-lg"></i></button>
      <button type="button" class="compose-panel-header-btn" id="adminComposeExpand" title="Agrandir"><i class="bi bi-arrows-fullscreen"></i></button>
      <button type="button" class="compose-panel-header-btn" id="adminComposeClose" title="Fermer"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
  <div class="compose-panel-body">
    <div class="compose-field">
      <label>À</label>
      <div class="compose-search-wrap">
        <div id="adminToTags" class="email-tags"></div>
        <input type="text" class="compose-search-input" id="adminComposeToSearch" placeholder="Destinataires" autocomplete="off">
        <div class="compose-search-dropdown" id="adminComposeToDropdown"></div>
      </div>
    </div>
    <div class="compose-field">
      <label>Cc</label>
      <div class="compose-search-wrap">
        <div id="adminCcTags" class="email-tags"></div>
        <input type="text" class="compose-search-input" id="adminComposeCcSearch" placeholder="Copie" autocomplete="off">
        <div class="compose-search-dropdown" id="adminComposeCcDropdown"></div>
      </div>
    </div>
    <div class="compose-field">
      <label style="visibility:hidden">Ob</label>
      <input type="text" class="form-control" id="adminComposeSubject" placeholder="Objet" maxlength="255">
    </div>
    <div id="adminComposeEditorWrap" class="zs-editor-wrap compose-editor-wrap"></div>
    <input type="hidden" id="adminComposeParentId" value="">
  </div>
  <div class="compose-panel-footer">
    <button type="button" class="adm-email-btn" id="btnAdminSendEmail"><i class="bi bi-send"></i> Envoyer</button>
    <div class="compose-panel-footer-right">
      <input type="file" id="adminComposeFile" multiple hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt,.csv">
      <button type="button" class="compose-panel-footer-btn" id="btnAdminAttach" title="Joindre un fichier"><i class="bi bi-paperclip"></i></button>
      <button type="button" class="compose-panel-footer-btn compose-panel-delete" id="adminComposeDiscard" title="Annuler"><i class="bi bi-trash3"></i></button>
    </div>
  </div>
  <div class="att-preview-list" id="adminAttPreviewList"></div>
</div>

<script<?= nonce() ?>>
(function() {
    let editorModule = null;
    const getEditorModule = async () => {
        if (!editorModule) editorModule = await import('/spocspace/assets/js/rich-editor.js');
        return editorModule;
    };
    let contacts = <?= json_encode(array_values($emailContacts), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let toSelected = [];
    let ccSelected = [];
    let currentPage = 1;
    let totalEmails = 0;
    let totalPagesVal = 1;
    let selectedId = null;
    let searchTimer = null;
    let currentSearch = '';
    let composeEditor = null;
    let currentTab = 'inbox';
    let pendingFiles = [];

    function initMessagesPage() {
        // Events
        document.getElementById('btnAdminCompose')?.addEventListener('click', () => openCompose());
        document.getElementById('btnAdminSendEmail')?.addEventListener('click', sendEmail);
        document.getElementById('emailPrev')?.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadList(); } });
        document.getElementById('emailNext')?.addEventListener('click', () => { if (currentPage < totalPagesVal) { currentPage++; loadList(); } });

        // Tab switching
        const tabOrder = ['inbox', 'sent', 'trash'];
        document.querySelectorAll('#emailTabs .email-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                if (tab === currentTab) return;
                currentTab = tab;
                currentPage = 1;
                selectedId = null;
                document.querySelectorAll('#emailTabs .email-tab').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
                const slider = document.getElementById('emailTabsSlider');
                if (slider) { slider.className = 'email-tabs-slider'; const idx = tabOrder.indexOf(tab); if (idx > 0) slider.classList.add('pos-' + idx); }
                document.getElementById('emailDetailCard').innerHTML = '<div class="adm-email-empty email-empty-padded"><i class="bi bi-envelope-open email-empty-icon"></i><p class="mb-0 email-empty-text">Sélectionnez un message</p></div>';
                loadList();
            });
        });

        // Attachment button + file input
        document.getElementById('btnAdminAttach')?.addEventListener('click', () => {
            document.getElementById('adminComposeFile')?.click();
        });
        document.getElementById('adminComposeFile')?.addEventListener('change', handleFileSelect);

        // Compose panel controls
        document.getElementById('adminComposeMinimize')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('adminComposePanel')?.classList.toggle('minimized');
        });
        document.getElementById('adminComposeExpand')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const panel = document.getElementById('adminComposePanel');
            const btn = document.getElementById('adminComposeExpand');
            panel?.classList.toggle('expanded');
            const isExpanded = panel?.classList.contains('expanded');
            btn.innerHTML = isExpanded ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-arrows-fullscreen"></i>';
            btn.title = isExpanded ? 'Réduire' : 'Agrandir';
        });
        document.getElementById('adminComposePanelHeader')?.addEventListener('click', () => {
            const panel = document.getElementById('adminComposePanel');
            if (panel?.classList.contains('minimized')) panel.classList.remove('minimized');
        });
        document.getElementById('adminComposeClose')?.addEventListener('click', (e) => {
            e.stopPropagation();
            hideComposePanel();
        });
        document.getElementById('adminComposeDiscard')?.addEventListener('click', () => {
            hideComposePanel();
        });

        // Hook into topbar search for email filtering
        const topbarInput = document.getElementById('topbarSearchInput');
        if (topbarInput) {
            topbarInput.dataset.origPlaceholder = topbarInput.placeholder;
            topbarInput.placeholder = 'Rechercher un message...';
            topbarInput.value = '';
            topbarInput.addEventListener('input', onTopbarSearch);
        }

        loadList();
    }

    function onTopbarSearch() {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        // Hide the normal user search results dropdown
        const resultsPanel = document.getElementById('topbarSearchResults');
        if (resultsPanel) resultsPanel.classList.remove('show');
        searchTimer = setTimeout(() => { currentSearch = q; currentPage = 1; loadList(); }, 300);
    }

    // Cleanup: restore topbar when leaving page
    window._cleanupMessagesPage = function() {
        const topbarInput = document.getElementById('topbarSearchInput');
        if (topbarInput) {
            topbarInput.removeEventListener('input', onTopbarSearch);
            topbarInput.placeholder = topbarInput.dataset.origPlaceholder || 'Rechercher un collaborateur...';
            topbarInput.value = '';
        }
        hideComposePanel();
    };

    async function loadStats() {
        const res = await adminApiPost('admin_get_message_stats');
        if (!res.success) return;
        const s = res.stats;
        document.getElementById('emailStatsLine').textContent =
            `${s.total} messages · ${s.today} aujourd'hui · ${s.unread} non lu(s) · ${s.attachments} pièce(s) jointe(s)`;
        const badge = document.getElementById('badgeInbox');
        if (badge) badge.textContent = parseInt(s.unread) > 0 ? s.unread : '';
        const trashBadge = document.getElementById('badgeTrash');
        if (trashBadge) trashBadge.textContent = parseInt(s.trash) > 0 ? s.trash : '';
    }

    function clearDetailAndReload() {
        selectedId = null;
        document.getElementById('emailDetailCard').innerHTML = '<div class="adm-email-empty email-empty-padded"><i class="bi bi-envelope-open email-empty-icon"></i><p class="mb-0 email-empty-text">Sélectionnez un message</p></div>';
        loadList();
        loadStats();
    }

    function renderAdminPagination() {
        const dotsEl = document.getElementById('emailPagDots');
        const prevBtn = document.getElementById('emailPrev');
        const nextBtn = document.getElementById('emailNext');
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPagesVal;
        if (dotsEl) {
            dotsEl.innerHTML = '';
            for (let i = 1; i <= totalPagesVal; i++) {
                const dot = document.createElement('span');
                dot.className = 'email-pag-dot' + (i === currentPage ? ' active' : '');
                dot.addEventListener('click', () => { currentPage = i; loadList(); });
                dotsEl.appendChild(dot);
            }
        }
    }

    async function loadList() {
        const container = document.getElementById('emailListContainer');
        container.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>';

        const res = await adminApiPost('admin_get_all_messages', { page: currentPage, search: currentSearch, tab: currentTab });
        const emails = res.messages || [];
        totalEmails = res.total || 0;
        const limit = 50;
        totalPagesVal = Math.ceil(totalEmails / limit) || 1;

        renderAdminPagination();

        if (!emails.length) {
            container.innerHTML = currentTab === 'trash'
                ? '<div class="text-center text-muted py-4"><i class="bi bi-trash3" style="font-size:1.5rem;opacity:.2"></i><p class="mt-2 mb-0">La corbeille est vide</p></div>'
                : '<div class="text-center text-muted py-4"><i class="bi bi-envelope-open email-empty-icon--sm"></i><p class="mt-1 mb-0">Aucun message</p></div>';
            return;
        }

        const initials = (name) => {
            const parts = name.split(' ');
            return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
        };

        // Trash toolbar
        let toolbarHtml = '';
        if (currentTab === 'trash') {
            toolbarHtml = `<div class="d-flex align-items-center gap-1 px-2 py-1" style="border-bottom:1px solid var(--cl-border-light);font-size:.7rem">
                <label class="mb-0" style="cursor:pointer;padding:2px 4px"><input type="checkbox" id="trashSelectAll"></label>
                <button class="adm-email-btn-delete" id="trashDeleteSel" style="font-size:.68rem;padding:2px 6px" disabled><i class="bi bi-trash3"></i> Supprimer</button>
                <button class="adm-email-btn-delete" id="trashEmptyAll" style="font-size:.68rem;padding:2px 6px;margin-left:auto"><i class="bi bi-trash3-fill"></i> Vider</button>
            </div>`;
        }

        const itemsHtml = emails.map(e => {
            const date = formatEmailDate(e.created_at);
            const hasUnread = parseInt(e.my_read) === 0;
            const fromName = (e.from_prenom || '') + ' ' + (e.from_nom || '');
            const toName = e.to_names || '—';
            const isTrash = currentTab === 'trash';
            const checkbox = isTrash ? `<input type="checkbox" class="trash-cb" data-id="${e.id}">` : '';
            return `
                <div class="adm-email-item ${e.id === selectedId ? 'selected' : ''} ${hasUnread ? 'unread' : ''}" data-id="${e.id}" ${!isTrash ? 'style="grid-template-columns:34px 1fr"' : ''}>
                    ${checkbox}
                    <div class="adm-email-item-avatar">${e.from_photo ? `<img src="${escapeHtml(e.from_photo)}" alt="">` : escapeHtml(initials(fromName))}</div>
                    <div class="adm-email-item-content">
                        <div class="adm-email-item-top">
                            <span class="adm-email-item-sender">${escapeHtml(fromName)}</span>
                            <span class="adm-email-item-date">${date}</span>
                        </div>
                        <div class="adm-email-item-subject">${escapeHtml(e.sujet)}</div>
                        <div class="adm-email-item-preview">
                            <span class="adm-email-item-to"><i class="bi bi-arrow-right-short"></i>${escapeHtml(toName)}</span>
                            ${parseInt(e.nb_attachments) > 0 ? '<i class="bi bi-paperclip"></i>' : ''}
                        </div>
                    </div>
                </div>`;
        }).join('');

        container.innerHTML = toolbarHtml + itemsHtml;

        // Item click → load detail
        container.querySelectorAll('.adm-email-item').forEach(el => {
            el.addEventListener('click', (ev) => {
                if (ev.target.closest('.trash-cb')) return;
                selectedId = el.dataset.id;
                container.querySelectorAll('.adm-email-item').forEach(x => x.classList.remove('selected'));
                el.classList.add('selected');
                el.classList.remove('unread');
                loadDetail(selectedId);
            });
        });

        // Trash toolbar events
        if (currentTab === 'trash') {
            const selAllCb = container.querySelector('#trashSelectAll');
            const delSelBtn = container.querySelector('#trashDeleteSel');
            const emptyBtn = container.querySelector('#trashEmptyAll');

            selAllCb?.addEventListener('change', () => {
                container.querySelectorAll('.trash-cb').forEach(cb => { cb.checked = selAllCb.checked; });
                delSelBtn.disabled = !selAllCb.checked;
            });
            container.querySelectorAll('.trash-cb').forEach(cb => {
                cb.addEventListener('change', () => {
                    const checked = container.querySelectorAll('.trash-cb:checked');
                    delSelBtn.disabled = checked.length === 0;
                    selAllCb.checked = checked.length === container.querySelectorAll('.trash-cb').length;
                });
            });

            delSelBtn?.addEventListener('click', async () => {
                const ids = Array.from(container.querySelectorAll('.trash-cb:checked')).map(cb => cb.dataset.id);
                if (!ids.length) return;
                if (!await adminConfirm({ title: 'Supprimer définitivement', text: `Supprimer définitivement <strong>${ids.length}</strong> message(s) ? Cette action est irréversible.`, icon: 'bi-trash3', type: 'danger', okText: 'Supprimer définitivement' })) return;
                for (const id of ids) await adminApiPost('admin_purge_message', { id });
                showToast(`${ids.length} message(s) supprimé(s)`, 'success');
                clearDetailAndReload();
            });

            emptyBtn?.addEventListener('click', async () => {
                if (!await adminConfirm({ title: 'Vider la corbeille', text: 'Tous les messages de la corbeille seront supprimés définitivement. Cette action est irréversible.', icon: 'bi-trash3-fill', type: 'danger', okText: 'Vider la corbeille' })) return;
                const r = await adminApiPost('admin_purge_all_trash');
                if (r.success) { showToast('Corbeille vidée', 'success'); clearDetailAndReload(); }
                else showToast(r.message || 'Erreur', 'error');
            });
        }
    }

    async function loadDetail(id) {
        const card = document.getElementById('emailDetailCard');
        card.innerHTML = '<div class="card-body text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const res = await adminApiPost('admin_get_message_detail', { id });
        if (!res.success || !res.email) {
            card.innerHTML = '<div class="card-body text-center text-muted py-4"><i class="bi bi-exclamation-triangle"></i> Email non trouvé</div>';
            return;
        }
        // Refresh unread badges (message was just marked as read)
        if (window.__ztRefreshUnread) window.__ztRefreshUnread();
        // Refresh page-level stats (Réception tab badge + stats line)
        loadStats();

        const e = res.email;
        const recipients = res.recipients || [];
        const attachments = res.attachments || [];
        const thread = res.thread || [];

        const toList = recipients.filter(r => r.type === 'to');
        const ccList = recipients.filter(r => r.type === 'cc');
        const date = new Date(e.created_at).toLocaleDateString('fr-CH', {
            day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        const initials = ((e.from_prenom || '')[0] || '') + ((e.from_nom || '')[0] || '');

        // Recipients HTML with read status
        const recipientHtml = (list, label) => {
            if (!list.length) return '';
            return `<div class="email-recipient-line"><span class="text-muted">${label} :</span> ${list.map(r => {
                const readIcon = r.lu
                    ? `<i class="bi bi-check2 text-success" title="Lu le ${r.lu_at ? new Date(r.lu_at).toLocaleString('fr-CH') : ''}"></i>`
                    : '<i class="bi bi-clock text-warning" title="Non lu"></i>';
                return `${escapeHtml(r.prenom + ' ' + r.nom)} ${readIcon}`;
            }).join(', ')}</div>`;
        };

        // Attachments
        function getFileColorClass(mime) {
            if (!mime) return 'text-muted';
            if (mime.includes('pdf')) return 'text-danger';
            if (mime.includes('word') || mime.includes('document')) return 'text-primary';
            if (mime.includes('excel') || mime.includes('sheet')) return 'text-success';
            if (mime.includes('text') || mime.includes('csv')) return 'text-secondary';
            return 'text-muted';
        }
        const attHtml = attachments.length ? `
            <div class="mt-3 p-3 bg-light rounded">
                <div class="fw-bold mb-2 email-att-title"><i class="bi bi-paperclip"></i> ${attachments.length} pièce(s) jointe(s)</div>
                <div class="email-att-grid">
                ${attachments.map(a => {
                    const isImg = a.mime_type && a.mime_type.startsWith('image/');
                    const dlUrl = '/spocspace/admin/api.php?action=admin_download_message_attachment&id=' + encodeURIComponent(a.id);
                    const thumb = isImg
                        ? `<img src="${dlUrl}" alt="">`
                        : `<i class="bi ${getFileIcon(a.mime_type)} ${getFileColorClass(a.mime_type)}"></i>`;
                    return `
                    <a href="${dlUrl}" ${isImg ? 'target="_blank"' : 'download'} class="email-att-card" title="${escapeHtml(a.original_name)}">
                        <div class="email-att-thumb">${thumb}</div>
                        <div class="email-att-info">
                            <div class="email-att-name">${escapeHtml(a.original_name)}</div>
                            <div class="email-att-size">${formatSize(a.size)}</div>
                        </div>
                    </a>`;
                }).join('')}
                </div>
            </div>` : '';

        // Thread
        let threadHtml = '';
        if (thread.length > 1) {
            const earlier = thread.filter(t => t.id !== e.id);
            if (earlier.length) {
                threadHtml = `
                    <div class="mt-3">
                        <div class="fw-bold text-muted mb-2 email-thread-title"><i class="bi bi-chat-left-text"></i> ${earlier.length} message(s) précédent(s) dans le fil</div>
                        ${earlier.map(t => `
                            <div class="border-start border-3 ps-3 mb-2">
                                <div class="d-flex justify-content-between email-thread-meta">
                                    <strong>${escapeHtml(t.from_prenom + ' ' + t.from_nom)}</strong>
                                    <small class="text-muted">${formatEmailDate(t.created_at)}</small>
                                </div>
                                <div class="email-thread-body">${t.contenu}</div>
                            </div>
                        `).join('')}
                    </div>`;
            }
        }

        card.innerHTML = `
            <div class="adm-email-detail-header">
                <h5 class="adm-email-detail-subject">${escapeHtml(e.sujet)}</h5>
                <div class="adm-email-detail-actions">
                    <button class="adm-email-btn-delete" id="btnDetailDelete" title="${currentTab === 'trash' ? 'Supprimer définitivement' : 'Corbeille'}"><i class="bi bi-trash3"></i>${currentTab === 'trash' ? ' Supprimer' : ''}</button>
                    ${currentTab === 'trash'
                        ? '<button class="adm-email-btn" id="btnDetailRestore"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</button>'
                        : '<button class="adm-email-btn" id="btnDetailForward"><i class="bi bi-forward"></i> Transférer</button>'
                        + '<button class="adm-email-btn" id="btnDetailReplyAll"><i class="bi bi-reply-all"></i> Rép. tous</button>'
                        + '<button class="adm-email-btn" id="btnDetailReply"><i class="bi bi-reply"></i> Répondre</button>'
                    }
                </div>
            </div>
            <div class="adm-email-detail-body">
                <div class="adm-email-detail-sender">
                    <div class="adm-email-detail-avatar">${escapeHtml(initials.toUpperCase())}</div>
                    <div class="adm-email-detail-sender-info">
                        <div class="adm-email-detail-sender-name">${escapeHtml(e.from_prenom + ' ' + e.from_nom)}</div>
                        <div class="adm-email-detail-sender-email">&lt;${escapeHtml(e.from_email || '')}&gt;</div>
                        ${recipientHtml(toList, 'À')}
                        ${recipientHtml(ccList, 'Cc')}
                    </div>
                    <div class="adm-email-detail-date">${date}</div>
                </div>
                <div class="adm-email-detail-content">
                    <div class="adm-email-detail-text">${e.contenu}</div>
                    ${attHtml}
                </div>
                ${threadHtml}
            </div>`;

        // Button events
        card.querySelector('#btnDetailReply')?.addEventListener('click', () => {
            openReply(e, recipients, false);
        });
        card.querySelector('#btnDetailReplyAll')?.addEventListener('click', () => {
            openReply(e, recipients, true);
        });
        card.querySelector('#btnDetailForward')?.addEventListener('click', () => {
            openForward(e);
        });
        card.querySelector('#btnDetailDelete')?.addEventListener('click', async () => {
            if (currentTab === 'trash') {
                // Permanent delete from trash
                if (!await adminConfirm({ title: 'Supprimer définitivement', text: 'Ce message sera supprimé définitivement. Cette action est irréversible.', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer définitivement' })) return;
                const r = await adminApiPost('admin_purge_message', { id: e.id });
                if (r.success) { showToast('Message supprimé définitivement', 'success'); clearDetailAndReload(); }
                else showToast(r.message || 'Erreur', 'error');
            } else {
                // Soft delete → move to trash
                if (!await adminConfirm({ title: 'Mettre à la corbeille', text: 'Déplacer ce message dans la corbeille ?', icon: 'bi-trash3', type: 'warning', okText: 'Mettre à la corbeille' })) return;
                const r = await adminApiPost('admin_delete_message', { id: e.id });
                if (r.success) { showToast('Message déplacé dans la corbeille', 'success'); clearDetailAndReload(); }
                else showToast(r.message || 'Erreur', 'error');
            }
        });
        // Restore button (only in trash view)
        card.querySelector('#btnDetailRestore')?.addEventListener('click', async () => {
            const r = await adminApiPost('admin_restore_message', { id: e.id });
            if (r.success) { showToast('Message restauré', 'success'); clearDetailAndReload(); }
            else showToast(r.message || 'Erreur', 'error');
        });
    }

    async function openCompose(prefill) {
        prefill = prefill || {};
        toSelected = prefill.to || [];
        ccSelected = prefill.cc || [];
        pendingFiles = [];
        renderPendingFiles();

        setupComposeSearch('adminComposeToSearch', 'adminComposeToDropdown', toSelected, 'adminToTags');
        setupComposeSearch('adminComposeCcSearch', 'adminComposeCcDropdown', ccSelected, 'adminCcTags');
        // Clear search inputs
        const toInput = document.getElementById('adminComposeToSearch');
        const ccInput = document.getElementById('adminComposeCcSearch');
        if (toInput) toInput.value = '';
        if (ccInput) ccInput.value = '';
        renderTags(toSelected, 'adminToTags');
        renderTags(ccSelected, 'adminCcTags');

        document.getElementById('adminComposeSubject').value = prefill.subject || '';
        document.getElementById('adminComposeParentId').value = prefill.parentId || '';
        document.getElementById('adminComposePanelTitle').textContent = prefill.title || 'Nouveau message';

        // Init Tiptap editor
        const editorWrap = document.getElementById('adminComposeEditorWrap');
        const em = await getEditorModule();
        if (composeEditor) { em.destroyEditor(composeEditor); composeEditor = null; }
        em.createEditor(editorWrap, {
            placeholder: 'Écrivez votre message...',
            content: prefill.body || '',
            mode: 'mini'
        }).then(ed => { composeEditor = ed; });

        // Show panel
        const panel = document.getElementById('adminComposePanel');
        if (panel) {
            panel.classList.remove('minimized', 'compose-panel--hidden');
            panel.classList.add('compose-panel--visible');
            panel.offsetHeight;
            panel.classList.add('open');
        }
    }

    function hideComposePanel() {
        const panel = document.getElementById('adminComposePanel');
        if (panel) {
            panel.classList.remove('open');
            setTimeout(() => { panel.classList.remove('compose-panel--visible'); panel.classList.add('compose-panel--hidden'); }, 300);
        }
        if (composeEditor && editorModule) { editorModule.destroyEditor(composeEditor); composeEditor = null; }
        pendingFiles = [];
        renderPendingFiles();
    }

    function openReply(email, recipients, replyAll) {
        const to = [email.from_user_id];
        let cc = [];
        if (replyAll) {
            cc = recipients.map(r => r.user_id).filter(id => !to.includes(id));
        }
        const subject = email.sujet.startsWith('RE:') ? email.sujet : 'RE: ' + email.sujet;
        const body = `<br><br><blockquote style="border-left:3px solid #ccc;padding-left:10px;margin-left:0;color:#666">
            <p><small>Le ${formatEmailDate(email.created_at)}, ${escapeHtml(email.from_prenom + ' ' + email.from_nom)} a écrit :</small></p>
            ${email.contenu}
        </blockquote>`;
        openCompose({ to, cc, subject, body, parentId: email.id, title: '<i class="bi bi-reply"></i> Répondre' });
    }

    function openForward(email) {
        const subject = email.sujet.startsWith('FW:') ? email.sujet : 'FW: ' + email.sujet;
        const body = `<br><br><div style="border-top:1px solid #ccc;padding-top:8px;color:#666">
            <small>---------- Message transféré ----------<br>
            <strong>De :</strong> ${escapeHtml(email.from_prenom + ' ' + email.from_nom)}<br>
            <strong>Date :</strong> ${formatEmailDate(email.created_at)}<br>
            <strong>Sujet :</strong> ${escapeHtml(email.sujet)}</small></div>
            ${email.contenu}`;
        openCompose({ to: [], cc: [], subject, body, parentId: email.id, title: '<i class="bi bi-forward"></i> Transférer' });
    }

    async function sendEmail() {
        const sujet = document.getElementById('adminComposeSubject')?.value.trim();
        const contenu = editorModule ? editorModule.getHTML(composeEditor) : '';
        const parentId = document.getElementById('adminComposeParentId')?.value || null;

        if (!sujet) { showToast('Sujet requis', 'error'); return; }
        if (!contenu || contenu === '<br>') { showToast('Message requis', 'error'); return; }
        if (!toSelected.length) { showToast('Au moins un destinataire', 'error'); return; }

        const btn = document.getElementById('btnAdminSendEmail');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Envoi...';

        const res = await adminApiPost('admin_send_message', {
            sujet, contenu,
            to: toSelected,
            cc: ccSelected,
            parent_id: parentId
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Envoyer';

        if (res.success) {
            if (pendingFiles.length && res.id) {
                await uploadAttachments(res.id);
            }
            showToast('Message envoyé', 'success');
            hideComposePanel();
            loadList();
            loadStats();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    // --- Contact search helpers ---
    function setupComposeSearch(inputId, dropdownId, arr, tagsId) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        if (!input || !dropdown) return;
        let activeIdx = -1;

        input.addEventListener('input', () => {
            activeIdx = -1;
            const q = input.value.trim();
            if (q.length < 2) { dropdown.classList.remove('open'); return; }
            renderSearchDropdown(q, dropdown, arr, tagsId, input);
        });

        input.addEventListener('keydown', (e) => {
            const items = dropdown.querySelectorAll('.csd-item');
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); items.forEach((el, i) => el.classList.toggle('active', i === activeIdx)); if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' }); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); items.forEach((el, i) => el.classList.toggle('active', i === activeIdx)); if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' }); }
            else if (e.key === 'Enter') { e.preventDefault(); if (items[activeIdx]) items[activeIdx].click(); }
            else if (e.key === 'Escape') { dropdown.classList.remove('open'); }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.compose-search-wrap')) dropdown.classList.remove('open');
        });
    }

    function renderSearchDropdown(query, dropdown, arr, tagsId, input) {
        const q = query.toLowerCase();
        const filtered = contacts.filter(c =>
            !arr.includes(c.id) &&
            `${c.prenom} ${c.nom} ${c.fonction_nom || ''} ${c.module_nom || ''}`.toLowerCase().includes(q)
        );

        if (!filtered.length) {
            dropdown.innerHTML = '<div class="csd-empty">Aucun collègue trouvé</div>';
            dropdown.classList.add('open');
            return;
        }

        const groups = {};
        filtered.forEach(c => {
            const mod = c.module_nom || 'Sans module';
            if (!groups[mod]) groups[mod] = [];
            groups[mod].push(c);
        });

        let html = '';
        function avatarHtml(c) {
            if (c.photo) return `<img class="csd-avatar" src="${escapeHtml(c.photo)}" alt="">`;
            const ini = (c.prenom?.[0] || '') + (c.nom?.[0] || '');
            return `<span class="csd-initials">${escapeHtml(ini.toUpperCase())}</span>`;
        }
        for (const [mod, members] of Object.entries(groups)) {
            html += `<div class="csd-group">${escapeHtml(mod)}</div>`;
            members.forEach(c => {
                html += `<div class="csd-item" data-id="${c.id}">${avatarHtml(c)}<span>${escapeHtml(c.prenom + ' ' + c.nom)}${c.fonction_nom ? ` <small>— ${escapeHtml(c.fonction_nom)}</small>` : ''}</span></div>`;
            });
        }
        dropdown.innerHTML = html;
        dropdown.classList.add('open');

        dropdown.querySelectorAll('.csd-item').forEach(item => {
            item.addEventListener('click', () => {
                const uid = item.dataset.id;
                if (!arr.includes(uid)) {
                    arr.push(uid);
                    renderTags(arr, tagsId);
                }
                input.value = '';
                dropdown.classList.remove('open');
                input.focus();
            });
        });
    }

    function renderTags(arr, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = arr.map(uid => {
            const c = contacts.find(x => x.id === uid);
            if (!c) return '';
            return `<span class="email-tag">${escapeHtml(c.prenom + ' ' + c.nom)} <button type="button" class="email-tag-remove" data-uid="${uid}">&times;</button></span>`;
        }).join('');
        container.querySelectorAll('.email-tag-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const uid = btn.dataset.uid;
                const idx = arr.indexOf(uid);
                if (idx > -1) arr.splice(idx, 1);
                renderTags(arr, containerId);
            });
        });
    }

    // --- Utility ---
    function formatEmailDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;
        if (diff < 60000) return "À l'instant";
        if (diff < 3600000) return Math.floor(diff / 60000) + ' min';
        if (diff < 86400000 && d.getDate() === now.getDate())
            return d.toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
        if (diff < 172800000) return 'Hier ' + d.toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
        return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
    }

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return tmp.textContent || '';
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
        return (bytes / 1048576).toFixed(1) + ' Mo';
    }

    function getFileIcon(mime) {
        if (!mime) return 'bi-file-earmark';
        if (mime.startsWith('image/')) return 'bi-file-image';
        if (mime.includes('pdf')) return 'bi-file-pdf';
        if (mime.includes('word') || mime.includes('document')) return 'bi-file-word';
        if (mime.includes('excel') || mime.includes('sheet')) return 'bi-file-excel';
        if (mime.includes('text')) return 'bi-file-text';
        return 'bi-file-earmark';
    }

    function handleFileSelect() {
        const input = document.getElementById('adminComposeFile');
        if (!input || !input.files.length) return;
        for (const f of input.files) {
            if (f.size > 5 * 1024 * 1024) { showToast(`${f.name} dépasse 5 Mo`, 'error'); continue; }
            pendingFiles.push(f);
        }
        input.value = '';
        renderPendingFiles();
    }

    function renderPendingFiles() {
        const container = document.getElementById('adminAttPreviewList');
        if (!container) return;
        if (!pendingFiles.length) { container.innerHTML = ''; return; }
        container.innerHTML = pendingFiles.map((f, i) => {
            const isImg = f.type.startsWith('image/');
            const thumb = isImg
                ? `<img src="${URL.createObjectURL(f)}" alt="">`
                : `<i class="bi ${getFileIcon(f.type)}"></i>`;
            return `<div class="att-preview-card">
                <div class="att-preview-thumb">${thumb}</div>
                <div class="att-preview-name" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</div>
                <button class="att-preview-remove" data-idx="${i}">&times;</button>
            </div>`;
        }).join('');
        container.querySelectorAll('.att-preview-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                pendingFiles.splice(parseInt(btn.dataset.idx), 1);
                renderPendingFiles();
            });
        });
    }

    async function uploadAttachments(emailId) {
        for (const f of pendingFiles) {
            const fd = new FormData();
            fd.append('action', 'admin_upload_message_attachment');
            fd.append('email_id', emailId);
            fd.append('file', f);
            await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: fd
            });
        }
    }

    window.initMessagesPage = initMessagesPage;
})();
</script>
