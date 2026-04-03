<?php
// Load SpocSpace DB to fetch dynamic menus + CMS sections
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/includes/cms.php';

// Load CMS sections
$cms = ws_load_sections('index');

// Get 4 weeks of menus (current week Monday → +27 days) for continuous carousel
$dt = new DateTime();
$dow = (int) $dt->format('N');
$wsMonday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
$wsEnd = (clone $wsMonday)->modify('+27 days');
$wsMenus = Db::fetchAll(
    "SELECT date_jour, repas, entree, plat, salade, accompagnement, dessert, remarques
     FROM menus WHERE date_jour BETWEEN ? AND ? ORDER BY date_jour ASC, repas ASC",
    [$wsMonday->format('Y-m-d'), $wsEnd->format('Y-m-d')]
);
$wsMenusByKey = [];
foreach ($wsMenus as $m) {
    $wsMenusByKey[$m['date_jour'] . '_' . $m['repas']] = $m;
}
$wsStartDate = $dt->format('Y-m-d');
$wsDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E.M.S. La Terrassière SA — Établissement Médico-Social, Genève</title>
<meta name="description" content="EMS La Terrassière SA, établissement médico-social au centre de Genève. Soins de qualité, accompagnement bienveillant pour personnes âgées dans un cadre chaleureux.">
<meta name="keywords" content="EMS, Terrassière, Genève, maison de retraite, personnes âgées, soins, accompagnement, médico-social">
<meta name="theme-color" content="#2E7D32">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌿</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/vendor/bootstrap-icons.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="../assets/css/vendor/bootstrap-icons.min.css"></noscript>
<link rel="stylesheet" href="assets/css/website.css?v=<?= filemtime(__DIR__ . '/assets/css/website.css') ?>">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══ HERO WITH VIDEO BACKGROUND ═══ -->
<?php if (ws_visible($cms, 'hero')):
    $hero = ws_content($cms, 'hero');
    $heroStats = $hero['stats'] ?? [];
    $heroVideos = $hero['videos'] ?? ['assets/video/6096_medium.mp4'];
    $ctaPrimary = $hero['cta_primary'] ?? ['text'=>'Nous contacter','href'=>'#contact','icon'=>'bi-telephone'];
    $ctaSecondary = $hero['cta_secondary'] ?? ['text'=>'Découvrir','href'=>'#services','icon'=>'bi-arrow-down'];
