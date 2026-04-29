<?php
/**
 * Tableau de bord — Module Planning (Tailwind/Spocspace Care)
 * Mockup utilisateur 29 avril 2026.
 */

// ─── Données serveur ─────────────────────────────────────────────────────────
$today = date('Y-m-d');
$adminPrenom = $admin['prenom'] ?? '';
$adminNom    = $admin['nom']    ?? '';
$adminRole   = $admin['role']   ?? 'admin';
$roleLabels = [
    'admin'       => 'Admin',
    'direction'   => 'Direction',
    'responsable' => 'Resp. planning',
];
$adminRoleLabel = $roleLabels[$adminRole] ?? ucfirst($adminRole);
$adminInitials = h(mb_substr($adminPrenom, 0, 1) . mb_substr($adminNom, 0, 1));

$totalUsers     = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1");
$pendingAbs     = (int) Db::getOne("SELECT COUNT(*) FROM absences WHERE statut = 'en_attente'");
$pendingDesirs  = (int) Db::getOne("SELECT COUNT(*) FROM desirs WHERE statut = 'en_attente'");
try { $pendingChg = (int) Db::getOne("SELECT COUNT(*) FROM changements WHERE statut = 'en_attente'"); } catch (\Throwable $e) { $pendingChg = 0; }
try { $pendingEch = (int) Db::getOne("SELECT COUNT(*) FROM echanges WHERE statut = 'en_attente'"); } catch (\Throwable $e) { $pendingEch = 0; }
$unreadMsgs     = (int) Db::getOne("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0", [$admin['id'] ?? '']);

// Stats du jour : combien de personnes en absence/vacances aujourd'hui
try {
    $absentsToday = (int) Db::getOne(
        "SELECT COUNT(DISTINCT user_id) FROM absences
         WHERE statut = 'valide' AND type IN ('maladie','accident','autre')
         AND date_debut <= ? AND date_fin >= ?",
        [$today, $today]
    );
} catch (\Throwable $e) { $absentsToday = 0; }
try {
    $vacancesToday = (int) Db::getOne(
        "SELECT COUNT(DISTINCT user_id) FROM absences
         WHERE statut = 'valide' AND type = 'vacances'
         AND date_debut <= ? AND date_fin >= ?",
        [$today, $today]
    );
} catch (\Throwable $e) { $vacancesToday = 0; }
$presentsToday = max(0, $totalUsers - $absentsToday - $vacancesToday);

// Nouveaux ce mois-ci
$newThisMonth = (int) Db::getOne("SELECT COUNT(*) FROM users WHERE is_active = 1 AND created_at >= ?", [date('Y-m-01')]);

// Comptes par statut absence
$absCounts = [];
try {
    $rows = Db::fetchAll("SELECT statut, COUNT(*) as n FROM absences GROUP BY statut");
    foreach ($rows as $r) $absCounts[$r['statut']] = (int) $r['n'];
} catch (\Throwable $e) {}
$cntAttente = $absCounts['en_attente'] ?? 0;
$cntValide  = $absCounts['valide']     ?? 0;
$cntRefuse  = $absCounts['refuse']     ?? 0;
$cntTotal   = array_sum($absCounts);

// Sous-effectif heuristique : 1ère absence validée chevauchant aujourd'hui sur fonction soins
try {
    $soufEffectifRow = Db::fetch(
        "SELECT a.*, u.prenom, u.nom, f.nom as fonction_nom, f.code as fonction_code
         FROM absences a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE a.statut = 'valide' AND a.type IN ('maladie','accident')
         AND a.date_debut <= ? AND a.date_fin >= ?
         ORDER BY a.created_at DESC LIMIT 1",
        [$today, $today]
    );
} catch (\Throwable $e) { $soufEffectifRow = null; }

// Liste des absences à traiter
try {
    $pendingList = Db::fetchAll(
        "SELECT a.*, u.prenom, u.nom, u.photo, u.taux, f.nom as fonction_nom, f.code as fonction_code
         FROM absences a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE a.statut = 'en_attente'
         ORDER BY a.date_debut ASC
         LIMIT 10"
    );
} catch (\Throwable $e) { $pendingList = []; }

