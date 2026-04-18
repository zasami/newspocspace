<?php
/**
 * Page de suivi d'une demande d'admission (magic link token)
 * URL : /spocspace/admission-suivi.php?token=UUID
 */

require_once __DIR__ . '/init_minimal.php';

Db::connect();

$cfg = [];
foreach (Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key IN ('ems_nom','ems_email','ems_adresse','ems_telephone','ems_logo_url')") as $r) {
    $cfg[$r['config_key']] = $r['config_value'];
}
$emsNom   = $cfg['ems_nom']   ?? 'EMS';
$emsEmail = $cfg['ems_email'] ?? '';
$emsTel   = $cfg['ems_telephone'] ?? '';
$emsAdr   = $cfg['ems_adresse'] ?? '';
$emsLogo  = $cfg['ems_logo_url'] ?? '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$token = trim($_GET['token'] ?? '');
$candidat = null;
$historique = [];

if ($token && preg_match('/^[0-9a-f-]{36}$/i', $token)) {
    $candidat = Db::fetch("SELECT * FROM admissions_candidats WHERE token_acces = ? LIMIT 1", [$token]);
    if ($candidat) {
        $historique = Db::fetchAll(
            "SELECT action, from_status, to_status, commentaire, created_at
             FROM admissions_historique
             WHERE candidat_id = ?
             ORDER BY created_at DESC",
            [$candidat['id']]
        );
    }
}

// Libellés statuts
$statutLabels = [
    'demande_envoyee'        => ['Demande envoyée', 'orange', 'bi-hourglass-split'],
    'en_examen'              => ['En cours d\'examen', 'green', 'bi-search'],
    'etape1_validee'         => ['Étape 1 validée', 'teal', 'bi-check-circle'],
    'info_manquante'         => ['Informations manquantes', 'orange', 'bi-exclamation-triangle'],
    'refuse'                 => ['Demande refusée', 'red', 'bi-x-circle'],
    'acceptee_liste_attente' => ['Acceptée — liste d\'attente', 'teal', 'bi-check-circle-fill'],
];

$situationLabels = [
    'domicile'     => 'À son domicile',
    'trois_chenes' => 'Hôpital des Trois-Chênes',
    'hug'          => 'Aux HUG',
    'autre'        => 'Autre',
];

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Suivi de demande — <?= h($emsNom) ?></title>
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
.status-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;font-weight:500;font-size:.95rem}
.status-pill.teal{background:var(--cl-teal-bg);color:var(--cl-teal-fg)}
.status-pill.green{background:var(--cl-green-bg);color:var(--cl-green-fg)}
.status-pill.orange{background:var(--cl-orange-bg);color:var(--cl-orange-fg)}
.status-pill.red{background:var(--cl-red-bg);color:var(--cl-red-fg)}
.kv{display:grid;grid-template-columns:220px 1fr;gap:8px 16px;font-size:.92rem}
.kv dt{color:var(--cl-text-secondary)}
.kv dd{margin:0;color:var(--cl-text)}
@media(max-width:640px){.kv{grid-template-columns:1fr}.kv dt{font-weight:500;color:var(--cl-text-secondary);margin-top:8px}}
.hist-item{padding:12px 0;border-top:1px solid var(--cl-border-light);font-size:.9rem}
.hist-item:first-child{border-top:none;padding-top:0}
.hist-date{color:var(--cl-text-muted);font-size:.82rem}
.hist-action{font-weight:500;margin:2px 0}
.ad-empty{background:var(--cl-surface);border:1px solid var(--cl-border);border-radius:var(--cl-radius);padding:48px;text-align:center}
.ad-empty .icon{font-size:3rem;color:var(--cl-text-muted);margin-bottom:12px}
.ad-empty h2{margin:0 0 10px}
.ad-empty p{color:var(--cl-text-secondary);max-width:500px;margin:0 auto}
.section-heading{font-size:.8rem;text-transform:uppercase;letter-spacing:.6px;color:var(--cl-text-muted);margin:24px 0 10px;font-weight:600}
.badge-type{display:inline-block;padding:3px 10px;border-radius:6px;font-size:.78rem;font-weight:500}
.badge-type.urgente{background:var(--cl-red-bg);color:var(--cl-red-fg)}
.badge-type.preventive{background:var(--cl-green-bg);color:var(--cl-green-fg)}
</style>
</head>
<body>
<div class="ad-shell">

<div class="ad-head">
  <?php if ($emsLogo): ?><img src="<?= h($emsLogo) ?>" alt="Logo"><?php endif; ?>
  <div>
    <h1><?= h($emsNom) ?></h1>
    <div class="sub">Suivi de demande d'admission</div>
  </div>
</div>

<?php if (!$candidat): ?>

<div class="ad-empty">
  <div class="icon"><i class="bi bi-exclamation-circle"></i></div>
  <h2>Dossier introuvable</h2>
  <p>Le lien de suivi utilisé n'est pas valide ou a expiré. Vérifiez le lien reçu par email ou contactez la direction de <?= h($emsNom) ?>.</p>
  <?php if ($emsEmail): ?><p style="margin-top:16px"><a href="mailto:<?= h($emsEmail) ?>"><?= h($emsEmail) ?></a><?= $emsTel ? ' · ' . h($emsTel) : '' ?></p><?php endif; ?>
</div>

