<?php
require_once __DIR__ . '/../init.php';

$emsNom   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
$emsEmail = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'") ?: '';
$emsTel   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_telephone'") ?: '';
$emsAdr   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_adresse'") ?: '';

// ─────────── Traitement du formulaire de demande en ligne ───────────
$formErrors  = [];
$formSuccess = false;
$formEmail   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admission_submit'])) {

    // Honeypot
    if (!empty($_POST['website_url'] ?? '')) {
        http_response_code(400);
        exit('Bad request');
    }

    $typeDemande = $_POST['type_demande'] ?? '';
    if (!in_array($typeDemande, ['urgente', 'preventive'], true)) {
        $formErrors[] = 'Veuillez indiquer la nature de la demande (urgente ou préventive).';
    }

    $dateDemande = $_POST['date_demande'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDemande)) $dateDemande = date('Y-m-d');

    $nomPrenom = trim($_POST['nom_prenom'] ?? '');
    if ($nomPrenom === '') $formErrors[] = 'Nom et prénom du résident requis.';

    $dateNaissance = trim($_POST['date_naissance'] ?? '');
    if ($dateNaissance && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaissance)) {
        $formErrors[] = 'Date de naissance invalide.';
        $dateNaissance = '';
    }
    $dateNaissance = $dateNaissance ?: null;

    $adresseRue   = trim($_POST['adresse_rue'] ?? '') ?: null;
    $adresseComp  = trim($_POST['adresse_complement'] ?? '') ?: null;
    $adresseCp    = trim($_POST['adresse_cp'] ?? '') ?: null;
    $adresseVille = trim($_POST['adresse_ville'] ?? '') ?: null;
    $email = trim($_POST['email'] ?? '');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Email du résident invalide.';
    }
    $email = $email ?: null;
    $telephone = trim($_POST['telephone'] ?? '') ?: null;

    $situation = $_POST['situation'] ?? '';
    if (!in_array($situation, ['domicile', 'trois_chenes', 'hug', 'autre'], true)) {
        $formErrors[] = 'Situation actuelle requise.';
    }
    $situationAutre = ($situation === 'autre') ? (trim($_POST['situation_autre'] ?? '') ?: null) : null;

    $refNomPrenom = trim($_POST['ref_nom_prenom'] ?? '');
    if ($refNomPrenom === '') $formErrors[] = 'Nom et prénom de la personne de référence requis.';

    $refAdmin    = !empty($_POST['ref_aspect_administratifs']) ? 1 : 0;
    $refSoins    = !empty($_POST['ref_aspect_soins']) ? 1 : 0;
    $refCurateur = !empty($_POST['ref_curateur']) ? 1 : 0;
    $refLien     = trim($_POST['ref_lien_parente'] ?? '') ?: null;
    $refAutre    = trim($_POST['ref_autre'] ?? '') ?: null;
    $refRue   = trim($_POST['ref_adresse_rue'] ?? '') ?: null;
    $refComp  = trim($_POST['ref_adresse_complement'] ?? '') ?: null;
    $refCp    = trim($_POST['ref_adresse_cp'] ?? '') ?: null;
    $refVille = trim($_POST['ref_adresse_ville'] ?? '') ?: null;
    $refEmail    = trim($_POST['ref_email'] ?? '');
    $refTel      = trim($_POST['ref_telephone'] ?? '') ?: null;

    if ($refEmail === '' || !filter_var($refEmail, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Email de la personne de référence requis et valide.';
    }

    // Mot de passe (pour le suivi du dossier + futur espace famille)
    $password        = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
    if (strlen($password) < 8) {
        $formErrors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $passwordConfirm) {
        $formErrors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    // Vérifier qu'il n'existe pas déjà un dossier avec cet email
    if ($refEmail && filter_var($refEmail, FILTER_VALIDATE_EMAIL)) {
        $existing = Db::fetch("SELECT id FROM admissions_candidats WHERE ref_email = ? LIMIT 1", [$refEmail]);
        if ($existing) {
            $formErrors[] = 'Un dossier existe déjà pour cet email. Si c\'est votre dossier, connectez-vous depuis la page de suivi. Sinon, contactez la direction.';
        }
    }

    $medNom = trim($_POST['med_nom'] ?? '') ?: null;
    $medRue   = trim($_POST['med_adresse_rue'] ?? '') ?: null;
    $medComp  = trim($_POST['med_adresse_complement'] ?? '') ?: null;
    $medCp    = trim($_POST['med_adresse_cp'] ?? '') ?: null;
    $medVille = trim($_POST['med_adresse_ville'] ?? '') ?: null;
    $medEmail = trim($_POST['med_email'] ?? '');
    if ($medEmail && !filter_var($medEmail, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Email du médecin traitant invalide.';
        $medEmail = null;
    }
    $medEmail = $medEmail ?: null;
    $medTel = trim($_POST['med_telephone'] ?? '') ?: null;

    if (empty($_POST['consentement'])) {
        $formErrors[] = 'Vous devez accepter le traitement des données pour valider la demande.';
    }

    if (!$formErrors) {
        $newId = Uuid::v4();
        $newToken = Uuid::v4();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        Db::exec(
            "INSERT INTO admissions_candidats (
                id, token_acces, password_hash, type_demande, date_demande,
                nom_prenom, date_naissance,
                adresse_rue, adresse_complement, adresse_cp, adresse_ville,
                email, telephone,
                situation, situation_autre,
                ref_nom_prenom, ref_aspect_administratifs, ref_aspect_soins,
                ref_lien_parente, ref_curateur, ref_autre,
                ref_adresse_rue, ref_adresse_complement, ref_adresse_cp, ref_adresse_ville,
                ref_email, ref_telephone,
                med_nom,
                med_adresse_rue, med_adresse_complement, med_adresse_cp, med_adresse_ville,
                med_email, med_telephone,
                statut, ip_soumission
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'demande_envoyee', ?)",
            [
                $newId, $newToken, $passwordHash, $typeDemande, $dateDemande,
                $nomPrenom, $dateNaissance,
                $adresseRue, $adresseComp, $adresseCp, $adresseVille,
                $email, $telephone,
                $situation, $situationAutre,
                $refNomPrenom, $refAdmin, $refSoins,
                $refLien, $refCurateur, $refAutre,
                $refRue, $refComp, $refCp, $refVille,
                $refEmail, $refTel,
                $medNom,
                $medRue, $medComp, $medCp, $medVille,
                $medEmail, $medTel,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]
        );

        Db::exec(
            "INSERT INTO admissions_historique (id, candidat_id, action, to_status, commentaire)
             VALUES (?, ?, 'creation', 'demande_envoyee', ?)",
            [Uuid::v4(), $newId, 'Demande soumise via le site web par ' . $refNomPrenom]
        );

        // Email de confirmation (pas de magic link, l'accès se fait via mot de passe)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $suiviUrl = $scheme . '://' . $host . '/spocspace/website/admissions-suivi.php';

        if ($emsEmail) {
            $subject = '=?UTF-8?B?' . base64_encode('[' . $emsNom . '] Confirmation de votre demande d\'admission') . '?=';
            $body = "<html><body style=\"font-family:Inter,Arial,sans-serif;color:#1A2E1A;max-width:600px;margin:auto;padding:20px;background:#FAFDF7;\">"
                  . "<h2 style=\"color:#2E7D32;font-family:'Playfair Display',Georgia,serif;\">Demande d'admission reçue</h2>"
                  . "<p>Bonjour " . h($refNomPrenom) . ",</p>"
                  . "<p>Nous accusons réception de votre demande d'admission concernant <strong>" . h($nomPrenom) . "</strong>.</p>"
                  . "<p>Votre dossier va être examiné par la direction de <strong>" . h($emsNom) . "</strong>. Vous serez informé(e) par email de la suite de la procédure.</p>"
                  . "<p><strong>Pour suivre votre dossier :</strong> connectez-vous à la page de suivi avec votre email (<em>" . h($refEmail) . "</em>) et le mot de passe que vous avez choisi lors de la demande.</p>"
                  . "<p style=\"margin:24px 0;\"><a href=\"" . h($suiviUrl) . "\" style=\"background:#2E7D32;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:600;\">Accéder au suivi du dossier</a></p>"
                  . "<p style=\"font-size:12px;color:#4A6548;\">Ou copiez ce lien : " . h($suiviUrl) . "</p>"
                  . "<p style=\"font-size:13px;color:#4A6548;\">En cas d'oubli de mot de passe, utilisez le lien « Mot de passe oublié » sur la page de connexion.</p>"
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

            $recipients = [$refEmail];
            if ($email && strcasecmp($email, $refEmail) !== 0) $recipients[] = $email;
            foreach ($recipients as $rcpt) {
                @mail($rcpt, $subject, $body, $headers);
            }
        }

        $formSuccess = true;
        $formEmail = $refEmail;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admissions — <?= h($emsNom) ?></title>
<meta name="description" content="Informations sur les admissions, documents requis, conditions d'entrée et questions fréquentes sur l'EMS La Terrassière SA à Genève.">
<meta name="robots" content="index, follow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
:root {
  --adm-green: #2E7D32;
  --adm-green-light: rgba(46,125,50,0.06);
  --adm-green-border: rgba(46,125,50,0.12);
  --adm-bg: #FAFDF7;
  --adm-surface: #FFFFFF;
  --adm-text: #1A2E1A;
  --adm-text-secondary: #4A6548;
  --adm-text-muted: #7E9A7A;
  --adm-border: #D8E8D0;
  --adm-radius: 16px;
  --adm-shadow: 0 1px 3px rgba(46,125,50,0.04), 0 1px 2px rgba(0,0,0,0.02);
  --adm-shadow-md: 0 4px 12px rgba(46,125,50,0.08), 0 2px 4px rgba(0,0,0,0.04);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--adm-bg); color: var(--adm-text); line-height: 1.7; }

/* ── Shell ── */
.adm-shell { max-width: 900px; margin: 0 auto; padding: 50px 20px 60px; }

/* ── Hero ── */
.adm-hero-section {
  background-color: #D8E8CC;
  background-image:
    linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%),
    url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg');
  background-repeat: no-repeat, repeat;
  background-size: auto, 120px 120px;
  padding: 120px 20px 60px;
  text-align: center;
  border-bottom: 1px solid var(--adm-border);
}
.adm-hero-section h1 {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 600;
  color: var(--adm-text);
  margin: 0 0 12px;
}
.adm-hero-section h1 i { color: #E6C220; margin-right: 10px; }
.adm-hero-section p {
  font-size: 1.05rem;
  color: var(--adm-text-secondary);
  max-width: 640px;
  margin: 0 auto;
}
/* Breadcrumb */
.adm-breadcrumb-wrap { border-bottom: 1px solid #E5EAE078; }
.adm-breadcrumb {
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .87rem;
  color: var(--adm-text-muted, #7E9A7A);
  list-style: none;
}
.adm-breadcrumb a {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--adm-text-secondary); text-decoration: none;
  transition: color .2s; font-weight: 500;
}
.adm-breadcrumb a:hover { color: var(--adm-green); }
.adm-breadcrumb-sep { color: #C8D4C2; }
.adm-breadcrumb-current {
  color: var(--adm-text); font-weight: 600;
  display: inline-flex; align-items: center; gap: 6px;
}
.adm-breadcrumb-current i { color: #E6C220; }

/* ── Section ── */
.adm-section { margin-bottom: 40px; }
.adm-section-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.adm-section-title i { color: var(--adm-green); font-size: 1.1rem; }

/* ── Card ── */
.adm-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 28px; box-shadow: var(--adm-shadow); margin-bottom: 20px; }

/* ── Documents list ── */
.adm-doc-list { list-style: none; padding: 0; margin: 0; }
.adm-doc-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(46,125,50,0.08); }
.adm-doc-item:last-child { border: none; }
.adm-doc-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--adm-green-light); color: var(--adm-green); display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.adm-doc-name { font-weight: 600; font-size: .92rem; }
.adm-doc-name a { color: var(--adm-green); text-decoration: none; }
.adm-doc-name a:hover { text-decoration: underline; }

/* ── FAQ ── */
.adm-faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .adm-faq-grid { grid-template-columns: 1fr; } }
.adm-faq-item { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 20px; }
.adm-faq-item h6 { font-size: .92rem; font-weight: 700; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; }
.adm-faq-item h6 i { color: var(--adm-green); margin-top: 2px; flex-shrink: 0; }
.adm-faq-item p { font-size: .88rem; color: var(--adm-text-secondary); margin: 0; }
.adm-faq-item a { color: var(--adm-green); font-weight: 600; text-decoration: none; }
.adm-faq-item a:hover { text-decoration: underline; }

/* ── Liens utiles ── */
.adm-links-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 768px) { .adm-links-grid { grid-template-columns: 1fr; } }
.adm-link-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 20px; }
.adm-link-card h6 { font-size: .88rem; font-weight: 700; margin-bottom: 8px; color: var(--adm-green); }
.adm-link-card p { font-size: .82rem; color: var(--adm-text-secondary); margin-bottom: 2px; }
.adm-link-card a { color: var(--adm-green); font-size: .82rem; text-decoration: none; font-weight: 600; }
.adm-link-card a:hover { text-decoration: underline; }

