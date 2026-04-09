/**
 * Wiki / Base de connaissances — Employee SPA module (lecture + favoris)
 */
import { apiPost, escapeHtml } from '../helpers.js';

let categories = [];
let tags = [];
let allPages = [];
let currentViewer = null;
let currentFilter = '';
let currentTagFilter = '';
let favOnlyFilter = false;

function renderCatFilters() {
    const c = document.getElementById('wikiCatFilters');
    if (!c) return;
    let html = `<button class="wiki-cat-btn ${!currentFilter ? 'active' : ''}" data-cat="" style="background:${!currentFilter ? '#2d4a43' : '#fff'};color:${!currentFilter ? '#fff' : '#333'}">
        <i class="bi bi-grid-3x3-gap"></i> Toutes
    </button>`;
    categories.forEach(cat => {
        const active = currentFilter === cat.id;
        html += `<button class="wiki-cat-btn ${active ? 'active' : ''}" data-cat="${cat.id}" style="background:${active ? cat.couleur : '#fff'};color:${active ? '#fff' : '#333'};border-color:${cat.couleur}">
            <i class="bi bi-${escapeHtml(cat.icone || 'book')}"></i> ${escapeHtml(cat.nom)}
        </button>`;
    });
    c.innerHTML = html;
    c.querySelectorAll('.wiki-cat-btn').forEach(btn => {
        btn.addEventListener('click', () => { currentFilter = btn.dataset.cat; renderCatFilters(); renderGrid(); });
    });
}

