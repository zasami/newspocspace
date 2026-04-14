import { apiPost } from '../helpers.js';

const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
let currentStagId = null;
let currentData = null;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const fmt = (d) => d ? new Date(d).toLocaleDateString('fr-CH') : '—';

export function init() {
    load();
    bindModals();
}

async function load() {
    const r = await apiPost('get_my_stagiaires_as_formateur', {});
    if (!r.success) return;
    renderActifs(r.actifs || []);
    renderHistory(r.history || []);
}

function renderActifs(list) {
    const el = document.getElementById('msActifs');
    if (!list.length) { el.innerHTML = '<div class="ms-empty">Aucun stagiaire actif à votre charge.</div>'; return; }
    el.innerHTML = '<div class="ms-grid">' + list.map(s => {
        const pending = parseInt(s.reports_a_valider) || 0;
        const photo = s.photo ? `<img src="${esc(s.photo)}" alt="">` : `<span>${esc((s.prenom?.[0] || '') + (s.nom?.[0] || ''))}</span>`;
        return `<div class="ms-card" data-open="${s.id}">
            <div class="ms-card-avatar">${photo}</div>
            <div class="ms-card-body">
                <div class="ms-card-name">${esc(s.prenom + ' ' + s.nom)}</div>
                <div class="ms-card-type">${esc(TYPE_LABELS[s.type] || s.type)}${s.etage_nom ? ' • ' + esc(s.etage_nom) : ''}</div>
                <div class="ms-card-period">${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div>
                ${pending > 0 ? `<div class="ms-card-pending"><i class="bi bi-bell-fill"></i> ${pending} report(s) à valider</div>` : ''}
            </div>
        </div>`;
    }).join('') + '</div>';
}

function renderHistory(list) {
    const el = document.getElementById('msHistory');
    if (!list.length) { el.innerHTML = '<div class="text-muted small">Aucun stagiaire passé.</div>'; return; }
    el.innerHTML = '<div class="ms-grid">' + list.map(s => `
        <div class="ms-card ms-card-past" data-open="${s.id}">
            <div class="ms-card-body">
                <div class="ms-card-name">${esc(s.prenom + ' ' + s.nom)}</div>
                <div class="ms-card-type">${esc(TYPE_LABELS[s.type] || s.type)}</div>
                <div class="ms-card-period">${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div>
            </div>
        </div>`).join('') + '</div>';
}

async function openDetail(id) {
    currentStagId = id;
    const r = await apiPost('get_stagiaire_view_formateur', { id });
    if (!r.success) return;
    currentData = r;
    const s = r.stagiaire;
    document.getElementById('msDetailTitle').textContent = `${s.prenom} ${s.nom}`;
    const canEdit = r.can_edit;
    const body = document.getElementById('msDetailBody');
    body.innerHTML = `
        <div class="ms-tabs">
            <button class="ms-tab active" data-tab="infos">Infos</button>
            <button class="ms-tab" data-tab="reports">Reports (${r.reports.length})</button>
            <button class="ms-tab" data-tab="evals">Évaluations (${r.evaluations.length})</button>
            <button class="ms-tab" data-tab="objectifs">Objectifs (${r.objectifs.length})</button>
        </div>
        <div class="ms-tab-pane active" data-pane="infos">
            <div class="ms-info-row"><span class="ms-lbl">Type:</span> ${esc(TYPE_LABELS[s.type] || s.type)}</div>
            <div class="ms-info-row"><span class="ms-lbl">Période:</span> ${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div>
            <div class="ms-info-row"><span class="ms-lbl">Étage:</span> ${esc(s.etage_nom || '—')}</div>
            ${s.objectifs_generaux ? `<div class="ms-info-row"><span class="ms-lbl">Objectifs:</span> ${esc(s.objectifs_generaux)}</div>` : ''}
            ${!canEdit ? '<div class="ms-alert">Vous consultez en lecture seule — votre affectation n\'est plus active.</div>' : ''}
            ${canEdit ? `<button class="ss-btn-primary mt-3" id="btnNewEval"><i class="bi bi-plus-lg"></i> Nouvelle évaluation</button>` : ''}
        </div>
        <div class="ms-tab-pane" data-pane="reports">
            ${r.reports.length ? r.reports.map(rep => renderReport(rep, canEdit)).join('') : '<div class="text-muted small">Aucun report</div>'}
        </div>
        <div class="ms-tab-pane" data-pane="evals">
            ${r.evaluations.length ? r.evaluations.map(renderEval).join('') : '<div class="text-muted small">Aucune évaluation</div>'}
        </div>
        <div class="ms-tab-pane" data-pane="objectifs">
            ${r.objectifs.length ? r.objectifs.map(renderObj).join('') : '<div class="text-muted small">Aucun objectif défini par la RUV</div>'}
        </div>
    `;
    document.getElementById('msDetailModal').style.display = 'flex';
}

