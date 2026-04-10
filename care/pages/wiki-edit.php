<?php
/**
 * SpocCare — Wiki Editor (TipTap)
 * Création / modification d'une page wiki
 */
$user = $_SESSION['ss_user'];
if (!in_array($user['role'], ['admin', 'direction', 'responsable'])) {
    echo '<div class="alert alert-danger m-3">Accès réservé aux responsables</div>';
    return;
}

// Load page data if editing
$pageId = $_GET['id'] ?? '';
$pageData = null;
if ($pageId) {
    $pageData = Db::fetch(
        "SELECT p.*, c.nom AS categorie_nom
         FROM wiki_pages p
         LEFT JOIN wiki_categories c ON c.id = p.categorie_id
         WHERE p.id = ?",
        [$pageId]
    );
}

$categories = Db::fetchAll("SELECT id, nom, icone, couleur FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
$tags = Db::fetchAll("SELECT id, nom, slug, couleur FROM wiki_tags ORDER BY nom");
$experts = Db::fetchAll("SELECT id, prenom, nom FROM users WHERE is_active = 1 AND role IN ('admin','direction','responsable') ORDER BY nom, prenom");
$pageTags = [];
$pagePerms = [];
if ($pageData) {
    $pageTags = array_column(Db::fetchAll("SELECT tag_id FROM wiki_page_tags WHERE page_id = ?", [$pageId]), 'tag_id');
    $pagePerms = array_column(Db::fetchAll("SELECT role FROM wiki_page_permissions WHERE page_id = ?", [$pageId]), 'role');
}
$isNew = !$pageData;
?>
<style>
.wiki-editor-wrap { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:20px; }
.wiki-editor-header { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.wiki-editor-title { flex:1; min-width:200px; }
.wiki-editor-title input { font-size:1.2rem; font-weight:700; border:none; border-bottom:2px solid #e9ecef; border-radius:0; padding:6px 2px; width:100%; transition:border-color .2s; }
.wiki-editor-title input:focus { outline:none; border-color:var(--care-primary, #2d4a43); box-shadow:none; }
.wiki-desc-input { font-size:.85rem; border:none; border-bottom:1px solid #e9ecef; border-radius:0; padding:4px 2px; width:100%; color:#6c757d; }
.wiki-desc-input:focus { outline:none; border-color:var(--care-primary, #2d4a43); box-shadow:none; }

.wiki-tiptap-wrap { border:1px solid #e9ecef; border-radius:8px; margin-top:12px; min-height:400px; }
.wiki-tiptap-wrap .zs-ed-toolbar { background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:6px 8px; display:flex; flex-wrap:wrap; gap:2px; position:sticky; top:56px; z-index:20; border-radius:8px 8px 0 0; }
.wiki-tiptap-wrap .zs-ed-btn { background:none; border:1px solid transparent; border-radius:4px; padding:4px 7px; cursor:pointer; color:#495057; font-size:.85rem; }
.wiki-tiptap-wrap .zs-ed-btn:hover { background:#e9ecef; }
.wiki-tiptap-wrap .zs-ed-btn.active { background:var(--care-primary, #2d4a43); color:#fff; }
.wiki-tiptap-wrap .zs-ed-sep { width:1px; background:#dee2e6; margin:0 4px; align-self:stretch; }
.wiki-tiptap-wrap .zs-ed-content { padding:16px; min-height:350px; }
.wiki-tiptap-wrap .zs-ed-content .tiptap { outline:none; min-height:300px; }
.wiki-tiptap-wrap .zs-ed-content .tiptap p.is-editor-empty:first-child::before { content:attr(data-placeholder); color:#adb5bd; pointer-events:none; float:left; height:0; }

.wiki-edit-tag { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:14px; font-size:.78rem; cursor:pointer; transition:all .15s; border:1.5px solid #dee2e6; background:#fff; user-select:none; }
.wiki-edit-tag input { display:none; }
.wiki-edit-tag:has(input:checked) { background:var(--tag-color, #6c757d); color:#fff; border-color:var(--tag-color, #6c757d); }
.wiki-edit-tag:hover { border-color:var(--tag-color, #6c757d); }

.wiki-cover-zone {
    border:2px dashed #dee2e6; border-radius:10px; padding:20px; text-align:center;
    color:#adb5bd; cursor:pointer; transition:all .2s; position:relative; overflow:hidden;
    min-height:100px; display:flex; align-items:center; justify-content:center; flex-direction:column; margin:12px 0;
}
.wiki-cover-zone:hover { border-color:var(--care-primary, #2d4a43); color:#6c757d; background:#f8faf9; }
.wiki-cover-zone.has-image { border:none; padding:0; }
.wiki-cover-zone img { width:100%; max-height:220px; object-fit:cover; border-radius:10px; }
.wiki-cover-zone .bi { font-size:1.3rem; display:block; margin-bottom:4px; }
.wiki-cover-remove { position:absolute; top:8px; right:8px; width:28px; height:28px; border-radius:50%; background:rgba(0,0,0,.6); color:#fff; border:none; cursor:pointer; font-size:.8rem; display:flex; align-items:center; justify-content:center; }

/* Image picker modal */
.cm-tab-btn { border-radius:10px 10px 0 0 !important; font-size:.85rem; font-weight:600; padding:8px 16px; }
.cm-tab-btn.active { background:var(--cl-surface, #fff) !important; border-color:#e5e7eb #e5e7eb #fff !important; }
.cm-upload-zone { border:2px dashed #e5e7eb; border-radius:14px; min-height:180px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .2s; position:relative; overflow:hidden; }
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

.wiki-save-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #e9ecef; padding:12px 20px; margin:-20px; margin-top:16px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 10px 10px; z-index:10; }
.wiki-autosave { font-size:.75rem; color:#adb5bd; }

.wiki-import-zone { border:2px dashed #dee2e6; border-radius:8px; padding:20px; text-align:center; color:#adb5bd; margin-top:12px; transition:all .2s; cursor:pointer; }
.wiki-import-zone:hover { border-color:var(--care-primary, #2d4a43); color:#6c757d; background:#f8faf9; }
.wiki-import-zone.dragging { border-color:var(--care-primary, #2d4a43); background:#eef3f1; }
.wiki-import-zone .bi { font-size:1.5rem; display:block; margin-bottom:6px; }
</style>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <button class="btn btn-light btn-sm" id="btnBackWiki">
      <i class="bi bi-arrow-left"></i> Retour
    </button>
    <h5 class="mb-0"><?= $isNew ? 'Nouvelle page' : 'Modifier la page' ?></h5>
  </div>

  <div class="wiki-editor-wrap">
    <!-- Title + Category -->
    <div class="wiki-editor-header">
      <div class="wiki-editor-title">
        <input type="text" id="wikiTitle" placeholder="Titre de la page" value="<?= h($pageData['titre'] ?? '') ?>" maxlength="255">
      </div>
      <div class="zs-select" id="wikiCategory" data-placeholder="— Catégorie —" style="min-width:200px"></div>
    </div>

    <!-- Description -->
    <input type="text" class="wiki-desc-input" id="wikiDescription" placeholder="Description courte (optionnel)" value="<?= h($pageData['description'] ?? '') ?>" maxlength="500">

    <!-- Tags -->
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin:10px 0" id="wikiTagsWrap">
      <?php foreach ($tags as $tag): ?>
      <label class="wiki-edit-tag" style="--tag-color:<?= h($tag['couleur']) ?>">
        <input type="checkbox" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $pageTags) ? 'checked' : '' ?>>
        <span><?= h($tag['nom']) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Expert + Vérification -->
    <div class="d-flex gap-3 align-items-center mb-2 flex-wrap">
      <div class="zs-select" id="wikiExpert" data-placeholder="— Expert —" style="min-width:200px"></div>
      <div class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small text-muted" style="white-space:nowrap">Revérifier tous les</label>
        <input type="number" class="form-control form-control-sm" id="wikiVerifyDays" value="<?= (int)($pageData['verify_interval_days'] ?? 90) ?>" min="7" max="365" style="width:70px">
        <span class="small text-muted">jours</span>
      </div>
      <?php if (!$isNew && $pageData['expert_id']): ?>
      <button class="btn btn-sm btn-outline-success" id="btnVerifyPage"><i class="bi bi-patch-check"></i> Vérifier maintenant</button>
      <?php endif; ?>
    </div>

    <!-- Permissions par rôle -->
    <div style="margin:10px 0">
      <label class="form-label small text-muted mb-1"><i class="bi bi-shield-lock"></i> Visibilité (vide = tous)</label>
      <div style="display:flex;flex-wrap:wrap;gap:6px" id="wikiPermsWrap">
        <?php
        $roles = ['collaborateur' => 'Collaborateurs', 'responsable' => 'Responsables', 'direction' => 'Direction', 'admin' => 'Admin'];
        foreach ($roles as $rk => $rl): ?>
        <label class="wiki-edit-tag" style="--tag-color:#3B4F6B">
          <input type="checkbox" value="<?= $rk ?>" <?= in_array($rk, $pagePerms) ? 'checked' : '' ?>>
          <span><?= $rl ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <small class="text-muted" style="font-size:.72rem">Si aucun rôle coché, la page est visible par tout le monde</small>
    </div>

    <!-- Cover image -->
    <div class="wiki-cover-zone" id="wikiCoverZone">
      <i class="bi bi-image"></i>
      <span>Image de couverture (optionnel) — cliquez pour choisir</span>
    </div>

    <!-- TipTap Editor -->
    <input type="file" id="wikiImportFile" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display:none">
    <div class="d-flex justify-content-end mb-1">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="wikiImportBtn" title="Importer depuis Word ou PDF"><i class="bi bi-file-earmark-arrow-up me-1"></i>Importer Word / PDF</button>
    </div>
    <div class="wiki-tiptap-wrap" id="wikiEditorWrap">
      <div class="text-center py-5 text-muted">
        <div class="spinner-border spinner-border-sm"></div> Chargement de l'éditeur...
      </div>
    </div>

    <!-- Workflow status -->
    <?php $currentStatus = $pageData['status'] ?? 'brouillon'; ?>
    <div class="wiki-workflow-bar">
      <label class="form-label small text-muted mb-1" style="display:block"><i class="bi bi-flag"></i> Statut de publication</label>
      <div class="wiki-status-tabs">
        <label class="wiki-status-tab"><input type="radio" name="wikiStatus" value="brouillon" <?= $currentStatus === 'brouillon' ? 'checked' : '' ?>><span><i class="bi bi-pencil"></i> Brouillon</span></label>
        <label class="wiki-status-tab"><input type="radio" name="wikiStatus" value="review" <?= $currentStatus === 'review' ? 'checked' : '' ?>><span><i class="bi bi-eye"></i> En review</span></label>
        <label class="wiki-status-tab"><input type="radio" name="wikiStatus" value="publie" <?= $currentStatus === 'publie' ? 'checked' : '' ?>><span><i class="bi bi-check-circle"></i> Publié</span></label>
      </div>
      <small class="text-muted" style="font-size:.72rem">Brouillon : seul vous voyez. Review : visible aux responsables. Publié : visible par tous selon les permissions.</small>
    </div>

    <?php if (!$isNew && $currentStatus === 'review'): ?>
    <div class="wiki-review-actions">
      <i class="bi bi-eye-fill"></i> En attente de review
      <div class="d-flex gap-2 mt-2">
        <button class="btn btn-success btn-sm" id="btnReviewApprove"><i class="bi bi-check2"></i> Approuver et publier</button>
        <button class="btn btn-warning btn-sm" id="btnReviewChanges"><i class="bi bi-arrow-counterclockwise"></i> Demander modifs</button>
        <button class="btn btn-light btn-sm" id="btnReviewComment"><i class="bi bi-chat"></i> Commenter</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$isNew): ?>
    <div id="wikiReviewsList" style="margin-top:10px"></div>
    <?php endif; ?>

    <!-- Save bar -->
    <div class="wiki-save-bar">
      <span class="wiki-autosave" id="wikiAutoSave"></span>
      <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" id="btnCancelWiki">Annuler</button>
        <button class="btn btn-primary btn-sm" id="btnSaveWiki">
          <i class="bi bi-check-lg"></i> <?= $isNew ? 'Créer la page' : 'Enregistrer' ?>
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.wiki-workflow-bar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px; margin-top:14px; }
.wiki-status-tabs { display:flex; gap:6px; margin-bottom:6px; }
.wiki-status-tab { flex:1; cursor:pointer; }
.wiki-status-tab input { display:none; }
.wiki-status-tab span { display:flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; background:#fff; border:1.5px solid #dee2e6; border-radius:6px; font-size:.82rem; color:#6c757d; transition:all .15s; }
.wiki-status-tab:hover span { border-color:var(--care-primary, #2d4a43); }
.wiki-status-tab input:checked + span { background:var(--care-primary, #2d4a43); color:#fff; border-color:var(--care-primary, #2d4a43); font-weight:600; }
.wiki-review-actions { background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:12px; margin-top:10px; font-size:.85rem; color:#7a5c00; }
.wiki-review-item { background:#fff; border:1px solid #e9ecef; border-radius:8px; padding:10px 14px; margin-bottom:6px; font-size:.82rem; }
.wiki-review-item .wri-decision { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:600; margin-right:6px; }
.wiki-review-item .wri-approved { background:#d4edda; color:#155724; }
.wiki-review-item .wri-changes { background:#fff3cd; color:#856404; }
.wiki-review-item .wri-commented { background:#e9ecef; color:#495057; }
</style>

<!-- IMAGE PICKER MODAL (Upload + Pixabay) -->
<div class="modal fade" id="wikiImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title"><i class="bi bi-image me-2"></i>Image de couverture</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body p-0" style="flex:1;overflow-y:auto">
        <ul class="nav nav-tabs px-3 pt-3" style="border-bottom:none;gap:4px">
          <li class="nav-item"><button class="nav-link active cm-tab-btn" data-cm-tab="upload"><i class="bi bi-cloud-arrow-up me-1"></i>Uploader</button></li>
          <li class="nav-item"><button class="nav-link cm-tab-btn" data-cm-tab="pixabay"><i class="bi bi-search me-1"></i>Pixabay</button></li>
        </ul>
        <div class="cm-panel p-3" id="cmPanelUpload">
          <div class="cm-upload-zone" id="cmUploadZone">
            <div class="cm-upload-placeholder" id="cmUploadPlaceholder">
              <i class="bi bi-cloud-arrow-up"></i>
              <p>Glissez une image ou cliquez pour charger</p>
              <span>JPG, PNG, WebP — max 5 Mo</span>
            </div>
            <div class="cm-upload-preview-wrap d-none" id="cmUploadPreviewWrap">
              <img src="" alt="" id="cmUploadPreviewImg">
              <button type="button" class="wiki-cover-remove" id="cmUploadRemove"><i class="bi bi-x-lg"></i></button>
            </div>
            <input type="file" id="cmUploadInput" accept="image/jpeg,image/png,image/webp" style="display:none">
          </div>
          <button class="btn btn-primary w-100 mt-2" id="cmUploadBtn" disabled><i class="bi bi-check-lg me-1"></i>Utiliser cette image</button>
        </div>
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
              <option value="food">Cuisine</option>
              <option value="buildings">Bâtiments</option>
              <option value="science">Science</option>
            </select>
            <button class="btn btn-primary" id="cmPixabaySearchBtn"><i class="bi bi-search"></i></button>
          </div>
          <div class="cm-pixabay-grid" id="cmPixabayGrid">
            <div class="cm-pixabay-empty"><i class="bi bi-images"></i><p>Recherchez des photos libres de droits</p></div>
          </div>
          <div class="text-center mt-2 d-none" id="cmPixabayLoading"><span class="spinner-border spinner-border-sm"></span> Recherche...</div>
          <button class="btn btn-outline-secondary w-100 mt-2 d-none" id="cmPixabayMore"><i class="bi bi-plus-circle me-1"></i>Voir plus</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const $ = id => document.getElementById(id);
    const $$ = sel => document.querySelectorAll(sel);

    const pageId = <?= json_encode($pageId ?: null) ?>;
    const initialContent = <?= json_encode($pageData['contenu'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const initialImage = <?= json_encode($pageData['image_url'] ?? '') ?>;
    const catOptions = <?= json_encode(array_map(function($c) use ($pageData) {
        return ['value' => $c['id'], 'label' => $c['nom'], 'icon' => 'bi-' . ($c['icone'] ?: 'book'), 'dot' => $c['couleur'] ?: '#6c757d', 'selected' => ($pageData['categorie_id'] ?? '') === $c['id']];
    }, $categories), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const expertOptions = <?= json_encode(array_map(function($u) use ($pageData) {
        return ['value' => $u['id'], 'label' => $u['prenom'] . ' ' . $u['nom'], 'selected' => ($pageData['expert_id'] ?? '') === $u['id']];
    }, $experts), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    let editor = null;
    let getHTMLFn = null;
    let dirty = false;
    let coverUrl = initialImage;
    let imageModal = null;
    let pendingUploadFile = null;
    let pxPage = 1, pxTotal = 0;

    async function initEditor() {
        // Cover image
        if (coverUrl) showCover(coverUrl);
        $('wikiCoverZone').addEventListener('click', (e) => {
            if (e.target.closest('.wiki-cover-remove')) return;
            imageModal.show();
        });
        imageModal = new bootstrap.Modal($('wikiImageModal'));
        initImageModal();

        // Init zerdaSelect for category
        const catEl = document.getElementById('wikiCategory');
        const opts = catOptions.map(c => ({ value: c.value, label: c.label, icon: c.icon, dot: c.dot }));
        zerdaSelect.init(catEl, opts, { search: false, dots: true, placeholder: '— Catégorie —' });
        const selected = catOptions.find(c => c.selected);
        if (selected) zerdaSelect.setValue(catEl, selected.value);

        // Init zerdaSelect for expert
        const expEl = document.getElementById('wikiExpert');
        const expOpts = expertOptions.map(e => ({ value: e.value, label: e.label }));
        zerdaSelect.init(expEl, expOpts, { search: true, placeholder: '— Expert référent —' });
        const selExp = expertOptions.find(e => e.selected);
        if (selExp) zerdaSelect.setValue(expEl, selExp.value);

        // Verify button
        document.getElementById('btnVerifyPage')?.addEventListener('click', async () => {
            const r = await adminApiPost('admin_verify_wiki_page', { page_id: pageId });
            if (r.success) showToast(r.message);
        });

        // ── Workflow review actions ────────────────────────
        async function doReview(decision) {
            const comment = decision === 'commented' || decision === 'changes_requested'
                ? prompt(decision === 'changes_requested' ? 'Modifications demandées :' : 'Commentaire :') || ''
                : '';
            const r = await adminApiPost('admin_review_wiki_page', { id: pageId, decision, comment });
            if (r.success) { showToast(r.message); loadReviews(); if (decision === 'approved') location.reload(); }
        }
        document.getElementById('btnReviewApprove')?.addEventListener('click', () => doReview('approved'));
        document.getElementById('btnReviewChanges')?.addEventListener('click', () => doReview('changes_requested'));
        document.getElementById('btnReviewComment')?.addEventListener('click', () => doReview('commented'));

        async function loadReviews() {
            if (!pageId) return;
            const list = document.getElementById('wikiReviewsList');
            if (!list) return;
            const r = await adminApiPost('admin_get_wiki_reviews', { page_id: pageId });
            if (!r.success || !r.reviews.length) { list.innerHTML = ''; return; }
            const labels = { approved: 'Approuvé', changes_requested: 'Modifs demandées', commented: 'Commentaire' };
            const cls = { approved: 'wri-approved', changes_requested: 'wri-changes', commented: 'wri-commented' };
            list.innerHTML = '<div class="form-label small text-muted mb-1"><i class="bi bi-chat-square-text"></i> Historique des reviews</div>' + r.reviews.map(rv => {
                const d = new Date(rv.created_at).toLocaleString('fr-CH', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                return `<div class="wiki-review-item">
                    <span class="wri-decision ${cls[rv.decision]}">${labels[rv.decision] || rv.decision}</span>
                    <strong>${escapeHtml((rv.prenom || '') + ' ' + (rv.nom || ''))}</strong>
                    <span class="text-muted" style="font-size:.72rem">${d}</span>
                    ${rv.comment ? `<div style="margin-top:4px;color:#495057">${escapeHtml(rv.comment)}</div>` : ''}
                </div>`;
            }).join('');
        }
        loadReviews();

        // Dynamic import of rich-editor module
        const { createEditor, getHTML, destroyEditor, editorConfirm } = await import('/spocspace/assets/js/rich-editor.js');
        getHTMLFn = getHTML;
        window.__editorConfirm = editorConfirm;

        const wrap = document.getElementById('wikiEditorWrap');
        editor = await createEditor(wrap, {
            placeholder: 'Commencez à écrire votre page wiki...',
            content: initialContent,
            mode: 'full'
        });

        if (!editor) {
            wrap.innerHTML = '<div class="text-danger p-3">Erreur de chargement de l\'éditeur</div>';
            return;
        }

        // Track changes
        editor.on('update', () => {
            dirty = true;
            document.getElementById('wikiAutoSave').textContent = 'Modifications non sauvegardées';
        });

        // Save handlers
        document.getElementById('btnSaveWiki').addEventListener('click', save);
        document.getElementById('btnCancelWiki').addEventListener('click', goBack);
        document.getElementById('btnBackWiki').addEventListener('click', goBack);

        // Import handlers
        initImport();

        // Warn on navigation if dirty
        window.addEventListener('beforeunload', (e) => {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });

        window.__wikiEditorCleanup = () => {
            if (editor) { destroyEditor(editor); editor = null; }
        };
    }

    // ── Import Word / PDF ─────────────────────────────────
    function initImport() {
        const fileInput = document.getElementById('wikiImportFile');
        const importBtn = document.getElementById('wikiImportBtn');

        importBtn.addEventListener('click', (e) => { e.preventDefault(); fileInput.click(); });
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) processImportFile(fileInput.files[0]);
            fileInput.value = '';
        });
    }

    async function processImportFile(file) {
        const ext = file.name.split('.').pop().toLowerCase();

        if (!['docx', 'pdf'].includes(ext)) {
            showToast('Format non supporté. Utilisez .docx ou .pdf', 'danger');
            return;
        }

        const btn = $('wikiImportBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';

        try {
            if (ext === 'docx') {
                await importDocx(file);
            } else {
                await importPdf(file);
            }

            const titleInput = $('wikiTitle');
            if (!titleInput.value.trim()) {
                titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ');
            }
            dirty = true;
            $('wikiAutoSave').textContent = 'Contenu importé — non sauvegardé';
            showToast('Contenu importé avec succès');
        } catch (err) {
            console.error('Import error:', err);
            showToast('Erreur import: ' + (err.message || 'Fichier invalide'), 'danger');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-arrow-up me-1"></i>Importer Word / PDF';
    }

    async function importDocx(file) {
        // Use mammoth.js for DOCX → HTML conversion (CDN)
        if (!window.mammoth) {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.8.0/mammoth.browser.min.js');
        }
        const arrayBuffer = await file.arrayBuffer();
        const result = await mammoth.convertToHtml({ arrayBuffer });
        if (result.value) {
            editor.commands.setContent(result.value);
        } else {
            throw new Error('Aucun contenu extrait');
        }
    }

    async function importPdf(file) {
        // Use pdf.js for PDF → text extraction (CDN)
        if (!window.pdfjsLib) {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs', 'module');
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';
        }
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        let html = '';

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const textContent = await page.getTextContent();
            let lastY = null;
            let paragraph = '';

            for (const item of textContent.items) {
                if (lastY !== null && Math.abs(item.transform[5] - lastY) > 2) {
                    // New line
                    if (paragraph.trim()) {
                        // Detect headings (larger font or bold)
                        if (item.height > 14) {
                            html += '<h2>' + escapeHtml(paragraph.trim()) + '</h2>';
                        } else {
                            html += '<p>' + escapeHtml(paragraph.trim()) + '</p>';
                        }
                    }
                    paragraph = '';
                }
                paragraph += item.str;
                lastY = item.transform[5];
            }
            if (paragraph.trim()) {
                html += '<p>' + escapeHtml(paragraph.trim()) + '</p>';
            }
            if (i < pdf.numPages) html += '<hr>';
        }

        if (html) {
            editor.commands.setContent(html);
        } else {
            throw new Error('Aucun texte extrait du PDF');
        }
    }

    function loadScript(src, type) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src;
            if (type) s.type = type;
            s.onload = resolve;
            s.onerror = () => reject(new Error('Failed to load: ' + src));
            document.head.appendChild(s);
        });
    }

    // ── Save ──────────────────────────────────────────────
    // ── Image modal ─────────────────────────────────────
    function initImageModal() {
        $$('.cm-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                $$('.cm-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                $$('.cm-panel').forEach(p => p.classList.add('d-none'));
                const tabId = 'cmPanel' + btn.dataset.cmTab.charAt(0).toUpperCase() + btn.dataset.cmTab.slice(1);
                document.getElementById(tabId)?.classList.remove('d-none');
            });
        });

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
            formData.append('action', 'admin_upload_wiki_image');
            try {
                const r = await fetch('/spocspace/admin/api.php', { method: 'POST', headers: { 'X-CSRF-Token': window.__SS_CARE__.csrfToken }, body: formData });
                const res = await r.json();
                if (res.csrf) window.__SS_CARE__.csrfToken = res.csrf;
                if (res.success) setCover(res.url);
                else showToast(res.message || 'Erreur', 'danger');
            } catch { showToast('Erreur upload', 'danger'); }
            $('cmUploadBtn').disabled = false;
            $('cmUploadBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Utiliser cette image';
        });

        $('cmPixabaySearchBtn').addEventListener('click', () => searchPx(false));
        $('cmPixabayInput').addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchPx(false); } });
        $('cmPixabayMore').addEventListener('click', () => searchPx(true));
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
        if (!append) { pxPage = 1; $('cmPixabayGrid').innerHTML = ''; } else pxPage++;
        $('cmPixabayLoading').classList.remove('d-none');
        $('cmPixabayMore').classList.add('d-none');
        const res = await adminApiPost('admin_search_pixabay', { query: query || cat, category: cat, page: pxPage });
        $('cmPixabayLoading').classList.add('d-none');
        if (!res.success) { showToast(res.message || 'Erreur', 'danger'); return; }
        pxTotal = res.total || 0;
        if (!res.hits?.length && !append) { $('cmPixabayGrid').innerHTML = '<div class="cm-pixabay-empty"><i class="bi bi-emoji-frown"></i><p>Aucun résultat</p></div>'; return; }
        const grid = $('cmPixabayGrid');
        res.hits.forEach(hit => {
            const item = document.createElement('div');
            item.className = 'cm-pixabay-item';
            item.innerHTML = `<img src="${hit.webformatURL}" alt="${escapeHtml(hit.tags || '')}" loading="lazy"><div class="cm-pixabay-item-overlay">${escapeHtml(hit.user)}</div>`;
            item.addEventListener('click', () => selectPxImage(hit.largeImageURL));
            grid.appendChild(item);
        });
        if (grid.querySelectorAll('.cm-pixabay-item').length < pxTotal) $('cmPixabayMore').classList.remove('d-none');
    }

    async function selectPxImage(url) {
        $('cmPixabayLoading').classList.remove('d-none');
        const res = await adminApiPost('admin_save_pixabay_wiki', { image_url: url });
        $('cmPixabayLoading').classList.add('d-none');
        if (res.success) setCover(res.url);
        else showToast(res.message || 'Erreur', 'danger');
    }

    function setCover(url) {
        coverUrl = url;
        showCover(url);
        dirty = true;
        imageModal.hide();
        pendingUploadFile = null;
        $('cmUploadPreviewWrap').classList.add('d-none');
        $('cmUploadPlaceholder').style.display = '';
        $('cmUploadBtn').disabled = true;
    }

    function showCover(url) {
        const zone = $('wikiCoverZone');
        zone.classList.add('has-image');
        zone.innerHTML = `<img src="${escapeHtml(url)}" alt=""><button type="button" class="wiki-cover-remove" title="Supprimer"><i class="bi bi-x"></i></button>`;
        zone.querySelector('.wiki-cover-remove').addEventListener('click', (e) => {
            e.stopPropagation();
            coverUrl = '';
            resetCoverZone();
            dirty = true;
        });
    }

    function resetCoverZone() {
        const zone = $('wikiCoverZone');
        zone.classList.remove('has-image');
        zone.innerHTML = '<i class="bi bi-image"></i><span>Image de couverture (optionnel) — cliquez pour choisir</span>';
    }

    // ── Save helpers ──────────────────────────────────
    function getSelectedTagIds() {
        return [...document.querySelectorAll('#wikiTagsWrap input:checked')].map(el => el.value);
    }
    function getSelectedRoles() {
        return [...document.querySelectorAll('#wikiPermsWrap input:checked')].map(el => el.value);
    }

    async function save() {
        const titre = document.getElementById('wikiTitle').value.trim();
        if (!titre) { showToast('Titre requis', 'danger'); return; }

        const contenu = getHTMLFn(editor);
        const description = document.getElementById('wikiDescription').value.trim();
        const categorie_id = zerdaSelect.getValue(document.getElementById('wikiCategory'));

        const btn = document.getElementById('btnSaveWiki');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const status = document.querySelector('input[name="wikiStatus"]:checked')?.value || 'brouillon';

        let res;
        if (pageId) {
            res = await adminApiPost('admin_update_wiki_page', { id: pageId, titre, contenu, description, categorie_id, image_url: coverUrl, status });
        } else {
            res = await adminApiPost('admin_create_wiki_page', { titre, contenu, description, categorie_id, image_url: coverUrl });
            // Apply status after creation if not default
            if (res.success && res.id && status !== 'publie') {
                await adminApiPost('admin_update_wiki_page', { id: res.id, status });
            }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';

        if (res.success) {
            const savedId = pageId || res.id;

            // Save tags
            await adminApiPost('admin_set_wiki_page_tags', { page_id: savedId, tag_ids: getSelectedTagIds() });

            // Save expert
            const expertId = zerdaSelect.getValue(document.getElementById('wikiExpert'));
            const intervalDays = document.getElementById('wikiVerifyDays')?.value || 90;
            await adminApiPost('admin_assign_wiki_expert', { page_id: savedId, expert_id: expertId, interval_days: intervalDays });

            // Save permissions
            await adminApiPost('admin_set_wiki_page_permissions', { page_id: savedId, roles: getSelectedRoles() });

            dirty = false;
            showToast(res.message || 'Sauvegardé');
            document.getElementById('wikiAutoSave').textContent = 'Sauvegardé';
            if (!pageId && res.id) {
                dirty = false;
                AdminURL.go('wiki-edit', res.id);
            }
        } else {
            showToast(res.message || 'Erreur', 'danger');
        }
    }

    async function goBack() {
        if (dirty) {
            if (window.__editorConfirm) {
                const ok = await window.__editorConfirm({ title: 'Quitter sans sauvegarder ?', text: 'Vos modifications seront perdues.', okText: 'Quitter', type: 'warn' });
                if (!ok) return;
            }
        }
        dirty = false;
        if (window.__wikiEditorCleanup) window.__wikiEditorCleanup();
        AdminURL.go('wiki');
    }

    // Start
    initEditor();
})();
</script>