?>
<section class="ws-hero ws-hero-video" id="hero">
  <div class="ws-hero-video-wrap">
    <video class="ws-hero-vid" autoplay muted loop playsinline preload="metadata"
           data-playlist='<?= json_encode($heroVideos) ?>'>
      <source src="<?= h($heroVideos[0] ?? '') ?>" type="video/mp4">
    </video>
    <div class="ws-hero-video-overlay"></div>
  </div>
  <div class="container">
    <div class="ws-hero-content">
      <img src="EMS-Terrassire-SA-logo-web-1920w.webp" alt="E.M.S. La Terrassière SA" class="ws-hero-logo" fetchpriority="high" width="400" height="131">
      <h1 class="ws-hero-title"><?= ws_get($cms, 'hero', 'title') ?></h1>
      <p class="ws-hero-desc"><?= h(ws_get($cms, 'hero', 'subtitle')) ?></p>
      <div class="ws-hero-actions">
        <a href="<?= h($ctaPrimary['href'] ?? '#contact') ?>" class="ws-btn ws-btn-primary"><i class="bi <?= h($ctaPrimary['icon'] ?? '') ?>"></i> <?= h($ctaPrimary['text'] ?? '') ?></a>
        <a href="<?= h($ctaSecondary['href'] ?? '#services') ?>" class="ws-btn ws-btn-outline-light"><i class="bi <?= h($ctaSecondary['icon'] ?? '') ?>"></i> <?= h($ctaSecondary['text'] ?? '') ?></a>
      </div>
      <?php if ($heroStats): ?>
      <div class="ws-hero-stats">
        <?php foreach ($heroStats as $st): ?>
        <div class="ws-stat">
          <div class="ws-stat-num"><?= h($st['num'] ?? '') ?></div>
          <div class="ws-stat-label"><?= h($st['label'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <!-- Scroll indicator -->
  <div class="ws-scroll-indicator">
    <div class="ws-scroll-mouse">
      <div class="ws-scroll-wheel"></div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ ABOUT / MISSION ═══ -->
<?php if (ws_visible($cms, 'about')):
    $aboutCards = ws_content($cms, 'about', 'cards') ?: [];
?>
<section class="ws-section" id="about">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi <?= h(ws_get($cms, 'about', 'badge_icon')) ?>"></i> <?= h(ws_get($cms, 'about', 'badge_text')) ?></div>
      <h2 class="ws-section-title"><?= ws_get($cms, 'about', 'title') ?></h2>
      <p class="ws-section-desc"><?= h(ws_get($cms, 'about', 'subtitle')) ?></p>
    </div>
    <div class="row g-4">
      <?= ws_render_cards($aboutCards) ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ VIDEO DIVIDER 1 ═══ -->
<?php if (ws_visible($cms, 'quote1')):
    $q1 = ws_content($cms, 'quote1');
?>
<section class="ws-video-divider">
  <video muted loop playsinline preload="none" class="ws-lazy-video" data-src="<?= h($q1['video'] ?? 'assets/video/229069_medium.mp4') ?>">
  </video>
  <div class="ws-video-divider-overlay"></div>
  <div class="ws-video-divider-content">
    <div class="ws-video-divider-badge"><i class="bi <?= h(ws_get($cms, 'quote1', 'badge_icon') ?: 'bi-quote') ?>"></i></div>
    <blockquote>« <?= h($q1['text'] ?? '') ?> »</blockquote>
  </div>
</section>
<?php endif; ?>

<!-- ═══ SERVICES ═══ -->
<?php if (ws_visible($cms, 'services')):
    $svcCards = ws_content($cms, 'services', 'cards') ?: [];
?>
<section class="ws-section ws-section-alt" id="services">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi <?= h(ws_get($cms, 'services', 'badge_icon')) ?>"></i> <?= h(ws_get($cms, 'services', 'badge_text')) ?></div>
      <h2 class="ws-section-title"><?= ws_get($cms, 'services', 'title') ?></h2>
    </div>
    <div class="row g-4">
      <?php foreach ($svcCards as $c): ?>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi <?= h($c['icon'] ?? '') ?>"></i></div>
          <div class="ws-service-body">
            <h4><?= h($c['title'] ?? '') ?></h4>
            <p><?= h($c['text'] ?? '') ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ VIE QUOTIDIENNE ═══ -->
<?php if (ws_visible($cms, 'life')):
    $lifeItems = ws_content($cms, 'life', 'items') ?: [];
?>
<section class="ws-section" id="life">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi <?= h(ws_get($cms, 'life', 'badge_icon')) ?>"></i> <?= h(ws_get($cms, 'life', 'badge_text')) ?></div>
      <h2 class="ws-section-title"><?= ws_get($cms, 'life', 'title') ?></h2>
      <p class="ws-section-desc"><?= h(ws_get($cms, 'life', 'subtitle')) ?></p>
    </div>
    <div class="ws-timeline">
      <?php foreach ($lifeItems as $item): ?>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time"><?= h($item['time'] ?? '') ?></div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5><?= h($item['title'] ?? '') ?></h5>
          <p><?= h($item['text'] ?? '') ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ VIDEO DIVIDER 2 ═══ -->
<?php if (ws_visible($cms, 'quote2')):
    $q2 = ws_content($cms, 'quote2');
?>
<section class="ws-video-divider">
  <video muted loop playsinline preload="none" class="ws-lazy-video" data-src="<?= h($q2['video'] ?? 'assets/video/229071_medium.mp4') ?>">
  </video>
  <div class="ws-video-divider-overlay"></div>
  <div class="ws-video-divider-content">
    <div class="ws-video-divider-badge"><i class="bi <?= h(ws_get($cms, 'quote2', 'badge_icon') ?: 'bi-heart-pulse') ?>"></i></div>
    <blockquote>« <?= h($q2['text'] ?? '') ?> »</blockquote>
  </div>
</section>
<?php endif; ?>

<!-- ═══ MENU DE LA SEMAINE — Carousel ═══ -->
<section class="ws-section" id="menu-semaine">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-egg-fried"></i> Restauration</div>
      <h2 class="ws-section-title">Menu de la <span class="ws-accent">semaine</span></h2>
      <p class="ws-section-desc">
        Des repas équilibrés, préparés sur place chaque jour avec des produits frais et de saison,
        adaptés aux besoins et préférences de chaque résident.
      </p>
    </div>
    <div class="ws-menu-week">
      <!-- Week nav -->
      <div class="ws-menu-nav">
        <button class="ws-menu-nav-btn" id="wsMenuPrev"><i class="bi bi-chevron-left"></i></button>
        <span class="ws-menu-nav-label" id="wsMenuWeekLabel"></span>
        <button class="ws-menu-nav-btn" id="wsMenuNext"><i class="bi bi-chevron-right"></i></button>
      </div>
      <!-- Carousel container -->
      <div class="ws-carousel-wrap">
        <button class="ws-carousel-arrow ws-carousel-arrow-left" id="wsCarouselLeft"><i class="bi bi-chevron-left"></i></button>
        <div class="ws-carousel-viewport" id="wsCarouselViewport">
          <div class="ws-carousel-track" id="wsCarouselTrack"></div>
        </div>
        <button class="ws-carousel-arrow ws-carousel-arrow-right" id="wsCarouselRight"><i class="bi bi-chevron-right"></i></button>
      </div>
      <p class="ws-menu-note"><i class="bi bi-info-circle"></i> Menus susceptibles de modifications. Régimes spéciaux disponibles sur demande.</p>
    </div>
  </div>
</section>

<!-- Modal réservation famille -->
<div class="ws-modal-overlay" id="wsResaOverlay" style="display:none">
  <div class="ws-modal">
    <div class="ws-modal-header">
      <h3 id="wsResaTitle"><i class="bi bi-calendar-check"></i> Réserver un repas</h3>
      <button class="ws-modal-close" id="wsResaClose">&times;</button>
    </div>
    <div class="ws-modal-body" id="wsResaBody">
      <!-- Step 1: Login -->
      <div id="wsResaLogin">
        <p class="ws-modal-desc">Identifiez-vous pour réserver un repas pour votre proche.</p>
        <div class="ws-form-group">
          <label>Email du correspondant</label>
          <input type="email" class="ws-input" id="wsResaEmail" placeholder="votre.email@exemple.com">
        </div>
        <div class="ws-form-group">
          <label>Code d'accès <small style="color:#888">(date de naissance du résident : JJMMAAAA)</small></label>
          <input type="password" class="ws-input" id="wsResaPassword" placeholder="Ex: 12031935">
        </div>
        <div class="ws-form-error" id="wsResaError" style="display:none"></div>
        <!-- DEMO: comptes test (à supprimer en prod) -->
        <div style="margin-top:12px">
          <button type="button" class="ws-demo-toggle" id="wsDemoToggle">
            <i class="bi bi-info-circle"></i> Comptes de démonstration
          </button>
          <div id="wsDemoList" style="display:none;margin-top:8px">
            <table class="ws-demo-table">
              <thead><tr><th>Résident</th><th>Ch.</th><th>Email</th><th>Code</th><th></th></tr></thead>
              <tbody>
                <tr><td>Marguerite Dupont</td><td>101</td><td class="ws-demo-email">jp.dupont@gmail.com</td><td><code>12031935</code></td>
                  <td><button class="ws-demo-use" data-email="jp.dupont@gmail.com" data-pwd="12031935">Utiliser</button></td></tr>
                <tr><td>Jeanne Favre</td><td>102</td><td class="ws-demo-email">michel.favre@bluewin.ch</td><td><code>22071938</code></td>
                  <td><button class="ws-demo-use" data-email="michel.favre@bluewin.ch" data-pwd="22071938">Utiliser</button></td></tr>
                <tr><td>André Rochat</td><td>103</td><td class="ws-demo-email">sophie.rochat@gmail.com</td><td><code>05111932</code></td>
                  <td><button class="ws-demo-use" data-email="sophie.rochat@gmail.com" data-pwd="05111932">Utiliser</button></td></tr>
                <tr><td>Hélène Muller</td><td>104</td><td class="ws-demo-email">thomas.muller@yahoo.fr</td><td><code>18011940</code></td>
                  <td><button class="ws-demo-use" data-email="thomas.muller@yahoo.fr" data-pwd="18011940">Utiliser</button></td></tr>
                <tr><td>Robert Blanc</td><td>105</td><td class="ws-demo-email">catherine.blanc@gmail.com</td><td><code>30091936</code></td>
                  <td><button class="ws-demo-use" data-email="catherine.blanc@gmail.com" data-pwd="30091936">Utiliser</button></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- Step 2: Reservation form -->
      <div id="wsResaForm" style="display:none">
        <div class="ws-resa-resident-card" id="wsResaResidentCard"></div>
        <input type="hidden" id="wsResaResidentId">
        <input type="hidden" id="wsResaDate">
        <div class="ws-form-group">
          <label>Repas</label>
          <div class="ws-radio-group">
            <label class="ws-radio-option active">
              <input type="radio" name="wsResaRepas" value="midi" checked>
              <i class="bi bi-sun"></i> Midi
              <span class="ws-radio-check"><i class="bi bi-check-lg"></i></span>
            </label>
            <label class="ws-radio-option">
              <input type="radio" name="wsResaRepas" value="soir">
              <i class="bi bi-moon-stars"></i> Soir
              <span class="ws-radio-check"><i class="bi bi-check-lg"></i></span>
            </label>
          </div>
        </div>
        <div class="ws-form-group">
          <label>Nombre de personnes</label>
          <select class="ws-input" id="wsResaNb">
            <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
          </select>
        </div>
        <div class="ws-form-group">
          <label>Remarques <small style="color:#888">(allergies, régime...)</small></label>
          <textarea class="ws-input" id="wsResaRemarques" rows="2" placeholder="Ex: sans gluten, allergie noix..."></textarea>
        </div>
        <div class="ws-form-error" id="wsResaFormError" style="display:none"></div>
      </div>
      <!-- Step 3: Ticket facture -->
      <div id="wsResaSuccess" style="display:none">
        <div class="ws-ticket" id="wsResaTicket"></div>
      </div>
    </div>
    <!-- Footer fixe -->
    <div class="ws-modal-footer" id="wsResaFooter">
      <button class="ws-btn ws-btn-outline" id="wsResaFooterCancel">Annuler</button>
      <button class="ws-btn ws-btn-primary" id="wsResaLoginBtn"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
      <button class="ws-btn ws-btn-primary" id="wsResaSubmitBtn" style="display:none"><i class="bi bi-check-circle"></i> Confirmer la réservation</button>
      <button class="ws-btn ws-btn-outline" id="wsResaCloseSuccess" style="display:none">Fermer</button>
    </div>
  </div>
</div>

<script>
(function() {
    const DAYS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const API = '/spocspace/website/api.php';
    let authCache = null; // { email, password, resident }

    // ── Menus cache indexé par "date_repas" ──
    const menusByKey = {};
    <?= json_encode(array_values($wsMenus), JSON_HEX_TAG | JSON_HEX_APOS) ?>.forEach(m => {
        menusByKey[m.date_jour + '_' + (m.repas || 'midi')] = m;
    });

    // ── 28 jours de données, carousel glissant ──
    const TOTAL_DAYS = 28;
    const minDate = new Date('<?= $wsMonday->format('Y-m-d') ?>T00:00:00');
    // Position initiale = aujourd'hui - lundi de la semaine
    let carouselPos = Math.floor((new Date() - minDate) / 86400000);
    if (carouselPos < 0) carouselPos = 0;
    if (carouselPos > TOTAL_DAYS - 3) carouselPos = TOTAL_DAYS - 3;

    function fmtDate(d) {
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    }
    function esc(s) { const t = document.createElement('span'); t.textContent = s; return t.innerHTML; }

    async function apiCall(action, data) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        return res.json();
    }

    // ── Week nav buttons = saut au lundi de la semaine ──
    function updateWeekLabel() {
        const label = document.getElementById('wsMenuWeekLabel');
        if (!label) return;
        // Quel lundi correspond à carouselPos ?
        const currentDay = new Date(minDate);
        currentDay.setDate(currentDay.getDate() + carouselPos);
        const dow = currentDay.getDay();
        const monday = new Date(currentDay);
        monday.setDate(monday.getDate() - (dow === 0 ? 6 : dow - 1));
        const sunday = new Date(monday);
        sunday.setDate(sunday.getDate() + 6);

        const weekNum = Math.floor((monday - minDate) / (7 * 86400000));
        const weekText = weekNum === 0 ? 'Cette semaine' : weekNum === 1 ? 'Semaine prochaine' :
            'Semaine du ' + fmtDateShort(monday);
        label.textContent = weekText + ' — ' + fmtDateShort(monday) + ' au ' + fmtDateShort(sunday);
    }

    function fmtDateShort(d) {
        return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
    }

    document.getElementById('wsMenuPrev')?.addEventListener('click', () => {
        // Trouver le lundi de la semaine précédente
        const currentDay = new Date(minDate);
        currentDay.setDate(currentDay.getDate() + carouselPos);
        const dow = currentDay.getDay();
        const daysFromMon = dow === 0 ? 6 : dow - 1;
        // Aller au lundi de la semaine précédente
        const targetPos = carouselPos - daysFromMon - 7;
        carouselPos = Math.max(0, targetPos);
        updateCarouselPosition();
        updateWeekLabel();
    });
    document.getElementById('wsMenuNext')?.addEventListener('click', () => {
        // Trouver le lundi de la semaine suivante
        const currentDay = new Date(minDate);
        currentDay.setDate(currentDay.getDate() + carouselPos);
        const dow = currentDay.getDay();
        const daysFromMon = dow === 0 ? 6 : dow - 1;
        const targetPos = carouselPos - daysFromMon + 7;
        carouselPos = Math.min(TOTAL_DAYS - 3, targetPos);
        updateCarouselPosition();
        updateWeekLabel();
    });

    // ── Carousel arrows = glisse d'1 jour ──
    document.getElementById('wsCarouselLeft')?.addEventListener('click', () => {
        if (carouselPos > 0) { carouselPos--; updateCarouselPosition(); updateWeekLabel(); }
    });
    document.getElementById('wsCarouselRight')?.addEventListener('click', () => {
        if (carouselPos < TOTAL_DAYS - 3) { carouselPos++; updateCarouselPosition(); updateWeekLabel(); }
    });

    function updateCarouselPosition() {
        const track = document.getElementById('wsCarouselTrack');
        if (!track) return;
        track.style.transform = 'translateX(-' + (carouselPos * (100 / 3)) + '%)';
    }

    // ── Rendu des 28 cartes (une seule fois) ──
    function renderAllCards() {
        const track = document.getElementById('wsCarouselTrack');
        if (!track) return;
        const today = fmtDate(new Date());
        const MEAL_PRICE = { midi: 'CHF 14.50', soir: 'CHF 11.00' };

        track.innerHTML = '';
        for (let i = 0; i < TOTAL_DAYS; i++) {
            const d = new Date(minDate);
            d.setDate(d.getDate() + i);
            const dateStr = fmtDate(d);
            const isToday = dateStr === today;
            const isPast = dateStr < today;
            const dayName = DAYS[d.getDay()];
            const midi = menusByKey[dateStr + '_midi'];
            const soir = menusByKey[dateStr + '_soir'];
            const dayLabel = dayName + ' ' + d.getDate() + '/' + (d.getMonth() + 1);

            const wrapper = document.createElement('div');
            wrapper.className = 'ws-menu-card' + (isToday ? ' is-today' : '');

            wrapper.innerHTML = '<div class="ws-card-inner">'
                + '<div class="ws-card-header">' + esc(dayLabel) + (isToday ? ' <span class="ws-today-badge">Aujourd\'hui</span>' : '') + '</div>'
                + '<div class="ws-card-body">'
                + buildMealBlock('midi', midi, MEAL_PRICE)
                + '<hr class="ws-meal-divider">'
                + buildMealBlock('soir', soir, MEAL_PRICE)
                + '</div>'
                + '<div class="ws-card-footer">'
                + '<button class="ws-btn ws-btn-reserve" data-date="' + dateStr + '" data-day="' + esc(dayLabel) + '"' + (isPast ? ' disabled' : '') + '>'
                + '<i class="bi bi-calendar-plus"></i> Réserver un repas</button>'
                + '</div></div>';

            track.appendChild(wrapper);
        }

        // Reserve buttons
        track.querySelectorAll('.ws-btn-reserve:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => openReservation(btn.dataset.date, btn.dataset.day));
        });

        // Equal height
        requestAnimationFrame(() => {
            const inners = track.querySelectorAll('.ws-card-inner');
            let maxH = 0;
            inners.forEach(el => { el.style.height = 'auto'; });
            inners.forEach(el => { if (el.offsetHeight > maxH) maxH = el.offsetHeight; });
            if (maxH) inners.forEach(el => { el.style.height = maxH + 'px'; });
        });

        updateCarouselPosition();
        updateWeekLabel();
    }

    function buildMealBlock(repas, menu, prices) {
        const label = repas === 'midi' ? 'Midi' : 'Soir';
        const icon = repas === 'midi' ? 'bi-sun' : 'bi-moon-stars';
        if (!menu) {
            return '<div class="ws-meal-block"><div class="ws-meal-label '+repas+'"><i class="bi '+icon+'"></i> '+label+'</div>'
                + '<div class="ws-meal-empty">Menu à venir</div></div>';
        }
        const items = [
            { val: menu.entree, bold: false }, { val: menu.plat, bold: true },
            { val: menu.accompagnement, bold: false }, { val: menu.salade, bold: false },
            { val: menu.dessert, bold: false }
        ].filter(i => i.val);
        let html = '<div class="ws-meal-block"><div class="ws-meal-label '+repas+'"><i class="bi '+icon+'"></i> '+label+'</div><div class="ws-meal-items">';
        items.forEach(i => {
            html += '<div class="ws-meal-item"><span class="ws-meal-dot '+repas+'"></span>'+(i.bold?'<strong>'+esc(i.val)+'</strong>':esc(i.val))+'</div>';
        });
        html += '</div>';
        if (menu.remarques) html += '<div class="ws-meal-remark"><i class="bi bi-info-circle"></i> '+esc(menu.remarques)+'</div>';
        html += '<div class="ws-meal-price"><i class="bi bi-tag"></i> '+prices[repas]+'</div></div>';
        return html;
    }

    // ── Reservation modal ──
    // ── Footer buttons visibility helper ──
    function setModalStep(step) {
        const loginBtn = document.getElementById('wsResaLoginBtn');
        const submitBtn = document.getElementById('wsResaSubmitBtn');
        const cancelBtn = document.getElementById('wsResaFooterCancel');
        const closeBtn = document.getElementById('wsResaCloseSuccess');

        loginBtn.style.display = step === 'login' ? '' : 'none';
        submitBtn.style.display = step === 'form' ? '' : 'none';
        cancelBtn.style.display = step === 'success' ? 'none' : '';
        closeBtn.style.display = step === 'success' ? '' : 'none';
    }

    function openReservation(dateStr, dayLabel) {
        document.getElementById('wsResaDate').value = dateStr;
        document.getElementById('wsResaTitle').innerHTML = '<i class="bi bi-calendar-check"></i> Réserver — ' + esc(dayLabel);

        document.getElementById('wsResaLogin').style.display = '';
        document.getElementById('wsResaForm').style.display = 'none';
        document.getElementById('wsResaSuccess').style.display = 'none';
        document.getElementById('wsResaError').style.display = 'none';
        document.getElementById('wsResaFormError').style.display = 'none';

        if (authCache) {
            showResaForm(authCache.resident);
            setModalStep('form');
        } else {
            document.getElementById('wsResaEmail').value = '';
            document.getElementById('wsResaPassword').value = '';
            setModalStep('login');
        }

        document.getElementById('wsResaOverlay').style.display = 'flex';
    }

    function closeReservation() {
        document.getElementById('wsResaOverlay').style.display = 'none';
    }

    document.getElementById('wsResaClose')?.addEventListener('click', closeReservation);
    document.getElementById('wsResaFooterCancel')?.addEventListener('click', closeReservation);
    document.getElementById('wsResaCloseSuccess')?.addEventListener('click', closeReservation);
    document.getElementById('wsResaOverlay')?.addEventListener('click', e => {
        if (e.target.id === 'wsResaOverlay') closeReservation();
    });

    // Demo toggle + auto-fill
    document.getElementById('wsDemoToggle')?.addEventListener('click', () => {
        const list = document.getElementById('wsDemoList');
        list.style.display = list.style.display === 'none' ? '' : 'none';
    });
    document.querySelectorAll('.ws-demo-use').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('wsResaEmail').value = btn.dataset.email;
            document.getElementById('wsResaPassword').value = btn.dataset.pwd;
            document.getElementById('wsDemoList').style.display = 'none';
        });
    });

    // Login
    document.getElementById('wsResaLoginBtn')?.addEventListener('click', async () => {
        const email = document.getElementById('wsResaEmail').value.trim();
        const password = document.getElementById('wsResaPassword').value.trim();
        const errEl = document.getElementById('wsResaError');

        if (!email || !password) { errEl.textContent = 'Veuillez remplir tous les champs'; errEl.style.display = ''; return; }
        errEl.style.display = 'none';

        const btn = document.getElementById('wsResaLoginBtn');
        btn.disabled = true; btn.textContent = 'Vérification...';

        const res = await apiCall('famille_login', { email, password });
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Se connecter';

        if (!res.success) {
            errEl.textContent = res.message || 'Erreur de connexion';
            errEl.style.display = '';
            return;
        }

        authCache = { email, password, resident: res.resident };
        showResaForm(res.resident);
        setModalStep('form');
    });

    function showResaForm(resident) {
        document.getElementById('wsResaLogin').style.display = 'none';
        document.getElementById('wsResaForm').style.display = '';
        document.getElementById('wsResaResidentId').value = resident.id;

        const initials = ((resident.prenom?.[0]||'')+(resident.nom?.[0]||'')).toUpperCase();
        document.getElementById('wsResaResidentCard').innerHTML =
            '<div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--ws-green-bg);border-radius:12px;margin-bottom:1rem">'
            + '<div style="width:48px;height:48px;border-radius:50%;background:var(--ws-green);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem">'+esc(initials)+'</div>'
            + '<div><div style="font-weight:700;font-size:1rem">'+esc(resident.prenom+' '+resident.nom)+'</div>'
            + '<div style="font-size:0.85rem;color:#666">Chambre '+esc(resident.chambre||'')+' — Étage '+esc(resident.etage||'')+'</div>'
            + '<div style="font-size:0.8rem;color:#888">Correspondant: '+esc((resident.correspondant_prenom||'')+' '+(resident.correspondant_nom||''))+'</div>'
            + '</div></div>';
    }

    // Radio toggle
    document.querySelectorAll('#wsResaForm .ws-radio-option').forEach(opt => {
        opt.querySelector('input')?.addEventListener('change', () => {
            document.querySelectorAll('#wsResaForm .ws-radio-option').forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
        });
    });

    // Submit reservation
    document.getElementById('wsResaSubmitBtn')?.addEventListener('click', async () => {
        if (!authCache) return;
        const errEl = document.getElementById('wsResaFormError');
        const btn = document.getElementById('wsResaSubmitBtn');

        btn.disabled = true; btn.textContent = 'Envoi...';
        errEl.style.display = 'none';

        const res = await apiCall('famille_reserver', {
            email: authCache.email,
            password: authCache.password,
            resident_id: document.getElementById('wsResaResidentId').value,
            date_jour: document.getElementById('wsResaDate').value,
            repas: document.querySelector('input[name="wsResaRepas"]:checked')?.value || 'midi',
            nb_personnes: document.getElementById('wsResaNb').value,
            remarques: document.getElementById('wsResaRemarques').value,
        });

        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmer la réservation';

        if (!res.success) {
            errEl.textContent = res.message || 'Erreur';
            errEl.style.display = '';
            return;
        }

        // Build ticket
        const repasVal = document.querySelector('input[name="wsResaRepas"]:checked')?.value || 'midi';
        const nbVal = parseInt(document.getElementById('wsResaNb').value) || 1;
        const remarquesVal = document.getElementById('wsResaRemarques').value;
        const dateVal = document.getElementById('wsResaDate').value;
        const residentName = authCache.resident.prenom + ' ' + authCache.resident.nom;
        const corrName = (authCache.resident.correspondant_prenom||'') + ' ' + (authCache.resident.correspondant_nom||'');
        const prixUnit = repasVal === 'midi' ? 14.50 : 11.00;
        const total = (prixUnit * nbVal).toFixed(2);
        const now = new Date();
        const timeStr = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');

        document.getElementById('wsResaTicket').innerHTML =
            '<div class="ws-ticket-header"><i class="bi bi-check-circle-fill"></i><h4>Réservation confirmée</h4></div>'
            + '<div class="ws-ticket-row"><span>Résident</span><strong>' + esc(residentName) + '</strong></div>'
            + '<div class="ws-ticket-row"><span>Chambre</span><strong>' + esc(authCache.resident.chambre || '-') + '</strong></div>'
            + '<div class="ws-ticket-row"><span>Date</span><strong>' + esc(dateVal) + '</strong></div>'
            + '<div class="ws-ticket-row"><span>Repas</span><strong>' + (repasVal === 'midi' ? '<i class="bi bi-sun"></i> Midi' : '<i class="bi bi-moon-stars"></i> Soir') + '</strong></div>'
            + '<div class="ws-ticket-row"><span>Personnes</span><strong>' + nbVal + '</strong></div>'
            + (remarquesVal ? '<div class="ws-ticket-row"><span>Remarques</span><strong>' + esc(remarquesVal) + '</strong></div>' : '')
            + '<div class="ws-ticket-row"><span>Prix unitaire</span><strong>CHF ' + prixUnit.toFixed(2) + '</strong></div>'
            + '<div class="ws-ticket-total"><span>Total</span><span>CHF ' + total + '</span></div>'
            + '<div class="ws-ticket-footer">'
            + '<div>Réservé par ' + esc(corrName.trim()) + '</div>'
            + '<div>' + esc(dateVal) + ' à ' + timeStr + '</div>'
            + '<div style="margin-top:6px"><i class="bi bi-printer"></i> Conservez ce reçu</div></div>';

        document.getElementById('wsResaForm').style.display = 'none';
        document.getElementById('wsResaSuccess').style.display = '';
        setModalStep('success');
    });

    // Init — rendu des 28 cartes + position sur aujourd'hui
    renderAllCards();
})();
</script>

