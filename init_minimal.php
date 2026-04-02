<?php
/**
 * SpocSpace - Minimal Bootstrap (for install wizard)
 * Loads config + DB connection only — no session, auth, or security headers.
 */

// Load .env.local (written by installer) or .env
$envLocal = __DIR__ . '/.env.local';
$envFile  = __DIR__ . '/.env';
$envPath  = file_exists($envLocal) ? $envLocal : $envFile;

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// Database constants
if (!defined('DB_HOST'))    define('DB_HOST',    $_ENV['DB_HOST'] ?? 'localhost');
if (!defined('DB_PORT'))    define('DB_PORT',    $_ENV['DB_PORT'] ?? 3306);
if (!defined('DB_NAME'))    define('DB_NAME',    $_ENV['DB_NAME'] ?? '');
if (!defined('DB_USER'))    define('DB_USER',    $_ENV['DB_USER'] ?? '');
if (!defined('DB_PASS'))    define('DB_PASS',    $_ENV['DB_PASS'] ?? '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Autoload core classes (Db, Uuid, etc.)
spl_autoload_register(function ($class) {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) return;
    $file = __DIR__ . '/core/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
