<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$initList = Db::fetchAll(
    "SELECT e.*, u.prenom, u.nom,
            (SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = e.id AND statut = 'inscrit') AS nb_inscrits,
            (SELECT COUNT(*) FROM evenement_champs WHERE evenement_id = e.id) AS nb_champs
     FROM evenements e
     LEFT JOIN users u ON u.id = e.created_by
     ORDER BY e.date_debut DESC
     LIMIT 30"
);
?>
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Événements</h5>
      <small class="text-muted">Organisez des événements et gérez les inscriptions</small>
    </div>
    <button class="btn btn-primary btn-sm" id="btnNewEvent">
      <i class="bi bi-plus-lg"></i> Nouvel événement
    </button>
  </div>

  <div class="row g-3">
    <!-- LEFT: List -->
    <div class="col-md-5 col-lg-4">
      <div class="card">
        <div class="card-header py-2">
          <div class="ev-tabs" id="evFilterTabs">
            <div class="ev-tabs-slider"></div>
            <button class="ev-tab active" data-statut="">Tous</button>
            <button class="ev-tab" data-statut="brouillon">Brouillon</button>
            <button class="ev-tab" data-statut="ouvert">Ouvert</button>
            <button class="ev-tab" data-statut="ferme">Fermé</button>
          </div>
        </div>
        <div id="evListContainer" class="ev-list">
          <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Detail / Form -->
    <div class="col-md-7 col-lg-8">
      <div class="card" id="evDetailCard">
        <div class="card-body text-center text-muted py-5">
          <i class="bi bi-calendar-event" style="font-size:3rem;opacity:.3"></i>
          <p class="mt-2">Sélectionnez un événement ou créez-en un nouveau</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Création/Édition ── -->
<div class="modal fade" id="evFormModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evFormTitle">Nouvel événement</h5>
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="evFormId" value="">

        <!-- Infos générales -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <label class="form-label small fw-semibold">Titre *</label>
            <input type="text" class="form-control" id="evTitre" placeholder="Ex: Soirée karaoké, Marathon solidaire...">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Description</label>
            <textarea class="form-control" id="evDescription" rows="3" placeholder="Décrivez l'événement..."></textarea>
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small fw-semibold">Date début *</label>
            <input type="date" class="form-control" id="evDateDebut">
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small fw-semibold">Date fin</label>
            <input type="date" class="form-control" id="evDateFin">
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small fw-semibold">Heure début</label>
            <input type="time" class="form-control" id="evHeureDebut">
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small fw-semibold">Heure fin</label>
            <input type="time" class="form-control" id="evHeureFin">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold">Lieu</label>
            <input type="text" class="form-control" id="evLieu" placeholder="Ex: Salle polyvalente">
          </div>
          <div class="col-sm-3">
            <label class="form-label small fw-semibold">Max participants</label>
            <input type="number" class="form-control" id="evMaxPart" min="0" placeholder="Illimité">
          </div>
          <div class="col-sm-3">
            <label class="form-label small fw-semibold">Statut</label>
            <select class="form-select" id="evStatut">
              <option value="brouillon">Brouillon</option>
              <option value="ouvert">Ouvert</option>
              <option value="ferme">Fermé</option>
            </select>
          </div>
        </div>

        <!-- Champs personnalisés — Builder -->
        <div class="border-top pt-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h6 class="mb-0"><i class="bi bi-ui-checks-grid"></i> Champs du formulaire d'inscription</h6>
              <small class="text-muted">Ajoutez des champs pour que les participants renseignent des informations</small>
            </div>
            <button class="btn btn-sm btn-outline-dark" id="btnAddField" type="button">
              <i class="bi bi-plus-lg"></i> Ajouter un champ
            </button>
          </div>
          <div id="evFieldsList"></div>
          <div id="evFieldsEmpty" class="text-center text-muted py-3 small" style="display:none">
            <i class="bi bi-ui-checks-grid" style="font-size:1.5rem;opacity:.3"></i>
            <div class="mt-1">Aucun champ personnalisé. Cliquez sur "Ajouter un champ" pour commencer.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm" style="background:#bcd2cb;color:#2d4a43" id="btnSaveEvent">
          <i class="bi bi-check-lg"></i> Enregistrer
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── List ── */
.ev-list { max-height: calc(100vh - 260px); overflow-y: auto; }
.ev-item {
  padding: 12px 16px;
  border-bottom: 1px solid var(--cl-border);
  cursor: pointer;
  transition: background var(--cl-transition);
}
.ev-item:hover { background: var(--cl-accent-bg); }
.ev-item.active { background: var(--cl-accent-bg); border-left: 4px solid var(--cl-accent); padding-left: 12px; }
.ev-item-title { font-weight: 500; font-size: 0.95rem; }
.ev-item-meta { font-size: 0.8rem; color: var(--cl-text-muted); margin-top: 2px; display: flex; justify-content: space-between; }

