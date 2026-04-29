<?php
// ─── Données serveur (filtre par défaut : en_attente) ─────────────────────
$initAbsences = Db::fetchAll(
    "SELECT a.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
            m.nom AS module_nom,
            ur.prenom AS rempl_prenom, ur.nom AS rempl_nom
     FROM absences a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     LEFT JOIN users ur ON ur.id = a.remplacement_user_id
     WHERE a.statut = 'en_attente'
     ORDER BY a.created_at DESC"
);
?>
<style>
.btn-desir-valider {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-valider:hover { background: #bcd2cb; color: #2d4a43; border-color: #bcd2cb; }
.btn-desir-refuser {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-desir-refuser:hover { background: #E2B8AE; color: #7B3B2C; border-color: #E2B8AE; }

/* Filters */
.abs-filter-select { width: auto; }

/* Modal */
.abs-modal-dialog { max-width: 540px; }
.abs-modal-close { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--cl-border, #e5e7eb); }
.abs-modal-close i { font-size: .85rem; }

/* Table rows */
.abs-row-clickable { cursor: pointer; }

/* Status badges */
.badge-ss-valid { background: #bcd2cb; color: #2d4a43; }
.badge-ss-refuse { background: #E2B8AE; color: #7B3B2C; }
.badge-ss-attente { background: #D4C4A8; color: #6B5B3E; }

/* Type badges */
.badge-ss-vacances { background: #B8C9D4; color: #3B4F6B; }
.badge-ss-maladie, .badge-ss-accident { background: #E2B8AE; color: #7B3B2C; }
.badge-ss-conge_special { background: #D0C4D8; color: #5B4B6B; }
.badge-ss-formation { background: #D4C4A8; color: #6B5B3E; }

/* Avatar */
.abs-avatar { border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.abs-avatar-initials { border-radius: 50%; background: #B8C9D4; color: #3B4F6B; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.abs-avatar-sm { width: 32px; height: 32px; font-size: .7rem; }
.abs-avatar-md { width: 44px; height: 44px; font-size: .85rem; }

/* Check icon */
.abs-icon-valid { color: #2d4a43; }
.abs-icon-pdf { color: #7B3B2C; }
.abs-icon-external { font-size: .7rem; opacity: .5; }

/* Upload label */
.abs-upload-label { border-radius: 8px; cursor: pointer; }
.abs-upload-input { display: none; }

/* Delete justificatif */
.btn-delete-justif { border: 1px solid var(--cl-border); color: #7B3B2C; border-radius: 8px; font-size: .8rem; transition: all .2s; }
.btn-delete-justif:hover { background: #7B3B2C; color: #fff; }

/* Empty justificatif placeholder */
.abs-no-justif { border: 1px dashed var(--cl-border); border-radius: 10px; }
.abs-no-justif i { font-size: 1.5rem; }

/* Lightbox toolbar separator */
.ss-lb-sep { width: 1px; height: 24px; background: rgba(255,255,255,.25); margin: 0 4px; }

/* Lightbox fallback file view */
.ss-lb-fallback { text-align: center; color: #fff; }
.ss-lb-fallback i { font-size: 5rem; }
.ss-lb-fallback a { color: #fff; text-decoration: underline; }

/* Detail modal */
.abs-detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--cl-border, #e5e7eb); font-size: .88rem; }
.abs-detail-row:last-child { border-bottom: none; }
.abs-detail-label { color: var(--cl-text-secondary, #6B6B69); font-weight: 500; }
.abs-detail-value { font-weight: 600; color: var(--cl-text, #1A1A18); }

/* File preview */
.abs-file-preview {
  border: 1px solid var(--cl-border, #e5e7eb); border-radius: 10px; overflow: hidden;
  cursor: pointer; transition: all .2s; background: #f9f7f4; position: relative;
}
.abs-file-preview:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-1px); }
.abs-file-preview img { width: 100%; max-height: 220px; object-fit: contain; display: block; }
.abs-file-preview .abs-pdf-preview { width: 100%; height: 200px; border: none; }
.abs-file-icon { display: flex; align-items: center; justify-content: center; height: 120px; font-size: 3rem; color: var(--cl-text-secondary); }
.abs-file-name { padding: 8px 12px; font-size: .78rem; color: var(--cl-text-secondary); border-top: 1px solid var(--cl-border, #e5e7eb); display: flex; align-items: center; gap: 6px; }
.abs-file-name i { font-size: .9rem; }

/* Lightbox */
.ss-lightbox { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; display: flex; align-items: center; justify-content: center; animation: ztLbFadeIn .3s ease; }
.ss-lightbox-hidden { display: none !important; }
.ss-lightbox-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.8); backdrop-filter: blur(10px); }
.ss-lightbox-content { position: relative; width: 100%; height: 100%; overflow: hidden; }
.ss-lightbox-stage { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; cursor: default; user-select: none; }
.ss-lightbox-stage img { max-width: 90vw; max-height: calc(100vh - 120px); width: auto; height: auto; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); will-change: transform; }
.ss-lightbox-stage iframe { width: 85vw; height: calc(100vh - 120px); border: none; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.5); background: #fff; }
.ss-lightbox-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,.1); border: none; color: #fff; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .3s; backdrop-filter: blur(10px); z-index: 10; font-size: 24px; }
.ss-lightbox-close:hover { background: rgba(255,255,255,.2); transform: scale(1.1); }
.ss-lightbox-title { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.15); color: #fff; padding: 10px 24px; border-radius: 24px; font-size: 15px; font-weight: 600; backdrop-filter: blur(10px); z-index: 11; }
.ss-lightbox-toolbar { position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 4px; background: rgba(30,30,30,.85); backdrop-filter: blur(12px); border-radius: 999px; padding: 6px 16px; z-index: 12; }
.ss-lb-btn { width: 40px; height: 40px; border: none; background: transparent; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; transition: background .2s; }
.ss-lb-btn:hover { background: rgba(255,255,255,.15); }
.ss-lb-zoom { color: #fff; font-size: 14px; font-weight: 600; min-width: 48px; text-align: center; user-select: none; }
.ss-lightbox-stage.ss-zoomed { cursor: grab; }
.ss-lightbox-stage.ss-dragging { cursor: grabbing !important; }
@keyframes ztLbFadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2">
    <div class="zs-select abs-filter-select" id="absStatutFilter" data-placeholder="Tous les statuts"></div>
    <div class="zs-select abs-filter-select" id="absTypeFilter" data-placeholder="Tous les types"></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collaborateur</th>
          <th>Module</th>
          <th>Type</th>
          <th>Du</th>
          <th>Au</th>
          <th>Justifi&eacute;</th>
          <th>Remplacement</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="absencesTableBody">
        <?php if (empty($initAbsences)): ?>
        <tr><td colspan="9" class="text-center py-4 text-muted">Aucune absence en attente</td></tr>
        <?php else: ?>
        <?php foreach ($initAbsences as $idx => $a):
            $sCls = ['valide'=>'badge-ss-valid','refuse'=>'badge-ss-refuse','en_attente'=>'badge-ss-attente'][$a['statut']] ?? 'badge-ss-attente';
            $tCls = ['vacances'=>'badge-ss-vacances','maladie'=>'badge-ss-maladie','accident'=>'badge-ss-accident','conge_special'=>'badge-ss-conge_special','formation'=>'badge-ss-formation'][$a['type']] ?? 'badge-ss-attente';
            $sLbl = ['valide'=>'Validé','refuse'=>'Refusé','en_attente'=>'En attente'][$a['statut']] ?? h($a['statut']);
            $initials = mb_strtoupper(mb_substr($a['prenom']??'',0,1).mb_substr($a['nom']??'',0,1));
            $rempl = $a['remplacement_type']
                ? ($a['remplacement_type']==='collegue' ? h(($a['rempl_prenom']??'').' '.($a['rempl_nom']??'')) : h($a['remplacement_type']))
                : '—';
        ?>
        <tr data-abs-idx="<?= $idx ?>" class="abs-row-clickable">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($a['photo'])): ?>
                <img src="<?= h($a['photo']) ?>" class="abs-avatar abs-avatar-sm">
              <?php else: ?>
                <div class="abs-avatar-initials abs-avatar-sm"><?= h($initials) ?></div>
              <?php endif; ?>
              <div>
                <strong><?= h($a['prenom'].' '.$a['nom']) ?></strong><br>
                <small class="text-muted"><?= h($a['fonction_code']??'') ?></small>
              </div>
            </div>
          </td>
          <td><small><?= h($a['module_nom']??'—') ?></small></td>
          <td><span class="badge <?= $tCls ?>"><?= h($a['type']) ?></span></td>
          <td><?= h($a['date_debut']) ?></td>
          <td><?= h($a['date_fin']) ?></td>
          <td><?= $a['justifie'] ? '<i class="bi bi-check abs-icon-valid"></i>' : '<i class="bi bi-x text-muted"></i>' ?></td>
          <td><small><?= $rempl ?></small></td>
          <td><span class="badge <?= $sCls ?>"><?= h($sLbl) ?></span></td>
          <td>
            <?php if ($a['statut'] === 'en_attente'): ?>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-sm btn-desir-valider me-1" data-valid-abs="<?= h($a['id']) ?>" title="Valider"><i class="bi bi-check-lg"></i></button>
                <button class="btn btn-sm btn-desir-refuser" data-refuse-abs="<?= h($a['id']) ?>" title="Refuser"><i class="bi bi-x-lg"></i></button>
              </div>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal detail absence -->
<div class="modal fade" id="absDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered abs-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3" id="absDetailHeader"></div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center abs-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="absDetailBody"></div>
      <div class="modal-footer d-flex" id="absDetailFooter">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div id="ztLightbox" class="ss-lightbox ss-lightbox-hidden">
  <div class="ss-lightbox-overlay"></div>
  <div class="ss-lightbox-content">
    <button class="ss-lightbox-close" type="button"><i class="bi bi-x-lg"></i></button>
    <div class="ss-lightbox-title" id="ztLbTitle"></div>
    <div class="ss-lightbox-stage" id="ztLbStage"></div>
    <div class="ss-lightbox-toolbar ss-lightbox-hidden" id="ztLbToolbar">
      <button type="button" class="ss-lb-btn" id="ztLbZoomOut"><i class="bi bi-zoom-out"></i></button>
      <span class="ss-lb-zoom" id="ztLbZoomLevel">100%</span>
      <button type="button" class="ss-lb-btn" id="ztLbZoomIn"><i class="bi bi-zoom-in"></i></button>
      <span class="ss-lb-sep"></span>
      <button type="button" class="ss-lb-btn" id="ztLbReset"><i class="bi bi-arrows-angle-contract"></i></button>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
// Données initiales injectées côté serveur (filtre: en_attente)
let absData = <?= json_encode(array_values($initAbsences), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
let absDetailModalInstance = null;

const statusClasses = { valide: 'badge-ss-valid', refuse: 'badge-ss-refuse', en_attente: 'badge-ss-attente' };
const statusLabels = { valide: 'Valid\u00e9', refuse: 'Refus\u00e9', en_attente: 'En attente' };
const typeClasses = { vacances: 'badge-ss-vacances', maladie: 'badge-ss-maladie', accident: 'badge-ss-accident', conge_special: 'badge-ss-conge_special', formation: 'badge-ss-formation' };

function makeBadge(text, cls) { return `<span class="badge ${cls}">${escapeHtml(text)}</span>`; }

function initAbsencesPage() {
    absDetailModalInstance = new bootstrap.Modal(document.getElementById('absDetailModal'));

    zerdaSelect.init('#absStatutFilter', [
        { value: '', label: 'Tous les statuts' },
        { value: 'en_attente', label: 'En attente' },
        { value: 'valide', label: 'Valid\u00e9' },
        { value: 'refuse', label: 'Refus\u00e9' }
    ], { onSelect: () => loadAbsences(), value: 'en_attente' });

    zerdaSelect.init('#absTypeFilter', [
        { value: '', label: 'Tous les types' },
        { value: 'vacances', label: 'Vacances' },
        { value: 'maladie', label: 'Maladie' },
        { value: 'accident', label: 'Accident' },
        { value: 'conge_special', label: 'Cong\u00e9 sp\u00e9cial' },
        { value: 'formation', label: 'Formation' }
    ], { onSelect: () => loadAbsences(), value: '' });

    // Event delegation for table actions
    document.getElementById('absencesTableBody').addEventListener('click', (e) => {
        const vBtn = e.target.closest('[data-valid-abs]');
        if (vBtn) { e.stopPropagation(); validAbsence(vBtn.dataset.validAbs, 'valide'); return; }
        const rBtn = e.target.closest('[data-refuse-abs]');
        if (rBtn) { e.stopPropagation(); validAbsence(rBtn.dataset.refuseAbs, 'refuse'); return; }
        const tr = e.target.closest('tr[data-abs-idx]');
        if (tr) openAbsDetail(parseInt(tr.dataset.absIdx));
    });
    // Initial render already done by PHP — no AJAX needed
}

function makeAvatar(a, size = 'sm') {
    const cls = size === 'md' ? 'abs-avatar-md' : 'abs-avatar-sm';
    const initials = ((a.prenom?.[0] || '') + (a.nom?.[0] || '')).toUpperCase();
    return a.photo
        ? `<img src="${escapeHtml(a.photo)}" class="abs-avatar ${cls}">`
        : `<div class="abs-avatar-initials ${cls}">${initials}</div>`;
}

async function loadAbsences() {
    const statut = zerdaSelect.getValue('#absStatutFilter') || '';
    const type = zerdaSelect.getValue('#absTypeFilter') || '';

    const res = await adminApiPost('admin_get_absences', { statut, type });
    const tbody = document.getElementById('absencesTableBody');
    absData = res.absences || [];

    if (!absData.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Aucune absence</td></tr>';
        return;
    }

    tbody.innerHTML = absData.map((a, idx) => {
        const sCls = statusClasses[a.statut] || statusClasses.en_attente;
        const tCls = typeClasses[a.type] || 'badge-ss-attente';
        const rempl = a.remplacement_type
            ? (a.remplacement_type === 'collegue' ? `${escapeHtml(a.rempl_prenom || '')} ${escapeHtml(a.rempl_nom || '')}` : a.remplacement_type)
            : '\u2014';

        return `<tr data-abs-idx="${idx}" class="abs-row-clickable">
          <td><div class="d-flex align-items-center gap-2">${makeAvatar(a)}<div><strong>${escapeHtml(a.prenom)} ${escapeHtml(a.nom)}</strong><br><small class="text-muted">${escapeHtml(a.fonction_code || '')}</small></div></div></td>
          <td><small>${escapeHtml(a.module_nom || '\u2014')}</small></td>
          <td>${makeBadge(a.type, tCls)}</td>
          <td>${escapeHtml(a.date_debut)}</td>
          <td>${escapeHtml(a.date_fin)}</td>
          <td>${a.justifie ? '<i class="bi bi-check abs-icon-valid"></i>' : '<i class="bi bi-x text-muted"></i>'}</td>
          <td><small>${rempl}</small></td>
          <td>${makeBadge(a.statut, sCls)}</td>
          <td>
            ${a.statut === 'en_attente' ? `
              <div class="btn-group btn-group-sm">
                <button class="btn btn-sm btn-desir-valider me-1" data-valid-abs="${a.id}" title="Valider"><i class="bi bi-check-lg"></i></button>
                <button class="btn btn-sm btn-desir-refuser" data-refuse-abs="${a.id}" title="Refuser"><i class="bi bi-x-lg"></i></button>
              </div>
            ` : '\u2014'}
          </td>
        </tr>`;
    }).join('');
}

function openAbsDetail(idx) {
    const a = absData[idx];
    if (!a) return;

    const sCls = statusClasses[a.statut] || statusClasses.en_attente;
    const tCls = typeClasses[a.type] || 'badge-ss-attente';
    const rempl = a.remplacement_type
        ? (a.remplacement_type === 'collegue' ? `${escapeHtml(a.rempl_prenom || '')} ${escapeHtml(a.rempl_nom || '')}` : escapeHtml(a.remplacement_type))
        : '\u2014';

    document.getElementById('absDetailHeader').innerHTML = `
        ${makeAvatar(a, 'md')}
        <div>
            <h6 class="mb-0">${escapeHtml(a.prenom)} ${escapeHtml(a.nom)}</h6>
            <small class="text-muted">${escapeHtml(a.fonction_code || '')} \u00b7 ${escapeHtml(a.module_nom || '')}</small>
        </div>`;

    let html = `
        <div class="abs-detail-row"><span class="abs-detail-label">Type</span><span class="abs-detail-value">${makeBadge(a.type, tCls)}</span></div>
        <div class="abs-detail-row"><span class="abs-detail-label">P\u00e9riode</span><span class="abs-detail-value">${escapeHtml(a.date_debut)} \u2192 ${escapeHtml(a.date_fin)}</span></div>
        <div class="abs-detail-row"><span class="abs-detail-label">Statut</span><span class="abs-detail-value">${makeBadge(statusLabels[a.statut] || a.statut, sCls)}</span></div>
        <div class="abs-detail-row"><span class="abs-detail-label">Remplacement</span><span class="abs-detail-value">${rempl}</span></div>`;

    if (a.motif) html += `<div class="abs-detail-row"><span class="abs-detail-label">Motif</span><span class="abs-detail-value">${escapeHtml(a.motif)}</span></div>`;
    if (a.commentaire) html += `<div class="abs-detail-row"><span class="abs-detail-label">Commentaire</span><span class="abs-detail-value">${escapeHtml(a.commentaire)}</span></div>`;
    if (a.created_at) html += `<div class="abs-detail-row"><span class="abs-detail-label">D\u00e9pos\u00e9e le</span><span class="abs-detail-value">${escapeHtml(a.created_at.substring(0, 10))}</span></div>`;

    html += '<div class="mt-3">';
    html += '<div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold small">Justificatif</span>';
    html += `<label class="btn btn-sm btn-outline-secondary abs-upload-label"><i class="bi bi-upload"></i> Ajouter<input type="file" id="absJustUpload" data-id="${a.id}" accept="image/*,.pdf" class="abs-upload-input"></label>`;
    html += '</div>';

    if (a.justificatif_path) {
        const ext = a.justificatif_path.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
        const isPdf = ext === 'pdf';
        html += `<div class="abs-file-preview" data-file-url="${escapeHtml(a.justificatif_path)}" data-file-name="${escapeHtml(a.justificatif_name || '')}" data-file-type="${isImage ? 'image' : (isPdf ? 'pdf' : 'other')}">`;
        if (isImage) html += `<img src="${escapeHtml(a.justificatif_path)}" alt="Justificatif">`;
        else if (isPdf) html += `<div class="abs-file-icon"><i class="bi bi-file-earmark-pdf abs-icon-pdf"></i></div>`;
        else html += `<div class="abs-file-icon"><i class="bi bi-file-earmark"></i></div>`;
        html += `<div class="abs-file-name"><i class="bi bi-paperclip"></i> ${escapeHtml(a.justificatif_name || 'Fichier')} <i class="bi bi-box-arrow-up-right ms-auto abs-icon-external"></i></div>`;
        html += '</div>';
        html += `<button class="btn btn-sm mt-2 btn-delete-justif" data-delete-justif="${a.id}"><i class="bi bi-trash"></i> Supprimer le justificatif</button>`;
    } else {
        html += '<div class="text-center text-muted py-3 abs-no-justif"><i class="bi bi-file-earmark-x"></i><br><small>Aucun justificatif</small></div>';
    }
    html += '</div>';

    document.getElementById('absDetailBody').innerHTML = html;

    let footerHtml = '';
    if (a.statut === 'en_attente') {
        footerHtml = `
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-sm btn-desir-refuser px-3" data-modal-refuse="${a.id}"><i class="bi bi-x-lg"></i> Refuser</button>
                <button class="btn btn-sm btn-desir-valider px-3" data-modal-valid="${a.id}"><i class="bi bi-check-lg"></i> Valider</button>
            </div>`;
    } else {
        footerHtml = '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-dismiss="modal">Fermer</button>';
    }
    document.getElementById('absDetailFooter').innerHTML = footerHtml;

    document.getElementById('absDetailBody').querySelector('.abs-file-preview')?.addEventListener('click', function() {
        openFileViewer(this.dataset.fileUrl, this.dataset.fileName, this.dataset.fileType);
    });
    document.getElementById('absDetailBody').querySelector('[data-delete-justif]')?.addEventListener('click', function() {
        deleteJustificatif(this.dataset.deleteJustif);
    });
    document.getElementById('absJustUpload')?.addEventListener('change', uploadJustificatif);
    document.getElementById('absDetailFooter').querySelector('[data-modal-valid]')?.addEventListener('click', function() {
        validAbsence(this.dataset.modalValid, 'valide', true);
    });
    document.getElementById('absDetailFooter').querySelector('[data-modal-refuse]')?.addEventListener('click', function() {
        validAbsence(this.dataset.modalRefuse, 'refuse', true);
    });

    absDetailModalInstance.show();
}

async function uploadJustificatif(e) {
    const file = e.target.files[0];
    if (!file) return;
    const id = e.target.dataset.id;
    const fd = new FormData();
    fd.append('action', 'admin_upload_justificatif');
    fd.append('absence_id', id);
    fd.append('file', file);
    try {
        const csrfToken = window.__SS_ADMIN__?.csrfToken || '';
        const resp = await fetch('/newspocspace/admin/api.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd });
        const res = await resp.json();
        if (res.csrf) window.__SS_ADMIN__.csrfToken = res.csrf;
        if (res.success) {
            showToast('Justificatif ajout\u00e9', 'success');
            absDetailModalInstance.hide();
            location.reload();
        } else { showToast(res.message || 'Erreur', 'error'); }
    } catch (err) { showToast('Erreur upload', 'error'); }
}

async function deleteJustificatif(absenceId) {
    if (!await adminConfirm({ title: 'Supprimer le justificatif', text: 'Supprimer ce fichier ? Cette action est irr\u00e9versible.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
    const res = await adminApiPost('admin_delete_justificatif', { absence_id: absenceId });
    if (res.success) {
        showToast('Justificatif supprim\u00e9', 'success');
        absDetailModalInstance.hide();
        location.reload();
    } else { showToast(res.message || 'Erreur', 'error'); }
}

async function validAbsence(id, statut, fromModal = false) {
    const res = await adminApiPost('admin_validate_absence', { id, statut });
    if (res.success) {
        showToast(res.message, 'success');
        if (fromModal) absDetailModalInstance.hide();
        location.reload();
    } else { showToast(res.message || 'Erreur', 'error'); }
}

// ═══ FILE VIEWER (Lightbox) ═══
const _lbHandlers = [];
function _lbAdd(el, evt, fn, opts) { if (!el) return; el.addEventListener(evt, fn, opts); _lbHandlers.push([el, evt, fn, opts]); }
function _lbRemoveAll() { _lbHandlers.forEach(([el, evt, fn, opts]) => el.removeEventListener(evt, fn, opts)); _lbHandlers.length = 0; }

function openFileViewer(url, name, type) {
    const lb = document.getElementById('ztLightbox');
    const stage = document.getElementById('ztLbStage');
    const titleEl = document.getElementById('ztLbTitle');
    const toolbar = document.getElementById('ztLbToolbar');

    _lbRemoveAll();
    titleEl.textContent = name || 'Fichier';

    let scale = 1, tx = 0, ty = 0, dragging = false, lastX = 0, lastY = 0;
    let imgEl = null;

    if (type === 'image') {
        stage.innerHTML = `<img src="${url}" alt="${escapeHtml(name)}" draggable="false">`;
        imgEl = stage.querySelector('img');
        toolbar.classList.remove('ss-lightbox-hidden');
    } else if (type === 'pdf') {
        stage.innerHTML = `<iframe src="${url}#toolbar=1"></iframe>`;
        toolbar.classList.add('ss-lightbox-hidden');
    } else {
        stage.innerHTML = `<div class="ss-lb-fallback"><i class="bi bi-file-earmark"></i><br><a href="${url}" target="_blank">T\u00e9l\u00e9charger le fichier</a></div>`;
        toolbar.classList.add('ss-lightbox-hidden');
    }

    function apply() {
        if (!imgEl) return;
        requestAnimationFrame(() => {
            imgEl.style.transform = `translate(${tx}px, ${ty}px) scale(${scale})`;
            document.getElementById('ztLbZoomLevel').textContent = Math.round(scale * 100) + '%';
            stage.classList.toggle('ss-zoomed', scale > 1.01);
        });
    }
    function resetZoom() { scale = 1; tx = 0; ty = 0; apply(); }
    function zoomAt(px, py, ns) {
        ns = Math.max(0.5, Math.min(10, ns));
        const rect = stage.getBoundingClientRect();
        const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
        const ox = px - cx, oy = py - cy;
        tx = ox - (ox - tx) * (ns / scale);
        ty = oy - (oy - ty) * (ns / scale);
        scale = ns;
        if (scale <= 1) { tx = 0; ty = 0; scale = 1; }
        apply();
    }
    function zoomBy(d) { const r = stage.getBoundingClientRect(); zoomAt(r.left + r.width / 2, r.top + r.height / 2, scale + d); }
    function closeLb() {
        lb.classList.add('ss-lightbox-hidden');
        document.body.style.overflow = '';
        stage.classList.remove('ss-zoomed', 'ss-dragging');
        _lbRemoveAll();
    }

    lb.classList.remove('ss-lightbox-hidden');
    document.body.style.overflow = 'hidden';

    _lbAdd(lb.querySelector('.ss-lightbox-close'), 'click', closeLb);
    _lbAdd(lb.querySelector('.ss-lightbox-overlay'), 'click', closeLb);
    _lbAdd(document.getElementById('ztLbZoomIn'), 'click', () => zoomBy(.25));
    _lbAdd(document.getElementById('ztLbZoomOut'), 'click', () => zoomBy(-.25));
    _lbAdd(document.getElementById('ztLbReset'), 'click', resetZoom);
    _lbAdd(document, 'keydown', (e) => {
        if (lb.classList.contains('ss-lightbox-hidden')) return;
        if (e.key === 'Escape') closeLb();
        else if (e.key === '+' || e.key === '=') zoomBy(.25);
        else if (e.key === '-') zoomBy(-.25);
        else if (e.key === '0') resetZoom();
    });

    if (imgEl) {
        _lbAdd(stage, 'wheel', (e) => { e.preventDefault(); zoomAt(e.clientX, e.clientY, scale * (1 + (e.deltaY > 0 ? -.15 : .15))); }, { passive: false });
        _lbAdd(stage, 'mousedown', (e) => { if (e.button !== 0 || scale <= 1.01) return; dragging = true; lastX = e.clientX; lastY = e.clientY; stage.classList.add('ss-dragging'); e.preventDefault(); });
        _lbAdd(document, 'mousemove', (e) => { if (!dragging) return; tx += e.clientX - lastX; ty += e.clientY - lastY; lastX = e.clientX; lastY = e.clientY; apply(); });
        _lbAdd(document, 'mouseup', () => { if (dragging) { dragging = false; stage.classList.remove('ss-dragging'); } });
        _lbAdd(stage, 'dblclick', (e) => { if (scale > 1.01) resetZoom(); else zoomAt(e.clientX, e.clientY, 2.5); });
    }
}

window.initAbsencesPage = initAbsencesPage;
window.validAbsence = validAbsence;
window.deleteJustificatif = deleteJustificatif;
window.openFileViewer = openFileViewer;
</script>
