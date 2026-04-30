<?php
/**
 * Liste des collaborateurs — Module Planning
 * Mockup utilisateur 29 avril 2026 (Tailwind/Spocspace Care).
 */

// ─── Données serveur ──────────────────────────────────────────────────────────
$today = date('Y-m-d');

$usersRaw = Db::fetchAll(
    "SELECT u.id, u.employee_id, u.email, u.nom, u.prenom, u.photo, u.taux,
            u.type_contrat, u.solde_vacances, u.role, u.is_active,
            f.code AS fonction_code, f.nom AS fonction_nom,
            (SELECT m.id FROM user_modules um JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = u.id ORDER BY um.is_principal DESC, m.ordre LIMIT 1) AS module_id,
            (SELECT m.code FROM user_modules um JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = u.id ORDER BY um.is_principal DESC, m.ordre LIMIT 1) AS module_code,
            (SELECT m.nom FROM user_modules um JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = u.id ORDER BY um.is_principal DESC, m.ordre LIMIT 1) AS module_nom,
            (SELECT a.type FROM absences a
             WHERE a.user_id = u.id AND a.statut = 'valide'
               AND a.date_debut <= ? AND a.date_fin >= ?
             ORDER BY FIELD(a.type,'maladie','accident','vacances','formation','conge_special','autre') LIMIT 1) AS statut_today
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     ORDER BY u.nom, u.prenom",
    [$today, $today]
);

// Liste des modules pour les stat cards (top-N triés par ordre, hors POOL/NUIT)
$modulesAll = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
$modulesMain = array_values(array_filter($modulesAll, fn($m) => !in_array($m['code'], ['POOL', 'NUIT'])));

// Compteurs par module : total, présents, absents, vacances
$moduleStats = [];
foreach ($modulesMain as $m) {
    $moduleStats[$m['id']] = [
        'id'       => $m['id'],
        'code'     => $m['code'],
        'nom'      => $m['nom'],
        'total'    => 0,
        'presents' => 0,
        'absents'  => 0,
        'vacances' => 0,
    ];
}
$globalCounts = ['presents' => 0, 'absents' => 0, 'vacances' => 0, 'total' => 0];

foreach ($usersRaw as $u) {
    if (!$u['is_active']) continue;
    $globalCounts['total']++;
    $statut = $u['statut_today'] ?? null;
    $isVac     = ($statut === 'vacances');
    $isAbsent  = in_array($statut, ['maladie','accident','conge_special','autre'], true);
    if ($isVac)            $globalCounts['vacances']++;
    elseif ($isAbsent)     $globalCounts['absents']++;
    else                   $globalCounts['presents']++;

    if (!empty($u['module_id']) && isset($moduleStats[$u['module_id']])) {
        $moduleStats[$u['module_id']]['total']++;
        if ($isVac)        $moduleStats[$u['module_id']]['vacances']++;
        elseif ($isAbsent) $moduleStats[$u['module_id']]['absents']++;
        else               $moduleStats[$u['module_id']]['presents']++;
    }
}

// Couleurs sec-* en rotation pour les cards module (ordre du module → palette)
$secPalette = ['sec-anim', 'sec-hotel', 'sec-soins', 'sec-tech', 'sec-int', 'sec-admin', 'sec-mgmt'];
$i = 0;
foreach ($moduleStats as &$ms) {
    $ms['sec'] = $secPalette[$i % count($secPalette)];
    $i++;
}
unset($ms);

$totalActive = $globalCounts['total'];
$nbModulesMain = count($modulesMain);

// Compteurs par fonction (dérivés des users actifs présents en DB)
$fonctionStats = [];
foreach ($usersRaw as $u) {
    if (!$u['is_active']) continue;
    $code = $u['fonction_code'] ?? '';
    if (!$code) continue;
    if (!isset($fonctionStats[$code])) {
        $fonctionStats[$code] = [
            'code'  => $code,
            'nom'   => $u['fonction_nom'] ?? $code,
            'count' => 0,
        ];
    }
    $fonctionStats[$code]['count']++;
}
// Tri par count desc puis par code asc (les fonctions les + représentées en tête)
uasort($fonctionStats, function($a, $b) {
    return $b['count'] !== $a['count']
        ? $b['count'] - $a['count']
        : strcmp($a['code'], $b['code']);
});

