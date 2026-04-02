<?php
$residents = Db::fetchAll("SELECT id, nom, prenom, chambre, etage FROM residents WHERE is_active = 1 ORDER BY chambre, nom");
$produits = Db::fetchAll("SELECT * FROM hygiene_produits WHERE is_active = 1 ORDER BY ordre, nom");
?>
<style>
.hyg-tabs { display: flex; gap: 4px; margin-bottom: 20px; background: var(--cl-bg, #F7F5F2); border-radius: 12px; padding: 4px; width: fit-content; }
.hyg-tab { padding: 8px 18px; border-radius: 10px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--cl-text-muted); transition: all .15s; }
.hyg-tab.active { background: var(--cl-surface, #fff); color: var(--cl-text); box-shadow: 0 1px 3px rgba(0,0,0,.06); }

.hyg-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); }
.hyg-table { width: 100%; border-collapse: collapse; background: var(--cl-surface, #fff); }
.hyg-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border-light); text-align: left; background: var(--cl-bg, #F7F5F2); }
.hyg-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.hyg-table tr:last-child td { border-bottom: none; }
.hyg-table tbody tr:hover td { background: var(--cl-bg, #FAFAF7); }

.hyg-badge { font-size: .72rem; padding: 3px 10px; border-radius: 8px; font-weight: 600; }
.hyg-badge-commandé { background: #D4C4A8; color: #6B5B3E; }
.hyg-badge-préparé  { background: #B8C9D4; color: #3B4F6B; }
.hyg-badge-distribué { background: #bcd2cb; color: #2d4a43; }
.hyg-badge-urgent { background: #E2B8AE; color: #7B3B2C; font-size: .65rem; }

.hyg-prod-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-right: 6px; vertical-align: middle; }
.hyg-prod-card { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); margin-bottom: 8px; }
.hyg-prod-card:hover { border-color: var(--cl-border-hover); }
.hyg-prod-info { flex: 1; }
.hyg-prod-name { font-weight: 600; font-size: .92rem; }
.hyg-prod-meta { font-size: .75rem; color: var(--cl-text-muted); }
.hyg-row-btn { background: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 6px; color: var(--cl-text-muted); font-size: .85rem; transition: all .12s; display: inline-flex; align-items: center; justify-content: center; }
.hyg-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }

/* Quick order card */
.hyg-quick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; margin-top: 12px; }
.hyg-quick-item {
    padding: 12px; border-radius: 12px; border: 1.5px solid var(--cl-border-light, #F0EDE8);
    background: var(--cl-surface, #fff); cursor: pointer; text-align: center; transition: all .15s;
}
.hyg-quick-item:hover { border-color: #bcd2cb; background: rgba(188,210,203,.06); transform: translateY(-1px); }
.hyg-quick-item.selected { border-color: #2d4a43; background: rgba(45,74,67,.06); box-shadow: 0 0 0 1px #2d4a43; }
.hyg-quick-item i { font-size: 1.5rem; display: block; margin-bottom: 4px; }
.hyg-quick-item span { font-size: .78rem; font-weight: 600; }

.hyg-cat-labels { savon:'Savon', rasoir:'Rasoir', parfum:'Parfum', gel_douche:'Gel douche', apres_rasage:'Après-rasage', dentifrice:'Dentifrice', shampooing:'Shampooing', creme:'Crème', deodorant:'Déodorant', autre:'Autre' }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-droplet"></i> Produits Hygiène</h4>
  <div class="d-flex align-items-center gap-2">
    <span class="small text-muted">Jour</span>
    <input type="date" class="form-control form-control-sm" id="hygJour" style="max-width:160px">
  </div>
</div>

<div class="row g-3 mb-4" id="hygStats"></div>

<div class="hyg-tabs">
  <button class="hyg-tab active" data-hyg-view="commande"><i class="bi bi-cart-plus"></i> Commander</button>
  <button class="hyg-tab" data-hyg-view="preparation"><i class="bi bi-box-seam"></i> Préparation</button>
  <button class="hyg-tab" data-hyg-view="distribution"><i class="bi bi-truck"></i> Distribution</button>
  <button class="hyg-tab" data-hyg-view="produits"><i class="bi bi-grid"></i> Catalogue</button>
</div>

<div id="hygContent"></div>

<!-- Produit modal -->
<div class="modal fade" id="hygProdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title" id="hygProdModalTitle">Nouveau produit</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="hygProdId">
      <div class="mb-2"><label class="form-label small fw-bold">Nom *</label><input class="form-control form-control-sm" id="hygProdNom"></div>
      <div class="row mb-2">
        <div class="col"><label class="form-label small fw-bold">Catégorie</label>
          <select class="form-select form-select-sm" id="hygProdCat">
            <option value="savon">Savon</option><option value="rasoir">Rasoir</option><option value="parfum">Parfum</option>
            <option value="gel_douche">Gel douche</option><option value="apres_rasage">Après-rasage</option>
            <option value="dentifrice">Dentifrice</option><option value="shampooing">Shampooing</option>
            <option value="creme">Crème</option><option value="deodorant">Déodorant</option><option value="autre">Autre</option>
          </select>
        </div>
        <div class="col"><label class="form-label small fw-bold">Marque</label><input class="form-control form-control-sm" id="hygProdMarque"></div>
      </div>
      <div class="mb-2"><label class="form-label small fw-bold">Couleur</label><input type="color" class="form-control form-control-sm form-control-color" id="hygProdColor" value="#3B4F6B" style="width:40px"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary btn-sm" id="hygProdSave">Enregistrer</button></div>
  </div></div>
</div>

<script<?= nonce() ?>>
(function(){
    const CAT_ICONS = { savon:'droplet', rasoir:'scissors', parfum:'flower1', gel_douche:'droplet-half', apres_rasage:'wind', dentifrice:'brush', shampooing:'droplet-fill', creme:'hand-index', deodorant:'moisture', autre:'box' };
    const CAT_LABELS = { savon:'Savon', rasoir:'Rasoir', parfum:'Parfum', gel_douche:'Gel douche', apres_rasage:'Après-rasage', dentifrice:'Dentifrice', shampooing:'Shampooing', creme:'Crème', deodorant:'Déodorant', autre:'Autre' };

    const ssrResidents = <?= json_encode(array_values($residents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let produits = <?= json_encode(array_values($produits), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let commandes = [];
    let currentView = 'commande';

    const jourInput = document.getElementById('hygJour');
    jourInput.value = new Date().toISOString().split('T')[0];

    const resOpts = ssrResidents.map(r => ({ value: r.id, label: r.nom + ' ' + r.prenom + ' — Ch.' + (r.chambre||'?') }));

    document.querySelectorAll('.hyg-tab').forEach(t => t.addEventListener('click', () => {
        currentView = t.dataset.hygView;
        document.querySelectorAll('.hyg-tab').forEach(x => x.classList.toggle('active', x.dataset.hygView === currentView));
        load();
    }));
    jourInput.addEventListener('change', load);

    async function load() {
        if (currentView === 'produits') { await loadProduits(); return; }
        await loadCommandes();
    }

    async function loadCommandes() {
        const r = await adminApiPost('admin_get_hygiene_commandes', { jour: jourInput.value });
        if (!r.success) return;
        commandes = r.commandes || [];
        renderStats(r.stats);
        if (currentView === 'commande') renderCommande();
        else if (currentView === 'preparation') renderPreparation();
        else renderDistribution();
    }

    function renderStats(s) {
        if (!s) s = {};
        const data = [
            { label: 'Commandés', val: s.commandes||0, bg: '#D4C4A8', color: '#6B5B3E', icon: 'cart-plus' },
            { label: 'Préparés', val: s.prepares||0, bg: '#B8C9D4', color: '#3B4F6B', icon: 'box-seam' },
            { label: 'Distribués', val: s.distribues||0, bg: '#bcd2cb', color: '#2d4a43', icon: 'check-all' },
            { label: 'Résidents', val: s.residents||0, bg: '#D0C4D8', color: '#5B4B6B', icon: 'people' },
        ];
        document.getElementById('hygStats').innerHTML = data.map(d =>
            '<div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:'+d.bg+';color:'+d.color+'"><i class="bi bi-'+d.icon+'"></i></div><div><div class="stat-value">'+d.val+'</div><div class="stat-label">'+d.label+'</div></div></div></div>'
        ).join('');
    }

    // ── Commander (aide after toilette) ──
    function renderCommande() {
        const el = document.getElementById('hygContent');
        let h = '<p class="small text-muted mb-3">Sélectionnez un résident puis les produits manquants après la toilette.</p>';
        h += '<div class="mb-3"><div class="zs-select" id="hygCmdResident" data-placeholder="Choisir un résident..."></div></div>';
        h += '<div id="hygCmdForm" style="display:none">';
        h += '<label class="form-label small fw-bold">Produits à commander</label>';
        h += '<div class="hyg-quick-grid">';
        produits.forEach(p => {
            const icon = CAT_ICONS[p.categorie] || 'box';
            h += '<div class="hyg-quick-item" data-prod="' + p.id + '" data-nom="' + escapeHtml(p.nom) + '">'
                + '<i class="bi bi-' + icon + '" style="color:' + (p.couleur||'#3B4F6B') + '"></i>'
                + '<span>' + escapeHtml(p.nom) + '</span></div>';
        });
        h += '</div>';
        h += '<div class="mt-3 d-flex gap-2 align-items-end">';
        h += '<div><label class="form-label small fw-bold">Notes</label><input class="form-control form-control-sm" id="hygCmdNotes" placeholder="Précisions..."></div>';
        h += '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="hygCmdUrgent"><label class="form-check-label small" for="hygCmdUrgent">Urgent</label></div>';
        h += '<button class="btn btn-primary btn-sm" id="hygCmdSave" disabled><i class="bi bi-check-lg"></i> Commander</button>';
        h += '</div></div>';
        el.innerHTML = h;

        zerdaSelect.init(document.getElementById('hygCmdResident'), resOpts, { search: true });
        document.getElementById('hygCmdResident').addEventListener('change', () => {
            const rid = zerdaSelect.getValue('#hygCmdResident');
            document.getElementById('hygCmdForm').style.display = rid ? '' : 'none';
        });

        let selected = new Set();
        el.querySelectorAll('.hyg-quick-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('selected');
                if (item.classList.contains('selected')) selected.add(item.dataset.prod);
                else selected.delete(item.dataset.prod);
                document.getElementById('hygCmdSave').disabled = selected.size === 0;
            });
        });

        document.getElementById('hygCmdSave')?.addEventListener('click', async () => {
            const rid = zerdaSelect.getValue('#hygCmdResident');
            if (!rid || !selected.size) return;
            const notes = document.getElementById('hygCmdNotes').value;
            const urgent = document.getElementById('hygCmdUrgent').checked ? 1 : 0;
            let ok = 0;
            for (const prodId of selected) {
                const r = await adminApiPost('admin_create_hygiene_commande', { resident_id: rid, produit_id: prodId, notes, urgence: urgent, jour: jourInput.value });
                if (r.success) ok++;
            }
            showToast(ok + ' produit(s) commandé(s)', 'success');
            selected.clear();
            el.querySelectorAll('.hyg-quick-item').forEach(i => i.classList.remove('selected'));
            document.getElementById('hygCmdSave').disabled = true;
            document.getElementById('hygCmdNotes').value = '';
            loadCommandes();
        });
    }

    // ── Préparation (hôtellerie) ──
    function renderPreparation() {
        const el = document.getElementById('hygContent');
        const pending = commandes.filter(c => c.statut === 'commandé');
        if (!pending.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucune commande en attente.</p>'; return; }

        // Group by etage
        const grouped = {};
        pending.forEach(c => {
            const key = c.etage || 'Autre';
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(c);
        });

        let h = '<p class="small text-muted mb-3">Préparez les produits par étage et validez.</p>';
        Object.entries(grouped).sort().forEach(([etage, items]) => {
            h += '<h6 class="fw-bold mt-3 mb-2"><i class="bi bi-building"></i> Étage ' + escapeHtml(etage) + '</h6>';
            h += '<div class="hyg-table-wrap"><table class="hyg-table"><thead><tr><th>Résident</th><th>Ch.</th><th>Produit</th><th>Qté</th><th>Notes</th><th>Par</th><th></th></tr></thead><tbody>';
            items.forEach(c => {
                h += '<tr><td><strong>' + escapeHtml(c.resident_nom + ' ' + c.resident_prenom) + '</strong></td>'
                    + '<td>' + escapeHtml(c.chambre||'') + '</td>'
                    + '<td><span class="hyg-prod-dot" style="background:' + (c.couleur||'#3B4F6B') + '"></span>' + escapeHtml(c.produit_nom) + '</td>'
                    + '<td>' + c.quantite + (c.urgence==1 ? ' <span class="hyg-badge hyg-badge-urgent">URGENT</span>' : '') + '</td>'
                    + '<td class="small text-muted">' + escapeHtml(c.notes||'—') + '</td>'
                    + '<td class="small">' + escapeHtml((c.cmd_prenom||'')+' '+(c.cmd_nom||'').charAt(0)+'.') + '</td>'
                    + '<td><input type="checkbox" class="form-check-input hyg-prep-check" value="' + c.id + '" checked></td></tr>';
            });
            h += '</tbody></table></div>';
        });
        h += '<button class="btn btn-primary btn-sm mt-3" id="hygPrepBtn"><i class="bi bi-check-all"></i> Valider la préparation</button>';
        el.innerHTML = h;

        document.getElementById('hygPrepBtn')?.addEventListener('click', async () => {
            const ids = [...document.querySelectorAll('.hyg-prep-check:checked')].map(c => c.value);
            if (!ids.length) return;
            const r = await adminApiPost('admin_prepare_hygiene_commandes', { ids });
            if (r.success) { showToast('Préparation validée', 'success'); loadCommandes(); }
        });
    }

    // ── Distribution (aide récupère) ──
    function renderDistribution() {
        const el = document.getElementById('hygContent');
        const ready = commandes.filter(c => c.statut === 'préparé');
        if (!ready.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucune distribution en attente.</p>'; return; }

        const grouped = {};
        ready.forEach(c => { const key = c.etage || 'Autre'; if (!grouped[key]) grouped[key] = []; grouped[key].push(c); });

        let h = '<p class="small text-muted mb-3">Récupérez les produits et distribuez-les en chambre.</p>';
        Object.entries(grouped).sort().forEach(([etage, items]) => {
            h += '<h6 class="fw-bold mt-3 mb-2"><i class="bi bi-building"></i> Étage ' + escapeHtml(etage) + '</h6>';
            h += '<div class="hyg-table-wrap"><table class="hyg-table"><thead><tr><th>Résident</th><th>Ch.</th><th>Produit</th><th>Qté</th><th>Préparé par</th><th></th></tr></thead><tbody>';
            items.forEach(c => {
                h += '<tr><td><strong>' + escapeHtml(c.resident_nom + ' ' + c.resident_prenom) + '</strong></td>'
                    + '<td>' + escapeHtml(c.chambre||'') + '</td>'
                    + '<td><span class="hyg-prod-dot" style="background:' + (c.couleur||'#3B4F6B') + '"></span>' + escapeHtml(c.produit_nom) + '</td>'
                    + '<td>' + c.quantite + '</td>'
                    + '<td class="small">' + escapeHtml((c.prep_prenom||'')+' '+(c.prep_nom||'')) + '</td>'
                    + '<td><input type="checkbox" class="form-check-input hyg-dist-check" value="' + c.id + '" checked></td></tr>';
            });
            h += '</tbody></table></div>';
        });
        h += '<button class="btn btn-sm mt-3" style="background:#bcd2cb;color:#2d4a43" id="hygDistBtn"><i class="bi bi-truck"></i> Confirmer distribution</button>';
        el.innerHTML = h;

        document.getElementById('hygDistBtn')?.addEventListener('click', async () => {
            const ids = [...document.querySelectorAll('.hyg-dist-check:checked')].map(c => c.value);
            if (!ids.length) return;
            const r = await adminApiPost('admin_deliver_hygiene_commandes', { ids });
            if (r.success) { showToast('Distribution confirmée', 'success'); loadCommandes(); }
        });
    }

    // ── Catalogue ──
    async function loadProduits() {
        const r = await adminApiPost('admin_get_hygiene_produits');
        if (r.success) produits = r.produits || [];
        const el = document.getElementById('hygContent');
        let h = '<div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold mb-0">Catalogue produits hygiène</h6><button class="btn btn-primary btn-sm" id="hygNewProdBtn"><i class="bi bi-plus-lg"></i> Nouveau</button></div>';
        if (!produits.length) h += '<p class="text-muted text-center py-4">Aucun produit</p>';
        else produits.forEach(p => {
            h += '<div class="hyg-prod-card"><span class="hyg-prod-dot" style="background:' + (p.couleur||'#3B4F6B') + ';width:14px;height:14px"></span>'
                + '<div class="hyg-prod-info"><div class="hyg-prod-name">' + escapeHtml(p.nom) + '</div>'
                + '<div class="hyg-prod-meta">' + (CAT_LABELS[p.categorie]||'') + (p.marque ? ' · ' + escapeHtml(p.marque) : '') + '</div></div>'
                + '<button class="hyg-row-btn" data-edit-prod="' + p.id + '"><i class="bi bi-pencil"></i></button>'
                + '<button class="hyg-row-btn" data-del-prod="' + p.id + '" style="color:#7B3B2C"><i class="bi bi-trash3"></i></button></div>';
        });
        el.innerHTML = h;

        document.getElementById('hygNewProdBtn')?.addEventListener('click', () => openProdModal());
        el.querySelectorAll('[data-edit-prod]').forEach(b => b.addEventListener('click', () => { const p = produits.find(x => x.id === b.dataset.editProd); if (p) openProdModal(p); }));
        el.querySelectorAll('[data-del-prod]').forEach(b => b.addEventListener('click', async () => {
            if (!await adminConfirm({title:'Supprimer',text:'Supprimer ce produit ?',type:'danger',okText:'Supprimer'})) return;
            await adminApiPost('admin_delete_hygiene_produit',{id:b.dataset.delProd});
            showToast('Supprimé','success'); loadProduits();
        }));
    }

    function openProdModal(p) {
        document.getElementById('hygProdId').value = p?.id||'';
        document.getElementById('hygProdNom').value = p?.nom||'';
        document.getElementById('hygProdCat').value = p?.categorie||'autre';
        document.getElementById('hygProdMarque').value = p?.marque||'';
        document.getElementById('hygProdColor').value = p?.couleur||'#3B4F6B';
        document.getElementById('hygProdModalTitle').textContent = p ? 'Modifier' : 'Nouveau produit';
        new bootstrap.Modal(document.getElementById('hygProdModal')).show();
    }

    document.getElementById('hygProdSave')?.addEventListener('click', async () => {
        const r = await adminApiPost('admin_save_hygiene_produit', {
            id: document.getElementById('hygProdId').value, nom: document.getElementById('hygProdNom').value,
            categorie: document.getElementById('hygProdCat').value, marque: document.getElementById('hygProdMarque').value,
            couleur: document.getElementById('hygProdColor').value
        });
        if (r.success) { showToast(r.message,'success'); bootstrap.Modal.getInstance(document.getElementById('hygProdModal'))?.hide(); loadProduits(); }
        else showToast(r.message||'Erreur','error');
    });

    load();
})();
</script>
