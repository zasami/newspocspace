/**
 * Cuisine Menus — Weekly menu management with card layout + modal edit
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let menuMonday = null;
let menusCache = {};
let modal = null;
let canEdit = true;

export async function init() {
    const denied = window.__ZT__?.deniedPerms || [];
    canEdit = !denied.includes('cuisine_saisie_menu');
    menuMonday = getMonday(new Date());

    document.getElementById('cmPrev')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, -7); loadMenus(); });
    document.getElementById('cmNext')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, 7); loadMenus(); });

    const mEl = document.getElementById('cmModal');
    if (mEl) modal = new bootstrap.Modal(mEl);
    document.getElementById('cmSaveBtn')?.addEventListener('click', saveMenu);

    document.getElementById('cmPrintDay')?.addEventListener('click', e => { e.preventDefault(); printDay(); });
    document.getElementById('cmPrintWeek')?.addEventListener('click', e => { e.preventDefault(); printWeek(); });

    loadMenus();
}

export function destroy() { modal = null; menuMonday = null; menusCache = {}; }

// ═══════════════════════════════════════
// Load & render cards
// ═══════════════════════════════════════

async function loadMenus() {
    const body = document.getElementById('cmBody');
    if (!body) return;
    updateWeekLabel();
    body.innerHTML = '<div class="text-center py-4" style="grid-column:1/-1"><span class="spinner"></span></div>';

    const res = await apiPost('cuisine_get_menus_semaine', { date: fmtDate(menuMonday) });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    menusCache = {};
    (res.menus || []).forEach(m => { menusCache[m.date_jour + '_' + (m.repas || 'midi')] = m; });

    const today = fmtDate(new Date());
    body.innerHTML = '';

    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);
        const isToday = dateStr === today;
        const midi = menusCache[dateStr + '_midi'];
        const soir = menusCache[dateStr + '_soir'];
        const hasAny = midi || soir;

        if (!hasAny && canEdit) {
            // Empty card — dashed
            const card = document.createElement('div');
            card.className = 'cm-card cm-card--empty' + (isToday ? ' is-today' : '');
            card.innerHTML = '<div class="cm-card-day">' + DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth()+1) + '</div>'
                + '<i class="bi bi-plus-circle cm-add-icon"></i>'
                + '<span class="small text-muted">Ajouter le menu</span>';
            card.addEventListener('click', () => openModal(dateStr, 'midi', null));
            body.appendChild(card);
        } else {
            const card = document.createElement('div');
            card.className = 'cm-card' + (isToday ? ' is-today' : '');

            // Header
            const header = document.createElement('div');
            header.className = 'cm-card-header';
            header.innerHTML = '<span class="cm-card-day">' + DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth()+1) + '</span>'
                + (isToday ? ' <span class="badge bg-primary" style="font-size:.6rem">Aujourd\'hui</span>' : '');
            card.appendChild(header);

            // Body
            const cardBody = document.createElement('div');
            cardBody.className = 'cm-card-body';

            cardBody.appendChild(buildMealBlock('midi', midi, dateStr));
            const hr = document.createElement('hr');
            hr.className = 'cm-divider';
            cardBody.appendChild(hr);
            cardBody.appendChild(buildMealBlock('soir', soir, dateStr));

            card.appendChild(cardBody);
            body.appendChild(card);
        }
    }
}

function buildMealBlock(repas, menu, dateStr) {
    const block = document.createElement('div');
    block.className = 'cm-meal';

    const icon = repas === 'midi' ? 'bi-sun' : 'bi-moon-stars';
    const label = repas === 'midi' ? 'Midi' : 'Soir';

    if (menu) {
        let labelHtml = '<div class="cm-meal-label ' + repas + '"><i class="bi ' + icon + '"></i> ' + label
            + ' <span class="cm-meal-stats">' + (menu.total_couverts || 0) + ' couv.</span></div>';

        const items = [
            { val: menu.entree, bold: false },
            { val: menu.plat, bold: true },
            { val: menu.salade, bold: false },
            { val: menu.accompagnement, bold: false },
            { val: menu.dessert, bold: false },
        ].filter(i => i.val);

        let itemsHtml = '<div class="cm-meal-items">'
            + items.map(i => (i.bold ? '<strong>' + escapeHtml(i.val) + '</strong>' : escapeHtml(i.val))).join(' · ')
            + '</div>';

        if (menu.remarques) {
            itemsHtml += '<div class="cm-meal-remark"><i class="bi bi-info-circle"></i> ' + escapeHtml(menu.remarques) + '</div>';
        }

        let actionsHtml = '';
        if (canEdit) {
            actionsHtml = '<div class="cm-card-actions mt-1">'
                + '<button class="btn btn-outline-primary cm-edit-btn" data-date="' + dateStr + '" data-repas="' + repas + '"><i class="bi bi-pencil"></i> Modifier</button>'
                + '<button class="btn btn-outline-danger cm-del-btn" data-id="' + menu.id + '"><i class="bi bi-trash"></i></button>'
                + '</div>';
        }

        block.innerHTML = labelHtml + itemsHtml + actionsHtml;

        // Event listeners
        block.querySelector('.cm-edit-btn')?.addEventListener('click', () => openModal(dateStr, repas, menu));
        block.querySelector('.cm-del-btn')?.addEventListener('click', async () => {
            if (!confirm('Supprimer ce menu et ses réservations ?')) return;
            const r = await apiPost('cuisine_delete_menu', { menu_id: menu.id });
            if (r.success) { toast('Supprimé', 'success'); loadMenus(); }
        });
    } else {
        block.innerHTML = '<div class="cm-meal-label ' + repas + '"><i class="bi ' + icon + '"></i> ' + label + '</div>'
            + '<div class="cm-meal-empty">Pas de menu</div>'
            + (canEdit ? '<button class="btn btn-sm btn-outline-secondary mt-1 cm-create-btn" style="font-size:.72rem"><i class="bi bi-plus"></i> Créer</button>' : '');
        block.querySelector('.cm-create-btn')?.addEventListener('click', () => openModal(dateStr, repas, null));
    }

    return block;
}

// ═══════════════════════════════════════
// Modal edit/create
// ═══════════════════════════════════════

function openModal(dateStr, repas, menu) {
    const d = new Date(dateStr + 'T00:00:00');
    const dayIdx = (d.getDay() + 6) % 7;
    document.getElementById('cmModalTitle').textContent = menu ? 'Modifier le menu' : 'Créer le menu';
    document.getElementById('cmModalSub').textContent = DAYS_FR[dayIdx] + ' ' + d.getDate() + '/' + (d.getMonth()+1) + ' — ' + (repas === 'midi' ? 'Midi' : 'Soir');
    document.getElementById('cmDate').value = dateStr;
    document.getElementById('cmRepas').value = repas;
    document.getElementById('cmEntree').value = menu?.entree || '';
    document.getElementById('cmPlat').value = menu?.plat || '';
    document.getElementById('cmSalade').value = menu?.salade || '';
    document.getElementById('cmAccomp').value = menu?.accompagnement || '';
    document.getElementById('cmDessert').value = menu?.dessert || '';
    document.getElementById('cmRemarques').value = menu?.remarques || '';
    modal?.show();
    setTimeout(() => document.getElementById('cmPlat')?.focus(), 300);
}

async function saveMenu() {
    const plat = document.getElementById('cmPlat').value.trim();
    if (!plat) { toast('Le plat principal est requis', 'error'); return; }

    const btn = document.getElementById('cmSaveBtn');
    btn.disabled = true;

    const res = await apiPost('cuisine_save_menu', {
        date_jour: document.getElementById('cmDate').value,
        repas: document.getElementById('cmRepas').value,
        entree: document.getElementById('cmEntree').value.trim(),
        plat,
        salade: document.getElementById('cmSalade').value.trim(),
        accompagnement: document.getElementById('cmAccomp').value.trim(),
        dessert: document.getElementById('cmDessert').value.trim(),
        remarques: document.getElementById('cmRemarques').value.trim(),
    });

    btn.disabled = false;
    if (res.success) {
        toast('Menu enregistré', 'success');
        modal?.hide();
        loadMenus();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// Print
// ═══════════════════════════════════════

function printDay() {
    const today = fmtDate(new Date());
    const midi = menusCache[today + '_midi'];
    const soir = menusCache[today + '_soir'];
    const dayName = DAYS_FR[(new Date().getDay() + 6) % 7];
    let html = '<h2>Menu du ' + dayName + ' ' + fmtDateFr(new Date()) + '</h2>';
    html += printMealSection('Midi', midi) + printMealSection('Soir', soir);
    openPrint('Menu du jour', html);
}

function printWeek() {
    let html = '<h2>Menus — ' + escapeHtml(document.getElementById('cmWeekLabel')?.textContent || '') + '</h2>';
    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday); d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);
        html += '<h3 style="margin-top:1rem;border-bottom:2px solid #333;padding-bottom:4px">' + DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth()+1) + '</h3>';
        html += printMealSection('Midi', menusCache[dateStr + '_midi']);
        html += printMealSection('Soir', menusCache[dateStr + '_soir']);
    }
    openPrint('Menus semaine', html);
}

function printMealSection(label, menu) {
    let html = '<div style="margin-left:12px"><strong style="text-transform:uppercase;font-size:11px;color:#666">' + label + '</strong>';
    if (menu) {
        const parts = [menu.entree, menu.plat, menu.accompagnement, menu.salade, menu.dessert].filter(Boolean);
        html += '<div style="font-size:13px">' + parts.map(p => escapeHtml(p)).join(' · ') + '</div>';
        if (menu.remarques) html += '<div style="font-size:11px;color:#888;font-style:italic">' + escapeHtml(menu.remarques) + '</div>';
    } else {
        html += '<div style="font-size:13px;color:#999;font-style:italic">Pas de menu</div>';
    }
    return html + '</div>';
}

function openPrint(title, body) {
    const win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>' + escapeHtml(title) + '</title>'
        + '<style>body{font-family:Arial,sans-serif;padding:24px;max-width:800px;margin:0 auto}'
        + 'h2{margin:0 0 8px;font-size:18px}h3{font-size:14px;margin:0 0 4px}'
        + '@media print{.no-print{display:none}}</style></head>'
        + '<body>' + body
        + '<div class="no-print" style="margin-top:20px;text-align:center">'
        + '<button onclick="window.print()" style="padding:8px 24px;font-size:14px;cursor:pointer">Imprimer</button></div>'
        + '</body></html>');
    win.document.close();
}

// ═══════════════════════════════════════
// Helpers
// ═══════════════════════════════════════

function updateWeekLabel() {
    const el = document.getElementById('cmWeekLabel');
    if (!el) return;
    const sun = new Date(menuMonday); sun.setDate(sun.getDate() + 6);
    el.textContent = fmtDateFr(menuMonday) + ' — ' + fmtDateFr(sun);
}

function getMonday(d) {
    const dt = new Date(d); const day = dt.getDay();
    dt.setDate(dt.getDate() - day + (day === 0 ? -6 : 1));
    dt.setHours(0,0,0,0); return dt;
}
function shiftWeek(m, days) { const d = new Date(m); d.setDate(d.getDate() + days); return d; }
function fmtDate(d) { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
function fmtDateFr(d) { return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); }
