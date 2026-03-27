/**
 * Votes module — employee side
 * View planning proposals, see own planning per proposal, vote pour/contre
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

const joursShort = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
let proposals = [];

export async function init() {
    await loadProposals();
}

async function loadProposals() {
    const container = document.getElementById('votesContainer');
    if (!container) return;

    const res = await apiPost('get_proposals_ouvertes');
    proposals = res.proposals || [];

    if (!proposals.length) {
        container.innerHTML = `
            <div class="empty-state" style="padding:3rem;text-align:center">
                <i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted,#999)"></i>
                <p style="margin-top:1rem;color:var(--text-muted,#888)">Aucune proposition de planning à voter pour le moment</p>
            </div>`;
        return;
    }

    // Group by month
    const byMonth = {};
    proposals.forEach(p => {
        if (!byMonth[p.mois_annee]) byMonth[p.mois_annee] = [];
        byMonth[p.mois_annee].push(p);
    });

    const monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

    let html = '';
    for (const [mois, props] of Object.entries(byMonth)) {
        const [y, m] = mois.split('-').map(Number);
        html += `<h3 style="margin:1rem 0 0.5rem"><i class="bi bi-calendar3"></i> ${monthNames[m-1]} ${y}</h3>`;
        html += '<div class="d-flex gap-3 flex-wrap">';

        props.forEach((p, idx) => {
            const total = p.votes_pour + p.votes_contre;
            const pctPour = total > 0 ? Math.round((p.votes_pour / total) * 100) : 0;
            const pctContre = total > 0 ? 100 - pctPour : 0;
            const votedClass = p.my_vote === 'pour' ? 'voted-pour' : p.my_vote === 'contre' ? 'voted-contre' : '';

            html += `
            <div class="proposal-card ${votedClass}" style="flex:1;min-width:280px;max-width:400px" data-proposal-id="${p.id}">
                <div class="proposal-header">
                    <span>${escapeHtml(p.label)}</span>
                    <span class="badge bg-primary bg-opacity-25 text-dark">${total} vote${total > 1 ? 's' : ''}</span>
                </div>
                <div class="proposal-body">
                    <div class="d-flex justify-content-between" style="font-size:0.8rem">
                        <span style="color:#27ae60"><i class="bi bi-hand-thumbs-up-fill"></i> ${p.votes_pour} pour (${pctPour}%)</span>
                        <span style="color:#e74c3c"><i class="bi bi-hand-thumbs-down-fill"></i> ${p.votes_contre} contre (${pctContre}%)</span>
                    </div>
                    <div class="vote-bar">
                        <div class="vote-bar-fill vote-bar-pour" style="width:${pctPour}%;display:inline-block"></div>
                        <div class="vote-bar-fill vote-bar-contre" style="width:${pctContre}%;display:inline-block"></div>
                    </div>

                    <button class="btn btn-sm btn-outline-primary w-100 mt-2" data-show-planning="${p.id}">
                        <i class="bi bi-eye"></i> Voir mon planning
                    </button>

                    <div class="proposal-planning" id="planning-${p.id}" style="display:none"></div>

                    <div class="vote-btn-group">
                        <button class="vote-btn pour ${p.my_vote === 'pour' ? 'active' : ''}" data-vote-pour="${p.id}">
                            <i class="bi bi-hand-thumbs-up-fill"></i> Pour
                        </button>
                        <button class="vote-btn contre ${p.my_vote === 'contre' ? 'active' : ''}" data-vote-contre="${p.id}">
                            <i class="bi bi-hand-thumbs-down-fill"></i> Contre
                        </button>
                    </div>

                    ${p.my_vote ? `<div class="text-center mt-2" style="font-size:0.78rem;color:var(--text-muted,#888)">
                        Vous avez voté <strong>${p.my_vote}</strong>
                    </div>` : ''}
                </div>
            </div>`;
        });

        html += '</div>';
    }

    container.innerHTML = html;

    // Show planning handlers
    container.querySelectorAll('[data-show-planning]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.showPlanning;
            const planDiv = document.getElementById(`planning-${id}`);
            if (!planDiv) return;

            if (planDiv.style.display !== 'none') {
                planDiv.style.display = 'none';
                btn.innerHTML = '<i class="bi bi-eye"></i> Voir mon planning';
                return;
            }

            planDiv.innerHTML = '<div class="text-center text-muted py-2"><span class="spinner"></span></div>';
            planDiv.style.display = '';
            btn.innerHTML = '<i class="bi bi-eye-slash"></i> Masquer';

            const res = await apiPost('get_proposal_planning', { proposal_id: id });
            const assignations = res.assignations || [];

            if (!assignations.length) {
                planDiv.innerHTML = '<div class="text-muted text-center py-2" style="font-size:0.85rem">Aucune assignation pour vous dans cette proposition</div>';
                return;
            }

            let rows = assignations.map(a => {
                const date = new Date(a.date_jour + 'T00:00:00');
                const jour = joursShort[date.getDay()];
                const d = date.getDate();
                return `<tr>
                    <td>${jour} ${d}</td>
                    <td><span class="badge" style="background:${escapeHtml(a.couleur)};color:#fff">${escapeHtml(a.horaire_code)}</span></td>
                    <td>${escapeHtml(a.heure_debut?.substring(0,5) || '')} — ${escapeHtml(a.heure_fin?.substring(0,5) || '')}</td>
                    <td>${escapeHtml(a.module_code)}</td>
                </tr>`;
            }).join('');

            planDiv.innerHTML = `<table class="table table-sm">
                <thead><tr><th>Jour</th><th>Horaire</th><th>Heures</th><th>Module</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
        });
    });

    // Vote handlers
    container.querySelectorAll('[data-vote-pour]').forEach(btn => {
        btn.addEventListener('click', () => submitVote(btn.dataset.votePour, 'pour'));
    });
    container.querySelectorAll('[data-vote-contre]').forEach(btn => {
        btn.addEventListener('click', () => submitVote(btn.dataset.voteContre, 'contre'));
    });
}

async function submitVote(proposalId, vote) {
    const res = await apiPost('submit_vote', { proposal_id: proposalId, vote });
    if (res.success) {
        toast(`Vote "${vote}" enregistré`);
        await loadProposals();
    } else {
        toast(res.message || 'Erreur');
    }
}

export function destroy() {
    proposals = [];
}
