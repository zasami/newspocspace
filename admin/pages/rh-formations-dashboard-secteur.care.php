<?php
// ─── Détail secteur · liste collaborateurs + thématiques + formations ────────

$secteurs = [
    'soins'           => ['Soins',           '#1f6359', 'bi-heart-pulse'],
    'socio_culturel'  => ['Animation',       '#9268b3', 'bi-palette'],
    'hotellerie'      => ['Hôtellerie',      '#c97a2a', 'bi-cup-hot'],
    'maintenance'     => ['Maintenance',     '#5a82a8', 'bi-tools'],
    'administration'  => ['Administration',  '#3a6a8a', 'bi-briefcase'],
    'management'      => ['Management',      '#164a42', 'bi-stars'],
];

$secKey = $_GET['secteur'] ?? '';
if (!isset($secteurs[$secKey])) {
    echo '<div style="padding:40px;text-align:center;color:#6b8783"><i class="bi bi-question-circle" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i><strong>Secteur inconnu</strong><div style="margin-top:8px"><a href="?page=rh-formations-dashboard" data-page-link style="color:#1f6359">← Retour au tableau de bord</a></div></div>';
    return;
}
[$secLabel, $secColor, $secIcon] = $secteurs[$secKey];
$anneeActuelle = (int) date('Y');

// ── Collaborateurs du secteur ───────────────────────────────────
$collabs = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.date_entree,
            f.nom AS fonction_nom, f.code AS fonction_code,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id) AS nb_them_total,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'a_jour') AS nb_them_ok,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'haute') AS nb_haute,
            (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'moyenne') AS nb_moyenne,
            (SELECT COUNT(*) FROM formation_participants p JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut IN ('present','valide')
              AND YEAR(COALESCE(p.date_realisation, fo.date_debut)) = ?) AS nb_form_realisees,
            (SELECT COUNT(*) FROM formation_participants p JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut = 'inscrit' AND fo.date_debut > CURDATE()) AS nb_form_a_venir,
            (SELECT COALESCE(SUM(p.heures_realisees),0) FROM formation_participants p JOIN formations fo ON fo.id = p.formation_id
              WHERE p.user_id = u.id AND p.statut IN ('present','valide')
              AND YEAR(COALESCE(p.date_realisation, fo.date_debut)) = ?) AS heures_an
     FROM users u
     JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 AND f.secteur_fegems = ?
     ORDER BY u.nom ASC, u.prenom ASC",
    [$anneeActuelle, $anneeActuelle, $secKey]
);

// ── Thématiques attendues secteur ────────────────────────────────
$thematiques = Db::fetchAll(
    "SELECT t.id, t.code, t.nom, t.categorie, t.tag_affichage, t.duree_validite_mois,
            pa.niveau_requis, pa.requis,
            (SELECT COUNT(*) FROM competences_user cu
              JOIN users u ON u.id = cu.user_id
              JOIN fonctions f ON f.id = u.fonction_id
              WHERE cu.thematique_id = t.id AND u.is_active = 1 AND f.secteur_fegems = ?) AS nb_users_total,
            (SELECT COUNT(*) FROM competences_user cu
              JOIN users u ON u.id = cu.user_id
              JOIN fonctions f ON f.id = u.fonction_id
              WHERE cu.thematique_id = t.id AND u.is_active = 1 AND f.secteur_fegems = ?
                AND cu.priorite = 'a_jour') AS nb_users_ok,
            (SELECT COUNT(*) FROM competences_user cu
              JOIN users u ON u.id = cu.user_id
              JOIN fonctions f ON f.id = u.fonction_id
              WHERE cu.thematique_id = t.id AND u.is_active = 1 AND f.secteur_fegems = ?
                AND cu.priorite = 'haute') AS nb_haute,
            (SELECT AVG(cu.niveau_actuel) FROM competences_user cu
              JOIN users u ON u.id = cu.user_id
              JOIN fonctions f ON f.id = u.fonction_id
              WHERE cu.thematique_id = t.id AND u.is_active = 1 AND f.secteur_fegems = ?) AS niv_moyen
     FROM competences_profil_attendu pa
     JOIN competences_thematiques t ON t.id = pa.thematique_id
     WHERE pa.secteur = ? AND t.actif = 1
     ORDER BY pa.niveau_requis DESC, t.ordre ASC",
    [$secKey, $secKey, $secKey, $secKey, $secKey]
);