function renderTagFilters() {
    const c = document.getElementById('wikiTagFilters');
    if (!c) return;
    let html = `<button class="wiki-fav-filter ${favOnlyFilter ? 'active' : ''}" id="wikiFavBtn"><i class="bi bi-heart-fill"></i> Mes favoris</button>`;
    tags.forEach(tag => {
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
    c.querySelector('#wikiFavBtn')?.addEventListener('click', () => {
        favOnlyFilter = !favOnlyFilter;
        renderTagFilters(); renderGrid();
    });
}

function renderGrid() {
    const grid = document.getElementById('wikiGrid');
    const empty = document.getElementById('wikiEmpty');
    if (!grid) return;

    const searchVal = (document.getElementById('feSearchInput')?.value || '').toLowerCase();
    let filtered = allPages;
    if (currentFilter) filtered = filtered.filter(p => p.categorie_id === currentFilter);
    if (currentTagFilter) filtered = filtered.filter(p => (p.tags || []).some(t => t.id === currentTagFilter));
    if (favOnlyFilter) filtered = filtered.filter(p => p.is_favori);
    if (searchVal) filtered = filtered.filter(p =>
        (p.titre || '').toLowerCase().includes(searchVal) ||
        (p.description || '').toLowerCase().includes(searchVal)
    );

    if (!filtered.length) { grid.innerHTML = ''; if (empty) empty.style.display = ''; return; }
    if (empty) empty.style.display = 'none';

    grid.innerHTML = '<div class="wiki-grid">' + filtered.map(p => {
        const catBadge = p.categorie_nom
            ? `<span class="wiki-card-cat" style="background:${p.categorie_couleur || '#6c757d'}"><i class="bi bi-${escapeHtml(p.categorie_icone || 'book')}"></i> ${escapeHtml(p.categorie_nom)}</span>`
            : '';
        const pin = p.epingle == 1 ? '<i class="bi bi-pin-angle-fill wiki-card-pin"></i>' : '';
        const date = p.updated_at ? new Date(p.updated_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric'}) : '';
        const tagsHtml = (p.tags || []).map(t => `<span class="wiki-tag" style="background:${t.couleur}">${escapeHtml(t.nom)}</span>`).join('');
        const favIcon = p.is_favori ? 'heart-fill' : 'heart';
        const favClass = p.is_favori ? 'active' : '';
        let verifyBadge = '';
        if (p.verify_next) {
            const expired = new Date(p.verify_next) <= new Date();
            verifyBadge = expired
                ? '<span class="wiki-verify-badge wiki-verify-expired"><i class="bi bi-exclamation-triangle"></i></span>'
                : '<span class="wiki-verify-badge wiki-verify-ok"><i class="bi bi-patch-check"></i></span>';
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
            </div>
        </div>`;
    }).join('') + '</div>';

    grid.querySelectorAll('.wiki-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('.wiki-fav-btn')) return;
            openPage(card.dataset.id);
        });
    });

    grid.querySelectorAll('.wiki-fav-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const pid = btn.dataset.favId;
            const res = await apiPost('toggle_wiki_favori', { page_id: pid });
            if (!res.success) return;
            const page = allPages.find(p => p.id === pid);
            if (page) page.is_favori = res.is_favori;
            renderGrid();
        });
    });
}

async function openPage(id) {
    const res = await apiPost('get_wiki_page', { id });
    if (!res.success) return;

    const p = res.page;
    const date = p.updated_at ? new Date(p.updated_at).toLocaleDateString('fr-CH', {day:'numeric',month:'long',year:'numeric'}) : '';
    const auteur = p.auteur_prenom ? `${p.auteur_prenom} ${p.auteur_nom}` : '';
    const catBadge = p.categorie_nom
        ? `<span class="wiki-card-cat" style="background:${p.categorie_couleur || '#6c757d'}"><i class="bi bi-${escapeHtml(p.categorie_icone || 'book')}"></i> ${escapeHtml(p.categorie_nom)}</span>`
        : '';
    const tagsHtml = (p.tags || []).map(t => `<span class="wiki-tag" style="background:${t.couleur}">${escapeHtml(t.nom)}</span>`).join('');
    const expertInfo = p.expert_prenom ? `<span class="wiki-expert-badge"><i class="bi bi-person-check"></i> Expert: ${escapeHtml(p.expert_prenom + ' ' + p.expert_nom)}</span>` : '';

    const heroImg = p.image_url ? `<img class="wiki-read-hero" src="${escapeHtml(p.image_url)}" alt="">` : '';

    // Destroy previous viewer
    if (currentViewer) { currentViewer.destroy(); currentViewer = null; }

    document.getElementById('wikiReadPanel').innerHTML = `
        ${heroImg}
        <div class="wiki-read-content-wrap">
            <h1>${escapeHtml(p.titre)}</h1>
            <div class="wiki-read-meta">
                ${catBadge} ${tagsHtml ? ` ${tagsHtml}` : ''}
                <span class="ms-2"><i class="bi bi-calendar3"></i> ${date}</span>
                ${auteur ? ` &middot; <i class="bi bi-person"></i> ${escapeHtml(auteur)}` : ''}
                ${expertInfo ? ` &middot; ${expertInfo}` : ''}
                &middot; v${p.version || 1}
            </div>
            <div class="wiki-read-content" id="wikiViewerMount"></div>
        </div>
    `;

    // Render with TipTap viewer
    const { createViewer } = await import('../rich-editor.js');
    const mount = document.getElementById('wikiViewerMount');
    if (p.contenu) {
        currentViewer = await createViewer(mount, p.contenu);
    } else {
        mount.innerHTML = '<p class="text-muted">Aucun contenu</p>';
    }

    document.getElementById('wikiCatFilters').style.display = 'none';
    document.getElementById('wikiTagFilters').style.display = 'none';
    document.getElementById('wikiGrid').style.display = 'none';
    document.getElementById('wikiEmpty').style.display = 'none';
    document.getElementById('wikiReadView').style.display = '';
}

function backToList() {
    if (currentViewer) { currentViewer.destroy(); currentViewer = null; }
    document.getElementById('wikiReadView').style.display = 'none';
    document.getElementById('wikiCatFilters').style.display = '';
    document.getElementById('wikiTagFilters').style.display = '';
    document.getElementById('wikiGrid').style.display = '';
    renderGrid();
}

export async function init() {
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr?.categories) categories = ssr.categories;

    // Load tags
    const tagRes = await apiPost('get_wiki_tags', {});
    if (tagRes.success) tags = tagRes.tags;

    renderCatFilters();
    renderTagFilters();

    // Load pages
    const res = await apiPost('get_wiki_pages', {});
    if (res.success) { allPages = res.pages; renderGrid(); }

    document.getElementById('wikiBackBtn')?.addEventListener('click', backToList);

    const searchInput = document.getElementById('feSearchInput');
    if (searchInput) {
        let timer;
        searchInput.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(renderGrid, 250); });
    }
}

export function destroy() {
    if (currentViewer) { currentViewer.destroy(); currentViewer = null; }
    categories = []; tags = []; allPages = [];
    currentFilter = ''; currentTagFilter = ''; favOnlyFilter = false;
    const searchInput = document.getElementById('feSearchInput');
    if (searchInput) searchInput.value = '';
}
