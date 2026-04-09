/**
 * Wiki / Base de connaissances — Employee SPA module (lecture seule)
 */
import { apiPost, escapeHtml } from '../helpers.js';

let categories = [];
let allPages = [];
let currentFilter = '';

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

function renderGrid() {
    const grid = document.getElementById('wikiGrid');
    const empty = document.getElementById('wikiEmpty');
    if (!grid) return;

    const searchVal = (document.getElementById('feSearchInput')?.value || '').toLowerCase();
    let filtered = allPages;
    if (currentFilter) filtered = filtered.filter(p => p.categorie_id === currentFilter);
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
        const auteur = p.auteur_prenom ? `${p.auteur_prenom} ${p.auteur_nom}` : '';
        return `<div class="wiki-card ${p.epingle == 1 ? 'pinned' : ''}" data-id="${p.id}">
            ${pin}
            <div class="wiki-card-title">${escapeHtml(p.titre)}</div>
            <div class="wiki-card-desc">${escapeHtml(p.description || '')}</div>
            <div class="wiki-card-meta">
                ${catBadge}
                <span><i class="bi bi-calendar3"></i> ${date}</span>
                ${auteur ? `<span><i class="bi bi-person"></i> ${escapeHtml(auteur)}</span>` : ''}
            </div>
        </div>`;
    }).join('') + '</div>';

    grid.querySelectorAll('.wiki-card').forEach(card => {
        card.addEventListener('click', () => openPage(card.dataset.id));
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

    document.getElementById('wikiReadPanel').innerHTML = `
        <h1>${escapeHtml(p.titre)}</h1>
        <div class="wiki-read-meta">
            ${catBadge}
            <span class="ms-2"><i class="bi bi-calendar3"></i> ${date}</span>
            ${auteur ? ` &middot; <i class="bi bi-person"></i> ${escapeHtml(auteur)}` : ''}
            &middot; v${p.version || 1}
        </div>
        <div class="wiki-read-content">${p.contenu || '<p class="text-muted">Aucun contenu</p>'}</div>
    `;

    document.getElementById('wikiCatFilters').style.display = 'none';
    document.getElementById('wikiGrid').style.display = 'none';
    document.getElementById('wikiEmpty').style.display = 'none';
    document.getElementById('wikiReadView').style.display = '';
}

function backToList() {
    document.getElementById('wikiReadView').style.display = 'none';
    document.getElementById('wikiCatFilters').style.display = '';
    document.getElementById('wikiGrid').style.display = '';
    renderGrid();
}

export async function init() {
    // SSR data
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr?.categories) categories = ssr.categories;

    renderCatFilters();

    // Load pages from API
    const res = await apiPost('get_wiki_pages', {});
    if (res.success) { allPages = res.pages; renderGrid(); }

    // Back button
    document.getElementById('wikiBackBtn')?.addEventListener('click', backToList);

    // Global search integration
    const searchInput = document.getElementById('feSearchInput');
    if (searchInput) {
        let timer;
        searchInput.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(renderGrid, 250); });
    }
}

export function destroy() {
    categories = [];
    allPages = [];
    currentFilter = '';
    const searchInput = document.getElementById('feSearchInput');
    if (searchInput) searchInput.value = '';
}
