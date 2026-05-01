/**
 * SpocSpace - SPA Router + Sidebar + Search
 */

const BASE = '/newspocspace';

// Save initial URL params before SPA routing replaces them
window.__SS_INITIAL_PARAMS__ = new URLSearchParams(window.location.search);

const moduleMap = {
    'home':       () => import('./modules/home.js'),
    'login':      () => import('./modules/auth.js'),
    'planning':   () => import('./modules/planning.js'),
    'desirs':     () => import('./modules/desirs.js'),
    'absences':   () => import('./modules/absences.js'),
    'vacances':   () => import('./modules/vacances.js'),
    'collegues':  () => import('./modules/collegues.js'),
    'emails':     () => import('./modules/emails.js'),
    'votes':      () => import('./modules/votes.js'),
    'pv':         () => import('./modules/pv.js'),
    'sondages':   () => import('./modules/sondages.js'),
    'documents':  () => import('./modules/documents.js'),
    'changements':() => import('./modules/changements.js'),
    'profile':    () => import('./modules/profile.js'),
    'apparence':  () => import('./modules/apparence.js'),
    'formations': () => import('./modules/formations.js'),
    'formation-detail-emp': () => import('./modules/formation-detail-emp.js'),
    'notifications': () => import('./modules/notifications.js'),
    'fiches-salaire': () => import('./modules/fiches-salaire.js'),
    'fiches-amelioration': () => import('./modules/fiches-amelioration.js'),
    'fiche-amelioration-new': () => import('./modules/fiche-amelioration-new.js'),
    'suggestions': () => import('./modules/suggestions.js'),
    'suggestion-new': () => import('./modules/suggestion-new.js'),
    'suggestion-detail': () => import('./modules/suggestion-detail.js'),
    'covoiturage': () => import('./modules/covoiturage.js'),
    'repartition': () => import('./modules/repartition.js'),
    'cuisine':    () => import('./modules/cuisine.js'),
    'cuisine-home':         () => import('./modules/cuisine-home.js'),
    'cuisine-menus':        () => import('./modules/cuisine-menus.js'),
    'cuisine-reservations': () => import('./modules/cuisine-reservations.js'),
    'cuisine-famille':      () => import('./modules/cuisine-famille.js'),
    'cuisine-vip':          () => import('./modules/cuisine-vip.js'),
    'mur':          () => import('./modules/mur.js'),
    'wiki':         () => import('./modules/wiki.js'),
    'annonces':     () => import('./modules/annonces.js'),
    'annuaire':     () => import('./modules/annuaire.js'),
    'evenements':   () => import('./modules/evenements.js'),
    'mes-stagiaires': () => import('./modules/mes-stagiaires.js'),
    'stagiaire-detail': () => import('./modules/stagiaire-detail.js'),
    'salles':       () => import('./modules/salles.js'),
    'mon-stage':    () => import('./modules/mon-stage.js'),
    'report-edit':  () => import('./modules/report-edit.js'),
};

let currentModule = null;
let currentPage = null;

/* ── Page loading ── */

async function loadPage(pageId, params = {}) {
    const content = document.getElementById('app-content');
    if (!content) return;

    if (!window.__SS__?.user && pageId !== 'login') {
        pageId = 'login';
        history.replaceState({}, '', `${BASE}/login`);
    }

    // External employees: home → cuisine-home
    if (pageId === 'home' && window.__SS__?.user?.type_employe === 'externe') {
        pageId = 'cuisine-home';
        history.replaceState({}, '', `${BASE}/cuisine-home`);
    }

    document.body.classList.toggle('no-nav', pageId === 'login');

    if (currentModule?.destroy) {
        try { currentModule.destroy(); } catch (e) { console.warn('destroy error', e); }
    }
    currentModule = null;

    content.innerHTML = '<div class="page-loading"><span class="spinner"></span></div>';

    try {
        // Forward query params + slug to PHP fragment for SSR
        const qs = new URLSearchParams(window.location.search);
        qs.set('v', Date.now());
        if (params.slug) qs.set('slug', params.slug);
        const res = await fetch(`${BASE}/pages/${pageId}.php?${qs.toString()}`);
        if (!res.ok) {
            content.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>Page introuvable</p></div>';
            return;
        }
        content.innerHTML = await res.text();
        // Extract SSR data injected by PHP pages
        const ssrEl = content.querySelector('script[type="application/json"][id="__ss_ssr__"]');
        window.__SS_PAGE_DATA__ = ssrEl ? JSON.parse(ssrEl.textContent) : null;
    } catch (e) {
        content.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off"></i><p>Erreur de chargement</p></div>';
        return;
    }

    const moduleLoader = moduleMap[pageId];
    if (moduleLoader) {
        try {
            const mod = await moduleLoader();
            currentModule = mod;
            if (mod.init) await mod.init(pageId, params);
        } catch (e) {
            console.error('Module error:', pageId, e);
        }
    }

    currentPage = pageId;
    window.scrollTo({ top: 0, behavior: 'instant' });
    updateNavActive(pageId);
    updateTopbarTitle(pageId);
    updateSearchPlaceholder(pageId);
    closeMobileSidebar();
}

/* ── Nav active state ── */

function updateNavActive(pageId) {
    document.querySelectorAll('.fe-sidebar-link[data-link]').forEach(a => {
        a.classList.toggle('active', a.getAttribute('data-link') === pageId);
    });
}

