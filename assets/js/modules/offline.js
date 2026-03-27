/**
 * zerdaTime - Offline support
 * Caches GET responses, queues writes when offline, syncs on reconnect.
 */

const CACHE_PREFIX = 'zt_cache_';
const QUEUE_KEY = 'zt_offline_queue';
const CACHE_TTL = 30 * 60 * 1000; // 30 min

// Actions worth caching for offline use
const CACHEABLE_ACTIONS = [
    'get_mon_planning', 'get_planning_hebdo', 'get_planning_mois',
    'get_mes_desirs', 'get_mes_permanents', 'get_horaires_types',
    'get_mes_absences', 'get_mes_messages', 'get_modules_list',
    'me', 'get_notifications_count',
];

// Actions that can be queued when offline
const QUEUEABLE_ACTIONS = [
    'submit_desir', 'update_desir', 'delete_desir',
    'submit_desir_permanent', 'submit_absence',
    'send_message', 'mark_message_read',
    'submit_vacances', 'annuler_vacances',
    'mark_notification_read', 'mark_all_notifications_read',
    'mark_alert_read',
];

/** Cache a response */
function cacheSet(action, params, data) {
    try {
        const key = CACHE_PREFIX + action + '_' + JSON.stringify(params);
        localStorage.setItem(key, JSON.stringify({ data, ts: Date.now() }));
    } catch (e) { /* storage full — ignore */ }
}

/** Get cached response if still valid */
function cacheGet(action, params) {
    try {
        const key = CACHE_PREFIX + action + '_' + JSON.stringify(params);
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const { data, ts } = JSON.parse(raw);
        if (Date.now() - ts > CACHE_TTL) {
            localStorage.removeItem(key);
            return null;
        }
        return data;
    } catch (e) { return null; }
}

/** Get the offline queue */
function getQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
    } catch (e) { return []; }
}

/** Add an action to the queue */
function enqueue(action, data) {
    const queue = getQueue();
    queue.push({ action, data, ts: Date.now() });
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

/** Clear the queue */
function clearQueue() {
    localStorage.removeItem(QUEUE_KEY);
}

/** Check if we're online */
function isOnline() {
    return navigator.onLine;
}

/** Show/hide the offline bar */
let offlineBar = null;

function showOfflineBar() {
    if (offlineBar) return;
    offlineBar = document.createElement('div');
    offlineBar.className = 'zt-offline-bar';
    offlineBar.innerHTML = '<i class="bi bi-wifi-off"></i> Mode hors-ligne — les données seront synchronisées au retour de la connexion';
    document.body.appendChild(offlineBar);
    requestAnimationFrame(() => offlineBar.classList.add('show'));
}

function hideOfflineBar() {
    if (!offlineBar) return;
    offlineBar.classList.remove('show');
    setTimeout(() => { offlineBar?.remove(); offlineBar = null; }, 300);
}

/** Sync bar with progress */
function showSyncBar(total) {
    const bar = document.createElement('div');
    bar.className = 'zt-sync-bar show';
    bar.innerHTML = `
        <div class="zt-sync-text"><i class="bi bi-arrow-repeat"></i> Synchronisation en cours...</div>
        <div class="zt-sync-progress"><div class="zt-sync-progress-fill" id="ztSyncFill"></div></div>
    `;
    document.body.appendChild(bar);
    return {
        update(current) {
            const fill = bar.querySelector('#ztSyncFill');
            if (fill) fill.style.width = Math.round((current / total) * 100) + '%';
        },
        done(success, failed) {
            const text = bar.querySelector('.zt-sync-text');
            if (text) {
                if (failed > 0) {
                    text.innerHTML = `<i class="bi bi-exclamation-circle"></i> Synchronisé : ${success} OK, ${failed} erreur(s)`;
                    bar.classList.add('zt-sync-warning');
                } else {
                    text.innerHTML = `<i class="bi bi-check-circle"></i> ${success} action(s) synchronisée(s)`;
                    bar.classList.add('zt-sync-success');
                }
            }
            const fill = bar.querySelector('#ztSyncFill');
            if (fill) fill.style.width = '100%';
            setTimeout(() => {
                bar.classList.remove('show');
                setTimeout(() => bar.remove(), 300);
            }, 3000);
        },
    };
}

/** Sync queued actions */
async function syncQueue() {
    const queue = getQueue();
    if (!queue.length) return;

    const { apiPost } = await import('../helpers.js');
    const syncBar = showSyncBar(queue.length);
    let success = 0, failed = 0;

    for (let i = 0; i < queue.length; i++) {
        const item = queue[i];
        try {
            const res = await apiPost(item.action, item.data);
            if (res.success) success++;
            else failed++;
        } catch (e) {
            failed++;
        }
        syncBar.update(i + 1);
    }

    clearQueue();
    syncBar.done(success, failed);
}

/**
 * Wrap apiPost with offline support.
 * Called from helpers.js apiPost when a request fails due to network error.
 */
export function handleOfflineGet(action, params) {
    if (CACHEABLE_ACTIONS.includes(action)) {
        const cached = cacheGet(action, params);
        if (cached) {
            cached._fromCache = true;
            return cached;
        }
    }
    return null;
}

export function handleOnlineResponse(action, params, response) {
    if (CACHEABLE_ACTIONS.includes(action) && response.success) {
        cacheSet(action, params, response);
    }
}

export function canQueue(action) {
    return QUEUEABLE_ACTIONS.includes(action);
}

export { enqueue, syncQueue, isOnline, showOfflineBar, hideOfflineBar, getQueue };

/** Setup online/offline listeners */
export function initOffline() {
    if (!isOnline()) showOfflineBar();

    window.addEventListener('online', () => {
        hideOfflineBar();
        const queue = getQueue();
        if (queue.length > 0) {
            syncQueue();
        }
    });

    window.addEventListener('offline', () => {
        showOfflineBar();
    });
}
