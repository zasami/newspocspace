/**
 * SpocSpace — Repartition module (employee view)
 * Slot-based weekly grid matching admin layout — read-only
 */
import { apiPost, escapeHtml } from '../helpers.js';

let data = null;
let weekStart = null;
let viewMode = 'week'; // 'week' or 'day'
let selectedDay = null; // for day view
let horairesModal = null;

const FR_FULL_DAYS = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
const FR_MONTHS = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
const SOIR_CODES = new Set(['S3','S4','D4','C2']);
const MOD_COLORS = { M1:'rep-c-M1', M2:'rep-c-M2', M3:'rep-c-M3', M4:'rep-c-M4', NUIT:'rep-c-NUIT', POOL:'rep-c-POOL', RS:'rep-c-RS' };

export async function init() {
    document.getElementById('repPrevWeek')?.addEventListener('click', () => navigate(-1));
    document.getElementById('repNextWeek')?.addEventListener('click', () => navigate(1));
    document.getElementById('repToday')?.addEventListener('click', () => { weekStart = null; selectedDay = null; load(); });

    const modalEl = document.getElementById('repHorairesModal');
    if (modalEl) horairesModal = new bootstrap.Modal(modalEl);
    document.getElementById('repInfoBtn')?.addEventListener('click', showHorairesModal);

    // View toggle
    document.querySelectorAll('#repViewToggle button').forEach(btn => {
        btn.addEventListener('click', () => {
            viewMode = btn.dataset.view;
            document.querySelectorAll('#repViewToggle button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (viewMode === 'day' && !selectedDay) selectedDay = fmtISO(new Date());
            render();
        });
    });

    // Drag-to-scroll + keyboard
    setupScrollNav();

    const ssrData = window.__SS_PAGE_DATA__;
    if (ssrData?.success) {
        data = ssrData;
        weekStart = ssrData.week_start;
        selectedDay = fmtISO(new Date());
        updateLabel();
        render();
    } else {
        selectedDay = fmtISO(new Date());
        await load();
    }
}

async function load() {
    const params = weekStart ? { date: weekStart } : {};
    const res = await apiPost('get_repartition', params);
    if (!res.success) return;
    data = res;
    weekStart = res.week_start;
    updateLabel();
    render();
}

function navigate(dir) {
    if (viewMode === 'day') {
        // Move day by day
        const d = new Date(selectedDay + 'T00:00:00');
        d.setDate(d.getDate() + dir);
        selectedDay = fmtISO(d);
        // Check if we crossed week boundary
        const monday = getMonday(d);
        const currentMonday = new Date(weekStart + 'T00:00:00');
        if (monday.getTime() !== currentMonday.getTime()) {
            weekStart = fmtISO(monday);
            load();
            return;
        }
        updateLabel();
        render();
    } else {
        // Move week by week
        const d = new Date(weekStart + 'T00:00:00');
        d.setDate(d.getDate() + dir * 7);
        weekStart = fmtISO(d);
        load();
    }
}

function updateLabel() {
    const el = document.getElementById('repWeekLabel');
    if (!el || !data) return;
    if (viewMode === 'day' && selectedDay) {
        const d = new Date(selectedDay + 'T00:00:00');
        const dow = (d.getDay() + 6) % 7;
        el.textContent = FR_FULL_DAYS[dow] + ' ' + d.getDate() + ' ' + FR_MONTHS[d.getMonth() + 1] + ' ' + d.getFullYear();
    } else {
        el.textContent = `Semaine ${data.week_num} — ${fmtShort(data.week_start)} au ${fmtShort(data.week_end)}`;
    }
}

// ─── Helpers ───
function fmtISO(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
function fmtShort(s) { const d = new Date(s+'T00:00:00'); return d.toLocaleDateString('fr-CH',{day:'numeric',month:'short'}); }
function getMonday(d) { const r = new Date(d); const dow = r.getDay() || 7; r.setDate(r.getDate() - dow + 1); r.setHours(0,0,0,0); return r; }
function dayHeaderLabel(d) {
    const dt = new Date(d.date + 'T00:00:00');
    const dow = (dt.getDay() + 6) % 7;
    return FR_FULL_DAYS[dow] + ', ' + dt.getDate() + ' ' + FR_MONTHS[dt.getMonth()+1] + ' ' + dt.getFullYear();
}

// ─── Build slot-based sections ───
function buildSections(days) {
    const fnMap = {};
    (data.fonctions || []).forEach(f => fnMap[f.code] = f);

    const horaireOrder = {};
    (data.horaires || []).forEach(h => horaireOrder[h.id] = h.heure_debut || '99:99');

    const byMod = {};
    (data.assignments || []).forEach(a => {
        const mid = a.module_id || '_NONE';
        if (!byMod[mid]) byMod[mid] = [];
        byMod[mid].push(a);
    });

    const dateList = days.map(d => d.date);

    function buildModSlots(mod, assigns) {
        const byFn = {};
        assigns.forEach(a => { const fc = a.fonction_code || '_'; if (!byFn[fc]) byFn[fc] = []; byFn[fc].push(a); });
        const fnCodes = Object.keys(byFn).sort((a,b) => ((fnMap[a]||{}).ordre||99) - ((fnMap[b]||{}).ordre||99));
        const sections = [];

        fnCodes.forEach(fc => {
            const fnAssigns = byFn[fc];
            const fnInfo = fnMap[fc] || { nom: fc };
            let slots = [];

            if (fc === 'AS' && mod?.etages?.length) {
                const usedIds = new Set();
                (mod.etages || []).forEach(et => {
                    (et.groupes || []).forEach(gr => {
                        const slotDays = {};
                        dateList.forEach(dt => {
                            const m = fnAssigns.find(a => a.date_jour === dt && a.groupe_id === gr.id && !SOIR_CODES.has(a.horaire_code));
                            if (m) { slotDays[dt] = m; usedIds.add(m.user_id + '|' + m.date_jour); }
                        });
                        slots.push({ label: et.code.replace('E','') + '-' + gr.code.replace(/^\d+-/,''), days: slotDays });
                    });
                });
                const soirA = fnAssigns.filter(a => !usedIds.has(a.user_id + '|' + a.date_jour));
                if (soirA.length) {
                    const soirByDay = {};
                    soirA.forEach(a => { if (!soirByDay[a.date_jour]) soirByDay[a.date_jour] = []; soirByDay[a.date_jour].push(a); });
                    const maxS = Math.max(...Object.values(soirByDay).map(arr => arr.length), 0);
                    for (let s = 0; s < maxS; s++) {
                        const sd = {};
                        dateList.forEach(dt => { if (soirByDay[dt]?.[s]) sd[dt] = soirByDay[dt][s]; });
                        slots.push({ label: 'Soir' + (maxS > 1 ? ' '+(s+1) : ''), days: sd });
                    }
                }
            } else {
                const byDay = {};
                dateList.forEach(dt => {
                    byDay[dt] = fnAssigns.filter(a => a.date_jour === dt).sort((a,b) => (horaireOrder[a.horaire_type_id]||'99').localeCompare(horaireOrder[b.horaire_type_id]||'99'));
                });
                const maxSlots = Math.max(...dateList.map(dt => (byDay[dt]||[]).length), 0);
                for (let s = 0; s < maxSlots; s++) {
                    const sd = {};
                    dateList.forEach(dt => { if (byDay[dt]?.[s]) sd[dt] = byDay[dt][s]; });
                    slots.push({ label: maxSlots > 1 ? String(s+1) : '', days: sd });
                }
            }
            if (slots.length) sections.push({ code: fc, nom: fnInfo.nom || fc, slots });
        });
        return sections;
    }

    const result = [];
    const modules = data.modules || [];

    // RS/RUV
    const rsA = (data.assignments || []).filter(a => a.fonction_code === 'RS' || a.fonction_code === 'RUV');
    if (rsA.length) result.push({ module: { id: '', code: 'RS', nom: 'RS / RUVs', etages: [] }, functions: buildModSlots(null, rsA) });

    modules.forEach(mod => {
        const assigns = (byMod[mod.id] || []).filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
        if (!assigns.length) return;
        const fns = buildModSlots(mod, assigns);
        if (fns.length) result.push({ module: mod, functions: fns });
    });

    if (byMod['_NONE']) {
        const noneA = byMod['_NONE'].filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
        if (noneA.length) result.push({ module: { id: '', code: 'POOL', nom: 'Pool', etages: [] }, functions: buildModSlots(null, noneA) });
    }

    return result;
}

// ─── Render ───
function render() {
    if (!data) return;

    // Filter days based on view mode
    let days = data.days || [];
    if (viewMode === 'day' && selectedDay) {
        days = days.filter(d => d.date === selectedDay);
        if (!days.length) {
            // selectedDay is outside loaded week — show message
            document.getElementById('repGrid').innerHTML = '<div class="text-center py-4 text-muted">Chargement...</div>';
            return;
        }
    }

    const sections = buildSections(days);
    if (!sections.length) {
        document.getElementById('repGrid').innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-calendar-x" style="font-size:1.5rem;opacity:.3;display:block;margin-bottom:8px"></i>Aucune donnée</div>';
        return;
    }

    const isDayView = viewMode === 'day';
    const today = fmtISO(new Date());

    let html = '';
    sections.forEach(sec => {
        const mod = sec.module;
        const colorCls = MOD_COLORS[mod.code] || 'rep-c-DEFAULT';
        let total = 0;
        sec.functions.forEach(fn => total += fn.slots.length);

        html += '<div class="rep-section">';
        html += '<div class="rep-section-header ' + colorCls + '"><i class="bi bi-building"></i> ' + escapeHtml(mod.nom || mod.code) + ' <span class="badge">' + total + '</span></div>';
        html += '<div class="rep-section-scroll" tabindex="-1">';
        html += '<table class="rep-tbl' + (isDayView ? ' rep-day-view' : '') + '">';

        // Header
        html += '<thead><tr><th class="col-fn" rowspan="2">Fonction</th><th class="col-slot" rowspan="2">Poste</th>';
        days.forEach(d => {
            const we = d.is_weekend ? ' weekend' : '';
            const isToday = d.date === today;
            const style = isToday ? ' style="background:var(--ss-accent-bg)"' : '';
            html += '<th class="col-day-head day-first' + we + '" colspan="3"' + style + '>' + dayHeaderLabel(d) + '</th>';
        });
        html += '</tr><tr>';
        days.forEach(() => {
            html += '<th class="col-sub col-sub-nom day-first"></th><th class="col-sub col-sub-hor">Hor.</th><th class="col-sub col-sub-et">Ét.</th>';
        });
        html += '</tr></thead><tbody>';

        // Rows
        sec.functions.forEach(fn => {
            fn.slots.forEach((slot, si) => {
                html += '<tr' + (si === 0 ? ' class="fn-first"' : '') + '>';
                if (si === 0) html += '<td class="cell-fn" rowspan="' + fn.slots.length + '">' + escapeHtml(fn.nom) + '</td>';
                html += '<td class="cell-slot">' + escapeHtml(slot.label) + '</td>';

                days.forEach(d => {
                    const a = slot.days[d.date] || null;
                    const we = d.is_weekend ? ' weekend' : '';
                    const isToday = d.date === today;
                    const todayStyle = isToday ? ';background:var(--ss-accent-bg)' : '';

                    // Nom
                    let nom = '';
                    if (a) {
                        nom = escapeHtml(a.user_prenom || '');
                        if (a.notes) nom += '<span class="rep-note-dot" title="' + escapeHtml(a.notes) + '">*</span>';
                    }
                    html += '<td class="cell-nom day-first' + we + '"' + (todayStyle ? ' style="' + todayStyle.slice(1) + '"' : '') + '>' + nom + '</td>';

                    // Hor
                    let hor = '';
                    if (a?.horaire_code) hor = '<span class="rep-badge" style="background:' + (a.horaire_couleur || '#6c757d') + '">' + escapeHtml(a.horaire_code) + '</span>';
                    html += '<td class="cell-hor' + we + '"' + (todayStyle ? ' style="' + todayStyle.slice(1) + '"' : '') + '>' + hor + '</td>';

                    // Ét
                    let et = '';
                    if (a) {
                        if (a.etage_code && a.groupe_code) et = escapeHtml(a.etage_code.replace('E','') + '-' + a.groupe_code.replace(/^\d+-/,''));
                        else if (a.groupe_code) et = escapeHtml(a.groupe_code);
                        else if (a.etage_code) et = escapeHtml(a.etage_code.replace('E',''));
                    }
                    html += '<td class="cell-et' + we + '"' + (todayStyle ? ' style="' + todayStyle.slice(1) + '"' : '') + '>' + et + '</td>';
                });
                html += '</tr>';
            });
        });

        html += '</tbody></table></div></div>';
    });

    document.getElementById('repGrid').innerHTML = html;
    updateLabel();
}

// ─── Drag-to-scroll + keyboard arrows ───
function setupScrollNav() {
    const grid = document.getElementById('repGrid');
    let dragEl = null, startX = 0, startScroll = 0;

    grid.addEventListener('mousedown', e => {
        const sec = e.target.closest('.rep-section-scroll');
        if (!sec || e.target.closest('button')) return;
        dragEl = sec; startX = e.pageX; startScroll = sec.scrollLeft;
        sec.style.cursor = 'grabbing'; sec.style.userSelect = 'none'; sec.style.scrollBehavior = 'auto';
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => { if (dragEl) dragEl.scrollLeft = startScroll - (e.pageX - startX); });
    document.addEventListener('mouseup', () => {
        if (!dragEl) return;
        dragEl.style.cursor = ''; dragEl.style.userSelect = ''; dragEl.style.scrollBehavior = '';
        dragEl = null;
    });

    grid.addEventListener('click', e => {
        const sec = e.target.closest('.rep-section-scroll');
        if (sec) { sec.setAttribute('tabindex', '-1'); sec.focus({ preventScroll: true }); }
    });
    grid.addEventListener('keydown', e => {
        const sec = e.target.closest('.rep-section-scroll');
        if (!sec) return;
        if (e.key === 'ArrowRight') { sec.scrollLeft += 200; e.preventDefault(); }
        else if (e.key === 'ArrowLeft') { sec.scrollLeft -= 200; e.preventDefault(); }
    });
}

// ─── Horaires modal ───
function showHorairesModal() {
    if (!data?.horaires?.length) return;
    let html = '<div style="display:flex;flex-direction:column">';
    data.horaires.forEach((h, i) => {
        const bg = i % 2 === 0 ? 'var(--ss-bg, #F7F5F2)' : 'var(--ss-bg-card, #fff)';
        html += `<div style="display:flex;align-items:center;gap:1rem;padding:.75rem 1.25rem;background:${bg};border-bottom:1px solid var(--ss-border-light)">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;background:${escapeHtml(h.couleur||'#6c757d')};color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0">${escapeHtml(h.code)}</span>
            <div style="flex:1"><div style="font-weight:600;font-size:.92rem">${escapeHtml(h.code)}</div>
            <div style="display:flex;gap:.75rem;margin-top:.15rem">
                <span style="font-size:.82rem;color:var(--ss-text-secondary)"><i class="bi bi-clock" style="font-size:.72rem;margin-right:.2rem"></i>${(h.heure_debut||'').slice(0,5)} — ${(h.heure_fin||'').slice(0,5)}</span>
                ${h.duree_effective ? `<span style="font-size:.78rem;color:var(--ss-text-muted)"><i class="bi bi-hourglass-split" style="font-size:.68rem;margin-right:.15rem"></i>${escapeHtml(h.duree_effective)}h</span>` : ''}
            </div></div>
            <div style="width:12px;height:12px;border-radius:50%;background:${escapeHtml(h.couleur||'#6c757d')}"></div>
        </div>`;
    });
    html += '</div>';
    document.getElementById('repHorairesBody').innerHTML = html;
    if (horairesModal) horairesModal.show();
}

export function destroy() {}
