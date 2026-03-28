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

.ext-detail { flex: 1; overflow-y: auto; padding: 20px; }
.ext-detail-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--cl-text-muted); flex-direction: column; gap: 8px; }
.ext-detail-empty i { font-size: 3rem; opacity: .2; }
.ext-detail-header { margin-bottom: 16px; }
.ext-detail-subject { font-size: 1.2rem; font-weight: 700; margin-bottom: 8px; }
.ext-detail-meta { font-size: .85rem; color: var(--cl-text-muted); }
.ext-detail-body { font-size: .95rem; line-height: 1.6; }
.ext-detail-body img { max-width: 100%; }
.ext-detail-actions { display: flex; gap: 8px; margin-top: 16px; }
.ext-att-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.ext-att-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--cl-bg); border: 1px solid var(--cl-border); border-radius: 6px; font-size: .82rem; cursor: pointer; transition: background .15s; }
.ext-att-chip:hover { background: var(--cl-surface); border-color: var(--cl-accent); }

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
    <div class="ext-folder" data-folder="INBOX.Sent"><i class="bi bi-send"></i> Envoyés</div>
    <div class="ext-folder" data-folder="INBOX.Drafts"><i class="bi bi-pencil-square"></i> Brouillons</div>
    <div class="ext-folder" data-folder="INBOX.Trash"><i class="bi bi-trash3"></i> Corbeille</div>
    <div class="ext-folder" data-folder="INBOX.Junk"><i class="bi bi-exclamation-triangle"></i> Spam</div>
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
    async function loadEmail(uid) {
        currentUid = uid;
        const detail = document.getElementById('extDetail');
        detail.innerHTML = '<div class="ext-detail-empty"><span class="spinner-border"></span></div>';

        const res = await adminApiPost('admin_email_ext_fetch_email', { folder: currentFolder, uid });
        if (!res.success) { detail.innerHTML = '<div class="text-danger p-3">' + escapeHtml(res.message) + '</div>'; return; }

        const e = res.email;
        let attHtml = '';
        if (e.attachments?.length) {
            attHtml = '<div class="ext-att-list">' + e.attachments.map(a =>
                '<div class="ext-att-chip" data-uid="' + uid + '" data-part="' + a.index + '" data-name="' + escapeHtml(a.filename) + '">'
                + '<i class="bi bi-paperclip"></i> ' + escapeHtml(a.filename)
                + '</div>'
            ).join('') + '</div>';
        }

        const toStr = (e.to || []).map(t => t.name ? (t.name + ' <' + t.email + '>') : t.email).join(', ');
        const ccStr = (e.cc || []).map(t => t.name ? (t.name + ' <' + t.email + '>') : t.email).join(', ');

        detail.innerHTML = '<div class="ext-detail-header">'
            + '<div class="ext-detail-subject">' + escapeHtml(e.subject || '(sans sujet)') + '</div>'
            + '<div class="ext-detail-meta"><strong>' + escapeHtml(e.from_name || e.from) + '</strong> &lt;' + escapeHtml(e.from) + '&gt;<br>'
            + '<small>À : ' + escapeHtml(toStr) + '</small>'
            + (ccStr ? '<br><small>Cc : ' + escapeHtml(ccStr) + '</small>' : '')
            + '<br><small>' + escapeHtml(e.date) + '</small></div>'
            + '</div>'
            + attHtml
            + '<hr>'
            + '<div class="ext-detail-body">' + e.body + '</div>'
            + '<div class="ext-detail-actions">'
            + '<button class="btn btn-sm btn-outline-primary" id="extReplyBtn"><i class="bi bi-reply"></i> Répondre</button>'
            + '<button class="btn btn-sm btn-outline-danger" id="extDeleteBtn"><i class="bi bi-trash3"></i> Supprimer</button>'
            + '</div>';

        // Attachment click
        detail.querySelectorAll('.ext-att-chip').forEach(chip => {
            chip.addEventListener('click', async () => {
                const url = '/zerdatime/admin/api.php?action=admin_email_ext_download_attachment&folder=' + encodeURIComponent(currentFolder)
                    + '&uid=' + chip.dataset.uid + '&part_index=' + chip.dataset.part;
                window.open(url, '_blank');
            });
        });

        // Reply
        detail.querySelector('#extReplyBtn')?.addEventListener('click', () => {
            openExtCompose({
                to: e.from,
                subject: e.subject.startsWith('RE:') ? e.subject : 'RE: ' + e.subject,
                body: '<br><br><blockquote style="border-left:3px solid #ccc;padding-left:10px;color:#666"><small>' + escapeHtml(e.date) + ', ' + escapeHtml(e.from) + ' :</small><br>' + e.body + '</blockquote>',
                replyTo: e.from
            });
        });

        // Delete
        detail.querySelector('#extDeleteBtn')?.addEventListener('click', async () => {
            if (!confirm('Supprimer cet email ?')) return;
            await adminApiPost('admin_email_ext_delete', { folder: currentFolder, uid });
            showToast('Email supprimé', 'success');
            detail.innerHTML = '<div class="ext-detail-empty"><i class="bi bi-envelope-open"></i><p>Sélectionnez un email</p></div>';
            loadList();
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

    // ── Init ──
    loadList();

    window.initEmailexternePage = () => {};
})();
</script>

<?php endif; ?>
