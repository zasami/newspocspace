<?php
/**
 * Admin Documents API
 */
require_once __DIR__ . '/../../core/Notification.php';

function admin_get_documents()
{
    require_responsable();
    global $params;

    $serviceId = $params['service_id'] ?? '';
    $search = Sanitize::text($params['search'] ?? '', 200);
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $showArchived = !empty($params['show_archived']);
    $where = $showArchived ? ['1=1'] : ['d.archived_at IS NULL'];
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

    $total = (int) Db::getOne("SELECT COUNT(*) FROM documents d WHERE $whereSql", $binds);

    $docs = Db::fetchAll(
        "SELECT d.*, s.nom AS service_nom, s.slug AS service_slug, s.icone AS service_icone, s.couleur AS service_couleur,
                u.prenom AS uploaded_prenom, u.nom AS uploaded_nom
         FROM documents d
         JOIN document_services s ON s.id = d.service_id
         JOIN users u ON u.id = d.uploaded_by
         WHERE $whereSql
         ORDER BY d.created_at DESC
         LIMIT $limit OFFSET $offset",
        $binds
    );

    // Count access restrictions for each document
    foreach ($docs as &$doc) {
        $doc['restrictions'] = (int) Db::getOne(
            "SELECT COUNT(*) FROM document_access WHERE document_id = ? AND acces = 'bloque'",
            [$doc['id']]
        );
    }

    respond([
        'success' => true,
        'documents' => $docs,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
    ]);
}

function admin_get_document_services()
{
    require_responsable();

    $services = Db::fetchAll(
        "SELECT s.*, (SELECT COUNT(*) FROM documents d WHERE d.service_id = s.id) AS doc_count
         FROM document_services s
         ORDER BY s.ordre, s.nom"
    );

    respond(['success' => true, 'services' => $services]);
}

function admin_upload_document()
{
    $admin = require_responsable();

    $titre = Sanitize::text($_POST['titre'] ?? '', 255);
    $description = Sanitize::text($_POST['description'] ?? '', 2000);
    $serviceId = $_POST['service_id'] ?? '';

    if (!$titre) bad_request('Titre requis');
    if (!$serviceId) bad_request('Service requis');

    // Verify service exists
    $service = Db::fetch("SELECT id FROM document_services WHERE id = ?", [$serviceId]);
    if (!$service) bad_request('Service invalide');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant ou invalide');
    }

    $file = $_FILES['file'];
    $maxSize = 20 * 1024 * 1024; // 20 MB
    if ($file['size'] > $maxSize) bad_request('Fichier trop volumineux (max 20 Mo)');

    $allowed = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'text/plain', 'text/csv',
    ];
    if (!in_array($file['type'], $allowed, true)) bad_request('Type de fichier non autorisé');

    $storageDir = __DIR__ . '/../../storage/documents/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde du fichier');
    }

    $originalName = mb_substr(basename($file['name']), 0, 255);
    $documentId = $_POST['document_id'] ?? ''; // If set, this is a new version

    if ($documentId) {
        // New version of existing document
        $existingDoc = Db::fetch("SELECT id, filename, original_name, mime_type, size, version, uploaded_by FROM documents WHERE id = ?", [$documentId]);
        if (!$existingDoc) bad_request('Document introuvable');

        // Archive current version
        Db::exec(
            "INSERT INTO document_versions (id, document_id, version, filename, original_name, mime_type, size, uploaded_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $documentId, $existingDoc['version'], $existingDoc['filename'], $existingDoc['original_name'],
             $existingDoc['mime_type'], $existingDoc['size'], $existingDoc['uploaded_by'],
             Sanitize::text($_POST['version_note'] ?? '', 500)]
        );

        $newVersion = (int) $existingDoc['version'] + 1;
        Db::exec(
            "UPDATE documents SET filename = ?, original_name = ?, mime_type = ?, size = ?, version = ?, uploaded_by = ? WHERE id = ?",
            [$filename, $originalName, $file['type'], $file['size'], $newVersion, $admin['id'], $documentId]
        );
        $id = $documentId;
    } else {
        // Brand new document
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO documents (id, titre, description, service_id, filename, original_name, mime_type, size, version, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
            [$id, $titre, $description, $serviceId, $filename, $originalName, $file['type'], $file['size'], $admin['id']]
        );
    }

    // Notify all active users about new document (not for new versions)
    if (!$documentId) {
        $activeUsers = Db::fetchAll("SELECT id FROM users WHERE is_active = 1");
        foreach ($activeUsers as $u) {
            if ($u['id'] !== $admin['id']) {
                Notification::create($u['id'], 'document_ajoute', 'Nouveau document',
                    "Le document « $titre » a été ajouté.", 'documents');
            }
        }
    }

    respond([
        'success' => true,
        'message' => $documentId ? 'Nouvelle version téléversée (v' . $newVersion . ')' : 'Document téléversé',
        'document' => ['id' => $id, 'titre' => $titre],
    ]);
}

