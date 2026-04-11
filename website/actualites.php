<?php
require_once __DIR__ . '/../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actualités — <?= h($emsNom) ?></title>
<meta name="description" content="Fil d'actualité de l'EMS La Terrassière : animations, photos, vidéos, activités à venir.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
:root {
  --ac-green: #2E7D32;
  --ac-green-hover: #1B5E20;
  --ac-green-pale: #81C784;
  --ac-green-bg: rgba(46,125,50,.06);
  --ac-gold: #F9D835;
  --ac-gold-dark: #E6C220;
  --ac-bg: #FAFDF7;
  --ac-bg-alt: #F3F8EF;
  --ac-surface: #FFFFFF;
  --ac-border: #D8E8D0;
  --ac-text: #1A2E1A;
  --ac-text-secondary: #4A6548;
  --ac-text-muted: #7E9A7A;
  --ac-radius: 16px;
  --ac-shadow: 0 1px 3px rgba(46,125,50,.05), 0 1px 2px rgba(0,0,0,.02);
  --ac-shadow-md: 0 8px 24px rgba(46,125,50,.10);
}

* { box-sizing: border-box; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--ac-bg);
  color: var(--ac-text);
  line-height: 1.6;
  margin: 0;
}

/* ── Hero ── */
.ac-hero {
  background-color: #D8E8CC;
  background-image:
    linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%),
    url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg');
  background-repeat: no-repeat, repeat;
  background-size: auto, 120px 120px;
  padding: 120px 20px 60px;
  text-align: center;
  border-bottom: 1px solid var(--ac-border);
  position: relative;
}
.ac-hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 600;
  color: var(--ac-text);
  margin: 0 0 12px;
}
.ac-hero h1 i { color: var(--ac-gold-dark); }
.ac-hero p {
  font-size: 1.05rem;
  color: var(--ac-text-secondary);
  max-width: 640px;
  margin: 0 auto;
}

/* ── Breadcrumb ── */
.ac-breadcrumb-wrap {
  border-bottom: 1px solid #E5EAE078;
}
.ac-breadcrumb {
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .87rem;
  color: var(--ac-text-muted);
  list-style: none;
}
.ac-breadcrumb a {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--ac-text-secondary); text-decoration: none;
  transition: color .2s; font-weight: 500;
}
.ac-breadcrumb a:hover { color: var(--ac-green); }
.ac-breadcrumb-sep { color: #C8D4C2; }
.ac-breadcrumb-current {
  color: var(--ac-text); font-weight: 600;
  display: inline-flex; align-items: center; gap: 6px;
}
.ac-breadcrumb-current i { color: var(--ac-gold-dark); }

/* ── Layout ── */
.ac-layout {
  max-width: 1200px;
  margin: 0 auto;
  padding: 50px 20px 80px;
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 40px;
}
@media (max-width: 900px) {
  .ac-layout { grid-template-columns: 1fr; }
}

/* ── Feed ── */
.ac-feed-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 16px;
}
.ac-feed-title {
  font-family: 'Playfair Display', serif;
  font-size: 2rem;
  font-weight: 600;
  color: var(--ac-text);
  margin: 0;
}
.ac-feed-title em { color: var(--ac-green); font-style: italic; }

