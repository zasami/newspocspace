<?php
/**
 * Admin — Changements d'horaire entre collègues
 * Support échange croisé : 2 dates différentes (date_demandeur + date_destinataire)
 */
require_once __DIR__ . '/../../core/Notification.php';

function admin_get_changements()
{
    global $params;
    require_responsable();

    $statut = $params['statut'] ?? '';

    $sql = "SELECT ch.*,
                   ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom, ud.employee_id AS demandeur_employee_id,
                   ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom, ude.employee_id AS destinataire_employee_id,
                   fd.code AS demandeur_fonction, fde.code AS destinataire_fonction,
                   ht_dem.code AS horaire_demandeur_code, ht_dem.nom AS horaire_demandeur_nom, ht_dem.couleur AS horaire_demandeur_couleur,
                   ht_dest.code AS horaire_destinataire_code, ht_dest.nom AS horaire_destinataire_nom, ht_dest.couleur AS horaire_destinataire_couleur,
                   m_dem.nom AS module_demandeur_nom, m_dest.nom AS module_destinataire_nom,
                   ut.prenom AS traite_par_prenom, ut.nom AS traite_par_nom
            FROM changements_horaire ch
            JOIN users ud ON ud.id = ch.demandeur_id
            JOIN users ude ON ude.id = ch.destinataire_id
            JOIN planning_assignations pa_dem ON pa_dem.id = ch.assignation_demandeur_id
            JOIN planning_assignations pa_dest ON pa_dest.id = ch.assignation_destinataire_id
            LEFT JOIN fonctions fd ON fd.id = ud.fonction_id
            LEFT JOIN fonctions fde ON fde.id = ude.fonction_id
            LEFT JOIN horaires_types ht_dem ON ht_dem.id = pa_dem.horaire_type_id
            LEFT JOIN horaires_types ht_dest ON ht_dest.id = pa_dest.horaire_type_id
            LEFT JOIN modules m_dem ON m_dem.id = pa_dem.module_id
            LEFT JOIN modules m_dest ON m_dest.id = pa_dest.module_id
            LEFT JOIN users ut ON ut.id = ch.traite_par
            WHERE 1=1";
    $p = [];

    if ($statut) {
        $sql .= " AND ch.statut = ?";
        $p[] = $statut;
    }

    $sql .= " ORDER BY ch.created_at DESC";

    respond(['success' => true, 'changements' => Db::fetchAll($sql, $p)]);
}

function admin_valider_changement()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $decision = $params['decision'] ?? '';
    $raison = Sanitize::text($params['raison'] ?? '', 500);

    if (!$id || !in_array($decision, ['valide', 'refuse'])) {
        bad_request('ID et décision requis');
    }

    $ch = Db::fetch(
        "SELECT * FROM changements_horaire WHERE id = ? AND statut = 'confirme_collegue'",
        [$id]
    );
    if (!$ch) bad_request('Demande non trouvée ou pas encore confirmée par le collègue');

    $dateDemFr = date('d.m.Y', strtotime($ch['date_demandeur']));
    $dateDestFr = date('d.m.Y', strtotime($ch['date_destinataire']));

    if ($decision === 'valide') {
        // Fetch the two "source" assignations (shifts being given away)
        $assignDem = Db::fetch(
            "SELECT horaire_type_id, module_id FROM planning_assignations WHERE id = ?",
            [$ch['assignation_demandeur_id']]
        );
        $assignDest = Db::fetch(
            "SELECT horaire_type_id, module_id FROM planning_assignations WHERE id = ?",
            [$ch['assignation_destinataire_id']]
        );

        if (!$assignDem || !$assignDest) {
            bad_request('Assignations introuvables — le planning a peut-être été modifié');
        }

        // Find or create B's assignation row on date_demandeur (where B will receive A's shift)
        $bOnDateDem = Db::fetch(
            "SELECT id FROM planning_assignations WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
            [$ch['planning_demandeur_id'], $ch['date_demandeur'], $ch['destinataire_id']]
        );
        if (!$bOnDateDem) {
            $newId = Uuid::v4();
            Db::exec(
                "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, statut)
                 VALUES (?, ?, ?, ?, 'repos')",
                [$newId, $ch['planning_demandeur_id'], $ch['destinataire_id'], $ch['date_demandeur']]
            );
            $bOnDateDem = ['id' => $newId];
        }

        // Find or create A's assignation row on date_destinataire (where A will receive B's shift)
        $aOnDateDest = Db::fetch(
            "SELECT id FROM planning_assignations WHERE planning_id = ? AND date_jour = ? AND user_id = ?",
            [$ch['planning_destinataire_id'], $ch['date_destinataire'], $ch['demandeur_id']]
        );
        if (!$aOnDateDest) {
            $newId = Uuid::v4();
            Db::exec(
                "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, statut)
                 VALUES (?, ?, ?, ?, 'repos')",
                [$newId, $ch['planning_destinataire_id'], $ch['demandeur_id'], $ch['date_destinataire']]
            );
            $aOnDateDest = ['id' => $newId];
        }

        // Perform the cross-day swap:
        // On date_demandeur: B gets A's shift, A goes to repos
        Db::exec(
            "UPDATE planning_assignations SET horaire_type_id = ?, module_id = ?, statut = 'present', updated_at = NOW() WHERE id = ?",
            [$assignDem['horaire_type_id'], $assignDem['module_id'], $bOnDateDem['id']]
        );
        Db::exec(
            "UPDATE planning_assignations SET horaire_type_id = NULL, module_id = NULL, statut = 'repos', updated_at = NOW() WHERE id = ?",
            [$ch['assignation_demandeur_id']]
        );

        // On date_destinataire: A gets B's shift, B goes to repos
        Db::exec(
            "UPDATE planning_assignations SET horaire_type_id = ?, module_id = ?, statut = 'present', updated_at = NOW() WHERE id = ?",
            [$assignDest['horaire_type_id'], $assignDest['module_id'], $aOnDateDest['id']]
        );
        Db::exec(
            "UPDATE planning_assignations SET horaire_type_id = NULL, module_id = NULL, statut = 'repos', updated_at = NOW() WHERE id = ?",
            [$ch['assignation_destinataire_id']]
        );

        Db::exec(
            "UPDATE changements_horaire SET statut = 'valide', traite_par = ?, traite_at = NOW() WHERE id = ?",
            [$_SESSION['ss_user']['id'], $id]
        );

        // Notify both users
        Notification::create($ch['demandeur_id'], 'changement_accepte', 'Changement validé',
            "Votre échange ($dateDemFr ↔ $dateDestFr) a été validé par la direction.", 'changements');
        Notification::create($ch['destinataire_id'], 'changement_accepte', 'Changement validé',
            "L'échange d'horaire ($dateDemFr ↔ $dateDestFr) a été validé par la direction.", 'changements');

        respond(['success' => true, 'message' => 'Changement validé — les horaires ont été échangés']);
    } else {
        Db::exec(
            "UPDATE changements_horaire SET statut = 'refuse', refuse_par = 'admin', raison_refus = ?, traite_par = ?, traite_at = NOW() WHERE id = ?",
            [$raison ?: null, $_SESSION['ss_user']['id'], $id]
        );

        Notification::create($ch['demandeur_id'], 'changement_refuse', 'Changement refusé',
            "L'échange ($dateDemFr ↔ $dateDestFr) a été refusé par la direction.", 'changements');
        Notification::create($ch['destinataire_id'], 'changement_refuse', 'Changement refusé',
            "L'échange ($dateDemFr ↔ $dateDestFr) a été refusé par la direction.", 'changements');

        respond(['success' => true, 'message' => 'Changement refusé']);
    }
}

