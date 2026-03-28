<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$famResidents = Db::fetchAll(
    "SELECT r.id, r.nom, r.prenom, r.chambre, r.etage, r.correspondant_email, r.code_acces, r.date_naissance,
            (SELECT COUNT(*) FROM famille_encryption_keys WHERE resident_id = r.id) AS has_key
     FROM residents r WHERE r.is_active = 1 ORDER BY r.nom, r.prenom"
);
?>
<style>
.fam-admin-select { max-width: 400px; }
.fam-admin-tabs .nav-link { font-size: .85rem; font-weight: 600; }
.fam-key-status { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; padding: 4px 10px; border-radius: 20px; }
.fam-key-ok { background: #E8F5E9; color: #2E7D32; }
.fam-key-missing { background: #FFF3E0; color: #E65100; }
.fam-upload-zone { border: 2px dashed var(--cl-border); border-radius: 8px; padding: 40px 20px; text-align: center; color: var(--cl-text-muted); cursor: pointer; transition: border-color .2s, background .2s; }
.fam-upload-zone:hover, .fam-upload-zone.dragover { border-color: var(--cl-accent); background: rgba(25,25,24,.02); }
.fam-thumb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; margin-top: 12px; }
.fam-thumb-item { position: relative; aspect-ratio: 1; background: #f0f0f0; border-radius: 6px; overflow: hidden; }
.fam-thumb-item img { width: 100%; height: 100%; object-fit: cover; }
.fam-thumb-del { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,.5); color: #fff; border: none; font-size: .7rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.fam-file-list { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
.fam-file-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: var(--cl-bg); border-radius: 6px; font-size: .85rem; }
.fam-file-row .del { margin-left: auto; background: none; border: none; color: var(--cl-text-muted); cursor: pointer; }
.fam-file-row .del:hover { color: #C53030; }
.fam-item-card { background: var(--cl-surface, #fff); border: 1px solid var(--cl-border-light, #f0ede8); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.fam-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.fam-item-title { font-weight: 700; }
.fam-item-date { font-size: .8rem; color: var(--cl-text-muted); }
.fam-item-actions { display: flex; gap: 4px; }
.fam-item-actions button { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--cl-border); background: transparent; cursor: pointer; font-size: .8rem; }
.fam-item-actions button:hover { background: var(--cl-bg); }
.fam-med-type-badge { font-size: .75rem; padding: 2px 8px; border-radius: 20px; }
.fam-med-type-avis { background: #E8F5E9; color: #2E7D32; }
.fam-med-type-rapport { background: #E3F2FD; color: #1976D2; }
.fam-med-type-ordonnance { background: #FFF3E0; color: #E65100; }
.fam-med-type-autre { background: #F5F0EB; color: #8B7355; }
</style>

<h5 class="mb-3"><i class="bi bi-house-heart"></i> Espace Famille</h5>

<!-- Sélecteur résident -->
<div class="d-flex align-items-center gap-3 mb-3">
  <div class="zs-select fam-admin-select" id="famResidentSelect" data-placeholder="Sélectionner un résident"></div>
  <span class="fam-key-status" id="famKeyStatus"></span>
  <button class="btn btn-sm btn-outline-secondary" id="famSetupKeyBtn" style="display:none"><i class="bi bi-key"></i> Générer la clé E2EE</button>
</div>

<!-- Contenu (affiché après sélection) -->
<div id="famContent" style="display:none">
  <ul class="nav nav-tabs fam-admin-tabs mb-3" id="famTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#famTabAct"><i class="bi bi-calendar-event"></i> Activités</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#famTabMed"><i class="bi bi-heart-pulse"></i> Médical</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#famTabGal"><i class="bi bi-images"></i> Galerie</button></li>
  </ul>

  <div class="tab-content">
    <!-- Activités -->
    <div class="tab-pane fade show active" id="famTabAct">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Activités</h6>
        <button class="btn btn-sm btn-outline-dark" id="famNewActBtn"><i class="bi bi-plus-lg"></i> Nouvelle activité</button>
      </div>
      <div id="famActList"></div>
    </div>

    <!-- Médical -->
    <div class="tab-pane fade" id="famTabMed">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Suivi médical</h6>
        <button class="btn btn-sm btn-outline-dark" id="famNewMedBtn"><i class="bi bi-plus-lg"></i> Nouvel avis</button>
      </div>
      <div id="famMedList"></div>
    </div>

    <!-- Galerie -->
    <div class="tab-pane fade" id="famTabGal">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Albums photo</h6>
        <button class="btn btn-sm btn-outline-dark" id="famNewAlbBtn"><i class="bi bi-plus-lg"></i> Nouvel album</button>
      </div>
      <div id="famGalList"></div>
    </div>
  </div>
</div>

<!-- Modal Activité -->
<div class="modal fade" id="famActModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="famActModalTitle">Nouvelle activité</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="famActEditId">
    <div class="mb-3"><label class="form-label fw-600">Titre</label><input type="text" class="form-control" id="famActTitre"></div>
    <div class="mb-3"><label class="form-label fw-600">Date</label><input type="date" class="form-control" id="famActDate"></div>
    <div class="mb-3"><label class="form-label fw-600">Description</label><textarea class="form-control" id="famActDesc" rows="3"></textarea></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
    <button class="btn btn-dark btn-sm" id="famActSaveBtn">Enregistrer</button>
  </div>
</div></div></div>

<!-- Modal Médical -->
<div class="modal fade" id="famMedModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="famMedModalTitle">Nouvel avis médical</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="famMedEditId">
    <div class="mb-3"><label class="form-label fw-600">Titre</label><input type="text" class="form-control" id="famMedTitre"></div>
    <div class="row mb-3">
      <div class="col"><label class="form-label fw-600">Date</label><input type="date" class="form-control" id="famMedDate"></div>
      <div class="col"><label class="form-label fw-600">Type</label>
        <select class="form-select" id="famMedType"><option value="avis">Avis</option><option value="rapport">Rapport</option><option value="ordonnance">Ordonnance</option><option value="autre">Autre</option></select>
      </div>
    </div>
    <div class="mb-3"><label class="form-label fw-600">Contenu <small class="text-muted">(sera chiffré)</small></label><textarea class="form-control" id="famMedContenu" rows="4"></textarea></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
    <button class="btn btn-dark btn-sm" id="famMedSaveBtn">Enregistrer</button>
  </div>
</div></div></div>

<!-- Modal Album -->
<div class="modal fade" id="famAlbModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="famAlbModalTitle">Nouvel album</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="famAlbEditId">
    <div class="mb-3"><label class="form-label fw-600">Titre</label><input type="text" class="form-control" id="famAlbTitre"></div>
    <div class="row mb-3">
      <div class="col"><label class="form-label fw-600">Date</label><input type="date" class="form-control" id="famAlbDate"></div>
      <div class="col"><label class="form-label fw-600">Année</label><input type="number" class="form-control" id="famAlbAnnee" value="<?= date('Y') ?>"></div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
    <button class="btn btn-dark btn-sm" id="famAlbSaveBtn">Enregistrer</button>
  </div>
</div></div></div>

<script<?= nonce() ?> src="/zerdatime/website/assets/js/famille-crypto.js"></script>
<script<?= nonce() ?>>
(function() {
    const residents = <?= json_encode(array_values($famResidents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let selectedResident = null;
    let aesKey = null;

    // ── Resident select ──
    const resOpts = residents.map(r => ({
        value: r.id,
        label: r.prenom + ' ' + r.nom + (r.chambre ? ' — Ch.' + r.chambre : '')
    }));
    zerdaSelect.init('#famResidentSelect', resOpts, { search: true, onSelect: onResidentSelect });

    async function onResidentSelect(val) {
        selectedResident = residents.find(r => r.id === val);
        if (!selectedResident) { document.getElementById('famContent').style.display = 'none'; return; }

        document.getElementById('famContent').style.display = '';

        // Key status
        updateKeyStatus();
        loadActivites();
        loadMedical();
        loadGalerie();
    }

    function updateKeyStatus() {
        const el = document.getElementById('famKeyStatus');
        const btn = document.getElementById('famSetupKeyBtn');
        if (parseInt(selectedResident.has_key)) {
            el.className = 'fam-key-status fam-key-ok';
            el.innerHTML = '<i class="bi bi-check-circle"></i> Clé E2EE active';
            btn.style.display = '';
            btn.textContent = 'Régénérer la clé';
        } else {
            el.className = 'fam-key-status fam-key-missing';
            el.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Aucune clé E2EE';
            btn.style.display = '';
            btn.innerHTML = '<i class="bi bi-key"></i> Générer la clé E2EE';
        }
    }

    // ── Setup E2EE key ──
    document.getElementById('famSetupKeyBtn').addEventListener('click', async () => {
        if (!selectedResident) return;

        // Derive the password (code_acces or date_naissance formatted)
        let pwd = selectedResident.code_acces;
        if (!pwd && selectedResident.date_naissance) {
            const dt = new Date(selectedResident.date_naissance + 'T00:00:00');
            pwd = String(dt.getDate()).padStart(2,'0') + String(dt.getMonth()+1).padStart(2,'0') + dt.getFullYear();
        }
        if (!pwd) { showToast('Ce résident n\'a pas de code d\'accès configuré', 'error'); return; }

        try {
            const residentKey = await FamilleCrypto.generateResidentKey();
            const wrapped = await FamilleCrypto.wrapKey(residentKey, pwd);

            const res = await adminApiPost('admin_famille_setup_key', {
                resident_id: selectedResident.id,
                encrypted_key: wrapped.encrypted_key,
                salt: wrapped.salt,
                iv: wrapped.iv
            });

            if (res.success) {
                showToast('Clé E2EE générée avec succès', 'success');
                selectedResident.has_key = '1';
                aesKey = residentKey;
                updateKeyStatus();
            } else { showToast(res.message || 'Erreur', 'error'); }
        } catch(e) {
            showToast('Erreur de génération de clé: ' + e.message, 'error');
        }
    });

    // ── Helper: get AES key (unwrap if needed) ──
    async function getAesKey() {
        if (aesKey) return aesKey;

        // Fetch the wrapped key
        const res = await adminApiPost('admin_famille_get_key', { resident_id: selectedResident.id });
        if (!res.key) { showToast('Aucune clé E2EE. Générez-en une d\'abord.', 'error'); return null; }

        let pwd = selectedResident.code_acces;
        if (!pwd && selectedResident.date_naissance) {
            const dt = new Date(selectedResident.date_naissance + 'T00:00:00');
            pwd = String(dt.getDate()).padStart(2,'0') + String(dt.getMonth()+1).padStart(2,'0') + dt.getFullYear();
        }
        if (!pwd) { showToast('Pas de code d\'accès pour ce résident', 'error'); return null; }

        try {
            aesKey = await FamilleCrypto.unwrapKey(res.key.encrypted_key, res.key.salt, res.key.iv, pwd);
            return aesKey;
        } catch(e) {
            showToast('Impossible de déchiffrer la clé E2EE', 'error');
            return null;
        }
    }

    // ── Encrypt and upload a file ──
    async function encryptAndUpload(file, action, extraData) {
        const key = await getAesKey();
        if (!key) return null;

        const buf = await file.arrayBuffer();
        const encrypted = await FamilleCrypto.encryptFile(key, buf);

        const blob = new Blob([encrypted.data]);
        const fd = new FormData();
        fd.append('file', blob, 'encrypted.enc');
        fd.append('encrypted_iv', encrypted.iv);
        fd.append('file_name', file.name);
        Object.entries(extraData || {}).forEach(([k, v]) => fd.append(k, v));

        const resp = await fetch('/zerdatime/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: fd
        });

        // Need to add action since FormData doesn't go in JSON
        // Actually admin api.php expects action in POST
        // Re-do with action in form data
        return null; // will handle properly below
    }

    // Better upload helper
    async function uploadEncryptedFile(file, action, extraFields) {
        const key = await getAesKey();
        if (!key) return null;

        const buf = await file.arrayBuffer();
        const encrypted = await FamilleCrypto.encryptFile(key, buf);
        const blob = new Blob([encrypted.data]);

        const fd = new FormData();
        fd.append('action', action);
        fd.append('file', blob, 'encrypted.enc');
        fd.append('encrypted_iv', encrypted.iv);
        fd.append('file_name', file.name);
        Object.entries(extraFields || {}).forEach(([k, v]) => fd.append(k, v));

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/zerdatime/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd
        });
        return resp.json();
    }

    // ═══ ACTIVITÉS ═══════════════════════════════════════════════════════════

    async function loadActivites() {
        const res = await adminApiPost('admin_famille_get_activites', { resident_id: selectedResident.id });
        if (!res.success) return;
        renderActivites(res.activites || []);
    }

    function renderActivites(items) {
        const el = document.getElementById('famActList');
        if (!items.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucune activité</p>'; return; }
        el.innerHTML = items.map(a => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(a.titre)}</span> <span class="fam-item-date">${escapeHtml(a.date_activite)}</span></div>
                <div class="fam-item-actions">
                  <button onclick="famEditAct('${a.id}','${escapeHtml(a.titre)}','${a.date_activite}','${escapeHtml(a.description||'')}')"><i class="bi bi-pencil"></i></button>
                  <button onclick="famUploadActPhotos('${a.id}')"><i class="bi bi-images"></i> +Photos</button>
                  <button onclick="famDeleteAct('${a.id}')"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              ${a.description ? '<p class="text-muted small mb-1">' + escapeHtml(a.description) + '</p>' : ''}
              <small class="text-muted">${a.nb_photos || 0} photo(s) · Par ${escapeHtml((a.creator_prenom||'')+' '+(a.creator_nom||''))}</small>
            </div>
        `).join('');
    }

    document.getElementById('famNewActBtn').addEventListener('click', () => {
        document.getElementById('famActEditId').value = '';
        document.getElementById('famActTitre').value = '';
        document.getElementById('famActDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famActDesc').value = '';
        document.getElementById('famActModalTitle').textContent = 'Nouvelle activité';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famActModal')).show();
    });

    window.famEditAct = (id, titre, date, desc) => {
        document.getElementById('famActEditId').value = id;
        document.getElementById('famActTitre').value = titre;
        document.getElementById('famActDate').value = date;
        document.getElementById('famActDesc').value = desc;
        document.getElementById('famActModalTitle').textContent = 'Modifier activité';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famActModal')).show();
    };

    document.getElementById('famActSaveBtn').addEventListener('click', async () => {
        const res = await adminApiPost('admin_famille_save_activite', {
            id: document.getElementById('famActEditId').value,
            resident_id: selectedResident.id,
            titre: document.getElementById('famActTitre').value,
            date_activite: document.getElementById('famActDate').value,
            description: document.getElementById('famActDesc').value
        });
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('famActModal'))?.hide();
            showToast(res.message, 'success');
            loadActivites();
        } else { showToast(res.message || 'Erreur', 'error'); }
    });

    window.famUploadActPhotos = async (actId) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            for (const file of input.files) {
                const res = await uploadEncryptedFile(file, 'admin_famille_upload_activite_photo', { activite_id: actId });
                if (res?.success) showToast('Photo ajoutée', 'success');
                else showToast(res?.message || 'Erreur upload', 'error');
            }
            loadActivites();
        });
        input.click();
    };

    window.famDeleteAct = async (id) => {
        if (!confirm('Supprimer cette activité et ses photos ?')) return;
        const res = await adminApiPost('admin_famille_delete_activite', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadActivites(); }
    };

    // ═══ MÉDICAL ═════════════════════════════════════════════════════════════

    async function loadMedical() {
        const res = await adminApiPost('admin_famille_get_medical', { resident_id: selectedResident.id });
        if (!res.success) return;
        renderMedical(res.medical || []);
    }

    function renderMedical(items) {
        const el = document.getElementById('famMedList');
        if (!items.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucun avis médical</p>'; return; }
        const typeBadge = t => `<span class="fam-med-type-badge fam-med-type-${t}">${t}</span>`;
        el.innerHTML = items.map(m => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(m.titre)}</span> ${typeBadge(m.type)} <span class="fam-item-date">${escapeHtml(m.date_avis)}</span></div>
                <div class="fam-item-actions">
                  <button onclick="famEditMed('${m.id}','${escapeHtml(m.titre)}','${m.date_avis}','${m.type}')"><i class="bi bi-pencil"></i></button>
                  <button onclick="famUploadMedFile('${m.id}')"><i class="bi bi-paperclip"></i> +Fichier</button>
                  <button onclick="famDeleteMed('${m.id}')"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              ${m.nb_fichiers > 0 ? '<small class="text-muted">' + m.nb_fichiers + ' fichier(s)</small>' : ''}
            </div>
        `).join('');
    }

    document.getElementById('famNewMedBtn').addEventListener('click', () => {
        document.getElementById('famMedEditId').value = '';
        document.getElementById('famMedTitre').value = '';
        document.getElementById('famMedDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famMedType').value = 'avis';
        document.getElementById('famMedContenu').value = '';
        document.getElementById('famMedModalTitle').textContent = 'Nouvel avis médical';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famMedModal')).show();
    });

    window.famEditMed = (id, titre, date, type) => {
        document.getElementById('famMedEditId').value = id;
        document.getElementById('famMedTitre').value = titre;
        document.getElementById('famMedDate').value = date;
        document.getElementById('famMedType').value = type;
        document.getElementById('famMedContenu').value = '';
        document.getElementById('famMedModalTitle').textContent = 'Modifier avis médical';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famMedModal')).show();
    };

    document.getElementById('famMedSaveBtn').addEventListener('click', async () => {
        const contenu = document.getElementById('famMedContenu').value.trim();
        let contenuChiffre = null, contentIv = null;

        if (contenu) {
            const key = await getAesKey();
            if (key) {
                const enc = await FamilleCrypto.encryptText(key, contenu);
                contenuChiffre = enc.ciphertext;
                contentIv = enc.iv;
            }
        }

        const res = await adminApiPost('admin_famille_save_medical', {
            id: document.getElementById('famMedEditId').value,
            resident_id: selectedResident.id,
            titre: document.getElementById('famMedTitre').value,
            date_avis: document.getElementById('famMedDate').value,
            type: document.getElementById('famMedType').value,
            contenu_chiffre: contenuChiffre,
            content_iv: contentIv
        });
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('famMedModal'))?.hide();
            showToast(res.message, 'success');
            loadMedical();
        } else { showToast(res.message || 'Erreur', 'error'); }
    });

    window.famUploadMedFile = async (medId) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';
        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) return;
            const res = await uploadEncryptedFile(file, 'admin_famille_upload_medical_fichier', { medical_id: medId });
            if (res?.success) { showToast('Fichier ajouté', 'success'); loadMedical(); }
            else showToast(res?.message || 'Erreur upload', 'error');
        });
        input.click();
    };

    window.famDeleteMed = async (id) => {
        if (!confirm('Supprimer cet avis médical et ses fichiers ?')) return;
        const res = await adminApiPost('admin_famille_delete_medical', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadMedical(); }
    };

    // ═══ GALERIE ═════════════════════════════════════════════════════════════

    async function loadGalerie() {
        const res = await adminApiPost('admin_famille_get_galerie', { resident_id: selectedResident.id });
        if (!res.success) return;
        renderGalerie(res.albums || []);
    }

    function renderGalerie(albums) {
        const el = document.getElementById('famGalList');
        if (!albums.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucun album</p>'; return; }
        el.innerHTML = albums.map(a => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(a.titre)}</span> <span class="fam-item-date">${a.annee} — ${escapeHtml(a.date_galerie)}</span></div>
                <div class="fam-item-actions">
                  <button onclick="famEditAlb('${a.id}','${escapeHtml(a.titre)}','${a.date_galerie}','${a.annee}')"><i class="bi bi-pencil"></i></button>
                  <button onclick="famUploadGalPhotos('${a.id}')"><i class="bi bi-images"></i> +Photos</button>
                  <button onclick="famDeleteAlb('${a.id}')"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              <small class="text-muted">${a.nb_photos || 0} photo(s) · Par ${escapeHtml((a.creator_prenom||'')+' '+(a.creator_nom||''))}</small>
            </div>
        `).join('');
    }

    document.getElementById('famNewAlbBtn').addEventListener('click', () => {
        document.getElementById('famAlbEditId').value = '';
        document.getElementById('famAlbTitre').value = '';
        document.getElementById('famAlbDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famAlbAnnee').value = new Date().getFullYear();
        document.getElementById('famAlbModalTitle').textContent = 'Nouvel album';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famAlbModal')).show();
    });

    window.famEditAlb = (id, titre, date, annee) => {
        document.getElementById('famAlbEditId').value = id;
        document.getElementById('famAlbTitre').value = titre;
        document.getElementById('famAlbDate').value = date;
        document.getElementById('famAlbAnnee').value = annee;
        document.getElementById('famAlbModalTitle').textContent = 'Modifier album';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famAlbModal')).show();
    };

    document.getElementById('famAlbSaveBtn').addEventListener('click', async () => {
        const res = await adminApiPost('admin_famille_save_album', {
            id: document.getElementById('famAlbEditId').value,
            resident_id: selectedResident.id,
            titre: document.getElementById('famAlbTitre').value,
            date_galerie: document.getElementById('famAlbDate').value,
            annee: document.getElementById('famAlbAnnee').value
        });
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('famAlbModal'))?.hide();
            showToast(res.message, 'success');
            loadGalerie();
        } else { showToast(res.message || 'Erreur', 'error'); }
    });

    window.famUploadGalPhotos = async (albumId) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            for (const file of input.files) {
                const res = await uploadEncryptedFile(file, 'admin_famille_upload_galerie_photo', { galerie_id: albumId });
                if (res?.success) showToast('Photo ajoutée', 'success');
                else showToast(res?.message || 'Erreur upload', 'error');
            }
            loadGalerie();
        });
        input.click();
    };

    window.famDeleteAlb = async (id) => {
        if (!confirm('Supprimer cet album et toutes ses photos ?')) return;
        const res = await adminApiPost('admin_famille_delete_album', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadGalerie(); }
    };

    // Reset on resident change
    document.querySelectorAll('#famTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => { aesKey = null; });
    });

    window.initFamillePage = () => {};
})();
</script>
