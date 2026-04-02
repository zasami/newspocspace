/**
 * Cuisine Home — Dashboard for external cuisine employees
 * 7 day cards, inline menu display, create/edit/reuse/print
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let menuMonday = null;
let menuModal = null;
let reuseModal = null;
let cmdModal = null;
let menusCache = {};
let canEdit = true;
let reuseSource = null; // menu data to copy

export async function init() {
    const user = window.__SS__?.user;
    const denied = window.__SS__?.deniedPerms || [];
    canEdit = !denied.includes('cuisine_saisie_menu');

    const nameEl = document.getElementById('chUserName');
    if (nameEl && user) nameEl.textContent = user.prenom || '';

    menuMonday = getMonday(new Date());

    // Modals
    const mEl = document.getElementById('chMenuModal');
    if (mEl) menuModal = new bootstrap.Modal(mEl);
    const rEl = document.getElementById('chReuseModal');
    if (rEl) reuseModal = new bootstrap.Modal(rEl);
    const cEl = document.getElementById('chCmdModal');
    if (cEl) cmdModal = new bootstrap.Modal(cEl);

    // Navigation
    document.getElementById('chMenuPrev')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, -7); loadAll(); });
    document.getElementById('chMenuNext')?.addEventListener('click', () => { menuMonday = shiftWeek(menuMonday, 7); loadAll(); });

    // Save menu
    document.getElementById('chEditSaveBtn')?.addEventListener('click', saveMenu);

    // Reuse save
    document.getElementById('chReuseSaveBtn')?.addEventListener('click', saveReuse);

    // Print
    document.getElementById('chPrintDay')?.addEventListener('click', e => { e.preventDefault(); printDay(); });
    document.getElementById('chPrintWeek')?.addEventListener('click', e => { e.preventDefault(); printWeek(); });

    // Commandes
    document.getElementById('chRepas')?.addEventListener('change', loadCommandes);
    document.getElementById('chPrintCommandes')?.addEventListener('click', printCommandes);
    document.getElementById('chAddCmdBtn')?.addEventListener('click', openCmdModal);
    document.getElementById('chCmdSaveBtn')?.addEventListener('click', saveCmdCommande);
    document.getElementById('chCmdUserSearch')?.addEventListener('input', debounce(searchCmdUsers, 300));
    initCmdModalEvents();

    // Use SSR data on first load to avoid waterfall requests
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr) {
        renderMenuCards(ssr);
        renderCommandes(ssr);
        renderFamilleStats(ssr);
    } else {
        await loadAll();
    }
}

export function destroy() {
    menuModal = null;
    reuseModal = null;
    cmdModal = null;
    menuMonday = null;
    menusCache = {};
}

async function loadAll() {
    await Promise.all([loadMenuCards(), loadCommandes(), loadFamilleStats()]);
}

// ═══════════════════════════════════════
// MENU CARDS (7 day cards)
// ═══════════════════════════════════════

async function loadMenuCards() {
    const container = document.getElementById('chMenuCards');
    if (!container) return;

    updateWeekLabel();
    container.innerHTML = '<div class="text-center py-4" style="grid-column:1/-1"><span class="spinner"></span></div>';

    const res = await apiPost('cuisine_get_menus_semaine', { date: fmtDate(menuMonday) });
    if (!res.success) { container.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    renderMenuCards(res);
}

function renderMenuCards(res) {
    const container = document.getElementById('chMenuCards');
    if (!container) return;

    updateWeekLabel();

    menusCache = {};
    (res.menus || []).forEach(m => { menusCache[m.date_jour + '_' + (m.repas || 'midi')] = m; });

    // Stats
    const nbSaisis = Object.keys(menusCache).length;
    document.getElementById('chStatMenusSaisis').textContent = nbSaisis + '/14';

    const today = todayStr();
    container.innerHTML = '';

    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);
        const isToday = dateStr === today;
        const midiMenu = menusCache[dateStr + '_midi'];
        const soirMenu = menusCache[dateStr + '_soir'];
        const hasAny = midiMenu || soirMenu;

        if (!hasAny && canEdit) {
            // Empty card — dashed border + plus icon
            const card = document.createElement('div');
            card.className = 'ch-day-card ch-day-card--empty' + (isToday ? ' is-today' : '');
            card.innerHTML = '<div class="ch-day-name">' + DAYS_FR[i] + '</div>'
                + '<div class="ch-day-date">' + fmtDateFr(d) + '</div>'
                + '<i class="bi bi-plus-circle ch-add-icon"></i>'
                + '<span class="small text-muted mt-1">Ajouter le menu</span>';
            card.addEventListener('click', () => openModal(dateStr, 'midi', null));
            container.appendChild(card);
        } else {
            // Filled card
            const card = document.createElement('div');
            card.className = 'ch-day-card' + (isToday ? ' is-today' : '');

            const header = document.createElement('div');
            header.className = 'ch-day-header';
            header.innerHTML = '<div><span class="ch-day-name">' + DAYS_FR[i] + '</span>'
                + (isToday ? ' <span class="badge bg-primary" style="font-size:.6rem;vertical-align:middle">Aujourd\'hui</span>' : '')
                + '<br><span class="ch-day-date">' + fmtDateFr(d) + '</span></div>';
            card.appendChild(header);

            ['midi', 'soir'].forEach(repas => {
                const menu = menusCache[dateStr + '_' + repas];
                const block = document.createElement('div');
                block.className = 'ch-repas-block';

                if (menu) {
                    block.innerHTML = '<span class="ch-repas-tag ' + repas + '">' + repas + '</span>'
                        + ' <span class="ch-couv-badge">' + (menu.total_couverts || 0) + ' couv.</span>'
                        + '<div class="ch-menu-plat">' + escapeHtml(menu.plat) + '</div>'
                        + '<div class="ch-menu-detail">'
                        + [menu.entree, menu.salade, menu.accompagnement, menu.dessert].filter(Boolean).map(escapeHtml).join(' · ')
                        + '</div>';

                    if (canEdit) {
                        const actions = document.createElement('div');
                        actions.className = 'ch-menu-actions';

                        const editBtn = document.createElement('button');
                        editBtn.className = 'btn btn-outline-primary';
                        editBtn.innerHTML = '<i class="bi bi-pencil"></i> Modifier';
                        editBtn.addEventListener('click', () => openModal(dateStr, repas, menu));
                        actions.appendChild(editBtn);

                        const reuseBtn = document.createElement('button');
                        reuseBtn.className = 'btn btn-outline-secondary';
                        reuseBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Réutiliser';
                        reuseBtn.addEventListener('click', () => openReuse(menu));
                        actions.appendChild(reuseBtn);

                        const delBtn = document.createElement('button');
                        delBtn.className = 'btn btn-outline-danger';
                        delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                        delBtn.addEventListener('click', async () => {
                            if (!confirm('Supprimer ce menu et ses réservations ?')) return;
                            const r = await apiPost('cuisine_delete_menu', { menu_id: menu.id });
                            if (r.success) { toast('Supprimé', 'success'); loadAll(); }
                        });
                        actions.appendChild(delBtn);

                        block.appendChild(actions);
                    }
                } else if (canEdit) {
                    block.innerHTML = '<span class="ch-repas-tag ' + repas + '">' + repas + '</span>';
                    const addLink = document.createElement('button');
                    addLink.className = 'btn btn-sm btn-outline-secondary';
                    addLink.style.cssText = 'font-size:.72rem;margin-left:.5rem';
                    addLink.innerHTML = '<i class="bi bi-plus"></i> Créer';
                    addLink.addEventListener('click', () => openModal(dateStr, repas, null));
                    block.appendChild(addLink);
                }

                card.appendChild(block);
            });

            container.appendChild(card);
        }
    }
}

// ═══════════════════════════════════════
// CREATE / EDIT MODAL
// ═══════════════════════════════════════

function openModal(dateStr, repas, menu) {
    const d = new Date(dateStr + 'T00:00:00');
    const dayIdx = (d.getDay() + 6) % 7;

    document.getElementById('chModalTitle').textContent = menu ? 'Modifier le menu' : 'Créer le menu';
    document.getElementById('chModalSubtitle').textContent = DAYS_FR[dayIdx] + ' ' + fmtDateFr(d) + ' — ' + (repas === 'midi' ? 'Midi' : 'Soir');
    document.getElementById('chEditDate').value = dateStr;
    document.getElementById('chEditRepas').value = repas;
    document.getElementById('chEditEntree').value = menu?.entree || '';
    document.getElementById('chEditPlat').value = menu?.plat || '';
    document.getElementById('chEditSalade').value = menu?.salade || '';
    document.getElementById('chEditAccomp').value = menu?.accompagnement || '';
    document.getElementById('chEditDessert').value = menu?.dessert || '';
    document.getElementById('chEditRemarques').value = menu?.remarques || '';

    menuModal?.show();
    setTimeout(() => document.getElementById('chEditPlat')?.focus(), 300);
}

async function saveMenu() {
    const plat = document.getElementById('chEditPlat').value.trim();
    if (!plat) { toast('Le plat principal est requis', 'error'); return; }

    const btn = document.getElementById('chEditSaveBtn');
    btn.disabled = true;

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
    btn.disabled = false;

    if (res.success) {
        toast('Menu enregistré', 'success');
        menuModal?.hide();
        loadAll();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// REUSE (copy menu to another day)
// ═══════════════════════════════════════

function openReuse(menu) {
    reuseSource = menu;
    // Default: tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('chReuseDate').value = fmtDate(tomorrow);
    document.getElementById('chReuseRepas').value = menu.repas || 'midi';
    reuseModal?.show();
}

async function saveReuse() {
    if (!reuseSource) return;
    const dateTarget = document.getElementById('chReuseDate').value;
    const repasTarget = document.getElementById('chReuseRepas').value;
    if (!dateTarget) { toast('Choisissez une date', 'error'); return; }

    const btn = document.getElementById('chReuseSaveBtn');
    btn.disabled = true;

    const res = await apiPost('cuisine_save_menu', {
        date_jour: dateTarget,
        repas: repasTarget,
        entree: reuseSource.entree || '',
        plat: reuseSource.plat || '',
        salade: reuseSource.salade || '',
        accompagnement: reuseSource.accompagnement || '',
        dessert: reuseSource.dessert || '',
        remarques: reuseSource.remarques || '',
    });

    btn.disabled = false;

    if (res.success) {
        toast('Menu copié', 'success');
        reuseModal?.hide();
        reuseSource = null;
        loadAll();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// COMMANDES DU JOUR
// ═══════════════════════════════════════

async function loadCommandes() {
    const body = document.getElementById('chCommandesBody');
    const repas = document.getElementById('chRepas')?.value || 'midi';
    if (!body) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_reservations_collab', { date: todayStr(), repas });

    renderCommandes(res);
}

function renderCommandes(res) {
    const body = document.getElementById('chCommandesBody');
    if (!body) return;

    // chStatCouverts removed — replaced by famille stats
    document.getElementById('chStatMenu').textContent = res.nb_menu || 0;
    document.getElementById('chStatSalade').textContent = res.nb_salade || 0;

    if (!res.success || !res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:1.5rem"><i class="bi bi-receipt"></i><p>Aucune commande pour aujourd\'hui</p></div>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'table table-sm table-striped mb-0';
    table.id = 'chCmdTable';
    table.innerHTML = '<thead><tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th><th></th></tr></thead>';
    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
            + '<td class="small">' + escapeHtml(r.fonction_nom || r.fonction_code || '-') + '</td>'
            + '<td><span class="badge ' + (r.choix === 'menu' ? 'bg-primary' : 'bg-success') + '" style="font-size:.72rem">' + escapeHtml(r.choix) + '</span></td>'
            + '<td>' + r.nb_personnes + '</td>'
            + '<td class="small">' + escapeHtml(r.paiement || '-') + '</td>'
            + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td><td></td>';

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        delBtn.title = 'Annuler';
        delBtn.addEventListener('click', async () => {
            if (!confirm('Annuler cette commande ?')) return;
            const result = await apiPost('cuisine_delete_commande', { id: r.id });
            if (result.success) { toast('Annulée', 'success'); loadCommandes(); }
        });
        tr.lastElementChild.appendChild(delBtn);
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.innerHTML = '';
    body.appendChild(table);
}

// ═══════════════════════════════════════
// ADD COMMANDE (from dashboard)
// ═══════════════════════════════════════

function openCmdModal() {
    document.getElementById('chCmdUserSearch').value = '';
    document.getElementById('chCmdUserId').value = '';
    document.getElementById('chCmdUserResults').innerHTML = '';
    document.getElementById('chCmdNb').value = '1';
    document.getElementById('chCmdRemarques').value = '';

    // Reset choix radio
    const menuRadio = document.querySelector('#chCmdModal input[name="chCmdChoix"][value="menu"]');
    if (menuRadio) menuRadio.checked = true;
    document.querySelectorAll('#chCmdModal .menu-choix-option').forEach(o => {
        o.style.borderColor = 'var(--ss-border)'; o.style.background = '';
    });
    if (menuRadio) { menuRadio.closest('.menu-choix-option').style.borderColor = 'var(--ss-teal)'; menuRadio.closest('.menu-choix-option').style.background = 'var(--ss-accent-bg)'; }

    // Reset paiement radio
    const salRadio = document.querySelector('#chCmdModal input[name="chCmdPaiement"][value="salaire"]');
    if (salRadio) salRadio.checked = true;
    document.querySelectorAll('#chCmdModal .menu-pay-option').forEach(o => {
        o.style.borderColor = 'var(--ss-border)'; o.style.background = '';
    });
    if (salRadio) { salRadio.closest('.menu-pay-option').style.borderColor = 'var(--ss-teal)'; salRadio.closest('.menu-pay-option').style.background = 'var(--ss-accent-bg)'; }

    // Reset quick tags
    document.querySelectorAll('#chCmdModal .ch-quick-tag').forEach(b => {
        b.style.background = ''; b.style.borderColor = '';
    });

    cmdModal?.show();
}

function initCmdModalEvents() {
    // Choix toggle
    document.querySelectorAll('#chCmdModal .menu-choix-option').forEach(opt => {
        opt.querySelector('input')?.addEventListener('change', () => {
            document.querySelectorAll('#chCmdModal .menu-choix-option').forEach(o => {
                o.style.borderColor = 'var(--ss-border)'; o.style.background = '';
            });
            opt.style.borderColor = 'var(--ss-teal)'; opt.style.background = 'var(--ss-accent-bg)';
        });
    });
    // Paiement toggle
    document.querySelectorAll('#chCmdModal input[name="chCmdPaiement"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('#chCmdModal .menu-pay-option').forEach(el => {
                el.style.borderColor = 'var(--ss-border)'; el.style.background = '';
            });
            if (radio.checked) {
                radio.closest('.menu-pay-option').style.borderColor = 'var(--ss-teal)';
                radio.closest('.menu-pay-option').style.background = 'var(--ss-accent-bg)';
            }
        });
    });
    // Quick tags
    document.querySelectorAll('#chCmdModal .ch-quick-tag').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const input = document.getElementById('chCmdRemarques');
            const tag = btn.dataset.tag;
            if (input.value.toLowerCase().includes(tag.toLowerCase())) return;
            input.value = input.value.trim() ? input.value.trim() + ', ' + tag : tag;
            btn.style.background = 'var(--ss-accent-bg)'; btn.style.borderColor = 'var(--ss-teal)';
        });
    });
}

async function searchCmdUsers() {
    const q = document.getElementById('chCmdUserSearch')?.value || '';
    const list = document.getElementById('chCmdUserResults');
    if (!list) return;
    if (q.length < 2) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_search_users', { q });
    list.innerHTML = '';
    (res.users || []).forEach(u => {
        const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.style.cssText = 'display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.75rem';

        // Avatar
        if (u.photo) {
            item.innerHTML = '<img src="' + escapeHtml(u.photo) + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">';
        } else {
            item.innerHTML = '<div style="width:32px;height:32px;border-radius:50%;background:var(--ss-teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">' + escapeHtml(initials) + '</div>';
        }

        // Name + fonction
        const info = document.createElement('div');
        info.innerHTML = '<div style="font-weight:600;font-size:0.88rem">' + escapeHtml(u.prenom + ' ' + u.nom) + '</div>'
            + (u.fonction_nom ? '<div style="font-size:0.75rem;color:var(--ss-text-muted)">' + escapeHtml(u.fonction_nom) + '</div>' : '');
        item.appendChild(info);

        item.addEventListener('click', () => {
            document.getElementById('chCmdUserSearch').value = u.prenom + ' ' + u.nom;
            document.getElementById('chCmdUserId').value = u.id;
            list.innerHTML = '';
        });
        list.appendChild(item);
    });
}

async function saveCmdCommande() {
    const userId = document.getElementById('chCmdUserId')?.value;
    if (!userId) { toast('Sélectionnez un collaborateur', 'error'); return; }

    const btn = document.getElementById('chCmdSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Envoi...';

    const res = await apiPost('cuisine_add_commande', {
        date_jour: todayStr(),
        repas: document.getElementById('chRepas')?.value || 'midi',
        user_id: userId,
        choix: document.querySelector('#chCmdModal input[name="chCmdChoix"]:checked')?.value || 'menu',
        nb_personnes: document.getElementById('chCmdNb')?.value || 1,
        paiement: document.querySelector('#chCmdModal input[name="chCmdPaiement"]:checked')?.value || 'salaire',
        remarques: document.getElementById('chCmdRemarques')?.value || '',
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmer la commande';

    if (res.success) {
        toast('Commande enregistrée', 'success');
        cmdModal?.hide();
        loadCommandes();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

// ═══════════════════════════════════════
// FAMILLE STATS
// ═══════════════════════════════════════

async function loadFamilleStats() {
    const res = await apiPost('cuisine_get_reservations_famille', { date: todayStr(), repas: 'midi' });
    renderFamilleStats(res);
}

function renderFamilleStats(res) {
    const count = res.nb_famille ?? res.reservations?.length ?? 0;
    document.getElementById('chStatFamille').textContent = count;
}

// ═══════════════════════════════════════
// PRINT
// ═══════════════════════════════════════

function printDay() {
    const today = todayStr();
    const midi = menusCache[today + '_midi'];
    const soir = menusCache[today + '_soir'];

    const dayName = DAYS_FR[(new Date().getDay() + 6) % 7];
    let html = '<h2>Menu du ' + dayName + ' ' + fmtDateFr(new Date()) + '</h2>';

    [{ label: 'Midi', m: midi }, { label: 'Soir', m: soir }].forEach(({ label, m }) => {
        html += '<h3 style="margin-top:1rem;border-bottom:1px solid #ccc;padding-bottom:4px">' + label + '</h3>';
        if (m) {
            html += '<table style="width:100%;border-collapse:collapse;margin-bottom:8px">';
            [['Entrée', m.entree], ['Plat', m.plat], ['Salade', m.salade], ['Accompagnement', m.accompagnement], ['Dessert', m.dessert], ['Remarques', m.remarques]]
                .filter(([, v]) => v)
                .forEach(([l, v]) => {
                    html += '<tr><td style="width:120px;font-weight:bold;padding:4px 8px;vertical-align:top">' + l + '</td><td style="padding:4px 8px">' + escapeHtml(v) + '</td></tr>';
                });
            html += '</table>';
        } else {
            html += '<p style="color:#999;font-style:italic">Pas de menu</p>';
        }
    });

    openPrintWindow('Menu du jour — ' + fmtDateFr(new Date()), html);
}

function printWeek() {
    let html = '<h2>Menus de la semaine</h2>';
    const sun = new Date(menuMonday);
    sun.setDate(sun.getDate() + 6);
    html += '<p style="color:#666">' + fmtDateFr(menuMonday) + ' au ' + fmtDateFr(sun) + '</p>';

    for (let i = 0; i < 7; i++) {
        const d = new Date(menuMonday);
        d.setDate(d.getDate() + i);
        const dateStr = fmtDate(d);

        html += '<h3 style="margin-top:1.2rem;border-bottom:2px solid #333;padding-bottom:4px">'
            + DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth() + 1) + '</h3>';

        ['midi', 'soir'].forEach(repas => {
            const m = menusCache[dateStr + '_' + repas];
            html += '<div style="margin-left:12px"><strong style="text-transform:uppercase;font-size:11px;color:#666">' + repas + '</strong>';
            if (m) {
                const parts = [m.entree, '<strong>' + escapeHtml(m.plat) + '</strong>', m.salade, m.accompagnement, m.dessert].filter(Boolean);
                html += '<div style="font-size:13px">' + parts.map(p => p.startsWith('<') ? p : escapeHtml(p)).join(' · ') + '</div>';
                if (m.remarques) html += '<div style="font-size:11px;color:#888;font-style:italic">' + escapeHtml(m.remarques) + '</div>';
            } else {
                html += '<div style="font-size:13px;color:#999;font-style:italic">Pas de menu</div>';
            }
            html += '</div>';
        });
    }

    openPrintWindow('Menus semaine — ' + fmtDateFr(menuMonday), html);
}

function printCommandes() {
    const table = document.getElementById('chCmdTable');
    if (!table) { toast('Rien à imprimer', 'error'); return; }
    const repas = document.getElementById('chRepas')?.value || 'midi';
    openPrintWindow('Commandes ' + todayStr() + ' (' + repas + ')',
        '<h2>Commandes du ' + fmtDateFr(new Date()) + ' (' + repas + ')</h2>' + table.outerHTML);
}

function openPrintWindow(title, body) {
    const win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>' + escapeHtml(title) + '</title>'
        + '<style>body{font-family:Arial,sans-serif;padding:24px;max-width:800px;margin:0 auto}'
        + 'h2{margin:0 0 8px;font-size:18px}h3{font-size:14px;margin:0 0 4px}'
        + 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;font-size:12px}'
        + 'th{background:#f0f0f0}.badge{padding:2px 6px;border-radius:3px;font-size:10px}'
        + '.bg-primary{background:#0d6efd;color:#fff}.bg-success{background:#198754;color:#fff}'
        + '@media print{.no-print{display:none}}</style></head>'
        + '<body>' + body
        + '<div class="no-print" style="margin-top:20px;text-align:center">'
        + '<button onclick="window.print()" style="padding:8px 24px;font-size:14px;cursor:pointer">Imprimer</button></div>'
        + '</body></html>');
    win.document.close();
}

// ═══════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════

function updateWeekLabel() {
    const el = document.getElementById('chMenuWeekLabel');
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

function shiftWeek(m, days) { const d = new Date(m); d.setDate(d.getDate() + days); return d; }

function fmtDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fmtDateFr(d) {
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}

function todayStr() { return fmtDate(new Date()); }