<!-- ═══ PROGRAMME ANIMATION ═══ -->
<section class="ws-section ws-section-alt" id="programme-animation">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-calendar-event"></i> Animation</div>
      <h2 class="ws-section-title">Programme de la <span class="ws-accent">semaine</span></h2>
      <p class="ws-section-desc">
        Chaque semaine, découvrez les activités et animations proposées à nos résidents.
        Moments de partage, de créativité et de bien-être.
      </p>
    </div>
    <div class="ws-anim-week">
      <div class="row g-3">
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card">
            <div class="ws-anim-day">Lundi</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 10h00</div>
              <div class="ws-anim-title">Gymnastique douce</div>
              <div class="ws-anim-desc">Exercices adaptés pour maintenir la mobilité et le bien-être physique.</div>
            </div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 14h30</div>
              <div class="ws-anim-title">Atelier mémoire</div>
              <div class="ws-anim-desc">Jeux et exercices de stimulation cognitive en groupe.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card">
            <div class="ws-anim-day">Mardi</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 10h00</div>
              <div class="ws-anim-title">Atelier peinture</div>
              <div class="ws-anim-desc">Expression créative à travers l'aquarelle et la peinture.</div>
            </div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 15h00</div>
              <div class="ws-anim-title">Loto</div>
              <div class="ws-anim-desc">Moment convivial autour du jeu de loto.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card">
            <div class="ws-anim-day">Mercredi</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 10h30</div>
              <div class="ws-anim-title">Musique & chant</div>
              <div class="ws-anim-desc">Séance musicale avec chansons d'hier et d'aujourd'hui.</div>
            </div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 14h30</div>
              <div class="ws-anim-title">Jeux de société</div>
              <div class="ws-anim-desc">Scrabble, cartes, dominos — pour le plaisir et le lien social.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card">
            <div class="ws-anim-day">Jeudi</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 10h00</div>
              <div class="ws-anim-title">Cuisine thérapeutique</div>
              <div class="ws-anim-desc">Préparer ensemble une recette simple et savoureuse.</div>
            </div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 15h00</div>
              <div class="ws-anim-title">Cinéma</div>
              <div class="ws-anim-desc">Projection d'un film suivi d'un goûter.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card">
            <div class="ws-anim-day">Vendredi</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 10h00</div>
              <div class="ws-anim-title">Revue de presse</div>
              <div class="ws-anim-desc">Lecture commentée de l'actualité, échanges et débats.</div>
            </div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-clock"></i> 14h30</div>
              <div class="ws-anim-title">Sortie au jardin</div>
              <div class="ws-anim-desc">Promenade en plein air, jardinage ou détente au soleil.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-4">
          <div class="ws-anim-card ws-anim-card-special">
            <div class="ws-anim-day">Événement spécial</div>
            <div class="ws-anim-event">
              <div class="ws-anim-time"><i class="bi bi-star"></i> Samedi 15h00</div>
              <div class="ws-anim-title">Concert de printemps</div>
              <div class="ws-anim-desc">Un après-midi musical avec un groupe local. Familles bienvenues !</div>
            </div>
          </div>
        </div>
      </div>
      <p class="ws-menu-note"><i class="bi bi-info-circle"></i> Programme susceptible de modifications. Consultez l'affichage à l'accueil pour les mises à jour.</p>
    </div>
  </div>
