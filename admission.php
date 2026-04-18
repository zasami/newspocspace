<?php
/**
 * Formulaire public de demande d'admission EMS
 * Étape 1 du dossier d'admission — accessible sans authentification
 * URL : /spocspace/admission.php
 */

require_once __DIR__ . '/init_minimal.php';
require_once __DIR__ . '/core/Uuid.php';
require_once __DIR__ . '/core/Sanitize.php';

Db::connect();

// Config EMS (expéditeur email + identité établissement)
$cfg = [];
foreach (Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key IN ('ems_nom','ems_email','ems_adresse','ems_telephone','ems_logo_url')") as $r) {
    $cfg[$r['config_key']] = $r['config_value'];
}
$emsNom   = $cfg['ems_nom']   ?? 'EMS';
$emsEmail = $cfg['ems_email'] ?? 'no-reply@localhost';
$emsTel   = $cfg['ems_telephone'] ?? '';
$emsAdr   = $cfg['ems_adresse'] ?? '';
$emsLogo  = $cfg['ems_logo_url'] ?? '';

$errors = [];
$success = false;
$successToken = null;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot anti-bot
    if (!empty($_POST['website_url'] ?? '')) {
        http_response_code(400);
        exit('Bad request');
    }

    // Collecte + validation
    $typeDemande = $_POST['type_demande'] ?? '';
    if (!in_array($typeDemande, ['urgente', 'preventive'], true)) {
        $errors[] = 'Type de demande requis (urgente ou préventive).';
    }

    $dateDemande = $_POST['date_demande'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDemande)) {
        $dateDemande = date('Y-m-d');
    }

    $nomPrenom = trim($_POST['nom_prenom'] ?? '');
    if ($nomPrenom === '') $errors[] = 'Nom et prénom du résident requis.';

    $dateNaissance = trim($_POST['date_naissance'] ?? '');
    if ($dateNaissance && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaissance)) {
        $errors[] = 'Date de naissance invalide.';
        $dateNaissance = '';
    }
    $dateNaissance = $dateNaissance ?: null;

    $adressePostale = trim($_POST['adresse_postale'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email du résident invalide.';
    }
    $telephone = trim($_POST['telephone'] ?? '');

    $situation = $_POST['situation'] ?? '';
    if (!in_array($situation, ['domicile', 'trois_chenes', 'hug', 'autre'], true)) {
        $errors[] = 'Situation actuelle requise.';
    }
    $situationAutre = ($situation === 'autre') ? trim($_POST['situation_autre'] ?? '') : null;

    $refNomPrenom = trim($_POST['ref_nom_prenom'] ?? '');
    if ($refNomPrenom === '') $errors[] = 'Personne de référence requise.';

    $refAdmin  = !empty($_POST['ref_aspect_administratifs']) ? 1 : 0;
    $refSoins  = !empty($_POST['ref_aspect_soins']) ? 1 : 0;
    $refCurateur = !empty($_POST['ref_curateur']) ? 1 : 0;
    $refLien   = trim($_POST['ref_lien_parente'] ?? '') ?: null;
    $refAutre  = trim($_POST['ref_autre'] ?? '') ?: null;
    $refAdr    = trim($_POST['ref_adresse_postale'] ?? '') ?: null;
    $refEmail  = trim($_POST['ref_email'] ?? '');
    $refTel    = trim($_POST['ref_telephone'] ?? '') ?: null;

    if ($refEmail === '' || !filter_var($refEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email de la personne de référence requis et valide.';
    }

    $medNom = trim($_POST['med_nom'] ?? '') ?: null;
    $medAdr = trim($_POST['med_adresse_postale'] ?? '') ?: null;
    $medEmail = trim($_POST['med_email'] ?? '') ?: null;
    if ($medEmail && !filter_var($medEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email du médecin traitant invalide.';
        $medEmail = null;
    }
    $medTel = trim($_POST['med_telephone'] ?? '') ?: null;

    if (empty($_POST['consentement'])) {
        $errors[] = 'Vous devez accepter le traitement de vos données.';
    }

    if (!$errors) {
        $id = Uuid::v4();
        $token = Uuid::v4();

        Db::exec(
            "INSERT INTO admissions_candidats (
                id, token_acces, type_demande, date_demande,
                nom_prenom, date_naissance, adresse_postale, email, telephone,
                situation, situation_autre,
                ref_nom_prenom, ref_aspect_administratifs, ref_aspect_soins,
                ref_lien_parente, ref_curateur, ref_autre,
                ref_adresse_postale, ref_email, ref_telephone,
                med_nom, med_adresse_postale, med_email, med_telephone,
                statut, ip_soumission
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                'demande_envoyee', ?
            )",
            [
                $id, $token, $typeDemande, $dateDemande,
                $nomPrenom, $dateNaissance, $adressePostale ?: null, $email ?: null, $telephone ?: null,
                $situation, $situationAutre,
                $refNomPrenom, $refAdmin, $refSoins,
                $refLien, $refCurateur, $refAutre,
                $refAdr, $refEmail, $refTel,
                $medNom, $medAdr, $medEmail, $medTel,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]
        );

        Db::exec(
            "INSERT INTO admissions_historique (id, candidat_id, action, to_status, commentaire)
             VALUES (?, ?, 'creation', 'demande_envoyee', ?)",
            [Uuid::v4(), $id, 'Demande soumise en ligne par ' . $refNomPrenom]
        );

        // Envoi email magic link
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $suiviUrl = $scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/admission-suivi.php?token=' . $token;

        $subject = '[' . $emsNom . '] Confirmation de votre demande d\'admission';
        $body = "<html><body style=\"font-family:Arial,sans-serif;color:#1A1A1A;max-width:600px;margin:auto;padding:20px;\">"
              . "<h2 style=\"color:#2d4a43;\">Demande d'admission reçue</h2>"
              . "<p>Bonjour " . h($refNomPrenom) . ",</p>"
              . "<p>Nous accusons réception de votre demande d'admission concernant <strong>" . h($nomPrenom) . "</strong>.</p>"
              . "<p>Votre demande va être examinée par la direction de <strong>" . h($emsNom) . "</strong>. Vous serez informé(e) par email de la suite de la procédure.</p>"
              . "<p>Vous pouvez suivre l'état de votre dossier à tout moment en cliquant sur le lien ci-dessous :</p>"
              . "<p style=\"margin:24px 0;\"><a href=\"" . h($suiviUrl) . "\" style=\"background:#2d4a43;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;\">Suivre ma demande</a></p>"
              . "<p style=\"font-size:12px;color:#6B6B6B;\">Ou copiez ce lien : " . h($suiviUrl) . "</p>"
              . "<hr style=\"border:none;border-top:1px solid #E8E5E0;margin:24px 0;\">"
              . "<p style=\"font-size:13px;color:#6B6B6B;\">" . h($emsNom) . ($emsAdr ? "<br>" . h($emsAdr) : '') . ($emsTel ? "<br>Tél. " . h($emsTel) : '') . "<br>" . h($emsEmail) . "</p>"
              . "</body></html>";

        $headers = "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "From: " . $emsNom . " <" . $emsEmail . ">\r\n"
                 . "Reply-To: " . $emsEmail . "\r\n";

        $recipients = [$refEmail];
        if ($email && strcasecmp($email, $refEmail) !== 0) {
            $recipients[] = $email;
        }
        foreach ($recipients as $rcpt) {
            @mail($rcpt, $subject, $body, $headers);
        }

        $success = true;
        $successToken = $token;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Demande d'admission — <?= h($emsNom) ?></title>
<link rel="stylesheet" href="assets/css/ss-colors.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--cl-bg);color:var(--cl-text);line-height:1.5}
.ad-shell{max-width:880px;margin:0 auto;padding:32px 20px 80px}
.ad-head{display:flex;align-items:center;gap:16px;margin-bottom:24px}
.ad-head img{height:56px;width:auto}
.ad-head h1{margin:0;font-size:1.5rem;font-weight:600}
.ad-head .sub{color:var(--cl-text-secondary);font-size:.95rem;margin-top:2px}
.ad-card{background:var(--cl-surface);border:1px solid var(--cl-border);border-radius:var(--cl-radius);padding:28px;margin-bottom:20px}
.ad-card h2{margin:0 0 18px;font-size:1.1rem;font-weight:600;color:var(--cl-accent);border-bottom:1px solid var(--cl-border-light);padding-bottom:10px;display:flex;align-items:center;gap:8px}
.ad-card h2 i{color:var(--cl-teal-fg)}
.ad-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px}
.ad-row.full{grid-template-columns:1fr}
@media(max-width:640px){.ad-row{grid-template-columns:1fr}}
.ad-field label{display:block;font-size:.85rem;font-weight:500;color:var(--cl-text-secondary);margin-bottom:6px}
.ad-field label .req{color:#7B3B2C}
.ad-field input[type="text"],.ad-field input[type="email"],.ad-field input[type="date"],.ad-field input[type="tel"],.ad-field textarea,.ad-field select{
  width:100%;padding:10px 12px;border:1px solid var(--cl-border);border-radius:var(--cl-radius-sm);background:var(--cl-surface);color:var(--cl-text);font-size:.95rem;font-family:inherit;
}
.ad-field input:focus,.ad-field textarea:focus,.ad-field select:focus{outline:none;border-color:var(--cl-accent)}
.ad-field textarea{min-height:80px;resize:vertical}
.ad-check{display:flex;align-items:center;gap:8px;padding:6px 0;font-size:.95rem}
.ad-check input[type="checkbox"],.ad-check input[type="radio"]{width:18px;height:18px;margin:0;accent-color:var(--cl-teal-fg)}
.ad-radiogroup,.ad-checkgroup{display:flex;flex-direction:column;gap:4px}
.ad-radiogroup.inline{flex-direction:row;gap:24px;flex-wrap:wrap}
.ad-hint{font-size:.82rem;color:var(--cl-text-muted);margin-top:4px}
.ad-alert{padding:14px 18px;border-radius:var(--cl-radius-sm);margin-bottom:20px;font-size:.92rem}
.ad-alert.error{background:#FEE2E2;color:#991B1B;border:1px solid #F5B5B5}
.ad-alert.success{background:#DCFCE7;color:#166534;border:1px solid #B5E5C5}
.ad-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px}
.ad-btn{padding:12px 28px;border:none;border-radius:var(--cl-radius-sm);font-size:.95rem;font-weight:500;cursor:pointer;transition:all .15s}
.ad-btn-primary{background:var(--cl-accent);color:#fff}
.ad-btn-primary:hover{background:var(--cl-accent-hover)}
.ad-btn-link{background:transparent;color:var(--cl-text-secondary);text-decoration:underline}
.honey{position:absolute;left:-10000px;width:1px;height:1px;opacity:0}
.ad-consent{background:var(--cl-accent-bg);padding:14px;border-radius:var(--cl-radius-sm);margin:16px 0}
.ad-success{background:var(--cl-surface);border:1px solid var(--cl-border);border-radius:var(--cl-radius);padding:40px;text-align:center}
.ad-success .icon{font-size:3rem;color:var(--cl-teal-fg);margin-bottom:12px}
.ad-success h2{margin:0 0 10px;font-size:1.4rem}
.ad-success p{color:var(--cl-text-secondary);max-width:540px;margin:0 auto 10px}
.ad-success .token-box{background:var(--cl-accent-bg);padding:14px;border-radius:var(--cl-radius-sm);margin:20px auto;max-width:500px;font-family:monospace;font-size:.85rem;word-break:break-all}
</style>
</head>
<body>
<div class="ad-shell">

<div class="ad-head">
  <?php if ($emsLogo): ?><img src="<?= h($emsLogo) ?>" alt="Logo"><?php endif; ?>
  <div>
    <h1><?= h($emsNom) ?></h1>
    <div class="sub">Formulaire de demande d'admission</div>
  </div>
</div>

<?php if ($success): ?>
  <div class="ad-success">
    <div class="icon"><i class="bi bi-check-circle-fill"></i></div>
    <h2>Demande envoyée avec succès</h2>
    <p>Un email de confirmation vous a été envoyé avec un lien personnel pour suivre l'avancement de votre dossier.</p>
    <p>La direction de <strong><?= h($emsNom) ?></strong> vous contactera prochainement.</p>
    <div class="token-box">
      Lien de suivi : <?php
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        echo h($scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME']) . '/admission-suivi.php?token=' . $successToken);
      ?>
    </div>
    <p style="font-size:.85rem;color:var(--cl-text-muted);">Conservez ce lien : il vous permet de consulter l'état de votre demande à tout moment.</p>
  </div>
<?php else: ?>

<?php if ($errors): ?>
  <div class="ad-alert error">
    <strong>Merci de corriger les erreurs suivantes :</strong>
    <ul style="margin:6px 0 0;padding-left:20px">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" autocomplete="off" novalidate>

  <input type="text" name="website_url" class="honey" tabindex="-1" autocomplete="off">

  <div class="ad-card">
    <h2><i class="bi bi-flag"></i> Type de demande</h2>
    <div class="ad-row">
      <div class="ad-field">
        <label>Nature de la demande <span class="req">*</span></label>
        <div class="ad-radiogroup inline">
          <label class="ad-check"><input type="radio" name="type_demande" value="urgente" <?= ($_POST['type_demande'] ?? '') === 'urgente' ? 'checked' : '' ?>> Urgente</label>
          <label class="ad-check"><input type="radio" name="type_demande" value="preventive" <?= ($_POST['type_demande'] ?? '') === 'preventive' ? 'checked' : '' ?>> Préventive</label>
        </div>
      </div>
      <div class="ad-field">
        <label>Date de la demande</label>
        <input type="date" name="date_demande" value="<?= h($_POST['date_demande'] ?? date('Y-m-d')) ?>">
      </div>
    </div>
  </div>

  <div class="ad-card">
    <h2><i class="bi bi-person"></i> Personne concernée</h2>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Nom et prénom <span class="req">*</span></label>
        <input type="text" name="nom_prenom" value="<?= h($_POST['nom_prenom'] ?? '') ?>" required>
      </div>
    </div>
    <div class="ad-row">
      <div class="ad-field">
        <label>Date de naissance</label>
        <input type="date" name="date_naissance" value="<?= h($_POST['date_naissance'] ?? '') ?>">
      </div>
      <div class="ad-field">
        <label>N° de téléphone</label>
        <input type="tel" name="telephone" value="<?= h($_POST['telephone'] ?? '') ?>">
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Adresse postale</label>
        <textarea name="adresse_postale"><?= h($_POST['adresse_postale'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Adresse email</label>
        <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>">
        <div class="ad-hint">Si renseignée, la personne concernée recevra aussi le lien de suivi.</div>
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Situation actuelle <span class="req">*</span></label>
        <div class="ad-radiogroup">
          <label class="ad-check"><input type="radio" name="situation" value="domicile" <?= ($_POST['situation'] ?? '') === 'domicile' ? 'checked' : '' ?>> À son domicile</label>
          <label class="ad-check"><input type="radio" name="situation" value="trois_chenes" <?= ($_POST['situation'] ?? '') === 'trois_chenes' ? 'checked' : '' ?>> À l'hôpital des Trois-Chênes</label>
          <label class="ad-check"><input type="radio" name="situation" value="hug" <?= ($_POST['situation'] ?? '') === 'hug' ? 'checked' : '' ?>> Aux HUG</label>
          <label class="ad-check"><input type="radio" name="situation" value="autre" id="situ_autre" <?= ($_POST['situation'] ?? '') === 'autre' ? 'checked' : '' ?>> Autre :</label>
          <input type="text" name="situation_autre" value="<?= h($_POST['situation_autre'] ?? '') ?>" placeholder="Précisez…" style="margin-left:26px;max-width:400px">
        </div>
      </div>
    </div>
  </div>

  <div class="ad-card">
    <h2><i class="bi bi-people"></i> Personne de référence</h2>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Nom et prénom <span class="req">*</span></label>
        <input type="text" name="ref_nom_prenom" value="<?= h($_POST['ref_nom_prenom'] ?? '') ?>" required>
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Pour les aspects</label>
        <div class="ad-checkgroup">
          <label class="ad-check"><input type="checkbox" name="ref_aspect_administratifs" value="1" <?= !empty($_POST['ref_aspect_administratifs']) ? 'checked' : '' ?>> Administratifs</label>
          <label class="ad-check"><input type="checkbox" name="ref_aspect_soins" value="1" <?= !empty($_POST['ref_aspect_soins']) ? 'checked' : '' ?>> Soins</label>
          <label class="ad-check"><input type="checkbox" name="ref_curateur" value="1" <?= !empty($_POST['ref_curateur']) ? 'checked' : '' ?>> Curateur</label>
        </div>
        <div style="display:flex;gap:16px;margin-top:10px;flex-wrap:wrap">
          <div style="flex:1;min-width:220px">
            <label style="font-size:.82rem;color:var(--cl-text-muted)">Lien de parenté</label>
            <input type="text" name="ref_lien_parente" value="<?= h($_POST['ref_lien_parente'] ?? '') ?>" placeholder="ex: fils, fille, conjoint…">
          </div>
          <div style="flex:1;min-width:220px">
            <label style="font-size:.82rem;color:var(--cl-text-muted)">Autre (précisez)</label>
            <input type="text" name="ref_autre" value="<?= h($_POST['ref_autre'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Adresse postale</label>
        <textarea name="ref_adresse_postale"><?= h($_POST['ref_adresse_postale'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="ad-row">
      <div class="ad-field">
        <label>Adresse email <span class="req">*</span></label>
        <input type="email" name="ref_email" value="<?= h($_POST['ref_email'] ?? '') ?>" required>
        <div class="ad-hint">Vous recevrez le lien de suivi de votre dossier à cette adresse.</div>
      </div>
      <div class="ad-field">
        <label>N° de téléphone</label>
        <input type="tel" name="ref_telephone" value="<?= h($_POST['ref_telephone'] ?? '') ?>">
      </div>
    </div>
  </div>

  <div class="ad-card">
    <h2><i class="bi bi-heart-pulse"></i> Médecin traitant</h2>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Nom</label>
        <input type="text" name="med_nom" value="<?= h($_POST['med_nom'] ?? '') ?>">
      </div>
    </div>
    <div class="ad-row full">
      <div class="ad-field">
        <label>Adresse postale</label>
        <textarea name="med_adresse_postale"><?= h($_POST['med_adresse_postale'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="ad-row">
      <div class="ad-field">
        <label>Adresse email</label>
        <input type="email" name="med_email" value="<?= h($_POST['med_email'] ?? '') ?>">
      </div>
      <div class="ad-field">
        <label>N° de téléphone</label>
        <input type="tel" name="med_telephone" value="<?= h($_POST['med_telephone'] ?? '') ?>">
      </div>
    </div>
  </div>

  <div class="ad-consent">
    <label class="ad-check">
      <input type="checkbox" name="consentement" value="1" required>
      <span>J'accepte que les informations transmises soient utilisées pour l'étude de la demande d'admission et conservées conformément à la politique de confidentialité de <?= h($emsNom) ?>.</span>
    </label>
  </div>

  <div class="ad-actions">
    <button type="submit" class="ad-btn ad-btn-primary">
      <i class="bi bi-send"></i> Envoyer la demande
    </button>
  </div>
</form>

<?php endif; ?>

</div>
</body>
</html>
