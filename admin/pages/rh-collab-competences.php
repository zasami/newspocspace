<?php
$userId = $_GET['id'] ?? '';
$user = $userId ? Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom, f.secteur_fegems,
            np1.prenom AS np1_prenom, np1.nom AS np1_nom
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN users np1 ON np1.id = u.n_plus_un_id
     WHERE u.id = ?", [$userId]
) : null;

if (!$user) {
    echo '<div class="d-flex justify-content-between align-items-center mb-3"><h4 class="mb-0"><i class="bi bi-person-badge"></i> Fiche compétences</h4></div>';
    echo '<div class="card"><div class="card-body comp-empty"><i class="bi bi-person-x"></i><p class="mb-0">Aucun collaborateur sélectionné.</p>';
    echo '<p class="mt-2"><a href="?page=rh-formations-cartographie" data-page-link>Retour à la cartographie</a></p></div></div>';
    return;
}

// ── Compétences ────────────────────────────────────────────────
$competences = Db::fetchAll(
    "SELECT cu.*, t.code AS them_code, t.nom AS them_nom, t.categorie AS them_categorie,
            t.tag_affichage, t.icone, t.duree_validite_mois
     FROM competences_user cu
     JOIN competences_thematiques t ON t.id = cu.thematique_id
     WHERE cu.user_id = ?
     ORDER BY FIELD(cu.priorite,'haute','moyenne','basse','a_jour'), cu.ecart DESC, t.ordre ASC",
    [$userId]
);

// ── Stats ──────────────────────────────────────────────────────
$nbEvalues = 0; $sumNiv = 0;
foreach ($competences as $c) {
    if ($c['niveau_actuel'] !== null) { $nbEvalues++; $sumNiv += (int) $c['niveau_actuel']; }
}
$niveauGlobal = $nbEvalues > 0 ? round($sumNiv / $nbEvalues, 1) : 0;
$nbAJour = count(array_filter($competences, fn($c) => $c['priorite'] === 'a_jour'));
$nbHaute = count(array_filter($competences, fn($c) => $c['priorite'] === 'haute'));
$nbMoyenne = count(array_filter($competences, fn($c) => $c['priorite'] === 'moyenne'));
$conformite = count($competences) > 0 ? round(($nbAJour / count($competences)) * 100) : 0;

$heuresAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut IN ('present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())",
    [$userId]
);

// ── Formations en cours / récentes ────────────────────────────
$formations = Db::fetchAll(
    "SELECT f.id, f.titre, f.statut, f.date_debut, f.duree_heures,
            p.statut AS participant_statut, p.heures_realisees, p.cout_individuel
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? ORDER BY f.date_debut DESC LIMIT 6",
    [$userId]
);

// ── Objectifs annuels ──────────────────────────────────────────
$objectifs = Db::fetchAll(
    "SELECT o.*, t.nom AS them_nom FROM competences_objectifs_annuels o
     LEFT JOIN competences_thematiques t ON t.id = o.thematique_id_liee
     WHERE o.user_id = ? AND o.annee = YEAR(CURDATE())
     ORDER BY FIELD(o.trimestre_cible,'Q1','Q2','Q3','Q4','annuel'), o.ordre",
    [$userId]
);

// ── Référents (rôles spéciaux) ────────────────────────────────
$referents = Db::fetchAll(
    "SELECT cr.*, t.nom AS them_nom FROM competences_referents cr
     JOIN competences_thematiques t ON t.id = cr.thematique_id
     WHERE cr.user_id = ? AND cr.actif = 1",
    [$userId]
);

// ── Entretiens annuels ────────────────────────────────────────
$entretiens = Db::fetchAll(
    "SELECT * FROM entretiens_annuels WHERE user_id = ? ORDER BY date_entretien DESC LIMIT 10",
    [$userId]
);
$nbEntretiens = count($entretiens);
$dernierEntretien = $entretiens[0] ?? null;

// ── Toutes les formations (pour tab détaillé) ────────────────
$formationsAll = Db::fetchAll(
    "SELECT f.id, f.titre, f.statut, f.date_debut, f.duree_heures, f.lieu, f.modalite,
            p.id AS participant_id, p.statut AS participant_statut, p.heures_realisees,
            p.date_realisation, p.certificat_url, p.evaluation_manager
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? ORDER BY COALESCE(p.date_realisation, f.date_debut) DESC",
    [$userId]
);
$nbFormationsAll = count($formationsAll);
$nbFormationsSansCert = count(array_filter($formationsAll, fn($f) =>
    in_array($f['participant_statut'], ['present','valide']) && empty($f['certificat_url'])
));

// ── Documents (formations avec certificat upload) ─────────────
$documents = array_filter($formationsAll, fn($f) => !empty($f['certificat_url']));
$nbDocuments = count($documents);

