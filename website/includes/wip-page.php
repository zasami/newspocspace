<?php
/**
 * Template réutilisable pour les pages "En construction"
 * Usage : $wipTitle, $wipIcon, $wipSubtitle, $wipDescription, $wipColor définis avant require.
 */
require_once __DIR__ . '/../../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';

$wipTitle       = $wipTitle       ?? 'En construction';
$wipIcon        = $wipIcon        ?? 'bi-tools';
$wipSubtitle    = $wipSubtitle    ?? 'Page en préparation';
$wipDescription = $wipDescription ?? 'Cette section est en cours de préparation. Revenez très bientôt pour découvrir son contenu.';
$wipColor       = $wipColor       ?? '#2E7D32';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($wipTitle) ?> — <?= h($emsNom) ?></title>
<meta name="description" content="<?= h($wipTitle) ?> — <?= h($emsNom) ?>. Page en préparation.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/footer-styles.php'; ?>
<style>
:root { --wip-green: <?= h($wipColor) ?>; }
* { box-sizing: border-box; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: #FAFDF7;
  color: #1A2E1A;
  line-height: 1.6;
  margin: 0;
}

/* ── Hero ── */
.wip-hero {
  background-color: #D8E8CC;
  background-image:
    linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%),
    url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg');
  background-repeat: no-repeat, repeat;
  background-size: auto, 120px 120px;
  padding: 140px 20px 70px;
  text-align: center;
  border-bottom: 1px solid #D8E8D0;
}
.wip-hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 600;
  margin: 0 0 12px;
  color: #1A2E1A;
}
.wip-hero h1 i { color: #E6C220; margin-right: 10px; }
.wip-hero p {
  font-size: 1.05rem;
  color: #4A6548;
  max-width: 640px;
  margin: 0 auto;
}

/* ── Breadcrumb ── */
.wip-breadcrumb-wrap { border-bottom: 1px solid #E5EAE078; }
.wip-breadcrumb {
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .87rem;
  color: #7E9A7A;
  list-style: none;
}
.wip-breadcrumb a {
  display: inline-flex; align-items: center; gap: 6px;
  color: #4A6548; text-decoration: none;
  transition: color .2s; font-weight: 500;
}
.wip-breadcrumb a:hover { color: var(--wip-green); }
.wip-breadcrumb-sep { color: #C8D4C2; }
.wip-breadcrumb-current {
  color: #1A2E1A; font-weight: 600;
  display: inline-flex; align-items: center; gap: 6px;
}
.wip-breadcrumb-current i { color: #E6C220; }

/* ── Main card ── */
.wip-main {
  max-width: 880px;
  margin: 0 auto;
  padding: 80px 24px 120px;
}
.wip-card {
  background: #fff;
  border: 1px solid #D8E8D0;
  border-radius: 24px;
  padding: 64px 48px 56px;
  text-align: center;
  box-shadow: 0 10px 40px rgba(46,125,50,.06), 0 2px 8px rgba(0,0,0,.02);
  position: relative;
  overflow: hidden;
}
.wip-card::before {
  content: '';
  position: absolute;
  top: -80px;
  right: -80px;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(230,194,32,.14) 0%, transparent 60%);
  pointer-events: none;
}
.wip-card::after {
  content: '';
  position: absolute;
  bottom: -100px;
  left: -60px;
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, rgba(46,125,50,.10) 0%, transparent 60%);
  pointer-events: none;
}

.wip-icon-wrap {
  position: relative;
  z-index: 1;
  width: 120px;
  height: 120px;
  margin: 0 auto 26px;
  border-radius: 28px;
  background: linear-gradient(135deg, var(--wip-green) 0%, color-mix(in srgb, var(--wip-green) 65%, #000) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #F9D835;
  font-size: 3.4rem;
  box-shadow: 0 16px 40px rgba(46,125,50,.25), inset 0 0 0 1px rgba(255,255,255,.15);
  animation: wipFloat 4s ease-in-out infinite;
}
@keyframes wipFloat {
  0%, 100% { transform: translateY(0); }
  50%      { transform: translateY(-8px); }
}

.wip-badge {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  background: rgba(230,194,32,.14);
  color: #B58E0E;
  border-radius: 100px;
  font-size: .78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  margin-bottom: 22px;
}
.wip-badge i { animation: wipSpin 3s linear infinite; }
@keyframes wipSpin { to { transform: rotate(360deg); } }

.wip-card h2 {
  position: relative;
  z-index: 1;
  font-family: 'Playfair Display', serif;
  font-size: clamp(1.8rem, 4vw, 2.6rem);
  font-weight: 600;
  color: #1A2E1A;
  margin: 0 0 16px;
  line-height: 1.2;
}
.wip-card h2 em { color: var(--wip-green); font-style: italic; }

.wip-card p {
  position: relative;
  z-index: 1;
  color: #4A6548;
  font-size: 1.02rem;
  max-width: 560px;
  margin: 0 auto 36px;
  line-height: 1.7;
}

.wip-progress {
  position: relative;
  z-index: 1;
  max-width: 420px;
  margin: 0 auto 40px;
}
.wip-progress-bar {
  height: 8px;
  background: #EEF2EA;
  border-radius: 100px;
  overflow: hidden;
}
.wip-progress-fill {
  height: 100%;
  width: 45%;
  background: linear-gradient(90deg, var(--wip-green) 0%, #81C784 100%);
  border-radius: 100px;
  position: relative;
  animation: wipGrow 2s ease-out;
}
@keyframes wipGrow {
  from { width: 0; }
}
.wip-progress-fill::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.35) 50%, transparent 100%);
  animation: wipShine 2.5s ease-in-out infinite;
}
@keyframes wipShine {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
.wip-progress-label {
  font-size: .78rem;
  color: #7E9A7A;
  text-align: center;
  margin-top: 10px;
  font-weight: 500;
}

.wip-actions {
  position: relative;
  z-index: 1;
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}
.wip-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 13px 28px;
  border-radius: 100px;
  font-size: .92rem;
  font-weight: 600;
  text-decoration: none;
  transition: all .25s;
  border: 1.5px solid transparent;
  cursor: pointer;
}
.wip-btn-primary {
  background: var(--wip-green);
  color: #fff;
  box-shadow: 0 6px 20px rgba(46,125,50,.20);
}
.wip-btn-primary:hover {
  background: color-mix(in srgb, var(--wip-green) 85%, #000);
  transform: translateY(-2px);
  box-shadow: 0 10px 26px rgba(46,125,50,.28);
  color: #fff;
}
.wip-btn-outline {
  background: transparent;
  color: var(--wip-green);
  border-color: var(--wip-green);
}
.wip-btn-outline:hover {
  background: var(--wip-green);
  color: #fff;
  transform: translateY(-2px);
}

/* Petits visuels décoratifs dans la carte */
.wip-decor-dots {
  position: absolute;
  top: 30px;
  left: 40px;
  display: flex;
  gap: 8px;
  opacity: .5;
}
.wip-decor-dots span {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.wip-decor-dots span:nth-child(1) { background: #ff6b6b; }
.wip-decor-dots span:nth-child(2) { background: #feca57; }
.wip-decor-dots span:nth-child(3) { background: #1dd1a1; }

@media (max-width: 640px) {
  .wip-main { padding: 40px 16px 80px; }
  .wip-card { padding: 48px 24px 40px; }
  .wip-icon-wrap { width: 96px; height: 96px; font-size: 2.6rem; border-radius: 22px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<!-- Hero -->
<section class="wip-hero">
  <h1><i class="bi <?= h($wipIcon) ?>"></i><?= h($wipTitle) ?></h1>
  <p><?= h($wipSubtitle) ?></p>
</section>

<!-- Breadcrumb -->
<nav class="wip-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="wip-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="wip-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="wip-breadcrumb-current" aria-current="page"><i class="bi <?= h($wipIcon) ?>"></i> <?= h($wipTitle) ?></li>
  </ol>
</nav>

<!-- Main -->
<main class="wip-main">
  <div class="wip-card">
    <div class="wip-decor-dots"><span></span><span></span><span></span></div>
    <div class="wip-icon-wrap"><i class="bi <?= h($wipIcon) ?>"></i></div>
    <div class="wip-badge"><i class="bi bi-gear-fill"></i> En préparation</div>
    <h2>Cette page est <em>bientôt disponible</em></h2>
    <p><?= h($wipDescription) ?></p>

    <div class="wip-progress">
      <div class="wip-progress-bar"><div class="wip-progress-fill"></div></div>
      <div class="wip-progress-label">Travaux en cours — merci de votre patience</div>
    </div>

    <div class="wip-actions">
      <a href="/spocspace/website/" class="wip-btn wip-btn-primary">
        <i class="bi bi-arrow-left"></i> Retour à l'accueil
      </a>
      <a href="/spocspace/website/#contact" class="wip-btn wip-btn-outline">
        <i class="bi bi-envelope"></i> Nous contacter
      </a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
