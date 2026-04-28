<?php
/**
 * Admin Formations Compétences FEGEMS — API
 *
 * Couvre : référentiel thématiques, profil d'équipe attendu (matrice),
 * cartographie individuelle, heatmap secteurs.
 */

const COMP_SECTEURS = ['soins', 'socio_culturel', 'hotellerie', 'maintenance', 'administration', 'management'];
const COMP_TYPES_FORMATION = ['interne', 'continue_catalogue', 'superieur', 'vae', 'autodidacte', 'tutorat', 'fpp', 'autre'];

function admin_get_thematiques()
{
    require_responsable();
    global $params;
    $categorie = $params['categorie'] ?? null;
    $where = $categorie ? 'WHERE actif = 1 AND categorie = ?' : 'WHERE actif = 1';
    $binds = $categorie ? [$categorie] : [];
    $rows = Db::fetchAll(
        "SELECT id, code, nom, categorie, parent_thematique_id, tag_affichage, icone, couleur, duree_validite_mois, ordre
         FROM competences_thematiques $where ORDER BY ordre ASC, nom ASC",
        $binds
    );
    respond(['success' => true, 'thematiques' => $rows]);
}

function admin_get_secteurs()
{
    require_responsable();
    $secteursLocaux = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'ref.secteurs_locaux_actifs'") === '1';
    $locaux = $secteursLocaux ? Db::fetchAll("SELECT * FROM secteurs_locaux WHERE actif = 1 ORDER BY ordre") : [];
    respond([
        'success' => true,
        'fegems' => COMP_SECTEURS,
        'locaux' => $locaux,
        'mapping_actif' => $secteursLocaux,
    ]);
}

/* ═══════════════════════════════════════════════════════════════
   Profil d'équipe attendu — matrice thématique × secteur
   ═══════════════════════════════════════════════════════════════ */

function admin_get_profil_attendu()
{
    require_responsable();

    $thems = Db::fetchAll(
        "SELECT id, code, nom, categorie, parent_thematique_id, tag_affichage, icone, ordre
         FROM competences_thematiques WHERE actif = 1 ORDER BY ordre ASC, nom ASC"
    );

    $cells = Db::fetchAll(
        "SELECT thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande
         FROM competences_profil_attendu"
    );

    // Indexer les cellules par "thematique_id|secteur"
    $byKey = [];
    foreach ($cells as $c) {
        $byKey[$c['thematique_id'] . '|' . $c['secteur']] = $c;
    }

    // Stats remplissage
    $totalCells = count($thems) * count(COMP_SECTEURS);
    $filledCells = count(array_filter($cells, fn($c) => (int) $c['requis'] === 1));
    $pctRempli = $totalCells > 0 ? round(($filledCells / $totalCells) * 100, 1) : 0;

    respond([
        'success' => true,
        'thematiques' => $thems,
        'secteurs' => COMP_SECTEURS,
        'cells' => $byKey,
        'stats' => [
            'total_cellules' => $totalCells,
            'cellules_definies' => $filledCells,
            'pct_rempli' => $pctRempli,
        ],
    ]);
}

