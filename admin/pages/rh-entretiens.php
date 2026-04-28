<?php
$today      = date('Y-m-d');
$nbPlanifies = (int) Db::getOne("SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien >= ?", [$today]);
$nbRealises  = (int) Db::getOne("SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'realise' AND YEAR(date_entretien) = YEAR(CURDATE())");
$nbEnRetard  = (int) Db::getOne("SELECT COUNT(*) FROM entretiens_annuels WHERE statut = 'planifie' AND date_entretien < ?", [$today]);
$nbAEcheance = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1 AND prochain_entretien_date IS NOT NULL AND prochain_entretien_date <= DATE_ADD(?, INTERVAL 30 DAY)", [$today]);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-chat-square-text"></i> Entretiens annuels</h4>
  <button class="btn btn-sm btn-primary" disabled><i class="bi bi-plus-lg"></i> Planifier un entretien</button>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-calendar-event"></i></div>
      <div><div class="stat-value"><?= $nbPlanifies ?></div><div class="stat-label">Planifiés à venir</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="stat-value"><?= $nbRealises ?></div><div class="stat-label">Réalisés cette année</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-red"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="stat-value"><?= $nbEnRetard ?></div><div class="stat-label">En retard</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-clock"></i></div>
      <div><div class="stat-value"><?= $nbAEcheance ?></div><div class="stat-label">À échéance (30j)</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-2"><strong>Phase 2</strong> — calendrier des entretiens, fiches détail, signatures électroniques, lien automatique vers la cartographie compétences du collaborateur.</p>
    <p class="small">Configurable depuis <a href="?page=rh-formations-parametres" data-page-link>Paramètres formation</a> (catégorie Entretiens).</p>
  </div>
</div>
