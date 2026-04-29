import { apiPost, escapeHtml, toast, debounce } from '../helpers.js';

let targetUsers = [];
let personnesSelected = [];
let graviteSelected = 'moyenne';
let visibilitySelected = 'private';
let attachedFile = null;
let searchDebounced = null;

export function init() {
    // Gravité
    document.querySelectorAll('#fanGravite .fan-sev').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('#fanGravite .fan-sev').forEach(e => e.classList.remove('selected'));
            el.classList.add('selected');
            graviteSelected = el.dataset.val;
        });
    });

    // Personnes concernées (multi)
    document.querySelectorAll('#fanPersonnes .fan-pill').forEach(p => {
        p.addEventListener('click', () => {
            p.classList.toggle('selected');
            const v = p.dataset.val;
            if (p.classList.contains('selected')) {
                if (!personnesSelected.includes(v)) personnesSelected.push(v);
            } else {
                personnesSelected = personnesSelected.filter(x => x !== v);
            }
        });
    });

    // Visibility (single)
    document.querySelectorAll('#fanVisibility .fan-pill').forEach(p => {
        p.addEventListener('click', () => {
            document.querySelectorAll('#fanVisibility .fan-pill').forEach(e => e.classList.remove('selected'));
            p.classList.add('selected');
            visibilitySelected = p.dataset.val;
            const tw = document.getElementById('fanTargetWrap');
            if (tw) tw.style.display = (visibilitySelected === 'targeted') ? '' : 'none';
        });
    });

    // File dropzone
    const drop = document.getElementById('fanDrop');
    const fileInput = document.getElementById('fanFile');
    const fileDisplay = document.getElementById('fanDropFile');
    if (drop && fileInput) {
        drop.addEventListener('click', () => fileInput.click());
        drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('drag'); });
        drop.addEventListener('dragleave', () => drop.classList.remove('drag'));
        drop.addEventListener('drop', e => {
            e.preventDefault(); drop.classList.remove('drag');
            if (e.dataTransfer.files[0]) { attachedFile = e.dataTransfer.files[0]; updateFileDisplay(); }
        });
        fileInput.addEventListener('change', e => {
            if (e.target.files[0]) { attachedFile = e.target.files[0]; updateFileDisplay(); }
        });
    }
    function updateFileDisplay() {
        if (!attachedFile || !fileDisplay) return;
        fileDisplay.innerHTML = `<i class="bi bi-paperclip"></i> ${escapeHtml(attachedFile.name)} (${(attachedFile.size/1024).toFixed(0)} Ko) <button type="button" style="background:none;border:none;color:#A85B4A;cursor:pointer;margin-left:6px" id="fanFileRemove"><i class="bi bi-x"></i></button>`;
        document.getElementById('fanFileRemove')?.addEventListener('click', (e) => {
            e.stopPropagation(); attachedFile = null; fileInput.value = ''; fileDisplay.innerHTML = '';
        });
    }

    // User search (targeted visibility)
    const search = document.getElementById('fanTargetSearch');
    const results = document.getElementById('fanTargetResults');
    if (search && results) {
        searchDebounced = debounce(async () => {
            const q = search.value.trim();
            if (q.length < 2) { results.style.display = 'none'; results.innerHTML = ''; return; }
            const r = await apiPost('search_fiche_amelioration_users', { q });
            if (!r.success || !r.users.length) { results.style.display = 'none'; return; }
            results.innerHTML = r.users
                .filter(u => !targetUsers.some(t => t.id === u.id))
                .map(u => `<div class="fa-user-search-item" data-uid="${u.id}" data-name="${escapeHtml(u.prenom + ' ' + u.nom)}" style="padding:8px 10px; cursor:pointer; border-bottom:1px solid var(--cl-border-light)">${escapeHtml(u.prenom + ' ' + u.nom)}</div>`).join('');
            results.style.display = results.innerHTML ? '' : 'none';
        }, 250);
        search.addEventListener('input', searchDebounced);
        results.addEventListener('click', e => {
            const it = e.target.closest('[data-uid]');
            if (!it) return;
            const [prenom, ...nomParts] = it.dataset.name.split(' ');
            addTargetUser({ id: it.dataset.uid, prenom, nom: nomParts.join(' ') });
            search.value = '';
            results.style.display = 'none';
        });
    }

    // Rich editors toolbar
    document.querySelectorAll('.fan-rich-toolbar').forEach(tb => {
        const targetId = tb.dataset.for;
        const ed = document.getElementById(targetId);
        if (!ed) return;
        tb.querySelectorAll('.fan-rich-btn').forEach(btn => {
            btn.addEventListener('mousedown', e => e.preventDefault());
            btn.addEventListener('click', () => applyRichCmd(ed, btn.dataset.fmt));
        });
        // Click on checklist item toggles checked
        ed.addEventListener('click', e => {
            const li = e.target.closest('ul.checklist > li');
            if (!li) return;
            // Only toggle if click is on the virtual checkbox area (left padding)
            const rect = li.getBoundingClientRect();
            if (e.clientX - rect.left < 26) li.classList.toggle('checked');
        });
    });

    // Buttons
    document.getElementById('fanSubmitBtn')?.addEventListener('click', () => submitFiche(false));
    document.getElementById('fanDraftBtn')?.addEventListener('click', () => submitFiche(true));
}

