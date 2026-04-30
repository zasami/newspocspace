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

// ─── Vraies données planning depuis la DB ───────────────────────────────────
// Format mois_annee : "YYYY-MM" (correspond à la colonne plannings.mois_annee)
$plMoisAnnee = sprintf('%04d-%02d', $plYear, $plMonth);

// Planning courant (peut être null si pas encore créé)
$plPlanning = Db::fetch(
    "SELECT * FROM plannings WHERE mois_annee = ?",
    [$plMoisAnnee]
);

// Map horaire_code → duree_effective (heures par shift)
$plShiftHoursMap = [];
foreach ($planningHoraires as $ht) {
    $code = strtolower((string) ($ht['code'] ?? ''));
    if ($code !== '') {
        $plShiftHoursMap[$code] = (float) ($ht['duree_effective'] ?? 0);
    }
}

// Assignations existantes : tableau [user_id][iso_date] = ['code'=>..., 'id'=>..., 'updated_at'=>..., ...]
$plUserShifts  = [];
$plTotalShifts = 0;
if ($plPlanning) {
    $rawAssignations = Db::fetchAll(
        "SELECT pa.id, pa.user_id, pa.date_jour, pa.horaire_type_id, pa.module_id,
                pa.statut, pa.notes, pa.updated_at,
                ht.code AS horaire_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         WHERE pa.planning_id = ?",
        [$plPlanning['id']]
    );
    foreach ($rawAssignations as $a) {
        $iso = $a['date_jour'];
        $uid = $a['user_id'];
        $plUserShifts[$uid][$iso] = [
            'code'           => strtolower((string) ($a['horaire_code'] ?? '')),
            'id'             => $a['id'],
            'horaire_type_id'=> $a['horaire_type_id'],
            'module_id'      => $a['module_id'],
            'statut'         => $a['statut'],
            'notes'          => $a['notes'],
            'updated_at'     => $a['updated_at'],
        ];
        $plTotalShifts++;
    }
}

