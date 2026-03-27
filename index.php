<?php
/**
 * zerdaTime - SPA Shell (Frontend collaborateurs)
 * Sidebar layout — same pattern as admin panel
 */
require_once __DIR__ . '/init.php';

$csrfToken = $_SESSION['zt_csrf_token'] ?? '';
$user = $_SESSION['zt_user'] ?? null;
$v = APP_VERSION;

// CSP nonce
$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: blob:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; worker-src 'self' blob:;");

// Load EMS config for logo + name
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'zerdaTime';

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
    $_SESSION['zt_user']['type_employe'] = $user['type_employe'];
}

// Per-user denied permissions
$deniedPerms = $user ? ($_SESSION['zt_user']['denied_perms'] ?? []) : [];

$sidebarNav = [
    'main' => [
        'label' => 'Navigation',
        'items' => [
            'home'        => ['label' => 'Accueil',        'icon' => 'house'],
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
            'collegues'   => ['label' => 'Collègues',       'icon' => 'people'],
            'covoiturage' => ['label' => 'Covoiturage',     'icon' => 'car-front'],
            'emails'      => ['label' => 'Emails',          'icon' => 'envelope'],
            'messages'    => ['label' => 'Messages',        'icon' => 'chat-dots'],
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
            'votes'     => ['label' => 'Votes',           'icon' => 'hand-thumbs-up'],
            'pv'        => ['label' => 'Procès-Verbaux',  'icon' => 'file-earmark-text'],
            'sondages'  => ['label' => 'Sondages',        'icon' => 'clipboard2-check'],
            'documents' => ['label' => 'Documents',       'icon' => 'folder2-open'],
            'fiches-salaire' => ['label' => 'Fiches de salaire', 'icon' => 'receipt'],
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
<title>zerdaTime — Gestion des Plannings</title>
<meta name="description" content="Application de gestion des plannings - zerdaTime, Genève">
<meta name="theme-color" content="#1B2A4A">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏥</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/zerdatime.css?v=<?= $v ?>">
<?php if ($cssMode === 'tailwind'): ?>
<script nonce="<?= $cspNonce ?>" src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
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
    <a href="/zerdatime/profile" class="fe-sidebar-brand" data-link="profile" title="Voir mon profil">
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
      <a href="/zerdatime/<?= $key ?>"
         class="fe-sidebar-link<?= $isDisabled ? ' fe-sidebar-link--disabled' : '' ?>"
         data-link="<?= $key ?>"
         <?= $isDisabled ? 'data-disabled="1" aria-disabled="true"' : '' ?>
         title="<?= h($item['label']) ?><?= $isDisabled ? ' (non disponible — aucun collègue de même fonction)' : '' ?>">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span class="fe-nav-label"><?= h($item['label']) ?></span>
        <?php if ($key === 'emails'): ?>
        <span class="fe-sidebar-badge" id="emailBadgeSidebar" style="display:none"></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="fe-sidebar-footer">
    <?php if (in_array($user['role'], ['admin', 'direction', 'responsable'])): ?>
    <a href="/zerdatime/admin/" class="fe-sidebar-link fe-sidebar-admin-link" title="Administration">
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
      <a href="/zerdatime/home" data-link="home" class="fe-topbar-brand" title="Accueil">
        <img src="/zerdatime/logo.png" alt="zerdaTime" class="fe-topbar-brand-logo">
      </a>
      <h5 class="fe-topbar-title" id="feTopbarTitle">Accueil</h5>
    </div>
    <div class="fe-topbar-search" id="feTopbarSearch">
      <i class="bi bi-search fe-search-icon"></i>
      <input type="text" class="form-control" id="feSearchInput" placeholder="Rechercher (collègues, pages…)" autocomplete="off">
      <div class="fe-search-results" id="feSearchResults"></div>
    </div>
    <div class="fe-topbar-right">
      <a href="/zerdatime/notifications" data-link="notifications" class="fe-topbar-icon-btn" title="Notifications">
        <i class="bi bi-bell"></i>
        <span class="fe-topbar-notif" style="display:none"></span>
      </a>
      <a href="/zerdatime/emails" data-link="emails" class="fe-topbar-icon-btn" title="Emails">
        <i class="bi bi-envelope"></i>
        <span class="fe-topbar-notif" id="emailBadge" style="display:none"></span>
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
window.__ZT__ = {
  csrfToken: '<?= $csrfToken ?>',
  user: <?= $user ? json_encode(['id' => $user['id'], 'prenom' => $user['prenom'], 'nom' => $user['nom'], 'email' => $user['email'], 'role' => $user['role'], 'taux' => $user['taux'], 'fonction_id' => $user['fonction_id'], 'type_employe' => $user['type_employe'] ?? 'interne'], JSON_HEX_TAG) : 'null' ?>,
  canChangement: <?= $canChangement ? 'true' : 'false' ?>,
  mustChangePassword: <?= !empty($_SESSION['zt_must_change_password']) ? 'true' : 'false' ?>,
  tempPasswordExpires: <?= !empty($_SESSION['zt_temp_password_expires']) ? "'" . h($_SESSION['zt_temp_password_expires']) . "'" : 'null' ?>,
  appUrl: '<?= APP_URL ?>',
  deniedPerms: <?= json_encode($deniedPerms, JSON_HEX_TAG) ?>,
  pageLabels: <?= json_encode(array_merge(['profile' => 'Mon profil', 'cuisine' => 'Cuisine', 'cuisine-home' => 'Tableau de bord cuisine'], ...array_values(array_map(fn($c) => array_combine(array_keys($c['items']), array_column($c['items'], 'label')), $sidebarNav))), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script nonce="<?= $cspNonce ?>" src="assets/js/vendor/bootstrap.bundle.min.js"></script>
<script nonce="<?= $cspNonce ?>" type="module" src="assets/js/app.js?v=<?= $v ?>"></script>
</body>
</html>
