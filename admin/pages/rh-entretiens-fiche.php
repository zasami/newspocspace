<?php
$entretienId = $_GET['id'] ?? '';
$entretien = $entretienId ? Db::fetch(
    "SELECT e.*, u.prenom, u.nom, ev.prenom AS eval_prenom, ev.nom AS eval_nom
     FROM entretiens_annuels e
     LEFT JOIN users u ON u.id = e.user_id
     LEFT JOIN users ev ON ev.id = e.evaluator_id
     WHERE e.id = ?", [$entretienId]
) : null;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="bi bi-chat-square-text"></i> Fiche entretien
    <?php if ($entretien): ?>
      <span class="text-muted">— <?= h($entretien['prenom'] . ' ' . $entretien['nom']) ?> · <?= h($entretien['annee']) ?></span>
    <?php endif ?>
  </h4>
  <a href="?page=rh-entretiens" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-arrow-left"></i> Retour
  </a>
</div>

<?php if (!$entretien): ?>
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-clipboard-x" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
      <p class="mb-0">Aucun entretien sélectionné. Cette page s'ouvre depuis la <a href="?page=rh-entretiens" data-page-link>liste des entretiens</a>.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="text-muted small">Collaborateur</div>
          <div class="fw-bold"><?= h($entretien['prenom'] . ' ' . $entretien['nom']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted small">Évaluateur</div>
          <div class="fw-bold"><?= h(($entretien['eval_prenom'] ?? '') . ' ' . ($entretien['eval_nom'] ?? '')) ?: '—' ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Année</div>
          <div class="fw-bold"><?= h($entretien['annee']) ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Date</div>
          <div class="fw-bold"><?= h($entretien['date_entretien'] ?? '—') ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Statut</div>
          <div class="fw-bold"><?= h($entretien['statut']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="card mt-3">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-tools" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
      <p class="mb-0"><strong>Phase 2</strong> — fiche détaillée avec auto-éval collaborateur, notes manager, objectifs, signatures électroniques, génération PDF.</p>
    </div>
  </div>
<?php endif ?>