// ── Formations à venir / inscrites pour le secteur ───────────────
$sessionsFutures = Db::fetchAll(
    "SELECT fs.id AS session_id, fs.date_debut, fs.lieu, fs.modalite, fs.capacite_max,
            f.id AS formation_id, f.titre, f.duree_heures, f.cout_formation,
            (SELECT COUNT(*) FROM formation_participants p
              JOIN users u ON u.id = p.user_id
              JOIN fonctions fc ON fc.id = u.fonction_id
              WHERE p.formation_id = f.id AND fc.secteur_fegems = ?
                AND p.statut = 'inscrit' AND fs.date_debut > CURDATE()) AS nb_inscrits_secteur
     FROM formation_sessions fs
     JOIN formations f ON f.id = fs.formation_id
     WHERE fs.date_debut >= CURDATE() AND fs.statut IN ('ouverte','liste_attente')
     ORDER BY fs.date_debut ASC LIMIT 15",
    [$secKey]
);

// ── KPIs secteur ─────────────────────────────────────────────────
$nbCollabs = count($collabs);

$totalCu = array_sum(array_column($collabs, 'nb_them_total'));
$totalOk = array_sum(array_column($collabs, 'nb_them_ok'));
$conformitePct = $totalCu > 0 ? round(($totalOk / $totalCu) * 100) : 0;

$nbHauteTotal = array_sum(array_column($collabs, 'nb_haute'));
$nbMoyenneTotal = array_sum(array_column($collabs, 'nb_moyenne'));

$heuresTotales = array_sum(array_column($collabs, 'heures_an'));
$nbInscrits = array_sum(array_column($collabs, 'nb_form_a_venir'));
$nbRealisees = array_sum(array_column($collabs, 'nb_form_realisees'));

// Top 3 collaborateurs (heures formation année)
$topCollabs = $collabs;
usort($topCollabs, fn($a, $b) => $b['heures_an'] <=> $a['heures_an']);
$topCollabs = array_slice($topCollabs, 0, 5);

$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';
$fmtN = fn($n, $d=0) => number_format((float)$n, $d, ',', "'");
?>

