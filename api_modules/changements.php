<?php
/**
 * Changements d'horaire API actions (employee-side)
 * Workflow: A demande → B confirme → Admin valide/refuse
 * Support échange croisé : 2 dates différentes (date_demandeur + date_destinataire)
 */
require_once __DIR__ . '/../core/Notification.php';

function get_mes_changements()
{
    $user = require_auth();

    $changements = Db::fetchAll(
        "SELECT ch.*,
                ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom,
                ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom,
                ht_dem.code AS horaire_demandeur_code, ht_dem.nom AS horaire_demandeur_nom, ht_dem.couleur AS horaire_demandeur_couleur,
                ht_dest.code AS horaire_destinataire_code, ht_dest.nom AS horaire_destinataire_nom, ht_dest.couleur AS horaire_destinataire_couleur,
                m_dem.nom AS module_demandeur_nom, m_dest.nom AS module_destinataire_nom,
                ut.prenom AS traite_par_prenom, ut.nom AS traite_par_nom
         FROM changements_horaire ch
         JOIN users ud ON ud.id = ch.demandeur_id
         JOIN users ude ON ude.id = ch.destinataire_id
         JOIN planning_assignations pa_dem ON pa_dem.id = ch.assignation_demandeur_id
         JOIN planning_assignations pa_dest ON pa_dest.id = ch.assignation_destinataire_id
         LEFT JOIN horaires_types ht_dem ON ht_dem.id = pa_dem.horaire_type_id
         LEFT JOIN horaires_types ht_dest ON ht_dest.id = pa_dest.horaire_type_id
         LEFT JOIN modules m_dem ON m_dem.id = pa_dem.module_id
         LEFT JOIN modules m_dest ON m_dest.id = pa_dest.module_id
         LEFT JOIN users ut ON ut.id = ch.traite_par
         WHERE ch.demandeur_id = ? OR ch.destinataire_id = ?
         ORDER BY ch.created_at DESC",
        [$user['id'], $user['id']]
    );

    respond(['success' => true, 'changements' => $changements]);
}

function get_collegues()
{
    global $params;
    $user = require_auth();

    $dateFilter = Sanitize::date($params['date'] ?? '');

    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.photo, u.taux, u.fonction_id,
                f.nom AS fonction_nom, f.code AS fonction_code,
                m.nom AS module_nom, m.code AS module_code
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN (
             SELECT pa.user_id, pa.module_id
             FROM planning_assignations pa
             WHERE pa.module_id IS NOT NULL
             ORDER BY pa.date_jour DESC
             LIMIT 1000
         ) last_pa ON last_pa.user_id = u.id
         LEFT JOIN modules m ON m.id = last_pa.module_id
         WHERE u.id != ? AND u.is_active = 1
         GROUP BY u.id
         ORDER BY u.nom, u.prenom",
        [$user['id']]
    );

    // If date provided, add each colleague's shift on that day
    if ($dateFilter) {
        $mois = substr($dateFilter, 0, 7);
        $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
        if ($planning) {
            foreach ($users as &$u) {
                $assign = Db::fetch(
                    "SELECT pa.horaire_type_id, pa.statut AS assign_statut,
                            ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur
                     FROM planning_assignations pa
                     LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
                     WHERE pa.planning_id = ? AND pa.date_jour = ? AND pa.user_id = ?",
                    [$planning['id'], $dateFilter, $u['id']]
                );
                $u['shift_on_date'] = $assign ?: null;
            }
            unset($u);
        }
    }

    respond([
        'success' => true,
        'data' => $users,
        'my_fonction_id' => $user['fonction_id'] ?? null
    ]);
}

function get_collegue_planning_mois()
{
    global $params;
    $user = require_auth();

    $collegueId = $params['collegue_id'] ?? '';
    $mois = $params['mois'] ?? '';
    if (!$collegueId || !$mois) bad_request('Collègue et mois requis');

    // Validate colleague exists
    $collegue = Db::fetch(
        "SELECT u.id, u.prenom, u.nom, f.nom AS fonction_nom, f.code AS fonction_code
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.id = ? AND u.is_active = 1",
        [$collegueId]
    );
    if (!$collegue) bad_request('Collègue non trouvé');

    // Find planning for this month
    $planning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) {
        respond(['success' => true, 'collegue' => $collegue, 'assignations' => [], 'planning_statut' => null]);
    }

    $assignations = Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.date_jour, pa.statut AS assign_statut,
                ht.id AS horaire_type_id, ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.couleur, ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom, m.code AS module_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.user_id = ?
         ORDER BY pa.date_jour",
        [$planning['id'], $collegueId]
    );

    respond([
        'success' => true,
        'collegue' => $collegue,
        'assignations' => $assignations,
        'planning_statut' => $planning['statut']
    ]);
}

