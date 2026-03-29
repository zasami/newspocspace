/**
 * Cuisine Reservations — Commandes collaborateurs (view + add + delete)
 */
import { apiPost, toast, escapeHtml, debounce } from '../helpers.js';

let modal = null;

export function init() {
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

    // Choix toggle
    document.querySelectorAll('#crModal .menu-choix-option').forEach(opt => {
        opt.querySelector('input')?.addEventListener('change', () => {
            document.querySelectorAll('#crModal .menu-choix-option').forEach(o => {
                o.style.borderColor = 'var(--zt-border)'; o.style.background = '';
            });
            opt.style.borderColor = 'var(--zt-teal)'; opt.style.background = 'var(--zt-accent-bg)';
        });
    });
    // Paiement toggle
    document.querySelectorAll('#crModal input[name="crPaiement"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('#crModal .menu-pay-option').forEach(el => {
                el.style.borderColor = 'var(--zt-border)'; el.style.background = '';
            });
            if (radio.checked) {
                radio.closest('.menu-pay-option').style.borderColor = 'var(--zt-teal)';
                radio.closest('.menu-pay-option').style.background = 'var(--zt-accent-bg)';
            }
        });
    });
    // Tag input system
    const tagList = document.getElementById('crTagList');
    const tagText = document.getElementById('crTagText');
    const tagHidden = document.getElementById('crRemarques');
    const tagWrap = document.getElementById('crTagWrap');
    let tags = [];

    function syncTags() {
        tagHidden.value = tags.join(', ');
        tagList.innerHTML = tags.map((t, i) =>
            `<span class="cr-tag-chip" data-idx="${i}">${escapeHtml(t)}<span class="cr-tag-x" data-idx="${i}">&times;</span></span>`
        ).join('');
        // Update quick tag buttons active state
        document.querySelectorAll('#crModal .cr-quick-tag').forEach(b => {
            b.classList.toggle('cr-tag-active', tags.some(t => t.toLowerCase() === b.dataset.tag.toLowerCase()));
        });
        tagText.placeholder = tags.length ? '' : 'Tapez ou cliquez ci-dessous...';
    }

    function addTag(text) {
        text = text.trim();
        if (!text || tags.some(t => t.toLowerCase() === text.toLowerCase())) return;
        tags.push(text);
        syncTags();
    }

    function removeTag(idx) {
        tags.splice(idx, 1);
        syncTags();
    }

    window.__crTagsClear = () => { tags = []; syncTags(); };
    window.__crTagsGet = () => tags;

    tagList.addEventListener('click', e => {
        const x = e.target.closest('.cr-tag-x');
        if (x) removeTag(parseInt(x.dataset.idx));
    });

    tagText.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(tagText.value);
            tagText.value = '';
        }
        if (e.key === 'Backspace' && !tagText.value && tags.length) {
            removeTag(tags.length - 1);
        }
    });

    tagWrap.addEventListener('click', () => tagText.focus());

    // Quick tags
    document.querySelectorAll('#crModal .cr-quick-tag').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const tag = btn.dataset.tag;
            const idx = tags.findIndex(t => t.toLowerCase() === tag.toLowerCase());
            if (idx >= 0) { removeTag(idx); } else { addTag(tag); }
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

    // Stats (admin palette)
    if (stats) {
        stats.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.82rem;font-weight:600;background:#bcd2cb;color:#2d4a43"><i class="bi bi-people"></i> ' + (res.total_couverts || 0) + ' couverts</span>'
            + '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.82rem;font-weight:600;background:#B8C9D4;color:#3B4F6B"><i class="bi bi-egg-fried"></i> ' + (res.nb_menu || 0) + ' menu</span>'
            + '<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:8px;font-size:.82rem;font-weight:600;background:#D0C4D8;color:#5B4B6B"><i class="bi bi-flower1"></i> ' + (res.nb_salade || 0) + ' salade</span>';
    }

    if (!res.reservations?.length) {
        body.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="bi bi-receipt"></i><p>Aucune commande pour cette date</p></div>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'cuis-table table table-sm';
    table.id = 'crTable';
    table.innerHTML = '<thead><tr><th>Nom</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th><th></th></tr></thead>';

    const tbody = document.createElement('tbody');
    res.reservations.forEach(r => {
        const tr = document.createElement('tr');
        const choixStyle = r.choix === 'menu'
            ? 'background:#B8C9D4;color:#3B4F6B' : 'background:#bcd2cb;color:#2d4a43';
        const paiementLabels = { salaire: 'Salaire', caisse: 'Cash', carte: 'Carte' };
        tr.innerHTML = '<td style="font-weight:500">' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
            + '<td>' + escapeHtml(r.fonction_nom || r.fonction_code || '-') + '</td>'
            + '<td><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:.75rem;font-weight:600;' + choixStyle + '">' + escapeHtml(r.choix) + '</span></td>'
            + '<td>' + r.nb_personnes + '</td>'
            + '<td><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:.75rem;font-weight:600;background:#D4C4A8;color:#6B5B3E">' + escapeHtml(paiementLabels[r.paiement] || r.paiement || '-') + '</span></td>'
            + '<td style="color:#6b7280;font-size:.82rem">' + escapeHtml(r.remarques || '-') + '</td>'
            + '<td></td>';

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-sm btn-outline-danger';
        delBtn.style.cssText = 'width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:8px';
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
    // Wrap in admin-style card
    body.innerHTML = '';
    const card = document.createElement('div');
    card.style.cssText = 'border:1px solid #E8E5E0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04)';
    const tableWrap = document.createElement('div');
    tableWrap.style.cssText = 'overflow-x:auto';
    tableWrap.appendChild(table);
    card.appendChild(tableWrap);
    body.appendChild(card);
}

function openAddModal() {
    document.getElementById('crUserSearch').value = '';
    document.getElementById('crUserId').value = '';
    document.getElementById('crUserResults').innerHTML = '';
    document.getElementById('crNb').value = '1';
    document.getElementById('crRemarques').value = '';
    if (document.getElementById('crTagText')) document.getElementById('crTagText').value = '';
    if (window.__crTagsClear) window.__crTagsClear();

    // Reset choix
    const menuRadio = document.querySelector('#crModal input[name="crChoix"][value="menu"]');
    if (menuRadio) menuRadio.checked = true;
    document.querySelectorAll('#crModal .menu-choix-option').forEach(o => {
        o.style.borderColor = 'var(--zt-border)'; o.style.background = '';
    });
    if (menuRadio) { menuRadio.closest('.menu-choix-option').style.borderColor = 'var(--zt-teal)'; menuRadio.closest('.menu-choix-option').style.background = 'var(--zt-accent-bg)'; }

    // Reset paiement
    const salRadio = document.querySelector('#crModal input[name="crPaiement"][value="salaire"]');
    if (salRadio) salRadio.checked = true;
    document.querySelectorAll('#crModal .menu-pay-option').forEach(o => {
        o.style.borderColor = 'var(--zt-border)'; o.style.background = '';
    });
    if (salRadio) { salRadio.closest('.menu-pay-option').style.borderColor = 'var(--zt-teal)'; salRadio.closest('.menu-pay-option').style.background = 'var(--zt-accent-bg)'; }

    // Reset quick tags
    document.querySelectorAll('#crModal .cr-quick-tag').forEach(b => {
        b.style.background = ''; b.style.borderColor = '';
        b.classList.remove('cr-tag-active');
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
        const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();
        const item = document.createElement('div');
        item.className = 'cuis-autocomplete-item';
        item.style.cssText = 'display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.75rem';

        if (u.photo) {
            item.innerHTML = '<img src="' + escapeHtml(u.photo) + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">';
        } else {
            item.innerHTML = '<div style="width:32px;height:32px;border-radius:50%;background:var(--zt-teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">' + escapeHtml(initials) + '</div>';
        }

        const info = document.createElement('div');
        info.innerHTML = '<div style="font-weight:600;font-size:0.88rem">' + escapeHtml(u.prenom + ' ' + u.nom) + '</div>'
            + (u.fonction_nom ? '<div style="font-size:0.75rem;color:var(--zt-text-muted)">' + escapeHtml(u.fonction_nom) + '</div>' : '');
        item.appendChild(info);

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
        paiement: document.querySelector('#crModal input[name="crPaiement"]:checked')?.value || 'salaire',
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