/* ── Topbar title ── */

const searchPlaceholders = {
    'cuisine-vip': '@nom ou @chambre pour chercher un résident...',
    'cuisine-famille': '@nom ou @chambre pour chercher un résident...',
    'cuisine-reservations': 'Chercher un collaborateur...',
    'cuisine-menus': 'Chercher un menu, une page...',
};

function updateSearchPlaceholder(pageId) {
    const input = document.getElementById('feSearchInput');
    if (!input) return;
    input.placeholder = searchPlaceholders[pageId] || 'Rechercher (collègues, pages…)';
}

function updateTopbarTitle(pageId) {
    const el = document.getElementById('feTopbarTitle');
    if (!el) return;
    const labels = window.__SS__?.pageLabels || {};
    el.textContent = labels[pageId] || pageId;
}

/* ── Routing ── */

function parseRoute() {
    let path = window.location.pathname;
    if (path.startsWith(BASE)) path = path.slice(BASE.length);
    path = path.replace(/^\/+|\/+$/g, '');

    const parts = path.split('/').filter(Boolean);
    let pageId = parts[0] || 'home';

    // External employees: redirect home → cuisine-home (dashboard cuisine)
    if (pageId === 'home' && window.__SS__?.user?.type_employe === 'externe') {
        pageId = 'cuisine-home';
    }
    // Stagiaires: default to mon-stage
    if (pageId === 'home' && window.__SS__?.user?.role === 'stagiaire') {
        pageId = 'mon-stage';
    }
    const params = {};
    if (parts.length > 1) params.slug = parts[1];

    const searchParams = new URLSearchParams(window.location.search);
    for (const [k, v] of searchParams) params[k] = v;

    return { pageId, params };
}

function navigateTo(pageId, slug) {
    const url = slug ? `${BASE}/${pageId}/${slug}` : `${BASE}/${pageId}`;
    history.pushState({}, '', url);
    loadPage(pageId, slug ? { slug } : {});
}

function handleRoute() {
    const { pageId, params } = parseRoute();
    loadPage(pageId, params);
}

/* ── SPA link clicks ── */

function setupLinks() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-link]');
        if (!link) return;
        e.preventDefault();
        if (link.dataset.disabled === '1') return;
        const pageId = link.getAttribute('data-link');
        const slug = link.getAttribute('data-slug') || '';
        navigateTo(pageId, slug);
    });
}

/* ── Sidebar toggle (desktop mini/full) ── */

const SIDEBAR_KEY = 'ss_fe_sidebar_mini';
const CAT_KEY = 'ss_fe_sidebar_cats';

function setupSidebar() {
    const sidebar = document.getElementById('feSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileBtn = document.getElementById('mobileToggle');
    const avatarBtn = document.getElementById('avatarToggleBtn');

    toggleBtn?.addEventListener('click', () => {
        closeMobileSidebar();
    });

    avatarBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = sidebar?.classList.toggle('open');
        if (isOpen) {
            overlay?.classList.add('show');
        } else {
            overlay?.classList.remove('show');
        }
    });

    // Mobile hamburger (if still visible)
    mobileBtn?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
    });

    overlay?.addEventListener('click', closeMobileSidebar);

    // Close sidebar when clicking outside (topbar, content)
    document.addEventListener('click', (e) => {
        if (!sidebar?.classList.contains('open')) return;
        if (e.target.closest('#feSidebar') || e.target.closest('#avatarToggleBtn') || e.target.closest('#mobileToggle')) return;
        closeMobileSidebar();
    });

    // Category collapse / expand
    const savedCats = JSON.parse(localStorage.getItem(CAT_KEY) || '{}');

    document.querySelectorAll('[data-cat-toggle]').forEach(catEl => {
        const catId = catEl.dataset.catToggle;
        const body = document.querySelector(`[data-cat-body="${catId}"]`);
        if (!body) return;

        const hasActive = body.querySelector('.fe-sidebar-link.active');
        if (savedCats[catId] && !hasActive) {
            body.classList.add('collapsed');
            catEl.classList.add('cat-collapsed');
        }

        catEl.addEventListener('click', () => {
            const isCollapsed = body.classList.toggle('collapsed');
            catEl.classList.toggle('cat-collapsed', isCollapsed);
            const all = JSON.parse(localStorage.getItem(CAT_KEY) || '{}');
            all[catId] = isCollapsed;
            localStorage.setItem(CAT_KEY, JSON.stringify(all));
        });
    });
}

function closeMobileSidebar() {
    document.getElementById('feSidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('show');
}

/* ── Logout ── */

function setupLogout() {
    const doLogout = async () => {
        const { apiPost } = await import('./helpers.js');
        await apiPost('logout');
        window.__SS__.user = null;
        window.location.href = `${BASE}/login`;
    };

    document.getElementById('logoutBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        doLogout();
    });
}

/* ── Global Search (Spocspace) ── */

const SS_GS_GROUP_LABELS_FE = {
    collegue: 'Collègues', wiki: 'Wiki', annonce: 'Annonces',
    document: 'Documents', contact: 'Annuaire', page: 'Pages',
};
const SS_GS_TYPE_ICON = {
    wiki: 'book', document: 'file-text', annonce: 'megaphone',
    contact: 'phone', page: 'arrow-right',
};

