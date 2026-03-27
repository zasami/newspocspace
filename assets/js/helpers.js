/**
 * zerdaTime - Helpers
 */

export async function apiPost(action, data = {}) {
    const body = { action, ...data };
    const headers = { 'Content-Type': 'application/json' };

    const readOnly = action.startsWith('get_') || ['me', 'login', 'request_reset', 'reset_password'].includes(action);
    if (!readOnly && window.__TR__?.csrfToken) {
        headers['X-CSRF-Token'] = window.__TR__.csrfToken;
    }

    try {
        const res = await fetch('/zerdatime/api.php', { method: 'POST', headers, body: JSON.stringify(body) });
        const json = await res.json();

        // Update CSRF token if returned
        if (json.csrf) {
            window.__TR__.csrfToken = json.csrf;
        }

        if (!res.ok && !json.message) {
            json.message = `Erreur ${res.status}`;
        }

        // Cache successful GET responses for offline
        try {
            const { handleOnlineResponse } = await import('./modules/offline.js');
            handleOnlineResponse(action, data, json);
        } catch (e) { /* offline module not loaded */ }

        return json;
    } catch (err) {
        console.error('API error:', err);

        // Try offline fallback
        try {
            const { handleOfflineGet, canQueue, enqueue } = await import('./modules/offline.js');
            if (readOnly) {
                const cached = handleOfflineGet(action, data);
                if (cached) return cached;
            } else if (canQueue(action)) {
                enqueue(action, data);
                return { success: true, message: 'Action sauvegardée hors-ligne', _queued: true };
            }
        } catch (e) { /* offline module not loaded */ }

        return { success: false, message: 'Erreur réseau' };
    }
}

export function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

export function toast(msg, duration = 2500) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(el._timeout);
    el._timeout = setTimeout(() => el.classList.remove('show'), duration);
}

export function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'short' });
}

export function formatDayName(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('fr-CH', { weekday: 'short' });
}

export function statusBadge(statut) {
    const map = {
        'en_attente': '<span class="badge badge-pending">En attente</span>',
        'valide': '<span class="badge badge-success">Validé</span>',
        'refuse': '<span class="badge badge-refused">Refusé</span>',
    };
    return map[statut] || `<span class="badge badge-info">${escapeHtml(statut)}</span>`;
}

export function absenceTypeBadge(type) {
    const map = {
        'vacances': '<span class="badge badge-info">Vacances</span>',
        'maladie': '<span class="badge badge-refused">Maladie</span>',
        'accident': '<span class="badge badge-refused">Accident</span>',
        'conge_special': '<span class="badge badge-purple">Congé spécial</span>',
        'formation': '<span class="badge badge-info">Formation</span>',
        'autre': '<span class="badge badge-pending">Autre</span>',
    };
    return map[type] || `<span class="badge badge-info">${escapeHtml(type)}</span>`;
}

export function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}