// ── Préparer regroupement par priorité ────────────────────────
$grouped = ['haute' => [], 'moyenne' => [], 'basse' => [], 'a_jour' => []];
foreach ($competences as $c) {
    $grouped[$c['priorite']][] = $c;
}

$prioLabels = [
    'haute'   => ['Priorité haute',   'exclamation-circle', 'danger'],
    'moyenne' => ['Écart léger',      'exclamation-triangle', 'warn'],
    'basse'   => ['Écart faible',     'info-circle',        'info'],
    'a_jour'  => ['À niveau requis',  'check-circle',       'ok'],
];

// ── Initiales avatar ───────────────────────────────────────────
$initials = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));

// ── Calcul ancienneté ──────────────────────────────────────────
$anciennete = '';
if ($user['date_entree']) {
    $diff = (new DateTime())->diff(new DateTime($user['date_entree']));
    $anciennete = $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->y === 0) $anciennete = $diff->m . ' mois';
}

// ── Échelle radar : 6 axes principaux (groupes logiques) ──────
$radarGroups = [
    'HPCI'              => ['HPCI_BASE', 'HPCI_PRECAUTIONS'],
    'Réanimation'       => ['BLS_AED'],
    'Soins palliatifs'  => ['SOINS_PALLIATIFS'],
    'Bientraitance'     => ['BIENTRAITANCE', 'BPSD'],
    'Sécurité & cyber'  => ['SECURITE_INCENDIE', 'CYBER_SECURITE'],
    'Transmissions'     => ['TRANSMISSIONS_INF', 'TRANSMISSIONS_AS', 'ACTES_DELEGUES'],
];

// Calcule niveau actuel/requis par axe (moyenne des thématiques du groupe)
$radarData = [];
foreach ($radarGroups as $axe => $codes) {
    $sumA = 0; $cntA = 0; $sumR = 0; $cntR = 0;
    foreach ($competences as $c) {
        if (!in_array($c['them_code'], $codes, true)) continue;
        if ($c['niveau_actuel'] !== null) { $sumA += (int) $c['niveau_actuel']; $cntA++; }
        if ($c['niveau_requis'] !== null) { $sumR += (int) $c['niveau_requis']; $cntR++; }
    }
    if ($cntR === 0) continue;
    $radarData[] = [
        'axe' => $axe,
        'actuel' => $cntA > 0 ? round($sumA / $cntA, 1) : 0,
        'requis' => round($sumR / $cntR, 1),
    ];
}
?>

<!-- Hero collaborateur -->
<div class="comp-hero">
  <div class="comp-hero-inner">
    <div class="comp-hero-avatar"><?= h($initials) ?></div>
    <div style="flex:1;min-width:280px">
      <div class="comp-hero-label">Collaborateur · <?= h(ucfirst(str_replace('_', ' ', $user['secteur_fegems'] ?? 'sans secteur'))) ?></div>
      <h1><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></h1>
      <div style="color:#cfe0db;font-size:.92rem;margin-top:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span><?= h($user['fonction_nom'] ?? '—') ?></span>
        <?php if ($user['np1_prenom']): ?>
          <span style="opacity:.4">·</span>
          <span>N+1 <?= h($user['np1_prenom'] . ' ' . $user['np1_nom']) ?></span>
        <?php endif ?>
        <?php if ($referents): ?>
          <span style="opacity:.4">·</span>
          <span style="color:#a8e6c9"><i class="bi bi-mortarboard"></i> Référent <?= h($referents[0]['them_nom']) ?></span>
        <?php endif ?>
      </div>
      <div class="comp-hero-meta">
        <?php if ($user['taux']): ?>
          <div class="m"><span class="k">Taux d'activité</span><span class="v"><?= round($user['taux']) ?>%</span></div>
        <?php endif ?>
        <?php if ($user['date_entree']): ?>
          <div class="m"><span class="k">Ancienneté</span><span class="v"><?= h($anciennete) ?></span></div>
        <?php endif ?>
        <?php if ($user['type_contrat']): ?>
          <div class="m"><span class="k">Contrat</span><span class="v"><?= h($user['type_contrat']) ?><?= $user['cct'] ? ' · ' . h($user['cct']) : '' ?></span></div>
        <?php endif ?>
        <?php if ($user['diplome_principal']): ?>
          <div class="m"><span class="k">Diplôme</span><span class="v"><?= h($user['diplome_principal']) ?></span></div>
        <?php endif ?>
        <?php if ($user['prochain_entretien_date']): ?>
          <div class="m"><span class="k">Prochain entretien</span><span class="v"><?= date('d.m.Y', strtotime($user['prochain_entretien_date'])) ?></span></div>
        <?php endif ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?page=rh-formations-cartographie" class="btn-on-dark" data-page-link>
        <i class="bi bi-arrow-left"></i> Cartographie
      </a>
      <a href="?page=user-detail&id=<?= h($user['id']) ?>" class="btn-on-dark" data-page-link>
        <i class="bi bi-person"></i> Fiche RH
      </a>
    </div>
  </div>
