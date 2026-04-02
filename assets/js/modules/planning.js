/**
 * Planning module — read-only grid view
 */
import { apiPost, escapeHtml, formatDateShort } from '../helpers.js';
import { getHolidaysInRange, getUpcomingHolidays } from './holidays.js';

let currentWeekStart = null;
let viewMode = 'week';
let allAssignations = [];
let allUsers = [];
let allModules = [];
let planningInfo = null;
let selectedUserIds = null;
let filterTempSelection = new Set();
let activeFonctionTags = new Set();
let activeModuleTabs = new Set();
const myUserId = window.__SS__?.user?.id || '';

// Context menu state
let ctxMenuEl = null;
let ctxStyleEl = null;
let onContextMenuHandler = null;
let onClickForCtxHandler = null;
let onKeyForCtxHandler = null;

export async function init() {
    const today = new Date();
    const dow = today.getDay() || 7;
    currentWeekStart = new Date(today);
    currentWeekStart.setDate(today.getDate() - dow + 1);

    const moisEl = document.getElementById('planMois');
    moisEl.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;

    moisEl.addEventListener('change', () => loadData());
    document.querySelectorAll('#planViewMode .plan-switch-btn').forEach(btn => {
        btn.addEventListener('click', () => onViewSwitch(btn.dataset.val));
    });
    document.getElementById('planPrevWeek')?.addEventListener('click', () => moveWeek(-1));
    document.getElementById('planNextWeek')?.addEventListener('click', () => moveWeek(1));
    document.getElementById('planPrevMonth')?.addEventListener('click', () => moveMonth(-1));
    document.getElementById('planNextMonth')?.addEventListener('click', () => moveMonth(1));
    document.getElementById('planFilterBtn')?.addEventListener('click', openFilterModal);
    document.getElementById('planFilterApply')?.addEventListener('click', applyFilter);
    document.getElementById('planFilterSelectAll')?.addEventListener('click', () => toggleAllFilter(true));
    document.getElementById('planFilterDeselectAll')?.addEventListener('click', () => toggleAllFilter(false));
    document.getElementById('planFilterSearch')?.addEventListener('input', renderFilterList);
    document.getElementById('planPrintBtn')?.addEventListener('click', printPlanning);
    document.getElementById('planEmailBtn')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('planEmailModal')).show();
    });
    document.getElementById('planEmailSend')?.addEventListener('click', sendEmail);
    document.getElementById('planRowsFilter')?.addEventListener('change', () => renderGrid());

    allModules = window.__SS_PAGE_DATA__?.modules || [];

    setupContextMenu();
    await loadData();
}

function onViewSwitch(val) {
    viewMode = val;
    document.querySelectorAll('#planViewMode .plan-switch-btn').forEach(b => b.classList.toggle('active', b.dataset.val === val));
    const bg = document.getElementById('planSwitchBg');
    if (bg) bg.classList.toggle('right', val === 'month');
    document.getElementById('planWeekNav').style.display = viewMode === 'week' ? '' : 'none';
    if (viewMode === 'week') {
        const [y, m] = document.getElementById('planMois').value.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const d = first.getDay() || 7;
        currentWeekStart = new Date(first);
        currentWeekStart.setDate(first.getDate() - d + 1);
    }
    loadData();
}

function moveWeek(dir) {
    currentWeekStart.setDate(currentWeekStart.getDate() + dir * 7);
    loadData();
}

function moveMonth(dir) {
    const el = document.getElementById('planMois');
    const [y, m] = el.value.split('-').map(Number);
    const d = new Date(y, m - 1 + dir, 1);
    el.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    loadData();
}

