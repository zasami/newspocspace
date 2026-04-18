<?php

/**
 * Get all EMS config values
 */
function admin_get_config()
{
    require_responsable();

    $rows = Db::fetchAll("SELECT config_key, config_value FROM ems_config ORDER BY config_key");
    $config = [];
    foreach ($rows as $r) {
        $config[$r['config_key']] = $r['config_value'];
    }

    // Also load modules with their responsables
    $modules = Db::fetchAll(
        "SELECT m.id, m.code, m.nom, m.ordre,
                u.id AS responsable_id,
                CONCAT(u.prenom, ' ', u.nom) AS responsable_nom
         FROM modules m
         LEFT JOIN user_modules um ON um.module_id = m.id AND um.is_principal = 1
         LEFT JOIN users u ON u.id = um.user_id AND u.role IN ('responsable','admin','direction')
         ORDER BY m.ordre"
    );

    // Geographic data
    $pays = Db::fetchAll("SELECT code, nom FROM geo_pays ORDER BY sort_order");
    $regions = Db::fetchAll("SELECT id, pays_code, code, nom FROM geo_regions ORDER BY pays_code, sort_order");

    respond([
        'success' => true,
        'config' => $config,
        'modules' => $modules,
        'geo_pays' => $pays,
        'geo_regions' => $regions,
    ]);
}

/**
 * Save EMS config (batch update)
 */
function admin_save_config()
{
    global $params;
    require_admin();

    $values = $params['values'] ?? [];
    if (!is_array($values) || empty($values)) {
        bad_request('Aucune valeur à enregistrer');
    }

    // Allowed keys (prevent injection of arbitrary config)
    $allowedKeys = [
        'ems_nom', 'ems_adresse', 'ems_npa', 'ems_ville', 'ems_canton', 'ems_pays',
        'ems_telephone', 'ems_fax', 'ems_email', 'ems_site_web', 'ems_logo_url',
        'ems_type', 'ems_nb_lits', 'ems_nb_etages', 'ems_nb_modules',
        'directeur_nom', 'directeur_prenom', 'directeur_email', 'directeur_telephone',
        'infirmiere_chef_nom', 'infirmiere_chef_prenom', 'infirmiere_chef_email', 'infirmiere_chef_telephone',
        'infirmiere_chef_user_id',
        'responsable_rh_nom', 'responsable_rh_prenom', 'responsable_rh_email', 'responsable_rh_telephone',
        'planning_heures_semaine', 'planning_repos_minimum',
        'planning_jours_consecutifs_max', 'planning_desirs_max_mois',
        'planning_desirs_ouverture_jour', 'planning_desirs_fermeture_jour',
        // IA config keys
        'ia_jours_ouvres', 'ia_heures_jour', 'ia_consecutif_max', 'ia_consecutif_max_besoins',
        'ia_direction_weekend_off', 'ia_bonus_principal', 'ia_random_max',
        'ia_weekend_skip_prob', 'ia_seuil_soir', 'ia_seuil_nuit', 'ia_admin_shift_code',
        'ia_consecutif_max_as', 'ia_consecutif_max_nuit',
        // Législation suisse (LTr) — plafonds légaux
        'ia_legal_max_hours_week', 'ia_legal_max_consec_days', 'ia_max_over_taux_hours_week',
        'ia_legal_max_hours_day', 'ia_legal_repos_quotidien_h',
        // IA API keys
        'ai_provider', 'gemini_api_key', 'gemini_model',
        'anthropic_api_key', 'anthropic_model',
        // Ollama local model
        'ollama_model',
        // Transcription engine (vosk / whisper)
        'transcription_engine',
        // External mode (cloud transcription + cloud structuration)
        'pv_external_mode', 'pv_transcription_cloud', 'pv_structuration_cloud', 'deepgram_api_key',
        // PV structuration options (JSON)
        'pv_structure_options',
        // Planning display config
        'planning_tabs_config',
        // Planning algorithm config (universal EMS)
        'planning_coverage_start', 'planning_coverage_end',
        'planning_as_per_etage', 'planning_shift_pairing',
        'planning_night_threshold', 'planning_evening_threshold',
        'planning_fullday_min_hours', 'planning_exclude_shifts',
        // Feature toggles
        'feature_desirs', 'feature_multi_modules', 'feature_civilistes',
        'feature_absences', 'feature_changements', 'feature_sondages',
        'feature_pv', 'feature_emails', 'feature_documents',
        'feature_votes', 'feature_covoiturage', 'feature_fiches_salaire',
        'feature_mur_social', 'feature_annonces',
        // CSS mode
        'css_mode',
        // Pinned section (website)
        'pinned_visible', 'pinned_title', 'pinned_text', 'pinned_signature', 'pinned_image',
        // Sécurité / Antivirus
        'virustotal_api_key', 'virustotal_enabled', 'security_local_scan',
    ];

    $userId = $_SESSION['ss_user']['id'];
    $stmt = Db::connect()->prepare(
        "INSERT INTO ems_config (config_key, config_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)"
    );

    $saved = 0;
    $longKeys = ['planning_tabs_config', 'pv_structure_options', 'pinned_text'];
    foreach ($values as $key => $val) {
        if (!in_array($key, $allowedKeys)) continue;
        $maxLen = in_array($key, $longKeys) ? 5000 : 500;
        $val = Sanitize::text((string) $val, $maxLen);
        $stmt->execute([$key, $val, $userId]);
        $saved++;
    }

    respond(['success' => true, 'message' => "$saved paramètres enregistrés", 'saved' => $saved]);
}

