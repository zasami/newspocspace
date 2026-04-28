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

/**
 * Vérifie les conflits absences/désirs/vacances pour un ou plusieurs collaborateurs
 * sur les dates d'une formation/session avant inscription.
 *
 * Params :
 *   - session_id (optionnel) ou formation_id : pour récupérer les dates
 *   - user_ids : array des collaborateurs à vérifier (sinon tous les candidats si proposition_id donné)
 *   - proposition_id (optionnel) : raccourci pour récupérer session + candidats
 *
 * Retour : ['conflicts' => [user_id => [['date'=>..., 'type'=>..., 'motif'=>...], ...]]]
 */
function admin_check_formation_conflicts()
{
    require_responsable();
    global $params;

    $sessionId = Sanitize::text($params['session_id'] ?? '', 36);
    $formationId = Sanitize::text($params['formation_id'] ?? '', 36);
    $propId = Sanitize::text($params['proposition_id'] ?? '', 36);
    $userIds = $params['user_ids'] ?? [];
    if (!is_array($userIds)) $userIds = [];

    $dateDebut = null; $dateFin = null;
    if ($propId) {
        $row = Db::fetch(
            "SELECT s.id AS session_id, s.date_debut, s.date_fin, f.id AS formation_id
             FROM inscription_propositions p
             JOIN formation_sessions s ON s.id = p.session_id
             JOIN formations f ON f.id = s.formation_id WHERE p.id = ?",
            [$propId]
        );
        if (!$row) bad_request('Proposition introuvable');
        $dateDebut = $row['date_debut'];
        $dateFin = $row['date_fin'] ?: $row['date_debut'];
        if (!$userIds) {
            $userIds = array_column(
                Db::fetchAll("SELECT user_id FROM inscription_proposition_users WHERE proposition_id = ? AND statut = 'selectionne'", [$propId]),
                'user_id'
            );
        }
    } elseif ($sessionId) {
        $row = Db::fetch("SELECT date_debut, date_fin FROM formation_sessions WHERE id = ?", [$sessionId]);
        if (!$row) bad_request('Session introuvable');
        $dateDebut = $row['date_debut'];
        $dateFin = $row['date_fin'] ?: $row['date_debut'];
    } elseif ($formationId) {
        $row = Db::fetch("SELECT date_debut, date_fin FROM formations WHERE id = ?", [$formationId]);
        if (!$row) bad_request('Formation introuvable');
        $dateDebut = $row['date_debut'];
        $dateFin = $row['date_fin'] ?: $row['date_debut'];
    } else {
        bad_request('session_id, formation_id ou proposition_id requis');
    }

    if (!$userIds) respond(['success' => true, 'conflicts' => [], 'date_debut' => $dateDebut, 'date_fin' => $dateFin]);

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    // 1) Absences validées chevauchant la plage
    $absRows = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin, type, motif
         FROM absences
         WHERE statut = 'valide' AND user_id IN ($placeholders)
           AND date_debut <= ? AND date_fin >= ?",
        array_merge($userIds, [$dateFin, $dateDebut])
    );

    // 2) Désirs jour_off validés sur les dates
    $desRows = Db::fetchAll(
        "SELECT user_id, date_souhaitee AS date, motif
         FROM desirs
         WHERE statut = 'valide' AND type = 'jour_off' AND user_id IN ($placeholders)
           AND date_souhaitee BETWEEN ? AND ?",
        array_merge($userIds, [$dateDebut, $dateFin])
    );

    $conflicts = [];
    $absLabels = [
        'vacances' => 'Vacances', 'maladie' => 'Maladie', 'accident' => 'Accident',
        'conge_special' => 'Congé spécial', 'formation' => 'Autre formation',
        'maternite' => 'Maternité', 'paternite' => 'Paternité', 'autre' => 'Absence',
    ];
    foreach ($absRows as $a) {
        $start = max(strtotime($dateDebut), strtotime($a['date_debut']));
        $end = min(strtotime($dateFin), strtotime($a['date_fin']));
        for ($t = $start; $t <= $end; $t += 86400) {
            $conflicts[$a['user_id']][] = [
                'date' => date('Y-m-d', $t),
                'kind' => 'absence',
                'type' => $a['type'],
                'label' => $absLabels[$a['type']] ?? 'Absence',
                'motif' => $a['motif'] ?: '',
            ];
        }
    }
    foreach ($desRows as $d) {
        $conflicts[$d['user_id']][] = [
            'date' => $d['date'],
            'kind' => 'desir',
            'type' => 'jour_off',
            'label' => 'Désir off',
            'motif' => $d['motif'] ?: '',
        ];
    }

    // Nom + prénom des users en conflit pour affichage UI
    $usersInfo = [];
    if ($conflicts) {
        $confUserIds = array_keys($conflicts);
        $ph2 = implode(',', array_fill(0, count($confUserIds), '?'));
        $usersRows = Db::fetchAll("SELECT id, prenom, nom FROM users WHERE id IN ($ph2)", $confUserIds);
        foreach ($usersRows as $u) $usersInfo[$u['id']] = $u['prenom'] . ' ' . $u['nom'];
    }

    respond([
        'success' => true,
        'conflicts' => $conflicts,
        'users_info' => $usersInfo,
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin,
    ]);
}

