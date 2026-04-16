/**
 * Notifications — JS minimal (page SSR).
 * Le HTML est rendu par pages/notifications.php.
 */
import { apiPost } from '../helpers.js';

export function init() {
    document.addEventListener('click', handleClick);
}

function updateTopbarBadge() {
    const remaining = document.querySelectorAll('.notif-item.unread').length;
    const badge = document.querySelector('.fe-topbar-notif');
    if (badge) {
        if (remaining > 0) { badge.textContent = remaining; badge.style.display = ''; }
        else { badge.style.display = 'none'; }
    }
}

async function handleClick(e) {
    const item = e.target.closest('[data-notif-id]');
    if (item) {
        const id = item.dataset.notifId;
        const url = item.dataset.notifUrl;
        if (item.classList.contains('unread')) {
            await apiPost('mark_notification_read', { id });
            item.classList.remove('unread');
            updateTopbarBadge();
        }
        if (url) {
            history.pushState({}, '', `/spocspace/${url}`);
            window.dispatchEvent(new PopStateEvent('popstate'));
        }
        return;
    }

    if (e.target.closest('#markAllRead')) {
        const r = await apiPost('mark_all_notifications_read', {});
        if (r.success) {
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
            const btn = document.getElementById('markAllRead');
            if (btn) btn.disabled = true;
            updateTopbarBadge();
        }
    }
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
