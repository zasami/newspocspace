<?php
/**
 * Resumable file download — supports Range headers for large files.
 * Usage: download.php?file=ZerdaTime-IA-Install.zip
 *        download.php?file=mistral-model.bin
 */

$allowed = [
    'ZerdaTime-IA-Install.zip' => __DIR__ . '/ZerdaTime-IA-Install.zip',
    'mistral-model.bin'        => __DIR__ . '/downloads/mistral-model.bin',
    'install.bat'              => __DIR__ . '/install.bat',
    'start-zerdatime-ia.bat'   => __DIR__ . '/start-zerdatime-ia.bat',
    'install-whisper.ps1'      => __DIR__ . '/install-whisper.ps1',
    'uninstall.bat'            => __DIR__ . '/uninstall.bat',
];

$file = $_GET['file'] ?? '';
if (!isset($allowed[$file]) || !file_exists($allowed[$file])) {
    http_response_code(404);
    exit('Fichier introuvable');
}

$path = realpath($allowed[$file]);
$size = filesize($path);
$mime = 'application/octet-stream';

// Désactiver toute compression — critique pour les fichiers de plusieurs Go
if (function_exists('ini_set')) {
    ini_set('zlib.output_compression', 'Off');
}
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
// Vider tout buffer PHP existant
while (ob_get_level()) { ob_end_clean(); }

// Range support
$start = 0;
$end = $size - 1;
$partial = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = $m[1] !== '' ? (int)$m[1] : 0;
        $end   = $m[2] !== '' ? min((int)$m[2], $size - 1) : $size - 1;
        $partial = true;
    }
}

$length = $end - $start + 1;

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Cache-Control: no-cache, no-store');
header('Content-Encoding: identity');

if ($partial) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

// Stream the file in 2 MB chunks
if (function_exists('set_time_limit')) { set_time_limit(0); }
ignore_user_abort(false);

$fp = fopen($path, 'rb');
fseek($fp, $start);
$remaining = $length;
$chunk = 2097152; // 2 MB

while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
    $read = min($chunk, $remaining);
    echo fread($fp, $read);
    $remaining -= $read;
    flush();
}
fclose($fp);
