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
          <!-- Image de couverture -->
          <div class="col-12">
            <label class="form-label small fw-semibold">Image de couverture</label>
            <div class="ev-cover-zone" id="evCoverZone">
              <div class="ev-cover-placeholder" id="evCoverPlaceholder">
                <i class="bi bi-image"></i>
                <span>Cliquez pour ajouter une image (upload ou Pixabay)</span>
              </div>
              <div class="ev-cover-preview d-none" id="evCoverPreview">
                <img src="" alt="" id="evCoverImg">
                <button type="button" class="ev-cover-remove" id="evCoverRemove" title="Retirer l'image"><i class="bi bi-x-lg"></i></button>
              </div>
            </div>
            <input type="hidden" id="evImageUrl" value="">
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
          <div class="col-sm-6">
            <label class="form-label small fw-semibold">Date limite d'inscription</label>
            <input type="datetime-local" class="form-control" id="evDateLimite">
            <small class="text-muted">Laissez vide = pas de limite</small>
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

<!-- ── Modal Image Picker ── -->
<div class="modal fade" id="evImagePickerModal" tabindex="-1" style="z-index:1060">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Choisir une image</h5>
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <!-- Tabs Upload / Pixabay -->
        <div class="d-flex gap-2 mb-3">
          <button class="btn btn-sm btn-outline-dark active" id="imgTabUpload" data-img-tab="upload"><i class="bi bi-cloud-arrow-up"></i> Télécharger</button>
          <button class="btn btn-sm btn-outline-dark" id="imgTabPixabay" data-img-tab="pixabay"><i class="bi bi-images"></i> Pixabay</button>
        </div>

        <!-- Upload panel -->
        <div id="imgPanelUpload">
          <div class="ev-upload-zone" id="evUploadZone">
            <div id="evUploadPlaceholder">
              <i class="bi bi-cloud-arrow-up" style="font-size:2rem;opacity:.4"></i>
              <p class="mb-0 mt-1 small">Glissez une image ou cliquez pour charger</p>
              <span class="text-muted" style="font-size:.75rem">JPG, PNG, WebP — max 5 Mo</span>
            </div>
            <div class="d-none" id="evUploadPreviewWrap">
              <img src="" alt="" id="evUploadPreviewImg" style="max-height:200px;border-radius:8px">
            </div>
            <input type="file" id="evUploadInput" accept="image/jpeg,image/png,image/webp" style="display:none">
          </div>
          <button class="btn btn-sm w-100 mt-2" style="background:#bcd2cb;color:#2d4a43" id="evUploadBtn" disabled>
            <i class="bi bi-check-lg"></i> Utiliser cette image
          </button>
        </div>

        <!-- Pixabay panel -->
        <div id="imgPanelPixabay" class="d-none">
          <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="evPixabayInput" placeholder="Rechercher des photos...">
            <select class="form-select" id="evPixabayCat" style="max-width:140px">
              <option value="">Toutes</option>
              <option value="nature">Nature</option>
              <option value="people">Personnes</option>
              <option value="food">Cuisine</option>
              <option value="travel">Voyage</option>
              <option value="buildings">Bâtiments</option>
              <option value="business">Business</option>
              <option value="feelings">Ambiance</option>
            </select>
            <button class="btn btn-outline-dark" id="evPixabaySearchBtn"><i class="bi bi-search"></i></button>
          </div>
          <div class="ev-pixabay-grid" id="evPixabayGrid">
            <div class="text-center text-muted py-4"><i class="bi bi-images" style="font-size:2rem;opacity:.3"></i><p class="mt-1 small">Recherchez des photos libres de droits</p></div>
          </div>
          <div class="text-center mt-2 d-none" id="evPixabayLoading"><span class="spinner-border spinner-border-sm"></span> Recherche...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Liste Inscriptions ── -->
