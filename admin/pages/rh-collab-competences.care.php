<?php
// ─── Fiche collaborateur compétences · version Care (Spocspace DS v1.0) ──────

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
    echo '<div class="care-page">';
    echo '<div class="care-section-title"><h2 class="serif">Fiche compétences</h2></div>';
    echo '<div class="ds-card ds-card-padded"><div class="ds-empty"><i class="bi bi-person-x"></i>';
    echo '<div class="ds-empty-title">Aucun collaborateur sélectionné</div>';
    echo '<div class="ds-empty-msg"><a href="?page=rh-formations-cartographie" data-page-link>Retour à la cartographie</a></div>';
    echo '</div></div></div>';
    return;
}

$competences = Db::fetchAll(
    "SELECT cu.*, t.code AS them_code, t.nom AS them_nom, t.categorie AS them_categorie,
            t.tag_affichage, t.icone, t.duree_validite_mois
     FROM competences_user cu
     JOIN competences_thematiques t ON t.id = cu.thematique_id
     WHERE cu.user_id = ?
     ORDER BY FIELD(cu.priorite,'haute','moyenne','basse','a_jour'), cu.ecart DESC, t.ordre ASC",
    [$userId]
);

$nbEvalues = 0; $sumNiv = 0;
foreach ($competences as $c) {
    if ($c['niveau_actuel'] !== null) { $nbEvalues++; $sumNiv += (int) $c['niveau_actuel']; }
}
$niveauGlobal = $nbEvalues > 0 ? round($sumNiv / $nbEvalues, 1) : 0;
$nbAJour   = count(array_filter($competences, fn($c) => $c['priorite'] === 'a_jour'));
$nbHaute   = count(array_filter($competences, fn($c) => $c['priorite'] === 'haute'));
$nbMoyenne = count(array_filter($competences, fn($c) => $c['priorite'] === 'moyenne'));
$conformite = count($competences) > 0 ? round(($nbAJour / count($competences)) * 100) : 0;

$heuresAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? AND p.statut IN ('present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())",
    [$userId]
);

$formations = Db::fetchAll(
    "SELECT f.id, f.titre, f.statut, f.date_debut, f.duree_heures,
            p.statut AS participant_statut, p.heures_realisees
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.user_id = ? ORDER BY f.date_debut DESC LIMIT 6",
    [$userId]
);

$objectifs = Db::fetchAll(
    "SELECT o.*, t.nom AS them_nom FROM competences_objectifs_annuels o
     LEFT JOIN competences_thematiques t ON t.id = o.thematique_id_liee
     WHERE o.user_id = ? AND o.annee = YEAR(CURDATE())
     ORDER BY FIELD(o.trimestre_cible,'Q1','Q2','Q3','Q4','annuel'), o.ordre",
    [$userId]
);

$grouped = ['haute' => [], 'moyenne' => [], 'basse' => [], 'a_jour' => []];
foreach ($competences as $c) $grouped[$c['priorite']][] = $c;

$prioMeta = [
    'haute'   => ['Priorité haute',  'exclamation-triangle', 'danger'],
    'moyenne' => ['Écart léger',     'exclamation-circle',   'warn'],
    'basse'   => ['Écart faible',    'info-circle',          'info'],
    'a_jour'  => ['À niveau requis', 'check-circle',         'ok'],
];

$secteurMap = [
    'soins'         => ['Soins',         's-soins'],
    'socio_culturel'=> ['Socio-culturel','s-anim'],
    'hotellerie'    => ['Hôtellerie',    's-hot'],
    'maintenance'   => ['Maintenance',   's-tech'],
    'administration'=> ['Administration','s-admin'],
    'management'    => ['Management',    's-mgmt'],
];
[$secLabel, $secCls] = $secteurMap[$user['secteur_fegems']] ?? ['—', ''];

$initials = strtoupper(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));

