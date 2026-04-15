<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];

// ─── Chargement des données ───
$stag = Db::fetch(
    "SELECT s.*,
            (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom,
            (SELECT CONCAT(r.prenom,' ',r.nom) FROM users r WHERE r.id = s.ruv_id) AS ruv_nom,
            (SELECT CONCAT(f.prenom,' ',f.nom) FROM users f WHERE f.id = s.formateur_principal_id) AS formateur_nom
     FROM stagiaires s
     WHERE s.user_id = ?
     ORDER BY s.date_debut DESC LIMIT 1",
    [$uid]
);

if (!$stag) {
    echo '<div class="mst-wrap">';
    echo render_page_header('Mon stage', 'bi-journal-text');
    echo render_empty_state('Aucun stage enregistré pour votre compte.', 'bi-info-circle',
        'Si vous devriez avoir un stage actif, contactez votre RUV ou l\'administration.');
    echo '</div>';
    exit;
}

$reports = Db::fetchAll(
    "SELECT r.*, (SELECT CONCAT(u.prenom,' ',u.nom) FROM users u WHERE u.id = r.validated_by) AS valideur_nom
     FROM stagiaire_reports r WHERE r.stagiaire_id = ?
     ORDER BY r.date_report DESC",
    [$stag['id']]
);

// Charger les tâches pour chaque report
foreach ($reports as &$rep) {
    $rep['taches'] = Db::fetchAll(
        "SELECT rt.*, c.nom AS tache_nom, c.categorie, c.code
         FROM stagiaire_report_taches rt
         JOIN stagiaire_taches_catalogue c ON c.id = rt.tache_id
         WHERE rt.report_id = ?",
        [$rep['id']]
    );
}
unset($rep);

$evals = Db::fetchAll(
    "SELECT e.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom
     FROM stagiaire_evaluations e
     JOIN users u ON u.id = e.formateur_id
     WHERE e.stagiaire_id = ? AND e.periode IN ('mi_stage','finale')
     ORDER BY e.date_eval DESC",
    [$stag['id']]
);

$objs = Db::fetchAll(
    "SELECT * FROM stagiaire_objectifs WHERE stagiaire_id = ? ORDER BY created_at DESC",
    [$stag['id']]
);

$formsActifs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, a.date_debut, a.date_fin, a.role_formateur
     FROM stagiaire_affectations a
     JOIN users u ON u.id = a.formateur_id
     WHERE a.stagiaire_id = ? AND a.date_fin >= CURDATE()
     ORDER BY a.date_debut",
    [$stag['id']]
);

// ─── Calculs stats ───
$nBrouillon = count(array_filter($reports, fn($r) => $r['statut'] === 'brouillon'));
$nSoumis    = count(array_filter($reports, fn($r) => $r['statut'] === 'soumis'));
$nValides   = count(array_filter($reports, fn($r) => $r['statut'] === 'valide'));
$nRefaire   = count(array_filter($reports, fn($r) => $r['statut'] === 'a_refaire'));
$objsAtteints = count(array_filter($objs, fn($o) => $o['statut'] === 'atteint'));

// Moyenne évaluations
$noteKeys = ['note_initiative','note_communication','note_connaissances','note_autonomie','note_savoir_etre','note_ponctualite'];
$allEvals = Db::fetchAll("SELECT * FROM stagiaire_evaluations WHERE stagiaire_id = ?", [$stag['id']]);
$sum = 0; $nNotes = 0;
foreach ($allEvals as $e) foreach ($noteKeys as $k) { if ($e[$k]) { $sum += (int)$e[$k]; $nNotes++; } }
$moyenne = $nNotes ? number_format($sum / $nNotes, 1, '.', '') : null;

// Progression du stage
$today = new DateTime();
$start = new DateTime($stag['date_debut']);
$end = new DateTime($stag['date_fin']);
$total = max(1, $end->diff($start)->days);
$done = max(0, min($total, (int)$today->diff($start)->days));
$progressPct = (int) round(($done / $total) * 100);
$remainingDays = max(0, (int) $today->diff($end)->days);
if ($today > $end) $remainingDays = 0;

$TYPE_LABELS = [
    'decouverte' => 'Découverte', 'cfc_asa' => 'CFC ASA', 'cfc_ase' => 'CFC ASE',
    'cfc_asfm' => 'CFC ASFM', 'bachelor_inf' => 'Bachelor inf.',
    'civiliste' => 'Civiliste', 'autre' => 'Autre',
];

