<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['zt_user']['id'];
$initMessages = Db::fetchAll(
    "SELECT m.id, m.sujet, m.contenu, m.from_user_id, m.created_at,
            uf.prenom AS from_prenom, uf.nom AS from_nom,
            (SELECT GROUP_CONCAT(CONCAT(u2.prenom, ' ', u2.nom) SEPARATOR ', ')
             FROM message_recipients mr JOIN users u2 ON u2.id = mr.user_id
             WHERE mr.email_id = m.id AND mr.type = 'to') AS to_names
     FROM messages m
     JOIN users uf ON uf.id = m.from_user_id
     WHERE m.is_draft = 0
       AND (m.from_user_id = ? OR EXISTS (SELECT 1 FROM message_recipients mr2 WHERE mr2.email_id = m.id AND mr2.user_id = ? AND mr2.deleted = 0))
     ORDER BY m.created_at DESC
     LIMIT 50",
    [$uid, $uid]
);
?>
<div class="page-header">
  <h1><i class="bi bi-envelope"></i> Messages</h1>
  <p>Communication avec la direction et vos collègues</p>
</div>

<div class="d-flex gap-2 flex-wrap">
  <!-- Nouveau message -->
  <div class="card" style="flex:0 0 360px;">
    <div class="card-header">
      <h3>Écrire à la direction</h3>
    </div>
    <div class="card-body">
      <form id="messageForm">
        <div class="form-group">
          <label class="form-label">Sujet</label>
          <input type="text" class="form-control" id="messageSujet" required placeholder="Objet du message">
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-control" id="messageContenu" required placeholder="Votre message..." style="min-height:120px"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send"></i> Envoyer
        </button>
      </form>
    </div>
  </div>

  <!-- Liste messages -->
  <div class="card" style="flex:1; min-width:300px;">
    <div class="card-header">
      <h3>Historique</h3>
    </div>
    <div class="card-body" id="messagesListBody">
      <div class="page-loading"><span class="spinner"></span></div>
    </div>
  </div>
</div>

<script type="application/json" id="__zt_ssr__"><?= json_encode(['messages' => $initMessages], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
