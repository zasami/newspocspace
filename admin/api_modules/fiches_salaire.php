<?php
/**
 * Admin — Fiches de salaire (payslip management)
 */
require_once __DIR__ . '/../../core/Notification.php';

/**
 * List payslips with filters
 */
function admin_get_fiches_salaire()
{
    global $params;
    require_responsable();

    $annee = intval($params['annee'] ?? date('Y'));
    $mois = isset($params['mois']) && $params['mois'] !== '' ? intval($params['mois']) : null;
    $userId = $params['user_id'] ?? '';
    $moduleId = $params['module_id'] ?? '';

    $where = ['1=1'];
    $binds = [];

    if ($annee) {
        $where[] = 'fs.annee = ?';
        $binds[] = $annee;
    }
    if ($mois !== null) {
        $where[] = 'fs.mois = ?';
        $binds[] = $mois;
    }
    if ($userId) {
        $where[] = 'fs.user_id = ?';
        $binds[] = $userId;
    }
    if ($moduleId) {
        $where[] = 'um.module_id = ?';
        $binds[] = $moduleId;
    }

    $sql = "SELECT fs.id, fs.user_id, fs.annee, fs.mois, fs.original_name, fs.size, fs.created_at,
                   u.prenom, u.nom, u.employee_id,
                   f.code AS fonction_code,
                   m.nom AS module_nom, m.code AS module_code,
                   up.prenom AS uploaded_by_prenom, up.nom AS uploaded_by_nom
            FROM fiches_salaire fs
            JOIN users u ON u.id = fs.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
            LEFT JOIN modules m ON m.id = um.module_id
            LEFT JOIN users up ON up.id = fs.uploaded_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fs.annee DESC, fs.mois DESC, u.nom, u.prenom";

    $fiches = Db::fetchAll($sql, $binds);

    // Get users for the upload form
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.employee_id, f.code AS fonction_code
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1
         ORDER BY u.nom, u.prenom"
    );

    $modules = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY ordre");

    respond([
        'success' => true,
        'fiches' => $fiches,
        'users' => $users,
        'modules' => $modules,
    ]);
}

/**
 * Upload a payslip PDF for a user
 */
function admin_upload_fiche_salaire()
{
    $admin = require_responsable();

    $userId = $_POST['user_id'] ?? '';
    $annee = intval($_POST['annee'] ?? 0);
    $mois = intval($_POST['mois'] ?? 0);

    if (!$userId) bad_request('Collaborateur requis');
    if ($annee < 2000 || $annee > 2100) bad_request('Année invalide');
    if ($mois < 1 || $mois > 12) bad_request('Mois invalide');

    // Verify user exists
    $user = Db::fetch("SELECT id, prenom, nom FROM users WHERE id = ?", [$userId]);
    if (!$user) bad_request('Collaborateur introuvable');

    // Check for existing payslip
    $existing = Db::fetch(
        "SELECT id FROM fiches_salaire WHERE user_id = ? AND annee = ? AND mois = ?",
        [$userId, $annee, $mois]
    );
    if ($existing) bad_request('Une fiche existe déjà pour cette période. Supprimez-la d\'abord.');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier PDF manquant');
    }

    $file = $_FILES['file'];
    if ($file['type'] !== 'application/pdf') bad_request('Seuls les fichiers PDF sont acceptés');
    if ($file['size'] > 10 * 1024 * 1024) bad_request('Fichier trop volumineux (max 10 Mo)');

    $storageDir = __DIR__ . '/../../storage/fiches_salaire/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $filename = bin2hex(random_bytes(16)) . '.pdf';

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde');
    }

    $id = Uuid::v4();
    $originalName = mb_substr(basename($file['name']), 0, 255);

    Db::exec(
        "INSERT INTO fiches_salaire (id, user_id, annee, mois, filename, original_name, size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $userId, $annee, $mois, $filename, $originalName, $file['size'], $admin['id']]
    );

    // Notify user
    $moisNoms = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $moisNom = $moisNoms[$mois] ?? $mois;
    Notification::create($userId, 'fiche_salaire', 'Fiche de salaire',
        "Votre fiche de salaire de $moisNom $annee est disponible.", 'documents');

    respond(['success' => true, 'message' => 'Fiche de salaire téléversée', 'id' => $id]);
}

/**
 * Bulk upload payslips — auto-detect user from filename pattern
 * Expects filenames like: NOM_Prenom_2026_03.pdf or employee_id_2026_03.pdf
 */
