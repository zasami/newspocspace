/**
 * SpocSpace - Helpers
 */

/**
 * Bootstrap-styled confirmation modal — remplace `confirm()` natif.
 * Respecte la palette SpocSpace et le layout header / body / footer.
 * Usage : const ok = await ssConfirm('Question ?', { okText:'Supprimer', variant:'danger' });
 */
export function ssConfirm(message, { title = 'Confirmation', okText = 'Confirmer', cancelText = 'Annuler', variant = 'primary', icon = null } = {}) {
    return new Promise(resolve => {
        let modalEl = document.getElementById('ssConfirmModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'ssConfirmModal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi ss-confirm-icon"></i> <span class="ss-confirm-title"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body ss-confirm-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary ss-confirm-cancel" data-bs-dismiss="modal"></button>
                            <button type="button" class="btn btn-sm ss-confirm-ok"></button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modalEl);
        }

        modalEl.querySelector('.ss-confirm-title').textContent = title;
        const body = modalEl.querySelector('.ss-confirm-body');
        if (typeof message === 'string') body.innerHTML = `<p class="mb-0">${message}</p>`;
        else { body.innerHTML = ''; if (message) body.append(message); }

        const iconEl = modalEl.querySelector('.ss-confirm-icon');
        iconEl.className = 'bi ss-confirm-icon ' + (icon || (variant === 'danger' ? 'bi-exclamation-triangle' : variant === 'warning' ? 'bi-exclamation-circle' : 'bi-question-circle'));

        const okBtn = modalEl.querySelector('.ss-confirm-ok');
        okBtn.textContent = okText;
        okBtn.className = 'btn btn-sm ss-confirm-ok btn-' + (variant === 'danger' ? 'danger' : variant === 'warning' ? 'warning' : 'primary');

        modalEl.querySelector('.ss-confirm-cancel').textContent = cancelText;

        let confirmed = false;
        const onOk = () => { confirmed = true; bsModal.hide(); };
        okBtn.addEventListener('click', onOk, { once: true });

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const onHidden = () => {
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
            okBtn.removeEventListener('click', onOk);
            resolve(confirmed);
        };
        modalEl.addEventListener('hidden.bs.modal', onHidden);
        bsModal.show();
    });
}

export async function apiPost(action, data = {}) {
    const body = { action, ...data };
    const headers = { 'Content-Type': 'application/json' };

    const readOnly = action.startsWith('get_') || ['me', 'login', 'request_reset', 'reset_password'].includes(action);
    if (!readOnly && window.__SS__?.csrfToken) {
        headers['X-CSRF-Token'] = window.__SS__.csrfToken;
    }

    try {
        const res = await fetch('/spocspace/api.php', { method: 'POST', headers, body: JSON.stringify(body) });
        const json = await res.json();

        // Update CSRF token if returned
        if (json.csrf) {
            window.__SS__.csrfToken = json.csrf;
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
                const cached = await handleOfflineGet(action, data);
                if (cached) return cached;
            } else if (canQueue(action)) {
                await enqueue(action, data);
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