/**
 * Upload EMS logo → convert to WebP
 */
function admin_upload_logo()
{
    require_admin();

    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur d\'upload');
    }

    $file = $_FILES['logo'];

    // Hardened validation — SVG rejected (XSS vector)
    require_once __DIR__ . '/../../core/FileSecurity.php';
    $err = FileSecurity::validateUpload($file, 'Logo', FileSecurity::ALLOW_IMAGE, 5 * 1024 * 1024);
    if ($err) bad_request($err);

    $mime = mime_content_type($file['tmp_name']);

    $uploadDir = __DIR__ . '/../../storage/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'ems_logo_' . time() . '.webp';
    $destPath = $uploadDir . $filename;

    // Always convert to WebP (no SVG accepted)
    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/gif' => imagecreatefromgif($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default => null,
    };
    if (!$img) bad_request('Impossible de lire l\'image');
    imagesavealpha($img, true);
    imagewebp($img, $destPath, 85);
    // Also save PNG version for email clients (WebP not supported in Outlook, older Apple Mail, etc.)
    imagepng($img, str_replace('.webp', '.png', $destPath), 6);
    imagedestroy($img);

    // Save URL in config
    $logoUrl = '/spocspace/storage/logos/' . $filename;
    $userId = $_SESSION['ss_user']['id'];
    Db::exec(
        "INSERT INTO ems_config (config_key, config_value, updated_by) VALUES ('ems_logo_url', ?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)",
        [$logoUrl, $userId]
    );

    respond(['success' => true, 'logo_url' => $logoUrl, 'message' => 'Logo mis à jour']);
}

/**
 * Upload pinned section image → convert to WebP
 */
function admin_upload_pinned_image()
{
    require_admin();

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Aucun fichier ou erreur d\'upload');
    }

    $file = $_FILES['image'];
    if ($file['size'] > 5 * 1024 * 1024) bad_request('Fichier trop volumineux (max 5 Mo)');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        bad_request('Format non supporté (JPG, PNG, GIF, WebP)');
    }

    $uploadDir = __DIR__ . '/../../storage/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/gif' => imagecreatefromgif($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default => null,
    };
    if (!$img) bad_request('Impossible de lire l\'image');

    imagesavealpha($img, true);
    $filename = 'pinned_' . time() . '.webp';
    $destPath = $uploadDir . $filename;
    imagewebp($img, $destPath, 85);
    imagedestroy($img);

    $imageUrl = '/spocspace/storage/logos/' . $filename;
    $userId = $_SESSION['ss_user']['id'];
    Db::exec(
        "INSERT INTO ems_config (config_key, config_value, updated_by) VALUES ('pinned_image', ?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)",
        [$imageUrl, $userId]
    );

    respond(['success' => true, 'image_url' => $imageUrl, 'message' => 'Image mise à jour']);
}