let searchTimer = null;

function ssGsSvg(name, size) {
    size = size || 16;
    const a = `width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"`;
    switch (name) {
        case 'search':       return `<svg ${a}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>`;
        case 'clock':        return `<svg ${a}><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
        case 'x':            return `<svg ${a}><path d="M6 6l12 12M18 6L6 18"/></svg>`;
        case 'calendar':     return `<svg ${a}><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>`;
        case 'users':        return `<svg ${a}><circle cx="9" cy="7" r="4"/><path d="M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/></svg>`;
        case 'file-text':    return `<svg ${a}><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>`;
        case 'megaphone':    return `<svg ${a}><path d="M3 11l18-5v12L3 14"/><path d="M11.6 16.8a3 3 0 11-5.8-1.6"/></svg>`;
        case 'book':         return `<svg ${a}><path d="M4 19.5v-15A2.5 2.5 0 016.5 2H20v20H6.5a2.5 2.5 0 010-5H20"/></svg>`;
        case 'phone':        return `<svg ${a}><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>`;
        case 'video':        return `<svg ${a}><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>`;
        case 'arrow-right':  return `<svg ${a}><path d="M5 12h14M13 5l7 7-7 7"/></svg>`;
        case 'search-empty': return `<svg ${a}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M11 8v3M11 14h.01"/></svg>`;
        default: return `<svg ${a}><circle cx="12" cy="12" r="10"/></svg>`;
    }
}

function ssGsAvatarVar(id) {
    const s = String(id || '');
    let h = 0;
    for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
    return Math.abs(h) % 5 + 1;
}

function ssGsInitials(prenom, nom) {
    const a = ((prenom || '').trim()[0] || '');
    const b = ((nom || '').trim()[0] || '');
    return (a + b).toUpperCase() || '?';
}

function ssGsHighlight(text, q) {
    const safe = escapeHtml(text || '');
    if (!q) return safe;
    const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return safe.replace(re, '<mark>$1</mark>');
}

