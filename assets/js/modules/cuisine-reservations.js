/**
 * Cuisine Reservations — Employee meal reservations view
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

export async function init() {
    const dateInput = document.getElementById('crDate');
    const repasSelect = document.getElementById('crRepas');
    if (dateInput) dateInput.value = todayStr();
    dateInput?.addEventListener('change', loadReservations);
    repasSelect?.addEventListener('change', loadReservations);
    document.getElementById('crPrintBtn')?.addEventListener('click', printReservations);
    loadReservations();
}

export function destroy() {}

async function loadReservations() {
    const body = document.getElementById('crBody');
    const dateVal = document.getElementById('crDate')?.value;
    const repasVal = document.getElementById('crRepas')?.value || 'midi';
    if (!body || !dateVal) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_reservations_collab', { date: dateVal, repas: repasVal });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-people"></i><p>Aucune réservation pour cette date</p></div>';
        return;
    }

    body.innerHTML = '';

    // Summary
    const summary = document.createElement('div');
    summary.className = 'cuis-collab-summary';
    summary.innerHTML = `<span><strong>${res.total_couverts || 0}</strong> couverts</span>
        <span class="badge bg-primary">${res.nb_menu || 0} menu</span>
        <span class="badge bg-success">${res.nb_salade || 0} salade</span>`;
    body.appendChild(summary);

    // Table
    const table = document.createElement('table');
    table.className = 'table table-sm table-striped cuis-table';
    table.id = 'crTable';

    const thead = document.createElement('thead');
    thead.innerHTML = '<tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th></tr>';
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(r.prenom + ' ' + r.nom)}</td>
            <td>${escapeHtml(r.fonction_nom || r.fonction_code || '-')}</td>
            <td><span class="badge ${r.choix === 'menu' ? 'bg-primary' : 'bg-success'}">${escapeHtml(r.choix)}</span></td>
            <td>${r.nb_personnes}</td>
            <td>${escapeHtml(r.paiement || '-')}</td>
            <td>${escapeHtml(r.remarques || '-')}</td>`;
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.appendChild(table);
}

function printReservations() {
    const table = document.getElementById('crTable');
    if (!table) { toast('Rien à imprimer', 'error'); return; }
    const dateVal = document.getElementById('crDate')?.value || '';
    const repasVal = document.getElementById('crRepas')?.value || 'midi';
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Réservations ${dateVal}</title>
        <style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:10px}
        table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}
        th{background:#f0f0f0}@media print{button{display:none}}</style></head>
        <body><h2>Réservations collaborateurs — ${escapeHtml(dateVal)} (${escapeHtml(repasVal)})</h2>
        ${table.outerHTML}<br><button onclick="window.print()">Imprimer</button></body></html>`);
    win.document.close();
}

function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
