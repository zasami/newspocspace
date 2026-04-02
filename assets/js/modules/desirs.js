/**
 * Désirs module — employee side
 * Calendar multi-select + horaire visual picker + permanent desires
 */
import { apiPost, toast, escapeHtml, formatDate, statusBadge } from '../helpers.js';

const joursComplets = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const joursShort = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
let horaires = [];
let selectedDates = new Set();
let selectedHoraireId = '';
let currentMode = 'ponctuel';
let targetMonth = ''; // YYYY-MM
let existingDesirs = [];
let editingDesirId = null;
let editingPermanentId = null;

export function init() {
    const ssrData = window.__SS_PAGE_DATA__ || {};

    // Compute target month (next month)
    const now = new Date();
    const next = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    targetMonth = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;

    // Use SSR horaires directly
    horaires = ssrData.horaires || [];
    buildHorairesPicker();

    // Mode toggle
    document.querySelectorAll('.mode-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mode-toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentMode = btn.dataset.mode;
            document.getElementById('ponctuelFields').style.display = currentMode === 'ponctuel' ? '' : 'none';
            document.getElementById('permanentFields').style.display = currentMode === 'permanent' ? '' : 'none';
            updateSubmitBtn();
        });
    });

    // Type change → show/hide horaire picker
    document.getElementById('desirType')?.addEventListener('change', (e) => {
        const isH = e.target.value === 'horaire_special';
        document.getElementById('desirHoraireGroup').style.display = isH ? '' : 'none';
        document.getElementById('desirDetailGroup').style.display = isH ? '' : 'none';
        if (!isH) { selectedHoraireId = ''; clearHoraireSelection(); }
        updateSubmitBtn();
    });

    // Submit
    document.getElementById('desirSubmitBtn')?.addEventListener('click', submitDesirs);

    // Build calendar + initial render from SSR data
    buildCalendar();
    renderDesirs(ssrData.desirs || [], ssrData.max_desirs || 4);
    renderPermanents(ssrData.permanents || []);
}

// ── Calendar ──
function buildCalendar() {
    const cal = document.getElementById('desirCalendar');
    if (!cal) return;

    const [year, month] = targetMonth.split('-').map(Number);
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDow = new Date(year, month - 1, 1).getDay(); // 0=dim
    // Shift so Monday=0
    const startOffset = (firstDow + 6) % 7;

    const monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    document.getElementById('calMonthLabel').textContent = `${monthNames[month - 1]} ${year}`;
    updateCountInfo();

    let html = '';
    // Headers: Lun Mar Mer Jeu Ven Sam Dim
    ['Lu','Ma','Me','Je','Ve','Sa','Di'].forEach(d => {
        html += `<div class="desir-cal-header">${d}</div>`;
    });

    // Empty cells before 1st
    for (let i = 0; i < startOffset; i++) {
        html += '<div class="desir-cal-day empty"></div>';
    }

    // Days
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dow = new Date(year, month - 1, d).getDay();
        const isWeekend = dow === 0 || dow === 6;
        const sel = selectedDates.has(dateStr) ? ' selected' : '';
        html += `<div class="desir-cal-day${isWeekend ? ' weekend' : ''}${sel}" data-date="${dateStr}">${d}</div>`;
    }

    cal.innerHTML = html;

    // Click handlers
    cal.querySelectorAll('.desir-cal-day:not(.empty)').forEach(cell => {
        cell.addEventListener('click', () => {
            const date = cell.dataset.date;
            if (selectedDates.has(date)) {
                selectedDates.delete(date);
                cell.classList.remove('selected');
            } else {
                selectedDates.add(date);
                cell.classList.add('selected');
            }
            updateCountInfo();
            updateSubmitBtn();
            showDesirDetails(date);
        });
    });
}

