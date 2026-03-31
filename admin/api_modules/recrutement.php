<?php
/**
 * Admin Recrutement API
 */

function admin_get_offres()
{
    require_responsable();

    $offres = Db::fetchAll(
        "SELECT o.*,
                (SELECT COUNT(*) FROM candidatures c WHERE c.offre_id = o.id) AS nb_candidatures,
                u.prenom AS created_prenom, u.nom AS created_nom
         FROM offres_emploi o
         LEFT JOIN users u ON u.id = o.created_by
         ORDER BY o.created_at DESC"
    );

    respond(['success' => true, 'offres' => $offres]);
}

function admin_create_offre()
{
    require_admin();
    global $params;

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $id = Uuid::v4();

    Db::exec(
        "INSERT INTO offres_emploi (id, titre, description, type_contrat, taux_activite, departement, lieu, date_debut, date_limite, exigences, avantages, salaire_indication, contact_email, is_active, ordre, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id,
            $titre,
            Sanitize::text($params['description'] ?? '', 10000),
            Sanitize::text($params['type_contrat'] ?? 'CDI', 50),
            Sanitize::text($params['taux_activite'] ?? '100%', 50),
            Sanitize::text($params['departement'] ?? '', 100),
            Sanitize::text($params['lieu'] ?? 'Genève', 100),
            Sanitize::date($params['date_debut'] ?? '') ?: null,
            Sanitize::date($params['date_limite'] ?? '') ?: null,
            Sanitize::text($params['exigences'] ?? '', 10000),
            Sanitize::text($params['avantages'] ?? '', 10000),
            Sanitize::text($params['salaire_indication'] ?? '', 255),
            Sanitize::email($params['contact_email'] ?? '') ?: null,
            1,
            Sanitize::int($params['ordre'] ?? 0),
            $_SESSION['zt_user']['id'],
        ]
    );

    respond(['success' => true, 'message' => 'Offre créée', 'id' => $id]);
}

function admin_update_offre()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $offre = Db::fetch("SELECT id FROM offres_emploi WHERE id = ?", [$id]);
    if (!$offre) not_found('Offre introuvable');

    $sets = [];
    $binds = [];

    $fields = [
        'titre'              => ['sanitize' => 'text',  'max' => 255],
        'description'        => ['sanitize' => 'text',  'max' => 10000],
        'type_contrat'       => ['sanitize' => 'text',  'max' => 50],
        'taux_activite'      => ['sanitize' => 'text',  'max' => 50],
        'departement'        => ['sanitize' => 'text',  'max' => 100],
        'lieu'               => ['sanitize' => 'text',  'max' => 100],
        'exigences'          => ['sanitize' => 'text',  'max' => 10000],
        'avantages'          => ['sanitize' => 'text',  'max' => 10000],
        'salaire_indication' => ['sanitize' => 'text',  'max' => 255],
    ];

    foreach ($fields as $field => $opts) {
        if (isset($params[$field])) {
            $sets[] = "$field = ?";
            $binds[] = Sanitize::text($params[$field], $opts['max']);
        }
    }

    if (isset($params['date_debut'])) {
        $sets[] = 'date_debut = ?';
        $binds[] = Sanitize::date($params['date_debut']) ?: null;
    }
    if (isset($params['date_limite'])) {
        $sets[] = 'date_limite = ?';
        $binds[] = Sanitize::date($params['date_limite']) ?: null;
    }
    if (isset($params['contact_email'])) {
        $sets[] = 'contact_email = ?';
        $binds[] = Sanitize::email($params['contact_email']) ?: null;
    }
    if (isset($params['is_active'])) {
        $sets[] = 'is_active = ?';
        $binds[] = (int) $params['is_active'];
    }
    if (isset($params['ordre'])) {
        $sets[] = 'ordre = ?';
        $binds[] = Sanitize::int($params['ordre']);
    }

    if (empty($sets)) bad_request('Rien à modifier');

    $binds[] = $id;
    Db::exec("UPDATE offres_emploi SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Offre mise à jour']);
}

