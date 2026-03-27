/**
 * Cuisine Home — Dashboard for external cuisine employees
 */
import { apiPost, toast, escapeHtml, formatDateShort, formatDayName } from '../helpers.js';

const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let menuMonday = null;
let editModal = null;
let canEdit = true;

export async function init() {
    const user = window.__ZT__?.user;
    const denied = window.__ZT__?.deniedPerms || [];
    canEdit = !denied.includes('cuisine_saisie_menu');

    const nameEl = document.getElementById('chUserName');
    if (nameEl && user) nameEl.textContent = user.prenom || '';

    menuMonday = getMonday(new Date());

    // Nav
    document.getElementById('chMenuPrev')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, -7); loadMenus(); });
    document.getElementById('chMenuNext')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, 7); loadMenus(); });
    document.getElementById('chRepas')?.addEventListener('change', loadCommandes);
    document.getElementById('chPrintBtn')?.addEventListener('click', printCommandes);

    // Edit modal
    const modalEl = document.getElementById('chMenuEditModal');
    if (modalEl) editModal = new bootstrap.Modal(modalEl);
    document.getElementById('chEditSaveBtn')?.addEventListener('click', saveMenu);

    // Load all
    await Promise.all([loadCommandes(), loadMenus()]);
}

export function destroy() {
    editModal = null;
    menuMonday = null;
}

// ═══════════════════════════════════════
// COMMANDES DU JOUR (left panel)
// ═══════════════════════════════════════

async function loadCommandes() {
    const body = document.getElementById('chCommandesBody');
    const repas = document.getElementById('chRepas')?.value || 'midi';
    if (!body) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_reservations_collab', { date: todayStr(), repas });

    // Update stats
    document.getElementById('chStatCouverts').textContent = res.total_couverts || 0;
    document.getElementById('chStatMenu').textContent = res.nb_menu || 0;
    document.getElementById('chStatSalade').textContent = res.nb_salade || 0;

    if (!res.success || !res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:1.5rem"><i class="bi bi-receipt"></i><p>Aucune commande</p></div>';
        return;
    }

    let html = '<table class="table table-sm table-striped mb-0" id="chCommandesTable">'
        + '<thead><tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th></tr></thead><tbody>';
    res.reservations.forEach(r => {
        html += '<tr>'
            + '<td>' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
            + '<td class="small">' + escapeHtml(r.fonction_nom || r.fonction_code || '-') + '</td>'
            + '<td><span class="badge ' + (r.choix === 'menu' ? 'bg-primary' : 'bg-success') + '" style="font-size:.72rem">' + escapeHtml(r.choix) + '</span></td>'
            + '<td>' + r.nb_personnes + '</td>'
            + '<td class="small">' + escapeHtml(r.paiement || '-') + '</td>'
            + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td></tr>';
    });
    html += '</tbody></table>';
    body.innerHTML = html;
}

function printCommandes() {
    const table = document.getElementById('chCommandesTable');
    if (!table) { toast('Rien à imprimer', 'error'); return; }
    const repas = document.getElementById('chRepas')?.value || 'midi';
    const win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Commandes ' + todayStr() + '</title>'
        + '<style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:10px}'
        + 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}'
        + 'th{background:#f0f0f0}.badge{padding:2px 8px;border-radius:4px;font-size:11px}'
        + '.bg-primary{background:#0d6efd;color:#fff}.bg-success{background:#198754;color:#fff}'
        + '@media print{button{display:none}}</style></head>'
        + '<body><h2>Commandes du ' + todayStr() + ' (' + escapeHtml(repas) + ')</h2>'
        + table.outerHTML + '<br><button onclick="window.print()">Imprimer</button></body></html>');
    win.document.close();
}

// ═══════════════════════════════════════
// MENUS DE LA SEMAINE (right panel)
// ═══════════════════════════════════════

