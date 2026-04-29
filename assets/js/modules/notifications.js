/**
 * Notifications — JS minimal (page SSR).
 */
import { apiPost, toast } from '../helpers.js';

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

function reload() {
    window.dispatchEvent(new PopStateEvent('popstate'));
}

async function handleClick(e) {
    // Filtre navigation — URL propre /notifications/unread
    const filterBtn = e.target.closest('[data-filter]');
    if (filterBtn) {
        e.preventDefault();
        const filter = filterBtn.dataset.filter;
        const url = filter === 'active' ? '/newspocspace/notifications' : `/newspocspace/notifications/${filter}`;
        history.pushState({}, '', url);
        reload();
        return;
    }

    // Archiver une notification
    const archiveBtn = e.target.closest('[data-archive]');
    if (archiveBtn) {
        e.stopPropagation();
        const id = archiveBtn.dataset.archive;
        const r = await apiPost('archive_notification', { id });
        if (r.success) {
            const item = archiveBtn.closest('.notif-item');
            if (item) item.remove();
            updateTopbarBadge();
            toast('Notification archivée');
        }
        return;
    }

    // Clic sur une notification → marquer lu + navigation
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
            history.pushState({}, '', `/newspocspace/${url}`);
            reload();
        }
        return;
    }

    // Tout marquer comme lu
    if (e.target.closest('#markAllRead')) {
        const r = await apiPost('mark_all_notifications_read', {});
        if (r.success) {
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
            const btn = document.getElementById('markAllRead');
            if (btn) btn.disabled = true;
            updateTopbarBadge();
        }
        return;
    }

    // Archiver toutes les lues
    if (e.target.closest('#archiveAllRead')) {
        const r = await apiPost('archive_all_read_notifications', {});
        if (r.success) {
            toast(r.message || 'Archivées');
            reload();
        }
    }
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