</div>

<!-- Snap cards en pull-up -->
<div class="comp-snap">
  <div class="comp-snap-card">
    <div class="comp-lbl">Niveau global</div>
    <div class="comp-val"><?= number_format($niveauGlobal, 1, ',', '') ?><span style="font-size:.85rem;color:var(--cl-text-muted)"> / 4</span></div>
    <div class="comp-sub">Échelle FEGEMS · moyenne pondérée</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Conformité formations</div>
    <div class="comp-val <?= $conformite >= 70 ? 'ok' : ($conformite >= 40 ? 'warn' : 'bad') ?>"><?= $conformite ?>%</div>
    <div class="comp-sub"><?= $nbAJour ?> / <?= count($competences) ?> thématiques à jour</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Écarts à combler</div>
    <div class="comp-val bad"><?= $nbHaute + $nbMoyenne ?></div>
    <div class="comp-sub"><?= $nbHaute ?> haute · <?= $nbMoyenne ?> moyenne</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Heures formation <?= date('Y') ?></div>
    <div class="comp-val"><?= number_format($heuresAnnee, 0, ',', ' ') ?><span style="font-size:.85rem;color:var(--cl-text-muted)">h</span></div>
    <div class="comp-sub">Présence ou validation</div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs comp-tabs mb-3" role="tablist">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-comp" type="button" role="tab">
      <i class="bi bi-grid-3x3"></i> Compétences <span class="badge bg-secondary ms-1"><?= count($competences) ?></span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-form" type="button" role="tab">
      <i class="bi bi-mortarboard"></i> Formations <span class="badge bg-secondary ms-1"><?= $nbFormationsAll ?></span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
      <i class="bi bi-file-text"></i> Documents <span class="badge bg-secondary ms-1"><?= $nbDocuments ?></span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-entr" type="button" role="tab">
      <i class="bi bi-chat-square-text"></i> Entretiens <span class="badge bg-secondary ms-1"><?= $nbEntretiens ?></span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hist" type="button" role="tab">
      <i class="bi bi-clock-history"></i> Historique
    </button>
  </li>
</ul>

<div class="tab-content">

