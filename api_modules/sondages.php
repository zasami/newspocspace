<?php

/**
 * Employee Sondages (Surveys) API actions
 */

function get_sondages_ouverts()
{
    global $params;
    require_auth();

    $userId = $_SESSION['zt_user']['id'];

    $list = Db::fetchAll(
        "SELECT s.id, s.titre, s.description, s.is_anonymous, s.created_at,
                u.prenom, u.nom,
                (SELECT COUNT(*) FROM sondage_questions WHERE sondage_id = s.id) AS nb_questions,
                (SELECT COUNT(DISTINCT sr.question_id) FROM sondage_reponses sr
                 INNER JOIN sondage_questions sq ON sq.id = sr.question_id AND sq.sondage_id = s.id
                 WHERE sr.user_id = ?) AS nb_repondu
         FROM sondages s
         LEFT JOIN users u ON u.id = s.created_by
         WHERE s.statut = 'ouvert'
         ORDER BY s.created_at DESC",
        [$userId]
    );

    respond(['success' => true, 'list' => $list]);
}

function get_sondage_detail()
{
    global $params;
    require_auth();

    $id = $params['id'] ?? '';
    if (empty($id)) bad_request('ID requis');

    $sondage = Db::fetch(
        "SELECT s.id, s.titre, s.description, s.is_anonymous, s.statut, s.created_at,
                u.prenom, u.nom
         FROM sondages s
         LEFT JOIN users u ON u.id = s.created_by
         WHERE s.id = ? AND s.statut IN ('ouvert', 'ferme')",
        [$id]
    );
    if (!$sondage) not_found('Sondage introuvable ou pas encore ouvert');

    $questions = Db::fetchAll(
        "SELECT * FROM sondage_questions WHERE sondage_id = ? ORDER BY ordre ASC",
        [$id]
    );
    foreach ($questions as &$q) {
        if (!empty($q['options'])) {
            $q['options'] = json_decode($q['options'], true);
        }
    }

    // User's existing answers
    $userId = $_SESSION['zt_user']['id'];
    $mesReponses = Db::fetchAll(
        "SELECT question_id, reponse FROM sondage_reponses WHERE sondage_id = ? AND user_id = ?",
        [$id, $userId]
    );
    $reponsesMap = [];
    foreach ($mesReponses as $r) {
        $reponsesMap[$r['question_id']] = $r['reponse'];
    }

    respond([
        'success' => true,
        'sondage' => $sondage,
        'questions' => $questions,
        'mes_reponses' => $reponsesMap,
    ]);
}

function submit_sondage_reponses()
{
    global $params;
    require_auth();

    $sondageId = $params['sondage_id'] ?? '';
    if (empty($sondageId)) bad_request('ID du sondage requis');

    // Check sondage is open
    $sondage = Db::fetch("SELECT * FROM sondages WHERE id = ? AND statut = 'ouvert'", [$sondageId]);
    if (!$sondage) bad_request('Ce sondage n\'est pas ouvert');

    $userId = $_SESSION['zt_user']['id'];
    $reponses = $params['reponses'] ?? [];

    if (empty($reponses)) bad_request('Aucune réponse fournie');

    // Get valid question IDs for this sondage
    $questions = Db::fetchAll(
        "SELECT id, type FROM sondage_questions WHERE sondage_id = ?",
        [$sondageId]
    );
    $validQIds = array_column($questions, 'id');

    foreach ($reponses as $questionId => $reponse) {
        if (!in_array($questionId, $validQIds)) continue;

        $reponseText = is_array($reponse) ? json_encode($reponse) : Sanitize::text($reponse);
        if ($reponseText === '' || $reponseText === null) continue;

        // Upsert (replace if already answered)
        $existing = Db::getOne(
            "SELECT id FROM sondage_reponses WHERE question_id = ? AND user_id = ?",
            [$questionId, $userId]
        );

        if ($existing) {
            Db::exec(
                "UPDATE sondage_reponses SET reponse = ? WHERE id = ?",
                [$reponseText, $existing]
            );
        } else {
            Db::exec(
                "INSERT INTO sondage_reponses (id, sondage_id, question_id, user_id, reponse)
                 VALUES (?, ?, ?, ?, ?)",
                [Uuid::v4(), $sondageId, $questionId, $userId, $reponseText]
            );
        }
    }

    respond(['success' => true, 'message' => 'Réponses enregistrées']);
}
