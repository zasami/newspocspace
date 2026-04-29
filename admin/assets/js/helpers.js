/**
 * Admin helpers
 */

window.adminApiPost = async function adminApiPost(action, data = {}) {
    const body = { action, ...data };
    const headers = { 'Content-Type': 'application/json' };

    const readOnly = action.startsWith('get_') || action.startsWith('admin_get_');
    if (!readOnly && window.__SS_ADMIN__?.csrfToken) {
        headers['X-CSRF-Token'] = window.__SS_ADMIN__.csrfToken;
    }

    try {
        const res = await fetch('/newspocspace/admin/api.php', { method: 'POST', headers, body: JSON.stringify(body) });
        if (res.status === 401 && action !== 'admin_get_session_ping') {
            window.__ssShowSessionExpired?.();
            return { success: false, message: 'Session expirée' };
        }
        const json = await res.json();
        if (json.csrf) window.__SS_ADMIN__.csrfToken = json.csrf;
        if (!res.ok && !json.message) json.message = `Erreur ${res.status}`;
        return json;
    } catch (err) {
        console.error('Admin API error:', err);
        return { success: false, message: 'Erreur réseau' };
    }
}

window.escapeHtml = function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

window.showToast = function showToast(msg, type = 'info') {
    // Bootstrap toast or simple alert
    const toastHtml = `
      <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div class="toast show align-items-center text-bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'primary'} border-0" role="alert">
          <div class="d-flex">
            <div class="toast-body">${window.escapeHtml(msg)}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>
      </div>`;
    const container = document.createElement('div');
    container.innerHTML = toastHtml;
    document.body.appendChild(container);
    setTimeout(() => container.remove(), 3000);
}

// Alias for toast
window.toast = (msg, type = 'success') => window.showToast(msg, type === 'error' ? 'error' : 'success');

/**
 * Modal de confirmation SpocSpace — remplace confirm() natif.
 *
 * Usage 1 : await ssConfirm('Question ?', { okText:'Supprimer', variant:'danger' })
 * Usage 2 : await ssConfirm({ title, message, confirmText, confirmClass, icon })
 */
window.ssConfirm = function ssConfirm(messageOrOpts, opts = {}) {
    let title, message, okText, cancelText, variant, icon;

    if (typeof messageOrOpts === 'object' && messageOrOpts !== null) {
        title       = messageOrOpts.title       || 'Confirmation';
        message     = messageOrOpts.message     || '';
        okText      = messageOrOpts.confirmText || messageOrOpts.okText || 'Confirmer';
        cancelText  = messageOrOpts.cancelText  || 'Annuler';
        icon        = messageOrOpts.icon        || null;
        const cls   = messageOrOpts.confirmClass || '';
        variant     = cls.includes('danger')  ? 'danger'
                    : cls.includes('warning') ? 'warning'
                    : cls.includes('success') ? 'success'
                    : (messageOrOpts.variant || 'primary');
    } else {
        title      = opts.title       || 'Confirmation';
        message    = messageOrOpts    || '';
        okText     = opts.okText      || 'Confirmer';
        cancelText = opts.cancelText  || 'Annuler';
        variant    = opts.variant     || 'primary';
        icon       = opts.icon        || null;
    }

    return new Promise(resolve => {
        let modalEl = document.getElementById('ssConfirmModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'ssConfirmModal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
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
        if (typeof message === 'string') body.innerHTML = `<p class="mb-0">${window.escapeHtml(message)}</p>`;
        else { body.innerHTML = ''; if (message) body.append(message); }

        const iconEl = modalEl.querySelector('.ss-confirm-icon');
        const defaultIcon = variant === 'danger'  ? 'bi-exclamation-triangle'
                         : variant === 'warning' ? 'bi-exclamation-circle'
                         : variant === 'success' ? 'bi-check-circle'
                         : 'bi-question-circle';
        iconEl.className = 'bi ss-confirm-icon ' + (icon || defaultIcon);

        const okBtn = modalEl.querySelector('.ss-confirm-ok');
        okBtn.textContent = okText;
        const okVariantClass = variant === 'danger'  ? 'btn-danger'
                            : variant === 'warning' ? 'btn-warning'
                            : variant === 'success' ? 'btn-success'
                            : 'btn-primary';
        okBtn.className = 'btn btn-sm ss-confirm-ok ' + okVariantClass;

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
};
