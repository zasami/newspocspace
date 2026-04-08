<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config ORDER BY config_key");
$etabConfig = [];
foreach ($cfgRows as $r) {
    $etabConfig[$r['config_key']] = $r['config_value'];
}

$etabConfigModules = Db::fetchAll(
    "SELECT m.id, m.code, m.nom, m.ordre,
            u.id AS responsable_id,
            CONCAT(u.prenom, ' ', u.nom) AS responsable_nom
     FROM modules m
     LEFT JOIN user_modules um ON um.module_id = m.id AND um.is_principal = 1
     LEFT JOIN users u ON u.id = um.user_id AND u.role IN ('responsable','admin','direction')
     ORDER BY m.ordre"
);

$etabModules = Db::fetchAll("SELECT * FROM modules ORDER BY ordre");
foreach ($etabModules as &$m) {
    $m['etages'] = Db::fetchAll("SELECT * FROM etages WHERE module_id = ? ORDER BY ordre", [$m['id']]);
    foreach ($m['etages'] as &$e) {
        $e['groupes'] = Db::fetchAll("SELECT * FROM groupes WHERE etage_id = ? ORDER BY ordre", [$e['id']]);
    }
    unset($e);
}
unset($m);

$etabResponsables = Db::fetchAll(
    "SELECT id, prenom, nom, role FROM users WHERE is_active = 1 AND role IN ('responsable','admin','direction') ORDER BY nom, prenom"
);

