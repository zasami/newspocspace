<?php
$adminId = $_SESSION['ss_user']['id'] ?? '';
$current = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$adminId]) ?: 'default';

$themes = [
    'default' => [
        'name'    => 'Spoc Default',
        'desc'    => 'Le thème actuel — palette pastel cream/teal de SpocSpace',
        'preview' => ['#F7F5F2', '#bcd2cb', '#2d4a43', '#FFFFFF'],
    ],
    'sombre' => [
        'name'    => 'Spoc Sombre',
        'desc'    => 'Mode sombre · pour usage prolongé en soirée',
        'preview' => ['#0d2a26', '#164a42', '#7dd3a8', '#0f3631'],
    ],
    'care' => [
        'name'    => 'Spoc Care',
        'desc'    => 'Nouveau thème médico-social · teal & sauge · Fraunces',
        'preview' => ['#0d2a26', '#1f6359', '#7dd3a8', '#FFFFFF'],
        'badge'   => 'Nouveau',
    ],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-palette"></i> Apparence</h4>
  <span class="text-muted small">Préférence personnelle · enregistrée par utilisateur</span>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="card-title">Thème de l'interface</h5>
    <p class="text-muted small mb-3">
      Choisissez le thème visuel qui sera appliqué à toute votre interface SpocSpace
      (admin et espace collaborateur). Le changement est immédiat.
    </p>

    <div class="row g-3" id="themesGrid">
      <?php foreach ($themes as $key => $t): ?>
        <div class="col-md-4">
          <div class="theme-card <?= $current === $key ? 'is-active' : '' ?>" data-theme="<?= h($key) ?>">
            <?php if (!empty($t['badge'])): ?>
              <span class="theme-badge"><?= h($t['badge']) ?></span>
            <?php endif ?>
            <div class="theme-preview">
              <?php foreach ($t['preview'] as $c): ?>
                <span style="background:<?= h($c) ?>"></span>
              <?php endforeach ?>
            </div>
            <div class="theme-name"><?= h($t['name']) ?></div>
            <div class="theme-desc"><?= h($t['desc']) ?></div>
            <div class="theme-check"><i class="bi bi-check-lg"></i></div>
          </div>
        </div>
      <?php endforeach ?>
    </div>

    <div class="text-muted small mt-3" id="themeSaveState"></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h5 class="card-title">Aperçu rapide</h5>
    <p class="text-muted small mb-3">Quelques composants pour vérifier le rendu du thème.</p>

    <div class="d-flex gap-2 flex-wrap mb-3">
      <button class="btn btn-primary btn-sm">Primaire</button>
      <button class="btn btn-outline-primary btn-sm">Secondaire</button>
      <button class="btn btn-outline-secondary btn-sm">Neutre</button>
      <button class="btn btn-success btn-sm">Succès</button>
      <button class="btn btn-warning btn-sm">Attention</button>
      <button class="btn btn-danger btn-sm">Danger</button>
    </div>

    <div class="d-flex gap-2 flex-wrap mb-3">
      <span class="badge text-bg-success">Conforme</span>
      <span class="badge text-bg-warning">À renouveler</span>
      <span class="badge text-bg-danger">Expirée</span>
      <span class="badge text-bg-info">Info</span>
    </div>

    <div class="alert alert-info" role="alert">
      <i class="bi bi-info-circle"></i>
      Les boutons, badges, cards et modales adoptent automatiquement le thème choisi.
    </div>

    <div class="row g-3">
      <div class="col-sm-4">
        <div class="stat-card">
          <div class="stat-icon bg-teal"><i class="bi bi-mortarboard"></i></div>
          <div><div class="stat-value">42</div><div class="stat-label">Formations</div></div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="stat-card">
          <div class="stat-icon bg-green"><i class="bi bi-check-circle"></i></div>
          <div><div class="stat-value">87<small>%</small></div><div class="stat-label">Conformité</div></div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="stat-card">
          <div class="stat-icon bg-orange"><i class="bi bi-clock-history"></i></div>
          <div><div class="stat-value">12</div><div class="stat-label">À renouveler</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.theme-card {
  border: 2px solid var(--cl-border-light, #F0EDE8);
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  transition: all .18s ease;
  background: var(--cl-surface, #fff);
  position: relative;
  height: 100%;
}
.theme-card:hover {
  border-color: var(--cl-teal, #2d4a43);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.06);
}
.theme-card.is-active {
  border-color: var(--cl-teal, #2d4a43);
  background: var(--cl-teal-50, #e8f0ee);
}
.theme-card.is-active .theme-check {
  display: flex;
}
.theme-preview {
  display: flex;
  gap: 4px;
  margin-bottom: 10px;
}
.theme-preview span {
  flex: 1;
  height: 28px;
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,.08);
}
.theme-name {
  font-size: .95rem;
  font-weight: 700;
  color: var(--cl-text, #2d4a43);
}
.theme-desc {
  font-size: .78rem;
  color: var(--cl-text-muted, #7A6E58);
  margin-top: 2px;
  line-height: 1.4;
}
.theme-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  background: var(--cl-teal, #2d4a43);
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 999px;
}
.theme-check {
  display: none;
  position: absolute;
  bottom: 10px;
  right: 10px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--cl-teal, #2d4a43);
  color: #fff;
  align-items: center;
  justify-content: center;
  font-size: .82rem;
}
@keyframes savedPulse { 0%,100% { opacity: 1 } 50% { opacity: .5 } }
.is-saving { animation: savedPulse 1s infinite; }
</style>

<script<?= nonce() ?>>
(function () {
  const grid = document.getElementById('themesGrid');
  const state = document.getElementById('themeSaveState');

  grid.querySelectorAll('.theme-card').forEach(card => {
    card.addEventListener('click', async () => {
      const theme = card.dataset.theme;
      if (card.classList.contains('is-active')) return;

      state.textContent = 'Application…';
      state.className = 'small text-muted is-saving';

      try {
        const r = await adminApiPost('admin_save_apparence', { theme });
        if (!r.success) throw new Error(r.message || 'Erreur');

        // UI : marquer la carte sélectionnée
        grid.querySelectorAll('.theme-card').forEach(c => c.classList.remove('is-active'));
        card.classList.add('is-active');

        // Charger Google Fonts si on bascule sur care
        if (theme === 'care' && !document.querySelector('link[href*="Fraunces"]')) {
          const pre1 = document.createElement('link'); pre1.rel = 'preconnect'; pre1.href = 'https://fonts.googleapis.com';
          const pre2 = document.createElement('link'); pre2.rel = 'preconnect'; pre2.href = 'https://fonts.gstatic.com'; pre2.crossOrigin = 'anonymous';
          const css = document.createElement('link');
          css.rel = 'stylesheet';
          css.href = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap';
          document.head.append(pre1, pre2, css);
        }

        // Application immédiate désactivée temporairement (clean-slate) :
        // la préférence est sauvegardée en DB mais le body class reste fixé
        // sur 'theme-care' pour ne pas casser la structure Tailwind.
        // Quand le système de couleurs par thème sera prêt, ré-activer ces
        // 2 lignes (cf. admin/index.php pour le détail).
        // document.body.className = document.body.className.replace(/\btheme-\w+\b/g, '').trim();
        // document.body.classList.add('theme-' + theme);

        state.textContent = '✓ Préférence enregistrée (système de thèmes désactivé temporairement)';
        state.className = 'small text-success';
        setTimeout(() => { state.textContent = ''; }, 2200);
      } catch (e) {
        state.textContent = '⚠ ' + (e.message || 'Erreur');
        state.className = 'small text-danger';
      }
    });
  });
})();
</script>