.ac-filters {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.ac-filter-btn {
  padding: 7px 14px;
  background: var(--ac-surface);
  border: 1.5px solid var(--ac-border);
  border-radius: 100px;
  font-size: .8rem;
  font-weight: 500;
  color: var(--ac-text-secondary);
  cursor: pointer;
  transition: all .2s;
}
.ac-filter-btn:hover { border-color: var(--ac-green-pale); color: var(--ac-green); }
.ac-filter-btn.active {
  background: var(--ac-green);
  color: #fff;
  border-color: var(--ac-green);
}

.ac-feed { display: flex; flex-direction: column; gap: 20px; }

/* ═══ CARD MODERNE ═══ */
.ac-card {
  background: var(--ac-surface);
  border: 1px solid var(--ac-border);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: var(--ac-shadow);
  transition: box-shadow .3s ease, transform .3s ease, border-color .3s ease;
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 0;
}
.ac-card:hover {
  box-shadow: 0 16px 40px rgba(46,125,50,.10), 0 4px 12px rgba(0,0,0,.04);
  border-color: var(--ac-green-pale);
  transform: translateY(-2px);
}
.ac-card.ac-pinned {
  border-color: var(--ac-gold-dark);
  background: linear-gradient(135deg, #FFFCEB 0%, #fff 50%);
}

/* Vidéo / galerie = pleine largeur (media en haut) */
.ac-card.ac-card-full {
  grid-template-columns: 1fr;
}

/* Carte sans média (ex: vidéo créée sans fichier) → corps pleine largeur */
.ac-card.ac-card-no-media {
  grid-template-columns: 1fr;
}

/* ═══ Carte TEXTE : style journal / magazine ═══ */
.ac-card.ac-card-texte {
  grid-template-columns: 1fr;
  background:
    linear-gradient(#fff, #fff) padding-box,
    radial-gradient(circle at top left, #f3f8ef 0%, #fff 60%) border-box;
  border: 1px solid var(--ac-border);
  position: relative;
  overflow: hidden;
}
.ac-card.ac-card-texte::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--ac-green) 0%, #9bc18a 50%, transparent 100%);
}
.ac-card.ac-card-texte::after {
  content: '\201C';
  position: absolute;
  top: -40px; right: 20px;
  font-family: 'Playfair Display', serif;
  font-size: 12rem;
  line-height: 1;
  color: var(--ac-green);
  opacity: .05;
  pointer-events: none;
  user-select: none;
}
/* Décoration shape-1 dans le coin bas-droit — recolorée en vert du site via mask */
.ac-card.ac-card-texte .ac-card-body { position: relative; }
.ac-card.ac-card-texte .ac-card-body::after {
  content: '';
  position: absolute;
  right: 0px;
  bottom: 0px;
  width: 180px;
  height: 180px;
  background-color: var(--ac-green);
  -webkit-mask: url('/spocspace/website/assets/img/shape-1.webp') no-repeat bottom right / contain;
          mask: url('/spocspace/website/assets/img/shape-1.webp') no-repeat bottom right / contain;
  opacity: .4;
  pointer-events: none;
  z-index: 0;
}
.ac-card.ac-card-texte .ac-card-body > * { position: relative; z-index: 1; }
@media (max-width: 720px) {
  .ac-card.ac-card-texte .ac-card-body::after { width: 120px; height: 120px; right: -10px; bottom: -10px; opacity: .18; }
}

/* Décoration shape-5 dans le coin bas-droit des cartes AFFICHE — recolorée en vert */
.ac-card:has(.ac-card-media.ac-affiche) .ac-card-body { position: relative; overflow: hidden; }
.ac-card:has(.ac-card-media.ac-affiche) .ac-card-body::after {
  content: '';
  position: absolute;
  right: 0px;
  bottom: 0px;
  width: 240px;
  height: 90px;
  background-color: var(--ac-green);
  -webkit-mask: url('/spocspace/website/assets/img/shape-5.webp') no-repeat bottom right / contain;
          mask: url('/spocspace/website/assets/img/shape-5.webp') no-repeat bottom right / contain;
  opacity: .4;
  pointer-events: none;
  z-index: 0;
}
.ac-card:has(.ac-card-media.ac-affiche) .ac-card-body > * { position: relative; z-index: 1; }
@media (max-width: 720px) {
  .ac-card:has(.ac-card-media.ac-affiche) .ac-card-body::after { width: 180px; height: 70px; right: -15px; bottom: -10px; opacity: .2; }
}
.ac-card.ac-card-texte .ac-card-body {
  padding: 36px 48px 40px;
  max-width: 780px;
  margin: 0 auto;
  width: 100%;
}
.ac-card.ac-card-texte .ac-card-title {
  font-size: 1.4rem;
  line-height: 1.25;
  letter-spacing: -.005em;
  margin: 8px 0 14px;
  text-align: left;
}
.ac-card.ac-card-texte .ac-card-title::after {
  content: '';
  display: block;
  width: 48px;
  height: 3px;
  background: var(--ac-green);
  margin-top: 12px;
  border-radius: 2px;
}
/* Extrait journal : lettrine verte sur 3 lignes + texte justifié + fondu vert vers « Lire la suite » */
.ac-card.ac-card-texte .ac-card-extrait {
  position: relative;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1rem;
  line-height: 1.7;
  color: #3a3a3a;
  max-height: calc(1.7em * 6);
  -webkit-line-clamp: unset;
  display: block;
  overflow: hidden;
  text-align: justify;
  hyphens: auto;
  margin-bottom: 14px;
  padding-bottom: 6px;
}
.ac-card.ac-card-texte .ac-card-extrait::first-letter {
  font-family: 'Playfair Display', serif;
  font-size: 3.2em;
  font-weight: 700;
  float: left;
  line-height: .85;
  padding: 6px 10px 0 0;
  color: var(--ac-green);
}
.ac-card.ac-card-texte .ac-card-extrait::after {
  content: '';
  position: absolute;
  left: 0; right: 0; bottom: 0;
  height: 75px;
  background: linear-gradient(180deg,
    rgba(255,255,255,0) 0%,
    rgba(255,255,255,.6) 50%,
    rgba(255,255,255,.98) 100%);
  pointer-events: none;
}
.ac-card.ac-card-texte .ac-read-more {
  align-self: center;
  margin-top: 8px;
  position: relative;
  z-index: 1;
}
@media (max-width: 720px) {
  .ac-card.ac-card-texte .ac-card-body { padding: 26px 22px 28px; }
  .ac-card.ac-card-texte .ac-card-title { font-size: 1.25rem; }
  .ac-card.ac-card-texte .ac-card-extrait { font-size: .95rem; max-height: calc(1.7em * 5); }
  .ac-card.ac-card-texte .ac-card-extrait::first-letter { font-size: 2.8em; }
  .ac-card.ac-card-texte::after { font-size: 7rem; top: -20px; right: 10px; }
}

/* ── Media (colonne gauche ou pleine largeur) ── */
.ac-card-media {
  position: relative;
  width: 100%;
  min-width: 0;        /* empêche l'image naturelle d'étendre la colonne grid */
  background: #F3F8EF;
  overflow: hidden;
  min-height: 220px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ac-card:not(.ac-card-full) .ac-card-media {
  border-right: 1px solid var(--ac-border);
  align-self: stretch;
}
.ac-card-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  cursor: zoom-in;
  transition: transform .45s ease;
}
.ac-card:hover .ac-card-media img { transform: scale(1.03); }

.ac-card.ac-card-full .ac-card-media {
  max-height: 440px;
  min-height: unset;
}
.ac-card.ac-card-full .ac-card-media img {
  max-height: 440px;
}

.ac-card-media.ac-affiche {
  background: var(--ac-bg-alt);
}

/* Placeholder quand aucune image/vidéo n'est disponible */
.ac-card-media.ac-media-placeholder {
  background: linear-gradient(135deg, #F3F8EF 0%, #E6EFDB 100%);
  cursor: pointer;
  transition: background .3s ease;
}
.ac-card-media.ac-media-placeholder:hover {
  background: linear-gradient(135deg, #EDF5E5 0%, #D8E9C6 100%);
}
.ac-card-media.ac-media-placeholder i {
  font-size: 4.5rem;
  color: var(--ac-green);
  opacity: .35;
  transition: opacity .3s ease, transform .3s ease;
}
.ac-card-media.ac-media-placeholder:hover i {
  opacity: .55;
  transform: scale(1.05);
}
.ac-card-media.ac-affiche img {
  object-fit: contain;
  max-height: 440px;
  width: auto;
  max-width: 100%;
}
.ac-gallery-count {
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(26,46,26,.45);
  color: #fff;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 4px 9px;
  border-radius: 100px;
  font-size: .68rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  z-index: 2;
  pointer-events: none;
}
.ac-gallery-count i { font-size: .78rem; }

/* ── Vidéo ── */
.ac-card-media.ac-has-video {
  background: #000;
  padding: 0;
  min-height: 220px;
  position: relative;
}
.ac-video-wrap {
  position: absolute;
  inset: 0;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.ac-video-wrap img,
.ac-video-wrap video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.ac-video-wrap video { background: #000; }
.ac-video-play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.3) 100%);
  cursor: pointer;
  transition: background .3s;
  z-index: 2;
}
.ac-video-play:hover { background: linear-gradient(180deg, rgba(0,0,0,.1) 0%, rgba(0,0,0,.4) 100%); }
.ac-video-play-btn {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: rgba(255,255,255,.96);
  color: var(--ac-green);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.7rem;
  box-shadow: 0 12px 32px rgba(0,0,0,.4);
  transition: transform .3s;
}
.ac-video-play:hover .ac-video-play-btn { transform: scale(1.1); }

/* ── Corps ── */
.ac-card-body {
  padding: 22px 26px 24px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}
.ac-card-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .72rem;
  color: var(--ac-text-muted);
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: .5px;
  font-weight: 600;
}
.ac-type-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  background: var(--ac-green-bg);
  color: var(--ac-green);
  border-radius: 100px;
  font-size: .7rem;
  font-weight: 600;
}
.ac-type-chip i { font-size: .82rem; }
.ac-meta-date {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--ac-text-muted);
}
.ac-pin-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  background: rgba(230,194,32,.14);
  color: #B58E0E;
  border-radius: 100px;
  font-size: .7rem;
  font-weight: 700;
  margin-left: auto;
}