<!-- ═══ TAB Compétences ═══ -->
<div class="tab-pane fade show active" id="tab-comp" role="tabpanel">
<div class="row g-3">
  <!-- Colonne gauche : radar + détail thématiques -->
  <div class="col-lg-8">

    <!-- Radar SVG -->
    <?php if (count($radarData) >= 3): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0" style="font-weight:600">Profil de compétences</h5>
        <div class="text-muted small mt-1">Synthèse par domaine · niveau actuel vs requis selon référentiel FEGEMS</div>
      </div>
      <div class="card-body">
        <?php
        $cx = 200; $cy = 180; $rMax = 130;
        $axes = count($radarData);
        $ptsActuel = []; $ptsRequis = []; $labelPos = [];
        foreach ($radarData as $i => $d) {
            $angle = -M_PI / 2 + ($i * 2 * M_PI / $axes);
            $rA = ($d['actuel'] / 4) * $rMax;
            $rR = ($d['requis'] / 4) * $rMax;
            $ptsActuel[] = round($cx + $rA * cos($angle), 1) . ',' . round($cy + $rA * sin($angle), 1);
            $ptsRequis[] = round($cx + $rR * cos($angle), 1) . ',' . round($cy + $rR * sin($angle), 1);
            $labelPos[] = [
                'x' => round($cx + ($rMax + 18) * cos($angle), 1),
                'y' => round($cy + ($rMax + 18) * sin($angle), 1),
                'label' => $d['axe'],
            ];
        }
        ?>
        <div class="row align-items-center">
          <div class="col-md-7">
            <svg viewBox="0 0 400 360" style="width:100%;max-width:420px;display:block;margin:0 auto">
              <defs>
                <radialGradient id="reqGradFiche" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#9cb8a8" stop-opacity=".05"/>
                  <stop offset="100%" stop-color="#9cb8a8" stop-opacity=".25"/>
                </radialGradient>
                <radialGradient id="curGradFiche" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#2d8074" stop-opacity=".15"/>
                  <stop offset="100%" stop-color="#2d8074" stop-opacity=".5"/>
                </radialGradient>
              </defs>
              <!-- Cercles concentriques -->
              <?php for ($i = 1; $i <= 4; $i++): $r = ($i / 4) * $rMax; ?>
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="<?= $i === 4 ? '#d4ddda' : '#e3ebe8' ?>" stroke-width="1" <?= $i === 4 ? 'stroke-dasharray="2 3"' : '' ?>/>
              <?php endfor ?>
              <!-- Axes -->
              <?php foreach ($radarData as $i => $d):
                  $angle = -M_PI / 2 + ($i * 2 * M_PI / $axes);
                  $x2 = round($cx + $rMax * cos($angle), 1);
                  $y2 = round($cy + $rMax * sin($angle), 1);
              ?>
                <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="#e3ebe8" stroke-width="1"/>
              <?php endforeach ?>
              <!-- Polygone requis -->
              <polygon points="<?= implode(' ', $ptsRequis) ?>" fill="url(#reqGradFiche)" stroke="#9cb8a8" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.85"/>
              <!-- Polygone actuel -->
              <polygon points="<?= implode(' ', $ptsActuel) ?>" fill="url(#curGradFiche)" stroke="#2d8074" stroke-width="2"/>
              <!-- Points actuels -->
              <?php foreach ($ptsActuel as $p): [$x, $y] = explode(',', $p); ?>
                <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" fill="#1f6359" stroke="#fff" stroke-width="2"/>
              <?php endforeach ?>
              <!-- Labels axes -->
              <?php foreach ($labelPos as $l): ?>
                <text x="<?= $l['x'] ?>" y="<?= $l['y'] ?>" text-anchor="middle" font-size="11" font-weight="500" fill="#324e4a"><?= h($l['label']) ?></text>
              <?php endforeach ?>
            </svg>
          </div>
          <div class="col-md-5">
            <div class="d-flex flex-column gap-2 small">
              <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#2d8074"></span>
                <span class="fw-bold">Niveau actuel</span>
                <span class="ms-auto" style="font-family:monospace"><?= number_format($niveauGlobal, 1, ',', '') ?></span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;width:12px;height:12px;border-radius:3px;border:1.5px dashed #9cb8a8"></span>
                <span class="fw-bold">Niveau requis</span>
                <span class="ms-auto" style="font-family:monospace"><?php
                  $sumR = 0; $cntR = 0;
                  foreach ($radarData as $d) { $sumR += $d['requis']; $cntR++; }
                  echo $cntR > 0 ? number_format($sumR / $cntR, 1, ',', '') : '—';
                ?></span>
              </div>
              <hr class="my-2">
              <div class="text-muted">
                <?php
                $analyses = [];
                foreach ($radarData as $d) {
                    $diff = $d['requis'] - $d['actuel'];
                    if ($diff >= 1.5) $analyses[] = '<span style="color:var(--comp-danger);font-weight:600">' . h($d['axe']) . '</span> point de tension';
                    elseif ($diff >= 0.6) $analyses[] = '<span style="color:var(--comp-warn);font-weight:600">' . h($d['axe']) . '</span> à consolider';
                }
                if ($analyses) {
                    echo implode(', ', array_slice($analyses, 0, 3)) . '.';
                } else {
                    echo '<span style="color:var(--comp-ok);font-weight:600">Profil aligné</span> sur le référentiel attendu.';
                }
                ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="border-top" style="background:var(--cl-bg);padding:12px 22px;display:flex;justify-content:space-around">
        <div class="text-center"><div style="font-size:1.4rem;font-weight:600;color:var(--comp-ok)"><?= $nbAJour ?></div><div class="small text-muted text-uppercase" style="letter-spacing:.06em;font-size:.65rem">À niveau</div></div>
        <div class="text-center"><div style="font-size:1.4rem;font-weight:600;color:var(--comp-warn)"><?= $nbMoyenne ?></div><div class="small text-muted text-uppercase" style="letter-spacing:.06em;font-size:.65rem">Écart léger</div></div>
        <div class="text-center"><div style="font-size:1.4rem;font-weight:600;color:var(--comp-danger)"><?= $nbHaute ?></div><div class="small text-muted text-uppercase" style="letter-spacing:.06em;font-size:.65rem">Écart critique</div></div>
        <div class="text-center"><div style="font-size:1.4rem;font-weight:600;color:var(--cl-text)"><?= count($competences) ?></div><div class="small text-muted text-uppercase" style="letter-spacing:.06em;font-size:.65rem">Thématiques suivies</div></div>
      </div>
    </div>
    <?php endif ?>

    <!-- Détail thématiques -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0" style="font-weight:600">Détail des thématiques</h5>
        <div class="text-muted small mt-1">Marqueur noir = niveau requis · barre teal = niveau validé</div>
      </div>

      <?php foreach ($prioLabels as $prio => [$label, $icon, $colorKey]):
          $list = $grouped[$prio] ?? [];
          if (!$list) continue;
      ?>
      <div class="comp-matrix-section">
        <i class="bi bi-<?= h($icon) ?>" style="color:var(--comp-<?= h($colorKey) ?>)"></i>
        <?= h($label) ?>
        <span class="num"><?= count($list) ?></span>
      </div>

      <?php foreach ($list as $c):
          $niv = (int) ($c['niveau_actuel'] ?? 0);
          $req = (int) ($c['niveau_requis'] ?? 0);
          $ecart = max($req - $niv, 0);
          $fillPct = $niv > 0 ? ($niv / 4) * 100 : 0;
          $reqPct = $req > 0 ? ($req / 4) * 100 : 0;
          $fillCls = $ecart === 0 ? '' : ($ecart === 1 ? 'warn' : 'bad');
      ?>
      <div class="px-3 py-3 border-bottom" style="border-color:var(--cl-border-light) !important">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <strong><?= h($c['them_nom']) ?></strong>
              <?php if ($c['tag_affichage']): ?>
                <span class="comp-tag" style="background:#fff3e3;color:#8a5a1a"><?= h($c['tag_affichage']) ?></span>
              <?php endif ?>
              <?php if ($c['date_expiration'] && $c['date_expiration'] < date('Y-m-d')): ?>
                <span class="comp-tag comp-tag-management">Expirée</span>
              <?php endif ?>
            </div>
            <?php if ($c['date_evaluation']): ?>
              <div class="text-muted small mt-1" style="font-family:monospace">
                Évalué le <?= date('d.m.Y', strtotime($c['date_evaluation'])) ?>
              </div>
            <?php endif ?>
          </div>
          <div class="text-end" style="flex-shrink:0">
            <?php if ($ecart > 0): ?>
              <div style="font-family:monospace;font-weight:600;color:var(--comp-<?= $colorKey ?>)">Écart +<?= $ecart ?></div>
              <div class="small text-muted text-uppercase" style="letter-spacing:.06em;font-size:.62rem"><?= $ecart ?> niveau<?= $ecart > 1 ? 'x' : '' ?></div>
            <?php else: ?>
              <div style="font-family:monospace;font-weight:600;color:var(--comp-ok)"><i class="bi bi-check-circle"></i> Conforme</div>
            <?php endif ?>
          </div>
        </div>
        <div class="comp-lvl-track" style="margin-bottom:12px">
          <div class="comp-lvl-grad">
            <span>1 · Débute</span><span>2 · Supervision</span><span>3 · Autonome</span><span>4 · Référent</span>
          </div>
          <?php if ($fillPct > 0): ?>
            <div class="comp-lvl-fill <?= $fillCls ?>" style="width:<?= $fillPct ?>%"><?= $niv ?></div>
          <?php endif ?>
          <?php if ($reqPct > 0): ?>
            <div class="comp-lvl-marker" style="left:<?= $reqPct ?>%"><span class="lbl">Requis <?= $req ?></span></div>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
      <?php endforeach ?>

      <?php if (!$competences): ?>
        <div class="comp-empty">
          <i class="bi bi-clipboard-x"></i>
          <p class="mb-0">Aucune compétence évaluée pour ce collaborateur.</p>
          <p class="small mt-2">Les thématiques requises sont définies dans <a href="?page=rh-formations-profil" data-page-link>Profil d'équipe attendu</a>.</p>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Colonne droite : infos + objectifs + formations -->
  <div class="col-lg-4">
    <!-- Informations -->
    <div class="card mb-3">
      <div class="card-header"><h5 class="mb-0" style="font-weight:600">Informations</h5></div>
      <div class="card-body">
        <div class="row g-3 small">
          <?php if ($user['email']): ?>
            <div class="col-12"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Email</div><div class="fw-bold"><?= h($user['email']) ?></div></div>
          <?php endif ?>
          <?php if ($user['telephone']): ?>
            <div class="col-12"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Téléphone</div><div class="fw-bold"><?= h($user['telephone']) ?></div></div>
          <?php endif ?>
          <?php if ($user['date_entree']): ?>
            <div class="col-6"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Date d'entrée</div><div class="fw-bold"><?= date('d.m.Y', strtotime($user['date_entree'])) ?></div></div>
          <?php endif ?>
          <?php if ($user['experience_fonction_annees'] > 0): ?>
            <div class="col-6"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Expérience fonction</div><div class="fw-bold"><?= number_format($user['experience_fonction_annees'], 1, ',', '') ?> ans</div></div>
          <?php endif ?>
          <?php if ($user['reconnaissance_crs_annee']): ?>
            <div class="col-12"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Reconnaissance CRS</div><div class="fw-bold" style="color:var(--comp-ok)"><i class="bi bi-check-circle"></i> <?= h($user['reconnaissance_crs_annee']) ?></div></div>
          <?php endif ?>
          <?php if ($referents): ?>
            <div class="col-12">
              <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Rôles spéciaux</div>
              <?php foreach ($referents as $r): ?>
                <div class="fw-bold">
                  <i class="bi bi-mortarboard text-success"></i> Référent <?= h($r['them_nom']) ?>
                  <?php if ($r['has_competences_pedago']): ?><span class="badge bg-success-subtle text-success ms-1" style="font-size:.65rem">Pédago</span><?php endif ?>
                  <div class="small text-muted">depuis <?= date('m.Y', strtotime($r['depuis_le'])) ?></div>
                </div>
              <?php endforeach ?>
            </div>
          <?php endif ?>
          <?php if ($user['disponibilite_formation']): ?>
            <div class="col-12">
              <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Disponibilité formation</div>
              <div style="font-style:italic;color:var(--cl-text-secondary)"><?= h($user['disponibilite_formation']) ?></div>
            </div>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- Formations -->
    <?php if ($formations): ?>
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div><h5 class="mb-0" style="font-weight:600">Formations</h5>
        <div class="text-muted small mt-1"><?= count($formations) ?> récentes</div></div>
      </div>
      <div>
        <?php foreach ($formations as $f):
            $statutCls = match ($f['participant_statut']) {
                'valide','present' => 'ok',
                'inscrit' => 'info',
                'absent' => 'danger',
                default => 'warn',
            };
        ?>
        <div class="px-3 py-2 border-bottom" style="border-color:var(--cl-border-light) !important">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
            <div class="small fw-bold" style="line-height:1.3"><?= h($f['titre']) ?></div>
            <span class="comp-priorite comp-priorite-<?= $statutCls === 'ok' ? 'a_jour' : ($statutCls === 'danger' ? 'haute' : 'moyenne') ?>" style="white-space:nowrap"><?= h($f['participant_statut']) ?></span>
          </div>
          <div class="text-muted" style="font-size:.72rem;font-family:monospace">
            <?php if ($f['date_debut']): ?><?= date('m.Y', strtotime($f['date_debut'])) ?> ·<?php endif ?>
            <?= $f['heures_realisees'] ? $f['heures_realisees'] . 'h' : ($f['duree_heures'] ? $f['duree_heures'] . 'h' : '—') ?>
            <?php if ($f['cout_individuel'] > 0): ?> · CHF <?= number_format($f['cout_individuel'], 0, '.', "'") ?><?php endif ?>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <!-- Objectifs annuels -->
    <?php if ($objectifs): ?>
    <div class="card">
      <div class="card-header"><h5 class="mb-0" style="font-weight:600">Objectifs <?= date('Y') ?></h5>
      <div class="text-muted small mt-1"><?= count(array_filter($objectifs, fn($o) => $o['statut'] === 'atteint')) ?>/<?= count($objectifs) ?> atteints</div></div>
      <div class="card-body" style="position:relative">
        <div style="position:absolute;left:34px;top:24px;bottom:24px;width:2px;background:var(--cl-border-light);"></div>
        <?php foreach ($objectifs as $o):
            $cls = match ($o['statut']) {
                'atteint'  => ['ok',     '#3d8b6b', '✓'],
                'en_cours' => ['cur',    '#1f6359', '·'],
                'reporte', 'abandonne' => ['next', '#d4ddda', '!'],
                default    => ['next',   '#d4ddda', '?'],
            };
        ?>
        <div class="d-flex gap-3 mb-3 position-relative">
          <div style="width:22px;height:22px;border-radius:50%;background:<?= $o['statut'] === 'en_cours' ? '#1f6359' : ($o['statut'] === 'atteint' ? '#3d8b6b' : '#fff') ?>;<?= $o['statut'] === 'en_cours' ? 'box-shadow:0 0 0 4px var(--comp-teal-50);' : ($o['statut'] !== 'atteint' ? 'border:2px dashed #d4ddda;' : '') ?>color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;z-index:1;font-family:monospace">
            <?php if ($o['statut'] === 'atteint'): ?>✓<?php elseif ($o['statut'] === 'en_cours'): ?>·<?php else: ?><span style="color:var(--cl-text-muted)">·</span><?php endif ?>
          </div>
          <div style="flex:1;min-width:0">
            <div class="text-uppercase text-muted" style="font-size:.62rem;letter-spacing:.08em;font-weight:600"><?= h($o['trimestre_cible']) ?> <?= h($o['annee']) ?> · <?= h(str_replace('_', ' ', $o['statut'])) ?></div>
            <div class="fw-bold small" style="line-height:1.3"><?= h($o['libelle']) ?></div>
            <?php if ($o['them_nom']): ?>
              <div class="text-muted" style="font-size:.7rem">→ <?= h($o['them_nom']) ?></div>
            <?php endif ?>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>