function setupSearch() {
    const wrap = document.getElementById('feTopbarSearch');
    const bar = document.getElementById('feSearchBar');
    const input = document.getElementById('feSearchInput');
    const panel = document.getElementById('feSearchResults');
    const clearBtn = document.getElementById('feSearchClear');
    if (!input || !panel) return;

    const HIST_KEY = 'spocspace:search-history';
    const TTL_MS = 2 * 24 * 60 * 60 * 1000;
    const now = () => Date.now();

    function loadHist() { try { const a = JSON.parse(localStorage.getItem(HIST_KEY) || '[]'); return a.filter(it => it && it.q && (it.ts||0) >= now() - TTL_MS); } catch { return []; } }
    function saveHist(arr) { const seen = new Set(); const c = arr.filter(it => { const k = (it.q||'').toLowerCase(); if (!k||seen.has(k)) return false; seen.add(k); return true; }).slice(0,30); try { localStorage.setItem(HIST_KEY, JSON.stringify(c)); } catch {} }
    function addHist(q) { q=(q||'').trim(); if(!q||q.length<2) return; const h=loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase()); h.unshift({q,ts:now()}); saveHist(h); }
    function delHist(q) { saveHist(loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase())); }

    function openPanel() { panel.classList.add('show'); }
    function closePanel() { panel.classList.remove('show'); }
    function updateBar() { if (input.value.length > 0) bar.classList.add('has-value'); else bar.classList.remove('has-value'); }

    function frame(inner) {
        return `
          <div class="ss-gs-scroll flex-1 overflow-y-auto py-1.5">${inner}</div>
          <div class="border-t border-line bg-surface-2 px-4 py-2 flex items-center justify-between gap-3 shrink-0 text-[11.5px] text-muted">
            <div class="flex items-center gap-3.5">
              <span class="hidden sm:inline-flex items-center gap-1 font-mono text-[10.5px]">
                <kbd class="bg-surface border border-line-2 rounded px-1.5 py-px text-[10px] font-semibold text-ink-2">↑</kbd>
                <kbd class="bg-surface border border-line-2 rounded px-1.5 py-px text-[10px] font-semibold text-ink-2">↓</kbd>
                naviguer
              </span>
              <span class="hidden sm:inline-flex items-center gap-1 font-mono text-[10.5px]">
                <kbd class="bg-surface border border-line-2 rounded px-1.5 py-px text-[10px] font-semibold text-ink-2">↵</kbd>
                ouvrir
              </span>
              <span class="inline-flex items-center gap-1 font-mono text-[10.5px]">
                <kbd class="bg-surface border border-line-2 rounded px-1.5 py-px text-[10px] font-semibold text-ink-2">esc</kbd>
                fermer
              </span>
            </div>
            <button type="button" class="ss-gs-advanced inline-flex items-center gap-1 text-teal-700 font-semibold text-[11.5px] hover:text-teal-600 transition-colors">
              Recherche avancée ${ssGsSvg('arrow-right', 11)}
            </button>
          </div>`;
    }

    function renderInitial(filter) {
        const hist = loadHist().sort((a,b)=>b.ts-a.ts);
        const f = (filter||'').toLowerCase();
        const filteredHist = f ? hist.filter(it=>it.q.toLowerCase().includes(f)) : hist;

        const shortcuts = [
            { label: 'Mon planning', page: 'planning',   icon: 'calendar' },
            { label: 'Collègues',    page: 'collegues',  icon: 'users' },
            { label: 'Documents',    page: 'documents',  icon: 'file-text' },
            { label: 'Annonces',     page: 'annonces',   icon: 'megaphone' },
        ];

        let html = `
          <div class="px-4 pt-2 pb-1.5 flex items-center justify-between">
            <span class="font-mono text-[10px] font-semibold text-muted tracking-[0.16em] uppercase">Accès rapides</span>
          </div>
          <div class="px-3 pb-2 grid grid-cols-2 gap-1.5">
            ${shortcuts.map(s => `
              <button type="button" class="ss-gs-shortcut group/sc flex items-center gap-2.5 px-2.5 py-2 bg-surface-2 border border-line rounded-lg text-[12.5px] text-ink-2 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition" data-shortcut-page="${s.page}">
                <span class="w-6 h-6 rounded-md bg-teal-50 group-hover/sc:bg-teal-100 text-teal-700 grid place-items-center shrink-0 transition-colors">${ssGsSvg(s.icon, 13)}</span>
                <span>${s.label}</span>
              </button>
            `).join('')}
          </div>`;

        if (filteredHist.length) {
            const recent = filteredHist.slice(0, 5);
            html += `
              <div class="border-t border-line mt-1 pt-2 px-4 pb-1.5">
                <span class="font-mono text-[10px] font-semibold text-muted tracking-[0.16em] uppercase">Recherches récentes</span>
              </div>
              <div class="pb-1">
                ${recent.map(it => `
                  <div class="ss-gs-item ss-gs-hist flex items-center gap-3 px-4 py-2 cursor-pointer hover:bg-teal-50 transition-colors" data-hist-q="${escapeHtml(it.q)}">
                    <span class="w-9 h-9 rounded-[10px] bg-surface-3 text-muted grid place-items-center shrink-0">${ssGsSvg('clock', 14)}</span>
                    <span class="flex-1 text-[13.5px] text-ink-2 truncate">${escapeHtml(it.q)}</span>
                    <button type="button" class="ss-gs-hist-del w-6 h-6 rounded-md text-muted hover:bg-surface-3 hover:text-danger grid place-items-center shrink-0 transition-colors" data-del-q="${escapeHtml(it.q)}" aria-label="Supprimer">${ssGsSvg('x', 12)}</button>
                  </div>
                `).join('')}
              </div>`;
        }

        panel.innerHTML = frame(html);
    }

    function renderEmpty() {
        panel.innerHTML = frame(`
          <div class="py-10 px-6 text-center">
            <div class="w-12 h-12 mx-auto mb-3 bg-surface-3 rounded-2xl grid place-items-center text-muted">${ssGsSvg('search-empty', 22)}</div>
            <h3 class="font-display text-[15px] font-semibold text-ink mb-1 -tracking-[0.01em]">Aucun résultat trouvé</h3>
            <p class="text-[12.5px] text-muted">Essayez d'autres mots-clés ou vérifiez l'orthographe.</p>
          </div>`);
    }

    function renderItem(r, q) {
        const type = r.type;
        const titleHtml = ssGsHighlight(r.title, q);

        let iconHtml;
        if (type === 'collegue') {
            const variant = ssGsAvatarVar(r.id);
            const init = ssGsInitials(r.prenom, r.nom);
            const status = r.is_online ? '<span class="ss-gs-status"></span>' : '';
            if (r.photo) {
                iconHtml = `<div class="relative w-9 h-9 rounded-[10px] overflow-hidden shrink-0 shadow-sp-sm">
                    <img src="${escapeHtml(r.photo)}" alt="" class="w-full h-full object-cover" />${status}
                  </div>`;
            } else {
                iconHtml = `<div class="relative ss-gs-av-${variant} w-9 h-9 rounded-[10px] grid place-items-center shrink-0 font-display font-semibold text-sm text-white -tracking-[0.02em] shadow-sp-sm">
                    ${escapeHtml(init)}${status}
                  </div>`;
            }
        } else {
            const knownIcons = ['wiki','document','annonce','resident','contact','page'];
            const iconClass = knownIcons.includes(type) ? `ss-gs-icon-${type}` : 'ss-gs-icon-default';
            const iconName = SS_GS_TYPE_ICON[type] || 'arrow-right';
            iconHtml = `<div class="${iconClass} w-9 h-9 rounded-[10px] grid place-items-center shrink-0 text-white shadow-sp-sm">${ssGsSvg(iconName, 16)}</div>`;
        }

        let extraRight = '';
        if (type === 'collegue' && r.is_online) {
            extraRight = `<div class="hidden group-hover:flex items-center gap-1 shrink-0 ml-1">
                <button type="button" class="w-7 h-7 rounded-md bg-teal-50 text-teal-700 hover:bg-teal-100 grid place-items-center transition-colors" title="Appel audio" data-call-audio="${escapeHtml(r.id)}">${ssGsSvg('phone', 13)}</button>
                <button type="button" class="w-7 h-7 rounded-md bg-info-bg text-info hover:opacity-80 grid place-items-center transition-colors" title="Appel vidéo" data-call-video="${escapeHtml(r.id)}">${ssGsSvg('video', 13)}</button>
              </div>`;
        }

        const subtitle = r.subtitle ? `<div class="text-[11.5px] text-muted truncate">${escapeHtml(r.subtitle)}</div>` : '';

        return `
          <button type="button" class="ss-gs-item ss-gs-result group flex items-center gap-3 w-full text-left px-4 py-2 cursor-pointer hover:bg-teal-50 transition-colors"
                  data-page="${escapeHtml(r.page||'')}" data-id="${escapeHtml(r.id||'')}" data-type="${escapeHtml(type)}"
                  data-user-photo="${escapeHtml(r.photo||'')}" data-user-prenom="${escapeHtml(r.prenom||'')}" data-user-nom="${escapeHtml(r.nom||'')}">
            ${iconHtml}
            <div class="flex-1 min-w-0">
              <div class="text-[13.5px] font-semibold text-ink truncate">${titleHtml}</div>
              ${subtitle}
            </div>
            ${extraRight}
          </button>`;
    }

    function renderResults(results, q) {
        if (!results.length) { renderEmpty(); return; }
        const groups = {};
        results.forEach(r => { if (!groups[r.type]) groups[r.type]=[]; groups[r.type].push(r); });

        const order = ['collegue','wiki','annonce','document','contact','page'];
        const sorted = Object.entries(groups).sort((a,b) => {
            const ia = order.indexOf(a[0]); const ib = order.indexOf(b[0]);
            return (ia<0?99:ia) - (ib<0?99:ib);
        });

        let html = '';
        sorted.forEach(([type, items], idx) => {
            const label = SS_GS_GROUP_LABELS_FE[type] || type;
            html += `
              <div class="${idx > 0 ? 'border-t border-line' : ''} py-1.5">
                <div class="px-4 pt-2 pb-1.5 flex items-center justify-between">
                  <span class="font-mono text-[10px] font-semibold text-muted tracking-[0.16em] uppercase">${escapeHtml(label)}</span>
                  <span class="font-mono text-[10px] font-semibold text-teal-700 bg-teal-50 px-1.5 py-px rounded-full">${items.length}</span>
                </div>
                <div>${items.map(r => renderItem(r, q)).join('')}</div>
              </div>`;
        });

        panel.innerHTML = frame(html);
    }

    function clearActive() { panel.querySelectorAll('.ss-gs-item.bg-teal-50').forEach(el => el.classList.remove('bg-teal-50')); panel.querySelectorAll('.ss-gs-item.active').forEach(el => el.classList.remove('active')); }
    function setActive(el) {
        if (!el) return;
        clearActive();
        el.classList.add('active', 'bg-teal-50');
        el.scrollIntoView({ block: 'nearest' });
    }

    async function doSearch(q) {
        if (q.length < 2) { renderInitial(q); return; }
        try {
            const { apiPost } = await import('./helpers.js');
            const res = await apiPost('global_search', { q });
            if (res.success) renderResults(res.results, q);
            else renderEmpty();
        } catch { renderInitial(q); }
    }

    input.addEventListener('focus', () => {
        if (!input.value) renderInitial('');
        openPanel();
    });

    input.addEventListener('input', () => {
        updateBar();
        clearTimeout(searchTimer);
        const v = input.value.trim();
        if (v.length < 2) { renderInitial(v); openPanel(); return; }
        searchTimer = setTimeout(() => doSearch(v), 300);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePanel(); input.blur(); return; }
        if (e.key === 'Enter') {
            const active = panel.querySelector('.ss-gs-item.active');
            if (active) { e.preventDefault(); active.click(); return; }
            if (input.value.trim().length >= 2) { addHist(input.value.trim()); doSearch(input.value.trim()); }
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = Array.from(panel.querySelectorAll('.ss-gs-item'));
            if (!items.length) return;
            const cur = items.findIndex(el => el.classList.contains('active'));
            const next = e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur <= 0 ? items.length - 1 : cur - 1);
            setActive(items[next]);
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => { input.value = ''; updateBar(); renderInitial(''); input.focus(); });
    }

    panel.addEventListener('click', async (e) => {
        const callAudio = e.target.closest('[data-call-audio]');
        const callVideo = e.target.closest('[data-call-video]');
        if (callAudio || callVideo) {
            e.preventDefault(); e.stopPropagation();
            const btn = callAudio || callVideo;
            const item = btn.closest('.ss-gs-result');
            if (!item) return;
            const user = { id: item.dataset.id, prenom: item.dataset.userPrenom, nom: item.dataset.userNom, photo: item.dataset.userPhoto };
            closePanel();
            try { const m = await import('./call-ui.js'); m.startCall(user, callAudio ? 'audio' : 'video'); } catch (err) { console.error(err); }
            return;
        }

        const del = e.target.closest('.ss-gs-hist-del');
        if (del) { e.stopPropagation(); delHist(del.dataset.delQ); renderInitial(input.value); return; }

        const hist = e.target.closest('.ss-gs-hist');
        if (hist) { input.value = hist.dataset.histQ; updateBar(); doSearch(hist.dataset.histQ); return; }

        const shortcut = e.target.closest('.ss-gs-shortcut');
        if (shortcut) { e.preventDefault(); const page = shortcut.dataset.shortcutPage; if (page) { closePanel(); navigateTo(page); } return; }

        const result = e.target.closest('.ss-gs-result');
        if (result) {
            addHist(input.value.trim());
            closePanel();
            const page = result.dataset.page;
            const id = result.dataset.id;
            if (!page) return;

            if ((page === 'documents' || page === 'annuaire') && id) {
                const url = `${BASE}/${page}?highlight=${encodeURIComponent(id)}`;
                history.pushState({}, '', url);
                loadPage(page, { highlight: id });
                return;
            }
            if (id) { loadPage(page, { id }); return; }
            navigateTo(page);
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            const ae = document.activeElement;
            if (ae && ae !== input && /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName) && !ae.readOnly) return;
            e.preventDefault();
            input.focus();
            input.select();
        }
    });

    document.addEventListener('click', (e) => { if (!e.target.closest('#feTopbarSearch')) closePanel(); });
}

