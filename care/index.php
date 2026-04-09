<?php
/**
 * SpocCare — Module soins & vie quotidienne
 * Sidebar + shell for resident-focused features
 */
require_once __DIR__ . '/../init.php';

// Auth check — all roles can access SpocCare
if (empty($_SESSION['ss_user'])) {
    header('Location: /spocspace/login?redirect=/spoccare/');
    exit;
}

$user = $_SESSION['ss_user'];
$csrfToken = $_SESSION['ss_csrf_token'] ?? '';

// CSP nonce
$cspNonce = base64_encode(random_bytes(16));
define('CSP_NONCE', $cspNonce);
function nonce(): string { return ' nonce="' . CSP_NONCE . '"'; }
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' 'wasm-unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self'; connect-src 'self' blob: https://cdnjs.cloudflare.com; worker-src 'self' blob: https://cdnjs.cloudflare.com; media-src 'self' blob:;");

// EMS config
$emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'SpocCare';

// ── Routing ────────────────────────────────────────────
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard', 'residents', 'marquage', 'famille', 'menus',
    'reservations', 'protection', 'hygiene', 'wiki', 'wiki-edit',
    'annonces', 'annonce-edit',
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
    'hygiene'      => 'Produits Hygiène',
    'wiki'         => 'Base de connaissances',
    'wiki-edit'    => 'Éditeur Wiki',
    'annonces'     => 'Annonces officielles',
    'annonce-edit' => 'Éditeur Annonce',
];

$topbarPlaceholders = [
    'residents'    => 'Rechercher un résident...',
    'marquage'     => 'Rechercher un marquage...',
    'famille'      => 'Rechercher un résident...',
    'menus'        => 'Rechercher un menu...',
    'reservations' => 'Rechercher une réservation...',
    'protection'   => 'Rechercher une protection...',
    'wiki'         => 'Rechercher une page wiki...',
    'annonces'     => 'Rechercher une annonce...',
];
$topbarPlaceholder = $topbarPlaceholders[$page] ?? 'Rechercher...';

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

$pageTitle = $pageLabels[$page] ?? 'SpocCare';
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
            'hygiene'      => ['label' => 'Produits Hygiène',   'icon' => 'droplet'],
            'menus'        => ['label' => 'Menus',              'icon' => 'egg-fried'],
            'reservations' => ['label' => 'Réservations repas', 'icon' => 'calendar-check'],
        ],
    ],
    'documentation' => [
        'label' => 'Documentation',
        'items' => [
            'wiki' => ['label' => 'Base de connaissances', 'icon' => 'book'],
        ],
    ],
    'communication' => [
        'label' => 'Communication',
        'items' => [
            'annonces' => ['label' => 'Annonces officielles', 'icon' => 'megaphone', 'feature' => 'feature_annonces'],
        ],
    ],
];

// Filter care sidebar by feature toggles
$careFeatureRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key LIKE 'feature_%' AND config_value = '0'");
$careDisabled = array_column($careFeatureRows, 'config_key');
foreach ($sidebarCategories as $catId => &$cat) {
    foreach ($cat['items'] as $key => $item) {
        if (!empty($item['feature']) && in_array($item['feature'], $careDisabled)) {
            unset($cat['items'][$key]);
        }
    }
    if (empty($cat['items'])) unset($sidebarCategories[$catId]);
}
unset($cat);