async function loadData() {
    const container = document.getElementById('planningContent');
    container.innerHTML = '<div style="text-align:center;padding:2rem"><span class="spinner"></span></div>';
    const mois = document.getElementById('planMois').value;

    // Fetch planning status
    const planRes = await apiPost('get_planning_mois', { mois });
    planningInfo = planRes.planning || null;

    // Don't load assignations if planning doesn't exist or is still brouillon
    const statut = planningInfo?.statut || null;
    if (!statut || statut === 'brouillon') {
        allAssignations = [];
        allUsers = [];
        renderGrid();
        return;
    }

    if (viewMode === 'week') {
        const dateStr = fmtISO(currentWeekStart);
        const res = await apiPost('get_planning_hebdo', { date: dateStr });
        allAssignations = res.assignations || [];
        const sun = new Date(currentWeekStart);
        sun.setDate(sun.getDate() + 6);
        document.getElementById('planWeekLabel').textContent =
            `Sem. du ${formatDateShort(dateStr)} au ${formatDateShort(fmtISO(sun))}`;
    } else {
        const [y, m] = mois.split('-').map(Number);
        const daysInMonth = new Date(y, m, 0).getDate();
        allAssignations = [];
        const fetched = new Set();
        for (let d = 1; d <= daysInMonth; d += 7) {
            const dt = new Date(y, m - 1, d);
            const dw = dt.getDay() || 7;
            const monday = new Date(dt);
            monday.setDate(dt.getDate() - dw + 1);
            const key = fmtISO(monday);
            if (fetched.has(key)) continue;
            fetched.add(key);
            const r = await apiPost('get_planning_hebdo', { date: key });
            if (r.assignations) {
                for (const a of r.assignations) {
                    if (a.date_jour.startsWith(mois)) allAssignations.push(a);
                }
            }
        }
    }

    const uMap = {};
    for (const a of allAssignations) {
        if (!uMap[a.user_id]) {
            uMap[a.user_id] = {
                id: a.user_id, nom: a.nom, prenom: a.prenom,
                fonction_code: a.fonction_code || '', module_code: a.module_code || '',
                taux: parseFloat(a.taux) || 100,
            };
        }
    }
    allUsers = Object.values(uMap).sort((a, b) => a.nom.localeCompare(b.nom));
    renderGrid();
}

