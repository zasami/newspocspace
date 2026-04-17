<?php
/**
 * Employee — Fiches de salaire (view/download own payslips)
 */

function get_mes_fiches_salaire()
{
    $user = require_auth();

    $fiches = Db::fetchAll(
        "SELECT id, annee, mois, original_name, size, created_at
         FROM fiches_salaire
         WHERE user_id = ?
         ORDER BY annee DESC, mois DESC",
        [$user['id']]
    );

    respond(['success' => true, 'fiches' => $fiches]);
}

function serve_fiche_salaire()
{
    global $params;
    $user = require_auth();

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $fiche = Db::fetch(
        "SELECT filename, original_name, annee, mois
         FROM fiches_salaire
         WHERE id = ? AND user_id = ?",
        [$id, $user['id']]
    );
    if (!$fiche) not_found('Fiche introuvable');

    $filePath = __DIR__ . '/../storage/fiches_salaire/' . $fiche['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../storage/fiches_salaire/');
    if ($realPath === false || strpos($realPath, $storageDir) !== 0) {
        forbidden('Accès interdit');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . safe_content_disposition(addslashes($fiche['original_name']), 'inline'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    exit;
}
