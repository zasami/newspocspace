<?php

// ─── Liste des événements ───
function admin_get_evenements() {
    global $params;
    $statut = $params['statut'] ?? '';
    $search = trim($params['search'] ?? '');

    $where = '1=1';
    $binds = [];

    if ($statut) {
        $where .= ' AND e.statut = ?';
        $binds[] = $statut;
    }
    if ($search) {
        $where .= ' AND (e.titre LIKE ? OR e.description LIKE ? OR e.lieu LIKE ?)';
        $s = '%' . $search . '%';
        $binds[] = $s;
        $binds[] = $s;
        $binds[] = $s;
    }

    $list = Db::fetchAll(
        "SELECT e.*, u.prenom, u.nom,
                (SELECT COUNT(*) FROM evenement_inscriptions WHERE evenement_id = e.id AND statut = 'inscrit') AS nb_inscrits,
                (SELECT COUNT(*) FROM evenement_champs WHERE evenement_id = e.id) AS nb_champs
         FROM evenements e
         LEFT JOIN users u ON u.id = e.created_by
         WHERE $where
         ORDER BY e.date_debut DESC
         LIMIT 50",
        $binds
    );

    respond(['success' => true, 'list' => $list]);
}

// ─── Détail d'un événement ───
function admin_get_evenement() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ev = Db::fetch("SELECT e.*, u.prenom, u.nom FROM evenements e LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?", [$id]);
    if (!$ev) not_found();

    $champs = Db::fetchAll(
        "SELECT * FROM evenement_champs WHERE evenement_id = ? ORDER BY ordre ASC",
        [$id]
    );
    foreach ($champs as &$c) {
        if ($c['options']) $c['options'] = json_decode($c['options'], true);
    }
    unset($c);

    $inscriptions = Db::fetchAll(
        "SELECT ei.*, u.prenom, u.nom, u.email
         FROM evenement_inscriptions ei
         JOIN users u ON u.id = ei.user_id
         WHERE ei.evenement_id = ?
         ORDER BY ei.created_at ASC",
        [$id]
    );

    // Charger les valeurs des champs pour chaque inscription
    foreach ($inscriptions as &$ins) {
        $ins['valeurs'] = Db::fetchAll(
            "SELECT eiv.champ_id, eiv.valeur, ec.label, ec.type
             FROM evenement_inscription_valeurs eiv
             JOIN evenement_champs ec ON ec.id = eiv.champ_id
             WHERE eiv.inscription_id = ?
             ORDER BY ec.ordre ASC",
            [$ins['id']]
        );
    }
    unset($ins);

    respond([
        'success' => true,
        'evenement' => $ev,
        'champs' => $champs,
        'inscriptions' => $inscriptions,
    ]);
}

// ─── Créer un événement ───
function admin_create_evenement() {
    global $params;
    $titre = trim($params['titre'] ?? '');
    if (!$titre) bad_request('Titre requis');

    $dateDebut = $params['date_debut'] ?? '';
    if (!$dateDebut) bad_request('Date de début requise');

    $id = Uuid::v4();
    $allowedStatuts = ['brouillon', 'ouvert', 'ferme', 'annule'];
    $statut = in_array($params['statut'] ?? '', $allowedStatuts) ? $params['statut'] : 'brouillon';

    Db::exec(
        "INSERT INTO evenements (id, titre, description, date_debut, date_fin, heure_debut, heure_fin, lieu, image_url, max_participants, statut, inscription_obligatoire, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $id,
            $titre,
            $params['description'] ?? null,
            $dateDebut,
            ($params['date_fin'] ?? null) ?: null,
            ($params['heure_debut'] ?? null) ?: null,
            ($params['heure_fin'] ?? null) ?: null,
            $params['lieu'] ?? null,
            $params['image_url'] ?? null,
            !empty($params['max_participants']) ? (int)$params['max_participants'] : null,
            $statut,
            (int)($params['inscription_obligatoire'] ?? 1),
            $_SESSION['ss_user']['id'],
        ]
    );

    // Sauvegarder les champs personnalisés
    if (!empty($params['champs']) && is_array($params['champs'])) {
        _save_champs($id, $params['champs']);
    }

    respond(['success' => true, 'id' => $id, 'message' => 'Événement créé']);
}

