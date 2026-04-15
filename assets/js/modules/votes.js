/**
 * Votes — JS minimal (page SSR).
 * Le HTML est rendu par pages/votes.php.
 */
import { apiPost, toast } from '../helpers.js';

export function init() {
    document.addEventListener('click', handleClick);
}

async function handleClick(e) {
    const btn = e.target.closest('.vote-btn');
    if (!btn) return;
    const card = btn.closest('[data-proposal-id]');
    if (!card) return;
    const r = await apiPost('submit_vote', {
        proposal_id: card.dataset.proposalId,
        vote: btn.dataset.vote,
    });
    if (r.success) {
        toast('Vote enregistré');
        window.dispatchEvent(new PopStateEvent('popstate'));
    } else {
        toast(r.message || 'Erreur');
    }
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}
