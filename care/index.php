<?php
/**
 * zerdaCare — Module soins & vie quotidienne
 * Sidebar + shell for resident-focused features
 */
require_once __DIR__ . '/../init.php';

// Auth check — all roles can access zerdaCare
if (empty($_SESSION['zt_user'])) {
    header('Location: /zerdatime/login');
    exit;
}

$user = $_SESSION['zt_user'];
$csrfToken = $_SESSION['zt_csrf_token'] ?? '';

// CSP nonce
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);
function nonce(): string { return ' nonce="' . CSP_NONCE . '"'; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self'; connect-src 'self' blob:; worker-src 'self' blob:; media-src 'self' blob:;");

// EMS config
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'zerdaCare';

// ── Routing ────────────────────────────────────────────
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard', 'residents', 'marquage', 'famille', 'menus',
    'reservations', 'protection',
];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

$pageLabels = [
    'dashboard'    => 'Tableau de bord',
    'residents'    => 'Résidents',
    'marquage'     => 'Marquage Lingerie',
    'famille'      => 'Espace Famille',
    'menus'        => 'Menus',
    'reservations' => 'Réservations repas',
    'protection'   => 'Suivi Protection',
];

$topbarPlaceholders = [
    'residents'    => 'Rechercher un résident...',
    'marquage'     => 'Rechercher un marquage...',
    'famille'      => 'Rechercher un résident...',
    'menus'        => 'Rechercher un menu...',
    'reservations' => 'Rechercher une réservation...',
    'protection'   => 'Rechercher une protection...',
];
$topbarPlaceholder = $topbarPlaceholders[$page] ?? 'Rechercher...';

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

$pageTitle = $pageLabels[$page] ?? 'zerdaCare';
$activeSection = $page;

// ── Sidebar categories ────────────────────────────────
$sidebarCategories = [
    'principal' => [
        'label' => 'Principal',
        'items' => [
            'dashboard' => ['label' => 'Tableau de bord', 'icon' => 'heart-pulse'],
        ],
    ],
    'residents' => [
        'label' => 'Résidents',
        'items' => [
            'residents'  => ['label' => 'Résidents',         'icon' => 'person-badge'],
            'famille'    => ['label' => 'Espace Famille',    'icon' => 'house-heart'],
            'protection' => ['label' => 'Suivi Protection',  'icon' => 'shield-check'],
        ],
    ],
    'quotidien' => [
        'label' => 'Vie quotidienne',
        'items' => [
            'marquage'     => ['label' => 'Marquage Lingerie',  'icon' => 'tags'],
            'menus'        => ['label' => 'Menus',              'icon' => 'egg-fried'],
            'reservations' => ['label' => 'Réservations repas', 'icon' => 'calendar-check'],
        ],
    ],
];

