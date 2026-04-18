<?php
require_responsable();
// ─── Données serveur ────────────────────────────────────────────────────────
$faStats = [
    'total'    => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration"),
    'soumise'  => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'soumise'"),
    'en_revue' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_revue'"),
    'en_cours' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_cours'"),
    'realisee' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'realisee'"),
    'haute'    => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE criticite='haute' AND statut NOT IN ('realisee','rejetee')"),
];

$faStatutLabels = [
    'soumise'  => ['label' => 'Soumise',  'cls' => 'fa-badge-teal'],
    'en_revue' => ['label' => 'En revue', 'cls' => 'fa-badge-orange'],
    'en_cours' => ['label' => 'En cours', 'cls' => 'fa-badge-purple'],
    'realisee' => ['label' => 'Réalisée', 'cls' => 'fa-badge-green'],
    'rejetee'  => ['label' => 'Rejetée',  'cls' => 'fa-badge-red'],
];
$faCriticiteLabels = [
    'faible'  => ['label' => 'Faible',  'cls' => 'fa-badge-teal'],
    'moyenne' => ['label' => 'Moyenne', 'cls' => 'fa-badge-orange'],
    'haute'   => ['label' => 'Haute',   'cls' => 'fa-badge-red'],
];
$faCategorieLabels = [
    'securite' => 'Sécurité', 'qualite_soins' => 'Qualité des soins',
    'organisation' => 'Organisation', 'materiel' => 'Matériel',
    'communication' => 'Communication', 'autre' => 'Autre',
];
?>
<style>
.fa-stat-row { display:grid; grid-template-columns:repeat(6, 1fr); gap:10px; margin-bottom:16px; }
@media (max-width: 991px) { .fa-stat-row { grid-template-columns:repeat(3, 1fr); } }
@media (max-width: 575px) { .fa-stat-row { grid-template-columns:repeat(2, 1fr); } }
.fa-stat-card { background:#fff; border:1px solid #E8E4DE; border-radius:10px; padding:12px; }
.fa-stat-card .fa-stat-icon { width:36px; height:36px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; color:#fff; margin-bottom:6px; }
.fa-stat-card .fa-stat-value { font-size:1.5rem; font-weight:700; line-height:1; }
.fa-stat-card .fa-stat-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.4px; color:#6c757d; margin-top:4px; }
.fa-icon-teal { background:#2d4a43; }
.fa-icon-orange { background:#C4A882; }
.fa-icon-purple { background:#5B4B6B; }
.fa-icon-green { background:#bcd2cb; color:#2d4a43 !important; }
.fa-icon-red { background:#A85B4A; }

.fa-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; align-items:center; }
.fa-filters select, .fa-filters input { font-size:.85rem; padding:6px 10px; border:1.5px solid #E8E4DE; border-radius:8px; }
.fa-filters select:focus, .fa-filters input:focus { outline:none; border-color:#C4A882; box-shadow:0 0 0 3px rgba(196,168,130,.18); }

.fa-adm-table { background:#fff; border:1px solid #E8E4DE; border-radius:12px; overflow:hidden; }
.fa-adm-table table { margin:0; font-size:.9rem; }
.fa-adm-table thead { background:#F7F5F2; }
.fa-adm-table th { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6c757d; padding:10px 12px; border-bottom:1px solid #E8E4DE; }
.fa-adm-table td { padding:10px 12px; vertical-align:middle; border-bottom:1px solid #F0EDE8; }
.fa-adm-table tr { cursor:pointer; transition:background .1s; }
.fa-adm-table tr:hover td { background:#F7F5F2; }

.fa-badge { font-size:.68rem; font-weight:700; padding:3px 8px; border-radius:4px; color:#fff; text-transform:uppercase; letter-spacing:.3px; display:inline-block; white-space:nowrap; }
.fa-badge-teal { background:#2d4a43; }
.fa-badge-orange { background:#C4A882; color:#3a2e1a; }
.fa-badge-purple { background:#5B4B6B; }
.fa-badge-green { background:#bcd2cb; color:#2d4a43; }
.fa-badge-red { background:#A85B4A; }

.fa-auth-cell { display:flex; align-items:center; gap:8px; }
.fa-avatar { width:28px; height:28px; border-radius:50%; background:#C4A882; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0; }
.fa-anon { color:#6c757d; font-style:italic; }

/* Modal */
.fa-mdl-head { background:#F7F5F2; border-bottom:1px solid #E8E4DE; padding:14px 18px; }
.fa-mdl-title { font-size:1.05rem; font-weight:700; margin-bottom:2px; }
.fa-mdl-meta { font-size:.78rem; color:#6c757d; display:flex; gap:10px; flex-wrap:wrap; }
.fa-mdl-body { padding:18px; max-height:70vh; overflow-y:auto; }

.fa-section { margin-bottom:18px; }
.fa-section-title { font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
.fa-desc-box { background:#F7F5F2; border-left:3px solid #C4A882; padding:12px 15px; border-radius:0 8px 8px 0; font-size:.9rem; line-height:1.55; white-space:pre-wrap; }

.fa-comments { display:flex; flex-direction:column; gap:8px; }
.fa-comment { padding:10px 12px; border-radius:8px; border:1px solid #E8E4DE; background:#fff; font-size:.85rem; }
.fa-comment.role-admin { background:#F7F5F2; border-left:3px solid #2d4a43; }
.fa-comment-head { display:flex; justify-content:space-between; font-size:.75rem; color:#6c757d; margin-bottom:4px; }

.fa-statut-pick { display:flex; gap:4px; flex-wrap:wrap; margin-top:6px; }
.fa-statut-opt { font-size:.78rem; padding:6px 12px; border:1.5px solid #E8E4DE; border-radius:999px; cursor:pointer; background:#fff; transition:all .15s; }
.fa-statut-opt:hover { background:#F7F5F2; border-color:#C4A882; }
.fa-statut-opt.active { background:#2d4a43; color:#fff; border-color:#2d4a43; }

.fa-rdv-card { border:1px solid #E8E4DE; border-radius:10px; padding:12px; margin-bottom:8px; background:#fff; }
.fa-rdv-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }

.fa-chip-list { display:flex; flex-wrap:wrap; gap:6px; }
.fa-chip { background:#F7F5F2; border:1px solid #E8E4DE; padding:3px 10px; border-radius:999px; font-size:.78rem; display:inline-flex; align-items:center; gap:6px; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Amélioration continue</h4>
        <p class="text-muted small mb-0">Suivi des fiches d'amélioration soumises par les collaborateurs</p>
    </div>
</div>

<!-- Stats -->
<div class="fa-stat-row">
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-teal"><i class="bi bi-inbox"></i></div><div class="fa-stat-value"><?= $faStats['total'] ?></div><div class="fa-stat-label">Total</div></div>
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-teal"><i class="bi bi-send"></i></div><div class="fa-stat-value"><?= $faStats['soumise'] ?></div><div class="fa-stat-label">Soumises</div></div>
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-orange"><i class="bi bi-eye"></i></div><div class="fa-stat-value"><?= $faStats['en_revue'] ?></div><div class="fa-stat-label">En revue</div></div>
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-purple"><i class="bi bi-gear"></i></div><div class="fa-stat-value"><?= $faStats['en_cours'] ?></div><div class="fa-stat-label">En cours</div></div>
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-green"><i class="bi bi-check-circle"></i></div><div class="fa-stat-value"><?= $faStats['realisee'] ?></div><div class="fa-stat-label">Réalisées</div></div>
    <div class="fa-stat-card"><div class="fa-stat-icon fa-icon-red"><i class="bi bi-exclamation-triangle"></i></div><div class="fa-stat-value"><?= $faStats['haute'] ?></div><div class="fa-stat-label">Haute criticité</div></div>
</div>

<!-- Filters -->
<div class="fa-filters">
    <select id="faFltStatut"><option value="">Tous les statuts</option>
        <?php foreach ($faStatutLabels as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l['label']) ?></option><?php endforeach ?>
    </select>
    <select id="faFltCategorie"><option value="">Toutes les catégories</option>
        <?php foreach ($faCategorieLabels as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach ?>
    </select>
    <select id="faFltCriticite"><option value="">Toutes les criticités</option>
        <?php foreach ($faCriticiteLabels as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l['label']) ?></option><?php endforeach ?>
    </select>
    <input type="text" id="faFltSearch" placeholder="Rechercher dans les titres/descriptions..." style="flex:1; min-width:200px">
</div>

<!-- Table -->
<div class="fa-adm-table">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th style="width:3%"></th>
                <th style="width:35%">Titre</th>
                <th style="width:15%">Auteur</th>
                <th style="width:12%">Catégorie</th>
                <th style="width:10%">Criticité</th>
                <th style="width:10%">Statut</th>
                <th style="width:10%">Date</th>
                <th style="width:5%"></th>
            </tr>
        </thead>
        <tbody id="faTableBody">
            <tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</td></tr>
        </tbody>
    </table>
</div>

<!-- Detail modal -->
<div class="modal fade" id="faDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="fa-mdl-head d-flex align-items-start justify-content-between">
                <div>
                    <div class="fa-mdl-title" id="faMdlTitle">—</div>
                    <div class="fa-mdl-meta" id="faMdlMeta"></div>
                </div>
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:.85rem"></i></button>
            </div>
            <div class="fa-mdl-body" id="faMdlBody">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- RDV modal -->
<div class="modal fade" id="faRdvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Proposer un RDV</h5>
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:.85rem"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Date et heure</label>
                    <input type="datetime-local" class="form-control" id="faRdvDate">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Lieu (optionnel)</label>
                    <input type="text" class="form-control" id="faRdvLieu" placeholder="Bureau RH, Salle 2, visio...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Note à l'auteur (optionnel)</label>
                    <textarea class="form-control" id="faRdvNotes" rows="3" placeholder="Contexte, agenda, objectifs du RDV..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" id="faRdvSubmit"><i class="bi bi-send"></i> Proposer</button>
            </div>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    const statutLabels = <?= json_encode($faStatutLabels) ?>;
    const criLabels = <?= json_encode($faCriticiteLabels) ?>;
    const catLabels = <?= json_encode($faCategorieLabels) ?>;
    let currentFicheId = null;
    let detailModal = null, rdvModal = null;

    document.addEventListener('DOMContentLoaded', boot);
    if (document.readyState !== 'loading') boot();

    function boot() {
        detailModal = new bootstrap.Modal(document.getElementById('faDetailModal'));
        rdvModal = new bootstrap.Modal(document.getElementById('faRdvModal'));

        loadList();

        ['faFltStatut','faFltCategorie','faFltCriticite'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', loadList);
        });
        document.getElementById('faFltSearch')?.addEventListener('input', debounce(loadList, 300));
        document.getElementById('faRdvSubmit')?.addEventListener('click', submitRdv);
    }

    function debounce(fn, ms) {
        let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }

    async function loadList() {
        const tb = document.getElementById('faTableBody');
        tb.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
        const r = await adminApiPost('admin_list_fiches_amelioration', {
            statut: document.getElementById('faFltStatut').value,
            categorie: document.getElementById('faFltCategorie').value,
            criticite: document.getElementById('faFltCriticite').value,
            search: document.getElementById('faFltSearch').value.trim(),
        });
        if (!r.success) { tb.innerHTML = '<tr><td colspan="8" class="text-danger">Erreur</td></tr>'; return; }
        if (!r.fiches.length) { tb.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Aucune fiche</td></tr>'; return; }
        tb.innerHTML = r.fiches.map(renderRow).join('');
        tb.querySelectorAll('[data-fiche]').forEach(tr => {
            tr.addEventListener('click', () => openDetail(tr.dataset.fiche));
        });
    }

    function renderRow(f) {
        const st = statutLabels[f.statut] || { label: f.statut, cls: 'fa-badge-teal' };
        const cr = criLabels[f.criticite] || { label: f.criticite, cls: 'fa-badge-teal' };
        const cat = catLabels[f.categorie] || f.categorie;
        const authorHtml = f.is_anonymous == 1
            ? '<span class="fa-anon"><i class="bi bi-incognito"></i> Anonyme</span>'
            : `<div class="fa-auth-cell">
                <div class="fa-avatar">${esc(((f.auteur_prenom||'')[0]||'')+((f.auteur_nom||'')[0]||'')).toUpperCase()}</div>
                <span>${esc((f.auteur_prenom||'') + ' ' + (f.auteur_nom||''))}</span>
              </div>`;
        const icon = f.visibility === 'public' ? 'bi-globe' : (f.visibility === 'targeted' ? 'bi-bullseye' : 'bi-lock');
        return `
          <tr data-fiche="${esc(f.id)}">
            <td><i class="bi ${icon} text-muted" title="${esc(f.visibility)}"></i></td>
            <td><strong>${esc(f.titre)}</strong>${parseInt(f.nb_comments)>0 ? `<span class="text-muted small ms-2"><i class="bi bi-chat-dots"></i> ${parseInt(f.nb_comments)}</span>` : ''}</td>
            <td>${authorHtml}</td>
            <td>${esc(cat)}</td>
            <td><span class="fa-badge ${cr.cls}">${esc(cr.label)}</span></td>
            <td><span class="fa-badge ${st.cls}">${esc(st.label)}</span></td>
            <td><small>${new Date(f.created_at).toLocaleDateString('fr-CH')}</small></td>
            <td><i class="bi bi-chevron-right text-muted"></i></td>
          </tr>`;
    }

    async function openDetail(id) {
        currentFicheId = id;
        document.getElementById('faMdlTitle').textContent = '—';
        document.getElementById('faMdlMeta').innerHTML = '';
        document.getElementById('faMdlBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
        detailModal.show();
        const r = await adminApiPost('admin_get_fiche_amelioration', { id });
        if (!r.success) { document.getElementById('faMdlBody').innerHTML = '<div class="text-danger">Erreur</div>'; return; }
        renderDetail(r);
    }

    function renderDetail(r) {
        const f = r.fiche, auteur = r.auteur;
        const st = statutLabels[f.statut] || { label: f.statut, cls: 'fa-badge-teal' };
        const cr = criLabels[f.criticite] || { label: f.criticite, cls: 'fa-badge-teal' };
        const cat = catLabels[f.categorie] || f.categorie;

        document.getElementById('faMdlTitle').textContent = f.titre;

        const authorHtml = (f.is_anonymous == 1)
            ? '<span class="fa-anon"><i class="bi bi-incognito"></i> Auteur anonyme (non traçable)</span>'
            : auteur ? `${esc(auteur.prenom + ' ' + auteur.nom)} <small class="text-muted">· ${esc(auteur.email||'')}</small>` : 'Inconnu';
        const visIcon = f.visibility === 'public' ? '🌐' : (f.visibility === 'targeted' ? '🎯' : '🔒');

        document.getElementById('faMdlMeta').innerHTML = `
            <span><i class="bi bi-person"></i> ${authorHtml}</span>
            <span><i class="bi bi-clock"></i> ${new Date(f.created_at).toLocaleDateString('fr-CH')}</span>
            <span><i class="bi bi-tag"></i> ${esc(cat)}</span>
            <span>${visIcon} ${esc(f.visibility)}</span>
            <span class="fa-badge ${cr.cls}">${esc(cr.label)}</span>
            <span class="fa-badge ${st.cls}">${esc(st.label)}</span>
        `;

        const commentsHtml = (r.comments || []).map(c => `
            <div class="fa-comment role-${esc(c.role||'user')}">
                <div class="fa-comment-head">
                    <strong>${esc((c.prenom||'') + ' ' + (c.nom||''))}${c.role === 'admin' ? ' <span class="text-success small">(Admin)</span>' : ''}</strong>
                    <span>${new Date(c.created_at).toLocaleString('fr-CH')}</span>
                </div>
                <div>${esc(c.content).replace(/\n/g,'<br>')}</div>
            </div>`).join('') || '<div class="text-muted small">Aucun commentaire</div>';

        const concernesHtml = (r.concernes && r.concernes.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-people"></i> Personnes concernées</div>
                <div class="fa-chip-list">
                    ${r.concernes.map(u => `<span class="fa-chip"><div class="fa-avatar" style="width:22px;height:22px;font-size:.62rem">${esc(((u.prenom||'')[0]||'')+((u.nom||'')[0]||'')).toUpperCase()}</div>${esc(u.prenom + ' ' + u.nom)}</span>`).join('')}
                </div>
            </div>` : '';

        const rdvsHtml = (r.rdvs && r.rdvs.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-calendar-event"></i> Rendez-vous</div>
                ${r.rdvs.map(rdv => `
                    <div class="fa-rdv-card">
                        <div class="fa-rdv-head">
                            <strong><i class="bi bi-calendar-event"></i> ${new Date(rdv.date_proposed).toLocaleString('fr-CH', { dateStyle:'medium', timeStyle:'short' })}</strong>
                            <span class="fa-badge fa-badge-${rdv.statut === 'acceptee' ? 'green' : rdv.statut === 'refusee' ? 'red' : 'teal'}">${esc(rdv.statut)}</span>
                        </div>
                        ${rdv.lieu ? `<div class="small"><i class="bi bi-geo-alt"></i> ${esc(rdv.lieu)}</div>` : ''}
                        ${rdv.admin_notes ? `<div class="small text-muted mt-1">${esc(rdv.admin_notes)}</div>` : ''}
                        ${rdv.user_response ? `<div class="small mt-2"><em>Réponse auteur :</em> ${esc(rdv.user_response)}</div>` : ''}
                    </div>`).join('')}
            </div>` : '';

        const attachmentsHtml = (r.attachments && r.attachments.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-paperclip"></i> Pièces jointes</div>
                ${r.attachments.map(a => `<a href="/spocspace/admin/api.php?action=admin_download_fiche_amelioration_attachment&id=${esc(a.id)}" target="_blank" class="d-block small">${esc(a.original_name)}</a>`).join('')}
            </div>` : '';

        const canPropose = (f.is_anonymous != 1);

        document.getElementById('faMdlBody').innerHTML = `
            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-card-text"></i> Description</div>
                <div class="fa-desc-box">${esc(f.description)}</div>
            </div>
            ${f.suggestion ? `<div class="fa-section"><div class="fa-section-title"><i class="bi bi-lightbulb"></i> Suggestion</div><div class="fa-desc-box">${esc(f.suggestion)}</div></div>` : ''}
            ${concernesHtml}
            ${rdvsHtml}
            ${attachmentsHtml}

            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-flag"></i> Statut</div>
                <div class="fa-statut-pick" id="faStatutPick">
                    ${Object.entries(statutLabels).map(([k, v]) => `<button class="fa-statut-opt ${k === f.statut ? 'active' : ''}" data-statut="${esc(k)}">${esc(v.label)}</button>`).join('')}
                </div>
            </div>

            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-chat-dots"></i> Commentaires</div>
                <div class="fa-comments">${commentsHtml}</div>
                <div class="mt-2">
                    <textarea class="form-control form-control-sm" id="faAdmComment" rows="2" placeholder="Réponse admin..."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                        ${canPropose ? '<button class="btn btn-outline-primary btn-sm" id="faProposeRdv"><i class="bi bi-calendar-plus"></i> Proposer un RDV</button>' : '<span class="small text-muted"><i class="bi bi-incognito"></i> RDV impossible (auteur anonyme)</span>'}
                        <button class="btn btn-success btn-sm" id="faAdmCommentSubmit"><i class="bi bi-send"></i> Envoyer</button>
                    </div>
                </div>
            </div>
        `;

        document.querySelectorAll('#faStatutPick .fa-statut-opt').forEach(b => {
            b.addEventListener('click', async () => {
                const newSt = b.dataset.statut;
                if (!confirm('Changer le statut ? Un email sera envoyé à l\'auteur (si non anonyme).')) return;
                const rr = await adminApiPost('admin_update_fiche_amelioration_statut', { id: currentFicheId, statut: newSt });
                if (rr.success) { showToast('Statut mis à jour', 'success'); openDetail(currentFicheId); loadList(); }
                else showToast(rr.message || 'Erreur', 'danger');
            });
        });

        document.getElementById('faAdmCommentSubmit')?.addEventListener('click', async () => {
            const text = (document.getElementById('faAdmComment').value || '').trim();
            if (!text) { showToast('Commentaire vide', 'warning'); return; }
            const rr = await adminApiPost('admin_add_fiche_amelioration_comment', { fiche_id: currentFicheId, content: text });
            if (rr.success) { showToast('Commentaire ajouté', 'success'); openDetail(currentFicheId); }
            else showToast(rr.message || 'Erreur', 'danger');
        });

        document.getElementById('faProposeRdv')?.addEventListener('click', () => {
            document.getElementById('faRdvDate').value = '';
            document.getElementById('faRdvLieu').value = '';
            document.getElementById('faRdvNotes').value = '';
            rdvModal.show();
        });
    }

    async function submitRdv() {
        const date = document.getElementById('faRdvDate').value;
        const lieu = document.getElementById('faRdvLieu').value.trim();
        const notes = document.getElementById('faRdvNotes').value.trim();
        if (!date) { showToast('Date requise', 'warning'); return; }
        const btn = document.getElementById('faRdvSubmit');
        btn.disabled = true;
        const r = await adminApiPost('admin_propose_fiche_amelioration_rdv', {
            fiche_id: currentFicheId, date_proposed: date, lieu, admin_notes: notes,
        });
        btn.disabled = false;
        if (r.success) { showToast('RDV proposé — email envoyé à l\'auteur', 'success'); rdvModal.hide(); openDetail(currentFicheId); }
        else showToast(r.message || 'Erreur', 'danger');
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function showToast(msg, type) { if (typeof toast === 'function') toast(msg, type); else alert(msg); }

    boot();
})();
</script>
