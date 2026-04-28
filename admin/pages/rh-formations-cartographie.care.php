<?php
// ─── Cartographie d'équipe · version Care (Spocspace DS v1.0) ────────────────

$secteurs = [
    ''               => ['Tous',           null,    ''],
    'soins'          => ['Soins',          '#1f6359', 's-soins'],
    'socio_culturel' => ['Socio-culturel', '#5e3a78', 's-anim'],
    'hotellerie'     => ['Hôtellerie',     '#8a5a1a', 's-hot'],
    'maintenance'    => ['Maintenance',    '#2d4a6b', 's-tech'],
    'administration' => ['Administration', '#6b5a1f', 's-admin'],
    'management'     => ['Management',     '#8a3a30', 's-mgmt'],
];

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
<div class="care-page">

  <!-- Hero -->
  <header class="ds-hero">
    <div class="t-eyebrow" style="color:#a8e6c9">Module RH · Compétences FEGEMS</div>
    <h1>Cartographie des compétences</h1>
    <div class="ds-hero-sub">
      Vision d'équipe alignée sur le référentiel
      <strong style="color:#a8e6c9">FEGEMS</strong>
      · suivi des écarts entre niveau requis et niveau validé.
    </div>
    <div style="display:flex;gap:8px;margin-top:18px;flex-wrap:wrap">
      <a href="?page=rh-formations-profil" class="btn btn-light btn-sm" data-page-link>
        <i class="bi bi-bullseye"></i> Profil attendu
      </a>
      <a href="?page=rh-formations-fegems" class="btn btn-sm" style="background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2)" data-page-link>
        <i class="bi bi-cloud-arrow-up"></i> Inscriptions FEGEMS
      </a>
    </div>
  </header>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="kpi feature">
        <div class="kpi-lbl">Conformité globale</div>
        <div class="kpi-val t-num"><?= $conformiteGlobal ?><small>%</small></div>
        <div class="kpi-delta"><?= $ajourComp ?? $nbAJour ?>/<?= $nbCollab ?> collaborateurs à jour</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Collaborateurs évalués</div>
        <div class="kpi-val t-num"><?= $nbCollab ?><small> / <?= $nbCollabTot ?></small></div>
        <div class="kpi-delta"><?= max(0, $nbCollabTot - $nbCollab) ?> en attente d'évaluation</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Écarts prioritaires</div>
        <div class="kpi-val t-num" style="color:var(--danger)"><?= $nbHaute ?></div>
        <div class="kpi-delta bad"><?= $nbMoyenne ?> écarts moyens à planifier</div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi">
        <div class="kpi-lbl">Heures formation <?= date('Y') ?></div>
        <div class="kpi-val t-num"><?= number_format($nbHeuresFormation, 0, ',', ' ') ?><small>h</small></div>
        <div class="kpi-delta"><?= $nbCollab > 0 ? round($nbHeuresFormation / $nbCollab, 1) : 0 ?> h / collab</div>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <section class="care-section">
    <div class="ds-card ds-card-padded mb-3">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="t-meta me-2">Secteur</span>
        <?php foreach ($secteurs as $key => [$label, $color, $cls]): ?>
          <button class="ds-pill <?= $key === '' ? 'on' : '' ?> rhc-filter-secteur"
                  data-secteur="<?= h($key) ?>">
            <?php if ($color): ?><span class="dot" style="background:<?= h($color) ?>"></span><?php endif ?>
            <?= h($label) ?>
          </button>
        <?php endforeach ?>

        <span style="width:1px;height:20px;background:var(--line);margin:0 8px"></span>

        <span class="t-meta me-2">Priorité</span>
        <button class="ds-pill on rhc-filter-priorite" data-priorite="">Toutes</button>
        <button class="ds-pill rhc-filter-priorite" data-priorite="haute" style="border-color:var(--danger-line)">
          <span class="dot" style="background:var(--danger)"></span>Haute
        </button>
        <button class="ds-pill rhc-filter-priorite" data-priorite="moyenne" style="border-color:var(--warn-line)">
          <span class="dot" style="background:var(--warn)"></span>Moyenne
        </button>
        <button class="ds-pill rhc-filter-priorite" data-priorite="a_jour" style="border-color:var(--ok-line)">
          <span class="dot" style="background:var(--ok)"></span>À jour
        </button>
      </div>
    </div>
  </section>

  <!-- Liste collaborateurs -->
  <section class="care-section">
    <div class="care-section-title">
      <h2 class="serif">Équipe · <span id="rhcCollabCount" class="t-num">—</span> collaborateurs</h2>
      <div class="t-meta">Trié par priorité d'écart · cliquez une ligne pour voir la fiche</div>
    </div>

    <div class="ds-tbl-wrap">
      <table class="ds-tbl">
        <thead>
          <tr>
            <th style="width:25%">Collaborateur</th>
            <th>Secteur</th>
            <th>Thématique prioritaire</th>
            <th style="width:170px">Niveau</th>
            <th class="num">Écart</th>
            <th>Priorité</th>
            <th style="width:140px">Conformité</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="rhcTbody">
          <tr><td colspan="8" class="ds-empty"><i class="bi bi-arrow-clockwise"></i> Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Heatmap -->
  <section class="care-section">
    <div class="care-section-title">
      <h2 class="serif">Heatmap des écarts par secteur</h2>
      <div class="d-flex align-items-center gap-2 t-meta">
        Légende
        <span class="hm-cell hm-c1" style="padding:2px 8px;border-radius:4px">1</span>
        <span class="hm-cell hm-c2" style="padding:2px 8px;border-radius:4px">2</span>
        <span class="hm-cell hm-c3" style="padding:2px 8px;border-radius:4px">3</span>
        <span class="hm-cell hm-c4" style="padding:2px 8px;border-radius:4px">4</span>
      </div>
    </div>

    <div class="ds-card ds-card-padded">
      <div id="rhcHeatmap" class="comp-heatmap">
        <div class="ds-empty"><i class="bi bi-arrow-clockwise"></i> Chargement…</div>
      </div>
    </div>
  </section>

