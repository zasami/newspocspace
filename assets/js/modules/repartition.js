/**
 * zerdaTime — Repartition module (employee view)
 * Weekly staffing grid per module with tabs
 */
import { apiPost, escapeHtml } from '../helpers.js';

let weekStart = null;
let data = null;
let activeModuleId = null;

export async function init() {
    document.getElementById('repPrevWeek')?.addEventListener('click', () => moveWeek(-7));
    document.getElementById('repNextWeek')?.addEventListener('click', () => moveWeek(7));
    document.getElementById('repToday')?.addEventListener('click', () => { weekStart = null; load(); });

    // Default to user's principal module
    const user = window.__TR__?.user;
    if (user?.modules?.length) {
        const principal = user.modules.find(m => m.is_principal) || user.modules[0];
        if (principal) activeModuleId = principal.module_id || principal.id;
    }

    await load();
}

async function load() {
    const params = weekStart ? { date: weekStart } : {};
    const res = await apiPost('get_repartition', params);
    if (!res.success) return;
    data = res;
    weekStart = res.week_start;

    document.getElementById('repWeekLabel').textContent = `Semaine ${res.week_num} — ${fmtShort(res.week_start)} au ${fmtShort(res.week_end)}`;

    // If no active module yet, pick the first one
    if (!activeModuleId && data.modules.length) activeModuleId = data.modules[0].id;

    renderTabs();
    renderLegend();
    renderGrid();
}

function moveWeek(days) {
    const d = new Date(weekStart + 'T00:00:00');
    d.setDate(d.getDate() + days);
    weekStart = fmtISO(d);
    load();
}

function fmtISO(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function fmtShort(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' });
}

/* ── Tabs ── */
function renderTabs() {
    const container = document.getElementById('repModuleTabs');
    let html = '';
    for (const mod of data.modules) {
        const active = mod.id === activeModuleId;
        html += `<button class="btn btn-sm rep-tab${active ? ' rep-tab-active' : ''}" data-mod="${escapeHtml(mod.id)}">${escapeHtml(mod.code || mod.nom)}</button>`;
    }
    container.innerHTML = html;

    container.querySelectorAll('.rep-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            activeModuleId = btn.dataset.mod;
            renderTabs();
            renderGrid();
        });
    });
}

/* ── Legend ── */
function renderLegend() {
    const container = document.getElementById('repLegend');
    let html = '';
    for (const h of data.horaires) {
        const color = h.couleur || '#1a1a1a';
        const debut = h.heure_debut?.slice(0, 5) || '';
        const fin = h.heure_fin?.slice(0, 5) || '';
        html += `<span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.78rem">
            <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:${escapeHtml(color)}"></span>
            <strong>${escapeHtml(h.code)}</strong>
            <span class="text-muted">${debut}–${fin}</span>
        </span>`;
    }
    container.innerHTML = html;
}

