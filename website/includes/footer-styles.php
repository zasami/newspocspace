<?php
if (defined('WS_FOOTER_STYLES_LOADED')) return;
define('WS_FOOTER_STYLES_LOADED', true);
?>
<style>
.ws-footer { background: #1A2E1A; color: #B5C8B0; padding: 60px 0 0; text-decoration: none; }

/* ── Carousel Partenaires ── */
.ws-partners {
  background: #FAFDF7;
  padding: 40px 0 90px;
  margin-top: 60px;
  margin-bottom: 40px;
}
.ws-partners-inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 20px;
}
.ws-partners-title {
  text-align: center;
  font-family: 'Playfair Display', Georgia, serif;
  font-size: 2.125rem;
  font-weight: 600;
  color: #1A2E1A;
  letter-spacing: 0;
  text-transform: none;
  margin: 0 0 55px;
  position: relative;
  line-height: 1.2;
}
.ws-partners-title::before,
.ws-partners-title::after {
  content: '';
  display: inline-block;
  width: 50px;
  height: 1px;
  background: #81C784;
  vertical-align: middle;
  margin: 0 22px 8px;
  opacity: .6;
}
.ws-partners-carousel {
  position: relative;
  overflow-x: clip;
  overflow-y: visible;
  padding: 30px 0;
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
          mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}
.ws-partners-track {
  display: flex;
  width: max-content;
  align-items: center;
  animation: wsPartnersScroll 70s linear infinite;
  will-change: transform;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  transform: translate3d(0, 0, 0);
}
.ws-partners-carousel:hover .ws-partners-track { animation-play-state: paused; }
@keyframes wsPartnersScroll {
  0%   { transform: translate3d(0, 0, 0); }
  100% { transform: translate3d(-50%, 0, 0); }
}
.ws-partner-item {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 96px;
  width: 170px;
  padding: 12px 18px;
  margin-right: 60px;
  background: #fff;
  border: 1px solid #E8EFE3;
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(46,125,50,.04), 0 1px 2px rgba(0,0,0,.02);
  transform-origin: center center;
  position: relative;
  z-index: 1;
  transition: transform .45s cubic-bezier(.22, 1, .36, 1),
              box-shadow .45s cubic-bezier(.22, 1, .36, 1),
              border-color .35s ease,
              z-index 0s linear .35s,
              filter .4s ease,
              opacity .4s ease;
}
.ws-partner-item:hover {
  transform: scale(1.25) translateY(-4px);
  box-shadow:
    0 22px 50px rgba(46,125,50,.20),
    0 8px 20px rgba(0,0,0,.08);
  border-color: #81C784;
  z-index: 20;
  transition: transform .45s cubic-bezier(.22, 1, .36, 1),
              box-shadow .45s cubic-bezier(.22, 1, .36, 1),
              border-color .35s ease,
              z-index 0s linear 0s;
}
.ws-partner-item img {
  max-height: 100%;
  max-width: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  filter: grayscale(100%) contrast(.9);
  opacity: .72;
  transition: filter .35s ease, opacity .35s ease, transform .35s ease;
}
.ws-partner-item:hover img {
  filter: grayscale(0) contrast(1);
  opacity: 1;
}
/* Flou léger sur les autres logos quand l'un est survolé */
.ws-partners-track:has(.ws-partner-item:hover) .ws-partner-item:not(:hover) {
  filter: blur(1.5px) saturate(.85);
  opacity: .6;
}
@media (max-width: 768px) {
  .ws-partners { padding: 28px 0 60px; margin-top: 36px; margin-bottom: 24px; }
  .ws-partner-item { height: 72px; width: 130px; padding: 10px 14px; margin-right: 30px; border-radius: 12px; }
  .ws-partners-title::before,
  .ws-partners-title::after { width: 24px; margin: 0 10px; }
}
.ws-footer-brand { margin-bottom: 16px; }
.ws-footer-logo { height: 50px; width: auto; filter: brightness(1.8) contrast(0.9); }
.ws-footer-desc { font-size: .875rem; color: #8BA888; max-width: 300px; line-height: 1.6; }
.ws-footer h6 { color: #fff; font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 16px; }
.ws-footer-links { list-style: none; padding: 0; margin: 0; }
.ws-footer-links li { margin-bottom: 8px; font-size: .85rem; display: flex; align-items: center; gap: 8px; }
.ws-footer-links li i { color: #81C784; font-size: .8rem; }
.ws-footer-links a { color: #8BA888; transition: color .2s; text-decoration: none; }
.ws-footer-links a:hover { color: #F9D835; }
.ws-footer-bottom { border-top: 1px solid #2A4A2A; margin-top: 40px; padding: 20px 0; text-align: center; }
.ws-footer-bottom p { font-size: .8rem; color: #5A7A58; margin: 0; }
.ws-footer-legal { margin-top: 4px !important; font-size: .72rem !important; color: #4A6A48 !important; }
</style>
