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

    const el = document.getElementById('mstContent');
    el.innerHTML = `
        <div class="mst-info-card">
            <div class="mst-info-grid">
                <div><span class="ms-lbl">Type:</span> ${esc(TYPE_LABELS[s.type] || s.type)}</div>
                <div><span class="ms-lbl">Période:</span> ${fmt(s.date_debut)} → ${fmt(s.date_fin)}</div>
                <div><span class="ms-lbl">Étage:</span> ${esc(s.etage_nom || '—')}</div>
                <div><span class="ms-lbl">RUV:</span> ${esc(s.ruv_nom || '—')}</div>
                <div><span class="ms-lbl">Formateur principal:</span> ${esc(s.formateur_nom || '—')}</div>
                <div><span class="ms-lbl">Statut:</span> <span class="mst-statut mst-statut-${s.statut}">${s.statut}</span></div>
            </div>
            ${s.objectifs_generaux ? `<div class="mt-2"><span class="ms-lbl">Objectifs:</span> ${esc(s.objectifs_generaux)}</div>` : ''}
            ${forms.length ? `<div class="mt-2"><span class="ms-lbl">Formateurs affectés:</span> ${forms.map(f => esc(f.prenom+' '+f.nom)+' ('+f.role_formateur+')').join(', ')}</div>` : ''}
        </div>

        <div class="mst-section">
            <div class="mst-section-head">
                <h4><i class="bi bi-journal-text"></i> Mes reports</h4>
                <button class="btn btn-sm btn-primary" data-link="report-edit"><i class="bi bi-plus-lg"></i> Nouveau report</button>
            </div>
            ${reports.length ? reports.map(renderReport).join('') : '<div class="text-muted small">Aucun report pour l\'instant — rédigez votre premier !</div>'}
        </div>

        ${objs.length ? `
        <div class="mst-section">
            <h4><i class="bi bi-bullseye"></i> Objectifs de stage</h4>
            ${objs.map(o => `<div class="ms-obj">
                <strong>${esc(o.titre)}</strong>
                <span class="ms-obj-status ms-obj-${o.statut}">${o.statut}</span>
                ${o.description ? `<div class="small mt-1">${esc(o.description)}</div>` : ''}
            </div>`).join('')}
        </div>` : ''}

        ${evals.length ? `
        <div class="mst-section">
            <h4><i class="bi bi-clipboard-check"></i> Évaluations (mi-stage / finale)</h4>
            ${evals.map(renderEval).join('')}
        </div>` : ''}
    `;
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
