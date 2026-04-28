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
.ud-avatar-initials { width: 56px; height: 56px; border-radius: 50%; background: var(--ss-teal); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; flex-shrink: 0; }
.ud-text-sm { font-size: 0.82rem; }
.ud-badge-horaire-special { background: #9B51E0; }
.ud-badge-permanent { background: rgba(255,193,7,0.15); color: #d4a017; font-size: 0.7rem; border: 1px solid rgba(255,193,7,0.3); }
.ud-badge-dynamic { color: #fff; }
.ud-plan-day-th { min-width: 34px; font-size: 0.68rem; padding: 4px 2px !important; }
.ud-plan-label { font-size: 0.8rem; }
.ud-plan-cell { font-size: 0.72rem; padding: 4px 2px !important; vertical-align: middle; }
.ud-plan-corner { min-width: 40px; }
.ud-plan-sticky { position: sticky; left: 0; background: #fff; z-index: 2; border-right: 2px solid var(--cl-border, #E8E5E0) !important; }
.ud-plan-sticky-end { position: sticky; right: 0; background: #fff; z-index: 2; border-left: 2px solid var(--cl-border, #E8E5E0) !important; }
.ud-plan-weekend { background: #faf8f5 !important; }
.ud-plan-scroll.grabbing { cursor: grabbing; user-select: none; }
.ud-plan-scroll.grabbing * { pointer-events: none; }
.ud-stat-card { flex: 1; min-width: 100px; text-align: center; padding: 12px 8px; border-radius: 12px; background: var(--cl-bg, #F7F5F2); border: 1px solid var(--cl-border-light, #F0EDE8); }
.ud-stat-val { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.ud-stat-lbl { font-size: .72rem; color: var(--cl-text-muted); margin-top: 2px; }

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

/* Permissions modal — Preset cards */
.perm-presets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px; }
.perm-preset-card { position: relative; border: 1.5px solid var(--cl-border, #E8E5E0); border-radius: 12px; padding: 14px 10px 12px; text-align: center; cursor: pointer; transition: all .2s; background: var(--cl-bg, #F7F5F2); }
.perm-preset-card:hover { border-color: var(--cl-border-hover, #D4D0CA); background: var(--cl-surface, #fff); }
.perm-preset-active { border-color: #1A1A1A !important; background: var(--cl-surface, #fff) !important; }
.perm-preset-check { position: absolute; top: 6px; right: 6px; width: 20px; height: 20px; border-radius: 50%; background: #1A1A1A; color: #fff; font-size: .65rem; display: none; align-items: center; justify-content: center; }
.perm-preset-active .perm-preset-check { display: flex; }
.perm-preset-icon { font-size: 1.3rem; color: var(--cl-text-secondary); display: block; margin-bottom: 6px; }
.perm-preset-active .perm-preset-icon { color: #1A1A1A; }
.perm-preset-label { font-weight: 600; font-size: .82rem; margin-bottom: 2px; }
.perm-preset-desc { font-size: .7rem; color: var(--cl-text-muted); line-height: 1.3; }
.perm-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.perm-col-title { font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-secondary); padding-bottom: 6px; margin-bottom: 8px; border-bottom: 1.5px solid var(--cl-border); }
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
    <div class="modal-dialog modal-dialog-centered" style="max-width:620px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-shield-check"></i> Accès SpocSpace</h5>
          <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" id="udPermBody">
          <p class="text-muted small">Chargement...</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
          <button type="button" class="btn btn-sm btn-primary" id="udPermSaveBtn"><i class="bi bi-check-lg"></i> Sauvegarder</button>
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
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <strong><i class="bi bi-calendar3 me-1"></i> Planning mensuel</strong>
          <input type="month" class="form-control form-control-sm ud-width-auto" id="udPlanMois">
        </div>
        <div class="table-responsive ud-plan-scroll" id="udPlanScroll" style="overflow-x:auto;cursor:grab">
          <table class="table table-sm table-bordered mb-0" id="udPlanTable">
            <thead id="udPlanHead"></thead>
            <tbody id="udPlanBody"><tr><td class="text-center text-muted py-3">Chargement...</td></tr></tbody>
          </table>
        </div>
      </div>
      <!-- Recap -->
      <div class="row g-3" id="udPlanRecap"></div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const userId = '<?= h($userId) ?>';
    const ssrUser = <?= $detailUser ? json_encode($detailUser, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;
    const joursSemaine = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    const joursComplets = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

    function initUserDetailPage() {
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
              <a href="${AdminURL.page('rh-collab-competences', u.id)}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-bullseye"></i> Compétences
              </a>
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
        if (!mois) return;

        const [year, month] = mois.split('-').map(Number);
        const daysInMonth = new Date(year, month, 0).getDate();
        const u = ssrUser;
        const taux = Math.round(u.taux || 0);

        // Fetch planning + refs + desirs + absences + rules in parallel
        const [planRes, refsRes, desirsRes, absRes, rulesRes] = await Promise.all([
            adminApiPost('admin_get_planning', { mois }),
            adminApiPost('admin_get_planning_refs'),
            adminApiPost('admin_get_desirs', { mois, user_id: userId }),
            adminApiPost('admin_get_absences'),
            adminApiPost('admin_get_ia_rules'),
        ]);

        const assignations = (planRes.assignations || []).filter(a => a.user_id === userId);
        const horaires = refsRes.horaires || [];
        const hMap = {};
        horaires.forEach(h => { hMap[h.id] = h; });

        const desirs = (desirsRes.desirs || []).filter(d => d.mois_cible === mois);
        const absences = (absRes.absences || []).filter(a => a.user_id === userId && a.date_debut <= `${mois}-31` && a.date_fin >= `${mois}-01`);

        // Rules for this user
        const allRules = rulesRes.rules || [];
        const userRules = allRules.filter(r => {
            if (!r.actif) return false;
            if (r.target_mode === 'all') return true;
            if (r.target_mode === 'users' && (r.targeted_users || []).some(tu => tu.id === userId)) return true;
            if (r.target_mode === 'fonction' && r.target_fonction_code === u.fonction_code) return true;
            return false;
        });

        const thead = document.getElementById('udPlanHead');
        const tbody = document.getElementById('udPlanBody');

        // Absence map
        const absMap = {};
        absences.filter(a => a.statut === 'valide').forEach(a => {
            const s = new Date(a.date_debut + 'T00:00:00'), e = new Date(a.date_fin + 'T00:00:00');
            for (let dt = new Date(s); dt <= e; dt.setDate(dt.getDate() + 1)) {
                absMap[dt.toISOString().slice(0, 10)] = a.type_absence || 'absence';
            }
        });

        // Desir map
        const desirMap = {};
        desirs.filter(d => d.statut === 'valide').forEach(d => { desirMap[d.date_souhaitee] = d; });

        // Header: Nom | % | 1 | 2 | ... | 31 | Total
        let hdr = '<tr><th class="ud-plan-sticky" style="min-width:120px">Collaborateur</th><th class="text-center ud-plan-day-th" style="min-width:40px">%</th>';
        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month - 1, d);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            hdr += `<th class="text-center ud-plan-day-th ${isWeekend ? 'ud-plan-weekend' : ''}">${joursSemaine[date.getDay()]}<br><small>${d}</small></th>`;
        }
        hdr += '<th class="text-center ud-plan-day-th ud-plan-sticky-end" style="min-width:55px">Heures</th></tr>';
        thead.innerHTML = hdr;

        // Body row
        const byDate = {};
        assignations.forEach(a => { byDate[a.date_jour] = a; });

        let totalHours = 0;
        let row = `<tr><td class="ud-plan-sticky fw-bold" style="font-size:.82rem"><i class="bi bi-person-fill me-1"></i>${escapeHtml(u.prenom + ' ' + u.nom)}</td>`;
        row += `<td class="text-center" style="font-size:.78rem;font-weight:600">${taux}%</td>`;

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const a = byDate[dateStr];
            const date = new Date(year, month - 1, d);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            const isAbs = absMap[dateStr];
            const isDesir = desirMap[dateStr];

            let cls = 'text-center ud-plan-cell';
            if (isWeekend) cls += ' ud-plan-weekend';

            if (isAbs && !a) {
                row += `<td class="${cls}" style="background:#FEE2E2" title="${escapeHtml(isAbs)}"><span style="font-size:.65rem;color:#7B3B2C">ABS</span></td>`;
            } else if (a && a.horaire_code) {
                const color = a.couleur || a.horaire_couleur || '#6c757d';
                const h = hMap[a.horaire_type_id];
                if (h) totalHours += parseFloat(h.duree_effective) || 0;
                const desirIcon = (a.notes && a.notes.includes('desir')) ? '<i class="bi bi-emoji-smile" style="font-size:.5rem;color:#e8a838;position:absolute;top:-1px;right:-1px"></i>' : '';
                const modCode = a.module_code ? `<br><span style="font-size:.55rem;color:var(--cl-text-muted)">${escapeHtml(a.module_code)}</span>` : '';
                row += `<td class="${cls}"><span class="badge ud-badge-dynamic" style="background:${escapeHtml(color)};position:relative;font-size:.68rem">${escapeHtml(a.horaire_code)}${desirIcon}</span>${modCode}</td>`;
            } else {
                row += `<td class="${cls}"><span class="text-muted" style="font-size:.7rem">—</span></td>`;
            }
        }
        const targetH = Math.round(21 * 7.6 * (taux / 100));
        const pct = targetH > 0 ? Math.round(totalHours / targetH * 100) : 0;
        const hColor = pct >= 90 ? '#198754' : pct >= 70 ? '#e8a838' : '#dc3545';
        row += `<td class="text-center ud-plan-sticky-end" style="font-size:.78rem;font-weight:700;color:${hColor}">${totalHours.toFixed(1)}h<br><small style="font-weight:400;color:var(--cl-text-muted)">${targetH}h</small></td></tr>`;
        tbody.innerHTML = row;

        // ── Recap cards ──
        const recap = document.getElementById('udPlanRecap');
        if (!recap) return;

        // Desirs card
        const desirRows = desirs.length ? desirs.map(d => {
            const stCls = d.statut === 'valide' ? 'success' : d.statut === 'refuse' ? 'danger' : 'warning';
            const dt = new Date(d.date_souhaitee + 'T00:00:00');
            const fulfilled = byDate[d.date_souhaitee] && d.type === 'horaire_special' && byDate[d.date_souhaitee].horaire_code === d.horaire_code;
            const icon = d.statut === 'valide' ? (fulfilled ? '✓' : '⚠') : '';
            return `<tr>
                <td style="font-size:.78rem">${joursComplets[dt.getDay()]} ${dt.getDate()}</td>
                <td style="font-size:.78rem">${d.type === 'jour_off' ? '<span class="badge bg-secondary">Jour off</span>' : `<span class="badge" style="background:${escapeHtml(d.horaire_couleur || '#666')};color:#fff">${escapeHtml(d.horaire_code || '?')}</span>`}</td>
                <td><span class="badge bg-${stCls}" style="font-size:.68rem">${escapeHtml(d.statut)}</span></td>
                <td style="font-size:.78rem">${icon}</td>
            </tr>`;
        }).join('') : '<tr><td colspan="4" class="text-center text-muted py-2" style="font-size:.82rem">Aucun désir ce mois</td></tr>';

        // Absences card
        const absRows = absences.length ? absences.map(a => {
            const stCls = a.statut === 'valide' ? 'success' : a.statut === 'refuse' ? 'danger' : 'warning';
            return `<tr>
                <td style="font-size:.78rem">${escapeHtml(a.date_debut)} → ${escapeHtml(a.date_fin)}</td>
                <td style="font-size:.78rem"><span class="badge bg-secondary">${escapeHtml(a.type_absence || '?')}</span></td>
                <td><span class="badge bg-${stCls}" style="font-size:.68rem">${escapeHtml(a.statut)}</span></td>
            </tr>`;
        }).join('') : '<tr><td colspan="3" class="text-center text-muted py-2" style="font-size:.82rem">Aucune absence</td></tr>';

        // Rules card
        const ruleTypeLabels = { user_schedule: 'Horaire unique', shift_only: 'Horaires autorisés', shift_exclude: 'Horaires exclus', days_only: 'Jours autorisés', module_only: 'Modules autorisés', module_exclude: 'Modules exclus', no_weekend: 'Pas de weekend', max_days_week: 'Max jours/sem.' };
        const rulesRows = userRules.length ? userRules.map(r => {
            const p = typeof r.rule_params === 'string' ? JSON.parse(r.rule_params || '{}') : (r.rule_params || {});
            let detail = ruleTypeLabels[r.rule_type] || r.rule_type || 'Texte libre';
            if (p.shift_codes?.length) detail += ' : ' + p.shift_codes.join(', ');
            if (p.days?.length) { const dn = ['','Lun','Mar','Mer','Jeu','Ven','Sam','Dim']; detail += ' | Jours: ' + p.days.map(d => dn[d]).join(', '); }
            return `<tr>
                <td style="font-size:.78rem">${escapeHtml(r.titre)}</td>
                <td style="font-size:.78rem">${escapeHtml(detail)}</td>
                <td><span class="badge bg-${r.importance === 'important' ? 'danger' : 'warning'}" style="font-size:.65rem">${escapeHtml(r.importance)}</span></td>
            </tr>`;
        }).join('') : '<tr><td colspan="3" class="text-center text-muted py-2" style="font-size:.82rem">Aucune règle spécifique</td></tr>';

        // Stats
        const nbJours = assignations.length;
        const nbDesirRespectes = desirs.filter(d => d.statut === 'valide').filter(d => {
            if (d.type === 'jour_off') return !byDate[d.date_souhaitee];
            return byDate[d.date_souhaitee] && byDate[d.date_souhaitee].horaire_code === d.horaire_code;
        }).length;
        const nbDesirTotal = desirs.filter(d => d.statut === 'valide').length;

        recap.innerHTML = `
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3 mb-3">
                <div class="ud-stat-card"><div class="ud-stat-val" style="color:#2d4a43">${nbJours}</div><div class="ud-stat-lbl">Jours planifiés</div></div>
                <div class="ud-stat-card"><div class="ud-stat-val" style="color:${hColor}">${totalHours.toFixed(1)}h</div><div class="ud-stat-lbl">sur ${targetH}h cible</div></div>
                <div class="ud-stat-card"><div class="ud-stat-val" style="color:#e8a838">${nbDesirRespectes}/${nbDesirTotal}</div><div class="ud-stat-lbl">Désirs respectés</div></div>
                <div class="ud-stat-card"><div class="ud-stat-val" style="color:#7B3B2C">${Object.keys(absMap).length}</div><div class="ud-stat-lbl">Jours d'absence</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-header py-2"><strong style="font-size:.85rem"><i class="bi bi-star me-1"></i> Désirs</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th style="font-size:.72rem">Date</th><th style="font-size:.72rem">Type</th><th style="font-size:.72rem">Statut</th><th style="font-size:.72rem"></th></tr></thead><tbody>${desirRows}</tbody></table></div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-header py-2"><strong style="font-size:.85rem"><i class="bi bi-calendar-x me-1"></i> Absences</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th style="font-size:.72rem">Période</th><th style="font-size:.72rem">Type</th><th style="font-size:.72rem">Statut</th></tr></thead><tbody>${absRows}</tbody></table></div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-header py-2"><strong style="font-size:.85rem"><i class="bi bi-sliders me-1"></i> Règles IA appliquées</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th style="font-size:.72rem">Règle</th><th style="font-size:.72rem">Détail</th><th style="font-size:.72rem">Priorité</th></tr></thead><tbody>${rulesRows}</tbody></table></div></div>
        </div>`;
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
        const pageEntries = Object.entries(perms).filter(([k]) => k.startsWith('page_'));
        const cuisineEntries = Object.entries(perms).filter(([k]) => k.startsWith('cuisine_'));
        const allPageKeys = pageEntries.map(([k]) => k);
        const allCuisineKeys = cuisineEntries.map(([k]) => k);
        const allKeys = [...allPageKeys, ...allCuisineKeys];
        const soinsKeys = allKeys.filter(k => k !== 'page_emails');

        const presets = [
            { id: 'standard', label: 'Standard', desc: 'Accès complet (email inclus)', icon: 'bi-person-check', keys: null },
            { id: 'infirmiere', label: 'Infirmière', desc: 'Tout accès + email', icon: 'bi-heart-pulse', keys: null },
            { id: 'assc', label: 'ASSC', desc: 'Tout sauf email externe', icon: 'bi-clipboard2-pulse', keys: soinsKeys },
            { id: 'aide_soignant', label: 'Aide soignant', desc: 'Tout sauf email et cuisine', icon: 'bi-bandaid',
              keys: allPageKeys.filter(k => k !== 'page_emails' && k !== 'page_cuisine') },
            { id: 'cuisine', label: 'Cuisine complet', desc: 'Cuisine + menus + réservations', icon: 'bi-egg-fried',
              keys: ['page_cuisine','cuisine_saisie_menu','cuisine_reservations_collab','cuisine_reservations_famille','cuisine_table_vip','page_messages'] },
            { id: 'hotellerie', label: 'Hôtellerie', desc: 'Cuisine + réservations famille', icon: 'bi-building',
              keys: ['page_cuisine','cuisine_reservations_famille','cuisine_reservations_collab','page_messages'] },
            { id: 'none', label: 'Aucun accès', desc: 'Tout désactivé', icon: 'bi-slash-circle', keys: [] },
        ];

        function detectActivePreset() {
            for (const p of presets) {
                if (p.keys === null) {
                    if ([...panel.querySelectorAll('input[data-key]')].every(el => el.checked)) return p.id;
                } else if (p.keys.length === 0) {
                    if ([...panel.querySelectorAll('input[data-key]')].every(el => !el.checked)) return p.id;
                } else {
                    const match = [...panel.querySelectorAll('input[data-key]')].every(el =>
                        p.keys.includes(el.dataset.key) ? el.checked : !el.checked
                    );
                    if (match) return p.id;
                }
            }
            return null;
        }

        function updatePresetCards() {
            const active = detectActivePreset();
            panel.querySelectorAll('.perm-preset-card').forEach(card => {
                card.classList.toggle('perm-preset-active', card.dataset.preset === active);
            });
        }

        function setAll(val) {
            panel.querySelectorAll('input[data-key]').forEach(el => { el.checked = val; });
            updatePresetCards();
        }

        function applyPreset(keys) {
            if (keys === null) { setAll(true); return; }
            panel.querySelectorAll('input[data-key]').forEach(el => {
                el.checked = keys.includes(el.dataset.key);
            });
            updatePresetCards();
        }

        panel.innerHTML =
            '<div class="perm-presets-grid">'
            + presets.map(p =>
                '<div class="perm-preset-card" data-preset="' + p.id + '">'
                + '<div class="perm-preset-check"><i class="bi bi-check-lg"></i></div>'
                + '<i class="bi ' + p.icon + ' perm-preset-icon"></i>'
                + '<div class="perm-preset-label">' + p.label + '</div>'
                + '<div class="perm-preset-desc">' + p.desc + '</div>'
                + '</div>'
            ).join('')
            + '</div>'
            + '<div class="perm-columns">'
            + '<div class="perm-col">'
            + '<div class="perm-col-title">Général</div>'
            + pageEntries.map(([key, info]) =>
                '<div class="form-check form-switch">'
                + '<input type="checkbox" class="form-check-input" id="udp_' + key + '" data-key="' + key + '"' + (info.granted === 1 ? ' checked' : '') + '>'
                + '<label class="form-check-label small" for="udp_' + key + '">' + escapeHtml(info.label) + '</label>'
                + '</div>'
            ).join('')
            + '</div>'
            + '<div class="perm-col">'
            + '<div class="perm-col-title">Cuisine</div>'
            + cuisineEntries.map(([key, info]) =>
                '<div class="form-check form-switch">'
                + '<input type="checkbox" class="form-check-input" id="udp_' + key + '" data-key="' + key + '"' + (info.granted === 1 ? ' checked' : '') + '>'
                + '<label class="form-check-label small" for="udp_' + key + '">' + escapeHtml(info.label) + '</label>'
                + '</div>'
            ).join('')
            + '</div>'
            + '</div>';

        panel.querySelectorAll('.perm-preset-card').forEach(card => {
            card.addEventListener('click', () => {
                const p = presets.find(x => x.id === card.dataset.preset);
                if (p) applyPreset(p.keys);
            });
        });

        panel.querySelectorAll('input[data-key]').forEach(el => {
            el.addEventListener('change', updatePresetCards);
        });

        updatePresetCards();

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

    // ── Drag-to-scroll on planning grid ──
    (function() {
        const el = document.getElementById('udPlanScroll');
        if (!el) return;
        let down = false, startX, scrollL;
        el.addEventListener('mousedown', e => {
            if (e.target.closest('a,button,input,select')) return;
            down = true; startX = e.pageX - el.offsetLeft; scrollL = el.scrollLeft;
            el.classList.add('grabbing');
        });
        el.addEventListener('mousemove', e => {
            if (!down) return;
            e.preventDefault();
            el.scrollLeft = scrollL - (e.pageX - el.offsetLeft - startX);
        });
        const stop = () => { down = false; el.classList.remove('grabbing'); };
        el.addEventListener('mouseup', stop);
        el.addEventListener('mouseleave', stop);
    })();

    window.initUserDetailPage = initUserDetailPage;
    initUserDetailPage();
})();
</script>
