<?php
/**
 * Spocspace Care — Layout commun de référence
 *
 * Fichier auto-suffisant utilisable de 3 façons :
 *  1) Visité directement (https://zkriva.com/newspocspace/_layout_tailwind.php)
 *     pour prévisualiser le design system Spocspace Care en action.
 *  2) Copié-collé comme point de départ pour migrer une page existante.
 *  3) Référencé visuellement quand on rebuild une vue dans le shell SPA.
 *
 * Règles à suivre :
 *  - Couleurs : teal-* + ink + muted + line + surface + ok/warn/danger/info + sec-*
 *  - Polices : font-display (titres), font-body (corps), font-mono (chiffres)
 *  - Icônes : SVG inline avec stroke="currentColor"
 *  - Pas de Bootstrap, pas de Font Awesome
 */
require_once __DIR__ . '/init.php';

// CSP nonce pour Tailwind CDN + scripts inline
$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self';");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Layout Tailwind — Spocspace Care (référence)</title>

<?php include __DIR__ . '/tailwind-config.php'; ?>
</head>
<body class="font-body bg-bg text-ink-2 antialiased min-h-screen">

<div class="flex">

  <!-- ============================================================ -->
  <!-- SIDEBAR — gradient teal foncé                                 -->
  <!-- ============================================================ -->
  <aside class="w-60 bg-grad-sidebar text-[#cfe0db] p-5 sticky top-0 h-screen flex flex-col gap-7">

    <!-- Brand -->
    <a href="#" class="flex items-center gap-3 group">
      <div class="w-10 h-10 rounded-xl bg-grad-mark flex items-center justify-center shadow-sp-md">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2L2 7l10 5 10-5-10-5z"/>
          <path d="M2 17l10 5 10-5"/>
          <path d="M2 12l10 5 10-5"/>
        </svg>
      </div>
      <div class="flex flex-col">
        <span class="font-display text-lg font-semibold text-white tracking-tight">Spocspace</span>
        <span class="text-xs text-[#7ab5ab] -mt-0.5">EMS Genève</span>
      </div>
    </a>

    <!-- Navigation -->
    <nav class="flex-1 flex flex-col gap-1 -mx-2 overflow-y-auto">
      <p class="px-3 mt-2 mb-1 text-[10px] font-semibold uppercase tracking-widest text-[#7ab5ab]">Pilotage</p>

      <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-teal-700/60 text-white text-sm font-medium">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Tableau de bord
      </a>

      <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-teal-700/40 text-sm transition-colors">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Planning
      </a>

      <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-teal-700/40 text-sm transition-colors">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Collaborateurs
      </a>

      <p class="px-3 mt-5 mb-1 text-[10px] font-semibold uppercase tracking-widest text-[#7ab5ab]">Care</p>

      <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-teal-700/40 text-sm transition-colors">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.29 1.51 4.04 3 5.5l7 7z"/></svg>
        Résidents
      </a>

      <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-teal-700/40 text-sm transition-colors">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Cartographie compétences
      </a>
    </nav>

    <!-- Footer sidebar -->
    <div class="text-xs text-[#7ab5ab] border-t border-white/10 pt-4">
      <div class="font-mono">v<?= defined('APP_VERSION') ? APP_VERSION : '0.0' ?></div>
      <div class="mt-1">SpocSpace · Genève</div>
    </div>
  </aside>

  <!-- ============================================================ -->
  <!-- MAIN — topbar + contenu                                       -->
  <!-- ============================================================ -->
  <main class="flex-1 min-h-screen">

    <!-- TOPBAR -->
    <header class="bg-surface border-b border-line h-16 px-6 flex items-center justify-between sticky top-0 z-10 backdrop-blur supports-[backdrop-filter]:bg-surface/80">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm">
        <a href="#" class="text-muted hover:text-teal-600">Pilotage</a>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-line-3"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="font-medium text-ink">Tableau de bord</span>
      </div>

      <!-- Actions topbar -->
      <div class="flex items-center gap-3">

        <!-- Search -->
        <div class="relative">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" placeholder="Rechercher…" class="bg-surface-3 border border-line pl-10 pr-3 py-1.5 rounded-lg text-sm w-64 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition">
        </div>

        <!-- Notif -->
        <button class="relative p-2 rounded-lg hover:bg-surface-3 transition-colors text-muted hover:text-teal-600">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <span class="absolute top-1 right-1 w-2 h-2 bg-warn rounded-full"></span>
        </button>

        <!-- Avatar -->
        <div class="flex items-center gap-2 pl-3 ml-1 border-l border-line">
          <div class="w-9 h-9 rounded-full bg-grad-mark flex items-center justify-center text-white font-display font-semibold text-sm">SZ</div>
          <div class="text-xs leading-tight">
            <div class="font-medium text-ink">Sami Z.</div>
            <div class="text-muted-2">Direction</div>
          </div>
        </div>
      </div>
    </header>

    <!-- CONTENU -->
    <div class="p-8 max-w-7xl mx-auto">

      <!-- Page header -->
      <div class="flex items-end justify-between mb-8">
        <div>
          <h1 class="font-display text-3xl font-semibold text-ink tracking-tight">Tableau de bord</h1>
          <p class="text-muted mt-1">Vue d'ensemble — cartographie des compétences et alertes formation</p>
        </div>
        <button class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nouvelle entrée
        </button>
      </div>

      <!-- Stats grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">

        <!-- Stat card 1 -->
        <div class="bg-surface border border-line rounded-xl p-5 shadow-sp-sm">
          <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted font-semibold">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-sec-soins"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.29 1.51 4.04 3 5.5l7 7z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Soins
          </div>
          <div class="font-mono tabular-nums text-3xl font-semibold text-ink mt-2">42</div>
          <div class="text-xs text-muted mt-1">collaborateurs · ASSC + ASA + AFP</div>
        </div>

        <!-- Stat card 2 -->
        <div class="bg-surface border border-line rounded-xl p-5 shadow-sp-sm">
          <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted font-semibold">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-sec-hotel" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Hôtellerie
          </div>
          <div class="font-mono tabular-nums text-3xl font-semibold text-ink mt-2">18</div>
          <div class="text-xs text-muted mt-1">cuisine + lingerie + intendance</div>
        </div>

        <!-- Stat card 3 — alerte -->
        <div class="bg-warn-bg border border-warn-line rounded-xl p-5 shadow-sp-sm">
          <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-warn font-semibold">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            BLS-AED expirant
          </div>
          <div class="font-mono tabular-nums text-3xl font-semibold text-ink mt-2">7</div>
          <div class="text-xs text-warn mt-1">à renouveler avant 30 j</div>
        </div>

        <!-- Stat card 4 — ok -->
        <div class="bg-ok-bg border border-ok-line rounded-xl p-5 shadow-sp-sm">
          <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-ok font-semibold">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            HPCI conforme
          </div>
          <div class="font-mono tabular-nums text-3xl font-semibold text-ink mt-2">100<span class="text-base text-muted-2">%</span></div>
          <div class="text-xs text-ok mt-1">tous les référent·e·s à jour</div>
        </div>
      </div>

      <!-- Card pleine -->
      <div class="bg-surface border border-line rounded-xl shadow-sp-sm overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-line bg-gradient-to-b from-surface-2 to-surface flex items-center justify-between">
          <h3 class="font-display text-lg font-semibold text-ink">Cartographie compétences — Soins</h3>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 bg-ok-bg text-ok text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded">
              <span class="w-1.5 h-1.5 rounded-full bg-ok"></span>
              Conforme
            </span>
            <span class="inline-flex items-center gap-1.5 bg-warn-bg text-warn text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded">
              À renouveler
            </span>
            <span class="inline-flex items-center gap-1.5 bg-danger-bg text-danger text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded">
              Manquant
            </span>
          </div>
        </div>
        <div class="p-5">
          <p class="text-sm text-ink-3 leading-relaxed">
            Référent·e·s par module — actes délégués, BPSD, INC, démences. Survolez une cellule pour voir le détail
            par collaborateur. Les <code>compétences requises</code> selon Fegems sont signalées par un trait teal.
          </p>

          <!-- Progress example -->
          <div class="mt-4 space-y-3">
            <div>
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="font-medium text-ink-2">BLS-AED (référent·e)</span>
                <span class="font-mono text-muted">8/10</span>
              </div>
              <div class="h-2 bg-line rounded-full overflow-hidden">
                <div class="h-full bg-grad-progress rounded-full" style="width:80%"></div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="font-medium text-ink-2">HPCI</span>
                <span class="font-mono text-muted">10/10</span>
              </div>
              <div class="h-2 bg-line rounded-full overflow-hidden">
                <div class="h-full bg-grad-progress rounded-full" style="width:100%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Boutons examples -->
      <div class="bg-surface border border-line rounded-xl shadow-sp-sm p-5">
        <h3 class="font-display text-base font-semibold text-ink mb-3">Composants de référence</h3>

        <div class="flex flex-wrap items-center gap-3">
          <button class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Bouton primaire
          </button>

          <button class="inline-flex items-center gap-2 bg-surface border border-line hover:border-teal-300 hover:text-teal-600 text-ink-2 px-4 py-2 rounded-lg text-sm font-medium transition-all">
            Bouton secondaire
          </button>

          <button class="inline-flex items-center gap-2 bg-danger-bg border border-danger-line hover:bg-danger hover:text-white text-danger px-4 py-2 rounded-lg text-sm font-medium transition-all">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            Action destructive
          </button>
        </div>
      </div>

    </div>
  </main>

</div>

</body>
</html>
