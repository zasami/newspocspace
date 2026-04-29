/**
 * SpocSpace Admin - Main JS
 * Retractable sidebar + category collapse + page init
 */
document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar references ──
    const sidebar   = document.getElementById('adminSidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileBtn = document.getElementById('mobileToggle');

    // ── Desktop: mini/full toggle with localStorage ──
    const SIDEBAR_KEY = 'ss_sidebar_mini';
    if (localStorage.getItem(SIDEBAR_KEY) === '1') {
        sidebar?.classList.add('mini');
    }

    toggleBtn?.addEventListener('click', () => {
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (isDesktop) {
            // Desktop : bascule mini ↔ full + persiste la pref user
            sidebar.classList.toggle('mini');
            localStorage.setItem(SIDEBAR_KEY, sidebar.classList.contains('mini') ? '1' : '0');
        } else {
            // Mobile : ferme l'overlay
            closeMobile();
        }
    });

    // ── Mobile: open / close ──
    mobileBtn?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
    });

    overlay?.addEventListener('click', closeMobile);

    function closeMobile() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    }

    // ── Category collapse/expand ──
    const CAT_KEY = 'ss_sidebar_cats';
    const savedCats = JSON.parse(localStorage.getItem(CAT_KEY) || '{}');

    document.querySelectorAll('[data-cat-toggle]').forEach(catEl => {
        const catId = catEl.dataset.catToggle;
        const body = document.querySelector(`[data-cat-body="${catId}"]`);
        if (!body) return;

        // Resolve initial state. Priority:
        //   1. Section/module contains the active link → always expanded (override saved + server)
        //   2. User explicitly toggled it → respect saved pref (true=collapsed, false=expanded)
        //   3. Otherwise → leave server-rendered default in place (modules collapse all but the active one)
        const hasActive = body.querySelector('.sidebar-link.active');
        const userPref  = Object.prototype.hasOwnProperty.call(savedCats, catId) ? savedCats[catId] : null;

        if (hasActive) {
            body.classList.remove('collapsed');
            catEl.classList.remove('cat-collapsed');
        } else if (userPref === true) {
            body.classList.add('collapsed');
            catEl.classList.add('cat-collapsed');
        } else if (userPref === false) {
            body.classList.remove('collapsed');
            catEl.classList.remove('cat-collapsed');
        }

        catEl.addEventListener('click', () => {
            const isCollapsed = body.classList.toggle('collapsed');
            catEl.classList.toggle('cat-collapsed', isCollapsed);

            const all = JSON.parse(localStorage.getItem(CAT_KEY) || '{}');
            all[catId] = isCollapsed;
            localStorage.setItem(CAT_KEY, JSON.stringify(all));
        });
    });

    // ── Topbar global search (with history) ──
    const searchInput = document.getElementById('topbarSearchInput');
    const searchPanel = document.getElementById('topbarSearchResults');
    let searchTimer = null;

    const currentPage = AdminURL.currentPage();
    const searchClear = document.getElementById('adminSearchClear');
    const searchWrap = document.getElementById('topbarSearch');

    if (searchInput && searchPanel) {
        const HIST_KEY = 'spocadmin:search-history';
        const TTL_MS = 2 * 24 * 60 * 60 * 1000;
        const now = () => Date.now();

        function loadHist() { try { const a = JSON.parse(localStorage.getItem(HIST_KEY)||'[]'); return a.filter(it=>it&&it.q&&(it.ts||0)>=now()-TTL_MS); } catch { return []; } }
        function saveHist(arr) { const seen = new Set(); const c = arr.filter(it => { const k=(it.q||'').toLowerCase(); if(!k||seen.has(k)) return false; seen.add(k); return true; }).slice(0,30); try { localStorage.setItem(HIST_KEY,JSON.stringify(c)); } catch {} }
        function addHist(q) { q=(q||'').trim(); if(!q||q.length<2) return; const h=loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase()); h.unshift({q,ts:now()}); saveHist(h); }
        function delHist(q) { saveHist(loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase())); }

        function openPanel() { searchPanel.classList.add('show'); }
        function closePanel() { searchPanel.classList.remove('show'); if (searchWrap) searchWrap.classList.remove('expanded'); }
        function updateClear() { if (searchClear) searchClear.style.display = searchInput.value.length > 0 ? '' : 'none'; }

        function renderHistory(filter) {
            const hist = loadHist().sort((a,b)=>b.ts-a.ts);
            const f = (filter||'').toLowerCase();
            const filtered = f ? hist.filter(it=>it.q.toLowerCase().includes(f)) : hist;
            if (!filtered.length) { searchPanel.innerHTML = '<div class="p-3 text-center text-muted" style="font-size:.85rem">Tapez pour rechercher</div>'; return; }
            searchPanel.innerHTML = filtered.slice(0,6).map((it,i) => {
                const icon = i < 2 ? 'clock' : 'search';
                return `<div class="search-result-item" style="cursor:pointer" data-hist-q="${escapeHtml(it.q)}">
                    <div class="search-result-avatar" style="background:#f0eeea;color:#999"><i class="bi bi-${icon}"></i></div>
                    <div class="search-result-name">${escapeHtml(it.q)}</div>
                    <button class="admin-hist-del" data-del-q="${escapeHtml(it.q)}" style="background:none;border:none;color:#ccc;cursor:pointer;margin-left:auto;font-size:.75rem"><i class="bi bi-x"></i></button>
                </div>`;
            }).join('');
        }

        function renderResults(results) {
            if (!results.length) { searchPanel.innerHTML = '<div class="p-3 text-center text-muted" style="font-size:.85rem">Aucun résultat</div>'; searchPanel.classList.add('show'); return; }
            const groups = {};
            results.forEach(r => { if (!groups[r.type]) groups[r.type]=[]; groups[r.type].push(r); });
            const typeLabels = { user:'Utilisateurs', resident:'Résidents', wiki:'Wiki', annonce:'Annonces', document:'Documents' };
            let html = '';
            for (const [type, items] of Object.entries(groups)) {
                html += `<div style="font-size:.7rem;color:#999;padding:4px 12px;font-weight:600;text-transform:uppercase">${typeLabels[type]||type}</div>`;
                items.forEach(r => {
                    const href = r.external_url ? r.external_url : AdminURL.page(r.page, r.page_id || r.id);
                    html += `<a class="search-result-item" href="${href}" style="text-decoration:none;color:inherit">
                        <div class="search-result-avatar"><i class="bi bi-${r.icon}"></i></div>
                        <div>
                            <div class="search-result-name">${escapeHtml(r.title)}</div>
                            <div class="search-result-meta">${escapeHtml(r.subtitle||'')}</div>
                        </div>
                    </a>`;
                });
            }
            searchPanel.innerHTML = html;
            searchPanel.classList.add('show');
        }

        async function doSearch(q) {
            if (q.length < 2) { renderHistory(q); return; }
            const res = await adminApiPost('admin_global_search', { q });
            if (res.success) renderResults(res.results);
        }

        // ── Mode recherche locale (@ prefix) ──
        const ORIGINAL_PLACEHOLDER = searchInput.placeholder;
        const LOCAL_PLACEHOLDER = 'Rechercher sur la page…';

        function isLocalMode(val) { return (val || '').trim().startsWith('@'); }

        function applyLocalSearch(raw) {
            const q = raw.replace(/^@/, '').trim();
            // Appelle le handler spécifique à la page si dispo
            if (typeof window.__pageLocalSearch === 'function') {
                try { window.__pageLocalSearch(q); }
                catch(e) { console.error('[localSearch] handler error:', e); }
            } else {
                console.debug('[localSearch] no __pageLocalSearch handler on this page');
            }
            // Panneau d'info
            if (q.length === 0) {
                searchPanel.innerHTML = '<div class="p-3 text-center text-muted" style="font-size:.85rem"><i class="bi bi-funnel"></i> Mode recherche sur la page actuelle<br><small>Tapez pour filtrer…</small></div>';
            } else {
                const count = (typeof window.__pageLocalSearchCount === 'function')
                    ? window.__pageLocalSearchCount() : null;
                searchPanel.innerHTML = `<div class="p-3 text-center text-muted" style="font-size:.85rem"><i class="bi bi-funnel-fill" style="color:var(--cl-green,#2E7D32)"></i> Filtre page : <strong>${escapeHtml(q)}</strong>${count !== null ? `<br><small>${count} résultat${count > 1 ? 's' : ''} visible${count > 1 ? 's' : ''}</small>` : ''}</div>`;
            }
            searchPanel.classList.add('show');
        }

        function clearLocalSearch() {
            if (typeof window.__pageLocalSearch === 'function') {
                try { window.__pageLocalSearch(''); } catch(e) {}
            }
        }

        function refreshPlaceholder() {
            searchInput.placeholder = isLocalMode(searchInput.value) ? LOCAL_PLACEHOLDER : ORIGINAL_PLACEHOLDER;
        }

        searchInput.addEventListener('focus', () => {
            if (searchWrap) searchWrap.classList.add('expanded');
            if (!searchInput.value) renderHistory('');
            openPanel();
        });

        searchInput.addEventListener('input', () => {
            updateClear();
            refreshPlaceholder();
            clearTimeout(searchTimer);
            const v = searchInput.value;
            // Mode local @
            if (isLocalMode(v)) {
                applyLocalSearch(v);
                openPanel();
                return;
            }
            // Si on était en mode local et qu'on sort, reset le filtre page
            clearLocalSearch();
            const trimmed = v.trim();
            if (trimmed.length < 2) { renderHistory(trimmed); openPanel(); return; }
            searchTimer = setTimeout(() => doSearch(trimmed), 300);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closePanel(); searchInput.blur(); }
            if (e.key === 'Enter') {
                const val = searchInput.value;
                if (isLocalMode(val)) { e.preventDefault(); applyLocalSearch(val); openPanel(); return; }
                if (val.trim().length >= 2) { addHist(val.trim()); doSearch(val.trim()); }
            }
        });

        searchInput.addEventListener('blur', () => {
            setTimeout(() => { if (!searchPanel.classList.contains('show')) { if (searchWrap) searchWrap.classList.remove('expanded'); } }, 200);
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                updateClear();
                refreshPlaceholder();
                clearLocalSearch();
                renderHistory('');
                searchInput.focus();
            });
        }

        searchPanel.addEventListener('click', (e) => {
            const del = e.target.closest('.admin-hist-del');
            if (del) { e.preventDefault(); e.stopPropagation(); delHist(del.dataset.delQ); renderHistory(searchInput.value); return; }
            const hist = e.target.closest('[data-hist-q]');
            if (hist && !e.target.closest('.admin-hist-del')) {
                const hq = hist.dataset.histQ;
                searchInput.value = hq;
                updateClear();
                refreshPlaceholder();
                if (isLocalMode(hq)) { applyLocalSearch(hq); openPanel(); }
                else { doSearch(hq); }
                return;
            }
        });

        document.addEventListener('click', (e) => { if (!e.target.closest('#topbarSearch')) closePanel(); });
    }

    // ── Page-specific init ──
    const fnName = 'init' + capitalize(currentPage.replace(/-/g, '')) + 'Page';
    if (typeof window[fnName] === 'function') {
        try {
            window[fnName]();
        } catch (e) {
            console.error('Page init error (' + fnName + '):', e);
        }
    }

    // ── Email notification badge ──
    async function updateAdminEmailBadge() {
        try {
            const res = await adminApiPost('admin_get_message_stats');
            if (!res.success) return;
            const badge = document.getElementById('adminEmailBadge');
            if (badge) {
                const count = parseInt(res.stats?.unread) || 0;
                badge.style.display = count > 0 ? '' : 'none';
            }
        } catch (e) {}
    }
    updateAdminEmailBadge();
    setInterval(updateAdminEmailBadge, 60000);

    // ── Scroll active menu item into view ──
    requestAnimationFrame(() => {
        const activeLink = sidebar?.querySelector('.sidebar-link.active');
        if (activeLink) {
            activeLink.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    });
});

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
