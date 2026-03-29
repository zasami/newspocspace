<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$totalUsers    = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
$pendingAbs    = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$pendingDesirs = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
$currentUserId = $_SESSION['zt_user']['id'] ?? '';
$unreadMsgs    = (int) Db::getOne("SELECT COUNT(*) FROM email_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$currentUserId]);

$recentAbsences = Db::fetchAll(
    "SELECT a.*, u.prenom, u.nom, u.photo
     FROM absences a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 10"
);

$absTypeCls = [
    'vacances'   => 'badge-zt-type-vacances',
    'maladie'    => 'badge-zt-type-maladie',
    'accident'   => 'badge-zt-type-accident',
    'formation'  => 'badge-zt-type-formation',
];
$absStatusCls = [
    'valide'     => 'badge-zt-valid',
    'refuse'     => 'badge-zt-refuse',
    'en_attente' => 'badge-zt-attente',
];
$absStatusLbl = [
    'valide'     => 'Validé',
    'refuse'     => 'Refusé',
    'en_attente' => 'En attente',
];
?>
<style>
.btn-desir-action {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }

.badge-zt-valid          { background: #bcd2cb !important; color: #2d4a43 !important; }
.badge-zt-refuse         { background: #E2B8AE !important; color: #7B3B2C !important; }
.badge-zt-attente        { background: #D4C4A8 !important; color: #6B5B3E !important; }
.badge-zt-type-vacances  { background: #B8C9D4; color: #3B4F6B; }
.badge-zt-type-maladie   { background: #E2B8AE; color: #7B3B2C; }
.badge-zt-type-accident  { background: #D4C4A8; color: #6B5B3E; }
.badge-zt-type-formation { background: #D0C4D8; color: #5B4B6B; }
.badge-zt-type-default   { background: #B8C9D4; color: #3B4F6B; }

.dash-avatar          { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
.dash-avatar-initials {
  width: 30px; height: 30px; border-radius: 50%;
  background: #B8C9D4; color: #3B4F6B;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .7rem;
}
</style>

<!-- Statistiques -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-teal"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Collaborateurs actifs</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-orange"><i class="bi bi-calendar-x"></i></div>
      <div>
        <div class="stat-value"><?= $pendingAbs ?></div>
        <div class="stat-label">Absences en attente</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-green"><i class="bi bi-star"></i></div>
      <div>
        <div class="stat-value"><?= $pendingDesirs ?></div>
        <div class="stat-label">Désirs en attente</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-red"><i class="bi bi-chat-dots"></i></div>
      <div>
        <div class="stat-value"><?= $unreadMsgs ?></div>
        <div class="stat-label">Messages non lus</div>
      </div>
    </div>
  </div>
</div>

<!-- Dernières demandes d'absence -->
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
        <?php if (empty($recentAbsences)): ?>
        <tr><td colspan="6" class="text-center py-4 text-muted">Aucune demande</td></tr>
        <?php else: ?>
        <?php foreach ($recentAbsences as $a):
            $initials = mb_strtoupper(mb_substr($a['prenom'] ?? '', 0, 1) . mb_substr($a['nom'] ?? '', 0, 1));
            $tc = $absTypeCls[$a['type']] ?? 'badge-zt-type-default';
            $sc = $absStatusCls[$a['statut']] ?? 'badge-zt-attente';
            $sl = $absStatusLbl[$a['statut']] ?? h($a['statut']);
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($a['photo'])): ?>
                <img src="<?= h($a['photo']) ?>" alt="" class="dash-avatar">
              <?php else: ?>
                <div class="dash-avatar-initials"><?= h($initials) ?></div>
              <?php endif; ?>
              <strong><?= h($a['prenom'] . ' ' . $a['nom']) ?></strong>
            </div>
          </td>
          <td><span class="badge <?= $tc ?>"><?= h($a['type']) ?></span></td>
          <td><?= h($a['date_debut']) ?></td>
          <td><?= h($a['date_fin']) ?></td>
          <td><span class="badge <?= $sc ?>"><?= h($sl) ?></span></td>
          <td>
            <?php if ($a['statut'] === 'en_attente'): ?>
              <button type="button" class="btn btn-sm btn-desir-action btn-desir-valider" data-quick-valid="<?= h($a['id']) ?>"><i class="bi bi-check-lg"></i></button>
              <button type="button" class="btn btn-sm btn-desir-action btn-desir-refuser" data-quick-refuse="<?= h($a['id']) ?>"><i class="bi bi-x-lg"></i></button>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script<?= nonce() ?>>
// Validation rapide — AJAX puis reload pour données fraîches
document.getElementById('dashRecentAbsences').addEventListener('click', async (e) => {
    const vBtn = e.target.closest('[data-quick-valid]');
    const rBtn = e.target.closest('[data-quick-refuse]');
    if (!vBtn && !rBtn) return;
    const btn = vBtn || rBtn;
    const id     = btn.dataset[vBtn ? 'quickValid' : 'quickRefuse'];
    const statut = vBtn ? 'valide' : 'refuse';
    btn.disabled = true;
    const res = await adminApiPost('admin_validate_absence', { id, statut });
    if (res.success) { showToast(res.message, 'success'); location.reload(); }
    else { showToast(res.message || 'Erreur', 'error'); btn.disabled = false; }
});
</script>
