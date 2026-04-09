<?php
/**
 * SpocCare — Wiki / Base de connaissances
 * Liste des pages + lecture, édition pour responsable+
 */
$user = $_SESSION['ss_user'];
$isResponsable = in_array($user['role'], ['admin', 'direction', 'responsable']);

// SSR: categories + tags + pages
$categories = Db::fetchAll("SELECT * FROM wiki_categories WHERE actif = 1 ORDER BY ordre, nom");
$tags = Db::fetchAll("SELECT id, nom, slug, couleur FROM wiki_tags ORDER BY nom");
$pages = Db::fetchAll(
    "SELECT p.id, p.titre, p.slug, p.description, p.categorie_id, p.version,
            p.visible, p.epingle, p.expert_id, p.verified_at, p.verify_next,
            p.created_at, p.updated_at,
            c.nom AS categorie_nom, c.icone AS categorie_icone, c.couleur AS categorie_couleur,
            cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
            ex.prenom AS expert_prenom, ex.nom AS expert_nom
     FROM wiki_pages p
     LEFT JOIN wiki_categories c ON c.id = p.categorie_id
     LEFT JOIN users cr ON cr.id = p.created_by
     LEFT JOIN users ex ON ex.id = p.expert_id
     WHERE p.archived_at IS NULL AND p.visible = 1
     ORDER BY p.epingle DESC, p.updated_at DESC"
);
// Attach tags + favoris
$pageIds = array_column($pages, 'id');
$tagsByPage = [];
$favSet = [];
if ($pageIds) {
    $ph = implode(',', array_fill(0, count($pageIds), '?'));
    $allTags = Db::fetchAll("SELECT wpt.page_id, t.id, t.nom, t.slug, t.couleur FROM wiki_page_tags wpt JOIN wiki_tags t ON t.id = wpt.tag_id WHERE wpt.page_id IN ($ph)", $pageIds);
    foreach ($allTags as $t) $tagsByPage[$t['page_id']][] = $t;
    $favRows = Db::fetchAll("SELECT page_id FROM wiki_favoris WHERE user_id = ? AND page_id IN ($ph)", array_merge([$user['id']], $pageIds));
    foreach ($favRows as $f) $favSet[$f['page_id']] = true;
}
foreach ($pages as &$p) {
    $p['tags'] = $tagsByPage[$p['id']] ?? [];
    $p['is_favori'] = isset($favSet[$p['id']]);
}
unset($p);
// Count expired verifications
$expiredCount = (int)Db::getOne("SELECT COUNT(*) FROM wiki_pages WHERE archived_at IS NULL AND verify_next IS NOT NULL AND verify_next <= NOW()");
?>
<style>
.wiki-cat-filters { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
.wiki-cat-btn { border:1px solid #dee2e6; background:#fff; border-radius:20px; padding:4px 14px; font-size:.8rem; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:5px; }
.wiki-cat-btn:hover, .wiki-cat-btn.active { color:#fff; border-color:transparent; }
.wiki-cat-btn .bi { font-size:.85rem; }

.wiki-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:14px; }
.wiki-card { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:16px; cursor:pointer; transition:all .2s; position:relative; }
.wiki-card:hover { border-color:var(--care-primary, #2d4a43); box-shadow:0 2px 8px rgba(0,0,0,.08); transform:translateY(-1px); }
.wiki-card.pinned { border-left:3px solid var(--care-primary, #2d4a43); }
.wiki-card-title { font-weight:600; font-size:.95rem; margin-bottom:4px; color:#212529; }
.wiki-card-desc { font-size:.8rem; color:#6c757d; margin-bottom:8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.wiki-card-meta { font-size:.72rem; color:#adb5bd; display:flex; gap:10px; align-items:center; }
.wiki-card-cat { font-size:.7rem; padding:2px 8px; border-radius:10px; color:#fff; display:inline-flex; align-items:center; gap:3px; }
.wiki-card-pin { position:absolute; top:8px; right:10px; color:var(--care-primary, #2d4a43); font-size:.85rem; }

/* Read view */
.wiki-read-panel { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:24px; }
.wiki-read-panel h1 { font-size:1.4rem; font-weight:700; margin-bottom:4px; }
.wiki-read-meta { font-size:.78rem; color:#6c757d; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }
.wiki-read-content { font-size:.92rem; line-height:1.7; }
.wiki-read-content h2 { font-size:1.15rem; font-weight:700; margin-top:20px; margin-bottom:8px; color:var(--care-primary, #2d4a43); }
.wiki-read-content h3 { font-size:1rem; font-weight:600; margin-top:16px; margin-bottom:6px; }
.wiki-read-content ul, .wiki-read-content ol { padding-left:20px; }
.wiki-read-content img { max-width:100%; border-radius:6px; margin:8px 0; }
.wiki-read-content blockquote { border-left:3px solid var(--care-primary, #2d4a43); padding-left:12px; color:#6c757d; margin:12px 0; }
.wiki-read-content a { color:var(--care-primary, #2d4a43); text-decoration:underline; }

.wiki-empty { text-align:center; padding:60px 20px; color:#adb5bd; }
.wiki-empty .bi { font-size:3rem; display:block; margin-bottom:12px; }

/* Versions panel */
.wiki-version-item { padding:8px 12px; border-bottom:1px solid #f0f0f0; font-size:.8rem; cursor:pointer; }
.wiki-version-item:hover { background:#f8f9fa; }
.wiki-version-item .ver-num { font-weight:600; color:var(--care-primary, #2d4a43); }

/* ── Category list items ─────────────────────────────── */
.wcat-item {
    display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:8px;
    background:#f8f9fa; transition:all .15s; cursor:default; margin-bottom:6px;
}
.wcat-item:hover { background:#f0eeea; }
.wcat-item .wcat-icon {
    width:40px; height:40px; border-radius:50%; display:flex; align-items:center;
    justify-content:center; color:#fff; font-size:1rem; flex-shrink:0;
}
.wcat-item .wcat-info { flex:1; min-width:0; }
.wcat-item .wcat-name { font-weight:600; font-size:.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wcat-item .wcat-slug { font-size:.7rem; color:#999; }
.wcat-item .wcat-actions { display:flex; gap:4px; opacity:0; transition:opacity .15s; }
.wcat-item:hover .wcat-actions { opacity:1; }
.wcat-act-btn {
    width:30px; height:30px; border:none; border-radius:6px; display:inline-flex;
    align-items:center; justify-content:center; cursor:pointer; font-size:.85rem;
    transition:all .12s; background:none; color:var(--cl-text-muted, #6c757d);
}
.wcat-act-btn:hover { background:var(--cl-bg, #F7F5F2); color:var(--cl-text, #1a1a1a); }
.wcat-act-del:hover { background:#E2B8AE; color:#7B3B2C; }

/* Add form */
.wcat-add-form {
    display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px;
    border:1.5px dashed #dee2e6; margin-top:8px; transition:border-color .2s;
}
.wcat-add-form:focus-within { border-color:#212529; }
.wcat-add-form input[type="text"] {
    flex:1; border:1px solid #dee2e6; border-radius:6px;
    padding:6px 10px; font-size:.85rem; outline:none;
}
.wcat-add-form input[type="text"]:focus { border-color:#212529; }
.wcat-add-form input[type="color"] {
    width:32px; height:32px; border:1px solid #dee2e6; border-radius:6px; padding:2px; cursor:pointer;
}
.wcat-add-btn {
    width:32px; height:32px; border:none; border-radius:6px;
    background:#212529; color:#fff;
    display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:.9rem;
    transition:all .15s;
}
.wcat-add-btn:hover { background:#000; }

/* ── Icon picker ────────────────────────────────────── */
.wcat-icon-search {
    border:1px solid #dee2e6; border-radius:6px;
    padding:6px 10px; font-size:.82rem; width:100%; margin-bottom:10px; outline:none;
}
.wcat-icon-search:focus { border-color:#212529; }
.wcat-icon-grid {
    display:grid; grid-template-columns:repeat(auto-fill, minmax(38px, 1fr));
    gap:4px; max-height:240px; overflow-y:auto;
}
.wcat-icon-grid .wico {
    width:38px; height:38px; border:1px solid transparent; border-radius:6px;
    display:flex; align-items:center; justify-content:center; cursor:pointer;
    font-size:1.05rem; color:#6c757d; transition:all .12s;
    background:none; padding:0;
}
.wcat-icon-grid .wico:hover { background:#f0eeea; color:#212529; }
.wcat-icon-grid .wico.active { background:#212529; color:#fff; border-color:#212529; }

/* ── Edit row ───────────────────────────────────────── */
.wcat-edit-row {
    background:#f8f9fa; border-radius:8px;
    padding:10px 12px; margin:0 0 6px 0;
}
.wcat-edit-row .row { --bs-gutter-x:8px; }

/* ── Tags ──────────────────────────────────────────── */
.wiki-tag { font-size:.65rem; padding:2px 7px; border-radius:8px; color:#fff; display:inline-flex; align-items:center; gap:2px; font-weight:600; }
.wiki-tags-row { display:flex; flex-wrap:wrap; gap:3px; margin-top:4px; }
.wiki-tag-filter { border:1px solid #dee2e6; background:#fff; border-radius:14px; padding:3px 10px; font-size:.72rem; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:3px; }
.wiki-tag-filter:hover, .wiki-tag-filter.active { color:#fff; border-color:transparent; }

/* ── Favoris ───────────────────────────────────────── */
.wiki-fav-btn { background:none; border:none; cursor:pointer; font-size:1rem; color:#ccc; transition:all .15s; padding:2px; }
.wiki-fav-btn:hover { color:#dc3545; }
.wiki-fav-btn.active { color:#dc3545; }
.wiki-fav-filter { border:1px solid #dee2e6; background:#fff; border-radius:14px; padding:3px 10px; font-size:.72rem; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:3px; }
.wiki-fav-filter.active { background:#dc3545; color:#fff; border-color:#dc3545; }

/* ── Verification badge ────────────────────────────── */
.wiki-verify-badge { font-size:.68rem; padding:2px 8px; border-radius:8px; font-weight:600; display:inline-flex; align-items:center; gap:3px; }
.wiki-verify-ok { background:#bcd2cb; color:#2d4a43; }
.wiki-verify-expired { background:#E2B8AE; color:#7B3B2C; }
.wiki-verify-none { background:#f0eeea; color:#6c757d; }
.wiki-expert-badge { font-size:.68rem; color:#3B4F6B; display:inline-flex; align-items:center; gap:3px; }
.wiki-alert-bar { background:#E2B8AE; color:#7B3B2C; padding:8px 14px; border-radius:8px; font-size:.82rem; display:flex; align-items:center; gap:8px; margin-bottom:12px; cursor:pointer; }
.wiki-alert-bar:hover { background:#d4a89c; }
</style>

<!-- LIST VIEW -->
<div id="wikiListView">
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-0"><i class="bi bi-book"></i> Base de connaissances</h5>
        <small class="text-muted">Protocoles, procédures et documentation interne</small>
      </div>
      <?php if ($isResponsable): ?>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" id="btnManageCategories" title="Gérer les catégories">
          <i class="bi bi-tags"></i>
        </button>
        <button class="btn btn-primary btn-sm" id="btnNewPage">
          <i class="bi bi-plus-lg"></i> Nouvelle page
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- Alert: expired verifications -->
    <?php if ($isResponsable && $expiredCount > 0): ?>
    <div class="wiki-alert-bar" id="wikiAlertBar">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span><strong><?= $expiredCount ?></strong> fiche(s) nécessitent une re-vérification</span>
      <i class="bi bi-chevron-right ms-auto"></i>
    </div>
    <?php endif; ?>

    <!-- Category filters -->
    <div class="wiki-cat-filters" id="wikiCatFilters"></div>

    <!-- Tag filters + Favoris -->
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center" id="wikiTagFilters">
      <button class="wiki-fav-filter" id="wikiFavFilter"><i class="bi bi-heart-fill"></i> Mes favoris</button>
    </div>

    <!-- Pages grid -->
    <div class="wiki-grid" id="wikiGrid"></div>

    <div class="wiki-empty" id="wikiEmpty" style="display:none">
      <i class="bi bi-book"></i>
      Aucune page dans cette catégorie
    </div>
  </div>
</div>

<!-- READ VIEW -->
<div id="wikiReadView" style="display:none">
  <div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light btn-sm" id="btnBackToList">
        <i class="bi bi-arrow-left"></i> Retour
      </button>
      <div class="ms-auto d-flex gap-2" id="wikiReadActions"></div>
    </div>
    <div class="wiki-read-panel" id="wikiReadPanel"></div>
  </div>
</div>

<!-- CATEGORY MODAL -->
<div class="modal fade" id="wikiCatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:500px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title">Catégories</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div id="catList"></div>
        <div class="wcat-add-form" id="catAddForm">
          <input type="text" id="catNom" placeholder="Nouvelle catégorie..." maxlength="100">
          <input type="color" id="catCouleur" value="#6c757d" title="Couleur">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddCatIcon" title="Icône" style="width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center"><i class="bi bi-book"></i></button>
          <button class="wcat-add-btn" id="btnAddCat" title="Ajouter"><i class="bi bi-plus-lg"></i></button>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- ICON PICKER MODAL -->
<div class="modal fade" id="wikiIconPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:70vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title">Choisir une icône</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <input type="text" class="wcat-icon-search" id="iconPickerSearch" placeholder="Rechercher une icône..." autocomplete="off">
        <div class="wcat-icon-grid" id="iconPickerGrid"></div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
      </div>
    </div>
  </div>
</div>

<!-- VERSIONS MODAL -->
<div class="modal fade" id="wikiVersionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <h5 class="modal-title">Historique des versions</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" id="versionsBody" style="flex:1;overflow-y:auto"></div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const isResp = <?= $isResponsable ? 'true' : 'false' ?>;
    const ssrCategories = <?= json_encode(array_values($categories), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const ssrPages = <?= json_encode(array_values($pages), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const ssrTags = <?= json_encode(array_values($tags), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    let allPages = ssrPages;
    let allCategories = ssrCategories;
    let allTags = ssrTags;
    let currentFilter = '';
    let currentTagFilter = '';
    let favOnlyFilter = false;
    let currentPageId = null;
    let editingCatId = null;
    let iconPickerCallback = null;

    // Icon set (same as CMS notes_manager)
    const ICON_SET = [
        'heart-pulse','shield-check','person-badge','egg-fried','house-heart','mortarboard',
        'book','book-half','book-fill','bookmarks','bookmarks-fill',
        'journal','journal-text','journal-richtext','journal-bookmark',
        'clipboard','clipboard-check','clipboard-data','clipboard-heart',
        'file-text','file-earmark','file-earmark-text','file-earmark-pdf',
        'pencil-square','pen','vector-pen',
        'star','star-fill','bookmark','bookmark-fill','pin-angle','pin-angle-fill',
        'calendar3','calendar-check','calendar-heart','alarm','bell','clock','hourglass',
        'person','person-fill','person-heart','person-check','people','people-fill',
        'envelope','envelope-heart','chat','chat-dots','chat-heart','chat-square-heart',
        'list-task','list-check','check2-square','patch-check',
        'heart','heart-fill','heart-pulse-fill','emoji-smile','emoji-laughing','lightbulb',
        'folder','folder2','folder-check','folder-heart','archive','inbox','box',
        'tag','tags','tag-fill',
        'camera','image','images','music-note',
        'sun','moon','cloud','tree','flower1','droplet','rainbow',
        'geo-alt','map','compass','globe','airplane',
        'link-45deg','palette','brush','eyedropper','key','lock','shield','shield-lock',
        'briefcase','building','house','house-fill','trophy','award','flag','gift',
        'cup-hot','megaphone','lightning','fire','bandaid','thermometer','activity',
        'exclamation-triangle','info-circle','question-circle','gear','tools','wrench',
        'graph-up','bar-chart','pie-chart','speedometer2','diagram-3',
        'hand-thumbs-up','hand-index','eye','search','binoculars',
        'stickies','sticky','card-text','card-checklist',
    ];

    // ── Init ──────────────────────────────────────────────
    function init() {
        renderCatFilters();
        renderTagFilters();
        renderGrid();
        bindEvents();
        bindSearch();
    }

    // ── Categories filters ────────────────────────────────
    function renderCatFilters() {
        const c = document.getElementById('wikiCatFilters');
        let html = `<button class="wiki-cat-btn ${!currentFilter ? 'active' : ''}" data-cat="" style="background:${!currentFilter ? '#2d4a43' : '#fff'};color:${!currentFilter ? '#fff' : '#333'}">
            <i class="bi bi-grid-3x3-gap"></i> Toutes
        </button>`;
        allCategories.forEach(cat => {
            const active = currentFilter === cat.id;
            html += `<button class="wiki-cat-btn ${active ? 'active' : ''}" data-cat="${cat.id}" style="background:${active ? cat.couleur : '#fff'};color:${active ? '#fff' : '#333'};border-color:${cat.couleur}">
                <i class="bi bi-${escapeHtml(cat.icone)}"></i> ${escapeHtml(cat.nom)}
            </button>`;
        });
        c.innerHTML = html;
        c.querySelectorAll('.wiki-cat-btn').forEach(btn => {
            btn.addEventListener('click', () => { currentFilter = btn.dataset.cat; renderCatFilters(); renderGrid(); });
        });
    }

    // ── Tag filters ───────────────────────────────────────
    function renderTagFilters() {
        const c = document.getElementById('wikiTagFilters');
        if (!c) return;
        let html = `<button class="wiki-fav-filter ${favOnlyFilter ? 'active' : ''}" id="wikiFavFilter"><i class="bi bi-heart-fill"></i> Mes favoris</button>`;
        allTags.forEach(tag => {
            const active = currentTagFilter === tag.id;
            html += `<button class="wiki-tag-filter ${active ? 'active' : ''}" data-tag="${tag.id}" style="background:${active ? tag.couleur : '#fff'};color:${active ? '#fff' : '#333'};border-color:${tag.couleur}">
                ${escapeHtml(tag.nom)}
            </button>`;
        });
        c.innerHTML = html;
        c.querySelectorAll('.wiki-tag-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                currentTagFilter = currentTagFilter === btn.dataset.tag ? '' : btn.dataset.tag;
                renderTagFilters(); renderGrid();
            });
        });
        c.querySelector('#wikiFavFilter')?.addEventListener('click', () => {
            favOnlyFilter = !favOnlyFilter;
            renderTagFilters(); renderGrid();
        });
    }

    // ── Grid ──────────────────────────────────────────────
    function renderGrid() {
        const grid = document.getElementById('wikiGrid');
        const empty = document.getElementById('wikiEmpty');
        const searchVal = (document.getElementById('topbarSearchInput')?.value || '').toLowerCase();

        let filtered = allPages;
        if (currentFilter) filtered = filtered.filter(p => p.categorie_id === currentFilter);
        if (currentTagFilter) filtered = filtered.filter(p => (p.tags || []).some(t => t.id === currentTagFilter));
        if (favOnlyFilter) filtered = filtered.filter(p => p.is_favori);
        if (searchVal) filtered = filtered.filter(p =>
            (p.titre || '').toLowerCase().includes(searchVal) ||
            (p.description || '').toLowerCase().includes(searchVal)
        );

        if (!filtered.length) { grid.innerHTML = ''; empty.style.display = ''; return; }
        empty.style.display = 'none';

        grid.innerHTML = filtered.map(p => {
            const catBadge = p.categorie_nom
                ? `<span class="wiki-card-cat" style="background:${p.categorie_couleur || '#6c757d'}"><i class="bi bi-${escapeHtml(p.categorie_icone || 'book')}"></i> ${escapeHtml(p.categorie_nom)}</span>`
                : '';
            const pin = p.epingle == 1 ? '<i class="bi bi-pin-angle-fill wiki-card-pin" title="Épinglé"></i>' : '';
            const date = p.updated_at ? new Date(p.updated_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric'}) : '';
            const auteur = p.auteur_prenom ? `${p.auteur_prenom} ${p.auteur_nom}` : '';
            const tagsHtml = (p.tags || []).map(t => `<span class="wiki-tag" style="background:${t.couleur}">${escapeHtml(t.nom)}</span>`).join('');
            const favIcon = p.is_favori ? 'heart-fill' : 'heart';
            const favClass = p.is_favori ? 'active' : '';
            // Verification status
            let verifyBadge = '';
            if (p.verify_next) {
                const isExpired = new Date(p.verify_next) <= new Date();
                verifyBadge = isExpired
                    ? '<span class="wiki-verify-badge wiki-verify-expired"><i class="bi bi-exclamation-triangle"></i> À revérifier</span>'
                    : '<span class="wiki-verify-badge wiki-verify-ok"><i class="bi bi-patch-check"></i> Vérifié</span>';
            }
            return `<div class="wiki-card ${p.epingle == 1 ? 'pinned' : ''}" data-id="${p.id}">
                ${pin}
                <button class="wiki-fav-btn ${favClass}" data-fav-id="${p.id}" title="Favoris" style="position:absolute;top:8px;right:${p.epingle == 1 ? '28' : '10'}px"><i class="bi bi-${favIcon}"></i></button>
                <div class="wiki-card-title">${escapeHtml(p.titre)}</div>
                <div class="wiki-card-desc">${escapeHtml(p.description || '')}</div>
                ${tagsHtml ? `<div class="wiki-tags-row">${tagsHtml}</div>` : ''}
                <div class="wiki-card-meta" style="margin-top:6px">
                    ${catBadge} ${verifyBadge}
                    <span><i class="bi bi-calendar3"></i> ${date}</span>
                    ${auteur ? `<span><i class="bi bi-person"></i> ${escapeHtml(auteur)}</span>` : ''}
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('.wiki-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.wiki-fav-btn')) return;
                openPage(card.dataset.id);
            });
        });

        // Favori toggle on cards
        grid.querySelectorAll('.wiki-fav-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const pid = btn.dataset.favId;
                const res = await adminApiPost('admin_toggle_wiki_favori', { page_id: pid });
                if (!res.success) return;
                // Update local data
                const page = allPages.find(p => p.id === pid);
                if (page) page.is_favori = res.is_favori;
                renderGrid();
            });
        });
    }

    // ── Read page ─────────────────────────────────────────
    async function openPage(id) {
        const res = await adminApiPost('admin_get_wiki_page', { id });
        if (!res.success) return showToast(res.message || 'Erreur', 'danger');

        currentPageId = id;
        const p = res.page;
        const date = p.updated_at ? new Date(p.updated_at).toLocaleDateString('fr-CH', {day:'numeric',month:'long',year:'numeric'}) : '';
        const auteur = p.auteur_prenom ? `${p.auteur_prenom} ${p.auteur_nom}` : '';
        const modif = p.modif_prenom ? `${p.modif_prenom} ${p.modif_nom}` : '';
        const catBadge = p.categorie_nom
            ? `<span class="wiki-card-cat" style="background:${p.categorie_couleur || '#6c757d'}"><i class="bi bi-${escapeHtml(p.categorie_icone || 'book')}"></i> ${escapeHtml(p.categorie_nom)}</span>`
            : '';

        document.getElementById('wikiReadPanel').innerHTML = `
            <h1>${escapeHtml(p.titre)}</h1>
            <div class="wiki-read-meta">
                ${catBadge}
                <span class="ms-2"><i class="bi bi-calendar3"></i> ${date}</span>
                ${auteur ? ` &middot; <i class="bi bi-person"></i> ${escapeHtml(auteur)}` : ''}
                ${modif ? ` &middot; Modifié par ${escapeHtml(modif)}` : ''}
                &middot; Version ${p.version || 1}
            </div>
            <div class="wiki-read-content">${p.contenu || '<p class="text-muted">Aucun contenu</p>'}</div>
        `;

        let actions = `<button class="btn btn-outline-secondary btn-sm" id="btnVersions"><i class="bi bi-clock-history"></i> Versions</button>`;
        if (isResp) {
            actions += `
                <button class="btn btn-outline-primary btn-sm" id="btnEditPage"><i class="bi bi-pencil"></i> Modifier</button>
                <button class="btn btn-outline-${p.epingle == 1 ? 'warning' : 'secondary'} btn-sm" id="btnTogglePin"><i class="bi bi-pin-angle${p.epingle == 1 ? '-fill' : ''}"></i></button>
                <button class="btn btn-outline-danger btn-sm" id="btnDeletePage"><i class="bi bi-trash"></i></button>
            `;
        }
        document.getElementById('wikiReadActions').innerHTML = actions;

        document.getElementById('btnVersions')?.addEventListener('click', () => showVersions(id));
        document.getElementById('btnEditPage')?.addEventListener('click', () => AdminURL.go('wiki-edit', id));
        document.getElementById('btnTogglePin')?.addEventListener('click', async () => {
            await adminApiPost('admin_update_wiki_page', { id, epingle: p.epingle == 1 ? 0 : 1 });
            openPage(id); reload();
        });
        document.getElementById('btnDeletePage')?.addEventListener('click', async () => {
            const ok = await adminConfirm({ title: 'Archiver cette page ?', text: 'Elle pourra être restaurée.', icon: 'bi-archive', type: 'danger', okText: 'Archiver' });
            if (!ok) return;
            const r = await adminApiPost('admin_delete_wiki_page', { id });
            if (r.success) { showToast('Page archivée'); backToList(); reload(); }
        });

        document.getElementById('wikiListView').style.display = 'none';
        document.getElementById('wikiReadView').style.display = '';
    }

    function backToList() {
        currentPageId = null;
        document.getElementById('wikiReadView').style.display = 'none';
        document.getElementById('wikiListView').style.display = '';
    }

    // ── Versions ──────────────────────────────────────────
    async function showVersions(pageId) {
        const res = await adminApiPost('admin_get_wiki_versions', { page_id: pageId });
        if (!res.success) return;
        const body = document.getElementById('versionsBody');
        if (!res.versions.length) {
            body.innerHTML = '<p class="text-muted text-center py-4">Aucun historique</p>';
        } else {
            body.innerHTML = res.versions.map(v => {
                const date = new Date(v.created_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
                const who = v.prenom ? `${v.prenom} ${v.nom}` : '';
                return `<div class="wiki-version-item" data-vid="${v.id}">
                    <span class="ver-num">v${v.version}</span> &middot; ${date}
                    ${who ? ` &middot; ${escapeHtml(who)}` : ''}
                    ${v.note ? ` &middot; <em>${escapeHtml(v.note)}</em>` : ''}
                    ${isResp ? `<button class="btn btn-sm btn-primary float-end btn-restore-ver" data-vid="${v.id}">Restaurer</button>` : ''}
                </div>`;
            }).join('');

            body.querySelectorAll('.btn-restore-ver').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const ok = await adminConfirm({ title: 'Restaurer cette version ?', text: 'La version actuelle sera sauvegardée dans l\'historique.', okText: 'Restaurer' });
                    if (!ok) return;
                    const r = await adminApiPost('admin_restore_wiki_version', { version_id: btn.dataset.vid });
                    if (r.success) {
                        showToast(r.message);
                        bootstrap.Modal.getInstance(document.getElementById('wikiVersionsModal'))?.hide();
                        openPage(currentPageId); reload();
                    }
                });
            });
        }
        new bootstrap.Modal(document.getElementById('wikiVersionsModal')).show();
    }

    // ══════════════════════════════════════════════════════
    //  CATEGORY MANAGEMENT (modal)
    // ══════════════════════════════════════════════════════

    function renderCatList() {
        const c = document.getElementById('catList');
        if (!allCategories.length) {
            c.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-tags" style="font-size:2rem;display:block;margin-bottom:8px"></i>Aucune catégorie</div>';
            return;
        }
        c.innerHTML = allCategories.map(cat => `
            <div class="wcat-item" data-cat-id="${cat.id}">
                <div class="wcat-icon" style="background:${cat.couleur || '#6c757d'}">
                    <i class="bi bi-${escapeHtml(cat.icone || 'book')}"></i>
                </div>
                <div class="wcat-info">
                    <div class="wcat-name">${escapeHtml(cat.nom)}</div>
                    <div class="wcat-slug">${escapeHtml(cat.slug || '')}</div>
                </div>
                <div class="wcat-actions">
                    <button class="wcat-act-btn wcat-act-edit" data-edit-cat="${cat.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="wcat-act-btn wcat-act-del" data-del-cat="${cat.id}" title="Supprimer"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
        `).join('');

        // Edit buttons
        c.querySelectorAll('[data-edit-cat]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openCatEdit(btn.dataset.editCat);
            });
        });

        // Delete buttons
        c.querySelectorAll('[data-del-cat]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const ok = await adminConfirm({ title: 'Supprimer cette catégorie ?', text: 'Les pages liées ne seront pas supprimées.', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer' });
                if (!ok) return;
                const r = await adminApiPost('admin_delete_wiki_category', { id: btn.dataset.delCat });
                if (r.success) { showToast('Catégorie supprimée'); reloadCategories(); }
                else showToast(r.message || 'Erreur', 'danger');
            });
        });
    }

    function openCatEdit(catId) {
        const cat = allCategories.find(c => c.id === catId);
        if (!cat) return;
        editingCatId = catId;

        const item = document.querySelector(`.wcat-item[data-cat-id="${catId}"]`);
        if (!item) return;

        // Check if edit row already open
        const existing = item.nextElementSibling;
        if (existing?.classList?.contains('wcat-edit-row')) { existing.remove(); return; }

        // Close any other edit row
        document.querySelectorAll('.wcat-edit-row').forEach(el => el.remove());

        const row = document.createElement('div');
        row.className = 'wcat-edit-row';
        row.innerHTML = `
            <div class="row align-items-center g-2">
                <div class="col">
                    <input type="text" class="form-control form-control-sm" id="editCatNom" value="${escapeHtml(cat.nom)}" maxlength="100">
                </div>
                <div class="col-auto">
                    <input type="color" class="form-control form-control-sm form-control-color" id="editCatCouleur" value="${cat.couleur || '#6c757d'}" title="Couleur" style="width:36px;height:32px">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm" id="editCatIconBtn" title="Changer l'icône" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-${escapeHtml(cat.icone || 'book')}"></i>
                    </button>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary" id="editCatSave" disabled><i class="bi bi-check-lg"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" id="editCatCancel"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        `;
        item.after(row);

        let selectedIcon = cat.icone || 'book';
        const saveBtn = row.querySelector('#editCatSave');

        // Track changes → activate save button
        function markDirty() {
            saveBtn.disabled = false;
            saveBtn.className = 'btn btn-sm btn-primary';
        }
        row.querySelector('#editCatNom').addEventListener('input', markDirty);
        row.querySelector('#editCatCouleur').addEventListener('input', markDirty);

        // Icon picker
        row.querySelector('#editCatIconBtn').addEventListener('click', () => {
            openIconPicker(selectedIcon, (icon) => {
                selectedIcon = icon;
                row.querySelector('#editCatIconBtn i').className = 'bi bi-' + icon;
                markDirty();
            });
        });

        // Save
        row.querySelector('#editCatSave').addEventListener('click', async () => {
            const nom = row.querySelector('#editCatNom').value.trim();
            if (!nom) return;
            const couleur = row.querySelector('#editCatCouleur').value;
            const r = await adminApiPost('admin_update_wiki_category', { id: catId, nom, couleur, icone: selectedIcon });
            if (r.success) { showToast('Catégorie mise à jour'); reloadCategories(); }
            else showToast(r.message || 'Erreur', 'danger');
        });

        // Cancel
        row.querySelector('#editCatCancel').addEventListener('click', () => row.remove());
    }

    // ══════════════════════════════════════════════════════
    //  ICON PICKER
    // ══════════════════════════════════════════════════════

    function openIconPicker(currentIcon, callback) {
        iconPickerCallback = callback;
        const grid = document.getElementById('iconPickerGrid');
        const search = document.getElementById('iconPickerSearch');
        search.value = '';

        buildIconGrid(grid, '', currentIcon);

        // Search
        search.oninput = () => buildIconGrid(grid, search.value, currentIcon);

        // Show as nested modal
        const catModal = bootstrap.Modal.getInstance(document.getElementById('wikiCatModal'));
        // Use a second modal on top
        new bootstrap.Modal(document.getElementById('wikiIconPickerModal')).show();
    }

    function buildIconGrid(grid, filter, currentIcon) {
        const terms = (filter || '').toLowerCase();
        const icons = ICON_SET.filter(k => !terms || k.includes(terms));
        grid.innerHTML = icons.map(name =>
            `<button type="button" class="wico ${name === currentIcon ? 'active' : ''}" data-icon="${name}" title="${name}"><i class="bi bi-${name}"></i></button>`
        ).join('');

        grid.querySelectorAll('.wico').forEach(btn => {
            btn.addEventListener('click', () => {
                if (iconPickerCallback) iconPickerCallback(btn.dataset.icon);
                bootstrap.Modal.getInstance(document.getElementById('wikiIconPickerModal'))?.hide();
            });
        });
    }

    // ── Reload ────────────────────────────────────────────
    async function reload() {
        const res = await adminApiPost('admin_get_wiki_pages', {});
        if (res.success) { allPages = res.pages; renderGrid(); }
    }

    async function reloadCategories() {
        const res = await adminApiPost('admin_get_wiki_categories', {});
        if (res.success) { allCategories = res.categories; renderCatFilters(); renderCatList(); }
    }

    // ── Bind ──────────────────────────────────────────────
    function bindEvents() {
        document.getElementById('btnBackToList')?.addEventListener('click', backToList);
        document.getElementById('btnNewPage')?.addEventListener('click', () => AdminURL.go('wiki-edit'));
        document.getElementById('wikiAlertBar')?.addEventListener('click', async () => {
            const res = await adminApiPost('admin_get_wiki_expired', {});
            if (!res.success) return;
            // Show expired pages inline — filter allPages to only expired
            const expiredIds = new Set(res.expired.map(e => e.id));
            allPages.forEach(p => { p._expired_highlight = expiredIds.has(p.id); });
            currentFilter = ''; currentTagFilter = ''; favOnlyFilter = false;
            renderCatFilters(); renderTagFilters(); renderGrid();
            showToast(res.count + ' fiche(s) à revérifier');
        });

        document.getElementById('btnManageCategories')?.addEventListener('click', () => {
            renderCatList();
            new bootstrap.Modal(document.getElementById('wikiCatModal')).show();
        });

        let newCatIcon = 'book';
        document.getElementById('btnAddCatIcon')?.addEventListener('click', () => {
            openIconPicker(newCatIcon, (icon) => {
                newCatIcon = icon;
                document.querySelector('#btnAddCatIcon i').className = 'bi bi-' + icon;
            });
        });

        document.getElementById('btnAddCat')?.addEventListener('click', async () => {
            const nom = document.getElementById('catNom').value.trim();
            const couleur = document.getElementById('catCouleur').value;
            if (!nom) return;
            const r = await adminApiPost('admin_create_wiki_category', { nom, couleur, icone: newCatIcon });
            if (r.success) {
                document.getElementById('catNom').value = '';
                newCatIcon = 'book';
                document.querySelector('#btnAddCatIcon i').className = 'bi bi-book';
                showToast('Catégorie créée');
                reloadCategories();
            } else showToast(r.message || 'Erreur', 'danger');
        });

        // Enter key on add input
        document.getElementById('catNom')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') document.getElementById('btnAddCat').click();
        });
    }

    function bindSearch() {
        const input = document.getElementById('topbarSearchInput');
        if (!input) return;
        let timer;
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => renderGrid(), 250); });
    }

    // ── Start ─────────────────────────────────────────────
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
