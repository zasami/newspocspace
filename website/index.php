<?php
// Load zerdaTime DB to fetch dynamic menus
require_once __DIR__ . '/../init.php';

// Get current week's menus (Monday → Sunday)
$dt = new DateTime();
$dow = (int) $dt->format('N');
$wsMonday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
$wsSunday = (clone $wsMonday)->modify('+6 days');
$wsMenus = Db::fetchAll(
    "SELECT date_jour, repas, entree, plat, salade, accompagnement, dessert, remarques
     FROM menus WHERE date_jour BETWEEN ? AND ? ORDER BY date_jour ASC, repas ASC",
    [$wsMonday->format('Y-m-d'), $wsSunday->format('Y-m-d')]
);
$wsMenusByKey = [];
foreach ($wsMenus as $m) {
    $wsMenusByKey[$m['date_jour'] . '_' . $m['repas']] = $m;
}
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/website.css">
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="ws-nav" id="wsNav">
  <div class="container">
    <div class="ws-nav-inner">
      <a href="#hero" class="ws-logo">
        <img src="EMS-Terrassire-SA-logo-web-1920w.png" alt="E.M.S. La Terrassière SA" class="ws-logo-img">
      </a>
      <button class="ws-nav-toggle" id="wsNavToggle" aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <ul class="ws-nav-links" id="wsNavLinks">
        <li><a href="#hero">Accueil</a></li>
        <li><a href="#about">Notre mission</a></li>
        <li><a href="#services">Nos soins</a></li>
        <li><a href="#life">Vie quotidienne</a></li>
        <li><a href="#team">Équipe</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="/zerdatime/" class="ws-btn-nav"><i class="bi bi-box-arrow-in-right"></i> Espace collaborateur</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ═══ HERO WITH VIDEO BACKGROUND ═══ -->
<section class="ws-hero ws-hero-video" id="hero">
  <div class="ws-hero-video-wrap">
    <video class="ws-hero-vid" autoplay muted loop playsinline
           data-playlist='["assets/video/6096_medium.mp4","assets/video/229069_medium.mp4","assets/video/229071_medium.mp4"]'>
      <source src="assets/video/6096_medium.mp4" type="video/mp4">
    </video>
    <div class="ws-hero-video-overlay"></div>
  </div>
  <div class="container">
    <div class="ws-hero-content">
      <img src="EMS-Terrassire-SA-logo-web-1920w.png" alt="E.M.S. La Terrassière SA" class="ws-hero-logo">
      <h1 class="ws-hero-title">Un lieu de vie<br>chaleureux <span class="ws-accent-light">au centre de Genève</span></h1>
      <p class="ws-hero-desc">
        Depuis plus de 30 ans, l'EMS La Terrassière SA accompagne les personnes âgées
        avec respect, dignité et professionnalisme au cœur de Genève.
      </p>
      <div class="ws-hero-actions">
        <a href="#contact" class="ws-btn ws-btn-primary"><i class="bi bi-telephone"></i> Nous contacter</a>
        <a href="#services" class="ws-btn ws-btn-outline-light"><i class="bi bi-arrow-down"></i> Découvrir</a>
      </div>
      <div class="ws-hero-stats">
        <div class="ws-stat">
          <div class="ws-stat-num">98</div>
          <div class="ws-stat-label">Collaborateurs</div>
        </div>
        <div class="ws-stat">
          <div class="ws-stat-num">4</div>
          <div class="ws-stat-label">Modules de soins</div>
        </div>
        <div class="ws-stat">
          <div class="ws-stat-num">24/7</div>
          <div class="ws-stat-label">Présence continue</div>
        </div>
        <div class="ws-stat">
          <div class="ws-stat-num">30+</div>
          <div class="ws-stat-label">Années d'expérience</div>
        </div>
      </div>
    </div>
  </div>
  <!-- Scroll indicator -->
  <div class="ws-scroll-indicator">
    <div class="ws-scroll-mouse">
      <div class="ws-scroll-wheel"></div>
    </div>
  </div>
</section>

