<?php
// ─── Données serveur — semaine courante ──────────────────────────────────────
$dto = new DateTime();
$dow = (int)$dto->format('N'); // 1=Mon
$dto->modify('-' . ($dow - 1) . ' days');
$weekStart = $dto->format('Y-m-d');

$dtoStart = new DateTime($weekStart);
$dtoEnd = clone $dtoStart;
$dtoEnd->modify('+6 days');
$weekEnd = $dtoEnd->format('Y-m-d');

$weekNum = (int)$dtoStart->format('W');
$year    = (int)$dtoStart->format('o');

$frMonths = [
    1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
    5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
    9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
];

$startDay   = (int)$dtoStart->format('j');
$endDay     = (int)$dtoEnd->format('j');
$startMonth = $frMonths[(int)$dtoStart->format('n')];
$endMonth   = $frMonths[(int)$dtoEnd->format('n')];

if ($dtoStart->format('n') === $dtoEnd->format('n')) {
    $weekLabel = "Semaine $weekNum — $startDay au $endDay $endMonth $year";
} else {
    $weekLabel = "Semaine $weekNum — $startDay $startMonth au $endDay $endMonth $year";
}

$weekIso = "$year-W" . str_pad($weekNum, 2, '0', STR_PAD_LEFT);

$repModules = Db::fetchAll("SELECT id, nom, code, ordre FROM modules ORDER BY ordre");
foreach ($repModules as &$mod) {
    $etages = Db::fetchAll("SELECT id, nom, code, ordre FROM etages WHERE module_id = ? ORDER BY ordre", [$mod['id']]);
    foreach ($etages as &$etage) {
        $etage['groupes'] = Db::fetchAll("SELECT id, nom, code, ordre FROM groupes WHERE etage_id = ? ORDER BY ordre", [$etage['id']]);
    }
    unset($etage);
    $mod['etages'] = $etages;
}
unset($mod);

$repHoraires  = Db::fetchAll("SELECT id, code, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types WHERE is_active = 1 ORDER BY code");
$repFonctions = Db::fetchAll("SELECT id, nom, code, ordre FROM fonctions ORDER BY ordre");

$repUsers = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.employee_id,
            f.id AS fonction_id, f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            hm.id AS home_module_id, hm.code AS home_module_code, hm.nom AS home_module_nom, hm.ordre AS home_module_ordre
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules hm ON hm.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY hm.ordre, f.ordre, u.nom"
);

$moisStart = $dtoStart->format('Y-m');
$moisEnd   = $dtoEnd->format('Y-m');
$moisList  = array_unique([$moisStart, $moisEnd]);
$phMois    = implode(',', array_fill(0, count($moisList), '?'));
$repPlannings = Db::fetchAll("SELECT id, mois_annee, statut FROM plannings WHERE mois_annee IN ($phMois)", $moisList);
$planningIds  = array_column($repPlannings, 'id');

$repAssignments = [];
if ($planningIds) {
    $phPlan  = implode(',', array_fill(0, count($planningIds), '?'));
    $qParams = array_merge($planningIds, [$weekStart, $weekEnd]);
    $repAssignments = Db::fetchAll(
        "SELECT pa.date_jour, pa.user_id, pa.statut, pa.notes,
                u.prenom AS user_prenom, u.nom AS user_nom,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.code AS module_code, m.id AS module_id,
                g.code AS groupe_code, g.id AS groupe_id,
                e.code AS etage_code
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN groupes g ON g.id = pa.groupe_id
         LEFT JOIN etages e ON e.id = g.etage_id
         WHERE pa.planning_id IN ($phPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
        $qParams
    );
}

$frDays  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$repDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $dtoStart;
    $d->modify("+$i days");
    $repDays[] = [
        'date'       => $d->format('Y-m-d'),
        'label'      => $frDays[$i] . ' ' . $d->format('d'),
        'short'      => $frDays[$i],
        'is_weekend' => in_array($d->format('N'), ['6', '7']),
    ];
}
?>
<!-- Week navigator -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-outline-secondary btn-sm" id="repPrevWeek" title="Semaine precedente">
      <i class="bi bi-chevron-left"></i>
    </button>
    <h6 class="mb-0 fw-semibold rep-week-label" id="repWeekLabel"><?= h($weekLabel) ?></h6>
    <button class="btn btn-outline-secondary btn-sm" id="repNextWeek" title="Semaine suivante">
      <i class="bi bi-chevron-right"></i>
    </button>
    <button class="btn btn-outline-primary btn-sm" id="repToday" title="Semaine courante">Aujourd'hui</button>
  </div>
  <div class="d-flex align-items-center gap-2">
    <input type="date" class="form-control form-control-sm rep-date-picker" id="repDatePicker" value="<?= h($weekStart) ?>">
    <button class="btn btn-outline-secondary btn-sm" id="repPrint" title="Imprimer">
      <i class="bi bi-printer"></i>
    </button>
  </div>
