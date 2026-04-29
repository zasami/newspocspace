import { apiPost, escapeHtml, toast } from '../helpers.js';

export function init() {
    // Tabs
    document.querySelectorAll('.fa-tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.fa-tab').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            const tab = t.dataset.faTab;
            ['mes','publiques','concerne'].forEach(n => {
                const el = document.getElementById('faPane' + n.charAt(0).toUpperCase() + n.slice(1));
                if (el) el.style.display = (n === tab) ? '' : 'none';
            });
        });
    });

    // Card click → detail
    document.querySelectorAll('.fa-card').forEach(c => {
        c.addEventListener('click', () => openDetail(c.dataset.ficheId));
        c.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDetail(c.dataset.ficheId); }
        });
    });

    // Detail modal close
    document.querySelectorAll('[data-fa-close="detail"]').forEach(b => {
        b.addEventListener('click', closeDetailModal);
    });
    const detailModal = document.getElementById('faDetailModal');
    detailModal?.addEventListener('click', e => { if (e.target === detailModal) closeDetailModal(); });
}

export function destroy() {}

function closeDetailModal() {
    document.getElementById('faDetailModal')?.classList.remove('show');
}

async function openDetail(ficheId) {
    const modal = document.getElementById('faDetailModal');
    const body = document.getElementById('faDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    modal.classList.add('show');
    const r = await apiPost('get_fiche_amelioration_detail', { id: ficheId });
    if (!r.success) { body.innerHTML = '<div class="text-danger">Erreur de chargement</div>'; return; }
    renderDetail(r);
}

function renderDetail(r) {
    const f = r.fiche;
    const auteur = r.auteur;
    const body = document.getElementById('faDetailBody');

    const statuts = { soumise:{l:'Soumise',c:'#2d4a43'}, en_revue:{l:'En revue',c:'#C4A882'}, en_cours:{l:'En cours',c:'#5B4B6B'}, realisee:{l:'Réalisée',c:'#2d4a43'}, rejetee:{l:'Rejetée',c:'#A85B4A'} };
    const criticites = { faible:'Faible', moyenne:'Moyenne', haute:'Haute' };
    const cats = { securite:'Sécurité', qualite_soins:'Qualité des soins', organisation:'Organisation', materiel:'Matériel', communication:'Communication', autre:'Autre' };
    const vis = { private:'Privée', public:'Publique', targeted:'Ciblée' };

    const st = statuts[f.statut] || { l: f.statut, c:'#666' };

    const authorHtml = f.is_anonymous ? '<em>Anonyme</em>' :
        auteur ? `${escapeHtml(auteur.prenom + ' ' + auteur.nom)}` : '<em>Inconnu</em>';

    const commentsHtml = (r.comments || []).map(c => `
        <div class="fa-comment ${c.role === 'admin' ? 'admin' : ''}">
          <div class="fa-comment-head">
            <strong>${escapeHtml((c.prenom || '') + ' ' + (c.nom || ''))}${c.role === 'admin' ? ' <span style="color:#2d4a43">(Admin)</span>' : ''}</strong>
            <span>${new Date(c.created_at).toLocaleString('fr-CH')}</span>
          </div>
          <div class="fa-comment-body">${escapeHtml(c.content)}</div>
        </div>`).join('') || '<div class="text-muted small">Aucun commentaire pour l\'instant</div>';

    const concernesHtml = (r.concernes && r.concernes.length) ? `
        <div class="fa-detail-section">
          <h6><i class="bi bi-people"></i> Personnes concernées</h6>
          <div class="fa-user-chips">
            ${r.concernes.map(u => `<span class="fa-user-chip"><span class="avatar-mini">${escapeHtml(((u.prenom||'')[0]||'')+((u.nom||'')[0]||'')).toUpperCase()}</span>${escapeHtml(u.prenom + ' ' + u.nom)}</span>`).join('')}
          </div>
        </div>` : '';

    const rdvsHtml = (r.rdvs && r.rdvs.length) ? `
        <div class="fa-detail-section">
          <h6><i class="bi bi-calendar-event"></i> Rendez-vous proposés</h6>
          ${r.rdvs.map(rdv => renderRdvCard(rdv, r.is_my_fiche)).join('')}
        </div>` : '';

    const attachmentsHtml = (r.attachments && r.attachments.length) ? `
        <div class="fa-detail-section">
          <h6><i class="bi bi-paperclip"></i> Pièces jointes</h6>
          ${r.attachments.map(a => `<a href="/newspocspace/api.php?action=download_fiche_amelioration_attachment&id=${a.id}" target="_blank" class="d-block small">${escapeHtml(a.original_name)}</a>`).join('')}
        </div>` : '';

    body.innerHTML = `
      <div class="fa-detail-head">
        <div>
          <div class="fa-detail-title">${escapeHtml(f.titre)}</div>
          <div class="fa-detail-meta">
            <i class="bi bi-person"></i> ${authorHtml}
            · <i class="bi bi-clock"></i> ${new Date(f.created_at).toLocaleDateString('fr-CH')}
            · <i class="bi bi-tag"></i> ${escapeHtml(cats[f.categorie] || f.categorie)}
            · <i class="bi bi-eye"></i> ${escapeHtml(vis[f.visibility] || f.visibility)}
          </div>
        </div>
        <div class="d-flex gap-1 flex-column align-items-end">
          <span class="fa-badge" style="background:${st.c};color:#fff">${escapeHtml(st.l)}</span>
          <span class="fa-badge" style="background:#eee;color:#555">${escapeHtml(criticites[f.criticite] || f.criticite)}</span>
        </div>
      </div>

      <div class="fa-detail-section">
        <h6>Description</h6>
        <div class="fa-detail-desc">${escapeHtml(f.description)}</div>
      </div>

      ${f.suggestion ? `<div class="fa-detail-section"><h6>Suggestion</h6><div class="fa-detail-desc">${escapeHtml(f.suggestion)}</div></div>` : ''}

      ${concernesHtml}
      ${rdvsHtml}
      ${attachmentsHtml}

      <div class="fa-detail-section">
        <h6><i class="bi bi-chat-dots"></i> Fil de discussion</h6>
        <div class="fa-comments">${commentsHtml}</div>

        <div style="margin-top:10px">
          <textarea class="fa-textarea" id="faCommentInput" rows="2" placeholder="Ajouter un commentaire..."></textarea>
          <div class="d-flex justify-content-between align-items-center mt-1">
            <label class="small d-flex gap-1 align-items-center">
              <input type="checkbox" id="faCommentAnon"> Commenter anonymement
            </label>
            <button class="fa-new-btn" id="faCommentSubmit" data-fiche-id="${escapeHtml(f.id)}">Envoyer</button>
          </div>
        </div>
      </div>
    `;

    // Bind comment submit
    document.getElementById('faCommentSubmit')?.addEventListener('click', async () => {
        const ta = document.getElementById('faCommentInput');
        const text = (ta?.value || '').trim();
        if (!text) { toast('Commentaire vide', 'warning'); return; }
        const isAnon = document.getElementById('faCommentAnon')?.checked ? 1 : 0;
        const btn = document.getElementById('faCommentSubmit');
        btn.disabled = true;
        const rr = await apiPost('add_fiche_amelioration_comment', {
            fiche_id: btn.dataset.ficheId, content: text, is_anonymous: isAnon,
        });
        btn.disabled = false;
        if (rr.success) { openDetail(btn.dataset.ficheId); } else { toast(rr.message || 'Erreur', 'danger'); }
    });

    // Bind RDV response
    document.querySelectorAll('[data-rdv-action]').forEach(b => {
        b.addEventListener('click', async () => {
            const action = b.dataset.rdvAction;
            const rdvId = b.dataset.rdvId;
            const resp = action === 'refusee' ? (prompt('Commentaire (optionnel) :') || '') : '';
            const rr = await apiPost('respond_fiche_amelioration_rdv', { rdv_id: rdvId, action_response: action, response: resp });
            if (rr.success) { toast(action === 'acceptee' ? 'RDV accepté' : 'RDV refusé', 'success'); openDetail(document.getElementById('faCommentSubmit').dataset.ficheId); }
            else toast(rr.message || 'Erreur', 'danger');
        });
    });
}

function renderRdvCard(rdv, isMine) {
    const statutLabels = { proposee:'Proposé', acceptee:'Accepté', refusee:'Refusé', effectuee:'Effectué', annulee:'Annulé' };
    const date = new Date(rdv.date_proposed).toLocaleString('fr-CH', { dateStyle:'medium', timeStyle:'short' });
    const actions = (isMine && rdv.statut === 'proposee') ? `
        <div class="fa-rdv-actions">
          <button class="fa-btn-accept" data-rdv-action="acceptee" data-rdv-id="${rdv.id}"><i class="bi bi-check"></i> Accepter</button>
          <button class="fa-btn-refuse" data-rdv-action="refusee" data-rdv-id="${rdv.id}"><i class="bi bi-x"></i> Refuser</button>
        </div>` : '';
    return `
      <div class="fa-rdv-card">
        <div class="fa-rdv-head">
          <div class="fa-rdv-date"><i class="bi bi-calendar-event"></i> ${escapeHtml(date)}</div>
          <span class="fa-badge bg-teal">${escapeHtml(statutLabels[rdv.statut] || rdv.statut)}</span>
        </div>
        ${rdv.lieu ? `<div class="small"><i class="bi bi-geo-alt"></i> ${escapeHtml(rdv.lieu)}</div>` : ''}
        ${rdv.admin_notes ? `<div class="small text-muted mt-1">${escapeHtml(rdv.admin_notes)}</div>` : ''}
        ${rdv.user_response ? `<div class="small mt-2"><em>Votre réponse :</em> ${escapeHtml(rdv.user_response)}</div>` : ''}
        ${actions}
      </div>`;
}
