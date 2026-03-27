/**
 * Changements d'horaire — module SPA
 * Layout: calendrier + demandes en haut, slidedown collègue en bas, modal confirmation
 * Support échange croisé multi-jours
 */
import { apiPost, toast, escapeHtml, formatDate } from '../helpers.js';

const JOURS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const MOIS_NOMS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

let state = {
    myMonth: null,
    myPlanning: [],
    dateDemandeur: null,
    myAssignOnDate: null,
    myFonctionId: null,
    collegues: [],
    collegueId: null,
    collegue: null,
    colMonth: null,
    colPlanning: [],
    dateDestinataire: null,
    colAssignOnDate: null,
    dualOffset: 0,
    refusId: null,
};

export async function init() {
    const now = new Date();
    state.myMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    state.colMonth = state.myMonth;

    bindEvents();
    await Promise.all([loadMyPlanning(), loadColleagues(), loadChangements()]);
    // Show hint after calendar loaded
    el('chgMyHint')?.classList.remove('chg-hidden');
}

export function destroy() {
    state = {
        myMonth: null, myPlanning: [], dateDemandeur: null, myAssignOnDate: null,
        myFonctionId: null, collegues: [], collegueId: null, collegue: null,
        colMonth: null, colPlanning: [], dateDestinataire: null, colAssignOnDate: null,
        dualOffset: 0, refusId: null
    };
}

/* ═══ Events ═══ */
function bindEvents() {
    el('chgMyPrev')?.addEventListener('click', () => { state.myMonth = shiftMonth(state.myMonth, -1); loadMyPlanning(); });
    el('chgMyNext')?.addEventListener('click', () => { state.myMonth = shiftMonth(state.myMonth, 1); loadMyPlanning(); });
    el('chgColPrev')?.addEventListener('click', () => { state.colMonth = shiftMonth(state.colMonth, -1); loadColPlanning(); });
    el('chgColNext')?.addEventListener('click', () => { state.colMonth = shiftMonth(state.colMonth, 1); loadColPlanning(); });
    el('chgSlideClose')?.addEventListener('click', closeSlidedown);
    el('chgColSearch')?.addEventListener('input', renderColleagueList);
    el('chgSubmitBtn')?.addEventListener('click', submitChangement);
    el('refusConfirmBtn')?.addEventListener('click', confirmRefus);
    document.querySelectorAll('[data-close-confirm]').forEach(b => b.addEventListener('click', closeConfirmModal));
    document.querySelectorAll('[data-close-refus]').forEach(b => b.addEventListener('click', closeRefusModal));
    el('changementsList')?.addEventListener('click', onListClick);
}

/* ═══ My planning ═══ */
async function loadMyPlanning() {
    const monthEl = el('chgMyMonth');
    if (monthEl) monthEl.textContent = formatMonth(state.myMonth);
    el('chgMyCal').innerHTML = '<div class="text-center text-muted py-3"><span class="spinner"></span></div>';

    const res = await apiPost('get_mon_planning_mois', { mois: state.myMonth });
    state.myPlanning = res.assignations || [];
    renderCalendar('chgMyCal', state.myMonth, state.myPlanning, onMyDayClick, state.dateDemandeur);
}

function onMyDayClick(date, assign) {
    if (!assign) return;
    state.dateDemandeur = date;
    state.myAssignOnDate = assign;
    // Reset colleague selection
    state.collegueId = null;
    state.collegue = null;
    state.dateDestinataire = null;
    state.colAssignOnDate = null;

    renderCalendar('chgMyCal', state.myMonth, state.myPlanning, onMyDayClick, state.dateDemandeur);
    openSlidedown();
}