/**
 * Assign a responsable to a module
 */
function admin_assign_module_responsable()
{
    global $params;
    require_admin();

    $moduleId = $params['module_id'] ?? '';
    $userId = $params['user_id'] ?? '';
    if (!$moduleId) bad_request('module_id requis');

    // Verify module exists
    $module = Db::fetch("SELECT id FROM modules WHERE id = ?", [$moduleId]);
    if (!$module) not_found('Module non trouvé');

    if ($userId) {
        // Verify user exists and has a management role
        $user = Db::fetch("SELECT id, role FROM users WHERE id = ? AND is_active = 1", [$userId]);
        if (!$user) not_found('Utilisateur non trouvé');

        // Check if user already has this module
        $existing = Db::fetch(
            "SELECT user_id FROM user_modules WHERE user_id = ? AND module_id = ?",
            [$userId, $moduleId]
        );

        if ($existing) {
            // Update to principal
            Db::exec(
                "UPDATE user_modules SET is_principal = 1 WHERE user_id = ? AND module_id = ?",
                [$userId, $moduleId]
            );
        } else {
            // Create assignment
            Db::exec(
                "INSERT INTO user_modules (user_id, module_id, is_principal) VALUES (?, ?, 1)",
                [$userId, $moduleId]
            );
        }
    }

    respond(['success' => true, 'message' => 'Responsable assigné']);
}

/**
 * Auto-generate modules + etages structure
 * Input: nb_etages, nb_modules
 * Logic: creates N modules, creates N etages, distributes etages across modules evenly
 */
function admin_generate_structure()
{
    global $params;
    require_admin();

    $nbEtages  = max(1, min(50, (int) ($params['nb_etages'] ?? 0)));
    $nbModules = max(1, min(50, (int) ($params['nb_modules'] ?? 0)));

    if ($nbEtages < 1 || $nbModules < 1) {
        bad_request('Nombre d\'étages et de modules requis (min 1)');
    }

    $pdo = Db::connect();
    $pdo->beginTransaction();

    try {
        // Delete existing modules + etages (CASCADE deletes etages and groupes)
        $pdo->exec("DELETE FROM etages");
        $pdo->exec("DELETE FROM modules");

        // Create modules
        $moduleIds = [];
        for ($m = 1; $m <= $nbModules; $m++) {
            $id = Uuid::v4();
            $code = 'M' . $m;
            $nom = 'Module ' . $m;
            Db::exec(
                "INSERT INTO modules (id, code, nom, ordre) VALUES (?, ?, ?, ?)",
                [$id, $code, $nom, $m]
            );
            $moduleIds[] = $id;
        }

        // Create etages and distribute sequentially across modules
        // Ex: 8 étages / 4 modules → M1:[1,2], M2:[3,4], M3:[5,6], M4:[7,8]
        $etagesPerModule = [];
        $perModule = (int) floor($nbEtages / $nbModules);
        $extra = $nbEtages % $nbModules; // first modules get 1 extra
        $e = 1;
        for ($m = 0; $m < $nbModules; $m++) {
            $count = $perModule + ($m < $extra ? 1 : 0);
            for ($i = 0; $i < $count; $i++) {
                $etagesPerModule[$m][] = $e++;
            }
        }

        foreach ($etagesPerModule as $modIdx => $etages) {
            $moduleId = $moduleIds[$modIdx];
            foreach ($etages as $ordre => $etageNum) {
                $etageId = Uuid::v4();
                $code = 'E' . $etageNum;
                $nom = 'Étage ' . $etageNum;
                Db::exec(
                    "INSERT INTO etages (id, module_id, code, nom, ordre) VALUES (?, ?, ?, ?, ?)",
                    [$etageId, $moduleId, $code, $nom, $etageNum]
                );
            }

            // Update module name with its etages
            $etageNums = implode(', ', $etages);
            $modNom = count($etages) === 1
                ? 'Module ' . ($modIdx + 1) . ' — Étage ' . $etages[0]
                : 'Module ' . ($modIdx + 1) . ' — Étages ' . $etageNums;
            Db::exec("UPDATE modules SET nom = ? WHERE id = ?", [$modNom, $moduleId]);
        }

        // Save counts in config
        $userId = $_SESSION['ss_user']['id'];
        $stmtCfg = $pdo->prepare(
            "INSERT INTO ems_config (config_key, config_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)"
        );
        $stmtCfg->execute(['ems_nb_etages', (string) $nbEtages, $userId]);
        $stmtCfg->execute(['ems_nb_modules', (string) $nbModules, $userId]);

        $pdo->commit();

        // Return new structure
        $modules = Db::fetchAll("SELECT * FROM modules ORDER BY ordre");
        foreach ($modules as &$mod) {
            $mod['etages'] = Db::fetchAll(
                "SELECT id, code, nom, ordre FROM etages WHERE module_id = ? ORDER BY ordre",
                [$mod['id']]
            );
        }
        unset($mod);

        respond([
            'success' => true,
            'message' => "$nbModules modules et $nbEtages étages créés",
            'modules' => $modules,
        ]);

    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('admin_generate_structure error: ' . $e->getMessage());
        error_response('Erreur lors de la génération', 500);
    }
}

