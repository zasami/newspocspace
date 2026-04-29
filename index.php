<?php
/**
 * SpocSpace - SPA Shell (Frontend collaborateurs)
 * Sidebar layout — same pattern as admin panel
 */
require_once __DIR__ . '/init.php';

$csrfToken = $_SESSION['ss_csrf_token'] ?? '';
$user = $_SESSION['ss_user'] ?? null;
$v = APP_VERSION;

// CSP nonce
$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; worker-src 'self' blob:;");

// Load EMS config for logo + name
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'SpocSpace';

// Éligibilité changements : l'utilisateur doit avoir au moins un collègue actif de même fonction
// CSS mode (classic or tailwind)
$cssMode = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'css_mode'") ?: 'classic';

$canChangement = $user && !empty($user['fonction_id']) && (bool) Db::getOne(
    "SELECT COUNT(*) FROM users WHERE fonction_id = ? AND id != ? AND is_active = 1",
    [$user['fonction_id'], $user['id']]
);

// Backfill type_employe in session if missing (post-migration)
if ($user && !isset($user['type_employe'])) {
    $user['type_employe'] = Db::getOne("SELECT type_employe FROM users WHERE id = ?", [$user['id']]) ?: 'interne';
    $_SESSION['ss_user']['type_employe'] = $user['type_employe'];
}

// Per-user denied permissions
$deniedPerms = $user ? ($_SESSION['ss_user']['denied_perms'] ?? []) : [];

// Préférence de thème (default | sombre | care)
$themePref = $user ? (Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$user['id']]) ?: 'default') : 'default';
$themeBodyClass = 'theme-' . preg_replace('/[^a-z]/', '', $themePref);

$sidebarNav = [
    'main' => [
        'label' => 'Navigation',
        'items' => [
            'home'        => ['label' => 'Accueil',        'icon' => 'house'],
            'profile'     => ['label' => 'Mon profil',    'icon' => 'person-circle'],
            'planning'    => ['label' => 'Planning',       'icon' => 'calendar3'],
            'repartition' => ['label' => 'Répartition',    'icon' => 'grid-3x3-gap'],
            'desirs'      => ['label' => 'Mes Désirs',     'icon' => 'star'],
            'vacances'    => ['label' => 'Vacances',       'icon' => 'sun'],
            'absences'    => ['label' => 'Absences',       'icon' => 'calendar-x'],
            'changements' => ['label' => 'Changements',    'icon' => 'arrow-left-right'],
        ],
    ],
    'collab' => [
        'label' => 'Collaboration',
        'items' => [
            'collegues'   => ['label' => 'Collègues',            'icon' => 'people'],
            'covoiturage' => ['label' => 'Covoiturage',      'icon' => 'car-front'],
            'salles'      => ['label' => 'Réservation salles',  'icon' => 'door-open'],
            'emails'      => ['label' => 'Messagerie interne','icon' => 'chat-dots'],
            'mur'         => ['label' => 'Mur social',         'icon' => 'chat-square-heart'],
            'mes-stagiaires' => ['label' => 'Mes stagiaires', 'icon' => 'mortarboard'],
        ],
    ],
    'cuisine' => [
        'label' => 'Cuisine',
        'items' => [
            'cuisine-menus'    => ['label' => 'Menus',                'icon' => 'journal-text'],
            'cuisine-reservations' => ['label' => 'Commandes',           'icon' => 'receipt'],
            'cuisine-famille'  => ['label' => 'Réservations famille', 'icon' => 'house-heart'],
            'cuisine-vip'      => ['label' => 'Table VIP',           'icon' => 'star'],
        ],
    ],
    'info' => [
        'label' => 'Informations',
        'items' => [
            'annuaire'  => ['label' => 'Annuaire téléphonique', 'icon' => 'telephone'],
            'annonces'  => ['label' => 'Annonces officielles', 'icon' => 'megaphone'],
            'evenements' => ['label' => 'Événements',          'icon' => 'calendar-event'],
            'votes'     => ['label' => 'Votes',           'icon' => 'hand-thumbs-up'],
            'pv'        => ['label' => 'Procès-Verbaux',  'icon' => 'file-earmark-text'],
            'sondages'  => ['label' => 'Sondages',        'icon' => 'clipboard2-check'],
            'documents' => ['label' => 'Documents',       'icon' => 'folder2-open'],
            'fiches-salaire' => ['label' => 'Fiches de salaire', 'icon' => 'receipt'],
            'fiches-amelioration' => ['label' => 'Amélioration continue', 'icon' => 'lightbulb'],
            'wiki'      => ['label' => 'Base de connaissances', 'icon' => 'book'],
        ],
    ],
    'parcours' => [
        'label' => 'Mon parcours',
        'items' => [
            'formations' => ['label' => 'Mes formations', 'icon' => 'mortarboard'],
        ],
    ],
    'preferences' => [
        'label' => 'Préférences',
        'items' => [
            'apparence' => ['label' => 'Apparence', 'icon' => 'palette'],
        ],
    ],
];

