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

// Mois minimum autorisé (= mois courant + 1). Les désirs ne peuvent être posés
// que pour un mois à l'avance minimum, donc on bloque la navigation en arrière.
function getMinMonth() {
    const now = new Date();
    const next = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
}

function shiftMonth(yyyyMm, delta) {
    const [y, m] = yyyyMm.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function init() {
    const ssrData = window.__SS_PAGE_DATA__ || {};

    // Mois par défaut = mois en cours (l'utilisateur peut naviguer vers mois+1 pour créer des désirs)
    const now = new Date();
    targetMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

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
            // Reset pour éviter que l'horaire choisi en permanent pollue le mode ponctuel
            selectedHoraireId = '';
            clearHoraireSelection();
            applyHoraireColorToSelection();
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

    // Navigation mois précédent / suivant (libre : on peut consulter l'historique)
    document.getElementById('calPrevBtn')?.addEventListener('click', async () => {
        targetMonth = shiftMonth(targetMonth, -1);
        selectedDates.clear();
        editingDesirId = null;
        await loadDesirs();
        buildCalendar();
    });
    document.getElementById('calNextBtn')?.addEventListener('click', async () => {
        targetMonth = shiftMonth(targetMonth, 1);
        selectedDates.clear();
        editingDesirId = null;
        await loadDesirs();
        buildCalendar();
    });

    // Build calendar + initial render from SSR data (le SSR renvoie déjà le mois en cours)
    buildCalendar();
    renderDesirs(ssrData.desirs || [], ssrData.max_desirs || 4);
    renderPermanents(ssrData.permanents || []);

    // Apply dynamic DB colors from data-bg attributes
    document.querySelectorAll('[data-bg]').forEach(el => { el.style.background = el.dataset.bg; });
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

    // Mode lecture seule si on consulte un mois antérieur au minimum autorisé (= mois courant + 1)
    const isReadOnly = targetMonth < getMinMonth();
    cal.classList.toggle('desir-cal-readonly', isReadOnly);
    // Masquer le bouton de soumission et afficher la bannière en mode lecture seule
    const submitBtn = document.getElementById('desirSubmitBtn');
    const roBanner = document.getElementById('desirReadOnlyBanner');
    if (submitBtn) submitBtn.style.display = isReadOnly ? 'none' : '';
    if (roBanner) roBanner.style.display = isReadOnly ? '' : 'none';

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

    // Re-marquer les jours qui ont déjà des désirs existants
    markExistingDesirs();

    // Click handlers
    cal.querySelectorAll('.desir-cal-day:not(.empty)').forEach(cell => {
        cell.addEventListener('click', () => {
            const date = cell.dataset.date;
            // En mode lecture seule : clic affiche le détail mais ne sélectionne pas
            if (isReadOnly) {
                showDesirDetails(date);
                return;
            }
            // Sélection unique : un clic remplace toujours la sélection précédente
            if (selectedDates.has(date)) {
                // Désélection du jour déjà sélectionné
                selectedDates.delete(date);
                editingDesirId = null;
                cell.classList.remove('selected');
                if (!cell.classList.contains('has-desir')) {
                    cell.style.removeProperty('background');
                    cell.style.removeProperty('border-color');
                    cell.querySelector('.desir-cal-day-chip')?.remove();
                }
                cell.style.removeProperty('--halo-color');
                document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Soumettre';
            } else {
                // Nettoyer sélection précédente
                selectedDates.clear();
                cal.querySelectorAll('.desir-cal-day.selected').forEach(c => {
                    c.classList.remove('selected');
                    c.style.removeProperty('--halo-color');
                    if (!c.classList.contains('has-desir')) {
                        c.style.removeProperty('background');
                        c.style.removeProperty('border-color');
                        c.querySelector('.desir-cal-day-chip')?.remove();
                    }
                });
                selectedDates.add(date);
                cell.classList.add('selected');

                // Si le jour a déjà un désir, basculer en mode édition de ce désir
                const existing = existingDesirs.find(d => d.date_souhaitee === date && d.statut !== 'refuse' && !d.permanent_id);
                if (existing) {
                    editingDesirId = existing.id;
                    document.getElementById('desirType').value = existing.type;
                    document.getElementById('desirDetail').value = existing.detail || '';
                    const isH = existing.type === 'horaire_special';
                    document.getElementById('desirHoraireGroup').style.display = isH ? '' : 'none';
                    document.getElementById('desirDetailGroup').style.display = isH ? '' : 'none';
                    selectedHoraireId = existing.horaire_type_id || '';
                    buildHorairesPicker();
                    const hiddenH = document.getElementById('desirHoraireId');
                    if (hiddenH) hiddenH.value = selectedHoraireId;
                    document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Modifier désir';
                } else {
                    editingDesirId = null;
                    document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Soumettre';
                }
            }
            applyHoraireColorToSelection();
            updateCountInfo();
            updateSubmitBtn();
            showDesirDetails(date);
        });
    });
}

// Modal de confirmation pour suppression
function showConfirmDelete({ title, message, onConfirm }) {
    const modal = document.getElementById('desirConfirmModal');
    if (!modal) { if (confirm(title)) onConfirm(); return; }
    document.getElementById('desirConfirmTitle').textContent = title || 'Confirmer ?';
    document.getElementById('desirConfirmMessage').textContent = message || '';
    modal.style.display = 'flex';

    const ok = document.getElementById('desirConfirmOk');
    const cancel = document.getElementById('desirConfirmCancel');

    const close = () => {
        modal.style.display = 'none';
        ok.removeEventListener('click', handleOk);
        cancel.removeEventListener('click', close);
        modal.removeEventListener('click', handleBg);
        document.removeEventListener('keydown', handleKey);
    };
    const handleOk = async () => {
        ok.disabled = true;
        try { await onConfirm(); } finally { ok.disabled = false; close(); }
    };
    const handleBg = (e) => { if (e.target === modal) close(); };
    const handleKey = (e) => { if (e.key === 'Escape') close(); };

    ok.addEventListener('click', handleOk);
    cancel.addEventListener('click', close);
    modal.addEventListener('click', handleBg);
    document.addEventListener('keydown', handleKey);
}

function updateStatsCards() {
    const maxDesirs = renderDesirs._maxDesirs || 4;
    const nonRefused = existingDesirs.filter(d => !d.permanent_id && d.statut !== 'refuse');
    const validated = nonRefused.filter(d => d.statut === 'valide').length;
    const pending = nonRefused.filter(d => d.statut === 'en_attente').length;

    // Désirs permanents comptent 1 slot du quota mensuel (valides OU en attente, sauf modifs)
    const perms = renderPermanents._cache || [];
    const permCountQuota = perms.filter(p =>
        !p.replaces_id && p.statut !== 'refuse' && p.is_active !== 0
    ).length;
    const permActifs = perms.filter(p => p.is_active && p.statut === 'valide' && !p.replaces_id).length;
    const permAttente = perms.filter(p => p.statut === 'en_attente' && !p.replaces_id).length;

    // Quota consommé total = ponctuels + permanents
    const consumed = nonRefused.length + permCountQuota;
    const remaining = Math.max(0, maxDesirs - consumed);

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('statRestants', `${remaining}/${maxDesirs}`);
    set('statRestantsSub',
        remaining === 0
            ? 'quota atteint'
            : `${nonRefused.length} ponctuel${nonRefused.length > 1 ? 's' : ''} + ${permCountQuota} perm.`
    );
    set('statEnAttente', pending);
    set('statValides', validated);
    set('statPermanents', permActifs);
    set('statPermanentsSub', permAttente > 0 ? `${permAttente} en attente` : 'actifs');
}

function updateCountInfo() {
    const el = document.getElementById('calCountInfo');
    if (!el) return;
    const n = selectedDates.size;
    const maxDesirs = renderDesirs._maxDesirs || 4;
    const ponctuels = existingDesirs.filter(d => !d.permanent_id && d.statut !== 'refuse').length;
    const perms = renderPermanents._cache || [];
    const permCount = perms.filter(p => !p.replaces_id && p.statut !== 'refuse' && p.is_active !== 0).length;
    const remaining = maxDesirs - ponctuels - permCount;
    if (n === 0) {
        el.innerHTML = `<span class="text-muted">${Math.max(0, remaining)} restant(s)</span>`;
    } else {
        const color = n > Math.max(0, remaining) ? 'color:#dc3545' : 'color:#1a1a1a';
        el.innerHTML = `<span style="${color};font-weight:600">${n} sélectionné(s)</span> / ${Math.max(0, remaining)} restant(s)`;
    }
}

function markExistingDesirs() {
    const cal = document.getElementById('desirCalendar');
    if (!cal) return;
    // Nettoyer les marques précédentes (classe + styles + chip)
    cal.querySelectorAll('.desir-cal-day').forEach(c => {
        c.classList.remove('has-desir');
        c.style.removeProperty('background');
        c.style.removeProperty('border-color');
        c.querySelector('.desir-cal-day-chip')?.remove();
    });

    // Map date → désir non refusé (le plus récent gagne en cas de doublon)
    const desirByDate = new Map();
    existingDesirs
        .filter(d => d.statut !== 'refuse')
        .forEach(d => { desirByDate.set(d.date_souhaitee, d); });

    cal.querySelectorAll('.desir-cal-day[data-date]').forEach(cell => {
        const d = desirByDate.get(cell.dataset.date);
        if (!d) return;
        cell.classList.add('has-desir');

        // Appliquer la couleur de l'horaire si disponible
        const color = d.horaire_couleur || null;
        if (color) {
            cell.style.background = hexToRgba(color, .2);
            cell.style.borderColor = color;
        }

        // Badge en haut à droite : code horaire, ou icône selon le type
        const chip = document.createElement('span');
        chip.className = 'desir-cal-day-chip';
        if (d.horaire_code) {
            chip.textContent = d.horaire_code;
            if (color) chip.style.background = color;
        } else if (d.type === 'jour_off') {
            chip.textContent = 'OFF';
            chip.style.background = '#6b7280';
        } else {
            chip.textContent = '•';
        }
        cell.appendChild(chip);
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
    panel.style.display = '';
    const body = panel;
    body.innerHTML = `
        <strong>Détails pour ${selectedDate}</strong>
        <ul style="padding-left:.9rem; margin: .3rem 0 0;">
            ${items.map(d => `<li>${d.type.replace('_', ' ')} ${d.horaire_code ? `(${escapeHtml(d.horaire_code)})` : ''} — ${statusBadge(d.statut)}</li>`).join('')}
        </ul>
    `;
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

    const fmtTime = (t) => (t || '').slice(0, 5);
    container.innerHTML = horaires.map(h => {
        const color = h.couleur || '#1a1a1a';
        const duree = h.duree_effective ? ` · ${h.duree_effective}h` : '';
        const tooltip = `${h.nom || ''}${duree}`;
        return `
        <button type="button"
                class="horaire-chip ${selectedHoraireId === h.id ? 'selected' : ''}"
                data-id="${h.id}"
                style="--chip-color:${escapeHtml(color)}"
                data-tooltip="${escapeHtml(tooltip)}"
                aria-label="${escapeHtml(h.nom || h.code || '')}">
            <span class="horaire-chip-code" style="background:${escapeHtml(color)}">${escapeHtml(h.code || '')}</span>
            <span class="horaire-chip-time">${fmtTime(h.heure_debut)}<br>${fmtTime(h.heure_fin)}</span>
        </button>`;
    }).join('');

    container.querySelectorAll('.horaire-chip').forEach(opt => {
        opt.addEventListener('click', () => {
            clearHoraireSelection();
            opt.classList.add('selected');
            selectedHoraireId = opt.dataset.id;
            document.getElementById('desirHoraireId').value = selectedHoraireId;
            applyHoraireColorToSelection();
            updateSubmitBtn();
        });
    });
}

// Applique la couleur + code de l'horaire choisi sur les cellules sélectionnées
// Règles :
//  - Cellule qui a déjà un désir existant (has-desir) → on NE CHANGE PAS son style/badge,
//    on dérive juste la couleur du halo depuis sa border-color existante
//  - Cellule "vierge" → on applique la couleur de l'horaire actuellement choisi
function applyHoraireColorToSelection() {
    const cal = document.getElementById('desirCalendar');
    if (!cal) return;
    const type = document.getElementById('desirType')?.value;
    const isHoraire = type === 'horaire_special';
    const h = isHoraire ? horaires.find(x => x.id === selectedHoraireId) : null;

    cal.querySelectorAll('.desir-cal-day.selected').forEach(cell => {
        if (cell.classList.contains('has-desir')) {
            // Conserver l'apparence existante, juste synchroniser le halo avec la border-color
            const computed = getComputedStyle(cell).borderTopColor;
            const rgb = cssColorToRgb(computed);
            if (rgb) cell.style.setProperty('--halo-color', rgb);
            return;
        }
        // Cellule vierge : nettoyer puis appliquer l'horaire actuel si horaire_special
        cell.style.removeProperty('background');
        cell.style.removeProperty('border-color');
        cell.style.removeProperty('--halo-color');
        cell.querySelector('.desir-cal-day-chip')?.remove();
        if (!h || !h.couleur) return;
        const rgb = hexToRgb(h.couleur);
        cell.style.background = `rgba(${rgb},.2)`;
        cell.style.borderColor = h.couleur;
        cell.style.setProperty('--halo-color', rgb);
        const chip = document.createElement('span');
        chip.className = 'desir-cal-day-chip';
        chip.textContent = h.code || '';
        chip.style.background = h.couleur;
        cell.appendChild(chip);
    });
}

function cssColorToRgb(color) {
    if (!color) return null;
    if (color.startsWith('#')) return hexToRgb(color);
    const m = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i.exec(color);
    if (m) return `${m[1]},${m[2]},${m[3]}`;
    return null;
}

function hexToRgb(hex) {
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
    if (!m) return '26,26,26';
    return `${parseInt(m[1],16)},${parseInt(m[2],16)},${parseInt(m[3],16)}`;
}
function hexToRgba(hex, alpha) {
    const rgb = hexToRgb(hex);
    return `rgba(${rgb},${alpha})`;
}

function clearHoraireSelection() {
    document.querySelectorAll('.horaire-chip.selected, .horaire-option.selected').forEach(o => o.classList.remove('selected'));
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
            btn.innerHTML = '<i class="bi bi-send"></i> Soumettre';
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
    updateStatsCards();

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
                actions.push(`<button class="btn btn-sm btn-desir-edit me-1" data-edit-desir="${d.id}" data-date="${escapeHtml(d.date_souhaitee)}" data-type="${escapeHtml(d.type)}" data-horaire="${escapeHtml(d.horaire_type_id || '')}" data-detail="${escapeHtml(d.detail || '')}" title="Modifier"><i class="bi bi-pencil"></i></button>`);
                actions.push(`<button class="btn btn-sm btn-desir-delete" data-delete-desir="${d.id}" title="Supprimer"><i class="bi bi-trash"></i></button>`);
            } else {
                actions.push(`<button class="btn btn-sm btn-desir-edit" data-edit-permanent="${d.permanent_id}" data-jour="${d.jour_semaine}" data-type="${escapeHtml(d.type)}" data-horaire="${escapeHtml(d.horaire_type_id || '')}" data-detail="${escapeHtml(d.detail || '')}" title="Modifier"><i class="bi bi-pencil"></i></button>`);
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
        btn.addEventListener('click', () => {
            showConfirmDelete({
                title: 'Supprimer ce désir ?',
                message: 'Ce désir sera définitivement supprimé. Cette action est irréversible.',
                onConfirm: async () => {
                    const res = await apiPost('delete_desir', { id: btn.dataset.deleteDesir });
                    if (res.success) { toast('Désir supprimé'); await loadDesirs(); }
                    else toast(res.message || 'Erreur');
                },
            });
        });
    });

    tbody.querySelectorAll('[data-edit-desir]').forEach(btn => {
        btn.addEventListener('click', async () => {
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

            // Si le désir est dans un autre mois, switcher le calendrier vers ce mois
            const desirMonth = date.slice(0, 7); // YYYY-MM
            if (desirMonth !== targetMonth) {
                targetMonth = desirMonth;
                await loadDesirs();
            }
            buildCalendar();

            // Scroll vers la cellule sélectionnée pour un feedback visuel clair
            const sel = document.querySelector(`.desir-cal-day[data-date="${date}"]`);
            if (sel) sel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

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
    renderPermanents._cache = perms || [];
    updateStatsCards();
    const tbody = document.getElementById('permanentsTableBody');
    if (!tbody) return;

    if (!perms.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:1.5rem">Aucun désir permanent</td></tr>';
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
        // Modifier : uniquement tant que le désir permanent n'est pas encore validé
        const editBtn = `<button class="btn btn-sm btn-desir-edit me-1" data-edit-perm="${p.id}" data-jour="${p.jour_semaine}" data-type="${escapeHtml(p.type)}" data-horaire="${escapeHtml(p.horaire_type_id || '')}" data-detail="${escapeHtml(p.detail || '')}" title="Modifier"><i class="bi bi-pencil"></i></button>`;
        const deleteBtn = `<button class="btn btn-sm btn-desir-delete" data-del-perm="${p.id}" title="Supprimer"><i class="bi bi-trash"></i></button>`;

        if (p.statut === 'en_attente' && p.replaces_id) {
            const ancienJour = joursComplets[p.ancien_jour_semaine] || '?';
            statut = '<span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107"><i class="bi bi-pencil-square"></i> Modification en attente</span>';
            actions = `<button class="btn btn-sm btn-desir-delete" data-cancel-modif="${p.id}" title="Annuler la modification"><i class="bi bi-x-lg"></i></button>`;
        } else if (p.statut === 'en_attente') {
            // En attente de validation → l'utilisateur peut encore éditer
            statut = '<span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107"><i class="bi bi-hourglass-split"></i> En attente</span>';
            actions = `${editBtn}${deleteBtn}`;
        } else if (p.is_active && p.statut === 'valide') {
            // Validé → seulement suppression possible
            statut = '<span class="badge badge-success">Actif</span>';
            if (p.has_pending_modification) {
                statut += ' <span class="badge" style="background:#fff3cd;color:#856404;font-size:0.68rem;border:1px solid #ffc107"><i class="bi bi-pencil-square"></i> Modif. en attente</span>';
            }
            actions = deleteBtn;
        } else if (p.statut === 'refuse') {
            statut = '<span class="badge badge-danger">Refusé</span>';
            if (p.commentaire_chef) {
                statut += ` <small class="text-muted" title="${escapeHtml(p.commentaire_chef)}"><i class="bi bi-chat-dots"></i></small>`;
            }
        } else {
            statut = '<span class="badge badge-secondary">Inactif</span>';
        }

        const detailCell = p.detail ? `<small>${escapeHtml(p.detail)}</small>` : '<small class="text-muted">—</small>';
        return `<tr>
          <td><strong>${jour}</strong></td>
          <td>${typeBadge}</td>
          <td>${horaireCell}</td>
          <td>${detailCell}</td>
          <td>${statut}</td>
          <td>${actions}</td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-del-perm]').forEach(btn => {
        btn.addEventListener('click', () => {
            showConfirmDelete({
                title: 'Supprimer ce désir permanent ?',
                message: 'Ce désir permanent sera définitivement supprimé. Cette action est irréversible.',
                onConfirm: async () => {
                    const res = await apiPost('delete_desir_permanent', { id: btn.dataset.delPerm });
                    if (res.success) { toast(res.message || 'Désir permanent supprimé'); await loadPermanents(); }
                    else toast(res.message || 'Erreur');
                },
            });
        });
    });

    tbody.querySelectorAll('[data-cancel-modif]').forEach(btn => {
        btn.addEventListener('click', () => {
            showConfirmDelete({
                title: 'Annuler cette modification ?',
                message: 'La proposition de modification sera supprimée.',
                onConfirm: async () => {
                    const res = await apiPost('delete_desir_permanent', { id: btn.dataset.cancelModif });
                    if (res.success) { toast('Proposition de modification annulée'); await loadPermanents(); }
                    else toast(res.message || 'Erreur');
                },
            });
        });
    });

    tbody.querySelectorAll('[data-edit-perm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const jour = parseInt(btn.dataset.jour);
            const type = btn.dataset.type;
            const detail = btn.dataset.detail || '';
            const horaire = btn.dataset.horaire || '';

            // Bascule en mode permanent
            currentMode = 'permanent';
            editingPermanentId = btn.dataset.editPerm;
            editingDesirId = null;

            document.querySelectorAll('.mode-toggle-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.mode-toggle-btn[data-mode="permanent"]')?.classList.add('active');
            document.getElementById('ponctuelFields').style.display = 'none';
            document.getElementById('permanentFields').style.display = '';

            // Pré-remplir champs
            document.getElementById('desirType').value = type;
            document.getElementById('desirDetail').value = detail;
            document.getElementById('desirJourSemaine').value = String(jour);

            // Afficher / cacher le picker d'horaire selon le type
            const isH = type === 'horaire_special';
            document.getElementById('desirHoraireGroup').style.display = isH ? '' : 'none';
            document.getElementById('desirDetailGroup').style.display = isH ? '' : 'none';

            // Pré-sélectionner l'horaire
            selectedHoraireId = horaire;
            buildHorairesPicker();
            const hiddenH = document.getElementById('desirHoraireId');
            if (hiddenH) hiddenH.value = selectedHoraireId;

            // Reset calendrier (pas utilisé en mode permanent)
            selectedDates.clear();

            document.getElementById('desirSubmitBtn').innerHTML = '<i class="bi bi-send"></i> Modifier permanent';
            updateSubmitBtn();

            // Scroll haut de la carte Nouveau désir pour voir le formulaire
            document.getElementById('murComposer') || document.querySelector('.mode-toggle-btn')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
}

export function destroy() {
    selectedDates.clear();
    selectedHoraireId = '';
    existingDesirs = [];
}