</div>

<script<?= nonce() ?>>
(function() {
    const SECTEUR_TAG = {
        soins: 's-soins',
        socio_culturel: 's-anim',
        hotellerie: 's-hot',
        maintenance: 's-tech',
        administration: 's-admin',
        management: 's-mgmt',
    };
    const SECTEUR_LABEL = {
        soins: 'Soins', socio_culturel: 'Socio-culturel', hotellerie: 'Hôtellerie',
        maintenance: 'Maintenance', administration: 'Administration', management: 'Management',
    };

    let collabData = [];
    let currentSecteur = '';
    let currentPriorite = '';

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function initials(p, n) { return ((p?.[0] ?? '') + (n?.[0] ?? '')).toUpperCase(); }
    function avatarColor(seed) {
        const colors = ['#1f6359', '#8a5a1a', '#2d4a6b', '#5e3a78', '#6b5a1f', '#8a3a30', '#3a5a2d'];
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
            tbody.innerHTML = '<tr><td colspan="8" class="ds-empty"><i class="bi bi-inbox"></i><div class="ds-empty-title">Aucun collaborateur</div></td></tr>';
            return;
        }
        tbody.innerHTML = collabData.map(c => {
            const av = '<div class="ds-avatar" style="background:' + avatarColor(c.id) + '">' + initials(c.prenom, c.nom) + '</div>';
            const meta = '<div><div style="font-weight:500;color:var(--ink)">' + escapeHtml(c.prenom + ' ' + c.nom) + '</div>' +
                '<div class="t-meta">' + escapeHtml(c.fonction_nom || '—') + ' · ' + (c.taux ? Math.round(c.taux) + '%' : '—') + '</div></div>';

            const sect = c.secteur_fegems
                ? '<span class="sector-tag ' + (SECTEUR_TAG[c.secteur_fegems] || '') + '"><span class="bullet"></span>' + escapeHtml(SECTEUR_LABEL[c.secteur_fegems] || c.secteur_fegems) + '</span>'
                : '<span class="t-meta">—</span>';

            const them = c.thematique_prioritaire ? escapeHtml(c.thematique_prioritaire) : '<span class="t-meta">Aucune évaluation</span>';

            const niv = (c.niveau_actuel !== null && c.niveau_requis !== null)
                ? '<div style="display:flex;align-items:center;gap:8px"><div style="display:flex;gap:2px">' +
                    Array.from({length:4}, (_, i) =>
                        '<span style="width:8px;height:14px;border-radius:1.5px;background:' +
                        (i < c.niveau_actuel ? 'var(--teal-500)' : (i < c.niveau_requis ? '#dde7e3' : 'var(--line)')) +
                        '"></span>'
                    ).join('') +
                  '</div><span class="t-meta t-num">' + c.niveau_actuel + ' → ' + c.niveau_requis + '</span></div>'
                : '<span class="t-meta">—</span>';

            const ecart = c.ecart !== null
                ? '<span class="t-num" style="font-weight:600;color:' +
                  (parseInt(c.ecart) >= 2 ? 'var(--danger)' : (parseInt(c.ecart) === 1 ? 'var(--warn)' : 'var(--ok)')) + '">' +
                  (parseInt(c.ecart) > 0 ? '+' : '') + c.ecart + '</span>'
                : '<span class="t-meta">—</span>';

            const prioMap = {
                haute:   ['Expirée',     'badge-danger'],
                moyenne: ['À renouveler','badge-warn'],
                basse:   ['Basse',       'badge-info'],
                a_jour:  ['Conforme',    'badge-ok'],
            };
            const prio = c.priorite && prioMap[c.priorite]
                ? '<span class="badge text-bg-' + prioMap[c.priorite][1].replace('badge-','') + '">' + prioMap[c.priorite][0] + '</span>'
                : '<span class="t-meta">—</span>';

            const conf = c.conformite_pct !== undefined
                ? '<div class="ds-progress">' +
                    '<div class="ds-progress-bar"><div class="ds-progress-fill ' +
                    (c.conformite_pct >= 80 ? '' : (c.conformite_pct >= 50 ? 'warn' : 'danger')) +
                    '" style="width:' + c.conformite_pct + '%"></div></div>' +
                    '<span class="ds-progress-pct">' + c.conformite_pct + '%</span>' +
                  '</div>'
                : '<span class="t-meta">—</span>';

            const link = '<a href="?page=rh-collab-competences&id=' + escapeHtml(c.id) + '" class="btn btn-outline-secondary btn-sm" data-page-link><i class="bi bi-arrow-right"></i></a>';

            return '<tr>' +
                '<td><div style="display:flex;align-items:center;gap:10px">' + av + meta + '</div></td>' +
                '<td>' + sect + '</td>' +
                '<td>' + them + '</td>' +
                '<td>' + niv + '</td>' +
                '<td class="num">' + ecart + '</td>' +
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
            el.innerHTML = '<div class="ds-empty"><i class="bi bi-bar-chart"></i><div class="ds-empty-title">Aucune donnée d\'évaluation</div></div>';
            return;
        }
        el.style.gridTemplateColumns = '180px repeat(' + secteurs.length + ', minmax(70px, 1fr))';
        let html = '<div></div>';
        secteurs.forEach(s => {
            html += '<div class="hm-col-lbl">' + escapeHtml(SECTEUR_LABEL[s] || s) + '</div>';
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
            b.classList.toggle('on', isActive);
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
