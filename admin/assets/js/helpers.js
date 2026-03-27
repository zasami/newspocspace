/**
 * Admin helpers
 */

window.adminApiPost = async function adminApiPost(action, data = {}) {
    const body = { action, ...data };
    const headers = { 'Content-Type': 'application/json' };

    const readOnly = action.startsWith('get_') || action.startsWith('admin_get_');
    if (!readOnly && window.__ZT_ADMIN__?.csrfToken) {
        headers['X-CSRF-Token'] = window.__ZT_ADMIN__.csrfToken;
    }

    try {
        const res = await fetch('/zerdatime/admin/api.php', { method: 'POST', headers, body: JSON.stringify(body) });
        const json = await res.json();
        if (json.csrf) window.__ZT_ADMIN__.csrfToken = json.csrf;
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
