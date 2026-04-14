<?php
// SSR stats
$ssrTotal = (int) Db::getOne("SELECT COUNT(*) FROM stagiaires");
$ssrActif = (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'actif'");
$ssrPrevu = (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'prevu'");
$ssrTermine = (int) Db::getOne("SELECT COUNT(*) FROM stagiaires WHERE statut = 'termine'");
$ssrReportsPending = (int) Db::getOne("SELECT COUNT(*) FROM stagiaire_reports WHERE statut = 'soumis'");
?>
<style>
.stg-stat-filter { cursor: pointer; transition: all .15s; border: 2px solid transparent; }
.stg-stat-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.stg-stat-filter.active { border-color: var(--cl-primary, #7B6B5B); background: var(--cl-bg, #F7F5F2); }
.stg-badge { font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; }
.stg-badge-prevu     { background: #B8C9D4; color: #3B4F6B; }
.stg-badge-actif     { background: #bcd2cb; color: #2d4a43; }
.stg-badge-termine   { background: #D4C4A8; color: #6B5B3E; }
.stg-badge-interrompu{ background: #E2B8AE; color: #7B3B2C; }
.stg-type-badge { font-size: .7rem; padding: 2px 8px; border-radius: 10px; background: #D0C4D8; color: #5B4B6B; font-weight: 600; }
.stg-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--cl-bg); display: inline-flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: .75rem; font-weight: 600; overflow: hidden; }
.stg-avatar img { width: 100%; height: 100%; object-fit: cover; }
.stg-pending-banner { background: linear-gradient(135deg, #fdf4e6 0%, #f6e8cd 100%); border-left: 4px solid #c99a3e; padding: 12px 16px; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; font-size: .9rem; }
#stgBody .table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); }
#stgBody .table td { vertical-align: middle; font-size: .88rem; }
.stg-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .9rem; transition: all .12s; display: inline-flex; align-items: center; justify-content: center; }
.stg-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.stg-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }
.stg-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.stg-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-badge"></i> Stagiaires & stages</h4>
  <button class="btn btn-sm btn-primary" id="btnAddStagiaire"><i class="bi bi-plus-lg"></i> Nouveau stagiaire</button>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card stg-stat-filter" data-filter-statut="">
      <div class="stat-icon bg-teal"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value" id="stgStatTotal"><?= $ssrTotal ?></div>
        <div class="stat-label">Total</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card stg-stat-filter" data-filter-statut="actif">
      <div class="stat-icon bg-green"><i class="bi bi-play-circle"></i></div>
      <div>
        <div class="stat-value" id="stgStatActif"><?= $ssrActif ?></div>
        <div class="stat-label">Actifs</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card stg-stat-filter" data-filter-statut="prevu">
      <div class="stat-icon bg-orange"><i class="bi bi-calendar-event"></i></div>
      <div>
        <div class="stat-value" id="stgStatPrevu"><?= $ssrPrevu ?></div>
        <div class="stat-label">Prévus</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card stg-stat-filter" data-filter-statut="termine">
      <div class="stat-icon bg-teal"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="stat-value" id="stgStatTermine"><?= $ssrTermine ?></div>
        <div class="stat-label">Terminés</div>
      </div>
    </div>
  </div>
</div>

<?php if ($ssrReportsPending > 0): ?>
<div class="stg-pending-banner">
  <i class="bi bi-bell-fill"></i>
  <div><strong><?= $ssrReportsPending ?></strong> report(s) en attente de validation formateur</div>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body p-0">
    <div id="stgBody"><div class="stg-empty"><i class="bi bi-hourglass"></i>Chargement…</div></div>
  </div>
</div>

<!-- Modal création/édition stagiaire -->
<div class="modal fade" id="stgModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stgModalTitle">Nouveau stagiaire</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="stgId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Utilisateur (stagiaire) *</label>
            <select class="form-select form-select-sm" id="stgUserId" required></select>
            <small class="text-muted">Ne voit pas de compte ? Créez-le dans Utilisateurs d'abord.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Type de stage *</label>
            <select class="form-select form-select-sm" id="stgType">
              <option value="decouverte">Découverte</option>
              <option value="cfc_asa">CFC ASA</option>
              <option value="cfc_ase">CFC ASE</option>
              <option value="cfc_asfm">CFC ASFM</option>
              <option value="bachelor_inf">Bachelor infirmier·ère</option>
              <option value="civiliste">Civiliste</option>
              <option value="autre">Autre</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Établissement d'origine</label>
            <input type="text" class="form-control form-control-sm" id="stgEtab" placeholder="Ex. École ARPIH, HEdS-FR...">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Niveau / année</label>
            <input type="text" class="form-control form-control-sm" id="stgNiveau" placeholder="Ex. 2e année">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Date début *</label>
            <input type="date" class="form-control form-control-sm" id="stgDateDebut" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Date fin *</label>
            <input type="date" class="form-control form-control-sm" id="stgDateFin" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Étage</label>
            <select class="form-select form-select-sm" id="stgEtage"><option value="">—</option></select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Statut</label>
            <select class="form-select form-select-sm" id="stgStatut">
              <option value="prevu">Prévu</option>
              <option value="actif">Actif</option>
              <option value="termine">Terminé</option>
              <option value="interrompu">Interrompu</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">RUV responsable</label>
            <select class="form-select form-select-sm" id="stgRuv"><option value="">—</option></select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Formateur principal</label>
            <select class="form-select form-select-sm" id="stgFormateur"><option value="">—</option></select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Objectifs généraux</label>
            <textarea class="form-control form-control-sm" id="stgObjectifs" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Notes RUV (privées)</label>
            <textarea class="form-control form-control-sm" id="stgNotes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm btn-primary" id="btnSaveStagiaire">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal détail stagiaire -->
<div class="modal fade" id="stgDetailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stgDetailTitle">Profil stagiaire</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="stgDetailBody"></div>
    </div>
  </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
(() => {
    let refs = { formateurs: [], etages: [], ruvs: [] };
    let stagiairesData = [];
    let currentDetailId = null;
    const TYPE_LABELS = { decouverte:'Découverte', cfc_asa:'CFC ASA', cfc_ase:'CFC ASE', cfc_asfm:'CFC ASFM', bachelor_inf:'Bachelor inf.', civiliste:'Civiliste', autre:'Autre' };
    const STATUT_LABELS = { prevu:'Prévu', actif:'Actif', termine:'Terminé', interrompu:'Interrompu' };

    function loadRefs() {
        return adminApiPost('admin_get_stagiaires_refs', {}).then(r => {
            if (!r.success) return;
            refs = r;
            // Users not yet stagiaires + existing stagiaires for reassign: we'll fetch users list from formateurs for now
            const userSel = document.getElementById('stgUserId');
            userSel.innerHTML = '<option value="">— Sélectionner —</option>' +
                refs.formateurs.map(u => `<option value="${u.id}">${escapeHtml(u.prenom + ' ' + u.nom)}</option>`).join('');
            document.getElementById('stgEtage').innerHTML = '<option value="">—</option>' +
                refs.etages.map(e => `<option value="${e.id}">${escapeHtml(e.nom)}</option>`).join('');
            document.getElementById('stgRuv').innerHTML = '<option value="">—</option>' +
                refs.ruvs.map(u => `<option value="${u.id}">${escapeHtml(u.prenom + ' ' + u.nom)}</option>`).join('');
            document.getElementById('stgFormateur').innerHTML = '<option value="">—</option>' +
                refs.formateurs.map(u => `<option value="${u.id}">${escapeHtml(u.prenom + ' ' + u.nom)}</option>`).join('');
        });
    }

    function load() {
        const statut = document.querySelector('.stg-stat-filter.active')?.dataset.filterStatut || '';
        adminApiPost('admin_get_stagiaires', { statut }).then(r => {
            if (!r.success) return;
            stagiairesData = r.stagiaires || [];
            if (r.stats) {
                document.getElementById('stgStatTotal').textContent = r.stats.total || 0;
                document.getElementById('stgStatActif').textContent = r.stats.actif || 0;
                document.getElementById('stgStatPrevu').textContent = r.stats.prevu || 0;
                document.getElementById('stgStatTermine').textContent = r.stats.termine || 0;
            }
            render();
        });
    }

    function render() {
        const el = document.getElementById('stgBody');
        if (!stagiairesData.length) {
            el.innerHTML = '<div class="stg-empty"><i class="bi bi-person-badge"></i>Aucun stagiaire</div>';
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>' +
            '<th></th><th>Stagiaire</th><th>Type</th><th>Période</th><th>Étage</th><th>Formateur</th><th>RUV</th><th>Reports</th><th>Statut</th><th></th>' +
            '</tr></thead><tbody>';
        stagiairesData.forEach(s => {
            const initials = (s.prenom?.[0] || '') + (s.nom?.[0] || '');
            const avatar = s.photo
                ? `<div class="stg-avatar"><img src="${escapeHtml(s.photo)}" alt=""></div>`
                : `<div class="stg-avatar">${escapeHtml(initials.toUpperCase())}</div>`;
            const pending = parseInt(s.reports_a_valider) || 0;
            const reportsBadge = pending > 0
                ? `<span class="badge bg-warning text-dark">${pending} à valider</span>`
                : `<span class="text-muted small">${s.reports_total || 0} reports</span>`;
            html += `<tr data-id="${s.id}">
                <td>${avatar}</td>
                <td><strong>${escapeHtml(s.prenom + ' ' + s.nom)}</strong><br><small class="text-muted">${escapeHtml(s.email || '')}</small></td>
                <td><span class="stg-type-badge">${escapeHtml(TYPE_LABELS[s.type] || s.type)}</span></td>
                <td><small>${formatDate(s.date_debut)} → ${formatDate(s.date_fin)}</small></td>
                <td>${escapeHtml(s.etage_nom || '—')}</td>
                <td>${escapeHtml(s.formateur_nom || '—')}</td>
                <td>${escapeHtml(s.ruv_nom || '—')}</td>
                <td>${reportsBadge}</td>
                <td><span class="stg-badge stg-badge-${s.statut}">${escapeHtml(STATUT_LABELS[s.statut] || s.statut)}</span></td>
                <td class="text-end">
                    <button class="stg-row-btn" data-detail="${s.id}" title="Détail"><i class="bi bi-eye"></i></button>
                    <button class="stg-row-btn" data-edit="${s.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="stg-row-btn danger" data-del="${s.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        el.innerHTML = html;
    }

    function openModal(id) {
        const s = id ? stagiairesData.find(x => x.id === id) : null;
        document.getElementById('stgId').value = s?.id || '';
        document.getElementById('stgModalTitle').textContent = s ? 'Modifier stagiaire' : 'Nouveau stagiaire';
        document.getElementById('stgUserId').value = s?.user_id || '';
        document.getElementById('stgType').value = s?.type || 'autre';
        document.getElementById('stgEtab').value = s?.etablissement_origine || '';
        document.getElementById('stgNiveau').value = s?.niveau || '';
        document.getElementById('stgDateDebut').value = s?.date_debut || '';
        document.getElementById('stgDateFin').value = s?.date_fin || '';
        document.getElementById('stgEtage').value = s?.etage_id || '';
        document.getElementById('stgStatut').value = s?.statut || 'prevu';
        document.getElementById('stgRuv').value = s?.ruv_id || '';
        document.getElementById('stgFormateur').value = s?.formateur_principal_id || '';
        document.getElementById('stgObjectifs').value = s?.objectifs_generaux || '';
        document.getElementById('stgNotes').value = s?.notes_ruv || '';
        new bootstrap.Modal(document.getElementById('stgModal')).show();
    }

    function save() {
        const data = {
            id: document.getElementById('stgId').value,
            user_id: document.getElementById('stgUserId').value,
            type: document.getElementById('stgType').value,
            etablissement_origine: document.getElementById('stgEtab').value,
            niveau: document.getElementById('stgNiveau').value,
            date_debut: document.getElementById('stgDateDebut').value,
            date_fin: document.getElementById('stgDateFin').value,
            etage_id: document.getElementById('stgEtage').value || null,
            statut: document.getElementById('stgStatut').value,
            ruv_id: document.getElementById('stgRuv').value || null,
            formateur_principal_id: document.getElementById('stgFormateur').value || null,
            objectifs_generaux: document.getElementById('stgObjectifs').value,
            notes_ruv: document.getElementById('stgNotes').value,
        };
        adminApiPost('admin_save_stagiaire', data).then(r => {
            if (r.success) {
                showToast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('stgModal'))?.hide();
                load();
            } else {
                showToast(r.error || 'Erreur', 'error');
            }
        });
    }

    function del(id) {
        if (!confirm('Supprimer ce stagiaire et toutes ses données (reports, évaluations) ?')) return;
        adminApiPost('admin_delete_stagiaire', { id }).then(r => {
            if (r.success) { showToast(r.message, 'success'); load(); }
        });
    }

    function openDetail(id) {
        currentDetailId = id;
        adminApiPost('admin_get_stagiaire_detail', { id }).then(r => {
            if (!r.success) return;
            renderDetail(r);
            new bootstrap.Modal(document.getElementById('stgDetailModal')).show();
        });
    }

    function renderDetail(d) {
        const s = d.stagiaire;
        const m = d.moyennes || {};
        document.getElementById('stgDetailTitle').textContent = `${s.prenom} ${s.nom} — Profil stagiaire`;
        const avg = (v) => v ? Number(v).toFixed(1) + '/5' : '—';
        let html = `
            <ul class="nav nav-tabs mb-3" id="stgDetailTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#stgTabInfo">Infos</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stgTabAff">Formateurs (${d.affectations.length})</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stgTabReports">Reports (${d.reports.length})</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stgTabEvals">Évaluations (${d.evaluations.length})</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stgTabObj">Objectifs (${d.objectifs.length})</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="stgTabInfo">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="small text-muted">Type</div><div>${escapeHtml(TYPE_LABELS[s.type] || s.type)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Statut</div><span class="stg-badge stg-badge-${s.statut}">${STATUT_LABELS[s.statut]}</span></div>
                        <div class="col-md-6"><div class="small text-muted">Établissement</div><div>${escapeHtml(s.etablissement_origine || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Niveau</div><div>${escapeHtml(s.niveau || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Période</div><div>${formatDate(s.date_debut)} → ${formatDate(s.date_fin)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Étage</div><div>${escapeHtml(s.etage_nom || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">RUV</div><div>${escapeHtml(s.ruv_nom || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Formateur principal</div><div>${escapeHtml(s.formateur_nom || '—')}</div></div>
                        <div class="col-12"><div class="small text-muted">Objectifs généraux</div><div>${escapeHtml(s.objectifs_generaux || '—')}</div></div>
                        <div class="col-12"><div class="small text-muted">Notes RUV</div><div style="white-space:pre-wrap">${escapeHtml(s.notes_ruv || '—')}</div></div>
                    </div>
                    <hr>
                    <h6>Moyennes évaluations <small class="text-muted">(${m.n || 0} évaluations)</small></h6>
                    <div class="row g-2 small">
                        <div class="col-md-4">Initiative: <strong>${avg(m.initiative)}</strong></div>
                        <div class="col-md-4">Communication: <strong>${avg(m.communication)}</strong></div>
                        <div class="col-md-4">Connaissances: <strong>${avg(m.connaissances)}</strong></div>
                        <div class="col-md-4">Autonomie: <strong>${avg(m.autonomie)}</strong></div>
                        <div class="col-md-4">Savoir-être: <strong>${avg(m.savoir_etre)}</strong></div>
                        <div class="col-md-4">Ponctualité: <strong>${avg(m.ponctualite)}</strong></div>
                    </div>
                </div>
                <div class="tab-pane fade" id="stgTabAff">
                    <button class="btn btn-sm btn-primary mb-3" id="btnAddAff"><i class="bi bi-plus-lg"></i> Nouvelle affectation</button>
                    <div id="stgAffList"></div>
                </div>
                <div class="tab-pane fade" id="stgTabReports">
                    <div id="stgReportsList"></div>
                </div>
                <div class="tab-pane fade" id="stgTabEvals">
                    <div id="stgEvalsList"></div>
                </div>
                <div class="tab-pane fade" id="stgTabObj">
                    <button class="btn btn-sm btn-primary mb-3" id="btnAddObj"><i class="bi bi-plus-lg"></i> Nouvel objectif</button>
                    <div id="stgObjList"></div>
                </div>
            </div>
        `;
        document.getElementById('stgDetailBody').innerHTML = html;
        renderAffectations(d.affectations);
        renderReports(d.reports);
        renderEvaluations(d.evaluations);
        renderObjectifs(d.objectifs);
    }

    function renderAffectations(list) {
        const el = document.getElementById('stgAffList');
        if (!list.length) { el.innerHTML = '<div class="text-muted small">Aucune affectation</div>'; return; }
        el.innerHTML = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Formateur</th><th>Étage</th><th>Période</th><th>Rôle</th><th></th></tr></thead><tbody>' +
            list.map(a => `<tr>
                <td>${escapeHtml(a.formateur_prenom + ' ' + a.formateur_nom)}</td>
                <td>${escapeHtml(a.etage_nom || '—')}</td>
                <td>${formatDate(a.date_debut)} → ${formatDate(a.date_fin)}</td>
                <td><span class="badge bg-secondary">${a.role_formateur}</span></td>
                <td><button class="stg-row-btn danger" data-del-aff="${a.id}"><i class="bi bi-trash"></i></button></td>
            </tr>`).join('') + '</tbody></table></div>';
    }

    function renderReports(list) {
        const el = document.getElementById('stgReportsList');
        if (!list.length) { el.innerHTML = '<div class="text-muted small">Aucun report</div>'; return; }
        el.innerHTML = list.map(r => {
            const badgeCls = {brouillon:'bg-secondary', soumis:'bg-warning text-dark', valide:'bg-success', a_refaire:'bg-danger'}[r.statut] || 'bg-secondary';
            return `<div class="card mb-2"><div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>${formatDate(r.date_report)}</strong>
                        <span class="badge bg-light text-dark ms-2">${r.type}</span>
                        <span class="badge ${badgeCls} ms-1">${r.statut}</span>
                    </div>
                    ${r.statut === 'soumis' ? `
                        <div>
                            <button class="btn btn-sm btn-success" data-validate="${r.id}"><i class="bi bi-check"></i> Valider</button>
                            <button class="btn btn-sm btn-outline-danger" data-refuse="${r.id}"><i class="bi bi-x"></i> À refaire</button>
                        </div>` : ''}
                </div>
                ${r.titre ? `<div class="fw-semibold small mb-1">${escapeHtml(r.titre)}</div>` : ''}
                <div class="small" style="white-space:pre-wrap">${escapeHtml(r.contenu || '')}</div>
                ${r.commentaire_formateur ? `<div class="alert alert-info mt-2 mb-0 small"><strong>Commentaire:</strong> ${escapeHtml(r.commentaire_formateur)}${r.valideur_nom ? ` — ${escapeHtml(r.valideur_nom)}` : ''}</div>` : ''}
            </div></div>`;
        }).join('');
    }

    function renderEvaluations(list) {
        const el = document.getElementById('stgEvalsList');
        if (!list.length) { el.innerHTML = '<div class="text-muted small">Aucune évaluation</div>'; return; }
        el.innerHTML = list.map(e => `<div class="card mb-2"><div class="card-body p-3">
            <div class="d-flex justify-content-between mb-2">
                <div><strong>${formatDate(e.date_eval)}</strong>
                    <span class="badge bg-light text-dark ms-2">${e.periode}</span>
                    <span class="text-muted small ms-2">par ${escapeHtml(e.formateur_prenom + ' ' + e.formateur_nom)}</span></div>
            </div>
            <div class="row g-2 small mb-2">
                <div class="col-md-4">Initiative: <strong>${e.note_initiative || '—'}/5</strong></div>
                <div class="col-md-4">Communication: <strong>${e.note_communication || '—'}/5</strong></div>
                <div class="col-md-4">Connaissances: <strong>${e.note_connaissances || '—'}/5</strong></div>
                <div class="col-md-4">Autonomie: <strong>${e.note_autonomie || '—'}/5</strong></div>
                <div class="col-md-4">Savoir-être: <strong>${e.note_savoir_etre || '—'}/5</strong></div>
                <div class="col-md-4">Ponctualité: <strong>${e.note_ponctualite || '—'}/5</strong></div>
            </div>
            ${e.points_forts ? `<div class="small"><strong>Points forts:</strong> ${escapeHtml(e.points_forts)}</div>` : ''}
            ${e.points_amelioration ? `<div class="small"><strong>À améliorer:</strong> ${escapeHtml(e.points_amelioration)}</div>` : ''}
            ${e.commentaire_general ? `<div class="small mt-1" style="white-space:pre-wrap">${escapeHtml(e.commentaire_general)}</div>` : ''}
        </div></div>`).join('');
    }

    function renderObjectifs(list) {
        const el = document.getElementById('stgObjList');
        if (!list.length) { el.innerHTML = '<div class="text-muted small">Aucun objectif défini</div>'; return; }
        el.innerHTML = list.map(o => {
            const cls = {en_cours:'bg-primary', atteint:'bg-success', non_atteint:'bg-danger', abandonne:'bg-secondary'}[o.statut] || 'bg-secondary';
            return `<div class="card mb-2"><div class="card-body p-3">
                <div class="d-flex justify-content-between">
                    <div><strong>${escapeHtml(o.titre)}</strong> <span class="badge ${cls} ms-2">${o.statut}</span>
                        ${o.date_cible ? `<span class="text-muted small ms-2">Cible: ${formatDate(o.date_cible)}</span>` : ''}
                    </div>
                    <button class="stg-row-btn danger" data-del-obj="${o.id}"><i class="bi bi-trash"></i></button>
                </div>
                ${o.description ? `<div class="small mt-1">${escapeHtml(o.description)}</div>` : ''}
                ${o.commentaire_ruv ? `<div class="alert alert-secondary mt-2 mb-0 small">${escapeHtml(o.commentaire_ruv)}</div>` : ''}
            </div></div>`;
        }).join('');
    }

    // ─── Event bindings ───
    document.querySelectorAll('.stg-stat-filter').forEach(card => {
        card.addEventListener('click', () => {
            const wasActive = card.classList.contains('active');
            document.querySelectorAll('.stg-stat-filter').forEach(c => c.classList.remove('active'));
            if (!wasActive && card.dataset.filterStatut) card.classList.add('active');
            load();
        });
    });

    document.getElementById('btnAddStagiaire')?.addEventListener('click', () => openModal(null));
    document.getElementById('btnSaveStagiaire')?.addEventListener('click', save);

    document.getElementById('stgBody')?.addEventListener('click', e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.dataset.detail) openDetail(btn.dataset.detail);
        else if (btn.dataset.edit) openModal(btn.dataset.edit);
        else if (btn.dataset.del) del(btn.dataset.del);
    });

    document.getElementById('stgDetailBody')?.addEventListener('click', e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.dataset.validate) {
            adminApiPost('admin_validate_stagiaire_report', { id: btn.dataset.validate, statut: 'valide' }).then(r => {
                if (r.success) { showToast('Report validé', 'success'); openDetail(currentDetailId); load(); }
            });
        } else if (btn.dataset.refuse) {
            const c = prompt('Commentaire (obligatoire pour refus):');
            if (!c) return;
            adminApiPost('admin_validate_stagiaire_report', { id: btn.dataset.refuse, statut: 'a_refaire', commentaire: c }).then(r => {
                if (r.success) { showToast('Report à refaire', 'success'); openDetail(currentDetailId); load(); }
            });
        } else if (btn.dataset.delAff) {
            if (!confirm('Supprimer cette affectation ?')) return;
            adminApiPost('admin_delete_stagiaire_affectation', { id: btn.dataset.delAff }).then(r => {
                if (r.success) { showToast(r.message, 'success'); openDetail(currentDetailId); }
            });
        } else if (btn.dataset.delObj) {
            if (!confirm('Supprimer cet objectif ?')) return;
            adminApiPost('admin_delete_stagiaire_objectif', { id: btn.dataset.delObj }).then(r => {
                if (r.success) { showToast(r.message, 'success'); openDetail(currentDetailId); }
            });
        } else if (btn.id === 'btnAddAff') {
            const formId = prompt('ID formateur (à remplacer par un modal plus tard):');
            if (!formId) return;
            const dd = prompt('Date début (YYYY-MM-DD):');
            const df = prompt('Date fin (YYYY-MM-DD):');
            if (!dd || !df) return;
            adminApiPost('admin_add_stagiaire_affectation', { stagiaire_id: currentDetailId, formateur_id: formId, date_debut: dd, date_fin: df, role_formateur: 'ponctuel' }).then(r => {
                if (r.success) { showToast(r.message, 'success'); openDetail(currentDetailId); }
            });
        } else if (btn.id === 'btnAddObj') {
            const titre = prompt('Titre objectif:');
            if (!titre) return;
            adminApiPost('admin_save_stagiaire_objectif', { stagiaire_id: currentDetailId, titre }).then(r => {
                if (r.success) { showToast(r.message, 'success'); openDetail(currentDetailId); }
            });
        }
    });

    loadRefs().then(load);
})();
</script>
