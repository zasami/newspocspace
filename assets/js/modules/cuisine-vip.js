/**
 * Cuisine VIP — VIP table management
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

let modal = null;

export async function init() {
    const modalEl = document.getElementById('cvModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);
    document.getElementById('cvAddBtn')?.addEventListener('click', () => {
        document.getElementById('cvResidentSearch').value = '';
        document.getElementById('cvResidentResults').innerHTML = '';
        modal?.show();
    });
    document.getElementById('cvResidentSearch')?.addEventListener('input', debounce(searchResidents, 300));
    loadVip();
}

export function destroy() { modal = null; }

async function loadVip() {
    const body = document.getElementById('cvBody');
    if (!body) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_vip');
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.residents?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-star"></i><p>Aucun résident VIP</p></div>';
        return;
    }

    body.innerHTML = '';
    res.residents.forEach(r => {
        const card = document.createElement('div');
        card.className = 'cuis-vip-card';

        const header = document.createElement('div');
        header.className = 'cuis-vip-header';
        header.innerHTML = '<strong>' + escapeHtml(r.prenom + ' ' + r.nom) + '</strong>'
            + '<span class="text-muted small">' + escapeHtml(r.chambre ? 'Ch. ' + r.chambre : '') + ' ' + escapeHtml(r.etage ? '— Ét. ' + r.etage : '') + '</span>';

        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeBtn.title = 'Retirer VIP';
        removeBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'remove' });
            if (result.success) { toast('Retiré de la table VIP', 'success'); loadVip(); }
        });
        header.appendChild(removeBtn);
        card.appendChild(header);

        const textarea = document.createElement('textarea');
        textarea.className = 'form-control form-control-sm mt-2';
        textarea.rows = 2;
        textarea.placeholder = 'Menu spécial...';
        textarea.value = r.menu_special || '';
        card.appendChild(textarea);

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-sm btn-outline-primary mt-1';
        saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
        saveBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'set_menu', menu_special: textarea.value });
            toast(result.success ? 'Menu spécial mis à jour' : 'Erreur', result.success ? 'success' : 'error');
        });
        card.appendChild(saveBtn);
        body.appendChild(card);
    });
}

async function searchResidents() {
    const q = document.getElementById('cvResidentSearch')?.value || '';
    const list = document.getElementById('cvResidentResults');
    if (!list) return;
    if (q.length < 2) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_get_residents', { search: q });
    list.innerHTML = '';
    (res.residents || []).filter(r => !r.is_vip).forEach(r => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = r.prenom + ' ' + r.nom + (r.chambre ? ' — Ch. ' + r.chambre : '');
        item.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'add' });
            if (result.success) {
                toast('Ajouté à la table VIP', 'success');
                modal?.hide();
                loadVip();
            }
        });
        list.appendChild(item);
    });
}
