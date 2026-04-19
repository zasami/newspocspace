<?php
/**
 * Mot de passe oublié — dossier d'admission
 * URL : /spocspace/website/admissions-mot-de-passe-oublie.php
 * URL (phase 2) : /spocspace/website/admissions-mot-de-passe-oublie.php?token=UUID
 */

require_once __DIR__ . '/../init.php';

$emsNom   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
$emsEmail = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'") ?: '';
$emsTel   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_telephone'") ?: '';
$emsAdr   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_adresse'") ?: '';

$token   = trim($_GET['token'] ?? '');
$phase   = $token ? 'reset' : 'request';  // 'request' = demande de lien, 'reset' = saisie nouveau mdp
$error   = null;
$notice  = null;
$candidat = null;

// ─── Phase 2 : token valide ? ───
if ($phase === 'reset') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $token)) {
        $error = 'Lien invalide.';
        $phase = 'invalid';
    } else {
        $candidat = Db::fetch(
            "SELECT id, ref_email FROM admissions_candidats
             WHERE password_reset_token = ?
               AND password_reset_expires IS NOT NULL
               AND password_reset_expires > NOW()
             LIMIT 1",
            [$token]
        );
        if (!$candidat) {
            $error = 'Ce lien de réinitialisation a expiré ou n\'est pas valide. Veuillez refaire une demande.';
            $phase = 'invalid';
        }
    }
}

// ─── Traitement POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Phase 1 : demande d'envoi de lien
    if (isset($_POST['request_submit']) && $phase === 'request') {
        $email = trim($_POST['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez saisir un email valide.';
        } else {
            $user = Db::fetch(
                "SELECT id, ref_nom_prenom, ref_email
                 FROM admissions_candidats WHERE ref_email = ? LIMIT 1",
                [$email]
            );

            if ($user) {
                $resetToken = Uuid::v4();
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1h

                Db::exec(
                    "UPDATE admissions_candidats
                     SET password_reset_token = ?, password_reset_expires = ?
                     WHERE id = ?",
                    [$resetToken, $expires, $user['id']]
                );

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetUrl = $scheme . '://' . $host . '/spocspace/website/admissions-mot-de-passe-oublie.php?token=' . $resetToken;

                if ($emsEmail) {
                    $subject = '=?UTF-8?B?' . base64_encode('[' . $emsNom . '] Réinitialisation de votre mot de passe') . '?=';
                    $body = "<html><body style=\"font-family:Inter,Arial,sans-serif;color:#1A2E1A;max-width:600px;margin:auto;padding:20px;background:#FAFDF7;\">"
                          . "<h2 style=\"color:#2E7D32;font-family:'Playfair Display',Georgia,serif;\">Réinitialisation du mot de passe</h2>"
                          . "<p>Bonjour " . h($user['ref_nom_prenom']) . ",</p>"
                          . "<p>Vous avez demandé à réinitialiser le mot de passe associé à votre dossier d'admission.</p>"
                          . "<p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien est valable <strong>1 heure</strong>.</p>"
                          . "<p style=\"margin:24px 0;\"><a href=\"" . h($resetUrl) . "\" style=\"background:#2E7D32;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:600;\">Choisir un nouveau mot de passe</a></p>"
                          . "<p style=\"font-size:12px;color:#4A6548;\">Ou copiez ce lien : " . h($resetUrl) . "</p>"
                          . "<p style=\"font-size:13px;color:#4A6548;\">Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>"
                          . "<hr style=\"border:none;border-top:1px solid #D8E8D0;margin:24px 0;\">"
                          . "<p style=\"font-size:13px;color:#4A6548;\">" . h($emsNom)
                          . ($emsAdr ? "<br>" . h($emsAdr) : '')
                          . ($emsTel ? "<br>Tél. " . h($emsTel) : '')
                          . "<br>" . h($emsEmail) . "</p>"
                          . "</body></html>";

                    $headers = "MIME-Version: 1.0\r\n"
                             . "Content-Type: text/html; charset=UTF-8\r\n"
                             . "From: " . $emsNom . " <" . $emsEmail . ">\r\n"
                             . "Reply-To: " . $emsEmail . "\r\n";

                    @mail($user['ref_email'], $subject, $body, $headers);
                }
            }

            // Toujours afficher le même message (évite de révéler si l'email existe)
            $notice = 'Si un dossier existe avec cet email, vous allez recevoir un lien de réinitialisation dans quelques minutes. Pensez à vérifier vos spams.';
        }
    }

    // Phase 2 : saisie du nouveau mot de passe
    elseif (isset($_POST['reset_submit']) && $phase === 'reset' && $candidat) {
        $password        = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            Db::exec(
                "UPDATE admissions_candidats
                 SET password_hash = ?,
                     password_reset_token = NULL,
                     password_reset_expires = NULL
                 WHERE id = ?",
                [$hash, $candidat['id']]
            );
            header('Location: /spocspace/website/admissions-suivi.php?reset_ok=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Mot de passe oublié — <?= h($emsNom) ?></title>
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
.adm-alert.info { background: #E3F2FD; color: #0D47A1; border: 1px solid #C5D9E8; }
.adm-alert.success { background: var(--adm-green-light); color: var(--adm-green); border: 1px solid var(--adm-green-border); }
.adm-footer { text-align: center; margin-top: 18px; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="adm-hero-section">
  <h1><i class="bi bi-key"></i> Mot de passe oublié</h1>
  <p><?php if ($phase === 'reset' && $candidat): ?>Choisissez un nouveau mot de passe pour votre dossier.<?php else: ?>Saisissez votre email pour recevoir un lien de réinitialisation.<?php endif; ?></p>
</section>

<div class="adm-shell">

  <?php if ($phase === 'request' || $phase === 'invalid'): ?>
    <div class="adm-card">
      <h2>Réinitialiser le mot de passe</h2>
      <p class="sub">Nous vous enverrons un lien à l'email associé à votre dossier.</p>

      <?php if ($error): ?><div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>
      <?php if ($notice): ?><div class="adm-alert success"><i class="bi bi-check-circle"></i> <?= h($notice) ?></div><?php endif; ?>

      <?php if (!$notice): ?>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="request_submit" value="1">
        <div class="adm-field">
          <label>Adresse email</label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
          <div class="hint">Email de la personne de référence utilisé lors de la demande.</div>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">
          <i class="bi bi-envelope"></i> Envoyer le lien de réinitialisation
        </button>
      </form>
      <?php endif; ?>

      <div class="adm-footer">
        <a href="/spocspace/website/admissions-suivi.php" class="adm-btn-link"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
      </div>
    </div>

  <?php elseif ($phase === 'reset'): ?>
    <div class="adm-card">
      <h2>Nouveau mot de passe</h2>
      <p class="sub">Choisissez un nouveau mot de passe pour accéder à votre dossier.</p>

      <?php if ($error): ?><div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="reset_submit" value="1">
        <div class="adm-field">
          <label>Nouveau mot de passe</label>
          <div class="adm-input-wrap">
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
            <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
          </div>
          <div class="hint">Au moins 8 caractères.</div>
        </div>
        <div class="adm-field">
          <label>Confirmer le mot de passe</label>
          <div class="adm-input-wrap">
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
            <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">
          <i class="bi bi-check-lg"></i> Enregistrer le nouveau mot de passe
        </button>
      </form>
    </div>
  <?php endif; ?>

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