async function loadMenus() {
    const body = document.getElementById('chMenusBody');
    if (!body) return;

    updateWeekLabel();
    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';

    const res = await apiPost('cuisine_get_menus_semaine', { date: fmtDate(menuMonday) });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    const menusByKey = {};
    (res.menus || []).forEach(m => { menusByKey[m.date_jour + '_' + (m.repas || 'midi')] = m; });

    // Count menus saisis cette semaine
    const nbSaisis = Object.keys(menusByKey).length;
    document.getElementById('chStatMenusSaisis').textContent = nbSaisis + '/14';

    const today = todayStr();
    let html = '<div style="display:flex;flex-direction:column;gap:0">';

    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);
        const isToday = dateStr === today;
        const todayBg = isToday ? 'background:var(--zt-accent-bg);border-left:3px solid var(--zt-teal);' : '';

        html += '<div style="border-bottom:1px solid var(--zt-border-light);' + todayBg + '">';
        html += '<div style="padding:0.5rem 0.75rem;font-weight:600;font-size:0.88rem">'
            + escapeHtml(DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth() + 1))
            + (isToday ? ' <span class="badge bg-primary" style="font-size:.65rem">Aujourd\'hui</span>' : '')
            + '</div>';

        ['midi', 'soir'].forEach(repas => {
            const menu = menusByKey[dateStr + '_' + repas];
            const cursor = canEdit ? 'cursor:pointer;' : '';

            if (menu) {
                html += '<div class="ch-menu-row" data-date="' + dateStr + '" data-repas="' + repas + '" style="padding:0.3rem 0.75rem 0.3rem 1.5rem;display:flex;align-items:center;gap:0.5rem;' + cursor + 'transition:background 0.15s" onmouseover="this.style.background=\'rgba(0,0,0,0.03)\'" onmouseout="this.style.background=\'\'">'
                    + '<span class="badge ' + (repas === 'midi' ? 'bg-warning text-dark' : 'bg-dark') + '" style="font-size:.65rem;min-width:32px">' + repas + '</span>'
                    + '<div style="flex:1;min-width:0">'
                    + '<span style="font-weight:500;font-size:.88rem">' + escapeHtml(menu.plat) + '</span>'
                    + (menu.salade ? ' <span class="text-muted small">/ ' + escapeHtml(menu.salade) + '</span>' : '')
                    + '</div>'
                    + '<span class="badge bg-info text-dark" style="font-size:.65rem">' + (menu.total_couverts || 0) + ' couv.</span>'
                    + (canEdit ? '<i class="bi bi-pencil text-muted" style="font-size:.75rem"></i>' : '')
                    + '</div>';
            } else {
                html += '<div class="ch-menu-row" data-date="' + dateStr + '" data-repas="' + repas + '" style="padding:0.3rem 0.75rem 0.3rem 1.5rem;display:flex;align-items:center;gap:0.5rem;' + cursor + '" onmouseover="this.style.background=\'rgba(0,0,0,0.03)\'" onmouseout="this.style.background=\'\'">'
                    + '<span class="badge bg-light text-muted" style="font-size:.65rem;min-width:32px">' + repas + '</span>'
                    + '<span class="text-muted small fst-italic">Pas de menu</span>'
                    + (canEdit ? '<span class="badge bg-outline-success text-success ms-auto" style="font-size:.65rem;border:1px solid"><i class="bi bi-plus"></i> Créer</span>' : '')
                    + '</div>';
            }
        });

        html += '</div>';
    }
    html += '</div>';
    body.innerHTML = html;

    // Click to edit
    if (canEdit) {
        body.querySelectorAll('.ch-menu-row').forEach(row => {
            row.addEventListener('click', () => {
                const dateStr = row.dataset.date;
                const repas = row.dataset.repas;
                const menu = menusByKey[dateStr + '_' + repas];
                openEditModal(dateStr, repas, menu);
            });
        });
    }
}

function openEditModal(dateStr, repas, menu) {
    const d = new Date(dateStr + 'T00:00:00');
    const dayIdx = (d.getDay() + 6) % 7;
    document.getElementById('chMenuEditTitle').textContent = (menu ? 'Modifier' : 'Créer') + ' — ' + DAYS_FR[dayIdx] + ' ' + repas;
    document.getElementById('chEditDate').value = dateStr;
    document.getElementById('chEditRepas').value = repas;
    document.getElementById('chEditEntree').value = menu?.entree || '';
    document.getElementById('chEditPlat').value = menu?.plat || '';
    document.getElementById('chEditSalade').value = menu?.salade || '';
    document.getElementById('chEditAccomp').value = menu?.accompagnement || '';
    document.getElementById('chEditDessert').value = menu?.dessert || '';
    document.getElementById('chEditRemarques').value = menu?.remarques || '';
    editModal?.show();
    setTimeout(() => document.getElementById('chEditPlat')?.focus(), 300);
}

async function saveMenu() {
    const plat = document.getElementById('chEditPlat').value.trim();
    if (!plat) { toast('Le plat principal est requis', 'error'); return; }

    const data = {
        date_jour: document.getElementById('chEditDate').value,
        repas: document.getElementById('chEditRepas').value,
        entree: document.getElementById('chEditEntree').value.trim(),
        plat,
        salade: document.getElementById('chEditSalade').value.trim(),
        accompagnement: document.getElementById('chEditAccomp').value.trim(),
        dessert: document.getElementById('chEditDessert').value.trim(),
        remarques: document.getElementById('chEditRemarques').value.trim(),
    };

    const res = await apiPost('cuisine_save_menu', data);
    if (res.success) {
        toast('Menu enregistré', 'success');
        editModal?.hide();
        loadMenus();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// Helpers
// ═══════════════════════════════════════

function updateWeekLabel() {
    const el = document.getElementById('chMenuWeekLabel');
    if (!el) return;
    const sun = new Date(menuMonday);
    sun.setDate(sun.getDate() + 6);
    el.textContent = 'S' + weekNum(menuMonday);
}

function getMonday(d) {
    const dt = new Date(d);
    const day = dt.getDay();
    dt.setDate(dt.getDate() - day + (day === 0 ? -6 : 1));
    dt.setHours(0, 0, 0, 0);
    return dt;
}

function shiftWeek(monday, days) {
    const d = new Date(monday);
    d.setDate(d.getDate() + days);
    return d;
}

function weekNum(monday) {
    const t = new Date(monday);
    t.setDate(t.getDate() + 3);
    const y = new Date(t.getFullYear(), 0, 1);
    return Math.ceil(((t - y) / 86400000 + y.getDay() + 1) / 7);
}

function fmtDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function todayStr() { return fmtDate(new Date()); }
