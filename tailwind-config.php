<?php
/**
 * SpocSpace Care — Tailwind v4 global setup (browser/play mode self-hosted)
 *
 * Inclus depuis chaque shell PHP (index.php, admin/index.php, care/index.php) :
 *   <?php include __DIR__ . '/tailwind-config.php'; ?>      (depuis la racine)
 *   <?php include __DIR__ . '/../tailwind-config.php'; ?>   (depuis admin/ ou care/)
 *
 * Charge :
 *  - Google Fonts (Fraunces / Outfit / JetBrains Mono)
 *  - @tailwindcss/browser v4 self-hosted (offline-friendly, sans dépendance tiers)
 *  - Configuration du design system « Spocspace Care » via @theme (CSS-first)
 *  - Globals @layer base (typographie + base)
 *
 * Le partial s'appuie sur la variable PHP $cspNonce définie dans le shell hôte.
 *
 * NOTE Tailwind v4 :
 *  - Plus de `tailwind.config = {}` JS — tout passe par @theme dans une <style type="text/tailwindcss">.
 *  - Les CSS custom properties --color-*, --font-*, --shadow-*, --background-image-*
 *    génèrent automatiquement les utilities correspondantes (bg-*, text-*, font-*, shadow-*, bg-*).
 *  - Les couleurs nested v3 (ex: ok.bg, sec-soins.bg) sont aplaties en --color-ok-bg, --color-sec-soins-bg.
 */
$_tw_nonce = $cspNonce ?? '';
?>
<!-- Google Fonts: Fraunces (display) + Outfit (body) + JetBrains Mono (mono) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Tailwind CSS v4 — @tailwindcss/browser self-hosted (offline-friendly, sans dépendance tiers) -->
<script nonce="<?= htmlspecialchars($_tw_nonce, ENT_QUOTES) ?>" src="/newspocspace/assets/js/vendor/tailwind-browser.min.js?v=<?= APP_VERSION ?>"></script>

