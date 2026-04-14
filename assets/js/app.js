/**
 * SpocSpace - SPA Router + Sidebar + Search
 */

const BASE = '/spocspace';

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
    'notifications': () => import('./modules/notifications.js'),
    'fiches-salaire': () => import('./modules/fiches-salaire.js'),
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
        const res = await fetch(`${BASE}/pages/${pageId}.php?v=${Date.now()}`);
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

/* ── Global Search ── */

let searchTimer = null;

function setupSearch() {
    const input = document.getElementById('feSearchInput');
    const panel = document.getElementById('feSearchResults');
    const clearBtn = document.getElementById('feSearchClear');
    const wrap = document.getElementById('feTopbarSearch');
    if (!input || !panel) return;

    const HIST_KEY = 'spocspace:search-history';
    const TTL_MS = 2 * 24 * 60 * 60 * 1000;
    const now = () => Date.now();

    function loadHist() { try { const a = JSON.parse(localStorage.getItem(HIST_KEY) || '[]'); return a.filter(it => it && it.q && (it.ts||0) >= now() - TTL_MS); } catch { return []; } }
    function saveHist(arr) { const seen = new Set(); const c = arr.filter(it => { const k = (it.q||'').toLowerCase(); if (!k||seen.has(k)) return false; seen.add(k); return true; }).slice(0,30); try { localStorage.setItem(HIST_KEY, JSON.stringify(c)); } catch {} }
    function addHist(q) { q=(q||'').trim(); if(!q||q.length<2) return; const h=loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase()); h.unshift({q,ts:now()}); saveHist(h); }
    function delHist(q) { saveHist(loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase())); }

    function openPanel() { panel.classList.add('show'); }
    function closePanel() { panel.classList.remove('show'); if (wrap) wrap.classList.remove('expanded'); }

    function updateClear() { if (clearBtn) clearBtn.style.display = input.value.length > 0 ? '' : 'none'; }

    function renderHistory(filter) {
        const hist = loadHist().sort((a,b)=>b.ts-a.ts);
        const f = (filter||'').toLowerCase();
        const filtered = f ? hist.filter(it=>it.q.toLowerCase().includes(f)) : hist;
        const recent = filtered.slice(0,3);
        const older = filtered.slice(3,7);
        if (!recent.length && !older.length) { panel.innerHTML = '<div class="fe-search-item" style="opacity:.5;justify-content:center"><span>Tapez pour rechercher</span></div>'; return; }
        const mkItem = (it, icon) => `<div class="fe-search-item fe-hist-item" data-q="${escapeHtml(it.q)}"><span class="fe-search-item-icon"><i class="bi bi-${icon}"></i></span><span class="fe-search-item-name">${escapeHtml(it.q)}</span><button class="fe-hist-del" data-del-q="${escapeHtml(it.q)}"><i class="bi bi-x"></i></button></div>`;
        let html = recent.map(it=>mkItem(it,'clock')).join('');
        if (older.length) html += '<div style="font-size:.7rem;color:#999;padding:4px 10px">Plus anciennes</div>' + older.map(it=>mkItem(it,'search')).join('');
        panel.innerHTML = html;
    }

    function renderResults(results, query) {
        if (!results.length) { panel.innerHTML = '<div class="fe-search-item" style="opacity:.5;justify-content:center"><span>Aucun résultat</span></div>'; return; }
        const groups = {};
        results.forEach(r => { if (!groups[r.type]) groups[r.type]=[]; groups[r.type].push(r); });
        const typeLabels = { collegue:'Collègues', wiki:'Wiki', annonce:'Annonces', document:'Documents', contact:'Annuaire', page:'Pages' };
        const typeColors = { collegue:'#bcd2cb', wiki:'#B8C9D4', annonce:'#D0C4D8', document:'#D4C4A8', contact:'#E2B8AE', page:'#f0eeea' };
        let html = '';
        for (const [type, items] of Object.entries(groups)) {
            html += `<div style="font-size:.7rem;color:#999;padding:4px 10px;font-weight:600">${typeLabels[type]||type}</div>`;
            items.forEach(r => {
                if (type === 'collegue') {
                    const online = !!r.is_online;
                    const presenceDot = `<span class="ss-presence-dot ${online ? 'ss-presence-online' : 'ss-presence-offline'}" style="width:10px;height:10px;border-width:1.5px"></span>`;
                    const callBtns = online
                        ? `<button class="fe-search-call-btn fe-search-call-audio" data-call-audio="${r.id}" title="Appel audio"><i class="bi bi-telephone-fill"></i></button>
                           <button class="fe-search-call-btn fe-search-call-video" data-call-video="${r.id}" title="Appel vidéo"><i class="bi bi-camera-video-fill"></i></button>`
                        : `<button class="fe-search-call-btn" disabled title="Hors ligne" style="opacity:.4;cursor:not-allowed"><i class="bi bi-telephone-x"></i></button>`;
                    html += `
                        <div class="fe-search-item fe-search-item-collegue fe-result-item"
                             data-page="${r.page}" data-id="${r.id||''}" data-type="${type}"
                             data-user-photo="${escapeHtml(r.photo||'')}"
                             data-user-prenom="${escapeHtml(r.prenom||'')}"
                             data-user-nom="${escapeHtml(r.nom||'')}">
                            <span class="fe-search-item-icon" style="background:${typeColors[type]};position:relative">
                                <i class="bi bi-${r.icon}"></i>
                                ${presenceDot}
                            </span>
                            <div class="fe-search-item-body">
                                <div class="fe-search-item-name">${escapeHtml(r.title)}</div>
                                <div class="fe-search-item-meta">${online ? '<span style="color:#2d4a43;font-weight:600">En ligne</span>' : 'Hors ligne'}</div>
                            </div>
                            <div class="fe-search-call-actions">${callBtns}</div>
                        </div>`;
                } else {
                    html += `<div class="fe-search-item fe-result-item" data-page="${r.page}" data-id="${r.id||''}" data-type="${type}"><span class="fe-search-item-icon" style="background:${typeColors[type]||'#f0eeea'}"><i class="bi bi-${r.icon}"></i></span><div><div class="fe-search-item-name">${escapeHtml(r.title)}</div>${r.subtitle?`<div class="fe-search-item-meta">${escapeHtml(r.subtitle)}</div>`:''}</div></div>`;
                }
            });
        }
        panel.innerHTML = html;
    }

    async function doSearch(q) {
        if (q.length < 2) { renderHistory(q); return; }
        try {
            const { apiPost } = await import('./helpers.js');
            const res = await apiPost('global_search', { q });
            if (res.success) renderResults(res.results, q);
        } catch { renderHistory(q); }
    }

    input.addEventListener('focus', () => {
        if (wrap) wrap.classList.add('expanded');
        if (!input.value) renderHistory('');
        openPanel();
    });

    input.addEventListener('input', () => {
        updateClear();
        clearTimeout(searchTimer);
        const v = input.value.trim();
        if (v.length < 2) { renderHistory(v); openPanel(); return; }
        searchTimer = setTimeout(() => doSearch(v), 300);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePanel(); input.blur(); }
        if (e.key === 'Enter' && input.value.trim().length >= 2) { addHist(input.value.trim()); doSearch(input.value.trim()); }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => { if (!panel.classList.contains('show')) { if (wrap) wrap.classList.remove('expanded'); } }, 200);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => { input.value = ''; updateClear(); renderHistory(''); input.focus(); });
    }

    panel.addEventListener('click', async (e) => {
        // Call buttons inside collegue result
        const callAudio = e.target.closest('[data-call-audio]');
        const callVideo = e.target.closest('[data-call-video]');
        if (callAudio || callVideo) {
            e.preventDefault();
            e.stopPropagation();
            const btn = callAudio || callVideo;
            const item = btn.closest('.fe-search-item-collegue');
            if (!item) return;
            const user = {
                id: item.dataset.id,
                prenom: item.dataset.userPrenom,
                nom: item.dataset.userNom,
                photo: item.dataset.userPhoto,
            };
            closePanel();
            try {
                const m = await import('./call-ui.js');
                m.startCall(user, callAudio ? 'audio' : 'video');
            } catch (err) { console.error(err); }
            return;
        }
        const del = e.target.closest('.fe-hist-del');
        if (del) { e.stopPropagation(); delHist(del.dataset.delQ); renderHistory(input.value); return; }
        const hist = e.target.closest('.fe-hist-item');
        if (hist) { input.value = hist.dataset.q; updateClear(); doSearch(hist.dataset.q); return; }
        const result = e.target.closest('.fe-result-item');
        if (result) {
            addHist(input.value.trim());
            closePanel();
            const page = result.dataset.page;
            const id = result.dataset.id;
            const type = result.dataset.type || '';
            if (!page) return;

            // Documents / Annuaire : passer l'id en highlight pour scroll + surbrillance
            if ((page === 'documents' || page === 'annuaire') && id) {
                const url = `${BASE}/${page}?highlight=${encodeURIComponent(id)}`;
                history.pushState({}, '', url);
                loadPage(page, { highlight: id });
                return;
            }
            // Pages avec id (annonces, wiki, etc.)
            if (id) {
                loadPage(page, { id });
                return;
            }
            navigateTo(page);
        }
    });

    document.addEventListener('click', (e) => { if (!e.target.closest('.fe-topbar-search')) closePanel(); });
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
