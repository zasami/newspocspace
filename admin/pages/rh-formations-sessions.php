<?php
$nbSessions  = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions");
$nbOuvertes  = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'ouverte' AND date_debut >= CURDATE()");
$nbCompletes = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'complete'");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-calendar3"></i> Sessions &amp; catalogue</h4>
  <button class="btn btn-sm btn-primary" disabled><i class="bi bi-plus-lg"></i> Nouvelle session</button>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-calendar3"></i></div>
      <div><div class="stat-value"><?= $nbSessions ?></div><div class="stat-label">Sessions au total</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-calendar-check"></i></div>
      <div><div class="stat-value"><?= $nbOuvertes ?></div><div class="stat-label">Ouvertes &amp; à venir</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-calendar-x"></i></div>
      <div><div class="stat-value"><?= $nbCompletes ?></div><div class="stat-label">Complètes</div></div></div>
  </div>
</div>

<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-2"><strong>Phase 3</strong> — CRUD sessions, filtres par mois/secteur/format, sync auto avec catalogue FEGEMS.</p>
    <p class="small">Une formation peut avoir plusieurs sessions (dates) avec des lieux et capacités différents.</p>
  </div>
</div>