// Radar : 6 axes
$radarGroups = [
    'HPCI'              => ['HPCI_BASE', 'HPCI_PRECAUTIONS'],
    'Réanimation'       => ['BLS_AED'],
    'Soins palliatifs'  => ['SOINS_PALLIATIFS'],
    'Bientraitance'     => ['BIENTRAITANCE', 'BPSD'],
    'Sécurité'          => ['SECURITE_INCENDIE', 'CYBER_SECURITE'],
    'Transmissions'     => ['TRANSMISSIONS_INF', 'TRANSMISSIONS_AS', 'ACTES_DELEGUES_INF', 'ACTES_DELEGUES_ASA', 'ACTES_DELEGUES'],
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
?>
<div class="care-page">

  <!-- Hero -->
  <header class="ds-hero">
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
      <div class="ds-avatar xl" style="background:rgba(255,255,255,.15);color:#fff;font-family:var(--font-display);font-size:24px;width:72px;height:72px;border-radius:var(--r-lg)">
        <?= h($initials) ?>
      </div>
      <div style="flex:1;min-width:280px">
        <div class="t-eyebrow" style="color:#a8e6c9">Collaborateur · <?= h($secLabel) ?></div>
        <h1><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></h1>
        <div class="ds-hero-sub">
          <?= h($user['fonction_nom'] ?? '—') ?>
          <?php if ($user['np1_prenom']): ?>
            · N+1 <?= h($user['np1_prenom'] . ' ' . $user['np1_nom']) ?>
          <?php endif ?>
          <?php if ($user['email']): ?>
            · <?= h($user['email']) ?>
          <?php endif ?>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="?page=rh-formations-cartographie" class="btn btn-light btn-sm" data-page-link>
          <i class="bi bi-arrow-left"></i> Cartographie
        </a>
      </div>
    </div>
  </header>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="kpi feature">
        <div class="kpi-lbl">Niveau global</div>
        <div class="kpi-val t-num"><?= number_format($niveauGlobal, 1, ',', '') ?><small>/4</small></div>
        <div class="kpi-delta"><?= $nbEvalues ?> compétences évaluées</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Conformité</div>
        <div class="kpi-val t-num" style="color:<?= $conformite >= 70 ? 'var(--ok)' : ($conformite >= 50 ? 'var(--warn)' : 'var(--danger)') ?>"><?= $conformite ?><small>%</small></div>
        <div class="kpi-delta"><?= $nbAJour ?>/<?= count($competences) ?> à niveau requis</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Écarts critiques</div>
        <div class="kpi-val t-num" style="color:var(--danger)"><?= $nbHaute ?></div>
        <div class="kpi-delta bad"><?= $nbMoyenne ?> écarts moyens</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Heures formation <?= date('Y') ?></div>
        <div class="kpi-val t-num"><?= number_format($heuresAnnee, 0, ',', ' ') ?><small>h</small></div>
        <div class="kpi-delta"><?= count($formations) ?> sessions cette année</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Colonne gauche -->
    <div class="col-lg-8">

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
                'x' => round($cx + ($rMax + 18) * cos($angle), 1),
                'y' => round($cy + ($rMax + 18) * sin($angle), 1),
                'label' => $d['axe'],
            ];
        }
      ?>
      <!-- Radar -->
      <div class="ds-card mb-3">
        <div class="ds-card-head">
          <div>
            <div class="ds-card-title">Profil de compétences</div>
            <div class="ds-card-sub">Synthèse par domaine · niveau actuel vs requis FEGEMS</div>
          </div>
        </div>
        <div class="ds-card-body">
          <div class="row align-items-center">
            <div class="col-md-7">
              <svg viewBox="0 0 400 360" style="width:100%;max-width:420px;display:block;margin:0 auto">
                <defs>
                  <radialGradient id="careRadarReq" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#9cb8a8" stop-opacity=".05"/>
                    <stop offset="100%" stop-color="#9cb8a8" stop-opacity=".25"/>
                  </radialGradient>
                  <radialGradient id="careRadarCur" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#2d8074" stop-opacity=".15"/>
                    <stop offset="100%" stop-color="#2d8074" stop-opacity=".5"/>
                  </radialGradient>
                </defs>
                <?php for ($i = 1; $i <= 4; $i++): $r = ($i / 4) * $rMax; ?>
                  <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="<?= $i === 4 ? '#d4ddda' : '#e3ebe8' ?>" stroke-width="1" <?= $i === 4 ? 'stroke-dasharray="2 3"' : '' ?>/>
                <?php endfor ?>
                <?php foreach ($radarData as $i => $d):
                    $angle = -M_PI / 2 + ($i * 2 * M_PI / $axes);
                    $x2 = round($cx + $rMax * cos($angle), 1);
                    $y2 = round($cy + $rMax * sin($angle), 1);
                ?>
                  <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="#e3ebe8" stroke-width="1"/>
                <?php endforeach ?>
                <polygon points="<?= implode(' ', $ptsRequis) ?>" fill="url(#careRadarReq)" stroke="#9cb8a8" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.85"/>
                <polygon points="<?= implode(' ', $ptsActuel) ?>" fill="url(#careRadarCur)" stroke="#2d8074" stroke-width="2"/>
                <?php foreach ($ptsActuel as $p): [$x, $y] = explode(',', $p); ?>
                  <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" fill="#1f6359" stroke="#fff" stroke-width="2"/>
                <?php endforeach ?>
                <?php foreach ($labelPos as $l): ?>
                  <text x="<?= $l['x'] ?>" y="<?= $l['y'] ?>" text-anchor="middle" font-size="11" font-weight="500" fill="#324e4a" font-family="Outfit, sans-serif"><?= h($l['label']) ?></text>
                <?php endforeach ?>
              </svg>
            </div>
            <div class="col-md-5">
              <div class="d-flex flex-column gap-2">
                <div class="d-flex align-items-center gap-2">
                  <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#2d8074"></span>
                  <span style="font-weight:600;color:var(--ink)">Niveau actuel</span>
                  <span class="ms-auto t-num" style="font-weight:600"><?= number_format($niveauGlobal, 1, ',', '') ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span style="display:inline-block;width:12px;height:12px;border-radius:3px;border:1.5px dashed #9cb8a8"></span>
                  <span style="font-weight:600;color:var(--ink)">Niveau requis</span>
                  <span class="ms-auto t-num" style="font-weight:600"><?php
                    $sumR = 0; $cntR = 0;
                    foreach ($radarData as $d) { $sumR += $d['requis']; $cntR++; }
                    echo $cntR > 0 ? number_format($sumR / $cntR, 1, ',', '') : '—';
                  ?></span>
                </div>
                <hr class="my-1" style="border-color:var(--line)">
                <div class="t-small">
                  <?php
                  $analyses = [];
                  foreach ($radarData as $d) {
                      $diff = $d['requis'] - $d['actuel'];
                      if ($diff >= 1.5) $analyses[] = '<strong style="color:var(--danger)">' . h($d['axe']) . '</strong> tension';
                      elseif ($diff >= 0.6) $analyses[] = '<strong style="color:var(--warn)">' . h($d['axe']) . '</strong> à consolider';
                  }
                  echo $analyses ? implode(' · ', $analyses) : '<span style="color:var(--ok)">Profil aligné sur les requis FEGEMS</span>';
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="ds-card-foot" style="display:grid;grid-template-columns:repeat(4, 1fr);text-align:center">
          <div><div class="t-num h-2" style="color:var(--ok)"><?= $nbAJour ?></div><div class="t-meta">À niveau</div></div>
          <div><div class="t-num h-2" style="color:var(--warn)"><?= $nbMoyenne ?></div><div class="t-meta">Écart léger</div></div>
          <div><div class="t-num h-2" style="color:var(--danger)"><?= $nbHaute ?></div><div class="t-meta">Écart critique</div></div>
          <div><div class="t-num h-2"><?= count($competences) ?></div><div class="t-meta">Suivies</div></div>
        </div>
      </div>
      <?php endif ?>

      <!-- Détail thématiques par priorité -->
      <div class="ds-card">
        <div class="ds-card-head">
          <div>
            <div class="ds-card-title">Détail des thématiques</div>
            <div class="ds-card-sub">Marqueur = niveau requis · barre = niveau validé</div>
          </div>
        </div>

        <?php if (!$competences): ?>
          <div class="ds-empty">
            <i class="bi bi-clipboard-x"></i>
            <div class="ds-empty-title">Aucune compétence évaluée</div>
            <div class="ds-empty-msg">Définies dans <a href="?page=rh-formations-profil" data-page-link>Profil d'équipe attendu</a>.</div>
          </div>
        <?php else: ?>
          <?php foreach ($prioMeta as $prio => [$label, $icon, $colorKey]):
            $list = $grouped[$prio] ?? [];
            if (!$list) continue;
            $colorVar = ['danger' => 'var(--danger)', 'warn' => 'var(--warn)', 'info' => 'var(--info)', 'ok' => 'var(--ok)'][$colorKey];
          ?>
            <div style="padding:10px 20px;background:var(--surface-2);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:8px">
              <i class="bi bi-<?= h($icon) ?>" style="color:<?= $colorVar ?>"></i>
              <strong style="color:var(--ink-2)"><?= h($label) ?></strong>
              <span class="t-num t-meta" style="margin-left:auto"><?= count($list) ?></span>
            </div>

            <?php foreach ($list as $c):
              $niv = (int) ($c['niveau_actuel'] ?? 0);
              $req = (int) ($c['niveau_requis'] ?? 0);
              $ecart = max($req - $niv, 0);
              $fillPct = $niv > 0 ? ($niv / 4) * 100 : 0;
              $reqPct = $req > 0 ? ($req / 4) * 100 : 0;
            ?>
              <div style="padding:14px 20px;border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
                  <div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                      <strong style="color:var(--ink)"><?= h($c['them_nom']) ?></strong>
                      <?php if ($c['tag_affichage']): ?>
                        <span class="badge text-bg-warning"><?= h($c['tag_affichage']) ?></span>
                      <?php endif ?>
                      <?php if ($c['date_expiration'] && $c['date_expiration'] < date('Y-m-d')): ?>
                        <span class="badge text-bg-danger">Expirée</span>
                      <?php endif ?>
                    </div>
                    <?php if ($c['date_evaluation']): ?>
                      <div class="t-meta t-num" style="margin-top:3px">
                        Évalué le <?= date('d.m.Y', strtotime($c['date_evaluation'])) ?>
                      </div>
                    <?php endif ?>
                  </div>
                  <div style="text-align:right;flex-shrink:0">
                    <?php if ($ecart > 0): ?>
                      <div class="t-num" style="font-weight:600;color:<?= $colorVar ?>">Écart +<?= $ecart ?></div>
                      <div class="t-meta"><?= $ecart ?> niveau<?= $ecart > 1 ? 'x' : '' ?></div>
                    <?php else: ?>
                      <div class="t-num" style="font-weight:600;color:var(--ok)"><i class="bi bi-check-circle"></i> Conforme</div>
                    <?php endif ?>
                  </div>
                </div>
                <!-- Track simple style DS -->
                <div style="position:relative;height:22px;background:var(--surface-3);border-radius:4px;overflow:hidden">
                  <?php if ($fillPct > 0): ?>
                    <div style="position:absolute;left:0;top:0;bottom:0;width:<?= $fillPct ?>%;background:linear-gradient(90deg, <?= $ecart === 0 ? 'var(--teal-500),var(--teal-600)' : ($ecart === 1 ? 'var(--warn),#e0a85a' : 'var(--danger),#cd6b62') ?>);display:flex;align-items:center;justify-content:flex-end;padding-right:6px;color:#fff;font-size:11px;font-weight:600;font-family:var(--font-mono)"><?= $niv ?></div>
                  <?php endif ?>
                  <?php if ($reqPct > 0): ?>
                    <div style="position:absolute;top:-3px;bottom:-3px;left:<?= $reqPct ?>%;width:2px;background:var(--ink);transform:translateX(-1px)" title="Requis <?= $req ?>"></div>
                  <?php endif ?>
                </div>
                <div class="t-meta" style="display:flex;justify-content:space-between;margin-top:4px">
                  <span>1 · Débute</span><span>2 · Supervision</span><span>3 · Autonome</span><span>4 · Référent</span>
                </div>
              </div>
            <?php endforeach ?>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>

    <!-- Colonne droite -->
    <div class="col-lg-4">

      <!-- Informations -->
      <div class="ds-card mb-3">
        <div class="ds-card-head">
          <div class="ds-card-title">Informations</div>
        </div>
        <div class="ds-card-body">
          <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
            <?php if ($user['email']): ?>
              <div><div class="t-meta">Email</div><div style="font-weight:500"><?= h($user['email']) ?></div></div>
            <?php endif ?>
            <?php if ($user['telephone']): ?>
              <div><div class="t-meta">Téléphone</div><div style="font-weight:500"><?= h($user['telephone']) ?></div></div>
            <?php endif ?>
            <div style="display:flex;gap:16px">
              <?php if ($user['date_entree']): ?>
                <div style="flex:1"><div class="t-meta">Date d'entrée</div><div style="font-weight:500" class="t-num"><?= date('d.m.Y', strtotime($user['date_entree'])) ?></div></div>
              <?php endif ?>
              <?php if ($user['experience_fonction_annees'] > 0): ?>
                <div style="flex:1"><div class="t-meta">Expérience</div><div style="font-weight:500" class="t-num"><?= number_format($user['experience_fonction_annees'], 1, ',', '') ?> ans</div></div>
              <?php endif ?>
            </div>
            <?php if ($user['cct']): ?>
              <div><div class="t-meta">CCT</div><div style="font-weight:500"><?= h($user['cct']) ?></div></div>
            <?php endif ?>
            <?php if ($user['reconnaissance_crs_annee']): ?>
              <div>
                <div class="t-meta">Reconnaissance CRS</div>
                <div style="font-weight:500;color:var(--ok)"><i class="bi bi-check-circle"></i> <?= h($user['reconnaissance_crs_annee']) ?></div>
              </div>
            <?php endif ?>
          </div>
        </div>
      </div>

      <!-- Formations récentes -->
      <div class="ds-card mb-3">
        <div class="ds-card-head">
          <div class="ds-card-title">Formations récentes</div>
        </div>
        <?php if (!$formations): ?>
          <div class="ds-empty"><i class="bi bi-mortarboard"></i><div class="ds-empty-title">Aucune formation</div></div>
        <?php else: ?>
          <div>
            <?php foreach ($formations as $f):
              $statutBadge = match($f['participant_statut']) {
                'valide'  => ['Validé',  'success'],
                'present' => ['Présent', 'info'],
                'absent'  => ['Absent',  'danger'],
                default   => ['Inscrit', 'warning'],
              };
            ?>
              <div style="padding:12px 20px;border-bottom:1px solid var(--line)">
                <div style="font-weight:500;color:var(--ink);font-size:13px"><?= h($f['titre']) ?></div>
                <div class="t-meta" style="margin-top:3px;display:flex;gap:8px;align-items:center">
                  <span class="t-num"><?= $f['date_debut'] ? date('d.m.Y', strtotime($f['date_debut'])) : '—' ?></span>
                  <?php if ($f['duree_heures']): ?>
                    · <span class="t-num"><?= h($f['duree_heures']) ?>h</span>
                  <?php endif ?>
                  <span class="badge text-bg-<?= $statutBadge[1] ?>" style="margin-left:auto"><?= h($statutBadge[0]) ?></span>
                </div>
              </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>

      <!-- Objectifs annuels -->
      <div class="ds-card">
        <div class="ds-card-head">
          <div class="ds-card-title">Objectifs <?= date('Y') ?></div>
        </div>
        <?php if (!$objectifs): ?>
          <div class="ds-empty"><i class="bi bi-flag"></i><div class="ds-empty-title">Aucun objectif défini</div></div>
        <?php else: ?>
          <div>
            <?php foreach ($objectifs as $o):
              $statutBadge = match($o['statut']) {
                'atteint'  => ['Atteint',  'success'],
                'en_cours' => ['En cours', 'info'],
                'reporte'  => ['Reporté',  'warning'],
                'abandonne'=> ['Abandonné','danger'],
                default    => ['À définir','secondary'],
              };
            ?>
              <div style="padding:12px 20px;border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:flex-start">
                <span class="badge text-bg-secondary" style="font-family:var(--font-mono);flex-shrink:0"><?= h($o['trimestre_cible']) ?></span>
                <div style="flex:1">
                  <div style="font-weight:500;font-size:13px"><?= h($o['libelle']) ?></div>
                  <?php if ($o['them_nom']): ?>
                    <div class="t-meta"><i class="bi bi-tag"></i> <?= h($o['them_nom']) ?></div>
                  <?php endif ?>
                </div>
                <span class="badge text-bg-<?= $statutBadge[1] ?>"><?= h($statutBadge[0]) ?></span>
              </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
    </div>
  </div>

</div>
