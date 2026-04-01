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
            window.__ZT__.user = res.user;
            if (res.csrf) window.__ZT__.csrfToken = res.csrf;
            // Check for redirect param
            const urlParams = new URLSearchParams(window.location.search);
            const redirect = urlParams.get('redirect');
            if (redirect && redirect.startsWith('/')) {
                window.location.href = redirect;
            } else {
                const role = res.user?.role;
                if (role === 'admin' || role === 'direction') {
                    window.location.href = '/zerdatime/admin/';
                } else {
                    window.location.href = '/zerdatime/home';
                }
            }
        } else {
            errorEl.textContent = res.message || 'Erreur de connexion';
            errorEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Se connecter';
        }
    });
}

export function destroy() {}