</div><!-- /#tab-comp -->

<!-- ═══ TAB Formations ═══ -->
<div class="tab-pane fade" id="tab-form" role="tabpanel">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0" style="font-weight:600">Toutes les formations</h5>
        <div class="text-muted small mt-1"><?= $nbFormationsAll ?> au total · <?= $nbFormationsSansCert ?> sans certificat uploadé</div>
      </div>
    </div>
    <?php if ($formationsAll): ?>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead style="background:var(--cl-bg)">
            <tr>
              <th class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.06em">Formation</th>
              <th class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.06em">Date</th>
              <th class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.06em">Modalité</th>
              <th class="small text-muted text-uppercase text-end" style="font-size:.7rem;letter-spacing:.06em">Heures</th>
              <th class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.06em">Statut</th>
              <th class="small text-muted text-uppercase text-center" style="font-size:.7rem;letter-spacing:.06em">Cert.</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($formationsAll as $f):
              $statutCls = match($f['participant_statut']) {
                'valide','present' => 'success',
                'inscrit' => 'info',
                'absent' => 'danger',
                default => 'secondary',
              };
              $statutLbl = match($f['participant_statut']) {
                'valide' => 'Validée', 'present' => 'Présent',
                'inscrit' => 'Inscrit', 'absent' => 'Absent', default => '—',
              };
              $hasCert = !empty($f['certificat_url']);
            ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h($f['titre']) ?></div>
                  <?php if ($f['lieu']): ?><div class="small text-muted"><?= h($f['lieu']) ?></div><?php endif ?>
                </td>
                <td class="small" style="font-family:monospace"><?php $dt = $f['date_realisation'] ?: $f['date_debut']; echo $dt ? date('d.m.Y', strtotime($dt)) : '—'; ?></td>
                <td class="small"><?= h(ucfirst($f['modalite'] ?: '—')) ?></td>
                <td class="text-end small" style="font-family:monospace"><?= $f['heures_realisees'] ?: $f['duree_heures'] ?: '—' ?>h</td>
                <td><span class="badge text-bg-<?= $statutCls ?>" style="font-size:.7rem"><?= h($statutLbl) ?></span></td>
                <td class="text-center">
                  <?php if ($hasCert): ?>
                    <a href="<?= h($f['certificat_url']) ?>" target="_blank" class="text-success" title="Voir certificat"><i class="bi bi-paperclip"></i></a>
                  <?php elseif (in_array($f['participant_statut'], ['present','valide'])): ?>
                    <span class="text-warning" title="Certificat manquant"><i class="bi bi-exclamation-circle"></i></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="comp-empty"><i class="bi bi-mortarboard"></i><p class="mb-0">Aucune formation enregistrée pour ce collaborateur.</p></div>
    <?php endif ?>
  </div>