/**
 * Inscrit un ou plusieurs users à une formation/session.
 * Refuse les users avec conflits (absence/désir validé) sauf si force=1.
 *
 * Params : session_id, user_ids[], force (optionnel)
 * Retour : ['inscrits' => [...], 'refuses' => [user_id => conflicts[]]]
 */
function admin_inscrire_users_formation()
{
    require_responsable();
    global $params;

    $sessionId = Sanitize::text($params['session_id'] ?? '', 36);
    $userIds = $params['user_ids'] ?? [];
    if (!is_array($userIds)) $userIds = [];
    $force = !empty($params['force']);

    if (!$sessionId) bad_request('session_id requis');
    if (!$userIds) bad_request('user_ids requis');

    $session = Db::fetch(
        "SELECT s.id, s.date_debut, s.date_fin, s.formation_id, f.titre
         FROM formation_sessions s JOIN formations f ON f.id = s.formation_id
         WHERE s.id = ?", [$sessionId]
    );
    if (!$session) not_found('Session introuvable');

    $dateDebut = $session['date_debut'];
    $dateFin = $session['date_fin'] ?: $dateDebut;

    // Vérifier conflits
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $absRows = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin, type, motif FROM absences
         WHERE statut = 'valide' AND user_id IN ($placeholders) AND date_debut <= ? AND date_fin >= ?",
        array_merge($userIds, [$dateFin, $dateDebut])
    );
    $desRows = Db::fetchAll(
        "SELECT user_id, date_souhaitee AS date, motif FROM desirs
         WHERE statut = 'valide' AND type = 'jour_off' AND user_id IN ($placeholders)
           AND date_souhaitee BETWEEN ? AND ?",
        array_merge($userIds, [$dateDebut, $dateFin])
    );

    $hasConflict = [];
    foreach ($absRows as $a) {
        $hasConflict[$a['user_id']][] = ['kind' => 'absence', 'type' => $a['type'], 'motif' => $a['motif']];
    }
    foreach ($desRows as $d) {
        $hasConflict[$d['user_id']][] = ['kind' => 'desir', 'date' => $d['date'], 'motif' => $d['motif']];
    }

    $refuses = [];
    $inscrits = [];

    foreach ($userIds as $uid) {
        if (isset($hasConflict[$uid]) && !$force) {
            $refuses[$uid] = $hasConflict[$uid];
            continue;
        }
        $exists = Db::getOne(
            "SELECT id FROM formation_participants WHERE formation_id = ? AND user_id = ?",
            [$session['formation_id'], $uid]
        );
        if ($exists) {
            Db::exec("UPDATE formation_participants SET session_id = ?, statut = 'inscrit' WHERE id = ?",
                [$sessionId, $exists]);
        } else {
            Db::exec(
                "INSERT INTO formation_participants (id, formation_id, user_id, session_id, statut)
                 VALUES (?, ?, ?, ?, 'inscrit')",
                [Uuid::v4(), $session['formation_id'], $uid, $sessionId]
            );
        }
        $inscrits[] = $uid;
    }

    Db::exec(
        "UPDATE formation_sessions SET places_inscrites = (
           SELECT COUNT(*) FROM formation_participants WHERE session_id = ? AND statut IN ('inscrit','present','valide')
         ) WHERE id = ?",
        [$sessionId, $sessionId]
    );

    // ── Sync vers planning si déjà créé pour le(s) mois de la formation ──
    $dureeForm = (float) Db::getOne("SELECT duree_heures FROM formations WHERE id = ?", [$session['formation_id']]);
    sync_formation_to_planning($inscrits, $dateDebut, $dateFin, $session['titre'], $dureeForm);

    respond([
        'success' => true,
        'inscrits_count' => count($inscrits),
        'refuses_count' => count($refuses),
        'refuses' => $refuses,
        'message' => count($refuses) > 0 && !$force
            ? sprintf('%d inscrit·es, %d refusé·es (conflits absences/désirs)', count($inscrits), count($refuses))
            : sprintf('%d inscription·s créée·s', count($inscrits)),
    ]);
}

