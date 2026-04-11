<?php
require_once __DIR__ . '/../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offres d'emploi — <?= h($emsNom) ?></title>
<meta name="description" content="Offres d'emploi de l'EMS La Terrassière SA. Rejoignez notre équipe de soins à Genève.">
<meta name="robots" content="index, follow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
/* ── Variables ── */
:root {
  --rec-green: #2E7D32;
  --rec-green-hover: #1B5E20;
  --rec-green-light: #4CAF50;
  --rec-green-pale: #81C784;
  --rec-green-bg: rgba(46,125,50,0.06);
  --rec-green-bg-mid: rgba(46,125,50,0.12);
  --rec-gold: #F9D835;
  --rec-gold-dark: #E6C220;
  --rec-bg: #FAFDF7;
  --rec-bg-alt: #F3F8EF;
  --rec-surface: #FFFFFF;
  --rec-border: #D8E8D0;
  --rec-border-hover: #B5D4A8;
  --rec-text: #1A2E1A;
  --rec-text-secondary: #4A6548;
  --rec-text-muted: #7E9A7A;
  --rec-radius: 16px;
  --rec-radius-sm: 12px;
  --rec-radius-xs: 8px;
  --rec-shadow: 0 1px 3px rgba(46,125,50,0.04), 0 1px 2px rgba(0,0,0,0.02);
  --rec-shadow-md: 0 4px 12px rgba(46,125,50,0.08), 0 2px 4px rgba(0,0,0,0.04);
  --rec-blue: #1565C0;
  --rec-orange: #E65100;
  --rec-purple: #6A1B9A;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--rec-bg);
  color: var(--rec-text);
  line-height: 1.6;
  min-height: 100vh;
}

/* ── Navbar ── */
.rec-nav {
  position: sticky; top: 0; z-index: 1000;
  background: rgba(250,253,247,0.92); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--rec-border); padding: 0;
}
.rec-nav-inner {
  max-width: 1200px; margin: 0 auto; padding: 10px 20px;
  display: flex; align-items: center; justify-content: space-between;
}
.rec-nav-logo { height: 44px; width: auto; }
.rec-nav-links { display: flex; align-items: center; gap: 4px; list-style: none; margin: 0; padding: 0; }
.rec-nav-links a {
  display: block; padding: 7px 14px; font-size: .85rem; font-weight: 500;
  color: var(--rec-text-secondary); border-radius: var(--rec-radius-xs);
  text-decoration: none; transition: all .2s;
}
.rec-nav-links a:hover { color: var(--rec-green); background: var(--rec-green-bg); }
.rec-nav-links .rec-nav-btn {
  background: var(--rec-green) !important; color: #fff !important;
  border-radius: var(--rec-radius-xs) !important; font-weight: 600 !important;
}
.rec-nav-links .rec-nav-btn:hover { background: var(--rec-green-hover) !important; }
.rec-nav-toggle {
  display: none; background: none; border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius-xs); padding: 6px 10px; font-size: 1.3rem;
  color: var(--rec-text); cursor: pointer;
}
@media (max-width: 768px) {
  .rec-nav-toggle { display: block; }
  .rec-nav-links {
    display: none; flex-direction: column; position: absolute; top: 100%; left: 0; right: 0;
    background: var(--rec-surface); border-bottom: 1px solid var(--rec-border);
    box-shadow: var(--rec-shadow-md); padding: 12px 20px; gap: 0;
  }
  .rec-nav-links.open { display: flex; }
  .rec-nav-links a { padding: 10px 16px; }
}

