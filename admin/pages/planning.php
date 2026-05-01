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
    "SELECT u.id, u.nom, u.prenom, u.photo, u.taux, u.role, u.type_contrat,
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
$planningEtages    = Db::fetchAll("SELECT id, code, nom, module_id, ordre FROM etages ORDER BY ordre");

// Map module_id → étages (pour calculer rapidement les étages d'un user via ses modules)
$plEtagesByModule = [];
foreach ($planningEtages as $e) {
    $plEtagesByModule[$e['module_id']][] = $e['id'];
}

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

/**
 * Avatar mini (28x28) : photo si dispo, sinon initiales sur fond teal.
 */
function pl_avatar(array $u): string {
    $photo = trim((string)($u['photo'] ?? ''));
    if ($photo !== '') {
        return '<img src="' . h($photo) . '" alt="" class="pl-avatar pl-avatar-img">';
    }
    $initials = strtoupper(
        (mb_substr((string)($u['prenom'] ?? ''), 0, 1) ?: '') .
        (mb_substr((string)($u['nom'] ?? ''), 0, 1) ?: '')
    );
    return '<span class="pl-avatar pl-avatar-initials">' . h($initials) . '</span>';
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

    <!-- Nav arrows : ← Auj → (synchronisé via reload) -->
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

    <!-- Groupe actions droite (Créer + Générer planning + roue dentée IA) :
         contenu dans un seul flex-item non-wrappable pour rester sur la même ligne -->
    <div class="cb-right-actions">
      <!-- Bouton Créer (à gauche du groupe d'actions de droite) -->
      <button type="button" class="cb-btn" id="plCreateBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Créer
      </button>

      <!-- Bouton Générer planning (action primaire dark) -->
      <button type="button" class="cb-btn dark" id="plGenerateBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg>
        Générer planning
      </button>

      <!-- Séparateur vertical -->
      <span class="cb-vsep" aria-hidden="true"></span>

      <!-- Bouton Paramètres IA (icône roue dentée → ouvre modale Règles de génération) -->
      <button type="button" class="cb-btn-mini" id="plIaRulesBtn" title="Règles de génération IA">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
      </button>
    </div>

    <!-- Row break : force le passage à la ligne 2 même sur écran large -->
    <div class="cb-row-break" aria-hidden="true"></div>

    <!-- Groupe icônes outils (stats / supprimer / fullscreen) -->
    <div class="cb-icon-group">
      <button type="button" class="cb-btn-mini" id="plStatsBtn" title="Statistiques du planning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
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
    <!-- Pills : flex-wrap, drag-to-scroll si overflow ─────────── -->
    <div class="team-filters-list" id="plFiltersList">
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
    </div>

    <!-- Boutons de navigation chevrons (visibles seulement si overflow) -->
    <button type="button" class="team-nav-btn" id="plFiltersScrollLeft" title="Filtres précédents" hidden>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    </button>
    <button type="button" class="team-nav-btn" id="plFiltersScrollRight" title="Filtres suivants" hidden>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
    </button>

    <!-- Séparateur vertical entre filtres et zoom -->
    <span class="team-filters-sep" aria-hidden="true"></span>

    <!-- Size controls : 5 boutons visuels (icônes originales xs/sm/md/std/lg)
         mais appliquent en interne les presets compacts : xxxs/xxs/xs/sm/md -->
    <div class="size-controls" role="group" aria-label="Zoom de la grille">
      <button type="button" class="size-btn active" data-size="xxxs" title="Ultra compact (défaut)">
        <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="6" y="6" width="4" height="4" rx="1"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="xxs" title="Extra petit">
        <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="5" y="5" width="6" height="6" rx="1"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="xs" title="Très petit">
        <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor"><rect x="3.5" y="3.5" width="9" height="9" rx="1.5"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="sm" title="Petit">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="2" width="12" height="12" rx="2"/></svg>
      </button>
      <button type="button" class="size-btn" data-size="md" title="Moyen">
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
            <th class="day-head <?= $day['weekend'] ? 'weekend' : '' ?> <?= $day['today'] ? 'today' : '' ?>"
                data-date="<?= h($day['iso']) ?>">
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
                <?= pl_avatar($u) ?>
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

  <!-- ═══ Modale Règles de génération IA ═══════════════════════════════════ -->
  <div id="plIaModalBackdrop" class="pl-ia-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="plIaModalTitle">
    <div class="pl-ia-modal" id="plIaModal">

      <!-- Header gradient -->
      <div class="pl-ia-header">
        <div class="pl-ia-title-wrap">
          <!-- Back button (visible uniquement en mode formulaire) -->
          <button type="button" class="pl-ia-back-btn" id="plIaBackBtn" aria-label="Retour à la liste">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          </button>
          <!-- Icon soleil (visible en mode liste) -->
          <div class="pl-ia-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              <circle cx="12" cy="12" r="4"/>
            </svg>
          </div>
          <div class="pl-ia-title-text">
            <div class="pl-ia-eyebrow" id="plIaEyebrow">Génération automatique</div>
            <h2 class="pl-ia-title" id="plIaModalTitle">Règles de génération IA</h2>
          </div>
        </div>
        <div class="pl-ia-stats">
          <div class="pl-ia-stat">
            <span class="pl-ia-stat-num" id="plIaStatActive">0</span>
            <span class="pl-ia-stat-lbl">Règles actives</span>
          </div>
          <div class="pl-ia-stat">
            <span class="pl-ia-stat-num" id="plIaStatDisabled">0</span>
            <span class="pl-ia-stat-lbl">Désactivées</span>
          </div>
        </div>
        <button class="pl-ia-close" id="plIaClose" type="button" aria-label="Fermer">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>

      <!-- Toolbar : recherche + filtres -->
      <div class="pl-ia-toolbar" id="plIaToolbar">
        <div class="pl-ia-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="text" id="plIaSearchInput" placeholder="Rechercher une règle…" autocomplete="off">
        </div>
        <div class="pl-ia-filters">
          <button type="button" class="pl-ia-pill active" data-ia-filter="all">Toutes <span class="count" id="plIaCntAll">0</span></button>
          <button type="button" class="pl-ia-pill" data-ia-filter="active">Actives <span class="count" id="plIaCntActive">0</span></button>
          <button type="button" class="pl-ia-pill" data-ia-filter="disabled">Désactivées <span class="count" id="plIaCntDisabled">0</span></button>
          <button type="button" class="pl-ia-pill" data-ia-filter="important">Importantes <span class="count" id="plIaCntImportant">0</span></button>
        </div>
      </div>

      <!-- Body : liste OU formulaire -->
      <div class="pl-ia-body" id="plIaBody">
        <div class="pl-ia-loading">
          <svg class="animate-spin" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
          Chargement des règles…
        </div>
      </div>

      <!-- Footer -->
      <div class="pl-ia-footer" id="plIaFooter">
        <div class="pl-ia-footer-left">
          <button type="button" class="pl-ia-btn-secondary" id="plIaConfigBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Config IA avancée
          </button>
          <span class="pl-ia-footer-help">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
            Les règles importantes ne peuvent pas être violées
          </span>
        </div>
        <button type="button" class="pl-ia-btn-primary" id="plIaAddBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Ajouter une règle
        </button>
      </div>

    </div>
  </div>

  <!-- ═══ Modale "Sélectionner les collaborateurs" (picker 2-panel) ════════ -->
  <div id="plIaPickerBackdrop" class="pl-ia-backdrop pl-ia-picker-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="plIaPickerTitle">
    <div class="pl-ia-picker-modal">

      <!-- Header gradient -->
      <div class="pl-ia-header">
        <div class="pl-ia-title-wrap">
          <div class="pl-ia-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
            </svg>
          </div>
          <div class="pl-ia-title-text">
            <div class="pl-ia-eyebrow">Cibler la règle</div>
            <h2 class="pl-ia-title" id="plIaPickerTitle">Sélectionner les collaborateurs</h2>
          </div>
        </div>
        <div class="pl-ia-picker-counter-pill"><strong id="plIaPickerCountTop">0</strong> sélectionnés</div>
        <button class="pl-ia-close" id="plIaPickerClose" type="button" aria-label="Fermer">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>

      <!-- Search bar (sous le header) -->
      <div class="pl-ia-picker-search-bar">
        <div class="pl-ia-picker-search" id="plIaPickerSearchBar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="text" id="plIaPickerSearch" placeholder="Rechercher par nom, prénom, fonction…" autocomplete="off">
          <button type="button" class="pl-ia-picker-search-clear" id="plIaPickerSearchClear" aria-label="Effacer">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </button>
        </div>
      </div>

      <!-- Body 2-panel : sidebar filtres + liste users -->
      <div class="pl-ia-picker-body-2col">

        <!-- Sidebar filtres -->
        <aside class="pl-ia-picker-sidebar" id="plIaPickerSidebar">
          <!-- généré dynamiquement par plIaRenderPickerSidebar() -->
        </aside>

        <!-- Panneau liste -->
        <div class="pl-ia-picker-main">
          <div class="pl-ia-picker-main-toolbar">
            <div class="pl-ia-picker-main-left">
              <strong id="plIaPickerResultCount">0</strong> résultats sur <strong id="plIaPickerTotalCount">0</strong>
            </div>
            <div class="pl-ia-picker-main-right">
              <button type="button" class="pl-ia-picker-quick" id="plIaPickerSelectAll">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Tout sélectionner
              </button>
              <button type="button" class="pl-ia-picker-quick" id="plIaPickerClearAll">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                Tout désélectionner
              </button>
            </div>
          </div>
          <div class="pl-ia-picker-list-scroll" id="plIaPickerList">
            <!-- généré dynamiquement -->
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="pl-ia-picker-footer-2col">
        <div class="pl-ia-picker-footer-info">
          <span class="pl-ia-picker-footer-num" id="plIaPickerFooterCount">0</span>
          <span class="pl-ia-picker-footer-label">collaborateur(s) sélectionné(s)</span>
        </div>
        <div class="pl-ia-form-footer-actions">
          <button type="button" class="pl-ia-btn-secondary" id="plIaPickerCancel">Annuler</button>
          <button type="button" class="pl-ia-btn-primary" id="plIaPickerValidate">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Valider la sélection
            <span class="pl-ia-picker-confirm-badge" id="plIaPickerConfirmBadge">0</span>
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- ═══ Modale Propositions ══════════════════════════════════════════════ -->
  <div id="plPropsModalBackdrop" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">

      <div class="flex items-center justify-between px-5 py-3.5 border-b border-line">
        <h3 class="font-display text-base font-semibold text-ink">Propositions de planning</h3>
        <button type="button" id="plPropsClose" class="pl-modal-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 6 6 18M6 6l12 12"/></svg>
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
        <button type="button" id="plStatsClose" class="pl-modal-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 6 6 18M6 6l12 12"/></svg>
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
        <button type="button" id="plGenClose" class="pl-modal-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 6 6 18M6 6l12 12"/></svg>
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

  <!-- ═══ Modale édition cellule (refonte pixel-perfect maquette) ═════════ -->
  <div id="plCellModalBackdrop" class="pl-cell-overlay" role="dialog" aria-modal="true" aria-labelledby="plCellModalTitle">
    <div class="pl-cell-modal" id="plCellModal">

      <!-- HEADER : gradient teal-700 → teal-500, eyebrow + titre + close -->
      <div class="pl-cell-header">
        <div class="pl-cell-title-wrap">
          <div class="pl-cell-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="4" width="18" height="18" rx="2"></rect>
              <path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"></path>
            </svg>
          </div>
          <div class="pl-cell-title-text">
            <div class="pl-cell-eyebrow">Attribuer un horaire</div>
            <h2 class="pl-cell-title" id="plCellModalTitle">
              <span class="pl-cell-title-name">—</span>
              <span class="pl-cell-title-sep">—</span>
              <span class="pl-cell-title-date">—</span>
            </h2>
          </div>
        </div>
        <button type="button" id="plCellClose" class="pl-cell-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M6 6l12 12M18 6L6 18"></path>
          </svg>
        </button>
      </div>

      <!-- BODY -->
      <div class="pl-cell-body">
        <input type="hidden" id="plCellUserId">
        <input type="hidden" id="plCellDate">
        <input type="hidden" id="plCellAssignId">
        <input type="hidden" id="plCellUpdatedAt">

        <!-- ─── Section Horaire ───────────────────────────────────── -->
        <div class="pl-cell-section">
          <div class="pl-cell-section-head">
            <h3 class="pl-cell-section-title">Horaire</h3>
            <button type="button" class="pl-cell-section-action" id="plCellManageShifts">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
              </svg>
              Gérer les horaires
            </button>
          </div>

          <div class="pl-shifts-grid" id="plCellHoraireGrid">
            <?php foreach ($planningHoraires as $ht):
              $color   = $ht['couleur'] ?? '#6b8783';
              $code    = strtoupper((string)($ht['code'] ?? ''));
              $codeRaw = strtolower((string)($ht['code'] ?? ''));
              $debut   = substr((string)($ht['heure_debut'] ?? ''), 0, 5);
              $fin     = substr((string)($ht['heure_fin'] ?? ''), 0, 5);
              $duree   = number_format((float)($ht['duree_effective'] ?? 0), 2, '.', '');
            ?>
            <button type="button" class="pl-horaire-card" data-horaire-id="<?= h($ht['id']) ?>" data-horaire-code="<?= h($codeRaw) ?>">
              <span class="pl-shift-color-dot" style="background:<?= h($color) ?>"></span>
              <span class="pl-shift-code"><?= h($code) ?></span>
              <span class="pl-shift-time"><?= h($debut) ?>–<?= h($fin) ?></span>
              <span class="pl-shift-duration"><?= h($duree) ?>h</span>
            </button>
            <?php endforeach; ?>

            <!-- Carte "Repos" (pas d'horaire) -->
            <button type="button" class="pl-horaire-card pl-horaire-none" data-horaire-id="" data-horaire-code="">
              <span class="pl-shift-color-dot" style="background:#d4d4d4;border:1px solid #b0b0b0"></span>
              <span class="pl-shift-code">—</span>
              <span class="pl-shift-time">Repos</span>
              <span class="pl-shift-duration">0.00h</span>
            </button>

            <!-- Carte "Nouvel horaire" (création) -->
            <button type="button" class="pl-shift-card-add" id="plCellAddShift">
              <span class="pl-shift-card-add-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                  <path d="M12 5v14M5 12h14"></path>
                </svg>
              </span>
              <span class="pl-shift-card-add-label">Nouvel horaire</span>
              <span class="pl-shift-card-add-hint">Créer un type</span>
            </button>
          </div>
        </div>

        <!-- ─── Section Module + Statut ────────────────────────────── -->
        <div class="pl-cell-section">
          <div class="pl-cell-row-2col">
            <div class="pl-cell-field">
              <label class="pl-cell-field-label">Module</label>
              <div class="pl-cell-select-wrapper">
                <select id="plCellModule" class="pl-cell-select-input">
                  <option value="">— Aucun —</option>
                  <?php foreach ($planningModules as $m): ?>
                  <option value="<?= h($m['id']) ?>"><?= h($m['code']) ?> — <?= h($m['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
                <svg class="pl-cell-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </div>
            </div>
            <div class="pl-cell-field">
              <label class="pl-cell-field-label">Statut</label>
              <div class="pl-cell-select-wrapper">
                <select id="plCellStatut" class="pl-cell-select-input pl-cell-select-with-dot" data-statut-color="ok">
                  <option value="present" data-color="ok">Présent</option>
                  <option value="absent" data-color="danger">Absent</option>
                  <option value="formation" data-color="warn">Formation</option>
                  <option value="conge" data-color="muted">Congé</option>
                </select>
                <span class="pl-cell-select-dot" aria-hidden="true"></span>
                <svg class="pl-cell-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </div>
            </div>
          </div>
        </div>

        <!-- ─── Section Notes ──────────────────────────────────────── -->
        <div class="pl-cell-section">
          <div class="pl-cell-field">
            <label class="pl-cell-field-label">Notes</label>
            <textarea id="plCellNotes" rows="2" maxlength="500" class="pl-cell-notes-textarea" placeholder="Note interne ou information complémentaire (optionnel)…"></textarea>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="pl-cell-footer">
        <button type="button" id="plCellDelete" class="pl-cell-btn-delete" hidden>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Supprimer
        </button>
        <div class="pl-cell-footer-right">
          <button type="button" id="plCellCancel" class="pl-cell-btn-secondary">Fermer</button>
          <button type="button" id="plCellSave" class="pl-cell-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            Enregistrer
          </button>
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
    users: <?= json_encode(array_map(function($u) use ($plEtagesByModule) {
        $modIds = array_filter(explode(',', $u['module_ids'] ?? ''));
        $etageIds = [];
        foreach ($modIds as $mid) {
            foreach (($plEtagesByModule[$mid] ?? []) as $eid) $etageIds[$eid] = true;
        }
        return [
            'id'         => $u['id'],
            'prenom'     => $u['prenom'],
            'nom'        => $u['nom'],
            'photo'      => $u['photo'] ?? '',
            'module_ids' => $u['module_ids'] ?? '',
            'fonction_code' => $u['fonction_code'] ?? '',
            'fonction_nom'  => $u['fonction_nom']  ?? '',
            'etage_ids'  => array_keys($etageIds),
        ];
    }, $planningUsers), JSON_UNESCAPED_UNICODE) ?>,
    horaires: <?= json_encode(array_map(fn($h) => [
        'id'      => $h['id'],
        'code'    => $h['code'],
        'nom'     => $h['nom'],
        'couleur' => $h['couleur'] ?? '#1f6359',
    ], $planningHoraires), JSON_UNESCAPED_UNICODE) ?>,
    modules: <?= json_encode(array_map(fn($m) => [
        'id'   => $m['id'],
        'code' => $m['code'],
        'nom'  => $m['nom'],
    ], $planningModules), JSON_UNESCAPED_UNICODE) ?>,
    fonctions: <?= json_encode(array_map(fn($f) => [
        'id'   => $f['id'],
        'code' => $f['code'],
        'nom'  => $f['nom'],
    ], $planningFonctions), JSON_UNESCAPED_UNICODE) ?>,
    etages: <?= json_encode(array_map(fn($e) => [
        'id'        => $e['id'],
        'code'      => $e['code'],
        'nom'       => $e['nom'],
        'module_id' => $e['module_id'],
    ], $planningEtages), JSON_UNESCAPED_UNICODE) ?>,
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

    // ── Bascule Vue mois ↔ Vue semaine (côté client, masque les colonnes) ───
    let plViewMode = 'mois';      // 'mois' | 'semaine'
    let plWeekStart = null;       // Date (lundi) de la semaine en cours en mode semaine

    // Helpers : ISO date → YYYY-MM-DD
    function plIsoDate(d) {
        const y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), dd = String(d.getDate()).padStart(2,'0');
        return y + '-' + m + '-' + dd;
    }
    // Renvoie le lundi de la semaine contenant la date donnée
    function plMondayOf(d) {
        const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const dow = (x.getDay() + 6) % 7; // 0 = lundi … 6 = dimanche
        x.setDate(x.getDate() - dow);
        return x;
    }
    // Choisit la semaine par défaut : on veut TOUJOURS afficher 7 jours.
    // Si la semaine d'aujourd'hui est entièrement dans le mois → on l'utilise.
    // Sinon → on prend la 1re semaine FULL Mon→Sun entièrement contenue dans le mois.
    // (une semaine qui dépasse le mois ne montre que les jours présents en thead,
    // d'où le bug "seulement 2-3 jours" si on tombe sur le bord du mois.)
    function plDefaultWeekStart() {
        const today = new Date();
        const ths = document.querySelectorAll('#plTable thead th.day-head[data-date]');
        if (!ths.length) return plMondayOf(today);
        const firstIso = ths[0].getAttribute('data-date');
        const lastIso  = ths[ths.length - 1].getAttribute('data-date');
        const todayIso = plIsoDate(today);

        // 1) Si aujourd'hui est dans le mois ET sa semaine tient entièrement dans le mois
        if (todayIso >= firstIso && todayIso <= lastIso) {
            const monday = plMondayOf(today);
            const sunday = new Date(monday); sunday.setDate(sunday.getDate() + 6);
            if (plIsoDate(monday) >= firstIso && plIsoDate(sunday) <= lastIso) return monday;
        }

        // 2) Sinon : 1re semaine FULL (Lun→Dim) entièrement dans le mois
        const [y, m, d] = firstIso.split('-').map(Number);
        const firstDate = new Date(y, m - 1, d);
        let candidate = plMondayOf(firstDate);
        // Si le lundi calculé est avant le 1er du mois, avancer d'1 semaine
        if (plIsoDate(candidate) < firstIso) {
            candidate = new Date(candidate); candidate.setDate(candidate.getDate() + 7);
        }
        // Vérifier que dimanche tient encore dans le mois (sinon prendre la dernière full week)
        const lastSunday = new Date(candidate); lastSunday.setDate(lastSunday.getDate() + 6);
        if (plIsoDate(lastSunday) > lastIso) {
            // Reculer jusqu'à trouver une full week qui tient
            const [ly, lm, ld] = lastIso.split('-').map(Number);
            const lastDate = new Date(ly, lm - 1, ld);
            const lastMonday = plMondayOf(lastDate);
            if (plIsoDate(lastMonday) >= firstIso) candidate = lastMonday;
        }
        return candidate;
    }

    function plApplyView() {
        const ths = document.querySelectorAll('#plTable thead th.day-head[data-date]');
        const tdsAll = document.querySelectorAll('#plTable tbody td.day-cell[data-date]');

        if (plViewMode === 'mois') {
            // Tout afficher
            ths.forEach(th => { th.style.display = ''; });
            tdsAll.forEach(td => { td.style.display = ''; });
        } else {
            // Mode semaine : masquer tous les jours hors [weekStart, weekStart+6]
            if (!plWeekStart) plWeekStart = plDefaultWeekStart();
            const startIso = plIsoDate(plWeekStart);
            const endDate = new Date(plWeekStart); endDate.setDate(endDate.getDate() + 6);
            const endIso = plIsoDate(endDate);
            ths.forEach(th => {
                const iso = th.getAttribute('data-date');
                th.style.display = (iso >= startIso && iso <= endIso) ? '' : 'none';
            });
            tdsAll.forEach(td => {
                const iso = td.getAttribute('data-date');
                td.style.display = (iso >= startIso && iso <= endIso) ? '' : 'none';
            });
        }
        // Persistance
        try { localStorage.setItem('ss_planning_view', plViewMode); } catch(e) {}
        try { if (plWeekStart) localStorage.setItem('ss_planning_week', plIsoDate(plWeekStart)); } catch(e) {}
    }

    // Sélection vue (semaine / mois)
    document.querySelectorAll('.dd-view-item').forEach(item => {
        item.addEventListener('click', () => {
            const view = item.dataset.view;
            plViewMode = view;
            if (view === 'semaine' && !plWeekStart) plWeekStart = plDefaultWeekStart();
            if (viewLabel) viewLabel.textContent = view === 'semaine' ? 'Semaine' : 'Mois';
            document.querySelectorAll('.dd-view-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            plApplyView();
            setTimeout(closeAllDropdowns, 200);
        });
    });

    // Restaure l'état au chargement (vue + semaine)
    try {
        const savedView = localStorage.getItem('ss_planning_view');
        if (savedView === 'semaine' || savedView === 'mois') {
            plViewMode = savedView;
            if (viewLabel) viewLabel.textContent = savedView === 'semaine' ? 'Semaine' : 'Mois';
            document.querySelectorAll('.dd-view-item').forEach(i => i.classList.toggle('active', i.dataset.view === savedView));
            if (savedView === 'semaine') {
                const savedWeek = localStorage.getItem('ss_planning_week');
                if (savedWeek) {
                    const [y, m, d] = savedWeek.split('-').map(Number);
                    const candidate = plMondayOf(new Date(y, m - 1, d));
                    // Si la semaine sauvegardée est dans le mois affiché, la prendre, sinon défaut
                    const ths = document.querySelectorAll('#plTable thead th.day-head[data-date]');
                    const firstIso = ths[0]?.getAttribute('data-date');
                    const lastIso  = ths[ths.length - 1]?.getAttribute('data-date');
                    const candidateIso = plIsoDate(candidate);
                    plWeekStart = (firstIso && candidateIso <= lastIso && candidateIso >= firstIso.substring(0, 8) + '01')
                        ? candidate : plDefaultWeekStart();
                } else {
                    plWeekStart = plDefaultWeekStart();
                }
            }
            plApplyView();
        }
    } catch(e) {}

    // ── Nav arrows (← Auj. →) ──────────────────────────────────────────────
    // Sync visuel instantané AVANT le reload : MAJ label période + active mois
    // dans le dropdown, pour feedback immédiat même sur connexion lente.
    const MOIS_NAMES_FR = {
        1:'Janvier', 2:'Février', 3:'Mars', 4:'Avril', 5:'Mai', 6:'Juin',
        7:'Juillet', 8:'Août', 9:'Septembre', 10:'Octobre', 11:'Novembre', 12:'Décembre'
    };
    const MOIS_EMOJIS = {
        1:'❄️', 2:'💧', 3:'🌱', 4:'🌸', 5:'🌿', 6:'☀️',
        7:'🌻', 8:'🏖️', 9:'🍂', 10:'🎃', 11:'🍁', 12:'🎄'
    };

    function plSyncPeriodUI(year, month) {
        // Label période
        const lbl = $('plPeriodLabel');
        if (lbl) lbl.textContent = (MOIS_NAMES_FR[month] || '') + ' ' + year;
        // Emoji
        const iconEl = document.querySelector('.cb-period-btn .cb-period-icon');
        if (iconEl && MOIS_EMOJIS[month]) iconEl.textContent = MOIS_EMOJIS[month];
        // Year header dans le dropdown
        currentYear = year;
        currentMonth = month;
        if (yearLabel) yearLabel.textContent = year;
        // Active mois dans la grille du dropdown
        document.querySelectorAll('.dd-month').forEach(b => {
            const m = parseInt(b.dataset.month, 10);
            b.classList.toggle('active', m === month);
            b.classList.toggle('past', m < month);
        });
    }

    // Nav prev/next : en mode mois → ±1 mois (reload). En mode semaine → ±7 jours
    // (sans reload si la nouvelle semaine reste dans le mois affiché ; sinon reload
    // sur le mois adjacent et recale la semaine).
    function plNavWeek(dir) {
        if (!plWeekStart) plWeekStart = plDefaultWeekStart();
        const next = new Date(plWeekStart);
        next.setDate(next.getDate() + (dir * 7));
        const ths = document.querySelectorAll('#plTable thead th.day-head[data-date]');
        const firstIso = ths[0]?.getAttribute('data-date');
        const lastIso  = ths[ths.length - 1]?.getAttribute('data-date');
        const nextEnd  = new Date(next); nextEnd.setDate(nextEnd.getDate() + 6);
        const nextStartIso = plIsoDate(next);
        const nextEndIso   = plIsoDate(nextEnd);
        // Si la nouvelle semaine touche un autre mois → reload sur ce mois
        if (firstIso && (nextEndIso < firstIso || nextStartIso > lastIso)) {
            const y = next.getFullYear(), m = next.getMonth() + 1;
            try { localStorage.setItem('ss_planning_week', plIsoDate(next)); } catch(e) {}
            plSyncPeriodUI(y, m);
            gotoMonth(y, m);
            return;
        }
        plWeekStart = next;
        plApplyView();
    }

    $('plNavPrev')?.addEventListener('click', () => {
        if (plViewMode === 'semaine') { plNavWeek(-1); return; }
        let m = currentMonth - 1, y = currentYear;
        if (m < 1) { m = 12; y--; }
        plSyncPeriodUI(y, m);
        gotoMonth(y, m);
    });
    $('plNavNext')?.addEventListener('click', () => {
        if (plViewMode === 'semaine') { plNavWeek(+1); return; }
        let m = currentMonth + 1, y = currentYear;
        if (m > 12) { m = 1; y++; }
        plSyncPeriodUI(y, m);
        gotoMonth(y, m);
    });
    $('plNavToday')?.addEventListener('click', () => {
        const now = new Date();
        const y = now.getFullYear(), m = now.getMonth() + 1;
        if (plViewMode === 'semaine') {
            // Aujourd'hui en mode semaine : recale sur la semaine du jour
            try { localStorage.setItem('ss_planning_week', plIsoDate(plMondayOf(now))); } catch(e) {}
        }
        plSyncPeriodUI(y, m);
        gotoMonth(y, m);
    });

    // ── Toggle Provisoire / Finaliser ───────────────────────────────────────
    document.querySelectorAll('.cb-finalize button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cb-finalize button').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
        });
    });

    // ── 7 presets de zoom : XXXS / XXS / XS / SM / MD / STD / LG ────────────
    // Quand on descend, TOUT scale : cellule, shift, badge role-tag, nom collab,
    // avatar, taux, heures, paddings → la grille reste lisible mais ultra-compacte.
    // Default : XS (très compact, idéal pour vue d'ensemble du mois).
    const SIZE_PRESETS = {
        xxxs: {
            // Cellules CARRÉES : 22×22
            cellW: 22, cellH: 22, shiftMinW: 18, shiftH: 14, shiftFs: 8,    dayNumSize: 14, dayNumFs: 8.5,
            avatarSize: 16, avatarInitialsFs: 7.5, collabNameFs: 9.5, collabPadX: 6, collabPadY: 4, collabGap: 6,
            roleTagH: 14, roleTagFs: 8,    roleTagMinW: 26,
            collabW: 150, pctFs: 9.5,  hoursMainFs: 11, hoursTargetFs: 8,
        },
        xxs: {
            // Cellules CARRÉES : 26×26
            cellW: 26, cellH: 26, shiftMinW: 22, shiftH: 18, shiftFs: 8.5,  dayNumSize: 17, dayNumFs: 9.5,
            avatarSize: 20, avatarInitialsFs: 8.5, collabNameFs: 10.5, collabPadX: 8, collabPadY: 5, collabGap: 7,
            roleTagH: 16, roleTagFs: 8.5,  roleTagMinW: 28,
            collabW: 180, pctFs: 10.5, hoursMainFs: 12.5, hoursTargetFs: 8.5,
        },
        xs: {
            cellW: 36, cellH: 28, shiftMinW: 28, shiftH: 20, shiftFs: 9.5,  dayNumSize: 20, dayNumFs: 11,
            avatarSize: 22, avatarInitialsFs: 9.5, collabNameFs: 11.5, collabPadX: 10, collabPadY: 6, collabGap: 8,
            roleTagH: 18, roleTagFs: 9,    roleTagMinW: 30,
            collabW: 200, pctFs: 10.5, hoursMainFs: 13.5, hoursTargetFs: 9,
        },
        sm: {
            cellW: 46, cellH: 36, shiftMinW: 32, shiftH: 24, shiftFs: 10.5, dayNumSize: 22, dayNumFs: 12,
            avatarSize: 24, avatarInitialsFs: 10, collabNameFs: 12, collabPadX: 12, collabPadY: 7, collabGap: 9,
            roleTagH: 20, roleTagFs: 9.5,  roleTagMinW: 32,
            collabW: 220, pctFs: 11, hoursMainFs: 14.5, hoursTargetFs: 9.5,
        },
        md: {
            cellW: 56, cellH: 44, shiftMinW: 38, shiftH: 27, shiftFs: 11,   dayNumSize: 26, dayNumFs: 13,
            avatarSize: 26, avatarInitialsFs: 10.5, collabNameFs: 12.5, collabPadX: 13, collabPadY: 7, collabGap: 10,
            roleTagH: 21, roleTagFs: 9.5,  roleTagMinW: 34,
            collabW: 230, pctFs: 11.5, hoursMainFs: 15, hoursTargetFs: 10,
        },
        std: {
            cellW: 64, cellH: 50, shiftMinW: 42, shiftH: 30, shiftFs: 11.5, dayNumSize: 28, dayNumFs: 14,
            avatarSize: 28, avatarInitialsFs: 10.5, collabNameFs: 13, collabPadX: 14, collabPadY: 8, collabGap: 11,
            roleTagH: 22, roleTagFs: 10,   roleTagMinW: 36,
            collabW: 240, pctFs: 11.5, hoursMainFs: 16, hoursTargetFs: 10,
        },
    };
    const planningTable = $('plTable');

    function applySize(size) {
        const p = SIZE_PRESETS[size];
        if (!p || !planningTable) return;
        const set = (k, v) => planningTable.style.setProperty(k, typeof v === 'number' ? v + 'px' : v);
        set('--cell-w',           p.cellW);
        set('--cell-h',           p.cellH);
        set('--shift-min-w',      p.shiftMinW);
        set('--shift-h',          p.shiftH);
        set('--shift-fs',         p.shiftFs);
        set('--day-num-size',     p.dayNumSize);
        set('--day-num-fs',       p.dayNumFs);
        // Vars supplémentaires pour scaling complet
        set('--avatar-size',      p.avatarSize);
        set('--avatar-initials-fs', p.avatarInitialsFs);
        set('--collab-name-fs',   p.collabNameFs);
        set('--collab-pad-x',     p.collabPadX);
        set('--collab-pad-y',     p.collabPadY);
        set('--collab-gap',       p.collabGap);
        set('--collab-w',         p.collabW);
        set('--role-tag-h',       p.roleTagH);
        set('--role-tag-fs',      p.roleTagFs);
        set('--role-tag-min-w',   p.roleTagMinW);
        set('--pct-fs',           p.pctFs);
        set('--hours-main-fs',    p.hoursMainFs);
        set('--hours-target-fs',  p.hoursTargetFs);
        document.querySelectorAll('.size-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
        });
        try { localStorage.setItem('ss_planning_size', size); } catch(e) {}
    }

    document.querySelectorAll('.size-btn').forEach(btn => {
        btn.addEventListener('click', () => applySize(btn.dataset.size));
    });

    // Default : XXXS (ultra compact = bouton le + petit qui correspond au 1er bouton actif)
    let initialSize = 'xxxs';
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

    // ── Chevrons + séparateur : visibles seulement si overflow ─────────────
    const plFiltersList     = $('plFiltersList');
    const plFiltersScrollL  = $('plFiltersScrollLeft');
    const plFiltersScrollR  = $('plFiltersScrollRight');
    const plFiltersSep      = document.querySelector('.team-filters-sep');

    function plUpdateFilterChevrons() {
        if (!plFiltersList) return;
        const overflow = plFiltersList.scrollWidth - plFiltersList.clientWidth > 1;
        const visible = overflow;
        // Show/hide chevrons + sep
        if (plFiltersScrollL) plFiltersScrollL.hidden = !visible;
        if (plFiltersScrollR) plFiltersScrollR.hidden = !visible;
        if (plFiltersSep)     plFiltersSep.hidden     = !visible;
        // Disabled state des chevrons selon position du scroll
        if (visible) {
            const sl = plFiltersList.scrollLeft;
            const max = plFiltersList.scrollWidth - plFiltersList.clientWidth;
            if (plFiltersScrollL) plFiltersScrollL.disabled = sl <= 0;
            if (plFiltersScrollR) plFiltersScrollR.disabled = sl >= max - 1;
        }
    }

    // Click chevrons → scroll par 200px
    plFiltersScrollL?.addEventListener('click', () => {
        plFiltersList?.scrollBy({ left: -200, behavior: 'smooth' });
    });
    plFiltersScrollR?.addEventListener('click', () => {
        plFiltersList?.scrollBy({ left: 200, behavior: 'smooth' });
    });

    // Update on scroll (rafraîchit le disabled state des chevrons)
    plFiltersList?.addEventListener('scroll', plUpdateFilterChevrons);

    // Update on resize de la fenêtre
    window.addEventListener('resize', plUpdateFilterChevrons);

    // Update au chargement (après que le DOM soit bien dimensionné)
    plUpdateFilterChevrons();
    setTimeout(plUpdateFilterChevrons, 100);  // 2ème passe pour fonts chargées

    // ── Drag-to-scroll : sur la liste de filtres (team-filters-list) ────────
    if (plFiltersList) {
        let flIsDown = false, flStartX = 0, flScrollL = 0, flDidDrag = false;
        const FL_DRAG_THRESHOLD = 6;

        plFiltersList.addEventListener('mousedown', (e) => {
            // Ne pas démarrer le drag sur un pill (button) — laissons le click passer
            if (e.target.closest('button, a, .size-controls')) return;
            if (e.button !== 0) return;
            flIsDown = true;
            flDidDrag = false;
            flStartX = e.pageX;
            flScrollL = plFiltersList.scrollLeft;
        });

        plFiltersList.addEventListener('mousemove', (e) => {
            if (!flIsDown) return;
            const dx = e.pageX - flStartX;
            if (!flDidDrag && Math.abs(dx) < FL_DRAG_THRESHOLD) return;
            if (!flDidDrag) {
                flDidDrag = true;
                plFiltersList.classList.add('grabbing');
            }
            e.preventDefault();
            plFiltersList.scrollLeft = flScrollL - dx;
        });

        function flStop() {
            if (!flIsDown) return;
            flIsDown = false;
            plFiltersList.classList.remove('grabbing');
            if (flDidDrag) {
                // Bloque le click qui suit pour ne pas activer un pill par accident
                const blocker = (ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();
                    plFiltersList.removeEventListener('click', blocker, true);
                };
                plFiltersList.addEventListener('click', blocker, true);
            }
        }
        plFiltersList.addEventListener('mouseup', flStop);
        plFiltersList.addEventListener('mouseleave', flStop);
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

    // ── Modale d'édition cellule (refonte pixel-perfect) ────────────────────
    const plModalBackdrop = $('plCellModalBackdrop');
    const plModalUserId   = $('plCellUserId');
    const plModalDate     = $('plCellDate');
    const plModalAssignId = $('plCellAssignId');
    const plModalUpdated  = $('plCellUpdatedAt');
    const plModalModule   = $('plCellModule');
    const plModalStatut   = $('plCellStatut');
    const plModalNotes    = $('plCellNotes');
    const plModalDelete   = $('plCellDelete');
    const plModalSave     = $('plCellSave');

    // Sous-éléments du titre splitté en 3 spans (nom · sep · date)
    const plModalTitleName = document.querySelector('.pl-cell-title-name');
    const plModalTitleSep  = document.querySelector('.pl-cell-title-sep');
    const plModalTitleDate = document.querySelector('.pl-cell-title-date');

    // Format date FR court : "Mer. 03 juin 2026"
    const PL_JOURS = ['Dim.','Lun.','Mar.','Mer.','Jeu.','Ven.','Sam.'];
    const PL_MOIS  = ['janvier','février','mars','avril','mai','juin',
                      'juillet','août','septembre','octobre','novembre','décembre'];
    function plFormatDateFR(iso) {
        if (!iso) return '';
        const [y, m, d] = iso.split('-').map(Number);
        const dt = new Date(y, m - 1, d);
        return PL_JOURS[dt.getDay()] + ' ' + String(d).padStart(2,'0') + ' ' + PL_MOIS[m - 1] + ' ' + y;
    }

    // Met à jour la pastille colorée du select Statut selon la valeur sélectionnée
    function plUpdateStatutDot() {
        if (!plModalStatut) return;
        const opt = plModalStatut.options[plModalStatut.selectedIndex];
        const color = opt?.dataset?.color || 'ok';
        plModalStatut.dataset.statutColor = color;
    }
    plModalStatut?.addEventListener('change', plUpdateStatutDot);

    function plOpenCellModal(td) {
        if (!plModalBackdrop) return;
        const userId = td.dataset.uid;
        const date   = td.dataset.date;
        if (!userId || !date) return;

        // Infos user pour le titre + module principal de fallback
        const user = (window.PL_DATA?.users || []).find(u => u.id === userId);
        if (plModalTitleName) plModalTitleName.textContent = user ? `${user.prenom} ${user.nom}` : 'Collaborateur';
        if (plModalTitleSep)  plModalTitleSep.textContent  = '—';
        if (plModalTitleDate) plModalTitleDate.textContent = plFormatDateFR(date);

        plModalUserId.value   = userId;
        plModalDate.value     = date;
        plModalAssignId.value = td.dataset.assignId || '';
        plModalUpdated.value  = td.dataset.updatedAt || '';

        // Horaire pré-sélectionné : par horaire_type_id si dispo, sinon code
        const targetHoraireId   = td.dataset.horaireTypeId || '';
        const targetHoraireCode = td.dataset.horaireCode || '';
        let foundActive = false;
        document.querySelectorAll('.pl-horaire-card').forEach(card => {
            const isActive = targetHoraireId
                ? (card.dataset.horaireId === targetHoraireId)
                : (targetHoraireCode && card.dataset.horaireCode === targetHoraireCode);
            card.classList.toggle('active', isActive);
            card.dataset.selected = isActive ? '1' : '';
            if (isActive) foundActive = true;
        });
        // Si aucune horaire sélectionnée, sélectionne la card "—" (repos)
        if (!foundActive) {
            const noneCard = document.querySelector('.pl-horaire-none');
            if (noneCard) {
                noneCard.classList.add('active');
                noneCard.dataset.selected = '1';
            }
        }

        // Module : valeur existante > module principal du user > vide
        const userModuleIds = (user?.module_ids || '').split(',').filter(Boolean);
        plModalModule.value = td.dataset.moduleId || userModuleIds[0] || '';

        // Statut + notes : valeurs existantes ou défaut
        plModalStatut.value = td.dataset.statut || 'present';
        plUpdateStatutDot();
        plModalNotes.value  = td.dataset.notes || '';

        if (plModalDelete) plModalDelete.hidden = !td.dataset.assignId;

        plModalBackdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function plCloseCellModal() {
        plModalBackdrop?.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Sélection d'une carte horaire (sauf la carte "Nouvel horaire")
    document.querySelectorAll('.pl-horaire-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.pl-horaire-card').forEach(c => {
                c.classList.remove('active');
                c.dataset.selected = '';
            });
            card.classList.add('active');
            card.dataset.selected = '1';
        });
    });

    // Bouton "Gérer les horaires" / "Nouvel horaire" — placeholder Phase 2
    $('plCellManageShifts')?.addEventListener('click', () => plToast('Gestion des horaires — TODO Phase 2', 'info'));
    $('plCellAddShift')?.addEventListener('click', () => plToast('Création d\'un nouvel horaire — TODO Phase 2', 'info'));

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
        if (e.key === 'Escape' && plModalBackdrop?.classList.contains('show')) plCloseCellModal();
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

    // ── Modale Règles de génération IA ──────────────────────────────────────
    const plIaBackdrop = $('plIaModalBackdrop');
    const plIaBody     = $('plIaBody');
    const plIaToolbar  = $('plIaToolbar');
    const plIaFooter   = $('plIaFooter');
    let   plIaRules    = [];
    let   plIaFilter   = 'all';      // all | active | disabled | important
    let   plIaSearchQ  = '';
    let   plIaView     = 'list';     // list | form
    let   plIaEditId   = null;

    function plIaOpen() {
        plIaView = 'list'; plIaEditId = null;
        plIaBackdrop?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        plIaLoadRules();
    }
    function plIaClose() {
        plIaBackdrop?.classList.add('hidden');
        document.body.style.overflow = '';
    }
    $('plIaRulesBtn')?.addEventListener('click', plIaOpen);
    $('plIaClose')?.addEventListener('click', plIaClose);
    plIaBackdrop?.addEventListener('click', (e) => { if (e.target === plIaBackdrop) plIaClose(); });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const pickerOpen = !$('plIaPickerBackdrop')?.classList.contains('hidden');
        if (pickerOpen) { plIaClosePicker(); return; }
        if (!plIaBackdrop?.classList.contains('hidden')) plIaClose();
    });

    // Mapping rule_type → label visuel + icône (Lucide-style)
    const PL_IA_TYPE_LABELS = {
        user_schedule:  'Collaborateur horaire unique',
        shift_only:     'Horaires autorisés',
        shift_exclude:  'Horaires exclus',
        days_only:      'Jours autorisés',
        module_only:    'Modules autorisés',
        module_exclude: 'Modules exclus',
        no_weekend:     'Pas de weekend',
        max_days_week:  'Max jours/semaine',
        '':             'Texte libre',
    };
    const PL_IA_TYPE_ICONS = {
        user_schedule:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/><path d="M16 11h6M19 8v6"/></svg>',
        shift_only:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        shift_exclude:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>',
        days_only:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        module_only:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18"/></svg>',
        module_exclude: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m4.93 4.93 14.14 14.14"/></svg>',
        no_weekend:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        max_days_week:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        '':             '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    };

    async function plIaLoadRules() {
        plIaBody.innerHTML = '<div class="pl-ia-loading"><svg class="animate-spin" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Chargement des règles…</div>';
        const res = await plApiPost('admin_get_ia_rules');
        plIaRules = res?.rules || [];
        plIaRender();
    }

    function plIaRender() {
        if (plIaView === 'form') { plIaRenderForm(); return; }
        plIaRenderList();
    }

    function plIaRenderList() {
        // Mode liste : retire le modifier .pl-ia-mode-form + restaure header
        plIaBackdrop?.classList.remove('pl-ia-mode-form');
        $('plIaModalTitle') && ($('plIaModalTitle').textContent = 'Règles de génération IA');
        $('plIaEyebrow')    && ($('plIaEyebrow').textContent    = 'Génération automatique');
        // Toolbar visible
        if (plIaToolbar) plIaToolbar.style.display = '';

        // Stats header
        const nbActive   = plIaRules.filter(r => r.actif).length;
        const nbDisabled = plIaRules.length - nbActive;
        const nbImportant = plIaRules.filter(r => r.importance === 'important').length;
        $('plIaStatActive')   && ($('plIaStatActive').textContent   = nbActive);
        $('plIaStatDisabled') && ($('plIaStatDisabled').textContent = nbDisabled);
        $('plIaCntAll')       && ($('plIaCntAll').textContent       = plIaRules.length);
        $('plIaCntActive')    && ($('plIaCntActive').textContent    = nbActive);
        $('plIaCntDisabled')  && ($('plIaCntDisabled').textContent  = nbDisabled);
        $('plIaCntImportant') && ($('plIaCntImportant').textContent = nbImportant);

        // Footer (mode liste)
        plIaFooter.innerHTML = `
          <div class="pl-ia-footer-left">
            <button type="button" class="pl-ia-btn-secondary" id="plIaConfigBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
              Config IA avancée
            </button>
            <span class="pl-ia-footer-help">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
              Les règles importantes ne peuvent pas être violées
            </span>
          </div>
          <button type="button" class="pl-ia-btn-primary" id="plIaAddBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter une règle
          </button>`;
        $('plIaAddBtn')?.addEventListener('click', () => { plIaView = 'form'; plIaEditId = null; plIaRender(); });

        // Filtre + recherche
        let visible = plIaRules.slice();
        if (plIaFilter === 'active')    visible = visible.filter(r => r.actif);
        if (plIaFilter === 'disabled')  visible = visible.filter(r => !r.actif);
        if (plIaFilter === 'important') visible = visible.filter(r => r.importance === 'important');
        if (plIaSearchQ) {
            const q = plIaSearchQ.toLowerCase();
            visible = visible.filter(r =>
                (r.titre || '').toLowerCase().includes(q) ||
                (r.description || '').toLowerCase().includes(q)
            );
        }

        if (!visible.length) {
            plIaBody.innerHTML = '<div class="pl-ia-empty">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/></svg>'
                + '<div class="pl-ia-empty-title">' + (plIaRules.length ? 'Aucune règle ne correspond' : 'Aucune règle configurée') + '</div>'
                + '<div class="pl-ia-empty-sub">' + (plIaRules.length ? 'Essayez un autre filtre ou recherche' : 'Cliquez sur « Ajouter une règle » pour commencer') + '</div>'
                + '</div>';
            return;
        }

        plIaBody.innerHTML = '<div class="pl-ia-list">' + visible.map(plIaRenderCard).join('') + '</div>';

        // Bind events
        plIaBody.querySelectorAll('.pl-ia-toggle').forEach(el => el.addEventListener('click', plIaToggleRule));
        plIaBody.querySelectorAll('[data-ia-edit]').forEach(el => el.addEventListener('click', () => { plIaEditId = el.dataset.iaEdit; plIaView = 'form'; plIaRender(); }));
        plIaBody.querySelectorAll('[data-ia-del]').forEach(el => el.addEventListener('click', plIaDeleteRule));
    }

    function plIaRenderCard(r) {
        const importance = r.importance || 'moyen';
        const badgeClass = importance === 'important' ? 'pl-ia-badge--important'
                          : importance === 'faible'   ? 'pl-ia-badge--info'
                          : 'pl-ia-badge--priority';
        const badgeText = importance === 'important' ? 'important'
                         : importance === 'faible'   ? 'global'
                         : 'priorité';
        const typeKey   = r.rule_type || '';
        const typeLabel = PL_IA_TYPE_LABELS[typeKey] || 'Texte libre';
        const typeIcon  = PL_IA_TYPE_ICONS[typeKey] || PL_IA_TYPE_ICONS[''];

        // Cible (ciblage human-readable)
        let targetHtml = '<strong>Tout le monde</strong>';
        if (r.target_mode === 'fonction')    targetHtml = 'Fonction <strong>' + plEsc(r.target_fonction_code || '?') + '</strong>';
        else if (r.target_mode === 'module') {
            const ids = r.rule_params?.target_module_ids || [];
            const codes = ids.map(id => (window.PL_DATA?.modules || []).find(m => m.id === id)?.code || '?');
            targetHtml = 'Modules <strong>' + plEsc(codes.join(', ') || '?') + '</strong>';
        }
        else if (r.target_mode === 'users') {
            const names = (r.targeted_users || []).map(u => u.name || (u.prenom + ' ' + u.nom));
            targetHtml = '<strong>' + plEsc(names.join(', ') || 'Utilisateurs ciblés') + '</strong>';
        }

        // Codes shifts colorés
        const shiftCodes = (r.rule_params?.shift_codes || []);
        const horaires = window.PL_DATA?.horaires || [];
        const shiftsHtml = shiftCodes.length
            ? '<span class="pl-ia-rule-shifts">' + shiftCodes.map(c => {
                const h = horaires.find(x => x.code === c);
                const bg = (h?.couleur || '#1f6359');
                return '<span class="pl-ia-shift-code" style="background:' + plEsc(bg) + '">' + plEsc(c) + '</span>';
            }).join('') + '</span>'
            : '';

        // Description visible (max 120 chars)
        const desc = r.description ? plEsc(r.description.substring(0, 140)) : '';

        return '<div class="pl-ia-rule-card' + (r.actif ? '' : ' disabled') + '">'
            + '<div class="pl-ia-toggle' + (r.actif ? ' on' : '') + '" data-ia-toggle="' + plEsc(r.id) + '" role="switch" aria-checked="' + (r.actif ? 'true' : 'false') + '"></div>'
            + '<div class="pl-ia-rule-content">'
            + '<div class="pl-ia-rule-head">'
            + '<span class="pl-ia-rule-title">' + plEsc(r.titre || '(Sans titre)') + '</span>'
            + '<span class="pl-ia-badge ' + badgeClass + '">' + badgeText + '</span>'
            + '<span class="pl-ia-rule-type">' + typeIcon + ' ' + plEsc(typeLabel) + '</span>'
            + shiftsHtml
            + '</div>'
            + '<div class="pl-ia-rule-desc">' + targetHtml + (desc ? ' · ' + desc : '') + '</div>'
            + '</div>'
            + '<div class="pl-ia-rule-actions">'
            + '<button type="button" class="pl-ia-rule-action-btn" data-ia-edit="' + plEsc(r.id) + '" title="Modifier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>'
            + '<button type="button" class="pl-ia-rule-action-btn danger" data-ia-del="' + plEsc(r.id) + '" title="Supprimer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>'
            + '</div>'
            + '</div>';
    }

    async function plIaToggleRule(ev) {
        const el = ev.currentTarget;
        const id = el.dataset.iaToggle;
        const wasOn = el.classList.contains('on');
        // Optimistic UI
        el.classList.toggle('on');
        el.closest('.pl-ia-rule-card')?.classList.toggle('disabled');
        const res = await plApiPost('admin_toggle_ia_rule', { id });
        if (!res?.success) {
            // Rollback
            el.classList.toggle('on');
            el.closest('.pl-ia-rule-card')?.classList.toggle('disabled');
            plToast(res?.message || 'Erreur', 'error');
            return;
        }
        // Sync local state + stats
        const r = plIaRules.find(x => x.id === id);
        if (r) r.actif = wasOn ? 0 : 1;
        plIaRenderList();
    }

    async function plIaDeleteRule(ev) {
        const id = ev.currentTarget.dataset.iaDel;
        const r  = plIaRules.find(x => x.id === id);
        if (!confirm('Supprimer la règle « ' + (r?.titre || '') + ' » ?')) return;
        const res = await plApiPost('admin_delete_ia_rule', { id });
        if (res?.success) {
            plToast('Règle supprimée', 'ok');
            plIaRules = plIaRules.filter(x => x.id !== id);
            plIaRenderList();
        } else {
            plToast(res?.message || 'Erreur', 'error');
        }
    }

    // Filtre pills + recherche
    plIaToolbar?.addEventListener('click', (e) => {
        const pill = e.target.closest('[data-ia-filter]');
        if (!pill) return;
        plIaFilter = pill.dataset.iaFilter;
        plIaToolbar.querySelectorAll('[data-ia-filter]').forEach(p => p.classList.toggle('active', p === pill));
        plIaRenderList();
    });
    $('plIaSearchInput')?.addEventListener('input', (e) => {
        plIaSearchQ = e.target.value || '';
        plIaRenderList();
    });

    // ── Formulaire ajout/édition règle (mockup-perfect) ─────────────────────
    // État local du formulaire (chips dynamiques) — réinitialisé à chaque renderForm
    let plIaFormState = { shifts: [], shiftsExclude: [], modules: [], users: [], days: [], type: '', target: 'all', importance: 'moyen', maxDays: 5, fonctionCode: '' };

    function plIaRenderForm() {
        const r = plIaEditId ? plIaRules.find(x => x.id === plIaEditId) : null;
        const isEdit = !!r;
        const params = r?.rule_params || {};

        // Mode formulaire : header + toolbar + footer adaptés
        plIaBackdrop?.classList.add('pl-ia-mode-form');
        $('plIaModalTitle') && ($('plIaModalTitle').textContent = isEdit ? 'Modifier la règle' : 'Nouvelle règle');
        $('plIaEyebrow')    && ($('plIaEyebrow').textContent    = 'Configuration de la règle');
        if (plIaToolbar) plIaToolbar.style.display = 'none';

        // Init state à partir de la règle existante (ou défauts)
        plIaFormState = {
            shifts:        (params.shift_codes || []).slice(),
            shiftsExclude: (params.exclude_shift_codes || []).slice(),
            modules:       (params.module_ids || params.target_module_ids || r?.rule_params?.target_module_ids || []).slice(),
            users:         (r?.targeted_users || []).map(u => u.id),
            days:          (params.days || []).slice(),
            type:          r?.rule_type || '',
            target:        r?.target_mode || 'all',
            importance:    r?.importance || 'moyen',
            maxDays:       params.max_days || 5,
            fonctionCode:  r?.target_fonction_code || '',
        };

        plIaBody.innerHTML = `
          <!-- Carte info "TYPE SÉLECTIONNÉ" -->
          <div class="pl-ia-type-card">
            <div class="pl-ia-type-card-icon" id="plIaTypeCardIcon">${PL_IA_TYPE_ICONS[plIaFormState.type] || PL_IA_TYPE_ICONS['']}</div>
            <div class="pl-ia-type-card-text">
              <div class="pl-ia-type-card-label">Type sélectionné</div>
              <div class="pl-ia-type-card-name" id="plIaTypeCardName">${plEsc(PL_IA_TYPE_LABELS[plIaFormState.type] || 'Texte libre')}</div>
            </div>
          </div>

          <!-- Titre -->
          <div class="pl-ia-form-group">
            <label class="pl-ia-form-label">Titre <span class="required">*</span></label>
            <input type="text" class="pl-ia-form-input" id="plIaFTitre" placeholder="Ex: Sandrine Lambert ne travaille pas le weekend" value="${plEsc(r?.titre || '')}">
          </div>

          <!-- Type / Importance / Cible -->
          <div class="pl-ia-form-group">
            <div class="pl-ia-form-row-3">
              <div class="pl-ia-select-wrap">
                <label class="pl-ia-form-label">Type de règle</label>
                <button type="button" class="pl-ia-select-btn" id="plIaFTypeBtn">
                  <span class="pl-ia-select-text" id="plIaFTypeText">${plEsc(PL_IA_TYPE_LABELS[plIaFormState.type] || 'Texte libre')}</span>
                </button>
                <svg class="pl-ia-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                <div class="pl-ia-select-dropdown" id="plIaFTypeDropdown">
                  <div class="pl-ia-select-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="text" id="plIaFTypeSearch" placeholder="Rechercher un type…">
                  </div>
                  <div class="pl-ia-select-list" id="plIaFTypeList">
                    ${Object.entries(PL_IA_TYPE_LABELS).map(([k, v]) => `
                      <div class="pl-ia-select-option${k === plIaFormState.type ? ' active' : ''}" data-type-val="${plEsc(k)}">
                        <span class="pl-ia-option-icon">${PL_IA_TYPE_ICONS[k] || ''}</span>
                        <span>${plEsc(v)}${k === '' ? ' (IA)' : ''}</span>
                      </div>`).join('')}
                  </div>
                </div>
              </div>

              <div class="pl-ia-select-wrap">
                <label class="pl-ia-form-label">Importance</label>
                <button type="button" class="pl-ia-select-btn" id="plIaFImpBtn">
                  <span class="pl-ia-select-text" id="plIaFImpText">${plEsc({important:'Important', moyen:'Moyen', faible:'Faible'}[plIaFormState.importance] || 'Moyen')}</span>
                </button>
                <svg class="pl-ia-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                <div class="pl-ia-select-dropdown" id="plIaFImpDropdown">
                  <div class="pl-ia-select-list">
                    <div class="pl-ia-select-option${plIaFormState.importance==='important'?' active':''}" data-imp-val="important">Important</div>
                    <div class="pl-ia-select-option${plIaFormState.importance==='moyen'?' active':''}" data-imp-val="moyen">Moyen</div>
                    <div class="pl-ia-select-option${plIaFormState.importance==='faible'?' active':''}" data-imp-val="faible">Faible</div>
                  </div>
                </div>
              </div>

              <div class="pl-ia-select-wrap">
                <label class="pl-ia-form-label">Cible</label>
                <button type="button" class="pl-ia-select-btn" id="plIaFTargetBtn">
                  <span class="pl-ia-select-text" id="plIaFTargetText">${plEsc({all:'Tout le monde',module:'Par module',fonction:'Par fonction',users:'Utilisateurs spécifiques'}[plIaFormState.target] || 'Tout le monde')}</span>
                </button>
                <svg class="pl-ia-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                <div class="pl-ia-select-dropdown" id="plIaFTargetDropdown">
                  <div class="pl-ia-select-list">
                    <div class="pl-ia-select-option${plIaFormState.target==='all'?' active':''}" data-tgt-val="all">Tout le monde</div>
                    <div class="pl-ia-select-option${plIaFormState.target==='module'?' active':''}" data-tgt-val="module">Par module</div>
                    <div class="pl-ia-select-option${plIaFormState.target==='fonction'?' active':''}" data-tgt-val="fonction">Par fonction</div>
                    <div class="pl-ia-select-option${plIaFormState.target==='users'?' active':''}" data-tgt-val="users">Utilisateurs spécifiques</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Détail cible (Utilisateurs / Fonction / Modules ciblés) -->
          <div class="pl-ia-form-group" id="plIaFTargetDetail"></div>

          <!-- Section dynamique selon type -->
          <div id="plIaFParamsDetail"></div>

          <!-- Description (toujours visible en bas) -->
          <div class="pl-ia-form-group">
            <label class="pl-ia-form-label">
              Description / règle en texte libre
              <span class="optional">(optionnel sauf pour Texte libre)</span>
            </label>
            <textarea class="pl-ia-form-textarea" id="plIaFDesc" placeholder="Décrivez la règle en langage naturel. Ex: «Marie ne doit jamais travailler le mercredi» ou «Les AS du module M1 ne font pas de D3 le weekend»">${plEsc(r?.description || '')}</textarea>
            <div class="pl-ia-form-help">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
              Cette description est transmise à l'IA pour les modes hybride et IA directe
            </div>
          </div>
        `;

        // Footer mode formulaire
        plIaFooter.innerHTML = `
          <span class="pl-ia-form-footer-help">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            Les modifications s'appliquent à la prochaine génération
          </span>
          <div class="pl-ia-form-footer-actions">
            <button type="button" class="pl-ia-btn-secondary" id="plIaCancelBtn">Annuler</button>
            <button type="button" class="pl-ia-btn-primary" id="plIaSaveBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              ${isEdit ? 'Modifier' : 'Créer'}
            </button>
          </div>
        `;

        // ─── Bind events ───
        $('plIaCancelBtn')?.addEventListener('click', () => { plIaView = 'list'; plIaRender(); });
        $('plIaSaveBtn')?.addEventListener('click', plIaSaveRule);

        // Custom selects (Type / Importance / Cible)
        plIaInitCustomSelect('plIaFTypeBtn', 'plIaFTypeDropdown', '[data-type-val]', (val, label) => {
            plIaFormState.type = val;
            $('plIaFTypeText').textContent = PL_IA_TYPE_LABELS[val] || 'Texte libre';
            $('plIaTypeCardName').textContent = PL_IA_TYPE_LABELS[val] || 'Texte libre';
            $('plIaTypeCardIcon').innerHTML = PL_IA_TYPE_ICONS[val] || PL_IA_TYPE_ICONS[''];
            plIaRenderParamsDetail();
        });
        plIaInitCustomSelect('plIaFImpBtn', 'plIaFImpDropdown', '[data-imp-val]', (val, label) => {
            plIaFormState.importance = val;
            $('plIaFImpText').textContent = label;
        });
        plIaInitCustomSelect('plIaFTargetBtn', 'plIaFTargetDropdown', '[data-tgt-val]', (val, label) => {
            plIaFormState.target = val;
            $('plIaFTargetText').textContent = label;
            plIaRenderTargetDetail();
        });

        // Recherche dans le dropdown des types
        $('plIaFTypeSearch')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            $('plIaFTypeList').querySelectorAll('.pl-ia-select-option').forEach(opt => {
                const txt = (opt.textContent || '').toLowerCase();
                opt.style.display = txt.includes(q) ? '' : 'none';
            });
        });
        $('plIaFTypeSearch')?.addEventListener('click', e => e.stopPropagation());

        // Render des sections dynamiques
        plIaRenderTargetDetail();
        plIaRenderParamsDetail();
    }

    // Init un custom select : ouverture/fermeture + sélection d'option
    function plIaInitCustomSelect(btnId, dropId, optSelector, onPick) {
        const btn  = $(btnId);
        const drop = $(dropId);
        if (!btn || !drop) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Ferme les autres
            document.querySelectorAll('.pl-ia-select-dropdown.show').forEach(d => { if (d !== drop) d.classList.remove('show'); });
            document.querySelectorAll('.pl-ia-select-btn.open').forEach(b => { if (b !== btn) b.classList.remove('open'); });
            drop.classList.toggle('show');
            btn.classList.toggle('open');
        });
        drop.addEventListener('click', e => e.stopPropagation());
        drop.querySelectorAll(optSelector).forEach(opt => {
            opt.addEventListener('click', () => {
                drop.querySelectorAll(optSelector).forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                const val = opt.getAttribute(optSelector.replace(/[\[\]]/g, ''));
                const label = opt.textContent.trim();
                drop.classList.remove('show');
                btn.classList.remove('open');
                onPick(val, label);
            });
        });
    }

    // Ferme tous les dropdowns au click ailleurs
    document.addEventListener('click', () => {
        document.querySelectorAll('.pl-ia-select-dropdown.show').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.pl-ia-select-btn.open').forEach(b => b.classList.remove('open'));
    });

    // Rendu détail de la cible (Utilisateurs / Fonction / Modules)
    function plIaRenderTargetDetail() {
        const det = $('plIaFTargetDetail');
        if (!det) return;
        const t = plIaFormState.target;
        if (t === 'all') { det.innerHTML = ''; return; }

        if (t === 'users') {
            det.innerHTML = `
              <label class="pl-ia-form-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/></svg>
                Utilisateurs
              </label>
              <div class="pl-ia-chips-input" id="plIaFUsersInput" tabindex="0"></div>
            `;
            plIaRenderUsersChips();
            return;
        }
        if (t === 'fonction') {
            const fonctions = window.PL_DATA?.fonctions || [];
            det.innerHTML = `
              <label class="pl-ia-form-label">Fonction</label>
              <div class="pl-ia-select-wrap">
                <button type="button" class="pl-ia-select-btn" id="plIaFFonctionBtn">
                  <span class="pl-ia-select-text${plIaFormState.fonctionCode ? '' : ' muted'}" id="plIaFFonctionText">${plEsc(plIaFormState.fonctionCode ? (fonctions.find(f => f.code === plIaFormState.fonctionCode)?.code + ' — ' + fonctions.find(f => f.code === plIaFormState.fonctionCode)?.nom) : 'Choisir une fonction')}</span>
                </button>
                <svg class="pl-ia-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                <div class="pl-ia-select-dropdown" id="plIaFFonctionDropdown">
                  <div class="pl-ia-select-list">
                    ${fonctions.map(f => `<div class="pl-ia-select-option${f.code === plIaFormState.fonctionCode ? ' active' : ''}" data-fct-val="${plEsc(f.code)}">${plEsc(f.code)} — ${plEsc(f.nom || '')}</div>`).join('')}
                  </div>
                </div>
              </div>
            `;
            plIaInitCustomSelect('plIaFFonctionBtn', 'plIaFFonctionDropdown', '[data-fct-val]', (val) => {
                plIaFormState.fonctionCode = val;
                const f = fonctions.find(x => x.code === val);
                $('plIaFFonctionText').textContent = f ? f.code + ' — ' + (f.nom || '') : 'Choisir une fonction';
                $('plIaFFonctionText').classList.remove('muted');
            });
            return;
        }
        if (t === 'module') {
            det.innerHTML = `
              <label class="pl-ia-form-label">Modules ciblés</label>
              <div class="pl-ia-chips-input" id="plIaFTgtModulesInput" tabindex="0"></div>
            `;
            plIaRenderModulesChips('plIaFTgtModulesInput', 'modules');
            return;
        }
    }

    // Rendu détail params (Jours / Horaires / Modules) selon type
    function plIaRenderParamsDetail() {
        const det = $('plIaFParamsDetail');
        if (!det) return;
        const t = plIaFormState.type;
        let html = '';

        if (t === 'user_schedule') {
            html += plIaSectionDays('Jours de travail', 'Laisser vide = tous');
            html += plIaSectionShifts('Horaires autorisés', 'Laisser vide = tous', 'plIaFShiftsInput', false);
            html += plIaSectionShifts('Horaires interdits', 'Optionnel', 'plIaFShiftsExcludeInput', true);
        } else if (t === 'shift_only') {
            html += plIaSectionShifts('Horaires', 'Au moins 1', 'plIaFShiftsInput', false);
        } else if (t === 'shift_exclude') {
            html += plIaSectionShifts('Horaires à exclure', 'Au moins 1', 'plIaFShiftsExcludeInput', true);
        } else if (t === 'days_only') {
            html += plIaSectionDays('Jours autorisés', 'Au moins 1');
        } else if (t === 'module_only' || t === 'module_exclude') {
            const title = t === 'module_only' ? 'Modules autorisés' : 'Modules exclus';
            html += `
              <div class="pl-ia-dynamic-section">
                <div class="pl-ia-dynamic-section-head">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                  <span class="pl-ia-dynamic-section-title">${plEsc(title)}</span>
                  <span class="pl-ia-dynamic-section-hint">Au moins 1</span>
                </div>
                <div class="pl-ia-chips-input" id="plIaFParamModulesInput" tabindex="0"></div>
              </div>`;
        } else if (t === 'max_days_week') {
            html += `
              <div class="pl-ia-dynamic-section">
                <div class="pl-ia-dynamic-section-head">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                  <span class="pl-ia-dynamic-section-title">Max jours par semaine</span>
                </div>
                <input type="number" class="pl-ia-form-input" id="plIaFMaxDays" min="1" max="7" value="${plIaFormState.maxDays}">
              </div>`;
        }
        det.innerHTML = html;

        // Bind days
        det.querySelectorAll('.pl-ia-day-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const dow = parseInt(btn.dataset.dow, 10);
                if (plIaFormState.days.includes(dow)) plIaFormState.days = plIaFormState.days.filter(x => x !== dow);
                else plIaFormState.days.push(dow);
                btn.classList.toggle('active');
            });
        });
        // Bind chips inputs
        if ($('plIaFShiftsInput'))        plIaRenderShiftsChips('plIaFShiftsInput', false);
        if ($('plIaFShiftsExcludeInput')) plIaRenderShiftsChips('plIaFShiftsExcludeInput', true);
        if ($('plIaFParamModulesInput'))  plIaRenderModulesChips('plIaFParamModulesInput', 'modules');
        if ($('plIaFMaxDays'))            $('plIaFMaxDays').addEventListener('input', e => { plIaFormState.maxDays = parseInt(e.target.value, 10) || 5; });
    }

    function plIaSectionDays(title, hint) {
        const dn = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
        return `
          <div class="pl-ia-dynamic-section">
            <div class="pl-ia-dynamic-section-head">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <span class="pl-ia-dynamic-section-title">${plEsc(title)}</span>
              <span class="pl-ia-dynamic-section-hint">${plEsc(hint)}</span>
            </div>
            <div class="pl-ia-day-grid">
              ${dn.map((n, i) => {
                const dow = i + 1;
                const active = plIaFormState.days.includes(dow);
                const wknd = (dow >= 6) ? ' weekend' : '';
                return `<button type="button" class="pl-ia-day-btn${active ? ' active' : ''}${wknd}" data-dow="${dow}">${n}<span class="day-num">${String(dow).padStart(2,'0')}</span></button>`;
              }).join('')}
            </div>
          </div>`;
    }

    function plIaSectionShifts(title, hint, inputId, isExclude) {
        const icon = isExclude
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
        return `
          <div class="pl-ia-dynamic-section">
            <div class="pl-ia-dynamic-section-head">
              ${icon}
              <span class="pl-ia-dynamic-section-title">${plEsc(title)}</span>
              <span class="pl-ia-dynamic-section-hint">${plEsc(hint)}</span>
            </div>
            <div class="pl-ia-chips-input" id="${inputId}" tabindex="0"></div>
          </div>`;
    }

    // Rendu des chips horaires (avec dropdown de sélection)
    function plIaRenderShiftsChips(inputId, isExclude) {
        const cont = $(inputId);
        if (!cont) return;
        const horaires = window.PL_DATA?.horaires || [];
        const list = isExclude ? plIaFormState.shiftsExclude : plIaFormState.shifts;
        const placeholderText = isExclude ? 'Exclure un horaire' : 'Ajouter un horaire';

        const chipsHtml = list.map(code => {
            const h = horaires.find(x => x.code === code);
            const bg = isExclude ? 'var(--color-danger)' : (h?.couleur || '#1f6359');
            return `<span class="pl-ia-shift-chip${isExclude ? ' danger' : ''}" style="background:${plEsc(bg)}">${plEsc(code)}<button type="button" class="pl-ia-chip-remove" data-rm-shift="${plEsc(code)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M6 6l12 12M18 6L6 18"/></svg></button></span>`;
        }).join('');

        // Options non encore sélectionnées
        const remaining = horaires.filter(h => !list.includes(h.code));
        const dropHtml = remaining.length ? `
          <div class="pl-ia-select-dropdown" id="${inputId}_drop">
            <div class="pl-ia-select-list">
              ${remaining.map(h => `<div class="pl-ia-select-option" data-add-shift="${plEsc(h.code)}"><span class="pl-ia-shift-chip" style="background:${plEsc(h.couleur || '#1f6359')}">${plEsc(h.code)}</span><span>${plEsc(h.nom || '')}</span></div>`).join('')}
            </div>
          </div>` : '';

        cont.innerHTML = chipsHtml + (chipsHtml && remaining.length ? '' : '') + `<span class="pl-ia-chips-placeholder">${plEsc(placeholderText)}</span>` + dropHtml;
        cont.style.position = 'relative';

        // Bind remove
        cont.querySelectorAll('[data-rm-shift]').forEach(btn => btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const code = btn.dataset.rmShift;
            if (isExclude) plIaFormState.shiftsExclude = plIaFormState.shiftsExclude.filter(x => x !== code);
            else plIaFormState.shifts = plIaFormState.shifts.filter(x => x !== code);
            plIaRenderShiftsChips(inputId, isExclude);
        }));

        // Open dropdown au click
        const drop = $(inputId + '_drop');
        cont.addEventListener('click', (e) => {
            if (e.target.closest('.pl-ia-chip-remove')) return;
            e.stopPropagation();
            document.querySelectorAll('.pl-ia-select-dropdown.show').forEach(d => { if (d !== drop) d.classList.remove('show'); });
            document.querySelectorAll('.pl-ia-chips-input.open').forEach(b => { if (b !== cont) b.classList.remove('open'); });
            if (drop) drop.classList.toggle('show');
            cont.classList.toggle('open');
        });

        // Bind add
        if (drop) {
            drop.addEventListener('click', e => e.stopPropagation());
            drop.querySelectorAll('[data-add-shift]').forEach(opt => opt.addEventListener('click', () => {
                const code = opt.dataset.addShift;
                if (isExclude) plIaFormState.shiftsExclude.push(code);
                else plIaFormState.shifts.push(code);
                plIaRenderShiftsChips(inputId, isExclude);
            }));
        }
    }

    // Rendu chips modules
    function plIaRenderModulesChips(inputId, target) {
        const cont = $(inputId);
        if (!cont) return;
        const modules = window.PL_DATA?.modules || [];
        const list = plIaFormState.modules;

        const chipsHtml = list.map(id => {
            const m = modules.find(x => x.id === id);
            return `<span class="pl-ia-module-chip">${plEsc(m?.code || '?')} — ${plEsc(m?.nom || '')}<button type="button" class="pl-ia-chip-remove" data-rm-mod="${plEsc(id)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M6 6l12 12M18 6L6 18"/></svg></button></span>`;
        }).join('');

        const remaining = modules.filter(m => !list.includes(m.id));
        const dropHtml = remaining.length ? `
          <div class="pl-ia-select-dropdown" id="${inputId}_drop">
            <div class="pl-ia-select-list">
              ${remaining.map(m => `<div class="pl-ia-select-option" data-add-mod="${plEsc(m.id)}"><span>${plEsc(m.code)} — ${plEsc(m.nom || '')}</span></div>`).join('')}
            </div>
          </div>` : '';

        cont.innerHTML = chipsHtml + `<span class="pl-ia-chips-placeholder">Ajouter un module</span>` + dropHtml;
        cont.style.position = 'relative';

        cont.querySelectorAll('[data-rm-mod]').forEach(btn => btn.addEventListener('click', (e) => {
            e.stopPropagation();
            plIaFormState.modules = plIaFormState.modules.filter(x => x !== btn.dataset.rmMod);
            plIaRenderModulesChips(inputId, target);
        }));

        const drop = $(inputId + '_drop');
        cont.addEventListener('click', (e) => {
            if (e.target.closest('.pl-ia-chip-remove')) return;
            e.stopPropagation();
            document.querySelectorAll('.pl-ia-select-dropdown.show').forEach(d => { if (d !== drop) d.classList.remove('show'); });
            document.querySelectorAll('.pl-ia-chips-input.open').forEach(b => { if (b !== cont) b.classList.remove('open'); });
            if (drop) drop.classList.toggle('show');
            cont.classList.toggle('open');
        });
        if (drop) {
            drop.addEventListener('click', e => e.stopPropagation());
            drop.querySelectorAll('[data-add-mod]').forEach(opt => opt.addEventListener('click', () => {
                plIaFormState.modules.push(opt.dataset.addMod);
                plIaRenderModulesChips(inputId, target);
            }));
        }
    }

    // Rendu chips utilisateurs (clic sur la zone → ouvre la modale picker)
    function plIaRenderUsersChips() {
        const cont = $('plIaFUsersInput');
        if (!cont) return;
        const users = window.PL_DATA?.users || [];
        const list = plIaFormState.users;

        const chipsHtml = list.map(id => {
            const u = users.find(x => x.id === id);
            const initials = ((u?.prenom || ' ').charAt(0) + (u?.nom || ' ').charAt(0)).toUpperCase();
            const av = plIaPickerAvatarClass(id);
            const photo = (u?.photo || '').trim();
            const avatarHtml = photo
                ? `<img src="${plEsc(photo)}" alt="" class="pl-ia-user-chip-avatar pl-ia-user-chip-avatar-img">`
                : `<span class="pl-ia-user-chip-avatar ${av}">${plEsc(initials || '·')}</span>`;
            return `<span class="pl-ia-user-chip">${avatarHtml}${plEsc((u?.prenom || '') + ' ' + (u?.nom || ''))}<button type="button" class="pl-ia-chip-remove" data-rm-user="${plEsc(id)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M6 6l12 12M18 6L6 18"/></svg></button></span>`;
        }).join('');

        const placeholder = list.length ? 'Cliquez pour modifier la sélection' : 'Choisir des collaborateurs';
        cont.innerHTML = chipsHtml + `<span class="pl-ia-chips-placeholder">${plEsc(placeholder)}</span>`;
        cont.style.position = 'relative';

        cont.querySelectorAll('[data-rm-user]').forEach(btn => btn.addEventListener('click', (e) => {
            e.stopPropagation();
            plIaFormState.users = plIaFormState.users.filter(x => x !== btn.dataset.rmUser);
            plIaRenderUsersChips();
        }));

        // Click n'importe où sur la zone → ouvre la modale picker
        cont.addEventListener('click', (e) => {
            if (e.target.closest('.pl-ia-chip-remove')) return;
            e.stopPropagation();
            plIaOpenUserPicker();
        });
    }

    // ── Sub-modale 2-col "Sélectionner les collaborateurs" ──────────────────
    const plIaPickerBackdrop = $('plIaPickerBackdrop');
    let plIaPickerSelected   = new Set();
    let plIaPickerSearchQ    = '';
    // Filtres persistants (3 catégories indépendantes : fonction / module / etage)
    let plIaPickerFilters = { fonction: 'all', module: 'all', etage: 'all' };

    // Couleur d'avatar gradient (av-1 à av-6) déterministe par id
    function plIaPickerAvatarClass(userId) {
        let h = 0;
        for (let i = 0; i < (userId || '').length; i++) h = (h * 31 + userId.charCodeAt(i)) >>> 0;
        return 'av-' + ((h % 6) + 1);
    }

    function plIaOpenUserPicker() {
        plIaPickerSelected = new Set(plIaFormState.users || []);
        // ⚠ NE PAS réinitialiser plIaPickerFilters → persistance entre ouvertures
        plIaPickerSearchQ = '';
        if ($('plIaPickerSearch')) {
            $('plIaPickerSearch').value = '';
            $('plIaPickerSearchBar')?.classList.remove('has-value');
        }
        plIaPickerBackdrop?.classList.remove('hidden');
        plIaRenderPickerSidebar();
        plIaRenderPickerList();
        plIaUpdatePickerCounts();
    }
    function plIaClosePicker() { plIaPickerBackdrop?.classList.add('hidden'); }

    $('plIaPickerClose')?.addEventListener('click', plIaClosePicker);
    $('plIaPickerCancel')?.addEventListener('click', plIaClosePicker);
    plIaPickerBackdrop?.addEventListener('click', (e) => { if (e.target === plIaPickerBackdrop) plIaClosePicker(); });

    $('plIaPickerValidate')?.addEventListener('click', () => {
        plIaFormState.users = [...plIaPickerSelected];
        plIaClosePicker();
        plIaRenderUsersChips();
    });

    $('plIaPickerSearch')?.addEventListener('input', (e) => {
        plIaPickerSearchQ = (e.target.value || '').toLowerCase().trim();
        $('plIaPickerSearchBar')?.classList.toggle('has-value', plIaPickerSearchQ.length > 0);
        plIaRenderPickerList();
    });
    $('plIaPickerSearchClear')?.addEventListener('click', () => {
        if ($('plIaPickerSearch')) $('plIaPickerSearch').value = '';
        plIaPickerSearchQ = '';
        $('plIaPickerSearchBar')?.classList.remove('has-value');
        plIaRenderPickerList();
    });

    // Tout sélectionner / désélectionner (sur la liste actuellement visible)
    $('plIaPickerSelectAll')?.addEventListener('click', () => {
        plIaPickerVisibleUsers().forEach(u => plIaPickerSelected.add(u.id));
        plIaRenderPickerList();
        plIaUpdatePickerCounts();
    });
    $('plIaPickerClearAll')?.addEventListener('click', () => {
        plIaPickerVisibleUsers().forEach(u => plIaPickerSelected.delete(u.id));
        plIaRenderPickerList();
        plIaUpdatePickerCounts();
    });

    // Construit la sidebar des filtres (Fonction + Module + Étage)
    function plIaRenderPickerSidebar() {
        const sidebar = $('plIaPickerSidebar');
        if (!sidebar) return;
        const users    = window.PL_DATA?.users     || [];
        const modules  = window.PL_DATA?.modules   || [];
        const fonctions = window.PL_DATA?.fonctions || [];
        const etages   = window.PL_DATA?.etages    || [];

        // Compteurs (sur l'ensemble des users — les compteurs sont indicatifs)
        const cntByFct = {}, cntByMod = {}, cntByEtg = {};
        users.forEach(u => {
            if (u.fonction_code) cntByFct[u.fonction_code] = (cntByFct[u.fonction_code] || 0) + 1;
            (u.module_ids || '').split(',').filter(Boolean).forEach(id => cntByMod[id] = (cntByMod[id] || 0) + 1);
            (u.etage_ids || []).forEach(id => cntByEtg[id] = (cntByEtg[id] || 0) + 1);
        });

        function group(key, title, items, valueGetter, labelGetter, countMap) {
            const cur = plIaPickerFilters[key];
            const hasActive = cur && cur !== 'all';
            let html = '<div class="pl-ia-picker-fgroup' + (hasActive ? ' has-active' : '') + '" data-fgroup="' + key + '">';
            html += '<div class="pl-ia-picker-fgroup-head">';
            html += '<span class="pl-ia-picker-fgroup-title">' + plEsc(title) + '</span>';
            html += '<button type="button" class="pl-ia-picker-fgroup-clear" data-fclear="' + key + '">Tout</button>';
            html += '</div>';
            html += '<div class="pl-ia-picker-foptions">';
            html += '<button type="button" class="pl-ia-picker-foption' + (cur === 'all' ? ' active' : '') + '" data-fset="' + key + '" data-fval="all">Tous<span class="fcount">' + users.length + '</span></button>';
            items.forEach(it => {
                const v = valueGetter(it);
                const lab = labelGetter(it);
                const cnt = countMap[v] || 0;
                if (!cnt) return; // skip groups with 0 users
                html += '<button type="button" class="pl-ia-picker-foption' + (cur === v ? ' active' : '') + '" data-fset="' + key + '" data-fval="' + plEsc(v) + '" title="' + plEsc(lab) + '">' + plEsc(lab) + '<span class="fcount">' + cnt + '</span></button>';
            });
            html += '</div></div>';
            return html;
        }

        sidebar.innerHTML =
            group('fonction', 'Fonction', fonctions, f => f.code, f => f.code + (f.nom ? ' — ' + f.nom : ''), cntByFct) +
            group('module',   'Module',   modules,   m => m.id,   m => m.code + (m.nom ? ' — ' + m.nom : ''), cntByMod) +
            group('etage',    'Étage',    etages,    e => e.id,   e => e.nom || e.code, cntByEtg);

        sidebar.querySelectorAll('[data-fset]').forEach(btn => btn.addEventListener('click', () => {
            const key = btn.dataset.fset;
            const val = btn.dataset.fval;
            plIaPickerFilters[key] = val;
            plIaRenderPickerSidebar();
            plIaRenderPickerList();
        }));
        sidebar.querySelectorAll('[data-fclear]').forEach(btn => btn.addEventListener('click', () => {
            plIaPickerFilters[btn.dataset.fclear] = 'all';
            plIaRenderPickerSidebar();
            plIaRenderPickerList();
        }));
    }

    function plIaPickerVisibleUsers() {
        const users = window.PL_DATA?.users || [];
        let visible = users.slice();

        if (plIaPickerFilters.fonction !== 'all') {
            visible = visible.filter(u => u.fonction_code === plIaPickerFilters.fonction);
        }
        if (plIaPickerFilters.module !== 'all') {
            visible = visible.filter(u => (u.module_ids || '').split(',').filter(Boolean).includes(plIaPickerFilters.module));
        }
        if (plIaPickerFilters.etage !== 'all') {
            visible = visible.filter(u => (u.etage_ids || []).includes(plIaPickerFilters.etage));
        }
        if (plIaPickerSearchQ) {
            const q = plIaPickerSearchQ;
            visible = visible.filter(u => {
                const txt = ((u.prenom || '') + ' ' + (u.nom || '') + ' ' + (u.fonction_code || '') + ' ' + (u.fonction_nom || '')).toLowerCase();
                return txt.includes(q);
            });
        }
        return visible;
    }

    function plIaRenderPickerList() {
        const list = $('plIaPickerList');
        if (!list) return;
        const users    = window.PL_DATA?.users     || [];
        const modules  = window.PL_DATA?.modules   || [];
        const fonctions = window.PL_DATA?.fonctions || [];
        const etages   = window.PL_DATA?.etages    || [];

        const visible = plIaPickerVisibleUsers();

        // Toolbar : compteur résultats / total
        $('plIaPickerResultCount') && ($('plIaPickerResultCount').textContent = visible.length);
        $('plIaPickerTotalCount')  && ($('plIaPickerTotalCount').textContent  = users.length);

        if (!visible.length) {
            list.innerHTML = `
              <div class="pl-ia-picker-empty-2col">
                <div class="pl-ia-picker-empty-2col-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-3.5-3.5"/>
                    <path d="M11 8v3M11 14h.01"/>
                  </svg>
                </div>
                <h3>Aucun collaborateur trouvé</h3>
                <p>Essayez de modifier votre recherche ou vos filtres</p>
              </div>`;
            return;
        }

        // Groupement par fonction (pour les sections du mockup)
        const byFct = {};
        visible.forEach(u => {
            const k = u.fonction_code || '_';
            if (!byFct[k]) byFct[k] = [];
            byFct[k].push(u);
        });

        // Ordre des fonctions selon l'ordre de la table fonctions
        const fctOrder = fonctions.map(f => f.code).filter(c => byFct[c]);
        if (byFct['_']) fctOrder.push('_');

        let html = '';
        fctOrder.forEach(fctCode => {
            const arr = byFct[fctCode];
            const fct = fonctions.find(f => f.code === fctCode);
            const sectionTitle = fct?.nom || fct?.code || 'Sans fonction';
            html += `
              <div class="pl-ia-picker-section">
                <div class="pl-ia-picker-section-head">
                  <span class="pl-ia-picker-section-title">${plEsc(sectionTitle)}</span>
                  <span class="pl-ia-picker-section-count">${arr.length} résultat${arr.length > 1 ? 's' : ''}</span>
                </div>
            `;
            arr.forEach(u => {
                const checked  = plIaPickerSelected.has(u.id);
                const initials = ((u.prenom || ' ').charAt(0) + (u.nom || ' ').charAt(0)).toUpperCase();
                const av = plIaPickerAvatarClass(u.id);
                // Photo si dispo (sinon avatar gradient + initiales)
                const photo = (u.photo || '').trim();
                const avatarHtml = photo
                    ? `<img src="${plEsc(photo)}" alt="" class="pl-ia-picker-uavatar pl-ia-picker-uavatar-img">`
                    : `<div class="pl-ia-picker-uavatar ${av}">${plEsc(initials || '·')}</div>`;

                // Module(s) du user (1er module pour l'affichage compact)
                const modIds = (u.module_ids || '').split(',').filter(Boolean);
                const firstMod = modIds[0] ? modules.find(m => m.id === modIds[0])?.code : null;
                // Étage(s) du user (1er étage pour l'affichage compact)
                const firstEtg = (u.etage_ids || [])[0] ? etages.find(e => e.id === u.etage_ids[0])?.nom : null;
                const metaParts = [];
                if (firstMod) metaParts.push(firstMod + (firstEtg ? ' · ' + firstEtg : ''));
                else if (firstEtg) metaParts.push(firstEtg);

                html += `
                  <button type="button" class="pl-ia-picker-uitem${checked ? ' selected' : ''}" data-pick-row="${plEsc(u.id)}">
                    ${avatarHtml}
                    <div class="pl-ia-picker-uinfo">
                      <div class="pl-ia-picker-uname">${plEsc((u.prenom || '') + ' ' + (u.nom || ''))}</div>
                      <div class="pl-ia-picker-umeta">
                        ${u.fonction_code ? `<span class="pl-ia-picker-umeta-tag">${plEsc(u.fonction_code)}</span>` : ''}
                        ${metaParts.length ? `<span class="pl-ia-picker-umeta-dot"></span><span>${plEsc(metaParts.join(' · '))}</span>` : ''}
                      </div>
                    </div>
                    <div class="pl-ia-picker-ucheck">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                  </button>
                `;
            });
            html += '</div>';
        });

        list.innerHTML = html;

        list.querySelectorAll('[data-pick-row]').forEach(row => row.addEventListener('click', () => {
            const id = row.dataset.pickRow;
            if (plIaPickerSelected.has(id)) plIaPickerSelected.delete(id);
            else plIaPickerSelected.add(id);
            row.classList.toggle('selected');
            plIaUpdatePickerCounts();
        }));
    }

    function plIaUpdatePickerCounts() {
        const n = plIaPickerSelected.size;
        $('plIaPickerCountTop')     && ($('plIaPickerCountTop').textContent     = n);
        $('plIaPickerFooterCount')  && ($('plIaPickerFooterCount').textContent  = n);
        $('plIaPickerConfirmBadge') && ($('plIaPickerConfirmBadge').textContent = n);
    }

    async function plIaSaveRule() {
        const titre = ($('plIaFTitre')?.value || '').trim();
        if (!titre) { plToast('Titre requis', 'error'); return; }
        const description = ($('plIaFDesc')?.value || '').trim();
        const ruleType   = plIaFormState.type;
        const importance = plIaFormState.importance;
        const targetMode = plIaFormState.target;

        let ruleParams = {};
        if (ruleType === 'user_schedule') {
            ruleParams.shift_codes = plIaFormState.shifts.slice();
            ruleParams.exclude_shift_codes = plIaFormState.shiftsExclude.slice();
            ruleParams.days = plIaFormState.days.slice();
        } else if (ruleType === 'shift_only') {
            ruleParams.shift_codes = plIaFormState.shifts.slice();
        } else if (ruleType === 'shift_exclude') {
            ruleParams.shift_codes = plIaFormState.shiftsExclude.slice();
        } else if (ruleType === 'days_only') {
            ruleParams.days = plIaFormState.days.slice();
        } else if (ruleType === 'max_days_week') {
            ruleParams.max_days = plIaFormState.maxDays;
        } else if (ruleType === 'module_only' || ruleType === 'module_exclude') {
            ruleParams.module_ids = plIaFormState.modules.slice();
        }

        const targetModuleIds = (targetMode === 'module') ? plIaFormState.modules.slice() : [];
        const userIds         = (targetMode === 'users')  ? plIaFormState.users.slice()   : [];

        const data = {
            titre, description, importance,
            rule_type: ruleType,
            rule_params: JSON.stringify(ruleParams),
            target_mode: targetMode,
            target_fonction_code: plIaFormState.fonctionCode,
            user_ids: userIds,
            target_module_ids: targetModuleIds,
        };
        const action = plIaEditId ? 'admin_update_ia_rule' : 'admin_create_ia_rule';
        if (plIaEditId) data.id = plIaEditId;

        const res = await plApiPost(action, data);
        if (res?.success) {
            plToast(plIaEditId ? 'Règle modifiée' : 'Règle créée', 'ok');
            plIaView = 'list'; plIaEditId = null;
            await plIaLoadRules();
        } else {
            plToast(res?.message || 'Erreur', 'error');
        }
    }

    // Bouton retour dans le header (mode formulaire)
    $('plIaBackBtn')?.addEventListener('click', () => { plIaView = 'list'; plIaRender(); });

    // Config IA avancée → ouvre la page admin (pas implémenté → toast info)
    plIaFooter?.addEventListener('click', (e) => {
        if (e.target.closest('#plIaConfigBtn')) plToast('Config IA avancée — TODO', 'info');
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
