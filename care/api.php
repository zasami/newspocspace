<?php
/**
 * zerdaCare — API entry point
 */
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Require at least collaborateur role (ASA, AS, infirmier can access)
if (empty($_SESSION['zt_user'])) {
    forbidden('Accès requis');
}

$rawInput = file_get_contents('php://input');
$params = json_decode($rawInput, true) ?? [];
$params = array_merge($_GET, $_POST, $params);

$action = $params['action'] ?? '';
if (empty($action)) {
    bad_request('Missing action parameter');
}

// CSRF for state-changing
$isReadOnly = str_starts_with($action, 'care_get_') || str_starts_with($action, 'care_serve_') || str_starts_with($action, 'care_download_');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isReadOnly) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['zt_csrf_token']) || !$token || !hash_equals($_SESSION['zt_csrf_token'], $token)) {
        forbidden('Invalid CSRF token');
    }
}

// Load routes
$routes = require __DIR__ . '/api_modules/_routes.php';

$module = null;
foreach ($routes as $mod => $actions) {
    if (in_array($action, $actions, true)) {
        $module = $mod;
        break;
    }
}

if (!$module) not_found('Unknown action');

$moduleFile = __DIR__ . '/api_modules/' . $module . '.php';
if (!file_exists($moduleFile)) not_found('Action not available');

require_once $moduleFile;

if (!function_exists($action)) not_found('Action not available');

try {
    $action();
} catch (\Throwable $e) {
    error_log('zerdaCare API error [' . $action . ']: ' . $e->getMessage());
    error_response('Erreur interne', 500);
}
