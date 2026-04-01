<?php
$totalResidents = (int) Db::getOne("SELECT COUNT(*) FROM residents WHERE is_active = 1");
$marquagesEnCours = (int) Db::getOne("SELECT COUNT(*) FROM marquages WHERE statut = 'en_cours'");
$marquagesAujourdhui = (int) Db::getOne("SELECT COUNT(*) FROM marquages WHERE DATE(created_at) = CURDATE()");
$totalChambres = (int) Db::getOne("SELECT COUNT(DISTINCT chambre) FROM residents WHERE is_active = 1 AND chambre IS NOT NULL");
?>
<style>
.care-welcome { margin-bottom: 24px; }
.care-welcome h3 { font-weight: 700; margin-bottom: 4px; }
.care-welcome p { color: var(--cl-text-muted); font-size: .92rem; }

.care-quick-links { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-top: 24px; }
.care-quick-link {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 20px; border-radius: 14px;
    background: var(--cl-surface, #fff); border: 1.5px solid var(--cl-border-light, #F0EDE8);
    cursor: pointer; transition: all .2s; text-decoration: none; color: inherit;
}
.care-quick-link:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.06); border-color: #bcd2cb; }
.care-ql-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.care-ql-label { font-weight: 600; font-size: .92rem; }
.care-ql-desc { font-size: .72rem; color: var(--cl-text-muted); margin-top: 2px; }
</style>

<div class="care-welcome">
  <h3><i class="bi bi-heart-pulse" style="color:#2d4a43"></i> Bienvenue sur zerdaCare</h3>
  <p>Module soins & vie quotidienne — <?= h($user['prenom']) ?>, <?= date('l j F Y') ?></p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-person-badge"></i></div>
      <div><div class="stat-value"><?= $totalResidents ?></div><div class="stat-label">Résidents actifs</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-tags"></i></div>
      <div><div class="stat-value"><?= $marquagesEnCours ?></div><div class="stat-label">Marquages en cours</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-calendar-check"></i></div>
      <div><div class="stat-value"><?= $marquagesAujourdhui ?></div><div class="stat-label">Marquages aujourd'hui</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#D0C4D8;color:#5B4B6B"><i class="bi bi-door-open"></i></div>
      <div><div class="stat-value"><?= $totalChambres ?></div><div class="stat-label">Chambres occupées</div></div>
    </div>
  </div>
</div>

<!-- Quick links -->
<h6 class="fw-bold mb-3"><i class="bi bi-grid" style="color:#2d4a43"></i> Accès rapide</h6>
<div class="care-quick-links">
  <a href="<?= care_url('residents') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-person-badge"></i></div>
    <div><div class="care-ql-label">Résidents</div><div class="care-ql-desc">Gérer les résidents</div></div>
  </a>
  <a href="<?= care_url('marquage') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#D4C4A8;color:#6B5B3E"><i class="bi bi-tags"></i></div>
    <div><div class="care-ql-label">Marquage Lingerie</div><div class="care-ql-desc">Suivi des vêtements</div></div>
  </a>
  <a href="<?= care_url('famille') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#E2B8AE;color:#7B3B2C"><i class="bi bi-house-heart"></i></div>
    <div><div class="care-ql-label">Espace Famille</div><div class="care-ql-desc">Activités & galerie</div></div>
  </a>
  <a href="<?= care_url('menus') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#B8C9D4;color:#3B4F6B"><i class="bi bi-egg-fried"></i></div>
    <div><div class="care-ql-label">Menus</div><div class="care-ql-desc">Menus de la semaine</div></div>
  </a>
  <a href="<?= care_url('reservations') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#D0C4D8;color:#5B4B6B"><i class="bi bi-calendar-check"></i></div>
    <div><div class="care-ql-label">Réservations</div><div class="care-ql-desc">Réservations repas</div></div>
  </a>
  <a href="<?= care_url('protection') ?>" class="care-quick-link">
    <div class="care-ql-icon" style="background:#bcd2cb;color:#2d4a43"><i class="bi bi-shield-check"></i></div>
    <div><div class="care-ql-label">Suivi Protection</div><div class="care-ql-desc">Mesures de protection</div></div>
  </a>
</div>
