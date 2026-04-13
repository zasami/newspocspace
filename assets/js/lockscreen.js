/**
 * SpocSpace — Lock Screen
 * Auto-lock after 15 min inactivity. Unlock via password (PBKDF2 local).
 *
 * Timeline:
 *   0-15 min inactivity  → app open, no lock
 *   15 min - 72h         → lock screen → password required (offline PBKDF2)
 *   > 72h                → full re-login required
 */
import * as db from './ss-db.js';

const LOCK_TIMEOUT = 15 * 60 * 1000; // 15 minutes
const CHECK_INTERVAL = 30 * 1000;     // check every 30s

let _lockEl = null;
let _locked = false;
let _checkTimer = null;
let _activityThrottle = 0;

// ══════════════════════════════════════════════════════════════
// Activity tracking — throttled to avoid spamming IndexedDB
// ══════════════════════════════════════════════════════════════
function _onActivity() {
    const now = Date.now();
    // Only write to IDB at most once every 30s
    if (now - _activityThrottle < 30000) return;
    _activityThrottle = now;
    db.touchActivity().catch(() => {});
}

function _startActivityTracking() {
    const events = ['click', 'keydown', 'scroll', 'touchstart'];
    events.forEach(evt => document.addEventListener(evt, _onActivity, { passive: true }));
    // Immediate touch on start
    _onActivity();
}

function _stopActivityTracking() {
    const events = ['click', 'keydown', 'scroll', 'touchstart'];
    events.forEach(evt => document.removeEventListener(evt, _onActivity));
}

// ══════════════════════════════════════════════════════════════
// Lock check — runs periodically + on visibility change
// ══════════════════════════════════════════════════════════════
async function _checkLock() {
    if (_locked) return;
    if (!window.__SS__?.user) return;

    const lastActivity = await db.getLastActivity();
    const elapsed = Date.now() - lastActivity;

    if (elapsed > LOCK_TIMEOUT) {
        lock();
    }
}

// ══════════════════════════════════════════════════════════════
// Lock
// ══════════════════════════════════════════════════════════════
function lock() {
    if (_locked) return;
    _locked = true;

    const user = window.__SS__?.user;
    if (!user) return;

    const initials = (user.prenom || '').charAt(0) + (user.nom || '').charAt(0);
    const fullName = ((user.prenom || '') + ' ' + (user.nom || '')).trim();
    const isOffline = !navigator.onLine;

    _lockEl = document.createElement('div');
    _lockEl.className = 'ss-lockscreen';
    _lockEl.innerHTML = `
        <div class="ss-lockscreen-card">
            ${user.photo
                ? `<img src="${_esc(user.photo)}" class="ss-lockscreen-avatar" alt="">`
                : `<div class="ss-lockscreen-avatar">${_esc(initials)}</div>`
            }
            <div class="ss-lockscreen-name">${_esc(fullName)}</div>
            <div class="ss-lockscreen-hint">Session verrouill\u00e9e par inactivit\u00e9</div>
            <form id="ssLockForm" autocomplete="off">
                <input type="password" class="ss-lockscreen-input" id="ssLockPwd"
                       placeholder="Mot de passe" autocomplete="current-password" autofocus>
                <button type="submit" class="ss-lockscreen-btn" id="ssLockBtn">
                    D\u00e9verrouiller
                </button>
                <div class="ss-lockscreen-error" id="ssLockError"></div>
            </form>
            <div class="ss-lockscreen-status">
                <span class="fe-conn-dot ${isOffline ? 'fe-conn-offline' : 'fe-conn-online'}"></span>
                ${isOffline ? 'Hors ligne' : 'En ligne'}
            </div>
            <button class="ss-lockscreen-logout" id="ssLockLogout">Se d\u00e9connecter</button>
        </div>`;

    document.body.appendChild(_lockEl);
    requestAnimationFrame(() => _lockEl.classList.add('show'));

    // Focus password field
    setTimeout(() => document.getElementById('ssLockPwd')?.focus(), 100);

    // Form submit
    document.getElementById('ssLockForm').addEventListener('submit', _handleUnlock);

    // Logout button
    document.getElementById('ssLockLogout').addEventListener('click', _handleLogout);

    // Update online/offline status live
    window.addEventListener('online', _updateLockStatus);
    window.addEventListener('offline', _updateLockStatus);
}

