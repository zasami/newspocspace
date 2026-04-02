#!/bin/bash
# Regenere le ZIP d'installation SpocSpace-IA-Install.zip
# Le install.bat reste EN DEHORS du ZIP (telechargement separe)
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"

rm -f SpocSpace-IA-Install.zip

# Creer un dossier temporaire avec la bonne structure
TMPDIR=$(mktemp -d)
PACK="$TMPDIR/SpocSpace-IA"
mkdir -p "$PACK/ollama-model/blobs"

# Fichiers racine
cp install-whisper.ps1 "$PACK/"
cp whisper_server.py "$PACK/"
cp downloads/OllamaSetup.exe "$PACK/"
cp downloads/python-3.11.9-embed-amd64.zip "$PACK/"
cp downloads/ffmpeg.zip "$PACK/"

# Modeles Ollama (manifests + tous les blobs)
for model in mistral tinyllama; do
    src="downloads/ollama-model/manifests/registry.ollama.ai/library/$model"
    if [ -d "$src" ]; then
        echo "Ajout modele: $model"
        mkdir -p "$PACK/ollama-model/manifests/registry.ollama.ai/library/$model"
        cp "$src"/* "$PACK/ollama-model/manifests/registry.ollama.ai/library/$model/"
    fi
done

# phi3 has tag "mini" not "latest"
src="downloads/ollama-model/manifests/registry.ollama.ai/library/phi3"
if [ -d "$src" ]; then
    echo "Ajout modele: phi3:mini"
    mkdir -p "$PACK/ollama-model/manifests/registry.ollama.ai/library/phi3"
    cp "$src"/* "$PACK/ollama-model/manifests/registry.ollama.ai/library/phi3/"
fi

# Tous les blobs (partages entre modeles)
cp downloads/ollama-model/blobs/sha256-* "$PACK/ollama-model/blobs/"

# Modele Vosk FR (transcription vocale, ~1.4 Go)
if [ -d "downloads/vosk-model-fr-0.22" ]; then
    echo "Ajout du modele Vosk..."
    cp -r downloads/vosk-model-fr-0.22 "$PACK/vosk-model-fr-0.22"
else
    echo "ATTENTION: downloads/vosk-model-fr-0.22 introuvable !"
    echo "Lancez: bash downloads/fetch-installers.sh"
    exit 1
fi

# Wheels faster-whisper (transcription haute precision, ~89 Mo)
if [ -d "downloads/whisper-wheels" ]; then
    echo "Ajout des wheels faster-whisper..."
    cp -r downloads/whisper-wheels "$PACK/whisper-wheels"
else
    echo "ATTENTION: downloads/whisper-wheels introuvable !"
    echo "Lancez: pip3 download faster-whisper --dest downloads/whisper-wheels --platform win_amd64 --python-version 3.11 --only-binary=:all:"
fi

# Modele faster-whisper pre-telecharge (~141 Mo)
if [ -d "downloads/whisper-model-base" ]; then
    echo "Ajout du modele faster-whisper base..."
    cp -r downloads/whisper-model-base "$PACK/whisper-model-base"
else
    echo "ATTENTION: downloads/whisper-model-base introuvable !"
    echo "Le modele sera telecharge au premier appel Whisper (~140 Mo)"
fi

# Creer le ZIP (tout inclus)
cd "$TMPDIR"
zip -0 -r "$DIR/SpocSpace-IA-Install.zip" SpocSpace-IA/
cd "$DIR"
rm -rf "$TMPDIR"

echo "=== ZIP regenere ==="
ls -lh SpocSpace-IA-Install.zip
echo ""
echo "Fichiers a distribuer :"
echo "  1. SpocSpace-IA-Install.zip  (tout inclus)"
echo "  2. install.bat               (HORS du ZIP, separe)"