// ── SPA mode for AJAX page loads ──────────────────────
if (!empty($_GET['_spa'])) {
    ob_start();
    include $pageFile;
    $html = ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html'  => $html,
        'page'  => $page,
        'title' => $pageTitle . ' — SpocCare',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper for clean URLs
function care_url(string $page = '', string $id = ''): string {
    $base = '/spoccare/';
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
<base href="/spoccare/">
<title><?= h($pageTitle) ?> — SpocCare</title>
<link href="/spocspace/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
<link href="/spocspace/admin/assets/css/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/admin/assets/css/admin.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/spocspace/care/assets/css/care.css?v=<?= APP_VERSION ?>">
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
        <img src="/spocspace/logo.png" alt="" class="brand-logo">
      <?php endif; ?>
      <span class="brand-text">SpocCare</span>
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
    <a href="/spocspace/admin/" class="sidebar-link" title="Administration">
      <i class="bi bi-gear"></i>
      <span class="nav-label">Administration</span>
    </a>
    <?php endif; ?>
    <a href="/spocspace/" class="sidebar-link" title="Portail collaborateur">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="nav-label">Portail collaborateur</span>
    </a>
    <div class="sidebar-bottom-row" style="padding:6px 16px;font-size:0.7rem;color:var(--ss-text-muted,#999);opacity:.7">
      <span class="nav-label">SpocCare v<?= APP_SEMVER ?></span>
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
    <h5 class="mb-0 topbar-title"><?= h($pageTitle) ?></h5>
    <div class="topbar-search ms-auto me-3" id="topbarSearch" style="position:relative">
      <i class="bi bi-search search-icon"></i>
      <input type="text" class="form-control form-control-sm" id="topbarSearchInput" placeholder="Rechercher partout..." autocomplete="off">
      <button type="button" class="admin-search-clear" id="adminSearchClear" style="display:none"><i class="bi bi-x-lg"></i></button>
      <!-- Global search panel -->
      <div id="careSearchPanel" class="care-search-panel"></div>
    </div>
    <div class="topbar-right">
      <div class="topbar-user d-none d-sm-flex">
        <span class="topbar-user-name"><?= h($user['prenom'] . ' ' . $user['nom']) ?></span>
        <span class="topbar-user-role"><?= h($roleLabel) ?></span>
      </div>
      <a href="/spocspace/login?action=logout" class="topbar-icon-btn topbar-logout" title="Déconnexion">
        <i class="bi bi-power"></i>
      </a>
    </div>
  </header>

  <!-- Scripts needed BEFORE page content (pages use bootstrap.Modal, adminApiPost, etc.) -->
  <script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/vendor/bootstrap.bundle.min.js"></script>
  <script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/url-manager.js?v=<?= APP_VERSION ?>"></script>
  <script nonce="<?= $cspNonce ?>">
  // Override AdminURL base for SpocCare
  (function(){
      const BASE = '/spoccare';
      AdminURL.page = function(page, id, params) {
          let url = (!page || page === 'dashboard') ? BASE + '/' : BASE + '/' + encodeURIComponent(page);
          if (id) url += '/' + encodeURIComponent(id);
          if (params && typeof params === 'object') { const qs = new URLSearchParams(params).toString(); if (qs) url += '?' + qs; }
          return url;
      };
      AdminURL.currentPage = function() {
          const path = window.location.pathname.replace(/\/+$/, '');
          const relative = path.substring(BASE.length);
          const parts = relative.split('/').filter(Boolean);
          return parts[0] || 'dashboard';
      };
      AdminURL.currentId = function() {
          const path = window.location.pathname.replace(/\/+$/, '');
          const relative = path.substring(BASE.length);
          const parts = relative.split('/').filter(Boolean);
          return parts[1] || new URLSearchParams(window.location.search).get('id') || '';
      };
      AdminURL.go = function(page, id, params) { window.location.href = this.page(page, id, params); };
  })();
  </script>
  <script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/helpers.js?v=<?= APP_VERSION ?>"></script>
  <script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/zerda-select.js?v=<?= APP_VERSION ?>"></script>
  <script nonce="<?= $cspNonce ?>">
  window.__SS_CARE__ = {
      csrfToken: '<?= $csrfToken ?>',
      userId: '<?= $user['id'] ?>',
      userName: '<?= h($user['prenom'] . ' ' . $user['nom']) ?>',
      role: '<?= $user['role'] ?>'
  };
  window.__SS_ADMIN__ = window.__SS_CARE__;
  // Override adminApiPost to use admin API with care CSRF
  window.careApiPost = async function(action, data = {}) {
      data.action = action;
      try {
          const r = await fetch('/spocspace/admin/api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__SS_CARE__.csrfToken },
              credentials: 'same-origin',
              body: JSON.stringify(data)
          });
          const j = await r.json();
          if (j.csrf) window.__SS_CARE__.csrfToken = j.csrf;
          return j;
      } catch (e) { return { success: false, message: 'Erreur de connexion' }; }
  };
  window.adminApiPost = window.careApiPost;
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

<script nonce="<?= $cspNonce ?>" src="/spocspace/admin/assets/js/admin.js?v=<?= APP_VERSION ?>"></script>

<!-- Global Search -->
<style>
.care-search-panel {
    position:absolute; left:0; right:0; top:calc(100% + 6px);
    background:#fff; border:1px solid #ececec; border-radius:.65rem;
    box-shadow:0 10px 24px rgba(0,0,0,.08); padding:.35rem; z-index:1000;
    max-height:0; opacity:0; transform:translateY(-4px); pointer-events:none; overflow:hidden;
    transition:max-height .28s cubic-bezier(.22,.61,.36,1), opacity .2s ease, transform .2s ease;
}
.care-search-panel.open {
    max-height:420px; opacity:1; transform:translateY(0); pointer-events:auto; overflow-y:auto;
}
.csp-section { font-size:.7rem; color:#8b8b8b; padding:.25rem .55rem .15rem; text-transform:uppercase; letter-spacing:.3px; font-weight:600; }
.csp-item {
    display:flex; align-items:center; gap:.6rem; padding:.5rem .55rem; border-radius:.5rem; cursor:pointer; transition:background .12s;
}
.csp-item:hover { background:rgba(45,74,67,.08); }
.csp-item .csp-icon {
    width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
    font-size:.85rem; flex-shrink:0;
}
.csp-item .csp-icon.t-resident { background:#bcd2cb; color:#2d4a43; }
.csp-item .csp-icon.t-wiki { background:#B8C9D4; color:#3B4F6B; }
.csp-item .csp-icon.t-annonce { background:#D0C4D8; color:#5B4B6B; }
.csp-item .csp-icon.t-history { background:#f0eeea; color:#6c757d; }
.csp-item .csp-text { flex:1; min-width:0; }
.csp-item .csp-title { font-size:.85rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.csp-item .csp-sub { font-size:.7rem; color:#999; }
.csp-del {
    background:none; border:none; color:#ccc; cursor:pointer; padding:2px; font-size:.75rem; transition:color .12s;
}
.csp-del:hover { color:#dc3545; }
.csp-empty { text-align:center; padding:16px; color:#adb5bd; font-size:.82rem; }
.csp-type-labels { display:flex; gap:4px; padding:.25rem .55rem; flex-wrap:wrap; }
.csp-type-label {
    font-size:.68rem; padding:2px 8px; border-radius:10px; font-weight:600; cursor:default;
}
</style>

<script nonce="<?= $cspNonce ?>">
(function(){
    const HIST_KEY = 'spoccare:search-history';
    const TTL_MS = 2 * 24 * 60 * 60 * 1000;
    const now = () => Date.now();

    const input = document.getElementById('topbarSearchInput');
    const panel = document.getElementById('careSearchPanel');
    const clearBtn = document.getElementById('adminSearchClear');
    if (!input || !panel) return;

    // ── History helpers ────────────────────────────────
    function loadHist() {
        try {
            const arr = JSON.parse(localStorage.getItem(HIST_KEY) || '[]');
            const cutoff = now() - TTL_MS;
            return arr.filter(it => it && it.q && (it.ts || 0) >= cutoff);
        } catch { return []; }
    }
    function saveHist(arr) {
        const seen = new Set();
        const clean = arr.filter(it => {
            const k = (it.q || '').toLowerCase();
            if (!k || seen.has(k)) return false;
            seen.add(k); return true;
        }).slice(0, 30);
        try { localStorage.setItem(HIST_KEY, JSON.stringify(clean)); } catch {}
    }
    function addHist(q) {
        q = (q || '').trim();
        if (!q || q.length < 2) return;
        const h = loadHist().filter(it => it.q.toLowerCase() !== q.toLowerCase());
        h.unshift({ q, ts: now() });
        saveHist(h);
    }
    function delHist(q) {
        saveHist(loadHist().filter(it => it.q.toLowerCase() !== q.toLowerCase()));
    }

    // ── Panel open/close ──────────────────────────────
    const searchWrap = document.getElementById('topbarSearch');
    function openPanel() { panel.classList.add('open'); }
    function closePanel() { panel.classList.remove('open'); if (searchWrap) searchWrap.classList.remove('expanded'); }

    // ── Render history ────────────────────────────────
    function renderHistory(filter) {
        const hist = loadHist().sort((a, b) => b.ts - a.ts);
        const f = (filter || '').toLowerCase();
        const filtered = f ? hist.filter(it => it.q.toLowerCase().includes(f)) : hist;
        const recent = filtered.slice(0, 3);
        const older = filtered.slice(3, 7);

        if (!recent.length && !older.length) {
            panel.innerHTML = '<div class="csp-empty"><i class="bi bi-search" style="font-size:1.2rem;display:block;margin-bottom:4px"></i>Tapez pour rechercher</div>';
            return;
        }

        const mkItem = (it, icon) => `
            <div class="csp-item csp-hist-item" data-q="${escapeHtml(it.q)}">
                <div class="csp-icon t-history"><i class="bi bi-${icon}"></i></div>
                <div class="csp-text"><div class="csp-title">${escapeHtml(it.q)}</div></div>
                <button class="csp-del" data-del-q="${escapeHtml(it.q)}" title="Supprimer"><i class="bi bi-x"></i></button>
            </div>`;

        let html = '<div class="csp-section">Recherches récentes</div>';
        html += recent.map(it => mkItem(it, 'clock')).join('');
        if (older.length) {
            html += '<div class="csp-section">Plus anciennes</div>';
            html += older.map(it => mkItem(it, 'search')).join('');
        }
        panel.innerHTML = html;
    }

    // ── Render results ────────────────────────────────
    function renderResults(results, query) {
        if (!results.length) {
            panel.innerHTML = `<div class="csp-empty">Aucun résultat pour « ${escapeHtml(query)} »</div>`;
            return;
        }

        // Group by type
        const groups = {};
        results.forEach(r => {
            if (!groups[r.type]) groups[r.type] = [];
            groups[r.type].push(r);
        });

        const typeLabels = { resident: 'Résidents', wiki: 'Wiki', annonce: 'Annonces' };
        let html = '';

        for (const [type, items] of Object.entries(groups)) {
            html += `<div class="csp-section">${typeLabels[type] || type}</div>`;
            items.forEach(r => {
                html += `
                    <div class="csp-item csp-result-item" data-url="${escapeHtml(r.url)}" data-id="${r.id}">
                        <div class="csp-icon t-${type}"><i class="bi bi-${r.icon}"></i></div>
                        <div class="csp-text">
                            <div class="csp-title">${escapeHtml(r.title)}</div>
                            <div class="csp-sub">${escapeHtml(r.subtitle)}</div>
                        </div>
                    </div>`;
            });
        }
        panel.innerHTML = html;
    }

    // ── Search API ────────────────────────────────────
    let searchTimer;
    async function doSearch(q) {
        if (q.length < 2) { renderHistory(q); return; }
        const res = await adminApiPost('admin_care_global_search', { q });
        if (res.success) renderResults(res.results, q);
    }

    // ── Events ────────────────────────────────────────
    input.addEventListener('focus', () => {
        if (searchWrap) searchWrap.classList.add('expanded');
        if (!input.value) renderHistory('');
        openPanel();
    });
    input.addEventListener('blur', () => {
        setTimeout(() => { if (searchWrap && !panel.classList.contains('open')) searchWrap.classList.remove('expanded'); }, 200);
    });

    input.addEventListener('input', () => {
        const v = input.value.trim();
        clearBtn.style.display = v ? '' : 'none';
        clearTimeout(searchTimer);
        if (v.length < 2) { renderHistory(v); openPanel(); return; }
        searchTimer = setTimeout(() => doSearch(v), 300);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePanel(); input.blur(); }
        if (e.key === 'Enter' && input.value.trim().length >= 2) {
            addHist(input.value.trim());
            doSearch(input.value.trim());
        }
    });

    clearBtn?.addEventListener('click', () => {
        input.value = '';
        clearBtn.style.display = 'none';
        renderHistory('');
        input.focus();
    });

    // Click on panel items
    panel.addEventListener('click', (e) => {
        // Delete history
        const del = e.target.closest('.csp-del');
        if (del) { e.stopPropagation(); delHist(del.dataset.delQ); renderHistory(input.value); return; }

        // Click history → fill input + search
        const hist = e.target.closest('.csp-hist-item');
        if (hist) {
            input.value = hist.dataset.q;
            clearBtn.style.display = '';
            doSearch(hist.dataset.q);
            return;
        }

        // Click result → navigate
        const result = e.target.closest('.csp-result-item');
        if (result) {
            addHist(input.value.trim());
            closePanel();
            const url = result.dataset.url;
            if (url) AdminURL.go(url.split('/')[0], url.split('/')[1] || '');
        }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#topbarSearch')) closePanel();
    });
})();
</script>
</body>
</html>
