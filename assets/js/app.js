/**
 * zerdaTime - SPA Router + Sidebar + Search
 */

const BASE = '/zerdatime';

const moduleMap = {
    'home':       () => import('./modules/home.js'),
    'login':      () => import('./modules/auth.js'),
    'planning':   () => import('./modules/planning.js'),
    'desirs':     () => import('./modules/desirs.js'),
    'absences':   () => import('./modules/absences.js'),
    'vacances':   () => import('./modules/vacances.js'),
    'collegues':  () => import('./modules/collegues.js'),
    'messages':   () => import('./modules/messages.js'),
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
};

let currentModule = null;
let currentPage = null;

/* ── Page loading ── */

async function loadPage(pageId, params = {}) {
    const content = document.getElementById('app-content');
    if (!content) return;

    if (!window.__TR__?.user && pageId !== 'login') {
        pageId = 'login';
        history.replaceState({}, '', `${BASE}/login`);
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
    closeMobileSidebar();
}

/* ── Nav active state ── */

function updateNavActive(pageId) {
    document.querySelectorAll('.fe-sidebar-link[data-link]').forEach(a => {
        a.classList.toggle('active', a.getAttribute('data-link') === pageId);
    });
}

/* ── Topbar title ── */

function updateTopbarTitle(pageId) {
    const el = document.getElementById('feTopbarTitle');
    if (!el) return;
    const labels = window.__TR__?.pageLabels || {};
    el.textContent = labels[pageId] || pageId;
}

/* ── Routing ── */

function parseRoute() {
    let path = window.location.pathname;
    if (path.startsWith(BASE)) path = path.slice(BASE.length);
    path = path.replace(/^\/+|\/+$/g, '');

    const parts = path.split('/').filter(Boolean);
    const pageId = parts[0] || 'home';
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

const SIDEBAR_KEY = 'zt_fe_sidebar_mini';
const CAT_KEY = 'zt_fe_sidebar_cats';

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
        window.__TR__.user = null;
        window.location.href = `${BASE}/login`;
    };

    document.getElementById('logoutBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        doLogout();
    });
}

/* ── Global Search ── */

let searchTimer = null;
let colleaguesCache = null;

function setupSearch() {
    const input = document.getElementById('feSearchInput');
    const results = document.getElementById('feSearchResults');
    if (!input || !results) return;

    input.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const raw = input.value.trim();
        const isMention = raw.startsWith('@');
        const q = (isMention ? raw.slice(1) : raw).toLowerCase();
        const minLen = isMention ? 2 : 1;
        if (q.length < minLen) { results.classList.remove('show'); return; }
        searchTimer = setTimeout(() => runSearch(q, results), 200);
    });

    input.addEventListener('focus', () => {
        const raw = input.value.trim();
        const isMention = raw.startsWith('@');
        const q = (isMention ? raw.slice(1) : raw).toLowerCase();
        const minLen = isMention ? 2 : 1;
        if (q.length >= minLen) runSearch(q, results);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.fe-topbar-search')) results.classList.remove('show');
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { results.classList.remove('show'); input.blur(); }
    });
}

async function runSearch(q, container) {
    const items = [];

    // Search pages
    const labels = window.__TR__?.pageLabels || {};
    for (const [key, label] of Object.entries(labels)) {
        if (label.toLowerCase().includes(q) || key.toLowerCase().includes(q)) {
            items.push({ type: 'page', key, label });
        }
    }

    // Search colleagues
    const colleagues = await getColleagues();
    const matchedColleagues = colleagues.filter(c => {
        const full = `${c.prenom} ${c.nom}`.toLowerCase();
        return full.includes(q);
    }).slice(0, 8);

    matchedColleagues.forEach(c => {
        items.push({
            type: 'user',
            key: c.id,
            label: `${c.prenom} ${c.nom}`,
            meta: c.fonction_nom || ''
        });
    });

    if (items.length === 0) {
        container.innerHTML = '<div class="fe-search-item" style="opacity:.5"><span>Aucun résultat</span></div>';
        container.classList.add('show');
        return;
    }

    container.innerHTML = items.map(item => {
        if (item.type === 'page') {
            return `<a class="fe-search-item" data-link="${item.key}">
                <span class="fe-search-item-icon"><i class="bi bi-file-text"></i></span>
                <span class="fe-search-item-name">${escapeHtml(item.label)}</span>
            </a>`;
        }
        const initials = (item.label.split(' ').map(w => w[0] || '').join('').substring(0, 2)).toUpperCase();
        return `<a class="fe-search-item" data-link="collegues" data-slug="${item.key}">
            <span class="fe-search-item-icon user-icon">${escapeHtml(initials)}</span>
            <div>
                <div class="fe-search-item-name">${escapeHtml(item.label)}</div>
                ${item.meta ? `<div class="fe-search-item-meta">${escapeHtml(item.meta)}</div>` : ''}
            </div>
        </a>`;
    }).join('');
    container.classList.add('show');
}

