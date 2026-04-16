/**
 * SpocSpace - Covoiturage (carpooling match finder)
 * Users pick their carpool buddies, then see schedule overlaps.
 */
import { apiPost, escapeHtml, toast, debounce } from '../helpers.js';

const JOURS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let currentMonday = null;
let selectedDate = null;
let hasBuddies = false;

export async function init() {
    const today = new Date();
    const dow = (today.getDay() + 6) % 7;
    currentMonday = new Date(today);
    currentMonday.setDate(currentMonday.getDate() - dow);

    document.getElementById('covPrevWeek')?.addEventListener('click', () => moveWeek(-1));
    document.getElementById('covNextWeek')?.addEventListener('click', () => moveWeek(1));
    document.getElementById('covTodayBtn')?.addEventListener('click', () => {
        const t = new Date();
        const d = (t.getDay() + 6) % 7;
        currentMonday = new Date(t);
        currentMonday.setDate(currentMonday.getDate() - d);
        loadWeek();
    });
    document.getElementById('covPrintBtn')?.addEventListener('click', printView);

    // Buddy panel
    document.getElementById('covBuddiesBtn')?.addEventListener('click', toggleBuddyPanel);
    document.getElementById('covCloseBuddyPanel')?.addEventListener('click', () => {
        document.getElementById('covBuddyPanel').style.display = 'none';
    });

    const searchInput = document.getElementById('covBuddySearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => searchUsers(searchInput.value), 300));
    }

    // Load buddy count first, then week
    // Use SSR data on first load to avoid waterfall requests
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr) {
        const count = ssr.buddy_count ?? 0;
        document.getElementById('covBuddyCount').textContent = count;
        hasBuddies = count > 0;
        renderWeek(ssr);
    } else {
        await loadBuddyCount();
        await loadWeek();
    }
}

// ── Buddy management ──

async function loadBuddyCount() {
    try {
        const res = await apiPost('get_covoiturage_buddies');
        if (res.success) {
            const count = res.buddies?.length || 0;
            document.getElementById('covBuddyCount').textContent = count;
            hasBuddies = count > 0;
        }
    } catch (e) {}
}

function toggleBuddyPanel() {
    const panel = document.getElementById('covBuddyPanel');
    const visible = getComputedStyle(panel).display !== 'none';
    panel.style.display = visible ? 'none' : 'block';
    if (!visible) loadBuddies();
}

async function loadBuddies() {
    const list = document.getElementById('covBuddyList');
    list.innerHTML = '<div class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm"></span></div>';

    const res = await apiPost('get_covoiturage_buddies');
    if (!res.success) {
        list.innerHTML = '<div class="text-muted small text-center py-2">Erreur</div>';
        return;
    }

    const buddies = res.buddies || [];
    document.getElementById('covBuddyCount').textContent = buddies.length;
    hasBuddies = buddies.length > 0;

    if (!buddies.length) {
        list.innerHTML = `<div class="text-center py-3">
            <i class="bi bi-people" style="font-size:1.5rem;color:var(--ss-text-muted);display:block;margin-bottom:6px;"></i>
            <div class="text-muted small">Aucun collègue ajouté.<br>Recherchez un nom ci-dessus pour en ajouter.</div>
        </div>`;
        return;
    }

    list.innerHTML = buddies.map(b => {
        const initials = ((b.prenom?.[0] || '') + (b.nom?.[0] || '')).toUpperCase();
        return `<div class="cov-buddy-item">
            <div class="cov-avatar" style="background:var(--ss-accent-bg);color:var(--ss-text)">${escapeHtml(initials)}</div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:0.9rem">${escapeHtml(b.prenom)} ${escapeHtml(b.nom)}</div>
                <div style="font-size:0.78rem;color:var(--ss-text-muted)">${escapeHtml(b.fonction_nom || '')}${b.module_nom ? ' · ' + escapeHtml(b.module_nom) : ''}</div>
            </div>
            <button class="btn btn-sm btn-light" data-remove-buddy="${escapeHtml(b.buddy_id)}" title="Retirer">
                <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>
            </button>
        </div>`;
    }).join('');

    list.querySelectorAll('[data-remove-buddy]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const buddyId = btn.dataset.removeBuddy;
            btn.disabled = true;
            await apiPost('remove_covoiturage_buddy', { buddy_id: buddyId });
            toast('Collègue retiré');
            await loadBuddies();
            await loadWeek();
        });
    });
}

