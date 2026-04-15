/**
 * Profile — JS minimal (page SSR).
 * Le HTML est rendu par pages/profile.php.
 * Ce module gère : upload avatar, toggle password, jauge de force, changement de MDP.
 */
import { apiPost, toast } from '../helpers.js';

export function init() {
    // Toggle eye password
    document.querySelectorAll('.pwd-eye').forEach(el => {
        el.addEventListener('click', () => {
            const input = document.getElementById(el.dataset.target);
            if (!input) return;
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            el.querySelector('i').className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Password strength
    const np = document.getElementById('newPassword');
    const strength = document.getElementById('pwdStrength');
    np?.addEventListener('input', () => {
        const v = np.value;
        const checks = [
            { ok: v.length >= 8,     label: '8+ caractères' },
            { ok: /[A-Z]/.test(v),   label: '1 majuscule' },
            { ok: /[0-9]/.test(v),   label: '1 chiffre' },
            { ok: /[^A-Za-z0-9]/.test(v), label: '1 spécial' },
        ];
        strength.innerHTML = checks.map(c =>
            `<span class="pwd-check ${c.ok ? 'ok' : 'ko'}">${c.ok ? '✓' : '✗'} ${c.label}</span>`
        ).join(' ');
    });

    // Password form
    document.getElementById('passwordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const current = document.getElementById('currentPassword').value;
        const newPass = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;

        if (newPass !== confirm) return toast('Les mots de passe ne correspondent pas');
        if (newPass.length < 8) return toast('Minimum 8 caractères');
        if (!/[A-Z]/.test(newPass)) return toast('Au moins 1 majuscule');
        if (!/[0-9]/.test(newPass)) return toast('Au moins 1 chiffre');
        if (!/[^A-Za-z0-9]/.test(newPass)) return toast('Au moins 1 caractère spécial');

        const r = await apiPost('update_password', { current_password: current, new_password: newPass });
        if (r.success) {
            toast(r.message || 'Mot de passe mis à jour');
            document.getElementById('passwordForm').reset();
            if (strength) strength.innerHTML = '';
        } else {
            toast(r.message || 'Erreur');
        }
    });

    // Avatar upload
    document.getElementById('profAvatarImg')?.addEventListener('click', () => {
        document.getElementById('profAvatarInput')?.click();
    });
    document.getElementById('profAvatarInput')?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('avatar', file);
        fd.append('action', 'upload_avatar');
        try {
            const res = await fetch('/spocspace/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS__?.csrfToken || '' },
                body: fd,
            });
            const json = await res.json();
            if (json.success) {
                toast(json.message || 'Avatar mis à jour');
                // Refresh SPA
                window.dispatchEvent(new PopStateEvent('popstate'));
            } else {
                toast(json.message || 'Erreur');
            }
        } catch (err) {
            toast('Erreur d\'upload');
        }
    });
}

export function destroy() {}
