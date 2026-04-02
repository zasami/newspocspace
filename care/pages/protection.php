<?php
$residents = Db::fetchAll(
    "SELECT r.id, r.nom, r.prenom, r.chambre,
            (SELECT COUNT(*) FROM protection_attributions WHERE resident_id = r.id) AS nb_produits
     FROM residents r WHERE r.is_active = 1 ORDER BY r.chambre, r.nom"
);
$produits = Db::fetchAll("SELECT * FROM protection_produits WHERE is_active = 1 ORDER BY ordre, nom");
$jourComptage = Db::getOne("SELECT config_value FROM ems_config WHERE config_key='protection_jour_comptage'") ?: 'mardi';
?>
<style>
.prot-tabs { display: flex; gap: 4px; margin-bottom: 20px; background: var(--cl-bg, #F7F5F2); border-radius: 12px; padding: 4px; width: fit-content; }
.prot-tab { padding: 8px 18px; border-radius: 10px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--cl-text-muted); transition: all .15s; }
.prot-tab.active { background: var(--cl-surface, #fff); color: var(--cl-text); box-shadow: 0 1px 3px rgba(0,0,0,.06); }

.prot-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); }
.prot-table { width: 100%; border-collapse: collapse; background: var(--cl-surface, #fff); }
.prot-table th { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); padding: 10px 14px; border-bottom: 1.5px solid var(--cl-border-light); text-align: left; background: var(--cl-bg, #F7F5F2); }
.prot-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.prot-table tr:last-child td { border-bottom: none; }
.prot-table tbody tr:hover td { background: var(--cl-bg, #FAFAF7); }

.prot-badge { font-size: .72rem; padding: 3px 10px; border-radius: 8px; font-weight: 600; }
.prot-badge-compté  { background: #D4C4A8; color: #6B5B3E; }
.prot-badge-validé  { background: #B8C9D4; color: #3B4F6B; }
.prot-badge-livré   { background: #bcd2cb; color: #2d4a43; }

.prot-prod-dot { width: 12px; height: 12px; border-radius: 4px; display: inline-block; margin-right: 6px; vertical-align: middle; }
.prot-qte-input { width: 70px; text-align: center; }
.prot-deficit { color: #7B3B2C; font-weight: 700; }
.prot-ok { color: #2d4a43; }

.prot-prod-card { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); margin-bottom: 8px; }
.prot-prod-card:hover { border-color: var(--cl-border-hover); }
.prot-prod-info { flex: 1; }
.prot-prod-name { font-weight: 600; font-size: .92rem; }
.prot-prod-meta { font-size: .75rem; color: var(--cl-text-muted); }

.prot-compt-card { background: var(--cl-surface, #fff); border: 1.5px solid var(--cl-border-light); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
.prot-compt-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.prot-compt-resident { font-weight: 700; font-size: .95rem; }
.prot-compt-chambre { font-size: .78rem; color: var(--cl-text-muted); background: var(--cl-bg); padding: 2px 10px; border-radius: 8px; }
.prot-compt-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.prot-compt-row:last-child { border: none; }

.prot-row-btn { background: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 6px; color: var(--cl-text-muted); font-size: .85rem; transition: all .12s; display: inline-flex; align-items: center; justify-content: center; }
.prot-row-btn:hover { background: var(--cl-bg); color: var(--cl-text); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-shield-check"></i> Suivi Protection</h4>
  <div class="d-flex align-items-center gap-2">
    <span class="small text-muted">Semaine du</span>
    <input type="date" class="form-control form-control-sm" id="protSemaine" style="max-width:160px">
    <span class="small text-muted">Jour : <strong><?= h(ucfirst($jourComptage)) ?></strong></span>
  </div>
</div>

<div class="row g-3 mb-4" id="protStats"></div>

<div class="prot-tabs">
  <button class="prot-tab active" data-prot-view="comptage"><i class="bi bi-clipboard-check"></i> Comptage</button>
  <button class="prot-tab" data-prot-view="validation"><i class="bi bi-check-circle"></i> Validation</button>
  <button class="prot-tab" data-prot-view="livraison"><i class="bi bi-truck"></i> Livraison</button>
  <button class="prot-tab" data-prot-view="produits"><i class="bi bi-box-seam"></i> Produits</button>
  <button class="prot-tab" data-prot-view="attributions"><i class="bi bi-person-gear"></i> Attributions</button>
</div>

<div id="protContent"></div>

<div class="modal fade" id="protProdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title" id="protProdModalTitle">Nouveau produit</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="protProdId">
      <div class="mb-2"><label class="form-label small fw-bold">Nom *</label><input class="form-control form-control-sm" id="protProdNom" placeholder="Ex: Maxi L"></div>
      <div class="row mb-2"><div class="col"><label class="form-label small fw-bold">Taille</label><input class="form-control form-control-sm" id="protProdTaille" placeholder="L, XL..."></div><div class="col"><label class="form-label small fw-bold">Marque</label><input class="form-control form-control-sm" id="protProdMarque" placeholder="Tena..."></div></div>
      <div class="row mb-2"><div class="col"><label class="form-label small fw-bold">Référence</label><input class="form-control form-control-sm" id="protProdRef"></div><div class="col-auto"><label class="form-label small fw-bold">Couleur</label><input type="color" class="form-control form-control-sm form-control-color" id="protProdColor" value="#2d4a43" style="width:40px"></div></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary btn-sm" id="protProdSave">Enregistrer</button></div>
  </div></div>
</div>

<div class="modal fade" id="protAttribModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title">Attribution de protection</h6><button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label small fw-bold">Résident *</label><div class="zs-select" id="protAttribResident" data-placeholder="Choisir un résident..."></div></div>
      <div class="mb-3"><label class="form-label small fw-bold">Produit *</label><div class="zs-select" id="protAttribProduit" data-placeholder="Choisir un produit..."></div></div>
      <div class="mb-3"><label class="form-label small fw-bold">Quantité hebdomadaire</label><input type="number" class="form-control form-control-sm" id="protAttribQte" min="0" value="0" style="max-width:120px"></div>
      <div class="mb-3"><label class="form-label small fw-bold">Notes</label><input class="form-control form-control-sm" id="protAttribNotes" placeholder="Ex: usage nuit seulement..."></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary btn-sm" id="protAttribSave">Enregistrer</button></div>
  </div></div>
</div>

<script<?= nonce() ?>>
(function(){
    const ssrResidents = <?= json_encode(array_values($residents), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const ssrProduits = <?= json_encode(array_values($produits), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let currentView = 'comptage';
    let comptages = [], produits = ssrProduits, attributions = [];

    const semaineInput = document.getElementById('protSemaine');
    const now = new Date(); const mon = new Date(now); mon.setDate(mon.getDate() - ((mon.getDay() + 6) % 7));
    semaineInput.value = mon.toISOString().split('T')[0];

    const resOpts = ssrResidents.map(r => ({ value: r.id, label: r.nom + ' ' + r.prenom + ' — Ch.' + (r.chambre||'?') }));
    zerdaSelect.init(document.getElementById('protAttribResident'), resOpts, { search: true });
    function initProdSelect() {
        const opts = produits.map(p => ({ value: p.id, label: p.nom + (p.taille ? ' (' + p.taille + ')' : '') }));
        zerdaSelect.destroy(document.getElementById('protAttribProduit'));
        zerdaSelect.init(document.getElementById('protAttribProduit'), opts, { search: true });
    }
    initProdSelect();

    document.querySelectorAll('.prot-tab').forEach(t => t.addEventListener('click', () => {
        currentView = t.dataset.protView;
        document.querySelectorAll('.prot-tab').forEach(x => x.classList.toggle('active', x.dataset.protView === currentView));
        load();
    }));
    semaineInput.addEventListener('change', load);

    async function load() {
        if (currentView === 'produits') { await loadProduits(); return; }
        if (currentView === 'attributions') { await loadAttributions(); return; }
        await loadComptages();
    }

    async function loadComptages() {
        const r = await adminApiPost('admin_get_protection_comptages', { semaine: semaineInput.value });
        if (!r.success) return;
        comptages = r.comptages || [];
        renderStats(r.stats);
        if (currentView === 'comptage') renderComptage();
        else if (currentView === 'validation') renderValidation();
        else renderLivraison();
    }

    function renderStats(s) {
        if (!s) s = {};
        const data = [
            { label: 'Comptés', val: s.comptes||0, bg: '#D4C4A8', color: '#6B5B3E', icon: 'clipboard-check' },
            { label: 'Validés', val: s.valides||0, bg: '#B8C9D4', color: '#3B4F6B', icon: 'check-circle' },
            { label: 'Livrés', val: s.livres||0, bg: '#bcd2cb', color: '#2d4a43', icon: 'truck' },
            { label: 'Résidents', val: s.residents||0, bg: '#D0C4D8', color: '#5B4B6B', icon: 'people' },
        ];
        document.getElementById('protStats').innerHTML = data.map(d =>
            '<div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:'+d.bg+';color:'+d.color+'"><i class="bi bi-'+d.icon+'"></i></div><div><div class="stat-value">'+d.val+'</div><div class="stat-label">'+d.label+'</div></div></div></div>'
        ).join('');
    }

    function renderComptage() {
        const el = document.getElementById('protContent');
        let h = '<p class="small text-muted mb-3">Sélectionnez un résident et notez le stock restant en chambre.</p>';
        h += '<div class="mb-3"><div class="zs-select" id="protComptResident" data-placeholder="Choisir un résident pour compter..."></div></div>';
        h += '<div id="protComptForm"></div>';
        el.innerHTML = h;
        zerdaSelect.init(document.getElementById('protComptResident'), resOpts, { search: true });
        document.getElementById('protComptResident').addEventListener('change', async () => {
            const rid = zerdaSelect.getValue('#protComptResident');
            if (!rid) return;
            const ar = await adminApiPost('admin_get_protection_attributions', { resident_id: rid });
            if (!ar.success) return;
            const attribs = ar.attributions || [];
            if (!attribs.length) { document.getElementById('protComptForm').innerHTML = '<p class="text-muted small">Aucune attribution pour ce résident.</p>'; return; }
            const existing = comptages.filter(c => c.resident_id === rid);
            let fh = '<div class="prot-compt-card">';
            fh += attribs.map(a => {
                const ex = existing.find(c => c.produit_id === a.produit_id);
                const val = ex ? ex.quantite_restante : '';
                return '<div class="prot-compt-row"><span class="prot-prod-dot" style="background:' + (a.couleur||'#2d4a43') + '"></span><span class="small fw-bold" style="flex:1">' + escapeHtml(a.produit_nom) + (a.taille ? ' ('+escapeHtml(a.taille)+')' : '') + '</span><span class="small text-muted">Hebdo: ' + a.quantite_hebdo + '</span><input type="number" class="form-control form-control-sm prot-qte-input" data-prod="' + a.produit_id + '" data-res="' + a.resident_id + '" min="0" value="' + val + '" placeholder="Rest."></div>';
            }).join('');
            fh += '<button class="btn btn-primary btn-sm mt-3" id="protComptSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button></div>';
            document.getElementById('protComptForm').innerHTML = fh;
            document.getElementById('protComptSaveBtn')?.addEventListener('click', async () => {
                for (const inp of document.querySelectorAll('.prot-qte-input')) {
                    if (inp.value === '') continue;
                    await adminApiPost('admin_save_protection_comptage', { resident_id: inp.dataset.res, produit_id: inp.dataset.prod, quantite_restante: parseInt(inp.value), semaine: semaineInput.value });
                }
                showToast('Comptage enregistré', 'success'); loadComptages();
            });
        });
    }

    function renderValidation() {
        const el = document.getElementById('protContent');
        const pending = comptages.filter(c => c.statut === 'compté');
        if (!pending.length) { el.innerHTML = '<p class="text-muted text-center py-4">Tous les comptages sont validés.</p>'; return; }
        let h = '<div class="prot-table-wrap"><table class="prot-table"><thead><tr><th>Résident</th><th>Ch.</th><th>Produit</th><th>Hebdo</th><th>Restant</th><th>À livrer</th><th>Par</th><th></th></tr></thead><tbody>';
        pending.forEach(c => {
            h += '<tr><td><strong>' + escapeHtml(c.resident_nom + ' ' + c.resident_prenom) + '</strong></td><td>' + escapeHtml(c.chambre||'') + '</td><td><span class="prot-prod-dot" style="background:' + (c.couleur||'#2d4a43') + '"></span>' + escapeHtml(c.produit_nom) + '</td><td>' + (c.quantite_hebdo||'—') + '</td><td>' + c.quantite_restante + '</td><td class="' + (c.quantite_a_livrer > 0 ? 'prot-deficit fw-bold' : 'prot-ok') + '">' + (c.quantite_a_livrer||0) + '</td><td class="small">' + escapeHtml((c.compteur_prenom||'')+' '+(c.compteur_nom||'').charAt(0)+'.') + '</td><td><input type="checkbox" class="form-check-input prot-val-check" value="' + c.id + '" checked></td></tr>';
        });
        h += '</tbody></table></div><button class="btn btn-primary btn-sm mt-3" id="protValBtn"><i class="bi bi-check-all"></i> Valider</button>';
        el.innerHTML = h;
        document.getElementById('protValBtn')?.addEventListener('click', async () => {
            const ids = [...document.querySelectorAll('.prot-val-check:checked')].map(c => c.value);
            if (!ids.length) return;
            const r = await adminApiPost('admin_validate_protection_comptages', { ids });
            if (r.success) { showToast('Validés','success'); loadComptages(); }
        });
    }

    function renderLivraison() {
        const el = document.getElementById('protContent');
        const ready = comptages.filter(c => c.statut === 'validé');
        if (!ready.length) { el.innerHTML = '<p class="text-muted text-center py-4">Aucune livraison en attente.</p>'; return; }
        let h = '<div class="prot-table-wrap"><table class="prot-table"><thead><tr><th>Résident</th><th>Ch.</th><th>Produit</th><th>Quantité</th><th>Validé par</th><th></th></tr></thead><tbody>';
        ready.forEach(c => {
            h += '<tr><td><strong>' + escapeHtml(c.resident_nom + ' ' + c.resident_prenom) + '</strong></td><td>' + escapeHtml(c.chambre||'') + '</td><td><span class="prot-prod-dot" style="background:' + (c.couleur||'#2d4a43') + '"></span>' + escapeHtml(c.produit_nom) + '</td><td class="fw-bold">' + (c.quantite_a_livrer||0) + '</td><td class="small">' + escapeHtml((c.valideur_prenom||'')+' '+(c.valideur_nom||'')) + '</td><td><input type="checkbox" class="form-check-input prot-liv-check" value="' + c.id + '" checked></td></tr>';
        });
        h += '</tbody></table></div><button class="btn btn-sm mt-3" style="background:#bcd2cb;color:#2d4a43" id="protLivBtn"><i class="bi bi-truck"></i> Confirmer livraisons</button>';
        el.innerHTML = h;
        document.getElementById('protLivBtn')?.addEventListener('click', async () => {
            const ids = [...document.querySelectorAll('.prot-liv-check:checked')].map(c => c.value);
            if (!ids.length) return;
            const r = await adminApiPost('admin_deliver_protection_comptages', { ids });
            if (r.success) { showToast('Livrés','success'); loadComptages(); }
        });
    }

    async function loadProduits() {
        const r = await adminApiPost('admin_get_protection_produits');
        if (r.success) produits = r.produits || [];
        initProdSelect();
        const el = document.getElementById('protContent');
        let h = '<div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold mb-0">Catalogue produits</h6><button class="btn btn-primary btn-sm" id="protNewProdBtn"><i class="bi bi-plus-lg"></i> Nouveau</button></div>';
        if (!produits.length) h += '<p class="text-muted text-center py-4">Aucun produit</p>';
        else produits.forEach(p => {
            h += '<div class="prot-prod-card"><span class="prot-prod-dot" style="background:' + (p.couleur||'#2d4a43') + ';width:16px;height:16px"></span><div class="prot-prod-info"><div class="prot-prod-name">' + escapeHtml(p.nom) + '</div><div class="prot-prod-meta">' + [p.taille,p.marque,p.reference].filter(Boolean).map(escapeHtml).join(' · ') + '</div></div><button class="prot-row-btn" data-edit-prod="' + p.id + '"><i class="bi bi-pencil"></i></button><button class="prot-row-btn" data-del-prod="' + p.id + '" style="color:#7B3B2C"><i class="bi bi-trash3"></i></button></div>';
        });
        el.innerHTML = h;
        document.getElementById('protNewProdBtn')?.addEventListener('click', () => openProdModal());
        el.querySelectorAll('[data-edit-prod]').forEach(b => b.addEventListener('click', () => { const p = produits.find(x => x.id === b.dataset.editProd); if (p) openProdModal(p); }));
        el.querySelectorAll('[data-del-prod]').forEach(b => b.addEventListener('click', async () => {
            if (!await adminConfirm({title:'Supprimer',text:'Supprimer ce produit ?',type:'danger',okText:'Supprimer'})) return;
            const r = await adminApiPost('admin_delete_protection_produit',{id:b.dataset.delProd});
            if (r.success) { showToast('Supprimé','success'); loadProduits(); }
        }));
    }

    function openProdModal(p) {
        document.getElementById('protProdId').value = p?.id||'';
        document.getElementById('protProdNom').value = p?.nom||'';
        document.getElementById('protProdTaille').value = p?.taille||'';
        document.getElementById('protProdMarque').value = p?.marque||'';
        document.getElementById('protProdRef').value = p?.reference||'';
        document.getElementById('protProdColor').value = p?.couleur||'#2d4a43';
        document.getElementById('protProdModalTitle').textContent = p ? 'Modifier' : 'Nouveau produit';
        new bootstrap.Modal(document.getElementById('protProdModal')).show();
    }
    document.getElementById('protProdSave')?.addEventListener('click', async () => {
        const r = await adminApiPost('admin_save_protection_produit', { id: document.getElementById('protProdId').value, nom: document.getElementById('protProdNom').value, taille: document.getElementById('protProdTaille').value, marque: document.getElementById('protProdMarque').value, reference: document.getElementById('protProdRef').value, couleur: document.getElementById('protProdColor').value });
        if (r.success) { showToast(r.message,'success'); bootstrap.Modal.getInstance(document.getElementById('protProdModal'))?.hide(); loadProduits(); }
        else showToast(r.message||'Erreur','error');
    });

    async function loadAttributions() {
        const r = await adminApiPost('admin_get_protection_attributions');
        if (r.success) attributions = r.attributions || [];
        const el = document.getElementById('protContent');
        let h = '<div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold mb-0">Attributions par résident</h6><button class="btn btn-primary btn-sm" id="protNewAttribBtn"><i class="bi bi-plus-lg"></i> Attribuer</button></div>';
        if (!attributions.length) h += '<p class="text-muted text-center py-4">Aucune attribution</p>';
        else {
            const grouped = {};
            attributions.forEach(a => { if (!grouped[a.resident_id]) grouped[a.resident_id] = { nom:a.resident_nom, prenom:a.resident_prenom, chambre:a.chambre, items:[] }; grouped[a.resident_id].items.push(a); });
            Object.entries(grouped).forEach(([rid,g]) => {
                h += '<div class="prot-compt-card"><div class="prot-compt-header"><div class="prot-compt-resident">'+escapeHtml(g.nom+' '+g.prenom)+'</div><div class="prot-compt-chambre">Ch.'+escapeHtml(g.chambre||'?')+'</div></div>';
                g.items.forEach(a => { h += '<div class="prot-compt-row"><span class="prot-prod-dot" style="background:'+(a.couleur||'#2d4a43')+'"></span><span class="small fw-bold" style="flex:1">'+escapeHtml(a.produit_nom)+(a.taille?' ('+escapeHtml(a.taille)+')':'')+'</span><span class="small">'+a.quantite_hebdo+'/sem</span>'+(a.notes?'<span class="small text-muted">'+escapeHtml(a.notes)+'</span>':'')+'<button class="prot-row-btn" data-del-attrib="'+a.id+'" style="color:#7B3B2C"><i class="bi bi-trash3"></i></button></div>'; });
                h += '</div>';
            });
        }
        el.innerHTML = h;
        document.getElementById('protNewAttribBtn')?.addEventListener('click', () => { zerdaSelect.setValue('#protAttribResident',''); zerdaSelect.setValue('#protAttribProduit',''); document.getElementById('protAttribQte').value=0; document.getElementById('protAttribNotes').value=''; new bootstrap.Modal(document.getElementById('protAttribModal')).show(); });
        el.querySelectorAll('[data-del-attrib]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('Supprimer ?')) return;
            const r = await adminApiPost('admin_delete_protection_attribution',{id:b.dataset.delAttrib});
            if (r.success) { showToast('Supprimé','success'); loadAttributions(); }
        }));
    }
    document.getElementById('protAttribSave')?.addEventListener('click', async () => {
        const r = await adminApiPost('admin_save_protection_attribution', { resident_id: zerdaSelect.getValue('#protAttribResident'), produit_id: zerdaSelect.getValue('#protAttribProduit'), quantite_hebdo: document.getElementById('protAttribQte').value, notes: document.getElementById('protAttribNotes').value });
        if (r.success) { showToast(r.message,'success'); bootstrap.Modal.getInstance(document.getElementById('protAttribModal'))?.hide(); loadAttributions(); }
        else showToast(r.message||'Erreur','error');
    });

    load();
})();
</script>