$etabGeoPays    = Db::fetchAll("SELECT code, nom FROM geo_pays ORDER BY sort_order");
$etabGeoRegions = Db::fetchAll("SELECT id, pays_code, code, nom FROM geo_regions ORDER BY pays_code, sort_order");
?>
<style>
.feature-toggle-card{display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;border:1px solid var(--ss-border-light,#e5e7eb);border-radius:8px;background:var(--ss-bg-card,#fff);transition:border-color .2s,box-shadow .2s}
.feature-toggle-card:hover{border-color:var(--ss-teal,#00b4a0);box-shadow:0 2px 8px rgba(0,180,160,.08)}
.feature-toggle-info{display:flex;align-items:center;gap:0.75rem}
.feature-toggle-info>i{font-size:1.3rem;color:var(--ss-teal,#00b4a0);min-width:24px;text-align:center}
.feature-toggle-label{font-weight:600;font-size:0.9rem}
.ems-logo-wrapper{width:200px;height:200px;border-radius:14px;border:2.5px dashed #D4C4A8;display:flex;align-items:center;justify-content:center;overflow:visible;background:#FAFAF8;position:relative;flex-shrink:0;cursor:pointer;transition:all .2s ease}
.ems-logo-wrapper:hover{border-color:#2d4a43;background:#F0EDE4}
.ems-logo-wrapper.has-logo{border-style:solid;border-color:var(--cl-border,#e0e0e0);cursor:default}
.ems-logo-wrapper.has-logo:hover{border-color:#C4BBA8}
.ems-logo-img{width:100%;height:100%;object-fit:contain;border-radius:12px}
.ems-logo-placeholder{display:flex;flex-direction:column;align-items:center;gap:6px;color:#C4BBA8;transition:color .2s}
.ems-logo-wrapper:hover .ems-logo-placeholder{color:#2d4a43}
.ems-logo-placeholder i{font-size:2rem}
.ems-logo-placeholder span{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.ems-logo-hover-overlay{position:absolute;inset:0;background:rgba(250,250,248,.75);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;pointer-events:none;border-radius:12px}
.ems-logo-wrapper:not(.has-logo):hover .ems-logo-hover-overlay{opacity:1}
.ems-logo-hover-overlay i{font-size:2.4rem;color:#2d4a43}
.ems-logo-actions{position:absolute;top:6px;right:6px;display:none;gap:4px;z-index:3}
.ems-logo-wrapper.has-logo:hover .ems-logo-actions{display:flex}
.ems-logo-action-btn{width:30px;height:30px;border-radius:8px;border:1px solid #D4C4A8;display:flex;align-items:center;justify-content:center;font-size:.75rem;cursor:pointer;transition:all .15s;background:#FAF8F4;color:#8B7D6B}
.ems-logo-action-btn:hover{background:#EDE8DF;color:#2d4a43}
.ems-logo-action-btn.delete:hover{background:rgba(155,44,44,.12);color:#9B2C2C;border-color:rgba(155,44,44,.25)}
.ems-separator{height:1px;background:linear-gradient(90deg,transparent,#D4C4A8 20%,#D4C4A8 80%,transparent);margin:4px 0}
.ems-logo-lightbox{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.ems-logo-lightbox.show{opacity:1;visibility:visible}
.ems-logo-lightbox img{max-width:90vw;max-height:80vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.4);transform:scale(.9);transition:transform .25s}
.ems-logo-lightbox.show img{transform:scale(1)}
.ems-logo-lightbox-close{position:absolute;top:20px;right:24px;width:36px;height:36px;border-radius:50%;border:none;background:rgba(255,255,255,.9);color:#333;font-size:1.1rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s}
.ems-logo-lightbox-close:hover{background:#fff}
.d-none-init{display:none}
.ems-input-w100{width:100px}
.ems-input-w70{width:70px}
.ems-text-085{font-size:0.85rem}
.ems-text-09{font-size:0.9rem}
.ems-text-082{font-size:0.82rem}
.ems-text-075{font-size:0.75rem;margin-bottom:3px}
.ems-cursor-pointer{cursor:pointer}
.ems-empty-icon{font-size:2rem;opacity:0.3}
</style>
<div id="etablissementPage">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> Configuration générale de l'établissement. Ces données sont utilisées dans tout le système.</p>
    <button class="btn btn-primary btn-sm" id="saveConfigBtn" disabled>
      <i class="bi bi-check-lg"></i> Enregistrer
    </button>
  </div>

  <!-- Identité -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-hospital"></i> Identité de l'établissement</div>
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Nom de l'établissement</label>
        <input type="text" class="form-control cfg" data-key="ems_nom" placeholder="Ex: Mon EMS">
        <div class="row g-2 mt-2">
          <div class="col-6">
            <label class="form-label">Type</label>
            <div class="zs-select" id="cfgEmsType" data-placeholder="— Choisir —"></div>
          </div>
          <div class="col-6">
            <label class="form-label">Nombre de lits</label>
            <input type="number" class="form-control cfg" data-key="ems_nb_lits" min="0">
          </div>
        </div>
      </div>
      <div class="col-md-4 d-flex flex-column align-items-center">
        <label class="form-label">Logo</label>
        <div class="ems-logo-wrapper" id="emsLogoPreview">
          <div class="ems-logo-placeholder" id="emsLogoPlaceholder">
            <i class="bi bi-plus-lg"></i>
            <span>Logo</span>
          </div>
          <img id="emsLogoImg" class="ems-logo-img d-none-init" alt="Logo">
          <div class="ems-logo-hover-overlay"><i class="bi bi-plus-lg"></i></div>
          <div class="ems-logo-actions">
            <button type="button" class="ems-logo-action-btn edit" id="emsLogoChangeBtn" title="Modifier"><i class="bi bi-pencil"></i></button>
            <button type="button" class="ems-logo-action-btn delete" id="emsLogoDeleteBtn" title="Supprimer"><i class="bi bi-trash3"></i></button>
          </div>
        </div>
        <input type="file" class="d-none" id="emsLogoFile" accept="image/*">
      </div>
      <div class="col-12"><div class="ems-separator"></div></div>
      <div class="col-md-8">
        <label class="form-label">Adresse</label>
        <input type="text" class="form-control cfg" data-key="ems_adresse" placeholder="Rue et numéro">
      </div>
      <div class="col-md-2">
        <label class="form-label">NPA</label>
        <input type="text" class="form-control cfg" data-key="ems_npa" placeholder="1209">
      </div>
      <div class="col-md-2">
        <label class="form-label">Ville</label>
        <input type="text" class="form-control cfg" data-key="ems_ville">
      </div>
      <div class="col-md-2">
        <label class="form-label">Pays</label>
        <div class="zs-select" id="cfgEmsPays" data-placeholder="— Choisir —"></div>
      </div>
      <div class="col-md-2">
        <label class="form-label" id="cfgCantonLabel">Canton</label>
        <div class="zs-select" id="cfgEmsCanton" data-placeholder="— Choisir —"></div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Téléphone</label>
        <input type="tel" class="form-control cfg" data-key="ems_telephone">
      </div>
      <div class="col-md-3">
        <label class="form-label">Fax</label>
        <input type="tel" class="form-control cfg" data-key="ems_fax">
      </div>
      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" class="form-control cfg" data-key="ems_email">
      </div>
      <div class="col-md-4">
        <label class="form-label">Site web</label>
        <input type="url" class="form-control cfg" data-key="ems_site_web" placeholder="https://...">
      </div>
    </div>
  </div>

  <!-- Direction -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-person-badge"></i> Direction</div>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Directeur/trice — Prénom</label>
        <input type="text" class="form-control cfg" data-key="directeur_prenom">
      </div>
      <div class="col-md-3">
        <label class="form-label">Nom</label>
        <input type="text" class="form-control cfg" data-key="directeur_nom">
      </div>
      <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control cfg" data-key="directeur_email">
      </div>
      <div class="col-md-3">
        <label class="form-label">Téléphone</label>
        <input type="tel" class="form-control cfg" data-key="directeur_telephone">
      </div>
    </div>
  </div>

  <!-- Infirmière chef -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-heart-pulse"></i> Infirmier/ère chef</div>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Prénom</label>
        <input type="text" class="form-control cfg" data-key="infirmiere_chef_prenom">
      </div>
      <div class="col-md-3">
        <label class="form-label">Nom</label>
        <input type="text" class="form-control cfg" data-key="infirmiere_chef_nom">
      </div>
      <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control cfg" data-key="infirmiere_chef_email">
      </div>
      <div class="col-md-3">
        <label class="form-label">Téléphone</label>
        <input type="tel" class="form-control cfg" data-key="infirmiere_chef_telephone">
      </div>
    </div>
  </div>

  <!-- RH -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-briefcase"></i> Responsable RH</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Prénom</label>
        <input type="text" class="form-control cfg" data-key="responsable_rh_prenom">
      </div>
      <div class="col-md-4">
        <label class="form-label">Nom</label>
        <input type="text" class="form-control cfg" data-key="responsable_rh_nom">
      </div>
      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" class="form-control cfg" data-key="responsable_rh_email">
      </div>
    </div>
  </div>

  <!-- Structure -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-building"></i> Structure — Modules & Unités</div>

    <!-- Step 1: Generator -->
    <div class="row g-3 align-items-end mb-3" id="structureGenerator">
      <div class="col-auto">
        <label class="form-label fw-600">Nombre d'étages</label>
        <input type="number" class="form-control ems-input-w100" id="genNbEtages" min="1" max="50" value="6">
      </div>
      <div class="col-auto">
        <label class="form-label fw-600">Nombre de modules</label>
        <input type="number" class="form-control ems-input-w100" id="genNbModules" min="1" max="50" value="4">
      </div>
      <div class="col-auto">
        <button class="btn btn-success" id="btnGenerateStructure">
          <i class="bi bi-magic"></i> Générer la structure
        </button>
      </div>
      <div class="col-12">
        <small class="text-muted">
          <i class="bi bi-info-circle"></i>
          La génération crée automatiquement les modules et répartit les étages équitablement.
          Vous pouvez ensuite personnaliser chaque module ci-dessous.
        </small>
      </div>
    </div>

    <hr>

    <!-- Step 2: Module cards (editable) -->
    <div id="moduleCards">
      <div class="text-center text-muted py-3"><span class="admin-spinner"></span> Chargement...</div>
    </div>
  </div>

  <!-- Planning rules -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-calendar3"></i> Règles du planning</div>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Heures/semaine (base 100%)</label>
        <input type="number" class="form-control cfg" data-key="planning_heures_semaine" min="20" max="60" step="0.5">
      </div>
      <div class="col-md-3">
        <label class="form-label">Repos min. / 7 jours</label>
        <input type="number" class="form-control cfg" data-key="planning_repos_minimum" min="1" max="3">
      </div>
      <div class="col-md-3">
        <label class="form-label">Jours consécutifs max.</label>
        <input type="number" class="form-control cfg" data-key="planning_jours_consecutifs_max" min="3" max="10">
      </div>
      <div class="col-md-3">
        <label class="form-label">Désirs max. / mois</label>
        <input type="number" class="form-control cfg" data-key="planning_desirs_max_mois" min="1" max="10">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ouverture désirs (jour du mois)</label>
        <input type="number" class="form-control cfg" data-key="planning_desirs_ouverture_jour" min="1" max="28">
      </div>
      <div class="col-md-3">
        <label class="form-label">Fermeture désirs (jour du mois)</label>
        <input type="number" class="form-control cfg" data-key="planning_desirs_fermeture_jour" min="1" max="28">
      </div>
    </div>
  </div>

  <!-- Feature toggles -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-toggles"></i> Fonctionnalités de l'EMS</div>
    <p class="text-muted mb-3 ems-text-085">Activez ou désactivez les modules selon les besoins de votre établissement. L'interface s'adaptera automatiquement.</p>
    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-star"></i>
            <div>
              <div class="feature-toggle-label">Désirs de planning</div>
              <small class="text-muted">Les collaborateurs peuvent soumettre des souhaits</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_desirs" id="ftDesirs">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-grid-3x3-gap"></i>
            <div>
              <div class="feature-toggle-label">Multi-modules</div>
              <small class="text-muted">Plusieurs modules/unités de soins</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_multi_modules" id="ftMultiModules">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-person-badge"></i>
            <div>
              <div class="feature-toggle-label">Civilistes</div>
              <small class="text-muted">Gestion du service civil</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_civilistes" id="ftCivilistes">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-calendar-x"></i>
            <div>
              <div class="feature-toggle-label">Absences</div>
              <small class="text-muted">Gestion des absences et maladies</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_absences" id="ftAbsences">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-arrow-left-right"></i>
            <div>
              <div class="feature-toggle-label">Changements d'horaire</div>
              <small class="text-muted">Échange de services entre collègues</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_changements" id="ftChangements">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-bar-chart"></i>
            <div>
              <div class="feature-toggle-label">Sondages</div>
              <small class="text-muted">Création et gestion de sondages</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_sondages" id="ftSondages">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-journal-text"></i>
            <div>
              <div class="feature-toggle-label">Procès-verbaux</div>
              <small class="text-muted">PV de séances et réunions</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_pv" id="ftPv">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-envelope"></i>
            <div>
              <div class="feature-toggle-label">Messagerie interne</div>
              <small class="text-muted">Emails internes entre collègues</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_emails" id="ftEmails">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-folder2-open"></i>
            <div>
              <div class="feature-toggle-label">Documents</div>
              <small class="text-muted">Partage de documents officiels</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_documents" id="ftDocuments">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-check2-square"></i>
            <div>
              <div class="feature-toggle-label">Votes de planning</div>
              <small class="text-muted">Système de vote sur les propositions</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_votes" id="ftVotes">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-car-front"></i>
            <div>
              <div class="feature-toggle-label">Covoiturage</div>
              <small class="text-muted">Organisation du covoiturage</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_covoiturage" id="ftCovoiturage">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-receipt"></i>
            <div>
              <div class="feature-toggle-label">Fiches de salaire</div>
              <small class="text-muted">Upload et gestion des fiches de paie</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_fiches_salaire" id="ftFichesSalaire">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-chat-square-heart"></i>
            <div>
              <div class="feature-toggle-label">Mur social</div>
              <small class="text-muted">Fil social interne entre collègues</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_mur_social" id="ftMurSocial">
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-toggle-card">
          <div class="feature-toggle-info">
            <i class="bi bi-megaphone"></i>
            <div>
              <div class="feature-toggle-label">Annonces officielles</div>
              <small class="text-muted">Communication direction → personnel</small>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cfg-toggle-input" type="checkbox" data-key="feature_annonces" id="ftAnnonces">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Interface CSS -->
  <!-- ═══ Rubrique épinglée (site web) ═══ -->
  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-pin-angle"></i> Rubrique épinglée — Site web</div>
    <p class="text-muted mb-3" style="font-size:.85rem">Cette rubrique s'affiche sur la page d'accueil du site web, avant la section "Formation continue". Vous pouvez y mettre un message, une annonce ou une félicitation.</p>
    <div class="row g-3">
      <div class="col-md-12">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="pinnedVisible" name="pinned_visible">
          <label class="form-check-label" for="pinnedVisible">Afficher la rubrique épinglée</label>
        </div>
      </div>
      <div class="col-md-8">
        <label class="form-label">Titre</label>
        <input type="text" class="form-control form-control-sm" id="pinnedTitle" name="pinned_title" placeholder="Ex: 🎓 Félicitations à nos diplômées ! 🎓">
      </div>
      <div class="col-md-4">
        <label class="form-label">Signature</label>
        <input type="text" class="form-control form-control-sm" id="pinnedSignature" name="pinned_signature" placeholder="Ex: Directrice">
      </div>
      <div class="col-12">
        <label class="form-label">Texte</label>
        <textarea class="form-control form-control-sm" id="pinnedText" name="pinned_text" rows="5" placeholder="Le message à afficher..."></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Image (optionnelle)</label>
        <div class="d-flex align-items-center gap-3">
          <div id="pinnedImgPreview" style="width:80px;height:80px;border-radius:12px;background:var(--cl-bg);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
            <i class="bi bi-image text-muted" style="font-size:1.5rem"></i>
          </div>
          <div>
            <label class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-upload"></i> Choisir une image
              <input type="file" id="pinnedImageFile" accept="image/*" style="display:none">
            </label>
            <button class="btn btn-sm btn-outline-danger" id="pinnedImageClear" style="display:none"><i class="bi bi-x"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title"><i class="bi bi-palette"></i> Framework CSS</div>
    <p class="text-muted mb-3 ems-text-085">Choisissez le framework CSS utilisé pour l'interface. Le mode Tailwind ajoute les utilitaires Tailwind CSS en complément du CSS actuel.</p>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="feature-toggle-card ems-cursor-pointer" data-css-mode="classic">
          <div class="feature-toggle-info">
            <i class="bi bi-brush"></i>
            <div>
              <div class="feature-toggle-label">CSS Classique</div>
              <small class="text-muted">Bootstrap + CSS personnalisé (par défaut)</small>
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="css_mode" value="classic" id="cssModeClassic">
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="feature-toggle-card ems-cursor-pointer" data-css-mode="tailwind">
          <div class="feature-toggle-info">
            <i class="bi bi-wind"></i>
            <div>
              <div class="feature-toggle-label">Tailwind CSS</div>
              <small class="text-muted">Utilitaires Tailwind avec préfixe <code>tw-</code> + CSS actuel</small>
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="css_mode" value="tailwind" id="cssModeTailwind">
          </div>
        </div>
      </div>
    </div>
    <div class="alert alert-info mt-3 mb-0 ems-text-085">
      <i class="bi bi-info-circle"></i> En mode Tailwind, utilisez le préfixe <code>tw-</code> (ex: <code>tw-flex tw-gap-4 tw-bg-white</code>). Le CSS classique reste chargé et fonctionnel dans les deux modes.
    </div>
  </div>

  <!-- Bottom save -->
  <div class="text-end mb-4">
    <button class="btn btn-primary" id="saveConfigBtn2">
      <i class="bi bi-check-lg"></i> Enregistrer toutes les modifications
    </button>
  </div>
</div>

<!-- Logo Lightbox -->
<div class="ems-logo-lightbox" id="emsLogoLightbox">
  <button type="button" class="ems-logo-lightbox-close" id="emsLogoLightboxClose"><i class="bi bi-x-lg"></i></button>
  <img id="emsLogoLightboxImg" src="" alt="Logo agrandi">
</div>

<script<?= nonce() ?>>
(function() {
    let config = <?= json_encode($etabConfig, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let geoPays = <?= json_encode(array_values($etabGeoPays), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let geoRegions = <?= json_encode(array_values($etabGeoRegions), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let modulesData = <?= json_encode(array_values($etabModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>; // modules with etages
    let configModules = <?= json_encode(array_values($etabConfigModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>; // from admin_get_config (with responsable)
    let allEtages = [];
    let responsables = <?= json_encode(array_values($etabResponsables), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let dirty = false;
    let editingModules = new Set(); // modules en mode édition

    function initEtablissementPage() {
        // Build allEtages flat list
        allEtages = [];
        modulesData.forEach(m => {
            (m.etages || []).forEach(e => {
                allEtages.push({ ...e, module_id: m.id });
            });
        });

        // Populate config fields
        document.querySelectorAll('.cfg').forEach(el => {
            const key = el.dataset.key;
            if (config[key] !== undefined) el.value = config[key];
        });

        // zerdaSelect: Type EMS
        zerdaSelect.init('#cfgEmsType', [
            { value: 'EMS', label: 'EMS' },
            { value: 'Hôpital', label: 'Hôpital' },
            { value: 'Clinique', label: 'Clinique' },
            { value: 'EHPAD', label: 'EHPAD' },
            { value: 'Autre', label: 'Autre' },
        ], { value: config.ems_type || 'EMS', onSelect: () => markDirty() });

        // zerdaSelect: Pays
        const paysOptions = geoPays.map(p => ({ value: p.code, label: p.nom }));
        zerdaSelect.init('#cfgEmsPays', paysOptions, {
            value: config.ems_pays || 'CH',
            onSelect: (val) => { updateCantonSelect(val); markDirty(); }
        });

        // zerdaSelect: Canton/Région (dynamique selon pays)
        function updateCantonSelect(paysCode) {
            const regions = geoRegions.filter(r => r.pays_code === paysCode);
            const label = document.getElementById('cfgCantonLabel');
            label.textContent = paysCode === 'FR' ? 'Région' : 'Canton';
            zerdaSelect.destroy('#cfgEmsCanton');
            zerdaSelect.init('#cfgEmsCanton', [
                { value: '', label: '— Aucun —' },
                ...regions.map(r => ({ value: r.code, label: r.nom }))
            ], { value: config.ems_canton || '', search: regions.length > 6, onSelect: () => markDirty() });
        }
        updateCantonSelect(config.ems_pays || 'CH');

        // Populate feature toggles
        document.querySelectorAll('.cfg-toggle-input').forEach(el => {
            const key = el.dataset.key;
            el.checked = config[key] === '1' || config[key] === 'true' || (config[key] === undefined);
        });

        // CSS mode radio
        const cssMode = config.css_mode || 'classic';
        const cssModeRadio = document.querySelector(`input[name="css_mode"][value="${cssMode}"]`);
        if (cssModeRadio) cssModeRadio.checked = true;
        document.querySelectorAll('[data-css-mode]').forEach(card => {
            card.addEventListener('click', () => {
                const val = card.dataset.cssMode;
                document.querySelector(`input[name="css_mode"][value="${val}"]`).checked = true;
                markDirty();
            });
        });
        document.querySelectorAll('input[name="css_mode"]').forEach(r => r.addEventListener('change', markDirty));

        // Pre-fill generator with current counts
        if (config.ems_nb_etages) document.getElementById('genNbEtages').value = config.ems_nb_etages;
        if (config.ems_nb_modules) document.getElementById('genNbModules').value = config.ems_nb_modules;

        // Pinned section
        const pv = document.getElementById('pinnedVisible');
        if (pv) pv.checked = config.pinned_visible === '1';
        const pt = document.getElementById('pinnedTitle');
        if (pt) pt.value = config.pinned_title || '';
        const ptxt = document.getElementById('pinnedText');
        if (ptxt) ptxt.value = config.pinned_text || '';
        const ps = document.getElementById('pinnedSignature');
        if (ps) ps.value = config.pinned_signature || '';

        // Pinned image preview
        if (config.pinned_image) {
            const prev = document.getElementById('pinnedImgPreview');
            prev.innerHTML = `<img src="${escapeHtml(config.pinned_image)}" style="width:100%;height:100%;object-fit:cover">`;
            document.getElementById('pinnedImageClear').style.display = '';
        }

        // Pinned image upload
        document.getElementById('pinnedImageFile')?.addEventListener('change', async (e) => {
            if (!e.target.files[0]) return;
            const fd = new FormData();
            fd.append('image', e.target.files[0]);
            fd.append('action', 'admin_upload_pinned_image');
            const res = await fetch('/spocspace/admin/api.php', {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
            }).then(r => r.json());
            e.target.value = '';
            if (res.success) {
                config.pinned_image = res.image_url;
                document.getElementById('pinnedImgPreview').innerHTML = `<img src="${escapeHtml(res.image_url)}" style="width:100%;height:100%;object-fit:cover">`;
                document.getElementById('pinnedImageClear').style.display = '';
                showToast('Image mise à jour', 'success');
            } else showToast(res.error || 'Erreur', 'danger');
        });

        document.getElementById('pinnedImageClear')?.addEventListener('click', () => {
            config.pinned_image = '';
            document.getElementById('pinnedImgPreview').innerHTML = '<i class="bi bi-image text-muted" style="font-size:1.5rem"></i>';
            document.getElementById('pinnedImageClear').style.display = 'none';
            markDirty();
        });

        [pv, pt, ptxt, ps].forEach(el => el?.addEventListener('input', markDirty));

        renderModuleCards();

        // Track config changes
        document.querySelectorAll('.cfg').forEach(el => {
            el.addEventListener('input', markDirty);
            el.addEventListener('change', markDirty);
        });
        document.querySelectorAll('.cfg-toggle-input').forEach(el => {
            el.addEventListener('change', markDirty);
        });

        // Logo preview
        function showLogo(url) {
            const img = document.getElementById('emsLogoImg');
            const ph = document.getElementById('emsLogoPlaceholder');
            const wrapper = document.getElementById('emsLogoPreview');
            if (url) {
                if (img) { img.src = url; img.classList.remove('d-none-init'); }
                if (ph) ph.classList.add('d-none');
                wrapper?.classList.add('has-logo');
            } else {
                if (img) { img.src = ''; img.classList.add('d-none-init'); }
                if (ph) ph.classList.remove('d-none');
                wrapper?.classList.remove('has-logo');
            }
        }

        if (config.ems_logo_url) showLogo(config.ems_logo_url);

        // Logo: wrapper click triggers file input (only when no logo)
        document.getElementById('emsLogoPreview')?.addEventListener('click', (e) => {
            if (e.target.closest('.ems-logo-actions')) return;
            if (document.getElementById('emsLogoPreview').classList.contains('has-logo')) return;
            document.getElementById('emsLogoFile').click();
        });

        // Logo change button
        document.getElementById('emsLogoChangeBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('emsLogoFile').click();
        });

        // Logo delete
        document.getElementById('emsLogoDeleteBtn')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!await adminConfirm({ title: 'Supprimer le logo', text: 'Voulez-vous supprimer le logo de l\'établissement ?', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
            const res = await adminApiPost('admin_save_config', { values: { ems_logo_url: '' } });
            if (res.success) {
                showLogo('');
                showToast('Logo supprimé', 'success');
            }
        });

        // Logo lightbox (zoom)
        const lightbox = document.getElementById('emsLogoLightbox');
        const lightboxImg = document.getElementById('emsLogoLightboxImg');
        function openLightbox() {
            const img = document.getElementById('emsLogoImg');
            if (!img || !img.src || img.classList.contains('d-none-init')) return;
            lightboxImg.src = img.src;
            lightbox.classList.add('show');
        }
        document.getElementById('emsLogoLightboxClose')?.addEventListener('click', () => lightbox.classList.remove('show'));
        lightbox?.addEventListener('click', (e) => { if (e.target === lightbox) lightbox.classList.remove('show'); });

        // Logo upload
        document.getElementById('emsLogoFile')?.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Instant preview
            const reader = new FileReader();
            reader.onload = (ev) => showLogo(ev.target.result);
            reader.readAsDataURL(file);

            const fd = new FormData();
            fd.append('logo', file);
            fd.append('action', 'admin_upload_logo');
            try {
                const res = await fetch('/spocspace/admin/api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': window.__SS_ADMIN__?.csrfToken || '' },
                    body: fd
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    showLogo(json.logo_url + '?t=' + Date.now());
                } else {
                    showToast(json.message || 'Erreur', 'error');
                }
            } catch (err) {
                showToast('Erreur d\'upload', 'error');
            }
            e.target.value = '';
        });

        document.getElementById('saveConfigBtn').addEventListener('click', saveConfig);
        document.getElementById('saveConfigBtn2').addEventListener('click', saveConfig);
        document.getElementById('btnGenerateStructure').addEventListener('click', generateStructure);

        updateSaveBtns();
    }

    function markDirty() { dirty = true; updateSaveBtns(); }

    function updateSaveBtns() {
        document.getElementById('saveConfigBtn').disabled = !dirty;
        document.getElementById('saveConfigBtn2').disabled = !dirty;
    }

    // ── Generate structure ──
    async function generateStructure() {
        const nbEtages = parseInt(document.getElementById('genNbEtages').value) || 0;
        const nbModules = parseInt(document.getElementById('genNbModules').value) || 0;

        if (nbEtages < 1 || nbModules < 1) {
            showToast('Entrez au moins 1 étage et 1 module', 'error');
            return;
        }

        if (!await adminConfirm({ title: 'Générer la structure', text: `Générer <strong>${nbModules} module(s)</strong> avec <strong>${nbEtages} étage(s)</strong> ?<br><br>Les étages seront répartis automatiquement entre les modules.<br><span class="text-danger fw-bold">Attention : cela remplace la structure existante.</span>`, icon: 'bi-building-gear', type: 'warning', okText: 'Générer' })) return;

        document.getElementById('moduleCards').innerHTML =
            '<div class="text-center py-3"><span class="admin-spinner"></span> Génération...</div>';

        const res = await adminApiPost('admin_generate_structure', {
            nb_etages: nbEtages,
            nb_modules: nbModules,
        });

        if (res.success) {
            showToast(res.message, 'success');
            modulesData = res.modules || [];

            // Rebuild allEtages
            allEtages = [];
            modulesData.forEach(m => {
                (m.etages || []).forEach(e => {
                    allEtages.push({ ...e, module_id: m.id });
                });
            });

            // Après génération, tous les modules en mode édition
            editingModules.clear();
            modulesData.forEach(m => editingModules.add(m.id));

            // Refresh configModules (responsable data)
            const cfgRes = await adminApiPost('admin_get_config');
            if (cfgRes.success) configModules = cfgRes.modules || [];

            renderModuleCards();
        } else {
            showToast(res.message || 'Erreur', 'error');
            renderModuleCards();
        }
    }

    // ── Render module cards ──
    function renderModuleCards() {
        const container = document.getElementById('moduleCards');

        if (!modulesData.length) {
            container.innerHTML = `<div class="text-center text-muted py-4">
                <i class="bi bi-building ems-empty-icon"></i>
                <p class="mt-2">Aucun module. Utilisez le générateur ci-dessus pour créer la structure.</p>
            </div>`;
            return;
        }

        // Responsable map from configModules
        const respMap = {};
        const respNameMap = {};
        configModules.forEach(cm => {
            if (cm.responsable_id) {
                respMap[cm.id] = cm.responsable_id;
                respNameMap[cm.id] = cm.responsable_nom || '';
            }
        });

        let html = '<div class="row g-3">';

        modulesData.forEach((m, idx) => {
            const etages = m.etages || [];
            const isEditing = editingModules.has(m.id);
            const currentResp = respMap[m.id] || '';
            const currentRespNom = respNameMap[m.id] || '';

            if (isEditing) {
                // ── MODE ÉDITION ──
                const etageChecks = allEtages.map(e => {
                    const checked = e.module_id === m.id ? 'checked' : '';
                    return `<label class="form-check form-check-inline ems-text-082">
                        <input class="form-check-input etage-check" type="checkbox" value="${e.id}" ${checked}>
                        ${escapeHtml(e.nom)}
                    </label>`;
                }).join('');


                html += `
                <div class="col-md-6 col-xl-4">
                  <div class="card module-card border-primary" data-module-id="${m.id}">
                    <div class="card-header d-flex align-items-center gap-2 py-2 bg-primary bg-opacity-10">
                      <span class="badge bg-primary">${idx + 1}</span>
                      <input type="text" class="form-control form-control-sm mod-code fw-bold ems-input-w70" value="${escapeHtml(m.code)}" title="Code">
                      <span class="text-muted">—</span>
                      <input type="text" class="form-control form-control-sm mod-nom flex-grow-1" value="${escapeHtml(m.nom)}" title="Nom du module">
                    </div>
                    <div class="card-body py-2">
                      <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-600">
                          <i class="bi bi-layers"></i> Étages assignés
                        </label>
                        <div class="etage-checks">${etageChecks}</div>
                      </div>
                      <div class="mb-1">
                        <label class="form-label form-label-sm mb-1 fw-600">
                          <i class="bi bi-person-badge"></i> Responsable
                        </label>
                        <div class="zs-select mod-resp" data-module-id="${m.id}" data-placeholder="— Aucun —"></div>
                      </div>
                    </div>
                    <div class="card-footer py-1 text-end">
                      <button class="btn btn-sm btn-outline-secondary btn-cancel-module me-1">
                        <i class="bi bi-x-lg"></i> Annuler
                      </button>
                      <button class="btn btn-sm btn-primary btn-save-module">
                        <i class="bi bi-check-lg"></i> Valider
                      </button>
                    </div>
                  </div>
                </div>`;
            } else {
                // ── MODE VUE ──
                const etagesBadges = etages.length
                    ? etages.map(e => `<span class="badge bg-info bg-opacity-25 text-dark border me-1 mb-1">${escapeHtml(e.nom)}</span>`).join('')
                    : '<span class="text-muted fst-italic ems-text-082">Aucun étage</span>';

                const respBadge = currentRespNom
                    ? `<span class="badge bg-success bg-opacity-25 text-dark border"><i class="bi bi-person-badge"></i> ${escapeHtml(currentRespNom)}</span>`
                    : '<span class="text-muted fst-italic ems-text-082">Non assigné</span>';

                html += `
                <div class="col-md-6 col-xl-4">
                  <div class="card module-card" data-module-id="${m.id}">
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                      <span class="badge bg-primary">${idx + 1}</span>
                      <span class="fw-bold text-uppercase ems-text-085">${escapeHtml(m.code)}</span>
                      <span class="text-muted">—</span>
                      <span class="flex-grow-1 ems-text-09">${escapeHtml(m.nom)}</span>
                    </div>
                    <div class="card-body py-2">
                      <div class="mb-2">
                        <div class="text-muted ems-text-075">
                          <i class="bi bi-layers"></i> Étages
                        </div>
                        <div>${etagesBadges}</div>
                      </div>
                      <div>
                        <div class="text-muted ems-text-075">
                          <i class="bi bi-person-badge"></i> Responsable
                        </div>
                        ${respBadge}
                      </div>
                    </div>
                    <div class="card-footer py-1 text-end">
                      <button class="btn btn-sm btn-outline-primary btn-edit-module">
                        <i class="bi bi-pencil"></i> Modifier
                      </button>
                    </div>
                  </div>
                </div>`;
            }
        });

        html += '</div>';
        container.innerHTML = html;

        // Init zerdaSelect for responsable in editing cards
        container.querySelectorAll('.zs-select.mod-resp').forEach(el => {
            const modId = el.dataset.moduleId;
            const currentResp = allModules.find(m => m.id === modId)?.responsable_id || '';
            const respOptsData = responsables.map(u => ({ value: u.id, label: u.prenom + ' ' + u.nom }));
            zerdaSelect.init(el, [
                { value: '', label: '— Aucun —' },
                ...respOptsData
            ], { value: currentResp, search: responsables.length > 6 });
        });

        // Attach handlers
        container.querySelectorAll('.btn-save-module').forEach(btn => {
            btn.addEventListener('click', function() {
                saveModuleCard(this.closest('.module-card'));
            });
        });
        container.querySelectorAll('.btn-edit-module').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.closest('.module-card').dataset.moduleId;
                editingModules.add(id);
                renderModuleCards();
            });
        });
        container.querySelectorAll('.btn-cancel-module').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.closest('.module-card').dataset.moduleId;
                editingModules.delete(id);
                renderModuleCards();
            });
        });
    }

    async function saveModuleCard(card) {
        const moduleId = card.dataset.moduleId;
        const code = card.querySelector('.mod-code').value.trim();
        const nom = card.querySelector('.mod-nom').value.trim();
        const respEl = card.querySelector('.mod-resp');
        const respId = zerdaSelect.getValue(respEl) || '';

        // Checked etage IDs
        const etageIds = [];
        card.querySelectorAll('.etage-check:checked').forEach(cb => {
            etageIds.push(cb.value);
        });

        if (!code || !nom) {
            showToast('Code et nom requis', 'error');
            return;
        }

        const btn = card.querySelector('.btn-save-module');
        btn.disabled = true;
        btn.innerHTML = '<span class="admin-spinner"></span>';

        const res = await adminApiPost('admin_update_module_config', {
            module_id: moduleId,
            code: code,
            nom: nom,
            etage_ids: etageIds,
            responsable_id: respId,
        });

        if (res.success) {
            showToast('Module mis à jour', 'success');

            // Sortir du mode édition
            editingModules.delete(moduleId);

            // Refresh structure data
            const modulesRes = await adminApiPost('admin_get_modules');
            if (modulesRes.success) {
                modulesData = modulesRes.modules || [];
                allEtages = [];
                modulesData.forEach(m2 => {
                    (m2.etages || []).forEach(e => {
                        allEtages.push({ ...e, module_id: m2.id });
                    });
                });
                const cfgRes = await adminApiPost('admin_get_config');
                if (cfgRes.success) configModules = cfgRes.modules || [];
                renderModuleCards();
            }
        } else {
            showToast(res.message || 'Erreur', 'error');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Valider';
            btn.disabled = false;
        }
    }

    async function saveConfig() {
        const values = {};
        document.querySelectorAll('.cfg').forEach(el => {
            const key = el.dataset.key;
            if (key) values[key] = el.value;
        });
        // zerdaSelect values
        values.ems_type = zerdaSelect.getValue('#cfgEmsType') || 'EMS';
        values.ems_pays = zerdaSelect.getValue('#cfgEmsPays') || 'CH';
        values.ems_canton = zerdaSelect.getValue('#cfgEmsCanton') || '';
        // Feature toggles
        document.querySelectorAll('.cfg-toggle-input').forEach(el => {
            const key = el.dataset.key;
            if (key) values[key] = el.checked ? '1' : '0';
        });
        // CSS mode
        const selectedCssMode = document.querySelector('input[name="css_mode"]:checked');
        if (selectedCssMode) values.css_mode = selectedCssMode.value;
        // Pinned section
        values.pinned_visible = document.getElementById('pinnedVisible')?.checked ? '1' : '0';
        values.pinned_title = document.getElementById('pinnedTitle')?.value || '';
        values.pinned_text = document.getElementById('pinnedText')?.value || '';
        values.pinned_signature = document.getElementById('pinnedSignature')?.value || '';
        values.pinned_image = config.pinned_image || '';

        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) {
            showToast(res.message, 'success');
            dirty = false;
            updateSaveBtns();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    window.initEtablissementPage = initEtablissementPage;
})();
</script>
