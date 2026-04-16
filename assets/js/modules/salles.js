/**
 * SpocSpace — Salles module (employee SPA)
 * Weekly timeline + reservation form
 */
import { apiPost, escapeHtml } from '../helpers.js';

const HOURS_START = 7;
const HOURS_END = 20;
const HOUR_H = 48;

let salles = [];
let reservations = [];
let userId = '';
let currentMonday = null;
let filterSalle = '';
let resaModal = null;
let detailModal = null;
let currentDetailResa = null;

export async function init() {
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr) {
        salles = ssr.salles || [];
        reservations = ssr.reservations || [];
        userId = ssr.userId || '';
        currentMonday = ssr.monday ? new Date(ssr.monday + 'T00:00:00') : getMonday(new Date());
    } else {
        currentMonday = getMonday(new Date());
    }

    // Modals
    const resaEl = document.getElementById('slResaModal');
    const detailEl = document.getElementById('slDetailModal');
    if (resaEl) resaModal = new bootstrap.Modal(resaEl);
    if (detailEl) detailModal = new bootstrap.Modal(detailEl);

    // Nav
    document.getElementById('slPrev')?.addEventListener('click', () => { currentMonday.setDate(currentMonday.getDate() - 7); loadWeek(); });
    document.getElementById('slNext')?.addEventListener('click', () => { currentMonday.setDate(currentMonday.getDate() + 7); loadWeek(); });
    document.getElementById('slToday')?.addEventListener('click', () => { currentMonday = getMonday(new Date()); loadWeek(); });

    // Filter
    document.getElementById('slSalleFilter')?.addEventListener('change', (e) => { filterSalle = e.target.value; renderGrid(); });

    // Journée entière toggle
    const slJourneeCheck = document.getElementById('slResaJournee');
    if (slJourneeCheck) {
        slJourneeCheck.addEventListener('change', () => {
            const hide = slJourneeCheck.checked;
            document.getElementById('slResaDebutWrap').style.display = hide ? 'none' : '';
            document.getElementById('slResaFinWrap').style.display = hide ? 'none' : '';
        });
    }

    // Check existing reservations on date/salle/journée change
    document.getElementById('slResaSalle')?.addEventListener('change', checkExistingReservations);
    document.getElementById('slResaDate')?.addEventListener('change', checkExistingReservations);
    document.getElementById('slResaDebut')?.addEventListener('change', checkExistingReservations);
    document.getElementById('slResaFin')?.addEventListener('change', checkExistingReservations);
    if (slJourneeCheck) slJourneeCheck.addEventListener('change', checkExistingReservations);

    // Alert collapse toggle
    document.getElementById('slResaAlertHeader')?.addEventListener('click', () => {
        const body = document.getElementById('slResaAlertBody');
        const chev = document.getElementById('slResaAlertChevron');
        const isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
        chev.style.transform = isOpen ? '' : 'rotate(180deg)';
    });

    // New reservation
    document.getElementById('slNewBtn')?.addEventListener('click', () => openResaModal());
    document.getElementById('slResaSaveBtn')?.addEventListener('click', saveResa);

    // Cancel from detail
    document.getElementById('slDetailCancelBtn')?.addEventListener('click', cancelFromDetail);

    // Cancel from list
    document.getElementById('slMyResas')?.addEventListener('click', handleCancelResa);

    // Render
    updateWeekLabel();
    renderGrid();
}

export function destroy() {
    resaModal = null;
    detailModal = null;
}

function getMonday(d) {
    const dt = new Date(d);
    const day = dt.getDay() || 7;
    dt.setDate(dt.getDate() - day + 1);
    dt.setHours(0, 0, 0, 0);
    return dt;
}

function fmtDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fmtDateFr(d) {
    const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    const mois = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jul', 'aoû', 'sep', 'oct', 'nov', 'déc'];
    return jours[d.getDay()] + ' ' + d.getDate() + ' ' + mois[d.getMonth()];
}

function getWeekDates() {
    const dates = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(currentMonday);
        d.setDate(d.getDate() + i);
        dates.push(d);
    }
    return dates;
}

function updateWeekLabel() {
    const dates = getWeekDates();
    const moisFr = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    const d0 = dates[0], d6 = dates[6];
    let label = d0.getDate() + ' ' + moisFr[d0.getMonth()];
    if (d0.getMonth() !== d6.getMonth()) label += ' — ' + d6.getDate() + ' ' + moisFr[d6.getMonth()];
    else label += ' — ' + d6.getDate();
    label += ' ' + d6.getFullYear();
    const el = document.getElementById('slWeekLabel');
    if (el) el.textContent = label;
}