function renderGrid() {
    const container = document.getElementById('planningContent');
    const mois = document.getElementById('planMois').value;
    const dn = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

    let days = [];
    if (viewMode === 'week') {
        for (let i = 0; i < 7; i++) {
            const d = new Date(currentWeekStart);
            d.setDate(d.getDate() + i);
            days.push(d);
        }
    } else {
        const [y, m] = mois.split('-').map(Number);
        const dim = new Date(y, m, 0).getDate();
        for (let d = 1; d <= dim; d++) days.push(new Date(y, m - 1, d));
    }

    // Get holidays for the displayed date range
    const holidays = getHolidaysInRange(days);

    let filtered = allAssignations;
    if (selectedUserIds && selectedUserIds.size > 0) {
        filtered = allAssignations.filter(a => selectedUserIds.has(a.user_id));
    }

    const aIdx = {};
    filtered.forEach(a => { aIdx[a.user_id + '_' + a.date_jour] = a; });
    const userSet = new Set(filtered.map(a => a.user_id));
    const users = allUsers.filter(u => userSet.has(u.id));

    // ── Holiday bar (in toolbar) ──
    const holidayBar = document.getElementById('planHolidayBar');
    if (holidayBar) {
        let hbHtml = '';
        const holidaysInView = [];
        for (const d of days) {
            const ds = fmtISO(d);
            if (holidays.has(ds)) holidaysInView.push({ date: ds, day: d, ...holidays.get(ds) });
        }
        const upcoming = getUpcomingHolidays(new Date(), 45);
        if (holidaysInView.length > 0) {
            for (const hol of holidaysInView) {
                const dayStr = `${dn[hol.day.getDay()]} ${hol.day.getDate()}`;
                hbHtml += `<span class="hb-item"><span class="hb-icon">${hol.icon}</span>${hol.name}<span class="hb-days">${dayStr}</span></span>`;
            }
        } else if (upcoming.length > 0) {
            const next = upcoming[0];
            const label = next.daysUntil === 0 ? "Aujourd'hui" : next.daysUntil === 1 ? 'Demain' : `Dans ${next.daysUntil}j`;
            hbHtml += `<span class="hb-item"><span class="hb-icon">${next.icon}</span>${next.name}<span class="hb-days">${label}</span></span>`;
            if (upcoming.length > 1) {
                const n2 = upcoming[1];
                hbHtml += `<span class="hb-item"><span class="hb-icon">${n2.icon}</span>${n2.name}<span class="hb-days">Dans ${n2.daysUntil}j</span></span>`;
            }
        }
        holidayBar.innerHTML = hbHtml;
    }

    const noData = !users.length;
    const statut = planningInfo?.statut || null;
    const showOverlay = noData || !statut || statut === 'brouillon';

    if (noData) {
        renderEmptyWithOverlay(container, days, holidays, dn, statut);
        return;
    }

    // Group by module
    const mGrp = {}, mOrd = {};
    let oi = 0;
    users.forEach(u => {
        const k = u.module_code || 'Pool';
        if (!mGrp[k]) { mGrp[k] = []; mOrd[k] = oi++; }
        mGrp[k].push(u);
    });
    const groups = Object.entries(mGrp).sort((a, b) => mOrd[a[0]] - mOrd[b[0]]);

    // ── Rows limit ──
    const rowsLimit = parseInt(document.getElementById('planRowsFilter')?.value || '0', 10);
    if (rowsLimit > 0) {
        let remaining = rowsLimit;
        for (const [, gu] of groups) {
            if (remaining <= 0) { gu.length = 0; continue; }
            if (gu.length > remaining) gu.length = remaining;
            remaining -= gu.length;
        }
    }

    // ── Table ──
    let h = '<div class="pg-wrap"><table class="pg">';

    // THEAD
    h += '<thead><tr>';
    h += '<th class="c-name">Collaborateur</th>';
    h += '<th class="c-fn">Fn</th>';
    h += '<th class="c-taux">%</th>';
    for (const d of days) {
        const w = d.getDay();
        const ds = fmtISO(d);
        const isWe = w === 0 || w === 6;
        const isHol = holidays.has(ds);
        let cls = 'c-day';
        if (isWe) cls += ' we';
        if (isHol) cls += ' holiday';
        const hol = holidays.get(ds);
        h += `<th class="${cls}">${dn[w]}<br><span class="we-day-num">${d.getDate()}</span>`;
        if (isHol) h += `<span class="holiday-icon" title="${escapeHtml(hol.name)}">${hol.icon}</span>`;
        h += '</th>';
    }
    h += '<th class="c-tot">Heures</th></tr></thead>';

    // TBODY
    h += '<tbody>';
    for (const [mc, gu] of groups) {
        const fa = filtered.find(a => (a.module_code || 'Pool') === mc);
        const ml = fa?.module_nom || mc;

        h += `<tr class="mod"><td class="c-name">${escapeHtml(ml)}</td><td class="c-fn"></td><td class="c-taux">${gu.length}</td>`;
        for (const d of days) {
            const w = d.getDay();
            const ds = fmtISO(d);
            let cls = 'c-day';
            if (w === 0 || w === 6) cls += ' we';
            if (holidays.has(ds)) cls += ' holiday';
            h += `<td class="${cls}"></td>`;
        }
        h += '<td class="c-tot"></td></tr>';

        for (const u of gu) {
            let th = 0;
            const me = u.id === myUserId;
            h += `<tr${me ? ' class="me"' : ''} data-uid="${u.id}">`;
            h += `<td class="c-name">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</td>`;
            h += `<td class="c-fn"><span class="fn-tag">${escapeHtml(u.fonction_code || '?')}</span></td>`;
            h += `<td class="c-taux">${Math.round(u.taux)}</td>`;

            for (const d of days) {
                const ds = fmtISO(d);
                const a = aIdx[u.id + '_' + ds];
                const w = d.getDay();
                const isWe = w === 0 || w === 6;
                const isHol = holidays.has(ds);
                let cl = 'c-day';
                if (isWe) cl += ' we';
                if (isHol) cl += ' holiday';
                let ct = '';

                if (a) {
                    if (a.statut === 'absent') cl += ' absent';
                    else if (a.statut === 'repos') cl += ' repos';
                    if (a.horaire_code) {
                        ct = `<span class="sc" style="background:${a.couleur || '#6c757d'}">${escapeHtml(a.horaire_code)}</span>`;
                        if (a.statut === 'present' && a.duree_effective) th += parseFloat(a.duree_effective) || 0;
                    } else if (a.statut && a.statut !== 'present') {
                        ct = `<span class="text-muted">${a.statut === 'repos' ? '\u00b7' : '\u2715'}</span>`;
                    }
                }
                const tdAttrs = (a && a.horaire_code)
                    ? ` data-date="${escapeHtml(ds)}" data-uid="${escapeHtml(u.id)}" data-uname="${escapeHtml(u.prenom + ' ' + u.nom)}" data-hcode="${escapeHtml(a.horaire_code)}" data-hnom="${escapeHtml(a.horaire_nom || '')}" data-hcouleur="${escapeHtml(a.couleur || '#6c757d')}" data-hmod="${escapeHtml(a.module_nom || '')}"`
                    : '';
                h += `<td class="${cl}"${tdAttrs}>${ct}</td>`;
            }

            const tgt = viewMode === 'month' ? Math.round(21.7 * 8.4 * (u.taux / 100)) : Math.round(5 * 8.4 * (u.taux / 100));
            const ec = Math.round((th - tgt) * 10) / 10;
            let hc = 'h-ok';
            if (ec < -5) hc = 'h-under';
            else if (ec > 5) hc = 'h-over';
            h += `<td class="c-tot"><span class="${hc}">${Math.round(th)}h</span>`;
            if (viewMode === 'month') h += `<br><small class="text-muted">${ec > 0 ? '+' : ''}${ec}</small>`;
            h += '</td></tr>';
        }
    }
    h += '</tbody></table></div>';

    // Wrap with status overlay if brouillon
    if (showOverlay) {
        h = `<div class="pg-status-wrap">${h}${buildStatusOverlay(statut)}</div>`;
    }

    // Filter banner
    if (selectedUserIds && selectedUserIds.size > 0) {
        h = `<div class="pg-filter-banner"><i class="bi bi-funnel-fill"></i> <strong>${selectedUserIds.size}</strong> collègue(s) filtrés <button class="pg-filter-clear" id="planClearFilter"><i class="bi bi-x-lg"></i> Effacer</button></div>` + h;
    }

    container.innerHTML = h;

    document.getElementById('planClearFilter')?.addEventListener('click', () => {
        selectedUserIds = null;
        updateFilterBadge();
        renderGrid();
    });

    // Auto-scroll to connected user's row
    requestAnimationFrame(() => {
        const myRow = container.querySelector('tr[data-uid="' + myUserId + '"]');
        if (myRow) {
            const wrap = container.querySelector('.pg-wrap');
            if (wrap) {
                const rowTop = myRow.offsetTop - wrap.offsetTop;
                wrap.scrollTop = Math.max(0, rowTop - 60);
            }
        }
    });
}

