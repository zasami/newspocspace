<?php

/**
 * Get proposals for a month
 */
function admin_get_proposals()
{
    global $params;
    require_responsable();

    $mois = $params['mois'] ?? '';
    if (!$mois) bad_request('Mois requis');

    $proposals = Db::fetchAll(
        "SELECT pp.*, u.prenom, u.nom AS creator_nom
         FROM planning_proposals pp
         LEFT JOIN users u ON u.id = pp.created_by
         WHERE pp.mois_annee = ?
         ORDER BY pp.created_at",
        [$mois]
    );

    // Count votes for each
    foreach ($proposals as &$p) {
        $votes = Db::fetchAll(
            "SELECT vote, COUNT(*) as cnt FROM planning_votes WHERE proposal_id = ? GROUP BY vote",
            [$p['id']]
        );
        $p['votes_pour'] = 0;
        $p['votes_contre'] = 0;
        foreach ($votes as $v) {
            if ($v['vote'] === 'pour') $p['votes_pour'] = (int)$v['cnt'];
            if ($v['vote'] === 'contre') $p['votes_contre'] = (int)$v['cnt'];
        }
        $p['total_votes'] = $p['votes_pour'] + $p['votes_contre'];
    }
    unset($p);

    respond(['success' => true, 'proposals' => $proposals]);
}

/**
 * Create a proposal from current planning assignations
 */
function admin_create_proposal()
{
    global $params;
    require_admin();

    $mois = $params['mois'] ?? '';
    $label = Sanitize::text($params['label'] ?? '', 100) ?: 'Proposition';

    if (!$mois) bad_request('Mois requis');

    // Check max 3 proposals per month
    $count = Db::getOne(
        "SELECT COUNT(*) FROM planning_proposals WHERE mois_annee = ? AND statut NOT IN ('rejete')",
        [$mois]
    );
    if ($count >= 3) bad_request('Maximum 3 propositions par mois');

    // Get current planning
    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) bad_request('Aucun planning pour ce mois');

    // Snapshot current assignations
    $assignations = Db::fetchAll(
        "SELECT pa.user_id, pa.date_jour, pa.horaire_type_id, pa.module_id, pa.statut, pa.notes
         FROM planning_assignations pa
         WHERE pa.planning_id = ?",
        [$planning['id']]
    );

    if (empty($assignations)) bad_request('Le planning est vide');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO planning_proposals (id, mois_annee, label, snapshot, created_by)
         VALUES (?, ?, ?, ?, ?)",
        [$id, $mois, $label, json_encode($assignations), $_SESSION['zt_user']['id']]
    );

    respond(['success' => true, 'id' => $id, 'message' => 'Proposition créée']);
}

/**
 * Open/close voting on proposals
 */
function admin_toggle_vote_status()
{
    global $params;
    require_admin();

    $proposalId = $params['proposal_id'] ?? '';
    $statut = $params['statut'] ?? '';

    if (!$proposalId || !in_array($statut, ['ouvert', 'ferme'])) bad_request('Paramètres invalides');

    Db::exec("UPDATE planning_proposals SET statut = ? WHERE id = ?", [$statut, $proposalId]);
    respond(['success' => true]);
}

/**
 * Validate a proposal (apply it as the official planning)
 */
function admin_validate_proposal()
{
    global $params;
    require_admin();

    $proposalId = $params['proposal_id'] ?? '';
    if (!$proposalId) bad_request('proposal_id requis');

    $proposal = Db::fetch("SELECT * FROM planning_proposals WHERE id = ?", [$proposalId]);
    if (!$proposal) not_found('Proposition non trouvée');

    $mois = $proposal['mois_annee'];
    $snapshot = json_decode($proposal['snapshot'], true);
    if (empty($snapshot)) bad_request('Snapshot vide');

    // Get or create planning
    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) bad_request('Aucun planning pour ce mois');

    $planningId = $planning['id'];

    // Replace assignations with snapshot
    Db::exec("DELETE FROM planning_assignations WHERE planning_id = ?", [$planningId]);

    $stmt = Db::connect()->prepare(
        "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($snapshot as $a) {
        $stmt->execute([
            Uuid::v4(), $planningId,
            $a['user_id'], $a['date_jour'], $a['horaire_type_id'],
            $a['module_id'], $a['statut'], $a['notes'] ?? null
        ]);
    }

    // Mark this proposal as validated, others as rejected
    Db::exec("UPDATE planning_proposals SET statut = 'valide' WHERE id = ?", [$proposalId]);
    Db::exec(
        "UPDATE planning_proposals SET statut = 'rejete' WHERE mois_annee = ? AND id != ?",
        [$mois, $proposalId]
    );

    respond(['success' => true, 'message' => 'Proposition validée et appliquée au planning']);
}

/**
 * Delete a proposal
 */
function admin_delete_proposal()
{
    global $params;
    require_admin();

    $proposalId = $params['proposal_id'] ?? '';
    if (!$proposalId) bad_request('proposal_id requis');

    Db::exec("DELETE FROM planning_proposals WHERE id = ?", [$proposalId]);
    respond(['success' => true]);
}

/**
 * Get vote details for a proposal
 */
function admin_get_proposal_votes()
{
    global $params;
    require_responsable();

    $proposalId = $params['proposal_id'] ?? '';
    if (!$proposalId) bad_request('proposal_id requis');

    $votes = Db::fetchAll(
        "SELECT pv.vote, pv.commentaire, pv.created_at,
                u.prenom, u.nom, f.code AS fonction_code
         FROM planning_votes pv
         JOIN users u ON u.id = pv.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE pv.proposal_id = ?
         ORDER BY pv.created_at",
        [$proposalId]
    );

    respond(['success' => true, 'votes' => $votes]);
}