.ac-card-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.35rem;
  font-weight: 600;
  color: var(--ac-text);
  margin: 0 0 10px;
  line-height: 1.3;
}
.ac-card-extrait {
  color: var(--ac-text-secondary);
  font-size: .92rem;
  margin: 0 0 14px;
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Bouton "Lire la suite" */
.ac-read-more {
  margin-top: auto;
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  background: transparent;
  border: 1.5px solid var(--ac-green);
  border-radius: 100px;
  color: var(--ac-green);
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all .2s;
}
.ac-read-more:hover { background: var(--ac-green); color: #fff; gap: 10px; }

/* ═══ CARROUSEL GALERIE ═══ */
.ac-card-media.ac-has-carousel { padding: 0; position: relative; overflow: hidden; }
.ac-carousel-track {
  position: absolute;
  inset: 0;
  display: flex;
}
.ac-carousel-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity .6s ease;
}
.ac-carousel-slide.active { opacity: 1; z-index: 1; }
.ac-carousel-slide img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  cursor: zoom-in;
}
.ac-carousel-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(255,255,255,.92);
  border: none;
  color: var(--ac-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  z-index: 3;
  box-shadow: 0 4px 14px rgba(0,0,0,.18);
  transition: all .2s;
  opacity: 0;
}
.ac-card-media:hover .ac-carousel-nav { opacity: 1; }
.ac-carousel-nav:hover { background: #fff; transform: translateY(-50%) scale(1.08); }
.ac-carousel-prev { left: 10px; }
.ac-carousel-next { right: 10px; }

.ac-carousel-dots {
  position: absolute;
  bottom: 10px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  z-index: 3;
}
.ac-carousel-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,.55);
  border: none;
  cursor: pointer;
  transition: all .25s;
  padding: 0;
}
.ac-carousel-dot.active {
  background: #fff;
  width: 22px;
  border-radius: 100px;
}

/* ═══ MODALE ARTICLE (lightbox 2 colonnes) ═══ */
.ac-article-modal {
  position: fixed;
  inset: 0;
  background: rgba(10, 20, 10, .82);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9998;
  padding: 30px 20px;
}
.ac-article-modal.show { display: flex; }
.ac-article-box {
  background: #fff;
  border-radius: 20px;
  max-width: 1180px;
  width: 100%;
  height: calc(100vh - 60px);
  max-height: calc(100vh - 60px);
  display: flex;
  flex-direction: row;
  overflow: hidden;
  box-shadow: 0 40px 100px rgba(0,0,0,.5);
  position: relative;
  animation: acArticleIn .35s cubic-bezier(.22, 1, .36, 1);
}
/* Mode media-only : texte caché, media pleine largeur */
.ac-article-box.ac-media-only {
  max-width: 1100px;
  background: #0a0f0a;
}
.ac-article-box.ac-media-only .ac-article-text { display: none; }
.ac-article-box.ac-media-only .ac-article-media { min-height: 70vh; }

/* Mode journal : article texte sans média, style magazine pleine largeur */
.ac-article-box.ac-article-journal {
  max-width: 820px;
  background: #fff;
  flex-direction: column;
}
.ac-article-box.ac-article-journal .ac-article-media { display: none; }
.ac-article-box.ac-article-journal .ac-article-fullscreen { display: none; }
.ac-article-box.ac-article-journal .ac-article-text {
  flex: 1 1 auto;
  width: 100%;
  height: 100%;
  max-height: 100%;
  padding: 64px 72px 52px;
  overflow-y: auto;
  background:
    linear-gradient(#fff, #fff) padding-box,
    radial-gradient(circle at top left, #f3f8ef 0%, #fff 60%) border-box;
  position: relative;
}
.ac-article-box.ac-article-journal .ac-article-text::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--ac-green) 0%, #9bc18a 50%, transparent 100%);
}
.ac-article-box.ac-article-journal .ac-article-text::after {
  content: '\201C';
  position: absolute;
  top: -30px; right: 30px;
  font-family: 'Playfair Display', serif;
  font-size: 14rem;
  line-height: 1;
  color: var(--ac-green);
  opacity: .06;
  pointer-events: none;
  user-select: none;
}
.ac-article-box.ac-article-journal .ac-article-title {
  font-family: 'Playfair Display', serif;
  font-size: 2rem;
  line-height: 1.2;
  letter-spacing: -.01em;
  margin: 10px 0 14px;
}
.ac-article-box.ac-article-journal .ac-article-title::after {
  content: '';
  display: block;
  width: 60px;
  height: 3px;
  background: var(--ac-green);
  margin-top: 16px;
  border-radius: 2px;
}
.ac-article-box.ac-article-journal .ac-article-extrait {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.1rem;
  font-style: italic;
  color: #555;
  line-height: 1.55;
  margin: 0 0 22px;
  padding: 0;
  border: none;
}
.ac-article-box.ac-article-journal .ac-article-content {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.08rem;
  line-height: 1.8;
  color: #2a2a2a;
  text-align: justify;
  hyphens: auto;
  column-count: 1;
}
.ac-article-box.ac-article-journal .ac-article-content > p:first-of-type::first-letter {
  font-family: 'Playfair Display', serif;
  font-size: 4rem;
  font-weight: 700;
  float: left;
  line-height: .85;
  padding: 6px 12px 0 0;
  color: var(--ac-green);
}
.ac-article-box.ac-article-journal .ac-article-content p { margin: 0 0 16px; }
@media (max-width: 720px) {
  .ac-article-box.ac-article-journal .ac-article-text { padding: 40px 24px 32px; }
  .ac-article-box.ac-article-journal .ac-article-title { font-size: 1.5rem; }
  .ac-article-box.ac-article-journal .ac-article-extrait { font-size: 1rem; }
  .ac-article-box.ac-article-journal .ac-article-content { font-size: .98rem; }
  .ac-article-box.ac-article-journal .ac-article-content > p:first-of-type::first-letter { font-size: 3rem; }
  .ac-article-box.ac-article-journal .ac-article-text::after { font-size: 8rem; top: -20px; right: 15px; }
}
@keyframes acArticleIn {
  from { opacity: 0; transform: scale(.96) translateY(12px); }
  to   { opacity: 1; transform: none; }
}
.ac-article-close {
  position: absolute;
  top: 16px;
  right: 18px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: rgba(26,46,26,.75);
  color: #fff;
  font-size: 1.4rem;
  cursor: pointer;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .2s, transform .2s;
  backdrop-filter: blur(6px);
}
.ac-article-close:hover { background: rgba(26,46,26,.95); transform: rotate(90deg); }

/* Bouton fullscreen sur la colonne média */
.ac-article-fullscreen {
  position: absolute;
  top: 16px;
  left: 16px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: rgba(26,46,26,.55);
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
  z-index: 11;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .2s, transform .25s;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.ac-article-fullscreen:hover {
  background: rgba(26,46,26,.85);
  transform: scale(1.08);
}
/* En mode fullscreen, déplacer le bouton du coin gauche pour ne pas gêner */
.ac-article-box.ac-media-only .ac-article-fullscreen {
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.25);
}
.ac-article-box.ac-media-only .ac-article-fullscreen:hover {
  background: rgba(255,255,255,.25);
}

.ac-article-media {
  position: relative;
  background: #0a0f0a;
  flex: 1.05 1 0;
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: hidden;
}
.ac-article-media > img,
.ac-article-media > video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* Carrousel dans la modale */
.ac-article-carousel {
  position: absolute;
  inset: 0;
}
.ac-article-carousel .ac-carousel-slide {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  opacity: 0;
  transition: opacity .6s ease;
}
.ac-article-carousel .ac-carousel-slide.active { opacity: 1; z-index: 1; }
.ac-article-carousel .ac-carousel-slide img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  cursor: default;
}
.ac-article-carousel .ac-carousel-nav {
  opacity: 1;
  width: 44px;
  height: 44px;
  font-size: 1.3rem;
  background: rgba(255,255,255,.92);
}
.ac-article-carousel .ac-carousel-prev { left: 16px; }
.ac-article-carousel .ac-carousel-next { right: 16px; }
.ac-article-carousel .ac-carousel-dots { bottom: 16px; }