/**
 * Update a single module's config (nom, code, etages assignment)
 */
function admin_update_module_config()
{
    global $params;
    require_admin();

    $moduleId = $params['module_id'] ?? '';
    if (!$moduleId) bad_request('module_id requis');

    $module = Db::fetch("SELECT * FROM modules WHERE id = ?", [$moduleId]);
    if (!$module) not_found('Module non trouvé');

    // Update nom/code if provided
    $nom = Sanitize::text($params['nom'] ?? $module['nom'], 100);
    $code = Sanitize::text($params['code'] ?? $module['code'], 20);
    Db::exec("UPDATE modules SET nom = ?, code = ? WHERE id = ?", [$nom, strtoupper($code), $moduleId]);

    // Reassign etages if provided
    if (isset($params['etage_ids']) && is_array($params['etage_ids'])) {
        $newEtageIds = $params['etage_ids'];

        // Get current etages of this module
        $currentEtages = Db::fetchAll("SELECT id FROM etages WHERE module_id = ?", [$moduleId]);
        $currentIds = array_column($currentEtages, 'id');

        // Etages to remove from this module: move them to a "spare" module or leave them
        // Etages to add: steal from their current module
        foreach ($newEtageIds as $etageId) {
            if (!in_array($etageId, $currentIds)) {
                // Move this etage to our module
                Db::exec("UPDATE etages SET module_id = ? WHERE id = ?", [$moduleId, $etageId]);
            }
        }

        // Etages that were in this module but are no longer: we need another module for them
        // Find or create a "Non assigné" module
        $removedIds = array_diff($currentIds, $newEtageIds);
        if (!empty($removedIds)) {
            // Find first other module to park orphan etages
            $otherModule = Db::fetch(
                "SELECT id FROM modules WHERE id != ? ORDER BY ordre LIMIT 1",
                [$moduleId]
            );
            if ($otherModule) {
                foreach ($removedIds as $rid) {
                    Db::exec("UPDATE etages SET module_id = ? WHERE id = ?", [$otherModule['id'], $rid]);
                }
            }
        }
    }

    // Assign responsable if provided
    if (isset($params['responsable_id'])) {
        $respId = $params['responsable_id'];
        if ($respId) {
            // Remove old principal for this module (among responsables)
            Db::exec(
                "DELETE um FROM user_modules um
                 JOIN users u ON u.id = um.user_id
                 WHERE um.module_id = ? AND um.is_principal = 1 AND u.role IN ('responsable','admin','direction')",
                [$moduleId]
            );
            // Check if user already has this module
            $existing = Db::fetch(
                "SELECT user_id FROM user_modules WHERE user_id = ? AND module_id = ?",
                [$respId, $moduleId]
            );
            if ($existing) {
                Db::exec(
                    "UPDATE user_modules SET is_principal = 1 WHERE user_id = ? AND module_id = ?",
                    [$respId, $moduleId]
                );
            } else {
                Db::exec(
                    "INSERT INTO user_modules (user_id, module_id, is_principal) VALUES (?, ?, 1)",
                    [$respId, $moduleId]
                );
            }
        }
    }

    respond(['success' => true, 'message' => 'Module mis à jour']);
}

