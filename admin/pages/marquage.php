<?php
$residents = Db::fetchAll("SELECT id, nom, prenom, chambre, etage FROM residents WHERE is_active = 1 ORDER BY nom, prenom");
?>
<style>
/* ── Stat cards as filters ── */
.mrk-stat-card { cursor: pointer; transition: all .2s; position: relative; }
.mrk-stat-card.active { box-shadow: 0 0 0 2px var(--cl-accent, #2d4a43); }
.mrk-stat-card.active::after {
    content: '\F26E'; font-family: 'bootstrap-icons'; position: absolute; top: 8px; right: 10px;
    font-size: .7rem; color: var(--cl-accent, #2d4a43); opacity: .7;
}

/* ── Table ── */
.mrk-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); }
.mrk-table { width: 100%; border-collapse: collapse; background: var(--cl-surface, #fff); }
.mrk-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border-light, #F0EDE8); text-align: left; background: var(--cl-bg, #F7F5F2); }
.mrk-table th:first-child { border-top-left-radius: 14px; }
.mrk-table th:last-child { border-top-right-radius: 14px; }
.mrk-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.mrk-table tr:last-child td { border-bottom: none; }
.mrk-table tbody tr { cursor: pointer; transition: background .12s; }
.mrk-table tbody tr:hover td { background: var(--cl-bg, #FAFAF7); }

/* ── Detail modal ── */
.mrk-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mrk-detail-label { font-size: .72rem; color: var(--cl-text-muted); text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
.mrk-detail-val { font-size: .92rem; font-weight: 500; }
.mrk-detail-photos { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.mrk-detail-photo {
    width: 120px; height: 120px; border-radius: 12px; overflow: hidden;
    border: 1.5px solid var(--cl-border-light, #F0EDE8); cursor: pointer; transition: transform .15s;
}
.mrk-detail-photo:hover { transform: scale(1.04); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.mrk-detail-photo img { width: 100%; height: 100%; object-fit: cover; }
.mrk-detail-desc { background: var(--cl-bg, #F7F5F2); border-radius: 10px; padding: 12px 14px; font-size: .88rem; margin-top: 12px; }

.mrk-badge { font-size: .72rem; padding: 3px 10px; border-radius: 8px; font-weight: 600; display: inline-block; }
.mrk-badge-en_cours  { background: #D4C4A8; color: #6B5B3E; }
.mrk-badge-marqué    { background: #B8C9D4; color: #3B4F6B; }
.mrk-badge-terminé   { background: #bcd2cb; color: #2d4a43; }

.mrk-action-badge { font-size: .72rem; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
.mrk-act-marquer   { background: #E2B8AE; color: #7B3B2C; }
.mrk-act-laver     { background: #B8C9D4; color: #3B4F6B; }
.mrk-act-repasser  { background: #D4C4A8; color: #6B5B3E; }
.mrk-act-reparer   { background: #D0C4D8; color: #5B4B6B; }
.mrk-act-autre     { background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); }

.mrk-photo-thumb { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; cursor: pointer; border: 1px solid var(--cl-border-light, #F0EDE8); }
.mrk-row-btn { background: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 6px; color: var(--cl-text-muted); font-size: .85rem; transition: all .12s; display: inline-flex; align-items: center; justify-content: center; }
.mrk-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.mrk-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }


/* ── History timeline ── */
.mrk-timeline { position: relative; padding-left: 24px; }
.mrk-timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: var(--cl-border-light, #F0EDE8); }
.mrk-tl-item { position: relative; margin-bottom: 16px; }
.mrk-tl-dot { position: absolute; left: -20px; top: 4px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; }
.mrk-tl-dot-en_cours  { background: #D4C4A8; }
.mrk-tl-dot-marqué    { background: #B8C9D4; }
.mrk-tl-dot-terminé   { background: #bcd2cb; }
.mrk-tl-content { background: var(--cl-bg); border-radius: 10px; padding: 12px 14px; }
.mrk-tl-photo { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; margin-top: 8px; cursor: pointer; }

/* ── Drop zone ── */
.mrk-drop-zone {
    border: 2px dashed var(--cl-border, #E0DDD8); border-radius: 14px; padding: 28px 16px;
    text-align: center; cursor: pointer; transition: all .25s;
    color: var(--cl-text-muted); background: var(--cl-bg, #FAFAF8);
    position: relative; overflow: hidden;
}
.mrk-drop-zone:hover, .mrk-drop-zone.dragover {
    border-color: #bcd2cb; background: rgba(188,210,203,.1);
}
.mrk-drop-zone .mrk-dz-icon { font-size: 2.2rem; opacity: .25; display: block; margin-bottom: 6px; color: #bcd2cb; transition: all .25s; }
.mrk-drop-zone p { margin-bottom: 2px; font-size: .88rem; transition: opacity .2s; }
.mrk-drop-zone small { font-size: .72rem; transition: opacity .2s; }
/* Hover: show big plus icon */
.mrk-drop-zone .mrk-dz-hover {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .2s; pointer-events: none;
}
.mrk-drop-zone .mrk-dz-hover i {
    font-size: 3rem; color: #bcd2cb; background: rgba(188,210,203,.15);
    width: 70px; height: 70px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; transition: transform .2s;
}
.mrk-drop-zone:hover .mrk-dz-hover { opacity: 1; }
.mrk-drop-zone:hover .mrk-dz-hover i { transform: scale(1.1); }
.mrk-drop-zone:hover .mrk-dz-icon,
.mrk-drop-zone:hover p,
.mrk-drop-zone:hover small { opacity: 0; }
/* Mobile: always show text, tap target */
@media (max-width: 576px) {
    .mrk-drop-zone { padding: 20px 12px; }
    .mrk-drop-zone .mrk-dz-hover { display: none; }
    .mrk-drop-zone:hover .mrk-dz-icon,
    .mrk-drop-zone:hover p,
    .mrk-drop-zone:hover small { opacity: 1; }
}

/* ── Photo grid ── */
.mrk-photo-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.mrk-photo-item {
    width: 80px; height: 80px; border-radius: 10px; overflow: visible;
    position: relative; transition: transform .15s;
}
.mrk-photo-item:hover { transform: scale(1.05); }
.mrk-photo-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 10px; border: 1.5px solid var(--cl-border-light, #F0EDE8); }
.mrk-photo-item .mrk-photo-del {
    position: absolute; top: -6px; right: -6px; width: 20px; height: 20px;
    border-radius: 50%; background: #E2B8AE; color: #7B3B2C; border: 2px solid #fff;
    font-size: .55rem; cursor: pointer; display: none; align-items: center; justify-content: center;
}
.mrk-photo-item:hover .mrk-photo-del { display: flex; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-tags"></i> Marquage Lingerie</h4>
  <button class="btn btn-primary btn-sm" id="mrkNewBtn"><i class="bi bi-plus-lg"></i> Nouveau marquage</button>
</div>

<!-- Stat cards = filters -->
<div class="row g-3 mb-4" id="mrkStats">
  <div class="col-6 col-lg"><div class="stat-card mrk-stat-card active" data-filter=""><div class="stat-icon bg-teal"><i class="bi bi-tags"></i></div><div><div class="stat-value" id="mrkStatTotal">—</div><div class="stat-label">Tous</div></div></div></div>
  <div class="col-6 col-lg"><div class="stat-card mrk-stat-card" data-filter="en_cours"><div class="stat-icon bg-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value" id="mrkStatEnCours">—</div><div class="stat-label">En cours</div></div></div></div>
  <div class="col-6 col-lg"><div class="stat-card mrk-stat-card" data-filter="marqué"><div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div><div><div class="stat-value" id="mrkStatMarques">—</div><div class="stat-label">Marqués</div></div></div></div>
  <div class="col-6 col-lg"><div class="stat-card mrk-stat-card" data-filter="terminé"><div class="stat-icon bg-teal"><i class="bi bi-check-all"></i></div><div><div class="stat-value" id="mrkStatTermines">—</div><div class="stat-label">Terminés</div></div></div></div>
  <div class="col-6 col-lg"><div class="stat-card mrk-stat-card" data-filter="_residents"><div class="stat-icon bg-purple"><i class="bi bi-people"></i></div><div><div class="stat-value" id="mrkStatResidents">—</div><div class="stat-label">Résidents</div></div></div></div>
</div>

<!-- Table -->
<div class="mrk-table-wrap">
  <table class="mrk-table">
    <thead><tr>
      <th>Photo</th><th>Résident</th><th>Chambre</th><th>Action</th><th>Qté</th><th>Description</th><th>Statut</th><th>Par</th><th>Date</th><th></th>
    </tr></thead>
    <tbody id="mrkBody"><tr><td colspan="10" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></td></tr></tbody>
  </table>
</div>

<!-- New marquage modal -->
<div class="modal fade" id="mrkNewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Nouveau marquage</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Résident *</label>
          <div class="zs-select" id="mrkResident" data-placeholder="Choisir un résident..."></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Action *</label>
          <select class="form-select form-select-sm" id="mrkAction">
            <option value="marquer">Marquer (puce Ubiquid)</option>
            <option value="laver">Laver</option>
            <option value="repasser">Repasser</option>
            <option value="reparer">Réparer</option>
            <option value="autre">Autre</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Quantité</label>
          <input type="number" class="form-control form-control-sm" id="mrkQty" min="1" value="1" style="max-width:100px">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Description</label>
          <textarea class="form-control form-control-sm" id="mrkDesc" rows="3" maxlength="500" placeholder="Ex: 2 pantalons bleus, 1 chemise blanche, chaussettes..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Photos</label>
          <div class="mrk-drop-zone" id="mrkDropZone">
            <input type="file" id="mrkPhotoInput" class="d-none" accept="image/*" multiple capture="environment">
            <i class="bi bi-image mrk-dz-icon"></i>
            <p><strong>Glissez vos photos ici</strong> ou cliquez pour parcourir</p>
            <small>JPG, PNG, WebP — max 10 Mo par photo</small>
            <div class="mrk-dz-hover"><i class="bi bi-plus-lg"></i></div>
          </div>
          <div class="mrk-photo-grid" id="mrkPhotoGrid"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary" id="mrkSaveBtn"><i class="bi bi-check-lg"></i> Valider</button>
      </div>
    </div>
  </div>
</div>

<!-- History modal -->
<div class="modal fade" id="mrkHistoryModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Historique marquage</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="mrkHistoryBody"></div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
    </div>
  </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="mrkDetailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mrkDetailTitle"><i class="bi bi-tags me-2"></i>Détail du marquage</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="mrkDetailBody"></div>
      <div class="modal-footer" id="mrkDetailFooter">
        <button class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Photo lightbox (reuse simple overlay) -->
<div id="mrkLightbox" class="position-fixed top-0 start-0 w-100 h-100" style="z-index:9999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;cursor:pointer;display:none">
  <img id="mrkLbImg" style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:10px">
</div>

<script<?= nonce() ?>>
(function(){
    const residents = <?= json_encode(array_values($residents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let currentFilter = '';
    let allMarquages = [];

    // ── Init resident select ──
    const resOpts = residents.map(r => ({
        value: r.id,
        label: r.nom + ' ' + r.prenom + (r.chambre ? ' — Ch.' + r.chambre : '')
    }));
    const resEl = document.getElementById('mrkResident');
    if (resEl) zerdaSelect.init(resEl, resOpts, { search: true });

    // ── Photo drop zone + multi-photo ──
    let pendingPhotos = []; // { file, dataUrl }

    const dropZone = document.getElementById('mrkDropZone');
    const photoInput = document.getElementById('mrkPhotoInput');
    const photoGrid = document.getElementById('mrkPhotoGrid');

    dropZone?.addEventListener('click', () => photoInput?.click());
    dropZone?.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone?.addEventListener('drop', e => {
        e.preventDefault(); dropZone.classList.remove('dragover');
        addPhotos(Array.from(e.dataTransfer.files));
    });
    photoInput?.addEventListener('change', () => {
        addPhotos(Array.from(photoInput.files));
        photoInput.value = '';
    });

    function addPhotos(files) {
        files.forEach(f => {
            if (!f.type.startsWith('image/')) return;
            if (f.size > 10 * 1024 * 1024) { showToast('Photo trop volumineuse (max 10 Mo)', 'error'); return; }
            const reader = new FileReader();
            reader.onload = e => {
                pendingPhotos.push({ file: f, dataUrl: e.target.result });
                renderPhotoGrid();
            };
            reader.readAsDataURL(f);
        });
    }

    function renderPhotoGrid() {
        photoGrid.innerHTML = pendingPhotos.map((p, i) =>
            '<div class="mrk-photo-item">'
            + '<img src="' + p.dataUrl + '">'
            + '<div class="mrk-photo-del" data-rm="' + i + '"><i class="bi bi-x"></i></div>'
            + '</div>'
        ).join('');
        photoGrid.querySelectorAll('[data-rm]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                pendingPhotos.splice(parseInt(btn.dataset.rm), 1);
                renderPhotoGrid();
            });
        });
    }

    // ── Load marquages ──
    async function load() {
        const q = document.getElementById('topbarSearchInput')?.value?.trim() || '';
        try {
            const r = await adminApiPost('admin_get_marquages', { statut: currentFilter, search: q });
            if (!r.success) { renderTable([]); renderStats(null); return; }
            allMarquages = r.marquages || [];
            renderStats(r.stats);
            renderTable(allMarquages);
        } catch(e) {
            console.error('load marquages error', e);
            renderTable([]);
            renderStats(null);
        }
    }

    function renderStats(s) {
        if (!s) s = { total:0, en_cours:0, marques:0, termines:0, residents_count:0 };
        document.getElementById('mrkStatTotal').textContent = s.total || 0;
        document.getElementById('mrkStatEnCours').textContent = s.en_cours || 0;
        document.getElementById('mrkStatMarques').textContent = s.marques || 0;
        document.getElementById('mrkStatTermines').textContent = s.termines || 0;
        document.getElementById('mrkStatResidents').textContent = s.residents_count || 0;
    }

    const actionLabels = { marquer: 'Marquer', laver: 'Laver', repasser: 'Repasser', reparer: 'Réparer', autre: 'Autre' };
    const statutLabels = { en_cours: 'En cours', 'marqué': 'Marqué', 'terminé': 'Terminé' };

    function renderTable(rows) {
        const tbody = document.getElementById('mrkBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-tags" style="font-size:1.5rem;opacity:.2"></i><br>Aucun marquage</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(m => {
            const photos = m.photo_path ? m.photo_path.split(',') : [];
            let photoHtml = '';
            if (photos.length) {
                photoHtml = photos.map(f => {
                    const u = '/zerdatime/admin/api.php?action=admin_serve_marquage_photo&file=' + encodeURIComponent(f);
                    return '<img src="' + u + '" class="mrk-photo-thumb" data-lightbox="' + u + '" style="margin-right:2px">';
                }).join('');
            } else {
                photoHtml = '<span class="text-muted small">—</span>';
            }
            const dt = new Date(m.created_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
            return '<tr data-id="' + m.id + '">'
                + '<td>' + photoHtml + '</td>'
                + '<td><strong>' + escapeHtml(m.resident_nom) + '</strong> ' + escapeHtml(m.resident_prenom) + '</td>'
                + '<td>' + escapeHtml(m.chambre || '—') + '</td>'
                + '<td><span class="mrk-action-badge mrk-act-' + m.action + '">' + (actionLabels[m.action]||m.action) + '</span></td>'
                + '<td>' + m.quantite + '</td>'
                + '<td class="text-muted small" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(m.description || '—') + '</td>'
                + '<td><span class="mrk-badge mrk-badge-' + m.statut + '">' + (statutLabels[m.statut]||m.statut) + '</span></td>'
                + '<td class="small">' + escapeHtml((m.user_prenom||'') + ' ' + (m.user_nom||'').charAt(0) + '.') + '</td>'
                + '<td class="text-muted small">' + dt + '</td>'
                + '<td>'
                + statusButtons(m)
                + '<button class="mrk-row-btn" title="Historique résident" data-history="' + m.resident_id + '" data-res-name="' + escapeHtml(m.resident_nom + ' ' + m.resident_prenom) + '"><i class="bi bi-clock-history"></i></button>'
                + '<button class="mrk-row-btn danger" title="Supprimer" data-del="' + m.id + '"><i class="bi bi-trash3"></i></button>'
                + '</td></tr>';
        }).join('');
    }

    function statusButtons(m) {
        if (m.statut === 'en_cours') {
            return '<button class="mrk-row-btn" title="Marquer comme marqué" data-status="' + m.id + '" data-to="marqué" style="color:#3B4F6B"><i class="bi bi-check-circle"></i></button>';
        }
        if (m.statut === 'marqué') {
            return '<button class="mrk-row-btn" title="Marquer comme terminé" data-status="' + m.id + '" data-to="terminé" style="color:#2d4a43"><i class="bi bi-check-all"></i></button>';
        }
        return '';
    }

    // ── Table events ──
    document.getElementById('mrkBody')?.addEventListener('click', async e => {
        // Buttons first (stop propagation to row)
        const btn = e.target.closest('[data-status]');
        if (btn) {
            e.stopPropagation();
            const r = await adminApiPost('admin_update_marquage_statut', { id: btn.dataset.status, statut: btn.dataset.to });
            if (r.success) { showToast('Statut mis à jour', 'success'); load(); }
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            e.stopPropagation();
            if (!await adminConfirm({ title: 'Supprimer', text: 'Supprimer ce marquage ?', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer' })) return;
            const r = await adminApiPost('admin_delete_marquage', { id: del.dataset.del });
            if (r.success) { showToast('Supprimé', 'success'); load(); }
            return;
        }
        const hist = e.target.closest('[data-history]');
        if (hist) {
            e.stopPropagation();
            openHistory(hist.dataset.history, hist.dataset.resName);
            return;
        }
        const lb = e.target.closest('[data-lightbox]');
        if (lb) {
            openLightbox(lb.dataset.lightbox);
            return;
        }
        // Row click → detail modal
        const row = e.target.closest('tr[data-id]');
        if (row) {
            const m = allMarquages.find(x => x.id === row.dataset.id);
            if (m) openDetail(m);
        }
    });

    // ── Detail modal ──
    function openDetail(m) {
        const photos = m.photo_path ? m.photo_path.split(',') : [];
        const dt = new Date(m.created_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
        const completedDt = m.completed_at ? new Date(m.completed_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : null;

        document.getElementById('mrkDetailTitle').innerHTML = '<i class="bi bi-tags me-2"></i>' + escapeHtml(m.resident_nom + ' ' + m.resident_prenom) + ' — Ch.' + escapeHtml(m.chambre || '?');

        let h = '<div class="mrk-detail-grid">'
            + '<div><div class="mrk-detail-label">Action</div><div class="mrk-detail-val"><span class="mrk-action-badge mrk-act-' + m.action + '">' + (actionLabels[m.action]||m.action) + '</span></div></div>'
            + '<div><div class="mrk-detail-label">Statut</div><div class="mrk-detail-val"><span class="mrk-badge mrk-badge-' + m.statut + '">' + (statutLabels[m.statut]||m.statut) + '</span></div></div>'
            + '<div><div class="mrk-detail-label">Quantité</div><div class="mrk-detail-val">' + m.quantite + '</div></div>'
            + '<div><div class="mrk-detail-label">Date</div><div class="mrk-detail-val">' + dt + '</div></div>'
            + '<div><div class="mrk-detail-label">Par</div><div class="mrk-detail-val">' + escapeHtml((m.user_prenom||'') + ' ' + (m.user_nom||'')) + '</div></div>';
        if (completedDt) {
            h += '<div><div class="mrk-detail-label">Complété</div><div class="mrk-detail-val">' + escapeHtml((m.completed_prenom||'') + ' ' + (m.completed_nom||'')) + '<br><span class="text-muted small">' + completedDt + '</span></div></div>';
        }
        h += '</div>';

        if (m.description) {
            h += '<div class="mrk-detail-desc">' + escapeHtml(m.description) + '</div>';
        }

        if (photos.length) {
            h += '<div class="mrk-detail-label mt-3">Photos (' + photos.length + ')</div>';
            h += '<div class="mrk-detail-photos">';
            photos.forEach(f => {
                const u = '/zerdatime/admin/api.php?action=admin_serve_marquage_photo&file=' + encodeURIComponent(f);
                h += '<div class="mrk-detail-photo" data-lb-url="' + u + '"><img src="' + u + '"></div>';
            });
            h += '</div>';
        }

        document.getElementById('mrkDetailBody').innerHTML = h;

        // Footer with action buttons
        let footer = '';
        if (m.statut === 'en_cours') {
            footer += '<button class="btn btn-primary btn-sm" id="mrkDetailAction" data-id="' + m.id + '" data-to="marqué"><i class="bi bi-check-circle"></i> Marquer comme marqué</button>';
        } else if (m.statut === 'marqué') {
            footer += '<button class="btn btn-primary btn-sm" id="mrkDetailAction" data-id="' + m.id + '" data-to="terminé"><i class="bi bi-check-all"></i> Marquer comme terminé</button>';
        }
        footer += '<button class="btn btn-light btn-sm" data-bs-dismiss="modal">Fermer</button>';
        document.getElementById('mrkDetailFooter').innerHTML = footer;

        // Bind action button
        document.getElementById('mrkDetailAction')?.addEventListener('click', async function() {
            const r = await adminApiPost('admin_update_marquage_statut', { id: this.dataset.id, statut: this.dataset.to });
            if (r.success) { showToast('Statut mis à jour', 'success'); bootstrap.Modal.getInstance(document.getElementById('mrkDetailModal'))?.hide(); load(); }
        });

        // Bind photo clicks in detail
        document.querySelectorAll('#mrkDetailBody [data-lb-url]').forEach(el => {
            el.addEventListener('click', () => openLightbox(el.dataset.lbUrl));
        });

        new bootstrap.Modal(document.getElementById('mrkDetailModal')).show();
    }

    // ── Stat card filters ──
    document.getElementById('mrkStats')?.addEventListener('click', e => {
        const card = e.target.closest('.mrk-stat-card');
        if (!card) return;
        document.querySelectorAll('.mrk-stat-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        const f = card.dataset.filter || '';
        currentFilter = f === '_residents' ? '' : f; // _residents just highlights, shows all
        load();
    });

    // ── Topbar search ──
    let searchTO;
    document.getElementById('topbarSearchInput')?.addEventListener('input', () => { clearTimeout(searchTO); searchTO = setTimeout(load, 300); });

    // ── New marquage ──
    document.getElementById('mrkNewBtn')?.addEventListener('click', () => {
        document.getElementById('mrkAction').value = 'marquer';
        document.getElementById('mrkQty').value = 1;
        document.getElementById('mrkDesc').value = '';
        pendingPhotos = [];
        renderPhotoGrid();
        zerdaSelect.setValue('#mrkResident', '');
        new bootstrap.Modal(document.getElementById('mrkNewModal')).show();
    });

    document.getElementById('mrkSaveBtn')?.addEventListener('click', async () => {
        const residentId = zerdaSelect.getValue('#mrkResident');
        if (!residentId) { showToast('Sélectionnez un résident', 'error'); return; }

        const btn = document.getElementById('mrkSaveBtn');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        // Step 1: create marquage
        const r = await adminApiPost('admin_create_marquage', {
            resident_id: residentId,
            action_type: document.getElementById('mrkAction').value,
            quantite: document.getElementById('mrkQty').value,
            description: document.getElementById('mrkDesc').value.trim()
        });

        if (!r.success) { showToast(r.message || 'Erreur', 'error'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Valider'; return; }

        // Step 2: upload photos
        for (const p of pendingPhotos) {
            const fd = new FormData();
            fd.append('action', 'admin_upload_marquage_photo');
            fd.append('id', r.id);
            fd.append('photo', p.file);
            try {
                const res = await fetch('/zerdatime/admin/api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': window.__ZT_ADMIN__?.csrfToken || '' },
                    body: fd
                });
                const j = await res.json();
                if (j.csrf) window.__ZT_ADMIN__.csrfToken = j.csrf;
            } catch(e) { console.warn('Photo upload failed', e); }
        }

        showToast('Marquage créé', 'success');
        bootstrap.Modal.getInstance(document.getElementById('mrkNewModal'))?.hide();
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Valider';
        load();
    });

    // ── History modal ──
    async function openHistory(residentId, resName) {
        const body = document.getElementById('mrkHistoryBody');
        body.innerHTML = '<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        document.querySelector('#mrkHistoryModal .modal-title').innerHTML = '<i class="bi bi-clock-history me-2"></i>Historique — ' + escapeHtml(resName);
        new bootstrap.Modal(document.getElementById('mrkHistoryModal')).show();

        const r = await adminApiPost('admin_get_marquage_history', { resident_id: residentId });
        if (!r.success) { body.innerHTML = '<p class="text-danger">Erreur</p>'; return; }

        const stats = r.stats || {};
        let h = '<div class="d-flex gap-3 mb-3 flex-wrap">'
            + '<div class="mrk-stat" style="flex:1;min-width:120px"><div class="mrk-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-hourglass-split"></i></div><div><div class="mrk-stat-val">' + (stats.en_cours||0) + '</div><div class="mrk-stat-label">En cours</div></div></div>'
            + '<div class="mrk-stat" style="flex:1;min-width:120px"><div class="mrk-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-check-circle"></i></div><div><div class="mrk-stat-val">' + (stats.marques||0) + '</div><div class="mrk-stat-label">Marqués</div></div></div>'
            + '<div class="mrk-stat" style="flex:1;min-width:120px"><div class="mrk-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check-all"></i></div><div><div class="mrk-stat-val">' + (stats.termines||0) + '</div><div class="mrk-stat-label">Terminés</div></div></div>'
            + '</div>';

        const items = r.history || [];
        if (!items.length) {
            h += '<p class="text-center text-muted py-3">Aucun historique</p>';
        } else {
            h += '<div class="mrk-timeline">';
            items.forEach(m => {
                const dt = new Date(m.created_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
                h += '<div class="mrk-tl-item">'
                    + '<div class="mrk-tl-dot mrk-tl-dot-' + m.statut + '"></div>'
                    + '<div class="mrk-tl-content">'
                    + '<div class="d-flex align-items-center gap-2 flex-wrap">'
                    + '<span class="mrk-action-badge mrk-act-' + m.action + '">' + (actionLabels[m.action]||m.action) + '</span>'
                    + '<span class="mrk-badge mrk-badge-' + m.statut + '">' + (statutLabels[m.statut]||m.statut) + '</span>'
                    + '<span class="small text-muted ms-auto">' + dt + '</span></div>'
                    + '<div class="small mt-1">' + (m.description ? escapeHtml(m.description) : '<span class="text-muted">—</span>') + '</div>'
                    + '<div class="small text-muted">Qté: ' + m.quantite + ' — Par ' + escapeHtml((m.user_prenom||'') + ' ' + (m.user_nom||'')) + '</div>';
                if (m.completed_by) {
                    h += '<div class="small text-muted">Complété par ' + escapeHtml((m.completed_prenom||'') + ' ' + (m.completed_nom||'')) + '</div>';
                }
                const histPhotos = m.photo_path ? m.photo_path.split(',') : [];
                if (histPhotos.length) {
                    h += '<div class="d-flex gap-2 flex-wrap mt-1">';
                    histPhotos.forEach(f => {
                        const pu = '/zerdatime/admin/api.php?action=admin_serve_marquage_photo&file=' + encodeURIComponent(f);
                        h += '<img src="' + pu + '" class="mrk-tl-photo" data-lightbox="' + pu + '">';
                    });
                    h += '</div>';
                }
                h += '</div></div>';
            });
            h += '</div>';
        }

        body.innerHTML = h;

        // Lightbox on history photos
        body.querySelectorAll('[data-lightbox]').forEach(img => {
            img.addEventListener('click', () => openLightbox(img.dataset.lightbox));
        });
    }

    // ── Lightbox ──
    const lbEl = document.getElementById('mrkLightbox');
    const lbImg = document.getElementById('mrkLbImg');
    function openLightbox(url) {
        lbImg.src = url;
        lbEl.style.display = 'flex';
    }
    function closeLightbox() { lbEl.style.display = 'none'; }
    lbEl?.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && lbEl.style.display === 'flex') closeLightbox(); });

    // ── Init ──
    load().catch(e => { console.error('marquage init error', e); document.getElementById('mrkBody').innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Erreur: ' + e.message + '</td></tr>'; });
})();
</script>
