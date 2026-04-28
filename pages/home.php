<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$user = $_SESSION['ss_user'];
$uid  = $user['id'];
$mois = date('Y-m');

// ─── Stats ───
$desirCount = (int) Db::getOne(
    "SELECT COUNT(*) FROM desirs WHERE user_id = ? AND mois_cible = ?",
    [$uid, $mois]
);
$maxDesirs = (int) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'planning_desirs_max_mois'") ?: 4);

$unread = (int) Db::getOne(
    "SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND lu = 0 AND deleted = 0",
    [$uid]
);

$soldeVac = (int) Db::getOne("SELECT solde_vacances FROM users WHERE id = ?", [$uid]);
$prisVac = (int) Db::getOne(
    "SELECT COALESCE(SUM(DATEDIFF(date_fin, date_debut) + 1), 0)
     FROM absences WHERE user_id = ? AND type = 'vacances' AND statut = 'valide'
       AND YEAR(date_debut) = YEAR(NOW())",
    [$uid]
);
$restVac = max(0, $soldeVac - $prisVac);

// Prochain service (assignation prévue)
$nextShift = Db::fetch(
    "SELECT pa.date_jour, ht.code AS horaire_code, ht.couleur, ht.nom AS horaire_nom
     FROM planning_assignations pa
     JOIN plannings p ON p.id = pa.planning_id
     LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
     WHERE pa.user_id = ? AND pa.date_jour >= CURDATE() AND p.statut IN ('provisoire','final','publie')
     ORDER BY pa.date_jour ASC LIMIT 1",
    [$uid]
);
?>
<div class="home-wrap">
    <div class="mb-3">
        <h2 class="page-title mb-0">Bonjour <?= h($user['prenom'] ?? '') ?></h2>
        <p class="text-muted small mb-0">Voici votre tableau de bord</p>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-4 col-lg">
            <a class="stat-card text-decoration-none" data-link="planning" href="#">
                <div class="stat-icon bg-teal"><i class="bi bi-calendar3"></i></div>
                <div class="flex-grow-1 min-width-0">
                    <div class="stat-value">
                        <?php if ($nextShift): ?>
                            <?= h(fmt_date_fr($nextShift['date_jour'], 'd.m')) ?>
                            <small class="stat-sub">· <?= h($nextShift['horaire_code']) ?></small>
                        <?php else: ?>
                            —
                        <?php endif ?>
                    </div>
                    <div class="stat-label">Prochain service</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg">
            <a class="stat-card text-decoration-none" data-link="desirs" href="#">
                <div class="stat-icon bg-green"><i class="bi bi-star"></i></div>
                <div class="flex-grow-1 min-width-0">
                    <div class="stat-value"><?= $desirCount ?><small class="stat-sub">/<?= $maxDesirs ?></small></div>
                    <div class="stat-label">Désirs ce mois</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg">
            <a class="stat-card text-decoration-none" data-link="vacances" href="#">
                <div class="stat-icon bg-orange"><i class="bi bi-calendar-x"></i></div>
                <div class="flex-grow-1 min-width-0">
                    <div class="stat-value"><?= $restVac ?></div>
                    <div class="stat-label">Jours vacances restants</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-lg">
            <a class="stat-card text-decoration-none" data-link="emails" href="#">
                <div class="stat-icon bg-purple"><i class="bi bi-envelope"></i></div>
                <div class="flex-grow-1 min-width-0">
                    <div class="stat-value"><?= $unread ?></div>
                    <div class="stat-label">Messages non lus</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Ma semaine + Menus — conservés en JS (navigation hebdo dynamique) -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Ma semaine</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="homePrevWeek" title="Semaine précédente">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span id="homeWeekLabel" class="home-week-label"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="homeNextWeek" title="Semaine suivante">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body" id="homeWeekPlanning">
                    <div class="home-loading"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-egg-fried"></i> Menu du midi</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="menuPrevWeek" title="Semaine précédente">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span id="menuWeekLabel" class="home-week-label"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="menuNextWeek" title="Semaine suivante">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="menuLastWeek" title="Dernier menu disponible" style="display:none">
                            <i class="bi bi-chevron-double-right"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body" id="homeMenus">
                    <div class="home-loading"><span class="spinner-border spinner-border-sm"></span> Chargement…</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal réservation repas -->
<div class="modal fade" id="menuReservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered home-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuReservationTitle">Réserver un repas</h5>
                <button type="button" class="btn btn-sm btn-light ms-auto home-close-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body home-modal-body">
                <div id="menuDetailContent"></div>
                <hr>
                <form id="menuReservationForm"></form>
            </div>
            <div class="modal-footer d-flex" id="menuReservationFooter">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmation annulation -->
<div class="modal fade" id="menuCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered home-cancel-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle home-cancel-icon"></i>
                    <span class="fw-semibold">Annuler la commande</span>
                </div>
                <button type="button" class="btn btn-sm btn-light ms-auto home-close-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0 small">Êtes-vous sûr de vouloir annuler votre commande ? Cette action est irréversible.</p>
            </div>
            <div class="modal-footer d-flex">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Non, garder</button>
                <button type="button" class="btn btn-sm btn-danger ms-auto" id="menuCancelConfirmBtn">
                    <i class="bi bi-x-circle"></i> Oui, annuler
                </button>
            </div>
        </div>
    </div>
</div>
