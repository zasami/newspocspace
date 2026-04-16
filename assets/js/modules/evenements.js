import { apiPost, escapeHtml } from '../helpers.js';

let detailModal = null;

export async function init() {
    const modalEl = document.getElementById('evDetailModal');
    if (modalEl) detailModal = new bootstrap.Modal(modalEl);

    // Tab switching
    document.querySelectorAll('.ev-user-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.ev-user-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const t = tab.dataset.tab;
            const sections = { upcoming: 'evUpcomingSection', mine: 'evMineSection', past: 'evPastSection' };
            Object.values(sections).forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
            const target = document.getElementById(sections[t]);
            if (target) target.style.display = '';
        });
    });

    // Card click → open detail
    document.querySelectorAll('.ev-card').forEach(card => {
        card.addEventListener('click', () => openDetail(card.dataset.eventId));
    });
}

export function destroy() {
    detailModal = null;
}

async function openDetail(id) {
    if (!detailModal) return;

    const titleEl = document.getElementById('evModalTitle');
    const bodyEl = document.getElementById('evModalBody');
    const footerEl = document.getElementById('evModalFooter');

    titleEl.textContent = 'Chargement...';
    bodyEl.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';
    footerEl.innerHTML = '';
    detailModal.show();

    const r = await apiPost('get_evenement_detail', { id });
    if (!r.success) {
        bodyEl.innerHTML = '<div class="text-danger text-center py-3">Erreur de chargement</div>';
        return;
    }

    const ev = r.evenement;
    const champs = r.champs || [];
    const inscrits = r.inscrits || [];
    const monInscription = r.mon_inscription;
    const mesValeurs = r.mes_valeurs || {};
    const isOpen = ev.statut === 'ouvert';
    const isInscrit = !!monInscription;
    const isFull = ev.max_participants && ev.nb_inscrits >= ev.max_participants;

    titleEl.textContent = ev.titre;

    // Build body
    let html = '';

    // Info
    html += '<div class="mb-3">';
    if (ev.description) {
        html += `<div class="mb-2" style="font-size:.9rem;line-height:1.6;white-space:pre-wrap">${escapeHtml(ev.description)}</div>`;
    }
    html += '<div class="d-flex flex-wrap gap-3 text-muted small">';
    if (ev.date_debut) {
        let dateStr = fmtDate(ev.date_debut);
        if (ev.date_fin && ev.date_fin !== ev.date_debut) dateStr += ' → ' + fmtDate(ev.date_fin);
        html += `<span><i class="bi bi-calendar3"></i> ${dateStr}</span>`;
    }
    if (ev.heure_debut) {
        html += `<span><i class="bi bi-clock"></i> ${ev.heure_debut.substring(0,5)}${ev.heure_fin ? ' - ' + ev.heure_fin.substring(0,5) : ''}</span>`;
    }
    if (ev.lieu) html += `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(ev.lieu)}</span>`;
    html += `<span><i class="bi bi-people-fill"></i> ${ev.nb_inscrits}${ev.max_participants ? '/' + ev.max_participants : ''} inscrits</span>`;
    html += '</div></div>';

    // Inscription form (if open and not inscrit)
    if (isOpen && !isInscrit && !isFull && champs.length > 0) {
        html += '<div class="border-top pt-3 mb-3"><h6 class="small fw-semibold mb-2"><i class="bi bi-pencil-square"></i> Formulaire d\'inscription</h6>';
        champs.forEach(c => {
            html += renderField(c, '');
        });
        html += '</div>';
    }

    // Already inscrit — show my values
    if (isInscrit && champs.length > 0) {
        html += '<div class="border-top pt-3 mb-3"><h6 class="small fw-semibold mb-2"><i class="bi bi-check-circle text-success"></i> Mon inscription</h6>';
        champs.forEach(c => {
            let val = mesValeurs[c.id] || '—';
            if (c.type === 'checkbox' && val !== '—') {
                try { val = JSON.parse(val).join(', '); } catch(e) {}
            }
            html += `<div class="ev-modal-field"><label>${escapeHtml(c.label)}</label><div class="small">${escapeHtml(val)}</div></div>`;
        });
        html += '</div>';
    }

    // Inscrits list
    if (inscrits.length > 0) {
        html += '<div class="border-top pt-3"><h6 class="small fw-semibold mb-2"><i class="bi bi-people"></i> Participants (' + inscrits.length + ')</h6>';
        html += '<div class="ev-inscrits-list">';
        inscrits.forEach(i => {
            html += `<span class="ev-inscrit-chip"><i class="bi bi-person-fill"></i> ${escapeHtml(i.prenom)} ${escapeHtml(i.nom)}</span>`;
        });
        html += '</div></div>';
    }

    bodyEl.innerHTML = html;

    // Footer buttons
    let footerHtml = '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>';
    if (isOpen && !isInscrit && !isFull) {
        footerHtml += `<button type="button" class="btn btn-sm" style="background:#bcd2cb;color:#2d4a43" id="btnInscrire"><i class="bi bi-check-lg"></i> S'inscrire</button>`;
    } else if (isOpen && isInscrit) {
        footerHtml += `<button type="button" class="btn btn-sm btn-outline-danger" id="btnDesinscrire"><i class="bi bi-x-lg"></i> Se désinscrire</button>`;
    } else if (isFull && !isInscrit) {
        footerHtml += '<span class="text-muted small">Complet</span>';
    }
    footerEl.innerHTML = footerHtml;

    // Attach button handlers
    const btnInscrire = document.getElementById('btnInscrire');
    if (btnInscrire) {
        btnInscrire.addEventListener('click', () => inscrire(ev.id, champs));
    }
    const btnDesinscrire = document.getElementById('btnDesinscrire');
    if (btnDesinscrire) {
        btnDesinscrire.addEventListener('click', () => desinscrire(ev.id));
    }
}