/* ═══ Slidedown ═══ */
function openSlidedown() {
    const slide = el('chgSlidedown');
    if (!slide) return;

    // Update header
    el('chgSlideDate').textContent = formatDateFr(state.dateDemandeur);
    const a = state.myAssignOnDate;
    if (a && a.horaire_type_id) {
        const bg = escapeHtml(a.couleur || '#6c757d');
        el('chgSlideBadge').innerHTML = `<span class="chg-badge-inline" style="background:${bg}">${escapeHtml(a.horaire_code)}</span>`;
    } else {
        el('chgSlideBadge').innerHTML = '<span class="chg-badge-inline" style="background:#999">Repos</span>';
    }

    // Reset right panel
    el('chgSlidePlaceholder')?.classList.remove('chg-hidden');
    el('chgColPanel')?.classList.add('chg-hidden');
    el('chgColSearch').value = '';
    renderColleagueList();

    // Open with animation
    slide.classList.remove('chg-slide-closed');

    // Scroll to slidedown
    setTimeout(() => {
        slide.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

function closeSlidedown() {
    const slide = el('chgSlidedown');
    if (!slide) return;
    slide.classList.add('chg-slide-closed');
    state.dateDemandeur = null;
    state.myAssignOnDate = null;
    state.collegueId = null;
    state.collegue = null;
    state.dateDestinataire = null;
    state.colAssignOnDate = null;
    renderCalendar('chgMyCal', state.myMonth, state.myPlanning, onMyDayClick, null);
}

/* ═══ Colleagues ═══ */
async function loadColleagues() {
    const res = await apiPost('get_collegues');
    state.myFonctionId = res.my_fonction_id || null;
    const all = res.data || [];
    state.collegues = state.myFonctionId
        ? all.filter(c => c.fonction_id === state.myFonctionId)
        : all;
}

function renderColleagueList() {
    const q = (el('chgColSearch')?.value || '').toLowerCase().trim();
    const list = state.collegues.filter(c => {
        if (!q) return true;
        return `${c.prenom} ${c.nom} ${c.fonction_nom || ''} ${c.module_nom || ''}`.toLowerCase().includes(q);
    });

    const container = el('chgColList');
    if (!container) return;

    if (!list.length) {
        container.innerHTML = '<div class="text-muted text-center py-3">Aucun collègue trouvé</div>';
        return;
    }

    container.innerHTML = list.map(c => {
        const initials = ((c.prenom || '').charAt(0) + (c.nom || '').charAt(0)).toUpperCase();
        const avatar = c.photo
            ? `<img src="${escapeHtml(c.photo)}" alt="" class="chg-col-avatar">`
            : `<div class="chg-col-avatar-initials">${initials}</div>`;
        const taux = c.taux ? `<span class="chg-col-taux">${Math.round(c.taux)}%</span>` : '';
        const mod = c.module_code ? `<span class="chg-col-module">${escapeHtml(c.module_code)}</span>` : '';
        const active = state.collegueId === c.id ? ' chg-col-active' : '';

        return `<div class="chg-col-item${active}" data-col-id="${escapeHtml(c.id)}">
            ${avatar}
            <div class="chg-col-info">
                <div class="chg-col-name">${escapeHtml(c.prenom)} ${escapeHtml(c.nom)}</div>
                <div class="chg-col-meta">
                    ${c.fonction_code ? `<span class="chg-col-fonction">${escapeHtml(c.fonction_code)}</span>` : ''}
                    ${mod}
                    ${taux}
                </div>
            </div>
        </div>`;
    }).join('');

    container.querySelectorAll('[data-col-id]').forEach(item => {
        item.addEventListener('click', () => selectColleague(item.dataset.colId));
    });
}

function selectColleague(id) {
    const c = state.collegues.find(x => x.id === id);
    if (!c) return;
    state.collegueId = id;
    state.collegue = c;
    state.dateDestinataire = null;
    state.colAssignOnDate = null;
    state.colMonth = state.myMonth;

    // Highlight active colleague
    renderColleagueList();

    // Show colleague panel
    el('chgSlidePlaceholder')?.classList.add('chg-hidden');
    el('chgColPanel')?.classList.remove('chg-hidden');

    const initials = ((c.prenom || '').charAt(0) + (c.nom || '').charAt(0)).toUpperCase();
    const avatar = c.photo
        ? `<img src="${escapeHtml(c.photo)}" alt="" class="chg-col-avatar">`
        : `<div class="chg-col-avatar-initials">${initials}</div>`;

    el('chgColPanelHeader').innerHTML = `
        ${avatar}
        <div class="chg-col-info">
            <div class="chg-col-name">${escapeHtml(c.prenom)} ${escapeHtml(c.nom)}</div>
            <div class="chg-col-meta">
                ${c.fonction_code ? `<span class="chg-col-fonction">${escapeHtml(c.fonction_code)}</span>` : ''}
                ${c.module_code ? `<span class="chg-col-module">${escapeHtml(c.module_code)}</span>` : ''}
                ${c.taux ? `<span class="chg-col-taux">${Math.round(c.taux)}%</span>` : ''}
            </div>
        </div>`;

    loadColPlanning();
}

/* ═══ Colleague planning ═══ */
async function loadColPlanning() {
    el('chgColMonth').textContent = formatMonth(state.colMonth);
    el('chgColCal').innerHTML = '<div class="text-center text-muted py-3"><span class="spinner"></span></div>';

    const res = await apiPost('get_collegue_planning_mois', { collegue_id: state.collegueId, mois: state.colMonth });
    state.colPlanning = res.assignations || [];
    renderCalendar('chgColCal', state.colMonth, state.colPlanning, onColDayClick, state.dateDestinataire);
}

function onColDayClick(date, assign) {
    if (!assign) return;
    state.dateDestinataire = date;
    state.colAssignOnDate = assign;
    renderCalendar('chgColCal', state.colMonth, state.colPlanning, onColDayClick, state.dateDestinataire);
    openConfirmModal();
}

/* ═══ Confirmation modal ═══ */
function openConfirmModal() {
    const a = state.myAssignOnDate;
    const b = state.colAssignOnDate;
    const c = state.collegue;

    const body = el('chgConfirmBody');
    body.innerHTML = `
        <div class="chg-confirm-block">
            <div class="chg-confirm-row">
                <div class="chg-confirm-side chg-confirm-give">
                    <div class="chg-confirm-label"><i class="bi bi-box-arrow-up-right"></i> Vous cédez</div>
                    <div class="chg-confirm-date">${escapeHtml(formatDateFr(state.dateDemandeur))}</div>
                    ${a ? buildBadge(a.horaire_code, a.couleur, a.module_nom) : '<span class="chg-badge-inline" style="background:#999">Repos</span>'}
                </div>
                <div class="chg-confirm-arrow"><i class="bi bi-arrow-left-right"></i></div>
                <div class="chg-confirm-side chg-confirm-take">
                    <div class="chg-confirm-label"><i class="bi bi-box-arrow-in-down-left"></i> Vous prenez</div>
                    <div class="chg-confirm-date">${escapeHtml(formatDateFr(state.dateDestinataire))}</div>
                    ${b ? buildBadge(b.horaire_code, b.couleur, b.module_nom) : '<span class="chg-badge-inline" style="background:#999">Repos</span>'}
                </div>
            </div>
            <div class="chg-confirm-with">
                <i class="bi bi-person"></i> Échange avec <strong>${escapeHtml(c.prenom)} ${escapeHtml(c.nom)}</strong>
            </div>
        </div>

        <div class="chg-dual-section">
            <h4 class="chg-dual-title"><i class="bi bi-layout-split"></i> Comparaison</h4>
            <div class="chg-dual-nav">
                <button class="btn btn-sm btn-outline-secondary" id="chgDualPrevM"><i class="bi bi-chevron-left"></i></button>
                <span class="chg-dual-range" id="chgDualRangeM"></span>
                <button class="btn btn-sm btn-outline-secondary" id="chgDualNextM"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="chg-dual-grid-wrap">
                <table class="chg-dual-table" id="chgDualTableM"></table>
            </div>
        </div>

        <div class="form-group mt-3">
            <label class="form-label">Motif (optionnel)</label>
            <textarea class="form-control" id="chgMotif" rows="2" placeholder="Raison de l'échange..." maxlength="500"></textarea>
        </div>`;

    el('chgDualPrevM')?.addEventListener('click', () => { state.dualOffset -= 7; renderDualGridModal(); });
    el('chgDualNextM')?.addEventListener('click', () => { state.dualOffset += 7; renderDualGridModal(); });
    state.dualOffset = 0;
    renderDualGridModal();

    el('chgConfirmModal')?.classList.remove('chg-hidden');
}

function closeConfirmModal() {
    el('chgConfirmModal')?.classList.add('chg-hidden');
    // Reset destination selection
    state.dateDestinataire = null;
    state.colAssignOnDate = null;
    if (state.collegueId) {
        renderCalendar('chgColCal', state.colMonth, state.colPlanning, onColDayClick, null);
    }
}

/* ═══ Dual planning grid ═══ */
function renderDualGridModal() {
    buildDualGridHtml('chgDualTableM', 'chgDualRangeM');
}

function buildDualGridHtml(tableId, rangeId) {
    if (!state.dateDemandeur || !state.dateDestinataire) return;

    const dateDem = new Date(state.dateDemandeur + 'T00:00:00');
    const dateDest = new Date(state.dateDestinataire + 'T00:00:00');
    const earliest = dateDem < dateDest ? dateDem : dateDest;

    const start = new Date(earliest);
    const dow = start.getDay() || 7;
    start.setDate(start.getDate() - (dow - 1) + state.dualOffset);

    const days = [];
    for (let i = 0; i < 14; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d.toISOString().slice(0, 10));
    }

    const rangeEl = el(rangeId);
    if (rangeEl) rangeEl.textContent = `${formatDateShort(days[0])} — ${formatDateShort(days[days.length - 1])}`;

    const myByDate = {};
    state.myPlanning.forEach(a => { myByDate[a.date_jour] = a; });
    const colByDate = {};
    state.colPlanning.forEach(a => { colByDate[a.date_jour] = a; });

    const c = state.collegue;

    let html = '<thead><tr><th class="chg-dual-user-col"></th>';
    days.forEach(d => {
        const dt = new Date(d + 'T00:00:00');
        const isSwapDem = d === state.dateDemandeur;
        const isSwapDest = d === state.dateDestinataire;
        let cls = 'chg-dual-day-col';
        if (isSwapDem) cls += ' chg-dual-swap-dem';
        if (isSwapDest) cls += ' chg-dual-swap-dest';
        const dayName = JOURS[dt.getDay() === 0 ? 6 : dt.getDay() - 1];
        html += `<th class="${cls}"><div>${dayName}</div><small>${dt.getDate()}</small></th>`;
    });
    html += '</tr></thead><tbody>';

    html += '<tr><td class="chg-dual-user-col"><i class="bi bi-person-fill"></i> Vous</td>';
    html += buildDualRow(days, myByDate, colByDate, state.dateDemandeur, state.dateDestinataire);
    html += '</tr>';

    html += `<tr><td class="chg-dual-user-col"><i class="bi bi-person"></i> ${escapeHtml(c.prenom)}</td>`;
    html += buildDualRow(days, colByDate, myByDate, state.dateDestinataire, state.dateDemandeur);
    html += '</tr></tbody>';

    const tableEl = el(tableId);
    if (tableEl) tableEl.innerHTML = html;
}

function buildDualRow(days, byDate, otherByDate, giveDate, takeDate) {
    let html = '';
    days.forEach(d => {
        const a = byDate[d];
        const isGive = d === giveDate;
        const isTake = d === takeDate;
        let cls = 'chg-dual-day-col';
        if (isGive) cls += ' chg-dual-cell-give';
        if (isTake) cls += ' chg-dual-cell-take';

        html += `<td class="${cls}">`;
        html += cellBadge(a);

        if (isGive) {
            html += '<div class="chg-sim-out">';
            html += '<i class="bi bi-arrow-up-right chg-sim-arrow-out"></i>';
            html += `<span class="chg-sim-dashed chg-sim-dashed-out">${cellBadgeRaw(a)}</span>`;
            html += '</div>';
        }
        if (isTake) {
            const other = otherByDate[giveDate];
            html += '<div class="chg-sim-in">';
            html += '<i class="bi bi-arrow-down-left chg-sim-arrow-in"></i>';
            html += `<span class="chg-sim-dashed chg-sim-dashed-in">${cellBadgeRaw(other)}</span>`;
            html += '</div>';
        }

        html += '</td>';
    });
    return html;
}

function cellBadge(a) {
    if (a && a.horaire_type_id) {
        const bg = escapeHtml(a.couleur || '#6c757d');
        return `<span class="chg-dual-badge" style="background:${bg}">${escapeHtml(a.horaire_code)}</span>`;
    }
    if (a && (a.assign_statut === 'repos' || !a.horaire_type_id)) return '<span class="chg-dual-repos">R</span>';
    return '<span class="chg-dual-empty">—</span>';
}

function cellBadgeRaw(a) {
    if (a && a.horaire_type_id) {
        const bg = escapeHtml(a.couleur || '#6c757d');
        return `<span class="chg-dual-badge" style="background:${bg}">${escapeHtml(a.horaire_code)}</span>`;
    }
    return '<span class="chg-dual-repos">R</span>';
}

/* ═══ Submit ═══ */
async function submitChangement() {
    const btn = el('chgSubmitBtn');
    btn.disabled = true;

    const res = await apiPost('submit_changement', {
        date_demandeur: state.dateDemandeur,
        date_destinataire: state.dateDestinataire,
        destinataire_id: state.collegueId,
        motif: el('chgMotif')?.value || ''
    });

    btn.disabled = false;
    if (res.success) {
        toast('Demande envoyée');
        closeConfirmModal();
        closeSlidedown();
        await loadChangements();
    } else {
        toast(res.message || 'Erreur');
    }
}

/* ═══ Calendar renderer ═══ */
function renderCalendar(containerId, mois, assignations, onDayClick, selectedDate) {
    const container = el(containerId);
    if (!container) return;

    const [year, month] = mois.split('-').map(Number);
    const lastDay = new Date(year, month, 0);
    const today = new Date().toISOString().slice(0, 10);

    const byDate = {};
    assignations.forEach(a => { byDate[a.date_jour] = a; });

    let startDow = new Date(year, month - 1, 1).getDay();
    if (startDow === 0) startDow = 7;
    const startOffset = startDow - 1;

    let html = '<div class="chg-cal-header">';
    JOURS.forEach(j => { html += `<div class="chg-cal-hcell">${j}</div>`; });
    html += '</div><div class="chg-cal-body">';

    const totalDays = lastDay.getDate();
    const totalCells = Math.ceil((totalDays + startOffset) / 7) * 7;

    for (let i = 0; i < totalCells; i++) {
        if (i % 7 === 0) html += '<div class="chg-cal-week">';

        const dayNum = i - startOffset + 1;
        if (dayNum < 1 || dayNum > totalDays) {
            html += '<div class="chg-cal-cell chg-cal-empty"></div>';
        } else {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
            const assign = byDate[dateStr];
            const hasShift = assign && assign.horaire_type_id;
            const hasAssign = !!assign;
            const isSelected = dateStr === selectedDate;
            const isPast = dateStr < today;
            const isWeekend = (i % 7 === 5) || (i % 7 === 6);

            let cls = 'chg-cal-cell';
            if (hasShift) cls += ' chg-cal-has-shift';
            else if (hasAssign) cls += ' chg-cal-clickable';
            if (isSelected) cls += ' chg-cal-selected';
            if (isPast) cls += ' chg-cal-past';
            if (isWeekend) cls += ' chg-cal-weekend';
            if (dateStr === today) cls += ' chg-cal-today';

            const clickable = !isPast && hasAssign;

            html += `<div class="${cls}" ${clickable ? `data-cal-date="${dateStr}"` : ''}>`;
            html += `<div class="chg-cal-day">${dayNum}</div>`;

            if (assign) {
                if (hasShift) {
                    const bg = escapeHtml(assign.couleur || '#6c757d');
                    html += `<div class="chg-cal-badge" style="background:${bg}">${escapeHtml(assign.horaire_code)}</div>`;
                    if (assign.module_code) {
                        html += `<div class="chg-cal-module">${escapeHtml(assign.module_code)}</div>`;
                    }
                } else if (assign.assign_statut === 'repos') {
                    html += '<div class="chg-cal-repos">R</div>';
                } else if (assign.assign_statut === 'absence') {
                    html += '<div class="chg-cal-absence">A</div>';
                }
            }

            html += '</div>';
        }

        if (i % 7 === 6) html += '</div>';
    }

    html += '</div>';
    container.innerHTML = html;

    container.querySelectorAll('[data-cal-date]').forEach(cell => {
        cell.addEventListener('click', () => {
            const d = cell.dataset.calDate;
            onDayClick(d, byDate[d]);
        });
    });
}

/* ═══ Changements list ═══ */
async function loadChangements() {
    const container = el('changementsList');
    if (!container) return;

    const res = await apiPost('get_mes_changements');
    const items = res.changements || [];

    const countEl = el('chgListCount');
    if (countEl) countEl.textContent = items.length;

    if (!items.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">Aucune demande</div>';
        return;
    }

    const userId = window.__TR__?.user?.id;

    container.innerHTML = items.map(ch => {
        const iAmDemandeur = ch.demandeur_id === userId;
        const iAmDestinataire = ch.destinataire_id === userId;

        const demandeurName = `${escapeHtml(ch.demandeur_prenom)} ${escapeHtml(ch.demandeur_nom)}`;
        const destinataireName = `${escapeHtml(ch.destinataire_prenom)} ${escapeHtml(ch.destinataire_nom)}`;

        const horDem = buildBadgeHtml(ch.horaire_demandeur_code, ch.horaire_demandeur_couleur, ch.module_demandeur_nom);
        const horDest = buildBadgeHtml(ch.horaire_destinataire_code, ch.horaire_destinataire_couleur, ch.module_destinataire_nom);

        let statutHtml = '';
        switch (ch.statut) {
            case 'en_attente_collegue': statutHtml = '<span class="badge badge-pending">En attente</span>'; break;
            case 'confirme_collegue': statutHtml = '<span class="badge badge-info">Attente admin</span>'; break;
            case 'valide': statutHtml = '<span class="badge badge-success">Validé</span>'; break;
            case 'refuse': statutHtml = `<span class="badge badge-refused">Refusé</span>`; break;
        }

        let actionsHtml = '';
        if (ch.statut === 'en_attente_collegue' && iAmDestinataire) {
            actionsHtml = `<div class="d-flex gap-1 mt-2">
                <button class="btn btn-success btn-sm" data-confirm="${escapeHtml(ch.id)}"><i class="bi bi-check-lg"></i> Accepter</button>
                <button class="btn btn-danger btn-sm" data-refuse="${escapeHtml(ch.id)}"><i class="bi bi-x-lg"></i> Refuser</button>
            </div>`;
        }
        if (ch.statut === 'en_attente_collegue' && iAmDemandeur) {
            actionsHtml = `<div class="d-flex gap-1 mt-2">
                <button class="btn btn-light btn-sm" data-annuler="${escapeHtml(ch.id)}"><i class="bi bi-trash"></i> Annuler</button>
            </div>`;
        }

        const refusInfo = ch.raison_refus ? `<div class="chg-info refus"><i class="bi bi-chat-left-text"></i><span>${escapeHtml(ch.raison_refus)}</span></div>` : '';
        const motifInfo = ch.motif ? `<div class="chg-info"><i class="bi bi-chat-dots"></i><span>${escapeHtml(ch.motif)}</span></div>` : '';

        const roleTag = iAmDemandeur
            ? '<span class="chg-role-tag demand">Demandé</span>'
            : '<span class="chg-role-tag invite">Reçu</span>';

        const dateDem = ch.date_demandeur || ch.date_jour;
        const dateDest = ch.date_destinataire || ch.date_jour;

        return `
        <div class="chg-item">
            <div class="chg-item-header">
                <div class="chg-item-date">
                    <i class="bi bi-calendar3"></i>
                    ${escapeHtml(formatDate(dateDem))} <i class="bi bi-arrow-left-right chg-date-arrow"></i> ${escapeHtml(formatDate(dateDest))}
                </div>
                ${roleTag}
                ${statutHtml}
            </div>
            <div class="chg-exchange">
                <div class="chg-person">
                    <div class="chg-person-name">${demandeurName}${iAmDemandeur ? ' <span class="chg-person-you">(vous)</span>' : ''}</div>
                    <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> ${horDem}</div>
                </div>
                <div class="chg-arrow"><i class="bi bi-arrow-left-right"></i></div>
                <div class="chg-person">
                    <div class="chg-person-name">${destinataireName}${iAmDestinataire ? ' <span class="chg-person-you">(vous)</span>' : ''}</div>
                    <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> ${horDest}</div>
                </div>
            </div>
            ${motifInfo}
            ${refusInfo}
            ${actionsHtml}
        </div>`;
    }).join('');
}

function onListClick(e) {
    const confirmBtn = e.target.closest('[data-confirm]');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        apiPost('confirmer_changement', { id: confirmBtn.dataset.confirm }).then(res => {
            if (res.success) { toast(res.message); loadChangements(); }
            else { toast(res.message || 'Erreur'); confirmBtn.disabled = false; }
        });
        return;
    }

    const refuseBtn = e.target.closest('[data-refuse]');
    if (refuseBtn) {
        state.refusId = refuseBtn.dataset.refuse;
        el('refusRaison').value = '';
        el('refusModal').classList.remove('chg-hidden');
        return;
    }

    const annulerBtn = e.target.closest('[data-annuler]');
    if (annulerBtn) {
        if (!confirm('Annuler cette demande ?')) return;
        annulerBtn.disabled = true;
        apiPost('annuler_changement', { id: annulerBtn.dataset.annuler }).then(res => {
            if (res.success) { toast('Demande annulée'); loadChangements(); }
            else { toast(res.message || 'Erreur'); annulerBtn.disabled = false; }
        });
        return;
    }
}

async function confirmRefus() {
    if (!state.refusId) return;
    const raison = el('refusRaison')?.value || '';
    const res = await apiPost('refuser_changement', { id: state.refusId, raison });
    if (res.success) {
        toast('Demande refusée');
        closeRefusModal();
        state.refusId = null;
        await loadChangements();
    } else {
        toast(res.message || 'Erreur');
    }
}

function closeRefusModal() {
    el('refusModal')?.classList.add('chg-hidden');
}

/* ═══ Helpers ═══ */
function el(id) { return document.getElementById(id); }

function shiftMonth(mois, delta) {
    const [y, m] = mois.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function formatMonth(mois) {
    const [y, m] = mois.split('-').map(Number);
    return `${MOIS_NOMS[m - 1]} ${y}`;
}

function formatDateFr(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function formatDateShort(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' });
}

function buildBadge(code, couleur, moduleName) {
    const bg = escapeHtml(couleur || '#6c757d');
    let html = `<span class="chg-badge-inline" style="background:${bg}">${escapeHtml(code || 'Repos')}</span>`;
    if (moduleName) html += ` <small class="text-muted">${escapeHtml(moduleName)}</small>`;
    return html;
}

function buildBadgeHtml(code, couleur, moduleName) {
    const bg = couleur || '#6c757d';
    let html = `<span class="badge" style="background:${escapeHtml(bg)};color:#fff">${escapeHtml(code || '?')}</span>`;
    if (moduleName) html += ` <small class="text-muted">${escapeHtml(moduleName)}</small>`;
    return html;
}
