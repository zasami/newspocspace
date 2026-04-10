<?php
require_once __DIR__ . '/../../core/Notification.php';

/**
 * Extract clean HTML from AI response (removes markdown fences, preamble, postamble)
 */
function extractHtmlFromAI(string $text): string
{
    // Extract content between ```html ... ``` if present
    if (preg_match('/```html?\s*(.*?)```/si', $text, $m)) {
        $text = $m[1];
    }
    // Remove any remaining ``` fences
    $text = preg_replace('/```\w*\s*/i', '', $text);
    // If it contains a full HTML document, extract just the body
    if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $text, $m)) {
        $text = $m[1];
    }
    // Remove <!DOCTYPE>, <html>, <head>...<style> blocks
    $text = preg_replace('/<!(DOCTYPE|html)[^>]*>/i', '', $text);
    $text = preg_replace('/<\/?(html|head|body|meta|title)[^>]*>/i', '', $text);
    $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text);
    return trim($text);
}

/**
 * Admin PV API actions
 */

function admin_get_pv_list()
{
    global $params;
    require_responsable();

    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min((int)($params['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $offset = ($page - 1) * $limit;

    // Filters
    $filters = [];
    $bindings = [];
    
    if (!empty($params['module_id'])) {
        $filters[] = 'pv.module_id = ?';
        $bindings[] = $params['module_id'];
    }
    if (!empty($params['etage_id'])) {
        $filters[] = 'pv.etage_id = ?';
        $bindings[] = $params['etage_id'];
    }
    if (!empty($params['fonction_id'])) {
        $filters[] = 'pv.fonction_filter_id = ?';
        $bindings[] = $params['fonction_id'];
    }
    if (!empty($params['search'])) {
        $filters[] = '(pv.titre LIKE ? OR pv.description LIKE ? OR pv.contenu LIKE ?)';
        $search = '%' . $params['search'] . '%';
        $bindings[] = $search;
        $bindings[] = $search;
        $bindings[] = $search;
    }

    // Archive filter
    $archived = ($params['archived'] ?? '0') === '1';
    $baseWhere = 'pv.is_active = 1 AND pv.is_archived = ' . ($archived ? '1' : '0');
    $whereClause = empty($filters) ? "WHERE $baseWhere" : "WHERE $baseWhere AND " . implode(' AND ', $filters);

    // Count total (separate query to avoid double-binding issue)
    $total = (int)Db::getOne(
        "SELECT COUNT(*) FROM pv {$whereClause}",
        $bindings
    );

    $list = Db::fetchAll(
        "SELECT pv.*, u.prenom, u.nom, u.fonction_id,
                f.code AS fonction_code, f.nom AS fonction_nom,
                m.code AS module_code, m.nom AS module_nom,
                e.code AS etage_code, e.nom AS etage_nom
         FROM pv
         LEFT JOIN users u ON u.id = pv.created_by
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN modules m ON m.id = pv.module_id
         LEFT JOIN etages e ON e.id = pv.etage_id
         {$whereClause}
         ORDER BY pv.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($bindings, [$limit, $offset])
    );

    respond([
        'success' => true,
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit),
    ]);
}

function admin_create_pv()
{
    global $params;
    require_responsable();

    $titre = Sanitize::text($params['titre'] ?? '');
    if (empty($titre)) bad_request('Titre requis');

    $id = Uuid::v4();
    $userId = $_SESSION['ss_user']['id'];
    $description = Sanitize::text($params['description'] ?? null);
    $moduleId = !empty($params['module_id']) ? Sanitize::text($params['module_id']) : null;
    $etageId = !empty($params['etage_id']) ? Sanitize::text($params['etage_id']) : null;
    $fonctionId = !empty($params['fonction_id']) ? Sanitize::text($params['fonction_id']) : null;
    $participants = $params['participants'] ?? [];
    $allow_comments = isset($params['allow_comments']) ? (int)$params['allow_comments'] : 1;

    Db::exec(
        "INSERT INTO pv (id, titre, description, created_by, module_id, etage_id, fonction_filter_id, participants, statut, allow_comments)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', ?)",
        [
            $id,
            $titre,
            $description,
            $userId,
            $moduleId,
            $etageId,
            $fonctionId,
            json_encode($participants),
            $allow_comments
        ]
    );

    // Notify participants (each can be a string ID or an object {id, prenom, nom})
    if (!empty($participants)) {
        foreach ($participants as $p) {
            $pid = is_array($p) ? ($p['id'] ?? null) : $p;
            if ($pid && $pid !== $userId) {
                Notification::create($pid, 'pv_ajoute', 'Nouveau PV',
                    "Un nouveau PV « $titre » vous concerne.", 'pv');
            }
        }
    }

    respond([
        'success' => true,
        'id' => $id,
        'message' => 'PV créé',
    ]);
}

function admin_get_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch(
        "SELECT pv.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
                f.code AS fonction_code, f.nom AS fonction_nom,
                m.code AS module_code, m.nom AS module_nom,
                e.code AS etage_code, e.nom AS etage_nom
         FROM pv
         LEFT JOIN users u ON u.id = pv.created_by
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN modules m ON m.id = pv.module_id
         LEFT JOIN etages e ON e.id = pv.etage_id
         WHERE pv.id = ?",
        [$pvId]
    );

    if (!$pv) not_found('PV non trouvé');

    // Parse JSON fields
    $pv['participants'] = !empty($pv['participants']) ? json_decode($pv['participants'], true) : [];
    $pv['tags'] = !empty($pv['tags']) ? json_decode($pv['tags'], true) : [];

    respond([
        'success' => true,
        'pv' => $pv,
    ]);
}

function admin_update_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    $updates = [];
    $bindings = [];

    if (isset($params['titre'])) {
        $updates[] = 'titre = ?';
        $bindings[] = Sanitize::text($params['titre']);
    }
    if (isset($params['description'])) {
        $updates[] = 'description = ?';
        $bindings[] = Sanitize::text($params['description']);
    }
    if (isset($params['contenu'])) {
        $updates[] = 'contenu = ?';
        $bindings[] = $params['contenu'];
    }
    if (isset($params['transcription_brute'])) {
        $updates[] = 'transcription_brute = ?';
        $bindings[] = $params['transcription_brute'];
    }
    if (isset($params['participants'])) {
        $updates[] = 'participants = ?';
        $bindings[] = json_encode($params['participants']);
    }
    if (isset($params['tags'])) {
        $updates[] = 'tags = ?';
        $bindings[] = json_encode($params['tags']);
    }
    if (isset($params['is_public'])) {
        $updates[] = 'is_public = ?';
        $bindings[] = (int)$params['is_public'];
    }
    if (isset($params['allow_comments'])) {
        $updates[] = 'allow_comments = ?';
        $bindings[] = (int)$params['allow_comments'];
    }
    if (isset($params['statut'])) {
        $validStatuts = ['brouillon', 'enregistrement', 'en_validation', 'finalisé'];
        if (in_array($params['statut'], $validStatuts)) {
            $updates[] = 'statut = ?';
            $bindings[] = $params['statut'];
        }
    }
    if (isset($params['validation_required'])) {
        $updates[] = 'validation_required = ?';
        $bindings[] = (int)$params['validation_required'];
    }
    if (isset($params['validation_role'])) {
        $validRoles = ['responsable', 'admin', 'direction', ''];
        $role = in_array($params['validation_role'], $validRoles) ? $params['validation_role'] : null;
        $updates[] = 'validation_role = ?';
        $bindings[] = $role;
    }

    if (empty($updates)) bad_request('Aucune modification');

    $bindings[] = $pvId;
    Db::exec(
        "UPDATE pv SET " . implode(', ', $updates) . " WHERE id = ?",
        $bindings
    );

    respond([
        'success' => true,
        'message' => 'PV mis à jour',
    ]);
}

