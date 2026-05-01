<?php
/**
 * SpocSpace Admin Panel - Server-rendered with retractable sidebar
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../_partials/icons.php';

/**
 * Build a clean admin URL
 * @param string $page  Page name (empty or 'dashboard' → /newspocspace/admin/)
 * @param string $id    Optional ID segment
 * @param array  $extra Optional query parameters
 */
function admin_url(string $page = '', string $id = '', array $extra = []): string {
    $base = '/newspocspace/admin';
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
    header('Location: /newspocspace/login');
    exit;
}

$admin = $_SESSION['ss_user'];
$csrfToken = $_SESSION['ss_csrf_token'] ?? '';

// CSP nonce for inline scripts
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);
function nonce(): string { return ' nonce="' . CSP_NONCE . '"'; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' blob: http://localhost:5876 http://localhost:5877 http://localhost:5878 http://localhost:11434 https://api.deepgram.com wss://api.deepgram.com; worker-src 'self' blob:; media-src 'self' blob:;");

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
    header('Location: /newspocspace/login');
    exit;
}

// Current page
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'etablissement', 'users', 'rh-collaborateurs', 'user-edit', 'user-detail', 'planning', 'modules', 'horaires', 'desirs', 'absences', 'vacances', 'changements', 'stats', 'besoins', 'messages', 'alertes', 'apparence', 'config-ia', 'repartition', 'affichage-planning', 'pv', 'pv-detail', 'pv-record', 'sondages', 'sondage-edit', 'documents', 'fiches-salaire', 'import-export', 'todos', 'notes', 'roadmap', 'residents', 'marquage', 'famille', 'cuisine', 'reservations', 'email-externe', 'email-config', 'contacts', 'recrutement', 'rh-offres', 'rh-candidatures', 'rh-formations', 'rh-formations-stats', 'rh-formations-cartographie', 'rh-formations-fegems', 'rh-formations-sessions', 'rh-formations-profil', 'rh-formations-parametres', 'rh-formations-pluriannuel', 'rh-formations-dashboard', 'rh-formations-dashboard-secteur', 'rh-formations-collaborateurs', 'rh-collab-competences', 'rh-entretiens', 'rh-entretiens-fiche', 'rh-stagiaires', 'rh-stagiaire-detail', 'connexions', 'agenda', 'mur', 'wiki', 'annonces', 'wiki-analytics', 'securite', 'annuaire', 'sauvegardes', 'salles', 'evenements', 'email-templates', 'fiches-amelioration', 'suggestions'];
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
    'users'         => 'Horaires collaborateurs',
    'rh-collaborateurs' => 'Collaborateurs',
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
    'rh-formations-collaborateurs' => 'Collaborateurs · formation',
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

// ─── Compteurs sidebar (badges dynamiques) ────────────────────────────────────
$adminUserId = $admin['id'] ?? '';
$cntAbs = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$cntDesirs = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
try { $cntChg = (int) Db::getOne("SELECT COUNT(*) FROM changements WHERE statut = 'en_attente'"); } catch (\Throwable $e) { $cntChg = 0; }
try { $cntEch = (int) Db::getOne("SELECT COUNT(*) FROM echanges WHERE statut = 'en_attente'"); } catch (\Throwable $e) { $cntEch = 0; }
$cntMsg = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$adminUserId]);

