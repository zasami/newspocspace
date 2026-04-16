<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// ─── Événements ouverts ou fermés (visibles) ───
$events = Db::fetchAll(
    "SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.heure_debut, e.heure_fin,
            e.lieu, e.max_participants, e.statut, e.inscription_obligatoire,
            (SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = e.id AND statut = 'inscrit') AS nb_inscrits,
            (SELECT COUNT(*) FROM evenement_champs WHERE evenement_id = e.id) AS nb_champs,
            (SELECT id FROM evenement_inscriptions WHERE evenement_id = e.id AND user_id = ? AND statut = 'inscrit' LIMIT 1) AS mon_inscription_id
     FROM evenements e
     WHERE e.statut IN ('ouvert', 'ferme')
     ORDER BY e.date_debut ASC",
    [$uid]
);

// Séparer : à venir / passés
$now = date('Y-m-d');
$upcoming = array_filter($events, fn($e) => $e['date_debut'] >= $now);
$past = array_filter($events, fn($e) => $e['date_debut'] < $now);
$mesInscriptions = array_filter($events, fn($e) => $e['mon_inscription_id']);
?>
<div class="page-wrap">
    <?= render_page_header('Événements', 'bi-calendar-event') ?>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <?= render_stat_card('À venir', count($upcoming), 'bi-calendar-plus', 'teal') ?>
        <?= render_stat_card('Mes inscriptions', count($mesInscriptions), 'bi-check2-circle', 'green') ?>
        <?= render_stat_card('Total', count($events), 'bi-calendar-event', 'purple') ?>
    </div>

    <!-- Tabs -->
    <div class="ev-user-tabs mb-3" id="evUserTabs">
        <button class="ev-user-tab active" data-tab="upcoming">À venir (<?= count($upcoming) ?>)</button>
        <button class="ev-user-tab" data-tab="mine">Mes inscriptions (<?= count($mesInscriptions) ?>)</button>
        <?php if (count($past) > 0): ?>
        <button class="ev-user-tab" data-tab="past">Passés (<?= count($past) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Event cards -->
    <div id="evUpcomingSection">
        <?php if (empty($upcoming)): ?>
            <?= render_empty_state('Aucun événement à venir', 'bi-calendar-event', 'Les événements apparaîtront ici quand ils seront publiés') ?>
        <?php else: ?>
            <div class="row g-3">
            <?php foreach ($upcoming as $ev): ?>
                <div class="col-sm-6 col-lg-4">
                    <?php renderEventCard($ev, $now); ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="evMineSection" style="display:none">
        <?php if (empty($mesInscriptions)): ?>
            <?= render_empty_state('Aucune inscription', 'bi-check2-circle', 'Inscrivez-vous à un événement pour le voir ici') ?>
        <?php else: ?>
            <div class="row g-3">
            <?php foreach ($mesInscriptions as $ev): ?>
                <div class="col-sm-6 col-lg-4">
                    <?php renderEventCard($ev, $now); ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($past) > 0): ?>
    <div id="evPastSection" style="display:none">
        <div class="row g-3">
        <?php foreach ($past as $ev): ?>
            <div class="col-sm-6 col-lg-4">
                <?php renderEventCard($ev, $now, true); ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal détail + inscription -->
<div class="modal fade" id="evDetailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evModalTitle">Événement</h5>
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="evModalBody">
        <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
      <div class="modal-footer" id="evModalFooter"></div>
    </div>
  </div>
</div>

