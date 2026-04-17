<?php
/**
 * SpocSpace - Bootstrap
 */

require_once __DIR__ . '/config/config.php';

// Autoload core classes
spl_autoload_register(function ($class) {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) return;
    $file = __DIR__ . '/core/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => true,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Session hardening ──
// 1. Server-side idle timeout (30 min)
// 2. Absolute lifetime (4h since login)
// 3. Bind session to IP + User-Agent hash (cookie theft mitigation)
// 4. Kill sessions older than password_changed_at for the current user
$sessionIdleTimeout = 1800;
$sessionAbsoluteMax = 4 * 3600;

$_killSession = function (): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Strict',
        ]);
    }
    @session_destroy();
    @session_start();
    session_regenerate_id(true);
};

if (!empty($_SESSION['ss_user'])) {
    $now = time();
    $lastActivity = $_SESSION['ss_last_activity'] ?? 0;
    $loginAt      = $_SESSION['ss_login_at'] ?? $lastActivity;

    $expire = false;

    if ($lastActivity && ($now - $lastActivity) > $sessionIdleTimeout)  $expire = true;
    if ($loginAt      && ($now - $loginAt)      > $sessionAbsoluteMax)  $expire = true;

    // IP pinning — allow /24 fluctuation for mobile networks would be fragile;
    // prefer strict comparison, users re-login on IP change.
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SESSION['ss_ip']) && $_SESSION['ss_ip'] !== $currentIp) $expire = true;

    // User-Agent pinning (hashed, first 32 chars of sha256)
    $currentUaHash = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 32);
    if (!empty($_SESSION['ss_ua']) && $_SESSION['ss_ua'] !== $currentUaHash) $expire = true;

    if ($expire) {
        $_killSession();
    } else {
        // Kill session if password was changed after this session was issued
        try {
            $pwChangedAt = \Db::getOne("SELECT password_changed_at FROM users WHERE id = ?", [$_SESSION['ss_user']['id']]);
            if ($pwChangedAt && $loginAt && strtotime($pwChangedAt) > $loginAt) {
                $_killSession();
            }
        } catch (\Throwable $e) {
            // DB unavailable — do nothing, fail-open on check
        }
        if (!empty($_SESSION['ss_user'])) {
            $_SESSION['ss_last_activity'] = $now;
        }
    }
}

// CSRF token
if (empty($_SESSION['ss_csrf_token'])) {
    $_SESSION['ss_csrf_token'] = bin2hex(random_bytes(32));
}

// Security headers
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(self), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self'; media-src 'self'; frame-ancestors 'self'");

// ── Helper functions ──

function respond($data = null, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function bad_request(string $msg = 'Bad request'): void { error_response($msg, 400); }
function unauthorized(string $msg = 'Non autorisé'): void { error_response($msg, 401); }
function forbidden(string $msg = 'Accès interdit'): void { error_response($msg, 403); }
function not_found(string $msg = 'Non trouvé'): void { error_response($msg, 404); }

/**
 * Offline conflict response — LWW: server version wins, client is notified
 */
function conflict_response(string $msg = 'Conflit — la version du serveur a été conservée'): void
{
    http_response_code(200); // 200 so the SW dequeues the item
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'conflict' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Check for offline conflict: if the queued action is older than the server record, it's a conflict.
 * Returns true if there IS a conflict (server version is newer).
 */
function check_offline_conflict(string $table, string $id, ?string $queuedAt): bool
{
    global $params;
    $queuedAt = $queuedAt ?? ($params['_queued_at'] ?? null);
    if (!$queuedAt) return false; // Not an offline action, no conflict possible

    $serverUpdatedAt = Db::getOne("SELECT updated_at FROM {$table} WHERE id = ?", [$id]);
    if (!$serverUpdatedAt) return false; // Record doesn't exist yet, no conflict

    return strtotime($serverUpdatedAt) > strtotime($queuedAt);
}

function h(?string $val): string
{
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Build a RFC6266-compliant Content-Disposition header value that neutralizes
 * CRLF/quote injection. Always emit X-Content-Type-Options: nosniff alongside.
 *
 * Usage:
 *   header('Content-Disposition: ' . safe_content_disposition($name, 'inline'));
 *   header('X-Content-Type-Options: nosniff');
 */
function safe_content_disposition(?string $filename, string $disposition = 'inline'): string
{
    $disposition = ($disposition === 'attachment') ? 'attachment' : 'inline';
    $name = basename((string) ($filename ?? ''));
    // Strip CR/LF/null/quotes/backslash — neutralise HTTP header injection
    $name = preg_replace('/[\r\n\0"\\\\]/', '', $name);
    $name = trim($name);
    if ($name === '') $name = 'file';
    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $name);
    $encoded = rawurlencode($name);
    return $disposition
        . '; filename="' . $asciiName . '"'
        . "; filename*=UTF-8''" . $encoded;
}

function require_auth(): array
{
    if (empty($_SESSION['ss_user'])) {
        unauthorized('Veuillez vous connecter');
    }
    return $_SESSION['ss_user'];
}

function require_responsable(): array
{
    $user = require_auth();
    if (!in_array($user['role'], ['admin', 'direction', 'responsable'])) {
        forbidden('Accès responsable requis');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (!in_array($user['role'], ['admin', 'direction'])) {
        forbidden('Accès administration requis');
    }
    return $user;
}

function require_permission(string $key): array
{
    $user = require_auth();
    $denied = $_SESSION['ss_user']['denied_perms'] ?? [];
    if (in_array($key, $denied)) {
        forbidden('Accès non autorisé');
    }
    return $user;
}

function get_pagination(): array
{
    global $params;
    $page = max(1, (int) ($params['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int) ($params['limit'] ?? DEFAULT_PAGE_SIZE)));
    return [$page, $limit];
}

/**
 * Rate limiting par IP + action
 */
function check_rate_limit(string $action, int $maxAttempts = RATE_LIMIT_MAX_ATTEMPTS, int $windowSeconds = RATE_LIMIT_WINDOW): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Nettoyer les anciennes entrées
    Db::exec(
        "DELETE FROM rate_limits WHERE action = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
        [$action, $windowSeconds]
    );

    // Compter les tentatives récentes
    $count = (int) Db::getOne(
        "SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
        [$ip, $action, $windowSeconds]
    );

    if ($count >= $maxAttempts) {
        error_response('Trop de tentatives. Veuillez réessayer dans quelques minutes.', 429);
    }

    // Enregistrer cette tentative
    Db::exec(
        "INSERT INTO rate_limits (ip, action) VALUES (?, ?)",
        [$ip, $action]
    );
}

/**
 * Valider la robustesse d'un mot de passe
 */
function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Le mot de passe doit faire au moins 8 caractères';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Le mot de passe doit contenir au moins une majuscule';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Le mot de passe doit contenir au moins un chiffre';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Le mot de passe doit contenir au moins un caractère spécial';
    }
    return null;
}
