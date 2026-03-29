/**
 * Emails module — Full internal email client
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';
import { createEditor, getHTML, setContent, destroyEditor } from '../rich-editor.js';

let currentTab = 'inbox';
let selectedId = null;
let contacts = [];
let toSelected = [];
let ccSelected = [];
let pendingFiles = [];
let currentDraftId = null;
let draftSaveTimer = null;
let composeEditor = null;
let currentPage = 1;
let totalPages = 1;
const PAGE_LIMIT = 30;

export async function init() {
    // Load contacts from SSR data
    contacts = window.__ZT_PAGE_DATA__?.contacts || [];

    // Setup colleague search dropdowns
    setupColleagueSearch('composeToSearch', 'composeToDropdown', toSelected, 'composeToTags');
    setupColleagueSearch('composeCcSearch', 'composeCcDropdown', ccSelected, 'composeCcTags');

    // Tab switching with slider animation
    const tabs = document.querySelectorAll('.email-tab');
    const slider = document.querySelector('.email-tabs-slider');
    tabs.forEach((btn, idx) => {
        btn.addEventListener('click', () => {
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (slider) {
                slider.classList.toggle('right', idx === 1);
            }
            currentTab = btn.dataset.tab;
            selectedId = null;
            currentPage = 1;
            loadList();
            showEmpty();
        });
    });

    // Compose button
    document.getElementById('btnCompose')?.addEventListener('click', () => openCompose());

    // Pagination arrows
    document.getElementById('emailPrev')?.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadList(); } });
    document.getElementById('emailNext')?.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; loadList(); } });

    // Send button
    document.getElementById('btnSendEmail')?.addEventListener('click', sendEmail);

    // File input
    document.getElementById('composeFile')?.addEventListener('change', handleFileSelect);

    // (colleague search handlers set up in setupColleagueSearch above)

    // Compose panel controls
    document.getElementById('composeMinimize')?.addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('composePanel')?.classList.toggle('minimized');
    });
    document.getElementById('composeFullscreen')?.addEventListener('click', (e) => {
        e.stopPropagation();
        const panel = document.getElementById('composePanel');
        const btn = document.getElementById('composeFullscreen');
        if (!panel || !btn) return;
        panel.classList.toggle('fullscreen');
        const icon = btn.querySelector('i');
        if (panel.classList.contains('fullscreen')) {
            icon.className = 'bi bi-fullscreen-exit';
            btn.title = 'Réduire';
        } else {
            icon.className = 'bi bi-arrows-fullscreen';
            btn.title = 'Agrandir';
        }
    });
    document.getElementById('composePanelHeader')?.addEventListener('click', () => {
        const panel = document.getElementById('composePanel');
        if (panel?.classList.contains('minimized')) panel.classList.remove('minimized');
    });
    document.getElementById('composeClose')?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeCompose();
    });
    document.getElementById('composeDiscard')?.addEventListener('click', discardDraft);

    // Load inbox
    await loadList();
    updateUnreadBadge();
}

function setupColleagueSearch(inputId, dropdownId, selectedArr, tagsContainerId) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    let activeIdx = -1;

    input.addEventListener('input', () => {
        activeIdx = -1;
        renderDropdown(input.value.trim(), dropdown, selectedArr, tagsContainerId, input);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length > 0) renderDropdown(input.value.trim(), dropdown, selectedArr, tagsContainerId, input);
    });

    input.addEventListener('keydown', (e) => {
        const items = dropdown.querySelectorAll('.cd-item');
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); highlightItem(items, activeIdx); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlightItem(items, activeIdx); }
        else if (e.key === 'Enter') { e.preventDefault(); if (items[activeIdx]) items[activeIdx].click(); }
        else if (e.key === 'Escape') { dropdown.classList.remove('open'); }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.colleague-search-wrap')) dropdown.classList.remove('open');
    });
}

function renderDropdown(query, dropdown, selectedArr, tagsContainerId, input) {
    const q = query.toLowerCase();
    const filtered = contacts.filter(c =>
        !selectedArr.includes(c.id) &&
        (q.length === 0 || `${c.prenom} ${c.nom} ${c.fonction_nom || ''} ${c.module_nom || ''}`.toLowerCase().includes(q))
    );

    if (q.length === 0 && filtered.length === 0) { dropdown.classList.remove('open'); return; }
    if (q.length > 0 && filtered.length === 0) {
        dropdown.innerHTML = '<div class="cd-empty">Aucun collègue trouvé</div>';
        dropdown.classList.add('open');
        return;
    }

    // Group by module
    const groups = {};
    filtered.forEach(c => {
        const mod = c.module_nom || 'Sans module';
        if (!groups[mod]) groups[mod] = [];
        groups[mod].push(c);
    });

    let html = '';
    for (const [mod, members] of Object.entries(groups)) {
        html += `<div class="cd-group">${escapeHtml(mod)}</div>`;
        members.forEach(c => {
            html += `<div class="cd-item" data-id="${c.id}">${escapeHtml(c.prenom + ' ' + c.nom)}${c.fonction_nom ? ` <small style="color:var(--zt-text-muted)">— ${escapeHtml(c.fonction_nom)}</small>` : ''}</div>`;
        });
    }
    dropdown.innerHTML = html;
    dropdown.classList.add('open');

    dropdown.querySelectorAll('.cd-item').forEach(item => {
        item.addEventListener('click', () => {
            const uid = item.dataset.id;
            if (!selectedArr.includes(uid)) {
                selectedArr.push(uid);
                renderTags(selectedArr, tagsContainerId);
            }
            input.value = '';
            dropdown.classList.remove('open');
        });
    });
}

function highlightItem(items, idx) {
    items.forEach((el, i) => el.classList.toggle('active', i === idx));
    if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
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

async function loadList() {
    const container = document.getElementById('emailListContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>';

    let res;
    if (currentTab === 'inbox') {
        res = await apiPost('get_inbox', { page: currentPage });
    } else {
        res = await apiPost('get_sent', { page: currentPage });
    }

    const emails = res.emails || [];
    const total = res.total ?? emails.length;
    totalPages = Math.ceil(total / PAGE_LIMIT) || 1;
    renderPagination();

    if (!emails.length) {
        container.innerHTML = `<div class="text-center text-muted py-4"><i class="bi bi-envelope-open" style="font-size:1.5rem;opacity:.3"></i><p class="mt-1 mb-0">${currentTab === 'inbox' ? 'Aucun email reçu' : 'Aucun email envoyé'}</p></div>`;
        return;
    }

    const initials = (name) => {
        const parts = name.split(' ');
        return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
    };

    container.innerHTML = emails.map(e => {
        const isUnread = currentTab === 'inbox' && !e.lu;
        const date = formatEmailDate(e.created_at);
        const preview = stripHtml(e.contenu).substring(0, 80);
        const fromName = currentTab === 'inbox'
            ? (e.from_prenom || '') + ' ' + (e.from_nom || '')
            : (e.to_names || 'Destinataire(s)');

        return `
            <div class="adm-email-item ${isUnread ? 'unread' : ''} ${e.id === selectedId ? 'selected' : ''}" data-id="${e.id}">
                <div class="adm-email-item-avatar">${escapeHtml(initials(fromName))}</div>
                <div class="adm-email-item-content">
                    <div class="adm-email-item-top">
                        <span class="adm-email-item-sender">${escapeHtml(fromName)}</span>
                        <span class="adm-email-item-date">${date}</span>
                    </div>
                    <div class="adm-email-item-subject">${escapeHtml(e.sujet)}</div>
                    <div class="adm-email-item-preview">${escapeHtml(preview)}
                        ${e.nb_attachments > 0 ? ' <i class="bi bi-paperclip"></i>' : ''}
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

    // Auto-select first
    if (!selectedId && emails.length) {
        const first = container.querySelector('.adm-email-item');
        if (first) first.click();
    }
}

async function loadDetail(id) {
    const panel = document.getElementById('emailDetailPanel');
    if (!panel) return;
    panel.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';

    const res = await apiPost('get_message_detail', { id });
    if (!res.success || !res.email) {
        panel.innerHTML = '<div class="adm-email-empty"><i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px"></i><p class="mb-0" style="font-size:.88rem">Email non trouvé</p></div>';
        return;
    }

    const e = res.email;
    const recipients = res.recipients || [];
    const attachments = res.attachments || [];
    const thread = res.thread || [];

    const toList = recipients.filter(r => r.type === 'to').map(r => escapeHtml(r.prenom + ' ' + r.nom)).join(', ');
    const ccList = recipients.filter(r => r.type === 'cc').map(r => escapeHtml(r.prenom + ' ' + r.nom)).join(', ');

    const date = new Date(e.created_at).toLocaleDateString('fr-CH', {
        day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    const initials = ((e.from_prenom || '')[0] || '') + ((e.from_nom || '')[0] || '');

    // Attachments HTML
    const attHtml = attachments.length ? `
        <div class="email-detail-attachments">
            <div class="email-detail-att-label"><i class="bi bi-paperclip"></i> ${attachments.length} pièce(s) jointe(s)</div>
            <div class="email-att-grid">
            ${attachments.map(a => {
                const isImg = a.mime_type && a.mime_type.startsWith('image/');
                const dlUrl = '/zerdatime/api.php?action=download_attachment&id=' + encodeURIComponent(a.id);
                const thumb = isImg
                    ? `<img src="${dlUrl}" class="att-preview-img" alt="">`
                    : `<i class="bi ${getFileIcon(a.mime_type)} att-preview-icon ${getFileColorClass(a.mime_type)}"></i>`;
                return `
                <a href="#" class="att-preview-card email-att-dl" data-att-id="${a.id}" title="${escapeHtml(a.original_name)}">
                    <div class="att-preview-thumb">${thumb}</div>
                    <div class="att-preview-info">
                        <span class="att-preview-name">${escapeHtml(a.original_name)}</span>
                        <span class="att-preview-size">${formatSize(a.size)}</span>
                    </div>
                </a>`;
            }).join('')}
            </div>
        </div>` : '';

    // Thread HTML
    let threadHtml = '';
    if (thread.length > 1) {
        const earlier = thread.filter(t => t.id !== e.id);
        if (earlier.length) {
            threadHtml = `
                <div class="email-thread">
                    <div class="email-thread-label"><i class="bi bi-chat-left-text"></i> ${earlier.length} message(s) précédent(s)</div>
                    ${earlier.map(t => `
                        <div class="email-thread-item ${t.id === e.id ? 'current' : ''}">
                            <div class="email-thread-header">
                                <strong>${escapeHtml(t.from_prenom + ' ' + t.from_nom)}</strong>
                                <small class="text-muted">${formatEmailDate(t.created_at)}</small>
                            </div>
                            <div class="email-thread-body">${t.contenu}</div>
                        </div>
                    `).join('')}
                </div>`;
        }
    }

    panel.innerHTML = `
        <div class="adm-email-detail-header">
            <h5 class="adm-email-detail-subject">${escapeHtml(e.sujet)}</h5>
            <div class="adm-email-detail-actions">
                <button class="adm-email-btn adm-email-btn-delete" id="btnDelete" title="Supprimer"><i class="bi bi-trash"></i> Supprimer</button>
                <button class="adm-email-btn" id="btnForward"><i class="bi bi-forward"></i> Transférer</button>
                <button class="adm-email-btn" id="btnReplyAll"><i class="bi bi-reply-all"></i> Rép. tous</button>
                <button class="adm-email-btn" id="btnReply"><i class="bi bi-reply"></i> Répondre</button>
            </div>
        </div>
        <div class="adm-email-detail-body">
            <div class="adm-email-detail-sender">
                <div class="adm-email-detail-avatar">${escapeHtml(initials.toUpperCase())}</div>
                <div class="adm-email-detail-sender-info">
                    <div class="adm-email-detail-sender-name">${escapeHtml(e.from_prenom + ' ' + e.from_nom)}</div>
                    <div class="adm-email-detail-sender-recipients">
                        <span>À : ${toList || '—'}</span>
                        ${ccList ? `<span class="ms-2">Cc : ${ccList}</span>` : ''}
                    </div>
                </div>
                <div class="adm-email-detail-date">${date}</div>
            </div>
            <div class="adm-email-detail-content">
                <div class="adm-email-detail-text">${e.contenu}</div>
                ${attHtml}
            </div>
            ${threadHtml}
        </div>`;

    // Action handlers
    panel.querySelector('#btnReply')?.addEventListener('click', () => {
        openReply(e, recipients, false);
    });
    panel.querySelector('#btnReplyAll')?.addEventListener('click', () => {
        openReply(e, recipients, true);
    });
    panel.querySelector('#btnForward')?.addEventListener('click', () => {
        openForward(e);
    });
    panel.querySelector('#btnDelete')?.addEventListener('click', async () => {
        if (!confirm('Supprimer cet email ?')) return;
        const r = await apiPost('delete_message', { id: e.id });
        if (r.success) {
            toast('Email supprimé');
            selectedId = null;
            showEmpty();
            loadList();
            updateUnreadBadge();
        }
    });

    // Attachment download
    panel.querySelectorAll('.email-att-dl').forEach(el => {
        el.addEventListener('click', async (ev) => {
            ev.preventDefault();
            const a = document.createElement('a');
            a.href = '/zerdatime/api.php?action=download_attachment&id=' + encodeURIComponent(el.dataset.attId);
            a.download = '';
            a.click();
        });
    });

    updateUnreadBadge();
}

async function openCompose(prefill = {}) {
    toSelected = prefill.to || [];
    ccSelected = prefill.cc || [];
    pendingFiles = [];
    currentDraftId = prefill.draftId || null;

    document.getElementById('composeSubject').value = prefill.subject || '';
    document.getElementById('composeParentId').value = prefill.parentId || '';
    document.getElementById('composeDraftId').value = currentDraftId || '';
    document.getElementById('composeAttachments').innerHTML = '';
    document.getElementById('composePanelTitle').textContent = prefill.title || 'Nouveau message';

    // Init or reset Tiptap editor
    const editorWrap = document.getElementById('composeEditorWrap');
    if (composeEditor) { destroyEditor(composeEditor); composeEditor = null; }
    composeEditor = await createEditor(editorWrap, {
        placeholder: 'Écrivez votre message...',
        content: prefill.body || '',
        mode: 'mini'
    });

    renderTags(toSelected, 'composeToTags');
    renderTags(ccSelected, 'composeCcTags');
    // Clear search inputs
    const toInput = document.getElementById('composeToSearch');
    const ccInput = document.getElementById('composeCcSearch');
    if (toInput) toInput.value = '';
    if (ccInput) ccInput.value = '';

    const panel = document.getElementById('composePanel');
    if (panel) {
        panel.classList.remove('minimized');
        panel.style.display = 'flex';
        // Force reflow then animate
        panel.offsetHeight;
        panel.classList.add('open');
    }
}

async function closeCompose() {
    // Auto-save draft if there's content
    const sujet = document.getElementById('composeSubject')?.value.trim();
    const contenu = getHTML(composeEditor);
    const hasContent = sujet || (contenu && contenu !== '<br>');

    if (hasContent) {
        await saveDraft();
        toast('Brouillon sauvegardé');
    }

    hideComposePanel();
}

function hideComposePanel() {
    const panel = document.getElementById('composePanel');
    if (panel) {
        panel.classList.remove('open');
        setTimeout(() => { panel.style.display = 'none'; }, 300);
    }
    if (composeEditor) { destroyEditor(composeEditor); composeEditor = null; }
    currentDraftId = null;
    clearTimeout(draftSaveTimer);
}

async function saveDraft() {
    const sujet = document.getElementById('composeSubject')?.value.trim() || '';
    const contenu = getHTML(composeEditor) || '';
    const parentId = document.getElementById('composeParentId')?.value || null;

    const res = await apiPost('save_draft', {
        draft_id: currentDraftId,
        sujet,
        contenu,
        to: toSelected,
        cc: ccSelected,
        parent_id: parentId,
    });

    if (res.success && res.draft_id) {
        currentDraftId = res.draft_id;
        document.getElementById('composeDraftId').value = currentDraftId;
    }
}

async function discardDraft() {
    if (currentDraftId) {
        await apiPost('delete_draft', { draft_id: currentDraftId });
    }
    hideComposePanel();
    toast('Brouillon supprimé');
}

function openReply(email, recipients, replyAll) {
    const userId = window.__ZT__?.user?.id;
    const to = [email.from_user_id].filter(id => id !== userId);
    let cc = [];
    if (replyAll) {
        const allRecipients = recipients.map(r => r.user_id).filter(id => id !== userId && !to.includes(id));
        cc = allRecipients;
    }
    const subject = email.sujet.startsWith('RE:') ? email.sujet : 'RE: ' + email.sujet;
    const quotedBody = `<br><br><blockquote class="email-quote">
        <p class="email-quote-header">Le ${formatEmailDate(email.created_at)}, ${escapeHtml(email.from_prenom + ' ' + email.from_nom)} a écrit :</p>
        ${email.contenu}
    </blockquote>`;
    openCompose({ to, cc, subject, body: quotedBody, parentId: email.id });
}

function openForward(email) {
    const subject = email.sujet.startsWith('FW:') ? email.sujet : 'FW: ' + email.sujet;
    const fwdBody = `<br><br><div class="email-forward-header">---------- Message transféré ----------<br>
        <strong>De :</strong> ${escapeHtml(email.from_prenom + ' ' + email.from_nom)}<br>
        <strong>Date :</strong> ${formatEmailDate(email.created_at)}<br>
        <strong>Sujet :</strong> ${escapeHtml(email.sujet)}</div>
        ${email.contenu}`;
    openCompose({ to: [], cc: [], subject, body: fwdBody, parentId: email.id });
}

function handleFileSelect(e) {
    const files = Array.from(e.target.files || []);
    files.forEach(f => {
        if (f.size > 5 * 1024 * 1024) {
            toast(`${f.name} dépasse 5 Mo`);
            return;
        }
        pendingFiles.push(f);
    });
    renderPendingFiles();
    e.target.value = '';
}

function renderPendingFiles() {
    const container = document.getElementById('composeAttachments');
    if (!container) return;
    container.innerHTML = pendingFiles.map((f, i) => {
        const isImage = f.type.startsWith('image/');
        let preview;
        if (isImage) {
            const url = URL.createObjectURL(f);
            preview = `<img src="${url}" class="att-preview-img" alt="">`;
        } else {
            preview = `<i class="bi ${getFileIcon(f.type)} att-preview-icon ${getFileColorClass(f.type)}"></i>`;
        }
        return `
        <div class="att-preview-card">
            <button type="button" class="att-preview-remove" data-idx="${i}">&times;</button>
            <div class="att-preview-thumb">${preview}</div>
            <div class="att-preview-info">
                <span class="att-preview-name" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</span>
                <span class="att-preview-size">${formatSize(f.size)}</span>
            </div>
        </div>`;
    }).join('');
    container.querySelectorAll('.att-preview-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingFiles.splice(parseInt(btn.dataset.idx), 1);
            renderPendingFiles();
        });
    });
}

function getFileColorClass(mime) {
    if (!mime) return '';
    if (mime.includes('pdf')) return 'att-icon-pdf';
    if (mime.includes('word') || mime.includes('document')) return 'att-icon-word';
    if (mime.includes('excel') || mime.includes('sheet')) return 'att-icon-excel';
    if (mime.includes('text') || mime.includes('csv')) return 'att-icon-txt';
    if (mime.startsWith('image/')) return 'att-icon-img';
    return '';
}

async function sendEmail() {
    const sujet = document.getElementById('composeSubject')?.value.trim();
    const contenu = getHTML(composeEditor);
    const parentId = document.getElementById('composeParentId')?.value || null;

    if (!sujet) { toast('Sujet requis'); return; }
    if (!contenu || contenu === '<br>') { toast('Message requis'); return; }
    if (!toSelected.length) { toast('Au moins un destinataire'); return; }

    const btn = document.getElementById('btnSendEmail');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Envoi...';

    const res = await apiPost('send_message', {
        sujet,
        contenu,
        to: toSelected,
        cc: ccSelected,
        parent_id: parentId,
        draft_id: currentDraftId || null,
    });

    if (!res.success) {
        toast(res.message || 'Erreur');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Envoyer';
        return;
    }

    // Upload attachments
    if (pendingFiles.length && res.id) {
        for (const file of pendingFiles) {
            const fd = new FormData();
            fd.append('action', 'upload_message_attachment');
            fd.append('email_id', res.id);
            fd.append('file', file);

            try {
                const csrfToken = window.__ZT__?.csrfToken;
                const headers = {};
                if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

                const uploadRes = await fetch('/zerdatime/api.php', {
                    method: 'POST',
                    body: fd,
                    headers,
                });
                const json = await uploadRes.json();
                if (json.csrf) window.__ZT__.csrfToken = json.csrf;
            } catch (err) {
                console.warn('Attachment upload error:', err);
            }
        }
    }

    toast('Email envoyé');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send"></i> Envoyer';
    hideComposePanel();
    pendingFiles = [];
    loadList();
    updateUnreadBadge();
}

function renderPagination() {
    const dotsEl = document.getElementById('emailPagDots');
    const prevBtn = document.getElementById('emailPrev');
    const nextBtn = document.getElementById('emailNext');

    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

    if (dotsEl) {
        dotsEl.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const dot = document.createElement('span');
            dot.className = 'email-pag-dot' + (i === currentPage ? ' active' : '');
            dot.addEventListener('click', () => { currentPage = i; loadList(); });
            dotsEl.appendChild(dot);
        }
    }
}

function showEmpty() {
    const panel = document.getElementById('emailDetailPanel');
    if (panel) {
        panel.innerHTML = '<div class="adm-email-empty" style="padding:60px 20px"><i class="bi bi-envelope-open" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:8px"></i><p class="mb-0" style="font-size:.88rem">Sélectionnez un email pour le lire</p></div>';
    }
}

async function updateUnreadBadge() {
    const res = await apiPost('get_unread_count');
    const count = res.count || 0;

    // Inbox badge
    const inboxBadge = document.getElementById('inboxBadge');
    if (inboxBadge) {
        inboxBadge.textContent = count > 0 ? count : '';
        inboxBadge.style.display = count > 0 ? '' : 'none';
    }

    // Navbar badge
    const navBadge = document.getElementById('emailBadge');
    if (navBadge) {
        navBadge.style.display = count > 0 ? '' : 'none';
    }
    // Also update the old msgBadge for backward compat
    const msgBadge = document.getElementById('msgBadge');
    if (msgBadge) {
        msgBadge.style.display = count > 0 ? '' : 'none';
    }
}

// --- Utility functions ---

function formatEmailDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = now - d;
    if (diff < 60000) return "À l'instant";
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min';
    if (diff < 86400000 && d.getDate() === now.getDate()) {
        return d.toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
    }
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

export function destroy() {
    hideComposePanel();
}
