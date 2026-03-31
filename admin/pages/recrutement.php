<?php
// ─── Pre-fetch offres for select filters ─────────────────────────────────────
$ssrOffres = Db::fetchAll("SELECT id, titre FROM offres_emploi ORDER BY created_at DESC");
?>
<style>
/* ── Tables ── */
.recr-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.recr-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; }
.recr-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.recr-table tr:hover td { background: var(--cl-bg); }

/* ── Badges ── */
.recr-badge { font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; display: inline-block; }
.recr-badge-recue     { background: #D4C4A8; color: #6B5B3E; }
.recr-badge-en_cours  { background: #B8C9D4; color: #3B4F6B; }
.recr-badge-entretien { background: #C4C8D4; color: #3B3F6B; }
.recr-badge-acceptee  { background: #bcd2cb; color: #2d4a43; }
.recr-badge-refusee   { background: #E2B8AE; color: #7B3B2C; }
.recr-badge-archivee  { background: var(--cl-border-light, #E8E4DE); color: var(--cl-text-muted); }

.recr-count-badge { background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); font-size: .72rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.recr-active   { background: #bcd2cb; color: #2d4a43; }
.recr-inactive { background: #E2B8AE; color: #7B3B2C; }

/* ── Action buttons ── */
.recr-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .88rem; transition: all .12s; display: flex; align-items: center; justify-content: center; }
.recr-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.recr-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

/* ── Detail sections ── */
.recr-detail-section { margin-bottom: 16px; }
.recr-detail-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin-bottom: 4px; }
.recr-detail-val { font-size: .88rem; }
.recr-doc-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.recr-doc-item:last-child { border: none; }

/* ── Empty ── */
.recr-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.recr-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-megaphone"></i> Recrutement</h4>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="recrTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#recrPane-offres">
      <i class="bi bi-briefcase"></i> Offres d'emploi
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#recrPane-cand">
      <i class="bi bi-person-lines-fill"></i> Candidatures
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ═══ TAB 1: Offres d'emploi ═══ -->
  <div class="tab-pane fade show active" id="recrPane-offres">
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="ms-auto"></div>
      <button class="btn btn-sm btn-primary" id="btnAddOffre"><i class="bi bi-plus-lg"></i> Ajouter une offre</button>
    </div>
    <div id="offresBody">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>

  <!-- ═══ TAB 2: Candidatures ═══ -->
  <div class="tab-pane fade" id="recrPane-cand">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <select class="form-select form-select-sm" id="candOffreFilter" style="max-width:220px">
        <option value="">Toutes les offres</option>
        <?php foreach ($ssrOffres as $o): ?>
          <option value="<?= h($o['id']) ?>"><?= h($o['titre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm" id="candStatutFilter" style="max-width:160px">
        <option value="">Tous les statuts</option>
        <option value="recue">Reçue</option>
        <option value="en_cours">En cours</option>
        <option value="entretien">Entretien</option>
        <option value="acceptee">Acceptée</option>
        <option value="refusee">Refusée</option>
        <option value="archivee">Archivée</option>
      </select>
      <input type="text" class="form-control form-control-sm" id="candSearch" placeholder="Rechercher..." style="max-width:200px">
    </div>
    <div id="candBody">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>

</div>

<!-- ═══ Modal: Offre ═══ -->
<div class="modal fade" id="offreModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="offreModalTitle">Nouvelle offre</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="offreId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Titre *</label>
            <input type="text" class="form-control form-control-sm" id="offreTitre" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Département</label>
            <select class="form-select form-select-sm" id="offreDept">
              <option value="">--</option>
              <option value="Soins">Soins</option>
              <option value="Cuisine">Cuisine</option>
              <option value="Animation">Animation</option>
              <option value="Administration">Administration</option>
              <option value="Hôtellerie">Hôtellerie</option>
              <option value="Technique">Technique</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Type de contrat</label>
            <select class="form-select form-select-sm" id="offreContrat">
              <option value="CDI">CDI</option>
              <option value="CDD">CDD</option>
              <option value="Stage">Stage</option>
              <option value="Civiliste">Civiliste</option>
              <option value="Temporaire">Temporaire</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Taux d'activité</label>
            <input type="text" class="form-control form-control-sm" id="offreTaux" placeholder="ex: 80-100%">
          </div>
          <div class="col-md-4">
            <label class="form-label">Lieu</label>
            <input type="text" class="form-control form-control-sm" id="offreLieu" value="Genève">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date de début</label>
            <input type="date" class="form-control form-control-sm" id="offreDateDebut">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date limite</label>
            <input type="date" class="form-control form-control-sm" id="offreDateLimite">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control form-control-sm" id="offreDesc" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Exigences</label>
            <textarea class="form-control form-control-sm" id="offreExigences" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Avantages</label>
            <textarea class="form-control form-control-sm" id="offreAvantages" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Indication salariale</label>
            <input type="text" class="form-control form-control-sm" id="offreSalaire" placeholder="ex: Selon CCT">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email de contact</label>
            <input type="email" class="form-control form-control-sm" id="offreEmail">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnSaveOffre"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Confirm delete offre ═══ -->
<div class="modal fade" id="deleteOffreModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Supprimer l'offre ?</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="mb-0 small text-muted">Cette action est irréversible (ou désactivation si candidatures existantes).</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDeleteOffre"><i class="bi bi-trash"></i> Supprimer</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Modal: Candidature detail ═══ -->
<div class="modal fade" id="candDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="candDetailTitle">Candidature</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="candDetailBody">
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

<!-- ═══ Modal: Confirm delete candidature ═══ -->
<div class="modal fade" id="deleteCandModal" tabindex="-1">
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
    const STATUT_CLASSES = { recue:'recue', en_cours:'en_cours', entretien:'entretien', acceptee:'acceptee', refusee:'refusee', archivee:'archivee' };

    let offresData = [];
    let candData = [];
    let currentCandId = null;
    let deleteOffreId = null;

    // ═══════════════════════════════════════
    // TAB 1: Offres d'emploi
    // ═══════════════════════════════════════

    function loadOffres() {
        adminApiPost('admin_get_offres', {}).then(r => {
            if (!r.success) return;
            offresData = r.offres || [];
            renderOffres();
        });
    }

    function renderOffres() {
        const el = document.getElementById('offresBody');
        if (!el) return;
        if (!offresData.length) {
            el.innerHTML = '<div class="recr-empty"><i class="bi bi-briefcase"></i>Aucune offre d\'emploi</div>';
            return;
        }
        let html = '<table class="recr-table"><thead><tr><th>Titre</th><th>Département</th><th>Contrat</th><th>Taux</th><th>Date limite</th><th>Candidatures</th><th>Actif</th><th style="width:80px">Actions</th></tr></thead><tbody>';
        offresData.forEach(o => {
            const dateLim = o.date_limite ? formatDate(o.date_limite) : '-';
            const isActive = parseInt(o.is_active);
            html += `<tr>
                <td><strong>${escapeHtml(o.titre)}</strong></td>
                <td>${escapeHtml(o.departement || '-')}</td>
                <td>${escapeHtml(o.type_contrat || '-')}</td>
                <td>${escapeHtml(o.taux_activite || '-')}</td>
                <td>${dateLim}</td>
                <td><span class="recr-count-badge">${parseInt(o.nb_candidatures || 0)}</span></td>
                <td><button class="recr-badge ${isActive ? 'recr-active' : 'recr-inactive'}" data-toggle-offre="${o.id}" data-active="${isActive}" style="border:none;cursor:pointer">${isActive ? 'Actif' : 'Inactif'}</button></td>
                <td><div class="d-flex gap-1">
                    <button class="recr-row-btn" data-edit-offre="${o.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="recr-row-btn danger" data-delete-offre="${o.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`;
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    document.getElementById('offresBody')?.addEventListener('click', e => {
        const toggleBtn = e.target.closest('[data-toggle-offre]');
        if (toggleBtn) {
            const id = toggleBtn.dataset.toggleOffre;
            const newVal = parseInt(toggleBtn.dataset.active) ? 0 : 1;
            adminApiPost('admin_update_offre', { id, is_active: newVal }).then(r => {
                if (r.success) { showToast('Statut mis à jour', 'success'); loadOffres(); }
            });
            return;
        }
        const editBtn = e.target.closest('[data-edit-offre]');
        if (editBtn) { openOffreModal(editBtn.dataset.editOffre); return; }
        const delBtn = e.target.closest('[data-delete-offre]');
        if (delBtn) {
            deleteOffreId = delBtn.dataset.deleteOffre;
            new bootstrap.Modal(document.getElementById('deleteOffreModal')).show();
        }
    });

    document.getElementById('btnAddOffre')?.addEventListener('click', () => openOffreModal(null));

    function openOffreModal(id) {
        document.getElementById('offreId').value = '';
        document.getElementById('offreModalTitle').textContent = id ? 'Modifier l\'offre' : 'Nouvelle offre';
        ['offreTitre','offreDesc','offreExigences','offreAvantages','offreSalaire','offreEmail'].forEach(f => document.getElementById(f).value = '');
        document.getElementById('offreDept').value = '';
        document.getElementById('offreContrat').value = 'CDI';
        document.getElementById('offreTaux').value = '';
        document.getElementById('offreLieu').value = 'Genève';
        document.getElementById('offreDateDebut').value = '';
        document.getElementById('offreDateLimite').value = '';

        if (id) {
            const o = offresData.find(x => x.id === id);
            if (o) {
                document.getElementById('offreId').value = o.id;
                document.getElementById('offreTitre').value = o.titre || '';
                document.getElementById('offreDesc').value = o.description || '';
                document.getElementById('offreDept').value = o.departement || '';
                document.getElementById('offreContrat').value = o.type_contrat || 'CDI';
                document.getElementById('offreTaux').value = o.taux_activite || '';
                document.getElementById('offreLieu').value = o.lieu || '';
                document.getElementById('offreDateDebut').value = o.date_debut || '';
                document.getElementById('offreDateLimite').value = o.date_limite || '';
                document.getElementById('offreExigences').value = o.exigences || '';
                document.getElementById('offreAvantages').value = o.avantages || '';
                document.getElementById('offreSalaire').value = o.salaire_indication || '';
                document.getElementById('offreEmail').value = o.contact_email || '';
            }
        }
        new bootstrap.Modal(document.getElementById('offreModal')).show();
    }

    document.getElementById('btnSaveOffre')?.addEventListener('click', () => {
        const id = document.getElementById('offreId').value;
        const data = {
            titre: document.getElementById('offreTitre').value.trim(),
            description: document.getElementById('offreDesc').value.trim(),
            departement: document.getElementById('offreDept').value,
            type_contrat: document.getElementById('offreContrat').value,
            taux_activite: document.getElementById('offreTaux').value.trim(),
            lieu: document.getElementById('offreLieu').value.trim(),
            date_debut: document.getElementById('offreDateDebut').value,
            date_limite: document.getElementById('offreDateLimite').value,
            exigences: document.getElementById('offreExigences').value.trim(),
            avantages: document.getElementById('offreAvantages').value.trim(),
            salaire_indication: document.getElementById('offreSalaire').value.trim(),
            contact_email: document.getElementById('offreEmail').value.trim(),
        };
        if (!data.titre) { showToast('Le titre est requis', 'danger'); return; }

        const action = id ? 'admin_update_offre' : 'admin_create_offre';
        if (id) data.id = id;

        adminApiPost(action, data).then(r => {
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('offreModal'))?.hide();
                showToast(r.message || 'OK', 'success');
                loadOffres();
            } else {
                showToast(r.error || 'Erreur', 'danger');
            }
        });
    });

    document.getElementById('btnConfirmDeleteOffre')?.addEventListener('click', () => {
        if (!deleteOffreId) return;
        adminApiPost('admin_delete_offre', { id: deleteOffreId }).then(r => {
            bootstrap.Modal.getInstance(document.getElementById('deleteOffreModal'))?.hide();
            if (r.success) { showToast(r.message || 'Supprimé', 'success'); loadOffres(); }
            else showToast(r.error || 'Erreur', 'danger');
            deleteOffreId = null;
        });
    });

    // ═══════════════════════════════════════
    // TAB 2: Candidatures
    // ═══════════════════════════════════════

    let candDebounce = null;
    function loadCandidatures() {
        const params = {
            offre_id: document.getElementById('candOffreFilter')?.value || '',
            statut: document.getElementById('candStatutFilter')?.value || '',
            search: document.getElementById('candSearch')?.value.trim() || '',
        };
        adminApiPost('admin_get_candidatures', params).then(r => {
            if (!r.success) return;
            candData = r.candidatures || [];
            renderCandidatures(r.total || 0);
        });
    }

    function renderCandidatures(total) {
        const el = document.getElementById('candBody');
        if (!el) return;
        if (!candData.length) {
            el.innerHTML = '<div class="recr-empty"><i class="bi bi-person-lines-fill"></i>Aucune candidature</div>';
            return;
        }
        let html = '<table class="recr-table"><thead><tr><th>Date</th><th>Nom Prénom</th><th>Offre</th><th>Statut</th><th style="width:60px">Actions</th></tr></thead><tbody>';
        candData.forEach(c => {
            const cls = STATUT_CLASSES[c.statut] || 'archivee';
            html += `<tr>
                <td>${formatDate(c.created_at)}</td>
                <td><strong>${escapeHtml(c.nom || '')} ${escapeHtml(c.prenom || '')}</strong></td>
                <td>${escapeHtml(c.offre_titre || '-')}</td>
                <td><span class="recr-badge recr-badge-${cls}">${escapeHtml(STATUT_LABELS[c.statut] || c.statut)}</span></td>
                <td><button class="recr-row-btn" data-view-cand="${c.id}" title="Voir détail"><i class="bi bi-eye"></i></button></td>
            </tr>`;
        });
        html += '</tbody></table>';
        if (total > candData.length) html += `<p class="text-muted small mt-2">${candData.length} / ${total} résultats affichés</p>`;
        el.innerHTML = html;
    }

    document.getElementById('candOffreFilter')?.addEventListener('change', loadCandidatures);
    document.getElementById('candStatutFilter')?.addEventListener('change', loadCandidatures);
    document.getElementById('candSearch')?.addEventListener('input', () => {
        clearTimeout(candDebounce);
        candDebounce = setTimeout(loadCandidatures, 300);
    });

    document.getElementById('candBody')?.addEventListener('click', e => {
        const btn = e.target.closest('[data-view-cand]');
        if (btn) openCandDetail(btn.dataset.viewCand);
    });

    function openCandDetail(id) {
        currentCandId = id;
        document.getElementById('candDetailBody').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
        new bootstrap.Modal(document.getElementById('candDetailModal')).show();

        adminApiPost('admin_get_candidature_detail', { id }).then(r => {
            if (!r.success) { document.getElementById('candDetailBody').innerHTML = '<p class="text-danger">Erreur</p>'; return; }
            const c = r.candidature;
            document.getElementById('candDetailTitle').textContent = `${c.prenom || ''} ${c.nom || ''} - ${c.offre_titre || 'Candidature'}`;

            let html = '<div class="row g-3">';
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Nom</div><div class="recr-detail-val">${escapeHtml(c.nom || '-')} ${escapeHtml(c.prenom || '')}</div></div></div>`;
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Email</div><div class="recr-detail-val">${c.email ? '<a href="mailto:'+escapeHtml(c.email)+'">'+escapeHtml(c.email)+'</a>' : '-'}</div></div></div>`;
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Téléphone</div><div class="recr-detail-val">${escapeHtml(c.telephone || '-')}</div></div></div>`;
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Offre</div><div class="recr-detail-val">${escapeHtml(c.offre_titre || '-')} <span class="text-muted small">${escapeHtml(c.offre_departement || '')}</span></div></div></div>`;
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Date de candidature</div><div class="recr-detail-val">${formatDate(c.created_at)}</div></div></div>`;
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Disponibilité</div><div class="recr-detail-val">${escapeHtml(c.disponibilite || '-')}</div></div></div>`;

            if (c.message) {
                html += `<div class="col-12"><div class="recr-detail-section"><div class="recr-detail-label">Message</div><div class="recr-detail-val" style="white-space:pre-wrap">${escapeHtml(c.message)}</div></div></div>`;
            }

            // Documents
            if (c.documents && c.documents.length) {
                html += '<div class="col-12"><div class="recr-detail-section"><div class="recr-detail-label">Documents</div>';
                c.documents.forEach(d => {
                    const sizeKb = d.size ? Math.round(d.size / 1024) : '?';
                    html += `<div class="recr-doc-item">
                        <i class="bi bi-file-earmark"></i>
                        <span class="flex-grow-1">${escapeHtml(d.original_name)} <span class="text-muted small">(${sizeKb} Ko)</span></span>
                        <a href="admin/api.php?action=admin_download_candidature_doc&id=${encodeURIComponent(d.id)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                    </div>`;
                });
                html += '</div></div>';
            }

            // Status + notes
            const cls = STATUT_CLASSES[c.statut] || 'archivee';
            html += `<div class="col-md-6"><div class="recr-detail-section"><div class="recr-detail-label">Statut</div>
                <select class="form-select form-select-sm mt-1" id="candStatutSelect">
                    <option value="recue" ${c.statut==='recue'?'selected':''}>Reçue</option>
                    <option value="en_cours" ${c.statut==='en_cours'?'selected':''}>En cours</option>
                    <option value="entretien" ${c.statut==='entretien'?'selected':''}>Entretien</option>
                    <option value="acceptee" ${c.statut==='acceptee'?'selected':''}>Acceptée</option>
                    <option value="refusee" ${c.statut==='refusee'?'selected':''}>Refusée</option>
                    <option value="archivee" ${c.statut==='archivee'?'selected':''}>Archivée</option>
                </select></div></div>`;
            html += `<div class="col-md-6"></div>`;
            html += `<div class="col-12"><div class="recr-detail-section"><div class="recr-detail-label">Notes admin</div>
                <textarea class="form-control form-control-sm mt-1" id="candNotesAdmin" rows="3">${escapeHtml(c.notes_admin || '')}</textarea></div></div>`;
            html += '</div>';
            document.getElementById('candDetailBody').innerHTML = html;
        });
    }

    document.getElementById('btnSaveCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        const statut = document.getElementById('candStatutSelect')?.value;
        const notes = document.getElementById('candNotesAdmin')?.value || '';
        adminApiPost('admin_update_candidature_status', { id: currentCandId, statut, notes_admin: notes }).then(r => {
            if (r.success) {
                showToast('Candidature mise à jour', 'success');
                bootstrap.Modal.getInstance(document.getElementById('candDetailModal'))?.hide();
                loadCandidatures();
            } else showToast(r.error || 'Erreur', 'danger');
        });
    });

    document.getElementById('btnDeleteCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        bootstrap.Modal.getInstance(document.getElementById('candDetailModal'))?.hide();
        new bootstrap.Modal(document.getElementById('deleteCandModal')).show();
    });

    document.getElementById('btnConfirmDeleteCand')?.addEventListener('click', () => {
        if (!currentCandId) return;
        adminApiPost('admin_delete_candidature', { id: currentCandId }).then(r => {
            bootstrap.Modal.getInstance(document.getElementById('deleteCandModal'))?.hide();
            if (r.success) { showToast('Candidature supprimée', 'success'); loadCandidatures(); }
            else showToast(r.error || 'Erreur', 'danger');
            currentCandId = null;
        });
    });

    // Load on tab switch
    document.querySelector('[data-bs-target="#recrPane-cand"]')?.addEventListener('shown.bs.tab', () => loadCandidatures());

    // ═══ Init ═══
    loadOffres();

    function formatDate(d) {
        if (!d) return '-';
        try { const dt = new Date(d); return dt.toLocaleDateString('fr-CH', { day:'2-digit', month:'2-digit', year:'numeric' }); } catch(e) { return d; }
    }
})();
</script>
