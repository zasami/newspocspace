<?php
/**
 * SpocSpace Admin Panel - Server-rendered with retractable sidebar
 */
require_once __DIR__ . '/../init.php';

/**
 * Build a clean admin URL
 * @param string $page  Page name (empty or 'dashboard' → /spocspace/admin/)
 * @param string $id    Optional ID segment
 * @param array  $extra Optional query parameters
 */
function admin_url(string $page = '', string $id = '', array $extra = []): string {
    $base = '/spocspace/admin';
    if (!$page || $page === 'dashboard') {
        $url = $base . '/';
    } else {
        $url = $base . '/' . rawurlencode($page);
        if ($id) $url .= '/' . rawurlencode($id);
    }
    if ($extra) $url .= '?' . http_build_query($extra);
    return $url;
}

// Auth check
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    header('Location: /spocspace/login');
    exit;
}

$admin = $_SESSION['ss_user'];
$csrfToken = $_SESSION['ss_csrf_token'] ?? '';

// CSP nonce for inline scripts
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);
function nonce(): string { return ' nonce="' . CSP_NONCE . '"'; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self'; connect-src 'self' blob: http://localhost:5876 http://localhost:5877 http://localhost:5878 http://localhost:11434 https://api.deepgram.com wss://api.deepgram.com; worker-src 'self' blob:; media-src 'self' blob:;");

// Load EMS logo + name for sidebar
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'SpocSpace';


// Redirect old-style URLs (?page=… or index.php?page=…) → clean URLs
if (isset($_GET['page']) && preg_match('/\?(.*&)?page=/', $_SERVER['REQUEST_URI'])) {
    $cleanUrl = admin_url($_GET['page'], $_GET['id'] ?? '');
    $qs = $_GET;
    unset($qs['page'], $qs['id']);
    if ($qs) $cleanUrl .= (str_contains($cleanUrl, '?') ? '&' : '?') . http_build_query($qs);
    header('Location: ' . $cleanUrl, true, 301);
    exit;
}
if (str_contains($_SERVER['REQUEST_URI'], 'index.php?action=logout')) {
    header('Location: ' . admin_url() . 'logout', true, 301);
    exit;
}

// Global compose contacts (for compose panel on all pages)
$globalComposeContacts = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, f.nom AS fonction_nom
     FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.is_active = 1 ORDER BY u.nom, u.prenom"
);

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /spocspace/login');
    exit;
}

// Current page
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'etablissement', 'users', 'user-edit', 'user-detail', 'planning', 'modules', 'horaires', 'desirs', 'absences', 'vacances', 'changements', 'stats', 'besoins', 'messages', 'alertes', 'apparence', 'config-ia', 'repartition', 'affichage-planning', 'pv', 'pv-detail', 'pv-record', 'sondages', 'sondage-edit', 'documents', 'fiches-salaire', 'import-export', 'todos', 'notes', 'roadmap', 'residents', 'marquage', 'famille', 'cuisine', 'reservations', 'email-externe', 'email-config', 'contacts', 'recrutement', 'rh-offres', 'rh-candidatures', 'rh-formations', 'rh-formations-stats', 'rh-formations-cartographie', 'rh-formations-fegems', 'rh-formations-sessions', 'rh-formations-profil', 'rh-formations-parametres', 'rh-formations-pluriannuel', 'rh-formations-dashboard', 'rh-formations-dashboard-secteur', 'rh-collab-competences', 'rh-entretiens', 'rh-entretiens-fiche', 'rh-stagiaires', 'rh-stagiaire-detail', 'connexions', 'agenda', 'mur', 'wiki', 'annonces', 'wiki-analytics', 'securite', 'annuaire', 'sauvegardes', 'salles', 'evenements', 'email-templates', 'fiches-amelioration', 'suggestions'];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

$topbarPlaceholders = [
    'users'     => 'Rechercher un collaborateur...',
    'desirs'    => 'Rechercher dans les désirs...',
    'absences'  => 'Rechercher dans les absences...',
    'vacances'  => 'Rechercher dans les vacances...',
    'messages'  => 'Rechercher un message...',
    'sondages'  => 'Rechercher un sondage...',
    'documents' => 'Rechercher un document...',
    'planning'  => 'Rechercher dans le planning...',
    'todos'     => 'Rechercher une tâche...',
    'notes'     => 'Rechercher une note...',
    'pv'        => 'Rechercher un procès-verbal...',
    'cuisine'   => 'Rechercher un menu...',
    'reservations' => 'Rechercher une réservation...',
    'residents'    => 'Rechercher un résident...',
    'marquage'     => 'Rechercher un marquage...',
    'famille'      => 'Rechercher un résident...',
    'email-externe' => 'Rechercher un email...',
    'contacts'      => 'Rechercher un contact...',
    'fiches-amelioration' => 'Rechercher dans les fiches d\'amélioration...',
];
$topbarPlaceholder = $topbarPlaceholders[$page] ?? 'Rechercher un collaborateur...';

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

// Dispatch theme-care : si l'utilisateur est en theme-care ET qu'un template
// alternatif {page}.care.php existe, on l'utilise à la place du standard.
$_themePrefDispatch = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$admin['id']]) ?: 'default';
if ($_themePrefDispatch === 'care') {
    $careFile = __DIR__ . '/pages/' . $page . '.care.php';
    if (file_exists($careFile)) {
        $pageFile = $careFile;
    }
}

$pageLabels = [
    'dashboard'     => 'Tableau de bord',
    'etablissement' => 'Établissement',
    'users'         => 'Collaborateurs',
    'user-edit'     => 'Modifier collaborateur',
    'user-detail'   => 'Fiche collaborateur',
    'planning'      => 'Planning',
    'modules'       => 'Modules & Étages',
    'horaires'      => 'Types d\'horaires',
    'desirs'        => 'Désirs',
    'absences'      => 'Absences',
    'vacances'      => 'Vacances',
    'besoins'       => 'Besoins couverture',
    'stats'         => 'Statistiques',
    'messages'      => 'Messagerie',
    'alertes'       => 'Alertes & Annonces',
    'config-ia'     => 'Config IA Planning',
    'repartition'   => 'Répartition',
    'affichage-planning' => 'Affichage Planning',
    'pv'            => 'Procès-Verbaux',
    'pv-detail'     => 'Détail PV',
    'pv-record'     => 'Enregistrement PV',
    'sondages'      => 'Sondages',
    'sondage-edit'  => 'Éditeur de sondage',
    'documents'     => 'Documents',
    'fiches-salaire' => 'Fiches de salaire',
    'import-export' => 'Import / Export',
    'changements'   => 'Changements d\'horaire',
    'roadmap'       => 'Roadmap',
    'agenda'        => 'Agenda',
    'connexions'    => 'Connexions',
    'residents'     => 'Résidents',
    'marquage'      => 'Marquage Lingerie',
    'famille'       => 'Espace Famille',
    'cuisine'       => 'Cuisine — Menus',
    'reservations'  => 'Réservations repas',
    'email-externe' => 'Email',
    'email-config'  => 'Configuration Email',
    'contacts'      => 'Contacts',
    'annuaire'      => 'Annuaire téléphonique',
    'recrutement'      => 'Recrutement',
    'rh-offres'        => 'Offres d\'emploi',
    'rh-candidatures'  => 'Candidatures',
    'rh-formations'    => 'Liste des formations',
    'rh-formations-stats' => 'Statistiques formations',
    'rh-formations-dashboard' => 'Tableau de bord formation',
    'rh-formations-dashboard-secteur' => 'Détail secteur formation',
    'rh-formations-pluriannuel' => 'Plan formation pluriannuel',
    'rh-formations-cartographie' => 'Cartographie d\'équipe',
    'rh-formations-fegems'       => 'Inscriptions FEGEMS',
    'rh-formations-sessions'     => 'Sessions & catalogue',
    'rh-formations-profil'       => 'Profil d\'équipe attendu',
    'rh-formations-parametres'   => 'Paramètres formation',
    'rh-collab-competences'      => 'Fiche compétences',
    'rh-entretiens'              => 'Entretiens annuels',
    'rh-entretiens-fiche'        => 'Fiche entretien',
    'rh-stagiaires'    => 'Stagiaires & stages',
    'rh-stagiaire-detail' => 'Profil stagiaire',
    'mur'              => 'Mur social',
    'wiki'             => 'Base de connaissances',
    'annonces'         => 'Annonces officielles',
    'wiki-analytics'   => 'Analytics Wiki',
    'securite'         => 'Sécurité & Antivirus',
    'email-templates'  => 'Templates d\'email',
    'fiches-amelioration' => 'Amélioration continue',
    'suggestions'      => 'Suggestions & Demandes',
];

