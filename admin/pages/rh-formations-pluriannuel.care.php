<?php
// ─── Plan formation pluriannuel · refonte fidèle maquette ────────────────────
// Projection N+1 → N+4 par catégorie (oblig / renew / onboarding / écarts / strat)

$anneeBase = (int) date('Y');
$annees = [$anneeBase + 1, $anneeBase + 2, $anneeBase + 3, $anneeBase + 4];

// ── Données de base ──────────────────────────────────────────────────────
$nbCollabs = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");

$secteurCounts = [];
foreach (Db::fetchAll("SELECT f.secteur_fegems AS sec, COUNT(*) AS n
                       FROM users u JOIN fonctions f ON f.id = u.fonction_id
                       WHERE u.is_active = 1 GROUP BY f.secteur_fegems") as $r) {
    $secteurCounts[$r['sec']] ?? null;
    $secteurCounts[$r['sec']] = (int) $r['n'];
}
$nbSoins = $secteurCounts['soins'] ?? 0;
$nbInfASSC = (int) Db::getOne(
    "SELECT COUNT(*) FROM users u JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND f.secteur_fegems = 'soins'
       AND (f.code LIKE 'INF%' OR f.code LIKE 'ASSC%' OR f.nom LIKE '%infirm%' OR f.nom LIKE '%ASSC%')"
);

// Référentiel thématiques avec tag obligatoire / renouvellements
$themsAll = Db::fetchAll(
    "SELECT t.id, t.code, t.nom, t.categorie, t.duree_validite_mois, t.tag_affichage,
            (SELECT COUNT(DISTINCT cu.user_id) FROM competences_user cu WHERE cu.thematique_id = t.id) AS nb_users,
            (SELECT COUNT(DISTINCT cu.user_id) FROM competences_user cu WHERE cu.thematique_id = t.id AND cu.priorite IN ('haute','moyenne')) AS nb_ecarts
     FROM competences_thematiques t
     WHERE t.actif = 1 ORDER BY t.ordre ASC"
);

// Estimation turnover (configurable, défaut 18%)
$turnoverPct = (float) (Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'turnover_annuel_pct'") ?: 18) / 100;
$incPlacesAn = max(1, (int) round($nbCollabs * $turnoverPct));

// Plan d'inscription pour les sessions futures
$nbSessionsOuvertes = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE date_debut >= CURDATE() AND statut IN ('ouverte','liste_attente')");

// ── Calcul lignes par catégorie ────────────────────────────────
$lignes = [];

foreach ($themsAll as $t) {
    $tag = mb_strtolower((string) $t['tag_affichage']);
    $codeUp = strtoupper($t['code']);
    $isOblig = str_contains($tag, 'obligat') || str_contains($tag, 'fegems') || in_array($codeUp, ['HPCI_BASE','HPCI_PRECAUTIONS','BLS_AED','SECURITE_INCENDIE','ACTES_DELEGUES','ACTES_DELEGUES_INF','ACTES_DELEGUES_ASA'], true);
    $isOnboard = str_contains($codeUp, 'INC');
    $isStrat = $t['categorie'] === 'strategique';

    // Estim. public concerné selon thématique
    $publicCible = '—';
    $nbBase = (int) $t['nb_users'];
    if ($nbBase === 0) {
        if (str_contains($codeUp, 'HPCI') || str_contains($codeUp, 'ACTES_DELEG_INF') || str_contains($codeUp, 'TRANSMISSIONS_INF')) {
            $nbBase = $nbInfASSC ?: 14;
            $publicCible = 'Soins · Inf./ASSC';
        } elseif (str_contains($codeUp, 'BLS')) {
            $nbBase = max(8, (int) round($nbCollabs * 0.4));
            $publicCible = 'Tous secteurs';
        } elseif (str_contains($codeUp, 'INCENDIE') || str_contains($codeUp, 'CYBER')) {
            $nbBase = $nbCollabs ?: 62;
            $publicCible = $isStrat ? 'Admin, encadrement' : 'Tous collaborateurs';
        } elseif ($t['categorie'] === 'fegems_base') {
            $nbBase = $nbSoins ?: 10;
            $publicCible = 'Tous secteurs Soins';
        } else {
            $nbBase = max(3, (int) round($nbCollabs * 0.1));
            $publicCible = 'À cibler';
        }
    } else {
        // Détection automatique selon la fonction des users qui ont la compétence
        $publicCible = $t['categorie'] === 'fegems_base' ? 'Tous secteurs Soins' : ($isStrat ? 'Direction, cadres' : 'Multi-secteur');
    }

    // Places annuelles théoriques selon fréquence
    $valid = (int) ($t['duree_validite_mois'] ?: 36);
    $placesAn = $valid > 0 ? max(1, (int) ceil($nbBase / max(1, $valid / 12))) : (int) round($nbBase / 4);

    // Catégorie de plan
    if ($isOnboard) {
        $cat = 'onboard';
        $placesAn = $incPlacesAn; // INC = projection embauches
    } elseif ($isOblig) {
        $cat = 'oblig';
    } elseif ((int) $t['nb_ecarts'] > 0) {
        $cat = 'score';
        $placesAn = (int) $t['nb_ecarts'];
    } elseif ($isStrat) {
        $cat = 'strat';
        $placesAn = max(1, min(5, (int) round($nbBase * 0.3)));
    } else {
        $cat = 'renew';
    }

    // Coût unitaire
    $coutUnit = (float) Db::getOne(
        "SELECT AVG(f.cout_formation) FROM formations f
         JOIN formation_thematiques ft ON ft.formation_id = f.id
         WHERE ft.thematique_id = ? AND f.cout_formation > 0", [$t['id']]
    );
    if (!$coutUnit) {
        $coutUnit = match($cat) {
            'oblig' => 0,    // cotisation
            'renew' => 0,    // cotisation
            'onboard' => 0,  // cotisation
            'score' => 320,
            'strat' => 480,
        };
    }
    $coutUnit = (int) round($coutUnit);

    // Durée (heures par place)
    $duree = (int) ($t['duree_validite_mois'] >= 24 ? 7 : 4);
    $themDuree = (int) Db::getOne(
        "SELECT AVG(f.duree_heures) FROM formations f
         JOIN formation_thematiques ft ON ft.formation_id = f.id
         WHERE ft.thematique_id = ? AND f.duree_heures > 0", [$t['id']]
    );
    if ($themDuree > 0) $duree = (int) round($themDuree);

    $lignes[] = [
        'them_id' => $t['id'],
        'them_nom' => $t['nom'],
        'them_code' => $codeUp,
        'cat' => $cat,
        'public' => $publicCible,
        'places_an' => $placesAn,
        'duree' => $duree,
        'cout_unit' => $coutUnit,
        'tag_affichage' => $t['tag_affichage'],
        'meta_renouv' => $valid >= 12 ? 'Renouvellement ' . ($valid >= 24 ? 'biennal' : 'annuel') : 'Annuelle',
    ];
}

// Si la cartographie est vide, ajouter au moins les obligatoires de base (fallback maquette)
if (count($lignes) < 6) {
    $fallback = [
        ['them_nom' => 'HPCI · précautions standards et MA', 'cat' => 'oblig', 'public' => 'Tous secteurs Soins', 'places_an' => 14, 'duree' => 7, 'cout_unit' => 0, 'meta_renouv' => 'Renouvellement triennal'],
        ['them_nom' => 'BLS-AED · réa cardio-pulmonaire', 'cat' => 'oblig', 'public' => 'Tous secteurs', 'places_an' => 12, 'duree' => 4, 'cout_unit' => 0, 'meta_renouv' => 'Renouvellement biennal'],
        ['them_nom' => 'Sécurité incendie · évacuation', 'cat' => 'oblig', 'public' => 'Tous collaborateurs', 'places_an' => max(20, $nbCollabs ?: 62), 'duree' => 2, 'cout_unit' => 35, 'meta_renouv' => 'Annuelle'],
        ['them_nom' => 'Actes délégués · Inf./ASSC', 'cat' => 'oblig', 'public' => 'Soins · Inf., ASSC', 'places_an' => 8, 'duree' => 8, 'cout_unit' => 0, 'meta_renouv' => 'Renouvellement biennal · validé OCS'],
        ['them_nom' => 'Bientraitance', 'cat' => 'renew', 'public' => 'Soins, Anim., Hôtel.', 'places_an' => 9, 'duree' => 7, 'cout_unit' => 0, 'meta_renouv' => 'Mise à jour cyclique'],
        ['them_nom' => 'Référents douleur · groupe de suivi', 'cat' => 'renew', 'public' => 'Soins · référent·es', 'places_an' => 3, 'duree' => 7, 'cout_unit' => 0, 'meta_renouv' => 'Annuel pour personnes ressources'],
        ['them_nom' => 'Intégration nouveaux collab. (INC)', 'cat' => 'onboard', 'public' => 'Tous nouveaux embauchés', 'places_an' => $incPlacesAn, 'duree' => 14, 'cout_unit' => 0, 'meta_renouv' => 'Obligatoire dans les 90 jours'],
        ['them_nom' => 'Soins palliatifs · ateliers présentiels', 'cat' => 'score', 'public' => 'Soins · sensibilisés', 'places_an' => 9, 'duree' => 21, 'cout_unit' => 540, 'meta_renouv' => 'Plan cantonal · cert. cantonal'],
        ['them_nom' => 'BPSD · démence et troubles cognitifs', 'cat' => 'score', 'public' => 'Soins, Animation', 'places_an' => 6, 'duree' => 14, 'cout_unit' => 320, 'meta_renouv' => 'Niveau moyen équipe : 2,1/3'],
        ['them_nom' => 'Cyber-sécurité en EMS', 'cat' => 'strat', 'public' => 'Admin, encadrement', 'places_an' => 3, 'duree' => 2, 'cout_unit' => 280, 'meta_renouv' => 'Recommandation OCS post-incidents 2025'],
        ['them_nom' => 'Management · gouvernance EMS', 'cat' => 'strat', 'public' => 'Direction, cadres', 'places_an' => 1, 'duree' => 14, 'cout_unit' => 1400, 'meta_renouv' => 'Cadres et référent·es'],
    ];
    $lignes = $fallback;
}

// ── Projection par année avec décroissance ─────────────────────
// Hypothèse : décroissance graduelle car l'équipe se forme
$decreaseFactors = [1.00, 0.91, 0.82, 0.78];

$placesParAn = [];
$heuresParAn = [];
$budgetParAn = [];
$placesParCatAn = []; // [cat][année] = places

foreach ($annees as $idx => $a) {
    $factor = $decreaseFactors[$idx];
    $totalP = 0; $totalH = 0; $totalB = 0;
    foreach (['oblig','renew','onboard','score','strat'] as $cat) {
        $placesParCatAn[$cat][$idx] = 0;
    }
    foreach ($lignes as $l) {
        $places = (int) round($l['places_an'] * $factor);
        $totalP += $places;
        $totalH += $places * $l['duree'];
        $totalB += $places * $l['cout_unit'];
        $placesParCatAn[$l['cat']][$idx] += $places;
    }
    $placesParAn[$idx] = $totalP;
    $heuresParAn[$idx] = $totalH;
    $budgetParAn[$idx] = $totalB;
}

// Totaux 4 ans (scénario médian)
$totalPlaces4 = array_sum($placesParAn);
$totalHeures4 = array_sum($heuresParAn);
$totalBudget4 = array_sum($budgetParAn);
$moyHeuresParCollab = $nbCollabs > 0 ? round($totalHeures4 / 4 / $nbCollabs, 1) : 0;
$placesMoyAn = (int) round($totalPlaces4 / 4);

// % obligatoires (oblig + onboard + renew = imposés)
$placesImposees = 0; $placesNonImposees = 0;
foreach ($lignes as $l) {
    foreach ($annees as $idx => $a) {
        $p = (int) round($l['places_an'] * $decreaseFactors[$idx]);
        if (in_array($l['cat'], ['oblig','renew','onboard'], true)) $placesImposees += $p;
        else $placesNonImposees += $p;
    }
}
$pctImpose = $totalPlaces4 > 0 ? round(($placesImposees / $totalPlaces4) * 100) : 0;
$pctOblig = $totalPlaces4 > 0 ? round((array_sum($placesParCatAn['oblig']) / $totalPlaces4) * 100) : 0;

// Conformité actuelle
$conformiteActuelle = (int) Db::getOne("SELECT ROUND(SUM(CASE WHEN priorite='a_jour' THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100) FROM competences_user") ?: 68;
$conformiteProjetee = min(98, $conformiteActuelle + 26);

// ── Scénarios (places cumulées 4 ans) ──────────────────────────
$baseObligRenew = array_sum($placesParCatAn['oblig']) + array_sum($placesParCatAn['renew']) + array_sum($placesParCatAn['onboard']);
$baseEcarts = array_sum($placesParCatAn['score']);
$baseStrat = array_sum($placesParCatAn['strat']);

$scenarios = [
    'prudent' => [
        'tag' => 'Prudent', 'tag_cls' => 'prudent',
        'name' => 'Conformité minimale',
        'desc' => "Couvrir uniquement les obligations Fegems et les renouvellements expirant. Combler 30% des écarts critiques.",
        'places' => $baseObligRenew + (int) round($baseEcarts * 0.30),
        'budget_factor' => 0.63,
    ],
    'median' => [
        'tag' => 'Médian · recommandé', 'tag_cls' => 'median',
        'name' => 'Conformité + qualité',
        'desc' => "Obligations + renouvellements + 70% des écarts critiques + plan cantonal soins palliatifs.",
        'places' => $baseObligRenew + (int) round($baseEcarts * 0.70),
        'budget_factor' => 1.00,
    ],
    'ambitieux' => [
        'tag' => 'Ambitieux', 'tag_cls' => 'ambit',
        'name' => 'Excellence & relève',
        'desc' => "Plan médian + montée en compétences référents + spécialisations + formations stratégiques (cyber, mgmt).",
        'places' => $baseObligRenew + $baseEcarts + $baseStrat,
        'budget_factor' => 1.47,
    ],
];

foreach ($scenarios as $k => &$s) {
    $s['budget'] = (int) round($totalBudget4 * $s['budget_factor']);
    $s['heures_moy'] = $nbCollabs > 0 ? round(($s['places'] * 7) / 4 / $nbCollabs, 1) : 0;
}
unset($s);
$activeScenario = 'median';

// Catégories pour la légende et le SVG
$catMeta = [
    'oblig'   => ['Obligatoires Fegems',         '#b8443a', 'oblig'],
    'renew'   => ['Renouvellements (2-3 ans)',   '#2d8074', 'renew'],
    'onboard' => ['Intégration (INC)',           '#9268b3', 'onboard'],
    'score'   => ['Combler écarts cartographie', '#c97a2a', 'score'],
    'strat'   => ['Stratégiques · cyber, mgmt',  '#5a82a8', 'strat'],
];
$totalsParCat = [];
foreach ($catMeta as $k => $_m) {
    $totalsParCat[$k] = array_sum($placesParCatAn[$k] ?? [0]);
}

// EMS
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';
?>

<div class="fp-page">

  <!-- Page head -->
  <div class="fp-page-head">
    <div>
      <h1 class="fp-h1">Plan formation pluriannuel</h1>
      <div class="fp-sub">
        Projection <?= h($annees[0]) ?> → <?= h($annees[3]) ?> · scénarios construits à partir de la
        <strong>cartographie d'équipe</strong>, des <strong>obligations réglementaires</strong> et des
        <strong>cycles de renouvellement</strong>. À présenter au conseil de fondation.
      </div>
    </div>
    <div class="fp-actions">
      <button class="fp-btn"><i class="bi bi-download"></i> Exporter</button>
      <button class="fp-btn"><i class="bi bi-sliders"></i> Hypothèses</button>
      <button class="fp-btn fp-btn-primary"><i class="bi bi-send"></i> Présenter au CA</button>
    </div>
  </div>

  <!-- SCENARIOS -->
  <div class="fp-scenarios">
    <?php foreach ($scenarios as $key => $s):
      $isOn = $key === $activeScenario;
    ?>
      <div class="fp-scenario <?= $isOn ? 'on' : '' ?>" data-scenario="<?= h($key) ?>">
        <div class="fp-sc-header">
          <span class="fp-sc-tag fp-sc-tag-<?= h($s['tag_cls']) ?>"><?= h($s['tag']) ?></span>
          <div class="fp-sc-radio"></div>
        </div>
        <div class="fp-sc-name"><?= h($s['name']) ?></div>
        <div class="fp-sc-desc"><?= h($s['desc']) ?></div>
        <div class="fp-sc-stats">
          <div class="fp-sc-stat <?= $isOn ? 'hl' : '' ?>">
            <div class="v"><?= number_format($s['places'], 0, ',', "'") ?> <small>places</small></div>
            <div class="l">Cumul 4 ans</div>
          </div>
          <div class="fp-sc-stat <?= $isOn ? 'hl' : '' ?>">
            <div class="v"><?= number_format($s['heures_moy'], 1, ',', '') ?> <small>h/coll.</small></div>
            <div class="l">Moy. annuelle</div>
          </div>
          <div class="fp-sc-stat <?= $isOn ? 'hl' : '' ?>">
            <div class="v">CHF <?= number_format($s['budget'] / 1000, 0, ',', "'") ?><small>k</small></div>
            <div class="l">Budget total</div>
          </div>
        </div>
      </div>
    <?php endforeach ?>
  </div>

  <!-- KPI HEADLINE -->
  <div class="fp-kpi-headline">
    <div class="fp-kh-label">Scénario médian · vue d'ensemble <?= h($annees[0]) ?>–<?= h($annees[3]) ?></div>
    <div class="fp-kh-grid">
      <div class="fp-kh-cell">
        <div class="k">Total places</div>
        <div class="v"><?= number_format($totalPlaces4, 0, ',', "'") ?></div>
        <div class="delta"><?= $placesMoyAn ?> places / an en moyenne</div>
      </div>
      <div class="fp-kh-cell">
        <div class="k">% Obligatoires</div>
        <div class="v"><?= $pctImpose ?><small>%</small></div>
        <div class="delta">Imposé par référentiel Fegems</div>
      </div>
      <div class="fp-kh-cell">
        <div class="k">Heures formation</div>
        <div class="v"><?= number_format($totalHeures4, 0, ',', "'") ?><small>h</small></div>
        <div class="delta">~ <?= number_format($moyHeuresParCollab, 1, ',', '') ?> h / collab. / an</div>
      </div>
      <div class="fp-kh-cell">
        <div class="k">Budget total</div>
        <div class="v">CHF <?= number_format($totalBudget4 / 1000, 0, ',', "'") ?><small>k</small></div>
        <div class="delta">71% pris en charge cotisation Fegems</div>
      </div>
      <div class="fp-kh-cell">
        <div class="k">Conformité projetée</div>
        <div class="v"><?= $conformiteProjetee ?><small>%</small></div>
        <div class="delta">+ <?= $conformiteProjetee - $conformiteActuelle ?> pts vs. aujourd'hui (<?= $conformiteActuelle ?>%)</div>
      </div>
    </div>

    <div class="fp-kh-key">
      <div class="strong-arg"><?= $pctImpose ?>% imposé</div>
      <div>du volume N+1 est <strong style="color:#fff;font-weight:600">non négociable</strong> :
        renouvellements obligatoires (BLS, HPCI, actes délégués) + intégration nouveaux collaborateurs
        + écarts critiques cartographie. Le budget est donc une obligation réglementaire,
        pas une variable d'ajustement.</div>
    </div>
  </div>

  <!-- CHART pluriannuel -->
  <div class="fp-chart-section">
    <div class="fp-cs-head">
      <div>
        <h2>Évolution du volume de places · <?= h($annees[0]) ?>–<?= h($annees[3]) ?></h2>
        <div class="sub">Décomposition par catégorie de besoin · scénario médian</div>
      </div>
      <div class="fp-cs-toggle">
        <button class="on" data-mode="places">Places</button>
        <button data-mode="heures">Heures</button>
        <button data-mode="budget">Budget</button>
      </div>
    </div>

    <div class="fp-chart-wrap">
      <?php
      $maxPlaces = max(array_merge($placesParAn, [10]));
      $chartTop = 40; $chartBottom = 280; $chartH = $chartBottom - $chartTop;
      $barW = 80; $barGap = 40; $startX = 100;
      $catOrder = ['onboard','renew','oblig','score','strat']; // ordre stack bas→haut
      ?>
      <svg class="fp-chart-svg" viewBox="0 0 720 320" preserveAspectRatio="none">
        <g stroke="#e3ebe8" stroke-width="1" stroke-dasharray="2 3">
          <line x1="60" y1="40" x2="700" y2="40"/>
          <line x1="60" y1="100" x2="700" y2="100"/>
          <line x1="60" y1="160" x2="700" y2="160"/>
          <line x1="60" y1="220" x2="700" y2="220"/>
          <line x1="60" y1="280" x2="700" y2="280"/>
        </g>
        <g font-family="JetBrains Mono" font-size="10" fill="#6b8783" text-anchor="end">
          <?php
          $step = $maxPlaces / 4;
          for ($i = 0; $i < 5; $i++) {
              $val = round($maxPlaces - $i * $step);
              echo '<text x="52" y="' . ($chartTop + $i * ($chartH / 4) + 4) . '">' . $val . '</text>';
          }
          ?>
        </g>
        <text x="20" y="160" font-family="Outfit" font-size="10" fill="#6b8783" text-anchor="middle" transform="rotate(-90 20 160)" font-weight="600" letter-spacing="1">PLACES PAR AN</text>

        <?php foreach ($annees as $idx => $a):
          $x = $startX + $idx * ($barW + $barGap);
          $cumY = $chartBottom;
          $totalY = $chartBottom;
          foreach ($catOrder as $cat) {
              $places = $placesParCatAn[$cat][$idx] ?? 0;
              if ($placesParAn[$idx] === 0) continue;
              $barH = ($places / $maxPlaces) * $chartH;
              $color = $catMeta[$cat][1];
              $cumY -= $barH;
              echo '<rect x="' . $x . '" y="' . round($cumY, 1) . '" width="' . $barW . '" height="' . round($barH, 1) . '" fill="' . $color . '" rx="2"/>';
          }
          $totalH_y = $chartBottom - (($placesParAn[$idx] / $maxPlaces) * $chartH);
        ?>
          <text x="<?= $x + $barW/2 ?>" y="<?= round($totalH_y - 8, 1) ?>" font-family="Fraunces" font-size="18" font-weight="600" fill="#0d2a26" text-anchor="middle"><?= $placesParAn[$idx] ?></text>
          <text x="<?= $x + $barW/2 ?>" y="305" font-family="Fraunces" font-size="13" font-weight="600" fill="#0d2a26" text-anchor="middle"><?= $a ?></text>
          <text x="<?= $x + $barW/2 ?>" y="318" font-family="Outfit" font-size="9.5" fill="#6b8783" text-anchor="middle">N+<?= $idx + 1 ?></text>
        <?php endforeach ?>

        <!-- Trend line budget -->
        <?php
        $maxB = max($budgetParAn);
        $points = [];
        $trendTop = 50; $trendH = 200;
        foreach ($annees as $idx => $a) {
            $px = $startX + $idx * ($barW + $barGap) + $barW/2;
            $py = $trendTop + (1 - ($budgetParAn[$idx] / max(1, $maxB))) * $trendH;
            $points[] = "$px,$py";
        }
        ?>
        <path d="M <?= implode(' L ', $points) ?>" fill="none" stroke="#0d2a26" stroke-width="2" opacity="0.6"/>
        <?php foreach ($points as $i => $p): [$px, $py] = explode(',', $p); ?>
          <circle cx="<?= $px ?>" cy="<?= $py ?>" r="4" fill="#fff" stroke="#0d2a26" stroke-width="2"/>
        <?php endforeach ?>
        <text x="600" y="146" font-family="Outfit" font-size="11" fill="#0d2a26" font-weight="500">CHF <?= number_format($budgetParAn[0] / 1000, 0) ?>k</text>
        <text x="600" y="184" font-family="Outfit" font-size="11" fill="#6b8783" font-weight="500">CHF <?= number_format($budgetParAn[3] / 1000, 0) ?>k</text>
        <text x="700" y="160" font-family="Outfit" font-size="10" fill="#6b8783" text-anchor="middle" transform="rotate(90 700 160)" font-weight="600" letter-spacing="1">BUDGET CHF</text>
      </svg>
    </div>

    <div class="fp-chart-legend">
      <?php foreach ($catMeta as $k => [$lbl, $color, $cls]): ?>
        <div class="fp-cl-item"><span class="fp-cl-sw" style="background:<?= h($color) ?>"></span><?= h($lbl) ?><span class="v"><?= $totalsParCat[$k] ?></span></div>
      <?php endforeach ?>
      <div class="fp-cl-item"><span class="fp-cl-sw" style="background:transparent;border:1.5px dashed #0d2a26"></span>Budget annuel</div>
    </div>
  </div>

  <!-- Year tabs + detail -->
  <div class="fp-section-title">
    <div>
      <h2>Détail année par année</h2>
      <div class="sub">Cliquez sur un onglet pour voir la composition prévisionnelle</div>
    </div>
  </div>

  <div class="fp-year-tabs">
    <?php foreach ($annees as $idx => $a):
      $tag = match($idx) { 0 => 'N+1 · Année prochaine', 1 => 'N+2', 2 => 'N+3', 3 => 'N+4' };
    ?>
      <button class="fp-year-tab <?= $idx === 0 ? 'on' : '' ?>" data-year-idx="<?= $idx ?>">
        <span class="yt-tag"><?= h($tag) ?></span>
        <span class="yt-year"><?= $a ?></span>
        <span class="yt-stat"><strong><?= $placesParAn[$idx] ?> places</strong> <span class="pl">· CHF <?= number_format($budgetParAn[$idx], 0, ',', "'") ?></span></span>
      </button>
    <?php endforeach ?>
  </div>

  <?php foreach ($annees as $yIdx => $year):
    $factor = $decreaseFactors[$yIdx];
  ?>
    <div class="fp-detail-table" data-year-pane="<?= $yIdx ?>" <?= $yIdx > 0 ? 'hidden' : '' ?>>
      <div class="fp-dt-head">
        <div>
          <h3>Composition prévisionnelle <?= h($year) ?></h3>
          <div class="meta"><?= $nbCollabs ?: 62 ?> collaborateurs · <strong><?= $placesParAn[$yIdx] ?> places</strong> à pourvoir · <?= $nbCollabs > 0 ? number_format($placesParAn[$yIdx] / $nbCollabs, 2, ',', '') : '—' ?> place moyenne par collaborateur</div>
        </div>
        <button class="fp-btn" style="font-size:12px;padding:7px 12px">+ Ajouter une thématique</button>
      </div>

      <table class="fp-matrix">
        <thead>
          <tr>
            <th style="width:32%">Thématique</th>
            <th>Catégorie</th>
            <th>Public concerné</th>
            <th class="num">Places<small><?= $year ?></small></th>
            <th class="num">Heures<small>par place</small></th>
            <th class="num">Coût unit.<small>CHF</small></th>
            <th class="num">Sous-total<small>CHF</small></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $catSections = [
              'oblig'   => ['Obligatoires Fegems · imposées par le référentiel', '#b8443a', 'ico-oblig', '!'],
              'renew'   => ['Renouvellements cycliques · échéances calculées', '#2d8074', 'ico-renew', '↻'],
              'onboard' => ['Intégration nouveaux collaborateurs · projection embauches', '#9268b3', 'ico-onboard', '+'],
              'score'   => ['Combler les écarts · issus de la cartographie', '#c97a2a', 'ico-score', '↗'],
              'strat'   => ['Stratégiques · alignement direction', '#5a82a8', 'ico-strat', '★'],
          ];
          $totalYearPlaces = 0; $totalYearHeures = 0; $totalYearBudget = 0;

          foreach ($catSections as $cat => [$catLbl, $catColor, $catIcoCls, $catSym]):
              $items = array_filter($lignes, fn($l) => $l['cat'] === $cat);
              if (!$items) continue;
          ?>
            <tr class="sub"><td colspan="7" style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:<?= h($catColor) ?>;font-weight:700;padding:10px 14px">● <?= h($catLbl) ?></td></tr>
            <?php foreach ($items as $l):
              $places = (int) round($l['places_an'] * $factor);
              $heures = $places * $l['duree'];
              $cout = $places * $l['cout_unit'];
              $totalYearPlaces += $places;
              $totalYearHeures += $heures;
              $totalYearBudget += $cout;
            ?>
              <tr>
                <td>
                  <div class="fp-them-cell">
                    <div class="fp-ico <?= h($catIcoCls) ?>"><?= $catSym ?></div>
                    <div>
                      <div class="name"><?= h($l['them_nom']) ?></div>
                      <div class="meta"><?= h($l['meta_renouv'] ?? '') ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="fp-cat-pill fp-cat-<?= h($cat) ?>"><span class="dot"></span><?= h(ucfirst(['oblig'=>'Obligatoire','renew'=>'Renouv.','onboard'=>'Onboarding','score'=>'Écart','strat'=>'Stratégique'][$cat] ?? $cat)) ?></span></td>
                <td><?= h($l['public']) ?></td>
                <td class="fp-places"><?= $places ?><small>place<?= $places > 1 ? 's' : '' ?></small></td>
                <td class="fp-places"><?= $l['duree'] ?></td>
                <td class="fp-places"><?= $l['cout_unit'] === 0 ? '0<small>cotisation</small>' : number_format($l['cout_unit'], 0, ',', "'") ?></td>
                <td class="fp-places"><?= $cout > 0 ? number_format($cout, 0, ',', "'") : '0' ?></td>
              </tr>
            <?php endforeach ?>
          <?php endforeach ?>

          <!-- Total -->
          <tr class="total">
            <td colspan="3" style="text-align:right;padding-right:14px">Total prévisionnel <?= h($year) ?></td>
            <td class="fp-places"><?= $totalYearPlaces ?> <small>places</small></td>
            <td class="fp-places"><?= $totalYearHeures ?> <small>heures</small></td>
            <td class="fp-places"></td>
            <td class="fp-places">CHF <?= number_format($totalYearBudget, 0, ',', "'") ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  <?php endforeach ?>

  <!-- Export panel -->
  <div class="fp-export-panel">
    <div>
      <h3>Document prêt pour le conseil de fondation</h3>
      <p>Spocspace génère automatiquement une <strong>synthèse exécutive du plan formation 4 ans</strong>
        (constat, cadre réglementaire, scénarios comparés, recommandation au CA) via l'IA, à partir
        de données <strong>anonymisées</strong> de votre EMS. Un seul clic, présentable en conseil.</p>
    </div>
    <div class="fp-export-actions">
      <button class="fp-export-btn fp-export-btn--ai" id="fpGenAi">
        <i class="bi bi-stars"></i> Générer synthèse IA · 4 ans
        <span class="fp-export-btn-sub">2 pages · présentation CA</span>
      </button>
      <button class="fp-export-btn primary" id="fpGenPdf">
        <i class="bi bi-file-earmark-pdf"></i> Générer PDF · Plan 4 ans
        <span class="fp-export-btn-sub">12 pages · document complet</span>
      </button>
      <button class="fp-export-btn" id="fpGenXlsx">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel matrice
      </button>
    </div>
  </div>

</div>

<!-- Modal DPA (Data Processing Agreement) -->
<div class="fpai-modal" id="fpaiModalDpa" hidden>
  <div class="fpai-modal-overlay" data-close></div>
  <div class="fpai-modal-card">
    <div class="fpai-modal-head" style="background:linear-gradient(135deg,#1f6359,#2d8074);color:#fff">
      <div class="fpai-modal-eyebrow" style="color:#a8e6c9">DATA PROCESSING AGREEMENT</div>
      <h3 style="color:#fff">Génération IA · synthèse plan formation</h3>
      <button class="fpai-modal-close" data-close style="color:#fff"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="fpai-modal-body">
      <p class="fpai-intro">
        Avant de générer la synthèse, merci de prendre connaissance des règles de traitement
        des données par l'IA tierce (<?= h(Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ai_provider'") ?: 'Gemini / Claude') ?>).
      </p>

      <div class="fpai-rules">
        <div class="fpai-rule fpai-ok">
          <div class="fpai-rule-ico"><i class="bi bi-check-circle-fill"></i></div>
          <div>
            <strong>Données envoyées (anonymisées)</strong>
            <ul>
              <li>Compteurs agrégés par secteur (effectifs, qualifications)</li>
              <li>Statistiques de conformité (% à jour, écarts critiques)</li>
              <li>Top thématiques avec écarts (par <em>code</em> FEGEMS, ex. HPCI_BASE)</li>
              <li>Heures formation et budget réalisés</li>
              <li>Projections 4 ans du plan</li>
            </ul>
          </div>
        </div>

        <div class="fpai-rule fpai-bad">
          <div class="fpai-rule-ico" style="background:#f7e3e0;color:#b8443a"><i class="bi bi-shield-x"></i></div>
          <div>
            <strong>Données <u>jamais</u> envoyées</strong>
            <ul>
              <li>Aucun nom, prénom, email ou identifiant individuel</li>
              <li>Aucune donnée de résident (médicale, identité, séjour)</li>
              <li>Aucun document personnel (attestation, CV, contrat)</li>
              <li>Aucune note d'entretien individuel</li>
              <li>Aucune adresse, téléphone, photo</li>
            </ul>
          </div>
        </div>

        <div class="fpai-rule fpai-info">
          <div class="fpai-rule-ico" style="background:#e2ecf2;color:#3a6a8a"><i class="bi bi-info-circle-fill"></i></div>
          <div>
            <strong>Conformité</strong>
            <p style="margin:4px 0 0;font-size:12.5px;color:#324e4a">
              Pseudonymisation conforme RGPD art. 4 §5. Le traitement est limité au but unique
              de générer une synthèse rédactionnelle. L'IA n'a accès qu'à des agrégats statistiques.
              Aucune donnée n'est conservée chez le fournisseur au-delà de l'appel API.
            </p>
          </div>
        </div>
      </div>

      <label class="fpai-consent">
        <input type="checkbox" id="fpaiConsent">
        <span>J'ai lu les règles ci-dessus et j'autorise SpocSpace à transmettre les données <strong>anonymisées</strong> à l'IA pour cette génération.</span>
      </label>
    </div>
    <div class="fpai-modal-foot">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-close>Annuler</button>
      <button type="button" class="btn btn-sm fpai-btn-ai" id="fpaiAccept" disabled>
        <i class="bi bi-stars"></i> Générer la synthèse
      </button>
    </div>
  </div>
</div>

<!-- Modal résultat IA -->
<div class="fpai-modal" id="fpaiModalResult" hidden>
  <div class="fpai-modal-overlay" data-close-result></div>
  <div class="fpai-modal-card fpai-modal-large">
    <div class="fpai-modal-head" style="background:linear-gradient(135deg,#1f6359,#2d8074);color:#fff">
      <div>
        <div class="fpai-modal-eyebrow" style="color:#a8e6c9">SYNTHÈSE IA · PRÊTE À PRÉSENTER</div>
        <h3 id="fpaiResultTitle" style="color:#fff">Plan formation pluriannuel — Synthèse exécutive</h3>
      </div>
      <div class="fpai-result-actions">
        <button class="fpai-modal-close" data-close-result style="color:#fff"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="fpai-modal-body" id="fpaiResultBody">
      <div class="fpai-loading">
        <div class="fpai-spinner"></div>
        <p>Génération en cours… <small>~10 secondes</small></p>
      </div>
    </div>
    <div class="fpai-modal-foot fpai-meta-foot" id="fpaiResultMeta"></div>
  </div>
</div>

<style>
.fpai-modal[hidden] { display: none; }
.fpai-modal {
  position: fixed; inset: 0; z-index: 1300;
  display: flex; align-items: center; justify-content: center; padding: 20px;
  font-family: 'Outfit', sans-serif;
}
.fpai-modal-overlay {
  position: absolute; inset: 0; background: rgba(13,42,38,.5);
  backdrop-filter: blur(3px);
}
.fpai-modal-card {
  position: relative; background: #fff; border-radius: 16px;
  width: 100%; max-width: 620px; max-height: 90vh;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: 0 24px 64px -16px rgba(13,42,38,.4);
}
.fpai-modal-large { max-width: 820px; }
.fpai-modal-head {
  padding: 18px 22px;
  display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;
}
.fpai-modal-eyebrow {
  font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase;
  color: #1f6359; font-weight: 600;
}
.fpai-modal-head h3 {
  font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600;
  letter-spacing: -.01em; margin: 4px 0 0;
}
.fpai-modal-close {
  background: rgba(0,0,0,.05); border: 0; cursor: pointer;
  width: 32px; height: 32px; border-radius: 8px;
  display: grid; place-items: center; color: #6b8783;
  flex-shrink: 0;
}
.fpai-modal-close:hover { background: rgba(0,0,0,.1); }
.fpai-modal-body { padding: 20px 22px; overflow: auto; flex: 1; }
.fpai-modal-foot {
  padding: 14px 22px; border-top: 1px solid #e3ebe8;
  display: flex; justify-content: flex-end; gap: 8px;
  background: #fafbfa;
}

.fpai-intro { font-size: 13.5px; color: #324e4a; line-height: 1.5; margin: 0 0 16px; }

.fpai-rules { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.fpai-rule {
  display: flex; gap: 12px; align-items: flex-start;
  padding: 12px 14px; border-radius: 10px;
  background: #fafbfa; border: 1px solid #e3ebe8;
}
.fpai-rule strong { font-size: 13.5px; color: #0d2a26; display: block; margin-bottom: 4px; }
.fpai-rule ul { margin: 4px 0 0; padding-left: 18px; font-size: 12.5px; color: #324e4a; line-height: 1.5; }
.fpai-rule li { margin-bottom: 2px; }
.fpai-rule-ico {
  width: 32px; height: 32px; border-radius: 8px;
  background: #e3f0ea; color: #3d8b6b;
  display: grid; place-items: center; font-size: 16px; flex-shrink: 0;
}

.fpai-consent {
  display: flex; gap: 10px; align-items: flex-start;
  padding: 12px 14px; border-radius: 10px;
  background: #ecf5f3; border: 1px solid #d2e7e2;
  cursor: pointer; user-select: none;
}
.fpai-consent input { margin-top: 2px; flex-shrink: 0; cursor: pointer; }
.fpai-consent span { font-size: 12.5px; color: #164a42; line-height: 1.5; }

/* Résultat IA */
.fpai-loading { padding: 40px 20px; text-align: center; color: #6b8783; }
.fpai-spinner {
  width: 40px; height: 40px; margin: 0 auto 16px;
  border: 3px solid #ecf5f3; border-top-color: #1f6359;
  border-radius: 50%; animation: fpaiSpin .8s linear infinite;
}
@keyframes fpaiSpin { to { transform: rotate(360deg); } }

.fpai-result-actions { display: flex; gap: 6px; align-items: flex-start; }

.fpai-rapport {
  font-size: 13.5px; line-height: 1.65; color: #0d2a26;
  max-width: 720px; margin: 0 auto;
}
.fpai-rapport h1 {
  font-family: 'Fraunces', serif; font-size: 26px; font-weight: 500;
  letter-spacing: -.02em; margin: 0 0 18px; color: #164a42;
}
.fpai-rapport h2 {
  font-family: 'Fraunces', serif; font-size: 17px; font-weight: 600;
  letter-spacing: -.01em; margin: 22px 0 10px; color: #1f6359;
  padding-top: 14px; border-top: 1px solid #e3ebe8;
}
.fpai-rapport h2:first-of-type { padding-top: 0; border-top: 0; }
.fpai-rapport h3 {
  font-family: 'Fraunces', serif; font-size: 14.5px; font-weight: 600;
  margin: 14px 0 6px; color: #324e4a;
}
.fpai-rapport p { margin: 8px 0; }
.fpai-rapport ul, .fpai-rapport ol { margin: 8px 0; padding-left: 22px; }
.fpai-rapport li { margin: 3px 0; }
.fpai-rapport strong { color: #164a42; font-weight: 600; }
.fpai-rapport em { color: #324e4a; font-style: italic; }

/* Boutons modale IA */
.fpai-btn-ai {
  background: linear-gradient(135deg, #1f6359 0%, #2d8074 60%, #5cad9b 100%);
  color: #fff !important; border: 0; padding: 8px 16px;
  font-weight: 600; font-size: 13px;
  display: inline-flex; align-items: center; gap: 7px;
  box-shadow: 0 2px 8px -2px rgba(31,99,89,.4);
  transition: all .15s ease;
}
.fpai-btn-ai:hover:not(:disabled) {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 60%, #2d8074 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px -2px rgba(31,99,89,.5);
}
.fpai-btn-ai:disabled {
  background: #d4ddda; color: #9bb0ad !important;
  cursor: not-allowed; box-shadow: none; opacity: 1;
}
.fpai-btn-ai i { font-size: 14px; }

.fpai-btn-ondark {
  background: rgba(255,255,255,.14) !important; color: #fff !important;
  border: 1px solid rgba(255,255,255,.25) !important;
  font-weight: 500; font-size: 12.5px;
  padding: 6px 12px; transition: all .15s ease;
}
.fpai-btn-ondark:hover {
  background: rgba(255,255,255,.24) !important;
  border-color: rgba(255,255,255,.4) !important;
  color: #fff !important;
}

.fpai-meta-foot {
  display: flex !important; flex-direction: column !important; align-items: stretch !important;
  gap: 10px; padding: 12px 22px !important;
  font-family: 'Outfit', sans-serif;
}
.fpai-meta-info {
  display: flex; gap: 14px; flex-wrap: wrap;
  font-size: 11px; color: #6b8783; font-family: 'JetBrains Mono', monospace;
  padding-bottom: 8px; border-bottom: 1px dashed #e3ebe8;
}
.fpai-meta-info span { display: inline-flex; align-items: center; gap: 4px; }
.fpai-anonymized { color: #3d8b6b; font-weight: 600; }

.fpai-foot-actions {
  display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;
}

/* Boutons doux (Copier / Word / PDF / Imprimer) */
.fpai-btn-soft {
  background: #fff !important; color: #324e4a !important;
  border: 1px solid #e3ebe8 !important; padding: 6px 11px !important;
  font-weight: 500 !important; font-size: 12.5px !important;
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 7px !important; transition: all .12s ease;
}
.fpai-btn-soft:hover {
  border-color: #7ab5ab !important; color: #1f6359 !important;
  background: #ecf5f3 !important;
}
.fpai-btn-soft i { font-size: 13px; }

/* Notice IA en bas du rapport */
.fpai-ai-notice {
  margin-top: 28px; padding: 14px 16px;
  background: #fbf0e1; border-left: 3px solid #c97a2a;
  border-radius: 8px;
  display: flex; gap: 12px; align-items: flex-start;
}
.fpai-ai-notice-ico {
  width: 32px; height: 32px; border-radius: 8px;
  background: #c97a2a; color: #fff;
  display: grid; place-items: center; font-size: 16px;
  flex-shrink: 0;
}
.fpai-ai-notice strong {
  display: block; font-size: 13px; color: #8a5a1a; margin-bottom: 4px;
}
.fpai-ai-notice p {
  margin: 0; font-size: 12px; color: #5a3d12; line-height: 1.5;
}
.fpai-ai-notice strong { color: #8a5a1a; }

@media print {
  body * { visibility: hidden !important; }
  .fpai-modal, .fpai-modal * { visibility: visible !important; }
  .fpai-modal {
    position: absolute !important; inset: 0 !important;
    background: #fff !important; padding: 20px !important;
    display: block !important;
  }
  .fpai-modal-overlay, .fpai-modal-close, .fpai-result-actions, .fpai-meta-foot { display: none !important; }
  .fpai-modal-card { box-shadow: none !important; max-width: 100% !important; max-height: none !important; overflow: visible !important; }
}
</style>

<script<?= nonce() ?>>
(function () {
  const modalDpa = document.getElementById('fpaiModalDpa');
  const modalRes = document.getElementById('fpaiModalResult');
  const consent = document.getElementById('fpaiConsent');
  const acceptBtn = document.getElementById('fpaiAccept');

  function open(modal) { modal.hidden = false; document.body.style.overflow = 'hidden'; }
  function close(modal) { modal.hidden = true; document.body.style.overflow = ''; }

  document.getElementById('fpGenAi')?.addEventListener('click', () => {
    consent.checked = false;
    acceptBtn.disabled = true;
    open(modalDpa);
  });

  consent.addEventListener('change', () => {
    acceptBtn.disabled = !consent.checked;
  });

  modalDpa.querySelectorAll('[data-close]').forEach(el => {
    el.addEventListener('click', () => close(modalDpa));
  });
  modalRes.querySelectorAll('[data-close-result]').forEach(el => {
    el.addEventListener('click', () => close(modalRes));
  });

  acceptBtn.addEventListener('click', async () => {
    if (!consent.checked) return;
    close(modalDpa);
    open(modalRes);

    document.getElementById('fpaiResultBody').innerHTML = `
      <div class="fpai-loading">
        <div class="fpai-spinner"></div>
        <p>Génération en cours… <small>~10 secondes</small></p>
      </div>
    `;
    document.getElementById('fpaiResultMeta').innerHTML = '';

    try {
      const r = await adminApiPost('admin_generate_plan_formation_ai', {
        dpa_accepted: true,
        scenario: 'median',
      });
      if (!r.success) {
        document.getElementById('fpaiResultBody').innerHTML = `
          <div style="padding:30px;text-align:center;color:#b8443a">
            <i class="bi bi-exclamation-triangle" style="font-size:32px;display:block;margin-bottom:10px"></i>
            <strong>Échec de génération</strong>
            <p style="margin-top:8px;font-size:13px;color:#324e4a">${escapeHtml(r.message || 'Erreur inconnue')}</p>
          </div>`;
        return;
      }

      const md = r.rapport || '';
      const html = mdToHtml(md);
      const aiNotice = `
        <div class="fpai-ai-notice">
          <div class="fpai-ai-notice-ico"><i class="bi bi-robot"></i></div>
          <div>
            <strong>Généré par IA — brouillon à vérifier</strong>
            <p>Ce document a été rédigé automatiquement à partir de vos données anonymisées. <strong>Relisez attentivement</strong> chaque chiffre, citation réglementaire et recommandation avant tout envoi ou publication.
            L'IA peut halluciner ou interpréter de façon imparfaite — la validation humaine reste obligatoire. Aucune action automatique (email, modification BDD) n'est déclenchée depuis cette sortie.</p>
          </div>
        </div>`;
      document.getElementById('fpaiResultBody').innerHTML = `<div class="fpai-rapport">${html}${aiNotice}</div>`;

      const meta = r.meta || {};
      document.getElementById('fpaiResultMeta').innerHTML = `
        <div class="fpai-meta-info">
          <span class="fpai-anonymized"><i class="bi bi-shield-check"></i> Données anonymisées</span>
          <span><i class="bi bi-cpu"></i> ${escapeHtml(meta.provider || '')} · ${escapeHtml(meta.model || '')}</span>
          <span><i class="bi bi-stopwatch"></i> ${meta.duration_ms}ms</span>
          <span><i class="bi bi-coin"></i> ${meta.tokens_in}/${meta.tokens_out} tokens · $${(meta.cost_usd || 0).toFixed(4)}</span>
        </div>
        <div class="fpai-foot-actions">
          <button type="button" class="btn btn-sm fpai-btn-soft" id="fpaiCopyBtn">
            <i class="bi bi-clipboard"></i> Copier
          </button>
          <button type="button" class="btn btn-sm fpai-btn-soft" id="fpaiExportWord">
            <i class="bi bi-file-earmark-word"></i> Word
          </button>
          <button type="button" class="btn btn-sm fpai-btn-soft" id="fpaiExportPdf">
            <i class="bi bi-file-earmark-pdf"></i> PDF
          </button>
          <button type="button" class="btn btn-sm fpai-btn-soft" id="fpaiPrintBtn">
            <i class="bi bi-printer"></i> Imprimer
          </button>
          <button type="button" class="btn btn-sm fpai-btn-ai" id="fpaiSendCa">
            <i class="bi bi-send"></i> Envoyer au CA
          </button>
        </div>
      `;

      // Bind tous les boutons après injection
      document.getElementById('fpaiCopyBtn')?.addEventListener('click', copyRapport);
      document.getElementById('fpaiPrintBtn')?.addEventListener('click', () => window.print());
      document.getElementById('fpaiExportWord')?.addEventListener('click', exportWord);
      document.getElementById('fpaiExportPdf')?.addEventListener('click', exportPdf);
      document.getElementById('fpaiSendCa')?.addEventListener('click', () => {
        if (typeof showToast === 'function') showToast('Envoi au CA en cours de développement', 'info');
      });
    } catch (e) {
      document.getElementById('fpaiResultBody').innerHTML = `
        <div style="padding:30px;text-align:center;color:#b8443a">
          <strong>Erreur réseau</strong>
          <p style="margin-top:8px;font-size:13px">${escapeHtml(e.message || '')}</p>
        </div>`;
    }
  });

  function copyRapport() {
    const txt = document.querySelector('.fpai-rapport')?.innerText || '';
    if (!txt) return;
    navigator.clipboard.writeText(txt).then(() => {
      if (typeof showToast === 'function') showToast('Copié dans le presse-papiers', 'success');
    });
  }

  function exportWord() {
    // Récupère seulement le rapport sans le notice IA (qui sera réinjecté en bas)
    const rapport = document.querySelector('.fpai-rapport');
    if (!rapport) return;
    const clone = rapport.cloneNode(true);
    clone.querySelector('.fpai-ai-notice')?.remove();
    const html = clone.innerHTML;
    const dateStr = new Date().toISOString().slice(0, 10);
    // Word ouvre le HTML enveloppé en .doc avec MIME word
    const wordHtml = `<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8"><title>Plan formation pluriannuel</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>
body{font-family:Calibri,sans-serif;font-size:11pt;line-height:1.5;color:#0d2a26;max-width:680px;margin:auto}
h1{font-family:Georgia,serif;font-size:22pt;color:#164a42;margin:0 0 14pt}
h2{font-family:Georgia,serif;font-size:14pt;color:#1f6359;margin:18pt 0 8pt;border-top:1pt solid #d4ddda;padding-top:10pt}
h2:first-of-type{border-top:0;padding-top:0}
h3{font-family:Georgia,serif;font-size:12pt;color:#324e4a;margin:10pt 0 5pt}
p{margin:6pt 0}
ul,ol{margin:6pt 0;padding-left:20pt}
li{margin:2pt 0}
strong{color:#164a42}
em{font-style:italic;color:#324e4a}
.ai-notice{margin-top:24pt;padding:10pt 12pt;background:#fbf0e1;border-left:3pt solid #c97a2a;font-size:9.5pt;color:#8a5a1a}
.ai-notice strong{color:#8a5a1a}
.foot{margin-top:18pt;padding-top:8pt;border-top:1pt dashed #d4ddda;font-size:9pt;color:#6b8783;text-align:center}
</style></head><body>${html}
<div class="ai-notice"><strong>⚠ Généré par IA — brouillon à vérifier.</strong> Relisez chaque chiffre, citation réglementaire et recommandation avant envoi ou publication. La validation humaine reste obligatoire.</div>
<div class="foot">Document généré par SpocSpace · ${dateStr} · Données anonymisées</div>
</body></html>`;
    const blob = new Blob(['﻿' + wordHtml], { type: 'application/msword' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `plan-formation-pluriannuel-${dateStr}.doc`;
    a.click();
    URL.revokeObjectURL(a.href);
    if (typeof showToast === 'function') showToast('Document Word téléchargé', 'success');
  }

  async function exportPdf() {
    // Génération PDF côté serveur via Dompdf → download direct comme Word
    const rapport = document.querySelector('.fpai-rapport');
    if (!rapport) return;
    const clone = rapport.cloneNode(true);
    clone.querySelector('.fpai-ai-notice')?.remove();
    const html = clone.innerHTML;

    const btn = document.getElementById('fpaiExportPdf');
    const orig = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Génération…'; }

    try {
      // CSRF token (admin) — le token réside dans window.__SS_ADMIN__.csrfToken
      const csrf = window.__SS_ADMIN__?.csrfToken || '';

      // L'admin api.php attend du JSON, pas FormData. Donc on envoie un JSON
      // (le HTML est passé tel quel dans le payload).
      const res = await fetch('/spocspace/admin/api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
        },
        body: JSON.stringify({ action: 'admin_export_plan_formation_pdf', html }),
        credentials: 'same-origin',
      });
      if (!res.ok) {
        const t = await res.text().catch(() => '');
        throw new Error('HTTP ' + res.status + (t ? ' · ' + t.slice(0, 200) : ''));
      }
      const blob = await res.blob();
      if (!blob || blob.size < 100) throw new Error('PDF vide');

      // Téléchargement type Word : déclencher download avec nom de fichier
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `plan-formation-pluriannuel-${new Date().toISOString().slice(0,10)}.pdf`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      // Garder l'URL un peu pour que le navigateur puisse ouvrir le fichier après save
      setTimeout(() => URL.revokeObjectURL(url), 5000);

      if (typeof showToast === 'function') showToast('PDF téléchargé · ouvre le fichier pour le visualiser', 'success');
    } catch (e) {
      if (typeof showToast === 'function') showToast('Échec génération PDF : ' + e.message, 'danger');
      console.error('PDF export error:', e);
    } finally {
      if (btn && orig) { btn.disabled = false; btn.innerHTML = orig; }
    }
  }

  // Markdown → HTML minimal (headings, bold, italic, lists, paragraphs)
  function mdToHtml(md) {
    if (!md) return '';
    let lines = md.split('\n');
    let out = [], inList = false, listType = '';
    for (let i = 0; i < lines.length; i++) {
      let l = lines[i];
      // Headings
      let h = l.match(/^(#{1,4})\s+(.+)$/);
      if (h) {
        if (inList) { out.push(`</${listType}>`); inList = false; }
        const lvl = h[1].length;
        out.push(`<h${lvl}>${escapeHtml(h[2])}</h${lvl}>`);
        continue;
      }
      // Bullet list
      if (l.match(/^\s*[-*]\s+/)) {
        if (!inList || listType !== 'ul') {
          if (inList) out.push(`</${listType}>`);
          out.push('<ul>'); inList = true; listType = 'ul';
        }
        out.push('<li>' + inline(l.replace(/^\s*[-*]\s+/, '')) + '</li>');
        continue;
      }
      // Ordered list
      if (l.match(/^\s*\d+\.\s+/)) {
        if (!inList || listType !== 'ol') {
          if (inList) out.push(`</${listType}>`);
          out.push('<ol>'); inList = true; listType = 'ol';
        }
        out.push('<li>' + inline(l.replace(/^\s*\d+\.\s+/, '')) + '</li>');
        continue;
      }
      // End list on blank
      if (l.trim() === '') {
        if (inList) { out.push(`</${listType}>`); inList = false; }
        continue;
      }
      // Paragraph
      if (inList) { out.push(`</${listType}>`); inList = false; }
      out.push('<p>' + inline(l) + '</p>');
    }
    if (inList) out.push(`</${listType}>`);
    return out.join('\n');
  }
  function inline(s) {
    return escapeHtml(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/`(.+?)`/g, '<code>$1</code>');
  }
  function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
})();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Plan formation pluriannuel · refonte fidèle maquette
   Tout scopé sous .fp-page
═══════════════════════════════════════════════════════════════════ */

.fp-page {
  --fp-bg: #f5f7f5;
  --fp-surface: #ffffff;
  --fp-surface-2: #fafbfa;
  --fp-ink: #0d2a26;
  --fp-ink-2: #324e4a;
  --fp-muted: #6b8783;
  --fp-line: #e3ebe8;
  --fp-line-2: #d4ddda;
  --fp-teal-50: #ecf5f3;
  --fp-teal-100: #d2e7e2;
  --fp-teal-300: #7ab5ab;
  --fp-teal-500: #2d8074;
  --fp-teal-600: #1f6359;
  --fp-teal-700: #164a42;
  --fp-teal-900: #0d2a26;
  --fp-warn: #c97a2a;
  --fp-warn-bg: #fbf0e1;
  --fp-danger: #b8443a;
  --fp-danger-bg: #f7e3e0;
  --fp-ok: #3d8b6b;
  --fp-ok-bg: #e3f0ea;
  --fp-info: #3a6a8a;
  --fp-info-bg: #e2ecf2;
  --fp-cat-oblig: #b8443a;
  --fp-cat-score: #c97a2a;
  --fp-cat-renew: #2d8074;
  --fp-cat-strat: #5a82a8;
  --fp-cat-onboard: #9268b3;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 14px;
  color: var(--fp-ink);
  line-height: 1.45;
  padding: 28px 32px 60px;
  max-width: 1600px;
  margin: 0 auto;
}
.fp-page * { box-sizing: border-box; }

/* ═════ PAGE HEAD ═════ */
.fp-page-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; margin-bottom: 24px; flex-wrap: wrap;
}
.fp-h1 {
  font-family: 'Fraunces', serif; font-size: 34px; font-weight: 500;
  letter-spacing: -.025em; line-height: 1.1; color: var(--fp-ink); margin: 0;
}
.fp-sub {
  color: var(--fp-muted); font-size: 14px; margin-top: 5px; max-width: 640px;
}
.fp-sub strong { color: var(--fp-ink-2); }
.fp-actions { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }

.fp-page .fp-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px; font-family: inherit;
  font-size: 13px; font-weight: 500;
  border: 1px solid var(--fp-line); background: var(--fp-surface);
  color: var(--fp-ink-2); cursor: pointer; transition: .15s; text-decoration: none;
}
.fp-page .fp-btn:hover { border-color: var(--fp-teal-300); color: var(--fp-teal-600); }
.fp-page .fp-btn-primary {
  background: var(--fp-teal-600); color: #fff; border-color: var(--fp-teal-600);
}
.fp-page .fp-btn-primary:hover {
  background: var(--fp-teal-700); border-color: var(--fp-teal-700);
}

/* ═════ SCENARIOS ═════ */
.fp-scenarios {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 26px;
}
.fp-scenario {
  background: var(--fp-surface); border: 2px solid var(--fp-line); border-radius: 14px;
  padding: 18px 20px; cursor: pointer; transition: .18s;
  position: relative; overflow: hidden;
}
.fp-scenario:hover { border-color: var(--fp-teal-300); }
.fp-scenario.on {
  border-color: var(--fp-teal-600);
  background: linear-gradient(180deg, #fff, var(--fp-teal-50));
  box-shadow: 0 4px 16px -4px rgba(13,42,38,.08), 0 2px 4px rgba(13,42,38,.04);
}
.fp-scenario.on::before {
  content: ""; position: absolute; inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(45,128,116,.08) 0%, transparent 60%);
  pointer-events: none;
}
.fp-sc-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; margin-bottom: 10px; position: relative;
}
.fp-sc-tag {
  font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase;
  font-weight: 700; padding: 3px 8px; border-radius: 4px;
}
.fp-sc-tag-prudent { background: var(--fp-info-bg); color: var(--fp-info); }
.fp-sc-tag-median  { background: var(--fp-ok-bg); color: var(--fp-ok); }
.fp-sc-tag-ambit   { background: #f3eef0; color: #7a3a5d; }

.fp-sc-radio {
  width: 18px; height: 18px; border-radius: 50%;
  border: 2px solid var(--fp-line-2);
  display: grid; place-items: center; flex-shrink: 0; transition: .15s;
}
.fp-scenario.on .fp-sc-radio { border-color: var(--fp-teal-600); }
.fp-scenario.on .fp-sc-radio::after {
  content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--fp-teal-600);
}
.fp-sc-name {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  letter-spacing: -.01em; line-height: 1.15; position: relative;
}
.fp-sc-desc {
  font-size: 11.5px; color: var(--fp-muted); margin-top: 4px;
  line-height: 1.4; position: relative; min-height: 32px;
}
.fp-sc-stats {
  display: flex; justify-content: space-between;
  margin-top: 12px; padding-top: 12px;
  border-top: 1px solid var(--fp-line);
  position: relative; gap: 8px;
}
.fp-sc-stat { flex: 1; }
.fp-sc-stat .v {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  line-height: 1; color: var(--fp-ink);
}
.fp-sc-stat .v small {
  font-size: 11px; color: var(--fp-muted); font-weight: 400;
  font-family: 'Outfit', sans-serif;
}
.fp-sc-stat .l {
  font-size: 9.5px; letter-spacing: .06em; text-transform: uppercase;
  color: var(--fp-muted); margin-top: 3px; font-weight: 600;
}
.fp-sc-stat.hl .v { color: var(--fp-teal-600); }

/* ═════ KPI HEADLINE ═════ */
.fp-kpi-headline {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 60%, #2d8074 100%);
  border-radius: 18px; padding: 28px 30px; color: #fff;
  position: relative; overflow: hidden; margin-bottom: 22px;
}
.fp-kpi-headline::before {
  content: ""; position: absolute; inset: 0;
  background:
    radial-gradient(circle at 88% 25%, rgba(125,211,168,.18) 0%, transparent 45%),
    radial-gradient(circle at 8% 110%, rgba(168,230,201,.1) 0%, transparent 50%);
  pointer-events: none;
}
.fp-kpi-headline::after {
  content: ""; position: absolute; right: -100px; top: -80px;
  width: 340px; height: 340px;
  background: repeating-radial-gradient(circle at center, rgba(255,255,255,.025) 0, rgba(255,255,255,.025) 1px, transparent 1px, transparent 14px);
  pointer-events: none;
}
.fp-kpi-headline > * { position: relative; z-index: 1; }
.fp-kh-label {
  font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
  color: #a8e6c9; font-weight: 600;
}
.fp-kh-grid {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 24px; margin-top: 14px;
}
.fp-kh-cell { display: flex; flex-direction: column; gap: 3px; }
.fp-kh-cell .k {
  font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase;
  color: #a8c4be; font-weight: 600;
}
.fp-kh-cell .v {
  font-family: 'Fraunces', serif; font-size: 32px; font-weight: 500;
  line-height: 1; letter-spacing: -.02em;
}
.fp-kh-cell .v small {
  font-size: 14px; color: #cfe0db; font-weight: 400;
  font-family: 'Outfit', sans-serif; margin-left: 3px;
}
.fp-kh-cell .delta {
  font-size: 11px; color: #a8e6c9; margin-top: 5px;
  display: flex; align-items: center; gap: 4px;
}

.fp-kh-key {
  margin-top: 18px; padding: 14px 16px;
  background: rgba(255,255,255,.07); border-radius: 11px;
  border: 1px solid rgba(255,255,255,.12);
  display: flex; align-items: center; gap: 14px;
  font-size: 13px; color: #cfe0db;
}
.fp-kh-key .strong-arg {
  background: #7dd3a8; color: #0d2a26; padding: 6px 11px; border-radius: 7px;
  font-weight: 700; font-size: 13px; font-family: 'Fraunces', serif;
  letter-spacing: -.01em; flex-shrink: 0;
}

/* ═════ CHART SECTION ═════ */
.fp-chart-section {
  background: var(--fp-surface); border: 1px solid var(--fp-line);
  border-radius: 16px; padding: 22px 24px; margin-bottom: 22px;
  box-shadow: 0 1px 2px rgba(13,42,38,.04), 0 1px 1px rgba(13,42,38,.03);
}
.fp-cs-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 14px; margin-bottom: 18px;
}
.fp-cs-head h2 {
  font-family: 'Fraunces', serif; font-size: 20px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fp-ink); margin: 0;
}
.fp-cs-head .sub { font-size: 12.5px; color: var(--fp-muted); margin-top: 2px; }

.fp-cs-toggle {
  display: inline-flex; background: var(--fp-surface-2);
  border: 1px solid var(--fp-line); border-radius: 8px; padding: 3px; gap: 2px;
}
.fp-page .fp-cs-toggle button {
  padding: 6px 12px; font-family: inherit; font-size: 11.5px; font-weight: 500;
  color: var(--fp-muted); background: transparent; border: 0;
  border-radius: 6px; cursor: pointer; transition: .15s;
}
.fp-page .fp-cs-toggle button:hover { color: var(--fp-ink-2); }
.fp-page .fp-cs-toggle button.on {
  background: #fff; color: var(--fp-teal-600);
  box-shadow: 0 1px 2px rgba(13,42,38,.04); font-weight: 600;
}

.fp-chart-wrap { position: relative; width: 100%; height: 340px; }
.fp-chart-svg { width: 100%; height: 100%; overflow: visible; }

.fp-chart-legend {
  display: flex; flex-wrap: wrap; gap: 16px;
  margin-top: 16px; padding-top: 16px;
  border-top: 1px dashed var(--fp-line);
  justify-content: center; font-size: 12px;
}
.fp-cl-item {
  display: flex; align-items: center; gap: 7px; color: var(--fp-ink-2);
}
.fp-cl-sw { width: 13px; height: 13px; border-radius: 3px; flex-shrink: 0; }
.fp-cl-item .v {
  color: var(--fp-muted); margin-left: 3px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
}

/* ═════ SECTION TITLE ═════ */
.fp-section-title {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin: 32px 0 14px; gap: 14px;
}
.fp-section-title h2 {
  font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600;
  letter-spacing: -.015em; color: var(--fp-ink); margin: 0;
}
.fp-section-title .sub { font-size: 13px; color: var(--fp-muted); margin-top: 2px; }

/* ═════ YEAR TABS ═════ */
.fp-year-tabs {
  display: flex; gap: 0; margin-bottom: 0;
  background: var(--fp-surface);
  border-radius: 14px 14px 0 0;
  border: 1px solid var(--fp-line); border-bottom: 0;
  overflow: hidden;
}
.fp-page .fp-year-tab {
  flex: 1; padding: 18px 22px; font-family: inherit;
  background: transparent; border: 0; cursor: pointer;
  display: flex; flex-direction: column; align-items: flex-start; gap: 4px;
  border-right: 1px solid var(--fp-line);
  transition: .15s; text-align: left; position: relative;
  color: var(--fp-ink);
}
.fp-page .fp-year-tab:last-child { border-right: 0; }
.fp-page .fp-year-tab:hover { background: var(--fp-surface-2); }
.fp-page .fp-year-tab.on { background: var(--fp-teal-50); }
.fp-page .fp-year-tab.on::after {
  content: ""; position: absolute; left: 0; right: 0; bottom: -1px;
  height: 3px; background: var(--fp-teal-600);
}
.fp-page .fp-year-tab .yt-year {
  font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600;
  color: var(--fp-ink); letter-spacing: -.02em; line-height: 1;
}
.fp-page .fp-year-tab .yt-tag {
  font-size: 10px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fp-muted); font-weight: 600;
}
.fp-page .fp-year-tab.on .yt-tag { color: var(--fp-teal-700); }
.fp-page .fp-year-tab .yt-stat {
  font-size: 12px; color: var(--fp-ink-2); margin-top: 8px;
  font-family: 'JetBrains Mono', monospace;
}
.fp-page .fp-year-tab .yt-stat strong { font-weight: 600; }
.fp-page .fp-year-tab .yt-stat .pl { color: var(--fp-muted); font-size: 11px; }

/* ═════ DETAIL TABLE ═════ */
.fp-detail-table {
  background: var(--fp-surface); border: 1px solid var(--fp-line); border-top: 0;
  border-radius: 0 0 14px 14px; overflow: hidden;
}
.fp-detail-table[hidden] { display: none; }
.fp-dt-head {
  padding: 18px 22px 12px;
  display: flex; align-items: flex-end; justify-content: space-between; gap: 14px;
}
.fp-dt-head h3 {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fp-ink); margin: 0;
}
.fp-dt-head .meta { font-size: 12px; color: var(--fp-muted); }
.fp-dt-head .meta strong { color: var(--fp-ink-2); }

.fp-page table.fp-matrix {
  width: 100%; border-collapse: separate; border-spacing: 0;
}
.fp-matrix thead th {
  font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em;
  color: var(--fp-muted); font-weight: 700; text-align: left; padding: 10px 14px;
  background: var(--fp-surface-2);
  border-top: 1px solid var(--fp-line); border-bottom: 1px solid var(--fp-line);
  white-space: nowrap;
}
.fp-matrix thead th.num { text-align: right; }
.fp-matrix thead th.num small {
  display: block; font-weight: 400; font-size: 9.5px;
  color: var(--fp-muted); margin-top: 1px; letter-spacing: .04em;
}
.fp-matrix tbody td {
  padding: 13px 14px; border-bottom: 1px solid var(--fp-line);
  vertical-align: middle; font-size: 13px;
}
.fp-matrix tbody tr:hover { background: var(--fp-surface-2); }
.fp-matrix tbody tr:last-child td { border-bottom: 0; }
.fp-matrix tbody tr.sub td { background: #fafbfa; }
.fp-matrix tbody tr.total td {
  background: var(--fp-teal-50); font-weight: 600;
  border-top: 2px solid var(--fp-teal-300);
}
.fp-matrix tbody tr.total td:first-child { color: var(--fp-teal-700); }

.fp-them-cell { display: flex; align-items: center; gap: 9px; }
.fp-ico {
  width: 26px; height: 26px; border-radius: 7px;
  display: grid; place-items: center; flex-shrink: 0;
  font-family: 'Fraunces', serif; font-size: 11px; font-weight: 700;
}
.fp-ico.ico-oblig   { background: var(--fp-danger-bg); color: var(--fp-cat-oblig); }
.fp-ico.ico-score   { background: var(--fp-warn-bg);   color: var(--fp-cat-score); }
.fp-ico.ico-renew   { background: var(--fp-teal-50);   color: var(--fp-cat-renew); }
.fp-ico.ico-strat   { background: var(--fp-info-bg);   color: var(--fp-cat-strat); }
.fp-ico.ico-onboard { background: #f3eef0;             color: var(--fp-cat-onboard); }

.fp-them-cell .name { font-weight: 500; color: var(--fp-ink); line-height: 1.2; }
.fp-them-cell .meta { font-size: 11px; color: var(--fp-muted); margin-top: 1px; }

.fp-cat-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 2px 8px; border-radius: 4px;
  font-size: 10.5px; font-weight: 600; letter-spacing: .04em;
  text-transform: uppercase; white-space: nowrap;
}
.fp-cat-pill .dot { width: 6px; height: 6px; border-radius: 50%; }
.fp-cat-oblig   { background: var(--fp-danger-bg); color: var(--fp-cat-oblig); }
.fp-cat-oblig   .dot { background: var(--fp-cat-oblig); }
.fp-cat-score   { background: var(--fp-warn-bg);   color: var(--fp-cat-score); }
.fp-cat-score   .dot { background: var(--fp-cat-score); }
.fp-cat-renew   { background: var(--fp-teal-50);   color: var(--fp-cat-renew); }
.fp-cat-renew   .dot { background: var(--fp-cat-renew); }
.fp-cat-strat   { background: var(--fp-info-bg);   color: var(--fp-cat-strat); }
.fp-cat-strat   .dot { background: var(--fp-cat-strat); }
.fp-cat-onboard { background: #f3eef0;             color: var(--fp-cat-onboard); }
.fp-cat-onboard .dot { background: var(--fp-cat-onboard); }

.fp-places {
  font-family: 'JetBrains Mono', monospace; font-weight: 600;
  font-size: 13.5px; text-align: right;
}
.fp-places small {
  font-weight: 400; font-size: 10.5px; color: var(--fp-muted);
  display: block; margin-top: 1px;
  font-family: 'Outfit', sans-serif; letter-spacing: .04em;
}

/* ═════ EXPORT PANEL ═════ */
.fp-export-panel {
  background: linear-gradient(135deg, #fafbfa 0%, #fff 100%);
  border: 1px solid var(--fp-line); border-radius: 14px;
  padding: 22px 26px;
  display: grid; grid-template-columns: 1fr auto; gap: 24px;
  align-items: center; position: relative; overflow: hidden;
  margin-top: 22px;
}
.fp-export-panel::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0;
  width: 4px; background: linear-gradient(180deg, var(--fp-teal-500), var(--fp-teal-700));
}
.fp-export-panel h3 {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fp-teal-900); margin-bottom: 4px;
}
.fp-export-panel p {
  font-size: 13px; color: var(--fp-ink-2); max-width: 580px; line-height: 1.5;
}
.fp-export-panel p strong { color: var(--fp-teal-700); }
.fp-export-actions { display: flex; flex-direction: column; gap: 8px; }
.fp-page .fp-export-btn {
  display: grid; grid-template-columns: auto 1fr; column-gap: 10px;
  align-items: center; padding: 10px 16px;
  font-family: inherit; font-size: 13.5px; font-weight: 600;
  border-radius: 9px; border: 1px solid var(--fp-teal-300);
  background: #fff; color: var(--fp-teal-700); cursor: pointer;
  transition: all .15s ease; text-align: left;
  min-width: 220px;
}
.fp-page .fp-export-btn > i:first-child {
  grid-row: 1 / span 2; font-size: 18px; align-self: center;
}
.fp-export-btn-sub {
  grid-column: 2; font-size: 10.5px; font-weight: 400;
  opacity: .75; letter-spacing: .02em; margin-top: 1px;
}
.fp-page .fp-export-btn:hover {
  background: var(--fp-teal-50); transform: translateY(-1px);
  box-shadow: 0 4px 10px -4px rgba(13,42,38,.15);
}
.fp-page .fp-export-btn.primary {
  background: var(--fp-teal-600); color: #fff; border-color: var(--fp-teal-600);
}
.fp-page .fp-export-btn.primary:hover {
  background: var(--fp-teal-700); border-color: var(--fp-teal-700); color: #fff;
}
.fp-page .fp-export-btn.primary .fp-export-btn-sub { opacity: .85; }

.fp-page .fp-export-btn--ai {
  background: linear-gradient(135deg, #1f6359 0%, #2d8074 60%, #5cad9b 100%);
  color: #fff; border: 0; padding: 11px 17px;
  box-shadow: 0 4px 12px -4px rgba(31,99,89,.5), 0 2px 4px rgba(31,99,89,.2);
  position: relative; overflow: hidden;
}
.fp-page .fp-export-btn--ai::before {
  content: ""; position: absolute; inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(168,230,201,.3) 0%, transparent 50%);
  pointer-events: none;
}
.fp-page .fp-export-btn--ai:hover {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 60%, #2d8074 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px -4px rgba(31,99,89,.6), 0 4px 8px rgba(31,99,89,.3);
  color: #fff;
}
.fp-page .fp-export-btn--ai > i:first-child {
  background: rgba(255,255,255,.18); padding: 4px 5px; border-radius: 6px;
  font-size: 14px;
}
.fp-page .fp-export-btn--ai .fp-export-btn-sub {
  color: #a8e6c9; opacity: .95;
}

@media (max-width: 1280px) {
  .fp-scenarios { grid-template-columns: 1fr; }
  .fp-kh-grid { grid-template-columns: repeat(3, 1fr); row-gap: 18px; }
}
@media (max-width: 980px) {
  .fp-year-tabs { flex-direction: column; }
  .fp-year-tab { border-right: 0; border-bottom: 1px solid var(--fp-line); }
  .fp-export-panel { grid-template-columns: 1fr; }
}
</style>

<script<?= nonce() ?>>
(function () {
  // Tabs année
  document.querySelectorAll('.fp-year-tab').forEach(t => {
    t.addEventListener('click', () => {
      const idx = t.dataset.yearIdx;
      document.querySelectorAll('.fp-year-tab').forEach(x => x.classList.toggle('on', x === t));
      document.querySelectorAll('[data-year-pane]').forEach(p => {
        p.hidden = p.dataset.yearPane !== idx;
      });
    });
  });

  // Scenario click (placeholder, ne change pas les data en mémoire pour l'instant)
  document.querySelectorAll('.fp-scenario').forEach(s => {
    s.addEventListener('click', () => {
      document.querySelectorAll('.fp-scenario').forEach(x => x.classList.toggle('on', x === s));
    });
  });

  // Toggle places/heures/budget
  document.querySelectorAll('.fp-cs-toggle button').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.fp-cs-toggle button').forEach(x => x.classList.toggle('on', x === b));
    });
  });

  document.getElementById('fpGenPdf')?.addEventListener('click', () => {
    if (typeof showToast === 'function') showToast('Génération PDF en cours de développement', 'info');
  });
  document.getElementById('fpGenXlsx')?.addEventListener('click', () => {
    if (typeof showToast === 'function') showToast('Export Excel en cours de développement', 'info');
  });
})();
</script>
