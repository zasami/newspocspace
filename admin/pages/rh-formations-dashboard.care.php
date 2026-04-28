<?php
// ─── Tableau de bord Formation · vue par secteur ─────────────────────────────

$secteurs = [
    'soins'           => ['Soins',           '#1f6359', 'bi-heart-pulse',     'soins'],
    'socio_culturel'  => ['Animation',       '#9268b3', 'bi-palette',         'anim'],
    'hotellerie'      => ['Hôtellerie',      '#c97a2a', 'bi-cup-hot',         'hot'],
    'maintenance'     => ['Maintenance',     '#5a82a8', 'bi-tools',           'tech'],
    'administration'  => ['Administration',  '#3a6a8a', 'bi-briefcase',       'admin'],
    'management'      => ['Management',      '#164a42', 'bi-stars',           'mgmt'],
];

// Année courante
$anneeActuelle = (int) date('Y');

// ── KPIs globaux ────────────────────────────────────────────────
$nbCollabsTotal = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");

$globaleAJour   = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite = 'a_jour'");
$globaleTotal   = (int) Db::getOne("SELECT COUNT(*) FROM competences_user");
$conformiteGlob = $globaleTotal > 0 ? round(($globaleAJour / $globaleTotal) * 100) : 0;

$nbEcartsHaute = (int) Db::getOne("SELECT COUNT(*) FROM competences_user WHERE priorite = 'haute'");

$heuresGlobAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = ?", [$anneeActuelle]
);
$budgetGlobAnnee = (float) Db::getOne(
    "SELECT COALESCE(SUM(f.cout_formation), 0)
     FROM formation_participants p JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('inscrit','present','valide')
       AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = ?", [$anneeActuelle]
);

// ── Stats par secteur ───────────────────────────────────────────
$secteurStats = [];
foreach ($secteurs as $key => $meta) {
    $nbCollabs = (int) Db::getOne(
        "SELECT COUNT(*) FROM users u JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ?", [$key]
    );

    $nbThemAttendues = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_profil_attendu WHERE secteur = ?", [$key]
    );

    $aJour = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ? AND cu.priorite = 'a_jour'", [$key]
    );
    $totalCu = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ?", [$key]
    );
    $conformite = $totalCu > 0 ? round(($aJour / $totalCu) * 100) : 0;

    // Obligatoires couvertes pour le secteur
    $aJourOblig = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN fonctions f ON f.id = u.fonction_id
         JOIN competences_thematiques t ON t.id = cu.thematique_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ? AND cu.priorite = 'a_jour'
           AND (t.tag_affichage LIKE '%obligat%' OR t.categorie = 'fegems_base')", [$key]
    );
    $totalOblig = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN fonctions f ON f.id = u.fonction_id
         JOIN competences_thematiques t ON t.id = cu.thematique_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ?
           AND (t.tag_affichage LIKE '%obligat%' OR t.categorie = 'fegems_base')", [$key]
    );
    $obligPct = $totalOblig > 0 ? round(($aJourOblig / $totalOblig) * 100) : 0;

    $nbHaute = (int) Db::getOne(
        "SELECT COUNT(*) FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND f.secteur_fegems = ? AND cu.priorite = 'haute'", [$key]
    );

    $heuresAn = (float) Db::getOne(
        "SELECT COALESCE(SUM(p.heures_realisees), 0)
         FROM formation_participants p
         JOIN users u ON u.id = p.user_id
         JOIN fonctions fc ON fc.id = u.fonction_id
         JOIN formations f ON f.id = p.formation_id
         WHERE u.is_active = 1 AND fc.secteur_fegems = ?
           AND p.statut IN ('present','valide')
           AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = ?", [$key, $anneeActuelle]
    );

    $nbInscritsAVenir = (int) Db::getOne(
        "SELECT COUNT(*) FROM formation_participants p
         JOIN users u ON u.id = p.user_id
         JOIN fonctions fc ON fc.id = u.fonction_id
         JOIN formations f ON f.id = p.formation_id
         WHERE u.is_active = 1 AND fc.secteur_fegems = ?
           AND p.statut = 'inscrit' AND f.date_debut > CURDATE()", [$key]
    );

    $secteurStats[$key] = [
        'meta' => $meta,
        'nb_collabs' => $nbCollabs,
        'nb_them_attendues' => $nbThemAttendues,
        'conformite_pct' => $conformite,
        'oblig_pct' => $obligPct,
        'nb_haute' => $nbHaute,
        'heures_an' => $heuresAn,
        'nb_inscrits' => $nbInscritsAVenir,
        'nb_a_jour' => $aJour,
        'nb_total_cu' => $totalCu,
    ];
}

