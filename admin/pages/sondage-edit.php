<!-- Admin Sondage Editor — Full-page comfortable layout -->
<?php
$editId = $_GET['id'] ?? '';
$isEdit = !empty($editId);

$ssrSondage = null;
$ssrQuestions = [];
if ($isEdit) {
    $ssrSondage = Db::fetch(
        "SELECT s.*, u.prenom, u.nom
         FROM sondages s LEFT JOIN users u ON u.id = s.created_by
         WHERE s.id = ?",
        [$editId]
    );
    if ($ssrSondage) {
        $ssrQuestions = Db::fetchAll(
            "SELECT * FROM sondage_questions WHERE sondage_id = ? ORDER BY ordre ASC",
            [$editId]
        );
        foreach ($ssrQuestions as &$q) {
            if (!empty($q['options'])) {
                $q['options'] = json_decode($q['options'], true);
            }
        }
        unset($q);
    }
}
?>

<style>
/* ── Editor layout ── */
.se-wrap {
  max-width: 820px;
  margin: 0 auto;
}
.se-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  gap: 1rem;
  position: sticky;
  top: 0;
  z-index: 20;
  background: var(--cl-bg);
  padding: 12px 0;
  margin: -12px 0 1.5rem;
}
.se-header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.se-back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--cl-border);
  background: var(--cl-surface);
  color: var(--cl-text-secondary);
  text-decoration: none;
  transition: all var(--cl-transition);
}
.se-back:hover { background: var(--cl-accent-bg); color: var(--cl-text); }
.se-header-actions { display: flex; gap: 8px; }

/* ── Card sections ── */
.se-section {
  background: var(--cl-surface);
  border: 1px solid var(--cl-border);
  border-radius: var(--cl-radius);
  padding: 1.5rem;
  margin-bottom: 1rem;
  box-shadow: var(--cl-shadow);
}
.se-section-title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--cl-text-secondary);
  margin-bottom: 1rem;
}

/* ── Title input (big) ── */
.se-title-input {
  font-size: 1.35rem;
  font-weight: 600;
  border: none;
  background: transparent;
  width: 100%;
  padding: 0;
  color: var(--cl-text);
  outline: none;
}
.se-title-input::placeholder { color: var(--cl-text-muted); }
.se-title-input:focus { box-shadow: none; }

.se-desc-input {
  border: none;
  background: transparent;
  width: 100%;
  resize: none;
  padding: 0;
  color: var(--cl-text-secondary);
  font-size: 0.9rem;
  outline: none;
  min-height: 40px;
}
.se-desc-input::placeholder { color: var(--cl-text-muted); }

/* ── Anonymous toggle ── */
.se-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0 0;
  border-top: 1px solid var(--cl-border);
  margin-top: 12px;
}
.se-toggle label {
  font-size: 0.85rem;
  color: var(--cl-text-secondary);
  cursor: pointer;
  user-select: none;
}

/* ── Question blocks ── */
.se-question {
  background: var(--cl-surface);
  border: 1px solid var(--cl-border);
  border-radius: var(--cl-radius);
  padding: 1.25rem;
  margin-bottom: 0.75rem;
  box-shadow: var(--cl-shadow);
  position: relative;
  transition: border-color 0.2s;
}
.se-question:hover { border-color: var(--cl-accent); }
.se-question.moving { animation: sePulse 0.25s ease; }
@keyframes sePulse { 0%{transform:scale(1)} 50%{transform:scale(1.01)} 100%{transform:scale(1)} }
.se-question.se-new { animation: seSlideIn 0.3s ease; }
@keyframes seSlideIn { from { opacity:0; transform:translateY(-8px) } to { opacity:1; transform:translateY(0) } }

