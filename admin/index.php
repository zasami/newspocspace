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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' blob: http://localhost:5876 http://localhost:5877 http://localhost:5878 http://localhost:59876 https://api.deepgram.com wss://api.deepgram.com; worker-src 'self' blob:; media-src 'self' blob:;");

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
<!-- Tailwind CSS is NOT loaded in admin: admin uses Bootstrap + admin.css only.
     The css_mode setting applies to the front-end app, not the admin panel. -->
</head>
<body>

<!-- Immersive mode restore pill -->
<button class="zt-immersive-pill" id="immersivePill" title="Afficher la sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>

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
    <div class="sidebar-version" style="padding:6px 16px;font-size:0.7rem;color:var(--zt-text-muted,#999);opacity:.7">
      <span class="nav-label">zerdaTime v<?= APP_SEMVER ?></span>
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
      <button class="topbar-icon-btn" id="immersiveToggle" title="Mode immersif">
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
    <?php require $pageFile; ?>
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

// Mode immersif (CSS-only, persiste via localStorage)
(function() {
    const btn = document.getElementById('immersiveToggle');
    const pill = document.getElementById('immersivePill');
    const KEY = 'zt_immersive';

    function setImmersive(on) {
        document.body.classList.toggle('zt-immersive', on);
        localStorage.setItem(KEY, on ? '1' : '0');
        const icon = btn?.querySelector('i');
        if (icon) icon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        if (btn) btn.title = on ? 'Quitter le mode immersif' : 'Mode immersif';
    }

    btn?.addEventListener('click', () => setImmersive(!document.body.classList.contains('zt-immersive')));
    pill?.addEventListener('click', () => setImmersive(false));

    // Restore on page load
    if (localStorage.getItem(KEY) === '1') setImmersive(true);
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
</body>
</html>