/* ── Note ── */
.adm-note { background: var(--adm-green-light); border: 1px solid var(--adm-green-border); border-radius: 12px; padding: 16px 20px; font-size: .9rem; color: var(--adm-text-secondary); margin-top: 12px; }
.adm-note i { color: var(--adm-green); margin-right: 6px; }

@media (max-width: 576px) {
  .adm-hero h1 { font-size: 1.5rem; }
  .adm-card { padding: 20px; }
}

/* ── Choix parcours (télécharger vs en ligne) ── */
.adm-paths { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
@media (max-width: 768px) { .adm-paths { grid-template-columns: 1fr; } }
.adm-path-card {
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  border-radius: var(--adm-radius);
  padding: 22px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
  transition: box-shadow .2s, border-color .2s, transform .15s;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
}
.adm-path-card:hover {
  box-shadow: var(--adm-shadow-md);
  border-color: var(--adm-green);
  transform: translateY(-2px);
  text-decoration: none;
  color: inherit;
}
.adm-path-card:focus-visible {
  outline: 2px solid var(--adm-green);
  outline-offset: 3px;
}
.adm-path-card h6 { color: var(--adm-text); }
.adm-path-card:hover h6 { color: var(--adm-green); }
.adm-path-icon {
  width: 42px; height: 42px; border-radius: 12px;
  background: var(--adm-green-light); color: var(--adm-green);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.adm-path-card h6 { font-size: 1rem; font-weight: 700; margin-bottom: 4px; }
.adm-path-card p { font-size: .88rem; color: var(--adm-text-secondary); margin: 0 0 10px; }
.adm-path-card .adm-anchor {
  display: inline-flex; align-items: center; gap: 4px;
  color: var(--adm-green); font-weight: 600; font-size: .88rem;
  transition: gap .15s;
}
.adm-path-card:hover .adm-anchor { gap: 10px; }
.adm-path-card:hover .adm-anchor i { transform: translateX(2px); }
.adm-path-card .adm-anchor i { transition: transform .15s; }

/* ── Formulaire en ligne ── */
.adm-form-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 32px; box-shadow: var(--adm-shadow); margin-bottom: 20px; }
.adm-form-fieldset { border: none; padding: 0; margin: 0 0 28px; }
.adm-form-fieldset legend {
  font-size: 1rem;
  font-weight: 700;
  color: var(--adm-green);
  padding: 0;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid var(--adm-border);
  padding-bottom: 8px;
  width: 100%;
}
.adm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px; }
.adm-form-row.full { grid-template-columns: 1fr; }
@media (max-width: 576px) { .adm-form-row { grid-template-columns: 1fr; } }
.adm-field label { display: block; font-size: .85rem; font-weight: 600; color: var(--adm-text); margin-bottom: 6px; }
.adm-field label .req { color: #B85450; }
.adm-input-wrap { position: relative; }
.adm-input-wrap .adm-eye {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: none; border: none; padding: 6px 8px; cursor: pointer;
  color: var(--adm-text-muted); display: flex; align-items: center; justify-content: center;
  border-radius: 8px; font-size: 1.05rem; transition: color .15s, background .15s;
}
.adm-input-wrap .adm-eye:hover { color: var(--adm-green); background: var(--adm-green-light); }
.adm-input-wrap input { padding-right: 44px !important; }
.adm-field input[type="text"], .adm-field input[type="email"], .adm-field input[type="password"], .adm-field input[type="date"], .adm-field input[type="tel"], .adm-field textarea, .adm-field select {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--adm-border);
  border-radius: 10px;
  background: #fff;
  color: var(--adm-text);
  font-family: inherit;
  font-size: .92rem;
  transition: border-color .15s, box-shadow .15s;
}
.adm-field input:focus, .adm-field textarea:focus, .adm-field select:focus {
  outline: none;
  border-color: var(--adm-green);
  box-shadow: 0 0 0 3px rgba(46,125,50,0.12);
}
.adm-field textarea { min-height: 80px; resize: vertical; }
.adm-field .hint { font-size: .78rem; color: var(--adm-text-muted); margin-top: 4px; }
.adm-radio-group, .adm-check-group { display: flex; flex-direction: column; gap: 6px; }
.adm-radio-group.inline, .adm-check-group.inline { flex-direction: row; gap: 24px; flex-wrap: wrap; }
.adm-check-label {
  display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: .92rem; color: var(--adm-text);
  padding: 4px 0;
}
.adm-check-label input { width: 18px; height: 18px; accent-color: var(--adm-green); margin: 0; flex-shrink: 0; }
.adm-consent {
  background: var(--adm-green-light);
  border: 1px solid var(--adm-green-border);
  padding: 14px 18px;
  border-radius: 12px;
  margin: 20px 0;
  font-size: .88rem;
  color: var(--adm-text-secondary);
}
.adm-alert {
  padding: 14px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: .92rem;
}
.adm-alert.error { background: #FDEDEA; color: #8B2E26; border: 1px solid #F5C5BB; }
.adm-alert.success { background: var(--adm-green-light); color: var(--adm-green); border: 1px solid var(--adm-green-border); }
.adm-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 28px;
  border: none;
  border-radius: 10px;
  font-size: .95rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s, transform .05s;
  font-family: inherit;
}
.adm-btn-primary { background: var(--adm-green); color: #fff; }
.adm-btn-primary:hover { background: #245d28; }
.adm-btn-primary:active { transform: translateY(1px); }
.adm-form-actions { display: flex; justify-content: flex-end; margin-top: 20px; }
.adm-honey { position: absolute; left: -10000px; width: 1px; height: 1px; opacity: 0; }

/* ── Succès ── */
.adm-success-card {
  background: var(--adm-surface);
  border: 1px solid var(--adm-green-border);
  border-radius: var(--adm-radius);
  padding: 40px;
  text-align: center;
  box-shadow: var(--adm-shadow-md);
}
.adm-success-card .icon { font-size: 3.4rem; color: var(--adm-green); margin-bottom: 14px; }
.adm-success-card h3 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.6rem; margin-bottom: 12px; color: var(--adm-text); }
.adm-success-card p { color: var(--adm-text-secondary); max-width: 560px; margin: 0 auto 10px; font-size: .95rem; }
.adm-token-box {
  background: var(--adm-green-light);
  border: 1px solid var(--adm-green-border);
  padding: 14px;
  border-radius: 10px;
  margin: 22px auto;
  max-width: 560px;
  font-family: monospace;
  font-size: .82rem;
  word-break: break-all;
  color: var(--adm-text);
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══ HERO ═══ -->
<section class="adm-hero-section">
  <h1><i class="bi bi-clipboard-check"></i> Admissions</h1>
  <p>Toutes les informations et documents pour préparer une admission à l'EMS La Terrassière SA. N'hésitez pas à nous contacter pour toute question.</p>
</section>

<!-- ═══ BREADCRUMB ═══ -->
<nav class="adm-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="adm-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="adm-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="adm-breadcrumb-current" aria-current="page"><i class="bi bi-clipboard-check"></i> Admissions</li>
  </ol>
</nav>

<div class="adm-shell">

  <!-- ═══ DEUX PARCOURS ═══ -->
  <div class="adm-section" id="demande">
    <h3 class="adm-section-title"><i class="bi bi-send-check"></i> Déposer une demande d'admission</h3>
    <p style="color:var(--adm-text-secondary);margin-bottom:18px;font-size:.95rem">
      Deux possibilités s'offrent à vous pour transmettre votre demande d'inscription :
    </p>
    <div class="adm-paths">
      <a href="#formulaire" class="adm-path-card">
        <div class="adm-path-icon"><i class="bi bi-pencil-square"></i></div>
        <div>
          <h6>Remplir la demande en ligne</h6>
          <p>Formulaire de pré-inscription rempli en ligne. Vous choisissez un mot de passe qui vous permettra de suivre votre dossier.</p>
          <span class="adm-anchor">Remplir maintenant <i class="bi bi-arrow-right"></i></span>
        </div>
      </a>
      <a href="#dossier" class="adm-path-card">
        <div class="adm-path-icon"><i class="bi bi-folder-symlink"></i></div>
        <div>
          <h6>Télécharger le dossier papier</h6>
          <p>Téléchargez les documents PDF, remplissez-les et retournez-nous le dossier complet par courrier ou email.</p>
          <span class="adm-anchor">Voir les documents <i class="bi bi-arrow-right"></i></span>
        </div>
      </a>
    </div>
    <a href="/spocspace/website/admissions-suivi.php" class="adm-path-card" style="margin-top:16px">
      <div class="adm-path-icon"><i class="bi bi-box-arrow-in-right"></i></div>
      <div>
        <h6>Suivre un dossier déjà soumis</h6>
        <p>Connectez-vous à votre espace de suivi avec l'email et le mot de passe choisis lors de votre demande pour consulter l'état d'avancement.</p>
        <span class="adm-anchor">Accéder au suivi <i class="bi bi-arrow-right"></i></span>
      </div>
    </a>
  </div>

  <!-- ═══ DOCUMENTS REQUIS ═══ -->
  <div class="adm-section" id="dossier">
    <h3 class="adm-section-title"><i class="bi bi-folder2-open"></i> Dossier d'inscription papier</h3>
    <div class="adm-card">
      <p style="margin-bottom:16px">L'inscription se fait sur la base d'un dossier complet. Les documents suivants sont à télécharger et à nous retourner :</p>
      <ul class="adm-doc-list">
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-text"></i></div>
          <div class="adm-doc-name"><a href="#">Demande d'inscription</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-text"></i></div>
          <div class="adm-doc-name"><a href="#">300-04 Formulaire d'inscription administrative en EMS</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-medical"></i></div>
          <div class="adm-doc-name"><a href="#">Rapport medical & formulaire activites de la vie quotidienne (AVQ)</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-list-check"></i></div>
          <div class="adm-doc-name"><a href="#">Recapitulatif des documents a fournir</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-ruled"></i></div>
          <div class="adm-doc-name"><a href="#">Contrat-type d'accueil</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-shield-check"></i></div>
          <div class="adm-doc-name"><a href="#">Reglement interne</a></div>
        </li>
      </ul>
      <div class="adm-note">
        <i class="bi bi-info-circle"></i> La reception peut egalement vous envoyer ces documents sur demande au <strong>022 718 62 00</strong>. L'inscription sur la liste d'attente deviendra effective a la reception du dossier complet.
      </div>
    </div>
  </div>

  <!-- ═══ FORMULAIRE EN LIGNE ═══ -->
  <div class="adm-section" id="formulaire">
    <h3 class="adm-section-title"><i class="bi bi-pencil-square"></i> Demande d'inscription en ligne</h3>

    <?php if ($formSuccess): ?>
      <div class="adm-success-card">
        <div class="icon"><i class="bi bi-check-circle-fill"></i></div>
        <h3>Votre demande a bien été envoyée</h3>
        <p>Un email de confirmation vient d'être envoyé à <strong><?= h($formEmail) ?></strong>.</p>
        <p>La direction de <strong><?= h($emsNom) ?></strong> vous contactera prochainement pour la suite de la procédure.</p>
        <div class="adm-token-box" style="text-align:left">
          <strong><i class="bi bi-key"></i> Pour suivre votre dossier à tout moment :</strong><br>
          Connectez-vous sur la page de suivi avec votre email (<em><?= h($formEmail) ?></em>) et le mot de passe que vous avez choisi.
        </div>
        <p style="margin-top:20px">
          <a href="/spocspace/website/admissions-suivi.php" class="adm-btn adm-btn-primary" style="text-decoration:none">
            <i class="bi bi-box-arrow-in-right"></i> Accéder au suivi du dossier
          </a>
        </p>
        <p style="font-size:.82rem;color:var(--adm-text-muted);margin-top:20px">Conservez précieusement vos identifiants : ils vous serviront aussi pour accéder à l'espace famille si votre dossier est accepté.</p>
      </div>
    <?php else: ?>

      <?php if ($formErrors): ?>
        <div class="adm-alert error">
          <strong>Merci de corriger les points suivants :</strong>
          <ul style="margin:6px 0 0;padding-left:22px">
            <?php foreach ($formErrors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="#formulaire" autocomplete="off" novalidate id="admissionForm">
        <input type="hidden" name="admission_submit" value="1">
        <input type="text" name="website_url" class="adm-honey" tabindex="-1" autocomplete="off">

        <div class="adm-form-card" style="background:linear-gradient(135deg, rgba(46,125,50,0.04) 0%, rgba(216,232,204,0.20) 100%);border:1px solid var(--adm-green-border);margin-bottom:20px">
          <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:16px">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--adm-green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
              <i class="bi bi-shield-lock"></i>
            </div>
            <div>
              <h4 style="font-family:'Playfair Display',Georgia,serif;font-size:1.2rem;margin:0 0 4px;color:var(--adm-text)">Accès à votre espace de suivi</h4>
              <p style="margin:0;color:var(--adm-text-secondary);font-size:.92rem">
                <i class="bi bi-info-circle" style="color:var(--adm-green)"></i> <strong>Conservez bien ces informations</strong> : elles vous permettent de suivre l'avancement de votre dossier, et — si votre demande est acceptée — d'accéder ensuite à votre espace famille pour les prochaines étapes.
              </p>
            </div>
          </div>

          <div class="adm-form-row">
            <div class="adm-field">
              <label>Mot de passe <span class="req">*</span></label>
              <div class="adm-input-wrap">
                <input type="password" name="password" minlength="8" required autocomplete="new-password">
                <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
              </div>
              <div class="hint">Au moins 8 caractères. À conserver précieusement.</div>
            </div>
            <div class="adm-field">
              <label>Confirmer le mot de passe <span class="req">*</span></label>
              <div class="adm-input-wrap">
                <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
              </div>
            </div>
          </div>
          <p style="font-size:.82rem;color:var(--adm-text-muted);margin:0">Votre email de connexion sera celui de la <strong>personne de référence</strong> que vous renseignerez ci-dessous.</p>
        </div>

        <div class="adm-form-card">
          <p style="color:var(--adm-text-secondary);margin-bottom:22px;font-size:.92rem">
            Ce formulaire correspond à la <strong>demande d'inscription</strong> (étape 1). Les documents médicaux et le formulaire administratif cantonal 300-04 vous seront demandés dans un second temps.
          </p>

        <fieldset class="adm-form-fieldset">
          <legend><i class="bi bi-flag"></i> Type de demande</legend>
          <div class="adm-form-row">
            <div class="adm-field">
              <label>Nature de la demande <span class="req">*</span></label>
              <div class="adm-radio-group inline">
                <label class="adm-check-label"><input type="radio" name="type_demande" value="urgente" <?= ($_POST['type_demande'] ?? '') === 'urgente' ? 'checked' : '' ?>> Urgente</label>
                <label class="adm-check-label"><input type="radio" name="type_demande" value="preventive" <?= ($_POST['type_demande'] ?? '') === 'preventive' ? 'checked' : '' ?>> Préventive</label>
              </div>
            </div>
            <div class="adm-field">
              <label>Date de la demande</label>
              <input type="date" name="date_demande" value="<?= h($_POST['date_demande'] ?? date('Y-m-d')) ?>">
            </div>
          </div>
        </fieldset>

        <fieldset class="adm-form-fieldset">
          <legend><i class="bi bi-person"></i> Personne concernée</legend>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Nom et prénom <span class="req">*</span></label>
              <input type="text" name="nom_prenom" value="<?= h($_POST['nom_prenom'] ?? '') ?>" required>
            </div>
          </div>
          <div class="adm-form-row">
            <div class="adm-field">
              <label>Date de naissance</label>
              <input type="date" name="date_naissance" value="<?= h($_POST['date_naissance'] ?? '') ?>">
            </div>
            <div class="adm-field">
              <label>Numéro de téléphone</label>
              <input type="tel" name="telephone" value="<?= h($_POST['telephone'] ?? '') ?>">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Rue et numéro</label>
              <input type="text" name="adresse_rue" value="<?= h($_POST['adresse_rue'] ?? '') ?>" placeholder="ex: Rue de Carouge 45">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Complément d'adresse <span style="font-weight:400;color:var(--adm-text-muted);font-size:.78rem">(facultatif)</span></label>
              <input type="text" name="adresse_complement" value="<?= h($_POST['adresse_complement'] ?? '') ?>" placeholder="ex: c/o, appartement, étage…">
            </div>
          </div>
          <div class="adm-form-row" style="grid-template-columns:160px 1fr">
            <div class="adm-field">
              <label>Code postal</label>
              <input type="text" name="adresse_cp" value="<?= h($_POST['adresse_cp'] ?? '') ?>" placeholder="1205" maxlength="10">
            </div>
            <div class="adm-field">
              <label>Ville</label>
              <input type="text" name="adresse_ville" value="<?= h($_POST['adresse_ville'] ?? '') ?>" placeholder="Genève">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Adresse email</label>
              <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>">
              <div class="hint">Si renseignée, la personne concernée recevra aussi le lien de suivi.</div>
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Situation actuelle <span class="req">*</span></label>
              <div class="adm-radio-group">
                <label class="adm-check-label"><input type="radio" name="situation" value="domicile" <?= ($_POST['situation'] ?? '') === 'domicile' ? 'checked' : '' ?>> À son domicile</label>
                <label class="adm-check-label"><input type="radio" name="situation" value="trois_chenes" <?= ($_POST['situation'] ?? '') === 'trois_chenes' ? 'checked' : '' ?>> À l'hôpital des Trois-Chênes</label>
                <label class="adm-check-label"><input type="radio" name="situation" value="hug" <?= ($_POST['situation'] ?? '') === 'hug' ? 'checked' : '' ?>> Aux HUG</label>
                <label class="adm-check-label"><input type="radio" name="situation" value="autre" <?= ($_POST['situation'] ?? '') === 'autre' ? 'checked' : '' ?>> Autre</label>
              </div>
              <input type="text" name="situation_autre" value="<?= h($_POST['situation_autre'] ?? '') ?>" placeholder="Précisez…" style="margin-top:8px">
            </div>
          </div>
        </fieldset>

        <fieldset class="adm-form-fieldset">
          <legend><i class="bi bi-people"></i> Personne de référence</legend>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Nom et prénom <span class="req">*</span></label>
              <input type="text" name="ref_nom_prenom" value="<?= h($_POST['ref_nom_prenom'] ?? '') ?>" required>
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Pour les aspects</label>
              <div class="adm-check-group inline">
                <label class="adm-check-label"><input type="checkbox" name="ref_aspect_administratifs" value="1" <?= !empty($_POST['ref_aspect_administratifs']) ? 'checked' : '' ?>> Administratifs</label>
                <label class="adm-check-label"><input type="checkbox" name="ref_aspect_soins" value="1" <?= !empty($_POST['ref_aspect_soins']) ? 'checked' : '' ?>> Soins</label>
                <label class="adm-check-label"><input type="checkbox" name="ref_curateur" value="1" <?= !empty($_POST['ref_curateur']) ? 'checked' : '' ?>> Curateur</label>
              </div>
            </div>
          </div>
          <div class="adm-form-row">
            <div class="adm-field">
              <label>Lien de parenté</label>
              <input type="text" name="ref_lien_parente" value="<?= h($_POST['ref_lien_parente'] ?? '') ?>" placeholder="ex: fils, fille, conjoint…">
            </div>
            <div class="adm-field">
              <label>Autre (précisez)</label>
              <input type="text" name="ref_autre" value="<?= h($_POST['ref_autre'] ?? '') ?>">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Rue et numéro</label>
              <input type="text" name="ref_adresse_rue" value="<?= h($_POST['ref_adresse_rue'] ?? '') ?>" placeholder="ex: Chemin des Clochettes 12">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Complément d'adresse <span style="font-weight:400;color:var(--adm-text-muted);font-size:.78rem">(facultatif)</span></label>
              <input type="text" name="ref_adresse_complement" value="<?= h($_POST['ref_adresse_complement'] ?? '') ?>" placeholder="ex: c/o, appartement, étage…">
            </div>
          </div>
          <div class="adm-form-row" style="grid-template-columns:160px 1fr">
            <div class="adm-field">
              <label>Code postal</label>
              <input type="text" name="ref_adresse_cp" value="<?= h($_POST['ref_adresse_cp'] ?? '') ?>" placeholder="1206" maxlength="10">
            </div>
            <div class="adm-field">
              <label>Ville</label>
              <input type="text" name="ref_adresse_ville" value="<?= h($_POST['ref_adresse_ville'] ?? '') ?>" placeholder="Genève">
            </div>
          </div>
          <div class="adm-form-row">
            <div class="adm-field">
              <label>Adresse email <span class="req">*</span></label>
              <input type="email" name="ref_email" value="<?= h($_POST['ref_email'] ?? '') ?>" required>
              <div class="hint">Le lien de suivi vous sera envoyé à cette adresse.</div>
            </div>
            <div class="adm-field">
              <label>Numéro de téléphone</label>
              <input type="tel" name="ref_telephone" value="<?= h($_POST['ref_telephone'] ?? '') ?>">
            </div>
          </div>
        </fieldset>

        <fieldset class="adm-form-fieldset">
          <legend><i class="bi bi-heart-pulse"></i> Médecin traitant</legend>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Nom</label>
              <input type="text" name="med_nom" value="<?= h($_POST['med_nom'] ?? '') ?>">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Rue et numéro</label>
              <input type="text" name="med_adresse_rue" value="<?= h($_POST['med_adresse_rue'] ?? '') ?>" placeholder="ex: Avenue de Champel 10">
            </div>
          </div>
          <div class="adm-form-row full">
            <div class="adm-field">
              <label>Complément d'adresse <span style="font-weight:400;color:var(--adm-text-muted);font-size:.78rem">(facultatif)</span></label>
              <input type="text" name="med_adresse_complement" value="<?= h($_POST['med_adresse_complement'] ?? '') ?>" placeholder="ex: Cabinet médical, étage…">
            </div>
          </div>
          <div class="adm-form-row" style="grid-template-columns:160px 1fr">
            <div class="adm-field">
              <label>Code postal</label>
              <input type="text" name="med_adresse_cp" value="<?= h($_POST['med_adresse_cp'] ?? '') ?>" placeholder="1206" maxlength="10">
            </div>
            <div class="adm-field">
              <label>Ville</label>
              <input type="text" name="med_adresse_ville" value="<?= h($_POST['med_adresse_ville'] ?? '') ?>" placeholder="Genève">
            </div>
          </div>
          <div class="adm-form-row">
            <div class="adm-field">
              <label>Adresse email</label>
              <input type="email" name="med_email" value="<?= h($_POST['med_email'] ?? '') ?>">
            </div>
            <div class="adm-field">
              <label>Numéro de téléphone</label>
              <input type="tel" name="med_telephone" value="<?= h($_POST['med_telephone'] ?? '') ?>">
            </div>
          </div>
        </fieldset>

        <div class="adm-consent">
          <label class="adm-check-label" style="align-items:flex-start">
            <input type="checkbox" name="consentement" value="1" required style="margin-top:2px">
            <span>J'accepte que les informations transmises soient utilisées pour l'étude de la demande d'admission et conservées conformément à la politique de confidentialité de <?= h($emsNom) ?>.</span>
          </label>
        </div>

          <div class="adm-form-actions">
            <button type="submit" class="adm-btn adm-btn-primary">
              <i class="bi bi-send"></i> Envoyer la demande
            </button>
          </div>
        </div>
      </form>

    <?php endif; ?>
  </div>

  <!-- ═══ FAQ ═══ -->
  <div class="adm-section">
    <h3 class="adm-section-title"><i class="bi bi-question-circle"></i> Questions frequentes</h3>
    <div class="adm-faq-grid">

      <div class="adm-faq-item">
        <h6><i class="bi bi-building"></i> Puis-je visiter l'etablissement ?</h6>
        <p>Certainement ! Vous trouverez nos coordonnees en cliquant ici : <a href="/spocspace/website/#contact">Contactez-nous</a></p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-bank"></i> Quelles demarches aupres du SPC ?</h6>
        <p>Vous trouverez tous les renseignements souhaites sur <a href="https://www.ge.ch/prestations-complementaires-avsai/demander-prestations-complementaires-avsai" target="_blank" rel="noopener">www.ge.ch</a></p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-person-check"></i> Conditions d'entree ?</h6>
        <p>Avoir l'age de l'AVS.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-box-seam"></i> Peut-on apporter ses meubles ?</h6>
        <p>Oui, sauf le lit et la table de chevet que nous fournissons. Lors de la visite, vous aurez la possibilite de poser toutes les questions relatives a votre installation.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-currency-exchange"></i> Quel est le prix de pension ?</h6>
        <p>Le prix de pension est un prix unique, quelle que soit la chambre occupee ou la situation financiere du residant. Il est fixe chaque annee par l'Etat.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-emoji-heart-eyes"></i> Acceptez-vous les animaux ?</h6>
        <p>Non. Nous n'avons, malheureusement, pas assez d'espace pour nos amies les betes.</p>
      </div>

    </div>
  </div>

  <!-- ═══ LIENS & ADRESSES UTILES ═══ -->
  <div class="adm-section">
    <h3 class="adm-section-title"><i class="bi bi-link-45deg"></i> Liens et adresses utiles</h3>
    <p style="color:var(--adm-text-secondary);margin-bottom:20px;font-size:.92rem">Pour obtenir des informations complementaires sur les aides et la prise en charge des personnes agees et/ou dependantes :</p>

    <div class="adm-links-grid">
      <div class="adm-link-card">
        <h6>SPC/OCPA</h6>
        <p><strong>Service des prestations complementaires</strong></p>
        <p>54, rte de chene<br>case postale 378<br>1211 Geneve 29</p>
        <p>Tel. 022 546 16 00<br>Fax. 022 546 17 00</p>
        <a href="https://www.ge.ch" target="_blank" rel="noopener">www.ge.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>DSC</h6>
        <p><strong>Departement de la cohesion sociale</strong></p>
        <p>2, rue de l'Hotel-de-Ville<br>case postale 3965<br>1211 Geneve 3</p>
        <p>Tel. 022 327 93 10</p>
      </div>

      <div class="adm-link-card">
        <h6>SMC</h6>
        <p><strong>Service du medecin cantonal</strong></p>
        <p>24, avenue de Beau-Sejour<br>1206 Geneve</p>
        <p>Tel. 022 546 50 00</p>
      </div>

      <div class="adm-link-card">
        <h6>FEGEMS</h6>
        <p><strong>Federation genevoise des structures d'accompagnement pour seniors</strong></p>
        <p>12, Avenue Industrielle<br>1227 Carouge</p>
        <p>Tel. 022 718 18 70</p>
        <a href="https://www.fegems.ch" target="_blank" rel="noopener">www.fegems.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>PRO SENECTUTE</h6>
        <p>5B, route de Saint-Julien<br>1227 Carouge</p>
        <p>Tel. 022 807 05 65</p>
        <a href="https://www.pro-senectute.ch" target="_blank" rel="noopener">www.pro-senectute.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>ASSOCIATION APPUIS AUX AINES</h6>
        <p>Boulevard Carl-Vogt 8<br>1205 Geneve</p>
        <p>Tel. 022 840 49 99<br>E-mail: info@appuis-aines.ch</p>
        <a href="https://www.appuis-aines.ch" target="_blank" rel="noopener">www.appuis-aines.ch</a>
      </div>
    </div>
  </div>

</div>

<!-- ═══ FOOTER ═══ -->
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
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
});
</script>

</body>
</html>