async function searchUsers(q) {
    const resultsEl = document.getElementById('covSearchResults');
    if (!q || q.length < 2) {
        resultsEl.style.display = 'none';
        return;
    }

    const res = await apiPost('search_covoiturage_users', { q });
    if (!res.success || !res.users?.length) {
        resultsEl.style.display = 'block';
        resultsEl.innerHTML = '<div class="text-muted small py-2 text-center">Aucun résultat</div>';
        return;
    }

    resultsEl.style.display = 'block';
    resultsEl.innerHTML = res.users.map(u => {
        const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();
        return `<div class="cov-search-item ${u.is_buddy ? 'is-buddy' : ''}" data-add-buddy="${escapeHtml(u.id)}">
            <div class="cov-avatar" style="width:32px;height:32px;font-size:0.7rem;background:var(--ss-accent-bg);color:var(--ss-text)">${escapeHtml(initials)}</div>
            <div style="flex:1;min-width:0">
                <span style="font-weight:500;font-size:0.88rem">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</span>
                <span style="font-size:0.75rem;color:var(--ss-text-muted);margin-left:6px">${escapeHtml(u.fonction_nom || '')}</span>
            </div>
            ${u.is_buddy
                ? '<span class="badge" style="background:var(--ss-accent-bg);color:var(--ss-text-muted);font-size:.7rem">Déjà ajouté</span>'
                : '<button class="btn btn-sm" style="background:#e8f5e9;color:#2e7d32;font-size:.75rem;"><i class="bi bi-plus-lg"></i></button>'
            }
        </div>`;
    }).join('');

    resultsEl.querySelectorAll('.cov-search-item:not(.is-buddy)').forEach(item => {
        item.addEventListener('click', async () => {
            const buddyId = item.dataset.addBuddy;
            item.style.opacity = '0.5';
            item.style.pointerEvents = 'none';
            await apiPost('add_covoiturage_buddy', { buddy_id: buddyId });
            toast('Collègue ajouté');
            document.getElementById('covBuddySearch').value = '';
            resultsEl.style.display = 'none';
            await loadBuddies();
            await loadWeek();
        });
    });
}

// ── Week / Day views ──

function moveWeek(delta) {
    currentMonday.setDate(currentMonday.getDate() + delta * 7);
    loadWeek();
}

async function loadWeek() {
    const mondayStr = fmt(currentMonday);
    updateWeekLabel();

    const grid = document.getElementById('covWeekGrid');
    grid.innerHTML = '<div class="col-12 text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    let res;
    try {
        res = await apiPost('get_covoiturage_semaine', { date: mondayStr });
    } catch (e) {
        grid.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="bi bi-wifi-off"></i> Erreur réseau</div>';
        return;
    }
    if (!res.success) {
        grid.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="bi bi-exclamation-triangle"></i> ' + (res.message || 'Erreur') + '</div>';
        return;
    }

    renderWeek(res);
}