.ac-article-text {
  flex: 1 1 0;
  padding: 40px 44px 40px;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  min-width: 0;
  height: 100%;
  max-height: 100%;
  gap: 6px;
}
.ac-article-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.ac-article-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.85rem;
  font-weight: 600;
  color: var(--ac-text);
  margin: 0 0 14px;
  line-height: 1.25;
}
.ac-article-extrait {
  font-size: 1rem;
  color: var(--ac-text-secondary);
  font-style: italic;
  margin: 0 0 18px;
  padding-bottom: 16px;
  border-bottom: 1px solid #EEF2EA;
  line-height: 1.6;
}
.ac-article-content {
  color: var(--ac-text);
  font-size: .95rem;
  line-height: 1.75;
}
.ac-article-content p { margin: 0 0 14px; }
.ac-article-content h2,
.ac-article-content h3 {
  font-family: 'Playfair Display', serif;
  color: var(--ac-text);
  margin: 22px 0 10px;
}
.ac-article-content h2 { font-size: 1.4rem; }
.ac-article-content h3 { font-size: 1.15rem; }
.ac-article-content a { color: var(--ac-green); text-decoration: underline; }
.ac-article-content img {
  max-width: 100%;
  height: auto;
  border-radius: 10px;
  margin: 14px 0;
}
.ac-article-content blockquote {
  border-left: 3px solid var(--ac-green-pale);
  padding: 10px 18px;
  margin: 18px 0;
  color: var(--ac-text-secondary);
  font-style: italic;
  background: var(--ac-green-bg);
  border-radius: 0 10px 10px 0;
}

@media (max-width: 900px) {
  .ac-article-box {
    flex-direction: column;
    height: calc(100vh - 40px);
    max-height: calc(100vh - 40px);
  }
  .ac-article-media { flex: 0 0 45vh; min-height: 0; height: 45vh; }
  .ac-article-text { flex: 1 1 auto; padding: 28px 24px 30px; height: auto; }
  .ac-article-title { font-size: 1.5rem; }
}

.ac-card-content {
  color: var(--ac-text);
  font-size: .92rem;
  line-height: 1.65;
  margin-top: 4px;
  border-top: 1px solid #F0F4ED;
  padding-top: 14px;
}
.ac-card-content :first-child { margin-top: 0; }
.ac-card-content :last-child { margin-bottom: 0; }
.ac-card-content p { margin: 0 0 10px; }
.ac-card-content h2,
.ac-card-content h3 {
  font-family: 'Playfair Display', serif;
  color: var(--ac-text);
  margin: 14px 0 8px;
}
.ac-card-content h2 { font-size: 1.2rem; }
.ac-card-content h3 { font-size: 1.05rem; }
.ac-card-content a { color: var(--ac-green); text-decoration: underline; }
.ac-card-content img {
  max-width: 100%;
  height: auto;
  border-radius: 10px;
  margin: 10px 0;
}
.ac-card-content blockquote {
  border-left: 3px solid var(--ac-green-pale);
  padding: 6px 14px;
  margin: 12px 0;
  color: var(--ac-text-secondary);
  font-style: italic;
  background: var(--ac-green-bg);
  border-radius: 0 8px 8px 0;
}

/* Full card : réordonne verticalement */
.ac-card.ac-card-full .ac-card-body {
  padding: 22px 28px 26px;
}

/* Responsive */
@media (max-width: 720px) {
  .ac-card { grid-template-columns: 1fr; }
  .ac-card:not(.ac-card-full) .ac-card-media {
    border-right: none;
    border-bottom: 1px solid var(--ac-border);
    min-height: 200px;
    max-height: 280px;
  }
  .ac-card-body { padding: 20px; }
  .ac-card-title { font-size: 1.2rem; }
}
.ac-post-content {
  color: var(--ac-text);
  font-size: .95rem;
  line-height: 1.7;
}
.ac-post-content :first-child { margin-top: 0; }
.ac-post-content :last-child { margin-bottom: 0; }
.ac-post-content p { margin: 0 0 10px; }
.ac-post-content h2, .ac-post-content h3 {
  font-family: 'Playfair Display', serif;
  color: var(--ac-text);
  margin: 18px 0 10px;
}
.ac-post-content h2 { font-size: 1.3rem; }
.ac-post-content h3 { font-size: 1.1rem; }
.ac-post-content img {
  max-width: 100%;
  height: auto;
  border-radius: 10px;
  margin: 12px 0;
}
.ac-post-content a { color: var(--ac-green); text-decoration: underline; }
.ac-post-content blockquote {
  border-left: 3px solid var(--ac-green-pale);
  padding: 6px 16px;
  margin: 14px 0;
  color: var(--ac-text-secondary);
  font-style: italic;
  background: var(--ac-green-bg);
  border-radius: 0 8px 8px 0;
}

/* ── Galerie ── */
.ac-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 6px;
  padding: 12px 14px 14px;
  background: #F7FAF4;
}
.ac-gallery img {
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  border-radius: 10px;
  cursor: pointer;
  transition: transform .3s, box-shadow .3s;
}
.ac-gallery img:hover {
  transform: scale(1.03);
  box-shadow: 0 6px 20px rgba(0,0,0,.15);
}

/* ── Sidebar ── */
.ac-sidebar { display: flex; flex-direction: column; gap: 24px; position: relative; }
.ac-sidebar-shape-wrap {
  position: relative;
  margin-top: -20px;
  margin-left: -30px;
  height: 130px;
  overflow: hidden;
  pointer-events: none;
}
.ac-sidebar-shape-wrap::before {
  content: '';
  position: absolute;
  top: -87px;
  left: 50px;
  width: 280px;
  height: 280px;
  background-color: var(--ac-green);
  -webkit-mask: url('/spocspace/website/assets/img/shape-8.svg') no-repeat center / contain;
          mask: url('/spocspace/website/assets/img/shape-8.svg') no-repeat center / contain;
  transform: rotate(90deg);
  opacity: .35;
  filter: drop-shadow(0 8px 24px rgba(46,125,50,.08));
}
@media (max-width: 960px) {
  .ac-sidebar-shape-wrap { height: 100px; margin-left: -10px; }
  .ac-sidebar-shape-wrap::before { width: 220px; height: 220px; top: -145px; opacity: .5; }
}
.ac-sidebar-card {
  background: var(--ac-surface);
  border: 1px solid var(--ac-border);
  border-radius: var(--ac-radius);
  padding: 22px;
  box-shadow: var(--ac-shadow);
}
.ac-sidebar-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 14px;
  margin-bottom: 14px;
  border-bottom: 1px solid var(--ac-border);
}
.ac-sidebar-header h3 {
  font-family: 'Playfair Display', serif;
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--ac-text);
  margin: 0;
}
.ac-sidebar-header i {
  font-size: 1.3rem;
  color: var(--ac-green);
}