/* ── Tabs ── */
.ev-tabs {
  display: flex; position: relative; background: var(--cl-bg); border-radius: 10px; padding: 3px; gap: 0;
}
.ev-tabs-slider {
  position: absolute; top: 3px; left: 3px; width: calc(25% - 3px); height: calc(100% - 6px);
  background: var(--cl-surface); border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 0; pointer-events: none;
}
.ev-tab {
  flex: 1; background: none; border: none; padding: 6px 8px; font-size: 0.78rem; font-weight: 500;
  color: var(--cl-text-secondary); border-radius: 8px; cursor: pointer; position: relative; z-index: 1;
  text-align: center; white-space: nowrap; transition: color 0.25s ease;
}
.ev-tab:hover { color: var(--cl-text); }
.ev-tab.active { color: var(--cl-text); font-weight: 600; }

/* ── Detail ── */
.ev-detail-body { max-height: calc(100vh - 320px); overflow-y: auto; }
.ev-inscrit-row {
  display: flex; align-items: center; gap: 10px; padding: 8px 12px;
  border-bottom: 1px solid var(--cl-border); font-size: 0.88rem;
}
.ev-inscrit-row:last-child { border-bottom: none; }

/* ── Field builder ── */
.ev-field-card {
  border: 1px solid var(--cl-border); border-radius: 10px; padding: 12px; margin-bottom: 10px;
  background: var(--cl-surface); position: relative;
}
.ev-field-card .ev-field-drag {
  cursor: grab; color: var(--cl-text-muted); font-size: 1.1rem; padding: 0 4px;
}
.ev-field-card .ev-field-remove {
  position: absolute; top: 8px; right: 8px; background: none; border: none;
  color: var(--cl-text-muted); cursor: pointer; font-size: 0.9rem; padding: 2px 6px; border-radius: 4px;
}
.ev-field-card .ev-field-remove:hover { color: #c0392b; background: rgba(192,57,43,0.08); }
.ev-field-options-area { margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--cl-border); }
</style>