$NIVEAU_LABELS = [
    'acquis' => 'Acquis', 'en_cours' => 'En cours',
    'non_acquis' => 'Non acquis', 'non_evalue' => 'À évaluer',
];
?>
<div class="mst-wrap">
    <div class="mst-header mb-3">
        <h2 class="mst-title"><i class="bi bi-journal-text"></i> Mon stage</h2>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-3">
        <?= render_stat_card('Reports validés', $nValides, 'bi-check-circle', 'teal', count($reports) . ' au total') ?>
        <?= render_stat_card('À valider', $nSoumis, 'bi-clock-history', 'orange', $nSoumis ? 'chez le formateur' : null) ?>
        <?= render_stat_card('À refaire', $nRefaire, 'bi-arrow-counterclockwise', 'red', $nRefaire ? 'corrections demandées' : null) ?>
        <?= render_stat_card('Objectifs atteints', $objsAtteints, 'bi-bullseye', 'green', count($objs) ? 'sur ' . count($objs) : null) ?>
        <?= render_stat_card('Évaluations', count($allEvals), 'bi-clipboard-check', 'purple', $moyenne ? "Moy. $moyenne/5" : 'aucune') ?>
        <?= render_stat_card('Jours restants', $remainingDays, 'bi-calendar-event', 'neutral', "$progressPct% du stage") ?>
    </div>

    <!-- Fiche identité stage -->
    <div class="card mst-info-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Mon stage</h5>
                <?= render_statut_badge($stag['statut']) ?>
            </div>
            <div class="mst-info-grid">
                <div><span class="ms-lbl">Type</span><div><?= h($TYPE_LABELS[$stag['type']] ?? $stag['type']) ?></div></div>
                <div><span class="ms-lbl">Période</span><div><?= h(fmt_date_fr($stag['date_debut'])) ?> → <?= h(fmt_date_fr($stag['date_fin'])) ?></div></div>
                <div><span class="ms-lbl">Étage</span><div><?= h($stag['etage_nom'] ?: '—') ?></div></div>
                <div><span class="ms-lbl">RUV</span><div><?= h($stag['ruv_nom'] ?: '—') ?></div></div>
                <div><span class="ms-lbl">Formateur principal</span><div><?= h($stag['formateur_nom'] ?: '—') ?></div></div>
            </div>
            <?php if ($stag['objectifs_generaux']): ?>
                <div class="mt-2">
                    <span class="ms-lbl">Objectifs du stage</span>
                    <div class="small"><?= h($stag['objectifs_generaux']) ?></div>
                </div>
            <?php endif ?>
            <?php if ($formsActifs): ?>
                <div class="mt-2">
                    <span class="ms-lbl">Formateurs affectés actuellement</span>
                    <div class="small">
                        <?php foreach ($formsActifs as $i => $f): ?>
                            <?php if ($i > 0) echo ' · ' ?>
                            <?= h($f['prenom'] . ' ' . $f['nom']) ?>
                            <span class="text-muted">(<?= h($f['role_formateur']) ?>)</span>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>
            <div class="mt-3">
                <?= render_progress_bar($progressPct, 'Progression') ?>
            </div>
        </div>
    </div>

    <!-- Reports -->
    <div class="mst-section">
        <div class="mst-section-head mb-2">
            <h4 class="mb-0">
                <i class="bi bi-journal-text"></i> Mes reports
                <span class="text-muted small">(<?= count($reports) ?>)</span>
            </h4>
            <button class="btn btn-sm btn-primary" data-link="report-edit">
                <i class="bi bi-plus-lg"></i> Nouveau report
            </button>
        </div>

        <?php if (!$reports): ?>
            <?= render_empty_state("Aucun report pour l'instant", 'bi-journal-text', 'Rédigez votre premier report !') ?>
        <?php else: foreach ($reports as $r): ?>
            <div class="ms-report">
                <div class="ms-report-head">
                    <strong><?= h(fmt_date_fr($r['date_report'])) ?></strong>
                    <?= render_type_badge($r['type']) ?>
                    <?= render_statut_badge($r['statut']) ?>
                    <span class="text-muted small ms-auto"><?= count($r['taches']) ?> tâche(s)</span>
                </div>
                <?php if ($r['titre']): ?>
                    <div class="ms-report-title"><?= h($r['titre']) ?></div>
                <?php endif ?>

                <?php if ($r['taches']): ?>
                    <?php
                    // Regrouper tâches par catégorie
                    $byCat = [];
                    foreach ($r['taches'] as $t) $byCat[$t['categorie']][] = $t;
                    ?>
                    <div class="mst-taches-summary">
                        <?php foreach ($byCat as $cat => $tList): ?>
                            <div class="mst-tcat">
                                <strong><?= h($cat) ?></strong>
                                <?php foreach ($tList as $t): ?>
                                    <?php
                                    $nivMap = [
                                        'acquis' => 'mst-niv-acquis',
                                        'en_cours' => 'mst-niv-encours',
                                        'non_acquis' => 'mst-niv-nonacquis',
                                        'non_evalue' => 'mst-niv-pending',
                                    ];
                                    $nivCls = $nivMap[$t['niveau_formateur']] ?? 'mst-niv-pending';
                                    ?>
                                    <span class="mst-tchip">
                                        <?= h($t['tache_nom']) ?><?php if ($t['nb_fois'] > 1) echo ' ×' . (int)$t['nb_fois'] ?>
                                        <span class="mst-niv-badge <?= $nivCls ?>"><?= h($NIVEAU_LABELS[$t['niveau_formateur']] ?? $t['niveau_formateur']) ?></span>
                                    </span>
                                <?php endforeach ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="ms-report-content mst-content-html"><?= $r['contenu'] /* HTML TipTap déjà stocké */ ?></div>

                <?php if ($r['commentaire_formateur']): ?>
                    <div class="ms-report-comment">
                        <strong>Commentaire<?php if ($r['valideur_nom']) echo ' de ' . h($r['valideur_nom']) ?> :</strong>
                        <?= h($r['commentaire_formateur']) ?>
                    </div>
                <?php endif ?>

                <?php $editable = in_array($r['statut'], ['brouillon', 'a_refaire']); ?>
                <?php if ($editable): ?>
                    <div class="ms-report-actions">
                        <button class="btn btn-sm btn-outline-secondary" data-edit-report="<?= h($r['id']) ?>">
                            <i class="bi bi-pencil"></i> Modifier
                        </button>
                        <?php if ($r['statut'] === 'brouillon'): ?>
                            <button class="btn btn-sm btn-outline-secondary" data-del-report="<?= h($r['id']) ?>">
                                <i class="bi bi-trash"></i> Supprimer
                            </button>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach; endif ?>
    </div>

    <!-- Objectifs -->
    <?php if ($objs): ?>
        <div class="mst-section mt-4">
            <h4 class="mb-2"><i class="bi bi-bullseye"></i> Objectifs de stage</h4>
            <?php foreach ($objs as $o): ?>
                <div class="card mb-2"><div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= h($o['titre']) ?></strong>
                            <?= render_statut_badge($o['statut']) ?>
                            <?php if ($o['date_cible']): ?>
                                <span class="text-muted small ms-2">Cible : <?= h(fmt_date_fr($o['date_cible'])) ?></span>
                            <?php endif ?>
                        </div>
                    </div>
                    <?php if ($o['description']): ?>
                        <div class="small mt-1"><?= h($o['description']) ?></div>
                    <?php endif ?>
                </div></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <!-- Évaluations mi-stage / finale -->
    <?php if ($evals): ?>
        <div class="mst-section mt-4">
            <h4 class="mb-2">
                <i class="bi bi-clipboard-check"></i> Évaluations
                <span class="text-muted small">(mi-stage / finale)</span>
            </h4>
            <?php foreach ($evals as $e): ?>
                <div class="ms-eval-card">
                    <div class="ms-eval-head">
                        <strong><?= h(fmt_date_fr($e['date_eval'])) ?></strong>
                        <span class="ms-eval-periode"><?= h($e['periode']) ?></span>
                    </div>
                    <div class="ms-eval-notes">
                        <?php foreach (['initiative','communication','connaissances','autonomie','savoir_etre','ponctualite'] as $k): ?>
                            <?= h($k) ?>: <strong><?= h($e['note_' . $k] ?: '—') ?>/5</strong>
                            <?php if ($k !== 'ponctualite') echo ' • ' ?>
                        <?php endforeach ?>
                    </div>
                    <?php if ($e['points_forts']): ?>
                        <div class="small"><strong>+ </strong><?= h($e['points_forts']) ?></div>
                    <?php endif ?>
                    <?php if ($e['points_amelioration']): ?>
                        <div class="small"><strong>~ </strong><?= h($e['points_amelioration']) ?></div>
                    <?php endif ?>
                    <?php if ($e['commentaire_general']): ?>
                        <div class="small mt-1"><?= h($e['commentaire_general']) ?></div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
