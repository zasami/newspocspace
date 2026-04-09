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
if ($pageData) {
    $pageTags = array_column(Db::fetchAll("SELECT tag_id FROM wiki_page_tags WHERE page_id = ?", [$pageId]), 'tag_id');
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

.wiki-tiptap-wrap { border:1px solid #e9ecef; border-radius:8px; overflow:hidden; margin-top:12px; min-height:400px; }
.wiki-tiptap-wrap .zs-ed-toolbar { background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:6px 8px; display:flex; flex-wrap:wrap; gap:2px; }
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

    <!-- Import zone -->
    <div class="wiki-import-zone" id="wikiImportZone">
      <i class="bi bi-file-earmark-arrow-up"></i>
      <span>Glissez un fichier <strong>Word (.docx)</strong> ou <strong>PDF</strong> ici, ou <a href="#" id="wikiImportBtn">cliquez pour importer</a></span>
      <input type="file" id="wikiImportFile" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display:none">
    </div>

    <!-- TipTap Editor -->
    <div class="wiki-tiptap-wrap" id="wikiEditorWrap">
      <div class="text-center py-5 text-muted">
        <div class="spinner-border spinner-border-sm"></div> Chargement de l'éditeur...
      </div>
    </div>

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

<script<?= nonce() ?>>
(function(){
    const pageId = <?= json_encode($pageId ?: null) ?>;
    const initialContent = <?= json_encode($pageData['contenu'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const catOptions = <?= json_encode(array_map(function($c) use ($pageData) {
        return ['value' => $c['id'], 'label' => $c['nom'], 'icon' => 'bi-' . ($c['icone'] ?: 'book'), 'dot' => $c['couleur'] ?: '#6c757d', 'selected' => ($pageData['categorie_id'] ?? '') === $c['id']];
    }, $categories), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const expertOptions = <?= json_encode(array_map(function($u) use ($pageData) {
        return ['value' => $u['id'], 'label' => $u['prenom'] . ' ' . $u['nom'], 'selected' => ($pageData['expert_id'] ?? '') === $u['id']];
    }, $experts), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    let editor = null;
    let getHTMLFn = null;
    let dirty = false;

    async function initEditor() {
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

        // Dynamic import of rich-editor module
        const { createEditor, getHTML, destroyEditor } = await import('/spocspace/assets/js/rich-editor.js');
        getHTMLFn = getHTML;

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

        // Hide import zone once editor has content
        if (initialContent) {
            document.getElementById('wikiImportZone').style.display = 'none';
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
        const zone = document.getElementById('wikiImportZone');
        const fileInput = document.getElementById('wikiImportFile');
        const importBtn = document.getElementById('wikiImportBtn');

        importBtn.addEventListener('click', (e) => { e.preventDefault(); fileInput.click(); });
        zone.addEventListener('click', (e) => { if (e.target === zone || e.target.closest('.wiki-import-zone')) fileInput.click(); });

        // Drag & drop
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragging'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragging'));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragging');
            const file = e.dataTransfer.files[0];
            if (file) processImportFile(file);
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) processImportFile(fileInput.files[0]);
            fileInput.value = '';
        });
    }

    async function processImportFile(file) {
        const zone = document.getElementById('wikiImportZone');
        const ext = file.name.split('.').pop().toLowerCase();

        if (!['docx', 'pdf'].includes(ext)) {
            showToast('Format non supporté. Utilisez .docx ou .pdf', 'danger');
            return;
        }

        zone.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Import en cours...';

        try {
            if (ext === 'docx') {
                await importDocx(file);
            } else {
                await importPdf(file);
            }
            zone.style.display = 'none';

            // Set title from filename if empty
            const titleInput = document.getElementById('wikiTitle');
            if (!titleInput.value.trim()) {
                titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ');
            }
            dirty = true;
            document.getElementById('wikiAutoSave').textContent = 'Contenu importé — non sauvegardé';
            showToast('Contenu importé avec succès');
        } catch (err) {
            console.error('Import error:', err);
            zone.innerHTML = '<i class="bi bi-file-earmark-arrow-up" style="font-size:1.5rem;display:block;margin-bottom:6px"></i><span>Erreur d\'import. <a href="#" id="wikiImportBtn">Réessayer</a></span>';
            document.getElementById('wikiImportBtn')?.addEventListener('click', (e) => { e.preventDefault(); document.getElementById('wikiImportFile').click(); });
            showToast('Erreur lors de l\'import: ' + (err.message || 'Fichier invalide'), 'danger');
        }
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
    function getSelectedTagIds() {
        return [...document.querySelectorAll('#wikiTagsWrap input:checked')].map(el => el.value);
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

        let res;
        if (pageId) {
            res = await adminApiPost('admin_update_wiki_page', { id: pageId, titre, contenu, description, categorie_id });
        } else {
            res = await adminApiPost('admin_create_wiki_page', { titre, contenu, description, categorie_id });
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

    function goBack() {
        if (dirty) {
            if (!confirm('Modifications non sauvegardées. Quitter quand même ?')) return;
        }
        dirty = false;
        if (window.__wikiEditorCleanup) window.__wikiEditorCleanup();
        AdminURL.go('wiki');
    }

    // Start
    initEditor();
})();
</script>