/* ── Hero ── */
.rec-hero-section {
  background-color: #D8E8CC;
  background-image:
    linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%),
    url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg');
  background-repeat: no-repeat, repeat;
  background-size: auto, 120px 120px;
  padding: 120px 20px 60px;
  text-align: center;
  border-bottom: 1px solid var(--rec-border);
}
.rec-hero-section h1 {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 600;
  color: var(--rec-text);
  margin: 0 0 12px;
}
.rec-hero-section h1 i { color: #E6C220; margin-right: 10px; }
.rec-hero-section p {
  font-size: 1.05rem;
  color: var(--rec-text-secondary);
  max-width: 640px;
  margin: 0 auto;
}
/* Breadcrumb */
.rec-breadcrumb-wrap { border-bottom: 1px solid #E5EAE078; }
.rec-breadcrumb {
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .87rem;
  color: var(--rec-text-muted);
  list-style: none;
}
.rec-breadcrumb a {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--rec-text-secondary); text-decoration: none;
  transition: color .2s; font-weight: 500;
}
.rec-breadcrumb a:hover { color: var(--rec-green); }
.rec-breadcrumb-sep { color: #C8D4C2; }
.rec-breadcrumb-current {
  color: var(--rec-text); font-weight: 600;
  display: inline-flex; align-items: center; gap: 6px;
}
.rec-breadcrumb-current i { color: #E6C220; }

/* ── Shell ── */
.rec-shell { min-height: 100vh; padding: 50px 16px 60px; }
.rec-container { max-width: 960px; margin: 0 auto; }

/* ── Back link ── */
.rec-back {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--rec-green); text-decoration: none; font-size: 0.9rem; font-weight: 500;
  margin-bottom: 24px; transition: color 0.2s;
}
.rec-back:hover { color: var(--rec-green-hover); }

/* ── Header ── */
.rec-header { text-align: center; margin-bottom: 32px; }
.rec-header h1 {
  font-size: 2rem; font-weight: 700; color: var(--rec-text);
  margin-bottom: 8px;
}
.rec-header p { color: var(--rec-text-secondary); font-size: 1.05rem; max-width: 600px; margin: 0 auto 24px; }

/* ── Tabs ── */
.rec-tabs { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
.rec-tab {
  padding: 10px 24px; border-radius: 100px; border: 2px solid var(--rec-border);
  background: var(--rec-surface); color: var(--rec-text-secondary);
  font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.rec-tab:hover { border-color: var(--rec-green-pale); color: var(--rec-green); }
.rec-tab.active {
  background: var(--rec-green); color: #fff; border-color: var(--rec-green);
}

/* ── Loading ── */
.rec-loading {
  text-align: center; padding: 60px 20px; color: var(--rec-text-muted);
}
.rec-loading i { font-size: 2rem; display: block; margin-bottom: 12px; animation: recSpin 1s linear infinite; }
@keyframes recSpin { to { transform: rotate(360deg); } }

/* ── Empty ── */
.rec-empty {
  text-align: center; padding: 60px 20px; color: var(--rec-text-muted);
}
.rec-empty i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: 0.5; }

/* ── Offre Card ── */
.rec-offre-card {
  background: var(--rec-surface); border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius); padding: 24px; margin-bottom: 16px;
  box-shadow: var(--rec-shadow); transition: all 0.2s;
}
.rec-offre-card:hover { border-color: var(--rec-border-hover); box-shadow: var(--rec-shadow-md); }

.rec-offre-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
.rec-offre-title { font-size: 1.25rem; font-weight: 600; color: var(--rec-text); margin: 0; }

.rec-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.rec-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 12px; border-radius: 100px; font-size: 0.78rem; font-weight: 500;
}
.rec-badge-dept { background: var(--rec-green-bg-mid); color: var(--rec-green); }
.rec-badge-contrat { background: rgba(21,101,192,0.1); color: var(--rec-blue); }
.rec-badge-taux { background: rgba(106,27,154,0.1); color: var(--rec-purple); }

.rec-offre-desc {
  color: var(--rec-text-secondary); font-size: 0.92rem; margin-bottom: 16px;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.rec-offre-desc.rec-expanded { -webkit-line-clamp: unset; overflow: visible; }

.rec-offre-meta {
  display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
  margin-bottom: 16px; font-size: 0.85rem; color: var(--rec-text-muted);
}
.rec-offre-meta i { margin-right: 4px; }

.rec-offre-section { margin-bottom: 12px; }
.rec-offre-section-title {
  font-size: 0.82rem; font-weight: 600; color: var(--rec-text-secondary);
  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
}
.rec-offre-section-content { font-size: 0.9rem; color: var(--rec-text-secondary); white-space: pre-line; }

.rec-offre-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 16px; border-top: 1px solid var(--rec-border); }

.rec-offre-deadline { font-size: 0.85rem; color: var(--rec-text-muted); }
.rec-offre-deadline i { color: var(--rec-orange); }

/* ── Buttons ── */
.rec-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 10px 24px;
  border-radius: var(--rec-radius-xs); border: none; font-size: 0.9rem; font-weight: 500;
  cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.rec-btn-primary { background: var(--rec-green); color: #fff; }
.rec-btn-primary:hover { background: var(--rec-green-hover); }
.rec-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.rec-btn-outline {
  background: transparent; color: var(--rec-green); border: 2px solid var(--rec-green);
}
.rec-btn-outline:hover { background: var(--rec-green-bg); }
.rec-btn-sm { padding: 6px 14px; font-size: 0.82rem; }
.rec-btn-lg { padding: 14px 32px; font-size: 1rem; }

/* ── Form ── */
.rec-form-section {
  background: var(--rec-surface); border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius); padding: 24px; margin-bottom: 20px;
}
.rec-form-section-title {
  font-size: 1rem; font-weight: 600; color: var(--rec-green); margin-bottom: 16px;
  display: flex; align-items: center; gap: 8px;
}
.rec-form-section-title i { font-size: 1.1rem; }

.rec-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.rec-form-row.rec-full { grid-template-columns: 1fr; }
@media (max-width: 600px) { .rec-form-row { grid-template-columns: 1fr; } }

.rec-form-group { display: flex; flex-direction: column; gap: 4px; }
.rec-form-group label {
  font-size: 0.85rem; font-weight: 500; color: var(--rec-text-secondary);
}
.rec-form-group label .rec-req { color: #d32f2f; }

.rec-input, .rec-textarea, .rec-select {
  padding: 10px 14px; border: 1.5px solid var(--rec-border); border-radius: var(--rec-radius-xs);
  font-size: 0.92rem; font-family: inherit; color: var(--rec-text);
  background: var(--rec-surface); transition: border-color 0.2s;
}
.rec-input:focus, .rec-textarea:focus, .rec-select:focus {
  outline: none; border-color: var(--rec-green); box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
}
.rec-textarea { resize: vertical; min-height: 100px; }

/* ── File upload ── */
.rec-file-zone {
  border: 2px dashed var(--rec-border); border-radius: var(--rec-radius-xs);
  padding: 16px; text-align: center; cursor: pointer; transition: all 0.2s;
  position: relative; overflow: hidden;
}
.rec-file-zone:hover { border-color: var(--rec-green-pale); background: var(--rec-green-bg); }
.rec-file-zone.rec-file-has { border-color: var(--rec-green); background: var(--rec-green-bg); }
.rec-file-zone input[type="file"] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.rec-file-zone-label { font-size: 0.85rem; color: var(--rec-text-muted); pointer-events: none; }
.rec-file-zone-label i { font-size: 1.3rem; display: block; margin-bottom: 4px; color: var(--rec-green-pale); }
.rec-file-zone-name { font-size: 0.82rem; color: var(--rec-green); font-weight: 500; margin-top: 4px; pointer-events: none; }

/* ── Back to offers link ── */
.rec-form-back {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--rec-text-muted); text-decoration: none; font-size: 0.88rem;
  margin-bottom: 20px; cursor: pointer; transition: color 0.2s;
}
.rec-form-back:hover { color: var(--rec-green); }

/* ── Success ── */
.rec-success {
  text-align: center; padding: 48px 24px;
  background: var(--rec-surface); border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius);
}
.rec-success i { font-size: 3rem; color: var(--rec-green); display: block; margin-bottom: 16px; }
.rec-success h3 { font-size: 1.3rem; font-weight: 600; margin-bottom: 12px; }
.rec-success p { color: var(--rec-text-secondary); margin-bottom: 8px; }
.rec-code-suivi {
  display: inline-block; background: var(--rec-green-bg-mid); color: var(--rec-green);
  font-family: monospace; font-size: 1.4rem; font-weight: 700; padding: 12px 24px;
  border-radius: var(--rec-radius-xs); margin: 16px 0; letter-spacing: 2px;
}

