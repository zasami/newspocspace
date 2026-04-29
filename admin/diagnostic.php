<?php
// Diagnostic page for Web Speech API availability
require_once __DIR__ . '/../init.php';

// Accès admin uniquement
if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction'])) {
    http_response_code(403);
    die('Accès interdit');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Web Speech API</title>
    <link href="/newspocspace/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">🔍 Diagnostic Web Speech API</h4>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td><strong>Protocole:</strong></td>
                            <td id="protocol">—</td>
                        </tr>
                        <tr>
                            <td><strong>Navigateur:</strong></td>
                            <td id="browser">—</td>
                        </tr>
                        <tr>
                            <td><strong>Web Speech API (SpeechRecognition):</strong></td>
                            <td id="speechRecognition" class="badge bg-danger">Non disponible</td>
                        </tr>
                        <tr>
                            <td><strong>Web Audio API:</strong></td>
                            <td id="webAudio" class="badge bg-danger">Non disponible</td>
                        </tr>
                        <tr>
                            <td><strong>MediaRecorder:</strong></td>
                            <td id="mediaRecorder" class="badge bg-danger">Non disponible</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4">
                    <h6>📋 Détails:</h6>
                    <pre class="bg-light p-3" id="details"></pre>
                </div>

                <div class="alert alert-info mt-4">
                    <strong>💡 Solution recommandée:</strong>
                    <p class="mb-0">Si Web Speech API n'est pas disponible, vous pouvez:</p>
                    <ol class="mb-0">
                        <li>Vérifier que vous êtes en <strong>HTTPS</strong> (pas HTTP)</li>
                        <li>Vérifier les permissions du navigateur</li>
                        <li>Utiliser le <strong>mode manuel</strong> pour taper le contenu</li>
                        <li>Importer un fichier audio et transcrire manuellement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Protocol check
        document.getElementById('protocol').textContent = window.location.protocol === 'https:' ? '✅ HTTPS' : '⚠️ HTTP (peut limiter certaines APIs)';

        // Browser detection
        const ua = navigator.userAgent;
        let browser = '?';
        if (ua.indexOf('Chrome') > -1) browser = 'Chrome';
        else if (ua.indexOf('Safari') > -1) browser = 'Safari';
        else if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
        else if (ua.indexOf('Edge') > -1) browser = 'Edge';
        document.getElementById('browser').textContent = browser;

        // API checks
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognition) {
            document.getElementById('speechRecognition').textContent = '✅ Disponible';
            document.getElementById('speechRecognition').className = 'badge bg-success';
        }

        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            document.getElementById('webAudio').textContent = '✅ Disponible';
            document.getElementById('webAudio').className = 'badge bg-success';
        }

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            document.getElementById('mediaRecorder').textContent = '✅ Disponible';
            document.getElementById('mediaRecorder').className = 'badge bg-success';
        }

        // Permissions check
        let details = 'Permissions et stato:\n';
        if (navigator.permissions) {
            Promise.all([
                navigator.permissions.query({ name: 'microphone' }),
                navigator.permissions.query({ name: 'speaker' })
            ]).then(perms => {
                details += 'Microphone: ' + perms[0].state + '\n';
                details += 'Speaker: ' + perms[1].state + '\n';
                document.getElementById('details').textContent = details;
            }).catch(e => {
                document.getElementById('details').textContent = details + '(Permissions API non disponible)';
            });
        } else {
            document.getElementById('details').textContent = details + '(Permissions API non disponible)';
        }
    </script>
</body>
</html>
