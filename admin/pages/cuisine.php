<?php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-egg-fried"></i> Menus de la semaine</h4>
  <span class="badge bg-light text-muted border"><i class="bi bi-info-circle"></i> Lecture seule — les menus sont gérés par l'équipe cuisine</span>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-outline-secondary" id="acMenuPrev"><i class="bi bi-chevron-left"></i></button>
  <span class="fw-bold" id="acMenuWeekLabel"></span>
  <button class="btn btn-sm btn-outline-secondary" id="acMenuNext"><i class="bi bi-chevron-right"></i></button>
</div>

<div id="acMenuBody">
  <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
</div>

<script<?= nonce() ?>>
(function() {
    const DAYS_FR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
    let menuMonday = getMonday(new Date());

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
            table.innerHTML = '<thead><tr><th style="width:70px">Repas</th><th>Entrée</th><th>Plat</th><th>Salade</th><th>Accomp.</th><th>Dessert</th><th>Remarques</th><th style="width:90px">Résa</th><th style="width:50px"></th></tr></thead>';

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

                    // Detail reservations button
                    const actionTd = tr.lastElementChild;
                    if (parseInt(menu.nb_reservations) > 0) {
                        const detailBtn = document.createElement('button');
                        detailBtn.className = 'btn btn-sm btn-outline-primary';
                        detailBtn.innerHTML = '<i class="bi bi-eye"></i>';
                        detailBtn.title = 'Voir réservations';
                        detailBtn.addEventListener('click', () => showMenuReservations(menu.id, dateStr, repas));
                        actionTd.appendChild(detailBtn);
                    }
                } else {
                    tr.innerHTML = '<td><span class="badge bg-light text-muted">' + repas + '</span></td>'
                        + '<td colspan="7" class="text-muted fst-italic">Pas de menu</td><td></td>';
                }
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            cardBody.appendChild(table);
            card.appendChild(cardBody);
            body.appendChild(card);
        }
    }

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
            text: html, type: 'info', icon: 'bi-people', okText: 'Fermer', cancelText: ''
        });
    }

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

    loadMenus();
})();
</script>
