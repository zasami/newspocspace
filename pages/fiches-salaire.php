<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];

$fiches = Db::fetchAll(
    "SELECT id, annee, mois, original_name, size, created_at
     FROM fiches_salaire
     WHERE user_id = ?
     ORDER BY annee DESC, mois DESC",
    [$uid]
);

// Année affichée : ?annee= ou année courante
$currentYear = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');

// Années disponibles (pour navigation)
$yearsAvailable = array_unique(array_map(fn($f) => (int) $f['annee'], $fiches));
rsort($yearsAvailable);
$prevYear = $currentYear - 1;
$nextYear = $currentYear + 1;

// Filtre sur l'année courante
$fichesYear = array_filter($fiches, fn($f) => (int) $f['annee'] === $currentYear);
$byMonth = [];
foreach ($fichesYear as $f) $byMonth[(int) $f['mois']] = $f;

$now = new DateTime();
$maxMonth = ($currentYear === (int) $now->format('Y')) ? (int) $now->format('n') : 12;

$MOIS = ['', 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

function fmt_size($bytes) {
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '') . ' Ko';
    return number_format($bytes / 1048576, 1, ',', '') . ' Mo';
}
?>
<div class="fs-wrap">
    <div class="mb-3">
        <h2 class="page-title mb-0"><i class="bi bi-receipt"></i> Fiches de salaire</h2>
    </div>

    <div class="d-flex gap-2 align-items-center mb-3">
        <a class="btn btn-sm btn-outline-secondary" data-link="fiches-salaire" data-params="annee=<?= $prevYear ?>" href="?annee=<?= $prevYear ?>">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="fw-bold fs-year-label"><?= $currentYear ?></span>
        <a class="btn btn-sm btn-outline-secondary" data-link="fiches-salaire" data-params="annee=<?= $nextYear ?>" href="?annee=<?= $nextYear ?>">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <div class="row g-3">
    <?php if (!$fiches): ?>
        <div class="col-12"><?= render_empty_state('Aucune fiche de salaire', 'bi-receipt') ?></div>
    <?php else: for ($m = $maxMonth; $m >= 1; $m--):
        $fiche = $byMonth[$m] ?? null;
    ?>
        <div class="col-12 col-sm-6 col-md-4">
            <?php if ($fiche): ?>
                <div class="fiche-card" data-fiche-id="<?= h($fiche['id']) ?>">
                    <div class="fiche-icon"><i class="bi bi-file-pdf-fill"></i></div>
                    <div class="fiche-info">
                        <div class="fiche-period"><?= h($MOIS[$m]) ?> <?= $currentYear ?></div>
                        <div class="fiche-meta"><?= h($fiche['original_name']) ?> · <?= h(fmt_size($fiche['size'])) ?></div>
                    </div>
                    <i class="bi bi-download text-primary"></i>
                </div>
            <?php else: ?>
                <div class="fiche-empty-month">
                    <div class="fiche-icon"><i class="bi bi-file-pdf"></i></div>
                    <div>
                        <div class="fiche-period"><?= h($MOIS[$m]) ?> <?= $currentYear ?></div>
                        <div class="fiche-meta">Non disponible</div>
                    </div>
                </div>
            <?php endif ?>
        </div>
    <?php endfor; endif ?>
    </div>
</div>
