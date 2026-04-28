<?php
$params = Db::fetchAll("SELECT * FROM parametres_formation WHERE visible = 1 ORDER BY categorie, ordre");
$grouped = [];
foreach ($params as $p) {
    $grouped[$p['categorie']][] = $p;
}

$catLabels = [
    'evaluation'    => ['Évaluation',    'bi-clipboard-check', 'teal'],
    'expirations'   => ['Expirations',   'bi-clock-history',   'orange'],
    'inscriptions'  => ['Inscriptions FEGEMS', 'bi-cloud-arrow-up', 'blue'],
    'entretiens'    => ['Entretiens',    'bi-chat-square-text', 'purple'],
    'budget'        => ['Budget',        'bi-cash-coin',       'green'],
    'referentiel'   => ['Référentiel',   'bi-book',            'sand'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-gear"></i> Paramètres formation</h4>
  <span class="text-muted small"><?= count($params) ?> paramètres · <?= count($grouped) ?> catégories</span>
</div>

<?php foreach ($grouped as $cat => $list):
    [$catLabel, $catIcon, $catColor] = $catLabels[$cat] ?? [ucfirst($cat), 'bi-gear', 'teal'];
?>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="<?= h($catIcon) ?>" style="color:var(--cl-<?= h($catColor) ?>, #2d4a43)"></i>
    <strong><?= h($catLabel) ?></strong>
    <span class="text-muted small ms-auto"><?= count($list) ?> paramètre<?= count($list) > 1 ? 's' : '' ?></span>
  </div>
  <div class="card-body py-2">
    <?php foreach ($list as $p):
        $val = $p['valeur'];
        $defaut = $p['valeur_defaut'];
        $modif = ($val !== $defaut) ? true : false;
    ?>
    <div class="d-flex align-items-start py-2 border-bottom" style="border-color:var(--cl-border-light, #F0EDE8) !important">
      <div class="flex-grow-1 me-3">
        <div class="fw-bold small"><?= h($p['libelle']) ?> <?php if ($modif): ?><span class="badge text-bg-light" style="font-size:.65rem">modifié</span><?php endif ?></div>
        <?php if ($p['description']): ?>
          <div class="text-muted" style="font-size:.78rem"><?= h($p['description']) ?></div>
        <?php endif ?>
        <div class="text-muted" style="font-size:.7rem;font-family:monospace"><?= h($p['cle']) ?></div>
      </div>
      <div class="text-end" style="min-width:160px">
        <?php if ($p['type'] === 'bool'): ?>
          <?php if ($val == '1'): ?>
            <span class="badge" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check2"></i> Activé</span>
          <?php else: ?>
            <span class="badge" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-x"></i> Désactivé</span>
          <?php endif ?>
        <?php elseif ($p['type'] === 'json'): ?>
          <code class="small text-muted"><?= h(strlen($val) > 40 ? substr($val, 0, 40) . '…' : $val) ?></code>
        <?php else: ?>
          <code class="small"><?= h($val !== '' ? $val : '(vide)') ?></code>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endforeach ?>

<div class="card">
  <div class="card-body text-center text-muted py-4">
    <i class="bi bi-tools" style="font-size:1.8rem;opacity:.3;display:block;margin-bottom:8px"></i>
    <p class="mb-0 small"><strong>Phase 4</strong> — édition inline de chaque paramètre, widgets adaptés au type (toggle, slider, dropdown), validation min/max, journalisation des changements.</p>
  </div>
</div>