<div class="fds-page">

  <!-- Breadcrumb -->
  <nav class="fds-crumb">
    <a href="?page=rh-formations-dashboard" data-page-link>Tableau de bord formation</a>
    <i class="bi bi-chevron-right"></i>
    <span>Secteur · <?= h($secLabel) ?></span>
  </nav>

  <!-- HERO -->
  <div class="fds-hero" style="--sec-color:<?= h($secColor) ?>">
    <div class="fds-hero-icon"><i class="bi <?= h($secIcon) ?>"></i></div>
    <div class="fds-hero-id">
      <div class="fds-hero-eyebrow">SECTEUR · <?= h(strtoupper($secLabel)) ?></div>
      <h1>Détail formation <?= h($secLabel) ?></h1>
      <div class="fds-hero-meta">
        <?= $nbCollabs ?> collaborateur<?= $nbCollabs > 1 ? 's' : '' ?> ·
        <?= count($thematiques) ?> thématique<?= count($thematiques) > 1 ? 's' : '' ?> attendue<?= count($thematiques) > 1 ? 's' : '' ?> ·
        <?= h($emsNom) ?>
      </div>
    </div>
    <div class="fds-hero-actions">
      <button class="fds-btn fds-btn-on-dark" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      <button class="fds-btn fds-btn-on-dark" id="exportCsvBtn"><i class="bi bi-download"></i> Export CSV</button>
      <a href="?page=rh-formations-fegems" class="fds-btn fds-btn-primary" data-page-link><i class="bi bi-plus-lg"></i> Inscrire FEGEMS</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="fds-kpi-row">
    <div class="fds-kpi">
      <div class="fds-kpi-lbl">Conformité</div>
      <div class="fds-kpi-val fds-<?= $conformitePct >= 80 ? 'ok' : ($conformitePct >= 60 ? 'warn' : 'bad') ?>"><?= $conformitePct ?>%</div>
      <div class="fds-kpi-sub"><?= $totalOk ?>/<?= $totalCu ?> compétences à jour</div>
    </div>
    <div class="fds-kpi">
      <div class="fds-kpi-lbl">Écarts critiques</div>
      <div class="fds-kpi-val fds-<?= $nbHauteTotal > 0 ? 'bad' : 'ok' ?>"><?= $nbHauteTotal ?></div>
      <div class="fds-kpi-sub">+ <?= $nbMoyenneTotal ?> écarts légers</div>
    </div>
    <div class="fds-kpi">
      <div class="fds-kpi-lbl">Heures <?= $anneeActuelle ?></div>
      <div class="fds-kpi-val"><?= $fmtN($heuresTotales) ?>h</div>
      <div class="fds-kpi-sub"><?= $fmtN($nbCollabs > 0 ? $heuresTotales / $nbCollabs : 0, 1) ?> h/collab.</div>
    </div>
    <div class="fds-kpi">
      <div class="fds-kpi-lbl">Formations</div>
      <div class="fds-kpi-val"><?= $nbRealisees ?></div>
      <div class="fds-kpi-sub"><?= $nbInscrits ?> inscriptions à venir</div>
    </div>
  </div>

  <!-- TABS -->
  <div class="fds-tabs">
    <button class="fds-tab on" data-tab="collabs">
      <i class="bi bi-people"></i> Collaborateurs <span class="count"><?= $nbCollabs ?></span>
    </button>
    <button class="fds-tab" data-tab="thems">
      <i class="bi bi-list-check"></i> Thématiques <span class="count"><?= count($thematiques) ?></span>
    </button>
    <button class="fds-tab" data-tab="formations">
      <i class="bi bi-mortarboard"></i> Sessions à venir <span class="count"><?= count($sessionsFutures) ?></span>
    </button>
    <button class="fds-tab" data-tab="apercu">
      <i class="bi bi-graph-up"></i> Aperçu visuel
    </button>
  </div>

  <!-- ─── Tab Collaborateurs ─── -->
  <div class="fds-pane on" data-pane="collabs">
    <div class="fds-card">
      <div class="fds-card-head">
        <h3>Liste des collaborateurs · <?= h($secLabel) ?></h3>
        <div class="meta">Cliquez sur un nom pour voir la fiche compétences</div>
        <button class="fds-btn-mini" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      </div>
      <?php if ($collabs): ?>
        <table class="fds-table">
          <thead>
            <tr>
              <th>Nom · prénom</th>
              <th>Fonction</th>
              <th class="num">Conformité</th>
              <th class="num">Écarts</th>
              <th class="num">Réalisées <?= $anneeActuelle ?></th>
              <th class="num">À venir</th>
              <th class="num">Heures</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($collabs as $c):
              $pct = $c['nb_them_total'] > 0 ? round(($c['nb_them_ok'] / $c['nb_them_total']) * 100) : 0;
              $cls = $pct >= 80 ? 'ok' : ($pct >= 60 ? 'warn' : 'bad');
            ?>
              <tr>
                <td>
                  <div class="fds-collab-cell">
                    <div class="fds-avatar" style="background:<?= h($secColor) ?>">
                      <?= h(strtoupper(mb_substr($c['prenom'] ?? '', 0, 1) . mb_substr($c['nom'] ?? '', 0, 1))) ?>
                    </div>
                    <a href="?page=rh-collab-competences&id=<?= h($c['id']) ?>" data-page-link class="fds-collab-link">
                      <?= h($c['nom'] . ' ' . $c['prenom']) ?>
                    </a>
                  </div>
                </td>
                <td><?= h($c['fonction_nom'] ?: '—') ?></td>
                <td class="num">
                  <div class="fds-conf">
                    <span class="fds-conf-val fds-<?= $cls ?>"><?= $pct ?>%</span>
                    <div class="fds-mini-bar"><span class="fds-bar-<?= $cls ?>" style="width:<?= $pct ?>%"></span></div>
                  </div>
                </td>
                <td class="num">
                  <?php if ($c['nb_haute'] > 0): ?>
                    <span class="fds-pill fds-pill-bad"><?= $c['nb_haute'] ?> haute</span>
                  <?php endif ?>
                  <?php if ($c['nb_moyenne'] > 0): ?>
                    <span class="fds-pill fds-pill-warn"><?= $c['nb_moyenne'] ?> légère<?= $c['nb_moyenne'] > 1 ? 's' : '' ?></span>
                  <?php endif ?>
                  <?php if (!$c['nb_haute'] && !$c['nb_moyenne']): ?>
                    <span class="fds-pill fds-pill-ok">À jour</span>
                  <?php endif ?>
                </td>
                <td class="num t-num"><?= $c['nb_form_realisees'] ?></td>
                <td class="num t-num"><?= $c['nb_form_a_venir'] ?></td>
                <td class="num t-num"><?= $fmtN($c['heures_an']) ?>h</td>
                <td class="num">
                  <a href="?page=rh-collab-competences&id=<?= h($c['id']) ?>" data-page-link class="fds-btn-mini"><i class="bi bi-arrow-right"></i></a>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="fds-empty">
          <i class="bi bi-people"></i>
          <strong>Aucun collaborateur dans ce secteur</strong>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- ─── Tab Thématiques ─── -->
  <div class="fds-pane" data-pane="thems" hidden>
    <div class="fds-card">
      <div class="fds-card-head">
        <h3>Thématiques attendues · <?= h($secLabel) ?></h3>
        <div class="meta">Profil d'équipe attendu selon référentiel FEGEMS</div>
        <button class="fds-btn-mini" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      </div>
      <?php if ($thematiques): ?>
        <table class="fds-table">
          <thead>
            <tr>
              <th>Thématique</th>
              <th class="num">Niveau requis</th>
              <th class="num">Niv. moyen équipe</th>
              <th>Couverture</th>
              <th class="num">Écarts critiques</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($thematiques as $t):
              $cov = $t['nb_users_total'] > 0 ? round(($t['nb_users_ok'] / $t['nb_users_total']) * 100) : 0;
              $cls = $cov >= 80 ? 'ok' : ($cov >= 50 ? 'warn' : 'bad');
              $isOblig = stripos($t['tag_affichage'] ?? '', 'obligat') !== false || $t['categorie'] === 'fegems_base';
            ?>
              <tr>
                <td>
                  <div class="fds-them-cell">
                    <strong><?= h($t['nom']) ?></strong>
                    <?php if ($isOblig): ?>
                      <span class="fds-tag fds-tag-oblig">Obligatoire</span>
                    <?php elseif ($t['categorie'] === 'strategique'): ?>
                      <span class="fds-tag fds-tag-strat">Stratégique</span>
                    <?php endif ?>
                    <div class="meta"><?= h($t['tag_affichage'] ?: $t['categorie']) ?><?= $t['duree_validite_mois'] ? ' · validité ' . $t['duree_validite_mois'] . ' mois' : '' ?></div>
                  </div>
                </td>
                <td class="num t-num"><?= $t['niveau_requis'] ? $t['niveau_requis'] . '/4' : '—' ?></td>
                <td class="num t-num"><?= $t['niv_moyen'] ? $fmtN($t['niv_moyen'], 1) . '/4' : '—' ?></td>
                <td>
                  <div class="fds-cov">
                    <span class="t-num fds-<?= $cls ?>" style="font-weight:600"><?= $cov ?>%</span>
                    <div class="fds-mini-bar"><span class="fds-bar-<?= $cls ?>" style="width:<?= $cov ?>%"></span></div>
                    <span class="fds-cov-detail"><?= $t['nb_users_ok'] ?>/<?= $t['nb_users_total'] ?> à jour</span>
                  </div>
                </td>
                <td class="num">
                  <?php if ($t['nb_haute'] > 0): ?>
                    <span class="fds-pill fds-pill-bad"><?= $t['nb_haute'] ?></span>
                  <?php else: ?>
                    <span class="t-meta">—</span>
                  <?php endif ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="fds-empty">
          <i class="bi bi-list-check"></i>
          <strong>Aucune thématique attendue pour ce secteur</strong>
          <div class="fds-meta">Définir le profil d'équipe dans <a href="?page=rh-formations-profil" data-page-link>Profil d'équipe attendu</a>.</div>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- ─── Tab Sessions à venir ─── -->
  <div class="fds-pane" data-pane="formations" hidden>
    <div class="fds-card">
      <div class="fds-card-head">
        <h3>Sessions à venir · disponibles pour ce secteur</h3>
        <div class="meta">Sessions ouvertes du catalogue Fegems</div>
        <button class="fds-btn-mini" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      </div>
      <?php if ($sessionsFutures): ?>
        <table class="fds-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Titre</th>
              <th>Lieu · modalité</th>
              <th class="num">Durée</th>
              <th class="num">Coût</th>
              <th class="num">Inscrits secteur</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sessionsFutures as $s):
              $dt = $s['date_debut'] ? new DateTime($s['date_debut']) : null;
            ?>
              <tr>
                <td class="t-num"><?= $dt ? $dt->format('d.m.Y') : '—' ?></td>
                <td>
                  <strong><?= h($s['titre']) ?></strong>
                </td>
                <td>
                  <?= h($s['lieu'] ?: '—') ?>
                  <span class="t-meta">· <?= h($s['modalite'] ?: 'présentiel') ?></span>
                </td>
                <td class="num t-num"><?= $s['duree_heures'] ? rtrim(rtrim(number_format($s['duree_heures'], 1, '.', ''), '0'), '.') . 'h' : '—' ?></td>
                <td class="num t-num"><?= $s['cout_formation'] > 0 ? 'CHF ' . $fmtN($s['cout_formation']) : '<span class="t-meta">cotisation</span>' ?></td>
                <td class="num">
                  <?php if ($s['nb_inscrits_secteur'] > 0): ?>
                    <span class="fds-pill fds-pill-info"><?= $s['nb_inscrits_secteur'] ?></span>
                  <?php else: ?>
                    <span class="t-meta">0</span>
                  <?php endif ?>
                </td>
                <td>
                  <a href="?page=rh-formations-fegems" data-page-link class="fds-btn-mini">
                    <i class="bi bi-plus-lg"></i> Inscrire
                  </a>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="fds-empty">
          <i class="bi bi-calendar-x"></i>
          <strong>Aucune session ouverte</strong>
          <div class="fds-meta">Resynchronisez le catalogue Fegems pour voir les nouvelles sessions.</div>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- ─── Tab Aperçu visuel ─── -->
  <div class="fds-pane" data-pane="apercu" hidden>
    <div class="fds-aper-grid">
      <!-- Donut conformité -->
      <div class="fds-card">
        <div class="fds-card-head">
          <h3>Répartition compétences</h3>
          <div class="meta">à jour vs écarts</div>
          <button class="fds-btn-mini" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
        <div class="fds-card-body" style="display:flex;justify-content:center;padding:30px">
          <?php
          $totalThem = $totalOk + $nbHauteTotal + $nbMoyenneTotal;
          $totalThem = max(1, $totalThem);
          $angOk = ($totalOk / $totalThem) * 360;
          $angHaute = ($nbHauteTotal / $totalThem) * 360;
          $angMoy = ($nbMoyenneTotal / $totalThem) * 360;
          $cx = 110; $cy = 110; $r = 80; $rIn = 55;

          function donutSlice($cx, $cy, $r, $rIn, $startA, $endA) {
              $startRad = deg2rad($startA - 90);
              $endRad = deg2rad($endA - 90);
              $largeArc = ($endA - $startA) > 180 ? 1 : 0;
              $x1 = $cx + $r * cos($startRad); $y1 = $cy + $r * sin($startRad);
              $x2 = $cx + $r * cos($endRad);   $y2 = $cy + $r * sin($endRad);
              $x3 = $cx + $rIn * cos($endRad); $y3 = $cy + $rIn * sin($endRad);
              $x4 = $cx + $rIn * cos($startRad); $y4 = $cy + $rIn * sin($startRad);
              return "M $x1 $y1 A $r $r 0 $largeArc 1 $x2 $y2 L $x3 $y3 A $rIn $rIn 0 $largeArc 0 $x4 $y4 Z";
          }
          $a = 0;
          ?>
          <svg viewBox="0 0 220 220" width="220" height="220">
            <?php if ($totalOk > 0): ?>
              <path d="<?= donutSlice($cx,$cy,$r,$rIn,$a,$a+$angOk) ?>" fill="#3d8b6b"/>
              <?php $a += $angOk; ?>
            <?php endif ?>
            <?php if ($nbMoyenneTotal > 0): ?>
              <path d="<?= donutSlice($cx,$cy,$r,$rIn,$a,$a+$angMoy) ?>" fill="#c97a2a"/>
              <?php $a += $angMoy; ?>
            <?php endif ?>
            <?php if ($nbHauteTotal > 0): ?>
              <path d="<?= donutSlice($cx,$cy,$r,$rIn,$a,$a+$angHaute) ?>" fill="#b8443a"/>
            <?php endif ?>
            <text x="110" y="105" text-anchor="middle" font-family="Fraunces" font-size="32" font-weight="600" fill="#0d2a26"><?= $conformitePct ?>%</text>
            <text x="110" y="128" text-anchor="middle" font-family="Outfit" font-size="11" fill="#6b8783" letter-spacing="1" text-transform="uppercase">Conformité</text>
          </svg>
        </div>
        <div class="fds-card-body" style="padding-top:0">
          <div class="fds-legend">
            <div class="fds-leg-row"><span class="fds-leg-dot" style="background:#3d8b6b"></span> À jour <span class="t-num"><?= $totalOk ?></span></div>
            <div class="fds-leg-row"><span class="fds-leg-dot" style="background:#c97a2a"></span> Écart léger <span class="t-num"><?= $nbMoyenneTotal ?></span></div>
            <div class="fds-leg-row"><span class="fds-leg-dot" style="background:#b8443a"></span> Écart critique <span class="t-num"><?= $nbHauteTotal ?></span></div>
          </div>
        </div>
      </div>

      <!-- Top contributeurs -->
      <div class="fds-card">
        <div class="fds-card-head">
          <h3>Top heures formation <?= $anneeActuelle ?></h3>
          <div class="meta">5 collaborateurs les plus formés</div>
          <button class="fds-btn-mini" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
        <?php if ($topCollabs && $topCollabs[0]['heures_an'] > 0): ?>
          <?php
            $heuresValues = array_column($topCollabs, 'heures_an');
            $maxH = $heuresValues ? max((float) max($heuresValues), 1.0) : 1.0;
          ?>
          <div class="fds-top-list">
            <?php foreach ($topCollabs as $c): if ($c['heures_an'] <= 0) continue; ?>
              <div class="fds-top-row">
                <div class="fds-avatar" style="background:<?= h($secColor) ?>;width:30px;height:30px;font-size:11px">
                  <?= h(strtoupper(mb_substr($c['prenom'] ?? '', 0, 1) . mb_substr($c['nom'] ?? '', 0, 1))) ?>
                </div>
                <div class="fds-top-name"><?= h($c['nom'] . ' ' . $c['prenom']) ?></div>
                <div class="fds-top-bar-wrap">
                  <div class="fds-top-bar"><span style="width:<?= ($c['heures_an'] / $maxH) * 100 ?>%;background:<?= h($secColor) ?>"></span></div>
                </div>
                <div class="fds-top-h t-num"><?= $fmtN($c['heures_an']) ?>h</div>
              </div>
            <?php endforeach ?>
          </div>
        <?php else: ?>
          <div class="fds-empty"><i class="bi bi-bar-chart"></i><strong>Aucune heure enregistrée</strong></div>
        <?php endif ?>
      </div>
    </div>
  </div>

