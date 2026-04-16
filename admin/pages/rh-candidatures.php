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
/* ── Documents grouped by type ── */
.rhc-docs-wrap { margin-top: 6px; display: flex; flex-direction: column; gap: 14px; }
.rhc-docs-group {
    border: 1px solid var(--cl-border-light, #F0EDE8);
    border-radius: 12px; overflow: hidden; background: var(--cl-surface, #fff);
}
.rhc-docs-group-head {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: var(--cl-bg, #F7F5F2);
    border-bottom: 1px solid var(--cl-border-light, #F0EDE8);
}
.rhc-docs-group-icon {
    width: 30px; height: 30px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
}
.rhc-docs-group-title {
    font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
    color: var(--cl-text);
}
.rhc-docs-group-count {
    font-size: .68rem; color: var(--cl-text-muted);
    background: #fff; border: 1px solid var(--cl-border-light, #F0EDE8);
    padding: 1px 8px; border-radius: 20px; font-weight: 600;
}
.rhc-docs-list { display: flex; flex-direction: column; }
.rhc-doc-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; cursor: pointer; transition: background .12s;
    border-bottom: 1px solid var(--cl-border-light, #F4F1EC);
}
.rhc-doc-item:last-child { border-bottom: none; }
.rhc-doc-item:hover { background: var(--cl-bg, #FAFAF7); }
.rhc-doc-icon {
    width: 38px; height: 38px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; flex-shrink: 0;
}
.rhc-doc-icon-pdf   { background: #FECACA; color: #991B1B; }
.rhc-doc-icon-word  { background: #DBEAFE; color: #1E40AF; }
.rhc-doc-icon-img   { background: #D1FAE5; color: #065F46; }
.rhc-doc-icon-other { background: #E5E7EB; color: #374151; }
.rhc-doc-info { flex: 1; min-width: 0; }
.rhc-doc-name {
    font-size: .85rem; font-weight: 600; color: var(--cl-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rhc-doc-meta { font-size: .7rem; color: var(--cl-text-muted); margin-top: 2px; }
.rhc-doc-type-pill {
    font-size: .65rem; font-weight: 600; padding: 2px 8px; border-radius: 10px;
    background: var(--cl-bg, #F0EDE8); color: var(--cl-text-secondary);
    text-transform: uppercase; letter-spacing: .3px;
}
.rhc-doc-actions {
    display: flex; align-items: center; gap: 4px; color: var(--cl-text-muted);
}
.rhc-doc-actions i { font-size: .95rem; }

/* ── Lightbox with navigation ── */
.rhc-lightbox {
    position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.88);
    display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px);
    animation: rhcLbIn .2s ease;
}
@keyframes rhcLbIn { from { opacity: 0; } to { opacity: 1; } }
.rhc-lb-close {
    position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,.12); border: none; color: #fff; font-size: 1.1rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s; z-index: 10;
}
.rhc-lb-close:hover { background: rgba(255,255,255,.25); }
.rhc-lb-counter {
    position: absolute; top: 20px; left: 50%; transform: translateX(-50%);
    color: rgba(255,255,255,.6); font-size: .8rem; font-weight: 600; z-index: 10;
}
.rhc-lb-nav {
    position: absolute; top: 50%; transform: translateY(-50%); z-index: 10;
    width: 48px; height: 48px; border-radius: 50%;
    background: rgba(255,255,255,.1); border: none; color: #fff; font-size: 1.3rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all .15s; backdrop-filter: blur(4px);
}
.rhc-lb-nav:hover { background: rgba(255,255,255,.25); transform: translateY(-50%) scale(1.08); }
.rhc-lb-prev { left: 16px; }
.rhc-lb-next { right: 16px; }
.rhc-lb-track {
    display: flex; width: 100%; height: calc(100vh - 60px);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.rhc-lb-slide {
    flex: 0 0 100%; width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    padding: 20px 70px;
    box-sizing: border-box;
}
.rhc-lb-slide img { max-width: 100%; max-height: 80vh; object-fit: contain; border-radius: 8px; }
.rhc-lb-slide iframe { width: 85vw; max-width: 1000px; height: 80vh; border: none; border-radius: 8px; background: #fff; }
.rhc-lb-bar {
    position: absolute; bottom: 0; left: 0; right: 0;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 20px;
    background: linear-gradient(transparent, rgba(0,0,0,.6));
    z-index: 10;
}
.rhc-lb-name { color: rgba(255,255,255,.8); font-size: .82rem; display: flex; align-items: center; gap: 6px; }
.rhc-lb-dl {
    color: #fff; text-decoration: none; font-size: .82rem;
    background: rgba(255,255,255,.12); padding: 6px 14px; border-radius: 6px;
    display: flex; align-items: center; gap: 6px; transition: background .15s;
}
.rhc-lb-dl:hover { background: rgba(255,255,255,.25); color: #fff; }
/* Word preview inside lightbox */
.rhc-lb-word-wrap {
    width: 85vw; max-width: 900px; height: 80vh;
    background: #fff; border-radius: 10px; overflow: hidden;
    display: flex; flex-direction: column;
}
.rhc-lb-word-body {
    flex: 1; overflow-y: auto; padding: 0;
}
.rhc-lb-word-body .docx-wrapper,
.rhc-lb-word-body > div { background: var(--cl-bg, #F7F5F2) !important; padding: 20px !important; display: flex; flex-direction: column; align-items: center; gap: 24px; }
.rhc-lb-word-body .docx-wrapper > section,
.rhc-lb-word-body .docx-wrapper section.docx,
.rhc-lb-word-body section[style] {
    max-width: 800px; width: 100%; background: #fff !important;
    border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 40px !important;
}

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
            html += `<tr data-view="${c.id}" style="cursor:pointer">
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
        const row = e.target.closest('tr[data-view]');
        if (row) { openDetail(row.dataset.view); return; }
        const btn = e.target.closest('button[data-view]');
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
            if (c.motivation) html += `<div class="col-12">${field('Pourquoi ce poste ?', '<div class="rhc-detail-box">'+escapeHtml(c.motivation)+'</div>')}</div>`;
            if (c.experience) html += `<div class="col-12">${field('Valeur ajoutée à l\'équipe', '<div class="rhc-detail-box">'+escapeHtml(c.experience)+'</div>')}</div>`;

            if (c.documents && c.documents.length) {
                const typeLabels = { cv:'CV', lettre_motivation:'Lettre de motivation', diplome:'Diplôme', certificat:'Certificat', autre:'Autre' };
                const groups = {
                    pdf:   { title: 'Documents PDF',   icon: 'bi-file-earmark-pdf',   cls: 'rhc-doc-icon-pdf',   items: [] },
                    word:  { title: 'Documents Word',  icon: 'bi-file-earmark-word',  cls: 'rhc-doc-icon-word',  items: [] },
                    image: { title: 'Images',          icon: 'bi-file-earmark-image', cls: 'rhc-doc-icon-img',   items: [] },
                    other: { title: 'Autres fichiers', icon: 'bi-file-earmark',       cls: 'rhc-doc-icon-other', items: [] },
                };
                c.documents.forEach(d => {
                    const ext = (d.original_name || '').split('.').pop().toLowerCase();
                    let cat = 'other';
                    if (ext === 'pdf') cat = 'pdf';
                    else if (['doc','docx','odt','rtf'].includes(ext)) cat = 'word';
                    else if (['jpg','jpeg','png','gif','webp','bmp','heic'].includes(ext)) cat = 'image';
                    groups[cat].items.push({ ...d, ext });
                });

                html += '<div class="col-12"><div class="rhc-detail-label">Documents</div><div class="rhc-docs-wrap">';
                Object.values(groups).forEach(g => {
                    if (!g.items.length) return;
                    html += `<div class="rhc-docs-group">
                        <div class="rhc-docs-group-head">
                            <div class="rhc-docs-group-icon ${g.cls}"><i class="bi ${g.icon}"></i></div>
                            <div class="rhc-docs-group-title">${g.title}</div>
                            <div class="rhc-docs-group-count">${g.items.length}</div>
                        </div>
                        <div class="rhc-docs-list">`;
                    g.items.forEach(d => {
                        const sizeKb = d.size ? Math.round(d.size / 1024) : '?';
                        const url = '/spocspace/admin/api.php?action=admin_download_candidature_doc&id=' + encodeURIComponent(d.id);
                        const typeLabel = typeLabels[d.type_document] || d.type_document || '';
                        html += `<div class="rhc-doc-item" data-doc-url="${escapeHtml(url)}" data-doc-ext="${d.ext}" data-doc-name="${escapeHtml(d.original_name)}">
                            <div class="rhc-doc-icon ${g.cls}"><i class="bi ${g.icon}"></i></div>
                            <div class="rhc-doc-info">
                                <div class="rhc-doc-name" title="${escapeHtml(d.original_name)}">${escapeHtml(d.original_name)}</div>
                                <div class="rhc-doc-meta">${sizeKb} Ko · ${formatDate(d.created_at)}</div>
                            </div>
                            ${typeLabel ? `<span class="rhc-doc-type-pill">${escapeHtml(typeLabel)}</span>` : ''}
                            <div class="rhc-doc-actions"><i class="bi bi-eye"></i><i class="bi bi-download"></i></div>
                        </div>`;
                    });
                    html += '</div></div>';
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

            // Bind doc item clicks
            document.querySelectorAll('.rhc-doc-item').forEach(card => {
                card.addEventListener('click', () => {
                    const url = card.dataset.docUrl;
                    const ext = card.dataset.docExt;
                    const name = card.dataset.docName;
                    openDocLightbox(url, ext, name);
                });
            });
        });
    }

    let lbDocs = [];
    let lbIndex = 0;
    let lbEl = null;

    function openDocLightbox(url, ext, name) {
        // Collect all documents
        lbDocs = [...document.querySelectorAll('.rhc-doc-item')].map(el => ({
            url: el.dataset.docUrl,
            ext: el.dataset.docExt,
            name: el.dataset.docName,
        }));
        lbIndex = lbDocs.findIndex(d => d.url === url && d.name === name);
        if (lbIndex < 0) lbIndex = 0;

        buildLightbox();
    }

    function buildLightbox() {
        closeLb();

        const lb = document.createElement('div');
        lb.id = 'rhcLightbox';
        lb.className = 'rhc-lightbox';
        lbEl = lb;

        // Build all slides at once
        const slidesHtml = lbDocs.map((doc, i) => {
            const isImage = ['jpg','jpeg','png','gif','webp','bmp'].includes(doc.ext);
            const isPdf = doc.ext === 'pdf';
            const isWord = ['doc','docx','odt','rtf'].includes(doc.ext);

            let content;
            if (isImage) {
                content = `<img src="${escapeHtml(doc.url)}" alt="${escapeHtml(doc.name)}" draggable="false">`;
            } else if (isPdf) {
                content = `<iframe src="${escapeHtml(doc.url)}#view=FitH" sandbox="allow-same-origin allow-scripts"></iframe>`;
            } else if (isWord) {
                content = `<div class="rhc-lb-word-wrap"><div class="rhc-lb-word-body" data-word-idx="${i}">
                    <div style="text-align:center;padding:40px;color:#999"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
                </div></div>`;
            } else {
                content = `<div style="text-align:center;padding:60px;color:#fff">
                    <i class="bi bi-file-earmark" style="font-size:3rem;opacity:.4"></i>
                    <p style="margin-top:12px">Aperçu non disponible</p>
                    <a href="${escapeHtml(doc.url)}" download class="btn btn-sm btn-light"><i class="bi bi-download"></i> Télécharger</a>
                </div>`;
            }
            return `<div class="rhc-lb-slide" data-slide="${i}">${content}</div>`;
        }).join('');

        lb.innerHTML = `
            <button class="rhc-lb-close" title="Fermer (Esc)"><i class="bi bi-x-lg"></i></button>
            <div class="rhc-lb-counter" id="rhcLbCounter"></div>
            <button class="rhc-lb-nav rhc-lb-prev" id="rhcLbPrev"><i class="bi bi-chevron-left"></i></button>
            <button class="rhc-lb-nav rhc-lb-next" id="rhcLbNext"><i class="bi bi-chevron-right"></i></button>
            <div class="rhc-lb-track" id="rhcLbTrack">${slidesHtml}</div>
            <div class="rhc-lb-bar">
                <span class="rhc-lb-name" id="rhcLbName"></span>
                <a href="#" id="rhcLbDl" class="rhc-lb-dl"><i class="bi bi-download"></i> Télécharger</a>
            </div>
        `;

        document.body.appendChild(lb);

        // Load all word docs in background
        lbDocs.forEach((doc, i) => {
            if (['doc','docx','odt','rtf'].includes(doc.ext)) {
                loadWordSlide(doc.url, i);
            }
        });

        // Bind events (once)
        lb.querySelector('.rhc-lb-close').addEventListener('click', closeLb);
        document.getElementById('rhcLbPrev').addEventListener('click', (e) => { e.stopPropagation(); goSlide(lbIndex - 1); });
        document.getElementById('rhcLbNext').addEventListener('click', (e) => { e.stopPropagation(); goSlide(lbIndex + 1); });
        lb.addEventListener('click', e => {
            if (e.target === lb || e.target.id === 'rhcLbTrack') closeLb();
        });
        document.addEventListener('keydown', lbKeyHandler);

        // Show initial slide
        goSlide(lbIndex, true);
    }

    function goSlide(idx, instant) {
        if (idx < 0 || idx >= lbDocs.length) return;
        lbIndex = idx;
        const doc = lbDocs[idx];

        // Move track
        const track = document.getElementById('rhcLbTrack');
        if (track) {
            track.style.transition = instant ? 'none' : 'transform .3s cubic-bezier(.4,0,.2,1)';
            track.style.transform = `translateX(-${idx * 100}%)`;
        }

        // Update info
        const counter = document.getElementById('rhcLbCounter');
        if (counter) counter.textContent = `${idx + 1} / ${lbDocs.length}`;

        const nameEl = document.getElementById('rhcLbName');
        if (nameEl) nameEl.innerHTML = `<i class="bi bi-file-earmark"></i> ${escapeHtml(doc.name)}`;

        const dlEl = document.getElementById('rhcLbDl');
        if (dlEl) { dlEl.href = doc.url; dlEl.setAttribute('download', doc.name); }

        // Nav buttons visibility
        const prev = document.getElementById('rhcLbPrev');
        const next = document.getElementById('rhcLbNext');
        if (prev) prev.style.display = idx > 0 ? '' : 'none';
        if (next) next.style.display = idx < lbDocs.length - 1 ? '' : 'none';
    }

    function lbKeyHandler(e) {
        if (e.key === 'Escape') closeLb();
        else if (e.key === 'ArrowLeft') goSlide(lbIndex - 1);
        else if (e.key === 'ArrowRight') goSlide(lbIndex + 1);
    }

    function closeLb() {
        document.getElementById('rhcLightbox')?.remove();
        document.removeEventListener('keydown', lbKeyHandler);
        lbEl = null;
    }

    async function loadWordSlide(url, slideIdx) {
        const container = document.querySelector(`.rhc-lb-word-body[data-word-idx="${slideIdx}"]`);
        if (!container) return;
        try {
            if (!window.JSZip) {
                await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/spocspace/assets/js/vendor/jszip.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
            }
            if (!window.docx) {
                await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/spocspace/assets/js/vendor/docx-preview.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
            }
            const resp = await fetch(url);
            const blob = await resp.blob();
            container.innerHTML = '';
            await window.docx.renderAsync(blob, container, null, {
                className: 'docx-preview', inWrapper: true, ignoreWidth: false,
                ignoreHeight: false, ignoreFonts: false, breakPages: true, useBase64URL: true,
            });
        } catch (e) {
            console.error('docx-preview error:', e);
            container.innerHTML = `<div style="text-align:center;padding:40px;color:#dc3545"><i class="bi bi-exclamation-triangle"></i> Erreur — <a href="${escapeHtml(url)}" target="_blank">Télécharger</a></div>`;
        }
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
