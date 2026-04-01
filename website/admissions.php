<?php
require_once __DIR__ . '/../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admissions — <?= h($emsNom) ?></title>
<meta name="description" content="Informations sur les admissions, documents requis, conditions d'entrée et questions fréquentes sur l'EMS La Terrassière SA à Genève.">
<meta name="robots" content="index, follow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/zerdatime/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/zerdatime/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/zerdatime/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
:root {
  --adm-green: #2E7D32;
  --adm-green-light: rgba(46,125,50,0.06);
  --adm-green-border: rgba(46,125,50,0.12);
  --adm-bg: #FAFDF7;
  --adm-surface: #FFFFFF;
  --adm-text: #1A2E1A;
  --adm-text-secondary: #4A6548;
  --adm-text-muted: #7E9A7A;
  --adm-border: #D8E8D0;
  --adm-radius: 16px;
  --adm-shadow: 0 1px 3px rgba(46,125,50,0.04), 0 1px 2px rgba(0,0,0,0.02);
  --adm-shadow-md: 0 4px 12px rgba(46,125,50,0.08), 0 2px 4px rgba(0,0,0,0.04);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--adm-bg); color: var(--adm-text); line-height: 1.7; }

/* ── Shell ── */
.adm-shell { max-width: 900px; margin: 0 auto; padding: 40px 20px 60px; }

/* ── Hero ── */
.adm-hero { text-align: center; margin-bottom: 48px; }
.adm-hero h1 { font-size: 2rem; font-weight: 700; margin-bottom: 12px; }
.adm-hero h1 i { color: var(--adm-green); }
.adm-hero p { font-size: 1.05rem; color: var(--adm-text-secondary); max-width: 650px; margin: 0 auto; }

/* ── Section ── */
.adm-section { margin-bottom: 40px; }
.adm-section-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.adm-section-title i { color: var(--adm-green); font-size: 1.1rem; }

/* ── Card ── */
.adm-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 28px; box-shadow: var(--adm-shadow); margin-bottom: 20px; }

/* ── Documents list ── */
.adm-doc-list { list-style: none; padding: 0; margin: 0; }
.adm-doc-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(46,125,50,0.08); }
.adm-doc-item:last-child { border: none; }
.adm-doc-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--adm-green-light); color: var(--adm-green); display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.adm-doc-name { font-weight: 600; font-size: .92rem; }
.adm-doc-name a { color: var(--adm-green); text-decoration: none; }
.adm-doc-name a:hover { text-decoration: underline; }

/* ── FAQ ── */
.adm-faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .adm-faq-grid { grid-template-columns: 1fr; } }
.adm-faq-item { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 20px; }
.adm-faq-item h6 { font-size: .92rem; font-weight: 700; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; }
.adm-faq-item h6 i { color: var(--adm-green); margin-top: 2px; flex-shrink: 0; }
.adm-faq-item p { font-size: .88rem; color: var(--adm-text-secondary); margin: 0; }
.adm-faq-item a { color: var(--adm-green); font-weight: 600; text-decoration: none; }
.adm-faq-item a:hover { text-decoration: underline; }

/* ── Liens utiles ── */
.adm-links-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 768px) { .adm-links-grid { grid-template-columns: 1fr; } }
.adm-link-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: 20px; }
.adm-link-card h6 { font-size: .88rem; font-weight: 700; margin-bottom: 8px; color: var(--adm-green); }
.adm-link-card p { font-size: .82rem; color: var(--adm-text-secondary); margin-bottom: 2px; }
.adm-link-card a { color: var(--adm-green); font-size: .82rem; text-decoration: none; font-weight: 600; }
.adm-link-card a:hover { text-decoration: underline; }

/* ── Note ── */
.adm-note { background: var(--adm-green-light); border: 1px solid var(--adm-green-border); border-radius: 12px; padding: 16px 20px; font-size: .9rem; color: var(--adm-text-secondary); margin-top: 12px; }
.adm-note i { color: var(--adm-green); margin-right: 6px; }