// ── Status overlay ──
function buildStatusOverlay(statut) {
    const steps = [
        { key: 'brouillon',   label: 'Brouillon',   icon: '✏️' },
        { key: 'provisoire',  label: 'Provisoire',   icon: '👁' },
        { key: 'final',       label: 'Final',        icon: '✅' },
    ];
    const order = ['brouillon', 'provisoire', 'final'];
    const idx = statut ? order.indexOf(statut) : -1;

    let icon, title, sub;
    if (!statut || idx < 0) {
        icon = '🕐';
        title = 'Le planning arrive bientôt';
        sub = 'Patience, l\'équipe prépare le planning de ce mois !';
    } else if (statut === 'brouillon') {
        icon = '✏️';
        title = 'Planning en cours de préparation';
        sub = 'Le planning est encore en brouillon, il sera bientôt disponible.';
    } else if (statut === 'provisoire') {
        icon = '👁';
        title = 'Planning provisoire';
        sub = 'Ce planning est provisoire et peut encore être modifié.';
    } else {
        icon = '✅';
        title = 'Planning finalisé';
        sub = '';
    }

    let progressHtml = '<div class="pg-progress-track">';
    for (let i = 0; i < steps.length; i++) {
        const s = steps[i];
        let cls = '';
        if (idx >= 0 && i < idx) cls = 'done';
        else if (i === idx) cls = 'active';

        if (i > 0) {
            progressHtml += `<div class="pg-progress-line${i <= idx ? ' done' : ''}"></div>`;
        }
        const dotContent = cls === 'done' ? '<i class="bi bi-check"></i>' : (i + 1);
        progressHtml += `<div class="pg-progress-step ${cls}"><div class="pg-progress-dot">${dotContent}</div><div class="pg-progress-label">${s.label}</div></div>`;
    }
    progressHtml += '</div>';

    return `<div class="pg-status-overlay"><div class="pg-status-card">
        <div class="pg-status-icon">${icon}</div>
        <div class="pg-status-title">${title}</div>
        ${sub ? `<div class="pg-status-sub">${sub}</div>` : ''}
        ${progressHtml}
    </div></div>`;
}

