<?php
/**
 * Répartition Admin — Spocspace Care
 *
 * VERSION 4.0 — Réécriture fidèle à la maquette `spocspace-repartition`
 * (mai 2026) : design Spocspace Care, modal hero unifié, filtre modules,
 * stats bar, légende inline, export PNG/JPEG/ZIP via html2canvas + JSZip.
 *
 * Hooks JS exposés :
 *   - window.initRepartitionPage()
 *   - data injectée côté serveur (semaine + assignations)
 *
 * APIs consommées (inchangées) :
 *   - admin_get_repartition (params: semaine | date)
 *   - admin_save_repartition_cell
 *   - admin_delete_repartition_cell
 *   - admin_mark_absent_repartition
 */

// ─── Données serveur — semaine courante ─────────────────────────────────────
$dto = new DateTime();
$dow = (int) $dto->format('N');
$dto->modify('-' . ($dow - 1) . ' days');
$weekStart = $dto->format('Y-m-d');

$dtoStart = new DateTime($weekStart);
$dtoEnd   = clone $dtoStart;
$dtoEnd->modify('+6 days');
$weekEnd  = $dtoEnd->format('Y-m-d');

$weekNum = (int) $dtoStart->format('W');
$year    = (int) $dtoStart->format('o');

$frMonths = [
    1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
    5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
    9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
];

$startDay   = (int) $dtoStart->format('j');
$endDay     = (int) $dtoEnd->format('j');
$startMonth = $frMonths[(int) $dtoStart->format('n')];
$endMonth   = $frMonths[(int) $dtoEnd->format('n')];

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
    "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.employee_id,
            f.id AS fonction_id, f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            hm.id AS home_module_id, hm.code AS home_module_code
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules hm ON hm.id = um.module_id
     WHERE u.is_active = 1
     ORDER BY f.ordre, u.nom"
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
        "SELECT pa.id AS assignation_id, pa.planning_id, pa.date_jour, pa.user_id,
                pa.horaire_type_id, pa.module_id, pa.groupe_id, pa.etage_id,
                pa.statut, pa.notes, pa.updated_at,
                u.prenom AS user_prenom, u.nom AS user_nom, u.photo AS user_photo, u.taux AS user_taux,
                f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin, ht.duree_effective,
                m.code AS module_code,
                g.code AS groupe_code,
                e.code AS etage_code
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         LEFT JOIN groupes g ON g.id = pa.groupe_id
         LEFT JOIN etages e ON e.id = pa.etage_id
         WHERE pa.planning_id IN ($phPlan)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour, m.ordre, f.ordre, u.nom",
        $qParams
    );
}

// Modified IDs
$repModifiedIds = [];
if ($repAssignments) {
    $aIds = array_column($repAssignments, 'assignation_id');
    $phA  = implode(',', array_fill(0, count($aIds), '?'));
    $modRows = Db::fetchAll("SELECT DISTINCT planning_assignation_id FROM planning_modifications WHERE planning_assignation_id IN ($phA)", $aIds);
    $repModifiedIds = array_column($modRows, 'planning_assignation_id');
}

// Absences
$repAbsences = Db::fetchAll(
    "SELECT user_id, date_debut, date_fin, type, motif FROM absences WHERE statut = 'valide' AND date_debut <= ? AND date_fin >= ? ORDER BY date_debut",
    [$weekEnd, $weekStart]
);

$frDays  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$frFull  = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$repDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $dtoStart;
    $d->modify("+$i days");
    $repDays[] = [
        'date'       => $d->format('Y-m-d'),
        'label'      => $frDays[$i] . ' ' . $d->format('d'),
        'full_name'  => $frFull[$i],
        'short'      => $frDays[$i],
        'day_num'    => $d->format('d'),
        'month_name' => $frMonths[(int) $d->format('n')],
        'year'       => (int) $d->format('Y'),
        'is_weekend' => in_array($d->format('N'), ['6', '7']),
    ];
}

$todayIso = date('Y-m-d');
?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- VENDOR LIBS — html2canvas (export image) + JSZip (export multiple)   -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<script<?= nonce() ?> src="/newspocspace/assets/js/vendor/html2canvas.min.js?v=<?= APP_VERSION ?>"></script>
<script<?= nonce() ?> src="/newspocspace/assets/js/vendor/jszip.min.js?v=<?= APP_VERSION ?>"></script>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- SUBHEADER — crumbs + week-nav + view-toggle + actions                 -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div class="ss-rep-subheader sticky top-16 z-10 -mx-4 lg:-mx-6 px-4 lg:px-6 py-2.5 bg-surface border-b border-line flex flex-wrap items-center gap-2.5">
  <!-- Crumbs / état planning -->
  <div class="flex items-center gap-1.5 text-[12.5px] text-muted">
    <span>Planning(s)</span>
    <span class="text-line-2">›</span>
    <strong id="repPlanningMonth" class="text-ink font-medium font-mono"><?= h($moisStart) ?></strong>
    <span class="text-line-2">›</span>
    <span id="repPlanningStatus" class="bg-ok-bg text-ok border border-ok-line text-[10.5px] font-semibold px-2 py-0.5 rounded-full lowercase tracking-[0.04em]">—</span>
  </div>

  <!-- Week navigator -->
  <div class="inline-flex items-center bg-surface-2 border border-line rounded-lg p-0.5">
    <button id="repPrevWeek" type="button" class="w-7 h-7 grid place-items-center rounded-md text-ink-2 hover:bg-surface transition" title="Précédent">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <span id="repWeekLabel" class="px-3 text-[12.5px] font-semibold text-ink font-display -tracking-[0.01em] whitespace-nowrap"><?= h($weekLabel) ?></span>
    <button id="repNextWeek" type="button" class="w-7 h-7 grid place-items-center rounded-md text-ink-2 hover:bg-surface transition" title="Suivant">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
  </div>

  <button id="repToday" type="button" class="px-2.5 py-1.5 text-[12px] font-medium bg-teal-50 text-teal-700 border border-teal-200 rounded-md hover:bg-teal-100 transition">Aujourd'hui</button>

  <!-- View toggle -->
  <div id="repViewToggle" class="inline-flex bg-surface-2 border border-line rounded-lg p-0.5">
    <button type="button" data-view="week" class="ss-rep-view-btn on px-3 py-1.5 text-[12px] font-medium text-muted rounded-md inline-flex items-center gap-1.5 transition">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Semaine
    </button>
    <button type="button" data-view="day" class="ss-rep-view-btn px-3 py-1.5 text-[12px] font-medium text-muted rounded-md inline-flex items-center gap-1.5 transition">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16h6"/></svg>
      Jour
    </button>
  </div>

  <div class="flex-1"></div>

  <button id="repPrint" type="button" class="ss-rep-btn-ghost inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[12.5px] font-medium text-ink-2 hover:bg-surface-2 transition" title="Imprimer">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
    Imprimer
  </button>
  <button id="repExportBtn" type="button" class="ss-rep-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[12.5px] font-medium text-ink-2 bg-surface border border-line hover:bg-surface-2 hover:border-line-2 transition">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
    Exporter
  </button>
  <button id="repToggleEdit" type="button" class="ss-rep-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[12.5px] font-medium text-ink-2 bg-surface border border-line hover:bg-surface-2 hover:border-line-2 transition">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
    <span id="repEditLabel">Éditer</span>
  </button>
  <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border border-line rounded-md bg-surface text-[12.5px] text-ink font-mono font-medium">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    <input type="date" id="repDatePicker" value="<?= h($weekStart) ?>" class="bg-transparent outline-none border-0 text-ink font-mono text-[12.5px] cursor-pointer">
  </div>
</div>

