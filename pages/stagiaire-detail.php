<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$stagId = $_GET['id'] ?? '';

if (!$stagId) {
    echo '<div class="sd-wrap container-fluid">';
    echo render_page_header('Profil stagiaire', 'bi-person-badge', 'mes-stagiaires', 'Mes stagiaires');
    echo render_empty_state('ID stagiaire manquant dans l\'URL.', 'bi-exclamation-triangle');
    echo '</div>';
    exit;
}

$today = date('Y-m-d');

// Vérification du droit d'accès : formateur (actuel ou passé) affecté au stagiaire
$hasAccess = (int) Db::getOne(
    "SELECT COUNT(*) FROM stagiaire_affectations
     WHERE stagiaire_id = ? AND formateur_id = ?",
    [$stagId, $uid]
);
if (!$hasAccess) {
    echo '<div class="sd-wrap container-fluid">';
    echo render_page_header('Accès refusé', 'bi-person-badge', 'mes-stagiaires', 'Mes stagiaires');
    echo render_empty_state('Vous n\'êtes pas formateur de ce stagiaire.', 'bi-shield-exclamation');
    echo '</div>';
    exit;
}

// Peut-il éditer ? Affectation active aujourd'hui
$canEdit = (int) Db::getOne(
    "SELECT COUNT(*) FROM stagiaire_affectations
     WHERE stagiaire_id = ? AND formateur_id = ? AND date_debut <= ? AND date_fin >= ?",
    [$stagId, $uid, $today, $today]
) > 0;

// Chargement des données
$stag = Db::fetch(
    "SELECT s.id, s.type, s.date_debut, s.date_fin, s.statut, s.objectifs_generaux,
            u.prenom, u.nom, u.email, u.photo, u.telephone,
            (SELECT e.nom FROM etages e WHERE e.id = s.etage_id) AS etage_nom
     FROM stagiaires s JOIN users u ON u.id = s.user_id WHERE s.id = ?",
    [$stagId]
);

if (!$stag) {
    echo '<div class="sd-wrap container-fluid">';
    echo render_page_header('Stagiaire introuvable', 'bi-person-badge', 'mes-stagiaires', 'Mes stagiaires');
    echo render_empty_state('Le stagiaire demandé n\'existe plus.', 'bi-exclamation-triangle');
    echo '</div>';
    exit;
}

$reports = Db::fetchAll(
    "SELECT * FROM stagiaire_reports WHERE stagiaire_id = ?
     ORDER BY date_report DESC LIMIT 60",
    [$stagId]
);
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

$evaluations = Db::fetchAll(
    "SELECT e.*, u.prenom AS formateur_prenom, u.nom AS formateur_nom
     FROM stagiaire_evaluations e
     JOIN users u ON u.id = e.formateur_id
     WHERE e.stagiaire_id = ?
     ORDER BY e.date_eval DESC",
    [$stagId]
);

$objectifs = Db::fetchAll(
    "SELECT * FROM stagiaire_objectifs WHERE stagiaire_id = ? ORDER BY created_at DESC",
    [$stagId]
);

// Stats
$pending = count(array_filter($reports, fn($r) => $r['statut'] === 'soumis'));
$valides = count(array_filter($reports, fn($r) => $r['statut'] === 'valide'));
$refaire = count(array_filter($reports, fn($r) => $r['statut'] === 'a_refaire'));
$objsAtteints = count(array_filter($objectifs, fn($o) => $o['statut'] === 'atteint'));

// Moyenne notes
$noteKeys = ['note_initiative','note_communication','note_connaissances','note_autonomie','note_savoir_etre','note_ponctualite'];
$sum = 0; $n = 0;
foreach ($evaluations as $e) foreach ($noteKeys as $k) { if ($e[$k]) { $sum += (int)$e[$k]; $n++; } }
$moyenne = $n ? number_format($sum / $n, 1, '.', '') : null;

$TYPE_LABELS = [
    'decouverte' => 'Découverte', 'cfc_asa' => 'CFC ASA', 'cfc_ase' => 'CFC ASE',
    'cfc_asfm' => 'CFC ASFM', 'bachelor_inf' => 'Bachelor inf.',
    'civiliste' => 'Civiliste', 'autre' => 'Autre',
];
$NIVEAU_LABELS = [
    'acquis' => 'Acquis', 'en_cours' => 'En cours',
    'non_acquis' => 'Non acquis', 'non_evalue' => 'À évaluer',
];