// ── SPA mode for AJAX page loads ──────────────────────
if (!empty($_GET['_spa'])) {
    ob_start();
    include $pageFile;
    $html = ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html'  => $html,
        'page'  => $page,
        'title' => $pageTitle . ' — zerdaCare',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper for clean URLs
function care_url(string $page = '', string $id = ''): string {
    $base = '/zerdatime/care/';
    if (!$page) return $base;
    if ($id) return $base . $page . '/' . $id;
    return $base . $page;
}

$roleLabel = $user['role'] === 'responsable' ? 'RUV' : ucfirst($user['role'] ?? '');
$fonctionCode = Db::getOne("SELECT code FROM fonctions WHERE id = ?", [$user['fonction_id'] ?? '']) ?: '';
if ($fonctionCode) $roleLabel = $fonctionCode;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#2d4a43">
<meta name="apple-mobile-web-app-capable" content="yes">
<base href="/zerdatime/care/">
<title><?= h($pageTitle) ?> — zerdaCare</title>
<link href="/zerdatime/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
<link href="/zerdatime/admin/assets/css/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/zerdatime/admin/assets/css/admin.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/zerdatime/care/assets/css/care.css?v=<?= APP_VERSION ?>">
</head>
<body>

<!-- BACKDROP (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-header">
    <a href="<?= care_url() ?>" class="sidebar-brand-link">
      <?php if ($emsLogo): ?>
        <img src="<?= h($emsLogo) ?>" alt="" class="brand-logo">
      <?php else: ?>
        <img src="/zerdatime/logo.png" alt="" class="brand-logo">
      <?php endif; ?>
      <span class="brand-text">zerdaCare</span>
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
      <a href="<?= care_url($key) ?>" class="sidebar-link <?= $activeSection === $key ? 'active' : '' ?>" title="<?= h($item['label']) ?>">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span class="nav-label"><?= h($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <?php if (in_array($user['role'], ['admin','direction','responsable'])): ?>
    <a href="/zerdatime/admin/" class="sidebar-link" title="Administration">
      <i class="bi bi-gear"></i>
      <span class="nav-label">Administration</span>
    </a>
    <?php endif; ?>
    <a href="/zerdatime/" class="sidebar-link" title="Portail collaborateur">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="nav-label">Portail collaborateur</span>
    </a>
    <div class="sidebar-bottom-row" style="padding:6px 16px;font-size:0.7rem;color:var(--zt-text-muted,#999);opacity:.7">
      <span class="nav-label">zerdaCare v<?= APP_SEMVER ?></span>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="admin-main" id="adminMain">
  <!-- TOP BAR -->
  <header class="admin-topbar">
    <div class="topbar-left">
      <button class="topbar-btn d-lg-none" id="mobileMenuBtn" title="Menu"><i class="bi bi-list"></i></button>
      <h5 class="topbar-title"><?= h($pageTitle) ?></h5>
    </div>
    <div class="topbar-center">
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="topbarSearchInput" placeholder="<?= h($topbarPlaceholder) ?>" autocomplete="off">
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-user">
        <span class="topbar-user-name"><?= h($user['prenom'] . ' ' . $user['nom']) ?></span>
        <span class="topbar-user-role"><?= h($roleLabel) ?></span>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <div class="admin-content" id="pageContent">
    <?php include $pageFile; ?>
  </div>
</div>

<!-- Confirm modal -->
<div class="modal fade" id="ztConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <div id="ztConfirmIcon" class="mb-3" style="font-size:2rem"></div>
        <h6 id="ztConfirmTitle" class="fw-bold"></h6>
        <p id="ztConfirmText" class="text-muted small mt-2"></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button class="btn btn-light btn-sm" id="ztConfirmCancel" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm" id="ztConfirmOk">Confirmer</button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/vendor/bootstrap.bundle.min.js"></script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/helpers.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/zerda-select.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>">
window.__ZT_CARE__ = {
    csrfToken: '<?= $csrfToken ?>',
    userId: '<?= $user['id'] ?>',
    userName: '<?= h($user['prenom'] . ' ' . $user['nom']) ?>',
    role: '<?= $user['role'] ?>'
};

// ── Care API helper ──
window.careApiPost = async function(action, data = {}) {
    data.action = action;
    try {
        const r = await fetch('/zerdatime/care/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.__ZT_CARE__.csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.csrf) window.__ZT_CARE__.csrfToken = j.csrf;
        return j;
    } catch (e) {
        return { success: false, message: 'Erreur de connexion' };
    }
};

// Also expose as adminApiPost for page compatibility
window.adminApiPost = window.careApiPost;
</script>
<script nonce="<?= $cspNonce ?>" src="/zerdatime/admin/assets/js/admin.js?v=<?= APP_VERSION ?>"></script>
<script nonce="<?= $cspNonce ?>">
// ── Confirm modal helper ──
window.adminConfirm = function(opts = {}) {
    return new Promise(resolve => {
        const m = document.getElementById('ztConfirmModal');
        document.getElementById('ztConfirmIcon').innerHTML = '<i class="bi ' + (opts.icon||'bi-question-circle') + '"></i>';
        document.getElementById('ztConfirmTitle').textContent = opts.title || 'Confirmer';
        document.getElementById('ztConfirmText').innerHTML = opts.text || '';
        const okBtn = document.getElementById('ztConfirmOk');
        okBtn.className = 'btn btn-sm btn-' + (opts.type === 'danger' ? 'danger' : 'primary');
        okBtn.textContent = opts.okText || 'Confirmer';
        const modal = new bootstrap.Modal(m);
        const cleanup = () => { okBtn.removeEventListener('click', onOk); m.removeEventListener('hidden.bs.modal', onHide); };
        const onOk = () => { cleanup(); modal.hide(); resolve(true); };
        const onHide = () => { cleanup(); resolve(false); };
        okBtn.addEventListener('click', onOk);
        m.addEventListener('hidden.bs.modal', onHide);
        modal.show();
    });
};
</script>
</body>
</html>