<!-- Edit mode banner -->
<div id="repEditBanner" class="hidden sticky top-[6.75rem] z-[9] -mx-4 lg:-mx-6 px-4 lg:px-6 py-2.5 bg-warn-bg border-b border-warn-line text-warn text-[12.5px] font-medium items-center gap-2.5">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
  <span><strong class="font-semibold">Mode édition activé</strong> — glissez-déposez les cellules pour les déplacer entre modules. Cliquez sur une cellule pour ouvrir l'éditeur.</span>
  <span class="flex-1"></span>
  <button type="button" id="repExitEdit" class="px-2.5 py-1 text-[11.5px] font-semibold bg-warn text-white rounded-md hover:opacity-90 transition">Quitter l'édition</button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- CONTENT                                                                -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div class="ss-rep-content py-4 flex flex-col gap-4">

  <!-- Module filter (chips horizontaux scrollables) -->
  <div id="repModFilter" class="bg-surface border border-line rounded-lg shadow-sp-sm px-2.5 py-2 flex items-center gap-1.5 overflow-x-auto ss-rep-scrollbar">
    <span class="text-[10px] tracking-[0.12em] uppercase text-muted font-bold pl-1.5 pr-1 shrink-0">Filtrer</span>
    <!-- chips injectés en JS -->
  </div>

  <!-- Stats bar -->
  <div id="repStatsBar" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2.5">
    <!-- cards injectées en JS -->
  </div>

  <!-- Grid (vue semaine) + Day header (vue jour) -->
  <div id="repGrid" class="flex flex-col gap-4">
    <div class="text-center py-12 text-muted">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-2 opacity-40"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      Chargement…
    </div>
  </div>

  <!-- Légende horaires (inline, en bas) -->
  <div id="repLegend" class="bg-surface border border-line rounded-xl shadow-sp-sm px-4 py-3.5">
    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
      <span class="text-[10.5px] tracking-[0.12em] uppercase text-muted font-bold mr-1">Horaires</span>
      <!-- légende injectée en JS depuis data.horaires -->
    </div>
  </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- MODAL : édition d'une cellule (hero unifié)                            -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div id="repCellModal" class="ss-rep-modal-overlay fixed inset-0 z-[100] items-center justify-center p-5 bg-[rgba(13,42,38,0.55)] backdrop-blur-[4px]">
  <div id="repCellModalCard" class="ss-rep-modal-card bg-surface rounded-2xl shadow-sp-lg w-[520px] max-w-full max-h-[calc(100vh-40px)] overflow-hidden flex flex-col">

    <!-- Hero -->
    <div class="ss-rep-modal-hero relative overflow-hidden text-white px-5 py-4 shrink-0">
      <div class="relative z-[1] flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <div id="repModalAvatar" class="w-[46px] h-[46px] rounded-xl grid place-items-center font-display font-semibold text-[#0d2a26] text-[17px] shadow-[0_6px_18px_rgba(0,0,0,0.2)] shrink-0 bg-[linear-gradient(135deg,#3da896,#7dd3a8)]">CB</div>
          <div class="min-w-0 flex-1">
            <div class="text-[9.5px] tracking-[0.14em] uppercase text-[#a8e6c9] font-semibold mb-0.5">Édition d'un poste</div>
            <h3 id="repModalName" class="font-display font-semibold text-[19px] -tracking-[0.01em] leading-tight truncate">—</h3>
            <div id="repModalRole" class="text-[11.5px] text-[#cfe0db] mt-px truncate">—</div>
          </div>
        </div>
        <button type="button" id="repModalClose" class="pl-cell-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Meta : Date · Module · Poste -->
      <div class="ss-rep-modal-meta relative z-[1] flex gap-2 mt-3.5 pt-3 border-t border-white/15">
        <div class="ss-rep-modal-meta-item flex items-center gap-2 flex-1 min-w-0 px-2.5 py-1.5 rounded-lg bg-white/[0.08] border border-white/10">
          <div class="w-6 h-6 rounded-md bg-white/15 grid place-items-center shrink-0">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
          <div class="flex flex-col min-w-0 flex-1">
            <span class="text-[8.5px] tracking-[0.12em] uppercase text-[#a8c4be] font-semibold leading-tight">Date</span>
            <span id="repModalDate" class="text-[11.5px] font-semibold font-mono text-white leading-tight truncate">—</span>
          </div>
        </div>
        <div class="ss-rep-modal-meta-item flex items-center gap-2 flex-1 min-w-0 px-2.5 py-1.5 rounded-lg bg-white/[0.08] border border-white/10">
          <div class="w-6 h-6 rounded-md bg-white/15 grid place-items-center shrink-0">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
          </div>
          <div class="flex flex-col min-w-0 flex-1">
            <span class="text-[8.5px] tracking-[0.12em] uppercase text-[#a8c4be] font-semibold leading-tight">Module</span>
            <span id="repModalModuleLabel" class="text-[11.5px] font-semibold font-mono text-white leading-tight truncate">—</span>
          </div>
        </div>
        <div class="ss-rep-modal-meta-item flex items-center gap-2 flex-1 min-w-0 px-2.5 py-1.5 rounded-lg bg-white/[0.08] border border-white/10">
          <div class="w-6 h-6 rounded-md bg-white/15 grid place-items-center shrink-0">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          </div>
          <div class="flex flex-col min-w-0 flex-1">
            <span class="text-[8.5px] tracking-[0.12em] uppercase text-[#a8c4be] font-semibold leading-tight">Poste</span>
            <span id="repModalPosteLabel" class="text-[11.5px] font-semibold font-mono text-white leading-tight truncate">—</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Body scrollable -->
    <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 flex flex-col gap-3.5 ss-rep-scrollbar">

      <!-- Statut -->
      <div class="ss-rep-section-title">Statut</div>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" data-status="present" class="ss-rep-status-btn ss-rep-status-present on flex items-center gap-2.5 px-3.5 py-3 rounded-md border-[1.5px] border-line-2 bg-surface text-left transition">
          <div class="ss-rep-status-ic w-[30px] h-[30px] rounded-lg grid place-items-center text-white shrink-0 bg-ok">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <div class="flex flex-col min-w-0">
            <span class="ss-rep-status-t text-[12.5px] font-semibold text-ink leading-tight">Présent·e</span>
            <span class="text-[10.5px] text-muted mt-px">Affecter à un poste</span>
          </div>
        </button>
        <button type="button" data-status="absent" class="ss-rep-status-btn ss-rep-status-absent flex items-center gap-2.5 px-3.5 py-3 rounded-md border-[1.5px] border-line-2 bg-surface text-left transition">
          <div class="ss-rep-status-ic w-[30px] h-[30px] rounded-lg grid place-items-center text-white shrink-0 bg-danger">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93 19.07 19.07"/></svg>
          </div>
          <div class="flex flex-col min-w-0">
            <span class="ss-rep-status-t text-[12.5px] font-semibold text-ink leading-tight">Absent·e</span>
            <span class="text-[10.5px] text-muted mt-px">Marquer une absence</span>
          </div>
        </button>
      </div>

      <!-- Motif d'absence (visible si statut=absent) -->
      <div id="repModalAbsentBlock" class="hidden flex-col gap-2 px-3.5 py-3 bg-danger-bg border border-danger-line rounded-md">
        <div class="text-[10.5px] tracking-[0.08em] uppercase text-danger font-bold">Motif de l'absence</div>
        <div class="flex flex-wrap gap-1.5">
          <button type="button" data-reason="maladie"        class="ss-rep-reason-chip">Maladie</button>
          <button type="button" data-reason="accident"       class="ss-rep-reason-chip">Accident</button>
          <button type="button" data-reason="enfant_malade"  class="ss-rep-reason-chip">Enfant malade</button>
          <button type="button" data-reason="vacances"       class="ss-rep-reason-chip">Vacances</button>
          <button type="button" data-reason="formation"      class="ss-rep-reason-chip">Formation</button>
          <button type="button" data-reason="conge_special"  class="ss-rep-reason-chip">Congé spécial</button>
          <button type="button" data-reason="autre"          class="ss-rep-reason-chip">Autre</button>
        </div>
      </div>

      <!-- Horaire — grille visuelle -->
      <div class="ss-rep-section-title">Horaire</div>
      <div id="repModalShiftGrid" class="grid grid-cols-4 gap-1.5">
        <!-- shift options injectées en JS -->
      </div>

      <!-- Affectation : module + étage/groupe -->
      <div class="ss-rep-section-title">Affectation</div>
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] tracking-[0.06em] uppercase text-muted font-semibold flex items-center gap-1.5">Module</label>
          <select id="repModalModule" class="ss-rep-input"></select>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] tracking-[0.06em] uppercase text-muted font-semibold flex items-center gap-1.5">Étage / Groupe</label>
          <select id="repModalGroupe" class="ss-rep-input"></select>
        </div>
      </div>

      <!-- Statut technique (présent/remplacé/intérim/...) -->
      <div class="flex flex-col gap-1.5">
        <label class="text-[11px] tracking-[0.06em] uppercase text-muted font-semibold flex items-center gap-1.5">Statut technique</label>
        <select id="repModalStatut" class="ss-rep-input">
          <option value="present">Présent</option>
          <option value="absent">Absent</option>
          <option value="remplace">Remplacé</option>
          <option value="interim">Intérim</option>
          <option value="entraide">Entraide</option>
          <option value="repos">Repos</option>
          <option value="vacant">Vacant</option>
        </select>
      </div>

      <!-- Commentaire -->
      <div class="flex flex-col gap-1.5">
        <label class="text-[11px] tracking-[0.06em] uppercase text-muted font-semibold flex items-center gap-1.5">Commentaire (optionnel)</label>
        <input id="repModalNotes" type="text" maxlength="500" placeholder="Note interne, consigne particulière…" class="ss-rep-input">
      </div>

    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between gap-2 px-5 py-3.5 bg-surface-2 border-t border-line shrink-0">
      <div class="flex gap-1.5">
        <button type="button" id="repModalDuplicate" class="ss-rep-btn-icon" title="Dupliquer sur d'autres jours (à venir)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
        <button type="button" id="repModalDelete" class="ss-rep-btn-icon ss-rep-btn-icon-danger" title="Supprimer le poste">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
      </div>
      <div class="flex gap-2">
        <button type="button" id="repModalCancel" class="ss-rep-btn px-3.5 py-2 rounded-md text-[12.5px] font-medium text-ink-2 bg-surface border border-line hover:bg-surface-2 transition">Annuler</button>
        <button type="button" id="repModalSave" class="ss-rep-btn-primary inline-flex items-center gap-1.5 px-3.5 py-2 rounded-md text-[12.5px] font-semibold text-white bg-teal-600 border border-teal-600 hover:bg-teal-700 transition">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          Valider
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- MODAL : Export PNG/JPEG/ZIP                                            -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div id="repExportModal" class="ss-rep-modal-overlay fixed inset-0 z-[100] items-center justify-center p-5 bg-[rgba(13,42,38,0.55)] backdrop-blur-[4px]">
  <div class="bg-surface rounded-2xl shadow-sp-lg w-[680px] max-w-full max-h-[calc(100vh-40px)] overflow-hidden flex flex-col">

    <!-- Hero -->
    <div class="ss-rep-modal-hero relative overflow-hidden text-white px-6 py-5 shrink-0">
      <div class="relative z-[1] flex items-center justify-between gap-3.5">
        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded-xl bg-white/15 border border-white/20 grid place-items-center shrink-0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          </div>
          <div>
            <div class="text-[10px] tracking-[0.14em] uppercase text-[#a8e6c9] font-semibold mb-0.5">Exporter la répartition</div>
            <h3 class="font-display font-semibold text-[21px] -tracking-[0.01em] leading-tight">Choisir modules et jours</h3>
            <div class="text-[12px] text-[#cfe0db] mt-px">Une image sera générée pour chaque module × chaque jour sélectionné</div>
          </div>
        </div>
        <button type="button" id="repExportClose" class="pl-cell-close" aria-label="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-5 ss-rep-scrollbar">

      <!-- Modules -->
      <div>
        <div class="flex items-center justify-between gap-2 mb-2.5">
          <span class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-bold">Modules</span>
          <button type="button" id="repExportToggleAllMod" class="text-[10.5px] font-semibold text-teal-600 hover:bg-teal-50 px-1.5 py-0.5 rounded transition">Tout / Aucun</button>
        </div>
        <div id="repExportModuleList" class="flex flex-col gap-1 bg-surface-2 border border-line rounded-lg p-1.5 max-h-[280px] overflow-y-auto ss-rep-scrollbar"></div>
      </div>

      <!-- Jours + Format -->
      <div>
        <div class="flex items-center justify-between gap-2 mb-2.5">
          <span class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-bold">Jours</span>
          <button type="button" id="repExportToggleAllDay" class="text-[10.5px] font-semibold text-teal-600 hover:bg-teal-50 px-1.5 py-0.5 rounded transition">Tout / Aucun</button>
        </div>
        <div id="repExportDayList" class="flex flex-col gap-1 bg-surface-2 border border-line rounded-lg p-1.5 max-h-[180px] overflow-y-auto ss-rep-scrollbar mb-3.5"></div>

        <div class="text-[10.5px] tracking-[0.14em] uppercase text-muted font-bold mb-2.5">Format</div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" data-fmt="png" class="ss-rep-format-btn on flex items-center gap-2.5 px-3 py-2.5 rounded-md border-[1.5px] border-line-2 bg-surface text-left transition">
            <div class="ss-rep-format-ic w-[30px] h-[30px] rounded-lg bg-teal-50 text-teal-700 grid place-items-center shrink-0 transition">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <div class="flex flex-col min-w-0">
              <span class="text-[12.5px] font-semibold text-ink leading-tight">PNG</span>
              <span class="text-[10.5px] text-muted mt-px">Haute qualité</span>
            </div>
          </button>
          <button type="button" data-fmt="jpeg" class="ss-rep-format-btn flex items-center gap-2.5 px-3 py-2.5 rounded-md border-[1.5px] border-line-2 bg-surface text-left transition">
            <div class="ss-rep-format-ic w-[30px] h-[30px] rounded-lg bg-teal-50 text-teal-700 grid place-items-center shrink-0 transition">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <div class="flex flex-col min-w-0">
              <span class="text-[12.5px] font-semibold text-ink leading-tight">JPEG</span>
              <span class="text-[10.5px] text-muted mt-px">Plus léger</span>
            </div>
          </button>
        </div>
      </div>

    </div>

    <!-- Récap -->
    <div class="px-6 py-3.5 bg-surface-2 border-t border-b border-line flex items-center justify-between gap-3.5">
      <div class="flex flex-col gap-px">
        <span class="text-[9.5px] tracking-[0.12em] uppercase text-muted font-bold">Images</span>
        <span id="repExportRecapCount" class="font-display font-semibold text-[18px] text-teal-900 leading-none">0</span>
      </div>
      <span class="text-muted-2">→</span>
      <div id="repExportRecapFile" class="flex-1 font-mono text-[11.5px] text-ink-2 bg-surface px-3 py-2 border border-line rounded-md truncate">—</div>
    </div>

    <div class="flex items-center justify-end gap-2 px-6 py-3.5 shrink-0">
      <button type="button" id="repExportCancel" class="ss-rep-btn px-3.5 py-2 rounded-md text-[12.5px] font-medium text-ink-2 bg-surface border border-line hover:bg-surface-2 transition">Annuler</button>
      <button type="button" id="repExportLaunch" class="ss-rep-btn-primary inline-flex items-center gap-1.5 px-3.5 py-2 rounded-md text-[12.5px] font-semibold text-white bg-teal-600 border border-teal-600 hover:bg-teal-700 transition">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Lancer l'export
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- OVERLAY : progression de l'export                                      -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div id="repExportProgress" class="ss-rep-progress-overlay fixed inset-0 z-[200] items-center justify-center bg-[rgba(13,42,38,0.65)] backdrop-blur-[4px]">
  <div id="repExportProgressCard" class="bg-surface rounded-2xl shadow-sp-lg p-7 w-[440px] max-w-[calc(100vw-32px)] flex flex-col gap-4">
    <h3 class="font-display font-semibold text-[18px] text-teal-900 -tracking-[0.01em] flex items-center gap-2.5">
      <span class="ss-rep-spin shrink-0"></span>
      <span id="repExportTitle">Export en cours…</span>
    </h3>
    <div id="repExportCurrent" class="text-[12.5px] text-ink-2 px-3.5 py-2.5 bg-surface-2 border border-line rounded-md font-mono truncate">Préparation…</div>
    <div class="h-2 bg-surface-3 rounded-full overflow-hidden">
      <div id="repExportBar" class="h-full bg-[linear-gradient(90deg,#2d8074,#1f6359)] rounded-full transition-all duration-300" style="width:0%"></div>
    </div>
    <div class="flex justify-between text-[12px] text-muted">
      <span><strong id="repExportDone" class="text-ink font-mono">0</strong> / <strong id="repExportTotal" class="text-ink font-mono">0</strong> images générées</span>
      <span id="repExportPct" class="font-mono">0%</span>
    </div>
    <div id="repExportDoneMsg" class="hidden items-center gap-2.5 px-3.5 py-2.5 bg-ok-bg border border-ok-line rounded-md text-ok text-[13px] font-medium">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
      <span>Export terminé · les fichiers ont été téléchargés.</span>
    </div>
    <button type="button" id="repExportProgressClose" class="hidden self-end px-3.5 py-2 rounded-md text-[13px] font-medium border border-line bg-surface text-ink-2 hover:bg-surface-2 transition">Fermer</button>
  </div>
</div>

