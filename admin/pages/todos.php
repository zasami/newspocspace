<!-- Todos Page -->
<style>
/* View tabs */
.todo-views .btn { border-radius: var(--cl-radius-sm, 8px); font-weight: 500; font-size: 0.85rem; }
.todo-views .btn.active { background: var(--cl-accent, #191918); color: #fff; border-color: var(--cl-accent); }
.todo-views .btn:not(.active) { background: transparent; color: var(--cl-text-secondary); border-color: var(--cl-border); }

/* Priority dots */
.todo-prio { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; flex-shrink: 0; }
.todo-prio-urgente { background: #C53030; }
.todo-prio-haute   { background: #D97757; }
.todo-prio-normale { background: #9B9B9B; }
.todo-prio-basse   { background: #bcd2cb; }

/* Status badges */
.todo-stat { font-size: 0.75rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
.todo-stat-a_faire   { background: #F0EDE8; color: var(--cl-text-secondary); }
.todo-stat-en_cours  { background: #E8E5E0; color: var(--cl-text); }
.todo-stat-termine   { background: #bcd2cb; color: #2d4a43; }
.todo-stat-annule    { background: #F5E6E0; color: #9B2C2C; }

/* Overdue */
.todo-overdue { color: #C53030; font-weight: 600; }

/* Task row */
.todo-row { border-bottom: 1px solid var(--cl-border-light, #F0EDE8); padding: 10px 0; transition: background 0.15s; cursor: pointer; }
.todo-row:hover { background: rgba(25,25,24,0.02); }
.todo-row.done .todo-title { text-decoration: line-through; color: var(--cl-text-muted); }

/* Week day header */
.week-day-header { font-weight: 700; font-size: 0.85rem; color: var(--cl-text-secondary); padding: 8px 0 4px; border-bottom: 2px solid var(--cl-border); margin-top: 12px; }
.week-day-today { color: var(--cl-accent); border-color: var(--cl-accent); }

/* Stat cards */
.todo-stat-card { text-align: center; padding: 16px 8px; border-radius: var(--cl-radius-sm, 8px); background: var(--cl-surface, #fff); border: 1px solid var(--cl-border-light, #F0EDE8); }
.todo-stat-card .num { font-size: 1.6rem; font-weight: 700; line-height: 1; }
.todo-stat-card .lbl { font-size: 0.75rem; color: var(--cl-text-muted); margin-top: 4px; }

/* Buttons */
.todo-btn-primary { background: var(--cl-accent, #191918); border: none; color: #fff; font-weight: 600; border-radius: var(--cl-radius-sm, 8px); transition: all 0.2s; }
.todo-btn-primary:hover { background: var(--cl-accent-hover, #000); color: #fff; }
.todo-btn-del { color: var(--cl-text-secondary); border: 1px solid var(--cl-border); background: transparent; border-radius: 6px; padding: 2px 7px; transition: all 0.2s; }
.todo-btn-del:hover { background: #F5E6E0; color: #9B2C2C; border-color: #E2B8AE; }

/* Confirm delete modal */
.todo-confirm-icon { width: 48px; height: 48px; border-radius: 50%; background: #F5E6E0; color: #9B2C2C; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.3rem; }
.todo-btn-confirm-del { background: #9B2C2C; border: none; color: #fff; font-weight: 600; border-radius: var(--cl-radius-sm, 8px); }
.todo-btn-confirm-del:hover { background: #7B1F1F; color: #fff; }

/* Modal content shared */
.todo-modal-content { border-radius: var(--cl-radius, 16px); border: none; box-shadow: var(--cl-shadow-md); }

/* Urgent stat color */
.todo-stat-urgent-color { color: #C53030; }

/* Width auto select */
.todo-select-auto { width: auto; }

/* Inline status select in row */
.todo-status-select { width: auto; font-size: 0.75rem; padding: 2px 24px 2px 6px; }

/* Empty state icon */
.todo-empty-icon { font-size: 2rem; }

/* Check icon size */
.todo-check-icon { font-size: 0.8rem; }

/* Checkbox custom */
.todo-check { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--cl-border-hover); background: transparent; cursor: pointer; flex-shrink: 0; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.todo-check:hover { border-color: var(--cl-accent); }
.todo-check.checked { background: #bcd2cb; border-color: #bcd2cb; color: #2d4a43; }
</style>

<div class="container-fluid py-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-check2-square"></i> Tâches</h1>
    <button class="btn todo-btn-primary" id="btnNewTodo"><i class="bi bi-plus-lg"></i> Nouvelle tâche</button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4" id="todoStats">
    <div class="col"><div class="todo-stat-card"><div class="num" id="statTotal">—</div><div class="lbl">Total</div></div></div>
    <div class="col"><div class="todo-stat-card"><div class="num" id="statAFaire">—</div><div class="lbl">À faire</div></div></div>
    <div class="col"><div class="todo-stat-card"><div class="num" id="statEnCours">—</div><div class="lbl">En cours</div></div></div>
    <div class="col"><div class="todo-stat-card"><div class="num" id="statTermine">—</div><div class="lbl">Terminé</div></div></div>
    <div class="col"><div class="todo-stat-card"><div class="num todo-overdue" id="statRetard">—</div><div class="lbl">En retard</div></div></div>
    <div class="col"><div class="todo-stat-card"><div class="num todo-stat-urgent-color" id="statUrgent">—</div><div class="lbl">Urgentes</div></div></div>
  </div>

  <!-- Views + Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="btn-group todo-views">
          <button class="btn btn-sm active" data-vue="today"><i class="bi bi-sun"></i> Aujourd'hui</button>
          <button class="btn btn-sm" data-vue="week"><i class="bi bi-calendar-week"></i> Semaine</button>
          <button class="btn btn-sm" data-vue="all"><i class="bi bi-list-task"></i> Toutes</button>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <div class="zs-select todo-select-auto" id="filterStatut" data-placeholder="Tous statuts"></div>
          <div class="zs-select todo-select-auto" id="filterPriorite" data-placeholder="Toutes priorités"></div>
          <div class="zs-select todo-select-auto" id="filterAssigned" data-placeholder="Tous"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tasks list -->
  <div class="card">
    <div class="card-body p-3" id="todoList">
      <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>
</div>

<!-- Modal: Create/Edit Todo -->
<div class="modal fade" id="modalTodo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content todo-modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold" id="modalTodoTitle">Nouvelle tâche</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="todoEditId">
        <div class="mb-3">
          <label class="form-label small fw-bold">Titre *</label>
          <input type="text" class="form-control form-control-sm" id="todoTitre" placeholder="Ex: Vérifier planning semaine">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Description</label>
          <textarea class="form-control form-control-sm" id="todoDesc" rows="3" placeholder="Détails..."></textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Priorité</label>
            <div class="zs-select" id="todoPriorite" data-placeholder="Priorité"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Échéance</label>
            <input type="date" class="form-control form-control-sm" id="todoDate">
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label small">Assigner à</label>
          <div class="zs-select" id="todoAssigned" data-placeholder="— Non assigné —"></div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm todo-btn-primary px-4" id="btnSaveTodo">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Delete -->
<div class="modal fade" id="modalDeleteTodo" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content todo-modal-content">
      <div class="modal-body text-center py-4 px-4">
        <div class="todo-confirm-icon"><i class="bi bi-trash3"></i></div>
        <h6 class="fw-bold mb-2">Supprimer cette tâche ?</h6>
        <p class="text-muted small mb-0">Cette action est irréversible.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm px-4 todo-btn-confirm-del" id="btnConfirmDeleteTodo">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {

let allTodos = [];
let allUsers = [];
let currentVue = 'today';
let deleteTargetId = null;

const PRIO_LABELS = { urgente: 'Urgente', haute: 'Haute', normale: 'Normale', basse: 'Basse' };
const STAT_LABELS = { a_faire: 'À faire', en_cours: 'En cours', termine: 'Terminé', annule: 'Annulé' };
const DAYS_FR = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

async function initTodosPage() {
  // Init filter selects
  zerdaSelect.init('#filterStatut', [
    { value: '', label: 'Tous statuts' },
    { value: 'a_faire', label: 'À faire' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'termine', label: 'Terminé' },
    { value: 'annule', label: 'Annulé' }
  ], { value: '', onSelect: loadTodos });

  zerdaSelect.init('#filterPriorite', [
    { value: '', label: 'Toutes priorités' },
    { value: 'urgente', label: 'Urgente' },
    { value: 'haute', label: 'Haute' },
    { value: 'normale', label: 'Normale' },
    { value: 'basse', label: 'Basse' }
  ], { value: '', onSelect: loadTodos });

  // Init modal selects
  zerdaSelect.init('#todoPriorite', [
    { value: 'basse', label: 'Basse' },
    { value: 'normale', label: 'Normale' },
    { value: 'haute', label: 'Haute' },
    { value: 'urgente', label: 'Urgente' }
  ], { value: 'normale' });

  // Load users for assignment
  const uRes = await adminApiPost('admin_get_todo_users');
  allUsers = uRes.users || [];
  const userOpts = allUsers.map(u => ({ value: u.id, label: `${u.prenom} ${u.nom}` }));

  zerdaSelect.init('#todoAssigned', userOpts, { value: '', search: true });
  zerdaSelect.init('#filterAssigned', userOpts, { value: '', search: true, onSelect: loadTodos });

  // View tabs
  document.querySelectorAll('.todo-views .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.todo-views .btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentVue = btn.dataset.vue;
      loadTodos();
    });
  });

  // Topbar global search
  const topbarInput = document.getElementById('topbarSearchInput');
  if (topbarInput) topbarInput.addEventListener('input', debounce(loadTodos, 300));

  // New todo
  document.getElementById('btnNewTodo').addEventListener('click', () => {
    document.getElementById('todoEditId').value = '';
    document.getElementById('todoTitre').value = '';
    document.getElementById('todoDesc').value = '';
    zerdaSelect.setValue('#todoPriorite', 'normale');
    document.getElementById('todoDate').value = '';
    zerdaSelect.setValue('#todoAssigned', '');
    document.getElementById('modalTodoTitle').textContent = 'Nouvelle tâche';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTodo')).show();
  });

  // Save todo
  document.getElementById('btnSaveTodo').addEventListener('click', saveTodo);

  // Delete confirm
  document.getElementById('btnConfirmDeleteTodo').addEventListener('click', confirmDelete);

  await loadTodos();
}

async function loadTodos() {
  const params = {
    vue: currentVue,
    statut: zerdaSelect.getValue('#filterStatut'),
    priorite: zerdaSelect.getValue('#filterPriorite'),
    assigned_to: zerdaSelect.getValue('#filterAssigned'),
    search: (document.getElementById('topbarSearchInput') || {}).value || '',
  };

  const res = await adminApiPost('admin_get_todos', params);
  if (!res.success) { toast('Erreur'); return; }

  allTodos = res.todos || [];
  const stats = res.stats || {};

  // Update stats
  document.getElementById('statTotal').textContent = stats.total || 0;
  document.getElementById('statAFaire').textContent = stats.a_faire || 0;
  document.getElementById('statEnCours').textContent = stats.en_cours || 0;
  document.getElementById('statTermine').textContent = stats.termine || 0;
  document.getElementById('statRetard').textContent = stats.en_retard || 0;
  document.getElementById('statUrgent').textContent = stats.urgentes || 0;

  renderTodos();
}

function renderTodos() {
  const container = document.getElementById('todoList');

  if (allTodos.length === 0) {
    container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-check2-all todo-empty-icon"></i><div class="mt-2">Aucune tâche</div></div>';
    return;
  }

  if (currentVue === 'week') {
    renderWeekView(container);
  } else {
    renderListView(container);
  }
}

function renderListView(container) {
  container.innerHTML = allTodos.map(t => buildTodoRow(t)).join('');
  attachRowEvents(container);
}

function renderWeekView(container) {
  const today = new Date();
  const monday = new Date(today);
  monday.setDate(today.getDate() - ((today.getDay() + 6) % 7));

  let html = '';
  for (let d = 0; d < 7; d++) {
    const day = new Date(monday);
    day.setDate(monday.getDate() + d);
    const dateStr = day.toISOString().slice(0, 10);
    const isToday = dateStr === today.toISOString().slice(0, 10);
    const dayTodos = allTodos.filter(t => t.date_echeance === dateStr);

    html += `<div class="week-day-header ${isToday ? 'week-day-today' : ''}">${DAYS_FR[day.getDay()]} ${day.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}${isToday ? ' — Aujourd\'hui' : ''}</div>`;

    if (dayTodos.length === 0) {
      html += '<div class="text-muted small py-2 ps-2">Aucune tâche</div>';
    } else {
      html += dayTodos.map(t => buildTodoRow(t)).join('');
    }
  }

  // Tâches sans date
  const noDate = allTodos.filter(t => !t.date_echeance);
  if (noDate.length > 0) {
    html += '<div class="week-day-header">Sans échéance</div>';
    html += noDate.map(t => buildTodoRow(t)).join('');
  }

  container.innerHTML = html;
  attachRowEvents(container);
}

function buildTodoRow(t) {
  const isDone = t.statut === 'termine' || t.statut === 'annule';
  const isOverdue = t.date_echeance && t.date_echeance < new Date().toISOString().slice(0, 10) && !isDone;
  const assignee = t.assigned_prenom ? `${t.assigned_prenom} ${t.assigned_nom}` : '';

  return `
    <div class="todo-row d-flex align-items-center gap-3 px-2 ${isDone ? 'done' : ''}" data-todo-id="${t.id}">
      <div class="todo-check ${isDone ? 'checked' : ''}" data-toggle-todo="${t.id}" title="Marquer comme terminé">
        ${isDone ? '<i class="bi bi-check2 todo-check-icon"></i>' : ''}
      </div>
      <span class="todo-prio todo-prio-${t.priorite}" title="${PRIO_LABELS[t.priorite]}"></span>
      <div class="flex-grow-1" data-edit-todo="${t.id}">
        <div class="todo-title fw-medium">${escapeHtml(t.titre)}</div>
        <div class="d-flex gap-2 align-items-center mt-1 flex-wrap">
          <span class="todo-stat todo-stat-${t.statut}">${STAT_LABELS[t.statut]}</span>
          ${t.date_echeance ? `<small class="${isOverdue ? 'todo-overdue' : 'text-muted'}">${isOverdue ? '<i class="bi bi-exclamation-triangle-fill"></i> ' : '<i class="bi bi-calendar3"></i> '}${new Date(t.date_echeance).toLocaleDateString('fr-FR')}</small>` : ''}
          ${assignee ? `<small class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(assignee)}</small>` : ''}
        </div>
      </div>
      <div class="d-flex gap-1 flex-shrink-0">
        <select class="form-select form-select-sm todo-status-select" data-status-todo="${t.id}">
          <option value="a_faire" ${t.statut === 'a_faire' ? 'selected' : ''}>À faire</option>
          <option value="en_cours" ${t.statut === 'en_cours' ? 'selected' : ''}>En cours</option>
          <option value="termine" ${t.statut === 'termine' ? 'selected' : ''}>Terminé</option>
          <option value="annule" ${t.statut === 'annule' ? 'selected' : ''}>Annulé</option>
        </select>
        <button class="todo-btn-del" data-del-todo="${t.id}" title="Supprimer"><i class="bi bi-trash3"></i></button>
      </div>
    </div>
  `;
}

function attachRowEvents(container) {
  // Toggle done
  container.querySelectorAll('[data-toggle-todo]').forEach(el => {
    el.addEventListener('click', async (e) => {
      e.stopPropagation();
      const id = el.dataset.toggleTodo;
      const t = allTodos.find(x => x.id === id);
      if (!t) return;
      const newStatus = (t.statut === 'termine') ? 'a_faire' : 'termine';
      await adminApiPost('admin_update_todo', { id, statut: newStatus });
      loadTodos();
    });
  });

  // Status change
  container.querySelectorAll('[data-status-todo]').forEach(sel => {
    sel.addEventListener('change', async (e) => {
      e.stopPropagation();
      await adminApiPost('admin_update_todo', { id: sel.dataset.statusTodo, statut: sel.value });
      loadTodos();
    });
  });

  // Edit
  container.querySelectorAll('[data-edit-todo]').forEach(el => {
    el.addEventListener('click', () => editTodo(el.dataset.editTodo));
  });

  // Delete
  container.querySelectorAll('[data-del-todo]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      deleteTargetId = btn.dataset.delTodo;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDeleteTodo')).show();
    });
  });
}

function editTodo(id) {
  const t = allTodos.find(x => x.id === id);
  if (!t) return;
  document.getElementById('todoEditId').value = t.id;
  document.getElementById('todoTitre').value = t.titre;
  document.getElementById('todoDesc').value = t.description || '';
  zerdaSelect.setValue('#todoPriorite', t.priorite);
  document.getElementById('todoDate').value = t.date_echeance || '';
  zerdaSelect.setValue('#todoAssigned', t.assigned_to || '');
  document.getElementById('modalTodoTitle').textContent = 'Modifier la tâche';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTodo')).show();
}

async function saveTodo() {
  const titre = document.getElementById('todoTitre').value.trim();
  if (!titre) { toast('Titre requis'); return; }

  const editId = document.getElementById('todoEditId').value;
  const data = {
    titre,
    description: document.getElementById('todoDesc').value,
    priorite: zerdaSelect.getValue('#todoPriorite'),
    date_echeance: document.getElementById('todoDate').value,
    assigned_to: zerdaSelect.getValue('#todoAssigned'),
  };

  const btn = document.getElementById('btnSaveTodo');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  if (editId) {
    data.id = editId;
    await adminApiPost('admin_update_todo', data);
  } else {
    await adminApiPost('admin_create_todo', data);
  }

  btn.disabled = false;
  btn.textContent = 'Enregistrer';
  bootstrap.Modal.getInstance(document.getElementById('modalTodo')).hide();
  toast(editId ? 'Tâche modifiée' : 'Tâche créée');
  loadTodos();
}

async function confirmDelete() {
  if (!deleteTargetId) return;
  const btn = document.getElementById('btnConfirmDeleteTodo');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  await adminApiPost('admin_delete_todo', { id: deleteTargetId });
  btn.disabled = false;
  btn.textContent = 'Supprimer';
  deleteTargetId = null;
  bootstrap.Modal.getInstance(document.getElementById('modalDeleteTodo')).hide();
  toast('Tâche supprimée');
  loadTodos();
}

function debounce(fn, ms) {
  let t;
  return function() { clearTimeout(t); t = setTimeout(fn, ms); };
}

window.initTodosPage = initTodosPage;
})();
</script>
