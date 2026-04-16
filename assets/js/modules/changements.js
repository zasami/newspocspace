/**
 * Changements d'horaire — module SPA
 * Layout: calendrier + demandes en haut, slidedown collègue en bas, modal confirmation
 * Support échange croisé multi-jours
 */
import { apiPost, toast, escapeHtml, formatDate } from '../helpers.js';
import { loadPage } from '../app.js';

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
    // Cas 3: jour OFF compensation
    isOffSwap: false,
    dateCompensation: null,
    compensationAssign: null,
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
    // Changements list is SSR-rendered — delegated click handler in bindEvents() covers it

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
    // Keep dateDestinataire and colAssignOnDate so user can reopen modal
    if (state.collegueId) {
        renderCalendar('chgColCal', state.colMonth, state.colPlanning, onColDayClick, state.dateDestinataire);
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

async function onMyDayClick(date, assign) {
    if (!assign) return;
    state.dateDemandeur = date;
    state.myAssignOnDate = assign;
    state.collegueId = null;
    state.collegue = null;
    state.dateDestinataire = null;
    state.colAssignOnDate = null;
    state.isOffSwap = false;
    state.dateCompensation = null;
    state.compensationAssign = null;

    renderCalendar('chgMyCal', state.myMonth, state.myPlanning, onMyDayClick, state.dateDemandeur);

    // Load colleagues with their shift on selected day
    const res = await apiPost('get_collegues', { date });
    state.myFonctionId = res.my_fonction_id || null;
    const all = res.data || [];

    // Filter: same function + colleague must have shift on this day (if user is OFF)
    const myHasShift = assign && assign.horaire_type_id;
    state.collegues = all.filter(c => {
        // Same function
        if (state.myFonctionId && c.fonction_id !== state.myFonctionId) return false;
        // If I'm OFF: colleague must work on this day
        if (!myHasShift) {
            return c.shift_on_date && c.shift_on_date.horaire_type_id;
        }
        // If I work: show all (they can swap same day or different day)
        return true;
    });

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
    state.isOffSwap = false;
    state.dateCompensation = null;
    state.compensationAssign = null;
    el('chgCompensationPanel')?.remove();
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
    const container = el('chgColList');
    if (!container) return;

    // If a colleague is already selected, hide list
    if (state.collegueId) {
        container.innerHTML = '';
        return;
    }

    // Filter by search query if typed
    const list = state.collegues.filter(c => {
        if (!q) return true;
        return `${c.prenom} ${c.nom} ${c.fonction_nom || ''} ${c.module_nom || ''}`.toLowerCase().includes(q);
    });

    if (!list.length) {
        container.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem">Aucun collègue disponible pour cet échange</div>';
        return;
    }

    container.innerHTML = list.map(c => {
        const initials = ((c.prenom || '').charAt(0) + (c.nom || '').charAt(0)).toUpperCase();
        const avatar = c.photo
            ? `<img src="${escapeHtml(c.photo)}" alt="" class="chg-col-avatar">`
            : `<div class="chg-col-avatar-initials">${initials}</div>`;

        // Show colleague's shift on selected day
        let shiftBadge = '';
        if (c.shift_on_date && c.shift_on_date.horaire_type_id) {
            shiftBadge = `<span class="chg-dual-badge" style="background:${escapeHtml(c.shift_on_date.couleur || '#6c757d')};font-size:.7rem;padding:1px 6px">${escapeHtml(c.shift_on_date.horaire_code)}</span>`;
        } else {
            shiftBadge = '<span style="font-size:.72rem;color:#999">OFF</span>';
        }

        return `<div class="chg-col-item" data-col-id="${escapeHtml(c.id)}">
            ${avatar}
            <div class="chg-col-info">
                <div class="chg-col-name">${escapeHtml(c.prenom)} ${escapeHtml(c.nom)}</div>
                <div class="chg-col-meta">
                    ${c.fonction_code ? `<span class="chg-col-fonction">${escapeHtml(c.fonction_code)}</span>` : ''}
                    ${shiftBadge}
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

    // Clear list and show selected name in search
    const searchInput = el('chgColSearch');
    if (searchInput) searchInput.value = `${c.prenom} ${c.nom}`;
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

    // Button to change colleague
    const changeBtn = document.createElement('button');
    changeBtn.className = 'btn btn-sm btn-outline-secondary ms-auto';
    changeBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
    changeBtn.title = 'Changer de collègue';
    changeBtn.addEventListener('click', () => {
        state.collegueId = null;
        state.collegue = null;
        state.dateDestinataire = null;
        state.colAssignOnDate = null;
        state.isOffSwap = false;
        state.dateCompensation = null;
        state.compensationAssign = null;
        el('chgCompensationPanel')?.remove();
        el('chgColSearch').value = '';
        el('chgSlidePlaceholder')?.classList.remove('chg-hidden');
        el('chgColPanel')?.classList.add('chg-hidden');
        el('chgColSearch')?.focus();
        renderColleagueList();
    });
    headerEl.appendChild(changeBtn);

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

    // Cas 3: X est en jour OFF → demander jour de compensation
    const myHasShift = state.myAssignOnDate && state.myAssignOnDate.horaire_type_id;
    const colHasShift = assign && assign.horaire_type_id;

    if (!myHasShift && colHasShift) {
        // X est OFF, Y travaille → besoin de compensation
        state.isOffSwap = true;
        state.dateCompensation = null;
        state.compensationAssign = null;
        showCompensationPicker();
    } else {
        state.isOffSwap = false;
        state.dateCompensation = null;
        state.compensationAssign = null;
        openConfirmModal();
    }
}

/* ═══ Compensation picker (cas 3: jour OFF) ═══ */
function showCompensationPicker() {
    const colPanel = el('chgColPanel');
    if (!colPanel) return;

    // Remove existing compensation panel
    el('chgCompensationPanel')?.remove();

    const panel = document.createElement('div');
    panel.id = 'chgCompensationPanel';
    panel.className = 'chg-compensation-panel';
    panel.innerHTML = `
        <div class="chg-comp-header">
            <i class="bi bi-info-circle chg-comp-icon-inline"></i>
            <div>
                <div class="chg-comp-title">Jour de compensation</div>
                <div class="chg-comp-desc">Vous êtes en repos — choisissez un jour de travail à céder à ${escapeHtml(state.collegue.prenom)} en échange.</div>
            </div>
        </div>
        <div id="chgCompCal" class="chg-comp-cal"></div>
    `;
    colPanel.appendChild(panel);

    renderCompensationCalendar();

    setTimeout(() => {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function renderCompensationCalendar() {
    const container = el('chgCompCal');
    if (!container) return;
    container.innerHTML = '';

    const today = new Date().toISOString().slice(0, 10);
    const [year, month] = state.myMonth.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();

    const byDate = {};
    state.myPlanning.forEach(a => { byDate[a.date_jour] = a; });
    const colByDate = {};
    state.colPlanning.forEach(a => { colByDate[a.date_jour] = a; });

    // Only show days where: X works AND Y is OFF
    for (let d = 1; d <= lastDay; d++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        if (dateStr <= today) continue;
        if (dateStr === state.dateDemandeur) continue;
        if (dateStr === state.dateDestinataire) continue;

        const assign = byDate[dateStr];
        if (!assign || !assign.horaire_type_id) continue; // X must be working

        const colAssign = colByDate[dateStr];
        if (colAssign && colAssign.horaire_type_id) continue; // Y must be OFF (no shift)

        const item = document.createElement('div');
        item.className = 'chg-comp-day';
        if (dateStr === state.dateCompensation) item.classList.add('chg-comp-day-selected');

        const dt = new Date(dateStr + 'T00:00:00');
        const dayName = JOURS[dt.getDay() === 0 ? 6 : dt.getDay() - 1];

        const badge = document.createElement('span');
        badge.className = 'chg-dual-badge';
        badge.style.background = assign.couleur || '#6c757d';
        badge.textContent = assign.horaire_code;

        item.innerHTML = `<span class="chg-comp-day-name">${dayName} ${d}</span>`;
        item.appendChild(badge);
        if (assign.module_code) {
            const mod = document.createElement('span');
            mod.className = 'chg-comp-day-module';
            mod.textContent = assign.module_code;
            item.appendChild(mod);
        }

        item.addEventListener('click', () => {
            state.dateCompensation = dateStr;
            state.compensationAssign = assign;
            renderCompensationCalendar();
            // Open confirm modal after short delay
            setTimeout(() => openConfirmModal(), 200);
        });

        container.appendChild(item);
    }

    if (!container.children.length) {
        container.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.85rem">Aucun jour compatible ce mois (vous devez travailler et ' + escapeHtml(state.collegue.prenom) + ' doit être en repos)</div>';
    }
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

    // ═══ Tableau résumé clair ═══
    const summaryTable = document.createElement('div');
    summaryTable.className = 'chg-summary-grid';

    // Header
    summaryTable.innerHTML = `<div class="chg-summary-row chg-summary-header">
        <span class="chg-summary-date">Date</span>
        <span class="chg-summary-person"><i class="bi bi-person-fill"></i> Vous</span>
        <span class="chg-summary-person"><i class="bi bi-person"></i> ${escapeHtml(c.prenom)}</span>
    </div>`;

    if (state.isOffSwap && state.dateCompensation) {
        // Cas 3 : ligne 1 = jour où vous prenez le shift
        summaryTable.innerHTML += `<div class="chg-summary-row">
            <span class="chg-summary-date">${formatDateFr(state.dateDestinataire)}</span>
            <span class="chg-summary-person chg-summary-gain">${buildBadgeInline(b)} <small>travaille</small></span>
            <span class="chg-summary-person chg-summary-lose"><span class="chg-swap-off">Repos</span></span>
        </div>`;
        // Cas 3 : ligne 2 = jour de compensation
        summaryTable.innerHTML += `<div class="chg-summary-row">
            <span class="chg-summary-date">${formatDateFr(state.dateCompensation)}</span>
            <span class="chg-summary-person chg-summary-lose"><span class="chg-swap-off">Repos</span></span>
            <span class="chg-summary-person chg-summary-gain">${buildBadgeInline(state.compensationAssign)} <small>travaille</small></span>
        </div>`;
    } else {
        // Cas 1 & 2
        summaryTable.innerHTML += `<div class="chg-summary-row">
            <span class="chg-summary-date">${formatDateFr(state.dateDemandeur)}</span>
            <span class="chg-summary-person chg-summary-gain">${buildBadgeInline(b)}</span>
            <span class="chg-summary-person chg-summary-lose">${buildBadgeInline(a)}</span>
        </div>`;
        summaryTable.innerHTML += `<div class="chg-summary-row">
            <span class="chg-summary-date">${formatDateFr(state.dateDestinataire)}</span>
            <span class="chg-summary-person chg-summary-lose">${buildBadgeInline(a)}</span>
            <span class="chg-summary-person chg-summary-gain">${buildBadgeInline(b)}</span>
        </div>`;
    }

    block.appendChild(summaryTable);

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

    const dualHeader = document.createElement('div');
    dualHeader.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:8px';

    const dualTitle = document.createElement('h4');
    dualTitle.className = 'chg-dual-title';
    dualTitle.style.margin = '0';
    dualTitle.innerHTML = '<i class="bi bi-layout-split"></i> Comparaison';
    dualHeader.appendChild(dualTitle);

    const infoBtn = document.createElement('button');
    infoBtn.type = 'button';
    infoBtn.className = 'chg-legend-btn';
    infoBtn.innerHTML = '<i class="bi bi-info-circle"></i>';
    infoBtn.title = 'Légende';
    infoBtn.addEventListener('click', () => {
        const legend = document.getElementById('chgLegendPanel');
        if (legend) legend.classList.toggle('open');
    });
    dualHeader.appendChild(infoBtn);
    dualSection.appendChild(dualHeader);

    const legendPanel = document.createElement('div');
    legendPanel.id = 'chgLegendPanel';
    legendPanel.className = 'chg-legend-panel';
    legendPanel.innerHTML = `
        <div class="chg-legend-item">
            <span class="chg-legend-color" style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.2)"></span>
            <span><i class="bi bi-arrow-up-right" style="color:#c0392b"></i> Jour que vous <strong>cédez</strong> — le badge montre ce que vous aurez à la place</span>
        </div>
        <div class="chg-legend-item">
            <span class="chg-legend-color" style="background:rgba(39,174,96,.1);border:1px solid rgba(39,174,96,.2)"></span>
            <span><i class="bi bi-arrow-down-left" style="color:#27ae60"></i> Jour que vous <strong>recevez</strong> — le badge montre le nouvel horaire</span>
        </div>
        <div class="chg-legend-item">
            <span class="chg-legend-off">OFF</span>
            <span>Jour de repos (pas d'horaire assigné)</span>
        </div>
    `;
    dualSection.appendChild(legendPanel);

    const dualNav = document.createElement('div');
    dualNav.className = 'chg-dual-nav';
    dualNav.style.justifyContent = 'flex-end';

    const prevBtn = document.createElement('button');
    prevBtn.className = 'btn btn-sm btn-outline-secondary';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
    prevBtn.addEventListener('click', () => { state.dualOffset -= 7; renderDualGridModal(); });

    const nextBtn = document.createElement('button');
    nextBtn.className = 'btn btn-sm btn-outline-secondary';
    nextBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
    nextBtn.addEventListener('click', () => { state.dualOffset += 7; renderDualGridModal(); });

    dualNav.appendChild(prevBtn);
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
    setupDragScroll();
    scrollToSwapDay();
}

function scrollToSwapDay() {
    const modalEl = document.getElementById('chgConfirmModal');
    if (!modalEl) return;

    function doScroll() {
        const wrap = modalEl.querySelector('.chg-dual-grid-wrap');
        const swapCell = wrap?.querySelector('.chg-dual-cell-give, .chg-dual-cell-take');
        if (wrap && swapCell) {
            const offset = swapCell.offsetLeft - wrap.offsetLeft - 140;
            wrap.scrollLeft = Math.max(0, offset);
        }
    }

    // Try immediately (if modal already visible, e.g. after nav click)
    setTimeout(doScroll, 100);
    // Also listen for modal shown event
    modalEl.addEventListener('shown.bs.modal', doScroll, { once: true });
}

function setupDragScroll() {
    document.querySelectorAll('.chg-dual-grid-wrap').forEach(wrap => {
        if (wrap._dragScrollInit) return;
        wrap._dragScrollInit = true;
        let isDown = false, startX, scrollLeft;
        wrap.addEventListener('mousedown', (e) => {
            if (e.target.closest('button, a, input')) return;
            isDown = true;
            wrap.classList.add('chg-grabbing');
            startX = e.pageX - wrap.offsetLeft;
            scrollLeft = wrap.scrollLeft;
        });
        wrap.addEventListener('mouseleave', () => { isDown = false; wrap.classList.remove('chg-grabbing'); });
        wrap.addEventListener('mouseup', () => { isDown = false; wrap.classList.remove('chg-grabbing'); });
        wrap.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - wrap.offsetLeft;
            wrap.scrollLeft = scrollLeft - (x - startX);
        });
    });
}

function buildDualGridHtml(tableId, rangeId) {
    if (!state.dateDemandeur || !state.dateDestinataire) return;

    const dateDem = new Date(state.dateDemandeur + 'T00:00:00');
    const dateDest = new Date(state.dateDestinataire + 'T00:00:00');
    let earliest = dateDem < dateDest ? dateDem : dateDest;
    if (state.dateCompensation) {
        const dateComp = new Date(state.dateCompensation + 'T00:00:00');
        if (dateComp < earliest) earliest = dateComp;
    }

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
    buildDualRowDOM(myRow, days, myByDate, colByDate, state.dateDemandeur, state.dateDestinataire, state.dateCompensation, 'me');
    tbody.appendChild(myRow);

    // Col row
    const colRow = document.createElement('tr');
    const colTd = document.createElement('td');
    colTd.className = 'chg-dual-user-col';
    colTd.innerHTML = `<i class="bi bi-person"></i> ${escapeHtml(c.prenom)}`;
    colRow.appendChild(colTd);
    buildDualRowDOM(colRow, days, colByDate, myByDate, state.dateDestinataire, state.dateDemandeur, state.dateCompensation, 'col');
    tbody.appendChild(colRow);

    tableEl.appendChild(tbody);
}

function buildDualRowDOM(row, days, byDate, otherByDate, giveDate, takeDate, compDate, who) {
    days.forEach(d => {
        const a = byDate[d];
        const isGive = d === giveDate;
        const isTake = d === takeDate;
        // Compensation: for "me" I give my shift, for "col" they receive it
        const isComp = compDate && d === compDate;
        const td = document.createElement('td');
        td.className = 'chg-dual-day-col';

        if (isComp) {
            if (who === 'me') {
                // I give my shift on comp day → OFF
                td.classList.add('chg-dual-cell-give');
                const wrap = document.createElement('div');
                wrap.className = 'chg-swap-result chg-swap-lose';
                wrap.innerHTML = '<div class="chg-swap-label"><i class="bi bi-arrow-up-right"></i></div>';
                const off = document.createElement('span');
                off.className = 'chg-swap-off';
                off.textContent = 'OFF';
                wrap.appendChild(off);
                td.appendChild(wrap);
            } else {
                // Colleague receives my shift on comp day
                td.classList.add('chg-dual-cell-take');
                const myShift = otherByDate[compDate];
                const wrap = document.createElement('div');
                wrap.className = 'chg-swap-result chg-swap-gain';
                wrap.innerHTML = '<div class="chg-swap-label"><i class="bi bi-arrow-down-left"></i></div>';
                if (myShift && myShift.horaire_type_id) {
                    wrap.appendChild(cellBadgeDOM(myShift));
                } else {
                    const off = document.createElement('span');
                    off.className = 'chg-swap-off';
                    off.textContent = 'OFF';
                    wrap.appendChild(off);
                }
                td.appendChild(wrap);
            }
        } else if (isGive) {
            // Ce jour : on CÈDE notre horaire → on passe en repos
            td.classList.add('chg-dual-cell-give');
            const wrap = document.createElement('div');
            wrap.className = 'chg-swap-result chg-swap-lose';
            wrap.innerHTML = '<div class="chg-swap-label"><i class="bi bi-arrow-up-right"></i></div>';
            const off = document.createElement('span');
            off.className = 'chg-swap-off';
            off.textContent = 'OFF';
            wrap.appendChild(off);
            td.appendChild(wrap);
        } else if (isTake) {
            // Ce jour : on REÇOIT le shift de l'autre
            td.classList.add('chg-dual-cell-take');
            const newAssign = otherByDate[takeDate];
            const wrap = document.createElement('div');
            wrap.className = 'chg-swap-result chg-swap-gain';
            wrap.innerHTML = '<div class="chg-swap-label"><i class="bi bi-arrow-down-left"></i></div>';
            if (newAssign && newAssign.horaire_type_id) {
                wrap.appendChild(cellBadgeDOM(newAssign));
            } else {
                const off = document.createElement('span');
                off.className = 'chg-swap-off';
                off.textContent = 'OFF';
                wrap.appendChild(off);
            }
            td.appendChild(wrap);
        } else {
            td.appendChild(cellBadgeDOM(a));
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

function buildBadgeInline(a) {
    if (a && a.horaire_type_id) {
        return `<span class="chg-badge-inline" style="background:${escapeHtml(a.couleur || '#6c757d')}">${escapeHtml(a.horaire_code)}</span>`;
    }
    return '<span class="chg-swap-off">OFF</span>';
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

    const payload = {
        date_demandeur: state.dateDemandeur,
        date_destinataire: state.dateDestinataire,
        destinataire_id: state.collegueId,
        motif: el('chgMotif')?.value || ''
    };
    // Cas 3: double échange avec compensation
    if (state.isOffSwap && state.dateCompensation) {
        payload.date_compensation = state.dateCompensation;
    }
    const res = await apiPost('submit_changement', payload);

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
            else cell.classList.add('chg-cal-clickable');
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
            } else {
                // Jour off (pas d'assignation)
                const off = document.createElement('div');
                off.className = 'chg-cal-repos';
                off.textContent = '—';
                cell.appendChild(off);
            }

            const clickable = !isPast;
            if (clickable) {
                cell.addEventListener('click', () => onDayClick(dateStr, byDate[dateStr] || { date_jour: dateStr, horaire_type_id: null, assign_statut: 'repos' }));
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
    // Reload full page for fresh SSR
    loadPage('changements');
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