// Mapping statut absence → libellé + tonalité (pour table)
// PRÉSENT/E (vert) · MALADIE (rouge) · ACCIDENT (orange) · VACANCES (info) · EN FORMATION (info) · AUTRE (gris)
$statutMap = [
    'maladie'       => ['label' => 'MALADIE',      'tone' => 'danger'],
    'accident'      => ['label' => 'ACCIDENT',     'tone' => 'warn'],
    'vacances'      => ['label' => 'VACANCES',     'tone' => 'info'],
    'formation'     => ['label' => 'EN FORMATION', 'tone' => 'info'],
    'conge_special' => ['label' => 'CONGÉ',        'tone' => 'muted'],
    'autre'         => ['label' => 'ABSENT',       'tone' => 'muted'],
];

// Avatar palette teintes sec-* → un couleur stable par utilisateur (hash du id)
$avatarSecs = ['sec-soins', 'sec-anim', 'sec-hotel', 'sec-tech', 'sec-int', 'sec-admin', 'sec-mgmt'];

// Inject minimal data ; tout le rendu de la table se fait JS-side pour pagination/filtres
foreach ($usersRaw as &$u) {
    unset($u['email']); // pas besoin côté JS pour le moment, on garde le minimum
    $u['avatar_sec'] = $avatarSecs[crc32($u['id']) % count($avatarSecs)];
    if ($u['statut_today']) {
        $u['statut_label'] = $statutMap[$u['statut_today']]['label'] ?? strtoupper($u['statut_today']);
        $u['statut_tone']  = $statutMap[$u['statut_today']]['tone'] ?? 'muted';
    } else {
        $u['statut_label'] = ($u['nom'] && in_array(mb_strtolower(mb_substr($u['prenom'], -1)), ['e','a'])) ? 'PRÉSENTE' : 'PRÉSENT';
        $u['statut_tone']  = 'ok';
    }
}
unset($u);
?>

<!-- ─── Hero : titre + sous-titre + actions ─────────────────────────────────── -->
<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
  <div class="min-w-0">
    <div class="flex items-center gap-2 text-[12px] text-muted mb-2">
      <span class="text-teal-700 font-medium">Module Planning</span>
      <svg class="w-3.5 h-3.5 text-muted-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <span>Horaires collaborateurs</span>
    </div>
    <h1 class="text-4xl lg:text-5xl font-semibold text-ink tracking-[-0.02em] leading-none mb-3">Horaires collaborateurs</h1>
    <p class="text-[14px] text-ink-3 max-w-2xl leading-relaxed">
      <span class="font-mono tabular-nums font-semibold text-ink"><?= (int) $totalActive ?> collaborateurs</span>
      répartis sur <span class="font-mono tabular-nums font-semibold text-ink"><?= $nbModulesMain ?> <?= $nbModulesMain > 1 ? 'modules' : 'module' ?></span>
      · vue planning : présence, taux, soldes vacances et contrat.
    </p>
  </div>
  <div class="flex items-center gap-2 shrink-0">
    <button type="button" id="usersBtnExport" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-line text-[13.5px] font-medium text-ink-2 hover:border-teal-300 hover:text-teal-700 hover:bg-teal-50/50 transition-colors bg-surface">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exporter
    </button>
    <a href="<?= admin_url('planning') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-line text-[13.5px] font-medium text-ink-2 hover:border-teal-300 hover:text-teal-700 hover:bg-teal-50/50 transition-colors bg-surface">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Voir le planning
    </a>
    <button type="button" id="usersBtnAdd" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-[13.5px] font-medium shadow-sp-sm transition-colors">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Ajouter
    </button>
  </div>
</div>