// Activité récente
$activiteRecente = Db::fetchAll(
    "SELECT u.prenom, u.nom, u.photo, f.titre AS formation_titre, fc.secteur_fegems,
            p.statut AS participant_statut, p.date_realisation,
            COALESCE(p.date_realisation, p.created_at) AS quand
     FROM formation_participants p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN fonctions fc ON fc.id = u.fonction_id
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide','inscrit')
     ORDER BY COALESCE(p.date_realisation, p.created_at) DESC LIMIT 8"
);

// EMS info
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS';

// Helper inline
$fmtN = fn($n, $d=0) => number_format((float)$n, $d, ',', "'");
?>

<div class="fdb-page">

  <!-- Page head -->
  <div class="fdb-head">
    <div>
      <h1 class="fdb-h1">Tableau de bord formation</h1>
      <div class="fdb-sub">
        Vue d'ensemble par secteur · <strong><?= h($emsNom) ?></strong> ·
        <?= $fmtN($nbCollabsTotal) ?> collaborateurs · année <?= h($anneeActuelle) ?>
      </div>
    </div>
    <div class="fdb-actions">
      <button class="fdb-btn" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
      <a href="?page=rh-formations-pluriannuel" class="fdb-btn" data-page-link>
        <i class="bi bi-graph-up-arrow"></i> Plan pluriannuel
      </a>
      <a href="?page=rh-formations-fegems" class="fdb-btn fdb-btn-primary" data-page-link>
        <i class="bi bi-cloud-arrow-up"></i> Inscriptions FEGEMS
      </a>
    </div>
  </div>

  <!-- KPI Headline globaux -->
  <div class="fdb-kpi-row">
    <div class="fdb-kpi">
      <div class="fdb-kpi-icon" style="background:var(--fdb-teal-50);color:var(--fdb-teal-600)"><i class="bi bi-people"></i></div>
      <div>
        <div class="fdb-kpi-lbl">Collaborateurs</div>
        <div class="fdb-kpi-val"><?= $fmtN($nbCollabsTotal) ?></div>
      </div>
    </div>
    <div class="fdb-kpi">
      <div class="fdb-kpi-icon" style="background:var(--fdb-ok-bg);color:var(--fdb-ok)"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="fdb-kpi-lbl">Conformité globale</div>
        <div class="fdb-kpi-val"><?= $conformiteGlob ?>%</div>
      </div>
    </div>
    <div class="fdb-kpi">
      <div class="fdb-kpi-icon" style="background:var(--fdb-danger-bg);color:var(--fdb-danger)"><i class="bi bi-exclamation-triangle"></i></div>
      <div>
        <div class="fdb-kpi-lbl">Écarts critiques</div>
        <div class="fdb-kpi-val"><?= $fmtN($nbEcartsHaute) ?></div>
      </div>
    </div>
    <div class="fdb-kpi">
      <div class="fdb-kpi-icon" style="background:var(--fdb-info-bg);color:var(--fdb-info)"><i class="bi bi-clock-history"></i></div>
      <div>
        <div class="fdb-kpi-lbl">Heures formation <?= $anneeActuelle ?></div>
        <div class="fdb-kpi-val"><?= $fmtN($heuresGlobAnnee) ?>h</div>
      </div>
    </div>
    <div class="fdb-kpi">
      <div class="fdb-kpi-icon" style="background:var(--fdb-warn-bg);color:var(--fdb-warn)"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="fdb-kpi-lbl">Budget engagé</div>
        <div class="fdb-kpi-val">CHF <?= $fmtN($budgetGlobAnnee) ?></div>
      </div>
    </div>
  </div>

  <!-- Section title -->
  <div class="fdb-section-title">
    <div>
      <h2>Vue par secteur</h2>
      <div class="sub">Cliquez sur un secteur pour voir le détail des collaborateurs, formations et écarts</div>
    </div>
  </div>

  <!-- Grid 6 secteurs -->
  <div class="fdb-secteurs">
    <?php foreach ($secteurStats as $key => $s):
      $m = $s['meta'];
      [$lbl, $color, $icon, $cls] = $m;
      $confCls = $s['conformite_pct'] >= 80 ? 'ok' : ($s['conformite_pct'] >= 60 ? 'warn' : 'bad');
      $obligCls = $s['oblig_pct'] >= 90 ? 'ok' : ($s['oblig_pct'] >= 70 ? 'warn' : 'bad');
    ?>
      <a class="fdb-secteur fdb-sec-<?= h($cls) ?>" href="?page=rh-formations-dashboard-secteur&secteur=<?= h($key) ?>" data-page-link style="--sec-color:<?= h($color) ?>">
        <div class="fdb-sec-head">
          <div class="fdb-sec-ico"><i class="bi <?= h($icon) ?>"></i></div>
          <div>
            <div class="fdb-sec-name"><?= h($lbl) ?></div>
            <div class="fdb-sec-collabs"><?= $s['nb_collabs'] ?> collaborateur<?= $s['nb_collabs'] > 1 ? 's' : '' ?></div>
          </div>
          <i class="bi bi-arrow-right fdb-sec-arrow"></i>
        </div>

        <div class="fdb-sec-stats">
          <div class="fdb-sec-stat">
            <div class="fdb-sec-stat-v fdb-<?= h($confCls) ?>"><?= $s['conformite_pct'] ?>%</div>
            <div class="fdb-sec-stat-l">Conformité</div>
            <div class="fdb-sec-bar"><span class="fdb-sec-bar-fill fdb-bar-<?= h($confCls) ?>" style="width:<?= $s['conformite_pct'] ?>%"></span></div>
          </div>
          <div class="fdb-sec-stat">
            <div class="fdb-sec-stat-v fdb-<?= h($obligCls) ?>"><?= $s['oblig_pct'] ?>%</div>
            <div class="fdb-sec-stat-l">Obligatoires FEGEMS</div>
            <div class="fdb-sec-bar"><span class="fdb-sec-bar-fill fdb-bar-<?= h($obligCls) ?>" style="width:<?= $s['oblig_pct'] ?>%"></span></div>
          </div>
        </div>

        <div class="fdb-sec-footer">
          <div class="fdb-sec-foot-cell">
            <span class="fdb-sec-foot-num <?= $s['nb_haute'] > 0 ? 'fdb-bad' : 'fdb-ok' ?>"><?= $s['nb_haute'] ?></span>
            <span class="fdb-sec-foot-lbl">Écarts critiques</span>
          </div>
          <div class="fdb-sec-foot-cell">
            <span class="fdb-sec-foot-num"><?= $fmtN($s['heures_an']) ?>h</span>
            <span class="fdb-sec-foot-lbl">Heures <?= $anneeActuelle ?></span>
          </div>
          <div class="fdb-sec-foot-cell">
            <span class="fdb-sec-foot-num"><?= $s['nb_inscrits'] ?></span>
            <span class="fdb-sec-foot-lbl">Inscrit<?= $s['nb_inscrits'] > 1 ? 'es' : 'e' ?> à venir</span>
          </div>
        </div>
      </a>
    <?php endforeach ?>
  </div>

  <!-- Activité récente -->
  <div class="fdb-bottom">
    <div class="fdb-card">
      <div class="fdb-card-head">
        <h3>Activité formation récente</h3>
        <div class="meta">8 dernières activités</div>
      </div>
      <?php if ($activiteRecente): ?>
        <div class="fdb-activity">
          <?php foreach ($activiteRecente as $a):
            $sectorMeta = $secteurs[$a['secteur_fegems'] ?? ''] ?? null;
            $sectorColor = $sectorMeta[1] ?? '#6b8783';
            $statutCls = match($a['participant_statut']) {
                'valide' => 'ok',
                'present' => 'ok',
                'inscrit' => 'info',
                default => 'muted',
            };
            $statutLbl = match($a['participant_statut']) {
                'valide' => 'Validée',
                'present' => 'Réalisée',
                'inscrit' => 'Inscrit',
                default => '—',
            };
          ?>
            <div class="fdb-act-row">
              <div class="fdb-act-avatar" style="background:<?= h($sectorColor) ?>">
                <?= h(strtoupper(mb_substr($a['prenom'] ?? '', 0, 1) . mb_substr($a['nom'] ?? '', 0, 1))) ?>
              </div>
              <div class="fdb-act-body">
                <div class="fdb-act-name"><?= h(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?></div>
                <div class="fdb-act-form"><?= h($a['formation_titre'] ?? '—') ?></div>
              </div>
              <div class="fdb-act-status">
                <span class="fdb-act-tag fdb-tag-<?= h($statutCls) ?>"><?= h($statutLbl) ?></span>
                <div class="fdb-act-when"><?= $a['quand'] ? date('d.m.Y', strtotime($a['quand'])) : '' ?></div>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <div class="fdb-empty">
          <i class="bi bi-clock-history"></i>
          <div><strong>Aucune activité récente</strong></div>
          <div class="fdb-meta">Les inscriptions et formations réalisées apparaîtront ici.</div>
        </div>
      <?php endif ?>
    </div>

    <div class="fdb-card">
      <div class="fdb-card-head">
        <h3>Répartition conformité</h3>
        <div class="meta">par secteur</div>
      </div>
      <div class="fdb-repart">
        <?php foreach ($secteurStats as $key => $s):
          [$lbl, $color] = $s['meta'];
        ?>
          <div class="fdb-rep-row">
            <div class="fdb-rep-name">
              <span class="fdb-rep-dot" style="background:<?= h($color) ?>"></span>
              <?= h($lbl) ?>
            </div>
            <div class="fdb-rep-bar-wrap">
              <div class="fdb-rep-bar"><span style="width:<?= $s['conformite_pct'] ?>%;background:<?= h($color) ?>"></span></div>
            </div>
            <div class="fdb-rep-val"><?= $s['conformite_pct'] ?>%</div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

</div>

<style>
/* ═══════════════════════════════════════════════════════════════════
   Tableau de bord formation · scopé sous .fdb-page
═══════════════════════════════════════════════════════════════════ */

.fdb-page {
  --fdb-bg: #f5f7f5;
  --fdb-surface: #fff;
  --fdb-surface-2: #fafbfa;
  --fdb-ink: #0d2a26;
  --fdb-ink-2: #324e4a;
  --fdb-muted: #6b8783;
  --fdb-line: #e3ebe8;
  --fdb-line-2: #d4ddda;
  --fdb-teal-50: #ecf5f3;
  --fdb-teal-300: #7ab5ab;
  --fdb-teal-500: #2d8074;
  --fdb-teal-600: #1f6359;
  --fdb-teal-700: #164a42;
  --fdb-warn: #c97a2a;
  --fdb-warn-bg: #fbf0e1;
  --fdb-danger: #b8443a;
  --fdb-danger-bg: #f7e3e0;
  --fdb-ok: #3d8b6b;
  --fdb-ok-bg: #e3f0ea;
  --fdb-info: #3a6a8a;
  --fdb-info-bg: #e2ecf2;

  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 14px; color: var(--fdb-ink); line-height: 1.45;
  padding: 28px 32px 60px; max-width: 1600px; margin: 0 auto;
}
.fdb-page * { box-sizing: border-box; }

/* ═════ HEAD ═════ */
.fdb-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; flex-wrap: wrap; margin-bottom: 24px;
}
.fdb-h1 {
  font-family: 'Fraunces', serif; font-size: 34px; font-weight: 500;
  letter-spacing: -.025em; line-height: 1.1; color: var(--fdb-ink); margin: 0;
}
.fdb-sub { color: var(--fdb-muted); font-size: 14px; margin-top: 5px; }
.fdb-sub strong { color: var(--fdb-ink-2); font-weight: 600; }
.fdb-actions { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }

