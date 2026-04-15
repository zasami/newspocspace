/**
 * Fiches de salaire — JS minimal (page SSR).
 * Le HTML est rendu par pages/fiches-salaire.php.
 * Ce module gère uniquement l'ouverture du PDF.
 */
const BASE = '/spocspace';

export function init() {
    document.addEventListener('click', handleClick);
}

function handleClick(e) {
    const card = e.target.closest('[data-fiche-id]');
    if (!card) return;
    window.open(`${BASE}/api.php?action=serve_fiche_salaire&id=${card.dataset.ficheId}`, '_blank');
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