// Sidebar — 6 modules unifiés (Planning · RH · Formation · Communication · Établissement · Configuration)
$sidebarModules = [
    'planning' => [
        'label' => 'Planning',
        'icon'  => 'calendar3',
        'sections' => [
            'planning-pilotage' => [
                'label' => 'Pilotage',
                'items' => [
                    'dashboard' => ['label' => 'Tableau de bord', 'icon' => 'house'],
                    'planning'  => ['label' => 'Plannings',       'icon' => 'calendar3'],
                    'users'     => ['label' => 'Collaborateurs',  'icon' => 'people'],
                ],
            ],
            'planning-demandes' => [
                'label' => 'Demandes',
                'items' => [
                    'absences'    => ['label' => 'Absences',    'icon' => 'chat-square-text', 'badge' => $cntAbs,    'badge_tone' => 'warn'],
                    'desirs'      => ['label' => 'Désirs',      'icon' => 'star',             'badge' => $cntDesirs, 'badge_tone' => 'warn'],
                    'changements' => ['label' => 'Changements', 'icon' => 'arrow-left-right', 'badge' => $cntChg,    'badge_tone' => 'warn'],
                    'echanges'    => ['label' => 'Échanges',    'icon' => 'arrow-down-up',    'badge' => $cntEch,    'badge_tone' => 'warn'],
                ],
            ],
            'planning-couverture' => [
                'label' => 'Couverture',
                'items' => [
                    'vacances'    => ['label' => 'Vacances',    'icon' => 'sun'],
                    'besoins'     => ['label' => 'Besoins',     'icon' => 'grid-3x3'],
                    'repartition' => ['label' => 'Répartition', 'icon' => 'grid-3x3-gap'],
                ],
            ],
            'planning-config' => [
                'label' => 'Configuration',
                'items' => [
                    'modules'            => ['label' => 'Modules & Unités', 'icon' => 'building'],
                    'horaires'           => ['label' => "Types d'horaires", 'icon' => 'clock'],
                    'affichage-planning' => ['label' => 'Affichage',        'icon' => 'sliders'],
                    'config-ia'          => ['label' => 'Config IA',        'icon' => 'cpu'],
                ],
            ],
        ],
    ],
    'rh' => [
        'label' => 'RH',
        'icon'  => 'people',
        'sections' => [
            'rh-effectif' => [
                'label' => 'Effectif',
                'items' => [
                    'rh-collaborateurs' => ['label' => 'Collaborateurs', 'icon' => 'people-fill'],
                ],
            ],
            'rh-recrutement' => [
                'label' => 'Recrutement',
                'items' => [
                    'rh-offres'       => ['label' => "Offres d'emploi", 'icon' => 'briefcase'],
                    'rh-candidatures' => ['label' => 'Candidatures',    'icon' => 'person-lines-fill'],
                    'rh-stagiaires'   => ['label' => 'Stagiaires',      'icon' => 'person-badge'],
                ],
            ],
            'rh-suivi' => [
                'label' => 'Suivi',
                'items' => [
                    'rh-entretiens'         => ['label' => 'Entretiens annuels', 'icon' => 'chat-square-text'],
                    'rh-collab-competences' => ['label' => 'Fiches compétences', 'icon' => 'shield-check'],
                    'fiches-salaire'        => ['label' => 'Fiches de salaire',  'icon' => 'receipt'],
                ],
            ],
        ],
    ],
    'formation' => [
        'label' => 'Formation',
        'icon'  => 'mortarboard',
        'sections' => [
            'formation-pilotage' => [
                'label' => 'Pilotage',
                'items' => [
                    'rh-formations-dashboard'      => ['label' => 'Tableau de bord',      'icon' => 'speedometer2'],
                    'rh-formations-collaborateurs' => ['label' => 'Collaborateurs',       'icon' => 'people-fill'],
                    'rh-formations'                => ['label' => 'Liste des formations', 'icon' => 'list-ul'],
                ],
            ],
            'formation-planif' => [
                'label' => 'Planification',
                'items' => [
                    'rh-formations-sessions'     => ['label' => 'Sessions & catalogue',     'icon' => 'calendar3'],
                    'rh-formations-pluriannuel'  => ['label' => 'Plan pluriannuel',         'icon' => 'graph-up-arrow'],
                    'rh-formations-cartographie' => ['label' => "Cartographie d'équipe",    'icon' => 'diagram-3'],
                    'rh-formations-profil'       => ['label' => "Profil d'équipe attendu",  'icon' => 'bullseye'],
                ],
            ],
            'formation-inscriptions' => [
                'label' => 'Inscriptions',
                'items' => [
                    'rh-formations-fegems' => ['label' => 'FEGEMS', 'icon' => 'cloud-arrow-up'],
                ],
            ],
            'formation-suivi' => [
                'label' => 'Suivi',
                'items' => [
                    'rh-formations-stats'      => ['label' => 'Statistiques', 'icon' => 'graph-up'],
                    'rh-formations-parametres' => ['label' => 'Paramètres',   'icon' => 'gear'],
                ],
            ],
        ],
    ],
    'communication' => [
        'label' => 'Communication',
        'icon'  => 'chat-dots',
        'sections' => [
            'comm-messagerie' => [
                'label' => 'Messagerie',
                'items' => [
                    'messages'        => ['label' => 'Messages',          'icon' => 'chat-dots',       'badge' => $cntMsg, 'badge_tone' => 'info'],
                    'email-externe'   => ['label' => 'Email externe',     'icon' => 'envelope'],
                    'email-config'    => ['label' => 'Config Email',      'icon' => 'envelope-at'],
                    'email-templates' => ['label' => "Templates d'email", 'icon' => 'envelope-paper'],
                ],
            ],
            'comm-diffusion' => [
                'label' => 'Diffusion',
                'items' => [
                    'annonces'       => ['label' => 'Annonces officielles', 'icon' => 'megaphone'],
                    'mur'            => ['label' => 'Mur social',           'icon' => 'chat-square-heart'],
                    'alertes'        => ['label' => 'Alertes',              'icon' => 'megaphone'],
                    'wiki'           => ['label' => 'Wiki',                 'icon' => 'book'],
                    'wiki-analytics' => ['label' => 'Analytics Wiki',       'icon' => 'graph-up'],
                ],
            ],
            'comm-contacts' => [
                'label' => 'Contacts',
                'items' => [
                    'contacts' => ['label' => 'Contacts', 'icon' => 'person-rolodex'],
                    'annuaire' => ['label' => 'Annuaire', 'icon' => 'telephone'],
                ],
            ],
            'comm-evenements' => [
                'label' => 'Événements',
                'items' => [
                    'agenda'     => ['label' => 'Agenda',     'icon' => 'calendar-week'],
                    'evenements' => ['label' => 'Événements', 'icon' => 'calendar-event'],
                    'salles'     => ['label' => 'Salles',     'icon' => 'door-open'],
                ],
            ],
        ],
    ],
    'etablissement' => [
        'label' => 'Établissement',
        'icon'  => 'hospital',
        'sections' => [
            'etab-residents' => [
                'label' => 'Résidents',
                'items' => [
                    'residents' => ['label' => 'Résidents',      'icon' => 'people'],
                    'famille'   => ['label' => 'Espace famille', 'icon' => 'heart'],
                ],
            ],
            'etab-services' => [
                'label' => 'Services',
                'items' => [
                    'cuisine'      => ['label' => 'Cuisine',           'icon' => 'cup-hot'],
                    'reservations' => ['label' => 'Réservations',      'icon' => 'calendar-check'],
                    'marquage'     => ['label' => 'Marquage lingerie', 'icon' => 'tag'],
                ],
            ],
            'etab-documents' => [
                'label' => 'Documents',
                'items' => [
                    'documents' => ['label' => 'Documents',      'icon' => 'folder2'],
                    'pv'        => ['label' => 'Procès-Verbaux', 'icon' => 'file-earmark-text'],
                    'sondages'  => ['label' => 'Sondages',       'icon' => 'clipboard2-check'],
                ],
            ],
            'etab-qualite' => [
                'label' => 'Qualité',
                'items' => [
                    'fiches-amelioration' => ['label' => 'Amélioration continue', 'icon' => 'lightbulb'],
                    'suggestions'         => ['label' => 'Suggestions',           'icon' => 'chat-square-quote'],
                ],
            ],
        ],
    ],
    'configuration' => [
        'label' => 'Configuration',
        'icon'  => 'gear',
        'sections' => [
            'config-systeme' => [
                'label' => 'Système',
                'items' => [
                    'etablissement' => ['label' => 'Établissement', 'icon' => 'hospital'],
                    'apparence'     => ['label' => 'Apparence',     'icon' => 'palette'],
                    'securite'      => ['label' => 'Sécurité',      'icon' => 'shield-check'],
                    'sauvegardes'   => ['label' => 'Sauvegardes',   'icon' => 'database-down'],
                ],
            ],
            'config-outils' => [
                'label' => 'Outils internes',
                'items' => [
                    'todos'         => ['label' => 'Tâches',          'icon' => 'check2-square'],
                    'notes'         => ['label' => 'Notes',           'icon' => 'journal-text'],
                    'roadmap'       => ['label' => 'Roadmap',         'icon' => 'rocket-takeoff'],
                    'connexions'   => ['label' => 'Connexions',       'icon' => 'person-check'],
                    'stats'         => ['label' => 'Statistiques',    'icon' => 'graph-up'],
                    'import-export' => ['label' => 'Import / Export', 'icon' => 'arrow-down-up'],
                ],
            ],
        ],
    ],
];