<!-- ─── Stat cards : 1 par module (max 4 en 1 row) ──────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= max(2, min(4, $nbModulesMain)) ?> gap-3 mb-4">
  <?php foreach ($moduleStats as $ms): ?>
  <div class="relative bg-surface border border-line rounded-xl p-4 overflow-hidden hover:shadow-sp-sm transition-shadow">
    <span class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-<?= h($ms['sec']) ?>"></span>
    <div class="flex items-start justify-between mb-3 pl-2">
      <div class="min-w-0">
        <div class="font-body text-[10.5px] tracking-[0.08em] text-muted uppercase font-semibold mb-0.5">Module</div>
        <div class="text-[15px] font-semibold text-ink tracking-[-0.005em] truncate" title="<?= h($ms['nom']) ?>"><?= h($ms['nom']) ?></div>
      </div>
      <div class="w-9 h-9 rounded-lg grid place-items-center bg-<?= h($ms['sec']) ?>-bg shrink-0">
        <svg class="w-4 h-4 text-<?= h($ms['sec']) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h12a1 1 0 001-1V10"/><path d="M9 21V12h6v9"/>
        </svg>
      </div>
    </div>
    <div class="flex items-baseline gap-1.5 pl-2 mb-2">
      <span class="font-display font-semibold text-3xl tabular-nums text-ink leading-none"><?= (int) $ms['total'] ?></span>
      <span class="font-body text-[12px] text-muted">collab.</span>
    </div>
    <div class="flex items-center gap-3 pl-2 text-[11.5px] font-body tabular-nums">
      <span class="text-ok"><span class="font-semibold tabular-nums"><?= (int) $ms['presents'] ?></span> présents</span>
      <span class="text-warn"><span class="font-semibold tabular-nums"><?= (int) $ms['absents'] ?></span> absent<?= $ms['absents'] > 1 ? 's' : '' ?></span>
      <span class="text-info"><span class="font-semibold tabular-nums"><?= (int) $ms['vacances'] ?></span> vac.</span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── Filter bar : 3 rangs (Module / Fonction / Statut) — search via topbar global ── -->
<div class="bg-surface border border-line rounded-xl p-3 mb-4 flex flex-col gap-2.5">
  <!-- Module -->
  <div class="flex items-center gap-2 flex-wrap">
    <span class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-semibold w-[68px] shrink-0">Module</span>
    <button type="button" data-filter-module="" class="filter-pill is-active inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-teal-600 bg-teal-600 text-white transition-colors">
      Tous · <span class="font-mono tabular-nums"><?= $totalActive ?></span>
    </button>
    <?php foreach ($moduleStats as $ms): ?>
    <button type="button" data-filter-module="<?= h($ms['id']) ?>" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-line text-ink-2 hover:border-teal-300 hover:text-teal-700 transition-colors bg-surface">
      <span class="w-1.5 h-1.5 rounded-full bg-<?= h($ms['sec']) ?>"></span>
      <?= h($ms['nom']) ?> · <span class="font-mono tabular-nums"><?= (int) $ms['total'] ?></span>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Fonction -->
  <?php if (!empty($fonctionStats)): ?>
  <div class="flex items-center gap-2 flex-wrap">
    <span class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-semibold w-[68px] shrink-0">Fonction</span>
    <button type="button" data-filter-fonction="" class="filter-pill-fonction is-active inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-teal-600 bg-teal-600 text-white transition-colors">
      Toutes · <span class="font-mono tabular-nums"><?= $totalActive ?></span>
    </button>
    <?php foreach ($fonctionStats as $f): ?>
    <button type="button" data-filter-fonction="<?= h($f['code']) ?>" title="<?= h($f['nom']) ?>" class="filter-pill-fonction inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-line text-ink-2 hover:border-teal-300 hover:text-teal-700 transition-colors bg-surface">
      <?= h($f['code']) ?> · <span class="font-mono tabular-nums"><?= (int) $f['count'] ?></span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Statut -->
  <div class="flex items-center gap-2 flex-wrap">
    <span class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-semibold w-[68px] shrink-0">Statut</span>
    <button type="button" data-filter-statut="presents" class="filter-pill-statut inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-line text-ink-2 hover:border-ok hover:text-ok transition-colors bg-surface">
      <span class="w-1.5 h-1.5 rounded-full bg-ok"></span>
      Présents · <span class="font-mono tabular-nums"><?= (int) $globalCounts['presents'] ?></span>
    </button>
    <button type="button" data-filter-statut="absents" class="filter-pill-statut inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-line text-ink-2 hover:border-danger hover:text-danger transition-colors bg-surface">
      <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
      Absents · <span class="font-mono tabular-nums"><?= (int) $globalCounts['absents'] ?></span>
    </button>
    <button type="button" data-filter-statut="vacances" class="filter-pill-statut inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12.5px] font-medium border border-line text-ink-2 hover:border-info hover:text-info transition-colors bg-surface">
      <span class="w-1.5 h-1.5 rounded-full bg-info"></span>
      Vacances · <span class="font-mono tabular-nums"><?= (int) $globalCounts['vacances'] ?></span>
    </button>
  </div>
