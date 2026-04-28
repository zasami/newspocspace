<?php
// ─── Fiche compétences collaborateur · version Care v2 (maquette fidèle) ─────

$userId = $_GET['id'] ?? '';
$user = $userId ? Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom, f.secteur_fegems,
            np1.prenom AS np1_prenom, np1.nom AS np1_nom,
            (SELECT COUNT(*) FROM user_modules um JOIN modules m ON m.id = um.module_id WHERE um.user_id = u.id AND um.is_principal = 1) AS has_principal,
            (SELECT m.nom FROM user_modules um JOIN modules m ON m.id = um.module_id WHERE um.user_id = u.id AND um.is_principal = 1 LIMIT 1) AS module_principal
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN users np1 ON np1.id = u.n_plus_un_id
     WHERE u.id = ?", [$userId]
) : null;

if (!$user) {
    echo '<div class="care-page"><div class="ds-card ds-card-padded"><div class="ds-empty">';
    echo '<i class="bi bi-person-x"></i><div class="ds-empty-title">Aucun collaborateur sélectionné</div>';
    echo '<div class="ds-empty-msg"><a href="?page=rh-formations-cartographie" data-page-link>Retour à la cartographie</a></div>';
    echo '</div></div></div>';
    return;
}

$competences = Db::fetchAll(
    "SELECT cu.*, t.code AS them_code, t.nom AS them_nom, t.categorie AS them_categorie,
            t.tag_affichage, t.icone, t.duree_validite_mois,
            ev.prenom AS eval_prenom, ev.nom AS eval_nom,
            (SELECT MAX(date_realisation) FROM formation_participants fp
             JOIN formations f ON f.id = fp.formation_id
             JOIN formation_thematiques ft ON ft.formation_id = f.id
             WHERE fp.user_id = cu.user_id AND ft.thematique_id = cu.thematique_id
               AND fp.statut IN ('present','valide')) AS prochaine_eval
     FROM competences_user cu
     JOIN competences_thematiques t ON t.id = cu.thematique_id
     LEFT JOIN users ev ON ev.id = cu.evaluator_id
     WHERE cu.user_id = ?
     ORDER BY FIELD(cu.priorite,'haute','moyenne','basse','a_jour'), cu.ecart DESC, t.ordre ASC",
    [$userId]
);

$nbEvalues = 0; $sumNiv = 0; $sumNivPond = 0;
foreach ($competences as $c) {
    if ($c['niveau_actuel'] !== null) { $nbEvalues++; $sumNiv += (int) $c['niveau_actuel']; }
    if ($c['niveau_requis'] !== null) { $sumNivPond += (int) $c['niveau_requis']; }
}
$niveauGlobal = $nbEvalues > 0 ? round($sumNiv / $nbEvalues, 1) : 0;
$nivRequisMoy = count($competences) > 0 ? round($sumNivPond / max(1, count($competences)), 1) : 0;
$ecartTotal   = $nivRequisMoy - $niveauGlobal;

$nbAJour   = count(array_filter($competences, fn($c) => $c['priorite'] === 'a_jour'));
$nbHaute   = count(array_filter($competences, fn($c) => $c['priorite'] === 'haute'));
$nbMoyenne = count(array_filter($competences, fn($c) => $c['priorite'] === 'moyenne'));
$conformite = count($competences) > 0 ? round(($nbAJour / count($competences)) * 100) : 0;
$nbSousSeuil = $nbHaute + $nbMoyenne;

$heuresAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut IN ('present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())",
    [$userId]
);
$heuresPlanifiees = (float) Db::getOne(
    "SELECT COALESCE(SUM(f.duree_heures), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut = 'inscrit'
       AND YEAR(f.date_debut) = YEAR(CURDATE()) AND f.date_debut > CURDATE()",
    [$userId]
);

$formations = Db::fetchAll(
    "SELECT f.id, f.titre, f.statut, f.date_debut, f.duree_heures, f.lieu, f.modalite,
            p.id AS participant_id, p.statut AS participant_statut, p.heures_realisees, p.evaluation_manager,
            p.date_realisation
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ?
     ORDER BY f.date_debut DESC LIMIT 10",
    [$userId]
);
$nbFormationsTotal = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants WHERE user_id = ?", [$userId]);

$objectifs = Db::fetchAll(
    "SELECT o.*, t.nom AS them_nom FROM competences_objectifs_annuels o
     LEFT JOIN competences_thematiques t ON t.id = o.thematique_id_liee
     WHERE o.user_id = ? AND o.annee = YEAR(CURDATE())
     ORDER BY FIELD(o.trimestre_cible,'Q1','Q2','Q3','Q4','annuel'), o.ordre",
    [$userId]
);
$nbObjectifsAtteints = count(array_filter($objectifs, fn($o) => $o['statut'] === 'atteint'));

$entretiens = Db::fetchAll(
    "SELECT * FROM entretiens_annuels WHERE user_id = ? ORDER BY date_entretien DESC LIMIT 5",
    [$userId]
);
$dernierEntretien = $entretiens[0] ?? null;
$nbEntretiens = (int) Db::getOne("SELECT COUNT(*) FROM entretiens_annuels WHERE user_id = ?", [$userId]);

$referents = Db::fetchAll(
    "SELECT cr.*, t.nom AS them_nom FROM competences_referents cr
     JOIN competences_thematiques t ON t.id = cr.thematique_id
     WHERE cr.user_id = ? AND cr.actif = 1",
    [$userId]
);

$grouped = ['haute' => [], 'moyenne' => [], 'basse' => [], 'a_jour' => []];
foreach ($competences as $c) $grouped[$c['priorite']][] = $c;

$secteurMap = [
    'soins' => ['Soins', 's-soins'], 'socio_culturel' => ['Socio-culturel', 's-anim'],
    'hotellerie' => ['Hôtellerie', 's-hot'], 'maintenance' => ['Maintenance', 's-tech'],
    'administration' => ['Administration', 's-admin'], 'management' => ['Management', 's-mgmt'],
];
[$secLabel, $secCls] = $secteurMap[$user['secteur_fegems']] ?? ['—', ''];

$initials = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));

// Ancienneté + formatage
$anciennete = '';
$dateEntreeAffichee = '—';
if ($user['date_entree']) {
    $dt = new DateTime($user['date_entree']);
    $diff = $dt->diff(new DateTime());
    if ($diff->y >= 1) {
        $anciennete = $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    } else {
        $anciennete = $diff->m . ' mois';
    }
    $dateEntreeAffichee = $dt->format('d.m.Y');
}

$expFonction = $user['experience_fonction_annees'] ? number_format($user['experience_fonction_annees'], 0, ',', '') . ' ans' : '—';
$prochainEntretien = $user['prochain_entretien_date']
    ? date('d.m.Y', strtotime($user['prochain_entretien_date']))
    : '—';

