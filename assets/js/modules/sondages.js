/**
 * Sondages module - Employee view
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

const _c = [];
function on(el, ev, fn, o) { if (!el) return; el.addEventListener(ev, fn, o); _c.push(() => el.removeEventListener(ev, fn, o)); }

let selectedId = null;

export async function init() {
    await loadList();
}

async function loadList() {
    const container = document.getElementById('sondageListContainer');
    const result = await apiPost('get_sondages_ouverts', {});

    if (!result?.success) {
        container.innerHTML = '<div class="split-view-loading text-danger">Erreur de chargement</div>';
        return;
    }

    const list = result.list || [];
    if (list.length === 0) {
        container.innerHTML = '<div class="split-view-loading">Aucun sondage ouvert</div>';
        return;
    }

    container.innerHTML = list.map(s => {
        const date = new Date(s.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        const done = parseInt(s.nb_repondu) >= parseInt(s.nb_questions) && parseInt(s.nb_questions) > 0;
        return `<div class="split-list-item ${s.id === selectedId ? 'selected' : ''}" data-sid="${s.id}">
            <div class="d-flex justify-content-between align-items-center">
                <div class="split-list-item-title">${escapeHtml(s.titre)}</div>
                ${done
                    ? '<span class="badge bg-success sondage-status-badge"><i class="bi bi-check-circle"></i> Répondu</span>'
                    : '<span class="badge bg-warning text-dark sondage-status-badge">À répondre</span>'}
            </div>
            <div class="split-list-item-meta">
                <span>${escapeHtml(s.prenom || '')} ${escapeHtml(s.nom || '')} · ${date}</span>
                <span>${s.nb_questions} question${s.nb_questions > 1 ? 's' : ''}</span>
            </div>
        </div>`;
    }).join('');

    container.querySelectorAll('.split-list-item').forEach(item => {
        on(item, 'click', () => selectSondage(item.dataset.sid));
    });

    // Auto-select the latest sondage if none selected
    if (!selectedId && list.length > 0) {
        selectSondage(list[0].id);
    }
}

async function selectSondage(id) {
    selectedId = id;
    document.querySelectorAll('#sondageListContainer .split-list-item').forEach(el =>
        el.classList.toggle('selected', el.dataset.sid === id));

    const panel = document.getElementById('sondageDetailPanel');
    panel.innerHTML = '<div class="split-view-loading"><span class="spinner-border spinner-border-sm"></span></div>';

    const result = await apiPost('get_sondage_detail', { id });
    if (!result?.success) {
        panel.innerHTML = '<div class="split-view-loading text-danger">Erreur de chargement</div>';
        return;
    }

    displaySondage(result.sondage, result.questions || [], result.mes_reponses || {});
}

function displaySondage(sondage, questions, mesReponses) {
    const panel = document.getElementById('sondageDetailPanel');
    const isClosed = sondage.statut === 'ferme';

    let questionsHtml = questions.map((q, idx) => {
        const existing = mesReponses[q.id] || '';
        let inputHtml = '';

        if (q.type === 'texte_libre') {
            inputHtml = `<textarea class="form-control form-control-sm q-answer" data-qid="${q.id}" rows="2" 
                placeholder="Votre réponse..." ${isClosed ? 'disabled' : ''}>${escapeHtml(existing)}</textarea>`;
        } else if (q.type === 'choix_unique') {
            const options = q.options || [];
            inputHtml = options.map(o => {
                const chosen = existing === o ? 'chosen' : '';
                return `<div class="sondage-choice-item ${chosen}" data-qid="${q.id}" data-value="${escapeHtml(o)}" data-mode="radio" ${isClosed ? 'aria-disabled="true"' : ''}>
                    <span class="choice-label">${escapeHtml(o)}</span>
                    <i class="bi bi-check-lg choice-check"></i>
                </div>`;
            }).join('');
        } else if (q.type === 'choix_multiple') {
            const options = q.options || [];
            let selectedVals = [];
            try { selectedVals = JSON.parse(existing); } catch(e) {}
            inputHtml = options.map(o => {
                const chosen = selectedVals.includes(o) ? 'chosen' : '';
                return `<div class="sondage-choice-item ${chosen}" data-qid="${q.id}" data-value="${escapeHtml(o)}" data-mode="check" ${isClosed ? 'aria-disabled="true"' : ''}>
                    <span class="choice-label">${escapeHtml(o)}</span>
                    <i class="bi bi-check-lg choice-check"></i>
                </div>`;
            }).join('');
        }

        return `<div class="sondage-q-card">
            <div class="q-label">${idx + 1}. ${escapeHtml(q.question)}
                <span class="badge bg-light text-dark ms-2 sondage-q-type-badge">${q.type === 'choix_unique' ? 'Choix unique' : q.type === 'choix_multiple' ? 'Choix multiple' : 'Texte libre'}</span>
            </div>
            ${inputHtml}
        </div>`;
    }).join('');

    panel.innerHTML = `
        <div class="sondage-detail-wrap">
            <h4 class="sondage-detail-title">${escapeHtml(sondage.titre)}</h4>
            <p class="sondage-detail-meta">
                Par ${escapeHtml(sondage.prenom || '')} ${escapeHtml(sondage.nom || '')}
                · ${new Date(sondage.created_at).toLocaleDateString('fr-FR')}
                ${sondage.is_anonymous == 1 ? ' · <i class="bi bi-incognito"></i> Réponses anonymes' : ''}
            </p>
            ${sondage.description ? '<div class="sondage-description-box">' + escapeHtml(sondage.description) + '</div>' : ''}
            
            ${questionsHtml}

            ${!isClosed ? `<div class="sondage-submit-wrap">
                <button class="btn btn-primary px-4" id="btnSubmitSondage">
                    <i class="bi bi-send"></i> Envoyer mes réponses
                </button>
            </div>` : '<div class="alert alert-secondary text-center small mt-3"><i class="bi bi-lock"></i> Ce sondage est fermé, les réponses ne peuvent plus être modifiées.</div>'}
        </div>`;

    // Choice item click handlers
    if (!isClosed) {
        panel.querySelectorAll('.sondage-choice-item').forEach(item => {
            if (item.getAttribute('aria-disabled') === 'true') return;
            on(item, 'click', () => {
                const mode = item.dataset.mode;
                if (mode === 'radio') {
                    // Deselect siblings for same question
                    panel.querySelectorAll(`.sondage-choice-item[data-qid="${item.dataset.qid}"][data-mode="radio"]`)
                        .forEach(sib => sib.classList.remove('chosen'));
                    item.classList.add('chosen');
                } else {
                    // Toggle for checkboxes
                    item.classList.toggle('chosen');
                }
            });
        });
    }

    // Submit handler
    const btnSubmit = document.getElementById('btnSubmitSondage');
    if (btnSubmit) {
        on(btnSubmit, 'click', async () => {
            const reponses = {};

            // Collect text answers
            panel.querySelectorAll('.q-answer').forEach(el => {
                reponses[el.dataset.qid] = el.value.trim();
            });

            // Collect radio answers (chosen items with mode=radio)
            panel.querySelectorAll('.sondage-choice-item.chosen[data-mode="radio"]').forEach(el => {
                reponses[el.dataset.qid] = el.dataset.value;
            });

            // Collect checkbox answers (chosen items with mode=check)
            const checkGroups = {};
            panel.querySelectorAll('.sondage-choice-item.chosen[data-mode="check"]').forEach(el => {
                const qid = el.dataset.qid;
                if (!checkGroups[qid]) checkGroups[qid] = [];
                checkGroups[qid].push(el.dataset.value);
            });
            for (const [qid, vals] of Object.entries(checkGroups)) {
                reponses[qid] = vals;
            }

            if (Object.keys(reponses).length === 0) {
                toast('Veuillez répondre à au moins une question');
                return;
            }

            btnSubmit.disabled = true;
            const r = await apiPost('submit_sondage_reponses', {
                sondage_id: sondage.id,
                reponses,
            });

            if (r.success) {
                toast('Réponses enregistrées !');
                await loadList();
                selectSondage(sondage.id);
            } else {
                toast(r.message || 'Erreur');
                btnSubmit.disabled = false;
            }
        });
    }
}

export function destroy() {
    _c.forEach(f => f());
    _c.length = 0;
}
