<?php
// ─── Fiche compétences collaborateur · refonte fidèle maquette ────────────────

$userId = $_GET['id'] ?? '';
$user = $userId ? Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom, f.secteur_fegems,
            np1.prenom AS np1_prenom, np1.nom AS np1_nom,
            (SELECT m.nom FROM user_modules um JOIN modules m ON m.id = um.module_id WHERE um.user_id = u.id AND um.is_principal = 1 LIMIT 1) AS module_principal
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN users np1 ON np1.id = u.n_plus_un_id
     WHERE u.id = ?", [$userId]
) : null;

if (!$user) {
    echo '<div style="padding:40px;text-align:center;color:#6b8783"><i class="bi bi-person-x" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i><strong>Aucun collaborateur sélectionné</strong><div style="margin-top:8px"><a href="?page=rh-formations-cartographie" data-page-link style="color:#1f6359">Retour à la cartographie</a></div></div>';
    return;
}

$competences = Db::fetchAll(
    "SELECT cu.*, t.code AS them_code, t.nom AS them_nom, t.categorie AS them_categorie,
            t.tag_affichage, t.icone, t.duree_validite_mois,
            ev.prenom AS eval_prenom, ev.nom AS eval_nom
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
    "SELECT f.id, f.titre, f.statut, f.date_debut, f.duree_heures, f.lieu, f.modalite, f.cout_formation,
            p.id AS participant_id, p.statut AS participant_statut, p.heures_realisees, p.evaluation_manager,
            p.date_realisation
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ?
     ORDER BY f.date_debut DESC LIMIT 10",
    [$userId]
);
$nbFormationsTotal = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants WHERE user_id = ?", [$userId]);
$nbFormEnCours = count(array_filter($formations, fn($f) => $f['participant_statut'] === 'present' || $f['statut'] === 'en_cours'));
$nbFormRealisees = count(array_filter($formations, fn($f) => $f['participant_statut'] === 'valide' && $f['date_realisation'] && date('Y', strtotime($f['date_realisation'])) === date('Y')));

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
    'soins' => 'Soins', 'socio_culturel' => 'Socio-culturel',
    'hotellerie' => 'Hôtellerie', 'maintenance' => 'Maintenance',
    'administration' => 'Administration', 'management' => 'Management',
];
$secLabel = $secteurMap[$user['secteur_fegems']] ?? '—';

$initials = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));

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
    $dateEntreeAffichee = $dt->format('m.Y');
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

// Génération du SVG radar (centre 180,160 · rMax 120 comme la maquette)
$cx = 180; $cy = 160; $rMax = 120;
$axes = count($radarData);
$ptsActuel = []; $ptsRequis = []; $labelPos = [];
foreach ($radarData as $i => $d) {
    $angle = -M_PI / 2 + ($i * 2 * M_PI / max(1,$axes));
    $rA = ($d['actuel'] / 4) * $rMax;
    $rR = ($d['requis'] / 4) * $rMax;
    $ptsActuel[] = round($rA * cos($angle), 1) . ',' . round($rA * sin($angle), 1);
    $ptsRequis[] = round($rR * cos($angle), 1) . ',' . round($rR * sin($angle), 1);
    $labelPos[] = [
        'x' => round(($rMax + 18) * cos($angle), 1),
        'y' => round(($rMax + 18) * sin($angle) + 4, 1),
        'label' => $d['axe'],
    ];
}

$prioMeta = [
    'haute'   => ['Priorité haute',   'fc-bad',  '⬤'],
    'moyenne' => ['Écart léger',      'fc-warn', '⬤'],
    'basse'   => ['Écart faible',     'fc-info', '⬤'],
    'a_jour'  => ['À niveau requis',  'fc-ok',   '⬤'],
];
?>