.ac-activity {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
  padding: 14px 0;
  border-bottom: 1px dashed var(--ac-border);
}
.ac-activity:last-child { border-bottom: none; padding-bottom: 0; }
.ac-activity:first-child { padding-top: 0; }

.ac-activity-date {
  background: var(--ac-green-bg);
  border: 1px solid var(--ac-border);
  border-radius: 12px;
  width: 62px;
  height: 68px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--ac-green);
  flex-shrink: 0;
}
.ac-activity-date-day {
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1;
}
.ac-activity-date-month {
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
  margin-top: 3px;
}
.ac-activity-body { min-width: 0; }
.ac-activity-title {
  font-size: .95rem;
  font-weight: 600;
  color: var(--ac-text);
  margin: 0 0 4px;
  display: flex;
  align-items: flex-start;
  gap: 6px;
}
.ac-activity-title i { color: var(--ac-green); font-size: .9rem; margin-top: 3px; }
.ac-activity-meta {
  font-size: .78rem;
  color: var(--ac-text-muted);
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ac-activity-meta span { display: inline-flex; align-items: center; gap: 5px; }

.ac-sidebar-empty {
  text-align: center;
  color: var(--ac-text-muted);
  font-size: .88rem;
  padding: 20px 0;
}
.ac-sidebar-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: .5; }

/* ── Loader + Empty ── */
.ac-loading {
  text-align: center;
  padding: 60px 20px;
  color: var(--ac-text-muted);
}
.ac-loading i {
  font-size: 2rem;
  display: block;
  margin-bottom: 12px;
  animation: acSpin 1s linear infinite;
}
@keyframes acSpin { to { transform: rotate(360deg); } }

.ac-empty {
  text-align: center;
  padding: 80px 20px;
  background: var(--ac-surface);
  border: 1px dashed var(--ac-border);
  border-radius: var(--ac-radius);
  color: var(--ac-text-muted);
}
.ac-empty i {
  font-size: 3rem;
  display: block;
  margin-bottom: 14px;
  opacity: .4;
}