function admin_finalize_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    // Si validation requise → passer en "en_validation" et notifier le validateur
    if ($pv['validation_required'] && $pv['validation_role']) {
        Db::exec("UPDATE pv SET statut = 'en_validation' WHERE id = ?", [$pvId]);

        // Trouver les utilisateurs avec le rôle de validation
        $role = $pv['validation_role'];
        $validators = Db::fetchAll(
            "SELECT id FROM users WHERE role = ? AND is_active = 1",
            [$role]
        );
        // Si direction, inclure aussi admin
        if ($role === 'direction') {
            $admins = Db::fetchAll("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
            $validators = array_merge($validators, $admins);
        }

        $validatorIds = array_unique(array_column($validators, 'id'));
        $creator = Db::fetch("SELECT prenom, nom FROM users WHERE id = ?", [$pv['created_by']]);
        $creatorName = ($creator['prenom'] ?? '') . ' ' . ($creator['nom'] ?? '');

        if (!empty($validatorIds)) {
            Notification::createBulk(
                $validatorIds,
                'pv_validation',
                'PV à valider',
                "Le PV « {$pv['titre']} » soumis par {$creatorName} nécessite votre validation.",
                'pv-detail/' . $pvId
            );
        }

        respond([
            'success' => true,
            'message' => 'PV soumis pour validation',
            'statut' => 'en_validation',
        ]);
        return;
    }

    // Pas de validation requise → finaliser directement
    Db::exec("UPDATE pv SET statut = 'finalisé' WHERE id = ?", [$pvId]);

    respond([
        'success' => true,
        'message' => 'PV finalisé',
        'statut' => 'finalisé',
    ]);
}