/**
 * Synchronise des dates de formation vers le ou les planning(s) existant(s).
 * Pour chaque date dans [dateDebut..dateFin] et chaque user :
 *  - cherche un planning du mois (statut != 'final')
 *  - skip si user a une absence validée ce jour-là (absence prime)
 *  - INSERT IGNORE une cellule statut='formation' avec notes 'Formation : <titre>'
 * Retourne le nombre de cellules ajoutées.
 */
function sync_formation_to_planning(array $userIds, string $dateDebut, string $dateFin, string $titre, ?float $dureeHeuresTotal = null): int
{
    if (!$userIds) return 0;
    $startTs = strtotime($dateDebut);
    $endTs = strtotime($dateFin ?: $dateDebut);
    $nbDays = max(1, (int) round(($endTs - $startTs) / 86400) + 1);
    // Heures par jour : duree formation / nb_jours, fallback 8.4 (LTr/CCT EMS Suisse)
    $heuresJourDefault = (float) (Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ia_heures_jour'") ?: 8.4);
    $heuresJour = $dureeHeuresTotal && $dureeHeuresTotal > 0
        ? round($dureeHeuresTotal / $nbDays, 2)
        : $heuresJourDefault;
    $note = sprintf('Formation : %s [%sh]', mb_substr($titre, 0, 70), rtrim(rtrim(number_format($heuresJour, 1, '.', ''), '0'), '.'));

    // Plannings concernés (par mois unique)
    $moisDistincts = [];
    for ($t = $startTs; $t <= $endTs; $t += 86400) {
        $moisDistincts[date('Y-m', $t)] = true;
    }
    $plannings = [];
    foreach (array_keys($moisDistincts) as $m) {
        $p = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$m]);
        if ($p && $p['statut'] !== 'final') $plannings[$m] = $p['id'];
    }
    if (!$plannings) return 0;

    // Modules principaux
    $userPrincipalModule = [];
    foreach (Db::fetchAll("SELECT user_id, module_id FROM user_modules WHERE is_principal = 1") as $um) {
        $userPrincipalModule[$um['user_id']] = $um['module_id'];
    }

    // Absences validées chevauchant la plage pour ces users
    $ph = implode(',', array_fill(0, count($userIds), '?'));
    $absRows = Db::fetchAll(
        "SELECT user_id, date_debut, date_fin FROM absences
         WHERE statut = 'valide' AND user_id IN ($ph) AND date_debut <= ? AND date_fin >= ?",
        array_merge($userIds, [date('Y-m-d', $endTs), date('Y-m-d', $startTs)])
    );
    $absMap = [];
    foreach ($absRows as $a) {
        $s = max($startTs, strtotime($a['date_debut']));
        $e = min($endTs, strtotime($a['date_fin']));
        for ($t = $s; $t <= $e; $t += 86400) $absMap[$a['user_id']][date('Y-m-d', $t)] = true;
    }

    $nb = 0;
    foreach ($userIds as $uid) {
        for ($t = $startTs; $t <= $endTs; $t += 86400) {
            $date = date('Y-m-d', $t);
            $mois = date('Y-m', $t);
            if (!isset($plannings[$mois])) continue;
            if (isset($absMap[$uid][$date])) continue;

            $modPrincipal = $userPrincipalModule[$uid] ?? null;

            // Si une cellule existe déjà (autre statut), on l'écrase en formation
            // sauf si statut = 'absent' (absence locale au planning)
            $existing = Db::fetch(
                "SELECT id, statut FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
                [$plannings[$mois], $uid, $date]
            );
            if ($existing) {
                if (in_array($existing['statut'], ['absent','formation'], true)) continue;
                Db::exec(
                    "UPDATE planning_assignations
                     SET horaire_type_id = NULL, module_id = ?, groupe_id = NULL, statut = 'formation', notes = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$modPrincipal, $note, $existing['id']]
                );
            } else {
                try {
                    Db::exec(
                        "INSERT INTO planning_assignations
                           (id, planning_id, user_id, date_jour, horaire_type_id, module_id, groupe_id, statut, notes)
                         VALUES (?, ?, ?, ?, NULL, ?, NULL, 'formation', ?)",
                        [Uuid::v4(), $plannings[$mois], $uid, $date, $modPrincipal, $note]
                    );
                } catch (\Throwable $e) { continue; }
            }
            $nb++;
        }
    }
    return $nb;
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

