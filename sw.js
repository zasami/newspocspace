/**
 * SpocSpace — Service Worker
 * Offline-first with cache + background sync
 */

const CACHE_VERSION = 'ss-v8';
const STATIC_CACHE = CACHE_VERSION + '-static';
const DYNAMIC_CACHE = CACHE_VERSION + '-dynamic';
const API_CACHE = CACHE_VERSION + '-api';
const SYNC_QUEUE = 'ss-sync-queue';

// Static assets to pre-cache on install (employee SPA)
const PRECACHE_URLS = [
  '/spocspace/',
  '/spocspace/login',
  '/spocspace/logo.png',
  '/spocspace/manifest.json',
  '/spocspace/assets/css/vendor/bootstrap.min.css',
  '/spocspace/assets/css/vendor/bootstrap-icons.min.css',
  '/spocspace/assets/css/spocspace.css',
  '/spocspace/assets/js/vendor/bootstrap.bundle.min.js',
  '/spocspace/assets/js/app.js',
  '/spocspace/assets/js/helpers.js',
  '/spocspace/assets/js/ss-db.js',
  '/spocspace/assets/js/lockscreen.js',
  '/spocspace/assets/js/zerda-select.js',
  '/spocspace/assets/icons/icon-192x192.png',
];

// All SPA page fragments — pre-cached for full offline support
const PAGE_URLS = [
  'home', 'login', 'planning', 'desirs', 'absences', 'vacances',
  'collegues', 'emails', 'votes', 'pv', 'sondages', 'documents',
  'changements', 'profile', 'notifications', 'fiches-salaire',
  'covoiturage', 'repartition', 'cuisine', 'cuisine-home',
  'cuisine-menus', 'cuisine-reservations', 'cuisine-famille',
  'cuisine-vip', 'mur', 'wiki', 'annonces',
  'annuaire', 'mes-stagiaires', 'mon-stage',
].map(p => '/spocspace/pages/' + p + '.php');

// JS modules to pre-cache for full offline support
const MODULE_URLS = [
  'home', 'auth', 'planning', 'desirs', 'absences', 'vacances',
  'collegues', 'emails', 'votes', 'pv', 'sondages', 'documents',
  'changements', 'profile', 'notifications', 'fiches-salaire',
  'covoiturage', 'repartition', 'cuisine', 'cuisine-home',
  'cuisine-menus', 'cuisine-reservations', 'cuisine-famille',
  'cuisine-vip', 'mur', 'wiki', 'annonces', 'offline',
  'annuaire', 'mes-stagiaires', 'mon-stage',
].map(m => '/spocspace/assets/js/modules/' + m + '.js');

// API actions that can be cached for offline reading
const CACHEABLE_GET_ACTIONS = [
  // Admin
  'admin_get_dashboard_stats', 'admin_get_all_messages', 'admin_get_message_contacts',
  'admin_get_planning', 'admin_get_planning_refs', 'admin_get_users',
  'admin_get_absences', 'admin_get_desirs', 'admin_get_horaires',
  'admin_get_config', 'admin_get_unread_counts', 'admin_get_message_stats',
  // Employee — planning & scheduling
  'get_planning_hebdo', 'get_planning_mois', 'get_mon_planning', 'get_modules_list',
  // Desirs, absences, vacances
  'get_mes_desirs', 'get_mes_absences', 'get_vacances_annee', 'get_absences_collegues',
  'get_desirs_permanents', 'get_mois_disponibles',
  // Messages
  'get_inbox', 'get_sent', 'get_unread_count', 'get_message_contacts',
  // Notifications
  'get_notifications', 'get_notifications_count', 'get_poll_data', 'get_pending_alerts',
  // Collaboration
  'get_collegues', 'get_changements', 'get_mes_changements',
  'get_covoiturage_matches', 'get_covoiturage_buddies',
  // Info
  'get_proposals_ouvertes', 'get_proposal_planning',
  'get_pv_list', 'get_pv',
  'get_sondages_ouverts', 'get_sondage_detail',
  'get_documents', 'get_document_services',
  'get_mes_fiches_salaire',
  'get_annonces', 'get_annonce_detail',
  // Wiki
  'get_wiki_categories', 'get_wiki_pages', 'get_wiki_page', 'get_wiki_favoris',
  // Mur social
  'get_mur_feed', 'get_mur_comments',
  // Cuisine
  'get_menus_semaine', 'cuisine_get_menus_semaine', 'cuisine_get_dashboard',
  'cuisine_get_reservations', 'cuisine_get_famille_reservations', 'cuisine_get_vip_residents',
  // Repartition
  'get_repartition',
  // Auth & sync
  'me', 'sync_delta', 'get_horaires_types',
  // Annuaire
  'get_annuaire', 'search_annuaire',
  // Stagiaires
  'get_my_stagiaires_as_formateur', 'get_stagiaire_view_formateur', 'get_my_stage',
];