// Radar : 6 axes
$radarGroups = [
    'HPCI'              => ['HPCI_BASE', 'HPCI_PRECAUTIONS'],
    'BLS-AED'           => ['BLS_AED'],
    'Soins palliatifs'  => ['SOINS_PALLIATIFS'],
    'Bientraitance'     => ['BIENTRAITANCE', 'BPSD'],
    'Actes délégués'    => ['ACTES_DELEGUES_INF', 'ACTES_DELEGUES_ASA', 'ACTES_DELEGUES'],
    'Transmissions'     => ['TRANSMISSIONS_INF', 'TRANSMISSIONS_AS'],
];
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

$prioMeta = [
    'haute'   => ['Priorité haute',  'priorite-haute'],
    'moyenne' => ['Écart léger',     'priorite-moyenne'],
    'basse'   => ['Écart faible',    'priorite-basse'],
    'a_jour'  => ['À niveau requis', 'priorite-ajour'],
];
?>
<!-- Breadcrumb -->
<nav class="careb-crumb">
  <a href="?page=rh-formations-cartographie" data-page-link>RH</a>
  <i class="bi bi-chevron-right"></i>
  <a href="?page=users" data-page-link>Collaborateurs</a>
  <i class="bi bi-chevron-right"></i>
  <span><?= h($user['prenom'] . ' ' . $user['nom']) ?></span>
</nav>

