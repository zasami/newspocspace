<?php
// ─── Pre-fetch today's collab reservations ───────────────────────────────────
$ssrResaDate = date('Y-m-d');
$ssrResaCollab = Db::fetchAll(
    "SELECT r.id, r.choix, r.nb_personnes, r.remarques, r.paiement, r.statut, r.created_at,
            m.date_jour, m.plat, m.salade,
            u.prenom, u.nom, f.nom AS fonction_nom
     FROM menu_reservations r
     JOIN menus m ON m.id = r.menu_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE m.date_jour = ? AND r.statut = 'confirmee'
     ORDER BY r.choix ASC, r.created_at ASC",
    [$ssrResaDate]
);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Réservations repas</h4>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="resaTabs">
  <li class="nav-item">
    <button class="nav-link active" id="resaTab-collab" data-bs-toggle="tab" data-bs-target="#resaPane-collab">
      <i class="bi bi-people"></i> Collaborateurs
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" id="resaTab-famille" data-bs-toggle="tab" data-bs-target="#resaPane-famille">
      <i class="bi bi-house-heart"></i> Famille / Visiteurs
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ═══ TAB 1: Réservations collaborateurs ═══ -->
  <div class="tab-pane fade show active" id="resaPane-collab">
    <div class="d-flex align-items-center gap-2 mb-3">
      <input type="date" class="form-control form-control-sm" id="rcDate" style="max-width:180px">
      <select class="form-select form-select-sm" id="rcRepas" style="max-width:120px">
        <option value="midi">Midi</option>
        <option value="soir">Soir</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="rcPrintBtn" title="Imprimer">
        <i class="bi bi-printer"></i> Imprimer
      </button>
    </div>
    <div id="rcBody">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>

  <!-- ═══ TAB 2: Réservations famille ═══ -->
  <div class="tab-pane fade" id="resaPane-famille">
    <div class="d-flex align-items-center gap-2 mb-3">
      <input type="date" class="form-control form-control-sm" id="rfDate" style="max-width:180px">
      <select class="form-select form-select-sm" id="rfRepas" style="max-width:120px">
        <option value="midi">Midi</option>
        <option value="soir">Soir</option>
      </select>
    </div>
    <div id="rfBody">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>

</div>

