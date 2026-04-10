<?php
/**
 * Admin — Analytics Wiki + Knowledge gaps
 */
require_responsable();
?>
<style>
.wa-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:24px; }
.wa-card { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:18px; }
.wa-card-label { font-size:.72rem; color:#999; text-transform:uppercase; font-weight:600; }
.wa-card-value { font-size:1.8rem; font-weight:700; color:#212529; margin-top:4px; }
.wa-card-sub { font-size:.75rem; color:#6c757d; margin-top:2px; }
.wa-card.warn .wa-card-value { color:#d49a3d; }
.wa-card.danger .wa-card-value { color:#c54a3a; }

.wa-section { background:#fff; border:1px solid #e9ecef; border-radius:10px; padding:18px; margin-bottom:18px; }
.wa-section h6 { font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.wa-section h6 .bi { color:#999; }

.wa-row { display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f1f3f5; gap:10px; }
.wa-row:last-child { border-bottom:none; }
.wa-row .wa-row-title { flex:1; font-size:.88rem; color:#212529; }
.wa-row .wa-row-meta { font-size:.75rem; color:#999; }
.wa-row .wa-row-count { font-weight:700; color:#2d4a43; min-width:48px; text-align:right; }

.wa-badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:.7rem; font-weight:600; }
.wa-badge-warn { background:#fff3cd; color:#856404; }
.wa-badge-empty { background:#fde2e0; color:#9b3729; }

.wa-empty { text-align:center; color:#adb5bd; padding:30px; font-size:.88rem; }
.wa-period-tabs { display:inline-flex; background:#f1f3f5; border-radius:8px; padding:3px; margin-bottom:18px; }
.wa-period-tabs button { background:none; border:none; padding:5px 14px; border-radius:6px; font-size:.8rem; color:#6c757d; cursor:pointer; }
.wa-period-tabs button.active { background:#fff; color:#212529; box-shadow:0 1px 2px rgba(0,0,0,.06); font-weight:600; }

.wa-create-btn { background:none; border:1px solid #2d4a43; color:#2d4a43; padding:3px 10px; border-radius:6px; font-size:.72rem; cursor:pointer; transition:all .15s; }
.wa-create-btn:hover { background:#2d4a43; color:#fff; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Analytics Wiki</h4>
            <p class="text-muted small mb-0">Vues, recherches sans résultat, cartes orphelines</p>
        </div>
        <div class="wa-period-tabs">
            <button data-days="7">7 jours</button>
            <button data-days="30" class="active">30 jours</button>
            <button data-days="90">90 jours</button>
        </div>
    </div>

    <div class="wa-cards" id="waCards"></div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="wa-section">
                <h6><i class="bi bi-fire"></i>Pages les plus consultées</h6>
                <div id="waTopPages"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="wa-section">
                <h6><i class="bi bi-search-heart"></i>Knowledge Gaps — Recherches sans résultat</h6>
                <div id="waEmptySearches"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="wa-section">
                <h6><i class="bi bi-archive"></i>Cartes orphelines (90+ jours sans màj, 0 vue)</h6>
                <div id="waOrphans"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="wa-section">
                <h6><i class="bi bi-search"></i>Top recherches</h6>
                <div id="waTopSearches"></div>
            </div>
        </div>
    </div>
</div>

<script<?= function_exists('nonce') ? nonce() : '' ?>>
(function(){
    let currentDays = 30;

    async function load() {
        const [analyt, gaps] = await Promise.all([
            adminApiPost('admin_get_wiki_analytics', { days: currentDays }),
            adminApiPost('admin_get_knowledge_gaps', { days: currentDays }),
        ]);

        if (!analyt.success || !gaps.success) return;

        // KPI cards
        const t = analyt.totals;
        document.getElementById('waCards').innerHTML = `
            <div class="wa-card"><div class="wa-card-label">Pages totales</div><div class="wa-card-value">${t.pages}</div><div class="wa-card-sub">actives</div></div>
            <div class="wa-card"><div class="wa-card-label">Vues</div><div class="wa-card-value">${t.views}</div><div class="wa-card-sub">${currentDays} derniers jours</div></div>
            <div class="wa-card"><div class="wa-card-label">Lecteurs uniques</div><div class="wa-card-value">${t.unique_viewers}</div><div class="wa-card-sub">collaborateurs distincts</div></div>
            <div class="wa-card ${t.expired > 0 ? 'danger' : ''}"><div class="wa-card-label">Vérifications expirées</div><div class="wa-card-value">${t.expired}</div><div class="wa-card-sub">à mettre à jour</div></div>
        `;

        // Top pages
        const tp = document.getElementById('waTopPages');
        tp.innerHTML = analyt.top_pages.length
            ? analyt.top_pages.map(p => `
                <div class="wa-row">
                    <div class="wa-row-title">${escapeHtml(p.titre)}</div>
                    <div class="wa-row-count">${p.views} <i class="bi bi-eye" style="color:#999"></i></div>
                </div>`).join('')
            : '<div class="wa-empty">Aucune vue dans cette période</div>';

        // Orphans
        const o = document.getElementById('waOrphans');
        o.innerHTML = analyt.orphans.length
            ? analyt.orphans.map(p => {
                const date = p.updated_at ? new Date(p.updated_at).toLocaleDateString('fr-CH') : '';
                return `<div class="wa-row">
                    <div class="wa-row-title">${escapeHtml(p.titre)}<div class="wa-row-meta">Dernière màj : ${date}</div></div>
                    <a href="/spoccare/wiki/${p.id}" class="wa-create-btn" style="text-decoration:none">Voir</a>
                </div>`;
            }).join('')
            : '<div class="wa-empty">Aucune carte orpheline 🎉</div>';

        // Empty searches
        const es = document.getElementById('waEmptySearches');
        es.innerHTML = gaps.empty_searches.length
            ? gaps.empty_searches.map(s => `
                <div class="wa-row">
                    <div class="wa-row-title">${escapeHtml(s.q)} <span class="wa-badge wa-badge-empty">${s.hits} ×</span></div>
                    <a href="/spoccare/wiki-edit?titre=${encodeURIComponent(s.q)}" class="wa-create-btn" style="text-decoration:none">Créer</a>
                </div>`).join('')
            : '<div class="wa-empty">Aucune recherche sans résultat — votre base est complète !</div>';

        // Top searches
        const ts = document.getElementById('waTopSearches');
        ts.innerHTML = gaps.top_searches.length
            ? gaps.top_searches.map(s => `
                <div class="wa-row">
                    <div class="wa-row-title">${escapeHtml(s.q)}</div>
                    <div class="wa-row-count">${s.hits}</div>
                </div>`).join('')
            : '<div class="wa-empty">Aucune recherche enregistrée</div>';
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    document.querySelectorAll('.wa-period-tabs button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.wa-period-tabs button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentDays = parseInt(btn.dataset.days);
            load();
        });
    });

    load();
})();
</script>
