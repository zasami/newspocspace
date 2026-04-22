import { apiPost, escapeHtml, toast, debounce } from '../helpers.js';
import { navigateTo } from '../app.js';

let state = { tab: 'tous', sort: 'votes', service: '', categorie: '', statut: '', search: '' };

export function init() {
    // Tabs
    document.querySelectorAll('.sug-tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.sug-tab').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            state.tab = t.dataset.sugTab;
            reload();
        });
    });

    // Filtres & tri
    document.getElementById('sugFilterService')?.addEventListener('change', e => { state.service = e.target.value; reload(); });
    document.getElementById('sugFilterCategorie')?.addEventListener('change', e => { state.categorie = e.target.value; reload(); });
    document.getElementById('sugFilterStatut')?.addEventListener('change', e => { state.statut = e.target.value; reload(); });
    document.getElementById('sugSort')?.addEventListener('change', e => { state.sort = e.target.value; reload(); });

    const search = document.getElementById('sugSearch');
    const doSearch = debounce(() => { state.search = search.value.trim(); reload(); }, 300);
    search?.addEventListener('input', doSearch);

    // Vote + ouverture détail (délégation — survit au re-render)
    document.getElementById('sugList')?.addEventListener('click', onListClick);
}

export function destroy() {
    document.getElementById('sugList')?.removeEventListener('click', onListClick);
}

async function onListClick(e) {
    const voteBtn = e.target.closest('[data-sug-vote]');
    if (voteBtn) {
        e.preventDefault();
        e.stopPropagation();
        await handleVote(voteBtn);
        return;
    }
    const openEl = e.target.closest('[data-sug-open]');
    if (openEl) {
        const id = openEl.dataset.sugOpen;
        navigateTo('suggestion-detail', id);
    }
}

async function handleVote(btn) {
    const id = btn.dataset.sugVote;
    btn.disabled = true;
    try {
        const r = await apiPost('toggle_suggestion_vote', { id });
        if (!r.success) { toast(r.message || 'Erreur'); return; }
        btn.classList.toggle('voted', r.voted);
        const icon = btn.querySelector('i');
        if (icon) icon.className = 'bi bi-hand-thumbs-up' + (r.voted ? '-fill' : '');
        const count = btn.querySelector('.sug-vote-count');
        if (count) count.textContent = r.votes_count;
        btn.title = r.voted ? 'Retirer mon vote' : 'Voter pour cette suggestion';
        // Met à jour data-voted sur la card pour le filtre "J'ai voté"
        const card = btn.closest('.sug-card');
        if (card) card.dataset.voted = r.voted ? '1' : '0';
    } finally {
        btn.disabled = false;
    }
}

async function reload() {
    const list = document.getElementById('sugList');
    if (!list) return;
    list.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div> Chargement…</div>';
    const r = await apiPost('get_suggestions', state);
    if (!r.success) { list.innerHTML = '<div class="text-danger py-3">Erreur de chargement</div>'; return; }
    renderList(r.suggestions);
}

function renderList(rows) {
    const list = document.getElementById('sugList');
    const tabCount = document.getElementById('sugCountTous');
    if (tabCount) tabCount.textContent = rows.length;
    if (!rows.length) {
        list.innerHTML = '<div class="card card-body text-center text-muted small py-4"><i class="bi bi-inbox" style="font-size:1.8rem;opacity:.25"></i><div class="mt-2">Aucune suggestion ne correspond</div></div>';
        return;
    }

    const urg = { critique:'sug-urg-critique', eleve:'sug-urg-eleve', moyen:'sug-urg-moyen', faible:'sug-urg-faible' };
    const urgLabel = { critique:'Critique', eleve:'Élevée', moyen:'Moyenne', faible:'Faible' };
    const st = { nouvelle:'sug-st-nouvelle', etudiee:'sug-st-etudiee', planifiee:'sug-st-planifiee', en_dev:'sug-st-endev', livree:'sug-st-livree', refusee:'sug-st-refusee' };
    const stLabel = { nouvelle:'Nouvelle', etudiee:'Étudiée', planifiee:'Planifiée', en_dev:'En développement', livree:'Livrée', refusee:'Refusée' };
    const svcLabel = {
        aide_soignant:'Aide-soignant·e', infirmier:'Infirmier·e', infirmier_chef:'Infirmier·e chef',
        animation:'Animation', cuisine:'Cuisine / Hôtellerie', technique:'Technique',
        admin:'Administration', rh:'RH', direction:'Direction', qualite:'Qualité', autre:'Autre',
    };
    const catLabel = {
        formulaire:'Formulaire à informatiser', fonctionnalite:'Nouvelle fonctionnalité',
        amelioration:'Amélioration', alerte:'Alerte / Notification', bug:'Bug', question:'Question',
    };

    const cards = rows.map(s => {
        const author = escapeHtml([s.auteur_prenom, s.auteur_nom].filter(Boolean).join(' ') || '—');
        const descTxt = escapeHtml(String(s.description || '').slice(0, 220));
        const rel = relative(s.created_at);
        const voted = Number(s.has_voted) === 1;
        return `
        <article class="sug-card"
                 data-sug-id="${escapeHtml(s.id)}"
                 data-voted="${voted ? '1' : '0'}">
            <div class="sug-card-head">
                <div class="sug-card-title" data-sug-open="${escapeHtml(s.id)}">${escapeHtml(s.titre)}</div>
                <div class="sug-badges">
                    <span class="sug-badge ${urg[s.urgence]||'sug-urg-moyen'}">${urgLabel[s.urgence]||s.urgence}</span>
                    <span class="sug-badge ${st[s.statut]||'sug-st-nouvelle'}">${stLabel[s.statut]||s.statut}</span>
                </div>
            </div>
            <div class="sug-ref">${escapeHtml(s.reference_code)} · ${escapeHtml(catLabel[s.categorie]||s.categorie)} · ${escapeHtml(svcLabel[s.service]||s.service)}</div>
            <div class="sug-desc">${descTxt}</div>
            <div class="sug-meta">
                <span><i class="bi bi-person"></i> ${author}</span>
                <span><i class="bi bi-clock"></i> ${rel}</span>
            </div>
            <div class="sug-actions">
                <button class="sug-vote-btn ${voted?'voted':''}" data-sug-vote="${escapeHtml(s.id)}" title="${voted?'Retirer mon vote':'Voter pour cette suggestion'}">
                    <i class="bi bi-hand-thumbs-up${voted?'-fill':''}"></i>
                    <span class="sug-vote-count">${s.votes_count|0}</span>
                </button>
                <a class="sug-comment-link" data-sug-open="${escapeHtml(s.id)}">
                    <i class="bi bi-chat-dots"></i> ${s.comments_count|0} commentaire${(s.comments_count|0) > 1 ? 's' : ''}
                </a>
            </div>
        </article>`;
    }).join('');

    list.innerHTML = `<div class="sug-grid">${cards}</div>`;
}

function relative(date) {
    if (!date) return '';
    const diff = (Date.now() - new Date(date.replace(' ', 'T')).getTime()) / 1000;
    if (diff < 60)   return 'à l\'instant';
    if (diff < 3600) return `il y a ${Math.floor(diff/60)} min`;
    if (diff < 86400) return `il y a ${Math.floor(diff/3600)} h`;
    if (diff < 2592000) return `il y a ${Math.floor(diff/86400)} j`;
    return new Date(date.replace(' ','T')).toLocaleDateString('fr-CH');
}
