<?php
/**
 * SpocCare — Annonce Editor (TipTap)
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

$catLabels = [
    'direction' => 'Direction',
    'rh' => 'RH',
    'vie_sociale' => 'Vie sociale',
    'cuisine' => 'Cuisine',
    'protocoles' => 'Protocoles',
    'securite' => 'Sécurité',
    'divers' => 'Divers',
];
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

.ann-tiptap-wrap { border:1px solid #e9ecef; border-radius:8px; overflow:hidden; margin-top:12px; min-height:300px; }
.ann-tiptap-wrap .zs-ed-toolbar { background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:6px 8px; display:flex; flex-wrap:wrap; gap:2px; }
.ann-tiptap-wrap .zs-ed-btn { background:none; border:1px solid transparent; border-radius:4px; padding:4px 7px; cursor:pointer; color:#495057; font-size:.85rem; }
.ann-tiptap-wrap .zs-ed-btn:hover { background:#e9ecef; }
.ann-tiptap-wrap .zs-ed-btn.active { background:var(--care-primary, #2d4a43); color:#fff; }
.ann-tiptap-wrap .zs-ed-sep { width:1px; background:#dee2e6; margin:0 4px; align-self:stretch; }
.ann-tiptap-wrap .zs-ed-content { padding:16px; min-height:250px; }
.ann-tiptap-wrap .zs-ed-content .tiptap { outline:none; min-height:200px; }
.ann-tiptap-wrap .zs-ed-content .tiptap p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:#adb5bd; pointer-events:none; float:left; height:0; }

.ann-save-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #e9ecef; padding:12px 20px; margin:-20px; margin-top:16px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 10px 10px; z-index:10; }
.ann-autosave { font-size:.75rem; color:#adb5bd; }
</style>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <button class="btn btn-light btn-sm" id="btnBackAnn"><i class="bi bi-arrow-left"></i> Retour</button>
    <h5 class="mb-0"><?= $isNew ? 'Nouvelle annonce' : 'Modifier l\'annonce' ?></h5>
  </div>

  <div class="ann-editor-wrap">
    <!-- Title -->
    <div class="ann-editor-title mb-2">
      <input type="text" id="annTitle" placeholder="Titre de l'annonce" value="<?= h($annonceData['titre'] ?? '') ?>" maxlength="255">
    </div>

    <!-- Description -->
    <input type="text" class="ann-desc-input mb-2" id="annDescription" placeholder="Description courte (optionnel)" value="<?= h($annonceData['description'] ?? '') ?>" maxlength="500">

    <!-- Category -->
    <div class="d-flex gap-3 align-items-center mb-2">
      <div class="zs-select" id="annCategory" data-placeholder="— Catégorie —" style="min-width:180px"></div>
    </div>

    <!-- Cover image -->
    <div class="ann-cover-zone" id="annCoverZone">
      <i class="bi bi-image"></i>
      <span>Image de couverture (optionnel) — cliquez ou glissez</span>
      <input type="file" id="annCoverInput" accept="image/jpeg,image/png,image/webp" style="display:none">
    </div>

    <!-- TipTap Editor -->
    <div class="ann-tiptap-wrap" id="annEditorWrap">
      <div class="text-center py-5 text-muted">
        <div class="spinner-border spinner-border-sm"></div> Chargement de l'éditeur...
      </div>
    </div>

    <!-- Save bar -->
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

<script<?= nonce() ?>>
(function(){
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

    let editor = null;
    let getHTMLFn = null;
    let dirty = false;
    let coverUrl = initialImage;

    async function initEditor() {
        // Category select
        const catEl = document.getElementById('annCategory');
        zerdaSelect.init(catEl, catOptions, { search: false, dots: true, placeholder: '— Catégorie —' });
        zerdaSelect.setValue(catEl, initialCat);

        // Cover image
        initCover();

        // TipTap
        const { createEditor, getHTML, destroyEditor } = await import('/spocspace/assets/js/rich-editor.js');
        getHTMLFn = getHTML;

        const wrap = document.getElementById('annEditorWrap');
        editor = await createEditor(wrap, {
            placeholder: 'Rédigez votre annonce...',
            content: initialContent,
            mode: 'full'
        });

        if (!editor) { wrap.innerHTML = '<div class="text-danger p-3">Erreur éditeur</div>'; return; }

        editor.on('update', () => { dirty = true; document.getElementById('annAutoSave').textContent = 'Modifications non sauvegardées'; });

        document.getElementById('btnSaveAnn').addEventListener('click', save);
        document.getElementById('btnCancelAnn').addEventListener('click', goBack);
        document.getElementById('btnBackAnn').addEventListener('click', goBack);

        window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
        window.__annEditorCleanup = () => { if (editor) { destroyEditor(editor); editor = null; } };
    }

    function initCover() {
        const zone = document.getElementById('annCoverZone');
        const input = document.getElementById('annCoverInput');

        if (coverUrl) showCover(coverUrl);

        zone.addEventListener('click', (e) => { if (!e.target.closest('.ann-cover-remove')) input.click(); });
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.style.borderColor = '#2d4a43'; });
        zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
        zone.addEventListener('drop', (e) => { e.preventDefault(); zone.style.borderColor = ''; if (e.dataTransfer.files[0]) uploadCover(e.dataTransfer.files[0]); });
        input.addEventListener('change', () => { if (input.files[0]) uploadCover(input.files[0]); input.value = ''; });
    }

    async function uploadCover(file) {
        if (file.size > 5 * 1024 * 1024) { showToast('Image trop volumineuse (max 5 Mo)', 'danger'); return; }

        const zone = document.getElementById('annCoverZone');
        zone.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Upload...';

        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'admin_upload_annonce_image');

        try {
            const r = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_CARE__.csrfToken },
                body: formData
            });
            const res = await r.json();
            if (res.csrf) window.__SS_CARE__.csrfToken = res.csrf;
            if (res.success) { coverUrl = res.url; showCover(res.url); dirty = true; }
            else { showToast(res.message || 'Erreur upload', 'danger'); resetCoverZone(); }
        } catch { showToast('Erreur upload', 'danger'); resetCoverZone(); }
    }

    function showCover(url) {
        const zone = document.getElementById('annCoverZone');
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
        const zone = document.getElementById('annCoverZone');
        zone.classList.remove('has-image');
        zone.innerHTML = '<i class="bi bi-image"></i><span>Image de couverture (optionnel) — cliquez ou glissez</span>';
    }

    async function save() {
        const titre = document.getElementById('annTitle').value.trim();
        if (!titre) { showToast('Titre requis', 'danger'); return; }

        const contenu = getHTMLFn(editor);
        const description = document.getElementById('annDescription').value.trim();
        const categorie = zerdaSelect.getValue(document.getElementById('annCategory'));

        const btn = document.getElementById('btnSaveAnn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        let res;
        if (annonceId) {
            res = await adminApiPost('admin_update_annonce', { id: annonceId, titre, contenu, description, categorie, image_url: coverUrl });
        } else {
            res = await adminApiPost('admin_create_annonce', { titre, contenu, description, categorie, image_url: coverUrl });
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';

        if (res.success) {
            dirty = false;
            showToast(res.message || 'Sauvegardé');
            document.getElementById('annAutoSave').textContent = 'Sauvegardé';
            if (!annonceId && res.id) { dirty = false; AdminURL.go('annonce-edit', res.id); }
        } else { showToast(res.message || 'Erreur', 'danger'); }
    }

    function goBack() {
        if (dirty && !confirm('Modifications non sauvegardées. Quitter quand même ?')) return;
        dirty = false;
        if (window.__annEditorCleanup) window.__annEditorCleanup();
        AdminURL.go('annonces');
    }

    initEditor();
})();
</script>
