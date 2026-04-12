<?php
// Charge les styles footer si pas déjà présents
if (!defined('WS_FOOTER_STYLES_LOADED')) {
    include __DIR__ . '/footer-styles.php';
}

// Liste des partenaires pour le carousel
$wsPartenaires = [
  ['name' => 'République et Canton de Genève', 'file' => 'canton-geneve.webp'],
  ['name' => 'Conseil du Canton de Genève',    'file' => 'cdc-ge.webp'],
  ['name' => 'FEGEMS',                          'file' => 'fegems.webp'],
  ['name' => 'Eldora',                          'file' => 'eldora.webp'],
  ['name' => 'Label Fait Maison',               'file' => 'fait-maison.webp'],
  ['name' => '1+ pour tous',                    'file' => '1plus-pour-tous.webp'],
  ['name' => 'SQS',                             'file' => 'sqs.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-01.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-02.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-03.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-04.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-05.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-06.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-07.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-08.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-09.webp'],
  ['name' => 'Partenaire',                      'file' => 'sponsor-10.webp'],
];
?>

<!-- ═══ CAROUSEL PARTENAIRES ═══ -->
<section class="ws-partners" aria-label="Nos partenaires">
  <div class="ws-partners-inner">
    <h4 class="ws-partners-title">Nos partenaires</h4>
    <div class="ws-partners-carousel">
      <div class="ws-partners-track" id="wsPartnersTrack">
        <?php
        // Rendu double pour boucle infinie
        for ($i = 0; $i < 2; $i++):
          foreach ($wsPartenaires as $p): ?>
            <div class="ws-partner-item">
              <img src="/spocspace/website/assets/img/partenaires/<?= h($p['file']) ?>"
                   alt="<?= h($p['name']) ?>"
                   loading="lazy">
            </div>
        <?php endforeach;
        endfor; ?>
      </div>
    </div>
  </div>
</section>

<footer class="ws-footer" style="position:relative;overflow:hidden">
  <img src="/spocspace/website/assets/img/shape-2-soft-light.svg" alt="" aria-hidden="true" style="position:absolute;bottom:0;left:0;width:420px;opacity:.6;pointer-events:none">
  <img src="/spocspace/website/assets/img/shape-1-soft-light.svg" alt="" aria-hidden="true" style="position:absolute;top:20px;right:0;width:200px;opacity:.8;pointer-events:none">
  <div style="max-width:1200px;margin:0 auto;padding:0 20px;position:relative;z-index:1">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:30px">
      <div>
        <div class="ws-footer-brand">
          <img src="/spocspace/website/EMS-Terrassire-SA-logo-web-1920w.webp" alt="E.M.S. La Terrassière SA" class="ws-footer-logo">
        </div>
        <p class="ws-footer-desc">
          Établissement médico-social au service des personnes âgées à Genève.
          Un lieu de vie où chacun trouve écoute, soins et chaleur humaine.
        </p>
      </div>
      <div>
        <h6>Navigation</h6>
        <ul class="ws-footer-links">
          <li><a href="/spocspace/website/">Accueil</a></li>
          <li><a href="/spocspace/website/#about">Notre mission</a></li>
          <li><a href="/spocspace/website/#services">Nos soins</a></li>
          <li><a href="/spocspace/website/#team">Équipe</a></li>
        </ul>
      </div>
      <div>
        <h6>Services</h6>
        <ul class="ws-footer-links">
          <li><a href="/spocspace/website/admissions.php">Admissions</a></li>
          <li><a href="/spocspace/website/recrutement.php">Recrutement</a></li>
          <li><a href="/spocspace/website/famille.php">Espace Famille</a></li>
          <li><a href="/spocspace/">Espace Collaborateur</a></li>
          <li><a href="/spocspace/website/#contact">Contact</a></li>
        </ul>
      </div>
      <div>
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