.fdb-page .fdb-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 8px; font-family: inherit;
  font-size: 13px; font-weight: 500;
  border: 1px solid var(--fdb-line); background: var(--fdb-surface);
  color: var(--fdb-ink-2); cursor: pointer; transition: .15s; text-decoration: none;
}
.fdb-page .fdb-btn:hover { border-color: var(--fdb-teal-300); color: var(--fdb-teal-600); }
.fdb-page .fdb-btn-primary {
  background: var(--fdb-teal-600); color: #fff; border-color: var(--fdb-teal-600);
}
.fdb-page .fdb-btn-primary:hover {
  background: var(--fdb-teal-700); border-color: var(--fdb-teal-700); color: #fff;
}

/* ═════ KPI ROW ═════ */
.fdb-kpi-row {
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px;
  margin-bottom: 28px;
}
.fdb-kpi {
  background: var(--fdb-surface); border: 1px solid var(--fdb-line);
  border-radius: 14px; padding: 16px 18px;
  display: flex; align-items: center; gap: 14px;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fdb-kpi-icon {
  width: 42px; height: 42px; border-radius: 10px;
  display: grid; place-items: center; font-size: 19px;
  flex-shrink: 0;
}
.fdb-kpi-lbl {
  font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fdb-muted); font-weight: 600;
}
.fdb-kpi-val {
  font-family: 'Fraunces', serif; font-size: 24px; font-weight: 600;
  letter-spacing: -.02em; line-height: 1.1; color: var(--fdb-ink); margin-top: 2px;
}

