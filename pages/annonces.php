<?php require_once __DIR__ ."/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['ss_user']['id'];

$initAnnonces = Db::fetchAll(
"SELECT a.id, a.titre, a.slug, a.description, a.image_url, a.categorie,
            a.epingle, a.requires_ack, a.published_at, a.created_at,
            cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
     FROM annonces a
     LEFT JOIN users cr ON cr.id = a.created_by
     WHERE a.archived_at IS NULL AND a.visible = 1
     ORDER BY a.epingle DESC, a.published_at DESC"
);
$acked = array_column(Db::fetchAll("SELECT annonce_id FROM annonce_acks WHERE user_id = ?", [$uid]), 'annonce_id');
$pendingAck = 0;
foreach ($initAnnonces as &$an) {
    $an['user_acked'] = in_array($an['id'], $acked) ? 1 : 0;
    if ($an['requires_ack'] && !$an['user_acked']) $pendingAck++;
}
unset($an);

$catLabels = [
    'direction' => ['label' => 'Direction', 'icon' => 'building', 'color' => '#2d4a43'],
    'rh' => ['label' => 'RH', 'icon' => 'person-badge', 'color' => '#3B4F6B'],
    'vie_sociale' => ['label' => 'Vie sociale', 'icon' => 'balloon-heart', 'color' => '#5B4B6B'],
    'cuisine' => ['label' => 'Cuisine', 'icon' => 'egg-fried', 'color' => '#198754'],
    'protocoles' => ['label' => 'Protocoles', 'icon' => 'heart-pulse', 'color' => '#dc3545'],
    'securite' => ['label' => 'Sécurité', 'icon' => 'shield-check', 'color' => '#fd7e14'],
    'divers' => ['label' => 'Divers', 'icon' => 'info-circle', 'color' => '#6c757d'],
];
?>


<!-- HERO with animated dot wave -->
<div class="page-header ann-page-header">
  <canvas class="ann-dots-canvas" id="annDotsCanvas"></canvas>
  <div class="ann-page-header-icon"><i class="bi bi-megaphone"></i></div>
  <div class="ann-page-header-text">
    <h1>Annonces officielles</h1>
    <p>Communications de la direction et des services</p>
  </div>
</div>

<!-- BREADCRUMB -->
<nav class="ann-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <div class="ann-breadcrumb-inner">
    <ol class="ann-breadcrumb">
      <li><a href="/spocspace/home" data-link="home"><i class="bi bi-house-door-fill"></i></a></li>
      <li class="ann-breadcrumb-sep" id="bcMainSep"><i class="bi bi-chevron-right"></i></li>
      <li class="ann-breadcrumb-current" aria-current="page">Annonces</li>
    </ol>
    <div class="ann-breadcrumb-stats">
      <span class="ann-bc-stat" id="bcStatTotal"><i class="bi bi-megaphone"></i> <strong id="bcStatTotalVal">—</strong> annonces</span>
      <span class="ann-bc-stat" id="bcStatPinned"><i class="bi bi-pin-angle-fill"></i> <strong id="bcStatPinnedVal">—</strong> épinglées</span>
      <span class="ann-bc-stat" id="bcStatAck"><i class="bi bi-exclamation-circle"></i> <strong id="bcStatAckVal">—</strong> à confirmer</span>
    </div>
  </div>
</nav>

<!-- LIST VIEW -->
<div id="annListView">
  <div id="annAckAlert" class="ann-ack-alert" style="display:none">
    <i class="bi bi-exclamation-triangle-fill"></i> <strong id="annAckCount">0</strong> annonce(s) nécessitent votre accusé de réception
  </div>
  <div class="ann-filters" id="annFilters"></div>
  <div class="ann-list" id="annList"></div>
  <div class="ann-empty " id="annEmpty" style="display:none">
    <i class="bi bi-megaphone"></i>
    Aucune annonce pour le moment
  </div>
</div>

<!-- READ VIEW -->
<div id="annReadView" class="" style="display:none">
  <div class="ann-read-panel" id="annReadPanel"></div>
</div>

<script type="application/json" id="__ss_ssr__"><?= json_encode([
    'annonces'    => $initAnnonces,
    'cat_labels'  => $catLabels,
    'pending_ack' => $pendingAck,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?></script>
