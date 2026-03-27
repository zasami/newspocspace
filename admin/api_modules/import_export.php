<?php
/**
 * Admin — Import/Export de données
 * Supports: CSV, Excel-compatible CSV, Polypoint format
 */

/**
 * Export planning data as CSV
 */
function admin_export_planning()
{
    global $params;
    require_responsable();

    $mois = $params['mois'] ?? date('Y-m');
    $format = $params['format'] ?? 'csv'; // csv, polypoint

    $planning = Db::fetch("SELECT * FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) bad_request('Aucun planning pour ce mois');

    $assignations = Db::fetchAll(
        "SELECT pa.date_jour, pa.statut,
                u.employee_id, u.nom, u.prenom,
                f.code AS fonction_code,
                ht.code AS horaire_code, ht.heure_debut, ht.heure_fin, ht.duree_effective,
                m.code AS module_code, m.nom AS module_nom
         FROM planning_assignations pa
         JOIN users u ON u.id = pa.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         LEFT JOIN modules m ON m.id = pa.module_id
         WHERE pa.planning_id = ?
         ORDER BY pa.date_jour, u.nom, u.prenom",
        [$planning['id']]
    );

    if ($format === 'polypoint') {
        $rows = exportPolypoint($assignations, $mois);
    } else {
        $rows = exportCsv($assignations);
    }

    respond(['success' => true, 'rows' => $rows, 'filename' => "planning_{$mois}.csv"]);
}

/**
 * Export users list as CSV
 */
function admin_export_users()
{
    require_responsable();

    $users = Db::fetchAll(
        "SELECT u.employee_id, u.nom, u.prenom, u.email, u.role, u.taux, u.type_contrat,
                u.date_entree, u.is_active,
                f.code AS fonction_code, f.nom AS fonction_nom,
                GROUP_CONCAT(m.code ORDER BY um.is_principal DESC SEPARATOR ', ') AS modules
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id
         LEFT JOIN modules m ON m.id = um.module_id
         GROUP BY u.id
         ORDER BY u.nom, u.prenom"
    );

    $header = ['ID', 'Nom', 'Prénom', 'Email', 'Rôle', 'Taux %', 'Contrat', 'Date entrée', 'Actif', 'Fonction', 'Modules'];
    $rows = [$header];
    foreach ($users as $u) {
        $rows[] = [
            $u['employee_id'] ?? '',
            $u['nom'],
            $u['prenom'],
            $u['email'],
            $u['role'],
            $u['taux'] ?? '',
            $u['type_contrat'] ?? '',
            $u['date_entree'] ?? '',
            $u['is_active'] ? 'Oui' : 'Non',
            $u['fonction_code'] ?? '',
            $u['modules'] ?? '',
        ];
    }

    respond(['success' => true, 'rows' => $rows, 'filename' => 'collaborateurs.csv']);
}

/**
 * Export absences/vacances as CSV
 */
function admin_export_absences()
{
    global $params;
    require_responsable();

    $annee = intval($params['annee'] ?? date('Y'));
    $type = $params['type'] ?? ''; // vacances, maladie, accident, etc.

    $where = ["YEAR(a.date_debut) = ? OR YEAR(a.date_fin) = ?"];
    $binds = [$annee, $annee];

    if ($type) {
        $where[] = "a.type = ?";
        $binds[] = $type;
    }

    $absences = Db::fetchAll(
        "SELECT a.type, a.date_debut, a.date_fin, a.statut, a.motif,
                u.employee_id, u.nom, u.prenom,
                f.code AS fonction_code,
                m.nom AS module_nom,
                v.prenom AS valide_prenom, v.nom AS valide_nom
         FROM absences a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules m ON m.id = um.module_id
         LEFT JOIN users v ON v.id = a.valide_par
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.date_debut DESC",
        $binds
    );

    $header = ['ID Employé', 'Nom', 'Prénom', 'Fonction', 'Module', 'Type', 'Du', 'Au', 'Statut', 'Motif', 'Validé par'];
    $rows = [$header];
    foreach ($absences as $a) {
        $rows[] = [
            $a['employee_id'] ?? '',
            $a['nom'],
            $a['prenom'],
            $a['fonction_code'] ?? '',
            $a['module_nom'] ?? '',
            $a['type'],
            $a['date_debut'],
            $a['date_fin'],
            $a['statut'],
            $a['motif'] ?? '',
            $a['valide_prenom'] ? $a['valide_prenom'] . ' ' . $a['valide_nom'] : '',
        ];
    }

    respond(['success' => true, 'rows' => $rows, 'filename' => "absences_{$annee}.csv"]);
}

/**
 * Import users from CSV
 */
function admin_import_users()
{
    require_admin();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier CSV manquant');
    }

    $file = $_FILES['file'];
    $mime = $file['type'];
    if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])) {
        bad_request('Seuls les fichiers CSV sont acceptés');
    }

    $content = file_get_contents($file['tmp_name']);
    $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
    if (count($lines) < 2) bad_request('Fichier vide ou sans données');

    // Detect separator
    $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';

    $header = str_getcsv(array_shift($lines), $sep);
    $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

    // Required columns
    $reqCols = ['nom', 'prenom', 'email'];
    foreach ($reqCols as $col) {
        if (!isset($headerMap[$col])) bad_request("Colonne requise manquante: $col");
    }

    $created = 0;
    $updated = 0;
    $errors = [];

    // Load existing fonctions
    $fonctions = [];
    foreach (Db::fetchAll("SELECT id, code FROM fonctions") as $f) {
        $fonctions[strtolower($f['code'])] = $f['id'];
    }

    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $row = str_getcsv($line, $sep);
        $data = [];
        foreach ($headerMap as $col => $idx) {
            $data[$col] = trim($row[$idx] ?? '');
        }

        $email = Sanitize::email($data['email'] ?? '');
        $nom = Sanitize::text($data['nom'] ?? '', 100);
        $prenom = Sanitize::text($data['prenom'] ?? '', 100);

        if (!$email || !$nom || !$prenom) {
            $errors[] = "Ligne " . ($i + 2) . ": données incomplètes";
            continue;
        }

        $existing = Db::fetch("SELECT id FROM users WHERE email = ?", [$email]);

        $fonctionId = null;
        if (!empty($data['fonction'])) {
            $fonctionId = $fonctions[strtolower($data['fonction'])] ?? null;
        }

        $taux = !empty($data['taux']) ? min(100, max(0, intval($data['taux']))) : null;
        $role = !empty($data['role']) && in_array($data['role'], ['collaborateur', 'responsable', 'admin', 'direction'])
            ? $data['role'] : 'collaborateur';

        if ($existing) {
            // Update existing
            Db::exec(
                "UPDATE users SET nom = ?, prenom = ?, fonction_id = COALESCE(?, fonction_id),
                        taux = COALESCE(?, taux), role = ?, employee_id = COALESCE(?, employee_id)
                 WHERE id = ?",
                [$nom, $prenom, $fonctionId, $taux, $role, $data['id'] ?? null, $existing['id']]
            );
            $updated++;
        } else {
            // Create new
            $id = Uuid::v4();
            $password = password_hash('Zerdatime2026!', PASSWORD_DEFAULT);
            Db::exec(
                "INSERT INTO users (id, nom, prenom, email, password, role, fonction_id, taux, employee_id, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$id, $nom, $prenom, $email, $password, $role, $fonctionId, $taux, $data['id'] ?? null]
            );
            $created++;
        }
    }

    respond([
        'success' => true,
        'message' => "$created créé(s), $updated mis à jour",
        'created' => $created,
        'updated' => $updated,
        'errors' => $errors,
    ]);
}