</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Détail secteur formation · scopé sous .fds-page
═══════════════════════════════════════════════════════════════════ */
.fds-page {
  --fds-bg: #f5f7f5;
  --fds-surface: #fff;
  --fds-surface-2: #fafbfa;
  --fds-ink: #0d2a26;
  --fds-ink-2: #324e4a;
  --fds-muted: #6b8783;
  --fds-line: #e3ebe8;
  --fds-line-2: #d4ddda;
  --fds-teal-50: #ecf5f3;
  --fds-teal-300: #7ab5ab;
  --fds-teal-600: #1f6359;
  --fds-teal-700: #164a42;
  --fds-warn: #c97a2a;
  --fds-warn-bg: #fbf0e1;
  --fds-danger: #b8443a;
  --fds-danger-bg: #f7e3e0;
  --fds-ok: #3d8b6b;
  --fds-ok-bg: #e3f0ea;
  --fds-info: #3a6a8a;
  --fds-info-bg: #e2ecf2;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 14px; color: var(--fds-ink); line-height: 1.45;
  padding: 28px 32px 60px; max-width: 1600px; margin: 0 auto;
}
.fds-page * { box-sizing: border-box; }

.fds-crumb {
  font-size: 12.5px; color: var(--fds-muted);
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 16px;
}
.fds-crumb a { color: var(--fds-teal-600); text-decoration: none; }
.fds-crumb a:hover { text-decoration: underline; }
.fds-crumb i { font-size: 9px; opacity: .5; }

