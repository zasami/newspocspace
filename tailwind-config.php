<?php
/**
 * SpocSpace Care — Tailwind global setup
 *
 * Inclus depuis chaque shell PHP (index.php, admin/index.php, care/index.php) :
 *   <?php include __DIR__ . '/tailwind-config.php'; ?>      (depuis la racine)
 *   <?php include __DIR__ . '/../tailwind-config.php'; ?>   (depuis admin/ ou care/)
 *
 * Charge :
 *  - Google Fonts (Fraunces / Outfit / JetBrains Mono)
 *  - Tailwind Play CDN
 *  - Configuration du design system « Spocspace Care »
 *  - Globals @layer base
 *
 * Le partial s'appuie sur la variable PHP $cspNonce définie dans le shell hôte.
 */
$_tw_nonce = $cspNonce ?? '';
?>
<!-- Google Fonts: Fraunces (display) + Outfit (body) + JetBrains Mono (mono) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Tailwind CSS — Play CDN (mode prototypage, sans build) -->
<script nonce="<?= htmlspecialchars($_tw_nonce, ENT_QUOTES) ?>" src="https://cdn.tailwindcss.com"></script>

<!-- Configuration Spocspace Care -->
<script nonce="<?= htmlspecialchars($_tw_nonce, ENT_QUOTES) ?>">
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          // === BRAND TEAL (couleur principale) ===
          teal: {
            50:  '#ecf5f3',
            100: '#d2e7e2',
            200: '#a8d1c8',
            300: '#7ab5ab',
            400: '#4d9a8e',
            500: '#2d8074',
            600: '#1f6359',  // PRIMAIRE Spocspace
            700: '#164a42',
            800: '#0f3631',
            900: '#0d2a26',
          },
          // === TEXTE & SURFACES ===
          ink: {
            DEFAULT: '#0d2a26',  // titres, texte fort
            2: '#324e4a',        // texte courant
            3: '#4a6661',        // texte secondaire
          },
          muted: {
            DEFAULT: '#6b8783',
            2: '#8aa39f',
          },
          line: {
            DEFAULT: '#e3ebe8',
            2: '#d4ddda',
            3: '#c4d0cc',
          },
          surface: {
            DEFAULT: '#ffffff',
            2: '#fafbfa',
            3: '#f3f6f5',
          },
          bg: '#f5f7f5',

          // === STATUTS SÉMANTIQUES ===
          ok: {
            DEFAULT: '#3d8b6b',
            bg: '#e3f0ea',
            line: '#b8d4c5',
          },
          warn: {
            DEFAULT: '#c97a2a',
            bg: '#fbf0e1',
            line: '#e8c897',
          },
          danger: {
            DEFAULT: '#b8443a',
            bg: '#f7e3e0',
            line: '#e6b8b0',
          },
          info: {
            DEFAULT: '#3a6a8a',
            bg: '#e2ecf2',
            line: '#b5cad8',
          },

          // === SECTEURS EMS Fegems ===
          'sec-soins':  { DEFAULT: '#1f6359', bg: '#ecf5f3' },
          'sec-hotel':  { DEFAULT: '#8a5a1a', bg: '#fff3e3' },
          'sec-anim':   { DEFAULT: '#5e3a78', bg: '#f0e8f5' },
          'sec-int':    { DEFAULT: '#3a5a2d', bg: '#e8f0e6' },
          'sec-tech':   { DEFAULT: '#2d4a6b', bg: '#e6ecf2' },
          'sec-admin':  { DEFAULT: '#6b5a1f', bg: '#f0eee3' },
          'sec-mgmt':   { DEFAULT: '#8a3a30', bg: '#fde8e6' },

          // === SIDEBAR (textes & nuances dégradé teal foncé) ===
          'sb-text':       '#a8c4be',  // items inactifs
          'sb-text-hover': '#e8f1ee',  // items au hover
          'sb-section':    '#5d8077',  // titres de sections (Espace, RH, …)
          'sb-sub':        '#7ea69c',  // sous-titre logo (EMS PLATFORM)
          'sb-muted':      '#9bbab2',  // sous-texte EMS card
        },
        fontFamily: {
          display: ['Fraunces', 'Georgia', 'serif'],
          body:    ['Outfit', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
          mono:    ['JetBrains Mono', 'Menlo', 'monospace'],
        },
        boxShadow: {
          'sp-sm': '0 1px 2px rgba(13,42,38,0.04), 0 1px 1px rgba(13,42,38,0.03)',
          'sp':    '0 4px 16px -4px rgba(13,42,38,0.08), 0 2px 4px rgba(13,42,38,0.04)',
          'sp-md': '0 8px 24px -6px rgba(13,42,38,0.12), 0 3px 8px rgba(13,42,38,0.05)',
          'sp-lg': '0 16px 48px -12px rgba(13,42,38,0.18), 0 4px 12px rgba(13,42,38,0.06)',
          // Mark logo glow vert clair
          'mark':  '0 4px 12px rgba(125,211,168,0.3)',
        },
        backgroundImage: {
          'grad-hero':     'linear-gradient(135deg,#164a42 0%, #1f6359 50%, #2d8074 100%)',
          'grad-sidebar':  'linear-gradient(180deg,#0d2a26 0%, #164a42 100%)',
          'grad-mark':     'linear-gradient(135deg,#3da896,#7dd3a8)',
          'grad-progress': 'linear-gradient(90deg,#2d8074,#5cad9b)',
          // Alias compatibles avec la spec sidebar (bg-sidebar-grad / bg-mark-grad)
          'sidebar-grad':  'linear-gradient(180deg,#0d2a26 0%, #164a42 100%)',
          'mark-grad':     'linear-gradient(135deg,#3da896,#7dd3a8)',
        },
      }
    }
  };
</script>

<!-- Globals Spocspace (typographie + base) -->
<style type="text/tailwindcss">
  @layer base {
    body {
      @apply font-body bg-bg text-ink-2 antialiased;
    }
    h1, h2, h3, h4, h5, h6 {
      @apply font-display tracking-tight font-semibold text-ink;
    }
    a {
      @apply text-teal-600 hover:text-teal-700 transition-colors;
    }
    code {
      @apply font-mono text-sm bg-surface-3 px-1.5 py-0.5 rounded text-teal-700;
    }
  }
</style>