/**
 * Import planning from Polypoint CSV format
 */
function admin_import_polypoint()
{
    global $params;
    require_admin();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier CSV manquant');
    }

    $mois = $params['mois'] ?? $_POST['mois'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) bad_request('Mois requis (YYYY-MM)');

    $content = file_get_contents($_FILES['file']['tmp_name']);
    $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
    if (count($lines) < 2) bad_request('Fichier vide');

    $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
    $header = str_getcsv(array_shift($lines), $sep);
    $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

    // Ensure planning exists
    $planning = Db::fetch("SELECT id FROM plannings WHERE mois_annee = ?", [$mois]);
    if (!$planning) {
        $planningId = Uuid::v4();
        Db::exec("INSERT INTO plannings (id, mois_annee, statut, created_by) VALUES (?, ?, 'brouillon', ?)",
            [$planningId, $mois, $_SESSION['zt_user']['id']]);
    } else {
        $planningId = $planning['id'];
    }

    // Load reference data
    $usersByEmail = [];
    $usersByEmpId = [];
    foreach (Db::fetchAll("SELECT id, email, employee_id FROM users WHERE is_active = 1") as $u) {
        $usersByEmail[strtolower($u['email'])] = $u['id'];
        if ($u['employee_id']) $usersByEmpId[strtolower($u['employee_id'])] = $u['id'];
    }

    $horairesByCode = [];
    foreach (Db::fetchAll("SELECT id, code FROM horaires_types WHERE is_active = 1") as $h) {
        $horairesByCode[strtolower($h['code'])] = $h['id'];
    }

    $modulesByCode = [];
    foreach (Db::fetchAll("SELECT id, code FROM modules") as $m) {
        $modulesByCode[strtolower($m['code'])] = $m['id'];
    }

    $imported = 0;
    $skipped = [];

    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $row = str_getcsv($line, $sep);
        $data = [];
        foreach ($headerMap as $col => $idx) {
            $data[$col] = trim($row[$idx] ?? '');
        }

        // Match user
        $userId = null;
        if (!empty($data['email'])) $userId = $usersByEmail[strtolower($data['email'])] ?? null;
        if (!$userId && !empty($data['id'])) $userId = $usersByEmpId[strtolower($data['id'])] ?? null;
        if (!$userId && !empty($data['employee_id'])) $userId = $usersByEmpId[strtolower($data['employee_id'])] ?? null;

        if (!$userId) {
            $skipped[] = "Ligne " . ($i + 2) . ": collaborateur non trouvé";
            continue;
        }

        $date = $data['date'] ?? $data['date_jour'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $skipped[] = "Ligne " . ($i + 2) . ": date invalide ($date)";
            continue;
        }

        $horaireCode = strtolower($data['horaire'] ?? $data['horaire_code'] ?? $data['code'] ?? '');
        $horaireId = $horairesByCode[$horaireCode] ?? null;

        $moduleCode = strtolower($data['module'] ?? $data['module_code'] ?? '');
        $moduleId = $modulesByCode[$moduleCode] ?? null;

        $statut = $data['statut'] ?? 'present';
        if (!in_array($statut, ['present', 'absent', 'repos', 'entraide', 'formation'])) {
            $statut = 'present';
        }

        // Upsert assignation
        $existing = Db::fetch(
            "SELECT id FROM planning_assignations WHERE planning_id = ? AND user_id = ? AND date_jour = ?",
            [$planningId, $userId, $date]
        );

        if ($existing) {
            Db::exec(
                "UPDATE planning_assignations SET horaire_type_id = ?, module_id = ?, statut = ?, updated_at = NOW() WHERE id = ?",
                [$horaireId, $moduleId, $statut, $existing['id']]
            );
        } else {
            $id = Uuid::v4();
            Db::exec(
                "INSERT INTO planning_assignations (id, planning_id, user_id, date_jour, horaire_type_id, module_id, statut)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$id, $planningId, $userId, $date, $horaireId, $moduleId, $statut]
            );
        }
        $imported++;
    }

    respond([
        'success' => true,
        'message' => "$imported assignation(s) importée(s)",
        'imported' => $imported,
        'skipped' => $skipped,
    ]);
}

/* ── Helpers ── */

function exportCsv($assignations)
{
    $header = ['Date', 'ID Employé', 'Nom', 'Prénom', 'Fonction', 'Horaire', 'Début', 'Fin', 'Durée', 'Module', 'Statut'];
    $rows = [$header];
    foreach ($assignations as $a) {
        $rows[] = [
            $a['date_jour'],
            $a['employee_id'] ?? '',
            $a['nom'],
            $a['prenom'],
            $a['fonction_code'] ?? '',
            $a['horaire_code'] ?? '',
            $a['heure_debut'] ?? '',
            $a['heure_fin'] ?? '',
            $a['duree_effective'] ?? '',
            $a['module_code'] ?? '',
            $a['statut'],
        ];
    }
    return $rows;
}

function exportPolypoint($assignations, $mois)
{
    // Polypoint format: employee_id;date;horaire_code;module_code
    $header = ['employee_id', 'date', 'horaire', 'module'];
    $rows = [$header];
    foreach ($assignations as $a) {
        $rows[] = [
            $a['employee_id'] ?? '',
            $a['date_jour'],
            $a['horaire_code'] ?? '',
            $a['module_code'] ?? '',
        ];
    }
    return $rows;
}