/* ═════ HERO ═════ */
.fds-hero {
  background: linear-gradient(135deg, var(--sec-color) 0%, color-mix(in srgb, var(--sec-color) 70%, #000) 100%);
  border-radius: 16px; padding: 24px 28px; color: #fff;
  display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
  margin-bottom: 22px;
  box-shadow: 0 8px 24px -8px rgba(13,42,38,.18);
}
.fds-hero-icon {
  width: 64px; height: 64px; border-radius: 16px;
  background: rgba(255,255,255,.15);
  display: grid; place-items: center;
  font-size: 30px; flex-shrink: 0;
  border: 1px solid rgba(255,255,255,.18);
}
.fds-hero-id { flex: 1; min-width: 280px; }
.fds-hero-eyebrow {
  font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
  color: rgba(255,255,255,.75); font-weight: 600;
}
.fds-hero h1 {
  font-family: 'Fraunces', serif; font-size: 30px; font-weight: 500;
  letter-spacing: -.02em; line-height: 1.1; color: #fff; margin: 4px 0 0;
}
.fds-hero-meta { font-size: 13px; color: rgba(255,255,255,.85); margin-top: 8px; }
.fds-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.fds-page .fds-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px; font-family: inherit;
  font-size: 13px; font-weight: 500;
  border: 1px solid var(--fds-line); background: var(--fds-surface);
  color: var(--fds-ink-2); cursor: pointer; transition: .15s; text-decoration: none;
}
.fds-page .fds-btn:hover { border-color: var(--fds-teal-300); color: var(--fds-teal-600); }
.fds-page .fds-btn-on-dark {
  background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); color: #fff;
}
.fds-page .fds-btn-on-dark:hover {
  background: rgba(255,255,255,.16); border-color: rgba(255,255,255,.28); color: #fff;
}
.fds-page .fds-btn-primary {
  background: #fff; color: var(--sec-color, var(--fds-teal-700)); border-color: #fff;
  font-weight: 600;
}

