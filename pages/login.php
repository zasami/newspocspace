<!--
  pages/login.php — Page de connexion (Tailwind / Spocspace Care)
  IDs préservés (utilisés par assets/js/modules/auth.js) :
    #loginForm, #loginEmail, #loginPassword, #loginBtn, #loginError,
    #forgotPasswordLink, .pwd-eye[data-target]
-->
<div class="min-h-screen w-full flex flex-col lg:flex-row bg-bg">

  <!-- ================================================================ -->
  <!-- PANNEAU BRAND (gauche desktop / haut mobile)                     -->
  <!-- ================================================================ -->
  <aside class="relative bg-sidebar-grad text-sb-text overflow-hidden lg:w-[42%] lg:min-h-screen p-8 lg:p-12 flex flex-col">

    <!-- Décoration : blobs radiaux discrets -->
    <div aria-hidden="true" class="absolute inset-0 pointer-events-none">
      <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-[#3da896]/[0.08] blur-3xl"></div>
      <div class="absolute bottom-[-100px] right-[-50px] w-80 h-80 rounded-full bg-[#7dd3a8]/[0.06] blur-3xl"></div>
    </div>

    <!-- Logo + brand -->
    <div class="relative flex items-center gap-3">
      <div class="w-11 h-11 rounded-[10px] bg-mark-grad grid place-items-center font-display font-bold text-teal-900 text-xl shadow-mark">
        S
      </div>
      <div>
        <div class="font-display text-2xl font-semibold text-white tracking-[-0.02em] leading-tight">Spocspace</div>
        <div class="text-[10.5px] text-sb-sub tracking-[0.14em] uppercase mt-0.5 font-medium">EMS Platform</div>
      </div>
    </div>

    <!-- Hero / baseline -->
    <div class="relative mt-12 lg:mt-20 max-w-md hidden lg:block">
      <h1 class="font-display text-4xl xl:text-5xl text-white font-medium leading-[1.15] tracking-[-0.01em]">
        Pensée pour les soignant·e·s,<br>
        <span class="text-[#7dd3a8]">aimée par les directions.</span>
      </h1>
      <p class="mt-6 text-[15px] text-sb-text leading-relaxed">
        Plateforme de gestion EMS conçue à Genève, pour les établissements affiliés
        Fegems&nbsp;: planning, cartographie compétences, désirs, vacances, formation continue.
      </p>
    </div>

    <!-- Trust badges -->
    <div class="relative mt-auto pt-12 hidden lg:flex flex-wrap gap-x-6 gap-y-3 text-[12px]">
      <div class="flex items-center gap-2 text-sb-text">
        <svg class="w-4 h-4 opacity-90 shrink-0 text-[#7dd3a8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        Hébergement Suisse · LPD
      </div>
      <div class="flex items-center gap-2 text-sb-text">
        <svg class="w-4 h-4 opacity-90 shrink-0 text-[#7dd3a8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M9 17h.01"/><path d="M15 9h.01"/><path d="M15 13h.01"/><path d="M15 17h.01"/></svg>
        Fegems compatible
      </div>
      <div class="flex items-center gap-2 text-sb-text">
        <svg class="w-4 h-4 opacity-90 shrink-0 text-[#7dd3a8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
        Genève · Plan-les-Ouates
      </div>
    </div>
  </aside>

  <!-- ================================================================ -->
  <!-- PANNEAU FORMULAIRE                                               -->
  <!-- ================================================================ -->
  <main class="flex-1 flex flex-col justify-center items-center px-6 py-10 lg:py-16">
    <div class="w-full max-w-[400px]">

      <!-- Header form -->
      <div class="mb-8">
        <h2 class="font-display text-3xl font-semibold text-ink tracking-[-0.01em]">Bienvenue</h2>
        <p class="text-ink-3 mt-2 text-[15px]">Connectez-vous à votre espace.</p>
      </div>

      <!-- Erreur (auth.js remplit #loginError) -->
      <div id="loginError"
           class="hidden mb-5 px-4 py-3 rounded-lg bg-danger-bg border border-danger-line text-danger text-[13.5px] leading-relaxed">
      </div>

      <form id="loginForm" autocomplete="on" class="flex flex-col gap-5">

        <!-- Email -->
        <div class="flex flex-col gap-1.5">
          <label for="loginEmail" class="text-[13px] font-medium text-ink-2">Adresse email</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m22 7-10 5L2 7"/>
            </svg>
            <input
              type="email"
              id="loginEmail"
              placeholder="prenom.nom@terrassiere.ch"
              required
              autocomplete="email"
              class="w-full pl-10 pr-3 py-2.5 bg-surface border border-line rounded-lg text-[14.5px] text-ink placeholder:text-muted-2 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition">
          </div>
        </div>

        <!-- Mot de passe -->
        <div class="flex flex-col gap-1.5">
          <div class="flex items-center justify-between">
            <label for="loginPassword" class="text-[13px] font-medium text-ink-2">Mot de passe</label>
            <a href="#" id="forgotPasswordLink" class="text-[12.5px] text-teal-600 hover:text-teal-700 transition-colors">Mot de passe oublié&nbsp;?</a>
          </div>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            <input
              type="password"
              id="loginPassword"
              placeholder="••••••••"
              required
              autocomplete="current-password"
              class="w-full pl-10 pr-11 py-2.5 bg-surface border border-line rounded-lg text-[14.5px] text-ink placeholder:text-muted-2 focus:outline-none focus:border-teal-300 focus:ring-2 focus:ring-teal-100 transition">
            <button
              type="button"
              class="pwd-eye absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded text-muted-2 hover:text-teal-600 hover:bg-surface-3 transition-colors"
              data-target="loginPassword"
              aria-label="Afficher / masquer le mot de passe">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          id="loginBtn"
          class="mt-2 inline-flex items-center justify-center gap-2 bg-teal-600 hover:bg-teal-700 active:bg-teal-800 text-white font-medium px-4 py-3 rounded-lg text-[14.5px] shadow-sp-sm hover:shadow-sp transition-all disabled:opacity-60 disabled:cursor-not-allowed">
          Continuer
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"/>
            <path d="m12 5 7 7-7 7"/>
          </svg>
        </button>
      </form>

      <!-- Footer page -->
      <div class="mt-12 flex items-center justify-between text-[11.5px] text-muted-2">
        <span>SpocSpace · Genève</span>
        <span class="font-mono">v<?= defined('APP_VERSION') ? APP_VERSION : '0.0' ?></span>
      </div>
    </div>
  </main>

</div>
