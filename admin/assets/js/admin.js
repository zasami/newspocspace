/**
 * zerdaTime Admin - Main JS
 * Retractable sidebar + category collapse + page init
 */
document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar references ──
    const sidebar   = document.getElementById('adminSidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileBtn = document.getElementById('mobileToggle');

    // ── Desktop: mini/full toggle with localStorage ──
    const SIDEBAR_KEY = 'zt_sidebar_mini';
    if (localStorage.getItem(SIDEBAR_KEY) === '1') {
        sidebar?.classList.add('mini');
    }

    toggleBtn?.addEventListener('click', () => {
        sidebar.classList.toggle('mini');
        localStorage.setItem(SIDEBAR_KEY, sidebar.classList.contains('mini') ? '1' : '0');
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
    const CAT_KEY = 'zt_sidebar_cats';
    const savedCats = JSON.parse(localStorage.getItem(CAT_KEY) || '{}');

    document.querySelectorAll('[data-cat-toggle]').forEach(catEl => {
        const catId = catEl.dataset.catToggle;
        const body = document.querySelector(`[data-cat-body="${catId}"]`);
        if (!body) return;

        // Restore saved state (keep open if contains active link)
        const hasActive = body.querySelector('.sidebar-link.active');
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

    // ── Topbar search (autocomplete) ──
    const searchInput = document.getElementById('topbarSearchInput');
    const searchResults = document.getElementById('topbarSearchResults');
    let searchTimer = null;

    // On certain pages, topbar search is hijacked by the page script — skip default user search
    const currentPage = AdminURL.currentPage();
    const searchOverridePages = ['messages', 'sondages', 'documents', 'users', 'todos', 'notes', 'pv'];
    const isSearchOverride = searchOverridePages.includes(currentPage);

    const searchClear = document.getElementById('adminSearchClear');
    const searchWrap = document.getElementById('topbarSearch');

    if (searchInput && searchResults) {
        function updateClear() {
            if (searchClear) searchClear.style.display = searchInput.value.length > 0 ? '' : 'none';
        }

        searchInput.addEventListener('input', () => {
            updateClear();
            clearTimeout(searchTimer);
            const raw = searchInput.value.trim();
            const isMention = raw.startsWith('@');
            const q = isMention ? raw.slice(1) : raw;

            if (isMention && q.length >= 2) {
                searchTimer = setTimeout(() => runUserSearch(q), 200);
            } else if (!isSearchOverride && q.length >= 3) {
                searchTimer = setTimeout(() => runUserSearch(q), 250);
            } else {
                searchResults.classList.remove('show');
            }
        });

        searchInput.addEventListener('focus', () => {
            if (searchWrap) searchWrap.classList.add('expanded');
            const raw = searchInput.value.trim();
            const isMention = raw.startsWith('@');
            const q = isMention ? raw.slice(1) : raw;
            if ((isMention && q.length >= 2) || (!isSearchOverride && q.length >= 3)) runUserSearch(q);
        });

        searchInput.addEventListener('blur', () => {
            setTimeout(() => { if (searchWrap) searchWrap.classList.remove('expanded'); }, 200);
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchResults.classList.remove('show');
                updateClear();
                searchInput.focus();
            });
        }

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { searchInput.value = ''; searchResults.classList.remove('show'); updateClear(); searchInput.blur(); }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#topbarSearch')) searchResults.classList.remove('show');
        });

        async function runUserSearch(q) {
            const res = await adminApiPost('admin_search_users', { q });
            const users = res.users || [];
            if (!users.length) {
                searchResults.innerHTML = '<div class="p-3 text-center text-muted" style="font-size:0.85rem">Aucun résultat</div>';
                searchResults.classList.add('show');
                return;
            }
            searchResults.innerHTML = users.map(u => {
                const initials = (u.prenom?.[0] || '') + (u.nom?.[0] || '');
                const meta = [u.fonction_code, u.modules_codes].filter(Boolean).join(' — ');
                return `<a class="search-result-item" href="${AdminURL.page('user-detail', u.id)}">
                    <div class="search-result-avatar">${escapeHtml(initials.toUpperCase())}</div>
                    <div>
                        <div class="search-result-name">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</div>
                        <div class="search-result-meta">${escapeHtml(meta)}</div>
                    </div>
                </a>`;
            }).join('');
            searchResults.classList.add('show');
        }
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
            const res = await adminApiPost('admin_get_email_stats');
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
