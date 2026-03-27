<!-- Notes Page -->
<link rel="stylesheet" href="/zerdatime/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">

<style>
/* Category badges */
.note-cat { font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
.note-cat-idee        { background: #E8E5E0; color: var(--cl-text); }
.note-cat-probleme    { background: #F5E6E0; color: #9B2C2C; }
.note-cat-decision    { background: #bcd2cb; color: #2d4a43; }
.note-cat-rappel      { background: #E8E0D0; color: #6B5B3E; }
.note-cat-observation { background: #DDE5F0; color: #3B4F6B; }
.note-cat-autre       { background: #F0EDE8; color: var(--cl-text-secondary); }

/* Note cards grid */
.notes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

.note-card {
  background: var(--cl-surface, #fff);
  border: 1px solid var(--cl-border-light, #F0EDE8);
  border-radius: var(--cl-radius-sm, 8px);
  padding: 16px;
  transition: all 0.2s;
  cursor: pointer;
  position: relative;
  display: flex; flex-direction: column;
}
.note-card:hover {
  box-shadow: var(--cl-shadow-md);
  border-color: var(--cl-border-hover, #D4D0CA);
}
.note-card.pinned { border-left: 3px solid var(--cl-accent, #191918); }

.note-card-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 6px; line-height: 1.3; }
.note-card-body { font-size: 0.82rem; color: var(--cl-text-secondary); line-height: 1.5; flex: 1;
  overflow: hidden; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; }
.note-card-footer { font-size: 0.72rem; color: var(--cl-text-muted); margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--cl-border-light, #F0EDE8); display: flex; justify-content: space-between; align-items: center; }

/* Pin icon on card */
.note-pin {
  position: absolute; top: 8px; right: 8px;
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: transparent; border: none; color: var(--cl-text-muted);
  font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease;
}
.note-pin:hover { background: rgba(139,115,85,.12); color: #6B5B3E; }
.note-card.pinned .note-pin { color: #6B5B3E; }
.note-card.pinned .note-pin:hover { background: rgba(139,115,85,.12); }

/* Buttons */
.note-btn-primary { background: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 600; border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s; }
.note-btn-primary:hover { background: var(--cl-accent-hover, #000); color: #fff; }

.note-btn-del { color: var(--cl-text-secondary); background: transparent; border: none; transition: color 0.2s; }
.note-btn-del:hover { color: #9B2C2C; }

/* ── Sliding pill filter tabs (same pattern as sondages) ── */
.note-filter-tabs {
  display: inline-flex; position: relative; background: #ECEAE5;
  border-radius: 10px; padding: 3px; gap: 0; overflow-x: auto;
  scrollbar-width: none; -ms-overflow-style: none;
}
.note-filter-tabs::-webkit-scrollbar { display: none; }

.note-filter-tabs-slider {
  position: absolute; top: 3px; left: 3px;
  height: calc(100% - 6px);
  background: var(--cl-surface);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 0; pointer-events: none;
}

.note-filter-tab {
  background: none; border: none; padding: 6px 12px;
  font-size: 0.78rem; font-weight: 500; color: var(--cl-text-secondary);
  border-radius: 8px; cursor: pointer; transition: color 0.25s ease;
  position: relative; z-index: 1; text-align: center; white-space: nowrap;
}
.note-filter-tab:hover { color: var(--cl-text); }
.note-filter-tab.active { color: var(--cl-text); font-weight: 600; }

/* Pin toggle button */
.note-pin-toggle {
  background: transparent; border: 1px solid var(--cl-border, #E8E5E0); color: var(--cl-text-secondary);
  font-size: 0.88rem; padding: 5px 10px; border-radius: 8px; transition: all 0.2s ease; flex-shrink: 0;
  cursor: pointer; line-height: 1;
}
.note-pin-toggle:hover { background: rgba(139,115,85,.1); color: #6B5B3E; border-color: #D4C4A8; }
.note-pin-toggle.active {
  background: var(--cl-accent, #191918); color: #fff; border-color: var(--cl-accent, #191918);
}

/* Confirm delete modal */
.note-confirm-icon { width: 48px; height: 48px; border-radius: 50%; background: #F5E6E0; color: #9B2C2C; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.3rem; }
.note-btn-confirm-del { background: #9B2C2C; border: none; color: #fff; font-weight: 600; border-radius: var(--cl-radius-sm, 8px); }
.note-btn-confirm-del:hover { background: #7B1F1F; color: #fff; }

/* Editor in modal */
#noteEditorContainer { min-height: 200px; border: 1px solid var(--cl-border, #E8E5E0); border-radius: 6px; }
#noteEditorContainer .zs-ed-content { min-height: 180px; padding: 0.8rem; }
#noteEditorContainer .zs-ed-content .ProseMirror { min-height: 180px; outline: none; }

/* Color picker */
.note-color-opt { width: 24px; height: 24px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; transition: all 0.15s; }
.note-color-opt:hover, .note-color-opt.active { border-color: var(--cl-accent); transform: scale(1.15); }

/* Modal content shared */
.note-modal-content { border-radius: var(--cl-radius, 16px); border: none; box-shadow: var(--cl-shadow-md); }

/* Color swatch backgrounds */
.note-color-bg-default { background: #F7F5F2; }
.note-color-bg-yellow  { background: #FFF8E1; }
.note-color-bg-green   { background: #E8F5E9; }
.note-color-bg-blue    { background: #E3F2FD; }
.note-color-bg-pink    { background: #FCE4EC; }
.note-color-bg-purple  { background: #F3E5F5; }

/* Empty state */
.note-empty-state { grid-column: 1/-1; }
.note-empty-icon { font-size: 2rem; }
</style>

<div class="container-fluid py-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-journal-text"></i> Notes</h1>
    <button class="btn note-btn-primary" id="btnNewNote"><i class="bi bi-plus-lg"></i> Nouvelle note</button>
  </div>

  <!-- Filters — sliding pill tabs -->
  <div class="d-flex align-items-center gap-3 mb-4" id="noteFiltersBar">
    <div class="note-filter-tabs" id="noteFilterTabs">
      <div class="note-filter-tabs-slider"></div>
      <button class="note-filter-tab active" data-cat="">Toutes</button>
      <button class="note-filter-tab" data-cat="idee">Idée</button>
      <button class="note-filter-tab" data-cat="probleme">Problème</button>
      <button class="note-filter-tab" data-cat="decision">Décision</button>
      <button class="note-filter-tab" data-cat="rappel">Rappel</button>
      <button class="note-filter-tab" data-cat="observation">Observation</button>
      <button class="note-filter-tab" data-cat="autre">Autre</button>
    </div>
    <button class="btn btn-sm note-pin-toggle" id="btnFilterPinned" title="Épinglées seulement">
      <i class="bi bi-pin-angle"></i>
    </button>
  </div>

  <!-- Notes grid -->
  <div class="notes-grid" id="notesGrid">
    <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>
  </div>
</div>

<!-- Modal: Create/Edit Note -->
<div class="modal fade" id="modalNote" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content note-modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold" id="modalNoteTitle">Nouvelle note</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="noteEditId">
        <div class="mb-3">
          <label class="form-label small fw-bold">Titre *</label>
          <input type="text" class="form-control form-control-sm" id="noteTitre" placeholder="Ex: Idée amélioration planning">
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small">Catégorie</label>
            <div class="zs-select" id="noteCategorie" data-placeholder="Catégorie"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Couleur</label>
            <div class="d-flex gap-2 mt-1" id="noteColorPicker">
              <div class="note-color-opt note-color-bg-default active" data-color="#F7F5F2"></div>
              <div class="note-color-opt note-color-bg-yellow" data-color="#FFF8E1"></div>
              <div class="note-color-opt note-color-bg-green" data-color="#E8F5E9"></div>
              <div class="note-color-opt note-color-bg-blue" data-color="#E3F2FD"></div>
              <div class="note-color-opt note-color-bg-pink" data-color="#FCE4EC"></div>
              <div class="note-color-opt note-color-bg-purple" data-color="#F3E5F5"></div>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Contenu</label>
          <div id="noteEditorContainer"></div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm note-btn-primary px-4" id="btnSaveNote">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Delete -->
<div class="modal fade" id="modalDeleteNote" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content note-modal-content">
      <div class="modal-body text-center py-4 px-4">
        <div class="note-confirm-icon"><i class="bi bi-trash3"></i></div>
        <h6 class="fw-bold mb-2">Supprimer cette note ?</h6>
        <p class="text-muted small mb-0">Cette action est irréversible.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm px-4 note-btn-confirm-del" id="btnConfirmDeleteNote">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {

let editorModule = null;
let editorInstance = null;
let allNotes = [];
let deleteTargetId = null;
let pinnedFilter = false;
let selectedColor = '#F7F5F2';
let currentCatFilter = '';

const CAT_LABELS = { idee: 'Idée', probleme: 'Problème', decision: 'Décision', rappel: 'Rappel', observation: 'Observation', autre: 'Autre' };
const CAT_OPTIONS = Object.entries(CAT_LABELS).map(([v, l]) => ({ value: v, label: l }));

async function initNotesPage() {
  editorModule = await import('/zerdatime/assets/js/rich-editor.js');

  // Init category select
  zerdaSelect.init('#noteCategorie', CAT_OPTIONS, { value: 'autre' });

  // Sliding pill filter tabs
  const filterTabs = document.querySelectorAll('.note-filter-tab');
  const filterSlider = document.querySelector('.note-filter-tabs-slider');
  function positionSlider(tab) {
    if (!filterSlider || !tab) return;
    filterSlider.style.width = tab.offsetWidth + 'px';
    filterSlider.style.transform = 'translateX(' + (tab.offsetLeft - 3) + 'px)';
  }
  // Init slider position
  requestAnimationFrame(() => positionSlider(document.querySelector('.note-filter-tab.active')));

  filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      positionSlider(tab);
      currentCatFilter = tab.dataset.cat || '';
      loadNotes();
    });
  });

  // Topbar search
  const topbarSearch = document.getElementById('topbarSearchInput');
  if (topbarSearch) {
    topbarSearch.addEventListener('input', debounce(loadNotes, 300));
  }

  // Pin toggle
  const btnPinned = document.getElementById('btnFilterPinned');
  btnPinned.addEventListener('click', () => {
    pinnedFilter = !pinnedFilter;
    btnPinned.classList.toggle('active', pinnedFilter);
    loadNotes();
  });

  // New note
  document.getElementById('btnNewNote').addEventListener('click', openNewNote);

  // Save note
  document.getElementById('btnSaveNote').addEventListener('click', saveNote);

  // Color picker
  document.querySelectorAll('#noteColorPicker .note-color-opt').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('#noteColorPicker .note-color-opt').forEach(o => o.classList.remove('active'));
      el.classList.add('active');
      selectedColor = el.dataset.color;
    });
  });

  // Delete confirm
  document.getElementById('btnConfirmDeleteNote').addEventListener('click', confirmDelete);

  await loadNotes();
}

async function loadNotes() {
  const topbarSearch = document.getElementById('topbarSearchInput');
  const params = {
    categorie: currentCatFilter,
    search: topbarSearch ? topbarSearch.value.trim() : '',
  };
  if (pinnedFilter) params.pinned = '1';

  const res = await adminApiPost('admin_get_notes', params);
  if (!res.success) { toast('Erreur'); return; }
  allNotes = res.notes || [];
  renderNotes();
}

function renderNotes() {
  const grid = document.getElementById('notesGrid');

  if (allNotes.length === 0) {
    grid.innerHTML = '<div class="text-center py-5 text-muted note-empty-state"><i class="bi bi-journal-text note-empty-icon"></i><div class="mt-2">Aucune note</div></div>';
    return;
  }

  grid.innerHTML = allNotes.map(n => {
    const plainText = stripHtml(n.contenu || '');
    return `
      <div class="note-card ${n.is_pinned ? 'pinned' : ''}" data-note-id="${n.id}" style="background-color: ${n.couleur || '#F7F5F2'};">
        <button class="note-pin" data-pin-note="${n.id}" title="${n.is_pinned ? 'Désépingler' : 'Épingler'}">
          <i class="bi bi-pin${n.is_pinned ? '-fill' : '-angle'}"></i>
        </button>
        <div class="note-card-title">${escapeHtml(n.titre)}</div>
        <div class="note-card-body">${escapeHtml(plainText)}</div>
        <div class="note-card-footer">
          <div>
            <span class="note-cat note-cat-${n.categorie}">${CAT_LABELS[n.categorie] || n.categorie}</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span>${new Date(n.updated_at).toLocaleDateString('fr-FR')}</span>
            <button class="note-btn-del" data-del-note="${n.id}" title="Supprimer"><i class="bi bi-trash3"></i></button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  // Events
  grid.querySelectorAll('[data-note-id]').forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('[data-pin-note]') || e.target.closest('[data-del-note]')) return;
      openEditNote(card.dataset.noteId);
    });
  });

  grid.querySelectorAll('[data-pin-note]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      await adminApiPost('admin_toggle_pin_note', { id: btn.dataset.pinNote });
      loadNotes();
    });
  });

  grid.querySelectorAll('[data-del-note]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      deleteTargetId = btn.dataset.delNote;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDeleteNote')).show();
    });
  });
}

async function openNewNote() {
  document.getElementById('noteEditId').value = '';
  document.getElementById('noteTitre').value = '';
  zerdaSelect.setValue('#noteCategorie', 'autre');
  selectedColor = '#F7F5F2';
  document.querySelectorAll('#noteColorPicker .note-color-opt').forEach(o => o.classList.toggle('active', o.dataset.color === selectedColor));
  document.getElementById('modalNoteTitle').textContent = 'Nouvelle note';

  await initEditor('');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNote')).show();
}

async function openEditNote(id) {
  const n = allNotes.find(x => x.id === id);
  if (!n) return;

  document.getElementById('noteEditId').value = n.id;
  document.getElementById('noteTitre').value = n.titre;
  zerdaSelect.setValue('#noteCategorie', n.categorie);
  selectedColor = n.couleur || '#F7F5F2';
  document.querySelectorAll('#noteColorPicker .note-color-opt').forEach(o => o.classList.toggle('active', o.dataset.color === selectedColor));
  document.getElementById('modalNoteTitle').textContent = 'Modifier la note';

  await initEditor(n.contenu || '');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNote')).show();
}

async function initEditor(content) {
  const container = document.getElementById('noteEditorContainer');
  // Destroy previous editor
  if (editorInstance && editorInstance.destroy) {
    try { editorInstance.destroy(); } catch(e) {}
  }
  container.innerHTML = '';

  editorInstance = await editorModule.createEditor(container, {
    placeholder: 'Écrivez votre note...',
    content: content,
    mode: 'full'
  });
}

async function saveNote() {
  const titre = document.getElementById('noteTitre').value.trim();
  if (!titre) { toast('Titre requis'); return; }

  const editId = document.getElementById('noteEditId').value;
  const contenu = editorInstance && editorModule ? editorModule.getHTML(editorInstance) : '';

  const data = {
    titre,
    contenu,
    categorie: zerdaSelect.getValue('#noteCategorie'),
    couleur: selectedColor,
  };

  const btn = document.getElementById('btnSaveNote');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  if (editId) {
    data.id = editId;
    await adminApiPost('admin_update_note', data);
  } else {
    await adminApiPost('admin_create_note', data);
  }

  btn.disabled = false;
  btn.textContent = 'Enregistrer';
  bootstrap.Modal.getInstance(document.getElementById('modalNote')).hide();
  toast(editId ? 'Note modifiée' : 'Note créée');
  loadNotes();
}

async function confirmDelete() {
  if (!deleteTargetId) return;
  const btn = document.getElementById('btnConfirmDeleteNote');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  await adminApiPost('admin_delete_note', { id: deleteTargetId });
  btn.disabled = false;
  btn.textContent = 'Supprimer';
  deleteTargetId = null;
  bootstrap.Modal.getInstance(document.getElementById('modalDeleteNote')).hide();
  toast('Note supprimée');
  loadNotes();
}

function stripHtml(html) {
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  return tmp.textContent || tmp.innerText || '';
}

function debounce(fn, ms) {
  let t;
  return function() { clearTimeout(t); t = setTimeout(fn, ms); };
}

window.initNotesPage = initNotesPage;
})();
</script>
