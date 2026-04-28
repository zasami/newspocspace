<?php
$secteurs = [
    ''               => ['Tous', null],
    'soins'          => ['Soins',          '#1f6359'],
    'socio_culturel' => ['Socio-culturel', '#9268b3'],
    'hotellerie'     => ['Hôtellerie',     '#d49039'],
    'maintenance'    => ['Maintenance',    '#5a82a8'],
    'administration' => ['Administration', '#a89a3d'],
    'management'     => ['Management',     '#c46658'],
];

// Stats SSR (premier render rapide)
$nbCollab    = (int) Db::getOne("SELECT COUNT(DISTINCT cu.user_id) FROM competences_user cu JOIN users u ON u.id = cu.user_id WHERE u.is_active = 1");
$nbCollabTot = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role IN ('collaborateur','responsable')");
$nbHaute     = (int) Db::getOne("SELECT COUNT(DISTINCT user_id) FROM competences_user WHERE priorite = 'haute'");
$nbMoyenne   = (int) Db::getOne("SELECT COUNT(DISTINCT user_id) FROM competences_user WHERE priorite = 'moyenne'");
$nbAJour     = (int) Db::getOne(
    "SELECT COUNT(DISTINCT user_id) FROM competences_user
     WHERE user_id NOT IN (SELECT user_id FROM competences_user WHERE priorite IN ('haute','moyenne'))"
);
$conformiteGlobal = $nbCollab > 0 ? round(($nbAJour / $nbCollab) * 100) : 0;
$nbHeuresFormation = (float) Db::getOne(
    "SELECT COALESCE(SUM(p.heures_realisees), 0) FROM formation_participants p
     JOIN formations f ON f.id = p.formation_id
     WHERE p.statut IN ('present','valide') AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())"
);
?>

<!-- Hero teal sombre style maquette -->
<div class="comp-hero">
  <div class="comp-hero-inner">
    <div style="flex:1;min-width:280px">
      <div class="comp-hero-label">Module RH · Compétences FEGEMS</div>
      <h1>Cartographie des compétences</h1>
      <div class="comp-hero-sub">Vision d'équipe alignée sur le référentiel <strong style="color:#a8e6c9">FEGEMS</strong> · suivi des écarts entre niveau requis et niveau validé.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?page=rh-formations-profil" class="btn-on-dark" data-page-link>
        <i class="bi bi-bullseye"></i> Profil attendu
      </a>
      <a href="?page=rh-formations-fegems" class="btn-on-light" data-page-link>
        <i class="bi bi-cloud-arrow-up"></i> Inscriptions FEGEMS
      </a>
    </div>
  </div>
</div>

<!-- Snap cards en pull-up -->
<div class="comp-snap">
  <div class="comp-snap-card">
    <div class="comp-lbl">Conformité globale</div>
    <div class="comp-val <?= $conformiteGlobal >= 70 ? 'ok' : ($conformiteGlobal >= 50 ? 'warn' : 'bad') ?>"><?= $conformiteGlobal ?><span style="font-size:1rem;color:var(--cl-text-muted)">%</span></div>
    <div class="comp-sub"><?= $nbAJour ?> / <?= $nbCollab ?> à jour sur toutes leurs thématiques</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Collaborateurs évalués</div>
    <div class="comp-val"><?= $nbCollab ?><span style="font-size:1rem;color:var(--cl-text-muted)"> / <?= $nbCollabTot ?></span></div>
    <div class="comp-sub"><?= max(0, $nbCollabTot - $nbCollab) ?> en attente d'évaluation</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Écarts prioritaires</div>
    <div class="comp-val bad"><?= $nbHaute ?></div>
    <div class="comp-sub"><?= $nbMoyenne ?> écarts moyens à planifier</div>
  </div>
  <div class="comp-snap-card">
    <div class="comp-lbl">Heures formation <?= date('Y') ?></div>
    <div class="comp-val"><?= number_format($nbHeuresFormation, 0, ',', ' ') ?><span style="font-size:1rem;color:var(--cl-text-muted)">h</span></div>
    <div class="comp-sub"><?= $nbCollab > 0 ? round($nbHeuresFormation / $nbCollab, 1) : 0 ?> h / collab. moyen</div>
  </div>