// Module Suggestions / Co-construction : injecté si flag activé
$sugFlag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($sugFlag === '1') {
    $sidebarNav['info']['items']['suggestions'] = ['label' => 'Suggestions', 'icon' => 'lightbulb'];
}

// Add profile link for external users
if ($user && ($user['type_employe'] ?? '') === 'externe') {
    $sidebarNav['compte'] = [
        'label' => 'Mon compte',
        'items' => [
            'profile' => ['label' => 'Mon profil', 'icon' => 'person-circle'],
        ],
    ];
}

// Stagiaire: ajoute "Mon stage", retire les items non pertinents
if ($user && ($user['role'] ?? '') === 'stagiaire') {
    $sidebarNav['stage'] = [
        'label' => 'Mon stage',
        'items' => [
            'mon-stage' => ['label' => 'Mon stage', 'icon' => 'journal-text'],
        ],
    ];
    // Remove "mes-stagiaires" from collab for stagiaires themselves
    unset($sidebarNav['collab']['items']['mes-stagiaires']);
}

// Filter sidebar items by feature toggles (ems_config)
$featureToggleMap = [
    'desirs' => 'feature_desirs', 'absences' => 'feature_absences',
    'changements' => 'feature_changements', 'covoiturage' => 'feature_covoiturage',
    'emails' => 'feature_emails', 'mur' => 'feature_mur_social',
    'votes' => 'feature_votes', 'pv' => 'feature_pv',
    'sondages' => 'feature_sondages', 'documents' => 'feature_documents',
    'fiches-salaire' => 'feature_fiches_salaire',
];
$disabledFeatures = [];
$featureRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key LIKE 'feature_%'");
foreach ($featureRows as $fr) {
    if ($fr['config_value'] === '0') $disabledFeatures[] = $fr['config_key'];
}
foreach ($sidebarNav as $catId => &$cat) {
    foreach ($cat['items'] as $key => $item) {
        $fk = $featureToggleMap[$key] ?? null;
        if ($fk && in_array($fk, $disabledFeatures)) {
            unset($cat['items'][$key]);
        }
    }
    if (empty($cat['items'])) unset($sidebarNav[$catId]);
}
unset($cat);

