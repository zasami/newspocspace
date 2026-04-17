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
    $admin = require_admin();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    // Fetch full candidature info + offre titre BEFORE deletion (for email)
    $candidature = Db::fetch(
        "SELECT c.id, c.prenom, c.nom, c.email, c.code_suivi, o.titre AS offre_titre
         FROM candidatures c
         LEFT JOIN offres_emploi o ON o.id = c.offre_id
         WHERE c.id = ?",
        [$id]
    );
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

    // Send LPD notification email via EmailTemplate (non-critical)
    $emailSent = false;
    if (!empty($candidature['email'])) {
        require_once __DIR__ . '/../../core/EmailTemplate.php';
        $emailSent = EmailTemplate::send('candidature_deleted', $candidature['email'], [
            'prenom' => $candidature['prenom'] ?? '',
            'nom' => $candidature['nom'] ?? '',
            'email' => $candidature['email'],
            'offre_titre' => $candidature['offre_titre'] ?? '',
            'code_suivi' => $candidature['code_suivi'] ?? '',
        ], $admin['id']);
    }

    respond([
        'success' => true,
        'message' => $emailSent
            ? 'Candidature supprimée et candidat notifié par email (nLPD)'
            : 'Candidature supprimée',
        'email_sent' => $emailSent,
    ]);
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
    $skipped = 0;
    foreach ($items as $f) {
        $titre = Sanitize::text($f['titre'] ?? '', 255);
        if (!$titre) continue;

        // Skip if already imported (same source_url)
        $url = $f['url'] ?? '';
        if ($url) {
            $existing = Db::fetch("SELECT id FROM formations WHERE source_url = ?", [$url]);
            if ($existing) { $skipped++; continue; }
        }

        // Scrape detail page for full info
        $detail = $url ? _scrape_fegems_detail($url) : [];

        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO formations (id, titre, description, type, formateur, lieu, date_debut, date_fin,
                modalite, categorie, source_url, image_url, objectifs, public_cible, intervenants,
                tarif_membres, tarif_non_membres, tarif_externes, sessions, date_cloture_inscription,
                places_restantes, info_complementaire, statut, created_by)
             VALUES (?, ?, ?, 'externe', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'planifiee', ?)",
            [
                $id, $titre,
                Sanitize::text($detail['descriptif'] ?? $f['description'] ?? '', 10000),
                Sanitize::text($detail['intervenants'] ?? $f['formateur'] ?? '', 255),
                Sanitize::text($detail['lieu'] ?? $f['lieu'] ?? '', 255),
                Sanitize::date($f['date_debut'] ?? '') ?: null,
                Sanitize::date($f['date_fin'] ?? '') ?: null,
                Sanitize::text($f['modalite'] ?? '', 100),
                Sanitize::text($f['categories'] ?? $f['categorie'] ?? '', 255),
                $url ?: null,
                $f['image_url'] ?? null,
                Sanitize::text($detail['objectifs'] ?? '', 10000),
                Sanitize::text($detail['public_cible'] ?? '', 500),
                Sanitize::text($detail['intervenants'] ?? '', 500),
                Sanitize::text($detail['tarif_membres'] ?? '', 100),
                Sanitize::text($detail['tarif_non_membres'] ?? '', 100),
                Sanitize::text($detail['tarif_externes'] ?? '', 100),
                Sanitize::text($detail['sessions'] ?? '', 10000),
                Sanitize::date($detail['date_cloture'] ?? '') ?: null,
                Sanitize::text($detail['places_restantes'] ?? '', 50),
                Sanitize::text($detail['info_complementaire'] ?? '', 10000),
                $_SESSION['ss_user']['id'],
            ]
        );
        $imported++;
    }

    $msg = "$imported formation(s) importée(s)";
    if ($skipped) $msg .= " ($skipped déjà existante(s))";
    respond(['success' => true, 'message' => $msg, 'imported' => $imported, 'skipped' => $skipped]);
}

/**
 * Scrape a single FEGEMS formation detail page
 */
