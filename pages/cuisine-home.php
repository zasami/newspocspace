<?php
require_once __DIR__ . "/../init.php";
if (empty($_SESSION["ss_user"])) { http_response_code(401); exit; }

$today = date('Y-m-d');
$dt = new DateTime($today);
$dow = (int)$dt->format('N');
$monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
$sunday = (clone $monday)->modify('+6 days');

// SSR: menus de la semaine
$ssrMenus = Db::fetchAll(
    "SELECT m.id, m.date_jour, m.repas, m.entree, m.plat, m.salade, m.accompagnement, m.dessert, m.remarques,
            (SELECT COUNT(*) FROM menu_reservations r WHERE r.menu_id = m.id AND r.statut = 'confirmee') AS nb_reservations,
            (SELECT SUM(r2.nb_personnes) FROM menu_reservations r2 WHERE r2.menu_id = m.id AND r2.statut = 'confirmee') AS total_couverts
     FROM menus m
     WHERE m.date_jour BETWEEN ? AND ?
     ORDER BY m.date_jour ASC, m.repas ASC",
    [$monday->format('Y-m-d'), $sunday->format('Y-m-d')]
);

// SSR: commandes du jour (midi)
$ssrCommandes = Db::fetchAll(
    "SELECT r.id, r.choix, r.nb_personnes, r.remarques, r.paiement, r.statut, r.created_at,
            u.prenom, u.nom, f.nom AS fonction_nom, f.code AS fonction_code
     FROM menu_reservations r
     JOIN menus m ON m.id = r.menu_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE m.date_jour = ? AND m.repas = 'midi' AND r.statut = 'confirmee'
     ORDER BY u.nom, u.prenom",
    [$today]
);

$ssrNbMenu   = count(array_filter($ssrCommandes, fn($r) => $r['choix'] === 'menu'));
$ssrNbSalade = count(array_filter($ssrCommandes, fn($r) => $r['choix'] === 'salade'));

// SSR: famille stats (midi)
$ssrFamille = Db::fetchAll(
    "SELECT rf.id FROM reservations_famille rf
     WHERE rf.date_jour = ? AND rf.repas = 'midi' AND rf.statut = 'confirmee'",
    [$today]
);

$ssrData = [
    'success'       => true,
    'menus'         => $ssrMenus,
    'semaine_debut' => $monday->format('Y-m-d'),
    'semaine_fin'   => $sunday->format('Y-m-d'),
    'reservations'  => $ssrCommandes,
    'nb_menu'       => $ssrNbMenu,
    'nb_salade'     => $ssrNbSalade,
    'nb_famille'    => count($ssrFamille),
];
?>
<div class="page-header">
  <h1>Bonjour <span id="chUserName"></span></h1>
  <p>Tableau de bord cuisine</p>
</div>

<!-- Stats cards -->
<div class="stats-grid" id="chStats">
  <div class="stat-card">
    <div class="stat-icon teal"><i class="bi bi-house-heart"></i></div>
    <div>
      <div class="stat-value" id="chStatFamille">—</div>
      <div class="stat-label">Réservations famille</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-egg-fried"></i></div>
    <div>
      <div class="stat-value" id="chStatMenu">—</div>
      <div class="stat-label">Commandes menu</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="bi bi-flower1"></i></div>
    <div>
      <div class="stat-value" id="chStatSalade">—</div>
      <div class="stat-label">Commandes salade</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="bi bi-journal-check"></i></div>
    <div>
      <div class="stat-value" id="chStatMenusSaisis">—</div>
      <div class="stat-label">Menus saisis / semaine</div>
    </div>
  </div>
</div>

<!-- Menu semaine cards -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 style="margin:0"><i class="bi bi-journal-text"></i> Menus de la semaine</h3>
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-sm btn-outline-secondary" id="chMenuPrev"><i class="bi bi-chevron-left"></i></button>
    <span id="chMenuWeekLabel" style="font-weight:600;min-width:180px;text-align:center"></span>
    <button class="btn btn-sm btn-outline-secondary" id="chMenuNext"><i class="bi bi-chevron-right"></i></button>
    <div class="dropdown ms-2">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer"></i> Imprimer</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#" id="chPrintDay"><i class="bi bi-calendar-day"></i> Menu du jour</a></li>
        <li><a class="dropdown-item" href="#" id="chPrintWeek"><i class="bi bi-calendar-week"></i> Menu de la semaine</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="ch-week-grid" id="chMenuCards"></div>

<!-- Commandes du jour -->
<div class="card mt-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 style="margin:0"><i class="bi bi-receipt"></i> Commandes du jour</h3>
    <div class="d-flex align-items-center gap-2">
      <select class="form-select form-select-sm" id="chRepas" style="width:auto">
        <option value="midi">Midi</option>
        <option value="soir">Soir</option>
      </select>
      <button class="btn btn-sm btn-primary" id="chAddCmdBtn"><i class="bi bi-plus-lg"></i> Ajouter</button>
      <button class="btn btn-sm btn-outline-secondary" id="chPrintCommandes" title="Imprimer commandes"><i class="bi bi-printer"></i></button>
    </div>
  </div>
  <div class="card-body" id="chCommandesBody" style="max-height:400px;overflow-y:auto">
    <div class="empty-state"><i class="bi bi-receipt"></i><p>Chargement...</p></div>
  </div>