/* ═════ SECTION TITLE ═════ */
.fdb-section-title {
  display: flex; justify-content: space-between; align-items: flex-end;
  margin: 24px 0 16px;
}
.fdb-section-title h2 {
  font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600;
  letter-spacing: -.015em; color: var(--fdb-ink); margin: 0;
}
.fdb-section-title .sub { font-size: 13px; color: var(--fdb-muted); margin-top: 2px; }

/* ═════ SECTEURS GRID ═════ */
.fdb-secteurs {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
  margin-bottom: 32px;
}
.fdb-page .fdb-secteur {
  background: var(--fdb-surface); border: 1px solid var(--fdb-line);
  border-radius: 16px; padding: 20px 22px;
  text-decoration: none; color: var(--fdb-ink);
  transition: .18s; position: relative; overflow: hidden;
  display: flex; flex-direction: column; gap: 18px;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fdb-page .fdb-secteur::before {
  content: ""; position: absolute; left: 0; top: 0; right: 0; height: 4px;
  background: var(--sec-color, var(--fdb-teal-600));
}
.fdb-page .fdb-secteur:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px -8px rgba(13,42,38,.18), 0 4px 12px rgba(13,42,38,.06);
  border-color: var(--sec-color, var(--fdb-teal-300));
}

.fdb-sec-head {
  display: flex; align-items: center; gap: 14px;
}
.fdb-sec-ico {
  width: 48px; height: 48px; border-radius: 12px;
  background: var(--sec-color, var(--fdb-teal-600));
  color: #fff; display: grid; place-items: center;
  font-size: 22px; flex-shrink: 0;
  box-shadow: 0 2px 8px -2px rgba(0,0,0,.18);
}
.fdb-sec-name {
  font-family: 'Fraunces', serif; font-size: 20px; font-weight: 600;
  letter-spacing: -.015em; color: var(--fdb-ink); line-height: 1.1;
}
.fdb-sec-collabs {
  font-size: 12px; color: var(--fdb-muted); margin-top: 2px;
}
.fdb-sec-arrow {
  margin-left: auto; color: var(--fdb-muted); font-size: 16px;
  transition: .18s;
}
.fdb-page .fdb-secteur:hover .fdb-sec-arrow {
  color: var(--sec-color, var(--fdb-teal-600));
  transform: translateX(3px);
}

