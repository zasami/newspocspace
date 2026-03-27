<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-sliders"></i> Configuration des onglets du planning</h6>
        <button class="btn btn-primary btn-sm" id="apSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Glissez-déposez les onglets pour changer l'ordre. L'onglet «&nbsp;Tous&nbsp;» est toujours présent en premier.</p>

        <div id="apTabsList" class="ap-tabs-list"></div>

        <button class="btn btn-outline-secondary btn-sm mt-2" id="apAddTabBtn">
          <i class="bi bi-plus-lg"></i> Ajouter un onglet
        </button>
      </div>
    </div>

    <!-- Preview -->
    <div class="card mt-3">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-eye"></i> Aperçu</h6></div>
      <div class="card-body">
        <div class="module-switch" id="apPreview">
          <button class="module-switch-btn active">Tous</button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightbulb"></i> Types de filtres</h6></div>
      <div class="card-body small">
        <table class="table table-sm mb-0">
          <tbody>
            <tr><td><strong>module</strong></td><td>Filtre par module (M1, M2, POOL, NUIT...)</td></tr>
            <tr><td><strong>fonction</strong></td><td>Filtre par fonction (INF, ASSC, AS...)</td></tr>
            <tr><td><strong>fonctions</strong></td><td>Combine plusieurs fonctions (ex: INF,ASSC)</td></tr>
            <tr><td><strong>etage</strong></td><td>Filtre par étage (1er, 2e, 3e...)</td></tr>
            <tr><td><strong>all</strong></td><td>Affiche tout le monde</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-bookmark"></i> Presets rapides</h6></div>
      <div class="card-body">
        <button class="btn btn-preset btn-sm w-100 mb-2" data-preset="modules">
          <i class="bi bi-grid-3x3"></i> Par modules uniquement
        </button>
        <button class="btn btn-preset btn-sm w-100 mb-2" data-preset="fonctions">
          <i class="bi bi-people"></i> Par fonctions uniquement
        </button>
        <button class="btn btn-preset btn-sm w-100 mb-2" data-preset="etages">
          <i class="bi bi-building"></i> Par étages
        </button>
        <button class="btn btn-preset btn-sm w-100 mb-2" data-preset="complet">
          <i class="bi bi-list-ul"></i> Modules + fonctions
        </button>
        <button class="btn btn-preset btn-sm w-100" data-preset="cahier">
          <i class="bi bi-journal"></i> Style cahier des charges
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Add tab modal -->
<div class="modal fade" id="apAddModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-info">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">Ajouter un onglet</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center ap-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg ap-close-icon"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Type de filtre</label>
          <div class="d-flex flex-wrap gap-2" id="apTypeSelector">
            <label class="ap-type-card active" data-type="module">
              <i class="bi bi-grid-3x3"></i>
              <span>Module</span>
              <input type="radio" name="apType" value="module" checked class="d-none">
            </label>
            <label class="ap-type-card" data-type="etage">
              <i class="bi bi-building"></i>
              <span>Étage</span>
              <input type="radio" name="apType" value="etage" class="d-none">
            </label>
            <label class="ap-type-card" data-type="fonction">
              <i class="bi bi-person-badge"></i>
              <span>Fonction</span>
              <input type="radio" name="apType" value="fonction" class="d-none">
            </label>
            <label class="ap-type-card" data-type="fonctions">
              <i class="bi bi-people"></i>
              <span>Multi-fonctions</span>
              <input type="radio" name="apType" value="fonctions" class="d-none">
            </label>
          </div>
        </div>
        <div class="mb-3" id="apNewModuleGroup">
          <label class="form-label small fw-bold">Module</label>
          <div class="zs-select" id="apNewModule" data-placeholder="Module"></div>
        </div>
        <div class="mb-3 d-none" id="apNewEtageGroup">
          <label class="form-label small fw-bold">Étage</label>
          <div class="zs-select" id="apNewEtage" data-placeholder="Étage"></div>
        </div>
        <div class="mb-3 d-none" id="apNewFonctionGroup">
          <label class="form-label small fw-bold">Fonction</label>
          <div class="zs-select" id="apNewFonction" data-placeholder="Fonction"></div>
        </div>
        <div class="mb-3 d-none" id="apNewFonctionsGroup">
          <label class="form-label small fw-bold">Fonctions (cochez plusieurs)</label>
          <div id="apNewFonctionsChecks"></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Label affiché</label>
          <input type="text" class="form-control form-control-sm" id="apNewLabel" placeholder="Ex: Équipe nuit">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm ap-confirm-btn" id="apConfirmAdd">
          <i class="bi bi-plus-lg me-1"></i>Ajouter
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.ap-tabs-list { display: flex; flex-direction: column; gap: 4px; }
.ap-tab-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 10px; background: var(--cl-bg); border-radius: var(--cl-radius-xs); border: 1px solid var(--cl-border);
  cursor: grab; transition: all var(--cl-transition);
}
.ap-tab-row:hover { border-color: var(--cl-border-hover); box-shadow: var(--cl-shadow); background: var(--cl-accent-bg, #f8f7f4); }
.ap-tab-row:active { cursor: grabbing; }
.ap-tab-row.dragging { opacity: 0.4; }
.ap-tab-row .ap-handle { color: var(--cl-text-muted); cursor: grab; }
.ap-tab-row .ap-label { font-weight: 600; min-width: 80px; }
.ap-tab-row .ap-type { font-size: 0.75rem; color: var(--cl-text-muted); }
.ap-tab-row .ap-value { font-size: 0.75rem; color: var(--cl-text-secondary); background: var(--cl-bg); padding: 1px 6px; border-radius: 4px; border: 1px solid var(--cl-border); }
.ap-tab-row .ap-remove { color: var(--zt-red); cursor: pointer; margin-left: auto; }
.ap-tab-row.ap-fixed { background: var(--cl-accent-bg); border-color: rgba(25,25,24,0.2); }
.ap-type-card {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 10px 14px; border-radius: 10px; border: 1.5px solid var(--cl-border, #e5e7eb);
  background: #FAFAF8; cursor: pointer; transition: all .2s; flex: 1; min-width: 80px; text-align: center;
}
.ap-type-card i { font-size: 1.2rem; color: var(--cl-text-muted, #999); transition: color .2s; }
.ap-type-card span { font-size: .72rem; font-weight: 600; color: var(--cl-text-secondary, #666); }
.ap-type-card:hover { border-color: #C4BBA8; background: #F5F3EE; }
.ap-type-card.active { border-color: var(--cl-accent, #191918); background: #fff; box-shadow: 0 2px 8px rgba(25,25,24,.08); }
.ap-type-card.active i { color: var(--cl-accent, #191918); }
.ap-type-card.active span { color: var(--cl-accent, #191918); }
.ap-modal-close { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.ap-close-icon { font-size: .85rem; }
.ap-confirm-btn { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: 8px; }
</style>

<script<?= nonce() ?>>
(function() {
    let tabs = [];
    let modulesData = [];
    let fonctionsData = [];
    let etagesData = [];

    async function initAffichageplanningPage() {
        // Load refs + etages in parallel
        const [refsRes, etagesRes, cfgRes] = await Promise.all([
            adminApiPost('admin_get_planning_refs'),
            adminApiPost('admin_get_etages'),
            adminApiPost('admin_get_config'),
        ]);
        modulesData = refsRes.modules || [];
        fonctionsData = refsRes.fonctions || [];
        etagesData = etagesRes.etages || [];

        const cfg = cfgRes.config || {};

        if (cfg.planning_tabs_config) {
            try {
                const parsed = JSON.parse(cfg.planning_tabs_config);
                tabs = parsed.tabs || [];
            } catch(e) { tabs = []; }
        }

        if (!tabs.length) {
            tabs = getPreset('complet');
        }

        renderTabs();
        renderPreview();
        setupEvents();
    }

    function getPreset(name) {
        const allTab = { type: 'all', label: 'Tous', value: '' };
        switch (name) {
            case 'modules':
                return [allTab, ...modulesData.map(m => ({ type: 'module', label: m.code, value: m.id }))];
            case 'fonctions':
                return [allTab, ...fonctionsData.map(f => ({ type: 'fonction', label: f.code, value: f.code }))];
            case 'complet':
                return [
                    allTab,
                    ...modulesData.map(m => ({ type: 'module', label: m.code, value: m.id })),
                    ...fonctionsData.map(f => ({ type: 'fonction', label: f.code, value: f.code }))
                ];
            case 'etages':
                return [allTab, ...etagesData.map(e => ({ type: 'etage', label: e.nom, value: e.id }))];
            case 'cahier':
                // RS/RUV, POOL, M1, M2, M3, M4, NUIT, then fonctions
                const rs = fonctionsData.find(f => f.code === 'RS');
                const ruv = fonctionsData.find(f => f.code === 'RUV');
                const pool = modulesData.find(m => m.code === 'POOL');
                const nuit = modulesData.find(m => m.code === 'NUIT');
                const result = [allTab];
                if (rs || ruv) result.push({ type: 'fonctions', label: 'RS / RUV', value: 'RS,RUV' });
                if (pool) result.push({ type: 'module', label: 'POOL', value: pool.id });
                modulesData.filter(m => !['POOL','NUIT'].includes(m.code)).forEach(m => {
                    result.push({ type: 'module', label: m.code, value: m.id });
                });
                if (nuit) result.push({ type: 'module', label: 'Éq. Nuit', value: nuit.id });
                ['INF','ASSC','AS'].forEach(code => {
                    const f = fonctionsData.find(fn => fn.code === code);
                    if (f) result.push({ type: 'fonction', label: code, value: code });
                });
                return result;
            default:
                return [allTab];
        }
    }

    function resolveTabValue(tab) {
        if (tab.type === 'module') {
            const m = modulesData.find(mod => mod.id === tab.value);
            return m ? (m.code + ' — ' + m.nom) : tab.value;
        }
        if (tab.type === 'etage') {
            const e = etagesData.find(et => et.id === tab.value);
            return e ? (e.nom + ' (' + (e.module_code || '') + ')') : tab.value;
        }
        return String(tab.value);
    }

    function renderTabs() {
        const container = document.getElementById('apTabsList');
        container.innerHTML = tabs.map((tab, i) => {
            const isFixed = tab.type === 'all' && i === 0;
            const typeLabel = { all: 'Tout', module: 'Module', fonction: 'Fonction', fonctions: 'Multi-fonctions', etage: 'Étage' }[tab.type] || tab.type;
            return `<div class="ap-tab-row${isFixed ? ' ap-fixed' : ''}" draggable="${isFixed ? 'false' : 'true'}" data-idx="${i}">
                <span class="ap-handle"><i class="bi bi-grip-vertical"></i></span>
                <span class="ap-label">${escapeHtml(tab.label)}</span>
                <span class="ap-type">${typeLabel}</span>
                ${tab.value ? `<span class="ap-value">${escapeHtml(resolveTabValue(tab))}</span>` : ''}
                ${!isFixed ? `<span class="ap-remove" data-idx="${i}"><i class="bi bi-x-lg"></i></span>` : ''}
            </div>`;
        }).join('');

        // Drag & drop
        let dragIdx = null;
        container.querySelectorAll('.ap-tab-row[draggable="true"]').forEach(row => {
            row.addEventListener('dragstart', e => {
                dragIdx = parseInt(row.dataset.idx);
                row.classList.add('dragging');
            });
            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                dragIdx = null;
            });
            row.addEventListener('dragover', e => {
                e.preventDefault();
                const targetIdx = parseInt(row.dataset.idx);
                if (dragIdx !== null && dragIdx !== targetIdx && targetIdx > 0) {
                    const [moved] = tabs.splice(dragIdx, 1);
                    tabs.splice(targetIdx, 0, moved);
                    dragIdx = targetIdx;
                    renderTabs();
                    renderPreview();
                }
            });
        });

        // Remove buttons
        container.querySelectorAll('.ap-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.idx);
                tabs.splice(idx, 1);
                renderTabs();
                renderPreview();
            });
        });
    }

    function renderPreview() {
        const preview = document.getElementById('apPreview');
        preview.innerHTML = '<div class="module-switch-indicator"></div>' + tabs.map((tab, i) => {
            return `<button class="module-switch-btn${i === 0 ? ' active' : ''}">${escapeHtml(tab.label)}</button>`;
        }).join('');
        // Position sliding indicator on active button
        requestAnimationFrame(() => {
            const indicator = preview.querySelector('.module-switch-indicator');
            const activeBtn = preview.querySelector('.module-switch-btn.active');
            if (indicator && activeBtn) {
                indicator.style.left = activeBtn.offsetLeft + 'px';
                indicator.style.width = activeBtn.offsetWidth + 'px';
            }
        });
    }

    function setupEvents() {
        // Save
        document.getElementById('apSaveBtn').addEventListener('click', async () => {
            const json = JSON.stringify({ tabs });
            await adminApiPost('admin_save_config', { values: { planning_tabs_config: json } });
            showToast('Configuration sauvegardée', 'success');
        });

        // Presets
        document.querySelectorAll('[data-preset]').forEach(btn => {
            btn.addEventListener('click', () => {
                tabs = getPreset(btn.dataset.preset);
                renderTabs();
                renderPreview();
                showToast('Preset appliqué — pensez à enregistrer', 'info');
            });
        });

        // Add tab modal
        const modal = new bootstrap.Modal(document.getElementById('apAddModal'));
        document.getElementById('apAddTabBtn').addEventListener('click', () => {
            populateAddModal();
            modal.show();
        });

        // Type change in modal (card selector)
        document.querySelectorAll('.ap-type-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.ap-type-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                card.querySelector('input').checked = true;
                const type = card.dataset.type;
                document.getElementById('apNewModuleGroup').classList.toggle('d-none', type !== 'module');
                document.getElementById('apNewEtageGroup').classList.toggle('d-none', type !== 'etage');
                document.getElementById('apNewFonctionGroup').classList.toggle('d-none', type !== 'fonction');
                document.getElementById('apNewFonctionsGroup').classList.toggle('d-none', type !== 'fonctions');
                autoLabel();
            });
        });

        // onSelect for autoLabel is handled in populateAddModal init

        // Confirm add
        document.getElementById('apConfirmAdd').addEventListener('click', () => {
            const type = document.querySelector('input[name="apType"]:checked').value;
            let value = '', label = document.getElementById('apNewLabel').value.trim();

            if (type === 'module') {
                value = zerdaSelect.getValue('#apNewModule');
                if (!label) label = modulesData.find(m => m.id === value)?.code || value;
            } else if (type === 'etage') {
                value = zerdaSelect.getValue('#apNewEtage');
                if (!label) label = etagesData.find(e => e.id === value)?.nom || value;
            } else if (type === 'fonction') {
                value = zerdaSelect.getValue('#apNewFonction');
                if (!label) label = value;
            } else if (type === 'fonctions') {
                const checked = [];
                document.querySelectorAll('#apNewFonctionsChecks input:checked').forEach(cb => checked.push(cb.value));
                value = checked.join(',');
                if (!label) label = checked.join(' + ');
            }

            if (!value && type !== 'all') { showToast('Sélectionnez une valeur', 'error'); return; }
            if (!label) label = value;

            tabs.push({ type, label, value });
            renderTabs();
            renderPreview();
            modal.hide();
        });
    }

    function populateAddModal() {
        // Modules select
        const modOpts = modulesData.map(m => ({ value: m.id, label: `${m.code} — ${m.nom}` }));
        zerdaSelect.destroy('#apNewModule');
        zerdaSelect.init('#apNewModule', modOpts, { value: modOpts[0]?.value || '', onSelect: autoLabel });

        // Etages select
        const etOpts = etagesData.map(e => ({ value: e.id, label: `${e.nom} (${e.module_code || ''})` }));
        zerdaSelect.destroy('#apNewEtage');
        zerdaSelect.init('#apNewEtage', etOpts, { value: etOpts[0]?.value || '', onSelect: autoLabel });

        // Fonctions select
        const fOpts = fonctionsData.map(f => ({ value: f.code, label: `${f.code} — ${f.nom}` }));
        zerdaSelect.destroy('#apNewFonction');
        zerdaSelect.init('#apNewFonction', fOpts, { value: fOpts[0]?.value || '', onSelect: autoLabel });

        // Fonctions checkboxes
        const fChecks = document.getElementById('apNewFonctionsChecks');
        fChecks.innerHTML = fonctionsData.map(f =>
            `<div class="form-check"><input class="form-check-input" type="checkbox" value="${f.code}" id="apfc_${f.code}"><label class="form-check-label" for="apfc_${f.code}">${escapeHtml(f.code)} — ${escapeHtml(f.nom)}</label></div>`
        ).join('');

        document.getElementById('apNewLabel').value = '';
        document.querySelectorAll('.ap-type-card').forEach(c => c.classList.remove('active'));
        document.querySelector('.ap-type-card[data-type="module"]').classList.add('active');
        document.querySelector('input[name="apType"][value="module"]').checked = true;
        document.getElementById('apNewModuleGroup').classList.remove('d-none');
        document.getElementById('apNewEtageGroup').classList.add('d-none');
        document.getElementById('apNewFonctionGroup').classList.add('d-none');
        document.getElementById('apNewFonctionsGroup').classList.add('d-none');
    }

    function autoLabel() {
        const type = document.querySelector('input[name="apType"]:checked').value;
        const labelInput = document.getElementById('apNewLabel');
        if (labelInput.value) return; // Don't override manual input

        if (type === 'module') {
            const val = zerdaSelect.getValue('#apNewModule');
            const mod = modulesData.find(m => m.id === val);
            if (mod) labelInput.placeholder = mod.code;
        } else if (type === 'etage') {
            const val = zerdaSelect.getValue('#apNewEtage');
            const et = etagesData.find(e => e.id === val);
            if (et) labelInput.placeholder = et.nom;
        } else if (type === 'fonction') {
            labelInput.placeholder = zerdaSelect.getValue('#apNewFonction');
        }
    }

    window.initAffichageplanningPage = initAffichageplanningPage;
})();
</script>
