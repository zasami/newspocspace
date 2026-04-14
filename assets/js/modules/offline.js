/**
 * SpocSpace — Offline-first module (complete)
 * Uses IndexedDB (ss-db) for local cache, delta sync with server.
 * All data types supported for full offline experience.
 */
import * as db from '../ss-db.js';

const SYNC_INTERVAL = 5 * 60 * 1000; // 5 min
let _syncTimer = null;
let _online = navigator.onLine;

// ══════════════════════════════════════════════════════════════
// Actions that can be served from IndexedDB when offline
// ══════════════════════════════════════════════════════════════
const OFFLINE_GET_ACTIONS = [
    // Planning
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
    // Repartition
    'get_repartition',
    // Auth & misc
    'me', 'get_horaires_types',
    // Annuaire
    'get_annuaire', 'search_annuaire',
];

// ══════════════════════════════════════════════════════════════
// Actions that can be queued when offline (with basic validation)
// ══════════════════════════════════════════════════════════════
const QUEUEABLE_ACTIONS = {
    // Desirs
    'submit_desir':            { required: ['date_souhaitee', 'horaire_type_id'] },
    'update_desir':            { required: ['id'] },
    'delete_desir':            { required: ['id'] },
    'submit_desir_permanent':  { required: ['jour_semaine', 'horaire_type_id'] },
    // Absences & vacances
    'submit_absence':          { required: ['date_debut', 'date_fin', 'type'] },
    'submit_vacances':         { required: ['date_debut', 'date_fin'] },
    'annuler_vacances':        { required: ['id'] },
    'modifier_vacances':       { required: ['id'] },
    // Messages
    'send_message':            { required: ['sujet', 'contenu'] },
    'mark_message_read':       { required: ['id'] },
    'mark_all_messages_read':  {},
    // Notifications
    'mark_notification_read':       { required: ['id'] },
    'mark_all_notifications_read':  {},
    'mark_alert_read':              { required: ['alert_id'] },
    // Changements
    'submit_changement':    { required: ['destinataire_id', 'date_origine'] },
    'confirmer_changement': { required: ['id'] },
    'refuser_changement':   { required: ['id'] },
    'annuler_changement':   { required: ['id'] },
    // Votes & sondages
    'submit_vote':              { required: ['proposal_id', 'choix'] },
    'submit_sondage_reponses':  { required: ['sondage_id'] },
    // Covoiturage
    'add_covoiturage_buddy':    { required: ['buddy_id'] },
    'remove_covoiturage_buddy': { required: ['buddy_id'] },
    'update_covoiturage_profile': {},
    // Mur social
    'create_mur_post':   { required: ['contenu'] },
    'toggle_mur_like':   { required: ['post_id'] },
    'add_mur_comment':   { required: ['post_id', 'contenu'] },
    'delete_mur_post':   { required: ['post_id'] },
    'delete_mur_comment': { required: ['comment_id'] },
    // Wiki
    'toggle_wiki_favori': { required: ['page_id'] },
    // Cuisine
    'reserver_menu':             { required: ['menu_id'] },
    'annuler_reservation_menu':  { required: ['reservation_id'] },
    // PV
    'rate_pv':    { required: ['pv_id', 'note'] },
    'comment_pv': { required: ['pv_id', 'contenu'] },
    // Profile
    'update_profile':  {},
    'update_password': { required: ['current_password', 'new_password'] },
};

// ══════════════════════════════════════════════════════════════
// Offline bar UI
// ══════════════════════════════════════════════════════════════
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

// ══════════════════════════════════════════════════════════════
// Sync toast UI — floating toast with direction arrows + progress
// ══════════════════════════════════════════════════════════════
let _syncToast = null;

