#!/usr/bin/env python3
"""
ZerdaTime - Serveur local de transcription (Vosk + Whisper)
Ecoute sur http://localhost:5876 et transcrit l'audio envoye en POST.
Supporte deux moteurs :
  - Vosk  : leger, rapide, fonctionne sur CPU faible (defaut)
  - Whisper: plus precis, necessite plus de puissance (CPU/GPU)
Selection du moteur : POST /transcribe?engine=vosk  (ou whisper)
"""

import sys
import json
import tempfile
import os
import subprocess
import wave
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

# ──────────────────────────────────────────────────
#  VOSK ENGINE
# ──────────────────────────────────────────────────
from vosk import Model, KaldiRecognizer, SetLogLevel

SetLogLevel(-1)  # Silencieux

VOSK_MODEL_PATH = os.environ.get("VOSK_MODEL", "")
VOSK_MODEL_NAME = "vosk-model-fr-0.22"
VOSK_MODEL_URL = "https://alphacephei.com/vosk/models/vosk-model-fr-0.22.zip"


def find_vosk_model():
    """Trouve ou telecharge le modele Vosk francais."""
    if VOSK_MODEL_PATH and os.path.isdir(VOSK_MODEL_PATH):
        return VOSK_MODEL_PATH

    script_dir = os.path.dirname(os.path.abspath(__file__))
    for base in [os.getcwd(), script_dir]:
        candidate = os.path.join(base, VOSK_MODEL_NAME)
        if os.path.isdir(candidate):
            return candidate

    dest = os.path.join(script_dir, VOSK_MODEL_NAME)
    zip_path = dest + ".zip"

    print(f"[Vosk] Modele non trouve. Telechargement de {VOSK_MODEL_NAME}...")
    print(f"[Vosk] (~1.4 Go, une seule fois)")

    import urllib.request
    import zipfile

    urllib.request.urlretrieve(VOSK_MODEL_URL, zip_path, _download_progress)
    print("\n[Vosk] Extraction du modele...")

    with zipfile.ZipFile(zip_path, 'r') as z:
        z.extractall(script_dir)

    os.unlink(zip_path)
    print(f"[Vosk] Modele installe dans {dest}")
    return dest


