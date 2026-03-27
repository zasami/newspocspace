/**
 * Cuisine Reservations — Commandes collaborateurs (view + add + delete)
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

let modal = null;

export async function init() {
    const dateInput = document.getElementById('crDate');
    const repasSelect = document.getElementById('crRepas');
    if (dateInput) dateInput.value = todayStr();
    dateInput?.addEventListener('change', loadReservations);
    repasSelect?.addEventListener('change', loadReservations);
    document.getElementById('crPrintBtn')?.addEventListener('click', printReservations);

    // Modal
    const modalEl = document.getElementById('crModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);
    document.getElementById('crAddBtn')?.addEventListener('click', openAddModal);
    document.getElementById('crSaveBtn')?.addEventListener('click', saveCommande);
    document.getElementById('crUserSearch')?.addEventListener('input', debounce(searchUsers, 300));

    // Radio toggle style
    document.querySelectorAll('#crModal input[name="crChoix"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('#crModal input[name="crChoix"]').forEach(r => {
                r.closest('label').classList.toggle('active', r.checked);
            });
        });
    });

    loadReservations();
}

export function destroy() { modal = null; }

async function loadReservations() {
    const body = document.getElementById('crBody');
    const stats = document.getElementById('crStats');
    const dateVal = document.getElementById('crDate')?.value;
    const repasVal = document.getElementById('crRepas')?.value || 'midi';
    if (!body || !dateVal) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_reservations_collab', { date: dateVal, repas: repasVal });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    // Stats
    if (stats) {
        stats.innerHTML = '<span class="badge bg-secondary" style="font-size:.85rem"><i class="bi bi-people"></i> ' + (res.total_couverts || 0) + ' couverts</span>'
            + '<span class="badge bg-primary" style="font-size:.85rem"><i class="bi bi-egg-fried"></i> ' + (res.nb_menu || 0) + ' menu</span>'
            + '<span class="badge bg-success" style="font-size:.85rem"><i class="bi bi-flower1"></i> ' + (res.nb_salade || 0) + ' salade</span>';
    }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-receipt"></i><p>Aucune commande pour cette date</p></div>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'table table-sm table-striped';
    table.id = 'crTable';
    table.innerHTML = '<thead><tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th><th></th></tr></thead>';

    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
            + '<td class="small">' + escapeHtml(r.fonction_nom || r.fonction_code || '-') + '</td>'
            + '<td><span class="badge ' + (r.choix === 'menu' ? 'bg-primary' : 'bg-success') + '">' + escapeHtml(r.choix) + '</span></td>'
            + '<td>' + r.nb_personnes + '</td>'
            + '<td class="small">' + escapeHtml(r.paiement || '-') + '</td>'
            + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td>'
            + '<td></td>';

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        delBtn.title = 'Annuler';
        delBtn.addEventListener('click', async () => {
            if (!confirm('Annuler cette commande ?')) return;
            const result = await apiPost('cuisine_delete_commande', { id: r.id });
            if (result.success) { toast('Commande annulée', 'success'); loadReservations(); }
        });
        tr.lastElementChild.appendChild(delBtn);
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.innerHTML = '';
    body.appendChild(table);
}

function openAddModal() {
    document.getElementById('crUserSearch').value = '';
    document.getElementById('crUserId').value = '';
    document.getElementById('crUserResults').innerHTML = '';
    document.getElementById('crNb').value = 1;
    document.getElementById('crPaiement').value = 'salaire';
    document.getElementById('crRemarques').value = '';
    document.querySelector('#crModal input[name="crChoix"][value="menu"]').checked = true;
    document.querySelectorAll('#crModal input[name="crChoix"]').forEach(r => {
        r.closest('label').classList.toggle('active', r.checked);
    });
    modal?.show();
}

async function searchUsers() {
    const q = document.getElementById('crUserSearch')?.value || '';
    const list = document.getElementById('crUserResults');
    if (!list) return;
    if (q.length < 2) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_search_users', { q });
    list.innerHTML = '';
    (res.users || []).forEach(u => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = u.prenom + ' ' + u.nom + (u.fonction_nom ? ' — ' + u.fonction_nom : '');
        item.addEventListener('click', () => {
            document.getElementById('crUserSearch').value = u.prenom + ' ' + u.nom;
            document.getElementById('crUserId').value = u.id;
            list.innerHTML = '';
        });
        list.appendChild(item);
    });
}

async function saveCommande() {
    const userId = document.getElementById('crUserId')?.value;
    if (!userId) { toast('Sélectionnez un collaborateur', 'error'); return; }

    const btn = document.getElementById('crSaveBtn');
    btn.disabled = true;

    const data = {
        date_jour: document.getElementById('crDate')?.value,
        repas: document.getElementById('crRepas')?.value || 'midi',
        user_id: userId,
        choix: document.querySelector('#crModal input[name="crChoix"]:checked')?.value || 'menu',
        nb_personnes: document.getElementById('crNb')?.value || 1,
        paiement: document.getElementById('crPaiement')?.value || 'salaire',
        remarques: document.getElementById('crRemarques')?.value || '',
    };

    const res = await apiPost('cuisine_add_commande', data);
    btn.disabled = false;

    if (res.success) {
        toast('Commande enregistrée', 'success');
        modal?.hide();
        loadReservations();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

function printReservations() {
    const table = document.getElementById('crTable');
    if (!table) { toast('Rien à imprimer', 'error'); return; }
    const dateVal = document.getElementById('crDate')?.value || '';
    const repasVal = document.getElementById('crRepas')?.value || 'midi';
    const win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Commandes ' + escapeHtml(dateVal) + '</title>'
        + '<style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:10px}'
        + 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}'
        + 'th{background:#f0f0f0}.badge{padding:2px 8px;border-radius:4px;font-size:11px}'
        + '.bg-primary{background:#0d6efd;color:#fff}.bg-success{background:#198754;color:#fff}'
        + '@media print{button{display:none}}</style></head>'
        + '<body><h2>Commandes collaborateurs — ' + escapeHtml(dateVal) + ' (' + escapeHtml(repasVal) + ')</h2>'
        + table.outerHTML + '<br><button onclick="window.print()">Imprimer</button></body></html>');
    win.document.close();
}

function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
