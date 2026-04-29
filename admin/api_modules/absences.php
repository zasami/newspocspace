<?php
require_once __DIR__ . '/../../core/Notification.php';

function admin_get_absences()
{
    require_responsable();
    global $params;
    $statut = $params['statut'] ?? '';
    $type = $params['type'] ?? '';

    $sql = "SELECT a.*, u.prenom, u.nom, u.employee_id, u.photo, f.code AS fonction_code,
                   m.nom AS module_nom,
                   ur.prenom AS rempl_prenom, ur.nom AS rempl_nom
            FROM absences a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN fonctions f ON f.id = u.fonction_id
            LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
            LEFT JOIN modules m ON m.id = um.module_id
            LEFT JOIN users ur ON ur.id = a.remplacement_user_id
            WHERE 1=1";
    $p = [];

    if ($statut) { $sql .= " AND a.statut = ?"; $p[] = $statut; }
    if ($type) { $sql .= " AND a.type = ?"; $p[] = $type; }

    $sql .= " ORDER BY a.created_at DESC";

    respond(['success' => true, 'absences' => Db::fetchAll($sql, $p)]);
}

function admin_validate_absence()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';

    if (!$id || !in_array($statut, ['valide', 'refuse'])) {
        bad_request('ID et statut requis');
    }

    $absence = Db::fetch("SELECT * FROM absences WHERE id = ?", [$id]);
    if (!$absence) bad_request('Absence introuvable');

    Db::exec(
        "UPDATE absences SET statut = ?, valide_par = ?, valide_at = NOW() WHERE id = ?",
        [$statut, $_SESSION['ss_user']['id'], $id]
    );

    // Notify user
    $type = $statut === 'valide' ? 'absence_valide' : 'absence_refuse';
    $title = $statut === 'valide' ? 'Absence validée' : 'Absence refusée';
    $label = $statut === 'valide' ? 'validée' : 'refusée';
    $msg = "Votre absence du {$absence['date_debut']} au {$absence['date_fin']} a été $label.";
    Notification::create($absence['user_id'], $type, $title, $msg, 'absences');

    respond(['success' => true, 'message' => 'Absence ' . ($statut === 'valide' ? 'validée' : 'refusée')]);
}

function admin_set_remplacement()
{
    global $params;
    require_responsable();

    $id = $params['id'] ?? '';
    $type = $params['remplacement_type'] ?? '';
    $remplacementUserId = $params['remplacement_user_id'] ?? null;

    if (!$id || !in_array($type, ['collegue', 'interim', 'entraide', 'vacant'])) {
        bad_request('ID et type de remplacement requis');
    }

    Db::exec(
        "UPDATE absences SET remplacement_type = ?, remplacement_user_id = ?,
                interim_requis = ?, entraide_notifie = ?
         WHERE id = ?",
        [
            $type,
            $type === 'collegue' ? $remplacementUserId : null,
            $type === 'interim' ? 1 : 0,
            $type === 'entraide' ? 1 : 0,
            $id
        ]
    );

    respond(['success' => true, 'message' => 'Remplacement configuré']);
}

function admin_upload_justificatif()
{
    global $params;
    require_responsable();

    $absenceId = $params['absence_id'] ?? '';
    if (!$absenceId) bad_request('absence_id requis');

    $absence = Db::fetch("SELECT id FROM absences WHERE id = ?", [$absenceId]);
    if (!$absence) not_found('Absence introuvable');

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant ou erreur d\'upload');
    }

    $file = $_FILES['file'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed)) bad_request('Format non autorisé (JPG, PNG, WebP, PDF)');
    if ($file['size'] > 10 * 1024 * 1024) bad_request('Fichier trop volumineux (max 10 Mo)');

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        default => 'bin'
    };

    $uploadDir = __DIR__ . '/../../storage/justificatifs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = $absenceId . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        bad_request('Erreur lors de l\'enregistrement du fichier');
    }

    $path = '/newspocspace/storage/justificatifs/' . $filename;
    $originalName = basename($file['name']);

    Db::exec(
        "UPDATE absences SET justificatif_path = ?, justificatif_name = ?, justifie = 1 WHERE id = ?",
        [$path, $originalName, $absenceId]
    );

    respond(['success' => true, 'path' => $path, 'name' => $originalName, 'message' => 'Justificatif uploadé']);
}

function admin_delete_justificatif()
{
    global $params;
    require_responsable();

    $absenceId = $params['absence_id'] ?? '';
    if (!$absenceId) bad_request('absence_id requis');

    $absence = Db::fetch("SELECT justificatif_path FROM absences WHERE id = ?", [$absenceId]);
    if (!$absence) not_found('Absence introuvable');

    if ($absence['justificatif_path']) {
        $filePath = __DIR__ . '/../../' . ltrim($absence['justificatif_path'], '/newspocspace/');
        if (file_exists($filePath)) @unlink($filePath);
    }

    Db::exec("UPDATE absences SET justificatif_path = NULL, justificatif_name = NULL WHERE id = ?", [$absenceId]);
    respond(['success' => true, 'message' => 'Justificatif supprimé']);
}
