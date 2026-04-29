<?php
/**
 * Annonces officielles — API admin
 * Communication descendante direction → personnel
 */

function _annonce_slugify(string $str): string
{
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[àáâãäå]/u', 'a', $str);
    $str = preg_replace('/[èéêë]/u', 'e', $str);
    $str = preg_replace('/[ìíîï]/u', 'i', $str);
    $str = preg_replace('/[òóôõö]/u', 'o', $str);
    $str = preg_replace('/[ùúûü]/u', 'u', $str);
    $str = preg_replace('/[ç]/u', 'c', $str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

function admin_get_annonces()
{
    require_auth();
    global $params;

    $search = Sanitize::text($params['search'] ?? '', 200);
    $categorie = $params['categorie'] ?? '';
    $showArchived = !empty($params['show_archived']);

    $where = $showArchived ? ['1=1'] : ['a.archived_at IS NULL'];
    $binds = [];

    if ($categorie) {
        $where[] = 'a.categorie = ?';
        $binds[] = $categorie;
    }
    if ($search) {
        $where[] = '(a.titre LIKE ? OR a.description LIKE ? OR a.contenu LIKE ?)';
        $s = "%$search%";
        $binds[] = $s;
        $binds[] = $s;
        $binds[] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $annonces = Db::fetchAll(
        "SELECT a.id, a.titre, a.slug, a.description, a.image_url, a.categorie,
                a.epingle, a.visible, a.requires_ack, a.ack_target_role,
                a.published_at, a.created_at, a.updated_at,
                cr.prenom AS auteur_prenom, cr.nom AS auteur_nom,
                (SELECT COUNT(*) FROM annonce_acks WHERE annonce_id = a.id) AS ack_count
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE $whereSql
         ORDER BY a.epingle DESC, a.published_at DESC, a.created_at DESC",
        $binds
    );

    respond(['success' => true, 'annonces' => $annonces]);
}

function admin_get_annonce()
{
    require_auth();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $a = Db::fetch(
        "SELECT a.*, cr.prenom AS auteur_prenom, cr.nom AS auteur_nom
         FROM annonces a
         LEFT JOIN users cr ON cr.id = a.created_by
         WHERE a.id = ?",
        [$id]
    );

    if (!$a) not_found('Annonce introuvable');

    // Log view + check ack status for current user
    try {
        $u = $_SESSION['ss_user'] ?? null;
        if ($u && empty($params['no_log'])) {
            Db::exec("INSERT INTO annonce_views (id, annonce_id, user_id) VALUES (?, ?, ?)", [Uuid::v4(), $id, $u['id']]);
            if (!empty($a['requires_ack'])) {
                $a['user_acked'] = (bool)Db::getOne("SELECT 1 FROM annonce_acks WHERE annonce_id = ? AND user_id = ?", [$id, $u['id']]);
            }
        }
    } catch (\Throwable $e) {}

    respond(['success' => true, 'annonce' => $a]);
}

function admin_create_annonce()
{
    $user = require_responsable();
    global $params;

    $titre = Sanitize::text($params['titre'] ?? '', 255);
    if (!$titre) bad_request('Titre requis');

    $slug = _annonce_slugify($titre);
    $base = $slug;
    $i = 1;
    while (Db::getOne("SELECT id FROM annonces WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . $i++;
    }

    $categorie = $params['categorie'] ?? 'direction';
    $validCats = ['direction', 'rh', 'vie_sociale', 'cuisine', 'protocoles', 'securite', 'divers'];
    if (!in_array($categorie, $validCats)) $categorie = 'direction';

    $id = Uuid::v4();
    $requiresAck = !empty($params['requires_ack']) ? 1 : 0;
    $ackRole = $params['ack_target_role'] ?? null;
    if ($ackRole && !in_array($ackRole, ['collaborateur','responsable','direction','admin'])) $ackRole = null;
    Db::exec(
        "INSERT INTO annonces (id, titre, slug, contenu, description, image_url, categorie, requires_ack, ack_target_role, published_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
        [
            $id, $titre, $slug,
            $params['contenu'] ?? '',
            Sanitize::text($params['description'] ?? '', 500),
            Sanitize::text($params['image_url'] ?? '', 500),
            $categorie,
            $requiresAck, $ackRole,
            $user['id'],
        ]
    );

    respond(['success' => true, 'message' => 'Annonce publiée', 'id' => $id]);
}

function admin_update_annonce()
{
    $user = require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    if (!Db::fetch("SELECT id FROM annonces WHERE id = ?", [$id])) not_found('Annonce introuvable');

    $sets = ['updated_by = ?'];
    $binds = [$user['id']];

    if (isset($params['titre'])) {
        $titre = Sanitize::text($params['titre'], 255);
        if ($titre) { $sets[] = 'titre = ?'; $binds[] = $titre; }
    }
    if (isset($params['contenu'])) { $sets[] = 'contenu = ?'; $binds[] = $params['contenu']; }
    if (isset($params['description'])) { $sets[] = 'description = ?'; $binds[] = Sanitize::text($params['description'], 500); }
    if (isset($params['image_url'])) { $sets[] = 'image_url = ?'; $binds[] = Sanitize::text($params['image_url'], 500); }
    if (isset($params['categorie'])) { $sets[] = 'categorie = ?'; $binds[] = $params['categorie']; }
    if (isset($params['visible'])) { $sets[] = 'visible = ?'; $binds[] = (int)$params['visible']; }
    if (isset($params['epingle'])) { $sets[] = 'epingle = ?'; $binds[] = (int)$params['epingle']; }
    if (isset($params['requires_ack'])) { $sets[] = 'requires_ack = ?'; $binds[] = (int)$params['requires_ack']; }
    if (isset($params['ack_target_role'])) {
        $role = $params['ack_target_role'];
        $sets[] = 'ack_target_role = ?';
        $binds[] = in_array($role, ['collaborateur','responsable','direction','admin']) ? $role : null;
    }

    $binds[] = $id;
    Db::exec("UPDATE annonces SET " . implode(', ', $sets) . " WHERE id = ?", $binds);

    respond(['success' => true, 'message' => 'Annonce mise à jour']);
}

function admin_delete_annonce()
{
    require_responsable();
    global $params;

    $id = $params['id'] ?? '';
    $permanent = !empty($params['permanent']);
    if (!$id) bad_request('ID requis');

    if ($permanent) {
        Db::exec("DELETE FROM annonces WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Annonce supprimée définitivement']);
    } else {
        Db::exec(
            "UPDATE annonces SET archived_at = NOW(), visible = 0 WHERE id = ?",
            [$id]
        );
        respond(['success' => true, 'message' => 'Annonce archivée']);
    }
}

function admin_upload_annonce_image()
{
    require_responsable();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bad_request('Image manquante');
    }

    $file = $_FILES['file'];

    require_once __DIR__ . '/../../core/FileSecurity.php';
    $err = FileSecurity::validateUpload($file, 'Annonce', FileSecurity::ALLOW_IMAGE, 5 * 1024 * 1024);
    if ($err) bad_request($err);

    $storageDir = __DIR__ . '/../../assets/uploads/annonces/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, FileSecurity::ALLOW_IMAGE, true)) bad_request('Extension non autorisée');
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $storageDir . $filename)) {
        bad_request('Erreur lors de la sauvegarde');
    }
    $sanErr = FileSecurity::sanitizeInPlace($storageDir . $filename, $ext);
    if ($sanErr) { @unlink($storageDir . $filename); bad_request($sanErr); }

    $url = '/newspocspace/assets/uploads/annonces/' . $filename;
    respond(['success' => true, 'url' => $url]);
}

function admin_save_pixabay_annonce()
{
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

    $storageDir = __DIR__ . '/../../assets/uploads/annonces/';
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

    $filename = 'ann_' . bin2hex(random_bytes(8)) . '.webp';
    imagewebp($img, $storageDir . $filename, 82);
    imagedestroy($img);

    $url = '/newspocspace/assets/uploads/annonces/' . $filename;
    respond(['success' => true, 'url' => $url]);
}

/* ── Phase 3 : Read receipts ─────────────────────────── */

function admin_ack_annonce()
{
    $u = require_auth();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');
    $a = Db::fetch("SELECT id, requires_ack FROM annonces WHERE id = ?", [$id]);
    if (!$a) not_found('Annonce introuvable');
    if (empty($a['requires_ack'])) bad_request('Cette annonce ne nécessite pas d\'accusé');

    Db::exec(
        "INSERT IGNORE INTO annonce_acks (annonce_id, user_id) VALUES (?, ?)",
        [$id, $u['id']]
    );
    respond(['success' => true, 'message' => 'Lecture confirmée']);
}

function admin_get_annonce_acks()
{
    require_responsable();
    global $params;
    $id = $params['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $a = Db::fetch("SELECT id, titre, requires_ack, ack_target_role FROM annonces WHERE id = ?", [$id]);
    if (!$a) not_found('Annonce introuvable');

    $where = ['is_active = 1'];
    $binds = [];
    if (!empty($a['ack_target_role'])) {
        $where[] = 'role = ?';
        $binds[] = $a['ack_target_role'];
    }
    $whereSql = implode(' AND ', $where);
    $totalTarget = (int)Db::getOne("SELECT COUNT(*) FROM users WHERE $whereSql", $binds);

    $acked = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.role, k.acked_at
         FROM annonce_acks k JOIN users u ON u.id = k.user_id
         WHERE k.annonce_id = ?
         ORDER BY k.acked_at DESC",
        [$id]
    );

    $ackedIds = array_column($acked, 'id');
    $missingWhere = $where;
    $missingBinds = $binds;
    if ($ackedIds) {
        $ph = implode(',', array_fill(0, count($ackedIds), '?'));
        $missingWhere[] = "id NOT IN ($ph)";
        $missingBinds = array_merge($missingBinds, $ackedIds);
    }
    $missingSql = implode(' AND ', $missingWhere);
    $missing = Db::fetchAll("SELECT id, prenom, nom, role FROM users WHERE $missingSql ORDER BY nom, prenom", $missingBinds);

    respond([
        'success' => true,
        'annonce' => $a,
        'total_target' => $totalTarget,
        'total_acked' => count($acked),
        'acked' => $acked,
        'missing' => $missing,
    ]);
}

