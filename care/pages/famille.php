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

/* ── Album gallery modal ── */
.fam-gal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
.fam-gal-item { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; cursor: pointer; background: var(--cl-bg, #F7F5F2); border: 1.5px solid var(--cl-border-light, #F0EDE8); transition: transform .15s; }
.fam-gal-item:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.fam-gal-item img { width: 100%; height: 100%; object-fit: cover; }
.fam-gal-item .fam-gal-del {
    position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border-radius: 50%;
    background: rgba(0,0,0,.55); color: #fff; border: none; font-size: .7rem; cursor: pointer;
    display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); transition: background .15s;
}
.fam-gal-item:hover .fam-gal-del { display: flex; }
.fam-gal-item .fam-gal-del:hover { background: #C53030; }
.fam-gal-loading { text-align: center; padding: 40px; color: var(--cl-text-muted); }

/* ── Lightbox fullscreen ── */
.fam-lb { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,.92); display: flex; align-items: center; justify-content: center; animation: famLbIn .2s; }
.fam-lb-hidden { display: none !important; }
.fam-lb img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 8px; user-select: none; transition: transform .2s; }
.fam-lb-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,.12); border: none; color: #fff; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.3rem; backdrop-filter: blur(6px); transition: background .2s; z-index: 2; }
.fam-lb-btn:hover { background: rgba(255,255,255,.25); }
.fam-lb-prev { left: 16px; }
.fam-lb-next { right: 16px; }
.fam-lb-close { position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,.12); border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; backdrop-filter: blur(6px); z-index: 2; }
.fam-lb-close:hover { background: rgba(255,255,255,.25); }
.fam-lb-counter { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.6); color: #fff; padding: 4px 14px; border-radius: 20px; font-size: .78rem; backdrop-filter: blur(6px); }
.fam-lb-zoom { position: absolute; bottom: 16px; right: 16px; display: flex; gap: 6px; }
.fam-lb-zoom button { background: rgba(255,255,255,.12); border: none; color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: .9rem; backdrop-filter: blur(6px); }
.fam-lb-zoom button:hover { background: rgba(255,255,255,.25); }
@keyframes famLbIn { from { opacity: 0; } to { opacity: 1; } }
.fam-item-card { background: var(--cl-surface, #fff); border: 1px solid var(--cl-border-light, #f0ede8); border-radius: 8px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: all .15s; }
.fam-item-card:hover { background: var(--cl-bg, #FAFAF7); border-color: var(--cl-border-hover, #d0d0c8); }
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
        <button class="btn btn-sm btn-outline-secondary" id="famBatchImportBtn"><i class="bi bi-cloud-upload"></i> Import par lot</button>
      </div>
      <div id="famGalList"></div>
    </div>
  </div>
</div>

<!-- Modal Activité -->
<div class="modal fade" id="famActModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="famActModalTitle">Nouvelle activité</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
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
  <div class="modal-header"><h6 class="modal-title" id="famMedModalTitle">Nouvel avis médical</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
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
  <div class="modal-header"><h6 class="modal-title" id="famAlbModalTitle">Nouvel album</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
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

<!-- Album Viewer Modal -->
<div class="modal fade" id="famGalViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="famGalViewTitle"><i class="bi bi-images me-2"></i>Album</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="famGalViewBody">
        <div class="fam-gal-loading"><span class="spinner-border spinner-border-sm"></span> Déchiffrement des photos...</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light btn-sm" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Photo Lightbox -->
<div id="famLightbox" class="fam-lb fam-lb-hidden">
  <button class="fam-lb-close" id="famLbClose"><i class="bi bi-x-lg"></i></button>
  <button class="fam-lb-btn fam-lb-prev" id="famLbPrev"><i class="bi bi-chevron-left"></i></button>
  <button class="fam-lb-btn fam-lb-next" id="famLbNext"><i class="bi bi-chevron-right"></i></button>
  <img id="famLbImg" src="" draggable="false">
  <div class="fam-lb-counter" id="famLbCounter"></div>
  <div class="fam-lb-zoom">
    <button id="famLbZoomOut" title="Zoom -"><i class="bi bi-zoom-out"></i></button>
    <button id="famLbZoomReset" title="Reset"><i class="bi bi-arrows-angle-contract"></i></button>
    <button id="famLbZoomIn" title="Zoom +"><i class="bi bi-zoom-in"></i></button>
  </div>
</div>

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

    // ── Convert image to WebP (client-side, via canvas) ──
    function convertToWebp(file, quality) {
        quality = quality || 0.82;
        return new Promise((resolve) => {
            // If already webp or not an image, skip conversion
            if (file.type === 'image/webp' || !file.type.startsWith('image/')) {
                resolve(file);
                return;
            }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                canvas.toBlob((blob) => {
                    URL.revokeObjectURL(url);
                    if (!blob) { resolve(file); return; }
                    const baseName = file.name.replace(/\.[^.]+$/, '') + '.webp';
                    resolve(new File([blob], baseName, { type: 'image/webp', lastModified: file.lastModified }));
                }, 'image/webp', quality);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    // ── Extract date from EXIF or filename ──
    function extractDateFromFile(file) {
        const name = file.name || '';
        // Try patterns: 2024-03-15, 20240315, IMG_20240315, etc.
        const m = name.match(/(\d{4})[-_]?(\d{2})[-_]?(\d{2})/);
        if (m) return m[1] + '-' + m[2] + '-' + m[3];
        // Try lastModified
        if (file.lastModified) {
            const d = new Date(file.lastModified);
            return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        }
        return null;
    }

    // Upload encrypted file helper
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

        const csrfToken = (window.__ZT_ADMIN__?.csrfToken || '');
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

    let activitesCache = [];

    function renderActivites(items) {
        activitesCache = items;
        const el = document.getElementById('famActList');
        if (!items.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucune activité</p>'; return; }
        el.innerHTML = items.map(a => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(a.titre)}</span> <span class="fam-item-date">${escapeHtml(a.date_activite)}</span></div>
                <div class="fam-item-actions">
                  <button data-fam-edit-act="${a.id}"><i class="bi bi-pencil"></i></button>
                  <button data-fam-upload-act="${a.id}"><i class="bi bi-images"></i> +Photos</button>
                  <button data-fam-del-act="${a.id}"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              ${a.description ? '<p class="text-muted small mb-1">' + escapeHtml(a.description) + '</p>' : ''}
              <small class="text-muted">${a.nb_photos || 0} photo(s) · Par ${escapeHtml((a.creator_prenom||'')+' '+(a.creator_nom||''))}</small>
            </div>
        `).join('');
    }

    // Event delegation for activités
    document.getElementById('famActList').addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-fam-edit-act]');
        if (editBtn) {
            const a = activitesCache.find(x => x.id === editBtn.dataset.famEditAct);
            if (a) {
                document.getElementById('famActEditId').value = a.id;
                document.getElementById('famActTitre').value = a.titre;
                document.getElementById('famActDate').value = a.date_activite;
                document.getElementById('famActDesc').value = a.description || '';
                document.getElementById('famActModalTitle').textContent = 'Modifier activité';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('famActModal')).show();
            }
            return;
        }
        const uploadBtn = e.target.closest('[data-fam-upload-act]');
        if (uploadBtn) {
            doUploadActPhotos(uploadBtn.dataset.famUploadAct);
            return;
        }
        const delBtn = e.target.closest('[data-fam-del-act]');
        if (delBtn) {
            doDeleteAct(delBtn.dataset.famDelAct);
            return;
        }
    });

    document.getElementById('famNewActBtn').addEventListener('click', () => {
        document.getElementById('famActEditId').value = '';
        document.getElementById('famActTitre').value = '';
        document.getElementById('famActDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famActDesc').value = '';
        document.getElementById('famActModalTitle').textContent = 'Nouvelle activité';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famActModal')).show();
    });

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

    async function doUploadActPhotos(actId) {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            for (const file of input.files) {
                const webpFile = await convertToWebp(file);
                const res = await uploadEncryptedFile(webpFile, 'admin_famille_upload_activite_photo', { activite_id: actId });
                if (res?.success) showToast('Photo ajoutée', 'success');
                else showToast(res?.message || 'Erreur upload', 'error');
            }
            loadActivites();
        });
        input.click();
    }

    async function doDeleteAct(id) {
        if (!confirm('Supprimer cette activité et ses photos ?')) return;
        const res = await adminApiPost('admin_famille_delete_activite', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadActivites(); }
    }

    // ═══ MÉDICAL ═════════════════════════════════════════════════════════════

    async function loadMedical() {
        const res = await adminApiPost('admin_famille_get_medical', { resident_id: selectedResident.id });
        if (!res.success) return;
        renderMedical(res.medical || []);
    }

    let medicalCache = [];

    function renderMedical(items) {
        medicalCache = items;
        const el = document.getElementById('famMedList');
        if (!items.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucun avis médical</p>'; return; }
        const typeBadge = t => `<span class="fam-med-type-badge fam-med-type-${t}">${t}</span>`;
        el.innerHTML = items.map(m => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(m.titre)}</span> ${typeBadge(m.type)} <span class="fam-item-date">${escapeHtml(m.date_avis)}</span></div>
                <div class="fam-item-actions">
                  <button data-fam-edit-med="${m.id}"><i class="bi bi-pencil"></i></button>
                  <button data-fam-upload-med="${m.id}"><i class="bi bi-paperclip"></i> +Fichier</button>
                  <button data-fam-del-med="${m.id}"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              ${m.nb_fichiers > 0 ? '<small class="text-muted">' + m.nb_fichiers + ' fichier(s)</small>' : ''}
            </div>
        `).join('');
    }

    document.getElementById('famMedList').addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-fam-edit-med]');
        if (editBtn) {
            const m = medicalCache.find(x => x.id === editBtn.dataset.famEditMed);
            if (m) {
                document.getElementById('famMedEditId').value = m.id;
                document.getElementById('famMedTitre').value = m.titre;
                document.getElementById('famMedDate').value = m.date_avis;
                document.getElementById('famMedType').value = m.type;
                document.getElementById('famMedContenu').value = '';
                document.getElementById('famMedModalTitle').textContent = 'Modifier avis médical';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('famMedModal')).show();
            }
            return;
        }
        const uploadBtn = e.target.closest('[data-fam-upload-med]');
        if (uploadBtn) { doUploadMedFile(uploadBtn.dataset.famUploadMed); return; }
        const delBtn = e.target.closest('[data-fam-del-med]');
        if (delBtn) { doDeleteMed(delBtn.dataset.famDelMed); return; }
    });

    document.getElementById('famNewMedBtn').addEventListener('click', () => {
        document.getElementById('famMedEditId').value = '';
        document.getElementById('famMedTitre').value = '';
        document.getElementById('famMedDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famMedType').value = 'avis';
        document.getElementById('famMedContenu').value = '';
        document.getElementById('famMedModalTitle').textContent = 'Nouvel avis médical';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famMedModal')).show();
    });

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

    async function doUploadMedFile(medId) {
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
    }

    async function doDeleteMed(id) {
        if (!confirm('Supprimer cet avis médical et ses fichiers ?')) return;
        const res = await adminApiPost('admin_famille_delete_medical', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadMedical(); }
    }

    // ═══ GALERIE ═════════════════════════════════════════════════════════════

    async function loadGalerie() {
        const res = await adminApiPost('admin_famille_get_galerie', { resident_id: selectedResident.id });
        if (!res.success) return;
        renderGalerie(res.albums || []);
    }

    let galerieCache = [];

    function renderGalerie(albums) {
        galerieCache = albums;
        const el = document.getElementById('famGalList');
        if (!albums.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucun album</p>'; return; }
        el.innerHTML = albums.map(a => `
            <div class="fam-item-card">
              <div class="fam-item-header">
                <div><span class="fam-item-title">${escapeHtml(a.titre)}</span> <span class="fam-item-date">${a.annee} — ${escapeHtml(a.date_galerie)}</span></div>
                <div class="fam-item-actions">
                  <button data-fam-edit-alb="${a.id}"><i class="bi bi-pencil"></i></button>
                  <button data-fam-upload-gal="${a.id}"><i class="bi bi-images"></i> +Photos</button>
                  <button data-fam-del-alb="${a.id}"><i class="bi bi-trash3"></i></button>
                </div>
              </div>
              <small class="text-muted">${a.nb_photos || 0} photo(s) · Par ${escapeHtml((a.creator_prenom||'')+' '+(a.creator_nom||''))}</small>
            </div>
        `).join('');
    }

    document.getElementById('famGalList').addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-fam-edit-alb]');
        if (editBtn) {
            const a = galerieCache.find(x => x.id === editBtn.dataset.famEditAlb);
            if (a) {
                document.getElementById('famAlbEditId').value = a.id;
                document.getElementById('famAlbTitre').value = a.titre;
                document.getElementById('famAlbDate').value = a.date_galerie;
                document.getElementById('famAlbAnnee').value = a.annee;
                document.getElementById('famAlbModalTitle').textContent = 'Modifier album';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('famAlbModal')).show();
            }
            return;
        }
        const uploadBtn = e.target.closest('[data-fam-upload-gal]');
        if (uploadBtn) { doUploadGalPhotos(uploadBtn.dataset.famUploadGal); return; }
        const delBtn = e.target.closest('[data-fam-del-alb]');
        if (delBtn) { doDeleteAlb(delBtn.dataset.famDelAlb); return; }
    });

    document.getElementById('famNewAlbBtn').addEventListener('click', () => {
        document.getElementById('famAlbEditId').value = '';
        document.getElementById('famAlbTitre').value = '';
        document.getElementById('famAlbDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('famAlbAnnee').value = new Date().getFullYear();
        document.getElementById('famAlbModalTitle').textContent = 'Nouvel album';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famAlbModal')).show();
    });

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

    async function doUploadGalPhotos(albumId) {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            for (const file of input.files) {
                const webpFile = await convertToWebp(file);
                const res = await uploadEncryptedFile(webpFile, 'admin_famille_upload_galerie_photo', { galerie_id: albumId });
                if (res?.success) showToast('Photo ajoutée', 'success');
                else showToast(res?.message || 'Erreur upload', 'error');
            }
            loadGalerie();
        });
        input.click();
    }

    async function doDeleteAlb(id) {
        if (!confirm('Supprimer cet album et toutes ses photos ?')) return;
        const res = await adminApiPost('admin_famille_delete_album', { id });
        if (res.success) { showToast('Supprimé', 'success'); loadGalerie(); }
    }

    // Reset on resident change
    document.querySelectorAll('#famTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => { aesKey = null; });
    });

    // ═══ IMPORT PAR LOT ═══════════════════════════════════════════════════════
    // Sélectionne N photos → extrait la date de chaque → regroupe par mois
    // → crée un album par mois → upload les photos convertis en webp + chiffrées

    document.getElementById('famBatchImportBtn').addEventListener('click', () => {
        if (!selectedResident) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*';
        input.addEventListener('change', () => doBatchImport(input.files));
        input.click();
    });

    async function doBatchImport(files) {
        if (!files.length) return;

        const MOIS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

        // Group files by month
        const byMonth = {};
        for (const file of files) {
            const dateStr = extractDateFromFile(file);
            let monthKey, year, month;
            if (dateStr) {
                year = dateStr.slice(0, 4);
                month = parseInt(dateStr.slice(5, 7));
                monthKey = year + '-' + String(month).padStart(2, '0');
            } else {
                year = String(new Date().getFullYear());
                month = new Date().getMonth() + 1;
                monthKey = year + '-' + String(month).padStart(2, '0');
            }
            if (!byMonth[monthKey]) byMonth[monthKey] = { year, month, files: [] };
            byMonth[monthKey].files.push(file);
        }

        const months = Object.keys(byMonth).sort();
        showToast('Import de ' + files.length + ' photos dans ' + months.length + ' album(s)…', 'info');

        for (const mk of months) {
            const group = byMonth[mk];
            const titre = MOIS_FR[group.month - 1] + ' ' + group.year;
            const dateGalerie = mk + '-01';

            // Create album
            const albRes = await adminApiPost('admin_famille_save_album', {
                resident_id: selectedResident.id,
                titre: titre,
                date_galerie: dateGalerie,
                annee: group.year
            });
            if (!albRes.success) { showToast('Erreur création album ' + titre, 'error'); continue; }

            const albumId = albRes.id;
            let count = 0;
            for (const file of group.files) {
                const webpFile = await convertToWebp(file);
                const res = await uploadEncryptedFile(webpFile, 'admin_famille_upload_galerie_photo', { galerie_id: albumId });
                if (res?.success) count++;
            }
            showToast(titre + ' : ' + count + ' photo(s) importées', 'success');
        }

        loadGalerie();
    }

    // ═══ ALBUM VIEWER ═════════════════════════════════════════════════════════
    let viewerPhotos = []; // [{url, id}]
    let viewerIndex = 0;

    // Click on album card (not on buttons) → open viewer
    document.getElementById('famGalList').addEventListener('click', (e) => {
        // Skip if clicked on a button
        if (e.target.closest('[data-fam-edit-alb]') || e.target.closest('[data-fam-upload-gal]') || e.target.closest('[data-fam-del-alb]')) return;
        const card = e.target.closest('.fam-item-card');
        if (!card) return;
        // Find album id from the first button
        const btn = card.querySelector('[data-fam-edit-alb]');
        if (!btn) return;
        const albumId = btn.dataset.famEditAlb;
        const album = galerieCache.find(a => a.id === albumId);
        if (album) openAlbumViewer(album);
    });

    async function openAlbumViewer(album) {
        document.getElementById('famGalViewTitle').innerHTML = '<i class="bi bi-images me-2"></i>' + escapeHtml(album.titre);
        const body = document.getElementById('famGalViewBody');
        body.innerHTML = '<div class="fam-gal-loading"><span class="spinner-border spinner-border-sm"></span> Déchiffrement des photos...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('famGalViewModal')).show();

        const photos = album.photos || [];
        if (!photos.length) {
            body.innerHTML = '<p class="text-muted text-center py-4">Aucune photo dans cet album</p>';
            return;
        }

        const key = await getAesKey();
        if (!key) { body.innerHTML = '<p class="text-danger text-center py-4">Impossible de déchiffrer (clé E2EE manquante)</p>'; return; }

        viewerPhotos = [];
        let grid = '<div class="fam-gal-grid">';

        for (let i = 0; i < photos.length; i++) {
            const p = photos[i];
            try {
                const res = await fetch('/zerdatime/admin/api.php?action=admin_famille_upload_galerie_photo&file=' + encodeURIComponent(p.file_name));
                if (!res.ok) { viewerPhotos.push({ url: '', id: p.id }); grid += '<div class="fam-gal-item"><div class="text-center text-muted py-4"><i class="bi bi-image"></i></div></div>'; continue; }
                const encData = await res.arrayBuffer();
                const iv = hexToBytes(p.encrypted_iv);
                const decrypted = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, encData);
                const blob = new Blob([decrypted], { type: 'image/webp' });
                const url = URL.createObjectURL(blob);
                viewerPhotos.push({ url, id: p.id });
                grid += '<div class="fam-gal-item" data-gal-idx="' + i + '">'
                    + '<img src="' + url + '">'
                    + '<button class="fam-gal-del" data-gal-del="' + p.id + '" title="Supprimer"><i class="bi bi-trash3"></i></button>'
                    + '</div>';
            } catch(e) {
                viewerPhotos.push({ url: '', id: p.id });
                grid += '<div class="fam-gal-item"><div class="text-center text-muted py-4" style="display:flex;align-items:center;justify-content:center;height:100%"><i class="bi bi-shield-exclamation"></i></div></div>';
            }
        }
        grid += '</div>';
        body.innerHTML = grid;

        // Click photo → lightbox
        body.querySelectorAll('[data-gal-idx]').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('.fam-gal-del')) return;
                openFamLightbox(parseInt(el.dataset.galIdx));
            });
        });

        // Delete photo
        body.querySelectorAll('[data-gal-del]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm('Supprimer cette photo ?')) return;
                const r = await adminApiPost('admin_famille_delete_photo', { id: btn.dataset.galDel });
                if (r.success) {
                    showToast('Photo supprimée', 'success');
                    // Refresh album
                    const res = await adminApiPost('admin_famille_get_galerie', { resident_id: selectedResident.id });
                    if (res.success) {
                        galerieCache = res.albums || [];
                        renderGalerie(galerieCache);
                        const updated = galerieCache.find(a => a.id === album.id);
                        if (updated) openAlbumViewer(updated);
                        else bootstrap.Modal.getInstance(document.getElementById('famGalViewModal'))?.hide();
                    }
                }
            });
        });
    }

    function hexToBytes(hex) {
        const bytes = new Uint8Array(hex.length / 2);
        for (let i = 0; i < hex.length; i += 2) bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
        return bytes;
    }

    // ═══ LIGHTBOX ════════════════════════════════════════════════════════════
    const lb = document.getElementById('famLightbox');
    const lbImg = document.getElementById('famLbImg');
    const lbCounter = document.getElementById('famLbCounter');
    let lbZoom = 1;

    function openFamLightbox(idx) {
        viewerIndex = idx;
        lbZoom = 1;
        updateLightbox();
        lb.classList.remove('fam-lb-hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeFamLightbox() {
        lb.classList.add('fam-lb-hidden');
        document.body.style.overflow = '';
    }

    function updateLightbox() {
        const p = viewerPhotos[viewerIndex];
        if (!p || !p.url) return;
        lbImg.src = p.url;
        lbImg.style.transform = 'scale(' + lbZoom + ')';
        lbCounter.textContent = (viewerIndex + 1) + ' / ' + viewerPhotos.length;
        document.getElementById('famLbPrev').style.display = viewerIndex > 0 ? '' : 'none';
        document.getElementById('famLbNext').style.display = viewerIndex < viewerPhotos.length - 1 ? '' : 'none';
    }

    document.getElementById('famLbClose').addEventListener('click', closeFamLightbox);
    lb.addEventListener('click', (e) => { if (e.target === lb) closeFamLightbox(); });
    document.getElementById('famLbPrev').addEventListener('click', () => { if (viewerIndex > 0) { viewerIndex--; lbZoom = 1; updateLightbox(); } });
    document.getElementById('famLbNext').addEventListener('click', () => { if (viewerIndex < viewerPhotos.length - 1) { viewerIndex++; lbZoom = 1; updateLightbox(); } });
    document.getElementById('famLbZoomIn').addEventListener('click', () => { lbZoom = Math.min(3, lbZoom + 0.25); updateLightbox(); });
    document.getElementById('famLbZoomOut').addEventListener('click', () => { lbZoom = Math.max(0.25, lbZoom - 0.25); updateLightbox(); });
    document.getElementById('famLbZoomReset').addEventListener('click', () => { lbZoom = 1; updateLightbox(); });
    document.addEventListener('keydown', (e) => {
        if (lb.classList.contains('fam-lb-hidden')) return;
        if (e.key === 'Escape') closeFamLightbox();
        if (e.key === 'ArrowLeft' && viewerIndex > 0) { viewerIndex--; lbZoom = 1; updateLightbox(); }
        if (e.key === 'ArrowRight' && viewerIndex < viewerPhotos.length - 1) { viewerIndex++; lbZoom = 1; updateLightbox(); }
    });

    window.initFamillePage = () => {};
})();
</script>
