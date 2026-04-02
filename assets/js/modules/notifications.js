/**
 * SpocSpace - Notifications module
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';

const TYPE_CONFIG = {
    vacances_valide:     { icon: 'bi-check-circle-fill', color: '#2d4a43', bg: '#bcd2cb' },
    vacances_refuse:     { icon: 'bi-x-circle-fill',     color: '#7B3B2C', bg: '#E2B8AE' },
    absence_valide:      { icon: 'bi-calendar-check',    color: '#2d4a43', bg: '#bcd2cb' },
    absence_refuse:      { icon: 'bi-calendar-x',        color: '#7B3B2C', bg: '#E2B8AE' },
    changement_accepte:  { icon: 'bi-arrow-left-right',  color: '#3B4F6B', bg: '#B8C9D4' },
    changement_refuse:   { icon: 'bi-arrow-left-right',  color: '#7B3B2C', bg: '#E2B8AE' },
    changement_demande:  { icon: 'bi-arrow-left-right',  color: '#6B5B3E', bg: '#D4C4A8' },
    sondage_nouveau:     { icon: 'bi-bar-chart',         color: '#5B4B6B', bg: '#D0C4D8' },
    planning_publie:     { icon: 'bi-calendar3',         color: '#3B4F6B', bg: '#B8C9D4' },
    pv_ajoute:           { icon: 'bi-journal-text',      color: '#3B4F6B', bg: '#B8C9D4' },
    pv_commentaire:      { icon: 'bi-chat-dots',         color: '#2d4a43', bg: '#bcd2cb' },
    document_ajoute:     { icon: 'bi-folder2-open',      color: '#6B5B3E', bg: '#D4C4A8' },
    fiche_salaire:       { icon: 'bi-receipt',           color: '#2d4a43', bg: '#bcd2cb' },
    accident_approuve:   { icon: 'bi-shield-exclamation',color: '#7B3B2C', bg: '#E2B8AE' },
    alert:               { icon: 'bi-megaphone',         color: '#7B3B2C', bg: '#E2B8AE' },
    general:             { icon: 'bi-bell',              color: '#5A5550', bg: '#C8C4BE' },
};

export function init() {
    // Render from SSR data synchronously
    const ssrData = window.__SS_PAGE_DATA__ || {};
    renderNotifications(ssrData.notifications || []);

    document.getElementById('markAllRead')?.addEventListener('click', async () => {
        await apiPost('mark_all_notifications_read');
        toast('Toutes les notifications marquées comme lues');
        await loadNotifications();
        updateBellBadge();
    });
}

function renderNotifications(notifs) {
    const container = document.querySelector('#notifList .card-body');
    if (!container) return;

    if (!notifs.length) {
        container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-bell-slash" style="font-size:2rem;display:block;margin-bottom:0.5rem"></i>Aucune notification</div>';
        return;
    }

    container.innerHTML = notifs.map(n => {
        const cfg = TYPE_CONFIG[n.type] || TYPE_CONFIG.general;
        const timeAgo = formatTimeAgo(n.created_at);
        return `<div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" data-id="${n.id}" data-link="${escapeHtml(n.link || '')}" data-type="${escapeHtml(n.type || '')}" data-title="${escapeHtml(n.title || '')}" data-message="${escapeHtml(n.message || '')}">
            <div class="notif-icon" style="background:${cfg.bg};color:${cfg.color}"><i class="bi ${cfg.icon}"></i></div>
            <div style="flex:1;min-width:0">
                <div class="notif-title">${escapeHtml(n.title)}</div>
                ${n.message ? `<div class="notif-msg">${escapeHtml(n.message)}</div>` : ''}
                <div class="notif-time">${timeAgo}</div>
            </div>
        </div>`;
    }).join('');

    container.querySelectorAll('.notif-item').forEach(el => {
        el.addEventListener('click', async () => {
            const id = el.dataset.id;
            const link = el.dataset.link;
            const type = el.dataset.type;
            if (el.classList.contains('unread')) {
                await apiPost('mark_notification_read', { id });
                el.classList.remove('unread');
                updateBellBadge();
            }
            // Alerts: show inline modal with the alert message
            if (type === 'alert') {
                showAlertNotifModal(el);
                return;
            }
            if (link) {
                const { loadPage } = await import('../app.js');
                loadPage(link);
            }
        });
    });
}

async function loadNotifications() {
    const res = await apiPost('get_notifications', { limit: 50 });
    renderNotifications(res.notifications || []);
}

function formatTimeAgo(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const mins = Math.floor(diffMs / 60000);
    if (mins < 1) return "À l'instant";
    if (mins < 60) return `Il y a ${mins} min`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `Il y a ${hours}h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `Il y a ${days}j`;
    return d.toLocaleDateString('fr-CH');
}

async function updateBellBadge() {
    const res = await apiPost('get_notifications_count');
    const badge = document.querySelector('.fe-topbar-notif');
    if (badge) {
        if (res.unread > 0) {
            badge.textContent = res.unread;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }
}

function showAlertNotifModal(el) {
    const title = el.dataset.title || 'Alerte';
    const message = el.dataset.message || '';
    const isRead = !el.classList.contains('unread');

    // Remove existing modal if any
    document.getElementById('notifAlertModal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'notifAlertModal';
    modal.innerHTML = `
        <div style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);backdrop-filter:blur(3px);">
            <div style="background:#fff;border-radius:16px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden;">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:.75rem;">
                    <div style="width:40px;height:40px;border-radius:50%;background:#E2B8AE;color:#7B3B2C;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                        <i class="bi bi-megaphone"></i>
                    </div>
                    <h6 style="margin:0;font-weight:600;flex:1;">${escapeHtml(title)}</h6>
                    <button id="notifAlertClose" style="width:32px;height:32px;border-radius:50%;border:1px solid #e5e7eb;background:#f9fafb;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-lg" style="font-size:.8rem;"></i>
                    </button>
                </div>
                <div style="padding:1.5rem;">
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;font-size:.9rem;line-height:1.6;white-space:pre-wrap;">${escapeHtml(message)}</div>
                    ${isRead ? '<div style="margin-top:1rem;display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:#2d4a43;"><i class="bi bi-check-circle-fill"></i> Vous avez déjà pris connaissance de cette alerte</div>' : ''}
                </div>
                <div style="padding:.75rem 1.5rem;border-top:1px solid #e5e7eb;text-align:right;">
                    <button id="notifAlertOk" style="padding:.4rem 1.25rem;border-radius:8px;border:none;background:#1a1a1a;color:#fff;font-size:.85rem;font-weight:500;cursor:pointer;">Fermer</button>
                </div>
            </div>
        </div>`;

    document.body.appendChild(modal);

    const close = () => modal.remove();
    modal.querySelector('#notifAlertClose').addEventListener('click', close);
    modal.querySelector('#notifAlertOk').addEventListener('click', close);
    modal.querySelector('div').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) close();
    });
}

export function destroy() {
    document.getElementById('notifAlertModal')?.remove();
}