// Filter sidebar items by user permissions (Permission::PAGE_MAP)
if ($user && !empty($deniedPerms)) {
    foreach ($sidebarNav as $catId => &$cat) {
        foreach ($cat['items'] as $key => $item) {
            $permKey = Permission::PAGE_MAP[$key] ?? null;
            if ($permKey && in_array($permKey, $deniedPerms)) {
                unset($cat['items'][$key]);
            }
        }
        // Remove empty categories
        if (empty($cat['items'])) {
            unset($sidebarNav[$catId]);
        }
    }
    unset($cat);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SpocSpace — Gestion des Plannings</title>
<meta name="description" content="Application de gestion des plannings - SpocSpace, Genève">
<meta name="theme-color" content="#1A1A1A">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/newspocspace/manifest.json">
<link rel="apple-touch-icon" href="/newspocspace/assets/icons/icon-192x192.png">
<link rel="icon" href="/newspocspace/assets/icons/icon-96x96.png" type="image/png">
<!-- Bootstrap retiré (newspocspace = base Tailwind/Spocspace Care). bootstrap-icons gardé temporairement pour les pages non-migrées. -->
<link rel="stylesheet" href="assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/ss-colors.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/spocspace.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/emoji-picker.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/annonces.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/pages-all.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/themes.css?v=<?= $v ?>">
<?php include __DIR__ . '/tailwind-config.php'; ?>
</head>
<body class="<?= h($themeBodyClass) ?>">

<?php if ($user): ?>
<!-- BACKDROP (mobile) — fe-sidebar-overlay garde le hook spocspace.css (.fe-sidebar-overlay.show{display:block}) -->
<div id="sidebarOverlay" class="fe-sidebar-overlay fixed inset-0 bg-ink/60 z-30 hidden lg:!hidden"></div>

<!-- SIDEBAR — fe-sidebar gardé pour le hook body.no-nav .fe-sidebar{display:none} -->
<aside id="feSidebar" class="fe-sidebar
  fixed lg:sticky inset-y-0 left-0 z-40
  w-60 h-screen overflow-y-auto
  bg-sidebar-grad text-sb-text font-body
  p-[18px] flex flex-col gap-7
  -translate-x-full lg:translate-x-0
  transition-transform duration-200">

  <!-- ── Brand : logo + Spocspace / EMS Platform ── -->
  <div class="flex items-center justify-between gap-2 shrink-0">
    <a href="/newspocspace/profile" data-link="profile" class="flex items-center gap-2.5 px-1 group min-w-0" title="<?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?> — Voir mon profil">
      <img src="/newspocspace/ss-logo.png" alt="Spocspace" class="w-[34px] h-[34px] shrink-0 rounded-[9px]">
      <div class="min-w-0">
        <div class="font-display text-xl font-semibold text-white tracking-[-0.02em] leading-tight truncate">Spocspace</div>
        <div class="text-[10.5px] text-sb-sub tracking-[0.12em] uppercase mt-0.5 font-medium">EMS Platform</div>
      </div>
    </a>
    <button id="sidebarToggleBtn" class="text-sb-text hover:text-white p-1.5 rounded-md hover:bg-white/[0.06] transition-colors shrink-0" title="Réduire le menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <line x1="9" y1="3" x2="9" y2="21"/>
      </svg>
    </button>
  </div>

  <!-- ── Navigation dynamique ── -->
  <nav class="fe-sidebar-nav flex-1 flex flex-col gap-0.5 overflow-y-auto -mx-[2px] -my-1 py-1 pr-1
              [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-thumb]:bg-white/10 [&::-webkit-scrollbar-thumb]:rounded">
    <?php foreach ($sidebarNav as $catId => $cat): ?>
    <div class="fe-sidebar-cat flex items-center justify-between text-[10.5px] tracking-[0.14em] uppercase text-sb-section px-2.5 mb-1 mt-3 first:mt-0 font-semibold cursor-pointer select-none hover:text-sb-text-hover transition-colors"
         data-cat-toggle="<?= $catId ?>">
      <span><?= h($cat['label']) ?></span>
      <svg class="w-3 h-3 opacity-60 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </div>
    <div class="fe-sidebar-cat-items flex flex-col gap-0.5 [&.collapsed]:hidden" data-cat-body="<?= $catId ?>">
      <?php foreach ($cat['items'] as $key => $item):
            $isDisabled = ($key === 'changements' && !$canChangement); ?>
      <a href="/newspocspace/<?= $key ?>"
         class="fe-sidebar-link relative flex items-center gap-3 px-2.5 py-2 rounded-lg text-[13.5px] font-normal text-sb-text hover:bg-white/[0.04] hover:text-sb-text-hover transition-colors
                [&.active]:pl-[15px] [&.active]:bg-[#7dd3a8]/[0.12] [&.active]:text-white [&.active]:font-medium
                [&.active]:before:content-[''] [&.active]:before:absolute [&.active]:before:left-0 [&.active]:before:top-1/2 [&.active]:before:-translate-y-1/2 [&.active]:before:w-[3px] [&.active]:before:h-4 [&.active]:before:bg-[#7dd3a8] [&.active]:before:rounded-[3px]
                <?= $isDisabled ? 'opacity-40 pointer-events-none' : '' ?>"
         data-link="<?= $key ?>"
         <?= $isDisabled ? 'data-disabled="1" aria-disabled="true"' : '' ?>
         title="<?= h($item['label']) ?><?= $isDisabled ? ' (non disponible — aucun collègue de même fonction)' : '' ?>">
        <i class="bi bi-<?= $item['icon'] ?> opacity-85 text-[15px] w-4 text-center shrink-0"></i>
        <span class="flex-1 truncate"><?= h($item['label']) ?></span>
        <?php if ($key === 'emails'): ?>
        <span id="msgBadgeSidebar" class="ml-auto text-[10px] font-mono font-bold bg-[#7dd3a8] text-teal-900 rounded-full px-1.5 py-px shrink-0" style="display:none"></span>
        <?php endif; ?>
        <?php if ($key === 'annonces'): ?>
        <span id="annBadgeSidebar" class="ml-auto text-[10px] font-mono font-bold bg-[#7dd3a8] text-teal-900 rounded-full px-1.5 py-px shrink-0" style="display:none"></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <!-- ── Footer : carte EMS actif + actions ── -->
  <div class="mt-auto flex flex-col gap-2 shrink-0">

    <!-- Carte EMS actif (style verre dépoli) -->
    <div class="bg-white/[0.04] border border-white/[0.07] rounded-[10px] p-3">
      <div class="text-[10px] tracking-[0.12em] uppercase text-sb-sub font-medium">EMS actif</div>
      <div class="text-white font-medium mt-0.5 text-[13px] truncate"><?= h($emsNom ?: 'SpocSpace') ?></div>
      <div class="text-[11px] text-sb-muted mt-0.5 font-mono">v<?= APP_VERSION ?></div>
    </div>

    <!-- Actions : Admin (si rôle) + Déconnexion -->
    <div class="flex gap-1">
      <?php if (in_array($user['role'], ['admin', 'direction', 'responsable'])): ?>
      <a href="/newspocspace/admin/" class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-[12px] font-medium text-sb-text hover:bg-white/[0.05] hover:text-sb-text-hover transition-colors" title="Administration">
        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Admin
      </a>
      <?php endif; ?>
      <button id="logoutBtn" type="button" class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-[12px] font-medium text-sb-text hover:bg-danger/[0.18] hover:text-[#ffb8b3] transition-colors bg-transparent border-0" title="Déconnexion">
        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sortir
      </button>
    </div>
  </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="fe-main" id="feMain">
  <!-- TOPBAR -->
  <header class="fe-topbar">
    <div class="fe-topbar-left">
      <button class="fe-topbar-hamburger" id="mobileToggle" title="Menu">
        <i class="bi bi-list"></i>
      </button>
      <a href="/newspocspace/home" data-link="home" class="fe-topbar-brand" title="Accueil">
        <img src="/newspocspace/ss-logo.png" alt="SpocSpace" class="fe-topbar-brand-logo">
        <span class="fe-conn-status" id="feConnStatus" title="En ligne">
          <span class="fe-conn-dot fe-conn-online"></span>
          <span class="fe-conn-count" id="feConnPending" style="display:none"></span>
        </span>
      </a>
      <h5 class="fe-topbar-title" id="feTopbarTitle">Accueil</h5>
      <span class="fe-sync-indicator" id="feSyncIndicator" title="Dernier sync" style="display:none">
        <i class="bi bi-arrow-repeat"></i>
        <span id="feSyncTime"></span>
      </span>
    </div>
    <div class="fe-topbar-search" id="feTopbarSearch">
      <i class="bi bi-search fe-search-icon"></i>
      <input type="text" class="form-control" id="feSearchInput" placeholder="Rechercher partout..." autocomplete="off">
      <button type="button" class="fe-search-clear" id="feSearchClear" style="display:none"><i class="bi bi-x-lg"></i></button>
      <div class="fe-search-results" id="feSearchResults"></div>
    </div>
    <div class="fe-topbar-right">
      <a href="/newspocspace/notifications" data-link="notifications" class="fe-topbar-icon-btn" title="Notifications">
        <i class="bi bi-bell"></i>
        <span class="fe-topbar-notif" style="display:none"></span>
      </a>
      <?php if (!in_array('page_emails', $deniedPerms)): ?>
      <a href="/newspocspace/emails" data-link="emails" class="fe-topbar-icon-btn" title="Messagerie interne">
        <i class="bi bi-chat-dots"></i>
        <span class="fe-topbar-notif" id="msgBadge" style="display:none"></span>
      </a>
      <?php endif; ?>
      <a href="/newspocspace/annuaire" data-link="annuaire" class="fe-topbar-icon-btn" title="Annuaire téléphonique">
        <i class="bi bi-telephone"></i>
      </a>
      <button class="fe-topbar-icon-btn" id="fullscreenToggle" title="Plein écran">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
      <div class="fe-topbar-user" id="avatarToggleBtn" style="cursor:pointer;" title="Ouvrir le menu">
        <?php $topAvatarUrl = $user['photo'] ?? ''; $topInitials = h(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1)); ?>
        <?php if ($topAvatarUrl): ?>
          <img src="<?= h($topAvatarUrl) ?>" alt="" class="fe-topbar-user-avatar" id="topbarAvatar">
        <?php else: ?>
          <span class="fe-topbar-user-avatar" id="topbarAvatar"><?= $topInitials ?></span>
        <?php endif; ?>
        <span class="fe-topbar-user-name d-none d-sm-inline"><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></span>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main id="app-content">
    <!-- Pages loaded here by SPA router -->
  </main>
</div>
<?php endif; ?>

<?php if (!$user): ?>
<!-- Login: no sidebar, just app-content -->
<main id="app-content" class="no-nav">
  <!-- Login page loaded here by SPA router -->
</main>
<?php endif; ?>

<!-- TOAST -->
<div class="toast-container" id="toast" role="status" aria-live="polite"></div>

<!-- App Config -->
<script nonce="<?= $cspNonce ?>">
window.__SS__ = {
  csrfToken: '<?= $csrfToken ?>',
  user: <?= $user ? json_encode(['id' => $user['id'], 'prenom' => $user['prenom'], 'nom' => $user['nom'], 'email' => $user['email'], 'role' => $user['role'], 'taux' => $user['taux'], 'fonction_id' => $user['fonction_id'], 'type_employe' => $user['type_employe'] ?? 'interne'], JSON_HEX_TAG) : 'null' ?>,
  canChangement: <?= $canChangement ? 'true' : 'false' ?>,
  mustChangePassword: <?= !empty($_SESSION['ss_must_change_password']) ? 'true' : 'false' ?>,
  tempPasswordExpires: <?= !empty($_SESSION['ss_temp_password_expires']) ? "'" . h($_SESSION['ss_temp_password_expires']) . "'" : 'null' ?>,
  appUrl: '<?= APP_URL ?>',
  deniedPerms: <?= json_encode($deniedPerms, JSON_HEX_TAG) ?>,
  pageLabels: <?= json_encode(array_merge(['profile' => 'Mon profil', 'cuisine' => 'Cuisine', 'cuisine-home' => 'Tableau de bord cuisine', 'suggestion-new' => 'Nouvelle suggestion', 'suggestion-detail' => 'Suggestion'], ...array_values(array_map(fn($c) => array_combine(array_keys($c['items']), array_column($c['items'], 'label')), $sidebarNav))), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<!-- Bootstrap JS retiré : modales/dropdowns Bootstrap des pages non-migrées seront silencieusement no-op jusqu'à leur migration en Tailwind/JS natif -->
<script nonce="<?= $cspNonce ?>" src="assets/js/zerda-select.js?v=<?= $v ?>"></script>
<script nonce="<?= $cspNonce ?>" type="module" src="assets/js/app.js?v=<?= $v ?>"></script>
<script nonce="<?= $cspNonce ?>">
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/newspocspace/sw.js', { scope: '/newspocspace/' }).catch(() => {});
}
</script>
</body>
</html>
