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
            $_SESSION['ss_user']['id'],
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

// ═══════════════════════════════════════════════════════════════════════════════
// FORMATIONS
// ═══════════════════════════════════════════════════════════════════════════════

function admin_get_formations()
{
    require_responsable();

    $formations = Db::fetchAll(
        "SELECT f.*, f.type AS type_formation,
                (SELECT COUNT(*) FROM formation_participants fp WHERE fp.formation_id = f.id) AS nb_participants,
                u.prenom AS created_prenom, u.nom AS created_nom
         FROM formations f
         LEFT JOIN users u ON u.id = f.created_by
         ORDER BY f.date_debut DESC, f.created_at DESC"
    );

    $stats = [
        'total' => (int) Db::getOne("SELECT COUNT(*) FROM formations"),
        'planifiee' => (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'planifiee'"),
        'en_cours' => (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'en_cours'"),
        'terminee' => (int) Db::getOne("SELECT COUNT(*) FROM formations WHERE statut = 'terminee'"),
    ];

    respond(['success' => true, 'formations' => $formations, 'stats' => $stats]);
}

function admin_create_formation()
{
    require_admin();
    global $params;

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO formations (id, titre, description, type, formateur, lieu, date_debut, date_fin, duree_heures, max_participants, is_obligatoire, statut, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id, $titre,
            Sanitize::text($params['description'] ?? '', 10000),
            in_array($params['type_formation'] ?? $params['type'] ?? '', ['interne','externe','e-learning','certificat']) ? ($params['type_formation'] ?? $params['type']) : 'interne',
            Sanitize::text($params['formateur'] ?? '', 255),
            Sanitize::text($params['lieu'] ?? '', 255),
            Sanitize::date($params['date_debut'] ?? '') ?: null,
            Sanitize::date($params['date_fin'] ?? '') ?: null,
            $params['duree_heures'] ?? null,
            $params['max_participants'] ? (int) $params['max_participants'] : null,
            !empty($params['is_obligatoire']) ? 1 : 0,
            'planifiee',
            $_SESSION['ss_user']['id'],
        ]
    );

    respond(['success' => true, 'message' => 'Formation créée', 'id' => $id]);
}

function admin_update_formation()
{
    require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    if (!Db::fetch("SELECT id FROM formations WHERE id = ?", [$id])) not_found();

    $sets = [];
    $binds = [];
    $textFields = ['titre' => 255, 'description' => 10000, 'formateur' => 255, 'lieu' => 255];
    foreach ($textFields as $f => $max) {
        if (isset($params[$f])) { $sets[] = "$f = ?"; $binds[] = Sanitize::text($params[$f], $max); }
    }
    $typeVal = $params['type_formation'] ?? $params['type'] ?? null;
    if ($typeVal !== null) { $sets[] = 'type = ?'; $binds[] = in_array($typeVal, ['interne','externe','e-learning','certificat']) ? $typeVal : 'interne'; }
    if (isset($params['date_debut'])) { $sets[] = 'date_debut = ?'; $binds[] = Sanitize::date($params['date_debut']) ?: null; }
    if (isset($params['date_fin'])) { $sets[] = 'date_fin = ?'; $binds[] = Sanitize::date($params['date_fin']) ?: null; }
    if (isset($params['duree_heures'])) { $sets[] = 'duree_heures = ?'; $binds[] = $params['duree_heures']; }
    if (isset($params['max_participants'])) { $sets[] = 'max_participants = ?'; $binds[] = (int) $params['max_participants'] ?: null; }
    if (isset($params['is_obligatoire'])) { $sets[] = 'is_obligatoire = ?'; $binds[] = (int) $params['is_obligatoire']; }
    if (isset($params['statut'])) {
        $allowed = ['planifiee','en_cours','terminee','annulee'];
        if (in_array($params['statut'], $allowed)) { $sets[] = 'statut = ?'; $binds[] = $params['statut']; }
    }

    if (empty($sets)) bad_request('Rien à modifier');
    $binds[] = $id;
    Db::exec("UPDATE formations SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Formation mise à jour']);
}

function admin_delete_formation()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM formation_participants WHERE formation_id = ?", [$id]);
    Db::exec("DELETE FROM formations WHERE id = ?", [$id]);

    respond(['success' => true, 'message' => 'Formation supprimée']);
}

function admin_get_formation_detail()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $formation = Db::fetch("SELECT *, type AS type_formation FROM formations WHERE id = ?", [$id]);
    if (!$formation) not_found();

    $formation['participants'] = Db::fetchAll(
        "SELECT fp.*, u.prenom, u.nom, u.email, u.photo, f.nom AS fonction_nom
         FROM formation_participants fp
         JOIN users u ON u.id = fp.user_id
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE fp.formation_id = ?
         ORDER BY u.nom, u.prenom",
        [$id]
    );

    respond(['success' => true, 'formation' => $formation]);
}

