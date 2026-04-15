/**
 * Mes stagiaires — JS minimal (page SSR).
 * Le HTML est rendu par pages/mes-stagiaires.php.
 * Ce module ne gère que la navigation vers la page de détail.
 */

export function init() {
    document.addEventListener('click', handleClick);
}

function handleClick(e) {
    const card = e.target.closest('[data-open-stagiaire]');
    if (!card) return;
    const id = card.dataset.openStagiaire;
    history.pushState({}, '', `/spocspace/stagiaire-detail?id=${id}`);
    window.dispatchEvent(new PopStateEvent('popstate'));
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