</section>

<!-- ═══ MODULES / ORGANISATION ═══ -->
<section class="ws-section ws-section-alt" id="modules">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-building"></i> Organisation</div>
      <h2 class="ws-section-title">Nos <span class="ws-accent">modules</span> de soins</h2>
      <p class="ws-section-desc">
        L'établissement est organisé en 4 modules, chacun disposant d'une équipe dédiée
        pour assurer un suivi optimal et personnalisé.
      </p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3">
        <div class="ws-module-card">
          <div class="ws-module-num">M1</div>
          <h4>Module 1</h4>
          <p>Étages 1 & 2</p>
          <div class="ws-module-team"><i class="bi bi-people"></i> Équipe dédiée</div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-module-card">
          <div class="ws-module-num">M2</div>
          <h4>Module 2</h4>
          <p>Étage 3</p>
          <div class="ws-module-team"><i class="bi bi-people"></i> Équipe dédiée</div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-module-card">
          <div class="ws-module-num">M3</div>
          <h4>Module 3</h4>
          <p>Étage 4</p>
          <div class="ws-module-team"><i class="bi bi-people"></i> Équipe dédiée</div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-module-card">
          <div class="ws-module-num">M4</div>
          <h4>Module 4</h4>
          <p>Étage 5</p>
          <div class="ws-module-team"><i class="bi bi-people"></i> Équipe dédiée</div>
        </div>
      </div>
    </div>
    <div class="ws-coverage-banner">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h4><i class="bi bi-shield-check"></i> Couverture 24h/24, 7j/7</h4>
          <p class="mb-0">Chaque module dispose d'au minimum 2 soignants de 7h à 20h30, complétés par une équipe de nuit dédiée de 20h15 à 7h15.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <div class="ws-coverage-hours">
            <div><strong>Jour</strong> 7h00 – 20h30</div>
            <div><strong>Nuit</strong> 20h15 – 7h15</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ TEAM ═══ -->
