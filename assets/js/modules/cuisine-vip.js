/**
 * Cuisine VIP — VIP table management
 * Residents are added via topbar search (@nom or @chambre)
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

let residentHandler = null;

export async function init() {
    // Listen for resident selection from topbar search
    residentHandler = async (e) => {
        const { id, name } = e.detail;
        const result = await apiPost('cuisine_save_vip', { resident_id: id, vip_action: 'add' });
        if (result.success) {
            toast(name + ' ajouté à la table VIP', 'success');
            loadVip();
        } else {
            toast(result.message || 'Erreur', 'error');
        }
    };
    window.addEventListener('resident-selected', residentHandler);
    loadVip();
}

export function destroy() {
    if (residentHandler) window.removeEventListener('resident-selected', residentHandler);
    residentHandler = null;
}

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
    const grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem';

    res.residents.forEach(r => {
        const card = document.createElement('div');
        card.style.cssText = 'border:1px solid #E8E5E0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);transition:box-shadow 0.2s';
        card.onmouseenter = () => { card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.06)'; };
        card.onmouseleave = () => { card.style.boxShadow = '0 1px 3px rgba(0,0,0,0.04)'; };

        const initials = ((r.prenom?.[0] || '') + (r.nom?.[0] || '')).toUpperCase();
        const header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-bottom:1px solid #E8E5E0';
        header.innerHTML = '<div style="width:40px;height:40px;border-radius:50%;background:#D4C4A8;color:#6B5B3E;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">' + escapeHtml(initials) + '</div>'
            + '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:.92rem;color:#1A1A18">' + escapeHtml(r.prenom + ' ' + r.nom) + '</div>'
            + '<div style="font-size:.78rem;color:#6b7280">' + escapeHtml(r.chambre ? 'Ch. ' + r.chambre : '') + (r.etage ? ' · Ét. ' + r.etage : '') + '</div></div>';

        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.style.cssText = 'width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:8px;flex-shrink:0';
        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeBtn.title = 'Retirer VIP';
        removeBtn.addEventListener('click', async () => {
            if (!confirm('Retirer ' + r.prenom + ' ' + r.nom + ' de la table VIP ?')) return;
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'remove' });
            if (result.success) { toast('Retiré', 'success'); loadVip(); }
        });
        header.appendChild(removeBtn);
        card.appendChild(header);

        const cardBody = document.createElement('div');
        cardBody.style.cssText = 'padding:1rem 1.25rem';
        const label = document.createElement('label');
        label.style.cssText = 'display:block;font-size:.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem';
        label.textContent = 'Menu spécial';
        cardBody.appendChild(label);

        const textarea = document.createElement('textarea');
        textarea.className = 'form-control';
        textarea.style.cssText = 'border-radius:8px;border:1px solid #E8E5E0;font-size:.85rem;resize:vertical';
        textarea.rows = 2;
        textarea.placeholder = 'Régime, allergies, préférences...';
        textarea.value = r.menu_special || '';
        const originalVal = r.menu_special || '';
        cardBody.appendChild(textarea);

        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn btn-sm btn-primary';
        saveBtn.style.cssText = 'margin-top:.5rem;border-radius:8px;font-size:.78rem';
        saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
        saveBtn.disabled = true;

        textarea.addEventListener('input', () => {
            saveBtn.disabled = textarea.value === originalVal;
        });

        saveBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_save_vip', { resident_id: r.id, vip_action: 'set_menu', menu_special: textarea.value });
            toast(result.success ? 'Menu spécial mis à jour' : 'Erreur', result.success ? 'success' : 'error');
            if (result.success) saveBtn.disabled = true;
        });
        cardBody.appendChild(saveBtn);
        card.appendChild(cardBody);
        grid.appendChild(card);
    });

    body.appendChild(grid);
}