function applyRichCmd(ed, cmd) {
    ed.focus();
    if (cmd === 'checklist') {
        document.execCommand('insertHTML', false, '<ul class="checklist"><li>Élément à faire</li></ul>');
        return;
    }
    document.execCommand(cmd, false, null);
}

function getRichContent(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    const html = el.innerHTML.trim();
    // Empty check: if only whitespace/br, return empty string
    if (html === '' || html === '<br>' || html === '<div><br></div>') return '';
    return html;
}
function getRichText(id) {
    const el = document.getElementById(id);
    return el ? (el.textContent || '').trim() : '';
}

export function destroy() {
    targetUsers = [];
    personnesSelected = [];
    attachedFile = null;
    searchDebounced = null;
}

function addTargetUser(u) {
    if (targetUsers.some(x => x.id === u.id)) return;
    targetUsers.push(u);
    renderTargetChips();
}
function removeTargetUser(id) {
    targetUsers = targetUsers.filter(u => u.id !== id);
    renderTargetChips();
}
function renderTargetChips() {
    const el = document.getElementById('fanTargetChips');
    if (!el) return;
    el.innerHTML = targetUsers.map(u => {
        const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();
        return `<span style="background:#F7F5F2; border:1px solid var(--cl-border-light); border-radius:999px; padding:3px 10px 3px 3px; display:inline-flex; align-items:center; gap:6px; font-size:.8rem">
            <span style="width:22px; height:22px; border-radius:50%; background:#C4A882; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:700">${escapeHtml(initials)}</span>
            ${escapeHtml(u.prenom + ' ' + u.nom)}
            <button type="button" data-rm="${u.id}" style="background:none; border:none; color:var(--cl-text-muted); cursor:pointer"><i class="bi bi-x"></i></button>
        </span>`;
    }).join('');
    el.querySelectorAll('[data-rm]').forEach(b => b.addEventListener('click', e => {
        e.stopPropagation(); removeTargetUser(b.dataset.rm);
    }));
}

async function submitFiche(isDraft) {
    const titre = document.getElementById('fanTitre').value.trim();
    const description = getRichContent('fanDescription');
    const descriptionText = getRichText('fanDescription');
    const suggestion = getRichContent('fanSuggestion');
    const mesures = getRichContent('fanMesures');
    const type = document.getElementById('fanType').value;
    const categorie = document.getElementById('fanCategorie').value;
    const unite = document.getElementById('fanUnite').value;
    const dateEvt = document.getElementById('fanDateEvt').value;
    const heureEvt = document.getElementById('fanHeureEvt').value;
    const lieu = document.getElementById('fanLieu').value.trim();
    const isAnonymous = document.getElementById('fanAnonymous').checked ? 1 : 0;

    // Validation (plus souple en mode draft)
    if (!isDraft) {
        if (!unite) { toast('Unité/département requis', 'warning'); return; }
        if (!type) { toast('Type d\'événement requis', 'warning'); return; }
        if (!categorie) { toast('Catégorie requise', 'warning'); return; }
        if (!titre) { toast('Titre requis', 'warning'); return; }
        if (!descriptionText) { toast('Description requise', 'warning'); return; }
        if (!dateEvt) { toast('Date de l\'événement requise', 'warning'); return; }
    }
    if (!titre) { toast('Au minimum un titre est requis', 'warning'); return; }

    const btn = isDraft ? document.getElementById('fanDraftBtn') : document.getElementById('fanSubmitBtn');
    if (btn) btn.disabled = true;

    const payload = {
        titre, description, suggestion,
        mesures_immediates: mesures,
        type_evenement: type || 'suggestion',
        categorie: categorie || 'autre',
        criticite: graviteSelected,
        visibility: visibilitySelected,
        is_anonymous: isAnonymous,
        is_draft: isDraft ? 1 : 0,
        unite_module_id: unite || '',
        date_evenement: dateEvt || '',
        heure_evenement: heureEvt || '',
        lieu_precis: lieu,
        personnes_concernees_types: personnesSelected.join(','),
        concernes_ids: targetUsers.map(u => u.id),
    };

    const r = await apiPost('submit_fiche_amelioration', payload);
    if (btn) btn.disabled = false;

    if (!r.success) { toast(r.message || 'Erreur', 'danger'); return; }

    // Upload attachment if present
    if (attachedFile && r.id) {
        const fd = new FormData();
        fd.append('action', 'upload_fiche_amelioration_attachment');
        fd.append('fiche_id', r.id);
        fd.append('file', attachedFile);
        try {
            const csrf = window.__SS__?.csrfToken || '';
            await fetch('/newspocspace/api.php', {
                method: 'POST',
                headers: csrf ? { 'X-CSRF-Token': csrf } : {},
                body: fd,
                credentials: 'same-origin',
            });
        } catch (e) { console.warn('upload failed', e); }
    }

    toast(isDraft ? 'Brouillon enregistré' : 'Fiche soumise — merci pour votre contribution', 'success');
    setTimeout(() => {
        window.location.href = '/newspocspace/fiches-amelioration';
    }, 600);
}