<section class="ws-section" id="team">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-people-fill"></i> Notre équipe</div>
      <h2 class="ws-section-title">Des professionnels <span class="ws-accent">passionnés</span></h2>
      <p class="ws-section-desc">
        Notre équipe pluridisciplinaire réunit des compétences variées
        au service du bien-être des résidents.
      </p>
    </div>
    <div class="row g-4">
      <div class="col-md-4 col-lg-3">
        <div class="ws-team-card">
          <div class="ws-team-avatar"><i class="bi bi-person-badge"></i></div>
          <h5>Infirmières</h5>
          <p>Diplômées HES, elles coordonnent les soins et assurent le suivi médical quotidien.</p>
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <div class="ws-team-card">
          <div class="ws-team-avatar"><i class="bi bi-heart"></i></div>
          <h5>ASSC</h5>
          <p>Assistantes en soins et santé communautaire, elles allient technique et accompagnement.</p>
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <div class="ws-team-card">
          <div class="ws-team-avatar"><i class="bi bi-hand-thumbs-up"></i></div>
          <h5>Aides-soignants</h5>
          <p>Qualifiés et attentifs, ils accompagnent les gestes du quotidien avec bienveillance.</p>
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <div class="ws-team-card">
          <div class="ws-team-avatar"><i class="bi bi-stars"></i></div>
          <h5>Stagiaires & Civilistes</h5>
          <p>Formés sur le terrain, ils apportent dynamisme et regard neuf à notre équipe.</p>
        </div>
      </div>
    </div>
    <!-- Rubrique épinglée -->
    <?php if (ws_visible($cms, 'pinned')):
        $pinned = ws_content($cms, 'pinned');
        $pinnedTitle = $pinned['title'] ?? '';
        $pinnedText  = $pinned['text'] ?? '';
        $pinnedSig   = $pinned['signature'] ?? '';
        $pinnedImg   = $pinned['image'] ?? '';
    ?>
    <div class="ws-team-cta ws-cta-pinned">
      <div class="row align-items-center g-4">
        <?php if ($pinnedImg): ?>
        <div class="col-lg-5 text-center">
          <img src="<?= h($pinnedImg) ?>" alt="" class="ws-pinned-img" loading="lazy">
        </div>
        <div class="col-lg-7">
        <?php else: ?>
        <div class="col-12">
        <?php endif; ?>
          <h4><?= $pinnedTitle ?></h4>
          <div class="ws-pinned-text"><?= $pinnedText ?></div>
          <?php if ($pinnedSig):
            $sigParts = array_map('trim', explode(',', $pinnedSig, 2));
          ?>
          <div class="ws-pinned-signature">
            <span class="ws-pinned-sig-name"><?= h($sigParts[0]) ?></span>
            <?php if (!empty($sigParts[1])): ?>
            <span class="ws-pinned-sig-role"><?= h($sigParts[1]) ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Formation continue -->
    <div class="ws-team-cta ws-cta-formation">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <h4><i class="bi bi-mortarboard"></i> Formation continue</h4>
          <p class="ws-cta-lead">L'EMS La Terrassière SA encourage et soutient la formation continue comme le perfectionnement de ses collaborateurs.</p>
          <p>La diversité des professions et des générations qui se côtoient dans notre établissement pousse au programme de formation continue. Faire progresser les compétences professionnelles et le développement personnel de chacun des collaborateurs est un objectif permanent de la direction.</p>
        </div>
        <div class="col-lg-5 text-center">
          <div class="ws-cta-icon-block">
            <i class="bi bi-book"></i>
            <i class="bi bi-people"></i>
            <i class="bi bi-graph-up-arrow"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Bénévoles -->
    <div class="ws-team-cta ws-cta-benevoles">
      <div class="row align-items-start g-4">
        <div class="col-lg-6">
          <h4><i class="bi bi-heart"></i> Bénévoles recherchés</h4>
          <p class="ws-cta-lead">Rejoignez-nous pour faire la différence !</p>
          <p>Vous avez un peu de temps libre et souhaitez apporter de la joie et du soutien à nos aînés ? Notre établissement est à la recherche de bénévoles dévoués et bienveillants pour enrichir la vie de nos résidants.</p>
          <h6 class="ws-cta-subtitle"><i class="bi bi-check-circle"></i> Vos missions</h6>
          <ul class="ws-cta-list">
            <li>Offrir une écoute attentive et un soutien moral</li>
            <li>Aider lors des sorties et événements spéciaux</li>
            <li>Participer à des activités récréatives et culturelles</li>
            <li>Contribuer à créer une atmosphère chaleureuse et conviviale</li>
          </ul>
        </div>
        <div class="col-lg-6">
          <h6 class="ws-cta-subtitle"><i class="bi bi-gift"></i> Nous offrons</h6>
          <ul class="ws-cta-list">
            <li>Une expérience humaine enrichissante</li>
            <li>La possibilité de développer de nouvelles compétences</li>
            <li>Un environnement accueillant et respectueux</li>
          </ul>
          <div class="ws-cta-contact">
            <h6 class="ws-cta-subtitle"><i class="bi bi-telephone"></i> Comment nous rejoindre ?</h6>
            <p>Contactez-nous dès aujourd'hui :</p>
            <div class="ws-cta-contact-links">
              <a href="tel:0227186200"><i class="bi bi-telephone-fill"></i> 022 718 62 00</a>
              <a href="mailto:info@ems-laterrassiere.ch"><i class="bi bi-envelope-fill"></i> info@ems-laterrassiere.ch</a>
            </div>
          </div>
        </div>
      </div>
      <div class="text-center mt-4">
        <p class="ws-cta-closing">Ensemble, faisons de chaque jour un moment spécial pour nos résidants !</p>
      </div>
    </div>

    <!-- Postuler -->
    <div style="margin-top:32px;display:flex;align-items:center;gap:24px;padding:20px 28px;border-top:1px solid rgba(46,125,50,0.1)">
      <div style="flex:1">
        <h5 style="font-size:1rem;font-weight:700;margin:0 0 2px;display:flex;align-items:center;gap:8px"><i class="bi bi-feather" style="color:var(--ws-green)"></i> Rejoignez notre équipe</h5>
        <p style="font-size:.85rem;margin:0;color:var(--ws-text-muted)">Nous sommes toujours à la recherche de professionnels motivés et bienveillants.</p>
      </div>
      <a href="/spocspace/website/recrutement.php" class="ws-btn-outline-sm" style="flex-shrink:0">
        <i class="bi bi-send"></i> Voir les offres
      </a>
    </div>
  </div>