// ─── Paramètres formation ────────────────────────────────────────────────────

function admin_get_parametres_formation()
{
    require_responsable();
    $rows = Db::fetchAll(
        "SELECT cle, valeur, type, categorie, libelle, description, valeur_defaut,
                min_val, max_val, options_json, ordre
         FROM parametres_formation WHERE visible = 1
         ORDER BY categorie, ordre"
    );
    respond(['success' => true, 'parametres' => $rows]);
}

function admin_save_parametre_formation()
{
    require_admin();
    global $params;
    $cle = Sanitize::text($params['cle'] ?? '', 100);
    if (!$cle) bad_request('cle requise');

    $row = Db::fetch("SELECT cle, type, min_val, max_val, options_json FROM parametres_formation WHERE cle = ?", [$cle]);
    if (!$row) not_found('Paramètre inconnu');

    $val = $params['valeur'] ?? '';

    switch ($row['type']) {
        case 'bool':
            $val = !empty($val) && $val !== '0' && $val !== 'false' ? '1' : '0';
            break;
        case 'int':
            if (!is_numeric($val)) bad_request('Entier attendu');
            $val = (string)(int)$val;
            if ($row['min_val'] !== null && (int)$val < (int)$row['min_val']) bad_request('Valeur < min (' . $row['min_val'] . ')');
            if ($row['max_val'] !== null && (int)$val > (int)$row['max_val']) bad_request('Valeur > max (' . $row['max_val'] . ')');
            break;
        case 'decimal':
            if (!is_numeric($val)) bad_request('Décimal attendu');
            $val = (string)(float)$val;
            if ($row['min_val'] !== null && (float)$val < (float)$row['min_val']) bad_request('Valeur < min');
            if ($row['max_val'] !== null && (float)$val > (float)$row['max_val']) bad_request('Valeur > max');
            break;
        case 'json':
            $decoded = json_decode($val, true);
            if (json_last_error() !== JSON_ERROR_NONE) bad_request('JSON invalide : ' . json_last_error_msg());
            $val = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            break;
        case 'date':
            if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) bad_request('Date format YYYY-MM-DD attendu');
            break;
        case 'string':
        default:
            if ($row['options_json']) {
                $opts = json_decode($row['options_json'], true) ?: [];
                $allowedKeys = array_keys($opts);
                if ($val !== '' && !in_array($val, $allowedKeys, true) && !in_array($val, $opts, true)) {
                    bad_request('Valeur hors options');
                }
            }
            $val = (string)$val;
            break;
    }

    $userId = $_SESSION['admin']['id'] ?? null;
    Db::exec(
        "UPDATE parametres_formation SET valeur = ?, updated_by = ?, updated_at = NOW() WHERE cle = ?",
        [$val, $userId, $cle]
    );

    respond(['success' => true, 'cle' => $cle, 'valeur' => $val]);
}