// ── AJAX page loading (SPA mode) ──
if (!empty($_GET['_spa'])) {
    ob_start();
    require $pageFile;
    $html = ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html' => $html,
        'page' => $page,
        'title' => ($pageLabels[$page] ?? 'Admin') . ' — SpocSpace',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sidebar with categories
$sidebarCategories = [
    'general' => [
        'label' => 'Général',
        'items' => [
            'dashboard'     => ['label' => 'Tableau de bord',  'icon' => 'speedometer2'],
        ],
    ],
    'planning' => [
        'label' => 'Planning',
        'items' => [
            'planning' => ['label' => 'Planning',            'icon' => 'calendar3'],
            'desirs'   => ['label' => 'Désirs',              'icon' => 'star'],
            'absences' => ['label' => 'Absences',            'icon' => 'calendar-x'],
            'vacances' => ['label' => 'Vacances',            'icon' => 'sun'],
            'changements' => ['label' => 'Changements',        'icon' => 'arrow-left-right'],
            'besoins'    => ['label' => 'Besoins couverture',  'icon' => 'grid-3x3'],
            'repartition' => ['label' => 'Répartition',           'icon' => 'grid-3x3-gap'],
        ],
    ],
    'config' => [
        'label' => 'Configuration',
        'items' => [
            'users'    => ['label' => 'Collaborateurs',      'icon' => 'people'],
            'modules'  => ['label' => 'Modules & Unités',    'icon' => 'building'],
            'horaires' => ['label' => 'Types d\'horaires',   'icon' => 'clock'],
        ],
    ],
    'outils' => [
        'label' => 'Outils',
        'items' => [
            'agenda'   => ['label' => 'Agenda',                 'icon' => 'calendar-week'],
            'salles'   => ['label' => 'Réservation salles',     'icon' => 'door-open'],
            'todos'    => ['label' => 'Tâches',                'icon' => 'check2-square'],
            'notes'    => ['label' => 'Notes',                 'icon' => 'journal-text'],
            'pv'       => ['label' => 'Procès-Verbaux',       'icon' => 'file-earmark-text'],
            'sondages' => ['label' => 'Sondages',             'icon' => 'clipboard2-check'],
            'mur'      => ['label' => 'Mur social',            'icon' => 'chat-square-heart'],
            'wiki'     => ['label' => 'Base de connaissances', 'icon' => 'book'],
            'wiki-analytics' => ['label' => 'Analytics Wiki',  'icon' => 'graph-up'],
            'annonces' => ['label' => 'Annonces officielles',  'icon' => 'megaphone'],
            'evenements' => ['label' => 'Événements',            'icon' => 'calendar-event'],
        ],
    ],
    'rh' => [
        'label' => 'Recrutement & RH',
        'items' => [
            'rh-offres'       => ['label' => 'Offres d\'emploi',     'icon' => 'briefcase'],
            'rh-candidatures' => ['label' => 'Candidatures',         'icon' => 'person-lines-fill'],
            'rh-stagiaires'   => ['label' => 'Stagiaires',           'icon' => 'person-badge'],
        ],
    ],
    'formations' => [
        'label' => 'Formations',
        'items' => [
            'rh-formations-dashboard'    => ['label' => 'Tableau de bord',          'icon' => 'speedometer2'],
            'rh-formations'              => ['label' => 'Liste des formations',     'icon' => 'list-ul'],
            'rh-formations-cartographie' => ['label' => 'Cartographie d\'équipe',   'icon' => 'diagram-3'],
            'rh-formations-fegems'       => ['label' => 'Inscriptions FEGEMS',      'icon' => 'cloud-arrow-up'],
            'rh-formations-sessions'     => ['label' => 'Sessions & catalogue',     'icon' => 'calendar3'],
            'rh-formations-profil'       => ['label' => 'Profil d\'équipe attendu', 'icon' => 'bullseye'],
            'rh-formations-stats'        => ['label' => 'Statistiques',             'icon' => 'graph-up'],
            'rh-formations-pluriannuel'  => ['label' => 'Plan pluriannuel',         'icon' => 'graph-up-arrow'],
            'rh-formations-parametres'   => ['label' => 'Paramètres',               'icon' => 'gear'],
        ],
    ],
    'entretiens' => [
        'label' => 'Entretiens',
        'items' => [
            'rh-entretiens'        => ['label' => 'Entretiens annuels', 'icon' => 'chat-square-text'],
        ],
    ],
    'autres' => [
        'label' => 'Autres',
        'items' => [
            'documents' => ['label' => 'Documents',             'icon' => 'folder2'],
            'fiches-salaire' => ['label' => 'Fiches de salaire', 'icon' => 'receipt'],
            'messages' => ['label' => 'Messagerie',            'icon' => 'chat-dots'],
            'email-externe' => ['label' => 'Email',              'icon' => 'envelope'],
            'contacts'      => ['label' => 'Contacts',           'icon' => 'person-rolodex'],
            'annuaire'      => ['label' => 'Annuaire',           'icon' => 'telephone'],
            'alertes'  => ['label' => 'Alertes',               'icon' => 'megaphone'],
            'fiches-amelioration' => ['label' => 'Amélioration continue', 'icon' => 'lightbulb'],
            'stats'    => ['label' => 'Statistiques',        'icon' => 'graph-up'],
            'import-export' => ['label' => 'Import / Export', 'icon' => 'arrow-down-up'],
            'roadmap'      => ['label' => 'Roadmap',          'icon' => 'rocket-takeoff'],
            'connexions'   => ['label' => 'Connexions',        'icon' => 'person-check'],
        ],
    ],
    'parametres' => [
        'label' => 'Paramètres',
        'items' => [
            'etablissement'      => ['label' => 'Établissement',       'icon' => 'hospital'],
            'affichage-planning' => ['label' => 'Affichage planning',  'icon' => 'sliders'],
            'apparence'          => ['label' => 'Apparence',           'icon' => 'palette'],
            'config-ia'          => ['label' => 'Config IA',           'icon' => 'cpu'],
            'email-config'       => ['label' => 'Config Email',        'icon' => 'envelope-at'],
            'email-templates'    => ['label' => "Templates d'email",    'icon' => 'envelope-paper'],
            'securite'           => ['label' => 'Sécurité',            'icon' => 'shield-check'],
            'sauvegardes'        => ['label' => 'Sauvegardes',          'icon' => 'database-down'],
        ],
    ],
];

// Module Suggestions : visible uniquement direction/admin ET si flag activé
$sugFlag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($sugFlag === '1' && in_array($admin['role'] ?? '', ['admin', 'direction'])) {
    $sidebarCategories['outils']['items']['suggestions'] = ['label' => 'Suggestions', 'icon' => 'lightbulb'];
}

$activeSection = match($page) {
    'user-edit', 'user-detail' => 'users',
    default => $page,
};

// Préférence de thème de l'utilisateur (default | sombre | care)
$themePref = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$admin['id']]) ?: 'default';
$themeBodyClass = 'theme-' . preg_replace('/[^a-z]/', '', $themePref);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1A1A1A">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/spocspace/manifest.json">
<link rel="apple-touch-icon" href="/spocspace/assets/icons/icon-192x192.png">
<base href="/spocspace/admin/">
<title>Admin — SpocSpace</title>
<link href="/spocspace/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
<link href="/spocspace/admin/assets/css/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/admin/assets/css/admin.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/spocspace/admin/assets/css/competences.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/spocspace/admin/assets/css/themes.css?v=<?= APP_VERSION ?>">
<?php if ($themePref === 'care'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php endif ?>
</head>
<body class="<?= h($themeBodyClass) ?>">

<!-- BACKDROP (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-header">
    <a href="<?= admin_url() ?>" class="sidebar-brand-link">
      <img src="/spocspace/ss-logo.png" alt="" class="brand-logo">
      <span class="brand-text"><?= h($emsNom) ?></span>
    </a>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Réduire le menu">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($sidebarCategories as $catId => $cat): ?>
    <div class="sidebar-cat" data-cat-toggle="<?= $catId ?>">
      <span class="sidebar-cat-label"><?= h($cat['label']) ?></span>
      <i class="bi bi-chevron-down sidebar-cat-chevron"></i>
    </div>
    <div class="sidebar-cat-items" data-cat-body="<?= $catId ?>">
      <?php foreach ($cat['items'] as $key => $item): ?>
      <a href="<?= admin_url($key) ?>" class="sidebar-link <?= $activeSection === $key ? 'active' : '' ?>" title="<?= h($item['label']) ?>" <?= in_array($key, ['messages', 'email-externe']) ? 'data-sidebar-badge="' . $key . '"' : '' ?>>
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span class="nav-label"><?= h($item['label']) ?></span>
        <?php if ($key === 'messages'): ?><span class="sidebar-badge" id="sidebarMsgBadge" style="display:none"></span><?php endif; ?>
        <?php if ($key === 'email-externe'): ?><span class="sidebar-badge" id="sidebarEmailBadge" style="display:none"></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/spoccare/" class="sidebar-link" title="SpocCare — Résidents & Soins">
      <i class="bi bi-heart-pulse" style="color:#2d4a43"></i>
      <span class="nav-label">SpocCare</span>
    </a>
    <a href="/spocspace/" class="sidebar-link" target="_blank" title="Portail collaborateur">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="nav-label">Portail collaborateur</span>
    </a>
    <div class="sidebar-bottom-row" style="padding:6px 16px;font-size:0.7rem;color:var(--ss-text-muted,#999);opacity:.7;display:flex;align-items:center;justify-content:space-between">
      <span class="nav-label">SpocSpace v<?= APP_SEMVER ?></span>
      <button class="sidebar-shortcuts-btn" id="sidebarShortcutsBtn" title="Raccourcis clavier"><i class="bi bi-keyboard"></i></button>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="admin-main" id="adminMain">
  <!-- TOP BAR -->
  <header class="admin-topbar">
    <button class="topbar-hamburger" id="mobileToggle" title="Menu">
      <i class="bi bi-list"></i>
    </button>
    <h5 class="mb-0 topbar-title"><?= h($pageLabels[$page] ?? 'Admin') ?></h5>
    <div class="topbar-search ms-auto me-3" id="topbarSearch">
      <i class="bi bi-search search-icon"></i>
      <input type="text" class="form-control form-control-sm" id="topbarSearchInput" placeholder="Rechercher partout... (@ pour la page actuelle)" autocomplete="off">
      <button type="button" class="admin-search-clear" id="adminSearchClear" style="display:none"><i class="bi bi-x-lg"></i></button>
      <div class="topbar-search-results" id="topbarSearchResults"></div>
    </div>
    <div class="topbar-right">
      <a href="<?= admin_url('messages') ?>" class="topbar-icon-btn" id="topbarMsgNotif" title="Messagerie">
        <i class="bi bi-chat-dots"></i>
        <span class="topbar-notif-badge" id="topbarMsgBadge" style="display:none"></span>
      </a>
      <a href="<?= admin_url('email-externe') ?>" class="topbar-icon-btn" id="topbarEmailNotif" title="Email">
        <i class="bi bi-envelope"></i>
        <span class="topbar-notif-badge" id="topbarEmailBadge" style="display:none"></span>
      </a>
      <a href="<?= admin_url('contacts') ?>" class="topbar-icon-btn" id="topbarContactsBtn" title="Contacts">
        <i class="bi bi-person-rolodex"></i>
      </a>
      <a href="<?= admin_url('annuaire') ?>" class="topbar-icon-btn" id="topbarAnnuaireBtn" title="Annuaire téléphonique">
        <i class="bi bi-telephone"></i>
      </a>
      <button class="topbar-icon-btn" id="ztInstallBtn" title="Installer l'application" style="display:none">
        <i class="bi bi-download"></i>
      </button>
      <button class="topbar-icon-btn" id="immersiveToggle" title="Plein écran">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
<?php
  $fonctionCode = '';
  if (!empty($admin['fonction_id'])) {
      $fonctionCode = Db::getOne("SELECT code FROM fonctions WHERE id = ?", [$admin['fonction_id']]) ?: '';
  }
  $roleLabel = $fonctionCode ?: ($admin['role'] === 'responsable' ? 'RUV' : ucfirst($admin['role'] ?? 'Admin'));
?>
      <div class="topbar-user d-none d-sm-flex">
        <span class="topbar-user-name"><?= h(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')) ?></span>
        <span class="topbar-user-role"><?= h($roleLabel) ?></span>
      </div>
      <a href="<?= admin_url() ?>logout" class="topbar-icon-btn topbar-logout" title="Déconnexion">
        <i class="bi bi-power"></i>
      </a>
    </div>
  </header>

<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/vendor/bootstrap.bundle.min.js"></script>
<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/url-manager.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/helpers.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/zerda-select.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>">
window.__SS_ADMIN__ = {
  csrfToken: '<?= $csrfToken ?>',
  adminId: '<?= h($admin['id']) ?>',
  adminName: '<?= h(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')) ?>'
};
// Bridge __SS__ for shared modules (call-ui uses it)
window.__SS__ = window.__SS__ || {
  user: { id: '<?= h($admin['id']) ?>', prenom: '<?= h($admin['prenom'] ?? '') ?>', nom: '<?= h($admin['nom'] ?? '') ?>', email: '<?= h($admin['email'] ?? '') ?>', role: '<?= h($admin['role'] ?? 'admin') ?>' },
  csrfToken: '<?= $csrfToken ?>',
};
</script>
<!-- WebRTC call engine for admin (receive + make calls) -->
<script nonce="<?= $cspNonce ?>" type="module">
  import * as callUi from '/spocspace/assets/js/call-ui.js';
  callUi.initIncomingPoll();
  window.ssStartCall = callUi.startCall;
</script>

  <!-- PAGE CONTENT -->
  <div class="admin-content" id="adminContent">
    <?php
    // Catch fatal errors so that global compose panel + shortcuts still render
    ob_start();
    $pageError = null;
    try {
        require $pageFile;
    } catch (\Throwable $e) {
        $pageError = $e->getMessage();
        error_log('[Admin page error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    $pageOutput = ob_get_clean();
    if ($pageError) {
        echo '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle"></i> Erreur : ' . h($pageError) . '</div>';
    } else {
        echo $pageOutput;
    }
    ?>
  </div>
</div>

<script nonce="<?= $cspNonce ?>">
// Extend with extra properties
window.__SS_ADMIN__.mustChangePassword = <?= !empty($_SESSION['ss_must_change_password']) ? 'true' : 'false' ?>;
window.__SS_ADMIN__.tempPasswordExpires = <?= !empty($_SESSION['ss_temp_password_expires']) ? "'" . h($_SESSION['ss_temp_password_expires']) . "'" : 'null' ?>;

// Temp password banner (admin)
if (window.__SS_ADMIN__.mustChangePassword && window.__SS_ADMIN__.tempPasswordExpires) {
    const exp = new Date(window.__SS_ADMIN__.tempPasswordExpires.replace(' ', 'T'));
    const banner = document.createElement('div');
    banner.id = 'tempPwdBanner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#9B2C2C;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:12px;font-size:.88rem;box-shadow:0 2px 8px rgba(0,0,0,.2);';
    banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><span>Vous utilisez un mot de passe temporaire. Changez-le avant <strong id="tempPwdCountdown"></strong></span><a href="/spocspace/profile" style="color:#fff;background:rgba(255,255,255,.2);padding:4px 14px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.82rem;white-space:nowrap;"><i class="bi bi-key"></i> Modifier</a>';
    document.body.prepend(banner);
    document.body.style.paddingTop = banner.offsetHeight + 'px';
    function updateCountdown() {
        const diff = exp - new Date();
        const el = document.getElementById('tempPwdCountdown');
        if (!el) return;
        if (diff <= 0) { el.textContent = '(expiré)'; return; }
        el.textContent = Math.floor(diff / 3600000) + 'h ' + String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0') + 'min';
    }
    updateCountdown();
    setInterval(updateCountdown, 30000);
}

// ── Unread counts polling (messages + email externe) ──
(function() {
    function setBadge(el, count) {
        if (!el) return;
        if (count > 0) {
            el.textContent = count > 99 ? '99+' : count;
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    }

    async function fetchUnreadCounts() {
        try {
            const res = await adminApiPost('admin_get_unread_counts', {});
            if (!res.success) return;
            // Topbar badges
            setBadge(document.getElementById('topbarMsgBadge'), res.unread_messages);
            setBadge(document.getElementById('topbarEmailBadge'), res.unread_email);
            // Sidebar badges
            setBadge(document.getElementById('sidebarMsgBadge'), res.unread_messages);
            setBadge(document.getElementById('sidebarEmailBadge'), res.unread_email);
        } catch (e) {}
    }

    // Initial fetch + poll every 60s
    fetchUnreadCounts();
    setInterval(fetchUnreadCounts, 60000);

    // Expose for manual refresh
    window.__ztRefreshUnread = fetchUnreadCounts;
})();

</script>

<!-- Fullscreen + SPA router (separate script) -->
<script nonce="<?= $cspNonce ?>">
(function() {
    // ── Fullscreen (browser API) ──
    var fsBtn = document.getElementById('immersiveToggle');

    function updateFsIcon() {
        var icon = fsBtn && fsBtn.querySelector('i');
        if (icon) icon.className = document.fullscreenElement ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    }

    if (fsBtn) fsBtn.addEventListener('click', function() {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function(){});
            localStorage.removeItem('ss_fullscreen');
        } else {
            document.documentElement.requestFullscreen().catch(function(){});
            localStorage.setItem('ss_fullscreen', '1');
        }
    });

    document.addEventListener('fullscreenchange', updateFsIcon);
    updateFsIcon();

    // ── SPA Router — navigation AJAX, fullscreen persists ──
    var contentEl = document.getElementById('adminContent');
    var titleEl = document.querySelector('.topbar-title');
    var BASE = '/spocspace/admin/';
    var spaActive = false; // only SPA-navigate after first click (initial page loads normally)

    function pageFromUrl(url) {
        var u = new URL(url, location.origin);
        var path = u.pathname.replace(/\/$/, '');
        var parts = path.replace(BASE.replace(/\/$/, ''), '').replace(/^\//, '').split('/');
        return parts[0] || 'dashboard';
    }

    function navigateTo(url, pushState) {
        var page = pageFromUrl(url);
        var sep = url.includes('?') ? '&' : '?';
        var ajaxUrl = url + sep + '_spa=1';

        // Update sidebar active
        document.querySelectorAll('.sidebar-link').forEach(function(l) {
            l.classList.toggle('active', pageFromUrl(l.getAttribute('href') || '') === page);
        });

        fetch(ajaxUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // Push state BEFORE running scripts so AdminURL.currentId() reflects new URL
                if (pushState !== false) history.pushState({ page: page }, '', url);

                // Inject HTML (scripts in innerHTML don't execute — we'll activate them)
                contentEl.innerHTML = data.html;

                // Activate scripts: replace each <script> with a fresh createElement copy
                // With 'strict-dynamic' CSP, scripts created by a trusted (nonce'd) script are allowed
                var scripts = Array.from(contentEl.querySelectorAll('script'));
                var chain = Promise.resolve();

                scripts.forEach(function(oldScript) {
                    chain = chain.then(function() {
                        return new Promise(function(resolve) {
                            var s = document.createElement('script');
                            // Copy non-nonce attributes (type, etc.)
                            Array.from(oldScript.attributes).forEach(function(a) {
                                if (a.name !== 'nonce') s.setAttribute(a.name, a.value);
                            });
                            if (oldScript.src) {
                                s.src = oldScript.src;
                                s.onload = resolve;
                                s.onerror = resolve;
                            } else {
                                s.textContent = oldScript.textContent;
                            }
                            oldScript.replaceWith(s);
                            if (!oldScript.src) resolve(); // inline scripts execute synchronously
                        });
                    });
                });

                chain.then(function() {
                    // Fire DOMContentLoaded-like event for pages that listen to it
                    document.dispatchEvent(new Event('DOMContentLoaded'));

                    // Also try calling the page init function
                    var initName = 'init' + page.replace(/(^|-)([a-z])/g, function(_, _2, c) { return c.toUpperCase(); }) + 'Page';
                    if (typeof window[initName] === 'function') {
                        try { window[initName](); } catch(e) { console.warn('Page init error:', e); }
                    }
                });

                document.title = data.title;
                if (titleEl) titleEl.textContent = data.title.split(' — ')[0];
                window.scrollTo(0, 0);
            })
            .catch(function(err) {
                console.error('SPA nav error:', err);
                location.href = url; // fallback
            });
    }

    // Intercept internal admin links
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || !href.startsWith(BASE) || href.includes('logout') || link.target === '_blank') return;
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        // Only SPA-navigate when in fullscreen (otherwise normal navigation is fine)
        if (!document.fullscreenElement) return;
        e.preventDefault();
        navigateTo(href);
    });

    window.addEventListener('popstate', function() {
        if (document.fullscreenElement) navigateTo(location.href, false);
        else location.reload();
    });

    // Expose for shortcuts
    window.__ztNavigateTo = navigateTo;

})();
</script>
<!-- ═══ MODAL: Session expirée ═══ -->
<div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:16px;border:none;overflow:hidden">
      <div class="modal-body text-center py-4 px-4">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(210,180,145,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.5rem;color:#d4a017">
          <i class="bi bi-clock-history"></i>
        </div>
        <h6 class="fw-bold mb-2">Session expirée</h6>
        <p class="text-muted small mb-3">Votre session a expiré par inactivité. Veuillez vous reconnecter pour continuer.</p>
        <div class="d-flex gap-2 justify-content-center">
          <a href="/spocspace/admin/logout" class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</a>
          <a href="/spocspace/login" class="btn btn-sm btn-primary" style="border-radius:8px;background:var(--cl-accent,#bcd2cb);border-color:var(--cl-accent,#bcd2cb);color:#2d4a43"><i class="bi bi-box-arrow-in-right me-1"></i>Connexion</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script nonce="<?= $cspNonce ?>">
(function() {
    let shown = false;
    window.__ssShowSessionExpired = function() {
        if (shown) return;
        shown = true;
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiredModal'));
        modal.show();
    };
    // Heartbeat: check session every 5 minutes
    setInterval(async () => {
        if (shown) return;
        try {
            const res = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'admin_get_session_ping' })
            });
            if (res.status === 401 || res.status === 403) {
                window.__ssShowSessionExpired();
            }
        } catch(e) {}
    }, 300000);
})();
</script>