// Module Suggestions : retirer si flag désactivé ou rôle non autorisé
$sugFlag = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'allow_feature_requests'");
if ($sugFlag !== '1' || !in_array($admin['role'] ?? '', ['admin', 'direction'])) {
    unset($sidebarModules['etablissement']['sections']['etab-qualite']['items']['suggestions']);
}

$activeSection = match($page) {
    'user-edit', 'user-detail' => 'users',
    default => $page,
};

// Détecter le module qui contient la page active (pour ouvrir uniquement celui-ci par défaut)
$activeModule = null;
foreach ($sidebarModules as $modId => $mod) {
    foreach ($mod['sections'] as $secId => $sec) {
        if (isset($sec['items'][$activeSection])) {
            $activeModule = $modId;
            break 2;
        }
    }
}
if ($activeModule === null) $activeModule = 'planning'; // fallback : Planning ouvert

// Préférence de thème — TEMPORAIREMENT NEUTRALISÉE (clean-slate avril 2026).
// Le système de thèmes original (default/sombre/care) modifiait la structure
// (typo, tailles, layouts) en plus des couleurs. On le réactivera plus tard
// avec uniquement des overrides de couleurs sur les tokens @theme Tailwind.
// La page /apparence sauvegarde toujours la préférence en DB pour la restaurer.
$themePref = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$admin['id']]) ?: 'default';
$themeBodyClass = 'theme-care'; // forcé : pas de switch structurel
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1A1A1A">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/newspocspace/manifest.json">
<link rel="apple-touch-icon" href="/newspocspace/assets/icons/icon-192x192.png">
<base href="/newspocspace/admin/">
<title>Admin — SpocSpace</title>
<!-- Clean slate Tailwind/Spocspace Care : ZÉRO Bootstrap, ZÉRO ancien CSS. Tout le visuel passe par Tailwind. -->
<?php include __DIR__ . '/../tailwind-config.php'; ?>
<!-- Styles shell admin non-utility-Tailwind (scrollbar custom + mini sidebar + modal Raccourcis) -->
<link rel="stylesheet" href="/newspocspace/admin/assets/css/admin-shell.css?v=<?= APP_VERSION ?>">
<!-- Styles dédiés à la page Planning (chargé en permanence pour éviter le FOUC quand on entre sur /admin/planning via SPA) -->
<link rel="stylesheet" href="/newspocspace/admin/assets/css/planning.css?v=<?= APP_VERSION ?>">
</head>
<body class="<?= h($themeBodyClass) ?>">

<div class="lg:flex min-h-screen">

<!-- BACKDROP (mobile) — JS toggle .show -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-ink/60 z-30 hidden [&.show]:block lg:!hidden"></div>

