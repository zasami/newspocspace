<?php
$nbProfils    = (int) Db::getOne("SELECT COUNT(*) FROM competences_profil_attendu");
$nbThemBase   = (int) Db::getOne("SELECT COUNT(*) FROM competences_thematiques WHERE categorie = 'fegems_base' AND actif = 1");
$nbReferents  = (int) Db::getOne("SELECT COUNT(*) FROM competences_thematiques WHERE categorie IN ('referent','referent_pedago') AND actif = 1");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-bullseye"></i> Profil d'équipe attendu</h4>
  <a href="?page=rh-formations-cartographie" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-diagram-3"></i> Cartographie
  </a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-tags"></i></div>
      <div><div class="stat-value"><?= $nbThemBase ?></div><div class="stat-label">Thématiques de base</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-mortarboard"></i></div>
      <div><div class="stat-value"><?= $nbReferents ?></div><div class="stat-label">Référents (rôles)</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-grid-3x3"></i></div>
      <div><div class="stat-value"><?= $nbProfils ?></div><div class="stat-label">Cellules définies</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-2"><strong>Phase 2</strong> — matrice éditable thématique × secteur (6 secteurs FEGEMS).</p>
    <p class="small">Définit pour chaque case : niveau requis (1-4), part du personnel à former (%), type de formation recommandé.
    Cette matrice est la fondation de la cartographie individuelle.</p>
  </div>
</div>