function admin_validate_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ? AND statut = 'en_validation'", [$pvId]);
    if (!$pv) not_found('PV non trouvé ou pas en attente de validation');

    // Vérifier que l'utilisateur a le rôle requis
    $userRole = $_SESSION['ss_user']['role'] ?? '';
    $requiredRole = $pv['validation_role'] ?? 'responsable';
    $allowedRoles = ['admin', 'direction'];
    if ($requiredRole === 'responsable') $allowedRoles[] = 'responsable';
    if (!in_array($userRole, $allowedRoles)) {
        forbidden('Vous n\'avez pas le rôle requis pour valider ce PV');
    }

    $userId = $_SESSION['ss_user']['id'];
    Db::exec(
        "UPDATE pv SET statut = 'finalisé', validated_by = ?, validated_at = NOW() WHERE id = ?",
        [$userId, $pvId]
    );

    // Notifier le créateur
    $validator = Db::fetch("SELECT prenom, nom FROM users WHERE id = ?", [$userId]);
    $validatorName = ($validator['prenom'] ?? '') . ' ' . ($validator['nom'] ?? '');
    Notification::create(
        $pv['created_by'],
        'pv_valide',
        'PV validé',
        "Votre PV « {$pv['titre']} » a été validé par {$validatorName}.",
        'pv-detail/' . $pvId
    );

    respond([
        'success' => true,
        'message' => 'PV validé et finalisé',
        'statut' => 'finalisé',
    ]);
}

function admin_reject_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ? AND statut = 'en_validation'", [$pvId]);
    if (!$pv) not_found('PV non trouvé ou pas en attente de validation');

    $userRole = $_SESSION['ss_user']['role'] ?? '';
    $requiredRole = $pv['validation_role'] ?? 'responsable';
    $allowedRoles = ['admin', 'direction'];
    if ($requiredRole === 'responsable') $allowedRoles[] = 'responsable';
    if (!in_array($userRole, $allowedRoles)) {
        forbidden('Vous n\'avez pas le rôle requis pour rejeter ce PV');
    }

    $motif = Sanitize::text($params['motif'] ?? '');
    $userId = $_SESSION['ss_user']['id'];

    Db::exec("UPDATE pv SET statut = 'brouillon', validated_by = NULL, validated_at = NULL WHERE id = ?", [$pvId]);

    $validator = Db::fetch("SELECT prenom, nom FROM users WHERE id = ?", [$userId]);
    $validatorName = ($validator['prenom'] ?? '') . ' ' . ($validator['nom'] ?? '');
    $msg = "Votre PV « {$pv['titre']} » a été refusé par {$validatorName}.";
    if ($motif) $msg .= " Motif : $motif";

    Notification::create(
        $pv['created_by'],
        'pv_refuse',
        'PV refusé',
        $msg,
        'pv-detail/' . $pvId
    );

    respond([
        'success' => true,
        'message' => 'PV refusé et renvoyé en brouillon',
        'statut' => 'brouillon',
    ]);
}