// ─── Mettre à jour un événement ───
function admin_update_evenement() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ev = Db::fetch("SELECT * FROM evenements WHERE id = ?", [$id]);
    if (!$ev) not_found();

    $titre = trim($params['titre'] ?? $ev['titre']);
    $allowedStatuts = ['brouillon', 'ouvert', 'ferme', 'annule'];
    $statut = in_array($params['statut'] ?? '', $allowedStatuts) ? $params['statut'] : $ev['statut'];

    Db::exec(
        "UPDATE evenements SET titre=?, description=?, date_debut=?, date_fin=?, heure_debut=?, heure_fin=?, lieu=?, image_url=?, max_participants=?, statut=?, inscription_obligatoire=? WHERE id=?",
        [
            $titre,
            array_key_exists('description', $params) ? $params['description'] : $ev['description'],
            $params['date_debut'] ?? $ev['date_debut'],
            array_key_exists('date_fin', $params) ? ($params['date_fin'] ?: null) : $ev['date_fin'],
            array_key_exists('heure_debut', $params) ? ($params['heure_debut'] ?: null) : $ev['heure_debut'],
            array_key_exists('heure_fin', $params) ? ($params['heure_fin'] ?: null) : $ev['heure_fin'],
            array_key_exists('lieu', $params) ? $params['lieu'] : $ev['lieu'],
            array_key_exists('image_url', $params) ? ($params['image_url'] ?: null) : $ev['image_url'],
            array_key_exists('max_participants', $params) ? ($params['max_participants'] ? (int)$params['max_participants'] : null) : $ev['max_participants'],
            $statut,
            array_key_exists('inscription_obligatoire', $params) ? (int)$params['inscription_obligatoire'] : $ev['inscription_obligatoire'],
            $id,
        ]
    );

    // Rebuild champs
    if (isset($params['champs']) && is_array($params['champs'])) {
        Db::exec("DELETE FROM evenement_champs WHERE evenement_id = ?", [$id]);
        _save_champs($id, $params['champs']);
    }

    respond(['success' => true, 'message' => 'Événement mis à jour']);
}

// ─── Changer le statut ───
function admin_toggle_evenement() {
    global $params;
    $id = $params['id'] ?? '';
    $statut = $params['statut'] ?? '';
    if (!$id) bad_request('ID requis');

    $allowed = ['brouillon', 'ouvert', 'ferme', 'annule'];
    if (!in_array($statut, $allowed)) bad_request('Statut invalide');

    $ev = Db::fetch("SELECT id FROM evenements WHERE id = ?", [$id]);
    if (!$ev) not_found();

    Db::exec("UPDATE evenements SET statut = ? WHERE id = ?", [$statut, $id]);
    respond(['success' => true, 'message' => 'Statut mis à jour']);
}

// ─── Supprimer un événement ───
function admin_delete_evenement() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    Db::exec("DELETE FROM evenements WHERE id = ?", [$id]);
    respond(['success' => true, 'message' => 'Événement supprimé']);
}

