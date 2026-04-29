<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
// Filtre via slug URL (/notifications/unread) ou query param fallback
$filter = $_GET['slug'] ?? $_GET['filter'] ?? 'active';
if (!in_array($filter, ['active', 'unread', 'archived'])) $filter = 'active';

$where = "WHERE user_id = ?";
$args = [$uid];
if ($filter === 'unread') {
    $where .= " AND is_read = 0 AND is_archived = 0";
} elseif ($filter === 'archived') {
    $where .= " AND is_archived = 1";
} else { // active = non archivées
    $where .= " AND is_archived = 0";
}

$notifs = Db::fetchAll(
    "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 50",
    $args
);

$countUnread   = (int) Db::getOne("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND is_archived = 0", [$uid]);
$countAll      = (int) Db::getOne("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_archived = 0", [$uid]);
$countArchived = (int) Db::getOne("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_archived = 1", [$uid]);

function notif_icon($type) {
    return match ($type) {
        'message', 'email'      => ['bi-chat-dots', 'green'],
        'stagiaire_report'      => ['bi-journal-check', 'teal'],
        'changement'            => ['bi-arrow-left-right', 'orange'],
        'absence', 'vacances'   => ['bi-calendar-x', 'red'],
        'planning'              => ['bi-calendar3', 'green'],
        'desir'                 => ['bi-star', 'purple'],
        'alerte'                => ['bi-exclamation-triangle', 'red'],
        'fiche_salaire'         => ['bi-receipt', 'teal'],
        'document_ajoute'       => ['bi-file-earmark-plus', 'green'],
        default                 => ['bi-bell', 'neutral'],
    };
}

$actions = '';
if ($filter !== 'archived') {
    $actions .= '<button class="btn btn-sm btn-outline-secondary" id="markAllRead" ' . ($countUnread ? '' : 'disabled') . '><i class="bi bi-check2-all"></i> Tout marquer lu</button> ';
    $actions .= '<button class="btn btn-sm btn-outline-secondary" id="archiveAllRead" title="Archiver toutes les notifications lues"><i class="bi bi-archive"></i> Archiver lues</button>';
}
?>
<div class="notif-wrap">
    <?= render_page_header('Notifications', 'bi-bell', null, null, $actions) ?>

    <!-- Filtres -->
    <div class="notif-filters mb-3">
        <a class="notif-filter-btn <?= $filter === 'active' ? 'active' : '' ?>" href="/newspocspace/notifications" data-filter="active">
            <i class="bi bi-bell"></i> Toutes
            <?php if ($countAll): ?><span class="notif-filter-count"><?= $countAll ?></span><?php endif ?>
        </a>
        <a class="notif-filter-btn <?= $filter === 'unread' ? 'active' : '' ?>" href="/newspocspace/notifications/unread" data-filter="unread">
            <i class="bi bi-bell-fill"></i> Non lues
            <?php if ($countUnread): ?><span class="notif-filter-count notif-filter-count-unread"><?= $countUnread ?></span><?php endif ?>
        </a>
        <a class="notif-filter-btn <?= $filter === 'archived' ? 'active' : '' ?>" href="/newspocspace/notifications/archived" data-filter="archived">
            <i class="bi bi-archive"></i> Archivées
            <?php if ($countArchived): ?><span class="notif-filter-count"><?= $countArchived ?></span><?php endif ?>
        </a>
    </div>

    <div id="notifList" class="card">
        <?php if (!$notifs): ?>
            <?= render_empty_state(
                $filter === 'unread' ? 'Aucune notification non lue' : ($filter === 'archived' ? 'Aucune notification archivée' : 'Aucune notification'),
                $filter === 'archived' ? 'bi-archive' : 'bi-bell-slash'
            ) ?>
        <?php else: ?>
            <div class="card-body p-0">
            <?php foreach ($notifs as $n):
                [$icon, $variant] = notif_icon($n['type']);
                $unreadCls = $n['is_read'] ? '' : ' unread';
            ?>
                <div class="notif-item<?= $unreadCls ?>"
                     data-notif-id="<?= h($n['id']) ?>"
                     <?= !empty($n['link']) ? 'data-notif-url="' . h($n['link']) . '"' : '' ?>>
                    <div class="notif-icon bg-<?= $variant ?>"><i class="bi <?= $icon ?>"></i></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="notif-title"><?= h($n['title']) ?></div>
                        <?php if ($n['message']): ?>
                            <div class="notif-msg"><?= h($n['message']) ?></div>
                        <?php endif ?>
                        <div class="notif-time"><?= h(fmt_relative($n['created_at'])) ?></div>
                    </div>
                    <?php if (!$n['is_archived']): ?>
                        <button class="notif-archive-btn" data-archive="<?= h($n['id']) ?>" title="Archiver">
                            <i class="bi bi-archive"></i>
                        </button>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>
