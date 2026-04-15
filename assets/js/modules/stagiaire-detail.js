/**
 * Stagiaire detail — JS minimal (page SSR).
 * Le HTML + les données sont rendus par pages/stagiaire-detail.php.
 * Ce module gère uniquement les interactions :
 *  - Filtrer les reports par statut
 *  - Valider / demander correction d'un report
 *  - Évaluer niveau d'une tâche
 *  - Ouvrir + enregistrer le modal Nouvelle évaluation
 */
import { apiPost, ssConfirm, toast } from '../helpers.js';

let evalModal = null;

export function init() {
    document.addEventListener('click', handleClick);
    document.getElementById('sdReportFilter')?.addEventListener('change', filterReports);
}

async function handleClick(e) {
    // ── Évaluer tâche (niveau) ──
    const nivBtn = e.target.closest('.mst-niv-btn');
    if (nivBtn) {
        const container = nivBtn.closest('[data-rt-id]');
        const rtId = container?.dataset.rtId;
        const niveau = nivBtn.dataset.niveau;
        if (rtId && niveau) {
            const r = await apiPost('evaluer_tache_report', { id: rtId, niveau_formateur: niveau });
            if (r.success) {
                // Mise à jour visuelle immédiate
                container.querySelectorAll('.mst-niv-btn').forEach(b => b.classList.remove('active'));
                nivBtn.classList.add('active');
            } else {
                toast(r.message || 'Erreur');
            }
        }
        return;
    }

    // ── Valider un report ──
    const vBtn = e.target.closest('[data-validate]');
    if (vBtn) {
        const r = await apiPost('validate_stagiaire_report', { id: vBtn.dataset.validate, statut: 'valide' });
        if (r.success) {
            toast('Report validé');
            window.dispatchEvent(new PopStateEvent('popstate')); // refresh via routeur SPA
        } else {
            toast(r.message || 'Erreur');
        }
        return;
    }

    // ── Refuser un report (demander correction) ──
    const rBtn = e.target.closest('[data-refuse]');
    if (rBtn) {
        const comm = await askComment();
        if (comm === null) return;
        const r = await apiPost('validate_stagiaire_report', { id: rBtn.dataset.refuse, statut: 'a_refaire', commentaire: comm });
        if (r.success) {
            toast('Report renvoyé au stagiaire');
            window.dispatchEvent(new PopStateEvent('popstate'));
        } else {
            toast(r.message || 'Erreur');
        }
        return;
    }

    // ── Ouvrir modal nouvelle évaluation ──
    if (e.target.closest('#sdBtnNewEval')) {
        openEvalModal();
        return;
    }

    // ── Enregistrer évaluation ──
    if (e.target.closest('#sdBtnSaveEval')) {
        await saveEval();
    }
}

function filterReports(e) {
    const value = e.target.value;
    document.querySelectorAll('#sdReportsBody [data-statut]').forEach(card => {
        card.style.display = (!value || card.dataset.statut === value) ? '' : 'none';
    });
}

async function askComment() {
    const wrap = document.createElement('div');
    wrap.innerHTML = `
        <p class="small mb-2">Explique au stagiaire ce qui doit être corrigé ou complété :</p>
        <textarea class="form-control form-control-sm" rows="4" id="sdPromptText"
            placeholder="Ex. Précise ce que tu as appris lors des transferts..."></textarea>`;
    const ok = await ssConfirm(wrap, {
        title: 'Demander une correction',
        okText: 'Envoyer',
        cancelText: 'Annuler',
        variant: 'warning',
        icon: 'bi-arrow-counterclockwise',
    });
    if (!ok) return null;
    return document.getElementById('sdPromptText')?.value.trim() || '';
}

function openEvalModal() {
    const el = document.getElementById('sdEvalModal');
    if (!el) return;
    document.getElementById('sdEvalDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('sdEvalPeriode').value = 'journaliere';
    ['sdNInit','sdNComm','sdNConn','sdNAuto','sdNSav','sdNPonc','sdPFortes','sdPAmelio','sdComGen']
        .forEach(id => { const i = document.getElementById(id); if (i) i.value = ''; });
    if (!evalModal) evalModal = new bootstrap.Modal(el);
    evalModal.show();
}

async function saveEval() {
    const payload = {
        stagiaire_id: document.getElementById('sdEvalStagId').value,
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
        window.dispatchEvent(new PopStateEvent('popstate'));
    } else {
        toast(r.message || 'Erreur');
    }
}

export function destroy() {
    document.removeEventListener('click', handleClick);
    if (evalModal) { evalModal.hide(); evalModal = null; }
}
