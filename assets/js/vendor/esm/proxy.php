<?php
/**
 * Zasamix - ESM Module Proxy
 * Local cache for ESM modules from jsdelivr CDN
 *
 * Usage: proxy.php?m=@scope/package@version
 */

$cacheDir = __DIR__ . '/cache';
$cdnBase = 'https://cdn.jsdelivr.net/npm';
$maxAge = 86400 * 365;

$localOnly = true;

if (!empty($GLOBALS['warmup_mode'])) {
    $localOnly = false;
}

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$module = $_GET['m'] ?? '';

if (empty($module)) {
    http_response_code(400);
    die('// Missing module parameter');
}

$cacheKey = md5($module);
$cacheFile = $cacheDir . '/' . $cacheKey . '.js';
$metaFile = $cacheDir . '/' . $cacheKey . '.meta';

if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Access-Control-Allow-Origin: https://zkriva.com');
}

if (file_exists($cacheFile)) {
    $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : null;
    $cacheAge = $meta ? time() - $meta['time'] : 0;

    if ($localOnly || $cacheAge < $maxAge) {
        if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) header('X-Cache: HIT');
        readfile($cacheFile);
        if (!empty($GLOBALS['warmup_mode'])) return;
        exit;
    }
}

if ($localOnly) {
    if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) {
        http_response_code(404);
        header('X-Cache: MISS-LOCAL');
    }
    if (!empty($GLOBALS['warmup_mode'])) {
        echo "// ERROR: Not in cache: $module\n// Run: php warmup.php";
        return;
    }
    die("// ERROR: Not in cache: $module\n// Run: php warmup.php");
}

if (!function_exists('fetchWithCurl')) {
function fetchWithCurl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ZasamixProxy/1.0)',
        CURLOPT_HTTPHEADER => ['Accept: application/javascript, */*'],
    ]);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200 && $content !== false) ? $content : false;
}
}

$modulePath = ltrim($module, '/');
$url = $cdnBase . '/' . $modulePath . (strpos($modulePath, '/+esm') === false ? '/+esm' : '');

$content = fetchWithCurl($url);

if ($content === false) {
    if (file_exists($cacheFile)) {
        if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) header('X-Cache: STALE');
        readfile($cacheFile);
        if (!empty($GLOBALS['warmup_mode'])) return;
        exit;
    }
    if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) http_response_code(502);
    $msg = '// Failed to fetch: ' . $module;
    if (!empty($GLOBALS['warmup_mode'])) { echo $msg; return; }
    die($msg);
}

// Rewrite imports to go through this proxy
$proxyBase = '/zerdatime/assets/js/vendor/esm/proxy.php?m=';

$modulePattern = '(\/npm\/[^"\']+|prosemirror-[^"\']+|w3c-keyname[^"\']*|rope-sequence[^"\']*|orderedmap[^"\']*|crelt[^"\']*)';

$pmVersions = [
    'prosemirror-state' => '1.4.4',
    'prosemirror-view' => '1.38.1',
    'prosemirror-model' => '1.25.0',
    'prosemirror-transform' => '1.10.4',
    'prosemirror-commands' => '1.7.1',
    'prosemirror-keymap' => '1.2.3',
    'prosemirror-schema-list' => '1.5.1',
    'prosemirror-history' => '1.4.1',
    'prosemirror-dropcursor' => '1.8.2',
    'prosemirror-gapcursor' => '1.4.0',
    'prosemirror-inputrules' => '1.5.0',
];

$cleanPath = function($path) use ($pmVersions) {
    $path = preg_replace('/^\/npm\//', '', $path);
    $path = preg_replace('/\/\+esm$/', '', $path);
    foreach ($pmVersions as $pkg => $version) {
        $path = preg_replace('/^' . preg_quote($pkg, '/') . '@[\d.]+/', $pkg . '@' . $version, $path);
    }
    return $path;
};

$rewrite = function($matches) use ($proxyBase, $cleanPath) {
    return $proxyBase . urlencode($cleanPath($matches[1]));
};

$content = preg_replace_callback('/from\s*["\']' . $modulePattern . '["\']/i', function($m) use ($rewrite) {
    return 'from "' . ($rewrite)($m) . '"';
}, $content);

$content = preg_replace_callback('/import\s*\(\s*["\']' . $modulePattern . '["\']\s*\)/i', function($m) use ($rewrite) {
    return 'import("' . ($rewrite)($m) . '")';
}, $content);

$content = preg_replace_callback('/export\s*\*\s*from\s*["\']' . $modulePattern . '["\']/i', function($m) use ($rewrite) {
    return 'export * from "' . ($rewrite)($m) . '"';
}, $content);

file_put_contents($cacheFile, $content);
file_put_contents($metaFile, json_encode(['time' => time(), 'module' => $module, 'url' => $url]));

if (php_sapi_name() !== 'cli' && empty($GLOBALS['no_http_headers'])) header('X-Cache: MISS');
echo $content;
