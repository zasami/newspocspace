/**
 * Cuisine module — Menu management, reservations, table VIP
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let menuMonday = null;
let familleModal = null, vipModal = null;

export function init() {
    const denied = window.__SS__?.deniedPerms || [];

    // Hide tabs the user can't access
    document.querySelectorAll('[data-cuis-perm]').forEach(li => {
        const perm = li.dataset.cuisPerm;
        if (denied.includes(perm)) li.style.display = 'none';
    });

    // Activate first visible tab
    const firstVisibleTab = document.querySelector('#cuisineTabs .nav-item:not([style*="display: none"]) .nav-link');
    if (firstVisibleTab) {
        firstVisibleTab.classList.add('active');
        const target = document.querySelector(firstVisibleTab.dataset.bsTarget);
        if (target) { target.classList.add('show', 'active'); }
    }

    // ── Tab 1: Menu saisie ──
    if (!denied.includes('cuisine_saisie_menu')) {
        menuMonday = getMonday(new Date());
        document.getElementById('cuisMenuPrev')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, -7); loadMenus(); });
        document.getElementById('cuisMenuNext')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, 7); loadMenus(); });
        // Load on tab shown
        document.getElementById('tab-saisie')?.addEventListener('shown.bs.tab', loadMenus);
        if (firstVisibleTab?.id === 'tab-saisie') loadMenus();
    }

    // ── Tab 2: Réservations collaborateurs ──
    if (!denied.includes('cuisine_reservations_collab')) {
        const dateInput = document.getElementById('cuisCollabDate');
        const repasSelect = document.getElementById('cuisCollabRepas');
        if (dateInput) dateInput.value = todayStr();
        dateInput?.addEventListener('change', loadCollabReservations);
        repasSelect?.addEventListener('change', loadCollabReservations);
        document.getElementById('cuisCollabPrint')?.addEventListener('click', printCollab);
        document.getElementById('tab-collab')?.addEventListener('shown.bs.tab', loadCollabReservations);
        if (firstVisibleTab?.id === 'tab-collab') loadCollabReservations();
    }

    // ── Tab 3: Réservations famille ──
    if (!denied.includes('cuisine_reservations_famille')) {
        const dateInput = document.getElementById('cuisFamilleDate');
        const repasSelect = document.getElementById('cuisFamilleRepas');
        if (dateInput) dateInput.value = todayStr();
        dateInput?.addEventListener('change', loadFamilleReservations);
        repasSelect?.addEventListener('change', loadFamilleReservations);
        document.getElementById('cuisFamilleAddBtn')?.addEventListener('click', openFamilleModal);

        const modalEl = document.getElementById('cuisFamilleModal');
        if (modalEl) familleModal = new bootstrap.Modal(modalEl);
        document.getElementById('cuisFamilleSaveBtn')?.addEventListener('click', saveFamilleReservation);

        // Autocomplete résident
        document.getElementById('cuisFamilleResidentSearch')?.addEventListener('input', debounce(searchResidents, 300));
        // Autocomplete visiteur
        document.getElementById('cuisFamilleVisiteurSearch')?.addEventListener('input', debounce(searchVisiteurs, 300));

        document.getElementById('tab-famille')?.addEventListener('shown.bs.tab', loadFamilleReservations);
        if (firstVisibleTab?.id === 'tab-famille') loadFamilleReservations();
    }

    // ── Tab 4: Table VIP ──
    if (!denied.includes('cuisine_table_vip')) {
        const vipModalEl = document.getElementById('cuisVipModal');
        if (vipModalEl) vipModal = new bootstrap.Modal(vipModalEl);
        document.getElementById('cuisVipAddBtn')?.addEventListener('click', () => { vipModal?.show(); });
        document.getElementById('cuisVipResidentSearch')?.addEventListener('input', debounce(searchVipResidents, 300));
        document.getElementById('tab-vip')?.addEventListener('shown.bs.tab', loadVip);
        if (firstVisibleTab?.id === 'tab-vip') loadVip();
    }
}

export function destroy() {
    familleModal = null;
    vipModal = null;
    menuMonday = null;
}

// ═══════════════════════════════════════
// TAB 1: Menu saisie
// ═══════════════════════════════════════

async function loadMenus() {
    const body = document.getElementById('cuisMenuBody');
    if (!body) return;

    updateWeekLabel('cuisMenuWeekLabel', menuMonday);
    body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    const res = await apiPost('cuisine_get_menus_semaine', { date: fmtDate(menuMonday) });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur chargement</p>'; return; }

    const menusByDay = {};
    (res.menus || []).forEach(m => {
        const key = m.date_jour + '_' + (m.repas || 'midi');
        menusByDay[key] = m;
    });

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
            const menu = menusByDay[dateStr + '_' + repas];
            const section = document.createElement('div');
            section.className = 'cuis-menu-section';

            const repasLabel = document.createElement('div');
            repasLabel.className = 'cuis-repas-label';
            repasLabel.textContent = repas === 'midi' ? 'Midi' : 'Soir';
            section.appendChild(repasLabel);

            const form = buildMenuForm(dateStr, repas, menu);
            section.appendChild(form);
            dayCard.appendChild(section);
        });

        body.appendChild(dayCard);
    }
}

function buildMenuForm(dateStr, repas, menu) {
    const wrap = document.createElement('div');
    wrap.className = 'cuis-menu-form';

    const fields = [
        { key: 'entree', label: 'Entrée' },
        { key: 'plat', label: 'Plat principal' },
        { key: 'salade', label: 'Salade' },
        { key: 'accompagnement', label: 'Accompagnement' },
        { key: 'dessert', label: 'Dessert' },
        { key: 'remarques', label: 'Remarques' },
    ];

    fields.forEach(f => {
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
    saveBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
    saveBtn.title = 'Enregistrer';
    saveBtn.addEventListener('click', async () => {
        const data = { date_jour: dateStr, repas };
        wrap.querySelectorAll('[data-field]').forEach(el => { data[el.dataset.field] = el.value; });
        const r = await apiPost('cuisine_save_menu', data);
        toast(r.success ? 'Menu enregistré' : (r.message || 'Erreur'), r.success ? 'success' : 'error');
    });
    btnRow.appendChild(saveBtn);

    if (menu?.id) {
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-trash"></i>';
        delBtn.title = 'Supprimer';
        delBtn.addEventListener('click', async () => {
            const r = await apiPost('cuisine_delete_menu', { menu_id: menu.id });
            if (r.success) { toast('Menu supprimé', 'success'); loadMenus(); }
        });
        btnRow.appendChild(delBtn);
    }

    wrap.appendChild(btnRow);
    return wrap;
}

// ═══════════════════════════════════════
// TAB 2: Réservations collaborateurs
// ═══════════════════════════════════════

async function loadCollabReservations() {
    const body = document.getElementById('cuisCollabBody');
    if (!body) return;

    const dateVal = document.getElementById('cuisCollabDate')?.value;
    const repasVal = document.getElementById('cuisCollabRepas')?.value || 'midi';
    if (!dateVal) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
    const res = await apiPost('cuisine_get_reservations_collab', { date: dateVal, repas: repasVal });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-people"></i><p>Aucune réservation pour cette date</p></div>';
        return;
    }

    // Summary
    const summary = document.createElement('div');
    summary.className = 'cuis-collab-summary';
    summary.innerHTML = `<span><strong>${res.total_couverts || 0}</strong> couverts</span>
        <span class="badge bg-primary">${res.nb_menu || 0} menu</span>
        <span class="badge bg-success">${res.nb_salade || 0} salade</span>`;
    body.innerHTML = '';
    body.appendChild(summary);

    // Table
    const table = document.createElement('table');
    table.className = 'table table-sm table-striped cuis-table';
    table.id = 'cuisCollabTable';

    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th></tr>';
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(r.prenom + ' ' + r.nom)}</td>
            <td>${escapeHtml(r.fonction_nom || r.fonction_code || '-')}</td>
            <td><span class="badge ${r.choix === 'menu' ? 'bg-primary' : 'bg-success'}">${escapeHtml(r.choix)}</span></td>
            <td>${r.nb_personnes}</td>
            <td>${escapeHtml(r.paiement || '-')}</td>
            <td>${escapeHtml(r.remarques || '-')}</td>`;
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.appendChild(table);
}

function printCollab() {
    const table = document.getElementById('cuisCollabTable');
    if (!table) { toast('Rien à imprimer', 'error'); return; }

    const dateVal = document.getElementById('cuisCollabDate')?.value || '';
    const repasVal = document.getElementById('cuisCollabRepas')?.value || 'midi';

    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Réservations ${dateVal}</title>
        <style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:10px}
        table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}
        th{background:#f0f0f0}@media print{button{display:none}}</style></head>
        <body><h2>Réservations collaborateurs — ${escapeHtml(dateVal)} (${escapeHtml(repasVal)})</h2>
        ${table.outerHTML}<br><button onclick="window.print()">Imprimer</button></body></html>`);
    win.document.close();
}

// ═══════════════════════════════════════
// TAB 3: Réservations famille
// ═══════════════════════════════════════

async function loadFamilleReservations() {
    const body = document.getElementById('cuisFamilleBody');
    if (!body) return;

    const dateVal = document.getElementById('cuisFamilleDate')?.value;
    const repasVal = document.getElementById('cuisFamilleRepas')?.value || 'midi';
    if (!dateVal) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
    const res = await apiPost('cuisine_get_reservations_famille', { date: dateVal, repas: repasVal });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-house-heart"></i><p>Aucune réservation famille pour cette date</p></div>';
        return;
    }

    body.innerHTML = '';
    const table = document.createElement('table');
    table.className = 'table table-sm table-striped cuis-table';

    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>Résident</th><th>Chambre</th><th>Visiteur</th><th>Relation</th><th>Pers.</th><th>Remarques</th><th>Créé par</th><th></th></tr>';
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const visiteurName = r.visiteur_nom_ref
            ? (r.visiteur_prenom_ref + ' ' + r.visiteur_nom_ref)
            : (r.visiteur_nom || '-');
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(r.resident_prenom + ' ' + r.resident_nom)}</td>
            <td>${escapeHtml(r.chambre || '-')}</td>
            <td>${escapeHtml(visiteurName)}</td>
            <td>${escapeHtml(r.relation || '-')}</td>
            <td>${r.nb_personnes}</td>
            <td>${escapeHtml(r.remarques || '-')}</td>
            <td>${escapeHtml((r.created_prenom || '') + ' ' + (r.created_nom || ''))}</td>
            <td></td>`;

        const actionTd = tr.lastElementChild;
        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        delBtn.title = 'Annuler';
        delBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_delete_reservation_famille', { id: r.id });
            if (result.success) { toast('Réservation annulée', 'success'); loadFamilleReservations(); }
        });
        actionTd.appendChild(delBtn);

        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.appendChild(table);
}

function openFamilleModal() {
    document.getElementById('cuisFamilleId').value = '';
    document.getElementById('cuisFamilleFormDate').value = document.getElementById('cuisFamilleDate')?.value || todayStr();
    document.getElementById('cuisFamilleFormRepas').value = document.getElementById('cuisFamilleRepas')?.value || 'midi';
    document.getElementById('cuisFamilleResidentSearch').value = '';
    document.getElementById('cuisFamilleResidentId').value = '';
    document.getElementById('cuisFamilleVisiteurSearch').value = '';
    document.getElementById('cuisFamilleVisiteurId').value = '';
    document.getElementById('cuisFamilleNb').value = 1;
    document.getElementById('cuisFamilleRemarques').value = '';
    document.getElementById('cuisResidentResults').innerHTML = '';
    document.getElementById('cuisVisiteurResults').innerHTML = '';
    document.getElementById('cuisSaveVisiteurWrap').style.display = 'none';
    familleModal?.show();
}

async function searchResidents() {
    const q = document.getElementById('cuisFamilleResidentSearch')?.value || '';
    const list = document.getElementById('cuisResidentResults');
    if (!list) return;

    if (q.length < 2) { list.innerHTML = ''; return; }
    const res = await apiPost('cuisine_get_residents', { search: q });
    list.innerHTML = '';
    (res.residents || []).forEach(r => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = r.prenom + ' ' + r.nom + (r.chambre ? ' — Ch. ' + r.chambre : '');
        item.addEventListener('click', () => {
            document.getElementById('cuisFamilleResidentSearch').value = r.prenom + ' ' + r.nom;
            document.getElementById('cuisFamilleResidentId').value = r.id;
            list.innerHTML = '';
            // Auto-search visiteurs for this resident
            searchVisiteurs();
        });
        list.appendChild(item);
    });
}

async function searchVisiteurs() {
    const q = document.getElementById('cuisFamilleVisiteurSearch')?.value || '';
    const residentId = document.getElementById('cuisFamilleResidentId')?.value || '';
    const list = document.getElementById('cuisVisiteurResults');
    if (!list) return;

    const saveWrap = document.getElementById('cuisSaveVisiteurWrap');

    if (q.length < 2 && !residentId) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_search_visiteurs', { q, resident_id: residentId });
    list.innerHTML = '';
    (res.visiteurs || []).forEach(v => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = v.prenom + ' ' + v.nom + (v.relation ? ' (' + v.relation + ')' : '');
        item.addEventListener('click', () => {
            document.getElementById('cuisFamilleVisiteurSearch').value = v.prenom + ' ' + v.nom;
            document.getElementById('cuisFamilleVisiteurId').value = v.id;
            list.innerHTML = '';
            if (saveWrap) saveWrap.style.display = 'none';
        });
        list.appendChild(item);
    });

    // Show save option if user typed something not matching
    if (q.length >= 2 && !(res.visiteurs || []).some(v => (v.prenom + ' ' + v.nom).toLowerCase() === q.toLowerCase())) {
        if (saveWrap) saveWrap.style.display = 'block';
        document.getElementById('cuisFamilleVisiteurId').value = '';
    }
}

async function saveFamilleReservation() {
    const residentId = document.getElementById('cuisFamilleResidentId')?.value;
    if (!residentId) { toast('Veuillez sélectionner un résident', 'error'); return; }

    const data = {
        id: document.getElementById('cuisFamilleId')?.value || '',
        date_jour: document.getElementById('cuisFamilleFormDate')?.value,
        repas: document.getElementById('cuisFamilleFormRepas')?.value || 'midi',
        resident_id: residentId,
        visiteur_id: document.getElementById('cuisFamilleVisiteurId')?.value || '',
        visiteur_nom: document.getElementById('cuisFamilleVisiteurSearch')?.value || '',
        nb_personnes: document.getElementById('cuisFamilleNb')?.value || 1,
        remarques: document.getElementById('cuisFamilleRemarques')?.value || '',
    };

    // Save visiteur first if checkbox checked
    if (document.getElementById('cuisSaveVisiteur')?.checked && !data.visiteur_id) {
        const parts = data.visiteur_nom.trim().split(/\s+/);
        const prenom = parts[0] || '';
        const nom = parts.slice(1).join(' ') || parts[0] || '';
        const vRes = await apiPost('cuisine_save_visiteur', {
            prenom, nom, resident_id: residentId
        });
        if (vRes.success) data.visiteur_id = vRes.id;
    }

    const res = await apiPost('cuisine_save_reservation_famille', data);
    if (res.success) {
        toast('Réservation enregistrée', 'success');
        familleModal?.hide();
        loadFamilleReservations();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// TAB 4: Table VIP
// ═══════════════════════════════════════

async function loadVip() {
    const body = document.getElementById('cuisVipBody');
    if (!body) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
    const res = await apiPost('cuisine_get_vip');
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.residents?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-star"></i><p>Aucun résident VIP</p></div>';
        return;
    }

    body.innerHTML = '';
    res.residents.forEach(r => {
        const card = document.createElement('div');
        card.className = 'cuis-vip-card';

        const header = document.createElement('div');
        header.className = 'cuis-vip-header';
        header.innerHTML = `<strong>${escapeHtml(r.prenom + ' ' + r.nom)}</strong>
            <span class="text-muted small">${escapeHtml(r.chambre ? 'Ch. ' + r.chambre : '')} ${escapeHtml(r.etage ? '— Ét. ' + r.etage : '')}</span>`;

        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeBtn.title = 'Retirer VIP';
        removeBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'remove' });
            if (result.success) { toast('Retiré de la table VIP', 'success'); loadVip(); }
        });
        header.appendChild(removeBtn);
        card.appendChild(header);

        const textarea = document.createElement('textarea');
        textarea.className = 'form-control form-control-sm mt-2';
        textarea.rows = 2;
        textarea.placeholder = 'Menu spécial...';
        textarea.value = r.menu_special || '';
        card.appendChild(textarea);

        const saveMenuBtn = document.createElement('button');
        saveMenuBtn.type = 'button';
        saveMenuBtn.className = 'btn btn-sm btn-outline-primary mt-1';
        saveMenuBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer le menu';
        saveMenuBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'set_menu', menu_special: textarea.value });
            toast(result.success ? 'Menu spécial mis à jour' : 'Erreur', result.success ? 'success' : 'error');
        });
        card.appendChild(saveMenuBtn);

        body.appendChild(card);
    });
}

async function searchVipResidents() {
    const q = document.getElementById('cuisVipResidentSearch')?.value || '';
    const list = document.getElementById('cuisVipResidentResults');
    if (!list) return;

    if (q.length < 2) { list.innerHTML = ''; return; }
    const res = await apiPost('cuisine_get_residents', { search: q });
    list.innerHTML = '';
    (res.residents || []).filter(r => !r.is_vip).forEach(r => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = r.prenom + ' ' + r.nom + (r.chambre ? ' — Ch. ' + r.chambre : '');
        item.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'add' });
            if (result.success) {
                toast('Ajouté à la table VIP', 'success');
                vipModal?.hide();
                document.getElementById('cuisVipResidentSearch').value = '';
                list.innerHTML = '';
                loadVip();
            }
        });
        list.appendChild(item);
    });
}

// ═══════════════════════════════════════
// Helpers
// ═══════════════════════════════════════

function getMonday(d) {
    const dt = new Date(d);
    const day = dt.getDay();
    const diff = dt.getDate() - day + (day === 0 ? -6 : 1);
    dt.setDate(diff);
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

function todayStr() { return fmtDate(new Date()); }

function updateWeekLabel(id, monday) {
    const el = document.getElementById(id);
    if (!el) return;
    const sun = new Date(monday);
    sun.setDate(sun.getDate() + 6);
    el.textContent = `${monday.getDate()}/${monday.getMonth() + 1} — ${sun.getDate()}/${sun.getMonth() + 1}/${sun.getFullYear()}`;
}