<?php else:
  $st = $statutLabels[$candidat['statut']] ?? [$candidat['statut'], 'neutral', 'bi-circle'];
?>

<div class="ad-card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:18px">
    <div>
      <div style="color:var(--cl-text-muted);font-size:.85rem;margin-bottom:4px">Demande soumise le <?= h(date('d/m/Y à H:i', strtotime($candidat['created_at']))) ?></div>
      <h2 style="border:none;padding:0;margin:0;font-size:1.3rem">
        <?= h($candidat['nom_prenom']) ?>
        <span class="badge-type <?= h($candidat['type_demande']) ?>" style="margin-left:8px;font-size:.75rem"><?= $candidat['type_demande'] === 'urgente' ? 'Urgente' : 'Préventive' ?></span>
      </h2>
    </div>
    <div class="status-pill <?= h($st[1]) ?>">
      <i class="bi <?= h($st[2]) ?>"></i> <?= h($st[0]) ?>
    </div>
  </div>

  <div class="section-heading">Personne concernée</div>
  <dl class="kv">
    <dt>Nom et prénom</dt><dd><?= h($candidat['nom_prenom']) ?></dd>
    <?php if ($candidat['date_naissance']): ?><dt>Date de naissance</dt><dd><?= h(date('d/m/Y', strtotime($candidat['date_naissance']))) ?></dd><?php endif; ?>
    <?php if ($candidat['adresse_postale']): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($candidat['adresse_postale']) ?></dd><?php endif; ?>
    <?php if ($candidat['email']): ?><dt>Email</dt><dd><?= h($candidat['email']) ?></dd><?php endif; ?>
    <?php if ($candidat['telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['telephone']) ?></dd><?php endif; ?>
    <dt>Situation actuelle</dt>
    <dd>
      <?= h($situationLabels[$candidat['situation']] ?? $candidat['situation']) ?>
      <?php if ($candidat['situation'] === 'autre' && $candidat['situation_autre']): ?> — <?= h($candidat['situation_autre']) ?><?php endif; ?>
    </dd>
  </dl>

  <div class="section-heading">Personne de référence</div>
  <dl class="kv">
    <dt>Nom et prénom</dt><dd><?= h($candidat['ref_nom_prenom']) ?></dd>
    <dt>Aspects pris en charge</dt>
    <dd>
      <?php
        $asp = [];
        if ($candidat['ref_aspect_administratifs']) $asp[] = 'Administratifs';
        if ($candidat['ref_aspect_soins']) $asp[] = 'Soins';
        if ($candidat['ref_curateur']) $asp[] = 'Curateur';
        echo $asp ? h(implode(' · ', $asp)) : '<span style="color:var(--cl-text-muted)">—</span>';
      ?>
    </dd>
    <?php if ($candidat['ref_lien_parente']): ?><dt>Lien de parenté</dt><dd><?= h($candidat['ref_lien_parente']) ?></dd><?php endif; ?>
    <?php if ($candidat['ref_autre']): ?><dt>Autre</dt><dd><?= h($candidat['ref_autre']) ?></dd><?php endif; ?>
    <?php if ($candidat['ref_adresse_postale']): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($candidat['ref_adresse_postale']) ?></dd><?php endif; ?>
    <dt>Email</dt><dd><?= h($candidat['ref_email']) ?></dd>
    <?php if ($candidat['ref_telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['ref_telephone']) ?></dd><?php endif; ?>
  </dl>

  <?php if ($candidat['med_nom'] || $candidat['med_email'] || $candidat['med_telephone']): ?>
  <div class="section-heading">Médecin traitant</div>
  <dl class="kv">
    <?php if ($candidat['med_nom']): ?><dt>Nom</dt><dd><?= h($candidat['med_nom']) ?></dd><?php endif; ?>
    <?php if ($candidat['med_adresse_postale']): ?><dt>Adresse postale</dt><dd style="white-space:pre-line"><?= h($candidat['med_adresse_postale']) ?></dd><?php endif; ?>
    <?php if ($candidat['med_email']): ?><dt>Email</dt><dd><?= h($candidat['med_email']) ?></dd><?php endif; ?>
    <?php if ($candidat['med_telephone']): ?><dt>Téléphone</dt><dd><?= h($candidat['med_telephone']) ?></dd><?php endif; ?>
  </dl>
  <?php endif; ?>
</div>

<?php if ($historique): ?>
<div class="ad-card">
  <h2><i class="bi bi-clock-history"></i> Historique</h2>
  <?php foreach ($historique as $h): ?>
    <div class="hist-item">
      <div class="hist-date"><?= h(date('d/m/Y à H:i', strtotime($h['created_at']))) ?></div>
      <div class="hist-action"><?= h(ucfirst(str_replace('_', ' ', $h['action']))) ?></div>
      <?php if ($h['commentaire']): ?><div style="color:var(--cl-text-secondary);margin-top:2px"><?= h($h['commentaire']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="text-align:center;color:var(--cl-text-muted);font-size:.85rem;margin-top:30px">
  Pour toute question, contactez <?= h($emsNom) ?><?= $emsEmail ? ' · <a href="mailto:' . h($emsEmail) . '">' . h($emsEmail) . '</a>' : '' ?><?= $emsTel ? ' · ' . h($emsTel) : '' ?>
</div>

<?php endif; ?>

</div>
</body>
</html>