<!-- ═══ MODAL: Confirmation globale ═══ -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" id="confirmModalHeader">
        <div class="d-flex align-items-center gap-3">
          <div class="confirm-modal-icon" id="confirmModalIcon"><i class="bi bi-question-circle"></i></div>
          <div>
            <h5 class="modal-title mb-0" id="confirmModalTitle">Confirmation</h5>
            <small class="text-muted" id="confirmModalSubtitle"></small>
          </div>
        </div>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="confirmModalBody">
        <p id="confirmModalText" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" id="confirmModalCancel" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" id="confirmModalOk">Confirmer</button>
      </div>
    </div>
  </div>
</div>

<!-- Prompt Modal -->
<div class="modal fade" id="promptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="confirm-modal-icon icon-info" id="promptModalIcon"><i class="bi bi-pencil"></i></div>
          <h6 class="modal-title mb-0" id="promptModalTitle">Saisie</h6>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" data-bs-dismiss="modal" style="width:32px;height:32px;border-radius:50%;border:1px solid #e5e7eb;"><i class="bi bi-x-lg" style="font-size:.85rem;"></i></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-bold" id="promptModalLabel"></label>
        <input type="text" class="form-control" id="promptModalInput">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" id="promptModalOk" style="background:var(--cl-accent,#191918);color:#fff;font-weight:600;border-radius:8px;">Valider</button>
      </div>
    </div>
  </div>
</div>

<style>
.confirm-modal-icon {
  width: 48px; height: 48px; border-radius: var(--cl-radius-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; flex-shrink: 0;
}
.confirm-modal-icon.icon-danger  { background: rgba(220,38,38,0.08); color: var(--ss-red); }
.confirm-modal-icon.icon-warning { background: rgba(234,139,45,0.08); color: var(--ss-orange); }
.confirm-modal-icon.icon-success { background: rgba(22,163,74,0.08); color: var(--ss-green); }
.confirm-modal-icon.icon-info    { background: var(--cl-accent-bg); color: var(--cl-accent); }
.confirm-modal-icon.icon-primary { background: var(--cl-accent-bg); color: var(--cl-accent); }
#confirmModalBody { font-size: 0.9rem; color: var(--cl-text-secondary); }

/* Confirm modal overlay */
#confirmModal .modal-backdrop, .modal-backdrop { background: rgba(0,0,0,0.35) !important; }

/* modal-header flex layout is now in admin.css globally */

/* Confirm & Prompt modals always on top */
#confirmModal, #promptModal { z-index: 1070 !important; }

/* Dim overlay on modals behind confirm/prompt */
.ss-dim-overlay {
  position: absolute; inset: 0; z-index: 10;
  background: rgba(0,0,0,.45);
  border-radius: inherit;
  pointer-events: none;
  animation: ztDimIn .2s ease;
}
@keyframes ztDimIn { from { opacity: 0; } to { opacity: 1; } }

/* Footer aligned right */
#confirmModal .modal-footer {
  display: flex; justify-content: flex-end; gap: 0.5rem;
  border-top: 1px solid var(--cl-border, #E8E5E0);
  padding: 0.75rem 1.25rem;
}

/* ── Confirm button: theme-aware ── */
.ss-confirm-btn {
  font-weight: 600; border-radius: 8px; border: none; color: #fff;
  transition: filter .15s, transform .1s;
}
.ss-confirm-btn:hover,
.ss-confirm-btn:focus,
.ss-confirm-btn:active {
  color: #fff !important;
  background-color: inherit;
  filter: brightness(1.08);
}
.ss-confirm-btn:active { transform: scale(.97); }
.ss-confirm-danger,
.ss-confirm-danger:hover,
.ss-confirm-danger:focus  { background-color: var(--ss-red, #DC2626) !important; color: #fff !important; }
.ss-confirm-warning,
.ss-confirm-warning:hover,
.ss-confirm-warning:focus { background-color: var(--ss-orange, #EA8B2D) !important; color: #fff !important; }
.ss-confirm-success,
.ss-confirm-success:hover,
.ss-confirm-success:focus { background-color: var(--ss-green, #16A34A) !important; color: #fff !important; }
.ss-confirm-info,
.ss-confirm-info:hover,
.ss-confirm-info:focus    { background-color: var(--cl-accent, #191918) !important; color: #fff !important; }
.ss-confirm-primary,
.ss-confirm-primary:hover,
.ss-confirm-primary:focus { background-color: var(--cl-accent, #191918) !important; color: #fff !important; }
</style>

<script nonce="<?= $cspNonce ?>">
/**
 * adminConfirm — remplace confirm() natif par un joli modal Bootstrap
 * @param {Object} opts
 *   title     : string — titre principal
 *   text      : string — corps du message (HTML autorisé)
 *   subtitle  : string — sous-titre optionnel
 *   icon      : string — classe Bootstrap Icons (ex: 'bi-trash')
 *   type      : 'danger'|'warning'|'success'|'info'|'primary' — couleur icône + bouton
 *   okText    : string — texte du bouton confirmer
 *   cancelText: string — texte du bouton annuler
 * @returns {Promise<boolean>}
 */
function adminConfirm(opts = {}) {
  return new Promise(resolve => {
    const type = opts.type || 'warning';
    const iconEl = document.getElementById('confirmModalIcon');
    const titleEl = document.getElementById('confirmModalTitle');
    const subtitleEl = document.getElementById('confirmModalSubtitle');
    const textEl = document.getElementById('confirmModalText');
    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');

    // Icon
    iconEl.className = 'confirm-modal-icon icon-' + type;
    iconEl.innerHTML = '<i class="bi ' + (opts.icon || 'bi-question-circle') + '"></i>';

    // Title & subtitle
    titleEl.textContent = opts.title || 'Confirmation';
    subtitleEl.textContent = opts.subtitle || '';
    subtitleEl.style.display = opts.subtitle ? '' : 'none';

    // Body
    textEl.innerHTML = opts.text || 'Êtes-vous sûr ?';

    // OK button — theme-aware colors
    okBtn.className = 'btn btn-sm ss-confirm-btn ss-confirm-' + type;
    okBtn.textContent = opts.okText || 'Confirmer';

    // Cancel button
    cancelBtn.className = 'btn btn-sm btn-light';
    cancelBtn.textContent = opts.cancelText || 'Annuler';

    // Show
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal'));
    let resolved = false;

    function done(val) {
      if (resolved) return;
      resolved = true;
      modal.hide();
      resolve(val);
    }

    okBtn.onclick = () => done(true);
    cancelBtn.onclick = () => done(false);

    // Si fermé autrement (clic overlay, Escape)
    document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function handler() {
      document.getElementById('confirmModal').removeEventListener('hidden.bs.modal', handler);
      if (!resolved) { resolved = true; resolve(false); }
    });

    // Dim modals behind the confirm
    const confirmEl = document.getElementById('confirmModal');
    document.querySelectorAll('.modal.show').forEach(m => {
      if (m !== confirmEl && !m.querySelector('.ss-dim-overlay')) {
        const ov = document.createElement('div');
        ov.className = 'ss-dim-overlay';
        m.appendChild(ov);
      }
    });
    confirmEl.addEventListener('hidden.bs.modal', function onHide() {
      confirmEl.removeEventListener('hidden.bs.modal', onHide);
      document.querySelectorAll('.ss-dim-overlay').forEach(ov => ov.remove());
    });

    confirmEl.addEventListener('shown.bs.modal', function onShown() {
      confirmEl.removeEventListener('shown.bs.modal', onShown);
      // Bump backdrop z-index above other modals
      const backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 1) {
        backdrops[backdrops.length - 1].style.zIndex = '1065';
      }
    });

    modal.show();
  });
}

/**
 * adminPrompt — remplace prompt() natif par un joli modal
 * @param {Object} opts
 *   title       : string
 *   label       : string — label du champ
 *   placeholder : string
 *   defaultValue: string
 *   icon        : string — classe bi
 *   okText      : string
 * @returns {Promise<string|null>} — valeur saisie ou null si annulé
 */
function adminPrompt(opts = {}) {
  return new Promise(resolve => {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('promptModal'));
    const titleEl = document.getElementById('promptModalTitle');
    const labelEl = document.getElementById('promptModalLabel');
    const inputEl = document.getElementById('promptModalInput');
    const okBtn = document.getElementById('promptModalOk');
    const iconEl = document.getElementById('promptModalIcon');

    titleEl.textContent = opts.title || 'Saisie';
    labelEl.textContent = opts.label || '';
    labelEl.style.display = opts.label ? '' : 'none';
    inputEl.value = opts.defaultValue || '';
    inputEl.placeholder = opts.placeholder || '';
    okBtn.textContent = opts.okText || 'Valider';
    iconEl.innerHTML = '<i class="bi ' + (opts.icon || 'bi-pencil') + '"></i>';

    let resolved = false;
    function done(val) {
      if (resolved) return;
      resolved = true;
      modal.hide();
      resolve(val);
    }

    okBtn.onclick = () => {
      const v = inputEl.value.trim();
      if (v) done(v); else inputEl.focus();
    };
    inputEl.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); okBtn.click(); } };

    // Dim modals behind
    const promptEl = document.getElementById('promptModal');
    document.querySelectorAll('.modal.show').forEach(m => {
      if (m !== promptEl && !m.querySelector('.ss-dim-overlay')) {
        const ov = document.createElement('div');
        ov.className = 'ss-dim-overlay';
        m.appendChild(ov);
      }
    });

    promptEl.addEventListener('hidden.bs.modal', function onHide() {
      promptEl.removeEventListener('hidden.bs.modal', onHide);
      document.querySelectorAll('.ss-dim-overlay').forEach(ov => ov.remove());
      if (!resolved) { resolved = true; resolve(null); }
    });

    promptEl.addEventListener('shown.bs.modal', function onShown() {
      promptEl.removeEventListener('shown.bs.modal', onShown);
      inputEl.focus();
      inputEl.select();
      // Bump backdrop z-index above other modals
      const backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 1) {
        backdrops[backdrops.length - 1].style.zIndex = '1065';
      }
    });

    modal.show();
  });
}
</script>

<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/admin.js?v=<?= APP_VERSION ?>"></script>

<!-- ═══ GLOBAL COMPOSE PANEL ═══ -->
<div class="compose-panel" id="globalComposePanel">
  <div class="compose-panel-header" id="globalComposePanelHeader">
    <span class="compose-panel-title" id="globalComposePanelTitle">Nouveau message</span>
    <div class="compose-panel-header-actions">
      <button type="button" class="compose-panel-header-btn" id="globalComposeMinimize" title="Réduire"><i class="bi bi-dash-lg"></i></button>
      <button type="button" class="compose-panel-header-btn" id="globalComposeExpand" title="Agrandir"><i class="bi bi-arrows-angle-expand"></i></button>
      <button type="button" class="compose-panel-header-btn" id="globalComposeClose" title="Fermer"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
  <div class="compose-panel-body">
    <div class="compose-field">
      <label>À</label>
      <div class="zs-select" id="globalComposeTo" data-placeholder="— Choisir —"></div>
      <div id="globalToTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <label>Cc</label>
      <div class="zs-select" id="globalComposeCc" data-placeholder="— Choisir —"></div>
      <div id="globalCcTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <input type="text" class="form-control form-control-sm" id="globalComposeSubject" placeholder="Sujet" maxlength="255">
    </div>
    <div id="globalComposeEditorWrap" class="zs-editor-wrap compose-editor-wrap"></div>
    <div class="att-preview-list" id="globalAttPreviewList"></div>
  </div>
  <div class="compose-panel-footer">
    <button type="button" class="adm-email-btn" id="globalComposeSend"><i class="bi bi-send"></i> Envoyer</button>
    <div class="compose-panel-footer-right">
      <input type="file" id="globalComposeFile" multiple hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt,.csv">
      <button type="button" class="compose-panel-footer-btn" id="globalComposeAttach" title="Joindre"><i class="bi bi-paperclip"></i></button>
      <button type="button" class="compose-panel-footer-btn compose-panel-delete" id="globalComposeDiscard" title="Annuler"><i class="bi bi-trash3"></i></button>
    </div>
  </div>
</div>

<script nonce="<?= $cspNonce ?>">
// ═══ Global Compose Module ═══
(function() {
    const contacts = <?= json_encode(array_values($globalComposeContacts), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let editorModule = null;
    let composeEditor = null;
    let toSelected = [];
    let ccSelected = [];
    let pendingFiles = [];
    let initialized = false;

    async function getEditorModule() {
        if (!editorModule) editorModule = await import('/spocspace/assets/js/rich-editor.js');
        return editorModule;
    }

    const contactOpts = contacts.map(c => ({
        value: c.id,
        label: c.prenom + ' ' + c.nom + (c.fonction_nom ? ' (' + c.fonction_nom + ')' : ''),
        searchText: c.prenom + ' ' + c.nom + ' ' + (c.email || '')
    }));

    function initSelects() {
        if (initialized) return;
        initialized = true;

        zerdaSelect.init('#globalComposeTo', contactOpts, {
            search: true,
            onSelect: (val) => {
                if (val && !toSelected.includes(val)) { toSelected.push(val); renderTags(toSelected, 'globalToTags'); }
                zerdaSelect.setValue('#globalComposeTo', '');
            }
        });
        zerdaSelect.init('#globalComposeCc', contactOpts, {
            search: true,
            onSelect: (val) => {
                if (val && !ccSelected.includes(val)) { ccSelected.push(val); renderTags(ccSelected, 'globalCcTags'); }
                zerdaSelect.setValue('#globalComposeCc', '');
            }
        });

        document.getElementById('globalComposeMinimize')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('globalComposePanel')?.classList.toggle('minimized');
        });
        document.getElementById('globalComposeExpand')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const panel = document.getElementById('globalComposePanel');
            const icon = document.querySelector('#globalComposeExpand i');
            if (panel) {
                panel.classList.toggle('expanded');
                if (icon) icon.className = panel.classList.contains('expanded') ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-angle-expand';
            }
        });
        document.getElementById('globalComposePanelHeader')?.addEventListener('click', () => {
            const panel = document.getElementById('globalComposePanel');
            if (panel?.classList.contains('minimized')) panel.classList.remove('minimized');
        });
        document.getElementById('globalComposeClose')?.addEventListener('click', (e) => { e.stopPropagation(); close(); });
        document.getElementById('globalComposeDiscard')?.addEventListener('click', close);
        document.getElementById('globalComposeSend')?.addEventListener('click', send);
        document.getElementById('globalComposeAttach')?.addEventListener('click', () => {
            document.getElementById('globalComposeFile')?.click();
        });
        document.getElementById('globalComposeFile')?.addEventListener('change', (e) => {
            for (const f of e.target.files) pendingFiles.push(f);
            e.target.value = '';
            renderPendingFiles();
        });
    }

    function renderTags(ids, containerId) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = ids.map(id => {
            const c = contacts.find(x => x.id === id);
            if (!c) return '';
            return '<span class="email-tag">' + escapeHtml(c.prenom + ' ' + c.nom)
                + ' <button type="button" data-remove="' + id + '" data-container="' + containerId + '">&times;</button></span>';
        }).join('');
        el.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => {
                const rid = btn.dataset.remove;
                const cid = btn.dataset.container;
                if (cid === 'globalToTags') toSelected = toSelected.filter(x => x !== rid);
                else ccSelected = ccSelected.filter(x => x !== rid);
                renderTags(cid === 'globalToTags' ? toSelected : ccSelected, cid);
            });
        });
    }

    function getFileIcon(mime) {
        if (mime.includes('pdf')) return 'bi-file-earmark-pdf-fill';
        if (mime.includes('word') || mime.includes('document')) return 'bi-file-earmark-word-fill';
        if (mime.includes('sheet') || mime.includes('excel')) return 'bi-file-earmark-excel-fill';
        return 'bi-file-earmark';
    }

    function renderPendingFiles() {
        const el = document.getElementById('globalAttPreviewList');
        if (!el) return;
        if (!pendingFiles.length) { el.innerHTML = ''; return; }
        el.innerHTML = pendingFiles.map((f, i) => {
            const isImg = f.type.startsWith('image/');
            const thumb = isImg
                ? '<img src="' + URL.createObjectURL(f) + '" alt="">'
                : '<i class="bi ' + getFileIcon(f.type) + '"></i>';
            return '<div class="att-preview-card">'
                + '<div class="att-preview-thumb">' + thumb + '</div>'
                + '<div class="att-preview-name" title="' + escapeHtml(f.name) + '">' + escapeHtml(f.name) + '</div>'
                + '<button class="att-preview-remove" data-idx="' + i + '">&times;</button>'
                + '</div>';
        }).join('');
        el.querySelectorAll('.att-preview-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                pendingFiles.splice(parseInt(btn.dataset.idx), 1);
                renderPendingFiles();
            });
        });
    }

    async function open(prefill) {
        initSelects();
        prefill = prefill || {};
        toSelected = prefill.to || [];
        ccSelected = prefill.cc || [];
        pendingFiles = [];
        renderPendingFiles();
        renderTags(toSelected, 'globalToTags');
        renderTags(ccSelected, 'globalCcTags');

        document.getElementById('globalComposeSubject').value = prefill.subject || '';
        document.getElementById('globalComposePanelTitle').textContent = prefill.title || 'Nouveau message';

        const editorWrap = document.getElementById('globalComposeEditorWrap');
        const em = await getEditorModule();
        if (composeEditor) { em.destroyEditor(composeEditor); composeEditor = null; }
        em.createEditor(editorWrap, {
            placeholder: 'Écrivez votre message...',
            content: prefill.body || '',
            mode: 'mini'
        }).then(ed => { composeEditor = ed; });

        const panel = document.getElementById('globalComposePanel');
        if (panel) {
            panel.classList.remove('minimized');
            panel.classList.add('open');
        }
    }

    function close() {
        const panel = document.getElementById('globalComposePanel');
        if (panel) {
            panel.classList.remove('open', 'expanded');
            const icon = document.querySelector('#globalComposeExpand i');
            if (icon) icon.className = 'bi bi-arrows-angle-expand';
        }
        if (composeEditor && editorModule) { editorModule.destroyEditor(composeEditor); composeEditor = null; }
        pendingFiles = [];
        renderPendingFiles();
    }

    async function send() {
        const sujet = document.getElementById('globalComposeSubject')?.value.trim();
        const contenu = editorModule ? editorModule.getHTML(composeEditor) : '';

        if (!sujet) { showToast('Sujet requis', 'error'); return; }
        if (!contenu || contenu === '<br>') { showToast('Message requis', 'error'); return; }
        if (!toSelected.length) { showToast('Au moins un destinataire', 'error'); return; }

        // Upload attachments first
        const attachmentIds = [];
        for (const file of pendingFiles) {
            const fd = new FormData();
            fd.append('action', 'admin_upload_message_attachment');
            fd.append('file', file);
            const resp = await fetch('/spocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: fd
            });
            const r = await resp.json();
            if (r.success && r.id) attachmentIds.push(r.id);
        }

        const res = await adminApiPost('admin_send_message', {
            sujet,
            contenu,
            to: toSelected,
            cc: ccSelected,
            attachment_ids: attachmentIds
        });

        if (res.success) {
            showToast('Message envoyé', 'success');
            close();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    // Expose globally
    window.ztCompose = { open, close };
})();
</script>

