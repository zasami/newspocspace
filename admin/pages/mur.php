<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$config = Db::fetchAll("SELECT config_key, config_value FROM mur_config");
$cfg = [];
foreach ($config as $r) $cfg[$r['config_key']] = $r['config_value'];

$pendingCount = (int) Db::getOne("SELECT COUNT(*) FROM mur_posts WHERE deleted_at IS NULL AND status = 'pending'");
?>
<style<?= nonce() ?>>
/* ── Tabs ── */
.mur-tabs { display: flex; gap: 4px; background: var(--cl-bg); border-radius: 12px; padding: 4px; width: fit-content; margin-bottom: 20px; }
.mur-tab { padding: 8px 18px; border-radius: 10px; font-size: .82rem; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--cl-text-muted); transition: var(--cl-transition); position: relative; }
.mur-tab.active { background: var(--cl-surface); color: var(--cl-text); box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.mur-tab .mur-tab-badge { position: absolute; top: 2px; right: 2px; background: #E2B8AE; color: #7B3B2C; font-size: .6rem; padding: 1px 5px; border-radius: 8px; font-weight: 700; }

/* ── Stats ── */
.mur-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.mur-stat-card { background: var(--cl-surface); border: 1.5px solid var(--cl-border-light); border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 12px; min-width: 160px; }
.mur-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.mur-stat-icon.bg-teal { background: #bcd2cb; color: #2d4a43; }
.mur-stat-icon.bg-orange { background: #D4C4A8; color: #6B5B3E; }
.mur-stat-icon.bg-red { background: #E2B8AE; color: #7B3B2C; }
.mur-stat-icon.bg-blue { background: #B8C9D4; color: #3B4F6B; }
.mur-stat-icon.bg-purple { background: #D0C4D8; color: #5B4B6B; }
.mur-stat-value { font-size: 1.3rem; font-weight: 700; line-height: 1; }
.mur-stat-label { font-size: .72rem; color: var(--cl-text-muted); text-transform: uppercase; letter-spacing: .3px; }

/* ── Config panel ── */
.mur-config-card { background: var(--cl-surface); border: 1.5px solid var(--cl-border-light); border-radius: 14px; padding: 24px; }
.mur-config-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--cl-border-light); }
.mur-config-row:last-child { border-bottom: none; }
.mur-config-label { font-weight: 600; font-size: .88rem; }
.mur-config-hint { font-size: .75rem; color: var(--cl-text-muted); margin-top: 2px; }

/* ── Toggle switch ── */
.mur-toggle { position: relative; width: 44px; height: 24px; }
.mur-toggle input { opacity: 0; width: 0; height: 0; }
.mur-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: .3s; }
.mur-toggle-slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; }
.mur-toggle input:checked + .mur-toggle-slider { background: #bcd2cb; }
.mur-toggle input:checked + .mur-toggle-slider::before { transform: translateX(20px); }

/* ── Post list (moderation) ── */
.mur-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light); background: var(--cl-surface); }
.mur-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.mur-table th { background: var(--cl-bg); font-size: .72rem; text-transform: uppercase; letter-spacing: .3px; padding: 10px 14px; font-weight: 600; color: var(--cl-text-muted); }
.mur-table th:first-child { border-top-left-radius: 14px; }
.mur-table th:last-child { border-top-right-radius: 14px; }
.mur-table td { padding: 12px 14px; border-top: 1px solid var(--cl-border-light); vertical-align: middle; }
.mur-table tr:hover { background: rgba(188,210,203,.06); }

.mur-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.mur-avatar-initials { width: 32px; height: 32px; border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 600; }
.mur-post-body { max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.mur-badge { font-size: .72rem; padding: 3px 10px; border-radius: 8px; font-weight: 600; }
.mur-badge-pending { background: #D4C4A8; color: #6B5B3E; }
.mur-badge-approved { background: #bcd2cb; color: #2d4a43; }
.mur-badge-rejected { background: #E2B8AE; color: #7B3B2C; }

.mur-row-btn { background: none; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: var(--cl-transition); }
.mur-row-btn:hover { background: var(--cl-bg); }
.mur-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }
.mur-row-btn.success:hover { background: #bcd2cb; color: #2d4a43; }

.mur-empty { text-align: center; padding: 40px; color: var(--cl-text-muted); }
.mur-empty i { font-size: 2rem; opacity: .4; display: block; margin-bottom: 8px; }

/* ── Filter pills ── */
.mur-filters { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.mur-filter-pill { padding: 6px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; border: 1.5px solid var(--cl-border-light); background: var(--cl-surface); cursor: pointer; transition: var(--cl-transition); }
.mur-filter-pill.active { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.mur-filter-pill:hover:not(.active) { border-color: var(--cl-border-hover); }

/* ── Categories input ── */
.mur-categories-input { font-size: .85rem; }

@media (max-width: 768px) {
    .mur-stats { flex-direction: column; }
    .mur-config-row { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>

<!-- Stats -->
<div class="mur-stats" id="murStats">
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-teal"><i class="bi bi-chat-square-text"></i></div>
        <div><div class="mur-stat-value" id="statTotal">-</div><div class="mur-stat-label">Posts total</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-orange"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="mur-stat-value" id="statPending">-</div><div class="mur-stat-label">En attente</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-blue"><i class="bi bi-calendar-event"></i></div>
        <div><div class="mur-stat-value" id="statToday">-</div><div class="mur-stat-label">Aujourd'hui</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-purple"><i class="bi bi-chat-dots"></i></div>
        <div><div class="mur-stat-value" id="statComments">-</div><div class="mur-stat-label">Commentaires</div></div>
    </div>
    <div class="mur-stat-card">
        <div class="mur-stat-icon bg-red"><i class="bi bi-heart"></i></div>
        <div><div class="mur-stat-value" id="statLikes">-</div><div class="mur-stat-label">Likes</div></div>
    </div>
</div>

<!-- Tabs -->
<div class="mur-tabs">
    <button class="mur-tab active" data-tab="moderation">
        Modération
        <?php if ($pendingCount > 0): ?><span class="mur-tab-badge" id="pendingBadge"><?= $pendingCount ?></span><?php endif; ?>
    </button>
    <button class="mur-tab" data-tab="config">Paramètres</button>
</div>

<!-- TAB: Moderation -->
<div id="tab-moderation">
    <div class="mur-filters">
        <button class="mur-filter-pill active" data-filter="all">Tous</button>
        <button class="mur-filter-pill" data-filter="pending">En attente</button>
        <button class="mur-filter-pill" data-filter="approved">Approuvés</button>
        <button class="mur-filter-pill" data-filter="rejected">Rejetés</button>
    </div>

    <div class="mur-table-wrap">
        <table class="mur-table">
            <thead>
                <tr>
                    <th style="width:5%"></th>
                    <th style="width:20%">Auteur</th>
                    <th style="width:35%">Contenu</th>
                    <th style="width:10%">Catégorie</th>
                    <th style="width:10%">Statut</th>
                    <th style="width:10%">Date</th>
                    <th style="width:10%; text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="murPostsBody">
                <tr><td colspan="7" class="mur-empty"><span class="spinner"></span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3 gap-2" id="murPagination"></div>
</div>

<!-- TAB: Config -->
<div id="tab-config" style="display:none">
    <div class="mur-config-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2"></i>Règles du mur</h6>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Modération avant publication</div>
                <div class="mur-config-hint">Les posts doivent être approuvés par un admin avant d'apparaître sur le mur</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgModeration" <?= ($cfg['moderation_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Commentaires anonymes autorisés</div>
                <div class="mur-config-hint">Les utilisateurs peuvent commenter sans que leur nom soit visible</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgAnonymousComments" <?= ($cfg['allow_anonymous_comments'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Posts personnels tolérés</div>
                <div class="mur-config-hint">Autoriser les posts non-professionnels (anniversaires, sorties, etc.)</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgPrivatePosts" <?= ($cfg['allow_private_posts'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Activer les commentaires</div>
                <div class="mur-config-hint">Les utilisateurs peuvent commenter les posts</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgComments" <?= ($cfg['allow_comments'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Activer les likes</div>
                <div class="mur-config-hint">Les utilisateurs peuvent liker les posts et commentaires</div>
            </div>
            <label class="mur-toggle">
                <input type="checkbox" id="cfgLikes" <?= ($cfg['allow_likes'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="mur-toggle-slider"></span>
            </label>
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Limite de posts par jour</div>
                <div class="mur-config-hint">Nombre maximum de posts qu'un utilisateur peut publier par jour</div>
            </div>
            <input type="number" id="cfgMaxPosts" class="form-control form-control-sm" style="width:80px" min="1" max="50" value="<?= h($cfg['max_posts_per_day'] ?? '10') ?>">
        </div>

        <div class="mur-config-row">
            <div>
                <div class="mur-config-label">Catégories disponibles</div>
                <div class="mur-config-hint">Liste séparée par des virgules (ex: general, info, evenement, social)</div>
            </div>
            <input type="text" id="cfgCategories" class="form-control form-control-sm mur-categories-input" style="width:300px" value="<?= h($cfg['post_categories'] ?? 'general,info,evenement,social') ?>">
        </div>

        <div class="mt-3 text-end">
            <button class="btn btn-primary btn-sm" id="btnSaveConfig"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    const $ = s => document.querySelector(s);
    const $$ = s => document.querySelectorAll(s);
    let currentFilter = 'all';
    let currentPage = 1;

    // ── Tabs ──
    $$('.mur-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            $$('.mur-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const tabId = tab.dataset.tab;
            document.getElementById('tab-moderation').style.display = tabId === 'moderation' ? '' : 'none';
            document.getElementById('tab-config').style.display = tabId === 'config' ? '' : 'none';
        });
    });

    // ── Stats ──
    async function loadStats() {
        const res = await adminApiPost('admin_get_mur_stats');
        if (!res.success) return;
        $('#statTotal').textContent = res.total_posts;
        $('#statPending').textContent = res.pending_posts;
        $('#statToday').textContent = res.posts_today;
        $('#statComments').textContent = res.total_comments;
        $('#statLikes').textContent = res.total_likes;
    }

    // ── Filters ──
    $$('.mur-filter-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            $$('.mur-filter-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentFilter = pill.dataset.filter;
            currentPage = 1;
            loadPosts();
        });
    });

    // ── Posts ──
    async function loadPosts() {
        const body = $('#murPostsBody');
        body.innerHTML = '<tr><td colspan="7" class="mur-empty"><span class="spinner"></span></td></tr>';

        const res = await adminApiPost('admin_get_mur_posts', { status: currentFilter, page: currentPage, limit: 20 });
        if (!res.success) { body.innerHTML = '<tr><td colspan="7" class="mur-empty">Erreur de chargement</td></tr>'; return; }

        if (!res.posts.length) {
            body.innerHTML = '<tr><td colspan="7" class="mur-empty"><i class="bi bi-chat-square"></i>Aucun post</td></tr>';
            $('#murPagination').innerHTML = '';
            return;
        }

        body.innerHTML = res.posts.map(p => {
            const initials = (p.prenom?.[0] || '') + (p.nom?.[0] || '');
            const avatarHtml = p.avatar_url
                ? `<img src="${escapeHtml(p.avatar_url)}" class="mur-avatar">`
                : `<div class="mur-avatar-initials">${escapeHtml(initials)}</div>`;
            const statusClass = p.status === 'pending' ? 'mur-badge-pending' : p.status === 'approved' ? 'mur-badge-approved' : 'mur-badge-rejected';
            const date = new Date(p.created_at).toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            const pinIcon = p.is_pinned == 1 ? 'bi-pin-fill text-warning' : 'bi-pin';

            return `<tr>
                <td>${p.is_pinned == 1 ? '<i class="bi bi-pin-fill text-warning" title="Épinglé"></i>' : ''}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        ${avatarHtml}
                        <div>
                            <div class="fw-semibold" style="font-size:.85rem">${escapeHtml(p.prenom + ' ' + p.nom)}</div>
                            <div style="font-size:.72rem; color:var(--cl-text-muted)">${escapeHtml(p.fonction_nom || '')}</div>
                        </div>
                    </div>
                </td>
                <td><div class="mur-post-body">${escapeHtml(p.body.replace(/<[^>]+>/g, ''))}</div></td>
                <td><span class="mur-badge" style="background:#f0f0f0">${escapeHtml(p.category)}</span></td>
                <td><span class="mur-badge ${statusClass}">${p.status === 'pending' ? 'En attente' : p.status === 'approved' ? 'Approuvé' : 'Rejeté'}</span></td>
                <td style="font-size:.8rem">${date}</td>
                <td style="text-align:center">
                    ${p.status === 'pending' ? `
                        <button class="mur-row-btn success" title="Approuver" data-action="approve" data-id="${p.id}"><i class="bi bi-check-lg"></i></button>
                        <button class="mur-row-btn danger" title="Rejeter" data-action="reject" data-id="${p.id}"><i class="bi bi-x-lg"></i></button>
                    ` : ''}
                    <button class="mur-row-btn" title="${p.is_pinned == 1 ? 'Désépingler' : 'Épingler'}" data-action="pin" data-id="${p.id}"><i class="bi ${pinIcon}"></i></button>
                    <button class="mur-row-btn danger" title="Supprimer" data-action="delete" data-id="${p.id}"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }).join('');

        // Pagination
        let pagHtml = '';
        if (res.total_pages > 1) {
            for (let i = 1; i <= res.total_pages; i++) {
                pagHtml += `<button class="btn btn-sm ${i === res.page ? 'btn-primary' : 'btn-light'}" data-page="${i}">${i}</button>`;
            }
        }
        $('#murPagination').innerHTML = pagHtml;

        // Action handlers
        body.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const id = btn.dataset.id;
                let res2;

                if (action === 'approve' || action === 'reject') {
                    res2 = await adminApiPost('admin_moderate_mur_post', { id, moderation: action });
                } else if (action === 'pin') {
                    res2 = await adminApiPost('admin_pin_mur_post', { id });
                } else if (action === 'delete') {
                    if (!confirm('Supprimer ce post ?')) return;
                    res2 = await adminApiPost('admin_delete_mur_post', { id });
                }

                if (res2?.success) {
                    showToast(res2.message || 'OK', 'success');
                    loadPosts();
                    loadStats();
                } else {
                    showToast(res2?.message || 'Erreur', 'error');
                }
            });
        });

        // Pagination handlers
        $$('#murPagination button').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page);
                loadPosts();
            });
        });
    }

    // ── Save Config ──
    $('#btnSaveConfig')?.addEventListener('click', async () => {
        const config = {
            moderation_enabled: $('#cfgModeration').checked ? '1' : '0',
            allow_anonymous_comments: $('#cfgAnonymousComments').checked ? '1' : '0',
            allow_private_posts: $('#cfgPrivatePosts').checked ? '1' : '0',
            allow_comments: $('#cfgComments').checked ? '1' : '0',
            allow_likes: $('#cfgLikes').checked ? '1' : '0',
            max_posts_per_day: $('#cfgMaxPosts').value || '10',
            post_categories: $('#cfgCategories').value || 'general,info,evenement,social',
        };

        const res = await adminApiPost('admin_save_mur_config', { config });
        if (res.success) {
            showToast('Configuration sauvegardée', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ── Init ──
    loadStats();
    loadPosts();
})();
</script>