function renderReport(r, canEdit) {
    const statusColors = {brouillon:'#9B9B9B', soumis:'#c99a3e', valide:'#2d7d32', a_refaire:'#c0392b'};
    return `<div class="ms-report">
        <div class="ms-report-head">
            <strong>${fmt(r.date_report)}</strong>
            <span class="ms-report-type">${r.type}</span>
            <span class="ms-report-status" style="background:${statusColors[r.statut]}">${r.statut}</span>
        </div>
        ${r.titre ? `<div class="ms-report-title">${esc(r.titre)}</div>` : ''}
        <div class="ms-report-content">${esc(r.contenu || '')}</div>
        ${r.commentaire_formateur ? `<div class="ms-report-comment"><strong>Commentaire:</strong> ${esc(r.commentaire_formateur)}</div>` : ''}
        ${canEdit && r.statut === 'soumis' ? `
            <div class="ms-report-actions">
                <button class="ss-btn-primary ms-btn-sm" data-validate="${r.id}"><i class="bi bi-check"></i> Valider</button>
                <button class="ss-btn-secondary ms-btn-sm" data-refuse="${r.id}"><i class="bi bi-arrow-counterclockwise"></i> À refaire</button>
            </div>` : ''}
    </div>`;
}

function renderEval(e) {
    const notes = ['initiative','communication','connaissances','autonomie','savoir_etre','ponctualite']
        .map(k => `${k}: <strong>${e['note_' + k] || '—'}/5</strong>`).join(' • ');
    return `<div class="ms-eval-card">
        <div class="ms-eval-head">
            <strong>${fmt(e.date_eval)}</strong>
            <span class="ms-eval-periode">${e.periode}</span>
            <span class="text-muted small">par ${esc(e.formateur_prenom + ' ' + e.formateur_nom)}</span>
        </div>
        <div class="ms-eval-notes">${notes}</div>
        ${e.points_forts ? `<div class="small"><strong>+ </strong>${esc(e.points_forts)}</div>` : ''}
        ${e.points_amelioration ? `<div class="small"><strong>~ </strong>${esc(e.points_amelioration)}</div>` : ''}
        ${e.commentaire_general ? `<div class="small mt-1">${esc(e.commentaire_general)}</div>` : ''}
    </div>`;
}

function renderObj(o) {
    return `<div class="ms-obj">
        <strong>${esc(o.titre)}</strong>
        <span class="ms-obj-status ms-obj-${o.statut}">${o.statut}</span>
        ${o.description ? `<div class="small mt-1">${esc(o.description)}</div>` : ''}
    </div>`;
}

function openEvalModal() {
    document.getElementById('msEvalId').value = '';
    document.getElementById('msEvalStagId').value = currentStagId;
    document.getElementById('msEvalDate').value = new Date().toISOString().slice(0,10);
    ['msEvalPeriode'].forEach(i => document.getElementById(i).value = 'journaliere');
    ['msNInit','msNComm','msNConn','msNAuto','msNSav','msNPonc','msPFortes','msPAmelio','msComGen'].forEach(i => document.getElementById(i).value = '');
    document.getElementById('msEvalModal').style.display = 'flex';
}

async function saveEval() {
    const data = {
        id: document.getElementById('msEvalId').value,
        stagiaire_id: document.getElementById('msEvalStagId').value,
        date_eval: document.getElementById('msEvalDate').value,
        periode: document.getElementById('msEvalPeriode').value,
        note_initiative: document.getElementById('msNInit').value,
        note_communication: document.getElementById('msNComm').value,
        note_connaissances: document.getElementById('msNConn').value,
        note_autonomie: document.getElementById('msNAuto').value,
        note_savoir_etre: document.getElementById('msNSav').value,
        note_ponctualite: document.getElementById('msNPonc').value,
        points_forts: document.getElementById('msPFortes').value,
        points_amelioration: document.getElementById('msPAmelio').value,
        commentaire_general: document.getElementById('msComGen').value,
    };
    const r = await apiPost('save_stagiaire_evaluation', data);
    if (r.success) {
        document.getElementById('msEvalModal').style.display = 'none';
        openDetail(currentStagId);
    }
}

function bindModals() {
    document.addEventListener('click', async (e) => {
        const openBtn = e.target.closest('[data-open]');
        if (openBtn) { openDetail(openBtn.dataset.open); return; }
        if (e.target.closest('[data-close-ms]') || e.target.classList.contains('ss-modal-backdrop') && e.target.closest('#msDetailModal')) {
            document.getElementById('msDetailModal').style.display = 'none'; return;
        }
        if (e.target.closest('[data-close-eval]')) {
            document.getElementById('msEvalModal').style.display = 'none'; return;
        }
        const tabBtn = e.target.closest('.ms-tab');
        if (tabBtn) {
            tabBtn.parentElement.querySelectorAll('.ms-tab').forEach(b => b.classList.remove('active'));
            tabBtn.classList.add('active');
            const pane = tabBtn.dataset.tab;
            document.querySelectorAll('.ms-tab-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === pane));
            return;
        }
        if (e.target.closest('#btnNewEval')) { openEvalModal(); return; }
        if (e.target.closest('#btnSaveEval')) { saveEval(); return; }

        const vBtn = e.target.closest('[data-validate]');
        if (vBtn) {
            const r = await apiPost('validate_stagiaire_report', { id: vBtn.dataset.validate, statut: 'valide' });
            if (r.success) openDetail(currentStagId);
            return;
        }
        const rBtn = e.target.closest('[data-refuse]');
        if (rBtn) {
            const c = prompt('Commentaire (obligatoire):');
            if (!c) return;
            const r = await apiPost('validate_stagiaire_report', { id: rBtn.dataset.refuse, statut: 'a_refaire', commentaire: c });
            if (r.success) openDetail(currentStagId);
        }
    });
}

export function destroy() {}