</div><!-- /#tab-form -->

<!-- ═══ TAB Documents ═══ -->
<div class="tab-pane fade" id="tab-docs" role="tabpanel">
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0" style="font-weight:600">Documents · attestations &amp; certificats</h5>
      <div class="text-muted small mt-1"><?= $nbDocuments ?> document<?= $nbDocuments > 1 ? 's' : '' ?> uploadé<?= $nbDocuments > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($documents): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($documents as $d): ?>
          <a href="<?= h($d['certificat_url']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi bi-file-earmark-pdf" style="font-size:1.3rem;color:var(--comp-danger)"></i>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= h($d['titre']) ?></div>
              <div class="small text-muted">
                <?= $d['date_realisation'] ? date('d.m.Y', strtotime($d['date_realisation'])) : 'Date inconnue' ?>
                <?php if ($d['heures_realisees']): ?> · <?= h($d['heures_realisees']) ?>h<?php endif ?>
              </div>
            </div>
            <i class="bi bi-arrow-up-right text-muted"></i>
          </a>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="comp-empty">
        <i class="bi bi-folder2-open"></i>
        <p class="mb-0">Aucun certificat uploadé.</p>
        <p class="small mt-2 text-muted">Le collaborateur peut téléverser ses attestations depuis son espace SpocSpace &gt; Formations.</p>
      </div>
    <?php endif ?>
  </div>
