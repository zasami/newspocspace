<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$ssrTotalFormations = (int) Db::getOne("SELECT COUNT(*) FROM formations");
$ssrPlanifiees = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'planifiee'");
$ssrEnCours = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'en_cours'");
$ssrTerminees = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'terminee'");
?>
<style>
/* stat cards use global .stat-card from admin.css */
.rhf-stat-filter { cursor: pointer; transition: all .15s; border: 2px solid transparent; }
.rhf-stat-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.rhf-stat-filter.active { border-color: var(--cl-primary, #7B6B5B); background: var(--cl-bg, #F7F5F2); }

/* ── Table overrides ── */
#rhfBody .table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); }
#rhfBody .table td { vertical-align: middle; font-size: .88rem; }

/* ── Badges ── */
.rhf-badge { font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; }
.rhf-badge-interne    { background: #bcd2cb; color: #2d4a43; }
.rhf-badge-externe    { background: #B8C9D4; color: #3B4F6B; }
.rhf-badge-e-learning { background: #D0C4D8; color: #5B4B6B; }
.rhf-badge-certificat { background: #D4C4A8; color: #6B5B3E; }
.rhf-badge-planifiee  { background: #B8C9D4; color: #3B4F6B; }
.rhf-badge-en_cours   { background: #D4C4A8; color: #6B5B3E; }
.rhf-badge-terminee   { background: #bcd2cb; color: #2d4a43; }
.rhf-badge-annulee    { background: #E2B8AE; color: #7B3B2C; }
.rhf-count { background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); font-size: .72rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }

/* ── Action buttons ── */
.rhf-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .88rem; transition: all .12s; display: flex; align-items: center; justify-content: center; }
.rhf-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.rhf-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

/* ── Detail sections ── */
.rhf-detail-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin-bottom: 4px; }
.rhf-detail-val { font-size: .88rem; }
/* participant table uses .table too */
.rhf-search-box { position: relative; }
.rhf-search-results { position: absolute; top: 100%; left: 0; right: 0; background: var(--cl-surface, #fff); border: 1px solid var(--cl-border); border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 10; display: none; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.rhf-search-results.show { display: block; }
.rhf-search-item { padding: 8px 12px; cursor: pointer; font-size: .85rem; }
.rhf-search-item:hover { background: var(--cl-bg); }

/* ── Empty ── */
.rhf-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.rhf-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }

/* ── Formation cards ── */
.rhf-cards { display: flex; flex-direction: column; gap: 12px; }
.rhf-card { display: flex; border: 1px solid var(--cl-border, #ddd); border-radius: 12px; overflow: hidden; background: #fff; transition: box-shadow .15s; }
.rhf-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.rhf-card-img-wrap { flex-shrink: 0; width: 160px; }
.rhf-card-img { width: 100%; height: 100%; object-fit: cover; display: block; background: var(--cl-bg, #F7F5F2); border-radius: 12px 0 0 12px; }
.rhf-card-img-placeholder { width: 100%; height: 100%; min-height: 120px; background: linear-gradient(135deg, var(--cl-bg, #F7F5F2), #e8e4de); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--cl-text-muted); opacity: .3; border-radius: 12px 0 0 12px; }
.rhf-card-content { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.rhf-card-body { padding: .75rem 1rem; flex: 1; }
.rhf-card-title { font-weight: 700; font-size: .95rem; color: var(--cl-text); margin-bottom: .4rem; line-height: 1.3; }
.rhf-card-title a { color: inherit; text-decoration: none; }
.rhf-card-title a:hover { text-decoration: underline; }
.rhf-card-meta { display: flex; flex-wrap: wrap; gap: .35rem .75rem; font-size: .78rem; color: var(--cl-text-muted); }
.rhf-card-meta i { margin-right: 2px; font-size: .72rem; }
.rhf-card-sep { border: 0; border-top: 1px solid var(--cl-border-light, #F0EDE8); margin: 0; }
.rhf-card-footer { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; }
.rhf-card-footer-left { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
.rhf-card-footer-right { display: flex; gap: .25rem; }
@media (max-width: 576px) { .rhf-card { flex-direction: column; } .rhf-card-img-wrap { width: 100%; height: 120px; } .rhf-card-img, .rhf-card-img-placeholder { border-radius: 12px 12px 0 0; } }

/* ── Import modal ── */
.rhf-import-sources { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; padding: 10px 0; }
.rhf-import-card { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 20px 18px; border-radius: 14px; border: 1.5px solid var(--cl-border-light, #F0EDE8); cursor: pointer; transition: all .2s ease; min-width: 110px; flex: 1; max-width: 150px; text-align: center; background: var(--cl-surface, #fff); }
.rhf-import-card:hover { border-color: var(--cl-primary, #7B6B5B); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.08); }
.rhf-import-card .rhf-ic-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; transition: transform .2s; }
.rhf-import-card:hover .rhf-ic-icon { transform: scale(1.1); }
.rhf-import-card .rhf-ic-label { font-size: .78rem; font-weight: 600; color: var(--cl-text); }

/* ── FEGEMS preview cards ── */
.rhf-fegems-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; max-height: 60vh; overflow-y: auto; padding: 4px; }
.rhf-fegems-card { display: flex; gap: 12px; padding: 12px; border-radius: 12px; border: 1.5px solid var(--cl-border-light, #F0EDE8); transition: all .15s; cursor: pointer; position: relative; }
.rhf-fegems-card:hover { border-color: var(--cl-primary, #7B6B5B); }
.rhf-fegems-card.selected { border-color: #2d7d32; background: rgba(188,210,203,.15); }
.rhf-fegems-card .rhf-fg-check { position: absolute; top: 8px; right: 8px; width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--cl-border); display: flex; align-items: center; justify-content: center; font-size: .7rem; transition: all .15s; }
.rhf-fegems-card.selected .rhf-fg-check { background: #2d7d32; border-color: #2d7d32; color: #fff; }
.rhf-fegems-card .rhf-fg-img { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: var(--cl-bg, #F7F5F2); }
.rhf-fegems-card .rhf-fg-img-placeholder { width: 80px; height: 80px; border-radius: 10px; background: var(--cl-bg, #F7F5F2); display: flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: 1.5rem; flex-shrink: 0; }
.rhf-fegems-card .rhf-fg-info { flex: 1; min-width: 0; }
.rhf-fegems-card .rhf-fg-title { font-weight: 600; font-size: .85rem; line-height: 1.3; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rhf-fegems-card .rhf-fg-meta { font-size: .72rem; color: var(--cl-text-muted); }
.rhf-fegems-card .rhf-fg-meta i { margin-right: 3px; }
.rhf-fegems-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.rhf-fegems-toolbar .rhf-fg-count { font-size: .82rem; color: var(--cl-text-muted); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-mortarboard"></i> Formations</h4>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card rhf-stat-filter" data-filter-statut="">
      <div class="stat-icon bg-teal"><i class="bi bi-mortarboard"></i></div>
      <div>
        <div class="stat-value" id="rhfStatTotal"><?= $ssrTotalFormations ?></div>
        <div class="stat-label">Total formations</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card rhf-stat-filter" data-filter-statut="planifiee">
      <div class="stat-icon bg-green"><i class="bi bi-calendar-check"></i></div>
      <div>
        <div class="stat-value" id="rhfStatPlanifiees"><?= $ssrPlanifiees ?></div>
        <div class="stat-label">Planifiées</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card rhf-stat-filter" data-filter-statut="en_cours">
      <div class="stat-icon bg-orange"><i class="bi bi-play-circle"></i></div>
      <div>
        <div class="stat-value" id="rhfStatEnCours"><?= $ssrEnCours ?></div>
        <div class="stat-label">En cours</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card rhf-stat-filter" data-filter-statut="terminee">
      <div class="stat-icon bg-teal"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="stat-value" id="rhfStatTerminees"><?= $ssrTerminees ?></div>
        <div class="stat-label">Terminées</div>
      </div>
    </div>
  </div>
</div>

<!-- Toolbar -->
<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-primary" id="btnAddFormation"><i class="bi bi-plus-lg"></i> Nouvelle formation</button>
  <button class="btn btn-sm btn-outline-primary" id="btnImportFormation"><i class="bi bi-cloud-download"></i> Importer formations</button>
  <div class="ms-auto"></div>
  <select class="form-select form-select-sm" id="rhfFilterStatut" style="max-width:160px">
    <option value="">Tous les statuts</option>
    <option value="planifiee">Planifiée</option>
    <option value="en_cours">En cours</option>
    <option value="terminee">Terminée</option>
    <option value="annulee">Annulée</option>
  </select>
</div>

<!-- Formations list -->
<div id="rhfBody">
  <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
</div>

<!-- ═══ Modal: Formation create/edit ═══ -->
<div class="modal fade" id="rhfFormModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhfFormModalTitle">Nouvelle formation</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rhfFormId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Titre *</label>
            <input type="text" class="form-control form-control-sm" id="rhfFormTitre" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select class="form-select form-select-sm" id="rhfFormType">
              <option value="interne">Interne</option>
              <option value="externe">Externe</option>
              <option value="e-learning">E-learning</option>
              <option value="certificat">Certificat</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control form-control-sm" id="rhfFormDesc" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Formateur</label>
            <input type="text" class="form-control form-control-sm" id="rhfFormFormateur">
          </div>
          <div class="col-md-6">
            <label class="form-label">Lieu</label>
            <input type="text" class="form-control form-control-sm" id="rhfFormLieu">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date début</label>
            <input type="date" class="form-control form-control-sm" id="rhfFormDateDebut">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date fin</label>
            <input type="date" class="form-control form-control-sm" id="rhfFormDateFin">
          </div>
          <div class="col-md-4">
            <label class="form-label">Durée (heures)</label>
            <input type="number" class="form-control form-control-sm" id="rhfFormDuree" min="0" step="0.5">
          </div>
          <div class="col-md-6">
            <label class="form-label">Max participants</label>
            <input type="number" class="form-control form-control-sm" id="rhfFormMaxPart" min="0">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="rhfFormObligatoire">
              <label class="form-check-label" for="rhfFormObligatoire">Formation obligatoire</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnSaveFormation"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Formation detail ═══ -->
<div class="modal fade" id="rhfDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhfDetailTitle">Détail formation</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="rhfDetailBody">
        <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Confirm delete ═══ -->
<div class="modal fade" id="rhfDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Supprimer la formation ?</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="mb-0 small text-muted">Cette action supprimera la formation et tous ses participants.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDeleteForm"><i class="bi bi-trash"></i> Supprimer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Import source chooser ═══ -->
<div class="modal fade" id="rhfImportModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cloud-download"></i> Importer des formations</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Choisissez la source d'importation :</p>
        <div class="rhf-import-sources">
          <div class="rhf-import-card" data-import="fegems">
            <div class="rhf-ic-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-globe2"></i></div>
            <div class="rhf-ic-label">FEGEMS</div>
          </div>
          <label class="rhf-import-card" data-import="csv">
            <div class="rhf-ic-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-filetype-csv"></i></div>
            <div class="rhf-ic-label">CSV</div>
            <input type="file" id="rhfFileCSV" accept=".csv,.txt" style="display:none">
          </label>
          <label class="rhf-import-card" data-import="excel">
            <div class="rhf-ic-icon" style="background:#c8e6c9;color:#2e7d32"><i class="bi bi-file-earmark-spreadsheet"></i></div>
            <div class="rhf-ic-label">Excel</div>
            <input type="file" id="rhfFileExcel" accept=".xls,.xlsx" style="display:none">
          </label>
          <label class="rhf-import-card" data-import="pdf">
            <div class="rhf-ic-icon" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-file-earmark-pdf"></i></div>
            <div class="rhf-ic-label">PDF</div>
            <input type="file" id="rhfFilePDF" accept=".pdf" style="display:none">
          </label>
          <label class="rhf-import-card" data-import="word">
            <div class="rhf-ic-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-file-earmark-word"></i></div>
            <div class="rhf-ic-label">Word</div>
            <input type="file" id="rhfFileWord" accept=".doc,.docx" style="display:none">
          </label>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: FEGEMS preview ═══ -->
<div class="modal fade" id="rhfFegemsModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-globe2"></i> Formations FEGEMS</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="rhfFegemsBody">
        <div class="text-center py-5"><span class="spinner-border spinner-border-sm"></span> Chargement des formations depuis fegems.ch...</div>
      </div>
      <div class="modal-footer" id="rhfFegemsFooter" style="display:none">
        <span class="rhf-fg-count me-auto" id="rhfFegemsCount"></span>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnImportSelected" disabled><i class="bi bi-download"></i> Importer la sélection</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const TYPE_LABELS = { interne:'Interne', externe:'Externe', 'e-learning':'E-learning', certificat:'Certificat' };
    const STATUT_LABELS = { planifiee:'Planifiée', en_cours:'En cours', terminee:'Terminée', annulee:'Annulée' };
    const PART_LABELS = { inscrit:'Inscrit', present:'Présent', absent:'Absent', valide:'Validé' };

    let formationsData = [];
    let deleteId = null;
    let currentDetailId = null;
    let searchDebounce = null;

    function loadFormations() {
        const statut = document.getElementById('rhfFilterStatut')?.value || '';
        adminApiPost('admin_get_formations', { statut }).then(r => {
            if (!r.success) return;
            formationsData = r.formations || [];
            renderFormations();
            if (r.stats) {
                document.getElementById('rhfStatTotal').textContent = r.stats.total || 0;
                document.getElementById('rhfStatPlanifiees').textContent = r.stats.planifiee || 0;
                document.getElementById('rhfStatEnCours').textContent = r.stats.en_cours || 0;
                document.getElementById('rhfStatTerminees').textContent = r.stats.terminee || 0;
            }
        });
    }

    function renderFormations() {
        const el = document.getElementById('rhfBody');
        if (!el) return;
        if (!formationsData.length) {
            el.innerHTML = '<div class="rhf-empty"><i class="bi bi-mortarboard"></i>Aucune formation</div>';
            return;
        }
        let html = '<div class="rhf-cards">';
        formationsData.forEach(f => {
            const typeCls = f.type_formation || 'interne';
            const statutCls = f.statut || 'planifiee';
            const dates = f.date_debut ? formatDate(f.date_debut) + (f.date_fin && f.date_fin !== f.date_debut ? ' — ' + formatDate(f.date_fin) : '') : '';
            const partCount = parseInt(f.nb_participants || 0);
            const maxPart = f.max_participants ? parseInt(f.max_participants) : null;

            // Image
            const imgHtml = f.image_url
                ? `<img class="rhf-card-img" src="${escapeHtml(f.image_url)}" onerror="this.outerHTML='<div class=\\'rhf-card-img-placeholder\\'><i class=\\'bi bi-mortarboard\\'></i></div>'">`
                : '<div class="rhf-card-img-placeholder"><i class="bi bi-mortarboard"></i></div>';

            // Meta items
            let meta = '';
            if (f.formateur) meta += `<span><i class="bi bi-person"></i> ${escapeHtml(f.formateur)}</span>`;
            if (dates) meta += `<span><i class="bi bi-calendar3"></i> ${dates}</span>`;
            if (f.duree_heures) meta += `<span><i class="bi bi-clock"></i> ${f.duree_heures}h</span>`;
            if (f.lieu) meta += `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(f.lieu)}</span>`;
            const partLabel = maxPart ? `${partCount}/${maxPart}` : `${partCount}`;
            meta += `<span><i class="bi bi-people"></i> ${partLabel} participant${partCount > 1 ? 's' : ''}</span>`;

            html += `<div class="rhf-card">
                <div class="rhf-card-img-wrap">${imgHtml}</div>
                <div class="rhf-card-content">
                    <div class="rhf-card-body">
                        <div class="rhf-card-title">${escapeHtml(f.titre)}${f.source_url ? ` <a href="${escapeHtml(f.source_url)}" target="_blank" title="Source"><i class="bi bi-box-arrow-up-right" style="font-size:.72rem;color:var(--cl-text-muted)"></i></a>` : ''}</div>
                        <div class="rhf-card-meta">${meta}</div>
                    </div>
                    <hr class="rhf-card-sep">
                    <div class="rhf-card-footer">
                        <div class="rhf-card-footer-left">
                            <span class="rhf-badge rhf-badge-${typeCls}">${escapeHtml(TYPE_LABELS[f.type_formation] || f.type_formation)}</span>
                            <span class="rhf-badge rhf-badge-${statutCls}">${escapeHtml(STATUT_LABELS[f.statut] || f.statut)}</span>
                            ${parseInt(f.is_obligatoire) ? '<span class="rhf-badge" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check-circle-fill"></i> Obligatoire</span>' : ''}
                        </div>
                        <div class="rhf-card-footer-right">
                            <button class="rhf-row-btn" data-detail="${f.id}" title="Détail"><i class="bi bi-eye"></i></button>
                            <button class="rhf-row-btn" data-edit="${f.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                            <button class="rhf-row-btn danger" data-del="${f.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
    }

    document.getElementById('rhfFilterStatut')?.addEventListener('change', () => {
        syncStatFilterActive();
        loadFormations();
    });

    function syncStatFilterActive() {
        const val = document.getElementById('rhfFilterStatut')?.value || '';
        document.querySelectorAll('.rhf-stat-filter').forEach(c => {
            c.classList.toggle('active', (c.dataset.filterStatut || '') === val);
        });
    }

    document.querySelectorAll('.rhf-stat-filter').forEach(card => {
        card.addEventListener('click', () => {
            const sel = document.getElementById('rhfFilterStatut');
            if (!sel) return;
            const target = card.dataset.filterStatut || '';
            sel.value = (sel.value === target && target !== '') ? '' : target;
            syncStatFilterActive();
            loadFormations();
        });
    });
    syncStatFilterActive();

    document.getElementById('rhfBody')?.addEventListener('click', e => {
        const detail = e.target.closest('[data-detail]');
        if (detail) { openDetail(detail.dataset.detail); return; }
        const edit = e.target.closest('[data-edit]');
        if (edit) { openFormationModal(edit.dataset.edit); return; }
        const del = e.target.closest('[data-del]');
        if (del) { deleteId = del.dataset.del; new bootstrap.Modal(document.getElementById('rhfDeleteModal')).show(); }
    });

    document.getElementById('btnAddFormation')?.addEventListener('click', () => openFormationModal(null));

    function openFormationModal(id) {
        document.getElementById('rhfFormId').value = '';
        document.getElementById('rhfFormModalTitle').textContent = id ? 'Modifier la formation' : 'Nouvelle formation';
        ['rhfFormTitre','rhfFormDesc','rhfFormFormateur','rhfFormLieu'].forEach(f => document.getElementById(f).value = '');
        document.getElementById('rhfFormType').value = 'interne';
        document.getElementById('rhfFormDateDebut').value = '';
        document.getElementById('rhfFormDateFin').value = '';
        document.getElementById('rhfFormDuree').value = '';
        document.getElementById('rhfFormMaxPart').value = '';
        document.getElementById('rhfFormObligatoire').checked = false;

        if (id) {
            const f = formationsData.find(x => x.id === id);
            if (f) {
                document.getElementById('rhfFormId').value = f.id;
                document.getElementById('rhfFormTitre').value = f.titre || '';
                document.getElementById('rhfFormDesc').value = f.description || '';
                document.getElementById('rhfFormType').value = f.type_formation || 'interne';
                document.getElementById('rhfFormFormateur').value = f.formateur || '';
                document.getElementById('rhfFormLieu').value = f.lieu || '';
                document.getElementById('rhfFormDateDebut').value = f.date_debut || '';
                document.getElementById('rhfFormDateFin').value = f.date_fin || '';
                document.getElementById('rhfFormDuree').value = f.duree_heures || '';
                document.getElementById('rhfFormMaxPart').value = f.max_participants || '';
                document.getElementById('rhfFormObligatoire').checked = parseInt(f.is_obligatoire);
            }
        }
        new bootstrap.Modal(document.getElementById('rhfFormModal')).show();
    }

    document.getElementById('btnSaveFormation')?.addEventListener('click', () => {
        const id = document.getElementById('rhfFormId').value;
        const data = {
            titre: document.getElementById('rhfFormTitre').value.trim(),
            description: document.getElementById('rhfFormDesc').value.trim(),
            type_formation: document.getElementById('rhfFormType').value,
            formateur: document.getElementById('rhfFormFormateur').value.trim(),
            lieu: document.getElementById('rhfFormLieu').value.trim(),
            date_debut: document.getElementById('rhfFormDateDebut').value,
            date_fin: document.getElementById('rhfFormDateFin').value,
            duree_heures: document.getElementById('rhfFormDuree').value || null,
            max_participants: document.getElementById('rhfFormMaxPart').value || null,
            is_obligatoire: document.getElementById('rhfFormObligatoire').checked ? 1 : 0,
        };
        if (!data.titre) { showToast('Le titre est requis', 'danger'); return; }
        const action = id ? 'admin_update_formation' : 'admin_create_formation';
        if (id) data.id = id;
        adminApiPost(action, data).then(r => {
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('rhfFormModal'))?.hide();
                showToast(r.message || 'OK', 'success');
                loadFormations();
            } else showToast(r.error || 'Erreur', 'danger');
        });
    });

    document.getElementById('btnConfirmDeleteForm')?.addEventListener('click', () => {
        if (!deleteId) return;
        adminApiPost('admin_delete_formation', { id: deleteId }).then(r => {
            bootstrap.Modal.getInstance(document.getElementById('rhfDeleteModal'))?.hide();
            if (r.success) { showToast(r.message || 'Supprimé', 'success'); loadFormations(); }
            else showToast(r.error || 'Erreur', 'danger');
            deleteId = null;
        });
    });

    // ═══ Detail modal with participants ═══

    function openDetail(id) {
        currentDetailId = id;
        document.getElementById('rhfDetailBody').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
        new bootstrap.Modal(document.getElementById('rhfDetailModal')).show();
        loadDetail(id);
    }

    function loadDetail(id) {
        adminApiPost('admin_get_formation_detail', { id }).then(r => {
            if (!r.success) { document.getElementById('rhfDetailBody').innerHTML = '<p class="text-danger">Erreur</p>'; return; }
            const f = r.formation;
            const participants = r.participants || f.participants || [];
            document.getElementById('rhfDetailTitle').textContent = f.titre;

            const field = (label, val) => `<div class="mb-2"><div class="rhf-detail-label">${label}</div><div class="rhf-detail-val">${val}</div></div>`;
            const preBlock = (txt) => `<div style="white-space:pre-wrap;background:var(--cl-bg);padding:10px;border-radius:8px;font-size:.85rem">${escapeHtml(txt)}</div>`;

            let html = '';

            // Image + source link for imported formations
            if (f.image_url || f.source_url) {
                html += '<div class="d-flex align-items-start gap-3 mb-3">';
                if (f.image_url) html += `<img src="${escapeHtml(f.image_url)}" style="width:100px;height:100px;border-radius:12px;object-fit:cover;flex-shrink:0" onerror="this.style.display='none'">`;
                html += '<div>';
                if (f.source_url) html += `<a href="${escapeHtml(f.source_url)}" target="_blank" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-box-arrow-up-right"></i> Voir sur fegems.ch</a><br>`;
                if (f.modalite) html += `<span class="rhf-badge rhf-badge-externe" style="margin-right:4px"><i class="bi bi-geo-alt"></i> ${escapeHtml(f.modalite)}</span>`;
                if (f.categorie) html += `<span class="text-muted small"><i class="bi bi-tag"></i> ${escapeHtml(f.categorie)}</span>`;
                html += '</div></div>';
            }

            html += '<div class="row g-3">';
            html += `<div class="col-md-4">${field('Type', '<span class="rhf-badge rhf-badge-'+(f.type_formation||'interne')+'">'+escapeHtml(TYPE_LABELS[f.type_formation]||f.type_formation)+'</span>')}</div>`;
            html += `<div class="col-md-4">${field('Statut', '<span class="rhf-badge rhf-badge-'+(f.statut||'planifiee')+'">'+escapeHtml(STATUT_LABELS[f.statut]||f.statut)+'</span>')}</div>`;
            html += `<div class="col-md-4">${field('Obligatoire', parseInt(f.is_obligatoire) ? '<i class="bi bi-check-circle-fill text-success"></i> Oui' : 'Non')}</div>`;
            html += `<div class="col-md-4">${field('Formateur / Intervenant·es', escapeHtml(f.intervenants || f.formateur || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Lieu', escapeHtml(f.lieu || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Durée', f.duree_heures ? f.duree_heures + 'h' : '-')}</div>`;
            const dates = f.date_debut ? formatDate(f.date_debut) + (f.date_fin && f.date_fin !== f.date_debut ? ' - ' + formatDate(f.date_fin) : '') : '-';
            html += `<div class="col-md-6">${field('Dates', dates)}</div>`;
            html += `<div class="col-md-6">${field('Max participants', f.max_participants || 'Illimité')}</div>`;

            // FEGEMS-specific fields
            if (f.tarif_membres || f.tarif_non_membres) {
                let tarifs = '';
                if (f.tarif_membres) tarifs += `<div>Membres : <strong>${escapeHtml(f.tarif_membres)}</strong></div>`;
                if (f.tarif_non_membres) tarifs += `<div>Non-membres : <strong>${escapeHtml(f.tarif_non_membres)}</strong></div>`;
                if (f.tarif_externes) tarifs += `<div>Externes : <strong>${escapeHtml(f.tarif_externes)}</strong></div>`;
                html += `<div class="col-md-6">${field('Tarifs', tarifs)}</div>`;
            }
            if (f.date_cloture_inscription) html += `<div class="col-md-3">${field('Clôture inscriptions', formatDate(f.date_cloture_inscription))}</div>`;
            if (f.places_restantes) html += `<div class="col-md-3">${field('Places restantes', '<strong>' + escapeHtml(f.places_restantes) + '</strong>')}</div>`;
            if (f.public_cible) html += `<div class="col-md-6">${field('Public-cible', escapeHtml(f.public_cible))}</div>`;
            if (f.sessions) html += `<div class="col-12">${field('Sessions', preBlock(f.sessions))}</div>`;
            if (f.objectifs) html += `<div class="col-12">${field('Objectifs', preBlock(f.objectifs))}</div>`;
            if (f.description) html += `<div class="col-12">${field('Description', preBlock(f.description))}</div>`;
            if (f.info_complementaire) html += `<div class="col-12">${field('Informations complémentaires', preBlock(f.info_complementaire))}</div>`;
            html += '</div>';

            // Participants section
            html += '<hr class="my-3">';
            html += '<div class="d-flex align-items-center gap-2 mb-2"><h6 class="mb-0"><i class="bi bi-people"></i> Participants ('+participants.length+')</h6></div>';

            // Add participant search
            html += `<div class="rhf-search-box mb-3">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="rhfSearchUser" placeholder="Rechercher un collaborateur...">
                    <button class="btn btn-outline-secondary" type="button" id="rhfSearchBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>
                </div>
                <div class="rhf-search-results" id="rhfSearchResults"></div>
            </div>`;

            if (participants.length) {
                html += '<div class="card mt-2"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Nom</th><th>Fonction</th><th>Statut</th><th style="width:100px">Actions</th></tr></thead><tbody>';
                participants.forEach(p => {
                    const pStatut = p.statut_participation || 'inscrit';
                    html += `<tr>
                        <td><strong>${escapeHtml((p.prenom||'')+' '+(p.nom||''))}</strong></td>
                        <td>${escapeHtml(p.fonction_nom || '-')}</td>
                        <td><select class="form-select form-select-sm" data-part-status="${p.id}" style="max-width:120px;font-size:.78rem">
                            <option value="inscrit" ${pStatut==='inscrit'?'selected':''}>Inscrit</option>
                            <option value="present" ${pStatut==='present'?'selected':''}>Présent</option>
                            <option value="absent" ${pStatut==='absent'?'selected':''}>Absent</option>
                            <option value="valide" ${pStatut==='valide'?'selected':''}>Validé</option>
                        </select></td>
                        <td><button class="rhf-row-btn danger" data-remove-part="${p.id}" title="Retirer"><i class="bi bi-x-lg"></i></button></td>
                    </tr>`;
                });
                html += '</tbody></table></div></div>';
            } else {
                html += '<p class="text-muted small">Aucun participant inscrit.</p>';
            }

            document.getElementById('rhfDetailBody').innerHTML = html;
            bindDetailEvents();
        });
    }

    function bindDetailEvents() {
        // Search users
        const searchInput = document.getElementById('rhfSearchUser');
        const resultsEl = document.getElementById('rhfSearchResults');
        let sDebounce = null;

        searchInput?.addEventListener('input', () => {
            clearTimeout(sDebounce);
            const q = searchInput.value.trim();
            if (q.length < 2) { resultsEl.classList.remove('show'); return; }
            sDebounce = setTimeout(() => {
                adminApiPost('admin_search_users', { search: q, limit: 10 }).then(r => {
                    if (!r.success || !r.users?.length) { resultsEl.classList.remove('show'); return; }
                    resultsEl.innerHTML = r.users.map(u =>
                        `<div class="rhf-search-item" data-add-user="${u.id}">${escapeHtml(u.prenom+' '+u.nom)} <span class="text-muted small">${escapeHtml(u.fonction_nom||'')}</span></div>`
                    ).join('');
                    resultsEl.classList.add('show');
                });
            }, 300);
        });

        document.addEventListener('click', function hideResults(e) {
            if (!e.target.closest('.rhf-search-box')) resultsEl?.classList.remove('show');
        }, { once: false });

        resultsEl?.addEventListener('click', e => {
            const item = e.target.closest('[data-add-user]');
            if (!item || !currentDetailId) return;
            adminApiPost('admin_add_formation_participant', { formation_id: currentDetailId, user_id: item.dataset.addUser }).then(r => {
                if (r.success) { showToast('Participant ajouté', 'success'); loadDetail(currentDetailId); loadFormations(); }
                else showToast(r.error || 'Erreur', 'danger');
            });
            resultsEl.classList.remove('show');
            searchInput.value = '';
        });

        // Change participant status
        document.getElementById('rhfDetailBody')?.addEventListener('change', e => {
            const sel = e.target.closest('[data-part-status]');
            if (!sel) return;
            adminApiPost('admin_update_formation_participant', { id: sel.dataset.partStatus, statut_participation: sel.value }).then(r => {
                if (r.success) showToast('Statut mis à jour', 'success');
                else showToast(r.error || 'Erreur', 'danger');
            });
        });

        // Remove participant
        document.getElementById('rhfDetailBody')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-remove-part]');
            if (!btn) return;
            if (!confirm('Retirer ce participant ?')) return;
            adminApiPost('admin_remove_formation_participant', { id: btn.dataset.removePart }).then(r => {
                if (r.success) { showToast('Participant retiré', 'success'); loadDetail(currentDetailId); loadFormations(); }
                else showToast(r.error || 'Erreur', 'danger');
            });
        });
    }

    function formatDate(d) {
        if (!d) return '-';
        try { const dt = new Date(d); return dt.toLocaleDateString('fr-CH', { day:'2-digit', month:'2-digit', year:'numeric' }); } catch(e) { return d; }
    }

    // ═══ Import formations ═══

    document.getElementById('btnImportFormation')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('rhfImportModal')).show();
    });

    // FEGEMS import
    let fegemsData = [];
    let fegemsSelected = new Set();

    document.querySelector('[data-import="fegems"]')?.addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('rhfImportModal'))?.hide();
        const modal = new bootstrap.Modal(document.getElementById('rhfFegemsModal'));
        modal.show();
        document.getElementById('rhfFegemsBody').innerHTML = '<div class="text-center py-5"><span class="spinner-border spinner-border-sm"></span> Chargement des formations depuis fegems.ch...</div>';
        document.getElementById('rhfFegemsFooter').style.display = 'none';

        adminApiPost('admin_import_fegems_formations', {}).then(r => {
            if (!r.success) { document.getElementById('rhfFegemsBody').innerHTML = '<p class="text-danger">Erreur : ' + escapeHtml(r.error || 'Impossible de charger') + '</p>'; return; }
            fegemsData = r.formations || [];
            fegemsSelected = new Set();
            renderFegemsPreview();
        });
    });

    function renderFegemsPreview() {
        const body = document.getElementById('rhfFegemsBody');
        if (!fegemsData.length) { body.innerHTML = '<p class="text-muted">Aucune formation trouvée.</p>'; return; }

        let html = '<div class="rhf-fegems-toolbar">';
        html += '<button class="btn btn-sm btn-outline-secondary" id="rhfSelectAll"><i class="bi bi-check2-all"></i> Tout sélectionner</button>';
        html += '<button class="btn btn-sm btn-outline-secondary" id="rhfDeselectAll"><i class="bi bi-x-lg"></i> Tout désélectionner</button>';
        html += '<input type="text" class="form-control form-control-sm" id="rhfFegemsSearch" placeholder="Filtrer..." style="max-width:220px">';
        html += '<span class="rhf-fg-count">' + fegemsData.length + ' formations disponibles</span>';
        html += '</div>';

        html += '<div class="rhf-fegems-grid" id="rhfFegemsGrid">';
        fegemsData.forEach((f, i) => {
            const sel = fegemsSelected.has(i) ? ' selected' : '';
            html += `<div class="rhf-fegems-card${sel}" data-fi="${i}">
                <div class="rhf-fg-check"><i class="bi bi-check-lg"></i></div>
                ${f.image_url ? `<img src="${escapeHtml(f.image_url)}" class="rhf-fg-img" loading="lazy" onerror="this.outerHTML='<div class=\\'rhf-fg-img-placeholder\\'><i class=\\'bi bi-mortarboard\\'></i></div>'">` : '<div class="rhf-fg-img-placeholder"><i class="bi bi-mortarboard"></i></div>'}
                <div class="rhf-fg-info">
                    <div class="rhf-fg-title">${escapeHtml(f.titre)}</div>
                    <div class="rhf-fg-meta"><i class="bi bi-calendar3"></i> ${escapeHtml(f.date_text || '-')}</div>
                    ${f.modalite ? `<div class="rhf-fg-meta"><i class="bi bi-geo-alt"></i> ${escapeHtml(f.modalite)}</div>` : ''}
                    ${f.categories ? `<div class="rhf-fg-meta"><i class="bi bi-tag"></i> ${escapeHtml(f.categories)}</div>` : ''}
                </div>
            </div>`;
        });
        html += '</div>';

        body.innerHTML = html;
        document.getElementById('rhfFegemsFooter').style.display = 'flex';
        updateFegemsCount();

        // Card click toggle
        document.getElementById('rhfFegemsGrid')?.addEventListener('click', e => {
            const card = e.target.closest('[data-fi]');
            if (!card) return;
            const idx = parseInt(card.dataset.fi);
            if (fegemsSelected.has(idx)) { fegemsSelected.delete(idx); card.classList.remove('selected'); }
            else { fegemsSelected.add(idx); card.classList.add('selected'); }
            updateFegemsCount();
        });

        // Select/Deselect all
        document.getElementById('rhfSelectAll')?.addEventListener('click', () => {
            document.querySelectorAll('#rhfFegemsGrid .rhf-fegems-card:not([style*="display: none"])').forEach(c => {
                const idx = parseInt(c.dataset.fi);
                fegemsSelected.add(idx);
                c.classList.add('selected');
            });
            updateFegemsCount();
        });
        document.getElementById('rhfDeselectAll')?.addEventListener('click', () => {
            fegemsSelected.clear();
            document.querySelectorAll('#rhfFegemsGrid .rhf-fegems-card').forEach(c => c.classList.remove('selected'));
            updateFegemsCount();
        });

        // Filter
        document.getElementById('rhfFegemsSearch')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('#rhfFegemsGrid .rhf-fegems-card').forEach(c => {
                const idx = parseInt(c.dataset.fi);
                const f = fegemsData[idx];
                const match = !q || f.titre.toLowerCase().includes(q) || (f.categories||'').toLowerCase().includes(q) || (f.modalite||'').toLowerCase().includes(q);
                c.style.display = match ? '' : 'none';
            });
        });
    }

    function updateFegemsCount() {
        const n = fegemsSelected.size;
        document.getElementById('rhfFegemsCount').textContent = n + ' formation' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : '');
        document.getElementById('btnImportSelected').disabled = n === 0;
    }

    document.getElementById('btnImportSelected')?.addEventListener('click', () => {
        if (!fegemsSelected.size) return;
        const btn = document.getElementById('btnImportSelected');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';

        const items = Array.from(fegemsSelected).map(i => fegemsData[i]);
        adminApiPost('admin_save_imported_formations', { formations: items }).then(r => {
            btn.innerHTML = '<i class="bi bi-download"></i> Importer la sélection';
            btn.disabled = false;
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('rhfFegemsModal'))?.hide();
                showToast(r.message || 'Importé', 'success');
                loadFormations();
            } else showToast(r.error || 'Erreur', 'danger');
        });
    });

    // File imports (CSV)
    document.getElementById('rhfFileCSV')?.addEventListener('change', (e) => {
        if (!e.target.files[0]) return;
        bootstrap.Modal.getInstance(document.getElementById('rhfImportModal'))?.hide();
        const fd = new FormData();
        fd.append('file', e.target.files[0]);
        fd.append('action', 'admin_import_formations_file');

        fetch('/spocspace/admin/api.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
        }).then(r => r.json()).then(r => {
            e.target.value = '';
            if (!r.success) { showToast(r.error || 'Erreur', 'danger'); return; }
            // Show as FEGEMS-style preview for selection
            fegemsData = r.formations || [];
            fegemsSelected = new Set();
            const modal = new bootstrap.Modal(document.getElementById('rhfFegemsModal'));
            document.querySelector('#rhfFegemsModal .modal-title').innerHTML = '<i class="bi bi-filetype-csv"></i> Formations importées du fichier';
            modal.show();
            renderFegemsPreview();
        });
    });

    // Excel/PDF/Word — same flow, just show info toast for now
    ['rhfFileExcel', 'rhfFilePDF', 'rhfFileWord'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', (e) => {
            if (!e.target.files[0]) return;
            bootstrap.Modal.getInstance(document.getElementById('rhfImportModal'))?.hide();
            showToast('Import Excel/PDF/Word : format CSV requis pour le moment. Exportez votre fichier en CSV puis réessayez.', 'warning');
            e.target.value = '';
        });
    });

    loadFormations();
})();
</script>