async function loadWeek() {
    updateWeekLabel();
    const dates = getWeekDates();
    const res = await apiPost('get_salles_disponibilites', {
        date_debut: fmtDate(dates[0]),
        date_fin: fmtDate(dates[6])
    });
    if (res.success) {
        reservations = res.reservations || [];
        if (res.salles) salles = res.salles;
        renderGrid();
    }
}

function renderGrid() {
    const dates = getWeekDates();
    const today = fmtDate(new Date());
    const totalHours = HOURS_END - HOURS_START;
    const cols = dates.length;

    const grid = document.getElementById('slGrid');
    if (!grid) return;

    grid.style.gridTemplateColumns = '50px repeat(' + cols + ', 1fr)';
    grid.style.gridTemplateRows = 'auto repeat(' + totalHours + ', ' + HOUR_H + 'px)';

    let html = '<div style="padding:8px 4px;font-weight:700;font-size:.7rem;text-align:center;background:var(--cl-bg,#F7F5F2);border-bottom:1.5px solid var(--cl-border-light,#F0EDE8);position:sticky;top:0;z-index:10"></div>';
    dates.forEach(d => {
        const isToday = fmtDate(d) === today;
        html += '<div style="padding:8px 4px;font-weight:700;font-size:.72rem;text-align:center;background:' + (isToday ? '#e8f0ed' : 'var(--cl-bg,#F7F5F2)') + ';border-bottom:1.5px solid var(--cl-border-light,#F0EDE8);position:sticky;top:0;z-index:10;' + (isToday ? 'color:#2d4a43' : '') + '">' + fmtDateFr(d) + '</div>';
    });

    for (let h = HOURS_START; h < HOURS_END; h++) {
        html += '<div style="grid-row:' + (h - HOURS_START + 2) + ';grid-column:1;height:' + HOUR_H + 'px;padding:2px 6px 0;font-size:.68rem;color:var(--cl-text-muted,#999);text-align:right;border-right:1.5px solid var(--cl-border-light,#F0EDE8);background:var(--cl-surface,#fff)">' + String(h).padStart(2, '0') + ':00</div>';
        dates.forEach((d, di) => {
            const isToday = fmtDate(d) === today;
            html += '<div class="sl-cell" style="grid-row:' + (h - HOURS_START + 2) + ';grid-column:' + (di + 2) + ';height:' + HOUR_H + 'px;border-right:1px solid var(--cl-border-light,#F0EDE8);border-bottom:1px solid var(--cl-border-light,#F0EDE8);cursor:pointer;position:relative;overflow:visible;' + (isToday ? 'background:rgba(45,74,67,.04)' : '') + '" data-date="' + fmtDate(d) + '" data-hour="' + h + '"></div>';
        });
    }

    grid.innerHTML = html;

    // Place reservation blocks
    const dayResaMap = {};
    reservations.forEach(r => {
        if (filterSalle && r.salle_id !== filterSalle) return;
        if (!dayResaMap[r.date_jour]) dayResaMap[r.date_jour] = [];
        dayResaMap[r.date_jour].push(r);
    });

    dates.forEach((d, di) => {
        const dateStr = fmtDate(d);
        const resas = dayResaMap[dateStr] || [];
        if (!resas.length) return;

        // Calculate positions for overlap detection
        const blocks = resas.map(r => {
            const isJE = parseInt(r.journee_entiere);
            const [hd, md] = r.heure_debut.split(':').map(Number);
            const [hf, mf] = r.heure_fin.split(':').map(Number);
            const startMin = isJE ? 0 : Math.max((hd - HOURS_START) * 60 + md, 0);
            const endMin = isJE ? (HOURS_END - HOURS_START) * 60 : Math.min((hf - HOURS_START) * 60 + mf, (HOURS_END - HOURS_START) * 60);
            return { r, isJE, startMin, endMin };
        });

        // Assign columns for overlapping blocks
        blocks.forEach(b => { b.col = 0; b.totalCols = 1; });
        for (let i = 0; i < blocks.length; i++) {
            const overlaps = [blocks[i]];
            for (let j = 0; j < blocks.length; j++) {
                if (i === j) continue;
                if (blocks[j].startMin < blocks[i].endMin && blocks[j].endMin > blocks[i].startMin) {
                    overlaps.push(blocks[j]);
                }
            }
            if (overlaps.length > 1) {
                overlaps.sort((a, b) => (a.r.salle_id || '').localeCompare(b.r.salle_id || '') || a.r.titre.localeCompare(b.r.titre));
                overlaps.forEach((ob, idx) => {
                    ob.col = idx;
                    ob.totalCols = Math.max(ob.totalCols, overlaps.length);
                });
            }
        }

        const firstCell = grid.querySelector('.sl-cell[data-date="' + dateStr + '"][data-hour="' + HOURS_START + '"]');
        if (!firstCell) return;

        blocks.forEach(({ r, isJE, startMin, endMin, col, totalCols }) => {
            const salle = salles.find(s => s.id === r.salle_id);
            const color = salle ? salle.couleur : '#888';
            const isMine = r.user_id === userId;
            const topPx = startMin * HOUR_H / 60;
            const heightPx = Math.max((endMin - startMin) * HOUR_H / 60, 16);

            const widthPct = 100 / totalCols;
            const leftPct = col * widthPct;

            const block = document.createElement('div');
            const hatchStyle = isJE ? 'background-image:repeating-linear-gradient(135deg,transparent,transparent 4px,rgba(255,255,255,.18) 4px,rgba(255,255,255,.18) 8px);border:2px solid rgba(255,255,255,.35);' : '';
            block.style.cssText = 'position:absolute;top:' + topPx + 'px;height:' + heightPx + 'px;left:' + leftPct + '%;width:calc(' + widthPct + '% - 4px);background:' + color + ';border-radius:5px;padding:3px 6px;font-size:.68rem;color:#fff;overflow:hidden;cursor:pointer;z-index:3;box-shadow:0 1px 3px rgba(0,0,0,.12);line-height:1.3;transition:transform .1s;' + hatchStyle;
            const timeLabel = isJE ? 'Journée entière' : r.heure_debut.substring(0, 5) + '–' + r.heure_fin.substring(0, 5);
            const salleObj = salles.find(s => s.id === r.salle_id);
            const showSalle = !filterSalle && salles.length > 1;
            const salleTag = showSalle ? '<div style="font-size:.55rem;opacity:.85;background:rgba(255,255,255,.2);display:inline-block;padding:0 4px;border-radius:3px;margin-bottom:1px">' + escapeHtml(salleObj?.nom || '') + '</div>' : '';
            block.innerHTML = salleTag
                + '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(r.titre) + '</div>'
                + (heightPx > 28 ? '<div style="opacity:.8;font-size:.6rem">' + timeLabel + '</div>' : '')
                + (heightPx > 42 ? '<div style="opacity:.7;font-size:.6rem">' + escapeHtml(r.prenom + ' ' + r.user_nom) + (isMine ? ' (moi)' : '') + '</div>' : '');

            block.addEventListener('click', (e) => { e.stopPropagation(); showDetail(r); });
            block.addEventListener('mouseenter', () => { block.style.transform = 'scale(1.02)'; });
            block.addEventListener('mouseleave', () => { block.style.transform = ''; });
            firstCell.appendChild(block);
        });
    });

    // Click empty cell → new reservation
    grid.querySelectorAll('.sl-cell').forEach(cell => {
        cell.addEventListener('click', (e) => {
            if (e.target !== cell) return;
            const date = cell.dataset.date;
            const hour = parseInt(cell.dataset.hour);
            openResaModal(date, String(hour).padStart(2, '0') + ':00', String(hour + 1).padStart(2, '0') + ':00');
        });
    });
}

