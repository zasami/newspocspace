<?php
/**
 * Public Website API — Family menu reservations (no auth required)
 */
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
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

    case 'famille_login':
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$email || !$password) {
            respond(['success' => false, 'message' => 'Email et code d\'accès requis'], 400);
        }

        // Find resident by correspondent email
        $resident = Db::fetch(
            "SELECT id, nom, prenom, chambre, etage, date_naissance, code_acces,
                    correspondant_nom, correspondant_prenom, correspondant_email
             FROM residents
             WHERE correspondant_email = ? AND is_active = 1",
            [$email]
        );

        if (!$resident) {
            respond(['success' => false, 'message' => 'Aucun résident associé à cet email. Contactez l\'administration.']);
        }

        // Password = date de naissance DDMMYYYY
        $expectedPwd = $resident['date_naissance']
            ? (new DateTime($resident['date_naissance']))->format('dmY')
            : '';

        if ($password !== $resident['code_acces'] && $password !== $expectedPwd) {
            respond(['success' => false, 'message' => 'Code d\'accès incorrect']);
        }

        respond([
            'success' => true,
            'resident' => [
                'id' => $resident['id'],
                'nom' => $resident['nom'],
                'prenom' => $resident['prenom'],
                'chambre' => $resident['chambre'],
                'etage' => $resident['etage'],
                'correspondant_nom' => $resident['correspondant_nom'],
                'correspondant_prenom' => $resident['correspondant_prenom'],
            ],
        ]);
        break;

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

        // Re-verify auth
        $resident = Db::fetch(
            "SELECT id, date_naissance, code_acces, correspondant_nom, correspondant_prenom
             FROM residents WHERE id = ? AND correspondant_email = ? AND is_active = 1",
            [$residentId, $email]
        );
        if (!$resident) {
            respond(['success' => false, 'message' => 'Accès refusé']);
        }
        $expectedPwd = $resident['date_naissance']
            ? (new DateTime($resident['date_naissance']))->format('dmY')
            : '';
        if ($password !== $resident['code_acces'] && $password !== $expectedPwd) {
            respond(['success' => false, 'message' => 'Code d\'accès incorrect']);
        }

        // Check date is not past
        if ($dateJour < date('Y-m-d')) {
            respond(['success' => false, 'message' => 'Impossible de réserver pour une date passée']);
        }

        // Check no duplicate
        $existing = Db::fetch(
            "SELECT id FROM reservations_famille
             WHERE resident_id = ? AND date_jour = ? AND repas = ? AND statut = 'confirmee'",
            [$residentId, $dateJour, $repas]
        );
        if ($existing) {
            respond(['success' => false, 'message' => 'Une réservation existe déjà pour ce résident à cette date']);
        }

        // Find or create a user for this correspondent
        $corrEmail = $email;
        $corrUser = Db::fetch("SELECT id FROM users WHERE email = ?", [$corrEmail]);
        if (!$corrUser) {
            $corrUserId = Uuid::v4();
            $corrNom = $resident['correspondant_nom'] ?? '';
            $corrPrenom = $resident['correspondant_prenom'] ?? '';
            Db::exec(
                "INSERT INTO users (id, email, password, nom, prenom, role, type_employe, is_active)
                 VALUES (?, ?, ?, ?, ?, 'collaborateur', 'externe', 1)",
                [$corrUserId, $corrEmail, password_hash('famille-' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                 $corrNom, $corrPrenom]
            );
        } else {
            $corrUserId = $corrUser['id'];
        }

        $id = Uuid::v4();
        $visiteurNom = trim(($resident['correspondant_prenom'] ?? '') . ' ' . ($resident['correspondant_nom'] ?? ''));
        Db::exec(
            "INSERT INTO reservations_famille (id, date_jour, repas, resident_id, visiteur_nom, nb_personnes, remarques, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $dateJour, $repas, $residentId, $visiteurNom, $nbPersonnes, $remarques, $corrUserId]
        );

        respond(['success' => true, 'message' => 'Réservation confirmée']);
        break;

    default:
        respond(['success' => false, 'message' => 'Action inconnue'], 400);
}
