<?php
require "config/config.php";
require "core/Db.php";

$cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
$cfg = []; foreach ($cfgRows as $r) $cfg[$r["config_key"]] = $r["config_value"];

$aiApiKey = $cfg['gemini_api_key'];
$aiModel = $cfg['gemini_model'];
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";

$prompt = "Réponds en JSON uniquement. Propose des modifications pour résoudre les conflits.\nIMPORTANT: utilise UNIQUEMENT les user_id UUID listés ci-dessus. Ne les invente pas.\nVérifie que l'employé n'est pas déjà assigné ce jour-là et qu'il a la bonne fonction pour le poste.\nFormat: {\"optimizations\": [{\"action\": \"move|add|remove\", \"user_id\": \"UUID exact\", \"date\": \"YYYY-MM-DD\", \"to_shift\": \"D1|D3|D4|S3|S4\", \"module_code\": \"M1|M2|M3|M4\", \"reason\": \"...\"}], \"summary\": \"résumé en français\"}\nSi aucune optimisation possible, retourne {\"optimizations\": [], \"summary\": \"Planning optimal\"}";
$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 4096], // Removed responseMimeType
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$raw = curl_exec($ch);
$resp = json_decode($raw, true);
$text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
echo "RAW TEXT FROM GEMINI:\n$text\n";