<!-- Zone de capture hors-écran -->
<div id="repCaptureStage" class="fixed -left-[99999px] top-0 w-[1240px] bg-transparent pointer-events-none" aria-hidden="true"></div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- STYLES — tokens spécifiques répartition (gradients modules + shifts)  -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<style<?= nonce() ?>>
/* ── Module gradients (header coloré) ─────────────────────────────── */
.ss-rep-mod-rj   .ss-rep-module-head { background: linear-gradient(135deg, #164a42 0%, #1f6359 100%); }
.ss-rep-mod-m1   .ss-rep-module-head { background: linear-gradient(135deg, #1f6359 0%, #2d8074 100%); }
.ss-rep-mod-m2   .ss-rep-module-head { background: linear-gradient(135deg, #2d4a6b 0%, #456b8e 100%); }
.ss-rep-mod-m3   .ss-rep-module-head { background: linear-gradient(135deg, #8a5a1a 0%, #b07a35 100%); }
.ss-rep-mod-m4   .ss-rep-module-head { background: linear-gradient(135deg, #5e3a78 0%, #7d5896 100%); }
.ss-rep-mod-pool .ss-rep-module-head { background: linear-gradient(135deg, #8a3a30 0%, #a85850 100%); }
.ss-rep-mod-na   .ss-rep-module-head { background: linear-gradient(135deg, #4a6661 0%, #6b8783 100%); }
.ss-rep-mod-nuit .ss-rep-module-head { background: linear-gradient(135deg, #0d2a26 0%, #324e4a 100%); }

/* Swatches dans les chips de filtre */
.ss-rep-mod-sw-rj   { background:#164a42; }
.ss-rep-mod-sw-m1   { background:#1f6359; }
.ss-rep-mod-sw-m2   { background:#2d4a6b; }
.ss-rep-mod-sw-m3   { background:#8a5a1a; }
.ss-rep-mod-sw-m4   { background:#5e3a78; }
.ss-rep-mod-sw-pool { background:#8a3a30; }
.ss-rep-mod-sw-na   { background:#6b8783; }
.ss-rep-mod-sw-nuit { background:#0d2a26; }

/* ── Module head overlay (gradient subtil blanc en surimpression) ─── */
.ss-rep-module-head { position: relative; }
.ss-rep-module-head::before {
  content: ""; position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, transparent 50%);
  pointer-events: none;
}

/* ── Shift palette (refondue selon maquette) ──────────────────────── */
.ss-rep-shift {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 30px; height: 22px; padding: 0 8px;
  font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: 700;
  border-radius: 5px; letter-spacing: 0.02em;
  border: 1px solid transparent; white-space: nowrap;
}
.ss-rep-shift-a2,
.ss-rep-shift-a3      { background:#d2e7e2; color:#164a42; border-color:rgba(22,74,66,.12); }
.ss-rep-shift-c1,
.ss-rep-shift-c2      { background:#a8d1c8; color:#0d2a26; border-color:rgba(13,42,38,.18); }
.ss-rep-shift-d1      { background:#e2ecf2; color:#3a6a8a; border-color:rgba(58,106,138,.18); }
.ss-rep-shift-d3      { background:#fbf0e1; color:#8a5a1a; border-color:rgba(138,90,26,.18); }
.ss-rep-shift-d4      { background:#fde8e6; color:#8a3a30; border-color:rgba(138,58,48,.18); }
.ss-rep-shift-s3,
.ss-rep-shift-s4      { background:#f0e8f5; color:#5e3a78; border-color:rgba(94,58,120,.16); }
.ss-rep-shift-n1      { background:#0d2a26; color:#a8e6c9; border-color:rgba(13,42,38,.4); }
.ss-rep-shift-piquet  { background:#e6ecf2; color:#2d4a6b; border-color:rgba(45,74,107,.18); }
.ss-rep-shift-default { background:#e3ebe8; color:#324e4a; border-color:rgba(50,78,74,.18); }

/* ── Table répartition ────────────────────────────────────────────── */
.ss-rep-table {
  width: 100%; border-collapse: separate; border-spacing: 0;
  font-size: 12.5px; table-layout: fixed; min-width: 2238px;
}
.ss-rep-table th, .ss-rep-table td {
  border-right: 1px solid var(--color-line);
  border-bottom: 1px solid var(--color-line);
  vertical-align: middle;
}
.ss-rep-table tbody tr:last-child td { border-bottom: 0; }

/* Header — 2 lignes : day-row puis subhead */
.ss-rep-table thead .day-row th {
  background: var(--color-surface-2); font-weight: 600; color: var(--color-ink-2);
  font-size: 12.5px; text-align: center; padding: 9px 8px 7px;
}
.ss-rep-table thead .day-row .day-name { display: block; font-size: 10px; color: var(--color-muted); letter-spacing: 0.1em; text-transform: uppercase; font-weight: 600; margin-bottom: 2px; }
.ss-rep-table thead .day-row .day-date { display: inline-flex; align-items: baseline; gap: 4px; font-family: 'Fraunces', serif; font-weight: 600; font-size: 14px; color: var(--color-teal-900); letter-spacing: -0.005em; }
.ss-rep-table thead .day-row .today { background: var(--color-teal-50); box-shadow: inset 0 -2px 0 var(--color-teal-500); }
.ss-rep-table thead .day-row .today .day-date { color: var(--color-teal-700); }
.ss-rep-table thead .day-row .today .day-name { color: var(--color-teal-600); }
.ss-rep-table thead .day-row .weekend { background: #f6f3ee; }
.ss-rep-table thead .day-row .weekend .day-name { color: #a87d3a; }

.ss-rep-table thead .subhead-row th {
  background: var(--color-surface-3); font-size: 9.5px; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--color-muted); font-weight: 600;
  padding: 6px 4px; text-align: center;
}
.ss-rep-table thead .subhead-row .today { background: var(--color-teal-50); }
.ss-rep-table thead .subhead-row .weekend { background: #f6f3ee; }

.ss-rep-table tbody td.weekend { background: #fdfcfa; }
.ss-rep-table tbody td.weekend .ss-rep-cell-etage { background: #f0ebe1; }

/* Sub-header double : Nom·Horaire | Étage */
.ss-rep-sub-double { display: flex; align-items: stretch; height: 100%; }
.ss-rep-sub-double > span { flex: 1; display: flex; align-items: center; justify-content: center; padding: 6px 4px; }
.ss-rep-sub-double > span:first-child { border-right: 1px solid var(--color-line); }

/* Colonnes sticky : Fonction + Poste */
.ss-rep-col-fonction {
  position: sticky; left: 0; z-index: 4; background: var(--color-surface);
  width: 90px; min-width: 90px; max-width: 90px; text-align: center;
  font-weight: 600; color: var(--color-ink); font-size: 11.5px;
  border-right: 1px solid var(--color-line-2) !important;
}
.ss-rep-table thead .ss-rep-col-fonction { z-index: 6; background: var(--color-surface-2); }
.ss-rep-col-fonction .label { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 4px; }
.ss-rep-col-fonction .label small { font-size: 9.5px; color: var(--color-muted); font-weight: 500; letter-spacing: 0.04em; }

.ss-rep-col-poste {
  position: sticky; left: 90px; z-index: 4; background: var(--color-surface);
  width: 48px; min-width: 48px; max-width: 48px; text-align: center;
  font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 600;
  color: var(--color-muted); border-right: 1px solid var(--color-line-2) !important;
}
.ss-rep-table thead .ss-rep-col-poste { z-index: 6; background: var(--color-surface-2); }

.ss-rep-col-day { width: auto; min-width: 300px; }

/* Cellules */
.ss-rep-table tbody td { padding: 0; height: 40px; background: var(--color-surface); transition: background 0.15s ease; position: relative; }
.ss-rep-table tbody tr:hover td:not(.ss-rep-col-fonction):not(.ss-rep-col-poste) { background: var(--color-surface-2); }

.ss-rep-cell { display: flex; align-items: stretch; height: 100%; cursor: pointer; transition: all 0.15s ease; position: relative; }
.ss-rep-cell-main { flex: 1; display: flex; align-items: center; gap: 6px; padding: 4px 8px 4px 10px; min-width: 0; border-right: 1px solid var(--color-line); }
.ss-rep-cell-name { flex: 1; min-width: 0; font-size: 12.5px; color: var(--color-ink); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ss-rep-cell-etage { width: 64px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: var(--color-muted); white-space: nowrap; text-align: center; background: var(--color-surface-3); }
.ss-rep-cell:hover .ss-rep-cell-main, .ss-rep-cell:hover .ss-rep-cell-etage { background: var(--color-teal-50); }

.ss-rep-cell.empty { cursor: pointer; }
.ss-rep-cell.empty .ss-rep-cell-main { background: repeating-linear-gradient(45deg, transparent, transparent 6px, rgba(106,131,131,.04) 6px, rgba(106,131,131,.04) 7px); }
.ss-rep-cell.empty:hover .ss-rep-cell-main { background: var(--color-teal-50); outline: 1px dashed var(--color-teal-300); outline-offset: -1px; }

/* État absent */
.ss-rep-cell.absent .ss-rep-cell-main { background: var(--color-danger-bg); border-right: 1px solid var(--color-danger-line); }
.ss-rep-cell.absent .ss-rep-cell-etage { background: #f0d4cf; color: var(--color-danger); font-weight: 600; gap: 4px; }
.ss-rep-cell.absent .ss-rep-cell-name { color: var(--color-danger); font-weight: 600; text-decoration: line-through; text-decoration-color: rgba(184,68,58,.4); text-decoration-thickness: 1px; }
.ss-rep-cell.absent .ss-rep-shift { opacity: 0.55; filter: saturate(0.6); }
.ss-rep-cell .absent-ico { width: 14px; height: 14px; border-radius: 50%; background: var(--color-danger); color: #fff; display: grid; place-items: center; flex-shrink: 0; font-size: 9px; font-weight: 700; }

/* Modifiée — petit point orange */
.ss-rep-cell.modified::after { content: ''; position: absolute; top: 4px; right: 70px; width: 6px; height: 6px; background: #FF9800; border-radius: 50%; }

/* Drag & drop (mode édition) */
.ss-rep-edit-mode .ss-rep-cell:not(.empty) { cursor: grab; }
.ss-rep-edit-mode .ss-rep-cell:not(.empty):active { cursor: grabbing; }
.ss-rep-cell.dragging { opacity: 0.4; }
.ss-rep-cell.drag-over .ss-rep-cell-main { background: var(--color-teal-100); outline: 2px dashed var(--color-teal-600); outline-offset: -2px; }
.ss-rep-module-head.drag-over-mod { outline: 3px dashed rgba(255,255,255,0.6); outline-offset: -3px; opacity: 0.85; }

/* ── Module body : scroll horizontal ──────────────────────────────── */
.ss-rep-module-body { overflow-x: auto; scrollbar-width: thin; cursor: grab; }
.ss-rep-module-body:active { cursor: grabbing; }
.ss-rep-module-body::-webkit-scrollbar { height: 8px; }
.ss-rep-module-body::-webkit-scrollbar-track { background: var(--color-surface-2); }
.ss-rep-module-body::-webkit-scrollbar-thumb { background: var(--color-line-2); border-radius: 99px; }
.ss-rep-module-body::-webkit-scrollbar-thumb:hover { background: var(--color-muted-2); }

/* ── Filtre modules (chip horizontal) ─────────────────────────────── */
.ss-rep-mf-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; background: var(--color-surface-2);
  border: 1px solid var(--color-line); border-radius: 9999px;
  font-size: 12px; font-weight: 500; color: var(--color-ink-2);
  cursor: pointer; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;
}
.ss-rep-mf-chip:hover { background: var(--color-surface); border-color: var(--color-line-2); }
.ss-rep-mf-chip.on { background: var(--color-teal-700); color: #fff; border-color: var(--color-teal-700); }
.ss-rep-mf-chip .swatch { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ss-rep-mf-chip .count {
  font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: var(--color-muted);
  background: var(--color-surface); padding: 1px 6px; border-radius: 99px;
  border: 1px solid var(--color-line);
}
.ss-rep-mf-chip.on .count { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.2); }

/* ── Stat cards ───────────────────────────────────────────────────── */
.ss-rep-stat-card { background: var(--color-surface); border: 1px solid var(--color-line); border-radius: 10px; padding: 11px 14px; box-shadow: 0 1px 2px rgba(13,42,38,0.04); }
.ss-rep-stat-card .lbl { font-size: 10.5px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-muted); font-weight: 600; margin-bottom: 4px; }
.ss-rep-stat-card .v { font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600; color: var(--color-teal-900); line-height: 1; }
.ss-rep-stat-card .v small { font-size: 11px; color: var(--color-muted); font-family: 'Outfit', sans-serif; font-weight: 400; margin-left: 3px; }
.ss-rep-stat-card .sub { font-size: 10.5px; color: var(--color-muted); margin-top: 3px; }
.ss-rep-stat-card.ok .v     { color: var(--color-ok); }
.ss-rep-stat-card.warn .v   { color: var(--color-warn); }
.ss-rep-stat-card.danger .v { color: var(--color-danger); }
.ss-rep-stat-card.info .v   { color: var(--color-info); }

/* ── Modal — overlay show + transitions ────────────────────────────── */
.ss-rep-modal-overlay { display: none; }
.ss-rep-modal-overlay.show { display: flex; animation: ssRepFadeIn 0.18s ease; }
.ss-rep-modal-overlay.show > * { animation: ssRepSlideUp 0.25s ease; }
.ss-rep-progress-overlay { display: none; }
.ss-rep-progress-overlay.show { display: flex; animation: ssRepFadeIn 0.18s ease; }
@keyframes ssRepFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes ssRepSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Modal hero — gradient teal + pseudo-elements pour fonds décoratifs */
.ss-rep-modal-hero {
  background: linear-gradient(135deg, #164a42 0%, #1f6359 50%, #2d8074 100%);
}
.ss-rep-modal-hero::before {
  content: ""; position: absolute; inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(125,211,168,0.18) 0%, transparent 55%);
  pointer-events: none; z-index: 0;
}
.ss-rep-modal-hero::after {
  content: ""; position: absolute; right: -60px; top: -60px; width: 200px; height: 200px;
  background: repeating-radial-gradient(circle at center, rgba(255,255,255,0.025) 0, rgba(255,255,255,0.025) 1px, transparent 1px, transparent 12px);
  pointer-events: none; z-index: 0;
}

/* Status btn */
.ss-rep-status-btn:hover { background: var(--color-surface-2); }
.ss-rep-status-present.on { border-color: var(--color-ok); background: var(--color-ok-bg); box-shadow: 0 0 0 3px rgba(61,139,107,0.12); }
.ss-rep-status-present.on .ss-rep-status-t { color: var(--color-ok); }
.ss-rep-status-absent.on  { border-color: var(--color-danger); background: var(--color-danger-bg); box-shadow: 0 0 0 3px rgba(184,68,58,0.12); }
.ss-rep-status-absent.on  .ss-rep-status-t { color: var(--color-danger); }

/* Reason chip */
.ss-rep-reason-chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 11px; border-radius: 9999px;
  background: #fff; border: 1px solid var(--color-danger-line);
  font: inherit; font-size: 11.5px; font-weight: 500; color: var(--color-danger);
  cursor: pointer; transition: all 0.15s ease;
}
.ss-rep-reason-chip:hover { background: #fef0ed; }
.ss-rep-reason-chip.on { background: var(--color-danger); color: #fff; border-color: var(--color-danger); }

/* Shift options grid (in modal) */
.ss-rep-shift-opt {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 9px 6px; border-radius: 6px;
  border: 1.5px solid var(--color-line-2); background: var(--color-surface);
  cursor: pointer; transition: all 0.15s ease; font: inherit;
}
.ss-rep-shift-opt:hover { background: var(--color-surface-2); }
.ss-rep-shift-opt.on { border-color: var(--color-teal-500); background: var(--color-teal-50); box-shadow: 0 0 0 3px rgba(45,128,116,0.12); }
.ss-rep-shift-opt .ss-rep-shift { margin-bottom: 2px; pointer-events: none; }
.ss-rep-shift-opt .time { font-family: 'JetBrains Mono', monospace; font-size: 9.5px; color: var(--color-muted); font-weight: 500; letter-spacing: 0.02em; }

/* Inputs */
.ss-rep-input {
  width: 100%; padding: 10px 12px; border: 1px solid var(--color-line-2);
  border-radius: 6px; font: inherit; font-size: 13px; color: var(--color-ink);
  background: var(--color-surface); transition: all 0.15s ease;
}
.ss-rep-input:focus { outline: 0; border-color: var(--color-teal-500); box-shadow: 0 0 0 3px rgba(45,128,116,0.15); }
select.ss-rep-input {
  background: var(--color-surface) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b8783' stroke-width='2'><path d='m6 9 6 6 6-6'/></svg>") right 12px center/12px no-repeat;
  appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 34px;
}

.ss-rep-section-title {
  display: flex; align-items: center; gap: 8px;
  font-size: 10.5px; letter-spacing: 0.14em; text-transform: uppercase;
  color: var(--color-muted); font-weight: 700; margin-bottom: -4px;
}
.ss-rep-section-title::before { content: ""; width: 14px; height: 1px; background: var(--color-line-2); }
.ss-rep-section-title::after  { content: ""; flex: 1; height: 1px; background: var(--color-line); }

/* Footer btn-icon */
.ss-rep-btn-icon {
  width: 34px; height: 34px; border-radius: 6px;
  border: 1px solid var(--color-line); background: var(--color-surface); color: var(--color-ink-2);
  display: grid; place-items: center; cursor: pointer; transition: all 0.15s ease;
}
.ss-rep-btn-icon:hover { background: var(--color-teal-50); border-color: var(--color-teal-200); color: var(--color-teal-700); }
.ss-rep-btn-icon-danger:hover { background: var(--color-danger-bg); border-color: var(--color-danger-line); color: var(--color-danger); }

/* Edit toggle ON state */
#repToggleEdit.on { background: var(--color-warn); color: #fff; border-color: var(--color-warn); }
#repToggleEdit.on:hover { background: #a86220; border-color: #a86220; }

/* View toggle on */
.ss-rep-view-btn:hover { color: var(--color-ink-2); }
.ss-rep-view-btn.on { background: var(--color-surface); color: var(--color-teal-700); box-shadow: 0 1px 2px rgba(13,42,38,0.04); font-weight: 600; }

/* Format btn */
.ss-rep-format-btn:hover { background: var(--color-surface-2); }
.ss-rep-format-btn.on { border-color: var(--color-teal-500); background: var(--color-teal-50); box-shadow: 0 0 0 3px rgba(45,128,116,0.12); }
.ss-rep-format-btn.on .ss-rep-format-ic { background: var(--color-teal-600); color: #fff; }

/* Checklist d'export */
.ss-rep-check-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 6px; cursor: pointer; user-select: none;
  transition: background 0.15s ease;
}
.ss-rep-check-item:hover { background: var(--color-surface); }
.ss-rep-check-item input[type="checkbox"] {
  appearance: none; -webkit-appearance: none;
  width: 16px; height: 16px; border: 1.5px solid var(--color-line-2);
  border-radius: 4px; background: var(--color-surface); cursor: pointer;
  display: grid; place-items: center; flex-shrink: 0; transition: all 0.15s ease;
}
.ss-rep-check-item input[type="checkbox"]:checked { background: var(--color-teal-600); border-color: var(--color-teal-600); }
.ss-rep-check-item input[type="checkbox"]:checked::after {
  content: ""; width: 8px; height: 5px;
  border-left: 2px solid #fff; border-bottom: 2px solid #fff;
  transform: rotate(-45deg) translate(1px, -1px);
}
.ss-rep-check-item .swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.ss-rep-check-item .ci-text { flex: 1; font-size: 13px; color: var(--color-ink); font-weight: 500; min-width: 0; }
.ss-rep-check-item .ci-text small { display: block; font-size: 11px; color: var(--color-muted); font-weight: 400; margin-top: 1px; }
.ss-rep-check-item .ci-tag {
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  background: var(--color-surface); border: 1px solid var(--color-line);
  padding: 2px 7px; border-radius: 99px; color: var(--color-muted); font-weight: 600;
}

/* Spinner */
.ss-rep-spin {
  width: 18px; height: 18px; border: 2.5px solid var(--color-teal-100);
  border-top-color: var(--color-teal-600); border-radius: 50%;
  animation: ssRepSpin 0.8s linear infinite;
}
@keyframes ssRepSpin { to { transform: rotate(360deg); } }

/* Scrollbar discrète */
.ss-rep-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
.ss-rep-scrollbar::-webkit-scrollbar-track { background: transparent; }
.ss-rep-scrollbar::-webkit-scrollbar-thumb { background: var(--color-line-2); border-radius: 99px; }
.ss-rep-scrollbar { scrollbar-width: thin; scrollbar-color: var(--color-line-2) transparent; }

/* Légende item */
.ss-rep-legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--color-ink-2); }

/* Day view header */
.ss-rep-day-header {
  background: linear-gradient(135deg, var(--color-teal-700) 0%, var(--color-teal-600) 100%);
  border-radius: 12px; padding: 16px 20px; color: #fff;
  display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
  position: relative; overflow: hidden;
}
.ss-rep-day-header::after { content:""; position:absolute; right:-80px; top:-80px; width:280px; height:280px; background: repeating-radial-gradient(circle at center, rgba(255,255,255,0.025) 0, rgba(255,255,255,0.025) 1px, transparent 1px, transparent 14px); pointer-events: none; }

/* ── Day view table (8 colonnes) ──────────────────────────────────── */
.ss-rep-table-day {
  width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px;
}
.ss-rep-table-day th, .ss-rep-table-day td {
  border-right: 1px solid var(--color-line);
  border-bottom: 1px solid var(--color-line);
  vertical-align: middle; padding: 0;
}
.ss-rep-table-day tbody tr:last-child td { border-bottom: 0; }
.ss-rep-table-day thead th {
  background: var(--color-surface-2); font-weight: 600;
  font-size: 10.5px; letter-spacing: 0.1em; text-transform: uppercase;
  color: var(--color-muted); text-align: left; padding: 10px 12px;
}
.ss-rep-table-day thead th.center { text-align: center; }

.ss-rep-table-day .ss-rep-day-col-fonc    { width: 130px; background: var(--color-surface); }
.ss-rep-table-day .ss-rep-day-col-poste   { width: 60px; text-align: center; font-family: 'JetBrains Mono', monospace; color: var(--color-muted); font-size: 11.5px; font-weight: 600; }
.ss-rep-table-day .ss-rep-day-col-name    { width: auto; min-width: 220px; }
.ss-rep-table-day .ss-rep-day-col-horaire { width: 90px; text-align: center; }
.ss-rep-table-day .ss-rep-day-col-time    { width: 140px; text-align: center; font-family: 'JetBrains Mono', monospace; color: var(--color-ink-2); font-size: 11.5px; }
.ss-rep-table-day .ss-rep-day-col-etage   { width: 90px; text-align: center; font-family: 'JetBrains Mono', monospace; color: var(--color-ink-2); font-size: 11.5px; }
.ss-rep-table-day .ss-rep-day-col-status  { width: 150px; padding: 8px 10px; text-align: center; }
.ss-rep-table-day .ss-rep-day-col-actions { width: 70px; text-align: center; }

.ss-rep-table-day tbody tr:hover td { background: var(--color-surface-2); }
.ss-rep-table-day tbody td.ss-rep-day-col-fonc {
  font-weight: 600; color: var(--color-ink); font-size: 11.5px; padding: 10px 12px;
  border-right: 1px solid var(--color-line-2) !important;
}
.ss-rep-table-day tbody td.ss-rep-day-col-poste,
.ss-rep-table-day tbody td.ss-rep-day-col-horaire,
.ss-rep-table-day tbody td.ss-rep-day-col-time,
.ss-rep-table-day tbody td.ss-rep-day-col-etage { padding: 10px 8px; }

/* Day collab : avatar + name + role */
.ss-rep-day-collab { display: flex; align-items: center; gap: 10px; padding: 8px 12px; }
.ss-rep-day-av {
  width: 32px; height: 32px; border-radius: 50%;
  color: #fff; font-weight: 600; font-size: 11px;
  display: grid; place-items: center; flex-shrink: 0;
  background: var(--color-teal-600);
  font-family: 'Outfit', sans-serif; letter-spacing: 0.02em;
  overflow: hidden;
}
.ss-rep-day-av-1 { background: linear-gradient(135deg,#1f6359,#2d8074); }
.ss-rep-day-av-2 { background: linear-gradient(135deg,#5a9bd8,#3a6a8a); }
.ss-rep-day-av-3 { background: linear-gradient(135deg,#7a4f9e,#9268b3); }
.ss-rep-day-av-4 { background: linear-gradient(135deg,#d96b5a,#a04863); }
.ss-rep-day-av-5 { background: linear-gradient(135deg,#3d8b6b,#5cad9b); }
.ss-rep-day-collab .info { display: flex; flex-direction: column; min-width: 0; }
.ss-rep-day-collab .info .name { font-size: 13px; font-weight: 500; color: var(--color-ink); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ss-rep-day-collab .info .role { font-size: 11px; color: var(--color-muted); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Day status pills */
.ss-rep-day-status {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 9px; border-radius: 9999px;
  font-size: 11px; font-weight: 600; border: 1px solid;
  white-space: nowrap;
}
.ss-rep-day-status .b { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.ss-rep-day-status-ok     { background: var(--color-ok-bg);     color: var(--color-ok);     border-color: var(--color-ok-line); }
.ss-rep-day-status-warn   { background: var(--color-warn-bg);   color: var(--color-warn);   border-color: var(--color-warn-line); }
.ss-rep-day-status-absent { background: var(--color-danger-bg); color: var(--color-danger); border-color: var(--color-danger-line); }

/* Day row actions */
.ss-rep-day-actions { display: flex; justify-content: center; gap: 4px; padding: 4px 0; }
.ss-rep-day-action-btn {
  width: 26px; height: 26px; border-radius: 4px;
  border: 1px solid var(--color-line); background: var(--color-surface); color: var(--color-muted);
  display: grid; place-items: center; cursor: pointer; transition: all 0.15s ease;
}
.ss-rep-day-action-btn:hover { background: var(--color-teal-50); border-color: var(--color-teal-200); color: var(--color-teal-700); }

/* Print */
@media print {
  .admin-sidebar, .admin-topbar, .ss-rep-subheader, #repEditBanner, #repCellModal, #repExportModal, #repExportProgress, #repModFilter { display: none !important; }
  .ss-rep-content { padding: 0 !important; }
  .ss-rep-table { min-width: 0; font-size: 9px; }
  .ss-rep-module-body { overflow: visible; }
  .ss-rep-module { page-break-inside: avoid; }
  .ss-rep-module-head, .ss-rep-shift, .ss-rep-cell.absent .ss-rep-cell-main, .ss-rep-cell.absent .ss-rep-cell-etage { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}

/* Responsive */
@media (max-width: 1280px) { #repStatsBar { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 980px)  { #repStatsBar { grid-template-columns: repeat(2, 1fr); } }
</style>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- SCRIPT — logique métier (préservée) + nouveau rendu                   -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<script<?= nonce() ?>>
(function () {

  // ── State ─────────────────────────────────────────────────────────
  let currentWeekISO = <?= json_encode($weekIso) ?>;
  let editMode    = false;
  let editingCell = null;
  let viewMode    = 'week';
  let selectedDay = dateToStr(new Date());
  let dragData    = null;
  let activeFilter = 'all';

  let data = {
    success: true,
    week_start: <?= json_encode($weekStart) ?>,
    week_end:   <?= json_encode($weekEnd) ?>,
    week_label: <?= json_encode($weekLabel) ?>,
    week_iso:   <?= json_encode($weekIso) ?>,
    days:       <?= json_encode(array_values($repDays), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modules:    <?= json_encode(array_values($repModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    fonctions:  <?= json_encode(array_values($repFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    horaires:   <?= json_encode(array_values($repHoraires), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    plannings:  <?= json_encode(array_values($repPlannings), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    assignments:<?= json_encode(array_values($repAssignments), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    users:      <?= json_encode(array_values($repUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    modified_ids: <?= json_encode($repModifiedIds, JSON_HEX_TAG | JSON_HEX_APOS) ?>,
    absences:   <?= json_encode(array_values($repAbsences), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
  };

  const TODAY_ISO = <?= json_encode($todayIso) ?>;

  // ── Date helpers ──────────────────────────────────────────────────
  function dateToStr(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function getMondayOfISOWeek(iso) {
    const m = iso.match(/^(\d{4})-W(\d{2})$/); if (!m) return null;
    const jan4 = new Date(parseInt(m[1]), 0, 4);
    const dow = jan4.getDay() || 7;
    const w1Mon = new Date(jan4); w1Mon.setDate(jan4.getDate() - dow + 1);
    const t = new Date(w1Mon); t.setDate(w1Mon.getDate() + (parseInt(m[2]) - 1) * 7);
    return t;
  }

  // ── Module key helper : map DB code → mockup key (rj/m1/m2/m3/m4/pool/na/nuit) ──
  function modKey(code) {
    if (!code) return 'na';
    const c = String(code).toUpperCase();
    if (c === 'RS' || c === 'RUV' || c === 'RJ' || c === 'RJN') return 'rj';
    if (c === 'M1' || c === 'M2' || c === 'M3' || c === 'M4') return c.toLowerCase();
    if (c === 'POOL') return 'pool';
    if (c === 'NUIT') return 'nuit';
    return 'na';
  }

  // Module SVG icon
  const MOD_ICON_HTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>';
  const MOD_ICON_HTML_RJ = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg>';

  // Horaire sort key (start time)
  const horaireOrder = {};
  (data.horaires || []).forEach(h => { horaireOrder[h.id] = h.heure_debut || '99:99'; });

  // Shift class helper
  function shiftClass(code) {
    if (!code) return 'ss-rep-shift-default';
    const c = String(code).toLowerCase().replace(/[^a-z0-9]/g, '');
    const known = ['a2','a3','c1','c2','d1','d3','d4','s3','s4','n1','piquet'];
    return known.includes(c) ? 'ss-rep-shift-' + c : 'ss-rep-shift-default';
  }

  // ── Build absence index ───────────────────────────────────────────
  function buildAbsIdx() {
    const idx = {};
    (data.absences || []).forEach(a => {
      const s = new Date(a.date_debut), e = new Date(a.date_fin);
      for (let d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
        idx[a.user_id + '|' + dateToStr(d)] = a.type;
      }
    });
    return idx;
  }

  // ── Slot builder (préservé de la version précédente) ──────────────
  function buildSections() {
    const days = data.days || [];
    const dateList = days.map(d => d.date);
    const modifiedSet = new Set(data.modified_ids || []);
    const absIdx = buildAbsIdx();

    const byMod = {};
    (data.assignments || []).forEach(a => {
      const mid = a.module_id || '_NONE';
      if (!byMod[mid]) byMod[mid] = [];
      byMod[mid].push(a);
    });

    const fnMap = {};
    (data.fonctions || []).forEach(f => { fnMap[f.code] = f; });

    const soirCodes = new Set(['S3', 'S4', 'D4', 'C2']);

    function buildModuleSlots(mod, assigns) {
      const byFn = {};
      assigns.forEach(a => {
        const fc = a.fonction_code || '_NONE';
        if (!byFn[fc]) byFn[fc] = [];
        byFn[fc].push(a);
      });

      const fnCodes = Object.keys(byFn).sort((a, b) =>
        ((fnMap[a] || {}).ordre || 99) - ((fnMap[b] || {}).ordre || 99)
      );

      const sections = [];

      fnCodes.forEach(fc => {
        const fnAssigns = byFn[fc];
        const fnInfo = fnMap[fc] || { nom: fc, ordre: 99 };
        let slots = [];

        if (fc === 'AS' && mod && mod.etages && mod.etages.length > 0) {
          const usedIds = new Set();
          mod.etages.forEach(et => {
            (et.groupes || []).forEach(gr => {
              const slotDays = {};
              dateList.forEach(dt => {
                const match = fnAssigns.find(a => a.date_jour === dt && a.groupe_id === gr.id && !soirCodes.has(a.horaire_code));
                if (match) { slotDays[dt] = match; usedIds.add(match.assignation_id); }
              });
              slots.push({ label: et.code.replace('E','') + '-' + gr.code.replace(/^\d+-/, ''), days: slotDays });
            });
          });
          const soirAssigns = fnAssigns.filter(a => !usedIds.has(a.assignation_id));
          if (soirAssigns.length > 0) {
            const soirByDay = {};
            soirAssigns.forEach(a => { (soirByDay[a.date_jour] = soirByDay[a.date_jour] || []).push(a); });
            const maxSoir = Math.max(...Object.values(soirByDay).map(arr => arr.length), 0);
            for (let s = 0; s < maxSoir; s++) {
              const slotDays = {};
              dateList.forEach(dt => { if (soirByDay[dt] && soirByDay[dt][s]) slotDays[dt] = soirByDay[dt][s]; });
              slots.push({ label: 'Soir' + (maxSoir > 1 ? ' ' + (s + 1) : ''), days: slotDays });
            }
          }
        } else {
          const byDay = {};
          dateList.forEach(dt => {
            byDay[dt] = fnAssigns.filter(a => a.date_jour === dt)
              .sort((a, b) => (horaireOrder[a.horaire_type_id] || '99').localeCompare(horaireOrder[b.horaire_type_id] || '99'));
          });
          const maxSlots = Math.max(...dateList.map(dt => (byDay[dt] || []).length), 0);
          for (let s = 0; s < maxSlots; s++) {
            const slotDays = {};
            dateList.forEach(dt => { if (byDay[dt] && byDay[dt][s]) slotDays[dt] = byDay[dt][s]; });
            slots.push({ label: maxSlots > 1 ? String(s + 1) : '1', days: slotDays });
          }
        }

        if (slots.length > 0) sections.push({ code: fc, nom: fnInfo.nom || fc, slots: slots });
      });

      return sections;
    }

    const result = [];
    const modules = data.modules || [];

    // RS/RUV : section spéciale "rj" (responsables)
    const rsAssigns = (data.assignments || []).filter(a => a.fonction_code === 'RS' || a.fonction_code === 'RUV');
    if (rsAssigns.length > 0) {
      const rsSections = buildModuleSlots(null, rsAssigns);
      result.push({ module: { id: '', code: 'RJ', nom: 'RJ / RJN — Responsables', etages: [] }, functions: rsSections });
    }

    modules.forEach(mod => {
      const assigns = (byMod[mod.id] || []).filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
      if (assigns.length === 0) return;
      const fnSections = buildModuleSlots(mod, assigns);
      if (fnSections.length > 0) result.push({ module: mod, functions: fnSections });
    });

    if (byMod['_NONE']) {
      const noneAssigns = byMod['_NONE'].filter(a => a.fonction_code !== 'RS' && a.fonction_code !== 'RUV');
      if (noneAssigns.length > 0) {
        const fnSections = buildModuleSlots(null, noneAssigns);
        result.push({ module: { id: '', code: 'POOL', nom: 'Pool / Non assigné', etages: [] }, functions: fnSections });
      }
    }

    return { sections: result, modifiedSet, absIdx };
  }

  // ── Render filter chips + stats ───────────────────────────────────
  function renderFilterAndStats() {
    const { sections } = buildSections();

    // Filter chips
    const mfEl = document.getElementById('repModFilter');
    const totalPostes = sections.reduce((acc, s) => acc + s.functions.reduce((a, f) => a + f.slots.length, 0), 0);
    let chipsHtml = '<span class="text-[10px] tracking-[0.12em] uppercase text-muted font-bold pl-1.5 pr-1 shrink-0">Filtrer</span>';
    chipsHtml += '<button type="button" data-filter="all" class="ss-rep-mf-chip' + (activeFilter==='all'?' on':'') + '">';
    chipsHtml += '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>';
    chipsHtml += 'Tous les modules <span class="count">' + sections.length + '</span></button>';
    sections.forEach(sec => {
      const key = modKey(sec.module.code);
      const count = sec.functions.reduce((a, f) => a + f.slots.length, 0);
      const isOn = activeFilter === key;
      chipsHtml += '<button type="button" data-filter="' + key + '" class="ss-rep-mf-chip' + (isOn?' on':'') + '">';
      chipsHtml += '<span class="swatch ss-rep-mod-sw-' + key + '"></span>' + escapeHtml(sec.module.code || sec.module.nom);
      chipsHtml += ' <span class="count">' + count + '</span></button>';
    });
    mfEl.innerHTML = chipsHtml;

    // Stats
    const assigns = data.assignments || [];
    const presents = assigns.filter(a => a.statut !== 'absent' && a.statut !== 'vacant').length;
    const absents  = assigns.filter(a => a.statut === 'absent').length;
    const vacants  = assigns.filter(a => a.statut === 'vacant').length;
    const heures   = assigns.reduce((acc, a) => acc + (parseFloat(a.duree_effective) || 0), 0);
    const poolUsers = new Set(assigns.filter(a => modKey(a.module_code) === 'pool').map(a => a.user_id));
    const todayAbs = assigns.filter(a => a.statut === 'absent' && a.date_jour === TODAY_ISO).length;

    const statsEl = document.getElementById('repStatsBar');
    statsEl.innerHTML = ''
      + '<div class="ss-rep-stat-card"><div class="lbl">Postes assignés</div><div class="v">' + presents + '<small> / ' + assigns.length + '</small></div><div class="sub">Semaine ' + escapeHtml(currentWeekISO) + '</div></div>'
      + '<div class="ss-rep-stat-card warn"><div class="lbl">Postes vacants</div><div class="v">' + vacants + '</div><div class="sub">Pool sollicité</div></div>'
      + '<div class="ss-rep-stat-card ok"><div class="lbl">Couverture</div><div class="v">' + (assigns.length ? Math.round(presents/assigns.length*100) : 0) + '%</div><div class="sub">' + presents + ' / ' + assigns.length + ' présents</div></div>'
      + '<div class="ss-rep-stat-card"><div class="lbl">Heures planifiées</div><div class="v">' + Math.round(heures) + '<small> h</small></div><div class="sub">Cumul semaine</div></div>'
      + '<div class="ss-rep-stat-card info"><div class="lbl">Pool en charge</div><div class="v">' + poolUsers.size + '<small> remplaçant·es</small></div><div class="sub">Modules POOL</div></div>'
      + '<div class="ss-rep-stat-card danger"><div class="lbl">Absents aujourd\'hui</div><div class="v">' + todayAbs + '</div><div class="sub">' + escapeHtml(TODAY_ISO) + '</div></div>';

    // Légende horaires
    renderLegend();

    // Status planning(s)
    const ps = data.plannings || [];
    if (ps.length) {
      const statuts = ps.map(p => p.statut).filter((v,i,a) => a.indexOf(v) === i);
      const dominant = statuts[0] || '—';
      const colorMap = { brouillon: 'bg-surface-3 text-ink-2 border-line', provisoire: 'bg-info-bg text-info border-info-line', final: 'bg-ok-bg text-ok border-ok-line' };
      const cls = colorMap[dominant] || 'bg-surface-3 text-ink-2 border-line';
      const el = document.getElementById('repPlanningStatus');
      el.className = 'border text-[10.5px] font-semibold px-2 py-0.5 rounded-full lowercase tracking-[0.04em] ' + cls;
      el.textContent = dominant;
      document.getElementById('repPlanningMonth').textContent = ps.map(p => p.mois_annee).join(' · ');
    }
  }

  function renderLegend() {
    const el = document.getElementById('repLegend');
    let html = '<div class="flex flex-wrap items-center gap-x-3.5 gap-y-2"><span class="text-[10.5px] tracking-[0.12em] uppercase text-muted font-bold mr-1">Horaires</span>';
    (data.horaires || []).forEach(h => {
      const cls = shiftClass(h.code);
      html += '<span class="ss-rep-legend-item"><span class="ss-rep-shift ' + cls + '">' + escapeHtml(h.code) + '</span> ' + escapeHtml((h.heure_debut||'').substring(0,5)) + '-' + escapeHtml((h.heure_fin||'').substring(0,5)) + '</span>';
    });
    html += '</div>';
    el.innerHTML = html;
  }

  // ── Render grid (vue semaine ou jour) ─────────────────────────────
  function dayHeaderHtml(d) {
    const dayName = d.full_name.charAt(0).toUpperCase() + d.full_name.slice(1);
    const isToday = d.date === TODAY_ISO;
    const cls = (d.is_weekend ? 'weekend' : '') + (isToday ? ' today' : '');
    return '<th class="' + cls + ' ss-rep-col-day"><span class="day-name">' + dayName + '</span><span class="day-date">' + d.day_num + ' ' + escapeHtml(d.month_name.substring(0,3)) + '</span></th>';
  }
  function subDayHeaderHtml(d) {
    const isToday = d.date === TODAY_ISO;
    const cls = (d.is_weekend ? 'weekend' : '') + (isToday ? ' today' : '');
    return '<th class="' + cls + '"><div class="ss-rep-sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>';
  }

  // ── Status pill helper for day view ──
  function dayStatusPill(statut, absType) {
    const map = {
      present:  { cls: 'ok',     label: 'Présent·e' },
      absent:   { cls: 'absent', label: 'Absent·e' + (absType ? ' · ' + absType.replace(/_/g,' ') : '') },
      remplace: { cls: 'warn',   label: 'Remplacé·e' },
      interim:  { cls: 'warn',   label: 'Intérim' },
      entraide: { cls: 'ok',     label: 'Entraide' },
      repos:    { cls: 'absent', label: 'Repos' },
      vacant:   { cls: 'warn',   label: 'Vacant' },
    };
    const m = map[statut] || map.present;
    return '<span class="ss-rep-day-status ss-rep-day-status-' + m.cls + '"><span class="b"></span>' + escapeHtml(m.label) + '</span>';
  }

  // ── Avatar gradient hash (5 variantes) ──
  function avatarVariant(id) {
    const s = String(id || '');
    let h = 0;
    for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
    return Math.abs(h) % 5 + 1;
  }

  function userInitials(prenom, nom) {
    return ((prenom||'').trim()[0] || '') + ((nom||'').trim()[0] || '');
  }

  // ── Render WEEK view ─────────────────────────────────────────────
  function renderWeekView(sections, days, modifiedSet, absIdx) {
    let html = '';
    sections.forEach(sec => {
      const mod = sec.module;
      const key = modKey(mod.code);
      const count = sec.functions.reduce((a, f) => a + f.slots.length, 0);
      const ico = (key === 'rj') ? MOD_ICON_HTML_RJ : MOD_ICON_HTML;
      const hidden = (activeFilter !== 'all' && activeFilter !== key) ? ' hidden' : '';

      html += '<div class="ss-rep-module ss-rep-mod-' + key + hidden + ' bg-surface border border-line rounded-xl overflow-hidden shadow-sp-sm" data-mod="' + key + '" data-section-module-id="' + (mod.id || '') + '" data-mod-code="' + escapeHtml(mod.code || '') + '">';
      html += '<div class="ss-rep-module-head flex items-center gap-3 px-4 py-2.5 text-white" data-drop-module-id="' + (mod.id || '') + '" data-drop-module-code="' + escapeHtml(mod.code || '') + '">'
            +   '<div class="w-[22px] h-[22px] rounded bg-white/20 grid place-items-center shrink-0 relative z-[1]">' + ico + '</div>'
            +   '<h2 class="font-display font-semibold text-[15px] -tracking-[0.01em] flex-1 relative z-[1] truncate">' + escapeHtml(mod.nom || mod.code) + '</h2>'
            +   '<span class="font-mono text-[11px] font-semibold bg-white/20 px-2.5 py-0.5 rounded-full tracking-[0.02em] relative z-[1]">' + count + ' poste(s)</span>'
            + '</div>';

      html += '<div class="ss-rep-module-body">';
      html += '<table class="ss-rep-table">';
      html += '<colgroup><col style="width:90px"><col style="width:48px">';
      days.forEach(() => { html += '<col>'; });
      html += '</colgroup>';
      html += '<thead><tr class="day-row">'
            +   '<th class="ss-rep-col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>'
            +   '<th class="ss-rep-col-poste" rowspan="2">Poste</th>';
      days.forEach(d => { html += dayHeaderHtml(d); });
      html += '</tr><tr class="subhead-row">';
      days.forEach(d => { html += subDayHeaderHtml(d); });
      html += '</tr></thead><tbody>';

      sec.functions.forEach(fn => {
        fn.slots.forEach((slot, si) => {
          html += '<tr>';
          if (si === 0) {
            html += '<td class="ss-rep-col-fonction" rowspan="' + fn.slots.length + '"><div class="label">' + escapeHtml(fn.nom);
            if (fn.slots.length > 1) html += '<small>' + fn.slots.length + ' postes</small>';
            html += '</div></td>';
          }
          html += '<td class="ss-rep-col-poste">' + escapeHtml(slot.label) + '</td>';

          days.forEach(d => {
            const a = slot.days[d.date] || null;
            const isToday = d.date === TODAY_ISO;
            const tdCls = (d.is_weekend ? 'weekend' : '') + (isToday ? ' today' : '');
            const cellCls = ['ss-rep-cell'];
            const attrs = cellDataAttrs(a, d.date);
            if (!a) cellCls.push('empty');
            if (a && a.statut === 'absent') cellCls.push('absent');
            if (a && a.assignation_id && modifiedSet.has(a.assignation_id)) cellCls.push('modified');
            const dragAttr = (a && a.assignation_id) ? ' draggable="true"' : '';

            let nameHtml = '';
            let etageHtml = '—';
            let shiftHtml = '';
            if (a) {
              const userName = (a.user_prenom || '') + ' ' + (a.user_nom || '');
              if (a.statut === 'absent') {
                nameHtml += '<span class="absent-ico">!</span>';
                etageHtml = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93 19.07 19.07"/></svg> Abs.';
              } else {
                if (a.etage_code && a.groupe_code) etageHtml = escapeHtml(a.etage_code.replace('E','') + '-' + a.groupe_code.replace(/^\d+-/, ''));
                else if (a.groupe_code) etageHtml = escapeHtml(a.groupe_code);
                else if (a.etage_code) etageHtml = escapeHtml(a.etage_code.replace('E',''));
              }
              nameHtml += '<span class="ss-rep-cell-name" title="' + escapeHtml(userName) + '">' + escapeHtml(userName) + '</span>';
              if (a.notes) nameHtml += '<span class="text-muted text-[8px] -mt-2 ml-px" title="' + escapeHtml(a.notes) + '">★</span>';
              if (a.horaire_code) {
                shiftHtml = '<span class="ss-rep-shift ' + shiftClass(a.horaire_code) + '">' + escapeHtml(a.horaire_code) + '</span>';
              }
            }

            html += '<td class="' + tdCls + '">'
                  +   '<div class="' + cellCls.join(' ') + '"' + attrs + dragAttr + '>'
                  +     '<div class="ss-rep-cell-main">' + nameHtml + shiftHtml + '</div>'
                  +     '<div class="ss-rep-cell-etage">' + etageHtml + '</div>'
                  +   '</div>'
                  + '</td>';
          });

          html += '</tr>';
        });
      });

      html += '</tbody></table></div></div>';
    });
    return html;
  }

  // ── Render DAY view (table dédiée 8 colonnes) ─────────────────────
  function renderDayView(sections, day, modifiedSet, absIdx) {
    const dayName = day.full_name.charAt(0).toUpperCase() + day.full_name.slice(1);
    const presentDay = (data.assignments || []).filter(a => a.date_jour === day.date && a.statut !== 'absent').length;
    const absentDay  = (data.assignments || []).filter(a => a.date_jour === day.date && a.statut === 'absent').length;
    const totalDay   = (data.assignments || []).filter(a => a.date_jour === day.date).length;
    const heuresDay  = (data.assignments || []).filter(a => a.date_jour === day.date).reduce((acc, a) => acc + (parseFloat(a.duree_effective)||0), 0);

    let html = '<div class="ss-rep-day-header relative">'
      + '<div class="font-display font-semibold text-[34px] leading-none -tracking-[0.02em] relative z-[1]">' + day.day_num + '</div>'
      + '<div class="flex flex-col gap-1 relative z-[1]"><div class="text-[11px] tracking-[0.14em] uppercase text-[#a8e6c9] font-semibold">' + dayName + '</div><div class="text-[18px] font-medium">' + escapeHtml(day.month_name) + ' ' + day.year + '</div></div>'
      + '<div class="flex gap-6 ml-auto relative z-[1]">'
      +   '<div class="flex flex-col gap-px"><span class="text-[10px] tracking-[0.1em] uppercase text-[#a8c4be] font-semibold">Postes</span><span class="font-display font-semibold text-[20px]">' + totalDay + '</span></div>'
      +   '<div class="flex flex-col gap-px"><span class="text-[10px] tracking-[0.1em] uppercase text-[#a8c4be] font-semibold">Présents</span><span class="font-display font-semibold text-[20px]">' + presentDay + '</span></div>'
      +   '<div class="flex flex-col gap-px"><span class="text-[10px] tracking-[0.1em] uppercase text-[#a8c4be] font-semibold">Absents</span><span class="font-display font-semibold text-[20px]"' + (absentDay>0?' style="color:#fbb6ad"':'') + '>' + absentDay + '</span></div>'
      +   '<div class="flex flex-col gap-px"><span class="text-[10px] tracking-[0.1em] uppercase text-[#a8c4be] font-semibold">Heures</span><span class="font-display font-semibold text-[20px]">' + Math.round(heuresDay) + '<span class="text-[13px] font-normal">h</span></span></div>'
      + '</div></div>';

    sections.forEach(sec => {
      const mod = sec.module;
      const key = modKey(mod.code);
      const ico = (key === 'rj') ? MOD_ICON_HTML_RJ : MOD_ICON_HTML;
      const hidden = (activeFilter !== 'all' && activeFilter !== key) ? ' hidden' : '';

      // Collect assignments for this module on this day, preserving fonction order
      const rows = [];
      sec.functions.forEach(fn => {
        fn.slots.forEach(slot => {
          const a = slot.days[day.date];
          if (a) rows.push({ fonction: fn.nom, fnCode: fn.code, poste: slot.label, a: a });
        });
      });

      if (rows.length === 0) return; // skip empty modules in day view

      html += '<div class="ss-rep-module ss-rep-mod-' + key + hidden + ' bg-surface border border-line rounded-xl overflow-hidden shadow-sp-sm" data-mod="' + key + '" data-section-module-id="' + (mod.id || '') + '" data-mod-code="' + escapeHtml(mod.code || '') + '">';
      html += '<div class="ss-rep-module-head flex items-center gap-3 px-4 py-2.5 text-white" data-drop-module-id="' + (mod.id || '') + '" data-drop-module-code="' + escapeHtml(mod.code || '') + '">'
            +   '<div class="w-[22px] h-[22px] rounded bg-white/20 grid place-items-center shrink-0 relative z-[1]">' + ico + '</div>'
            +   '<h2 class="font-display font-semibold text-[15px] -tracking-[0.01em] flex-1 relative z-[1] truncate">' + escapeHtml(mod.nom || mod.code) + ' · ' + escapeHtml(dayName + ' ' + day.day_num + ' ' + day.month_name) + '</h2>'
            +   '<span class="font-mono text-[11px] font-semibold bg-white/20 px-2.5 py-0.5 rounded-full tracking-[0.02em] relative z-[1]">' + rows.length + ' personne' + (rows.length > 1 ? 's' : '') + '</span>'
            + '</div>';

      html += '<div class="ss-rep-module-body"><table class="ss-rep-table-day">';
      html += '<thead><tr>'
            +   '<th class="ss-rep-day-col-fonc">Fonction</th>'
            +   '<th class="ss-rep-day-col-poste center">Poste</th>'
            +   '<th class="ss-rep-day-col-name">Collaborateur</th>'
            +   '<th class="ss-rep-day-col-horaire center">Horaire</th>'
            +   '<th class="ss-rep-day-col-time center">Plage horaire</th>'
            +   '<th class="ss-rep-day-col-etage center">Étage</th>'
            +   '<th class="ss-rep-day-col-status center">Statut</th>'
            +   '<th class="ss-rep-day-col-actions center">Actions</th>'
            + '</tr></thead><tbody>';

      rows.forEach(r => {
        const a = r.a;
        const userName = (a.user_prenom || '') + ' ' + (a.user_nom || '');
        const init = userInitials(a.user_prenom, a.user_nom).toUpperCase() || '?';
        const av = a.user_photo
          ? '<div class="ss-rep-day-av"><img src="' + escapeHtml(a.user_photo) + '" alt="" class="w-full h-full object-cover rounded-full"></div>'
          : '<div class="ss-rep-day-av ss-rep-day-av-' + avatarVariant(a.user_id) + '">' + escapeHtml(init) + '</div>';
        const role = (a.fonction_nom || '') + (a.user_taux ? ' · ' + a.user_taux + '%' : '');
        const shiftBadge = a.horaire_code ? '<span class="ss-rep-shift ' + shiftClass(a.horaire_code) + '">' + escapeHtml(a.horaire_code) + '</span>' : '<span class="text-muted-2">—</span>';
        const plage = (a.heure_debut && a.heure_fin) ? (a.heure_debut.substring(0,5) + ' → ' + a.heure_fin.substring(0,5)) : '—';
        let etage = '—';
        if (a.statut === 'absent') etage = '<span style="color:var(--color-danger);font-weight:600">— absente —</span>';
        else if (a.etage_code && a.groupe_code) etage = escapeHtml(a.etage_code.replace('E','') + '-' + a.groupe_code.replace(/^\d+-/, ''));
        else if (a.groupe_code) etage = escapeHtml(a.groupe_code);
        else if (a.etage_code) etage = escapeHtml(a.etage_code.replace('E',''));
        const absType = absIdx[a.user_id + '|' + a.date_jour] || null;
        const status = dayStatusPill(a.statut, absType);
        const attrs = cellDataAttrs(a, day.date);

        html += '<tr class="ss-rep-day-row" ' + attrs + '>'
              +   '<td class="ss-rep-day-col-fonc">' + escapeHtml(r.fonction) + '</td>'
              +   '<td class="ss-rep-day-col-poste">' + escapeHtml(r.poste) + '</td>'
              +   '<td><div class="ss-rep-day-collab">' + av + '<div class="info"><div class="name">' + escapeHtml(userName) + '</div><div class="role">' + escapeHtml(role || '—') + '</div></div></div></td>'
              +   '<td class="ss-rep-day-col-horaire">' + shiftBadge + '</td>'
              +   '<td class="ss-rep-day-col-time">' + escapeHtml(plage) + '</td>'
              +   '<td class="ss-rep-day-col-etage">' + etage + '</td>'
              +   '<td class="ss-rep-day-col-status">' + status + '</td>'
              +   '<td class="ss-rep-day-col-actions"><div class="ss-rep-day-actions">'
              +     '<button type="button" class="ss-rep-day-action-btn ss-rep-day-edit" title="Modifier"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>'
              +   '</div></td>'
              + '</tr>';
      });

      html += '</tbody></table></div></div>';
    });

    return html;
  }

  function renderGrid() {
    const { sections, modifiedSet, absIdx } = buildSections();
    const grid = document.getElementById('repGrid');
    const isDayView = viewMode === 'day';

    if (sections.length === 0) {
      grid.innerHTML = '<div class="bg-surface border border-line rounded-xl px-6 py-12 text-center text-muted shadow-sp-sm">'
        + '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-2 opacity-40"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
        + 'Aucune affectation pour cette ' + (isDayView ? 'journée' : 'semaine') + '.</div>';
      return;
    }

    if (isDayView) {
      let day = (data.days || []).find(d => d.date === selectedDay);
      if (!day && (data.days || []).length) day = data.days[0];
      if (!day) { grid.innerHTML = ''; return; }
      grid.innerHTML = renderDayView(sections, day, modifiedSet, absIdx);
    } else {
      grid.innerHTML = renderWeekView(sections, data.days || [], modifiedSet, absIdx);
    }
  }

  // ── Cell data attributes ──────────────────────────────────────────
  function cellDataAttrs(a, date) {
    let s = ' data-date="' + date + '"';
    if (a) {
      s += ' data-assignation-id="' + (a.assignation_id || '') + '"';
      s += ' data-planning-id="'    + (a.planning_id || '') + '"';
      s += ' data-user-id="'        + (a.user_id || '') + '"';
      s += ' data-horaire-type-id="' + (a.horaire_type_id || '') + '"';
      s += ' data-module-id="'      + (a.module_id || '') + '"';
      s += ' data-groupe-id="'      + (a.groupe_id || '') + '"';
      s += ' data-etage-id="'       + (a.etage_id || '') + '"';
      s += ' data-statut="'         + (a.statut || 'present') + '"';
      s += ' data-notes="'          + escapeHtml(a.notes || '') + '"';
      s += ' data-updated-at="'     + (a.updated_at || '') + '"';
      s += ' data-user-name="'      + escapeHtml((a.user_prenom || '') + ' ' + (a.user_nom || '')) + '"';
      s += ' data-user-prenom="'    + escapeHtml(a.user_prenom || '') + '"';
      s += ' data-user-nom="'       + escapeHtml(a.user_nom || '') + '"';
      s += ' data-user-photo="'     + escapeHtml(a.user_photo || '') + '"';
      s += ' data-user-taux="'      + (a.user_taux || '') + '"';
      s += ' data-fonction-nom="'   + escapeHtml(a.fonction_nom || '') + '"';
      s += ' data-horaire-code="'   + escapeHtml(a.horaire_code || '') + '"';
      s += ' data-module-code="'    + escapeHtml(a.module_code || '') + '"';
    }
    return s;
  }

  // ── Load week via API ─────────────────────────────────────────────
  async function loadWeek(weekOrDate) {
    const params = {};
    if (weekOrDate && weekOrDate.includes && weekOrDate.includes('-W')) params.semaine = weekOrDate;
    else if (weekOrDate) params.date = weekOrDate;

    const grid = document.getElementById('repGrid');
    grid.innerHTML = '<div class="text-center py-12 text-muted"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-2 opacity-40 animate-spin"><circle cx="12" cy="12" r="9" stroke-dasharray="40 60"/></svg>Chargement…</div>';

    const res = await adminApiPost('admin_get_repartition', params);
    if (!res.success) {
      grid.innerHTML = '<div class="bg-danger-bg border border-danger-line rounded-md text-danger px-4 py-3 text-sm">Erreur : ' + escapeHtml(res.message || 'Erreur') + '</div>';
      return;
    }

    data = res;
    currentWeekISO = res.week_iso;
    document.getElementById('repWeekLabel').textContent = res.week_label;
    document.getElementById('repDatePicker').value = res.week_start;

    closeCellModal();
    if (typeof updateLabel === 'function') updateLabel();
    renderFilterAndStats();
    renderGrid();
  }

  // ── Edit mode toggle ──────────────────────────────────────────────
  function setEditMode(on) {
    editMode = !!on;
    const btn = document.getElementById('repToggleEdit');
    btn.classList.toggle('on', editMode);
    document.getElementById('repEditLabel').textContent = editMode ? 'En édition' : 'Éditer';
    document.getElementById('repGrid').classList.toggle('ss-rep-edit-mode', editMode);
    const banner = document.getElementById('repEditBanner');
    if (editMode) { banner.classList.remove('hidden'); banner.classList.add('flex'); }
    else { banner.classList.add('hidden'); banner.classList.remove('flex'); }
    if (!editMode) closeCellModal();
  }

  document.getElementById('repToggleEdit').addEventListener('click', () => setEditMode(!editMode));
  document.getElementById('repExitEdit').addEventListener('click', () => setEditMode(false));

  // ── Cell modal (hero unifié) ──────────────────────────────────────
  function buildShiftGrid() {
    const grid = document.getElementById('repModalShiftGrid');
    let html = '';
    (data.horaires || []).forEach(h => {
      const cls = shiftClass(h.code);
      const range = (h.heure_debut||'').substring(0,5) + '→' + (h.heure_fin||'').substring(0,5);
      const isLong = (h.code || '').length > 3;
      html += '<button type="button" class="ss-rep-shift-opt" data-shift-id="' + h.id + '" data-shift-code="' + escapeHtml(h.code) + '">'
            +   '<span class="ss-rep-shift ' + cls + '"' + (isLong?' style="font-size:9px"':'') + '>' + escapeHtml(h.code) + '</span>'
            +   '<span class="time">' + escapeHtml(range) + '</span>'
            + '</button>';
    });
    // "—" : aucun horaire
    html += '<button type="button" class="ss-rep-shift-opt" data-shift-id="" data-shift-code="" style="border-style:dashed">'
          +   '<span class="ss-rep-shift" style="background:transparent;color:var(--color-muted);border-color:var(--color-line-2)">—</span>'
          +   '<span class="time">aucun</span>'
          + '</button>';
    grid.innerHTML = html;
  }

  function populateModalSelects() {
    const mSel = document.getElementById('repModalModule');
    mSel.innerHTML = '<option value="">—</option>';
    (data.modules || []).forEach(m => {
      const o = document.createElement('option');
      o.value = m.id; o.textContent = (m.code || '') + ' — ' + (m.nom || '');
      mSel.appendChild(o);
    });
  }

  function populateGroupeSelect(moduleId) {
    const sel = document.getElementById('repModalGroupe');
    sel.innerHTML = '<option value="">—</option>';
    if (!moduleId) return;
    const mod = (data.modules || []).find(m => m.id === moduleId);
    if (!mod) return;
    (mod.etages || []).forEach(et => {
      (et.groupes || []).forEach(gr => {
        const o = document.createElement('option');
        o.value = gr.id; o.dataset.etageId = et.id;
        o.textContent = et.code + '-' + gr.code;
        sel.appendChild(o);
      });
      if (!et.groupes || et.groupes.length === 0) {
        const o = document.createElement('option');
        o.value = ''; o.dataset.etageId = et.id;
        o.textContent = et.code;
        sel.appendChild(o);
      }
    });
  }

  function openCellModal(cellEl) {
    if (cellEl.classList.contains('empty')) return;

    const ds = cellEl.dataset;
    editingCell = {
      assignation_id: ds.assignationId || '',
      planning_id: ds.planningId || '',
      user_id: ds.userId || '',
      date: ds.date || '',
      horaire_type_id: ds.horaireTypeId || '',
      module_id: ds.moduleId || '',
      groupe_id: ds.groupeId || '',
      etage_id: ds.etageId || '',
      statut: ds.statut || 'present',
      notes: ds.notes || '',
      updated_at: ds.updatedAt || '',
      user_name: ds.userName || '',
      user_prenom: ds.userPrenom || '',
      user_nom: ds.userNom || '',
      user_photo: ds.userPhoto || '',
      user_taux: ds.userTaux || '',
      fonction_nom: ds.fonctionNom || '',
      module_code: ds.moduleCode || '',
      horaire_code: ds.horaireCode || '',
      cellEl: cellEl,
    };

    // Avatar : initiales
    const av = document.getElementById('repModalAvatar');
    const init = ((editingCell.user_prenom || '').trim()[0] || '') + ((editingCell.user_nom || '').trim()[0] || '');
    if (editingCell.user_photo) {
      av.innerHTML = '<img src="' + escapeHtml(editingCell.user_photo) + '" alt="" class="w-full h-full object-cover rounded-xl">';
      av.style.background = 'transparent';
    } else {
      av.textContent = init.toUpperCase() || '?';
      av.style.background = '';
    }

    // Nom + role
    document.getElementById('repModalName').textContent = editingCell.user_name.trim() || 'Nouveau';
    let role = editingCell.fonction_nom || '';
    if (editingCell.user_taux) role += (role ? ' · ' : '') + editingCell.user_taux + '%';
    document.getElementById('repModalRole').textContent = role || '—';

    // Meta
    document.getElementById('repModalDate').textContent = editingCell.date || '—';
    const modOpt = (data.modules || []).find(m => m.id === editingCell.module_id);
    document.getElementById('repModalModuleLabel').textContent = modOpt ? (modOpt.code + ' — ' + modOpt.nom) : (editingCell.module_code || '—');
    // Poste : on n'a pas le numéro exact, on affiche horaire ou "—"
    document.getElementById('repModalPosteLabel').textContent = editingCell.horaire_code || '—';

    // Statut
    document.querySelectorAll('#repCellModal .ss-rep-status-btn').forEach(b => b.classList.remove('on'));
    const statusBtn = document.querySelector('#repCellModal .ss-rep-status-btn[data-status="' + (editingCell.statut === 'absent' ? 'absent' : 'present') + '"]');
    if (statusBtn) statusBtn.classList.add('on');
    document.getElementById('repModalAbsentBlock').classList.toggle('hidden', editingCell.statut !== 'absent');
    document.getElementById('repModalAbsentBlock').classList.toggle('flex', editingCell.statut === 'absent');

    // Reasons
    document.querySelectorAll('#repCellModal .ss-rep-reason-chip').forEach(b => b.classList.remove('on'));

    // Shift selection
    document.querySelectorAll('#repCellModal .ss-rep-shift-opt').forEach(b => {
      b.classList.toggle('on', b.dataset.shiftId === editingCell.horaire_type_id);
    });

    // Selects
    populateModalSelects();
    document.getElementById('repModalModule').value = editingCell.module_id || '';
    populateGroupeSelect(editingCell.module_id || '');
    document.getElementById('repModalGroupe').value = editingCell.groupe_id || '';
    document.getElementById('repModalStatut').value = editingCell.statut || 'present';
    document.getElementById('repModalNotes').value = editingCell.notes || '';

    // Show/hide delete
    document.getElementById('repModalDelete').style.display = editingCell.assignation_id ? '' : 'none';

    document.getElementById('repCellModal').classList.add('show');
  }

  function closeCellModal() {
    document.getElementById('repCellModal').classList.remove('show');
    editingCell = null;
  }

  // Status btn click
  document.querySelectorAll('#repCellModal .ss-rep-status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#repCellModal .ss-rep-status-btn').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
      const isAbs = btn.dataset.status === 'absent';
      document.getElementById('repModalAbsentBlock').classList.toggle('hidden', !isAbs);
      document.getElementById('repModalAbsentBlock').classList.toggle('flex', isAbs);
      document.getElementById('repModalStatut').value = isAbs ? 'absent' : 'present';
    });
  });

  // Reason chip click
  document.querySelectorAll('#repCellModal .ss-rep-reason-chip').forEach(c => {
    c.addEventListener('click', () => {
      document.querySelectorAll('#repCellModal .ss-rep-reason-chip').forEach(b => b.classList.remove('on'));
      c.classList.add('on');
    });
  });

  // Shift opt click
  document.getElementById('repModalShiftGrid').addEventListener('click', e => {
    const b = e.target.closest('.ss-rep-shift-opt');
    if (!b) return;
    document.querySelectorAll('#repCellModal .ss-rep-shift-opt').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
  });

  // Module change → repopulate groupes
  document.getElementById('repModalModule').addEventListener('change', e => populateGroupeSelect(e.target.value));

  // Close handlers
  document.getElementById('repModalClose').addEventListener('click', closeCellModal);
  document.getElementById('repModalCancel').addEventListener('click', closeCellModal);
  document.getElementById('repCellModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeCellModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeCellModal(); closeExportModal(); } });

  // Save
  document.getElementById('repModalSave').addEventListener('click', async function () {
    if (!editingCell) return;
    const isAbs = document.querySelector('#repCellModal .ss-rep-status-btn.on')?.dataset.status === 'absent';
    const shiftBtn = document.querySelector('#repCellModal .ss-rep-shift-opt.on');
    const horaireId = shiftBtn ? shiftBtn.dataset.shiftId : '';
    const gSel = document.getElementById('repModalGroupe');
    const opt = gSel.options[gSel.selectedIndex];

    if (isAbs) {
      // Marquer absent : nécessite un assignation_id existant
      if (!editingCell.assignation_id) {
        toast('Un assignation existant est requis pour marquer une absence', 'error');
        return;
      }
      const reasonBtn = document.querySelector('#repCellModal .ss-rep-reason-chip.on');
      const absType = reasonBtn ? reasonBtn.dataset.reason : 'autre';
      this.disabled = true;
      const res = await adminApiPost('admin_mark_absent_repartition', {
        assignation_id: editingCell.assignation_id,
        absence_type: absType,
        motif: document.getElementById('repModalNotes').value,
      });
      this.disabled = false;
      if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
      toast('Absence enregistrée', 'success');
      closeCellModal();
      loadWeek(currentWeekISO);
      return;
    }

    // Save normal
    const p = {
      assignation_id: editingCell.assignation_id || '',
      planning_id: editingCell.planning_id || '',
      user_id: editingCell.user_id,
      date_jour: editingCell.date,
      horaire_type_id: horaireId || null,
      module_id: document.getElementById('repModalModule').value || null,
      groupe_id: gSel.value || null,
      etage_id: opt ? (opt.dataset.etageId || null) : null,
      statut: document.getElementById('repModalStatut').value,
      notes: document.getElementById('repModalNotes').value,
      expected_updated_at: editingCell.updated_at || null,
    };
    this.disabled = true;
    const res = await adminApiPost('admin_save_repartition_cell', p);
    this.disabled = false;
    if (res.conflict) { toast('Conflit. Rechargement…', 'error'); loadWeek(currentWeekISO); return; }
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Enregistré', 'success');
    closeCellModal();
    loadWeek(currentWeekISO);
  });

  // Delete
  document.getElementById('repModalDelete').addEventListener('click', async function () {
    if (!editingCell || !editingCell.assignation_id || !confirm('Supprimer ce poste ?')) return;
    this.disabled = true;
    const res = await adminApiPost('admin_delete_repartition_cell', { assignation_id: editingCell.assignation_id });
    this.disabled = false;
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Supprimé', 'success');
    closeCellModal();
    loadWeek(currentWeekISO);
  });

  // Duplicate (placeholder)
  document.getElementById('repModalDuplicate').addEventListener('click', () => toast('Duplication multi-jours : à venir', 'info'));

  // ── Cell click → open modal (works for week cells AND day rows) ───
  document.getElementById('repGrid').addEventListener('click', e => {
    const cellEl = e.target.closest('.ss-rep-cell');
    if (cellEl) {
      if (cellEl.classList.contains('empty')) return;
      openCellModal(cellEl);
      return;
    }
    const rowEl = e.target.closest('.ss-rep-day-row');
    if (rowEl) {
      if (e.target.closest('.ss-rep-day-action-btn')) return; // let action btn handle
      openCellModal(rowEl);
    }
  });

  // ── Drag & Drop entre modules ─────────────────────────────────────
  const grid = document.getElementById('repGrid');
  grid.addEventListener('dragstart', e => {
    if (!editMode) { e.preventDefault(); return; }
    const cell = e.target.closest('.ss-rep-cell');
    if (!cell || !cell.dataset.assignationId) { e.preventDefault(); return; }
    cell.classList.add('dragging');
    dragData = { ...cell.dataset };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragData.assignationId);
  });
  grid.addEventListener('dragend', e => {
    const cell = e.target.closest('.ss-rep-cell');
    if (cell) cell.classList.remove('dragging');
    document.querySelectorAll('.drag-over,.drag-over-mod').forEach(el => el.classList.remove('drag-over', 'drag-over-mod'));
    dragData = null;
  });
  grid.addEventListener('dragover', e => {
    if (!editMode || !dragData) return;
    if (e.target.closest('.ss-rep-module-head[data-drop-module-id]') || e.target.closest('.ss-rep-cell')) {
      e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    }
  });
  grid.addEventListener('dragenter', e => {
    if (!editMode || !dragData) return;
    document.querySelectorAll('.drag-over,.drag-over-mod').forEach(el => el.classList.remove('drag-over', 'drag-over-mod'));
    const mh = e.target.closest('.ss-rep-module-head[data-drop-module-id]');
    if (mh) mh.classList.add('drag-over-mod');
    const cell = e.target.closest('.ss-rep-cell');
    if (cell) cell.classList.add('drag-over');
  });
  grid.addEventListener('drop', async e => {
    e.preventDefault();
    if (!editMode || !dragData) return;
    document.querySelectorAll('.drag-over,.drag-over-mod').forEach(el => el.classList.remove('drag-over', 'drag-over-mod'));

    let targetModId = null;
    const mh = e.target.closest('.ss-rep-module-head[data-drop-module-id]');
    const cell = e.target.closest('.ss-rep-cell');
    if (mh) targetModId = mh.dataset.dropModuleId;
    else if (cell) { const sec = cell.closest('.ss-rep-module'); if (sec) targetModId = sec.dataset.sectionModuleId; }
    if (!targetModId || targetModId === dragData.moduleId) { dragData = null; return; }

    const tMod = (data.modules || []).find(m => m.id === targetModId);
    if (!tMod) { dragData = null; return; }

    let opts = [];
    (tMod.etages || []).forEach(et => {
      (et.groupes || []).forEach(gr => opts.push({ etageId: et.id, groupeId: gr.id, label: et.code + '-' + gr.code }));
      if (!et.groupes || !et.groupes.length) opts.push({ etageId: et.id, groupeId: null, label: et.code });
    });

    if (opts.length <= 1) {
      const o = opts[0] || { etageId: null, groupeId: null };
      await doMove(targetModId, o.etageId, o.groupeId, tMod.code);
    } else {
      // Pas de modale dédiée — on prend le premier groupe par défaut + on demande confirmation
      const choice = prompt('Étage / groupe cible :\n' + opts.map((o, i) => (i+1) + '. ' + o.label).join('\n') + '\n\nNuméro :', '1');
      const idx = Math.max(0, Math.min(opts.length-1, (parseInt(choice,10)||1) - 1));
      const o = opts[idx];
      await doMove(targetModId, o.etageId, o.groupeId, tMod.code);
    }
    dragData = null;
  });

  async function doMove(targetModId, etageId, groupeId, modCode) {
    const dd = dragData;
    if (!dd) return;
    const res = await adminApiPost('admin_save_repartition_cell', {
      assignation_id: dd.assignationId, planning_id: dd.planningId, user_id: dd.userId,
      date_jour: dd.date, horaire_type_id: dd.horaireTypeId || null,
      module_id: targetModId, groupe_id: groupeId, etage_id: etageId,
      statut: dd.statut || 'present', notes: dd.notes || '',
      expected_updated_at: dd.updatedAt || null,
    });
    if (res.conflict) { toast('Conflit. Rechargement…', 'error'); loadWeek(currentWeekISO); return; }
    if (!res.success) { toast(res.message || 'Erreur', 'error'); return; }
    toast('Déplacé vers ' + modCode, 'success');
    loadWeek(currentWeekISO);
  }

  // ── Mouse drag-to-scroll ──────────────────────────────────────────
  (function () {
    let dragEl = null, startX = 0, startScroll = 0;

    grid.addEventListener('mousedown', e => {
      const sec = e.target.closest('.ss-rep-module-body');
      if (!sec) return;
      if (e.target.closest('select, input, button')) return;
      if (editMode && e.target.closest('.ss-rep-cell[draggable]')) return;
      dragEl = sec;
      startX = e.pageX;
      startScroll = sec.scrollLeft;
      sec.style.cursor = 'grabbing';
      sec.style.userSelect = 'none';
      e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
      if (!dragEl) return;
      dragEl.scrollLeft = startScroll - (e.pageX - startX);
    });
    document.addEventListener('mouseup', () => {
      if (!dragEl) return;
      dragEl.style.cursor = '';
      dragEl.style.userSelect = '';
      dragEl = null;
    });
  })();

  // ── Filter chips click ────────────────────────────────────────────
  document.getElementById('repModFilter').addEventListener('click', e => {
    const chip = e.target.closest('.ss-rep-mf-chip');
    if (!chip) return;
    activeFilter = chip.dataset.filter || 'all';
    document.querySelectorAll('#repModFilter .ss-rep-mf-chip').forEach(c => c.classList.toggle('on', c === chip));
    document.querySelectorAll('.ss-rep-module').forEach(m => {
      if (activeFilter === 'all' || m.dataset.mod === activeFilter) m.classList.remove('hidden');
      else m.classList.add('hidden');
    });
  });

  // ── View toggle ───────────────────────────────────────────────────
  function updateLabel() {
    if (viewMode === 'day' && selectedDay) {
      const dt = new Date(selectedDay + 'T00:00:00');
      const fr = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
      const ms = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
      const dow = (dt.getDay() + 6) % 7;
      document.getElementById('repWeekLabel').textContent = fr[dow].charAt(0).toUpperCase() + fr[dow].slice(1) + ' ' + dt.getDate() + ' ' + ms[dt.getMonth() + 1] + ' ' + dt.getFullYear();
    } else {
      document.getElementById('repWeekLabel').textContent = data.week_label || '';
    }
  }
  document.querySelectorAll('#repViewToggle button').forEach(btn => {
    btn.addEventListener('click', () => {
      viewMode = btn.dataset.view;
      document.querySelectorAll('#repViewToggle button').forEach(b => b.classList.toggle('on', b === btn));
      if (viewMode === 'day' && !selectedDay) selectedDay = TODAY_ISO;
      updateLabel();
      renderGrid();
    });
  });

  // ── Navigation ────────────────────────────────────────────────────
  function navigateStep(dir) {
    if (viewMode === 'day') {
      const d = new Date(selectedDay + 'T00:00:00');
      d.setDate(d.getDate() + dir);
      selectedDay = dateToStr(d);
      const allDates = (data.days || []).map(x => x.date);
      if (allDates.indexOf(selectedDay) === -1) loadWeek(selectedDay);
      else { updateLabel(); renderGrid(); }
    } else {
      const mon = getMondayOfISOWeek(currentWeekISO);
      if (mon) { mon.setDate(mon.getDate() + dir * 7); loadWeek(dateToStr(mon)); }
    }
  }
  document.getElementById('repPrevWeek').addEventListener('click', () => navigateStep(-1));
  document.getElementById('repNextWeek').addEventListener('click', () => navigateStep(1));
  document.getElementById('repToday').addEventListener('click', () => { selectedDay = TODAY_ISO; loadWeek(null); });
  document.getElementById('repDatePicker').addEventListener('change', e => { if (e.target.value) { selectedDay = e.target.value; loadWeek(e.target.value); } });
  document.getElementById('repPrint').addEventListener('click', () => window.print());

  // ═══════════════════════════════════════════════════════════════════
  // EXPORT — modal sélection + html2canvas + JSZip
  // ═══════════════════════════════════════════════════════════════════
  let exportFormat = 'png';

  function MOD_META(modCode) {
    const k = modKey(modCode);
    const map = {
      rj:   { code:'RJ',   label:'RJ-RJN_Responsables', full:'RJ / RJN — Responsables', bg1:'#164a42', bg2:'#1f6359' },
      m1:   { code:'M1',   label:'Module1',             full:'Module 1',                bg1:'#1f6359', bg2:'#2d8074' },
      m2:   { code:'M2',   label:'Module2',             full:'Module 2',                bg1:'#2d4a6b', bg2:'#456b8e' },
      m3:   { code:'M3',   label:'Module3',             full:'Module 3',                bg1:'#8a5a1a', bg2:'#b07a35' },
      m4:   { code:'M4',   label:'Module4',             full:'Module 4',                bg1:'#5e3a78', bg2:'#7d5896' },
      pool: { code:'POOL', label:'Pool',                full:'Pool / Remplacements',    bg1:'#8a3a30', bg2:'#a85850' },
      na:   { code:'NA',   label:'Non_assigne',         full:'Pool / Non assigné',      bg1:'#4a6661', bg2:'#6b8783' },
      nuit: { code:'NUIT', label:'Nuit',                full:'Équipe de nuit',          bg1:'#0d2a26', bg2:'#324e4a' },
    };
    return map[k] || map.na;
  }

  function openExportModal() {
    // Build module list from current sections
    const { sections } = buildSections();
    const mList = document.getElementById('repExportModuleList');
    mList.innerHTML = '';
    sections.forEach(sec => {
      const k = modKey(sec.module.code);
      const count = sec.functions.reduce((a,f) => a + f.slots.length, 0);
      const lbl = document.createElement('label');
      lbl.className = 'ss-rep-check-item';
      lbl.innerHTML = '<input type="checkbox" data-mod-key="' + k + '" data-mod-id="' + (sec.module.id||'') + '" data-mod-code="' + escapeHtml(sec.module.code||'') + '" checked>'
        + '<span class="swatch ss-rep-mod-sw-' + k + '"></span>'
        + '<span class="ci-text">' + escapeHtml(sec.module.nom || sec.module.code) + '</span>'
        + '<span class="ci-tag">' + count + '</span>';
      mList.appendChild(lbl);
    });

    // Build day list
    const dList = document.getElementById('repExportDayList');
    dList.innerHTML = '';
    (data.days || []).forEach((d, i) => {
      const dayName = d.full_name.charAt(0).toUpperCase() + d.full_name.slice(1);
      const lbl = document.createElement('label');
      lbl.className = 'ss-rep-check-item' + (d.is_weekend ? ' opacity-90' : '');
      lbl.innerHTML = '<input type="checkbox" data-day-idx="' + i + '" checked>'
        + '<span class="ci-text">' + dayName + ' <small>' + d.day_num + ' ' + escapeHtml(d.month_name) + ' ' + d.year + '</small></span>'
        + '<span class="ci-tag">' + dayName.charAt(0) + '</span>';
      dList.appendChild(lbl);
    });

    document.getElementById('repExportModal').classList.add('show');
    updateExportRecap();
  }

  function closeExportModal() {
    document.getElementById('repExportModal').classList.remove('show');
  }

  document.getElementById('repExportBtn').addEventListener('click', openExportModal);
  document.getElementById('repExportClose').addEventListener('click', closeExportModal);
  document.getElementById('repExportCancel').addEventListener('click', closeExportModal);
  document.getElementById('repExportModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeExportModal(); });

  document.getElementById('repExportToggleAllMod').addEventListener('click', () => {
    const items = document.querySelectorAll('#repExportModuleList input[type=checkbox]');
    const all = Array.from(items).every(c => c.checked);
    items.forEach(c => c.checked = !all);
    updateExportRecap();
  });
  document.getElementById('repExportToggleAllDay').addEventListener('click', () => {
    const items = document.querySelectorAll('#repExportDayList input[type=checkbox]');
    const all = Array.from(items).every(c => c.checked);
    items.forEach(c => c.checked = !all);
    updateExportRecap();
  });

  document.getElementById('repExportModal').addEventListener('change', e => {
    if (e.target.matches('#repExportModuleList input, #repExportDayList input')) updateExportRecap();
  });

  document.querySelectorAll('#repExportModal .ss-rep-format-btn').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('#repExportModal .ss-rep-format-btn').forEach(x => x.classList.remove('on'));
      b.classList.add('on');
      exportFormat = b.dataset.fmt;
      updateExportRecap();
    });
  });

  function getSelectedModulesForExport() {
    return Array.from(document.querySelectorAll('#repExportModuleList input[type=checkbox]:checked')).map(c => ({
      key: c.dataset.modKey, id: c.dataset.modId, code: c.dataset.modCode,
    }));
  }
  function getSelectedDays() {
    return Array.from(document.querySelectorAll('#repExportDayList input[type=checkbox]:checked')).map(c => parseInt(c.dataset.dayIdx, 10));
  }

  function updateExportRecap() {
    const mods = getSelectedModulesForExport();
    const days = getSelectedDays();
    const total = mods.length * days.length;
    document.getElementById('repExportRecapCount').textContent = total;

    const ext = exportFormat === 'jpeg' ? 'jpg' : 'png';
    const file = document.getElementById('repExportRecapFile');
    const btn = document.getElementById('repExportLaunch');

    if (total === 0) {
      file.innerHTML = '<span class="text-muted-2">Aucun fichier — sélectionnez au moins un module et un jour</span>';
      btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed');
    } else if (total === 1) {
      const m = MOD_META(mods[0].code);
      const d = data.days[days[0]];
      file.innerHTML = '<strong>1 fichier :</strong> ' + escapeHtml(m.label) + '_' + escapeHtml(d.full_name) + '_' + d.day_num + '_' + d.year + '.' + ext;
      btn.disabled = false; btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
      file.innerHTML = '<span class="text-teal-600 font-bold">📦</span> <strong>Spocspace_Repartition_' + escapeHtml(currentWeekISO) + '.zip</strong> · ' + total + ' fichiers ' + ext.toUpperCase();
      btn.disabled = false; btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
  }

  // ── Capture HTML for one module × one day ─────────────────────────
  function getModuleDataForDay(modKey, day) {
    const moduleEl = document.querySelector('.ss-rep-module[data-mod="' + modKey + '"]');
    if (!moduleEl) return [];
    const rows = [];
    const trList = moduleEl.querySelectorAll('tbody tr');
    let currentFonction = null;
    trList.forEach(tr => {
      const fnTd = tr.querySelector('td.ss-rep-col-fonction');
      if (fnTd) {
        const lab = fnTd.querySelector('.label');
        currentFonction = lab ? lab.childNodes[0].textContent.trim() : fnTd.textContent.trim().split('\n')[0];
      }
      const posteTd = tr.querySelector('td.ss-rep-col-poste');
      if (!posteTd) return;
      const poste = posteTd.textContent.trim();
      const tds = tr.querySelectorAll('td');
      const dayCells = Array.from(tds).filter(t => !t.classList.contains('ss-rep-col-fonction') && !t.classList.contains('ss-rep-col-poste'));
      const dayIdx = (data.days || []).findIndex(x => x.date === day.date);
      const targetTd = dayCells[dayIdx];
      if (!targetTd) return;
      const cell = targetTd.querySelector('.ss-rep-cell');
      if (!cell || cell.classList.contains('empty')) return;

      const name = cell.dataset.userName || '';
      const horCode = (cell.querySelector('.ss-rep-shift') || {}).textContent || '';
      const horClass = horCode ? shiftClass(horCode) : '';
      const etage = (cell.querySelector('.ss-rep-cell-etage') || {}).textContent.trim() || '—';
      const isAbsent = cell.classList.contains('absent');

      rows.push({ fonction: currentFonction || '—', poste, name, horCode, horClass, etage, isAbsent });
    });
    return rows;
  }

  const SHIFT_INLINE = {
    'a2':{bg:'#d2e7e2',fg:'#164a42'}, 'a3':{bg:'#d2e7e2',fg:'#164a42'},
    'c1':{bg:'#a8d1c8',fg:'#0d2a26'}, 'c2':{bg:'#a8d1c8',fg:'#0d2a26'},
    'd1':{bg:'#e2ecf2',fg:'#3a6a8a'},
    'd3':{bg:'#fbf0e1',fg:'#8a5a1a'},
    'd4':{bg:'#fde8e6',fg:'#8a3a30'},
    's3':{bg:'#f0e8f5',fg:'#5e3a78'}, 's4':{bg:'#f0e8f5',fg:'#5e3a78'},
    'n1':{bg:'#0d2a26',fg:'#a8e6c9'},
    'piquet':{bg:'#e6ecf2',fg:'#2d4a6b'},
    'default':{bg:'#e3ebe8',fg:'#324e4a'},
  };
  function shiftBadgeInline(code) {
    if (!code) return '';
    const k = String(code).toLowerCase().replace(/[^a-z0-9]/g,'');
    const s = SHIFT_INLINE[k] || SHIFT_INLINE.default;
    const isLong = code.length > 3;
    return '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:24px;padding:0 9px;background:' + s.bg + ';color:' + s.fg + ';font-family:\'JetBrains Mono\',monospace;font-size:' + (isLong?'9.5':'11') + 'px;font-weight:700;border-radius:5px;letter-spacing:.02em;border:1px solid rgba(0,0,0,.06)">' + escapeHtml(code) + '</span>';
  }

  function buildCaptureCard(modKey, day, rows) {
    const meta = MOD_META(modKey);
    const present = rows.filter(r => !r.isAbsent).length;
    const absent  = rows.filter(r => r.isAbsent).length;

    const rowHtml = rows.map(i => {
      const bg = i.isAbsent ? '#f7e3e0' : '#ffffff';
      const nameColor = i.isAbsent ? '#b8443a' : '#0d2a26';
      const nameW = i.isAbsent ? '600' : '500';
      const nameDeco = i.isAbsent ? 'text-decoration:line-through;text-decoration-color:rgba(184,68,58,.4);' : '';
      const etBg = i.isAbsent ? '#f0d4cf' : '#f3f6f5';
      const etColor = i.isAbsent ? '#b8443a' : '#6b8783';
      const etTxt = i.isAbsent ? 'Absent·e' : i.etage;

      return '<tr style="border-bottom:1px solid #e3ebe8">'
        + '<td style="padding:10px 14px;font-size:12px;font-weight:600;color:#0d2a26;border-right:1px solid #e3ebe8;background:#fafbfa;width:170px">' + escapeHtml(i.fonction) + '</td>'
        + '<td style="padding:10px 8px;font-family:\'JetBrains Mono\',monospace;font-size:11.5px;color:#6b8783;font-weight:600;border-right:1px solid #e3ebe8;text-align:center;width:60px">' + escapeHtml(i.poste) + '</td>'
        + '<td style="padding:10px 14px;background:' + bg + ';border-right:1px solid #e3ebe8">'
        +   '<div style="display:flex;align-items:center;gap:10px">'
        +     (i.isAbsent ? '<span style="width:18px;height:18px;border-radius:50%;background:#b8443a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;line-height:1">!</span>' : '')
        +     '<span style="flex:1;font-size:13.5px;color:' + nameColor + ';font-weight:' + nameW + ';' + nameDeco + '">' + escapeHtml(i.name) + '</span>'
        +     shiftBadgeInline(i.horCode)
        +   '</div>'
        + '</td>'
        + '<td style="padding:10px 8px;font-family:\'JetBrains Mono\',monospace;font-size:11px;color:' + etColor + ';font-weight:600;text-align:center;background:' + etBg + ';width:110px">' + escapeHtml(etTxt) + '</td>'
        + '</tr>';
    }).join('');

    const empty = rows.length === 0
      ? '<tr><td colspan="4" style="padding:50px;text-align:center;color:#6b8783;font-size:13px;font-style:italic;background:#fafbfa">Aucune affectation pour ce jour</td></tr>'
      : '';

    return ''
      + '<div style="background:#fff;border-radius:14px;overflow:hidden;font-family:\'Outfit\',-apple-system,sans-serif;color:#0d2a26;width:1192px;border:1px solid #e3ebe8">'
      +   '<div style="background:linear-gradient(135deg,' + meta.bg1 + ' 0%,' + meta.bg2 + ' 100%);padding:22px 26px;color:#fff;position:relative">'
      +     '<div style="display:flex;align-items:center;justify-content:space-between;gap:20px">'
      +       '<div style="display:flex;align-items:center;gap:14px">'
      +         '<div style="width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,#3da896,#7dd3a8);display:flex;align-items:center;justify-content:center;font-family:\'Fraunces\',Georgia,serif;font-weight:700;color:#0d2a26;font-size:22px">S</div>'
      +         '<div>'
      +           '<div style="font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:3px">Spocspace · Répartition</div>'
      +           '<div style="font-family:\'Fraunces\',Georgia,serif;font-size:23px;font-weight:600;letter-spacing:-.01em;line-height:1.1">' + escapeHtml(meta.full) + '</div>'
      +         '</div>'
      +       '</div>'
      +       '<div style="text-align:right">'
      +         '<div style="font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:3px">' + escapeHtml(day.full_name.charAt(0).toUpperCase() + day.full_name.slice(1)) + '</div>'
      +         '<div style="font-family:\'Fraunces\',Georgia,serif;font-size:24px;font-weight:600;letter-spacing:-.01em;line-height:1.1">' + day.day_num + ' ' + escapeHtml(day.month_name) + ' ' + day.year + '</div>'
      +       '</div>'
      +     '</div>'
      +     '<div style="display:flex;gap:30px;margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.18)">'
      +       '<div><div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Postes</div><div style="font-family:\'Fraunces\',Georgia,serif;font-size:20px;font-weight:600">' + rows.length + '</div></div>'
      +       '<div><div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Présent·es</div><div style="font-family:\'Fraunces\',Georgia,serif;font-size:20px;font-weight:600">' + present + '</div></div>'
      +       '<div><div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Absent·es</div><div style="font-family:\'Fraunces\',Georgia,serif;font-size:20px;font-weight:600;color:' + (absent>0?'#fbb6ad':'#fff') + '">' + absent + '</div></div>'
      +       '<div style="margin-left:auto;text-align:right"><div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Module</div><div style="font-family:\'JetBrains Mono\',monospace;font-size:14px;font-weight:600">' + escapeHtml(meta.code) + '</div></div>'
      +     '</div>'
      +   '</div>'
      +   '<table style="width:100%;border-collapse:collapse">'
      +     '<thead><tr style="background:#fafbfa;border-bottom:1px solid #e3ebe8">'
      +       '<th style="padding:11px 14px;text-align:left;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8;width:170px">Fonction</th>'
      +       '<th style="padding:11px 8px;text-align:center;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8;width:60px">Poste</th>'
      +       '<th style="padding:11px 14px;text-align:left;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8">Collaborateur · Horaire</th>'
      +       '<th style="padding:11px 8px;text-align:center;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;width:110px">Étage</th>'
      +     '</tr></thead>'
      +     '<tbody>' + rowHtml + empty + '</tbody>'
      +   '</table>'
      +   '<div style="padding:13px 26px;background:#fafbfa;border-top:1px solid #e3ebe8;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#6b8783">'
      +     '<div style="display:flex;align-items:center;gap:8px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b8783" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg><span>Spocspace · Répartition</span></div>'
      +     '<div style="font-family:\'JetBrains Mono\',monospace">Généré le ' + new Date().toLocaleDateString('fr-CH') + ' à ' + new Date().toLocaleTimeString('fr-CH', {hour:'2-digit',minute:'2-digit'}) + '</div>'
      +   '</div>'
      + '</div>';
  }

  async function captureOne(modKey, day) {
    const stage = document.getElementById('repCaptureStage');
    const rows = getModuleDataForDay(modKey, day);
    stage.innerHTML = buildCaptureCard(modKey, day, rows);
    await new Promise(r => setTimeout(r, 80));
    if (typeof html2canvas === 'undefined') throw new Error('html2canvas non chargé');
    const canvas = await html2canvas(stage.firstElementChild, {
      backgroundColor: exportFormat === 'jpeg' ? '#ffffff' : null,
      scale: 2, useCORS: true, logging: false,
    });
    return canvas;
  }

  function downloadDataURL(dataURL, filename) {
    const a = document.createElement('a');
    a.href = dataURL; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  }
  function canvasToBlob(canvas, fmt) {
    return new Promise(r => canvas.toBlob(b => r(b), 'image/' + fmt, fmt === 'jpeg' ? 0.95 : 1.0));
  }

  document.getElementById('repExportLaunch').addEventListener('click', async () => {
    const mods = getSelectedModulesForExport();
    const dayIdx = getSelectedDays();
    if (!mods.length || !dayIdx.length) return;

    const fmt = exportFormat === 'jpeg' ? 'jpeg' : 'png';
    const ext = fmt === 'jpeg' ? 'jpg' : 'png';
    const days = dayIdx.map(i => data.days[i]);

    const tasks = [];
    mods.forEach(m => days.forEach(d => tasks.push({ modKey: m.key, day: d })));

    closeExportModal();

    const overlay = document.getElementById('repExportProgress');
    const card    = document.getElementById('repExportProgressCard');
    const bar     = document.getElementById('repExportBar');
    const cur     = document.getElementById('repExportCurrent');
    const doneEl  = document.getElementById('repExportDone');
    const totalEl = document.getElementById('repExportTotal');
    const pctEl   = document.getElementById('repExportPct');
    const titleEl = document.getElementById('repExportTitle');
    const stage   = document.getElementById('repCaptureStage');
    const doneMsg = document.getElementById('repExportDoneMsg');
    const closeBtn = document.getElementById('repExportProgressClose');

    doneMsg.classList.add('hidden'); closeBtn.classList.add('hidden');
    document.querySelector('#repExportProgressCard .ss-rep-spin').classList.remove('hidden');
    overlay.classList.add('show');
    totalEl.textContent = tasks.length; doneEl.textContent = '0';
    bar.style.width = '0%'; pctEl.textContent = '0%';
    titleEl.textContent = tasks.length === 1 ? 'Génération de l\'image…' : 'Génération de ' + tasks.length + ' images…';

    if (tasks.length === 1) {
      const { modKey, day } = tasks[0];
      const meta = MOD_META(modKey);
      const filename = meta.label + '_' + day.full_name + '_' + day.day_num + '_' + day.year + '.' + ext;
      cur.textContent = filename;
      try {
        const canvas = await captureOne(modKey, day);
        const dataURL = canvas.toDataURL('image/' + fmt, fmt === 'jpeg' ? 0.95 : 1.0);
        downloadDataURL(dataURL, filename);
      } catch (err) { console.error(err); toast('Erreur lors de la génération', 'error'); }
      stage.innerHTML = '';
      doneEl.textContent = '1'; bar.style.width = '100%'; pctEl.textContent = '100%';
      titleEl.textContent = 'Export terminé';
      doneMsg.classList.remove('hidden'); doneMsg.classList.add('flex');
      closeBtn.classList.remove('hidden');
      document.querySelector('#repExportProgressCard .ss-rep-spin').classList.add('hidden');
      return;
    }

    if (typeof JSZip === 'undefined') { toast('JSZip non chargé', 'error'); return; }
    const zip = new JSZip();
    const folder = zip.folder('Spocspace_Repartition_' + currentWeekISO);

    let done = 0;
    for (const task of tasks) {
      const { modKey, day } = task;
      const meta = MOD_META(modKey);
      const filename = meta.label + '_' + day.full_name + '_' + day.day_num + '_' + day.year + '.' + ext;
      cur.textContent = filename;
      try {
        const canvas = await captureOne(modKey, day);
        const blob = await canvasToBlob(canvas, fmt);
        folder.file(filename, blob);
      } catch (err) { console.error('Erreur ' + filename, err); }
      done++; doneEl.textContent = done;
      const pct = Math.round((done / tasks.length) * 100);
      bar.style.width = pct + '%'; pctEl.textContent = pct + '%';
      await new Promise(r => setTimeout(r, 30));
    }

    titleEl.textContent = 'Compression du ZIP…';
    cur.textContent = 'Spocspace_Repartition_' + currentWeekISO + '.zip';
    const zipBlob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } });
    const zipURL = URL.createObjectURL(zipBlob);
    downloadDataURL(zipURL, 'Spocspace_Repartition_' + currentWeekISO + '.zip');
    setTimeout(() => URL.revokeObjectURL(zipURL), 5000);

    stage.innerHTML = '';
    titleEl.textContent = 'Export terminé';
    cur.textContent = 'Spocspace_Repartition_' + currentWeekISO + '.zip · ' + tasks.length + ' fichiers';
    doneMsg.classList.remove('hidden'); doneMsg.classList.add('flex');
    closeBtn.classList.remove('hidden');
    document.querySelector('#repExportProgressCard .ss-rep-spin').classList.add('hidden');
  });

  document.getElementById('repExportProgressClose').addEventListener('click', () => {
    document.getElementById('repExportProgress').classList.remove('show');
  });

  // ── Init ──────────────────────────────────────────────────────────
  window.initRepartitionPage = function () {
    buildShiftGrid();
    populateModalSelects();
    renderFilterAndStats();
    renderGrid();
  };

})();
</script>
