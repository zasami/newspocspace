<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['ss_user']['id'];
$initNotifs = Db::fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30",
    [$uid]
);
$initUnread = (int) Db::getOne(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [$uid]
);
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1><i class="bi bi-bell"></i> Notifications</h1>
  <button class="btn btn-sm btn-outline-secondary" id="markAllRead"><i class="bi bi-check2-all"></i> Tout marquer comme lu</button>
</div>

<div id="notifList" class="card">
  <div class="card-body" style="padding:0">
    <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>
  </div>
</div>

<style>
.notif-item{display:flex;gap:0.75rem;padding:0.85rem 1.2rem;border-bottom:1px solid var(--ss-border-light);cursor:pointer;transition:background .15s}
.notif-item:hover{background:var(--ss-accent-bg,rgba(0,180,160,.05))}
.notif-item.unread{background:rgba(0,180,160,.04);border-left:3px solid var(--ss-teal)}
.notif-item.unread .notif-title{font-weight:700}
.notif-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.9rem}
.notif-title{font-size:0.9rem;margin-bottom:2px}
.notif-msg{font-size:0.8rem;color:var(--ss-text-muted);line-height:1.3}
.notif-time{font-size:0.72rem;color:var(--ss-text-muted);margin-top:3px}
</style>
<script type="application/json" id="__ss_ssr__"><?= json_encode(['notifications' => $initNotifs, 'unread' => $initUnread], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
