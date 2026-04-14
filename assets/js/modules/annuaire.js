/**
 * Annuaire — Employee SPA module
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

let _items = [];
let _currentTab = 'all';
let _searchQuery = '';
let _searchHandler = null;
let _highlightId = null;

export async function init(pageId, params) {
    _highlightId = params?.highlight || null;
    const res = await apiPost('get_annuaire');
    _items = res.data || [];

    // If highlighting a contact, switch to its tab
    if (_highlightId) {
        const target = _items.find(i => i.id === _highlightId);
        if (target) _currentTab = target.type;
    }

    renderAll();
    setupTabs();
    hookSearch();

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

function renderCard(it) {
    const fullName = [it.prenom, it.nom].filter(Boolean).join(' ');
    const fctServ = [it.fonction, it.service].filter(Boolean).join(' · ');
    const initials = ((it.prenom || '')[0] || '') + ((it.nom || '')[0] || '');
    const avatarClass = `an-emp-avatar an-emp-avatar-${it.type}`;

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
            <div class="${avatarClass}">${escapeHtml(initials.toUpperCase() || '?')}</div>
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
