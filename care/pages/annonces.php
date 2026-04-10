<?php
/**
 * SpocCare — Annonces officielles
 * Communication descendante : direction → personnel
 */
$user = $_SESSION['ss_user'];
$isResponsable = in_array($user['role'], ['admin', 'direction', 'responsable']);

$annonces = Db::fetchAll(
    "SELECT a.id, a.titre, a.slug, a.description, a.image_url, a.categorie,
            a.epingle, a.published_at, a.created_at,
            cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
     FROM annonces a
     LEFT JOIN users cr ON cr.id = a.created_by
     WHERE a.archived_at IS NULL AND a.visible = 1
     ORDER BY a.epingle DESC, a.published_at DESC"
);

$catLabels = [
    'direction' => ['label' => 'Direction', 'icon' => 'building', 'color' => '#2d4a43'],
    'rh' => ['label' => 'RH', 'icon' => 'person-badge', 'color' => '#3B4F6B'],
    'vie_sociale' => ['label' => 'Vie sociale', 'icon' => 'balloon-heart', 'color' => '#5B4B6B'],
    'cuisine' => ['label' => 'Cuisine', 'icon' => 'egg-fried', 'color' => '#198754'],
    'protocoles' => ['label' => 'Protocoles', 'icon' => 'heart-pulse', 'color' => '#dc3545'],
    'securite' => ['label' => 'Sécurité', 'icon' => 'shield-check', 'color' => '#fd7e14'],
    'divers' => ['label' => 'Divers', 'icon' => 'info-circle', 'color' => '#6c757d'],
];
?>
<style>
/* ── Annonces list ─────────────────────────────────── */
.ann-filters { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
.ann-filter-btn { border:1px solid #dee2e6; background:#fff; border-radius:20px; padding:4px 14px; font-size:.8rem; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:5px; }
.ann-filter-btn:hover, .ann-filter-btn.active { color:#fff; border-color:transparent; }

.ann-list { display:flex; flex-direction:column; gap:12px; }
.ann-card {
    display:flex; gap:16px; background:#fff; border:1px solid #e9ecef; border-radius:10px;
    padding:16px; cursor:pointer; transition:all .2s; position:relative; overflow:hidden;
}
.ann-card:hover { border-color:var(--care-primary, #2d4a43); box-shadow:0 2px 8px rgba(0,0,0,.06); }
.ann-card.pinned { border-left:3px solid var(--care-primary, #2d4a43); }
.ann-card-img {
    width:120px; height:90px; border-radius:8px; object-fit:cover; flex-shrink:0; background:#f0eeea;
}
.ann-card-img-placeholder {
    width:120px; height:90px; border-radius:8px; flex-shrink:0; background:#f0eeea;
    display:flex; align-items:center; justify-content:center; color:#adb5bd; font-size:1.5rem;
}
.ann-card-body { flex:1; min-width:0; }
.ann-card-title { font-weight:600; font-size:.95rem; margin-bottom:4px; }
.ann-card-desc { font-size:.82rem; color:#6c757d; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:8px; }
.ann-card-meta { font-size:.72rem; color:#adb5bd; display:flex; gap:10px; align-items:center; }
.ann-card-cat { font-size:.68rem; padding:2px 8px; border-radius:10px; color:#fff; display:inline-flex; align-items:center; gap:3px; font-weight:600; }
.ann-card-pin { position:absolute; top:10px; right:12px; color:var(--care-primary, #2d4a43); font-size:.85rem; }

/* ── Read view ─────────────────────────────────────── */
.ann-read-panel { background:#fff; border:1px solid #e9ecef; border-radius:10px; overflow:hidden; }
.ann-read-hero { width:100%; max-height:300px; object-fit:cover; display:block; }
.ann-read-content-wrap { padding:24px; }
.ann-read-content-wrap h1 { font-size:1.4rem; font-weight:700; margin-bottom:4px; }
.ann-read-meta { font-size:.78rem; color:#6c757d; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }
.ann-read-body { font-size:.92rem; line-height:1.7; }
.ann-read-body h2 { font-size:1.15rem; font-weight:700; margin-top:20px; margin-bottom:8px; color:var(--care-primary, #2d4a43); }
.ann-read-body h3 { font-size:1rem; font-weight:600; margin-top:16px; margin-bottom:6px; }
.ann-read-body img { max-width:100%; border-radius:6px; margin:8px 0; }
.ann-read-body blockquote { border-left:3px solid var(--care-primary, #2d4a43); padding-left:12px; color:#6c757d; margin:12px 0; font-style:italic; }
.ann-read-body table { border-collapse:collapse; width:100%; margin:12px 0; border:1px solid #dee2e6; }
.ann-read-body table th { background:#f8f9fa; font-weight:600; font-size:.85rem; padding:10px 12px; border:1px solid #dee2e6; text-align:left; }
.ann-read-body table td { padding:10px 12px; border:1px solid #dee2e6; font-size:.88rem; vertical-align:top; }
.ann-read-body table tr:hover td { background:#fafaf7; }

.ann-empty { text-align:center; padding:60px 20px; color:#adb5bd; }
.ann-empty .bi { font-size:3rem; display:block; margin-bottom:12px; }

@media(max-width:576px) {
    .ann-card { flex-direction:column; }
    .ann-card-img, .ann-card-img-placeholder { width:100%; height:140px; }
}

.ann-ack-badge { display:inline-block; background:#fff3cd; color:#856404; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:600; }
.ann-ack-required { background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:14px; margin:14px 0; color:#7a5c00; font-size:.88rem; }
.ann-ack-confirmed { background:#d4edda; border:1px solid #a7d4b0; border-radius:8px; padding:10px 14px; margin:14px 0; color:#155724; font-size:.85rem; display:flex; align-items:center; gap:8px; }
</style>

<!-- LIST VIEW -->
<div id="annListView">
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-0"><i class="bi bi-megaphone"></i> Annonces officielles</h5>
        <small class="text-muted">Communications de la direction</small>
      </div>
      <?php if ($isResponsable): ?>
      <button class="btn btn-primary btn-sm" id="btnNewAnnonce">
        <i class="bi bi-plus-lg"></i> Nouvelle annonce
      </button>
      <?php endif; ?>
    </div>

    <div class="ann-filters" id="annFilters"></div>
    <div class="ann-list" id="annList"></div>
    <div class="ann-empty" id="annEmpty" style="display:none">
      <i class="bi bi-megaphone"></i>
      Aucune annonce pour le moment
    </div>
  </div>
</div>

<!-- READ VIEW -->
<div id="annReadView" style="display:none">
  <div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light btn-sm" id="btnBackToAnnList">
        <i class="bi bi-arrow-left"></i> Retour
      </button>
      <div class="ms-auto d-flex gap-2" id="annReadActions"></div>
    </div>
    <div class="ann-read-panel" id="annReadPanel"></div>
  </div>
</div>

<script<?= nonce() ?>>
(function(){
    const isResp = <?= $isResponsable ? 'true' : 'false' ?>;
    const ssrAnnonces = <?= json_encode(array_values($annonces), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const CAT_LABELS = <?= json_encode($catLabels, JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    let allAnnonces = ssrAnnonces;
    let currentFilter = '';

    function init() {
        renderFilters();
        renderList();
        bindEvents();
        bindSearch();
        const urlId = (typeof AdminURL !== 'undefined') ? AdminURL.currentId() : '';
        if (urlId) openAnnonce(urlId);
    }

    function renderFilters() {
        const c = document.getElementById('annFilters');
        let html = `<button class="ann-filter-btn ${!currentFilter ? 'active' : ''}" data-cat="" style="background:${!currentFilter ? '#2d4a43' : '#fff'};color:${!currentFilter ? '#fff' : '#333'}">
            <i class="bi bi-grid-3x3-gap"></i> Toutes
        </button>`;
        Object.entries(CAT_LABELS).forEach(([key, cat]) => {
            const active = currentFilter === key;
            html += `<button class="ann-filter-btn ${active ? 'active' : ''}" data-cat="${key}" style="background:${active ? cat.color : '#fff'};color:${active ? '#fff' : '#333'};border-color:${cat.color}">
                <i class="bi bi-${cat.icon}"></i> ${cat.label}
            </button>`;
        });
        c.innerHTML = html;
        c.querySelectorAll('.ann-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => { currentFilter = btn.dataset.cat; renderFilters(); renderList(); });
        });
    }

    function renderList() {
        const list = document.getElementById('annList');
        const empty = document.getElementById('annEmpty');
        const searchVal = (document.getElementById('topbarSearchInput')?.value || '').toLowerCase();

        let filtered = allAnnonces;
        if (currentFilter) filtered = filtered.filter(a => a.categorie === currentFilter);
        if (searchVal) filtered = filtered.filter(a =>
            (a.titre || '').toLowerCase().includes(searchVal) ||
            (a.description || '').toLowerCase().includes(searchVal)
        );

        if (!filtered.length) { list.innerHTML = ''; empty.style.display = ''; return; }
        empty.style.display = 'none';

        list.innerHTML = filtered.map(a => {
            const cat = CAT_LABELS[a.categorie] || CAT_LABELS.divers;
            const pin = a.epingle == 1 ? '<i class="bi bi-pin-angle-fill ann-card-pin" title="Épinglé"></i>' : '';
            const date = a.published_at ? new Date(a.published_at).toLocaleDateString('fr-CH', {day:'numeric',month:'long',year:'numeric'}) : '';
            const auteur = a.auteur_prenom ? `${a.auteur_prenom} ${a.auteur_nom}` : '';
            const img = a.image_url
                ? `<img class="ann-card-img" src="${escapeHtml(a.image_url)}" alt="">`
                : `<div class="ann-card-img-placeholder"><i class="bi bi-${cat.icon}"></i></div>`;

            return `<div class="ann-card ${a.epingle == 1 ? 'pinned' : ''}" data-id="${a.id}">
                ${pin}
                ${img}
                <div class="ann-card-body">
                    <div class="ann-card-title">${escapeHtml(a.titre)}</div>
                    <div class="ann-card-desc">${escapeHtml(a.description || '')}</div>
                    <div class="ann-card-meta">
                        <span class="ann-card-cat" style="background:${cat.color}"><i class="bi bi-${cat.icon}"></i> ${cat.label}</span>
                        <span><i class="bi bi-calendar3"></i> ${date}</span>
                        ${auteur ? `<span><i class="bi bi-person"></i> ${escapeHtml(auteur)}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');

        list.querySelectorAll('.ann-card').forEach(card => {
            card.addEventListener('click', () => openAnnonce(card.dataset.id));
        });
    }

    async function openAnnonce(id) {
        const res = await adminApiPost('admin_get_annonce', { id });
        if (!res.success) return showToast(res.message || 'Erreur', 'danger');

        const a = res.annonce;
        const cat = CAT_LABELS[a.categorie] || CAT_LABELS.divers;
        const date = a.published_at ? new Date(a.published_at).toLocaleDateString('fr-CH', {day:'numeric',month:'long',year:'numeric'}) : '';
        const auteur = a.auteur_prenom ? `${a.auteur_prenom} ${a.auteur_nom}` : '';
        const heroImg = a.image_url ? `<img class="ann-read-hero" src="${escapeHtml(a.image_url)}" alt="">` : '';

        const ackBlock = a.requires_ack ? (a.user_acked
            ? `<div class="ann-ack-confirmed"><i class="bi bi-check-circle-fill"></i> Vous avez confirmé la lecture de cette annonce</div>`
            : `<div class="ann-ack-required">
                 <div><i class="bi bi-shield-exclamation"></i> Cette annonce nécessite votre accusé de lecture</div>
                 <button class="btn btn-success btn-sm mt-2" id="btnAckAnnonce"><i class="bi bi-check2"></i> J'ai lu et compris</button>
               </div>`) : '';

        document.getElementById('annReadPanel').innerHTML = `
            ${heroImg}
            <div class="ann-read-content-wrap">
                <h1>${escapeHtml(a.titre)}</h1>
                <div class="ann-read-meta">
                    <span class="ann-card-cat" style="background:${cat.color}"><i class="bi bi-${cat.icon}"></i> ${cat.label}</span>
                    <span class="ms-2"><i class="bi bi-calendar3"></i> ${date}</span>
                    ${auteur ? ` &middot; <i class="bi bi-person"></i> ${escapeHtml(auteur)}` : ''}
                    ${a.requires_ack ? ' &middot; <span class="ann-ack-badge"><i class="bi bi-shield-check"></i> Accusé requis</span>' : ''}
                </div>
                ${ackBlock}
                <div class="ann-read-body">${a.contenu || '<p class="text-muted">Aucun contenu</p>'}</div>
            </div>
        `;

        document.getElementById('btnAckAnnonce')?.addEventListener('click', async () => {
            const r = await adminApiPost('admin_ack_annonce', { id });
            if (r.success) { showToast('Lecture confirmée'); openAnnonce(id); }
        });

        let actions = '';
        if (isResp) {
            actions = `
                <button class="btn btn-outline-primary btn-sm" id="btnEditAnnonce"><i class="bi bi-pencil"></i> Modifier</button>
                <button class="btn btn-outline-${a.epingle == 1 ? 'warning' : 'secondary'} btn-sm" id="btnTogglePinAnn"><i class="bi bi-pin-angle${a.epingle == 1 ? '-fill' : ''}"></i></button>
                <button class="btn btn-outline-danger btn-sm" id="btnDeleteAnnonce"><i class="bi bi-trash"></i></button>
            `;
        }
        document.getElementById('annReadActions').innerHTML = actions;

        document.getElementById('btnEditAnnonce')?.addEventListener('click', () => AdminURL.go('annonce-edit', id));
        document.getElementById('btnTogglePinAnn')?.addEventListener('click', async () => {
            await adminApiPost('admin_update_annonce', { id, epingle: a.epingle == 1 ? 0 : 1 });
            openAnnonce(id); reload();
        });
        document.getElementById('btnDeleteAnnonce')?.addEventListener('click', async () => {
            const ok = await adminConfirm({ title: 'Archiver cette annonce ?', text: 'Elle ne sera plus visible.', icon: 'bi-archive', type: 'danger', okText: 'Archiver' });
            if (!ok) return;
            const r = await adminApiPost('admin_delete_annonce', { id });
            if (r.success) { showToast('Annonce archivée'); backToList(); reload(); }
        });

        document.getElementById('annListView').style.display = 'none';
        document.getElementById('annReadView').style.display = '';
    }

    function backToList() {
        document.getElementById('annReadView').style.display = 'none';
        document.getElementById('annListView').style.display = '';
    }

    async function reload() {
        const res = await adminApiPost('admin_get_annonces', {});
        if (res.success) { allAnnonces = res.annonces; renderList(); }
    }

    function bindEvents() {
        document.getElementById('btnBackToAnnList')?.addEventListener('click', backToList);
        document.getElementById('btnNewAnnonce')?.addEventListener('click', () => AdminURL.go('annonce-edit'));
    }

    function bindSearch() {
        const input = document.getElementById('topbarSearchInput');
        if (!input) return;
        let timer;
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => renderList(), 250); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
