<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }
$uid = $_SESSION['ss_user']['id'];
$emailContacts = Db::fetchAll(
    "SELECT u.id, u.prenom, u.nom, u.email, COALESCE(f.nom, '') AS fonction_nom,
            COALESCE(m.nom, 'Sans module') AS module_nom,
            COALESCE(m.ordre, 999) AS module_ordre
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1 AND u.id != ?
     ORDER BY module_ordre, m.nom, u.nom, u.prenom",
    [$uid]
);
?>
<!-- Messagerie interne — Split-view client -->
<link rel="stylesheet" href="/spocspace/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">

<div class="adm-email-split">

  <!-- LEFT: Email List -->
  <div class="adm-email-left">
    <div class="adm-email-left-header">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <h6 class="mb-0 em-title"><i class="bi bi-chat-dots"></i> Messagerie interne</h6>
        </div>
        <button class="btn btn-sm btn-primary" id="btnCompose" title="Nouveau message">
          <i class="bi bi-pencil-square"></i>
        </button>
      </div>
      <div class="email-tabs">
        <div class="email-tabs-slider"></div>
        <button class="email-tab active" data-tab="inbox"><i class="bi bi-inbox"></i> Reçus <span class="email-unread-badge" id="inboxBadge"></span></button>
        <button class="email-tab" data-tab="sent"><i class="bi bi-send"></i> Envoyés</button>
      </div>
    </div>

    <div id="emailListContainer" class="adm-email-list">
      <div class="adm-email-empty"><span class="spinner-border spinner-border-sm"></span></div>
    </div>

    <div class="adm-email-left-footer">
      <button class="email-pag-arrow" id="emailPrev" disabled><i class="bi bi-arrow-left"></i></button>
      <div class="email-pag-dots" id="emailPagDots"></div>
      <button class="email-pag-arrow" id="emailNext" disabled><i class="bi bi-arrow-right"></i></button>
    </div>
  </div>

  <!-- RIGHT: Email Detail -->
  <div class="adm-email-right">
    <div id="emailDetailPanel" class="adm-email-detail">
      <div class="adm-email-empty em-empty-detail">
        <i class="bi bi-envelope-open em-empty-icon"></i>
        <p class="mb-0 em-empty-text">Sélectionnez un email pour le lire</p>
      </div>
    </div>
  </div>

</div>

<!-- Gmail-style Compose Panel (bottom-right) -->
<div class="compose-panel" id="composePanel">
  <div class="compose-panel-header" id="composePanelHeader">
    <span class="compose-panel-title" id="composePanelTitle">Nouveau message</span>
    <div class="compose-panel-header-actions">
      <button type="button" class="compose-panel-header-btn" id="composeMinimize" title="Réduire"><i class="bi bi-dash-lg"></i></button>
      <button type="button" class="compose-panel-header-btn" id="composeFullscreen" title="Agrandir"><i class="bi bi-arrows-fullscreen"></i></button>
      <button type="button" class="compose-panel-header-btn" id="composeClose" title="Fermer"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
  <div class="compose-panel-body" id="composePanelBody">
    <div class="compose-field">
      <label>À (collègues)</label>
      <div class="colleague-search-wrap em-search-wrap">
        <input type="text" class="form-control form-control-sm" id="composeToSearch" placeholder="Rechercher un collègue..." autocomplete="off">
        <div class="colleague-dropdown" id="composeToDropdown"></div>
      </div>
      <div id="composeToTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <label>Cc (collègues)</label>
      <div class="colleague-search-wrap em-search-wrap">
        <input type="text" class="form-control form-control-sm" id="composeCcSearch" placeholder="Rechercher un collègue..." autocomplete="off">
        <div class="colleague-dropdown" id="composeCcDropdown"></div>
      </div>
      <div id="composeCcTags" class="email-tags mt-1"></div>
    </div>
    <div class="compose-field">
      <input type="text" class="form-control form-control-sm" id="composeSubject" placeholder="Sujet" maxlength="255">
    </div>
    <div id="composeEditorWrap" class="zs-editor-wrap compose-editor-wrap"></div>
    <div id="composeAttachments" class="email-attachments-list"></div>
    <input type="hidden" id="composeParentId" value="">
    <input type="hidden" id="composeReplyToEmail" value="">
    <input type="hidden" id="composeDraftId" value="">
  </div>
  <div class="compose-panel-footer">
    <button type="button" class="adm-email-btn" id="btnSendEmail"><i class="bi bi-send"></i> Envoyer</button>
    <div class="compose-panel-footer-right">
      <label class="compose-panel-footer-btn" title="Joindre un fichier">
        <i class="bi bi-paperclip"></i>
        <input type="file" id="composeFile" class="d-none" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv">
      </label>
      <button type="button" class="compose-panel-footer-btn compose-panel-delete" id="composeDiscard" title="Supprimer le brouillon"><i class="bi bi-trash3"></i></button>
    </div>
  </div>
</div>
<script type="application/json" id="__ss_ssr__"><?= json_encode(['contacts' => $emailContacts], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
