import { apiPost } from '../helpers.js';

// Lazy-loaded rich editor (avoid blocking page init if load fails)
let _richEditor = null;
async function _getEditor() {
    if (_richEditor) return _richEditor;
    try {
        _richEditor = await import('../rich-editor.js');
    } catch (err) {
        console.warn('[mon-stage] rich-editor failed, falling back to textarea', err);
        _richEditor = null;
    }
    return _richEditor;
}

const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
const NIVEAU_LABELS = { acquis:'Acquis', en_cours:'En cours', non_acquis:'Non acquis', non_evalue:'À évaluer' };
let currentData = null;
let catalogue = [];
let editor = null;
let currentReportId = null;
let selectedTaches = new Map(); // tache_id -> { nb_fois, commentaire_stagiaire }
let reportModal = null;

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
    loadCatalogue();
}

async function loadCatalogue() {
    const r = await apiPost('get_stagiaire_taches_catalogue', {});
    if (r.success) catalogue = r.taches || [];
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
                <button class="btn btn-sm btn-primary" id="btnNewReport"><i class="bi bi-plus-lg"></i> Nouveau report</button>
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
    const taches = r.taches || [];
    const tachesSummary = taches.length ? renderTachesSummary(taches) : '';
    return `<div class="ms-report">
        <div class="ms-report-head">
            <strong>${fmt(r.date_report)}</strong>
            <span class="ms-report-type">${r.type}</span>
            <span class="ms-report-status" style="background:${statusColors[r.statut]}">${r.statut}</span>
            <span class="text-muted small ms-auto">${taches.length} tâche(s)</span>
        </div>
        ${r.titre ? `<div class="ms-report-title">${esc(r.titre)}</div>` : ''}
        ${tachesSummary}
        <div class="ms-report-content mst-content-html">${r.contenu || ''}</div>
        ${r.commentaire_formateur ? `<div class="ms-report-comment"><strong>Commentaire ${r.valideur_nom ? 'de '+esc(r.valideur_nom) : ''}:</strong> ${esc(r.commentaire_formateur)}</div>` : ''}
        ${editable ? `
            <div class="ms-report-actions">
                <button class="btn btn-sm btn-outline-secondary ms-btn-sm" data-edit-report="${r.id}"><i class="bi bi-pencil"></i> Modifier</button>
                ${r.statut === 'brouillon' ? `<button class="btn btn-sm btn-outline-secondary ms-btn-sm" data-del-report="${r.id}"><i class="bi bi-trash"></i> Supprimer</button>` : ''}
            </div>` : ''}
    </div>`;
}

