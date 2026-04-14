<?php
/**
 * Annuaire — Admin API (CRUD + import CSV)
 */

function admin_get_annuaire()
{
    require_admin();
    global $params;
    $type = $params['type'] ?? null;

    $sql = "SELECT * FROM annuaire WHERE is_active = 1";
    $args = [];
    if ($type && in_array($type, ['interne', 'externe', 'urgence'])) {
        $sql .= " AND type = ?";
        $args[] = $type;
    }
    $sql .= " ORDER BY is_favori DESC, ordre ASC, nom ASC, prenom ASC";

    $rows = Db::fetchAll($sql, $args);
    respond(['success' => true, 'data' => $rows]);
}

function admin_search_annuaire()
{
    require_admin();
    global $params;
    $q = trim($params['q'] ?? '');
    if (strlen($q) < 2) {
        respond(['success' => true, 'data' => []]);
    }
    $like = '%' . $q . '%';
    $rows = Db::fetchAll(
        "SELECT * FROM annuaire WHERE is_active = 1
         AND (nom LIKE ? OR prenom LIKE ? OR fonction LIKE ? OR service LIKE ?
              OR telephone_1 LIKE ? OR telephone_2 LIKE ? OR email LIKE ? OR categorie LIKE ?)
         ORDER BY is_favori DESC, nom ASC LIMIT 50",
        [$like, $like, $like, $like, $like, $like, $like, $like]
    );
    respond(['success' => true, 'data' => $rows]);
}

function admin_save_annuaire()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;

    $id = $params['id'] ?? '';
    $type = $params['type'] ?? 'externe';
    if (!in_array($type, ['interne', 'externe', 'urgence'])) {
        bad_request('Type invalide');
    }

    $estOrg = !empty($params['est_organisation']) ? 1 : 0;
    $data = [
        'type'             => $type,
        'est_organisation' => $estOrg,
        'categorie'        => Sanitize::text($params['categorie'] ?? '', 50),
        'nom'              => Sanitize::text($params['nom'] ?? '', 100),
        'prenom'           => $estOrg ? '' : Sanitize::text($params['prenom'] ?? '', 50),
        'fonction'         => Sanitize::text($params['fonction'] ?? '', 100),
        'service'          => Sanitize::text($params['service'] ?? '', 100),
        'telephone_1'      => Sanitize::text($params['telephone_1'] ?? '', 30),
        'telephone_2'      => Sanitize::text($params['telephone_2'] ?? '', 30),
        'email'            => Sanitize::email($params['email'] ?? ''),
        'adresse'          => Sanitize::text($params['adresse'] ?? '', 500),
        'notes'            => Sanitize::text($params['notes'] ?? '', 1000),
        'ordre'            => (int) ($params['ordre'] ?? 100),
        'is_favori'        => !empty($params['is_favori']) ? 1 : 0,
    ];

    if (empty($data['nom'])) bad_request($estOrg ? 'Le nom de l\'organisation est requis' : 'Le nom est requis');

    if ($id) {
        Db::exec(
            "UPDATE annuaire SET type=?, est_organisation=?, categorie=?, nom=?, prenom=?, fonction=?, service=?,
             telephone_1=?, telephone_2=?, email=?, adresse=?, notes=?, ordre=?, is_favori=?
             WHERE id = ?",
            [...array_values($data), $id]
        );
        respond(['success' => true, 'id' => $id, 'message' => 'Contact mis à jour']);
    } else {
        $id = Uuid::v4();
        Db::exec(
            "INSERT INTO annuaire (id, type, est_organisation, categorie, nom, prenom, fonction, service,
             telephone_1, telephone_2, email, adresse, notes, ordre, is_favori, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, ...array_values($data), $admin['id'] ?? null]
        );
        respond(['success' => true, 'id' => $id, 'message' => 'Contact ajouté']);
    }
}

function admin_delete_annuaire()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("UPDATE annuaire SET is_active = 0 WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Contact supprimé']);
}

function admin_toggle_favori_annuaire()
{
    require_admin();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("UPDATE annuaire SET is_favori = 1 - is_favori WHERE id = ?", [$id]);
    respond(['success' => true]);
}

/**
 * Import CSV — format: type;categorie;nom;prenom;fonction;service;telephone_1;telephone_2;email;adresse;notes
 * Première ligne = entêtes (ignorée)
 */
function admin_import_annuaire_csv()
{
    require_admin();
    global $params;
    $admin = $_SESSION['admin'] ?? null;

    $csvText = $params['csv'] ?? '';
    $delimiter = $params['delimiter'] ?? ';';
    $skipHeader = !empty($params['skip_header']);

    if (!$csvText) bad_request('Contenu CSV manquant');

    // Parse
    $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
    if ($skipHeader) array_shift($lines);

    $inserted = 0;
    $errors = [];

    foreach ($lines as $i => $line) {
        if (!trim($line)) continue;
        $cols = str_getcsv($line, $delimiter);

        $type = strtolower(trim($cols[0] ?? 'externe'));
        if (!in_array($type, ['interne', 'externe', 'urgence'])) $type = 'externe';

        $nom = trim($cols[2] ?? '');
        if (!$nom) {
            $errors[] = 'Ligne ' . ($i + 1) . ' : nom manquant';
            continue;
        }

        // Optional 12th column "organisation" (1/oui/o/yes = organisation)
        $orgRaw = strtolower(trim($cols[11] ?? ''));
        $estOrg = in_array($orgRaw, ['1', 'oui', 'o', 'yes', 'org', 'organisation', 'societe', 'société']) ? 1 : 0;

        try {
            Db::exec(
                "INSERT INTO annuaire (id, type, est_organisation, categorie, nom, prenom, fonction, service,
                 telephone_1, telephone_2, email, adresse, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Uuid::v4(), $type, $estOrg,
                    trim($cols[1] ?? ''),
                    $nom,
                    $estOrg ? '' : trim($cols[3] ?? ''),
                    trim($cols[4] ?? ''),
                    trim($cols[5] ?? ''),
                    trim($cols[6] ?? ''),
                    trim($cols[7] ?? ''),
                    trim($cols[8] ?? ''),
                    trim($cols[9] ?? ''),
                    trim($cols[10] ?? ''),
                    $admin['id'] ?? null,
                ]
            );
            $inserted++;
        } catch (Exception $e) {
            $errors[] = 'Ligne ' . ($i + 1) . ' : ' . $e->getMessage();
        }
    }

    respond([
        'success' => true,
        'inserted' => $inserted,
        'errors' => $errors,
        'message' => "$inserted contact(s) importé(s)" . (count($errors) ? ' — ' . count($errors) . ' erreur(s)' : ''),
    ]);
}

function admin_export_annuaire_csv()
{
    require_admin();
    $rows = Db::fetchAll("SELECT * FROM annuaire WHERE is_active = 1 ORDER BY type, nom");
    $csv = "type;categorie;nom;prenom;fonction;service;telephone_1;telephone_2;email;adresse;notes;organisation\n";
    foreach ($rows as $r) {
        $csv .= implode(';', array_map(fn($v) => str_replace(["\n", ";", "\r"], [' ', ',', ''], (string)$v), [
            $r['type'], $r['categorie'], $r['nom'], $r['prenom'], $r['fonction'], $r['service'],
            $r['telephone_1'], $r['telephone_2'], $r['email'], $r['adresse'], $r['notes'],
            $r['est_organisation'] ? '1' : '0',
        ])) . "\n";
    }
    respond(['success' => true, 'csv' => $csv, 'count' => count($rows)]);
}