function admin_send_pv_email()
{
    global $params;
    require_responsable();

    $pvId = $params['pv_id'] ?? '';
    $recipientIds = $params['to'] ?? [];
    $sujet = Sanitize::text($params['sujet'] ?? '');
    $contenu = $params['contenu'] ?? '';

    if (empty($pvId) || empty($recipientIds) || empty($sujet)) {
        bad_request('Destinataires et sujet requis');
    }

    $userId = $_SESSION['ss_user']['id'];
    $emailId = Uuid::v4();
    $threadId = $emailId;

    Db::exec(
        "INSERT INTO messages (id, thread_id, from_user_id, sujet, contenu, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$emailId, $threadId, $userId, $sujet, $contenu]
    );

    foreach ($recipientIds as $rid) {
        Db::exec(
            "INSERT INTO message_recipients (id, email_id, user_id, type) VALUES (?, ?, ?, 'to')",
            [Uuid::v4(), $emailId, $rid]
        );
    }

    respond([
        'success' => true,
        'message' => 'Email envoyé',
        'id' => $emailId,
    ]);
}

function admin_delete_pv()
{
    global $params;
    require_admin();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    // Soft delete: just mark as inactive
    Db::exec("UPDATE pv SET is_active = 0, updated_at = NOW() WHERE id = ?", [$pvId]);

    respond([
        'success' => true,
        'message' => 'PV archivé',
    ]);
}

function admin_restore_pv()
{
    global $params;
    require_admin();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    Db::exec("UPDATE pv SET is_active = 1, updated_at = NOW() WHERE id = ?", [$pvId]);

    respond([
        'success' => true,
        'message' => 'PV restauré',
    ]);
}

function admin_purge_pv()
{
    global $params;
    require_admin();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    // Hard delete: remove audio file and record
    if (!empty($pv['audio_path'])) {
        $audioPath = __DIR__ . '/../../storage/pv/' . basename($pv['audio_path']);
        if (file_exists($audioPath)) @unlink($audioPath);
    }

    Db::exec("DELETE FROM pv WHERE id = ?", [$pvId]);

    respond([
        'success' => true,
        'message' => 'PV supprimé définitivement',
    ]);
}

function admin_archive_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ? AND is_active = 1", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    Db::exec("UPDATE pv SET is_archived = 1, updated_at = NOW() WHERE id = ?", [$pvId]);

    respond(['success' => true, 'message' => 'PV archivé']);
}

function admin_unarchive_pv()
{
    global $params;
    require_responsable();

    $pvId = $params['id'] ?? '';
    if (empty($pvId)) bad_request('ID requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ? AND is_active = 1", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    Db::exec("UPDATE pv SET is_archived = 0, updated_at = NOW() WHERE id = ?", [$pvId]);

    respond(['success' => true, 'message' => 'PV désarchivé']);
}

function admin_upload_pv_audio()
{
    require_responsable();

    $pvId = $_POST['id'] ?? '';
    if (empty($pvId)) bad_request('ID du PV requis');

    $pv = Db::fetch("SELECT * FROM pv WHERE id = ?", [$pvId]);
    if (!$pv) not_found('PV non trouvé');

    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier audio manquant ou invalide');
    }

    $file = $_FILES['audio'];
    $allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/x-m4a', 'video/webm'];

    // Vérifier le vrai MIME type avec finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowedTypes, true)) {
        bad_request('Format audio non supporté (' . $realMime . ')');
    }

    // Déterminer l'extension d'après le vrai MIME
    $mimeToExt = [
        'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/webm' => 'webm',
        'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'video/webm' => 'webm',
    ];
    $ext = $mimeToExt[$realMime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext) || !preg_match('/^[a-zA-Z0-9]{1,5}$/', $ext)) {
        $ext = 'mp3';
    }

    $storageDir = __DIR__ . '/../../storage/pv/';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    // Supprimer l'ancien fichier si existant
    if (!empty($pv['audio_path'])) {
        $oldPath = $storageDir . basename($pv['audio_path']);
        if (file_exists($oldPath) && !unlink($oldPath)) {
            error_log('SpocSpace: impossible de supprimer ancien audio PV: ' . $oldPath);
        }
    }

    $fileName = 'pv_' . $pvId . '_' . time() . '.' . $ext;
    $targetPath = $storageDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        bad_request('Erreur lors de la sauvegarde du fichier');
    }

    $audioDbPath = 'storage/pv/' . $fileName;

    Db::exec("UPDATE pv SET audio_path = ? WHERE id = ?", [$audioDbPath, $pvId]);

    respond([
        'success' => true,
        'message' => 'Audio sauvegardé',
        'audio_path' => $audioDbPath
    ]);
}