/* ── Tracking ── */
/* ── Timeline ── */
.rec-timeline { position: relative; padding: 0; margin: 24px 0 0; }
.rec-tl-item {
    display: flex; gap: 16px; position: relative; padding-bottom: 24px;
}
.rec-tl-item:last-child { padding-bottom: 0; }
.rec-tl-line {
    width: 32px; display: flex; flex-direction: column; align-items: center; flex-shrink: 0;
}
.rec-tl-dot {
    width: 14px; height: 14px; border-radius: 50%; border: 3px solid var(--rec-border);
    background: var(--rec-surface); flex-shrink: 0; z-index: 1; transition: all .3s;
}
.rec-tl-dot.active { border-color: var(--rec-green); background: var(--rec-green); box-shadow: 0 0 0 4px rgba(46,125,50,.15); }
.rec-tl-dot.done { border-color: var(--rec-green); background: var(--rec-green); }
.rec-tl-dot.refused { border-color: #D32F2F; background: #D32F2F; }
.rec-tl-dot.pending { border-color: var(--rec-border); background: var(--rec-bg-alt); }
.rec-tl-bar { flex: 1; width: 2px; background: var(--rec-border); margin: 4px 0; }
.rec-tl-bar.done { background: var(--rec-green); }
.rec-tl-content { flex: 1; min-width: 0; }
.rec-tl-title { font-size: .92rem; font-weight: 600; color: var(--rec-text); margin-bottom: 2px; }
.rec-tl-desc { font-size: .82rem; color: var(--rec-text-muted); }
.rec-tl-date { font-size: .72rem; color: var(--rec-text-muted); margin-top: 2px; }

.rec-tl-card {
    background: var(--rec-surface); border: 1.5px solid var(--rec-border); border-radius: 14px;
    padding: 20px; margin-bottom: 20px;
}
.rec-tl-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 14px;
    border-bottom: 1px solid var(--rec-border);
}
.rec-tl-header-icon {
    width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center;
    justify-content: center; font-size: 1.2rem; flex-shrink: 0;
}
.rec-tl-header-info { flex: 1; }
.rec-tl-header-info h3 { font-size: 1rem; font-weight: 700; margin: 0 0 2px; color: var(--rec-text); }
.rec-tl-header-info p { font-size: .82rem; color: var(--rec-text-muted); margin: 0; }
.rec-tl-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.rec-tl-tag {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px;
    border-radius: 20px; font-size: .78rem; font-weight: 500;
    background: var(--rec-bg-alt); color: var(--rec-text-secondary); border: 1px solid var(--rec-border);
}

.rec-track-form {
  background: var(--rec-surface); border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius); padding: 32px; max-width: 500px; margin: 0 auto;
}
.rec-track-form h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; text-align: center; }

