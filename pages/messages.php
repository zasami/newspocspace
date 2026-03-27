<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
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
