/**
 * Annonces officielles — Employee SPA
 * Same display as admin/care annonces page
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

let allAnnonces = [];
let CAT_LABELS = {};
let currentFilter = '';
let dotWaveRAF = null;

function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('fr-CH', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
}

function renderFilters() {
    const c = document.getElementById('annFilters');
    if (!c) return;
    let html = `<button class="ann-filter-btn ${!currentFilter ? 'active' : ''}" data-cat="" style="background:${!currentFilter ? '#2d4a43' : '#fff'};color:${!currentFilter ? '#fff' : '#333'}">
        <i class="bi bi-grid-3x3-gap"></i> Toutes
    </button>`;
    Object.entries(CAT_LABELS).forEach(([key, cat]) => {
        const active = currentFilter === key;
        html += `<button class="ann-filter-btn ${active ? 'active' : ''}" data-cat="${key}" style="background:${active ? cat.color : '#fff'};color:${active ? '#fff' : '#333'};border-color:${cat.color}">
            <i class="bi bi-${cat.icon}"></i> ${cat.label}
        </button>`;
    });
    c.innerHTML = html;
    c.querySelectorAll('.ann-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => { currentFilter = btn.dataset.cat; renderFilters(); renderList(); });
    });
}

function renderList() {
    const list = document.getElementById('annList');
    const empty = document.getElementById('annEmpty');
    if (!list) return;

    const searchVal = (document.getElementById('feSearchInput')?.value || '').toLowerCase();
    let filtered = allAnnonces;
    if (currentFilter) filtered = filtered.filter(a => a.categorie === currentFilter);
    if (searchVal) filtered = filtered.filter(a =>
        (a.titre || '').toLowerCase().includes(searchVal) ||
        (a.description || '').toLowerCase().includes(searchVal)
    );

    if (!filtered.length) { list.innerHTML = ''; if (empty) empty.style.display = ''; return; }
    if (empty) empty.style.display = 'none';

    list.innerHTML = filtered.map(a => {
        const cat = CAT_LABELS[a.categorie] || CAT_LABELS.divers || { label: a.categorie, icon: 'megaphone', color: '#6c757d' };
        const pin = a.epingle == 1 ? '<i class="bi bi-pin-angle-fill ann-card-pin" title="Épinglé"></i>' : '';
        const date = fmtDate(a.published_at || a.created_at);
        const auteur = a.auteur_prenom ? `${a.auteur_prenom} ${a.auteur_nom}` : '';
        const img = a.image_url
            ? `<img class="ann-card-img" src="${escapeHtml(a.image_url)}" alt="">`
            : `<div class="ann-card-img-placeholder"><i class="bi bi-${cat.icon}"></i></div>`;

        return `<div class="ann-card ${a.epingle == 1 ? 'pinned' : ''}" data-id="${a.id}">
            ${pin}
            ${img}
            <div class="ann-card-body">
                <div class="ann-card-title">${escapeHtml(a.titre)}</div>
                <div class="ann-card-desc">${escapeHtml(a.description || '')}</div>
                <div class="ann-card-meta">
                    <span class="ann-card-cat" style="background:${cat.color}"><i class="bi bi-${cat.icon}"></i> ${cat.label}</span>
                    <span><i class="bi bi-calendar3"></i> ${date}</span>
                    ${auteur ? `<span><i class="bi bi-person"></i> ${escapeHtml(auteur)}</span>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');

    list.querySelectorAll('.ann-card').forEach(card => {
        card.addEventListener('click', () => openAnnonce(card.dataset.id));
    });
}

async function openAnnonce(id) {
    const res = await apiPost('get_annonce_detail', { id });
    if (!res.success || !res.annonce) return;

    const a = res.annonce;
    const cat = CAT_LABELS[a.categorie] || { label: a.categorie, icon: 'megaphone', color: '#6c757d' };
    const date = fmtDate(a.published_at || a.created_at);
    const auteur = a.auteur_prenom ? `${a.auteur_prenom} ${a.auteur_nom}` : '';
    const heroImg = a.image_url ? `<img class="ann-read-hero" src="${escapeHtml(a.image_url)}" alt="">` : '';

    const ackBlock = a.requires_ack ? (a.user_acked
        ? `<div class="ann-ack-confirmed"><i class="bi bi-check-circle-fill"></i> Vous avez confirmé la lecture de cette annonce</div>`
        : `<div class="ann-ack-required">
             <div><i class="bi bi-shield-exclamation"></i> Cette annonce nécessite votre accusé de lecture</div>
             <button class="btn btn-sm mt-2" id="btnAckAnnonce" style="background:#bcd2cb;color:#2d4a43;border:none;font-weight:600;border-radius:8px"><i class="bi bi-check2"></i> J'ai lu et compris</button>
           </div>`) : '';

    document.getElementById('annReadPanel').innerHTML = `
        ${heroImg}
        <div class="ann-read-content-wrap">
            <h1>${escapeHtml(a.titre)}</h1>
            <div class="ann-read-meta">
                <span class="ann-card-cat" style="background:${cat.color}"><i class="bi bi-${cat.icon}"></i> ${cat.label}</span>
                <span class="ms-2"><i class="bi bi-calendar3"></i> ${date}</span>
                ${auteur ? ` &middot; <i class="bi bi-person"></i> ${escapeHtml(auteur)}` : ''}
                ${a.requires_ack ? ' &middot; <span class="ann-ack-badge"><i class="bi bi-shield-check"></i> Accusé requis</span>' : ''}
            </div>
            ${ackBlock}
            <div class="ann-read-body">${a.contenu || '<p class="text-muted">Aucun contenu</p>'}</div>
            ${auteur ? `<div class="ann-signature">
                <div class="ann-signature-line"></div>
                <div class="ann-signature-name">${escapeHtml(auteur)}</div>
                <div class="ann-signature-role">${escapeHtml(cat.label)}</div>
            </div>` : ''}
        </div>
    `;

    document.getElementById('btnAckAnnonce')?.addEventListener('click', async () => {
        const r = await apiPost('ack_annonce', { id });
        if (r.success) {
            toast('Lecture confirmée');
            const ann = allAnnonces.find(x => x.id === id);
            if (ann) ann.user_acked = 1;
            openAnnonce(id);
            updateStats();
        }
    });

    document.getElementById('annListView').style.display = 'none';
    document.getElementById('annReadView').style.display = '';

    // Update hero title + breadcrumb
    const headerTitle = document.querySelector('.ann-page-header-text h1');
    const headerSub = document.querySelector('.ann-page-header-text p');
    const bcCurrent = document.querySelector('.ann-breadcrumb-current');
    if (headerTitle) headerTitle.textContent = a.titre;
    if (headerSub) headerSub.textContent = `${cat.label} · ${fmtDate(a.published_at || a.created_at)}`;
    // Breadcrumb: Home > Annonces > Titre
    const bcList = document.querySelector('.ann-breadcrumb');
    if (bcList) {
        let annLink = document.getElementById('bcAnnLink');
        if (!annLink) {
            // Insert separator + Annonces link + separator before current
            const sep1 = document.createElement('li');
            sep1.id = 'bcAnnSep1';
            sep1.className = 'ann-breadcrumb-sep';
            sep1.innerHTML = '<i class="bi bi-chevron-right"></i>';

            annLink = document.createElement('li');
            annLink.id = 'bcAnnLink';
            annLink.innerHTML = '<a href="#" id="bcAnnBackLink" style="font-size:.82rem">Annonces</a>';

            const sep2 = document.createElement('li');
            sep2.id = 'bcAnnSep2';
            sep2.className = 'ann-breadcrumb-sep';
            sep2.innerHTML = '<i class="bi bi-chevron-right"></i>';

            const current = document.querySelector('.ann-breadcrumb-current');
            if (current) {
                bcList.insertBefore(sep1, current);
                bcList.insertBefore(annLink, current);
                bcList.insertBefore(sep2, current);
            }
            document.getElementById('bcAnnBackLink')?.addEventListener('click', (e) => { e.preventDefault(); backToList(); });
        }
        // Show detail breadcrumb, hide original separator
        document.getElementById('bcMainSep').style.display = 'none';
        document.getElementById('bcAnnSep1').style.display = '';
        document.getElementById('bcAnnLink').style.display = '';
        document.getElementById('bcAnnSep2').style.display = '';
        if (bcCurrent) {
            bcCurrent.textContent = a.titre.length > 50 ? a.titre.substring(0, 50) + '…' : a.titre;
        }
    }

    window.scrollTo({ top: 0, behavior: 'instant' });
}

function backToList() {
    document.getElementById('annReadView').style.display = 'none';
    document.getElementById('annListView').style.display = '';

    // Restore hero title + breadcrumb
    const headerTitle = document.querySelector('.ann-page-header-text h1');
    const headerSub = document.querySelector('.ann-page-header-text p');
    const bcCurrent = document.querySelector('.ann-breadcrumb-current');
    if (headerTitle) headerTitle.textContent = 'Annonces officielles';
    if (headerSub) headerSub.textContent = 'Communications de la direction et des services';
    if (bcCurrent) bcCurrent.textContent = 'Annonces';
    const ids = ['bcAnnSep1', 'bcAnnLink', 'bcAnnSep2'];
    ids.forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
    const mainSep = document.getElementById('bcMainSep');
    if (mainSep) mainSep.style.display = '';
}

function updateStats() {
    const total = allAnnonces.length;
    const pinned = allAnnonces.filter(a => a.epingle == 1).length;
    const pending = allAnnonces.filter(a => a.requires_ack && !a.user_acked).length;

    const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    el('bcStatTotalVal', total);
    el('bcStatPinnedVal', pinned);
    el('bcStatAckVal', pending);
    el('annAckCount', pending);

    const ackStat = document.getElementById('bcStatAck');
    if (ackStat) ackStat.style.display = pending > 0 ? '' : 'none';
    const ackAlert = document.getElementById('annAckAlert');
    if (ackAlert) ackAlert.style.display = pending > 0 ? '' : 'none';
}

export async function init(pageId, params = {}) {
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr) {
        allAnnonces = ssr.annonces || [];
        CAT_LABELS = ssr.cat_labels || {};
    } else {
        const res = await apiPost('get_annonces_list');
        allAnnonces = res.annonces || [];
    }

    renderFilters();
    renderList();
    updateStats();
    initDotWave();

    // Search
    const searchInput = document.getElementById('feSearchInput');
    let searchTimer;
    searchInput?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(renderList, 250); });

    // Open specific annonce from search or notification
    if (params?.id) {
        openAnnonce(params.id);
    }
}

export function destroy() {
    allAnnonces = [];
    currentFilter = '';
    if (dotWaveRAF) { cancelAnimationFrame(dotWaveRAF); dotWaveRAF = null; }
    const searchInput = document.getElementById('feSearchInput');
    if (searchInput) searchInput.value = '';
}

/* ── Animated dot wave (like Claude Cowork) ── */
function initDotWave() {
    const canvas = document.getElementById('annDotsCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    const SP = 20;
    const R = 0.7;
    const SPEED = 0.003;
    const WL = 350;
    const COL = '140,132,120';

    let t = 0;

    function draw() {
        const w = canvas.width / dpr;
        const h = canvas.height / dpr;
        ctx.clearRect(0, 0, w, h);

        const cols = Math.ceil(w / SP) + 1;
        const rows = Math.ceil(h / SP) + 1;
        const ox = (w % SP) / 2;
        const oy = (h % SP) / 2;

        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const x = ox + c * SP;
                const y = oy + r * SP;

                const phase = (w - x + y * 0.3) / WL * Math.PI * 2 - t;
                const wave = (Math.sin(phase) + 1) / 2;

                const a = 0.12 + wave * 0.16;

                ctx.fillStyle = `rgba(${COL},${a})`;
                ctx.fillRect(x - R, y - R, R * 2, R * 2);
            }
        }

        t += SPEED;
        dotWaveRAF = requestAnimationFrame(draw);
    }

    draw();
}