<div class="fc-page">

  <!-- HERO teal gradient -->
  <div class="fc-hero">
    <div class="fc-hero-inner">
      <div class="fc-hero-avatar"><?= h($initials) ?></div>

      <div class="fc-hero-id">
        <div class="fc-hero-label"><?= h(strtoupper($user['sexe'] === 'F' ? 'COLLABORATRICE' : 'COLLABORATEUR')) ?> · <?= h(strtoupper($secLabel)) ?></div>
        <h1 class="fc-hero-h1"><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></h1>
        <div class="fc-hero-role">
          <span class="pip"><?= h($user['fonction_nom'] ?? '—') ?></span>
          <?php if ($user['np1_prenom']): ?>
            <span class="dot"></span>
            <span class="pip">N+1 · <?= h(mb_substr($user['np1_prenom'], 0, 1) . '. ' . $user['np1_nom']) ?></span>
          <?php endif ?>
          <?php if ($user['module_principal']): ?>
            <span class="dot"></span>
            <span class="pip">Étage <?= h($user['module_principal']) ?></span>
          <?php endif ?>
        </div>

        <div class="fc-hero-meta">
          <div class="m">
            <span class="k">Taux d'activité</span>
            <span class="v"><?= $user['taux'] ? (int) round($user['taux']) . '%' : '—' ?></span>
          </div>
          <div class="m">
            <span class="k">Ancienneté</span>
            <span class="v"><?= h($anciennete ?: '—') ?><?php if ($dateEntreeAffichee !== '—'): ?> · depuis <?= h($dateEntreeAffichee) ?><?php endif ?></span>
          </div>
          <div class="m">
            <span class="k">Expérience fonction</span>
            <span class="v"><?= h($expFonction) ?></span>
          </div>
          <div class="m">
            <span class="k">Contrat</span>
            <span class="v"><?= h($user['type_contrat'] ?: '—') ?><?php if ($user['cct']): ?> · <?= h($user['cct']) ?><?php endif ?></span>
          </div>
          <div class="m">
            <span class="k">Prochain entretien</span>
            <span class="v"><?= h($prochainEntretien) ?></span>
          </div>
        </div>
      </div>

      <div class="fc-hero-actions">
        <a href="?page=user-edit&id=<?= h($userId) ?>" class="fc-btn fc-btn-on-dark" data-page-link>
          <i class="bi bi-pencil"></i> Modifier
        </a>
        <button type="button" class="fc-btn fc-btn-on-dark" id="exportPdfBtn">
          <i class="bi bi-download"></i> Exporter PDF
        </button>
        <button type="button" class="fc-btn fc-btn-primary" id="lancerEntretienBtn" data-uid="<?= h($userId) ?>">
          <i class="bi bi-chat-square-text"></i> Lancer entretien annuel
        </button>
      </div>
    </div>
  </div>

  <!-- Pull-up snap cards -->
  <div class="fc-snap">
    <div class="fc-card-snap">
      <div class="fc-snap-lbl">Niveau global</div>
      <div class="fc-snap-val"><?= number_format($niveauGlobal, 1, ',', '') ?><span style="font-size:14px;color:#6b8783"> / 4</span></div>
      <div class="fc-snap-sub">Échelle Fegems · moy. pondérée</div>
    </div>
    <div class="fc-card-snap">
      <div class="fc-snap-lbl">Conformité formations</div>
      <div class="fc-snap-val <?= $conformite >= 70 ? 'ok' : ($conformite >= 50 ? 'warn' : 'bad') ?>"><?= $conformite ?>%</div>
      <div class="fc-snap-sub"><?= $nbSousSeuil ?> thématique<?= $nbSousSeuil > 1 ? 's' : '' ?> sous le seuil</div>
    </div>
    <div class="fc-card-snap">
      <div class="fc-snap-lbl">Écarts à combler</div>
      <div class="fc-snap-val bad"><?= $nbHaute + $nbMoyenne ?></div>
      <div class="fc-snap-sub">dont <?= $nbHaute ?> priorité haute<?= $nbHaute > 0 && !empty($competences) ? ' (' . h($competences[0]['them_code'] === 'HPCI_BASE' || $competences[0]['them_code'] === 'HPCI_PRECAUTIONS' ? 'HPCI' : ($competences[0]['them_nom'] ?? '')) . ')' : '' ?></div>
    </div>
    <div class="fc-card-snap">
      <div class="fc-snap-lbl">Heures formation <?= date('Y') ?></div>
      <div class="fc-snap-val"><?= number_format($heuresAnnee, 0, ',', ' ') ?><span style="font-size:14px;color:#6b8783">h</span></div>
      <div class="fc-snap-sub">+ <?= number_format($heuresPlanifiees, 0, ',', ' ') ?> h planifiées d'ici décembre</div>
    </div>
  </div>

  <!-- TABS -->
  <div class="fc-tabs">
    <button class="fc-tab on" data-tab="competences">
      <i class="bi bi-grid-3x3"></i> Compétences <span class="count"><?= count($competences) ?></span>
    </button>
    <button class="fc-tab" data-tab="formations">
      <i class="bi bi-mortarboard"></i> Formations <span class="count"><?= $nbFormationsTotal ?></span>
    </button>
    <button class="fc-tab" data-tab="documents">
      <i class="bi bi-file-text"></i> Documents <span class="count">—</span>
    </button>
    <button class="fc-tab" data-tab="entretiens">
      <i class="bi bi-chat-square-text"></i> Entretiens <span class="count"><?= $nbEntretiens ?></span>
    </button>
    <button class="fc-tab" data-tab="historique">
      <i class="bi bi-clock-history"></i> Historique
    </button>
  </div>

  <!-- ============ TAB COMPÉTENCES ============ -->
  <div class="fc-tab-pane on" data-pane="competences">
    <div class="fc-grid">

      <!-- LEFT COLUMN -->
      <div class="fc-col">

        <!-- Profil compétences (radar) -->
        <?php if (count($radarData) >= 3): ?>
        <div class="fc-panel">
          <div class="fc-panel-head">
            <div>
              <h2>Profil de compétences</h2>
              <div class="sub">Synthèse par domaine · niveau actuel vs requis selon référentiel Fegems</div>
            </div>
            <div class="right">Mise à jour <?= date('d.m.Y') ?></div>
          </div>
          <div class="fc-panel-body">
            <div class="fc-radar-wrap">
              <svg class="fc-radar-svg" viewBox="0 0 360 320" xmlns="http://www.w3.org/2000/svg">
                <defs>
                  <radialGradient id="fcReqGrad" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#9cb8a8" stop-opacity=".05"/>
                    <stop offset="100%" stop-color="#9cb8a8" stop-opacity=".25"/>
                  </radialGradient>
                  <radialGradient id="fcCurGrad" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#2d8074" stop-opacity=".15"/>
                    <stop offset="100%" stop-color="#2d8074" stop-opacity=".5"/>
                  </radialGradient>
                </defs>
                <g transform="translate(<?= $cx ?>,<?= $cy ?>)">
                  <?php for ($i = 1; $i <= 4; $i++): $r = ($i / 4) * $rMax; ?>
                    <circle r="<?= $r ?>" fill="none" stroke="<?= $i === 4 ? '#d4ddda' : '#e3ebe8' ?>" stroke-width="<?= $i === 4 ? '1.2' : '1' ?>" <?= $i === 4 ? 'stroke-dasharray="2 3"' : '' ?>/>
                  <?php endfor ?>
                  <g stroke="#e3ebe8" stroke-width="1">
                    <?php foreach ($radarData as $i => $d):
                      $angle = -M_PI / 2 + ($i * 2 * M_PI / max(1,$axes));
                      $x2 = round($rMax * cos($angle), 1);
                      $y2 = round($rMax * sin($angle), 1);
                    ?>
                      <line x1="0" y1="0" x2="<?= $x2 ?>" y2="<?= $y2 ?>"/>
                    <?php endforeach ?>
                  </g>
                  <polygon points="<?= implode(' ', $ptsRequis) ?>" fill="url(#fcReqGrad)" stroke="#9cb8a8" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.85"/>
                  <polygon points="<?= implode(' ', $ptsActuel) ?>" fill="url(#fcCurGrad)" stroke="#2d8074" stroke-width="2"/>
                  <g fill="#1f6359" stroke="#fff" stroke-width="2">
                    <?php foreach ($ptsActuel as $p): [$x, $y] = explode(',', $p); ?>
                      <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4"/>
                    <?php endforeach ?>
                  </g>
                  <g font-family="Outfit" font-size="11.5" font-weight="500" fill="#324e4a" text-anchor="middle">
                    <?php foreach ($labelPos as $l): ?>
                      <text x="<?= $l['x'] ?>" y="<?= $l['y'] ?>"><?= h($l['label']) ?></text>
                    <?php endforeach ?>
                  </g>
                  <g font-family="JetBrains Mono" font-size="9" fill="#6b8783" text-anchor="middle">
                    <text x="-8" y="-28">1</text>
                    <text x="-8" y="-58">2</text>
                    <text x="-8" y="-88">3</text>
                    <text x="-8" y="-118">4</text>
                  </g>
                </g>
              </svg>

              <!-- Légende latérale -->
              <div class="fc-radar-leg">
                <div class="fc-rl-item">
                  <div class="fc-rl-top">
                    <span class="fc-rl-sw" style="background:#2d8074"></span>Niveau actuel
                  </div>
                  <div class="fc-rl-stat">
                    moyenne pondérée
                    <span style="color:#0d2a26;font-weight:600"><?= number_format($niveauGlobal, 1, ',', '') ?></span>
                  </div>
                  <div class="fc-rl-stat">
                    progression S1
                    <span style="color:#3d8b6b;font-weight:600">+0,3</span>
                  </div>
                </div>
                <div class="fc-rl-item">
                  <div class="fc-rl-top">
                    <span class="fc-rl-sw" style="background:transparent;border:1.5px dashed #9cb8a8"></span>Niveau requis
                  </div>
                  <div class="fc-rl-stat">
                    moyenne pondérée
                    <span style="color:#0d2a26;font-weight:600"><?= number_format($nivRequisMoy, 1, ',', '') ?></span>
                  </div>
                  <div class="fc-rl-stat">
                    écart total
                    <span style="color:#b8443a;font-weight:600">−<?= number_format($ecartTotal, 1, ',', '') ?></span>
                  </div>
                </div>
                <div class="fc-rl-item" style="margin-top:6px">
                  <div class="fc-rl-top" style="color:#6b8783;font-size:11.5px">Synthèse par domaine</div>
                  <div style="font-size:12px;color:#324e4a;line-height:1.5">
                    <?php
                    $analyses = [];
                    foreach ($radarData as $d) {
                        $diff = $d['requis'] - $d['actuel'];
                        if ($diff >= 1.5) $analyses[] = ['#b8443a', $d['axe'], 'reste le point de tension majeur'];
                        elseif ($diff >= 0.6) $analyses[] = ['#c97a2a', $d['axe'], 'à consolider'];
                        elseif ($diff <= 0.2) $analyses[] = ['#3d8b6b', $d['axe'], 'au niveau requis'];
                    }
                    if (!$analyses) $analyses[] = ['#3d8b6b', 'Profil aligné', 'sur les requis Fegems'];
                    foreach (array_slice($analyses, 0, 3) as $i => $a):
                    ?>
                      <strong style="color:<?= $a[0] ?>"><?= h($a[1]) ?></strong> <?= h($a[2]) ?><?= $i < count($analyses) - 1 && $i < 2 ? '. ' : '.' ?>
                    <?php endforeach ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="fc-radar-summary">
            <div class="fc-rs-item">
              <div class="v ok"><?= $nbAJour ?></div>
              <div class="l">À niveau</div>
            </div>
            <div class="fc-rs-item">
              <div class="v warn"><?= $nbMoyenne ?></div>
              <div class="l">Écart léger</div>
            </div>
            <div class="fc-rs-item">
              <div class="v bad"><?= $nbHaute ?></div>
              <div class="l">Écart critique</div>
            </div>
            <div class="fc-rs-item">
              <div class="v" style="color:#324e4a"><?= count($competences) ?></div>
              <div class="l">Thématiques suivies</div>
            </div>
          </div>
        </div>
        <?php endif ?>

        <!-- Détail thématiques -->
        <div class="fc-panel">
          <div class="fc-panel-head">
            <div>
              <h2>Détail des thématiques</h2>
              <div class="sub">Saisie inline · marqueur noir = niveau requis · barre teal = niveau validé</div>
            </div>
            <button class="fc-btn-mini" id="addThematiqueBtn">+ Ajouter une thématique</button>
          </div>

          <?php if (!$competences): ?>
            <div style="padding:50px;text-align:center;color:#6b8783">
              <i class="bi bi-clipboard-x" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i>
              <strong>Aucune compétence évaluée</strong>
              <div style="margin-top:6px;font-size:12.5px">Définies dans <a href="?page=rh-formations-profil" data-page-link style="color:#1f6359">Profil d'équipe attendu</a>.</div>
            </div>
          <?php else: ?>
            <?php foreach ($prioMeta as $prio => [$label, $colorCls, $sym]):
              $list = $grouped[$prio] ?? [];
              if (!$list) continue;
              $color = $prio === 'haute' ? '#b8443a' : ($prio === 'moyenne' ? '#c97a2a' : ($prio === 'basse' ? '#3a6a8a' : '#3d8b6b'));
            ?>
              <div class="fc-group-head">
                <span style="color:<?= $color ?>;font-size:8px">●</span>
                <span><?= h($label) ?></span>
                <span class="num"><?= count($list) ?></span>
              </div>

              <?php foreach ($list as $c):
                $niv = (int) ($c['niveau_actuel'] ?? 0);
                $req = (int) ($c['niveau_requis'] ?? 0);
                $ecart = max($req - $niv, 0);
                $expired = $c['date_expiration'] && $c['date_expiration'] < date('Y-m-d');
                $expSoon = $c['date_expiration'] && !$expired && strtotime($c['date_expiration']) < strtotime('+60 days');
                $fillPct = ($niv / 4) * 100;
                $reqPct = ($req / 4) * 100;
              ?>
                <div class="fc-thematique">
                  <div class="fc-th-head">
                    <div>
                      <div class="fc-th-title">
                        <span class="name"><?= h($c['them_nom']) ?></span>
                        <?php if ($c['tag_affichage']): ?>
                          <span class="tag req"><?= h(ucfirst(strtolower($c['tag_affichage']))) ?></span>
                        <?php endif ?>
                        <?php if ($expired): ?>
                          <span class="tag exp">Expirée <?= date('m.Y', strtotime($c['date_expiration'])) ?></span>
                        <?php elseif ($expSoon): ?>
                          <span class="tag exp">À renouveler <?= date('m.Y', strtotime($c['date_expiration'])) ?></span>
                        <?php elseif ($prio === 'a_jour'): ?>
                          <span class="tag ok">À jour</span>
                        <?php endif ?>
                      </div>
                      <?php if ($c['date_evaluation']): ?>
                        <div class="fc-th-meta">
                          Évalué <?= date('d.m.Y', strtotime($c['date_evaluation'])) ?>
                          <?php if ($c['eval_prenom']): ?> · par Dr. <?= h(mb_substr($c['eval_prenom'], 0, 1) . '. ' . $c['eval_nom']) ?><?php endif ?>
                          <?php if ($c['date_expiration']): ?>
                            · prochaine éval <?= date('m.Y', strtotime($c['date_expiration'])) ?>
                          <?php endif ?>
                        </div>
                      <?php endif ?>
                    </div>
                    <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;color:<?= $ecart > 0 ? $color : '#3d8b6b' ?>;text-align:right;flex-shrink:0">
                      <?php if ($ecart > 0): ?>
                        Écart +<?= $ecart ?>
                        <br><span style="font-size:10px;font-weight:400;color:#6b8783;text-transform:uppercase;letter-spacing:.06em"><?= $ecart ?> niveau<?= $ecart > 1 ? 'x' : '' ?></span>
                      <?php else: ?>
                        ✓ Conforme
                      <?php endif ?>
                    </div>
                  </div>

                  <div class="fc-lvl-track">
                    <div class="fc-grad">
                      <span>1 · Débute</span>
                      <span>2 · Supervision</span>
                      <span>3 · Autonome</span>
                      <span>4 · Référent</span>
                    </div>
                    <?php if ($niv > 0): ?>
                      <div class="fc-lvl-fill" style="width:<?= $fillPct ?>%"><?= $niv ?></div>
                    <?php endif ?>
                    <?php if ($req > 0): ?>
                      <div class="fc-lvl-marker" style="left:<?= $reqPct ?>%"><span class="lbl">Requis <?= $req ?></span></div>
                    <?php endif ?>
                  </div>

                  <div class="fc-th-actions">
                    <?php if ($c['attestation_url']): ?>
                      <a href="<?= h($c['attestation_url']) ?>" class="fc-btn-mini" target="_blank"><i class="bi bi-paperclip"></i> Voir attestation</a>
                    <?php elseif ($prio === 'a_jour'): ?>
                      <button class="fc-btn-mini"><i class="bi bi-paperclip"></i> Attestation</button>
                    <?php endif ?>
                    <button class="fc-btn-mini" data-reeval="<?= h($c['id']) ?>"><i class="bi bi-pencil"></i> Re-évaluer</button>
                    <?php if ($expired || $prio === 'haute'): ?>
                      <button class="fc-btn-mini fc-btn-mini--accent" data-inscrire-form="<?= h($c['thematique_id']) ?>"><i class="bi bi-mortarboard"></i> Inscrire à formation</button>
                    <?php elseif ($prio === 'moyenne'): ?>
                      <button class="fc-btn-mini" data-renouvellement="<?= h($c['id']) ?>"><i class="bi bi-arrow-repeat"></i> Renouvellement</button>
                    <?php endif ?>
                  </div>
                </div>
              <?php endforeach ?>
            <?php endforeach ?>
          <?php endif ?>
        </div>

      </div>

      <!-- RIGHT COLUMN -->
      <aside class="fc-aside">

        <!-- Informations -->
        <div class="fc-panel">
          <div class="fc-panel-head">
            <div><h2>Informations</h2></div>
            <button class="fc-btn-mini"><i class="bi bi-pencil"></i></button>
          </div>
          <div class="fc-info-grid">
            <div class="fc-info-cell">
              <div class="k">Diplôme</div>
              <div class="v"><?= h($user['diplome_principal'] ?: '—') ?></div>
            </div>
            <div class="fc-info-cell">
              <div class="k">Reconnaissance CRS</div>
              <div class="v"><?php if ($user['reconnaissance_crs_annee']): ?><span style="color:#3d8b6b">✓ <?= h($user['reconnaissance_crs_annee']) ?></span><?php else: ?>—<?php endif ?></div>
            </div>
            <div class="fc-info-cell">
              <div class="k">Date d'entrée</div>
              <div class="v"><?= h($user['date_entree'] ? date('d.m.Y', strtotime($user['date_entree'])) : '—') ?></div>
            </div>
            <div class="fc-info-cell">
              <div class="k">Type contrat</div>
              <div class="v"><?= h($user['type_contrat'] ?: '—') ?></div>
            </div>
            <div class="fc-info-cell">
              <div class="k">Taux d'activité</div>
              <div class="v"><?= $user['taux'] ? (int) round($user['taux']) . '% (' . round($user['taux'] * 0.4) . 'h/sem.)' : '—' ?></div>
            </div>
            <div class="fc-info-cell">
              <div class="k">Secteur · étage</div>
              <div class="v"><?= h($secLabel) ?><?php if ($user['module_principal']): ?> · <?= h($user['module_principal']) ?><?php endif ?></div>
            </div>
            <?php if ($referents): ?>
              <div class="fc-info-cell full">
                <div class="k">Rôles spéciaux</div>
                <div class="v">
                  <?php foreach ($referents as $r): ?>
                    Personne ressource <?= h($r['them_nom']) ?> (depuis <?= date('m.Y', strtotime($r['depuis_le'])) ?>)<br>
                  <?php endforeach ?>
                </div>
              </div>
            <?php endif ?>
            <?php if ($user['disponibilite_formation']): ?>
              <div class="fc-info-cell full">
                <div class="k">Disponibilité formation</div>
                <div class="v" style="font-weight:400;color:#324e4a"><?= h($user['disponibilite_formation']) ?></div>
              </div>
            <?php endif ?>
          </div>
        </div>

        <!-- Formations -->
        <div class="fc-panel">
          <div class="fc-panel-head">
            <div>
              <h2>Formations</h2>
              <div class="sub"><?= $nbFormEnCours ?> en cours · <?= $nbFormRealisees ?> réalisées en <?= date('Y') ?></div>
            </div>
            <button class="fc-btn-mini"><i class="bi bi-plus"></i></button>
          </div>

          <?php if ($formations): foreach (array_slice($formations, 0, 3) as $f):
            $sts = match($f['participant_statut']) {
              'valide' => ['Réalisée', 's-fait', 100],
              'present' => ['En cours', 's-encours', 50],
              'inscrit' => ['Inscrite', 's-prog', 0],
              'absent' => ['Absent', 's-prog', 0],
              default => ['—', 's-prog', 0],
            };
            if ($f['statut'] === 'en_cours' && $f['participant_statut'] !== 'valide') $sts = ['En cours', 's-encours', 50];

            $progPct = $sts[2];
            if ($f['heures_realisees'] && $f['duree_heures'] && $sts[1] === 's-encours') {
                $progPct = min(100, ($f['heures_realisees'] / $f['duree_heures']) * 100);
            }
          ?>
            <div class="fc-formation-card">
              <div class="fc-fc-top">
                <div>
                  <div class="fc-fc-name"><?= h($f['titre']) ?></div>
                  <div class="fc-fc-org">
                    <span style="width:5px;height:5px;border-radius:50%;background:#2d8074;display:inline-block"></span>
                    Fegems · <?= h($f['modalite'] ?: 'présentiel') ?><?php if ($f['duree_heures']): ?> · <?= rtrim(rtrim(number_format($f['duree_heures'], 1, '.', ''), '0'), '.') ?>h<?php endif ?>
                  </div>
                </div>
                <span class="fc-fc-status <?= h($sts[1]) ?>"><?= h($sts[0]) ?></span>
              </div>
              <?php if ($progPct > 0 || $sts[1] === 's-encours' || $sts[1] === 's-fait'): ?>
                <div class="fc-fc-progress"><span style="width:<?= $progPct ?>%"></span></div>
              <?php endif ?>
              <div class="fc-fc-meta">
                <?php if ($sts[1] === 's-fait' && $f['date_realisation']): ?>
                  <span><?= date('m.Y', strtotime($f['date_realisation'])) ?> · <?= rtrim(rtrim(number_format($f['duree_heures'] ?: 0, 1, '.', ''), '0'), '.') ?>h</span>
                  <?php if ($f['evaluation_manager']): ?>
                    <span>Évaluation : <?= h(ucfirst($f['evaluation_manager'])) ?></span>
                  <?php endif ?>
                <?php elseif ($f['heures_realisees'] && $f['duree_heures']): ?>
                  <span><?= h($f['heures_realisees']) ?> / <?= h($f['duree_heures']) ?>h</span>
                  <?php if ($f['cout_formation']): ?><span>CHF <?= number_format($f['cout_formation'], 0) ?></span><?php endif ?>
                <?php elseif ($f['date_debut']): ?>
                  <span>Session <?= date('d.m.Y', strtotime($f['date_debut'])) ?></span>
                  <?php if ($f['cout_formation']): ?><span>CHF <?= number_format($f['cout_formation'], 0) ?></span><?php endif ?>
                <?php endif ?>
              </div>
            </div>
          <?php endforeach; else: ?>
            <div style="padding:30px;text-align:center;color:#6b8783;font-size:13px">
              <i class="bi bi-mortarboard" style="font-size:24px;opacity:.3;display:block;margin-bottom:8px"></i>
              Aucune formation
            </div>
          <?php endif ?>
        </div>

        <!-- Objectifs annuels -->
        <div class="fc-panel">
          <div class="fc-panel-head">
            <div>
              <h2>Objectifs annuels</h2>
              <?php if ($dernierEntretien): ?>
                <div class="sub">Issus de l'entretien <?= date('m.Y', strtotime($dernierEntretien['date_entretien'])) ?></div>
              <?php endif ?>
            </div>
            <span style="font-size:11px;color:#6b8783;font-family:'JetBrains Mono',monospace"><?= $nbObjectifsAtteints ?>/<?= count($objectifs) ?></span>
          </div>
          <?php if ($objectifs): ?>
            <div class="fc-timeline">
              <?php foreach ($objectifs as $i => $o):
                $statutCls = match($o['statut']) {
                    'atteint' => 'ok',
                    'en_cours' => 'cur',
                    default => 'next',
                };
                $statutContent = $o['statut'] === 'atteint' ? '✓' : ($i + 1);
                $statutLbl = match($o['statut']) {
                    'atteint' => '',
                    'en_cours' => ' · en cours',
                    'reporte' => ' · reporté',
                    'abandonne' => ' · abandonné',
                    default => '',
                };
              ?>
                <div class="fc-tl-item">
                  <div class="fc-tl-dot <?= $statutCls ?>"><?= $statutContent ?></div>
                  <div class="fc-tl-body">
                    <div class="fc-tl-date"><?= h($o['trimestre_cible']) ?> <?= date('Y') ?><?= $statutLbl ?></div>
                    <div class="fc-tl-title"><?= h($o['libelle']) ?></div>
                    <?php if ($o['description']): ?>
                      <div class="fc-tl-desc"><?= h($o['description']) ?></div>
                    <?php elseif ($o['date_atteint']): ?>
                      <div class="fc-tl-desc">Atteint · <?= date('d.m.Y', strtotime($o['date_atteint'])) ?></div>
                    <?php endif ?>
                  </div>
                </div>
              <?php endforeach ?>
            </div>
          <?php else: ?>
            <div style="padding:24px;text-align:center;color:#6b8783;font-size:13px">
              <i class="bi bi-flag" style="font-size:24px;opacity:.3;display:block;margin-bottom:8px"></i>
              Aucun objectif défini
            </div>
          <?php endif ?>

          <?php if ($dernierEntretien && $dernierEntretien['notes_manager']): ?>
            <div class="fc-notes">
              « <?= h($dernierEntretien['notes_manager']) ?> »
              <div class="fc-notes-author">— Entretien <?= date('m.Y', strtotime($dernierEntretien['date_entretien'])) ?></div>
            </div>
          <?php endif ?>
        </div>

      </aside>
    </div>
  </div>

  <!-- Tabs autres (placeholders) -->
  <div class="fc-tab-pane" data-pane="formations" hidden>
    <div class="fc-panel">
      <div class="fc-panel-body">
        <div style="padding:50px;text-align:center;color:#6b8783">
          <i class="bi bi-mortarboard" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i>
          <strong>Formations détaillées à venir</strong>
          <div style="margin-top:6px;font-size:12.5px">Voir la section "Formations" en haut pour l'aperçu.</div>
        </div>
      </div>
    </div>
  </div>
  <div class="fc-tab-pane" data-pane="documents" hidden>
    <div class="fc-panel"><div class="fc-panel-body"><div style="padding:50px;text-align:center;color:#6b8783"><i class="bi bi-file-text" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i><strong>Documents</strong><div style="margin-top:6px;font-size:12.5px">À venir</div></div></div></div>
  </div>
  <div class="fc-tab-pane" data-pane="entretiens" hidden>
    <div class="fc-panel">
      <?php if ($entretiens): foreach ($entretiens as $e): ?>
        <div class="fc-formation-card" style="border-bottom:1px solid #e3ebe8">
          <div class="fc-fc-top">
            <div>
              <div class="fc-fc-name">Entretien <?= h($e['annee']) ?></div>
              <div class="fc-fc-org"><?= h(date('d.m.Y', strtotime($e['date_entretien']))) ?></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
              <span class="fc-fc-status s-fait"><?= h(strtoupper($e['statut'])) ?></span>
              <a href="?page=rh-entretiens-fiche&id=<?= h($e['id']) ?>" class="fc-btn-mini" data-page-link><i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div style="padding:50px;text-align:center;color:#6b8783"><i class="bi bi-chat-square-text" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i><strong>Aucun entretien</strong></div>
      <?php endif ?>
    </div>
  </div>
  <div class="fc-tab-pane" data-pane="historique" hidden>
    <div class="fc-panel"><div class="fc-panel-body"><div style="padding:50px;text-align:center;color:#6b8783"><i class="bi bi-clock-history" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i><strong>Historique</strong><div style="margin-top:6px;font-size:12.5px">À venir</div></div></div></div>
  </div>

  <!-- Footer -->
  <div class="fc-foot-note">
    <span class="pip"><span style="width:6px;height:6px;border-radius:50%;background:#3d8b6b;display:inline-block"></span>Données chiffrées AES-256-GCM</span>
    <span class="pip"><span style="width:6px;height:6px;border-radius:50%;background:#3a6a8a;display:inline-block"></span>Référentiel Fegems S<?= (int) date('m') >= 7 ? '2' : '1' ?> <?= date('Y') ?></span>
    <span style="margin-left:auto">Dernière modification · <?= date('d.m.Y') ?></span>
  </div>

