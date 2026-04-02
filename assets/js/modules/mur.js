/**
 * Mur social — Facebook-style wall for zerdaTime
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

let currentPage = 1;
let totalPages = 1;
let loading = false;
let wallConfig = {};

export async function init() {
    const user = window.__ZT__?.user;
    if (!user) return;

    // Load config + stats in parallel
    const [cfgRes, statsRes] = await Promise.all([
        apiPost('get_mur_config'),
        apiPost('get_mur_stats'),
    ]);

    if (cfgRes.success) {
        wallConfig = cfgRes.config;
        setupComposer(user);
        renderRules();
    }

    if (statsRes.success) {
        animateNum('murStatPosts', statsRes.total_posts);
        animateNum('murStatToday', statsRes.posts_today);
        animateNum('murStatContributors', statsRes.contributors);
    }

    // Load more button
    document.getElementById('btnLoadMore')?.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadFeed(true);
        }
    });

    loadFeed(false);
}

export function destroy() {
    currentPage = 1;
    totalPages = 1;
    loading = false;
    wallConfig = {};
}

// ══════════════════════════════════════
// COMPOSER
// ══════════════════════════════════════
function setupComposer(user) {
    // Avatar
    const av = document.getElementById('composerAvatar');
    if (av) {
        if (user.photo) {
            av.innerHTML = `<img src="${escapeHtml(user.photo)}" alt="">`;
        } else {
            av.textContent = ((user.prenom || '')[0] || '') + ((user.nom || '')[0] || '');
        }
    }

    // Categories
    const catSelect = document.getElementById('composerCategory');
    if (catSelect) {
        const cats = (wallConfig.post_categories || 'general').split(',').map(c => c.trim());
        const catLabels = { general: 'Général', info: 'Info', evenement: 'Événement', social: 'Social' };
        catSelect.innerHTML = cats.map(c => `<option value="${c}">${escapeHtml(catLabels[c] || c)}</option>`).join('');
    }

    // Anonymous checkbox
    const anonWrap = document.getElementById('composerAnonWrap');
    if (anonWrap && wallConfig.allow_anonymous_comments === '1') {
        anonWrap.style.display = '';
    }

    // Post button
    document.getElementById('btnPost')?.addEventListener('click', submitPost);

    // Ctrl+Enter to post
    document.getElementById('composerBody')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) submitPost();
    });
}

async function submitPost() {
    const bodyEl = document.getElementById('composerBody');
    const body = bodyEl?.value?.trim();
    if (!body) return;

    const btn = document.getElementById('btnPost');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner spinner-sm"></span>'; }

    const data = {
        body,
        category: document.getElementById('composerCategory')?.value || 'general',
        is_anonymous: document.getElementById('composerAnon')?.checked ? 1 : 0,
    };

    const res = await apiPost('create_mur_post', data);

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Publier'; }

    if (res.success) {
        bodyEl.value = '';
        toast(res.message || 'Post publié');
        currentPage = 1;
        loadFeed(false);
        // Update today stat
        const el = document.getElementById('murStatToday');
        if (el) el.textContent = parseInt(el.textContent || '0') + 1;
    } else {
        toast(res.message || 'Erreur');
    }
}

// ══════════════════════════════════════
// FEED
// ══════════════════════════════════════
async function loadFeed(append) {
    if (loading) return;
    loading = true;

    const feed = document.getElementById('murFeed');
    if (!append) feed.innerHTML = '<div class="mur-loading"><span class="spinner"></span></div>';

    const res = await apiPost('get_mur_feed', { page: currentPage, limit: 15 });
    loading = false;

    if (!res.success) {
        if (!append) feed.innerHTML = '<div class="mur-empty-feed"><i class="bi bi-exclamation-triangle"></i><p>Erreur de chargement</p></div>';
        return;
    }

    totalPages = res.total_pages || 1;
    const posts = res.posts || [];

    if (!append) feed.innerHTML = '';

    if (posts.length === 0 && currentPage === 1) {
        feed.innerHTML = `<div class="mur-empty-feed">
            <i class="bi bi-chat-square-text"></i>
            <p>Aucune publication pour le moment</p>
            <span>Soyez le premier à publier !</span>
        </div>`;
    } else {
        for (const post of posts) {
            feed.insertAdjacentHTML('beforeend', renderPost(post));
        }
        setupPostHandlers(feed);
    }

    // Load more button
    const loadMore = document.getElementById('murLoadMore');
    if (loadMore) {
        loadMore.style.display = currentPage < totalPages ? '' : 'none';
    }
}

// ══════════════════════════════════════
// POST RENDERING
// ══════════════════════════════════════
function renderPost(post) {
    const user = window.__ZT__?.user;
    const isOwn = user?.id === post.user_id;
    const initials = ((post.prenom || '')[0] || '') + ((post.nom || '')[0] || '');
    const displayName = escapeHtml((post.prenom || '') + ' ' + (post.nom || '')).trim() || 'Anonyme';
    const time = timeAgo(post.created_at);
    const liked = post.is_liked;
    const catLabels = { general: 'Général', info: 'Info', evenement: 'Événement', social: 'Social' };

    const avatarHtml = post.avatar_url
        ? `<img src="${escapeHtml(post.avatar_url)}" alt="" class="mur-post-avatar">`
        : `<div class="mur-post-avatar mur-post-avatar-initials">${escapeHtml(initials || '?')}</div>`;

    const likeSection = wallConfig.allow_likes !== '0' ? `
        <button class="mur-action-btn mur-like-btn ${liked ? 'liked' : ''}" data-id="${post.id}" data-type="post">
            <i class="bi ${liked ? 'bi-heart-fill' : 'bi-heart'}"></i>
            <span class="mur-like-count">${post.likes_count || 0}</span>
        </button>` : '';

    const commentSection = wallConfig.allow_comments !== '0' ? `
        <button class="mur-action-btn mur-comment-toggle" data-id="${post.id}">
            <i class="bi bi-chat"></i>
            <span class="mur-comment-count">${post.comments_count || 0}</span>
        </button>` : '';

    return `<div class="mur-post" data-post-id="${post.id}">
        <div class="mur-post-header">
            ${avatarHtml}
            <div class="mur-post-meta">
                <div class="mur-post-name">${displayName}${post.fonction_nom ? ` <span class="mur-post-fonction">${escapeHtml(post.fonction_nom)}</span>` : ''}</div>
                <div class="mur-post-time">
                    ${time}
                    ${post.is_pinned == 1 ? '<i class="bi bi-pin-fill mur-pin-icon" title="Épinglé"></i>' : ''}
                    <span class="mur-post-cat">${escapeHtml(catLabels[post.category] || post.category)}</span>
                </div>
            </div>
            ${isOwn ? `<div class="mur-post-actions">
                <button class="mur-action-btn mur-edit-btn" data-id="${post.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                <button class="mur-action-btn mur-delete-btn" data-id="${post.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
            </div>` : ''}
        </div>
        <div class="mur-post-body">${escapeHtml(post.body)}</div>
        <div class="mur-post-footer">
            ${likeSection}
            ${commentSection}
        </div>
        <div class="mur-comments-section" data-post-id="${post.id}" style="display:none"></div>
    </div>`;
}

// ══════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════
function setupPostHandlers(container) {
    // Like
    container.querySelectorAll('.mur-like-btn:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const res = await apiPost('toggle_mur_like', { target_type: 'post', target_id: id });
            if (res.success) {
                const icon = btn.querySelector('i');
                const count = btn.querySelector('.mur-like-count');
                btn.classList.toggle('liked', res.liked);
                icon.className = res.liked ? 'bi bi-heart-fill' : 'bi bi-heart';
                if (count) count.textContent = res.count;
            }
        });
    });

    // Comment toggle
    container.querySelectorAll('.mur-comment-toggle:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const postId = btn.dataset.id;
            const section = container.querySelector(`.mur-comments-section[data-post-id="${postId}"]`);
            if (!section) return;
            if (section.style.display === 'none') {
                section.style.display = '';
                loadComments(postId, section);
            } else {
                section.style.display = 'none';
            }
        });
    });

    // Delete
    container.querySelectorAll('.mur-delete-btn:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            if (!confirm('Supprimer ce post ?')) return;
            const res = await apiPost('delete_mur_post', { id: btn.dataset.id });
            if (res.success) {
                btn.closest('.mur-post')?.remove();
                toast('Post supprimé');
            }
        });
    });

    // Edit
    container.querySelectorAll('.mur-edit-btn:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const post = btn.closest('.mur-post');
            const bodyEl = post?.querySelector('.mur-post-body');
            if (!bodyEl) return;

            const currentText = bodyEl.textContent;
            bodyEl.innerHTML = `<textarea class="mur-edit-area">${escapeHtml(currentText)}</textarea>
                <div class="mur-edit-actions">
                    <button class="mur-btn-save" data-id="${btn.dataset.id}">Enregistrer</button>
                    <button class="mur-btn-cancel">Annuler</button>
                </div>`;

            bodyEl.querySelector('.mur-btn-cancel')?.addEventListener('click', () => {
                bodyEl.textContent = currentText;
            });

            bodyEl.querySelector('.mur-btn-save')?.addEventListener('click', async () => {
                const newBody = bodyEl.querySelector('.mur-edit-area')?.value?.trim();
                if (!newBody) return;
                const res = await apiPost('update_mur_post', { id: btn.dataset.id, body: newBody });
                if (res.success) {
                    bodyEl.textContent = newBody;
                    toast('Post modifié');
                } else {
                    toast(res.message || 'Erreur');
                }
            });
        });
    });
}

// ══════════════════════════════════════
// COMMENTS
// ══════════════════════════════════════
async function loadComments(postId, container) {
    container.innerHTML = '<div class="mur-loading"><span class="spinner spinner-sm"></span></div>';

    const res = await apiPost('get_mur_comments', { post_id: postId });
    if (!res.success) {
        container.innerHTML = '<div class="mur-comment-error">Erreur</div>';
        return;
    }

    const comments = res.comments || [];
    const user = window.__ZT__?.user;
    const allowAnon = wallConfig.allow_anonymous_comments === '1';

    let html = '<div class="mur-comment-list">';
    html += comments.map(c => {
        const initials = ((c.prenom || '')[0] || '') + ((c.nom || '')[0] || '');
        const name = escapeHtml(((c.prenom || '') + ' ' + (c.nom || '')).trim() || 'Anonyme');
        const isOwn = user?.id === c.user_id;

        return `<div class="mur-comment" data-comment-id="${c.id}">
            <div class="mur-comment-avatar">${c.avatar_url ? `<img src="${escapeHtml(c.avatar_url)}" alt="">` : escapeHtml(initials || '?')}</div>
            <div class="mur-comment-content">
                <div class="mur-comment-header">
                    <span class="mur-comment-name">${name}</span>
                    <span class="mur-comment-time">${timeAgo(c.created_at)}</span>
                    ${isOwn ? `<button class="mur-comment-del" data-id="${c.id}" data-post-id="${postId}"><i class="bi bi-x"></i></button>` : ''}
                </div>
                <div class="mur-comment-body">${escapeHtml(c.body)}</div>
            </div>
        </div>`;
    }).join('');
    html += '</div>';

    // Comment input
    if (user) {
        html += `<div class="mur-comment-input">
            <input type="text" class="mur-comment-text" placeholder="Écrire un commentaire..." data-post-id="${postId}">
            ${allowAnon ? '<label class="mur-comment-anon-label"><input type="checkbox" class="mur-comment-anon"> Anonyme</label>' : ''}
            <button class="mur-comment-send" data-post-id="${postId}"><i class="bi bi-send"></i></button>
        </div>`;
    }

    container.innerHTML = html;

    // Delete comment
    container.querySelectorAll('.mur-comment-del').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res2 = await apiPost('delete_mur_comment', { id: btn.dataset.id });
            if (res2.success) {
                btn.closest('.mur-comment')?.remove();
                const countEl = document.querySelector(`.mur-comment-toggle[data-id="${btn.dataset.postId}"] .mur-comment-count`);
                if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
            }
        });
    });

    // Send comment
    const sendBtn = container.querySelector('.mur-comment-send');
    const inputEl = container.querySelector('.mur-comment-text');
    if (sendBtn && inputEl) {
        const submit = async () => {
            const body = inputEl.value.trim();
            if (!body) return;
            const isAnon = container.querySelector('.mur-comment-anon')?.checked ? 1 : 0;
            const res2 = await apiPost('add_mur_comment', { post_id: postId, body, is_anonymous: isAnon });
            if (res2.success) {
                inputEl.value = '';
                toast('Commentaire ajouté');
                loadComments(postId, container);
                const countEl = document.querySelector(`.mur-comment-toggle[data-id="${postId}"] .mur-comment-count`);
                if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
            } else {
                toast(res2.message || 'Erreur');
            }
        };
        sendBtn.addEventListener('click', submit);
        inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
    }
}

// ══════════════════════════════════════
// SIDEBAR RULES
// ══════════════════════════════════════
function renderRules() {
    const el = document.getElementById('murRules');
    if (!el) return;

    const rules = [];
    if (wallConfig.allow_private_posts === '1') {
        rules.push('<i class="bi bi-check-circle text-success"></i> Posts personnels autorisés');
    } else {
        rules.push('<i class="bi bi-shield-check"></i> Posts professionnels uniquement');
    }

    if (wallConfig.allow_anonymous_comments === '1') {
        rules.push('<i class="bi bi-incognito"></i> Commentaires anonymes autorisés');
    } else {
        rules.push('<i class="bi bi-person-check"></i> Commentaires identifiés obligatoires');
    }

    if (wallConfig.allow_comments === '0') {
        rules.push('<i class="bi bi-chat-left-dots"></i> Commentaires désactivés');
    }
    if (wallConfig.allow_likes === '0') {
        rules.push('<i class="bi bi-heart"></i> Likes désactivés');
    }

    const moderation = wallConfig.moderation_enabled === undefined ? false : wallConfig.moderation_enabled;
    // Note: moderation_enabled is not exposed to employees, but we can infer from config
    rules.push('<i class="bi bi-people"></i> Respect et bienveillance');

    el.innerHTML = rules.map(r => `<div class="mur-rule">${r}</div>`).join('');
}

// ══════════════════════════════════════
// HELPERS
// ══════════════════════════════════════
function timeAgo(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'à l\'instant';
    if (diff < 3600) return `il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `il y a ${Math.floor(diff / 3600)}h`;
    if (diff < 604800) return `il y a ${Math.floor(diff / 86400)}j`;

    return date.toLocaleDateString('fr-CH', { day: '2-digit', month: 'short' });
}

function animateNum(id, target) {
    const el = document.getElementById(id);
    if (!el) return;
    if (target === 0) { el.textContent = '0'; return; }

    const duration = 500;
    const start = performance.now();
    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const ease = 1 - (1 - progress) * (1 - progress);
        el.textContent = Math.round(target * ease);
        if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}
