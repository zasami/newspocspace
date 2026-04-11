<?php
/**
 * FileSecurity — validation défensive des fichiers uploadés.
 *
 * Couche 1 anti-malware (pas de service externe, conforme LPD suisse) :
 *   - vérif magic bytes (pas juste l'extension / MIME envoyé par le navigateur)
 *   - re-encodage des images (casse payloads cachés dans EXIF / steganographie / polyglot)
 *   - détection JavaScript / OpenAction / Launch dans les PDF
 *   - rejet des doubles extensions et null bytes dans le nom
 *
 * Utilisation :
 *   $err = FileSecurity::validateUpload($_FILES['cv'], 'cv');
 *   if ($err) respond(['success' => false, 'message' => $err], 400);
 *   FileSecurity::sanitizeInPlace($destPath, $ext); // après move_uploaded_file
 */
class FileSecurity
{
    /** Magic bytes (signatures binaires de début de fichier) par extension */
    private const MAGIC = [
        'pdf'  => ["%PDF-"],
        'jpg'  => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89PNG\r\n\x1A\n"],
        'doc'  => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // OLE2 (MS Office legacy)
        'docx' => ["PK\x03\x04"],                        // ZIP container
    ];

    /** Motifs suspects dans les PDF */
    private const PDF_SUSPICIOUS_PATTERNS = [
        '/\/JavaScript\b/i',
        '/\/JS\b/i',
        '/\/OpenAction\b/i',
        '/\/Launch\b/i',
        '/\/EmbeddedFile\b/i',
        '/\/AA\b/i',         // Additional Actions
        '/\/RichMedia\b/i',
    ];

    /**
     * Valide un $_FILES[...] avant insertion/déplacement.
     * Retourne null si OK, ou un message d'erreur.
     */
    public static function validateUpload(array $file, string $label): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "Upload $label : erreur système (" . $file['error'] . ").";
        }

        $name = $file['name'] ?? '';
        $tmp  = $file['tmp_name'] ?? '';

        if ($name === '' || $tmp === '' || !is_uploaded_file($tmp)) {
            return "Upload $label : fichier invalide.";
        }

        // Rejet null bytes
        if (strpos($name, "\0") !== false) {
            return "Upload $label : nom de fichier invalide.";
        }

        // Rejet double extension (ex: cv.pdf.exe, photo.jpg.php)
        $basename = basename($name);
        $parts = explode('.', $basename);
        if (count($parts) > 2) {
            $dangerous = ['php','phtml','phar','exe','bat','cmd','sh','js','html','htm','svg','jsp','asp','aspx'];
            foreach (array_slice($parts, 1, -1) as $middle) {
                if (in_array(strtolower($middle), $dangerous, true)) {
                    return "Upload $label : double extension suspecte rejetée.";
                }
            }
        }

        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!isset(self::MAGIC[$ext])) {
            return "Upload $label : extension non autorisée.";
        }

        // Vérif magic bytes
        $fp = fopen($tmp, 'rb');
        if (!$fp) return "Upload $label : lecture impossible.";
        $head = fread($fp, 16);
        fclose($fp);

        $matched = false;
        foreach (self::MAGIC[$ext] as $sig) {
            if (strncmp($head, $sig, strlen($sig)) === 0) { $matched = true; break; }
        }
        if (!$matched) {
            return "Upload $label : le contenu réel du fichier ne correspond pas à son extension.";
        }

        // Scan PDF : bloquer JS / actions dangereuses
        if ($ext === 'pdf') {
            $raw = file_get_contents($tmp);
            if ($raw === false) return "Upload $label : lecture impossible.";
            foreach (self::PDF_SUSPICIOUS_PATTERNS as $pat) {
                if (preg_match($pat, $raw)) {
                    return "Upload $label : PDF contenant du code actif (JavaScript / action automatique) rejeté.";
                }
            }
        }

        return null;
    }

    /**
     * Nettoie un fichier déjà déplacé (après move_uploaded_file).
     * Pour les images : re-encodage complet pour casser tout payload
     * caché dans EXIF / commentaires / polyglot files.
     * Pour les PDF : validation bytes finaux (%%EOF).
     * Retourne null si OK, ou message d'erreur (le fichier peut être supprimé par l'appelant).
     */
    public static function sanitizeInPlace(string $path, string $ext): ?string
    {
        $ext = strtolower($ext);

        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return self::reencodeImage($path, $ext);
        }

        if ($ext === 'pdf') {
            // Un PDF valide finit par %%EOF (parfois suivi de whitespace)
            $size = filesize($path);
            if ($size < 32) return "PDF tronqué.";
            $fp = fopen($path, 'rb');
            fseek($fp, max(0, $size - 1024));
            $tail = fread($fp, 1024);
            fclose($fp);
            if (strpos($tail, '%%EOF') === false) {
                return "PDF malformé (marqueur de fin manquant).";
            }
        }

        return null;
    }

    /**
     * Re-encode complètement une image via GD : supprime EXIF, ICC profiles,
     * métadonnées, commentaires, et tout polyglot/payload caché.
     * Le fichier résultant n'a que les pixels.
     */
    private static function reencodeImage(string $path, string $ext): ?string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            // GD absent : on laisse passer (validation magic bytes déjà faite)
            return null;
        }

        try {
            $info = @getimagesize($path);
            if (!$info) return "Image invalide.";
            [$w, $h] = $info;

            // Limite raisonnable (bloque les zip-bombs d'image)
            if ($w > 10000 || $h > 10000 || ($w * $h) > 40000000) {
                return "Image trop grande (max 10000x10000).";
            }

            $src = null;
            if ($ext === 'jpg' || $ext === 'jpeg') $src = @imagecreatefromjpeg($path);
            elseif ($ext === 'png') $src = @imagecreatefrompng($path);
            if (!$src) return "Image corrompue ou non décodable.";

            // Canvas propre, on copie juste les pixels
            $clean = imagecreatetruecolor($w, $h);
            if ($ext === 'png') {
                imagealphablending($clean, false);
                imagesavealpha($clean, true);
                $transparent = imagecolorallocatealpha($clean, 0, 0, 0, 127);
                imagefilledrectangle($clean, 0, 0, $w, $h, $transparent);
            }
            imagecopy($clean, $src, 0, 0, 0, 0, $w, $h);

            $tmpOut = $path . '.clean';
            $ok = ($ext === 'png')
                ? imagepng($clean, $tmpOut, 6)
                : imagejpeg($clean, $tmpOut, 88);

            imagedestroy($src);
            imagedestroy($clean);

            if (!$ok || !file_exists($tmpOut)) return "Échec du re-encodage.";
            if (!rename($tmpOut, $path)) { @unlink($tmpOut); return "Échec du remplacement."; }
            return null;
        } catch (Throwable $e) {
            return "Erreur de traitement image.";
        }
    }
}