</div>

<!-- ─── Liste des collaborateurs ────────────────────────────────────────────── -->
<div class="bg-surface border border-line rounded-xl overflow-hidden">
  <div class="px-5 py-4 border-b border-line flex items-end justify-between gap-4">
    <div class="min-w-0">
      <h2 class="text-[20px] font-semibold text-ink tracking-[-0.005em] leading-tight">Liste des collaborateurs</h2>
      <p class="text-[12.5px] text-muted mt-0.5">
        <span id="usersCount" class="font-mono tabular-nums font-medium text-ink-3"><?= $totalActive ?></span>
        collaborateurs · vue planning
      </p>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-[13px]">
      <thead class="border-b border-line">
        <tr class="text-[10.5px] tracking-[0.08em] uppercase text-muted font-semibold">
          <th class="text-left font-semibold pl-5 pr-3 py-3">Collaborateur</th>
          <th class="text-left font-semibold px-3 py-3">Étage</th>
          <th class="text-left font-semibold px-3 py-3 w-48">Taux d'activité</th>
          <th class="text-left font-semibold px-3 py-3">Statut aujourd'hui</th>
          <th class="text-left font-semibold px-3 py-3">Solde vacances</th>
          <th class="text-left font-semibold px-3 py-3">Compétences</th>
          <th class="text-right font-semibold pr-5 pl-3 py-3">Actions</th>
        </tr>
      </thead>
      <tbody id="usersTableBody" class="divide-y divide-line">
        <tr><td colspan="7" class="text-center py-10 text-muted text-[13px]">Chargement...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <div class="px-5 py-3 border-t border-line flex items-center justify-between gap-3 flex-wrap">
    <p class="text-[12.5px] text-muted" id="paginationInfo"></p>
    <div class="flex items-center gap-1" id="paginationBtns"></div>
  </div>
</div>