/* ── Grid ── */
function renderGrid() {
    if (!data || !activeModuleId) return;

    const days = data.days;
    const mod = data.modules.find(m => m.id === activeModuleId);
    if (!mod) return;

    // Users assigned to this module (home module)
    const moduleUsers = data.users.filter(u => u.home_module_id === activeModuleId);

    // Also include users from other modules who have assignments in this module this week
    const assignedUserIds = new Set();
    for (const a of data.assignments) {
        if (a.module_id === activeModuleId) assignedUserIds.add(a.user_id);
    }
    const extraUsers = data.users.filter(u => u.home_module_id !== activeModuleId && assignedUserIds.has(u.id));

    // Group by fonction
    const fonctionMap = {};
    for (const u of [...moduleUsers, ...extraUsers]) {
        const fk = u.fonction_code || 'Autre';
        if (!fonctionMap[fk]) fonctionMap[fk] = { label: u.fonction_nom || fk, ordre: u.fonction_ordre ?? 99, users: [] };
        // Avoid duplicates
        if (!fonctionMap[fk].users.find(x => x.id === u.id)) {
            fonctionMap[fk].users.push(u);
        }
    }
    const groups = Object.values(fonctionMap).sort((a, b) => a.ordre - b.ordre);

    // Build assignment index: userId_date -> assignment
    const assignIdx = {};
    for (const a of data.assignments) {
        const key = a.user_id + '_' + a.date_jour;
        assignIdx[key] = a;
    }

    const today = fmtISO(new Date());

    // Header
    const head = document.getElementById('repHead');
    let headHtml = '<tr><th style="min-width:150px;position:sticky;left:0;background:var(--zt-bg-card, #fff);z-index:1">Collaborateur</th>';
    for (const day of days) {
        const isToday = day.date === today;
        const isWe = day.is_weekend;
        headHtml += `<th style="text-align:center;min-width:80px;${isToday ? 'background:var(--zt-accent-bg);font-weight:700' : ''}${isWe ? ';color:var(--zt-text-muted)' : ''}">${escapeHtml(day.label)}</th>`;
    }
    headHtml += '</tr>';
    head.innerHTML = headHtml;

    // Body
    const body = document.getElementById('repBody');
    if (!groups.length) {
        body.innerHTML = `<tr><td colspan="${days.length + 1}" class="text-center py-4 text-muted">Aucun collaborateur dans ce module</td></tr>`;
        return;
    }

    let html = '';
    for (const group of groups) {
        // Fonction header row
        html += `<tr><td colspan="${days.length + 1}" style="background:var(--zt-accent-bg);font-weight:600;font-size:0.82rem;padding:0.4rem 0.75rem;color:var(--zt-text);border-bottom:1px solid var(--zt-border)">${escapeHtml(group.label)}</td></tr>`;

        for (const user of group.users) {
            const isExternal = user.home_module_id !== activeModuleId;
            html += '<tr>';
            html += `<td style="position:sticky;left:0;background:var(--zt-bg-card, #fff);z-index:1;white-space:nowrap;padding:0.4rem 0.75rem">
                <span${isExternal ? ' style="font-style:italic;color:var(--zt-text-secondary)"' : ''}>${escapeHtml(user.prenom)} ${escapeHtml(user.nom)}</span>
                ${isExternal ? `<small style="margin-left:0.3rem;padding:0.1rem 0.35rem;border-radius:4px;background:var(--zt-accent-bg);font-size:0.68rem;color:var(--zt-text-muted)" title="Module principal: ${escapeHtml(user.home_module_code || '?')}">${escapeHtml(user.home_module_code || '?')}</small>` : ''}
            </td>`;

            for (const day of days) {
                const isToday = day.date === today;
                const a = assignIdx[user.id + '_' + day.date];
                let cellStyle = 'text-align:center;padding:0.3rem;';
                if (isToday) cellStyle += 'background:var(--zt-accent-bg);';

                if (a) {
                    const color = a.horaire_couleur || '#1a1a1a';
                    const inThisModule = a.module_id === activeModuleId;
                    html += `<td style="${cellStyle}">
                        <span style="display:inline-block;padding:0.15rem 0.45rem;border-radius:5px;font-weight:600;font-size:0.78rem;background:${escapeHtml(color)};color:#fff;${!inThisModule ? 'opacity:0.5;' : ''}" title="${escapeHtml(a.horaire_code)} ${a.heure_debut?.slice(0,5) || ''}–${a.heure_fin?.slice(0,5) || ''}${!inThisModule ? ' (' + escapeHtml(a.module_code || '') + ')' : ''}">${escapeHtml(a.horaire_code)}</span>
                        ${!inThisModule ? `<div style="font-size:0.6rem;color:var(--zt-text-muted);margin-top:1px">${escapeHtml(a.module_code || '')}</div>` : ''}
                    </td>`;
                } else {
                    html += `<td style="${cellStyle}"><span style="color:var(--zt-text-muted);font-size:0.75rem">—</span></td>`;
                }
            }

            html += '</tr>';
        }
    }

    body.innerHTML = html;
}

export function destroy() {}