.se-question-header {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  margin-bottom: 10px;
}
.se-question-num {
  min-width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--cl-accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 2px;
}
.se-question-input {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 500;
  border: none;
  border-bottom: 2px solid transparent;
  background: transparent;
  padding: 2px 0;
  color: var(--cl-text);
  outline: none;
  transition: border-color 0.2s;
}
.se-question-input:focus { border-bottom-color: var(--cl-accent); }
.se-question-input::placeholder { color: var(--cl-text-muted); }

.se-question-actions {
  display: flex;
  gap: 4px;
  align-items: center;
}
.se-question-actions button {
  background: none;
  border: none;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  color: var(--cl-text-muted);
  transition: all 0.2s;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}
.se-question-actions button:hover { background: var(--cl-accent-bg); color: var(--cl-text); }
.se-question-actions .se-btn-delete:hover { background: rgba(220,38,38,0.08); color: #DC2626; }
.se-question-actions .se-btn-duplicate:hover { background: rgba(16,185,129,0.08); color: #059669; }
.se-question-actions .se-btn-move:disabled { opacity: 0.2; cursor: default; }
.se-question-actions .se-btn-move:disabled:hover { background: none; color: var(--cl-text-muted); }

/* ── Type selector ── */
.se-type-select {
  font-size: 0.8rem;
  border: 1px solid var(--cl-border);
  border-radius: 20px;
  padding: 4px 12px;
  background: var(--cl-bg);
  color: var(--cl-text-secondary);
  cursor: pointer;
  outline: none;
  transition: border-color 0.2s;
}
.se-type-select:focus { border-color: var(--cl-accent); }

/* ── Options ── */
.se-options { margin-top: 12px; }
.se-option-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.se-option-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid var(--cl-border);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--cl-text-muted);
}
.se-option-row[data-multi] .se-option-icon { border-radius: 4px; }
.se-option-input {
  flex: 1;
  border: none;
  border-bottom: 1px solid var(--cl-border);
  background: transparent;
  font-size: 0.85rem;
  padding: 4px 0;
  color: var(--cl-text);
  outline: none;
  transition: border-color 0.2s;
}
.se-option-input:focus { border-bottom-color: var(--cl-accent); }
.se-option-remove {
  background: none;
  border: none;
  color: var(--cl-text-muted);
  cursor: pointer;
  font-size: 0.85rem;
  padding: 4px;
  border-radius: 50%;
  transition: all 0.2s;
}
.se-option-remove:hover { color: #DC2626; background: rgba(220,38,38,0.08); }
.se-add-option {
  background: none;
  border: none;
  color: var(--cl-text-muted);
  font-size: 0.82rem;
  cursor: pointer;
  padding: 4px 0;
  margin-top: 2px;
  transition: color 0.2s;
}
.se-add-option:hover { color: var(--cl-accent); }

/* ── Add question button ── */
.se-add-question {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px;
  border: 2px dashed var(--cl-border);
  border-radius: var(--cl-radius);
  background: transparent;
  color: var(--cl-text-secondary);
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.se-add-question:hover {
  border-color: var(--cl-accent);
  color: var(--cl-accent);
  background: var(--cl-accent-bg);
}

/* ── Question count badge ── */
.se-q-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  background: var(--cl-accent-bg);
  color: var(--cl-text-secondary);
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0 6px;
  margin-left: 6px;
}

/* ── Loading overlay ── */
.se-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 0;
  gap: 10px;
  color: var(--cl-text-muted);
  font-size: 0.9rem;
}

/* ── Empty state ── */
.se-empty {
  text-align: center;
  padding: 2rem;
  color: var(--cl-text-muted);
}
.se-empty i { font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem; }

/* ── Preview Modal ── */
.se-preview-body {
  padding: 2rem;
  background: var(--cl-bg);
}
.se-preview-title {
  font-size: 1.4rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}