function _ensureSyncToast() {
    if (_syncToast && document.body.contains(_syncToast)) return _syncToast;
    _syncToast = document.createElement('div');
    _syncToast.className = 'ss-sync-toast';
    _syncToast.innerHTML = `
        <div class="ss-sync-toast-header">
            <i class="bi bi-arrow-repeat ss-sync-toast-spinner"></i>
            <span class="ss-sync-toast-title">Synchronisation</span>
            <button class="ss-sync-toast-close" title="Fermer"><i class="bi bi-x"></i></button>
        </div>
        <div class="ss-sync-toast-body">
            <div class="ss-sync-toast-row" id="ssSyncDown">
                <i class="bi bi-arrow-down-circle ss-sync-icon-down"></i>
                <div class="ss-sync-toast-info">
                    <span class="ss-sync-toast-label">Telechargement</span>
                    <span class="ss-sync-toast-detail" id="ssSyncDownDetail">En attente...</span>
                </div>
            </div>
            <div class="ss-sync-toast-progress">
                <div class="ss-sync-toast-progress-fill ss-sync-down-fill" id="ssSyncDownFill"></div>
            </div>
            <div class="ss-sync-toast-row" id="ssSyncUp" style="display:none">
                <i class="bi bi-arrow-up-circle ss-sync-icon-up"></i>
                <div class="ss-sync-toast-info">
                    <span class="ss-sync-toast-label">Televersement</span>
                    <span class="ss-sync-toast-detail" id="ssSyncUpDetail">En attente...</span>
                </div>
            </div>
            <div class="ss-sync-toast-progress" id="ssSyncUpBar" style="display:none">
                <div class="ss-sync-toast-progress-fill ss-sync-up-fill" id="ssSyncUpFill"></div>
            </div>
        </div>
        <div class="ss-sync-toast-footer" id="ssSyncFooter"></div>`;
    document.body.appendChild(_syncToast);

    _syncToast.querySelector('.ss-sync-toast-close').addEventListener('click', () => {
        _syncToast.classList.remove('show');
        setTimeout(() => { _syncToast?.remove(); _syncToast = null; }, 300);
    });

    requestAnimationFrame(() => _syncToast.classList.add('show'));
    return _syncToast;
}

function _updateSyncDown(detail, percent) {
    const toast = _ensureSyncToast();
    const el = toast.querySelector('#ssSyncDownDetail');
    const fill = toast.querySelector('#ssSyncDownFill');
    if (el) el.textContent = detail;
    if (fill) fill.style.width = percent + '%';
}

function _updateSyncUp(detail, percent) {
    const toast = _ensureSyncToast();
    const upRow = toast.querySelector('#ssSyncUp');
    const upBar = toast.querySelector('#ssSyncUpBar');
    if (upRow) upRow.style.display = '';
    if (upBar) upBar.style.display = '';
    const el = toast.querySelector('#ssSyncUpDetail');
    const fill = toast.querySelector('#ssSyncUpFill');
    if (el) el.textContent = detail;
    if (fill) fill.style.width = percent + '%';
}

function _syncDone(message, status) {
    const toast = _ensureSyncToast();
    const footer = toast.querySelector('#ssSyncFooter');
    const spinner = toast.querySelector('.ss-sync-toast-spinner');
    if (spinner) {
        spinner.classList.remove('ss-sync-toast-spinner');
        spinner.className = status === 'success'
            ? 'bi bi-check-circle-fill ss-sync-icon-done'
            : 'bi bi-exclamation-circle-fill ss-sync-icon-warn';
    }
    const title = toast.querySelector('.ss-sync-toast-title');
    if (title) title.textContent = 'Synchronisation terminee';
    if (footer) footer.textContent = message;
    toast.classList.add('ss-sync-toast--' + status);
    // Auto-hide after 4s
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => { toast?.remove(); _syncToast = null; }, 300);
    }, 4000);
}

// ══════════════════════════════════════════════════════════════
// Delta sync — pull all data from server into IndexedDB
// ══════════════════════════════════════════════════════════════
let _deltaSyncRunning = false;