</div>

<!-- Planning status -->
<div id="repPlanningStatus" class="mb-2 rep-planning-status">
  <?php if (!empty($repPlannings)):
    $colors = ['brouillon' => 'secondary', 'provisoire' => 'info', 'final' => 'success'];
    $badges = array_map(fn($p) => '<span class="badge bg-' . ($colors[$p['statut']] ?? 'secondary') . ' me-1">' . h($p['mois_annee']) . ' : ' . h($p['statut']) . '</span>', $repPlannings);
    echo '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' . implode('', $badges);
  else: ?>
    <span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette periode</span>
  <?php endif; ?>
</div>

<!-- Legend -->
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center rep-legend" id="repLegend">
  <span class="text-muted fw-medium">Légende :</span>
  <?php foreach ($repHoraires as $h): ?>
    <span class="rep-legend-item">
      <span class="rep-badge" style="background:<?= htmlspecialchars($h['couleur'] ?? '#6c757d') ?>"><?= htmlspecialchars($h['code']) ?></span>
      <span class="text-muted"><?= htmlspecialchars(substr($h['heure_debut'] ?? '', 0, 5)) ?>-<?= htmlspecialchars(substr($h['heure_fin'] ?? '', 0, 5)) ?></span>
    </span>
  <?php endforeach; ?>
</div>

<!-- Planning grid container -->
<div id="repGrid" class="rep-grid-container">
  <div class="text-center py-5 text-muted">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    Chargement de la répartition...
  </div>
</div>

<!-- Styles -->
<style>
/* Layout helpers */
.rep-week-label { min-width: 260px; text-align: center; }
.rep-date-picker { width: 160px; }
.rep-planning-status { font-size: 0.8rem; }
.rep-legend { font-size: 0.75rem; }