</div>

<!-- Modal saisie/édition menu -->
<div class="modal fade" id="chMenuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title" id="chModalTitle">Menu</h5>
          <small class="text-muted" id="chModalSubtitle"></small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <input type="hidden" id="chEditDate">
        <input type="hidden" id="chEditRepas">
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-cup-hot"></i> Entrée</label>
          <input type="text" class="form-control" id="chEditEntree" placeholder="Ex: Soupe de légumes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-egg-fried"></i> Plat principal <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="chEditPlat" placeholder="Ex: Poulet rôti aux herbes">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold"><i class="bi bi-flower1"></i> Salade</label>
          <input type="text" class="form-control" id="chEditSalade" placeholder="Ex: Salade verte vinaigrette">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-grid-3x3"></i> Accompagnement</label>
            <input type="text" class="form-control" id="chEditAccomp" placeholder="Ex: Riz basmati">
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold"><i class="bi bi-cake2"></i> Dessert</label>
            <input type="text" class="form-control" id="chEditDessert" placeholder="Ex: Tarte aux pommes">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold"><i class="bi bi-info-circle"></i> Remarques</label>
          <textarea class="form-control" id="chEditRemarques" rows="2" placeholder="Allergènes, options végé..."></textarea>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="chEditSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal "Réutiliser ce menu" — choix du jour cible -->
<div class="modal fade" id="chReuseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Réutiliser ce menu</h5>
          <small class="text-muted">Copier le menu vers un autre jour</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <div class="mb-2">
          <label class="form-label small fw-bold">Date cible</label>
          <input type="date" class="form-control" id="chReuseDate">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-bold">Repas</label>
          <select class="form-select" id="chReuseRepas">
            <option value="midi">Midi</option>
            <option value="soir">Soir</option>
          </select>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="chReuseSaveBtn"><i class="bi bi-check-lg"></i> Copier</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal ajouter commande -->
<div class="modal fade" id="chCmdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
    <div class="modal-content" style="display:flex;flex-direction:column;max-height:85vh">
      <div class="modal-header" style="flex-shrink:0">
        <div>
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter une commande</h5>
          <small class="text-muted" id="chCmdSubtitle">Saisir une commande pour un collaborateur</small>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--ss-border)" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto">
        <!-- Recherche collaborateur -->
        <div style="margin-bottom:1rem;position:relative">
          <label class="form-label" style="font-weight:600">Collaborateur</label>
          <input type="text" class="form-control" id="chCmdUserSearch" placeholder="Chercher par nom ou prénom..." autocomplete="off">
          <input type="hidden" id="chCmdUserId">
          <div class="cuis-autocomplete-list" id="chCmdUserResults" style="max-height:200px;overflow-y:auto"></div>
        </div>
        <!-- Choix menu/salade -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Choix du repas</label>
          <div style="display:flex;gap:0.5rem">
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-teal);border-radius:10px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
              <input type="radio" name="chCmdChoix" value="menu" checked style="display:none">
              <i class="bi bi-egg-fried" style="font-size:1.2rem;color:var(--ss-orange)"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Menu du jour</div></div>
            </label>
            <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-border);border-radius:10px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdChoix" value="salade" style="display:none">
              <i class="bi bi-flower1" style="font-size:1.2rem;color:#16A34A"></i>
              <div><div style="font-weight:700;font-size:0.9rem">Salade</div></div>
            </label>
          </div>
        </div>
        <!-- Nb personnes -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Nombre de personnes</label>
          <select class="form-select" id="chCmdNb">
            <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
          </select>
        </div>
        <!-- Paiement -->
        <div style="margin-bottom:1rem">
          <label class="form-label" style="font-weight:600">Mode de paiement</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-teal);border-radius:8px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
              <input type="radio" name="chCmdPaiement" value="salaire" checked style="accent-color:var(--ss-teal)"> <i class="bi bi-wallet2"></i> Retenue salaire
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdPaiement" value="caisse" style="accent-color:var(--ss-teal)"> <i class="bi bi-cash-coin"></i> Cash caisse
            </label>
            <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
              <input type="radio" name="chCmdPaiement" value="carte" style="accent-color:var(--ss-teal)"> <i class="bi bi-credit-card"></i> Carte
            </label>
          </div>
        </div>
        <!-- Remarques -->
        <div style="margin-bottom:0">
          <label class="form-label" style="font-weight:600">Demande spéciale <small class="text-muted">(optionnel)</small></label>
          <input type="text" class="form-control" id="chCmdRemarques" placeholder="Ex: sans viande, allergie noix..." maxlength="500">
          <div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-top:0.5rem">
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans viande">Sans viande</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans porc">Sans porc</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans gluten">Sans gluten</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Sans lactose">Sans lactose</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ch-quick-tag" data-tag="Végétarien">Végétarien</button>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex" style="flex-shrink:0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark ms-auto" id="chCmdSaveBtn"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
      </div>
    </div>
  </div>
</div>


<script type="application/json" id="__ss_ssr__"><?php echo json_encode($ssrData, JSON_UNESCAPED_UNICODE); ?></script>