/**
 * Get IA usage stats for expenses dashboard
 */
function admin_get_ia_usage()
{
    require_admin();

    $year = date('Y');

    // Monthly breakdown for current year
    $monthly = Db::fetchAll(
        "SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS mois,
            COUNT(*) AS nb_generations,
            SUM(nb_assignations) AS total_assignations,
            SUM(cost_usd) AS total_cost,
            SUM(tokens_in) AS total_tokens_in,
            SUM(tokens_out) AS total_tokens_out,
            AVG(duration_ms) AS avg_duration_ms
         FROM ia_usage_log
         WHERE YEAR(created_at) = ?
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY mois",
        [$year]
    );

    // Annual totals
    $annual = Db::fetch(
        "SELECT
            COUNT(*) AS nb_generations,
            SUM(nb_assignations) AS total_assignations,
            SUM(cost_usd) AS total_cost,
            SUM(tokens_in) AS total_tokens_in,
            SUM(tokens_out) AS total_tokens_out,
            AVG(duration_ms) AS avg_duration_ms
         FROM ia_usage_log
         WHERE YEAR(created_at) = ?",
        [$year]
    );

    // Current month totals
    $currentMonth = date('Y-m');
    $monthStats = Db::fetch(
        "SELECT
            COUNT(*) AS nb_generations,
            SUM(nb_assignations) AS total_assignations,
            SUM(cost_usd) AS total_cost,
            AVG(duration_ms) AS avg_duration_ms
         FROM ia_usage_log
         WHERE DATE_FORMAT(created_at, '%Y-%m') = ?",
        [$currentMonth]
    );

    // Last 10 generations
    $recent = Db::fetchAll(
        "SELECT l.*, u.nom AS admin_nom, u.prenom AS admin_prenom
         FROM ia_usage_log l
         LEFT JOIN users u ON u.id = l.admin_id
         ORDER BY l.created_at DESC
         LIMIT 10"
    );

    respond([
        'success' => true,
        'year' => $year,
        'monthly' => $monthly,
        'annual' => $annual,
        'month_stats' => $monthStats,
        'recent' => $recent,
    ]);
}

/**
 * Get all IA human rules
 */
function admin_get_ia_rules()
{
    require_responsable();

    $rules = Db::fetchAll(
        "SELECT id, titre, description, importance, actif, rule_type, rule_params, target_mode, target_fonction_code, created_at, updated_at
         FROM ia_human_rules
         ORDER BY CASE WHEN importance = 'important' THEN 1 WHEN importance = 'moyen' THEN 2 ELSE 3 END, created_at DESC"
    );

    // Load targeted users for all rules
    $ruleIds = array_column($rules, 'id');
    $ruleUsersMap = [];
    if (!empty($ruleIds)) {
        $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
        $ruleUsers = Db::fetchAll(
            "SELECT ru.rule_id, ru.user_id, u.prenom, u.nom
             FROM ia_rule_users ru
             JOIN users u ON u.id = ru.user_id
             WHERE ru.rule_id IN ($placeholders)",
            $ruleIds
        );
        foreach ($ruleUsers as $ru) {
            $ruleUsersMap[$ru['rule_id']][] = [
                'id' => $ru['user_id'],
                'name' => $ru['prenom'] . ' ' . $ru['nom'],
            ];
        }
    }

    foreach ($rules as &$r) {
        $r['rule_params'] = $r['rule_params'] ? json_decode($r['rule_params'], true) : null;
        $r['targeted_users'] = $ruleUsersMap[$r['id']] ?? [];
    }
    unset($r);

    respond([
        'success' => true,
        'rules' => $rules,
    ]);
}

/**
 * Create a new IA human rule
 */