</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Fiche compétences · refonte fidèle maquette Saliha
   Tout est scopé sous .fc-page
═══════════════════════════════════════════════════════════════════ */

.fc-page {
  --fc-bg: #f5f7f5;
  --fc-surface: #ffffff;
  --fc-surface-2: #fafbfa;
  --fc-ink: #0d2a26;
  --fc-ink-2: #324e4a;
  --fc-muted: #6b8783;
  --fc-line: #e3ebe8;
  --fc-line-2: #d4ddda;
  --fc-teal-50: #ecf5f3;
  --fc-teal-100: #d2e7e2;
  --fc-teal-300: #7ab5ab;
  --fc-teal-500: #2d8074;
  --fc-teal-600: #1f6359;
  --fc-teal-700: #164a42;
  --fc-warn: #c97a2a;
  --fc-warn-bg: #fbf0e1;
  --fc-danger: #b8443a;
  --fc-danger-bg: #f7e3e0;
  --fc-ok: #3d8b6b;
  --fc-ok-bg: #e3f0ea;
  --fc-info: #3a6a8a;
  --fc-info-bg: #e2ecf2;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 14px;
  color: var(--fc-ink);
  line-height: 1.45;
  padding: 0 32px 60px;
  max-width: 1600px;
  margin: 0 auto;
}
.fc-page * { box-sizing: border-box; }

