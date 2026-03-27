<?php
/**
 * Documents API — Employee (read-only access with visibility/access checks)
 */

function get_documents()
{
    $user = require_auth();
    global $params;

    $serviceId = $params['service_id'] ?? '';
    $search = Sanitize::text($params['search'] ?? '', 200);

    $where = ['d.visible = 1'];
    $binds = [];

    if ($serviceId) {
        $where[] = 'd.service_id = ?';
        $binds[] = $serviceId;
    }

    if ($search) {
        $where[] = '(d.titre LIKE ? OR d.original_name LIKE ? OR d.description LIKE ?)';
        $binds[] = "%$search%";
        $binds[] = "%$search%";
        $binds[] = "%$search%";
    }

    $whereSql = implode(' AND ', $where);

    $docs = Db::fetchAll(
        "SELECT d.id, d.titre, d.description, d.original_name, d.mime_type, d.size, d.created_at,
                s.nom AS service_nom, s.slug AS service_slug, s.icone AS service_icone, s.couleur AS service_couleur
         FROM documents d
         JOIN document_services s ON s.id = d.service_id AND s.actif = 1
         WHERE $whereSql
         ORDER BY d.created_at DESC",
        $binds
    );

    // Filter out documents with access restrictions for this user
    $userRole = $user['role'] ?? 'collaborateur';
    $filtered = [];
    foreach ($docs as $doc) {
        $blocked = Db::fetch(
            "SELECT id FROM document_access
             WHERE document_id = ? AND acces = 'bloque' AND (role = ? OR service_id = ?)",
            [$doc['id'], $userRole, $doc['service_slug'] ?? '']
        );
        if (!$blocked) {
            $filtered[] = $doc;
        }
    }

    respond(['success' => true, 'documents' => $filtered]);
}

function get_document_services()
{
    require_auth();

    $services = Db::fetchAll(
        "SELECT id, nom, slug, icone, couleur
         FROM document_services
         WHERE actif = 1
         ORDER BY ordre, nom"
    );

    respond(['success' => true, 'services' => $services]);
}

function serve_document()
{
    $user = require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch(
        "SELECT d.filename, d.original_name, d.mime_type, d.visible
         FROM documents d
         JOIN document_services s ON s.id = d.service_id AND s.actif = 1
         WHERE d.id = ?",
        [$id]
    );
    if (!$doc || !$doc['visible']) not_found('Document introuvable');

    // Check access
    $userRole = $user['role'] ?? 'collaborateur';
    $blocked = Db::fetch(
        "SELECT id FROM document_access WHERE document_id = ? AND acces = 'bloque' AND role = ?",
        [$id, $userRole]
    );
    if ($blocked) forbidden('Accès bloqué à ce document');

    $filePath = __DIR__ . '/../storage/documents/' . $doc['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../storage/documents/');
    if ($realPath === false || strpos($realPath, $storageDir) !== 0) {
        forbidden('Accès interdit');
    }

    header('Content-Type: ' . $doc['mime_type']);
    header('Content-Disposition: inline; filename="' . addslashes($doc['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    exit;
}
