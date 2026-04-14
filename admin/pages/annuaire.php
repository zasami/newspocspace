<?php
$counts = [
    'total'    => (int) Db::getOne("SELECT COUNT(*) FROM annuaire WHERE is_active = 1"),
    'interne'  => (int) Db::getOne("SELECT COUNT(*) FROM annuaire WHERE is_active = 1 AND type = 'interne'"),
    'externe'  => (int) Db::getOne("SELECT COUNT(*) FROM annuaire WHERE is_active = 1 AND type = 'externe'"),
    'urgence'  => (int) Db::getOne("SELECT COUNT(*) FROM annuaire WHERE is_active = 1 AND type = 'urgence'"),
];
?>
<style>
.an-page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.an-page-title { font-weight: 700; margin: 0; flex: 1; }

.an-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.an-tab { background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 10px; padding: 8px 14px; font-size: .88rem; font-weight: 600; color: var(--cl-text-muted); cursor: pointer; transition: all .15s; display: flex; align-items: center; gap: 6px; }
.an-tab:hover { background: var(--cl-bg); color: var(--cl-text); }
.an-tab.active { background: #191918; color: #fff; border-color: #191918; }
.an-tab-badge { background: rgba(255,255,255,.2); font-size: .72rem; padding: 1px 7px; border-radius: 10px; }
.an-tab:not(.active) .an-tab-badge { background: var(--cl-bg); color: var(--cl-text-muted); }

.an-toolbar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }

.an-table-wrap { background: #fff; border: 1px solid var(--cl-border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.an-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
.an-table thead { background: #fff; }
.an-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--cl-text-muted); padding: 12px 14px; border-bottom: 1.5px solid var(--cl-border); text-align: left; background: #fff; white-space: nowrap; }
.an-table td { padding: 12px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); font-size: .88rem; vertical-align: middle; }
.an-table tbody tr:last-child td { border-bottom: none; }
.an-table tbody tr:hover td { background: var(--cl-bg); }