function admin_serve_pv_audio()
{
    require_responsable();

    $pvId = $_GET['id'] ?? ($_POST['id'] ?? '');
    if (empty($pvId)) bad_request('ID du PV requis');

    $pv = Db::fetch("SELECT audio_path FROM pv WHERE id = ?", [$pvId]);
    if (!$pv || empty($pv['audio_path'])) not_found('Audio non trouvé');

    $path = __DIR__ . '/../../storage/pv/' . basename($pv['audio_path']);
    if (!file_exists($path)) not_found('Fichier audio non trouvé');

    $mime = mime_content_type($path) ?: 'audio/mpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

function admin_get_pv_refs()
{
    global $params;
    require_responsable();

    $modules = Db::fetchAll(
        "SELECT id, nom, code FROM modules ORDER BY ordre, nom"
    );

    $etages = Db::fetchAll(
        "SELECT e.id, e.nom, e.code, m.id AS module_id, m.code AS module_code FROM etages e
         JOIN modules m ON m.id = e.module_id
         ORDER BY m.ordre, e.ordre"
    );

    $fonctions = Db::fetchAll(
        "SELECT id, nom, code FROM fonctions ORDER BY ordre, nom"
    );

    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.fonction_id, u.email, u.photo,
                f.nom AS fonction_nom
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         ORDER BY u.nom, u.prenom"
    );

    respond([
        'success' => true,
        'modules' => $modules,
        'etages' => $etages,
        'fonctions' => $fonctions,
        'users' => $users,
    ]);
}

/**
 * Transcribe audio via Deepgram API
 * Used when pv_external_mode is enabled
 */
function admin_transcribe_external()
{
    global $params;
    require_responsable();

    // Load config
    $cfg = [];
    $rows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
    foreach ($rows as $r) $cfg[$r['config_key']] = $r['config_value'];

    if (($cfg['pv_external_mode'] ?? '0') !== '1') {
        bad_request('Le mode serveur externe n\'est pas activé');
    }

    $apiKey = $cfg['deepgram_api_key'] ?? '';
    if (!$apiKey) {
        bad_request('Clé API Deepgram non configurée. Allez dans Configuration IA > Transcription PV.');
    }

    // Get uploaded audio file
    if (empty($_FILES['audio'])) {
        bad_request('Aucun fichier audio reçu');
    }

    $file = $_FILES['audio'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        bad_request('Erreur upload: ' . $file['error']);
    }

    // Deepgram API
    $url = 'https://api.deepgram.com/v1/listen?language=fr&model=nova-2&smart_format=true&punctuate=true&mip_opt_out=true';
    $audioData = file_get_contents($file['tmp_name']);
    $contentType = $file['type'] ?: 'audio/webm';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $apiKey,
            'Content-Type: ' . $contentType,
        ],
        CURLOPT_POSTFIELDS => $audioData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$raw) {
        error_log("Deepgram API error: HTTP $httpCode — " . substr($raw ?: '', 0, 500));
        bad_request('Erreur API Deepgram (HTTP ' . $httpCode . ')');
    }

    $resp = json_decode($raw, true);
    $text = $resp['results']['channels'][0]['alternatives'][0]['transcript'] ?? '';

    if (empty(trim($text))) {
        respond(['success' => true, 'text' => '', 'message' => 'Aucun texte détecté dans l\'audio']);
        return;
    }

    respond([
        'success' => true,
        'text' => trim($text),
        'engine' => 'deepgram',
    ]);
}

/**
 * Structure PV text via external AI (Claude or Gemini API)
 * Used when pv_external_mode is enabled
 */