$initials = strtoupper(mb_substr($stag['prenom'], 0, 1) . mb_substr($stag['nom'], 0, 1));
$pageTitle = $stag['prenom'] . ' ' . $stag['nom'];
?>
<div class="sd-wrap container-fluid">
    <button class="btn btn-sm btn-link re-back-link mb-1 px-0" data-link="mes-stagiaires">
        <i class="bi bi-arrow-left"></i> Mes stagiaires
    </button>

    <div class="sd-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="sd-title mb-0"><i class="bi bi-person-badge"></i> <?= h($pageTitle) ?></h2>
        <div class="d-flex gap-2">
            <?php if ($canEdit): ?>
                <button class="btn btn-sm btn-primary" id="sdBtnNewEval">
                    <i class="bi bi-plus-lg"></i> Nouvelle évaluation
                </button>
            <?php endif ?>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <?= render_stat_card('À valider', $pending, 'bi-bell-fill', 'orange') ?>
        <?= render_stat_card('Reports validés', $valides, 'bi-check-circle', 'teal') ?>
        <?= render_stat_card('À refaire', $refaire, 'bi-arrow-counterclockwise', 'red') ?>
        <?= render_stat_card('Évaluations', count($evaluations), 'bi-clipboard-check', 'purple', $moyenne ? "Moy. $moyenne/5" : null) ?>
        <?= render_stat_card('Objectifs atteints', $objsAtteints, 'bi-bullseye', 'green', count($objectifs) ? 'sur ' . count($objectifs) : null) ?>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="sdTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#sdTabInfos">Infos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabReports">Reports (<?= count($reports) ?>)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabEvals">Évaluations (<?= count($evaluations) ?>)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sdTabObjectifs">Objectifs (<?= count($objectifs) ?>)</a></li>
    </ul>

    <div class="tab-content">
        <!-- Tab Infos -->
        <div class="tab-pane fade show active" id="sdTabInfos">
            <div class="card"><div class="card-body">
                <div class="sd-profile-head">
                    <div class="sd-avatar">
                        <?php if (!empty($stag['photo'])): ?>
                            <img src="<?= h($stag['photo']) ?>" alt="">
                        <?php else: ?>
                            <?= h($initials) ?>
                        <?php endif ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="sd-profile-name"><?= h($stag['prenom'] . ' ' . $stag['nom']) ?></div>
                        <div class="text-muted small">
                            <?= h($stag['email'] ?: '') ?>
                            <?php if ($stag['telephone']): ?> • <?= h($stag['telephone']) ?><?php endif ?>
                        </div>
                        <div class="mt-1">
                            <?= render_type_badge($TYPE_LABELS[$stag['type']] ?? $stag['type']) ?>
                            <?= render_statut_badge($stag['statut']) ?>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6"><div class="sd-lbl">Période</div><div><?= h(fmt_date_fr($stag['date_debut'])) ?> → <?= h(fmt_date_fr($stag['date_fin'])) ?></div></div>
                    <div class="col-md-6"><div class="sd-lbl">Étage</div><div><?= h($stag['etage_nom'] ?: '—') ?></div></div>
                    <div class="col-md-12"><div class="sd-lbl">Objectifs généraux</div><div><?= h($stag['objectifs_generaux'] ?: '—') ?></div></div>
                </div>
                <?php if (!$canEdit): ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <i class="bi bi-info-circle"></i>
                        Vous consultez en lecture seule — votre affectation n'est plus active aujourd'hui.
                    </div>
                <?php endif ?>
            </div></div>
        </div>

        <!-- Tab Reports -->
        <div class="tab-pane fade" id="sdTabReports">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted">Reports rédigés par le stagiaire — valide ou demande une correction.</small>
                <select class="form-select form-select-sm" id="sdReportFilter" style="max-width:200px">
                    <option value="">Tous les statuts</option>
                    <option value="soumis">À valider</option>
                    <option value="valide">Validés</option>
                    <option value="a_refaire">À refaire</option>
                    <option value="brouillon">Brouillons</option>
                </select>
            </div>
            <div id="sdReportsBody">
                <?php if (!$reports): ?>
                    <?= render_empty_state('Aucun report', 'bi-journal-text') ?>
                <?php else: foreach ($reports as $r): ?>
                    <div class="card mb-2 sd-report" data-statut="<?= h($r['statut']) ?>">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                <strong><?= h(fmt_date_fr($r['date_report'])) ?></strong>
                                <?= render_type_badge($r['type']) ?>
                                <?= render_statut_badge($r['statut']) ?>
                                <span class="text-muted small ms-auto"><?= count($r['taches']) ?> tâche(s)</span>
                            </div>
                            <?php if ($r['titre']): ?>
                                <div class="fw-semibold small mb-1"><?= h($r['titre']) ?></div>
                            <?php endif ?>

                            <?php if ($r['taches']): ?>
                                <?php
                                $byCat = [];
                                foreach ($r['taches'] as $t) $byCat[$t['categorie']][] = $t;
                                ?>
                                <div class="mst-taches-eval">
                                    <?php foreach ($byCat as $cat => $tList): ?>
                                        <div class="mst-tcat-eval">
                                            <div class="mst-tcat-title-eval"><?= h($cat) ?></div>
                                            <?php foreach ($tList as $t): ?>
                                                <?php
                                                $nivMap = ['acquis'=>'mst-niv-acquis','en_cours'=>'mst-niv-encours','non_acquis'=>'mst-niv-nonacquis','non_evalue'=>'mst-niv-pending'];
                                                $nivCls = $nivMap[$t['niveau_formateur']] ?? 'mst-niv-pending';
                                                ?>
                                                <div class="mst-tache-eval-row">
                                                    <div class="mst-tache-eval-name">
                                                        <i class="bi bi-check2-square"></i> <?= h($t['tache_nom']) ?>
                                                        <?php if ($t['nb_fois'] > 1): ?>
                                                            <span class="text-muted">×<?= (int)$t['nb_fois'] ?></span>
                                                        <?php endif ?>
                                                    </div>
                                                    <?php if ($canEdit): ?>
                                                        <div class="mst-niv-buttons" data-rt-id="<?= h($t['id']) ?>">
                                                            <?php foreach (['acquis','en_cours','non_acquis','non_evalue'] as $nivKey): ?>
                                                                <button class="mst-niv-btn mst-niv-btn-<?= $nivKey ?><?= $t['niveau_formateur'] === $nivKey ? ' active' : '' ?>"
                                                                        data-niveau="<?= $nivKey ?>"
                                                                        title="<?= h($NIVEAU_LABELS[$nivKey]) ?>"></button>
                                                            <?php endforeach ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="mst-niv-badge <?= $nivCls ?>"><?= h($NIVEAU_LABELS[$t['niveau_formateur']] ?? $t['niveau_formateur']) ?></span>
                                                    <?php endif ?>
                                                </div>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>

                            <div class="sd-report-content mst-content-html"><?= $r['contenu'] ?></div>

                            <?php if ($r['commentaire_formateur']): ?>
                                <div class="ms-report-comment mt-2">
                                    <strong>Commentaire :</strong> <?= h($r['commentaire_formateur']) ?>
                                </div>
                            <?php endif ?>

                            <?php if ($canEdit && $r['statut'] === 'soumis'): ?>
                                <div class="mt-2 d-flex gap-2">
                                    <button class="btn btn-sm btn-primary" data-validate="<?= h($r['id']) ?>">
                                        <i class="bi bi-check"></i> Valider
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" data-refuse="<?= h($r['id']) ?>">
                                        <i class="bi bi-arrow-counterclockwise"></i> À refaire
                                    </button>
                                </div>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endforeach; endif ?>
            </div>
        </div>

        <!-- Tab Évaluations -->
        <div class="tab-pane fade" id="sdTabEvals">
            <?php if (!$evaluations): ?>
                <?= render_empty_state('Aucune évaluation', 'bi-clipboard-check') ?>
            <?php else: foreach ($evaluations as $e): ?>
                <div class="card mb-2"><div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <strong><?= h(fmt_date_fr($e['date_eval'])) ?></strong>
                        <?= render_type_badge($e['periode']) ?>
                        <span class="text-muted small ms-auto">par <?= h($e['formateur_prenom'] . ' ' . $e['formateur_nom']) ?></span>
                    </div>
                    <div class="small mb-1">
                        <?php foreach (['initiative','communication','connaissances','autonomie','savoir_etre','ponctualite'] as $k): ?>
                            <?= h($k) ?>: <strong><?= h($e['note_' . $k] ?: '—') ?>/5</strong>
                            <?php if ($k !== 'ponctualite') echo ' • ' ?>
                        <?php endforeach ?>
                    </div>
                    <?php if ($e['points_forts']): ?><div class="small"><strong>+ </strong><?= h($e['points_forts']) ?></div><?php endif ?>
                    <?php if ($e['points_amelioration']): ?><div class="small"><strong>~ </strong><?= h($e['points_amelioration']) ?></div><?php endif ?>
                    <?php if ($e['commentaire_general']): ?><div class="small mt-1"><?= h($e['commentaire_general']) ?></div><?php endif ?>
                </div></div>
            <?php endforeach; endif ?>
        </div>

        <!-- Tab Objectifs -->
        <div class="tab-pane fade" id="sdTabObjectifs">
            <small class="text-muted d-block mb-2">Objectifs définis par la RUV.</small>
            <?php if (!$objectifs): ?>
                <?= render_empty_state("Aucun objectif défini par la RUV", 'bi-bullseye') ?>
            <?php else: foreach ($objectifs as $o): ?>
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
                    <?php if ($o['description']): ?><div class="small mt-1"><?= h($o['description']) ?></div><?php endif ?>
                    <?php if ($o['commentaire_ruv']): ?>
                        <div class="alert alert-light border mt-2 mb-0 small"><?= h($o['commentaire_ruv']) ?></div>
                    <?php endif ?>
                </div></div>
            <?php endforeach; endif ?>
        </div>
    </div>
