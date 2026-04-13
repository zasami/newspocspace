/**
 * SpocSpace — IndexedDB local database
 * Cache temporaire de travail — la source de verite est le serveur
 */
const DB_NAME = 'ss_local';
const DB_VERSION = 2;

const STORES = {
    planning:      { keyPath: 'id', indexes: ['date_jour', 'user_id'] },
    messages:      { keyPath: 'id', indexes: ['created_at'] },
    users:         { keyPath: 'id', indexes: ['nom'] },
    desirs:        { keyPath: 'id', indexes: ['mois'] },
    absences:      { keyPath: 'id', indexes: ['date_debut'] },
    vacances:      { keyPath: 'id', indexes: ['annee'] },
    notifications: { keyPath: 'id', indexes: ['created_at'] },
    changements:   { keyPath: 'id', indexes: ['created_at'] },
    annonces:      { keyPath: 'id', indexes: ['created_at'] },
    documents:     { keyPath: 'id', indexes: ['service_id'] },
    votes:         { keyPath: 'id', indexes: ['created_at'] },
    sondages:      { keyPath: 'id', indexes: ['created_at'] },
    pv:            { keyPath: 'id', indexes: ['date_reunion'] },
    wiki_pages:    { keyPath: 'id', indexes: ['category_id'] },
    mur:           { keyPath: 'id', indexes: ['created_at'] },
    collegues:     { keyPath: 'id', indexes: ['nom'] },
    covoiturage:   { keyPath: 'id', indexes: ['user_id'] },
    cuisine_menus: { keyPath: 'id', indexes: ['date_menu'] },
    sync_queue:    { keyPath: 'id', autoIncrement: true },
    meta:          { keyPath: 'key' },
};

let _db = null;

function open() {
    if (_db) return Promise.resolve(_db);
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            for (const [name, opts] of Object.entries(STORES)) {
                if (db.objectStoreNames.contains(name)) continue;
                const cfg = { keyPath: opts.keyPath };
                if (opts.autoIncrement) cfg.autoIncrement = true;
                const store = db.createObjectStore(name, cfg);
                if (opts.indexes) opts.indexes.forEach(idx => store.createIndex(idx, idx, { unique: false }));
            }
        };
        req.onsuccess = () => { _db = req.result; resolve(_db); };
        req.onerror = () => reject(req.error);
    });
}

function _tx(store, mode = 'readonly') {
    return open().then(db => {
        const tx = db.transaction(store, mode);
        return tx.objectStore(store);
    });
}

async function getAll(store) {
    const s = await _tx(store);
    return new Promise((res, rej) => { const r = s.getAll(); r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); });
}

async function get(store, key) {
    const s = await _tx(store);
    return new Promise((res, rej) => { const r = s.get(key); r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); });
}

async function put(store, data) {
    const s = await _tx(store, 'readwrite');
    const items = Array.isArray(data) ? data : [data];
    return new Promise((res, rej) => {
        items.forEach(item => s.put(item));
        s.transaction.oncomplete = () => res();
        s.transaction.onerror = () => rej(s.transaction.error);
    });
}

async function remove(store, key) {
    const s = await _tx(store, 'readwrite');
    return new Promise((res, rej) => { const r = s.delete(key); r.onsuccess = () => res(); r.onerror = () => rej(r.error); });
}

async function clear(store) {
    const s = await _tx(store, 'readwrite');
    return new Promise((res, rej) => { const r = s.clear(); r.onsuccess = () => res(); r.onerror = () => rej(r.error); });
}

async function count(store) {
    const s = await _tx(store);
    return new Promise((res, rej) => { const r = s.count(); r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error); });
}

// ── Meta helpers ──
async function getMeta(key) {
    const row = await get('meta', key);
    return row ? row.value : null;
}

async function setMeta(key, value) {
    return put('meta', { key, value });
}