<!-- SIDEBAR — admin-sidebar gardé en hook (id seul suffirait, classe gardée pour compat). .mini = mode rétracté desktop (toggle persisté en localStorage 'ss_sidebar_mini'). -->
<aside id="adminSidebar" class="admin-sidebar
  fixed lg:sticky lg:top-0 inset-y-0 left-0 z-40
  w-60 h-screen overflow-y-auto shrink-0
  bg-sidebar-grad text-sb-text font-body
  p-[18px] flex flex-col gap-7
  -translate-x-full lg:translate-x-0 [&.open]:translate-x-0
  transition-[transform,width,padding] duration-200">

  <!-- ── Brand : logo "S" gradient + Spocspace + EMS PLATFORM + bouton toggle ── -->
  <div class="flex items-center justify-between gap-2 shrink-0">
    <a href="<?= admin_url() ?>" class="flex items-center gap-2.5 px-1 group min-w-0" title="Tableau de bord">
      <img src="/newspocspace/ss-white-logo.png" alt="Spocspace" class="w-[34px] h-[34px] rounded-[9px] shrink-0 object-contain">
      <div class="sidebar-brand-text min-w-0">
        <div class="text-xl font-semibold text-white tracking-[-0.02em] leading-tight truncate">Spocspace</div>
        <div class="text-[10.5px] text-sb-sub tracking-[0.12em] uppercase mt-0.5 font-medium">EMS Platform</div>
      </div>
    </a>
    <button id="sidebarToggleBtn" type="button"
            class="text-sb-text hover:text-white p-1.5 rounded-md hover:bg-white/[0.06] transition-colors shrink-0"
            title="Réduire le menu" aria-label="Basculer mini sidebar">
      <!-- Icône panel-left-collapse (rectangle + ligne verticale gauche) -->
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <line x1="9" y1="3" x2="9" y2="21"/>
      </svg>
    </button>
  </div>

  <!-- ── Navigation 3 niveaux : Module → Section → Item — scrollbar custom dans le <style> du <head> ── -->
  <nav class="sidebar-nav flex-1 flex flex-col gap-0.5 overflow-y-auto -mx-[2px] -my-1 py-1 pr-1">
    <?php foreach ($sidebarModules as $modId => $mod):
      $modCatId  = 'mod-' . $modId;
      $isActiveModule = ($modId === $activeModule);
    ?>
    <!-- ─── Module (niveau 1) ─── -->
    <div class="sidebar-module flex items-center gap-2.5 mt-3 first:mt-0 px-2 py-2 rounded-lg cursor-pointer select-none transition-colors
                <?= $isActiveModule ? 'bg-white/[0.05] text-white' : 'text-sb-text hover:bg-white/[0.03] hover:text-sb-text-hover' ?>
                <?= $isActiveModule ? '' : 'cat-collapsed' ?>"
         data-cat-toggle="<?= $modCatId ?>">
      <div class="w-7 h-7 rounded-md grid place-items-center shrink-0 transition-colors
                  <?= $isActiveModule ? 'bg-teal-700/60 text-[#7dd3a8]' : 'bg-white/[0.04] text-sb-sub' ?>">
        <?= ss_icon($mod['icon'], 'w-4 h-4') ?>
      </div>
      <span class="sidebar-module-label text-[13.5px] font-semibold flex-1 truncate tracking-[-0.005em]"><?= h($mod['label']) ?></span>
      <svg class="sidebar-cat-chevron w-3.5 h-3.5 opacity-50 transition-transform shrink-0
                  [.cat-collapsed_&]:-rotate-90"
           viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </div>

    <!-- ─── Sections du module (niveau 2 + 3) ─── -->
    <div class="sidebar-module-body flex flex-col gap-0.5 pl-2 mb-1 [&.collapsed]:hidden
                <?= $isActiveModule ? '' : 'collapsed' ?>"
         data-cat-body="<?= $modCatId ?>">

      <?php foreach ($mod['sections'] as $secId => $sec): ?>
      <!-- Section (niveau 2) -->
      <div class="sidebar-cat flex items-center justify-between text-[10px] tracking-[0.14em] uppercase text-sb-section px-2.5 mb-1 mt-3 first:mt-2 font-semibold cursor-pointer select-none hover:text-sb-text-hover transition-colors"
           data-cat-toggle="<?= $secId ?>">
        <span><?= h($sec['label']) ?></span>
        <svg class="sidebar-cat-chevron w-3 h-3 opacity-60 transition-transform [.cat-collapsed_&]:-rotate-90"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <div class="sidebar-cat-items flex flex-col gap-0.5 [&.collapsed]:hidden" data-cat-body="<?= $secId ?>">
        <?php foreach ($sec['items'] as $key => $item):
          $hasBadge = !empty($item['badge']);
          $badgeTone = $item['badge_tone'] ?? 'warn';
          // Badges sidebar : pills pleines (background coloré + texte blanc, pas de bordure)
          $badgeClasses = match ($badgeTone) {
              'info'   => 'bg-info text-white',
              'danger' => 'bg-danger text-white',
              'ok'     => 'bg-ok text-white',
              default  => 'bg-warm text-white',
          };
        ?>
        <!-- Item (niveau 3) -->
        <a href="<?= admin_url($key) ?>"
           class="sidebar-link relative flex items-center gap-3 px-2.5 py-1.5 rounded-lg text-[13px] font-normal text-sb-text hover:bg-white/[0.04] hover:text-sb-text-hover transition-colors
                  <?= $activeSection === $key ? 'active pl-[15px] bg-[#7dd3a8]/[0.12] !text-white font-medium before:content-[\'\'] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-[3px] before:h-4 before:bg-[#7dd3a8] before:rounded-[3px]' : '' ?>"
           title="<?= h($item['label']) ?>" <?= in_array($key, ['messages', 'email-externe']) ? 'data-sidebar-badge="' . $key . '"' : '' ?>>
          <?= ss_icon($item['icon'], 'w-4 h-4 opacity-85 shrink-0') ?>
          <span class="nav-label flex-1 truncate"><?= h($item['label']) ?></span>
          <?php if ($hasBadge && $item['badge'] > 0): ?>
          <span class="ml-auto text-[10px] font-mono font-bold rounded-full px-1.5 py-px shrink-0 <?= $badgeClasses ?>"<?= $key === 'messages' ? ' id="sidebarMsgBadge"' : '' ?>><?= (int) $item['badge'] ?></span>
          <?php elseif ($key === 'messages'): ?>
          <span id="sidebarMsgBadge" class="ml-auto text-[10px] font-mono font-bold bg-info text-white rounded-full px-1.5 py-px shrink-0" style="display:none"></span>
          <?php elseif ($key === 'email-externe'): ?>
          <span id="sidebarEmailBadge" class="ml-auto text-[10px] font-mono font-bold bg-info text-white rounded-full px-1.5 py-px shrink-0" style="display:none"></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <!-- ── Footer : actions externes + version ── -->
  <div class="sidebar-footer mt-auto flex flex-col gap-1.5 shrink-0 pt-3 border-t border-white/[0.07]">

    <!-- Liens vers SpocCare et le portail collaborateur -->
    <a href="/spoccare/" class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-lg text-[12.5px] font-normal text-sb-text hover:bg-white/[0.04] hover:text-sb-text-hover transition-colors" title="SpocCare — Résidents & Soins">
      <svg class="w-4 h-4 opacity-85 shrink-0 text-[#7dd3a8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
      </svg>
      <span class="nav-label flex-1 truncate">SpocCare</span>
    </a>
    <a href="/newspocspace/" target="_blank" class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-lg text-[12.5px] font-normal text-sb-text hover:bg-white/[0.04] hover:text-sb-text-hover transition-colors" title="Portail collaborateur (nouvel onglet)">
      <svg class="w-4 h-4 opacity-85 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
        <polyline points="15 3 21 3 21 9"/>
        <line x1="10" y1="14" x2="21" y2="3"/>
      </svg>
      <span class="nav-label flex-1 truncate">Portail collaborateur</span>
    </a>

    <!-- Row version + shortcuts -->
    <div class="sidebar-bottom-row flex items-center justify-between px-2.5 pt-1 mt-1">
      <span class="nav-label text-[10.5px] text-sb-muted font-mono">SpocSpace v<?= APP_SEMVER ?></span>
      <button id="sidebarShortcutsBtn" class="sidebar-shortcuts-btn text-sb-text hover:text-white p-1 rounded hover:bg-white/[0.06] transition-colors" title="Raccourcis clavier (?)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="6" width="20" height="12" rx="2"/>
          <path d="M7 10h.01"/><path d="M11 10h.01"/><path d="M15 10h.01"/><path d="M19 10h.01"/>
          <path d="M7 14h10"/>
        </svg>
      </button>
    </div>
  </div>