<!-- Configuration Spocspace Care + globals (@theme + @layer base) — syntaxe Tailwind v4 -->
<style type="text/tailwindcss">
  @theme {
    /* ═══ BRAND TEAL (couleur principale) ═══ */
    --color-teal-50:  #ecf5f3;
    --color-teal-100: #d2e7e2;
    --color-teal-200: #a8d1c8;
    --color-teal-300: #7ab5ab;
    --color-teal-400: #4d9a8e;
    --color-teal-500: #2d8074;
    --color-teal-600: #1f6359;  /* PRIMAIRE Spocspace */
    --color-teal-700: #164a42;
    --color-teal-800: #0f3631;
    --color-teal-900: #0d2a26;

    /* ═══ TEXTE & SURFACES ═══ */
    --color-ink:     #0d2a26;  /* titres, texte fort */
    --color-ink-2:   #324e4a;  /* texte courant */
    --color-ink-3:   #4a6661;  /* texte secondaire */

    --color-muted:   #6b8783;
    --color-muted-2: #8aa39f;

    --color-line:    #e3ebe8;
    --color-line-2:  #d4ddda;
    --color-line-3:  #c4d0cc;

    --color-surface:    #ffffff;
    --color-surface-2:  #fafbfa;
    --color-surface-3:  #f3f6f5;

    --color-bg: #f5f7f5;

    /* ═══ STATUTS SÉMANTIQUES ═══ */
    --color-ok:        #3d8b6b;
    --color-ok-bg:     #e3f0ea;
    --color-ok-line:   #b8d4c5;

    --color-warn:      #c97a2a;
    --color-warn-bg:   #fbf0e1;
    --color-warn-line: #e8c897;

    --color-danger:      #b8443a;
    --color-danger-bg:   #f7e3e0;
    --color-danger-line: #e6b8b0;

    --color-info:      #3a6a8a;
    --color-info-bg:   #e2ecf2;
    --color-info-line: #b5cad8;

    --color-warm:      #d4a04a;
    --color-warm-bg:   #fdf5e3;

    /* ═══ SECTEURS EMS Fegems ═══ */
    --color-sec-soins:    #1f6359;
    --color-sec-soins-bg: #ecf5f3;
    --color-sec-hotel:    #8a5a1a;
    --color-sec-hotel-bg: #fff3e3;
    --color-sec-anim:     #5e3a78;
    --color-sec-anim-bg:  #f0e8f5;
    --color-sec-int:      #3a5a2d;
    --color-sec-int-bg:   #e8f0e6;
    --color-sec-tech:     #2d4a6b;
    --color-sec-tech-bg:  #e6ecf2;
    --color-sec-admin:    #6b5a1f;
    --color-sec-admin-bg: #f0eee3;
    --color-sec-mgmt:     #8a3a30;
    --color-sec-mgmt-bg:  #fde8e6;

    /* ═══ SIDEBAR (textes & nuances dégradé teal foncé) ═══ */
    --color-sb-text:       #a8c4be;  /* items inactifs */
    --color-sb-text-hover: #e8f1ee;  /* items au hover */
    --color-sb-section:    #5d8077;  /* titres de sections */
    --color-sb-sub:        #7ea69c;  /* sous-titre logo (EMS PLATFORM) */
    --color-sb-muted:      #9bbab2;  /* sous-texte EMS card */

    /* ═══ POLICES ═══ */
    --font-display: "Fraunces", "Georgia", serif;
    --font-body:    "Outfit", "-apple-system", "BlinkMacSystemFont", sans-serif;
    --font-mono:    "JetBrains Mono", "Menlo", monospace;

    /* ═══ OMBRES ═══ */
    --shadow-sp-sm: 0 1px 2px rgba(13,42,38,0.04), 0 1px 1px rgba(13,42,38,0.03);
    --shadow-sp:    0 4px 16px -4px rgba(13,42,38,0.08), 0 2px 4px rgba(13,42,38,0.04);
    --shadow-sp-md: 0 8px 24px -6px rgba(13,42,38,0.12), 0 3px 8px rgba(13,42,38,0.05);
    --shadow-sp-lg: 0 16px 48px -12px rgba(13,42,38,0.18), 0 4px 12px rgba(13,42,38,0.06);
    --shadow-mark:  0 4px 12px rgba(125,211,168,0.3);

    /* ═══ GRADIENTS (background-image) ═══ */
    --background-image-grad-hero:     linear-gradient(135deg, #164a42 0%, #1f6359 50%, #2d8074 100%);
    --background-image-grad-sidebar:  linear-gradient(180deg, #0d2a26 0%, #164a42 100%);
    --background-image-grad-mark:     linear-gradient(135deg, #3da896, #7dd3a8);
    --background-image-grad-progress: linear-gradient(90deg, #2d8074, #5cad9b);
    /* Login : sombre bas-gauche → clair haut-droit (lumière diagonale) */
    --background-image-grad-login:    linear-gradient(to top right, #0d2a26 0%, #164a42 55%, #1f6359 100%);
    /* Modal header (Raccourcis + autres modales premium) : teal foncé → mid-teal → menthe lumineuse */
    --background-image-grad-modal-header: linear-gradient(135deg, #1f6359 0%, #2d8074 60%, #5cad9b 100%);
    /* Alias compatibles avec les classes existantes (bg-sidebar-grad / bg-mark-grad) */
    --background-image-sidebar-grad:  linear-gradient(180deg, #0d2a26 0%, #164a42 100%);
    --background-image-mark-grad:     linear-gradient(135deg, #3da896, #7dd3a8);
  }

  /* ═══ Globals Spocspace (typographie + base) ═══
   * Règle typo : tout le site en Outfit (font-body via la règle body{}).
   * font-display (Fraunces) est réservée AUX SEULS GRANDS CHIFFRES STATS
   * dans les cards (ex: "22 collab"), appliqué explicitement sur ces éléments.
   */
  @layer base {
    body {
      @apply font-body bg-bg text-ink-2 antialiased;
    }
    h1, h2, h3, h4, h5, h6 {
      @apply tracking-tight font-semibold text-ink;
    }
    a {
      @apply text-teal-600 transition-colors;
    }
    a:hover {
      @apply text-teal-700;
    }
    code {
      @apply font-mono text-sm bg-surface-3 px-1.5 py-0.5 rounded text-teal-700;
    }
  }
</style>

<!-- ═══ Global Search dropdown — styles non exprimables en utilities pures ═══ -->
<style nonce="<?= htmlspecialchars($_tw_nonce, ENT_QUOTES) ?>">
/* Panel show/hide transition */
.ss-gs-panel{
  opacity:0;visibility:hidden;transform:translateY(-6px);
  transition:opacity .18s ease,visibility .18s ease,transform .18s ease;
  pointer-events:none;
}
.ss-gs-panel.show{
  opacity:1;visibility:visible;transform:translateY(0);
  pointer-events:auto;
}

/* Custom scrollbar (results list) */
.ss-gs-scroll::-webkit-scrollbar{width:8px}
.ss-gs-scroll::-webkit-scrollbar-track{background:transparent}
.ss-gs-scroll::-webkit-scrollbar-thumb{background:#d4ddda;border-radius:99px}
.ss-gs-scroll::-webkit-scrollbar-thumb:hover{background:#6b8783}
.ss-gs-scroll{scrollbar-width:thin;scrollbar-color:#d4ddda transparent}

/* <mark> highlight (override browser default yellow) */
.ss-gs-panel mark{
  background:rgba(212,160,74,.25);
  color:#0d2a26;
  padding:0 2px;border-radius:3px;
  font-weight:700;
}

/* Avatar gradient variants (users) */
.ss-gs-av-1{background:linear-gradient(135deg,#1f6359,#2d8074)}
.ss-gs-av-2{background:linear-gradient(135deg,#5a9bd8,#3a6a8a)}
.ss-gs-av-3{background:linear-gradient(135deg,#7a4f9e,#9268b3)}
.ss-gs-av-4{background:linear-gradient(135deg,#d96b5a,#a04863)}
.ss-gs-av-5{background:linear-gradient(135deg,#3d8b6b,#5cad9b)}

/* Type icon gradient variants (keys match backend `type` values) */
.ss-gs-icon-wiki    {background:linear-gradient(135deg,#5a9bd8,#3a6a8a)}
.ss-gs-icon-document{background:linear-gradient(135deg,#7a4f9e,#5e3a78)}
.ss-gs-icon-annonce {background:linear-gradient(135deg,#d4a04a,#c97a2a)}
.ss-gs-icon-resident{background:linear-gradient(135deg,#5cad9b,#3d8b6b)}
.ss-gs-icon-contact {background:linear-gradient(135deg,#5cad9b,#3d8b6b)}
.ss-gs-icon-page    {background:linear-gradient(135deg,#324e4a,#0d2a26)}
.ss-gs-icon-default {background:linear-gradient(135deg,#324e4a,#0d2a26)}

/* Status dot (online indicator overlaid on avatar) */
.ss-gs-status{
  position:absolute;bottom:-2px;right:-2px;
  width:9px;height:9px;border-radius:50%;
  background:#3d8b6b;border:2px solid #fff;
}

/* Bar focus glow (slightly stronger than default ring) */
.ss-gs-bar:focus-within{
  background:#fff;
  border-color:#7ab5ab;
  box-shadow:0 0 0 4px rgba(45,128,116,.10),0 4px 16px -4px rgba(13,42,38,.08);
}

/* has-value: hide kbd hint, show clear */
.ss-gs-bar .ss-gs-clear{display:none}
.ss-gs-bar.has-value .ss-gs-kbd{display:none}
.ss-gs-bar.has-value .ss-gs-clear{display:flex}
</style>
