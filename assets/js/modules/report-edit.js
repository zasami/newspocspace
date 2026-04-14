import { apiPost, escapeHtml, toast, ssConfirm } from '../helpers.js';

let _rich = null;
async function getRich() {
    if (_rich) return _rich;
    try { _rich = await import('../rich-editor.js'); }
    catch (err) { console.warn('[report-edit] editor load fail', err); _rich = null; }
    return _rich;
}

let catalogue = [];
let selectedTaches = new Map(); // id -> { nb_fois, commentaire_stagiaire }
let editor = null;
let currentId = '';

const esc = (s) => escapeHtml(String(s ?? ''));

export async function init(pageId, params = {}) {
    currentId = params?.id || new URLSearchParams(window.location.search).get('id') || '';

    // Réinitialiser l'état (évite la rémanence entre 2 visites de la page)
    selectedTaches = new Map();
    catalogue = [];
    if (editor && _rich) { try { _rich.destroyEditor(editor); } catch(_){} editor = null; }
    delete window.__SS_REPORT_CONTENT__;

    // Reset champs
    document.getElementById('reReportId').value = '';
    document.getElementById('reType').value = 'quotidien';
    document.getElementById('reDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('reTitre').value = '';
    document.getElementById('reTitle').textContent = 'Nouveau report';

    // Load catalogue + existing report in parallel
    await Promise.all([
        loadCatalogue(),
        currentId ? loadExistingReport(currentId) : Promise.resolve(),
    ]);

    renderTaches();

    // Init rich editor
    const rich = await getRich();
    const wrap = document.getElementById('reEditor');
    wrap.innerHTML = '';
    if (rich) {
        try {
            editor = await rich.createEditor(wrap, {
                placeholder: 'Décris ta journée…',
                content: window.__SS_REPORT_CONTENT__ || '',
                mode: 'full',
            });
        } catch (err) { console.warn('[report-edit] createEditor failed', err); editor = null; }
    }
    if (!editor) {
        wrap.innerHTML = `<textarea id="reTextarea" class="form-control" rows="14" placeholder="Décris ta journée…">${esc(window.__SS_REPORT_CONTENT__?.replace(/<[^>]+>/g, '') || '')}</textarea>`;
    }
    delete window.__SS_REPORT_CONTENT__;

    bind();
}

async function loadCatalogue() {
    const r = await apiPost('get_stagiaire_taches_catalogue', {});
    if (r.success) catalogue = r.taches || [];
}

async function loadExistingReport(id) {
    const r = await apiPost('get_my_stage', {});
    if (!r.success || !r.reports) return;
    const rep = r.reports.find(x => x.id === id);
    if (!rep) return;
    document.getElementById('reReportId').value = rep.id;
    document.getElementById('reType').value = rep.type;
    document.getElementById('reDate').value = rep.date_report;
    document.getElementById('reTitre').value = rep.titre || '';
    document.getElementById('reTitle').textContent = 'Modifier report';
    window.__SS_REPORT_CONTENT__ = rep.contenu || '';
    selectedTaches = new Map();
    (rep.taches || []).forEach(t => {
        selectedTaches.set(t.tache_id, {
            nb_fois: t.nb_fois || 1,
            commentaire_stagiaire: t.commentaire_stagiaire || ''
        });
    });
}

function renderTaches(filter = '') {
    const el = document.getElementById('reTachesList');
    if (!catalogue.length) {
        el.innerHTML = '<div class="text-muted small p-2">Aucun catalogue chargé pour votre profil.</div>';
        return;
    }
    const q = filter.trim().toLowerCase();
    const filtered = q
        ? catalogue.filter(t => (t.nom + ' ' + (t.description || '') + ' ' + t.categorie).toLowerCase().includes(q))
        : catalogue;
    if (!filtered.length) {
        el.innerHTML = '<div class="text-muted small p-2">Aucun résultat.</div>';
        updateCount();
        return;
    }
    const byCat = {};
    filtered.forEach(t => { (byCat[t.categorie] = byCat[t.categorie] || []).push(t); });
    let html = '';
    Object.keys(byCat).forEach(cat => {
        html += `<div class="re-tcat">
            <div class="re-tcat-title">${esc(cat)}</div>`;
        byCat[cat].forEach(t => {
            const sel = selectedTaches.get(t.id);
            const checked = sel ? 'checked' : '';
            const nb = sel?.nb_fois || 1;
            html += `<div class="re-tache-wrap" data-tid="${t.id}">
                <label class="re-tache ${checked ? 'is-checked' : ''}">
                    <input type="checkbox" ${checked} data-tache-check="${t.id}" class="form-check-input">
                    <span class="re-tache-name">${esc(t.nom)}</span>
                    ${t.description ? `<button type="button" class="re-tache-info" data-tache-info="${t.id}" title="Voir la description" aria-label="Description"><i class="bi bi-info-circle"></i></button>` : ''}
                    <input type="number" class="re-tache-nb form-control form-control-sm" min="1" max="20" value="${nb}" data-tache-nb="${t.id}" title="Nombre de fois" ${checked ? '' : 'disabled'}>
                </label>
                ${t.description ? `<div class="re-tache-desc" data-tache-desc="${t.id}" hidden>${esc(t.description)}</div>` : ''}
            </div>`;
        });
        html += '</div>';
    });
    el.innerHTML = html;
    updateCount();
}

function updateCount() {
    const n = selectedTaches.size;
    const badge = document.getElementById('reCountBadge');
    const foot = document.getElementById('reTachesFooterCount');
    if (badge) badge.textContent = n;
    if (foot) foot.textContent = n;
}

async function save(action) {
    let html = '';
    if (editor && _rich) html = _rich.getHTML(editor);
    else {
        const ta = document.getElementById('reTextarea');
        html = ta ? ta.value : '';
    }
    const text = html.replace(/<[^>]+>/g, '').trim();
    if (!text) { toast('Contenu du rapport obligatoire', 'error'); return; }

    const taches = [];
    selectedTaches.forEach((v, tid) => taches.push({ tache_id: tid, nb_fois: v.nb_fois, commentaire_stagiaire: v.commentaire_stagiaire }));

    const data = {
        id: document.getElementById('reReportId').value,
        type: document.getElementById('reType').value,
        date_report: document.getElementById('reDate').value,
        titre: document.getElementById('reTitre').value,
        contenu: html,
        taches,
        mode: action, // 'save' | 'submit' — renommé pour ne pas écraser l'action API
    };
    const r = await apiPost('save_my_report', data);
    if (r.success) {
        toast(r.message || 'Enregistré');
        // Retour à la page mon-stage (via SPA router)
        history.pushState({}, '', '/spocspace/mon-stage');
        window.dispatchEvent(new PopStateEvent('popstate'));
    } else {
        toast(r.message || r.error || 'Erreur lors de l\'enregistrement');
        console.error('[save_my_report] error:', r);
        return;
    }
}

function bind() {
    const search = document.getElementById('reTacheSearch');
    search?.addEventListener('input', () => renderTaches(search.value));

    document.getElementById('reBtnDraft')?.addEventListener('click', () => save('save'));
    document.getElementById('reBtnSubmit')?.addEventListener('click', async () => {
        const ok = await ssConfirm('Ce report sera transmis à ton formateur et <strong>ne sera plus modifiable</strong> après validation.', {
            title: 'Soumettre le report ?',
            okText: 'Soumettre',
            cancelText: 'Annuler',
            variant: 'primary',
            icon: 'bi-send',
        });
        if (ok) save('submit');
    });

    const list = document.getElementById('reTachesList');

    // Click "info" → toggle description, sans cocher la tâche
    list?.addEventListener('click', (e) => {
        const infoBtn = e.target.closest('[data-tache-info]');
        if (infoBtn) {
            e.preventDefault();
            e.stopPropagation();
            const tid = infoBtn.dataset.tacheInfo;
            const desc = list.querySelector(`[data-tache-desc="${tid}"]`);
            if (desc) {
                const isOpen = !desc.hasAttribute('hidden');
                if (isOpen) desc.setAttribute('hidden', '');
                else desc.removeAttribute('hidden');
                infoBtn.classList.toggle('is-open', !isOpen);
            }
        }
    });

    list?.addEventListener('change', (e) => {
        const chk = e.target.closest('[data-tache-check]');
        if (chk) {
            const tid = chk.dataset.tacheCheck;
            const label = chk.closest('.re-tache');
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
            updateCount();
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
    if (editor && _rich) { _rich.destroyEditor(editor); editor = null; }
}