<!-- ═══ ABOUT / MISSION ═══ -->
<section class="ws-section" id="about">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-feather"></i> Notre engagement</div>
      <h2 class="ws-section-title">Une mission centrée sur <span class="ws-accent">l'humain</span></h2>
      <p class="ws-section-desc">
        L'EMS La Terrassière offre un accompagnement personnalisé dans un environnement
        chaleureux et sécurisant au centre de Genève, favorisant l'autonomie et le bien-être de chaque résident.
      </p>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="ws-card ws-card-icon">
          <div class="ws-card-ic"><i class="bi bi-heart-pulse"></i></div>
          <h3>Soins personnalisés</h3>
          <p>Chaque résident bénéficie d'un plan de soins adapté à ses besoins, élaboré en concertation avec l'équipe médicale et la famille.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ws-card ws-card-icon">
          <div class="ws-card-ic"><i class="bi bi-people-fill"></i></div>
          <h3>Équipe qualifiée</h3>
          <p>98 collaborateurs formés et passionnés — infirmières, aides-soignants, accompagnants — assurent une présence continue 7j/7.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ws-card ws-card-icon">
          <div class="ws-card-ic"><i class="bi bi-geo-alt"></i></div>
          <h3>Au centre de Genève</h3>
          <p>Un emplacement idéal en plein cœur de la ville, facilitant les visites des proches et l'accès aux services urbains.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ VIDEO DIVIDER 1 ═══ -->
<section class="ws-video-divider">
  <video autoplay muted loop playsinline>
    <source src="assets/video/229069_medium.mp4" type="video/mp4">
  </video>
  <div class="ws-video-divider-overlay"></div>
  <div class="ws-video-divider-content">
    <div class="ws-video-divider-badge"><i class="bi bi-quote"></i></div>
    <blockquote>« Prendre soin, c'est offrir un regard bienveillant sur chaque instant de vie. »</blockquote>
  </div>
</section>

<!-- ═══ SERVICES ═══ -->
<section class="ws-section ws-section-alt" id="services">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-clipboard2-pulse"></i> Nos prestations</div>
      <h2 class="ws-section-title">Des soins <span class="ws-accent">complets</span> et adaptés</h2>
    </div>
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-clipboard2-pulse"></i></div>
          <div class="ws-service-body">
            <h4>Soins infirmiers</h4>
            <p>Administration des traitements, surveillance clinique, soins techniques et accompagnement médical quotidien par notre équipe d'infirmières diplômées.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-person-hearts"></i></div>
          <div class="ws-service-body">
            <h4>Accompagnement quotidien</h4>
            <p>Aide à la toilette, aux repas, aux déplacements. Nos aides-soignants qualifiés accompagnent chaque geste du quotidien avec bienveillance.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-emoji-smile"></i></div>
          <div class="ws-service-body">
            <h4>Animation & loisirs</h4>
            <p>Activités variées : ateliers créatifs, gymnastique douce, sorties, musique, jeux de société — pour stimuler et maintenir le lien social.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-moon-stars"></i></div>
          <div class="ws-service-body">
            <h4>Veille de nuit</h4>
            <p>Équipe de nuit dédiée de 20h15 à 7h15, garantissant sécurité et sérénité pour tous les résidents, avec rondes régulières.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-capsule"></i></div>
          <div class="ws-service-body">
            <h4>Suivi médical</h4>
            <p>Collaboration étroite avec les médecins traitants, spécialistes et pharmaciens. Gestion rigoureuse des traitements et dossiers médicaux.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ws-service-card">
          <div class="ws-service-icon"><i class="bi bi-chat-heart"></i></div>
          <div class="ws-service-body">
            <h4>Soutien aux familles</h4>
            <p>Écoute, conseil et accompagnement des proches. Des entretiens réguliers pour maintenir le lien et informer sur l'évolution des soins.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ VIE QUOTIDIENNE ═══ -->