<!-- ─── Modal Nouveau collaborateur (placeholder — réutilise styles Bootstrap legacy en attente d'une migration dédiée) ── -->
<div id="createUserModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/50 p-4" data-modal>
  <div class="bg-surface border border-line rounded-2xl shadow-sp-md w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
    <div class="flex items-center justify-between p-5 border-b border-line shrink-0">
      <h3 class="text-lg font-semibold text-ink">Nouveau collaborateur</h3>
      <button type="button" data-modal-close class="p-1.5 rounded-md text-muted hover:bg-surface-3 hover:text-ink-2 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="createUserForm" class="flex-1 overflow-y-auto p-5 space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-ink-2 mb-1">Prénom *</label>
          <input type="text" name="prenom" required class="w-full bg-surface-3 border border-line rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100">
        </div>
        <div>
          <label class="block text-[12px] font-medium text-ink-2 mb-1">Nom *</label>
          <input type="text" name="nom" required class="w-full bg-surface-3 border border-line rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100">
        </div>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-ink-2 mb-1">Email *</label>
        <input type="email" name="email" required class="w-full bg-surface-3 border border-line rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-ink-2 mb-1">Taux %</label>
          <input type="number" name="taux" value="100" min="20" max="100" step="5" class="w-full bg-surface-3 border border-line rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100">
        </div>
        <div>
          <label class="block text-[12px] font-medium text-ink-2 mb-1">Contrat</label>
          <select name="type_contrat" class="w-full bg-surface-3 border border-line rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100">
            <option value="CDI">CDI</option>
            <option value="CDD">CDD</option>
            <option value="stagiaire">Stagiaire</option>
            <option value="civiliste">Civiliste</option>
            <option value="interim">Intérim</option>
          </select>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
        <button type="button" data-modal-close class="px-4 py-2 rounded-lg border border-line text-[13px] font-medium text-ink-2 hover:bg-surface-3 transition-colors">Annuler</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-[13px] font-medium transition-colors">Créer</button>
      </div>
    </form>
  </div>
</div>

<script<?= nonce() ?>>
let allUsers = <?= json_encode(array_values($usersRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
let filteredUsers = [];
let currentPage = 1;
const PAGE_SIZE = 11; // 11 lignes par page comme dans la maquette
let currentFilters = { module: '', fonction: '', statut: '', search: '' };

// ── Mappings côté JS ────────────────────────────────────────────────────────
const STATUT_TONES = {
    ok:     'bg-ok-bg text-ok border-ok-line',
    danger: 'bg-danger-bg text-danger border-danger-line',
    warn:   'bg-warn-bg text-warn border-warn-line',
    info:   'bg-info-bg text-info border-info-line',
    muted:  'bg-surface-3 text-ink-3 border-line',
};

const MODULE_BG = {
    <?php foreach ($moduleStats as $ms): ?>
    '<?= h($ms['id']) ?>': { sec: '<?= h($ms['sec']) ?>', nom: <?= json_encode($ms['nom']) ?> },
    <?php endforeach; ?>
};

const CONTRAT_LABELS = { CDI: 'CDI', CDD: 'CDD', stagiaire: 'STAGE', civiliste: 'CIV.', interim: 'INTÉRIM' };
const CONTRAT_TONES  = {
    CDI:       'bg-surface-3 text-ink-3 border-line',
    CDD:       'bg-warn-bg text-warn border-warn-line',
    stagiaire: 'bg-info-bg text-info border-info-line',
    civiliste: 'bg-info-bg text-info border-info-line',
    interim:   'bg-warn-bg text-warn border-warn-line',
};

// ── Init page ──────────────────────────────────────────────────────────────
function initUsersPage() {
    // Filtre par module (pills)
    document.querySelectorAll('[data-filter-module]').forEach(btn => {
        btn.addEventListener('click', () => {
            currentFilters.module = btn.dataset.filterModule;
            document.querySelectorAll('[data-filter-module]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
                if (b === btn) {
                    b.classList.add('bg-teal-600','text-white','border-teal-600');
                    b.classList.remove('bg-surface','text-ink-2','border-line','hover:border-teal-300','hover:text-teal-700');
                } else {
                    b.classList.remove('bg-teal-600','text-white','border-teal-600');
                    b.classList.add('bg-surface','text-ink-2','border-line','hover:border-teal-300','hover:text-teal-700');
                }
            });
            applyFilters();
        });
    });

    // Filtre par fonction (pills, mêmes mécaniques que module)
    document.querySelectorAll('[data-filter-fonction]').forEach(btn => {
        btn.addEventListener('click', () => {
            currentFilters.fonction = btn.dataset.filterFonction;
            document.querySelectorAll('[data-filter-fonction]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
                if (b === btn) {
                    b.classList.add('bg-teal-600','text-white','border-teal-600');
                    b.classList.remove('bg-surface','text-ink-2','border-line','hover:border-teal-300','hover:text-teal-700');
                } else {
                    b.classList.remove('bg-teal-600','text-white','border-teal-600');
                    b.classList.add('bg-surface','text-ink-2','border-line','hover:border-teal-300','hover:text-teal-700');
                }
            });
            applyFilters();
        });
    });

    // Filtre par statut (toggle pills)
    document.querySelectorAll('[data-filter-statut]').forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.dataset.filterStatut;
            currentFilters.statut = (currentFilters.statut === val) ? '' : val;
            document.querySelectorAll('[data-filter-statut]').forEach(b => {
                const active = (currentFilters.statut !== '' && b.dataset.filterStatut === currentFilters.statut);
                b.classList.toggle('is-active', active);
                if (active) {
                    b.classList.add('bg-teal-600','text-white','border-teal-600');
                    b.classList.remove('bg-surface','text-ink-2','border-line');
                } else {
                    b.classList.remove('bg-teal-600','text-white','border-teal-600');
                    b.classList.add('bg-surface','text-ink-2','border-line');
                }
            });
            applyFilters();
        });
    });

    // Topbar global search → utilisé comme filtre principal de la liste
    document.getElementById('topbarSearchInput')?.addEventListener('input', (e) => {
        currentFilters.search = (e.target.value || '').toLowerCase();
        applyFilters();
    });

    // Pagination clicks
    document.getElementById('paginationBtns').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (btn && !btn.disabled) {
            currentPage = parseInt(btn.dataset.page);
            renderPage();
        }
    });

    // Click ligne → fiche collaborateur
    document.getElementById('usersTableBody').addEventListener('click', (e) => {
        if (e.target.closest('a, button')) return;
        const tr = e.target.closest('tr[data-user-href]');
        if (tr) window.location.href = tr.dataset.userHref;
    });

    // Modal create user (Tailwind native, pas de Bootstrap)
    const modal = document.getElementById('createUserModal');
    const openModal  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.getElementById('usersBtnAdd')?.addEventListener('click', openModal);
    modal.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    document.getElementById('createUserForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd);
        data.role = 'collaborateur';
        const res = await adminApiPost('admin_create_user', data);
        if (res.success) {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            showToast(res.message || 'Collaborateur créé', 'success');
            e.target.reset();
            await loadUsers();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // Export bouton (placeholder)
    document.getElementById('usersBtnExport')?.addEventListener('click', () => {
        showToast?.('Export à implémenter', 'info');
    });

    applyFilters();
}

// ── Filtres ────────────────────────────────────────────────────────────────
function applyFilters() {
    let f = allUsers.filter(u => u.is_active);

    if (currentFilters.module) {
        f = f.filter(u => u.module_id === currentFilters.module);
    }
    if (currentFilters.fonction) {
        f = f.filter(u => u.fonction_code === currentFilters.fonction);
    }
    if (currentFilters.statut === 'presents') {
        f = f.filter(u => !u.statut_today);
    } else if (currentFilters.statut === 'absents') {
        f = f.filter(u => ['maladie','accident','conge_special','autre'].includes(u.statut_today));
    } else if (currentFilters.statut === 'vacances') {
        f = f.filter(u => u.statut_today === 'vacances');
    }
    if (currentFilters.search) {
        const s = currentFilters.search;
        f = f.filter(u =>
            (u.nom + ' ' + u.prenom + ' ' + (u.fonction_nom || '') + ' ' + (u.module_nom || '')).toLowerCase().includes(s)
        );
    }

    filteredUsers = f;
    currentPage = 1;
    document.getElementById('usersCount').textContent = f.length;
    renderPage();
}

// ── Pagination + rendu liste ───────────────────────────────────────────────
function renderPage() {
    const total = filteredUsers.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageUsers = filteredUsers.slice(start, start + PAGE_SIZE);

    renderUsers(pageUsers);

    const infoEl = document.getElementById('paginationInfo');
    if (total === 0) {
        infoEl.textContent = '';
    } else {
        infoEl.innerHTML = `Affichage <span class="font-mono tabular-nums font-medium text-ink-2">${start + 1} – ${Math.min(start + PAGE_SIZE, total)}</span> sur <span class="font-mono tabular-nums font-medium text-ink-2">${total} collaborateurs</span>`;
    }

    const btns = document.getElementById('paginationBtns');
    if (totalPages <= 1) { btns.innerHTML = ''; return; }

    let html = '';
    const pillBase = 'min-w-[34px] h-[34px] px-2 inline-flex items-center justify-center rounded-md text-[13px] font-medium transition-colors';
    const pillIdle = 'border border-line text-ink-2 hover:border-teal-300 hover:text-teal-700 bg-surface';
    const pillActive = 'border border-teal-600 bg-teal-600 text-white shadow-sp-sm';
    const pillDisabled = 'border border-line text-muted-2 bg-surface cursor-not-allowed';

    html += `<button class="${pillBase} ${currentPage <= 1 ? pillDisabled : pillIdle}" ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}" aria-label="Précédent">‹</button>`;

    const range = getPaginationRange(currentPage, totalPages);
    range.forEach(p => {
        if (p === '...') {
            html += `<span class="px-1.5 text-muted-2 text-[14px]">…</span>`;
        } else {
            html += `<button class="${pillBase} ${p === currentPage ? pillActive : pillIdle}" data-page="${p}">${p}</button>`;
        }
    });

    html += `<button class="${pillBase} ${currentPage >= totalPages ? pillDisabled : pillIdle}" ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}" aria-label="Suivant">›</button>`;
    btns.innerHTML = html;
}

function getPaginationRange(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [];
    pages.push(1);
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
}

// ── Rendu ligne ────────────────────────────────────────────────────────────
function renderAvatar(u) {
    if (u.photo) {
        return `<img src="${escapeHtml(u.photo)}" alt="" class="w-9 h-9 rounded-full object-cover ring-1 ring-line shrink-0">`;
    }
    const initials = ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase();
    return `<div class="w-9 h-9 rounded-full grid place-items-center text-white text-[12px] font-bold bg-${u.avatar_sec} shrink-0">${escapeHtml(initials)}</div>`;
}

function renderModuleBadge(u) {
    const m = MODULE_BG[u.module_id];
    if (!m) return `<span class="text-muted-2 text-[12px]">—</span>`;
    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11.5px] font-medium bg-${m.sec}-bg text-${m.sec} whitespace-nowrap">
        <span class="w-1.5 h-1.5 rounded-full bg-${m.sec}"></span>
        ${escapeHtml(m.nom)}
    </span>`;
}

function renderTaux(u) {
    const taux = Math.round(parseFloat(u.taux) || 0);
    const pct = Math.max(0, Math.min(100, taux));
    return `<div class="flex items-center gap-3">
        <div class="flex-1 h-1.5 rounded-full bg-surface-3 overflow-hidden">
            <div class="h-full rounded-full bg-grad-progress" style="width:${pct}%"></div>
        </div>
        <span class="font-mono tabular-nums text-[12.5px] font-semibold text-ink-2 shrink-0 w-10 text-right">${pct}%</span>
    </div>`;
}

function renderStatut(u) {
    const tone = STATUT_TONES[u.statut_tone] || STATUT_TONES.muted;
    const dotColor = u.statut_tone === 'ok' ? 'bg-ok'
                   : u.statut_tone === 'danger' ? 'bg-danger'
                   : u.statut_tone === 'warn' ? 'bg-warn'
                   : u.statut_tone === 'info' ? 'bg-info' : 'bg-muted-2';
    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10.5px] font-bold tracking-[0.04em] border ${tone} whitespace-nowrap">
        <span class="w-1.5 h-1.5 rounded-full ${dotColor}"></span>
        ${escapeHtml(u.statut_label)}
    </span>`;
}

