<?php
/**
 * Planning Admin — Module Planning
 *
 * Réécriture clean-slate (avril 2026) selon mockup Spocspace Care.
 * Les styles vivent dans admin/assets/css/planning.css.
 *
 * NOTE Phase 1 : la grille rend les VRAIS users actifs depuis la DB,
 * groupés par fonction. Les cellules shifts sont **vides** — la logique
 * d'assignation / génération IA / édition de cellule (ancien planning.js)
 * sera réintégrée en Phase 2.
 *
 * Hooks JS injectés ici (suffisent pour le mockup) :
 *   - Dropdown période (sélecteur de mois 3×4 + nav année)
 *   - Dropdown vue (semaine / mois)
 *   - Nav arrows (prev/today/next mois)
 *   - Toggle Provisoire / Finaliser
 *   - 5 presets de taille (XS / SM / MD / STD / LG) sur les variables CSS
 *   - Fullscreen toggle (masque sidebar + topbar du shell admin)
 *   - Filtre équipes (pills) — filtrage local des lignes
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
// Emojis saisonniers FR (selon spec design : janvier=❄️, février=💧, mars=🌱, etc.)
$plMonthEmojis = [
    1 => '❄️', 2 => '💧', 3 => '🌱', 4 => '🌸', 5 => '🌿', 6 => '☀️',
    7 => '🌻', 8 => '🏖️', 9 => '🍂', 10 => '🎃', 11 => '🍁', 12 => '🎄'
];
$plDayNamesShort = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// Jours pour l'en-tête (avec nom court + numéro + flag weekend/today)
$plDays = [];
for ($d = 1; $d <= $plDaysInMonth; $d++) {
    $ts = mktime(0, 0, 0, $plMonth, $d, $plYear);
    $w  = (int) date('N', $ts); // 1 = Lundi, 7 = Dimanche
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

// ─── Groupement des users par fonction (sections) ───────────────────────────
$plUsersByFonction = [];
foreach ($planningUsers as $u) {
    $code = $u['fonction_code'] ?? 'SANS';
    if (!isset($plUsersByFonction[$code])) {
        $plUsersByFonction[$code] = [
            'code'  => $code,
            'nom'   => $u['fonction_nom'] ?? 'Sans fonction',
            'ordre' => $u['fonction_ordre'] ?? 999,
            'users' => [],
        ];
    }
    $plUsersByFonction[$code]['users'][] = $u;
}
uasort($plUsersByFonction, fn($a, $b) => ($a['ordre'] ?? 999) - ($b['ordre'] ?? 999));

// ─── Compteurs pour la barre de filtres équipes ─────────────────────────────
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

// Mapping fonction_code → classe role-tag (pour les couleurs des role tags)
function pl_role_class(string $code): string {
    $c = strtoupper($code);
    if ($c === 'INF')  return 'pl-role-inf';
    if ($c === 'ASSC') return 'pl-role-assc';
    if ($c === 'AS')   return 'pl-role-as';
    if (in_array($c, ['ANIM', 'ASE'], true)) return 'pl-role-anim';
    if ($c === 'RUV')  return 'pl-role-ruv';
    return ''; // role-tag par défaut (gris neutre)
}

// Calcul "heures cible" approximatif : taux % × 1.82 ≈ heures mensuelles à 100% = 182h
function pl_target_hours(float $taux): float {
    return round($taux * 1.82, 1);
}
?>

<!-- ═══ Page Planning ═══════════════════════════════════════════════════════ -->
<div class="planning-page flex flex-col min-h-[calc(100vh-64px)]" id="planningPage">

  <!-- ── Command bar : période + vue + nav + status + actions ─────────────── -->
  <div class="pl-command-bar">

    <!-- Période + Vue (groupe accolé avec dropdowns) -->
    <div class="pl-period-group">
      <button type="button" class="pl-period-btn" id="plPeriodBtn">
        <span class="pl-period-icon"><?= $plMonthEmojis[$plMonth] ?? '📅' ?></span>
        <span class="pl-period-text">
          <span class="pl-period-label">Période</span>
          <span class="pl-period-value" id="plPeriodLabel"><?= h($plMonthNamesFr[$plMonth]) ?> <?= (int) $plYear ?></span>
        </span>
        <svg class="pl-period-chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <button type="button" class="pl-period-btn" id="plViewBtn">
        <span class="pl-period-icon">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/></svg>
        </span>
        <span class="pl-period-text">
          <span class="pl-period-label">Vue</span>
          <span class="pl-period-value" id="plViewLabel">Mois</span>
        </span>
        <svg class="pl-period-chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </button>

      <!-- Dropdown PÉRIODE -->
      <div class="pl-dropdown pl-dd-period" id="plPeriodDropdown" role="dialog" aria-label="Sélection du mois">
        <div class="pl-dd-head">
          <button type="button" class="pl-year-nav" id="plYearPrev" aria-label="Année précédente">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <span class="pl-year" id="plYearLabel"><?= (int) $plYear ?></span>
          <button type="button" class="pl-year-nav" id="plYearNext" aria-label="Année suivante">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
        <div class="pl-month-grid">
          <?php foreach ($plMonthNamesFr as $m => $name): ?>
          <button type="button" class="pl-month <?= $m === $plMonth ? 'is-active' : ($m < $plMonth ? 'is-past' : '') ?>" data-month="<?= $m ?>">
            <span class="pl-month-emoji"><?= $plMonthEmojis[$m] ?? '📅' ?></span>
            <span class="pl-month-num"><?= sprintf('%02d', $m) ?></span>
            <span class="pl-month-name"><?= h(mb_substr($name, 0, 3)) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <div class="pl-dd-foot">
          <button type="button" class="pl-dd-foot-btn" id="plTodayBtn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 2"/></svg>
            Aujourd'hui
          </button>
        </div>
      </div>

      <!-- Dropdown VUE -->
      <div class="pl-dropdown pl-dd-view" id="plViewDropdown" role="menu" aria-label="Type de vue">
        <button type="button" class="pl-dd-view-item" data-view="semaine" role="menuitem">
          <span class="pl-dd-view-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 14h2M13 14h2M9 18h2M13 18h2"/></svg>
          </span>
          <span class="pl-dd-view-text">
            <span class="pl-dd-view-name">Vue semaine</span>
            <span class="pl-dd-view-desc">7 jours · plus de détail par cellule</span>
          </span>
          <svg class="pl-dd-view-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
        <button type="button" class="pl-dd-view-item is-active" data-view="mois" role="menuitem">
          <span class="pl-dd-view-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </span>
          <span class="pl-dd-view-text">
            <span class="pl-dd-view-name">Vue mois</span>
            <span class="pl-dd-view-desc">Vue d'ensemble · 28-31 jours</span>
          </span>
          <svg class="pl-dd-view-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
      </div>
    </div>

    <!-- Nav arrows -->
    <div class="pl-nav">
      <button type="button" class="pl-nav-btn" id="plNavPrev" title="Mois précédent">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <button type="button" class="pl-nav-btn pl-nav-today" id="plNavToday" title="Aujourd'hui">Auj.</button>
      <button type="button" class="pl-nav-btn" id="plNavNext" title="Mois suivant">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>

    <!-- Status badge -->
    <div class="pl-status">
      <span class="pl-status-pulse"></span>
      Brouillon
    </div>

    <!-- Compteur d'assignations -->
    <div class="pl-meta"><strong id="plAssignCount">0</strong> assignations</div>

    <div class="pl-spacer"></div>

    <!-- Bouton Générer planning (action primaire dark) -->
    <button type="button" class="pl-btn pl-btn-dark" id="plGenerateBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg>
      Générer planning
    </button>

    <!-- Bouton Créer -->
    <button type="button" class="pl-btn" id="plCreateBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Créer
    </button>

    <!-- Groupe icônes outils (stats / filtres / supprimer / fullscreen) -->
    <div class="pl-icon-group">
      <button type="button" class="pl-btn-icon" title="Statistiques">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" title="Filtres avancés">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="11" y2="6"/><line x1="14" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="6" y2="12"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="13" y2="18"/><line x1="16" y1="18" x2="20" y2="18"/><circle cx="12.5" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="14.5" cy="18" r="2"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" title="Supprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" id="plFullscreenBtn" title="Plein écran (F11)">
        <svg id="plFullscreenIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V3h4M21 7V3h-4M3 17v4h4M21 17v4h-4"/></svg>
      </button>
    </div>

    <!-- Groupe icônes export (imprimer / PDF / email / CSV) -->
    <div class="pl-icon-group">
      <button type="button" class="pl-btn-icon" title="Imprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" title="Exporter PDF">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" title="Envoyer par email">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
      </button>
      <button type="button" class="pl-btn-icon" title="Exporter CSV">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
      </button>
    </div>

    <!-- Bouton Proposition -->
    <button type="button" class="pl-btn" id="plPropositionBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 00-6 0v4M5 9h14l1 12H4z"/></svg>
      Proposition
    </button>

    <!-- Icône liste (voir toutes les propositions) -->
    <button type="button" class="pl-btn-icon" title="Voir toutes les propositions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </button>

    <!-- Toggle Provisoire / Finaliser -->
    <div class="pl-finalize">
      <button type="button" class="on" data-finalize="provisoire">Provisoire</button>
      <button type="button" data-finalize="finaliser">Finaliser</button>
    </div>

  </div>

  <!-- ── Team filters : pills horizontales ─────────────────────────────────── -->
  <div class="pl-team-filters">
    <button type="button" class="pl-team-pill on" data-team-filter="" data-team-type="all">Tous · <?= (int) $plCountTotal ?></button>
    <span class="pl-team-divider"></span>
    <?php foreach ($planningModules as $m): if (in_array($m['code'], ['POOL','NUIT'], true)) continue; ?>
    <button type="button" class="pl-team-pill" data-team-filter="<?= h($m['code']) ?>" data-team-type="module"><?= h($m['code']) ?> · <?= (int) ($plCountByModule[$m['code']] ?? 0) ?></button>
    <?php endforeach; ?>
    <span class="pl-team-divider"></span>
    <?php foreach ($planningModules as $m): if (!in_array($m['code'], ['POOL','NUIT'], true)) continue; ?>
    <button type="button" class="pl-team-pill" data-team-filter="<?= h($m['code']) ?>" data-team-type="module"><?= h($m['code']) ?></button>
    <?php endforeach; ?>
    <?php if (!empty($plCountByFonction)): ?>
    <span class="pl-team-divider"></span>
    <?php foreach ($planningFonctions as $f): if (empty($plCountByFonction[$f['code']])) continue; ?>
    <button type="button" class="pl-team-pill" data-team-filter="<?= h($f['code']) ?>" data-team-type="fonction" title="<?= h($f['nom']) ?>"><?= h($f['code']) ?></button>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ── Planning grid ─────────────────────────────────────────────────────── -->
  <div class="pl-grid-wrap" id="plGridWrap">

    <div class="pl-grid-scroll">
      <table class="pl-table" id="plTable">

        <thead>
          <tr>
            <th class="pl-col-collab">
              <span class="pl-collab-header">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/></svg>
                Collaborateur
              </span>
            </th>
            <th class="pl-col-pct">%</th>
            <?php foreach ($plDays as $day): ?>
            <th class="pl-day-head <?= $day['weekend'] ? 'pl-weekend' : '' ?> <?= $day['today'] ? 'pl-today' : '' ?>">
              <span class="pl-day-name"><?= h($day['name']) ?></span>
              <span class="pl-day-num"><?= (int) $day['num'] ?></span>
            </th>
            <?php endforeach; ?>
            <th class="pl-col-hours">Heures</th>
          </tr>
        </thead>

        <tbody>
          <?php $isFirstSection = true; foreach ($plUsersByFonction as $section): ?>
          <!-- Section header -->
          <tr class="pl-section" data-team-fonction="<?= h($section['code']) ?>">
            <td class="pl-col-collab" colspan="2">
              <div class="pl-section-title">
                <?= h($section['nom']) ?> · <?= h($section['code']) ?>
                <span class="pl-section-count"><?= count($section['users']) ?></span>
              </div>
            </td>
            <td colspan="<?= $plNbDays ?>"></td>
            <?php if ($isFirstSection): // Le sélecteur de taille n'apparaît que sur la 1ère section pour le mockup ?>
            <td class="pl-col-hours pl-size-cell">
              <div class="pl-size-controls" role="group" aria-label="Taille du tableau">
                <button type="button" class="pl-size-btn" data-size="xs" title="Très petit">
                  <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="6" y="6" width="4" height="4" rx="1"/></svg>
                </button>
                <button type="button" class="pl-size-btn" data-size="sm" title="Petit">
                  <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><rect x="5" y="5" width="6" height="6" rx="1"/></svg>
                </button>
                <button type="button" class="pl-size-btn" data-size="md" title="Moyen">
                  <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor"><rect x="3.5" y="3.5" width="9" height="9" rx="1.5"/></svg>
                </button>
                <button type="button" class="pl-size-btn is-active" data-size="std" title="Standard (défaut)">
                  <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="2" width="12" height="12" rx="2"/></svg>
                </button>
                <button type="button" class="pl-size-btn" data-size="lg" title="Grand">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><rect x="0.5" y="0.5" width="15" height="15" rx="2"/></svg>
                </button>
              </div>
            </td>
            <?php else: ?>
            <td></td>
            <?php endif; ?>
          </tr>

          <?php foreach ($section['users'] as $u):
            $taux = (float) ($u['taux'] ?? 0);
            $tauxRounded = (int) round($taux);
            $cible = pl_target_hours($taux);
            $heuresCourant = 0; // Phase 1 : pas de calcul heures courantes
            $diff = $heuresCourant - $cible;
            $modCodes = explode(',', (string) ($u['module_codes'] ?? ''));
          ?>
          <tr data-user-id="<?= h($u['id']) ?>"
              data-fonction="<?= h($u['fonction_code'] ?? '') ?>"
              data-modules="<?= h(implode(' ', array_filter($modCodes))) ?>">
            <td class="pl-col-collab">
              <div class="pl-collab-cell">
                <span class="pl-role-tag <?= pl_role_class($u['fonction_code'] ?? '') ?>"><?= h($u['fonction_code'] ?? '—') ?></span>
                <div class="pl-collab-info">
                  <div class="pl-collab-name"><?= h(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?></div>
                </div>
              </div>
            </td>
            <td class="pl-col-pct">
              <div class="pl-pct"><?= $tauxRounded ?>%</div>
              <div class="pl-pct-bar"><div style="width:<?= $tauxRounded ?>%"></div></div>
            </td>
            <?php foreach ($plDays as $day): ?>
            <td class="pl-day-cell <?= $day['weekend'] ? 'pl-weekend' : '' ?> <?= $day['today'] ? 'pl-today' : '' ?>">
              <!-- Phase 2 : injecter ici les shifts assignés (ex: <span class="pl-shift pl-shift-c1">C1</span>) -->
            </td>
            <?php endforeach; ?>
            <td class="pl-col-hours">
              <div class="pl-hours">
                <div class="pl-hours-main"><?= $heuresCourant ?>h</div>
                <div class="pl-hours-target <?= $diff < 0 ? 'pl-hours-under' : ($diff > 0 ? 'pl-hours-over' : '') ?>"><?= $cible ?>h<?= $diff !== 0.0 ? ' · ' . ($diff > 0 ? '+' : '') . $diff : '' ?></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php $isFirstSection = false; endforeach; ?>

          <?php if (empty($plUsersByFonction)): ?>
          <tr>
            <td colspan="<?= $plNbDays + 3 ?>" class="text-center py-12 text-muted">
              Aucun collaborateur actif dans la base.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Footer planning : légende shifts + stats -->
    <div class="pl-foot">
      <div class="pl-legend">
        <span class="pl-legend-label">Shifts</span>
        <?php foreach ($planningHoraires as $hor): if (empty($hor['couleur'])) continue; ?>
        <span class="pl-legend-item">
          <span class="pl-legend-sw" style="background: <?= h($hor['couleur']) ?>"></span>
          <?= h($hor['code']) ?> · <?= h(substr($hor['heure_debut'] ?? '', 0, 5)) ?>-<?= h(substr($hor['heure_fin'] ?? '', 0, 5)) ?>
        </span>
        <?php endforeach; ?>
      </div>
      <div class="pl-foot-stats">
        <div class="pl-foot-stat">
          <span class="pl-foot-stat-num"><?= (int) $plCountTotal ?></span>
          <span class="pl-foot-stat-lbl">Collab.</span>
        </div>
        <div class="pl-foot-stat">
          <span class="pl-foot-stat-num" id="plFootShifts">0</span>
          <span class="pl-foot-stat-lbl">Shifts</span>
        </div>
        <div class="pl-foot-stat">
          <span class="pl-foot-stat-num text-warn" id="plFootMissing">—</span>
          <span class="pl-foot-stat-lbl">Manquants</span>
        </div>
      </div>
    </div>

  </div>

</div>

<script<?= nonce() ?>>
// ═════════════════════════════════════════════════════════════════════════════
// Planning page — JS interactions (mockup phase 1)
//   - Dropdowns période + vue
//   - Nav arrows (prev/today/next mois) — recharge avec ?year & ?month
//   - Toggle Provisoire / Finaliser
//   - 5 presets de taille (XS / SM / MD / STD / LG)
//   - Fullscreen toggle (masque sidebar + topbar du shell)
//   - Filtre équipes (pills)
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

    // Sélection mois (recharge la page)
    document.querySelectorAll('.pl-month').forEach(btn => {
        btn.addEventListener('click', () => gotoMonth(currentYear, parseInt(btn.dataset.month, 10)));
    });
    $('plTodayBtn')?.addEventListener('click', () => {
        const now = new Date();
        gotoMonth(now.getFullYear(), now.getMonth() + 1);
    });

    // Sélection vue (semaine / mois) — Phase 2 pour la vraie vue semaine
    document.querySelectorAll('.pl-dd-view-item').forEach(item => {
        item.addEventListener('click', () => {
            const view = item.dataset.view;
            if (viewLabel) viewLabel.textContent = view === 'semaine' ? 'Semaine' : 'Mois';
            document.querySelectorAll('.pl-dd-view-item').forEach(i => i.classList.remove('is-active'));
            item.classList.add('is-active');
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
    document.querySelectorAll('.pl-finalize button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pl-finalize button').forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
        });
    });

    // ── 5 presets de taille ─────────────────────────────────────────────────
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
        planningTable.style.setProperty('--pl-cell-w',       preset.cellW + 'px');
        planningTable.style.setProperty('--pl-cell-h',       preset.cellH + 'px');
        planningTable.style.setProperty('--pl-shift-min-w',  preset.shiftMinW + 'px');
        planningTable.style.setProperty('--pl-shift-h',      preset.shiftH + 'px');
        planningTable.style.setProperty('--pl-shift-fs',     preset.shiftFs + 'px');
        planningTable.style.setProperty('--pl-day-num-size', preset.dayNumSize + 'px');
        planningTable.style.setProperty('--pl-day-num-fs',   preset.dayNumFs + 'px');
        document.querySelectorAll('.pl-size-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.size === size);
        });
        try { localStorage.setItem('ss_planning_size', size); } catch(e) {}
    }
    document.querySelectorAll('.pl-size-btn').forEach(btn => {
        btn.addEventListener('click', () => applySize(btn.dataset.size));
    });
    try {
        const saved = localStorage.getItem('ss_planning_size');
        if (saved && SIZE_PRESETS[saved]) applySize(saved);
    } catch(e) {}

    // ── Fullscreen toggle (masque sidebar + topbar) ─────────────────────────
    const fullscreenBtn  = $('plFullscreenBtn');
    const fullscreenIcon = $('plFullscreenIcon');
    const ICON_EXPAND    = '<path d="M3 7V3h4M21 7V3h-4M3 17v4h4M21 17v4h-4"/>';
    const ICON_COLLAPSE  = '<path d="M8 3v4H4M16 3v4h4M8 21v-4H4M16 21v-4h4"/>';

    function togglePlFullscreen() {
        const isFs = document.body.classList.toggle('pl-is-fullscreen');
        fullscreenBtn?.classList.toggle('is-active', isFs);
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

    // ── Filtre équipes (pills) ──────────────────────────────────────────────
    document.querySelectorAll('.pl-team-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.pl-team-pill').forEach(p => p.classList.remove('on'));
            pill.classList.add('on');
            const filter = pill.dataset.teamFilter || '';
            const type   = pill.dataset.teamType   || 'all';

            document.querySelectorAll('#plTable tbody tr').forEach(row => {
                if (row.classList.contains('pl-section')) {
                    if (type === 'fonction') {
                        const sectionFonc = row.dataset.teamFonction || '';
                        row.style.display = (sectionFonc === filter) ? '' : 'none';
                    } else {
                        row.style.display = '';
                    }
                    return;
                }
                if (type === 'all' || !filter) {
                    row.style.display = '';
                } else if (type === 'fonction') {
                    row.style.display = (row.dataset.fonction === filter) ? '' : 'none';
                } else if (type === 'module') {
                    const mods = (row.dataset.modules || '').split(/\s+/);
                    row.style.display = mods.includes(filter) ? '' : 'none';
                }
            });
        });
    });

    // ── Boutons d'action (Phase 2 : reconnecter à la logique IA / création) ──
    $('plGenerateBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') {
            showToast('Génération IA — fonctionnalité Phase 2', 'info');
        } else {
            alert('Génération IA — fonctionnalité Phase 2');
        }
    });
    $('plCreateBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') {
            showToast('Création — fonctionnalité Phase 2', 'info');
        }
    });
    $('plPropositionBtn')?.addEventListener('click', () => {
        if (typeof showToast === 'function') {
            showToast('Propositions — fonctionnalité Phase 2', 'info');
        }
    });

})();

window.initPlanningPage = function() {
    // Hook SPA — pas d'init supplémentaire pour l'instant, tout est dans l'IIFE ci-dessus.
};
</script>
