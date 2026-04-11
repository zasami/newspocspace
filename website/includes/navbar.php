<?php
// Determine active page
$wsCurrentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$wsIsHome = ($wsCurrentPage === 'index');
function wsNavActive($page) {
    global $wsCurrentPage;
    return $wsCurrentPage === $page ? ' active' : '';
}
function wsNavHref($target) {
    global $wsIsHome;
    // For anchor links, prefix with /spocspace/website/ if not on home
    if (str_starts_with($target, '#')) {
        return $wsIsHome ? $target : '/spocspace/website/' . $target;
    }
    return $target;
}
?>
<nav class="ws-nav" id="wsNav">
  <div class="container">
    <div class="ws-nav-inner">
      <a href="/spocspace/website/" class="ws-logo">
        <img src="/spocspace/website/EMS-Terrassire-SA-logo-web-1920w.webp" alt="E.M.S. La Terrassière SA" class="ws-logo-img">
      </a>
      <button class="ws-nav-toggle" id="wsNavToggle" aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <ul class="ws-nav-links" id="wsNavLinks">
        <li><a href="<?= wsNavHref('#hero') ?>"<?= wsNavActive('index') ? ' class="active"' : '' ?>>Accueil</a></li>
        <li><a href="<?= wsNavHref('#about') ?>">Notre mission</a></li>
        <li><a href="<?= wsNavHref('#services') ?>">Nos soins</a></li>
        <li><a href="<?= wsNavHref('#team') ?>">Équipe</a></li>
        <li><a href="/spocspace/website/admissions.php" class="<?= wsNavActive('admissions') ?>">Admissions</a></li>
        <li><a href="/spocspace/website/actualites.php" class="<?= wsNavActive('actualites') ?>">Actualités</a></li>
        <li><a href="/spocspace/website/recrutement.php" class="<?= wsNavActive('recrutement') ?>">Emploi</a></li>
        <li><a href="/spocspace/website/famille.php" class="<?= wsNavActive('famille') ?>">Famille</a></li>
        <li><a href="<?= wsNavHref('#contact') ?>">Contact</a></li>
        <li><a href="/spocspace/" class="ws-btn-nav"><i class="bi bi-box-arrow-in-right"></i> Collaborateur</a></li>
      </ul>
    </div>
  </div>
</nav>
<script>
(function(){
  // Mobile toggle
  var t = document.getElementById('wsNavToggle');
  var l = document.getElementById('wsNavLinks');
  if (t && l) t.addEventListener('click', function() {
    l.classList.toggle('open');
    t.querySelector('i').className = l.classList.contains('open') ? 'bi bi-x-lg' : 'bi bi-list';
  });
  // Scroll shrink logo
  var nav = document.getElementById('wsNav');
  if (nav) window.addEventListener('scroll', function() {
    nav.classList.toggle('scrolled', window.scrollY > 30);
  }, { passive: true });
})();
</script>