/**
 * Fetch planning lines for both users in a changement request.
 * Returns a date range spanning both swap dates with surrounding context.
 */
function admin_get_changement_detail()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ch = Db::fetch(
        "SELECT ch.*,
                ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom,
                ude.prenom AS destinataire_prenom, ude.nom AS destinataire_nom,
                fd.code AS demandeur_fonction, fde.code AS destinataire_fonction
         FROM changements_horaire ch
         JOIN users ud ON ud.id = ch.demandeur_id
         JOIN users ude ON ude.id = ch.destinataire_id
         LEFT JOIN fonctions fd ON fd.id = ud.fonction_id
         LEFT JOIN fonctions fde ON fde.id = ude.fonction_id
         WHERE ch.id = ?",
        [$id]
    );
    if (!$ch) not_found('Demande introuvable');

    // Compute date range spanning both swap dates with 1 month buffer
    $dateDem = new DateTime($ch['date_demandeur']);
    $dateDest = new DateTime($ch['date_destinataire']);
    $earliest = $dateDem < $dateDest ? clone $dateDem : clone $dateDest;
    $latest   = $dateDem > $dateDest ? clone $dateDem : clone $dateDest;

    $startDate = (clone $earliest)->modify('first day of this month')->modify('-1 month');
    $dow = (int)$startDate->format('N');
    if ($dow > 1) $startDate->modify('-' . ($dow - 1) . ' days');

    $endDate = (clone $latest)->modify('last day of this month')->modify('+1 month');
    $dowEnd = (int)$endDate->format('N');
    if ($dowEnd < 7) $endDate->modify('+' . (7 - $dowEnd) . ' days');

    $startStr = $startDate->format('Y-m-d');
    $endStr   = $endDate->format('Y-m-d');

    $rows = Db::fetchAll(
        "SELECT pa.id, pa.user_id, pa.date_jour, pa.statut,
                ht.code AS horaire_code, ht.couleur AS horaire_couleur, ht.heure_debut, ht.heure_fin,
                m.code AS module_code, m.nom AS module_nom
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.user_id IN (?, ?)
           AND pa.date_jour BETWEEN ? AND ?
         ORDER BY pa.date_jour",
        [$ch['demandeur_id'], $ch['destinataire_id'], $startStr, $endStr]
    );

    $planning = [
        $ch['demandeur_id'] => [],
        $ch['destinataire_id'] => []
    ];
    foreach ($rows as $r) {
        $planning[$r['user_id']][$r['date_jour']] = $r;
    }

    // Build full days array
    $days = [];
    $d = clone $startDate;
    while ($d <= $endDate) {
        $days[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }

    // viewStart = index of earliest swap date
    $viewStart = array_search($earliest->format('Y-m-d'), $days);
    if ($viewStart === false) $viewStart = 0;

    respond([
        'success' => true,
        'changement' => $ch,
        'days' => $days,
        'start_date' => $startStr,
        'end_date' => $endStr,
        'view_start' => $viewStart,
        'swap_dates' => [$ch['date_demandeur'], $ch['date_destinataire']],
        'planning_demandeur' => $planning[$ch['demandeur_id']],
        'planning_destinataire' => $planning[$ch['destinataire_id']],
    ]);
}