</div><!-- /#tab-docs -->

<!-- ═══ TAB Entretiens ═══ -->
<div class="tab-pane fade" id="tab-entr" role="tabpanel">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0" style="font-weight:600">Entretiens annuels</h5>
        <div class="text-muted small mt-1"><?= $nbEntretiens ?> au total<?= $dernierEntretien ? ' · dernier le ' . date('d.m.Y', strtotime($dernierEntretien['date_entretien'])) : '' ?></div>
      </div>
      <a href="?page=rh-entretiens&user_id=<?= h($userId) ?>" data-page-link class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-lg"></i> Nouveau
      </a>
    </div>
    <?php if ($entretiens): ?>
      <div class="list-group list-group-flush">
        <?php foreach ($entretiens as $e):
          $stCls = match($e['statut']) {
            'realise' => 'success', 'planifie' => 'info', 'reporte' => 'warning', default => 'secondary',
          };
        ?>
          <a href="?page=rh-entretiens-fiche&id=<?= h($e['id']) ?>" data-page-link class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--cl-bg);display:grid;place-items:center;flex-shrink:0">
              <i class="bi bi-chat-square-text" style="color:var(--comp-teal-600);font-size:1.2rem"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold">Entretien <?= h($e['annee']) ?></div>
              <div class="small text-muted" style="font-family:monospace"><?= h(date('d.m.Y', strtotime($e['date_entretien']))) ?></div>
            </div>
            <span class="badge text-bg-<?= $stCls ?>"><?= h(strtoupper($e['statut'])) ?></span>
            <i class="bi bi-arrow-right text-muted"></i>
          </a>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="comp-empty">
        <i class="bi bi-chat-square-text"></i>
        <p class="mb-0">Aucun entretien annuel.</p>
        <p class="small mt-2"><a href="?page=rh-entretiens&user_id=<?= h($userId) ?>" data-page-link>Planifier un entretien</a>.</p>
      </div>
    <?php endif ?>
  </div>
