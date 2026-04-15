import { apiPost, escapeHtml, toast, ssConfirm } from '../helpers.js';

const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
const NIVEAU_LABELS = { acquis:'Acquis', en_cours:'En cours', non_acquis:'Non acquis', non_evalue:'À évaluer' };

let stagId = null;
let data = null;
let evalModal = null;

const esc = (s) => escapeHtml(String(s ?? ''));
const fmt = (d) => d ? new Date(d).toLocaleDateString('fr-CH') : '—';

export async function init(pageId, params = {}) {
    stagId = params?.id || new URLSearchParams(window.location.search).get('id') || '';
    if (!stagId) {
        document.getElementById('sdInfosBody').innerHTML = '<div class="text-danger">ID stagiaire manquant.</div>';
        return;
    }
    await load();
    bind();
}

async function load() {
    const r = await apiPost('get_stagiaire_view_formateur', { id: stagId });
    if (!r.success) {
        toast(r.message || 'Accès refusé');
        document.getElementById('sdInfosBody').innerHTML = `<div class="text-danger">${esc(r.message || 'Erreur')}</div>`;
        return;
    }
    data = r;
    render();
}

function render() {
    const s = data.stagiaire;
    const reports = data.reports || [];
    const evals = data.evaluations || [];
    const objs = data.objectifs || [];
    const canEdit = data.can_edit;

    // Title
    document.getElementById('sdTitle').textContent = `${s.prenom} ${s.nom}`;

    // Enable "New eval" button
    const btnEval = document.getElementById('sdBtnNewEval');
    btnEval.disabled = !canEdit;
    btnEval.title = canEdit ? '' : 'Votre affectation n\'est plus active';

    // Stats cards
    const pending = reports.filter(r => r.statut === 'soumis').length;
    const valides = reports.filter(r => r.statut === 'valide').length;
    const refaire = reports.filter(r => r.statut === 'a_refaire').length;
    const objsAtteints = objs.filter(o => o.statut === 'atteint').length;
    const moyenne = calcMoyenne(evals);

    document.getElementById('sdStats').innerHTML = `
        ${statCard('À valider', pending, 'bi-bell-fill', 'orange')}
        ${statCard('Reports validés', valides, 'bi-check-circle', 'teal')}
        ${statCard('À refaire', refaire, 'bi-arrow-counterclockwise', 'red')}
        ${statCard('Évaluations', evals.length, 'bi-clipboard-check', 'purple', moyenne ? `Moy. ${moyenne}/5` : null)}
        ${statCard('Objectifs atteints', objsAtteints, 'bi-bullseye', 'green', objs.length ? `sur ${objs.length}` : null)}
    `;

    // Infos tab
    const initials = ((s.prenom?.[0] || '') + (s.nom?.[0] || '')).toUpperCase();
    document.getElementById('sdInfosBody').innerHTML = `
        <div class="sd-profile-head">
            <div class="sd-avatar">${s.photo ? `<img src="${esc(s.photo)}" alt="">` : esc(initials)}</div>
            <div class="flex-grow-1">
                <div class="sd-profile-name">${esc(s.prenom + ' ' + s.nom)}</div>
                <div class="text-muted small">${esc(s.email || '')}${s.telephone ? ' • ' + esc(s.telephone) : ''}</div>
                <div class="mt-1"><span class="stg-type-badge">${esc(TYPE_LABELS[s.type] || s.type)}</span>
                    <span class="stg-badge stg-badge-${s.statut} ms-1">${esc(s.statut)}</span></div>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-md-6"><div class="sd-lbl">Période</div><div>${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div></div>
            <div class="col-md-6"><div class="sd-lbl">Étage</div><div>${esc(s.etage_nom || '—')}</div></div>
            <div class="col-md-12"><div class="sd-lbl">Objectifs généraux</div><div>${esc(s.objectifs_generaux || '—')}</div></div>
        </div>
        ${!canEdit ? '<div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-info-circle"></i> Vous consultez en lecture seule — votre affectation n\'est plus active aujourd\'hui.</div>' : ''}
    `;

    // Reports tab — full render
    renderReports(document.getElementById('sdReportFilter')?.value || '');

    // Evals tab
    document.getElementById('sdEvalsBody').innerHTML = evals.length
        ? evals.map(renderEval).join('')
        : '<div class="text-muted small card card-body">Aucune évaluation</div>';

    // Objectifs tab
    document.getElementById('sdObjectifsBody').innerHTML = objs.length
        ? objs.map(renderObj).join('')
        : '<div class="text-muted small card card-body">Aucun objectif défini par la RUV</div>';
}

function statCard(label, value, icon, variant, sub = null) {
    return `<div class="col-sm-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-${variant}"><i class="bi ${icon}"></i></div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-value">${value}</div>
                <div class="stat-label">${esc(label)}${sub ? ` <span class="sd-stat-sub">· ${esc(sub)}</span>` : ''}</div>
            </div>
        </div>
    </div>`;
}

