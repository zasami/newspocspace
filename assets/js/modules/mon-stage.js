import { apiPost } from '../helpers.js';

const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
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
                <button class="ss-btn-primary" id="btnNewReport"><i class="bi bi-plus-lg"></i> Nouveau report</button>
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
    const statusColors = {brouillon:'#9B9B9B', soumis:'#c99a3e', valide:'#2d7d32', a_refaire:'#c0392b'};
    const editable = r.statut === 'brouillon' || r.statut === 'a_refaire';
    return `<div class="ms-report">
        <div class="ms-report-head">
            <strong>${fmt(r.date_report)}</strong>
            <span class="ms-report-type">${r.type}</span>
            <span class="ms-report-status" style="background:${statusColors[r.statut]}">${r.statut}</span>
        </div>
        ${r.titre ? `<div class="ms-report-title">${esc(r.titre)}</div>` : ''}
        <div class="ms-report-content">${esc(r.contenu || '')}</div>
        ${r.commentaire_formateur ? `<div class="ms-report-comment"><strong>Commentaire ${r.valideur_nom ? 'de '+esc(r.valideur_nom) : ''}:</strong> ${esc(r.commentaire_formateur)}</div>` : ''}
        ${editable ? `
            <div class="ms-report-actions">
                <button class="ss-btn-secondary ms-btn-sm" data-edit-report='${esc(JSON.stringify({id:r.id, type:r.type, date:r.date_report, titre:r.titre||'', contenu:r.contenu||''}))}'><i class="bi bi-pencil"></i> Modifier</button>
                ${r.statut === 'brouillon' ? `<button class="ss-btn-secondary ms-btn-sm" data-del-report="${r.id}"><i class="bi bi-trash"></i> Supprimer</button>` : ''}
            </div>` : ''}
    </div>`;
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

function openReportModal(data) {
    document.getElementById('mstReportId').value = data?.id || '';
    document.getElementById('mstReportTitle').textContent = data ? 'Modifier report' : 'Nouveau report';
    document.getElementById('mstRType').value = data?.type || 'quotidien';
    document.getElementById('mstRDate').value = data?.date || new Date().toISOString().slice(0,10);
    document.getElementById('mstRTitre').value = data?.titre || '';
    document.getElementById('mstRContenu').value = data?.contenu || '';
    document.getElementById('mstReportModal').style.display = 'flex';
}

async function saveReport(action) {
    const data = {
        id: document.getElementById('mstReportId').value,
        type: document.getElementById('mstRType').value,
        date_report: document.getElementById('mstRDate').value,
        titre: document.getElementById('mstRTitre').value,
        contenu: document.getElementById('mstRContenu').value,
        action,
    };
    if (!data.contenu.trim()) { alert('Contenu obligatoire'); return; }
    const r = await apiPost('save_my_report', data);
    if (r.success) {
        document.getElementById('mstReportModal').style.display = 'none';
        load();
    }
}

function bind() {
    document.addEventListener('click', async (e) => {
        if (e.target.closest('#btnNewReport')) { openReportModal(null); return; }
        if (e.target.closest('[data-close-report]') || (e.target.classList.contains('ss-modal-backdrop') && e.target.closest('#mstReportModal'))) {
            document.getElementById('mstReportModal').style.display = 'none'; return;
        }
        if (e.target.closest('#btnSaveDraft')) { saveReport('save'); return; }
        if (e.target.closest('#btnSubmitReport')) {
            if (!confirm('Soumettre ce report au formateur ? Il ne sera plus modifiable après validation.')) return;
            saveReport('submit');
            return;
        }
        const editBtn = e.target.closest('[data-edit-report]');
        if (editBtn) {
            try { openReportModal(JSON.parse(editBtn.dataset.editReport)); } catch(_) {}
            return;
        }
        const delBtn = e.target.closest('[data-del-report]');
        if (delBtn) {
            if (!confirm('Supprimer ce brouillon ?')) return;
            const r = await apiPost('delete_my_report', { id: delBtn.dataset.delReport });
            if (r.success) load();
        }
    });
}

export function destroy() {}