function admin_create_ia_rule()
{
    global $params;
    require_admin();

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    $description = Sanitize::text($params['description'] ?? '', 5000);
    $importance = $params['importance'] ?? 'moyen';

    // Validate importance
    $allowed_importance = ['important', 'moyen', 'supprime'];
    if (!in_array($importance, $allowed_importance)) {
        $importance = 'moyen';
    }

    if (empty($titre)) {
        bad_request('Titre requis');
    }

    // Structured rule fields
    $ruleType = $params['rule_type'] ?? null;
    $allowedTypes = ['user_schedule', 'shift_only', 'shift_exclude', 'days_only', 'module_only', 'module_exclude', 'no_weekend', 'max_days_week'];
    if ($ruleType && !in_array($ruleType, $allowedTypes)) $ruleType = null;

    $ruleParams = null;
    if ($ruleType && isset($params['rule_params'])) {
        if (is_string($params['rule_params'])) {
            $ruleParams = $params['rule_params'];
        } elseif (is_array($params['rule_params'])) {
            $ruleParams = json_encode($params['rule_params']);
        }
    }

    $targetMode = $params['target_mode'] ?? 'all';
    if (!in_array($targetMode, ['all', 'users', 'fonction', 'module'])) $targetMode = 'all';

    $targetFonctionCode = null;
    if ($targetMode === 'fonction') {
        $targetFonctionCode = Sanitize::text($params['target_fonction_code'] ?? '', 10);
    }

    // For module targeting, store module IDs in rule_params
    if ($targetMode === 'module') {
        $targetModuleIds = $params['target_module_ids'] ?? [];
        if (!is_array($targetModuleIds)) $targetModuleIds = [];
        $decoded = json_decode($ruleParams, true) ?: [];
        $decoded['target_module_ids'] = $targetModuleIds;
        $ruleParams = json_encode($decoded);
    }

    $userIds = $params['user_ids'] ?? [];
    if (!is_array($userIds)) $userIds = [];

    // Free-text rules require description
    if (!$ruleType && empty($description)) {
        bad_request('Description requise pour les règles texte libre');
    }

    $id = Uuid::v4();
    $userId = $_SESSION['ss_user']['id'];

    Db::exec(
        "INSERT INTO ia_human_rules (id, titre, description, importance, actif, created_by, rule_type, rule_params, target_mode, target_fonction_code)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)",
        [$id, $titre, $description, $importance, $userId, $ruleType, $ruleParams, $targetMode, $targetFonctionCode]
    );

    // Insert user targets
    if ($targetMode === 'users' && !empty($userIds)) {
        foreach ($userIds as $uid) {
            Db::exec("INSERT INTO ia_rule_users (rule_id, user_id) VALUES (?, ?)", [$id, $uid]);
        }
    }

    respond([
        'success' => true,
        'message' => 'Règle créée',
        'rule_id' => $id,
    ]);
}

/**
 * Update an IA human rule
 */
