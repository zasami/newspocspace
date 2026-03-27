/**
 * Cuisine VIP — Monthly VIP table sessions
 * Sessions with residents, accompanists, special menu
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

let currentSession = null;
let residentHandler = null;

export async function init() {
    document.getElementById('cvNewSession')?.addEventListener('click', createSession);

    // Listen for resident selection from topbar search
    residentHandler = async (e) => {
        if (!currentSession) { toast('Créez d\'abord une session VIP', 'error'); return; }
        const { id, name } = e.detail;
        const res = await apiPost('cuisine_add_vip_resident', { session_id: currentSession.id, resident_id: id });
        if (res.success) { toast(name + ' ajouté', 'success'); loadSession(); }
        else toast(res.message || 'Erreur', 'error');
    };
    window.addEventListener('resident-selected', residentHandler);

    loadSession();
}

export function destroy() {
    if (residentHandler) window.removeEventListener('resident-selected', residentHandler);
    residentHandler = null;
    currentSession = null;
}

async function loadSession(sessionId) {
    const res = await apiPost('cuisine_get_vip_session', { session_id: sessionId || '' });
    if (!res.success) return;

    currentSession = res.session;

    renderHistory(res.history || []);
    renderAccompagnateurs(res.accompagnateurs || []);
    renderMenu(res.session);
    renderResidents(res.residents || []);
    renderBadge(res.session);
}

// ═══════════════════════════════════════
// Session badge
// ═══════════════════════════════════════

function renderBadge(session) {
    const el = document.getElementById('cvSessionBadge');
    if (!el) return;
    if (!session) { el.textContent = 'Aucune session'; el.style.background = '#E8E5E0'; el.style.color = '#6b7280'; return; }
    const d = new Date(session.date_session + 'T00:00:00');
    const label = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    el.textContent = session.statut === 'termine' ? 'Terminée · ' + label : 'Planifiée · ' + label;
    el.style.background = session.statut === 'termine' ? '#D0C4D8' : '#bcd2cb';
    el.style.color = session.statut === 'termine' ? '#5B4B6B' : '#2d4a43';
}

// ═══════════════════════════════════════
// History selector
// ═══════════════════════════════════════

function renderHistory(history) {
    if (!history.length) return;
    const opts = history.map(h => {
        const d = new Date(h.date_session + 'T00:00:00');
        const label = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
            + ' — ' + (h.menu_plat || 'Pas de menu') + ' (' + h.nb_residents + ' rés.)';
        return { value: h.id, label };
    });
    if (typeof zerdaSelect !== 'undefined') {
        zerdaSelect.init('#cvHistorySelect', opts, {
            value: currentSession?.id || '',
            onSelect: (val) => { if (val) loadSession(val); },
            search: history.length > 5,
        });
    }
}

// ═══════════════════════════════════════
// Accompagnateurs (stat cards top)
// ═══════════════════════════════════════

function renderAccompagnateurs(accompagnateurs) {
    const row = document.getElementById('cvAccompRow');
    if (!row) return;

    if (!accompagnateurs.length && currentSession) {
        row.innerHTML = '<div style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1rem;background:#F0EDE8;border-radius:12px;font-size:.85rem;color:#6b7280">'
            + '<i class="bi bi-person-plus"></i> Aucun accompagnateur. Cherchez <kbd>@nom</kbd> d\'un collaborateur pour en ajouter.'
            + '</div>';
        return;
    }
    if (!accompagnateurs.length) { row.innerHTML = ''; return; }

    let html = '<div style="display:flex;gap:.75rem;flex-wrap:wrap">';
    accompagnateurs.forEach(a => {
        const initials = ((a.prenom?.[0] || '') + (a.nom?.[0] || '')).toUpperCase();
        const avatar = a.photo
            ? '<img src="' + escapeHtml(a.photo) + '" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">'
            : '<div style="width:40px;height:40px;border-radius:50%;background:#B8C9D4;color:#3B4F6B;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">' + escapeHtml(initials) + '</div>';

        const fonctionBg = { 'ASC': '#D4C4A8', 'INF': '#E2B8AE', 'AS': '#B8C9D4', 'ANIM': '#D0C4D8' };
        const fonctionColor = { 'ASC': '#6B5B3E', 'INF': '#7B3B2C', 'AS': '#3B4F6B', 'ANIM': '#5B4B6B' };
        const bg = fonctionBg[a.fonction_code] || '#bcd2cb';
        const fg = fonctionColor[a.fonction_code] || '#2d4a43';

        html += '<div style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;background:#fff;border:1px solid #E8E5E0;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.04)">'
            + avatar
            + '<div><div style="font-weight:600;font-size:.88rem;color:#1A1A18">' + escapeHtml(a.prenom + ' ' + a.nom) + '</div>'
            + '<span style="display:inline-block;padding:1px 8px;border-radius:6px;font-size:.68rem;font-weight:700;background:' + bg + ';color:' + fg + '">' + escapeHtml(a.fonction_code || a.fonction_nom || '-') + '</span></div>'
            + '<button class="btn btn-sm" style="width:24px;height:24px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid #E8E5E0;font-size:.65rem;color:#999" data-remove-accomp="' + a.link_id + '" title="Retirer"><i class="bi bi-x"></i></button>'
            + '</div>';
    });
    html += '</div>';
    row.innerHTML = html;

    // Remove buttons
    row.querySelectorAll('[data-remove-accomp]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiPost('cuisine_remove_vip_accompagnateur', { link_id: btn.dataset.removeAccomp });
            if (res.success) { toast('Retiré', 'success'); loadSession(currentSession?.id); }
        });
    });
}

// ═══════════════════════════════════════
// Menu VIP card
// ═══════════════════════════════════════

function renderMenu(session) {
    const card = document.getElementById('cvMenuCard');
    if (!card || !session) { if (card) card.innerHTML = ''; return; }

    const fields = [
        { key: 'menu_entree', label: 'Entrée', icon: 'bi-cup-hot' },
        { key: 'menu_plat', label: 'Plat', icon: 'bi-egg-fried' },
        { key: 'menu_accompagnement', label: 'Accompagnement', icon: 'bi-grid-3x3' },
        { key: 'menu_dessert', label: 'Dessert', icon: 'bi-cake2' },
    ];

    let menuHtml = fields.map(f =>
        '<div style="display:flex;align-items:baseline;gap:6px;margin-bottom:4px">'
        + '<i class="bi ' + f.icon + '" style="font-size:.75rem;color:#6b7280"></i>'
        + '<span style="font-size:.75rem;color:#6b7280;min-width:90px">' + f.label + '</span>'
        + '<strong style="font-size:.88rem;color:#1A1A18">' + escapeHtml(session[f.key] || '—') + '</strong>'
        + '</div>'
    ).join('');

    if (session.menu_remarques) {
        menuHtml += '<div style="margin-top:6px;padding:6px 10px;background:#f9fafb;border-radius:6px;border:1px solid #E8E5E0;font-size:.78rem;color:#888;font-style:italic"><i class="bi bi-info-circle"></i> ' + escapeHtml(session.menu_remarques) + '</div>';
    }

    card.innerHTML = '<div style="border:1px solid #E8E5E0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)">'
        + '<div style="padding:.85rem 1.25rem;border-bottom:1px solid #E8E5E0;display:flex;align-items:center;justify-content:space-between">'
        + '<span style="font-weight:700;font-size:.92rem;color:#1A1A18"><i class="bi bi-star-fill" style="color:#D4C4A8"></i> Menu VIP spécial</span>'
        + '<button class="btn btn-sm btn-outline-primary" id="cvEditMenu" style="border-radius:8px;font-size:.75rem"><i class="bi bi-pencil"></i> Modifier</button>'
        + '</div>'
        + '<div style="padding:1rem 1.25rem">' + menuHtml + '</div></div>';

    document.getElementById('cvEditMenu')?.addEventListener('click', () => editMenu(session));
}

function editMenu(session) {
    const fields = ['menu_entree', 'menu_plat', 'menu_accompagnement', 'menu_dessert', 'menu_remarques'];
    const labels = { menu_entree: 'Entrée', menu_plat: 'Plat principal', menu_accompagnement: 'Accompagnement', menu_dessert: 'Dessert', menu_remarques: 'Remarques' };

    let html = fields.map(f =>
        '<div class="mb-2"><label class="form-label small fw-bold">' + labels[f] + '</label>'
        + '<input type="text" class="form-control" id="cvMenu_' + f + '" value="' + escapeHtml(session[f] || '') + '"></div>'
    ).join('');

    const card = document.getElementById('cvMenuCard');
    card.innerHTML = '<div style="border:1px solid #E8E5E0;border-radius:16px;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04)">'
        + '<div style="font-weight:700;font-size:.92rem;margin-bottom:.75rem"><i class="bi bi-star-fill" style="color:#D4C4A8"></i> Modifier le menu VIP</div>'
        + html
        + '<div class="d-flex gap-2 mt-2">'
        + '<button class="btn btn-sm btn-outline-secondary" id="cvMenuCancel" style="border-radius:8px">Annuler</button>'
        + '<button class="btn btn-sm btn-primary" id="cvMenuSave" style="border-radius:8px"><i class="bi bi-check-lg"></i> Enregistrer</button>'
        + '</div></div>';

    document.getElementById('cvMenuCancel')?.addEventListener('click', () => renderMenu(session));
    document.getElementById('cvMenuSave')?.addEventListener('click', async () => {
        const data = { session_id: session.id };
        fields.forEach(f => { data[f] = document.getElementById('cvMenu_' + f)?.value || ''; });
        const res = await apiPost('cuisine_save_vip_session_menu', data);
        if (res.success) { toast('Menu VIP enregistré', 'success'); loadSession(session.id); }
        else toast(res.message || 'Erreur', 'error');
    });
}

// ═══════════════════════════════════════
// Residents grid
// ═══════════════════════════════════════

function renderResidents(residents) {
    const body = document.getElementById('cvResidentsBody');
    if (!body) return;

    if (!currentSession) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-star"></i><p>Créez une session VIP pour commencer</p></div>';
        return;
    }

    if (!residents.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-person-plus"></i><p>Aucun résident. Cherchez avec <kbd>@</kbd> dans la barre de recherche.</p></div>';
        return;
    }

    let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.75rem">';
    residents.forEach(r => {
        const initials = ((r.prenom?.[0] || '') + (r.nom?.[0] || '')).toUpperCase();
        html += '<div style="border:1px solid #E8E5E0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem">'
            + '<div style="width:40px;height:40px;border-radius:50%;background:#D4C4A8;color:#6B5B3E;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">' + escapeHtml(initials) + '</div>'
            + '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:.88rem;color:#1A1A18">' + escapeHtml(r.prenom + ' ' + r.nom) + '</div>'
            + '<div style="font-size:.75rem;color:#6b7280">Ch. ' + escapeHtml(r.chambre || '-') + ' · Ét. ' + escapeHtml(r.etage || '-') + '</div></div>'
            + '<button class="btn btn-sm btn-outline-danger" style="width:28px;height:28px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:.7rem" data-remove-resident="' + r.link_id + '" title="Retirer"><i class="bi bi-x-lg"></i></button>'
            + '</div>';
    });
    html += '</div>';
    body.innerHTML = html;

    body.querySelectorAll('[data-remove-resident]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiPost('cuisine_remove_vip_resident', { link_id: btn.dataset.removeResident });
            if (res.success) { toast('Retiré', 'success'); loadSession(currentSession?.id); }
        });
    });
}

// ═══════════════════════════════════════
// Create new session
// ═══════════════════════════════════════

async function createSession() {
    const dateStr = prompt('Date de la session VIP (AAAA-MM-JJ) :');
    if (!dateStr) return;
    const res = await apiPost('cuisine_create_vip_session', { date_session: dateStr });
    if (res.success) { toast('Session VIP créée', 'success'); loadSession(res.id); }
    else toast(res.message || 'Erreur', 'error');
}