</div><!-- /#tab-entr -->

<!-- ═══ TAB Historique ═══ -->
<div class="tab-pane fade" id="tab-hist" role="tabpanel">
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0" style="font-weight:600">Historique formation &amp; compétences</h5>
      <div class="text-muted small mt-1">Chronologie des évènements récents</div>
    </div>
    <div class="card-body">
      <?php
      // Construire la timeline : formations + entretiens triés par date desc
      $timeline = [];
      foreach ($formationsAll as $f) {
          $d = $f['date_realisation'] ?: $f['date_debut'];
          if (!$d) continue;
          $timeline[] = ['date' => $d, 'kind' => 'formation', 'item' => $f];
      }
      foreach ($entretiens as $e) {
          $timeline[] = ['date' => $e['date_entretien'], 'kind' => 'entretien', 'item' => $e];
      }
      usort($timeline, fn($a, $b) => strcmp($b['date'], $a['date']));
      $timeline = array_slice($timeline, 0, 20);
      ?>

      <?php if (!$timeline): ?>
        <div class="comp-empty"><i class="bi bi-clock-history"></i><p class="mb-0">Aucun évènement enregistré.</p></div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($timeline as $t):
            $isForm = $t['kind'] === 'formation';
            $i = $t['item'];
          ?>
            <div class="d-flex gap-3 align-items-start">
              <div style="width:40px;height:40px;border-radius:50%;background:<?= $isForm ? '#ecf5f3' : '#fef3c7' ?>;color:<?= $isForm ? '#1f6359' : '#92400e' ?>;display:grid;place-items:center;flex-shrink:0;font-size:1rem">
                <i class="bi bi-<?= $isForm ? 'mortarboard' : 'chat-square-text' ?>"></i>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold"><?= h($isForm ? $i['titre'] : 'Entretien ' . $i['annee']) ?></div>
                    <div class="small text-muted" style="font-family:monospace">
                      <?= date('d.m.Y', strtotime($t['date'])) ?>
                      <?php if ($isForm && $i['heures_realisees']): ?> · <?= $i['heures_realisees'] ?>h<?php endif ?>
                    </div>
                  </div>
                  <?php if ($isForm): ?>
                    <span class="badge text-bg-secondary" style="font-size:.7rem"><?= h(strtoupper($i['participant_statut'] ?? '—')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary" style="font-size:.7rem"><?= h(strtoupper($i['statut'] ?? '—')) ?></span>
                  <?php endif ?>
                </div>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>
  </div>
</div><!-- /#tab-hist -->

</div><!-- /.tab-content -->

<style>
.comp-tabs .nav-link {
  color: var(--cl-text-muted, #6b6b6b);
  border: 0; border-bottom: 2px solid transparent;
  padding: 10px 16px; font-weight: 500; font-size: .92rem;
  background: transparent;
  transition: all .15s ease;
}
.comp-tabs .nav-link:hover {
  color: var(--cl-text, #1a1a1a);
  border-bottom-color: var(--cl-border-hover, #d4d0ca);
}
.comp-tabs .nav-link.active {
  color: var(--cl-primary, #191918) !important;
  border-bottom-color: var(--cl-primary, #191918) !important;
  background: transparent;
  font-weight: 600;
}
.comp-tabs .nav-link .badge {
  font-family: 'JetBrains Mono', monospace; font-size: .65rem;
  padding: 2px 6px; vertical-align: middle;
}
.comp-tabs .nav-link.active .badge {
  background: var(--cl-primary, #191918) !important;
  color: #fff;
}
</style>