function calcMoyenne(evals) {
    if (!evals.length) return null;
    const keys = ['note_initiative','note_communication','note_connaissances','note_autonomie','note_savoir_etre','note_ponctualite'];
    let sum = 0, n = 0;
    evals.forEach(e => keys.forEach(k => { if (e[k]) { sum += Number(e[k]); n++; } }));
    return n ? (sum / n).toFixed(1) : null;
}

function renderReports(filter) {
    const el = document.getElementById('sdReportsBody');
    const reports = (data.reports || []).filter(r => !filter || r.statut === filter);
    if (!reports.length) { el.innerHTML = '<div class="text-muted small card card-body">Aucun report</div>'; return; }
    el.innerHTML = reports.map(r => renderReport(r, data.can_edit)).join('');
}

function renderReport(r, canEdit) {
    const statutClass = {brouillon:'ss-badge-brouillon', soumis:'ss-badge-en_cours', valide:'ss-badge-acquis', a_refaire:'ss-badge-non_acquis'}[r.statut] || 'ss-badge-brouillon';
    const taches = r.taches || [];
    return `<div class="card mb-2 sd-report">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <strong>${fmt(r.date_report)}</strong>
                <span class="ss-badge ss-badge-type">${r.type}</span>
                <span class="ss-badge ${statutClass}">${r.statut}</span>
                <span class="text-muted small ms-auto">${taches.length} tâche(s)</span>
            </div>
            ${r.titre ? `<div class="fw-semibold small mb-1">${esc(r.titre)}</div>` : ''}
            ${taches.length ? renderTachesEval(taches, canEdit) : ''}
            <div class="sd-report-content mst-content-html">${r.contenu || ''}</div>
            ${r.commentaire_formateur ? `<div class="ms-report-comment mt-2"><strong>Commentaire:</strong> ${esc(r.commentaire_formateur)}</div>` : ''}
            ${canEdit && r.statut === 'soumis' ? `
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-primary" data-validate="${r.id}"><i class="bi bi-check"></i> Valider</button>
                    <button class="btn btn-sm btn-outline-secondary" data-refuse="${r.id}"><i class="bi bi-arrow-counterclockwise"></i> À refaire</button>
                </div>` : ''}
        </div>
    </div>`;
}

function renderTachesEval(taches, canEdit) {
    const byCat = {};
    taches.forEach(t => { (byCat[t.categorie] = byCat[t.categorie] || []).push(t); });
    let html = '<div class="mst-taches-eval">';
    Object.keys(byCat).forEach(cat => {
        html += `<div class="mst-tcat-eval"><div class="mst-tcat-title-eval">${esc(cat)}</div>`;
        byCat[cat].forEach(t => {
            const cls = {acquis:'mst-niv-acquis', en_cours:'mst-niv-encours', non_acquis:'mst-niv-nonacquis', non_evalue:'mst-niv-pending'}[t.niveau_formateur] || 'mst-niv-pending';
            html += `<div class="mst-tache-eval-row">
                <div class="mst-tache-eval-name">
                    <i class="bi bi-check2-square"></i> ${esc(t.tache_nom)}
                    ${t.nb_fois > 1 ? `<span class="text-muted">×${t.nb_fois}</span>` : ''}
                </div>
                ${canEdit ? `
                    <div class="mst-niv-buttons" data-rt-id="${t.id}">
                        ${['acquis','en_cours','non_acquis','non_evalue'].map(n =>
                            `<button class="mst-niv-btn mst-niv-btn-${n} ${t.niveau_formateur === n ? 'active' : ''}" data-niveau="${n}" title="${NIVEAU_LABELS[n]}"></button>`
                        ).join('')}
                    </div>
                ` : `<span class="mst-niv-badge ${cls}">${NIVEAU_LABELS[t.niveau_formateur] || t.niveau_formateur}</span>`}
            </div>`;
        });
        html += '</div>';
    });
    html += '</div>';
    return html;
}

function renderEval(e) {
    const notes = ['initiative','communication','connaissances','autonomie','savoir_etre','ponctualite']
        .map(k => `${k}: <strong>${e['note_' + k] || '—'}/5</strong>`).join(' • ');
    return `<div class="card mb-2"><div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <strong>${fmt(e.date_eval)}</strong>
            <span class="ss-badge ss-badge-type">${e.periode}</span>
            <span class="text-muted small ms-auto">par ${esc(e.formateur_prenom + ' ' + e.formateur_nom)}</span>
        </div>
        <div class="small mb-1">${notes}</div>
        ${e.points_forts ? `<div class="small"><strong>+ </strong>${esc(e.points_forts)}</div>` : ''}
        ${e.points_amelioration ? `<div class="small"><strong>~ </strong>${esc(e.points_amelioration)}</div>` : ''}
        ${e.commentaire_general ? `<div class="small mt-1">${esc(e.commentaire_general)}</div>` : ''}
    </div></div>`;
}