// ── Annonce modal from global search ──
async function openAnnonceModal(id) {
    const { apiPost } = await import('./helpers.js');
    const res = await apiPost('get_annonce_detail', { id });
    if (!res.success || !res.annonce) return;
    const a = res.annonce;

    document.getElementById('ssAnnonceModal')?.remove();
    const catIcons = {direction:'building',rh:'person-badge',vie_sociale:'balloon-heart',cuisine:'egg-fried',protocoles:'heart-pulse',securite:'shield-check',divers:'info-circle'};
    const icon = catIcons[a.categorie] || 'megaphone';
    const auteur = [a.auteur_prenom, a.auteur_nom].filter(Boolean).join(' ') || 'Administration';
    const date = new Date(a.created_at).toLocaleDateString('fr-CH', { day:'numeric', month:'long', year:'numeric' });

    const overlay = document.createElement('div');
    overlay.id = 'ssAnnonceModal';
    overlay.className = 'ss-alert-overlay';
    overlay.innerHTML = `
        <div class="ss-alert-modal" style="max-width:640px">
            <div class="ss-alert-header">
                <div class="ss-alert-header-icon"><i class="bi bi-${escapeHtml(icon)}"></i></div>
                <div style="flex:1;min-width:0">
                    <h5 class="ss-alert-title">${escapeHtml(a.titre)}</h5>
                    <span class="ss-alert-meta">${escapeHtml(auteur)} · ${date}</span>
                </div>
            </div>
            <div class="ss-alert-content" style="max-height:60vh;overflow-y:auto">
                ${a.description ? '<div style="font-size:.9rem;line-height:1.65;color:#374151">' + a.description + '</div>' : ''}
            </div>
            <div class="ss-alert-footer">
                <button class="ss-alert-btn" id="ssAnnonceClose"><i class="bi bi-check-lg"></i> Fermer</button>
            </div>
        </div>`;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    function close() {
        overlay.classList.remove('show');
        setTimeout(() => overlay.remove(), 300);
    }
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    overlay.querySelector('#ssAnnonceClose').addEventListener('click', close);
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

/* ── Init ── */

/* ── Temp password banner ── */
function showTempPasswordBanner() {
    const expires = window.__SS__.tempPasswordExpires;
    if (!expires) return;

    const banner = document.createElement('div');
    banner.id = 'tempPwdBanner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#9B2C2C;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:12px;font-size:.88rem;box-shadow:0 2px 8px rgba(0,0,0,.2);';
    banner.innerHTML = `
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>Vous utilisez un mot de passe temporaire. Changez-le avant <strong id="tempPwdCountdown"></strong></span>
        <a href="#" id="tempPwdLink" style="color:#fff;background:rgba(255,255,255,.2);padding:4px 14px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.82rem;white-space:nowrap;">
            <i class="bi bi-key"></i> Modifier maintenant
        </a>
    `;
    document.body.prepend(banner);

    // Shift content down
    document.body.style.paddingTop = banner.offsetHeight + 'px';

    // Link to profile
    banner.querySelector('#tempPwdLink').addEventListener('click', (e) => {
        e.preventDefault();
        navigateTo('profile');
    });

    // Countdown
    const expiresDate = new Date(expires.replace(' ', 'T'));
    function updateCountdown() {
        const now = new Date();
        const diff = expiresDate - now;
        const el = document.getElementById('tempPwdCountdown');
        if (!el) return;
        if (diff <= 0) {
            el.textContent = '(expiré)';
            return;
        }
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        el.textContent = `${h}h ${m.toString().padStart(2, '0')}min`;
    }
    updateCountdown();
    setInterval(updateCountdown, 30000);
}

async function init() {
    // ── Offline boot: restore user session from IndexedDB if shell came empty ──
    if (!window.__SS__?.user && !navigator.onLine) {
        try {
            const { getAuthToken, getShellData } = await import('./ss-db.js');
            const token = await getAuthToken();
            const shell = await getShellData();
            if (token && shell) {
                window.__SS__ = window.__SS__ || {};
                window.__SS__.user = {
                    id: token.userId, prenom: token.prenom, nom: token.nom,
                    email: token.email, role: token.role, taux: token.taux,
                    fonction_id: token.fonction_id, type_employe: token.type_employe,
                };
                window.__SS__.csrfToken = shell.csrfToken || '';
                window.__SS__.canChangement = shell.canChangement || false;
                window.__SS__.deniedPerms = shell.deniedPerms || [];
                window.__SS__.pageLabels = shell.pageLabels || {};
            }
        } catch (e) { /* IndexedDB not available */ }
    }

    setupLinks();
    setupSidebar();
    setupLogout();
    setupSearch();
    handleRoute();
    window.addEventListener('popstate', handleRoute);
    window.__trNavigate = navigateTo;

    // Poll notification badge + check alerts + offline support
    if (window.__SS__?.user) {
        pollNotifBadge();
        setInterval(() => {
            if (document.visibilityState === 'visible') pollNotifBadge();
        }, 60000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') pollNotifBadge();
        });
        checkPendingAlerts();
        import('./modules/offline.js').then(m => m.initOffline()).catch(e => console.error('[offline] init failed:', e));

        // Persist shell data + auth token for offline boot (refresh on every load)
        if (navigator.onLine) {
            import('./ss-db.js').then(async (db) => {
                try {
                    const u = window.__SS__.user;
                    await db.saveAuthToken(u.id, {
                        email: u.email, role: u.role, prenom: u.prenom, nom: u.nom,
                        taux: u.taux, fonction_id: u.fonction_id, type_employe: u.type_employe, photo: u.photo,
                    });
                    await db.saveShellData({
                        csrfToken: window.__SS__.csrfToken,
                        canChangement: window.__SS__.canChangement,
                        deniedPerms: window.__SS__.deniedPerms,
                        pageLabels: window.__SS__.pageLabels,
                    });
                } catch (e) { /* silent */ }
            }).catch(() => {});
        }

        // Connection indicator updates
        function updateConnIndicator() {
            const dot = document.querySelector('#feConnStatus .fe-conn-dot');
            const status = document.getElementById('feConnStatus');
            if (!dot || !status) return;
            if (navigator.onLine) {
                dot.className = 'fe-conn-dot fe-conn-online';
                status.title = 'En ligne';
            } else {
                dot.className = 'fe-conn-dot fe-conn-offline';
                status.title = 'Hors ligne';
            }
        }
        window.addEventListener('online', updateConnIndicator);
        window.addEventListener('offline', updateConnIndicator);
        updateConnIndicator();

        // Update pending queue count periodically
        setInterval(async () => {
            try {
                const { getQueueCount } = await import('./modules/offline.js');
                const count = await getQueueCount();
                const badge = document.getElementById('feConnPending');
                if (badge) {
                    if (count > 0) { badge.textContent = count; badge.style.display = ''; }
                    else { badge.style.display = 'none'; }
                }
            } catch {}
        }, 10000);
        initFullscreen();
        if (window.__SS__.mustChangePassword) showTempPasswordBanner();

        // Auto-lock screen after 15 min inactivity
        import('./lockscreen.js').then(m => m.initLockScreen()).catch(() => {});

        // WebRTC call polling for incoming calls
        import('./call-ui.js').then(m => m.initIncomingPoll()).catch(() => {});
    }
}