<script<?= nonce() ?>>
(function() {
    const today = fmtDate(new Date());
    const ssrResaCollab = <?= json_encode(array_values($ssrResaCollab), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    // ═══════════════════════════════════════
    // TAB 1: Réservations collaborateurs
    // ═══════════════════════════════════════

    const rcDateInput = document.getElementById('rcDate');
    if (rcDateInput) rcDateInput.value = today;
    rcDateInput?.addEventListener('change', loadCollab);
    document.getElementById('rcRepas')?.addEventListener('change', loadCollab);
    document.getElementById('resaTab-collab')?.addEventListener('shown.bs.tab', loadCollab);

    function renderCollab(reservations) {
        const body = document.getElementById('rcBody');
        if (!body) return;

        if (!reservations.length) {
            body.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-people" style="font-size:2rem;opacity:.3"></i><br>Aucune réservation collaborateur pour cette date</div>';
            return;
        }

        let total = 0, nbMenu = 0, nbSalade = 0;
        reservations.forEach(r => {
            total += parseInt(r.nb_personnes) || 0;
            if (r.choix === 'menu') nbMenu++;
            else if (r.choix === 'salade') nbSalade++;
        });

        let html = '<div class="d-flex gap-3 mb-3 flex-wrap">'
            + '<span class="badge bg-secondary fs-6">' + total + ' couverts</span>'
            + '<span class="badge bg-primary">' + nbMenu + ' menu</span>'
            + '<span class="badge bg-success">' + nbSalade + ' salade</span></div>';

        html += '<div class="card"><div class="card-body p-0"><div class="table-responsive">'
            + '<table class="table table-sm table-striped table-hover mb-0" id="rcTable"><thead><tr>'
            + '<th>Nom</th><th>Fonction</th><th>Plat du jour</th><th>Choix</th><th>Pers.</th><th>Paiement</th><th>Remarques</th>'
            + '</tr></thead><tbody>';
        reservations.forEach(r => {
            html += '<tr>'
                + '<td>' + escapeHtml(r.prenom + ' ' + r.nom) + '</td>'
                + '<td>' + escapeHtml(r.fonction_nom || '-') + '</td>'
                + '<td class="small">' + escapeHtml(r.plat || '-') + '</td>'
                + '<td><span class="badge ' + (r.choix === 'menu' ? 'bg-primary' : 'bg-success') + '">' + escapeHtml(r.choix) + '</span></td>'
                + '<td>' + r.nb_personnes + '</td>'
                + '<td>' + escapeHtml(r.paiement || '-') + '</td>'
                + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div></div></div>';

        body.innerHTML = html;
    }

    async function loadCollab() {
        const body = document.getElementById('rcBody');
        const dateVal = document.getElementById('rcDate')?.value;
        if (!body || !dateVal) return;

        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
        const res = await adminApiPost('admin_get_reservations_jour', { date: dateVal });
        if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }
        renderCollab(res.reservations || []);
    }

    // Print
    document.getElementById('rcPrintBtn')?.addEventListener('click', () => {
        const table = document.getElementById('rcTable');
        if (!table) { showToast('Rien à imprimer', 'error'); return; }
        const dateVal = document.getElementById('rcDate')?.value || '';
        const repasVal = document.getElementById('rcRepas')?.value || 'midi';
        const win = window.open('', '_blank');
        win.document.write('<!DOCTYPE html><html><head><title>Réservations ' + escapeHtml(dateVal) + '</title>'
            + '<style>body{font-family:Arial,sans-serif;padding:20px}h2{margin-bottom:10px}'
            + 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}'
            + 'th{background:#f0f0f0}@media print{button{display:none}}</style></head>'
            + '<body><h2>Réservations collaborateurs — ' + escapeHtml(dateVal) + ' (' + escapeHtml(repasVal) + ')</h2>'
            + table.outerHTML + '<br><button onclick="window.print()">Imprimer</button></body></html>');
        win.document.close();
    });

    // ═══════════════════════════════════════
    // TAB 2: Réservations famille
    // ═══════════════════════════════════════

    const rfDateInput = document.getElementById('rfDate');
    if (rfDateInput) rfDateInput.value = today;
    rfDateInput?.addEventListener('change', loadFamille);
    document.getElementById('rfRepas')?.addEventListener('change', loadFamille);
    document.getElementById('resaTab-famille')?.addEventListener('shown.bs.tab', loadFamille);

    async function loadFamille() {
        const body = document.getElementById('rfBody');
        const dateVal = document.getElementById('rfDate')?.value;
        const repasVal = document.getElementById('rfRepas')?.value || 'midi';
        if (!body || !dateVal) return;

        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
        const res = await adminApiPost('admin_get_reservations_famille', { date: dateVal, repas: repasVal });
        if (!res.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

        const reservations = (res.reservations || []);
        if (!reservations.length) {
            body.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-house-heart" style="font-size:2rem;opacity:.3"></i><br>Aucune réservation famille pour cette date</div>';
            return;
        }

        let html = '<div class="d-flex gap-3 mb-3">'
            + '<span class="badge bg-secondary fs-6">' + (res.total_personnes || 0) + ' personnes</span>'
            + '<span class="badge bg-info text-dark">' + reservations.length + ' réservation' + (reservations.length > 1 ? 's' : '') + '</span></div>';

        html += '<div class="card"><div class="card-body p-0"><div class="table-responsive">'
            + '<table class="table table-sm table-striped table-hover mb-0"><thead><tr>'
            + '<th>Résident</th><th>Chambre</th><th>Visiteur</th><th>Relation</th><th>Pers.</th><th>Remarques</th><th>Créé par</th>'
            + '</tr></thead><tbody>';
        reservations.forEach(r => {
            const visiteurName = r.visiteur_nom_ref
                ? (r.visiteur_prenom_ref + ' ' + r.visiteur_nom_ref)
                : (r.visiteur_nom || '-');
            html += '<tr>'
                + '<td><strong>' + escapeHtml(r.resident_prenom + ' ' + r.resident_nom) + '</strong></td>'
                + '<td>' + escapeHtml(r.chambre || '-') + '</td>'
                + '<td>' + escapeHtml(visiteurName) + '</td>'
                + '<td>' + escapeHtml(r.relation || '-') + '</td>'
                + '<td>' + r.nb_personnes + '</td>'
                + '<td class="small">' + escapeHtml(r.remarques || '-') + '</td>'
                + '<td class="small">' + escapeHtml((r.created_prenom || '') + ' ' + (r.created_nom || '')) + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div></div></div>';

        body.innerHTML = html;
    }

    // ═══════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════

    function fmtDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // Initial render from SSR data — no AJAX
    renderCollab(ssrResaCollab);
})();
</script>