// ── Sync queue helpers ──
async function enqueue(action, data) {
    return put('sync_queue', { action, data, ts: Date.now() });
}

async function getQueue() {
    return getAll('sync_queue');
}

async function dequeue(id) {
    return remove('sync_queue', id);
}

async function getQueueCount() {
    return count('sync_queue');
}

// ── Auth token helpers ──
// ── Auth token — 72h for offline access ──
async function saveAuthToken(userId, tokenData) {
    const secret = crypto.getRandomValues(new Uint8Array(16));
    const secretHex = Array.from(secret).map(b => b.toString(16).padStart(2, '0')).join('');
    const raw = userId + ':' + Date.now() + ':' + secretHex;
    const hashBuf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(raw));
    const hash = Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('');
    await setMeta('auth_token', {
        userId,
        email: tokenData.email,
        role: tokenData.role,
        prenom: tokenData.prenom,
        nom: tokenData.nom,
        taux: tokenData.taux,
        fonction_id: tokenData.fonction_id,
        type_employe: tokenData.type_employe,
        photo: tokenData.photo,
        hash,
        createdAt: Date.now(),
        expiresAt: Date.now() + 72 * 3600 * 1000, // 72h offline access
    });
}

async function getAuthToken() {
    const token = await getMeta('auth_token');
    if (!token) return null;
    if (Date.now() > token.expiresAt) { await clearAuthToken(); return null; }
    return token;
}

async function clearAuthToken() {
    return remove('meta', 'auth_token');
}

// ── Offline login — PBKDF2 600k iterations (OWASP 2024 recommendation) ──
const PBKDF2_ITERATIONS = 600000;

async function _deriveKey(password, salt) {
    const enc = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
        { name: 'PBKDF2', salt: enc.encode(salt), iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
        keyMaterial, 256
    );
    return Array.from(new Uint8Array(bits)).map(b => b.toString(16).padStart(2, '0')).join('');
}

async function savePasswordHash(email, password) {
    // Random salt per user — stored alongside the hash
    const saltBytes = crypto.getRandomValues(new Uint8Array(16));
    const salt = Array.from(saltBytes).map(b => b.toString(16).padStart(2, '0')).join('');
    const hash = await _deriveKey(password, salt + ':' + email.toLowerCase().trim());
    await setMeta('offline_pwd', { email: email.toLowerCase().trim(), salt, hash });
}

async function verifyPasswordOffline(email, password) {
    const stored = await getMeta('offline_pwd');
    if (!stored || stored.email !== email.toLowerCase().trim()) return false;
    const hash = await _deriveKey(password, stored.salt + ':' + email.toLowerCase().trim());
    return hash === stored.hash;
}

// ── Save full shell data (__SS__) for offline boot ──
async function saveShellData(ssData) {
    await setMeta('shell_data', { ...ssData, savedAt: Date.now() });
}

async function getShellData() {
    return getMeta('shell_data');
}

// ── Activity tracking for auto-lock ──
const AUTO_LOCK_KEY = 'last_activity';

async function touchActivity() {
    await setMeta(AUTO_LOCK_KEY, Date.now());
}

async function getLastActivity() {
    return (await getMeta(AUTO_LOCK_KEY)) || 0;
}

// ── Cleanup — remove data older than 30 days ──
async function cleanup() {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - 30);
    const cutoffStr = cutoff.toISOString();

    const msgs = await getAll('messages');
    const old = msgs.filter(m => m.created_at && m.created_at < cutoffStr);
    if (old.length) {
        const s = await _tx('messages', 'readwrite');
        old.forEach(m => s.delete(m.id));
    }
}

export {
    open, getAll, get, put, remove, clear, count,
    getMeta, setMeta,
    enqueue, getQueue, dequeue, getQueueCount,
    saveAuthToken, getAuthToken, clearAuthToken,
    savePasswordHash, verifyPasswordOffline,
    saveShellData, getShellData,
    touchActivity, getLastActivity,
    cleanup,
};