</div>

<!-- Filtres -->
<div class="card mb-3">
  <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
    <span class="small text-muted me-2 fw-bold" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem">Secteur</span>
    <?php foreach ($secteurs as $key => [$label, $color]): ?>
      <button class="btn btn-sm rhc-filter-secteur<?= $key === '' ? ' active' : '' ?>"
              data-secteur="<?= h($key) ?>"
              style="font-size:.78rem;border-radius:99px;<?= $key === '' ? 'background:var(--cl-primary);color:#fff;border-color:var(--cl-primary)' : 'background:#fff;border:1px solid var(--cl-border-light)' ?>">
        <?php if ($color): ?><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= h($color) ?>;margin-right:5px"></span><?php endif ?>
        <?= h($label) ?>
      </button>
    <?php endforeach ?>
    <span class="vr mx-2"></span>
    <span class="small text-muted me-2 fw-bold" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem">Priorité</span>
    <button class="btn btn-sm rhc-filter-priorite active" data-priorite="" style="font-size:.78rem;border-radius:99px;background:var(--cl-primary);color:#fff;border-color:var(--cl-primary)">Toutes</button>
    <button class="btn btn-sm rhc-filter-priorite" data-priorite="haute"   style="font-size:.78rem;border-radius:99px;background:#fff;border:1px solid var(--cl-border-light)"><span class="comp-priorite comp-priorite-haute" style="padding:0;background:none">Haute</span></button>
    <button class="btn btn-sm rhc-filter-priorite" data-priorite="moyenne" style="font-size:.78rem;border-radius:99px;background:#fff;border:1px solid var(--cl-border-light)"><span class="comp-priorite comp-priorite-moyenne" style="padding:0;background:none">Moyenne</span></button>
    <button class="btn btn-sm rhc-filter-priorite" data-priorite="a_jour"  style="font-size:.78rem;border-radius:99px;background:#fff;border:1px solid var(--cl-border-light)"><span class="comp-priorite comp-priorite-a_jour" style="padding:0;background:none">À jour</span></button>
  </div>
</div>