// Mappings type → couleurs Spocspace Care
$typeColors = [
    'maladie'   => ['bg' => 'bg-danger-bg',  'text' => 'text-danger',  'dot' => 'bg-danger',  'label' => 'Maladie'],
    'accident'  => ['bg' => 'bg-warn-bg',    'text' => 'text-warn',    'dot' => 'bg-warn',    'label' => 'Accident'],
    'vacances'  => ['bg' => 'bg-info-bg',    'text' => 'text-info',    'dot' => 'bg-info',    'label' => 'Vacances'],
    'formation' => ['bg' => 'bg-ok-bg',      'text' => 'text-ok',      'dot' => 'bg-ok',      'label' => 'Formation'],
    'personnel' => ['bg' => 'bg-warn-bg',    'text' => 'text-warn',    'dot' => 'bg-warn',    'label' => 'Personnel'],
    'autre'     => ['bg' => 'bg-surface-3',  'text' => 'text-ink-3',   'dot' => 'bg-muted-2', 'label' => 'Autre'],
];
$avatarBg = ['bg-danger', 'bg-teal-600', 'bg-info', 'bg-warn', 'bg-ok', 'bg-teal-700', 'bg-sec-anim', 'bg-sec-admin'];

// Date FR formatée
$frDays = ['','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$frMonths = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$dayName  = $frDays[(int) date('N')];
$monthName= $frMonths[(int) date('n')];
$dateStr  = strtoupper("$dayName " . date('j') . " $monthName " . date('Y'));

$totalPending = $pendingAbs + $pendingDesirs + $pendingChg + $pendingEch;
?>
<div class="max-w-7xl mx-auto space-y-6">

  <!-- ───── HERO : greeting + counters + alerte sous-effectif ───── -->
  <section class="bg-grad-hero rounded-2xl p-7 lg:p-8 text-white shadow-sp-md relative overflow-hidden">
    <!-- Décoration discrète -->
    <div aria-hidden="true" class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-[#7dd3a8]/10 blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
      <div class="flex-1 min-w-0">
        <div class="text-[11px] tracking-[0.18em] uppercase text-[#a8d1c8] font-semibold mb-2"><?= h($dateStr) ?></div>
        <h1 class="font-display text-3xl lg:text-4xl font-medium text-white tracking-[-0.01em] leading-tight">
          Bonjour <?= h($adminPrenom) ?><?php if ($adminNom): ?> <?= h($adminNom) ?><?php endif ?>
        </h1>
        <p class="mt-2 text-[15px] text-[#cfe0db] leading-relaxed">
          <?php if ($totalPending > 0): ?>
            <span class="font-semibold text-white"><?= $totalPending ?></span> demande<?= $totalPending > 1 ? 's' : '' ?> en attente sur votre bureau · une vue d'ensemble du planning ci-dessous.
          <?php else: ?>
            Aucune demande en attente — bonne journée !
          <?php endif ?>
        </p>
      </div>

      <!-- Counters jour J -->
      <div class="grid grid-cols-3 gap-6 lg:gap-10 lg:pl-8 lg:border-l lg:border-white/15">
        <div class="text-center lg:text-right">
          <div class="text-[10px] tracking-[0.16em] uppercase text-[#a8d1c8] font-semibold mb-1">Présents</div>
          <div class="font-display text-3xl lg:text-4xl font-medium text-white tabular-nums"><?= $presentsToday ?></div>
        </div>
        <div class="text-center lg:text-right">
          <div class="text-[10px] tracking-[0.16em] uppercase text-[#a8d1c8] font-semibold mb-1">Absents</div>
          <div class="font-display text-3xl lg:text-4xl font-medium text-white tabular-nums"><?= $absentsToday ?></div>
        </div>
        <div class="text-center lg:text-right">
          <div class="text-[10px] tracking-[0.16em] uppercase text-[#a8d1c8] font-semibold mb-1">En vacances</div>
          <div class="font-display text-3xl lg:text-4xl font-medium text-white tabular-nums"><?= $vacancesToday ?></div>
        </div>
      </div>
    </div>

    <?php if ($soufEffectifRow): ?>
    <!-- Alerte sous-effectif -->
    <div class="relative mt-6 flex items-center justify-between gap-4 bg-warn/15 border border-warn/30 rounded-xl px-4 py-3">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-warn/25 grid place-items-center text-warn shrink-0">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="min-w-0 text-[13.5px] text-white">
          <span class="font-semibold">Alerte sous-effectif</span>
          <span class="text-[#cfe0db]"> · 1&nbsp;<?= h($soufEffectifRow['fonction_code'] ?? $soufEffectifRow['fonction_nom'] ?: 'collaborateur') ?> manquant·e (<?= h(($soufEffectifRow['prenom'] ?? '') . ' ' . ($soufEffectifRow['nom'] ?? '')) ?> en <?= h($soufEffectifRow['type']) ?>)</span>
        </div>
      </div>
      <a href="<?= admin_url('planning') ?>" class="hidden sm:inline-flex items-center gap-1 text-warn hover:text-white text-[13px] font-medium underline-offset-2 hover:underline shrink-0">
        Trouver un remplaçant
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>
    <?php endif ?>
  </section>

  <!-- ───── 4 STATS CARDS ───── -->
  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

    <div class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden relative">
      <span class="absolute left-0 top-0 bottom-0 w-1 bg-ok"></span>
      <div class="p-5 pl-6">
        <div class="font-display text-3xl font-semibold text-ink tabular-nums leading-none"><?= $totalUsers ?></div>
        <div class="text-[13px] text-ink-2 mt-2">Collaborateurs actifs</div>
        <?php if ($newThisMonth > 0): ?>
        <div class="mt-3 pt-3 border-t border-line text-[12px] text-ok flex items-center gap-1">
          <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 15 12 9 18 15"/></svg>
          <span><?= $newThisMonth ?> nouveau<?= $newThisMonth > 1 ? 'x' : '' ?> ce mois-ci</span>
        </div>
        <?php endif ?>
      </div>
    </div>

    <div class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden relative">
      <span class="absolute left-0 top-0 bottom-0 w-1 bg-danger"></span>
      <div class="p-5 pl-6">
        <div class="font-display text-3xl font-semibold text-ink tabular-nums leading-none"><?= $pendingAbs ?></div>
        <div class="text-[13px] text-ink-2 mt-2">Absences en attente</div>
        <?php if ($pendingAbs > 0): ?>
        <div class="mt-3 pt-3 border-t border-line text-[12px] text-danger">
          <a href="<?= admin_url('absences') ?>" class="hover:underline">À traiter cette semaine</a>
        </div>
        <?php endif ?>
      </div>
    </div>

    <div class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden relative">
      <span class="absolute left-0 top-0 bottom-0 w-1 bg-warn"></span>
      <div class="p-5 pl-6">
        <div class="font-display text-3xl font-semibold text-ink tabular-nums leading-none"><?= $pendingDesirs ?></div>
        <div class="text-[13px] text-ink-2 mt-2">Désirs en attente</div>
        <?php if ($pendingDesirs > 0): ?>
        <div class="mt-3 pt-3 border-t border-line text-[12px] text-warn">
          <a href="<?= admin_url('desirs') ?>" class="hover:underline">Pour le prochain planning</a>
        </div>
        <?php endif ?>
      </div>
    </div>

    <div class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden relative">
      <span class="absolute left-0 top-0 bottom-0 w-1 bg-info"></span>
      <div class="p-5 pl-6">
        <div class="font-display text-3xl font-semibold text-ink tabular-nums leading-none"><?= $unreadMsgs ?></div>
        <div class="text-[13px] text-ink-2 mt-2">Messages non lus</div>
        <?php if ($unreadMsgs > 0): ?>
        <div class="mt-3 pt-3 border-t border-line text-[12px] text-info">
          <a href="<?= admin_url('messages') ?>" class="hover:underline">Ouvrir la messagerie</a>
        </div>
        <?php endif ?>
      </div>
    </div>
  </section>

  <!-- ───── TABS Demandes ───── -->
  <div class="flex flex-wrap items-center gap-2">
    <button class="inline-flex items-center gap-2 bg-teal-700 text-white px-4 py-2 rounded-lg text-[13px] font-medium shadow-sp-sm">
      <?= ss_icon('chat-square-text', 'w-4 h-4') ?>
      Absences
      <span class="bg-white/15 text-white text-[10.5px] font-mono font-bold rounded-full px-1.5 py-px"><?= $pendingAbs ?></span>
    </button>
    <a href="<?= admin_url('desirs') ?>" class="inline-flex items-center gap-2 bg-surface border border-line text-ink-2 hover:border-teal-300 hover:text-teal-600 px-4 py-2 rounded-lg text-[13px] font-medium transition-colors">
      <?= ss_icon('star', 'w-4 h-4') ?>
      Désirs
      <span class="bg-surface-3 text-muted text-[10.5px] font-mono font-bold rounded-full px-1.5 py-px"><?= $pendingDesirs ?></span>
    </a>
    <a href="<?= admin_url('changements') ?>" class="inline-flex items-center gap-2 bg-surface border border-line text-ink-2 hover:border-teal-300 hover:text-teal-600 px-4 py-2 rounded-lg text-[13px] font-medium transition-colors">
      <?= ss_icon('arrow-left-right', 'w-4 h-4') ?>
      Changements
      <span class="bg-surface-3 text-muted text-[10.5px] font-mono font-bold rounded-full px-1.5 py-px"><?= $pendingChg ?></span>
    </a>
    <a href="<?= admin_url('echanges') ?>" class="inline-flex items-center gap-2 bg-surface border border-line text-ink-2 hover:border-teal-300 hover:text-teal-600 px-4 py-2 rounded-lg text-[13px] font-medium transition-colors">
      <?= ss_icon('arrow-down-up', 'w-4 h-4') ?>
      Échanges
      <span class="bg-surface-3 text-muted text-[10.5px] font-mono font-bold rounded-full px-1.5 py-px"><?= $pendingEch ?></span>
    </a>
  </div>

  <!-- ───── TABLEAU absences à traiter ───── -->
  <section class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden">
    <header class="px-5 py-4 border-b border-line bg-gradient-to-b from-surface-2 to-surface flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <h3 class="font-display text-xl font-semibold text-ink tracking-[-0.01em]">Absences à traiter</h3>
        <p class="text-[13px] text-muted mt-0.5"><?= $cntAttente ?> demande<?= $cntAttente > 1 ? 's' : '' ?> en attente de validation</p>
      </div>
      <div class="flex items-center gap-1.5 text-[12.5px] flex-wrap">
        <span class="px-3 py-1.5 rounded-lg bg-teal-50 text-teal-700 border border-teal-200 font-medium">À traiter <span class="font-mono"><?= $cntAttente ?></span></span>
        <a href="<?= admin_url('absences') ?>?statut=valide" class="px-3 py-1.5 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors">Validées <span class="font-mono"><?= $cntValide ?></span></a>
        <a href="<?= admin_url('absences') ?>?statut=refuse" class="px-3 py-1.5 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors">Refusées <span class="font-mono"><?= $cntRefuse ?></span></a>
        <a href="<?= admin_url('absences') ?>" class="px-3 py-1.5 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors">Toutes</a>
        <span class="w-px h-4 bg-line mx-1"></span>
        <a href="<?= admin_url('absences') ?>?export=csv" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-muted hover:text-teal-600 hover:bg-surface-3 transition-colors">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Exporter
        </a>
      </div>
    </header>

    <?php if (!$pendingList): ?>
      <div class="p-10 text-center">
        <div class="inline-flex w-12 h-12 rounded-full bg-ok-bg text-ok items-center justify-center mb-3">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="font-display text-lg text-ink">Aucune absence à traiter</p>
        <p class="text-muted text-[13px] mt-1">Toutes les demandes ont été validées.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead>
          <tr class="bg-surface-2 text-[10.5px] tracking-[0.14em] uppercase text-muted font-semibold">
            <th class="text-left px-5 py-3 font-semibold">Date demandée</th>
            <th class="text-left px-3 py-3 font-semibold">Collaborateur</th>
            <th class="text-left px-3 py-3 font-semibold">Type</th>
            <th class="text-left px-3 py-3 font-semibold">Motif / Note</th>
            <th class="text-left px-3 py-3 font-semibold">Statut</th>
            <th class="text-right px-5 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingList as $i => $abs):
            $type = $abs['type'] ?? 'autre';
            $tc = $typeColors[$type] ?? $typeColors['autre'];
            $userInitials = h(mb_substr($abs['prenom'] ?? '', 0, 1) . mb_substr($abs['nom'] ?? '', 0, 1));
            $color = $avatarBg[$i % count($avatarBg)];
            $isUrgent = $abs['date_debut'] && strtotime($abs['date_debut']) <= strtotime('+3 days');
            $debutTs = $abs['date_debut'] ? strtotime($abs['date_debut']) : time();
            $finTs   = $abs['date_fin']   ? strtotime($abs['date_fin'])   : $debutTs;
            $duration = max(1, (int) (($finTs - $debutTs) / 86400) + 1);
            $monthLabel = strtoupper($frMonths[(int) date('n', $debutTs)]);
            $dayLabel = date('d', $debutTs);
            $isToday = date('Y-m-d', $debutTs) === $today;
            $isHalf = !empty($abs['demi_journee']);
            $taux = $abs['taux'] ?? null;
          ?>
          <tr class="border-t border-line hover:bg-surface-2 transition-colors">
            <td class="px-5 py-4 align-top">
              <div class="flex items-start gap-3">
                <div class="bg-surface-3 border border-line rounded-lg w-12 h-14 flex flex-col items-center justify-center shrink-0">
                  <div class="text-[9.5px] uppercase tracking-wide text-muted-2 font-semibold mt-0.5"><?= mb_substr($monthLabel, 0, 3) ?></div>
                  <div class="font-display text-xl font-semibold text-ink leading-none"><?= $dayLabel ?></div>
                </div>
                <div class="text-[12px] leading-tight pt-0.5">
                  <div class="text-ink-2"><?= $isToday ? 'Aujourd\'hui' : date('d.m', $debutTs) ?></div>
                  <div class="text-muted mt-0.5">
                    <?php if ($isHalf): ?>
                      demi-journée
                    <?php elseif ($duration > 1): ?>
                      jusqu'au <?= date('d.m', $finTs) ?> <span class="text-muted-2">·</span> <?= $duration ?> j
                    <?php else: ?>
                      1 jour
                    <?php endif ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="px-3 py-4 align-top">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full <?= $color ?> grid place-items-center text-white font-display font-semibold text-[12.5px] shrink-0"><?= $userInitials ?></div>
                <div class="min-w-0">
                  <div class="font-medium text-ink truncate"><?= h(($abs['prenom'] ?? '') . ' ' . ($abs['nom'] ?? '')) ?></div>
                  <div class="text-[11.5px] text-muted truncate">
                    <?= h($abs['fonction_code'] ?: $abs['fonction_nom'] ?: '—') ?>
                    <?php if ($taux): ?> · <?= (int) $taux ?>%<?php endif ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="px-3 py-4 align-top">
              <span class="inline-flex items-center gap-1.5 <?= $tc['bg'] ?> <?= $tc['text'] ?> text-[10.5px] font-semibold uppercase tracking-wide px-2 py-1 rounded">
                <span class="w-1.5 h-1.5 rounded-full <?= $tc['dot'] ?>"></span>
                <?= h($tc['label']) ?>
              </span>
            </td>
            <td class="px-3 py-4 align-top max-w-md">
              <div class="text-ink-2 truncate" title="<?= h($abs['motif'] ?? '') ?>"><?= h($abs['motif'] ?? '—') ?></div>
            </td>
            <td class="px-3 py-4 align-top">
              <?php if ($isUrgent): ?>
              <span class="inline-flex items-center gap-1.5 bg-danger-bg text-danger text-[10.5px] font-semibold uppercase tracking-wide px-2 py-1 rounded">
                <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                Urgent
              </span>
              <?php else: ?>
              <span class="inline-flex items-center gap-1.5 bg-warn-bg text-warn text-[10.5px] font-semibold uppercase tracking-wide px-2 py-1 rounded">
                <span class="w-1.5 h-1.5 rounded-full bg-warn"></span>
                En attente
              </span>
              <?php endif ?>
            </td>
            <td class="px-5 py-4 align-top text-right">
              <div class="inline-flex items-center gap-1">
                <a href="<?= admin_url('absences') ?>?id=<?= h($abs['id']) ?>" class="p-1.5 rounded-md border border-line text-muted hover:text-teal-600 hover:border-teal-300 transition-colors" title="Voir le détail">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                <button type="button" class="p-1.5 rounded-md border border-line text-muted hover:text-ok hover:border-ok-line hover:bg-ok-bg transition-colors" title="Valider rapidement" data-quick-validate="<?= h($abs['id']) ?>">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </section>

  <!-- ───── Footer banner : voir aussi ───── -->
  <?php if ($pendingDesirs + $pendingChg + $pendingEch > 0): ?>
  <div class="bg-info-bg border border-info-line rounded-xl px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex items-center gap-3 text-[13px] text-ink-2">
      <div class="w-8 h-8 rounded-full bg-info/15 grid place-items-center text-info shrink-0">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      </div>
      <div>
        <span class="text-muted">Voir aussi : </span>
        <?php $pieces = []; ?>
        <?php if ($pendingDesirs > 0): $pieces[] = '<a href="' . admin_url('desirs') . '" class="font-semibold text-info hover:underline">' . $pendingDesirs . ' désir' . ($pendingDesirs > 1 ? 's' : '') . '</a> à traiter pour le prochain planning'; endif ?>
        <?php if ($pendingChg > 0): $pieces[] = '<a href="' . admin_url('changements') . '" class="font-semibold text-info hover:underline">' . $pendingChg . ' demande' . ($pendingChg > 1 ? 's' : '') . ' de changement</a>'; endif ?>
        <?php if ($pendingEch > 0): $pieces[] = '<a href="' . admin_url('echanges') . '" class="font-semibold text-info hover:underline">' . $pendingEch . ' échange' . ($pendingEch > 1 ? 's' : '') . '</a> entre collègues'; endif ?>
        <?= implode(' <span class="text-muted-2">·</span> ', $pieces) ?>
      </div>
    </div>
    <a href="<?= admin_url('absences') ?>" class="inline-flex items-center gap-1 text-info hover:text-teal-600 text-[13px] font-medium shrink-0">
      Tout traiter
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
  </div>
  <?php endif ?>

</div>