function admin_delete_offre()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $offre = Db::fetch("SELECT id FROM offres_emploi WHERE id = ?", [$id]);
    if (!$offre) not_found('Offre introuvable');

    $nbCandidatures = (int) Db::getOne("SELECT COUNT(*) FROM candidatures WHERE offre_id = ?", [$id]);

    if ($nbCandidatures > 0) {
        // Soft delete: deactivate
        Db::exec("UPDATE offres_emploi SET is_active = 0 WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Offre désactivée (candidatures existantes)']);
    } else {
        // Hard delete
        Db::exec("DELETE FROM offres_emploi WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Offre supprimée']);
    }
}

function admin_get_candidatures()
{
    require_responsable();
    global $params;

    $page = max(1, (int) ($params['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $binds = [];

    $offreId = $params['offre_id'] ?? '';
    if ($offreId) {
        $where[] = 'c.offre_id = ?';
        $binds[] = $offreId;
    }

    $statut = $params['statut'] ?? '';
    if ($statut) {
        $where[] = 'c.statut = ?';
        $binds[] = $statut;
    }

    $search = Sanitize::text($params['search'] ?? '', 200);
    if ($search) {
        $where[] = '(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)';
        $binds[] = "%$search%";
        $binds[] = "%$search%";
        $binds[] = "%$search%";
    }

    $whereSql = implode(' AND ', $where);

    $total = (int) Db::getOne("SELECT COUNT(*) FROM candidatures c WHERE $whereSql", $binds);

    $candidatures = Db::fetchAll(
        "SELECT c.*, o.titre AS offre_titre,
                (SELECT COUNT(*) FROM candidature_documents cd WHERE cd.candidature_id = c.id) AS nb_documents
         FROM candidatures c
         LEFT JOIN offres_emploi o ON o.id = c.offre_id
         WHERE $whereSql
         ORDER BY c.created_at DESC
         LIMIT $limit OFFSET $offset",
        $binds
    );

    respond([
        'success' => true,
        'candidatures' => $candidatures,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
    ]);
}

function admin_get_candidature_detail()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $candidature = Db::fetch(
        "SELECT c.*, o.titre AS offre_titre, o.type_contrat AS offre_type_contrat,
                o.departement AS offre_departement
         FROM candidatures c
         LEFT JOIN offres_emploi o ON o.id = c.offre_id
         WHERE c.id = ?",
        [$id]
    );

    if (!$candidature) not_found('Candidature introuvable');

    $documents = Db::fetchAll(
        "SELECT id, type_document, original_name, mime_type, size, created_at
         FROM candidature_documents
         WHERE candidature_id = ?
         ORDER BY created_at",
        [$id]
    );

    $candidature['documents'] = $documents;

    respond(['success' => true, 'candidature' => $candidature]);
}

function admin_update_candidature_status()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    $notesAdmin = $params['notes_admin'] ?? null;

    if (!$id) bad_request('ID requis');

    $allowed = ['recue', 'en_cours', 'entretien', 'acceptee', 'refusee', 'archivee'];
    if (!in_array($statut, $allowed)) {
        bad_request('Statut invalide');
    }

    $candidature = Db::fetch("SELECT id FROM candidatures WHERE id = ?", [$id]);
    if (!$candidature) not_found('Candidature introuvable');

    $sets = ['statut = ?'];
    $binds = [$statut];

    if ($notesAdmin !== null) {
        $sets[] = 'notes_admin = ?';
        $binds[] = Sanitize::text($notesAdmin, 10000);
    }

    $binds[] = $id;
    Db::exec("UPDATE candidatures SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Statut mis à jour']);
}

function admin_download_candidature_doc()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $doc = Db::fetch(
        "SELECT filename, original_name, mime_type FROM candidature_documents WHERE id = ?",
        [$id]
    );
    if (!$doc) not_found('Document introuvable');

    $filePath = __DIR__ . '/../../storage/candidatures/' . $doc['filename'];
    if (!file_exists($filePath)) not_found('Fichier introuvable');

    $realPath = realpath($filePath);
    $storageDir = realpath(__DIR__ . '/../../storage/candidatures/');
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

function admin_delete_candidature()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $candidature = Db::fetch("SELECT id FROM candidatures WHERE id = ?", [$id]);
    if (!$candidature) not_found('Candidature introuvable');

    // Delete documents from disk
    $docs = Db::fetchAll("SELECT filename FROM candidature_documents WHERE candidature_id = ?", [$id]);
    $storageDir = __DIR__ . '/../../storage/candidatures/';
    foreach ($docs as $doc) {
        $filePath = $storageDir . $doc['filename'];
        if (file_exists($filePath)) @unlink($filePath);
    }

    // Delete from DB
    Db::exec("DELETE FROM candidature_documents WHERE candidature_id = ?", [$id]);
    Db::exec("DELETE FROM candidatures WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Candidature supprimée']);
}
