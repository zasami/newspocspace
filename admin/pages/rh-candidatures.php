<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$ssrTotal = (int) Db::getOne("SELECT COUNT(*) FROM candidatures");
$ssrRecues = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'recue'");
$ssrEnCours = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'en_cours'");
$ssrEntretien = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'entretien'");
$ssrAcceptees = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'acceptee'");
$ssrRefusees = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'refusee'");
$ssrOffres = Db::fetchAll("SELECT id, titre FROM offres_emploi ORDER BY created_at DESC");
?>
<style>
/* ── Stat cards ── */
.rhc-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.rhc-stat-card {
    flex: 1; min-width: 120px; text-align: center; padding: 16px 10px;
    border-radius: 14px; border: 1px solid var(--cl-border-light, #F0EDE8);
    background: var(--cl-surface, #fff);
}
.rhc-stat-icon {
    width: 38px; height: 38px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem; margin-bottom: 8px;
}
.rhc-stat-val { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
.rhc-stat-lbl { font-size: .7rem; color: var(--cl-text-muted); margin-top: 3px; }

/* ── Table wrapper ── */
.rhc-table-wrap {
    border: 1.5px solid var(--cl-border-light, #F0EDE8);
    border-radius: 14px; overflow: hidden;
    background: var(--cl-surface, #fff);
}
.rhc-table { width: 100%; border-collapse: collapse; }
.rhc-table th {
    font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
    color: var(--cl-text-muted); padding: 12px 14px;
    border-bottom: 1.5px solid var(--cl-border-light, #F0EDE8);
    text-align: left; background: var(--cl-bg, #F7F5F2);
}
.rhc-table th:first-child { border-top-left-radius: 14px; }
.rhc-table th:last-child { border-top-right-radius: 14px; }
.rhc-table td { padding: 11px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.rhc-table tr:last-child td { border-bottom: none; }
.rhc-table tr:hover td { background: var(--cl-bg, #FAFAF7); }

/* ── Badges ── */
.rhc-badge { font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; }
.rhc-badge-recue     { background: #D4C4A8; color: #6B5B3E; }
.rhc-badge-en_cours  { background: #B8C9D4; color: #3B4F6B; }
.rhc-badge-entretien { background: #D0C4D8; color: #5B4B6B; }
.rhc-badge-acceptee  { background: #bcd2cb; color: #2d4a43; }
.rhc-badge-refusee   { background: #E2B8AE; color: #7B3B2C; }
.rhc-badge-archivee  { background: var(--cl-border-light, #E8E4DE); color: var(--cl-text-muted); }
.rhc-timer { font-size: .72rem; color: var(--cl-text-muted); }

/* ── Detail sections ── */
.rhc-detail-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin-bottom: 4px; }
.rhc-detail-val { font-size: .88rem; }
.rhc-detail-box { white-space: pre-wrap; background: var(--cl-bg); padding: 10px; border-radius: 8px; font-size: .85rem; }
/* ── Document cards ── */
.rhc-docs-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
.rhc-doc-card {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    border-radius: 10px; border: 1px solid var(--cl-border-light, #F0EDE8);
    background: var(--cl-bg, #F7F5F2); cursor: pointer; transition: all .15s;
    min-width: 180px; flex: 1; max-width: 280px;
}
.rhc-doc-card:hover { border-color: var(--cl-border-hover, #D4D0CA); background: var(--cl-surface, #fff); box-shadow: 0 2px 8px rgba(0,0,0,.04); }
.rhc-doc-icon {
    width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.rhc-doc-icon-pdf { background: #FECACA; color: #991B1B; }
.rhc-doc-icon-word { background: #DBEAFE; color: #1E40AF; }
.rhc-doc-icon-img { background: #D1FAE5; color: #065F46; }
.rhc-doc-icon-other { background: #E5E7EB; color: #374151; }
.rhc-doc-info { flex: 1; min-width: 0; }
.rhc-doc-name { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rhc-doc-meta { font-size: .68rem; color: var(--cl-text-muted); }

/* ── Lightbox ── */
.rhc-lightbox {
    position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.85);
    display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);
}
.rhc-lightbox-close {
    position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 1.2rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.rhc-lightbox-close:hover { background: rgba(255,255,255,.3); }
.rhc-lightbox-content { max-width: 90vw; max-height: 85vh; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.rhc-lightbox-content img { max-width: 90vw; max-height: 85vh; object-fit: contain; display: block; }
.rhc-lightbox-content iframe { width: 80vw; height: 85vh; border: none; background: #fff; }
.rhc-lightbox-download {
    position: absolute; bottom: 20px; right: 20px; padding: 8px 16px; border-radius: 8px;
    background: rgba(255,255,255,.15); border: none; color: #fff; font-size: .85rem;
    cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 6px;
}
.rhc-lightbox-download:hover { background: rgba(255,255,255,.3); color: #fff; }

/* ── Action buttons ── */
.rhc-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .88rem; transition: all .12s; display: flex; align-items: center; justify-content: center; }
.rhc-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }

/* ── Empty ── */
.rhc-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.rhc-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-lines-fill"></i> Candidatures</h4>
</div>

<!-- Stat cards -->
<div class="rhc-stats">
  <div class="rhc-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="rhc-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-people"></i></div>
    <div class="rhc-stat-val" style="color:#2d4a43" id="rhcStatTotal"><?= $ssrTotal ?></div>
    <div class="rhc-stat-lbl">Total</div>
  </div>
  <div class="rhc-stat-card" style="background:#f8f4ed;border-color:#e8dece">
    <div class="rhc-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-inbox"></i></div>
    <div class="rhc-stat-val" style="color:#6B5B3E" id="rhcStatRecues"><?= $ssrRecues ?></div>
    <div class="rhc-stat-lbl">Reçues</div>
  </div>
  <div class="rhc-stat-card" style="background:#f0f4f8;border-color:#d4dfe8">
    <div class="rhc-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-arrow-repeat"></i></div>
    <div class="rhc-stat-val" style="color:#3B4F6B" id="rhcStatEnCours"><?= $ssrEnCours ?></div>
    <div class="rhc-stat-lbl">En cours</div>
  </div>
  <div class="rhc-stat-card" style="background:#f4f0f6;border-color:#e0d8e6">
    <div class="rhc-stat-icon" style="background:#D0C4D8;color:#5B4B6B"><i class="bi bi-calendar-event"></i></div>
    <div class="rhc-stat-val" style="color:#5B4B6B" id="rhcStatEntretien"><?= $ssrEntretien ?></div>
    <div class="rhc-stat-lbl">Entretien</div>
  </div>
  <div class="rhc-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="rhc-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check-circle"></i></div>
    <div class="rhc-stat-val" style="color:#2d4a43" id="rhcStatAcceptees"><?= $ssrAcceptees ?></div>
    <div class="rhc-stat-lbl">Acceptées</div>
  </div>
  <div class="rhc-stat-card" style="background:#f8f0ee;border-color:#e8d4ce">
    <div class="rhc-stat-icon" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-x-circle"></i></div>
    <div class="rhc-stat-val" style="color:#7B3B2C" id="rhcStatRefusees"><?= $ssrRefusees ?></div>
    <div class="rhc-stat-lbl">Refusées</div>
  </div>
</div>

<!-- Filters -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <select class="form-select form-select-sm" id="rhcOffreFilter" style="max-width:220px">
    <option value="">Toutes les offres</option>
    <?php foreach ($ssrOffres as $o): ?>
      <option value="<?= h($o['id']) ?>"><?= h($o['titre']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-select form-select-sm" id="rhcStatutFilter" style="max-width:160px">
    <option value="">Tous les statuts</option>
    <option value="recue">Reçue</option>
    <option value="en_cours">En cours</option>
    <option value="entretien">Entretien</option>
    <option value="acceptee">Acceptée</option>
    <option value="refusee">Refusée</option>
    <option value="archivee">Archivée</option>
  </select>
  <input type="text" class="form-control form-control-sm" id="rhcSearch" placeholder="Rechercher..." style="max-width:200px">
</div>

<!-- Table body -->
<div id="rhcBody">
  <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
</div>

<!-- ═══ Modal: Candidature detail ═══ -->
<div class="modal fade" id="rhcDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhcDetailTitle">Candidature</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="rhcDetailBody">
        <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger me-auto" id="btnDeleteCand"><i class="bi bi-trash"></i> Supprimer</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnSaveCand"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Confirm delete ═══ -->
<div class="modal fade" id="rhcDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Supprimer la candidature ?</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="mb-0 small text-muted">Cette action supprimera la candidature et tous ses documents.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDeleteCand"><i class="bi bi-trash"></i> Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const STATUT_LABELS = { recue:'Reçue', en_cours:'En cours', entretien:'Entretien', acceptee:'Acceptée', refusee:'Refusée', archivee:'Archivée' };
    let candData = [];
    let currentCandId = null;
    let searchDebounce = null;

    function loadCandidatures() {
        const params = {
            offre_id: document.getElementById('rhcOffreFilter')?.value || '',
            statut: document.getElementById('rhcStatutFilter')?.value || '',
            search: document.getElementById('rhcSearch')?.value.trim() || '',
        };
        adminApiPost('admin_get_candidatures', params).then(r => {
            if (!r.success) return;
            candData = r.candidatures || [];
            renderCandidatures(r.total || 0);
        });
    }

    function renderCandidatures(total) {
        const el = document.getElementById('rhcBody');
        if (!el) return;
        if (!candData.length) {
            el.innerHTML = '<div class="rhc-empty"><i class="bi bi-person-lines-fill"></i>Aucune candidature</div>';
            return;
        }
        const today = new Date(); today.setHours(0,0,0,0);
        let html = '<div class="rhc-table-wrap"><table class="rhc-table"><thead><tr><th>Date</th><th>Nom Prénom</th><th>Email</th><th>Offre</th><th>Statut</th><th>Code suivi</th><th>Timer</th><th style="width:60px">Actions</th></tr></thead><tbody>';
        candData.forEach(c => {
            const cls = c.statut || 'archivee';
            const created = new Date(c.created_at); created.setHours(0,0,0,0);
            const days = Math.floor((today - created) / 86400000);
            const timerTxt = days === 0 ? "aujourd'hui" : days + 'j';
            html += `<tr>
                <td>${formatDate(c.created_at)}</td>
                <td><strong>${escapeHtml(c.nom || '')} ${escapeHtml(c.prenom || '')}</strong></td>
                <td>${escapeHtml(c.email || '-')}</td>
                <td>${escapeHtml(c.offre_titre || '-')}</td>
                <td><span class="rhc-badge rhc-badge-${cls}">${escapeHtml(STATUT_LABELS[c.statut] || c.statut)}</span></td>
                <td><code style="font-size:.78rem">${escapeHtml(c.code_suivi || '-')}</code></td>
                <td><span class="rhc-timer"><i class="bi bi-clock"></i> ${timerTxt}</span></td>
                <td><button class="rhc-row-btn" data-view="${c.id}" title="Voir détail"><i class="bi bi-eye"></i></button></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        if (total > candData.length) html += `<p class="text-muted small mt-2">${candData.length} / ${total} résultats affichés</p>`;
        el.innerHTML = html;
    }

    document.getElementById('rhcOffreFilter')?.addEventListener('change', loadCandidatures);
    document.getElementById('rhcStatutFilter')?.addEventListener('change', loadCandidatures);
    document.getElementById('rhcSearch')?.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(loadCandidatures, 300);
    });

    document.getElementById('rhcBody')?.addEventListener('click', e => {
        const btn = e.target.closest('[data-view]');
        if (btn) openDetail(btn.dataset.view);
    });

    function openDetail(id) {
        currentCandId = id;
        document.getElementById('rhcDetailBody').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
        new bootstrap.Modal(document.getElementById('rhcDetailModal')).show();

        adminApiPost('admin_get_candidature_detail', { id }).then(r => {
            if (!r.success) { document.getElementById('rhcDetailBody').innerHTML = '<p class="text-danger">Erreur</p>'; return; }
            const c = r.candidature;
            document.getElementById('rhcDetailTitle').textContent = `${c.prenom || ''} ${c.nom || ''} - ${c.offre_titre || 'Candidature'}`;

            const today = new Date(); today.setHours(0,0,0,0);
            const created = new Date(c.created_at); created.setHours(0,0,0,0);
            const days = Math.floor((today - created) / 86400000);

            const field = (label, val) => `<div class="mb-3"><div class="rhc-detail-label">${label}</div><div class="rhc-detail-val">${val}</div></div>`;

            let html = '<div class="row g-3">';
            html += `<div class="col-md-6">${field('Nom Prénom', escapeHtml((c.nom||'')+' '+(c.prenom||'')))}</div>`;
            html += `<div class="col-md-6">${field('Email', c.email ? '<a href="mailto:'+escapeHtml(c.email)+'">'+escapeHtml(c.email)+'</a>' : '-')}</div>`;
            html += `<div class="col-md-4">${field('Téléphone', escapeHtml(c.telephone || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Date de naissance', c.date_naissance ? formatDate(c.date_naissance) : '-')}</div>`;
            html += `<div class="col-md-4">${field('Nationalité', escapeHtml(c.nationalite || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Permis de travail', escapeHtml(c.permis_travail || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Disponibilité', escapeHtml(c.disponibilite || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Code de suivi', '<code>'+escapeHtml(c.code_suivi || '-')+'</code>')}</div>`;
            if (c.adresse) html += `<div class="col-12">${field('Adresse', escapeHtml(c.adresse))}</div>`;

            html += '<div class="col-12"><hr class="my-1"></div>';
            if (c.motivation) html += `<div class="col-12">${field('Motivation', '<div class="rhc-detail-box">'+escapeHtml(c.motivation)+'</div>')}</div>`;
            if (c.experience) html += `<div class="col-12">${field('Expérience', '<div class="rhc-detail-box">'+escapeHtml(c.experience)+'</div>')}</div>`;

            if (c.documents && c.documents.length) {
                html += '<div class="col-12"><div class="rhc-detail-label">Documents</div><div class="rhc-docs-grid">';
                c.documents.forEach(d => {
                    const sizeKb = d.size ? Math.round(d.size / 1024) : '?';
                    const ext = (d.original_name || '').split('.').pop().toLowerCase();
                    const url = 'admin/api.php?action=admin_download_candidature_doc&id=' + encodeURIComponent(d.id);
                    let iconCls = 'rhc-doc-icon-other', icon = 'bi-file-earmark';
                    if (ext === 'pdf') { iconCls = 'rhc-doc-icon-pdf'; icon = 'bi-file-earmark-pdf'; }
                    else if (['doc','docx'].includes(ext)) { iconCls = 'rhc-doc-icon-word'; icon = 'bi-file-earmark-word'; }
                    else if (['jpg','jpeg','png','gif','webp'].includes(ext)) { iconCls = 'rhc-doc-icon-img'; icon = 'bi-file-earmark-image'; }
                    const typeLabel = { cv:'CV', lettre_motivation:'Lettre', diplome:'Diplôme', certificat:'Certificat', autre:'Autre' }[d.type_document] || d.type_document;
                    html += `<div class="rhc-doc-card" data-doc-url="${escapeHtml(url)}" data-doc-ext="${ext}" data-doc-name="${escapeHtml(d.original_name)}">
                        <div class="rhc-doc-icon ${iconCls}"><i class="bi ${icon}"></i></div>
                        <div class="rhc-doc-info">
                            <div class="rhc-doc-name" title="${escapeHtml(d.original_name)}">${escapeHtml(d.original_name)}</div>
                            <div class="rhc-doc-meta">${escapeHtml(typeLabel)} — ${sizeKb} Ko</div>
                        </div>
                    </div>`;
                });
                html += '</div></div>';
            }

            html += `<div class="col-md-6"><div class="rhc-detail-label">Statut</div>
                <select class="form-select form-select-sm mt-1" id="rhcStatutSelect">
                    <option value="recue" ${c.statut==='recue'?'selected':''}>Reçue</option>
                    <option value="en_cours" ${c.statut==='en_cours'?'selected':''}>En cours</option>
                    <option value="entretien" ${c.statut==='entretien'?'selected':''}>Entretien</option>
                    <option value="acceptee" ${c.statut==='acceptee'?'selected':''}>Acceptée</option>
                    <option value="refusee" ${c.statut==='refusee'?'selected':''}>Refusée</option>
                    <option value="archivee" ${c.statut==='archivee'?'selected':''}>Archivée</option>
                </select></div>`;
            html += `<div class="col-md-6"><div class="rhc-detail-label" style="margin-bottom:8px">Timer</div><span class="rhc-timer"><i class="bi bi-clock"></i> Reçue il y a ${days} jour${days > 1 ? 's' : ''}</span></div>`;
            html += `<div class="col-12"><div class="rhc-detail-label">Notes admin</div>
                <textarea class="form-control form-control-sm mt-1" id="rhcNotesAdmin" rows="3">${escapeHtml(c.notes_admin || '')}</textarea></div>`;
            html += '</div>';
            document.getElementById('rhcDetailBody').innerHTML = html;

            // Bind doc card clicks
            document.querySelectorAll('.rhc-doc-card').forEach(card => {
                card.addEventListener('click', () => {
                    const url = card.dataset.docUrl;
                    const ext = card.dataset.docExt;
                    const name = card.dataset.docName;
                    openDocLightbox(url, ext, name);
                });
            });
        });
    }

    function openDocLightbox(url, ext, name) {
        document.getElementById('rhcLightbox')?.remove();

        const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);
        const isPdf = ext === 'pdf';

        let content;
        if (isImage) {
            content = '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(name) + '">';
        } else if (isPdf) {
            content = '<iframe src="' + escapeHtml(url) + '#toolbar=1" sandbox="allow-same-origin allow-scripts"></iframe>';
        } else {
            // Word/other: direct download
            window.open(url, '_blank');
            return;
        }

        const lb = document.createElement('div');
        lb.id = 'rhcLightbox';
        lb.className = 'rhc-lightbox';
        lb.innerHTML = `
            <button class="rhc-lightbox-close" title="Fermer"><i class="bi bi-x-lg"></i></button>
            <div class="rhc-lightbox-content">${content}</div>
            <a href="${escapeHtml(url)}" download="${escapeHtml(name)}" class="rhc-lightbox-download"><i class="bi bi-download"></i> Télécharger</a>
        `;

        document.body.appendChild(lb);

        lb.querySelector('.rhc-lightbox-close').addEventListener('click', () => lb.remove());
        lb.addEventListener('click', e => { if (e.target === lb) lb.remove(); });
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { lb.remove(); document.removeEventListener('keydown', esc); } });
    }

    document.getElementById('btnSaveCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        const statut = document.getElementById('rhcStatutSelect')?.value;
        const notes = document.getElementById('rhcNotesAdmin')?.value || '';
        adminApiPost('admin_update_candidature_status', { id: currentCandId, statut, notes_admin: notes }).then(r => {
            if (r.success) {
                showToast('Candidature mise à jour', 'success');
                bootstrap.Modal.getInstance(document.getElementById('rhcDetailModal'))?.hide();
                loadCandidatures();
            } else showToast(r.error || 'Erreur', 'danger');
        });
    });

    document.getElementById('btnDeleteCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        bootstrap.Modal.getInstance(document.getElementById('rhcDetailModal'))?.hide();
        new bootstrap.Modal(document.getElementById('rhcDeleteModal')).show();
    });

    document.getElementById('btnConfirmDeleteCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        adminApiPost('admin_delete_candidature', { id: currentCandId }).then(r => {
            bootstrap.Modal.getInstance(document.getElementById('rhcDeleteModal'))?.hide();
            if (r.success) { showToast('Candidature supprimée', 'success'); loadCandidatures(); }
            else showToast(r.error || 'Erreur', 'danger');
            currentCandId = null;
        });
    });

    function formatDate(d) {
        if (!d) return '-';
        try { const dt = new Date(d); return dt.toLocaleDateString('fr-CH', { day:'2-digit', month:'2-digit', year:'numeric' }); } catch(e) { return d; }
    }

    loadCandidatures();
})();
</script>
