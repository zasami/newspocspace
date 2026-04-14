/**
 * Annuaire — Employee SPA module
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';
import { startCall } from '../call-ui.js';

let _items = [];
let _collegues = [];
let _currentTab = 'collegues';
let _searchQuery = '';
let _searchHandler = null;
let _highlightId = null;
let _presenceTimer = null;

export async function init(pageId, params) {
    _highlightId = params?.highlight || null;
    _items = [];
    _collegues = [];

    // Load annuaire (contacts)
    try {
        const r1 = await apiPost('get_annuaire');
        _items = r1?.data || [];
    } catch (e) { console.error('[annuaire] get_annuaire failed:', e); }

    // Load collegues (for SpocSpace calls)
    try {
        const r2 = await apiPost('get_collegues');
        const myId = window.__SS__?.user?.id;
        _collegues = (r2?.data || []).filter(u => u && u.id !== myId);
    } catch (e) { console.error('[annuaire] get_collegues failed:', e); }

    // If highlighting a contact, switch to its tab
    if (_highlightId) {
        const target = _items.find(i => i.id === _highlightId);
        if (target) _currentTab = target.type;
    }

    renderAll();
    setupTabs();
    hookSearch();
    setupCallButtons();

    // Refresh presence every 10s (when on collegues tab)
    _presenceTimer = setInterval(async () => {
        if (_currentTab !== 'collegues') return;
        try {
            const r = await apiPost('get_collegues');
            _collegues = (r.data || []).filter(u => u.id !== window.__SS__?.user?.id);
            renderList();
        } catch {}
    }, 10000);

    // Scroll + highlight after render
    if (_highlightId) {
        setTimeout(() => scrollToHighlight(_highlightId), 100);
    }
}

function scrollToHighlight(id) {
    const el = document.querySelector(`[data-contact-id="${id}"]`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('an-emp-highlight');
    setTimeout(() => el.classList.remove('an-emp-highlight'), 3000);
}

export function destroy() {
    unhookSearch();
    if (_presenceTimer) { clearInterval(_presenceTimer); _presenceTimer = null; }
}

function setupCallButtons() {
    const wrap = document.getElementById('anEmpList');
    if (!wrap) return;
    wrap.addEventListener('click', (e) => {
        const audioBtn = e.target.closest('[data-call-audio]');
        const videoBtn = e.target.closest('[data-call-video]');
        if (!audioBtn && !videoBtn) return;
        e.preventDefault();
        const userId = (audioBtn || videoBtn).dataset.callAudio || (audioBtn || videoBtn).dataset.callVideo;
        const user = _collegues.find(u => u.id === userId);
        if (!user) return;
        startCall(user, audioBtn ? 'audio' : 'video');
    });
}

function setupTabs() {
    // Reflect _currentTab in UI
    document.querySelectorAll('.an-emp-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === _currentTab);
    });
    document.querySelectorAll('.an-emp-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.an-emp-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            _currentTab = tab.dataset.tab;
            renderAll();
        });
    });
}

function renderAll() {
    renderUrgenceQuick();
    renderList();
}

function renderUrgenceQuick() {
    const wrap = document.getElementById('anEmpUrgenceQuick');
    if (!wrap) return;
    // Show urgence tiles on "all" and "urgence" tabs only
    if (_currentTab !== 'all' && _currentTab !== 'urgence') {
        wrap.innerHTML = '';
        return;
    }
    const urgences = _items.filter(i => i.type === 'urgence' && i.telephone_1);
    if (!urgences.length) { wrap.innerHTML = ''; return; }

    const categoryIcons = {
        urgence_interne: 'broadcast-pin',
        urgence_generale: 'shield-exclamation',
        police: 'shield-fill',
        pompiers: 'fire',
        ambulance: 'heart-pulse',
        garde_medicale: 'bandaid',
        tox: 'droplet',
        rega: 'airplane',
        main_tendue: 'chat-heart',
    };

    let html = '';
    for (const u of urgences) {
        const icon = categoryIcons[u.categorie] || 'telephone';
        html += `
            <a href="tel:${escapeHtml(u.telephone_1)}" class="an-emp-urgence-tile">
                <i class="bi bi-${icon}"></i>
                <div class="an-emp-urgence-num">${escapeHtml(u.telephone_1)}</div>
                <div class="an-emp-urgence-label">${escapeHtml(u.nom)}</div>
            </a>`;
    }
    wrap.innerHTML = html;
}

function renderList() {
    const wrap = document.getElementById('anEmpList');
    if (!wrap) return;

    // Tab "collegues" : SpocSpace users (call buttons)
    if (_currentTab === 'collegues') {
        renderCollegues(wrap);
        return;
    }

    // Tab "history" : call history
    if (_currentTab === 'history') {
        renderHistory(wrap);
        return;
    }

    let filtered = _currentTab === 'all' ? _items : _items.filter(i => i.type === _currentTab);

    // Search filter (local)
    if (_searchQuery && _searchQuery.length >= 2) {
        const q = _searchQuery.toLowerCase();
        filtered = filtered.filter(i =>
            [i.nom, i.prenom, i.fonction, i.service, i.telephone_1, i.telephone_2, i.email, i.categorie]
                .filter(Boolean).some(v => v.toLowerCase().includes(q))
        );
    }

    // On "urgence" tab: already shown as tiles, skip list
    if (_currentTab === 'urgence' && !_searchQuery) {
        wrap.innerHTML = '';
        return;
    }

    if (!filtered.length) {
        wrap.innerHTML = '<div class="an-emp-empty"><i class="bi bi-telephone"></i><div>Aucun contact trouvé</div></div>';
        return;
    }

    // Group by type
    const groups = {};
    for (const it of filtered) {
        const g = _currentTab === 'all' ? it.type : 'all';
        if (!groups[g]) groups[g] = [];
        groups[g].push(it);
    }

    const groupLabels = { urgence: 'Urgences', interne: 'Contacts internes', externe: 'Contacts externes', all: '' };
    let html = '';
    for (const [g, list] of Object.entries(groups)) {
        if (_currentTab === 'all' && g === 'urgence') continue; // shown as tiles
        if (groupLabels[g]) html += `<div class="an-emp-group-label">${groupLabels[g]}</div>`;
        for (const it of list) {
            html += renderCard(it);
        }
    }
    wrap.innerHTML = html;
}

async function renderHistory(wrap) {
    wrap.innerHTML = '<div class="an-emp-empty"><i class="bi bi-arrow-repeat" style="animation:ss-spin 1s linear infinite"></i><div>Chargement...</div></div>';
    const res = await apiPost('call_history');
    const calls = res.data || [];
    const myId = window.__SS__?.user?.id;
    if (!calls.length) {
        wrap.innerHTML = '<div class="an-emp-empty"><i class="bi bi-clock-history"></i><div>Aucun appel</div></div>';
        return;
    }

    // Group by date (today / yesterday / older)
    const today = new Date().toISOString().slice(0, 10);
    const yesterdayDate = new Date(); yesterdayDate.setDate(yesterdayDate.getDate() - 1);
    const yesterday = yesterdayDate.toISOString().slice(0, 10);

    const groups = { 'Aujourd\'hui': [], 'Hier': [], 'Plus ancien': [] };
    for (const c of calls) {
        const d = (c.started_at || '').slice(0, 10);
        if (d === today) groups['Aujourd\'hui'].push(c);
        else if (d === yesterday) groups['Hier'].push(c);
        else groups['Plus ancien'].push(c);
    }

    let html = '';
    for (const [label, list] of Object.entries(groups)) {
        if (!list.length) continue;
        html += `<div class="an-emp-group-label">${label}</div>`;
        for (const c of list) {
            const isOutgoing = c.from_user_id === myId;
            const peerName = isOutgoing
                ? [c.to_prenom, c.to_nom].filter(Boolean).join(' ')
                : [c.from_prenom, c.from_nom].filter(Boolean).join(' ');
            const initials = isOutgoing
                ? ((c.to_prenom?.[0] || '') + (c.to_nom?.[0] || ''))
                : ((c.from_prenom?.[0] || '') + (c.from_nom?.[0] || ''));

            const arrowIcon = isOutgoing ? 'arrow-up-right' : 'arrow-down-left';
            const arrowClass = c.status === 'missed' ? 'ss-call-arrow-missed'
                : (isOutgoing ? 'ss-call-arrow-out' : 'ss-call-arrow-in');

            const time = new Date(c.started_at).toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
            const dateFull = new Date(c.started_at).toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' });
            const duration = c.duration_sec
                ? `${Math.floor(c.duration_sec / 60)}m ${(c.duration_sec % 60).toString().padStart(2, '0')}s`
                : (c.status === 'missed' ? 'Manqué' : (c.status === 'rejected' ? 'Refusé' : '—'));
            const mediaIcon = c.media === 'video' ? 'camera-video-fill' : 'telephone-fill';
            const peerId = isOutgoing ? c.to_user_id : c.from_user_id;

            html += `
                <div class="ss-callhist-item ${c.status === 'missed' && !isOutgoing ? 'ss-callhist-missed' : ''}">
                    <div class="ss-callhist-arrow ${arrowClass}">
                        <i class="bi bi-${arrowIcon}"></i>
                    </div>
                    <div class="an-emp-avatar an-emp-avatar-interne">${escapeHtml(initials.toUpperCase() || '?')}</div>
                    <div class="ss-callhist-info">
                        <div class="ss-callhist-name">
                            ${escapeHtml(peerName)}
                            <i class="bi bi-${mediaIcon} ss-callhist-media"></i>
                        </div>
                        <div class="ss-callhist-meta">
                            <span>${isOutgoing ? 'Sortant' : 'Entrant'} · ${time}</span>
                            <span class="ss-callhist-duration">${duration}</span>
                        </div>
                    </div>
                    <button class="an-emp-action an-emp-action-call" data-call-audio="${escapeHtml(peerId)}" title="Rappeler">
                        <i class="bi bi-telephone-fill"></i>
                    </button>
                </div>`;
        }
    }
    wrap.innerHTML = html;
}

function renderCollegues(wrap) {
    if (!Array.isArray(_collegues)) {
        wrap.innerHTML = '<div class="an-emp-empty"><i class="bi bi-exclamation-circle"></i><div>Erreur chargement — actualise la page</div></div>';
        return;
    }
    let list = _collegues;
    if (_searchQuery && _searchQuery.length >= 2) {
        const q = _searchQuery.toLowerCase();
        list = list.filter(u =>
            [u.prenom, u.nom, u.email, u.fonction_nom]
                .filter(Boolean).some(v => v.toLowerCase().includes(q))
        );
    }
    if (!list.length) {
        wrap.innerHTML = '<div class="an-emp-empty"><i class="bi bi-people"></i><div>Aucun collègue trouvé</div></div>';
        return;
    }
    // Online first, then alphabetical
    const sorted = [...list].sort((a, b) => {
        const oa = a.is_online ? 0 : 1;
        const ob = b.is_online ? 0 : 1;
        if (oa !== ob) return oa - ob;
        return (a.nom || '').localeCompare(b.nom || '');
    });

    const onlineCount = _collegues.filter(u => u.is_online).length;
    let html = `<div class="an-emp-group-label">
        Appel SpocSpace direct
        <span style="font-weight:500;color:var(--ss-text-muted);font-size:.7rem;margin-left:8px">
            <span class="ss-presence-dot ss-presence-online" style="display:inline-block;margin-right:4px"></span>${onlineCount} en ligne
        </span>
    </div>`;

    for (const u of sorted) {
        const fullName = [u.prenom, u.nom].filter(Boolean).join(' ');
        const initials = ((u.prenom || '')[0] || '') + ((u.nom || '')[0] || '');
        const online = !!u.is_online;
        const avatar = u.photo
            ? `<img src="${escapeHtml(u.photo)}" class="an-emp-avatar an-emp-avatar-interne" style="object-fit:cover" alt="">`
            : `<div class="an-emp-avatar an-emp-avatar-interne">${escapeHtml(initials.toUpperCase() || '?')}</div>`;

        const presenceClass = online ? 'ss-presence-online' : 'ss-presence-offline';
        const presenceLabel = online ? 'En ligne' : _formatLastSeen(u.last_seen_at);

        const callBtns = online
            ? `<button class="an-emp-action an-emp-action-call" data-call-audio="${escapeHtml(u.id)}" title="Appel audio SpocSpace">
                   <i class="bi bi-telephone-fill"></i>
               </button>
               <button class="an-emp-action" data-call-video="${escapeHtml(u.id)}" title="Appel vidéo SpocSpace" style="background:#B8C9D4;color:#3B4F6B">
                   <i class="bi bi-camera-video-fill"></i>
               </button>`
            : `<button class="an-emp-action" disabled title="Hors ligne — appel impossible" style="opacity:.4;cursor:not-allowed">
                   <i class="bi bi-telephone-x"></i>
               </button>`;

        html += `
            <div class="an-emp-card ${online ? '' : 'an-emp-card-offline'}" data-coll-id="${escapeHtml(u.id)}">
                <div class="ss-avatar-with-presence">
                    ${avatar}
                    <span class="ss-presence-dot ${presenceClass}"></span>
                </div>
                <div class="an-emp-info">
                    <div class="an-emp-name">${escapeHtml(fullName)}</div>
                    <div class="an-emp-meta">
                        <span class="ss-presence-label ${online ? 'is-online' : ''}">${escapeHtml(presenceLabel)}</span>
                        ${u.fonction_nom ? ' · ' + escapeHtml(u.fonction_nom) : ''}
                    </div>
                </div>
                <div class="an-emp-actions">${callBtns}</div>
            </div>`;
    }
    wrap.innerHTML = html;
}

function _formatLastSeen(ts) {
    if (!ts) return 'Jamais connecté';
    const diff = Date.now() - new Date(ts.replace(' ', 'T')).getTime();
    const sec = Math.floor(diff / 1000);
    if (sec < 60) return 'Hors ligne · à l\'instant';
    const min = Math.floor(sec / 60);
    if (min < 60) return 'Hors ligne · il y a ' + min + ' min';
    const hrs = Math.floor(min / 60);
    if (hrs < 24) return 'Hors ligne · il y a ' + hrs + 'h';
    return 'Hors ligne · il y a ' + Math.floor(hrs / 24) + 'j';
}

function renderCard(it) {
    const isOrg = !!Number(it.est_organisation);
    const fullName = isOrg ? (it.nom || '') : [it.prenom, it.nom].filter(Boolean).join(' ');
    const fctServ = [it.fonction, it.service].filter(Boolean).join(' · ');
    const initials = isOrg
        ? ''
        : (((it.prenom || '')[0] || '') + ((it.nom || '')[0] || ''));
    const avatarClass = `an-emp-avatar an-emp-avatar-${it.type}`;
    const avatarContent = isOrg
        ? '<i class="bi bi-building"></i>'
        : escapeHtml(initials.toUpperCase() || '?');

    let actions = '';
    if (it.telephone_1) {
        actions += `<a href="tel:${escapeHtml(it.telephone_1)}" class="an-emp-action an-emp-action-call" title="Appeler">
            <i class="bi bi-telephone-fill"></i>
        </a>`;
    }
    if (it.telephone_1) {
        actions += `<a href="sms:${escapeHtml(it.telephone_1)}" class="an-emp-action" title="SMS">
            <i class="bi bi-chat-dots-fill"></i>
        </a>`;
    }
    if (it.email) {
        actions += `<a href="mailto:${escapeHtml(it.email)}" class="an-emp-action" title="Email">
            <i class="bi bi-envelope-fill"></i>
        </a>`;
    }

    const fav = it.is_favori == 1 ? '<i class="bi bi-star-fill an-emp-fav"></i>' : '';

    return `
        <div class="an-emp-card" data-contact-id="${escapeHtml(it.id)}">
            <div class="${avatarClass}">${avatarContent}</div>
            <div class="an-emp-info">
                <div class="an-emp-name">${escapeHtml(fullName)} ${fav}</div>
                ${fctServ ? '<div class="an-emp-meta">' + escapeHtml(fctServ) + '</div>' : ''}
                ${it.telephone_1 ? '<div class="an-emp-tel">' + escapeHtml(it.telephone_1) + '</div>' : ''}
                ${it.telephone_2 ? '<div class="an-emp-tel an-emp-tel-sec">' + escapeHtml(it.telephone_2) + '</div>' : ''}
                ${it.email ? '<div class="an-emp-email">' + escapeHtml(it.email) + '</div>' : ''}
            </div>
            <div class="an-emp-actions">${actions}</div>
        </div>`;
}

// Hook the global search input to filter locally when on this page
function hookSearch() {
    const input = document.getElementById('feSearchInput');
    if (!input) return;
    _searchHandler = () => {
        _searchQuery = input.value.trim();
        renderList();
    };
    input.addEventListener('input', _searchHandler);
    input.placeholder = 'Rechercher un contact...';
}

function unhookSearch() {
    const input = document.getElementById('feSearchInput');
    if (input && _searchHandler) {
        input.removeEventListener('input', _searchHandler);
        _searchHandler = null;
    }
}