</section>

<!-- ═══ VIDEO SHOWCASE ═══ -->
<section class="ws-video-gallery" id="gallery">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-camera-reels"></i> Notre cadre</div>
      <h2 class="ws-section-title">Découvrez <span class="ws-accent">La Terrassière</span> en images</h2>
      <p class="ws-section-desc">Un environnement chaleureux au centre de Genève, pensé pour le confort et le bien-être de chaque résident.</p>
    </div>
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="ws-video-card ws-video-card-lg">
          <video autoplay muted loop playsinline>
            <source src="assets/video/6096_medium.mp4" type="video/mp4">
          </video>
          <div class="ws-video-card-overlay">
            <span class="ws-video-card-label"><i class="bi bi-play-circle"></i> Notre environnement</span>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="row g-4">
          <div class="col-12">
            <div class="ws-video-card">
              <video autoplay muted loop playsinline>
                <source src="assets/video/229069_medium.mp4" type="video/mp4">
              </video>
              <div class="ws-video-card-overlay">
                <span class="ws-video-card-label"><i class="bi bi-play-circle"></i> Le quotidien</span>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="ws-video-card">
              <video autoplay muted loop playsinline>
                <source src="assets/video/229071_medium.mp4" type="video/mp4">
              </video>
              <div class="ws-video-card-overlay">
                <span class="ws-video-card-label"><i class="bi bi-play-circle"></i> Les soins</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ VALUES ═══ -->
