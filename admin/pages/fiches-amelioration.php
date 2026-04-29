<?php
require_responsable();
// ─── Données serveur ────────────────────────────────────────────────────────
$faStats = [
    'total'    => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration"),
    'soumise'  => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'soumise'"),
    'en_revue' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_revue'"),
    'en_cours' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'en_cours'"),
    'realisee' => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE statut = 'realisee'"),
    'haute'    => (int) Db::getOne("SELECT COUNT(*) FROM fiches_amelioration WHERE criticite='haute' AND statut NOT IN ('realisee','rejetee')"),
];

$faStatutLabels = [
    'soumise'  => ['label' => 'Soumise',  'cls' => 'fa-badge-teal'],
    'en_revue' => ['label' => 'En revue', 'cls' => 'fa-badge-orange'],
    'en_cours' => ['label' => 'En cours', 'cls' => 'fa-badge-purple'],
    'realisee' => ['label' => 'Réalisée', 'cls' => 'fa-badge-green'],
    'rejetee'  => ['label' => 'Rejetée',  'cls' => 'fa-badge-red'],
];
$faCriticiteLabels = [
    'faible'  => ['label' => 'Faible',  'cls' => 'fa-badge-teal'],
    'moyenne' => ['label' => 'Moyenne', 'cls' => 'fa-badge-orange'],
    'haute'   => ['label' => 'Haute',   'cls' => 'fa-badge-red'],
];
$faCategorieLabels = [
    'securite' => 'Sécurité', 'qualite_soins' => 'Qualité des soins',
    'organisation' => 'Organisation', 'materiel' => 'Matériel',
    'communication' => 'Communication', 'autre' => 'Autre',
];
?>
<style>
.fa-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; align-items:center; }

/* Stat cards cliquables + état actif */
.fa-stat-click { cursor:pointer; transition: transform .1s, box-shadow .15s, border-color .15s; position: relative; }
.fa-stat-click:hover { transform: translateY(-1px); box-shadow: 0 2px 10px rgba(0,0,0,.06); }
.fa-stat-click.active {
    border-color: #1A1A1A !important;
    background: #F7F5F2 !important;
    box-shadow: 0 0 0 2px #1A1A1A, 0 4px 12px rgba(0,0,0,.08) !important;
}
.fa-stat-click.active::after {
    content: '\f633'; font-family: 'bootstrap-icons';
    position: absolute; top: 6px; right: 8px;
    width: 18px; height: 18px; border-radius: 50%;
    background: #1A1A1A; color: #fff;
    font-size: 10px; display: flex; align-items: center; justify-content: center;
    pointer-events: none;
}
.fa-stat-click * { pointer-events: none; }

/* Criticity pill tabs — plus large + fond */
#faCriticiteTabs { min-width: 340px; padding: 4px !important; }
#faCriticiteTabs .email-tab { padding: 7px 14px !important; font-size: .82rem !important; }
#faCriticiteTabs .email-tabs-slider { width: calc(25% - 2px) !important; top: 4px !important; left: 4px !important; height: calc(100% - 8px) !important; }
#faCriticiteTabs .email-tabs-slider.pos-1 { transform: translateX(100%); }
#faCriticiteTabs .email-tabs-slider.pos-2 { transform: translateX(200%); }
#faCriticiteTabs .email-tabs-slider.pos-3 { transform: translateX(300%); }