function _scrape_fegems_detail(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $html = @file_get_contents($url, false, $ctx);
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);

    $result = [];

    // Extract sections by h3.module-title headings
    // Structure: h3 is inside div.module-text. Content can be:
    //   a) sibling elements of h3 within the same parent div
    //   b) the next sibling div.module of the parent div (when heading is alone)
    $headings = $xpath->query("//h3[contains(@class, 'module-title')]");
    foreach ($headings as $h3) {
        $label = mb_strtolower(trim($h3->textContent));

        // Strategy A: content in sibling elements after h3 within same parent
        $parts = [];
        $node = $h3->nextSibling;
        while ($node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $txt = trim($node->textContent);
                if ($txt) $parts[] = $txt;
            }
            $node = $node->nextSibling;
        }
        $content = implode("\n", $parts);

        // Strategy B: next sibling module of the parent div
        if (!$content) {
            $parentMod = $h3->parentNode;
            $nextMod = $parentMod->nextSibling;
            while ($nextMod && $nextMod->nodeType !== XML_ELEMENT_NODE) $nextMod = $nextMod->nextSibling;
            if ($nextMod) {
                $content = trim($nextMod->textContent);
            }
        }

        // Strategy C: parent's full text minus the heading text
        if (!$content) {
            $parent = $h3->parentNode;
            $full = trim($parent->textContent);
            $heading = trim($h3->textContent);
            $content = trim(mb_substr($full, mb_strlen($heading)));
        }

        if (preg_match('/objectif/u', $label)) {
            $result['objectifs'] = $content;
        } elseif (preg_match('/descriptif/u', $label)) {
            $result['descriptif'] = $content;
        } elseif (preg_match('/public/u', $label)) {
            $result['public_cible'] = $content;
        } elseif (preg_match('/intervenant/u', $label)) {
            $result['intervenants'] = $content;
        } elseif (preg_match('/tarif/', $label)) {
            // Parse tariffs — use [\d\'\.,\-\s]+ to catch "0.-" and "1'832.00"
            $result['tarifs_raw'] = $content;
            if (preg_match('/(?<!non[- ])membres?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $content, $m)) {
                $result['tarif_membres'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
            }
            if (preg_match('/non[- ]membres?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $content, $m)) {
                $result['tarif_non_membres'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
            }
            if (preg_match('/externes?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $content, $m)) {
                $result['tarif_externes'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
            }
        } elseif (preg_match('/date\s*(et\s*heure|&|\/\s*h)/iu', $label) || preg_match('/horaire/u', $label)) {
            // Sessions: collect ALL sibling modules until next heading
            $parentMod = $h3->parentNode;
            $nextNode = $parentMod->nextSibling;
            $sessionLines = [];
            while ($nextNode) {
                if ($nextNode->nodeType === XML_ELEMENT_NODE) {
                    $innerH3 = $xpath->query(".//h3[contains(@class, 'module-title')]", $nextNode);
                    if ($innerH3->length > 0) break;
                    $t = preg_replace('/\s+/', ' ', trim($nextNode->textContent));
                    if ($t) $sessionLines[] = preg_replace('/\s*,\s*/', ', ', $t);
                }
                $nextNode = $nextNode->nextSibling;
            }
            $result['sessions'] = implode("\n", $sessionLines) ?: $content;
        } elseif (preg_match('/lieu/u', $label)) {
            $result['lieu'] = preg_replace('/\s+/', ' ', $content);
        } elseif (preg_match('/information|info/iu', $label) && !preg_match('/cl[oô]ture/iu', $label)) {
            $result['info_complementaire'] = $content;
        } elseif (preg_match('/cl[oô]ture/u', $label)) {
            $result['date_cloture_raw'] = $content;
        }
    }

    // Try alternative: look for all module-text divs and match by content
    $textModules = $xpath->query("//div[contains(@class, 'module-text')]");
    foreach ($textModules as $mod) {
        $text = trim($mod->textContent);

        // Date de clôture — often a standalone line
        if (preg_match('/cl[oô]ture.*?(\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{4})/i', $text, $m)) {
            $result['date_cloture_raw'] = $m[1];
        }
        // Places restantes
        if (preg_match('/(\d+)\s*places?\s*restantes?/i', $text, $m)) {
            $result['places_restantes'] = $m[1] . ' places';
        }

        // Tarifs block — fallback parsing from text modules
        if (!isset($result['tarif_membres']) && preg_match('/(?<!non[- ])membres?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $text, $m)) {
            $result['tarif_membres'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
        }
        if (!isset($result['tarif_non_membres']) && preg_match('/non[- ]membres?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $text, $m)) {
            $result['tarif_non_membres'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
        }
        if (!isset($result['tarif_externes']) && preg_match('/externes?\s*:?\s*((?:CHF\s*)?[\d\'\.\,]+[\.\-]*)/i', $text, $m)) {
            $result['tarif_externes'] = 'CHF ' . trim(preg_replace('/^CHF\s*/i', '', $m[1]));
        }
    }

    // Parse date cloture to Y-m-d
    if (!empty($result['date_cloture_raw'])) {
        $raw = $result['date_cloture_raw'];
        if (preg_match('/(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})/', $raw, $dm)) {
            $result['date_cloture'] = "$dm[3]-$dm[2]-$dm[1]";
        }
    }

    // Featured image (larger)
    $featImg = $xpath->query("//div[contains(@class, 'post-image')]//img")->item(0);
    if ($featImg) {
        $result['image_large'] = $featImg->getAttribute('src');
    }

    return $result;
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