function renderEmptyWithOverlay(container, days, holidays, dn, statut) {
    const dn2 = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    let h = '<div class="pg-status-wrap"><div class="pg-wrap"><table class="pg"><thead><tr>';
    h += '<th class="c-name">Collaborateur</th><th class="c-fn">Fn</th><th class="c-taux">%</th>';
    for (const d of days) {
        const w = d.getDay();
        const ds = fmtISO(d);
        const isWe = w === 0 || w === 6;
        const isHol = holidays.has(ds);
        let cls = 'c-day';
        if (isWe) cls += ' we';
        if (isHol) cls += ' holiday';
        h += `<th class="${cls}">${dn2[w]}<br>${d.getDate()}</th>`;
    }
    h += '<th class="c-tot">Heures</th></tr></thead><tbody>';
    // Empty placeholder rows
    for (let r = 0; r < 12; r++) {
        h += '<tr>';
        h += '<td class="c-name" style="color:transparent">—</td><td class="c-fn"></td><td class="c-taux"></td>';
        for (const d of days) {
            const w = d.getDay();
            const ds = fmtISO(d);
            let cls = 'c-day';
            if (w === 0 || w === 6) cls += ' we';
            if (holidays.has(ds)) cls += ' holiday';
            h += `<td class="${cls}"></td>`;
        }
        h += '<td class="c-tot"></td></tr>';
    }
    h += '</tbody></table></div>';
    h += buildStatusOverlay(statut);
    h += '</div>';
    container.innerHTML = h;
}

// ── Filter Modal ──
function openFilterModal() {
    filterTempSelection = new Set(selectedUserIds || []);
    activeFonctionTags.clear();
    activeModuleTabs.clear();
    renderFilterCategories();
    renderFilterList();
    updateFilterCount();
    new bootstrap.Modal(document.getElementById('planFilterModal')).show();
}

function renderFilterCategories() {
    const fonctions = {};
    allUsers.forEach(u => { const fc = u.fonction_code || 'Autre'; fonctions[fc] = (fonctions[fc] || 0) + 1; });

    const fnContainer = document.getElementById('planFilterFonctions');
    fnContainer.innerHTML = Object.entries(fonctions).map(([fc, cnt]) =>
        `<span class="pf-tag${activeFonctionTags.has(fc) ? ' active' : ''}" data-fn="${fc}">${escapeHtml(fc)}<span class="pf-tag-count">${cnt}</span></span>`
    ).join('');

    fnContainer.querySelectorAll('.pf-tag').forEach(tag => {
        tag.addEventListener('click', () => {
            const fc = tag.dataset.fn;
            const usersOfFn = allUsers.filter(u => (u.fonction_code || 'Autre') === fc);
            if (activeFonctionTags.has(fc)) {
                activeFonctionTags.delete(fc);
                tag.classList.remove('active');
                usersOfFn.forEach(u => filterTempSelection.delete(u.id));
            } else {
                activeFonctionTags.add(fc);
                tag.classList.add('active');
                usersOfFn.forEach(u => filterTempSelection.add(u.id));
            }
            renderFilterList();
            updateFilterCount();
        });
    });

    const modContainer = document.getElementById('planFilterModules');
    const modCounts = {};
    allUsers.forEach(u => { const mc = u.module_code || '__pool__'; modCounts[mc] = (modCounts[mc] || 0) + 1; });

    let modTabs = allModules.map(m => ({ code: m.code, nom: m.nom, count: modCounts[m.code] || 0 }));
    const poolCount = allUsers.filter(u => !u.module_code || !allModules.some(m => m.code === u.module_code)).length;
    if (poolCount > 0) {
        modTabs.push({ code: '__pool__', nom: 'Pool', count: poolCount });
    }

    modContainer.innerHTML = modTabs.map(m =>
        `<span class="pf-mod-tab${activeModuleTabs.has(m.code) ? ' active' : ''}" data-mod="${m.code}">${escapeHtml(m.code === '__pool__' ? m.nom : m.code)}<span class="pf-tag-count">${m.count}</span></span>`
    ).join('');

    modContainer.querySelectorAll('.pf-mod-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const mc = tab.dataset.mod;
            let usersOfMod;
            if (mc === '__pool__') {
                usersOfMod = allUsers.filter(u => !u.module_code || !allModules.some(m => m.code === u.module_code));
            } else {
                usersOfMod = allUsers.filter(u => u.module_code === mc);
            }
            if (activeModuleTabs.has(mc)) {
                activeModuleTabs.delete(mc);
                tab.classList.remove('active');
                usersOfMod.forEach(u => filterTempSelection.delete(u.id));
            } else {
                activeModuleTabs.add(mc);
                tab.classList.add('active');
                usersOfMod.forEach(u => filterTempSelection.add(u.id));
            }
            renderFilterList();
            updateFilterCount();
        });
    });
}