function updateCountInfo() {
    const el = document.getElementById('calCountInfo');
    if (!el) return;
    const n = selectedDates.size;
    const maxDesirs = renderDesirs._maxDesirs || 4;
    const remaining = maxDesirs - (existingDesirs.filter(d => !d.permanent_id && d.statut !== 'refuse').length);
    if (n === 0) {
        el.innerHTML = `<span class="text-muted">${Math.max(0, remaining)} restant(s)</span>`;
    } else {
        const color = n > Math.max(0, remaining) ? 'color:#dc3545' : 'color:#1a1a1a';
        el.innerHTML = `<span style="${color};font-weight:600">${n} sélectionné(s)</span> / ${Math.max(0, remaining)} restant(s)`;
    }
    
    // Update badge in page header
    const badge = document.getElementById('desirSoldeBadge');
    if (badge) {
        const totalRemaining = Math.max(0, remaining);
        badge.textContent = `${totalRemaining} jour${totalRemaining > 1 ? 's' : ''} restant${totalRemaining > 1 ? 's' : ''}`;
        badge.style.background = totalRemaining === 0 ? '#ffebee' : '#f0e9dd';
        badge.style.color = totalRemaining === 0 ? '#d32f2f' : '#1a1a1a';
    }
}

function markExistingDesirs() {
    const cal = document.getElementById('desirCalendar');
    if (!cal) return;
    // Clear previous marks
    cal.querySelectorAll('.desir-cal-day.has-desir').forEach(c => c.classList.remove('has-desir'));
    // Collect dates that already have a désir (not refused)
    const desirDates = new Set(
        existingDesirs
            .filter(d => d.statut !== 'refuse')
            .map(d => d.date_souhaitee)
    );
    cal.querySelectorAll('.desir-cal-day[data-date]').forEach(cell => {
        if (desirDates.has(cell.dataset.date)) {
            cell.classList.add('has-desir');
        }
    });
}

