<?php
/**
 * SpocCare — Annonce Editor (TipTap) + Image Picker (Local + Pixabay)
 */
$user = $_SESSION['ss_user'];
if (!in_array($user['role'], ['admin', 'direction', 'responsable'])) {
    echo '<div class="alert alert-danger m-3">Accès réservé aux responsables</div>';
    return;
}

$annonceId = $_GET['id'] ?? '';
$annonceData = null;
if ($annonceId) {
    $annonceData = Db::fetch("SELECT * FROM annonces WHERE id = ?", [$annonceId]);
}
$isNew = !$annonceData;
?>
<style>
.ann-editor-wrap { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:20px; }
.ann-editor-title input { font-size:1.2rem; font-weight:700; border:none; border-bottom:2px solid #e9ecef; border-radius:0; padding:6px 2px; width:100%; transition:border-color .2s; outline:none; }
.ann-editor-title input:focus { border-color:var(--care-primary, #2d4a43); }
.ann-desc-input { font-size:.85rem; border:none; border-bottom:1px solid #e9ecef; border-radius:0; padding:4px 2px; width:100%; color:#6c757d; outline:none; }
.ann-desc-input:focus { border-color:var(--care-primary, #2d4a43); }

.ann-cover-zone {
    border:2px dashed #dee2e6; border-radius:10px; padding:20px; text-align:center;
    color:#adb5bd; cursor:pointer; transition:all .2s; position:relative; overflow:hidden;
    min-height:120px; display:flex; align-items:center; justify-content:center; flex-direction:column;
    margin:12px 0;
}
.ann-cover-zone:hover { border-color:var(--care-primary, #2d4a43); color:#6c757d; background:#f8faf9; }
.ann-cover-zone.has-image { border:none; padding:0; }
.ann-cover-zone img { width:100%; max-height:250px; object-fit:cover; border-radius:10px; }
.ann-cover-zone .bi { font-size:1.5rem; display:block; margin-bottom:6px; }
.ann-cover-remove {
    position:absolute; top:8px; right:8px; width:28px; height:28px; border-radius:50%;
    background:rgba(0,0,0,.6); color:#fff; border:none; cursor:pointer; font-size:.8rem;
    display:flex; align-items:center; justify-content:center;
}

.ann-tiptap-wrap { border:1px solid #e9ecef; border-radius:8px; margin-top:12px; min-height:300px; }
.ann-tiptap-wrap .zs-ed-toolbar { background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:6px 8px; display:flex; flex-wrap:wrap; gap:2px; position:sticky; top:56px; z-index:20; border-radius:8px 8px 0 0; }
.ann-tiptap-wrap .zs-ed-btn { background:none; border:1px solid transparent; border-radius:4px; padding:4px 7px; cursor:pointer; color:#495057; font-size:.85rem; }
.ann-tiptap-wrap .zs-ed-btn:hover { background:#e9ecef; }
.ann-tiptap-wrap .zs-ed-btn.active { background:var(--care-primary, #2d4a43); color:#fff; }
.ann-tiptap-wrap .zs-ed-sep { width:1px; background:#dee2e6; margin:0 4px; align-self:stretch; }
.ann-tiptap-wrap .zs-ed-content { padding:16px; min-height:250px; }
.ann-tiptap-wrap .zs-ed-content .tiptap { outline:none; min-height:200px; }
.ann-tiptap-wrap .zs-ed-content .tiptap p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:#adb5bd; pointer-events:none; float:left; height:0; }

.ann-save-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #e9ecef; padding:12px 20px; margin:-20px; margin-top:16px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 10px 10px; z-index:10; }
.ann-autosave { font-size:.75rem; color:#adb5bd; }

/* ── Image picker modal ────────────────────────────── */
.cm-tab-btn { border-radius:10px 10px 0 0 !important; font-size:.85rem; font-weight:600; padding:8px 16px; }
.cm-tab-btn.active { background:var(--cl-surface, #fff) !important; border-color:#e5e7eb #e5e7eb #fff !important; }
.cm-upload-zone { border:2px dashed #e5e7eb; border-radius:14px; min-height:200px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .2s; position:relative; overflow:hidden; }
.cm-upload-zone:hover { border-color:#bcd2cb; background:rgba(188,210,203,.05); }
.cm-upload-zone.dragover { border-color:#2d4a43; background:rgba(188,210,203,.12); }
.cm-upload-placeholder { text-align:center; color:#999; pointer-events:none; }
.cm-upload-placeholder i { font-size:2.5rem; color:#ccc; display:block; margin-bottom:8px; }
.cm-upload-placeholder p { font-size:.9rem; font-weight:600; margin:0 0 4px; color:#666; }
.cm-upload-placeholder span { font-size:.78rem; }
.cm-upload-preview-wrap { position:absolute; inset:0; }
.cm-upload-preview-wrap img { width:100%; height:100%; object-fit:cover; }
.cm-pixabay-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:8px; max-height:360px; overflow-y:auto; }
.cm-pixabay-item { aspect-ratio:16/10; border-radius:10px; overflow:hidden; cursor:pointer; position:relative; transition:transform .2s, box-shadow .2s; }
.cm-pixabay-item:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
.cm-pixabay-item img { width:100%; height:100%; object-fit:cover; }
.cm-pixabay-item-overlay { position:absolute; bottom:0; left:0; right:0; padding:4px 8px; background:linear-gradient(transparent, rgba(0,0,0,.6)); font-size:.65rem; color:#fff; }
.cm-pixabay-empty { grid-column:1 / -1; text-align:center; padding:40px; color:#999; }
.cm-pixabay-empty i { font-size:2rem; display:block; margin-bottom:8px; opacity:.4; }
.cm-pixabay-empty p { margin:0; font-size:.85rem; }
@media(max-width:576px) { .cm-pixabay-grid { grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); } }
</style>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <button class="btn btn-light btn-sm" id="btnBackAnn"><i class="bi bi-arrow-left"></i> Retour</button>
    <h5 class="mb-0"><?= $isNew ? 'Nouvelle annonce' : 'Modifier l\'annonce' ?></h5>
  </div>

  <div class="ann-editor-wrap">
    <div class="ann-editor-title mb-2">
      <input type="text" id="annTitle" placeholder="Titre de l'annonce" value="<?= h($annonceData['titre'] ?? '') ?>" maxlength="255">
    </div>
    <input type="text" class="ann-desc-input mb-2" id="annDescription" placeholder="Description courte (optionnel)" value="<?= h($annonceData['description'] ?? '') ?>" maxlength="500">
    <div class="d-flex gap-3 align-items-center mb-2">
      <div class="zs-select" id="annCategory" data-placeholder="— Catégorie —" style="min-width:180px"></div>
    </div>

    <!-- Cover image zone (click opens modal) -->
    <div class="ann-cover-zone" id="annCoverZone">
      <i class="bi bi-image"></i>
      <span>Image de couverture (optionnel) — cliquez pour choisir</span>
    </div>

    <!-- TipTap Editor -->
    <div class="ann-tiptap-wrap" id="annEditorWrap">
      <div class="text-center py-5 text-muted">
        <div class="spinner-border spinner-border-sm"></div> Chargement de l'éditeur...
      </div>
    </div>

    <!-- Read receipt config -->
    <div class="ann-ack-bar">
      <label class="ann-ack-toggle">
        <input type="checkbox" id="annRequiresAck" <?= !empty($annonceData['requires_ack']) ? 'checked' : '' ?>>
        <span><i class="bi bi-shield-check"></i> Exiger un accusé de lecture</span>
      </label>
      <div class="ann-ack-target" id="annAckTargetWrap" style="<?= empty($annonceData['requires_ack']) ? 'display:none' : '' ?>">
        <label class="form-label small text-muted mb-1">Cible (vide = tous les collaborateurs actifs)</label>
        <select class="form-select form-select-sm" id="annAckTargetRole" style="max-width:260px">
          <option value="">— Tous —</option>
          <?php $tr = $annonceData['ack_target_role'] ?? ''; ?>
          <option value="collaborateur" <?= $tr === 'collaborateur' ? 'selected' : '' ?>>Collaborateurs uniquement</option>
          <option value="responsable" <?= $tr === 'responsable' ? 'selected' : '' ?>>Responsables uniquement</option>
          <option value="direction" <?= $tr === 'direction' ? 'selected' : '' ?>>Direction</option>
          <option value="admin" <?= $tr === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <small class="text-muted" style="font-size:.72rem;display:block;margin-top:6px">Idéal pour protocoles critiques : chaque destinataire devra confirmer la lecture.</small>
    </div>

    <?php if (!$isNew && !empty($annonceData['requires_ack'])): ?>
    <div id="annAckStats" style="margin-top:10px"></div>
    <?php endif; ?>

    <div class="ann-save-bar">
      <span class="ann-autosave" id="annAutoSave"></span>
      <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" id="btnCancelAnn">Annuler</button>
        <button class="btn btn-primary btn-sm" id="btnSaveAnn">
          <i class="bi bi-check-lg"></i> <?= $isNew ? 'Publier' : 'Enregistrer' ?>
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.ann-ack-bar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px; margin-top:14px; }
.ann-ack-toggle { display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.88rem; color:#212529; }
.ann-ack-toggle input { width:16px; height:16px; }
.ann-ack-target { margin-top:8px; }
.ann-ack-stats-card { background:#fff; border:1px solid #e9ecef; border-radius:8px; padding:14px; }
.ann-ack-progress { height:8px; background:#f1f3f5; border-radius:4px; overflow:hidden; margin:8px 0; }
.ann-ack-progress-fill { height:100%; background:#7eb586; transition:width .3s; }
.ann-ack-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:6px; margin-top:8px; }
.ann-ack-user { background:#f8f9fa; border:1px solid #e9ecef; border-radius:6px; padding:5px 9px; font-size:.78rem; display:flex; align-items:center; gap:6px; }
.ann-ack-user.acked { background:#d4edda; border-color:#a7d4b0; color:#155724; }
</style>

<!-- IMAGE PICKER MODAL (Upload + Pixabay) -->
<div class="modal fade" id="annImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title"><i class="bi bi-image me-2"></i>Image de couverture</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow-y:auto">
        <!-- Tabs -->
        <ul class="nav nav-tabs px-3 pt-3" style="border-bottom:none;gap:4px">
          <li class="nav-item"><button class="nav-link active cm-tab-btn" data-cm-tab="upload"><i class="bi bi-cloud-arrow-up me-1"></i>Uploader</button></li>
          <li class="nav-item"><button class="nav-link cm-tab-btn" data-cm-tab="pixabay"><i class="bi bi-search me-1"></i>Pixabay</button></li>
        </ul>

        <!-- Upload tab -->
        <div class="cm-panel p-3" id="cmPanelUpload">
          <div class="cm-upload-zone" id="cmUploadZone">
            <div class="cm-upload-placeholder" id="cmUploadPlaceholder">
              <i class="bi bi-cloud-arrow-up"></i>
              <p>Glissez une image ou cliquez pour charger</p>
              <span>JPG, PNG, WebP — max 5 Mo</span>
            </div>
            <div class="cm-upload-preview-wrap d-none" id="cmUploadPreviewWrap">
              <img src="" alt="" id="cmUploadPreviewImg">
              <button type="button" class="ann-cover-remove" id="cmUploadRemove" style="position:absolute;top:8px;right:8px"><i class="bi bi-x-lg"></i></button>
            </div>
            <input type="file" id="cmUploadInput" accept="image/jpeg,image/png,image/webp" style="display:none">
          </div>
          <button class="btn btn-primary w-100 mt-2" id="cmUploadBtn" disabled><i class="bi bi-check-lg me-1"></i>Utiliser cette image</button>
        </div>

        <!-- Pixabay tab -->
        <div class="cm-panel p-3 d-none" id="cmPanelPixabay">
          <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="cmPixabayInput" placeholder="Rechercher des photos...">
            <select class="form-select" id="cmPixabayCat" style="max-width:140px">
              <option value="">Toutes</option>
              <option value="backgrounds">Fonds</option>
              <option value="nature">Nature</option>
              <option value="business">Business</option>
              <option value="people">Personnes</option>
              <option value="places">Lieux</option>
              <option value="food">Cuisine</option>
              <option value="buildings">Bâtiments</option>
              <option value="travel">Voyage</option>
            </select>
            <button class="btn btn-primary" id="cmPixabaySearchBtn"><i class="bi bi-search"></i></button>
          </div>
          <div class="cm-pixabay-grid" id="cmPixabayGrid" style="max-height:400px;overflow-y:auto">
            <div class="cm-pixabay-empty"><i class="bi bi-images"></i><p>Recherchez des photos libres de droits</p></div>
          </div>
          <div class="text-center mt-2 d-none" id="cmPixabayLoading"><span class="spinner-border spinner-border-sm"></span> Recherche...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const $ = id => document.getElementById(id);
    const $$ = sel => document.querySelectorAll(sel);

    const annonceId = <?= json_encode($annonceId ?: null) ?>;
    const initialContent = <?= json_encode($annonceData['contenu'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const initialCat = <?= json_encode($annonceData['categorie'] ?? 'direction') ?>;
    const initialImage = <?= json_encode($annonceData['image_url'] ?? '') ?>;

    const catOptions = [
        { value: 'direction', label: 'Direction', dot: '#2d4a43', icon: 'bi-building' },
        { value: 'rh', label: 'RH', dot: '#3B4F6B', icon: 'bi-person-badge' },
        { value: 'vie_sociale', label: 'Vie sociale', dot: '#5B4B6B', icon: 'bi-balloon-heart' },
        { value: 'cuisine', label: 'Cuisine', dot: '#198754', icon: 'bi-egg-fried' },
        { value: 'protocoles', label: 'Protocoles', dot: '#dc3545', icon: 'bi-heart-pulse' },
        { value: 'securite', label: 'Sécurité', dot: '#fd7e14', icon: 'bi-shield-check' },
        { value: 'divers', label: 'Divers', dot: '#6c757d', icon: 'bi-info-circle' },
    ];

    let editor = null, getHTMLFn = null, dirty = false, coverUrl = initialImage;
    let imageModal = null, pendingUploadFile = null, pxPage = 1, pxTotal = 0;

    async function initEditor() {
        // Category
        const catEl = $('annCategory');
        zerdaSelect.init(catEl, catOptions, { search: false, dots: true, placeholder: '— Catégorie —' });
        zerdaSelect.setValue(catEl, initialCat);

        // Cover
        if (coverUrl) showCover(coverUrl);
        $('annCoverZone').addEventListener('click', (e) => {
            if (e.target.closest('.ann-cover-remove')) return;
            openImageModal();
        });

        // Image modal
        imageModal = new bootstrap.Modal($('annImageModal'));
        initImageModal();

        // TipTap
        const { createEditor, getHTML, destroyEditor, editorConfirm } = await import('/newspocspace/assets/js/rich-editor.js');
        window.__editorConfirm = editorConfirm;
        getHTMLFn = getHTML;
        editor = await createEditor($('annEditorWrap'), { placeholder: 'Rédigez votre annonce...', content: initialContent, mode: 'full' });
        if (!editor) { $('annEditorWrap').innerHTML = '<div class="text-danger p-3">Erreur éditeur</div>'; return; }

        editor.on('update', () => { dirty = true; $('annAutoSave').textContent = 'Modifications non sauvegardées'; });
        $('btnSaveAnn').addEventListener('click', save);
        $('btnCancelAnn').addEventListener('click', goBack);
        $('btnBackAnn').addEventListener('click', goBack);

        // Toggle ack target visibility
        $('annRequiresAck')?.addEventListener('change', e => {
            $('annAckTargetWrap').style.display = e.target.checked ? '' : 'none';
            dirty = true;
        });
        $('annAckTargetRole')?.addEventListener('change', () => { dirty = true; });

        // Load ack stats if applicable
        loadAckStats();

        window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
        window.__annEditorCleanup = () => { if (editor) { destroyEditor(editor); editor = null; } };
    }

    async function loadAckStats() {
        if (!annonceId || !$('annRequiresAck')?.checked) return;
        const wrap = $('annAckStats');
        if (!wrap) return;
        const r = await adminApiPost('admin_get_annonce_acks', { id: annonceId });
        if (!r.success) return;
        const pct = r.total_target ? Math.round(100 * r.total_acked / r.total_target) : 0;
        wrap.innerHTML = `<div class="ann-ack-stats-card">
            <div class="d-flex justify-content-between"><strong><i class="bi bi-check2-square"></i> Accusés de lecture</strong><span>${r.total_acked} / ${r.total_target} (${pct}%)</span></div>
            <div class="ann-ack-progress"><div class="ann-ack-progress-fill" style="width:${pct}%"></div></div>
            <div class="ann-ack-list">
                ${r.acked.map(u => `<div class="ann-ack-user acked"><i class="bi bi-check-circle-fill"></i> ${escapeHtml(u.prenom + ' ' + u.nom)}</div>`).join('')}
                ${r.missing.map(u => `<div class="ann-ack-user"><i class="bi bi-circle"></i> ${escapeHtml(u.prenom + ' ' + u.nom)}</div>`).join('')}
            </div>
        </div>`;
    }

    // ══════════════════════════════════════════════════════
    //  IMAGE PICKER MODAL
    // ══════════════════════════════════════════════════════

    function openImageModal() { imageModal.show(); }

    function initImageModal() {
        // Tab switching
        $$('.cm-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                $$('.cm-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                $$('.cm-panel').forEach(p => p.classList.add('d-none'));
                $('cmPanel' + btn.dataset.cmTab.charAt(0).toUpperCase() + btn.dataset.cmTab.slice(1))?.classList.remove('d-none');
            });
        });

        // ── Upload tab ────────────────────────────────
        const uploadZone = $('cmUploadZone');
        const uploadInput = $('cmUploadInput');

        uploadZone.addEventListener('click', (e) => { if (!e.target.closest('#cmUploadRemove')) uploadInput.click(); });
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', (e) => { e.preventDefault(); uploadZone.classList.remove('dragover'); if (e.dataTransfer.files[0]) previewUpload(e.dataTransfer.files[0]); });
        uploadInput.addEventListener('change', () => { if (uploadInput.files[0]) previewUpload(uploadInput.files[0]); uploadInput.value = ''; });

        $('cmUploadRemove')?.addEventListener('click', (e) => {
            e.stopPropagation();
            pendingUploadFile = null;
            $('cmUploadPreviewWrap').classList.add('d-none');
            $('cmUploadPlaceholder').style.display = '';
            $('cmUploadBtn').disabled = true;
        });

        $('cmUploadBtn').addEventListener('click', async () => {
            if (!pendingUploadFile) return;
            $('cmUploadBtn').disabled = true;
            $('cmUploadBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const formData = new FormData();
            formData.append('file', pendingUploadFile);
            formData.append('action', 'admin_upload_annonce_image');

            try {
                const r = await fetch('/newspocspace/admin/api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': window.__SS_CARE__.csrfToken },
                    body: formData
                });
                const res = await r.json();
                if (res.csrf) window.__SS_CARE__.csrfToken = res.csrf;
                if (res.success) { setCover(res.url); }
                else showToast(res.message || 'Erreur', 'danger');
            } catch { showToast('Erreur upload', 'danger'); }

            $('cmUploadBtn').disabled = false;
            $('cmUploadBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Utiliser cette image';
        });

        // ── Pixabay tab ───────────────────────────────
        $('cmPixabaySearchBtn').addEventListener('click', () => searchPx(false));
        $('cmPixabayInput').addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchPx(false); } });
        // Infinite scroll on grid
        let pxLoading = false;
        $('cmPixabayGrid').addEventListener('scroll', () => {
            const g = $('cmPixabayGrid');
            if (pxLoading) return;
            if (g.scrollTop + g.clientHeight >= g.scrollHeight - 80) {
                const loaded = g.querySelectorAll('.cm-pixabay-item').length;
                if (loaded < pxTotal) {
                    pxLoading = true;
                    searchPx(true).then(() => { pxLoading = false; });
                }
            }
        });
    }

    function previewUpload(file) {
        if (file.size > 5 * 1024 * 1024) { showToast('Max 5 Mo', 'danger'); return; }
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) { showToast('Format non supporté', 'danger'); return; }
        pendingUploadFile = file;
        const reader = new FileReader();
        reader.onload = () => {
            $('cmUploadPreviewImg').src = reader.result;
            $('cmUploadPreviewWrap').classList.remove('d-none');
            $('cmUploadPlaceholder').style.display = 'none';
            $('cmUploadBtn').disabled = false;
        };
        reader.readAsDataURL(file);
    }

    async function searchPx(append) {
        const query = $('cmPixabayInput').value.trim();
        const cat = $('cmPixabayCat').value;
        if (!query && !cat) { showToast('Entrez un terme', 'warning'); return; }

        if (!append) { pxPage = 1; $('cmPixabayGrid').innerHTML = ''; }
        else pxPage++;

        $('cmPixabayLoading').classList.remove('d-none');
        const res = await adminApiPost('admin_search_pixabay', { query: query || cat, category: cat, page: pxPage });
        $('cmPixabayLoading').classList.add('d-none');

        if (!res.success) { showToast(res.message || 'Erreur', 'danger'); return; }
        pxTotal = res.total || 0;

        if (!res.hits?.length && !append) {
            $('cmPixabayGrid').innerHTML = '<div class="cm-pixabay-empty"><i class="bi bi-emoji-frown"></i><p>Aucun résultat</p></div>';
            return;
        }

        const grid = $('cmPixabayGrid');
        res.hits.forEach(hit => {
            const item = document.createElement('div');
            item.className = 'cm-pixabay-item';
            item.innerHTML = `<img src="${hit.webformatURL}" alt="${escapeHtml(hit.tags || '')}" loading="lazy"><div class="cm-pixabay-item-overlay">${escapeHtml(hit.user)}</div>`;
            item.addEventListener('click', () => selectPxImage(hit.largeImageURL));
            grid.appendChild(item);
        });
    }

    async function selectPxImage(url) {
        $('cmPixabayLoading').classList.remove('d-none');
        const res = await adminApiPost('admin_save_pixabay_annonce', { image_url: url });
        $('cmPixabayLoading').classList.add('d-none');
        if (res.success) { setCover(res.url); }
        else showToast(res.message || 'Erreur', 'danger');
    }

    function setCover(url) {
        coverUrl = url;
        showCover(url);
        dirty = true;
        imageModal.hide();
        pendingUploadFile = null;
        // Reset upload preview
        $('cmUploadPreviewWrap').classList.add('d-none');
        $('cmUploadPlaceholder').style.display = '';
        $('cmUploadBtn').disabled = true;
    }

    // ══════════════════════════════════════════════════════
    //  COVER DISPLAY
    // ══════════════════════════════════════════════════════

    function showCover(url) {
        const zone = $('annCoverZone');
        zone.classList.add('has-image');
        zone.innerHTML = `<img src="${escapeHtml(url)}" alt=""><button type="button" class="ann-cover-remove" title="Supprimer"><i class="bi bi-x"></i></button>`;
        zone.querySelector('.ann-cover-remove').addEventListener('click', (e) => {
            e.stopPropagation();
            coverUrl = '';
            resetCoverZone();
            dirty = true;
        });
    }

    function resetCoverZone() {
        const zone = $('annCoverZone');
        zone.classList.remove('has-image');
        zone.innerHTML = '<i class="bi bi-image"></i><span>Image de couverture (optionnel) — cliquez pour choisir</span>';
    }

    // ══════════════════════════════════════════════════════
    //  SAVE
    // ══════════════════════════════════════════════════════

    async function save() {
        const titre = $('annTitle').value.trim();
        if (!titre) { showToast('Titre requis', 'danger'); return; }

        const contenu = getHTMLFn(editor);
        const description = $('annDescription').value.trim();
        const categorie = zerdaSelect.getValue($('annCategory'));

        const btn = $('btnSaveAnn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const requires_ack = $('annRequiresAck').checked ? 1 : 0;
        const ack_target_role = $('annAckTargetRole')?.value || '';

        let res;
        if (annonceId) {
            res = await adminApiPost('admin_update_annonce', { id: annonceId, titre, contenu, description, categorie, image_url: coverUrl, requires_ack, ack_target_role });
        } else {
            res = await adminApiPost('admin_create_annonce', { titre, contenu, description, categorie, image_url: coverUrl, requires_ack, ack_target_role });
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';

        if (res.success) {
            dirty = false;
            showToast(res.message || 'Sauvegardé');
            $('annAutoSave').textContent = 'Sauvegardé';
            if (!annonceId && res.id) { dirty = false; AdminURL.go('annonce-edit', res.id); }
        } else showToast(res.message || 'Erreur', 'danger');
    }

    async function goBack() {
        if (dirty && window.__editorConfirm) {
            const ok = await window.__editorConfirm({ title: 'Quitter sans sauvegarder ?', text: 'Vos modifications seront perdues.', okText: 'Quitter', type: 'warn' });
            if (!ok) return;
        }
        dirty = false;
        if (window.__annEditorCleanup) window.__annEditorCleanup();
        AdminURL.go('annonces');
    }

    initEditor();
})();
</script>
