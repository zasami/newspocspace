/**
 * SpocSpace — Offline-first module
 * Uses IndexedDB (ss-db) for local cache, delta sync with server.
 */
import * as db from '../ss-db.js';

const SYNC_INTERVAL = 5 * 60 * 1000; // 5 min
let _syncTimer = null;
let _online = navigator.onLine;

// Actions that can be served from IndexedDB when offline
const OFFLINE_GET_ACTIONS = [
    'get_planning_hebdo', 'get_planning_mois', 'get_mon_planning',
    'get_mes_desirs', 'get_mes_absences', 'get_mes_messages',
    'get_inbox', 'get_sent', 'get_unread_count', 'get_horaires_types',
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

// ── Offline bar UI ──
let offlineBar = null;

function showOfflineBar() {
    if (offlineBar) return;
    offlineBar = document.createElement('div');
    offlineBar.className = 'ss-offline-bar';
    offlineBar.innerHTML = '<i class="bi bi-wifi-off"></i> Mode hors-ligne — les donnees seront synchronisees au retour';
    document.body.appendChild(offlineBar);
    requestAnimationFrame(() => offlineBar.classList.add('show'));
}

function hideOfflineBar() {
    if (!offlineBar) return;
    offlineBar.classList.remove('show');
    setTimeout(() => { offlineBar?.remove(); offlineBar = null; }, 300);
}

// ── Sync bar UI ──
function showSyncBar(total) {
    const bar = document.createElement('div');
    bar.className = 'ss-sync-bar show';
    bar.innerHTML = `
        <div class="ss-sync-text"><i class="bi bi-arrow-repeat"></i> Synchronisation en cours...</div>
        <div class="ss-sync-progress"><div class="ss-sync-progress-fill" id="ztSyncFill"></div></div>`;
    document.body.appendChild(bar);
    return {
        update(current) {
            const fill = bar.querySelector('#ztSyncFill');
            if (fill) fill.style.width = Math.round((current / total) * 100) + '%';
        },
        done(success, failed) {
            const text = bar.querySelector('.ss-sync-text');
            if (text) {
                if (failed > 0) {
                    text.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${success} OK, ${failed} erreur(s)`;
                    bar.classList.add('ss-sync-warning');
                } else {
                    text.innerHTML = `<i class="bi bi-check-circle"></i> ${success} action(s) synchronisee(s)`;
                    bar.classList.add('ss-sync-success');
                }
            }
            const fill = bar.querySelector('#ztSyncFill');
            if (fill) fill.style.width = '100%';
            setTimeout(() => { bar.classList.remove('show'); setTimeout(() => bar.remove(), 300); }, 3000);
        },
    };
}

// ── Delta sync ──
async function deltaSync() {
    if (!navigator.onLine) return;
    try {
        const { apiPost } = await import('../helpers.js');
        const lastSync = await db.getMeta('last_sync');
        const res = await apiPost('sync_delta', { last_sync: lastSync });
        if (!res.success) return;

        if (res.planning?.length) await db.put('planning', res.planning);
        if (res.messages?.length) await db.put('messages', res.messages);
        if (res.users?.length)    await db.put('users', res.users);
        // Horaires: full replace (small dataset)
        if (res.horaires?.length) {
            await db.clear('meta'); // clear old horaires meta only
            await db.setMeta('horaires', res.horaires);
        }
        if (res.timestamp) await db.setMeta('last_sync', res.timestamp);
        await db.cleanup();
    } catch (e) {
        console.warn('[offline] deltaSync error:', e);
    }
}

// ── Sync queue ──
async function syncQueue() {
    const queue = await db.getQueue();
    if (!queue.length) return;

    const { apiPost } = await import('../helpers.js');
    const syncBar = showSyncBar(queue.length);
    let success = 0, failed = 0;

    for (let i = 0; i < queue.length; i++) {
        const item = queue[i];
        try {
            const res = await apiPost(item.action, item.data);
            if (res.success) { success++; await db.dequeue(item.id); }
            else failed++;
        } catch (e) {
            failed++;
            break; // still offline, stop
        }
        syncBar.update(i + 1);
    }
    syncBar.done(success, failed);
}

// ── Offline GET handler ──
export function handleOfflineGet(action, params) {
    if (!OFFLINE_GET_ACTIONS.includes(action)) return null;
    // Return a promise — caller must await
    return _resolveOfflineGet(action, params);
}

async function _resolveOfflineGet(action, params) {
    try {
        if (action.includes('planning')) {
            const all = await db.getAll('planning');
            return all.length ? { success: true, data: all, _fromCache: true } : null;
        }
        if (action.includes('message') || action === 'get_inbox' || action === 'get_sent') {
            const all = await db.getAll('messages');
            return all.length ? { success: true, data: all, _fromCache: true } : null;
        }
        if (action === 'get_horaires_types') {
            const h = await db.getMeta('horaires');
            return h ? { success: true, data: h, _fromCache: true } : null;
        }
        if (action === 'me') {
            const token = await db.getAuthToken();
            if (token) return { success: true, user: { id: token.userId, prenom: token.prenom, nom: token.nom, email: token.email, role: token.role }, _fromCache: true };
        }
    } catch (e) { /* ignore */ }
    return null;
}

// ── Online response handler — store in IndexedDB ──
export function handleOnlineResponse(action, params, response) {
    if (!response.success) return;
    try {
        if (action.includes('planning') && response.data?.length) {
            db.put('planning', response.data).catch(() => {});
        }
        if ((action.includes('message') || action === 'get_inbox' || action === 'get_sent') && response.data?.length) {
            db.put('messages', response.data).catch(() => {});
        }
    } catch (e) { /* ignore */ }
}

export function canQueue(action) {
    return QUEUEABLE_ACTIONS.includes(action);
}

export function enqueue(action, data) {
    return db.enqueue(action, data);
}

export function isOnline() {
    return _online;
}

export async function getQueueCount() {
    try { return await db.getQueueCount(); } catch { return 0; }
}

// ── Connection indicator update ──
function updateConnDot() {
    const dot = document.querySelector('#feConnStatus .fe-conn-dot');
    const status = document.getElementById('feConnStatus');
    if (!dot || !status) return;
    if (navigator.onLine) {
        dot.className = 'fe-conn-dot fe-conn-online';
        status.title = 'En ligne';
    } else {
        dot.className = 'fe-conn-dot fe-conn-offline';
        status.title = 'Hors ligne';
    }
}

// ── Init ──
export async function initOffline() {
    await db.open();
    _online = navigator.onLine;
    if (!_online) showOfflineBar();
    updateConnDot();

    window.addEventListener('online', async () => {
        _online = true;
        hideOfflineBar();
        updateConnDot();
        const queueLen = await db.getQueueCount();
        if (queueLen > 0) await syncQueue();
        deltaSync();
        _startPeriodicSync();
    });

    window.addEventListener('offline', () => {
        _online = false;
        showOfflineBar();
        updateConnDot();
        _stopPeriodicSync();
    });

    // Listen for SW sync messages
    navigator.serviceWorker?.addEventListener('message', evt => {
        if (evt.data?.type === 'SYNC_COMPLETE') {
            deltaSync();
            updateConnDot();
        }
    });

    // Initial sync if online
    if (_online) {
        deltaSync();
        _startPeriodicSync();
    }
}

function _startPeriodicSync() {
    _stopPeriodicSync();
    _syncTimer = setInterval(deltaSync, SYNC_INTERVAL);
}

function _stopPeriodicSync() {
    if (_syncTimer) { clearInterval(_syncTimer); _syncTimer = null; }
}

export { syncQueue, showOfflineBar, hideOfflineBar };
