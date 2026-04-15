import { apiPost, ssConfirm } from '../helpers.js';

const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
const NIVEAU_LABELS = { acquis:'Acquis', en_cours:'En cours', non_acquis:'Non acquis', non_evalue:'À évaluer' };
let currentData = null;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const fmt = (d) => d ? new Date(d).toLocaleDateString('fr-CH') : '—';

export function init() {
    load();
    bind();
}

async function load() {
    const r = await apiPost('get_my_stage', {});
    if (!r.success) return;
    if (!r.stagiaire) {
        document.getElementById('mstContent').innerHTML = '<div class="mst-empty"><i class="bi bi-info-circle"></i> Aucun stage enregistré pour votre compte.</div>';
        return;
    }
    currentData = r;
    render();
}

function render() {
    const s = currentData.stagiaire;
    const reports = currentData.reports || [];
    const evals = currentData.evaluations || [];
    const objs = currentData.objectifs || [];
    const forms = currentData.formateurs_actifs || [];

    // ── Calcul des stats ──
    const nBrouillon = reports.filter(r => r.statut === 'brouillon').length;
    const nSoumis   = reports.filter(r => r.statut === 'soumis').length;
    const nValides  = reports.filter(r => r.statut === 'valide').length;
    const nRefaire  = reports.filter(r => r.statut === 'a_refaire').length;
    const objsAtteints = objs.filter(o => o.statut === 'atteint').length;
    const moyenne = calcMoyenne(evals);

    // Progression dates
    const today = new Date();
    const start = new Date(s.date_debut);
    const end = new Date(s.date_fin);
    const total = Math.max(1, (end - start) / 86400000);
    const done = Math.max(0, Math.min(total, (today - start) / 86400000));
    const progressPct = Math.round((done / total) * 100);
    const remainingDays = Math.max(0, Math.ceil((end - today) / 86400000));

    const el = document.getElementById('mstContent');
    el.innerHTML = `
        <!-- Stats cards -->
        <div class="row g-3 mb-3">
            ${statCard('Reports validés', nValides, 'bi-check-circle', 'teal', `${nSoumis + nValides + nRefaire + nBrouillon} au total`)}
            ${statCard('À valider', nSoumis, 'bi-clock-history', 'orange', nSoumis ? 'chez le formateur' : null)}
            ${statCard('À refaire', nRefaire, 'bi-arrow-counterclockwise', 'red', nRefaire ? 'corrections demandées' : null)}
            ${statCard('Objectifs atteints', objsAtteints, 'bi-bullseye', 'green', objs.length ? `sur ${objs.length}` : null)}
            ${statCard('Évaluations', evals.length, 'bi-clipboard-check', 'purple', moyenne ? `Moy. ${moyenne}/5` : 'aucune')}
            ${statCard('Jours restants', remainingDays, 'bi-calendar-event', 'neutral', `${progressPct}% du stage`)}
        </div>

        <!-- Fiche identité stage -->
        <div class="card mst-info-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Mon stage</h5>
                    <span class="ss-badge ss-badge-${s.statut === 'actif' ? 'actif' : (s.statut === 'prevu' ? 'prevu' : 'brouillon')}">${s.statut}</span>
                </div>
                <div class="mst-info-grid">
                    <div><span class="ms-lbl">Type</span><div>${esc(TYPE_LABELS[s.type] || s.type)}</div></div>
                    <div><span class="ms-lbl">Période</span><div>${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div></div>
                    <div><span class="ms-lbl">Étage</span><div>${esc(s.etage_nom || '—')}</div></div>
                    <div><span class="ms-lbl">RUV</span><div>${esc(s.ruv_nom || '—')}</div></div>
                    <div><span class="ms-lbl">Formateur principal</span><div>${esc(s.formateur_nom || '—')}</div></div>
                </div>
                ${s.objectifs_generaux ? `<div class="mt-2"><span class="ms-lbl">Objectifs du stage</span><div class="small">${esc(s.objectifs_generaux)}</div></div>` : ''}
                ${forms.length ? `<div class="mt-2"><span class="ms-lbl">Formateurs affectés actuellement</span><div class="small">${forms.map(f => esc(f.prenom+' '+f.nom)+' <span class="text-muted">('+f.role_formateur+')</span>').join(' · ')}</div></div>` : ''}
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Progression</span><span>${progressPct}%</span>
                    </div>
                    <div class="mst-progress"><div class="mst-progress-bar" style="width:${progressPct}%"></div></div>
                </div>
            </div>
        </div>

        <!-- Reports -->
        <div class="mst-section">
            <div class="mst-section-head">
                <h4><i class="bi bi-journal-text"></i> Mes reports <span class="text-muted small">(${reports.length})</span></h4>
                <button class="btn btn-sm btn-primary" data-link="report-edit"><i class="bi bi-plus-lg"></i> Nouveau report</button>
            </div>
            ${reports.length ? reports.map(renderReport).join('') : '<div class="card card-body text-muted small">Aucun report pour l\'instant — rédigez votre premier !</div>'}
        </div>

        ${objs.length ? `
        <div class="mst-section">
            <h4><i class="bi bi-bullseye"></i> Objectifs de stage</h4>
            ${objs.map(o => {
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
                </div></div>`;
            }).join('')}
        </div>` : ''}

        ${evals.length ? `
        <div class="mst-section">
            <h4><i class="bi bi-clipboard-check"></i> Évaluations <span class="text-muted small">(mi-stage / finale)</span></h4>
            ${evals.map(renderEval).join('')}
        </div>` : ''}
    `;
}

function statCard(label, value, icon, variant, sub = null) {
    return `<div class="col-sm-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-${variant}"><i class="bi ${icon}"></i></div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-value">${value}</div>
                <div class="stat-label">${esc(label)}${sub ? ` <span class="stat-sub">· ${esc(sub)}</span>` : ''}</div>
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

function renderReport(r) {
    const statutClass = {brouillon:'ss-badge-brouillon', soumis:'ss-badge-en_cours', valide:'ss-badge-acquis', a_refaire:'ss-badge-non_acquis'}[r.statut] || 'ss-badge-brouillon';
    const editable = r.statut === 'brouillon' || r.statut === 'a_refaire';
    const taches = r.taches || [];
    const tachesSummary = taches.length ? renderTachesSummary(taches) : '';
    return `<div class="ms-report">
        <div class="ms-report-head">
            <strong>${fmt(r.date_report)}</strong>
            <span class="ss-badge ss-badge-type">${r.type}</span>
            <span class="ss-badge ${statutClass}">${r.statut}</span>
            <span class="text-muted small ms-auto">${taches.length} tâche(s)</span>
        </div>
        ${r.titre ? `<div class="ms-report-title">${esc(r.titre)}</div>` : ''}
        ${tachesSummary}
        <div class="ms-report-content mst-content-html">${r.contenu || ''}</div>
        ${r.commentaire_formateur ? `<div class="ms-report-comment"><strong>Commentaire ${r.valideur_nom ? 'de '+esc(r.valideur_nom) : ''}:</strong> ${esc(r.commentaire_formateur)}</div>` : ''}
        ${editable ? `
            <div class="ms-report-actions">
                <button class="btn btn-sm btn-outline-secondary" data-edit-report="${r.id}"><i class="bi bi-pencil"></i> Modifier</button>
                ${r.statut === 'brouillon' ? `<button class="btn btn-sm btn-outline-secondary" data-del-report="${r.id}"><i class="bi bi-trash"></i> Supprimer</button>` : ''}
            </div>` : ''}
    </div>`;
}

function renderTachesSummary(taches) {
    const byCat = {};
    taches.forEach(t => { (byCat[t.categorie] = byCat[t.categorie] || []).push(t); });
    const niveauBadge = (n) => {
        const cls = {acquis:'mst-niv-acquis', en_cours:'mst-niv-encours', non_acquis:'mst-niv-nonacquis', non_evalue:'mst-niv-pending'}[n] || 'mst-niv-pending';
        return `<span class="mst-niv-badge ${cls}">${NIVEAU_LABELS[n] || n}</span>`;
    };
    let html = '<div class="mst-taches-summary">';
    Object.keys(byCat).forEach(cat => {
        html += `<div class="mst-tcat"><strong>${esc(cat)}</strong> ${byCat[cat].map(t =>
            `<span class="mst-tchip">${esc(t.tache_nom)}${t.nb_fois > 1 ? ` ×${t.nb_fois}` : ''} ${niveauBadge(t.niveau_formateur)}</span>`
        ).join(' ')}</div>`;
    });
    html += '</div>';
    return html;
}

function renderEval(e) {
    const notes = ['initiative','communication','connaissances','autonomie','savoir_etre','ponctualite']
        .map(k => `${k}: <strong>${e['note_' + k] || '—'}/5</strong>`).join(' • ');
    return `<div class="ms-eval-card">
        <div class="ms-eval-head"><strong>${fmt(e.date_eval)}</strong><span class="ms-eval-periode">${e.periode}</span></div>
        <div class="ms-eval-notes">${notes}</div>
        ${e.points_forts ? `<div class="small"><strong>+ </strong>${esc(e.points_forts)}</div>` : ''}
        ${e.points_amelioration ? `<div class="small"><strong>~ </strong>${esc(e.points_amelioration)}</div>` : ''}
        ${e.commentaire_general ? `<div class="small mt-1">${esc(e.commentaire_general)}</div>` : ''}
    </div>`;
}

function bind() {
    document.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-report]');
        if (editBtn) {
            history.pushState({}, '', `/spocspace/report-edit?id=${editBtn.dataset.editReport}`);
            window.dispatchEvent(new PopStateEvent('popstate'));
            return;
        }
        const delBtn = e.target.closest('[data-del-report]');
        if (delBtn) {
            const ok = await ssConfirm('Ce brouillon sera supprimé définitivement.', { title: 'Supprimer le brouillon ?', okText: 'Supprimer', variant: 'danger' });
            if (!ok) return;
            const r = await apiPost('delete_my_report', { id: delBtn.dataset.delReport });
            if (r.success) load();
        }
    });
}

export function destroy() {}
