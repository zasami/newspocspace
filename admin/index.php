<?php
/**
 * zerdaTime Admin Panel - Server-rendered with retractable sidebar
 */
require_once __DIR__ . '/../init.php';

/**
 * Build a clean admin URL
 * @param string $page  Page name (empty or 'dashboard' → /zerdatime/admin/)
 * @param string $id    Optional ID segment
 * @param array  $extra Optional query parameters
 */
function admin_url(string $page = '', string $id = '', array $extra = []): string {
    $base = '/zerdatime/admin';
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
if (empty($_SESSION['zt_user']) || !in_array($_SESSION['zt_user']['role'], ['admin', 'direction', 'responsable'])) {
    header('Location: /zerdatime/login');
    exit;
}

$admin = $_SESSION['zt_user'];
$csrfToken = $_SESSION['zt_csrf_token'] ?? '';

// CSP nonce for inline scripts
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);
function nonce(): string { return ' nonce="' . CSP_NONCE . '"'; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' blob: http://localhost:5876 http://localhost:5877 http://localhost:5878 http://localhost:59876 https://api.deepgram.com wss://api.deepgram.com; worker-src 'self' blob:; media-src 'self' blob:;");

// Load EMS logo + name for sidebar
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'zerdaTime';


// Redirect old-style URLs (index.php?page=…) → clean URLs
if (str_contains($_SERVER['REQUEST_URI'], 'index.php?page=') && isset($_GET['page'])) {
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
    header('Location: /zerdatime/login');
    exit;
}

// Current page
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'etablissement', 'users', 'user-edit', 'user-detail', 'planning', 'modules', 'horaires', 'desirs', 'absences', 'vacances', 'changements', 'stats', 'besoins', 'messages', 'alertes', 'config-ia', 'repartition', 'affichage-planning', 'pv', 'pv-detail', 'pv-record', 'sondages', 'sondage-edit', 'documents', 'fiches-salaire', 'import-export', 'todos', 'notes', 'roadmap', 'residents', 'famille', 'cuisine', 'reservations'];
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
    'famille'      => 'Rechercher un résident...',
];
$topbarPlaceholder = $topbarPlaceholders[$page] ?? 'Rechercher un collaborateur...';

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
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
    'residents'     => 'Résidents',
    'famille'       => 'Espace Famille',
    'cuisine'       => 'Cuisine — Menus',
    'reservations'  => 'Réservations repas',
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
        'title' => ($pageLabels[$page] ?? 'Admin') . ' — zerdaTime',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sidebar with categories
$sidebarCategories = [
    'general' => [
        'label' => 'Général',
        'items' => [
            'dashboard'     => ['label' => 'Tableau de bord',  'icon' => 'speedometer2'],
            'etablissement' => ['label' => 'Établissement',    'icon' => 'hospital'],
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
            'affichage-planning' => ['label' => 'Affichage',     'icon' => 'sliders'],
            'config-ia'  => ['label' => 'Config IA',             'icon' => 'cpu'],
        ],
    ],
    'config' => [
        'label' => 'Configuration',
        'items' => [
            'users'    => ['label' => 'Collaborateurs',      'icon' => 'people'],
            'modules'  => ['label' => 'Modules & Unités',    'icon' => 'building'],
            'horaires' => ['label' => 'Types d\'horaires',   'icon' => 'clock'],
            'residents' => ['label' => 'Résidents',          'icon' => 'person-badge'],
            'famille'   => ['label' => 'Espace Famille',     'icon' => 'house-heart'],
            'cuisine'      => ['label' => 'Menus',               'icon' => 'egg-fried'],
            'reservations' => ['label' => 'Réservations repas',  'icon' => 'calendar-check'],
        ],
    ],
    'outils' => [
        'label' => 'Outils',
        'items' => [
            'todos'    => ['label' => 'Tâches',                'icon' => 'check2-square'],
            'notes'    => ['label' => 'Notes',                 'icon' => 'journal-text'],
            'pv'       => ['label' => 'Procès-Verbaux',       'icon' => 'file-earmark-text'],
            'sondages' => ['label' => 'Sondages',             'icon' => 'clipboard2-check'],
        ],
    ],
    'autres' => [
        'label' => 'Autres',
        'items' => [
            'documents' => ['label' => 'Documents',             'icon' => 'folder2'],
            'fiches-salaire' => ['label' => 'Fiches de salaire', 'icon' => 'receipt'],
            'messages' => ['label' => 'Messagerie',            'icon' => 'envelope'],
            'alertes'  => ['label' => 'Alertes',               'icon' => 'megaphone'],
            'stats'    => ['label' => 'Statistiques',        'icon' => 'graph-up'],
            'import-export' => ['label' => 'Import / Export', 'icon' => 'arrow-down-up'],
            'roadmap'      => ['label' => 'Roadmap',          'icon' => 'rocket-takeoff'],
        ],
    ],
];