<div class="modal fade" id="evInscritsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evInscritsTitle">Inscriptions</h5>
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="evInscritsBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Admin stat cards ── */
.ev-admin-stat-card {
  display: flex; align-items: center; gap: 12px; padding: 14px 16px;
  border-radius: 14px; border: 1px solid var(--cl-border);
  background: var(--cl-surface, #fff); transition: all .2s;
}
.ev-admin-stat-clickable { cursor: pointer; }
.ev-admin-stat-clickable:hover { border-color: var(--cl-accent); box-shadow: 0 2px 8px rgba(0,0,0,.06); transform: translateY(-1px); }
.ev-admin-stat-icon {
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff;
}
.ev-admin-stat-icon.bg-teal { background: #2d4a43; }
.ev-admin-stat-icon.bg-purple { background: #7C5B8E; }
.ev-admin-stat-value { font-size: 1.2rem; font-weight: 700; line-height: 1.2; }
.ev-admin-stat-label { font-size: .75rem; color: var(--cl-text-muted); font-weight: 500; }
.ev-admin-stat-arrow { margin-left: auto; color: var(--cl-text-muted); font-size: .9rem; }
.ev-admin-desc { font-size: .88rem; line-height: 1.6; white-space: pre-wrap; color: var(--cl-text-secondary); padding: 12px 16px; border-radius: 12px; background: var(--cl-accent-bg, #f4f1ec); border: 1px solid rgba(0,0,0,.04); max-height: 120px; overflow-y: auto; }

/* ── Inscrits table wrap ── */
.ev-inscrits-wrap {
  border: 1.5px solid var(--cl-border, #E8E5E0); border-radius: 14px; overflow: hidden;
}
.ev-inscrits-table { width: 100%; border-collapse: collapse; }
.ev-inscrits-table th { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; color: var(--cl-text-muted); padding: 10px 14px; background: var(--cl-accent-bg, #f8f6f3); border-bottom: 1.5px solid var(--cl-border); }
.ev-inscrits-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border); font-size: .88rem; vertical-align: middle; }
.ev-inscrits-table tr:hover td { background: var(--cl-accent-bg, #f8f6f3); }
.ev-inscrits-table tr:last-child td { border-bottom: none; }
.ev-inscrits-avatar { width: 32px; height: 32px; border-radius: 50%; background: #D0C4D8; color: #5B4B6B; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .6rem; overflow: hidden; vertical-align: middle; margin-right: 8px; }
.ev-inscrits-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── PDF Lightbox ── */
.ev-lightbox {
  position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.85);
  display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);
}
.ev-lightbox-close {
  position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 50%;
  background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 1.2rem;
  cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.ev-lightbox-close:hover { background: rgba(255,255,255,.3); }
.ev-lightbox-content { border-radius: 12px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.ev-lightbox-content iframe { width: 70vw; height: 80vh; border: none; background: #fff; }
.ev-lightbox-actions {
  position: absolute; bottom: 20px; right: 20px; display: flex; gap: 8px;
}
.ev-lightbox-btn {
  padding: 8px 16px; border-radius: 8px; background: rgba(255,255,255,.15); border: none;
  color: #fff; font-size: .85rem; cursor: pointer; display: flex; align-items: center; gap: 6px;
  transition: background .15s;
}
.ev-lightbox-btn:hover { background: rgba(255,255,255,.3); }

/* ── Cover image zone ── */
.ev-cover-zone {
  border: 2px dashed var(--cl-border); border-radius: 10px; cursor: pointer;
  text-align: center; transition: border-color 0.2s; overflow: hidden; position: relative;
}
.ev-cover-zone:hover { border-color: var(--cl-accent); }
.ev-cover-placeholder { padding: 24px; color: var(--cl-text-muted); }
.ev-cover-placeholder i { font-size: 2rem; opacity: 0.4; display: block; margin-bottom: 4px; }
.ev-cover-placeholder span { font-size: 0.82rem; }
.ev-cover-preview img { width: 100%; max-height: 180px; object-fit: cover; display: block; }
.ev-cover-remove {
  position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6); color: #fff;
  border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
}
.ev-cover-remove:hover { background: rgba(192,57,43,0.9); }

/* ── Upload zone ── */
.ev-upload-zone {
  border: 2px dashed var(--cl-border); border-radius: 10px; text-align: center;
  padding: 24px; cursor: pointer; transition: border-color 0.2s;
}
.ev-upload-zone:hover { border-color: var(--cl-accent); }

/* ── Pixabay grid ── */
.ev-pixabay-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
  max-height: 400px; overflow-y: auto;
}
.ev-pixabay-thumb {
  aspect-ratio: 16/10; border-radius: 8px; overflow: hidden; cursor: pointer;
  border: 2px solid transparent; transition: border-color 0.2s;
}
.ev-pixabay-thumb:hover { border-color: var(--cl-accent); }
.ev-pixabay-thumb img { width: 100%; height: 100%; object-fit: cover; }

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
    let imagePickerModal = null;
    let fieldCounter = 0;
    let pendingUploadFile = null;

    // ─── Init ───
    document.addEventListener('DOMContentLoaded', () => {
        formModal = new bootstrap.Modal(document.getElementById('evFormModal'));
        imagePickerModal = new bootstrap.Modal(document.getElementById('evImagePickerModal'));
        window._inscritsModal = new bootstrap.Modal(document.getElementById('evInscritsModal'));
        renderList(initData);

        document.getElementById('btnNewEvent').addEventListener('click', openNewForm);
        document.getElementById('btnAddField').addEventListener('click', addField);
        document.getElementById('btnSaveEvent').addEventListener('click', saveEvent);

        // Image picker
        initImagePicker();

        // Detail card actions delegate
        document.getElementById('evDetailCard').addEventListener('click', (e) => {
            const btn = e.target.closest('[data-ev-action]');
            if (!btn) return;
            const a = btn.dataset.evAction;
            if (a === 'edit') editEvent();
            else if (a === 'toggle') toggleEvent(btn.dataset.statut);
            else if (a === 'delete') deleteEvent();
            else if (a === 'export') exportInscriptions();
            else if (a === 'openInscrits') openInscritsModal();
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

        const inscritsActifs = inscriptions.filter(i => i.statut === 'inscrit');
        const pctFull = ev.max_participants ? Math.round(inscritsActifs.length / ev.max_participants * 100) : 0;

        // Store for modal
        window._evInscritsData = { ev, champs, inscriptions: inscritsActifs };

        // Info cards row
        let statsCards = `
            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="ev-admin-stat-card ev-admin-stat-clickable" data-ev-action="openInscrits">
                        <div class="ev-admin-stat-icon bg-teal"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="ev-admin-stat-value">${inscritsActifs.length}${ev.max_participants ? '<small class="text-muted"> / ' + ev.max_participants + '</small>' : ''}</div>
                            <div class="ev-admin-stat-label">Inscriptions</div>
                        </div>
                        <i class="bi bi-chevron-right ev-admin-stat-arrow"></i>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="ev-admin-stat-card">
                        <div class="ev-admin-stat-icon bg-purple"><i class="bi bi-ui-checks-grid"></i></div>
                        <div>
                            <div class="ev-admin-stat-value">${champs.length}</div>
                            <div class="ev-admin-stat-label">Champs formulaire</div>
                        </div>
                    </div>
                </div>
            </div>`;

        card.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">${escapeHtml(ev.titre)} ${badge}</h5>
                    <div class="small text-muted">${infoHtml}</div>
                </div>
                <div class="d-flex flex-wrap gap-1">${actions}</div>
            </div>
            <div class="card-body ev-detail-body">
                ${ev.image_url ? '<div class="mb-3"><img src="' + escapeHtml(ev.image_url) + '" alt="" style="width:100%;max-height:200px;object-fit:cover;border-radius:10px"></div>' : ''}
                ${ev.description ? '<div class="ev-admin-desc mb-3">' + escapeHtml(ev.description) + '</div>' : ''}
                ${statsCards}
                ${champsHtml}
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
        document.getElementById('evDateLimite').value = '';
        document.getElementById('evStatut').value = 'brouillon';
        document.getElementById('evFieldsList').innerHTML = '';
        fieldCounter = 0;
        toggleFieldsEmpty();
        setCoverImage('');
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
        document.getElementById('evDateLimite').value = (ev.date_limite_inscription || '').replace(' ', 'T').substring(0, 16);
        document.getElementById('evStatut').value = ev.statut;
        setCoverImage(ev.image_url || '');

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
            date_limite_inscription: document.getElementById('evDateLimite').value || null,
            statut: document.getElementById('evStatut').value,
            image_url: document.getElementById('evImageUrl').value || null,
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

    // ─── Image cover helpers ───
    function setCoverImage(url) {
        document.getElementById('evImageUrl').value = url || '';
        const placeholder = document.getElementById('evCoverPlaceholder');
        const preview = document.getElementById('evCoverPreview');
        const img = document.getElementById('evCoverImg');
        if (url) {
            img.src = url;
            placeholder.classList.add('d-none');
            preview.classList.remove('d-none');
        } else {
            img.src = '';
            placeholder.classList.remove('d-none');
            preview.classList.add('d-none');
        }
    }

    function initImagePicker() {
        // Open picker on cover zone click
        document.getElementById('evCoverZone').addEventListener('click', (e) => {
            if (e.target.closest('.ev-cover-remove')) return;
            imagePickerModal.show();
        });

        // Remove cover
        document.getElementById('evCoverRemove').addEventListener('click', (e) => {
            e.stopPropagation();
            setCoverImage('');
        });

        // Tabs switch
        document.querySelectorAll('[data-img-tab]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-img-tab]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('imgPanelUpload').classList.toggle('d-none', btn.dataset.imgTab !== 'upload');
                document.getElementById('imgPanelPixabay').classList.toggle('d-none', btn.dataset.imgTab !== 'pixabay');
            });
        });

        // ── Upload ──
        const uploadZone = document.getElementById('evUploadZone');
        const uploadInput = document.getElementById('evUploadInput');

        uploadZone.addEventListener('click', () => uploadInput.click());
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.style.borderColor = 'var(--cl-accent)'; });
        uploadZone.addEventListener('dragleave', () => { uploadZone.style.borderColor = ''; });
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '';
            if (e.dataTransfer.files.length) previewUpload(e.dataTransfer.files[0]);
        });
        uploadInput.addEventListener('change', () => {
            if (uploadInput.files.length) previewUpload(uploadInput.files[0]);
        });

        document.getElementById('evUploadBtn').addEventListener('click', doUpload);

        // ── Pixabay ──
        document.getElementById('evPixabaySearchBtn').addEventListener('click', searchPixabay);
        document.getElementById('evPixabayInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') searchPixabay();
        });
    }

    function previewUpload(file) {
        if (!file || !file.type.startsWith('image/')) { toast('Fichier non valide', 'error'); return; }
        if (file.size > 5 * 1024 * 1024) { toast('Image trop volumineuse (max 5 Mo)', 'error'); return; }
        pendingUploadFile = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('evUploadPlaceholder').classList.add('d-none');
            document.getElementById('evUploadPreviewWrap').classList.remove('d-none');
            document.getElementById('evUploadPreviewImg').src = e.target.result;
            document.getElementById('evUploadBtn').disabled = false;
        };
        reader.readAsDataURL(file);
    }

    async function doUpload() {
        if (!pendingUploadFile) return;
        const btn = document.getElementById('evUploadBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const formData = new FormData();
        formData.append('file', pendingUploadFile);
        formData.append('action', 'admin_upload_evenement_image');

        try {
            const resp = await fetch('/newspocspace/admin/api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                setCoverImage(res.url);
                imagePickerModal.hide();
                resetUploadPanel();
                toast('Image ajoutée', 'success');
            } else {
                toast(res.message || 'Erreur upload', 'error');
            }
        } catch {
            toast('Erreur upload', 'error');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Utiliser cette image';
    }

    function resetUploadPanel() {
        pendingUploadFile = null;
        document.getElementById('evUploadPlaceholder').classList.remove('d-none');
        document.getElementById('evUploadPreviewWrap').classList.add('d-none');
        document.getElementById('evUploadPreviewImg').src = '';
        document.getElementById('evUploadBtn').disabled = true;
        document.getElementById('evUploadInput').value = '';
    }

    async function searchPixabay() {
        const query = document.getElementById('evPixabayInput').value.trim();
        const category = document.getElementById('evPixabayCat').value;
        if (!query && !category) { toast('Entrez un terme de recherche', 'error'); return; }

        const grid = document.getElementById('evPixabayGrid');
        const loading = document.getElementById('evPixabayLoading');
        grid.innerHTML = '';
        loading.classList.remove('d-none');

        const r = await adminApiPost('admin_search_pixabay', { query, category });
        loading.classList.add('d-none');

        if (!r.success || !r.hits?.length) {
            grid.innerHTML = '<div class="text-center text-muted py-4 small" style="grid-column:1/-1">Aucun résultat</div>';
            return;
        }

        grid.innerHTML = r.hits.map(h =>
            `<div class="ev-pixabay-thumb" data-url="${escapeHtml(h.largeImageURL || h.webformatURL)}">
                <img src="${escapeHtml(h.previewURL)}" alt="" loading="lazy">
            </div>`
        ).join('');

        grid.querySelectorAll('.ev-pixabay-thumb').forEach(thumb => {
            thumb.addEventListener('click', () => selectPixabayImage(thumb.dataset.url));
        });
    }

    async function selectPixabayImage(url) {
        const loading = document.getElementById('evPixabayLoading');
        loading.classList.remove('d-none');

        const r = await adminApiPost('admin_save_pixabay_evenement', { image_url: url });
        loading.classList.add('d-none');

        if (r.success) {
            setCoverImage(r.url);
            imagePickerModal.hide();
            toast('Image Pixabay ajoutée', 'success');
        } else {
            toast(r.message || 'Erreur', 'error');
        }
    }

    // ─── Modal inscriptions ───
    function openInscritsModal() {
        const data = window._evInscritsData;
        if (!data) return;
        const { ev, champs, inscriptions } = data;

        document.getElementById('evInscritsTitle').textContent = `Inscriptions — ${ev.titre} (${inscriptions.length})`;
        const body = document.getElementById('evInscritsBody');

        if (!inscriptions.length) {
            body.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-people" style="font-size:2rem;opacity:.3"></i><p class="mt-2">Aucune inscription</p></div>';
            window._inscritsModal.show();
            return;
        }

        let thExtra = champs.map(c => `<th>${escapeHtml(c.label)}</th>`).join('');
        let rows = inscriptions.map((ins, idx) => {
            const initials = ((ins.prenom || '')[0] || '') + ((ins.nom || '')[0] || '');
            let tdExtra = champs.map(c => {
                const v = (ins.valeurs || []).find(val => val.champ_id === c.id);
                let display = v ? v.valeur : '—';
                if (v && c.type === 'checkbox') { try { display = JSON.parse(display).join(', '); } catch(e) {} }
                return `<td>${escapeHtml(display)}</td>`;
            }).join('');
            const d = new Date(ins.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
            return `<tr>
                <td class="text-muted">${idx + 1}</td>
                <td>
                    <span class="ev-inscrits-avatar">${escapeHtml(initials)}</span>
                    <strong>${escapeHtml(ins.prenom)} ${escapeHtml(ins.nom)}</strong>
                </td>
                <td class="text-muted">${escapeHtml(ins.email)}</td>
                <td class="text-muted">${d}</td>
                ${tdExtra}
            </tr>`;
        }).join('');

        body.innerHTML = `
            <div class="d-flex flex-wrap gap-2 mb-3 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnInscritsCsv"><i class="bi bi-download"></i> Export CSV</button>
                <button type="button" class="btn btn-sm btn-outline-dark" id="btnInscritsPdf"><i class="bi bi-file-earmark-pdf"></i> Exporter PDF</button>
                <button type="button" class="btn btn-sm btn-outline-dark" id="btnInscritsShare"><i class="bi bi-share"></i> Partager</button>
                <button type="button" class="btn btn-sm" style="background:#bcd2cb;color:#2d4a43" id="btnInscritsEmail"><i class="bi bi-envelope"></i> Envoyer par email</button>
            </div>
            <div class="ev-inscrits-wrap">
                <table class="ev-inscrits-table">
                    <thead><tr>
                        <th style="width:40px">#</th><th>Participant</th><th>Email</th><th>Inscrit le</th>
                        ${thExtra}
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;

        // Re-attach button handlers (they are inside the body now)
        document.getElementById('btnInscritsPdf').addEventListener('click', exportPdf);
        document.getElementById('btnInscritsShare').addEventListener('click', shareInscrits);
        document.getElementById('btnInscritsEmail').addEventListener('click', emailInscrits);
        document.getElementById('btnInscritsCsv').addEventListener('click', exportInscriptions);

        window._inscritsModal.show();
    }

    function exportPdf() {
        const data = window._evInscritsData;
        if (!data || !data.inscriptions.length) { toast('Aucune inscription', 'error'); return; }
        const { ev, champs, inscriptions } = data;

        const headers = ['#', 'Prénom', 'Nom', 'Email', 'Inscrit le', ...champs.map(c => c.label)];
        let tableRows = inscriptions.map((ins, idx) => {
            const vals = champs.map(c => {
                const v = (ins.valeurs || []).find(val => val.champ_id === c.id);
                let d = v ? v.valeur : '';
                if (v && c.type === 'checkbox') { try { d = JSON.parse(d).join(', '); } catch(e) {} }
                return d;
            });
            const date = new Date(ins.created_at).toLocaleDateString('fr-FR');
            return [idx + 1, ins.prenom, ins.nom, ins.email, date, ...vals];
        });

        const htmlContent = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Inscriptions — ${ev.titre}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 30px; color: #333; }
                h1 { font-size: 18px; margin-bottom: 4px; }
                .meta { font-size: 13px; color: #888; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 13px; }
                th { background: #f4f1ec; padding: 8px 10px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
                td { padding: 7px 10px; border-bottom: 1px solid #eee; }
                @media print { body { padding: 10px; } }
            </style></head><body>
            <h1>${ev.titre}</h1>
            <div class="meta">${ev.date_debut}${ev.lieu ? ' · ' + ev.lieu : ''} · ${inscriptions.length} inscrit(s)</div>
            <table><thead><tr>${headers.map(h => '<th>' + h + '</th>').join('')}</tr></thead>
            <tbody>${tableRows.map(r => '<tr>' + r.map(c => '<td>' + (c || '—') + '</td>').join('') + '</tr>').join('')}</tbody></table>
            </body></html>`;

        openPdfLightbox(htmlContent);
    }

    function openPdfLightbox(htmlContent) {
        document.getElementById('evPdfLightbox')?.remove();
        const lb = document.createElement('div');
        lb.id = 'evPdfLightbox';
        lb.className = 'ev-lightbox';
        lb.innerHTML = `
            <button class="ev-lightbox-close" title="Fermer"><i class="bi bi-x-lg"></i></button>
            <div class="ev-lightbox-content">
                <iframe id="evPdfIframe"></iframe>
            </div>
            <div class="ev-lightbox-actions">
                <button class="ev-lightbox-btn" id="evLbPrint" title="Imprimer / PDF"><i class="bi bi-printer"></i> Imprimer</button>
            </div>
        `;
        document.body.appendChild(lb);

        const iframe = document.getElementById('evPdfIframe');
        iframe.srcdoc = htmlContent;

        const closeLb = () => lb.remove();
        lb.querySelector('.ev-lightbox-close').addEventListener('click', closeLb);
        lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { closeLb(); document.removeEventListener('keydown', esc); }
        });
        document.getElementById('evLbPrint').addEventListener('click', () => {
            if (iframe?.contentWindow) iframe.contentWindow.print();
        });
    }

    async function shareInscrits() {
        const data = window._evInscritsData;
        if (!data || !data.inscriptions.length) { toast('Aucune inscription', 'error'); return; }
        const { ev, champs, inscriptions } = data;

        // Build CSV content for internal share
        const csvData = await adminApiPost('admin_export_evenement_inscriptions', { id: ev.id });
        if (!csvData.success) { toast('Erreur', 'error'); return; }

        // Compose internal message
        let body = `<strong>Liste des inscriptions — ${ev.titre}</strong><br>`;
        body += `${ev.date_debut}${ev.lieu ? ' · ' + ev.lieu : ''}<br><br>`;
        body += `<table style="border-collapse:collapse;width:100%"><tr style="background:#f4f1ec"><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Nom</th>`;
        champs.forEach(c => { body += `<th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">${c.label}</th>`; });
        body += '</tr>';
        inscriptions.forEach(ins => {
            body += `<tr><td style="padding:5px 8px;border-bottom:1px solid #eee">${ins.prenom} ${ins.nom}</td>`;
            champs.forEach(c => {
                const v = (ins.valeurs || []).find(val => val.champ_id === c.id);
                let d = v ? v.valeur : '—';
                if (v && c.type === 'checkbox') { try { d = JSON.parse(d).join(', '); } catch(e) {} }
                body += `<td style="padding:5px 8px;border-bottom:1px solid #eee">${d}</td>`;
            });
            body += '</tr>';
        });
        body += '</table>';

        // Open admin internal message
        window._inscritsModal.hide();
        AdminURL.go('messages');
        setTimeout(() => {
            window.__SS_ADMIN_COMPOSE__ = { subject: `Inscriptions — ${ev.titre}`, body };
        }, 500);
        toast('Redirigé vers la messagerie', 'success');
    }

    function emailInscrits() {
        const data = window._evInscritsData;
        if (!data || !data.inscriptions.length) { toast('Aucune inscription', 'error'); return; }
        const { ev, inscriptions } = data;

        // Generate mailto with summary
        const names = inscriptions.map(i => i.prenom + ' ' + i.nom).join(', ');
        const subject = encodeURIComponent(`Inscriptions — ${ev.titre}`);
        const body = encodeURIComponent(
            `Bonjour,\n\nVoici la liste des inscrits pour l'événement "${ev.titre}" du ${ev.date_debut}` +
            `${ev.lieu ? ' à ' + ev.lieu : ''}:\n\n` +
            inscriptions.map((i, idx) => `${idx + 1}. ${i.prenom} ${i.nom} (${i.email})`).join('\n') +
            `\n\nTotal: ${inscriptions.length} inscrit(s).\n\nCordialement`
        );

        window.open(`mailto:?subject=${subject}&body=${body}`, '_self');
        toast('Client email ouvert', 'success');
    }

    function debounce(fn, delay) {
        let t; return function(...args) { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

})();
</script>