<!-- ═══ MODAL: Raccourcis clavier ═══ -->
<div class="modal fade" id="shortcutsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-keyboard"></i> Raccourcis clavier</h5>
        <div class="d-flex gap-2 ms-auto">
          <button class="btn btn-sm btn-outline-secondary" id="scResetBtn" title="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body" style="max-height:65vh;overflow-y:auto">
        <p class="text-muted small mb-3">Cliquez sur un raccourci pour le modifier. Appuyez sur la combinaison souhaitée (Ctrl/Alt/Shift + touche).</p>
        <div id="scList"></div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">Appuyez sur <kbd>?</kbd> n'importe où pour afficher ce panneau</small>
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<style>
.sc-group-title { font-weight: 700; font-size: .85rem; color: var(--cl-text-secondary); text-transform: uppercase; letter-spacing: .5px; padding: 8px 0 4px; border-bottom: 1.5px solid var(--cl-border); margin-top: 16px; margin-bottom: 8px; }
.sc-group-title:first-child { margin-top: 0; }
.sc-row { display: flex; align-items: center; padding: 8px 12px; border-radius: 6px; transition: background .15s; }
.sc-row:hover { background: var(--cl-bg); }
.sc-label { flex: 1; font-size: .9rem; }
.sc-key-wrap { display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 4px 8px; border-radius: 6px; border: 1.5px solid transparent; transition: border-color .2s; }
.sc-key-wrap:hover { border-color: var(--cl-accent); }
.sc-key-wrap.recording { border-color: var(--cl-accent); background: rgba(25,25,24,.04); }
.sc-key-wrap.recording::after { content: 'Appuyez...'; font-size: .75rem; color: var(--cl-accent); margin-left: 8px; }
kbd { background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 4px; padding: 2px 7px; font-size: .8rem; font-family: inherit; color: var(--cl-text); box-shadow: 0 1px 2px rgba(0,0,0,.08); }
.sc-key-none { font-size: .8rem; color: var(--cl-text-muted); font-style: italic; }
</style>

