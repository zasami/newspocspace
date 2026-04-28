<?php
$secteurs = [
    'soins'          => ['Soins',          'soins'],
    'socio_culturel' => ['Socio-culturel', 'socio_culturel'],
    'hotellerie'     => ['Hôtellerie',     'hotellerie'],
    'maintenance'    => ['Maintenance',    'maintenance'],
    'administration' => ['Administration', 'administration'],
    'management'     => ['Management',     'management'],
];

$catLabels = [
    'fegems_base'      => ['Thématiques de base FEGEMS', 'shield-check', 'teal'],
    'referent'         => ['Référents (rôles spécialisés)', 'mortarboard', 'purple'],
    'referent_pedago'  => ['Compétences pédagogiques référents', 'book', 'orange'],
];

$nbThemBase = (int) Db::getOne("SELECT COUNT(*) FROM competences_thematiques WHERE actif = 1 AND categorie = 'fegems_base'");
$nbProfils  = (int) Db::getOne("SELECT COUNT(*) FROM competences_profil_attendu WHERE requis = 1");
$totalCellsBase = $nbThemBase * 6;
$pctRempli = $totalCellsBase > 0 ? round(($nbProfils / $totalCellsBase) * 100) : 0;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-bullseye"></i> Profil d'équipe attendu</h4>
  <a href="?page=rh-formations-cartographie" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-diagram-3"></i> Cartographie
  </a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-tags"></i></div>
      <div><div class="stat-value" id="rhpStatThem"><?= $nbThemBase ?></div><div class="stat-label">Thématiques actives</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-grid-3x3"></i></div>
      <div><div class="stat-value" id="rhpStatCells"><?= $nbProfils ?></div><div class="stat-label">Cellules définies</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-pie-chart"></i></div>
      <div><div class="stat-value" id="rhpStatPct"><?= $pctRempli ?>%</div><div class="stat-label">Matrice de base remplie</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-people"></i></div>
      <div><div class="stat-value">6</div><div class="stat-label">Secteurs FEGEMS</div></div></div>
  </div>
</div>

<div class="alert" style="background:var(--cl-bg);border:1px solid var(--cl-border-light);font-size:.85rem">
  <i class="bi bi-info-circle"></i>
  <strong>Cliquez une cellule</strong> pour définir le niveau requis (1-4) et la part du personnel à former (%).
  Cette matrice est la fondation de la cartographie individuelle : chaque collaborateur sera évalué
  sur les thématiques requises pour son secteur.
</div>

<div class="comp-matrix">
  <div class="comp-matrix-head">
    <div>
      <h5 class="mb-0" style="font-weight:600">Matrice thématique × secteur</h5>
      <div class="text-muted small mt-1">
        Niveau · <span class="comp-cell niv-1" style="margin:0 4px"><span class="niv">1</span></span> Connaît
        <span class="comp-cell niv-2" style="margin:0 4px"><span class="niv">2</span></span> Supervision
        <span class="comp-cell niv-3" style="margin:0 4px"><span class="niv">3</span></span> Autonome
        <span class="comp-cell niv-4" style="margin:0 4px"><span class="niv">4</span></span> Référent
      </div>
    </div>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-magic"></i> Remplissage rapide
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($secteurs as $key => [$label]): ?>
          <li><a class="dropdown-item rhp-fill-secteur" href="#" data-secteur="<?= h($key) ?>">
            <i class="bi bi-arrow-right"></i> Pré-remplir <?= h($label) ?> (niveau 2 · 100 %)
          </a></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>

  <div id="rhpMatrixBody">
    <div class="text-center py-5"><span class="spinner-border spinner-border-sm"></span> Chargement de la matrice…</div>
  </div>
</div>