// ─── Exporter les inscriptions ───
function admin_export_evenement_inscriptions() {
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $ev = Db::fetch("SELECT titre FROM evenements WHERE id = ?", [$id]);
    if (!$ev) not_found();

    $champs = Db::fetchAll("SELECT id, label FROM evenement_champs WHERE evenement_id = ? ORDER BY ordre ASC", [$id]);

    $inscriptions = Db::fetchAll(
        "SELECT ei.id, u.prenom, u.nom, u.email, ei.created_at, ei.statut
         FROM evenement_inscriptions ei
         JOIN users u ON u.id = ei.user_id
         WHERE ei.evenement_id = ?
         ORDER BY ei.created_at ASC",
        [$id]
    );

    $rows = [];
    foreach ($inscriptions as $ins) {
        $row = [
            'Prénom' => $ins['prenom'],
            'Nom' => $ins['nom'],
            'Email' => $ins['email'],
            'Statut' => $ins['statut'],
            'Inscrit le' => $ins['created_at'],
        ];
        $valeurs = Db::fetchAll(
            "SELECT eiv.champ_id, eiv.valeur FROM evenement_inscription_valeurs eiv WHERE eiv.inscription_id = ?",
            [$ins['id']]
        );
        $valMap = [];
        foreach ($valeurs as $v) $valMap[$v['champ_id']] = $v['valeur'];
        foreach ($champs as $c) {
            $row[$c['label']] = $valMap[$c['id']] ?? '';
        }
        $rows[] = $row;
    }

    respond(['success' => true, 'titre' => $ev['titre'], 'rows' => $rows, 'champs' => array_column($champs, 'label')]);
}

// ─── Upload image événement ───
function admin_upload_evenement_image() {
    require_responsable();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Image manquante');
    }

    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) bad_request('Image trop volumineuse (max 5 Mo)');

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed, true)) bad_request('Type de fichier non autorisé');

    $storageDir = __DIR__ . '/../../assets/uploads/evenements/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde');
    }

    $url = '/spocspace/assets/uploads/evenements/' . $filename;
    respond(['success' => true, 'url' => $url]);
}

// ─── Pixabay → image événement ───
function admin_save_pixabay_evenement() {
    require_responsable();
    global $params;

    $imageUrl = $params['image_url'] ?? '';
    if (!$imageUrl) bad_request('URL manquante');

    $parsed = parse_url($imageUrl);
    if (!$parsed || !preg_match('/pixabay\.(com|net)$/', $parsed['host'] ?? '')) {
        bad_request('Source non autorisée');
    }
    if (($parsed['scheme'] ?? '') !== 'https') bad_request('HTTPS requis');

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true]);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$imgData) bad_request('Téléchargement échoué');

    $storageDir = __DIR__ . '/../../assets/uploads/evenements/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $tmpFile = tempnam(sys_get_temp_dir(), 'pxb_');
    file_put_contents($tmpFile, $imgData);

    $mime = mime_content_type($tmpFile);
    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmpFile),
        'image/png'  => imagecreatefrompng($tmpFile),
        'image/webp' => imagecreatefromwebp($tmpFile),
        default => null,
    };
    unlink($tmpFile);
    if (!$img) bad_request('Format image non supporté');

    $filename = 'ev_' . bin2hex(random_bytes(8)) . '.webp';
    imagewebp($img, $storageDir . $filename, 82);
    imagedestroy($img);

    $url = '/spocspace/assets/uploads/evenements/' . $filename;
    respond(['success' => true, 'url' => $url]);
}

// ─── Helper: sauvegarder les champs ───
function _save_champs(string $evenementId, array $champs): void {
    foreach ($champs as $i => $c) {
        $label = trim($c['label'] ?? '');
        if (!$label) continue;

        $type = $c['type'] ?? 'texte';
        $allowed = ['texte', 'textarea', 'nombre', 'checkbox', 'radio', 'select'];
        if (!in_array($type, $allowed)) $type = 'texte';

        $options = null;
        if (in_array($type, ['radio', 'select', 'checkbox']) && !empty($c['options'])) {
            $opts = is_array($c['options']) ? $c['options'] : array_map('trim', explode("\n", $c['options']));
            $opts = array_values(array_filter($opts, fn($o) => $o !== ''));
            if ($opts) $options = json_encode($opts, JSON_UNESCAPED_UNICODE);
        }

        Db::exec(
            "INSERT INTO evenement_champs (id, evenement_id, label, type, options, obligatoire, ordre)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(),
                $evenementId,
                $label,
                $type,
                $options,
                (int)($c['obligatoire'] ?? 0),
                $i,
            ]
        );
    }
}
