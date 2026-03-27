/**
 * Vacances — two-section layout:
 *   1) Top: current user's big interactive row (drag to select, click to delete pending)
 *   2) Bottom: read-only team grid (current user highlighted, auto-scroll after submit)
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

const MO = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DJ = ['D','L','M','M','J','V','S'];
const ME = window.__ZT__?.user?.id || '';

let year, month, data = null, modFilter = '';
let dragging = false, dStart = null, dEnd = null;
let formModal = null, confirmModal = null;
let cellSize = 0; // -1 = small, 0 = medium (default), 1 = large
const _c = [];
function on(el, ev, fn, o) { if (!el) return; el.addEventListener(ev, fn, o); _c.push(() => el.removeEventListener(ev, fn, o)); }

export async function init() {
    const now = new Date();
    year = now.getFullYear();
    month = now.getMonth();

    // Load cell size preference
    cellSize = parseInt(localStorage.getItem('zt_vac_cellsize') || '0');
    updateSizeButtons();

    const fmEl = document.getElementById('vacFormModal');
    const cmEl = document.getElementById('vacConfirmModal');
    if (fmEl) formModal = new bootstrap.Modal(fmEl);
    if (cmEl) confirmModal = new bootstrap.Modal(cmEl);

    const app = document.getElementById('app-content');
    if (app) app.style.maxWidth = '100%';

    on(document.getElementById('vacPrevMonth'), 'click', () => { month--; if (month < 0) { month = 11; year--; } load(); });
    on(document.getElementById('vacNextMonth'), 'click', () => { month++; if (month > 11) { month = 0; year++; } load(); });
    on(document.getElementById('vacModuleFilter'), 'change', e => { modFilter = e.target.value; renderTeam(); });
    on(document.getElementById('vacFormBtn'), 'click', openForm);
    on(document.getElementById('vacFormDebut'), 'change', updateFormInfo);
    on(document.getElementById('vacFormFin'), 'change', updateFormInfo);
    on(document.getElementById('vacFormSubmit'), 'click', submitForm);
    on(document.getElementById('vacConfirmDebut'), 'change', updateConfirmInfo);
    on(document.getElementById('vacConfirmFin'), 'change', updateConfirmInfo);
    on(document.getElementById('vacConfirmSubmit'), 'click', submitConfirm);
    on(document.getElementById('vacDragCancel'), 'click', cancelDrag);
    on(document, 'mousemove', onMM);
    on(document, 'mouseup', onMU);
    on(document, 'touchend', onMU);
    on(document, 'selectstart', e => { if (dragging) e.preventDefault(); });

    // Size buttons
    on(document.getElementById('vacSize--1'), 'click', () => setSizeAndRender(-1));
    on(document.getElementById('vacSize-0'), 'click', () => setSizeAndRender(0));
    on(document.getElementById('vacSize-1'), 'click', () => setSizeAndRender(1));

    await load();
}

async function load() {
    updateHeader();

    const myG = document.getElementById('vacMyGrid');
    const teamG = document.getElementById('vacTeamGrid');
    if (myG) myG.innerHTML = '<div class="vac-empty"><span class="spinner-border spinner-border-sm"></span></div>';
    if (teamG) teamG.innerHTML = '<div class="vac-empty"><span class="spinner-border spinner-border-sm"></span></div>';

    data = await apiPost('get_vacances_annee', { annee: year });
    if (!data?.success) {
        if (myG) myG.innerHTML = '<div class="vac-empty">Erreur</div>';
        if (teamG) teamG.innerHTML = '';
        return;
    }

    // Module filter
    const sel = document.getElementById('vacModuleFilter');
    if (sel) {
        const prev = sel.value;
        sel.innerHTML = '<option value="">Tous les modules</option>';
        (data.modules || []).forEach(m => { sel.innerHTML += `<option value="${m.id}">${escapeHtml(m.code)} — ${escapeHtml(m.nom)}</option>`; });
        sel.value = prev || modFilter;
    }

    // Solde
    const solde = data.mon_solde || 0, used = data.jours_utilises || 0, rest = solde - used;
    const sv = document.getElementById('vacSoldeValue'), sd = document.getElementById('vacSoldeDetail'), se = document.getElementById('vacSolde');
    if (sv) sv.textContent = rd(rest) + 'j';
    if (sd) sd.textContent = `${rd(used)} pris / ${rd(solde)} total`;
    if (se) se.classList.toggle('low', rest <= 5);

    // My name
    const me = (data.users || []).find(u => u.id === ME);
    const nameEl = document.getElementById('vacMyName');
    if (nameEl && me) nameEl.textContent = me.prenom + ' ' + me.nom + ' — ' + (me.fonction_code || '');

    renderMy();
    renderTeam();
}

function updateHeader() {
    const cm = document.getElementById('vacCurrentMonth');
    if (cm) cm.textContent = MO[month] + ' ' + year;
    const pp = document.getElementById('vacMonthPills');
    if (!pp) return;
    let h = '';
    for (let m = 0; m < 12; m++) h += `<button class="vac-month-pill${m === month ? ' active' : ''}" data-m="${m}">${MO[m].substring(0, 3)}</button>`;
    pp.innerHTML = h;
    pp.querySelectorAll('.vac-month-pill').forEach(b => b.addEventListener('click', () => { month = +b.dataset.m; load(); }));
}

// ═══════════════════════════════════════════
// SECTION 1: My interactive row
// ═══════════════════════════════════════════
function renderMy() {
    const g = document.getElementById('vacMyGrid');
    if (!g) return;

    const dim = new Date(year, month + 1, 0).getDate();
    const days = []; for (let d = 1; d <= dim; d++) days.push(new Date(year, month, d));
    const todayStr = iso(new Date());

    const myAbs = (data.absences || []).filter(a => a.user_id === ME);
    const blocked = data.bloquees || [];

    let h = '<div class="vac-my-wrap"><table class="vac-my-table"><thead><tr>';
    days.forEach(d => {
        const dow = d.getDay(), we = dow === 0 || dow === 6, td = iso(d) === todayStr;
        h += `<th class="${we ? 'th-we' : ''}${td ? ' th-today' : ''}">${DJ[dow]}<br>${d.getDate()}</th>`;
    });
    h += '</tr></thead><tbody><tr>';

    days.forEach(d => {
        const ds = iso(d), dow = d.getDay(), we = dow === 0 || dow === 6, td = ds === todayStr;
        const bl = blocked.some(b => ds >= b.date_debut && ds <= b.date_fin);
        const ab = myAbs.find(a => ds >= a.date_debut && ds <= a.date_fin);

        let cls = 'mc'; if (we) cls += ' we'; if (td) cls += ' today'; if (bl) cls += ' blocked';
        if (ab) cls += ab.statut === 'valide' ? ' vv' : ' va';

        let cc = '';
        if (ab) {
            const ic = ab.statut === 'valide' ? '✓' : '⏳';
            cc = `<span class="my-lbl">${ic}</span>`;
            if (ab.statut === 'en_attente') cc += `<span class="my-del" data-aid="${ab.id}" title="Annuler">✕</span>`;
        }
        h += `<td class="${cls}" data-d="${ds}">${cc}</td>`;
    });

    h += '</tr></tbody></table></div>';
    g.innerHTML = h;

    // Bind delete buttons BEFORE drag (so mousedown stops propagation)
    g.querySelectorAll('.my-del').forEach(b => {
        // Block drag from starting when clicking delete
        b.addEventListener('mousedown', e => { e.stopPropagation(); e.preventDefault(); });
        b.addEventListener('touchstart', e => { e.stopPropagation(); e.preventDefault(); }, { passive: false });
        b.addEventListener('click', e => {
            e.stopPropagation(); e.preventDefault();
            const btn = e.currentTarget;
            const cell = btn.closest('td.mc');
            const row = cell.closest('tr');
            if (btn.dataset.confirming) {
                // Second click = confirm delete
                btn.textContent = '…';
                apiPost('annuler_vacances', { id: btn.dataset.aid }).then(r => {
                    if (r?.success) { toast('Demande annulée'); load(); }
                    else toast(r?.message || 'Erreur');
                });
                return;
            }
            // First click = show red confirm state on vacation cells only
            const absId = btn.dataset.aid;
            const ab = (data.absences || []).find(a => a.id === absId);
            if (!ab) return;
            
            btn.dataset.confirming = '1';
            // Add del-confirm class only to cells in the vacation date range
            row.querySelectorAll('td.mc').forEach(c => {
                if (c.dataset.d >= ab.date_debut && c.dataset.d <= ab.date_fin) {
                    c.classList.add('del-confirm');
                }
            });
            btn.innerHTML = '✓';
            btn.title = 'Confirmer la suppression';
            // Auto-cancel after 3s
            const timer = setTimeout(() => {
                delete btn.dataset.confirming;
                // Remove from all highlighted cells in the row
                row.querySelectorAll('td.mc.del-confirm').forEach(c => {
                    c.classList.remove('del-confirm');
                });
                btn.innerHTML = '✕';
                btn.title = 'Annuler';
            }, 3000);
            btn._timer = timer;
        });
    });

    // Bind drag on my cells
    g.querySelectorAll('td.mc').forEach(c => {
        c.addEventListener('mousedown', onMD);
        c.addEventListener('touchstart', onMD, { passive: false });
    });
}

// ═══════════════════════════════════════════
// SECTION 2: Team read-only grid
// ═══════════════════════════════════════════
function renderTeam(scrollToMe = false) {
    const g = document.getElementById('vacTeamGrid');
    if (!g) return;

    let users = data.users || [];
    if (modFilter) users = users.filter(u => u.module_id === modFilter);
    if (!users.length) { g.innerHTML = '<div class="vac-empty"><i class="bi bi-people" style="font-size:1.5rem"></i><br>Aucun collaborateur</div>'; return; }

    const dim = new Date(year, month + 1, 0).getDate();
    const days = []; for (let d = 1; d <= dim; d++) days.push(new Date(year, month, d));
    const todayStr = iso(new Date());

    const absMap = {};
    (data.absences || []).forEach(a => { if (!absMap[a.user_id]) absMap[a.user_id] = []; absMap[a.user_id].push(a); });

    // Group by module
    const grps = {}; const gOrd = {}; let gi = 0;
    users.forEach(u => { const k = u.module_code || 'AUTRE'; if (!grps[k]) { grps[k] = { label: u.module_nom || k, list: [] }; gOrd[k] = gi++; } grps[k].list.push(u); });
    const sorted = Object.entries(grps).sort((a, b) => (gOrd[a[0]] || 0) - (gOrd[b[0]] || 0));

    // Put current user's module first, user first in their group
    const mySorted = [...sorted];
    const myIdx = mySorted.findIndex(([, g]) => g.list.some(u => u.id === ME));
    if (myIdx > 0) { const [myGrp] = mySorted.splice(myIdx, 1); mySorted.unshift(myGrp); }

    let h = '<div class="vac-team-wrap"><table class="vac-team-table"><thead><tr><th class="col-user">Collaborateur</th>';
    days.forEach(d => {
        const dow = d.getDay(), we = dow === 0 || dow === 6, td = iso(d) === todayStr;
        h += `<th class="${we ? 'th-we' : ''}${td ? ' th-today' : ''}">${DJ[dow]}<br>${d.getDate()}</th>`;
    });
    h += '</tr></thead><tbody>';

    mySorted.forEach(([, grp]) => {
        grp.list.sort((a, b) => (a.id === ME ? -1 : b.id === ME ? 1 : 0));
        h += `<tr class="module-sep"><td colspan="${dim + 1}">${escapeHtml(grp.label)} (${grp.list.length})</td></tr>`;
        grp.list.forEach(u => {
            const me = u.id === ME, ua = absMap[u.id] || [];
            const rid = me ? ' id="vacMyTeamRow"' : '';
            h += `<tr class="${me ? 'myrow' : ''}"${rid}><td class="col-user"><span class="fn-badge">${escapeHtml(u.fonction_code || '?')}</span> ${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</td>`;

            days.forEach((d, di) => {
                const ds = iso(d), dow = d.getDay(), we = dow === 0 || dow === 6, td = ds === todayStr;
                const ab = ua.find(a => ds >= a.date_debut && ds <= a.date_fin);
                let cls = 'tc'; if (we) cls += ' we'; if (td) cls += ' today';
                if (ab) cls += ab.statut === 'valide' ? ' vv' : ' va';

                let cc = '';
                if (ab) {
                    const mS = `${year}-${String(month+1).padStart(2,'0')}-01`;
                    const fv = ab.date_debut >= mS ? ab.date_debut : mS;
                    if (ds === fv) {
                        const mE = iso(days[days.length - 1]);
                        const lv = ab.date_fin <= mE ? ab.date_fin : mE;
                        let sp = 0; for (let k = di; k < days.length; k++) { if (iso(days[k]) <= lv) sp++; else break; }
                        const w = calcLabelWidth(sp);
                        const ic = ab.statut === 'valide' ? '✓' : '⏳';
                        const lb = sp >= 3 ? ic + ' ' + u.prenom : ic;
                        cc = `<span class="t-lbl${ab.statut === 'valide' ? '' : ' att'}" style="width:${w}px">${escapeHtml(lb)}</span>`;
                    }
                }
                h += `<td class="${cls}">${cc}</td>`;
            });
            h += '</tr>';
        });
    });
    h += '</tbody></table></div>';
    g.innerHTML = h;
    applyGridSize();

    if (scrollToMe) scrollToMyRow();
}

function scrollToMyRow() {
    setTimeout(() => {
        const row = document.getElementById('vacMyTeamRow');
        if (!row) return;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Brief flash
        row.style.transition = 'background .3s';
        row.style.background = 'rgba(25,135,84,.18)';
        setTimeout(() => { row.style.background = ''; }, 1200);
    }, 100);
}

function setSizeAndRender(size) {
    cellSize = size;
    localStorage.setItem('zt_vac_cellsize', size);
    updateSizeButtons();
    applyGridSize();
}

function updateSizeButtons() {
    document.getElementById('vacSize--1')?.classList.toggle('active', cellSize === -1);
    document.getElementById('vacSize-0')?.classList.toggle('active', cellSize === 0);
    document.getElementById('vacSize-1')?.classList.toggle('active', cellSize === 1);
}

function applyGridSize() {
    const g = document.getElementById('vacTeamGrid');
    if (!g) return;
    g.classList.remove('size-small', 'size-large');
    if (cellSize === -1) g.classList.add('size-small');
    else if (cellSize === 1) g.classList.add('size-large');
}

function calcLabelWidth(spanDays) {
    // Base cell width is 30px (medium)
    // Small: 20px, Large: 45px
    // Formula: 30 * (base_width / 30)
    // sp * 28 is the base width calculation
    let scale = 1;
    if (cellSize === -1) scale = 20 / 30;        // small
    else if (cellSize === 1) scale = 45 / 30;    // large
    return Math.round(spanDays * 28 * scale);
}

// ═══════════════════════════════════════════
// DRAG on my row
// ═══════════════════════════════════════════
function onMD(e) {
    const c = e.currentTarget;
    if (c.classList.contains('we') || c.classList.contains('blocked') || c.classList.contains('vv')) return;
    e.preventDefault();
    dragging = true; dStart = dEnd = c.dataset.d;
    document.body.style.userSelect = 'none'; document.body.style.webkitUserSelect = 'none';
    hlDrag(); showBar();
}

function onMM(e) {
    if (!dragging) return;
    const x = e.clientX ?? e.touches?.[0]?.clientX, y = e.clientY ?? e.touches?.[0]?.clientY;
    const el = document.elementFromPoint(x, y);
    if (!el) return;
    const c = el.closest('td.mc');
    if (c && c.dataset.d && c.dataset.d !== dEnd) { dEnd = c.dataset.d; hlDrag(); showBar(); }
}

function onMU() {
    if (!dragging) return;
    dragging = false;
    document.body.style.userSelect = ''; document.body.style.webkitUserSelect = '';
    if (!dStart || !dEnd) return;
    const [a, b] = dStart <= dEnd ? [dStart, dEnd] : [dEnd, dStart];

    const cd = document.getElementById('vacConfirmDebut'), cf = document.getElementById('vacConfirmFin'), ct = document.getElementById('vacConfirmText');
    if (cd) cd.value = a;
    if (cf) cf.value = b;
    if (ct) ct.textContent = a === b ? `Le ${fmtFR(a)}` : `Du ${fmtFR(a)} au ${fmtFR(b)}`;
    updateConfirmInfo();

    const btn = document.getElementById('vacConfirmSubmit');
    if (btn) { delete btn.dataset.editId; btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmer'; }
    if (confirmModal) confirmModal.show();

    clearHL();
    const bar = document.getElementById('vacDragInfo'); if (bar) bar.style.display = 'none';
}

function hlDrag() {
    clearHL(); if (!dStart || !dEnd) return;
    const [a, b] = dStart <= dEnd ? [dStart, dEnd] : [dEnd, dStart];
    document.querySelectorAll('td.mc').forEach(c => {
        if (c.dataset.d >= a && c.dataset.d <= b && !c.classList.contains('we') && !c.classList.contains('blocked')) c.classList.add('drag-hl');
    });
}
function clearHL() { document.querySelectorAll('td.mc.drag-hl').forEach(c => c.classList.remove('drag-hl')); }

function showBar() {
    if (!dStart || !dEnd) return;
    const [a, b] = dStart <= dEnd ? [dStart, dEnd] : [dEnd, dStart];
    const t = document.getElementById('vacDragText'), bar = document.getElementById('vacDragInfo'), hint = document.getElementById('vacDragHint');
    if (t) t.textContent = `${fmtFR(a)} → ${fmtFR(b)} — ${workDays(a, b)} jour(s) ouvré(s)`;
    if (bar) bar.style.display = '';
    if (hint) hint.style.display = 'none';
}

function cancelDrag() {
    dragging = false; dStart = dEnd = null;
    document.body.style.userSelect = '';
    clearHL();
    const bar = document.getElementById('vacDragInfo'); if (bar) bar.style.display = 'none';
    const hint = document.getElementById('vacDragHint'); if (hint) hint.style.display = '';
}

// ═══════════════════════════════════════════
// SUBMIT
// ═══════════════════════════════════════════
async function submitConfirm() {
    const d1 = document.getElementById('vacConfirmDebut')?.value;
    const d2 = document.getElementById('vacConfirmFin')?.value;
    await sub(d1, d2, confirmModal, document.getElementById('vacConfirmSubmit'));
}

async function submitForm() {
    await sub(document.getElementById('vacFormDebut')?.value, document.getElementById('vacFormFin')?.value, formModal, document.getElementById('vacFormSubmit'));
}

async function sub(d1, d2, modal, btn) {
    if (!d1 || !d2) { toast('Dates requises'); return; }
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const r = await apiPost('submit_vacances', { date_debut: d1, date_fin: d2 });
        if (modal) modal.hide();
        cancelDrag();
        if (r?.success) {
            toast('Demande soumise !');
            await load();
            scrollToMyRow();
        } else {
            toast(r?.message || 'Erreur');
        }
    } catch { toast('Erreur réseau'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
}

function openForm() {
    const d = document.getElementById('vacFormDebut'), f = document.getElementById('vacFormFin'), i = document.getElementById('vacFormInfo');
    if (d) d.value = ''; if (f) f.value = '';
    if (i) { i.innerHTML = ''; i.style.display = 'none'; }
    if (formModal) formModal.show();
}

function updateFormInfo() {
    const d1 = document.getElementById('vacFormDebut')?.value, d2 = document.getElementById('vacFormFin')?.value, el = document.getElementById('vacFormInfo');
    if (!el) return;
    if (d1 && d2 && d2 >= d1) { const n = workDays(d1, d2), r = (data?.mon_solde || 0) - (data?.jours_utilises || 0); el.innerHTML = `<strong>${n}</strong> jour(s) ouvré(s) — Solde après: <strong>${rd(r - n)}j</strong>`; el.style.display = ''; }
    else { el.innerHTML = ''; el.style.display = 'none'; }
}
function updateConfirmInfo() {
    const d1 = document.getElementById('vacConfirmDebut')?.value, d2 = document.getElementById('vacConfirmFin')?.value, el = document.getElementById('vacConfirmInfo');
    if (!el) return;
    if (d1 && d2 && d2 >= d1) { const n = workDays(d1, d2), r = (data?.mon_solde || 0) - (data?.jours_utilises || 0); el.innerHTML = `<strong>${n}</strong> jour(s) ouvré(s) — Solde après: <strong>${rd(r - n)}j</strong>`; el.style.display = ''; }
    else { el.innerHTML = ''; el.style.display = 'none'; }
}

// ── HELPERS ──
function rd(v) { return Math.round(v); }
function iso(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
function fmtFR(s) { const [,m,d] = s.split('-'); return `${+d} ${MO[+m-1].substring(0,3).toLowerCase()}`; }
function workDays(a, b) { let n = 0; const d = new Date(a), e = new Date(b); while (d <= e) { if (d.getDay() !== 0 && d.getDay() !== 6) n++; d.setDate(d.getDate()+1); } return n; }

export function destroy() {
    _c.forEach(fn => fn()); _c.length = 0;
    document.body.style.userSelect = '';
    const app = document.getElementById('app-content');
    if (app) app.style.maxWidth = '';
    if (formModal) { try { formModal.dispose(); } catch {} formModal = null; }
    if (confirmModal) { try { confirmModal.dispose(); } catch {} confirmModal = null; }
    dragging = false; data = null;
}
