<style>
.btn-horaire-edit {
  background: var(--cl-bg); border: 1px solid var(--cl-border); color: var(--cl-text-muted);
  border-radius: var(--cl-radius-xs); transition: all var(--cl-transition);
}
.btn-horaire-edit:hover {
  background: rgba(25,25,24,0.08); color: var(--cl-accent); border-color: var(--cl-accent);
}

/* Modal header colored border */
.horaire-modal-header { border-bottom: 3px solid #1a1a1a; }

/* Preview badge in modal header */
.horaire-preview-badge {
  background: #1a1a1a; color: #fff; padding: 4px 14px;
  border-radius: 6px; font-weight: 700; font-size: 1.1rem;
  display: inline-block;
}

/* Modal form flex layout */
.horaire-form-flex {
  display: flex; flex-direction: column; overflow: hidden; flex: 1 1 auto;
}

/* Scrollable modal body */
.horaire-modal-body-scroll { overflow-y: auto; flex: 1 1 auto; }

/* Section headings (repeated pattern) */
.horaire-section-title {
  font-size: 0.72rem; letter-spacing: 1px;
}

/* Code input field */
.horaire-code-input { font-size: 1.2rem; letter-spacing: 2px; }

/* Color picker sizing */
.horaire-color-picker { width: 50px; height: 38px; }

/* Read-only computed field */
.horaire-readonly { pointer-events: none; }

/* Effective duration emphasis */
.horaire-duree-eff { font-size: 1.1rem; }

/* Scaled switch */
.horaire-switch-lg { transform: scale(1.3); }

/* Modal footer spread layout */
.horaire-footer-spread { justify-content: space-between; }

/* Table code badge */
.horaire-code-badge {
  display: inline-block; color: #fff; padding: 0.15rem 0.5rem;
  border-radius: 4px; font-weight: 700; font-size: 0.85rem;
}
.horaire-code-badge--inactive { background: #aaa; }

/* Table color swatch */
.horaire-color-swatch {
  display: inline-block; width: 24px; height: 24px; border-radius: 4px;
}
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0">Codes horaires utilisés dans les plannings (A1, D3, S4, etc.)</p>
  <button class="btn btn-primary btn-sm" id="btnNewHoraire">
    <i class="bi bi-plus-lg"></i> Nouveau
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Code</th>
          <th>Nom</th>
          <th>Début</th>
          <th>Fin</th>
          <th>Pauses payées</th>
          <th>Pauses non payées</th>
          <th>Durée effective</th>
          <th>Couleur</th>
          <th>Actif</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="horairesTableBody">
        <tr><td colspan="10" class="text-center py-4 text-muted"><span class="admin-spinner"></span> Chargement...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Horaire Modal — redesigned -->
<div class="modal fade" id="horaireModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-info modal-dialog-scrollable">
    <div class="modal-content">
      <!-- Header with color preview -->
      <div class="modal-header horaire-modal-header" id="horaireModalHeader">
        <div class="d-flex align-items-center gap-2">
          <span class="horaire-preview-badge" id="horairePreviewBadge">A1</span>
          <div>
            <h5 class="modal-title mb-0" id="horaireModalTitle">Nouveau type d'horaire</h5>
            <small class="text-muted" id="horaireModalSubtitle">Définissez le code, les horaires et les pauses</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="horaireForm" class="horaire-form-flex">
        <input type="hidden" id="horaireId">

        <!-- Body (scrollable) -->
        <div class="modal-body horaire-modal-body-scroll">
          <!-- Section 1: Identité -->
          <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold horaire-section-title"><i class="bi bi-tag"></i> Identité</h6>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg text-center fw-bold horaire-code-input" id="horaireCode" required maxlength="10" placeholder="A1">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="horaireNom" required placeholder="Ex: Matin court, Après-midi, Soir...">
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">Couleur</label>
                <div class="d-flex align-items-center gap-2">
                  <input type="color" class="form-control form-control-color horaire-color-picker" id="horaireCouleur" value="#1a1a1a">
                  <span class="text-muted small" id="horaireCouleurHex">#1a1a1a</span>
                </div>
              </div>
            </div>
          </div>

          <hr>

          <!-- Section 2: Horaires -->
          <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold horaire-section-title"><i class="bi bi-clock"></i> Horaires</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Heure de début <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="horaireDebut" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Heure de fin <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="horaireFin" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Durée brute</label>
                <div class="form-control bg-light horaire-readonly" id="horaireDureeBrute">—</div>
              </div>
            </div>
          </div>

          <hr>

          <!-- Section 3: Pauses -->
          <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold horaire-section-title"><i class="bi bi-cup-hot"></i> Pauses</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Pauses payées</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="horairePP" value="1" min="0">
                  <span class="input-group-text">× 30 min</span>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Pauses non payées</label>
                <div class="input-group">
                  <input type="number" class="form-control" id="horairePNP" value="1" min="0">
                  <span class="input-group-text">× 30 min</span>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Durée effective</label>
                <div class="form-control bg-light fw-bold text-success horaire-readonly horaire-duree-eff" id="horaireDureeEff">—</div>
              </div>
            </div>
          </div>

          <hr>

          <!-- Section 4: Statut -->
          <div>
            <h6 class="text-uppercase text-muted fw-bold horaire-section-title"><i class="bi bi-toggles"></i> Statut</h6>
            <div class="form-check form-switch">
              <input class="form-check-input horaire-switch-lg" type="checkbox" id="horaireActif" checked>
              <label class="form-check-label fw-semibold ms-2" for="horaireActif">Actif — disponible pour la planification</label>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer horaire-footer-spread">
          <div>
            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="horaireDeleteBtn">
              <i class="bi bi-trash"></i> Supprimer
            </button>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
              <i class="bi bi-x-lg"></i> Annuler
            </button>
            <button type="submit" class="btn btn-dark">
              <i class="bi bi-check-lg"></i> Enregistrer
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let horairesCache = [];

    async function initHorairesPage() {
        await loadHoraires();

        // New button
        document.getElementById('btnNewHoraire').addEventListener('click', () => {
            resetHoraireForm();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('horaireModal')).show();
        });

        // Form submit
        document.getElementById('horaireForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('horaireId').value;
            const data = {
                code: document.getElementById('horaireCode').value.trim(),
                nom: document.getElementById('horaireNom').value.trim(),
                heure_debut: document.getElementById('horaireDebut').value,
                heure_fin: document.getElementById('horaireFin').value,
                pauses_payees: document.getElementById('horairePP').value,
                pauses_non_payees: document.getElementById('horairePNP').value,
                couleur: document.getElementById('horaireCouleur').value,
                is_active: document.getElementById('horaireActif').checked ? 1 : 0,
            };

            if (!data.code || !data.nom || !data.heure_debut || !data.heure_fin) {
                showToast('Remplissez les champs obligatoires', 'error');
                return;
            }

            const action = id ? 'admin_update_horaire' : 'admin_create_horaire';
            if (id) data.id = id;

            const res = await adminApiPost(action, data);
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('horaireModal')).hide();
                showToast(id ? 'Horaire modifié' : 'Horaire créé', 'success');
                await loadHoraires();
            } else {
                showToast(res.message || 'Erreur', 'error');
            }
        });

        // Delete button
        document.getElementById('horaireDeleteBtn').addEventListener('click', async () => {
            const id = document.getElementById('horaireId').value;
            if (!id) return;
            if (!await adminConfirm({ title: 'Désactiver l\'horaire', text: 'Cet horaire ne sera plus utilisable dans la génération de planning.', icon: 'bi-clock-history', type: 'warning', okText: 'Désactiver' })) return;
            const res = await adminApiPost('admin_delete_horaire', { id });
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('horaireModal')).hide();
                showToast('Horaire désactivé', 'success');
                await loadHoraires();
            }
        });

        // Live preview updates
        document.getElementById('horaireCode').addEventListener('input', updatePreview);
        document.getElementById('horaireCouleur').addEventListener('input', updatePreview);
        document.getElementById('horaireDebut').addEventListener('change', calcDuration);
        document.getElementById('horaireFin').addEventListener('change', calcDuration);
        document.getElementById('horairePP').addEventListener('input', calcDuration);
        document.getElementById('horairePNP').addEventListener('input', calcDuration);
    }

    function updatePreview() {
        const code = document.getElementById('horaireCode').value || '?';
        const color = document.getElementById('horaireCouleur').value;
        const badge = document.getElementById('horairePreviewBadge');
        badge.textContent = code;
        badge.style.background = color;
        document.getElementById('horaireModalHeader').style.borderBottomColor = color;
        document.getElementById('horaireCouleurHex').textContent = color;
    }

    function calcDuration() {
        const debut = document.getElementById('horaireDebut').value;
        const fin = document.getElementById('horaireFin').value;
        const pnp = parseInt(document.getElementById('horairePNP').value) || 0;
        const bruteEl = document.getElementById('horaireDureeBrute');
        const effEl = document.getElementById('horaireDureeEff');

        if (!debut || !fin) { bruteEl.textContent = '—'; effEl.textContent = '—'; return; }

        const [dh, dm] = debut.split(':').map(Number);
        const [fh, fm] = fin.split(':').map(Number);
        let mins = (fh * 60 + fm) - (dh * 60 + dm);
        if (mins < 0) mins += 24 * 60; // overnight

        const bruteH = Math.floor(mins / 60);
        const bruteM = mins % 60;
        bruteEl.textContent = `${bruteH}h${bruteM > 0 ? String(bruteM).padStart(2, '0') : ''}`;

        const effMins = mins - (pnp * 30);
        const effH = (effMins / 60).toFixed(2);
        effEl.textContent = `${effH}h`;
    }

    async function loadHoraires() {
        const res = await adminApiPost('admin_get_horaires');
        horairesCache = res.horaires || [];
        const tbody = document.getElementById('horairesTableBody');

        if (!horairesCache.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Aucun horaire configuré</td></tr>';
            return;
        }

        tbody.innerHTML = horairesCache.map(h => {
            const active = parseInt(h.is_active) !== 0;
            return `<tr class="clickable-row ${!active ? 'text-muted' : ''}" data-row-horaire="${h.id}">
                <td><span class="horaire-code-badge ${active ? '' : 'horaire-code-badge--inactive'}" ${active ? `style="background:${h.couleur || '#ccc'}"` : ''}>${escapeHtml(h.code)}</span></td>
                <td>${escapeHtml(h.nom)}</td>
                <td>${escapeHtml(h.heure_debut?.substring(0,5) || '')}</td>
                <td>${escapeHtml(h.heure_fin?.substring(0,5) || '')}</td>
                <td>${h.pauses_payees}</td>
                <td>${h.pauses_non_payees}</td>
                <td><strong>${h.duree_effective}h</strong></td>
                <td><span class="horaire-color-swatch" style="background:${h.couleur || '#ccc'}"></span></td>
                <td>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" ${active ? 'checked' : ''} data-toggle-horaire="${h.id}">
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-horaire-edit" data-edit-horaire="${h.id}"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>`;
        }).join('');

        // Toggle handlers
        tbody.querySelectorAll('[data-toggle-horaire]').forEach(sw => {
            sw.addEventListener('change', async () => {
                const res = await adminApiPost('admin_toggle_horaire', {
                    id: sw.dataset.toggleHoraire,
                    is_active: sw.checked ? 1 : 0,
                });
                if (res.success) {
                    showToast(sw.checked ? 'Horaire activé' : 'Horaire désactivé', 'success');
                    await loadHoraires();
                } else {
                    showToast(res.message || 'Erreur', 'error');
                    sw.checked = !sw.checked;
                }
            });
        });

        // Edit handlers
        tbody.querySelectorAll('[data-edit-horaire]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const h = horairesCache.find(x => x.id === btn.dataset.editHoraire);
                if (h) editHoraire(h);
            });
        });

        // Row click → open edit modal
        tbody.querySelectorAll('[data-row-horaire]').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('.form-check-input, .btn')) return;
                const h = horairesCache.find(x => x.id === row.dataset.rowHoraire);
                if (h) editHoraire(h);
            });
        });
    }

    function resetHoraireForm() {
        document.getElementById('horaireId').value = '';
        document.getElementById('horaireForm').reset();
        document.getElementById('horaireCode').value = '';
        document.getElementById('horaireNom').value = '';
        document.getElementById('horaireDebut').value = '';
        document.getElementById('horaireFin').value = '';
        document.getElementById('horairePP').value = '1';
        document.getElementById('horairePNP').value = '1';
        document.getElementById('horaireCouleur').value = '#1a1a1a';
        document.getElementById('horaireActif').checked = true;
        document.getElementById('horaireModalTitle').textContent = 'Nouveau type d\'horaire';
        document.getElementById('horaireModalSubtitle').textContent = 'Définissez le code, les horaires et les pauses';
        document.getElementById('horaireDeleteBtn').classList.add('d-none');
        document.getElementById('horaireDureeBrute').textContent = '—';
        document.getElementById('horaireDureeEff').textContent = '—';
        updatePreview();
    }

    function editHoraire(h) {
        document.getElementById('horaireId').value = h.id;
        document.getElementById('horaireCode').value = h.code;
        document.getElementById('horaireNom').value = h.nom;
        document.getElementById('horaireDebut').value = h.heure_debut?.substring(0,5) || '';
        document.getElementById('horaireFin').value = h.heure_fin?.substring(0,5) || '';
        document.getElementById('horairePP').value = h.pauses_payees;
        document.getElementById('horairePNP').value = h.pauses_non_payees;
        document.getElementById('horaireCouleur').value = h.couleur || '#1a1a1a';
        document.getElementById('horaireActif').checked = parseInt(h.is_active) !== 0;
        document.getElementById('horaireModalTitle').textContent = 'Modifier — ' + h.code;
        document.getElementById('horaireModalSubtitle').textContent = h.nom + ' · ' + (h.heure_debut?.substring(0,5) || '') + ' – ' + (h.heure_fin?.substring(0,5) || '');
        document.getElementById('horaireDeleteBtn').classList.remove('d-none');
        updatePreview();
        calcDuration();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('horaireModal')).show();
    }

    window.initHorairesPage = initHorairesPage;
})();
</script>