</aside>

<!-- MAIN — wrapper flex column qui contient topbar + page -->
<div id="adminMain" class="admin-main flex-1 min-w-0 min-h-screen flex flex-col">
  <!-- TOP BAR -->
  <header class="admin-topbar bg-surface border-b border-line h-16 px-4 lg:px-6 flex items-center gap-3 sticky top-0 z-20 backdrop-blur supports-[backdrop-filter]:bg-surface/90">
    <!-- Hamburger mobile -->
    <button id="mobileToggle" type="button" class="topbar-hamburger lg:hidden p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors bg-transparent border-0" title="Menu">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <!-- Page title -->
    <h5 class="topbar-title text-lg font-semibold text-ink tracking-[-0.01em] truncate"><?= h($pageLabels[$page] ?? 'Admin') ?></h5>

    <!-- Search (centre) — Spocspace global search admin -->
    <div id="topbarSearch" class="topbar-search relative flex-1 max-w-xl mx-auto hidden md:block">
      <div id="topbarSearchBar" class="ss-gs-bar relative flex items-center gap-2.5 bg-surface-2 border border-line rounded-xl px-3.5 py-2 transition-all">
        <svg class="w-[18px] h-[18px] text-muted shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="text" id="topbarSearchInput" autocomplete="off"
               placeholder="Rechercher partout… (collaborateurs, résidents, documents, pages)"
               class="flex-1 bg-transparent border-0 outline-none text-sm text-ink placeholder:text-muted font-body min-w-0">
        <div class="ss-gs-kbd flex items-center gap-1 shrink-0">
          <kbd class="font-mono text-[10.5px] font-semibold text-muted bg-surface border border-line-2 rounded px-1.5 py-px">⌘</kbd>
          <kbd class="font-mono text-[10.5px] font-semibold text-muted bg-surface border border-line-2 rounded px-1.5 py-px">K</kbd>
        </div>
        <button type="button" id="adminSearchClear" class="ss-gs-clear admin-search-clear w-6 h-6 rounded-md text-muted hover:bg-surface-3 hover:text-ink-2 items-center justify-center shrink-0 transition-colors bg-transparent border-0" aria-label="Effacer">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div id="topbarSearchResults" class="ss-gs-panel topbar-search-results absolute left-0 right-0 top-full mt-2 bg-surface border border-line rounded-2xl shadow-sp-lg max-h-[480px] overflow-hidden z-30 flex flex-col"></div>
    </div>

    <!-- Actions droite -->
    <div class="topbar-right flex items-center gap-1 ml-auto">
      <a href="<?= admin_url('messages') ?>" id="topbarMsgNotif" class="topbar-icon-btn relative p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors" title="Messagerie">
        <?= ss_icon('chat-dots', 'w-5 h-5') ?>
        <span id="topbarMsgBadge" class="topbar-notif-badge absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-teal-600 text-white text-[10px] font-mono font-bold flex items-center justify-center" style="display:none"></span>
      </a>
      <a href="<?= admin_url('email-externe') ?>" id="topbarEmailNotif" class="topbar-icon-btn relative p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors" title="Email">
        <?= ss_icon('envelope', 'w-5 h-5') ?>
        <span id="topbarEmailBadge" class="topbar-notif-badge absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-teal-600 text-white text-[10px] font-mono font-bold flex items-center justify-center" style="display:none"></span>
      </a>
      <a href="<?= admin_url('contacts') ?>" id="topbarContactsBtn" class="topbar-icon-btn hidden sm:inline-flex p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors" title="Contacts">
        <?= ss_icon('person-rolodex', 'w-5 h-5') ?>
      </a>
      <a href="<?= admin_url('annuaire') ?>" id="topbarAnnuaireBtn" class="topbar-icon-btn hidden sm:inline-flex p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors" title="Annuaire téléphonique">
        <?= ss_icon('telephone', 'w-5 h-5') ?>
      </a>
      <button id="ztInstallBtn" type="button" class="topbar-icon-btn p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors bg-transparent border-0" title="Installer l'application" style="display:none">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </button>
      <button id="immersiveToggle" type="button" class="topbar-icon-btn hidden sm:inline-flex p-2 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors bg-transparent border-0" title="Plein écran">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 00-2 2v3"/><path d="M21 8V5a2 2 0 00-2-2h-3"/><path d="M3 16v3a2 2 0 002 2h3"/><path d="M16 21h3a2 2 0 002-2v-3"/></svg>
      </button>