/* Empty cell dash */
.rep-empty-dash { color: #d0d0d0; }

/* Empty state */
.rep-empty-state-icon { font-size: 2rem; opacity: .3; display: block; margin-bottom: 8px; }

.rep-grid-container {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 10px;
}

/* Module section */
.rep-module-section {
  margin-bottom: 1.5rem;
}
.rep-module-header {
  padding: 0.45rem 0.75rem;
  font-weight: 600;
  font-size: 0.88rem;
  color: #fff;
  border-radius: 4px 4px 0 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.rep-module-header .badge {
  font-size: 0.7rem;
  background: rgba(255,255,255,0.25);
}

/* Table */
.rep-table {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
  font-size: 0.78rem;
  table-layout: fixed;
}
.rep-table th,
.rep-table td {
  border: 1px solid #dee2e6;
  padding: 0.25rem 0.4rem;
  vertical-align: middle;
}
.rep-table thead th {
  background: #f1f3f5;
  font-weight: 600;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 2;
  font-size: 0.73rem;
}
.rep-table th.col-fn {
  width: 90px;
  text-align: left;
}
.rep-table th.col-name {
  width: 130px;
  text-align: left;
}
.rep-table th.col-day {
  width: calc((100% - 220px) / 7);
}

/* Function cell (rowspan) */
.rep-table td.cell-fn {
  font-weight: 600;
  font-size: 0.73rem;
  color: #333;
  background: #f8f9fa;
  vertical-align: middle;
  text-align: center;
  border-right: 2px solid #ccc;
}

/* Employee name */
.rep-table td.cell-name {
  font-size: 0.75rem;
  color: #222;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background: #fdfdfd;
  font-weight: 500;
}

/* Day cells */
.rep-table td.cell-day {
  text-align: center;
  min-height: 28px;
  position: relative;
}
.rep-table td.cell-day.weekend {
  background: #fefcf3;
}
.rep-table td.cell-day.empty-cell {
  color: #d0d0d0;
}

/* Function group border */
.rep-table tr.fn-group-first td {
  border-top: 2px solid #adb5bd;
}

/* Horaire badge */
.rep-badge {
  display: inline-block;
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 0.66rem;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  line-height: 1.3;
}

/* Etage / groupe code (combined as "1-B") */
.rep-etage {
  font-size: 0.62rem;
  color: #555;
  font-weight: 600;
  margin-left: 2px;
}
/* Module tag (shown when employee works outside home module) */
.rep-mod-tag {
  font-size: 0.6rem;
  color: #c0392b;
  font-style: italic;
  font-weight: 600;
  margin-left: 2px;
}

/* Notes indicator */
.rep-notes {
  font-size: 0.58rem;
  color: #999;
  display: block;
  line-height: 1.1;
  max-width: 80px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin: 0 auto;
}

/* Absent */
.rep-absent {
  text-decoration: line-through;
  opacity: 0.5;
}

/* Module colors */
.rep-mod-M1  { background: #2196F3; }
.rep-mod-M2  { background: #4CAF50; }
.rep-mod-M3  { background: #FF9800; }
.rep-mod-M4  { background: #9C27B0; }
.rep-mod-NUIT { background: #37474F; }
.rep-mod-POOL { background: #795548; }
.rep-mod-RS  { background: #607D8B; }
.rep-mod-DEFAULT { background: #6c757d; }

/* Legend badges */
.rep-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}

/* Print */
@media print {
  .admin-sidebar, .admin-topbar, .sidebar-overlay,
  #repPrevWeek, #repNextWeek, #repToday, #repDatePicker, #repPrint,
  #repPlanningStatus,
  .topbar-search, .topbar-right { display: none !important; }
  .admin-main { margin-left: 0 !important; }
  .admin-content { padding: 0.5rem !important; }
  .rep-grid-container { overflow: visible; }
  .rep-table { min-width: 0; font-size: 0.68rem; }
  .rep-module-section { page-break-inside: avoid; }
  .rep-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-module-header { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-table td.cell-day.weekend { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .rep-table td.cell-fn { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>

<script<?= nonce() ?>>
(function() {

  let currentWeekISO = <?= json_encode($weekIso) ?>;
  let data = {
    success: true,
    week_start: <?= json_encode($weekStart) ?>,
    week_end: <?= json_encode($weekEnd) ?>,
    week_label: <?= json_encode($weekLabel) ?>,
    week_iso: <?= json_encode($weekIso) ?>,
    days: <?= json_encode(array_values($repDays), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modules: <?= json_encode(array_values($repModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    fonctions: <?= json_encode(array_values($repFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    horaires: <?= json_encode(array_values($repHoraires), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    plannings: <?= json_encode(array_values($repPlannings), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    assignments: <?= json_encode(array_values($repAssignments), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    users: <?= json_encode(array_values($repUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
  };

  // ─── ISO week helpers ───
  function dateToStr(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function getMondayOfISOWeek(isoWeek) {
    const m = isoWeek.match(/^(\d{4})-W(\d{2})$/);
    if (!m) return null;
    const yr = parseInt(m[1]), wk = parseInt(m[2]);
    const jan4 = new Date(yr, 0, 4);
    const dow = jan4.getDay() || 7;
    const week1Mon = new Date(jan4);
    week1Mon.setDate(jan4.getDate() - dow + 1);
    const target = new Date(week1Mon);
    target.setDate(week1Mon.getDate() + (wk - 1) * 7);
    return target;
  }

  // ─── Load data ───
  async function loadWeek(weekOrDate) {
    const params = {};
    if (weekOrDate && weekOrDate.includes('-W')) {
      params.semaine = weekOrDate;
    } else if (weekOrDate) {
      params.date = weekOrDate;
    }

    document.getElementById('repGrid').innerHTML =
      '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Chargement...</div>';

    const res = await adminApiPost('admin_get_repartition', params);
    if (!res.success) {
      document.getElementById('repGrid').innerHTML =
        '<div class="alert alert-danger">Erreur : ' + escapeHtml(res.message || 'Erreur inconnue') + '</div>';
      return;
    }

    data = res;
    currentWeekISO = res.week_iso;
    document.getElementById('repWeekLabel').textContent = res.week_label;
    document.getElementById('repDatePicker').value = res.week_start;

    // Planning status
    const statusEl = document.getElementById('repPlanningStatus');
    if (res.plannings && res.plannings.length) {
      const colors = { brouillon: 'secondary', provisoire: 'info', final: 'success' };
      const badges = res.plannings.map(p =>
        '<span class="badge bg-' + (colors[p.statut] || 'secondary') + ' me-1">' +
        escapeHtml(p.mois_annee) + ' : ' + escapeHtml(p.statut) + '</span>'
      ).join('');
      statusEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Planning(s) : ' + badges;
    } else {
      statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Aucun planning pour cette periode</span>';
    }

    renderLegend();
    renderGrid();
  }

  // ─── Legend ───
  function renderLegend() {
    const el = document.getElementById('repLegend');
    let html = '<span class="text-muted fw-medium">Légende :</span>';
    (data.horaires || []).forEach(function(h) {
      const bg = h.couleur || '#6c757d';
      html += '<span class="rep-legend-item">' +
        '<span class="rep-badge" style="background:' + escapeHtml(bg) + '">' + escapeHtml(h.code) + '</span>' +
        '<span class="text-muted">' + escapeHtml((h.heure_debut || '').substring(0, 5)) + '-' + escapeHtml((h.heure_fin || '').substring(0, 5)) + '</span>' +
        '</span>';
    });
    el.innerHTML = html;
  }

  // ─── Build employee-centric index grouped by HOME module ───
  function buildIndex() {
    var assignByUser = {};
    (data.assignments || []).forEach(function(a) {
      var uid = a.user_id;
      if (!assignByUser[uid]) assignByUser[uid] = {};
      var dt = a.date_jour;
      if (!assignByUser[uid][dt]) assignByUser[uid][dt] = [];
      assignByUser[uid][dt].push(a);
    });

    var idx = {};
    (data.users || []).forEach(function(u) {
      var mc  = u.home_module_code || '_NONE';
      var fc  = u.fonction_code || '_NONE';
      var uid = u.id;

      if (!idx[mc]) idx[mc] = {};
      if (!idx[mc][fc]) idx[mc][fc] = {};

      idx[mc][fc][uid] = {
        user_id: uid,
        prenom: u.prenom,
        nom: u.nom,
        employee_id: u.employee_id,
        fonction_code: fc,
        fonction_nom: u.fonction_nom || fc,
        fonction_ordre: u.fonction_ordre || 999,
        home_module_code: mc,
        home_module_nom: u.home_module_nom || '',
        home_module_ordre: u.home_module_ordre || 999,
        days: assignByUser[uid] || {}
      };
    });

    return idx;
  }

  // ─── Render a single day cell for one employee ───
  function renderDayCell(entries, homeModuleCode) {
    if (!entries || entries.length === 0) {
      return '<span class="rep-empty-dash">—</span>';
    }
    return entries.map(function(a) {
      var bg   = a.horaire_couleur || '#6c757d';
      var code = escapeHtml(a.horaire_code || '?');
      var cls  = a.statut === 'absent' ? ' rep-absent' : '';
      var html = '<span class="' + cls + '">';
      html += '<span class="rep-badge" style="background:' + bg + '">' + code + '</span>';
      if (a.etage_code || a.groupe_code) {
        var loc = a.etage_code && a.groupe_code
          ? escapeHtml(a.etage_code) + '-' + escapeHtml(a.groupe_code)
          : escapeHtml(a.etage_code || a.groupe_code);
        html += '<span class="rep-etage">' + loc + '</span>';
      }
      if (a.module_code && homeModuleCode && a.module_code !== homeModuleCode && homeModuleCode !== '_NONE') {
        html += '<span class="rep-mod-tag">' + escapeHtml(a.module_code) + '</span>';
      }
      html += '</span>';
      if (a.notes) {
        html += '<span class="rep-notes" title="' + escapeHtml(a.notes) + '">' + escapeHtml(a.notes) + '</span>';
      }
      return html;
    }).join(' ');
  }

  // ─── Day column headers ───
  function dayHeaders() {
    return (data.days || []).map(function(d) {
      var cls = d.is_weekend ? 'col-day weekend' : 'col-day';
      return '<th class="' + cls + '">' + escapeHtml(d.label) + '</th>';
    }).join('');
  }

  // ─── Render one module section ───
  function renderModuleSection(mod, modData, days) {
    var modColors = {
      'M1': 'rep-mod-M1', 'M2': 'rep-mod-M2', 'M3': 'rep-mod-M3',
      'M4': 'rep-mod-M4', 'NUIT': 'rep-mod-NUIT', 'POOL': 'rep-mod-POOL',
      'RS': 'rep-mod-RS'
    };
    var colorCls = modColors[mod.code] || 'rep-mod-DEFAULT';

    var fnCodes = Object.keys(modData);
    var fnMeta = {};
    fnCodes.forEach(function(fc) {
      var firstUser = modData[fc][Object.keys(modData[fc])[0]];
      fnMeta[fc] = {
        code: fc,
        nom: firstUser ? firstUser.fonction_nom : fc,
        ordre: firstUser ? firstUser.fonction_ordre : 999
      };
    });
    fnCodes.sort(function(a, b) { return (fnMeta[a].ordre || 999) - (fnMeta[b].ordre || 999); });

    var empCount = 0;
    fnCodes.forEach(function(fc) { empCount += Object.keys(modData[fc]).length; });

    var html = '<div class="rep-module-section">';
    html += '<div class="rep-module-header ' + colorCls + '">' +
      '<i class="bi bi-building"></i> ' + escapeHtml(mod.nom || mod.code) +
      ' <span class="badge">' + empCount + ' employé(s)</span></div>';
    html += '<table class="rep-table"><thead><tr>' +
      '<th class="col-fn">Fonction</th>' +
      '<th class="col-name">Employé</th>' +
      dayHeaders() +
      '</tr></thead><tbody>';

    fnCodes.forEach(function(fc) {
      var users = Object.values(modData[fc]).sort(function(a, b) {
        return (a.nom || '').localeCompare(b.nom || '');
      });

      users.forEach(function(u, i) {
        var trCls = i === 0 ? ' class="fn-group-first"' : '';
        html += '<tr' + trCls + '>';

        if (i === 0) {
          html += '<td class="cell-fn" rowspan="' + users.length + '">' +
            escapeHtml(fnMeta[fc].nom || fc) + '</td>';
        }

        var fullName = (u.prenom || '') + ' ' + (u.nom || '');
        html += '<td class="cell-name" title="' + escapeHtml(fullName) + '">' + escapeHtml(fullName) + '</td>';

        days.forEach(function(d) {
          var entries = u.days[d.date] || [];
          var weCls   = d.is_weekend ? ' weekend' : '';
          var emptCls = entries.length === 0 ? ' empty-cell' : '';
          html += '<td class="cell-day' + weCls + emptCls + '">' + renderDayCell(entries, u.home_module_code) + '</td>';
        });

        html += '</tr>';
      });
    });

    html += '</tbody></table></div>';
    return html;
  }

  // ─── Render the full grid ───
  function renderGrid() {
    var idx    = buildIndex();
    var modules   = data.modules || [];
    var days      = data.days || [];
    var html      = '';

    var rsRuvCodes = ['RS', 'RUV'];
    var rsData = {};
    Object.keys(idx).forEach(function(mc) {
      rsRuvCodes.forEach(function(fc) {
        if (idx[mc][fc]) {
          if (!rsData[fc]) rsData[fc] = {};
          Object.keys(idx[mc][fc]).forEach(function(uid) {
            rsData[fc][uid] = idx[mc][fc][uid];
          });
        }
      });
    });

    if (Object.keys(rsData).length > 0) {
      html += renderModuleSection(
        { code: 'RS', nom: 'Direction / Responsables' },
        rsData,
        days
      );
    }

    if (idx['_NONE']) {
      var poolData = {};
      Object.keys(idx['_NONE']).forEach(function(fc) {
        if (rsRuvCodes.indexOf(fc) === -1) {
          poolData[fc] = idx['_NONE'][fc];
        }
      });
      if (Object.keys(poolData).length > 0) {
        html += renderModuleSection(
          { code: 'POOL', nom: 'Pool / Non assigné' },
          poolData,
          days
        );
      }
    }

    modules.forEach(function(mod) {
      var modData = idx[mod.code];
      if (!modData) return;

      var filtered = {};
      Object.keys(modData).forEach(function(fc) {
        if (rsRuvCodes.indexOf(fc) === -1) {
          filtered[fc] = modData[fc];
        }
      });

      if (Object.keys(filtered).length === 0) return;

      html += renderModuleSection(mod, filtered, days);
    });

    document.getElementById('repGrid').innerHTML = html ||
      '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x rep-empty-state-icon"></i>Aucune donnée pour cette semaine.</div>';
  }

  // ─── Event listeners ───
  document.getElementById('repPrevWeek').addEventListener('click', function() {
    if (!currentWeekISO) return;
    var mon = getMondayOfISOWeek(currentWeekISO);
    if (mon) {
      mon.setDate(mon.getDate() - 7);
      loadWeek(dateToStr(mon));
    }
  });

  document.getElementById('repNextWeek').addEventListener('click', function() {
    if (!currentWeekISO) return;
    var mon = getMondayOfISOWeek(currentWeekISO);
    if (mon) {
      mon.setDate(mon.getDate() + 7);
      loadWeek(dateToStr(mon));
    }
  });

  document.getElementById('repToday').addEventListener('click', function() {
    loadWeek(null);
  });

  document.getElementById('repDatePicker').addEventListener('change', function(e) {
    if (e.target.value) loadWeek(e.target.value);
  });

  document.getElementById('repPrint').addEventListener('click', function() {
    window.print();
  });

  // ─── Init — render synchronously from injected data ───
  function initRepartitionPage() {
    renderGrid();
  }

  window.initRepartitionPage = initRepartitionPage;

})();
</script>
