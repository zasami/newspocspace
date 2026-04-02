<?php
/**
 * Website Admin API — CRUD sections
 */
require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$userId = $_SESSION['ss_user']['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

switch ($action) {

// ── Liste toutes les sections ──
case 'list':
    $page = $input['page'] ?? 'index';
    $sections = Db::fetchAll(
        "SELECT * FROM website_sections WHERE page = ? ORDER BY sort_order ASC",
        [$page]
    );
    foreach ($sections as &$s) {
        $s['content'] = json_decode($s['content'], true) ?: [];
    }
    unset($s);
    respond(['success' => true, 'sections' => $sections]);
    break;

// ── Récupérer une section ──
case 'get':
    $id = $input['id'] ?? '';
    if (!$id) bad_request('ID requis');
    $section = Db::fetch("SELECT * FROM website_sections WHERE id = ?", [$id]);
    if (!$section) not_found('Section introuvable');
    $section['content'] = json_decode($section['content'], true) ?: [];
    respond(['success' => true, 'section' => $section]);
    break;

// ── Sauvegarder une section ──
case 'save':
    $id = $input['id'] ?? '';
    if (!$id) bad_request('ID requis');

    $section = Db::fetch("SELECT * FROM website_sections WHERE id = ?", [$id]);
    if (!$section) not_found('Section introuvable');

    $fields = [];
    $params = [];

    if (isset($input['title'])) {
        $fields[] = 'title = ?';
        $params[] = $input['title'];
    }
    if (isset($input['subtitle'])) {
        $fields[] = 'subtitle = ?';
        $params[] = $input['subtitle'];
    }
    if (isset($input['badge_icon'])) {
        $fields[] = 'badge_icon = ?';
        $params[] = $input['badge_icon'];
    }
    if (isset($input['badge_text'])) {
        $fields[] = 'badge_text = ?';
        $params[] = $input['badge_text'];
    }
    if (isset($input['content'])) {
        $fields[] = 'content = ?';
        $params[] = json_encode($input['content'], JSON_UNESCAPED_UNICODE);
    }
    if (isset($input['is_visible'])) {
        $fields[] = 'is_visible = ?';
        $params[] = (int) $input['is_visible'];
    }

    if (!$fields) bad_request('Rien à sauvegarder');

    $fields[] = 'updated_by = ?';
    $params[] = $userId;
    $params[] = $id;

    Db::exec(
        "UPDATE website_sections SET " . implode(', ', $fields) . " WHERE id = ?",
        $params
    );

    respond(['success' => true]);
    break;

// ── Créer une section ──
case 'create':
    $key = trim($input['section_key'] ?? '');
    $type = $input['section_type'] ?? 'text';
    $page = $input['page'] ?? 'index';
    $title = $input['title'] ?? '';

    if (!$key) bad_request('Clé requise');
    if (!preg_match('/^[a-z0-9_]+$/', $key)) bad_request('Clé invalide (a-z, 0-9, _)');

    $exists = Db::fetch(
        "SELECT id FROM website_sections WHERE page = ? AND section_key = ?",
        [$page, $key]
    );
    if ($exists) bad_request('Cette clé existe déjà');

    $maxOrder = (int) Db::getOne(
        "SELECT MAX(sort_order) FROM website_sections WHERE page = ?",
        [$page]
    );

    $id = Uuid::v4();
    $defaultContent = [];

    if (in_array($type, ['cards', 'services', 'values', 'team'])) {
        $defaultContent = ['cards' => []];
    } elseif ($type === 'timeline') {
        $defaultContent = ['items' => []];
    } elseif ($type === 'hero') {
        $defaultContent = ['stats' => [], 'videos' => []];
    } elseif ($type === 'quote') {
        $defaultContent = ['text' => ''];
    } elseif ($type === 'contact') {
        $defaultContent = ['address' => '', 'phone' => '', 'email' => '', 'hours' => ''];
    }

    Db::exec(
        "INSERT INTO website_sections (id, page, section_key, section_type, title, content, sort_order, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $page, $key, $type, $title ?: null, json_encode($defaultContent, JSON_UNESCAPED_UNICODE), $maxOrder + 1, $userId]
    );

    $section = Db::fetch("SELECT * FROM website_sections WHERE id = ?", [$id]);
    $section['content'] = json_decode($section['content'], true) ?: [];

    respond(['success' => true, 'section' => $section]);
    break;

// ── Supprimer une section ──
case 'delete':
    $id = $input['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec("DELETE FROM website_sections WHERE id = ?", [$id]);
    respond(['success' => true]);
    break;

// ── Réordonner les sections ──
case 'reorder':
    $order = $input['order'] ?? [];
    if (!is_array($order)) bad_request('Format invalide');

    foreach ($order as $i => $id) {
        Db::exec("UPDATE website_sections SET sort_order = ? WHERE id = ?", [$i + 1, $id]);
    }

    respond(['success' => true]);
    break;

// ── Toggle visibilité ──
case 'toggle_visibility':
    $id = $input['id'] ?? '';
    if (!$id) bad_request('ID requis');
    Db::exec(
        "UPDATE website_sections SET is_visible = NOT is_visible, updated_by = ? WHERE id = ?",
        [$userId, $id]
    );
    $section = Db::fetch("SELECT is_visible FROM website_sections WHERE id = ?", [$id]);
    respond(['success' => true, 'is_visible' => (int) $section['is_visible']]);
    break;

default:
    bad_request('Action inconnue: ' . $action);
}
