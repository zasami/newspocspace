<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$userId = $_GET['id'] ?? '';
if (!$userId) { header('Location: ' . admin_url('users')); exit; }

$detailUser = null;
if ($userId) {
    $detailUser = Db::fetch(
        "SELECT u.*, f.nom AS fonction_nom, f.code AS fonction_code
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.id = ?",
        [$userId]
    );
    if ($detailUser) {
        unset($detailUser['password'], $detailUser['reset_token'], $detailUser['reset_expires']);
        $detailUser['modules'] = Db::fetchAll(
            "SELECT m.id, m.nom, m.code, um.is_principal
             FROM user_modules um
             JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = ?",
            [$userId]
        );
    }
}
?>
<style>
/* User detail page classes */
.ud-width-auto { width: auto; }
.ud-avatar-img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.ud-avatar-initials { width: 56px; height: 56px; border-radius: 50%; background: var(--zt-teal); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; flex-shrink: 0; }
.ud-text-sm { font-size: 0.82rem; }
.ud-badge-horaire-special { background: #9B51E0; }
.ud-badge-permanent { background: rgba(255,193,7,0.15); color: #d4a017; font-size: 0.7rem; border: 1px solid rgba(255,193,7,0.3); }
.ud-badge-dynamic { color: #fff; }
.ud-plan-day-th { min-width: 36px; font-size: 0.72rem; }
.ud-plan-label { font-size: 0.8rem; }
.ud-plan-cell { font-size: 0.75rem; }
.ud-plan-corner { min-width: 40px; }

/* Desir modal */
.ud-desir-header-attente { background: #FEF3C7; border-bottom: 2px solid #F59E0B; }
.ud-desir-header-valide { background: #D1FAE5; border-bottom: 2px solid #10B981; }
.ud-desir-header-refuse { background: #FEE2E2; border-bottom: 2px solid #EF4444; }
.ud-desir-row { cursor: pointer; transition: background .12s; }
.ud-desir-row:hover { background: var(--cl-bg, #F7F5F2) !important; }
.ud-desir-hidden { display: none; }
.btn-desir-valider { background: var(--cl-bg); border: 1px solid var(--cl-border); color: #2d4a43; }
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser { background: var(--cl-bg); border: 1px solid var(--cl-border); color: #7B3B2C; }
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }
</style>
<div id="userDetailPage">
  <div class="mb-3">
    <a href="<?= admin_url('users') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Retour
    </a>
  </div>

  <!-- Header card -->
  <div class="card mb-3" id="userHeaderCard">
    <div class="card-body py-3">
      <div class="text-center py-3 text-muted"><span class="admin-spinner"></span> Chargement...</div>
    </div>
  </div>

  <!-- Modal Permissions -->
  <div class="modal fade" id="udPermModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-shield-check"></i> Accès zerdaTime</h5>
          <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
        </div>
        <div class="modal-body" id="udPermBody">
          <p class="text-muted small">Chargement...</p>
        </div>
        <div class="modal-footer d-flex">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn btn-sm btn-primary ms-auto" id="udPermSaveBtn"><i class="bi bi-check-lg"></i> Sauvegarder</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Désir -->
  <div class="modal fade" id="udDesirModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" id="udDesirHeader">
          <div>
            <h6 class="modal-title mb-0" id="udDesirTitle"></h6>
            <small class="text-muted" id="udDesirSubtitle"></small>
          </div>
          <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" id="udDesirBody"></div>
        <div class="modal-footer d-flex" id="udDesirFooter">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="userTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDesirs">
        <i class="bi bi-star"></i> Désirs
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPermanents">
        <i class="bi bi-pin-angle"></i> Désirs permanents
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAbsences">
        <i class="bi bi-calendar-x"></i> Absences
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPlanning">
        <i class="bi bi-calendar3"></i> Planning
      </button>
    </li>
  </ul>

  <div class="tab-content">
    <!-- Désirs -->
    <div class="tab-pane fade show active" id="tabDesirs">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <strong>Historique des désirs</strong>
          <div class="zs-select ud-width-auto" id="udDesirMois" data-placeholder="Mois"></div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>Horaire</th><th>Détail</th><th>Statut</th></tr></thead>
            <tbody id="udDesirsBody"><tr><td colspan="5" class="text-center text-muted py-3">Chargement...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Désirs permanents -->
    <div class="tab-pane fade" id="tabPermanents">
      <div class="card">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Jour</th><th>Type</th><th>Horaire</th><th>Actif</th></tr></thead>
            <tbody id="udPermanentsBody"><tr><td colspan="4" class="text-center text-muted py-3">Chargement...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Absences -->
    <div class="tab-pane fade" id="tabAbsences">
      <div class="card">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Début</th><th>Fin</th><th>Type</th><th>Motif</th><th>Statut</th></tr></thead>
            <tbody id="udAbsencesBody"><tr><td colspan="5" class="text-center text-muted py-3">Chargement...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Planning -->
    <div class="tab-pane fade" id="tabPlanning">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <strong>Planning mensuel</strong>
          <input type="month" class="form-control form-control-sm ud-width-auto" id="udPlanMois">
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0" id="udPlanTable">
            <thead id="udPlanHead"></thead>
            <tbody id="udPlanBody"><tr><td class="text-center text-muted py-3">Chargement...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const userId = '<?= h($userId) ?>';
    const ssrUser = <?= $detailUser ? json_encode($detailUser, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;
    const joursSemaine = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    const joursComplets = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

    function initUserdetailPage() {
        if (!userId || !ssrUser) {
            showToast('Utilisateur non trouvé', 'error');
            return;
        }
        const u = ssrUser;

        const modules = (u.modules || []).map(m => `<span class="badge bg-info bg-opacity-25 text-dark border me-1">${escapeHtml(m.code)}</span>`).join('');
        const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();

        const avatarHtml = u.photo
            ? `<img src="${escapeHtml(u.photo)}" alt="" class="ud-avatar-img">`
            : `<div class="ud-avatar-initials">${initials}</div>`;

        document.getElementById('userHeaderCard').innerHTML = `
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            ${avatarHtml}
            <div class="flex-grow-1">
              <h5 class="mb-1">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</h5>
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-secondary">${escapeHtml(u.role)}</span>
                <span class="badge ${u.type_employe === 'externe' ? 'bg-warning text-dark' : 'bg-success bg-opacity-75'}">${escapeHtml(u.type_employe || 'interne')}</span>
                ${u.fonction_nom ? `<span class="badge bg-primary bg-opacity-25 text-dark border">${escapeHtml(u.fonction_nom)}</span>` : ''}
                ${modules}
                <span class="text-muted ud-text-sm">${escapeHtml(u.email)}</span>
                ${u.taux ? `<span class="text-muted ud-text-sm">${Math.round(u.taux)}%</span>` : ''}
              </div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-warning btn-sm" id="udPermBtn">
                <i class="bi bi-shield-check"></i> Permissions
              </button>
              <a href="${AdminURL.page('user-edit', u.id)}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil"></i> Modifier
              </a>
            </div>
          </div>
        </div>`;

        // Month selectors
        const now = new Date();
        const planMois = document.getElementById('udPlanMois');
        const moisOpts = [];
        let defaultMoisVal = '';
        for (let i = -6; i <= 2; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
            const val = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
            const label = d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
            moisOpts.push({ value: val, label });
            if (i === 1) defaultMoisVal = val;
        }
        zerdaSelect.init('#udDesirMois', moisOpts, { value: defaultMoisVal, onSelect: loadDesirs });

        const nextM = new Date(now.getFullYear(), now.getMonth()+1, 1);
        planMois.value = `${nextM.getFullYear()}-${String(nextM.getMonth()+1).padStart(2,'0')}`;

        planMois.addEventListener('change', loadPlanning);

        // Permissions button
        document.getElementById('udPermBtn')?.addEventListener('click', () => {
            loadPermissionsModal(userId);
        });

        Promise.all([loadDesirs(), loadPermanents(), loadAbsences(), loadPlanning()]);
    }

    let udDesirsData = [];
    const udDesirModal = new bootstrap.Modal(document.getElementById('udDesirModal'));

    async function loadDesirs() {
        const mois = zerdaSelect.getValue('#udDesirMois');
        const res = await adminApiPost('admin_get_desirs', { statut: '', mois, user_id: userId });
        udDesirsData = res.desirs || [];
        const tbody = document.getElementById('udDesirsBody');

        if (!udDesirsData.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Aucun désir</td></tr>';
            return;
        }

        tbody.innerHTML = udDesirsData.map((d, i) => {
            const stCls = d.statut === 'valide' ? 'success' : d.statut === 'refuse' ? 'danger' : 'warning';
            const stLabel = d.statut === 'valide' ? 'Validé' : d.statut === 'refuse' ? 'Refusé' : 'En attente';
            const isPerm = !!d.permanent_id;
            const typeBadge = d.type === 'jour_off' ? '<span class="badge bg-info">Jour off</span>' : '<span class="badge text-white ud-badge-horaire-special">Horaire spécial</span>';
            const permIcon = isPerm ? ' <span class="badge badge-permanent ud-badge-permanent"><i class="bi bi-pin-angle-fill"></i></span>' : '';
            let horaire = '<span class="text-muted">—</span>';
            if (d.horaire_code) {
                horaire = `<span class="badge ud-badge-dynamic" style="background:${escapeHtml(d.horaire_couleur||'#9B51E0')}">${escapeHtml(d.horaire_code)}</span>`;
            }
            const date = new Date(d.date_souhaitee + 'T00:00:00');
            const dateFmt = `${joursSemaine[date.getDay()]} ${date.getDate()}/${date.getMonth()+1}`;
            return `<tr class="ud-desir-row" data-desir-idx="${i}">
                <td>${dateFmt}</td>
                <td>${typeBadge}${permIcon}</td>
                <td>${horaire}</td>
                <td><small>${escapeHtml(d.detail || '')}</small></td>
                <td><span class="badge bg-${stCls}">${stLabel}</span></td>
            </tr>`;
        }).join('');

        // Click handlers
        tbody.querySelectorAll('.ud-desir-row').forEach(row => {
            row.addEventListener('click', () => openDesirDetail(parseInt(row.dataset.desirIdx)));
        });
    }

    function formatDateDesir(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return `${joursSemaine[d.getDay()]} ${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()}`;
    }

    function openDesirDetail(idx) {
        const d = udDesirsData[idx];
        if (!d) return;

        const header = document.getElementById('udDesirHeader');
        header.className = 'modal-header ' + (d.statut === 'en_attente' ? 'ud-desir-header-attente' : d.statut === 'valide' ? 'ud-desir-header-valide' : 'ud-desir-header-refuse');

        const isPerm = !!d.permanent_id;
        const permLabel = isPerm ? ' <span class="badge ud-badge-permanent"><i class="bi bi-pin-angle-fill"></i> Permanent</span>' : '';
        document.getElementById('udDesirTitle').innerHTML = escapeHtml((d.prenom || '') + ' ' + (d.nom || '')) + permLabel;
        document.getElementById('udDesirSubtitle').textContent = (d.fonction_code || '') + ' — Désir pour le ' + formatDateDesir(d.date_souhaitee);

        const typeBadge = d.type === 'jour_off'
            ? '<span class="badge bg-info"><i class="bi bi-moon"></i> Jour off</span>'
            : '<span class="badge text-white ud-badge-horaire-special"><i class="bi bi-clock"></i> Horaire spécial</span>';

        let horaireBlock = '';
        if (d.type === 'horaire_special' && d.horaire_code) {
            const couleur = d.horaire_couleur || '#9B51E0';
            horaireBlock = `
            <div class="d-flex align-items-center gap-3 p-3 rounded mb-3" style="background:var(--cl-bg);border-left:4px solid ${escapeHtml(couleur)}">
              <span class="fw-bold" style="font-size:1.5rem;color:${escapeHtml(couleur)}">${escapeHtml(d.horaire_code)}</span>
              <div>
                <div class="fw-semibold">${escapeHtml(d.horaire_nom || '')}</div>
                <small class="text-muted">${escapeHtml(d.horaire_debut || '')} — ${escapeHtml(d.horaire_fin || '')}</small>
                ${d.horaire_duree ? `<small class="text-muted ms-2">(${d.horaire_duree}h)</small>` : ''}
              </div>
            </div>`;
        }

        let detailBlock = d.detail ? `<div class="p-2 rounded mb-3" style="background:var(--cl-bg)"><small class="text-muted"><i class="bi bi-pencil"></i> Détail</small><div>${escapeHtml(d.detail)}</div></div>` : '';

        const stCls = d.statut === 'valide' ? 'success' : d.statut === 'refuse' ? 'danger' : 'warning';
        const stLabel = d.statut === 'valide' ? 'Validé' : d.statut === 'refuse' ? 'Refusé' : 'En attente';
        let statusBlock = `<div class="d-flex align-items-center gap-2 mb-3"><span class="badge bg-${stCls}">${stLabel}</span>${d.valide_at ? `<small class="text-muted">le ${formatDateDesir(d.valide_at?.substring(0,10))}</small>` : ''}</div>`;

        let commentBlock = d.commentaire_chef ? `<div class="p-3 rounded" style="background:var(--cl-bg)"><small class="text-muted d-block mb-1"><i class="bi bi-chat-dots"></i> Commentaire</small><span>${escapeHtml(d.commentaire_chef)}</span></div>` : '';

        let refusHtml = d.statut === 'en_attente'
            ? `<div id="udRefusBlock" class="mt-3 ud-desir-hidden"><label class="form-label fw-semibold small"><i class="bi bi-chat-dots"></i> Motif du refus (optionnel)</label><textarea class="form-control form-control-sm" id="udRefusComment" rows="2" placeholder="Raison du refus..."></textarea></div>`
            : '';

        document.getElementById('udDesirBody').innerHTML = `
            <div class="mb-3 d-flex align-items-center justify-content-between">${typeBadge}${d.created_at ? `<small class="text-muted"><i class="bi bi-clock-history"></i> ${formatDateDesir(d.created_at?.substring(0,10))}</small>` : ''}</div>
            ${horaireBlock}${detailBlock}${statusBlock}${commentBlock}${refusHtml}`;

        const footer = document.getElementById('udDesirFooter');
        if (d.statut === 'en_attente') {
            footer.innerHTML = `
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
              <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-desir-refuser btn-sm" id="udBtnRefuser"><i class="bi bi-x-circle"></i> Refuser</button>
                <button type="button" class="btn btn-desir-valider btn-sm" id="udBtnValider"><i class="bi bi-check-circle"></i> Valider</button>
              </div>`;

            footer.querySelector('#udBtnValider').addEventListener('click', () => udValidateDesir(d.id, 'valide'));
            footer.querySelector('#udBtnRefuser').addEventListener('click', () => {
                const block = document.getElementById('udRefusBlock');
                if (block.classList.contains('ud-desir-hidden')) {
                    block.classList.remove('ud-desir-hidden');
                    document.getElementById('udRefusComment').focus();
                    footer.querySelector('#udBtnRefuser').innerHTML = '<i class="bi bi-x-circle"></i> Confirmer le refus';
                    footer.querySelector('#udBtnRefuser').classList.replace('btn-desir-refuser', 'btn-danger');
                } else {
                    udValidateDesir(d.id, 'refuse', document.getElementById('udRefusComment').value.trim());
                }
            });
        } else {
            footer.innerHTML = `<button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-bs-dismiss="modal">Fermer</button>`;
        }

        udDesirModal.show();
    }

    async function udValidateDesir(id, statut, commentaire) {
        const res = await adminApiPost('admin_validate_desir', { id, statut, commentaire: commentaire || '' });
        if (res.success) {
            udDesirModal.hide();
            showToast(statut === 'valide' ? 'Désir validé' : 'Désir refusé', 'success');
            loadDesirs();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    async function loadPermanents() {
        const res = await adminApiPost('admin_get_user_permanents', { user_id: userId });
        const perms = res.permanents || [];
        const tbody = document.getElementById('udPermanentsBody');

        if (!perms.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Aucun désir permanent</td></tr>';
            return;
        }

        tbody.innerHTML = perms.map(p => {
            const jour = joursComplets[p.jour_semaine] || '?';
            const typeBadge = p.type === 'jour_off' ? '<span class="badge bg-info">Jour off</span>' : '<span class="badge text-white ud-badge-horaire-special">Horaire</span>';
            let horaire = '—';
            if (p.horaire_code) {
                horaire = `<span class="badge ud-badge-dynamic" style="background:${escapeHtml(p.horaire_couleur||'#9B51E0')}">${escapeHtml(p.horaire_code)} — ${escapeHtml(p.horaire_nom||'')}</span>`;
            }
            const actif = p.is_active ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>';
            return `<tr><td>${jour}</td><td>${typeBadge}</td><td>${horaire}</td><td>${actif}</td></tr>`;
        }).join('');
    }

    async function loadAbsences() {
        const res = await adminApiPost('admin_get_absences', { user_id: userId });
        const absences = res.absences || [];
        const tbody = document.getElementById('udAbsencesBody');

        if (!absences.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Aucune absence</td></tr>';
            return;
        }

        tbody.innerHTML = absences.map(a => {
            const stCls = a.statut === 'valide' ? 'success' : a.statut === 'refuse' ? 'danger' : 'warning';
            return `<tr>
                <td>${escapeHtml(a.date_debut)}</td>
                <td>${escapeHtml(a.date_fin)}</td>
                <td><span class="badge bg-secondary">${escapeHtml(a.type_absence || '')}</span></td>
                <td><small>${escapeHtml(a.motif || '')}</small></td>
                <td><span class="badge bg-${stCls}">${escapeHtml(a.statut)}</span></td>
            </tr>`;
        }).join('');
    }

    async function loadPlanning() {
        const mois = document.getElementById('udPlanMois').value;
        const res = await adminApiPost('admin_get_planning', { mois });
        const assignations = (res.assignations || []).filter(a => a.user_id === userId);
        const thead = document.getElementById('udPlanHead');
        const tbody = document.getElementById('udPlanBody');

        if (!mois) return;
        const [year, month] = mois.split('-').map(Number);
        const daysInMonth = new Date(year, month, 0).getDate();

        // Header row
        let hdr = '<tr><th class="ud-plan-corner">Jour</th>';
        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month-1, d);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            hdr += `<th class="text-center ud-plan-day-th ${isWeekend?'bg-light':''}">${joursSemaine[date.getDay()]}<br>${d}</th>`;
        }
        thead.innerHTML = hdr + '</tr>';

        // Map by date
        const byDate = {};
        assignations.forEach(a => { byDate[a.date_jour] = a; });

        let row = '<tr><td class="fw-bold ud-plan-label">Horaire</td>';
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const a = byDate[dateStr];
            const date = new Date(year, month-1, d);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            if (a && a.horaire_code) {
                const color = a.couleur || a.horaire_couleur || '#1a1a1a';
                row += `<td class="text-center ud-plan-cell ${isWeekend?'bg-light':''}">
                    <span class="badge ud-badge-dynamic" style="background:${escapeHtml(color)}">${escapeHtml(a.horaire_code)}</span>
                </td>`;
            } else {
                row += `<td class="text-center ud-plan-cell ${isWeekend?'bg-light':''}">—</td>`;
            }
        }
        tbody.innerHTML = row + '</tr>';
    }

    async function loadPermissionsModal(uid) {
        const panel = document.getElementById('udPermBody');
        const modalEl = document.getElementById('udPermModal');
        if (!panel || !modalEl) return;

        const permModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        panel.innerHTML = '<p class="text-muted small">Chargement...</p>';
        permModal.show();

        const res = await adminApiPost('admin_get_user_permissions', { user_id: uid });
        if (!res.success || !res.permissions) {
            panel.innerHTML = '<p class="text-danger small">Erreur chargement permissions</p>';
            return;
        }

        const perms = res.permissions;
        const groups = {
            'Pages': Object.entries(perms).filter(([k]) => k.startsWith('page_')),
            'Cuisine': Object.entries(perms).filter(([k]) => k.startsWith('cuisine_')),
        };

        panel.innerHTML = '';

        // Presets
        const presetRow = document.createElement('div');
        presetRow.className = 'mb-3 d-flex gap-1 flex-wrap';
        const presets = [
            { label: 'Standard (tout)', fn: () => setAll(true) },
            { label: 'Cuisine complet', fn: () => applyPreset(['page_cuisine','cuisine_saisie_menu','cuisine_reservations_collab','cuisine_reservations_famille','cuisine_table_vip','page_messages']) },
            { label: 'Hôtellerie', fn: () => applyPreset(['page_cuisine','cuisine_reservations_famille','cuisine_reservations_collab','page_messages']) },
            { label: 'Aucun accès', fn: () => setAll(false) },
        ];
        presets.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm';
            btn.textContent = p.label;
            btn.addEventListener('click', p.fn);
            presetRow.appendChild(btn);
        });
        panel.appendChild(presetRow);

        // Groups
        for (const [groupName, entries] of Object.entries(groups)) {
            const h = document.createElement('div');
            h.className = 'fw-semibold small mb-1 mt-2';
            h.textContent = groupName;
            panel.appendChild(h);

            entries.forEach(([key, info]) => {
                const wrap = document.createElement('div');
                wrap.className = 'form-check form-switch';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.id = 'udperm_' + key;
                input.dataset.key = key;
                input.checked = info.granted === 1;

                const label = document.createElement('label');
                label.className = 'form-check-label small';
                label.htmlFor = input.id;
                label.textContent = info.label;

                wrap.appendChild(input);
                wrap.appendChild(label);
                panel.appendChild(wrap);
            });
        }

        function setAll(val) {
            panel.querySelectorAll('input[data-key]').forEach(el => { el.checked = val; });
        }

        function applyPreset(allowed) {
            panel.querySelectorAll('input[data-key]').forEach(el => {
                el.checked = allowed.includes(el.dataset.key);
            });
        }

        // Save handler
        document.getElementById('udPermSaveBtn').onclick = async () => {
            const data = {};
            panel.querySelectorAll('input[data-key]').forEach(el => {
                data[el.dataset.key] = el.checked ? 1 : 0;
            });
            const r = await adminApiPost('admin_save_user_permissions', { user_id: uid, permissions: data });
            if (r.success) {
                showToast('Accès mis à jour', 'success');
                permModal.hide();
            } else {
                showToast(r.message || 'Erreur', 'error');
            }
        };
    }

    window.initUserdetailPage = initUserdetailPage;
})();
</script>
