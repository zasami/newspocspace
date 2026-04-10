<?php
/**
 * Website Admin API — Modération Livre d'or
 */
require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');

// Auth
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$userId = $_SESSION['ss_user']['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

// CSRF sur écritures
$readActions = ['list', 'stats'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $readActions)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');
    if (empty($_SESSION['ss_csrf_token']) || !$csrfToken || !hash_equals($_SESSION['ss_csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {

case 'list':
    $statut = $input['statut'] ?? ($_GET['statut'] ?? 'all');
    $where = '';
    $params = [];
    if (in_array($statut, ['en_attente','approuve','rejete'], true)) {
        $where = 'WHERE statut = ?';
        $params[] = $statut;
    }
    $rows = Db::fetchAll(
        "SELECT * FROM livre_or $where ORDER BY epingle DESC, created_at DESC LIMIT 500",
        $params
    );
    $stats = [
        'en_attente' => (int) Db::getOne("SELECT COUNT(*) FROM livre_or WHERE statut='en_attente'"),
        'approuve'   => (int) Db::getOne("SELECT COUNT(*) FROM livre_or WHERE statut='approuve'"),
        'rejete'     => (int) Db::getOne("SELECT COUNT(*) FROM livre_or WHERE statut='rejete'"),
    ];
    echo json_encode(['success' => true, 'temoignages' => $rows, 'stats' => $stats]);
    break;

case 'set_statut':
    $id = $input['id'] ?? '';
    $statut = $input['statut'] ?? '';
    if (!in_array($statut, ['en_attente','approuve','rejete'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        exit;
    }
    Db::exec(
        "UPDATE livre_or SET statut = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?",
        [$statut, $userId, $id]
    );
    echo json_encode(['success' => true]);
    break;

case 'toggle_pin':
    $id = $input['id'] ?? '';
    Db::exec("UPDATE livre_or SET epingle = 1 - epingle WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    break;

case 'delete':
    $id = $input['id'] ?? '';
    Db::exec("DELETE FROM livre_or WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    break;

case 'update':
    $id = $input['id'] ?? '';
    $titre = trim($input['titre'] ?? '');
    $message = trim($input['message'] ?? '');
    $note = (int)($input['note'] ?? 5);
    if (!$id || !$message || $note < 1 || $note > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }
    Db::exec(
        "UPDATE livre_or SET titre = ?, message = ?, note = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?",
        [$titre ?: null, $message, $note, $userId, $id]
    );
    echo json_encode(['success' => true]);
    break;

default:
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