function renderSolde(u) {
    const solde = parseFloat(u.solde_vacances ?? 0);
    const total = 25; // baseline annuelle
    const low = solde > 0 && solde < 6;
    const cls = low ? 'text-warn' : 'text-ink-2';
    const soldeStr = solde % 1 === 0 ? String(solde) : solde.toFixed(1).replace('.', ',');
    return `<div class="font-mono tabular-nums text-[13px]">
        <span class="font-semibold ${cls}">${soldeStr}</span>
        <span class="text-muted-2 ml-1">/ ${total} jours</span>
    </div>`;
}

function renderCompetences(u) {
    // Heuristique pour le mockup : responsable → "Resp. équipe"
    // (tant qu'on n'a pas de table compétences réelle).
    if (u.role === 'responsable') {
        return `<span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-medium bg-sec-soins-bg text-sec-soins border border-sec-soins/20 whitespace-nowrap">Resp. équipe</span>`;
    }
    if (u.fonction_code && /INF/i.test(u.fonction_code)) {
        return `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium bg-warn-bg text-warn border border-warn-line whitespace-nowrap">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26"/></svg>
            Réf. plaies
        </span>`;
    }
    if (u.fonction_code && /ANIM/i.test(u.fonction_code)) {
        return `<span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-medium bg-sec-anim-bg text-sec-anim border border-sec-anim/20 whitespace-nowrap">Réf. animation</span>`;
    }
    return `<span class="text-muted-2 text-[13px]">—</span>`;
}