function admin_save_profil_cellule()
{
    require_admin();
    global $params;

    $thematiqueId = Sanitize::text($params['thematique_id'] ?? '', 36);
    $secteur = Sanitize::text($params['secteur'] ?? '', 30);
    if (!$thematiqueId || !in_array($secteur, COMP_SECTEURS, true)) bad_request('Paramètres invalides');

    $them = Db::fetch("SELECT id FROM competences_thematiques WHERE id = ?", [$thematiqueId]);
    if (!$them) not_found('Thématique introuvable');

    $requis = (int) ($params['requis'] ?? 1);
    $niveauRequis = isset($params['niveau_requis']) ? max(1, min(4, (int) $params['niveau_requis'])) : null;
    $part = isset($params['part_a_former_pct']) ? max(0, min(100, (float) $params['part_a_former_pct'])) : 0;
    $typeFormation = $params['type_formation_recommande'] ?? 'continue_catalogue';
    if (!in_array($typeFormation, COMP_TYPES_FORMATION, true)) $typeFormation = 'continue_catalogue';
    $objectif = Sanitize::text($params['objectif_strategique'] ?? '', 1000);

    $existing = Db::fetch(
        "SELECT id FROM competences_profil_attendu WHERE thematique_id = ? AND secteur = ?",
        [$thematiqueId, $secteur]
    );

    $u = $_SESSION['admin']['id'] ?? null;
    if ($existing) {
        Db::exec(
            "UPDATE competences_profil_attendu
             SET requis = ?, niveau_requis = ?, part_a_former_pct = ?, type_formation_recommande = ?,
                 objectif_strategique = ?, updated_by = ?
             WHERE id = ?",
            [$requis, $niveauRequis, $part, $typeFormation, $objectif, $u, $existing['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO competences_profil_attendu
             (id, thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande, objectif_strategique, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $thematiqueId, $secteur, $requis, $niveauRequis, $part, $typeFormation, $objectif, $u]
        );
    }

    respond(['success' => true]);
}

function admin_clear_profil_cellule()
{
    require_admin();
    global $params;
    $thematiqueId = Sanitize::text($params['thematique_id'] ?? '', 36);
    $secteur = Sanitize::text($params['secteur'] ?? '', 30);
    if (!$thematiqueId || !in_array($secteur, COMP_SECTEURS, true)) bad_request('Paramètres invalides');

    Db::exec(
        "DELETE FROM competences_profil_attendu WHERE thematique_id = ? AND secteur = ?",
        [$thematiqueId, $secteur]
    );
    respond(['success' => true]);
}

function admin_remplir_secteur()
{
    require_admin();
    global $params;
    $secteur = Sanitize::text($params['secteur'] ?? '', 30);
    $niveauRequis = max(1, min(4, (int) ($params['niveau_requis'] ?? 2)));
    $part = max(0, min(100, (float) ($params['part_a_former_pct'] ?? 100)));
    $categorie = $params['categorie'] ?? 'fegems_base';

    if (!in_array($secteur, COMP_SECTEURS, true)) bad_request('Secteur invalide');

    $thems = Db::fetchAll(
        "SELECT id FROM competences_thematiques WHERE actif = 1 AND categorie = ?",
        [$categorie]
    );

    $u = $_SESSION['admin']['id'] ?? null;
    $count = 0;
    foreach ($thems as $t) {
        $existing = Db::fetch(
            "SELECT id FROM competences_profil_attendu WHERE thematique_id = ? AND secteur = ?",
            [$t['id'], $secteur]
        );
        if ($existing) continue; // ne pas écraser
        Db::exec(
            "INSERT INTO competences_profil_attendu
             (id, thematique_id, secteur, requis, niveau_requis, part_a_former_pct, type_formation_recommande, updated_by)
             VALUES (?, ?, ?, 1, ?, ?, 'continue_catalogue', ?)",
            [Uuid::v4(), $t['id'], $secteur, $niveauRequis, $part, $u]
        );
        $count++;
    }

    respond(['success' => true, 'inserted' => $count, 'message' => "$count cellule(s) ajoutée(s)"]);
}

/* ═══════════════════════════════════════════════════════════════
   Cartographie d'équipe — vue liste 62 collaborateurs
   ═══════════════════════════════════════════════════════════════ */

function admin_get_cartographie_equipe()
{
    require_responsable();
    global $params;

    $secteurFilter = $params['secteur'] ?? '';
    $prioriteFilter = $params['priorite'] ?? '';

    $where = ["u.is_active = 1", "u.role IN ('collaborateur','responsable','admin','direction')"];
    $binds = [];
    if ($secteurFilter && in_array($secteurFilter, COMP_SECTEURS, true)) {
        $where[] = "f.secteur_fegems = ?";
        $binds[] = $secteurFilter;
    }

    $whereSql = implode(' AND ', $where);

    // Récupérer les collab + leur thématique prioritaire (écart le plus grand)
    $rows = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.date_entree,
                f.nom AS fonction_nom, f.secteur_fegems,
                (SELECT cu.thematique_id FROM competences_user cu
                 WHERE cu.user_id = u.id ORDER BY cu.ecart DESC, cu.priorite ASC LIMIT 1) AS them_prio_id,
                (SELECT cu.niveau_actuel FROM competences_user cu
                 WHERE cu.user_id = u.id ORDER BY cu.ecart DESC, cu.priorite ASC LIMIT 1) AS niveau_actuel,
                (SELECT cu.niveau_requis FROM competences_user cu
                 WHERE cu.user_id = u.id ORDER BY cu.ecart DESC, cu.priorite ASC LIMIT 1) AS niveau_requis,
                (SELECT cu.ecart FROM competences_user cu
                 WHERE cu.user_id = u.id ORDER BY cu.ecart DESC, cu.priorite ASC LIMIT 1) AS ecart,
                (SELECT cu.priorite FROM competences_user cu
                 WHERE cu.user_id = u.id ORDER BY cu.ecart DESC, cu.priorite ASC LIMIT 1) AS priorite,
                (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id) AS nb_competences,
                (SELECT COUNT(*) FROM competences_user cu WHERE cu.user_id = u.id AND cu.priorite = 'a_jour') AS nb_a_jour
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE $whereSql
         ORDER BY u.nom ASC, u.prenom ASC",
        $binds
    );

    // Joindre la thématique prioritaire (titre)
    $themIds = array_filter(array_column($rows, 'them_prio_id'));
    $themMap = [];
    if ($themIds) {
        $ph = implode(',', array_fill(0, count($themIds), '?'));
        $themRows = Db::fetchAll("SELECT id, nom, tag_affichage FROM competences_thematiques WHERE id IN ($ph)", array_values($themIds));
        foreach ($themRows as $t) $themMap[$t['id']] = $t;
    }

    foreach ($rows as &$r) {
        $r['thematique_prioritaire'] = $r['them_prio_id'] ? ($themMap[$r['them_prio_id']]['nom'] ?? null) : null;
        $r['conformite_pct'] = $r['nb_competences'] > 0
            ? round(($r['nb_a_jour'] / $r['nb_competences']) * 100)
            : 0;
    }
    unset($r);

    // Filtre priorité (post-query car dépend de la sub-query)
    if ($prioriteFilter) {
        $rows = array_values(array_filter($rows, fn($r) => $r['priorite'] === $prioriteFilter));
    }

    // Stats globales
    $stats = [
        'total_collab' => count($rows),
        'haute' => count(array_filter($rows, fn($r) => $r['priorite'] === 'haute')),
        'moyenne' => count(array_filter($rows, fn($r) => $r['priorite'] === 'moyenne')),
        'a_jour' => count(array_filter($rows, fn($r) => $r['priorite'] === 'a_jour')),
        'non_evalues' => count(array_filter($rows, fn($r) => $r['priorite'] === null)),
    ];

    respond(['success' => true, 'collaborateurs' => $rows, 'stats' => $stats]);
}

function admin_get_heatmap_secteurs()
{
    require_responsable();

    // Calcul du niveau moyen par (thématique × secteur), pondéré par les collaborateurs ayant été évalués
    $rows = Db::fetchAll(
        "SELECT cu.thematique_id, f.secteur_fegems AS secteur, AVG(cu.niveau_actuel) AS niveau_moyen, COUNT(*) AS nb
         FROM competences_user cu
         JOIN users u ON u.id = cu.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND f.secteur_fegems IS NOT NULL AND cu.niveau_actuel IS NOT NULL
         GROUP BY cu.thematique_id, f.secteur_fegems"
    );

    $thems = Db::fetchAll(
        "SELECT id, nom, ordre FROM competences_thematiques
         WHERE actif = 1 AND categorie = 'fegems_base'
         ORDER BY ordre ASC LIMIT 12"
    );

    // Indexer par "thematique_id|secteur"
    $heatmap = [];
    foreach ($rows as $r) {
        $heatmap[$r['thematique_id'] . '|' . $r['secteur']] = round((float) $r['niveau_moyen'], 1);
    }

    respond([
        'success' => true,
        'thematiques' => $thems,
        'secteurs' => COMP_SECTEURS,
        'heatmap' => $heatmap,
    ]);
}

/* ═══════════════════════════════════════════════════════════════
   Fiche compétences employé
   ═══════════════════════════════════════════════════════════════ */

function admin_get_collab_competences()
{
    require_responsable();
    global $params;

    $userId = Sanitize::text($params['user_id'] ?? '', 36);
    if (!$userId) bad_request('user_id requis');

    $user = Db::fetch(
        "SELECT u.*, f.nom AS fonction_nom, f.secteur_fegems,
                np1.prenom AS np1_prenom, np1.nom AS np1_nom
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN users np1 ON np1.id = u.n_plus_un_id
         WHERE u.id = ?",
        [$userId]
    );
    if (!$user) not_found('Collaborateur introuvable');

    // Compétences (toutes) avec libellés
    $competences = Db::fetchAll(
        "SELECT cu.*, t.code AS them_code, t.nom AS them_nom, t.categorie AS them_categorie,
                t.tag_affichage, t.icone, t.duree_validite_mois,
                ev.prenom AS evaluator_prenom, ev.nom AS evaluator_nom
         FROM competences_user cu
         JOIN competences_thematiques t ON t.id = cu.thematique_id
         LEFT JOIN users ev ON ev.id = cu.evaluator_id
         WHERE cu.user_id = ?
         ORDER BY cu.priorite ASC, cu.ecart DESC, t.ordre ASC",
        [$userId]
    );

    // Stats
    $niveauGlobal = 0;
    $nbEvalues = 0;
    foreach ($competences as $c) {
        if ($c['niveau_actuel'] !== null) {
            $niveauGlobal += (int) $c['niveau_actuel'];
            $nbEvalues++;
        }
    }
    $niveauGlobal = $nbEvalues > 0 ? round($niveauGlobal / $nbEvalues, 1) : 0;
    $nbAJour = count(array_filter($competences, fn($c) => $c['priorite'] === 'a_jour'));
    $nbHaute = count(array_filter($competences, fn($c) => $c['priorite'] === 'haute'));
    $conformite = count($competences) > 0 ? round(($nbAJour / count($competences)) * 100) : 0;

    // Heures formation cette année
    $heuresAnnee = (float) Db::getOne(
        "SELECT COALESCE(SUM(p.heures_realisees), 0)
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.user_id = ? AND p.statut IN ('present','valide')
           AND YEAR(COALESCE(p.date_realisation, f.date_debut)) = YEAR(CURDATE())",
        [$userId]
    );

    // Formations en cours / planifiées
    $formations = Db::fetchAll(
        "SELECT f.id, f.titre, f.statut, f.date_debut, f.date_fin, f.duree_heures,
                p.statut AS participant_statut, p.evaluation_manager, p.heures_realisees,
                p.cout_individuel, p.date_realisation
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.user_id = ?
         ORDER BY f.date_debut DESC LIMIT 10",
        [$userId]
    );

    // Objectifs annuels
    $objectifs = Db::fetchAll(
        "SELECT * FROM competences_objectifs_annuels
         WHERE user_id = ? AND annee = YEAR(CURDATE())
         ORDER BY ordre ASC, trimestre_cible ASC",
        [$userId]
    );

    // Référents (rôles spéciaux)
    $referents = Db::fetchAll(
        "SELECT cr.*, t.nom AS them_nom
         FROM competences_referents cr
         JOIN competences_thematiques t ON t.id = cr.thematique_id
         WHERE cr.user_id = ? AND cr.actif = 1",
        [$userId]
    );

    respond([
        'success' => true,
        'user' => $user,
        'competences' => $competences,
        'formations' => $formations,
        'objectifs' => $objectifs,
        'referents' => $referents,
        'stats' => [
            'niveau_global' => $niveauGlobal,
            'nb_competences' => count($competences),
            'nb_evalues' => $nbEvalues,
            'nb_a_jour' => $nbAJour,
            'nb_haute' => $nbHaute,
            'conformite_pct' => $conformite,
            'heures_annee' => $heuresAnnee,
        ],
    ]);
}

function admin_save_collab_competence()
{
    require_admin();
    global $params;

    $userId = Sanitize::text($params['user_id'] ?? '', 36);
    $thematiqueId = Sanitize::text($params['thematique_id'] ?? '', 36);
    if (!$userId || !$thematiqueId) bad_request('Paramètres invalides');

    $niveauActuel = isset($params['niveau_actuel']) && $params['niveau_actuel'] !== ''
        ? max(1, min(4, (int) $params['niveau_actuel'])) : null;
    $niveauRequis = isset($params['niveau_requis']) && $params['niveau_requis'] !== ''
        ? max(1, min(4, (int) $params['niveau_requis'])) : null;
    $commentaires = Sanitize::text($params['commentaires'] ?? '', 2000);
    $u = $_SESSION['admin']['id'] ?? null;

    $existing = Db::fetch(
        "SELECT id, niveau_actuel FROM competences_user WHERE user_id = ? AND thematique_id = ?",
        [$userId, $thematiqueId]
    );

    if ($existing) {
        // Trace dans l'historique si le niveau change
        if ((int) $existing['niveau_actuel'] !== (int) $niveauActuel) {
            Db::exec(
                "INSERT INTO competences_evaluations_historique
                 (id, user_id, thematique_id, niveau_avant, niveau_apres, evaluator_id, date_evaluation, notes)
                 VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)",
                [Uuid::v4(), $userId, $thematiqueId, $existing['niveau_actuel'], $niveauActuel, $u, $commentaires]
            );
        }
        Db::exec(
            "UPDATE competences_user
             SET niveau_actuel = ?, niveau_requis = COALESCE(?, niveau_requis),
                 commentaires = ?, evaluator_id = ?, date_evaluation = CURDATE()
             WHERE id = ?",
            [$niveauActuel, $niveauRequis, $commentaires, $u, $existing['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO competences_user
             (id, user_id, thematique_id, niveau_actuel, niveau_requis, commentaires, evaluator_id, date_evaluation)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())",
            [Uuid::v4(), $userId, $thematiqueId, $niveauActuel, $niveauRequis, $commentaires, $u]
        );
    }

    respond(['success' => true]);
}

/* ═══════════════════════════════════════════════════════════════
   Inscriptions FEGEMS — propositions auto + workflow email
   ═══════════════════════════════════════════════════════════════ */

function admin_get_inscriptions_propositions()
{
    require_responsable();

    $rows = Db::fetchAll(
        "SELECT p.*, s.date_debut, s.date_fin, s.lieu, s.modalite, s.capacite_max, s.places_inscrites,
                s.cout_membre, s.cout_non_membre, s.contact_inscription_email,
                f.id AS formation_id, f.titre AS formation_titre, f.duree_heures,
                (SELECT COUNT(*) FROM inscription_proposition_users ipu WHERE ipu.proposition_id = p.id AND ipu.statut = 'selectionne') AS nb_candidats
         FROM inscription_propositions p
         JOIN formation_sessions s ON s.id = p.session_id
         JOIN formations f ON f.id = s.formation_id
         WHERE p.statut IN ('proposee','en_validation')
         ORDER BY p.deadline_action ASC"
    );

    // Charger les premiers candidats (jusqu'à 6) par proposition pour l'avatar stack
    foreach ($rows as &$p) {
        $p['candidats'] = Db::fetchAll(
            "SELECT u.id, u.prenom, u.nom, ipu.motif_individuel
             FROM inscription_proposition_users ipu
             JOIN users u ON u.id = ipu.user_id
             WHERE ipu.proposition_id = ? AND ipu.statut = 'selectionne'
             ORDER BY u.nom ASC LIMIT 8",
            [$p['id']]
        );
    }
    unset($p);

    // Stats globales pour la bannière
    $now = new DateTime();
    $stats = [
        'total' => count($rows),
        'urgent' => count(array_filter($rows, function ($r) use ($now) {
            return $r['deadline_action'] && (new DateTime($r['deadline_action']))->diff($now)->days <= 30
                && $r['type_motif'] === 'renouvellement_expire';
        })),
        'inc' => count(array_filter($rows, fn($r) => $r['type_motif'] === 'inc_nouveau')),
        'cout_total' => array_sum(array_map(fn($r) => (float) ($r['cout_non_membre'] ?? 0) * (int) $r['nb_candidats'], $rows)),
    ];
    $cotisationActive = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.cotisation_fegems_active'") === '1';
    if ($cotisationActive) $stats['cout_total'] = 0;
    $stats['membre_fegems'] = $cotisationActive;

    respond(['success' => true, 'propositions' => $rows, 'stats' => $stats]);
}

function admin_regenerer_propositions()
{
    require_admin();

    // Lance le script de génération en mode forcé
    $script = realpath(__DIR__ . '/../../scripts/generate_inscription_propositions.php');
    if (!$script || !file_exists($script)) bad_request('Script de génération introuvable');

    ob_start();
    $argv = ['regenerer', '--force'];
    require $script;
    $output = ob_get_clean();

    respond(['success' => true, 'log' => $output, 'message' => 'Propositions regénérées']);
}

function admin_get_proposition_detail()
{
    require_responsable();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');

    $prop = Db::fetch(
        "SELECT p.*, s.date_debut, s.date_fin, s.heure_debut, s.heure_fin, s.lieu, s.modalite,
                s.capacite_max, s.places_inscrites, s.cout_membre, s.cout_non_membre,
                s.contact_inscription_email,
                f.id AS formation_id, f.titre AS formation_titre, f.description AS formation_description,
                f.duree_heures
         FROM inscription_propositions p
         JOIN formation_sessions s ON s.id = p.session_id
         JOIN formations f ON f.id = s.formation_id
         WHERE p.id = ?",
        [$id]
    );
    if (!$prop) not_found('Proposition introuvable');

    $candidats = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, u.diplome_principal, f.nom AS fonction_nom,
                ipu.motif_individuel, ipu.statut
         FROM inscription_proposition_users ipu
         JOIN users u ON u.id = ipu.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE ipu.proposition_id = ?
         ORDER BY u.nom",
        [$id]
    );

    respond(['success' => true, 'proposition' => $prop, 'candidats' => $candidats]);
}

