<?php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-egg-fried"></i> Gestion des menus</h4>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-outline-secondary" id="acMenuPrev"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold" id="acMenuWeekLabel"></span>
  <button class="btn btn-sm btn-outline-secondary" id="acMenuNext"><i class="bi bi-chevron-right"></i></button>
</div>

<div id="acMenuBody">
  <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
</div>

<!-- Modal saisie menu -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menuModalTitle">Menu</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="menuDate">
        <input type="hidden" id="menuRepas">
        <div class="mb-2">
          <label class="form-label">Entrée</label>
          <input type="text" class="form-control" id="menuEntree" placeholder="Ex: Soupe de légumes">
        </div>
        <div class="mb-2">
          <label class="form-label">Plat principal <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="menuPlat" required placeholder="Ex: Poulet rôti">
        </div>
        <div class="mb-2">
          <label class="form-label">Salade</label>
          <input type="text" class="form-control" id="menuSalade" placeholder="Ex: Salade verte">
        </div>
        <div class="mb-2">
          <label class="form-label">Accompagnement</label>
          <input type="text" class="form-control" id="menuAccomp" placeholder="Ex: Riz basmati">
        </div>
        <div class="mb-2">
          <label class="form-label">Dessert</label>
          <input type="text" class="form-control" id="menuDessert" placeholder="Ex: Tarte aux pommes">
        </div>
        <div class="mb-0">
          <label class="form-label">Remarques</label>
          <textarea class="form-control" id="menuRemarques" rows="2" placeholder="Allergènes, options végé..."></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="menuSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
    let menuMonday = getMonday(new Date());
    let modal = null;
    const modalEl = document.getElementById('menuModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);

    // ═══════════════════════════════════════
    // Week navigation
    // ═══════════════════════════════════════

    function updateWeekLabel() {
        const el = document.getElementById('acMenuWeekLabel');
        if (!el) return;
        const sun = new Date(menuMonday);
        sun.setDate(sun.getDate() + 6);
        el.textContent = fmtDateFr(menuMonday) + ' — ' + fmtDateFr(sun);
    }

    document.getElementById('acMenuPrev')?.addEventListener('click', () => {
        menuMonday = shiftWeek(menuMonday, -7);
        loadMenus();
    });
    document.getElementById('acMenuNext')?.addEventListener('click', () => {
        menuMonday = shiftWeek(menuMonday, 7);
        loadMenus();
    });

    // ═══════════════════════════════════════
    // Load & render
    // ═══════════════════════════════════════

    async function loadMenus() {
        const body = document.getElementById('acMenuBody');
        if (!body) return;
        updateWeekLabel();
        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const res = await adminApiPost('admin_get_menus', { date: fmtDate(menuMonday) });
        if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur chargement</p>'; return; }

        const menusByKey = {};
        (res.menus || []).forEach(m => { menusByKey[m.date_jour + '_' + (m.repas || 'midi')] = m; });

        body.innerHTML = '';
        for (let i = 0; i < 7; i++) {
            const d = new Date(menuMonday);
            d.setDate(d.getDate() + i);
            const dateStr = fmtDate(d);
            const isToday = dateStr === fmtDate(new Date());

            const card = document.createElement('div');
            card.className = 'card mb-2';
            if (isToday) card.style.borderLeft = '3px solid var(--bs-primary)';

            const header = document.createElement('div');
            header.className = 'card-header d-flex align-items-center gap-2 py-2';
            header.innerHTML = '<strong>' + escapeHtml(DAYS_FR[i] + ' ' + d.getDate() + '/' + (d.getMonth() + 1)) + '</strong>';
            if (isToday) header.innerHTML += ' <span class="badge bg-primary">Aujourd\'hui</span>';
            card.appendChild(header);

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-0';

            const table = document.createElement('table');
            table.className = 'table table-sm mb-0';
            table.innerHTML = '<thead><tr><th style="width:70px">Repas</th><th>Entrée</th><th>Plat</th><th>Salade</th><th>Accomp.</th><th>Dessert</th><th>Remarques</th><th style="width:90px">Résa</th><th style="width:100px"></th></tr></thead>';

            const tbody = document.createElement('tbody');
            ['midi', 'soir'].forEach(repas => {
                const menu = menusByKey[dateStr + '_' + repas];
                const tr = document.createElement('tr');

                if (menu) {
                    tr.innerHTML = '<td><span class="badge ' + (repas === 'midi' ? 'bg-warning text-dark' : 'bg-dark') + '">' + repas + '</span></td>'
                        + '<td>' + escapeHtml(menu.entree || '-') + '</td>'
                        + '<td><strong>' + escapeHtml(menu.plat || '-') + '</strong></td>'
                        + '<td>' + escapeHtml(menu.salade || '-') + '</td>'
                        + '<td>' + escapeHtml(menu.accompagnement || '-') + '</td>'
                        + '<td>' + escapeHtml(menu.dessert || '-') + '</td>'
                        + '<td class="text-muted small">' + escapeHtml(menu.remarques || '-') + '</td>'
                        + '<td><span class="badge bg-info text-dark">' + (menu.total_couverts || 0) + ' couv.</span> '
                        + '<span class="small text-muted">(' + (menu.nb_menu || 0) + 'M/' + (menu.nb_salade || 0) + 'S)</span></td>'
                        + '<td></td>';

                    const actionTd = tr.lastElementChild;

                    // Edit
                    const editBtn = document.createElement('button');
                    editBtn.className = 'btn btn-sm btn-outline-primary';
                    editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                    editBtn.title = 'Modifier';
                    editBtn.addEventListener('click', () => openMenuModal(dateStr, repas, menu));
                    actionTd.appendChild(editBtn);

                    // Detail reservations
                    if (parseInt(menu.nb_reservations) > 0) {
                        const detailBtn = document.createElement('button');
                        detailBtn.className = 'btn btn-sm btn-outline-secondary ms-1';
                        detailBtn.innerHTML = '<i class="bi bi-eye"></i>';
                        detailBtn.title = 'Voir réservations';
                        detailBtn.addEventListener('click', () => showMenuReservations(menu.id, dateStr, repas));
                        actionTd.appendChild(detailBtn);
                    }

                    // Delete
                    const delBtn = document.createElement('button');
                    delBtn.className = 'btn btn-sm btn-outline-danger ms-1';
                    delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                    delBtn.title = 'Supprimer';
                    delBtn.addEventListener('click', async () => {
                        const ok = await adminConfirm({
                            title: 'Supprimer ce menu ?',
                            text: 'Le menu du <strong>' + escapeHtml(DAYS_FR[i]) + ' ' + repas + '</strong> et toutes ses réservations seront supprimés.',
                            type: 'danger', icon: 'bi-trash', okText: 'Supprimer'
                        });
                        if (!ok) return;
                        const r = await adminApiPost('admin_delete_menu', { menu_id: menu.id });
                        if (r.success) { showToast('Menu supprimé', 'success'); loadMenus(); }
                    });
                    actionTd.appendChild(delBtn);
                } else {
                    // No menu — show add button
                    tr.innerHTML = '<td><span class="badge bg-light text-muted">' + repas + '</span></td>'
                        + '<td colspan="7" class="text-muted fst-italic">Pas de menu</td>'
                        + '<td></td>';
                    const actionTd = tr.lastElementChild;
                    const addBtn = document.createElement('button');
                    addBtn.className = 'btn btn-sm btn-outline-success';
                    addBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Créer';
                    addBtn.addEventListener('click', () => openMenuModal(dateStr, repas, null));
                    actionTd.appendChild(addBtn);
                }
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            cardBody.appendChild(table);
            card.appendChild(cardBody);
            body.appendChild(card);
        }
    }

    // ═══════════════════════════════════════
    // Modal create/edit
    // ═══════════════════════════════════════

    function openMenuModal(dateStr, repas, menu) {
        document.getElementById('menuModalTitle').textContent = menu
            ? 'Modifier le menu — ' + dateStr + ' (' + repas + ')'
            : 'Créer le menu — ' + dateStr + ' (' + repas + ')';
        document.getElementById('menuDate').value = dateStr;
        document.getElementById('menuRepas').value = repas;
        document.getElementById('menuEntree').value = menu?.entree || '';
        document.getElementById('menuPlat').value = menu?.plat || '';
        document.getElementById('menuSalade').value = menu?.salade || '';
        document.getElementById('menuAccomp').value = menu?.accompagnement || '';
        document.getElementById('menuDessert').value = menu?.dessert || '';
        document.getElementById('menuRemarques').value = menu?.remarques || '';
        modal?.show();
    }

    document.getElementById('menuSaveBtn')?.addEventListener('click', async () => {
        const plat = document.getElementById('menuPlat').value.trim();
        if (!plat) { showToast('Le plat principal est requis', 'error'); return; }

        const data = {
            date_jour: document.getElementById('menuDate').value,
            repas: document.getElementById('menuRepas').value,
            entree: document.getElementById('menuEntree').value.trim(),
            plat: plat,
            salade: document.getElementById('menuSalade').value.trim(),
            accompagnement: document.getElementById('menuAccomp').value.trim(),
            dessert: document.getElementById('menuDessert').value.trim(),
            remarques: document.getElementById('menuRemarques').value.trim(),
        };

        const res = await adminApiPost('admin_save_menu', data);
        if (res.success) {
            showToast('Menu enregistré', 'success');
            modal?.hide();
            loadMenus();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ═══════════════════════════════════════
    // Reservations detail (modal)
    // ═══════════════════════════════════════

    async function showMenuReservations(menuId, dateStr, repas) {
        const res = await adminApiPost('admin_get_menu_reservations', { menu_id: menuId });
        if (!res.success) return;

        let html = '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr>'
            + '<th>Nom</th><th>Email</th><th>Fonction</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th>'
            + '</tr></thead><tbody>';
        (res.reservations || []).forEach(r => {
            html += '<tr>'
                + '<td>' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
                + '<td class="small">' + escapeHtml(r.email || '') + '</td>'
                + '<td>' + escapeHtml(r.fonction_nom || '-') + '</td>'
                + '<td><span class="badge ' + (r.choix === 'menu' ? 'bg-primary' : 'bg-success') + '">' + escapeHtml(r.choix) + '</span></td>'
                + '<td>' + r.nb_personnes + '</td>'
                + '<td>' + escapeHtml(r.paiement || '-') + '</td>'
                + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';
        html += '<div class="d-flex gap-3 mt-2"><span><strong>' + res.total_couverts + '</strong> couverts</span>'
            + '<span class="badge bg-primary">' + res.nb_menu + ' menu</span>'
            + '<span class="badge bg-success">' + res.nb_salade + ' salade</span></div>';

        await adminConfirm({
            title: 'Réservations — ' + dateStr + ' (' + repas + ')',
            text: html,
            type: 'info', icon: 'bi-people', okText: 'Fermer', cancelText: ''
        });
    }

    // ═══════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════

    function getMonday(d) {
        const dt = new Date(d);
        const day = dt.getDay();
        dt.setDate(dt.getDate() - day + (day === 0 ? -6 : 1));
        dt.setHours(0, 0, 0, 0);
        return dt;
    }

    function shiftWeek(monday, days) {
        const d = new Date(monday);
        d.setDate(d.getDate() + days);
        return d;
    }

    function fmtDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function fmtDateFr(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    // Initial load
    loadMenus();
})();
</script>
