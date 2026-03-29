<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; }
// ─── Données serveur ──────────────────────────────────────────────────────────
$uid = $_SESSION['zt_user']['id'];
$profUser = Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id WHERE u.id = ? AND u.is_active = 1",
    [$uid]
);
if ($profUser) {
    unset($profUser['password'], $profUser['reset_token'], $profUser['reset_expires']);
    $profUser['modules'] = Db::fetchAll(
        "SELECT m.id, m.nom, m.code, um.is_principal FROM user_modules um JOIN modules m ON m.id = um.module_id WHERE um.user_id = ? ORDER BY um.is_principal DESC, m.ordre",
        [$uid]
    );
}
?>
<div class="page-header">
  <h1><i class="bi bi-person"></i> Mon Profil</h1>
  <p>Vos informations personnelles et paramètres</p>
</div>

<!-- Hero identity card -->
<div class="card profile-hero-card mb-4" id="profileHero">
  <div class="page-loading"><span class="spinner"></span></div>
</div>

<div class="row g-4">
  <!-- Infos -->
  <div class="col-12 col-md-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-info-circle text-muted"></i>
        <h3 class="mb-0">Informations</h3>
      </div>
      <div class="card-body">
        <div id="profileInfo">
          <div class="page-loading"><span class="spinner"></span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modifier mot de passe -->
  <div class="col-12 col-md-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-lock text-muted"></i>
        <h3 class="mb-0">Changer le mot de passe</h3>
      </div>
      <div class="card-body">
        <form id="passwordForm">
          <div class="form-group">
            <label class="form-label">Mot de passe actuel</label>
            <div class="pwd-field-wrap">
              <input type="password" class="form-control" id="currentPassword" required autocomplete="current-password">
              <span class="pwd-eye" data-target="currentPassword"><i class="bi bi-eye"></i></span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nouveau mot de passe</label>
            <div class="pwd-field-wrap">
              <input type="password" class="form-control" id="newPassword" required minlength="8" autocomplete="new-password">
              <span class="pwd-eye" data-target="newPassword"><i class="bi bi-eye"></i></span>
            </div>
            <div id="pwdStrength" style="margin-top:6px;font-size:.78rem"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirmer</label>
            <div class="pwd-field-wrap">
              <input type="password" class="form-control" id="confirmPassword" required autocomplete="new-password">
              <span class="pwd-eye" data-target="confirmPassword"><i class="bi bi-eye"></i></span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100 mt-2" id="btnSavePassword">
            <i class="bi bi-lock"></i> Modifier le mot de passe
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<style>
.pwd-field-wrap { position: relative; }
.pwd-field-wrap input { padding-right: 42px; }
.pwd-eye {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: var(--zt-text-muted, #999); font-size: 1.1rem;
    line-height: 1; padding: 4px; user-select: none; z-index: 5;
}
.pwd-eye:hover { color: var(--zt-text, #333); }
</style>
<script type="application/json" id="__zt_ssr__"><?= json_encode(['user' => $profUser], JSON_HEX_TAG | JSON_HEX_APOS) ?></script>
