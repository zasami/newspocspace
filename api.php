<?php
/**
 * zerdaTime - API entry point
 */
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$params = json_decode($rawInput, true) ?? [];
$params = array_merge($_GET, $_POST, $params);

$action = $params['action'] ?? '';

if (empty($action)) {
    bad_request('Missing action parameter');
}

// CSRF validation (skip read-only + auth flow)
$csrfExempt = ['login', 'request_reset', 'reset_password', 'me'];
$isReadOnly = str_starts_with($action, 'get_') || str_starts_with($action, 'serve_') || in_array($action, $csrfExempt, true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isReadOnly) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['zt_csrf_token']) || !$token || !hash_equals($_SESSION['zt_csrf_token'], $token)) {
        forbidden('Invalid or missing CSRF token');
    }
}

// Load routes
$routes = require __DIR__ . '/api_modules/_routes.php';

// Find module
$module = null;
foreach ($routes as $mod => $actions) {
    if (in_array($action, $actions, true)) {
        $module = $mod;
        break;
    }
}

if (!$module) {
    not_found('Unknown action');
}

$moduleFile = __DIR__ . '/api_modules/' . $module . '.php';
if (!file_exists($moduleFile)) {
    not_found('Action not available');
}

require_once $moduleFile;

if (!function_exists($action)) {
    not_found('Action not available');
}

try {
    $action();
} catch (\Throwable $e) {
    error_log('zerdaTime API error [' . $action . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_response('Une erreur interne est survenue', 500);
}
