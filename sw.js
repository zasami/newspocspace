/**
 * zerdaTime — Service Worker
 * Offline-first with cache + background sync
 */

const CACHE_VERSION = 'zt-v2';
const STATIC_CACHE = CACHE_VERSION + '-static';
const DYNAMIC_CACHE = CACHE_VERSION + '-dynamic';
const API_CACHE = CACHE_VERSION + '-api';
const SYNC_QUEUE = 'zt-sync-queue';

// Static assets to pre-cache on install (employee SPA)
const PRECACHE_URLS = [
  '/zerdatime/',
  '/zerdatime/logo.png',
  '/zerdatime/manifest.json',
  '/zerdatime/assets/css/vendor/bootstrap.min.css',
  '/zerdatime/assets/css/vendor/bootstrap-icons.min.css',
  '/zerdatime/assets/css/zerdatime.css',
  '/zerdatime/assets/js/vendor/bootstrap.bundle.min.js',
  '/zerdatime/assets/js/app.js',
  '/zerdatime/assets/js/helpers.js',
  '/zerdatime/assets/js/zt-db.js',
  '/zerdatime/assets/js/zerda-select.js',
  '/zerdatime/assets/icons/icon-192x192.png',
];

// API actions that can be cached for offline reading
const CACHEABLE_GET_ACTIONS = [
  'admin_get_dashboard_stats',
  'admin_get_all_messages',
  'admin_get_message_contacts',
  'admin_get_planning',
  'admin_get_planning_refs',
  'admin_get_users',
  'admin_get_absences',
  'admin_get_desirs',
  'admin_get_horaires',
  'admin_get_config',
  'admin_get_unread_counts',
  'admin_get_message_stats',
  'get_planning_hebdo',
  'get_my_desirs',
  'get_my_absences',
  'get_inbox',
  'get_sent',
  'get_unread_count',
  'sync_delta',
];

// API actions that should be queued for sync when offline
const SYNCABLE_ACTIONS = [
  'admin_send_message',
  'admin_save_assignation',
  'admin_validate_absence',
  'admin_validate_desir',
  'admin_reply_message',
  'send_message',
  'submit_desir',
];

// ══════════════════════════════════════════════════════════════
// INSTALL — Pre-cache static assets
// ══════════════════════════════════════════════════════════════
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(async cache => {
      // Cache each URL individually — don't fail if one is unavailable
      for (const url of PRECACHE_URLS) {
        try {
          const response = await fetch(url, { credentials: 'same-origin' });
          if (response.ok) await cache.put(url, response);
        } catch (e) {
          console.warn('[SW] Could not cache:', url);
        }
      }
    }).then(() => self.skipWaiting())
  );
});

// ══════════════════════════════════════════════════════════════
// ACTIVATE — Clean old caches
// ══════════════════════════════════════════════════════════════
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k.startsWith('zt-') && k !== STATIC_CACHE && k !== DYNAMIC_CACHE && k !== API_CACHE)
          .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ══════════════════════════════════════════════════════════════
// FETCH — Network-first for API, cache-first for static
// ══════════════════════════════════════════════════════════════
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET cross-origin
  if (url.origin !== self.location.origin) return;

  // Skip website + admin + care API (let them pass through directly)
  if (url.pathname.includes('/website/api.php')) return;
  if (url.pathname.includes('/admin/api.php')) return;
  if (url.pathname.includes('/care/api.php')) return;

  // API requests (POST to api.php — employee SPA only)
  if (event.request.method === 'POST' && url.pathname.includes('api.php')) {
    event.respondWith(handleApiRequest(event.request));
    return;
  }

  // GET API requests
  if (event.request.method === 'GET' && url.pathname.includes('api.php')) {
    event.respondWith(networkFirst(event.request, API_CACHE));
    return;
  }

  // SPA page fragments (pages/*.php fetched by app.js)
  if (url.pathname.match(/\/zerdatime\/pages\/.*\.php/)) {
    event.respondWith(networkFirst(event.request, DYNAMIC_CACHE));
    return;
  }

  // HTML pages (navigate) — network first, shell fallback
  if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(handleNavigate(event.request));
    return;
  }

  // Static assets — cache first
  if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff2?|ttf|eot)$/)) {
    event.respondWith(cacheFirst(event.request, STATIC_CACHE));
    return;
  }

  // Everything else — network first
  event.respondWith(networkFirst(event.request, DYNAMIC_CACHE));
});

// ══════════════════════════════════════════════════════════════
// STRATEGIES
// ══════════════════════════════════════════════════════════════

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
  }
}

async function handleNavigate(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
      // Also cache as the shell URL for offline fallback
      if (new URL(request.url).pathname.startsWith('/zerdatime/') && !new URL(request.url).pathname.includes('/admin/') && !new URL(request.url).pathname.includes('/care/') && !new URL(request.url).pathname.includes('/website/')) {
        cache.put(new Request('/zerdatime/'), response.clone());
      }
    }
    return response;
  } catch {
    // Offline: try exact match first, then shell, then offline page
    const cached = await caches.match(request);
    if (cached) return cached;
    const shell = await caches.match('/zerdatime/');
    if (shell) return shell;
    return new Response(offlinePage(), {
      headers: { 'Content-Type': 'text/html; charset=utf-8' },
    });
  }
}

async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;
    // For page fragments, return a helpful offline message
    if (request.url.includes('/pages/')) {
      return new Response('<div class="text-center py-5"><i class="bi bi-wifi-off" style="font-size:2rem;opacity:.2"></i><h5 class="mt-3">Hors ligne</h5><p class="text-muted">Cette page n\'est pas disponible hors ligne. Visitez-la une fois en ligne pour la mettre en cache.</p></div>', {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      });
    }
    return new Response('Offline', { status: 503 });
  }
}