function renderObj(o) {
    const map = {en_cours:'ss-badge-prevu', atteint:'ss-badge-acquis', non_atteint:'ss-badge-non_acquis', abandonne:'ss-badge-brouillon'};
    return `<div class="card mb-2"><div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong>${esc(o.titre)}</strong>
                <span class="ss-badge ${map[o.statut] || 'ss-badge-brouillon'} ms-2">${o.statut}</span>
                ${o.date_cible ? `<span class="text-muted small ms-2">Cible: ${fmt(o.date_cible)}</span>` : ''}
            </div>
        </div>
        ${o.description ? `<div class="small mt-1">${esc(o.description)}</div>` : ''}
        ${o.commentaire_ruv ? `<div class="alert alert-light border mt-2 mb-0 small">${esc(o.commentaire_ruv)}</div>` : ''}
    </div></div>`;
}

function openEvalModal() {
    document.getElementById('sdEvalId').value = '';
    document.getElementById('sdEvalDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('sdEvalPeriode').value = 'journaliere';
    ['sdNInit','sdNComm','sdNConn','sdNAuto','sdNSav','sdNPonc','sdPFortes','sdPAmelio','sdComGen'].forEach(i => document.getElementById(i).value = '');
    if (!evalModal) evalModal = new bootstrap.Modal(document.getElementById('sdEvalModal'));
    evalModal.show();
}

async function saveEval() {
    const payload = {
        id: document.getElementById('sdEvalId').value,
        stagiaire_id: stagId,
        date_eval: document.getElementById('sdEvalDate').value,
        periode: document.getElementById('sdEvalPeriode').value,
        note_initiative: document.getElementById('sdNInit').value,
        note_communication: document.getElementById('sdNComm').value,
        note_connaissances: document.getElementById('sdNConn').value,
        note_autonomie: document.getElementById('sdNAuto').value,
        note_savoir_etre: document.getElementById('sdNSav').value,
        note_ponctualite: document.getElementById('sdNPonc').value,
        points_forts: document.getElementById('sdPFortes').value,
        points_amelioration: document.getElementById('sdPAmelio').value,
        commentaire_general: document.getElementById('sdComGen').value,
    };
    const r = await apiPost('save_stagiaire_evaluation', payload);
    if (r.success) {
        toast(r.message || 'Évaluation enregistrée');
        evalModal?.hide();
        load();
    } else {
        toast(r.message || 'Erreur');
    }
}

function bind() {
    document.addEventListener('click', async (e) => {
        const nivBtn = e.target.closest('.mst-niv-btn');
        if (nivBtn) {
            const container = nivBtn.closest('[data-rt-id]');
            const rtId = container?.dataset.rtId;
            const niveau = nivBtn.dataset.niveau;
            if (rtId && niveau) {
                const r = await apiPost('evaluer_tache_report', { id: rtId, niveau_formateur: niveau });
                if (r.success) load();
            }
            return;
        }
        const vBtn = e.target.closest('[data-validate]');
        if (vBtn) {
            const r = await apiPost('validate_stagiaire_report', { id: vBtn.dataset.validate, statut: 'valide' });
            if (r.success) { toast('Report validé'); load(); }
            return;
        }
        const rBtn = e.target.closest('[data-refuse]');
        if (rBtn) {
            const comm = await askComment('Commentaire pour demander une correction');
            if (comm === null) return;
            const r = await apiPost('validate_stagiaire_report', { id: rBtn.dataset.refuse, statut: 'a_refaire', commentaire: comm });
            if (r.success) { toast('Report renvoyé au stagiaire'); load(); }
            return;
        }
        if (e.target.closest('#sdBtnNewEval')) { openEvalModal(); return; }
        if (e.target.closest('#sdBtnSaveEval')) { saveEval(); return; }
    });

    document.getElementById('sdReportFilter')?.addEventListener('change', (e) => renderReports(e.target.value));
}

async function askComment(title) {
    const wrap = document.createElement('div');
    wrap.innerHTML = `<p class="small mb-2">Explique au stagiaire ce qui doit être corrigé ou complété :</p>
        <textarea class="form-control form-control-sm" rows="4" id="sdPromptText" placeholder="Ex. Précise ce que tu as appris lors des transferts..."></textarea>`;
    const ok = await ssConfirm(wrap, {
        title,
        okText: 'Envoyer',
        cancelText: 'Annuler',
        variant: 'warning',
        icon: 'bi-arrow-counterclockwise',
    });
    if (!ok) return null;
    return document.getElementById('sdPromptText')?.value.trim() || '';
}

export function destroy() {}