async function deltaSync(showToast = true) {
    if (!navigator.onLine || _deltaSyncRunning) return;
    _deltaSyncRunning = true;
    try {
        const { apiPost } = await import('../helpers.js');
        const lastSync = await db.getMeta('last_sync');

        if (showToast) _updateSyncDown('Recuperation des donnees...', 10);

        const res = await apiPost('sync_delta', { last_sync: lastSync });
        if (!res.success) { _deltaSyncRunning = false; return; }

        // Store each data type in its IndexedDB store
        const syncMap = {
            planning:        'planning',
            messages:        'messages',
            users:           'users',
            desirs:          'desirs',
            absences:        'absences',
            vacances:        'vacances',
            notifications:   'notifications',
            changements:     'changements',
            annonces:        'annonces',
            documents:       'documents',
            votes:           'votes',
            sondages:        'sondages',
            pv:              'pv',
            wiki_pages:      'wiki_pages',
            mur:             'mur',
            collegues:       'collegues',
            covoiturage:     'covoiturage',
            cuisine_menus:   'cuisine_menus',
            annuaire:        'annuaire',
        };

        const entries = Object.entries(syncMap);
        let stored = 0, totalItems = 0;

        for (const [key, store] of entries) {
            if (res[key]?.length) {
                await db.put(store, res[key]);
                totalItems += res[key].length;
            }
            stored++;
            if (showToast) {
                const pct = Math.round(10 + (stored / entries.length) * 80);
                _updateSyncDown(`${totalItems} elements recus...`, pct);
            }
        }

        // Special stores
        if (res.wiki_categories?.length) {
            await db.setMeta('wiki_categories', res.wiki_categories);
            totalItems += res.wiki_categories.length;
        }
        if (res.horaires?.length) {
            await db.setMeta('horaires', res.horaires);
            totalItems += res.horaires.length;
        }
        if (res.fiches_salaire?.length) {
            await db.setMeta('fiches_salaire', res.fiches_salaire);
            totalItems += res.fiches_salaire.length;
        }

        if (res.timestamp) await db.setMeta('last_sync', res.timestamp);
        await db.cleanup();

        if (showToast) {
            _updateSyncDown(`${totalItems} elements synchronises`, 100);
        }
        _updateSyncIndicator();
    } catch (e) {
        console.warn('[offline] deltaSync error:', e);
        if (showToast) _updateSyncDown('Erreur de synchronisation', 0);
    }
    _deltaSyncRunning = false;
}

// ══════════════════════════════════════════════════════════════
// Sync queue — push queued offline actions to server
// ══════════════════════════════════════════════════════════════
async function syncQueue() {
    const queue = await db.getQueue();
    if (!queue.length) return;

    const { apiPost } = await import('../helpers.js');
    let success = 0, failed = 0, conflicts = 0;

    _updateSyncUp(`0 / ${queue.length} actions...`, 0);

    for (let i = 0; i < queue.length; i++) {
        const item = queue[i];
        try {
            const res = await apiPost(item.action, item.data);
            if (res.success) {
                success++;
                await db.dequeue(item.id);
            } else if (res.conflict) {
                conflicts++;
                await db.dequeue(item.id);
                _showConflictToast(item.action, res.message || 'Conflit resolu par le serveur');
            } else {
                failed++;
                await db.put('sync_queue', { ...item, _error: res.message, _errorAt: Date.now() });
            }
        } catch (e) {
            failed++;
            break; // still offline, stop
        }
        const pct = Math.round(((i + 1) / queue.length) * 100);
        _updateSyncUp(`${i + 1} / ${queue.length} actions envoyees`, pct);
    }

    // Final status
    if (failed > 0 || conflicts > 0) {
        const parts = [];
        if (success) parts.push(`${success} OK`);
        if (conflicts) parts.push(`${conflicts} conflit(s)`);
        if (failed) parts.push(`${failed} erreur(s)`);
        _updateSyncUp(parts.join(', '), 100);
    } else {
        _updateSyncUp(`${success} action(s) envoyee(s)`, 100);
    }

    // Re-sync data after pushing changes
    if (success > 0) await deltaSync(false);
}