<?php if (ws_visible($cms, 'values')):
    $valCards = ws_content($cms, 'values', 'cards') ?: [];
?>
<section class="ws-section ws-section-alt" id="values">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi <?= h(ws_get($cms, 'values', 'badge_icon')) ?>"></i> <?= h(ws_get($cms, 'values', 'badge_text')) ?></div>
      <h2 class="ws-section-title"><?= ws_get($cms, 'values', 'title') ?></h2>
    </div>
    <div class="row g-4">
      <?php foreach ($valCards as $c): ?>
      <div class="col-md-6 col-lg-3">
        <div class="ws-value-card">
          <div class="ws-value-icon"><i class="bi <?= h($c['icon'] ?? '') ?>"></i></div>
          <h5><?= h($c['title'] ?? '') ?></h5>
          <p><?= h($c['text'] ?? '') ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ CONTACT ═══ -->
<?php if (ws_visible($cms, 'contact')):
    $contact = ws_content($cms, 'contact');
?>
<section class="ws-section" id="contact">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi <?= h(ws_get($cms, 'contact', 'badge_icon')) ?>"></i> <?= h(ws_get($cms, 'contact', 'badge_text')) ?></div>
      <h2 class="ws-section-title"><?= ws_get($cms, 'contact', 'title') ?></h2>
      <p class="ws-section-desc"><?= h(ws_get($cms, 'contact', 'subtitle')) ?></p>
    </div>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="ws-contact-info">
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
              <h5>Adresse</h5>
              <p><?= nl2br(h($contact['address'] ?? '')) ?></p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-telephone-fill"></i></div>
            <div>
              <h5>Téléphone</h5>
              <p><?= h($contact['phone'] ?? '') ?></p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-envelope-fill"></i></div>
            <div>
              <h5>Email</h5>
              <p><?= h($contact['email'] ?? '') ?></p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-clock-fill"></i></div>
            <div>
              <h5>Horaires de visite</h5>
              <p><?= h($contact['hours'] ?? '') ?><br>
              <?php if (!empty($contact['hours_note'])): ?><small class="text-muted"><?= h($contact['hours_note']) ?></small><?php endif; ?></p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <form class="ws-contact-form" id="contactForm">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input type="text" class="form-control" name="nom" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Prénom</label>
              <input type="text" class="form-control" name="prenom" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Téléphone</label>
              <input type="tel" class="form-control" name="telephone">
            </div>
            <div class="col-12">
              <label class="form-label">Objet</label>
              <select class="form-select" name="objet">
                <option value="info">Demande d'information</option>
                <option value="visite">Demande de visite</option>
                <option value="admission">Demande d'admission</option>
                <option value="emploi">Candidature spontanée</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4" required></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="ws-btn ws-btn-primary w-100">
                <i class="bi bi-send"></i> Envoyer le message
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php endif; /* contact */ ?>

