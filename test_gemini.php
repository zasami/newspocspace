<?php
require "config/config.php";
require "core/Db.php";

$cfgRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config");
$cfg = []; foreach ($cfgRows as $r) $cfg[$r["config_key"]] = $r["config_value"];

$aiApiKey = $cfg['gemini_api_key'];
$aiModel = $cfg['gemini_model'];
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$aiModel}:generateContent?key={$aiApiKey}";

$prompt = "Réponds en JSON uniquement. Format: {\"optimizations\": []}";
$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json'],
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$resp = json_decode($raw, true);
$text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
echo "RAW TEXT:\n$text\n";

$json = json_decode($text, true);
if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
} else {
    echo "JSON Decode OK\n";
    print_r($json);
}
