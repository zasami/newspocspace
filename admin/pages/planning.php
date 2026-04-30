<?php
/**
 * Planning Admin — Module Planning · Spocspace Care
 *
 * VERSION 3.0 — Réécriture fidèle à la maquette Planning · Spocspace.htm
 * (avril 2026) : classes simplifiées (.command-bar, .cb-*, .shift, .col-*)
 *
 * Note : la grille rend les VRAIS users actifs depuis la DB, groupés par
 * fonction. Les cellules shifts utilisent un mock déterministe (pl_demo_shifts)
 * en attendant la Phase 2 (logique d'assignation / IA / édition).
 *
 * Hooks JS inclus :
 *   - Dropdown période (3×4 mois + nav année)
 *   - Dropdown vue (semaine / mois)
 *   - Nav arrows (prev / today / next)
 *   - Toggle Provisoire / Finaliser
 *   - 5 presets de taille (XS / SM / MD / STD / LG, STD par défaut)
 *   - Fullscreen toggle
 *   - Filtre équipes (pills)
 */

// ─── Données serveur ────────────────────────────────────────────────────────
$planningUsers = Db::fetchAll(
    "SELECT u.id, u.nom, u.prenom, u.taux, u.role, u.type_contrat,
            f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            GROUP_CONCAT(m.id ORDER BY um.is_principal DESC) AS module_ids,
            GROUP_CONCAT(m.code ORDER BY um.is_principal DESC) AS module_codes
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     GROUP BY u.id
     ORDER BY f.ordre, u.nom, u.prenom"
);
$planningHoraires = Db::fetchAll(
    "SELECT id, code, nom, heure_debut, heure_fin, duree_effective, couleur
     FROM horaires_types WHERE is_active = 1 ORDER BY code"
);
$planningModules   = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
$planningFonctions = Db::fetchAll("SELECT id, code, nom, ordre FROM fonctions ORDER BY ordre");

// ─── Calcul des jours du mois courant ───────────────────────────────────────
$plYear  = (int) ($_GET['year']  ?? date('Y'));
$plMonth = (int) ($_GET['month'] ?? date('n'));
if ($plMonth < 1 || $plMonth > 12) $plMonth = (int) date('n');

$plDaysInMonth = (int) date('t', mktime(0, 0, 0, $plMonth, 1, $plYear));
$plToday       = date('Y-m-d');
$plMonthNamesFr = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
$plMonthEmojis = [
    1 => '❄️', 2 => '💧', 3 => '🌱', 4 => '🌸', 5 => '🌿', 6 => '☀️',
    7 => '🌻', 8 => '🏖️', 9 => '🍂', 10 => '🎃', 11 => '🍁', 12 => '🎄'
];
$plDayNamesShort = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

$plDays = [];
for ($d = 1; $d <= $plDaysInMonth; $d++) {
    $ts = mktime(0, 0, 0, $plMonth, $d, $plYear);
    $w  = (int) date('N', $ts);
    $iso = date('Y-m-d', $ts);
    $plDays[] = [
        'num'     => $d,
        'name'    => $plDayNamesShort[$w] ?? '',
        'weekend' => ($w >= 6),
        'today'   => ($iso === $plToday),
        'iso'     => $iso,
    ];
}
$plNbDays = count($plDays);

// ─── Map module_code → infos pour lookup ───────────────────────────────────
$plModuleMap = [];
foreach ($planningModules as $m) {
    $plModuleMap[$m['code']] = $m;
}

// ─── Hiérarchie : Module (principal) → Fonction → Users ────────────────────
$plHierarchy = [];
foreach ($planningUsers as $u) {
    $modCodes = array_filter(explode(',', (string) ($u['module_codes'] ?? '')));
    $modCode  = !empty($modCodes) ? $modCodes[0] : 'SANS';   // module principal (1er)
    $foncCode = $u['fonction_code'] ?? 'SANS';

    if (!isset($plHierarchy[$modCode])) {
        $modInfo = $plModuleMap[$modCode] ?? null;
        $plHierarchy[$modCode] = [
            'code'       => $modCode,
            'nom'        => $modInfo['nom'] ?? ($modCode === 'SANS' ? 'Sans module' : $modCode),
            'ordre'      => (int) ($modInfo['ordre'] ?? 999),
            'fonctions'  => [],
            'totalUsers' => 0,
        ];
    }
    if (!isset($plHierarchy[$modCode]['fonctions'][$foncCode])) {
        $plHierarchy[$modCode]['fonctions'][$foncCode] = [
            'code'  => $foncCode,
            'nom'   => $u['fonction_nom'] ?? 'Sans fonction',
            'ordre' => (int) ($u['fonction_ordre'] ?? 999),
            'users' => [],
        ];
    }
    $plHierarchy[$modCode]['fonctions'][$foncCode]['users'][] = $u;
    $plHierarchy[$modCode]['totalUsers']++;
}