.se-preview-desc {
  color: var(--cl-text-secondary);
  font-size: 0.9rem;
  margin-bottom: 1.5rem;
}
.se-preview-q {
  background: var(--cl-surface);
  border: 1px solid var(--cl-border);
  border-radius: var(--cl-radius-sm);
  padding: 1.25rem;
  margin-bottom: 1rem;
}
.se-preview-q-title {
  font-weight: 600;
  font-size: 0.92rem;
  margin-bottom: 0.75rem;
}
.se-preview-q-type {
  font-size: 0.72rem;
  color: var(--cl-text-muted);
  background: var(--cl-bg);
  border-radius: 20px;
  padding: 2px 10px;
  margin-left: 8px;
  font-weight: 500;
}
.se-preview-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border: 1px solid var(--cl-border);
  border-radius: 8px;
  margin-bottom: 6px;
  font-size: 0.85rem;
  color: var(--cl-text);
  background: var(--cl-surface);
  transition: border-color 0.2s;
}
.se-preview-option:hover { border-color: var(--cl-accent); }
.se-preview-radio {
  width: 16px;
  height: 16px;
  border: 2px solid var(--cl-border);
  border-radius: 50%;
  flex-shrink: 0;
}
.se-preview-checkbox {
  width: 16px;
  height: 16px;
  border: 2px solid var(--cl-border);
  border-radius: 4px;
  flex-shrink: 0;
}
.se-preview-textarea {
  width: 100%;
  border: 1px solid var(--cl-border);
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 0.85rem;
  background: var(--cl-surface);
  color: var(--cl-text-muted);
  resize: none;
  min-height: 60px;
}
.se-preview-anon {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: var(--cl-text-muted);
  background: var(--cl-bg);
  border-radius: 20px;
  padding: 4px 12px;
  margin-bottom: 1rem;
}

/* ── AI button ── */
.se-ai-btn {
  background: linear-gradient(135deg, rgba(111,66,193,.08), rgba(0,180,160,.08));
  border-color: rgba(111,66,193,.2);
  color: #6f42c1;
}