function renderTachesSummary(taches) {
    const byCat = {};
    taches.forEach(t => {
        (byCat[t.categorie] = byCat[t.categorie] || []).push(t);
    });
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

async function openReportModal(existingReport) {
    currentReportId = existingReport?.id || '';
    selectedTaches = new Map();
    (existingReport?.taches || []).forEach(t => {
        selectedTaches.set(t.tache_id, {
            nb_fois: t.nb_fois || 1,
            commentaire_stagiaire: t.commentaire_stagiaire || ''
        });
    });

    document.getElementById('mstReportId').value = currentReportId;
    document.getElementById('mstReportTitle').textContent = existingReport ? 'Modifier report' : 'Nouveau report';
    document.getElementById('mstRType').value = existingReport?.type || 'quotidien';
    document.getElementById('mstRDate').value = existingReport?.date_report || new Date().toISOString().slice(0,10);
    document.getElementById('mstRTitre').value = existingReport?.titre || '';

    renderTachesChecklist();

    if (!reportModal) reportModal = new bootstrap.Modal(document.getElementById('mstReportModal'));
    reportModal.show();

    const wrap = document.getElementById('mstREditor');
    wrap.innerHTML = '';
    const rich = await _getEditor();
    if (rich) {
        if (editor) { rich.destroyEditor(editor); editor = null; }
        try {
            editor = await rich.createEditor(wrap, {
                placeholder: 'Décris ta journée, ce que tu as appris, les difficultés rencontrées, les questions…',
                content: existingReport?.contenu || '',
                mode: 'full',
            });
        } catch (err) {
            console.warn('[mon-stage] createEditor failed, using textarea', err);
            editor = null;
        }
    }
    if (!editor) {
        wrap.innerHTML = `<textarea id="mstRTextarea" class="ms-input" rows="10" placeholder="Décris ta journée...">${esc(existingReport?.contenu?.replace(/<[^>]+>/g, '') || '')}</textarea>`;
    }
}

function renderTachesChecklist() {
    const el = document.getElementById('mstTachesList');
    if (!catalogue.length) { el.innerHTML = '<div class="text-muted small">Aucun catalogue chargé.</div>'; return; }
    const byCat = {};
    catalogue.forEach(t => {
        (byCat[t.categorie] = byCat[t.categorie] || []).push(t);
    });
    let html = '';
    Object.keys(byCat).forEach(cat => {
        html += `<div class="mst-tcat-section">
            <div class="mst-tcat-title">${esc(cat)}</div>
            <div class="mst-tcat-items">`;
        byCat[cat].forEach(t => {
            const sel = selectedTaches.get(t.id);
            const checked = sel ? 'checked' : '';
            const nb = sel?.nb_fois || 1;
            html += `<label class="mst-tache-item ${checked ? 'is-checked' : ''}" data-tid="${t.id}">
                <input type="checkbox" ${checked} data-tache-check="${t.id}">
                <span class="mst-tache-name">${esc(t.nom)}</span>
                ${t.description ? `<span class="mst-tache-desc" title="${esc(t.description)}"><i class="bi bi-info-circle"></i></span>` : ''}
                <input type="number" class="mst-tache-nb" min="1" max="20" value="${nb}" data-tache-nb="${t.id}" title="Nombre de fois" ${checked ? '' : 'disabled'}>
            </label>`;
        });
        html += '</div></div>';
    });
    el.innerHTML = html;
}

async function saveReport(action) {
    let contenuHtml = '';
    if (editor && _richEditor) {
        contenuHtml = _richEditor.getHTML(editor);
    } else {
        const ta = document.getElementById('mstRTextarea');
        contenuHtml = ta ? ta.value : '';
    }
    const contenuText = contenuHtml.replace(/<[^>]+>/g, '').trim();
    if (!contenuText) { alert('Contenu du rapport obligatoire'); return; }

    const taches = [];
    selectedTaches.forEach((v, tacheId) => {
        taches.push({ tache_id: tacheId, nb_fois: v.nb_fois, commentaire_stagiaire: v.commentaire_stagiaire });
    });

    const data = {
        id: currentReportId || '',
        type: document.getElementById('mstRType').value,
        date_report: document.getElementById('mstRDate').value,
        titre: document.getElementById('mstRTitre').value,
        contenu: contenuHtml,
        taches,
        action,
    };
    const r = await apiPost('save_my_report', data);
    if (r.success) {
        if (editor && _richEditor) { _richEditor.destroyEditor(editor); editor = null; }
        reportModal?.hide();
        load();
    }
}

function bind() {
    document.addEventListener('click', async (e) => {
        if (e.target.closest('#btnNewReport')) { openReportModal(null); return; }
        if (e.target.closest('#btnSaveDraft')) { saveReport('save'); return; }
        if (e.target.closest('#btnSubmitReport')) {
            if (!confirm('Soumettre ce report au formateur ? Il ne sera plus modifiable après validation.')) return;
            saveReport('submit');
            return;
        }
        const editBtn = e.target.closest('[data-edit-report]');
        if (editBtn) {
            const rep = (currentData.reports || []).find(r => r.id === editBtn.dataset.editReport);
            if (rep) openReportModal(rep);
            return;
        }
        const delBtn = e.target.closest('[data-del-report]');
        if (delBtn) {
            if (!confirm('Supprimer ce brouillon ?')) return;
            const r = await apiPost('delete_my_report', { id: delBtn.dataset.delReport });
            if (r.success) load();
        }
    });

    document.addEventListener('change', (e) => {
        const chk = e.target.closest('[data-tache-check]');
        if (chk) {
            const tid = chk.dataset.tacheCheck;
            const label = chk.closest('.mst-tache-item');
            const nbInput = label.querySelector('[data-tache-nb]');
            if (chk.checked) {
                selectedTaches.set(tid, { nb_fois: parseInt(nbInput.value) || 1, commentaire_stagiaire: '' });
                label.classList.add('is-checked');
                nbInput.disabled = false;
            } else {
                selectedTaches.delete(tid);
                label.classList.remove('is-checked');
                nbInput.disabled = true;
            }
            return;
        }
        const nb = e.target.closest('[data-tache-nb]');
        if (nb) {
            const tid = nb.dataset.tacheNb;
            if (selectedTaches.has(tid)) {
                const cur = selectedTaches.get(tid);
                cur.nb_fois = parseInt(nb.value) || 1;
                selectedTaches.set(tid, cur);
            }
        }
    });
}

export function destroy() {
    if (editor && _richEditor) { _richEditor.destroyEditor(editor); editor = null; }
}