function admin_update_document()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $titre = Sanitize::text($params['titre'] ?? '', 255);
    $description = Sanitize::text($params['description'] ?? '', 2000);
    $serviceId = $params['service_id'] ?? '';
    $visible = isset($params['visible']) ? (int) $params['visible'] : null;

    if (!$id) bad_request('ID requis');

    $doc = Db::fetch("SELECT id FROM documents WHERE id = ?", [$id]);
    if (!$doc) not_found('Document introuvable');

    $sets = [];
    $binds = [];

    if ($titre) { $sets[] = 'titre = ?'; $binds[] = $titre; }
    if ($description !== '') { $sets[] = 'description = ?'; $binds[] = $description; }
    if ($serviceId) {
        $service = Db::fetch("SELECT id FROM document_services WHERE id = ?", [$serviceId]);
        if ($service) { $sets[] = 'service_id = ?'; $binds[] = $serviceId; }
    }
    if ($visible !== null) { $sets[] = 'visible = ?'; $binds[] = $visible; }

    if (empty($sets)) bad_request('Rien à modifier');

    $binds[] = $id;
    Db::exec("UPDATE documents SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Document mis à jour']);
}

function admin_delete_document()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $permanent = !empty($params['permanent']);
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch("SELECT id, filename FROM documents WHERE id = ?", [$id]);
    if (!$doc) not_found('Document introuvable');

    if ($permanent) {
        // Hard delete: remove all version files + current file
        $versions = Db::fetchAll("SELECT filename FROM document_versions WHERE document_id = ?", [$id]);
        $storageDir = __DIR__ . '/../../storage/documents/';
        foreach ($versions as $v) {
            $vPath = $storageDir . $v['filename'];
            if (file_exists($vPath)) @unlink($vPath);
        }
        $filePath = $storageDir . $doc['filename'];
        if (file_exists($filePath)) @unlink($filePath);

        Db::exec("DELETE FROM document_versions WHERE document_id = ?", [$id]);
        Db::exec("DELETE FROM document_access WHERE document_id = ?", [$id]);
        Db::exec("DELETE FROM documents WHERE id = ?", [$id]);

        respond(['success' => true, 'message' => 'Document supprimé définitivement']);
    } else {
        // Soft delete: archive
        Db::exec("UPDATE documents SET archived_at = NOW(), archived_by = ?, visible = 0 WHERE id = ?",
            [$_SESSION['ss_user']['id'], $id]);
        respond(['success' => true, 'message' => 'Document archivé']);
    }
}

function admin_archive_document()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("UPDATE documents SET archived_at = NOW(), archived_by = ?, visible = 0 WHERE id = ?",
        [$_SESSION['ss_user']['id'], $id]);
    respond(['success' => true, 'message' => 'Document archivé']);
}

function admin_restore_document()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("UPDATE documents SET archived_at = NULL, archived_by = NULL, visible = 1 WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Document restauré']);
}