// API actions that should be queued for sync when offline
const SYNCABLE_ACTIONS = [
  // Admin
  'admin_send_message', 'admin_save_assignation', 'admin_validate_absence',
  'admin_validate_desir', 'admin_reply_message',
  // Desirs
  'submit_desir', 'update_desir', 'delete_desir', 'submit_desir_permanent',
  // Absences & vacances
  'submit_absence', 'submit_vacances', 'annuler_vacances', 'modifier_vacances',
  // Messages
  'send_message', 'mark_message_read', 'mark_all_messages_read',
  // Notifications
  'mark_notification_read', 'mark_all_notifications_read', 'mark_alert_read',
  // Changements
  'submit_changement', 'confirmer_changement', 'refuser_changement', 'annuler_changement',
  // Stagiaires
  'save_my_report', 'delete_my_report', 'validate_stagiaire_report', 'save_stagiaire_evaluation',
  // Votes & sondages
  'submit_vote', 'submit_sondage_reponses',
  // Covoiturage
  'add_covoiturage_buddy', 'remove_covoiturage_buddy', 'update_covoiturage_profile',
  // Mur social
  'create_mur_post', 'toggle_mur_like', 'add_mur_comment', 'delete_mur_post', 'delete_mur_comment',
  // Wiki
  'toggle_wiki_favori',
  // Cuisine
  'reserver_menu', 'annuler_reservation_menu',
  'cuisine_save_menu', 'cuisine_save_reservation_famille', 'cuisine_save_vip_order',
  // PV
  'rate_pv', 'comment_pv',
  // Profile
  'update_profile', 'update_password',
];

// ══════════════════════════════════════════════════════════════
// INSTALL — Pre-cache static assets
// ══════════════════════════════════════════════════════════════
self.addEventListener('install', event => {
  // Force immediate activation — don't wait for old SW to die
  self.skipWaiting();
  event.waitUntil(
    Promise.all([
      // Cache static assets
      caches.open(STATIC_CACHE).then(async cache => {
        for (const url of PRECACHE_URLS) {
          try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (response.ok) await cache.put(url, response);
          } catch (e) {
            console.warn('[SW] Could not cache:', url);
          }
        }
      }),
      // Cache all page fragments for full offline support
      caches.open(DYNAMIC_CACHE).then(async cache => {
        for (const url of PAGE_URLS) {
          try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (response.ok) await cache.put(url, response);
          } catch (e) {
            console.warn('[SW] Could not cache page:', url);
          }
        }
      }),
      // Cache all JS modules for full offline support
      caches.open(STATIC_CACHE).then(async cache => {
        for (const url of MODULE_URLS) {
          try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (response.ok) await cache.put(url, response);
          } catch (e) {
            console.warn('[SW] Could not cache module:', url);
          }
        }
      }),
    ])
  );
});

// ══════════════════════════════════════════════════════════════
// ACTIVATE — Clean old caches
// ══════════════════════════════════════════════════════════════
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k.startsWith('ss-') && k !== STATIC_CACHE && k !== DYNAMIC_CACHE && k !== API_CACHE)
          .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ══════════════════════════════════════════════════════════════
// FETCH — Network-first for API, cache-first for static
// ══════════════════════════════════════════════════════════════
self.addEventListener('fetch', event => {
  // Skip invalid or empty requests
  if (!event.request || !event.request.url) return;

  let url;
  try { url = new URL(event.request.url); } catch { return; }

  // Skip non-GET cross-origin
  if (url.origin !== self.location.origin) return;

  // Skip media files (video/audio) — streaming with Range requests, too large to cache
  if (url.pathname.match(/\.(mp4|webm|ogg|mp3|wav|mov|m4v|avi)$/i)) return;

  // Skip Range requests (partial content for media streaming)
  if (event.request.headers.get('range')) return;

  // Skip website subfolder entirely (public marketing site, separate concern)
  if (url.pathname.startsWith('/spocspace/website/')) return;

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
  if (url.pathname.match(/\/spocspace\/pages\/.*\.php/)) {
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
      // Cache the SPA shell for offline fallback
      const p = new URL(request.url).pathname;
      if (p.startsWith('/spocspace/') && !p.includes('/admin/') && !p.includes('/care/') && !p.includes('/website/')) {
        cache.put(new Request('/spocspace/'), response.clone());
      }
    }
    return response;
  } catch {
    // Offline: try exact match first, then shell, then offline page
    const cached = await caches.match(request);
    if (cached) return cached;
    const shell = await caches.match('/spocspace/');
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
      // Add offline timestamp for conflict detection
      body._queued_at = new Date().toISOString();
      body._offline = true;
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
    const req = indexedDB.open('ss_sync', 1);
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
      self.registration.sync?.register('ss-sync').catch(() => {});
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
    const response = await cache.match('/spocspace/');
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
  if (event.tag === 'ss-sync') {
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
<title>SpocSpace — Hors ligne</title>
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