.rec-track-result {
  background: var(--rec-surface); border: 1px solid var(--rec-border);
  border-radius: var(--rec-radius); padding: 24px; margin-top: 20px; max-width: 500px; margin-left: auto; margin-right: auto;
}
.rec-track-result h4 { font-size: 1rem; font-weight: 600; margin-bottom: 12px; }
.rec-track-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--rec-border); }
.rec-track-row:last-child { border-bottom: none; }
.rec-track-label { font-size: 0.85rem; color: var(--rec-text-muted); }
.rec-track-value { font-size: 0.92rem; font-weight: 500; }

.rec-status-badge {
  display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px;
  border-radius: 100px; font-size: 0.8rem; font-weight: 600;
}
.rec-status-recue { background: rgba(21,101,192,0.1); color: #1565C0; }
.rec-status-en_cours { background: rgba(249,216,53,0.2); color: #F57F17; }
.rec-status-entretien { background: rgba(106,27,154,0.1); color: #6A1B9A; }
.rec-status-acceptee { background: rgba(46,125,50,0.12); color: #2E7D32; }
.rec-status-refusee { background: rgba(211,47,47,0.1); color: #D32F2F; }
.rec-status-archivee { background: rgba(0,0,0,0.06); color: #757575; }

/* ── Error ── */
.rec-error { color: #d32f2f; font-size: 0.85rem; margin-top: 8px; text-align: center; }

/* ── Responsive ── */
@media (max-width: 600px) {
  .rec-header h1 { font-size: 1.5rem; }
  .rec-offre-header { flex-direction: column; }
  .rec-offre-footer { flex-direction: column; align-items: stretch; }
  .rec-offre-footer .rec-btn { text-align: center; justify-content: center; }
  .rec-form-section { padding: 16px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══ HERO ═══ -->
<section class="rec-hero-section">
  <h1><i class="bi bi-briefcase-fill"></i> Emploi &amp; formation</h1>
  <p>Rejoignez une équipe passionnée au service du bien-être des personnes âgées. Soins, animation, formation — trouvez votre place à La Terrassière.</p>
</section>

<!-- ═══ BREADCRUMB ═══ -->
<nav class="rec-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="rec-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="rec-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="rec-breadcrumb-current" aria-current="page"><i class="bi bi-briefcase-fill"></i> Emploi</li>
  </ol>
</nav>

<div class="rec-shell">
<div class="rec-container">

  <!-- ═══ TABS ═══ -->
  <div class="rec-header">
    <div class="rec-tabs">
      <button class="rec-tab active" data-view="offres"><i class="bi bi-briefcase"></i> Offres</button>
      <button class="rec-tab" data-view="suivi"><i class="bi bi-search"></i> Suivi candidature</button>
    </div>
  </div>

  <!-- ═══ VIEW 1: OFFRES LIST ═══ -->
  <div id="recOffresView">
    <div id="recOffresList">
      <div class="rec-loading"><i class="bi bi-arrow-repeat"></i> Chargement des offres...</div>
    </div>
  </div>

  <!-- ═══ VIEW 2: APPLICATION FORM ═══ -->
  <div id="recFormView" style="display:none">
    <a class="rec-form-back" id="recBackToList"><i class="bi bi-arrow-left"></i> Retour aux offres</a>
    <div style="margin-bottom:20px">
      <h2 style="font-size:1.3rem;font-weight:600" id="recFormTitle">Postuler</h2>
      <p style="color:var(--rec-text-muted);font-size:0.9rem" id="recFormSubtitle"></p>
    </div>

    <form id="recCandidatureForm" enctype="multipart/form-data">
      <input type="hidden" id="recOffreId" name="offre_id">

      <!-- Section: Informations personnelles -->
      <div class="rec-form-section">
        <div class="rec-form-section-title"><i class="bi bi-person"></i> Informations personnelles</div>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>Nom <span class="rec-req">*</span></label>
            <input type="text" class="rec-input" name="nom" required>
          </div>
          <div class="rec-form-group">
            <label>Prenom <span class="rec-req">*</span></label>
            <input type="text" class="rec-input" name="prenom" required>
          </div>
        </div>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>Email <span class="rec-req">*</span></label>
            <input type="email" class="rec-input" name="email" required>
          </div>
          <div class="rec-form-group">
            <label>Telephone</label>
            <input type="tel" class="rec-input" name="telephone">
          </div>
        </div>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>Date de naissance</label>
            <input type="date" class="rec-input" name="date_naissance">
          </div>
          <div class="rec-form-group">
            <label>Nationalite</label>
            <input type="text" class="rec-input" name="nationalite">
          </div>
        </div>
        <div class="rec-form-row rec-full">
          <div class="rec-form-group">
            <label>Adresse</label>
            <input type="text" class="rec-input" name="adresse">
          </div>
        </div>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>Permis de travail</label>
            <select class="rec-select" name="permis_travail">
              <option value="">-- Selectionnez --</option>
              <option value="Suisse">Citoyen(ne) Suisse</option>
              <option value="Permis B">Permis B (autorisation de sejour)</option>
              <option value="Permis C">Permis C (etablissement)</option>
              <option value="Permis G">Permis G (frontalier)</option>
              <option value="Permis L">Permis L (courte duree)</option>
              <option value="Autre">Autre</option>
            </select>
          </div>
          <div class="rec-form-group">
            <label>Disponibilité</label>
            <select class="rec-input" name="disponibilite">
              <option value="">— Choisir —</option>
              <option value="Immédiate">Immédiate</option>
              <option value="Dans 1 mois">Dans 1 mois</option>
              <option value="Dans 2 mois">Dans 2 mois</option>
              <option value="Dans 3 mois">Dans 3 mois</option>
              <option value="Dans 4 mois">Dans 4 mois</option>
              <option value="Dans 5 mois">Dans 5 mois</option>
              <option value="Dans 6 mois">Dans 6 mois</option>
              <option value="À convenir">À convenir</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section: Motivation & Experience -->
      <div class="rec-form-section">
        <div class="rec-form-section-title"><i class="bi bi-chat-left-text"></i> Motivation et experience</div>
        <div class="rec-form-row rec-full">
          <div class="rec-form-group">
            <label>Lettre de motivation</label>
            <textarea class="rec-textarea" name="motivation" rows="5" placeholder="Decrivez votre motivation pour ce poste..."></textarea>
          </div>
        </div>
        <div class="rec-form-row rec-full">
          <div class="rec-form-group">
            <label>Experience professionnelle</label>
            <textarea class="rec-textarea" name="experience" rows="5" placeholder="Resumez votre parcours professionnel..."></textarea>
          </div>
        </div>
      </div>

      <!-- Section: Documents -->
      <div class="rec-form-section">
        <div class="rec-form-section-title"><i class="bi bi-paperclip"></i> Documents</div>
        <p style="font-size:0.82rem;color:var(--rec-text-muted);margin-bottom:16px">Formats acceptes : PDF, DOC, DOCX, JPG, PNG. Taille max : 10 Mo par fichier.</p>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>CV <span class="rec-req">*</span></label>
            <div class="rec-file-zone" data-field="cv">
              <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
              <div class="rec-file-zone-label"><i class="bi bi-cloud-arrow-up"></i> Glissez ou cliquez</div>
              <div class="rec-file-zone-name"></div>
            </div>
          </div>
          <div class="rec-form-group">
            <label>Lettre de motivation</label>
            <div class="rec-file-zone" data-field="lettre_motivation">
              <input type="file" name="lettre_motivation" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <div class="rec-file-zone-label"><i class="bi bi-cloud-arrow-up"></i> Glissez ou cliquez</div>
              <div class="rec-file-zone-name"></div>
            </div>
          </div>
        </div>
        <div class="rec-form-row">
          <div class="rec-form-group">
            <label>Diplomes</label>
            <div class="rec-file-zone" data-field="diplome">
              <input type="file" name="diplome" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <div class="rec-file-zone-label"><i class="bi bi-cloud-arrow-up"></i> Glissez ou cliquez</div>
              <div class="rec-file-zone-name"></div>
            </div>
          </div>
          <div class="rec-form-group">
            <label>Certificats</label>
            <div class="rec-file-zone" data-field="certificat">
              <input type="file" name="certificat" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <div class="rec-file-zone-label"><i class="bi bi-cloud-arrow-up"></i> Glissez ou cliquez</div>
              <div class="rec-file-zone-name"></div>
            </div>
          </div>
        </div>
        <div class="rec-form-row rec-full">
          <div class="rec-form-group">
            <label>Autre document</label>
            <div class="rec-file-zone" data-field="autre">
              <input type="file" name="autre" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <div class="rec-file-zone-label"><i class="bi bi-cloud-arrow-up"></i> Glissez ou cliquez</div>
              <div class="rec-file-zone-name"></div>
            </div>
          </div>
        </div>
      </div>

      <div style="text-align:center">
        <button type="submit" class="rec-btn rec-btn-primary rec-btn-lg" id="recSubmitBtn">
          <i class="bi bi-send"></i> Envoyer ma candidature
        </button>
      </div>
      <div class="rec-error" id="recFormError"></div>
    </form>
  </div>

  <!-- ═══ VIEW 3: SUCCESS ═══ -->
  <div id="recSuccessView" style="display:none">
    <div class="rec-success">
      <i class="bi bi-check-circle-fill"></i>
      <h3>Candidature envoyee avec succes !</h3>
      <p>Merci pour votre candidature. Nous l'examinerons avec attention.</p>
      <p>Votre code de suivi :</p>
      <div class="rec-code-suivi" id="recCodeSuivi"></div>
      <p style="font-size:0.85rem;color:var(--rec-text-muted)">Conservez ce code pour suivre l'etat de votre candidature.</p>
      <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <button class="rec-btn rec-btn-outline" id="recBackAfterSuccess"><i class="bi bi-arrow-left"></i> Voir les offres</button>
        <button class="rec-btn rec-btn-primary" id="recGoTrack"><i class="bi bi-search"></i> Suivre ma candidature</button>
      </div>
    </div>
  </div>

  <!-- ═══ VIEW 4: TRACKING ═══ -->
  <div id="recTrackView" style="display:none">
    <div class="rec-track-form">
      <h3><i class="bi bi-search" style="color:var(--rec-green)"></i> Suivi de candidature</h3>
      <div class="rec-form-group" style="margin-bottom:12px">
        <label>Votre email</label>
        <input type="email" class="rec-input" id="recTrackEmail" placeholder="votre.email@exemple.com">
      </div>
      <div class="rec-form-group" style="margin-bottom:16px">
        <label>Code de suivi</label>
        <input type="text" class="rec-input" id="recTrackCode" placeholder="Ex: A1B2C3D4" style="text-transform:uppercase">
      </div>
      <button class="rec-btn rec-btn-primary" style="width:100%" id="recTrackBtn">
        <i class="bi bi-search"></i> Verifier
      </button>
      <div class="rec-error" id="recTrackError"></div>
    </div>
    <div id="recTrackResult"></div>
  </div>

</div>
</div>

<script>
(function() {
  const API_URL = '/spocspace/website/api.php';

  // ── Helpers ──
  function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function formatDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('fr-CH', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  async function apiPost(action, data) {
    const res = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...data })
    });
    return res.json();
  }

  // ── Tab switching ──
  const tabs = document.querySelectorAll('.rec-tab');
  const views = {
    offres: document.getElementById('recOffresView'),
    suivi: document.getElementById('recTrackView')
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const view = tab.dataset.view;
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      // Hide all views
      document.getElementById('recOffresView').style.display = 'none';
      document.getElementById('recFormView').style.display = 'none';
      document.getElementById('recSuccessView').style.display = 'none';
      document.getElementById('recTrackView').style.display = 'none';

      if (view === 'offres') {
        document.getElementById('recOffresView').style.display = '';
      } else if (view === 'suivi') {
        document.getElementById('recTrackView').style.display = '';
      }
    });
  });

  // ── Load offres ──
  let offresData = [];

  async function loadOffres() {
    const list = document.getElementById('recOffresList');
    try {
      const res = await apiPost('get_offres');
      if (!res.success) throw new Error(res.message);
      offresData = res.offres || [];

      if (offresData.length === 0) {
        list.innerHTML = '<div class="rec-empty"><i class="bi bi-inbox"></i>Aucune offre d\'emploi disponible actuellement.<br>N\'hesitez pas a envoyer une candidature spontanee via notre page de contact.</div>';
        return;
      }

      list.innerHTML = offresData.map(o => renderOffreCard(o)).join('');

      // Bind postuler buttons
      list.querySelectorAll('[data-postuler]').forEach(btn => {
        btn.addEventListener('click', () => showForm(btn.dataset.postuler));
      });

      // Bind "voir plus" toggles
      list.querySelectorAll('[data-toggle-desc]').forEach(btn => {
        btn.addEventListener('click', () => {
          const desc = document.getElementById('desc-' + btn.dataset.toggleDesc);
          if (desc) {
            desc.classList.toggle('rec-expanded');
            btn.textContent = desc.classList.contains('rec-expanded') ? 'Voir moins' : 'Voir plus';
          }
        });
      });
    } catch (e) {
      list.innerHTML = '<div class="rec-empty"><i class="bi bi-exclamation-triangle"></i>Erreur lors du chargement des offres.</div>';
    }
  }

  function renderOffreCard(o) {
    let badges = '';
    if (o.departement) badges += '<span class="rec-badge rec-badge-dept"><i class="bi bi-building"></i> ' + escHtml(o.departement) + '</span>';
    if (o.type_contrat) badges += '<span class="rec-badge rec-badge-contrat">' + escHtml(o.type_contrat) + '</span>';
    if (o.taux_activite) badges += '<span class="rec-badge rec-badge-taux">' + escHtml(o.taux_activite) + '</span>';

    let meta = '';
    if (o.lieu) meta += '<span><i class="bi bi-geo-alt"></i> ' + escHtml(o.lieu) + '</span>';
    if (o.date_debut) meta += '<span><i class="bi bi-calendar"></i> Debut : ' + formatDate(o.date_debut) + '</span>';
    if (o.salaire_indication) meta += '<span><i class="bi bi-cash-stack"></i> ' + escHtml(o.salaire_indication) + '</span>';

    let sections = '';
    if (o.exigences) {
      sections += '<div class="rec-offre-section"><div class="rec-offre-section-title">Exigences</div><div class="rec-offre-section-content">' + escHtml(o.exigences) + '</div></div>';
    }
    if (o.avantages) {
      sections += '<div class="rec-offre-section"><div class="rec-offre-section-title">Avantages</div><div class="rec-offre-section-content">' + escHtml(o.avantages) + '</div></div>';
    }

    let deadline = '';
    if (o.date_limite) {
      deadline = '<span class="rec-offre-deadline"><i class="bi bi-clock"></i> Delai : ' + formatDate(o.date_limite) + '</span>';
    }

    const descLong = o.description && o.description.length > 200;

    return '<div class="rec-offre-card">' +
      '<div class="rec-offre-header"><h3 class="rec-offre-title">' + escHtml(o.titre) + '</h3><div class="rec-badges">' + badges + '</div></div>' +
      (o.description ? '<div class="rec-offre-desc" id="desc-' + o.id + '">' + escHtml(o.description) + '</div>' : '') +
      (descLong ? '<a href="javascript:void(0)" data-toggle-desc="' + o.id + '" style="font-size:0.82rem;color:var(--rec-green);cursor:pointer">Voir plus</a>' : '') +
      (meta ? '<div class="rec-offre-meta">' + meta + '</div>' : '') +
      sections +
      '<div class="rec-offre-footer">' + deadline +
        '<button class="rec-btn rec-btn-primary" data-postuler="' + o.id + '"><i class="bi bi-send"></i> Postuler</button>' +
      '</div>' +
    '</div>';
  }

  // ── Show form ──
  function showForm(offreId) {
    const offre = offresData.find(o => o.id === offreId);
    if (!offre) return;

    document.getElementById('recOffreId').value = offreId;
    document.getElementById('recFormTitle').textContent = 'Postuler : ' + offre.titre;
    document.getElementById('recFormSubtitle').textContent = [offre.departement, offre.type_contrat, offre.taux_activite].filter(Boolean).join(' — ');

    document.getElementById('recOffresView').style.display = 'none';
    document.getElementById('recFormView').style.display = '';
    document.getElementById('recFormError').textContent = '';
    document.getElementById('recCandidatureForm').reset();

    // Reset file zones
    document.querySelectorAll('.rec-file-zone').forEach(z => {
      z.classList.remove('rec-file-has');
      z.querySelector('.rec-file-zone-name').textContent = '';
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ── Back to list ──
  document.getElementById('recBackToList').addEventListener('click', () => {
    document.getElementById('recFormView').style.display = 'none';
    document.getElementById('recOffresView').style.display = '';
  });

  document.getElementById('recBackAfterSuccess').addEventListener('click', () => {
    document.getElementById('recSuccessView').style.display = 'none';
    document.getElementById('recOffresView').style.display = '';
    tabs[0].classList.add('active');
    tabs[1].classList.remove('active');
  });

  document.getElementById('recGoTrack').addEventListener('click', () => {
    document.getElementById('recSuccessView').style.display = 'none';
    document.getElementById('recTrackView').style.display = '';
    tabs[0].classList.remove('active');
    tabs[1].classList.add('active');
  });

  // ── File upload display ──
  document.querySelectorAll('.rec-file-zone input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
      const zone = input.closest('.rec-file-zone');
      const nameEl = zone.querySelector('.rec-file-zone-name');
      if (input.files.length > 0) {
        zone.classList.add('rec-file-has');
        nameEl.textContent = input.files[0].name;
      } else {
        zone.classList.remove('rec-file-has');
        nameEl.textContent = '';
      }
    });
  });

  // ── Submit candidature ──
  document.getElementById('recCandidatureForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('recSubmitBtn');
    const errEl = document.getElementById('recFormError');
    errEl.textContent = '';

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:recSpin 1s linear infinite"></i> Envoi en cours...';

    try {
      const formData = new FormData(e.target);
      formData.append('action', 'submit_candidature');

      const res = await fetch(API_URL, { method: 'POST', body: formData });
      const data = await res.json();

      if (!data.success) {
        errEl.textContent = data.message || 'Erreur lors de l\'envoi.';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Envoyer ma candidature';
        return;
      }

      // Show success
      document.getElementById('recFormView').style.display = 'none';
      document.getElementById('recSuccessView').style.display = '';
      document.getElementById('recCodeSuivi').textContent = data.code_suivi;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      errEl.textContent = 'Erreur de connexion. Veuillez reessayer.';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send"></i> Envoyer ma candidature';
  });

  // ── Track candidature ──
  document.getElementById('recTrackBtn').addEventListener('click', async () => {
    const email = document.getElementById('recTrackEmail').value.trim();
    const code = document.getElementById('recTrackCode').value.trim().toUpperCase();
    const errEl = document.getElementById('recTrackError');
    const resultEl = document.getElementById('recTrackResult');
    errEl.textContent = '';
    resultEl.innerHTML = '';

    if (!email || !code) {
      errEl.textContent = 'Veuillez remplir les deux champs.';
      return;
    }

    try {
      const data = await apiPost('track_candidature', { email, code_suivi: code });

      if (!data.success) {
        errEl.textContent = data.message || 'Candidature introuvable.';
        return;
      }

      const c = data.candidature;
      const steps = [
          { key: 'recue',     label: 'Candidature reçue',     icon: 'bi-inbox',           desc: 'Votre dossier a été réceptionné' },
          { key: 'en_cours',  label: 'Examen du dossier',     icon: 'bi-search',          desc: 'Votre candidature est en cours d\'analyse' },
          { key: 'entretien', label: 'Entretien planifié',    icon: 'bi-calendar-event',  desc: 'Vous serez contacté pour un entretien' },
          { key: 'acceptee',  label: 'Candidature acceptée',  icon: 'bi-check-circle',    desc: 'Félicitations ! Votre candidature est retenue' },
      ];
      const statusOrder = { recue: 0, en_cours: 1, entretien: 2, acceptee: 3, refusee: -1, archivee: -2 };
      const currentStep = statusOrder[c.statut] ?? -1;
      const isRefused = c.statut === 'refusee';
      const isArchived = c.statut === 'archivee';

      // Header card
      const iconBg = isRefused ? '#FECACA' : (currentStep >= 3 ? '#D1FAE5' : 'rgba(46,125,50,.1)');
      const iconColor = isRefused ? '#991B1B' : '#2E7D32';
      const headerIcon = isRefused ? 'bi-x-circle' : (currentStep >= 3 ? 'bi-check-circle-fill' : 'bi-person-badge');

      let html = '<div class="rec-tl-card">';
      html += '<div class="rec-tl-header">';
      html += '<div class="rec-tl-header-icon" style="background:' + iconBg + ';color:' + iconColor + '"><i class="bi ' + headerIcon + '"></i></div>';
      html += '<div class="rec-tl-header-info"><h3>' + escHtml(c.prenom) + ' ' + escHtml(c.nom) + '</h3>';
      html += '<p>Code de suivi : <strong>' + escHtml(c.code_suivi) + '</strong></p></div>';
      html += '<span class="rec-status-badge rec-status-' + escHtml(c.statut) + '">' + escHtml(c.statut === 'refusee' ? 'Refusée' : c.statut === 'archivee' ? 'Archivée' : steps[currentStep]?.label || c.statut) + '</span>';
      html += '</div>';

      // Meta tags
      html += '<div class="rec-tl-meta">';
      html += '<span class="rec-tl-tag"><i class="bi bi-briefcase"></i> ' + escHtml(c.offre_titre) + '</span>';
      if (c.offre_departement) html += '<span class="rec-tl-tag"><i class="bi bi-building"></i> ' + escHtml(c.offre_departement) + '</span>';
      if (c.offre_contrat) html += '<span class="rec-tl-tag"><i class="bi bi-file-text"></i> ' + escHtml(c.offre_contrat) + '</span>';
      if (c.offre_taux) html += '<span class="rec-tl-tag"><i class="bi bi-speedometer2"></i> ' + escHtml(c.offre_taux) + '</span>';
      html += '</div>';

      // Timeline
      if (isRefused || isArchived) {
          html += '<div class="rec-timeline">';
          html += '<div class="rec-tl-item"><div class="rec-tl-line"><div class="rec-tl-dot done"></div><div class="rec-tl-bar done"></div></div>';
          html += '<div class="rec-tl-content"><div class="rec-tl-title">Candidature reçue</div><div class="rec-tl-date">' + formatDate(c.date_soumission) + '</div></div></div>';
          html += '<div class="rec-tl-item"><div class="rec-tl-line"><div class="rec-tl-dot refused"></div></div>';
          html += '<div class="rec-tl-content"><div class="rec-tl-title">' + (isRefused ? 'Candidature refusée' : 'Dossier archivé') + '</div>';
          html += '<div class="rec-tl-desc">' + (isRefused ? 'Votre candidature n\'a pas été retenue cette fois.' : 'Ce dossier a été archivé.') + '</div>';
          if (c.updated_at) html += '<div class="rec-tl-date">' + formatDate(c.updated_at) + '</div>';
          html += '</div></div></div>';
      } else {
          html += '<div class="rec-timeline">';
          steps.forEach((step, i) => {
              const isDone = i < currentStep;
              const isActive = i === currentStep;
              const isPending = i > currentStep;
              const dotCls = isDone ? 'done' : (isActive ? 'active' : 'pending');
              const barCls = isDone ? 'done' : '';
              html += '<div class="rec-tl-item">';
              html += '<div class="rec-tl-line"><div class="rec-tl-dot ' + dotCls + '"></div>';
              if (i < steps.length - 1) html += '<div class="rec-tl-bar ' + barCls + '"></div>';
              html += '</div>';
              html += '<div class="rec-tl-content">';
              html += '<div class="rec-tl-title" style="' + (isPending ? 'opacity:.4' : '') + '"><i class="bi ' + step.icon + '" style="margin-right:6px"></i>' + step.label + '</div>';
              html += '<div class="rec-tl-desc" style="' + (isPending ? 'opacity:.3' : '') + '">' + step.desc + '</div>';
              if (i === 0) html += '<div class="rec-tl-date">' + formatDate(c.date_soumission) + '</div>';
              else if (isActive && c.updated_at && c.updated_at !== c.date_soumission) html += '<div class="rec-tl-date">' + formatDate(c.updated_at) + '</div>';
              html += '</div></div>';
          });
          html += '</div>';
      }

      html += '</div>';
      resultEl.innerHTML = html;
    } catch (err) {
      errEl.textContent = 'Erreur de connexion. Veuillez reessayer.';
    }
  });

  // Enter key on tracking fields
  document.getElementById('recTrackCode').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('recTrackBtn').click();
  });
  document.getElementById('recTrackEmail').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('recTrackBtn').click();
  });

  // ── Navbar toggle ──
  document.getElementById('recNavToggle')?.addEventListener('click', () => {
    document.getElementById('recNavLinks').classList.toggle('open');
  });

  // ── Init ──
  loadOffres();
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