function admin_bulk_upload_fiches()
{
    $admin = require_responsable();

    $annee = intval($_POST['annee'] ?? 0);
    $mois = intval($_POST['mois'] ?? 0);

    if ($annee < 2000 || $annee > 2100) bad_request('Année invalide');
    if ($mois < 1 || $mois > 12) bad_request('Mois invalide');

    if (empty($_FILES['files'])) bad_request('Aucun fichier');

    $storageDir = __DIR__ . '/../../storage/fiches_salaire/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    // Load all active users for matching
    $users = Db::fetchAll("SELECT id, prenom, nom, employee_id FROM users WHERE is_active = 1");

    $uploaded = 0;
    $skipped = [];
    $adminId = $admin['id'];
    $moisNoms = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $moisNom = $moisNoms[$mois] ?? $mois;

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['type'][$i] !== 'application/pdf') {
            $skipped[] = $files['name'][$i] . ' (pas un PDF)';
            continue;
        }

        $originalName = $files['name'][$i];
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        // Try to match user by filename
        $matchedUser = null;

        // Pattern 1: employee_id (e.g., "EMP001_2026_03.pdf")
        foreach ($users as $u) {
            if (!empty($u['employee_id']) && stripos($baseName, $u['employee_id']) !== false) {
                $matchedUser = $u;
                break;
            }
        }

        // Pattern 2: NOM_Prenom or Prenom_NOM
        if (!$matchedUser) {
            $baseNorm = strtolower(str_replace(['-', '_', '.', ' '], '', $baseName));
            foreach ($users as $u) {
                $nomPrenom = strtolower(str_replace(['-', ' '], '', $u['nom'] . $u['prenom']));
                $prenomNom = strtolower(str_replace(['-', ' '], '', $u['prenom'] . $u['nom']));
                if (strpos($baseNorm, $nomPrenom) !== false || strpos($baseNorm, $prenomNom) !== false) {
                    $matchedUser = $u;
                    break;
                }
            }
        }

        if (!$matchedUser) {
            $skipped[] = $originalName . ' (collaborateur non identifié)';
            continue;
        }

        // Check for existing
        $existing = Db::fetch(
            "SELECT id FROM fiches_salaire WHERE user_id = ? AND annee = ? AND mois = ?",
            [$matchedUser['id'], $annee, $mois]
        );
        if ($existing) {
            $skipped[] = $originalName . ' (fiche déjà existante pour ' . $matchedUser['prenom'] . ' ' . $matchedUser['nom'] . ')';
            continue;
        }

        $filename = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($files['tmp_name'][$i], $storageDir . $filename)) {
            $skipped[] = $originalName . ' (erreur sauvegarde)';
            continue;
        }

        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO fiches_salaire (id, user_id, annee, mois, filename, original_name, size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $matchedUser['id'], $annee, $mois, $filename, mb_substr(basename($originalName), 0, 255), $files['size'][$i], $adminId]
        );

        Notification::create($matchedUser['id'], 'fiche_salaire', 'Fiche de salaire',
            "Votre fiche de salaire de $moisNom $annee est disponible.", 'documents');

        $uploaded++;
    }

    respond([
        'success' => true,
        'message' => "$uploaded fiche(s) téléversée(s)",
        'uploaded' => $uploaded,
        'skipped' => $skipped,
    ]);
}

/**
 * Delete a payslip
 */
function admin_delete_fiche_salaire()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fiche = Db::fetch("SELECT id, filename FROM fiches_salaire WHERE id = ?", [$id]);
    if (!$fiche) not_found('Fiche introuvable');

    $filePath = __DIR__ . '/../../storage/fiches_salaire/' . $fiche['filename'];
    if (file_exists($filePath)) unlink($filePath);

    Db::exec("DELETE FROM fiches_salaire WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Fiche supprimée']);
}

/**
 * Serve/download a payslip PDF (admin)
 */
function admin_serve_fiche_salaire()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fiche = Db::fetch(
        "SELECT fs.filename, fs.original_name, u.prenom, u.nom, fs.annee, fs.mois
         FROM fiches_salaire fs
         JOIN users u ON u.id = fs.user_id
         WHERE fs.id = ?",
        [$id]
    );
    if (!$fiche) not_found('Fiche introuvable');

    $filePath = __DIR__ . '/../../storage/fiches_salaire/' . $fiche['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../../storage/fiches_salaire/');
    if ($realPath === false || strpos($realPath, $storageDir) !== 0) {
        forbidden('Accès interdit');
    }

    $downloadName = "Fiche_{$fiche['nom']}_{$fiche['prenom']}_{$fiche['annee']}_" . str_pad($fiche['mois'], 2, '0', STR_PAD_LEFT) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    exit;
}