function admin_validate_proposition()
{
    require_admin();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');
    $u = $_SESSION['admin']['id'] ?? null;
    Db::exec(
        "UPDATE inscription_propositions SET statut = 'envoyee', validated_by = ?, validated_at = NOW()
         WHERE id = ?",
        [$u, $id]
    );
    respond(['success' => true]);
}

function admin_reject_proposition()
{
    require_admin();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');
    Db::exec("UPDATE inscription_propositions SET statut = 'rejetee' WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_send_inscription_email()
{
    require_admin();
    global $params;
    $id = Sanitize::text($params['proposition_id'] ?? '', 36);
    if (!$id) bad_request('proposition_id requis');

    $prop = Db::fetch(
        "SELECT p.*, s.date_debut, s.lieu, s.contact_inscription_email,
                f.titre AS formation_titre
         FROM inscription_propositions p
         JOIN formation_sessions s ON s.id = p.session_id
         JOIN formations f ON f.id = s.formation_id
         WHERE p.id = ?",
        [$id]
    );
    if (!$prop) not_found('Proposition introuvable');

    $candidats = Db::fetchAll(
        "SELECT u.prenom, u.nom, u.email, u.diplome_principal, f.nom AS fonction_nom
         FROM inscription_proposition_users ipu
         JOIN users u ON u.id = ipu.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE ipu.proposition_id = ? AND ipu.statut = 'selectionne'",
        [$id]
    );

    $destinataire = $prop['contact_inscription_email']
        ?: Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.email_destinataire_fegems'")
        ?: 'inscription@fegems.ch';
    $cc = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.email_cc_interne'");
    $sujet = sprintf('Inscription %s · %s · %d collaborateurs',
        $prop['formation_titre'],
        date('d.m.Y', strtotime($prop['date_debut'])),
        count($candidats)
    );

    $tableHtml = '<table border="1" cellpadding="6" style="border-collapse:collapse"><thead><tr>'
        . '<th>Nom · Prénom</th><th>Fonction</th><th>Diplôme</th><th>Email</th></tr></thead><tbody>';
    foreach ($candidats as $c) {
        $tableHtml .= '<tr><td>' . h($c['nom']) . ' ' . h($c['prenom']) . '</td>'
            . '<td>' . h($c['fonction_nom'] ?? '—') . '</td>'
            . '<td>' . h($c['diplome_principal'] ?? '—') . '</td>'
            . '<td>' . h($c['email']) . '</td></tr>';
    }
    $tableHtml .= '</tbody></table>';

    $corps = '<p>Bonjour,</p>'
        . '<p>Pour la session du <strong>' . date('d.m.Y', strtotime($prop['date_debut'])) . '</strong> '
        . '(' . h($prop['lieu']) . '), je vous transmets les inscriptions de notre EMS pour la formation '
        . '<strong>' . h($prop['formation_titre']) . '</strong>.</p>'
        . $tableHtml
        . '<p>' . h($prop['libelle_motif']) . '</p>'
        . '<p>Pouvez-vous me confirmer la prise en compte de ces inscriptions ?</p>'
        . '<p>Bien cordialement.</p>';

    $dryRun = !empty($params['dry_run']);
    $u = $_SESSION['admin']['id'] ?? null;

    if (!$dryRun) {
        Db::exec(
            "INSERT INTO inscription_emails
             (id, proposition_id, destinataire, cc, sujet, corps_html, sent_at, sent_by_id, statut_reponse)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'en_attente')",
            [Uuid::v4(), $id, $destinataire, $cc, $sujet, $corps, $u]
        );
        Db::exec("UPDATE inscription_propositions SET statut = 'envoyee', validated_by = ?, validated_at = NOW() WHERE id = ?",
            [$u, $id]);
    }

    respond([
        'success' => true,
        'dry_run' => $dryRun,
        'destinataire' => $destinataire,
        'cc' => $cc,
        'sujet' => $sujet,
        'corps_html' => $corps,
        'message' => $dryRun ? 'Aperçu généré.' : 'Email enregistré (envoi manuel à effectuer depuis votre client mail).',
    ]);
}

/* ═══════════════════════════════════════════════════════════════
   Sessions de formation — CRUD
   ═══════════════════════════════════════════════════════════════ */

function admin_get_formation_sessions()
{
    require_responsable();
    global $params;

    $statut = $params['statut'] ?? '';
    $periode = $params['periode'] ?? '';

    $where = ["1=1"];
    $binds = [];
    if ($statut && in_array($statut, ['ouverte','complete','liste_attente','annulee','passee'], true)) {
        $where[] = "s.statut = ?";
        $binds[] = $statut;
    }
    if ($periode === 'a_venir') {
        $where[] = "s.date_debut >= CURDATE()";
    } elseif ($periode === 'passees') {
        $where[] = "s.date_debut < CURDATE()";
    }

    $rows = Db::fetchAll(
        "SELECT s.*, f.titre AS formation_titre, f.type, f.duree_heures,
                (SELECT COUNT(*) FROM formation_participants fp WHERE fp.session_id = s.id) AS nb_participants
         FROM formation_sessions s
         JOIN formations f ON f.id = s.formation_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY s.date_debut ASC",
        $binds
    );

    respond(['success' => true, 'sessions' => $rows]);
}

function admin_get_session_detail()
{
    require_responsable();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');

    $session = Db::fetch(
        "SELECT s.*, f.titre AS formation_titre, f.description AS formation_description,
                f.type, f.duree_heures
         FROM formation_sessions s
         JOIN formations f ON f.id = s.formation_id
         WHERE s.id = ?",
        [$id]
    );
    if (!$session) not_found('Session introuvable');

    $participants = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, fct.nom AS fonction_nom,
                p.statut, p.evaluation_manager, p.heures_realisees, p.cout_individuel, p.date_realisation
         FROM formation_participants p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN fonctions fct ON fct.id = u.fonction_id
         WHERE p.session_id = ?
         ORDER BY u.nom",
        [$id]
    );

    respond(['success' => true, 'session' => $session, 'participants' => $participants]);
}