<!-- ═══ Modal édition cellule ═══ -->
<div class="modal fade" id="rhpCellModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhpCellTitle">Profil attendu</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rhpCellThemId">
        <input type="hidden" id="rhpCellSecteur">
        <div class="text-muted small mb-3" id="rhpCellSubtitle">—</div>

        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="rhpCellRequis" checked>
          <label class="form-check-label" for="rhpCellRequis"><strong>Thématique requise pour ce secteur</strong></label>
        </div>

        <div id="rhpCellRequisFields">
          <div class="mb-3">
            <label class="form-label">Niveau requis (échelle FEGEMS)</label>
            <div class="btn-group w-100" role="group" id="rhpNiveauGroup">
              <input type="radio" class="btn-check" name="rhpNiveau" id="rhpNiv1" value="1">
              <label class="btn btn-outline-secondary" for="rhpNiv1"><strong>1</strong><br><span class="small">Débute</span></label>
              <input type="radio" class="btn-check" name="rhpNiveau" id="rhpNiv2" value="2" checked>
              <label class="btn btn-outline-secondary" for="rhpNiv2"><strong>2</strong><br><span class="small">Supervision</span></label>
              <input type="radio" class="btn-check" name="rhpNiveau" id="rhpNiv3" value="3">
              <label class="btn btn-outline-secondary" for="rhpNiv3"><strong>3</strong><br><span class="small">Autonome</span></label>
              <input type="radio" class="btn-check" name="rhpNiveau" id="rhpNiv4" value="4">
              <label class="btn btn-outline-secondary" for="rhpNiv4"><strong>4</strong><br><span class="small">Référent</span></label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label d-flex justify-content-between">
              <span>Part du personnel à former (%)</span>
              <strong id="rhpPctVal">100%</strong>
            </label>
            <input type="range" class="form-range" id="rhpCellPct" min="0" max="100" step="5" value="100">
          </div>
          <div class="mb-3">
            <label class="form-label">Type de formation recommandé</label>
            <select class="form-select form-select-sm" id="rhpCellType">
              <option value="continue_catalogue">Formation continue catalogue</option>
              <option value="interne">Formation interne</option>
              <option value="superieur">Formation supérieure (HES, brevet)</option>
              <option value="vae">VAE</option>
              <option value="autodidacte">Autodidacte</option>
              <option value="tutorat">Tutorat / coaching</option>
              <option value="fpp">Formation professionnelle pratique</option>
              <option value="autre">Autre</option>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label">Objectif stratégique <span class="text-muted small">(optionnel)</span></label>
            <textarea class="form-control form-control-sm" id="rhpCellObjectif" rows="2" placeholder="Ex: 100 % du personnel Soins formé HPCI avant 12.2026"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="rhpCellClear">
          <i class="bi bi-trash"></i> Effacer
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="rhpCellSave"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const SECTEURS = <?= json_encode(array_keys($secteurs)) ?>;
    const SECTEUR_LABELS = <?= json_encode(array_map(fn($s) => $s[0], $secteurs)) ?>;
    const CAT_LABELS = <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE) ?>;

    let thematiques = [];
    let cells = {};
    let modal = null;

    function loadMatrix() {
        adminApiPost('admin_get_profil_attendu', {}).then(r => {
            if (!r.success) return;
            thematiques = r.thematiques || [];
            cells = r.cells || {};
            renderMatrix();
            updateStats(r.stats);
        });
    }

    function updateStats(s) {
        if (!s) return;
        const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        el('rhpStatCells', s.cellules_definies);
    }

    function cellHtml(themId, secteur) {
        const key = themId + '|' + secteur;
        const c = cells[key];
        if (!c || !parseInt(c.requis)) {
            return '<span class="comp-cell empty" data-them="' + themId + '" data-sect="' + secteur + '">' +
                   '<span class="niv">+</span></span>';
        }
        const niv = parseInt(c.niveau_requis) || 0;
        const pct = parseFloat(c.part_a_former_pct) || 0;
        return '<span class="comp-cell niv-' + niv + '" data-them="' + themId + '" data-sect="' + secteur + '">' +
               '<span class="niv">' + niv + '</span>' +
               '<span class="pct">' + Math.round(pct) + '%</span>' +
               '</span>';
    }

    function renderMatrix() {
        const body = document.getElementById('rhpMatrixBody');
        if (!body) return;

        // Grouper par catégorie
        const byCat = {};
        thematiques.forEach(t => {
            (byCat[t.categorie] = byCat[t.categorie] || []).push(t);
        });

        let html = '<table class="comp-matrix-table">';
        html += '<thead><tr><th>Thématique</th>';
        SECTEURS.forEach(s => { html += '<th>' + escapeHtml(SECTEUR_LABELS[SECTEURS.indexOf(s)]) + '</th>'; });
        html += '</tr></thead>';

        Object.keys(CAT_LABELS).forEach(cat => {
            const list = byCat[cat] || [];
            if (!list.length) return;
            html += '<tbody>';
            html += '<tr><td colspan="7" class="comp-matrix-section">'
                + '<i class="bi bi-' + CAT_LABELS[cat][1] + '"></i> ' + escapeHtml(CAT_LABELS[cat][0])
                + ' <span class="num">' + list.length + '</span></td></tr>';
            list.forEach(t => {
                html += '<tr><td>' +
                    (t.icone ? '<i class="bi bi-' + t.icone + '" style="color:var(--comp-teal-500);margin-right:6px"></i>' : '') +
                    escapeHtml(t.nom) +
                    (t.tag_affichage ? ' <span class="text-muted small ms-2">' + escapeHtml(t.tag_affichage) + '</span>' : '') +
                    '</td>';
                SECTEURS.forEach(s => { html += '<td>' + cellHtml(t.id, s) + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody>';
        });

        html += '</table>';
        body.innerHTML = html;

        body.querySelectorAll('.comp-cell').forEach(el => {
            el.addEventListener('click', () => openCellModal(el.dataset.them, el.dataset.sect));
        });
    }

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }

    function openCellModal(themId, secteur) {
        const them = thematiques.find(t => t.id === themId);
        if (!them) return;
        const c = cells[themId + '|' + secteur] || { requis: 1, niveau_requis: 2, part_a_former_pct: 100, type_formation_recommande: 'continue_catalogue', objectif_strategique: '' };

        document.getElementById('rhpCellTitle').textContent = them.nom;
        document.getElementById('rhpCellSubtitle').textContent = SECTEUR_LABELS[SECTEURS.indexOf(secteur)] + ' · cliquez les boutons puis Enregistrer';
        document.getElementById('rhpCellThemId').value = themId;
        document.getElementById('rhpCellSecteur').value = secteur;
        document.getElementById('rhpCellRequis').checked = parseInt(c.requis) === 1;

        const niv = parseInt(c.niveau_requis) || 2;
        const radio = document.querySelector('input[name="rhpNiveau"][value="' + niv + '"]');
        if (radio) radio.checked = true;

        const pct = Math.round(parseFloat(c.part_a_former_pct) || 100);
        document.getElementById('rhpCellPct').value = pct;
        document.getElementById('rhpPctVal').textContent = pct + '%';

        document.getElementById('rhpCellType').value = c.type_formation_recommande || 'continue_catalogue';
        document.getElementById('rhpCellObjectif').value = c.objectif_strategique || '';

        toggleRequisFields();
        modal.show();
    }

    function toggleRequisFields() {
        const requis = document.getElementById('rhpCellRequis').checked;
        document.getElementById('rhpCellRequisFields').style.display = requis ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('rhpCellModal');
        if (modalEl) modal = new bootstrap.Modal(modalEl);

        document.getElementById('rhpCellRequis')?.addEventListener('change', toggleRequisFields);

        document.getElementById('rhpCellPct')?.addEventListener('input', e => {
            document.getElementById('rhpPctVal').textContent = e.target.value + '%';
        });

        document.getElementById('rhpCellSave')?.addEventListener('click', () => {
            const themId = document.getElementById('rhpCellThemId').value;
            const secteur = document.getElementById('rhpCellSecteur').value;
            const requis = document.getElementById('rhpCellRequis').checked ? 1 : 0;
            const niveau = document.querySelector('input[name="rhpNiveau"]:checked')?.value || 2;
            const pct = document.getElementById('rhpCellPct').value;
            const type = document.getElementById('rhpCellType').value;
            const obj = document.getElementById('rhpCellObjectif').value;

            adminApiPost('admin_save_profil_cellule', {
                thematique_id: themId, secteur, requis,
                niveau_requis: niveau, part_a_former_pct: pct,
                type_formation_recommande: type, objectif_strategique: obj
            }).then(r => {
                if (!r.success) { showToast(r.error || 'Erreur', 'danger'); return; }
                modal.hide();
                showToast('Cellule enregistrée', 'success');
                loadMatrix();
            });
        });

        document.getElementById('rhpCellClear')?.addEventListener('click', () => {
            const themId = document.getElementById('rhpCellThemId').value;
            const secteur = document.getElementById('rhpCellSecteur').value;
            adminApiPost('admin_clear_profil_cellule', { thematique_id: themId, secteur }).then(r => {
                if (!r.success) return;
                modal.hide();
                showToast('Cellule effacée', 'success');
                loadMatrix();
            });
        });

        document.querySelectorAll('.rhp-fill-secteur').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                const secteur = el.dataset.secteur;
                if (!confirm('Pré-remplir toutes les thématiques de base pour le secteur ' + SECTEUR_LABELS[SECTEURS.indexOf(secteur)] + ' (niveau 2 · 100 %) ? Les cellules déjà définies ne seront pas écrasées.')) return;
                adminApiPost('admin_remplir_secteur', {
                    secteur, niveau_requis: 2, part_a_former_pct: 100, categorie: 'fegems_base'
                }).then(r => {
                    if (!r.success) return;
                    showToast(r.message || 'Secteur pré-rempli', 'success');
                    loadMatrix();
                });
            });
        });

        loadMatrix();
    });
})();
</script>
