<?php
$userId = $_SESSION['zt_user']['id'] ?? '';
$totalContacts = (int) Db::getOne("SELECT COUNT(*) FROM email_externe_contacts WHERE created_by = ? OR is_shared = 1", [$userId]);
$sharedContacts = (int) Db::getOne("SELECT COUNT(*) FROM email_externe_contacts WHERE is_shared = 1");
?>
<style>
/* Page layout */
.ct-page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.ct-page-title { font-weight: 700; margin: 0; flex: 1; }
.ct-page-stats { font-size: .82rem; color: var(--cl-text-muted); }

/* Search uses global topbar input */

/* Table */
.ct-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ct-table th { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; }
.ct-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); font-size: .88rem; vertical-align: middle; }
.ct-table tr:hover td { background: var(--cl-bg); }
.ct-table tr { cursor: pointer; }

/* Avatar */
.ct-avatar { width: 34px; height: 34px; border-radius: 50%; background: #E2B8AE; color: #7B3B2C; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .72rem; flex-shrink: 0; }
.ct-avatar-shared { background: #bcd2cb; color: #2d4a43; }
.ct-name-cell { display: flex; align-items: center; gap: 10px; }
.ct-name-text { font-weight: 600; }
.ct-shared-badge { display: inline-block; font-size: .65rem; background: #bcd2cb; color: #2d4a43; padding: 1px 6px; border-radius: 4px; margin-left: 6px; font-weight: 600; }

/* Actions */
.ct-row-actions { display: flex; gap: 2px; }
.ct-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .92rem; transition: all .15s; display: flex; align-items: center; justify-content: center; }
.ct-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.ct-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

/* Empty state */
.ct-empty { text-align: center; padding: 60px 20px; }
.ct-empty i { font-size: 3rem; opacity: .15; display: block; margin-bottom: 12px; }

/* Context menu */
.ct-ctx { position: fixed; z-index: 1060; background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 10px; padding: 4px; box-shadow: 0 8px 24px rgba(0,0,0,.12); min-width: 180px; }
.ct-ctx-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 6px; font-size: .84rem; cursor: pointer; color: var(--cl-text); transition: background .1s; border: none; background: none; width: 100%; text-align: left; }
.ct-ctx-item:hover { background: var(--cl-bg); }
.ct-ctx-item.danger { color: #7B3B2C; }
.ct-ctx-item.danger:hover { background: #E2B8AE; }
.ct-ctx-sep { height: 1px; background: var(--cl-border-light); margin: 4px 0; }

/* Form in modal */
.ct-form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.ct-form-row > * { flex: 1; }
.ct-form-label { display: block; font-size: .78rem; font-weight: 600; color: var(--cl-text-secondary); margin-bottom: 3px; }
.ct-shared-label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: .85rem; margin-top: 6px; }

/* Import zone */
.ct-import-zone { border: 2px dashed var(--cl-border); border-radius: 12px; padding: 30px 20px; text-align: center; color: var(--cl-text-muted); cursor: pointer; transition: all .2s; }
.ct-import-zone:hover { border-color: var(--cl-accent); background: rgba(25,25,24,.02); }
.ct-import-zone i { font-size: 2rem; opacity: .3; display: block; margin-bottom: 8px; }
.ct-csv-hint { margin-top: 16px; font-size: .82rem; color: var(--cl-text-muted); }
.ct-csv-code { display: block; background: var(--cl-bg); padding: 8px 12px; border-radius: 6px; margin-top: 6px; font-size: .78rem; }

/* Extract */
.ct-extract-icon { width: 56px; height: 56px; border-radius: 50%; background: #bcd2cb; color: #2d4a43; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px; }
.ct-extract-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.ct-extract-list { max-height: 250px; overflow-y: auto; border: 1px solid var(--cl-border); border-radius: 8px; }
.ct-extract-list label { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); cursor: pointer; margin: 0; font-size: .85rem; }
.ct-extract-list label:hover { background: var(--cl-bg); }
.ct-extract-freq { color: var(--cl-text-muted); font-size: .7rem; }
.ct-alert-ok { background: #bcd2cb; color: #2d4a43; padding: 10px 14px; border-radius: 8px; font-size: .85rem; margin-top: 12px; }
.ct-alert-err { background: #E2B8AE; color: #7B3B2C; padding: 10px 14px; border-radius: 8px; font-size: .85rem; margin-top: 12px; }
</style>

<!-- Header -->
<div class="ct-page-header">
  <h5 class="ct-page-title"><i class="bi bi-person-rolodex"></i> Contacts</h5>
  <span class="ct-page-stats" id="ctStats"><?= $totalContacts ?> contact(s) · <?= $sharedContacts ?> partagé(s)</span>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" id="ctExtractBtn"><i class="bi bi-envelope-arrow-down"></i> Depuis emails</button>
    <button class="btn btn-sm btn-outline-secondary" id="ctImportBtn"><i class="bi bi-upload"></i> CSV</button>
    <button class="btn btn-sm btn-primary" id="ctAddBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <table class="ct-table">
      <thead>
        <tr>
          <th>Contact</th>
          <th>Email</th>
          <th>Entreprise</th>
          <th>Téléphone</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="ctTableBody">
        <tr><td colspan="5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Empty state (hidden by default) -->
<div class="ct-empty" id="ctEmptyState" style="display:none">
  <i class="bi bi-person-rolodex"></i>
  <p class="mb-3">Aucun contact</p>
  <div class="d-flex gap-2 justify-content-center">
    <button class="btn btn-primary" id="ctEmptyExtract"><i class="bi bi-envelope-arrow-down"></i> Importer depuis mes emails</button>
    <button class="btn btn-outline-secondary" id="ctEmptyImport"><i class="bi bi-upload"></i> Importer CSV</button>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="ctFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ctFormTitle">Nouveau contact</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="ct-form-row">
          <div><label class="ct-form-label">Prénom</label><input class="form-control form-control-sm" id="ctfPrenom"></div>
          <div><label class="ct-form-label">Nom</label><input class="form-control form-control-sm" id="ctfNom"></div>
        </div>
        <div class="ct-form-row">
          <div><label class="ct-form-label">Email *</label><input type="email" class="form-control form-control-sm" id="ctfEmail"></div>
        </div>
        <div class="ct-form-row">
          <div><label class="ct-form-label">Entreprise</label><input class="form-control form-control-sm" id="ctfEntreprise"></div>
          <div><label class="ct-form-label">Téléphone</label><input class="form-control form-control-sm" id="ctfTel"></div>
        </div>
        <div><label class="ct-form-label">Notes</label><textarea class="form-control form-control-sm" id="ctfNotes" rows="2"></textarea></div>
        <label class="ct-shared-label"><input type="checkbox" class="form-check-input" id="ctfShared"> Partagé avec toute l'équipe</label>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary" id="ctfSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Import CSV Modal -->
<div class="modal fade" id="ctImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Importer des contacts (CSV)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="ct-import-zone" id="ctImportZone">
          <i class="bi bi-file-earmark-arrow-up"></i>
          <p><strong>Glissez un fichier CSV ici</strong></p>
          <p class="small text-muted">ou cliquez pour sélectionner</p>
          <input type="file" id="ctImportFile" accept=".csv,.txt" hidden>
        </div>
        <div class="ct-csv-hint">
          <p><strong>Format CSV attendu :</strong></p>
          <code class="ct-csv-code">prenom,nom,email,entreprise,telephone<br>Jean,Dupont,jean@example.com,Entreprise SA,+41 22 123 45 67</code>
        </div>
        <div id="ctImportResult"></div>
      </div>
    </div>
  </div>
</div>

<!-- Extract from Emails Modal -->
<div class="modal fade" id="ctExtractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Importer depuis mes emails</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" id="ctExtractBody">
        <div class="ct-extract-icon"><i class="bi bi-envelope-arrow-down"></i></div>
        <p><strong>Scanner ma boîte email</strong></p>
        <p class="small text-muted mb-3">Analyse les 500 derniers emails (Boîte de réception + Envoyés).<br>Les dossiers Spam, Corbeille et Brouillons sont ignorés.</p>
        <button class="btn btn-primary" id="ctStartExtract"><i class="bi bi-search"></i> Lancer le scan</button>
        <div id="ctExtractResult"></div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let contacts = [];
    let editId = null;
    let ctxMenu = null;
    const formModal = new bootstrap.Modal(document.getElementById('ctFormModal'));
    const importModal = new bootstrap.Modal(document.getElementById('ctImportModal'));
    const extractModal = new bootstrap.Modal(document.getElementById('ctExtractModal'));

    // ── Load & Render ──
    async function load() {
        const res = await adminApiPost('admin_email_ext_get_contacts', {});
        if (res.success) contacts = res.contacts || [];
        render();
    }

    function render() {
        const q = (document.getElementById('topbarSearchInput')?.value || '').toLowerCase();
        const filtered = q
            ? contacts.filter(c => [c.prenom,c.nom,c.email,c.entreprise,c.telephone].join(' ').toLowerCase().includes(q))
            : contacts;

        const tbody = document.getElementById('ctTableBody');
        const empty = document.getElementById('ctEmptyState');
        const card = tbody.closest('.card');

        document.getElementById('ctStats').textContent = contacts.length + ' contact(s) · ' + contacts.filter(c => c.is_shared).length + ' partagé(s)';

        if (!contacts.length) {
            card.style.display = 'none';
            empty.style.display = '';
            return;
        }
        card.style.display = '';
        empty.style.display = 'none';

        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Aucun résultat pour «' + escapeHtml(q) + '»</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(c => {
            const name = [c.prenom, c.nom].filter(Boolean).join(' ') || c.email;
            const initials = ((c.prenom||'')[0] || '') + ((c.nom||'')[0] || c.email[0] || '?');
            return '<tr data-id="' + c.id + '">'
                + '<td><div class="ct-name-cell"><div class="ct-avatar' + (c.is_shared ? ' ct-avatar-shared' : '') + '">' + escapeHtml(initials.toUpperCase()) + '</div><span class="ct-name-text">' + escapeHtml(name) + '</span>' + (c.is_shared ? '<span class="ct-shared-badge">Partagé</span>' : '') + '</div></td>'
                + '<td>' + escapeHtml(c.email) + '</td>'
                + '<td>' + escapeHtml(c.entreprise || '—') + '</td>'
                + '<td>' + escapeHtml(c.telephone || '—') + '</td>'
                + '<td><div class="ct-row-actions">'
                + '<button class="ct-row-btn ct-act-send" title="Envoyer un email"><i class="bi bi-send"></i></button>'
                + '<button class="ct-row-btn ct-act-edit" title="Modifier"><i class="bi bi-pencil"></i></button>'
                + '<button class="ct-row-btn ct-act-copy" title="Copier l\'email"><i class="bi bi-clipboard"></i></button>'
                + '<button class="ct-row-btn danger ct-act-del" title="Supprimer"><i class="bi bi-trash3"></i></button>'
                + '</div></td>'
                + '</tr>';
        }).join('');

        // Events
        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const id = tr.dataset.id;
            tr.querySelector('.ct-act-send')?.addEventListener('click', e => { e.stopPropagation(); sendTo(id); });
            tr.querySelector('.ct-act-edit')?.addEventListener('click', e => { e.stopPropagation(); openForm(id); });
            tr.querySelector('.ct-act-copy')?.addEventListener('click', e => { e.stopPropagation(); copyEmail(id); });
            tr.querySelector('.ct-act-del')?.addEventListener('click', e => { e.stopPropagation(); del(id); });
            tr.addEventListener('contextmenu', e => { e.preventDefault(); showCtx(e, id); });
            tr.addEventListener('click', () => openForm(id));
        });
    }

    // ── Search via global topbar ──
    const topbarInput = document.getElementById('topbarSearchInput');
    if (topbarInput) {
        topbarInput.addEventListener('input', render);
    }

    // ── Add / Edit ──
    document.getElementById('ctAddBtn')?.addEventListener('click', () => openForm(null));

    function openForm(id) {
        closeCtx();
        editId = id;
        const c = id ? contacts.find(x => x.id === id) : {};
        document.getElementById('ctFormTitle').textContent = id ? 'Modifier le contact' : 'Nouveau contact';
        document.getElementById('ctfPrenom').value = c?.prenom || '';
        document.getElementById('ctfNom').value = c?.nom || '';
        document.getElementById('ctfEmail').value = c?.email || '';
        document.getElementById('ctfEntreprise').value = c?.entreprise || '';
        document.getElementById('ctfTel').value = c?.telephone || '';
        document.getElementById('ctfNotes').value = c?.notes || '';
        document.getElementById('ctfShared').checked = !!c?.is_shared;
        formModal.show();
        setTimeout(() => document.getElementById('ctfPrenom').focus(), 200);
    }

    document.getElementById('ctfSaveBtn')?.addEventListener('click', async () => {
        const data = {
            id: editId || '',
            prenom: document.getElementById('ctfPrenom').value.trim(),
            nom: document.getElementById('ctfNom').value.trim(),
            email: document.getElementById('ctfEmail').value.trim(),
            entreprise: document.getElementById('ctfEntreprise').value.trim(),
            telephone: document.getElementById('ctfTel').value.trim(),
            notes: document.getElementById('ctfNotes').value.trim(),
            is_shared: document.getElementById('ctfShared').checked ? 1 : 0,
        };
        if (!data.email) { showToast('Email requis', 'error'); return; }
        const res = await adminApiPost('admin_email_ext_save_contact', data);
        if (res.success) {
            showToast(editId ? 'Contact modifié' : 'Contact ajouté', 'success');
            formModal.hide();
            load();
        } else { showToast(res.message || 'Erreur', 'error'); }
    });

    // ── Delete ──
    async function del(id) {
        closeCtx();
        const c = contacts.find(x => x.id === id);
        const ok = await adminConfirm({
            title: 'Supprimer ce contact ?',
            text: escapeHtml([c?.prenom, c?.nom].filter(Boolean).join(' ') || c?.email),
            type: 'danger', icon: 'bi-trash3', okText: 'Supprimer'
        });
        if (!ok) return;
        const res = await adminApiPost('admin_email_ext_delete_contact', { id });
        if (res.success) { showToast('Contact supprimé', 'success'); load(); }
    }

    // ── Send email ──
    function sendTo(id) {
        closeCtx();
        const c = contacts.find(x => x.id === id);
        if (!c) return;
        // Use global compose if on email-externe page, otherwise navigate
        if (window.ztCompose) {
            // Can't send external email via internal compose — navigate to email-externe
        }
        window.location.href = '/zerdatime/admin/email-externe';
        // TODO: pre-fill compose with contact email
    }

    // ── Copy email ──
    function copyEmail(id) {
        closeCtx();
        const c = contacts.find(x => x.id === id);
        if (c) { navigator.clipboard.writeText(c.email); showToast('Email copié', 'success'); }
    }

    // ── Context menu ──
    function showCtx(e, id) {
        closeCtx();
        const c = contacts.find(x => x.id === id);
        if (!c) return;
        const menu = document.createElement('div');
        menu.className = 'ct-ctx';
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.innerHTML =
            '<button class="ct-ctx-item" data-a="send"><i class="bi bi-send"></i> Envoyer un email</button>'
            + '<button class="ct-ctx-item" data-a="copy"><i class="bi bi-clipboard"></i> Copier l\'email</button>'
            + '<button class="ct-ctx-item" data-a="edit"><i class="bi bi-pencil"></i> Modifier</button>'
            + '<div class="ct-ctx-sep"></div>'
            + '<button class="ct-ctx-item danger" data-a="delete"><i class="bi bi-trash3"></i> Supprimer</button>';
        document.body.appendChild(menu);
        // Viewport
        const r = menu.getBoundingClientRect();
        if (r.right > innerWidth) menu.style.left = (innerWidth - r.width - 8) + 'px';
        if (r.bottom > innerHeight) menu.style.top = (innerHeight - r.height - 8) + 'px';
        menu.querySelectorAll('.ct-ctx-item').forEach(item => {
            item.addEventListener('click', () => {
                const a = item.dataset.a;
                if (a === 'send') sendTo(id);
                else if (a === 'copy') copyEmail(id);
                else if (a === 'edit') openForm(id);
                else if (a === 'delete') del(id);
            });
        });
        ctxMenu = menu;
        setTimeout(() => document.addEventListener('click', closeCtx, { once: true }), 10);
    }
    function closeCtx() { if (ctxMenu) { ctxMenu.remove(); ctxMenu = null; } }

    // ── Import CSV ──
    document.getElementById('ctImportBtn')?.addEventListener('click', () => {
        document.getElementById('ctImportResult').innerHTML = '';
        importModal.show();
    });
    document.getElementById('ctEmptyImport')?.addEventListener('click', () => {
        document.getElementById('ctImportResult').innerHTML = '';
        importModal.show();
    });

    const zone = document.getElementById('ctImportZone');
    const fileInput = document.getElementById('ctImportFile');
    zone?.addEventListener('click', () => fileInput?.click());
    zone?.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--cl-accent)'; });
    zone?.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone?.addEventListener('drop', e => { e.preventDefault(); zone.style.borderColor = ''; if (e.dataTransfer.files[0]) processCSV(e.dataTransfer.files[0]); });
    fileInput?.addEventListener('change', e => { if (e.target.files[0]) processCSV(e.target.files[0]); });

    async function processCSV(file) {
        const resultEl = document.getElementById('ctImportResult');
        resultEl.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Lecture...</div>';
        const text = await file.text();
        const lines = text.split(/\r?\n/).filter(l => l.trim());
        if (lines.length < 2) { resultEl.innerHTML = '<div class="ct-alert-err">Fichier vide ou invalide</div>'; return; }
        const sep = lines[0].includes(';') ? ';' : ',';
        const headers = lines[0].split(sep).map(h => h.trim().toLowerCase().replace(/['"]/g, ''));
        const ei = headers.findIndex(h => h.includes('email') || h.includes('mail'));
        if (ei === -1) { resultEl.innerHTML = '<div class="ct-alert-err">Colonne "email" non trouvée</div>'; return; }
        const ni = headers.findIndex(h => h === 'nom' || h === 'last_name' || h === 'lastname');
        const pi = headers.findIndex(h => h === 'prenom' || h === 'prénom' || h === 'first_name' || h === 'firstname');
        const enti = headers.findIndex(h => h.includes('entreprise') || h.includes('company') || h.includes('organization'));
        const ti = headers.findIndex(h => h.includes('tel') || h.includes('phone'));
        const parsed = [];
        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(sep).map(c => c.trim().replace(/^['"]|['"]$/g, ''));
            if (!cols[ei]) continue;
            parsed.push({ email: cols[ei], nom: ni>=0?cols[ni]:'', prenom: pi>=0?cols[pi]:'', entreprise: enti>=0?cols[enti]:'', telephone: ti>=0?cols[ti]:'' });
        }
        if (!parsed.length) { resultEl.innerHTML = '<div class="ct-alert-err">Aucun contact valide</div>'; return; }
        resultEl.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Import de ' + parsed.length + ' contact(s)...</div>';
        const res = await adminApiPost('admin_email_ext_import_contacts', { contacts: parsed });
        if (res.success) {
            resultEl.innerHTML = '<div class="ct-alert-ok"><i class="bi bi-check-circle"></i> ' + escapeHtml(res.message) + '</div>';
            load();
            setTimeout(() => importModal.hide(), 1200);
        } else { resultEl.innerHTML = '<div class="ct-alert-err">' + escapeHtml(res.message || 'Erreur') + '</div>'; }
    }

    // ── Extract from emails ──
    document.getElementById('ctExtractBtn')?.addEventListener('click', () => { resetExtract(); extractModal.show(); });
    document.getElementById('ctEmptyExtract')?.addEventListener('click', () => { resetExtract(); extractModal.show(); });

    function resetExtract() {
        document.getElementById('ctExtractBody').innerHTML =
            '<div class="ct-extract-icon"><i class="bi bi-envelope-arrow-down"></i></div>'
            + '<p><strong>Scanner ma boîte email</strong></p>'
            + '<p class="small text-muted mb-3">Analyse les 500 derniers emails (Boîte de réception + Envoyés).<br>Les dossiers Spam, Corbeille et Brouillons sont ignorés.</p>'
            + '<button class="btn btn-primary" id="ctStartExtract"><i class="bi bi-search"></i> Lancer le scan</button>'
            + '<div id="ctExtractResult"></div>';
        document.getElementById('ctStartExtract')?.addEventListener('click', doExtract);
    }

    async function doExtract() {
        const btn = document.getElementById('ctStartExtract');
        const resultEl = document.getElementById('ctExtractResult');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scan en cours...';
        resultEl.innerHTML = '<p class="small text-muted mt-2">Connexion IMAP et analyse...</p>';

        const res = await adminApiPost('admin_email_ext_extract_contacts', {});
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search"></i> Lancer le scan';

        if (!res.success) { resultEl.innerHTML = '<div class="ct-alert-err">' + escapeHtml(res.message || 'Erreur') + '</div>'; return; }

        const found = res.contacts || [];
        const existingEmails = new Set(contacts.map(c => c.email.toLowerCase()));
        const newOnes = found.filter(c => !existingEmails.has(c.email.toLowerCase()));

        if (!newOnes.length) {
            resultEl.innerHTML = '<div class="ct-alert-ok"><i class="bi bi-check-circle"></i> ' + found.length + ' adresse(s) trouvée(s), toutes déjà dans le carnet.</div>';
            return;
        }

        resultEl.innerHTML =
            '<div class="text-left mt-3">'
            + '<div class="ct-extract-header">'
            + '<strong>' + newOnes.length + ' nouveau(x) contact(s)</strong>'
            + '<label class="small"><input type="checkbox" id="ctExtAll" checked> Tout sélectionner</label>'
            + '</div>'
            + '<div class="ct-extract-list">'
            + newOnes.map((c, i) => {
                const name = c.name || c.email.split('@')[0];
                return '<label><input type="checkbox" class="ct-ext-cb" data-idx="' + i + '" checked>'
                    + '<span><strong>' + escapeHtml(name) + '</strong> &lt;' + escapeHtml(c.email) + '&gt; <span class="ct-extract-freq">(' + c.count + ')</span></span></label>';
            }).join('')
            + '</div>'
            + '<div class="mt-3 text-end"><button class="btn btn-primary" id="ctDoExtImport"><i class="bi bi-download"></i> Importer la sélection</button></div>'
            + '</div>';

        document.getElementById('ctExtAll')?.addEventListener('change', e => {
            resultEl.querySelectorAll('.ct-ext-cb').forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('ctDoExtImport')?.addEventListener('click', async () => {
            const cbs = resultEl.querySelectorAll('.ct-ext-cb:checked');
            const toImport = [];
            cbs.forEach(cb => {
                const c = newOnes[parseInt(cb.dataset.idx)];
                if (c) { const p = (c.name||'').split(/\s+/); toImport.push({ email: c.email, prenom: p[0]||'', nom: p.slice(1).join(' ')||'' }); }
            });
            if (!toImport.length) { showToast('Aucun sélectionné', 'error'); return; }
            const r = await adminApiPost('admin_email_ext_import_contacts', { contacts: toImport });
            if (r.success) {
                showToast(r.message, 'success');
                load();
                setTimeout(() => extractModal.hide(), 800);
            } else { showToast(r.message || 'Erreur', 'error'); }
        });
    }

    // ── Init ──
    load();
    window.initContactsPage = () => {};
})();
</script>