<?php
  $fonctionCode = '';
  if (!empty($admin['fonction_id'])) {
      $fonctionCode = Db::getOne("SELECT code FROM fonctions WHERE id = ?", [$admin['fonction_id']]) ?: '';
  }
  $roleLabel = $fonctionCode ?: ($admin['role'] === 'responsable' ? 'RUV' : ucfirst($admin['role'] ?? 'Admin'));
?>
      <div class="topbar-user hidden sm:flex flex-col items-end leading-tight pl-3 ml-1 border-l border-line">
        <span class="topbar-user-name text-[12.5px] font-medium text-ink truncate max-w-[160px]"><?= h(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')) ?></span>
        <span class="topbar-user-role text-[10.5px] text-muted-2 capitalize"><?= h($roleLabel) ?></span>
      </div>
      <a href="<?= admin_url() ?>logout" class="topbar-icon-btn topbar-logout p-2 rounded-lg text-muted hover:bg-danger-bg hover:text-danger transition-colors" title="Déconnexion">
        <?= ss_icon('power', 'w-5 h-5') ?>
      </a>
    </div>
  </header>

<!-- Bootstrap JS retiré : appels bootstrap.Modal des modales (session expirée, confirm, prompt, raccourcis) seront migrés au cas par cas en JS natif/Tailwind -->
<script nonce="<?= $cspNonce ?>" src="/newspocspace/admin/assets/js/url-manager.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/newspocspace/admin/assets/js/helpers.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/newspocspace/admin/assets/js/zerda-select.js?v=<?= APP_VERSION ?>"></script>
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
  import * as callUi from '/newspocspace/assets/js/call-ui.js';
  callUi.initIncomingPoll();
  window.ssStartCall = callUi.startCall;
</script>

  <!-- PAGE CONTENT -->
  <div id="adminContent" class="admin-content flex-1 min-w-0 min-h-0 p-4 lg:p-6">
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
        echo '<div class="bg-danger-bg border border-danger-line text-danger px-4 py-3 rounded-lg m-3"><strong>Erreur&nbsp;:</strong> ' . h($pageError) . '</div>';
    } else {
        echo $pageOutput;
    }
    ?>
  </div>