/* ── Fullscreen persistant ── */
function initFullscreen() {
    const btn = document.getElementById('fullscreenToggle');
    if (!btn) return;

    function updateIcon() {
        const icon = btn.querySelector('i');
        if (document.fullscreenElement) {
            icon.className = 'bi bi-fullscreen-exit';
            btn.title = 'Quitter le plein écran';
        } else {
            icon.className = 'bi bi-arrows-fullscreen';
            btn.title = 'Plein écran';
        }
    }

    btn.addEventListener('click', () => {
        if (document.fullscreenElement) {
            sessionStorage.removeItem('ss_fullscreen');
            document.exitFullscreen().catch(() => {});
        } else {
            sessionStorage.setItem('ss_fullscreen', '1');
            document.documentElement.requestFullscreen().catch(() => {});
        }
    });

    document.addEventListener('fullscreenchange', () => {
        updateIcon();
        // Si l'utilisateur quitte via Escape, on désactive le mode persistant
        if (!document.fullscreenElement) {
            sessionStorage.removeItem('ss_fullscreen');
        }
    });

    // Restaurer le fullscreen si actif en session (changement de page SPA)
    if (sessionStorage.getItem('ss_fullscreen') === '1' && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {
            sessionStorage.removeItem('ss_fullscreen');
        });
    }

    updateIcon();
}

