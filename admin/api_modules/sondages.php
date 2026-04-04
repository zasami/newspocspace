<?php
require_once __DIR__ . '/../../core/Notification.php';

/**
 * Admin Sondages (Surveys) API actions
 */

function admin_get_sondages()
{
    global $params;
    require_responsable();

    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min((int)($params['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $offset = ($page - 1) * $limit;

    $filters = [];
    $bindings = [];

    if (!empty($params['statut'])) {
        $filters[] = 's.statut = ?';
        $bindings[] = $params['statut'];
    }
    if (!empty($params['search'])) {
        $filters[] = '(s.titre LIKE ? OR s.description LIKE ?)';
        $search = '%' . $params['search'] . '%';
        $bindings[] = $search;
        $bindings[] = $search;
    }

    $where = empty($filters) ? '' : 'WHERE ' . implode(' AND ', $filters);

    $total = (int)Db::getOne(
        "SELECT COUNT(*) FROM sondages s $where",
        $bindings
    );

    $list = Db::fetchAll(
        "SELECT s.*, u.prenom, u.nom,
                (SELECT COUNT(*) FROM sondage_questions WHERE sondage_id = s.id) AS nb_questions,
                (SELECT COUNT(DISTINCT user_id) FROM sondage_reponses WHERE sondage_id = s.id) AS nb_repondants
         FROM sondages s
         LEFT JOIN users u ON u.id = s.created_by
         $where
         ORDER BY s.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($bindings, [$limit, $offset])
    );

    respond([
        'success' => true,
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

function admin_get_sondage()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (empty($id)) bad_request('ID requis');

    $sondage = Db::fetch(
        "SELECT s.*, u.prenom, u.nom
         FROM sondages s
         LEFT JOIN users u ON u.id = s.created_by
         WHERE s.id = ?",
        [$id]
    );
    if (!$sondage) not_found('Sondage introuvable');

    $questions = Db::fetchAll(
        "SELECT * FROM sondage_questions WHERE sondage_id = ? ORDER BY ordre ASC",
        [$id]
    );
    foreach ($questions as &$q) {
        if (!empty($q['options'])) {
            $q['options'] = json_decode($q['options'], true);
        }
    }

    // Get results per question
    $results = [];
    foreach ($questions as $q) {
        $reponses = Db::fetchAll(
            "SELECT sr.*, u.prenom, u.nom
             FROM sondage_reponses sr
             LEFT JOIN users u ON u.id = sr.user_id
             WHERE sr.question_id = ?
             ORDER BY sr.created_at ASC",
            [$q['id']]
        );
        $results[$q['id']] = $reponses;
    }

    $nbRepondants = (int)Db::getOne(
        "SELECT COUNT(DISTINCT user_id) FROM sondage_reponses WHERE sondage_id = ?",
        [$id]
    );

    respond([
        'success' => true,
        'sondage' => $sondage,
        'questions' => $questions,
        'results' => $results,
        'nb_repondants' => $nbRepondants,
    ]);
}

function admin_create_sondage()
{
    global $params;
    require_responsable();

    $titre = Sanitize::text($params['titre'] ?? '');
    if (empty($titre)) bad_request('Titre requis');

    $id = Uuid::v4();
    $userId = $_SESSION['ss_user']['id'];
    $description = Sanitize::text($params['description'] ?? null);
    $isAnonymous = isset($params['is_anonymous']) ? (int)(bool)$params['is_anonymous'] : 0;

    Db::exec(
        "INSERT INTO sondages (id, titre, description, is_anonymous, created_by, statut)
         VALUES (?, ?, ?, ?, ?, 'brouillon')",
        [$id, $titre, $description, $isAnonymous, $userId]
    );

    // Add questions if provided
    $questions = $params['questions'] ?? [];
    foreach ($questions as $i => $q) {
        $qText = Sanitize::text($q['question'] ?? '');
        if (empty($qText)) continue;

        $qId = Uuid::v4();
        $type = in_array($q['type'] ?? '', ['choix_unique', 'choix_multiple', 'texte_libre'])
            ? $q['type'] : 'choix_unique';
        $options = ($type !== 'texte_libre' && !empty($q['options']))
            ? json_encode(array_values(array_filter(array_map('trim', $q['options']))))
            : null;

        Db::exec(
            "INSERT INTO sondage_questions (id, sondage_id, question, type, options, ordre)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$qId, $id, $qText, $type, $options, $i]
        );
    }

    respond(['success' => true, 'id' => $id]);
}

function admin_update_sondage()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (empty($id)) bad_request('ID requis');

    $sondage = Db::fetch("SELECT * FROM sondages WHERE id = ?", [$id]);
    if (!$sondage) not_found('Sondage introuvable');

    $titre = Sanitize::text($params['titre'] ?? $sondage['titre']);
    $description = Sanitize::text($params['description'] ?? $sondage['description']);
    $isAnonymous = isset($params['is_anonymous']) ? (int)(bool)$params['is_anonymous'] : (int)$sondage['is_anonymous'];

    Db::exec(
        "UPDATE sondages SET titre = ?, description = ?, is_anonymous = ? WHERE id = ?",
        [$titre, $description, $isAnonymous, $id]
    );

    // Replace questions if provided
    if (isset($params['questions'])) {
        // Delete old questions + their responses
        Db::exec("DELETE FROM sondage_reponses WHERE sondage_id = ?", [$id]);
        Db::exec("DELETE FROM sondage_questions WHERE sondage_id = ?", [$id]);

        foreach ($params['questions'] as $i => $q) {
            $qText = Sanitize::text($q['question'] ?? '');
            if (empty($qText)) continue;

            $qId = Uuid::v4();
            $type = in_array($q['type'] ?? '', ['choix_unique', 'choix_multiple', 'texte_libre'])
                ? $q['type'] : 'choix_unique';
            $options = ($type !== 'texte_libre' && !empty($q['options']))
                ? json_encode(array_values(array_filter(array_map('trim', $q['options']))))
                : null;

            Db::exec(
                "INSERT INTO sondage_questions (id, sondage_id, question, type, options, ordre)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$qId, $id, $qText, $type, $options, $i]
            );
        }
    }

    respond(['success' => true]);
}

function admin_toggle_sondage()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    if (empty($id)) bad_request('ID requis');
    if (!in_array($statut, ['brouillon', 'ouvert', 'ferme'])) bad_request('Statut invalide');

    $sondage = Db::fetch("SELECT * FROM sondages WHERE id = ?", [$id]);
    if (!$sondage) not_found('Sondage introuvable');

    // Must have at least 1 question to open
    if ($statut === 'ouvert') {
        $nbQ = (int)Db::getOne("SELECT COUNT(*) FROM sondage_questions WHERE sondage_id = ?", [$id]);
        if ($nbQ === 0) bad_request('Ajoutez au moins une question avant d\'ouvrir le sondage');
    }

    $closedAt = ($statut === 'ferme') ? date('Y-m-d H:i:s') : null;
    Db::exec(
        "UPDATE sondages SET statut = ?, closed_at = ? WHERE id = ?",
        [$statut, $closedAt, $id]
    );

    // Notify all active users when survey is opened
    if ($statut === 'ouvert') {
        $activeUsers = Db::fetchAll("SELECT id FROM users WHERE is_active = 1");
        foreach ($activeUsers as $u) {
            Notification::create($u['id'], 'sondage_nouveau', 'Nouveau sondage',
                "Le sondage « {$sondage['titre']} » est ouvert. Participez !", 'sondages');
        }
    }

    respond(['success' => true]);
}

function admin_delete_sondage()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (empty($id)) bad_request('ID requis');

    Db::exec("DELETE FROM sondage_reponses WHERE sondage_id = ?", [$id]);
    Db::exec("DELETE FROM sondage_questions WHERE sondage_id = ?", [$id]);
    Db::exec("DELETE FROM sondages WHERE id = ?", [$id]);

    respond(['success' => true]);
}

/**
 * Generate survey questions using AI
 */
function admin_generate_sondage_questions()
{
    global $params;
    require_responsable();

    $theme = Sanitize::text($params['theme'] ?? '', 500);
    $nbQuestions = min(20, max(1, intval($params['nb_questions'] ?? 5)));
    $langue = in_array($params['langue'] ?? '', ['fr', 'de', 'en', 'it']) ? $params['langue'] : 'fr';
    $anonyme = !empty($params['anonyme']);
    $generateIntro = !empty($params['generate_intro']);

    if (empty($theme)) bad_request('Thème requis');

    // Load AI config
    $cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
    $cfg = [];
    foreach ($cfgRows as $r) $cfg[$r['config_key']] = $r['config_value'];

    $aiProvider = $cfg['ai_provider'] ?? 'gemini';
    $aiApiKey = ($aiProvider === 'gemini') ? ($cfg['gemini_api_key'] ?? '') : ($cfg['anthropic_api_key'] ?? '');
    $aiModel = ($aiProvider === 'gemini') ? ($cfg['gemini_model'] ?? 'gemini-2.5-flash') : ($cfg['anthropic_model'] ?? 'claude-haiku-4-5-20251001');

    if (empty($aiApiKey)) bad_request("Clé API non configurée pour $aiProvider. Allez dans Config IA > Clés API.");

    $langLabel = ['fr' => 'français', 'de' => 'allemand', 'en' => 'anglais', 'it' => 'italien'][$langue] ?? 'français';

    $introInstruction = $generateIntro
        ? "\n- Ajoute aussi un champ \"introduction\" : un texte d'introduction bienveillant et professionnel (2-3 phrases) " .
          "qui explique l'objectif du sondage aux collaborateurs, les encourage à participer et précise si c'est anonyme ou non.\n"
        : "";

    $introFormat = $generateIntro
        ? "{\n  \"introduction\": \"Texte d'intro...\",\n  \"questions\": [\n"
        : "[\n";
    $introFormatEnd = $generateIntro ? "  ]\n}" : "]\n";

    $prompt = "Tu es un expert RH dans un EMS (établissement médico-social) en Suisse. " .
        "Génère exactement $nbQuestions questions de sondage en $langLabel sur le thème: \"$theme\".\n\n" .
        "Règles:\n" .
        "- Questions pertinentes pour le personnel soignant d'un EMS\n" .
        "- Mix de types: choix_unique (échelle 1-5 ou oui/non), choix_multiple, texte_libre\n" .
        "- Les questions doivent être neutres et professionnelles\n" .
        ($anonyme ? "- Le sondage est anonyme, les questions peuvent être plus personnelles\n" : "") .
        $introInstruction .
        "- Retourne UNIQUEMENT un JSON valide, sans texte autour\n\n" .
        "Format JSON attendu:\n" .
        $introFormat .
        "    {\"question\": \"...\", \"type\": \"choix_unique\", \"options\": [\"Tout à fait\", \"Plutôt oui\", \"Neutre\", \"Plutôt non\", \"Pas du tout\"]},\n" .
        "    {\"question\": \"...\", \"type\": \"choix_multiple\", \"options\": [\"Option A\", \"Option B\", \"Option C\"]},\n" .
        "    {\"question\": \"...\", \"type\": \"texte_libre\", \"options\": []}\n" .
        $introFormatEnd;

    $questions = null;
    $iaError = null;

    if ($aiProvider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json'],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $resp = json_decode($raw, true);
            $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $questions = json_decode($text, true);
        } else {
            $iaError = "Gemini API error (HTTP $httpCode)";
        }
    } else {
        // Anthropic
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = json_encode([
            'model' => $aiModel,
            'max_tokens' => 4096,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $aiApiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $resp = json_decode($raw, true);
            $text = $resp['content'][0]['text'] ?? '';
            // Extract JSON from response — try object first, then array
            $questions = json_decode($text, true);
            if (!$questions && preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                $questions = json_decode($matches[0], true);
            }
            if (!$questions && preg_match('/\[[\s\S]*\]/', $text, $matches)) {
                $questions = json_decode($matches[0], true);
            }
        } else {
            $iaError = "Anthropic API error (HTTP $httpCode)";
        }
    }

    if ($iaError) bad_request($iaError);

    // Parse response — may be {introduction, questions} or just [questions]
    $introduction = null;
    if ($generateIntro && is_array($questions) && isset($questions['questions'])) {
        $introduction = $questions['introduction'] ?? null;
        $questions = $questions['questions'];
    }

    if (!is_array($questions) || empty($questions)) bad_request('L\'IA n\'a pas pu générer de questions valides. Réessayez.');

    // Validate structure
    $validated = [];
    foreach ($questions as $q) {
        if (empty($q['question'])) continue;
        $type = in_array($q['type'] ?? '', ['choix_unique', 'choix_multiple', 'texte_libre']) ? $q['type'] : 'choix_unique';
        $options = ($type !== 'texte_libre' && !empty($q['options']) && is_array($q['options'])) ? $q['options'] : [];
        $validated[] = [
            'question' => $q['question'],
            'type' => $type,
            'options' => $options,
        ];
    }

    // Log AI usage
    try {
        Db::exec(
            "INSERT INTO ia_usage_log (id, planning_id, mois_annee, provider, model, tokens_in, tokens_out, admin_id)
             VALUES (?, '', 'sondage', ?, ?, ?, ?, ?)",
            [Uuid::v4(), $aiProvider, $aiModel,
             strlen($prompt), strlen($raw ?? ''), $_SESSION['ss_user']['id']]
        );
    } catch (\Exception $e) {
        // Non-critical
    }

    $response = ['success' => true, 'questions' => $validated];
    if ($introduction) $response['introduction'] = $introduction;
    respond($response);
}
