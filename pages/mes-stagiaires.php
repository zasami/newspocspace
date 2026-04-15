<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$today = date('Y-m-d');

// Stagiaires actifs (formateur affecté sur la période courante)
$actifs = Db::fetchAll(
    "SELECT DISTINCT s.id, s.type, s.date_debut, s.date_fin, s.statut,
            u.prenom, u.nom, u.email, u.photo,
            (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
            (SELECT COUNT(*) FROM stagiaire_reports r WHERE r.stagiaire_id = s.id AND r.statut = 'soumis') AS reports_a_valider
     FROM stagiaire_affectations a
     JOIN stagiaires s ON s.id = a.stagiaire_id
     JOIN users u ON u.id = s.user_id
     WHERE a.formateur_id = ?
       AND a.date_debut <= ? AND a.date_fin >= ?
       AND s.statut IN ('prevu','actif')
     ORDER BY s.date_debut DESC",
    [$uid, $today, $today]
);

// Historique (stages terminés ou affectations expirées)
$history = Db::fetchAll(
    "SELECT DISTINCT s.id, s.type, s.date_debut, s.date_fin, s.statut,
            u.prenom, u.nom, u.email, u.photo
     FROM stagiaire_affectations a
     JOIN stagiaires s ON s.id = a.stagiaire_id
     JOIN users u ON u.id = s.user_id
     WHERE a.formateur_id = ?
       AND a.date_fin < ?
     ORDER BY a.date_fin DESC LIMIT 20",
    [$uid, $today]
);

$TYPE_LABELS = [
    'decouverte' => 'Découverte', 'cfc_asa' => 'CFC ASA', 'cfc_ase' => 'CFC ASE',
    'cfc_asfm' => 'CFC ASFM', 'bachelor_inf' => 'Bachelor inf.',
    'civiliste' => 'Civiliste', 'autre' => 'Autre',
];

function render_stagiaire_card($s, $types, $isPast = false) {
    $initials = strtoupper(mb_substr($s['prenom'] ?? '', 0, 1) . mb_substr($s['nom'] ?? '', 0, 1));
    $pending = (int) ($s['reports_a_valider'] ?? 0);
    $cls = 'ms-card' . ($isPast ? ' ms-card-past' : '');
    ob_start(); ?>
    <div class="<?= $cls ?>" data-open-stagiaire="<?= h($s['id']) ?>">
        <div class="ms-card-avatar">
            <?php if (!empty($s['photo'])): ?>
                <img src="<?= h($s['photo']) ?>" alt="">
            <?php else: ?>
                <span><?= h($initials) ?></span>
            <?php endif ?>
        </div>
        <div class="ms-card-body">
            <div class="ms-card-name"><?= h($s['prenom'] . ' ' . $s['nom']) ?></div>
            <div class="ms-card-type">
                <?= h($types[$s['type']] ?? $s['type']) ?>
                <?php if (!empty($s['etage_nom'])): ?> • <?= h($s['etage_nom']) ?><?php endif ?>
            </div>
            <div class="ms-card-period">
                <?= h(fmt_date_fr($s['date_debut'])) ?> → <?= h(fmt_date_fr($s['date_fin'])) ?>
            </div>
            <?php if ($pending > 0): ?>
                <div class="ms-card-pending">
                    <i class="bi bi-bell-fill"></i> <?= $pending ?> report(s) à valider
                </div>
            <?php endif ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>
<div class="ms-wrap">
    <div class="ms-header mb-3">
        <h2 class="ms-title"><i class="bi bi-mortarboard-fill"></i> Mes stagiaires</h2>
        <p class="ms-sub text-muted small mb-0">
            Liste des stagiaires dont vous êtes formateur — validez leurs reports et complétez les évaluations.
        </p>
    </div>

    <h5 class="mt-3 mb-2"><i class="bi bi-person-check"></i> Actifs
        <span class="text-muted small">(<?= count($actifs) ?>)</span>
    </h5>
    <?php if (!$actifs): ?>
        <?= render_empty_state("Aucun stagiaire actif à votre charge", 'bi-person-badge') ?>
    <?php else: ?>
        <div class="ms-grid">
            <?php foreach ($actifs as $s) echo render_stagiaire_card($s, $TYPE_LABELS, false) ?>
        </div>
    <?php endif ?>

    <div class="ms-history-section mt-4">
        <h5><i class="bi bi-clock-history"></i> Historique
            <span class="text-muted small">(<?= count($history) ?>)</span>
        </h5>
        <?php if (!$history): ?>
            <div class="text-muted small">Aucun stagiaire passé.</div>
        <?php else: ?>
            <div class="ms-grid">
                <?php foreach ($history as $s) echo render_stagiaire_card($s, $TYPE_LABELS, true) ?>
            </div>
        <?php endif ?>
    </div>
</div>
