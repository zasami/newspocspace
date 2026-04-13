/**
 * SpocSpace — Lock Screen
 * Auto-locks after 15 min of inactivity.
 * Unlock via password (verified locally with PBKDF2).
 *
 * Timings:
 *   0 - 15 min inactivity  →  app stays open
 *   15 min - 72h            →  lock screen (password to unlock)
 *   > 72h                   →  full login required (redirect to /login)
 */
import * as db from './ss-db.js';

const LOCK_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const TOKEN_MAX_MS = 72 * 3600 * 1000;  // 72 hours
let _lockTimer = null;
let _locked = false;
let _overlay = null;

// ── Activity tracking (debounced) ──
let _activityDebounce = null;

function _onActivity() {
    if (_locked) return;
    clearTimeout(_activityDebounce);
    _activityDebounce = setTimeout(() => {
        db.touchActivity().catch(() => {});
    }, 5000); // Debounce: save at most every 5s
    _resetLockTimer();
}

function _resetLockTimer() {
    clearTimeout(_lockTimer);
    _lockTimer = setTimeout(() => lock(), LOCK_TIMEOUT_MS);
}

// ── Lock ──
export function lock() {
    if (_locked) return;
    if (!window.__SS__?.user) return; // Not logged in, nothing to lock
    _locked = true;
    _showLockOverlay();
}

// ── Unlock ──
async function _tryUnlock(password) {
    const email = window.__SS__?.user?.email;
    if (!email) return false;

    const ok = await db.verifyPasswordOffline(email, password);
    if (ok) {
        _locked = false;
        _hideLockOverlay();
        db.touchActivity().catch(() => {});
        _resetLockTimer();
        return true;
    }
    return false;
}

// ── Check on init: should we lock immediately? ──
export async function initLockScreen() {
    if (!window.__SS__?.user) return;

    // Check last activity
    const lastActivity = await db.getLastActivity();
    const now = Date.now();
    const elapsed = now - lastActivity;

    if (lastActivity && elapsed > TOKEN_MAX_MS) {
        // Token expired → full login
        window.location.href = '/spocspace/login';
        return;
    }

    if (lastActivity && elapsed > LOCK_TIMEOUT_MS) {
        // Inactive > 15 min → lock
        lock();
    } else {
        // Fresh → start timer
        db.touchActivity().catch(() => {});
        _resetLockTimer();
    }

    // Listen for user activity
    const events = ['click', 'keydown', 'scroll', 'touchstart', 'mousemove'];
    events.forEach(evt => {
        document.addEventListener(evt, _onActivity, { passive: true });
    });

    // Lock when tab becomes hidden (optional extra security)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            // Don't lock immediately on tab switch, but save activity
            db.touchActivity().catch(() => {});
        }
    });
}

// ── Lock overlay UI ──
function _showLockOverlay() {
    if (_overlay) return;

    const user = window.__SS__?.user;
    const initials = ((user?.prenom || '')[0] || '') + ((user?.nom || '')[0] || '');
    const displayName = [user?.prenom, user?.nom].filter(Boolean).join(' ') || '';

    _overlay = document.createElement('div');
    _overlay.id = 'ssLockScreen';
    _overlay.innerHTML = `
        <div class="ss-lock-card">
            <div class="ss-lock-avatar">${_escHtml(initials)}</div>
            <div class="ss-lock-name">${_escHtml(displayName)}</div>
            <div class="ss-lock-hint">Session verrouill\u00e9e par inactivit\u00e9</div>
            <form id="ssLockForm" autocomplete="off">
                <div class="ss-lock-input-wrap">
                    <input type="password" id="ssLockPwd" class="ss-lock-input"
                           placeholder="Mot de passe" autocomplete="current-password" autofocus>
                    <button type="button" class="ss-lock-eye" id="ssLockEye" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="ss-lock-error" id="ssLockError"></div>
                <button type="submit" class="ss-lock-btn" id="ssLockBtn">
                    <i class="bi bi-unlock"></i> D\u00e9verrouiller
                </button>
            </form>
            <div class="ss-lock-footer">
                <a href="/spocspace/login" class="ss-lock-switch">Changer de compte</a>
            </div>
        </div>
    `;

    document.body.appendChild(_overlay);
    requestAnimationFrame(() => _overlay.classList.add('show'));

    // Focus password input
    setTimeout(() => document.getElementById('ssLockPwd')?.focus(), 100);

    // Form submit
    document.getElementById('ssLockForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const pwd = document.getElementById('ssLockPwd').value;
        const btn = document.getElementById('ssLockBtn');
        const err = document.getElementById('ssLockError');
        if (!pwd) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> V\u00e9rification...';
        err.textContent = '';

        const ok = await _tryUnlock(pwd);
        if (!ok) {
            err.textContent = 'Mot de passe incorrect';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-unlock"></i> D\u00e9verrouiller';
            document.getElementById('ssLockPwd').select();
        }
    });

    // Eye toggle
    document.getElementById('ssLockEye')?.addEventListener('click', () => {
        const input = document.getElementById('ssLockPwd');
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        document.querySelector('#ssLockEye i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Enter to submit
    document.getElementById('ssLockPwd')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('ssLockForm')?.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
}

function _hideLockOverlay() {
    if (!_overlay) return;
    _overlay.classList.remove('show');
    setTimeout(() => { _overlay?.remove(); _overlay = null; }, 300);
}

function _escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

export function isLocked() {
    return _locked;
}