<?php
function renderEventCard($ev, $now, $isPast = false) {
    $isFull = $ev['max_participants'] && $ev['nb_inscrits'] >= $ev['max_participants'];
    $isInscrit = !empty($ev['mon_inscription_id']);
    $isOpen = $ev['statut'] === 'ouvert';
    $dateStr = fmt_date_fr($ev['date_debut'], 'd M Y');
    if ($ev['date_fin'] && $ev['date_fin'] !== $ev['date_debut']) {
        $dateStr .= ' → ' . fmt_date_fr($ev['date_fin'], 'd M Y');
    }
    ?>
    <div class="ev-card <?= $isPast ? 'ev-card-past' : '' ?>" data-event-id="<?= h($ev['id']) ?>">
        <div class="ev-card-date">
            <div class="ev-card-day"><?= date('d', strtotime($ev['date_debut'])) ?></div>
            <div class="ev-card-month"><?= strftime('%b', strtotime($ev['date_debut'])) ?: date('M', strtotime($ev['date_debut'])) ?></div>
        </div>
        <div class="ev-card-body">
            <h6 class="ev-card-title"><?= h($ev['titre']) ?></h6>
            <?php if ($ev['lieu']): ?>
                <div class="ev-card-meta"><i class="bi bi-geo-alt"></i> <?= h($ev['lieu']) ?></div>
            <?php endif; ?>
            <?php if ($ev['heure_debut']): ?>
                <div class="ev-card-meta"><i class="bi bi-clock"></i> <?= h(substr($ev['heure_debut'], 0, 5)) ?><?= $ev['heure_fin'] ? ' - ' . h(substr($ev['heure_fin'], 0, 5)) : '' ?></div>
            <?php endif; ?>
            <div class="ev-card-footer">
                <span class="ev-card-count"><i class="bi bi-people-fill"></i> <?= (int)$ev['nb_inscrits'] ?><?= $ev['max_participants'] ? '/' . (int)$ev['max_participants'] : '' ?></span>
                <?php if ($isInscrit): ?>
                    <span class="ev-badge-inscrit"><i class="bi bi-check-circle-fill"></i> Inscrit</span>
                <?php elseif (!$isOpen): ?>
                    <span class="ev-badge-ferme">Fermé</span>
                <?php elseif ($isFull): ?>
                    <span class="ev-badge-complet">Complet</span>
                <?php else: ?>
                    <span class="ev-badge-open">Ouvert</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<style>
/* ── Tabs ── */
.ev-user-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--cl-border, #e5e5e5); padding-bottom: 0; }
.ev-user-tab {
    background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px;
    padding: 8px 16px; font-size: 0.88rem; font-weight: 500; color: var(--cl-text-secondary, #888);
    cursor: pointer; transition: all 0.2s;
}
.ev-user-tab:hover { color: var(--cl-text, #333); }
.ev-user-tab.active { color: var(--cl-accent, #2d4a43); border-bottom-color: var(--cl-accent, #2d4a43); font-weight: 600; }

/* ── Event card ── */
.ev-card {
    display: flex; gap: 14px; padding: 16px; border-radius: 12px; cursor: pointer;
    background: var(--cl-surface, #fff); border: 1px solid var(--cl-border, #e5e5e5);
    transition: all 0.2s; height: 100%;
}
.ev-card:hover { border-color: var(--cl-accent, #2d4a43); box-shadow: 0 2px 8px rgba(0,0,0,0.06); transform: translateY(-1px); }
.ev-card-past { opacity: 0.6; }
.ev-card-date {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-width: 52px; padding: 8px; border-radius: 10px;
    background: var(--cl-accent-bg, #eef5f2); text-align: center;
}
.ev-card-day { font-size: 1.4rem; font-weight: 700; line-height: 1; color: var(--cl-accent, #2d4a43); }
.ev-card-month { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--cl-text-secondary, #888); margin-top: 2px; }
.ev-card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ev-card-title { font-size: 0.95rem; font-weight: 600; margin: 0 0 4px; }
.ev-card-meta { font-size: 0.8rem; color: var(--cl-text-muted, #999); margin-bottom: 2px; }
.ev-card-meta i { width: 16px; }
.ev-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 6px; }
.ev-card-count { font-size: 0.8rem; color: var(--cl-text-muted, #999); }

.ev-badge-inscrit { font-size: 0.75rem; font-weight: 600; color: #16A34A; }
.ev-badge-ferme { font-size: 0.75rem; font-weight: 500; color: #999; }
.ev-badge-complet { font-size: 0.75rem; font-weight: 500; color: #c0392b; }
.ev-badge-open { font-size: 0.75rem; font-weight: 500; color: var(--cl-accent, #2d4a43); }

/* ── Modal form fields ── */
.ev-modal-field { margin-bottom: 12px; }
.ev-modal-field label { font-size: 0.85rem; font-weight: 500; margin-bottom: 4px; display: block; }
.ev-modal-field .form-text { font-size: 0.75rem; }
.ev-inscrits-list { max-height: 200px; overflow-y: auto; }
.ev-inscrit-chip {
    display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
    border-radius: 20px; background: var(--cl-accent-bg, #eef5f2); font-size: 0.8rem;
    margin: 2px;
}
</style>
