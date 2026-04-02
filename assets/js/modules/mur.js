/**
 * Mur social — 3-column wall with hero, media, gallery, lightbox
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

let currentPage = 1;
let totalPages = 1;
let loading = false;
let wallConfig = {};
let activeCategory = 'all';
let pendingFiles = [];
let allGalleryUrls = [];
let lightboxIndex = 0;

const CAT_LABELS = { general: 'Général', info: 'Info', evenement: 'Événement', social: 'Social' };
const CAT_COLORS = { general: '#bcd2cb', info: '#B8C9D4', evenement: '#D4C4A8', social: '#D0C4D8' };

export async function init() {
    const user = window.__ZT__?.user;
    if (!user) return;

    const [cfgRes, statsRes] = await Promise.all([
        apiPost('get_mur_config'),
        apiPost('get_mur_stats'),
    ]);

    if (cfgRes.success) {
        wallConfig = cfgRes.config;
        setupHero();
        setupComposer(user);
        renderCategoryNav();
        renderRules();
    }

    if (statsRes.success) {
        animateNum('murStatPosts', statsRes.total_posts);
        animateNum('murStatComments', statsRes.posts_today);
        animateNum('murStatContributors', statsRes.contributors);
    }

    document.getElementById('btnLoadMore')?.addEventListener('click', () => {
        if (currentPage < totalPages) { currentPage++; loadFeed(true); }
    });

    setupLightbox();
    loadFeed(false);
    loadGallery();
    loadContributors();
}

export function destroy() {
    currentPage = 1; totalPages = 1; loading = false;
    wallConfig = {}; activeCategory = 'all'; pendingFiles = [];
    allGalleryUrls = []; lightboxIndex = 0;
}

// ══════════════════════════════════════
// HERO
// ══════════════════════════════════════
function setupHero() {
    if (wallConfig.hero_image) {
        const cover = document.getElementById('murHeroCover');
        if (cover) cover.style.backgroundImage = `url('${wallConfig.hero_image}')`;
    }
    if (wallConfig.ems_logo) {
        const logo = document.getElementById('murHeroLogo');
        if (logo) logo.src = wallConfig.ems_logo;
    }
    if (wallConfig.hero_title) {
        const el = document.getElementById('murHeroTitle');
        if (el) el.textContent = wallConfig.hero_title;
    }
    if (wallConfig.hero_subtitle) {
        const el = document.getElementById('murHeroSubtitle');
        if (el) el.textContent = wallConfig.hero_subtitle;
    }
}

// ══════════════════════════════════════
// LEFT SIDEBAR
// ══════════════════════════════════════
function renderCategoryNav() {
    const nav = document.getElementById('murCatNav');
    if (!nav) return;
    const cats = (wallConfig.post_categories || 'general').split(',').map(c => c.trim());

    let html = `<button class="mur-cat-item active" data-cat="all"><i class="bi bi-house-door mur-cat-icon"></i> Tout voir</button>`;
    for (const cat of cats) {
        html += `<button class="mur-cat-item" data-cat="${cat}"><span class="mur-cat-dot" style="background:${CAT_COLORS[cat] || '#ccc'}"></span> ${escapeHtml(CAT_LABELS[cat] || cat)}</button>`;
    }
    nav.innerHTML = html;

    nav.querySelectorAll('.mur-cat-item').forEach(btn => {
        btn.addEventListener('click', () => {
            nav.querySelectorAll('.mur-cat-item').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCategory = btn.dataset.cat;
            currentPage = 1;
            loadFeed(false);
        });
    });
}

function renderRules() {
    const el = document.getElementById('murRules');
    if (!el) return;
    const rules = [];
    rules.push(wallConfig.allow_private_posts === '1'
        ? '<i class="bi bi-check-circle"></i> Posts perso autorisés'
        : '<i class="bi bi-shield-check"></i> Posts pro uniquement');
    rules.push(wallConfig.allow_anonymous_comments === '1'
        ? '<i class="bi bi-incognito"></i> Anonymat autorisé'
        : '<i class="bi bi-person-check"></i> Identité obligatoire');
    if (wallConfig.allow_comments === '0') rules.push('<i class="bi bi-chat-left-dots"></i> Commentaires off');
    if (wallConfig.allow_likes === '0') rules.push('<i class="bi bi-heart"></i> Likes off');
    rules.push('<i class="bi bi-people"></i> Respect et bienveillance');
    el.innerHTML = rules.map(r => `<div class="mur-rule">${r}</div>`).join('');
}

// ══════════════════════════════════════
// RIGHT SIDEBAR: Gallery + Contributors
// ══════════════════════════════════════
async function loadGallery() {
    const el = document.getElementById('murGallery');
    if (!el) return;

    const res = await apiPost('get_mur_gallery', { limit: 8 });
    if (!res.success || !res.media?.length) return;

    allGalleryUrls = res.media.map(m => m.url);
    el.innerHTML = res.media.map((m, i) =>
        `<div class="mur-gallery-thumb" data-lightbox="${i}"><img src="${escapeHtml(m.url)}" alt="" loading="lazy"></div>`
    ).join('');

    el.querySelectorAll('.mur-gallery-thumb').forEach(thumb => {
        thumb.addEventListener('click', () => openLightbox(allGalleryUrls, parseInt(thumb.dataset.lightbox)));
    });
}

async function loadContributors() {
    const el = document.getElementById('murTopContributors');
    if (!el) return;

    const res = await apiPost('get_mur_feed', { page: 1, limit: 50 });
    if (!res.success) return;

    const posts = res.posts || [];
    const userCounts = {};
    for (const p of posts) {
        const key = p.user_id || 'anon';
        if (!userCounts[key]) userCounts[key] = { prenom: p.prenom, nom: p.nom, avatar_url: p.avatar_url, count: 0 };
        userCounts[key].count++;
    }
    const topUsers = Object.values(userCounts).sort((a, b) => b.count - a.count).slice(0, 5);
    if (!topUsers.length) return;

    el.innerHTML = topUsers.map(u => {
        const initials = ((u.prenom || '')[0] || '') + ((u.nom || '')[0] || '');
        const name = ((u.prenom || '') + ' ' + (u.nom || '')).trim() || 'Anonyme';
        return `<div class="mur-widget-item">
            <div class="mur-widget-avatar">${u.avatar_url ? `<img src="${escapeHtml(u.avatar_url)}" alt="">` : escapeHtml(initials)}</div>
            <div class="mur-widget-text">${escapeHtml(name)}</div>
            <div class="mur-widget-meta">${u.count} post${u.count > 1 ? 's' : ''}</div>
        </div>`;
    }).join('');
}

// ══════════════════════════════════════
// LIGHTBOX
// ══════════════════════════════════════
function setupLightbox() {
    document.getElementById('murLightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('murLightboxOverlay')?.addEventListener('click', closeLightbox);
    document.querySelector('.mur-lightbox-overlay')?.addEventListener('click', closeLightbox);
    document.getElementById('murLightboxPrev')?.addEventListener('click', () => navigateLightbox(-1));
    document.getElementById('murLightboxNext')?.addEventListener('click', () => navigateLightbox(1));
    document.addEventListener('keydown', (e) => {
        const lb = document.getElementById('murLightbox');
        if (!lb || lb.style.display === 'none') return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });
}

function openLightbox(urls, index) {
    allGalleryUrls = urls;
    lightboxIndex = index;
    const lb = document.getElementById('murLightbox');
    const img = document.getElementById('murLightboxImg');
    if (!lb || !img) return;
    img.src = urls[index];
    lb.style.display = 'flex';
    document.getElementById('murLightboxPrev').style.display = urls.length > 1 ? '' : 'none';
    document.getElementById('murLightboxNext').style.display = urls.length > 1 ? '' : 'none';
}

function closeLightbox() {
    const lb = document.getElementById('murLightbox');
    if (lb) lb.style.display = 'none';
}

function navigateLightbox(dir) {
    lightboxIndex = (lightboxIndex + dir + allGalleryUrls.length) % allGalleryUrls.length;
    const img = document.getElementById('murLightboxImg');
    if (img) img.src = allGalleryUrls[lightboxIndex];
}

// ══════════════════════════════════════
// COMPOSER
// ══════════════════════════════════════
function setupComposer(user) {
    const av = document.getElementById('composerAvatar');
    if (av) {
        if (user.photo) av.innerHTML = `<img src="${escapeHtml(user.photo)}" alt="">`;
        else av.textContent = ((user.prenom || '')[0] || '') + ((user.nom || '')[0] || '');
    }

    const catSelect = document.getElementById('composerCategory');
    if (catSelect) {
        const cats = (wallConfig.post_categories || 'general').split(',').map(c => c.trim());
        catSelect.innerHTML = cats.map(c => `<option value="${c}">${escapeHtml(CAT_LABELS[c] || c)}</option>`).join('');
    }

    if (wallConfig.allow_anonymous_comments === '1') {
        const w = document.getElementById('composerAnonWrap');
        if (w) w.style.display = '';
    }

    // File picker
    const fileInput = document.getElementById('composerFiles');
    fileInput?.addEventListener('change', () => {
        const files = Array.from(fileInput.files).slice(0, 4);
        pendingFiles = files;
        renderFilePreview();
    });

    document.getElementById('btnPost')?.addEventListener('click', submitPost);
    const textarea = document.getElementById('composerBody');
    textarea?.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) submitPost(); });
    textarea?.addEventListener('input', () => { textarea.style.height = 'auto'; textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px'; });
}

function renderFilePreview() {
    const preview = document.getElementById('composerPreview');
    if (!preview) return;
    if (!pendingFiles.length) { preview.style.display = 'none'; return; }
    preview.style.display = 'flex';
    preview.innerHTML = pendingFiles.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div class="mur-preview-thumb">
            <img src="${url}" alt="">
            <button class="mur-preview-remove" data-idx="${i}"><i class="bi bi-x"></i></button>
        </div>`;
    }).join('');
    preview.querySelectorAll('.mur-preview-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingFiles.splice(parseInt(btn.dataset.idx), 1);
            renderFilePreview();
        });
    });
}

async function submitPost() {
    const bodyEl = document.getElementById('composerBody');
    const body = bodyEl?.value?.trim();
    if (!body && !pendingFiles.length) return;

    const btn = document.getElementById('btnPost');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner spinner-sm"></span>'; }

    // Create post first
    const data = {
        body: body || '',
        category: document.getElementById('composerCategory')?.value || 'general',
        is_anonymous: document.getElementById('composerAnon')?.checked ? 1 : 0,
    };

    const res = await apiPost('create_mur_post', data);
    if (!res.success) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i> Publier'; }
        toast(res.message || 'Erreur');
        return;
    }

    // Upload media if any
    if (pendingFiles.length && res.id) {
        const fd = new FormData();
        fd.append('action', 'upload_mur_media');
        fd.append('post_id', res.id);
        pendingFiles.forEach((f, i) => fd.append(`file_${i}`, f));
        if (window.__ZT__?.csrfToken) fd.append('_csrf', window.__ZT__.csrfToken);

        await fetch('/zerdatime/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.__ZT__?.csrfToken || '' },
            body: fd,
        });
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i> Publier'; }
    bodyEl.value = '';
    bodyEl.style.height = 'auto';
    pendingFiles = [];
    renderFilePreview();
    toast(res.message || 'Post publié');
    currentPage = 1;
    loadFeed(false);
    loadGallery(); // Refresh gallery
    const el = document.getElementById('murStatComments');
    if (el) el.textContent = parseInt(el.textContent || '0') + 1;
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
    let posts = res.posts || [];

    if (activeCategory !== 'all') posts = posts.filter(p => p.category === activeCategory);
    if (!append) feed.innerHTML = '';

    if (posts.length === 0 && currentPage === 1) {
        feed.innerHTML = `<div class="mur-empty-feed"><i class="bi bi-chat-square-text"></i><p>Aucune publication</p><span>Soyez le premier à publier !</span></div>`;
    } else {
        for (const post of posts) feed.insertAdjacentHTML('beforeend', renderPost(post));
        setupPostHandlers(feed);
    }

    const loadMore = document.getElementById('murLoadMore');
    if (loadMore) loadMore.style.display = currentPage < totalPages ? '' : 'none';
}

// ══════════════════════════════════════
// POST RENDERING
// ══════════════════════════════════════
function renderPost(post) {
    const user = window.__ZT__?.user;
    const isOwn = user?.id === post.user_id;
    const initials = ((post.prenom || '')[0] || '') + ((post.nom || '')[0] || '');
    const displayName = escapeHtml((post.prenom || '') + ' ' + (post.nom || '')).trim() || 'Anonyme';
    const catColor = CAT_COLORS[post.category] || '#eee';

    const avatarHtml = post.avatar_url
        ? `<img src="${escapeHtml(post.avatar_url)}" alt="" class="mur-post-avatar">`
        : `<div class="mur-post-avatar mur-post-avatar-initials">${escapeHtml(initials || '?')}</div>`;

    // Media images
    const media = post.media || [];
    let imagesHtml = '';
    if (media.length) {
        const count = Math.min(media.length, 4);
        imagesHtml = `<div class="mur-post-images count-${count}" data-post-id="${post.id}">`;
        for (let i = 0; i < count; i++) {
            imagesHtml += `<div class="mur-post-img" data-img-idx="${i}"><img src="${escapeHtml(media[i].url)}" alt="" loading="lazy"></div>`;
        }
        imagesHtml += '</div>';
    }

    const likeBtn = wallConfig.allow_likes !== '0' ? `<button class="mur-action-btn mur-like-btn ${post.is_liked ? 'liked' : ''}" data-id="${post.id}"><i class="bi ${post.is_liked ? 'bi-heart-fill' : 'bi-heart'}"></i> <span>${post.is_liked ? 'Aimé' : 'Aimer'}</span> <span class="mur-like-count">${post.likes_count || 0}</span></button>` : '';
    const commentBtn = wallConfig.allow_comments !== '0' ? `<button class="mur-action-btn mur-comment-toggle" data-id="${post.id}"><i class="bi bi-chat"></i> <span>Commenter</span> <span class="mur-comment-count">${post.comments_count || 0}</span></button>` : '';

    return `<div class="mur-post" data-post-id="${post.id}">
        <div class="mur-post-header">
            ${avatarHtml}
            <div class="mur-post-meta">
                <div class="mur-post-name">${displayName}${post.fonction_nom ? ` <span class="mur-post-fonction">${escapeHtml(post.fonction_nom)}</span>` : ''}</div>
                <div class="mur-post-time">${timeAgo(post.created_at)} ${post.is_pinned == 1 ? '<i class="bi bi-pin-fill mur-pin-icon"></i>' : ''} <span class="mur-post-cat" style="background:${catColor}20">${escapeHtml(CAT_LABELS[post.category] || post.category)}</span></div>
            </div>
            ${isOwn ? `<div class="mur-post-actions"><button class="mur-action-btn mur-edit-btn" data-id="${post.id}"><i class="bi bi-three-dots"></i></button></div>` : ''}
        </div>
        ${post.body ? `<div class="mur-post-body">${escapeHtml(post.body)}</div>` : ''}
        ${imagesHtml}
        <div class="mur-post-footer">${likeBtn}${commentBtn}</div>
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
            const res = await apiPost('toggle_mur_like', { target_type: 'post', target_id: btn.dataset.id });
            if (res.success) {
                btn.classList.toggle('liked', res.liked);
                btn.querySelector('i').className = res.liked ? 'bi bi-heart-fill' : 'bi bi-heart';
                btn.querySelectorAll('span')[0].textContent = res.liked ? 'Aimé' : 'Aimer';
                btn.querySelector('.mur-like-count').textContent = res.count;
            }
        });
    });

    // Comment toggle
    container.querySelectorAll('.mur-comment-toggle:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const section = container.querySelector(`.mur-comments-section[data-post-id="${btn.dataset.id}"]`);
            if (!section) return;
            if (section.style.display === 'none') { section.style.display = ''; loadComments(btn.dataset.id, section); }
            else section.style.display = 'none';
        });
    });

    // Image lightbox
    container.querySelectorAll('.mur-post-images:not([data-bound])').forEach(grid => {
        grid.dataset.bound = '1';
        const imgs = Array.from(grid.querySelectorAll('.mur-post-img img')).map(i => i.src);
        grid.querySelectorAll('.mur-post-img').forEach(imgEl => {
            imgEl.addEventListener('click', () => openLightbox(imgs, parseInt(imgEl.dataset.imgIdx)));
        });
    });

    // 3-dots menu
    container.querySelectorAll('.mur-edit-btn:not([data-bound])').forEach(btn => {
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const post = btn.closest('.mur-post');
            const existing = post?.querySelector('.mur-post-menu');
            if (existing) { existing.remove(); return; }

            const menu = document.createElement('div');
            menu.className = 'mur-post-menu';
            menu.innerHTML = `<button class="mur-menu-item" data-act="edit"><i class="bi bi-pencil"></i> Modifier</button><button class="mur-menu-item mur-menu-danger" data-act="delete"><i class="bi bi-trash"></i> Supprimer</button>`;
            menu.style.cssText = 'position:absolute;right:0;top:100%;background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:4px;z-index:10;box-shadow:0 4px 16px rgba(0,0,0,.1);min-width:140px';
            btn.parentElement.style.position = 'relative';
            btn.parentElement.appendChild(menu);

            menu.querySelector('[data-act="edit"]').addEventListener('click', () => {
                menu.remove();
                const bodyEl = post?.querySelector('.mur-post-body');
                if (!bodyEl) return;
                const currentText = bodyEl.textContent;
                bodyEl.innerHTML = `<textarea class="mur-edit-area">${escapeHtml(currentText)}</textarea><div class="mur-edit-actions"><button class="mur-btn-save">Enregistrer</button><button class="mur-btn-cancel">Annuler</button></div>`;
                bodyEl.querySelector('.mur-btn-cancel')?.addEventListener('click', () => { bodyEl.textContent = currentText; });
                bodyEl.querySelector('.mur-btn-save')?.addEventListener('click', async () => {
                    const newBody = bodyEl.querySelector('.mur-edit-area')?.value?.trim();
                    if (!newBody) return;
                    const r = await apiPost('update_mur_post', { id: btn.dataset.id, body: newBody });
                    if (r.success) { bodyEl.textContent = newBody; toast('Post modifié'); } else toast(r.message || 'Erreur');
                });
            });

            menu.querySelector('[data-act="delete"]').addEventListener('click', async () => {
                if (!confirm('Supprimer ce post ?')) return;
                const r = await apiPost('delete_mur_post', { id: btn.dataset.id });
                if (r.success) { post.remove(); toast('Post supprimé'); }
            });

            setTimeout(() => document.addEventListener('click', function close(e) {
                if (!menu.contains(e.target) && e.target !== btn) { menu.remove(); document.removeEventListener('click', close); }
            }), 10);
        });
    });
}

// ══════════════════════════════════════
// COMMENTS
// ══════════════════════════════════════
async function loadComments(postId, container) {
    container.innerHTML = '<div class="mur-loading"><span class="spinner spinner-sm"></span></div>';
    const res = await apiPost('get_mur_comments', { post_id: postId });
    if (!res.success) { container.innerHTML = '<div class="mur-comment-error">Erreur</div>'; return; }

    const comments = res.comments || [];
    const user = window.__ZT__?.user;
    const allowAnon = wallConfig.allow_anonymous_comments === '1';

    let html = '<div class="mur-comment-list">' + comments.map(c => {
        const initials = ((c.prenom || '')[0] || '') + ((c.nom || '')[0] || '');
        const name = escapeHtml(((c.prenom || '') + ' ' + (c.nom || '')).trim() || 'Anonyme');
        const isOwn = user?.id === c.user_id;
        return `<div class="mur-comment"><div class="mur-comment-avatar">${c.avatar_url ? `<img src="${escapeHtml(c.avatar_url)}" alt="">` : escapeHtml(initials || '?')}</div><div class="mur-comment-content"><div class="mur-comment-header"><span class="mur-comment-name">${name}</span><span class="mur-comment-time">${timeAgo(c.created_at)}</span>${isOwn ? `<button class="mur-comment-del" data-id="${c.id}" data-post-id="${postId}"><i class="bi bi-x"></i></button>` : ''}</div><div class="mur-comment-body">${escapeHtml(c.body)}</div></div></div>`;
    }).join('') + '</div>';

    if (user) {
        html += `<div class="mur-comment-input"><input type="text" class="mur-comment-text" placeholder="Écrire un commentaire...">${allowAnon ? '<label class="mur-comment-anon-label"><input type="checkbox" class="mur-comment-anon"> Anonyme</label>' : ''}<button class="mur-comment-send" data-post-id="${postId}"><i class="bi bi-send"></i></button></div>`;
    }
    container.innerHTML = html;

    container.querySelectorAll('.mur-comment-del').forEach(btn => {
        btn.addEventListener('click', async () => {
            const r = await apiPost('delete_mur_comment', { id: btn.dataset.id });
            if (r.success) { btn.closest('.mur-comment')?.remove(); updateCommentCount(btn.dataset.postId, -1); }
        });
    });

    const sendBtn = container.querySelector('.mur-comment-send');
    const inputEl = container.querySelector('.mur-comment-text');
    if (sendBtn && inputEl) {
        const submit = async () => {
            const body = inputEl.value.trim();
            if (!body) return;
            const isAnon = container.querySelector('.mur-comment-anon')?.checked ? 1 : 0;
            const r = await apiPost('add_mur_comment', { post_id: postId, body, is_anonymous: isAnon });
            if (r.success) { inputEl.value = ''; toast('Commentaire ajouté'); loadComments(postId, container); updateCommentCount(postId, 1); }
            else toast(r.message || 'Erreur');
        };
        sendBtn.addEventListener('click', submit);
        inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
    }
}

function updateCommentCount(postId, delta) {
    const el = document.querySelector(`.mur-comment-toggle[data-id="${postId}"] .mur-comment-count`);
    if (el) el.textContent = Math.max(0, parseInt(el.textContent) + delta);
}

// ══════════════════════════════════════
// HELPERS
// ══════════════════════════════════════
function timeAgo(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr), now = new Date(), diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'à l\'instant';
    if (diff < 3600) return `il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `il y a ${Math.floor(diff / 3600)}h`;
    if (diff < 604800) return `il y a ${Math.floor(diff / 86400)}j`;
    return d.toLocaleDateString('fr-CH', { day: '2-digit', month: 'short' });
}

function animateNum(id, target) {
    const el = document.getElementById(id);
    if (!el) return;
    if (target === 0) { el.textContent = '0'; return; }
    const start = performance.now();
    (function tick(now) {
        const p = Math.min((now - start) / 500, 1);
        el.textContent = Math.round(target * (1 - (1 - p) * (1 - p)));
        if (p < 1) requestAnimationFrame(tick);
    })(start);
}