<section class="ws-section" id="life">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-sun"></i> Au quotidien</div>
      <h2 class="ws-section-title">Une journée à <span class="ws-accent">La Terrassière</span></h2>
      <p class="ws-section-desc">
        Chaque journée est rythmée par des moments de soins, de partage et de détente,
        dans le respect du rythme de chacun.
      </p>
    </div>
    <div class="ws-timeline">
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">7h00</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Réveil en douceur</h5>
          <p>L'équipe de jour prend le relais. Aide au lever, toilette, habillage selon les besoins de chaque résident.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">8h00</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Petit-déjeuner</h5>
          <p>Repas servi en salle commune ou en chambre. Menus adaptés aux régimes et préférences alimentaires.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">9h30</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Soins & activités du matin</h5>
          <p>Soins infirmiers, visites médicales, kiné. En parallèle : ateliers mémoire, gymnastique douce, lecture.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">12h00</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Déjeuner</h5>
          <p>Repas équilibrés préparés sur place. Moment convivial de partage entre résidents.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">14h00</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Animations de l'après-midi</h5>
          <p>Ateliers créatifs, musique, jeux, sorties au jardin. Temps libre pour les visites des proches.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">18h30</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Dîner & soirée</h5>
          <p>Repas du soir suivi d'un moment de détente. Préparation au coucher selon le rythme de chacun.</p>
        </div>
      </div>
      <div class="ws-timeline-item">
        <div class="ws-timeline-time">20h15</div>
        <div class="ws-timeline-dot"></div>
        <div class="ws-timeline-content">
          <h5>Équipe de nuit</h5>
          <p>Relève de l'équipe de nuit. Rondes de surveillance, disponibilité continue jusqu'au matin.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ VIDEO DIVIDER 2 ═══ -->
<section class="ws-video-divider">
  <video autoplay muted loop playsinline>
    <source src="assets/video/229071_medium.mp4" type="video/mp4">
  </video>
  <div class="ws-video-divider-overlay"></div>
  <div class="ws-video-divider-content">
    <div class="ws-video-divider-badge"><i class="bi bi-heart-pulse"></i></div>
    <blockquote>« Chaque jour, nous cultivons le bien-être et la dignité de nos résidents. »</blockquote>
  </div>