function openResaModal(date, debut, fin) {
    if (!resaModal) return;
    document.getElementById('slResaSalle').value = filterSalle || (salles[0]?.id || '');
    document.getElementById('slResaTitre').value = '';
    document.getElementById('slResaDesc').value = '';
    document.getElementById('slResaDate').value = date || fmtDate(new Date());
    const jCheck = document.getElementById('slResaJournee');
    if (jCheck) {
        jCheck.checked = false;
        document.getElementById('slResaDebutWrap').style.display = '';
        document.getElementById('slResaFinWrap').style.display = '';
    }
    document.getElementById('slResaDebut').value = debut || '08:00';
    document.getElementById('slResaFin').value = fin || '09:00';
    resaModal.show();
    checkExistingReservations();
}

function checkExistingReservations() {
    const salleId = document.getElementById('slResaSalle')?.value;
    const dateVal = document.getElementById('slResaDate')?.value;
    const alertWrap = document.getElementById('slResaAlertWrap');
    const alertList = document.getElementById('slResaAlertList');
    const alertTitle = document.getElementById('slResaAlertTitle');
    const alertBody = document.getElementById('slResaAlertBody');

    if (!alertWrap || !salleId || !dateVal) { if (alertWrap) alertWrap.style.display = 'none'; return; }

    const isJournee = document.getElementById('slResaJournee')?.checked || false;
    const myDebut = isJournee ? '00:00' : (document.getElementById('slResaDebut')?.value || '');
    const myFin = isJournee ? '23:59' : (document.getElementById('slResaFin')?.value || '');
    if (!myDebut || !myFin) { alertWrap.style.display = 'none'; return; }

    // Only keep reservations that actually overlap with chosen time
    const conflicts = reservations.filter(r => {
        if (r.salle_id !== salleId || r.date_jour !== dateVal) return false;
        const rDebut = parseInt(r.journee_entiere) ? '00:00' : r.heure_debut.substring(0, 5);
        const rFin = parseInt(r.journee_entiere) ? '23:59' : r.heure_fin.substring(0, 5);
        return rDebut < myFin && rFin > myDebut;
    });

    if (!conflicts.length) { alertWrap.style.display = 'none'; return; }

    const salleObj = salles.find(s => s.id === salleId);

    alertTitle.textContent = conflicts.length + ' conflit' + (conflicts.length > 1 ? 's' : '') + ' — ' + (salleObj?.nom || 'cette salle');

    let html = '';
    conflicts.forEach(r => {
        const isJE = parseInt(r.journee_entiere);
        const timeStr = isJE ? 'Journée entière' : r.heure_debut.substring(0, 5) + ' — ' + r.heure_fin.substring(0, 5);
        html += '<div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid rgba(0,0,0,.06)">'
            + '<span style="width:8px;height:8px;border-radius:50%;background:' + escapeHtml(salleObj?.couleur || '#888') + ';flex-shrink:0"></span>'
            + '<span style="font-weight:600">' + escapeHtml(r.titre) + '</span>'
            + '<span style="margin-left:auto;white-space:nowrap">' + timeStr + '</span>'
            + '</div>';
    });
    alertList.innerHTML = html;
    alertWrap.style.display = 'block';
    alertBody.style.display = 'block';
    document.getElementById('slResaAlertChevron').style.transform = 'rotate(180deg)';
}