<!-- ═══ FOOTER ═══ -->
<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- ═══ FLOATING SIDE WIDGET ═══ -->
<div class="ws-side-widget" id="wsSideWidget">
  <a href="#contact" class="ws-sw-btn" title="Contactez-nous">
    <img src="/spocspace/website/assets/img/webp/email.webp" alt="" class="ws-sw-icon" loading="lazy">
    <span class="ws-sw-label">Contactez-nous</span>
  </a>
  <a href="#menu-semaine" class="ws-sw-btn" title="Menu de la semaine">
    <img src="/spocspace/website/assets/img/webp/menu.webp" alt="" class="ws-sw-icon" loading="lazy">
    <span class="ws-sw-label">Menu de la semaine</span>
  </a>
  <?php
    // Icône animation dynamique par mois (animation_{mois}-white.webp, fallback: animation.webp)
    $animMonth = (int) date('n');
    $animIcon = "assets/img/webp/animation_{$animMonth}.webp";
    if (!file_exists(__DIR__ . '/' . $animIcon)) $animIcon = 'assets/img/webp/animation.webp';
  ?>
  <a href="#programme-animation" class="ws-sw-btn" title="Programme animation">
    <img src="/spocspace/website/<?= $animIcon ?>" alt="" class="ws-sw-icon" loading="lazy">
    <span class="ws-sw-label">Programme animation</span>
  </a>
  <a href="/spocspace/website/famille.php" class="ws-sw-btn" title="Espace Famille">
    <img src="/spocspace/website/assets/img/webp/famille.webp" alt="" class="ws-sw-icon" loading="lazy">
    <span class="ws-sw-label">Espace Famille</span>
  </a>
  <!-- Cachés par défaut, à réactiver plus tard -->
  <a href="tel:+41220000000" class="ws-sw-btn ws-sw-hidden" title="Téléphone">
    <i class="bi bi-telephone-fill"></i>
    <span class="ws-sw-label">+41 22 XXX XX XX</span>
  </a>
  <a href="https://maps.google.com/?q=EMS+La+Terrassi%C3%A8re+Gen%C3%A8ve" target="_blank" rel="noopener" class="ws-sw-btn ws-sw-hidden" title="Localisation">
    <i class="bi bi-geo-alt-fill"></i>
    <span class="ws-sw-label">Nous trouver</span>
  </a>
</div>

<script src="../assets/js/vendor/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/website.js" defer></script>
<script>
// Lazy-load videos when they enter viewport
if ('IntersectionObserver' in window) {
    const vObs = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                const v = e.target;
                if (v.dataset.src && !v.querySelector('source')) {
                    const s = document.createElement('source');
                    s.src = v.dataset.src;
                    s.type = 'video/mp4';
                    v.appendChild(s);
                    v.load();
                    v.play();
                }
                vObs.unobserve(v);
            }
        });
    }, { rootMargin: '200px' });
    document.querySelectorAll('.ws-lazy-video').forEach(v => vObs.observe(v));
}
</script>
</body>
</html>