async function pollNotifBadge() {
    try {
        const { apiPost } = await import('./helpers.js');
        const res = await apiPost('get_poll_data');
        if (!res.success) return;

        // Notification badge
        const notifBadge = document.querySelector('.fe-topbar-notif');
        if (notifBadge) {
            if (res.unread_notifs > 0) { notifBadge.textContent = res.unread_notifs; notifBadge.style.display = ''; }
            else { notifBadge.style.display = 'none'; }
        }

        // Messages badge
        const setBadge = (el, count) => {
            if (!el) return;
            if (count > 0) { el.textContent = count; el.style.display = ''; }
            else { el.style.display = 'none'; }
        };
        setBadge(document.getElementById('msgBadge'), res.unread_messages);
        setBadge(document.getElementById('msgBadgeSidebar'), res.unread_messages);

        // Annonces badge (dot only, no number)
        const annBadge = document.getElementById('annBadgeSidebar');
        if (annBadge) annBadge.style.display = res.pending_ack > 0 ? '' : 'none';

        // Live alert toasts
        if (res.alerts?.length) {
            for (const alert of res.alerts) {
                if (_seenAlertIds.has(alert.id)) continue;
                _seenAlertIds.add(alert.id);
                const type = alert.priority === 'haute' ? 'danger' : 'info';
                showAlertToast(alert.title, alert.message, type, 8000);
            }
        }
    } catch (e) { /* silent */ }
}