function _showConflictToast(action, message) {
    const actionLabels = {
        'submit_desir': 'Desir', 'update_desir': 'Desir', 'submit_absence': 'Absence',
        'submit_changement': 'Changement', 'send_message': 'Message',
    };
    const label = actionLabels[action] || action;
    try {
        import('../helpers.js').then(({ toast }) => {
            toast(`${label}: ${message}`, 4000);
        });
    } catch (e) { /* silent */ }
}

// ══════════════════════════════════════════════════════════════
// Offline GET handler — serve data from IndexedDB
// ══════════════════════════════════════════════════════════════
export function handleOfflineGet(action, params) {
    if (!OFFLINE_GET_ACTIONS.includes(action)) return null;
    return _resolveOfflineGet(action, params);
}

async function _resolveOfflineGet(action, params) {
    try {
        // Planning
        if (action.includes('planning') || action === 'get_repartition') {
            const all = await db.getAll('planning');
            return all.length ? { success: true, data: all, _fromCache: true } : null;
        }
        // Messages
        if (action.includes('message') || action === 'get_inbox' || action === 'get_sent') {
            const all = await db.getAll('messages');
            if (action === 'get_unread_count') {
                return { success: true, count: 0, _fromCache: true };
            }
            return all.length ? { success: true, data: all, _fromCache: true } : null;
        }
        // Desirs
        if (action.includes('desir')) {
            const all = await db.getAll('desirs');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Absences
        if (action.includes('absence')) {
            const all = await db.getAll('absences');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Vacances
        if (action.includes('vacance')) {
            const all = await db.getAll('vacances');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Notifications
        if (action.includes('notification') || action === 'get_poll_data' || action === 'get_pending_alerts') {
            if (action === 'get_notifications_count' || action === 'get_poll_data') {
                const notifs = await db.getAll('notifications');
                const unread = notifs.filter(n => !n.read_at).length;
                return { success: true, unread_notifs: unread, unread_messages: 0, pending_ack: 0, alerts: [], _fromCache: true };
            }
            if (action === 'get_pending_alerts') {
                return { success: true, alerts: [], _fromCache: true };
            }
            const all = await db.getAll('notifications');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Changements
        if (action.includes('changement')) {
            const all = await db.getAll('changements');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Collegues
        if (action === 'get_collegues') {
            const all = await db.getAll('collegues');
            if (all.length) return { success: true, data: all, _fromCache: true };
            // Fallback to users store
            const users = await db.getAll('users');
            return { success: true, data: users || [], _fromCache: true };
        }
        // Covoiturage
        if (action.includes('covoiturage')) {
            const all = await db.getAll('covoiturage');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Votes
        if (action.includes('proposal') || action.includes('vote')) {
            const all = await db.getAll('votes');
            return { success: true, data: all || [], _fromCache: true };
        }
        // PV
        if (action.includes('pv')) {
            const all = await db.getAll('pv');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Sondages
        if (action.includes('sondage')) {
            const all = await db.getAll('sondages');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Documents
        if (action.includes('document')) {
            const all = await db.getAll('documents');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Fiches salaire
        if (action.includes('fiche')) {
            const fiches = await db.getMeta('fiches_salaire');
            return { success: true, data: fiches || [], _fromCache: true };
        }
        // Annonces
        if (action.includes('annonce')) {
            const all = await db.getAll('annonces');
            if (action === 'get_annonce_detail' && params?.id) {
                const detail = all.find(a => a.id === params.id);
                return detail ? { success: true, annonce: detail, _fromCache: true } : null;
            }
            return { success: true, data: all || [], _fromCache: true };
        }
        // Wiki
        if (action.includes('wiki')) {
            if (action === 'get_wiki_categories') {
                const cats = await db.getMeta('wiki_categories');
                return { success: true, data: cats || [], _fromCache: true };
            }
            if (action === 'get_wiki_page' && params?.id) {
                const pages = await db.getAll('wiki_pages');
                const page = pages.find(p => p.id === params.id || p.slug === params.slug);
                return page ? { success: true, page, _fromCache: true } : null;
            }
            const all = await db.getAll('wiki_pages');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Mur social
        if (action.includes('mur')) {
            const all = await db.getAll('mur');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Cuisine menus
        if (action.includes('menu') || action.includes('cuisine')) {
            const all = await db.getAll('cuisine_menus');
            return { success: true, data: all || [], _fromCache: true };
        }
        // Annuaire
        if (action.includes('annuaire')) {
            let all = await db.getAll('annuaire');
            if (action === 'search_annuaire' && params?.q) {
                const q = params.q.toLowerCase();
                all = all.filter(i =>
                    [i.nom, i.prenom, i.fonction, i.service, i.telephone_1, i.telephone_2, i.email, i.categorie]
                        .filter(Boolean).some(v => v.toLowerCase().includes(q))
                );
            } else if (params?.type) {
                all = all.filter(i => i.type === params.type);
            }
            return { success: true, data: all || [], _fromCache: true };
        }
        // Horaires
        if (action === 'get_horaires_types') {
            const h = await db.getMeta('horaires');
            return h ? { success: true, data: h, _fromCache: true } : null;
        }
        // Me
        if (action === 'me') {
            const token = await db.getAuthToken();
            if (token) return {
                success: true,
                user: { id: token.userId, prenom: token.prenom, nom: token.nom, email: token.email, role: token.role, taux: token.taux, fonction_id: token.fonction_id, type_employe: token.type_employe },
                _fromCache: true,
            };
        }
    } catch (e) { /* ignore */ }
    return null;
}

// ══════════════════════════════════════════════════════════════
// Online response handler — store in IndexedDB for offline use
// ══════════════════════════════════════════════════════════════
export function handleOnlineResponse(action, params, response) {
    if (!response.success) return;
    try {
        // Map action patterns to stores
        const storeMap = [
            [/planning|repartition/, 'planning'],
            [/message|inbox|sent/, 'messages'],
            [/desir/, 'desirs'],
            [/absence/, 'absences'],
            [/vacance/, 'vacances'],
            [/notification/, 'notifications'],
            [/changement/, 'changements'],
            [/annonce/, 'annonces'],
            [/document/, 'documents'],
            [/proposal|vote/, 'votes'],
            [/sondage/, 'sondages'],
            [/^get_pv/, 'pv'],
            [/wiki_page/, 'wiki_pages'],
            [/mur/, 'mur'],
            [/collegue/, 'collegues'],
            [/covoiturage/, 'covoiturage'],
            [/menu|cuisine/, 'cuisine_menus'],
        ];

        const data = response.data || response.items || response.results;
        if (!Array.isArray(data) || !data.length) return;

        for (const [pattern, store] of storeMap) {
            if (pattern.test(action)) {
                db.put(store, data).catch(() => {});
                return;
            }
        }
    } catch (e) { /* ignore */ }
}

// ══════════════════════════════════════════════════════════════
// Queue management with validation
// ══════════════════════════════════════════════════════════════
export function canQueue(action) {
    return action in QUEUEABLE_ACTIONS;
}

export function enqueue(action, data) {
    // Basic validation before queueing
    const rules = QUEUEABLE_ACTIONS[action];
    if (rules?.required) {
        for (const field of rules.required) {
            if (!data[field] && data[field] !== 0 && data[field] !== false) {
                return Promise.reject(new Error(`Champ requis manquant: ${field}`));
            }
        }
    }
    return db.enqueue(action, data);
}

export function isOnline() {
    return _online;
}

export async function getQueueCount() {
    try { return await db.getQueueCount(); } catch { return 0; }
}

// ══════════════════════════════════════════════════════════════
// Connection + sync indicator updates
// ══════════════════════════════════════════════════════════════
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
    _updateSyncIndicator();
}

function _updateSyncIndicator() {
    const el = document.getElementById('feSyncIndicator');
    const timeEl = document.getElementById('feSyncTime');
    if (!el || !timeEl) return;

    if (!navigator.onLine) {
        el.style.display = '';
        el.className = 'fe-sync-indicator offline';
        timeEl.textContent = 'Hors ligne';
        return;
    }

    db.getMeta('last_sync').then(lastSync => {
        if (!lastSync) {
            el.style.display = 'none';
            return;
        }
        el.style.display = '';
        el.className = 'fe-sync-indicator synced';
        timeEl.textContent = _formatTimeAgo(lastSync);
        el.title = 'Derniere synchronisation : ' + new Date(lastSync).toLocaleString('fr-CH');
    }).catch(() => {});
}

function _formatTimeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const sec = Math.floor(diff / 1000);
    if (sec < 30) return 'A l\'instant';
    if (sec < 60) return 'Il y a ' + sec + 's';
    const min = Math.floor(sec / 60);
    if (min < 60) return 'Il y a ' + min + ' min';
    const hrs = Math.floor(min / 60);
    if (hrs < 24) return 'Il y a ' + hrs + 'h';
    return 'Il y a ' + Math.floor(hrs / 24) + 'j';
}

// Refresh sync indicator every 30s
setInterval(() => _updateSyncIndicator(), 30000);

// ══════════════════════════════════════════════════════════════
// Init
// ══════════════════════════════════════════════════════════════
export async function initOffline() {
    console.log('[offline] initOffline starting...');
    await db.open();
    _online = navigator.onLine;
    console.log('[offline] online:', _online);
    if (!_online) showOfflineBar();
    updateConnDot();

    window.addEventListener('online', async () => {
        _online = true;
        hideOfflineBar();
        updateConnDot();
        // Re-validate session with server silently
        try {
            const { apiPost } = await import('../helpers.js');
            const res = await apiPost('me');
            if (res.success && res.user) {
                // Refresh auth token on reconnect
                await db.saveAuthToken(res.user.id, {
                    email: res.user.email, role: res.user.role,
                    prenom: res.user.prenom, nom: res.user.nom,
                    taux: res.user.taux, fonction_id: res.user.fonction_id,
                    type_employe: res.user.type_employe, photo: res.user.photo,
                });
            }
        } catch (e) { /* silent */ }
        // Sync: upload queued actions first, then download fresh data
        const queueLen = await db.getQueueCount();
        if (queueLen > 0) {
            await syncQueue();
            // deltaSync already called inside syncQueue on success
        } else {
            await deltaSync(true);
        }
        // Show final status
        _syncDone('Donnees a jour', 'success');
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
            deltaSync(false);
            updateConnDot();
        }
    });

    // Click sync indicator to force sync
    document.getElementById('feSyncIndicator')?.addEventListener('click', async () => {
        if (!navigator.onLine || _deltaSyncRunning) return;
        const el = document.getElementById('feSyncIndicator');
        if (el) el.className = 'fe-sync-indicator syncing';
        await deltaSync(true);
        _syncDone('Donnees synchronisees', 'success');
    });

    // Initial sync if online — show toast on first load so user sees data syncing
    if (_online) {
        try {
            const lastSync = await db.getMeta('last_sync');
            const showInitialToast = !lastSync || (Date.now() - new Date(lastSync).getTime() > 60000);
            console.log('[offline] initial sync, showToast:', showInitialToast, 'lastSync:', lastSync);
            await deltaSync(showInitialToast);
            if (showInitialToast) {
                _syncDone('Donnees synchronisees', 'success');
            }
            _updateSyncIndicator();
        } catch (e) {
            console.error('[offline] initial sync failed:', e);
        }
        _startPeriodicSync();
    }
}

function _startPeriodicSync() {
    _stopPeriodicSync();
    // Periodic sync is silent (no toast) — toast only shows on reconnect
    _syncTimer = setInterval(() => deltaSync(false), SYNC_INTERVAL);
}

function _stopPeriodicSync() {
    if (_syncTimer) { clearInterval(_syncTimer); _syncTimer = null; }
}

export { syncQueue, showOfflineBar, hideOfflineBar };