function get_mon_planning_mois()
{
    global $params;
    $user = require_auth();

    $mois = $params['mois'] ?? '';
    if (!$mois) bad_request('Mois requis');

    $planning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) {
        respond(['success' => true, 'assignations' => [], 'planning_statut' => null]);
    }

    $assignations = Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.date_jour, pa.statut AS assign_statut,
                ht.id AS horaire_type_id, ht.code AS horaire_code, ht.nom AS horaire_nom,
                ht.couleur, ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom, m.code AS module_code
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.user_id = ?
         ORDER BY pa.date_jour",
        [$planning['id'], $user['id']]
    );

    respond([
        'success' => true,
        'assignations' => $assignations,
        'planning_statut' => $planning['statut']
    ]);
}

function modifier_changement()
{
    global $params;
    $user = require_auth();

    $id    = $params['id'] ?? '';
    $motif = Sanitize::text($params['motif'] ?? '');
    if (!$id) bad_request('ID requis');

    $ch = Db::fetch("SELECT * FROM changements_horaire WHERE id = ?", [$id]);
    if (!$ch) not_found();
    if ($ch['demandeur_id'] !== $user['id']) forbidden();
    if ($ch['statut'] !== 'en_attente_collegue') bad_request('Cette demande ne peut plus être modifiée');

    Db::exec(
        "UPDATE changements_horaire SET motif = ?, updated_at = NOW() WHERE id = ?",
        [$motif ?: null, $id]
    );
    respond(['success' => true, 'message' => 'Demande modifiée']);
}

function get_collegues_planning()
{
    global $params;
    $user = require_auth();

    $date = Sanitize::date($params['date'] ?? '');
    if (!$date) bad_request('Date requise');

    $mois = substr($date, 0, 7);

    $planning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) {
        respond(['success' => true, 'collegues' => []]);
    }

    $fonctionId = $user['fonction_id'] ?? null;
    $assignations = $fonctionId ? Db::fetchAll(
        "SELECT pa.id AS assignation_id, pa.user_id, pa.horaire_type_id, pa.module_id, pa.statut AS assign_statut,
                u.prenom, u.nom,
                ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id AND u.is_active = 1 AND u.fonction_id = ?
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.date_jour = ? AND pa.user_id != ?
           AND pa.statut IN ('present','entraide')
           AND pa.horaire_type_id IS NOT NULL
         ORDER BY u.nom, u.prenom",
        [$fonctionId, $planning['id'], $date, $user['id']]
    ) : [];

    $monAssignation = Db::fetch(
        "SELECT pa.id AS assignation_id, pa.horaire_type_id, pa.module_id, pa.statut AS assign_statut,
                ht.code AS horaire_code, ht.nom AS horaire_nom, ht.couleur AS horaire_couleur,
                ht.heure_debut, ht.heure_fin,
                m.nom AS module_nom
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ? AND pa.date_jour = ? AND pa.user_id = ?",
        [$planning['id'], $date, $user['id']]
    );

    respond([
        'success' => true,
        'collegues' => $assignations,
        'mon_assignation' => $monAssignation,
        'planning_statut' => $planning['statut']
    ]);
}