function renderFilterList() {
    const query = (document.getElementById('planFilterSearch')?.value || '').trim().toLowerCase();
    const list = document.getElementById('planFilterList');
    const byFn = {};
    allUsers.forEach(u => {
        if (query && !(`${u.prenom} ${u.nom}`).toLowerCase().includes(query)) return;
        const fc = u.fonction_code || 'Autre';
        if (!byFn[fc]) byFn[fc] = [];
        byFn[fc].push(u);
    });

    let html = '';
    for (const [fc, us] of Object.entries(byFn)) {
        html += `<div style="font-size:.7rem;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.5px;padding:8px 10px 3px">${escapeHtml(fc)} (${us.length})</div>`;
        for (const u of us) {
            const sel = filterTempSelection.has(u.id);
            html += `<div class="pf-user${sel ? ' selected' : ''}" data-uid="${u.id}">
                <span class="pf-user-name">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</span>
                <span class="pf-user-mod">${escapeHtml(u.module_code || '—')}</span>
                <span class="pf-check"><i class="bi bi-check-lg"></i></span>
            </div>`;
        }
    }
    if (!html) html = '<div style="text-align:center;color:#999;padding:1.5rem">Aucun résultat</div>';
    list.innerHTML = html;

    list.querySelectorAll('.pf-user').forEach(row => {
        row.addEventListener('click', () => {
            const uid = row.dataset.uid;
            if (filterTempSelection.has(uid)) {
                filterTempSelection.delete(uid);
                row.classList.remove('selected');
            } else {
                filterTempSelection.add(uid);
                row.classList.add('selected');
            }
            updateFilterCount();
        });
    });
}

function toggleAllFilter(sel) {
    if (sel) allUsers.forEach(u => filterTempSelection.add(u.id));
    else filterTempSelection.clear();
    if (!sel) {
        activeFonctionTags.clear();
        activeModuleTabs.clear();
        document.querySelectorAll('.pf-tag.active, .pf-mod-tab.active').forEach(el => el.classList.remove('active'));
    }
    renderFilterList();
    updateFilterCount();
}

function updateFilterCount() {
    document.getElementById('planFilterSelectedCount').textContent = filterTempSelection.size;
}

function applyFilter() {
    selectedUserIds = (filterTempSelection.size > 0 && filterTempSelection.size < allUsers.length)
        ? new Set(filterTempSelection) : null;
    updateFilterBadge();
    bootstrap.Modal.getInstance(document.getElementById('planFilterModal'))?.hide();
    renderGrid();
}

function updateFilterBadge() {
    const badge = document.getElementById('planFilterCount');
    if (selectedUserIds && selectedUserIds.size > 0) { badge.textContent = selectedUserIds.size; badge.style.display = ''; }
    else badge.style.display = 'none';
}