</div>
</div><!-- /lg:flex min-h-screen -->

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
    banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><span>Vous utilisez un mot de passe temporaire. Changez-le avant <strong id="tempPwdCountdown"></strong></span><a href="/newspocspace/profile" style="color:#fff;background:rgba(255,255,255,.2);padding:4px 14px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.82rem;white-space:nowrap;"><i class="bi bi-key"></i> Modifier</a>';
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
    var BASE = '/newspocspace/admin/';
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
          <a href="/newspocspace/admin/logout" class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</a>
          <a href="/newspocspace/login" class="btn btn-sm btn-primary" style="border-radius:8px;background:var(--cl-accent,#bcd2cb);border-color:var(--cl-accent,#bcd2cb);color:#2d4a43"><i class="bi bi-box-arrow-in-right me-1"></i>Connexion</a>
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
            const res = await fetch('/newspocspace/admin/api.php', {
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

<script nonce="<?= $cspNonce ?>" src="/newspocspace/admin/assets/js/admin.js?v=<?= APP_VERSION ?>"></script>

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
        if (!editorModule) editorModule = await import('/newspocspace/assets/js/rich-editor.js');
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
            const resp = await fetch('/newspocspace/admin/api.php', {
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
<!-- ═══ MODAL: Raccourcis clavier (Tailwind natif + admin-shell.css) ═══ -->
<div id="shortcutsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-5 bg-ink/35 backdrop-blur-sm" data-modal role="dialog" aria-labelledby="scModalTitle">
  <div class="sc-modal-card">

    <!-- Header gradient teal + titre + actions -->
    <div class="sc-modal-header">
      <div class="sc-title-wrap">
        <div class="sc-modal-icon">
          <?= ss_icon('keyboard', 'w-5 h-5') ?>
        </div>
        <div>
          <h2 class="sc-modal-title" id="scModalTitle">Raccourcis clavier</h2>
          <div class="sc-modal-subtitle">Personnalisez votre navigation Spocspace</div>
        </div>
      </div>
      <div class="sc-modal-actions">
        <button id="scResetBtn" type="button" class="btn-sc-reset" title="Réinitialiser tous les raccourcis">
          <?= ss_icon('arrow-rotate-ccw', 'w-3.5 h-3.5') ?>
          <span>Réinitialiser</span>
        </button>
        <button data-modal-close type="button" class="btn-sc-close" aria-label="Fermer">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
    </div>

    <!-- Info bar (instructions) -->
    <div class="sc-modal-info">
      <?= ss_icon('info-circle', 'w-3.5 h-3.5') ?>
      <span>Cliquez sur un raccourci pour le modifier. Appuyez sur la combinaison souhaitée (<strong>Ctrl</strong> / <strong>Alt</strong> / <strong>Shift</strong> + touche).</span>
    </div>

    <!-- Body : sections + raccourcis (rendus par JS dans #scList) -->
    <div class="sc-modal-body">
      <div id="scList"></div>
    </div>

    <!-- Footer -->
    <div class="sc-modal-footer">
      <div class="sc-footer-hint">
        <span>Appuyez sur</span>
        <span class="kbd result">?</span>
        <span>n'importe où pour afficher ce panneau</span>
      </div>
      <button data-modal-close type="button" class="btn-sc-fermer">Fermer</button>
    </div>

  </div>
</div>

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

    // ── Default shortcuts definition (icon = SVG inline rendu dans le titre de section) ──
    const ICON_NAV = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>';
    const ICON_TOOLS = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>';
    const ICON_BOLT = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>';

    const SHORTCUTS_DEF = [
        { group: 'Navigation', icon: ICON_NAV, items: [
            { id: 'nav_dashboard',    label: 'Tableau de bord',       default: 'Alt+D',     action: () => goTo('/newspocspace/admin/') },
            { id: 'nav_planning',     label: 'Planning',              default: 'Alt+P',     action: () => goTo('/newspocspace/admin/planning') },
            { id: 'nav_users',        label: 'Collaborateurs',        default: 'Alt+U',     action: () => goTo('/newspocspace/admin/users') },
            { id: 'nav_residents',    label: 'Résidents',             default: 'Alt+R',     action: () => goTo('/newspocspace/admin/residents') },
            { id: 'nav_absences',     label: 'Absences',              default: 'Alt+A',     action: () => goTo('/newspocspace/admin/absences') },
            { id: 'nav_desirs',       label: 'Désirs',                default: 'Alt+W',     action: () => goTo('/newspocspace/admin/desirs') },
            { id: 'nav_vacances',     label: 'Vacances',              default: 'Alt+V',     action: () => goTo('/newspocspace/admin/vacances') },
            { id: 'nav_messages',     label: 'Messagerie',            default: 'Alt+M',     action: () => goTo('/newspocspace/admin/messages') },
            { id: 'nav_famille',      label: 'Espace Famille',        default: 'Alt+F',     action: () => goTo('/newspocspace/admin/famille') },
            { id: 'nav_documents',    label: 'Documents',             default: 'Alt+O',     action: () => goTo('/newspocspace/admin/documents') },
            { id: 'nav_annuaire',     label: 'Annuaire téléphonique', default: 'Alt+H',     action: () => goTo('/newspocspace/admin/annuaire') },
        ]},
        { group: 'Outils', icon: ICON_TOOLS, items: [
            { id: 'nav_todos',        label: 'Tâches (Todos)',        default: 'Alt+T',     action: () => goTo('/newspocspace/admin/todos') },
            { id: 'nav_notes',        label: 'Notes',                 default: 'Alt+N',     action: () => goTo('/newspocspace/admin/notes') },
            { id: 'nav_pv',           label: 'Procès-Verbaux',        default: 'Alt+J',     action: () => goTo('/newspocspace/admin/pv') },
            { id: 'nav_sondages',     label: 'Sondages',              default: 'Alt+G',     action: () => goTo('/newspocspace/admin/sondages') },
            { id: 'nav_stats',        label: 'Statistiques',          default: 'Alt+S',     action: () => goTo('/newspocspace/admin/stats') },
        ]},
        { group: 'Actions rapides', icon: ICON_BOLT, items: [
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

    // ── Modal show/hide (Tailwind natif, pas de Bootstrap) ──
    function showShortcutsModal() {
        var m = document.getElementById('shortcutsModal');
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    function hideShortcutsModal() {
        var m = document.getElementById('shortcutsModal');
        if (!m) return;
        m.classList.add('hidden');
        m.classList.remove('flex');
        // Sortie du mode recording si actif au moment de la fermeture
        if (recordingEl) cancelRecording();
    }
    // Wire up close buttons + backdrop click (conteneur extérieur fixe inset-0)
    (function() {
        var m = document.getElementById('shortcutsModal');
        if (!m) return;
        m.querySelectorAll('[data-modal-close]').forEach(function(btn) {
            btn.addEventListener('click', hideShortcutsModal);
        });
        m.addEventListener('click', function(e) {
            if (e.target === m) hideShortcutsModal(); // clic sur l'overlay (hors carte)
        });
    })();

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

    // Génère le markup d'une combinaison : modifier kbd + "+" + result kbd
    function comboToKeys(str) {
        if (!str) return '<span class="sc-key-none">Non défini</span>';
        var parts = str.split('+').map(function(s) { return s.trim(); }).filter(Boolean);
        var modifiers = ['Ctrl', 'Alt', 'Shift', 'Meta', 'Cmd'];
        var html = '';
        parts.forEach(function(p, i) {
            if (i > 0) html += '<span class="key-plus">+</span>';
            var isMod = modifiers.includes(p);
            html += '<span class="kbd ' + (isMod ? 'modifier' : 'result') + '">' + p + '</span>';
        });
        return html;
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
            html += '<div class="sc-section">';
            html += '<div class="sc-section-title"><span class="sc-section-icon">' + (group.icon || '') + '</span>' + group.group + '</div>';
            group.items.forEach(function(item) {
                var combo = getStrForItem(item);
                html += '<div class="sc-row" data-sc-id="' + item.id + '">'
                    + '<span class="sc-name">' + item.label + '</span>'
                    + '<div class="sc-keys">' + comboToKeys(combo) + '</div>'
                    + '</div>';
            });
            html += '</div>';
        });

        container.innerHTML = html;

        // Click ligne entière → entre en recording
        container.querySelectorAll('.sc-row').forEach(function(el) {
            el.addEventListener('click', function() { startRecording(el); });
        });
    }

    // ── Recording mode (.recording sur la <div class="sc-row">) ──
    var recordingEl = null; // c'est la <div class="sc-row"> qui prend la classe .recording
    var recordingId = null;

    function getKeysWrap(rowEl) {
        return rowEl ? rowEl.querySelector('.sc-keys') : null;
    }

    function startRecording(rowEl) {
        if (recordingEl) cancelRecording();
        recordingEl = rowEl;
        recordingId = rowEl.dataset.scId;
        rowEl.classList.add('recording');
        var keys = getKeysWrap(rowEl);
        if (keys) keys.innerHTML = '<span class="key-plus">Appuyez…</span>';
    }

    function stopRecording(combo) {
        if (!recordingEl) return;
        var str = formatCombo(combo);
        var custom = loadCustom();
        custom[recordingId] = str;
        saveCustom(custom);
        var row = recordingEl;
        var keys = getKeysWrap(row);
        row.classList.remove('recording');
        if (keys) keys.innerHTML = comboToKeys(str);
        recordingEl = null;
        recordingId = null;
    }

    function cancelRecording() {
        if (!recordingEl) return;
        var item = findItem(recordingId);
        var row = recordingEl;
        var keys = getKeysWrap(row);
        row.classList.remove('recording');
        if (keys && item) keys.innerHTML = comboToKeys(getStrForItem(item));
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

        // Escape : prioritaire — annule recording si actif, sinon ferme le modal s'il est ouvert
        if (e.key === 'Escape') {
            if (recordingEl) { e.preventDefault(); cancelRecording(); return; }
            var modalEl = document.getElementById('shortcutsModal');
            if (modalEl && !modalEl.classList.contains('hidden')) {
                e.preventDefault();
                hideShortcutsModal();
                return;
            }
        }

        // Recording mode
        if (recordingEl) {
            e.preventDefault();
            e.stopPropagation();
            if (e.key === 'Escape') { cancelRecording(); return; }
            if (e.key === 'Backspace' || e.key === 'Delete') {
                // Effacer le raccourci → "Non défini"
                var custom = loadCustom();
                custom[recordingId] = '';
                saveCustom(custom);
                var row = recordingEl;
                var keys = getKeysWrap(row);
                row.classList.remove('recording');
                if (keys) keys.innerHTML = '<span class="sc-key-none">Non défini</span>';
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
        navigator.serviceWorker.register('/newspocspace/sw.js', { scope: '/newspocspace/' })
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
