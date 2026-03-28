<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['zt_user']['id'];
$initSondages = Db::fetchAll(
    "SELECT s.id, s.titre, s.description, s.is_anonymous, s.created_at,
            u.prenom, u.nom,
            (SELECT COUNT(*) FROM sondage_questions WHERE sondage_id = s.id) AS nb_questions,
            (SELECT COUNT(DISTINCT sr.question_id) FROM sondage_reponses sr
             INNER JOIN sondage_questions sq ON sq.id = sr.question_id AND sq.sondage_id = s.id
             WHERE sr.user_id = ?) AS nb_repondu
     FROM sondages s
     LEFT JOIN users u ON u.id = s.created_by
     WHERE s.statut = 'ouvert'
     ORDER BY s.created_at DESC",
    [$uid]
);
?>
<!-- Sondages Page - Employee -->
<div class="split-view">

  <!-- LEFT: Sondage List -->
  <div class="split-view-left">
    <div class="split-view-header">
      <h4><i class="bi bi-clipboard2-check"></i> Sondages</h4>
      <small>Répondez aux sondages ouverts</small>
    </div>

    <div id="sondageListContainer" class="split-view-list">
      <div class="split-view-loading">
        <span class="spinner-border spinner-border-sm"></span>
      </div>
    </div>
  </div>

  <!-- RIGHT: Sondage Detail / Answer Form -->
  <div class="split-view-right">
    <div id="sondageDetailPanel" class="split-view-detail">
      <div class="split-view-empty">
        <i class="bi bi-clipboard2-check"></i>
        <p>Sélectionnez un sondage pour y répondre</p>
      </div>
    </div>
  </div>

</div>

<script type="application/json" id="__zt_ssr__"><?= json_encode(['list' => $initSondages], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