function renderWeek(res) {
    updateWeekLabel();

    const grid = document.getElementById('covWeekGrid');

    // Show/hide no-buddies alert
    const noBuddiesAlert = document.getElementById('covNoBuddiesAlert');
    if (res.has_buddies === false && !hasBuddies) {
        noBuddiesAlert.style.display = 'block';
    } else {
        noBuddiesAlert.style.display = 'none';
    }

    let html = '';
    for (let i = 0; i < 7; i++) {
        const d = new Date(currentMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmt(d);
        const dayData = res.days?.[dateStr];
        const isRest = !dayData;
        const isToday = dateStr === fmt(new Date());

        html += `<div class="col">
            <div class="cov-day-card ${isRest ? 'rest' : ''} ${dateStr === selectedDate ? 'active' : ''}" data-date="${dateStr}" ${isRest ? '' : 'data-clickable'}>
                <div class="cov-day-name">${JOURS[i]}${isToday ? ' *' : ''}</div>
                <div class="cov-day-date">${d.getDate()}</div>
                ${dayData ? `
                    <div class="cov-day-shift" style="background:var(--ss-accent-bg)">${escapeHtml(dayData.horaire || '-')} ${escapeHtml(dayData.debut || '')}–${escapeHtml(dayData.fin || '')}</div>
                    <div class="cov-day-count">${dayData.same_shift_count} collègue${dayData.same_shift_count !== 1 ? 's' : ''}</div>
                ` : `<div class="cov-day-shift" style="background:var(--ss-border-light);color:var(--ss-text-muted)">Repos</div>`}
            </div>
        </div>`;
    }
    grid.innerHTML = html;

    grid.querySelectorAll('.cov-day-card[data-clickable]').forEach(card => {
        card.addEventListener('click', () => {
            selectedDate = card.dataset.date;
            grid.querySelectorAll('.cov-day-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            loadDayDetail(selectedDate);
        });
    });

    // Auto-select today or first working day
    if (!selectedDate || !res.days?.[selectedDate]) {
        const todayStr = fmt(new Date());
        if (res.days?.[todayStr]) {
            selectedDate = todayStr;
        } else {
            for (let i = 0; i < 7; i++) {
                const d = new Date(currentMonday);
                d.setDate(d.getDate() + i);
                const ds = fmt(d);
                if (res.days?.[ds]) { selectedDate = ds; break; }
            }
        }
    }

    const hasDays = res.days && Object.keys(res.days).length > 0;
    if (!hasDays) {
        grid.innerHTML += '<div class="col-12 text-center py-3 text-muted"><i class="bi bi-calendar-x"></i> Aucun planning publié pour cette semaine</div>';
        document.getElementById('covDayDetail').classList.add('d-none');
        return;
    }

    if (selectedDate && res.days?.[selectedDate]) {
        grid.querySelector(`.cov-day-card[data-date="${selectedDate}"]`)?.classList.add('active');
        loadDayDetail(selectedDate);
    } else {
        document.getElementById('covDayDetail').classList.add('d-none');
    }
}

async function loadDayDetail(date) {
    const detail = document.getElementById('covDayDetail');
    detail.classList.remove('d-none');

    const d = new Date(date + 'T12:00:00');
    const dayName = JOURS[(d.getDay() + 6) % 7];
    document.getElementById('covDayTitle').textContent = `${dayName} ${d.toLocaleDateString('fr-CH')}`;

    const matchList = document.getElementById('covMatchList');
    matchList.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    const res = await apiPost('get_covoiturage_matches', { date });
    if (!res.success) return;

    const myShift = document.getElementById('covMyShift');
    if (res.mon_horaire) {
        myShift.textContent = `${res.mon_horaire.code} (${res.mon_horaire.debut}–${res.mon_horaire.fin})`;
        myShift.style.background = res.mon_horaire.couleur || '#6c757d';
        myShift.style.color = '#fff';
    }

    if (res.no_buddies) {
        matchList.innerHTML = `<div class="text-center py-4 text-muted">
            <i class="bi bi-people" style="font-size:1.5rem;display:block;margin-bottom:0.5rem"></i>
            Ajoutez des collègues pour voir les croisements d'horaires
            <div class="mt-2"><button class="btn btn-sm" id="covAddBuddyCta" style="background:var(--ss-accent-bg);color:var(--ss-text);font-weight:500;"><i class="bi bi-plus-lg"></i> Ajouter des collègues</button></div>
        </div>`;
        document.getElementById('covAddBuddyCta')?.addEventListener('click', () => {
            document.getElementById('covBuddyPanel').style.display = 'block';
            loadBuddies();
            document.getElementById('covBuddySearch')?.focus();
        });
        return;
    }

    const allMatches = [
        ...(res.same_shift || []).map(m => ({ ...m, matchLabel: 'Même horaire', matchClass: 'cov-match-exact' })),
        ...(res.other_shift || []).map(m => ({ ...m, matchLabel: 'Horaire croisé', matchClass: 'cov-match-overlap' })),
    ];

    if (!allMatches.length) {
        matchList.innerHTML = `<div class="text-center py-4 text-muted">
            <i class="bi bi-car-front" style="font-size:1.5rem;display:block;margin-bottom:0.5rem"></i>
            Aucun de vos collègues n'a un horaire compatible ce jour
        </div>`;
        return;
    }

    matchList.innerHTML = allMatches.map(m => {
        const initials = ((m.prenom?.[0] || '') + (m.nom?.[0] || '')).toUpperCase();
        const bgColor = m.couleur || '#6c757d';
        return `<div class="cov-match-item">
            <div class="cov-avatar" style="background:${escapeHtml(bgColor)}20;color:${escapeHtml(bgColor)}">${escapeHtml(initials)}</div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:0.9rem">${escapeHtml(m.prenom)} ${escapeHtml(m.nom)}</div>
                <div style="font-size:0.78rem;color:var(--ss-text-muted)">${escapeHtml(m.fonction_nom || '')} ${m.module_nom ? '· ' + escapeHtml(m.module_nom) : ''}</div>
            </div>
            <div>
                <span class="cov-match-badge ${m.matchClass}">${m.matchLabel}</span>
                <div style="font-size:0.72rem;text-align:right;margin-top:2px">${escapeHtml(m.horaire_code || '')} ${escapeHtml(m.heure_debut || '')}–${escapeHtml(m.heure_fin || '')}</div>
            </div>
        </div>`;
    }).join('');
}

function printView() {
    const content = document.getElementById('covDayDetail');
    if (!content || content.classList.contains('d-none')) {
        toast('Sélectionnez un jour d\'abord');
        return;
    }
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><title>Covoiturage</title>
        <style>body{font-family:Inter,sans-serif;padding:2rem}
        .cov-match-item{display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid #eee}
        .cov-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.7rem;border:1px solid #ddd}
        .cov-match-badge{font-size:0.7rem;padding:2px 6px;border-radius:4px}
        .cov-match-exact{background:#e8f5e9;color:#2e7d32}.cov-match-overlap{background:#fff3e0;color:#e65100}
        </style></head><body>${content.innerHTML}<script>window.print();<\/script></body></html>`);
    w.document.close();
}

function updateWeekLabel() {
    const end = new Date(currentMonday);
    end.setDate(end.getDate() + 6);
    const opts = { day: 'numeric', month: 'short' };
    document.getElementById('covWeekLabel').textContent =
        `${currentMonday.toLocaleDateString('fr-CH', opts)} — ${end.toLocaleDateString('fr-CH', opts)} ${end.getFullYear()}`;
}

function fmt(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

export function destroy() {}
