<?php
/**
 * SpocSpace — Enable Installer
 *
 * CLI-only. Generates a random token and writes storage/.install-enabled.
 * Prints the URL to open install.php in a browser.
 *
 * Usage:  php scripts/enable-install.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande (SSH).\n");
}

$root = dirname(__DIR__);
$storageDir = $root . '/storage';
$lockFile = $storageDir . '/.installed';
$enableFile = $storageDir . '/.install-enabled';

if (!is_dir($storageDir)) {
    fwrite(STDERR, "Le dossier storage/ n'existe pas : $storageDir\n");
    exit(1);
}

if (file_exists($lockFile)) {
    fwrite(STDERR, "\n⚠  SpocSpace est déjà installé (storage/.installed existe).\n");
    fwrite(STDERR, "   Pour réinstaller : supprime d'abord ce fichier manuellement :\n");
    fwrite(STDERR, "     rm $lockFile\n\n");
    exit(2);
}

$token = bin2hex(random_bytes(24));

if (file_put_contents($enableFile, $token) === false) {
    fwrite(STDERR, "Impossible d'écrire $enableFile\n");
    exit(3);
}
@chmod($enableFile, 0600);

$scheme = 'https';
$host = trim(getenv('INSTALL_HOST') ?: '');
if ($host === '') {
    $host = '{votre-domaine}';
}
$urlPath = '/newspocspace/install.php';
$url = $scheme . '://' . $host . $urlPath . '?key=' . $token;

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Installeur SpocSpace activé\n";
echo "════════════════════════════════════════════════════════════════\n\n";
echo "Token : $token\n";
echo "Fichier : $enableFile\n\n";
echo "Ouvre cette URL dans ton navigateur :\n";
echo "  $url\n\n";
if ($host === '{votre-domaine}') {
    echo "(remplace {votre-domaine} par le domaine réel, ex: client.ch)\n";
    echo "Ou définis INSTALL_HOST avant de lancer ce script :\n";
    echo "  INSTALL_HOST=client.ch php scripts/enable-install.php\n\n";
}
echo "Une fois l'installation terminée, le fichier .install-enabled\n";
echo "sera automatiquement supprimé et l'installeur désactivé.\n\n";
echo "Pour désactiver manuellement avant d'installer :\n";
echo "  rm $enableFile\n";
echo "════════════════════════════════════════════════════════════════\n";