</div>

<!-- Modal évaluation — pattern SpocCare -->
<?php if ($canEdit): ?>
<div class="modal fade" id="sdEvalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered sd-eval-modal-dialog">
        <div class="modal-content sd-eval-modal-content">
            <div class="modal-header sd-modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Nouvelle évaluation</h5>
                <button type="button" class="btn btn-sm btn-light ms-auto sd-close-btn" data-bs-dismiss="modal" aria-label="Fermer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body sd-modal-body">
                <input type="hidden" id="sdEvalStagId" value="<?= h($stag['id']) ?>">
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Date</label><input type="date" id="sdEvalDate" class="form-control form-control-sm"></div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Période</label>
                        <select id="sdEvalPeriode" class="form-select form-select-sm">
                            <option value="journaliere">Journalière</option>
                            <option value="hebdo">Hebdomadaire</option>
                            <option value="mi_stage">Mi-stage</option>
                            <option value="finale">Finale</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Initiative</label><input type="number" min="1" max="5" id="sdNInit" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Communication</label><input type="number" min="1" max="5" id="sdNComm" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Connaissances</label><input type="number" min="1" max="5" id="sdNConn" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Autonomie</label><input type="number" min="1" max="5" id="sdNAuto" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Savoir-être</label><input type="number" min="1" max="5" id="sdNSav" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-4"><label class="form-label small fw-semibold">Ponctualité</label><input type="number" min="1" max="5" id="sdNPonc" class="form-control form-control-sm"></div>
                </div>
                <label class="form-label small fw-semibold mt-2">Points forts</label>
                <textarea id="sdPFortes" class="form-control form-control-sm" rows="2"></textarea>
                <label class="form-label small fw-semibold mt-2">Points à améliorer</label>
                <textarea id="sdPAmelio" class="form-control form-control-sm" rows="2"></textarea>
                <label class="form-label small fw-semibold mt-2">Commentaire général</label>
                <textarea id="sdComGen" class="form-control form-control-sm" rows="3"></textarea>
            </div>
            <div class="modal-footer d-flex">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-sm btn-primary ms-auto" id="sdBtnSaveEval">
                    <i class="bi bi-check-lg"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
