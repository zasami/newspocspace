/**
 * Auth module - Login
 */
import { apiPost, toast } from '../helpers.js';

export async function init() {
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
        // Auto-submit after short delay
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

        const res = await apiPost('login', { email, password });

        if (res.success) {
            window.__SS__.user = res.user;
            if (res.csrf) window.__SS__.csrfToken = res.csrf;
            // Check for redirect param
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
