<?php
$userId = $_GET['id'] ?? '';
$user = $userId ? Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom, f.secteur_fegems
     FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.id = ?", [$userId]
) : null;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="bi bi-person-badge"></i>
    Fiche compétences
    <?php if ($user): ?>
      <span class="text-muted">— <?= h($user['prenom'] . ' ' . $user['nom']) ?></span>
    <?php endif ?>
  </h4>
  <a href="?page=rh-formations-cartographie" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-arrow-left"></i> Retour cartographie
  </a>
</div>

<?php if (!$user): ?>
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-person-x" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
      <p class="mb-0">Aucun collaborateur sélectionné. Cette page s'ouvre depuis la <a href="?page=rh-formations-cartographie" data-page-link>cartographie d'équipe</a>.</p>
    </div>
  </div>
<?php else:
    $nbCompetences = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE user_id = ?", [$userId]);
    $nbHaute       = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE user_id = ? AND priorite = 'haute'", [$userId]);
?>
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-tags"></i></div>
        <div><div class="stat-value"><?= $nbCompetences ?></div><div class="stat-label">Thématiques</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card"><div class="stat-icon bg-red"><i class="bi bi-exclamation-triangle"></i></div>
        <div><div class="stat-value"><?= $nbHaute ?></div><div class="stat-label">Écarts haute</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-mortarboard"></i></div>
        <div><div class="stat-value"><?= h($user['fonction_nom'] ?? '—') ?></div><div class="stat-label">Fonction</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-bar-chart"></i></div>
        <div><div class="stat-value"><?= h(ucfirst(str_replace('_', ' ', $user['secteur_fegems'] ?? '—'))) ?></div><div class="stat-label">Secteur FEGEMS</div></div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
      <p class="mb-2"><strong>Phase 2</strong> — hero collaborateur + radar par domaine + détail thématiques + objectifs annuels + historique.</p>
      <p class="small">Voir la maquette « Saliha Mansour — Fiche compétences ».</p>
    </div>
  </div>
<?php endif ?>
