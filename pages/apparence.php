<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$userId = $_SESSION['ss_user']['id'];
$current = Db::getOne("SELECT theme_preference FROM users WHERE id = ?", [$userId]) ?: 'default';

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
<div class="page-wrap">
  <?= render_page_header('Apparence', 'bi-palette', 'profile', 'Mon profil') ?>

  <div class="card mb-3">
    <div class="card-body">
      <h5>Thème de l'interface</h5>
      <p class="text-muted small mb-3">
        Choisissez le thème visuel appliqué à votre espace. Le changement est immédiat
        et enregistré sur votre compte.
      </p>

      <div class="row g-3" id="appThemesGrid">
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

      <div class="text-muted small mt-3" id="appThemeSaveState"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5>Aperçu</h5>
      <p class="text-muted small mb-3">Quelques composants pour vérifier le rendu du thème.</p>
      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-primary btn-sm">Action principale</button>
        <button class="btn btn-outline-primary btn-sm">Secondaire</button>
        <button class="btn btn-outline-secondary btn-sm">Neutre</button>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <span class="badge text-bg-success">Validé</span>
        <span class="badge text-bg-warning">En attente</span>
        <span class="badge text-bg-danger">Refusé</span>
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
.theme-card.is-active { border-color: var(--cl-teal, #2d4a43); background: var(--cl-teal-50, #e8f0ee); }
.theme-card.is-active .theme-check { display: flex; }
.theme-preview { display: flex; gap: 4px; margin-bottom: 10px; }
.theme-preview span { flex: 1; height: 28px; border-radius: 6px; border: 1px solid rgba(0,0,0,.08); }
.theme-name { font-size: .95rem; font-weight: 700; color: var(--cl-text, #2d4a43); }
.theme-desc { font-size: .78rem; color: var(--cl-text-muted, #7A6E58); margin-top: 2px; line-height: 1.4; }
.theme-badge {
  position: absolute; top: 10px; right: 10px;
  background: var(--cl-teal, #2d4a43); color: #fff;
  font-size: .65rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
  padding: 3px 8px; border-radius: 999px;
}
.theme-check {
  display: none; position: absolute; bottom: 10px; right: 10px;
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--cl-teal, #2d4a43); color: #fff;
  align-items: center; justify-content: center; font-size: .82rem;
}
</style>