.ac-load-more {
  display: block;
  margin: 40px auto 0;
  padding: 12px 28px;
  background: transparent;
  border: 1.5px solid var(--ac-green);
  border-radius: 100px;
  color: var(--ac-green);
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
}
.ac-load-more:hover { background: var(--ac-green); color: #fff; }
.ac-load-more:disabled { opacity: .5; cursor: not-allowed; }

/* ── Lightbox ── */
.ac-lightbox {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.92);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.ac-lightbox.show { display: flex; }
.ac-lightbox img {
  max-width: 100%;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 8px;
}
.ac-lightbox-close {
  position: absolute;
  top: 20px; right: 30px;
  background: none;
  border: none;
  color: #fff;
  font-size: 2.4rem;
  cursor: pointer;
  opacity: .8;
}
.ac-lightbox-close:hover { opacity: 1; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══ HERO ═══ -->
<section class="ac-hero">
  <h1><i class="bi bi-newspaper"></i> Actualités</h1>
  <p>Retrouvez ici les moments forts, animations, photos et vidéos de la vie à l'<?= h($emsNom) ?>.</p>
</section>

<!-- ═══ BREADCRUMB ═══ -->
<nav class="ac-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="ac-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="ac-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="ac-breadcrumb-current" aria-current="page"><i class="bi bi-newspaper"></i> Actualités</li>
  </ol>
</nav>

<!-- ═══ LAYOUT ═══ -->
<main class="ac-layout">

  <!-- Feed -->
  <div>
    <div class="ac-feed-header">
      <h2 class="ac-feed-title">Fil d'<em>actualité</em></h2>
      <div class="ac-filters" id="acFilters">
        <button class="ac-filter-btn active" data-type="">Tous</button>
        <button class="ac-filter-btn" data-type="photo"><i class="bi bi-image"></i> Photos</button>
        <button class="ac-filter-btn" data-type="video"><i class="bi bi-camera-video"></i> Vidéos</button>
        <button class="ac-filter-btn" data-type="galerie"><i class="bi bi-images"></i> Galeries</button>
        <button class="ac-filter-btn" data-type="affiche"><i class="bi bi-megaphone"></i> Affiches</button>
        <button class="ac-filter-btn" data-type="texte"><i class="bi bi-file-text"></i> Textes</button>
      </div>
    </div>

    <div class="ac-feed" id="acFeed">
      <div class="ac-loading"><i class="bi bi-arrow-repeat"></i> Chargement…</div>
    </div>

    <button class="ac-load-more" id="acLoadMore" style="display:none">
      <i class="bi bi-arrow-down-circle"></i> Charger plus
    </button>
  </div>

  <!-- Sidebar -->
  <aside class="ac-sidebar">
    <div class="ac-sidebar-card">
      <div class="ac-sidebar-header">
        <i class="bi bi-calendar-event"></i>
        <h3>Prochaines activités</h3>
      </div>
      <div id="acActivitesList">
        <div class="ac-loading" style="padding:20px 0"><i class="bi bi-arrow-repeat"></i></div>
      </div>
    </div>

    <!-- Affiches accrochées par l'admin -->
    <div id="acSidebarAffiches"></div>

    <div class="ac-sidebar-card ac-sidebar-rester" style="background:linear-gradient(135deg,#FFFCEB 0%,#FAFDF7 100%);border-color:rgba(230,194,32,.4)">
      <div class="ac-sidebar-header" style="border-color:rgba(230,194,32,.3)">
        <i class="bi bi-bell-fill" style="color:var(--ac-gold-dark)"></i>
        <h3>Rester informé</h3>
      </div>
      <p style="font-size:.88rem;color:var(--ac-text-secondary);margin:0 0 14px">Abonnez-vous à notre fil pour ne rien manquer des actualités de l'établissement.</p>
      <a href="/spocspace/website/#contact" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--ac-green);color:#fff;border-radius:100px;text-decoration:none;font-size:.85rem;font-weight:600">
        <i class="bi bi-envelope"></i> Nous contacter
      </a>
    </div>
    <div class="ac-sidebar-shape-wrap" aria-hidden="true"></div>
  </aside>
</main>

<!-- Lightbox image simple -->
<div class="ac-lightbox" id="acLightbox">
  <button class="ac-lightbox-close" onclick="acCloseLightbox()">&times;</button>
  <img id="acLightboxImg" src="" alt="">
</div>

<!-- Modale article (style Facebook) -->
<div class="ac-article-modal" id="acArticleModal">
  <div class="ac-article-box">
    <button class="ac-article-close" onclick="acCloseArticle()" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
    <button class="ac-article-fullscreen" onclick="acToggleMediaFullscreen()" aria-label="Agrandir les médias" title="Agrandir">
      <i class="bi bi-arrows-fullscreen" id="acFsIconExpand"></i>
      <i class="bi bi-fullscreen-exit" id="acFsIconExit" style="display:none"></i>
    </button>
    <div class="ac-article-media" id="acArticleMedia"></div>
    <div class="ac-article-text">
      <div class="ac-article-meta" id="acArticleMeta"></div>
      <h1 class="ac-article-title" id="acArticleTitle"></h1>
      <div class="ac-article-extrait" id="acArticleExtrait"></div>
      <div class="ac-article-content" id="acArticleContent"></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
  const FEED = document.getElementById('acFeed');
  const LOAD_MORE = document.getElementById('acLoadMore');
  const FILTERS = document.getElementById('acFilters');
  const API = '/spocspace/website/api.php';
  const PAGE_SIZE = 8;

  let currentType = '';
  let currentOffset = 0;
  let totalCount = 0;

  const TYPE_LABELS = {
    photo: "Photo", video: "Vidéo", galerie: "Galerie",
    affiche: "Affiche", texte: "Article"
  };
  const TYPE_ICONS = {
    photo: "bi-image", video: "bi-camera-video", galerie: "bi-images",
    affiche: "bi-megaphone", texte: "bi-file-text"
  };

  function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleDateString('fr-CH', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  // Map id → actualite pour la modale
  const ARTICLES = new Map();

  function buildCard(a) {
    ARTICLES.set(a.id, a);

    const type = a.type || 'texte';
    const typeLabel = TYPE_LABELS[type] || type;
    const typeIcon = TYPE_ICONS[type] || 'bi-file-text';
    const isPinned = a.epingle == 1;
    const dateStr = formatDate(a.published_at || a.created_at);

    // ── Media ──
    let mediaHtml = '';
    if (type === 'video' && a.video_url) {
      mediaHtml = `
        <div class="ac-card-media ac-has-video">
          <div class="ac-video-wrap" data-video-url="${escHtml(a.video_url)}" data-video-poster="${escHtml(a.video_poster || a.cover_url || '')}">
            ${a.video_poster || a.cover_url
              ? `<img src="${escHtml(a.video_poster || a.cover_url)}" alt="${escHtml(a.titre)}">`
              : ''
            }
            <div class="ac-video-play" onclick="acOpenArticle('${a.id}')">
              <div class="ac-video-play-btn"><i class="bi bi-play-fill"></i></div>
            </div>
          </div>
        </div>`;
    } else if (type === 'video' && (a.video_poster || a.cover_url)) {
      // Vidéo sans fichier vidéo : fallback cover image cliquable
      mediaHtml = `
        <div class="ac-card-media">
          <img src="${escHtml(a.video_poster || a.cover_url)}" alt="" onerror="acImgFallback(this,'bi-camera-video')" onclick="acOpenArticle('${a.id}')">
        </div>`;
    } else if (type === 'video') {
      // Vidéo sans rien : grand icône placeholder
      mediaHtml = `
        <div class="ac-card-media ac-media-placeholder" onclick="acOpenArticle('${a.id}')">
          <i class="bi bi-camera-video"></i>
        </div>`;
    } else if ((type === 'photo' || type === 'affiche') && a.cover_url) {
      mediaHtml = `
        <div class="ac-card-media ${type === 'affiche' ? 'ac-affiche' : ''}">
          <img src="${escHtml(a.cover_url)}" alt="" onerror="acImgFallback(this,'bi-image')" onclick="acOpenArticle('${a.id}')">
        </div>`;
    } else if (type === 'galerie' && a.images && a.images.length) {
      const imgs = a.images;
      const count = imgs.length;
      if (count >= 2) {
        // Carrousel auto
        const slides = imgs.map((url, i) => `
          <div class="ac-carousel-slide ${i === 0 ? 'active' : ''}" data-idx="${i}">
            <img src="${escHtml(url)}" alt="" onclick="acOpenArticle('${a.id}')">
          </div>
        `).join('');
        const dots = imgs.map((_, i) =>
          `<button class="ac-carousel-dot ${i === 0 ? 'active' : ''}" data-idx="${i}" type="button" aria-label="Slide ${i + 1}"></button>`
        ).join('');
        mediaHtml = `
          <div class="ac-card-media ac-has-carousel" data-carousel="card">
            ${slides}
            <button class="ac-carousel-nav ac-carousel-prev" type="button" aria-label="Précédent"><i class="bi bi-chevron-left"></i></button>
            <button class="ac-carousel-nav ac-carousel-next" type="button" aria-label="Suivant"><i class="bi bi-chevron-right"></i></button>
            <div class="ac-carousel-dots">${dots}</div>
            <div class="ac-gallery-count"><i class="bi bi-images"></i>${count}</div>
          </div>
        `;
      } else {
        const cover = a.cover_url || imgs[0];
        mediaHtml = `
          <div class="ac-card-media">
            <img src="${escHtml(cover)}" alt="${escHtml(a.titre)}" onclick="acOpenArticle('${a.id}')">
          </div>
        `;
      }
    } else if (type === 'photo' || type === 'affiche') {
      // Photo / affiche sans image : grand icône placeholder
      mediaHtml = `
        <div class="ac-card-media ac-media-placeholder" onclick="acOpenArticle('${a.id}')">
          <i class="bi bi-image"></i>
        </div>`;
    } else if (type === 'galerie') {
      // Galerie sans images : grand icône placeholder
      mediaHtml = `
        <div class="ac-card-media ac-media-placeholder" onclick="acOpenArticle('${a.id}')">
          <i class="bi bi-images"></i>
        </div>`;
    }

    // ── Body ──
    const longArticle = isLongArticle(a);
    let ctaLabel, ctaIcon;
    if (longArticle) {
      ctaLabel = 'Lire la suite';
      ctaIcon = 'bi-arrow-right';
    } else if (type === 'video') {
      ctaLabel = 'Voir la vidéo';
      ctaIcon = 'bi-play-fill';
    } else if (type === 'galerie') {
      ctaLabel = 'Voir les photos';
      ctaIcon = 'bi-images';
    } else if (type === 'photo' || type === 'affiche') {
      ctaLabel = 'Voir l\'image';
      ctaIcon = 'bi-eye';
    } else {
      ctaLabel = null;
    }

    // Extrait : au moins ~3 lignes de prévisualisation, fallback sur le contenu stripé
    const stripHtml = (h) => (h || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    let previewText = (a.extrait || '').trim();
    const contenuText = stripHtml(a.contenu);
    if (previewText.length < 120 && contenuText) {
      previewText = previewText ? previewText + ' ' + contenuText : contenuText;
    }

    const bodyHtml = `
      <div class="ac-card-body">
        <div class="ac-card-meta">
          <span class="ac-type-chip"><i class="bi ${typeIcon}"></i>${typeLabel}</span>
          <span class="ac-meta-date"><i class="bi bi-clock"></i>${dateStr}</span>
          ${isPinned ? '<span class="ac-pin-chip"><i class="bi bi-pin-angle-fill"></i>Épinglé</span>' : ''}
        </div>
        <h2 class="ac-card-title" onclick="acOpenArticle('${a.id}')" style="cursor:pointer">${escHtml(a.titre)}</h2>
        ${previewText ? `<p class="ac-card-extrait">${escHtml(previewText)}</p>` : ''}
        ${ctaLabel ? `<button class="ac-read-more" type="button" onclick="acOpenArticle('${a.id}')">${ctaLabel} <i class="bi ${ctaIcon}"></i></button>` : ''}
      </div>
    `;

    const textClass = type === 'texte' ? 'ac-card-texte' : '';
    const noMediaClass = !mediaHtml ? 'ac-card-no-media' : '';

    return `
      <article class="ac-card ${isPinned ? 'ac-pinned' : ''} ${textClass} ${noMediaClass}" data-id="${a.id}">
        ${mediaHtml}
        ${bodyHtml}
      </article>
    `;
  }

  // ═══ CARROUSEL GALERIE (auto + manuel) ═══
  const carouselStates = new WeakMap();

  function initCarousels(rootEl) {
    rootEl.querySelectorAll('[data-carousel="card"]').forEach(el => {
      if (carouselStates.has(el)) return;
      const slides = Array.from(el.querySelectorAll('.ac-carousel-slide'));
      const dots = Array.from(el.querySelectorAll('.ac-carousel-dot'));
      if (slides.length < 2) return;

      let idx = 0;
      let interval = null;
      let paused = false;

      const goTo = (n) => {
        idx = (n + slides.length) % slides.length;
        slides.forEach((s, i) => s.classList.toggle('active', i === idx));
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
      };
      const next = () => goTo(idx + 1);
      const prev = () => goTo(idx - 1);

      el.querySelector('.ac-carousel-next').addEventListener('click', (e) => {
        e.stopPropagation();
        next();
        restart();
      });
      el.querySelector('.ac-carousel-prev').addEventListener('click', (e) => {
        e.stopPropagation();
        prev();
        restart();
      });
      dots.forEach((d, i) => {
        d.addEventListener('click', (e) => {
          e.stopPropagation();
          goTo(i);
          restart();
        });
      });

      el.addEventListener('mouseenter', () => { paused = true; });
      el.addEventListener('mouseleave', () => { paused = false; });

      const tick = () => { if (!paused) next(); };
      const restart = () => {
        if (interval) clearInterval(interval);
        interval = setInterval(tick, 4500);
      };
      restart();

      carouselStates.set(el, { goTo, next, prev });
    });
  }

  async function loadFeed(reset = true) {
    if (reset) {
      currentOffset = 0;
      FEED.innerHTML = '<div class="ac-loading"><i class="bi bi-arrow-repeat"></i> Chargement…</div>';
    }
    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'get_actualites',
          limit: PAGE_SIZE,
          offset: currentOffset,
          type: currentType || undefined
        })
      });
      const data = await res.json();
      if (!data.success) throw new Error('API error');

      totalCount = data.total;
      const list = data.actualites || [];

      if (reset && list.length === 0) {
        FEED.innerHTML = `
          <div class="ac-empty">
            <i class="bi bi-newspaper"></i>
            <div>Aucune actualité pour le moment.</div>
          </div>`;
        LOAD_MORE.style.display = 'none';
        return;
      }

      const html = list.map(buildCard).join('');
      if (reset) FEED.innerHTML = html;
      else FEED.insertAdjacentHTML('beforeend', html);

      // Init carrousels pour les nouvelles cartes
      initCarousels(FEED);

      currentOffset += list.length;
      LOAD_MORE.style.display = (currentOffset < totalCount) ? 'block' : 'none';
    } catch (e) {
      console.error(e);
      if (reset) FEED.innerHTML = '<div class="ac-empty"><i class="bi bi-exclamation-triangle"></i><div>Erreur de chargement</div></div>';
    }
  }

  async function loadActivites() {
    const list = document.getElementById('acActivitesList');
    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_activites_venir', limit: 6 })
      });
      const data = await res.json();
      if (!data.success || !data.activites || !data.activites.length) {
        list.innerHTML = '<div class="ac-sidebar-empty"><i class="bi bi-calendar2-x"></i>Aucune activité planifiée</div>';
        return;
      }
      const months = ['janv.','févr.','mars','avril','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
      list.innerHTML = data.activites.map(a => {
        const d = new Date(a.date_activite + 'T00:00:00');
        const day = d.getDate();
        const month = months[d.getMonth()];
        const heure = a.heure_debut ? a.heure_debut.substring(0, 5) : null;
        const heureFin = a.heure_fin ? a.heure_fin.substring(0, 5) : null;
        return `
          <div class="ac-activity">
            <div class="ac-activity-date">
              <div class="ac-activity-date-day">${day}</div>
              <div class="ac-activity-date-month">${month}</div>
            </div>
            <div class="ac-activity-body">
              <div class="ac-activity-title"><i class="bi ${escHtml(a.icone || 'bi-calendar-event')}"></i>${escHtml(a.titre)}</div>
              <div class="ac-activity-meta">
                ${heure ? `<span><i class="bi bi-clock"></i>${heure}${heureFin ? ' – ' + heureFin : ''}</span>` : ''}
                ${a.lieu ? `<span><i class="bi bi-geo-alt"></i>${escHtml(a.lieu)}</span>` : ''}
              </div>
            </div>
          </div>`;
      }).join('');
    } catch (e) {
      list.innerHTML = '<div class="ac-sidebar-empty"><i class="bi bi-exclamation-triangle"></i>Erreur</div>';
    }
  }

  // ── Affiches accrochées sur la sidebar (admin) ──
  async function loadSidebarAffiches() {
    const wrap = document.getElementById('acSidebarAffiches');
    if (!wrap) return;
    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_sidebar_affiches' })
      });
      const data = await res.json();
      if (!data.success || !data.affiches || !data.affiches.length) { wrap.innerHTML = ''; return; }
      wrap.innerHTML = data.affiches.map(a => {
        if (!a.cover_url) return '';
        return `
          <div class="ac-sidebar-card ac-sidebar-affiche" style="padding:0;overflow:hidden">
            <img src="${escHtml(a.cover_url)}"
                 alt="${escHtml(a.titre)}"
                 style="width:100%;height:auto;display:block;cursor:zoom-in"
                 onclick="acOpenLightbox('${escHtml(a.cover_url)}')">
          </div>`;
      }).join('');
    } catch (e) { wrap.innerHTML = ''; }
  }
  loadSidebarAffiches();

  FILTERS.addEventListener('click', (e) => {
    const btn = e.target.closest('.ac-filter-btn');
    if (!btn) return;
    FILTERS.querySelectorAll('.ac-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentType = btn.dataset.type;
    loadFeed(true);
  });

  LOAD_MORE.addEventListener('click', () => loadFeed(false));

  // ── Lightbox ──
  window.acOpenLightbox = function(url) {
    document.getElementById('acLightboxImg').src = url;
    document.getElementById('acLightbox').classList.add('show');
  };
  window.acCloseLightbox = function() {
    document.getElementById('acLightbox').classList.remove('show');
  };
  document.getElementById('acLightbox').addEventListener('click', (e) => {
    if (e.target.id === 'acLightbox') acCloseLightbox();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') acCloseLightbox();
  });

  // ══ MODALE ARTICLE (style Facebook) ══
  const ART_MODAL = document.getElementById('acArticleModal');
  const ART_MEDIA = document.getElementById('acArticleMedia');
  const ART_META = document.getElementById('acArticleMeta');
  const ART_TITLE = document.getElementById('acArticleTitle');
  const ART_EXTRAIT = document.getElementById('acArticleExtrait');
  const ART_CONTENT = document.getElementById('acArticleContent');
  let articleCarouselInterval = null;

  // Détecte si l'article a un texte "long" méritant la modale 2 colonnes
  function isLongArticle(a) {
    if (!a.contenu) return false;
    const plain = String(a.contenu).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return plain.length > 300;
  }

  // Fallback icône quand une image ne peut pas être chargée
  window.acImgFallback = function(img, iconClass) {
    const parent = img.parentElement;
    if (!parent) return;
    parent.classList.add('ac-media-placeholder');
    parent.innerHTML = `<i class="bi ${iconClass}"></i>`;
  };

  window.acOpenArticle = function(id) {
    const a = ARTICLES.get(id);
    if (!a) return;
    const type = a.type || 'texte';
    const typeLabel = TYPE_LABELS[type] || type;
    const typeIcon = TYPE_ICONS[type] || 'bi-file-text';
    const dateStr = formatDate(a.published_at || a.created_at);

    // Mode journal si l'article est de type texte (pas de média)
    const isJournal = type === 'texte';
    // Mode media-only si le texte est court (sauf pour les articles)
    const mediaOnly = !isJournal && !isLongArticle(a);
    const box = ART_MODAL.querySelector('.ac-article-box');
    box.classList.toggle('ac-article-journal', isJournal);
    box.classList.toggle('ac-media-only', mediaOnly);
    box.dataset.userForceFullscreen = '';
    // Sync icon du bouton fullscreen
    document.getElementById('acFsIconExpand').style.display = mediaOnly ? 'none' : '';
    document.getElementById('acFsIconExit').style.display = mediaOnly ? '' : 'none';
    const fsBtn = document.querySelector('.ac-article-fullscreen');
    if (fsBtn) fsBtn.title = mediaOnly ? 'Revenir à l\'article' : 'Agrandir';

    // ── Media ──
    let mediaHtml = '';
    if (type === 'video' && a.video_url) {
      const poster = a.video_poster || a.cover_url || '';
      mediaHtml = `<video controls autoplay playsinline preload="metadata" ${poster ? `poster="${escHtml(poster)}"` : ''}>
        <source src="${escHtml(a.video_url)}" type="video/mp4">
      </video>`;
    } else if ((type === 'photo' || type === 'affiche') && a.cover_url) {
      mediaHtml = `<img src="${escHtml(a.cover_url)}" alt="${escHtml(a.titre)}">`;
    } else if (type === 'galerie' && a.images && a.images.length) {
      const imgs = a.images;
      if (imgs.length >= 2) {
        const slides = imgs.map((url, i) => `
          <div class="ac-carousel-slide ${i === 0 ? 'active' : ''}" data-idx="${i}">
            <img src="${escHtml(url)}" alt="">
          </div>
        `).join('');
        const dots = imgs.map((_, i) =>
          `<button class="ac-carousel-dot ${i === 0 ? 'active' : ''}" data-idx="${i}" type="button"></button>`
        ).join('');
        mediaHtml = `
          <div class="ac-article-carousel" id="acArtCarousel">
            ${slides}
            <button class="ac-carousel-nav ac-carousel-prev" type="button"><i class="bi bi-chevron-left"></i></button>
            <button class="ac-carousel-nav ac-carousel-next" type="button"><i class="bi bi-chevron-right"></i></button>
            <div class="ac-carousel-dots">${dots}</div>
          </div>
        `;
      } else {
        mediaHtml = `<img src="${escHtml(imgs[0])}" alt="${escHtml(a.titre)}">`;
      }
    } else {
      // Texte seul : pas de media
      mediaHtml = '<div style="color:#7E9A7A;font-size:1.2rem;padding:40px;text-align:center"><i class="bi bi-file-text" style="font-size:3rem;display:block;margin-bottom:12px;opacity:.5"></i>Article</div>';
    }

    ART_MEDIA.innerHTML = mediaHtml;

    // ── Meta ──
    ART_META.innerHTML = `
      <span class="ac-type-chip"><i class="bi ${typeIcon}"></i>${typeLabel}</span>
      <span class="ac-meta-date"><i class="bi bi-clock"></i>${dateStr}</span>
      ${a.epingle == 1 ? '<span class="ac-pin-chip"><i class="bi bi-pin-angle-fill"></i>Épinglé</span>' : ''}
    `;

    ART_TITLE.textContent = a.titre;
    ART_EXTRAIT.textContent = a.extrait || '';
    ART_EXTRAIT.style.display = a.extrait ? '' : 'none';
    ART_CONTENT.innerHTML = a.contenu || '';

    ART_MODAL.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Init carrousel de la modale si nécessaire
    const artCar = document.getElementById('acArtCarousel');
    if (artCar) initArticleCarousel(artCar);
  };

  window.acCloseArticle = function() {
    ART_MODAL.classList.remove('show');
    document.body.style.overflow = '';
    // Stop toute vidéo en lecture
    ART_MEDIA.querySelectorAll('video').forEach(v => { try { v.pause(); } catch(e){} });
    if (articleCarouselInterval) { clearInterval(articleCarouselInterval); articleCarouselInterval = null; }
    ART_MEDIA.innerHTML = '';
    // Restaure l'état fullscreen
    const box = ART_MODAL.querySelector('.ac-article-box');
    if (box) box.dataset.userForceFullscreen = '';
  };

  // Toggle fullscreen média (cache/montre le panneau texte)
  window.acToggleMediaFullscreen = function() {
    const box = ART_MODAL.querySelector('.ac-article-box');
    if (!box) return;
    const isFull = box.classList.toggle('ac-media-only');
    box.dataset.userForceFullscreen = isFull ? '1' : '';
    document.getElementById('acFsIconExpand').style.display = isFull ? 'none' : '';
    document.getElementById('acFsIconExit').style.display = isFull ? '' : 'none';
    const btn = document.querySelector('.ac-article-fullscreen');
    if (btn) btn.title = isFull ? 'Revenir à l\'article' : 'Agrandir';
  };

  function initArticleCarousel(el) {
    const slides = Array.from(el.querySelectorAll('.ac-carousel-slide'));
    const dots = Array.from(el.querySelectorAll('.ac-carousel-dot'));
    let idx = 0;
    const goTo = (n) => {
      idx = (n + slides.length) % slides.length;
      slides.forEach((s, i) => s.classList.toggle('active', i === idx));
      dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    };
    el.querySelector('.ac-carousel-next').addEventListener('click', (e) => { e.stopPropagation(); goTo(idx + 1); restart(); });
    el.querySelector('.ac-carousel-prev').addEventListener('click', (e) => { e.stopPropagation(); goTo(idx - 1); restart(); });
    dots.forEach((d, i) => d.addEventListener('click', (e) => { e.stopPropagation(); goTo(i); restart(); }));
    let paused = false;
    el.addEventListener('mouseenter', () => { paused = true; });
    el.addEventListener('mouseleave', () => { paused = false; });
    const tick = () => { if (!paused) goTo(idx + 1); };
    const restart = () => {
      if (articleCarouselInterval) clearInterval(articleCarouselInterval);
      articleCarouselInterval = setInterval(tick, 5000);
    };
    restart();
  }

  ART_MODAL.addEventListener('click', (e) => {
    if (e.target === ART_MODAL) acCloseArticle();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && ART_MODAL.classList.contains('show')) acCloseArticle();
  });

  loadFeed(true);
  loadActivites();
})();
</script>
</body>
</html>