<div class="care-page" style="padding-top:0">

  <!-- HERO -->
  <header class="careb-hero">
    <div class="careb-hero-row">
      <div class="careb-hero-avatar"><?= h($initials) ?></div>
      <div class="careb-hero-id">
        <div class="careb-hero-eyebrow"><?= h(strtoupper($user['type_employe'] === 'externe' ? 'COLLABORATEUR' : ($user['sexe'] === 'F' ? 'COLLABORATRICE' : 'COLLABORATEUR'))) ?> · <?= h(strtoupper($secLabel)) ?></div>
        <h1><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></h1>
        <div class="careb-hero-meta">
          <span><?= h($user['fonction_nom'] ?? '—') ?></span>
          <?php if ($user['np1_prenom']): ?><span class="sep">·</span><span>N+1 <?= h($user['np1_prenom'] . ' ' . $user['np1_nom']) ?></span><?php endif ?>
          <?php if ($user['module_principal']): ?><span class="sep">·</span><span>Étage <?= h($user['module_principal']) ?></span><?php endif ?>
        </div>
      </div>
      <div class="careb-hero-actions">
        <a href="?page=user-edit&id=<?= h($userId) ?>" class="careb-btn-light" data-page-link>
          <i class="bi bi-pencil"></i> Modifier
        </a>
        <button class="careb-btn-light" id="exportPdfBtn" type="button">
          <i class="bi bi-file-pdf"></i> Exporter PDF
        </button>
        <button class="careb-btn-primary" id="lancerEntretienBtn" type="button" data-uid="<?= h($userId) ?>">
          <i class="bi bi-chat-square-text"></i> Lancer entretien annuel
        </button>
      </div>
    </div>

    <div class="careb-hero-grid">
      <div class="careb-hero-cell">
        <div class="careb-hero-lbl">TAUX D'ACTIVITÉ</div>
        <div class="careb-hero-val"><?= $user['taux'] ? (int) round($user['taux']) . '%' : '—' ?></div>
      </div>
      <div class="careb-hero-cell">
        <div class="careb-hero-lbl">ANCIENNETÉ</div>
        <div class="careb-hero-val"><?= h($anciennete ?: '—') ?> <?php if ($dateEntreeAffichee !== '—'): ?><span class="careb-hero-sub">depuis <?= h($dateEntreeAffichee) ?></span><?php endif ?></div>
      </div>
      <div class="careb-hero-cell">
        <div class="careb-hero-lbl">EXPÉRIENCE FONCTION</div>
        <div class="careb-hero-val"><?= h($expFonction) ?></div>
      </div>
      <div class="careb-hero-cell">
        <div class="careb-hero-lbl">CONTRAT</div>
        <div class="careb-hero-val"><?= h($user['type_contrat'] ?: '—') ?> <?php if ($user['cct']): ?><span class="careb-hero-sub">· <?= h($user['cct']) ?></span><?php endif ?></div>
      </div>
      <div class="careb-hero-cell">
        <div class="careb-hero-lbl">PROCHAIN ENTRETIEN</div>
        <div class="careb-hero-val"><?= h($prochainEntretien) ?></div>
      </div>
    </div>
  </header>

  <!-- KPI cards -->
  <div class="careb-kpi-row">
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">NIVEAU GLOBAL</div>
      <div class="careb-kpi-val"><?= number_format($niveauGlobal, 1, ',', '') ?><span class="careb-kpi-unit">/ 4</span></div>
      <div class="careb-kpi-sub">Échelle Fegems · moy. pondérée</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">CONFORMITÉ FORMATIONS</div>
      <div class="careb-kpi-val careb-kpi-val--<?= $conformite >= 70 ? 'ok' : ($conformite >= 50 ? 'warn' : 'danger') ?>"><?= $conformite ?>%</div>
      <div class="careb-kpi-sub"><?= $nbSousSeuil ?> thématique<?= $nbSousSeuil > 1 ? 's' : '' ?> sous le seuil</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">ÉCARTS À COMBLER</div>
      <div class="careb-kpi-val careb-kpi-val--danger"><?= $nbHaute + $nbMoyenne ?></div>
      <div class="careb-kpi-sub"><?= $nbHaute ?> priorité haute</div>
    </div>
    <div class="careb-kpi">
      <div class="careb-kpi-lbl">HEURES FORMATION <?= date('Y') ?></div>
      <div class="careb-kpi-val"><?= number_format($heuresAnnee, 0, ',', ' ') ?><span class="careb-kpi-unit">h</span></div>
      <div class="careb-kpi-sub">+ <?= number_format($heuresPlanifiees, 0, ',', ' ') ?>h planifiées</div>
    </div>
  </div>

  <!-- Onglets -->
  <ul class="careb-tabs" role="tablist">
    <li class="active" data-tab="competences">
      <i class="bi bi-grid-3x3"></i> Compétences <span class="careb-tab-count"><?= count($competences) ?></span>
    </li>
    <li data-tab="formations">
      <i class="bi bi-mortarboard"></i> Formations <span class="careb-tab-count"><?= $nbFormationsTotal ?></span>
    </li>
    <li data-tab="documents">
      <i class="bi bi-file-text"></i> Documents <span class="careb-tab-count">—</span>
    </li>
    <li data-tab="entretiens">
      <i class="bi bi-chat-square-text"></i> Entretiens <span class="careb-tab-count"><?= $nbEntretiens ?></span>
    </li>
    <li data-tab="historique">
      <i class="bi bi-clock-history"></i> Historique
    </li>
  </ul>

  <!-- Tab Compétences -->
  <div class="careb-tab-pane active" data-pane="competences">

    <!-- Profil de compétences (radar) -->
    <?php if (count($radarData) >= 3):
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
              'x' => round($cx + ($rMax + 22) * cos($angle), 1),
              'y' => round($cy + ($rMax + 22) * sin($angle) + 4, 1),
              'label' => $d['axe'],
          ];
      }
    ?>
    <div class="careb-card">
      <div class="careb-card-head">
        <div>
          <div class="careb-card-title">Profil de compétences</div>
          <div class="careb-card-sub">Synthèse par domaine · niveau actuel vs requis selon référentiel Fegems</div>
        </div>
        <div class="t-meta">Mise à jour <?= date('d.m.Y') ?></div>
      </div>
      <div class="careb-card-body">
        <div class="careb-radar-row">
          <div class="careb-radar-svg">
            <svg viewBox="0 0 400 360">
              <defs>
                <radialGradient id="careRadarReq2" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#9cb8a8" stop-opacity=".05"/>
                  <stop offset="100%" stop-color="#9cb8a8" stop-opacity=".25"/>
                </radialGradient>
                <radialGradient id="careRadarCur2" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#2d8074" stop-opacity=".15"/>
                  <stop offset="100%" stop-color="#2d8074" stop-opacity=".5"/>
                </radialGradient>
              </defs>
              <?php for ($i = 1; $i <= 4; $i++): $r = ($i / 4) * $rMax; ?>
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="<?= $i === 4 ? '#d4ddda' : '#e3ebe8' ?>" stroke-width="1" <?= $i === 4 ? 'stroke-dasharray="2 3"' : '' ?>/>
                <text x="<?= $cx + 4 ?>" y="<?= $cy - $r + 3 ?>" font-size="9" fill="#9bb0ad" font-family="JetBrains Mono, monospace"><?= $i ?></text>
              <?php endfor ?>
              <?php foreach ($radarData as $i => $d):
                  $angle = -M_PI / 2 + ($i * 2 * M_PI / $axes);
                  $x2 = round($cx + $rMax * cos($angle), 1);
                  $y2 = round($cy + $rMax * sin($angle), 1);
              ?>
                <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="#e3ebe8" stroke-width="1"/>
              <?php endforeach ?>
              <polygon points="<?= implode(' ', $ptsRequis) ?>" fill="url(#careRadarReq2)" stroke="#9cb8a8" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.85"/>
              <polygon points="<?= implode(' ', $ptsActuel) ?>" fill="url(#careRadarCur2)" stroke="#2d8074" stroke-width="2"/>
              <?php foreach ($ptsActuel as $p): [$x, $y] = explode(',', $p); ?>
                <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" fill="#1f6359" stroke="#fff" stroke-width="2"/>
              <?php endforeach ?>
              <?php foreach ($labelPos as $l): ?>
                <text x="<?= $l['x'] ?>" y="<?= $l['y'] ?>" text-anchor="middle" font-size="11" font-weight="600" fill="#324e4a" font-family="Outfit, sans-serif"><?= h($l['label']) ?></text>
              <?php endforeach ?>
            </svg>
          </div>
          <div class="careb-radar-side">
            <div class="careb-radar-line">
              <span class="careb-radar-dot careb-radar-dot--solid"></span>
              <span class="careb-radar-lbl">Niveau actuel</span>
              <span class="careb-radar-val t-num"><?= number_format($niveauGlobal, 1, ',', '') ?></span>
            </div>
            <div class="careb-radar-line">
              <span class="careb-radar-sub">moyenne pondérée</span>
              <span class="careb-radar-progression t-num">+0,3</span>
            </div>
            <div class="careb-radar-divider"></div>
            <div class="careb-radar-line">
              <span class="careb-radar-dot careb-radar-dot--dashed"></span>
              <span class="careb-radar-lbl">Niveau requis</span>
              <span class="careb-radar-val t-num"><?= number_format($nivRequisMoy, 1, ',', '') ?></span>
            </div>
            <div class="careb-radar-line">
              <span class="careb-radar-sub">moyenne pondérée</span>
              <span></span>
            </div>
            <div class="careb-radar-line">
              <span class="careb-radar-sub">écart total</span>
              <span class="careb-radar-val t-num" style="color:var(--danger)">−<?= number_format($ecartTotal, 1, ',', '') ?></span>
            </div>
            <div class="careb-radar-divider"></div>
            <div class="careb-radar-synthese">
              <div class="t-eyebrow" style="margin-bottom:6px">Synthèse par domaine</div>
              <?php
              $analyses = [];
              foreach ($radarData as $d) {
                  $diff = $d['requis'] - $d['actuel'];
                  if ($diff >= 1.5) $analyses[] = ['danger', $d['axe'], 'reste le point de tension majeur'];
                  elseif ($diff >= 0.6) $analyses[] = ['warn', $d['axe'], 'à consolider'];
              }
              foreach ($competences as $c) {
                  if ($c['priorite'] === 'a_jour' && in_array($c['them_code'], ['BIENTRAITANCE', 'BPSD'], true)) {
                      $analyses[] = ['ok', 'Bientraitance', 'au niveau requis'];
                      break;
                  }
              }
              if (!$analyses) $analyses[] = ['ok', 'Profil aligné', 'sur les requis Fegems'];
              ?>
              <div class="careb-radar-synth-text">
                <?php foreach (array_slice($analyses, 0, 3) as $a): ?>
                  <strong style="color:var(--<?= $a[0] === 'ok' ? 'ok' : ($a[0] === 'warn' ? 'warn' : 'danger') ?>)"><?= h($a[1]) ?></strong>
                  <span><?= h($a[2]) ?></span>.
                <?php endforeach ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="careb-radar-footer">
        <div class="careb-radar-stat">
          <div class="careb-radar-stat-num" style="color:var(--ok)"><?= $nbAJour ?></div>
          <div class="careb-radar-stat-lbl">À niveau</div>
        </div>
        <div class="careb-radar-stat">
          <div class="careb-radar-stat-num" style="color:var(--warn)"><?= $nbMoyenne ?></div>
          <div class="careb-radar-stat-lbl">Écart léger</div>
        </div>
        <div class="careb-radar-stat">
          <div class="careb-radar-stat-num" style="color:var(--danger)"><?= $nbHaute ?></div>
          <div class="careb-radar-stat-lbl">Écart critique</div>
        </div>
        <div class="careb-radar-stat">
          <div class="careb-radar-stat-num"><?= count($competences) ?></div>
          <div class="careb-radar-stat-lbl">Thématiques suivies</div>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!-- Détail thématiques -->
    <div class="careb-card">
      <div class="careb-card-head">
        <div>
          <div class="careb-card-title">Détail des thématiques</div>
          <div class="careb-card-sub">Saisie inline · marqueur noir = niveau requis · barre teal = niveau validé</div>
        </div>
        <button class="careb-btn-soft" id="addThematiqueBtn">
          <i class="bi bi-plus-lg"></i> Ajouter une thématique
        </button>
      </div>

      <?php if (!$competences): ?>
        <div class="ds-empty">
          <i class="bi bi-clipboard-x"></i>
          <div class="ds-empty-title">Aucune compétence évaluée</div>
          <div class="ds-empty-msg">Définies dans <a href="?page=rh-formations-profil" data-page-link>Profil d'équipe attendu</a>.</div>
        </div>
      <?php else: ?>
        <?php foreach ($prioMeta as $prio => [$label, $color]):
          $list = $grouped[$prio] ?? [];
          if (!$list) continue;
        ?>
          <div class="careb-prio-section">
            <div class="careb-prio-header">
              <span class="careb-prio-dot careb-prio-dot--<?= $color ?>"></span>
              <strong><?= h(strtoupper($label)) ?></strong>
              <span class="careb-prio-count"><?= count($list) ?></span>
            </div>

            <?php foreach ($list as $c):
              $niv = (int) ($c['niveau_actuel'] ?? 0);
              $req = (int) ($c['niveau_requis'] ?? 0);
              $ecart = max($req - $niv, 0);
              $expired = $c['date_expiration'] && $c['date_expiration'] < date('Y-m-d');
              $expSoon = $c['date_expiration'] && !$expired && strtotime($c['date_expiration']) < strtotime('+60 days');
            ?>
              <div class="careb-them-row">
                <div class="careb-them-head">
                  <div class="careb-them-title">
                    <strong><?= h($c['them_nom']) ?></strong>
                    <?php if ($c['tag_affichage']): ?>
                      <span class="careb-tag-tag"><?= h(strtoupper($c['tag_affichage'])) ?></span>
                    <?php endif ?>
                    <?php if ($expired): ?>
                      <span class="careb-tag-expired">EXPIRÉE <?= h(date('d.m.Y', strtotime($c['date_expiration']))) ?></span>
                    <?php elseif ($expSoon): ?>
                      <span class="careb-tag-soon">À RENOUVELER <?= h(date('d.m.Y', strtotime($c['date_expiration']))) ?></span>
                    <?php elseif ($prio === 'a_jour'): ?>
                      <span class="careb-tag-ok">À JOUR</span>
                    <?php endif ?>
                  </div>
                  <?php if ($ecart > 0): ?>
                    <div class="careb-them-ecart" data-color="<?= $color ?>">
                      Écart <span class="t-num">+<?= $ecart ?></span>
                      <span class="careb-them-ecart-sub"><?= $ecart ?> NIVEAU<?= $ecart > 1 ? 'X' : '' ?></span>
                    </div>
                  <?php else: ?>
                    <div class="careb-them-ecart careb-them-ecart--ok">
                      <i class="bi bi-check-circle"></i> Conforme
                    </div>
                  <?php endif ?>
                </div>

                <?php if ($c['date_evaluation']): ?>
                  <div class="careb-them-meta">
                    Évalué <span class="t-num"><?= date('d.m.Y', strtotime($c['date_evaluation'])) ?></span>
                    <?php if ($c['eval_prenom']): ?> · par Dr. <?= h(mb_substr($c['eval_prenom'], 0, 1) . '. ' . $c['eval_nom']) ?><?php endif ?>
                    <?php if ($c['date_expiration']): ?>
                      · prochaine éval <span class="t-num"><?= date('d.m.Y', strtotime($c['date_expiration'])) ?></span>
                    <?php endif ?>
                  </div>
                <?php endif ?>

                <!-- Track avec niveaux 1-4 -->
                <div class="careb-them-track">
                  <?php for ($i = 1; $i <= 4; $i++):
                    $isFilled = $i <= $niv;
                    $isReq = $i === $req;
                    $segCls = $isFilled
                        ? ($ecart === 0 ? 'careb-them-seg--ok' : ($color === 'priorite-haute' ? 'careb-them-seg--bad' : 'careb-them-seg--warn'))
                        : 'careb-them-seg--empty';
                    $lblMap = [1 => '1', 2 => '2 · SUPERVISION', 3 => '3 · AUTONOME', 4 => '4 · RÉFÉRENT'];
                  ?>
                    <div class="careb-them-seg <?= $segCls ?> <?= $isReq ? 'careb-them-seg--req' : '' ?>">
                      <span class="careb-them-seg-lbl"><?= h($lblMap[$i]) ?></span>
                    </div>
                  <?php endfor ?>
                </div>

                <!-- Boutons -->
                <div class="careb-them-actions">
                  <?php if ($c['attestation_url'] || ($prio === 'a_jour' && $c['date_validation_manager'])): ?>
                    <a href="<?= h($c['attestation_url'] ?: '#') ?>" class="careb-btn-light-sm" <?= $c['attestation_url'] ? 'target="_blank"' : '' ?>>
                      <i class="bi bi-paperclip"></i> Voir attestation
                    </a>
                  <?php endif ?>
                  <button class="careb-btn-light-sm" data-reeval="<?= h($c['id']) ?>">
                    <i class="bi bi-pencil"></i> Re-évaluer
                  </button>
                  <?php if ($expired || $prio === 'haute'): ?>
                    <button class="careb-btn-primary-sm" data-inscrire-form="<?= h($c['thematique_id']) ?>">
                      <i class="bi bi-mortarboard"></i> Inscrire à formation
                    </button>
                  <?php elseif ($prio === 'moyenne'): ?>
                    <button class="careb-btn-light-sm" data-renouvellement="<?= h($c['id']) ?>">
                      <i class="bi bi-arrow-repeat"></i> Renouvellement
                    </button>
                  <?php endif ?>
                </div>
              </div>
            <?php endforeach ?>
          </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>

    <!-- Informations -->
    <div class="careb-card">
      <div class="careb-card-head">
        <div class="careb-card-title">Informations</div>
        <button class="careb-btn-icon"><i class="bi bi-pencil"></i></button>
      </div>
      <div class="careb-card-body">
        <div class="careb-info-grid">
          <div>
            <div class="careb-info-lbl">DIPLÔME</div>
            <div class="careb-info-val"><?= h($user['diplome_principal'] ?: '—') ?></div>
          </div>
          <div>
            <div class="careb-info-lbl">RECONNAISSANCE CRS</div>
            <div class="careb-info-val">
              <?php if ($user['reconnaissance_crs_annee']): ?>
                <span style="color:var(--ok)">✓ <?= h($user['reconnaissance_crs_annee']) ?></span>
              <?php else: ?>—<?php endif ?>
            </div>
          </div>
          <div>
            <div class="careb-info-lbl">DATE D'ENTRÉE</div>
            <div class="careb-info-val t-num"><?= h($dateEntreeAffichee) ?></div>
          </div>
          <div>
            <div class="careb-info-lbl">TYPE CONTRAT</div>
            <div class="careb-info-val"><?= h($user['type_contrat'] ?: '—') ?></div>
          </div>
          <div>
            <div class="careb-info-lbl">TAUX D'ACTIVITÉ</div>
            <div class="careb-info-val"><?= $user['taux'] ? (int) round($user['taux']) . '% (' . round($user['taux'] * 0.4) . 'h/sem.)' : '—' ?></div>
          </div>
          <div>
            <div class="careb-info-lbl">SECTEUR · ÉTAGE</div>
            <div class="careb-info-val"><?= h($secLabel) ?><?php if ($user['module_principal']): ?> · <?= h($user['module_principal']) ?><?php endif ?></div>
          </div>
          <?php if ($referents): ?>
          <div style="grid-column:1/-1">
            <div class="careb-info-lbl">RÔLES SPÉCIAUX</div>
            <div class="careb-info-val">
              <?php foreach ($referents as $r): ?>
                <div>Personne ressource <?= h($r['them_nom']) ?> (depuis <?= date('m.Y', strtotime($r['depuis_le'])) ?>)</div>
              <?php endforeach ?>
            </div>
          </div>
          <?php endif ?>
          <?php if ($user['disponibilite_formation']): ?>
          <div style="grid-column:1/-1">
            <div class="careb-info-lbl">DISPONIBILITÉ FORMATION</div>
            <div class="careb-info-val"><?= h($user['disponibilite_formation']) ?></div>
          </div>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- Formations -->
    <div class="careb-card">
      <div class="careb-card-head">
        <div>
          <div class="careb-card-title">Formations</div>
          <?php
          $nbEnCours = count(array_filter($formations, fn($f) => $f['statut'] === 'en_cours'));
          $nbRealisees = count(array_filter($formations, fn($f) => $f['participant_statut'] === 'valide'));
          ?>
          <div class="careb-card-sub"><?= $nbEnCours ?> en cours · <?= $nbRealisees ?> réalisées en <?= date('Y') ?></div>
        </div>
        <button class="careb-btn-icon"><i class="bi bi-plus"></i></button>
      </div>
      <?php if ($formations): ?>
        <?php foreach (array_slice($formations, 0, 3) as $f):
          $sts = match($f['participant_statut']) {
            'valide' => ['RÉALISÉE', 'realisee'],
            'present' => ['PRÉSENT', 'present'],
            'inscrit' => ['INSCRITE', 'inscrite'],
            'absent' => ['ABSENT', 'absent'],
            default => ['—', 'inscrite'],
          };
          $cours = $f['statut'] === 'en_cours';
          if ($cours) $sts = ['EN COURS', 'encours'];

          $totalHeures = $f['heures_realisees'] ?: $f['duree_heures'];
        ?>
          <div class="careb-form-row">
            <div class="careb-form-info">
              <div class="careb-form-title"><?= h($f['titre']) ?></div>
              <div class="careb-form-meta">
                <span><?= h(strtolower($f['modalite'] ?: $f['lieu'] ?: 'Fegems')) ?></span>
                <?php if ($f['duree_heures']): ?>
                  <span class="sep">·</span><span><?= h($f['duree_heures']) ?>h</span>
                <?php endif ?>
              </div>
            </div>
            <div class="careb-form-progress">
              <?php if ($cours && $f['heures_realisees'] && $f['duree_heures']):
                $pct = ($f['heures_realisees'] / $f['duree_heures']) * 100;
              ?>
                <div class="careb-form-bar">
                  <div class="careb-form-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="careb-form-bar-lbl t-num"><?= h($f['heures_realisees']) ?> / <?= h($f['duree_heures']) ?>h</div>
              <?php elseif ($f['date_realisation']): ?>
                <div class="t-num t-meta"><?= date('d.m.Y', strtotime($f['date_realisation'])) ?></div>
              <?php elseif ($f['date_debut']): ?>
                <div class="t-num t-meta">Session <?= date('d.m.Y', strtotime($f['date_debut'])) ?></div>
              <?php endif ?>
            </div>
            <div class="careb-form-status">
              <span class="careb-status careb-status--<?= $sts[1] ?>"><?= h($sts[0]) ?></span>
              <?php if ($f['evaluation_manager'] === 'satisfaisant'): ?>
                <div class="t-meta" style="margin-top:4px">Évaluation : Très bonne</div>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="ds-empty"><i class="bi bi-mortarboard"></i><div class="ds-empty-title">Aucune formation</div></div>
      <?php endif ?>
    </div>

    <!-- Objectifs annuels -->
    <div class="careb-card">
      <div class="careb-card-head">
        <div>
          <div class="careb-card-title">Objectifs annuels</div>
          <?php if ($dernierEntretien): ?>
            <div class="careb-card-sub">Issus de l'entretien <?= date('d.m.Y', strtotime($dernierEntretien['date_entretien'])) ?></div>
          <?php endif ?>
        </div>
        <div class="t-meta"><?= $nbObjectifsAtteints ?>/<?= count($objectifs) ?></div>
      </div>
      <?php if ($objectifs): ?>
        <?php foreach ($objectifs as $i => $o):
          $iconMap = ['atteint' => '✓', 'en_cours' => '<span class="t-num">'.($i+1).'</span>', 'a_definir' => '<span class="t-num">'.($i+1).'</span>', 'reporte' => '⏸', 'abandonne' => '✕'];
          $clsMap  = ['atteint' => 'ok', 'en_cours' => 'cours', 'a_definir' => 'todo', 'reporte' => 'paused', 'abandonne' => 'cancel'];
          $icon = $iconMap[$o['statut']] ?? '<span class="t-num">'.($i+1).'</span>';
          $cls  = $clsMap[$o['statut']] ?? 'todo';
          $statutLbl = ['atteint' => 'ATTEINT', 'en_cours' => 'EN COURS', 'a_definir' => 'À DÉFINIR', 'reporte' => 'REPORTÉ', 'abandonne' => 'ABANDONNÉ'][$o['statut']] ?? '';
        ?>
          <div class="careb-obj-row">
            <div class="careb-obj-icon careb-obj-icon--<?= $cls ?>"><?= $icon ?></div>
            <div class="careb-obj-info">
              <div class="careb-obj-tri"><?= h($o['trimestre_cible']) ?> <?= date('Y') ?> <?php if ($statutLbl): ?>· <?= h($statutLbl) ?><?php endif ?></div>
              <div class="careb-obj-titre"><?= h($o['libelle']) ?></div>
              <?php if ($o['description']): ?>
                <div class="careb-obj-desc"><?= h($o['description']) ?></div>
              <?php endif ?>
              <?php if ($o['date_atteint']): ?>
                <div class="careb-obj-desc">Atteint <?= date('d.m.Y', strtotime($o['date_atteint'])) ?></div>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="ds-empty"><i class="bi bi-flag"></i><div class="ds-empty-title">Aucun objectif défini</div></div>
      <?php endif ?>
    </div>

    <!-- Citation entretien -->
    <?php if ($dernierEntretien && $dernierEntretien['notes_manager']): ?>
    <div class="careb-quote">
      <div class="careb-quote-text">« <?= h($dernierEntretien['notes_manager']) ?> »</div>
      <div class="careb-quote-author">— Entretien du <?= date('d.m.Y', strtotime($dernierEntretien['date_entretien'])) ?></div>
    </div>
    <?php endif ?>

  </div>

  <!-- Tab Formations -->
  <div class="careb-tab-pane" data-pane="formations" hidden>
    <div class="careb-card careb-card-padded">
      <div class="ds-empty"><i class="bi bi-mortarboard"></i><div class="ds-empty-title">Section Formations détaillées à venir</div><div class="ds-empty-msg">Voir la section "Formations" en haut pour l'aperçu.</div></div>
    </div>
  </div>
  <div class="careb-tab-pane" data-pane="documents" hidden>
    <div class="careb-card careb-card-padded">
      <div class="ds-empty"><i class="bi bi-file-text"></i><div class="ds-empty-title">Documents</div><div class="ds-empty-msg">À venir</div></div>
    </div>
  </div>
  <div class="careb-tab-pane" data-pane="entretiens" hidden>
    <div class="careb-card careb-card-padded">
      <?php if ($entretiens): ?>
        <?php foreach ($entretiens as $e): ?>
          <div class="careb-form-row">
            <div class="careb-form-info">
              <div class="careb-form-title">Entretien <?= h($e['annee']) ?></div>
              <div class="careb-form-meta"><span><?= h(date('d.m.Y', strtotime($e['date_entretien']))) ?></span></div>
            </div>
            <div class="careb-form-status">
              <span class="careb-status careb-status--<?= $e['statut'] === 'realise' ? 'realisee' : 'inscrite' ?>"><?= h(strtoupper($e['statut'])) ?></span>
            </div>
            <div>
              <a href="?page=rh-entretiens-fiche&id=<?= h($e['id']) ?>" class="careb-btn-light-sm" data-page-link><i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        <?php endforeach ?>
      <?php else: ?>
        <div class="ds-empty"><i class="bi bi-chat-square-text"></i><div class="ds-empty-title">Aucun entretien</div></div>
      <?php endif ?>
    </div>
  </div>
  <div class="careb-tab-pane" data-pane="historique" hidden>
    <div class="careb-card careb-card-padded">
      <div class="ds-empty"><i class="bi bi-clock-history"></i><div class="ds-empty-title">Historique</div><div class="ds-empty-msg">À venir</div></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="careb-footer">
    <div>
      <span class="careb-foot-dot"></span> Données chiffrées AES-256-GCM
      <span class="sep">·</span>
      <span class="careb-foot-dot"></span> Référentiel Fegems S2 <?= date('Y') ?>
    </div>
    <div>
      Dernière modification · <?= date('d.m.Y') ?> par M. Chanel
    </div>
  </div>