function admin_get_document_versions()
{
    require_responsable();
    global $params;
    $id = $params['document_id'] ?? '';
    if (!$id) bad_request('document_id requis');

    $versions = Db::fetchAll(
        "SELECT dv.*, u.prenom, u.nom
         FROM document_versions dv
         LEFT JOIN users u ON u.id = dv.uploaded_by
         WHERE dv.document_id = ?
         ORDER BY dv.version DESC",
        [$id]
    );

    $current = Db::fetch(
        "SELECT d.version, d.filename, d.original_name, d.mime_type, d.size, d.uploaded_by, d.updated_at,
                u.prenom, u.nom
         FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ?", [$id]);

    respond(['success' => true, 'versions' => $versions, 'current' => $current]);
}

function admin_restore_document_version()
{
    require_responsable();
    global $params;
    $versionId = $params['version_id'] ?? '';
    if (!$versionId) bad_request('version_id requis');

    $version = Db::fetch("SELECT * FROM document_versions WHERE id = ?", [$versionId]);
    if (!$version) not_found('Version introuvable');

    $docId = $version['document_id'];
    $doc = Db::fetch("SELECT * FROM documents WHERE id = ?", [$docId]);
    if (!$doc) not_found('Document introuvable');

    // Save current as a new version entry
    Db::exec(
        "INSERT INTO document_versions (id, document_id, version, filename, original_name, mime_type, size, uploaded_by, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Remplacé par restauration')",
        [Uuid::v4(), $docId, $doc['version'], $doc['filename'], $doc['original_name'],
         $doc['mime_type'], $doc['size'], $doc['uploaded_by']]
    );

    // Restore the old version as current
    $newVersion = (int) $doc['version'] + 1;
    Db::exec(
        "UPDATE documents SET filename = ?, original_name = ?, mime_type = ?, size = ?, version = ? WHERE id = ?",
        [$version['filename'], $version['original_name'], $version['mime_type'], $version['size'], $newVersion, $docId]
    );

    respond(['success' => true, 'message' => 'Version restaurée (v' . $newVersion . ')']);
}

function admin_serve_document_version()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $v = Db::fetch("SELECT filename, original_name, mime_type FROM document_versions WHERE id = ?", [$id]);
    if (!$v) not_found('Version introuvable');

    $filePath = __DIR__ . '/../../storage/documents/' . $v['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../../storage/documents/');
    if ($realPath === false || strpos($realPath, $storageDir) !== 0) forbidden('Accès interdit');

    header('Content-Type: ' . $v['mime_type']);
    header('Content-Disposition: inline; filename="' . addslashes($v['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

/**
 * Convert DOCX to PDF and serve it.
 * Caches the PDF so conversion only happens once per file version.
 */
function admin_convert_document_pdf()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch("SELECT filename, original_name, mime_type, version FROM documents WHERE id = ?", [$id]);
    if (!$doc) not_found('Document introuvable');

    // Only convert Word documents
    $isWord = strpos($doc['mime_type'], 'word') !== false || strpos($doc['mime_type'], 'msword') !== false;
    if (!$isWord) {
        // Not a Word doc — serve original
        $filePath = __DIR__ . '/../../storage/documents/' . $doc['filename'];
        if (!file_exists($filePath)) not_found('Fichier introuvable');
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: inline; filename="' . addslashes($doc['original_name']) . '"');
        readfile($filePath);
        exit;
    }

    $storageDir = __DIR__ . '/../../storage/documents/';
    $cacheDir = $storageDir . 'pdf_cache/';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    // Cache key: document id + version
    $cacheFile = $cacheDir . $id . '_v' . $doc['version'] . '.pdf';

    if (file_exists($cacheFile)) {
        // Serve cached PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . addslashes(pathinfo($doc['original_name'], PATHINFO_FILENAME) . '.pdf') . '"');
        header('Content-Length: ' . filesize($cacheFile));
        header('Cache-Control: private, max-age=86400');
        readfile($cacheFile);
        exit;
    }

    // Convert DOCX → HTML → PDF
    $srcPath = $storageDir . $doc['filename'];
    if (!file_exists($srcPath)) not_found('Fichier source introuvable');

    require_once __DIR__ . '/../../vendor/autoload.php';

    try {
        // Read DOCX with PhpWord
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($srcPath, 'Word2007');

        // Convert to HTML
        $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        ob_start();
        $htmlWriter->save('php://output');
        $html = ob_get_clean();

        // Clean up HTML for DOMPDF
        $html = preg_replace('/<head>.*?<\/head>/s', '<head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #333; line-height: 1.5; margin: 20mm; }
            table { border-collapse: collapse; width: 100%; margin: 8px 0; }
            td, th { border: 1px solid #ccc; padding: 6px 8px; font-size: 10pt; }
            th { background: #f5f5f5; font-weight: bold; }
            img { max-width: 100%; height: auto; }
            h1 { font-size: 18pt; } h2 { font-size: 15pt; } h3 { font-size: 13pt; }
        </style></head>', $html);

        // Generate PDF with DOMPDF
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();

        // Cache the result
        file_put_contents($cacheFile, $pdfContent);

        // Serve
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . addslashes(pathinfo($doc['original_name'], PATHINFO_FILENAME) . '.pdf') . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=86400');
        echo $pdfContent;
        exit;

    } catch (\Exception $e) {
        bad_request('Erreur de conversion : ' . $e->getMessage());
    }
}

function admin_toggle_document_visibility()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch("SELECT id, visible FROM documents WHERE id = ?", [$id]);
    if (!$doc) not_found('Document introuvable');

    $newVisible = $doc['visible'] ? 0 : 1;
    Db::exec("UPDATE documents SET visible = ? WHERE id = ?", [$newVisible, $id]);

    respond(['success' => true, 'visible' => $newVisible, 'message' => $newVisible ? 'Document visible' : 'Document masqué']);
}

function admin_set_document_access()
{
    require_responsable();
    global $params;

    $documentId = $params['document_id'] ?? '';
    $rules = $params['rules'] ?? [];

    if (!$documentId) bad_request('document_id requis');

    $doc = Db::fetch("SELECT id FROM documents WHERE id = ?", [$documentId]);
    if (!$doc) not_found('Document introuvable');

    // Clear existing rules for this document
    Db::exec("DELETE FROM document_access WHERE document_id = ?", [$documentId]);

    // Insert new rules
    $validRoles = ['collaborateur', 'responsable', 'admin', 'direction'];
    foreach ($rules as $rule) {
        $role = $rule['role'] ?? null;
        $serviceId = $rule['service_id'] ?? null;
        $acces = ($rule['acces'] ?? 'visible') === 'bloque' ? 'bloque' : 'visible';

        if ($role && !in_array($role, $validRoles, true)) continue;

        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO document_access (id, document_id, service_id, role, acces) VALUES (?, ?, ?, ?, ?)",
            [$id, $documentId, $serviceId, $role, $acces]
        );
    }

    respond(['success' => true, 'message' => 'Accès mis à jour']);
}

function admin_get_document_access()
{
    require_responsable();
    global $params;

    $documentId = $params['document_id'] ?? '';
    if (!$documentId) bad_request('document_id requis');

    $rules = Db::fetchAll(
        "SELECT da.*, ds.nom AS service_nom
         FROM document_access da
         LEFT JOIN document_services ds ON ds.id = da.service_id
         WHERE da.document_id = ?",
        [$documentId]
    );

    respond(['success' => true, 'rules' => $rules]);
}

function admin_create_service()
{
    require_responsable();
    global $params;

    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $icone = Sanitize::text($params['icone'] ?? 'folder', 50);
    $couleur = Sanitize::text($params['couleur'] ?? '#6c757d', 20);

    if (!$nom) bad_request('Nom requis');

    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nom)));
    $slug = trim($slug, '-');

    $existing = Db::fetch("SELECT id FROM document_services WHERE slug = ?", [$slug]);
    if ($existing) bad_request('Un service avec ce nom existe déjà');

    $maxOrdre = (int) Db::getOne("SELECT MAX(ordre) FROM document_services");

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO document_services (id, nom, slug, icone, couleur, ordre) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $nom, $slug, $icone, $couleur, $maxOrdre + 1]
    );

    respond(['success' => true, 'message' => 'Service créé', 'id' => $id]);
}

