<?php
$userId = $_SESSION['zt_user']['id'] ?? '';
$hasConfig = (bool) Db::getOne("SELECT COUNT(*) FROM email_externe_config WHERE user_id = ? AND is_active = 1", [$userId]);
?>
<style>
.ext-layout { display: flex; gap: 0; height: calc(100vh - 80px); margin: -1rem -1.5rem; }
.ext-sidebar { width: 200px; border-right: 1px solid var(--cl-border); background: var(--cl-bg); padding: 12px 8px; flex-shrink: 0; overflow-y: auto; }
.ext-folder { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: .85rem; color: var(--cl-text-secondary); transition: all .15s; }
.ext-folder:hover { background: var(--cl-surface); }
.ext-folder.active { background: var(--cl-accent); color: #fff; font-weight: 600; }
.ext-folder i { font-size: 1rem; width: 18px; text-align: center; }
.ext-folder-badge { margin-left: auto; font-size: .72rem; background: rgba(0,0,0,.1); padding: 1px 6px; border-radius: 10px; }
.ext-folder.active .ext-folder-badge { background: rgba(255,255,255,.2); }

.ext-list { width: 350px; border-right: 1px solid var(--cl-border); overflow-y: auto; flex-shrink: 0; }
.ext-list-header { padding: 12px; border-bottom: 1px solid var(--cl-border); display: flex; align-items: center; gap: 8px; }
.ext-email-row { padding: 12px; border-bottom: 1px solid var(--cl-border-light, #f5f5f0); cursor: pointer; transition: background .15s; }
.ext-email-row:hover { background: var(--cl-bg); }
.ext-email-row.active { background: rgba(25,25,24,.04); border-left: 3px solid var(--cl-accent); }
.ext-email-row.unread .ext-email-from { font-weight: 700; }
.ext-email-from { font-size: .88rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ext-email-subject { font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ext-email-snippet { font-size: .78rem; color: var(--cl-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ext-email-date { font-size: .72rem; color: var(--cl-text-muted); float: right; }

.ext-detail { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.ext-detail-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--cl-text-muted); flex-direction: column; gap: 8px; }
.ext-detail-empty i { font-size: 3rem; opacity: .2; }

/* Gmail-style toolbar */
.ext-toolbar { display: flex; align-items: center; gap: 2px; padding: 8px 16px; border-bottom: 1px solid var(--cl-border); flex-shrink: 0; }
.ext-toolbar-btn { background: none; border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--cl-text-secondary); font-size: 1rem; transition: background .15s; }
.ext-toolbar-btn:hover { background: var(--cl-bg); }
.ext-toolbar-btn.danger:hover { background: #FFEBEE; color: #C53030; }
.ext-toolbar-sep { width: 1px; height: 20px; background: var(--cl-border); margin: 0 6px; }
.ext-toolbar-spacer { flex: 1; }

/* Detail content */
.ext-detail-inner { flex: 1; overflow-y: auto; padding: 20px 24px; background: var(--cl-bg); }
.ext-detail-card { background: var(--cl-surface); border: 1px solid var(--cl-border-light, #f0ede8); border-radius: 10px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.ext-detail-subject { font-size: 1.2rem; font-weight: 700; margin-bottom: 16px; line-height: 1.3; }
.ext-sender-row { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
.ext-sender-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--cl-bg); display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 700; color: var(--cl-text-secondary); flex-shrink: 0; }
.ext-sender-info { flex: 1; min-width: 0; }
.ext-sender-name { font-weight: 700; font-size: .92rem; }
.ext-sender-email { font-size: .8rem; color: var(--cl-text-muted); }
.ext-sender-to { font-size: .78rem; color: var(--cl-text-muted); margin-top: 2px; }
.ext-sender-date { font-size: .78rem; color: var(--cl-text-muted); flex-shrink: 0; white-space: nowrap; }
.ext-detail-body { font-size: .95rem; line-height: 1.6; padding: 16px 0; }
.ext-detail-body img { max-width: 100%; }

/* Attachments */
.ext-att-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--cl-border); }
.ext-att-title { font-size: .82rem; color: var(--cl-text-muted); margin-bottom: 8px; }
.ext-att-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.ext-att-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: var(--cl-bg); border: 1.5px solid var(--cl-border); border-radius: 8px; font-size: .82rem; cursor: pointer; transition: all .15s; }
.ext-att-chip:hover { background: var(--cl-surface); border-color: var(--cl-accent); }
.ext-att-chip i { font-size: 1.1rem; }
.ext-att-chip-pdf i { color: #C53030; }
.ext-att-chip-doc i { color: #1565C0; }
.ext-att-chip-xls i { color: #2E7D32; }
.ext-att-chip-img i { color: #6A1B9A; }
.ext-att-size { font-size: .72rem; color: var(--cl-text-muted); }

/* Reply box at bottom */
.ext-reply-box { padding: 16px 24px; border-top: 1px solid var(--cl-border); flex-shrink: 0; }
.ext-reply-trigger { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 1.5px solid var(--cl-border); border-radius: 8px; cursor: pointer; color: var(--cl-text-muted); font-size: .88rem; transition: all .15s; background: var(--cl-bg); }
.ext-reply-trigger:hover { border-color: var(--cl-accent); color: var(--cl-text); }

.ext-no-config { text-align: center; padding: 60px 20px; }
.ext-no-config i { font-size: 4rem; opacity: .15; display: block; margin-bottom: 16px; }
</style>

<?php if (!$hasConfig): ?>
<div class="ext-no-config">
  <i class="bi bi-mailbox"></i>
  <h5>Email non configuré</h5>
  <p class="text-muted">Configurez votre compte email pour accéder à vos messages.</p>
  <a href="<?= admin_url('email-config') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurer maintenant</a>
</div>
<?php else: ?>

<div class="ext-layout">
  <!-- Folders -->
  <div class="ext-sidebar" id="extFolders">
    <div class="ext-folder active" data-folder="INBOX"><i class="bi bi-inbox"></i> Boîte de réception <span class="ext-folder-badge" id="extInboxCount"></span></div>
    <div id="extFoldersDynamic"></div>
    <hr style="margin:8px 0;opacity:.2">
    <div class="ext-folder" id="extComposeBtn"><i class="bi bi-plus-circle"></i> <strong>Nouveau</strong></div>
    <div class="ext-folder" id="extContactsBtn"><i class="bi bi-person-rolodex"></i> Contacts</div>
  </div>

  <!-- Email list -->
  <div class="ext-list">
    <div class="ext-list-header">
      <button class="btn btn-sm btn-outline-secondary" id="extRefreshBtn"><i class="bi bi-arrow-clockwise"></i></button>
      <span class="text-muted small" id="extListInfo"></span>
      <div class="ms-auto d-flex gap-1">
        <button class="btn btn-sm btn-outline-secondary" id="extPrevBtn" disabled><i class="bi bi-chevron-left"></i></button>
        <button class="btn btn-sm btn-outline-secondary" id="extNextBtn" disabled><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div id="extEmailList"></div>
  </div>

  <!-- Detail pane -->
  <div class="ext-detail" id="extDetail">
    <div class="ext-detail-empty">
      <i class="bi bi-envelope-open"></i>
      <p>Sélectionnez un email</p>
    </div>
  </div>
</div>

<!-- Compose modal for external email -->
<div class="compose-panel" id="extComposePanel">
  <div class="compose-panel-header" id="extComposePanelHeader">
    <span class="compose-panel-title">Nouveau email</span>
    <div class="compose-panel-header-actions">
      <button type="button" class="compose-panel-header-btn" id="extComposeMinimize"><i class="bi bi-dash-lg"></i></button>
      <button type="button" class="compose-panel-header-btn" id="extComposeExpand"><i class="bi bi-arrows-angle-expand"></i></button>
      <button type="button" class="compose-panel-header-btn" id="extComposeClose"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
  <div class="compose-panel-body">
    <div class="compose-field"><label>À</label><input type="text" class="form-control form-control-sm" id="extComposeTo" placeholder="email@exemple.com"></div>
    <div class="compose-field"><label>Cc</label><input type="text" class="form-control form-control-sm" id="extComposeCc" placeholder=""></div>
    <div class="compose-field"><input type="text" class="form-control form-control-sm" id="extComposeSubject" placeholder="Sujet"></div>
    <div id="extComposeEditorWrap" class="zs-editor-wrap compose-editor-wrap"></div>
  </div>
  <div class="compose-panel-footer">
    <button type="button" class="adm-email-btn" id="extComposeSendBtn"><i class="bi bi-send"></i> Envoyer</button>
    <div class="compose-panel-footer-right">
      <button type="button" class="compose-panel-footer-btn compose-panel-delete" id="extComposeDiscard"><i class="bi bi-trash3"></i></button>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let currentFolder = 'INBOX';
    let currentOffset = 0;
    let currentTotal = 0;
    let currentUid = null;
    let extEditor = null;
    let extEditorModule = null;
    const LIMIT = 30;

    // ── Folders ──
    document.getElementById('extFolders')?.addEventListener('click', (e) => {
        const folder = e.target.closest('[data-folder]');
        if (folder) {
            document.querySelectorAll('.ext-folder').forEach(f => f.classList.remove('active'));
            folder.classList.add('active');
            currentFolder = folder.dataset.folder;
            currentOffset = 0;
            loadList();
            return;
        }
    });

    document.getElementById('extComposeBtn')?.addEventListener('click', openExtCompose);
    document.getElementById('extRefreshBtn')?.addEventListener('click', () => loadList());
    document.getElementById('extPrevBtn')?.addEventListener('click', () => { if (currentOffset > 0) { currentOffset -= LIMIT; loadList(); } });
    document.getElementById('extNextBtn')?.addEventListener('click', () => { if (currentOffset + LIMIT < currentTotal) { currentOffset += LIMIT; loadList(); } });

    // ── Load email list ──
    async function loadList() {
        const listEl = document.getElementById('extEmailList');
        listEl.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const res = await adminApiPost('admin_email_ext_fetch_list', { folder: currentFolder, limit: LIMIT, offset: currentOffset });
        if (!res.success) { listEl.innerHTML = '<div class="text-center text-danger py-4">' + escapeHtml(res.message || 'Erreur') + '</div>'; return; }

        currentTotal = res.total;
        document.getElementById('extListInfo').textContent = (currentOffset + 1) + '-' + Math.min(currentOffset + LIMIT, currentTotal) + ' sur ' + currentTotal;
        document.getElementById('extPrevBtn').disabled = currentOffset <= 0;
        document.getElementById('extNextBtn').disabled = currentOffset + LIMIT >= currentTotal;

        if (!res.emails.length) {
            listEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox" style="font-size:2rem;opacity:.2"></i><br>Aucun message</div>';
            return;
        }

        listEl.innerHTML = res.emails.map(e => {
            const d = e.date ? new Date(e.date) : null;
            const dateStr = d ? (d.getDate() + '/' + (d.getMonth()+1) + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0')) : '';
            return '<div class="ext-email-row' + (!e.is_read ? ' unread' : '') + '" data-uid="' + e.uid + '">'
                + '<span class="ext-email-date">' + escapeHtml(dateStr) + '</span>'
                + '<div class="ext-email-from">' + escapeHtml(e.from) + '</div>'
                + '<div class="ext-email-subject">' + escapeHtml(e.subject) + '</div>'
                + '</div>';
        }).join('');

        listEl.querySelectorAll('.ext-email-row').forEach(row => {
            row.addEventListener('click', () => {
                listEl.querySelectorAll('.ext-email-row').forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                row.classList.remove('unread');
                loadEmail(parseInt(row.dataset.uid));
            });
        });
    }

    // ── Load single email ──
    function getAttIcon(filename) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        if (ext === 'pdf') return { icon: 'bi-file-earmark-pdf-fill', cls: 'ext-att-chip-pdf' };
        if (['doc','docx'].includes(ext)) return { icon: 'bi-file-earmark-word-fill', cls: 'ext-att-chip-doc' };
        if (['xls','xlsx'].includes(ext)) return { icon: 'bi-file-earmark-excel-fill', cls: 'ext-att-chip-xls' };
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) return { icon: 'bi-file-earmark-image-fill', cls: 'ext-att-chip-img' };
        return { icon: 'bi-file-earmark', cls: '' };
    }

    function formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' Ko';
        return (bytes / 1048576).toFixed(1) + ' Mo';
    }

    function fmtEmailDate(str) {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d)) return str;
        const pad = n => String(n).padStart(2, '0');
        const mois = ['jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];
        return d.getDate() + ' ' + mois[d.getMonth()] + ' ' + d.getFullYear() + ' à ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    let lastEmailData = null;

    async function loadEmail(uid) {
        currentUid = uid;
        const detail = document.getElementById('extDetail');
        detail.innerHTML = '<div class="ext-detail-empty"><span class="spinner-border"></span></div>';

        const res = await adminApiPost('admin_email_ext_fetch_email', { folder: currentFolder, uid });
        if (!res.success) { detail.innerHTML = '<div class="text-danger p-3">' + escapeHtml(res.message) + '</div>'; return; }
        // Refresh unread badges (email marked as read by IMAP)
        if (window.__ztRefreshUnread) window.__ztRefreshUnread();

        const e = res.email;
        lastEmailData = e;

        const fromInitials = ((e.from_name || e.from || '').charAt(0) || '?').toUpperCase();
        const toStr = (e.to || []).map(t => t.name ? escapeHtml(t.name) : escapeHtml(t.email)).join(', ');
        const ccStr = (e.cc || []).map(t => t.name ? escapeHtml(t.name) : escapeHtml(t.email)).join(', ');

        // Attachments
        let attHtml = '';
        if (e.attachments?.length) {
            attHtml = '<div class="ext-att-section"><div class="ext-att-title"><i class="bi bi-paperclip"></i> ' + e.attachments.length + ' pièce(s) jointe(s)</div><div class="ext-att-grid">';
            e.attachments.forEach(a => {
                const ai = getAttIcon(a.filename);
                attHtml += '<div class="ext-att-chip ' + ai.cls + '" data-uid="' + uid + '" data-part="' + a.index + '">'
                    + '<i class="bi ' + ai.icon + '"></i>'
                    + '<div><div>' + escapeHtml(a.filename) + '</div><div class="ext-att-size">' + formatSize(a.size) + '</div></div>'
                    + '</div>';
            });
            attHtml += '</div></div>';
        }

        detail.innerHTML =
            // Toolbar
            '<div class="ext-toolbar">'
            + '<button class="ext-toolbar-btn" id="extBackBtn" title="Retour"><i class="bi bi-arrow-left"></i></button>'
            + '<div class="ext-toolbar-sep"></div>'
            + '<button class="ext-toolbar-btn" id="extArchiveBtn" title="Archiver"><i class="bi bi-archive"></i></button>'
            + '<button class="ext-toolbar-btn danger" id="extDeleteBtn" title="Supprimer"><i class="bi bi-trash3"></i></button>'
            + '<button class="ext-toolbar-btn" id="extMarkUnreadBtn" title="Marquer non lu"><i class="bi bi-envelope"></i></button>'
            + '<div class="ext-toolbar-sep"></div>'
            + '<button class="ext-toolbar-btn" id="extReplyBtn" title="Répondre"><i class="bi bi-reply"></i></button>'
            + '<button class="ext-toolbar-btn" id="extReplyAllBtn" title="Répondre à tous"><i class="bi bi-reply-all"></i></button>'
            + '<button class="ext-toolbar-btn" id="extForwardBtn" title="Transférer"><i class="bi bi-forward"></i></button>'
            + '<div class="ext-toolbar-spacer"></div>'
            + '<button class="ext-toolbar-btn" id="extPrintBtn" title="Imprimer"><i class="bi bi-printer"></i></button>'
            + '</div>'

            // Content
            + '<div class="ext-detail-inner">'
            + '<div class="ext-detail-subject">' + escapeHtml(e.subject || '(sans sujet)') + '</div>'
            + '<div class="ext-detail-card">'

            // Sender row
            + '<div class="ext-sender-row">'
            + '<div class="ext-sender-avatar">' + escapeHtml(fromInitials) + '</div>'
            + '<div class="ext-sender-info">'
            + '<div class="ext-sender-name">' + escapeHtml(e.from_name || e.from) + '</div>'
            + '<div class="ext-sender-email">&lt;' + escapeHtml(e.from) + '&gt;</div>'
            + '<div class="ext-sender-to">à ' + toStr + (ccStr ? ', cc: ' + ccStr : '') + '</div>'
            + '</div>'
            + '<div class="ext-sender-date">' + fmtEmailDate(e.date) + '</div>'
            + '</div>'

            // Body
            + '<div class="ext-detail-body">' + e.body + '</div>'

            // Attachments
            + attHtml
            + '</div>' // close ext-detail-card
            + '</div>' // close ext-detail-inner

            // Reply trigger at bottom
            + '<div class="ext-reply-box">'
            + '<div class="ext-reply-trigger" id="extReplyTrigger"><i class="bi bi-reply"></i> Cliquez ici pour répondre</div>'
            + '</div>';

        // ── Event handlers ──

        // Back
        detail.querySelector('#extBackBtn')?.addEventListener('click', () => {
            detail.innerHTML = '<div class="ext-detail-empty"><i class="bi bi-envelope-open"></i><p>Sélectionnez un email</p></div>';
            document.querySelectorAll('.ext-email-row').forEach(r => r.classList.remove('active'));
        });

        // Delete
        detail.querySelector('#extDeleteBtn')?.addEventListener('click', async () => {
            const confirmed = await adminConfirm({
                title: 'Supprimer cet email ?',
                text: '<strong>' + escapeHtml(e.subject || '(sans sujet)') + '</strong><br><small class="text-muted">De : ' + escapeHtml(e.from_name || e.from) + '</small>',
                type: 'danger',
                icon: 'bi-trash3',
                okText: 'Supprimer',
                cancelText: 'Annuler'
            });
            if (!confirmed) return;
            await adminApiPost('admin_email_ext_delete', { folder: currentFolder, uid });
            showToast('Email supprimé', 'success');
            detail.innerHTML = '<div class="ext-detail-empty"><i class="bi bi-envelope-open"></i><p>Sélectionnez un email</p></div>';
            loadList();
        });

        // Reply
        function doReply(all) {
            const toAddr = e.from;
            let ccAddrs = '';
            if (all) {
                const allTo = (e.to || []).map(t => t.email).concat((e.cc || []).map(t => t.email));
                ccAddrs = allTo.filter(addr => addr !== e.from).join(', ');
            }
            openExtCompose({
                to: toAddr,
                cc: ccAddrs,
                subject: e.subject.startsWith('RE:') ? e.subject : 'RE: ' + e.subject,
                body: '<br><br><blockquote style="border-left:3px solid #ccc;padding-left:12px;margin:0;color:#666"><p style="font-size:.85rem;color:#999">'
                    + fmtEmailDate(e.date) + ', ' + escapeHtml(e.from_name || e.from) + ' &lt;' + escapeHtml(e.from) + '&gt; :</p>'
                    + e.body + '</blockquote>',
                replyTo: e.from
            });
        }

        detail.querySelector('#extReplyBtn')?.addEventListener('click', () => doReply(false));
        detail.querySelector('#extReplyAllBtn')?.addEventListener('click', () => doReply(true));
        detail.querySelector('#extReplyTrigger')?.addEventListener('click', () => doReply(false));

        // Forward
        detail.querySelector('#extForwardBtn')?.addEventListener('click', () => {
            openExtCompose({
                to: '',
                subject: e.subject.startsWith('FW:') ? e.subject : 'FW: ' + e.subject,
                body: '<br><br><div style="border-top:1px solid #ccc;padding-top:10px;color:#666">'
                    + '<p style="font-size:.85rem"><strong>---------- Message transféré ----------</strong><br>'
                    + '<strong>De :</strong> ' + escapeHtml(e.from_name || '') + ' &lt;' + escapeHtml(e.from) + '&gt;<br>'
                    + '<strong>Date :</strong> ' + fmtEmailDate(e.date) + '<br>'
                    + '<strong>Sujet :</strong> ' + escapeHtml(e.subject) + '<br>'
                    + '<strong>À :</strong> ' + toStr + '</p></div>'
                    + e.body
            });
        });

        // Print
        detail.querySelector('#extPrintBtn')?.addEventListener('click', () => {
            const win = window.open('', '_blank');
            win.document.write('<!DOCTYPE html><html><head><title>' + escapeHtml(e.subject) + '</title>'
                + '<style>body{font-family:Arial,sans-serif;padding:30px;max-width:800px;margin:0 auto}'
                + 'h2{margin-bottom:4px} .meta{color:#666;font-size:13px;margin-bottom:20px;border-bottom:1px solid #ddd;padding-bottom:12px}'
                + '.body{font-size:14px;line-height:1.6} @media print{button{display:none}}</style></head>'
                + '<body><h2>' + escapeHtml(e.subject) + '</h2>'
                + '<div class="meta"><strong>' + escapeHtml(e.from_name || e.from) + '</strong> &lt;' + escapeHtml(e.from) + '&gt;<br>'
                + 'À : ' + toStr + '<br>' + fmtEmailDate(e.date) + '</div>'
                + '<div class="body">' + e.body + '</div>'
                + '<br><button onclick="window.print()">Imprimer</button></body></html>');
            win.document.close();
        });

        // Mark unread (just visual for now)
        detail.querySelector('#extMarkUnreadBtn')?.addEventListener('click', () => {
            const row = document.querySelector('.ext-email-row[data-uid="' + uid + '"]');
            if (row) row.classList.add('unread');
            showToast('Marqué comme non lu', 'success');
        });

        // Attachments
        detail.querySelectorAll('.ext-att-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const url = '/zerdatime/admin/api.php?action=admin_email_ext_download_attachment&folder=' + encodeURIComponent(currentFolder)
                    + '&uid=' + chip.dataset.uid + '&part_index=' + chip.dataset.part;
                window.open(url, '_blank');
            });
        });
    }

    // ── Compose ──
    async function openExtCompose(prefill) {
        prefill = prefill || {};
        document.getElementById('extComposeTo').value = prefill.to || '';
        document.getElementById('extComposeCc').value = prefill.cc || '';
        document.getElementById('extComposeSubject').value = prefill.subject || '';

        if (!extEditorModule) extEditorModule = await import('/zerdatime/assets/js/rich-editor.js');
        const wrap = document.getElementById('extComposeEditorWrap');
        if (extEditor) extEditorModule.destroyEditor(extEditor);
        extEditor = await extEditorModule.createEditor(wrap, { placeholder: 'Écrivez votre message...', content: prefill.body || '', mode: 'mini' });

        const panel = document.getElementById('extComposePanel');
        panel.classList.remove('minimized', 'expanded');
        panel.classList.add('open');
    }

    function closeExtCompose() {
        const panel = document.getElementById('extComposePanel');
        panel.classList.remove('open', 'expanded');
        if (extEditor && extEditorModule) { extEditorModule.destroyEditor(extEditor); extEditor = null; }
    }

    document.getElementById('extComposeClose')?.addEventListener('click', closeExtCompose);
    document.getElementById('extComposeDiscard')?.addEventListener('click', closeExtCompose);
    document.getElementById('extComposeMinimize')?.addEventListener('click', () => {
        document.getElementById('extComposePanel')?.classList.toggle('minimized');
    });
    document.getElementById('extComposeExpand')?.addEventListener('click', () => {
        const panel = document.getElementById('extComposePanel');
        panel?.classList.toggle('expanded');
        const icon = document.querySelector('#extComposeExpand i');
        if (icon) icon.className = panel.classList.contains('expanded') ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-angle-expand';
    });
    document.getElementById('extComposePanelHeader')?.addEventListener('click', () => {
        const panel = document.getElementById('extComposePanel');
        if (panel?.classList.contains('minimized')) panel.classList.remove('minimized');
    });

    document.getElementById('extComposeSendBtn')?.addEventListener('click', async () => {
        const to = document.getElementById('extComposeTo').value.trim();
        const cc = document.getElementById('extComposeCc').value.trim();
        const subject = document.getElementById('extComposeSubject').value.trim();
        const body = extEditorModule ? extEditorModule.getHTML(extEditor) : '';

        if (!to || !subject) { showToast('Destinataire et sujet requis', 'error'); return; }

        const toList = to.split(/[,;]/).map(s => s.trim()).filter(Boolean);
        const ccList = cc ? cc.split(/[,;]/).map(s => s.trim()).filter(Boolean) : [];

        const res = await adminApiPost('admin_email_ext_send', { to: toList, cc: ccList, subject, body });
        if (res.success) {
            showToast('Email envoyé', 'success');
            closeExtCompose();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ── Contacts modal (placeholder) ──
    document.getElementById('extContactsBtn')?.addEventListener('click', () => {
        showToast('Carnet de contacts — bientôt disponible', 'info');
    });

    // ── Load real IMAP folders ──
    async function loadFolders() {
        const container = document.getElementById('extFoldersDynamic');
        if (!container) return;
        const res = await adminApiPost('admin_email_ext_get_folders', {});
        if (!res.success || !res.folders) return;

        const folderMap = {
            'sent':     { icon: 'bi-send',                label: 'Envoyés' },
            'drafts':   { icon: 'bi-pencil-square',       label: 'Brouillons' },
            'trash':    { icon: 'bi-trash3',              label: 'Corbeille' },
            'junk':     { icon: 'bi-exclamation-triangle', label: 'Spam' },
            'spam':     { icon: 'bi-exclamation-triangle', label: 'Spam' },
            'archives': { icon: 'bi-archive',             label: 'Archives' },
            'archive':  { icon: 'bi-archive',             label: 'Archives' },
        };

        // Filter out INBOX (already shown) and build folder list
        const folders = res.folders.filter(f => f !== 'INBOX');
        container.innerHTML = folders.map(f => {
            const key = f.replace(/^INBOX\.?/i, '').toLowerCase();
            const info = folderMap[key] || { icon: 'bi-folder', label: f };
            return '<div class="ext-folder" data-folder="' + escapeHtml(f) + '"><i class="bi ' + info.icon + '"></i> ' + escapeHtml(info.label) + '</div>';
        }).join('');
    }

    // ── Init ──
    loadFolders();
    loadList();

    window.initEmailexternePage = () => {};
})();
</script>

<?php endif; ?>