function admin_add_formation_participant()
{
    require_responsable();
    global $params;
    $formationId = $params['formation_id'] ?? '';
    $userId = $params['user_id'] ?? '';
    if (!$formationId || !$userId) bad_request('Paramètres manquants');

    $existing = Db::fetch("SELECT id FROM formation_participants WHERE formation_id = ? AND user_id = ?", [$formationId, $userId]);
    if ($existing) bad_request('Déjà inscrit');

    Db::exec("INSERT INTO formation_participants (id, formation_id, user_id) VALUES (?, ?, ?)", [Uuid::v4(), $formationId, $userId]);
    respond(['success' => true, 'message' => 'Participant ajouté']);
}

function admin_remove_formation_participant()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM formation_participants WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Participant retiré']);
}

function admin_update_formation_participant()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $sets = [];
    $binds = [];
    if (isset($params['statut'])) {
        $allowed = ['inscrit','present','absent','valide'];
        if (in_array($params['statut'], $allowed)) { $sets[] = 'statut = ?'; $binds[] = $params['statut']; }
    }
    if (isset($params['note'])) { $sets[] = 'note = ?'; $binds[] = Sanitize::text($params['note'], 2000); }

    if (empty($sets)) bad_request('Rien à modifier');
    $binds[] = $id;
    Db::exec("UPDATE formation_participants SET " . implode(', ', $sets) . " WHERE id = ?", $binds);
    respond(['success' => true, 'message' => 'Participant mis à jour']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// IMPORT FORMATIONS — FEGEMS scrape
// ═══════════════════════════════════════════════════════════════════════════════

function admin_import_fegems_formations()
{
    require_admin();

    $html = @file_get_contents('https://www.fegems.ch/formation/');
    if (!$html) bad_request('Impossible de contacter fegems.ch');

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);

    $articles = $xpath->query("//article[contains(@class, 'formation')]");
    $formations = [];

    foreach ($articles as $article) {
        // Title + URL
        $titleNode = $xpath->query(".//h2[contains(@class, 'fancy-heading')]//span[contains(@class, 'main-head')]/a", $article)->item(0);
        if (!$titleNode) continue;
        $titre = trim($titleNode->textContent);
        $url = $titleNode->getAttribute('href');

        // Image
        $imgNode = $xpath->query(".//div[contains(@class, 'image-wrap')]//img", $article)->item(0);
        $imageUrl = $imgNode ? $imgNode->getAttribute('src') : '';
        // Try to get larger image by removing size suffix
        if ($imageUrl) {
            $imageUrl = preg_replace('/-\d+x\d+\./', '.', $imageUrl);
        }

        // Date — icon block with alarm-clock
        $dateText = '';
        $iconBlocks = $xpath->query(".//div[contains(@class, 'module-icon')]", $article);
        foreach ($iconBlocks as $block) {
            $svg = $xpath->query(".//svg[contains(@class, 'alarm-clock')]", $block)->item(0);
            if ($svg) {
                $span = $xpath->query(".//span", $block)->item(0);
                $dateText = $span ? trim($span->textContent) : '';
                break;
            }
        }

        // Modalite — icon block with info-alt
        $modalite = '';
        foreach ($iconBlocks as $block) {
            $svg = $xpath->query(".//svg[contains(@class, 'info-alt')]", $block)->item(0);
            if ($svg) {
                $span = $xpath->query(".//span", $block)->item(0);
                $modalite = $span ? trim($span->textContent) : '';
                $modalite = preg_replace('/^Modalit[eé]\s*:\s*/i', '', $modalite);
                break;
            }
        }

        // Categories from article classes
        $classes = $article->getAttribute('class');
        $cats = [];
        if (preg_match_all('/certification-thematiques-([a-z0-9-]+)/', $classes, $m)) {
            $cats = $m[1];
        }

        // Parse dates
        $dateDebut = null;
        $dateFin = null;
        if (preg_match('/Du\s+(\d{2})\.(\d{2})\.(\d{4})\s+au\s+(\d{2})\.(\d{2})\.(\d{4})/i', $dateText, $dm)) {
            $dateDebut = "$dm[3]-$dm[2]-$dm[1]";
            $dateFin = "$dm[6]-$dm[5]-$dm[4]";
        } elseif (preg_match('/Le\s+(\d{2})\.(\d{2})\.(\d{4})/i', $dateText, $dm)) {
            $dateDebut = "$dm[3]-$dm[2]-$dm[1]";
            $dateFin = $dateDebut;
        }

        $formations[] = [
            'titre' => $titre,
            'url' => $url,
            'image_url' => $imageUrl,
            'date_text' => $dateText,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'modalite' => $modalite,
            'categories' => implode(', ', array_map(function($s) {
                return ucfirst(str_replace('-', ' ', $s));
            }, $cats)),
        ];
    }

    if (empty($formations)) bad_request('Aucune formation trouvée sur le site FEGEMS');

    respond(['success' => true, 'formations' => $formations, 'count' => count($formations)]);
}

