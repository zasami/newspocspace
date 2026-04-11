<?php
/**
 * VirusTotal — couche 2 antivirus, hash-only (conforme LPD suisse).
 *
 * On envoie uniquement le SHA-256 du fichier (une empreinte n'est pas une
 * donnée personnelle). Si le hash est connu comme malveillant → rejet.
 * Sinon → on laisse passer (la couche 1 locale a déjà fait son travail).
 *
 * Dégradation gracieuse :
 *   - clé manquante / désactivée   → SKIPPED  (upload accepté)
 *   - quota dépassé (HTTP 429)     → SKIPPED  (upload accepté, incrément compteur quota_exceeded)
 *   - timeout / erreur réseau      → SKIPPED  (upload accepté, erreur loggée)
 *   - hash inconnu (HTTP 404)      → UNKNOWN  (upload accepté)
 *   - hash connu & malveillant     → MALICIOUS (upload REJETÉ)
 *   - hash connu & propre          → CLEAN    (upload accepté)
 */
class VirusTotal
{
    public const STATUS_CLEAN     = 'clean';
    public const STATUS_MALICIOUS = 'malicious';
    public const STATUS_UNKNOWN   = 'unknown';
    public const STATUS_SKIPPED   = 'skipped';

    /** Seuil : nombre de moteurs qui doivent flagger pour considérer "malicious" */
    private const MALICIOUS_THRESHOLD = 3;

    /**
     * Vérifie un fichier par son hash. N'échoue jamais : en cas de problème
     * réseau / quota / clé manquante, retourne SKIPPED.
     *
     * @return array ['status' => ..., 'detections' => int, 'message' => string]
     */
    public static function checkFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Fichier introuvable'];
        }

        $enabled = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'virustotal_enabled'");
        if ($enabled !== '1') {
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'VirusTotal désactivé'];
        }

        $key = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'virustotal_api_key'");
        if (!$key) {
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Clé API absente'];
        }

        $sha256 = @hash_file('sha256', $filePath);
        if (!$sha256) {
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Hash impossible'];
        }

        return self::lookupHash($sha256, $key);
    }

    /**
     * Appel direct à l'API VirusTotal v3 par hash (GET /files/{hash}).
     * Ne téléverse jamais le fichier — uniquement le hash.
     */
    private static function lookupHash(string $sha256, string $apiKey): array
    {
        $url = 'https://www.virustotal.com/api/v3/files/' . $sha256;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ['x-apikey: ' . $apiKey, 'Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        // Réseau cassé : on skip sans bloquer
        if ($resp === false) {
            self::logError('network error: ' . $err);
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Réseau indisponible'];
        }

        // Quota dépassé (500/jour gratuit) : on skip sans bloquer
        if ($code === 429) {
            self::incrementQuotaExceeded();
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Quota VirusTotal dépassé — fallback couche 1 uniquement'];
        }

        // Clé invalide / révoquée : on skip, l'admin verra le log
        if ($code === 401 || $code === 403) {
            self::logError('auth error HTTP ' . $code);
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Clé VirusTotal invalide'];
        }

        // Hash jamais vu par VirusTotal : pas d'info, on laisse passer (couche 1 a déjà validé)
        if ($code === 404) {
            return ['status' => self::STATUS_UNKNOWN, 'detections' => 0, 'message' => 'Hash inconnu de VirusTotal'];
        }

        if ($code !== 200) {
            self::logError('unexpected HTTP ' . $code);
            return ['status' => self::STATUS_SKIPPED, 'detections' => 0, 'message' => 'Réponse inattendue HTTP ' . $code];
        }

        $json = json_decode($resp, true);
        $stats = $json['data']['attributes']['last_analysis_stats'] ?? null;
        if (!$stats) {
            return ['status' => self::STATUS_UNKNOWN, 'detections' => 0, 'message' => 'Données d\'analyse absentes'];
        }

        $malicious  = (int) ($stats['malicious']  ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $detections = $malicious + $suspicious;

        if ($malicious >= self::MALICIOUS_THRESHOLD) {
            return [
                'status'     => self::STATUS_MALICIOUS,
                'detections' => $detections,
                'message'    => "Fichier détecté comme malveillant par $malicious moteur(s) antivirus",
            ];
        }

        return [
            'status'     => self::STATUS_CLEAN,
            'detections' => $detections,
            'message'    => 'Hash propre',
        ];
    }

    private static function incrementQuotaExceeded(): void
    {
        try {
            $key = 'virustotal_quota_exceeded_' . date('Y-m-d');
            $userId = $_SESSION['ss_user']['id'] ?? null;
            Db::exec(
                "INSERT INTO ems_config (config_key, config_value, updated_by) VALUES (?, '1', ?)
                 ON DUPLICATE KEY UPDATE config_value = config_value + 1, updated_by = VALUES(updated_by)",
                [$key, $userId]
            );
        } catch (Throwable $e) { /* ignore */ }
    }

    private static function logError(string $msg): void
    {
        @error_log('[VirusTotal] ' . $msg);
    }
}
