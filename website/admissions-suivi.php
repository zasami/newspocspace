<?php
/**
 * Suivi de dossier d'admission — accès par email + mot de passe
 * URL : /spocspace/website/admissions-suivi.php
 */

require_once __DIR__ . '/../init.php';

$emsNom   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
$emsEmail = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'") ?: '';
$emsTel   = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_telephone'") ?: '';

// ─── Session dédiée famille (séparée de la session employés) ───
$loginError = null;
$logout     = isset($_GET['logout']);

if ($logout) {
    unset($_SESSION['admission_famille']);
    header('Location: /spocspace/website/admissions-suivi.php?disconnected=1');
    exit;
}

// Gestion login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $loginEmail    = trim($_POST['email'] ?? '');
    $loginPassword = (string)($_POST['password'] ?? '');

    if (!$loginEmail || !$loginPassword) {
        $loginError = 'Veuillez saisir votre email et votre mot de passe.';
    } else {
        $user = Db::fetch(
            "SELECT id, ref_email, ref_nom_prenom, password_hash
             FROM admissions_candidats
             WHERE ref_email = ? LIMIT 1",
            [$loginEmail]
        );
        if (!$user || empty($user['password_hash']) || !password_verify($loginPassword, $user['password_hash'])) {
            $loginError = 'Email ou mot de passe incorrect.';
        } else {
            $_SESSION['admission_famille'] = [
                'id'          => $user['id'],
                'email'       => $user['ref_email'],
                'nom_prenom'  => $user['ref_nom_prenom'],
                'logged_at'   => time(),
            ];
            Db::exec("UPDATE admissions_candidats SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
            header('Location: /spocspace/website/admissions-suivi.php');
            exit;
        }
    }
}

$famille = $_SESSION['admission_famille'] ?? null;
$candidat = null;
$historique = [];

if ($famille) {
    $candidat = Db::fetch("SELECT * FROM admissions_candidats WHERE id = ?", [$famille['id']]);
    if ($candidat) {
        $historique = Db::fetchAll(
            "SELECT action, from_status, to_status, commentaire, created_at
             FROM admissions_historique
             WHERE candidat_id = ?
             ORDER BY created_at DESC",
            [$candidat['id']]
        );
    } else {
        unset($_SESSION['admission_famille']);
        $famille = null;
    }
}

$statutLabels = [
    'demande_envoyee'        => ['Demande envoyée', 'pending', 'bi-hourglass-split'],
    'en_examen'              => ['En cours d\'examen', 'info', 'bi-search'],
    'etape1_validee'         => ['Étape 1 validée', 'success', 'bi-check-circle'],
    'info_manquante'         => ['Informations manquantes', 'warning', 'bi-exclamation-triangle'],
    'refuse'                 => ['Demande refusée', 'danger', 'bi-x-circle'],
    'acceptee_liste_attente' => ['Acceptée — liste d\'attente', 'success', 'bi-check-circle-fill'],
];

$situationLabels = [
    'domicile'     => 'À son domicile',
    'trois_chenes' => 'Hôpital des Trois-Chênes',
    'hug'          => 'Aux HUG',
    'autre'        => 'Autre',
];

