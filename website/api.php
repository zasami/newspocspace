<?php
/**
 * Public Website API — Menus + Espace Famille
 */
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_POST['action'] ?? ($_GET['action'] ?? ''));

// ═══════════════════════════════════════════════════════════════════════════════
// Helper: famille auth via token
// ═══════════════════════════════════════════════════════════════════════════════

function require_famille_auth(): array {
    $token = $_SERVER['HTTP_X_FAMILLE_TOKEN'] ?? ($_GET['token'] ?? '');
    if (!$token) respond(['success' => false, 'message' => 'Token requis'], 401);

    $session = Db::fetch(
        "SELECT fs.*, r.nom AS resident_nom, r.prenom AS resident_prenom, r.chambre, r.etage, r.photo_url AS resident_photo
         FROM famille_sessions fs
         JOIN residents r ON r.id = fs.resident_id
         WHERE fs.token = ? AND fs.expires_at > NOW()",
        [$token]
    );
    if (!$session) respond(['success' => false, 'message' => 'Session expirée'], 401);

    return $session;
}

function famille_rate_check(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $row = Db::fetch("SELECT attempts, last_attempt FROM famille_rate_limits WHERE ip = ?", [$ip]);
    if ($row) {
        $diff = time() - strtotime($row['last_attempt']);
        if ($diff < 900 && $row['attempts'] >= 5) {
            respond(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans 15 minutes.'], 429);
        }
        if ($diff > 900) {
            Db::exec("UPDATE famille_rate_limits SET attempts = 1, last_attempt = NOW() WHERE ip = ?", [$ip]);
        } else {
            Db::exec("UPDATE famille_rate_limits SET attempts = attempts + 1, last_attempt = NOW() WHERE ip = ?", [$ip]);
        }
    } else {
        Db::exec("INSERT INTO famille_rate_limits (ip, attempts, last_attempt) VALUES (?, 1, NOW())", [$ip]);
    }
}

function famille_rate_reset(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    Db::exec("DELETE FROM famille_rate_limits WHERE ip = ?", [$ip]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Routes
// ═══════════════════════════════════════════════════════════════════════════════

switch ($action) {

// ── Menus publics ───────────────────────────────────────────────────────────

case 'get_menus_semaine':
    $dateRef = $input['date'] ?? date('Y-m-d');
    $dt = new DateTime($dateRef);
    $dow = (int) $dt->format('N');
    $monday = (clone $dt)->modify('-' . ($dow - 1) . ' days');
    $sunday = (clone $monday)->modify('+6 days');

    $menus = Db::fetchAll(
        "SELECT id, date_jour, repas, entree, plat, salade, accompagnement, dessert, remarques
         FROM menus WHERE date_jour BETWEEN ? AND ?
         ORDER BY date_jour ASC, repas ASC",
        [$monday->format('Y-m-d'), $sunday->format('Y-m-d')]
    );

    respond(['success' => true, 'menus' => $menus,
        'semaine_debut' => $monday->format('Y-m-d'),
        'semaine_fin' => $sunday->format('Y-m-d')]);
    break;

case 'get_menus_last_update':
    $ts = Db::getOne("SELECT MAX(updated_at) FROM menus");
    respond(['success' => true, 'last_update' => $ts ?: '']);
    break;

// ── Famille: Login ─────────────────────────────────────────────────────────

case 'famille_login':
    famille_rate_check();

    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) {
        respond(['success' => false, 'message' => 'Email et code d\'accès requis'], 400);
    }

    $resident = Db::fetch(
        "SELECT id, nom, prenom, chambre, etage, date_naissance, code_acces,
                correspondant_nom, correspondant_prenom, correspondant_email, photo_url
         FROM residents
         WHERE correspondant_email = ? AND is_active = 1",
        [$email]
    );

    if (!$resident) {
        respond(['success' => false, 'message' => 'Aucun résident associé à cet email.']);
    }

    $expectedPwd = $resident['date_naissance']
        ? (new DateTime($resident['date_naissance']))->format('dmY')
        : '';

    if ($password !== $resident['code_acces'] && $password !== $expectedPwd) {
        respond(['success' => false, 'message' => 'Code d\'accès incorrect']);
    }

    // Rate limit reset on success
    famille_rate_reset();

    // Create session token
    $token = bin2hex(random_bytes(48));
    $sessionId = Uuid::v4();
    Db::exec(
        "INSERT INTO famille_sessions (id, token, correspondant_email, resident_id, ip_address, expires_at)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))",
        [$sessionId, $token, $email, $resident['id'], $_SERVER['REMOTE_ADDR'] ?? '']
    );

    // Clean expired sessions
    Db::exec("DELETE FROM famille_sessions WHERE expires_at < NOW()");

    // Get encryption key info (for client-side unwrap)
    $encKey = Db::fetch(
        "SELECT encrypted_key, salt, iv FROM famille_encryption_keys WHERE resident_id = ?",
        [$resident['id']]
    );

    respond([
        'success' => true,
        'token' => $token,
        'resident' => [
            'id' => $resident['id'],
            'nom' => $resident['nom'],
            'prenom' => $resident['prenom'],
            'chambre' => $resident['chambre'],
            'etage' => $resident['etage'],
            'correspondant_nom' => $resident['correspondant_nom'],
            'correspondant_prenom' => $resident['correspondant_prenom'],
            'photo_url' => $resident['photo_url'] ?? null,
        ],
        'encryption_key' => $encKey ?: null,
    ]);
    break;

case 'famille_logout':
    $token = $_SERVER['HTTP_X_FAMILLE_TOKEN'] ?? '';
    if ($token) {
        Db::exec("DELETE FROM famille_sessions WHERE token = ?", [$token]);
    }
    respond(['success' => true]);
    break;

case 'famille_check_session':
    $session = require_famille_auth();
    $encKey = Db::fetch(
        "SELECT encrypted_key, salt, iv FROM famille_encryption_keys WHERE resident_id = ?",
        [$session['resident_id']]
    );
    respond([
        'success' => true,
        'resident' => [
            'id' => $session['resident_id'],
            'nom' => $session['resident_nom'],
            'prenom' => $session['resident_prenom'],
            'chambre' => $session['chambre'],
            'etage' => $session['etage'],
            'photo_url' => $session['resident_photo'] ?? null,
        ],
        'encryption_key' => $encKey ?: null,
    ]);
    break;

// ── Famille: Dashboard ─────────────────────────────────────────────────────

case 'famille_get_dashboard':
    $session = require_famille_auth();
    $rid = $session['resident_id'];

    $recentActivites = Db::fetchAll(
        "SELECT a.id, a.titre, a.date_activite, a.description,
                (SELECT COUNT(*) FROM famille_activite_photos WHERE activite_id = a.id) AS nb_photos
         FROM famille_activites a WHERE a.resident_id = ?
         ORDER BY a.date_activite DESC LIMIT 5",
        [$rid]
    );

    $recentMedical = Db::fetchAll(
        "SELECT m.id, m.titre, m.date_avis, m.type,
                (SELECT COUNT(*) FROM famille_medical_fichiers WHERE medical_id = m.id) AS nb_fichiers
         FROM famille_medical m WHERE m.resident_id = ?
         ORDER BY m.date_avis DESC LIMIT 5",
        [$rid]
    );

    $recentAlbums = Db::fetchAll(
        "SELECT g.id, g.titre, g.date_galerie, g.annee, g.cover_photo_id,
                (SELECT COUNT(*) FROM famille_galerie_photos WHERE galerie_id = g.id) AS nb_photos
         FROM famille_galerie g WHERE g.resident_id = ?
         ORDER BY g.date_galerie DESC LIMIT 6",
        [$rid]
    );

    // Add cover photo info
    foreach ($recentAlbums as &$album) {
        if ($album['cover_photo_id']) {
            $cover = Db::fetch("SELECT id, encrypted_iv, file_name FROM famille_galerie_photos WHERE id = ?", [$album['cover_photo_id']]);
            $album['cover'] = $cover;
        } else {
            $cover = Db::fetch("SELECT id, encrypted_iv, file_name FROM famille_galerie_photos WHERE galerie_id = ? ORDER BY ordre ASC LIMIT 1", [$album['id']]);
            $album['cover'] = $cover;
        }
    }
    unset($album);

    $stats = [
        'activites' => (int) Db::getOne("SELECT COUNT(*) FROM famille_activites WHERE resident_id = ?", [$rid]),
        'medical' => (int) Db::getOne("SELECT COUNT(*) FROM famille_medical WHERE resident_id = ?", [$rid]),
        'albums' => (int) Db::getOne("SELECT COUNT(*) FROM famille_galerie WHERE resident_id = ?", [$rid]),
        'photos' => (int) Db::getOne(
            "SELECT COUNT(*) FROM famille_galerie_photos gp
             JOIN famille_galerie g ON g.id = gp.galerie_id WHERE g.resident_id = ?",
            [$rid]
        ),
    ];

    respond(['success' => true, 'activites' => $recentActivites, 'medical' => $recentMedical, 'albums' => $recentAlbums, 'stats' => $stats]);
    break;

// ── Famille: Activités ─────────────────────────────────────────────────────

case 'famille_get_activites':
    $session = require_famille_auth();
    $rid = $session['resident_id'];
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $total = (int) Db::getOne("SELECT COUNT(*) FROM famille_activites WHERE resident_id = ?", [$rid]);
    $activites = Db::fetchAll(
        "SELECT a.id, a.titre, a.description, a.date_activite,
                (SELECT COUNT(*) FROM famille_activite_photos WHERE activite_id = a.id) AS nb_photos
         FROM famille_activites a WHERE a.resident_id = ?
         ORDER BY a.date_activite DESC LIMIT ? OFFSET ?",
        [$rid, $limit, $offset]
    );

    respond(['success' => true, 'activites' => $activites, 'total' => $total, 'page' => $page]);
    break;

case 'famille_get_activite_detail':
    $session = require_famille_auth();
    $rid = $session['resident_id'];
    $id = $input['id'] ?? '';

    $activite = Db::fetch(
        "SELECT * FROM famille_activites WHERE id = ? AND resident_id = ?",
        [$id, $rid]
    );
    if (!$activite) not_found('Activité introuvable');

    $photos = Db::fetchAll(
        "SELECT id, file_name, encrypted_iv, ordre FROM famille_activite_photos WHERE activite_id = ? ORDER BY ordre ASC",
        [$id]
    );

    respond(['success' => true, 'activite' => $activite, 'photos' => $photos]);
    break;

// ── Famille: Suivi médical ─────────────────────────────────────────────────

case 'famille_get_medical':
    $session = require_famille_auth();
    $rid = $session['resident_id'];

    $items = Db::fetchAll(
        "SELECT m.id, m.titre, m.date_avis, m.type, m.contenu_chiffre, m.content_iv,
                (SELECT COUNT(*) FROM famille_medical_fichiers WHERE medical_id = m.id) AS nb_fichiers
         FROM famille_medical m WHERE m.resident_id = ?
         ORDER BY m.date_avis DESC",
        [$rid]
    );

    // Get fichiers for each
    foreach ($items as &$item) {
        $item['fichiers'] = Db::fetchAll(
            "SELECT id, file_name, file_type, encrypted_iv, size FROM famille_medical_fichiers WHERE medical_id = ? ORDER BY created_at ASC",
            [$item['id']]
        );
    }
    unset($item);

    respond(['success' => true, 'medical' => $items]);
    break;

// ── Famille: Galerie ───────────────────────────────────────────────────────

case 'famille_get_galerie':
    $session = require_famille_auth();
    $rid = $session['resident_id'];

    $albums = Db::fetchAll(
        "SELECT g.id, g.titre, g.date_galerie, g.annee, g.cover_photo_id,
                (SELECT COUNT(*) FROM famille_galerie_photos WHERE galerie_id = g.id) AS nb_photos
         FROM famille_galerie g WHERE g.resident_id = ?
         ORDER BY g.date_galerie DESC",
        [$rid]
    );

    foreach ($albums as &$album) {
        $coverId = $album['cover_photo_id'];
        if ($coverId) {
            $album['cover'] = Db::fetch("SELECT id, encrypted_iv, file_name FROM famille_galerie_photos WHERE id = ?", [$coverId]);
        } else {
            $album['cover'] = Db::fetch("SELECT id, encrypted_iv, file_name FROM famille_galerie_photos WHERE galerie_id = ? ORDER BY ordre ASC LIMIT 1", [$album['id']]);
        }
    }
    unset($album);

    respond(['success' => true, 'albums' => $albums]);
    break;

case 'famille_get_album_photos':
    $session = require_famille_auth();
    $rid = $session['resident_id'];
    $albumId = $input['album_id'] ?? '';

    // Verify album belongs to this resident
    $album = Db::fetch("SELECT * FROM famille_galerie WHERE id = ? AND resident_id = ?", [$albumId, $rid]);
    if (!$album) not_found('Album introuvable');

    $photos = Db::fetchAll(
        "SELECT id, file_name, encrypted_iv, legende, ordre FROM famille_galerie_photos WHERE galerie_id = ? ORDER BY ordre ASC",
        [$albumId]
    );

    respond(['success' => true, 'album' => $album, 'photos' => $photos]);
    break;

// ── Famille: Serve encrypted file (binary) ─────────────────────────────────

case 'famille_get_file':
    $session = require_famille_auth();
    $rid = $session['resident_id'];
    $fileId = $input['file_id'] ?? ($_GET['file_id'] ?? '');
    $type = $input['type'] ?? ($_GET['type'] ?? ''); // activite_photo, medical_fichier, galerie_photo

    $filePath = null;

    if ($type === 'activite_photo') {
        $row = Db::fetch(
            "SELECT p.file_path FROM famille_activite_photos p
             JOIN famille_activites a ON a.id = p.activite_id
             WHERE p.id = ? AND a.resident_id = ?",
            [$fileId, $rid]
        );
        $filePath = $row['file_path'] ?? null;
    } elseif ($type === 'medical_fichier') {
        $row = Db::fetch(
            "SELECT f.file_path FROM famille_medical_fichiers f
             JOIN famille_medical m ON m.id = f.medical_id
             WHERE f.id = ? AND m.resident_id = ?",
            [$fileId, $rid]
        );
        $filePath = $row['file_path'] ?? null;
    } elseif ($type === 'galerie_photo') {
        $row = Db::fetch(
            "SELECT p.file_path FROM famille_galerie_photos p
             JOIN famille_galerie g ON g.id = p.galerie_id
             WHERE p.id = ? AND g.resident_id = ?",
            [$fileId, $rid]
        );
        $filePath = $row['file_path'] ?? null;
    }

    if (!$filePath) not_found('Fichier introuvable');

    $absPath = __DIR__ . '/../' . $filePath;
    if (!file_exists($absPath)) not_found('Fichier introuvable sur le serveur');

    // Serve binary (encrypted content)
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($absPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($absPath);
    exit;

// ── Famille: Réservation repas ─────────────────────────────────────────────

case 'famille_reserver':
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');
    $residentId = $input['resident_id'] ?? '';
    $dateJour = $input['date_jour'] ?? '';
    $repas = in_array($input['repas'] ?? '', ['midi', 'soir']) ? $input['repas'] : 'midi';
    $nbPersonnes = max(1, min(20, intval($input['nb_personnes'] ?? 1)));
    $remarques = substr(trim($input['remarques'] ?? ''), 0, 500);

    if (!$email || !$password || !$residentId || !$dateJour) {
        respond(['success' => false, 'message' => 'Données incomplètes'], 400);
    }

    $resident = Db::fetch(
        "SELECT id, date_naissance, code_acces, correspondant_nom, correspondant_prenom
         FROM residents WHERE id = ? AND correspondant_email = ? AND is_active = 1",
        [$residentId, $email]
    );
    if (!$resident) respond(['success' => false, 'message' => 'Accès refusé']);

    $expectedPwd = $resident['date_naissance']
        ? (new DateTime($resident['date_naissance']))->format('dmY')
        : '';
    if ($password !== $resident['code_acces'] && $password !== $expectedPwd) {
        respond(['success' => false, 'message' => 'Code d\'accès incorrect']);
    }

    if ($dateJour < date('Y-m-d')) {
        respond(['success' => false, 'message' => 'Impossible de réserver pour une date passée']);
    }

    $existing = Db::fetch(
        "SELECT id FROM reservations_famille
         WHERE resident_id = ? AND date_jour = ? AND repas = ? AND statut = 'confirmee'",
        [$residentId, $dateJour, $repas]
    );
    if ($existing) {
        respond(['success' => false, 'message' => 'Une réservation existe déjà pour ce résident à cette date']);
    }

    $corr = Db::fetch("SELECT id FROM correspondants WHERE email = ?", [$email]);
    if (!$corr) {
        $corrId = Uuid::v4();
        Db::exec(
            "INSERT INTO correspondants (id, email, nom, prenom, resident_id) VALUES (?, ?, ?, ?, ?)",
            [$corrId, $email, $resident['correspondant_nom'] ?? '', $resident['correspondant_prenom'] ?? '', $residentId]
        );
    } else {
        $corrId = $corr['id'];
    }

    $id = Uuid::v4();
    $visiteurNom = trim(($resident['correspondant_prenom'] ?? '') . ' ' . ($resident['correspondant_nom'] ?? ''));
    Db::exec(
        "INSERT INTO reservations_famille (id, date_jour, repas, resident_id, visiteur_nom, nb_personnes, remarques, correspondant_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $dateJour, $repas, $residentId, $visiteurNom, $nbPersonnes, $remarques, $corrId]
    );

    respond(['success' => true, 'message' => 'Réservation confirmée']);
    break;

default:
    respond(['success' => false, 'message' => 'Action inconnue'], 400);
}
