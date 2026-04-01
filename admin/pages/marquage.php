<?php
$residents = Db::fetchAll("SELECT id, nom, prenom, chambre, etage FROM residents WHERE is_active = 1 ORDER BY nom, prenom");
?>
<style>
/* ── Stat cards ── */
.mrk-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
.mrk-stat { border-radius: 14px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; border: 1.5px solid var(--cl-border-light, #F0EDE8); }
.mrk-stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.mrk-stat-val { font-size: 1.4rem; font-weight: 700; line-height: 1; }
.mrk-stat-label { font-size: .72rem; color: var(--cl-text-muted); margin-top: 2px; }

/* ── Table ── */
.mrk-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light, #F0EDE8); }
.mrk-table { width: 100%; border-collapse: collapse; }
.mrk-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; background: var(--cl-bg); }
.mrk-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.mrk-table tr:hover td { background: rgba(25,25,24,.02); }

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

/* ── Filter tabs ── */
.mrk-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.mrk-tab { padding: 6px 16px; border-radius: 10px; font-size: .82rem; font-weight: 600; cursor: pointer; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: transparent; color: var(--cl-text-muted); transition: all .15s; }
.mrk-tab:hover { border-color: var(--cl-border-hover); }
.mrk-tab.active { background: var(--cl-surface); border-color: var(--cl-accent); color: var(--cl-text); }

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
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-tags"></i> Marquage Lingerie</h4>
  <button class="btn btn-primary btn-sm" id="mrkNewBtn"><i class="bi bi-plus-lg"></i> Nouveau marquage</button>
</div>

<!-- Stats -->
<div class="mrk-stats" id="mrkStats"></div>

<!-- Filter tabs -->
<div class="mrk-tabs" id="mrkTabs">
  <button class="mrk-tab active" data-filter="">Tous</button>
  <button class="mrk-tab" data-filter="en_cours"><i class="bi bi-hourglass-split"></i> En cours</button>
  <button class="mrk-tab" data-filter="marqué"><i class="bi bi-check-circle"></i> Marqués</button>
  <button class="mrk-tab" data-filter="terminé"><i class="bi bi-check-all"></i> Terminés</button>
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
        <div class="row mb-3">
          <div class="col-4">
            <label class="form-label small fw-bold">Quantité</label>
            <input type="number" class="form-control form-control-sm" id="mrkQty" min="1" value="1">
          </div>
          <div class="col-8">
            <label class="form-label small fw-bold">Description</label>
            <input type="text" class="form-control form-control-sm" id="mrkDesc" maxlength="500" placeholder="Ex: Pantalon bleu, chemise blanche...">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Photo</label>
          <input type="file" class="form-control form-control-sm" id="mrkPhoto" accept="image/*" capture="environment">
          <div class="form-text">Prenez une photo du vêtement</div>
          <div id="mrkPhotoPreview" class="mt-2 d-none">
            <img id="mrkPhotoImg" style="max-width:200px;border-radius:10px">
          </div>
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

    // ── Photo preview ──
    document.getElementById('mrkPhoto')?.addEventListener('change', function() {
        const f = this.files[0];
        const prev = document.getElementById('mrkPhotoPreview');
        const img = document.getElementById('mrkPhotoImg');
        if (f) {
            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; prev.classList.remove('d-none'); };
            reader.readAsDataURL(f);
        } else {
            prev.classList.add('d-none');
        }
    });

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
        if (!s) return;
        const data = [
            { label: 'En cours', val: s.en_cours || 0, bg: '#D4C4A8', color: '#6B5B3E', icon: 'hourglass-split' },
            { label: 'Marqués', val: s.marques || 0, bg: '#B8C9D4', color: '#3B4F6B', icon: 'check-circle' },
            { label: 'Terminés', val: s.termines || 0, bg: '#bcd2cb', color: '#2d4a43', icon: 'check-all' },
            { label: 'Résidents', val: s.residents_count || 0, bg: '#D0C4D8', color: '#5B4B6B', icon: 'people' },
            { label: 'Chambres', val: s.chambres_count || 0, bg: '#E2B8AE', color: '#7B3B2C', icon: 'door-open' },
        ];
        document.getElementById('mrkStats').innerHTML = data.map(d =>
            '<div class="mrk-stat"><div class="mrk-stat-icon" style="background:' + d.bg + ';color:' + d.color + '"><i class="bi bi-' + d.icon + '"></i></div>'
            + '<div><div class="mrk-stat-val">' + d.val + '</div><div class="mrk-stat-label">' + d.label + '</div></div></div>'
        ).join('');
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
            const photoUrl = m.photo_path ? '/zerdatime/admin/api.php?action=admin_serve_marquage_photo&id=' + encodeURIComponent(m.id) : '';
            const photoHtml = photoUrl
                ? '<img src="' + photoUrl + '" class="mrk-photo-thumb" data-lightbox="' + photoUrl + '">'
                : '<span class="text-muted small">—</span>';
            const dt = new Date(m.created_at).toLocaleDateString('fr-CH', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
            return '<tr>'
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
        const btn = e.target.closest('[data-status]');
        if (btn) {
            const r = await adminApiPost('admin_update_marquage_statut', { id: btn.dataset.status, statut: btn.dataset.to });
            if (r.success) { showToast('Statut mis à jour', 'success'); load(); }
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            if (!await adminConfirm({ title: 'Supprimer', text: 'Supprimer ce marquage ?', icon: 'bi-trash3', type: 'danger', okText: 'Supprimer' })) return;
            const r = await adminApiPost('admin_delete_marquage', { id: del.dataset.del });
            if (r.success) { showToast('Supprimé', 'success'); load(); }
            return;
        }
        const hist = e.target.closest('[data-history]');
        if (hist) {
            openHistory(hist.dataset.history, hist.dataset.resName);
            return;
        }
        const lb = e.target.closest('[data-lightbox]');
        if (lb) {
            openLightbox(lb.dataset.lightbox);
        }
    });

    // ── Filter tabs ──
    document.getElementById('mrkTabs')?.addEventListener('click', e => {
        const tab = e.target.closest('.mrk-tab');
        if (!tab) return;
        document.querySelectorAll('.mrk-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentFilter = tab.dataset.filter || '';
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
        document.getElementById('mrkPhoto').value = '';
        document.getElementById('mrkPhotoPreview').classList.add('d-none');
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

        // Step 2: upload photo if any
        const photoFile = document.getElementById('mrkPhoto').files[0];
        if (photoFile && r.id) {
            const fd = new FormData();
            fd.append('action', 'admin_upload_marquage_photo');
            fd.append('id', r.id);
            fd.append('photo', photoFile);
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
                const photoUrl = m.photo_path ? '/zerdatime/admin/api.php?action=admin_serve_marquage_photo&id=' + encodeURIComponent(m.id) : '';
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
                if (photoUrl) {
                    h += '<img src="' + photoUrl + '" class="mrk-tl-photo" data-lightbox="' + photoUrl + '">';
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
    load();
})();
</script>
