<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$uid  = $user['id'];

// ─── Événements ouverts ou fermés (visibles) ───
$events = Db::fetchAll(
    "SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.heure_debut, e.heure_fin,
            e.lieu, e.image_url, e.max_participants, e.statut, e.inscription_obligatoire,
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

<!-- Modal détail + inscription (large) -->
<div class="modal fade" id="evDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="evModalTitle">Événement</h5>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center cuis-modal-close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="evModalBody">
        <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
      <div class="modal-footer" id="evModalFooter">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
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
    <div class="ev-card <?= $isPast ? 'ev-card-past' : '' ?> <?= !empty($ev['image_url']) ? 'ev-card-has-img' : '' ?>" data-event-id="<?= h($ev['id']) ?>">
        <?php if (!empty($ev['image_url'])): ?>
            <div class="ev-card-img"><img src="<?= h($ev['image_url']) ?>" alt=""></div>
        <?php else: ?>
            <div class="ev-card-date">
                <div class="ev-card-day"><?= date('d', strtotime($ev['date_debut'])) ?></div>
                <?php $moisFr = ['','JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC']; ?>
            <div class="ev-card-month"><?= $moisFr[(int)date('n', strtotime($ev['date_debut']))] ?></div>
            </div>
        <?php endif; ?>
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
.ev-card-has-img { flex-direction: column; gap: 0; }
.ev-card-img { border-radius: 10px 10px 0 0; overflow: hidden; margin: -16px -16px 12px; }
.ev-card-img img { width: 100%; height: 120px; object-fit: cover; display: block; }
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

.ev-badge-inscrit { font-size: 0.72rem; font-weight: 600; color: #16A34A; background: rgba(22,163,74,0.1); padding: 3px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; }
.ev-badge-ferme { font-size: 0.75rem; font-weight: 500; color: #999; }
.ev-badge-complet { font-size: 0.75rem; font-weight: 500; color: #c0392b; }
.ev-badge-open { font-size: 0.75rem; font-weight: 500; color: var(--cl-accent, #2d4a43); }

/* ── Modal cover ── */
.ev-modal-cover { margin: -1rem -1rem 1rem; }
.ev-modal-cover img { width: 100%; max-height: 220px; object-fit: cover; display: block; }

/* ── Description ── */
.ev-description-box {
    font-size: .9rem; line-height: 1.7; white-space: pre-wrap;
    padding: 12px 16px; border-radius: 10px; margin-bottom: 14px;
    background: var(--cl-surface, #fff); border-left: 3px solid var(--cl-accent, #2d4a43);
    max-height: 160px; overflow-y: auto; color: var(--cl-text, #333);
}

/* ── Info cards ── */
.ev-info-card {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    border-radius: 10px; background: var(--cl-accent-bg, #eef5f2); font-size: 0.85rem;
}
.ev-info-card > i { font-size: 1.1rem; color: var(--cl-accent, #2d4a43); flex-shrink: 0; }
.ev-info-label { font-size: 0.7rem; color: var(--cl-text-muted, #999); font-weight: 500; text-transform: uppercase; letter-spacing: .3px; }
.ev-info-value { font-weight: 600; font-size: 0.88rem; }

/* ── Modal sections ── */
.ev-section { border-top: 1px solid var(--cl-border, #e5e5e5); padding-top: 14px; margin-top: 14px; }
.ev-section-title { font-size: 0.82rem; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }

/* ── Participants list ── */
.ev-participants-list { max-height: 240px; overflow-y: auto; border: 1px solid var(--cl-border, #e5e5e5); border-radius: 10px; }
.ev-participant {
    display: flex; align-items: center; gap: 10px; padding: 8px 14px;
    border-bottom: 1px solid var(--cl-border, #e5e5e5); transition: background .15s;
}
.ev-participant:last-child { border-bottom: none; }
.ev-participant:hover { background: var(--cl-accent-bg, #f7f5f2); }
.ev-participant-rank { width: 22px; font-size: 0.75rem; font-weight: 700; color: var(--cl-text-muted, #999); text-align: center; flex-shrink: 0; }
.ev-participant-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: var(--cl-accent-bg, #D0C4D8); color: var(--cl-accent, #5B4B6B);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.65rem; overflow: hidden;
}
.ev-participant-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.ev-participant-name { flex: 1; font-size: 0.88rem; font-weight: 500; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ev-participant-date { font-size: 0.75rem; color: var(--cl-text-muted, #999); flex-shrink: 0; }

/* ── Modal form fields ── */
.ev-modal-field { margin-bottom: 12px; }
.ev-modal-field label { font-size: 0.85rem; font-weight: 500; margin-bottom: 4px; display: block; }
.ev-modal-field .form-text { font-size: 0.75rem; }

/* ── Inscription values card ── */
.ev-val-card {
    padding: 8px 12px; border-radius: 8px;
    background: var(--cl-accent-bg, #eef5f2); font-size: 0.85rem;
}
.ev-val-label { font-size: 0.7rem; color: var(--cl-text-muted, #999); font-weight: 500; text-transform: uppercase; letter-spacing: .3px; }
.ev-val-value { font-weight: 600; }

/* ── Modal footer ── */
#evDetailModal .modal-footer { border-top: 1px solid var(--cl-border, #e5e5e5); }
</style>