/* ═════ KPI ROW ═════ */
.fds-kpi-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
  margin-bottom: 22px;
}
.fds-kpi {
  background: var(--fds-surface); border: 1px solid var(--fds-line);
  border-radius: 14px; padding: 18px 20px;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fds-kpi-lbl {
  font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fds-muted); font-weight: 600;
}
.fds-kpi-val {
  font-family: 'Fraunces', serif; font-size: 32px; font-weight: 600;
  letter-spacing: -.02em; line-height: 1.05; color: var(--fds-ink);
  margin: 6px 0 4px;
}
.fds-kpi-val.fds-ok   { color: var(--fds-ok); }
.fds-kpi-val.fds-warn { color: var(--fds-warn); }
.fds-kpi-val.fds-bad  { color: var(--fds-danger); }
.fds-kpi-sub { font-size: 12px; color: var(--fds-muted); }

/* ═════ TABS ═════ */
.fds-tabs {
  display: flex; gap: 2px; border-bottom: 1px solid var(--fds-line);
  margin-bottom: 16px;
}
.fds-page .fds-tab {
  padding: 11px 18px; font-family: inherit; font-size: 13.5px; font-weight: 500;
  background: transparent; border: 0; color: var(--fds-muted); cursor: pointer;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
  display: inline-flex; align-items: center; gap: 8px; transition: .15s;
}
.fds-page .fds-tab:hover { color: var(--fds-ink-2); }
.fds-page .fds-tab.on {
  color: var(--fds-teal-600); border-bottom-color: var(--fds-teal-600);
}
.fds-page .fds-tab .count {
  font-family: 'JetBrains Mono', monospace; font-size: 10.5px;
  background: var(--fds-teal-50); color: var(--fds-teal-700);
  padding: 1px 6px; border-radius: 99px; font-weight: 600;
}
.fds-page .fds-tab.on .count { background: var(--fds-teal-600); color: #fff; }
.fds-pane:not(.on) { display: none; }

/* ═════ CARD ═════ */
.fds-card {
  background: var(--fds-surface); border: 1px solid var(--fds-line);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fds-card-head {
  padding: 16px 22px; border-bottom: 1px solid var(--fds-line);
  display: flex; align-items: center; gap: 14px;
}
.fds-card-head h3 {
  font-family: 'Fraunces', serif; font-size: 17px; font-weight: 600;
  letter-spacing: -.01em; color: var(--fds-ink); margin: 0;
}
.fds-card-head .meta { font-size: 12px; color: var(--fds-muted); flex: 1; }
.fds-card-body { padding: 22px; }

.fds-page .fds-btn-mini {
  padding: 5px 10px; font-family: inherit; font-size: 11.5px;
  border-radius: 5px; border: 1px solid var(--fds-line);
  background: #fff; color: var(--fds-ink-2); cursor: pointer; font-weight: 500;
  transition: .15s; display: inline-flex; align-items: center; gap: 4px;
  text-decoration: none; flex-shrink: 0;
}
.fds-page .fds-btn-mini:hover {
  border-color: var(--fds-teal-500, var(--fds-teal-600));
  color: var(--fds-teal-600); background: var(--fds-teal-50);
}

/* ═════ TABLE ═════ */
.fds-page table.fds-table {
  width: 100%; border-collapse: separate; border-spacing: 0;
}
.fds-table thead th {
  font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em;
  color: var(--fds-muted); font-weight: 700; text-align: left;
  padding: 10px 14px; background: var(--fds-surface-2);
  border-bottom: 1px solid var(--fds-line);
}
.fds-table thead th.num { text-align: right; }
.fds-table tbody td {
  padding: 12px 14px; border-bottom: 1px solid var(--fds-line);
  vertical-align: middle; font-size: 13px;
}
.fds-table tbody td.num { text-align: right; font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
.fds-table tbody tr:last-child td { border-bottom: 0; }
.fds-table tbody tr:hover { background: var(--fds-surface-2); }

.fds-collab-cell { display: flex; align-items: center; gap: 10px; }
.fds-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  display: grid; place-items: center; color: #fff;
  font-weight: 600; font-size: 12px; flex-shrink: 0;
}
.fds-page .fds-collab-link {
  color: var(--fds-ink); font-weight: 500; text-decoration: none;
}
.fds-page .fds-collab-link:hover { color: var(--fds-teal-600); }

.fds-them-cell { display: flex; flex-direction: column; gap: 2px; }
.fds-them-cell strong { font-size: 13.5px; font-weight: 500; color: var(--fds-ink); }
.fds-them-cell .meta { font-size: 11px; color: var(--fds-muted); }

.fds-conf, .fds-cov {
  display: flex; flex-direction: column; align-items: flex-end; gap: 4px;
  min-width: 110px;
}
.fds-cov {
  align-items: stretch;
}
.fds-conf-val {
  font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 600;
}
.fds-conf-val.fds-ok   { color: var(--fds-ok); }
.fds-conf-val.fds-warn { color: var(--fds-warn); }
.fds-conf-val.fds-bad  { color: var(--fds-danger); }
.fds-cov-detail { font-size: 10px; color: var(--fds-muted); }
.fds-cov .t-num.fds-ok { color: var(--fds-ok); }
.fds-cov .t-num.fds-warn { color: var(--fds-warn); }
.fds-cov .t-num.fds-bad { color: var(--fds-danger); }
.fds-mini-bar {
  height: 4px; background: var(--fds-line); border-radius: 99px;
  overflow: hidden; width: 100px; margin-left: auto;
}
.fds-mini-bar span { display: block; height: 100%; border-radius: 99px; }
.fds-bar-ok   { background: linear-gradient(90deg, var(--fds-ok), #5cad8b); }
.fds-bar-warn { background: linear-gradient(90deg, var(--fds-warn), #e0a85a); }
.fds-bar-bad  { background: linear-gradient(90deg, var(--fds-danger), #cd6b62); }

.fds-pill {
  display: inline-block; font-size: 10px; padding: 2px 8px; border-radius: 4px;
  font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
  margin-right: 4px;
}
.fds-pill-ok   { background: var(--fds-ok-bg); color: var(--fds-ok); }
.fds-pill-warn { background: var(--fds-warn-bg); color: var(--fds-warn); }
.fds-pill-bad  { background: var(--fds-danger-bg); color: var(--fds-danger); }
.fds-pill-info { background: var(--fds-info-bg); color: var(--fds-info); }

.fds-tag {
  display: inline-block; font-size: 9.5px; padding: 2px 7px; border-radius: 4px;
  font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
}
.fds-tag-oblig { background: var(--fds-danger-bg); color: var(--fds-danger); }
.fds-tag-strat { background: var(--fds-info-bg); color: var(--fds-info); }

.fds-empty {
  padding: 60px 20px; text-align: center; color: var(--fds-muted);
}
.fds-empty i { font-size: 40px; opacity: .25; display: block; margin-bottom: 12px; }
.fds-empty .fds-meta { font-size: 12px; margin-top: 8px; }
.fds-empty .fds-meta a { color: var(--fds-teal-600); }

/* ═════ APERÇU GRID ═════ */
.fds-aper-grid { display: grid; grid-template-columns: 1fr 1.3fr; gap: 16px; }

.fds-legend { display: flex; flex-direction: column; gap: 8px; }
.fds-leg-row {
  display: flex; align-items: center; gap: 10px; font-size: 13px;
  padding: 6px 0; border-bottom: 1px dashed var(--fds-line);
}
.fds-leg-row:last-child { border-bottom: 0; }
.fds-leg-dot { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
.fds-leg-row .t-num { margin-left: auto; font-weight: 600; color: var(--fds-ink); }

.fds-top-list { padding: 8px 22px 18px; }
.fds-top-row {
  display: grid; grid-template-columns: auto 1fr auto auto;
  align-items: center; gap: 12px; padding: 8px 0;
  border-bottom: 1px solid var(--fds-line);
}
.fds-top-row:last-child { border-bottom: 0; }
.fds-top-name { font-size: 13px; color: var(--fds-ink); font-weight: 500; }
.fds-top-bar-wrap { width: 180px; }
.fds-top-bar {
  height: 6px; background: var(--fds-line); border-radius: 99px; overflow: hidden;
}
.fds-top-bar span { display: block; height: 100%; border-radius: 99px; }
.fds-top-h {
  font-family: 'JetBrains Mono', monospace; font-weight: 600; color: var(--fds-ink);
  min-width: 60px; text-align: right;
}

.t-num { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
.t-meta { font-size: 11px; color: var(--fds-muted); }

@media (max-width: 1280px) {
  .fds-kpi-row { grid-template-columns: repeat(2, 1fr); }
  .fds-aper-grid { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .fds-tabs { overflow-x: auto; }
  .fds-table { font-size: 11.5px; }
}

/* Print */
@media print {
  .fds-hero-actions, .fds-tabs, .fds-btn-mini, .fds-crumb { display: none !important; }
  .fds-page { padding: 12px; max-width: 100%; }
  .fds-pane { display: block !important; page-break-before: auto; }
  .fds-pane[hidden] { display: block !important; }
  .fds-card { break-inside: avoid; margin-bottom: 16px; box-shadow: none; }
  .fds-table tbody tr { break-inside: avoid; }
  .fds-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-shadow: none; }
}
</style>

<script<?= nonce() ?>>
(function () {
  document.querySelectorAll('.fds-tab').forEach(t => {
    t.addEventListener('click', () => {
      const tab = t.dataset.tab;
      document.querySelectorAll('.fds-tab').forEach(x => x.classList.toggle('on', x === t));
      document.querySelectorAll('.fds-pane').forEach(p => {
        const isActive = p.dataset.pane === tab;
        p.classList.toggle('on', isActive);
        p.hidden = !isActive;
      });
    });
  });

  document.getElementById('exportCsvBtn')?.addEventListener('click', () => {
    if (typeof showToast === 'function') showToast('Export CSV en cours de développement', 'info');
  });
})();
</script>
