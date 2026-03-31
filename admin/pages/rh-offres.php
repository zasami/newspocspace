<?php
// ─── SSR data ────────────────────────────────────────────────────────────────
$ssrTotalOffres = (int) Db::getOne("SELECT COUNT(*) FROM offres_emploi");
$ssrActiveOffres = (int) Db::getOne("SELECT COUNT(*) FROM offres_emploi WHERE is_active = 1");
$ssrTotalCand = (int) Db::getOne("SELECT COUNT(*) FROM candidatures");
$ssrPendingCand = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE statut = 'recue'");
?>
<style>
/* ── Stat cards ── */
.rho-stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.rho-stat-card {
    flex: 1; min-width: 140px; text-align: center; padding: 16px 10px;
    border-radius: 14px; border: 1.5px solid var(--cl-border-light, #F0EDE8);
    background: var(--cl-surface, #fff);
}
.rho-stat-icon { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 8px; }
.rho-stat-val { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
.rho-stat-lbl { font-size: .72rem; color: var(--cl-text-muted); margin-top: 3px; }

/* ── Table ── */
.rho-table-wrap { border: 1.5px solid var(--cl-border-light, #F0EDE8); border-radius: 14px; overflow: hidden; background: var(--cl-surface, #fff); }
.rho-table { width: 100%; border-collapse: collapse; }
.rho-table th {
    font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
    color: var(--cl-text-muted); padding: 12px 14px;
    border-bottom: 1.5px solid var(--cl-border-light, #F0EDE8);
    text-align: left; background: var(--cl-bg, #F7F5F2);
}
.rho-table th:first-child { border-top-left-radius: 14px; }
.rho-table th:last-child { border-top-right-radius: 14px; }
.rho-table td { padding: 11px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.rho-table tr:last-child td { border-bottom: none; }
.rho-table tbody tr { cursor: pointer; transition: background .12s; }
.rho-table tbody tr:hover td { background: var(--cl-bg, #FAFAF7); }

/* ── Badges ── */
.rho-badge-active   { background: #bcd2cb; color: #2d4a43; font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; border: none; cursor: pointer; }
.rho-badge-inactive { background: #E2B8AE; color: #7B3B2C; font-size: .72rem; padding: 3px 10px; border-radius: 20px; font-weight: 600; border: none; cursor: pointer; }
.rho-count-badge { background: var(--cl-border-light, #F0EDE8); color: var(--cl-text-muted); font-size: .72rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.rho-days-badge { font-size: .68rem; padding: 2px 7px; border-radius: 8px; font-weight: 600; }
.rho-days-ok   { background: #bcd2cb; color: #2d4a43; }
.rho-days-warn { background: #D4C4A8; color: #6B5B3E; }
.rho-days-over { background: #E2B8AE; color: #7B3B2C; }

/* ── Action buttons ── */
.rho-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); font-size: .88rem; transition: all .12s; display: flex; align-items: center; justify-content: center; }
.rho-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.rho-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

/* ── Empty ── */
.rho-empty { text-align: center; padding: 50px 20px; color: var(--cl-text-muted); }
.rho-empty i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-briefcase"></i> Offres d'emploi</h4>
</div>

<!-- Stat cards -->
<div class="rho-stats">
  <div class="rho-stat-card" style="background:#f0f6f4;border-color:#d4e5df">
    <div class="rho-stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-briefcase"></i></div>
    <div class="rho-stat-val" style="color:#2d4a43" id="rhoStatTotal"><?= $ssrTotalOffres ?></div>
    <div class="rho-stat-lbl">Total offres</div>
  </div>
  <div class="rho-stat-card" style="background:#f0f8f0;border-color:#d4e8d4">
    <div class="rho-stat-icon" style="background:#C6E2C0;color:#2D5A2D"><i class="bi bi-check-circle"></i></div>
    <div class="rho-stat-val" style="color:#2D5A2D" id="rhoStatActive"><?= $ssrActiveOffres ?></div>
    <div class="rho-stat-lbl">Offres actives</div>
  </div>
  <div class="rho-stat-card" style="background:#f0f4f8;border-color:#d4dfe8">
    <div class="rho-stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-person-lines-fill"></i></div>
    <div class="rho-stat-val" style="color:#3B4F6B" id="rhoStatCand"><?= $ssrTotalCand ?></div>
    <div class="rho-stat-lbl">Total candidatures</div>
  </div>
  <div class="rho-stat-card" style="background:#f8f4ed;border-color:#e8dece">
    <div class="rho-stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-clock"></i></div>
    <div class="rho-stat-val" style="color:#6B5B3E" id="rhoStatPending"><?= $ssrPendingCand ?></div>
    <div class="rho-stat-lbl">En attente</div>
  </div>
</div>

<!-- Toolbar -->
<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-primary" id="btnAddOffre"><i class="bi bi-plus-lg"></i> Nouvelle offre</button>
  <div class="ms-auto"></div>
  <select class="form-select form-select-sm" id="rhoFilterActive" style="max-width:160px">
    <option value="">Tous</option>
    <option value="1">Actif</option>
    <option value="0">Inactif</option>
  </select>
</div>

<!-- Table body -->
<div id="rhoBody">
  <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
</div>

<!-- ═══ Modal: Offre create/edit ═══ -->
<div class="modal fade" id="rhoOffreModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhoOffreModalTitle">Nouvelle offre</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rhoOffreId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Titre *</label>
            <input type="text" class="form-control form-control-sm" id="rhoOffreTitre" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Département</label>
            <select class="form-select form-select-sm" id="rhoOffreDept">
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
            <select class="form-select form-select-sm" id="rhoOffreContrat">
              <option value="CDI">CDI</option>
              <option value="CDD">CDD</option>
              <option value="Stage">Stage</option>
              <option value="Civiliste">Civiliste</option>
              <option value="Temporaire">Temporaire</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Taux d'activité</label>
            <input type="text" class="form-control form-control-sm" id="rhoOffreTaux" placeholder="ex: 80-100%">
          </div>
          <div class="col-md-4">
            <label class="form-label">Lieu</label>
            <input type="text" class="form-control form-control-sm" id="rhoOffreLieu" value="Genève">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date de début</label>
            <input type="date" class="form-control form-control-sm" id="rhoOffreDateDebut">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date limite</label>
            <input type="date" class="form-control form-control-sm" id="rhoOffreDateLimite">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control form-control-sm" id="rhoOffreDesc" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Exigences</label>
            <textarea class="form-control form-control-sm" id="rhoOffreExigences" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Avantages</label>
            <textarea class="form-control form-control-sm" id="rhoOffreAvantages" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Indication salariale</label>
            <input type="text" class="form-control form-control-sm" id="rhoOffreSalaire" placeholder="ex: Selon CCT">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email de contact</label>
            <input type="email" class="form-control form-control-sm" id="rhoOffreEmail">
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

<!-- ═══ Modal: Confirm delete ═══ -->
<div class="modal fade" id="rhoDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Supprimer l'offre ?</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <p class="mb-0 small text-muted">Cette action est irréversible.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDelete"><i class="bi bi-trash"></i> Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let offresData = [];
    let deleteId = null;
    const filterEl = document.getElementById('rhoFilterActive');

    function loadOffres() {
        adminApiPost('admin_get_offres', {}).then(r => {
            if (!r.success) return;
            offresData = r.offres || [];
            renderOffres();
            // Update stat cards
            const total = offresData.length;
            const active = offresData.filter(o => parseInt(o.is_active)).length;
            const totalCand = offresData.reduce((s, o) => s + parseInt(o.nb_candidatures || 0), 0);
            document.getElementById('rhoStatTotal').textContent = total;
            document.getElementById('rhoStatActive').textContent = active;
            document.getElementById('rhoStatCand').textContent = totalCand;
        });
    }

    function renderOffres() {
        const el = document.getElementById('rhoBody');
        if (!el) return;
        const filterVal = filterEl?.value;
        let filtered = offresData;
        if (filterVal !== '') filtered = offresData.filter(o => String(o.is_active) === filterVal);

        if (!filtered.length) {
            el.innerHTML = '<div class="rho-empty"><i class="bi bi-briefcase"></i>Aucune offre d\'emploi</div>';
            return;
        }
        const today = new Date(); today.setHours(0,0,0,0);
        let html = '<div class="rho-table-wrap"><table class="rho-table"><thead><tr><th>Titre</th><th>Département</th><th>Contrat</th><th>Taux</th><th>Date limite</th><th>Candidatures</th><th>Actif</th><th style="width:80px">Actions</th></tr></thead><tbody>';
        filtered.forEach(o => {
            const isActive = parseInt(o.is_active);
            let dlHtml = '-';
            if (o.date_limite) {
                const dl = new Date(o.date_limite); dl.setHours(0,0,0,0);
                const diff = Math.ceil((dl - today) / 86400000);
                const cls = diff < 0 ? 'rho-days-over' : diff <= 7 ? 'rho-days-warn' : 'rho-days-ok';
                const lbl = diff < 0 ? 'expiré' : diff === 0 ? 'aujourd\'hui' : diff + 'j';
                dlHtml = formatDate(o.date_limite) + ' <span class="rho-days-badge ' + cls + '">' + lbl + '</span>';
            }
            html += `<tr data-offre-id="${o.id}">
                <td><strong>${escapeHtml(o.titre)}</strong></td>
                <td>${escapeHtml(o.departement || '-')}</td>
                <td>${escapeHtml(o.type_contrat || '-')}</td>
                <td>${escapeHtml(o.taux_activite || '-')}</td>
                <td>${dlHtml}</td>
                <td><span class="rho-count-badge">${parseInt(o.nb_candidatures || 0)}</span></td>
                <td><button class="${isActive ? 'rho-badge-active' : 'rho-badge-inactive'}" data-toggle="${o.id}" data-active="${isActive}">${isActive ? 'Actif' : 'Inactif'}</button></td>
                <td><div class="d-flex gap-1">
                    <button class="rho-row-btn" data-edit="${o.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="rho-row-btn danger" data-del="${o.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </div></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        el.innerHTML = html;
    }

    filterEl?.addEventListener('change', renderOffres);

    document.getElementById('rhoBody')?.addEventListener('click', e => {
        const tog = e.target.closest('[data-toggle]');
        if (tog) {
            const newVal = parseInt(tog.dataset.active) ? 0 : 1;
            adminApiPost('admin_update_offre', { id: tog.dataset.toggle, is_active: newVal }).then(r => {
                if (r.success) { showToast('Statut mis à jour', 'success'); loadOffres(); }
            });
            return;
        }
        const edit = e.target.closest('[data-edit]');
        if (edit) { openOffreModal(edit.dataset.edit); return; }
        const del = e.target.closest('[data-del]');
        if (del) { deleteId = del.dataset.del; new bootstrap.Modal(document.getElementById('rhoDeleteModal')).show(); return; }
        // Row click → open edit
        const row = e.target.closest('tr[data-offre-id]');
        if (row) openOffreModal(row.dataset.offreId);
    });

    document.getElementById('btnAddOffre')?.addEventListener('click', () => openOffreModal(null));

    function openOffreModal(id) {
        document.getElementById('rhoOffreId').value = '';
        document.getElementById('rhoOffreModalTitle').textContent = id ? 'Modifier l\'offre' : 'Nouvelle offre';
        ['rhoOffreTitre','rhoOffreDesc','rhoOffreExigences','rhoOffreAvantages','rhoOffreSalaire','rhoOffreEmail'].forEach(f => document.getElementById(f).value = '');
        document.getElementById('rhoOffreDept').value = '';
        document.getElementById('rhoOffreContrat').value = 'CDI';
        document.getElementById('rhoOffreTaux').value = '';
        document.getElementById('rhoOffreLieu').value = 'Genève';
        document.getElementById('rhoOffreDateDebut').value = '';
        document.getElementById('rhoOffreDateLimite').value = '';

        if (id) {
            const o = offresData.find(x => x.id === id);
            if (o) {
                document.getElementById('rhoOffreId').value = o.id;
                document.getElementById('rhoOffreTitre').value = o.titre || '';
                document.getElementById('rhoOffreDesc').value = o.description || '';
                document.getElementById('rhoOffreDept').value = o.departement || '';
                document.getElementById('rhoOffreContrat').value = o.type_contrat || 'CDI';
                document.getElementById('rhoOffreTaux').value = o.taux_activite || '';
                document.getElementById('rhoOffreLieu').value = o.lieu || '';
                document.getElementById('rhoOffreDateDebut').value = o.date_debut || '';
                document.getElementById('rhoOffreDateLimite').value = o.date_limite || '';
                document.getElementById('rhoOffreExigences').value = o.exigences || '';
                document.getElementById('rhoOffreAvantages').value = o.avantages || '';
                document.getElementById('rhoOffreSalaire').value = o.salaire_indication || '';
                document.getElementById('rhoOffreEmail').value = o.contact_email || '';
            }
        }
        new bootstrap.Modal(document.getElementById('rhoOffreModal')).show();
    }

    document.getElementById('btnSaveOffre')?.addEventListener('click', () => {
        const id = document.getElementById('rhoOffreId').value;
        const data = {
            titre: document.getElementById('rhoOffreTitre').value.trim(),
            description: document.getElementById('rhoOffreDesc').value.trim(),
            departement: document.getElementById('rhoOffreDept').value,
            type_contrat: document.getElementById('rhoOffreContrat').value,
            taux_activite: document.getElementById('rhoOffreTaux').value.trim(),
            lieu: document.getElementById('rhoOffreLieu').value.trim(),
            date_debut: document.getElementById('rhoOffreDateDebut').value,
            date_limite: document.getElementById('rhoOffreDateLimite').value,
            exigences: document.getElementById('rhoOffreExigences').value.trim(),
            avantages: document.getElementById('rhoOffreAvantages').value.trim(),
            salaire_indication: document.getElementById('rhoOffreSalaire').value.trim(),
            contact_email: document.getElementById('rhoOffreEmail').value.trim(),
        };
        if (!data.titre) { showToast('Le titre est requis', 'danger'); return; }
        const action = id ? 'admin_update_offre' : 'admin_create_offre';
        if (id) data.id = id;
        adminApiPost(action, data).then(r => {
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('rhoOffreModal'))?.hide();
                showToast(r.message || 'OK', 'success');
                loadOffres();
            } else showToast(r.error || 'Erreur', 'danger');
        });
    });

    document.getElementById('btnConfirmDelete')?.addEventListener('click', () => {
        if (!deleteId) return;
        adminApiPost('admin_delete_offre', { id: deleteId }).then(r => {
            bootstrap.Modal.getInstance(document.getElementById('rhoDeleteModal'))?.hide();
            if (r.success) { showToast(r.message || 'Supprimé', 'success'); loadOffres(); }
            else showToast(r.error || 'Erreur', 'danger');
            deleteId = null;
        });
    });

    function formatDate(d) {
        if (!d) return '-';
        try { const dt = new Date(d); return dt.toLocaleDateString('fr-CH', { day:'2-digit', month:'2-digit', year:'numeric' }); } catch(e) { return d; }
    }

    loadOffres();
})();
</script>