function renderContrat(u) {
    const label = CONTRAT_LABELS[u.type_contrat] || u.type_contrat;
    const tone  = CONTRAT_TONES[u.type_contrat]  || CONTRAT_TONES.CDI;
    return `<span class="inline-flex items-center px-1.5 py-px rounded text-[9.5px] font-mono font-bold tracking-[0.04em] border ${tone}">${escapeHtml(label)}</span>`;
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!users.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-12 text-muted text-[13px]">Aucun collaborateur ne correspond aux filtres</td></tr>`;
        return;
    }
    const editPath = (id) => AdminURL.page('user-edit', id);
    const detailPath = (id) => AdminURL.page('user-detail', id);

    tbody.innerHTML = users.map(u => `
        <tr data-user-href="${detailPath(u.id)}" class="hover:bg-surface-3/50 cursor-pointer transition-colors">
            <td class="pl-5 pr-3 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    ${renderAvatar(u)}
                    <div class="min-w-0">
                        <div class="text-[14px] font-semibold text-ink leading-tight truncate">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</div>
                        <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                            <span class="text-[11.5px] text-muted truncate">${escapeHtml(u.fonction_nom || '—')}</span>
                            ${renderContrat(u)}
                        </div>
                    </div>
                </div>
            </td>
            <td class="px-3 py-3">${renderModuleBadge(u)}</td>
            <td class="px-3 py-3">${renderTaux(u)}</td>
            <td class="px-3 py-3">${renderStatut(u)}</td>
            <td class="px-3 py-3">${renderSolde(u)}</td>
            <td class="px-3 py-3">${renderCompetences(u)}</td>
            <td class="pr-5 pl-3 py-3 text-right">
                <div class="inline-flex items-center gap-1">
                    <a href="${AdminURL.page('planning')}" class="w-8 h-8 inline-grid place-items-center rounded-md border border-line text-muted hover:text-teal-700 hover:border-teal-300 hover:bg-teal-50/50 transition-colors" title="Voir au planning">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </a>
                    <a href="${detailPath(u.id)}" class="w-8 h-8 inline-grid place-items-center rounded-md border border-line text-muted hover:text-teal-700 hover:border-teal-300 hover:bg-teal-50/50 transition-colors" title="Voir la fiche">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </div>
            </td>
        </tr>`).join('');
}

async function loadUsers() {
    const res = await adminApiPost('admin_get_users');
    allUsers = (res.users || []).filter(u => u.is_active);
    applyFilters();
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

window.initUsersPage = initUsersPage;
window.loadUsers = loadUsers;

// Auto-init si chargé via SPA ou directement
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUsersPage);
} else {
    initUsersPage();
}
</script>
