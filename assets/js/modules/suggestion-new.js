import { apiPost, toast, escapeHtml } from '../helpers.js';
import { navigateTo } from '../app.js';

let pendingFiles = [];      // fichiers à uploader APRÈS création (mode create)
let createdId = null;       // id une fois créé (pour uploads incrémentaux ensuite)

export function init() {
    const form = document.getElementById('sgnForm');
    if (!form) return;

    const editId = form.dataset.editId || null;
    createdId = editId; // en mode edit, on a déjà un id

    // Pills — catégorie (un seul choix)
    bindPillGroup('categorie');
    bindPillGroup('urgence');

    // Bénéfices — checkbox toggle visuel
    document.querySelectorAll('.sgn-benef').forEach(lbl => {
        const cb = lbl.querySelector('input[type=checkbox]');
        if (!cb) return;
        const sync = () => lbl.classList.toggle('checked', cb.checked);
        cb.addEventListener('change', sync);
        lbl.addEventListener('click', e => { if (e.target === lbl || e.target.tagName === 'SPAN' || e.target.tagName === 'I') { /* let label toggle naturally */ } });
    });

    // Dropzone
    setupDropzone();

    // Submit
    form.addEventListener('submit', async e => {
        e.preventDefault();
        await submit(editId);
    });
}

export function destroy() {
    pendingFiles = [];
    createdId = null;
}

function bindPillGroup(name) {
    const hidden = document.querySelector(`input[name="${name}"]`);
    const pills = document.querySelectorAll(`[data-sgn-group="${name}"] > *`);
    pills.forEach(p => {
        p.addEventListener('click', () => {
            pills.forEach(x => x.classList.remove('selected'));
            p.classList.add('selected');
            if (hidden) hidden.value = p.dataset.val;
        });
    });
}

function setupDropzone() {
    const dz = document.getElementById('sgnDropzone');
    const input = document.getElementById('sgnFileInput');
    if (!dz || !input) return;

    dz.addEventListener('click', () => input.click());
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag');
        handleFiles(e.dataTransfer.files);
    });
    input.addEventListener('change', () => handleFiles(input.files));
}

async function handleFiles(fileList) {
    for (const f of fileList) {
        if (f.size > 10 * 1024 * 1024) {
            toast(`${f.name} dépasse 10 Mo, ignoré`);
            continue;
        }
        if (createdId) {
            // Mode edit ou après création : upload direct
            await uploadOne(createdId, f);
        } else {
            pendingFiles.push(f);
            renderPending();
        }
    }
}

function renderPending() {
    const list = document.getElementById('sgnAttachList');
    if (!list) return;
    list.innerHTML = pendingFiles.map((f, i) => `
        <div class="sgn-attach-item">
            <i class="bi ${iconFor(f.type)}"></i>
            <span class="sgn-attach-name">${escapeHtml(f.name)}</span>
            <span class="text-muted small">${(f.size/1024).toFixed(0)} Ko</span>
            <button type="button" class="sgn-attach-del" data-pending-del="${i}"><i class="bi bi-x-lg"></i></button>
        </div>`).join('');
    list.querySelectorAll('[data-pending-del]').forEach(b => {
        b.addEventListener('click', () => {
            pendingFiles.splice(Number(b.dataset.pendingDel), 1);
            renderPending();
        });
    });
}

function appendUploaded(att, file) {
    const list = document.getElementById('sgnAttachList');
    if (!list) return;
    const div = document.createElement('div');
    div.className = 'sgn-attach-item';
    div.dataset.attId = att.id;
    div.innerHTML = `
        <i class="bi ${iconFor(file.type)}"></i>
        <span class="sgn-attach-name">${escapeHtml(file.name)}</span>
        <span class="text-muted small">${(file.size/1024).toFixed(0)} Ko</span>
        <button type="button" class="sgn-attach-del"><i class="bi bi-x-lg"></i></button>`;
    div.querySelector('.sgn-attach-del').addEventListener('click', async () => {
        if (!confirm('Supprimer cette pièce jointe ?')) return;
        const r = await apiPost('delete_suggestion_attachment', { id: att.id });
        if (r.success) div.remove();
    });
    list.appendChild(div);
}

function iconFor(type) {
    if (!type) return 'bi-file-earmark';
    if (type.startsWith('image/')) return 'bi-image';
    if (type.startsWith('audio/')) return 'bi-music-note';
    if (type.includes('pdf')) return 'bi-file-earmark-pdf';
    return 'bi-file-earmark';
}

async function uploadOne(sugId, file) {
    const fd = new FormData();
    fd.append('action', 'upload_suggestion_attachment');
    fd.append('suggestion_id', sugId);
    fd.append('file', file);
    const res = await fetch('/spocspace/api.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': window.__SS__?.csrfToken || '' },
        body: fd,
    });
    const j = await res.json();
    if (!j.success) { toast(j.message || 'Erreur upload'); return null; }
    appendUploaded(j, file);
    return j;
}

async function submit(editId) {
    const form = document.getElementById('sgnForm');
    const btn = document.getElementById('sgnSubmit');
    if (!form || !btn) return;

    const fd = new FormData(form);
    const benefices = fd.getAll('benefices[]');
    const payload = {
        titre: fd.get('titre')?.trim(),
        service: fd.get('service'),
        categorie: fd.get('categorie'),
        urgence: fd.get('urgence'),
        frequence: fd.get('frequence') || null,
        description: fd.get('description')?.trim(),
        benefices,
    };

    if (!payload.titre || payload.titre.length < 4) {
        toast('Titre trop court (min 4 caractères)'); return;
    }
    if (!payload.description || payload.description.length < 10) {
        toast('Description trop courte (min 10 caractères)'); return;
    }

    btn.disabled = true;
    try {
        let r;
        if (editId) {
            r = await apiPost('update_suggestion', { id: editId, ...payload });
            if (!r.success) { toast(r.message || 'Erreur'); return; }
            toast('Suggestion mise à jour');
            navigateTo('suggestion-detail', editId);
            return;
        }

        r = await apiPost('create_suggestion', payload);
        if (!r.success) { toast(r.message || 'Erreur'); return; }

        const newId = r.id;
        createdId = newId;
        // Upload des fichiers en attente
        if (pendingFiles.length) {
            toast(`Envoi de ${pendingFiles.length} fichier(s)…`);
            for (const f of pendingFiles) await uploadOne(newId, f);
            pendingFiles = [];
        }
        toast(`Suggestion créée (${r.reference})`);
        navigateTo('suggestion-detail', newId);
    } finally {
        btn.disabled = false;
    }
}
