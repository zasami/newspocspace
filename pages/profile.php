<?php require_once __DIR__ . "/../init.php"; if (empty($_SESSION["zt_user"])) { http_response_code(401); exit; } ?>
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
            <input type="password" class="form-control" id="currentPassword" required autocomplete="current-password">
          </div>
          <div class="form-group">
            <label class="form-label">Nouveau mot de passe</label>
            <input type="password" class="form-control" id="newPassword" required minlength="8" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label">Confirmer</label>
            <input type="password" class="form-control" id="confirmPassword" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary w-100 mt-2">
            <i class="bi bi-lock"></i> Modifier le mot de passe
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