function admin_reset_parametre_formation()
{
    require_admin();
    global $params;
    $cle = Sanitize::text($params['cle'] ?? '', 100);
    if (!$cle) bad_request('cle requise');
    $row = Db::fetch("SELECT valeur_defaut FROM parametres_formation WHERE cle = ?", [$cle]);
    if (!$row) not_found('Paramètre inconnu');
    $userId = $_SESSION['admin']['id'] ?? null;
    Db::exec(
        "UPDATE parametres_formation SET valeur = ?, updated_by = ?, updated_at = NOW() WHERE cle = ?",
        [$row['valeur_defaut'], $userId, $cle]
    );
    respond(['success' => true, 'cle' => $cle, 'valeur' => $row['valeur_defaut']]);
}

// ─── Stats formations enrichies ──────────────────────────────────────────────

function admin_get_formations_stats_enriched()
{
    require_responsable();

    $totalFormations   = (int) Db::getOne("SELECT COUNT(*) FROM formations");
    $totalParticipants = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants");
    $totalHeures       = (float) Db::getOne(
        "SELECT COALESCE(SUM(f.duree_heures), 0) FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.statut IN ('present','valide')"
    );
    $totalValides = (int) Db::getOne("SELECT COUNT(*) FROM formation_participants WHERE statut = 'valide'");

    // Heures par secteur FEGEMS
    $heuresParSecteur = Db::fetchAll(
        "SELECT COALESCE(fn.secteur_fegems, 'sans_secteur') AS secteur,
                COUNT(DISTINCT p.user_id) AS nb_collab,
                COALESCE(SUM(f.duree_heures), 0) AS heures
         FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         JOIN users u ON u.id = p.user_id
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE p.statut IN ('present','valide')
         GROUP BY secteur
         ORDER BY heures DESC"
    );

    // Top 10 thématiques (par occurence dans formation_thematiques avec liens vers participants)
    $topThematiques = Db::fetchAll(
        "SELECT t.id, t.code, t.nom, t.categorie, t.couleur,
                COUNT(DISTINCT fp.id) AS nb_part,
                COALESCE(SUM(CASE WHEN fp.statut IN ('present','valide') THEN f.duree_heures ELSE 0 END), 0) AS heures
         FROM competences_thematiques t
         JOIN formation_thematiques ft ON ft.thematique_id = t.id
         JOIN formations f ON f.id = ft.formation_id
         LEFT JOIN formation_participants fp ON fp.formation_id = f.id
         GROUP BY t.id
         HAVING nb_part > 0
         ORDER BY nb_part DESC
         LIMIT 10"
    );

    // Budget : alloué vs consommé
    $budgetAnnuelTotal = (float) Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.annuel_total_chf'");
    $seuilAlertePct    = (int) Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.seuil_alerte_pct'") ?: 80;
    $devise            = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'bud.devise'") ?: 'CHF';

    $anneeCourante = (int) date('Y');
    $budgetAlloue = (float) Db::getOne(
        "SELECT COALESCE(SUM(budget_alloue), 0) FROM formations
         WHERE YEAR(date_debut) = ?", [$anneeCourante]
    );
    $coutReel = (float) Db::getOne(
        "SELECT COALESCE(SUM(p.cout_individuel), 0) FROM formation_participants p
         JOIN formations f ON f.id = p.formation_id
         WHERE p.statut IN ('present','valide')
           AND YEAR(f.date_debut) = ?", [$anneeCourante]
    );
    if ($coutReel == 0) {
        // Fallback : somme cout_formation × nb participants
        $coutReel = (float) Db::getOne(
            "SELECT COALESCE(SUM(f.cout_formation), 0) FROM formation_participants p
             JOIN formations f ON f.id = p.formation_id
             WHERE p.statut IN ('present','valide')
               AND YEAR(f.date_debut) = ?", [$anneeCourante]
        );
    }

    // Évolution mensuelle (12 derniers mois)
    $evolution = Db::fetchAll(
        "SELECT DATE_FORMAT(f.date_debut, '%Y-%m') AS mois,
                COUNT(DISTINCT f.id) AS nb_formations,
                COUNT(DISTINCT p.id) AS nb_part,
                COALESCE(SUM(CASE WHEN p.statut IN ('present','valide') THEN f.duree_heures END), 0) AS heures
         FROM formations f
         LEFT JOIN formation_participants p ON p.formation_id = f.id
         WHERE f.date_debut >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY mois
         ORDER BY mois ASC"
    );

    respond([
        'success' => true,
        'global' => [
            'formations'   => $totalFormations,
            'participants' => $totalParticipants,
            'heures'       => $totalHeures,
            'valides'      => $totalValides,
            'taux_validation' => $totalParticipants > 0 ? round(($totalValides / $totalParticipants) * 100, 1) : 0,
        ],
        'par_secteur'    => $heuresParSecteur,
        'top_thematiques' => $topThematiques,
        'budget' => [
            'annuee'         => $anneeCourante,
            'budget_total'   => $budgetAnnuelTotal,
            'budget_alloue'  => $budgetAlloue,
            'cout_reel'      => $coutReel,
            'reste'          => max(0, $budgetAnnuelTotal - $coutReel),
            'seuil_alerte_pct' => $seuilAlertePct,
            'pct_consomme'   => $budgetAnnuelTotal > 0 ? round(($coutReel / $budgetAnnuelTotal) * 100, 1) : 0,
            'devise'         => $devise,
        ],
        'evolution' => $evolution,
    ]);
}