function showDesirDetails(date = null) {
    const panel = document.getElementById('desirDetailPanel');
    if (!panel) return;
    const selectedDate = date || (existingDesirs[0] ? existingDesirs[0].date_souhaitee : '');
    if (!selectedDate) {
        panel.style.display = 'none';
        return;
    }
    const items = existingDesirs.filter(d => d.date_souhaitee === selectedDate);
    if (!items.length) {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = 'block';
    panel.innerHTML = `
        <strong>Détails pour ${selectedDate}</strong>
        <ul style="padding-left:.9rem; margin: .3rem 0 0;">
            ${items.map(d => `<li>${d.type.replace('_', ' ')} ${d.horaire_code ? `(${escapeHtml(d.horaire_code)})` : ''} — ${statusBadge(d.statut)}</li>`).join('')}
        </ul>
    `;
    // Scroll to detail panel and briefly highlight it
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    panel.classList.remove('desir-detail-pulse');
    // Force reflow to restart animation
    void panel.offsetWidth;
    panel.classList.add('desir-detail-pulse');
}

// ── Horaire picker ──
function buildHorairesPicker() {
    const container = document.getElementById('horairesList');
    if (!container) return;

    container.innerHTML = horaires.map(h => `
        <div class="horaire-option ${selectedHoraireId === h.id ? 'selected' : ''}" data-id="${h.id}">
            <span class="horaire-badge" style="background:${escapeHtml(h.couleur || '#1a1a1a')}">${escapeHtml(h.code)}</span>
            <div>
                <div class="horaire-label">${escapeHtml(h.nom)}</div>
                <div class="horaire-time">${escapeHtml(h.heure_debut)} — ${escapeHtml(h.heure_fin)}${h.duree_effective ? ` (${h.duree_effective}h)` : ''}</div>
            </div>
        </div>
    `).join('');

    container.querySelectorAll('.horaire-option').forEach(opt => {
        opt.addEventListener('click', () => {
            clearHoraireSelection();
            opt.classList.add('selected');
            selectedHoraireId = opt.dataset.id;
            document.getElementById('desirHoraireId').value = selectedHoraireId;
            updateSubmitBtn();
        });
    });
}

function clearHoraireSelection() {
    document.querySelectorAll('.horaire-option.selected').forEach(o => o.classList.remove('selected'));
    selectedHoraireId = '';
    const hidden = document.getElementById('desirHoraireId');
    if (hidden) hidden.value = '';
}

// ── Submit ──
function updateSubmitBtn() {
    const btn = document.getElementById('desirSubmitBtn');
    if (!btn) return;
    const type = document.getElementById('desirType')?.value;
    const isHoraire = type === 'horaire_special';

    if (currentMode === 'ponctuel') {
        const hasDates = selectedDates.size > 0;
        const hasHoraire = !isHoraire || selectedHoraireId;
        btn.disabled = !(hasDates && hasHoraire);
        if (editingDesirId) {
            btn.innerHTML = '<i class="bi bi-send"></i> Modifier désir';
        } else {
            btn.innerHTML = selectedDates.size > 1
                ? `<i class="bi bi-send"></i> Soumettre ${selectedDates.size} désirs`
                : '<i class="bi bi-send"></i> Soumettre';
        }
    } else {
        const hasHoraire = !isHoraire || selectedHoraireId;
        btn.disabled = !hasHoraire;
        if (editingPermanentId) {
            btn.innerHTML = '<i class="bi bi-send"></i> Modifier permanent';
        } else {
            btn.innerHTML = '<i class="bi bi-send"></i> Créer désir permanent';
        }
    }
}

async function submitDesirs() {
    const type = document.getElementById('desirType')?.value || 'jour_off';
    const horaireId = selectedHoraireId || '';
    const detail = document.getElementById('desirDetail')?.value || '';
    const btn = document.getElementById('desirSubmitBtn');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    if (currentMode === 'permanent') {
        const jourSemaine = document.getElementById('desirJourSemaine')?.value;
        const payload = {
            jour_semaine: parseInt(jourSemaine),
            type,
            horaire_type_id: horaireId,
            detail,
        };

        const action = editingPermanentId ? 'update_desir_permanent' : 'submit_desir_permanent';
        if (editingPermanentId) payload.id = editingPermanentId;

        const res = await apiPost(action, payload);
        if (res.success) {
            toast(editingPermanentId ? 'Modification proposée — en attente de validation' : 'Désir permanent soumis — en attente de validation');
            editingPermanentId = null;
            btn.innerHTML = '<i class="bi bi-send"></i> Créer désir permanent';
            await Promise.all([loadDesirs(), loadPermanents()]);
        } else {
            toast(res.message || 'Erreur');
        }
    } else {
        // If editing one existing desire, update that one
        if (editingDesirId && selectedDates.size === 1) {
            const date = [...selectedDates][0];
            const res = await apiPost('update_desir', {
                id: editingDesirId,
                date_souhaitee: date,
                type,
                horaire_type_id: horaireId,
                detail,
            });
            if (res.success) {
                toast('Désir modifié');
                editingDesirId = null;
                btn.innerHTML = '<i class="bi bi-send"></i> Soumettre';
                selectedDates.clear();
                buildCalendar();
                await loadDesirs();
            } else {
                toast(res.message || 'Erreur');
            }
        } else {
            const dates = [...selectedDates].sort();
            let ok = 0, lastError = '';
            for (const date of dates) {
                const res = await apiPost('submit_desir', {
                    date_souhaitee: date,
                    type,
                    horaire_type_id: horaireId,
                    detail,
                });
                if (res.success) ok++;
                else lastError = res.message || 'Erreur';
            }
            if (ok > 0) {
                toast(`${ok} désir(s) soumis`);
                selectedDates.clear();
                buildCalendar();
                await loadDesirs();
            }
            if (lastError) toast(lastError);
        }
    }

    updateSubmitBtn();
}

// ── Load desires ──
async function loadDesirs() {
    const res = await apiPost('get_mes_desirs', { mois: targetMonth });
    renderDesirs(res.desirs || []);
}

function renderDesirs(desirs, maxDesirs) {
    if (maxDesirs !== undefined) {
        // Store max for updateCountInfo to use (use existing constant of 4 if not provided)
        renderDesirs._maxDesirs = maxDesirs;
    }
    existingDesirs = desirs;
    const tbody = document.getElementById('desirsTableBody');
    if (!tbody) return;

    const monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    const [y, m] = targetMonth.split('-').map(Number);
    const titleEl = document.getElementById('desirsListTitle');
    if (titleEl) titleEl.textContent = `Désirs — ${monthNames[m-1]} ${y}`;

    updateCountInfo();

    if (!existingDesirs.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Aucun désir pour ce mois</td></tr>';
        return;
    }

    tbody.innerHTML = existingDesirs.map(d => {
        const isPerm = !!d.permanent_id;
        const typeBadge = d.type === 'jour_off'
            ? '<span class="badge badge-info">Jour off</span>'
            : '<span class="badge badge-purple">Horaire</span>';
        const permIcon = isPerm
            ? ' <span class="badge" style="background:rgba(255,193,7,0.15);color:#d4a017;font-size:0.68rem;border:1px solid rgba(255,193,7,0.3)"><i class="bi bi-pin-angle-fill"></i></span>'
            : '';

        let horaireCell = '';
        if (d.horaire_code) {
            const c = d.horaire_couleur || '#9B51E0';
            horaireCell = `<span class="badge" style="background:${escapeHtml(c)};color:#fff">${escapeHtml(d.horaire_code)}</span>`;
        }

        const date = new Date(d.date_souhaitee + 'T00:00:00');
        const dateFmt = `${joursShort[date.getDay()]} ${date.getDate()}`;

        const actions = [];
        if (d.statut === 'en_attente') {
            if (!isPerm) {
                actions.push(`<button class="btn btn-outline-dark btn-sm" data-edit-desir="${d.id}" data-date="${escapeHtml(d.date_souhaitee)}" data-type="${escapeHtml(d.type)}" data-horaire="${escapeHtml(d.horaire_type_id || '')}" data-detail="${escapeHtml(d.detail || '')}"><i class="bi bi-pencil"></i></button>`);
                actions.push(`<button class="btn btn-danger btn-sm" data-delete-desir="${d.id}"><i class="bi bi-trash"></i></button>`);
            } else {
                actions.push(`<button class="btn btn-outline-dark btn-sm" data-edit-permanent="${d.permanent_id}" data-jour="${d.jour_semaine}" data-type="${escapeHtml(d.type)}" data-horaire="${escapeHtml(d.horaire_type_id || '')}" data-detail="${escapeHtml(d.detail || '')}"><i class="bi bi-pencil"></i></button>`);
            }
        }

        return `<tr class="desir-table-row" data-date-row="${escapeHtml(d.date_souhaitee)}">
          <td>${dateFmt}</td>
          <td>${typeBadge}${permIcon}</td>
          <td>${horaireCell}</td>
          <td><small>${escapeHtml(d.detail || '')}</small></td>
          <td>${statusBadge(d.statut)}</td>
          <td>${actions.join(' ')}</td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-delete-desir]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiPost('delete_desir', { id: btn.dataset.deleteDesir });
            if (res.success) { toast('Désir supprimé'); await loadDesirs(); }
            else toast(res.message || 'Erreur');
        });
    });

    tbody.querySelectorAll('[data-edit-desir]').forEach(btn => {
        btn.addEventListener('click', () => {
            const date = btn.dataset.date;
            const type = btn.dataset.type;
            const detail = btn.dataset.detail;
            const horaire = btn.dataset.horaire;

            currentMode = 'ponctuel';
            editingDesirId = btn.dataset.editDesir;
            document.querySelectorAll('.mode-toggle-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.mode-toggle-btn[data-mode="ponctuel"]').classList.add('active');
            document.getElementById('ponctuelFields').style.display = '';
            document.getElementById('permanentFields').style.display = 'none';
            document.getElementById('desirType').value = type;
            document.getElementById('desirDetail').value = detail;
            selectedDates = new Set([date]);
            buildCalendar();
            if (horaire) {
                selectedHoraireId = horaire;
                buildHorairesPicker();
                document.getElementById('desirHoraireGroup').style.display = type === 'horaire_special' ? '' : 'none';
                document.getElementById('desirDetailGroup').style.display = type === 'horaire_special' ? '' : 'none';
            }
            document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Modifier';
            updateSubmitBtn();
        });
    });

    tbody.querySelectorAll('[data-edit-permanent]').forEach(btn => {
        btn.addEventListener('click', () => {
            const jour = parseInt(btn.dataset.jour);
            const type = btn.dataset.type;
            const detail = btn.dataset.detail;
            const horaire = btn.dataset.horaire;

            currentMode = 'permanent';
            editingPermanentId = btn.dataset.editPermanent;
            document.querySelectorAll('.mode-toggle-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.mode-toggle-btn[data-mode="permanent"]').classList.add('active');
            document.getElementById('ponctuelFields').style.display = 'none';
            document.getElementById('permanentFields').style.display = '';
            document.getElementById('desirType').value = type;
            document.getElementById('desirDetail').value = detail;
            document.getElementById('desirJourSemaine').value = String(jour);
            selectedDates.clear();
            selectedHoraireId = horaire || '';
            buildHorairesPicker();
            document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Modifier permanent';
            updateSubmitBtn();
        });
    });

    tbody.querySelectorAll('tr[data-date-row]').forEach(row => {
        row.addEventListener('click', () => {
            showDesirDetails(row.dataset.dateRow);
        });
    });

    markExistingDesirs();
    showDesirDetails();
}

// ── Permanents ──
async function loadPermanents() {
    const res = await apiPost('get_mes_permanents');
    renderPermanents(res.permanents || []);
}

function renderPermanents(perms) {
    const tbody = document.getElementById('permanentsTableBody');
    if (!tbody) return;

    if (!perms.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding:1.5rem">Aucun désir permanent</td></tr>';
        return;
    }

    // Filter out refused modifications (keep refused new ones for history)
    const visiblePerms = perms.filter(p => !(p.statut === 'refuse' && p.replaces_id));

    tbody.innerHTML = visiblePerms.map(p => {
        const jour = joursComplets[p.jour_semaine] || '?';
        const typeBadge = p.type === 'jour_off'
            ? '<span class="badge badge-info">Jour off</span>'
            : '<span class="badge badge-purple">Horaire</span>';

        let horaireCell = '';
        if (p.horaire_code) {
            const c = p.horaire_couleur || '#9B51E0';
            horaireCell = `<span class="badge" style="background:${escapeHtml(c)};color:#fff">${escapeHtml(p.horaire_code)}</span>`;
        }

        let statut = '';
        let actions = '';
        if (p.statut === 'en_attente' && p.replaces_id) {
            // Pending modification proposal
            const ancienJour = joursComplets[p.ancien_jour_semaine] || '?';
            statut = '<span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107"><i class="bi bi-pencil-square"></i> Modification en attente</span>';
            actions = `<button class="btn btn-outline-secondary btn-sm" data-cancel-modif="${p.id}" title="Annuler la modification"><i class="bi bi-x-lg"></i></button>`;
        } else if (p.statut === 'en_attente') {
            statut = '<span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107"><i class="bi bi-hourglass-split"></i> En attente</span>';
            actions = `<button class="btn btn-danger btn-sm" data-del-perm="${p.id}"><i class="bi bi-trash"></i></button>`;
        } else if (p.is_active && p.statut === 'valide') {
            statut = '<span class="badge badge-success">Actif</span>';
            if (p.has_pending_modification) {
                statut += ' <span class="badge" style="background:#fff3cd;color:#856404;font-size:0.68rem;border:1px solid #ffc107"><i class="bi bi-pencil-square"></i> Modif. en attente</span>';
                actions = `<button class="btn btn-danger btn-sm" data-del-perm="${p.id}"><i class="bi bi-trash"></i></button>`;
            } else {
                actions = `<button class="btn btn-danger btn-sm" data-del-perm="${p.id}"><i class="bi bi-trash"></i></button>`;
            }
        } else if (p.statut === 'refuse') {
            statut = '<span class="badge badge-danger">Refusé</span>';
            if (p.commentaire_chef) {
                statut += ` <small class="text-muted" title="${escapeHtml(p.commentaire_chef)}"><i class="bi bi-chat-dots"></i></small>`;
            }
        } else {
            statut = '<span class="badge badge-secondary">Inactif</span>';
        }

        return `<tr>
          <td><strong>${jour}</strong></td>
          <td>${typeBadge}</td>
          <td>${horaireCell}</td>
          <td>${statut}</td>
          <td>${actions}</td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-del-perm]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiPost('delete_desir_permanent', { id: btn.dataset.delPerm });
            if (res.success) { toast(res.message || 'Désir permanent supprimé'); await loadPermanents(); }
            else toast(res.message || 'Erreur');
        });
    });

    tbody.querySelectorAll('[data-cancel-modif]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const res = await apiPost('delete_desir_permanent', { id: btn.dataset.cancelModif });
            if (res.success) { toast('Proposition de modification annulée'); await loadPermanents(); }
            else toast(res.message || 'Erreur');
        });
    });
}

export function destroy() {
    selectedDates.clear();
    selectedHoraireId = '';
    existingDesirs = [];
}
