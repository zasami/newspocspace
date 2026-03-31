<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$ssrTotalFormations = (int) Db::getOne("SELECT COUNT(*) FROM formations");
$ssrPlanifiees = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'planifiee'");
$ssrEnCours = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'en_cours'");
$ssrTerminees = (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'terminee'");
?>
<style>
/* ── Stat cards ── */
.rhf-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.rhf-stat-card { flex: 1; min-width: 140px; text-align: center; padding: 14px 10px; border-radius: 12px; border: 1px solid var(--cl-border-light, #F0EDE8); }
.rhf-stat-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 6px; }
.rhf-stat-val { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.rhf-stat-lbl { font-size: .72rem; color: var(--cl-text-muted); margin-top: 2px; }

/* ── Table ── */
.rhf-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.rhf-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; }
.rhf-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.rhf-table tr:hover td { background: var(--cl-bg); }

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
.rhf-participant-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
.rhf-participant-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 8px 10px; border-bottom: 1.5px solid var(--cl-border); text-align: left; }
.rhf-participant-table td { padding: 8px 10px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .85rem; }
.rhf-search-box { position: relative; }
.rhf-search-results { position: absolute; top: 100%; left: 0; right: 0; background: var(--cl-surface, #fff); border: 1px solid var(--cl-border); border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 10; display: none; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.rhf-search-results.show { display: block; }
.rhf-search-item { padding: 8px 12px; cursor: pointer; font-size: .85rem; }
.rhf-search-item:hover { background: var(--cl-bg); }

/* ── Empty ── */
.rhf-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.rhf-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-mortarboard"></i> Formations</h4>
</div>

<!-- Stat cards -->
<div class="rhf-stats">
  <div class="rhf-stat-card">
    <div class="rhf-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-mortarboard"></i></div>
    <div class="rhf-stat-val" id="rhfStatTotal"><?= $ssrTotalFormations ?></div>
    <div class="rhf-stat-lbl">Total formations</div>
  </div>
  <div class="rhf-stat-card">
    <div class="rhf-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-calendar-check"></i></div>
    <div class="rhf-stat-val" id="rhfStatPlanifiees"><?= $ssrPlanifiees ?></div>
    <div class="rhf-stat-lbl">Planifiées</div>
  </div>
  <div class="rhf-stat-card">
    <div class="rhf-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-play-circle"></i></div>
    <div class="rhf-stat-val" id="rhfStatEnCours"><?= $ssrEnCours ?></div>
    <div class="rhf-stat-lbl">En cours</div>
  </div>
  <div class="rhf-stat-card">
    <div class="rhf-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-check-circle"></i></div>
    <div class="rhf-stat-val" id="rhfStatTerminees"><?= $ssrTerminees ?></div>
    <div class="rhf-stat-lbl">Terminées</div>
  </div>
</div>

<!-- Toolbar -->
<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-primary" id="btnAddFormation"><i class="bi bi-plus-lg"></i> Nouvelle formation</button>
  <div class="ms-auto"></div>
  <select class="form-select form-select-sm" id="rhfFilterStatut" style="max-width:160px">
    <option value="">Tous les statuts</option>
    <option value="planifiee">Planifiée</option>
    <option value="en_cours">En cours</option>
    <option value="terminee">Terminée</option>
    <option value="annulee">Annulée</option>
  </select>
</div>

<!-- Table body -->
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
        let html = '<table class="rhf-table"><thead><tr><th>Titre</th><th>Type</th><th>Formateur</th><th>Dates</th><th>Durée</th><th>Participants</th><th>Oblig.</th><th>Statut</th><th style="width:100px">Actions</th></tr></thead><tbody>';
        formationsData.forEach(f => {
            const typeCls = f.type_formation || 'interne';
            const statutCls = f.statut || 'planifiee';
            const dates = f.date_debut ? formatDate(f.date_debut) + (f.date_fin && f.date_fin !== f.date_debut ? ' - ' + formatDate(f.date_fin) : '') : '-';
            const partCount = parseInt(f.nb_participants || 0);
            const maxPart = f.max_participants ? parseInt(f.max_participants) : null;
            const partHtml = maxPart ? `<span class="rhf-count">${partCount}/${maxPart}</span>` : `<span class="rhf-count">${partCount}</span>`;
            html += `<tr>
                <td><strong>${escapeHtml(f.titre)}</strong></td>
                <td><span class="rhf-badge rhf-badge-${typeCls}">${escapeHtml(TYPE_LABELS[f.type_formation] || f.type_formation)}</span></td>
                <td>${escapeHtml(f.formateur || '-')}</td>
                <td>${dates}</td>
                <td>${f.duree_heures ? f.duree_heures + 'h' : '-'}</td>
                <td>${partHtml}</td>
                <td>${parseInt(f.is_obligatoire) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-muted"></i>'}</td>
                <td><span class="rhf-badge rhf-badge-${statutCls}">${escapeHtml(STATUT_LABELS[f.statut] || f.statut)}</span></td>
                <td><div class="d-flex gap-1">
                    <button class="rhf-row-btn" data-detail="${f.id}" title="Détail"><i class="bi bi-eye"></i></button>
                    <button class="rhf-row-btn" data-edit="${f.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="rhf-row-btn danger" data-del="${f.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`;
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    document.getElementById('rhfFilterStatut')?.addEventListener('change', loadFormations);

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
            const participants = r.participants || [];
            document.getElementById('rhfDetailTitle').textContent = f.titre;

            const field = (label, val) => `<div class="mb-2"><div class="rhf-detail-label">${label}</div><div class="rhf-detail-val">${val}</div></div>`;
            let html = '<div class="row g-3">';
            html += `<div class="col-md-4">${field('Type', '<span class="rhf-badge rhf-badge-'+(f.type_formation||'interne')+'">'+escapeHtml(TYPE_LABELS[f.type_formation]||f.type_formation)+'</span>')}</div>`;
            html += `<div class="col-md-4">${field('Statut', '<span class="rhf-badge rhf-badge-'+(f.statut||'planifiee')+'">'+escapeHtml(STATUT_LABELS[f.statut]||f.statut)+'</span>')}</div>`;
            html += `<div class="col-md-4">${field('Obligatoire', parseInt(f.is_obligatoire) ? '<i class="bi bi-check-circle-fill text-success"></i> Oui' : 'Non')}</div>`;
            html += `<div class="col-md-4">${field('Formateur', escapeHtml(f.formateur || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Lieu', escapeHtml(f.lieu || '-'))}</div>`;
            html += `<div class="col-md-4">${field('Durée', f.duree_heures ? f.duree_heures + 'h' : '-')}</div>`;
            const dates = f.date_debut ? formatDate(f.date_debut) + (f.date_fin && f.date_fin !== f.date_debut ? ' - ' + formatDate(f.date_fin) : '') : '-';
            html += `<div class="col-md-6">${field('Dates', dates)}</div>`;
            html += `<div class="col-md-6">${field('Max participants', f.max_participants || 'Illimité')}</div>`;
            if (f.description) html += `<div class="col-12">${field('Description', '<div style="white-space:pre-wrap;background:var(--cl-bg);padding:10px;border-radius:8px;font-size:.85rem">'+escapeHtml(f.description)+'</div>')}</div>`;
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
                html += '<table class="rhf-participant-table"><thead><tr><th>Nom</th><th>Fonction</th><th>Statut</th><th style="width:100px">Actions</th></tr></thead><tbody>';
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
                html += '</tbody></table>';
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

    loadFormations();
})();
</script>
