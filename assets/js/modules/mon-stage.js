/**
 * Mon stage — JS minimal (page SSR).
 * Le HTML + les données sont rendus par pages/mon-stage.php.
 * Ce module ne gère que les interactions (Modifier, Supprimer).
 */
import { apiPost, ssConfirm, toast } from '../helpers.js';

export function init() {
    document.addEventListener('click', handleClick);
}

async function handleClick(e) {
    // Éditer un report → navigation vers report-edit?id=...
    const editBtn = e.target.closest('[data-edit-report]');
    if (editBtn) {
        history.pushState({}, '', `/newspocspace/report-edit?id=${editBtn.dataset.editReport}`);
        window.dispatchEvent(new PopStateEvent('popstate'));
        return;
    }

    // Supprimer un brouillon → confirm + recharger la page
    const delBtn = e.target.closest('[data-del-report]');
    if (delBtn) {
        const ok = await ssConfirm(
            'Ce brouillon sera supprimé définitivement.',
            { title: 'Supprimer le brouillon ?', okText: 'Supprimer', variant: 'danger' }
        );
        if (!ok) return;
        const r = await apiPost('delete_my_report', { id: delBtn.dataset.delReport });
        if (r.success) {
            toast(r.message || 'Supprimé');
            // Reload la page via le routeur SPA pour re-rendre le PHP
            window.dispatchEvent(new PopStateEvent('popstate'));
        } else {
            toast(r.message || 'Erreur');
        }
    }
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
