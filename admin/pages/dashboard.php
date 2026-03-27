<style>
/* --- Shared action button base --- */
.btn-desir-action {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }

/* --- Status & type badges --- */
.badge-zt-valid { background: #bcd2cb !important; color: #2d4a43 !important; }
.badge-zt-refuse { background: #E2B8AE !important; color: #7B3B2C !important; }
.badge-zt-attente { background: #D4C4A8 !important; color: #6B5B3E !important; }
.badge-zt-type-vacances { background: #B8C9D4; color: #3B4F6B; }
.badge-zt-type-maladie { background: #E2B8AE; color: #7B3B2C; }
.badge-zt-type-accident { background: #D4C4A8; color: #6B5B3E; }
.badge-zt-type-formation { background: #D0C4D8; color: #5B4B6B; }
.badge-zt-type-default { background: #B8C9D4; color: #3B4F6B; }

/* --- Avatar --- */
.dash-avatar {
  width: 30px; height: 30px; border-radius: 50%; object-fit: cover;
}
.dash-avatar-initials {
  width: 30px; height: 30px; border-radius: 50%;
  background: #B8C9D4; color: #3B4F6B;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .7rem;
}
</style>
<div class="row g-3 mb-4" id="dashStats">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-teal"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value" id="statUsers">—</div>
        <div class="stat-label">Collaborateurs actifs</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-orange"><i class="bi bi-calendar-x"></i></div>
      <div>
        <div class="stat-value" id="statAbsences">—</div>
        <div class="stat-label">Absences en attente</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-green"><i class="bi bi-star"></i></div>
      <div>
        <div class="stat-value" id="statDesirs">—</div>
        <div class="stat-label">Désirs en attente</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-red"><i class="bi bi-envelope"></i></div>
      <div>
        <div class="stat-value" id="statMessages">—</div>
        <div class="stat-label">Messages non lus</div>
      </div>
    </div>
  </div>
</div>

<!-- Recent absences -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Dernières demandes d'absence</h6>
    <a href="<?= admin_url('absences') ?>" class="btn btn-sm btn-outline-secondary">Voir tout</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collaborateur</th>
          <th>Type</th>
          <th>Du</th>
          <th>Au</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="dashRecentAbsences">
        <tr><td colspan="6" class="text-center py-4 text-muted">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script<?= nonce() ?>>
async function initDashboardPage() {
    // Event delegation
    document.getElementById('dashRecentAbsences').addEventListener('click', (e) => {
        const vBtn = e.target.closest('[data-quick-valid]');
        if (vBtn) { quickValidateAbsence(vBtn.dataset.quickValid, 'valide'); return; }
        const rBtn = e.target.closest('[data-quick-refuse]');
        if (rBtn) { quickValidateAbsence(rBtn.dataset.quickRefuse, 'refuse'); return; }
    });

    const res = await adminApiPost('admin_get_dashboard_stats');
    if (!res.success) return;

    const s = res.stats;
    document.getElementById('statUsers').textContent = s.total_users;
    document.getElementById('statAbsences').textContent = s.pending_absences;
    document.getElementById('statDesirs').textContent = s.pending_desirs;
    document.getElementById('statMessages').textContent = s.unread_messages;

    // Recent absences
    const tbody = document.getElementById('dashRecentAbsences');
    const absences = res.recent_absences || [];
    if (!absences.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Aucune demande</td></tr>';
        return;
    }

    const typeClasses = { vacances: 'badge-zt-type-vacances', maladie: 'badge-zt-type-maladie', accident: 'badge-zt-type-accident', formation: 'badge-zt-type-formation' };
    const statusClasses = { valide: 'badge-zt-valid', refuse: 'badge-zt-refuse', en_attente: 'badge-zt-attente' };
    const statusLabels = { valide: 'Validé', refuse: 'Refusé', en_attente: 'En attente' };

    function renderAvatar(photo, prenom, nom) {
        const initials = ((prenom||'').charAt(0) + (nom||'').charAt(0)).toUpperCase();
        return photo
            ? `<img src="${escapeHtml(photo)}" alt="" class="dash-avatar">`
            : `<div class="dash-avatar-initials">${initials}</div>`;
    }

    function renderBadge(text, cls) {
        return `<span class="badge ${cls}">${escapeHtml(text)}</span>`;
    }

    tbody.innerHTML = absences.map(a => {
        const typeCls = typeClasses[a.type] || 'badge-zt-type-default';
        const statusCls = statusClasses[a.statut] || 'badge-zt-attente';
        return `<tr>
          <td><div class="d-flex align-items-center gap-2">${renderAvatar(a.photo, a.prenom, a.nom)}<strong>${escapeHtml(a.prenom)} ${escapeHtml(a.nom)}</strong></div></td>
          <td>${renderBadge(a.type, typeCls)}</td>
          <td>${escapeHtml(a.date_debut)}</td>
          <td>${escapeHtml(a.date_fin)}</td>
          <td>${renderBadge(statusLabels[a.statut] || a.statut, statusCls)}</td>
          <td>
            ${a.statut === 'en_attente' ? `
              <button class="btn btn-sm btn-desir-action btn-desir-valider" data-quick-valid="${a.id}"><i class="bi bi-check-lg"></i></button>
              <button class="btn btn-sm btn-desir-action btn-desir-refuser" data-quick-refuse="${a.id}"><i class="bi bi-x-lg"></i></button>
            ` : '<span class="text-muted">—</span>'}
          </td>
        </tr>`;
    }).join('');
}

async function quickValidateAbsence(id, statut) {
    const res = await adminApiPost('admin_validate_absence', { id, statut });
    if (res.success) {
        showToast(res.message, 'success');
        initDashboardPage();
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}

window.initDashboardPage = initDashboardPage;
window.quickValidateAbsence = quickValidateAbsence;
</script>
