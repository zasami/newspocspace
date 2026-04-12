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

// ── Demo unlock (rate limit reset) ─────────────────────────────────────────
case 'famille_demo_unlock':
    famille_rate_reset();
    respond(['success' => true, 'message' => 'Rate limit réinitialisé']);
    break;

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

    // Determine the password used to wrap the E2EE key (code_acces takes priority, same as admin)
    $keyPassword = $resident['code_acces'] ?: $expectedPwd;

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
        'key_password' => $encKey ? $keyPassword : null,
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
    // Get key_password for E2EE unwrap on session restore
    $csResident = Db::fetch("SELECT code_acces, date_naissance FROM residents WHERE id = ?", [$session['resident_id']]);
    $csKeyPwd = $csResident['code_acces'] ?: ((new DateTime($csResident['date_naissance']))->format('dmY'));
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
        'key_password' => $encKey ? $csKeyPwd : null,
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

    $absPath = realpath(__DIR__ . '/../' . $filePath);
    $allowedBase = realpath(__DIR__ . '/../uploads/');
    if (!$absPath || !$allowedBase || !str_starts_with($absPath, $allowedBase)) {
        not_found('Fichier introuvable');
    }
    if (!file_exists($absPath)) not_found('Fichier introuvable sur le serveur');

    // Serve binary (encrypted content)
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($absPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($absPath);
    exit;

// ── Famille: Réservation repas ─────────────────────────────────────────────

case 'famille_reserver':
    famille_rate_check(); // brute-force protection
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

// ── Recrutement: Offres d'emploi ──────────────────────────────────────────

case 'get_offres':
    $offres = Db::fetchAll(
        "SELECT id, titre, description, type_contrat, taux_activite, departement, lieu,
                date_debut, date_limite, exigences, avantages, salaire_indication
         FROM offres_emploi
         WHERE is_active = 1 AND (date_limite IS NULL OR date_limite >= CURDATE())
         ORDER BY ordre, created_at DESC"
    );
    respond(['success' => true, 'offres' => $offres]);
    break;

case 'submit_candidature':
    famille_rate_check(); // anti-spam: 5 per 15 min per IP
    $offre_id     = trim($input['offre_id'] ?? '');
    $nom          = trim($input['nom'] ?? '');
    $prenom       = trim($input['prenom'] ?? '');
    $email        = trim($input['email'] ?? '');
    $telephone    = trim($input['telephone'] ?? '');
    $date_naissance = trim($input['date_naissance'] ?? '');
    $adresse      = trim($input['adresse'] ?? '');
    $nationalite  = trim($input['nationalite'] ?? '');
    $permis_travail = trim($input['permis_travail'] ?? '');
    $disponibilite = trim($input['disponibilite'] ?? '');
    $motivation   = trim($input['motivation'] ?? '');
    $experience   = trim($input['experience'] ?? '');

    // For multipart/form-data fallback
    if (!$offre_id) $offre_id = trim($_POST['offre_id'] ?? '');
    if (!$nom) $nom = trim($_POST['nom'] ?? '');
    if (!$prenom) $prenom = trim($_POST['prenom'] ?? '');
    if (!$email) $email = trim($_POST['email'] ?? '');
    if (!$telephone) $telephone = trim($_POST['telephone'] ?? '');
    if (!$date_naissance) $date_naissance = trim($_POST['date_naissance'] ?? '');
    if (!$adresse) $adresse = trim($_POST['adresse'] ?? '');
    if (!$nationalite) $nationalite = trim($_POST['nationalite'] ?? '');
    if (!$permis_travail) $permis_travail = trim($_POST['permis_travail'] ?? '');
    if (!$disponibilite) $disponibilite = trim($_POST['disponibilite'] ?? '');
    if (!$motivation) $motivation = trim($_POST['motivation'] ?? '');
    if (!$experience) $experience = trim($_POST['experience'] ?? '');

    if (!$offre_id || !$nom || !$prenom || !$email) {
        respond(['success' => false, 'message' => 'Les champs offre, nom, prénom et email sont requis.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    // Verify offre exists and is active
    $offre = Db::fetch(
        "SELECT id, titre FROM offres_emploi WHERE id = ? AND is_active = 1 AND (date_limite IS NULL OR date_limite >= CURDATE())",
        [$offre_id]
    );
    if (!$offre) {
        respond(['success' => false, 'message' => 'Cette offre n\'est plus disponible.'], 400);
    }

    // Pre-validate all uploaded files BEFORE inserting anything (couche 1 antivirus local).
    $maxSize = 10 * 1024 * 1024; // 10 Mo
    $uploadDir = __DIR__ . '/../storage/candidatures/';

    $validatedFiles = [];
    foreach (['cv', 'lettre_motivation', 'diplome', 'certificat', 'autre'] as $fieldName) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) continue;
        $file = $_FILES[$fieldName];
        if ($file['error'] !== UPLOAD_ERR_OK) continue;

        if ($file['size'] > $maxSize) {
            respond(['success' => false, 'message' => "Le fichier $fieldName dépasse la taille maximale de 10 Mo."], 400);
        }

        // Magic bytes + PDF JS detection + double-extension + null bytes
        $err = FileSecurity::validateUpload($file, $fieldName);
        if ($err) respond(['success' => false, 'message' => $err], 400);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $validatedFiles[] = ['field' => $fieldName, 'file' => $file, 'ext' => $ext];
    }

    $code_suivi = strtoupper(bin2hex(random_bytes(4)));
    $candidature_id = Uuid::v4();

    $pdo = Db::connect();
    $pdo->beginTransaction();
    $movedFiles = [];
    try {
        Db::exec(
            "INSERT INTO candidatures (id, offre_id, nom, prenom, email, telephone, date_naissance, adresse, nationalite, permis_travail, disponibilite, motivation, experience, code_suivi, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'recue')",
            [$candidature_id, $offre_id, $nom, $prenom, $email, $telephone, $date_naissance ?: null, $adresse, $nationalite, $permis_travail, $disponibilite, $motivation, $experience, $code_suivi]
        );

        foreach ($validatedFiles as $vf) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $vf['ext'];
            $destPath = $uploadDir . $storedName;
            if (!move_uploaded_file($vf['file']['tmp_name'], $destPath)) {
                throw new RuntimeException("Erreur lors de l'upload du fichier {$vf['field']}.");
            }
            $movedFiles[] = $destPath;

            // Post-upload sanitize : re-encode images, valide fin PDF
            $sanitizeErr = FileSecurity::sanitizeInPlace($destPath, $vf['ext']);
            if ($sanitizeErr) {
                throw new RuntimeException("Fichier {$vf['field']} rejeté : $sanitizeErr");
            }

            // Couche 2 : VirusTotal hash-only. Ne bloque que sur "malicious".
            // Quota dépassé / réseau indisponible → fallback silencieux sur couche 1.
            $vt = VirusTotal::checkFile($destPath);
            if ($vt['status'] === VirusTotal::STATUS_MALICIOUS) {
                throw new RuntimeException("Fichier {$vf['field']} rejeté (antivirus) : {$vt['message']}");
            }

            $finalSize = filesize($destPath) ?: $vf['file']['size'];

            Db::exec(
                "INSERT INTO candidature_documents (id, candidature_id, type_document, original_name, filename, size, mime_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [Uuid::v4(), $candidature_id, $vf['field'], $vf['file']['name'], $storedName, $finalSize, $vf['file']['type']]
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($movedFiles as $mf) { if (file_exists($mf)) @unlink($mf); }
        respond(['success' => false, 'message' => "Erreur lors de l'enregistrement de la candidature."], 500);
    }

    respond(['success' => true, 'code_suivi' => $code_suivi, 'message' => 'Votre candidature a été soumise avec succès.']);
    break;

case 'track_candidature':
    $email = trim($input['email'] ?? '');
    $code_suivi = trim($input['code_suivi'] ?? '');

    if (!$email || !$code_suivi) {
        respond(['success' => false, 'message' => 'Email et code de suivi requis.'], 400);
    }

    $candidature = Db::fetch(
        "SELECT c.prenom, c.nom, c.statut, c.code_suivi,
                c.created_at AS date_soumission, c.updated_at,
                o.titre AS offre_titre, o.departement AS offre_departement,
                o.type_contrat AS offre_contrat, o.taux_activite AS offre_taux,
                o.date_limite AS offre_date_limite
         FROM candidatures c
         JOIN offres_emploi o ON o.id = c.offre_id
         WHERE c.code_suivi = ? AND c.email = ?",
        [$code_suivi, $email]
    );

    if (!$candidature) {
        respond(['success' => false, 'message' => 'Aucune candidature trouvée avec ces informations.']);
    }

    respond([
        'success' => true,
        'candidature' => $candidature,
    ]);
    break;

// ── Livre d'or ─────────────────────────────────────────────────────────────

case 'livre_or_get_approved':
    $limit = max(1, min(200, (int)($input['limit'] ?? 50)));
    $orderInput = $input['order'] ?? 'desc';
    if ($orderInput === 'random') {
        $orderClause = 'epingle DESC, RAND()';
    } elseif ($orderInput === 'asc') {
        $orderClause = 'epingle DESC, created_at ASC';
    } else {
        $orderClause = 'epingle DESC, created_at DESC';
    }
    $rows = Db::fetchAll(
        "SELECT id, nom, lien_resident, note, titre, message, cible, epingle, created_at
         FROM livre_or
         WHERE statut = 'approuve'
         ORDER BY $orderClause
         LIMIT $limit"
    );
    $stats = Db::fetch(
        "SELECT COUNT(*) AS total, COALESCE(AVG(note), 0) AS moyenne
         FROM livre_or WHERE statut = 'approuve'"
    );
    respond([
        'success' => true,
        'temoignages' => $rows,
        'stats' => [
            'total' => (int)($stats['total'] ?? 0),
            'moyenne' => round((float)($stats['moyenne'] ?? 0), 2),
        ],
    ]);
    break;

case 'livre_or_submit':
    famille_rate_check(); // anti-spam (réutilise le rate-limit famille)

    $nom = Sanitize::text($input['nom'] ?? '', 120);
    $email = trim($input['email'] ?? '');
    $lien = Sanitize::text($input['lien_resident'] ?? '', 200);
    $note = (int)($input['note'] ?? 0);
    $titre = Sanitize::text($input['titre'] ?? '', 200);
    $message = Sanitize::text($input['message'] ?? '', 3000);
    $cible = $input['cible'] ?? 'ems';

    $allowedCibles = ['ems','personnel','prise_en_charge','vie','autre'];
    if (!in_array($cible, $allowedCibles, true)) $cible = 'ems';

    if (!$nom || !$message || $note < 1 || $note > 5) {
        respond(['success' => false, 'message' => 'Nom, note et message sont obligatoires.'], 400);
    }
    if (mb_strlen($message) < 10) {
        respond(['success' => false, 'message' => 'Le message doit contenir au moins 10 caractères.'], 400);
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO livre_or (id, nom, email, lien_resident, note, titre, message, cible, statut, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?)",
        [$id, $nom, $email ?: null, $lien ?: null, $note, $titre ?: null, $message, $cible, $_SERVER['REMOTE_ADDR'] ?? '']
    );

    // Notif admin
    $adminEmail = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'");
    if ($adminEmail && function_exists('mail')) {
        $headers = "From: noreply@ems-la-terrassiere.ch\r\nContent-Type: text/plain; charset=UTF-8";
        $body = "Nouveau témoignage à modérer.\n\nDe: $nom\nNote: $note/5\nCible: $cible\n\n$message";
        @mail($adminEmail, "[Livre d'or] Nouveau témoignage de $nom", $body, $headers);
    }

    respond(['success' => true, 'message' => 'Merci ! Votre témoignage a été envoyé. Il sera publié après modération.']);
    break;

// ── Actualités publiques ───────────────────────────────────────────────────

case 'get_sidebar_affiches':
    $rows = Db::fetchAll(
        "SELECT id, slug, titre, cover_url, published_at
         FROM website_actualites
         WHERE type = 'affiche' AND sidebar_pin = 1 AND is_visible = 1
         ORDER BY COALESCE(published_at, created_at) DESC"
    );
    respond(['success' => true, 'affiches' => $rows]);
    break;

case 'get_actualites':
    $limit = max(1, min(50, (int)($input['limit'] ?? 12)));
    $offset = max(0, (int)($input['offset'] ?? 0));
    $type = $input['type'] ?? null;

    $where = "WHERE is_visible = 1";
    $params = [];
    if ($type && in_array($type, ['photo','video','affiche','texte','galerie'], true)) {
        $where .= " AND type = ?";
        $params[] = $type;
    }

    $total = (int) Db::getOne("SELECT COUNT(*) FROM website_actualites $where", $params);
    $rows = Db::fetchAll(
        "SELECT id, slug, titre, type, extrait, contenu, cover_url, video_url, video_poster, images, epingle, published_at, created_at
         FROM website_actualites
         $where
         ORDER BY epingle DESC, COALESCE(published_at, created_at) DESC
         LIMIT $limit OFFSET $offset",
        $params
    );
    foreach ($rows as &$r) {
        if ($r['images']) {
            $r['images'] = json_decode($r['images'], true) ?: [];
        } else {
            $r['images'] = [];
        }
    }
    respond([
        'success' => true,
        'actualites' => $rows,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
    break;

case 'get_actualite_detail':
    $slug = $input['slug'] ?? '';
    $id = $input['id'] ?? '';
    if (!$slug && !$id) bad_request('Identifiant requis');

    $row = $slug
        ? Db::fetch("SELECT * FROM website_actualites WHERE slug = ? AND is_visible = 1", [$slug])
        : Db::fetch("SELECT * FROM website_actualites WHERE id = ? AND is_visible = 1", [$id]);

    if (!$row) not_found('Actualité introuvable');
    $row['images'] = $row['images'] ? (json_decode($row['images'], true) ?: []) : [];
    respond(['success' => true, 'actualite' => $row]);
    break;

case 'get_activites_venir':
    $limit = max(1, min(20, (int)($input['limit'] ?? 5)));
    $rows = Db::fetchAll(
        "SELECT id, titre, description, date_activite, heure_debut, heure_fin, lieu, image_url, icone, couleur
         FROM website_activites_venir
         WHERE is_visible = 1 AND date_activite >= CURDATE()
         ORDER BY date_activite ASC, heure_debut ASC
         LIMIT $limit"
    );
    respond(['success' => true, 'activites' => $rows]);
    break;

// ── Contact form ──────────────────────────────────────────────────────────

case 'contact_submit':
    famille_rate_check(); // anti-spam

    $nom = Sanitize::text($input['nom'] ?? '', 100);
    $email = Sanitize::email($input['email'] ?? '');
    $sujet = Sanitize::text($input['sujet'] ?? 'general', 100);
    $message = Sanitize::text($input['message'] ?? '', 5000);

    if (!$nom || !$email || !$message) {
        respond(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    // Store in DB
    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO contact_messages (id, nom, email, sujet, message, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $nom, $email, $sujet, $message, $_SERVER['REMOTE_ADDR'] ?? '']
    );

    // Send notification email to admin
    $adminEmail = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_email'");
    if ($adminEmail && function_exists('mail')) {
        $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($adminEmail, "[EMS Contact] $sujet — $nom", "Nom: $nom\nEmail: $email\nSujet: $sujet\n\n$message", $headers);
    }

    respond(['success' => true, 'message' => 'Votre message a été envoyé.']);
    break;

// ── Communication famille ↔ soignants ──────────────────────────────────────

case 'famille_get_messages':
    $session = require_famille_auth();
    $residentId = $session['resident_id'];

    $messages = Db::fetchAll(
        "SELECT id, sender_type, sender_name, message, is_read, created_at
         FROM famille_messages
         WHERE resident_id = ?
         ORDER BY created_at ASC",
        [$residentId]
    );

    // Mark soignant messages as read
    Db::exec(
        "UPDATE famille_messages SET is_read = 1 WHERE resident_id = ? AND sender_type = 'soignant' AND is_read = 0",
        [$residentId]
    );

    respond(['success' => true, 'messages' => $messages]);
    break;

case 'famille_send_message':
    $session = require_famille_auth();
    $residentId = $session['resident_id'];
    $message = trim($input['message'] ?? '');

    if (!$message || mb_strlen($message) > 2000) {
        respond(['success' => false, 'message' => 'Message vide ou trop long (max 2000 caractères).'], 400);
    }

    $resident = Db::fetch("SELECT correspondant_prenom, correspondant_nom FROM residents WHERE id = ?", [$residentId]);
    $senderName = trim(($resident['correspondant_prenom'] ?? '') . ' ' . ($resident['correspondant_nom'] ?? '')) ?: $session['correspondant_email'];
    $id = Uuid::v4();

    Db::exec(
        "INSERT INTO famille_messages (id, resident_id, sender_type, sender_name, message, created_at) VALUES (?, ?, 'famille', ?, ?, NOW())",
        [$id, $residentId, $senderName, $message]
    );

    respond([
        'success' => true,
        'message_obj' => [
            'id' => $id,
            'sender_type' => 'famille',
            'sender_name' => $senderName,
            'message' => $message,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    ]);
    break;

case 'famille_get_unread_count':
    $session = require_famille_auth();
    $count = (int) Db::getOne(
        "SELECT COUNT(*) FROM famille_messages WHERE resident_id = ? AND sender_type = 'soignant' AND is_read = 0",
        [$session['resident_id']]
    );
    respond(['success' => true, 'count' => $count]);
    break;

default:
    respond(['success' => false, 'message' => 'Action inconnue'], 400);
}