function submit_changement()
{
    global $params;
    $user = require_auth();

    $destinataireId  = $params['destinataire_id'] ?? '';
    $dateDemandeur   = Sanitize::date($params['date_demandeur'] ?? '');
    $dateDestinataire = Sanitize::date($params['date_destinataire'] ?? '');
    $motif = Sanitize::text($params['motif'] ?? '', 500);

    if (!$destinataireId || !$dateDemandeur || !$dateDestinataire) {
        bad_request('Destinataire et les deux dates sont requis');
    }
    if ($destinataireId === $user['id']) bad_request('Vous ne pouvez pas échanger avec vous-même');

    // Verify destinataire exists and is active
    $dest = Db::fetch("SELECT id, fonction_id FROM users WHERE id = ? AND is_active = 1", [$destinataireId]);
    if (!$dest) bad_request('Collègue non trouvé');

    // Validate dates are not in the past
    $today = date('Y-m-d');
    if ($dateDemandeur < $today) bad_request('La date à céder ne peut pas être dans le passé');
    if ($dateDestinataire < $today) bad_request('La date à prendre ne peut pas être dans le passé');

    // Get plannings for both months (may be different)
    $moisDem = substr($dateDemandeur, 0, 7);
    $moisDest = substr($dateDestinataire, 0, 7);

    $planningDem = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$moisDem]);
    if (!$planningDem) bad_request("Aucun planning pour le mois $moisDem");
    if ($planningDem['statut'] === 'brouillon') bad_request("Le planning de $moisDem n'est pas encore publié");

    $planningDest = $moisDem === $moisDest
        ? $planningDem
        : Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$moisDest]);
    if (!$planningDest) bad_request("Aucun planning pour le mois $moisDest");
    if ($planningDest['statut'] === 'brouillon') bad_request("Le planning de $moisDest n'est pas encore publié");

    // Get demandeur's assignation on date_demandeur (the shift A gives away, or jour off)
    $assignDem = Db::fetch(
        "SELECT id, horaire_type_id FROM planning_assignations
         WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
        [$planningDem['id'], $dateDemandeur, $user['id']]
    );
    // If no assignation (jour off), create a repos entry
    if (!$assignDem) {
        $offId = Uuid::v4();
        Db::exec(
            "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut)
             VALUES (?, ?, ?, ?, NULL, NULL, 'repos')",
            [$offId, $planningDem['id'], $user['id'], $dateDemandeur]
        );
        $assignDem = ['id' => $offId, 'horaire_type_id' => null];
    }

    // Get destinataire's assignation on date_destinataire (the shift A wants to take, or jour off)
    $assignDest = Db::fetch(
        "SELECT id, horaire_type_id FROM planning_assignations
         WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
        [$planningDest['id'], $dateDestinataire, $destinataireId]
    );
    // If no assignation (jour off), create a repos entry
    if (!$assignDest) {
        $offId = Uuid::v4();
        Db::exec(
            "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut)
             VALUES (?, ?, ?, ?, NULL, NULL, 'repos')",
            [$offId, $planningDest['id'], $destinataireId, $dateDestinataire]
        );
        $assignDest = ['id' => $offId, 'horaire_type_id' => null];
    }

    // At least one side must have a horaire (no point swapping two repos)
    if (!$assignDem['horaire_type_id'] && !$assignDest['horaire_type_id']) {
        bad_request("Au moins un des deux doit avoir un horaire assigné");
    }

    // Check no pending changement already exists for overlapping dates/pair
    $existing = Db::fetch(
        "SELECT id FROM changements_horaire
         WHERE statut IN ('en_attente_collegue','confirme_collegue')
           AND ((demandeur_id = ? AND destinataire_id = ?) OR (demandeur_id = ? AND destinataire_id = ?))
           AND (date_demandeur = ? OR date_destinataire = ? OR date_demandeur = ? OR date_destinataire = ?)",
        [$user['id'], $destinataireId, $destinataireId, $user['id'],
         $dateDemandeur, $dateDemandeur, $dateDestinataire, $dateDestinataire]
    );
    if ($existing) bad_request('Une demande de changement est déjà en cours pour ces dates');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO changements_horaire
            (id, demandeur_id, destinataire_id, planning_demandeur_id, planning_destinataire_id,
             date_demandeur, date_destinataire, assignation_demandeur_id, assignation_destinataire_id, motif)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $user['id'], $destinataireId,
         $planningDem['id'], $planningDest['id'],
         $dateDemandeur, $dateDestinataire,
         $assignDem['id'], $assignDest['id'],
         $motif ?: null]
    );

    // Cas 3: jour OFF avec compensation → créer le 2e changement
    $dateCompensation = Sanitize::date($params['date_compensation'] ?? '');
    if ($dateCompensation) {
        $moisComp = substr($dateCompensation, 0, 7);
        $planningComp = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$moisComp]);

        // Assignation du demandeur le jour de compensation (il cède ce jour)
        $assignComp = Db::fetch(
            "SELECT id, horaire_type_id FROM planning_assignations
             WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
            [$planningComp['id'] ?? $planningDem['id'], $dateCompensation, $user['id']]
        );

        // Assignation du destinataire le jour de compensation (il reçoit)
        $assignDestComp = Db::fetch(
            "SELECT id FROM planning_assignations
             WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
            [$planningComp['id'] ?? $planningDem['id'], $dateCompensation, $destinataireId]
        );
        if (!$assignDestComp) {
            $compDestId = Uuid::v4();
            Db::exec(
                "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut)
                 VALUES (?, ?, ?, ?, NULL, NULL, 'repos')",
                [$compDestId, $planningComp['id'] ?? $planningDem['id'], $destinataireId, $dateCompensation]
            );
            $assignDestComp = ['id' => $compDestId];
        }

        if ($assignComp) {
            $id2 = Uuid::v4();
            Db::exec(
                "INSERT INTO changements_horaire
                    (id, demandeur_id, destinataire_id, planning_demandeur_id, planning_destinataire_id,
                     date_demandeur, date_destinataire, assignation_demandeur_id, assignation_destinataire_id, motif)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$id2, $user['id'], $destinataireId,
                 $planningComp['id'] ?? $planningDem['id'], $planningComp['id'] ?? $planningDem['id'],
                 $dateCompensation, $dateCompensation,
                 $assignComp['id'], $assignDestComp['id'],
                 ($motif ? $motif . ' (compensation)' : 'Compensation jour OFF')]
            );
        }
    }

    // Notify destinataire
    $demPrenom = $user['prenom'] ?? '';
    $demNom = $user['nom'] ?? '';
    $dateDemFr = date('d.m.Y', strtotime($dateDemandeur));
    $dateDestFr = date('d.m.Y', strtotime($dateDestinataire));
    $notifMsg = "$demPrenom $demNom vous propose un échange : céder votre horaire du $dateDestFr contre le sien du $dateDemFr.";
    if ($dateCompensation) {
        $dateCompFr = date('d.m.Y', strtotime($dateCompensation));
        $notifMsg .= " Compensation : $demPrenom cède aussi son horaire du $dateCompFr.";
    }
    Notification::create(
        $destinataireId,
        'changement_demande',
        'Demande de changement',
        $notifMsg,
        'changements'
    );

    respond(['success' => true, 'message' => 'Demande envoyée au collègue', 'id' => $id]);
}

