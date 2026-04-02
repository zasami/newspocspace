<?php
$userId = $_SESSION['ss_user']['id'] ?? '';
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

/* Contacts modal — inner components */
#contactsModal .modal-body { padding: 0; max-height: 65vh; overflow-y: auto; }
#contactsModal .modal-footer { display: flex; gap: 8px; align-items: center; }
.ct-search { padding: 10px 16px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.ct-search input { width: 100%; border: 1px solid var(--cl-border); border-radius: 8px; padding: 7px 12px 7px 32px; font-size: .85rem; background: var(--cl-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%239B9B9B' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E") 10px center no-repeat; }
.ct-search input:focus { outline: none; border-color: var(--cl-accent); }
.ct-empty { text-align: center; padding: 40px 20px; color: var(--cl-text-muted); }
.ct-empty i { font-size: 2.5rem; opacity: .2; display: block; margin-bottom: 8px; }
.ct-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); cursor: pointer; transition: background .12s; }
.ct-row:hover { background: var(--cl-bg, #F7F5F2); }
.ct-avatar { width: 36px; height: 36px; border-radius: 50%; background: #E2B8AE; color: #7B3B2C; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .75rem; flex-shrink: 0; }
.ct-info { flex: 1; min-width: 0; }
.ct-name { font-weight: 600; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ct-email { font-size: .78rem; color: var(--cl-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ct-entreprise { font-size: .72rem; color: var(--cl-text-secondary); }
.ct-actions { display: flex; gap: 2px; flex-shrink: 0; }
.ct-actions button { background: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 6px; color: var(--cl-text-muted); font-size: .9rem; transition: all .15s; display: flex; align-items: center; justify-content: center; }
.ct-actions button:hover { background: var(--cl-bg); color: var(--cl-text); }
.ct-actions button.danger:hover { background: #E2B8AE; color: #7B3B2C; }
.ct-footer-info { flex: 1; font-size: .78rem; color: var(--cl-text-muted); }

/* Context menu */
.ct-ctx { position: fixed; z-index: 1060; background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 10px; padding: 4px; box-shadow: 0 8px 24px rgba(0,0,0,.12); min-width: 180px; }
.ct-ctx-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 6px; font-size: .84rem; cursor: pointer; color: var(--cl-text); transition: background .1s; border: none; background: none; width: 100%; text-align: left; }
.ct-ctx-item:hover { background: var(--cl-bg); }
.ct-ctx-item.danger { color: #7B3B2C; }
.ct-ctx-item.danger:hover { background: #E2B8AE; }
.ct-ctx-sep { height: 1px; background: var(--cl-border-light); margin: 4px 0; }

/* Contact form */
.ct-form { padding: 16px; }
.ct-form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.ct-form-row > * { flex: 1; }
.ct-form label { display: block; font-size: .78rem; font-weight: 600; color: var(--cl-text-secondary); margin-bottom: 3px; }
.ct-form input, .ct-form textarea { width: 100%; border: 1px solid var(--cl-border); border-radius: 8px; padding: 7px 10px; font-size: .85rem; font-family: inherit; }
.ct-form input:focus, .ct-form textarea:focus { outline: none; border-color: var(--cl-accent); }
.ct-form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
.ct-shared-label { margin-top: 10px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: .85rem; }

/* Import / Extract views */
.ct-import-zone { border: 2px dashed var(--cl-border); border-radius: 12px; padding: 30px 20px; text-align: center; color: var(--cl-text-muted); cursor: pointer; transition: all .2s; }
.ct-import-zone:hover { border-color: var(--cl-accent); background: rgba(25,25,24,.02); }
.ct-import-zone i { font-size: 2rem; opacity: .3; display: block; margin-bottom: 8px; }
.ct-extract-body { padding: 20px; text-align: center; }
.ct-extract-icon { width: 56px; height: 56px; border-radius: 50%; background: #bcd2cb; color: #2d4a43; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px; }
.ct-extract-title { font-size: .92rem; margin-bottom: 4px; }
.ct-extract-desc { font-size: .82rem; color: var(--cl-text-muted); margin-bottom: 16px; }
.ct-extract-result { margin-top: 16px; }
.ct-extract-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; text-align: left; }
.ct-extract-count { font-size: .85rem; font-weight: 600; }
.ct-extract-selectall { font-size: .78rem; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.ct-extract-list { max-height: 250px; overflow-y: auto; border: 1px solid var(--cl-border); border-radius: 8px; }
.ct-extract-list .ct-row { cursor: pointer; margin: 0; }
.ct-extract-freq { color: var(--cl-text-muted); font-size: .7rem; }
.ct-extract-actions { margin-top: 12px; text-align: right; }
.ct-alert-ok { background: #bcd2cb; color: #2d4a43; padding: 10px 14px; border-radius: 8px; font-size: .85rem; }
.ct-alert-err { background: #E2B8AE; color: #7B3B2C; padding: 10px 14px; border-radius: 8px; font-size: .85rem; }
.ct-csv-hint { margin-top: 16px; font-size: .82rem; color: var(--cl-text-muted); }
.ct-csv-code { display: block; background: var(--cl-bg); padding: 8px 12px; border-radius: 6px; margin-top: 6px; font-size: .78rem; }
.ct-import-result { margin-top: 12px; }

/* Lightbox */
.ext-lb { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; display: flex; align-items: center; justify-content: center; animation: extLbIn .25s ease; }
.ext-lb-hidden { display: none !important; }
.ext-lb-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.82); backdrop-filter: blur(8px); }
.ext-lb-stage { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; z-index: 1; }
.ext-lb-stage img { max-width: 90vw; max-height: calc(100vh - 100px); object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); cursor: zoom-in; }
.ext-lb-stage.zoomed img { cursor: grab; max-width: none; max-height: none; }
.ext-lb-stage.dragging img { cursor: grabbing !important; }
.ext-lb-stage iframe { width: 85vw; height: calc(100vh - 100px); border: none; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); background: #fff; }
.ext-lb-close { position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,.12); border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; z-index: 10; transition: background .2s; backdrop-filter: blur(8px); }
.ext-lb-close:hover { background: rgba(255,255,255,.22); }
.ext-lb-title { position: absolute; top: 16px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.12); color: #fff; padding: 8px 20px; border-radius: 20px; font-size: .85rem; font-weight: 600; backdrop-filter: blur(8px); z-index: 10; white-space: nowrap; max-width: 60vw; overflow: hidden; text-overflow: ellipsis; }
.ext-lb-toolbar { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 4px; background: rgba(30,30,30,.85); backdrop-filter: blur(10px); border-radius: 999px; padding: 6px 14px; z-index: 10; }
.ext-lb-btn { width: 36px; height: 36px; border: none; background: transparent; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: background .2s; }
.ext-lb-btn:hover { background: rgba(255,255,255,.15); }
.ext-lb-zoom { color: #fff; font-size: .8rem; font-weight: 600; min-width: 44px; text-align: center; }
.ext-lb-dl { text-decoration: none; color: #fff; }
@keyframes extLbIn { from { opacity: 0; } to { opacity: 1; } }
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
    <div class="ext-folder" id="extEmptyTrashBtn" style="display:none;color:#7B3B2C"><i class="bi bi-trash3"></i> Vider la corbeille</div>
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

<!-- Contacts Modal (Bootstrap) -->
<div class="modal fade" id="contactsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" id="ctModalHeader">
        <h5 class="modal-title" id="ctModalTitle"><i class="bi bi-person-rolodex"></i> Carnet d'adresses</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div id="ctModalSearch"></div>
      <div class="modal-body" id="ctModalBody"></div>
      <div class="modal-footer" id="ctModalFooter"></div>
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
            updateEmptyTrashBtn();
            return;
        }
    });

    function updateEmptyTrashBtn() {
        const btn = document.getElementById('extEmptyTrashBtn');
        if (!btn) return;
        const activeFolder = document.querySelector('.ext-folder.active[data-is-trash="1"]');
        btn.style.display = activeFolder ? '' : 'none';
    }

    document.getElementById('extEmptyTrashBtn')?.addEventListener('click', async () => {
        const ok = await adminConfirm({
            title: 'Vider la corbeille ?',
            text: 'Tous les emails de la corbeille seront <strong>supprimés définitivement</strong>. Cette action est irréversible.',
            type: 'danger', icon: 'bi-trash3', okText: 'Vider la corbeille'
        });
        if (!ok) return;
        const res = await adminApiPost('admin_email_ext_empty_trash', {});
        if (res.success) {
            showToast(res.message, 'success');
            loadList();
        } else {
            showToast(res.message || 'Erreur', 'error');
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

        // Attachments — lightbox for images/PDF, download for others
        detail.querySelectorAll('.ext-att-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const url = '/spocspace/admin/api.php?action=admin_email_ext_download_attachment&folder=' + encodeURIComponent(currentFolder)
                    + '&uid=' + chip.dataset.uid + '&part_index=' + chip.dataset.part;
                const filename = chip.querySelector('div div')?.textContent || '';
                const ext = filename.split('.').pop().toLowerCase();
                if (['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext)) {
                    openExtLightbox(url, filename, 'image');
                } else if (ext === 'pdf') {
                    openExtLightbox(url, filename, 'pdf');
                } else {
                    window.open(url, '_blank');
                }
            });
        });
    }

    // ── Compose ──
    async function openExtCompose(prefill) {
        prefill = prefill || {};
        document.getElementById('extComposeTo').value = prefill.to || '';
        document.getElementById('extComposeCc').value = prefill.cc || '';
        document.getElementById('extComposeSubject').value = prefill.subject || '';

        if (!extEditorModule) extEditorModule = await import('/spocspace/assets/js/rich-editor.js');
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

    // ══════════════════════════════════════════════════════════════════════
    // CONTACTS MODAL — Full address book
    // ══════════════════════════════════════════════════════════════════════
    let ctContacts = [];
    let ctSearch = '';
    let ctView = 'list'; // 'list' | 'form' | 'import' | 'extract'
    let ctEditId = null;
    let ctContextMenu = null;

    const ctModal = new bootstrap.Modal(document.getElementById('contactsModal'));
    document.getElementById('extContactsBtn')?.addEventListener('click', openContacts);

    async function openContacts() {
        ctView = 'list'; ctEditId = null; ctSearch = '';
        await loadContacts();
        renderContactsModal();
        ctModal.show();
    }

    async function loadContacts() {
        const res = await adminApiPost('admin_email_ext_get_contacts', {});
        if (res.success) ctContacts = res.contacts || [];
    }

    function getInitials(c) {
        return ((c.prenom || '')[0] || '') + ((c.nom || '')[0] || c.email[0] || '?');
    }

    function filteredContacts() {
        if (!ctSearch) return ctContacts;
        const q = ctSearch.toLowerCase();
        return ctContacts.filter(c =>
            (c.nom || '').toLowerCase().includes(q) ||
            (c.prenom || '').toLowerCase().includes(q) ||
            (c.email || '').toLowerCase().includes(q) ||
            (c.entreprise || '').toLowerCase().includes(q) ||
            (c.telephone || '').toLowerCase().includes(q)
        );
    }

    function renderContactsModal() {
        closeContextMenu();
        const titleEl = document.getElementById('ctModalTitle');
        const searchEl = document.getElementById('ctModalSearch');
        const bodyEl = document.getElementById('ctModalBody');
        const footerEl = document.getElementById('ctModalFooter');

        if (ctView === 'form') { renderContactForm(); return; }
        if (ctView === 'import') { renderImportView(); return; }
        if (ctView === 'extract') { renderExtractView(); return; }

        titleEl.innerHTML = '<i class="bi bi-person-rolodex"></i> Carnet d\'adresses';
        searchEl.innerHTML = '<div class="ct-search"><input type="text" id="ctSearchInput" placeholder="Rechercher un contact..." value="' + escapeHtml(ctSearch) + '"></div>';

        const list = filteredContacts();
        bodyEl.innerHTML = list.length
            ? list.map(c => {
                const initials = getInitials(c).toUpperCase();
                const name = [c.prenom, c.nom].filter(Boolean).join(' ') || c.email;
                return '<div class="ct-row" data-id="' + c.id + '">'
                    + '<div class="ct-avatar">' + escapeHtml(initials) + '</div>'
                    + '<div class="ct-info">'
                    + '<div class="ct-name">' + escapeHtml(name) + '</div>'
                    + '<div class="ct-email">' + escapeHtml(c.email) + '</div>'
                    + (c.entreprise ? '<div class="ct-entreprise">' + escapeHtml(c.entreprise) + '</div>' : '')
                    + '</div>'
                    + '<div class="ct-actions">'
                    + '<button class="ct-send" title="Envoyer un email"><i class="bi bi-send"></i></button>'
                    + '<button class="ct-edit" title="Modifier"><i class="bi bi-pencil"></i></button>'
                    + '<button class="ct-del danger" title="Supprimer"><i class="bi bi-trash3"></i></button>'
                    + '</div>'
                    + '</div>';
            }).join('')
            : (ctContacts.length === 0
                ? '<div class="ct-empty"><i class="bi bi-person-rolodex"></i><p>Aucun contact</p><div class="d-flex gap-2 justify-content-center mt-2"><button class="btn btn-sm btn-primary" id="ctEmptyExtract"><i class="bi bi-envelope-arrow-down"></i> Importer depuis mes emails</button><button class="btn btn-sm btn-outline-secondary" id="ctEmptyImport"><i class="bi bi-upload"></i> CSV</button></div></div>'
                : '<div class="ct-empty"><i class="bi bi-search"></i><p>Aucun résultat pour «' + escapeHtml(ctSearch) + '»</p></div>');

        footerEl.innerHTML =
            '<span class="ct-footer-info">' + ctContacts.length + ' contact(s)</span>'
            + '<button class="btn btn-sm btn-outline-secondary" id="ctExtractBtn"><i class="bi bi-envelope-arrow-down"></i> Depuis emails</button>'
            + '<button class="btn btn-sm btn-outline-secondary" id="ctImportBtn"><i class="bi bi-upload"></i> CSV</button>'
            + '<button class="btn btn-sm btn-primary" id="ctAddBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>';

        // Events
        searchEl.querySelector('#ctSearchInput').addEventListener('input', (e) => { ctSearch = e.target.value; renderContactsModal(); });
        setTimeout(() => searchEl.querySelector('#ctSearchInput')?.focus(), 100);
        footerEl.querySelector('#ctAddBtn').addEventListener('click', () => { ctView = 'form'; ctEditId = null; renderContactsModal(); });
        footerEl.querySelector('#ctImportBtn')?.addEventListener('click', () => { ctView = 'import'; renderContactsModal(); });
        footerEl.querySelector('#ctExtractBtn')?.addEventListener('click', () => { ctView = 'extract'; renderContactsModal(); });
        bodyEl.querySelector('#ctEmptyImport')?.addEventListener('click', () => { ctView = 'import'; renderContactsModal(); });
        bodyEl.querySelector('#ctEmptyExtract')?.addEventListener('click', () => { ctView = 'extract'; renderContactsModal(); });

        // Row actions
        bodyEl.querySelectorAll('.ct-row').forEach(row => {
            const id = row.dataset.id;
            row.querySelector('.ct-send')?.addEventListener('click', (e) => { e.stopPropagation(); sendToContact(id); });
            row.querySelector('.ct-edit')?.addEventListener('click', (e) => { e.stopPropagation(); ctEditId = id; ctView = 'form'; renderContactsModal(); });
            row.querySelector('.ct-del')?.addEventListener('click', (e) => { e.stopPropagation(); deleteContact(id); });
            row.addEventListener('contextmenu', (e) => { e.preventDefault(); showContextMenu(e, id); });
        });
    }

    function renderContactForm() {
        const c = ctEditId ? ctContacts.find(x => x.id === ctEditId) : {};
        const isEdit = !!ctEditId;
        const titleEl = document.getElementById('ctModalTitle');
        const searchEl = document.getElementById('ctModalSearch');
        const bodyEl = document.getElementById('ctModalBody');
        const footerEl = document.getElementById('ctModalFooter');

        titleEl.innerHTML = '<button class="btn btn-sm btn-link p-0 me-2" id="ctBackBtn"><i class="bi bi-arrow-left"></i></button> ' + (isEdit ? 'Modifier le contact' : 'Nouveau contact');
        searchEl.innerHTML = '';
        bodyEl.innerHTML =
            '<div class="ct-form">'
            + '<div class="ct-form-row">'
            + '<div><label>Prénom</label><input id="ctPrenom" class="form-control form-control-sm" value="' + escapeHtml(c.prenom || '') + '"></div>'
            + '<div><label>Nom</label><input id="ctNom" class="form-control form-control-sm" value="' + escapeHtml(c.nom || '') + '"></div>'
            + '</div>'
            + '<div class="ct-form-row">'
            + '<div><label>Email *</label><input id="ctEmail" type="email" class="form-control form-control-sm" value="' + escapeHtml(c.email || '') + '"></div>'
            + '</div>'
            + '<div class="ct-form-row">'
            + '<div><label>Entreprise</label><input id="ctEntreprise" class="form-control form-control-sm" value="' + escapeHtml(c.entreprise || '') + '"></div>'
            + '<div><label>Téléphone</label><input id="ctTel" class="form-control form-control-sm" value="' + escapeHtml(c.telephone || '') + '"></div>'
            + '</div>'
            + '<div><label>Notes</label><textarea id="ctNotes" class="form-control form-control-sm" rows="2">' + escapeHtml(c.notes || '') + '</textarea></div>'
            + '<div><label class="ct-shared-label"><input type="checkbox" class="form-check-input" id="ctShared" ' + (c.is_shared ? 'checked' : '') + '> Partagé avec toute l\'équipe</label></div>'
            + '</div>';
        footerEl.innerHTML =
            '<button class="btn btn-sm btn-outline-secondary" id="ctCancelForm">Annuler</button>'
            + '<button class="btn btn-sm btn-primary" id="ctSaveForm"><i class="bi bi-check-lg"></i> ' + (isEdit ? 'Modifier' : 'Ajouter') + '</button>';

        titleEl.querySelector('#ctBackBtn').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });
        footerEl.querySelector('#ctCancelForm').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });
        footerEl.querySelector('#ctSaveForm').addEventListener('click', saveContact);
        setTimeout(() => document.getElementById('ctPrenom')?.focus(), 100);
    }

    function renderImportView() {
        const titleEl = document.getElementById('ctModalTitle');
        const searchEl = document.getElementById('ctModalSearch');
        const bodyEl = document.getElementById('ctModalBody');
        const footerEl = document.getElementById('ctModalFooter');

        titleEl.innerHTML = '<button class="btn btn-sm btn-link p-0 me-2" id="ctBackBtn"><i class="bi bi-arrow-left"></i></button> Importer des contacts (CSV)';
        searchEl.innerHTML = '';
        footerEl.innerHTML = '<button class="btn btn-sm btn-outline-secondary" id="ctBackBtn2">Retour</button>';
        bodyEl.innerHTML =
            '<div class="ct-extract-body">'
            + '<div class="ct-import-zone" id="ctImportZone">'
            + '<i class="bi bi-file-earmark-arrow-up"></i>'
            + '<p class="ct-extract-title"><strong>Glissez un fichier CSV ici</strong></p>'
            + '<p class="ct-extract-desc">ou cliquez pour sélectionner</p>'
            + '<input type="file" id="ctImportFile" accept=".csv,.txt,.vcf" hidden>'
            + '</div>'
            + '<div class="ct-csv-hint">'
            + '<p><strong>Format CSV attendu :</strong></p>'
            + '<code class="ct-csv-code">'
            + 'prenom,nom,email,entreprise,telephone<br>'
            + 'Jean,Dupont,jean@example.com,Entreprise SA,+41 22 123 45 67'
            + '</code>'
            + '</div>'
            + '<div class="ct-import-result" id="ctImportResult"></div>'
            + '</div>';

        titleEl.querySelector('#ctBackBtn').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });
        footerEl.querySelector('#ctBackBtn2').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });

        const zone = bodyEl.querySelector('#ctImportZone');
        const fileInput = bodyEl.querySelector('#ctImportFile');
        zone.addEventListener('click', () => fileInput.click());
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.style.borderColor = 'var(--cl-accent)'; });
        zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
        zone.addEventListener('drop', (e) => { e.preventDefault(); zone.style.borderColor = ''; if (e.dataTransfer.files[0]) processImportFile(e.dataTransfer.files[0]); });
        fileInput.addEventListener('change', (e) => { if (e.target.files[0]) processImportFile(e.target.files[0]); });
    }

    async function renderExtractView() {
        const titleEl = document.getElementById('ctModalTitle');
        const searchEl = document.getElementById('ctModalSearch');
        const bodyEl = document.getElementById('ctModalBody');
        const footerEl = document.getElementById('ctModalFooter');

        titleEl.innerHTML = '<button class="btn btn-sm btn-link p-0 me-2" id="ctBackBtn"><i class="bi bi-arrow-left"></i></button> Importer depuis mes emails';
        searchEl.innerHTML = '';
        footerEl.innerHTML = '<button class="btn btn-sm btn-outline-secondary" id="ctBackBtn2">Retour</button>';
        bodyEl.innerHTML =
            '<div class="ct-extract-body">'
            + '<div class="ct-extract-icon"><i class="bi bi-envelope-arrow-down"></i></div>'
            + '<p class="ct-extract-title"><strong>Scanner ma boîte email</strong></p>'
            + '<p class="ct-extract-desc">Analyse les 500 derniers emails (Boîte de réception + Envoyés) pour extraire les adresses.<br>Les dossiers Spam, Corbeille et Brouillons sont ignorés.</p>'
            + '<button class="btn btn-primary" id="ctStartExtract"><i class="bi bi-search"></i> Lancer le scan</button>'
            + '<div class="ct-extract-result" id="ctExtractResult"></div>'
            + '</div>';

        titleEl.querySelector('#ctBackBtn').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });
        footerEl.querySelector('#ctBackBtn2').addEventListener('click', () => { ctView = 'list'; renderContactsModal(); });
        bodyEl.querySelector('#ctStartExtract').addEventListener('click', doExtract);
    }

    async function doExtract() {
        const btn = document.getElementById('ctStartExtract');
        const resultEl = document.getElementById('ctExtractResult');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scan en cours...';
        resultEl.innerHTML = '<p class="ct-extract-desc">Connexion IMAP et analyse des headers... Cela peut prendre quelques secondes.</p>';

        const res = await adminApiPost('admin_email_ext_extract_contacts', {});
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search"></i> Lancer le scan';

        if (!res.success) {
            resultEl.innerHTML = '<div class="ct-alert-err">' + escapeHtml(res.message || 'Erreur') + '</div>';
            return;
        }

        const found = res.contacts || [];
        if (!found.length) {
            resultEl.innerHTML = '<div class="ct-alert-ok">Aucune nouvelle adresse trouvée.</div>';
            return;
        }

        // Show found contacts with checkboxes
        const existingEmails = new Set(ctContacts.map(c => c.email.toLowerCase()));
        const newContacts = found.filter(c => !existingEmails.has(c.email.toLowerCase()));

        if (!newContacts.length) {
            resultEl.innerHTML = '<div class="ct-alert-ok"><i class="bi bi-check-circle"></i> ' + found.length + ' adresse(s) trouvée(s), mais toutes existent déjà dans votre carnet.</div>';
            return;
        }

        resultEl.innerHTML =
            '<div>'
            + '<div class="ct-extract-header">'
            + '<span class="ct-extract-count">' + newContacts.length + ' nouveau(x) contact(s) trouvé(s)</span>'
            + '<label class="ct-extract-selectall"><input type="checkbox" id="ctExtSelectAll" checked> Tout sélectionner</label>'
            + '</div>'
            + '<div class="ct-extract-list">'
            + newContacts.map((c, i) => {
                const name = c.name || c.email.split('@')[0];
                return '<label class="ct-row">'
                    + '<input type="checkbox" class="ct-ext-check" data-idx="' + i + '" checked>'
                    + '<div class="ct-info">'
                    + '<div class="ct-name">' + escapeHtml(name) + '</div>'
                    + '<div class="ct-email">' + escapeHtml(c.email) + ' <span class="ct-extract-freq">(' + c.count + ' email' + (c.count > 1 ? 's' : '') + ')</span></div>'
                    + '</div>'
                    + '</label>';
            }).join('')
            + '</div>'
            + '<div class="ct-extract-actions"><button class="ct-btn ct-btn-primary" id="ctDoImportExtracted"><i class="bi bi-download"></i> Importer la sélection</button></div>'
            + '</div>';

        // Store for import
        resultEl._newContacts = newContacts;

        document.getElementById('ctExtSelectAll')?.addEventListener('change', (e) => {
            resultEl.querySelectorAll('.ct-ext-check').forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('ctDoImportExtracted')?.addEventListener('click', async () => {
            const checks = resultEl.querySelectorAll('.ct-ext-check:checked');
            const toImport = [];
            checks.forEach(cb => {
                const c = newContacts[parseInt(cb.dataset.idx)];
                if (c) {
                    const parts = (c.name || '').split(/\s+/);
                    toImport.push({
                        email: c.email,
                        prenom: parts[0] || '',
                        nom: parts.slice(1).join(' ') || '',
                    });
                }
            });
            if (!toImport.length) { showToast('Aucun contact sélectionné', 'error'); return; }

            const importRes = await adminApiPost('admin_email_ext_import_contacts', { contacts: toImport });
            if (importRes.success) {
                showToast(importRes.message, 'success');
                await loadContacts();
                setTimeout(() => { ctView = 'list'; renderContactsModal(); }, 800);
            } else {
                showToast(importRes.message || 'Erreur', 'error');
            }
        });
    }

    async function processImportFile(file) {
        const resultEl = document.getElementById('ctImportResult');
        resultEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Lecture...';

        const text = await file.text();
        const lines = text.split(/\r?\n/).filter(l => l.trim());
        if (lines.length < 2) { resultEl.innerHTML = '<div class="alert alert-danger" style="background:#E2B8AE;color:#7B3B2C;padding:8px 12px;border-radius:8px;font-size:.85rem">Fichier vide ou invalide</div>'; return; }

        // Parse header
        const sep = lines[0].includes(';') ? ';' : ',';
        const headers = lines[0].split(sep).map(h => h.trim().toLowerCase().replace(/['"]/g, ''));
        const emailIdx = headers.findIndex(h => h.includes('email') || h.includes('mail'));
        if (emailIdx === -1) { resultEl.innerHTML = '<div class="alert alert-danger" style="background:#E2B8AE;color:#7B3B2C;padding:8px 12px;border-radius:8px;font-size:.85rem">Colonne "email" non trouvée</div>'; return; }

        const nomIdx = headers.findIndex(h => h === 'nom' || h === 'last_name' || h === 'lastname' || h === 'family_name');
        const prenomIdx = headers.findIndex(h => h === 'prenom' || h === 'prénom' || h === 'first_name' || h === 'firstname' || h === 'given_name');
        const entIdx = headers.findIndex(h => h.includes('entreprise') || h.includes('company') || h.includes('organization') || h.includes('organisation'));
        const telIdx = headers.findIndex(h => h.includes('tel') || h.includes('phone') || h.includes('mobile'));

        const contacts = [];
        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(sep).map(c => c.trim().replace(/^['"]|['"]$/g, ''));
            const email = cols[emailIdx] || '';
            if (!email) continue;
            contacts.push({
                email,
                nom: nomIdx >= 0 ? (cols[nomIdx] || '') : '',
                prenom: prenomIdx >= 0 ? (cols[prenomIdx] || '') : '',
                entreprise: entIdx >= 0 ? (cols[entIdx] || '') : '',
                telephone: telIdx >= 0 ? (cols[telIdx] || '') : '',
            });
        }

        if (!contacts.length) { resultEl.innerHTML = '<div style="background:#E2B8AE;color:#7B3B2C;padding:8px 12px;border-radius:8px;font-size:.85rem">Aucun contact valide trouvé</div>'; return; }

        resultEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import de ' + contacts.length + ' contact(s)...';
        const res = await adminApiPost('admin_email_ext_import_contacts', { contacts });
        if (res.success) {
            resultEl.innerHTML = '<div style="background:#bcd2cb;color:#2d4a43;padding:8px 12px;border-radius:8px;font-size:.85rem"><i class="bi bi-check-circle"></i> ' + escapeHtml(res.message) + '</div>';
            await loadContacts();
            setTimeout(() => { ctView = 'list'; renderContactsModal(); }, 1200);
        } else {
            resultEl.innerHTML = '<div style="background:#E2B8AE;color:#7B3B2C;padding:8px 12px;border-radius:8px;font-size:.85rem">' + escapeHtml(res.message || 'Erreur') + '</div>';
        }
    }

    async function saveContact() {
        const data = {
            id: ctEditId || '',
            prenom: document.getElementById('ctPrenom').value.trim(),
            nom: document.getElementById('ctNom').value.trim(),
            email: document.getElementById('ctEmail').value.trim(),
            entreprise: document.getElementById('ctEntreprise').value.trim(),
            telephone: document.getElementById('ctTel').value.trim(),
            notes: document.getElementById('ctNotes').value.trim(),
            is_shared: document.getElementById('ctShared').checked ? 1 : 0,
        };
        if (!data.email) { showToast('Email requis', 'error'); return; }

        const res = await adminApiPost('admin_email_ext_save_contact', data);
        if (res.success) {
            showToast(ctEditId ? 'Contact modifié' : 'Contact ajouté', 'success');
            await loadContacts();
            ctView = 'list'; ctEditId = null;
            renderContactsModal();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    async function deleteContact(id) {
        closeContextMenu();
        const c = ctContacts.find(x => x.id === id);
        const confirmed = await adminConfirm({
            title: 'Supprimer ce contact ?',
            text: escapeHtml([c?.prenom, c?.nom].filter(Boolean).join(' ') || c?.email),
            type: 'danger', icon: 'bi-trash3', okText: 'Supprimer'
        });
        if (!confirmed) return;
        const res = await adminApiPost('admin_email_ext_delete_contact', { id });
        if (res.success) {
            showToast('Contact supprimé', 'success');
            await loadContacts();
            renderContactsModal();
        }
    }

    function sendToContact(id) {
        closeContextMenu();
        ctModal.hide();
        const c = ctContacts.find(x => x.id === id);
        if (!c) return;
        openExtCompose({ to: c.email, subject: '' });
    }

    function closeContacts() {
        closeContextMenu();
        ctModal.hide();
    }

    function showContextMenu(e, id) {
        closeContextMenu();
        const c = ctContacts.find(x => x.id === id);
        if (!c) return;

        const menu = document.createElement('div');
        menu.className = 'ct-ctx';
        menu.id = 'ctCtxMenu';
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.innerHTML =
            '<button class="ct-ctx-item" data-action="send"><i class="bi bi-send"></i> Envoyer un email</button>'
            + '<button class="ct-ctx-item" data-action="copy"><i class="bi bi-clipboard"></i> Copier l\'email</button>'
            + '<button class="ct-ctx-item" data-action="edit"><i class="bi bi-pencil"></i> Modifier</button>'
            + '<div class="ct-ctx-sep"></div>'
            + '<button class="ct-ctx-item danger" data-action="delete"><i class="bi bi-trash3"></i> Supprimer</button>';
        document.body.appendChild(menu);

        // Keep in viewport
        const r = menu.getBoundingClientRect();
        if (r.right > window.innerWidth) menu.style.left = (window.innerWidth - r.width - 8) + 'px';
        if (r.bottom > window.innerHeight) menu.style.top = (window.innerHeight - r.height - 8) + 'px';

        menu.querySelectorAll('.ct-ctx-item').forEach(item => {
            item.addEventListener('click', () => {
                const action = item.dataset.action;
                if (action === 'send') sendToContact(id);
                else if (action === 'copy') { navigator.clipboard.writeText(c.email); showToast('Email copié', 'success'); closeContextMenu(); }
                else if (action === 'edit') { closeContextMenu(); ctEditId = id; ctView = 'form'; renderContactsModal(); }
                else if (action === 'delete') deleteContact(id);
            });
        });

        ctContextMenu = menu;
        setTimeout(() => document.addEventListener('click', closeContextMenu, { once: true }), 10);
    }

    function closeContextMenu() {
        if (ctContextMenu) { ctContextMenu.remove(); ctContextMenu = null; }
    }

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
            const isTrash = key === 'trash' || key.includes('trash') || key.includes('corbeille');
            return '<div class="ext-folder" data-folder="' + escapeHtml(f) + '"' + (isTrash ? ' data-is-trash="1"' : '') + '><i class="bi ' + info.icon + '"></i> ' + escapeHtml(info.label) + '</div>';
        }).join('');

        // Show/hide empty trash button
        updateEmptyTrashBtn();
    }

    // ── Init ──
    loadFolders();
    loadList();

    // Auto-open contacts modal if ?contacts=1
    if (new URLSearchParams(window.location.search).get('contacts') === '1') {
        setTimeout(() => openContacts(), 300);
    }

    // ── Lightbox for attachments ──
    function openExtLightbox(url, filename, type) {
        let lb = document.getElementById('extLightbox');
        if (!lb) {
            lb = document.createElement('div');
            lb.id = 'extLightbox';
            lb.className = 'ext-lb';
            lb.innerHTML =
                '<div class="ext-lb-overlay"></div>'
                + '<button class="ext-lb-close" type="button"><i class="bi bi-x-lg"></i></button>'
                + '<div class="ext-lb-title" id="extLbTitle"></div>'
                + '<div class="ext-lb-stage" id="extLbStage"></div>'
                + '<div class="ext-lb-toolbar" id="extLbToolbar">'
                + '  <button type="button" class="ext-lb-btn" id="extLbZoomOut"><i class="bi bi-zoom-out"></i></button>'
                + '  <span class="ext-lb-zoom" id="extLbZoomLvl">100%</span>'
                + '  <button type="button" class="ext-lb-btn" id="extLbZoomIn"><i class="bi bi-zoom-in"></i></button>'
                + '  <button type="button" class="ext-lb-btn" id="extLbReset"><i class="bi bi-arrows-angle-contract"></i></button>'
                + '  <span style="width:1px;height:20px;background:rgba(255,255,255,.2);margin:0 6px"></span>'
                + '  <a class="ext-lb-btn ext-lb-dl" id="extLbDownload" title="Télécharger"><i class="bi bi-download"></i></a>'
                + '</div>';
            document.body.appendChild(lb);
        }

        const stage = document.getElementById('extLbStage');
        const toolbar = document.getElementById('extLbToolbar');
        const title = document.getElementById('extLbTitle');
        const zoomLvl = document.getElementById('extLbZoomLvl');
        const dlBtn = document.getElementById('extLbDownload');

        title.textContent = filename;
        dlBtn.href = url;
        dlBtn.setAttribute('download', filename);

        let scale = 1, tx = 0, ty = 0, imgEl = null;

        if (type === 'image') {
            stage.innerHTML = '<img src="' + url + '" alt="' + escapeHtml(filename) + '" draggable="false">';
            imgEl = stage.querySelector('img');
            toolbar.style.display = '';
        } else if (type === 'pdf') {
            stage.innerHTML = '<iframe src="' + url + '#toolbar=1"></iframe>';
            toolbar.style.display = 'none';
        }

        function apply() {
            if (!imgEl) return;
            imgEl.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
            zoomLvl.textContent = Math.round(scale * 100) + '%';
            stage.classList.toggle('zoomed', scale > 1);
        }

        function zoomAt(cx, cy, ns) {
            ns = Math.max(0.25, Math.min(5, ns));
            if (imgEl) {
                const r = imgEl.getBoundingClientRect();
                const ox = cx - r.left - r.width / 2;
                const oy = cy - r.top - r.height / 2;
                tx += ox * (1 - ns / scale);
                ty += oy * (1 - ns / scale);
            }
            scale = ns;
            if (scale <= 1) { tx = 0; ty = 0; scale = 1; }
            apply();
        }

        function closeLb() {
            lb.classList.add('ext-lb-hidden');
            document.body.style.overflow = '';
            stage.classList.remove('zoomed', 'dragging');
        }

        lb.classList.remove('ext-lb-hidden');
        document.body.style.overflow = 'hidden';
        scale = 1; tx = 0; ty = 0;
        apply();

        // Events — use AbortController for clean cleanup
        const ac = new AbortController();
        const sig = { signal: ac.signal };

        lb.querySelector('.ext-lb-close').addEventListener('click', () => { closeLb(); ac.abort(); }, sig);
        lb.querySelector('.ext-lb-overlay').addEventListener('click', () => { closeLb(); ac.abort(); }, sig);
        document.getElementById('extLbZoomIn')?.addEventListener('click', () => zoomAt(innerWidth / 2, innerHeight / 2, scale + .25), sig);
        document.getElementById('extLbZoomOut')?.addEventListener('click', () => zoomAt(innerWidth / 2, innerHeight / 2, scale - .25), sig);
        document.getElementById('extLbReset')?.addEventListener('click', () => { scale = 1; tx = 0; ty = 0; apply(); }, sig);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closeLb(); ac.abort(); }
            else if (e.key === '+' || e.key === '=') zoomAt(innerWidth / 2, innerHeight / 2, scale + .25);
            else if (e.key === '-') zoomAt(innerWidth / 2, innerHeight / 2, scale - .25);
            else if (e.key === '0') { scale = 1; tx = 0; ty = 0; apply(); }
        }, sig);

        // Mouse wheel zoom
        stage.addEventListener('wheel', (e) => {
            if (!imgEl) return;
            e.preventDefault();
            zoomAt(e.clientX, e.clientY, scale + (e.deltaY < 0 ? .15 : -.15));
        }, { ...sig, passive: false });

        // Drag when zoomed
        let dragging = false, sx = 0, sy = 0;
        stage.addEventListener('mousedown', (e) => {
            if (scale <= 1 || !imgEl) return;
            dragging = true; sx = e.clientX - tx; sy = e.clientY - ty;
            stage.classList.add('dragging');
        }, sig);
        document.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            tx = e.clientX - sx; ty = e.clientY - sy;
            apply();
        }, sig);
        document.addEventListener('mouseup', () => {
            dragging = false;
            stage.classList.remove('dragging');
        }, sig);
    }

    window.initEmailexternePage = () => {};
})();
</script>

<?php endif; ?>
