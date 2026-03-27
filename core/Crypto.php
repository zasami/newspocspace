<?php
/**
 * Crypto — AES-256-GCM encryption for sensitive data
 *
 * Usage:
 *   $encrypted = Crypto::encrypt('sensitive data');
 *   $decrypted = Crypto::decrypt($encrypted);
 *
 * Key management:
 *   - Key is stored in storage/.encryption_key (auto-generated on first use)
 *   - This file MUST be excluded from backups or encrypted separately
 *   - Key is 256-bit (32 bytes), hex-encoded in file
 */
class Crypto
{
    private static ?string $key = null;
    private const CIPHER = 'aes-256-gcm';
    private const KEY_FILE = __DIR__ . '/../storage/.encryption_key';

    /**
     * Encrypt plaintext → base64-encoded ciphertext (iv + tag + ciphertext)
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // 128-bit tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Pack: iv (12) + tag (16) + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt base64-encoded ciphertext → plaintext
     * Returns null if decryption fails (tampered data, wrong key)
     */
    public static function decrypt(string $encoded): ?string
    {
        $key = self::getKey();
        $data = base64_decode($encoded, true);

        if ($data === false || strlen($data) < 28) {
            return null; // 12 (iv) + 16 (tag) = 28 minimum
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Check if a value looks encrypted (base64-encoded, correct minimum length)
     */
    public static function isEncrypted(string $value): bool
    {
        if (strlen($value) < 40) return false; // base64 of 28+ bytes
        $decoded = base64_decode($value, true);
        return $decoded !== false && strlen($decoded) >= 28;
    }

    /**
     * Encrypt if not already encrypted
     */
    public static function ensureEncrypted(string $value): string
    {
        if (empty($value)) return $value;
        if (self::isEncrypted($value)) {
            // Verify it actually decrypts
            $test = self::decrypt($value);
            if ($test !== null) return $value;
        }
        return self::encrypt($value);
    }

    /**
     * Get or generate the encryption key
     */
    private static function getKey(): string
    {
        if (self::$key !== null) return self::$key;

        $keyFile = self::KEY_FILE;

        if (file_exists($keyFile)) {
            $hex = trim(file_get_contents($keyFile));
            if (strlen($hex) === 64) { // 32 bytes hex-encoded
                self::$key = hex2bin($hex);
                return self::$key;
            }
        }

        // Generate new key
        $dir = dirname($keyFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        self::$key = random_bytes(32);
        file_put_contents($keyFile, bin2hex(self::$key));
        chmod($keyFile, 0600);

        // Also create .htaccess to protect the key file
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess) || strpos(file_get_contents($htaccess), 'encryption_key') === false) {
            $content = file_exists($htaccess) ? file_get_contents($htaccess) . "\n" : '';
            $content .= "<Files \".encryption_key\">\n    Require all denied\n</Files>\n";
            file_put_contents($htaccess, $content);
        }

        return self::$key;
    }

    /**
     * Rotate encryption key — re-encrypts all sensitive data with a new key
     * Returns count of re-encrypted records
     */
    public static function rotateKey(): array
    {
        $oldKey = self::getKey();
        $stats = ['absences_motifs' => 0, 'fiches' => 0, 'errors' => []];

        // Generate new key
        $newKey = random_bytes(32);

        // Re-encrypt absence motifs
        $absences = Db::fetchAll("SELECT id, motif FROM absences WHERE motif IS NOT NULL AND motif != ''");
        foreach ($absences as $row) {
            $plaintext = self::decryptWithKey($row['motif'], $oldKey);
            if ($plaintext !== null) {
                $newCipher = self::encryptWithKey($plaintext, $newKey);
                Db::exec("UPDATE absences SET motif = ? WHERE id = ?", [$newCipher, $row['id']]);
                $stats['absences_motifs']++;
            }
        }

        // Save new key
        file_put_contents(self::KEY_FILE, bin2hex($newKey));
        chmod(self::KEY_FILE, 0600);
        self::$key = $newKey;

        return $stats;
    }

    private static function encryptWithKey(string $plaintext, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decryptWithKey(string $encoded, string $key): ?string
    {
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 28) return null;
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? null : $plaintext;
    }
}
