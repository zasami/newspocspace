<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$notifs = Db::fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30",
    [$uid]
);
$unread = (int) Db::getOne(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [$uid]
);

// Mapping type → icône + variant palette
function notif_icon($type) {
    return match ($type) {
        'message', 'email'      => ['bi-chat-dots', 'green'],
        'stagiaire_report'      => ['bi-journal-check', 'teal'],
        'changement'            => ['bi-arrow-left-right', 'orange'],
        'absence', 'vacances'   => ['bi-calendar-x', 'red'],
        'planning'              => ['bi-calendar3', 'green'],
        'desir'                 => ['bi-star', 'purple'],
        'alerte'                => ['bi-exclamation-triangle', 'red'],
        default                 => ['bi-bell', 'neutral'],
    };
}

$actions = '<button class="btn btn-sm btn-outline-secondary" id="markAllRead" '
    . ($unread ? '' : 'disabled') . '>'
    . '<i class="bi bi-check2-all"></i> Tout marquer comme lu</button>';
?>
<div class="notif-wrap">
    <?= render_page_header('Notifications', 'bi-bell', null, null, $actions) ?>

    <div id="notifList" class="card">
        <?php if (!$notifs): ?>
            <?= render_empty_state('Aucune notification', 'bi-bell-slash') ?>
        <?php else: ?>
            <div class="card-body p-0">
            <?php foreach ($notifs as $n):
                [$icon, $variant] = notif_icon($n['type']);
                $unreadCls = $n['is_read'] ? '' : ' unread';
            ?>
                <div class="notif-item<?= $unreadCls ?>"
                     data-notif-id="<?= h($n['id']) ?>"
                     <?= !empty($n['url']) ? 'data-notif-url="' . h($n['url']) . '"' : '' ?>>
                    <div class="notif-icon bg-<?= $variant ?>"><i class="bi <?= $icon ?>"></i></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="notif-title"><?= h($n['title']) ?></div>
                        <?php if ($n['message']): ?>
                            <div class="notif-msg"><?= h($n['message']) ?></div>
                        <?php endif ?>
                        <div class="notif-time"><?= h(fmt_relative($n['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>
