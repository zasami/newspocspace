/**
 * Cuisine Famille — Family/visitor reservations management
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

let modal = null;

export function init() {
    const dateInput = document.getElementById('cfDate');
    const repasSelect = document.getElementById('cfRepas');
    if (dateInput) dateInput.value = todayStr();
    dateInput?.addEventListener('change', loadReservations);
    repasSelect?.addEventListener('change', loadReservations);
    document.getElementById('cfAddBtn')?.addEventListener('click', openModal);

    const modalEl = document.getElementById('cfModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);
    document.getElementById('cfSaveBtn')?.addEventListener('click', saveReservation);
    document.getElementById('cfResidentSearch')?.addEventListener('input', debounce(searchResidents, 300));
    document.getElementById('cfVisiteurSearch')?.addEventListener('input', debounce(searchVisiteurs, 300));

    loadReservations();
}

export function destroy() { modal = null; }

async function loadReservations() {
    const body = document.getElementById('cfBody');
    const dateVal = document.getElementById('cfDate')?.value;
    const repasVal = document.getElementById('cfRepas')?.value || 'midi';
    if (!body || !dateVal) return;

    body.innerHTML = '<div class="text-center py-3"><span class="spinner"></span></div>';
    const res = await apiPost('cuisine_get_reservations_famille', { date: dateVal, repas: repasVal });
    if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-house-heart"></i><p>Aucune réservation famille</p></div>';
        return;
    }

    body.innerHTML = '';
    const table = document.createElement('table');
    table.className = 'table table-sm table-striped';
    table.innerHTML = '<thead><tr><th>Résident</th><th>Chambre</th><th>Visiteur</th><th>Relation</th><th>Pers.</th><th>Remarques</th><th></th></tr></thead>';
    const tbody = document.createElement('tbody');

    res.reservations.forEach(r => {
        const vName = r.visiteur_nom_ref ? (r.visiteur_prenom_ref + ' ' + r.visiteur_nom_ref) : (r.visiteur_nom || '-');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><strong>' + escapeHtml(r.resident_prenom + ' ' + r.resident_nom) + '</strong></td>'
            + '<td>' + escapeHtml(r.chambre || '-') + '</td>'
            + '<td>' + escapeHtml(vName) + '</td>'
            + '<td>' + escapeHtml(r.relation || '-') + '</td>'
            + '<td>' + r.nb_personnes + '</td>'
            + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td><td></td>';

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        delBtn.addEventListener('click', async () => {
            const result = await apiPost('cuisine_delete_reservation_famille', { id: r.id });
            if (result.success) { toast('Annulée', 'success'); loadReservations(); }
        });
        tr.lastElementChild.appendChild(delBtn);
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    body.appendChild(table);
}

function openModal() {
    document.getElementById('cfEditId').value = '';
    document.getElementById('cfFormDate').value = document.getElementById('cfDate')?.value || todayStr();
    document.getElementById('cfFormRepas').value = document.getElementById('cfRepas')?.value || 'midi';
    document.getElementById('cfResidentSearch').value = '';
    document.getElementById('cfResidentId').value = '';
    document.getElementById('cfVisiteurSearch').value = '';
    document.getElementById('cfVisiteurId').value = '';
    document.getElementById('cfNb').value = 1;
    document.getElementById('cfRemarques').value = '';
    document.getElementById('cfResidentResults').innerHTML = '';
    document.getElementById('cfVisiteurResults').innerHTML = '';
    document.getElementById('cfSaveVisiteurWrap').style.display = 'none';
    modal?.show();
}

async function searchResidents() {
    const q = document.getElementById('cfResidentSearch')?.value || '';
    const list = document.getElementById('cfResidentResults');
    if (!list) return;
    if (q.length < 2) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_get_residents', { search: q });
    list.innerHTML = '';
    (res.residents || []).forEach(r => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = r.prenom + ' ' + r.nom + (r.chambre ? ' — Ch. ' + r.chambre : '');
        item.addEventListener('click', () => {
            document.getElementById('cfResidentSearch').value = r.prenom + ' ' + r.nom;
            document.getElementById('cfResidentId').value = r.id;
            list.innerHTML = '';
            searchVisiteurs();
        });
        list.appendChild(item);
    });
}

async function searchVisiteurs() {
    const q = document.getElementById('cfVisiteurSearch')?.value || '';
    const residentId = document.getElementById('cfResidentId')?.value || '';
    const list = document.getElementById('cfVisiteurResults');
    if (!list) return;
    if (q.length < 2 && !residentId) { list.innerHTML = ''; return; }

    const res = await apiPost('cuisine_search_visiteurs', { q, resident_id: residentId });
    list.innerHTML = '';
    (res.visiteurs || []).forEach(v => {
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.textContent = v.prenom + ' ' + v.nom + (v.relation ? ' (' + v.relation + ')' : '');
        item.addEventListener('click', () => {
            document.getElementById('cfVisiteurSearch').value = v.prenom + ' ' + v.nom;
            document.getElementById('cfVisiteurId').value = v.id;
            list.innerHTML = '';
            document.getElementById('cfSaveVisiteurWrap').style.display = 'none';
        });
        list.appendChild(item);
    });

    const saveWrap = document.getElementById('cfSaveVisiteurWrap');
    if (q.length >= 2 && !(res.visiteurs || []).some(v => (v.prenom + ' ' + v.nom).toLowerCase() === q.toLowerCase())) {
        if (saveWrap) saveWrap.style.display = 'block';
        document.getElementById('cfVisiteurId').value = '';
    }
}

async function saveReservation() {
    const residentId = document.getElementById('cfResidentId')?.value;
    if (!residentId) { toast('Sélectionnez un résident', 'error'); return; }

    const data = {
        id: document.getElementById('cfEditId')?.value || '',
        date_jour: document.getElementById('cfFormDate')?.value,
        repas: document.getElementById('cfFormRepas')?.value || 'midi',
        resident_id: residentId,
        visiteur_id: document.getElementById('cfVisiteurId')?.value || '',
        visiteur_nom: document.getElementById('cfVisiteurSearch')?.value || '',
        nb_personnes: document.getElementById('cfNb')?.value || 1,
        remarques: document.getElementById('cfRemarques')?.value || '',
    };

    if (document.getElementById('cfSaveVisiteur')?.checked && !data.visiteur_id) {
        const parts = data.visiteur_nom.trim().split(/\s+/);
        const vRes = await apiPost('cuisine_save_visiteur', {
            prenom: parts[0] || '', nom: parts.slice(1).join(' ') || parts[0] || '',
            resident_id: residentId
        });
        if (vRes.success) data.visiteur_id = vRes.id;
    }

    const res = await apiPost('cuisine_save_reservation_famille', data);
    if (res.success) {
        toast('Réservation enregistrée', 'success');
        modal?.hide();
        loadReservations();
    } else {
        toast(res.message || 'Erreur', 'error');
    }
}

function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