// Compteur "Manquants" : couverture cible (à raffiner avec règles métier).
// Pour l'instant : différence entre cible globale et total assigné.
$plMissingShifts = 0; // TODO: calcul réel via admin_get_planning_stats si besoin

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
      <button type="button" class="cb-btn-mini" id="plStatsBtn" title="Statistiques du planning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" id="plFiltersBtn" title="Filtres avancés (TODO)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="11" y2="6"/><line x1="14" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="6" y2="12"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="13" y2="18"/><line x1="16" y1="18" x2="20" y2="18"/><circle cx="12.5" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="14.5" cy="18" r="2"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" id="plClearBtn" title="Vider le planning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </button>
      <button type="button" class="cb-btn-mini cb-fullscreen" id="plFullscreenBtn" title="Plein écran (F11)">
        <svg id="plFullscreenIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V3h4M21 7V3h-4M3 17v4h4M21 17v4h-4"/></svg>
      </button>
    </div>

    <!-- Groupe icônes export (imprimer / PDF / email / CSV) -->
    <div class="cb-icon-group">
      <button type="button" class="cb-btn-mini" id="plPrintBtn" title="Imprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" id="plPdfBtn" title="Exporter PDF (via impression)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" id="plEmailBtn" title="Envoyer par email">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
      </button>
      <button type="button" class="cb-btn-mini" id="plCsvBtn" title="Exporter CSV">
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
            foreach ($userShifts as $sh) {
                $heuresCourant += $plShiftHoursMap[$sh['code']] ?? 0;
            }
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
              $sh = $userShifts[$day['iso']] ?? null;
              $code = $sh['code'] ?? null;
            ?>
            <td class="<?= $day['weekend'] ? 'weekend' : '' ?> <?= $day['today'] ? 'today' : '' ?> day-cell"
                data-uid="<?= h($u['id']) ?>"
                data-date="<?= h($day['iso']) ?>"
                <?php if ($sh): ?>data-assign-id="<?= h($sh['id']) ?>"
                  data-updated-at="<?= h($sh['updated_at'] ?? '') ?>"
                  data-horaire-type-id="<?= h($sh['horaire_type_id'] ?? '') ?>"
                  data-horaire-code="<?= h($sh['code'] ?? '') ?>"
                  data-module-id="<?= h($sh['module_id'] ?? '') ?>"
                  data-statut="<?= h($sh['statut'] ?? 'present') ?>"
                  data-notes="<?= h($sh['notes'] ?? '') ?>"<?php endif; ?>>
              <?php if ($code): ?>
              <span class="shift <?= h($code) ?>" data-shift="<?= h($code) ?>"><?= strtoupper(h($code)) ?></span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="col-hours">
              <div class="hours-cell">
                <div class="hours-main"><?= (int) round($heuresCourant) ?>h</div>
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

  <!-- ═══ Modale Filtres avancés ═══════════════════════════════════════════ -->
  <div id="plFiltersModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="plFiltersModalTitle">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">

      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 id="plFiltersModalTitle" class="font-display text-base font-semibold text-ink">Filtres avancés</h3>
        <button type="button" id="plFiltersClose" class="w-8 h-8 grid place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink transition-colors" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="px-5 py-4 space-y-4">
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Nom du collaborateur</label>
          <input type="text" id="plFiltersSearch" placeholder="Rechercher (Marie, Dubois...)" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100" autocomplete="off">
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Taux minimum</label>
          <select id="plFiltersTaux" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100">
            <option value="0">Tous</option>
            <option value="100">100% uniquement</option>
            <option value="80">≥ 80%</option>
            <option value="50">≥ 50%</option>
            <option value="20">≥ 20%</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">État cellules</label>
          <div class="flex gap-2 flex-wrap">
            <label class="inline-flex items-center gap-2 text-sm text-ink-2 cursor-pointer">
              <input type="checkbox" id="plFiltersHideEmpty" class="rounded border-line text-teal-600 focus:ring-teal-100">
              Masquer collaborateurs sans aucun shift
            </label>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between px-5 py-3 border-t border-line bg-surface-2">
        <button type="button" id="plFiltersReset" class="px-3 py-2 rounded-lg text-sm text-muted hover:text-ink-2 transition-colors">Réinitialiser</button>
        <div class="flex items-center gap-2">
          <button type="button" id="plFiltersCancel" class="px-4 py-2 rounded-lg border border-line bg-white text-ink-2 text-sm font-medium hover:border-teal-300 hover:text-teal-600 transition-colors">Fermer</button>
          <button type="button" id="plFiltersApply" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 transition-colors">Appliquer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ Modale Propositions ══════════════════════════════════════════════ -->
  <div id="plPropsModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">

      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 class="font-display text-base font-semibold text-ink">Propositions de planning</h3>
        <button type="button" id="plPropsClose" class="w-8 h-8 grid place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink transition-colors" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div id="plPropsBody" class="px-5 py-4 overflow-y-auto flex-1">
        <div class="text-center py-8 text-muted">Chargement…</div>
      </div>

      <div class="flex items-center justify-end px-5 py-3 border-t border-line bg-surface-2">
        <button type="button" id="plPropsCloseBtn" class="px-4 py-2 rounded-lg border border-line bg-white text-ink-2 text-sm font-medium hover:border-teal-300 hover:text-teal-600 transition-colors">Fermer</button>
      </div>
    </div>
  </div>

  <!-- ═══ Modale Statistiques ══════════════════════════════════════════════ -->
  <div id="plStatsModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="plStatsModalTitle">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 id="plStatsModalTitle" class="font-display text-base font-semibold text-ink">Statistiques du planning</h3>
        <button type="button" id="plStatsClose" class="w-8 h-8 grid place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink transition-colors" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div id="plStatsBody" class="px-5 py-4 overflow-y-auto flex-1">
        <!-- Contenu généré dynamiquement par JS -->
        <div class="text-center py-8 text-muted">
          <svg class="animate-spin mx-auto mb-2" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
          Chargement des statistiques…
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end px-5 py-3 border-t border-line bg-surface-2">
        <button type="button" id="plStatsCloseBtn" class="px-4 py-2 rounded-lg border border-line bg-white text-ink-2 text-sm font-medium hover:border-teal-300 hover:text-teal-600 transition-colors">Fermer</button>
      </div>

    </div>
  </div>

  <!-- ═══ Modale Génération IA ═════════════════════════════════════════════ -->
  <div id="plGenModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="plGenModalTitle">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col" id="plGenModal">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 id="plGenModalTitle" class="font-display text-base font-semibold text-ink">Générer le planning</h3>
        <button type="button" id="plGenClose" class="w-8 h-8 grid place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink transition-colors" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="px-5 py-4 overflow-y-auto flex-1">
        <p class="text-sm text-muted mb-3">Choisissez le mode de génération :</p>

        <!-- 3 cartes mode -->
        <div class="grid grid-cols-3 gap-3 mb-4">
          <button type="button" class="pl-gen-mode group relative p-3.5 rounded-xl border-2 border-line bg-surface-2 hover:border-teal-300 hover:bg-teal-50 transition-all text-left flex flex-col items-center text-center" data-mode="local">
            <span class="w-10 h-10 grid place-items-center rounded-lg bg-ok/10 text-ok mb-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/></svg>
            </span>
            <span class="block font-semibold text-sm text-ink mb-1">Algorithme local</span>
            <span class="block text-[11px] text-muted leading-snug mb-2">Rapide et gratuit.</span>
            <span class="inline-flex items-center gap-1.5 text-[10px]">
              <span class="px-1.5 py-0.5 rounded bg-ok/15 text-ok font-semibold">Gratuit</span>
              <span class="px-1.5 py-0.5 rounded bg-surface-3 text-muted font-mono">~1s</span>
            </span>
          </button>
          <button type="button" class="pl-gen-mode group relative p-3.5 rounded-xl border-2 border-line bg-surface-2 hover:border-teal-300 hover:bg-teal-50 transition-all text-left flex flex-col items-center text-center" data-mode="hybrid">
            <span class="w-10 h-10 grid place-items-center rounded-lg bg-teal-100 text-teal-700 mb-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5z"/></svg>
            </span>
            <span class="block font-semibold text-sm text-ink mb-1">Hybride</span>
            <span class="block text-[11px] text-muted leading-snug mb-2">Local + IA d'optimisation.</span>
            <span class="inline-flex items-center gap-1.5 text-[10px]">
              <span class="px-1.5 py-0.5 rounded bg-info/15 text-info font-semibold">~$0.01</span>
              <span class="px-1.5 py-0.5 rounded bg-surface-3 text-muted font-mono">~10s</span>
            </span>
          </button>
          <button type="button" class="pl-gen-mode group relative p-3.5 rounded-xl border-2 border-line bg-surface-2 hover:border-teal-300 hover:bg-teal-50 transition-all text-left flex flex-col items-center text-center" data-mode="ai">
            <span class="w-10 h-10 grid place-items-center rounded-lg bg-warm/15 text-warm mb-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 10h.01M16 10h.01M9 16c1 1 2 1.5 3 1.5s2-.5 3-1.5"/></svg>
            </span>
            <span class="block font-semibold text-sm text-ink mb-1">IA directe</span>
            <span class="block text-[11px] text-muted leading-snug mb-2">L'IA génère tout.</span>
            <span class="inline-flex items-center gap-1.5 text-[10px]">
              <span class="px-1.5 py-0.5 rounded bg-warn/15 text-warn font-semibold">~$0.05</span>
              <span class="px-1.5 py-0.5 rounded bg-surface-3 text-muted font-mono">~30s</span>
            </span>
          </button>
        </div>

        <!-- Provider info (caché par défaut, montré pour hybrid/ai) -->
        <div id="plGenProviderInfo" class="hidden mb-3 px-3 py-2 rounded-lg border border-line bg-surface-2 text-sm flex items-center justify-between">
          <span>
            <span class="text-muted">Provider :</span> <strong id="plGenProviderName" class="text-ink">—</strong>
            <span class="text-muted ml-2">·</span>
            <span class="text-muted ml-2">Modèle :</span> <strong id="plGenModelName" class="text-ink">—</strong>
          </span>
          <a href="<?= admin_url('config-ia') ?>" class="text-teal-600 hover:text-teal-700 text-xs">Config IA →</a>
        </div>

        <!-- Module filter -->
        <div class="mb-3">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Module à générer</label>
          <select id="plGenModule" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100">
            <option value="">Tous les modules</option>
            <?php foreach ($planningModules as $m): ?>
            <option value="<?= h($m['id']) ?>"><?= h($m['code']) ?> — <?= h($m['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Avertissement -->
        <div class="px-3 py-2 rounded-lg border border-warn-line bg-warn-bg text-warn text-xs flex items-start gap-2">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
          <span>Les assignations existantes du module sélectionné seront <strong>remplacées</strong>.</span>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-line bg-surface-2">
        <button type="button" id="plGenCancel" class="px-4 py-2 rounded-lg border border-line bg-white text-ink-2 text-sm font-medium hover:border-teal-300 hover:text-teal-600 transition-colors">Annuler</button>
        <button type="button" id="plGenConfirm" class="px-4 py-2 rounded-lg bg-teal-900 text-white text-sm font-semibold hover:bg-black disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2" disabled>
          <svg id="plGenSpinner" class="hidden animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
          <span id="plGenConfirmLabel">Sélectionnez un mode</span>
        </button>
      </div>

    </div>
  </div>

  <!-- ═══ Modale édition cellule ═══════════════════════════════════════════ -->
  <div id="plCellModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="plCellModalTitle">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col" id="plCellModal">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 id="plCellModalTitle" class="font-display text-base font-semibold text-ink truncate">Modifier l'assignation</h3>
        <button type="button" id="plCellClose" class="w-8 h-8 grid place-items-center rounded-lg text-muted hover:bg-surface-3 hover:text-ink transition-colors" aria-label="Fermer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="px-5 py-4 overflow-y-auto flex-1">
        <input type="hidden" id="plCellUserId">
        <input type="hidden" id="plCellDate">
        <input type="hidden" id="plCellAssignId">
        <input type="hidden" id="plCellUpdatedAt">

        <!-- Horaires : grille de cards -->
        <div class="mb-4">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-2">Horaire</label>
          <div id="plCellHoraireGrid" class="grid grid-cols-3 gap-2">
            <?php foreach ($planningHoraires as $ht):
              $color = $ht['couleur'] ?? '#6b8783';
              $code  = strtolower($ht['code'] ?? '');
            ?>
            <button type="button" class="pl-horaire-card relative px-3 py-2.5 rounded-lg border-2 border-line bg-surface-2 hover:border-teal-300 hover:bg-teal-50 transition-all text-left flex items-center gap-2.5" data-horaire-id="<?= h($ht['id']) ?>" data-horaire-code="<?= h($code) ?>">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= h($color) ?>"></span>
              <span class="flex-1 min-w-0">
                <span class="block font-mono text-[11px] font-bold text-ink leading-tight"><?= h(strtoupper($code)) ?></span>
                <span class="block text-[10px] text-muted truncate"><?= h(substr((string)($ht['heure_debut'] ?? ''), 0, 5)) ?>–<?= h(substr((string)($ht['heure_fin'] ?? ''), 0, 5)) ?></span>
              </span>
            </button>
            <?php endforeach; ?>
            <button type="button" class="pl-horaire-card pl-horaire-none relative px-3 py-2.5 rounded-lg border-2 border-line bg-surface-2 hover:border-teal-300 hover:bg-teal-50 transition-all text-left flex items-center gap-2.5" data-horaire-id="" data-horaire-code="">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 border border-line bg-white"></span>
              <span class="flex-1 min-w-0">
                <span class="block font-mono text-[11px] font-bold text-ink-2 leading-tight">—</span>
                <span class="block text-[10px] text-muted">Repos</span>
              </span>
            </button>
          </div>
        </div>

        <!-- Module + Statut -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Module</label>
            <select id="plCellModule" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100">
              <option value="">— Aucun —</option>
              <?php foreach ($planningModules as $m): ?>
              <option value="<?= h($m['id']) ?>"><?= h($m['code']) ?> · <?= h($m['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Statut</label>
            <select id="plCellStatut" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100">
              <option value="present">Présent</option>
              <option value="absent">Absent</option>
              <option value="formation">Formation</option>
              <option value="conge">Congé</option>
            </select>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Notes</label>
          <textarea id="plCellNotes" rows="2" maxlength="500" class="w-full text-sm px-3 py-2 rounded-lg border border-line bg-white focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-100 resize-none"></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-5 py-3 border-t border-line bg-surface-2">
        <button type="button" id="plCellDelete" class="hidden inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-danger text-white text-sm font-medium hover:bg-danger/90 transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Supprimer
        </button>
        <div class="flex items-center gap-2 ml-auto">
          <button type="button" id="plCellCancel" class="px-4 py-2 rounded-lg border border-line bg-white text-ink-2 text-sm font-medium hover:border-teal-300 hover:text-teal-600 transition-colors">Annuler</button>
          <button type="button" id="plCellSave" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 transition-colors">Enregistrer</button>
        </div>
      </div>

    </div>
  </div>

</div>

<script<?= nonce() ?>>
// ═════════════════════════════════════════════════════════════════════════════
// Données serveur exposées au JS
// ═════════════════════════════════════════════════════════════════════════════
window.PL_DATA = {
    planning: <?= $plPlanning ? json_encode([
        'id'         => $plPlanning['id'],
        'mois_annee' => $plPlanning['mois_annee'],
        'statut'     => $plPlanning['statut'],
    ], JSON_UNESCAPED_UNICODE) : 'null' ?>,
    moisAnnee: <?= json_encode($plMoisAnnee) ?>,
    csrfToken: <?= json_encode($_SESSION['ss_csrf_token'] ?? '') ?>,
    users: <?= json_encode(array_map(fn($u) => [
        'id'         => $u['id'],
        'prenom'     => $u['prenom'],
        'nom'        => $u['nom'],
        'module_ids' => $u['module_ids'] ?? '',
    ], $planningUsers), JSON_UNESCAPED_UNICODE) ?>,
};
</script>

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
    // XS calé exactement sur le legacy spocspace/admin/assets/css/admin.css
    // (.tr-grid .dc { min-width:36px; height:28px }) puis progression douce.
    const SIZE_PRESETS = {
        xs:  { cellW: 36, cellH: 28, shiftMinW: 28, shiftH: 20, shiftFs: 10,   dayNumSize: 20, dayNumFs: 11 },
        sm:  { cellW: 46, cellH: 36, shiftMinW: 32, shiftH: 24, shiftFs: 10.5, dayNumSize: 22, dayNumFs: 12 },
        md:  { cellW: 56, cellH: 44, shiftMinW: 38, shiftH: 27, shiftFs: 11,   dayNumSize: 26, dayNumFs: 13 },
        std: { cellW: 64, cellH: 50, shiftMinW: 42, shiftH: 30, shiftFs: 11.5, dayNumSize: 28, dayNumFs: 14 },
        lg:  { cellW: 84, cellH: 64, shiftMinW: 56, shiftH: 38, shiftFs: 13,   dayNumSize: 34, dayNumFs: 16 },
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

    // ── Drag-to-scroll : clic-glisser sur la grille pour scroller ───────────
    const plGridEl = document.querySelector('#plGridWrap .planning-grid');
    if (plGridEl) {
        let pgIsDown = false, pgStartX = 0, pgStartY = 0, pgScrollL = 0, pgScrollT = 0, pgDidDrag = false;
        const PG_DRAG_THRESHOLD = 6; // px avant que ça compte comme un drag

        plGridEl.addEventListener('mousedown', (e) => {
            // N'active pas le drag sur les éléments interactifs
            if (e.target.closest('.shift, button, a, input, select, .module-toggle')) return;
            if (e.button !== 0) return; // bouton gauche uniquement
            pgIsDown = true;
            pgDidDrag = false;
            pgStartX = e.pageX;
            pgStartY = e.pageY;
            pgScrollL = plGridEl.scrollLeft;
            pgScrollT = plGridEl.scrollTop;
        });

        plGridEl.addEventListener('mousemove', (e) => {
            if (!pgIsDown) return;
            const dx = e.pageX - pgStartX;
            const dy = e.pageY - pgStartY;
            if (!pgDidDrag && Math.abs(dx) < PG_DRAG_THRESHOLD && Math.abs(dy) < PG_DRAG_THRESHOLD) return;
            if (!pgDidDrag) {
                pgDidDrag = true;
                plGridEl.classList.add('grabbing');
            }
            e.preventDefault();
            plGridEl.scrollLeft = pgScrollL - dx;
            plGridEl.scrollTop  = pgScrollT - dy;
        });

        function pgStop(e) {
            if (!pgIsDown) return;
            pgIsDown = false;
            plGridEl.classList.remove('grabbing');
            // Empêche le click suivant après un drag (pour ne pas ouvrir un éditeur)
            if (pgDidDrag) {
                const blocker = (ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();
                    plGridEl.removeEventListener('click', blocker, true);
                };
                plGridEl.addEventListener('click', blocker, true);
            }
        }
        plGridEl.addEventListener('mouseup', pgStop);
        plGridEl.addEventListener('mouseleave', pgStop);
    }

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

    // ── API helper ──────────────────────────────────────────────────────────
    // adminApiPost est défini dans admin/assets/js/helpers.js (chargé en
    // amont par le shell admin). Si indisponible, fallback fetch direct.
    async function plApiPost(action, data = {}) {
        if (typeof adminApiPost === 'function') {
            return await adminApiPost(action, data);
        }
        const csrf = window.PL_DATA?.csrfToken || '';
        const res = await fetch('/newspocspace/admin/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        });
        return await res.json();
    }
    function plToast(msg, type = 'info') {
        if (typeof showToast === 'function') showToast(msg, type);
        else if (type === 'error') alert(msg); else console.log('[planning]', type, msg);
    }

    // ── Modale d'édition cellule ────────────────────────────────────────────
    const plModalBackdrop = $('plCellModalBackdrop');
    const plModalTitle    = $('plCellModalTitle');
    const plModalUserId   = $('plCellUserId');
    const plModalDate     = $('plCellDate');
    const plModalAssignId = $('plCellAssignId');
    const plModalUpdated  = $('plCellUpdatedAt');
    const plModalModule   = $('plCellModule');
    const plModalStatut   = $('plCellStatut');
    const plModalNotes    = $('plCellNotes');
    const plModalDelete   = $('plCellDelete');
    const plModalSave     = $('plCellSave');

    function plOpenCellModal(td) {
        if (!plModalBackdrop) return;
        const userId = td.dataset.uid;
        const date   = td.dataset.date;
        if (!userId || !date) return;

        // Infos user pour le titre + module principal de fallback
        const user = (window.PL_DATA?.users || []).find(u => u.id === userId);
        plModalTitle.textContent = (user ? `${user.prenom} ${user.nom}` : 'Collaborateur') + ' — ' + date;
        plModalUserId.value   = userId;
        plModalDate.value     = date;
        plModalAssignId.value = td.dataset.assignId || '';
        plModalUpdated.value  = td.dataset.updatedAt || '';

        // Horaire pré-sélectionné : par horaire_type_id si dispo, sinon code
        const targetHoraireId   = td.dataset.horaireTypeId || '';
        const targetHoraireCode = td.dataset.horaireCode || '';
        document.querySelectorAll('.pl-horaire-card').forEach(card => {
            const isActive = targetHoraireId
                ? (card.dataset.horaireId === targetHoraireId)
                : (targetHoraireCode && card.dataset.horaireCode === targetHoraireCode);
            card.classList.toggle('!border-teal-600', isActive);
            card.classList.toggle('!bg-teal-50', isActive);
            card.dataset.selected = isActive ? '1' : '';
        });
        // Si aucune horaire sélectionnée, sélectionne la card "—" (repos)
        if (!targetHoraireId && !targetHoraireCode) {
            const noneCard = document.querySelector('.pl-horaire-none');
            if (noneCard) {
                noneCard.classList.add('!border-teal-600', '!bg-teal-50');
                noneCard.dataset.selected = '1';
            }
        }

        // Module : valeur existante > module principal du user > vide
        const userModuleIds = (user?.module_ids || '').split(',').filter(Boolean);
        plModalModule.value = td.dataset.moduleId || userModuleIds[0] || '';

        // Statut + notes : valeurs existantes ou défaut
        plModalStatut.value = td.dataset.statut || 'present';
        plModalNotes.value  = td.dataset.notes || '';

        plModalDelete.classList.toggle('hidden', !td.dataset.assignId);

        plModalBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function plCloseCellModal() {
        plModalBackdrop?.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Sélection d'une carte horaire
    document.querySelectorAll('.pl-horaire-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.pl-horaire-card').forEach(c => {
                c.classList.remove('!border-teal-600', '!bg-teal-50');
                c.dataset.selected = '';
            });
            card.classList.add('!border-teal-600', '!bg-teal-50');
            card.dataset.selected = '1';
        });
    });

    // Click sur cellule jour → ouvre la modale (uniquement si planning existe)
    document.querySelectorAll('td.day-cell').forEach(td => {
        td.addEventListener('click', (e) => {
            // N'ouvre pas si on était en train de drag-scroll (déjà géré par drag)
            if (!window.PL_DATA?.planning) {
                plToast('Aucun planning créé pour ce mois — clic sur Créer', 'info');
                return;
            }
            plOpenCellModal(td);
        });
    });

    // Boutons fermeture
    $('plCellClose')?.addEventListener('click', plCloseCellModal);
    $('plCellCancel')?.addEventListener('click', plCloseCellModal);
    plModalBackdrop?.addEventListener('click', (e) => {
        if (e.target === plModalBackdrop) plCloseCellModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !plModalBackdrop.classList.contains('hidden')) plCloseCellModal();
    });

    // Save
    plModalSave?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) return;
        const selectedCard = document.querySelector('.pl-horaire-card[data-selected="1"]');
        const horaireId = selectedCard?.dataset.horaireId || null;
        const res = await plApiPost('admin_save_assignation', {
            planning_id: window.PL_DATA.planning.id,
            user_id: plModalUserId.value,
            date_jour: plModalDate.value,
            horaire_type_id: horaireId || null,
            module_id: plModalModule.value || null,
            statut: plModalStatut.value || 'present',
            notes: plModalNotes.value || '',
            expected_updated_at: plModalUpdated.value || undefined,
        });
        if (res?.conflict) {
            plToast('⚠ Conflit : la cellule a été modifiée ailleurs. Rechargement…', 'error');
            plCloseCellModal();
            setTimeout(() => location.reload(), 800);
        } else if (res?.success) {
            plCloseCellModal();
            location.reload();
        } else {
            plToast(res?.message || 'Erreur lors de l\'enregistrement', 'error');
        }
    });

    // Delete
    plModalDelete?.addEventListener('click', async () => {
        const assignId = plModalAssignId.value;
        if (!assignId) return;
        if (!confirm('Supprimer cette assignation ?')) return;
        const res = await plApiPost('admin_delete_assignation', {
            id: assignId,
            expected_updated_at: plModalUpdated.value || undefined,
        });
        if (res?.success) {
            plCloseCellModal();
            location.reload();
        } else {
            plToast(res?.message || 'Erreur lors de la suppression', 'error');
        }
    });

    // ── Bouton Créer planning ───────────────────────────────────────────────
    $('plCreateBtn')?.addEventListener('click', async () => {
        if (window.PL_DATA?.planning) {
            plToast('Un planning existe déjà pour ce mois', 'info');
            return;
        }
        if (!confirm('Créer un planning vide pour ' + (window.PL_DATA?.moisAnnee || 'ce mois') + ' ?')) return;
        const res = await plApiPost('admin_create_planning', { mois: window.PL_DATA.moisAnnee });
        if (res?.success) {
            plToast(res.message || 'Planning créé', 'ok');
            setTimeout(() => location.reload(), 600);
        } else {
            plToast(res?.message || 'Erreur création planning', 'error');
        }
    });

    // ── Bouton Vider planning ───────────────────────────────────────────────
    $('plClearBtn')?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) {
            plToast('Aucun planning à vider', 'info');
            return;
        }
        if (!confirm('Vider TOUTES les assignations du planning ' + (window.PL_DATA.moisAnnee || '') + ' ? Cette action est irréversible.')) return;
        const res = await plApiPost('admin_clear_planning', { planning_id: window.PL_DATA.planning.id });
        if (res?.success) {
            plToast(res.message || 'Planning vidé', 'ok');
            setTimeout(() => location.reload(), 600);
        } else {
            plToast(res?.message || 'Erreur', 'error');
        }
    });

    // ── Bouton Imprimer (browser native + style print) ──────────────────────
    $('plPrintBtn')?.addEventListener('click', () => window.print());
    $('plPdfBtn')?.addEventListener('click', () => {
        plToast('Choisissez "Enregistrer en PDF" dans la boîte d\'impression', 'info');
        window.print();
    });

    // ── Bouton Email — TODO modal email ─────────────────────────────────────
    $('plEmailBtn')?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) {
            plToast('Aucun planning à envoyer', 'info');
            return;
        }
        const email = prompt('Adresse email du destinataire ?\n(laisse vide pour envoyer à tous les collaborateurs actifs)');
        if (email === null) return; // cancel
        const res = await plApiPost('admin_send_planning_email', {
            planning_id: window.PL_DATA.planning.id,
            email: email.trim() || null,
        });
        if (res?.success) {
            plToast(res.message || 'Email envoyé', 'ok');
        } else {
            plToast(res?.message || 'Erreur envoi email', 'error');
        }
    });

    // ── Bouton CSV (download direct via endpoint dédié) ─────────────────────
    $('plCsvBtn')?.addEventListener('click', () => {
        if (!window.PL_DATA?.planning) {
            plToast('Aucun planning à exporter', 'info');
            return;
        }
        // Download direct : on utilise un endpoint Excel/CSV legacy si dispo.
        // Sinon on génère un CSV côté JS depuis la grille rendue.
        plExportCsv();
    });

    function plExportCsv() {
        const rows = [];
        // Header
        const headRow = ['Collaborateur', '%'];
        document.querySelectorAll('#plTable thead th.day-head').forEach(th => {
            const name = th.querySelector('.day-name')?.textContent.trim() || '';
            const num = th.querySelector('.day-num')?.textContent.trim() || '';
            headRow.push(`${name} ${num}`);
        });
        headRow.push('Heures');
        rows.push(headRow);

        // Body
        document.querySelectorAll('#plTable tbody tr.user-row').forEach(tr => {
            const r = [];
            r.push(tr.querySelector('.collab-cell-name')?.textContent.trim() || '');
            r.push(tr.querySelector('.pct-cell')?.textContent.trim() || '');
            tr.querySelectorAll('td.day-cell').forEach(td => {
                r.push(td.querySelector('.shift')?.textContent.trim() || '');
            });
            r.push(tr.querySelector('.hours-main')?.textContent.trim() || '');
            rows.push(r);
        });

        const csv = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(';')).join('\r\n');
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }); // BOM UTF-8 pour Excel
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'planning-' + (window.PL_DATA?.moisAnnee || 'export') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // ── Modale Statistiques (Tailwind) ──────────────────────────────────────
    const plStatsBackdrop = $('plStatsModalBackdrop');
    const plStatsBody     = $('plStatsBody');

    function plStatsClose() {
        plStatsBackdrop?.classList.add('hidden');
        document.body.style.overflow = '';
    }
    $('plStatsClose')?.addEventListener('click', plStatsClose);
    $('plStatsCloseBtn')?.addEventListener('click', plStatsClose);
    plStatsBackdrop?.addEventListener('click', (e) => {
        if (e.target === plStatsBackdrop) plStatsClose();
    });

    function plEsc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        })[c]);
    }
    function plRenderStats(stats, mois) {
        if (!stats) {
            plStatsBody.innerHTML = '<div class="text-center py-8 text-muted">Aucune statistique disponible — créez et générez le planning d\'abord.</div>';
            return;
        }
        const t = stats.totals || {};
        const users = stats.heures_par_user || [];
        const gaps = stats.gaps || [];

        let html = '';
        // ── 1) Stat cards ────────────────────────────────────────────────
        html += '<div class="grid grid-cols-4 gap-3 mb-5">';
        html += `<div class="px-3 py-3 rounded-xl border border-line bg-surface-2">
                   <div class="text-[10px] uppercase tracking-wider text-muted font-semibold mb-1">Employés</div>
                   <div class="font-display text-2xl font-bold text-ink leading-none">${plEsc(t.nb_employes || 0)}</div>
                 </div>`;
        html += `<div class="px-3 py-3 rounded-xl border border-line bg-surface-2">
                   <div class="text-[10px] uppercase tracking-wider text-muted font-semibold mb-1">Assignations</div>
                   <div class="font-display text-2xl font-bold text-ink leading-none">${plEsc(t.nb_assignations || 0)}</div>
                 </div>`;
        html += `<div class="px-3 py-3 rounded-xl border border-line bg-surface-2">
                   <div class="text-[10px] uppercase tracking-wider text-muted font-semibold mb-1">Heures totales</div>
                   <div class="font-display text-2xl font-bold text-ink leading-none">${plEsc(Math.round(t.total_heures || 0))}h</div>
                 </div>`;
        const gapColor = gaps.length > 0 ? 'text-warn' : 'text-ok';
        html += `<div class="px-3 py-3 rounded-xl border border-line bg-surface-2">
                   <div class="text-[10px] uppercase tracking-wider text-muted font-semibold mb-1">Manques</div>
                   <div class="font-display text-2xl font-bold ${gapColor} leading-none">${plEsc(stats.nb_gaps || 0)}</div>
                 </div>`;
        html += '</div>';

        // ── 2) Heures par collaborateur ───────────────────────────────────
        html += `<div class="mb-5">
          <h4 class="font-display text-sm font-semibold text-ink mb-2 flex items-center gap-2">
            <span>Heures par collaborateur</span>
            <span class="text-[11px] text-muted font-normal">${users.length} employés · ${stats.jours_ouvrables} j ouvrables sur ${stats.jours_mois}</span>
          </h4>`;
        if (users.length === 0) {
            html += '<div class="text-sm text-muted italic px-3 py-4">Aucune donnée</div>';
        } else {
            html += `<div class="border border-line rounded-lg overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-surface-2 text-[10px] uppercase tracking-wider text-muted font-semibold">
                  <tr>
                    <th class="text-left px-3 py-2">Collaborateur</th>
                    <th class="text-center px-2 py-2">Fonction</th>
                    <th class="text-right px-2 py-2">Taux</th>
                    <th class="text-right px-2 py-2">Réelles</th>
                    <th class="text-right px-2 py-2">Cibles</th>
                    <th class="text-right px-3 py-2">Écart</th>
                  </tr>
                </thead>
                <tbody>`;
            users.forEach(u => {
                const ecart = parseFloat(u.ecart || 0);
                const ecartClass = ecart > 1 ? 'text-ok font-semibold' : (ecart < -1 ? 'text-warn font-semibold' : 'text-muted');
                const ecartSign = ecart > 0 ? '+' : '';
                html += `<tr class="border-t border-line hover:bg-teal-50/50">
                  <td class="px-3 py-2">${plEsc(u.prenom || '')} ${plEsc(u.nom || '')}</td>
                  <td class="text-center px-2 py-2 text-[11px] text-muted">${plEsc(u.fonction_code || '—')}</td>
                  <td class="text-right px-2 py-2 font-mono text-[12px] text-ink-2">${plEsc(Math.round(u.taux || 0))}%</td>
                  <td class="text-right px-2 py-2 font-mono text-[12px] text-ink">${plEsc(Math.round(u.total_heures || 0))}h</td>
                  <td class="text-right px-2 py-2 font-mono text-[12px] text-muted">${plEsc(u.heures_cibles || 0)}h</td>
                  <td class="text-right px-3 py-2 font-mono text-[12px] ${ecartClass}">${ecartSign}${plEsc(u.ecart || 0)}h</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';

        // ── 3) Manques de couverture ──────────────────────────────────────
        if (gaps.length > 0) {
            // Group by date
            const byDate = {};
            gaps.forEach(g => {
                if (!byDate[g.date]) byDate[g.date] = [];
                byDate[g.date].push(g);
            });
            html += `<div>
              <h4 class="font-display text-sm font-semibold text-warn mb-2 flex items-center gap-2">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                Manques de couverture
                <span class="text-[11px] text-muted font-normal">${gaps.length} manque(s) sur ${Object.keys(byDate).length} jour(s)</span>
              </h4>
              <div class="space-y-1.5">`;
            Object.keys(byDate).sort().forEach(date => {
                const dayGaps = byDate[date];
                html += `<div class="px-3 py-2 rounded-lg border border-warn-line bg-warn-bg/40 text-sm">
                  <span class="font-semibold text-warn">${plEsc(date)}</span>
                  <span class="text-muted ml-2">·</span>
                  ${dayGaps.map(g => `<span class="ml-2 text-ink-2"><span class="font-mono text-[11px] bg-white px-1.5 py-0.5 rounded">${plEsc(g.module_code)}/${plEsc(g.fonction_code)}</span> manque <strong class="text-warn">${plEsc(g.manque)}</strong> (${plEsc(g.present)}/${plEsc(g.requis)})</span>`).join(' ')}
                </div>`;
            });
            html += '</div></div>';
        } else if (stats.nb_assignations > 0) {
            html += `<div class="px-3 py-3 rounded-lg border border-ok-line bg-ok-bg/40 text-sm text-ok flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
              Aucun manque de couverture détecté.
            </div>`;
        }

        plStatsBody.innerHTML = html;
        $('plStatsModalTitle').textContent = 'Statistiques · ' + (mois || window.PL_DATA?.moisAnnee || '');
    }

    $('plStatsBtn')?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) {
            plToast('Aucun planning — créez-en un d\'abord', 'info');
            return;
        }
        // Ouvre la modale en mode loading immédiat
        plStatsBody.innerHTML = `<div class="text-center py-8 text-muted">
          <svg class="animate-spin mx-auto mb-2" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
          Chargement des statistiques…
        </div>`;
        plStatsBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        try {
            const res = await plApiPost('admin_get_planning_stats', { mois: window.PL_DATA.moisAnnee });
            plRenderStats(res?.stats, window.PL_DATA.moisAnnee);
        } catch (e) {
            plStatsBody.innerHTML = '<div class="text-center py-8 text-danger">Erreur : ' + plEsc(e.message || e) + '</div>';
        }
    });

    // ── Modale Filtres avancés ──────────────────────────────────────────────
    const plFiltersBackdrop = $('plFiltersModalBackdrop');
    function plFiltersOpen()  { plFiltersBackdrop?.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function plFiltersClose() { plFiltersBackdrop?.classList.add('hidden');    document.body.style.overflow = ''; }
    $('plFiltersBtn')?.addEventListener('click', plFiltersOpen);
    $('plFiltersClose')?.addEventListener('click', plFiltersClose);
    $('plFiltersCancel')?.addEventListener('click', plFiltersClose);
    plFiltersBackdrop?.addEventListener('click', (e) => { if (e.target === plFiltersBackdrop) plFiltersClose(); });

    function plApplyFilters() {
        const q          = ($('plFiltersSearch')?.value || '').trim().toLowerCase();
        const tauxMin    = parseInt($('plFiltersTaux')?.value || '0', 10);
        const hideEmpty  = !!$('plFiltersHideEmpty')?.checked;

        document.querySelectorAll('#plTable tbody tr.user-row').forEach(row => {
            row.removeAttribute('data-adv-hidden');
            if (q) {
                const name = (row.querySelector('.collab-cell-name')?.textContent || '').toLowerCase();
                if (!name.includes(q)) row.setAttribute('data-adv-hidden', '');
            }
            if (tauxMin > 0) {
                const tauxText = (row.querySelector('.pct-cell')?.textContent || '0').replace('%','').trim();
                const taux = parseInt(tauxText, 10) || 0;
                if (taux < tauxMin) row.setAttribute('data-adv-hidden', '');
            }
            if (hideEmpty) {
                const hasShift = row.querySelectorAll('td.day-cell .shift').length > 0;
                if (!hasShift) row.setAttribute('data-adv-hidden', '');
            }
        });
        plFiltersClose();
    }
    $('plFiltersApply')?.addEventListener('click', plApplyFilters);
    $('plFiltersReset')?.addEventListener('click', () => {
        $('plFiltersSearch').value = '';
        $('plFiltersTaux').value = '0';
        $('plFiltersHideEmpty').checked = false;
        document.querySelectorAll('#plTable tbody tr.user-row').forEach(r => r.removeAttribute('data-adv-hidden'));
        plToast('Filtres réinitialisés', 'info');
    });

    // ── Modale Propositions (sauvegarde + liste) ────────────────────────────
    const plPropsBackdrop = $('plPropsModalBackdrop');
    const plPropsBody     = $('plPropsBody');
    function plPropsClose() { plPropsBackdrop?.classList.add('hidden'); document.body.style.overflow = ''; }
    $('plPropsClose')?.addEventListener('click', plPropsClose);
    $('plPropsCloseBtn')?.addEventListener('click', plPropsClose);
    plPropsBackdrop?.addEventListener('click', (e) => { if (e.target === plPropsBackdrop) plPropsClose(); });

    async function plLoadProposals() {
        plPropsBody.innerHTML = '<div class="text-center py-8 text-muted">Chargement…</div>';
        const res = await plApiPost('admin_get_proposals', { mois: window.PL_DATA?.moisAnnee });
        const props = res?.proposals || [];
        if (!props.length) {
            plPropsBody.innerHTML = '<div class="text-center py-8 text-muted">Aucune proposition pour ce mois.<br><span class="text-xs">Cliquez sur le bouton Proposition pour en créer une.</span></div>';
            return;
        }
        const statutBadge = {
            ouvert:  'bg-ok/15 text-ok',
            ferme:   'bg-surface-3 text-muted',
            valide:  'bg-teal-100 text-teal-700',
            rejete:  'bg-danger/15 text-danger',
        };
        const statutLabel = { ouvert: 'Ouvert', ferme: 'Fermé', valide: 'Validé', rejete: 'Rejeté' };
        let html = '<div class="space-y-2">';
        props.forEach(p => {
            const total = (p.votes_pour || 0) + (p.votes_contre || 0);
            const pctPour = total > 0 ? Math.round((p.votes_pour / total) * 100) : 0;
            html += `<div class="px-3 py-2.5 rounded-lg border border-line bg-surface-2 hover:border-teal-200 transition-colors">
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2 min-w-0">
                  <strong class="text-sm text-ink truncate">${plEsc(p.label)}</strong>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold ${statutBadge[p.statut] || 'bg-surface-3 text-muted'}">${plEsc(statutLabel[p.statut] || p.statut)}</span>
                </div>
                <div class="flex items-center gap-1">
                  ${p.statut === 'ouvert' ? `<button type="button" data-prop-toggle="${plEsc(p.id)}" data-status="ferme" class="px-2 py-1 rounded text-xs text-muted hover:text-ink hover:bg-surface-3" title="Fermer le vote">⏸</button>` : ''}
                  ${p.statut === 'ferme' ? `<button type="button" data-prop-toggle="${plEsc(p.id)}" data-status="ouvert" class="px-2 py-1 rounded text-xs text-muted hover:text-ink hover:bg-surface-3" title="Rouvrir">▶</button>` : ''}
                  ${(p.statut === 'ouvert' || p.statut === 'ferme') ? `<button type="button" data-prop-validate="${plEsc(p.id)}" class="px-2 py-1 rounded text-xs text-teal-600 hover:bg-teal-50" title="Valider et appliquer">✓</button>` : ''}
                  <button type="button" data-prop-delete="${plEsc(p.id)}" class="px-2 py-1 rounded text-xs text-danger hover:bg-danger/10" title="Supprimer">🗑</button>
                </div>
              </div>
              <div class="flex items-center gap-3 text-[11px] text-muted">
                <span><span class="text-ok font-semibold">${plEsc(p.votes_pour || 0)}</span> pour</span>
                <span><span class="text-danger font-semibold">${plEsc(p.votes_contre || 0)}</span> contre</span>
                <span>${plEsc(total)} vote${total > 1 ? 's' : ''}</span>
                ${total > 0 ? `<span class="ml-auto font-mono">${pctPour}% favorable</span>` : ''}
              </div>
              ${total > 0 ? `<div class="mt-1.5 h-1 rounded-full overflow-hidden bg-surface-3"><div class="h-full bg-ok" style="width:${pctPour}%"></div></div>` : ''}
            </div>`;
        });
        html += '</div>';
        plPropsBody.innerHTML = html;

        // Bind action buttons
        plPropsBody.querySelectorAll('[data-prop-toggle]').forEach(b => b.addEventListener('click', async () => {
            const res = await plApiPost('admin_toggle_vote_status', { id: b.dataset.propToggle, statut: b.dataset.status });
            if (res?.success) plLoadProposals();
            else plToast(res?.message || 'Erreur', 'error');
        }));
        plPropsBody.querySelectorAll('[data-prop-validate]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('Valider cette proposition et l\'appliquer comme planning final ?')) return;
            const res = await plApiPost('admin_validate_proposal', { id: b.dataset.propValidate });
            if (res?.success) {
                plToast('Proposition validée et appliquée', 'ok');
                plPropsClose();
                setTimeout(() => location.reload(), 600);
            } else plToast(res?.message || 'Erreur', 'error');
        }));
        plPropsBody.querySelectorAll('[data-prop-delete]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('Supprimer cette proposition ?')) return;
            const res = await plApiPost('admin_delete_proposal', { id: b.dataset.propDelete });
            if (res?.success) plLoadProposals();
            else plToast(res?.message || 'Erreur', 'error');
        }));
    }

    // Bouton Proposition (en haut) : crée une nouvelle proposition (snapshot du planning courant)
    $('plPropositionBtn')?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) {
            plToast('Créez d\'abord un planning pour ce mois', 'info');
            return;
        }
        // Compte les propositions existantes pour suggérer un nom
        const existing = await plApiPost('admin_get_proposals', { mois: window.PL_DATA.moisAnnee });
        const count = (existing?.proposals || []).length;
        const moisNoms = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        const [y, m] = (window.PL_DATA.moisAnnee || '').split('-');
        const moisLabel = (moisNoms[parseInt(m, 10)] || '') + ' ' + y;
        const suggested = 'Choix ' + (count + 1) + ' – planning ' + moisLabel;
        const label = prompt('Nom de la proposition ?', suggested);
        if (!label || !label.trim()) return;
        const res = await plApiPost('admin_create_proposal', {
            mois: window.PL_DATA.moisAnnee,
            label: label.trim(),
        });
        if (res?.success) {
            plToast('Proposition « ' + label.trim() + ' » créée — vote ouvert', 'ok');
        } else {
            plToast(res?.message || 'Erreur création proposition', 'error');
        }
    });

    // Icône liste (à côté du bouton Proposition) : ouvre la modale liste
    document.querySelector('button.cb-btn-mini[title="Voir toutes les propositions"]')?.addEventListener('click', async () => {
        if (!window.PL_DATA?.planning) {
            plToast('Aucun planning — créez-en un d\'abord', 'info');
            return;
        }
        plPropsBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        await plLoadProposals();
    });

    // ── Modale Génération IA (mode local / hybrid / ai) ─────────────────────
    const plGenBackdrop  = $('plGenModalBackdrop');
    const plGenConfirm   = $('plGenConfirm');
    const plGenLabel     = $('plGenConfirmLabel');
    const plGenSpinner   = $('plGenSpinner');
    const plGenProvider  = $('plGenProviderInfo');
    let   plGenSelectedMode = null;
    let   plGenInProgress   = false;

    const PL_GEN_MODES = {
        local:  { label: 'Générer (algorithme local)', icon: '⚙️' },
        hybrid: { label: 'Générer (hybride + IA)',     icon: '✨' },
        ai:     { label: 'Générer (IA directe)',        icon: '🤖' },
    };

    function plGenOpen() {
        if (!window.PL_DATA?.planning) {
            plToast('Créez d\'abord le planning pour ce mois', 'info');
            return;
        }
        // Reset
        plGenSelectedMode = null;
        plGenConfirm.disabled = true;
        plGenLabel.textContent = 'Sélectionnez un mode';
        plGenSpinner.classList.add('hidden');
        plGenProvider.classList.add('hidden');
        document.querySelectorAll('.pl-gen-mode').forEach(c => {
            c.classList.remove('!border-teal-700', '!bg-teal-50', 'ring-2', 'ring-teal-200');
        });
        plGenBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function plGenClose() {
        if (plGenInProgress) return; // Ne ferme pas pendant la génération
        plGenBackdrop?.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Sélection d'un mode
    document.querySelectorAll('.pl-gen-mode').forEach(card => {
        card.addEventListener('click', async () => {
            if (plGenInProgress) return;
            plGenSelectedMode = card.dataset.mode;
            // Visuel
            document.querySelectorAll('.pl-gen-mode').forEach(c => {
                c.classList.remove('!border-teal-700', '!bg-teal-50', 'ring-2', 'ring-teal-200');
            });
            card.classList.add('!border-teal-700', '!bg-teal-50', 'ring-2', 'ring-teal-200');

            const mc = PL_GEN_MODES[plGenSelectedMode];
            plGenConfirm.disabled = false;
            plGenLabel.textContent = mc.icon + ' ' + mc.label;

            // Pour hybrid/ai : check API key
            if (plGenSelectedMode === 'hybrid' || plGenSelectedMode === 'ai') {
                plGenProvider.classList.remove('hidden');
                $('plGenProviderName').textContent = 'Vérification…';
                $('plGenModelName').textContent = '—';
                try {
                    const cfgRes = await plApiPost('admin_get_config');
                    const cfg = cfgRes?.config || {};
                    const prov = cfg.ai_provider || 'gemini';
                    const provName = prov === 'gemini' ? 'Google Gemini' : 'Anthropic Claude';
                    const model = prov === 'gemini' ? (cfg.gemini_model || 'gemini-2.5-flash') : (cfg.anthropic_model || 'claude-haiku-4-5');
                    const hasKey = prov === 'gemini' ? !!cfg.gemini_api_key : !!cfg.anthropic_api_key;
                    $('plGenProviderName').textContent = provName;
                    $('plGenModelName').textContent = model;
                    if (!hasKey) {
                        plGenProvider.classList.add('!border-danger-line', '!bg-danger-bg', '!text-danger');
                        $('plGenProviderName').textContent = '⚠ Aucune clé API ' + provName;
                        plGenConfirm.disabled = true;
                    } else {
                        plGenProvider.classList.remove('!border-danger-line', '!bg-danger-bg', '!text-danger');
                    }
                } catch (e) {
                    plGenProvider.classList.add('!border-danger-line', '!bg-danger-bg', '!text-danger');
                    $('plGenProviderName').textContent = '⚠ Impossible de vérifier la config';
                    plGenConfirm.disabled = true;
                }
            } else {
                plGenProvider.classList.add('hidden');
            }
        });
    });

    // Click "Générer planning" → ouvre la modale
    $('plGenerateBtn')?.addEventListener('click', plGenOpen);

    // Fermeture
    $('plGenClose')?.addEventListener('click', plGenClose);
    $('plGenCancel')?.addEventListener('click', plGenClose);
    plGenBackdrop?.addEventListener('click', (e) => {
        if (e.target === plGenBackdrop) plGenClose();
    });

    // Confirm → lance la génération
    plGenConfirm?.addEventListener('click', async () => {
        if (plGenInProgress || !plGenSelectedMode || !window.PL_DATA?.planning) return;
        plGenInProgress = true;
        plGenConfirm.disabled = true;
        plGenSpinner.classList.remove('hidden');
        const mc = PL_GEN_MODES[plGenSelectedMode];
        plGenLabel.textContent = 'Génération en cours…';

        const moduleFilter = $('plGenModule')?.value || '';
        const data = {
            mois: window.PL_DATA.moisAnnee,
            mode: plGenSelectedMode,
        };
        if (moduleFilter) data.module_id = moduleFilter;

        try {
            const res = await plApiPost('admin_generate_planning', data);
            plGenInProgress = false;
            if (res?.success) {
                let msg = res.message || 'Planning généré';
                if (res.nb_conflicts > 0) msg += ' (' + res.nb_conflicts + ' manques de couverture)';
                plToast(msg, 'ok');
                plGenClose();
                setTimeout(() => location.reload(), 800);
            } else {
                plGenSpinner.classList.add('hidden');
                plGenLabel.textContent = mc.icon + ' ' + mc.label;
                plGenConfirm.disabled = false;
                plToast(res?.message || 'Erreur génération', 'error');
            }
        } catch (e) {
            plGenInProgress = false;
            plGenSpinner.classList.add('hidden');
            plGenLabel.textContent = mc.icon + ' ' + mc.label;
            plGenConfirm.disabled = false;
            plToast('Erreur réseau : ' + (e.message || e), 'error');
        }
    });

    // ── Toggle Provisoire / Finaliser : branche admin_finalize_planning ────
    document.querySelectorAll('.cb-finalize button').forEach(btn => {
        // Le toggle visuel est déjà géré plus haut. On surcharge ici pour
        // appeler l'API quand on bascule.
        btn.addEventListener('click', async () => {
            if (!window.PL_DATA?.planning) {
                plToast('Aucun planning à finaliser — clic sur Créer', 'info');
                return;
            }
            const targetStatut = btn.dataset.finalize === 'finaliser' ? 'final' : 'provisoire';
            // Évite les double-appels si déjà dans cet état
            if (window.PL_DATA.planning.statut === targetStatut) return;
            const res = await plApiPost('admin_finalize_planning', {
                id: window.PL_DATA.planning.id,
                statut: targetStatut,
            });
            if (res?.success) {
                plToast('Planning ' + (targetStatut === 'final' ? 'finalisé' : 'remis en provisoire'), 'ok');
                setTimeout(() => location.reload(), 600);
            } else {
                plToast(res?.message || 'Erreur', 'error');
            }
        });
    });

})();

window.initPlanningPage = function() {};
</script>