function admin_save_session()
{
    require_admin();
    global $params;

    $id = Sanitize::text($params['id'] ?? '', 36);
    $formationId = Sanitize::text($params['formation_id'] ?? '', 36);
    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    if (!$formationId || !$dateDebut) bad_request('Formation et date début requis');

    $fields = [
        'formation_id' => $formationId,
        'date_debut' => $dateDebut,
        'date_fin' => Sanitize::date($params['date_fin'] ?? '') ?: null,
        'heure_debut' => $params['heure_debut'] ?? null,
        'heure_fin' => $params['heure_fin'] ?? null,
        'lieu' => Sanitize::text($params['lieu'] ?? '', 255),
        'modalite' => in_array($params['modalite'] ?? '', ['presentiel','elearning','hybride'], true) ? $params['modalite'] : 'presentiel',
        'capacite_max' => isset($params['capacite_max']) ? (int) $params['capacite_max'] : null,
        'cout_membre' => (float) ($params['cout_membre'] ?? 0),
        'cout_non_membre' => (float) ($params['cout_non_membre'] ?? 0),
        'contact_inscription_email' => Sanitize::email($params['contact_inscription_email'] ?? ''),
        'statut' => in_array($params['statut'] ?? '', ['ouverte','complete','liste_attente','annulee','passee'], true) ? $params['statut'] : 'ouverte',
    ];

    if ($id) {
        $sets = []; $binds = [];
        foreach ($fields as $k => $v) { $sets[] = "$k = ?"; $binds[] = $v; }
        $binds[] = $id;
        Db::exec("UPDATE formation_sessions SET " . implode(', ', $sets) . " WHERE id = ?", $binds);
    } else {
        $newId = Uuid::v4();
        Db::exec(
            "INSERT INTO formation_sessions
             (id, formation_id, date_debut, date_fin, heure_debut, heure_fin, lieu, modalite, capacite_max, cout_membre, cout_non_membre, contact_inscription_email, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$newId, ...array_values($fields)]
        );
        $id = $newId;
    }

    respond(['success' => true, 'id' => $id]);
}

function admin_delete_session()
{
    require_admin();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');
    Db::exec("DELETE FROM formation_sessions WHERE id = ?", [$id]);
    respond(['success' => true]);
}