// ══════════════════════════════════════════════════════════════
// API — Cache readable actions, queue writable ones when offline
// ══════════════════════════════════════════════════════════════

async function handleApiRequest(request) {
  const clone = request.clone();
  let body;
  try { body = await clone.json(); } catch { body = {}; }
  const action = body.action || '';

  // Try network first
  try {
    const response = await fetch(request);
    // Cache GET-like actions for offline
    if (response.ok && CACHEABLE_GET_ACTIONS.includes(action)) {
      const cache = await caches.open(API_CACHE);
      const cacheKey = new Request(request.url + '?_action=' + action + '&_body=' + JSON.stringify(body));
      cache.put(cacheKey, response.clone());
    }
    return response;
  } catch {
    // OFFLINE — return cached data for read actions
    if (CACHEABLE_GET_ACTIONS.includes(action)) {
      const cache = await caches.open(API_CACHE);
      const cacheKey = new Request(request.url + '?_action=' + action + '&_body=' + JSON.stringify(body));
      const cached = await cache.match(cacheKey);
      if (cached) {
        const data = await cached.clone().json();
        data._cached = true;
        data._cachedAt = cached.headers.get('date');
        return new Response(JSON.stringify(data), {
          headers: { 'Content-Type': 'application/json' },
        });
      }
    }

    // Queue writable actions for background sync
    if (SYNCABLE_ACTIONS.includes(action)) {
      await queueForSync({ url: request.url, action, body, timestamp: Date.now() });
      return new Response(JSON.stringify({
        success: true,
        _offline: true,
        message: 'Action enregistrée — sera synchronisée au retour de la connexion',
      }), { headers: { 'Content-Type': 'application/json' } });
    }

    return new Response(JSON.stringify({
      success: false,
      message: 'Vous êtes hors ligne',
    }), { headers: { 'Content-Type': 'application/json' } });
  }
}

// ══════════════════════════════════════════════════════════════
// SYNC QUEUE — IndexedDB for offline actions
// ══════════════════════════════════════════════════════════════

function openSyncDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('zt_sync', 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains('queue')) {
        db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function queueForSync(data) {
  const db = await openSyncDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('queue', 'readwrite');
    tx.objectStore('queue').add(data);
    tx.oncomplete = () => {
      resolve();
      // Try to register background sync
      self.registration.sync?.register('zt-sync').catch(() => {});
    };
    tx.onerror = () => reject(tx.error);
  });
}

async function processQueue() {
  const db = await openSyncDB();
  const tx = db.transaction('queue', 'readonly');
  const store = tx.objectStore('queue');

  return new Promise((resolve, reject) => {
    const req = store.getAll();
    req.onsuccess = async () => {
      const items = req.result;
      let processed = 0;

      for (const item of items) {
        try {
          const csrfToken = await getCSRFToken();
          const headers = { 'Content-Type': 'application/json' };
          if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

          const response = await fetch(item.url, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify(item.body),
          });

          if (response.ok) {
            // Remove from queue
            const delTx = db.transaction('queue', 'readwrite');
            delTx.objectStore('queue').delete(item.id);
            processed++;
          }
        } catch {
          // Still offline — stop processing
          break;
        }
      }

      // Notify clients
      if (processed > 0) {
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
          client.postMessage({
            type: 'SYNC_COMPLETE',
            processed,
            remaining: items.length - processed,
          });
        });
      }

      resolve(processed);
    };
    req.onerror = () => reject(req.error);
  });
}

async function getCSRFToken() {
  try {
    const cache = await caches.open(DYNAMIC_CACHE);
    const response = await cache.match('/zerdatime/');
    if (response) {
      const html = await response.text();
      const match = html.match(/csrfToken:\s*'([^']+)'/);
      if (match) return match[1];
    }
  } catch {}
  return null;
}

// ══════════════════════════════════════════════════════════════
// BACKGROUND SYNC
// ══════════════════════════════════════════════════════════════

self.addEventListener('sync', event => {
  if (event.tag === 'zt-sync') {
    event.waitUntil(processQueue());
  }
});

// Periodic sync fallback — message from main thread
self.addEventListener('message', event => {
  if (event.data?.type === 'PROCESS_QUEUE') {
    processQueue();
  }
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// ══════════════════════════════════════════════════════════════
// OFFLINE PAGE
// ══════════════════════════════════════════════════════════════

function offlinePage() {
  return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>zerdaTime — Hors ligne</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F5F2; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.card { background: #fff; border-radius: 2rem; padding: 3rem; text-align: center; max-width: 400px; border: 1px solid #E8E5E0; }
.icon { width: 64px; height: 64px; border-radius: 50%; background: #E2B8AE; color: #7B3B2C; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem; }
h1 { font-size: 1.3rem; margin-bottom: .5rem; color: #1A1A1A; }
p { color: #6B6B6B; font-size: .9rem; margin-bottom: 1.5rem; }
button { background: #1A1A1A; color: #fff; border: none; padding: .7rem 2rem; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: .9rem; }
button:hover { background: #000; }
.status { margin-top: 1rem; font-size: .78rem; color: #9B9B9B; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">&#x26A0;</div>
  <h1>Vous etes hors ligne</h1>
  <p>Les donnees mises en cache sont disponibles. La synchronisation reprendra automatiquement.</p>
  <button onclick="location.reload()">Reessayer</button>
  <div class="status" id="syncStatus"></div>
</div>
<script>
  if (navigator.onLine) location.reload();
  window.addEventListener('online', () => location.reload());
</script>
</body>
</html>`;
}