<script nonce="<?= $cspNonce ?>">
// ═══════════════════════════════════════════════════════════════════════════════
// Keyboard Shortcuts System
// ═══════════════════════════════════════════════════════════════════════════════
(function() {
    const STORAGE_KEY = 'ss_shortcuts';

    // Navigate: use SPA router in fullscreen, normal otherwise
    function goTo(url) {
        if (document.fullscreenElement && window.__ztNavigateTo) {
            window.__ztNavigateTo(url);
        } else {
            location.href = url;
        }
    }

    // ── Default shortcuts definition ──
    const SHORTCUTS_DEF = [
        { group: 'Navigation', items: [
            { id: 'nav_dashboard',    label: 'Tableau de bord',       default: 'Alt+D',     action: () => goTo('/spocspace/admin/') },
            { id: 'nav_planning',     label: 'Planning',              default: 'Alt+P',     action: () => goTo('/spocspace/admin/planning') },
            { id: 'nav_users',        label: 'Collaborateurs',        default: 'Alt+U',     action: () => goTo('/spocspace/admin/users') },
            { id: 'nav_residents',    label: 'Résidents',             default: 'Alt+R',     action: () => goTo('/spocspace/admin/residents') },
            { id: 'nav_absences',     label: 'Absences',              default: 'Alt+A',     action: () => goTo('/spocspace/admin/absences') },
            { id: 'nav_desirs',       label: 'Désirs',                default: 'Alt+W',     action: () => goTo('/spocspace/admin/desirs') },
            { id: 'nav_vacances',     label: 'Vacances',              default: 'Alt+V',     action: () => goTo('/spocspace/admin/vacances') },
            { id: 'nav_messages',     label: 'Messagerie',            default: 'Alt+M',     action: () => goTo('/spocspace/admin/messages') },
            { id: 'nav_famille',      label: 'Espace Famille',        default: 'Alt+F',     action: () => goTo('/spocspace/admin/famille') },
            { id: 'nav_documents',    label: 'Documents',             default: 'Alt+O',     action: () => goTo('/spocspace/admin/documents') },
            { id: 'nav_annuaire',     label: 'Annuaire téléphonique', default: 'Alt+H',     action: () => goTo('/spocspace/admin/annuaire') },
        ]},
        { group: 'Outils', items: [
            { id: 'nav_todos',        label: 'Tâches (Todos)',        default: 'Alt+T',     action: () => goTo('/spocspace/admin/todos') },
            { id: 'nav_notes',        label: 'Notes',                 default: 'Alt+N',     action: () => goTo('/spocspace/admin/notes') },
            { id: 'nav_pv',           label: 'Procès-Verbaux',        default: 'Alt+J',     action: () => goTo('/spocspace/admin/pv') },
            { id: 'nav_sondages',     label: 'Sondages',              default: 'Alt+G',     action: () => goTo('/spocspace/admin/sondages') },
            { id: 'nav_stats',        label: 'Statistiques',          default: 'Alt+S',     action: () => goTo('/spocspace/admin/stats') },
        ]},
        { group: 'Actions rapides', items: [
            { id: 'act_email',        label: 'Nouveau message (email)', default: 'Alt+E', action: openComposeEmail },
            { id: 'act_search',       label: 'Focus recherche',        default: 'Ctrl+K', action: focusSearch },
            { id: 'act_fullscreen',   label: 'Plein écran',            default: 'F11',    action: toggleFullscreen },
            { id: 'act_sidebar',      label: 'Replier/déplier sidebar', default: 'Ctrl+B', action: toggleSidebar },
            { id: 'act_shortcuts',    label: 'Afficher raccourcis',     default: '?',      action: showShortcutsModal },
        ]},
    ];

    // ── Action implementations ──
    function openComposeEmail() {
        if (window.ztCompose) window.ztCompose.open();
    }

    function focusSearch() {
        const input = document.getElementById('topbarSearchInput');
        if (input) { input.focus(); input.select(); }
    }

    function toggleFullscreen() {
        document.getElementById('immersiveToggle')?.click();
    }

    function toggleSidebar() {
        document.getElementById('sidebarToggleBtn')?.click();
    }

    function showShortcutsModal() {
        var m = bootstrap.Modal.getOrCreateInstance(document.getElementById('shortcutsModal'));
        m.show();
    }

    // ── Parse / format key combos ──
    function parseCombo(str) {
        if (!str) return null;
        var parts = str.split('+').map(function(s) { return s.trim(); });
        return {
            ctrl: parts.includes('Ctrl'),
            alt: parts.includes('Alt'),
            shift: parts.includes('Shift'),
            key: parts.filter(function(p) { return !['Ctrl','Alt','Shift'].includes(p); })[0] || ''
        };
    }

    function formatCombo(combo) {
        if (!combo || !combo.key) return '';
        var parts = [];
        if (combo.ctrl) parts.push('Ctrl');
        if (combo.alt) parts.push('Alt');
        if (combo.shift) parts.push('Shift');
        parts.push(combo.key);
        return parts.join('+');
    }

    function comboToKbd(str) {
        if (!str) return '<span class="sc-key-none">Non défini</span>';
        return str.split('+').map(function(p) { return '<kbd>' + p.trim() + '</kbd>'; }).join(' + ');
    }

    function matchEvent(e, combo) {
        if (!combo || !combo.key) return false;
        if (combo.ctrl !== (e.ctrlKey || e.metaKey)) return false;
        if (combo.alt !== e.altKey) return false;
        if (combo.shift !== e.shiftKey) return false;
        var k = combo.key.toUpperCase();
        if (k.length === 1) return e.key.toUpperCase() === k;
        if (k === 'F11') return e.key === 'F11';
        if (k === '?') return e.key === '?';
        return e.key.toUpperCase() === k || e.code.toUpperCase() === ('KEY' + k);
    }

    // ── Load/save from localStorage ──
    function loadCustom() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; }
    }

    function saveCustom(map) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    }

    function getComboForItem(item) {
        var custom = loadCustom();
        var str = custom[item.id] !== undefined ? custom[item.id] : item.default;
        return parseCombo(str);
    }

    function getStrForItem(item) {
        var custom = loadCustom();
        return custom[item.id] !== undefined ? custom[item.id] : item.default;
    }

    // ── Render shortcuts modal ──
    function renderShortcutsList() {
        var container = document.getElementById('scList');
        if (!container) return;
        var html = '';

        SHORTCUTS_DEF.forEach(function(group) {
            html += '<div class="sc-group-title">' + group.group + '</div>';
            group.items.forEach(function(item) {
                var combo = getStrForItem(item);
                html += '<div class="sc-row">'
                    + '<span class="sc-label">' + item.label + '</span>'
                    + '<div class="sc-key-wrap" data-sc-id="' + item.id + '">' + comboToKbd(combo) + '</div>'
                    + '</div>';
            });
        });

        container.innerHTML = html;

        // Click to edit
        container.querySelectorAll('.sc-key-wrap').forEach(function(el) {
            el.addEventListener('click', function() { startRecording(el); });
        });
    }

    // ── Recording mode ──
    var recordingEl = null;
    var recordingId = null;

    function startRecording(el) {
        if (recordingEl) recordingEl.classList.remove('recording');
        recordingEl = el;
        recordingId = el.dataset.scId;
        el.classList.add('recording');
        el.innerHTML = '';
    }

    function stopRecording(combo) {
        if (!recordingEl) return;
        var str = formatCombo(combo);
        var custom = loadCustom();
        custom[recordingId] = str;
        saveCustom(custom);
        recordingEl.classList.remove('recording');
        recordingEl.innerHTML = comboToKbd(str);
        recordingEl = null;
        recordingId = null;
    }

    function cancelRecording() {
        if (!recordingEl) return;
        var item = findItem(recordingId);
        recordingEl.classList.remove('recording');
        recordingEl.innerHTML = comboToKbd(getStrForItem(item));
        recordingEl = null;
        recordingId = null;
    }

    function findItem(id) {
        for (var g = 0; g < SHORTCUTS_DEF.length; g++) {
            for (var i = 0; i < SHORTCUTS_DEF[g].items.length; i++) {
                if (SHORTCUTS_DEF[g].items[i].id === id) return SHORTCUTS_DEF[g].items[i];
            }
        }
        return null;
    }

    // ── Global key handler ──
    document.addEventListener('keydown', function(e) {
        // Don't trigger shortcuts when typing in inputs
        var tag = (e.target.tagName || '').toLowerCase();
        var isInput = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;

        // Recording mode
        if (recordingEl) {
            e.preventDefault();
            e.stopPropagation();
            if (e.key === 'Escape') { cancelRecording(); return; }
            if (e.key === 'Backspace' || e.key === 'Delete') {
                // Clear shortcut
                var custom = loadCustom();
                custom[recordingId] = '';
                saveCustom(custom);
                recordingEl.classList.remove('recording');
                recordingEl.innerHTML = '<span class="sc-key-none">Non défini</span>';
                recordingEl = null;
                recordingId = null;
                return;
            }
            if (['Control','Alt','Shift','Meta'].includes(e.key)) return; // wait for actual key
            stopRecording({ ctrl: e.ctrlKey || e.metaKey, alt: e.altKey, shift: e.shiftKey, key: e.key.length === 1 ? e.key.toUpperCase() : e.key });
            return;
        }

        // ? key to show shortcuts (only if not in input)
        if (e.key === '?' && !isInput) {
            e.preventDefault();
            showShortcutsModal();
            return;
        }

        // Match shortcuts
        for (var g = 0; g < SHORTCUTS_DEF.length; g++) {
            for (var i = 0; i < SHORTCUTS_DEF[g].items.length; i++) {
                var item = SHORTCUTS_DEF[g].items[i];
                var combo = getComboForItem(item);
                if (!combo) continue;

                // Allow search shortcut even in inputs
                if (item.id === 'act_search' && matchEvent(e, combo)) {
                    e.preventDefault();
                    item.action();
                    return;
                }

                // Skip other shortcuts when in inputs
                if (isInput) continue;

                if (matchEvent(e, combo)) {
                    e.preventDefault();
                    item.action();
                    return;
                }
            }
        }
    });

    // ── Button + reset ──
    document.getElementById('shortcutsBtn')?.addEventListener('click', function() {
        renderShortcutsList();
        showShortcutsModal();
    });
    document.getElementById('sidebarShortcutsBtn')?.addEventListener('click', function() {
        renderShortcutsList();
        showShortcutsModal();
    });

    document.getElementById('scResetBtn')?.addEventListener('click', function() {
        localStorage.removeItem(STORAGE_KEY);
        renderShortcutsList();
        showToast('Raccourcis réinitialisés', 'success');
    });


    // Init list
    renderShortcutsList();
})();
</script>