function confirmer_changement()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ch = Db::fetch(
        "SELECT * FROM changements_horaire WHERE id = ? AND destinataire_id = ? AND statut = 'en_attente_collegue'",
        [$id, $user['id']]
    );
    if (!$ch) bad_request('Demande non trouvée ou déjà traitée');

    Db::exec(
        "UPDATE changements_horaire SET statut = 'confirme_collegue', confirme_at = NOW() WHERE id = ?",
        [$id]
    );

    $destPrenom = $user['prenom'] ?? '';
    $destNom = $user['nom'] ?? '';
    $dateDemFr = date('d.m.Y', strtotime($ch['date_demandeur']));
    $dateDestFr = date('d.m.Y', strtotime($ch['date_destinataire']));
    Notification::create(
        $ch['demandeur_id'],
        'changement_accepte',
        'Changement confirmé',
        "$destPrenom $destNom a confirmé l'échange ($dateDemFr ↔ $dateDestFr). En attente de validation.",
        'changements'
    );

    respond(['success' => true, 'message' => 'Changement confirmé — en attente de validation admin']);
}

function refuser_changement()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    $raison = Sanitize::text($params['raison'] ?? '', 500);
    if (!$id) bad_request('ID requis');

    $ch = Db::fetch(
        "SELECT * FROM changements_horaire WHERE id = ? AND destinataire_id = ? AND statut = 'en_attente_collegue'",
        [$id, $user['id']]
    );
    if (!$ch) bad_request('Demande non trouvée ou déjà traitée');

    Db::exec(
        "UPDATE changements_horaire SET statut = 'refuse', refuse_par = 'collegue', raison_refus = ? WHERE id = ?",
        [$raison ?: null, $id]
    );

    $destPrenom = $user['prenom'] ?? '';
    $destNom = $user['nom'] ?? '';
    $dateDemFr = date('d.m.Y', strtotime($ch['date_demandeur']));
    $dateDestFr = date('d.m.Y', strtotime($ch['date_destinataire']));
    Notification::create(
        $ch['demandeur_id'],
        'changement_refuse',
        'Changement refusé',
        "$destPrenom $destNom a refusé l'échange ($dateDemFr ↔ $dateDestFr).",
        'changements'
    );

    respond(['success' => true, 'message' => 'Demande refusée']);
}

function annuler_changement()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ch = Db::fetch(
        "SELECT * FROM changements_horaire WHERE id = ? AND demandeur_id = ? AND statut = 'en_attente_collegue'",
        [$id, $user['id']]
    );
    if (!$ch) bad_request('Demande non trouvée ou déjà traitée');

    Db::exec("DELETE FROM changements_horaire WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Demande annulée']);
}