function formatAdresse(array $c, string $prefix = ''): ?string
{
    $rue   = trim((string)($c[$prefix . 'adresse_rue'] ?? ''));
    $comp  = trim((string)($c[$prefix . 'adresse_complement'] ?? ''));
    $cp    = trim((string)($c[$prefix . 'adresse_cp'] ?? ''));
    $ville = trim((string)($c[$prefix . 'adresse_ville'] ?? ''));
    $lines = [];
    if ($rue) $lines[] = $rue;
    if ($comp) $lines[] = $comp;
    if ($cp || $ville) $lines[] = trim($cp . ' ' . $ville);
    if (!$lines) {
        $legacy = trim((string)($c[$prefix . 'adresse_postale'] ?? ''));
        return $legacy ?: null;
    }
    return implode("\n", $lines);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Suivi de demande — <?= h($emsNom) ?></title>
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
.adm-hero-section h1 { font-family: 'Playfair Display', Georgia, serif; font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 600; color: var(--adm-text); margin: 0 0 12px; }
.adm-hero-section h1 i { color: #E6C220; margin-right: 10px; }
.adm-hero-section p { font-size: 1.05rem; color: var(--adm-text-secondary); max-width: 640px; margin: 0 auto; }

.adm-breadcrumb-wrap { border-bottom: 1px solid #E5EAE078; }
.adm-breadcrumb { max-width: 1200px; margin: 0 auto; padding: 16px 20px; display: flex; align-items: center; gap: 10px; font-size: .87rem; color: var(--adm-text-muted); list-style: none; }
.adm-breadcrumb a { display: inline-flex; align-items: center; gap: 6px; color: var(--adm-text-secondary); text-decoration: none; font-weight: 500; }
.adm-breadcrumb a:hover { color: var(--adm-green); }
.adm-breadcrumb-sep { color: #C8D4C2; }
.adm-breadcrumb-current { color: var(--adm-text); font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.adm-breadcrumb-current i { color: #E6C220; }

.adm-shell { max-width: 900px; margin: 0 auto; padding: 50px 20px 60px; }
.adm-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 32px; box-shadow: var(--adm-shadow); margin-bottom: 20px; }
.adm-section-title { font-size: 1.1rem; font-weight: 700; margin: 26px 0 12px; color: var(--adm-green); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--adm-border); padding-bottom: 6px; }

.badge-type { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: .78rem; font-weight: 600; margin-left: 8px; }
.badge-type.urgente { background: #FDEDEA; color: #8B2E26; }
.badge-type.preventive { background: var(--adm-green-light); color: var(--adm-green); }

.status-pill { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 999px; font-weight: 600; font-size: .95rem; }
.status-pill.pending { background: #FEF6E0; color: #8B6914; }
.status-pill.info    { background: #E3F2FD; color: #0D47A1; }
.status-pill.success { background: var(--adm-green-light); color: var(--adm-green); }
.status-pill.warning { background: #FEF0E0; color: #9B4B14; }
.status-pill.danger  { background: #FDEDEA; color: #8B2E26; }

.kv { display: grid; grid-template-columns: 220px 1fr; gap: 10px 20px; font-size: .92rem; }
.kv dt { color: var(--adm-text-muted); font-weight: 500; }
.kv dd { margin: 0; color: var(--adm-text); }
@media (max-width: 640px) { .kv { grid-template-columns: 1fr; gap: 4px 0; } .kv dt { margin-top: 10px; font-weight: 600; } }

.hist-item { padding: 14px 0; border-top: 1px solid var(--adm-border); font-size: .9rem; }
.hist-item:first-child { border-top: none; padding-top: 0; }
.hist-date { color: var(--adm-text-muted); font-size: .82rem; }
.hist-action { font-weight: 600; margin: 4px 0; text-transform: capitalize; }

/* Login */
.adm-login-card { max-width: 460px; margin: 0 auto; background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 40px 36px; box-shadow: var(--adm-shadow); }
.adm-login-card h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.5rem; color: var(--adm-text); margin-bottom: 6px; text-align: center; }
.adm-login-card p.sub { color: var(--adm-text-secondary); text-align: center; margin-bottom: 24px; font-size: .92rem; }
.adm-field { margin-bottom: 16px; }
.adm-field label { display: block; font-size: .85rem; font-weight: 600; color: var(--adm-text); margin-bottom: 6px; }
.adm-field input { width: 100%; padding: 11px 14px; border: 1px solid var(--adm-border); border-radius: 10px; background: #fff; color: var(--adm-text); font-family: inherit; font-size: .95rem; }
.adm-input-wrap { position: relative; }
.adm-input-wrap .adm-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--adm-text-muted); display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 1.05rem; transition: color .15s, background .15s; }
.adm-input-wrap .adm-eye:hover { color: var(--adm-green); background: var(--adm-green-light); }
.adm-input-wrap input { padding-right: 44px !important; }
.adm-field input:focus { outline: none; border-color: var(--adm-green); box-shadow: 0 0 0 3px rgba(46,125,50,0.12); }
.adm-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 10px; font-size: .95rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .15s; width: 100%; }
.adm-btn-primary { background: var(--adm-green); color: #fff; }
.adm-btn-primary:hover { background: #245d28; }
.adm-btn-link { background: transparent; color: var(--adm-green); text-decoration: none; font-weight: 600; font-size: .88rem; }
.adm-btn-link:hover { text-decoration: underline; }
.adm-alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: .9rem; }
.adm-alert.error { background: #FDEDEA; color: #8B2E26; border: 1px solid #F5C5BB; }
.adm-alert.info { background: #E3F2FD; color: #0D47A1; border: 1px solid #C5D9E8; }
.adm-login-footer { display: flex; justify-content: space-between; margin-top: 16px; flex-wrap: wrap; gap: 8px; }
.adm-logout-bar { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 14px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: var(--adm-shadow); }
.adm-logout-bar .who { font-size: .9rem; color: var(--adm-text-secondary); }
.adm-logout-bar .who strong { color: var(--adm-text); }
.adm-logout-bar .actions { display: flex; gap: 8px; flex-wrap: wrap; }
.adm-logout-bar a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; text-decoration: none; font-size: .85rem; font-weight: 500; transition: background .15s; }
.adm-logout-bar a.pill-primary { background: var(--adm-green-light); color: var(--adm-green); }
.adm-logout-bar a.pill-primary:hover { background: rgba(46,125,50,0.15); }
.adm-logout-bar a.pill-neutral { background: #F0F0ED; color: var(--adm-text-secondary); }
.adm-logout-bar a.pill-neutral:hover { background: #E5E5DF; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="adm-hero-section">
  <h1><i class="bi bi-clipboard-data"></i> Suivi de votre demande</h1>
  <p>Consultez l'état d'avancement de votre dossier d'admission à <?= h($emsNom) ?></p>
</section>

<nav class="adm-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="adm-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="adm-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li><a href="/spocspace/website/admissions.php"><i class="bi bi-clipboard-check"></i> Admissions</a></li>
    <li class="adm-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="adm-breadcrumb-current" aria-current="page"><i class="bi bi-clipboard-data"></i> Suivi</li>
  </ol>
</nav>

<div class="adm-shell">

<?php if (!$famille || !$candidat): ?>

  <div class="adm-login-card">
    <h2>Accès à votre dossier</h2>
    <p class="sub">Connectez-vous avec l'email de la personne de référence et le mot de passe choisi lors de la demande.</p>

    <?php if (!empty($_GET['disconnected'])): ?>
      <div class="adm-alert info"><i class="bi bi-check-circle"></i> Vous avez été déconnecté(e).</div>
    <?php endif; ?>
    <?php if (!empty($_GET['reset_ok'])): ?>
      <div class="adm-alert info"><i class="bi bi-check-circle"></i> Mot de passe mis à jour. Vous pouvez maintenant vous connecter.</div>
    <?php endif; ?>
    <?php if ($loginError): ?>
      <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> <?= h($loginError) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="login_submit" value="1">
      <div class="adm-field">
        <label>Adresse email</label>
        <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>
      <div class="adm-field">
        <label>Mot de passe</label>
        <div class="adm-input-wrap">
          <input type="password" name="password" required autocomplete="current-password">
          <button type="button" class="adm-eye" data-toggle-password aria-label="Afficher ou masquer le mot de passe"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary">
        <i class="bi bi-box-arrow-in-right"></i> Se connecter
      </button>
    </form>

    <div class="adm-login-footer">
      <a href="/spocspace/website/admissions-mot-de-passe-oublie.php" class="adm-btn-link">Mot de passe oublié ?</a>
      <a href="/spocspace/website/admissions.php#demande" class="adm-btn-link">Nouvelle demande</a>
    </div>
  </div>

<?php else:
  $st = $statutLabels[$candidat['statut']] ?? [$candidat['statut'], 'info', 'bi-circle'];
?>

  <div class="adm-logout-bar">
    <div class="who">Connecté en tant que <strong><?= h($famille['nom_prenom']) ?></strong> <span style="color:var(--adm-text-muted)">(<?= h($famille['email']) ?>)</span></div>
    <div class="actions">
      <a href="/spocspace/website/admissions-changer-mot-de-passe.php" class="pill-primary"><i class="bi bi-key"></i> Changer le mot de passe</a>
      <a href="/spocspace/website/admissions-suivi.php?logout=1" class="pill-neutral"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>
  </div>

  <div class="adm-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:18px;margin-bottom:18px">
      <div>
        <div style="color:var(--adm-text-muted);font-size:.85rem;margin-bottom:4px">Demande soumise le <?= h(date('d/m/Y à H:i', strtotime($candidat['created_at']))) ?></div>
        <h2 style="font-family:'Playfair Display',Georgia,serif;font-weight:600;font-size:1.5rem;margin:0">
          <?= h($candidat['nom_prenom']) ?>
          <span class="badge-type <?= h($candidat['type_demande']) ?>"><?= $candidat['type_demande'] === 'urgente' ? 'Urgente' : 'Préventive' ?></span>
        </h2>
      </div>
      <div class="status-pill <?= h($st[1]) ?>">
        <i class="bi <?= h($st[2]) ?>"></i> <?= h($st[0]) ?>
      </div>
    </div>

    <div class="adm-section-title"><i class="bi bi-person"></i> Personne concernée</div>
    <dl class="kv">
      <dt>Nom et prénom</dt><dd><?= h($candidat['nom_prenom']) ?></dd>
      <?php if ($candidat['date_naissance']): ?><dt>Date de naissance</dt><dd><?= h(date('d/m/Y', strtotime($candidat['date_naissance']))) ?></dd><?php endif; ?>
      <?php $adr = formatAdresse($candidat); if ($adr): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($adr) ?></dd><?php endif; ?>
      <?php if ($candidat['email']): ?><dt>Email</dt><dd><?= h($candidat['email']) ?></dd><?php endif; ?>
      <?php if ($candidat['telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['telephone']) ?></dd><?php endif; ?>
      <dt>Situation actuelle</dt>
      <dd>
        <?= h($situationLabels[$candidat['situation']] ?? $candidat['situation']) ?>
        <?php if ($candidat['situation'] === 'autre' && $candidat['situation_autre']): ?> — <?= h($candidat['situation_autre']) ?><?php endif; ?>
      </dd>
    </dl>

    <div class="adm-section-title"><i class="bi bi-people"></i> Personne de référence</div>
    <dl class="kv">
      <dt>Nom et prénom</dt><dd><?= h($candidat['ref_nom_prenom']) ?></dd>
      <dt>Aspects pris en charge</dt>
      <dd>
        <?php
          $asp = [];
          if ($candidat['ref_aspect_administratifs']) $asp[] = 'Administratifs';
          if ($candidat['ref_aspect_soins']) $asp[] = 'Soins';
          if ($candidat['ref_curateur']) $asp[] = 'Curateur';
          echo $asp ? h(implode(' · ', $asp)) : '<span style="color:var(--adm-text-muted)">—</span>';
        ?>
      </dd>
      <?php if ($candidat['ref_lien_parente']): ?><dt>Lien de parenté</dt><dd><?= h($candidat['ref_lien_parente']) ?></dd><?php endif; ?>
      <?php if ($candidat['ref_autre']): ?><dt>Autre</dt><dd><?= h($candidat['ref_autre']) ?></dd><?php endif; ?>
      <?php $refAdrFmt = formatAdresse($candidat, 'ref_'); if ($refAdrFmt): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($refAdrFmt) ?></dd><?php endif; ?>
      <dt>Email</dt><dd><?= h($candidat['ref_email']) ?></dd>
      <?php if ($candidat['ref_telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['ref_telephone']) ?></dd><?php endif; ?>
    </dl>

    <?php $medAdrFmtBlock = formatAdresse($candidat, 'med_'); if ($candidat['med_nom'] || $candidat['med_email'] || $candidat['med_telephone'] || $medAdrFmtBlock): ?>
    <div class="adm-section-title"><i class="bi bi-heart-pulse"></i> Médecin traitant</div>
    <dl class="kv">
      <?php if ($candidat['med_nom']): ?><dt>Nom</dt><dd><?= h($candidat['med_nom']) ?></dd><?php endif; ?>
      <?php $medAdrFmt = formatAdresse($candidat, 'med_'); if ($medAdrFmt): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($medAdrFmt) ?></dd><?php endif; ?>
      <?php if ($candidat['med_email']): ?><dt>Email</dt><dd><?= h($candidat['med_email']) ?></dd><?php endif; ?>
      <?php if ($candidat['med_telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['med_telephone']) ?></dd><?php endif; ?>
    </dl>
    <?php endif; ?>
  </div>

  <?php if ($historique): ?>
  <div class="adm-card">
    <h3 style="font-family:'Playfair Display',Georgia,serif;font-size:1.3rem;margin-bottom:18px;display:flex;align-items:center;gap:10px">
      <i class="bi bi-clock-history" style="color:var(--adm-green)"></i> Historique
    </h3>
    <?php foreach ($historique as $hi): ?>
      <div class="hist-item">
        <div class="hist-date"><?= h(date('d/m/Y à H:i', strtotime($hi['created_at']))) ?></div>
        <div class="hist-action"><?= h(str_replace('_', ' ', $hi['action'])) ?></div>
        <?php if ($hi['commentaire']): ?><div style="color:var(--adm-text-secondary);font-size:.88rem"><?= h($hi['commentaire']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="text-align:center;color:var(--adm-text-muted);font-size:.88rem;margin-top:30px">
    Pour toute question, contactez <?= h($emsNom) ?>
    <?php if ($emsEmail): ?> · <a href="mailto:<?= h($emsEmail) ?>" style="color:var(--adm-green);font-weight:600"><?= h($emsEmail) ?></a><?php endif; ?>
    <?php if ($emsTel): ?> · <?= h($emsTel) ?><?php endif; ?>
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
