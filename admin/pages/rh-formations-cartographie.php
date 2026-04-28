<?php
$nbCollab    = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role IN ('collaborateur','responsable')");
$nbThem      = (int) Db::getOne("SELECT COUNT(*) FROM competences_thematiques WHERE actif = 1");
$nbEvalues   = (int) Db::getOne("SELECT COUNT(DISTINCT user_id) FROM competences_user WHERE niveau_actuel IS NOT NULL");
$nbEcartHaut = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite = 'haute'");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-diagram-3"></i> Cartographie d'équipe</h4>
  <a href="?page=rh-formations-profil" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-bullseye"></i> Profil attendu
  </a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-people"></i></div>
      <div><div class="stat-value"><?= $nbCollab ?></div><div class="stat-label">Collaborateurs actifs</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-tags"></i></div>
      <div><div class="stat-value"><?= $nbThem ?></div><div class="stat-label">Thématiques FEGEMS</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-clipboard-check"></i></div>
      <div><div class="stat-value"><?= $nbEvalues ?></div><div class="stat-label">Évalués</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-red"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="stat-value"><?= $nbEcartHaut ?></div><div class="stat-label">Écarts priorité haute</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-2"><strong>Phase 2</strong> — vue tableau 62 collaborateurs + heatmap secteur×thématique + donut écarts.</p>
    <p class="small">Le squelette de données est en place (<?= $nbThem ?> thématiques · <?= $nbCollab ?> collaborateurs).
    Une fois le profil attendu rempli (<a href="?page=rh-formations-profil" data-page-link>Profil d'équipe attendu</a>),
    la cartographie individuelle pourra être générée.</p>
  </div>
</div>