// ─── Entretiens annuels ──────────────────────────────────────────────────────

function admin_get_entretiens_list()
{
    require_responsable();
    global $params;
    $statut = $params['statut'] ?? null;
    $annee  = isset($params['annee']) ? (int)$params['annee'] : null;

    $where = ['1=1'];
    $binds = [];
    if ($statut && in_array($statut, ['planifie', 'realise', 'reporte', 'annule'], true)) {
        $where[] = 'e.statut = ?';
        $binds[] = $statut;
    }
    if ($annee) {
        $where[] = 'e.annee = ?';
        $binds[] = $annee;
    }
    $whereSql = implode(' AND ', $where);

    $rows = Db::fetchAll(
        "SELECT e.id, e.user_id, e.evaluator_id, e.annee, e.date_entretien, e.statut,
                e.signed_at, e.created_at,
                u.prenom, u.nom, u.email,
                ev.prenom AS eval_prenom, ev.nom AS eval_nom,
                fn.nom AS fonction, fn.secteur_fegems
         FROM entretiens_annuels e
         JOIN users u ON u.id = e.user_id
         LEFT JOIN users ev ON ev.id = e.evaluator_id
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE $whereSql
         ORDER BY e.date_entretien IS NULL, e.date_entretien ASC, u.nom ASC",
        $binds
    );

    // Échéances : users actifs avec prochain_entretien_date <= +30 jours et pas d'entretien planifié futur
    $echeances = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.prochain_entretien_date,
                fn.nom AS fonction, fn.secteur_fegems,
                DATEDIFF(u.prochain_entretien_date, CURDATE()) AS jours_restants
         FROM users u
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE u.is_active = 1
           AND u.prochain_entretien_date IS NOT NULL
           AND u.prochain_entretien_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
           AND NOT EXISTS (
               SELECT 1 FROM entretiens_annuels e2
               WHERE e2.user_id = u.id AND e2.statut = 'planifie' AND e2.date_entretien >= CURDATE()
           )
         ORDER BY u.prochain_entretien_date ASC
         LIMIT 30"
    );

    respond([
        'success' => true,
        'entretiens' => $rows,
        'echeances'  => $echeances,
    ]);
}

