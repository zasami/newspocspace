<div class="login-page">
  <div class="login-card">
    <div class="login-header">
      <img src="/zerdatime/logo.png" alt="zerdaTime" class="login-logo-img">
      <h1>Bienvenue</h1>
      <p>Connectez-vous à zerdaTime</p>
    </div>
    <div class="login-error" id="loginError"></div>
    <form id="loginForm" autocomplete="on">
      <div class="login-field">
        <label for="loginEmail">Adresse email</label>
        <input type="email" id="loginEmail" placeholder="prenom.nom@terrassiere.ch" required autocomplete="email">
      </div>
      <div class="login-field">
        <label for="loginPassword">Mot de passe</label>
        <input type="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="login-submit" id="loginBtn">
        Continuer
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
    </form>
    <div class="login-footer">
      <a href="#" id="forgotPasswordLink">Mot de passe oublié ?</a>
    </div>
    <div class="login-brand">zerdaTime — Genève</div>
  </div>
</div>
