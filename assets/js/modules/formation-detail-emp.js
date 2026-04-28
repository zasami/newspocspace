import { apiPost, toast } from '../helpers.js';

let modalCovoit = null;

export function init() {
    const modalEl = document.getElementById('covoitModal');
    if (modalEl) modalCovoit = new bootstrap.Modal(modalEl);

    // Update compteur sur change checkbox
    document.querySelectorAll('.covoit-cb').forEach(cb => {
        cb.addEventListener('change', updateCovoitCount);
    });

    document.getElementById('covoitBtn')?.addEventListener('click', openCovoitModal);
    document.getElementById('covoitSendBtn')?.addEventListener('click', sendCovoit);

    // Bouton "Ajouter mon adresse" → renvoie vers profile
    document.getElementById('addAdresseBtn')?.addEventListener('click', () => {
        location.href = '/spocspace/profile';
    });
}

export function destroy() {}

function updateCovoitCount() {
    const checked = document.querySelectorAll('.covoit-cb:checked');
    const cnt = checked.length;
    document.getElementById('covoitCount').textContent = cnt;
    document.getElementById('covoitBtn').disabled = cnt === 0;
}

function openCovoitModal() {
    const checked = [...document.querySelectorAll('.covoit-cb:checked')];
    if (!checked.length) { toast('Sélectionnez au moins un collègue', 'danger'); return; }

    const names = checked.map(cb => cb.dataset.name);
    document.getElementById('covoitNamesPreview').innerHTML = names
        .map(n => `<span class="badge text-bg-light me-1 mb-1">${escapeHtml(n)}</span>`)
        .join('');
    document.getElementById('covoitMessage').value = '';
    modalCovoit?.show();
}

async function sendCovoit() {
    const checked = [...document.querySelectorAll('.covoit-cb:checked')];
    const ids = checked.map(cb => cb.value);
    const formId = document.getElementById('formationId').value;
    const message = document.getElementById('covoitMessage').value;

    if (!ids.length) return;

    const btn = document.getElementById('covoitSendBtn');
    btn.disabled = true;

    try {
        const r = await apiPost('propose_covoiturage_formation', {
            formation_id: formId,
            collegue_ids: ids,
            message,
        });
        if (!r.success) throw new Error(r.message || 'Erreur');
        toast(r.message || 'Message envoyé', 'success');
        modalCovoit?.hide();
        // Reset checkboxes
        document.querySelectorAll('.covoit-cb:checked').forEach(cb => cb.checked = false);
        updateCovoitCount();
    } catch (err) {
        toast('Erreur : ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
    }
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    const t = document.createElement('span');
    t.textContent = String(s);
    return t.innerHTML;
}