/* ── Pending alerts modal ── */
async function checkPendingAlerts() {
    try {
        const { apiPost } = await import('./helpers.js');
        const res = await apiPost('get_pending_alerts');
        if (!res.success || !res.alerts?.length) return;

        // Show alerts one at a time, highest priority first
        for (const alert of res.alerts) {
            await showAlertModal(alert);
            await apiPost('mark_alert_read', { alert_id: alert.id });
        }
    } catch (e) { /* silent */ }
}

function showAlertModal(alert) {
    return new Promise(resolve => {
        const isHaute = alert.priority === 'haute';
        const overlay = document.createElement('div');
        overlay.className = 'ss-alert-overlay';
        overlay.innerHTML = `
            <div class="ss-alert-modal ${isHaute ? 'ss-alert-haute' : ''}">
                <div class="ss-alert-header">
                    <div class="ss-alert-header-icon ${isHaute ? 'ss-alert-header-icon--danger' : ''}">
                        <i class="bi ${isHaute ? 'bi-exclamation-triangle-fill' : 'bi-megaphone-fill'}"></i>
                    </div>
                    <div>
                        <h5 class="ss-alert-title">${escapeHtml(alert.title)}</h5>
                        <span class="ss-alert-meta">${escapeHtml(alert.creator_prenom + ' ' + alert.creator_nom)} · ${new Date(alert.created_at).toLocaleDateString('fr-FR')}</span>
                    </div>
                </div>
                <div class="ss-alert-content">
                    <div class="ss-alert-message ${isHaute ? 'ss-alert-message--danger' : ''}">
                        <i class="bi ${isHaute ? 'bi-exclamation-circle' : 'bi-info-circle'} ss-alert-message-icon"></i>
                        <div class="ss-alert-message-text">${alert.message}</div>
                    </div>
                </div>
                <div class="ss-alert-footer">
                    <button class="ss-alert-btn ${isHaute ? 'ss-alert-btn-danger' : ''}" disabled>
                        <i class="bi bi-lock"></i> Lire le message complet
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('show'));

        const btn = overlay.querySelector('.ss-alert-btn');
        const content = overlay.querySelector('.ss-alert-content');
        const btnTextReady = `<i class="bi bi-check-lg"></i> J'ai pris connaissance`;
        const btnTextLocked = `<i class="bi bi-lock"></i> Lire le message complet`;

        function checkScroll() {
            // Content is short enough (no overflow) or scrolled to bottom
            const isShort = content.scrollHeight <= content.clientHeight + 5;
            const isBottom = content.scrollTop + content.clientHeight >= content.scrollHeight - 10;
            if (isShort || isBottom) {
                btn.disabled = false;
                btn.innerHTML = btnTextReady;
                content.removeEventListener('scroll', checkScroll);
            }
        }

        content.addEventListener('scroll', checkScroll);
        // Check immediately (short content = unlock right away)
        setTimeout(checkScroll, 100);

        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            overlay.classList.remove('show');
            setTimeout(() => { overlay.remove(); resolve(); }, 300);
        });
    });
}

/* ── Live alert toasts ── */
let _seenAlertIds = new Set();

function showAlertToast(title, message, type = 'info', duration = 6000) {
    // Ensure container exists
    let wrap = document.getElementById('ssAlertToastWrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'ssAlertToastWrap';
        wrap.className = 'ss-alert-toast-wrap';
        document.body.appendChild(wrap);
    }

    const icons = { danger: 'bi-exclamation-triangle-fill', info: 'bi-megaphone-fill', warn: 'bi-info-circle-fill' };
    const toast = document.createElement('div');
    toast.className = `ss-alert-toast ss-alert-toast--${type}`;
    // Strip HTML tags for clean text display
    const cleanMsg = message.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();

    toast.innerHTML = `
        <div class="ss-alert-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
        <div class="ss-alert-toast-body">
            <div class="ss-alert-toast-title">${escapeHtml(title)}</div>
            <div class="ss-alert-toast-msg">${escapeHtml(cleanMsg)}</div>
        </div>
        <button class="ss-alert-toast-close" title="Fermer"><i class="bi bi-x-lg"></i></button>
        <div class="ss-alert-toast-progress" style="width:100%"></div>
    `;

    wrap.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    const progress = toast.querySelector('.ss-alert-toast-progress');
    if (progress) {
        progress.style.transitionDuration = duration + 'ms';
        requestAnimationFrame(() => { progress.style.width = '0%'; });
    }

    let timer = setTimeout(() => removeToast(toast), duration);

    toast.querySelector('.ss-alert-toast-close').addEventListener('click', () => {
        clearTimeout(timer);
        removeToast(toast);
    });

    // Pause on hover
    toast.addEventListener('mouseenter', () => { clearTimeout(timer); if (progress) progress.style.transitionPlayState = 'paused'; });
    toast.addEventListener('mouseleave', () => {
        const remaining = progress ? (parseFloat(getComputedStyle(progress).width) / progress.parentElement.offsetWidth) * duration : 2000;
        if (progress) progress.style.transitionPlayState = 'running';
        timer = setTimeout(() => removeToast(toast), Math.max(remaining, 1000));
    });
}

function removeToast(toast) {
    toast.classList.add('removing');
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 400);
}

export { loadPage, navigateTo, BASE };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
