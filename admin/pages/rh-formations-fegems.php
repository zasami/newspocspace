<?php
$nbPropositions = (int) Db::getOne("SELECT COUNT(*) FROM inscription_propositions WHERE statut = 'proposee'");
$nbSessions     = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'ouverte' AND date_debut >= CURDATE()");
$nbEnvoyees     = (int) Db::getOne("SELECT COUNT(*) FROM inscription_emails WHERE statut_reponse = 'en_attente'");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-cloud-arrow-up"></i> Inscriptions FEGEMS</h4>
  <div>
    <a href="?page=rh-formations-sessions" class="btn btn-sm btn-outline-secondary" data-page-link>
      <i class="bi bi-calendar3"></i> Catalogue
    </a>
    <a href="?page=rh-formations-parametres" class="btn btn-sm btn-outline-secondary" data-page-link>
      <i class="bi bi-gear"></i> Paramètres
    </a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-lightbulb"></i></div>
      <div><div class="stat-value"><?= $nbPropositions ?></div><div class="stat-label">Suggestions en attente</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-calendar3"></i></div>
      <div><div class="stat-value"><?= $nbSessions ?></div><div class="stat-label">Sessions ouvertes</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-envelope"></i></div>
      <div><div class="stat-value"><?= $nbEnvoyees ?></div><div class="stat-label">Emails en attente réponse</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-2"><strong>Phase 3</strong> — bannière "intel" suggestions auto + cards par session + email pré-rempli.</p>
    <p class="small">Le moteur de propositions doit d'abord scanner la cartographie (à venir Phase 2).
    En attendant, gérez le catalogue manuellement via <a href="?page=rh-formations-sessions" data-page-link>Sessions &amp; catalogue</a>.</p>
  </div>
</div>