/* ── Modal shared ── */
.se-modal-content { border: none; border-radius: var(--cl-radius); }
.se-modal-header { border-bottom: 1px solid var(--cl-border); background: var(--cl-surface); }
.se-modal-footer { border-top: 1px solid var(--cl-border); background: var(--cl-surface); }
.se-ai-modal-header { border-bottom: 1px solid var(--cl-border); background: linear-gradient(135deg, rgba(111,66,193,.05), rgba(0,180,160,.05)); }
.se-ai-generate-btn { background: linear-gradient(135deg, #6f42c1, #00b4a0); color: #fff; border: none; }

/* ── Question removal animation ── */
.se-question-removing { transition: opacity 0.2s, transform 0.2s; opacity: 0; transform: scale(0.97); }

/* ── Option icon multi (checkbox) ── */
.se-option-icon-multi { border-radius: 4px !important; }
</style>

<div class="container-fluid py-3">
  <div class="se-wrap">

    <!-- Header -->
    <div class="se-header">
      <div class="se-header-left">
        <a href="<?= admin_url('sondages') ?>" class="se-back" title="Retour"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0"><?= $isEdit ? 'Modifier le sondage' : 'Nouveau sondage' ?></h5>
      </div>
      <div class="se-header-actions">
        <button class="btn btn-outline-dark btn-sm" id="btnPreview">
          <i class="bi bi-eye"></i> Aperçu
        </button>
        <button class="btn btn-primary btn-sm" id="btnSave">
          <i class="bi bi-check-lg"></i> Enregistrer
        </button>
      </div>
    </div>

    <!-- Info section -->
    <div class="se-section">
      <input type="text" class="se-title-input" id="seTitre" placeholder="Titre du sondage" maxlength="255">
      <div class="mt-2">
        <textarea class="se-desc-input" id="seDescription" placeholder="Description (optionnelle)" rows="2"></textarea>
      </div>
      <div class="se-toggle">
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" role="switch" id="seAnonymous">
          <label class="form-check-label" for="seAnonymous"><i class="bi bi-incognito"></i> Réponses anonymes</label>
        </div>
      </div>
    </div>

    <!-- Questions section -->
    <div class="se-section-title"><i class="bi bi-list-check"></i> Questions <span class="se-q-count" id="seQCount">0</span></div>
    <div id="seQuestionsWrap"></div>
    <div id="seEmptyState" class="se-empty d-none">
      <i class="bi bi-clipboard2-plus"></i>
      <div>Ajoutez votre première question</div>
    </div>
    <button type="button" class="se-add-question" id="btnAddQ">
      <i class="bi bi-plus-circle"></i> Ajouter une question
    </button>
    <button type="button" class="se-add-question se-ai-btn" id="btnAiGenerate">
      <i class="bi bi-magic"></i> Générer avec l'IA
    </button>

  </div>
</div>

<!-- AI Generate Modal -->
<div class="modal fade" id="aiModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content se-modal-content">
      <div class="modal-header se-ai-modal-header">
        <h6 class="modal-title"><i class="bi bi-magic"></i> Générer des questions avec l'IA</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Thème / Sujet du sondage</label>
          <textarea class="form-control" id="aiTheme" rows="3" placeholder="Ex: Satisfaction au travail, qualité de la communication interne, bien-être des soignants..."></textarea>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Nombre de questions</label>
            <input type="number" class="form-control" id="aiNbQuestions" value="5" min="1" max="20">
          </div>
          <div class="col-6">
            <label class="form-label">Langue</label>
            <div class="zs-select" id="aiLangue" data-placeholder="Langue"></div>
          </div>
        </div>
        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" id="aiAnonyme">
          <label class="form-check-label" for="aiAnonyme">Sondage anonyme (questions plus personnelles autorisées)</label>
        </div>
        <div id="aiError" class="alert alert-danger small d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-sm se-ai-generate-btn" id="aiGenerateBtn">
          <i class="bi bi-magic"></i> Générer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content se-modal-content">
      <div class="modal-header se-modal-header">
        <h6 class="modal-title"><i class="bi bi-eye"></i> Aperçu du sondage</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="previewContent" class="se-preview-body"></div>
      </div>
      <div class="modal-footer se-modal-footer">
        <small class="text-muted me-auto"><i class="bi bi-info-circle"></i> Aperçu tel que vu par les collaborateurs</small>
        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="seEditId" value="<?= h($editId) ?>">

<script<?= nonce() ?>>
(function() {
    const editId = document.getElementById('seEditId').value;
    const ssrSondage = <?= json_encode($ssrSondage, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const ssrQuestions = <?= json_encode(array_values($ssrQuestions), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let previewModal;
    let isDirty = false;

    // Track unsaved changes
    let saving = false;
    function markDirty() { isDirty = true; }

    // Auto-save as draft when leaving the page
    async function autoSaveDraft() {
        if (!isDirty || saving) return false;
        const d = collectData();
        if (!d.titre && d.questions.length === 0) return false; // nothing to save
        saving = true;
        try {
            let result;
            if (editId) {
                d.id = editId;
                result = await adminApiPost('admin_update_sondage', d);
            } else {
                result = await adminApiPost('admin_create_sondage', d);
            }
            if (result?.success) { isDirty = false; return true; }
        } catch (e) { /* silent */ }
        finally { saving = false; }
        return false;
    }

    // Warn on hard close (tab close / browser close) — can't async save here
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Ctrl+S to save
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            document.getElementById('btnSave')?.click();
        }
    });

    document.addEventListener('DOMContentLoaded', async () => {
        previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

        // Init AI language select
        zerdaSelect.init('#aiLangue', [
            { value: 'fr', label: 'Français' },
            { value: 'de', label: 'Allemand' },
            { value: 'en', label: 'Anglais' },
            { value: 'it', label: 'Italien' }
        ], { value: 'fr' });

        document.getElementById('btnAddQ').addEventListener('click', () => { addQuestion(null, true); markDirty(); });
        document.getElementById('btnAiGenerate')?.addEventListener('click', () => {
            document.getElementById('aiTheme').value = document.getElementById('seTitre')?.value || '';
            document.getElementById('aiError').classList.add('d-none');
            new bootstrap.Modal('#aiModal').show();
        });
        document.getElementById('aiGenerateBtn')?.addEventListener('click', generateWithAI);
        document.getElementById('btnPreview').addEventListener('click', showPreview);
        document.getElementById('btnSave').addEventListener('click', save);

        // Track dirty on info fields
        document.getElementById('seTitre').addEventListener('input', markDirty);
        document.getElementById('seDescription').addEventListener('input', markDirty);
        document.getElementById('seAnonymous').addEventListener('change', markDirty);

        // Auto-resize description
        const descEl = document.getElementById('seDescription');
        descEl.addEventListener('input', () => { descEl.style.height = 'auto'; descEl.style.height = descEl.scrollHeight + 'px'; });

        // Auto-save draft before any navigation away
        async function guardNavigation(e, targetHref) {
            if (!isDirty) return;
            e.preventDefault();
            const saved = await autoSaveDraft();
            if (saved) toast('Brouillon sauvegardé', 'success');
            // Small delay so toast is visible
            setTimeout(() => { window.location.href = targetHref; }, saved ? 400 : 0);
        }

        // Back button
        const backLink = document.querySelector('.se-back');
        if (backLink) {
            backLink.addEventListener('click', (e) => guardNavigation(e, backLink.href));
        }

        // Sidebar links — auto-save when clicking away
        document.querySelectorAll('.sidebar a[href], .admin-sidebar a[href]').forEach(link => {
            link.addEventListener('click', (e) => guardNavigation(e, link.href));
        });

        if (editId && ssrSondage) {
            // Use SSR data — no AJAX needed
            populateFromData(ssrSondage, ssrQuestions);
        } else if (editId) {
            // Fallback: AJAX
            document.getElementById('seQuestionsWrap').innerHTML = '<div class="se-loading"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>';
            await loadExisting();
        } else {
            addQuestion();
        }
        updateEmptyState();
    });

    // ── Populate form from sondage + questions data ──
    function populateFromData(sondage, questions) {
        document.getElementById('seTitre').value = sondage.titre;
        const descEl = document.getElementById('seDescription');
        descEl.value = sondage.description || '';
        descEl.style.height = 'auto';
        descEl.style.height = descEl.scrollHeight + 'px';
        document.getElementById('seAnonymous').checked = sondage.is_anonymous == 1;

        document.getElementById('seQuestionsWrap').innerHTML = '';
        (questions || []).forEach(q => addQuestion(q));
    }

    // ── Load existing sondage for editing (AJAX fallback) ──
    async function loadExisting() {
        const r = await adminApiPost('admin_get_sondage', { id: editId });
        if (!r.success) { toast('Sondage introuvable', 'error'); return; }
        populateFromData(r.sondage, r.questions);
    }

    // ── Add question block ──
    function addQuestion(data, animate) {
        const wrap = document.getElementById('seQuestionsWrap');
        const idx = wrap.children.length;
        const type = data?.type || 'choix_unique';
        const options = data?.options || ['', ''];

        const block = document.createElement('div');
        block.className = 'se-question';
        block.innerHTML = `
            <div class="se-question-header">
                <div class="se-question-num">${idx + 1}</div>
                <input type="text" class="se-question-input q-text" placeholder="Votre question..." value="${escapeHtml(data?.question || '')}">
                <select class="se-type-select q-type">
                    <option value="choix_unique" ${type === 'choix_unique' ? 'selected' : ''}>Choix unique</option>
                    <option value="choix_multiple" ${type === 'choix_multiple' ? 'selected' : ''}>Choix multiple</option>
                    <option value="texte_libre" ${type === 'texte_libre' ? 'selected' : ''}>Texte libre</option>
                </select>
                <div class="se-question-actions">
                    <button type="button" class="se-btn-move se-btn-up" title="Monter"><i class="bi bi-chevron-up"></i></button>
                    <button type="button" class="se-btn-move se-btn-down" title="Descendre"><i class="bi bi-chevron-down"></i></button>
                    <button type="button" class="se-btn-duplicate" title="Dupliquer"><i class="bi bi-copy"></i></button>
                    <button type="button" class="se-btn-delete" title="Supprimer"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
            <div class="se-options q-options ${type === 'texte_libre' ? 'd-none' : ''}">
                ${options.map((o, i) => optionRowHtml(o, type === 'choix_multiple')).join('')}
                <button type="button" class="se-add-option btn-add-opt"><i class="bi bi-plus"></i> Ajouter une option</button>
            </div>
            ${type === 'texte_libre' ? '<div class="se-texte-libre-hint text-muted small mt-2"><i class="bi bi-fonts"></i> Les collaborateurs répondront en texte libre</div>' : ''}
        `;

        // Move up / down
        block.querySelector('.se-btn-up').addEventListener('click', () => {
            const prev = block.previousElementSibling;
            if (prev) { wrap.insertBefore(block, prev); renumberQuestions(); markDirty(); block.classList.add('moving'); setTimeout(() => block.classList.remove('moving'), 250); }
        });
        block.querySelector('.se-btn-down').addEventListener('click', () => {
            const next = block.nextElementSibling;
            if (next) { wrap.insertBefore(next, block); renumberQuestions(); markDirty(); block.classList.add('moving'); setTimeout(() => block.classList.remove('moving'), 250); }
        });

        // Duplicate question
        block.querySelector('.se-btn-duplicate').addEventListener('click', () => {
            const qData = {
                question: block.querySelector('.q-text').value,
                type: block.querySelector('.q-type').value,
                options: [...block.querySelectorAll('.se-option-row .se-option-input')].map(i => i.value)
            };
            addQuestion(qData, true);
            markDirty();
        });

        // Delete question
        block.querySelector('.se-btn-delete').addEventListener('click', () => {
            const hasContent = block.querySelector('.q-text').value.trim();
            if (hasContent && !confirm('Supprimer cette question ?')) return;
            block.classList.add('se-question-removing');
            setTimeout(() => { block.remove(); renumberQuestions(); updateEmptyState(); }, 200);
            markDirty();
        });

        // Type change
        block.querySelector('.q-type').addEventListener('change', function() {
            markDirty();
            const isText = this.value === 'texte_libre';
            block.querySelector('.q-options').classList.toggle('d-none', isText);
            let hint = block.querySelector('.se-texte-libre-hint');
            if (isText && !hint) {
                hint = document.createElement('div');
                hint.className = 'se-texte-libre-hint text-muted small mt-2';
                hint.innerHTML = '<i class="bi bi-fonts"></i> Les collaborateurs répondront en texte libre';
                block.appendChild(hint);
            } else if (!isText && hint) {
                hint.remove();
            }
            // Update option icons (radio vs checkbox)
            block.querySelectorAll('.se-option-icon').forEach(icon => {
                icon.classList.toggle('se-option-icon-multi', this.value === 'choix_multiple');
            });
        });

        // Add option
        block.querySelector('.btn-add-opt').addEventListener('click', function() {
            markDirty();
            const isMulti = block.querySelector('.q-type').value === 'choix_multiple';
            const row = document.createElement('div');
            row.className = 'se-option-row';
            row.innerHTML = optionRowInner('', isMulti);
            wireOptionRemove(row);
            wireOptionEnter(row, block);
            this.before(row);
            relabelOptions(block);
        });

        // Wire existing option removes + Enter on option → add new
        block.querySelectorAll('.se-option-row').forEach(r => { wireOptionRemove(r); wireOptionEnter(r, block); });

        // Track dirty on question/option text input
        block.addEventListener('input', markDirty);

        wrap.appendChild(block);
        renumberQuestions();
        updateEmptyState();

        // Animate & focus new question
        if (animate) {
            block.classList.add('se-new');
            setTimeout(() => block.classList.remove('se-new'), 300);
            block.querySelector('.q-text').focus();
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function optionRowHtml(value, isMulti) {
        return `<div class="se-option-row">
            ${optionRowInner(value, isMulti)}
        </div>`;
    }

    function optionRowInner(value, isMulti) {
        return `<div class="se-option-icon ${isMulti ? 'se-option-icon-multi' : ''}"></div>
                <input type="text" class="se-option-input" placeholder="Option" value="${escapeHtml(value || '')}">
                <button type="button" class="se-option-remove" title="Supprimer"><i class="bi bi-x"></i></button>`;
    }

    function wireOptionEnter(row, block) {
        const inp = row.querySelector('.se-option-input');
        if (!inp) return;
        inp.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                block.querySelector('.btn-add-opt')?.click();
                // Focus the newly added option
                setTimeout(() => {
                    const rows = block.querySelectorAll('.se-option-row');
                    const last = rows[rows.length - 1];
                    last?.querySelector('.se-option-input')?.focus();
                }, 10);
            }
        });
    }

    function wireOptionRemove(row) {
        row.querySelector('.se-option-remove')?.addEventListener('click', () => {
            const block = row.closest('.se-question');
            row.remove();
            markDirty();
            relabelOptions(block);
        });
    }

    function renumberQuestions() {
        const blocks = document.querySelectorAll('#seQuestionsWrap .se-question');
        blocks.forEach((el, i) => {
            el.querySelector('.se-question-num').textContent = i + 1;
            el.querySelector('.se-btn-up').disabled = (i === 0);
            el.querySelector('.se-btn-down').disabled = (i === blocks.length - 1);
            relabelOptions(el);
        });
        const counter = document.getElementById('seQCount');
        if (counter) counter.textContent = blocks.length;
    }

    function relabelOptions(block) {
        if (!block) return;
        const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        block.querySelectorAll('.se-option-row .se-option-icon').forEach((icon, i) => {
            icon.textContent = letters[i] || '';
        });
    }

    function updateEmptyState() {
        const wrap = document.getElementById('seQuestionsWrap');
        const empty = document.getElementById('seEmptyState');
        if (empty) empty.classList.toggle('d-none', wrap.children.length > 0);
    }

    // ── Collect form data ──
    function collectData() {
        const titre = document.getElementById('seTitre').value.trim();
        const description = document.getElementById('seDescription').value.trim();
        const isAnonymous = document.getElementById('seAnonymous').checked ? 1 : 0;

        const questions = [];
        document.querySelectorAll('.se-question').forEach(block => {
            const text = block.querySelector('.q-text').value.trim();
            if (!text) return;
            const type = block.querySelector('.q-type').value;
            const opts = [];
            block.querySelectorAll('.se-option-row .se-option-input').forEach(inp => {
                const v = inp.value.trim();
                if (v) opts.push(v);
            });
            questions.push({ question: text, type, options: opts });
        });

        return { titre, description, is_anonymous: isAnonymous, questions };
    }

    // ── Preview ──
    function showPreview() {
        const d = collectData();
        if (!d.titre) { toast('Ajoutez un titre pour voir l\'aperçu', 'error'); return; }

        const container = document.getElementById('previewContent');
        let html = `
            <div class="se-preview-title">${escapeHtml(d.titre)}</div>
            ${d.description ? '<div class="se-preview-desc">' + escapeHtml(d.description) + '</div>' : ''}
            ${d.is_anonymous ? '<div class="se-preview-anon"><i class="bi bi-incognito"></i> Réponses anonymes</div>' : ''}
        `;

        if (d.questions.length === 0) {
            html += '<div class="text-muted text-center py-4">Aucune question ajoutée</div>';
        }

        d.questions.forEach((q, idx) => {
            const typeLabel = q.type === 'choix_unique' ? 'Choix unique'
                : q.type === 'choix_multiple' ? 'Choix multiple' : 'Texte libre';

            html += `<div class="se-preview-q">
                <div class="se-preview-q-title">
                    ${idx + 1}. ${escapeHtml(q.question)}
                    <span class="se-preview-q-type">${typeLabel}</span>
                </div>`;

            if (q.type === 'texte_libre') {
                html += '<textarea class="se-preview-textarea" placeholder="Votre réponse..." disabled></textarea>';
            } else {
                const iconClass = q.type === 'choix_unique' ? 'se-preview-radio' : 'se-preview-checkbox';
                q.options.forEach(o => {
                    html += `<div class="se-preview-option">
                        <div class="${iconClass}"></div>
                        ${escapeHtml(o)}
                    </div>`;
                });
            }

            html += '</div>';
        });

        container.innerHTML = html;
        previewModal.show();
    }

    // ── AI Generation ──
    async function generateWithAI() {
        const theme = document.getElementById('aiTheme').value.trim();
        const nbQuestions = parseInt(document.getElementById('aiNbQuestions').value) || 5;
        const langue = zerdaSelect.getValue('#aiLangue');
        const anonyme = document.getElementById('aiAnonyme').checked;
        const errorDiv = document.getElementById('aiError');

        if (!theme) { errorDiv.textContent = 'Veuillez saisir un thème'; errorDiv.classList.remove('d-none'); return; }
        errorDiv.classList.add('d-none');

        const btn = document.getElementById('aiGenerateBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Génération en cours...';

        try {
            const res = await adminApiPost('admin_generate_sondage_questions', { theme, nb_questions: nbQuestions, langue, anonyme });
            if (res.success && res.questions?.length) {
                bootstrap.Modal.getInstance('#aiModal')?.hide();
                // Remove empty placeholder questions before adding AI ones
                document.querySelectorAll('.se-question').forEach(block => {
                    if (!block.querySelector('.q-text')?.value.trim()) block.remove();
                });
                res.questions.forEach(q => {
                    addQuestion({
                        question: q.question,
                        type: q.type,
                        options: q.options || [],
                    }, true);
                });
                markDirty();
                toast(`${res.questions.length} question(s) générée(s) par l'IA`, 'success');
            } else {
                errorDiv.innerHTML = (res.message || res.error || 'Erreur lors de la génération') +
                    '<br><small>Vous pouvez ajouter vos questions manuellement en fermant cette fenêtre.</small>';
                errorDiv.classList.remove('d-none');
            }
        } catch (e) {
            errorDiv.innerHTML = 'Erreur réseau — l\'IA n\'est pas disponible.<br><small>Vous pouvez ajouter vos questions manuellement.</small>';
            errorDiv.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-magic"></i> Générer';
        }
    }

    // ── Save ──
    async function save() {
        const d = collectData();
        if (!d.titre) { toast('Titre requis', 'error'); return; }
        if (d.questions.length === 0) { toast('Ajoutez au moins une question', 'error'); return; }

        // Validate that choice questions have at least 2 options
        for (const q of d.questions) {
            if (q.type !== 'texte_libre' && q.options.length < 2) {
                toast('La question "' + q.question.substring(0, 40) + '" nécessite au moins 2 options', 'error');
                return;
            }
        }

        const btn = document.getElementById('btnSave');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';

        let result;
        if (editId) {
            d.id = editId;
            result = await adminApiPost('admin_update_sondage', d);
        } else {
            result = await adminApiPost('admin_create_sondage', d);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';

        if (result.success) {
            isDirty = false;
            toast('Sondage enregistré', 'success');
            const targetId = editId || result.id;
            window.location.href = AdminURL.page('sondages', null, { selected: targetId });
        } else {
            toast(result.message || 'Erreur', 'error');
        }
    }

})();
</script>