</div>

<style>
/* ─── Variables locales (si theme-care pas actif, fallback) ─── */
.careb-crumb {
  padding: 14px 28px;
  font-size: 12.5px;
  color: var(--muted, #6b8783);
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface, #fff);
  border-bottom: 1px solid var(--line, #e3ebe8);
}
.careb-crumb a { color: var(--muted, #6b8783); text-decoration: none; }
.careb-crumb a:hover { color: var(--teal-700, #164a42); }
.careb-crumb i { font-size: 9px; opacity: .5; }

/* ─── HERO ─── */
.careb-hero {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 50%, #2d8074 100%);
  color: #fff;
  border-radius: var(--r-lg, 16px);
  padding: 26px 30px;
  margin: 24px 0;
}
.careb-hero-row { display: flex; gap: 22px; align-items: flex-start; flex-wrap: wrap; }
.careb-hero-avatar {
  width: 88px; height: 88px; border-radius: 16px;
  background: rgba(255,255,255,.13);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display, Fraunces, serif);
  font-size: 30px; font-weight: 500; color: #fff;
  flex-shrink: 0;
}
.careb-hero-id { flex: 1; min-width: 280px; }
.careb-hero-eyebrow { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #a8e6c9; font-weight: 600; }
.careb-hero h1 { font-family: var(--font-display, Fraunces, serif); font-size: 32px; font-weight: 500; color: #fff; margin: 4px 0 6px; letter-spacing: -.02em; line-height: 1.1; }
.careb-hero-meta { font-size: 14px; color: #cfe0db; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.careb-hero-meta .sep { opacity: .4; }
.careb-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }

.careb-btn-light, .careb-btn-light-sm {
  background: rgba(255,255,255,.95); color: #164a42; border: 0;
  padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 500;
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
  transition: all .15s ease;
}
.careb-btn-light:hover { background: #fff; color: #0d2a26; }
.careb-btn-light-sm { padding: 6px 12px; font-size: 12px; background: #fff; color: var(--ink-2, #324e4a); border: 1px solid var(--line, #e3ebe8); }
.careb-btn-light-sm:hover { border-color: var(--teal-300, #7ab5ab); color: var(--teal-700, #164a42); }
.careb-btn-primary, .careb-btn-primary-sm {
  background: #fff; color: #164a42; border: 0;
  padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
  transition: all .15s ease;
}
.careb-btn-primary { background: linear-gradient(135deg, #3da896, #7dd3a8); color: #0d2a26; }
.careb-btn-primary:hover { background: linear-gradient(135deg, #5cad9b, #a8e6c9); }
.careb-btn-primary-sm { padding: 6px 12px; font-size: 12px; background: var(--teal-600, #1f6359); color: #fff; }
.careb-btn-primary-sm:hover { background: var(--teal-700, #164a42); }
.careb-btn-soft {
  background: var(--teal-50, #ecf5f3); color: var(--teal-700, #164a42); border: 1px solid var(--teal-100, #d2e7e2);
  padding: 7px 13px; border-radius: 8px; font-size: 12.5px; font-weight: 500;
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
}
.careb-btn-soft:hover { background: var(--teal-100, #d2e7e2); }
.careb-btn-icon {
  background: transparent; border: 0; color: var(--muted, #6b8783); cursor: pointer;
  padding: 6px 8px; border-radius: 6px;
}
.careb-btn-icon:hover { background: var(--surface-3, #f3f6f5); color: var(--ink-2, #324e4a); }

.careb-hero-grid {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 18px;
  margin-top: 22px; padding-top: 20px;
  border-top: 1px solid rgba(255,255,255,.12);
}
.careb-hero-cell {}
.careb-hero-lbl { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: #a8c4be; font-weight: 600; }
.careb-hero-val { font-family: var(--font-display, Fraunces, serif); font-size: 18px; font-weight: 500; color: #fff; margin-top: 4px; }
.careb-hero-sub { font-size: 11.5px; color: #a8c4be; font-family: var(--font-body, Outfit, sans-serif); font-weight: 400; }

/* ─── KPI ─── */
.careb-kpi-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
  margin-bottom: 24px;
}
.careb-kpi {
  background: var(--surface, #fff); border: 1px solid var(--line, #e3ebe8);
  border-radius: var(--r-lg, 16px); padding: 20px 22px;
}
.careb-kpi-lbl { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted, #6b8783); font-weight: 600; margin-bottom: 8px; }
.careb-kpi-val { font-family: var(--font-display, Fraunces, serif); font-size: 36px; font-weight: 500; color: var(--ink, #0d2a26); line-height: 1; letter-spacing: -.02em; }
.careb-kpi-val--ok { color: var(--ok, #3d8b6b); }
.careb-kpi-val--warn { color: var(--warn, #c97a2a); }
.careb-kpi-val--danger { color: var(--danger, #b8443a); }
.careb-kpi-unit { font-family: var(--font-body, Outfit, sans-serif); font-size: 16px; color: var(--muted, #6b8783); font-weight: 400; margin-left: 4px; }
.careb-kpi-sub { font-size: 12.5px; color: var(--muted, #6b8783); margin-top: 8px; }

/* ─── Tabs ─── */
.careb-tabs {
  list-style: none; padding: 0; margin: 0 0 20px;
  display: flex; gap: 4px; border-bottom: 1px solid var(--line, #e3ebe8);
}
.careb-tabs li {
  padding: 12px 18px; font-size: 13.5px; font-weight: 500; color: var(--muted, #6b8783);
  cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
  display: inline-flex; align-items: center; gap: 8px; transition: all .12s ease;
}
.careb-tabs li:hover { color: var(--ink-2, #324e4a); }
.careb-tabs li.active { color: var(--teal-700, #164a42); border-bottom-color: var(--teal-600, #1f6359); }
.careb-tab-count {
  font-family: var(--font-mono, monospace); font-size: 10.5px;
  background: var(--surface-3, #f3f6f5); color: var(--ink-2, #324e4a);
  padding: 1px 7px; border-radius: 99px; font-weight: 600;
}
.careb-tabs li.active .careb-tab-count { background: var(--teal-600, #1f6359); color: #fff; }
.careb-tab-pane[hidden] { display: none; }

/* ─── Cards section ─── */
.careb-card {
  background: var(--surface, #fff); border: 1px solid var(--line, #e3ebe8);
  border-radius: var(--r-md, 12px); margin-bottom: 16px; overflow: hidden;
}
.careb-card-head {
  padding: 18px 24px; border-bottom: 1px solid var(--line, #e3ebe8);
  display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
}
.careb-card-title { font-family: var(--font-display, Fraunces, serif); font-size: 19px; font-weight: 600; color: var(--ink, #0d2a26); letter-spacing: -.01em; }
.careb-card-sub { font-size: 12.5px; color: var(--muted, #6b8783); margin-top: 4px; }
.careb-card-body { padding: 22px 24px; }
.careb-card-padded { padding: 24px; }

/* ─── Radar ─── */
.careb-radar-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center; }
.careb-radar-svg svg { width: 100%; max-width: 420px; display: block; margin: 0 auto; }
.careb-radar-side { padding: 0 8px; }
.careb-radar-line { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
.careb-radar-dot { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }
.careb-radar-dot--solid { background: var(--teal-500, #2d8074); }
.careb-radar-dot--dashed { background: transparent; border: 1.5px dashed #9cb8a8; }
.careb-radar-lbl { font-weight: 600; color: var(--ink, #0d2a26); flex: 1; }
.careb-radar-sub { font-size: 12px; color: var(--muted, #6b8783); flex: 1; padding-left: 24px; }
.careb-radar-val { font-family: var(--font-display, Fraunces, serif); font-size: 18px; font-weight: 500; color: var(--ink, #0d2a26); }
.careb-radar-progression { color: var(--ok, #3d8b6b); font-weight: 600; font-size: 13px; }
.careb-radar-divider { height: 1px; background: var(--line, #e3ebe8); margin: 8px 0; }
.careb-radar-synth-text { font-size: 13px; line-height: 1.55; color: var(--ink-2, #324e4a); }
.careb-radar-footer {
  display: grid; grid-template-columns: repeat(4, 1fr);
  border-top: 1px solid var(--line, #e3ebe8);
  background: var(--surface-2, #fafbfa);
  padding: 18px 24px; text-align: center;
}
.careb-radar-stat-num { font-family: var(--font-display, Fraunces, serif); font-size: 28px; font-weight: 600; line-height: 1; }
.careb-radar-stat-lbl { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted, #6b8783); margin-top: 6px; font-weight: 600; }

/* ─── Thématiques ─── */
.careb-prio-section { padding: 0; }
.careb-prio-header {
  padding: 12px 24px; background: var(--surface-2, #fafbfa);
  border-bottom: 1px solid var(--line, #e3ebe8);
  display: flex; align-items: center; gap: 8px;
  font-size: 11px; letter-spacing: .08em; color: var(--ink-2, #324e4a);
}
.careb-prio-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.careb-prio-dot--priorite-haute { background: var(--danger, #b8443a); }
.careb-prio-dot--priorite-moyenne { background: var(--warn, #c97a2a); }
.careb-prio-dot--priorite-basse { background: var(--info, #3a6a8a); }
.careb-prio-dot--priorite-ajour { background: var(--ok, #3d8b6b); }
.careb-prio-count {
  margin-left: auto; font-family: var(--font-mono, monospace);
  background: var(--surface-3, #f3f6f5); padding: 1px 8px; border-radius: 99px;
  font-size: 11px; font-weight: 700; letter-spacing: 0;
  text-transform: none; color: var(--ink-2, #324e4a);
}

.careb-them-row { padding: 18px 24px; border-bottom: 1px solid var(--line, #e3ebe8); }
.careb-them-row:last-child { border-bottom: 0; }
.careb-them-head {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 6px;
}
.careb-them-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.careb-them-title strong { font-size: 14.5px; color: var(--ink, #0d2a26); }
.careb-tag-tag, .careb-tag-expired, .careb-tag-soon, .careb-tag-ok {
  font-size: 9.5px; letter-spacing: .08em; padding: 2px 7px; border-radius: 4px; font-weight: 700; text-transform: uppercase;
}
.careb-tag-tag { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); }
.careb-tag-expired { background: var(--danger-bg, #f7e3e0); color: var(--danger, #b8443a); }
.careb-tag-soon { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); }
.careb-tag-ok { background: var(--ok-bg, #e3f0ea); color: var(--ok, #3d8b6b); }

.careb-them-ecart { text-align: right; flex-shrink: 0; }
.careb-them-ecart[data-color="priorite-haute"] { color: var(--danger, #b8443a); font-weight: 600; }
.careb-them-ecart[data-color="priorite-moyenne"] { color: var(--warn, #c97a2a); font-weight: 600; }
.careb-them-ecart[data-color="priorite-basse"] { color: var(--info, #3a6a8a); font-weight: 600; }
.careb-them-ecart-sub { display: block; font-size: 9.5px; letter-spacing: .08em; font-weight: 600; opacity: .7; }
.careb-them-ecart--ok { color: var(--ok, #3d8b6b); font-weight: 600; }

.careb-them-meta { font-size: 12px; color: var(--muted, #6b8783); margin-bottom: 12px; }

.careb-them-track {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 2px; height: 38px; border-radius: 6px; overflow: hidden;
  margin-bottom: 12px; background: var(--line, #e3ebe8);
}
.careb-them-seg {
  display: flex; align-items: center; padding: 0 12px;
  font-size: 9.5px; letter-spacing: .08em; font-weight: 700; text-transform: uppercase;
  position: relative; transition: all .2s ease;
}
.careb-them-seg-lbl { color: rgba(255,255,255,.85); }
.careb-them-seg--empty { background: var(--surface-3, #f3f6f5); }
.careb-them-seg--empty .careb-them-seg-lbl { color: var(--muted, #6b8783); }
.careb-them-seg--ok { background: linear-gradient(90deg, #2d8074, #1f6359); }
.careb-them-seg--warn { background: linear-gradient(90deg, #c97a2a, #a86322); }
.careb-them-seg--bad { background: linear-gradient(90deg, #b8443a, #8e342c); }
.careb-them-seg--req::after {
  content: ''; position: absolute; right: -1px; top: -2px; bottom: -2px;
  width: 3px; background: var(--ink, #0d2a26); border-radius: 2px;
}

.careb-them-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ─── Info grid ─── */
.careb-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px 32px; }
.careb-info-lbl { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted, #6b8783); font-weight: 600; margin-bottom: 4px; }
.careb-info-val { font-size: 14px; color: var(--ink, #0d2a26); font-weight: 500; }

/* ─── Formations ─── */
.careb-form-row {
  display: grid; grid-template-columns: 1fr 240px 130px;
  align-items: center; gap: 18px;
  padding: 14px 24px; border-bottom: 1px solid var(--line, #e3ebe8);
}
.careb-form-row:last-child { border-bottom: 0; }
.careb-form-title { font-size: 14px; font-weight: 600; color: var(--ink, #0d2a26); margin-bottom: 3px; }
.careb-form-meta { font-size: 12px; color: var(--muted, #6b8783); display: flex; gap: 8px; align-items: center; }
.careb-form-meta .sep { opacity: .4; }
.careb-form-bar {
  height: 6px; background: var(--line, #e3ebe8); border-radius: 99px; overflow: hidden;
}
.careb-form-bar-fill {
  height: 100%; background: linear-gradient(90deg, var(--teal-500, #2d8074), #5cad9b);
}
.careb-form-bar-lbl { font-size: 11.5px; color: var(--muted, #6b8783); margin-top: 4px; text-align: right; }
.careb-form-status { text-align: right; }

.careb-status {
  display: inline-block; padding: 3px 9px; border-radius: 5px;
  font-size: 10px; letter-spacing: .08em; font-weight: 700; text-transform: uppercase;
}
.careb-status--inscrite { background: var(--info-bg, #e2ecf2); color: var(--info, #3a6a8a); }
.careb-status--encours  { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); }
.careb-status--realisee { background: var(--ok-bg, #e3f0ea); color: var(--ok, #3d8b6b); }
.careb-status--present  { background: var(--ok-bg, #e3f0ea); color: var(--ok, #3d8b6b); }
.careb-status--absent   { background: var(--danger-bg, #f7e3e0); color: var(--danger, #b8443a); }

/* ─── Objectifs ─── */
.careb-obj-row {
  display: flex; gap: 14px; padding: 14px 24px;
  border-bottom: 1px solid var(--line, #e3ebe8); align-items: flex-start;
}
.careb-obj-row:last-child { border-bottom: 0; }
.careb-obj-icon {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 12px; flex-shrink: 0;
}
.careb-obj-icon--ok    { background: var(--ok, #3d8b6b); color: #fff; }
.careb-obj-icon--cours { background: var(--warn-bg, #fbf0e1); color: var(--warn, #c97a2a); border: 2px solid var(--warn, #c97a2a); }
.careb-obj-icon--todo  { background: var(--surface-3, #f3f6f5); color: var(--muted, #6b8783); border: 2px solid var(--line-2, #d4ddda); }
.careb-obj-icon--paused { background: var(--info-bg, #e2ecf2); color: var(--info, #3a6a8a); }
.careb-obj-icon--cancel { background: var(--danger-bg, #f7e3e0); color: var(--danger, #b8443a); }
.careb-obj-tri { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted, #6b8783); font-weight: 600; }
.careb-obj-titre { font-size: 14px; font-weight: 600; color: var(--ink, #0d2a26); margin: 2px 0 4px; }
.careb-obj-desc { font-size: 12.5px; color: var(--muted, #6b8783); }

/* ─── Quote ─── */
.careb-quote {
  background: var(--warn-bg, #fbf0e1);
  border-left: 3px solid var(--warn, #c97a2a);
  padding: 16px 20px; margin: 20px 0;
  border-radius: 8px; font-style: italic;
}
.careb-quote-text { font-size: 14px; color: var(--ink, #0d2a26); line-height: 1.55; }
.careb-quote-author { font-size: 12px; color: var(--muted, #6b8783); margin-top: 6px; font-style: normal; }

/* ─── Footer ─── */
.careb-footer {
  margin-top: 24px; padding: 18px 0;
  display: flex; justify-content: space-between; align-items: center;
  font-size: 11.5px; color: var(--muted, #6b8783);
  border-top: 1px solid var(--line, #e3ebe8);
}
.careb-foot-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--ok, #3d8b6b); margin-right: 6px; vertical-align: middle; }
.careb-footer .sep { margin: 0 8px; opacity: .4; }

/* ─── Responsive ─── */
@media (max-width: 991px) {
  .careb-hero-grid { grid-template-columns: repeat(2, 1fr); }
  .careb-kpi-row { grid-template-columns: repeat(2, 1fr); }
  .careb-radar-row { grid-template-columns: 1fr; }
  .careb-info-grid { grid-template-columns: 1fr; }
  .careb-form-row { grid-template-columns: 1fr; gap: 8px; }
}
</style>

<script<?= nonce() ?>>
// Tabs switch
document.querySelectorAll('.careb-tabs li').forEach(li => {
  li.addEventListener('click', () => {
    const tab = li.dataset.tab;
    document.querySelectorAll('.careb-tabs li').forEach(l => l.classList.toggle('active', l === li));
    document.querySelectorAll('.careb-tab-pane').forEach(p => {
      p.hidden = p.dataset.pane !== tab;
    });
  });
});

// Boutons hero (placeholder)
document.getElementById('exportPdfBtn')?.addEventListener('click', () => {
  showToast('Export PDF en cours de développement', 'info');
});
document.getElementById('lancerEntretienBtn')?.addEventListener('click', () => {
  const uid = document.getElementById('lancerEntretienBtn').dataset.uid;
  location.href = '?page=rh-entretiens&statut=planifie';
});
document.getElementById('addThematiqueBtn')?.addEventListener('click', () => {
  showToast('Ajout de thématique en cours de développement', 'info');
});

// Re-évaluer / Inscrire à formation : placeholders
document.querySelectorAll('[data-reeval]').forEach(b => {
  b.addEventListener('click', () => showToast('Modal re-évaluation à venir', 'info'));
});
document.querySelectorAll('[data-inscrire-form]').forEach(b => {
  b.addEventListener('click', () => location.href = '?page=rh-formations-fegems');
});
document.querySelectorAll('[data-renouvellement]').forEach(b => {
  b.addEventListener('click', () => showToast('Lancer renouvellement à venir', 'info'));
});
</script>
