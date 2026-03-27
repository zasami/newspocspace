<?php
/**
 * Absences API actions
 */

function get_mes_absences()
{
    global $params;
    $user = require_auth();

    $absences = Db::fetchAll(
        "SELECT a.*, u2.prenom AS valide_par_prenom, u2.nom AS valide_par_nom,
                ur.prenom AS remplacement_prenom, ur.nom AS remplacement_nom
         FROM absences a
         LEFT JOIN users u2 ON u2.id = a.valide_par
         LEFT JOIN users ur ON ur.id = a.remplacement_user_id
         WHERE a.user_id = ?
         ORDER BY a.date_debut DESC",
        [$user['id']]
    );

    respond(['success' => true, 'absences' => $absences]);
}

function submit_absence()
{
    global $params;
    $user = require_auth();

    $dateDebut = Sanitize::date($params['date_debut'] ?? '');
    $dateFin = Sanitize::date($params['date_fin'] ?? '');
    $type = $params['type'] ?? '';
    $motif = Sanitize::text($params['motif'] ?? '', 500);
    $commentaire = Sanitize::text($params['commentaire'] ?? '', 500);

    if (!$dateDebut || !$dateFin) {
        bad_request('Dates de début et fin requises');
    }
    if ($dateFin < $dateDebut) {
        bad_request('La date de fin doit être après la date de début');
    }
    if (!in_array($type, ['vacances', 'maladie', 'accident', 'conge_special', 'formation', 'autre'])) {
        bad_request('Type d\'absence invalide');
    }

    // Check for overlap
    $overlap = Db::getOne(
        "SELECT COUNT(*) FROM absences
         WHERE user_id = ? AND statut != 'refuse'
           AND date_debut <= ? AND date_fin >= ?",
        [$user['id'], $dateFin, $dateDebut]
    );
    if ($overlap > 0) {
        bad_request('Vous avez déjà une absence sur cette période');
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO absences (id, user_id, date_debut, date_fin, type, motif, commentaire)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $user['id'], $dateDebut, $dateFin, $type, $motif, $commentaire]
    );

    respond(['success' => true, 'message' => 'Demande soumise', 'id' => $id]);
}

function upload_absence_justificatif()
{
    global $params;
    $user = require_auth();

    $absenceId = $params['absence_id'] ?? '';
    if (!$absenceId) bad_request('absence_id requis');

    $absence = Db::fetch("SELECT id, user_id FROM absences WHERE id = ? AND user_id = ?", [$absenceId, $user['id']]);
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

    $uploadDir = __DIR__ . '/../storage/justificatifs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = $absenceId . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        bad_request('Erreur lors de l\'enregistrement');
    }

    $path = '/zerdatime/storage/justificatifs/' . $filename;
    $originalName = basename($file['name']);

    Db::exec(
        "UPDATE absences SET justificatif_path = ?, justificatif_name = ?, justifie = 1 WHERE id = ?",
        [$path, $originalName, $absenceId]
    );

    respond(['success' => true, 'path' => $path, 'name' => $originalName, 'message' => 'Justificatif ajouté']);
}

function get_absences_collegues()
{
    global $params;
    require_auth();

    // Public view: names, dates, status, module - no medical info (RGPD)
    $absences = Db::fetchAll(
        "SELECT a.id, u.prenom, u.nom, a.date_debut, a.date_fin, a.type, a.statut,
                m.nom AS module_nom
         FROM absences a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN user_modules um ON um.user_id = u.id AND um.is_principal = 1
         LEFT JOIN modules m ON m.id = um.module_id
         WHERE a.statut = 'valide'
           AND a.date_fin >= CURDATE()
         ORDER BY a.date_debut",
        []
    );

    respond(['success' => true, 'absences' => $absences]);
}