<!-- Liste collaborateurs -->
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="mb-0" style="font-weight:600">Équipe — <span id="rhcCollabCount">—</span> collaborateurs</h5>
    <div class="text-muted small">Trié par priorité d'écart · cliquez une ligne pour voir la fiche</div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:.85rem">
      <thead class="table-light">
        <tr>
          <th style="width:25%">Collaborateur</th>
          <th>Secteur</th>
          <th>Thématique prioritaire</th>
          <th style="width:180px">Niveau actuel → requis</th>
          <th>Écart</th>
          <th>Priorité</th>
          <th style="width:140px">Conformité</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="rhcTbody">
        <tr><td colspan="8" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Heatmap secteurs × thématiques -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <h5 class="mb-0" style="font-weight:600">Heatmap des écarts par secteur</h5>
      <div class="text-muted small mt-1">Niveau moyen actuel · échelle FEGEMS 1 (débute) → 4 (référent)</div>
    </div>
    <div class="d-flex align-items-center gap-2 small text-muted">
      <span>Légende:</span>
      <span class="hm-cell hm-c1 px-2" style="border-radius:4px">1</span>
      <span class="hm-cell hm-c2 px-2" style="border-radius:4px">2</span>
      <span class="hm-cell hm-c3 px-2" style="border-radius:4px">3</span>
      <span class="hm-cell hm-c4 px-2" style="border-radius:4px">4</span>
    </div>
  </div>
  <div class="card-body">
    <div id="rhcHeatmap" class="comp-heatmap">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    const SECTEUR_COLORS = <?= json_encode(array_combine(array_keys($secteurs), array_map(fn($s) => $s[1] ?? '#888', $secteurs))) ?>;
    let collabData = [];
    let currentSecteur = '';
    let currentPriorite = '';

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function initials(p, n) { return ((p?.[0] ?? '') + (n?.[0] ?? '')).toUpperCase(); }
    function avatarColor(seed) {
        const colors = ['#1f6359', '#c46658', '#5a82a8', '#9268b3', '#d49039', '#a89a3d', '#6b9558'];
        let h = 0; for (let i = 0; i < (seed || '').length; i++) h = (h * 31 + seed.charCodeAt(i)) % 1000;
        return colors[h % colors.length];
    }

    function loadCartographie() {
        adminApiPost('admin_get_cartographie_equipe', {
            secteur: currentSecteur,
            priorite: currentPriorite
        }).then(r => {
            if (!r.success) return;
            collabData = r.collaborateurs || [];
            renderCollabs();
        });
    }

    function renderCollabs() {
        document.getElementById('rhcCollabCount').textContent = collabData.length;
        const tbody = document.getElementById('rhcTbody');
        if (!collabData.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> Aucun collaborateur</td></tr>';
            return;
        }
        tbody.innerHTML = collabData.map(c => {
            const av = '<div style="width:34px;height:34px;border-radius:50%;background:' + avatarColor(c.id) + ';color:#fff;display:grid;place-items:center;font-size:.72rem;font-weight:600;flex-shrink:0">' + initials(c.prenom, c.nom) + '</div>';
            const meta = '<div><div class="fw-bold">' + escapeHtml(c.prenom + ' ' + c.nom) + '</div>' +
                '<div class="small text-muted">' + escapeHtml(c.fonction_nom || '—') + ' · ' + (c.taux ? Math.round(c.taux) + '%' : '—') + '</div></div>';
            const sect = c.secteur_fegems
                ? '<span class="comp-tag comp-tag-' + c.secteur_fegems + '"><span class="dot"></span>' + escapeHtml((c.secteur_fegems === 'socio_culturel' ? 'Socio-c.' : c.secteur_fegems.charAt(0).toUpperCase() + c.secteur_fegems.slice(1))) + '</span>'
                : '<span class="text-muted small">—</span>';
            const them = c.thematique_prioritaire ? escapeHtml(c.thematique_prioritaire) : '<span class="text-muted small">Aucune évaluation</span>';
            const niv = (c.niveau_actuel !== null && c.niveau_requis !== null)
                ? '<div class="d-flex align-items-center gap-2"><div style="display:flex;gap:2px">' +
                    Array.from({length:4}, (_, i) =>
                        '<span style="width:8px;height:14px;border-radius:1.5px;background:' +
                        (i < c.niveau_actuel ? '#2d8074' : (i < c.niveau_requis ? 'repeating-linear-gradient(45deg,#e3ebe8 0 2px,#fff 2px 4px)' : '#e3ebe8')) +
                        '"></span>'
                    ).join('') +
                  '</div><span class="text-muted small" style="font-family:monospace">' + c.niveau_actuel + ' → ' + c.niveau_requis + '</span></div>'
                : '<span class="text-muted small">—</span>';
            const ecart = c.ecart !== null
                ? '<span class="comp-ecart-pill comp-ecart-' + Math.min(parseInt(c.ecart), 3) + '">' + (parseInt(c.ecart) > 0 ? '+' : '') + c.ecart + '</span>'
                : '<span class="text-muted small">—</span>';
            const prio = c.priorite
                ? '<span class="comp-priorite comp-priorite-' + c.priorite + '">' + ({haute:'Haute', moyenne:'Moyenne', basse:'Basse', a_jour:'À jour'}[c.priorite] || c.priorite) + '</span>'
                : '<span class="text-muted small">—</span>';
            const conf = c.conformite_pct !== undefined
                ? '<div class="d-flex align-items-center gap-2">' +
                    '<div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="background:' +
                    (c.conformite_pct >= 80 ? '#5cad9b' : (c.conformite_pct >= 50 ? '#d49039' : '#cd6b62')) +
                    ';width:' + c.conformite_pct + '%"></div></div>' +
                    '<span class="small text-muted" style="font-family:monospace;min-width:30px;text-align:right">' + c.conformite_pct + '%</span>' +
                  '</div>'
                : '<span class="text-muted small">—</span>';
            const link = '<a href="?page=rh-collab-competences&id=' + escapeHtml(c.id) + '" class="btn btn-sm btn-outline-secondary" data-page-link><i class="bi bi-arrow-right"></i></a>';

            return '<tr>' +
                '<td><div class="d-flex align-items-center gap-2">' + av + meta + '</div></td>' +
                '<td>' + sect + '</td>' +
                '<td>' + them + '</td>' +
                '<td>' + niv + '</td>' +
                '<td>' + ecart + '</td>' +
                '<td>' + prio + '</td>' +
                '<td>' + conf + '</td>' +
                '<td>' + link + '</td>' +
                '</tr>';
        }).join('');
    }

    function loadHeatmap() {
        adminApiPost('admin_get_heatmap_secteurs', {}).then(r => {
            if (!r.success) return;
            renderHeatmap(r.thematiques || [], r.secteurs || [], r.heatmap || {});
        });
    }

    function renderHeatmap(thems, secteurs, data) {
        const el = document.getElementById('rhcHeatmap');
        if (!thems.length) {
            el.innerHTML = '<div class="comp-empty"><i class="bi bi-bar-chart"></i>Aucune donnée d\'évaluation</div>';
            return;
        }
        // Grille : col = "180px " + secteurs.length cols ; rows = thems.length + 1 (header)
        el.style.gridTemplateColumns = '180px repeat(' + secteurs.length + ', minmax(70px, 1fr))';
        let html = '<div></div>';
        secteurs.forEach(s => {
            const label = s === 'socio_culturel' ? 'Socio-c.' : s.charAt(0).toUpperCase() + s.slice(1);
            html += '<div class="hm-col-lbl">' + escapeHtml(label) + '</div>';
        });
        thems.forEach(t => {
            html += '<div class="hm-row-lbl">' + escapeHtml(t.nom) + '</div>';
            secteurs.forEach(s => {
                const val = data[t.id + '|' + s];
                if (val === undefined) {
                    html += '<div class="hm-cell hm-c0">—</div>';
                } else {
                    const cls = val >= 3.5 ? 'hm-c4' : val >= 2.5 ? 'hm-c3' : val >= 1.5 ? 'hm-c2' : 'hm-c1';
                    html += '<div class="hm-cell ' + cls + '">' + val.toFixed(1) + '</div>';
                }
            });
        });
        el.innerHTML = html;
    }

    function setActive(selector, value, attr) {
        document.querySelectorAll(selector).forEach(b => {
            const isActive = (b.dataset[attr] || '') === (value || '');
            b.classList.toggle('active', isActive);
            b.style.background = isActive ? 'var(--cl-primary)' : '#fff';
            b.style.color = isActive ? '#fff' : '';
            b.style.borderColor = isActive ? 'var(--cl-primary)' : 'var(--cl-border-light)';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.rhc-filter-secteur').forEach(btn => {
            btn.addEventListener('click', () => {
                currentSecteur = btn.dataset.secteur || '';
                setActive('.rhc-filter-secteur', currentSecteur, 'secteur');
                loadCartographie();
            });
        });
        document.querySelectorAll('.rhc-filter-priorite').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPriorite = btn.dataset.priorite || '';
                setActive('.rhc-filter-priorite', currentPriorite, 'priorite');
                loadCartographie();
            });
        });

        loadCartographie();
        loadHeatmap();
    });
})();
</script>
