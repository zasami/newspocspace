import { apiPost, escapeHtml, ssConfirm } from '../helpers.js';

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

    const headerEl = document.querySelector('#evDetailModal .modal-header');
    // Reset header
    headerEl.className = 'modal-header';
    headerEl.style.cssText = '';
    titleEl.textContent = 'Chargement...';
    titleEl.style.cssText = '';
    const closeBtn = headerEl.querySelector('[data-bs-dismiss="modal"]');
    if (closeBtn) closeBtn.className = 'btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center cuis-modal-close';

    bodyEl.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';
    footerEl.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>';
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

    // ── Header avec image hero ou simple ──
    if (ev.image_url) {
        headerEl.className = 'modal-header ev-hero-header';
        headerEl.style.cssText = `background-image: url('${ev.image_url.replace(/'/g, "\\'")}')`;
        titleEl.style.cssText = 'color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.5)';
        if (closeBtn) closeBtn.className = 'btn btn-sm ms-auto d-flex align-items-center justify-content-center ev-hero-close';
    }
    titleEl.textContent = ev.titre;

    let html = '';

    // ── Description ──
    if (ev.description) {
        html += `<div class="ev-description-box">${escapeHtml(ev.description)}</div>`;
    }

    // ── Infos en cartes ──
    html += '<div class="row g-2 mb-3">';
    if (ev.date_debut) {
        let dateStr = fmtDate(ev.date_debut);
        if (ev.date_fin && ev.date_fin !== ev.date_debut) dateStr += ' → ' + fmtDate(ev.date_fin);
        html += `<div class="col-sm-6"><div class="ev-info-card"><i class="bi bi-calendar3"></i><div><div class="ev-info-label">Date</div><div class="ev-info-value">${dateStr}</div></div></div></div>`;
    }
    if (ev.heure_debut) {
        const heure = ev.heure_debut.substring(0,5) + (ev.heure_fin ? ' - ' + ev.heure_fin.substring(0,5) : '');
        html += `<div class="col-sm-6"><div class="ev-info-card"><i class="bi bi-clock"></i><div><div class="ev-info-label">Horaire</div><div class="ev-info-value">${heure}</div></div></div></div>`;
    }
    if (ev.lieu) {
        html += `<div class="col-sm-6"><div class="ev-info-card"><i class="bi bi-geo-alt"></i><div><div class="ev-info-label">Lieu</div><div class="ev-info-value">${escapeHtml(ev.lieu)}</div></div></div></div>`;
    }
    html += `<div class="col-sm-6"><div class="ev-info-card"><i class="bi bi-people-fill"></i><div><div class="ev-info-label">Inscrits</div><div class="ev-info-value">${ev.nb_inscrits}${ev.max_participants ? ' / ' + ev.max_participants : ''}</div></div></div></div>`;
    html += '</div>';

    // ── Mon inscription — valeurs remplies ──
    if (isInscrit && champs.length > 0) {
        html += '<div class="ev-section"><h6 class="ev-section-title"><i class="bi bi-check-circle-fill text-success"></i> Mes réponses</h6>';
        html += '<div class="row g-2">';
        champs.forEach(c => {
            let val = mesValeurs[c.id] || '—';
            if (c.type === 'checkbox' && val !== '—') {
                try { val = JSON.parse(val).join(', '); } catch(e) {}
            }
            html += `<div class="col-sm-6"><div class="ev-val-card"><div class="ev-val-label">${escapeHtml(c.label)}</div><div class="ev-val-value">${escapeHtml(val)}</div></div></div>`;
        });
        html += '</div></div>';
    }

    // ── Formulaire d'inscription ──
    if (isOpen && !isInscrit && !isFull) {
        html += '<div class="ev-section"><h6 class="ev-section-title"><i class="bi bi-pencil-square"></i> Inscription</h6>';
        if (champs.length > 0) {
            champs.forEach(c => { html += renderField(c, ''); });
        } else {
            html += '<p class="small text-muted mb-0">Cliquez sur "S\'inscrire" pour confirmer votre participation.</p>';
        }
        html += '</div>';
    }

    // ── Complet / Fermé ──
    if (isFull && !isInscrit) {
        html += '<div class="ev-section text-center"><span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Complet</span></div>';
    }
    if (!isOpen && !isInscrit) {
        html += '<div class="ev-section text-center"><span class="badge bg-secondary"><i class="bi bi-lock"></i> Inscriptions fermées</span></div>';
    }

    // ── Liste des participants ──
    html += '<div class="ev-section">';
    html += `<h6 class="ev-section-title"><i class="bi bi-people"></i> Participants (${inscrits.length})</h6>`;
    if (inscrits.length > 0) {
        html += '<div class="ev-participants-list">';
        inscrits.forEach((ins, idx) => {
            const initials = ((ins.prenom || '')[0] || '') + ((ins.nom || '')[0] || '');
            const d = new Date(ins.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
            html += `<div class="ev-participant">
                <div class="ev-participant-rank">${idx + 1}</div>
                <div class="ev-participant-avatar">${ins.photo
                    ? `<img src="${escapeHtml(ins.photo)}" alt="">`
                    : escapeHtml(initials)
                }</div>
                <div class="ev-participant-name">${escapeHtml(ins.prenom)} ${escapeHtml(ins.nom)}</div>
                <div class="ev-participant-date">${d}</div>
            </div>`;
        });
        html += '</div>';
    } else {
        html += '<div class="text-center text-muted py-3 small"><i class="bi bi-people" style="font-size:1.3rem;opacity:.3"></i><div class="mt-1">Aucune inscription pour le moment</div></div>';
    }
    html += '</div>';

    bodyEl.innerHTML = html;

    // ── Footer ──
    let footerHtml = '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>';
    if (isOpen && !isInscrit && !isFull) {
        footerHtml += ` <button type="button" class="btn btn-sm" style="background:#bcd2cb;color:#2d4a43" id="btnInscrire"><i class="bi bi-check-lg"></i> S'inscrire</button>`;
    }
    if (isOpen && isInscrit) {
        footerHtml += ` <button type="button" class="btn btn-sm btn-outline-danger" id="btnDesinscrire"><i class="bi bi-x-lg"></i> Se désinscrire</button>`;
    }
    footerEl.innerHTML = footerHtml;

    // ── Attach handlers ──
    document.getElementById('btnInscrire')?.addEventListener('click', () => inscrire(ev.id, champs));
    document.getElementById('btnDesinscrire')?.addEventListener('click', () => desinscrire(ev.id));
}

// ── Render un champ de formulaire ──
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
        location.reload();
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> S\'inscrire';
        const errEl = document.createElement('div');
        errEl.className = 'alert alert-danger small py-2 mt-2';
        errEl.textContent = r.message || 'Erreur';
        document.getElementById('evModalBody').appendChild(errEl);
        setTimeout(() => errEl.remove(), 4000);
    }
}

async function desinscrire(eventId) {
    const ok = await ssConfirm('Voulez-vous vous désinscrire de cet événement ?', {
        title: 'Se désinscrire',
        okText: 'Se désinscrire',
        variant: 'danger',
        icon: 'bi-x-circle',
    });
    if (!ok) return;

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