function admin_get_entretien_detail()
{
    require_responsable();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');

    $entretien = Db::fetch(
        "SELECT e.*, u.prenom, u.nom, u.email, u.experience_fonction_annees, u.cct,
                ev.prenom AS eval_prenom, ev.nom AS eval_nom,
                fn.nom AS fonction, fn.secteur_fegems
         FROM entretiens_annuels e
         JOIN users u ON u.id = e.user_id
         LEFT JOIN users ev ON ev.id = e.evaluator_id
         LEFT JOIN fonctions fn ON fn.id = u.fonction_id
         WHERE e.id = ?",
        [$id]
    );
    if (!$entretien) not_found('Entretien introuvable');

    // Synthèse compétences du collab
    $synthese = Db::fetch(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN priorite = 'haute' THEN 1 ELSE 0 END) AS prio_haute,
                SUM(CASE WHEN priorite = 'moyenne' THEN 1 ELSE 0 END) AS prio_moyenne,
                ROUND(AVG(niveau_actuel), 2) AS niveau_moyen
         FROM competences_user
         WHERE user_id = ?",
        [$entretien['user_id']]
    );

    // Objectifs liés à cet entretien (origine) ou de l'année
    $objectifs = Db::fetchAll(
        "SELECT o.*, t.nom AS thematique_nom
         FROM competences_objectifs_annuels o
         LEFT JOIN competences_thematiques t ON t.id = o.thematique_id_liee
         WHERE o.user_id = ?
           AND (o.entretien_origine_id = ? OR o.annee = ?)
         ORDER BY o.statut, o.ordre",
        [$entretien['user_id'], $id, $entretien['annee']]
    );

    respond([
        'success' => true,
        'entretien' => $entretien,
        'synthese'  => $synthese,
        'objectifs' => $objectifs,
    ]);
}

function admin_save_entretien()
{
    require_responsable();
    global $params;

    $id      = Sanitize::text($params['id'] ?? '', 36);
    $userId  = Sanitize::text($params['user_id'] ?? '', 36);
    $evalId  = Sanitize::text($params['evaluator_id'] ?? '', 36) ?: null;
    $annee   = (int)($params['annee'] ?? date('Y'));
    $date    = Sanitize::date($params['date_entretien'] ?? null);
    $statut  = in_array($params['statut'] ?? '', ['planifie','realise','reporte','annule'], true) ? $params['statut'] : 'planifie';
    $notesM  = $params['notes_manager'] ?? null;
    $notesC  = $params['notes_collaborateur'] ?? null;

    if (!$userId) bad_request('user_id requis');

    if ($id) {
        Db::exec(
            "UPDATE entretiens_annuels
             SET evaluator_id = ?, annee = ?, date_entretien = ?, statut = ?,
                 notes_manager = ?, notes_collaborateur = ?,
                 signed_at = CASE WHEN ? = 'realise' AND signed_at IS NULL THEN NOW() ELSE signed_at END
             WHERE id = ?",
            [$evalId, $annee, $date, $statut, $notesM, $notesC, $statut, $id]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO entretiens_annuels
             (id, user_id, evaluator_id, annee, date_entretien, statut, notes_manager, notes_collaborateur)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $userId, $evalId, $annee, $date, $statut, $notesM, $notesC]
        );
    }

    // Si réalisé : mettre à jour prochain_entretien_date du user (selon paramètre fréquence)
    if ($statut === 'realise' && $date) {
        $freq = (int) Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'entr.frequence_mois'") ?: 12;
        Db::exec(
            "UPDATE users SET prochain_entretien_date = DATE_ADD(?, INTERVAL ? MONTH) WHERE id = ?",
            [$date, $freq, $userId]
        );
    }

    respond(['success' => true, 'id' => $id]);
}

function admin_delete_entretien()
{
    require_admin();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');
    Db::exec("DELETE FROM entretiens_annuels WHERE id = ?", [$id]);
    respond(['success' => true]);
}