async function getColleagues() {
    if (colleaguesCache) return colleaguesCache;
    try {
        const { apiPost } = await import('./helpers.js');
        const res = await apiPost('get_collegues');
        if (res.ok && Array.isArray(res.data)) {
            colleaguesCache = res.data;
            return res.data;
        }
    } catch (e) { console.warn('getColleagues error:', e); }
    return [];
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

/* ── Init ── */

/* ── Temp password banner ── */
function showTempPasswordBanner() {
    const expires = window.__TR__.tempPasswordExpires;
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

function init() {
    setupLinks();
    setupSidebar();
    setupLogout();
    setupSearch();
    handleRoute();
    window.addEventListener('popstate', handleRoute);
    window.__trNavigate = navigateTo;

    // Poll notification badge + check alerts + offline support
    if (window.__TR__?.user) {
        pollNotifBadge();
        setInterval(pollNotifBadge, 60000); // every 60s
        checkPendingAlerts();
        import('./modules/offline.js').then(m => m.initOffline()).catch(() => {});
        initFullscreen();
        if (window.__TR__.mustChangePassword) showTempPasswordBanner();
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
            sessionStorage.removeItem('zt_fullscreen');
            document.exitFullscreen().catch(() => {});
        } else {
            sessionStorage.setItem('zt_fullscreen', '1');
            document.documentElement.requestFullscreen().catch(() => {});
        }
    });

    document.addEventListener('fullscreenchange', () => {
        updateIcon();
        // Si l'utilisateur quitte via Escape, on désactive le mode persistant
        if (!document.fullscreenElement) {
            sessionStorage.removeItem('zt_fullscreen');
        }
    });

    // Restaurer le fullscreen si actif en session (changement de page SPA)
    if (sessionStorage.getItem('zt_fullscreen') === '1' && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {
            sessionStorage.removeItem('zt_fullscreen');
        });
    }

    updateIcon();
}

async function pollNotifBadge() {
    try {
        const { apiPost } = await import('./helpers.js');
        const res = await apiPost('get_notifications_count');
        const badge = document.querySelector('.fe-topbar-notif');
        if (badge) {
            if (res.unread > 0) {
                badge.textContent = res.unread;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
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
        overlay.className = 'zt-alert-overlay';
        overlay.innerHTML = `
            <div class="zt-alert-modal ${isHaute ? 'zt-alert-haute' : ''}">
                <div class="zt-alert-header">
                    <div class="zt-alert-header-icon ${isHaute ? 'zt-alert-header-icon--danger' : ''}">
                        <i class="bi ${isHaute ? 'bi-exclamation-triangle-fill' : 'bi-megaphone-fill'}"></i>
                    </div>
                    <div>
                        <h5 class="zt-alert-title">${escapeHtml(alert.title)}</h5>
                        <span class="zt-alert-meta">${escapeHtml(alert.creator_prenom + ' ' + alert.creator_nom)} · ${new Date(alert.created_at).toLocaleDateString('fr-FR')}</span>
                    </div>
                </div>
                <div class="zt-alert-content">
                    <div class="zt-alert-message ${isHaute ? 'zt-alert-message--danger' : ''}">
                        <i class="bi ${isHaute ? 'bi-exclamation-circle' : 'bi-info-circle'} zt-alert-message-icon"></i>
                        <div class="zt-alert-message-text">${escapeHtml(alert.message)}</div>
                    </div>
                </div>
                <div class="zt-alert-footer">
                    <button class="zt-alert-btn ${isHaute ? 'zt-alert-btn-danger' : ''}">
                        <i class="bi bi-check-lg"></i> J'ai pris connaissance
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('show'));

        overlay.querySelector('.zt-alert-btn').addEventListener('click', () => {
            overlay.classList.remove('show');
            setTimeout(() => { overlay.remove(); resolve(); }, 300);
        });
    });
}

export { loadPage, navigateTo, BASE };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