$activeSection = match($page) {
    'user-edit', 'user-detail' => 'users',
    default => $page,
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="/zerdatime/admin/">
<title>Admin — zerdaTime</title>
<link href="/zerdatime/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
<link href="/zerdatime/admin/assets/css/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/zerdatime/admin/assets/css/admin.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/zerdatime/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">
</head>
<body>

<!-- BACKDROP (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-header">
    <a href="<?= admin_url() ?>" class="sidebar-brand-link">
      <img src="/zerdatime/logo.png" alt="" class="brand-logo">
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
      <a href="<?= admin_url($key) ?>" class="sidebar-link <?= $activeSection === $key ? 'active' : '' ?>" title="<?= h($item['label']) ?>">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span class="nav-label"><?= h($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/zerdatime/" class="sidebar-link" target="_blank" title="Portail collaborateur">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="nav-label">Portail collaborateur</span>
    </a>
    <div class="sidebar-bottom-row" style="padding:6px 16px;font-size:0.7rem;color:var(--zt-text-muted,#999);opacity:.7;display:flex;align-items:center;justify-content:space-between">
      <span class="nav-label">zerdaTime v<?= APP_SEMVER ?></span>
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
      <input type="text" class="form-control form-control-sm" id="topbarSearchInput" placeholder="<?= h($topbarPlaceholder) ?>" autocomplete="off">
      <button type="button" class="admin-search-clear" id="adminSearchClear" style="display:none"><i class="bi bi-x-lg"></i></button>
      <div class="topbar-search-results" id="topbarSearchResults"></div>
    </div>
    <div class="topbar-right">
      <a href="<?= admin_url('messages') ?>" class="topbar-icon-btn" id="topbarEmailNotif" title="Messagerie">
        <i class="bi bi-envelope"></i>
        <span class="topbar-notif-badge" id="adminEmailBadge" style="display:none"></span>
      </a>
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

<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/vendor/bootstrap.bundle.min.js"></script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/url-manager.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/helpers.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/zerda-select.js?v=<?= APP_VERSION ?>"></script>

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
window.__ZT_ADMIN__ = {
  csrfToken: '<?= $csrfToken ?>',
  adminId: '<?= h($admin['id']) ?>',
  adminName: '<?= h(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')) ?>',
  mustChangePassword: <?= !empty($_SESSION['zt_must_change_password']) ? 'true' : 'false' ?>,
  tempPasswordExpires: <?= !empty($_SESSION['zt_temp_password_expires']) ? "'" . h($_SESSION['zt_temp_password_expires']) . "'" : 'null' ?>
};

// Temp password banner (admin)
if (window.__ZT_ADMIN__.mustChangePassword && window.__ZT_ADMIN__.tempPasswordExpires) {
    const exp = new Date(window.__ZT_ADMIN__.tempPasswordExpires.replace(' ', 'T'));
    const banner = document.createElement('div');
    banner.id = 'tempPwdBanner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#9B2C2C;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:12px;font-size:.88rem;box-shadow:0 2px 8px rgba(0,0,0,.2);';
    banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><span>Vous utilisez un mot de passe temporaire. Changez-le avant <strong id="tempPwdCountdown"></strong></span><a href="/zerdatime/profile" style="color:#fff;background:rgba(255,255,255,.2);padding:4px 14px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.82rem;white-space:nowrap;"><i class="bi bi-key"></i> Modifier</a>';
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
            localStorage.removeItem('zt_fullscreen');
        } else {
            document.documentElement.requestFullscreen().catch(function(){});
            localStorage.setItem('zt_fullscreen', '1');
        }
    });

    document.addEventListener('fullscreenchange', updateFsIcon);
    updateFsIcon();

    // ── SPA Router — navigation AJAX, fullscreen persists ──
    var contentEl = document.getElementById('adminContent');
    var titleEl = document.querySelector('.topbar-title');
    var BASE = '/zerdatime/admin/';
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
                if (pushState !== false) history.pushState({ page: page }, '', url);
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
.confirm-modal-icon.icon-danger  { background: rgba(220,38,38,0.08); color: var(--zt-red); }
.confirm-modal-icon.icon-warning { background: rgba(234,139,45,0.08); color: var(--zt-orange); }
.confirm-modal-icon.icon-success { background: rgba(22,163,74,0.08); color: var(--zt-green); }
.confirm-modal-icon.icon-info    { background: var(--cl-accent-bg); color: var(--cl-accent); }
.confirm-modal-icon.icon-primary { background: var(--cl-accent-bg); color: var(--cl-accent); }
#confirmModalBody { font-size: 0.9rem; color: var(--cl-text-secondary); }