// Tri : modules par ordre, puis fonctions dans chaque module
uasort($plHierarchy, fn($a, $b) => ($a['ordre'] ?? 999) - ($b['ordre'] ?? 999));
foreach ($plHierarchy as &$_mod) {
    uasort($_mod['fonctions'], fn($a, $b) => ($a['ordre'] ?? 999) - ($b['ordre'] ?? 999));
}
unset($_mod);

// ─── Compteurs filtres équipes ──────────────────────────────────────────────
$plCountTotal = count($planningUsers);
$plCountByModule   = [];
$plCountByFonction = [];
foreach ($planningUsers as $u) {
    $codes = array_filter(explode(',', (string) ($u['module_codes'] ?? '')));
    foreach ($codes as $c) $plCountByModule[$c] = ($plCountByModule[$c] ?? 0) + 1;
    if (!empty($u['fonction_code'])) {
        $plCountByFonction[$u['fonction_code']] = ($plCountByFonction[$u['fonction_code']] ?? 0) + 1;
    }
}

// Mapping fonction_code → classe role-tag (couleurs des badges)
function pl_role_class(string $code): string {
    $c = strtoupper($code);
    if ($c === 'INF')  return 'inf';
    if ($c === 'ASSC') return 'assc';
    if ($c === 'AS')   return 'as';
    if (in_array($c, ['ANIM', 'ASE'], true)) return 'anim';
    if ($c === 'RUV')  return 'ruv';
    return ''; // role-tag par défaut
}

function pl_target_hours(float $taux): float {
    return round($taux * 1.82, 1);
}

// ─── Shifts démo (Phase 1) ──────────────────────────────────────────────────
$plShiftCodes = ['c1', 'c2', 'd1', 'd3', 'd4', 's3', 's4', 'a2', 'a3', 'n'];
$plShiftHours = ['c1' => 8, 'c2' => 8, 'd1' => 8, 'd3' => 8, 'd4' => 8,
                 's3' => 8, 's4' => 8, 'a2' => 7, 'a3' => 7, 'n' => 10];

function pl_demo_shifts(string $userId, array $days, array $codes): array {
    $shifts = [];
    $seed = abs(crc32($userId));
    foreach ($days as $idx => $day) {
        $rng = ($seed + $idx * 37 + ($idx * $idx * 13)) % 100;
        if ($day['weekend'] && $rng < 60) continue;
        if (!$day['weekend'] && $rng < 22) continue;
        $code = $codes[($seed + $idx * 7) % count($codes)];
        $shifts[$day['iso']] = $code;
    }
    return $shifts;
}

$plUserShifts  = [];
$plTotalShifts = 0;
foreach ($planningUsers as $u) {
    $sh = pl_demo_shifts((string) $u['id'], $plDays, $plShiftCodes);
    $plUserShifts[$u['id']] = $sh;
    $plTotalShifts += count($sh);
}
$plMissingShifts = max(0, (int) round($plTotalShifts * 0.0085));

$plFonctionsForFilter = array_filter(
    $planningFonctions,
    fn($f) => !empty($plCountByFonction[$f['code']]) && $plCountByFonction[$f['code']] >= 1
);
uasort($plFonctionsForFilter, fn($a, $b) => ($plCountByFonction[$b['code']] ?? 0) - ($plCountByFonction[$a['code']] ?? 0));
$plFonctionsForFilter = array_slice($plFonctionsForFilter, 0, 8, true);
?>

