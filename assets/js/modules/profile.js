/**
 * Profile module
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

export function init() {
    // Profile data injected by PHP
    const user = window.__SS_PAGE_DATA__?.user;
    if (user) {
        renderProfileHero(user);
        renderProfile(user);
    }

    // Eye toggle for all password fields
    document.querySelectorAll('.pwd-eye').forEach(el => {
        el.addEventListener('click', () => {
            const input = document.getElementById(el.dataset.target);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            el.querySelector('i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Live password strength indicator
    const newPassInput = document.getElementById('newPassword');
    const strengthEl = document.getElementById('pwdStrength');
    if (newPassInput && strengthEl) {
        newPassInput.addEventListener('input', () => {
            const v = newPassInput.value;
            const checks = [
                { ok: v.length >= 8, label: '8+ caractères' },
                { ok: /[A-Z]/.test(v), label: '1 majuscule' },
                { ok: /[0-9]/.test(v), label: '1 chiffre' },
                { ok: /[^A-Za-z0-9]/.test(v), label: '1 spécial (!@#...)' },
            ];
            strengthEl.innerHTML = checks.map(c =>
                `<span style="color:${c.ok ? '#198754' : '#dc3545'};margin-right:8px">${c.ok ? '✓' : '✗'} ${c.label}</span>`
            ).join('');
        });
    }

    // Password form
    document.getElementById('passwordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const current = document.getElementById('currentPassword').value;
        const newPass = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;

        if (newPass !== confirm) {
            toast('Les mots de passe ne correspondent pas', 'error');
            return;
        }
        if (newPass.length < 8) {
            toast('Minimum 8 caractères', 'error');
            return;
        }
        if (!/[A-Z]/.test(newPass)) {
            toast('Le mot de passe doit contenir au moins une majuscule', 'error');
            return;
        }
        if (!/[0-9]/.test(newPass)) {
            toast('Le mot de passe doit contenir au moins un chiffre', 'error');
            return;
        }
        if (!/[^A-Za-z0-9]/.test(newPass)) {
            toast('Le mot de passe doit contenir au moins un caractère spécial', 'error');
            return;
        }

        const btn = document.getElementById('btnSavePassword');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Enregistrement...'; }

        const res = await apiPost('update_password', {
            current_password: current,
            new_password: newPass
        });

        if (res.success) {
            toast('Mot de passe mis à jour avec succès !', 'success');
            document.getElementById('passwordForm').reset();
            if (strengthEl) strengthEl.innerHTML = '';
            // Remove temp password banner if present
            const banner = document.getElementById('tempPwdBanner');
            if (banner) {
                banner.remove();
                document.body.style.paddingTop = '';
                window.__SS__.mustChangePassword = false;
            }
        } else {
            toast(res.message || 'Erreur lors de la sauvegarde', 'error');
        }

        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock"></i> Modifier le mot de passe'; }
    });
}

function renderProfile(user) {
    const container = document.getElementById('profileInfo');
    if (!container) return;

    const modules = (user.modules || []).map(m =>
        `<span style="display:inline-block;background:var(--ss-accent-bg);color:var(--ss-teal);padding:2px 10px;border-radius:12px;font-size:.8rem;font-weight:500;border:1px solid var(--ss-border)">${escapeHtml(m.nom)}</span>`
    ).join(' ') || '<span class="text-muted">—</span>';

    const row = (icon, label, value) => `
        <div style="display:flex;align-items:flex-start;gap:14px;padding:12px 0;border-bottom:1px solid var(--ss-border-light)">
            <div style="width:32px;height:32px;border-radius:8px;background:var(--ss-accent-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi ${icon}" style="color:var(--ss-teal);font-size:.95rem"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--ss-text-muted);margin-bottom:2px">${label}</div>
                <div style="font-size:.92rem;color:var(--ss-text)">${value}</div>
            </div>
        </div>`;

    container.innerHTML = `
        <div style="padding-top:4px">
            ${row('bi-envelope', 'Email', escapeHtml(user.email))}
            ${row('bi-telephone', 'Téléphone', escapeHtml(user.telephone || '—'))}
            ${row('bi-briefcase', 'Fonction', escapeHtml(user.fonction_nom || user.role || '—'))}
            ${row('bi-file-text', 'Type de contrat', escapeHtml(user.type_contrat || '—'))}
            ${row('bi-speedometer2', 'Taux d\'activité', `<span style="font-weight:600;color:var(--ss-teal)">${Math.round(user.taux)}%</span>`)}
            ${row('bi-building', 'Module(s)', `<div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:2px">${modules}</div>`)}
            ${row('bi-sun', 'Solde vacances', `<span style="font-weight:600">${user.solde_vacances || 0}</span> jours`)}
        </div>
    `;
}

function renderProfileHero(user) {
    const hero = document.getElementById('profileHero');
    if (!hero) return;

    const initials = escapeHtml((user.prenom?.[0] || '') + (user.nom?.[0] || ''));
    const roleColors = {
        admin: '#dc3545', direction: '#6f42c1', responsable: '#0d6efd', collaborateur: '#198754'
    };
    const roleColor = roleColors[user.role] || 'var(--ss-teal)';
    const roleLabel = { admin: 'Administrateur', direction: 'Direction', responsable: 'Responsable', collaborateur: 'Collaborateur' }[user.role] || user.role;

    const avatarHtml = user.photo
        ? `<img src="${escapeHtml(user.photo)}?t=${Date.now()}" style="width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.15);cursor:pointer" id="profileAvatarImg" title="Cliquer pour changer">`
        : `<div style="width:72px;height:72px;border-radius:50%;background:var(--ss-teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.15);cursor:pointer;position:relative" id="profileAvatarImg" title="Cliquer pour ajouter une photo">${initials}<i class="bi bi-camera-fill" style="position:absolute;bottom:0;right:0;background:#fff;border-radius:50%;padding:3px;font-size:0.65rem;color:var(--ss-teal);box-shadow:0 1px 4px rgba(0,0,0,.2)"></i></div>`;

    hero.innerHTML = `
        <div class="card-body" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;padding:1.5rem 2rem">
            ${avatarHtml}
            <input type="file" id="profileAvatarInput" accept="image/*" style="display:none">
            <div style="flex:1;min-width:0">
                <h2 style="margin:0 0 4px;font-size:1.4rem;font-weight:700">${escapeHtml(user.prenom)} ${escapeHtml(user.nom)}</h2>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;background:${roleColor}18;color:${roleColor};border:1px solid ${roleColor}40">
                        <i class="bi bi-shield-check"></i> ${roleLabel}
                    </span>
                    ${user.fonction_nom ? `<span style="font-size:.85rem;color:var(--ss-text-secondary)"><i class="bi bi-person-badge me-1"></i>${escapeHtml(user.fonction_nom)}</span>` : ''}
                    <span style="font-size:.85rem;color:var(--ss-text-secondary)"><i class="bi bi-speedometer2 me-1"></i>${Math.round(user.taux)}%</span>
                </div>
            </div>
        </div>
    `;

    // Avatar click → file input
    document.getElementById('profileAvatarImg')?.addEventListener('click', () => {
        document.getElementById('profileAvatarInput')?.click();
    });

    // File selected → upload
    document.getElementById('profileAvatarInput')?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('avatar', file);
        fd.append('action', 'upload_avatar');
        try {
            const res = await fetch('/spocspace/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS__?.csrfToken || '' },
                body: fd
            });
            const json = await res.json();
            if (json.success) {
                // Update avatar in hero
                const img = document.getElementById('profileAvatarImg');
                if (img.tagName === 'IMG') {
                    img.src = json.photo_url + '?t=' + Date.now();
                } else {
                    img.outerHTML = `<img src="${json.photo_url}?t=${Date.now()}" style="width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.15)" id="profileAvatarImg">`;
                }
                // Update session
                if (window.__SS__?.user) window.__SS__.user.photo = json.photo_url;
                // Update topbar avatar
                const topbar = document.getElementById('topbarAvatar');
                if (topbar) {
                    if (topbar.tagName === 'IMG') {
                        topbar.src = json.photo_url + '?t=' + Date.now();
                    } else {
                        topbar.outerHTML = `<img src="${json.photo_url}?t=${Date.now()}" alt="" class="fe-topbar-user-avatar" id="topbarAvatar">`;
                    }
                }
                toast(json.message, 'success');
            } else {
                toast(json.message || 'Erreur', 'error');
            }
        } catch (err) {
            toast('Erreur d\'upload', 'error');
        }
    });
}

export function destroy() {}