/* ═════ HERO ═════ */
.fc-hero {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 50%, #2d8074 100%);
  margin: 0 -32px 0;
  padding: 32px 32px 110px;
  position: relative;
  overflow: hidden;
}
.fc-hero::before {
  content: ""; position: absolute; inset: 0;
  background:
    radial-gradient(circle at 80% 20%, rgba(125,211,168,.18) 0%, transparent 45%),
    radial-gradient(circle at 15% 100%, rgba(168,230,201,.12) 0%, transparent 50%);
  pointer-events: none;
}
.fc-hero::after {
  content: ""; position: absolute; right: -100px; top: -100px;
  width: 400px; height: 400px;
  background: repeating-radial-gradient(circle at center, rgba(255,255,255,.02) 0, rgba(255,255,255,.02) 1px, transparent 1px, transparent 14px);
  pointer-events: none;
}
.fc-hero-inner {
  position: relative; z-index: 1;
  display: flex; align-items: flex-start; gap: 24px; flex-wrap: wrap;
}
.fc-hero-avatar {
  width: 84px; height: 84px; border-radius: 22px;
  background: linear-gradient(135deg, #fff 0%, #d2e7e2 100%);
  display: grid; place-items: center;
  font-family: 'Fraunces', serif;
  font-size: 34px; font-weight: 600; color: var(--fc-teal-700);
  box-shadow: 0 8px 24px rgba(0,0,0,.18), inset 0 0 0 4px rgba(255,255,255,.6);
  flex-shrink: 0;
}
.fc-hero-id { flex: 1; min-width: 280px; color: #fff; }
.fc-hero-label {
  font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
  color: #a8e6c9; font-weight: 500;
}
.fc-hero-h1 {
  font-family: 'Fraunces', serif;
  font-size: 36px; font-weight: 500; letter-spacing: -.02em;
  line-height: 1.1; margin: 4px 0 0; color: #fff;
}
.fc-hero-role {
  color: #cfe0db; font-size: 14.5px; margin-top: 6px;
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.fc-hero-role .pip { display: inline-flex; align-items: center; gap: 5px; }
.fc-hero-role .dot { width: 3px; height: 3px; border-radius: 50%; background: #7ea69c; }
.fc-hero-meta {
  display: flex; gap: 24px; color: #cfe0db; font-size: 12.5px;
  margin-top: 14px; flex-wrap: wrap;
}
.fc-hero-meta .m { display: flex; flex-direction: column; gap: 1px; }
.fc-hero-meta .m .k {
  font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
  color: #7ea69c;
}
.fc-hero-meta .m .v { color: #fff; font-weight: 500; font-size: 13px; }

.fc-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.fc-page .fc-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px;
  font-family: inherit; font-size: 13px; font-weight: 500;
  border: 1px solid var(--fc-line); background: var(--fc-surface);
  color: var(--fc-ink-2); cursor: pointer; transition: .15s; text-decoration: none;
}
.fc-page .fc-btn:hover { border-color: var(--fc-teal-300); color: var(--fc-teal-600); }
.fc-page .fc-btn-primary {
  background: #fff; color: var(--fc-teal-700); border-color: #fff;
}
.fc-page .fc-btn-primary:hover {
  background: #d2e7e2; color: var(--fc-teal-700); border-color: #d2e7e2;
}
.fc-page .fc-btn-on-dark {
  background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); color: #e8f1ee;
}
.fc-page .fc-btn-on-dark:hover {
  background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.28); color: #fff;
}

/* ═════ SNAP CARDS (pull-up) ═════ */
.fc-snap {
  margin-top: -86px; position: relative; z-index: 2;
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
  margin-bottom: 28px;
}
.fc-card-snap {
  background: var(--fc-surface); border: 1px solid var(--fc-line);
  border-radius: 16px; padding: 16px 18px;
  box-shadow: 0 4px 16px -4px rgba(13,42,38,.08), 0 2px 4px rgba(13,42,38,.04);
}
.fc-snap-lbl {
  font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
  color: var(--fc-muted); font-weight: 600;
}
.fc-snap-val {
  font-family: 'Fraunces', serif;
  font-size: 28px; font-weight: 500; line-height: 1.05;
  letter-spacing: -.02em; margin-top: 6px; color: var(--fc-ink);
}
.fc-snap-val.ok   { color: var(--fc-ok); }
.fc-snap-val.warn { color: var(--fc-warn); }
.fc-snap-val.bad  { color: var(--fc-danger); }
.fc-snap-sub { font-size: 11.5px; color: var(--fc-muted); margin-top: 5px; }

/* ═════ TABS ═════ */
.fc-tabs {
  display: flex; gap: 2px;
  border-bottom: 1px solid var(--fc-line);
  margin-bottom: 24px;
}
.fc-page .fc-tab {
  padding: 11px 18px; font-family: inherit; font-size: 13.5px; font-weight: 500;
  background: transparent; border: 0; color: var(--fc-muted); cursor: pointer;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
  display: inline-flex; align-items: center; gap: 8px; transition: .15s;
}
.fc-page .fc-tab:hover { color: var(--fc-ink-2); }
.fc-page .fc-tab.on {
  color: var(--fc-teal-600); border-bottom-color: var(--fc-teal-600);
}
.fc-page .fc-tab .count {
  font-family: 'JetBrains Mono', monospace; font-size: 10.5px;
  background: var(--fc-teal-50); color: var(--fc-teal-700);
  padding: 1px 6px; border-radius: 99px; font-weight: 600;
}
.fc-page .fc-tab.on .count { background: var(--fc-teal-600); color: #fff; }
.fc-tab-pane:not(.on) { display: none; }

/* ═════ GRID 2 cols ═════ */
.fc-grid {
  display: grid; grid-template-columns: 1fr 360px; gap: 20px;
  align-items: flex-start;
}
.fc-col, .fc-aside { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

/* ═════ PANEL ═════ */
.fc-panel {
  background: var(--fc-surface); border: 1px solid var(--fc-line);
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(13,42,38,.04), 0 1px 1px rgba(13,42,38,.03);
}
.fc-panel-head {
  padding: 18px 22px 14px;
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 12px; border-bottom: 1px solid var(--fc-line);
}
.fc-panel-head h2 {
  font-family: 'Fraunces', serif; font-size: 18px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fc-ink); margin: 0;
}
.fc-panel-head .sub { font-size: 12px; color: var(--fc-muted); margin-top: 2px; }
.fc-panel-head .right { font-size: 12px; color: var(--fc-muted); }
.fc-panel-body { padding: 22px; }

.fc-page .fc-btn-mini {
  padding: 5px 10px; font-size: 11.5px; border-radius: 5px;
  border: 1px solid var(--fc-line); background: #fff; color: var(--fc-ink-2);
  cursor: pointer; font-weight: 500; transition: .15s;
  font-family: inherit; display: inline-flex; align-items: center; gap: 4px;
  text-decoration: none;
}
.fc-page .fc-btn-mini:hover {
  border-color: var(--fc-teal-500); color: var(--fc-teal-600); background: var(--fc-teal-50);
}
.fc-page .fc-btn-mini--accent {
  color: var(--fc-teal-700); background: var(--fc-teal-50); border-color: var(--fc-teal-100);
}

/* ═════ RADAR ═════ */
.fc-radar-wrap {
  display: grid; grid-template-columns: 1fr 1fr; gap: 24px;
  align-items: center;
}
.fc-radar-svg { width: 100%; height: auto; max-width: 340px; display: block; margin: 0 auto; }
.fc-radar-leg { display: flex; flex-direction: column; gap: 14px; }
.fc-rl-item { display: flex; flex-direction: column; gap: 6px; }
.fc-rl-top {
  display: flex; align-items: center; gap: 8px;
  font-size: 12.5px; font-weight: 500; color: var(--fc-ink-2);
}
.fc-rl-sw { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.fc-rl-stat {
  font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--fc-muted);
  display: flex; justify-content: space-between;
}

.fc-radar-summary {
  padding: 14px 22px 22px;
  display: flex; justify-content: space-around;
  border-top: 1px dashed var(--fc-line); background: var(--fc-surface-2);
}
.fc-rs-item { text-align: center; }
.fc-rs-item .v {
  font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600;
  letter-spacing: -.02em; line-height: 1;
}
.fc-rs-item .v.ok   { color: var(--fc-ok); }
.fc-rs-item .v.warn { color: var(--fc-warn); }
.fc-rs-item .v.bad  { color: var(--fc-danger); }
.fc-rs-item .l {
  font-size: 11px; letter-spacing: .06em; text-transform: uppercase;
  color: var(--fc-muted); margin-top: 5px; font-weight: 500;
}

/* ═════ THÉMATIQUES ═════ */
.fc-group-head {
  padding: 14px 22px;
  background: linear-gradient(180deg, #fafbfa, #fff);
  border-bottom: 1px solid var(--fc-line);
  display: flex; align-items: center; gap: 10px;
  font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fc-ink-2); font-weight: 600;
}
.fc-group-head .num {
  background: var(--fc-teal-50); color: var(--fc-teal-700);
  padding: 1px 7px; border-radius: 4px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0; margin-left: auto;
}

.fc-thematique {
  padding: 16px 22px; border-bottom: 1px solid var(--fc-line); transition: .15s;
}
.fc-thematique:hover { background: var(--fc-surface-2); }
.fc-thematique:last-child { border-bottom: 0; }

.fc-th-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 10px;
}
.fc-th-title {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.fc-th-title .name { font-weight: 500; font-size: 14px; color: var(--fc-ink); }
.fc-th-title .tag {
  font-size: 10.5px; padding: 2px 7px; border-radius: 4px;
  background: var(--fc-teal-50); color: var(--fc-teal-700);
  font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
}
.fc-th-title .tag.req { background: #fff3e3; color: #8a5a1a; }
.fc-th-title .tag.exp { background: var(--fc-danger-bg); color: var(--fc-danger); }
.fc-th-title .tag.ok  { background: var(--fc-ok-bg); color: var(--fc-ok); }
.fc-th-meta {
  font-size: 11.5px; color: var(--fc-muted); margin-top: 4px;
  font-family: 'JetBrains Mono', monospace;
}

.fc-lvl-track {
  position: relative; height: 28px; background: var(--fc-surface-2);
  border: 1px solid var(--fc-line); border-radius: 7px; overflow: visible;
  display: grid; grid-template-columns: repeat(4, 1fr);
  margin-bottom: 30px;
}
.fc-lvl-track .fc-grad {
  position: absolute; inset: 0;
  display: grid; grid-template-columns: repeat(4, 1fr);
}
.fc-lvl-track .fc-grad span {
  border-right: 1px dashed var(--fc-line);
  font-size: 9px; color: var(--fc-muted);
  padding: 2px 5px; text-transform: uppercase; letter-spacing: .06em;
  font-weight: 600; display: flex; align-items: flex-start;
}
.fc-lvl-track .fc-grad span:last-child { border-right: 0; }
.fc-lvl-fill {
  position: absolute; left: 0; top: 0; bottom: 0;
  background: linear-gradient(90deg, var(--fc-teal-300), var(--fc-teal-500));
  border-radius: 7px 0 0 7px;
  display: flex; align-items: center; justify-content: flex-end; padding-right: 8px;
  color: #fff; font-weight: 600; font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
  box-shadow: inset 0 -2px 4px rgba(0,0,0,.08);
  z-index: 1;
}
.fc-lvl-marker {
  position: absolute; top: -3px; bottom: -3px; width: 2px;
  background: var(--fc-ink); border-radius: 2px; z-index: 2;
}
.fc-lvl-marker::before {
  content: ""; position: absolute; top: -6px; left: 50%; transform: translateX(-50%);
  width: 0; height: 0;
  border-left: 5px solid transparent; border-right: 5px solid transparent;
  border-top: 6px solid var(--fc-ink);
}
.fc-lvl-marker .lbl {
  position: absolute; bottom: -22px; left: 50%; transform: translateX(-50%);
  font-size: 10px; font-weight: 600; color: var(--fc-ink);
  background: #fff; border: 1px solid var(--fc-ink); border-radius: 4px;
  padding: 1px 5px; white-space: nowrap; font-family: 'JetBrains Mono', monospace;
}

.fc-th-actions {
  display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; justify-content: flex-end;
}

/* ═════ INFOS GRID ═════ */
.fc-info-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
  padding: 18px 22px;
}
.fc-info-cell { display: flex; flex-direction: column; gap: 2px; }
.fc-info-cell .k {
  font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
  color: var(--fc-muted); font-weight: 600;
}
.fc-info-cell .v { font-size: 13.5px; color: var(--fc-ink); font-weight: 500; }
.fc-info-cell.full { grid-column: 1 / -1; }

/* ═════ FORMATIONS ═════ */
.fc-formation-card {
  padding: 14px 22px; border-bottom: 1px solid var(--fc-line);
  display: flex; flex-direction: column; gap: 8px;
}
.fc-formation-card:last-child { border-bottom: 0; }
.fc-fc-top {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
}
.fc-fc-name { font-size: 13px; font-weight: 500; color: var(--fc-ink); line-height: 1.3; }
.fc-fc-org {
  font-size: 11px; color: var(--fc-muted); margin-top: 2px;
  display: flex; align-items: center; gap: 6px;
}
.fc-fc-status {
  font-size: 10.5px; padding: 2px 7px; border-radius: 4px;
  font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
  white-space: nowrap; flex-shrink: 0;
}
.fc-fc-status.s-encours { background: var(--fc-info-bg); color: var(--fc-info); }
.fc-fc-status.s-prog { background: var(--fc-warn-bg); color: var(--fc-warn); }
.fc-fc-status.s-fait { background: var(--fc-ok-bg); color: var(--fc-ok); }
.fc-fc-progress {
  height: 4px; background: var(--fc-line); border-radius: 99px; overflow: hidden;
}
.fc-fc-progress span {
  display: block; height: 100%;
  background: linear-gradient(90deg, var(--fc-teal-500), #5cad9b);
  border-radius: 99px;
}
.fc-fc-meta {
  display: flex; justify-content: space-between;
  font-size: 11px; color: var(--fc-muted);
  font-family: 'JetBrains Mono', monospace;
}

/* ═════ TIMELINE OBJECTIFS ═════ */
.fc-timeline {
  padding: 18px 22px; display: flex; flex-direction: column; gap: 14px;
  position: relative;
}
.fc-timeline::before {
  content: ""; position: absolute; left: 32px; top: 24px; bottom: 24px;
  width: 2px; background: var(--fc-line);
}
.fc-tl-item { display: flex; gap: 14px; position: relative; }
.fc-tl-dot {
  width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
  display: grid; place-items: center;
  font-size: 10px; font-weight: 700; color: #fff; z-index: 1;
  font-family: 'JetBrains Mono', monospace;
}
.fc-tl-dot.ok { background: var(--fc-ok); }
.fc-tl-dot.cur {
  background: var(--fc-teal-600);
  box-shadow: 0 0 0 4px var(--fc-teal-50);
}
.fc-tl-dot.next {
  background: var(--fc-surface); border: 2px dashed var(--fc-line-2);
  color: var(--fc-muted);
}
.fc-tl-body { flex: 1; min-width: 0; }
.fc-tl-date {
  font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fc-muted); font-weight: 600;
}
.fc-tl-title {
  font-size: 13px; color: var(--fc-ink); font-weight: 500;
  margin-top: 1px; line-height: 1.35;
}
.fc-tl-desc {
  font-size: 11.5px; color: var(--fc-muted); margin-top: 3px; line-height: 1.4;
}

/* ═════ NOTES (citation entretien) ═════ */
.fc-notes {
  padding: 18px 22px; font-size: 13px; line-height: 1.55;
  color: var(--fc-ink-2); background: #fdfbf3;
  border-top: 1px solid #f0eccd; font-style: italic;
  position: relative;
}
.fc-notes::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: #d4a635;
}
.fc-notes-author {
  font-style: normal; font-size: 11.5px; color: var(--fc-muted);
  margin-top: 8px; display: flex; align-items: center; gap: 6px;
}

/* ═════ FOOT NOTE ═════ */
.fc-foot-note {
  margin-top: 24px; padding: 18px 0;
  font-size: 11.5px; color: var(--fc-muted);
  display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
  border-top: 1px solid var(--fc-line);
}
.fc-foot-note .pip { display: flex; align-items: center; gap: 5px; }

/* ═════ RESPONSIVE ═════ */
@media (max-width: 1280px) {
  .fc-grid { grid-template-columns: 1fr; }
  .fc-snap { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 980px) {
  .fc-radar-wrap { grid-template-columns: 1fr; }
  .fc-info-grid { grid-template-columns: 1fr; }
  .fc-hero-meta { gap: 14px; }
}
</style>

<script<?= nonce() ?>>
(function () {
  // Tabs
  document.querySelectorAll('.fc-tab').forEach(t => {
    t.addEventListener('click', () => {
      const tab = t.dataset.tab;
      document.querySelectorAll('.fc-tab').forEach(x => x.classList.toggle('on', x === t));
      document.querySelectorAll('.fc-tab-pane').forEach(p => {
        const isActive = p.dataset.pane === tab;
        p.classList.toggle('on', isActive);
        p.hidden = !isActive;
      });
    });
  });

  // Boutons hero
  document.getElementById('exportPdfBtn')?.addEventListener('click', () => {
    if (typeof showToast === 'function') showToast('Export PDF en cours de développement', 'info');
  });
  document.getElementById('lancerEntretienBtn')?.addEventListener('click', () => {
    location.href = '?page=rh-entretiens&statut=planifie';
  });
  document.getElementById('addThematiqueBtn')?.addEventListener('click', () => {
    if (typeof showToast === 'function') showToast('Ajout de thématique en cours de développement', 'info');
  });

  document.querySelectorAll('[data-reeval]').forEach(b => {
    b.addEventListener('click', () => {
      if (typeof showToast === 'function') showToast('Modal re-évaluation à venir', 'info');
    });
  });
  document.querySelectorAll('[data-inscrire-form]').forEach(b => {
    b.addEventListener('click', () => location.href = '?page=rh-formations-fegems');
  });
  document.querySelectorAll('[data-renouvellement]').forEach(b => {
    b.addEventListener('click', () => {
      if (typeof showToast === 'function') showToast('Lancer renouvellement à venir', 'info');
    });
  });
})();
</script>