<!-- ═══ Page Planning ═══════════════════════════════════════════════════════ -->
<div class="planning-page" id="planningPage">

  <!-- ── Command bar ───────────────────────────────────────────────────────── -->
  <div class="command-bar">

    <!-- Période + Vue groupés -->
    <div class="cb-period-group">
      <button type="button" class="cb-period-btn" id="plPeriodBtn">
        <div class="cb-period-icon"><?= $plMonthEmojis[$plMonth] ?? '📅' ?></div>
        <div class="cb-period-text">
          <span class="cb-period-label">Période</span>
          <span class="cb-period-value" id="plPeriodLabel"><?= h($plMonthNamesFr[$plMonth]) ?> <?= (int) $plYear ?></span>
        </div>
        <svg class="cb-period-chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <button type="button" class="cb-period-btn" id="plViewBtn">
        <div class="cb-period-icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/></svg>
        </div>
        <div class="cb-period-text">
          <span class="cb-period-label">Vue</span>
          <span class="cb-period-value" id="plViewLabel">Mois</span>
        </div>
        <svg class="cb-period-chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </button>

      <!-- Dropdown PÉRIODE -->
      <div class="dropdown dropdown-period" id="plPeriodDropdown" role="dialog" aria-label="Sélection du mois">
        <div class="dd-period-head">
          <button type="button" class="dd-year-nav" id="plYearPrev" aria-label="Année précédente">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <span class="dd-year" id="plYearLabel"><?= (int) $plYear ?></span>
          <button type="button" class="dd-year-nav" id="plYearNext" aria-label="Année suivante">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
        <div class="dd-month-grid">
          <?php foreach ($plMonthNamesFr as $m => $name): ?>
          <button type="button" class="dd-month <?= $m === $plMonth ? 'active' : ($m < $plMonth ? 'past' : '') ?>" data-month="<?= $m ?>">
            <span class="dd-month-emoji"><?= $plMonthEmojis[$m] ?? '📅' ?></span>
            <span class="dd-month-num"><?= sprintf('%02d', $m) ?></span>
            <span class="dd-month-name"><?= h(mb_substr($name, 0, 3)) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <div class="dd-period-foot">
          <button type="button" class="dd-foot-btn" id="plTodayBtn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 2"/></svg>
            Aujourd'hui
          </button>
        </div>
      </div>

      <!-- Dropdown VUE -->
      <div class="dropdown dropdown-view" id="plViewDropdown" role="menu" aria-label="Type de vue">
        <button type="button" class="dd-view-item" data-view="semaine" role="menuitem">
          <span class="dd-view-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 14h2M13 14h2M9 18h2M13 18h2"/></svg>
          </span>
          <span class="dd-view-text">
            <span class="dd-view-name">Vue semaine</span>
            <span class="dd-view-desc">7 jours · plus de détail par cellule</span>
          </span>
          <svg class="dd-view-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
        <button type="button" class="dd-view-item active" data-view="mois" role="menuitem">
          <span class="dd-view-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </span>
          <span class="dd-view-text">
            <span class="dd-view-name">Vue mois</span>
            <span class="dd-view-desc">Vue d'ensemble · 28-31 jours</span>
          </span>
          <svg class="dd-view-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
      </div>
    </div>

    <!-- Nav arrows -->
    <div class="cb-nav">
      <button type="button" class="cb-nav-btn" id="plNavPrev" title="Mois précédent">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <button type="button" class="cb-nav-btn cb-nav-today" id="plNavToday" title="Aujourd'hui">Auj.</button>
      <button type="button" class="cb-nav-btn" id="plNavNext" title="Mois suivant">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>

    <!-- Status badge -->
    <div class="cb-status">
      <span class="pulse"></span>
      Brouillon
    </div>

    <!-- Compteur d'assignations -->
    <div class="cb-meta"><strong id="plAssignCount"><?= number_format($plTotalShifts, 0, ',', "'") ?></strong> assignations</div>

    <div class="cb-spacer"></div>

    <!-- Bouton Générer planning (action primaire dark) -->
    <button type="button" class="cb-btn dark" id="plGenerateBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg>
      Générer planning
    </button>

    <!-- Bouton Créer -->
    <button type="button" class="cb-btn" id="plCreateBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Créer
    </button>

    <!-- Groupe icônes outils (stats / filtres / supprimer / fullscreen) -->
    <div class="cb-icon-group">
      <button type="button" class="cb-btn-mini" title="Statistiques">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" title="Filtres avancés">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="11" y2="6"/><line x1="14" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="6" y2="12"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="13" y2="18"/><line x1="16" y1="18" x2="20" y2="18"/><circle cx="12.5" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="14.5" cy="18" r="2"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" title="Supprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </button>
      <button type="button" class="cb-btn-mini cb-fullscreen" id="plFullscreenBtn" title="Plein écran (F11)">
        <svg id="plFullscreenIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V3h4M21 7V3h-4M3 17v4h4M21 17v4h-4"/></svg>
      </button>
    </div>

    <!-- Groupe icônes export (imprimer / PDF / email / CSV) -->
    <div class="cb-icon-group">
      <button type="button" class="cb-btn-mini" title="Imprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" title="Exporter PDF">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" title="Envoyer par email">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" title="Exporter CSV">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
      </button>
    </div>

    <!-- Bouton Proposition -->
    <button type="button" class="cb-btn" id="plPropositionBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 00-6 0v4M5 9h14l1 12H4z"/></svg>
      Proposition
    </button>

    <!-- Icône liste -->
    <button type="button" class="cb-btn-mini" title="Voir toutes les propositions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </button>

    <!-- Toggle Provisoire / Finaliser -->
    <div class="cb-finalize">
      <button type="button" class="on" data-finalize="provisoire">Provisoire</button>
      <button type="button" data-finalize="finaliser">Finaliser</button>
    </div>

  </div>

  <!-- ── Team filters ──────────────────────────────────────────────────────── -->
  <div class="team-filters">
    <button type="button" class="team-pill on" data-team-filter="" data-team-type="all">Tous · <?= (int) $plCountTotal ?></button>
    <span class="team-divider"></span>
    <?php foreach ($planningModules as $m): if (in_array($m['code'], ['POOL','NUIT'], true)) continue; ?>
    <button type="button" class="team-pill" data-team-filter="<?= h($m['code']) ?>" data-team-type="module"><?= h($m['code']) ?> · <?= (int) ($plCountByModule[$m['code']] ?? 0) ?></button>
    <?php endforeach; ?>
    <span class="team-divider"></span>
    <?php foreach ($planningModules as $m): if (!in_array($m['code'], ['POOL','NUIT'], true)) continue; ?>
    <button type="button" class="team-pill" data-team-filter="<?= h($m['code']) ?>" data-team-type="module"><?= h($m['code']) ?></button>
    <?php endforeach; ?>
    <?php if (!empty($plFonctionsForFilter)): ?>
    <span class="team-divider"></span>
    <?php foreach ($plFonctionsForFilter as $f): ?>
    <button type="button" class="team-pill" data-team-filter="<?= h($f['code']) ?>" data-team-type="fonction" title="<?= h($f['nom']) ?>"><?= h($f['code']) ?></button>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Size controls : poussés tout à droite via margin-left:auto ─── -->
    <div class="size-controls" role="group" aria-label="Zoom de la grille">
      <button type="button" class="size-btn" data-size="xs" title="Très petit">
        <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="6" y="6" width="4" height="4" rx="1"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="sm" title="Petit">
        <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="5" y="5" width="6" height="6" rx="1"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="md" title="Moyen">
        <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor"><rect x="3.5" y="3.5" width="9" height="9" rx="1.5"/></svg>
      </button>
      <button type="button" class="size-btn active" data-size="std" title="Standard (défaut)">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="2" width="12" height="12" rx="2"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="lg" title="Grand">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><rect x="0.5" y="0.5" width="15" height="15" rx="2"/></svg>
      </button>
    </div>
  </div>

  <!-- ── Planning grid ─────────────────────────────────────────────────────── -->
  <div class="planning-wrap" id="plGridWrap">

    <div class="planning-grid">
      <table class="planning" id="plTable">

        <thead>
          <tr>
            <th class="col-collab">
              <span class="col-collab-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/></svg>
                Collaborateur
              </span>
            </th>
            <th class="col-pct">%</th>
            <?php foreach ($plDays as $day): ?>
            <th class="day-head <?= $day['weekend'] ? 'weekend' : '' ?> <?= $day['today'] ? 'today' : '' ?>">
              <span class="day-name"><?= h($day['name']) ?></span>
              <span class="day-num"><?= (int) $day['num'] ?></span>
            </th>
            <?php endforeach; ?>
            <th class="col-hours">Heures</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($plHierarchy as $module): ?>

          <!-- ░░░ MODULE ROW ░░░ collapsable, niveau 1 ░░░ -->
          <tr class="module-row" data-module-row="<?= h($module['code']) ?>" aria-expanded="true">
            <td class="col-collab" colspan="2">
              <div class="module-cell-content">
                <button type="button" class="module-toggle" aria-label="Replier/déplier le module">
                  <svg class="module-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M9 18l6-6-6-6"/>
                  </svg>
                </button>
                <span class="module-code"><?= h($module['code']) ?></span>
                <span class="module-name"><?= h($module['nom']) ?></span>
                <span class="module-count"><?= (int) $module['totalUsers'] ?> emp</span>
              </div>
            </td>
            <td colspan="<?= $plNbDays ?>"></td>
            <td></td>
          </tr>

          <?php foreach ($module['fonctions'] as $fonction): ?>

          <!-- ─── FONCTION SUB-ROW ─── niveau 2 ─── -->
          <tr class="section-row fonction-row" data-module="<?= h($module['code']) ?>" data-team-fonction="<?= h($fonction['code']) ?>">
            <td class="col-collab" colspan="2">
              <div class="section-cell-content">
                <?= h($fonction['nom']) ?> · <?= h($fonction['code']) ?>
                <span class="section-count"><?= count($fonction['users']) ?></span>
              </div>
            </td>
            <td colspan="<?= $plNbDays + 1 ?>"></td>
          </tr>

          <?php foreach ($fonction['users'] as $u):
            $taux = (float) ($u['taux'] ?? 0);
            $tauxRounded = (int) round($taux);
            $cible = pl_target_hours($taux);
            $userShifts = $plUserShifts[$u['id']] ?? [];
            $heuresCourant = 0;
            foreach ($userShifts as $code) { $heuresCourant += $plShiftHours[$code] ?? 8; }
            $diff = round($heuresCourant - $cible, 1);
            $userModCodes = array_filter(explode(',', (string) ($u['module_codes'] ?? '')));
          ?>
          <tr class="user-row"
              data-user-id="<?= h($u['id']) ?>"
              data-module="<?= h($module['code']) ?>"
              data-fonction="<?= h($u['fonction_code'] ?? '') ?>"
              data-modules="<?= h(implode(' ', $userModCodes)) ?>">
            <td class="col-collab">
              <div class="collab-cell">
                <span class="role-tag <?= pl_role_class($u['fonction_code'] ?? '') ?>"><?= h($u['fonction_code'] ?? '—') ?></span>
                <div class="collab-cell-info">
                  <div class="collab-cell-name"><?= h(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?></div>
                </div>
              </div>
            </td>
            <td class="col-pct">
              <div class="pct-cell"><?= $tauxRounded ?>%</div>
              <div class="pct-cell-bar"><div style="width:<?= $tauxRounded ?>%"></div></div>
            </td>
            <?php foreach ($plDays as $day):
              $code = $userShifts[$day['iso']] ?? null;
            ?>
            <td class="<?= $day['weekend'] ? 'weekend' : '' ?> <?= $day['today'] ? 'today' : '' ?>">
              <?php if ($code): ?>
              <span class="shift <?= h($code) ?>" data-shift="<?= h($code) ?>"><?= strtoupper(h($code)) ?></span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="col-hours">
              <div class="hours-cell">
                <div class="hours-main"><?= (int) $heuresCourant ?>h</div>
                <div class="hours-target <?= $diff < 0 ? 'under' : ($diff > 0 ? 'over' : '') ?>"><?= $cible ?>h<?= $diff !== 0.0 ? ' · ' . ($diff > 0 ? '+' : '') . $diff : ' · ✓' ?></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>

          <?php endforeach; // fonctions ?>
          <?php endforeach; // modules ?>

          <?php if (empty($plHierarchy)): ?>
          <tr>
            <td colspan="<?= $plNbDays + 3 ?>" class="text-center py-12 text-muted">
              Aucun collaborateur actif dans la base.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Footer planning : légende + stats -->
    <div class="planning-foot">
      <div class="legend">
        <span class="legend-label">Shifts</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-c1)"></span>C1 · 7h-15h</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-c2)"></span>C2 · 14h-22h</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-d1)"></span>D1 doublure</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-s3)"></span>S3 soir</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-s4)"></span>S4 soir</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-a2)"></span>A2 aprem</span>
        <span class="legend-item"><span class="legend-sw" style="background: var(--shift-n)"></span>N nuit</span>
      </div>
      <div class="foot-stats">
        <div class="foot-stat">
          <span class="foot-stat-num"><?= (int) $plCountTotal ?></span>
          <span class="foot-stat-lbl">Collab.</span>
        </div>
        <div class="foot-stat">
          <span class="foot-stat-num" id="plFootShifts"><?= number_format($plTotalShifts, 0, ',', "'") ?></span>
          <span class="foot-stat-lbl">Shifts</span>
        </div>
        <div class="foot-stat">
          <span class="foot-stat-num" style="color:var(--warn)" id="plFootMissing"><?= (int) $plMissingShifts ?></span>
          <span class="foot-stat-lbl">Manquants</span>
        </div>
      </div>
    </div>

  </div>