<!-- ═══ PWA: Update Modal ═══ -->
<style<?= nonce() ?>>@keyframes ss-update-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}#ztUpdateNow:hover{background:#a8c4bb!important}#ztUpdateLater:hover{background:#f7f5f2!important;border-color:#bcd2cb!important}</style>
<div id="ztUpdateOverlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);transition:opacity .3s;opacity:0">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:18px;padding:32px 36px;width:380px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center">
    <div id="ztUpdateIcon" style="width:56px;height:56px;border-radius:50%;background:#bcd2cb;color:#2d4a43;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.4rem">
      <i class="bi bi-arrow-repeat"></i>
    </div>
    <h6 style="font-weight:700;font-size:1rem;margin:0 0 6px">Nouvelle version disponible</h6>
    <p id="ztUpdateText" style="font-size:.85rem;color:#666;margin:0 0 20px">Une mise à jour est prête. Souhaitez-vous l'installer maintenant ?</p>
    <div id="ztUpdateProgress" style="display:none;margin-bottom:20px">
      <div style="background:#f0eeeb;border-radius:8px;height:8px;overflow:hidden">
        <div id="ztUpdateBar" style="height:100%;width:0%;background:linear-gradient(90deg,#bcd2cb,#8fb5a8);border-radius:8px;transition:width .3s ease"></div>
      </div>
      <div id="ztUpdatePercent" style="font-size:.75rem;color:#888;margin-top:6px">Préparation...</div>
    </div>
    <div id="ztUpdateBtns" style="display:flex;gap:10px;justify-content:center">
      <button id="ztUpdateLater" style="padding:9px 22px;border-radius:10px;border:1.5px solid #e5e7eb;background:#fff;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;color:#666">Plus tard</button>
      <button id="ztUpdateNow" style="padding:9px 22px;border-radius:10px;border:none;background:#bcd2cb;color:#2d4a43;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s">Mettre à jour</button>
    </div>
  </div>