function renderField(champ, value) {
    const req = champ.obligatoire ? ' <span class="text-danger">*</span>' : '';
    let input = '';

    switch (champ.type) {
        case 'texte':
            input = `<input type="text" class="form-control form-control-sm" data-champ-id="${champ.id}" value="${escapeHtml(value)}">`;
            break;
        case 'textarea':
            input = `<textarea class="form-control form-control-sm" data-champ-id="${champ.id}" rows="2">${escapeHtml(value)}</textarea>`;
            break;
        case 'nombre':
            input = `<input type="number" class="form-control form-control-sm" data-champ-id="${champ.id}" value="${escapeHtml(value)}">`;
            break;
        case 'select':
            input = `<select class="form-select form-select-sm" data-champ-id="${champ.id}"><option value="">— Choisir —</option>`;
            (champ.options || []).forEach(o => {
                input += `<option value="${escapeHtml(o)}" ${value === o ? 'selected' : ''}>${escapeHtml(o)}</option>`;
            });
            input += '</select>';
            break;
        case 'radio':
            input = '<div class="d-flex flex-wrap gap-3">';
            (champ.options || []).forEach((o, i) => {
                const uid = 'r_' + champ.id + '_' + i;
                input += `<div class="form-check">
                    <input class="form-check-input" type="radio" name="radio_${champ.id}" id="${uid}" value="${escapeHtml(o)}" data-champ-id="${champ.id}" ${value === o ? 'checked' : ''}>
                    <label class="form-check-label small" for="${uid}">${escapeHtml(o)}</label>
                </div>`;
            });
            input += '</div>';
            break;
        case 'checkbox':
            input = '<div class="d-flex flex-wrap gap-3">';
            let checked = [];
            if (value) { try { checked = JSON.parse(value); } catch(e) { checked = [value]; } }
            (champ.options || []).forEach((o, i) => {
                const uid = 'c_' + champ.id + '_' + i;
                input += `<div class="form-check">
                    <input class="form-check-input" type="checkbox" id="${uid}" value="${escapeHtml(o)}" data-champ-id="${champ.id}" ${checked.includes(o) ? 'checked' : ''}>
                    <label class="form-check-label small" for="${uid}">${escapeHtml(o)}</label>
                </div>`;
            });
            input += '</div>';
            break;
    }

    return `<div class="ev-modal-field"><label>${escapeHtml(champ.label)}${req}</label>${input}</div>`;
}

function collectFormValues() {
    const valeurs = {};
    // Text, textarea, number, select
    document.querySelectorAll('#evModalBody [data-champ-id]').forEach(el => {
        const id = el.dataset.champId;
        if (el.type === 'radio') {
            if (el.checked) valeurs[id] = el.value;
        } else if (el.type === 'checkbox') {
            if (!valeurs[id]) valeurs[id] = [];
            if (el.checked) valeurs[id].push(el.value);
        } else {
            valeurs[id] = el.value;
        }
    });
    return valeurs;
}

async function inscrire(eventId, champs) {
    const valeurs = collectFormValues();
    const btn = document.getElementById('btnInscrire');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const r = await apiPost('inscrire_evenement', { evenement_id: eventId, valeurs });

    if (r.success) {
        detailModal.hide();
        // Reload page to refresh SSR data
        location.reload();
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> S\'inscrire';
        // Show error in modal
        const errEl = document.createElement('div');
        errEl.className = 'alert alert-danger alert-sm small py-2 mt-2';
        errEl.textContent = r.message || 'Erreur';
        document.getElementById('evModalBody').appendChild(errEl);
        setTimeout(() => errEl.remove(), 4000);
    }
}

async function desinscrire(eventId) {
    if (!confirm('Voulez-vous vous désinscrire de cet événement ?')) return;

    const btn = document.getElementById('btnDesinscrire');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const r = await apiPost('desinscrire_evenement', { evenement_id: eventId });
    if (r.success) {
        detailModal.hide();
        location.reload();
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Se désinscrire';
    }
}

function fmtDate(d) {
    if (!d) return '—';
    try { return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }); }
    catch(e) { return d; }
}