</div>

<script<?= nonce() ?>>
// ═════════════════════════════════════════════════════════════════════════════
// Planning page — interactions JS
// ═════════════════════════════════════════════════════════════════════════════
(function() {
    'use strict';

    let currentMonth = <?= (int) $plMonth ?>;
    let currentYear  = <?= (int) $plYear ?>;

    function $(id) { return document.getElementById(id); }
    function gotoMonth(year, month) {
        const url = new URL(location.href);
        url.searchParams.set('year', year);
        url.searchParams.set('month', month);
        location.href = url.toString();
    }

    // ── Dropdowns période + vue ─────────────────────────────────────────────
    const periodBtn      = $('plPeriodBtn');
    const viewBtn        = $('plViewBtn');
    const periodDropdown = $('plPeriodDropdown');
    const viewDropdown   = $('plViewDropdown');
    const viewLabel      = $('plViewLabel');
    const yearLabel      = $('plYearLabel');

    function closeAllDropdowns() {
        periodDropdown?.classList.remove('show');
        viewDropdown?.classList.remove('show');
    }

    periodBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = periodDropdown.classList.contains('show');
        closeAllDropdowns();
        if (!isOpen) periodDropdown.classList.add('show');
    });
    viewBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = viewDropdown.classList.contains('show');
        closeAllDropdowns();
        if (!isOpen) viewDropdown.classList.add('show');
    });
    periodDropdown?.addEventListener('click', (e) => e.stopPropagation());
    viewDropdown?.addEventListener('click', (e) => e.stopPropagation());
    document.addEventListener('click', closeAllDropdowns);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllDropdowns(); });

    // Nav année dans le dropdown période
    $('plYearPrev')?.addEventListener('click', () => { currentYear--; if (yearLabel) yearLabel.textContent = currentYear; });
    $('plYearNext')?.addEventListener('click', () => { currentYear++; if (yearLabel) yearLabel.textContent = currentYear; });

    // Sélection mois
    document.querySelectorAll('.dd-month').forEach(btn => {
        btn.addEventListener('click', () => gotoMonth(currentYear, parseInt(btn.dataset.month, 10)));
    });
    $('plTodayBtn')?.addEventListener('click', () => {
        const now = new Date();
        gotoMonth(now.getFullYear(), now.getMonth() + 1);
    });

    // Sélection vue (semaine / mois)
    document.querySelectorAll('.dd-view-item').forEach(item => {
        item.addEventListener('click', () => {
            const view = item.dataset.view;
            if (viewLabel) viewLabel.textContent = view === 'semaine' ? 'Semaine' : 'Mois';
            document.querySelectorAll('.dd-view-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            setTimeout(closeAllDropdowns, 200);
        });
    });

    // ── Nav arrows ──────────────────────────────────────────────────────────
    $('plNavPrev')?.addEventListener('click', () => {
        let m = currentMonth - 1, y = currentYear;
        if (m < 1) { m = 12; y--; }
        gotoMonth(y, m);
    });
    $('plNavNext')?.addEventListener('click', () => {
        let m = currentMonth + 1, y = currentYear;
        if (m > 12) { m = 1; y++; }
        gotoMonth(y, m);
    });
    $('plNavToday')?.addEventListener('click', () => {
        const now = new Date();
        gotoMonth(now.getFullYear(), now.getMonth() + 1);
    });

    // ── Toggle Provisoire / Finaliser ───────────────────────────────────────
    document.querySelectorAll('.cb-finalize button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cb-finalize button').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
        });
    });

    // ── 5 presets de zoom : XS / SM / MD / STD / LG (STD = défaut) ──────────
    const SIZE_PRESETS = {
        xs:  { cellW: 36, cellH: 32, shiftMinW: 22, shiftH: 18, shiftFs: 9,    dayNumSize: 20, dayNumFs: 11 },
        sm:  { cellW: 48, cellH: 40, shiftMinW: 28, shiftH: 22, shiftFs: 10,   dayNumSize: 24, dayNumFs: 12 },
        md:  { cellW: 56, cellH: 44, shiftMinW: 32, shiftH: 25, shiftFs: 10.5, dayNumSize: 26, dayNumFs: 13 },
        std: { cellW: 64, cellH: 50, shiftMinW: 36, shiftH: 28, shiftFs: 11,   dayNumSize: 28, dayNumFs: 14 },
        lg:  { cellW: 84, cellH: 64, shiftMinW: 50, shiftH: 36, shiftFs: 13,   dayNumSize: 34, dayNumFs: 16 },
    };
    const planningTable = $('plTable');

    function applySize(size) {
        const preset = SIZE_PRESETS[size];
        if (!preset || !planningTable) return;
        planningTable.style.setProperty('--cell-w',       preset.cellW + 'px');
        planningTable.style.setProperty('--cell-h',       preset.cellH + 'px');
        planningTable.style.setProperty('--shift-min-w',  preset.shiftMinW + 'px');
        planningTable.style.setProperty('--shift-h',      preset.shiftH + 'px');
        planningTable.style.setProperty('--shift-fs',     preset.shiftFs + 'px');
        planningTable.style.setProperty('--day-num-size', preset.dayNumSize + 'px');
        planningTable.style.setProperty('--day-num-fs',   preset.dayNumFs + 'px');
        document.querySelectorAll('.size-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
        });
        try { localStorage.setItem('ss_planning_size', size); } catch(e) {}
    }

    document.querySelectorAll('.size-btn').forEach(btn => {
        btn.addEventListener('click', () => applySize(btn.dataset.size));
    });

    let initialSize = 'std';
    try {
        const saved = localStorage.getItem('ss_planning_size');
        if (saved && SIZE_PRESETS[saved]) initialSize = saved;
    } catch(e) {}
    applySize(initialSize);

    // ── Fullscreen toggle ───────────────────────────────────────────────────
    const fullscreenBtn  = $('plFullscreenBtn');
    const fullscreenIcon = $('plFullscreenIcon');
    const ICON_EXPAND    = '<path d="M3 7V3h4M21 7V3h-4M3 17v4h4M21 17v4h-4"/>';
    const ICON_COLLAPSE  = '<path d="M8 3v4H4M16 3v4h4M8 21v-4H4M16 21v-4h4"/>';

    function togglePlFullscreen() {
        const isFs = document.body.classList.toggle('pl-is-fullscreen');
        fullscreenBtn?.classList.toggle('active', isFs);
        if (fullscreenIcon) fullscreenIcon.innerHTML = isFs ? ICON_COLLAPSE : ICON_EXPAND;
    }
    fullscreenBtn?.addEventListener('click', togglePlFullscreen);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F11') {
            e.preventDefault();
            togglePlFullscreen();
        } else if (e.key === 'Escape' && document.body.classList.contains('pl-is-fullscreen')) {
            togglePlFullscreen();
        }
    });

    // ── Module collapse / expand ────────────────────────────────────────────
    function setModuleCollapsed(moduleCode, collapsed) {
        const moduleRow = document.querySelector(`tr.module-row[data-module-row="${CSS.escape(moduleCode)}"]`);
        if (!moduleRow) return;
        moduleRow.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        document.querySelectorAll(
            `tr.fonction-row[data-module="${CSS.escape(moduleCode)}"], tr.user-row[data-module="${CSS.escape(moduleCode)}"]`
        ).forEach(r => {
            if (collapsed) r.setAttribute('hidden', '');
            else r.removeAttribute('hidden');
        });
    }
    function saveCollapsedState() {
        const state = {};
        document.querySelectorAll('tr.module-row[aria-expanded="false"]').forEach(r => {
            state[r.dataset.moduleRow] = true;
        });
        try { localStorage.setItem('ss_planning_collapsed_modules', JSON.stringify(state)); } catch(e) {}
    }
    function loadCollapsedState() {
        try {
            const saved = JSON.parse(localStorage.getItem('ss_planning_collapsed_modules') || '{}');
            Object.keys(saved).forEach(mc => { if (saved[mc]) setModuleCollapsed(mc, true); });
        } catch(e) {}
    }
    // Click sur la ligne module (mais pas sur la cellule des size-buttons)
    document.querySelectorAll('tr.module-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('.section-controls-cell, .size-controls')) return;
            const moduleCode = row.dataset.moduleRow;
            const isCollapsed = row.getAttribute('aria-expanded') === 'false';
            setModuleCollapsed(moduleCode, !isCollapsed);
            saveCollapsedState();
        });
    });
    loadCollapsedState();

    // ── Filtre équipes (pills) ──────────────────────────────────────────────
    document.querySelectorAll('.team-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.team-pill').forEach(p => p.classList.remove('on'));
            pill.classList.add('on');
            const filter = pill.dataset.teamFilter || '';
            const type   = pill.dataset.teamType   || 'all';
            const allRows = document.querySelectorAll('#plTable tbody tr');

            // Helper : visibilité finale = filtre passé ET (module ouvert OU c'est une module-row)
            allRows.forEach(row => row.removeAttribute('data-filtered-out'));

            if (type === 'all' || !filter) {
                // tout visible (le collapse module reste actif via [hidden])
                return;
            }

            if (type === 'module') {
                // Filtre sur le code module : ne montre que la module-row correspondante + ses descendants
                allRows.forEach(row => {
                    if (row.classList.contains('module-row')) {
                        if (row.dataset.moduleRow !== filter) row.setAttribute('data-filtered-out', '');
                    } else {
                        // fonction-row & user-row : on regarde data-module (module principal)
                        // OU pour user-row on accepte aussi un module secondaire
                        let match = (row.dataset.module === filter);
                        if (!match && row.classList.contains('user-row')) {
                            const userMods = (row.dataset.modules || '').split(/\s+/);
                            if (userMods.includes(filter)) match = true;
                        }
                        if (!match) row.setAttribute('data-filtered-out', '');
                    }
                });
            } else if (type === 'fonction') {
                // Filtre sur fonction : montre toutes les module-row qui ont au moins un user matching,
                // les fonction-row de la fonction sélectionnée, et les user-row de cette fonction
                const modulesWithMatch = new Set();
                allRows.forEach(row => {
                    if (row.classList.contains('user-row') && row.dataset.fonction === filter) {
                        modulesWithMatch.add(row.dataset.module);
                    }
                });
                allRows.forEach(row => {
                    if (row.classList.contains('module-row')) {
                        if (!modulesWithMatch.has(row.dataset.moduleRow)) row.setAttribute('data-filtered-out', '');
                    } else if (row.classList.contains('fonction-row')) {
                        if (row.dataset.teamFonction !== filter) row.setAttribute('data-filtered-out', '');
                    } else if (row.classList.contains('user-row')) {
                        if (row.dataset.fonction !== filter) row.setAttribute('data-filtered-out', '');
                    }
                });
            }
        });
    });

    // ── Boutons d'action (Phase 2) ──────────────────────────────────────────
    $('plGenerateBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') showToast('Génération IA — fonctionnalité Phase 2', 'info');
        else alert('Génération IA — fonctionnalité Phase 2');
    });
    $('plCreateBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') showToast('Création — fonctionnalité Phase 2', 'info');
    });
    $('plPropositionBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') showToast('Propositions — fonctionnalité Phase 2', 'info');
    });

})();

window.initPlanningPage = function() {};
</script>