</div>

<!-- ═══ PWA: Service Worker + Offline banner ═══ -->
<div id="ztOfflineBanner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1A1A1A;color:#fff;padding:8px 20px;font-size:.82rem;text-align:center">
  <i class="bi bi-wifi-off"></i> Mode hors ligne — les données en cache sont affichées
  <span id="ztSyncPending"></span>
</div>
<script nonce="<?= $cspNonce ?>">
(function() {
    // ── Update modal helpers ──
    function showUpdateModal(newSW) {
        const overlay = document.getElementById('ztUpdateOverlay');
        const btnNow = document.getElementById('ztUpdateNow');
        const btnLater = document.getElementById('ztUpdateLater');
        const progress = document.getElementById('ztUpdateProgress');
        const bar = document.getElementById('ztUpdateBar');
        const percent = document.getElementById('ztUpdatePercent');
        const btns = document.getElementById('ztUpdateBtns');
        const icon = document.getElementById('ztUpdateIcon');
        const text = document.getElementById('ztUpdateText');

        overlay.style.display = '';
        requestAnimationFrame(() => { overlay.style.opacity = '1'; });

        function closeModal() {
            overlay.style.opacity = '0';
            setTimeout(() => { overlay.style.display = 'none'; }, 300);
        }

        btnLater.onclick = closeModal;

        btnNow.onclick = () => {
            // Show progress bar, hide buttons
            btns.style.display = 'none';
            progress.style.display = '';
            text.textContent = 'Installation en cours...';
            icon.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:ss-update-spin 1s linear infinite"></i>';

            // Simulate progress (SW activation is nearly instant but we make it feel smooth)
            let pct = 0;
            const steps = [
                { to: 25, label: 'Téléchargement...', delay: 200 },
                { to: 50, label: 'Installation des fichiers...', delay: 400 },
                { to: 75, label: 'Nettoyage du cache...', delay: 300 },
                { to: 95, label: 'Presque terminé...', delay: 300 },
            ];

            let i = 0;
            function nextStep() {
                if (i >= steps.length) {
                    // Final step: activate SW and reload
                    bar.style.width = '100%';
                    percent.textContent = 'Redémarrage...';
                    icon.style.background = '#bcd2cb';
                    icon.innerHTML = '<i class="bi bi-check-lg"></i>';
                    text.textContent = 'Mise à jour terminée !';
                    newSW.postMessage({ type: 'SKIP_WAITING' });
                    setTimeout(() => location.reload(), 500);
                    return;
                }
                const step = steps[i];
                bar.style.width = step.to + '%';
                percent.textContent = step.label;
                i++;
                setTimeout(nextStep, step.delay);
            }
            nextStep();
        };
    }

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/spocspace/sw.js', { scope: '/spocspace/' })
            .then(reg => {
                console.log('[PWA] SW registered:', reg.scope);
                // Check for updates
                reg.addEventListener('updatefound', () => {
                    const newSW = reg.installing;
                    newSW.addEventListener('statechange', () => {
                        if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateModal(newSW);
                        }
                    });
                });
            })
            .catch(err => console.warn('[PWA] SW registration failed:', err));

        // Listen for sync messages
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data?.type === 'SYNC_COMPLETE') {
                showToast(event.data.processed + ' action(s) synchronisée(s)', 'success');
                if (window.__ztRefreshUnread) window.__ztRefreshUnread();
            }
        });
    }

    // ── PWA Install Prompt ──
    let deferredInstallPrompt = null;
    const installBtn = document.getElementById('ztInstallBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        if (installBtn) installBtn.style.display = '';
    });

    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                // Fallback: show instructions
                await adminConfirm({
                    title: 'Installer SpocSpace',
                    text: '<div style="text-align:left;font-size:.88rem">'
                        + '<p><strong>Chrome / Edge :</strong></p>'
                        + '<ol style="margin:8px 0 16px 20px"><li>Cliquez sur le menu <kbd>⋮</kbd> en haut à droite</li><li>Sélectionnez <strong>"Installer SpocSpace"</strong></li></ol>'
                        + '<p><strong>Safari (Mac) :</strong></p>'
                        + '<ol style="margin:8px 0 16px 20px"><li>Menu <strong>Fichier → Ajouter au Dock</strong></li></ol>'
                        + '<p><strong>iPhone / iPad :</strong></p>'
                        + '<ol style="margin:8px 0 0 20px"><li>Appuyez sur <strong>Partager</strong> (⬆)</li><li>Sélectionnez <strong>"Sur l\'écran d\'accueil"</strong></li></ol>'
                        + '</div>',
                    type: 'info',
                    icon: 'bi-download',
                    okText: 'Compris',
                    cancelText: ''
                });
                return;
            }
            deferredInstallPrompt.prompt();
            const result = await deferredInstallPrompt.userChoice;
            if (result.outcome === 'accepted') {
                showToast('Application installée', 'success');
                installBtn.style.display = 'none';
            }
            deferredInstallPrompt = null;
        });
    }

    // Hide install button if already installed as PWA
    window.addEventListener('appinstalled', () => {
        if (installBtn) installBtn.style.display = 'none';
        deferredInstallPrompt = null;
    });

    // Always show button if not in standalone mode (as fallback instructions)
    if (!window.matchMedia('(display-mode: standalone)').matches && !navigator.standalone) {
        if (installBtn) installBtn.style.display = '';
    }

    // Offline/online banner
    const banner = document.getElementById('ztOfflineBanner');
    function updateOnlineStatus() {
        banner.style.display = navigator.onLine ? 'none' : 'block';
        if (navigator.onLine && navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'PROCESS_QUEUE' });
        }
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
})();
</script>
</body>
</html>