function admin_save_imported_formations()
{
    require_admin();
    global $params;

    $items = $params['formations'] ?? [];
    if (empty($items) || !is_array($items)) bad_request('Aucune formation à importer');

    $imported = 0;
    foreach ($items as $f) {
        $titre = Sanitize::text($f['titre'] ?? '', 255);
        if (!$titre) continue;

        // Skip if already imported (same source_url)
        $url = $f['url'] ?? '';
        if ($url) {
            $existing = Db::fetch("SELECT id FROM formations WHERE source_url = ?", [$url]);
            if ($existing) continue;
        }

        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO formations (id, titre, description, type, formateur, lieu, date_debut, date_fin, modalite, categorie, source_url, image_url, statut, created_by)
             VALUES (?, ?, ?, 'externe', ?, ?, ?, ?, ?, ?, ?, ?, 'planifiee', ?)",
            [
                $id, $titre,
                Sanitize::text($f['description'] ?? '', 10000),
                Sanitize::text($f['formateur'] ?? '', 255),
                Sanitize::text($f['lieu'] ?? '', 255),
                Sanitize::date($f['date_debut'] ?? '') ?: null,
                Sanitize::date($f['date_fin'] ?? '') ?: null,
                Sanitize::text($f['modalite'] ?? '', 100),
                Sanitize::text($f['categories'] ?? $f['categorie'] ?? '', 255),
                $url ?: null,
                $f['image_url'] ?? null,
                $_SESSION['ss_user']['id'],
            ]
        );
        $imported++;
    }

    respond(['success' => true, 'message' => "$imported formation(s) importée(s)", 'imported' => $imported]);
}

function admin_import_formations_file()
{
    require_admin();

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Fichier manquant');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'txt'])) {
        bad_request('Format non supporté. Utilisez un fichier CSV (séparateur ; ou ,).');
    }

    $content = file_get_contents($file['tmp_name']);
    $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
    if (count($lines) < 2) bad_request('Fichier vide ou incomplet');

    $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
    $header = str_getcsv(array_shift($lines), $sep);
    $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

    $formations = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $row = str_getcsv($line, $sep);

        $titre = trim($row[$headerMap['titre'] ?? $headerMap['title'] ?? $headerMap['nom'] ?? 0] ?? '');
        if (!$titre) continue;

        $formations[] = [
            'titre' => $titre,
            'description' => trim($row[$headerMap['description'] ?? $headerMap['desc'] ?? 999] ?? ''),
            'formateur' => trim($row[$headerMap['formateur'] ?? $headerMap['trainer'] ?? 999] ?? ''),
            'lieu' => trim($row[$headerMap['lieu'] ?? $headerMap['location'] ?? 999] ?? ''),
            'date_debut' => trim($row[$headerMap['date_debut'] ?? $headerMap['date'] ?? 999] ?? ''),
            'date_fin' => trim($row[$headerMap['date_fin'] ?? 999] ?? ''),
            'modalite' => trim($row[$headerMap['modalite'] ?? $headerMap['modalité'] ?? 999] ?? ''),
            'categories' => trim($row[$headerMap['categorie'] ?? $headerMap['catégorie'] ?? $headerMap['category'] ?? 999] ?? ''),
        ];
    }

    if (empty($formations)) bad_request('Aucune formation trouvée dans le fichier');

    respond(['success' => true, 'formations' => $formations, 'count' => count($formations)]);
}
