/**
 * Cuisine Menus — Weekly menu management (create/edit/view/print)
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
const FIELDS = [
    { key: 'entree', label: 'Entrée' },
    { key: 'plat', label: 'Plat principal' },
    { key: 'salade', label: 'Salade' },
    { key: 'accompagnement', label: 'Accompagnement' },
    { key: 'dessert', label: 'Dessert' },
    { key: 'remarques', label: 'Remarques' },
];

let menuMonday = null;
let canEdit = true;

export async function init() {
    const denied = window.__ZT__?.deniedPerms || [];
    canEdit = !denied.includes('cuisine_saisie_menu');

    menuMonday = getMonday(new Date());
    document.getElementById('cmPrev')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, -7); loadMenus(); });
    document.getElementById('cmNext')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, 7); loadMenus(); });
    document.getElementById('cmPrintBtn')?.addEventListener('click', printMenus);
    loadMenus();
}

export function destroy() {
    menuMonday = null;
}

async function loadMenus() {
    const body = document.getElementById('cmBody');
    if (!body) return;

    updateWeekLabel();
    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';

    const res = await apiPost('cuisine_get_menus_semaine', { date: fmtDate(menuMonday) });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur chargement</p>'; return; }

    const menusByKey = {};
    (res.menus || []).forEach(m => { menusByKey[m.date_jour + '_' + (m.repas || 'midi')] = m; });

    body.innerHTML = '';
    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);

        const dayCard = document.createElement('div');
        dayCard.className = 'cuis-day-card';

        const dayHeader = document.createElement('div');
        dayHeader.className = 'cuis-day-header';
        dayHeader.textContent = DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth() + 1);
        dayCard.appendChild(dayHeader);

        ['midi', 'soir'].forEach(repas => {
            const menu = menusByKey[dateStr + '_' + repas];
            const section = document.createElement('div');
            section.className = 'cuis-menu-section';

            const repasLabel = document.createElement('div');
            repasLabel.className = 'cuis-repas-label';
            repasLabel.textContent = repas === 'midi' ? 'Midi' : 'Soir';
            if (menu) {
                repasLabel.innerHTML += ' <span class="badge bg-info text-dark ms-2" style="font-size:.7rem">'
                    + (menu.nb_reservations || 0) + ' résa / ' + (menu.total_couverts || 0) + ' couv.</span>';
            }
            section.appendChild(repasLabel);

            if (canEdit) {
                // Editable form
                const form = buildMenuForm(dateStr, repas, menu);
                section.appendChild(form);
            } else {
                // Read-only display
                const display = buildMenuDisplay(menu);
                section.appendChild(display);
            }

            dayCard.appendChild(section);
        });

        body.appendChild(dayCard);
    }
}

function buildMenuForm(dateStr, repas, menu) {
    const wrap = document.createElement('div');
    wrap.className = 'cuis-menu-form';

    FIELDS.forEach(f => {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm cuis-menu-input';
        input.placeholder = f.label;
        input.dataset.field = f.key;
        input.value = menu?.[f.key] || '';
        wrap.appendChild(input);
    });

    const btnRow = document.createElement('div');
    btnRow.className = 'cuis-menu-btn-row';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'btn btn-sm btn-primary';
    saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
    saveBtn.addEventListener('click', async () => {
        const data = { date_jour: dateStr, repas };
        wrap.querySelectorAll('[data-field]').forEach(el => { data[el.dataset.field] = el.value; });
        const r = await apiPost('cuisine_save_menu', data);
        toast(r.success ? 'Menu enregistré' : (r.message || 'Erreur'), r.success ? 'success' : 'error');
        if (r.success) loadMenus();
    });
    btnRow.appendChild(saveBtn);

    if (menu?.id) {
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-trash"></i>';
        delBtn.title = 'Supprimer';
        delBtn.addEventListener('click', async () => {
            if (!confirm('Supprimer ce menu et ses réservations ?')) return;
            const r = await apiPost('cuisine_delete_menu', { menu_id: menu.id });
            if (r.success) { toast('Menu supprimé', 'success'); loadMenus(); }
        });
        btnRow.appendChild(delBtn);
    }

    wrap.appendChild(btnRow);
    return wrap;
}

function buildMenuDisplay(menu) {
    const wrap = document.createElement('div');
    if (!menu) {
        wrap.innerHTML = '<p class="text-muted small fst-italic mb-0">Pas de menu</p>';
        return wrap;
    }
    const items = FIELDS.filter(f => f.key !== 'remarques' && menu[f.key]);
    wrap.innerHTML = items.map(f =>
        '<span class="me-3"><strong class="small text-muted">' + f.label + ':</strong> ' + escapeHtml(menu[f.key]) + '</span>'
    ).join('');
    if (menu.remarques) {
        wrap.innerHTML += '<div class="text-muted small mt-1"><i class="bi bi-info-circle"></i> ' + escapeHtml(menu.remarques) + '</div>';
    }
    return wrap;
}

function printMenus() {
    const body = document.getElementById('cmBody');
    if (!body) return;
    const label = document.getElementById('cmWeekLabel')?.textContent || '';
    const win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Menus ' + escapeHtml(label) + '</title>'
        + '<style>body{font-family:Arial,sans-serif;padding:20px;font-size:13px}'
        + '.cuis-day-card{margin-bottom:12px;border:1px solid #ddd;border-radius:6px;padding:8px}'
        + '.cuis-day-header{font-weight:bold;font-size:15px;margin-bottom:6px;border-bottom:1px solid #eee;padding-bottom:4px}'
        + '.cuis-repas-label{font-weight:600;font-size:13px;margin:4px 0 2px}'
        + '.cuis-menu-form,.cuis-menu-section{margin-bottom:4px}'
        + 'input,.btn,.cuis-menu-btn-row,.badge{display:none}'
        + '@media print{button{display:none}}</style></head>'
        + '<body><h2>Menus — ' + escapeHtml(label) + '</h2>'
        + body.innerHTML + '<br><button onclick="window.print()">Imprimer</button></body></html>');
    win.document.close();
}

function updateWeekLabel() {
    const el = document.getElementById('cmWeekLabel');
    if (!el) return;
    const sun = new Date(menuMonday);
    sun.setDate(sun.getDate() + 6);
    el.textContent = fmtDateFr(menuMonday) + ' — ' + fmtDateFr(sun);
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

function fmtDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fmtDateFr(d) {
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}
