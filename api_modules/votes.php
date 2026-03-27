<?php

/**
 * Get open proposals for voting (employee side)
 */
function get_proposals_ouvertes()
{
    require_auth();

    $userId = $_SESSION['zt_user']['id'];

    // Get proposals that are open for voting
    $proposals = Db::fetchAll(
        "SELECT pp.id, pp.mois_annee, pp.label, pp.statut, pp.votes_pour, pp.votes_contre, pp.created_at
         FROM planning_proposals pp
         WHERE pp.statut = 'ouvert'
         ORDER BY pp.mois_annee DESC, pp.created_at"
    );

    // Check if user already voted + get snapshot summary
    foreach ($proposals as &$p) {
        $myVote = Db::fetch(
            "SELECT vote, commentaire FROM planning_votes WHERE proposal_id = ? AND user_id = ?",
            [$p['id'], $userId]
        );
        $p['my_vote'] = $myVote['vote'] ?? null;
        $p['my_comment'] = $myVote['commentaire'] ?? null;

        // Count actual votes
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
 * Get user's planning from a proposal snapshot
 */
function get_proposal_planning()
{
    require_auth();

    global $params;
    $proposalId = $params['proposal_id'] ?? '';
    if (!$proposalId) bad_request('proposal_id requis');

    $userId = $_SESSION['zt_user']['id'];

    $proposal = Db::fetch("SELECT * FROM planning_proposals WHERE id = ?", [$proposalId]);
    if (!$proposal) not_found('Proposition non trouvée');

    $snapshot = json_decode($proposal['snapshot'], true) ?: [];

    // Filter for this user
    $myAssignations = array_values(array_filter($snapshot, fn($a) => $a['user_id'] === $userId));

    // Enrich with horaire & module info
    $horaires = Db::fetchAll("SELECT id, code, nom, heure_debut, heure_fin, duree_effective, couleur FROM horaires_types");
    $horaireMap = [];
    foreach ($horaires as $h) $horaireMap[$h['id']] = $h;

    $modules = Db::fetchAll("SELECT id, code, nom FROM modules");
    $moduleMap = [];
    foreach ($modules as $m) $moduleMap[$m['id']] = $m;

    foreach ($myAssignations as &$a) {
        $h = $horaireMap[$a['horaire_type_id']] ?? null;
        $m = $moduleMap[$a['module_id']] ?? null;
        $a['horaire_code'] = $h['code'] ?? '';
        $a['horaire_nom'] = $h['nom'] ?? '';
        $a['heure_debut'] = $h['heure_debut'] ?? '';
        $a['heure_fin'] = $h['heure_fin'] ?? '';
        $a['couleur'] = $h['couleur'] ?? '#ccc';
        $a['module_code'] = $m['code'] ?? '';
    }
    unset($a);

    respond([
        'success' => true,
        'proposal' => [
            'id' => $proposal['id'],
            'label' => $proposal['label'],
            'mois_annee' => $proposal['mois_annee'],
        ],
        'assignations' => $myAssignations,
    ]);
}

/**
 * Submit a vote
 */
function submit_vote()
{
    require_auth();

    global $params;
    $proposalId = $params['proposal_id'] ?? '';
    $vote = $params['vote'] ?? '';
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 500);

    if (!$proposalId || !in_array($vote, ['pour', 'contre'])) bad_request('Paramètres invalides');

    $userId = $_SESSION['zt_user']['id'];

    // Check proposal is open
    $proposal = Db::fetch("SELECT statut FROM planning_proposals WHERE id = ?", [$proposalId]);
    if (!$proposal) not_found('Proposition non trouvée');
    if ($proposal['statut'] !== 'ouvert') bad_request('Le vote est fermé pour cette proposition');

    // Upsert vote
    $existing = Db::fetch(
        "SELECT id FROM planning_votes WHERE proposal_id = ? AND user_id = ?",
        [$proposalId, $userId]
    );

    if ($existing) {
        Db::exec(
            "UPDATE planning_votes SET vote = ?, commentaire = ? WHERE id = ?",
            [$vote, $commentaire, $existing['id']]
        );
    } else {
        Db::exec(
            "INSERT INTO planning_votes (id, proposal_id, user_id, vote, commentaire) VALUES (?, ?, ?, ?, ?)",
            [Uuid::v4(), $proposalId, $userId, $vote, $commentaire]
        );
    }

    respond(['success' => true, 'message' => 'Vote enregistré']);
}