.fa-adm-table { background:#fff; border:1px solid #E8E4DE; border-radius:12px; overflow:hidden; }
.fa-adm-table table { margin:0; font-size:.9rem; }
.fa-adm-table thead { background:#F7F5F2; }
.fa-adm-table th { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6c757d; padding:10px 12px; border-bottom:1px solid #E8E4DE; }
.fa-adm-table td { padding:10px 12px; vertical-align:middle; border-bottom:1px solid #F0EDE8; }
.fa-adm-table tr { cursor:pointer; transition:background .1s; }
.fa-adm-table tr:hover td { background:#F7F5F2; }

.fa-badge { font-size:.68rem; font-weight:700; padding:3px 8px; border-radius:4px; color:#fff; text-transform:uppercase; letter-spacing:.3px; display:inline-block; white-space:nowrap; }
.fa-badge-teal { background:#2d4a43; }
.fa-badge-orange { background:#C4A882; color:#3a2e1a; }
.fa-badge-purple { background:#5B4B6B; }
.fa-badge-green { background:#bcd2cb; color:#2d4a43; }
.fa-badge-red { background:#A85B4A; }

.fa-auth-cell { display:flex; align-items:center; gap:8px; }
.fa-avatar { width:28px; height:28px; border-radius:50%; background:#C4A882; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; flex-shrink:0; }
.fa-anon { color:#6c757d; font-style:italic; }

.fa-section { margin-bottom:18px; }
.fa-section-title { font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
.fa-desc-box { background:#F7F5F2; border-left:3px solid #C4A882; padding:12px 15px; border-radius:0 8px 8px 0; font-size:.9rem; line-height:1.55; }
.fa-desc-box p { margin: 0 0 .5em; }
.fa-desc-box p:last-child { margin-bottom: 0; }
.fa-desc-box ul, .fa-desc-box ol { margin: 0 0 .5em 1.4em; padding: 0; }
.fa-desc-box ul.checklist { list-style: none; margin-left: 0; padding-left: 0; }
.fa-desc-box ul.checklist li { position: relative; padding-left: 26px; margin-bottom: 4px; }
.fa-desc-box ul.checklist li::before {
    content: ''; position: absolute; left: 0; top: 4px;
    width: 16px; height: 16px; border: 1.5px solid var(--cl-border); border-radius: 3px; background: #fff;
}
.fa-desc-box ul.checklist li.checked { text-decoration: line-through; color: var(--cl-text-muted); }
.fa-desc-box ul.checklist li.checked::before {
    background: #2d4a43; border-color: #2d4a43;
    content: '\f633'; font-family: 'bootstrap-icons'; color: #fff; font-size: 10px;
    display: flex; align-items: center; justify-content: center;
}

/* Info cards (header grid du modal — style Claude plugin) */
.fa-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 18px; }
.fa-info-card {
    background: #fff; border: 1px solid #E8E4DE; border-radius: 10px;
    padding: 12px 14px; display: flex; align-items: center; gap: 10px;
}
.fa-info-ico {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: #F5F4F0; color: #6B6B6B;
    display: inline-flex; align-items: center; justify-content: center; font-size: .95rem;
}
.fa-info-text { min-width: 0; flex: 1; }
.fa-info-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .4px; color: #9B9B9B; font-weight: 700; margin-bottom: 2px; }
.fa-info-value { font-size: .88rem; font-weight: 600; color: #1A1A1A; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fa-info-sub { font-size: .72rem; color: #6B6B6B; line-height: 1.2; margin-top: 1px; }

.fa-comments { display:flex; flex-direction:column; gap:8px; }
.fa-comment { padding:10px 12px; border-radius:8px; border:1px solid #E8E4DE; background:#fff; font-size:.85rem; }
.fa-comment.role-admin { background:#F7F5F2; border-left:3px solid #2d4a43; }
.fa-comment-head { display:flex; justify-content:space-between; font-size:.75rem; color:#6c757d; margin-bottom:4px; }
.fa-comment-body p { margin: 0 0 .4em; }
.fa-comment-body p:last-child { margin-bottom: 0; }
.fa-comment-body ul, .fa-comment-body ol { margin: 0 0 .4em 1.3em; padding: 0; }
.fa-comment-body ul.checklist { list-style:none; margin-left:0; padding-left:0; }
.fa-comment-body ul.checklist li { position:relative; padding-left:24px; margin-bottom:3px; }
.fa-comment-body ul.checklist li::before { content:''; position:absolute; left:0; top:3px; width:14px; height:14px; border:1.5px solid #E8E4DE; border-radius:3px; background:#fff; }
.fa-comment-body ul.checklist li.checked { text-decoration:line-through; color:#9B9B9B; }
.fa-comment-body ul.checklist li.checked::before { background:#2d4a43; border-color:#2d4a43; content:'\f633'; font-family:'bootstrap-icons'; color:#fff; font-size:9px; display:flex; align-items:center; justify-content:center; }

.fa-statut-pick { display:flex; gap:4px; flex-wrap:wrap; margin-top:6px; }
.fa-statut-opt { font-size:.78rem; padding:6px 12px; border:1.5px solid #E8E4DE; border-radius:999px; cursor:pointer; background:#fff; transition:all .15s; }
.fa-statut-opt:hover { background:#F7F5F2; border-color:#C4A882; }
.fa-statut-opt.active { background:#2d4a43; color:#fff; border-color:#2d4a43; }

.fa-rdv-card { border:1px solid #E8E4DE; border-radius:10px; padding:12px; margin-bottom:8px; background:#fff; }
.fa-rdv-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }

.fa-chip-list { display:flex; flex-wrap:wrap; gap:6px; }
.fa-chip { background:#F7F5F2; border:1px solid #E8E4DE; padding:3px 10px; border-radius:999px; font-size:.78rem; display:inline-flex; align-items:center; gap:6px; }

/* Rich comment editor (contenteditable + toolbar) */
.fa-rich-wrap { border:1px solid #dee2e6; border-radius:8px; overflow:hidden; background:#fff; transition:border-color .15s, box-shadow .15s; }
.fa-rich-wrap:focus-within { border-color:#1A1A1A; box-shadow:0 0 0 3px rgba(25,25,24,.08); }
.fa-rich-toolbar { display:flex; align-items:center; gap:1px; background:#fff; border-bottom:1px solid #E8E4DE; padding:4px 6px; }
.fa-rich-btn { width:28px; height:28px; border:none; background:none; border-radius:5px; color:#6c757d; cursor:pointer; font-size:.85rem; display:inline-flex; align-items:center; justify-content:center; transition:background .1s, color .1s; }
.fa-rich-btn:hover { background:#F7F5F2; color:#1A1A1A; }
.fa-rich-sep { width:1px; height:16px; background:#E8E4DE; margin:0 3px; }
.fa-rich-editor { min-height:70px; padding:10px 12px; font-size:.88rem; line-height:1.55; outline:none; background:#fff; overflow-y:auto; max-height:240px; }
.fa-rich-editor:empty::before { content:attr(data-placeholder); color:#9B9B9B; font-style:italic; pointer-events:none; }
.fa-rich-editor p { margin:0 0 .5em; }
.fa-rich-editor ul, .fa-rich-editor ol { margin:0 0 .5em 1.4em; padding:0; }
.fa-rich-editor ul.checklist { list-style:none; margin-left:0; padding-left:0; }
.fa-rich-editor ul.checklist li { position:relative; padding-left:26px; margin-bottom:4px; }
.fa-rich-editor ul.checklist li::before { content:''; position:absolute; left:0; top:4px; width:16px; height:16px; border:1.5px solid #E8E4DE; border-radius:3px; background:#fff; }
.fa-rich-editor ul.checklist li.checked { text-decoration:line-through; color:#9B9B9B; }
.fa-rich-editor ul.checklist li.checked::before { background:#2d4a43; border-color:#2d4a43; content:'\f633'; font-family:'bootstrap-icons'; color:#fff; font-size:10px; display:flex; align-items:center; justify-content:center; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Amélioration continue</h4>
        <p class="text-muted small mb-0">Suivi des fiches d'amélioration soumises par les collaborateurs</p>
    </div>
</div>

<!-- Stats (standard admin stat-card pattern — cliquables, filtrent par statut) -->
<div class="row g-3 mb-4" id="faStatsRow">
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-filter=""><div class="stat-icon bg-teal"><i class="bi bi-inbox"></i></div><div><div class="stat-value"><?= $faStats['total'] ?></div><div class="stat-label">Total</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-filter="soumise"><div class="stat-icon bg-teal"><i class="bi bi-send"></i></div><div><div class="stat-value"><?= $faStats['soumise'] ?></div><div class="stat-label">Soumises</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-filter="en_revue"><div class="stat-icon bg-orange"><i class="bi bi-eye"></i></div><div><div class="stat-value"><?= $faStats['en_revue'] ?></div><div class="stat-label">En revue</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-filter="en_cours"><div class="stat-icon bg-purple"><i class="bi bi-gear"></i></div><div><div class="stat-value"><?= $faStats['en_cours'] ?></div><div class="stat-label">En cours</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-filter="realisee"><div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div><div><div class="stat-value"><?= $faStats['realisee'] ?></div><div class="stat-label">Réalisées</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="stat-card fa-stat-click" data-stat-crit="haute"><div class="stat-icon bg-red"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-value"><?= $faStats['haute'] ?></div><div class="stat-label">Haute criticité</div></div></div>
    </div>
</div>

<!-- Filters -->
<div class="fa-filters">
    <select class="form-select form-select-sm" id="faFltCategorie" style="max-width:220px"><option value="">Toutes les catégories</option>
        <?php foreach ($faCategorieLabels as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach ?>
    </select>
    <div class="email-tabs" id="faCriticiteTabs" style="margin-top:0; max-width:460px">
        <div class="email-tabs-slider" id="faCriticiteTabsSlider"></div>
        <button class="email-tab active" data-crit="">Toutes</button>
        <button class="email-tab" data-crit="faible">Faible</button>
        <button class="email-tab" data-crit="moyenne">Moyenne</button>
        <button class="email-tab" data-crit="haute">Haute</button>
    </div>
    <input type="hidden" id="faFltStatut" value="">
    <input type="hidden" id="faFltCriticite" value="">
</div>

<!-- Table -->
<div class="fa-adm-table">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th style="width:3%"></th>
                <th style="width:35%">Titre</th>
                <th style="width:15%">Auteur</th>
                <th style="width:12%">Catégorie</th>
                <th style="width:10%">Criticité</th>
                <th style="width:10%">Statut</th>
                <th style="width:10%">Date</th>
                <th style="width:5%"></th>
            </tr>
        </thead>
        <tbody id="faTableBody">
            <tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</td></tr>
        </tbody>
    </table>
</div>

<!-- Detail modal -->
<div class="modal fade" id="faDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div style="flex:1; min-width:0">
                    <h5 class="modal-title" id="faMdlTitle">—</h5>
                    <div class="small text-muted" id="faMdlRef"></div>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body" id="faMdlBody">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="faDeleteBtn"><i class="bi bi-trash"></i> Supprimer</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- RDV modal -->
<div class="modal fade" id="faRdvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Proposer un RDV</h5>
                <button type="button" class="btn btn-sm btn-light d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:.85rem"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Date et heure</label>
                    <input type="datetime-local" class="form-control" id="faRdvDate">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Lieu (optionnel)</label>
                    <input type="text" class="form-control" id="faRdvLieu" placeholder="Bureau RH, Salle 2, visio...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Note à l'auteur (optionnel)</label>
                    <textarea class="form-control" id="faRdvNotes" rows="3" placeholder="Contexte, agenda, objectifs du RDV..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" id="faRdvSubmit"><i class="bi bi-send"></i> Proposer</button>
            </div>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    const statutLabels = <?= json_encode($faStatutLabels) ?>;
    const criLabels = <?= json_encode($faCriticiteLabels) ?>;
    const catLabels = <?= json_encode($faCategorieLabels) ?>;
    let currentFicheId = null;
    let detailModal = null, rdvModal = null;

    let _booted = false;
    function boot() {
        if (_booted) return;
        _booted = true;
        detailModal = new bootstrap.Modal(document.getElementById('faDetailModal'));
        rdvModal = new bootstrap.Modal(document.getElementById('faRdvModal'));

        loadList();

        document.getElementById('faFltCategorie')?.addEventListener('change', loadList);

        // Stat cards → filtre statut (+ cas spécial "haute criticité")
        document.querySelectorAll('[data-stat-filter], [data-stat-crit]').forEach(card => {
            card.addEventListener('click', () => {
                const statut = card.dataset.statFilter;
                const crit = card.dataset.statCrit;
                const stInput = document.getElementById('faFltStatut');
                const crInput = document.getElementById('faFltCriticite');

                if (crit !== undefined) {
                    // Card criticité (Haute) : reset statut, toggle crit
                    stInput.value = '';
                    crInput.value = (crInput.value === crit) ? '' : crit;
                    setCriticiteTab(crInput.value);
                } else {
                    // Card statut : reset crit (sinon Haute reste collé) + toggle statut
                    stInput.value = (stInput.value === statut) ? '' : (statut || '');
                    crInput.value = '';
                    setCriticiteTab('');
                }
                updateStatActive();
                loadList();
            });
        });

        // Criticity slide tabs
        const critOrder = ['', 'faible', 'moyenne', 'haute'];
        document.querySelectorAll('#faCriticiteTabs .email-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const v = btn.dataset.crit;
                document.getElementById('faFltCriticite').value = v;
                setCriticiteTab(v);
                // Si on sélectionne une criticité, désactiver la card "Haute criticité"
                updateStatActive();
                loadList();
            });
        });

        function setCriticiteTab(v) {
            document.querySelectorAll('#faCriticiteTabs .email-tab').forEach(t => t.classList.toggle('active', t.dataset.crit === v));
            const slider = document.getElementById('faCriticiteTabsSlider');
            if (slider) {
                slider.className = 'email-tabs-slider';
                const idx = critOrder.indexOf(v);
                if (idx > 0) slider.classList.add('pos-' + idx);
            }
        }

        function updateStatActive() {
            const st = document.getElementById('faFltStatut').value;
            const cr = document.getElementById('faFltCriticite').value;
            document.querySelectorAll('[data-stat-filter]').forEach(c => {
                c.classList.toggle('active', c.dataset.statFilter === st && cr !== 'haute');
            });
            document.querySelectorAll('[data-stat-crit]').forEach(c => {
                c.classList.toggle('active', c.dataset.statCrit === cr);
            });
            // "Total" card active si aucun filtre
            document.querySelectorAll('[data-stat-filter=""]').forEach(c => {
                c.classList.toggle('active', !st && !cr);
            });
        }

        // Use global topbar search input
        document.getElementById('topbarSearchInput')?.addEventListener('input', debounce(loadList, 300));
        document.getElementById('faRdvSubmit')?.addEventListener('click', submitRdv);
        document.getElementById('faDeleteBtn')?.addEventListener('click', async () => {
            if (!currentFicheId) return;
            const ok = await adminConfirm({
                title: 'Supprimer la fiche',
                text: 'Cette action est <strong>irréversible</strong>. La fiche et tous ses commentaires, pièces jointes et RDV associés seront supprimés.',
                icon: 'bi-trash3',
                type: 'danger',
                okText: 'Supprimer',
            });
            if (!ok) return;
            const r = await adminApiPost('admin_delete_fiche_amelioration', { id: currentFicheId });
            if (r.success) { showToast('Fiche supprimée', 'success'); detailModal.hide(); loadList(); }
            else showToast(r.message || 'Erreur', 'danger');
        });

        updateStatActive();
    }

    function debounce(fn, ms) {
        let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }

    async function loadList() {
        const tb = document.getElementById('faTableBody');
        tb.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
        const r = await adminApiPost('admin_list_fiches_amelioration', {
            statut: document.getElementById('faFltStatut').value,
            categorie: document.getElementById('faFltCategorie').value,
            criticite: document.getElementById('faFltCriticite').value,
            search: (document.getElementById('topbarSearchInput')?.value || '').trim(),
        });
        if (!r.success) { tb.innerHTML = '<tr><td colspan="8" class="text-danger">Erreur</td></tr>'; return; }
        if (!r.fiches.length) { tb.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Aucune fiche</td></tr>'; return; }
        tb.innerHTML = r.fiches.map(renderRow).join('');
        tb.querySelectorAll('[data-fiche]').forEach(tr => {
            tr.addEventListener('click', () => openDetail(tr.dataset.fiche));
        });
    }

    function renderRow(f) {
        const st = statutLabels[f.statut] || { label: f.statut, cls: 'fa-badge-teal' };
        const cr = criLabels[f.criticite] || { label: f.criticite, cls: 'fa-badge-teal' };
        const cat = catLabels[f.categorie] || f.categorie;
        const authorHtml = f.is_anonymous == 1
            ? '<span class="fa-anon"><i class="bi bi-incognito"></i> Anonyme</span>'
            : `<div class="fa-auth-cell">
                <div class="fa-avatar">${esc(((f.auteur_prenom||'')[0]||'')+((f.auteur_nom||'')[0]||'')).toUpperCase()}</div>
                <span>${esc((f.auteur_prenom||'') + ' ' + (f.auteur_nom||''))}</span>
              </div>`;
        const icon = f.visibility === 'public' ? 'bi-globe' : (f.visibility === 'targeted' ? 'bi-bullseye' : 'bi-lock');
        return `
          <tr data-fiche="${esc(f.id)}">
            <td><i class="bi ${icon} text-muted" title="${esc(f.visibility)}"></i></td>
            <td><strong>${esc(f.titre)}</strong>${parseInt(f.nb_comments)>0 ? `<span class="text-muted small ms-2"><i class="bi bi-chat-dots"></i> ${parseInt(f.nb_comments)}</span>` : ''}</td>
            <td>${authorHtml}</td>
            <td>${esc(cat)}</td>
            <td><span class="fa-badge ${cr.cls}">${esc(cr.label)}</span></td>
            <td><span class="fa-badge ${st.cls}">${esc(st.label)}</span></td>
            <td><small>${new Date(f.created_at).toLocaleDateString('fr-CH')}</small></td>
            <td><i class="bi bi-chevron-right text-muted"></i></td>
          </tr>`;
    }

    async function openDetail(id) {
        currentFicheId = id;
        document.getElementById('faMdlTitle').textContent = '—';
        const refEl = document.getElementById('faMdlRef');
        if (refEl) refEl.textContent = '';
        document.getElementById('faMdlBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
        detailModal.show();
        const r = await adminApiPost('admin_get_fiche_amelioration', { id });
        if (!r.success) { document.getElementById('faMdlBody').innerHTML = '<div class="text-danger">Erreur</div>'; return; }
        renderDetail(r);
    }

    function renderDetail(r) {
        const f = r.fiche, auteur = r.auteur;
        const st = statutLabels[f.statut] || { label: f.statut, cls: 'fa-badge-teal' };
        const cr = criLabels[f.criticite] || { label: f.criticite, cls: 'fa-badge-teal' };
        const cat = catLabels[f.categorie] || f.categorie;

        document.getElementById('faMdlTitle').textContent = f.titre;
        document.getElementById('faMdlRef').textContent = f.reference_code ? '#' + f.reference_code : '';

        // Header info cards (style Claude plugin)
        const visInfo = {
            'private':  { label: 'Privée',   icon: 'bi-lock',     sub: 'Admin uniquement' },
            'public':   { label: 'Publique', icon: 'bi-globe',    sub: 'Tous les collègues' },
            'targeted': { label: 'Ciblée',   icon: 'bi-bullseye', sub: 'Personnes désignées' },
        }[f.visibility] || { label: f.visibility, icon: 'bi-eye', sub: '' };

        const authorMain = (f.is_anonymous == 1)
            ? 'Anonyme'
            : (auteur ? esc(auteur.prenom + ' ' + auteur.nom) : '—');
        const authorSub = (f.is_anonymous == 1)
            ? 'Non traçable'
            : (auteur && auteur.email ? esc(auteur.email) : '');

        const dateStr = new Date(f.created_at).toLocaleDateString('fr-CH', { day:'2-digit', month:'short', year:'numeric' });
        const evtDateStr = f.date_evenement ? new Date(f.date_evenement).toLocaleDateString('fr-CH', { day:'2-digit', month:'short', year:'numeric' }) : '';
        const heureStr = f.heure_evenement ? ' · ' + f.heure_evenement.slice(0, 5) : '';

        const infoCardsHtml = `
            <div class="fa-info-grid">
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi ${f.is_anonymous == 1 ? 'bi-incognito' : 'bi-person'}"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Auteur</div>
                        <div class="fa-info-value">${authorMain}</div>
                        ${authorSub ? `<div class="fa-info-sub">${authorSub}</div>` : ''}
                    </div>
                </div>
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-tag"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Catégorie</div>
                        <div class="fa-info-value">${esc(cat)}</div>
                        <div class="fa-info-sub">${esc(f.type_evenement || '').replace(/_/g, ' ')}</div>
                    </div>
                </div>
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi ${visInfo.icon}"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Visibilité</div>
                        <div class="fa-info-value">${esc(visInfo.label)}</div>
                        <div class="fa-info-sub">${esc(visInfo.sub)}</div>
                    </div>
                </div>
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Criticité</div>
                        <div class="fa-info-value"><span class="fa-badge ${cr.cls}">${esc(cr.label)}</span></div>
                    </div>
                </div>
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-flag"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Statut</div>
                        <div class="fa-info-value"><span class="fa-badge ${st.cls}">${esc(st.label)}</span></div>
                    </div>
                </div>
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-calendar-event"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">${evtDateStr ? 'Événement' : 'Créée le'}</div>
                        <div class="fa-info-value">${esc(evtDateStr || dateStr)}${heureStr}</div>
                        ${evtDateStr ? `<div class="fa-info-sub">Fiche créée le ${dateStr}</div>` : ''}
                    </div>
                </div>
                ${f.lieu_precis ? `
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-geo-alt"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Lieu</div>
                        <div class="fa-info-value" title="${esc(f.lieu_precis)}">${esc(f.lieu_precis)}</div>
                    </div>
                </div>` : ''}
                ${f.personnes_concernees_types ? `
                <div class="fa-info-card">
                    <div class="fa-info-ico"><i class="bi bi-people"></i></div>
                    <div class="fa-info-text">
                        <div class="fa-info-label">Personnes concernées</div>
                        <div class="fa-info-value" style="white-space:normal">${esc(f.personnes_concernees_types).replace(/,/g, ', ')}</div>
                    </div>
                </div>` : ''}
            </div>
        `;

        const commentsHtml = (r.comments || []).map(c => `
            <div class="fa-comment role-${esc(c.role||'user')}">
                <div class="fa-comment-head">
                    <strong>${esc((c.prenom||'') + ' ' + (c.nom||''))}${c.role === 'admin' ? ' <span class="text-success small">(Admin)</span>' : ''}</strong>
                    <span>${new Date(c.created_at).toLocaleString('fr-CH')}</span>
                </div>
                <div class="fa-comment-body">${c.content || ''}</div>
            </div>`).join('') || '<div class="text-muted small">Aucun commentaire</div>';

        const concernesHtml = (r.concernes && r.concernes.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-people"></i> Personnes concernées</div>
                <div class="fa-chip-list">
                    ${r.concernes.map(u => `<span class="fa-chip"><div class="fa-avatar" style="width:22px;height:22px;font-size:.62rem">${esc(((u.prenom||'')[0]||'')+((u.nom||'')[0]||'')).toUpperCase()}</div>${esc(u.prenom + ' ' + u.nom)}</span>`).join('')}
                </div>
            </div>` : '';

        const rdvsHtml = (r.rdvs && r.rdvs.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-calendar-event"></i> Rendez-vous</div>
                ${r.rdvs.map(rdv => `
                    <div class="fa-rdv-card">
                        <div class="fa-rdv-head">
                            <strong><i class="bi bi-calendar-event"></i> ${new Date(rdv.date_proposed).toLocaleString('fr-CH', { dateStyle:'medium', timeStyle:'short' })}</strong>
                            <span class="fa-badge fa-badge-${rdv.statut === 'acceptee' ? 'green' : rdv.statut === 'refusee' ? 'red' : 'teal'}">${esc(rdv.statut)}</span>
                        </div>
                        ${rdv.lieu ? `<div class="small"><i class="bi bi-geo-alt"></i> ${esc(rdv.lieu)}</div>` : ''}
                        ${rdv.admin_notes ? `<div class="small text-muted mt-1">${esc(rdv.admin_notes)}</div>` : ''}
                        ${rdv.user_response ? `<div class="small mt-2"><em>Réponse auteur :</em> ${esc(rdv.user_response)}</div>` : ''}
                    </div>`).join('')}
            </div>` : '';

        const attachmentsHtml = (r.attachments && r.attachments.length) ? `
            <div class="fa-section"><div class="fa-section-title"><i class="bi bi-paperclip"></i> Pièces jointes</div>
                ${r.attachments.map(a => `<a href="/newspocspace/admin/api.php?action=admin_download_fiche_amelioration_attachment&id=${esc(a.id)}" target="_blank" class="d-block small">${esc(a.original_name)}</a>`).join('')}
            </div>` : '';

        const canPropose = (f.is_anonymous != 1);

        document.getElementById('faMdlBody').innerHTML = `
            ${infoCardsHtml}

            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-card-text"></i> Description</div>
                <div class="fa-desc-box">${f.description || '<em class="text-muted">—</em>'}</div>
            </div>
            ${f.mesures_immediates ? `<div class="fa-section"><div class="fa-section-title"><i class="bi bi-shield-check"></i> Mesures immédiates prises</div><div class="fa-desc-box">${f.mesures_immediates}</div></div>` : ''}
            ${f.suggestion ? `<div class="fa-section"><div class="fa-section-title"><i class="bi bi-lightbulb"></i> Suggestion</div><div class="fa-desc-box">${f.suggestion}</div></div>` : ''}
            ${concernesHtml}
            ${rdvsHtml}
            ${attachmentsHtml}

            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-flag"></i> Statut</div>
                <div class="fa-statut-pick" id="faStatutPick">
                    ${Object.entries(statutLabels).map(([k, v]) => `<button class="fa-statut-opt ${k === f.statut ? 'active' : ''}" data-statut="${esc(k)}">${esc(v.label)}</button>`).join('')}
                </div>
            </div>

            <div class="fa-section">
                <div class="fa-section-title"><i class="bi bi-chat-dots"></i> Commentaires</div>
                <div class="fa-comments">${commentsHtml}</div>
                <div class="mt-2">
                    <div class="fa-rich-wrap">
                        <div class="fa-rich-toolbar" data-for="faAdmComment">
                            <button type="button" class="fa-rich-btn" data-fmt="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="fa-rich-btn" data-fmt="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="fa-rich-btn" data-fmt="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
                            <button type="button" class="fa-rich-btn" data-fmt="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
                            <span class="fa-rich-sep"></span>
                            <button type="button" class="fa-rich-btn" data-fmt="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="fa-rich-btn" data-fmt="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="fa-rich-btn" data-fmt="checklist" title="Checklist"><i class="bi bi-check2-square"></i></button>
                        </div>
                        <div class="fa-rich-editor" id="faAdmComment" contenteditable="true" data-placeholder="Réponse admin…"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                        ${canPropose ? '<button class="btn btn-outline-primary btn-sm" id="faProposeRdv"><i class="bi bi-calendar-plus"></i> Proposer un RDV</button>' : '<span class="small text-muted"><i class="bi bi-incognito"></i> RDV impossible (auteur anonyme)</span>'}
                        <button class="btn btn-success btn-sm" id="faAdmCommentSubmit"><i class="bi bi-send"></i> Envoyer</button>
                    </div>
                </div>
            </div>
        `;

        document.querySelectorAll('#faStatutPick .fa-statut-opt').forEach(b => {
            b.addEventListener('click', async () => {
                const newSt = b.dataset.statut;
                const ok = await adminConfirm({
                    title: 'Changer le statut',
                    text: 'Un email sera envoyé à l\'auteur (si non anonyme). Confirmer ?',
                    icon: 'bi-flag',
                    type: 'warning',
                    okText: 'Confirmer',
                });
                if (!ok) return;
                const rr = await adminApiPost('admin_update_fiche_amelioration_statut', { id: currentFicheId, statut: newSt });
                if (rr.success) { showToast('Statut mis à jour', 'success'); openDetail(currentFicheId); loadList(); }
                else showToast(rr.message || 'Erreur', 'danger');
            });
        });

        // Rich editor toolbar
        document.querySelectorAll('.modal-body .fa-rich-toolbar').forEach(tb => {
            const targetId = tb.dataset.for;
            const ed = document.getElementById(targetId);
            if (!ed) return;
            tb.querySelectorAll('.fa-rich-btn').forEach(btn => {
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => {
                    ed.focus();
                    const cmd = btn.dataset.fmt;
                    if (cmd === 'checklist') {
                        document.execCommand('insertHTML', false, '<ul class="checklist"><li>Élément à faire</li></ul>');
                    } else {
                        document.execCommand(cmd, false, null);
                    }
                });
            });
            ed.addEventListener('click', e => {
                const li = e.target.closest('ul.checklist > li');
                if (!li) return;
                const rect = li.getBoundingClientRect();
                if (e.clientX - rect.left < 26) li.classList.toggle('checked');
            });
        });

        document.getElementById('faAdmCommentSubmit')?.addEventListener('click', async () => {
            const ed = document.getElementById('faAdmComment');
            const textOnly = (ed?.textContent || '').trim();
            const html = (ed?.innerHTML || '').trim();
            if (!textOnly) { showToast('Commentaire vide', 'warning'); return; }
            const rr = await adminApiPost('admin_add_fiche_amelioration_comment', { fiche_id: currentFicheId, content: html });
            if (rr.success) { showToast('Commentaire ajouté', 'success'); openDetail(currentFicheId); }
            else showToast(rr.message || 'Erreur', 'danger');
        });

        document.getElementById('faProposeRdv')?.addEventListener('click', () => {
            document.getElementById('faRdvDate').value = '';
            document.getElementById('faRdvLieu').value = '';
            document.getElementById('faRdvNotes').value = '';
            rdvModal.show();
        });
    }

    async function submitRdv() {
        const date = document.getElementById('faRdvDate').value;
        const lieu = document.getElementById('faRdvLieu').value.trim();
        const notes = document.getElementById('faRdvNotes').value.trim();
        if (!date) { showToast('Date requise', 'warning'); return; }
        const btn = document.getElementById('faRdvSubmit');
        btn.disabled = true;
        const r = await adminApiPost('admin_propose_fiche_amelioration_rdv', {
            fiche_id: currentFicheId, date_proposed: date, lieu, admin_notes: notes,
        });
        btn.disabled = false;
        if (r.success) { showToast('RDV proposé — email envoyé à l\'auteur', 'success'); rdvModal.hide(); openDetail(currentFicheId); }
        else showToast(r.message || 'Erreur', 'danger');
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function showToast(msg, type) { if (typeof toast === 'function') toast(msg, type); else alert(msg); }

    boot();
})();
</script>
