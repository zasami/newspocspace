<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$emailContacts = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, u.fonction_nom,
            COALESCE(m.nom, 'Sans module') AS module_nom,
            COALESCE(m.ordre, 999) AS module_ordre
     FROM users u
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY module_ordre, m.nom, u.nom, u.prenom"
);

$emailStatsTotal       = (int) Db::getOne("SELECT COUNT(*) FROM emails WHERE is_draft = 0");
$emailStatsToday       = (int) Db::getOne("SELECT COUNT(*) FROM emails WHERE is_draft = 0 AND DATE(created_at) = CURDATE()");
$emailStatsUnread      = (int) Db::getOne("SELECT COUNT(*) FROM email_recipients WHERE lu = 0");
$emailStatsAttachments = (int) Db::getOne("SELECT COUNT(*) FROM email_attachments");
?>
<!-- Admin Emails Page — Split-view email client -->
<link rel="stylesheet" href="/zerdatime/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">

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
</style>

<!-- Grand titre stats + bouton nouveau -->
<div class="email-page-header">
  <div>
    <h4 class="email-page-title"><i class="bi bi-envelope"></i> Emails</h4>
    <div id="emailStatsLine" class="text-muted email-stats-line"><?= $emailStatsTotal ?> emails · <?= $emailStatsToday ?> aujourd'hui · <?= $emailStatsUnread ?> non lu(s) · <?= $emailStatsAttachments ?> pièce(s) jointe(s)</div>
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
        <button class="email-tab active" data-tab="inbox">Boîte de réception <span class="email-unread-badge" id="badgeInbox"><?= $emailStatsUnread > 0 ? $emailStatsUnread : '' ?></span></button>
        <button class="email-tab" data-tab="sent">Envoyés</button>
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
        <p class="mb-0 email-empty-text">Sélectionnez un email pour le lire</p>
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
      <button type="button" class="compose-panel-header-btn" id="adminComposeClose" title="Fermer"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
  <div class="compose-panel-body">
    <div class="compose-field">
      <label>À</label>
      <div class="zs-select" id="adminComposeTo" data-placeholder="— Choisir un destinataire —"></div>
      <div id="adminToTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <label>Cc</label>
      <div class="zs-select" id="adminComposeCc" data-placeholder="— Choisir un destinataire —"></div>
      <div id="adminCcTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <input type="text" class="form-control form-control-sm" id="adminComposeSubject" placeholder="Sujet" maxlength="255">
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
        if (!editorModule) editorModule = await import('/zerdatime/assets/js/rich-editor.js');
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
        document.querySelectorAll('#emailTabs .email-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                if (tab === currentTab) return;
                currentTab = tab;
                currentPage = 1;
                document.querySelectorAll('#emailTabs .email-tab').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
                const slider = document.getElementById('emailTabsSlider');
                if (slider) slider.classList.toggle('right', tab === 'sent');
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
            topbarInput.placeholder = 'Rechercher un email...';
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
        const res = await adminApiPost('admin_get_email_stats');
        if (!res.success) return;
        const s = res.stats;
        document.getElementById('emailStatsLine').textContent =
            `${s.total} emails · ${s.today} aujourd'hui · ${s.unread} non lu(s) · ${s.attachments} pièce(s) jointe(s)`;
        const badge = document.getElementById('badgeInbox');
        if (badge) badge.textContent = parseInt(s.unread) > 0 ? s.unread : '';
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

        const res = await adminApiPost('admin_get_all_emails', { page: currentPage, search: currentSearch, tab: currentTab });
        const emails = res.emails || [];
        totalEmails = res.total || 0;
        const limit = 50;
        totalPagesVal = Math.ceil(totalEmails / limit) || 1;

        renderAdminPagination();

        if (!emails.length) {
            container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-envelope-open email-empty-icon--sm"></i><p class="mt-1 mb-0">Aucun email</p></div>';
            return;
        }

        const initials = (name) => {
            const parts = name.split(' ');
            return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
        };

        container.innerHTML = emails.map(e => {
            const date = formatEmailDate(e.created_at);
            const preview = stripHtml(e.contenu).substring(0, 80);
            const hasUnread = parseInt(e.nb_unread) > 0;
            const fromName = (e.from_prenom || '') + ' ' + (e.from_nom || '');
            const toName = e.to_names || '—';
            return `
                <div class="adm-email-item ${e.id === selectedId ? 'selected' : ''} ${hasUnread ? 'unread' : ''}" data-id="${e.id}">
                    <div class="adm-email-item-avatar">${escapeHtml(initials(fromName))}</div>
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

        container.querySelectorAll('.adm-email-item').forEach(el => {
            el.addEventListener('click', () => {
                selectedId = el.dataset.id;
                container.querySelectorAll('.adm-email-item').forEach(x => x.classList.remove('selected'));
                el.classList.add('selected');
                el.classList.remove('unread');
                loadDetail(selectedId);
            });
        });
    }

    async function loadDetail(id) {
        const card = document.getElementById('emailDetailCard');
        card.innerHTML = '<div class="card-body text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const res = await adminApiPost('admin_get_email_detail', { id });
        if (!res.success || !res.email) {
            card.innerHTML = '<div class="card-body text-center text-muted py-4"><i class="bi bi-exclamation-triangle"></i> Email non trouvé</div>';
            return;
        }

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
                    const dlUrl = '/zerdatime/admin/api.php?action=admin_download_attachment&id=' + encodeURIComponent(a.id);
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
                    <button class="btn btn-sm btn-outline-danger" id="btnDetailDelete" title="Supprimer"><i class="bi bi-trash"></i></button>
                    <button class="adm-email-btn" id="btnDetailForward"><i class="bi bi-forward"></i> Transférer</button>
                    <button class="adm-email-btn" id="btnDetailReplyAll"><i class="bi bi-reply-all"></i> Rép. tous</button>
                    <button class="adm-email-btn" id="btnDetailReply"><i class="bi bi-reply"></i> Répondre</button>
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
            if (!confirm('Supprimer définitivement cet email ?')) return;
            const r = await adminApiPost('admin_delete_email', { id: e.id });
            if (r.success) {
                showToast('Email supprimé', 'success');
                selectedId = null;
                document.getElementById('emailDetailCard').innerHTML = '<div class="adm-email-empty email-empty-padded"><i class="bi bi-envelope-open email-empty-icon"></i><p class="mb-0 email-empty-text">Sélectionnez un email</p></div>';
                loadList();
                loadStats();
            } else {
                showToast(r.message || 'Erreur', 'error');
            }
        });
    }

    async function openCompose(prefill) {
        prefill = prefill || {};
        toSelected = prefill.to || [];
        ccSelected = prefill.cc || [];
        pendingFiles = [];
        renderPendingFiles();

        populateSelect('adminComposeTo');
        populateSelect('adminComposeCc');
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

        const res = await adminApiPost('admin_send_email', {
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
            showToast('Email envoyé', 'success');
            hideComposePanel();
            loadList();
            loadStats();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    // --- Contact helpers ---
    function populateSelect(selectId) {
        const el = document.getElementById(selectId);
        if (!el) return;
        const opts = contacts.map(c => ({
            value: c.id,
            label: `${c.prenom} ${c.nom}${c.fonction_nom ? ' — ' + c.fonction_nom : ''}`
        }));
        const arr = selectId === 'adminComposeTo' ? toSelected : ccSelected;
        const containerId = selectId === 'adminComposeTo' ? 'adminToTags' : 'adminCcTags';
        zerdaSelect.destroy(el);
        zerdaSelect.init(el, opts, {
            value: '',
            search: true,
            onSelect: (val) => {
                if (!val || arr.includes(val)) { zerdaSelect.setValue('#' + selectId, ''); return; }
                arr.push(val);
                zerdaSelect.setValue('#' + selectId, '');
                renderTags(arr, containerId);
            }
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
            fd.append('action', 'admin_upload_attachment');
            fd.append('email_id', emailId);
            fd.append('file', f);
            await fetch('/zerdatime/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__ZT_ADMIN__?.csrfToken || '' },
                body: fd
            });
        }
    }

    window.initMessagesPage = initMessagesPage;
})();
</script>