</section>

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
    const API = '/zerdatime/website/api.php';
    let currentWeekOffset = 0;
    let carouselPos = 0;
    let menusCache = [];
    let authCache = null; // { email, password, resident }

    function getMonday(offset) {
        const d = new Date();
        const day = d.getDay();
        d.setDate(d.getDate() - day + (day === 0 ? -6 : 1) + offset * 7);
        d.setHours(0,0,0,0);
        return d;
    }

    function fmtDate(d) {
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    }
    function fmtDateFr(d) {
        return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear();
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

    // ── Week nav ──
    function updateWeekLabel() {
        const mon = getMonday(currentWeekOffset);
        const sun = new Date(mon); sun.setDate(sun.getDate()+6);
        const label = document.getElementById('wsMenuWeekLabel');
        if (label) {
            const weekLabel = currentWeekOffset === 0 ? 'Cette semaine' :
                currentWeekOffset === 1 ? 'Semaine prochaine' :
                'Semaine du ' + fmtDateFr(mon);
            label.textContent = weekLabel + ' — ' + fmtDateFr(mon) + ' au ' + fmtDateFr(sun);
        }
    }

    document.getElementById('wsMenuPrev')?.addEventListener('click', () => {
        if (currentWeekOffset > 0) { currentWeekOffset--; carouselPos = 0; loadWeek(); }
    });
    document.getElementById('wsMenuNext')?.addEventListener('click', () => {
        if (currentWeekOffset < 3) { currentWeekOffset++; carouselPos = 0; loadWeek(); }
    });

    // ── Carousel arrows ──
    document.getElementById('wsCarouselLeft')?.addEventListener('click', () => slideCarousel(-1));
    document.getElementById('wsCarouselRight')?.addEventListener('click', () => slideCarousel(1));

    function slideCarousel(dir) {
        const newPos = carouselPos + dir;
        // Passage à la semaine suivante quand on dépasse la fin
        if (newPos > 4 && currentWeekOffset < 3) {
            currentWeekOffset++;
            carouselPos = 0;
            loadWeek();
            return;
        }
        // Passage à la semaine précédente quand on dépasse le début
        if (newPos < 0 && currentWeekOffset > 0) {
            currentWeekOffset--;
            carouselPos = 4;
            loadWeek();
            return;
        }
        carouselPos = Math.max(0, Math.min(newPos, 4));
        updateCarouselPosition();
    }

    function updateCarouselPosition() {
        const track = document.getElementById('wsCarouselTrack');
        if (!track) return;
        track.style.transform = 'translateX(-' + (carouselPos * (100/3)) + '%)';
    }

    // ── Load menus ──
    async function loadWeek() {
        updateWeekLabel();
        const mon = getMonday(currentWeekOffset);
        const res = await apiCall('get_menus_semaine', { date: fmtDate(mon) });
        menusCache = res.menus || [];

        const menusByKey = {};
        menusCache.forEach(m => { menusByKey[m.date_jour+'_'+(m.repas||'midi')] = m; });

        const track = document.getElementById('wsCarouselTrack');
        if (!track) return;
        const today = fmtDate(new Date());

        const MEAL_PRICE = { midi: 'CHF 14.50', soir: 'CHF 11.00' };

        track.innerHTML = '';
        for (let i = 0; i < 7; i++) {
            const d = new Date(mon); d.setDate(d.getDate()+i);
            const dateStr = fmtDate(d);
            const isToday = dateStr === today;
            const isPast = dateStr < today;
            const dayName = DAYS[d.getDay()];
            const midi = menusByKey[dateStr+'_midi'];
            const soir = menusByKey[dateStr+'_soir'];
            const dayLabel = dayName + ' ' + d.getDate()+'/'+(d.getMonth()+1);

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
                + '<button class="ws-btn ws-btn-reserve" data-date="'+dateStr+'" data-day="'+esc(dayLabel)+'"'+(isPast?' disabled':'')+'>'+
                '<i class="bi bi-calendar-plus"></i> Réserver un repas</button>'
                + '</div></div>';

            track.appendChild(wrapper);
        }

        // Reserve buttons (only non-disabled)
        track.querySelectorAll('.ws-btn-reserve:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => openReservation(btn.dataset.date, btn.dataset.day));
        });

        // Equal height via JS
        requestAnimationFrame(() => {
            const inners = track.querySelectorAll('.ws-card-inner');
            let maxH = 0;
            inners.forEach(el => { el.style.height = 'auto'; });
            inners.forEach(el => { if (el.offsetHeight > maxH) maxH = el.offsetHeight; });
            if (maxH) inners.forEach(el => { el.style.height = maxH + 'px'; });
        });

        updateCarouselPosition();
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

    // Init
    loadWeek();
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
    <div class="ws-team-cta">
      <h4><i class="bi bi-feather"></i> Rejoignez notre équipe</h4>
      <p>Nous sommes toujours à la recherche de professionnels motivés et bienveillants.</p>
      <a href="#contact" class="ws-btn ws-btn-primary"><i class="bi bi-envelope"></i> Postuler</a>
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
<section class="ws-section ws-section-alt" id="values">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-award"></i> Nos valeurs</div>
      <h2 class="ws-section-title">Ce qui nous <span class="ws-accent">guide</span></h2>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3">
        <div class="ws-value-card">
          <div class="ws-value-icon"><i class="bi bi-shield-heart"></i></div>
          <h5>Respect</h5>
          <p>Chaque résident est unique. Nous respectons sa dignité, ses choix et son rythme de vie.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-value-card">
          <div class="ws-value-icon"><i class="bi bi-brightness-high"></i></div>
          <h5>Bienveillance</h5>
          <p>Un accompagnement chaleureux et attentionné, dans un climat de confiance et de sécurité.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-value-card">
          <div class="ws-value-icon"><i class="bi bi-graph-up-arrow"></i></div>
          <h5>Excellence</h5>
          <p>Formation continue, protocoles actualisés et amélioration constante de nos pratiques.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="ws-value-card">
          <div class="ws-value-icon"><i class="bi bi-puzzle"></i></div>
          <h5>Collaboration</h5>
          <p>Travail d'équipe entre soignants, familles et médecins pour un accompagnement global.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ CONTACT ═══ -->
<section class="ws-section" id="contact">
  <div class="container">
    <div class="ws-section-header">
      <div class="ws-section-badge"><i class="bi bi-chat-dots"></i> Contactez-nous</div>
      <h2 class="ws-section-title">Nous sommes à votre <span class="ws-accent">écoute</span></h2>
      <p class="ws-section-desc">
        Pour toute question, demande de visite ou renseignement sur nos prestations,
        n'hésitez pas à nous contacter.
      </p>
    </div>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="ws-contact-info">
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
              <h5>Adresse</h5>
              <p>E.M.S. La Terrassière SA<br>Genève, Suisse</p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-telephone-fill"></i></div>
            <div>
              <h5>Téléphone</h5>
              <p>+41 22 XXX XX XX</p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-envelope-fill"></i></div>
            <div>
              <h5>Email</h5>
              <p>contact@ems-la-terrassiere.ch</p>
            </div>
          </div>
          <div class="ws-contact-item">
            <div class="ws-contact-ic"><i class="bi bi-clock-fill"></i></div>
            <div>
              <h5>Horaires de visite</h5>
              <p>Tous les jours : 10h – 12h / 14h – 19h<br>
              <small class="text-muted">Horaires flexibles sur demande</small></p>
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

<!-- ═══ FOOTER ═══ -->
<footer class="ws-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="ws-footer-brand">
          <img src="EMS-Terrassire-SA-logo-web-1920w.png" alt="E.M.S. La Terrassière SA" class="ws-footer-logo">
        </div>
        <p class="ws-footer-desc">
          Établissement médico-social au service des personnes âgées à Genève.
          Un lieu de vie où chacun trouve écoute, soins et chaleur humaine.
        </p>
      </div>
      <div class="col-lg-2 col-md-4">
        <h6>Navigation</h6>
        <ul class="ws-footer-links">
          <li><a href="#hero">Accueil</a></li>
          <li><a href="#about">Notre mission</a></li>
          <li><a href="#services">Nos soins</a></li>
          <li><a href="#life">Vie quotidienne</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-md-4">
        <h6>L'EMS</h6>
        <ul class="ws-footer-links">
          <li><a href="#modules">Organisation</a></li>
          <li><a href="#team">Notre équipe</a></li>
          <li><a href="#values">Nos valeurs</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-4">
        <h6>Contact</h6>
        <ul class="ws-footer-links">
          <li><i class="bi bi-geo-alt"></i> Genève, Suisse</li>
          <li><i class="bi bi-telephone"></i> +41 22 XXX XX XX</li>
          <li><i class="bi bi-envelope"></i> contact@ems-la-terrassiere.ch</li>
        </ul>
      </div>
    </div>
    <div class="ws-footer-bottom">
      <p>&copy; <?= date('Y') ?> E.M.S. La Terrassière SA — Tous droits réservés</p>
      <p class="ws-footer-legal">Conforme LPD/RGPD — Hébergement Suisse</p>
    </div>
  </div>
</footer>

<!-- ═══ FLOATING SIDE WIDGET ═══ -->
<div class="ws-side-widget" id="wsSideWidget">
  <a href="#contact" class="ws-sw-btn" title="Contactez-nous">
    <i class="bi bi-envelope-fill"></i>
    <span class="ws-sw-label">Contactez-nous</span>
  </a>
  <a href="#menu-semaine" class="ws-sw-btn" title="Menu de la semaine">
    <i class="bi bi-egg-fried"></i>
    <span class="ws-sw-label">Menu de la semaine</span>
  </a>
  <a href="#programme-animation" class="ws-sw-btn" title="Programme animation">
    <i class="bi bi-calendar-event"></i>
    <span class="ws-sw-label">Programme animation</span>
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

<script src="../assets/js/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/website.js"></script>
</body>
</html>
