/**
 * Auth module - Login (with offline support)
 */
import { apiPost, toast } from '../helpers.js';
import * as db from '../ss-db.js';

const AUTO_LOGIN_WINDOW = 15 * 60 * 1000; // 15 min — auto-login sans mot de passe

export async function init() {
    // ── Auto-login: if offline + valid token + recent activity (< 15 min) ──
    if (!navigator.onLine) {
        const token = await db.getAuthToken();
        if (token) {
            const shellData = await db.getShellData();
            const lastActivity = await db.getLastActivity();
            const recentActivity = lastActivity && (Date.now() - lastActivity < AUTO_LOGIN_WINDOW);

            if (shellData && recentActivity) {
                // Recent activity — auto-login direct, pas de mot de passe
                window.__SS__ = {
                    ...window.__SS__,
                    user: {
                        id: token.userId, prenom: token.prenom, nom: token.nom,
                        email: token.email, role: token.role, taux: token.taux,
                        fonction_id: token.fonction_id, type_employe: token.type_employe,
                    },
                    csrfToken: shellData.csrfToken || '',
                    canChangement: shellData.canChangement || false,
                    deniedPerms: shellData.deniedPerms || [],
                    pageLabels: shellData.pageLabels || {},
                };
                await db.touchActivity();
                window.location.href = '/spocspace/home';
                return;
            }
            // Token valid but inactive > 15 min — show login form (offline validation)
        }
    }

    // Eye toggle for password field
    document.querySelectorAll('.pwd-eye').forEach(el => {
        el.addEventListener('click', () => {
            const input = document.getElementById(el.dataset.target);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            el.querySelector('i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Auto-fill from URL params (demo access)
    const urlParams = window.__SS_INITIAL_PARAMS__ || new URLSearchParams(window.location.search);
    const autoEmail = urlParams.get('email');
    const autoPwd = urlParams.get('pwd');
    if (autoEmail) document.getElementById('loginEmail').value = autoEmail;
    if (autoPwd) document.getElementById('loginPassword').value = autoPwd;
    if (autoEmail && autoPwd) {
        setTimeout(() => document.getElementById('loginForm')?.dispatchEvent(new Event('submit', { cancelable: true })), 500);
    }

    const form = document.getElementById('loginForm');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const errorEl = document.getElementById('loginError');
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;

        if (!email || !password) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Connexion...';
        errorEl.style.display = 'none';

        // ── OFFLINE: validate locally ──
        if (!navigator.onLine) {
            const pwdOk = await db.verifyPasswordOffline(email, password);
            const token = await db.getAuthToken();

            if (pwdOk && token && token.email === email.toLowerCase().trim()) {
                const shellData = await db.getShellData();
                window.__SS__ = {
                    ...window.__SS__,
                    user: {
                        id: token.userId, prenom: token.prenom, nom: token.nom,
                        email: token.email, role: token.role, taux: token.taux,
                        fonction_id: token.fonction_id, type_employe: token.type_employe,
                    },
                    csrfToken: shellData?.csrfToken || '',
                    canChangement: shellData?.canChangement || false,
                    deniedPerms: shellData?.deniedPerms || [],
                    pageLabels: shellData?.pageLabels || {},
                };
                toast('Connexion hors-ligne');
                window.location.href = '/spocspace/home';
                return;
            } else if (!pwdOk && token) {
                errorEl.textContent = 'Mot de passe incorrect (vérification hors-ligne)';
                errorEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Se connecter';
                return;
            } else {
                errorEl.textContent = 'Première connexion requise en ligne';
                errorEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Se connecter';
                return;
            }
        }

        // ── ONLINE: normal login ──
        const res = await apiPost('login', { email, password });

        if (res.success) {
            window.__SS__.user = res.user;
            if (res.csrf) window.__SS__.csrfToken = res.csrf;

            // Save credentials for offline login
            try {
                await db.savePasswordHash(email, password);
                await db.saveAuthToken(res.user.id, {
                    email: res.user.email, role: res.user.role,
                    prenom: res.user.prenom, nom: res.user.nom,
                    taux: res.user.taux, fonction_id: res.user.fonction_id,
                    type_employe: res.user.type_employe, photo: res.user.photo,
                });
                await db.saveShellData({
                    csrfToken: window.__SS__.csrfToken,
                    canChangement: window.__SS__.canChangement,
                    deniedPerms: window.__SS__.deniedPerms,
                    pageLabels: window.__SS__.pageLabels,
                });
            } catch (e) { /* IndexedDB save failed — not critical */ }

            // Redirect
            const urlParams = new URLSearchParams(window.location.search);
            const redirect = urlParams.get('redirect');
            if (redirect && redirect.startsWith('/')) {
                window.location.href = redirect;
            } else {
                const role = res.user?.role;
                if (role === 'admin' || role === 'direction') {
                    window.location.href = '/spocspace/admin/';
                } else {
                    window.location.href = '/spocspace/home';
                }
            }
        } else {
            const msg = res.message || 'Erreur de connexion';
            const isRateLimit = msg.includes('Trop de tentatives');
            errorEl.innerHTML = isRateLimit
                ? msg + ' <button type="button" id="demoUnlockBtn" style="margin-left:8px;background:#bcd2cb;color:#2d4a43;border:none;border-radius:6px;padding:4px 12px;font-size:.82rem;font-weight:600;cursor:pointer"><i class="bi bi-unlock"></i> Déverrouiller (démo)</button>'
                : msg;
            errorEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Se connecter';
            document.getElementById('demoUnlockBtn')?.addEventListener('click', async () => {
                const r = await apiPost('demo_unlock_rate_limit');
                if (r.success) {
                    errorEl.innerHTML = '<span style="color:#2d4a43"><i class="bi bi-check-circle"></i> Déverrouillé ! Réessayez.</span>';
                }
            });
        }
    });
}

export function destroy() {}