/* Confirm modal overlay */
#confirmModal .modal-backdrop, .modal-backdrop { background: rgba(0,0,0,0.35) !important; }

/* Close button — always top-right corner in all modals */
.modal-header { position: relative; }
.modal-header .btn-close,
.modal-header .confirm-close-btn {
  position: absolute; top: 0.75rem; right: 0.75rem; z-index: 2;
  background: none; border: none; padding: 0.25rem;
  font-size: 1.15rem; color: var(--cl-text-secondary, #6B6B69);
  cursor: pointer; line-height: 1; border-radius: 6px;
  transition: background 0.15s, color 0.15s;
}
.modal-header .btn-close:hover,
.modal-header .confirm-close-btn:hover { background: rgba(0,0,0,0.06); color: var(--cl-text, #1A1A18); }

/* Confirm & Prompt modals always on top */
#confirmModal, #promptModal { z-index: 1070 !important; }

/* Dim overlay on modals behind confirm/prompt */
.zt-dim-overlay {
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

    // OK button
    const btnClass = {
      danger: 'btn-danger', warning: 'btn-warning',
      success: 'btn-success', info: 'btn-primary', primary: 'btn-primary'
    }[type] || 'btn-primary';
    okBtn.className = 'btn btn-sm ' + btnClass;
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
      if (m !== confirmEl && !m.querySelector('.zt-dim-overlay')) {
        const ov = document.createElement('div');
        ov.className = 'zt-dim-overlay';
        m.appendChild(ov);
      }
    });
    confirmEl.addEventListener('hidden.bs.modal', function onHide() {
      confirmEl.removeEventListener('hidden.bs.modal', onHide);
      document.querySelectorAll('.zt-dim-overlay').forEach(ov => ov.remove());
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
      if (m !== promptEl && !m.querySelector('.zt-dim-overlay')) {
        const ov = document.createElement('div');
        ov.className = 'zt-dim-overlay';
        m.appendChild(ov);
      }
    });

    promptEl.addEventListener('hidden.bs.modal', function onHide() {
      promptEl.removeEventListener('hidden.bs.modal', onHide);
      document.querySelectorAll('.zt-dim-overlay').forEach(ov => ov.remove());
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

<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/admin.js?v=<?= APP_VERSION ?>"></script>

<!-- ═══ GLOBAL COMPOSE PANEL ═══ -->
<div class="compose-panel" id="globalComposePanel">
  <div class="compose-panel-header" id="globalComposePanelHeader">
    <span class="compose-panel-title" id="globalComposePanelTitle">Nouveau message</span>
    <div class="compose-panel-header-actions">
      <button type="button" class="compose-panel-header-btn" id="globalComposeMinimize" title="Réduire"><i class="bi bi-dash-lg"></i></button>
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
  </div>
  <div class="compose-panel-footer">
    <button type="button" class="adm-email-btn" id="globalComposeSend"><i class="bi bi-send"></i> Envoyer</button>
    <div class="compose-panel-footer-right">
      <input type="file" id="globalComposeFile" multiple hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt,.csv">
      <button type="button" class="compose-panel-footer-btn" id="globalComposeAttach" title="Joindre"><i class="bi bi-paperclip"></i></button>
      <button type="button" class="compose-panel-footer-btn compose-panel-delete" id="globalComposeDiscard" title="Annuler"><i class="bi bi-trash3"></i></button>
    </div>
  </div>
  <div class="att-preview-list" id="globalAttPreviewList"></div>
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
        if (!editorModule) editorModule = await import('/zerdatime/assets/js/rich-editor.js');
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

        document.getElementById('globalComposeMinimize')?.addEventListener('click', () => {
            document.getElementById('globalComposePanel')?.classList.toggle('minimized');
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
            panel.classList.remove('open');
            setTimeout(() => { panel.classList.remove('open'); }, 300);
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
            fd.append('action', 'admin_upload_attachment');
            fd.append('file', file);
            const resp = await fetch('/zerdatime/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__ZT_ADMIN__?.csrfToken || '' },
                body: fd
            });
            const r = await resp.json();
            if (r.success && r.id) attachmentIds.push(r.id);
        }

        const res = await adminApiPost('admin_send_email', {
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
    const STORAGE_KEY = 'zt_shortcuts';

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
            { id: 'nav_dashboard',    label: 'Tableau de bord',       default: 'Alt+D',     action: () => goTo('/zerdatime/admin/') },
            { id: 'nav_planning',     label: 'Planning',              default: 'Alt+P',     action: () => goTo('/zerdatime/admin/planning') },
            { id: 'nav_users',        label: 'Collaborateurs',        default: 'Alt+U',     action: () => goTo('/zerdatime/admin/users') },
            { id: 'nav_residents',    label: 'Résidents',             default: 'Alt+R',     action: () => goTo('/zerdatime/admin/residents') },
            { id: 'nav_absences',     label: 'Absences',              default: 'Alt+A',     action: () => goTo('/zerdatime/admin/absences') },
            { id: 'nav_desirs',       label: 'Désirs',                default: 'Alt+W',     action: () => goTo('/zerdatime/admin/desirs') },
            { id: 'nav_vacances',     label: 'Vacances',              default: 'Alt+V',     action: () => goTo('/zerdatime/admin/vacances') },
            { id: 'nav_messages',     label: 'Messagerie',            default: 'Alt+M',     action: () => goTo('/zerdatime/admin/messages') },
            { id: 'nav_famille',      label: 'Espace Famille',        default: 'Alt+F',     action: () => goTo('/zerdatime/admin/famille') },
            { id: 'nav_documents',    label: 'Documents',             default: 'Alt+O',     action: () => goTo('/zerdatime/admin/documents') },
        ]},
        { group: 'Outils', items: [
            { id: 'nav_todos',        label: 'Tâches (Todos)',        default: 'Alt+T',     action: () => goTo('/zerdatime/admin/todos') },
            { id: 'nav_notes',        label: 'Notes',                 default: 'Alt+N',     action: () => goTo('/zerdatime/admin/notes') },
            { id: 'nav_pv',           label: 'Procès-Verbaux',        default: 'Alt+J',     action: () => goTo('/zerdatime/admin/pv') },
            { id: 'nav_sondages',     label: 'Sondages',              default: 'Alt+G',     action: () => goTo('/zerdatime/admin/sondages') },
            { id: 'nav_stats',        label: 'Statistiques',          default: 'Alt+S',     action: () => goTo('/zerdatime/admin/stats') },
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
</body>
</html>