function admin_save_objectif_annuel()
{
    require_responsable();
    global $params;
    $id     = Sanitize::text($params['id'] ?? '', 36);
    $userId = Sanitize::text($params['user_id'] ?? '', 36);
    $annee  = (int)($params['annee'] ?? date('Y'));
    $libelle = trim($params['libelle'] ?? '');
    $description = $params['description'] ?? null;
    $thematiqueId = Sanitize::text($params['thematique_id_liee'] ?? '', 36) ?: null;
    $statut = in_array($params['statut'] ?? '', ['a_definir','en_cours','atteint','reporte','abandonne'], true) ? $params['statut'] : 'a_definir';
    $entretienId = Sanitize::text($params['entretien_origine_id'] ?? '', 36) ?: null;
    $trimestre = in_array($params['trimestre_cible'] ?? '', ['Q1','Q2','Q3','Q4','annuel'], true) ? $params['trimestre_cible'] : 'annuel';

    if (!$userId || !$libelle) bad_request('user_id et libelle requis');

    if ($id) {
        Db::exec(
            "UPDATE competences_objectifs_annuels
             SET libelle = ?, description = ?, thematique_id_liee = ?, statut = ?, trimestre_cible = ?,
                 date_atteint = CASE WHEN ? = 'atteint' AND date_atteint IS NULL THEN CURDATE() ELSE date_atteint END
             WHERE id = ?",
            [$libelle, $description, $thematiqueId, $statut, $trimestre, $statut, $id]
        );
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO competences_objectifs_annuels
             (id, user_id, annee, trimestre_cible, libelle, description, thematique_id_liee, statut, entretien_origine_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $userId, $annee, $trimestre, $libelle, $description, $thematiqueId, $statut, $entretienId]
        );
    }
    respond(['success' => true, 'id' => $id]);
}

function admin_delete_objectif_annuel()
{
    require_responsable();
    global $params;
    $id = Sanitize::text($params['id'] ?? '', 36);
    if (!$id) bad_request('id requis');
    Db::exec("DELETE FROM competences_objectifs_annuels WHERE id = ?", [$id]);
    respond(['success' => true]);
}

// ─── Hook auto-init competences_user depuis profil_attendu ──────────────────
//
// Appelé automatiquement quand un user est créé ou change de fonction.
// - Récupère les lignes profil_attendu requises pour le secteur
// - Pour chaque thématique : crée la ligne si absente (niveau_actuel = NULL),
//   ou met à jour niveau_requis sans toucher au niveau_actuel existant.
// Retourne le nombre de lignes insérées + mises à jour.

function init_competences_for_user(string $userId, ?string $fonctionId): array
{
    if (!$fonctionId) return ['inserted' => 0, 'updated' => 0, 'skipped' => 'no_fonction'];

    $secteur = Db::getOne("SELECT secteur_fegems FROM fonctions WHERE id = ?", [$fonctionId]);
    if (!$secteur || !in_array($secteur, COMP_SECTEURS, true)) {
        return ['inserted' => 0, 'updated' => 0, 'skipped' => 'no_secteur'];
    }

    // Lignes du profil attendu pour ce secteur
    $rows = Db::fetchAll(
        "SELECT thematique_id, niveau_requis, type_formation_recommande
         FROM competences_profil_attendu
         WHERE secteur = ? AND requis = 1 AND niveau_requis IS NOT NULL",
        [$secteur]
    );

    $inserted = 0;
    $updated = 0;
    foreach ($rows as $r) {
        $existing = Db::fetch(
            "SELECT id FROM competences_user WHERE user_id = ? AND thematique_id = ?",
            [$userId, $r['thematique_id']]
        );
        if ($existing) {
            Db::exec(
                "UPDATE competences_user
                 SET niveau_requis = ?, type_action = COALESCE(type_action, ?)
                 WHERE id = ?",
                [$r['niveau_requis'], $r['type_formation_recommande'], $existing['id']]
            );
            $updated++;
        } else {
            Db::exec(
                "INSERT INTO competences_user
                 (id, user_id, thematique_id, niveau_actuel, niveau_requis, type_action)
                 VALUES (?, ?, ?, NULL, ?, ?)",
                [Uuid::v4(), $userId, $r['thematique_id'], $r['niveau_requis'], $r['type_formation_recommande']]
            );
            $inserted++;
        }
    }

    return ['inserted' => $inserted, 'updated' => $updated, 'secteur' => $secteur];
}

// Endpoint admin pour appliquer manuellement le profil (bouton "Régénérer compétences")
function admin_init_competences_for_user()
{
    require_responsable();
    global $params;
    $userId = Sanitize::text($params['user_id'] ?? '', 36);
    if (!$userId) bad_request('user_id requis');
    $fonctionId = Db::getOne("SELECT fonction_id FROM users WHERE id = ?", [$userId]);
    $result = init_competences_for_user($userId, $fonctionId);
    respond(['success' => true] + $result);
}
