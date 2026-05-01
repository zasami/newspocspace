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

    // ── Topbar global search (Spocspace design) ──
    const searchInput = document.getElementById('topbarSearchInput');
    const searchPanel = document.getElementById('topbarSearchResults');
    const searchBar   = document.getElementById('topbarSearchBar');
    const searchClear = document.getElementById('adminSearchClear');
    const currentPage = AdminURL.currentPage();
    let searchTimer = null;

    const SS_GS_GROUP_LABELS_ADMIN = {
        user: 'Utilisateurs', resident: 'Résidents', wiki: 'Wiki',
        annonce: 'Annonces', document: 'Documents', page: 'Pages',
    };
    const SS_GS_TYPE_ICON_ADMIN = {
        wiki: 'book', document: 'file-text', annonce: 'megaphone',
        resident: 'address-book', page: 'arrow-right',
    };

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
            case 'address-book': return `<svg ${a}><path d="M4 19.5v-15A2.5 2.5 0 016.5 2H20v20H6.5a2.5 2.5 0 010-5H20"/><circle cx="12" cy="11" r="3"/><path d="M8 17h8"/></svg>`;
            case 'arrow-right':  return `<svg ${a}><path d="M5 12h14M13 5l7 7-7 7"/></svg>`;
            case 'search-empty': return `<svg ${a}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M11 8v3M11 14h.01"/></svg>`;
            case 'filter':       return `<svg ${a}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>`;
            default: return `<svg ${a}><circle cx="12" cy="12" r="10"/></svg>`;
        }
    }

    function ssGsAvatarVar(id) {
        const s = String(id || '');
        let h = 0;
        for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
        return Math.abs(h) % 5 + 1;
    }

    function ssGsInitialsFromTitle(title) {
        const parts = String(title || '').trim().split(/\s+/);
        const a = (parts[0]?.[0] || '');
        const b = (parts[1]?.[0] || '');
        return (a + b).toUpperCase() || '?';
    }

    function ssGsHighlight(text, q) {
        const safe = escapeHtml(text || '');
        if (!q) return safe;
        const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return safe.replace(re, '<mark>$1</mark>');
    }

    if (searchInput && searchPanel && searchBar) {
        const HIST_KEY = 'spocadmin:search-history';
        const TTL_MS = 2 * 24 * 60 * 60 * 1000;
        const now = () => Date.now();

        function loadHist() { try { const a = JSON.parse(localStorage.getItem(HIST_KEY)||'[]'); return a.filter(it=>it&&it.q&&(it.ts||0)>=now()-TTL_MS); } catch { return []; } }
        function saveHist(arr) { const seen = new Set(); const c = arr.filter(it => { const k=(it.q||'').toLowerCase(); if(!k||seen.has(k)) return false; seen.add(k); return true; }).slice(0,30); try { localStorage.setItem(HIST_KEY,JSON.stringify(c)); } catch {} }
        function addHist(q) { q=(q||'').trim(); if(!q||q.length<2) return; const h=loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase()); h.unshift({q,ts:now()}); saveHist(h); }
        function delHist(q) { saveHist(loadHist().filter(it=>it.q.toLowerCase()!==q.toLowerCase())); }

        function openPanel() { searchPanel.classList.add('show'); }
        function closePanel() { searchPanel.classList.remove('show'); }
        function updateBar() { if (searchInput.value.length > 0) searchBar.classList.add('has-value'); else searchBar.classList.remove('has-value'); }

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
                <span class="hidden md:inline font-mono text-[10.5px] text-muted-2">@ pour filtrer la page</span>
              </div>`;
        }

        function renderInitial(filter) {
            const hist = loadHist().sort((a,b)=>b.ts-a.ts);
            const f = (filter||'').toLowerCase();
            const filteredHist = f ? hist.filter(it=>it.q.toLowerCase().includes(f)) : hist;

            const shortcuts = [
                { label: 'Planning',       page: 'planning',       icon: 'calendar' },
                { label: 'Collaborateurs', page: 'users',          icon: 'users' },
                { label: 'Documents',      page: 'documents',      icon: 'file-text' },
                { label: 'Annonces',       page: 'annonces',       icon: 'megaphone' },
            ];

            let html = `
              <div class="px-4 pt-2 pb-1.5 flex items-center justify-between">
                <span class="font-mono text-[10px] font-semibold text-muted tracking-[0.16em] uppercase">Accès rapides</span>
              </div>
              <div class="px-3 pb-2 grid grid-cols-2 gap-1.5">
                ${shortcuts.map(s => `
                  <a href="${AdminURL.page(s.page)}" class="ss-gs-shortcut group/sc flex items-center gap-2.5 px-2.5 py-2 bg-surface-2 border border-line rounded-lg text-[12.5px] text-ink-2 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition no-underline">
                    <span class="w-6 h-6 rounded-md bg-teal-50 group-hover/sc:bg-teal-100 text-teal-700 grid place-items-center shrink-0 transition-colors">${ssGsSvg(s.icon, 13)}</span>
                    <span>${s.label}</span>
                  </a>
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

            searchPanel.innerHTML = frame(html);
        }

        function renderEmpty() {
            searchPanel.innerHTML = frame(`
              <div class="py-10 px-6 text-center">
                <div class="w-12 h-12 mx-auto mb-3 bg-surface-3 rounded-2xl grid place-items-center text-muted">${ssGsSvg('search-empty', 22)}</div>
                <h3 class="font-display text-[15px] font-semibold text-ink mb-1 -tracking-[0.01em]">Aucun résultat trouvé</h3>
                <p class="text-[12.5px] text-muted">Essayez d'autres mots-clés ou vérifiez l'orthographe.</p>
              </div>`);
        }

        function renderItem(r, q) {
            const type = r.type;
            const titleHtml = ssGsHighlight(r.title, q);
            const href = r.external_url ? r.external_url : AdminURL.page(r.page, r.page_id || r.id);

            let iconHtml;
            if (type === 'user' || type === 'resident') {
                if (r.photo) {
                    iconHtml = `<div class="w-9 h-9 rounded-[10px] overflow-hidden shrink-0 shadow-sp-sm bg-surface-3">
                        <img src="${escapeHtml(r.photo)}" alt="" class="w-full h-full object-cover" />
                      </div>`;
                } else if (type === 'user') {
                    const variant = ssGsAvatarVar(r.id);
                    const init = ssGsInitialsFromTitle(r.title);
                    iconHtml = `<div class="ss-gs-av-${variant} w-9 h-9 rounded-[10px] grid place-items-center shrink-0 font-display font-semibold text-sm text-white -tracking-[0.02em] shadow-sp-sm">${escapeHtml(init)}</div>`;
                } else {
                    iconHtml = `<div class="ss-gs-icon-resident w-9 h-9 rounded-[10px] grid place-items-center shrink-0 text-white shadow-sp-sm">${ssGsSvg('address-book', 16)}</div>`;
                }
            } else {
                const knownIcons = ['wiki','document','annonce','contact','page'];
                const iconClass = knownIcons.includes(type) ? `ss-gs-icon-${type}` : 'ss-gs-icon-default';
                const iconName = SS_GS_TYPE_ICON_ADMIN[type] || 'arrow-right';
                iconHtml = `<div class="${iconClass} w-9 h-9 rounded-[10px] grid place-items-center shrink-0 text-white shadow-sp-sm">${ssGsSvg(iconName, 16)}</div>`;
            }

            const subtitle = r.subtitle ? `<div class="text-[11.5px] text-muted truncate">${escapeHtml(r.subtitle)}</div>` : '';

            return `
              <a href="${href}" class="ss-gs-item ss-gs-result flex items-center gap-3 px-4 py-2 cursor-pointer hover:bg-teal-50 transition-colors no-underline text-ink-2"
                 data-page="${escapeHtml(r.page||'')}" data-id="${escapeHtml(r.id||'')}" data-type="${escapeHtml(type)}">
                ${iconHtml}
                <div class="flex-1 min-w-0">
                  <div class="text-[13.5px] font-semibold text-ink truncate">${titleHtml}</div>
                  ${subtitle}
                </div>
              </a>`;
        }

        function renderResults(results, q) {
            if (!results.length) { renderEmpty(); return; }
            const groups = {};
            results.forEach(r => { if (!groups[r.type]) groups[r.type]=[]; groups[r.type].push(r); });

            const order = ['user','resident','wiki','annonce','document','page'];
            const sorted = Object.entries(groups).sort((a,b) => {
                const ia = order.indexOf(a[0]); const ib = order.indexOf(b[0]);
                return (ia<0?99:ia) - (ib<0?99:ib);
            });

            let html = '';
            sorted.forEach(([type, items], idx) => {
                const label = SS_GS_GROUP_LABELS_ADMIN[type] || type;
                html += `
                  <div class="${idx > 0 ? 'border-t border-line' : ''} py-1.5">
                    <div class="px-4 pt-2 pb-1.5 flex items-center justify-between">
                      <span class="font-mono text-[10px] font-semibold text-muted tracking-[0.16em] uppercase">${escapeHtml(label)}</span>
                      <span class="font-mono text-[10px] font-semibold text-teal-700 bg-teal-50 px-1.5 py-px rounded-full">${items.length}</span>
                    </div>
                    <div>${items.map(r => renderItem(r, q)).join('')}</div>
                  </div>`;
            });

            searchPanel.innerHTML = frame(html);
        }

        function clearActive() { searchPanel.querySelectorAll('.ss-gs-item.bg-teal-50').forEach(el => el.classList.remove('bg-teal-50')); searchPanel.querySelectorAll('.ss-gs-item.active').forEach(el => el.classList.remove('active')); }
        function setActive(el) {
            if (!el) return;
            clearActive();
            el.classList.add('active', 'bg-teal-50');
            el.scrollIntoView({ block: 'nearest' });
        }

        async function doSearch(q) {
            if (q.length < 2) { renderInitial(q); return; }
            try {
                const res = await adminApiPost('admin_global_search', { q });
                if (res.success) renderResults(res.results, q);
                else renderEmpty();
            } catch { renderEmpty(); }
        }

        // ── Mode recherche locale (@ prefix) ──
        const ORIGINAL_PLACEHOLDER = searchInput.placeholder;
        const LOCAL_PLACEHOLDER = 'Rechercher sur la page…';

        function isLocalMode(val) { return (val || '').trim().startsWith('@'); }

        function applyLocalSearch(raw) {
            const q = raw.replace(/^@/, '').trim();
            if (typeof window.__pageLocalSearch === 'function') {
                try { window.__pageLocalSearch(q); } catch(e) { console.error('[localSearch] handler error:', e); }
            }
            if (q.length === 0) {
                searchPanel.innerHTML = frame(`
                  <div class="py-8 px-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 bg-teal-50 rounded-2xl grid place-items-center text-teal-700">${ssGsSvg('filter', 22)}</div>
                    <h3 class="font-display text-[15px] font-semibold text-ink mb-1 -tracking-[0.01em]">Filtre sur la page actuelle</h3>
                    <p class="text-[12.5px] text-muted">Tapez pour filtrer le contenu visible…</p>
                  </div>`);
            } else {
                const count = (typeof window.__pageLocalSearchCount === 'function') ? window.__pageLocalSearchCount() : null;
                searchPanel.innerHTML = frame(`
                  <div class="py-6 px-6 text-center">
                    <div class="w-10 h-10 mx-auto mb-2 bg-teal-50 rounded-xl grid place-items-center text-teal-700">${ssGsSvg('filter', 18)}</div>
                    <h3 class="font-body text-[13.5px] font-semibold text-ink mb-1">Filtre page : <span class="text-teal-700">${escapeHtml(q)}</span></h3>
                    ${count !== null ? `<p class="text-[12px] text-muted">${count} résultat${count > 1 ? 's' : ''} visible${count > 1 ? 's' : ''}</p>` : ''}
                  </div>`);
            }
        }

        function clearLocalSearch() {
            if (typeof window.__pageLocalSearch === 'function') { try { window.__pageLocalSearch(''); } catch(e) {} }
        }

        function refreshPlaceholder() {
            searchInput.placeholder = isLocalMode(searchInput.value) ? LOCAL_PLACEHOLDER : ORIGINAL_PLACEHOLDER;
        }

        searchInput.addEventListener('focus', () => {
            if (!searchInput.value) renderInitial('');
            openPanel();
        });

        searchInput.addEventListener('input', () => {
            updateBar();
            refreshPlaceholder();
            clearTimeout(searchTimer);
            const v = searchInput.value;
            if (isLocalMode(v)) { applyLocalSearch(v); openPanel(); return; }
            clearLocalSearch();
            const trimmed = v.trim();
            if (trimmed.length < 2) { renderInitial(trimmed); openPanel(); return; }
            searchTimer = setTimeout(() => doSearch(trimmed), 300);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closePanel(); searchInput.blur(); return; }
            if (e.key === 'Enter') {
                const val = searchInput.value;
                if (isLocalMode(val)) { e.preventDefault(); applyLocalSearch(val); openPanel(); return; }
                const active = searchPanel.querySelector('.ss-gs-item.active');
                if (active) { e.preventDefault(); active.click(); return; }
                if (val.trim().length >= 2) { addHist(val.trim()); doSearch(val.trim()); }
                return;
            }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const items = Array.from(searchPanel.querySelectorAll('.ss-gs-item'));
                if (!items.length) return;
                const cur = items.findIndex(el => el.classList.contains('active'));
                const next = e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur <= 0 ? items.length - 1 : cur - 1);
                setActive(items[next]);
            }
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                updateBar();
                refreshPlaceholder();
                clearLocalSearch();
                renderInitial('');
                searchInput.focus();
            });
        }

        searchPanel.addEventListener('click', (e) => {
            const del = e.target.closest('.ss-gs-hist-del');
            if (del) { e.preventDefault(); e.stopPropagation(); delHist(del.dataset.delQ); renderInitial(searchInput.value); return; }
            const hist = e.target.closest('.ss-gs-hist');
            if (hist && !del) {
                const hq = hist.dataset.histQ;
                searchInput.value = hq;
                updateBar();
                refreshPlaceholder();
                if (isLocalMode(hq)) { applyLocalSearch(hq); openPanel(); }
                else { doSearch(hq); }
                return;
            }
            // .ss-gs-result are <a> with href: native navigation handles them
        });

        // Cmd/Ctrl + K — focus search anywhere
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                const ae = document.activeElement;
                if (ae && ae !== searchInput && /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName) && !ae.readOnly) return;
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
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