function admin_structure_pv_external()
{
    global $params;
    require_responsable();

    $rawText = trim($params['text'] ?? '');
    $prompt = trim($params['prompt'] ?? '');
    if (strlen($rawText) < 30) {
        bad_request('Pas assez de texte à structurer');
    }
    if (empty($prompt)) {
        bad_request('Prompt de structuration manquant');
    }

    // Load config
    $cfg = [];
    $rows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
    foreach ($rows as $r) $cfg[$r['config_key']] = $r['config_value'];

    if (($cfg['pv_external_mode'] ?? '0') !== '1') {
        bad_request('Le mode serveur externe n\'est pas activé');
    }

    $aiProvider = $cfg['ai_provider'] ?? 'gemini';
    $aiApiKey = ($aiProvider === 'gemini') ? ($cfg['gemini_api_key'] ?? '') : ($cfg['anthropic_api_key'] ?? '');
    $aiModel = ($aiProvider === 'gemini') ? ($cfg['gemini_model'] ?? 'gemini-2.5-flash') : ($cfg['anthropic_model'] ?? 'claude-sonnet-4-5-20250929');

    if (empty($aiApiKey)) {
        bad_request("Clé API " . ucfirst($aiProvider) . " non configurée. Allez dans Configuration IA > Clés API.");
    }

    $html = null;
    $iaTokensIn = 0;
    $iaTokensOut = 0;
    $iaCostUsd = 0;

    if ($aiProvider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 8192],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $raw) {
            $resp = json_decode($raw, true);
            $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $usage = $resp['usageMetadata'] ?? [];
            $iaTokensIn = (int) ($usage['promptTokenCount'] ?? 0);
            $iaTokensOut = (int) ($usage['candidatesTokenCount'] ?? 0);

            if (str_contains($aiModel, 'flash')) { $priceIn = 0.075 / 1000000; $priceOut = 0.30 / 1000000; }
            elseif (str_contains($aiModel, 'pro')) { $priceIn = 1.25 / 1000000; $priceOut = 5.00 / 1000000; }
            else { $priceIn = 0; $priceOut = 0; }
            $iaCostUsd = $iaTokensIn * $priceIn + $iaTokensOut * $priceOut;

            $html = extractHtmlFromAI($text);
        } else {
            error_log("Gemini PV structuration error: HTTP $httpCode — " . substr($raw ?: '', 0, 500));
            bad_request('Erreur API Gemini (HTTP ' . $httpCode . ')');
        }

    } elseif ($aiProvider === 'claude') {
        $url = "https://api.anthropic.com/v1/messages";
        $payload = json_encode([
            'model' => $aiModel,
            'max_tokens' => 8192,
            'temperature' => 0.3,
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
            CURLOPT_TIMEOUT => 90,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $raw) {
            $resp = json_decode($raw, true);
            $text = $resp['content'][0]['text'] ?? '';
            $usage = $resp['usage'] ?? [];
            $iaTokensIn = (int) ($usage['input_tokens'] ?? 0);
            $iaTokensOut = (int) ($usage['output_tokens'] ?? 0);

            if (str_contains($aiModel, 'haiku')) { $priceIn = 0.80 / 1000000; $priceOut = 4.00 / 1000000; }
            elseif (str_contains($aiModel, 'sonnet')) { $priceIn = 3.00 / 1000000; $priceOut = 15.00 / 1000000; }
            elseif (str_contains($aiModel, 'opus')) { $priceIn = 15.00 / 1000000; $priceOut = 75.00 / 1000000; }
            else { $priceIn = 0; $priceOut = 0; }
            $iaCostUsd = $iaTokensIn * $priceIn + $iaTokensOut * $priceOut;

            $html = extractHtmlFromAI($text);
        } else {
            error_log("Claude PV structuration error: HTTP $httpCode — " . substr($raw ?: '', 0, 500));
            bad_request('Erreur API Claude (HTTP ' . $httpCode . ')');
        }
    } else {
        bad_request('Fournisseur IA non configuré');
    }

    // Log usage
    try {
        Db::exec(
            "INSERT INTO ia_usage_log (id, admin_id, mois_annee, provider, model, tokens_in, tokens_out, cost_usd, nb_assignations, nb_conflicts, duration_ms, created_at)
             VALUES (?, ?, 'pv', ?, ?, ?, ?, ?, 0, 0, 0, NOW())",
            [Uuid::v4(), $_SESSION['ss_user']['id'], $aiProvider, $aiModel, $iaTokensIn, $iaTokensOut, $iaCostUsd]
        );
    } catch (\Exception $e) {
        // Non-critical
    }

    respond([
        'success' => true,
        'html' => $html ?: '',
        'provider' => $aiProvider,
        'model' => $aiModel,
        'tokens_in' => $iaTokensIn,
        'tokens_out' => $iaTokensOut,
        'cost_usd' => round($iaCostUsd, 6),
    ]);
}