<script<?= nonce() ?>>
(function() {
    const initData = <?= json_encode(array_values($initList), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let selectedId = null;
    let currentStatut = '';
    let formModal = null;
    let fieldCounter = 0;

    // ─── Init ───
    document.addEventListener('DOMContentLoaded', () => {
        formModal = new bootstrap.Modal(document.getElementById('evFormModal'));
        renderList(initData);

        document.getElementById('btnNewEvent').addEventListener('click', openNewForm);
        document.getElementById('btnAddField').addEventListener('click', addField);
        document.getElementById('btnSaveEvent').addEventListener('click', saveEvent);

        // Detail card actions delegate
        document.getElementById('evDetailCard').addEventListener('click', (e) => {
            const btn = e.target.closest('[data-ev-action]');
            if (!btn) return;
            const a = btn.dataset.evAction;
            if (a === 'edit') editEvent();
            else if (a === 'toggle') toggleEvent(btn.dataset.statut);
            else if (a === 'delete') deleteEvent();
            else if (a === 'export') exportInscriptions();
        });

        // Sliding pill toggle
        const tabs = document.querySelectorAll('.ev-tab');
        const slider = document.querySelector('.ev-tabs-slider');
        tabs.forEach((btn, idx) => {
            btn.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (slider) slider.style.transform = 'translateX(' + (idx * 100) + '%)';
                currentStatut = btn.dataset.statut;
                loadList();
            });
        });

        // Topbar search
        const topInput = document.getElementById('topbarSearchInput');
        if (topInput) {
            topInput.placeholder = 'Rechercher un événement...';
            topInput.addEventListener('input', debounce(loadList, 300));
        }
    });

    // ─── Render list ───
    function renderList(list) {
        const c = document.getElementById('evListContainer');
        if (!list || list.length === 0) {
            c.innerHTML = '<div class="text-center py-4 text-muted">Aucun événement</div>';
            return;
        }
        c.innerHTML = list.map(e => {
            const date = fmtDate(e.date_debut);
            const badge = statusBadge(e.statut);
            return `<div class="ev-item ${e.id === selectedId ? 'active' : ''}" data-id="${escapeHtml(e.id)}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="ev-item-title">${escapeHtml(e.titre)}</div>
                    ${badge}
                </div>
                <div class="ev-item-meta">
                    <span>${date}${e.lieu ? ' · ' + escapeHtml(e.lieu) : ''}</span>
                    <span><i class="bi bi-people-fill"></i> ${e.nb_inscrits}</span>
                </div>
            </div>`;
        }).join('');

        c.querySelectorAll('.ev-item').forEach(item => {
            item.addEventListener('click', () => loadDetail(item.dataset.id));
        });
    }

    async function loadList() {
        const c = document.getElementById('evListContainer');
        c.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>';
        const topInput = document.getElementById('topbarSearchInput');
        const r = await adminApiPost('admin_get_evenements', { statut: currentStatut, search: topInput ? topInput.value : '' });
        if (!r.success) { c.innerHTML = '<div class="text-center py-4 text-danger">Erreur</div>'; return; }
        renderList(r.list);
    }

    // ─── Detail ───
    async function loadDetail(id) {
        selectedId = id;
        document.querySelectorAll('.ev-item').forEach(el => el.classList.toggle('active', el.dataset.id === id));

        const card = document.getElementById('evDetailCard');
        card.innerHTML = '<div class="card-body text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const r = await adminApiPost('admin_get_evenement', { id });
        if (!r.success) { card.innerHTML = '<div class="card-body text-danger">Erreur</div>'; return; }

        const ev = r.evenement;
        const champs = r.champs || [];
        const inscriptions = r.inscriptions || [];
        const badge = statusBadge(ev.statut);

        // Actions
        let actions = '';
        actions += `<button class="btn btn-outline-dark btn-sm me-1" data-ev-action="edit"><i class="bi bi-pencil"></i> Modifier</button>`;
        if (ev.statut === 'brouillon') {
            actions += `<button class="btn btn-sm me-1" style="background:#bcd2cb;color:#2d4a43" data-ev-action="toggle" data-statut="ouvert"><i class="bi bi-play-fill"></i> Ouvrir</button>`;
        } else if (ev.statut === 'ouvert') {
            actions += `<button class="btn btn-sm me-1" style="background:#D4C4A8;color:#6B5B3E" data-ev-action="toggle" data-statut="ferme"><i class="bi bi-stop-fill"></i> Fermer</button>`;
        } else if (ev.statut === 'ferme') {
            actions += `<button class="btn btn-sm me-1" style="background:#bcd2cb;color:#2d4a43" data-ev-action="toggle" data-statut="ouvert"><i class="bi bi-play-fill"></i> Réouvrir</button>`;
        }
        if (inscriptions.length > 0) {
            actions += `<button class="btn btn-outline-secondary btn-sm me-1" data-ev-action="export"><i class="bi bi-download"></i> Export</button>`;
        }
        actions += `<button class="btn btn-outline-danger btn-sm" data-ev-action="delete"><i class="bi bi-trash"></i></button>`;

        // Info
        let infoHtml = '';
        if (ev.date_debut) infoHtml += `<span class="me-3"><i class="bi bi-calendar3"></i> ${fmtDate(ev.date_debut)}${ev.date_fin && ev.date_fin !== ev.date_debut ? ' → ' + fmtDate(ev.date_fin) : ''}</span>`;
        if (ev.heure_debut) infoHtml += `<span class="me-3"><i class="bi bi-clock"></i> ${ev.heure_debut.substring(0,5)}${ev.heure_fin ? ' - ' + ev.heure_fin.substring(0,5) : ''}</span>`;
        if (ev.lieu) infoHtml += `<span class="me-3"><i class="bi bi-geo-alt"></i> ${escapeHtml(ev.lieu)}</span>`;
        infoHtml += `<span><i class="bi bi-people-fill"></i> ${inscriptions.filter(i => i.statut === 'inscrit').length}${ev.max_participants ? '/' + ev.max_participants : ''} inscrits</span>`;

        // Champs configurés
        let champsHtml = '';
        if (champs.length > 0) {
            champsHtml = `<div class="mb-3"><h6 class="text-muted small fw-semibold mb-2"><i class="bi bi-ui-checks-grid"></i> Champs du formulaire (${champs.length})</h6>
                <div class="d-flex flex-wrap gap-2">
                    ${champs.map(c => `<span class="badge" style="background:#F2EDE8;color:#6B5B3E">${escapeHtml(c.label)} <small class="opacity-75">(${c.type})</small>${c.obligatoire ? ' *' : ''}</span>`).join('')}
                </div></div>`;
        }

        // Inscriptions table
        let inscritsHtml = '';
        const inscritsActifs = inscriptions.filter(i => i.statut === 'inscrit');
        if (inscritsActifs.length > 0) {
            // Build header
            let thExtra = champs.map(c => `<th class="small">${escapeHtml(c.label)}</th>`).join('');
            let rows = inscritsActifs.map(ins => {
                let tdExtra = champs.map(c => {
                    const v = (ins.valeurs || []).find(val => val.champ_id === c.id);
                    let display = v ? v.valeur : '—';
                    // Checkbox: parse JSON array
                    if (v && c.type === 'checkbox') {
                        try { display = JSON.parse(display).join(', '); } catch(e) {}
                    }
                    return `<td class="small">${escapeHtml(display)}</td>`;
                }).join('');
                const d = new Date(ins.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                return `<tr>
                    <td class="small fw-medium">${escapeHtml(ins.prenom)} ${escapeHtml(ins.nom)}</td>
                    <td class="small text-muted">${escapeHtml(ins.email)}</td>
                    <td class="small text-muted">${d}</td>
                    ${tdExtra}
                </tr>`;
            }).join('');

            inscritsHtml = `
                <h6 class="text-muted small fw-semibold mb-2"><i class="bi bi-people"></i> Inscriptions (${inscritsActifs.length})</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr>
                            <th class="small">Nom</th><th class="small">Email</th><th class="small">Date</th>
                            ${thExtra}
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        } else {
            inscritsHtml = '<div class="text-center text-muted py-3 small"><i class="bi bi-people" style="font-size:1.3rem;opacity:.3"></i><div class="mt-1">Aucune inscription</div></div>';
        }

        card.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">${escapeHtml(ev.titre)} ${badge}</h5>
                    <div class="small text-muted">${infoHtml}</div>
                </div>
                <div class="d-flex flex-wrap gap-1">${actions}</div>
            </div>
            <div class="card-body ev-detail-body">
                ${ev.description ? '<div class="mb-3" style="font-size:.9rem;line-height:1.6;white-space:pre-wrap">' + escapeHtml(ev.description) + '</div>' : ''}
                ${champsHtml}
                ${inscritsHtml}
            </div>`;
    }

    // ─── Form: New ───
    function openNewForm() {
        document.getElementById('evFormId').value = '';
        document.getElementById('evFormTitle').textContent = 'Nouvel événement';
        document.getElementById('evTitre').value = '';
        document.getElementById('evDescription').value = '';
        document.getElementById('evDateDebut').value = '';
        document.getElementById('evDateFin').value = '';
        document.getElementById('evHeureDebut').value = '';
        document.getElementById('evHeureFin').value = '';
        document.getElementById('evLieu').value = '';
        document.getElementById('evMaxPart').value = '';
        document.getElementById('evStatut').value = 'brouillon';
        document.getElementById('evFieldsList').innerHTML = '';
        fieldCounter = 0;
        toggleFieldsEmpty();
        formModal.show();
    }

    // ─── Form: Edit ───
    async function editEvent() {
        if (!selectedId) return;
        const r = await adminApiPost('admin_get_evenement', { id: selectedId });
        if (!r.success) { toast('Erreur de chargement', 'error'); return; }

        const ev = r.evenement;
        document.getElementById('evFormId').value = ev.id;
        document.getElementById('evFormTitle').textContent = 'Modifier l\'événement';
        document.getElementById('evTitre').value = ev.titre || '';
        document.getElementById('evDescription').value = ev.description || '';
        document.getElementById('evDateDebut').value = ev.date_debut || '';
        document.getElementById('evDateFin').value = ev.date_fin || '';
        document.getElementById('evHeureDebut').value = (ev.heure_debut || '').substring(0, 5);
        document.getElementById('evHeureFin').value = (ev.heure_fin || '').substring(0, 5);
        document.getElementById('evLieu').value = ev.lieu || '';
        document.getElementById('evMaxPart').value = ev.max_participants || '';
        document.getElementById('evStatut').value = ev.statut;

        // Populate champs
        document.getElementById('evFieldsList').innerHTML = '';
        fieldCounter = 0;
        (r.champs || []).forEach(c => {
            addField(null, c);
        });
        toggleFieldsEmpty();
        formModal.show();
    }

    // ─── Field builder ───
    function addField(e, data) {
        fieldCounter++;
        const idx = fieldCounter;
        const type = data ? data.type : 'texte';
        const label = data ? data.label : '';
        const obligatoire = data ? data.obligatoire : 0;
        const options = data && data.options ? (Array.isArray(data.options) ? data.options.join('\n') : data.options) : '';

        const needsOptions = ['radio', 'select', 'checkbox'].includes(type);

        const html = `
        <div class="ev-field-card" data-field-idx="${idx}">
            <button class="ev-field-remove" title="Supprimer ce champ" type="button">&times;</button>
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label class="form-label small mb-1">Libellé</label>
                    <input type="text" class="form-control form-control-sm ev-f-label" value="${escapeHtml(label)}" placeholder="Ex: Distance choisie, Taille t-shirt...">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Type</label>
                    <select class="form-select form-select-sm ev-f-type">
                        <option value="texte" ${type === 'texte' ? 'selected' : ''}>Texte court</option>
                        <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>Texte long</option>
                        <option value="nombre" ${type === 'nombre' ? 'selected' : ''}>Nombre</option>
                        <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>Cases à cocher</option>
                        <option value="radio" ${type === 'radio' ? 'selected' : ''}>Choix unique (radio)</option>
                        <option value="select" ${type === 'select' ? 'selected' : ''}>Liste déroulante</option>
                    </select>
                </div>
                <div class="col-sm-3 d-flex align-items-center gap-2" style="padding-bottom:2px">
                    <div class="form-check">
                        <input class="form-check-input ev-f-obligatoire" type="checkbox" id="evFOblig${idx}" ${obligatoire ? 'checked' : ''}>
                        <label class="form-check-label small" for="evFOblig${idx}">Obligatoire</label>
                    </div>
                </div>
            </div>
            <div class="ev-field-options-area" style="${needsOptions ? '' : 'display:none'}">
                <label class="form-label small mb-1">Options (une par ligne)</label>
                <textarea class="form-control form-control-sm ev-f-options" rows="3" placeholder="Relais 1 – 5 km\nRelais 2 – 10 km\nRelais 3 – 21 km">${escapeHtml(options)}</textarea>
            </div>
        </div>`;

        document.getElementById('evFieldsList').insertAdjacentHTML('beforeend', html);
        const card = document.querySelector(`.ev-field-card[data-field-idx="${idx}"]`);

        // Type change → show/hide options
        card.querySelector('.ev-f-type').addEventListener('change', function() {
            const show = ['radio', 'select', 'checkbox'].includes(this.value);
            card.querySelector('.ev-field-options-area').style.display = show ? '' : 'none';
        });

        // Remove
        card.querySelector('.ev-field-remove').addEventListener('click', () => {
            card.remove();
            toggleFieldsEmpty();
        });

        toggleFieldsEmpty();
    }

    function toggleFieldsEmpty() {
        const has = document.querySelectorAll('.ev-field-card').length > 0;
        document.getElementById('evFieldsEmpty').style.display = has ? 'none' : '';
    }

    function collectFields() {
        const fields = [];
        document.querySelectorAll('.ev-field-card').forEach(card => {
            const label = card.querySelector('.ev-f-label').value.trim();
            if (!label) return;
            fields.push({
                label,
                type: card.querySelector('.ev-f-type').value,
                obligatoire: card.querySelector('.ev-f-obligatoire').checked ? 1 : 0,
                options: card.querySelector('.ev-f-options').value,
            });
        });
        return fields;
    }

    // ─── Save ───
    async function saveEvent() {
        const id = document.getElementById('evFormId').value;
        const titre = document.getElementById('evTitre').value.trim();
        if (!titre) { toast('Le titre est requis', 'error'); return; }

        const dateDebut = document.getElementById('evDateDebut').value;
        if (!dateDebut) { toast('La date de début est requise', 'error'); return; }

        const payload = {
            titre,
            description: document.getElementById('evDescription').value,
            date_debut: dateDebut,
            date_fin: document.getElementById('evDateFin').value || null,
            heure_debut: document.getElementById('evHeureDebut').value || null,
            heure_fin: document.getElementById('evHeureFin').value || null,
            lieu: document.getElementById('evLieu').value,
            max_participants: document.getElementById('evMaxPart').value || null,
            statut: document.getElementById('evStatut').value,
            champs: collectFields(),
        };

        const actionName = id ? 'admin_update_evenement' : 'admin_create_evenement';
        if (id) payload.id = id;

        const btn = document.getElementById('btnSaveEvent');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const r = await adminApiPost(actionName, payload);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';

        if (r.success) {
            toast(r.message || 'Enregistré', 'success');
            formModal.hide();
            loadList();
            const newId = id || r.id;
            if (newId) loadDetail(newId);
        } else {
            toast(r.message || 'Erreur', 'error');
        }
    }

    // ─── Toggle status ───
    async function toggleEvent(statut) {
        if (!selectedId) return;
        const icons = { ouvert: 'bi-play-circle', ferme: 'bi-stop-circle', brouillon: 'bi-arrow-counterclockwise', annule: 'bi-x-circle' };
        const types = { ouvert: 'success', ferme: 'warning', brouillon: 'info', annule: 'danger' };
        const labels = { ouvert: 'Ouvrir', ferme: 'Fermer', brouillon: 'Remettre en brouillon', annule: 'Annuler' };
        const ok = await adminConfirm({
            title: labels[statut] || 'Changer le statut',
            text: 'Voulez-vous ' + (labels[statut] || statut).toLowerCase() + ' cet événement ?',
            icon: icons[statut] || 'bi-question-circle',
            type: types[statut] || 'warning',
            okText: labels[statut] || 'Confirmer',
        });
        if (!ok) return;

        const r = await adminApiPost('admin_toggle_evenement', { id: selectedId, statut });
        if (r.success) { toast('Statut mis à jour', 'success'); loadList(); loadDetail(selectedId); }
        else toast(r.message || 'Erreur', 'error');
    }

    // ─── Delete ───
    async function deleteEvent() {
        if (!selectedId) return;
        const ok = await adminConfirm({
            title: 'Supprimer l\'événement',
            text: 'Supprimer cet événement et toutes ses inscriptions ? Cette action est irréversible.',
            icon: 'bi-trash3',
            type: 'danger',
            okText: 'Supprimer',
        });
        if (!ok) return;

        const r = await adminApiPost('admin_delete_evenement', { id: selectedId });
        if (r.success) {
            toast('Événement supprimé', 'success');
            selectedId = null;
            document.getElementById('evDetailCard').innerHTML = '<div class="card-body text-center text-muted py-5"><i class="bi bi-calendar-event" style="font-size:3rem;opacity:.3"></i><p class="mt-2">Sélectionnez un événement</p></div>';
            loadList();
        } else toast(r.message || 'Erreur', 'error');
    }

    // ─── Export CSV ───
    async function exportInscriptions() {
        if (!selectedId) return;
        const r = await adminApiPost('admin_export_evenement_inscriptions', { id: selectedId });
        if (!r.success) { toast('Erreur', 'error'); return; }

        const headers = ['Prénom', 'Nom', 'Email', 'Statut', 'Inscrit le', ...(r.champs || [])];
        let csv = headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(';') + '\n';
        (r.rows || []).forEach(row => {
            csv += headers.map(h => '"' + (row[h] || '').toString().replace(/"/g, '""') + '"').join(';') + '\n';
        });

        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (r.titre || 'inscriptions') + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
        toast('Export téléchargé', 'success');
    }

    // ─── Utils ───
    function statusBadge(s) {
        const m = { ouvert: 'bg-success', ferme: 'bg-secondary', brouillon: 'bg-warning text-dark', annule: 'bg-danger' };
        const l = { ouvert: 'Ouvert', ferme: 'Fermé', brouillon: 'Brouillon', annule: 'Annulé' };
        return `<span class="badge ${m[s] || 'bg-secondary'}">${l[s] || s}</span>`;
    }

    function fmtDate(d) {
        if (!d) return '—';
        try { return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }); }
        catch(e) { return d; }
    }

    function debounce(fn, delay) {
        let t; return function(...args) { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

})();
</script>