.fdb-sec-stats {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
  padding: 14px 0; border-top: 1px solid var(--fdb-line);
  border-bottom: 1px solid var(--fdb-line);
}
.fdb-sec-stat {}
.fdb-sec-stat-v {
  font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600;
  letter-spacing: -.02em; line-height: 1; color: var(--fdb-ink);
}
.fdb-sec-stat-v.fdb-ok   { color: var(--fdb-ok); }
.fdb-sec-stat-v.fdb-warn { color: var(--fdb-warn); }
.fdb-sec-stat-v.fdb-bad  { color: var(--fdb-danger); }
.fdb-sec-stat-l {
  font-size: 10px; letter-spacing: .08em; text-transform: uppercase;
  color: var(--fdb-muted); font-weight: 600; margin-top: 4px;
}
.fdb-sec-bar {
  height: 4px; background: var(--fdb-line); border-radius: 99px;
  overflow: hidden; margin-top: 6px;
}
.fdb-sec-bar-fill {
  display: block; height: 100%; border-radius: 99px;
}
.fdb-bar-ok   { background: linear-gradient(90deg, var(--fdb-ok), #5cad8b); }
.fdb-bar-warn { background: linear-gradient(90deg, var(--fdb-warn), #e0a85a); }
.fdb-bar-bad  { background: linear-gradient(90deg, var(--fdb-danger), #cd6b62); }

.fdb-sec-footer {
  display: flex; justify-content: space-between; gap: 12px;
}
.fdb-sec-foot-cell {
  display: flex; flex-direction: column; gap: 2px;
}
.fdb-sec-foot-num {
  font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 600;
  color: var(--fdb-ink);
}
.fdb-sec-foot-num.fdb-bad { color: var(--fdb-danger); }
.fdb-sec-foot-num.fdb-ok  { color: var(--fdb-muted); }
.fdb-sec-foot-lbl {
  font-size: 9.5px; letter-spacing: .06em; text-transform: uppercase;
  color: var(--fdb-muted); font-weight: 600;
}

/* ═════ BOTTOM ═════ */
.fdb-bottom {
  display: grid; grid-template-columns: 1.4fr 1fr; gap: 16px;
}

.fdb-card {
  background: var(--fdb-surface); border: 1px solid var(--fdb-line);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(13,42,38,.04);
}
.fdb-card-head {
  padding: 16px 20px; border-bottom: 1px solid var(--fdb-line);
  display: flex; justify-content: space-between; align-items: center;
}
.fdb-card-head h3 {
  font-family: 'Fraunces', serif; font-size: 16px; font-weight: 600;
  letter-spacing: -.01em; margin: 0;
}
.fdb-card-head .meta { font-size: 12px; color: var(--fdb-muted); }

/* Activité */
.fdb-activity {}
.fdb-act-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 20px; border-bottom: 1px solid var(--fdb-line);
}
.fdb-act-row:last-child { border-bottom: 0; }
.fdb-act-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: grid; place-items: center; color: #fff;
  font-weight: 600; font-size: 12px; flex-shrink: 0;
}
.fdb-act-body { flex: 1; min-width: 0; }
.fdb-act-name { font-weight: 500; font-size: 13px; color: var(--fdb-ink); }
.fdb-act-form { font-size: 12px; color: var(--fdb-muted); margin-top: 2px; line-height: 1.3; }
.fdb-act-status { text-align: right; flex-shrink: 0; }
.fdb-act-tag {
  font-size: 9.5px; letter-spacing: .06em; text-transform: uppercase;
  font-weight: 700; padding: 2px 7px; border-radius: 4px;
}
.fdb-tag-ok   { background: var(--fdb-ok-bg); color: var(--fdb-ok); }
.fdb-tag-info { background: var(--fdb-info-bg); color: var(--fdb-info); }
.fdb-tag-muted{ background: var(--fdb-line); color: var(--fdb-muted); }
.fdb-act-when {
  font-size: 10.5px; color: var(--fdb-muted); margin-top: 2px;
  font-family: 'JetBrains Mono', monospace;
}

.fdb-empty {
  padding: 40px 20px; text-align: center; color: var(--fdb-muted);
}
.fdb-empty i { font-size: 32px; opacity: .3; display: block; margin-bottom: 10px; }
.fdb-empty .fdb-meta { font-size: 12px; margin-top: 6px; }

/* Répartition bars */
.fdb-repart { padding: 16px 20px; }
.fdb-rep-row {
  display: grid; grid-template-columns: 130px 1fr 50px;
  align-items: center; gap: 10px;
  padding: 6px 0;
}
.fdb-rep-name {
  font-size: 12.5px; color: var(--fdb-ink-2); font-weight: 500;
  display: flex; align-items: center; gap: 8px;
}
.fdb-rep-dot { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }
.fdb-rep-bar {
  height: 6px; background: var(--fdb-line); border-radius: 99px; overflow: hidden;
}
.fdb-rep-bar span { display: block; height: 100%; border-radius: 99px; }
.fdb-rep-val {
  font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;
  text-align: right; color: var(--fdb-ink-2);
}

@media (max-width: 1280px) {
  .fdb-kpi-row { grid-template-columns: repeat(3, 1fr); }
  .fdb-secteurs { grid-template-columns: repeat(2, 1fr); }
  .fdb-bottom { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .fdb-secteurs { grid-template-columns: 1fr; }
  .fdb-kpi-row { grid-template-columns: 1fr 1fr; }
}

/* Print */
@media print {
  .fdb-actions, .fdb-sec-arrow { display: none !important; }
  .fdb-page { padding: 12px; }
  .fdb-secteur { break-inside: avoid; }
  .fdb-secteur::before { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