@media (max-width: 576px) {
  .adm-hero h1 { font-size: 1.5rem; }
  .adm-card { padding: 20px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="adm-shell">

  <!-- ═══ HERO ═══ -->
  <div class="adm-hero">
    <h1><i class="bi bi-clipboard-check"></i> Admissions</h1>
    <p>Toutes les informations et documents pour préparer une admission à l'EMS La Terrassière SA. N'hésitez pas à nous contacter pour toute question.</p>
  </div>

  <!-- ═══ DOCUMENTS REQUIS ═══ -->
  <div class="adm-section">
    <h3 class="adm-section-title"><i class="bi bi-folder2-open"></i> Dossier d'inscription</h3>
    <div class="adm-card">
      <p style="margin-bottom:16px">L'inscription se fait sur la base d'un dossier complet. Les documents suivants sont à télécharger et à nous retourner :</p>
      <ul class="adm-doc-list">
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-text"></i></div>
          <div class="adm-doc-name"><a href="#">Demande d'inscription</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-text"></i></div>
          <div class="adm-doc-name"><a href="#">300-04 Formulaire d'inscription administrative en EMS</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-medical"></i></div>
          <div class="adm-doc-name"><a href="#">Rapport medical & formulaire activites de la vie quotidienne (AVQ)</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-list-check"></i></div>
          <div class="adm-doc-name"><a href="#">Recapitulatif des documents a fournir</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-file-earmark-ruled"></i></div>
          <div class="adm-doc-name"><a href="#">Contrat-type d'accueil</a></div>
        </li>
        <li class="adm-doc-item">
          <div class="adm-doc-icon"><i class="bi bi-shield-check"></i></div>
          <div class="adm-doc-name"><a href="#">Reglement interne</a></div>
        </li>
      </ul>
      <div class="adm-note">
        <i class="bi bi-info-circle"></i> La reception peut egalement vous envoyer ces documents sur demande au <strong>022 718 62 00</strong>. L'inscription sur la liste d'attente deviendra effective a la reception du dossier complet.
      </div>
    </div>
  </div>

  <!-- ═══ FAQ ═══ -->
  <div class="adm-section">
    <h3 class="adm-section-title"><i class="bi bi-question-circle"></i> Questions frequentes</h3>
    <div class="adm-faq-grid">

      <div class="adm-faq-item">
        <h6><i class="bi bi-building"></i> Puis-je visiter l'etablissement ?</h6>
        <p>Certainement ! Vous trouverez nos coordonnees en cliquant ici : <a href="/zerdatime/website/#contact">Contactez-nous</a></p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-bank"></i> Quelles demarches aupres du SPC ?</h6>
        <p>Vous trouverez tous les renseignements souhaites sur <a href="https://www.ge.ch/prestations-complementaires-avsai/demander-prestations-complementaires-avsai" target="_blank" rel="noopener">www.ge.ch</a></p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-person-check"></i> Conditions d'entree ?</h6>
        <p>Avoir l'age de l'AVS.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-box-seam"></i> Peut-on apporter ses meubles ?</h6>
        <p>Oui, sauf le lit et la table de chevet que nous fournissons. Lors de la visite, vous aurez la possibilite de poser toutes les questions relatives a votre installation.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-currency-exchange"></i> Quel est le prix de pension ?</h6>
        <p>Le prix de pension est un prix unique, quelle que soit la chambre occupee ou la situation financiere du residant. Il est fixe chaque annee par l'Etat.</p>
      </div>

      <div class="adm-faq-item">
        <h6><i class="bi bi-emoji-heart-eyes"></i> Acceptez-vous les animaux ?</h6>
        <p>Non. Nous n'avons, malheureusement, pas assez d'espace pour nos amies les betes.</p>
      </div>

    </div>
  </div>

  <!-- ═══ LIENS & ADRESSES UTILES ═══ -->
  <div class="adm-section">
    <h3 class="adm-section-title"><i class="bi bi-link-45deg"></i> Liens et adresses utiles</h3>
    <p style="color:var(--adm-text-secondary);margin-bottom:20px;font-size:.92rem">Pour obtenir des informations complementaires sur les aides et la prise en charge des personnes agees et/ou dependantes :</p>

    <div class="adm-links-grid">
      <div class="adm-link-card">
        <h6>SPC/OCPA</h6>
        <p><strong>Service des prestations complementaires</strong></p>
        <p>54, rte de chene<br>case postale 378<br>1211 Geneve 29</p>
        <p>Tel. 022 546 16 00<br>Fax. 022 546 17 00</p>
        <a href="https://www.ge.ch" target="_blank" rel="noopener">www.ge.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>DSC</h6>
        <p><strong>Departement de la cohesion sociale</strong></p>
        <p>2, rue de l'Hotel-de-Ville<br>case postale 3965<br>1211 Geneve 3</p>
        <p>Tel. 022 327 93 10</p>
      </div>

      <div class="adm-link-card">
        <h6>SMC</h6>
        <p><strong>Service du medecin cantonal</strong></p>
        <p>24, avenue de Beau-Sejour<br>1206 Geneve</p>
        <p>Tel. 022 546 50 00</p>
      </div>

      <div class="adm-link-card">
        <h6>FEGEMS</h6>
        <p><strong>Federation genevoise des structures d'accompagnement pour seniors</strong></p>
        <p>12, Avenue Industrielle<br>1227 Carouge</p>
        <p>Tel. 022 718 18 70</p>
        <a href="https://www.fegems.ch" target="_blank" rel="noopener">www.fegems.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>PRO SENECTUTE</h6>
        <p>5B, route de Saint-Julien<br>1227 Carouge</p>
        <p>Tel. 022 807 05 65</p>
        <a href="https://www.pro-senectute.ch" target="_blank" rel="noopener">www.pro-senectute.ch</a>
      </div>

      <div class="adm-link-card">
        <h6>ASSOCIATION APPUIS AUX AINES</h6>
        <p>Boulevard Carl-Vogt 8<br>1205 Geneve</p>
        <p>Tel. 022 840 49 99<br>E-mail: info@appuis-aines.ch</p>
        <a href="https://www.appuis-aines.ch" target="_blank" rel="noopener">www.appuis-aines.ch</a>
      </div>
    </div>
  </div>

</div>

<!-- ═══ FOOTER ═══ -->
<?php include __DIR__ . '/includes/footer.php'; ?>


</body>
</html>
