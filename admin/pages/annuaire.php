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
    <h5 class="an-page-title"><i class="bi bi-telephone"></i> Annuaire téléphonique</h5>
    <span style="font-size:.82rem;color:var(--cl-text-muted)" id="anTotalCount"><?= $counts['total'] ?> contact(s)</span>
</div>

<div class="an-tabs">
    <button class="an-tab active" data-tab="collegues">
        <i class="bi bi-people-fill"></i> Appel SpocSpace
    </button>
    <button class="an-tab" data-tab="all">
        <i class="bi bi-grid"></i> Tous <span class="an-tab-badge"><?= $counts['total'] ?></span>
    </button>
    <button class="an-tab" data-tab="interne">
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
                            <label class="an-form-label">Nature *</label>
                            <select id="anFormNature" class="form-select form-select-sm">
                                <option value="0">Personne</option>
                                <option value="1">Organisation / Société</option>
                            </select>
                            <input type="hidden" name="est_organisation" id="anFormEstOrg" value="0">
                        </div>
                    </div>
                    <div class="an-form-group">
                        <label class="an-form-label" id="anFormLabelCat">Catégorie</label>
                        <input name="categorie" id="anFormCategorie" list="anCategorieList" class="form-control form-control-sm" placeholder="Choisir ou taper...">
                        <datalist id="anCategorieList"></datalist>
                        <small class="text-muted" style="font-size:.72rem">Choisir dans la liste ou taper un texte libre</small>
                    </div>
                    <div class="an-form-row" id="anFormNamesRow">
                        <div>
                            <label class="an-form-label" id="anFormLabelNom">Nom *</label>
                            <input name="nom" class="form-control form-control-sm" required>
                        </div>
                        <div id="anFormPrenomCol">
                            <label class="an-form-label">Prénom</label>
                            <input name="prenom" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="an-form-row">
                        <div>
                            <label class="an-form-label" id="anFormLabelFonction">Fonction</label>
                            <input name="fonction" id="anFormFonction" list="anFonctionList" class="form-control form-control-sm" placeholder="Choisir ou taper...">
                            <datalist id="anFonctionList"></datalist>
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
                    <code>type;categorie;nom;prenom;fonction;service;telephone_1;telephone_2;email;adresse;notes;organisation</code><br><br>
                    <strong>Types</strong> : <code>interne</code>, <code>externe</code>, <code>urgence</code>.<br>
                    <strong>Organisation</strong> : <code>1</code>/<code>oui</code> pour société/cabinet/labo (laisser vide ou <code>0</code> pour personne).<br><br>
                    <strong>Exemples</strong> :<br>
                    <code>externe;medecin;Dr Martin;Jean;Médecin traitant;;+41221234567;;j.martin@example.ch;Rue X 10;Référent étage 3;0</code><br>
                    <code>externe;pharmacie;Pharmacie du Centre;;Pharmacie;;+41221234500;;contact@pharma-centre.ch;Place Neuve 5;Service livraison;1</code>
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
    let _collegues = [];
    let _currentTab = 'collegues';
    let _formModal = null;
    let _importModal = null;
    let _searchQuery = '';
    let _searchHandler = null;

    function _markOnline(users) {
        const now = Date.now();
        return users.map(u => ({
            ...u,
            is_online: u.last_seen_at && (now - new Date(u.last_seen_at.replace(' ', 'T')).getTime() < 35000) ? 1 : 0,
        }));
    }

    async function load() {
        try {
            const [r1, r2] = await Promise.all([
                adminApiPost('admin_get_annuaire'),
                adminApiPost('admin_get_users').catch(() => ({ data: [] })),
            ]);
            _items = r1?.data || [];
            const adminId = window.__SS_ADMIN__?.adminId;
            const users = (r2?.data || r2?.users || []).filter(u => u && u.id !== adminId && u.is_active != 0);
            _collegues = _markOnline(users);
            const totalEl = document.getElementById('anTotalCount');
            if (totalEl) totalEl.textContent = _items.length + ' contact(s)';
            render();
        } catch (e) {
            console.error('[annuaire] load error:', e);
        }
    }

    function escHtml(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function renderCollegues() {
        const wrap = document.getElementById('anTableWrap');
        let list = _collegues;
        if (_searchQuery && _searchQuery.length >= 2) {
            const q = _searchQuery.toLowerCase();
            list = list.filter(u =>
                [u.prenom, u.nom, u.email, u.fonction_nom]
                    .filter(Boolean).some(v => v.toLowerCase().includes(q))
            );
        }
        if (!list.length) {
            wrap.innerHTML = '<div class="an-table-wrap an-empty"><i class="bi bi-people"></i><div>Aucun collègue trouvé</div></div>';
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const oa = a.is_online ? 0 : 1, ob = b.is_online ? 0 : 1;
            if (oa !== ob) return oa - ob;
            return (a.nom || '').localeCompare(b.nom || '');
        });
        const onlineCount = list.filter(u => u.is_online).length;
        let html = `<div class="an-table-wrap"><table class="an-table">
            <thead><tr>
                <th>Collègue (${onlineCount} en ligne)</th>
                <th>Fonction</th>
                <th>Statut</th>
                <th style="width:110px">Appel</th>
            </tr></thead><tbody>`;
        for (const u of sorted) {
            const fullName = [u.prenom, u.nom].filter(Boolean).join(' ');
            const online = !!u.is_online;
            const dot = `<span class="ss-presence-dot ${online ? 'ss-presence-online' : 'ss-presence-offline'}" style="display:inline-block;position:relative;margin-right:6px"></span>`;
            const callBtns = online
                ? `<button class="btn btn-sm btn-success" data-call-audio="${escHtml(u.id)}" title="Appel audio"><i class="bi bi-telephone-fill"></i></button>
                   <button class="btn btn-sm btn-info" data-call-video="${escHtml(u.id)}" title="Appel vidéo"><i class="bi bi-camera-video-fill"></i></button>`
                : `<button class="btn btn-sm btn-secondary" disabled title="Hors ligne"><i class="bi bi-telephone-x"></i></button>`;
            html += `<tr>
                <td>${dot}<strong>${escHtml(fullName)}</strong></td>
                <td>${escHtml(u.fonction_nom || '—')}</td>
                <td>${online ? '<span style="color:#2d4a43;font-weight:600">En ligne</span>' : '<span style="color:#9B9B9B">Hors ligne</span>'}</td>
                <td>${callBtns}</td>
            </tr>`;
        }
        html += '</tbody></table></div>';
        wrap.innerHTML = html;
    }

    function render() {
        if (_currentTab === 'collegues') { renderCollegues(); return; }
        const wrap = document.getElementById('anTableWrap');
        let filtered = _currentTab === 'all' ? _items : _items.filter(i => i.type === _currentTab);
        if (_searchQuery && _searchQuery.length >= 2) {
            const q = _searchQuery.toLowerCase();
            filtered = filtered.filter(i =>
                [i.nom, i.prenom, i.fonction, i.service, i.telephone_1, i.telephone_2, i.email, i.categorie]
                    .filter(Boolean).some(v => v.toLowerCase().includes(q))
            );
        }
        if (!filtered.length) {
            wrap.innerHTML = '<div class="an-table-wrap an-empty"><i class="bi bi-telephone"></i><div>Aucun contact trouvé</div></div>';
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
            const isOrg = !!Number(it.est_organisation);
            const fullName = isOrg ? (it.nom || '') : [it.prenom, it.nom].filter(Boolean).join(' ');
            const fctServ = [it.fonction, it.service].filter(Boolean).join(' · ');
            const tel = it.telephone_1 ? `<a href="tel:${escapeHtml(it.telephone_1)}" class="an-tel">${escapeHtml(it.telephone_1)}</a>` : '';
            const tel2 = it.telephone_2 ? `<div style="font-size:.75rem;color:var(--cl-text-muted)">${escapeHtml(it.telephone_2)}</div>` : '';
            const email = it.email ? `<a href="mailto:${escapeHtml(it.email)}" style="color:var(--cl-text-muted)">${escapeHtml(it.email)}</a>` : '';
            const natureIcon = isOrg ? 'bi-building' : 'bi-person-circle';
            html += `<tr data-id="${it.id}">
                <td><button class="an-fav-btn ${it.is_favori == 1 ? 'active' : ''}" data-fav="${it.id}"><i class="bi bi-star${it.is_favori == 1 ? '-fill' : ''}"></i></button></td>
                <td>
                    <i class="bi ${natureIcon}" style="color:var(--cl-text-muted);margin-right:6px"></i>
                    <strong>${escapeHtml(fullName)}</strong>
                    ${it.categorie ? '<div style="font-size:.72rem;color:var(--cl-text-muted);margin-left:22px">' + escapeHtml(it.categorie) + '</div>' : ''}
                </td>
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

    const SUGGESTIONS_PERSONNE = {
        categorie: [
            'Médecin', 'Médecin traitant', 'Spécialiste', 'Cardiologue', 'Dermatologue',
            'Gériatre', 'Neurologue', 'Ophtalmologue', 'Oncologue', 'Pédiatre',
            'Psychiatre', 'Psychologue', 'Psychothérapeute',
            'Dentiste', 'Orthodontiste',
            'Infirmier(ère)', 'Infirmier indépendant',
            'Physiothérapeute', 'Ostéopathe', 'Kinésithérapeute', 'Ergothérapeute',
            'Logopédiste', 'Diététicien(ne)', 'Podologue', 'Sage-femme',
            'Aide-soignant', 'Auxiliaire de vie',
            'Pharmacien', 'Assistant médical',
            'Avocat', 'Notaire', 'Tuteur', 'Curateur',
            'Famille', 'Proche', 'Représentant légal',
            'Bénévole', 'Aumônier', 'Coiffeur', 'Pédicure',
            'Autre',
        ],
        fonction: [
            'Médecin traitant', 'Médecin de garde', 'Médecin référent',
            'Infirmier responsable', 'Infirmier de nuit',
            'Spécialiste consultant', 'Thérapeute', 'Psychologue clinicien',
            'Famille référente', 'Personne de contact',
            'Direction', 'Responsable',
        ],
    };

    const SUGGESTIONS_ORGANISATION = {
        categorie: [
            'Pharmacie', 'Pharmacie de garde', 'Pharmacie 24h',
            'Laboratoire', 'Laboratoire d\'analyses',
            'Cabinet médical', 'Cabinet de groupe', 'Cabinet dentaire',
            'Clinique', 'Clinique privée', 'Hôpital', 'Hôpital universitaire',
            'EMS', 'EMS partenaire', 'Centre de soins',
            'Centre de réadaptation', 'Centre de jour',
            'Service de soins à domicile', 'Imad', 'CMS',
            'Imagerie médicale', 'Radiologie', 'IRM/Scanner',
            'Centre d\'urgences', 'SMUR', 'Centrale d\'ambulances',
            'Mutuelle', 'Assurance', 'Caisse maladie',
            'Service social', 'OPF', 'Tutelle',
            'Pompes funèbres', 'Service funéraire',
            'Fournisseur', 'Traiteur', 'Maintenance',
            'Service technique', 'Société de nettoyage',
            'Société de transport', 'Taxi médical',
            'Administration', 'Mairie', 'Hospice général',
            'Association', 'Aumônerie',
            'Autre',
        ],
        fonction: [
            'Service principal', 'Accueil', 'Secrétariat', 'Direction',
            'Service de garde', 'Service d\'urgence', 'Standard',
            'Service livraison', 'Service commercial', 'Service technique',
            'Comptabilité', 'Facturation',
        ],
    };

    function _fillDatalist(id, options) {
        const dl = document.getElementById(id);
        if (!dl) return;
        dl.innerHTML = options.map(o => `<option value="${escHtml(o)}"></option>`).join('');
    }

    function _applyNature(isOrg) {
        document.getElementById('anFormEstOrg').value = isOrg ? '1' : '0';
        const prenomCol = document.getElementById('anFormPrenomCol');
        const labelNom = document.getElementById('anFormLabelNom');
        const labelFct = document.getElementById('anFormLabelFonction');
        const labelCat = document.getElementById('anFormLabelCat');

        const sugg = isOrg ? SUGGESTIONS_ORGANISATION : SUGGESTIONS_PERSONNE;
        _fillDatalist('anCategorieList', sugg.categorie);
        _fillDatalist('anFonctionList', sugg.fonction);

        if (isOrg) {
            prenomCol.style.display = 'none';
            labelNom.textContent = 'Nom de l\'organisation *';
            labelFct.textContent = 'Type d\'établissement';
            labelCat.textContent = 'Catégorie (pharmacie, labo, clinique...)';
            document.getElementById('anFormNamesRow').style.display = 'block';
        } else {
            prenomCol.style.display = '';
            labelNom.textContent = 'Nom *';
            labelFct.textContent = 'Fonction';
            labelCat.textContent = 'Catégorie (médecin, dentiste, psy...)';
            document.getElementById('anFormNamesRow').style.display = 'flex';
        }
    }

    function openFormModal(item) {
        const form = document.getElementById('anForm');
        form.reset();
        document.getElementById('anFormId').value = item?.id || '';
        document.getElementById('anModalTitle').textContent = item ? 'Modifier contact' : 'Nouveau contact';
        const isOrg = item ? !!Number(item.est_organisation) : false;
        document.getElementById('anFormNature').value = isOrg ? '1' : '0';
        if (item) {
            for (const [k, v] of Object.entries(item)) {
                const input = form.elements[k];
                if (input && input.type !== 'checkbox' && input.type !== 'hidden') input.value = v ?? '';
            }
            document.getElementById('anFormFavori').checked = item.is_favori == 1;
        } else {
            document.getElementById('anFormType').value = _currentTab === 'all' || _currentTab === 'collegues' ? 'externe' : _currentTab;
        }
        _applyNature(isOrg);
        if (!_formModal) _formModal = new bootstrap.Modal(document.getElementById('anFormModal'));
        _formModal.show();
    }

    // Listen for nature change
    document.getElementById('anFormNature').addEventListener('change', (e) => {
        _applyNature(e.target.value === '1');
    });

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
        const callAudio = e.target.closest('[data-call-audio]')?.dataset.callAudio;
        const callVideo = e.target.closest('[data-call-video]')?.dataset.callVideo;

        if (callAudio || callVideo) {
            const userId = callAudio || callVideo;
            const u = _collegues.find(x => x.id === userId);
            if (!u) return;
            if (typeof window.ssStartCall === 'function') {
                window.ssStartCall(u, callAudio ? 'audio' : 'video');
            } else {
                toast('Module appel non chargé');
            }
            return;
        }
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

    // Refresh collegues presence every 10s
    setInterval(async () => {
        if (_currentTab !== 'collegues') return;
        try {
            const r = await adminApiPost('admin_get_users');
            const adminId = window.__SS_ADMIN__?.adminId;
            const users = (r?.data || r?.users || []).filter(u => u && u.id !== adminId && u.is_active != 0);
            _collegues = _markOnline(users);
            renderCollegues();
        } catch {}
    }, 10000);

    // Hook the admin global search input → filter local list
    const searchInput = document.getElementById('topbarSearchInput');
    if (searchInput) {
        const originalPlaceholder = searchInput.placeholder;
        searchInput.placeholder = 'Rechercher dans l\'annuaire...';
        _searchHandler = () => {
            _searchQuery = searchInput.value.trim();
            render();
        };
        searchInput.addEventListener('input', _searchHandler);

        // Cleanup when leaving the page
        const cleanup = () => {
            searchInput.removeEventListener('input', _searchHandler);
            searchInput.placeholder = originalPlaceholder;
        };
        window.addEventListener('beforeunload', cleanup);
        // Also cleanup when admin SPA loads another page (DOM check)
        const observer = new MutationObserver(() => {
            if (!document.getElementById('anTableWrap')) {
                cleanup();
                observer.disconnect();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

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