.an-badge { display: inline-block; font-size: .7rem; padding: 2px 8px; border-radius: 5px; font-weight: 600; }
.an-badge-interne { background: #bcd2cb; color: #2d4a43; }
.an-badge-externe { background: #B8C9D4; color: #3B4F6B; }
.an-badge-urgence { background: #E2B8AE; color: #7B3B2C; }

.an-fav-btn { background: none; border: none; cursor: pointer; font-size: 1rem; color: #D4C4A8; padding: 4px; }
.an-fav-btn.active { color: #EA8B2D; }

.an-tel { font-family: monospace; font-weight: 600; color: var(--cl-text); text-decoration: none; }
.an-tel:hover { color: #2d4a43; }

.an-row-btn { background: none; border: none; cursor: pointer; width: 32px; height: 32px; border-radius: 6px; color: var(--cl-text-muted); }
.an-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
.an-row-btn.danger:hover { background: #E2B8AE; color: #7B3B2C; }

.an-empty { text-align: center; padding: 60px 20px; color: var(--cl-text-muted); }
.an-empty i { font-size: 3rem; opacity: .15; display: block; margin-bottom: 12px; }

/* Scrollable modal body */
.an-modal-body-scroll { max-height: calc(85vh - 180px); overflow-y: auto; }

.an-form-row { display: flex; gap: 12px; margin-bottom: 14px; }
.an-form-row > * { flex: 1; }
.an-form-group { margin-bottom: 14px; }
.an-form-label { display: block; font-size: .75rem; font-weight: 600; color: var(--cl-text-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .3px; }

.an-import-help { font-size: .78rem; color: var(--cl-text-muted); background: var(--cl-bg); padding: 12px; border-radius: 8px; margin-bottom: 12px; }
.an-import-help code { background: rgba(0,0,0,.05); padding: 1px 5px; border-radius: 3px; font-size: .76rem; }
</style>

<div class="an-page-header">
    <h1 class="an-page-title">Annuaire téléphonique</h1>
    <span style="font-size:.82rem;color:var(--cl-text-muted)" id="anTotalCount"><?= $counts['total'] ?> contact(s)</span>
</div>

<div class="an-tabs">
    <button class="an-tab" data-tab="all">
        <i class="bi bi-grid"></i> Tous <span class="an-tab-badge"><?= $counts['total'] ?></span>
    </button>
    <button class="an-tab active" data-tab="interne">
        <i class="bi bi-building"></i> Interne <span class="an-tab-badge"><?= $counts['interne'] ?></span>
    </button>
    <button class="an-tab" data-tab="externe">
        <i class="bi bi-person-lines-fill"></i> Externe <span class="an-tab-badge"><?= $counts['externe'] ?></span>
    </button>
    <button class="an-tab" data-tab="urgence">
        <i class="bi bi-exclamation-triangle-fill"></i> Urgence <span class="an-tab-badge"><?= $counts['urgence'] ?></span>
    </button>
</div>

<div class="an-toolbar">
    <button class="btn btn-sm btn-primary" id="anNewBtn"><i class="bi bi-plus-lg"></i> Nouveau contact</button>
    <button class="btn btn-sm btn-outline-secondary" id="anImportBtn"><i class="bi bi-upload"></i> Importer CSV</button>
    <button class="btn btn-sm btn-outline-secondary" id="anExportBtn"><i class="bi bi-download"></i> Exporter CSV</button>
</div>

<div id="anTableWrap"></div>

<!-- Modal édition -->
<div class="modal fade" id="anFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="anModalTitle">Nouveau contact</h5>
                <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body an-modal-body-scroll">
                <form id="anForm">
                    <input type="hidden" name="id" id="anFormId">
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label">Type *</label>
                            <select name="type" id="anFormType" class="form-select form-select-sm" required>
                                <option value="interne">Interne</option>
                                <option value="externe">Externe</option>
                                <option value="urgence">Urgence</option>
                            </select>
                        </div>
                        <div>
                            <label class="an-form-label">Catégorie</label>
                            <input name="categorie" class="form-control form-control-sm" placeholder="Médecin, Pharmacie, etc.">
                        </div>
                    </div>
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label">Nom *</label>
                            <input name="nom" class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="an-form-label">Prénom</label>
                            <input name="prenom" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label">Fonction</label>
                            <input name="fonction" class="form-control form-control-sm" placeholder="Médecin, Infirmier...">
                        </div>
                        <div>
                            <label class="an-form-label">Service</label>
                            <input name="service" class="form-control form-control-sm" placeholder="Étage 3, Direction...">
                        </div>
                    </div>
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label">Téléphone principal</label>
                            <input name="telephone_1" class="form-control form-control-sm" placeholder="+41 22 ...">
                        </div>
                        <div>
                            <label class="an-form-label">Téléphone secondaire</label>
                            <input name="telephone_2" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="an-form-group">
                        <label class="an-form-label">Email</label>
                        <input name="email" type="email" class="form-control form-control-sm">
                    </div>
                    <div class="an-form-group">
                        <label class="an-form-label">Adresse</label>
                        <textarea name="adresse" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="an-form-group">
                        <label class="an-form-label">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label">Ordre d'affichage</label>
                            <input name="ordre" type="number" class="form-control form-control-sm" value="100">
                        </div>
                        <div style="display:flex;align-items:flex-end;padding-bottom:4px">
                            <label style="display:flex;align-items:center;gap:8px;font-size:.88rem;cursor:pointer;margin:0">
                                <input type="checkbox" name="is_favori" id="anFormFavori" class="form-check-input"> Favori
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-sm btn-primary" id="anSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal import -->
<div class="modal fade" id="anImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer depuis CSV</h5>
                <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body an-modal-body-scroll">
                <div class="an-import-help">
                    <strong>Format attendu</strong> (séparateur <code>;</code>) :<br>
                    <code>type;categorie;nom;prenom;fonction;service;telephone_1;telephone_2;email;adresse;notes</code><br><br>
                    <strong>Types</strong> : <code>interne</code>, <code>externe</code>, <code>urgence</code>.<br>
                    <strong>Exemple</strong> :<br>
                    <code>externe;medecin;Dr Martin;Jean;Médecin traitant;;+41221234567;;j.martin@example.ch;Rue X 10;Référent étage 3</code>
                </div>
                <label class="an-form-label">Coller le contenu CSV ou charger un fichier</label>
                <input type="file" id="anImportFile" accept=".csv,.txt" class="form-control form-control-sm" style="margin-bottom:8px">
                <textarea id="anImportText" class="form-control form-control-sm" rows="8" placeholder="Colle ton CSV ici..."></textarea>
                <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;margin-top:8px;cursor:pointer">
                    <input type="checkbox" id="anImportSkipHeader" class="form-check-input" checked> Ignorer la première ligne (entêtes)
                </label>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-sm btn-primary" id="anDoImportBtn"><i class="bi bi-upload"></i> Importer</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
(function() {
    let _items = [];
    let _currentTab = 'interne'; // default tab
    let _formModal = null;
    let _importModal = null;

    async function load() {
        try {
            const res = await adminApiPost('admin_get_annuaire');
            _items = res.data || [];
            const totalEl = document.getElementById('anTotalCount');
            if (totalEl) totalEl.textContent = _items.length + ' contact(s)';
            render();
        } catch (e) {
            console.error('[annuaire] load error:', e);
        }
    }

    function render() {
        const wrap = document.getElementById('anTableWrap');
        const filtered = _currentTab === 'all' ? _items : _items.filter(i => i.type === _currentTab);
        if (!filtered.length) {
            wrap.innerHTML = '<div class="an-table-wrap an-empty"><i class="bi bi-telephone"></i><div>Aucun contact</div></div>';
            return;
        }
        let html = `<div class="an-table-wrap"><table class="an-table">
            <thead><tr>
                <th style="width:40px"></th>
                <th>Nom</th>
                <th>Type</th>
                <th>Fonction / Service</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th style="width:80px"></th>
            </tr></thead><tbody>`;
        for (const it of filtered) {
            const fullName = [it.prenom, it.nom].filter(Boolean).join(' ');
            const fctServ = [it.fonction, it.service].filter(Boolean).join(' · ');
            const tel = it.telephone_1 ? `<a href="tel:${escapeHtml(it.telephone_1)}" class="an-tel">${escapeHtml(it.telephone_1)}</a>` : '';
            const tel2 = it.telephone_2 ? `<div style="font-size:.75rem;color:var(--cl-text-muted)">${escapeHtml(it.telephone_2)}</div>` : '';
            const email = it.email ? `<a href="mailto:${escapeHtml(it.email)}" style="color:var(--cl-text-muted)">${escapeHtml(it.email)}</a>` : '';
            html += `<tr data-id="${it.id}">
                <td><button class="an-fav-btn ${it.is_favori == 1 ? 'active' : ''}" data-fav="${it.id}"><i class="bi bi-star${it.is_favori == 1 ? '-fill' : ''}"></i></button></td>
                <td><strong>${escapeHtml(fullName)}</strong>${it.categorie ? '<div style="font-size:.72rem;color:var(--cl-text-muted)">' + escapeHtml(it.categorie) + '</div>' : ''}</td>
                <td><span class="an-badge an-badge-${it.type}">${it.type}</span></td>
                <td>${escapeHtml(fctServ)}</td>
                <td>${tel}${tel2}</td>
                <td>${email}</td>
                <td>
                    <button class="an-row-btn" data-edit="${it.id}" title="Modifier"><i class="bi bi-pencil"></i></button>
                    <button class="an-row-btn danger" data-del="${it.id}" title="Supprimer"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }
        html += '</tbody></table></div>';
        wrap.innerHTML = html;
    }

    function openFormModal(item) {
        const form = document.getElementById('anForm');
        form.reset();
        document.getElementById('anFormId').value = item?.id || '';
        document.getElementById('anModalTitle').textContent = item ? 'Modifier contact' : 'Nouveau contact';
        if (item) {
            for (const [k, v] of Object.entries(item)) {
                const input = form.elements[k];
                if (input && input.type !== 'checkbox') input.value = v ?? '';
            }
            document.getElementById('anFormFavori').checked = item.is_favori == 1;
        } else {
            // Default type based on current tab
            document.getElementById('anFormType').value = _currentTab === 'all' ? 'interne' : _currentTab;
        }
        if (!_formModal) _formModal = new bootstrap.Modal(document.getElementById('anFormModal'));
        _formModal.show();
    }

    let _saving = false;
    async function saveAnForm() {
        if (_saving) return;
        _saving = true;
        const btn = document.getElementById('anSaveBtn');
        btn.disabled = true;
        try {
            const form = document.getElementById('anForm');
            const data = {};
            for (const el of form.elements) {
                if (el.name) data[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
            }
            if (!data.nom) { toast('Le nom est requis'); return; }
            const r = await adminApiPost('admin_save_annuaire', data);
            if (r.success) {
                toast(r.message);
                _formModal?.hide();
                load();
            } else {
                toast(r.message || 'Erreur');
            }
        } finally {
            _saving = false;
            btn.disabled = false;
        }
    }

    let _importing = false;
    async function doAnImport() {
        if (_importing) return;
        _importing = true;
        const btn = document.getElementById('anDoImportBtn');
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';
        try {
            const csv = document.getElementById('anImportText').value.trim();
            if (!csv) { toast('Contenu vide'); return; }
            const skipHeader = document.getElementById('anImportSkipHeader').checked;
            const r = await adminApiPost('admin_import_annuaire_csv', { csv, skip_header: skipHeader });
            if (r.success) {
                toast(r.message);
                _importModal?.hide();
                load();
            } else {
                toast(r.message || 'Erreur');
            }
        } finally {
            _importing = false;
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }

    // Tab switching
    document.querySelectorAll('.an-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.an-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            _currentTab = tab.dataset.tab;
            render();
        });
    });

    // Table actions
    document.getElementById('anTableWrap').addEventListener('click', async (e) => {
        const editId = e.target.closest('[data-edit]')?.dataset.edit;
        const delId = e.target.closest('[data-del]')?.dataset.del;
        const favId = e.target.closest('[data-fav]')?.dataset.fav;
        if (editId) openFormModal(_items.find(i => i.id === editId));
        if (delId && confirm('Supprimer ce contact ?')) {
            const r = await adminApiPost('admin_delete_annuaire', { id: delId });
            if (r.success) { toast(r.message); load(); }
        }
        if (favId) {
            await adminApiPost('admin_toggle_favori_annuaire', { id: favId });
            load();
        }
    });

    // Toolbar buttons
    document.getElementById('anNewBtn').addEventListener('click', () => openFormModal(null));
    document.getElementById('anSaveBtn').addEventListener('click', saveAnForm);
    document.getElementById('anForm').addEventListener('submit', (e) => { e.preventDefault(); saveAnForm(); });

    // Import modal
    document.getElementById('anImportBtn').addEventListener('click', () => {
        document.getElementById('anImportText').value = '';
        document.getElementById('anImportFile').value = '';
        if (!_importModal) _importModal = new bootstrap.Modal(document.getElementById('anImportModal'));
        _importModal.show();
    });
    document.getElementById('anDoImportBtn').addEventListener('click', doAnImport);
    document.getElementById('anImportFile').addEventListener('change', (e) => {
        const f = e.target.files[0];
        if (!f) return;
        const reader = new FileReader();
        reader.onload = () => document.getElementById('anImportText').value = reader.result;
        reader.readAsText(f, 'UTF-8');
    });

    // Export
    document.getElementById('anExportBtn').addEventListener('click', async () => {
        const r = await adminApiPost('admin_export_annuaire_csv');
        if (r.success && r.csv) {
            const blob = new Blob([r.csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'annuaire_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            URL.revokeObjectURL(url);
            toast(r.count + ' contact(s) exporté(s)');
        }
    });

    load();
})();
</script>