async function saveResa() {
    const isJournee = document.getElementById('slResaJournee')?.checked || false;
    const data = {
        salle_id: document.getElementById('slResaSalle').value,
        titre: document.getElementById('slResaTitre').value.trim(),
        description: document.getElementById('slResaDesc').value.trim(),
        date_jour: document.getElementById('slResaDate').value,
        journee_entiere: isJournee ? 1 : 0,
        heure_debut: isJournee ? '00:00' : document.getElementById('slResaDebut').value,
        heure_fin: isJournee ? '23:59' : document.getElementById('slResaFin').value,
    };

    if (!data.titre) { toast('Titre requis', 'error'); return; }
    if (!data.date_jour) { toast('Date requise', 'error'); return; }

    const res = await apiPost('create_reservation_salle', data);
    if (res.success) {
        resaModal.hide();
        toast('Salle réservée !', 'success');
        loadWeek();
        // Refresh "mes réservations" → reload full page (SSR)
        setTimeout(() => location.reload(), 600);
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

function showDetail(r) {
    currentDetailResa = r;
    const salle = salles.find(s => s.id === r.salle_id);
    document.getElementById('slDetailTitle').textContent = r.titre;

    const dateFr = new Date(r.date_jour + 'T00:00:00').toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    let html = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">'
        + '<span style="width:12px;height:12px;border-radius:3px;background:' + escapeHtml(salle?.couleur || '#888') + ';display:inline-block"></span>'
        + '<strong>' + escapeHtml(salle?.nom || '?') + '</strong></div>'
        + '<p style="margin:0 0 6px;font-size:.85rem"><i class="bi bi-calendar3"></i> ' + escapeHtml(dateFr) + '</p>'
        + '<p style="margin:0 0 6px;font-size:.85rem"><i class="bi bi-clock"></i> ' + (parseInt(r.journee_entiere) ? 'Journée entière' : r.heure_debut.substring(0, 5) + ' — ' + r.heure_fin.substring(0, 5)) + '</p>'
        + '<p style="margin:0 0 6px;font-size:.85rem"><i class="bi bi-person"></i> ' + escapeHtml(r.prenom + ' ' + r.user_nom) + '</p>';
    if (r.description) html += '<p style="margin:10px 0 0;font-size:.82rem;color:var(--cl-text-muted)">' + escapeHtml(r.description) + '</p>';

    document.getElementById('slDetailBody').innerHTML = html;

    // Show cancel button only for own reservations
    const footer = document.getElementById('slDetailFooter');
    footer.style.display = (r.user_id === userId) ? '' : 'none';

    detailModal.show();
}

async function cancelFromDetail() {
    if (!currentDetailResa) return;
    const res = await apiPost('annuler_reservation_salle', { id: currentDetailResa.id });
    if (res.success) {
        detailModal.hide();
        toast('Réservation annulée', 'success');
        loadWeek();
        setTimeout(() => location.reload(), 600);
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

async function handleCancelResa(e) {
    const btn = e.target.closest('.sl-cancel-resa');
    if (!btn) return;
    const id = btn.dataset.id;
    const res = await apiPost('annuler_reservation_salle', { id });
    if (res.success) {
        toast('Réservation annulée', 'success');
        btn.closest('.d-flex').remove();
        loadWeek();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

function toast(msg, type) {
    if (window.toast) window.toast(msg, type);
    else if (window.showToast) window.showToast(msg, type);
}
