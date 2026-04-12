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

let confirmModal = null;
let refusModalInstance = null;

export function init() {
    const ssrData = window.__SS_PAGE_DATA__ || {};
    const now = new Date();
    state.myMonth = ssrData.current_mois || `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    state.colMonth = state.myMonth;
    state.myFonctionId = ssrData.my_fonction_id || null;

    // Bootstrap modals
    const cmEl = document.getElementById('chgConfirmModal');
    const rmEl = document.getElementById('refusModal');
    if (cmEl) confirmModal = new bootstrap.Modal(cmEl);
    if (rmEl) refusModalInstance = new bootstrap.Modal(rmEl);

    // Reset destination on confirm modal close
    if (cmEl) cmEl.addEventListener('hidden.bs.modal', onConfirmModalClosed);

    bindEvents();

    // Seed state from SSR data and render immediately
    state.myPlanning = ssrData.my_planning || [];
    const allCollegues = ssrData.collegues || [];
    state.collegues = state.myFonctionId
        ? allCollegues.filter(c => c.fonction_id === state.myFonctionId)
        : allCollegues;

    renderMyPlanningFromState();
    renderColleagueList();
    renderChangements(ssrData.changements || []);

    el('chgMyHint')?.classList.remove('chg-hidden');
}

export function destroy() {
    confirmModal = null;
    refusModalInstance = null;
    const cmEl = document.getElementById('chgConfirmModal');
    if (cmEl) cmEl.removeEventListener('hidden.bs.modal', onConfirmModalClosed);
    state = {
        myMonth: null, myPlanning: [], dateDemandeur: null, myAssignOnDate: null,
        myFonctionId: null, collegues: [], collegueId: null, collegue: null,
        colMonth: null, colPlanning: [], dateDestinataire: null, colAssignOnDate: null,
        dualOffset: 0, refusId: null
    };
}

function onConfirmModalClosed() {
    state.dateDestinataire = null;
    state.colAssignOnDate = null;
    if (state.collegueId) {
        renderCalendar('chgColCal', state.colMonth, state.colPlanning, onColDayClick, null);
    }
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
    el('changementsList')?.addEventListener('click', onListClick);
}

/* ═══ My planning ═══ */
function renderMyPlanningFromState() {
    const monthEl = el('chgMyMonth');
    if (monthEl) monthEl.textContent = formatMonth(state.myMonth);
    renderCalendar('chgMyCal', state.myMonth, state.myPlanning, onMyDayClick, state.dateDemandeur);
}

async function loadMyPlanning() {
    const monthEl = el('chgMyMonth');
    if (monthEl) monthEl.textContent = formatMonth(state.myMonth);
    el('chgMyCal').innerHTML = '<div class="text-center text-muted py-3"><span class="spinner"></span></div>';

    const res = await apiPost('get_mon_planning_mois', { mois: state.myMonth });
    state.myPlanning = res.assignations || [];
    renderMyPlanningFromState();
}

function onMyDayClick(date, assign) {
    if (!assign) return;
    state.dateDemandeur = date;
    state.myAssignOnDate = assign;
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

    el('chgSlideDate').textContent = formatDateFr(state.dateDemandeur);
    const a = state.myAssignOnDate;
    const badgeEl = el('chgSlideBadge');
    if (a && a.horaire_type_id) {
        badgeEl.innerHTML = '';
        const span = document.createElement('span');
        span.className = 'chg-badge-inline';
        span.style.background = a.couleur || '#6c757d';
        span.textContent = a.horaire_code;
        badgeEl.appendChild(span);
    } else {
        badgeEl.innerHTML = '';
        const span = document.createElement('span');
        span.className = 'chg-badge-inline';
        span.style.background = '#999';
        span.textContent = 'Repos';
        badgeEl.appendChild(span);
    }

    el('chgSlidePlaceholder')?.classList.remove('chg-hidden');
    el('chgColPanel')?.classList.add('chg-hidden');
    el('chgColSearch').value = '';
    renderColleagueList();

    slide.classList.remove('chg-slide-closed');

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

    renderColleagueList();

    el('chgSlidePlaceholder')?.classList.add('chg-hidden');
    el('chgColPanel')?.classList.remove('chg-hidden');

    const headerEl = el('chgColPanelHeader');
    headerEl.innerHTML = '';

    const initials = ((c.prenom || '').charAt(0) + (c.nom || '').charAt(0)).toUpperCase();
    if (c.photo) {
        const img = document.createElement('img');
        img.src = c.photo;
        img.alt = '';
        img.className = 'chg-col-avatar';
        headerEl.appendChild(img);
    } else {
        const div = document.createElement('div');
        div.className = 'chg-col-avatar-initials';
        div.textContent = initials;
        headerEl.appendChild(div);
    }

    const infoDiv = document.createElement('div');
    infoDiv.className = 'chg-col-info';

    const nameDiv = document.createElement('div');
    nameDiv.className = 'chg-col-name';
    nameDiv.textContent = `${c.prenom} ${c.nom}`;
    infoDiv.appendChild(nameDiv);

    const metaDiv = document.createElement('div');
    metaDiv.className = 'chg-col-meta';
    if (c.fonction_code) {
        const fSpan = document.createElement('span');
        fSpan.className = 'chg-col-fonction';
        fSpan.textContent = c.fonction_code;
        metaDiv.appendChild(fSpan);
    }
    if (c.module_code) {
        const mSpan = document.createElement('span');
        mSpan.className = 'chg-col-module';
        mSpan.textContent = c.module_code;
        metaDiv.appendChild(mSpan);
    }
    if (c.taux) {
        const tSpan = document.createElement('span');
        tSpan.className = 'chg-col-taux';
        tSpan.textContent = `${Math.round(c.taux)}%`;
        metaDiv.appendChild(tSpan);
    }
    infoDiv.appendChild(metaDiv);
    headerEl.appendChild(infoDiv);

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
    body.innerHTML = '';

    // Confirm block
    const block = document.createElement('div');
    block.className = 'chg-confirm-block';

    // Row: give / arrow / take
    const row = document.createElement('div');
    row.className = 'chg-confirm-row';

    // Give side
    const giveSide = document.createElement('div');
    giveSide.className = 'chg-confirm-side chg-confirm-give';
    const giveLabel = document.createElement('div');
    giveLabel.className = 'chg-confirm-label';
    giveLabel.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Vous cédez';
    const giveDate = document.createElement('div');
    giveDate.className = 'chg-confirm-date';
    giveDate.textContent = formatDateFr(state.dateDemandeur);
    giveSide.appendChild(giveLabel);
    giveSide.appendChild(giveDate);
    giveSide.appendChild(buildBadgeEl(a));
    row.appendChild(giveSide);

    // Arrow
    const arrowDiv = document.createElement('div');
    arrowDiv.className = 'chg-confirm-arrow';
    arrowDiv.innerHTML = '<i class="bi bi-arrow-left-right"></i>';
    row.appendChild(arrowDiv);

    // Take side
    const takeSide = document.createElement('div');
    takeSide.className = 'chg-confirm-side chg-confirm-take';
    const takeLabel = document.createElement('div');
    takeLabel.className = 'chg-confirm-label';
    takeLabel.innerHTML = '<i class="bi bi-box-arrow-in-down-left"></i> Vous prenez';
    const takeDate = document.createElement('div');
    takeDate.className = 'chg-confirm-date';
    takeDate.textContent = formatDateFr(state.dateDestinataire);
    takeSide.appendChild(takeLabel);
    takeSide.appendChild(takeDate);
    takeSide.appendChild(buildBadgeEl(b));
    row.appendChild(takeSide);

    block.appendChild(row);

    // "Échange avec" line
    const withDiv = document.createElement('div');
    withDiv.className = 'chg-confirm-with';
    const withIcon = document.createElement('i');
    withIcon.className = 'bi bi-person';
    withDiv.appendChild(withIcon);
    withDiv.append(' Échange avec ');
    const withStrong = document.createElement('strong');
    withStrong.textContent = `${c.prenom} ${c.nom}`;
    withDiv.appendChild(withStrong);
    block.appendChild(withDiv);
    body.appendChild(block);

    // Dual grid section
    const dualSection = document.createElement('div');
    dualSection.className = 'chg-dual-section';

    const dualTitle = document.createElement('h4');
    dualTitle.className = 'chg-dual-title';
    dualTitle.innerHTML = '<i class="bi bi-layout-split"></i> Comparaison';
    dualSection.appendChild(dualTitle);

    const dualNav = document.createElement('div');
    dualNav.className = 'chg-dual-nav';

    const prevBtn = document.createElement('button');
    prevBtn.className = 'btn btn-sm btn-outline-secondary';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
    prevBtn.addEventListener('click', () => { state.dualOffset -= 7; renderDualGridModal(); });

    const rangeSpan = document.createElement('span');
    rangeSpan.className = 'chg-dual-range';
    rangeSpan.id = 'chgDualRangeM';

    const nextBtn = document.createElement('button');
    nextBtn.className = 'btn btn-sm btn-outline-secondary';
    nextBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
    nextBtn.addEventListener('click', () => { state.dualOffset += 7; renderDualGridModal(); });

    dualNav.appendChild(prevBtn);
    dualNav.appendChild(rangeSpan);
    dualNav.appendChild(nextBtn);
    dualSection.appendChild(dualNav);

    const gridWrap = document.createElement('div');
    gridWrap.className = 'chg-dual-grid-wrap';
    const table = document.createElement('table');
    table.className = 'chg-dual-table';
    table.id = 'chgDualTableM';
    gridWrap.appendChild(table);
    dualSection.appendChild(gridWrap);
    body.appendChild(dualSection);

    // Motif
    const motifGroup = document.createElement('div');
    motifGroup.className = 'form-group mt-3';
    const motifLabel = document.createElement('label');
    motifLabel.className = 'form-label';
    motifLabel.textContent = 'Motif (optionnel)';
    const motifArea = document.createElement('textarea');
    motifArea.className = 'form-control';
    motifArea.id = 'chgMotif';
    motifArea.rows = 2;
    motifArea.placeholder = "Raison de l'échange...";
    motifArea.maxLength = 500;
    motifGroup.appendChild(motifLabel);
    motifGroup.appendChild(motifArea);
    body.appendChild(motifGroup);

    state.dualOffset = 0;
    renderDualGridModal();

    if (confirmModal) confirmModal.show();
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
    const tableEl = el(tableId);
    if (!tableEl) return;

    // Build with DOM
    tableEl.innerHTML = '';

    // Thead
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    const thEmpty = document.createElement('th');
    thEmpty.className = 'chg-dual-user-col';
    headRow.appendChild(thEmpty);

    days.forEach(d => {
        const dt = new Date(d + 'T00:00:00');
        const isSwapDem = d === state.dateDemandeur;
        const isSwapDest = d === state.dateDestinataire;
        const th = document.createElement('th');
        th.className = 'chg-dual-day-col';
        if (isSwapDem) th.classList.add('chg-dual-swap-dem');
        if (isSwapDest) th.classList.add('chg-dual-swap-dest');
        const dayName = JOURS[dt.getDay() === 0 ? 6 : dt.getDay() - 1];
        const dayDiv = document.createElement('div');
        dayDiv.textContent = dayName;
        const small = document.createElement('small');
        small.textContent = dt.getDate();
        th.appendChild(dayDiv);
        th.appendChild(small);
        headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    tableEl.appendChild(thead);

    // Tbody
    const tbody = document.createElement('tbody');

    // My row
    const myRow = document.createElement('tr');
    const myTd = document.createElement('td');
    myTd.className = 'chg-dual-user-col';
    myTd.innerHTML = '<i class="bi bi-person-fill"></i> Vous';
    myRow.appendChild(myTd);
    buildDualRowDOM(myRow, days, myByDate, colByDate, state.dateDemandeur, state.dateDestinataire);
    tbody.appendChild(myRow);

    // Col row
    const colRow = document.createElement('tr');
    const colTd = document.createElement('td');
    colTd.className = 'chg-dual-user-col';
    colTd.innerHTML = `<i class="bi bi-person"></i> ${escapeHtml(c.prenom)}`;
    colRow.appendChild(colTd);
    buildDualRowDOM(colRow, days, colByDate, myByDate, state.dateDestinataire, state.dateDemandeur);
    tbody.appendChild(colRow);

    tableEl.appendChild(tbody);
}

function buildDualRowDOM(row, days, byDate, otherByDate, giveDate, takeDate) {
    days.forEach(d => {
        const a = byDate[d];
        const isGive = d === giveDate;
        const isTake = d === takeDate;
        const td = document.createElement('td');
        td.className = 'chg-dual-day-col';
        if (isGive) td.classList.add('chg-dual-cell-give');
        if (isTake) td.classList.add('chg-dual-cell-take');

        td.appendChild(cellBadgeDOM(a));

        if (isGive) {
            const sim = document.createElement('div');
            sim.className = 'chg-sim-out';
            const arrow = document.createElement('i');
            arrow.className = 'bi bi-arrow-up-right chg-sim-arrow-out';
            sim.appendChild(arrow);
            const dashed = document.createElement('span');
            dashed.className = 'chg-sim-dashed chg-sim-dashed-out';
            dashed.appendChild(cellBadgeDOM(a));
            sim.appendChild(dashed);
            td.appendChild(sim);
        }
        if (isTake) {
            const other = otherByDate[giveDate];
            const sim = document.createElement('div');
            sim.className = 'chg-sim-in';
            const arrow = document.createElement('i');
            arrow.className = 'bi bi-arrow-down-left chg-sim-arrow-in';
            sim.appendChild(arrow);
            const dashed = document.createElement('span');
            dashed.className = 'chg-sim-dashed chg-sim-dashed-in';
            dashed.appendChild(cellBadgeDOM(other));
            sim.appendChild(dashed);
            td.appendChild(sim);
        }

        row.appendChild(td);
    });
}

function cellBadgeDOM(a) {
    if (a && a.horaire_type_id) {
        const span = document.createElement('span');
        span.className = 'chg-dual-badge';
        span.style.background = a.couleur || '#6c757d';
        span.textContent = a.horaire_code;
        return span;
    }
    if (a && (a.assign_statut === 'repos' || !a.horaire_type_id)) {
        const span = document.createElement('span');
        span.className = 'chg-dual-repos';
        span.textContent = 'R';
        return span;
    }
    const span = document.createElement('span');
    span.className = 'chg-dual-empty';
    span.textContent = '—';
    return span;
}

function buildBadgeEl(a) {
    const wrap = document.createElement('div');
    if (a && a.horaire_type_id) {
        const span = document.createElement('span');
        span.className = 'chg-badge-inline';
        span.style.background = a.couleur || '#6c757d';
        span.textContent = a.horaire_code;
        wrap.appendChild(span);
        if (a.module_nom) {
            const small = document.createElement('small');
            small.className = 'text-muted ms-1';
            small.textContent = a.module_nom;
            wrap.appendChild(small);
        }
    } else {
        const span = document.createElement('span');
        span.className = 'chg-badge-inline';
        span.style.background = '#999';
        span.textContent = 'Repos';
        wrap.appendChild(span);
    }
    return wrap;
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
        if (confirmModal) confirmModal.hide();
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

    const frag = document.createDocumentFragment();

    // Header
    const header = document.createElement('div');
    header.className = 'chg-cal-header';
    JOURS.forEach(j => {
        const hcell = document.createElement('div');
        hcell.className = 'chg-cal-hcell';
        hcell.textContent = j;
        header.appendChild(hcell);
    });
    frag.appendChild(header);

    // Body
    const bodyDiv = document.createElement('div');
    bodyDiv.className = 'chg-cal-body';

    const totalDays = lastDay.getDate();
    const totalCells = Math.ceil((totalDays + startOffset) / 7) * 7;
    let weekDiv = null;

    for (let i = 0; i < totalCells; i++) {
        if (i % 7 === 0) {
            weekDiv = document.createElement('div');
            weekDiv.className = 'chg-cal-week';
        }

        const dayNum = i - startOffset + 1;
        if (dayNum < 1 || dayNum > totalDays) {
            const empty = document.createElement('div');
            empty.className = 'chg-cal-cell chg-cal-empty';
            weekDiv.appendChild(empty);
        } else {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
            const assign = byDate[dateStr];
            const hasShift = assign && assign.horaire_type_id;
            const hasAssign = !!assign;
            const isSelected = dateStr === selectedDate;
            const isPast = dateStr < today;
            const isWeekend = (i % 7 === 5) || (i % 7 === 6);

            const cell = document.createElement('div');
            cell.className = 'chg-cal-cell';
            if (hasShift) cell.classList.add('chg-cal-has-shift');
            else if (hasAssign) cell.classList.add('chg-cal-clickable');
            if (isSelected) cell.classList.add('chg-cal-selected');
            if (isPast) cell.classList.add('chg-cal-past');
            if (isWeekend) cell.classList.add('chg-cal-weekend');
            if (dateStr === today) cell.classList.add('chg-cal-today');

            const dayDiv = document.createElement('div');
            dayDiv.className = 'chg-cal-day';
            dayDiv.textContent = dayNum;
            cell.appendChild(dayDiv);

            if (assign) {
                if (hasShift) {
                    const badge = document.createElement('div');
                    badge.className = 'chg-cal-badge';
                    badge.style.background = assign.couleur || '#6c757d';
                    badge.textContent = assign.horaire_code;
                    cell.appendChild(badge);
                    if (assign.module_code) {
                        const modDiv = document.createElement('div');
                        modDiv.className = 'chg-cal-module';
                        modDiv.textContent = assign.module_code;
                        cell.appendChild(modDiv);
                    }
                } else if (assign.assign_statut === 'repos') {
                    const r = document.createElement('div');
                    r.className = 'chg-cal-repos';
                    r.textContent = 'R';
                    cell.appendChild(r);
                } else if (assign.assign_statut === 'absence') {
                    const ab = document.createElement('div');
                    ab.className = 'chg-cal-absence';
                    ab.textContent = 'A';
                    cell.appendChild(ab);
                }
            }

            const clickable = !isPast && hasAssign;
            if (clickable) {
                cell.addEventListener('click', () => onDayClick(dateStr, byDate[dateStr]));
            }

            weekDiv.appendChild(cell);
        }

        if (i % 7 === 6) bodyDiv.appendChild(weekDiv);
    }

    frag.appendChild(bodyDiv);
    container.innerHTML = '';
    container.appendChild(frag);
}

/* ═══ Changements list ═══ */
async function loadChangements() {
    const res = await apiPost('get_mes_changements');
    renderChangements(res.changements || []);
}

function renderChangements(items) {
    const container = el('changementsList');
    if (!container) return;

    const countEl = el('chgListCount');
    if (countEl) countEl.textContent = items.length;

    if (!items.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">Aucune demande</div>';
        return;
    }

    const userId = window.__SS__?.user?.id;

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
            case 'refuse': statutHtml = '<span class="badge badge-refused">Refusé</span>'; break;
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

        const createdDate = ch.created_at ? formatDateTime(ch.created_at) : '';
        const updatedDate = ch.updated_at ? formatDateTime(ch.updated_at) : '';
        const refusInfo = ch.raison_refus ? `<div class="chg-info refus"><i class="bi bi-chat-left-text"></i><span>${escapeHtml(ch.raison_refus)}</span>${updatedDate ? `<span class="chg-info-date">${updatedDate}</span>` : ''}</div>` : '';
        const motifInfo = ch.motif ? `<div class="chg-info"><i class="bi bi-chat-dots"></i><span>${escapeHtml(ch.motif)}</span>${createdDate ? `<span class="chg-info-date">${createdDate}</span>` : ''}</div>` : '';

        const roleTag = iAmDemandeur
            ? '<span class="chg-role-tag demand">Demandé</span>'
            : '<span class="chg-role-tag invite">Reçu</span>';

        const dateDem = ch.date_demandeur || ch.date_jour;
        const dateDest = ch.date_destinataire || ch.date_jour;

        const hasDetails = ch.motif || ch.raison_refus;

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
            <div class="chg-exchange" ${hasDetails ? 'style="position:relative"' : ''}>
                <div class="chg-person">
                    <div class="chg-person-name">${demandeurName}${iAmDemandeur ? ' <span class="chg-person-you">(vous)</span>' : ''}</div>
                    <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> ${horDem}</div>
                </div>
                <div class="chg-arrow"><i class="bi bi-arrow-left-right"></i></div>
                <div class="chg-person">
                    <div class="chg-person-name">${destinataireName}${iAmDestinataire ? ' <span class="chg-person-you">(vous)</span>' : ''}</div>
                    <div class="chg-person-shift"><span class="chg-person-shift-label">Cède :</span> ${horDest}</div>
                </div>
                ${hasDetails ? `<button class="chg-details-toggle" data-toggle-details="${escapeHtml(ch.id)}" title="Voir les détails"><i class="bi bi-plus-lg"></i></button>` : ''}
            </div>
            ${actionsHtml}
            ${hasDetails ? `<div class="chg-details" id="chgDetails-${escapeHtml(ch.id)}">${motifInfo}${refusInfo}</div>` : ''}
        </div>`;
    }).join('');
}

function onListClick(e) {
    const toggleBtn = e.target.closest('[data-toggle-details]');
    if (toggleBtn) {
        const id = toggleBtn.dataset.toggleDetails;
        const details = document.getElementById('chgDetails-' + id);
        if (details) {
            const isOpen = details.classList.toggle('open');
            toggleBtn.classList.toggle('open', isOpen);
            toggleBtn.innerHTML = isOpen ? '<i class="bi bi-dash-lg"></i>' : '<i class="bi bi-plus-lg"></i>';
        }
        return;
    }

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
        if (refusModalInstance) refusModalInstance.show();
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
        if (refusModalInstance) refusModalInstance.hide();
        state.refusId = null;
        await loadChangements();
    } else {
        toast(res.message || 'Erreur');
    }
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

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T'));
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' }) + ' à ' + d.toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
}

function buildBadgeHtml(code, couleur, moduleName) {
    const bg = couleur || '#6c757d';
    let html = `<span class="badge" style="background:${escapeHtml(bg)};color:#fff">${escapeHtml(code || '?')}</span>`;
    if (moduleName) html += ` <small class="text-muted">${escapeHtml(moduleName)}</small>`;
    return html;
}
