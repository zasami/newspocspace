<?php
/**
 * Changer le mot de passe — connecté uniquement
 * URL : /spocspace/website/admissions-changer-mot-de-passe.php
 */

require_once __DIR__ . '/../init.php';

$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';

// Session famille requise
$famille = $_SESSION['admission_famille'] ?? null;
if (!$famille) {
    header('Location: /spocspace/website/admissions-suivi.php');
    exit;
}

$error   = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['new_password_confirm'] ?? '');

    $candidat = Db::fetch("SELECT password_hash FROM admissions_candidats WHERE id = ?", [$famille['id']]);

    if (!$candidat || !password_verify($current, $candidat['password_hash'] ?? '')) {
        $error = 'Mot de passe actuel incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($new !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } elseif ($new === $current) {
        $error = 'Le nouveau mot de passe doit être différent de l\'ancien.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        Db::exec("UPDATE admissions_candidats SET password_hash = ? WHERE id = ?", [$hash, $famille['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Changer le mot de passe — <?= h($emsNom) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
:root {
  --adm-green: #2E7D32; --adm-green-light: rgba(46,125,50,0.06); --adm-green-border: rgba(46,125,50,0.12);
  --adm-bg: #FAFDF7; --adm-surface: #FFFFFF; --adm-text: #1A2E1A; --adm-text-secondary: #4A6548;
  --adm-text-muted: #7E9A7A; --adm-border: #D8E8D0; --adm-radius: 16px;
  --adm-shadow: 0 1px 3px rgba(46,125,50,0.04), 0 1px 2px rgba(0,0,0,0.02);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--adm-bg); color: var(--adm-text); line-height: 1.7; }
.adm-hero-section { background-color: #D8E8CC; background-image: linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%), url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg'); background-repeat: no-repeat, repeat; background-size: auto, 120px 120px; padding: 120px 20px 60px; text-align: center; border-bottom: 1px solid var(--adm-border); }
.adm-hero-section h1 { font-family: 'Playfair Display', Georgia, serif; font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 600; color: var(--adm-text); margin: 0 0 12px; }
.adm-hero-section h1 i { color: #E6C220; margin-right: 10px; }
.adm-hero-section p { font-size: 1.05rem; color: var(--adm-text-secondary); max-width: 640px; margin: 0 auto; }
.adm-shell { max-width: 500px; margin: 0 auto; padding: 50px 20px 60px; }
.adm-card { max-width: 460px; margin: 0 auto; background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 40px 36px; box-shadow: var(--adm-shadow); }
.adm-card h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.5rem; color: var(--adm-text); margin-bottom: 6px; text-align: center; }
.adm-card .sub { color: var(--adm-text-secondary); text-align: center; margin-bottom: 24px; font-size: .92rem; }
.adm-field { margin-bottom: 16px; }
.adm-field label { display: block; font-size: .85rem; font-weight: 600; color: var(--adm-text); margin-bottom: 6px; }
.adm-field .hint { font-size: .78rem; color: var(--adm-text-muted); margin-top: 4px; }
.adm-field input { width: 100%; padding: 11px 14px; border: 1px solid var(--adm-border); border-radius: 10px; background: #fff; color: var(--adm-text); font-family: inherit; font-size: .95rem; }
.adm-input-wrap { position: relative; }
.adm-input-wrap .adm-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--adm-text-muted); display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 1.05rem; transition: color .15s, background .15s; }
.adm-input-wrap .adm-eye:hover { color: var(--adm-green); background: var(--adm-green-light); }
.adm-input-wrap input { padding-right: 44px !important; }
.adm-field input:focus { outline: none; border-color: var(--adm-green); box-shadow: 0 0 0 3px rgba(46,125,50,0.12); }
.adm-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 10px; font-size: .95rem; font-weight: 600; cursor: pointer; font-family: inherit; width: 100%; }
.adm-btn-primary { background: var(--adm-green); color: #fff; }
.adm-btn-primary:hover { background: #245d28; }
.adm-btn-link { background: transparent; color: var(--adm-green); text-decoration: none; font-weight: 600; font-size: .88rem; }
.adm-btn-link:hover { text-decoration: underline; }
.adm-alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: .9rem; }
.adm-alert.error { background: #FDEDEA; color: #8B2E26; border: 1px solid #F5C5BB; }
.adm-alert.success { background: var(--adm-green-light); color: var(--adm-green); border: 1px solid var(--adm-green-border); }
.adm-footer { text-align: center; margin-top: 18px; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="adm-hero-section">
  <h1><i class="bi bi-key"></i> Changer le mot de passe</h1>
  <p>Connecté en tant que <strong><?= h($famille['nom_prenom']) ?></strong> (<?= h($famille['email']) ?>)</p>
</section>

<div class="adm-shell">

  <div class="adm-card">
    <h2>Nouveau mot de passe</h2>
    <p class="sub">Pour des raisons de sécurité, veuillez confirmer votre mot de passe actuel.</p>

    <?php if ($error): ?><div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="adm-alert success"><i class="bi bi-check-circle"></i> Mot de passe mis à jour avec succès.</div>
      <div class="adm-footer">
        <a href="/spocspace/website/admissions-suivi.php" class="adm-btn-link"><i class="bi bi-arrow-left"></i> Retour au suivi du dossier</a>
      </div>
    <?php else: ?>
    <form method="POST" autocomplete="off">
      <div class="adm-field">
        <label>Mot de passe actuel</label>
        <div class="adm-input-wrap">
          <input type="password" name="current_password" required autocomplete="current-password">
          <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <div class="adm-field">
        <label>Nouveau mot de passe</label>
        <div class="adm-input-wrap">
          <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
          <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
        </div>
        <div class="hint">Au moins 8 caractères.</div>
      </div>
      <div class="adm-field">
        <label>Confirmer le nouveau mot de passe</label>
        <div class="adm-input-wrap">
          <input type="password" name="new_password_confirm" minlength="8" required autocomplete="new-password">
          <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary">
        <i class="bi bi-check-lg"></i> Mettre à jour le mot de passe
      </button>
    </form>

    <div class="adm-footer">
      <a href="/spocspace/website/admissions-suivi.php" class="adm-btn-link"><i class="bi bi-arrow-left"></i> Annuler</a>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('click', function(e) {
  var btn = e.target.closest('[data-toggle-password]');
  if (!btn) return;
  var wrap = btn.closest('.adm-input-wrap');
  if (!wrap) return;
  var input = wrap.querySelector('input');
  var icon = btn.querySelector('i');
  if (!input || !icon) return;
  if (input.type === 'password') { input.type = 'text'; icon.className = 'bi bi-eye-slash'; }
  else { input.type = 'password'; icon.className = 'bi bi-eye'; }
});
</script>

</body>
</html>
