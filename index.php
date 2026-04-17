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
            'wiki'      => ['label' => 'Base de connaissances', 'icon' => 'book'],
        ],
    ],
];

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
<link rel="manifest" href="/spocspace/manifest.json">
<link rel="apple-touch-icon" href="/spocspace/assets/icons/icon-192x192.png">
<link rel="icon" href="/spocspace/assets/icons/icon-96x96.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Pacifico&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/ss-colors.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/spocspace.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/emoji-picker.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/annonces.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/css/pages-all.css?v=<?= $v ?>">
<?php if ($cssMode === 'tailwind'): ?>
<script nonce="<?= $cspNonce ?>" src="/spocspace/assets/js/vendor/tailwind-browser.min.js"></script>
<style type="text/tailwindcss">
@theme { --prefix: tw; }
</style>
<?php endif; ?>
</head>
<body>

<?php if ($user): ?>
<!-- BACKDROP (mobile) -->
<div class="fe-sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="fe-sidebar" id="feSidebar">
  <div class="fe-sidebar-header">
    <a href="/spocspace/profile" class="fe-sidebar-brand" data-link="profile" title="Voir mon profil">
      <?php
        $avatarUrl = $user['photo'] ?? '';
        $initials = h(mb_substr($user['prenom'] ?? '', 0, 1) . mb_substr($user['nom'] ?? '', 0, 1));
      ?>
      <?php if ($avatarUrl): ?>
        <img src="<?= h($avatarUrl) ?>" alt="" class="fe-brand-avatar">
      <?php else: ?>
        <span class="fe-brand-avatar-circle"><?= $initials ?></span>
      <?php endif; ?>
      <span class="fe-brand-text"><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></span>
    </a>
    <button class="fe-sidebar-toggle" id="sidebarToggleBtn" title="Réduire le menu">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>
  </div>
  <nav class="fe-sidebar-nav">
    <?php foreach ($sidebarNav as $catId => $cat): ?>
    <div class="fe-sidebar-cat" data-cat-toggle="<?= $catId ?>">
      <span class="fe-sidebar-cat-label"><?= h($cat['label']) ?></span>
      <i class="bi bi-chevron-down fe-sidebar-cat-chevron"></i>
    </div>
    <div class="fe-sidebar-cat-items" data-cat-body="<?= $catId ?>">
      <?php foreach ($cat['items'] as $key => $item):
            $isDisabled = ($key === 'changements' && !$canChangement); ?>
      <a href="/spocspace/<?= $key ?>"
         class="fe-sidebar-link<?= $isDisabled ? ' fe-sidebar-link--disabled' : '' ?>"
         data-link="<?= $key ?>"
         <?= $isDisabled ? 'data-disabled="1" aria-disabled="true"' : '' ?>
         title="<?= h($item['label']) ?><?= $isDisabled ? ' (non disponible — aucun collègue de même fonction)' : '' ?>">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span class="fe-nav-label"><?= h($item['label']) ?></span>
        <?php if ($key === 'emails'): ?>
        <span class="fe-sidebar-badge" id="msgBadgeSidebar" style="display:none"></span>
        <?php endif; ?>
        <?php if ($key === 'annonces'): ?>
        <span class="fe-sidebar-badge" id="annBadgeSidebar" style="display:none"></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="fe-sidebar-footer">
    <?php if (in_array($user['role'], ['admin', 'direction', 'responsable'])): ?>
    <a href="/spocspace/admin/" class="fe-sidebar-link fe-sidebar-admin-link" title="Administration">
      <i class="bi bi-shield-lock"></i>
      <span class="fe-nav-label">Administration</span>
    </a>
    <?php endif; ?>
    <button class="fe-sidebar-link fe-sidebar-logout-btn w-100 text-start bg-transparent border-0" id="logoutBtn" title="Déconnexion">
      <i class="bi bi-power"></i>
      <span class="fe-nav-label">Déconnexion</span>
    </button>
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
      <a href="/spocspace/home" data-link="home" class="fe-topbar-brand" title="Accueil">
        <img src="/spocspace/ss-logo.png" alt="SpocSpace" class="fe-topbar-brand-logo">
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
      <a href="/spocspace/notifications" data-link="notifications" class="fe-topbar-icon-btn" title="Notifications">
        <i class="bi bi-bell"></i>
        <span class="fe-topbar-notif" style="display:none"></span>
      </a>
      <?php if (!in_array('page_emails', $deniedPerms)): ?>
      <a href="/spocspace/emails" data-link="emails" class="fe-topbar-icon-btn" title="Messagerie interne">
        <i class="bi bi-chat-dots"></i>
        <span class="fe-topbar-notif" id="msgBadge" style="display:none"></span>
      </a>
      <?php endif; ?>
      <a href="/spocspace/annuaire" data-link="annuaire" class="fe-topbar-icon-btn" title="Annuaire téléphonique">
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
  pageLabels: <?= json_encode(array_merge(['profile' => 'Mon profil', 'cuisine' => 'Cuisine', 'cuisine-home' => 'Tableau de bord cuisine'], ...array_values(array_map(fn($c) => array_combine(array_keys($c['items']), array_column($c['items'], 'label')), $sidebarNav))), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script nonce="<?= $cspNonce ?>" src="assets/js/vendor/bootstrap.bundle.min.js"></script>
<script nonce="<?= $cspNonce ?>" src="assets/js/zerda-select.js?v=<?= $v ?>"></script>
<script nonce="<?= $cspNonce ?>" type="module" src="assets/js/app.js?v=<?= $v ?>"></script>
<script nonce="<?= $cspNonce ?>">
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/spocspace/sw.js', { scope: '/spocspace/' }).catch(() => {});
}
</script>
</body>
</html>
