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
        'gif'  => ["GIF87a", "GIF89a"],
        'webp' => ["RIFF"], // full check in validateUpload (4 bytes "WEBP" at offset 8)
        'doc'  => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // OLE2 (MS Office legacy)
        'docx' => ["PK\x03\x04"],                        // ZIP container
        'xlsx' => ["PK\x03\x04"],
        'pptx' => ["PK\x03\x04"],
        'mp3'  => ["\xFF\xFB", "\xFF\xF3", "\xFF\xF2", "ID3"],
        'wav'  => ["RIFF"],
        'm4a'  => ["\x00\x00\x00\x1C\x66\x74\x79\x70", "\x00\x00\x00\x20\x66\x74\x79\x70"],
        'ogg'  => ["OggS"],
        'mp4'  => ["\x00\x00\x00\x18\x66\x74\x79\x70", "\x00\x00\x00\x1C\x66\x74\x79\x70", "\x00\x00\x00\x20\x66\x74\x79\x70"],
        'webm' => ["\x1A\x45\xDF\xA3"],
    ];

    /** Presets — extensions autorisées par contexte (whitelist). */
    public const ALLOW_IMAGE    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const ALLOW_PDF      = ['pdf'];
    public const ALLOW_DOCUMENT = ['pdf', 'doc', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const ALLOW_AUDIO    = ['mp3', 'wav', 'm4a', 'ogg'];
    public const ALLOW_VIDEO    = ['mp4', 'webm'];

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
     *
     * @param array       $file        entrée $_FILES['xxx']
     * @param string      $label       texte pour les messages d'erreur
     * @param array|null  $allowedExt  whitelist d'extensions autorisées (ex: ALLOW_IMAGE).
     *                                 Null = toutes les extensions supportées par MAGIC.
     * @param int|null    $maxBytes    taille max en octets (défaut 50 Mo)
     * @return string|null null si OK, message d'erreur sinon.
     */
    public static function validateUpload(array $file, string $label, ?array $allowedExt = null, ?int $maxBytes = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "Upload $label : erreur système (" . $file['error'] . ").";
        }

        $name = $file['name'] ?? '';
        $tmp  = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        $fn   = $name ? ' « ' . basename($name) . ' »' : '';

        if ($name === '' || $tmp === '' || !is_uploaded_file($tmp)) {
            return "$label$fn : fichier invalide.";
        }

        // Rejet null bytes
        if (strpos($name, "\0") !== false) {
            return "$label$fn : nom de fichier invalide.";
        }

        $maxBytes = $maxBytes ?: 50 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            $mb = (int) round($maxBytes / 1024 / 1024);
            return "$label$fn : taille invalide (max {$mb} Mo).";
        }

        // Rejet double extension (ex: cv.pdf.exe, photo.jpg.php)
        $basename = basename($name);
        $parts = explode('.', $basename);
        if (count($parts) > 2) {
            $dangerous = ['php','phtml','phar','phps','exe','bat','cmd','sh','js','html','htm','svg','jsp','asp','aspx','pl','py','cgi'];
            foreach (array_slice($parts, 1, -1) as $middle) {
                if (in_array(strtolower($middle), $dangerous, true)) {
                    return "$label$fn : double extension suspecte rejetée.";
                }
            }
        }

        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!isset(self::MAGIC[$ext])) {
            return "$label$fn : extension non autorisée.";
        }
        if ($allowedExt !== null && !in_array($ext, $allowedExt, true)) {
            return "$label$fn : extension non autorisée pour ce contexte.";
        }

        // Vérif magic bytes
        $fp = fopen($tmp, 'rb');
        if (!$fp) return "$label$fn : lecture impossible.";
        $head = fread($fp, 32);
        fclose($fp);

        $matched = false;
        foreach (self::MAGIC[$ext] as $sig) {
            if (strncmp($head, $sig, strlen($sig)) === 0) { $matched = true; break; }
        }
        if (!$matched) {
            return "$label$fn : le contenu du fichier ne correspond pas à son extension.";
        }

        // RIFF containers: verify 4-byte subtype at offset 8
        if ($ext === 'webp') {
            if (strlen($head) < 12 || substr($head, 8, 4) !== 'WEBP') {
                return "$label$fn : fichier WebP invalide.";
            }
        }
        if ($ext === 'wav') {
            if (strlen($head) < 12 || substr($head, 8, 4) !== 'WAVE') {
                return "$label$fn : fichier WAV invalide.";
            }
        }

        // Scan PDF : bloquer JS / actions dangereuses
        if ($ext === 'pdf') {
            $raw = file_get_contents($tmp);
            if ($raw === false) return "$label$fn : lecture impossible.";
            foreach (self::PDF_SUSPICIOUS_PATTERNS as $pat) {
                if (preg_match($pat, $raw)) {
                    return "$label$fn : PDF contenant du code actif (JavaScript) rejeté.";
                }
            }
        }

        return null;
    }

    /**
     * Génère un header Content-Disposition sûr (RFC 6266),
     * neutralise CRLF/quotes et gère les caractères non-ASCII via filename*.
     */
    public static function safeContentDisposition(string $filename, string $disposition = 'inline'): string
    {
        $disposition = ($disposition === 'attachment') ? 'attachment' : 'inline';
        $name = basename((string) $filename);
        // Strip CR/LF/null/quotes — neutralise HTTP header injection
        $name = preg_replace('/[\r\n\0"\\\\]/', '', $name);
        $name = trim($name);
        if ($name === '') $name = 'file';

        // ASCII fallback (limite à ASCII imprimable)
        $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $name);
        $encoded = rawurlencode($name);

        return $disposition
            . '; filename="' . $asciiName . '"'
            . "; filename*=UTF-8''" . $encoded;
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
