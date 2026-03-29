<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$modulesRaw = Db::fetchAll("SELECT * FROM modules ORDER BY ordre");
foreach ($modulesRaw as &$m) {
    $m['etages'] = Db::fetchAll("SELECT * FROM etages WHERE module_id = ? ORDER BY ordre", [$m['id']]);
    foreach ($m['etages'] as &$e) {
        $e['groupes'] = Db::fetchAll("SELECT * FROM groupes WHERE etage_id = ? ORDER BY ordre", [$e['id']]);
    }
}
unset($m, $e);

$configModulesRaw = Db::fetchAll(
    "SELECT m.id, m.code, m.nom, m.ordre,
            u.id AS responsable_id,
            CONCAT(u.prenom, ' ', u.nom) AS responsable_nom
     FROM modules m
     LEFT JOIN user_modules um ON um.module_id = m.id AND um.is_principal = 1
     LEFT JOIN users u ON u.id = um.user_id AND u.role IN ('responsable','admin','direction')
     ORDER BY m.ordre"
);

$responsablesRaw = Db::fetchAll(
    "SELECT id, prenom, nom, role FROM users
     WHERE is_active = 1 AND role IN ('responsable','admin','direction')
     ORDER BY nom, prenom"
);

$configNbEtages = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nb_etages'") ?: '6';
$configNbModules = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nb_modules'") ?: '4';
?>
<style>
.mod-input-sm { width: 100px; }
.mod-input-code { width: 70px; }
.mod-fs-xs { font-size: 0.68rem; }
.mod-fs-sm { font-size: 0.72rem; }
.mod-fs-075 { font-size: 0.75rem; }
.mod-fs-082 { font-size: 0.82rem; }
.mod-fs-085 { font-size: 0.85rem; }
.mod-fs-09 { font-size: 0.9rem; }
.mod-icon-empty { font-size: 2rem; opacity: 0.3; }
.mod-label-section { font-size: 0.75rem; margin-bottom: 3px; }
.mod-close-xs { font-size: 0.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> Structure des modules, étages et responsables.</p>
  <button class="btn btn-primary btn-sm" id="btnAddModule">
    <i class="bi bi-plus-lg"></i> Ajouter un module / unité
  </button>
</div>

<!-- Add module modal -->
<div class="modal fade" id="addModuleModal" tabindex="-1">
  <div class="modal-dialog modal-info">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-building-add"></i> Nouveau module / unité</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-bold">Code *</label>
            <input type="text" class="form-control form-control-sm" id="newModCode" placeholder="Ex: M5, POOL" maxlength="20">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-bold">Nom *</label>
            <input type="text" class="form-control form-control-sm" id="newModNom" placeholder="Ex: Module 5, Unité de soins B" maxlength="100">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Description</label>
          <textarea class="form-control form-control-sm" id="newModDescription" rows="2" placeholder="Description du module ou unité (optionnel)" maxlength="500"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold"><i class="bi bi-layers"></i> Étages à assigner</label>
          <div id="newModEtages" class="d-flex flex-wrap gap-2">
            <small class="text-muted">Chargement...</small>
          </div>
          <small class="text-muted">Sélectionnez un ou plusieurs étages pour ce module.</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold"><i class="bi bi-person-badge"></i> Responsable</label>
          <div class="zs-select" id="newModResponsable" data-placeholder="— Aucun —"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary btn-sm" id="confirmAddModule">
          <i class="bi bi-plus-lg"></i> Créer le module
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Générateur -->
<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-auto">
        <label class="form-label fw-600">Nombre d'étages</label>
        <input type="number" class="form-control" id="modGenNbEtages" min="1" max="50" value="<?= h($configNbEtages) ?>" class="mod-input-sm">
      </div>
      <div class="col-auto">
        <label class="form-label fw-600">Nombre de modules</label>
        <input type="number" class="form-control" id="modGenNbModules" min="1" max="50" value="<?= h($configNbModules) ?>" class="mod-input-sm">
      </div>
      <div class="col-auto">
        <button class="btn btn-success" id="btnModGenerate">
          <i class="bi bi-magic"></i> Générer la structure
        </button>
      </div>
      <div class="col-12">
        <small class="text-muted">
          <i class="bi bi-info-circle"></i>
          Crée automatiquement les modules et répartit les étages séquentiellement (M1: E1-E2, M2: E3-E4, etc.).
          Vous pouvez ensuite personnaliser chaque module.
        </small>
      </div>
    </div>
  </div>
</div>

<!-- Module cards -->
<div id="modulesGrid">
  <div class="text-center text-muted py-4"><span class="admin-spinner"></span> Chargement...</div>
</div>

<script<?= nonce() ?>>
(function() {
    let modulesData = <?= json_encode(array_values($modulesRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let allEtages = [];
    let configModules = <?= json_encode(array_values($configModulesRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let responsables = <?= json_encode(array_values($responsablesRaw), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let editingModules = new Set();

    // Build initial allEtages from injected data
    modulesData.forEach(m => {
        (m.etages || []).forEach(e => {
            allEtages.push({ ...e, module_id: m.id });
        });
    });

    function initModulesPage() {
        renderModuleCards();

        document.getElementById('btnModGenerate').addEventListener('click', generateStructure);

        // Add module modal
        const addModal = new bootstrap.Modal(document.getElementById('addModuleModal'));
        document.getElementById('btnAddModule').addEventListener('click', () => {
            document.getElementById('newModCode').value = '';
            document.getElementById('newModNom').value = '';
            document.getElementById('newModDescription').value = '';

            // Populate etages checkboxes
            const etagesContainer = document.getElementById('newModEtages');
            if (allEtages.length) {
                etagesContainer.innerHTML = allEtages.map(e =>
                    `<label class="form-check form-check-inline mod-fs-085">
                        <input class="form-check-input new-mod-etage" type="checkbox" value="${e.id}">
                        ${escapeHtml(e.nom)}
                    </label>`
                ).join('');
            } else {
                etagesContainer.innerHTML = '<small class="text-muted fst-italic">Aucun étage disponible. Générez d\'abord la structure.</small>';
            }

            // Populate responsables
            zerdaSelect.destroy('#newModResponsable');
            zerdaSelect.init('#newModResponsable',
                [{ value: '', label: '— Aucun —' }].concat(
                    responsables.map(u => ({ value: u.id, label: u.prenom + ' ' + u.nom }))
                ), { value: '', search: responsables.length > 5 });

            addModal.show();
        });
        document.getElementById('confirmAddModule').addEventListener('click', async () => {
            const code = document.getElementById('newModCode').value.trim();
            const nom = document.getElementById('newModNom').value.trim();
            const description = document.getElementById('newModDescription').value.trim();
            const responsable_id = zerdaSelect.getValue('#newModResponsable');
            const etage_ids = [];
            document.querySelectorAll('.new-mod-etage:checked').forEach(cb => etage_ids.push(cb.value));

            if (!code || !nom) { showToast('Code et nom requis', 'error'); return; }

            const btn = document.getElementById('confirmAddModule');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const res = await adminApiPost('admin_create_module', { code, nom, description, responsable_id, etage_ids });

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-lg"></i> Créer le module';

            if (res.success) {
                showToast('Module créé', 'success');
                addModal.hide();
                await refreshData();
                renderModuleCards();
            } else {
                showToast(res.message || 'Erreur', 'error');
            }
        });
    }

    async function refreshData() {
        const [modulesRes, configRes, refsRes] = await Promise.all([
            adminApiPost('admin_get_modules'),
            adminApiPost('admin_get_config'),
            adminApiPost('admin_get_planning_refs'),
        ]);

        if (modulesRes.success) {
            modulesData = modulesRes.modules || [];
        }
        if (configRes.success) {
            configModules = configRes.modules || [];
            if (configRes.config?.ems_nb_etages) document.getElementById('modGenNbEtages').value = configRes.config.ems_nb_etages;
            if (configRes.config?.ems_nb_modules) document.getElementById('modGenNbModules').value = configRes.config.ems_nb_modules;
        }
        if (refsRes.success) {
            responsables = (refsRes.users || []).filter(
                u => ['admin', 'direction', 'responsable'].includes(u.role)
            );
        }

        // Flat list of all etages
        allEtages = [];
        modulesData.forEach(m => {
            (m.etages || []).forEach(e => {
                allEtages.push({ ...e, module_id: m.id });
            });
        });
    }

    // ── Generate structure ──
    async function generateStructure() {
        const nbEtages = parseInt(document.getElementById('modGenNbEtages').value) || 0;
        const nbModules = parseInt(document.getElementById('modGenNbModules').value) || 0;

        if (nbEtages < 1 || nbModules < 1) {
            showToast('Entrez au moins 1 étage et 1 module', 'error');
            return;
        }

        if (!await adminConfirm({ title: 'Générer les modules', text: `Générer <strong>${nbModules} module(s)</strong> avec <strong>${nbEtages} étage(s)</strong> ?<br><br>Les étages seront répartis automatiquement.<br><span class="text-danger fw-bold">Attention : cela remplace la structure existante.</span>`, icon: 'bi-building-gear', type: 'warning', okText: 'Générer' })) return;

        document.getElementById('modulesGrid').innerHTML =
            '<div class="text-center py-3"><span class="admin-spinner"></span> Génération...</div>';

        const res = await adminApiPost('admin_generate_structure', {
            nb_etages: nbEtages,
            nb_modules: nbModules,
        });

        if (res.success) {
            showToast(res.message, 'success');
            modulesData = res.modules || [];
            allEtages = [];
            modulesData.forEach(m => {
                (m.etages || []).forEach(e => {
                    allEtages.push({ ...e, module_id: m.id });
                });
            });

            // Tous en édition après génération
            editingModules.clear();
            modulesData.forEach(m => editingModules.add(m.id));

            const cfgRes = await adminApiPost('admin_get_config');
            if (cfgRes.success) configModules = cfgRes.modules || [];

            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
            renderModuleCards();
        }
    }

    // ── Render module cards ──
    function renderModuleCards() {
        const container = document.getElementById('modulesGrid');

        if (!modulesData.length) {
            container.innerHTML = `<div class="text-center text-muted py-4">
                <i class="bi bi-building mod-icon-empty"></i>
                <p class="mt-2">Aucun module. Utilisez le générateur ci-dessus pour créer la structure.</p>
            </div>`;
            return;
        }

        // Responsable map
        const respMap = {};
        const respNameMap = {};
        configModules.forEach(cm => {
            if (cm.responsable_id) {
                respMap[cm.id] = cm.responsable_id;
                respNameMap[cm.id] = cm.responsable_nom || '';
            }
        });

        let html = '<div class="row g-3">';

        modulesData.forEach((m, idx) => {
            const etages = m.etages || [];
            const isEditing = editingModules.has(m.id);
            const currentResp = respMap[m.id] || '';
            const currentRespNom = respNameMap[m.id] || '';

            if (isEditing) {
                // ── MODE ÉDITION ──
                const etageChecks = allEtages.map(e => {
                    const checked = e.module_id === m.id ? 'checked' : '';
                    return `<label class="form-check form-check-inline mod-fs-082">
                        <input class="form-check-input etage-check" type="checkbox" value="${e.id}" ${checked}>
                        ${escapeHtml(e.nom)}
                    </label>`;
                }).join('');

                // Groupes par étage
                const etageGroupesHtml = etages.map(e => {
                    const grps = (e.groupes || []);
                    const grpBadges = grps.map(g =>
                        `<span class="badge bg-light text-dark border me-1 mb-1 d-inline-flex align-items-center gap-1 mod-fs-sm">
                            ${escapeHtml(g.code)} <span class="text-muted">-</span> ${escapeHtml(g.nom)}
                            <button type="button" class="btn-close btn-close-sm ms-1 btn-del-groupe mod-close-xs" data-groupe-id="${g.id}" title="Supprimer"></button>
                        </span>`
                    ).join('');
                    return `<div class="mb-1">
                        <div class="d-flex align-items-center gap-1 mod-fs-075">
                            <span class="text-muted fw-600">${escapeHtml(e.code)} :</span>
                            ${grpBadges}
                            <button type="button" class="btn btn-outline-success btn-sm px-1 py-0 btn-add-groupe mod-fs-xs" data-etage-id="${e.id}" data-etage-code="${escapeHtml(e.code)}" title="Ajouter un groupe">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>`;
                }).join('');

                html += `
                <div class="col-md-6 col-xl-4">
                  <div class="card module-card border-primary" data-module-id="${m.id}">
                    <div class="card-header d-flex align-items-center gap-2 py-2 bg-primary bg-opacity-10">
                      <span class="badge bg-primary">${idx + 1}</span>
                      <input type="text" class="form-control form-control-sm mod-code fw-bold mod-input-code" value="${escapeHtml(m.code)}" title="Code">
                      <span class="text-muted">—</span>
                      <input type="text" class="form-control form-control-sm mod-nom flex-grow-1" value="${escapeHtml(m.nom)}" title="Nom du module">
                    </div>
                    <div class="card-body py-2">
                      <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-600">
                          <i class="bi bi-layers"></i> Étages assignés
                        </label>
                        <div class="etage-checks">${etageChecks}</div>
                      </div>
                      ${etages.length ? `<div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-600">
                          <i class="bi bi-people"></i> Groupes de résidents
                        </label>
                        ${etageGroupesHtml}
                      </div>` : ''}
                      <div class="mb-1">
                        <label class="form-label form-label-sm mb-1 fw-600">
                          <i class="bi bi-person-badge"></i> Responsable
                        </label>
                        <div class="zs-select mod-resp" data-module-id="${m.id}" data-placeholder="— Aucun —" data-resp-value="${currentResp}"></div>
                      </div>
                    </div>
                    <div class="card-footer py-1 text-end">
                      <button class="btn btn-sm btn-outline-secondary btn-cancel-module me-1">
                        <i class="bi bi-x-lg"></i> Annuler
                      </button>
                      <button class="btn btn-sm btn-primary btn-save-module">
                        <i class="bi bi-check-lg"></i> Valider
                      </button>
                    </div>
                  </div>
                </div>`;
            } else {
                // ── MODE VUE ──
                const etagesBadges = etages.length
                    ? etages.map(e => {
                        const groupes = (e.groupes || []);
                        const groupesBadges = groupes.length
                            ? groupes.map(g => `<span class="badge bg-warning bg-opacity-25 text-dark border mod-fs-xs">${escapeHtml(e.code)}-${escapeHtml(g.code)}</span>`).join(' ')
                            : '';
                        return `<div class="d-inline-flex align-items-center gap-1 me-2 mb-1">
                            <span class="badge bg-info bg-opacity-25 text-dark border">${escapeHtml(e.nom)}</span>
                            ${groupesBadges}
                        </div>`;
                    }).join('')
                    : '<span class="text-muted fst-italic mod-fs-082">Aucun étage</span>';

                const respBadge = currentRespNom
                    ? `<span class="badge bg-success bg-opacity-25 text-dark border"><i class="bi bi-person-badge"></i> ${escapeHtml(currentRespNom)}</span>`
                    : '<span class="text-muted fst-italic mod-fs-082">Non assigné</span>';

                html += `
                <div class="col-md-6 col-xl-4">
                  <div class="card module-card h-100" data-module-id="${m.id}">
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                      <span class="badge bg-primary">${idx + 1}</span>
                      <span class="fw-bold text-uppercase mod-fs-085">${escapeHtml(m.code)}</span>
                      <span class="text-muted">—</span>
                      <span class="flex-grow-1 mod-fs-09">${escapeHtml(m.nom)}</span>
                    </div>
                    <div class="card-body py-2">
                      <div class="mb-2">
                        <div class="text-muted mod-label-section">
                          <i class="bi bi-layers"></i> Étages
                        </div>
                        <div>${etagesBadges}</div>
                      </div>
                      <div>
                        <div class="text-muted mod-label-section">
                          <i class="bi bi-person-badge"></i> Responsable
                        </div>
                        ${respBadge}
                      </div>
                    </div>
                    <div class="card-footer py-1 text-end">
                      <button class="btn btn-sm btn-outline-danger btn-delete-module me-1" title="Supprimer">
                        <i class="bi bi-trash"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-primary btn-edit-module">
                        <i class="bi bi-pencil"></i> Modifier
                      </button>
                    </div>
                  </div>
                </div>`;
            }
        });

        html += '</div>';
        container.innerHTML = html;

        // Init zerdaSelect for each .mod-resp in editing mode
        container.querySelectorAll('.mod-resp.zs-select').forEach(el => {
            const respValue = el.dataset.respValue || '';
            const respOptions = [{ value: '', label: '— Aucun —' }].concat(
                responsables.map(u => ({ value: u.id, label: u.prenom + ' ' + u.nom }))
            );
            zerdaSelect.init(el, respOptions, { value: respValue, search: responsables.length > 5 });
        });

        // Attach handlers
        container.querySelectorAll('.btn-save-module').forEach(btn => {
            btn.addEventListener('click', function() {
                saveModuleCard(this.closest('.module-card'));
            });
        });
        container.querySelectorAll('.btn-edit-module').forEach(btn => {
            btn.addEventListener('click', function() {
                editingModules.add(this.closest('.module-card').dataset.moduleId);
                renderModuleCards();
            });
        });
        container.querySelectorAll('.btn-cancel-module').forEach(btn => {
            btn.addEventListener('click', function() {
                editingModules.delete(this.closest('.module-card').dataset.moduleId);
                renderModuleCards();
            });
        });
        container.querySelectorAll('.btn-delete-module').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteModule(this.closest('.module-card').dataset.moduleId);
            });
        });
        container.querySelectorAll('.btn-add-groupe').forEach(btn => {
            btn.addEventListener('click', function() {
                addGroupe(this.dataset.etageId, this.dataset.etageCode);
            });
        });
        container.querySelectorAll('.btn-del-groupe').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteGroupe(this.dataset.groupeId);
            });
        });
    }

    async function saveModuleCard(card) {
        const moduleId = card.dataset.moduleId;
        const code = card.querySelector('.mod-code').value.trim();
        const nom = card.querySelector('.mod-nom').value.trim();
        const respId = zerdaSelect.getValue(card.querySelector('.mod-resp'));

        const etageIds = [];
        card.querySelectorAll('.etage-check:checked').forEach(cb => {
            etageIds.push(cb.value);
        });

        if (!code || !nom) {
            showToast('Code et nom requis', 'error');
            return;
        }

        const btn = card.querySelector('.btn-save-module');
        btn.disabled = true;
        btn.innerHTML = '<span class="admin-spinner"></span>';

        const res = await adminApiPost('admin_update_module_config', {
            module_id: moduleId,
            code: code,
            nom: nom,
            etage_ids: etageIds,
            responsable_id: respId,
        });

        if (res.success) {
            showToast('Module mis à jour', 'success');
            editingModules.delete(moduleId);
            await refreshData();
            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Valider';
            btn.disabled = false;
        }
    }

    async function deleteModule(id) {
        if (!await adminConfirm({ title: 'Supprimer le module', text: 'Les étages et groupes associés seront également supprimés.<br>Cette action est irréversible.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
        const res = await adminApiPost('admin_delete_module', { id });
        if (res.success) {
            showToast('Module supprimé', 'success');
            await refreshData();
            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    async function addGroupe(etageId, etageCode) {
        const code = prompt(`Code du groupe pour ${etageCode} (ex: A, B) :`);
        if (!code || !code.trim()) return;
        const nom = prompt(`Nom du groupe (ex: Groupe A) :`, `Groupe ${code.trim().toUpperCase()}`);
        if (!nom || !nom.trim()) return;

        const res = await adminApiPost('admin_create_groupe', {
            etage_id: etageId,
            code: code.trim(),
            nom: nom.trim(),
        });
        if (res.success) {
            showToast('Groupe créé', 'success');
            await refreshData();
            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    async function deleteGroupe(groupeId) {
        if (!confirm('Supprimer ce groupe ?')) return;
        const res = await adminApiPost('admin_delete_groupe', { id: groupeId });
        if (res.success) {
            showToast('Groupe supprimé', 'success');
            await refreshData();
            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    window.initModulesPage = initModulesPage;
})();
</script>