// ── Print / Email ──
function printPlanning() {
    const mois = document.getElementById('planMois').value;
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Planning — ${mois}</title>
        <style>body{font-size:9px;padding:6px;font-family:sans-serif}table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #ccc;padding:2px 3px;text-align:center;white-space:nowrap}
        th{background:#f0f0f0;font-weight:bold}.sc{display:inline-block;padding:1px 3px;border-radius:2px;color:#fff;font-weight:bold;font-size:8px}
        .mod td{background:#2c3e50!important;color:#fff;font-weight:bold;text-align:left;padding:3px 6px}
        @media print{body{margin:0}}</style></head><body>`);
    win.document.write(`<h4 style="text-align:center;margin:4px 0">Planning — ${mois}</h4>`);
    win.document.write(document.getElementById('planningContent').innerHTML);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(() => win.print(), 400);
}

async function sendEmail() {
    const to = document.getElementById('planEmailTo').value.trim();
    if (!to) { alert('Saisissez un email'); return; }
    const mois = document.getElementById('planMois').value;
    const res = await apiPost('send_planning_email', {
        to, subject: `Planning — ${mois}`,
        message: document.getElementById('planEmailMsg').value.trim(),
        html_content: document.getElementById('planningContent').innerHTML,
    });
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('planEmailModal'))?.hide();
        alert('Email envoyé !');
    } else alert(res.message || 'Erreur');
}

function fmtISO(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// ── Right-click context menu ──
function setupContextMenu() {
    // Remove any previous instance
    document.getElementById('planCtxMenu')?.remove();
    document.getElementById('planCtxStyle')?.remove();
    document.getElementById('planDetailPop')?.remove();

    ctxStyleEl = document.createElement('style');
    ctxStyleEl.id = 'planCtxStyle';
    ctxStyleEl.textContent = `
.plan-ctx-menu{position:fixed;z-index:9999;background:var(--ss-bg-card,#fff);border:1px solid var(--ss-border,#e5e7eb);border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.14);min-width:210px;overflow:hidden;padding:4px 0;font-size:.86rem}
.plan-ctx-header{padding:.35rem 1rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--ss-text-muted,#888);font-weight:600;border-bottom:1px solid var(--ss-border-light,#f0ede8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.plan-ctx-item{display:flex;align-items:center;gap:.6rem;padding:.5rem 1rem;cursor:pointer;color:var(--ss-text,#1a1a1a);transition:background .12s;white-space:nowrap}
.plan-ctx-item:hover{background:var(--ss-accent-bg,#f5f3ee)}
.plan-ctx-item i{font-size:.9rem;color:var(--ss-text-muted,#888);width:1.1em;text-align:center}
.plan-ctx-divider{height:1px;background:var(--ss-border-light,#f0ede8);margin:3px 0}
td[data-date]{cursor:context-menu !important}
.plan-detail-pop{position:fixed;z-index:9998;background:var(--ss-bg-card,#fff);border:1px solid var(--ss-border,#e5e7eb);border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.12);padding:.8rem 1rem;min-width:210px;max-width:260px;font-size:.84rem}
.plan-detail-pop h6{font-size:.85rem;font-weight:700;margin:0 0 .45rem}
.plan-detail-row{display:flex;align-items:center;gap:.4rem;margin:.22rem 0;font-size:.8rem;color:var(--ss-text-secondary,#555)}
.plan-detail-row i{font-size:.82rem;color:var(--ss-text-muted,#888);width:1em}
`;
    document.head.appendChild(ctxStyleEl);

    ctxMenuEl = document.createElement('div');
    ctxMenuEl.id = 'planCtxMenu';
    ctxMenuEl.className = 'plan-ctx-menu';
    ctxMenuEl.style.display = 'none';
    ctxMenuEl.innerHTML = `
<div class="plan-ctx-header" id="planCtxHeader"></div>
<div class="plan-ctx-item" id="planCtxDetailBtn"><i class="bi bi-info-circle"></i> D&eacute;tail de l&rsquo;horaire</div>
<div class="plan-ctx-divider"></div>
<div class="plan-ctx-item" id="planCtxChangeBtn"><i class="bi bi-arrow-left-right"></i> Demander un changement</div>`;
    document.body.appendChild(ctxMenuEl);

    let ctxData = null;
    let detailPopEl = null;
    const closeMenu = () => { ctxMenuEl.style.display = 'none'; };
    const closeDetail = () => { detailPopEl?.remove(); detailPopEl = null; };

    onContextMenuHandler = (e) => {
        const td = e.target.closest('td[data-date]');
        if (!td) { closeMenu(); return; }
        e.preventDefault();
        closeDetail();
        ctxData = { ...td.dataset };
        document.getElementById('planCtxHeader').textContent = ctxData.uname || '';
        const x = Math.min(e.clientX, window.innerWidth - 225);
        const y = Math.min(e.clientY, window.innerHeight - 115);
        ctxMenuEl.style.left = x + 'px';
        ctxMenuEl.style.top = y + 'px';
        ctxMenuEl.style.display = '';
    };

    onClickForCtxHandler = (e) => {
        if (!ctxMenuEl.contains(e.target)) closeMenu();
        if (detailPopEl && !detailPopEl.contains(e.target)) closeDetail();
    };

    onKeyForCtxHandler = (e) => {
        if (e.key === 'Escape') { closeMenu(); closeDetail(); }
    };

    document.addEventListener('contextmenu', onContextMenuHandler);
    document.addEventListener('click', onClickForCtxHandler);
    document.addEventListener('keydown', onKeyForCtxHandler);

    document.getElementById('planCtxDetailBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        closeMenu();
        if (!ctxData) return;
        closeDetail();

        const d = new Date(ctxData.date + 'T00:00:00');
        const dayStr = d.toLocaleDateString('fr-CH', { weekday: 'long', day: 'numeric', month: 'long' });

        detailPopEl = document.createElement('div');
        detailPopEl.id = 'planDetailPop';
        detailPopEl.className = 'plan-detail-pop';
        detailPopEl.innerHTML = `
<h6>${escapeHtml(ctxData.uname)}</h6>
<div class="plan-detail-row"><i class="bi bi-calendar3"></i> ${escapeHtml(dayStr)}</div>
<div class="plan-detail-row">
  <span style="background:${escapeHtml(ctxData.hcouleur)};color:#fff;padding:2px 9px;border-radius:4px;font-size:.76rem;font-weight:700;display:inline-block">${escapeHtml(ctxData.hcode)}</span>&ensp;${escapeHtml(ctxData.hnom || '')}
</div>
${ctxData.hmod ? `<div class="plan-detail-row"><i class="bi bi-building"></i> ${escapeHtml(ctxData.hmod)}</div>` : ''}
<div style="margin-top:.6rem;text-align:right">
  <button style="padding:2px 12px;font-size:.76rem;border:1px solid var(--ss-border,#e5e7eb);border-radius:5px;background:transparent;cursor:pointer" id="planDetailCloseBtn">Fermer</button>
</div>`;

        const tdRect = e.target.closest('td')?.getBoundingClientRect() || { right: e.clientX, top: e.clientY };
        detailPopEl.style.left = Math.min(tdRect.right + 6, window.innerWidth - 270) + 'px';
        detailPopEl.style.top = Math.min(tdRect.top, window.innerHeight - 190) + 'px';
        document.body.appendChild(detailPopEl);
        document.getElementById('planDetailCloseBtn')?.addEventListener('click', closeDetail);
    });

    document.getElementById('planCtxChangeBtn').addEventListener('click', (e) => {
        e.stopPropagation();
        closeMenu();
        if (!ctxData) return;
        const isCol = ctxData.uid !== myUserId;
        sessionStorage.setItem('changement_prefill', JSON.stringify({
            date: ctxData.date,
            destinataireId:  isCol ? ctxData.uid     : '',
            destinataireNom: isCol ? ctxData.uname   : '',
            colCode:         isCol ? ctxData.hcode   : '',
            colNom:          isCol ? ctxData.hnom    : '',
            colCouleur:      isCol ? ctxData.hcouleur: '#6c757d',
            colModule:       isCol ? ctxData.hmod    : '',
        }));
        window.__trNavigate?.('changements');
    });
}

export function destroy() {
    // Clean up context menu
    if (onContextMenuHandler) { document.removeEventListener('contextmenu', onContextMenuHandler); onContextMenuHandler = null; }
    if (onClickForCtxHandler) { document.removeEventListener('click', onClickForCtxHandler); onClickForCtxHandler = null; }
    if (onKeyForCtxHandler)   { document.removeEventListener('keydown', onKeyForCtxHandler);   onKeyForCtxHandler = null; }
    ctxMenuEl?.remove();  ctxMenuEl = null;
    ctxStyleEl?.remove(); ctxStyleEl = null;
    document.getElementById('planDetailPop')?.remove();

    currentWeekStart = null;
    allAssignations = [];
    allUsers = [];
    allModules = [];
    planningInfo = null;
    selectedUserIds = null;
}