function admin_update_ia_rule()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $rule = Db::fetch("SELECT * FROM ia_human_rules WHERE id = ?", [$id]);
    if (!$rule) not_found('Règle non trouvée');

    $titre = Sanitize::text($params['titre'] ?? $rule['titre'], 255);
    $description = Sanitize::text($params['description'] ?? $rule['description'], 5000);
    $importance = $params['importance'] ?? $rule['importance'];
    $actif = isset($params['actif']) ? (int) $params['actif'] : $rule['actif'];

    // Validate importance
    $allowed_importance = ['important', 'moyen', 'supprime'];
    if (!in_array($importance, $allowed_importance)) {
        $importance = $rule['importance'];
    }

    if (empty($titre)) {
        bad_request('Titre requis');
    }

    // Structured rule fields
    $ruleType = array_key_exists('rule_type', $params) ? $params['rule_type'] : $rule['rule_type'];
    $allowedTypes = ['user_schedule', 'shift_only', 'shift_exclude', 'days_only', 'module_only', 'module_exclude', 'no_weekend', 'max_days_week', null];
    if (!in_array($ruleType, $allowedTypes)) $ruleType = $rule['rule_type'];

    $ruleParams = $rule['rule_params'];
    if (isset($params['rule_params'])) {
        if (is_string($params['rule_params'])) {
            $ruleParams = $params['rule_params'];
        } elseif (is_array($params['rule_params'])) {
            $ruleParams = json_encode($params['rule_params']);
        }
    }

    $targetMode = $params['target_mode'] ?? $rule['target_mode'];
    if (!in_array($targetMode, ['all', 'users', 'fonction', 'module'])) $targetMode = $rule['target_mode'];

    $targetFonctionCode = $rule['target_fonction_code'];
    if ($targetMode === 'fonction' && isset($params['target_fonction_code'])) {
        $targetFonctionCode = Sanitize::text($params['target_fonction_code'], 10);
    } elseif ($targetMode !== 'fonction') {
        $targetFonctionCode = null;
    }

    // For module targeting, store module IDs in rule_params
    if ($targetMode === 'module' && isset($params['target_module_ids'])) {
        $decoded = json_decode($ruleParams, true) ?: [];
        $decoded['target_module_ids'] = is_array($params['target_module_ids']) ? $params['target_module_ids'] : [];
        $ruleParams = json_encode($decoded);
    }

    Db::exec(
        "UPDATE ia_human_rules SET titre = ?, description = ?, importance = ?, actif = ?, rule_type = ?, rule_params = ?, target_mode = ?, target_fonction_code = ? WHERE id = ?",
        [$titre, $description, $importance, $actif, $ruleType, $ruleParams, $targetMode, $targetFonctionCode, $id]
    );

    // Update user targets
    if (isset($params['user_ids'])) {
        Db::exec("DELETE FROM ia_rule_users WHERE rule_id = ?", [$id]);
        $userIds = is_array($params['user_ids']) ? $params['user_ids'] : [];
        if ($targetMode === 'users' && !empty($userIds)) {
            foreach ($userIds as $uid) {
                Db::exec("INSERT INTO ia_rule_users (rule_id, user_id) VALUES (?, ?)", [$id, $uid]);
            }
        }
    }

    respond(['success' => true, 'message' => 'Règle mise à jour']);
}

/**
 * Delete an IA human rule
 */
function admin_delete_ia_rule()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $rule = Db::fetch("SELECT * FROM ia_human_rules WHERE id = ?", [$id]);
    if (!$rule) not_found('Règle non trouvée');

    Db::exec("DELETE FROM ia_rule_users WHERE rule_id = ?", [$id]);
    Db::exec("DELETE FROM ia_human_rules WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Règle supprimée']);
}

/**
 * Toggle IA rule active status
 */
function admin_toggle_ia_rule()
{
    global $params;
    require_admin();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $rule = Db::fetch("SELECT actif FROM ia_human_rules WHERE id = ?", [$id]);
    if (!$rule) not_found('Règle non trouvée');

    $newStatus = $rule['actif'] ? 0 : 1;
    Db::exec("UPDATE ia_human_rules SET actif = ? WHERE id = ?", [$newStatus, $id]);

    respond(['success' => true, 'message' => 'Statut mis à jour', 'actif' => $newStatus]);
}

/**
 * Test VirusTotal API key (lightweight call: hash lookup on EICAR test hash).
 */
function admin_test_virustotal()
{
    require_admin();

    $key = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'virustotal_api_key'");
    if (!$key) {
        respond(['success' => false, 'message' => 'Aucune clé enregistrée.']);
    }

    // EICAR standard test file hash — always recognized as malware signature.
    $eicarSha256 = '275a021bbfb6489e54d471899f7db9d1663fc695ec2fe2a2c4538aabf651fd0f';
    $url = 'https://www.virustotal.com/api/v3/files/' . $eicarSha256;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['x-apikey: ' . $key, 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        respond(['success' => false, 'message' => 'Erreur réseau : ' . $err]);
    }
    if ($code === 401 || $code === 403) {
        respond(['success' => false, 'message' => 'Clé invalide (HTTP ' . $code . ').']);
    }
    if ($code === 429) {
        respond(['success' => false, 'message' => 'Quota dépassé (HTTP 429).']);
    }
    if ($code !== 200) {
        respond(['success' => false, 'message' => 'Réponse inattendue (HTTP ' . $code . ').']);
    }

    $json = json_decode($resp, true);
    $stats = $json['data']['attributes']['last_analysis_stats'] ?? null;
    $malicious = $stats['malicious'] ?? 0;
    respond([
        'success' => true,
        'message' => 'Clé valide',
        'quota'   => 'OK',
        'eicar_detected_by' => $malicious,
    ]);
}