function admin_update_service()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $nom = Sanitize::text($params['nom'] ?? '', 100);
    $icone = Sanitize::text($params['icone'] ?? '', 50);
    $couleur = Sanitize::text($params['couleur'] ?? '', 20);
    $actif = isset($params['actif']) ? (int) $params['actif'] : null;

    if (!$id) bad_request('ID requis');

    $service = Db::fetch("SELECT id FROM document_services WHERE id = ?", [$id]);
    if (!$service) not_found('Service introuvable');

    $sets = [];
    $binds = [];

    if ($nom) { $sets[] = 'nom = ?'; $binds[] = $nom; }
    if ($icone) { $sets[] = 'icone = ?'; $binds[] = $icone; }
    if ($couleur) { $sets[] = 'couleur = ?'; $binds[] = $couleur; }
    if ($actif !== null) { $sets[] = 'actif = ?'; $binds[] = $actif; }

    if (empty($sets)) bad_request('Rien à modifier');

    $binds[] = $id;
    Db::exec("UPDATE document_services SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Service mis à jour']);
}

function admin_serve_document()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch("SELECT filename, original_name, mime_type FROM documents WHERE id = ?", [$id]);
    if (!$doc) not_found('Document introuvable');

    $filePath = __DIR__ . '/../../storage/documents/' . $doc['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../../storage/documents/');
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