def _download_progress(block_num, block_size, total_size):
    downloaded = block_num * block_size
    if total_size > 0:
        pct = min(100, downloaded * 100 // total_size)
        mb = downloaded // (1024 * 1024)
        total_mb = total_size // (1024 * 1024)
        print(f"\r[Vosk] Telechargement: {mb}/{total_mb} Mo ({pct}%)", end="", flush=True)


print("[ZerdaTime] Chargement du modele Vosk...")
vosk_model_path = find_vosk_model()
vosk_model = Model(vosk_model_path)
print(f"[ZerdaTime] Vosk pret ! ({os.path.basename(vosk_model_path)})")


def transcribe_vosk(audio_path):
    """Transcrit un fichier audio avec Vosk."""
    wav_path = convert_to_wav(audio_path)

    try:
        wf = wave.open(wav_path, "rb")
        rec = KaldiRecognizer(vosk_model, wf.getframerate())
        rec.SetWords(False)

        results = []
        while True:
            data = wf.readframes(4000)
            if len(data) == 0:
                break
            if rec.AcceptWaveform(data):
                part = json.loads(rec.Result())
                if part.get("text"):
                    results.append(part["text"])

        final = json.loads(rec.FinalResult())
        if final.get("text"):
            results.append(final["text"])

        wf.close()
        return " ".join(results)
    finally:
        if os.path.exists(wav_path):
            os.unlink(wav_path)


# ──────────────────────────────────────────────────
#  WHISPER ENGINE (faster-whisper, charge a la demande)
#  4x plus rapide que openai-whisper, meme qualite
# ──────────────────────────────────────────────────
whisper_model = None
WHISPER_MODEL_SIZE = os.environ.get("WHISPER_MODEL", "base")


def find_whisper_model():
    """Trouve le modele faster-whisper local (pre-telecharge) ou retourne le nom pour auto-download."""
    script_dir = os.path.dirname(os.path.abspath(__file__))
    # Chercher le modele pre-telecharge dans plusieurs emplacements
    model_dir_name = f"whisper-model-{WHISPER_MODEL_SIZE}"
    for base in [script_dir, os.getcwd(), os.path.join(script_dir, "..")]:
        candidate = os.path.join(base, model_dir_name)
        if os.path.isdir(candidate) and os.path.exists(os.path.join(candidate, "model.bin")):
            print(f"[Whisper] Modele local trouve: {candidate}")
            return candidate
    # Pas trouve localement — faster-whisper telechargera depuis HuggingFace
    print(f"[Whisper] Pas de modele local, utilisation du modele '{WHISPER_MODEL_SIZE}' (telechargement auto si absent)")
    return WHISPER_MODEL_SIZE


def load_whisper():
    """Charge le modele faster-whisper (une seule fois, lazy)."""
    global whisper_model
    if whisper_model is not None:
        return True

    try:
        from faster_whisper import WhisperModel
        model_path = find_whisper_model()
        print(f"[Whisper] Chargement du modele '{model_path}'...")
        whisper_model = WhisperModel(model_path, device="cpu", compute_type="int8")
        print(f"[Whisper] Modele pret !")
        return True
    except ImportError:
        print("[Whisper] Module 'faster-whisper' non installe.", file=sys.stderr)
        return False
    except Exception as e:
        print(f"[Whisper] Erreur chargement: {e}", file=sys.stderr)
        return False


def check_whisper_available():
    """Verifie si faster-whisper est installe sans charger le modele."""
    try:
        from faster_whisper import WhisperModel
        return True
    except ImportError:
        return False


def transcribe_whisper(audio_path):
    """Transcrit un fichier audio avec faster-whisper."""
    if not load_whisper():
        raise RuntimeError("Whisper non disponible. Installez faster-whisper.")

    wav_path = convert_to_wav(audio_path)
    try:
        segments, info = whisper_model.transcribe(
            wav_path,
            language="fr",
            task="transcribe",
            beam_size=5,
            vad_filter=True  # filtre les silences
        )
        text = " ".join(seg.text.strip() for seg in segments)
        return text
    finally:
        if os.path.exists(wav_path):
            os.unlink(wav_path)


# ──────────────────────────────────────────────────
#  AUDIO CONVERSION (partage entre les deux moteurs)
# ──────────────────────────────────────────────────
def convert_to_wav(input_path):
    """Convertit n'importe quel format audio en WAV 16kHz mono via ffmpeg."""
    wav_path = input_path + ".wav"
    try:
        subprocess.run([
            "ffmpeg", "-y", "-i", input_path,
            "-ar", "16000", "-ac", "1", "-f", "wav", wav_path
        ], capture_output=True, check=True)
        return wav_path
    except FileNotFoundError:
        print("[Erreur] ffmpeg non trouve !", file=sys.stderr)
        raise
    except subprocess.CalledProcessError as e:
        print(f"[Erreur] ffmpeg: {e.stderr.decode()}", file=sys.stderr)
        raise


# ──────────────────────────────────────────────────
#  HTTP SERVER
# ──────────────────────────────────────────────────
class TranscriptionHandler(BaseHTTPRequestHandler):
    """Gere les requetes HTTP entrantes."""

    def _cors(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")

    def _json(self, code, data):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self._cors()
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors()
        self.end_headers()

    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == "/health":
            engines = ["vosk"]
            if check_whisper_available():
                engines.append("whisper")
            self._json(200, {
                "status": "ok",
                "engines": engines,
                "vosk_model": os.path.basename(vosk_model_path),
                "whisper_model": WHISPER_MODEL_SIZE if check_whisper_available() else None,
                "whisper_available": check_whisper_available()
            })
        else:
            self._json(404, {"error": "Not found"})

    def do_POST(self):
        parsed = urlparse(self.path)
        if parsed.path != "/transcribe":
            self._json(404, {"error": "Not found"})
            return

        # Determine engine from query param (default: vosk)
        qs = parse_qs(parsed.query)
        engine = qs.get("engine", ["vosk"])[0].lower()

        if engine not in ("vosk", "whisper"):
            self._json(400, {"error": f"Moteur inconnu: {engine}. Utilisez 'vosk' ou 'whisper'."})
            return

        if engine == "whisper" and not check_whisper_available():
            self._json(400, {"error": "Whisper non installe sur ce poste."})
            return

        try:
            length = int(self.headers.get("Content-Length", 0))
            if length == 0:
                self._json(400, {"error": "No audio data"})
                return

            audio_data = self.rfile.read(length)

            suffix = ".webm"
            content_type = self.headers.get("Content-Type", "")
            if "wav" in content_type:
                suffix = ".wav"
            elif "mp3" in content_type or "mpeg" in content_type:
                suffix = ".mp3"
            elif "ogg" in content_type:
                suffix = ".ogg"
            elif "mp4" in content_type:
                suffix = ".mp4"

            with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
                tmp.write(audio_data)
                tmp_path = tmp.name

            try:
                if engine == "whisper":
                    text = transcribe_whisper(tmp_path)
                else:
                    text = transcribe_vosk(tmp_path)
                self._json(200, {"success": True, "text": text, "engine": engine})
            finally:
                if os.path.exists(tmp_path):
                    os.unlink(tmp_path)

        except Exception as e:
            print(f"[Erreur transcription/{engine}] {e}", file=sys.stderr)
            self._json(500, {"error": str(e)})

    def log_message(self, format, *args):
        print(f"[Server] {args[0]}")


def main():
    base_port = int(os.environ.get("WHISPER_PORT", 5876))
    server = None

    for port in range(base_port, base_port + 10):
        try:
            server = HTTPServer(("127.0.0.1", port), TranscriptionHandler)
            break
        except OSError as e:
            print(f"[ZerdaTime] Port {port} indisponible ({e}), essai suivant...")

    if server is None:
        print(f"[ZerdaTime] ERREUR : aucun port disponible entre {base_port} et {base_port + 9}")
        sys.exit(1)

    whisper_status = "disponible" if check_whisper_available() else "non installe"
    print(f"[ZerdaTime] Serveur demarre sur http://localhost:{port}")
    print(f"[ZerdaTime] Moteurs : Vosk (pret), Whisper ({whisper_status})")
    print(f"[ZerdaTime] Endpoints :")
    print(f"  GET  /health                -> statut + moteurs disponibles")
    print(f"  POST /transcribe            -> transcription Vosk (defaut)")
    print(f"  POST /transcribe?engine=whisper -> transcription Whisper")
    print(f"[ZerdaTime] Ctrl+C pour arreter")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[ZerdaTime] Arret du serveur.")
        server.server_close()


if __name__ == "__main__":
    main()