// ══════════════════════════════════════════════════════════════
// Unlock
// ══════════════════════════════════════════════════════════════
async function _handleUnlock(e) {
    e.preventDefault();
    const input = document.getElementById('ssLockPwd');
    const btn = document.getElementById('ssLockBtn');
    const errorEl = document.getElementById('ssLockError');
    const password = input.value;

    if (!password) { input.focus(); return; }

    btn.disabled = true;
    btn.textContent = 'Verification...';
    errorEl.textContent = '';

    let verified = false;

    if (navigator.onLine) {
        // Online: verify with server (also refreshes session)
        try {
            const { apiPost } = await import('./helpers.js');
            const res = await apiPost('login', {
                email: window.__SS__.user.email,
                password,
            });
            if (res.success) {
                verified = true;
                // Refresh CSRF + session
                if (res.csrf) window.__SS__.csrfToken = res.csrf;
                // Update offline password hash too
                await db.savePasswordHash(window.__SS__.user.email, password);
            }
        } catch (e) {
            // Network error — fall through to offline check
        }
    }

    if (!verified) {
        // Offline (or online check failed): verify locally with PBKDF2
        try {
            verified = await db.verifyPasswordOffline(window.__SS__.user.email, password);
        } catch (e) { /* PBKDF2 failed */ }
    }

    if (verified) {
        unlock();
    } else {
        input.classList.add('error');
        errorEl.textContent = 'Mot de passe incorrect';
        btn.disabled = false;
        btn.textContent = 'Deverrouiller';
        setTimeout(() => input.classList.remove('error'), 500);
        input.value = '';
        input.focus();
    }
}

function unlock() {
    _locked = false;
    _onActivity(); // Reset activity timer

    if (_lockEl) {
        _lockEl.classList.remove('show');
        setTimeout(() => { _lockEl?.remove(); _lockEl = null; }, 300);
    }

    window.removeEventListener('online', _updateLockStatus);
    window.removeEventListener('offline', _updateLockStatus);
}

async function _handleLogout() {
    try {
        const { apiPost } = await import('./helpers.js');
        await apiPost('logout');
    } catch (e) { /* offline logout */ }
    await db.clearAuthToken();
    window.__SS__.user = null;
    window.location.href = '/spocspace/login';
}

function _updateLockStatus() {
    const statusEl = _lockEl?.querySelector('.ss-lockscreen-status');
    if (!statusEl) return;
    const isOffline = !navigator.onLine;
    statusEl.innerHTML = `
        <span class="fe-conn-dot ${isOffline ? 'fe-conn-offline' : 'fe-conn-online'}"></span>
        ${isOffline ? 'Hors ligne' : 'En ligne'}`;
}

function _esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ══════════════════════════════════════════════════════════════
// Public API
// ══════════════════════════════════════════════════════════════
export function isLocked() { return _locked; }

export async function initLockScreen() {
    if (!window.__SS__?.user) return;

    // Check if we should lock immediately (returning from background/sleep)
    const lastActivity = await db.getLastActivity();
    if (lastActivity && (Date.now() - lastActivity > LOCK_TIMEOUT)) {
        lock();
    } else {
        // Fresh activity
        _onActivity();
    }

    _startActivityTracking();

    // Periodic check
    _checkTimer = setInterval(_checkLock, CHECK_INTERVAL);

    // Also check on tab/app becoming visible (returning from background)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            _checkLock();
        }
    });
}

export function destroyLockScreen() {
    _stopActivityTracking();
    if (_checkTimer) { clearInterval(_checkTimer); _checkTimer = null; }
    if (_lockEl) { _lockEl.remove(); _lockEl = null; }
    _locked = false;
}
