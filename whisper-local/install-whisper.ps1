# ============================================================
# ZerdaTime - Installation complete : Vosk + Ollama + Mistral
# ============================================================
# Usage :
#   Clic droit > Executer avec PowerShell
#   ou : powershell -ExecutionPolicy Bypass -File install-whisper.ps1
# ============================================================
# Le script cherche ZerdaTime-IA-Install.zip dans le meme
# dossier ou dans Telechargements, le dezippe, puis installe.
# ============================================================

$ErrorActionPreference = "Stop"
$WHISPER_DIR = "$env:LOCALAPPDATA\ZerdaTimeWhisper"
$VENV_DIR    = "$WHISPER_DIR\venv"
$SERVER_PY   = "$WHISPER_DIR\whisper_server.py"
$PORT        = 5876

# URL de base (fallback si fichiers locaux absents)
$BASE_URL = "https://zkriva.com/zerdatime/whisper-local"

# -- Fonctions d'affichage (ASCII only) --
function Write-Step($msg)  { Write-Host "`n> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)    { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Write-Warn($msg)  { Write-Host "  [!!] $msg" -ForegroundColor Yellow }
function Write-Err($msg)   { Write-Host "  [ERR] $msg" -ForegroundColor Red }

Write-Host ""
Write-Host "============================================" -ForegroundColor DarkCyan
Write-Host "  ZerdaTime - Installation IA locale" -ForegroundColor DarkCyan
Write-Host "  Vosk (transcription) + Ollama (structuration)" -ForegroundColor DarkCyan
Write-Host "============================================" -ForegroundColor DarkCyan

# Dossier contenant ce script (et les fichiers d installation)
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# -- Creer le dossier principal --
if (-not (Test-Path $WHISPER_DIR)) {
    New-Item -ItemType Directory -Path $WHISPER_DIR -Force | Out-Null
}

# ============================================================
# PARTIE 1 : PYTHON (portable, sans installation systeme)
# ============================================================

Write-Step "Verification de Python..."

$pythonCmd = $null
$pythonPortable = "$WHISPER_DIR\python\python.exe"

# Verifier si Python portable est deja installe
if (Test-Path $pythonPortable) {
    $pythonCmd = $pythonPortable
    Write-Ok "Python portable deja installe"
} else {
    # Verifier si Python systeme existe
    foreach ($cmd in @("python", "python3", "py")) {
        try {
            $ver = & $cmd --version 2>&1
            if ($ver -match "Python 3\.(\d+)") {
                $minor = [int]$Matches[1]
                if ($minor -ge 8) {
                    $pythonCmd = $cmd
                    Write-Ok "Python systeme trouve : $ver ($cmd)"
                    break
                }
            }
        } catch {}
    }
}

if (-not $pythonCmd) {
    $pythonZip = "$WHISPER_DIR\python-embedded.zip"
    $pythonDir = "$WHISPER_DIR\python"

    # Chercher le fichier dans le meme dossier que le script
    $localPythonZip = Join-Path $scriptDir "python-3.11.9-embed-amd64.zip"
    if (Test-Path $localPythonZip) {
        Write-Host "  Fichier Python trouve localement..." -ForegroundColor Gray
        Copy-Item $localPythonZip $pythonZip -Force
        Write-Ok "Python copie depuis le dossier d installation"
    } else {
        Write-Host "  Telechargement de Python portable depuis ZerdaTime..." -ForegroundColor Gray
        Invoke-WebRequest -Uri "$BASE_URL/downloads/python-3.11.9-embed-amd64.zip" -OutFile $pythonZip -UseBasicParsing
        Write-Ok "Python telecharge"
    }

    try {

        # Extraire
        if (-not (Test-Path $pythonDir)) { New-Item -ItemType Directory -Path $pythonDir -Force | Out-Null }
        Expand-Archive -Path $pythonZip -DestinationPath $pythonDir -Force
        Remove-Item $pythonZip -Force

        # Activer pip dans Python embedded : decommenter import site dans python311._pth
        $pthFile = Get-ChildItem -Path $pythonDir -Filter "python*._pth" | Select-Object -First 1
        if ($pthFile) {
            $pthContent = Get-Content $pthFile.FullName
            $pthContent = $pthContent -replace "^#\s*import site", "import site"
            $pthContent | Set-Content $pthFile.FullName
        }

        # Installer pip
        Write-Host "  Installation de pip..." -ForegroundColor Gray
        $ErrorActionPreference = "Continue"
        $getPipUrl = "https://bootstrap.pypa.io/get-pip.py"
        $getPipPath = "$pythonDir\get-pip.py"
        Invoke-WebRequest -Uri $getPipUrl -OutFile $getPipPath -UseBasicParsing
        & "$pythonDir\python.exe" $getPipPath --quiet 2>&1 | Out-Null
        Remove-Item $getPipPath -Force -ErrorAction SilentlyContinue
        $ErrorActionPreference = "Stop"

        $pythonCmd = "$pythonDir\python.exe"
        Write-Ok "Python portable installe"
    } catch {
        Write-Err "Impossible d installer Python."
        Write-Host "  Erreur : $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  Installez Python 3.8+ depuis https://python.org puis relancez ce script." -ForegroundColor White
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }
}

# ============================================================
# PARTIE 2 : VOSK (serveur de transcription)
# ============================================================

Write-Host ""
Write-Host "--------------------------------------------" -ForegroundColor DarkCyan
Write-Host "  Transcription vocale (Vosk)" -ForegroundColor DarkCyan
Write-Host "--------------------------------------------" -ForegroundColor DarkCyan

# -- Copier le serveur Python --
Write-Step "Preparation du serveur Vosk..."

$srcServer = Join-Path $scriptDir "whisper_server.py"
if (Test-Path $srcServer) {
    Copy-Item $srcServer $SERVER_PY -Force
    Write-Ok "whisper_server.py copie"
} else {
    Write-Host "  Telechargement depuis ZerdaTime..." -ForegroundColor Gray
    try {
        Invoke-WebRequest -Uri "$BASE_URL/whisper_server.py" -OutFile $SERVER_PY -UseBasicParsing
        Write-Ok "whisper_server.py telecharge"
    } catch {
        Write-Err "Impossible de recuperer whisper_server.py"
        Read-Host "Appuyez sur Entree pour quitter"
        exit 1
    }
}

# -- Creer l environnement virtuel ou utiliser Python portable --
Write-Step "Configuration de l environnement Python..."

# Si Python portable, on installe directement dedans (pas de venv)
$isPortable = $pythonCmd -like "*$WHISPER_DIR*"

if ($isPortable) {
    $pipExe = "$WHISPER_DIR\python\Scripts\pip.exe"
    $pythonExe = $pythonCmd
    Write-Ok "Utilisation de Python portable (pas de venv)"
} else {
    if (-not (Test-Path "$VENV_DIR\Scripts\python.exe")) {
        & $pythonCmd -m venv $VENV_DIR
        Write-Ok "venv cree"
    } else {
        Write-Ok "venv existe deja"
    }
    $pipExe = "$VENV_DIR\Scripts\pip.exe"
    $pythonExe = "$VENV_DIR\Scripts\python.exe"
}

# -- Installer vosk + faster-whisper --
Write-Step "Installation de vosk..."
Write-Host "  Cela peut prendre quelques minutes la premiere fois..." -ForegroundColor Gray

$ErrorActionPreference = "Continue"
& $pipExe install --upgrade pip --quiet 2>&1 | Out-Null
& $pipExe install vosk 2>&1 | Out-Null
Write-Ok "vosk installe"

Write-Step "Installation de faster-whisper (transcription haute precision)..."
# Chercher les wheels pre-telechargees (dans le dossier USB)
$wheelDir = $null
foreach ($p in @(
    "$scriptDir\whisper-wheels",
    "$scriptDir\ZerdaTime-IA\whisper-wheels",
    "$PSScriptRoot\whisper-wheels",
    "$PSScriptRoot\ZerdaTime-IA\whisper-wheels"
)) {
    if (Test-Path "$p\faster_whisper*.whl") {
        $wheelDir = $p
        break
    }
}

if ($wheelDir) {
    Write-Host "  Installation offline depuis $wheelDir..." -ForegroundColor Gray
    & $pipExe install --no-index --find-links="$wheelDir" faster-whisper 2>&1 | Out-Null
    Write-Ok "faster-whisper installe (offline)"
} else {
    Write-Host "  Wheels non trouvees, installation depuis internet..." -ForegroundColor Yellow
    & $pipExe install faster-whisper 2>&1 | Out-Null
    Write-Ok "faster-whisper installe (online)"
}
# -- Modele faster-whisper pre-telecharge --
Write-Step "Installation du modele faster-whisper..."
$whisperModelDir = "$WHISPER_DIR\whisper-model-base"
if (Test-Path "$whisperModelDir\model.bin") {
    Write-Ok "Modele faster-whisper deja present"
} else {
    $whisperModelSource = $null
    $searchPaths = @(
        "$scriptDir\whisper-model-base",
        "$scriptDir\ZerdaTime-IA\whisper-model-base",
        "$PSScriptRoot\whisper-model-base",
        "$PSScriptRoot\ZerdaTime-IA\whisper-model-base"
    )
    foreach ($p in $searchPaths) {
        if (Test-Path "$p\model.bin") {
            $whisperModelSource = $p
            break
        }
    }
    if ($whisperModelSource) {
        Write-Host "  Copie du modele faster-whisper (~141 Mo)..." -ForegroundColor Gray
        Copy-Item -Path $whisperModelSource -Destination $whisperModelDir -Recurse -Force
        Write-Ok "Modele faster-whisper installe"
    } else {
        Write-Warn "Modele faster-whisper introuvable dans le pack."
        Write-Host "  Le modele sera telecharge au premier appel Whisper (~140 Mo)" -ForegroundColor Yellow
    }
}

$ErrorActionPreference = "Stop"

# ============================================================
# PARTIE 3 : FFMPEG
# ============================================================

Write-Step "Verification de ffmpeg..."

$ffmpegOk = $false
$ffmpegLocal = "$WHISPER_DIR\ffmpeg\ffmpeg.exe"

# Verifier ffmpeg local (installe par nous)
if (Test-Path $ffmpegLocal) {
    $ffmpegOk = $true
    Write-Ok "ffmpeg local trouve"
    # S assurer qu il est dans le PATH de la session
    $env:Path = "$WHISPER_DIR\ffmpeg;" + $env:Path
} else {
    # Verifier ffmpeg systeme
    try {
        $ffVer = & ffmpeg -version 2>&1
        if ($ffVer -match "ffmpeg version") {
            $ffmpegOk = $true
            Write-Ok "ffmpeg systeme trouve"
        }
    } catch {}
}

if (-not $ffmpegOk) {
    $ffmpegZip = "$WHISPER_DIR\ffmpeg.zip"
    $ffmpegDir = "$WHISPER_DIR\ffmpeg"

    # Chercher le fichier dans le meme dossier que le script
    $localFfmpegZip = Join-Path $scriptDir "ffmpeg.zip"

    try {
        if (Test-Path $localFfmpegZip) {
            Write-Host "  Fichier ffmpeg trouve localement..." -ForegroundColor Gray
            Copy-Item $localFfmpegZip $ffmpegZip -Force
            Write-Ok "ffmpeg copie depuis le dossier d installation"
        } else {
            Write-Host "  Telechargement de ffmpeg depuis ZerdaTime..." -ForegroundColor Gray
            Invoke-WebRequest -Uri "$BASE_URL/downloads/ffmpeg.zip" -OutFile $ffmpegZip -UseBasicParsing
            Write-Ok "ffmpeg telecharge"
        }

        # Extraire
        Write-Host "  Extraction de ffmpeg..." -ForegroundColor Gray
        $ffmpegTmp = "$WHISPER_DIR\ffmpeg_tmp"
        Expand-Archive -Path $ffmpegZip -DestinationPath $ffmpegTmp -Force

        # Trouver le dossier bin (la structure du zip contient un sous-dossier)
        $ffmpegBin = Get-ChildItem -Path $ffmpegTmp -Recurse -Filter "ffmpeg.exe" | Select-Object -First 1
        if ($ffmpegBin) {
            if (-not (Test-Path $ffmpegDir)) { New-Item -ItemType Directory -Path $ffmpegDir -Force | Out-Null }
            Copy-Item $ffmpegBin.FullName "$ffmpegDir\ffmpeg.exe" -Force
            # Aussi copier ffprobe si present
            $ffprobe = Join-Path $ffmpegBin.DirectoryName "ffprobe.exe"
            if (Test-Path $ffprobe) { Copy-Item $ffprobe "$ffmpegDir\ffprobe.exe" -Force }
        }

        Remove-Item $ffmpegZip -Force -ErrorAction SilentlyContinue
        Remove-Item $ffmpegTmp -Recurse -Force -ErrorAction SilentlyContinue

        $env:Path = "$ffmpegDir;" + $env:Path
        Write-Ok "ffmpeg installe localement"
    } catch {
        Write-Warn "Impossible de telecharger ffmpeg depuis ZerdaTime."
        Write-Host "  Tentative via winget..." -ForegroundColor Gray
        try {
            winget install -e --id Gyan.FFmpeg --accept-package-agreements --accept-source-agreements
            $env:Path = [Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [Environment]::GetEnvironmentVariable("Path", "User")
            Write-Ok "ffmpeg installe via winget"
        } catch {
            Write-Warn "ffmpeg non installe. Installez-le manuellement depuis ffmpeg.org"
        }
    }
}

# -- Modele Vosk FR --
Write-Step "Installation du modele Vosk francais..."
$modelDir = "$WHISPER_DIR\vosk-model-fr-0.22"
if (Test-Path "$modelDir\conf\model.conf") {
    Write-Ok "Modele vosk-model-fr-0.22 deja present"
} else {
    # Chercher dans le dossier d'installation dezipe
    $voskSource = $null
    $searchPaths = @(
        "$scriptDir\vosk-model-fr-0.22",
        "$scriptDir\ZerdaTime-IA\vosk-model-fr-0.22",
        "$PSScriptRoot\vosk-model-fr-0.22",
        "$PSScriptRoot\ZerdaTime-IA\vosk-model-fr-0.22"
    )
    foreach ($p in $searchPaths) {
        if (Test-Path "$p\conf\model.conf") {
            $voskSource = $p
            break
        }
    }
    if ($voskSource) {
        Write-Host "  Copie du modele Vosk (~1.4 Go, patientez)..." -ForegroundColor Gray
        Copy-Item -Path $voskSource -Destination $modelDir -Recurse -Force
        Write-Ok "Modele Vosk installe"
    } else {
        Write-Host "  [!!] Modele Vosk introuvable dans le dossier d'installation." -ForegroundColor Red
        Write-Host "  Le modele sera telecharge au premier lancement (~1.4 Go)" -ForegroundColor Yellow
    }
}

# ============================================================
# PARTIE 4 : OLLAMA + MISTRAL (structuration IA)
# ============================================================

Write-Host ""
Write-Host "--------------------------------------------" -ForegroundColor DarkCyan
Write-Host "  Structuration IA (Ollama + Mistral)" -ForegroundColor DarkCyan
Write-Host "--------------------------------------------" -ForegroundColor DarkCyan

Write-Step "Verification d Ollama..."

$ollamaOk = $false
$ollamaExe = $null

# Chercher ollama.exe sur le systeme (sans l executer — evite les blocages)
$ollamaPaths = @(
    "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe",
    "$env:ProgramFiles\Ollama\ollama.exe",
    "${env:ProgramFiles(x86)}\Ollama\ollama.exe",
    "$env:LOCALAPPDATA\Ollama\ollama.exe"
)
foreach ($p in $ollamaPaths) {
    if (Test-Path $p) {
        $ollamaExe = $p
        $ollamaOk = $true
        Write-Ok "Ollama trouve : $p"
        break
    }
}

# Si pas trouve, aussi chercher dans le PATH
if (-not $ollamaOk) {
    $ollamaInPath = Get-Command "ollama" -ErrorAction SilentlyContinue
    if ($ollamaInPath) {
        $ollamaExe = $ollamaInPath.Source
        $ollamaOk = $true
        Write-Ok "Ollama trouve dans PATH : $ollamaExe"
    }
}

if (-not $ollamaOk) {
    $ollamaInstaller = "$WHISPER_DIR\OllamaSetup.exe"

    # Chercher OllamaSetup.exe dans le meme dossier que le script
    $localOllama = Join-Path $scriptDir "OllamaSetup.exe"
    if (Test-Path $localOllama) {
        Write-Host "  Fichier OllamaSetup.exe trouve localement..." -ForegroundColor Gray
        Copy-Item $localOllama $ollamaInstaller -Force
        Write-Ok "OllamaSetup.exe copie"
    } else {
        try {
            Write-Host "  Telechargement d Ollama depuis ollama.com (~1.7 Go)..." -ForegroundColor Gray
            Invoke-WebRequest -Uri "https://ollama.com/download/OllamaSetup.exe" -OutFile $ollamaInstaller -UseBasicParsing
            Write-Ok "Ollama telecharge"
        } catch {
            Write-Err "Impossible de telecharger Ollama."
            Write-Host "  Installez Ollama depuis https://ollama.com/download" -ForegroundColor White
        }
    }

    if (Test-Path $ollamaInstaller) {
        Write-Host "  Installation d Ollama (peut prendre 1-2 minutes)..." -ForegroundColor Gray
        Write-Host "  Une fenetre Ollama peut s ouvrir, c est normal." -ForegroundColor Gray

        $proc = Start-Process -FilePath $ollamaInstaller -ArgumentList "/VERYSILENT /NORESTART /CLOSEAPPLICATIONS /SUPPRESSMSGBOXES" -PassThru
        # Attendre max 120s (l'installeur lance parfois l'app GUI et ne se ferme pas)
        $waited = 0
        while (-not $proc.HasExited -and $waited -lt 120) {
            Start-Sleep -Seconds 3
            $waited += 3
            # Si ollama.exe existe deja, l'installation est terminee
            foreach ($p in $ollamaPaths) {
                if (Test-Path $p) { break }
            }
        }
        if (-not $proc.HasExited) {
            Write-Host "  Installeur toujours actif, on continue..." -ForegroundColor Gray
            Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        }

        # Rafraichir le PATH
        $env:Path = [Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [Environment]::GetEnvironmentVariable("Path", "User")

        Start-Sleep -Seconds 2
        foreach ($p in $ollamaPaths) {
            if (Test-Path $p) {
                $ollamaExe = $p
                $ollamaOk = $true
                Write-Ok "Ollama installe : $p"
                break
            }
        }
        if (-not $ollamaOk) {
            $ollamaInPath = Get-Command "ollama" -ErrorAction SilentlyContinue
            if ($ollamaInPath) {
                $ollamaExe = $ollamaInPath.Source
                $ollamaOk = $true
                Write-Ok "Ollama installe"
            }
        }

        # Fermer l app Ollama GUI si elle s est lancee toute seule
        $ErrorActionPreference = "Continue"
        Stop-Process -Name "Ollama" -ErrorAction SilentlyContinue 2>&1 | Out-Null
        $ErrorActionPreference = "Stop"

        Remove-Item $ollamaInstaller -Force -ErrorAction SilentlyContinue

        if (-not $ollamaOk) {
            Write-Warn "Ollama semble installe mais introuvable. Redemarrez l ordinateur puis relancez ce script."
        }
    }
}

# -- Demarrer Ollama --
if ($ollamaOk) {
    Write-Step "Demarrage d Ollama..."

    # Fonction de test rapide via TCP (pas de HTTP qui bloque)
    function Test-OllamaPort {
        try {
            $tcp = New-Object System.Net.Sockets.TcpClient
            $tcp.Connect("127.0.0.1", 59876)
            $tcp.Close()
            return $true
        } catch { return $false }
    }

    # Fermer toute instance GUI d Ollama qui pourrait bloquer le port
    $ErrorActionPreference = "Continue"
    Get-Process -Name "Ollama" -ErrorAction SilentlyContinue | Where-Object { $_.MainWindowTitle -ne "" } | Stop-Process -Force -ErrorAction SilentlyContinue 2>&1 | Out-Null
    $ErrorActionPreference = "Stop"

    $ollamaRunning = Test-OllamaPort
    if ($ollamaRunning) {
        Write-Ok "Ollama deja en cours d execution"
    }

    if (-not $ollamaRunning) {
        # Forcer le port 59876 (evite conflit VS Code sur 11434)
        $env:OLLAMA_HOST = "127.0.0.1:59876"
        # Autoriser les requetes du navigateur (CORS)
        $env:OLLAMA_ORIGINS = "*"

        # Tuer toute instance Ollama existante (GUI ou serve sur mauvais port)
        Stop-Process -Name "ollama" -Force -ErrorAction SilentlyContinue
        Stop-Process -Name "ollama app" -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2

        Write-Host "  Lancement d Ollama sur le port 59876..." -ForegroundColor Gray

        # Lancer ollama serve en arriere-plan
        $serveCmd = if ($ollamaExe) { $ollamaExe } else { "ollama" }
        Start-Process -FilePath $serveCmd -ArgumentList "serve" -WindowStyle Hidden

        $waited = 0
        $maxWait = 30
        while ($waited -lt $maxWait) {
            Start-Sleep -Seconds 1
            $waited++
            $pct = [math]::Round($waited / $maxWait * 100)
            $bar = ('#' * [math]::Floor($pct / 5)).PadRight(20, '.')
            Write-Host "`r  [$bar] Attente d Ollama... ${waited}s / ${maxWait}s" -NoNewline -ForegroundColor Gray
            if (Test-OllamaPort) {
                Write-Host ""
                Write-Ok "Ollama demarre (${waited}s)"
                $ollamaRunning = $true
                break
            }
        }
        if (-not $ollamaRunning) {
            Write-Host ""
            Write-Warn "Ollama n a pas demarre en ${maxWait}s."
            Write-Host "  Essayez de lancer Ollama manuellement depuis le menu Demarrer" -ForegroundColor White
            Write-Host "  puis relancez ce script." -ForegroundColor White
        }
    }

    # -- Installer les modeles IA (copie locale depuis le ZIP) --
    Write-Step "Installation des modeles IA (TinyLlama, Phi3, Mistral)..."

    $ollamaHome = "$env:USERPROFILE\.ollama"
    $blobsDir = "$ollamaHome\models\blobs"
    $localModelDir = Join-Path $scriptDir "ollama-model"

    if (-not (Test-Path $blobsDir)) { New-Item -ItemType Directory -Path $blobsDir -Force | Out-Null }

    # Copier tous les blobs d'un coup (partages entre modeles)
    if (Test-Path "$localModelDir\blobs") {
        $blobFiles = Get-ChildItem -Path "$localModelDir\blobs" -File
        $totalBlobs = $blobFiles.Count
        $currentBlob = 0
        Write-Host "  Copie de $totalBlobs blobs (peut prendre quelques minutes)..." -ForegroundColor Gray
        foreach ($blob in $blobFiles) {
            $currentBlob++
            $sizeMB = [math]::Round($blob.Length / 1MB, 1)
            $pct = [math]::Round($currentBlob / $totalBlobs * 100)
            Write-Host "`r  [$pct%] Blob $currentBlob/$totalBlobs ($sizeMB Mo)..." -NoNewline -ForegroundColor Gray
            if (-not (Test-Path "$blobsDir\$($blob.Name)")) {
                Copy-Item $blob.FullName "$blobsDir\$($blob.Name)" -Force
            }
        }
        Write-Host ""
    }

    # Copier les manifests pour chaque modele
    $models = @(
        @{ name = "tinyllama"; tag = "latest"; label = "TinyLlama (1.1 Go)" },
        @{ name = "phi3";      tag = "mini";   label = "Phi-3 Mini (2.3 Go)" },
        @{ name = "mistral";   tag = "latest"; label = "Mistral 7B (4.4 Go)" }
    )

    foreach ($m in $models) {
        $srcManifest = "$localModelDir\manifests\registry.ollama.ai\library\$($m.name)\$($m.tag)"
        $destDir = "$ollamaHome\models\manifests\registry.ollama.ai\library\$($m.name)"
        if (Test-Path $srcManifest) {
            if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
            Copy-Item $srcManifest "$destDir\$($m.tag)" -Force
            Write-Ok "$($m.label) installe"
        } else {
            Write-Warn "$($m.label) non trouve dans le pack"
        }
    }
}

# ============================================================
# PARTIE 5 : RACCOURCIS DE LANCEMENT
# ============================================================

Write-Step "Creation des raccourcis..."

# -- Raccourci unifie (Vosk + Ollama) --
$shortcutPathIA = [Environment]::GetFolderPath("Desktop") + "\ZerdaTime IA.lnk"
$batPathIA = "$WHISPER_DIR\start-zerdatime-ia.bat"

# Preparer le PATH ffmpeg dans le bat si installe localement
$ffmpegPathLine = ""
if (Test-Path "$WHISPER_DIR\ffmpeg\ffmpeg.exe") {
    $ffmpegPathLine = "set PATH=$WHISPER_DIR\ffmpeg;%PATH%"
}

$batLinesIA = @(
    "@echo off",
    "title ZerdaTime IA - Vosk + Ollama",
    "echo.",
    "echo  ============================================",
    "echo   ZerdaTime IA - Serveurs locaux",
    "echo   Vosk (transcription) + Ollama (structuration)",
    "echo   Ne fermez pas cette fenetre !",
    "echo  ============================================",
    "echo."
)
if ($ffmpegPathLine) { $batLinesIA += $ffmpegPathLine }
# Chemin ollama pour le BAT
$ollamaBatCmd = if ($ollamaExe) { """$ollamaExe""" } else { "ollama" }

$batLinesIA += @(
    "",
    ":: ---- Demarrer Ollama ----",
    "echo  [*] Verification d Ollama...",
    "curl -s http://localhost:59876/api/tags >nul 2>&1",
    "if not errorlevel 1 goto OLLAMA_OK",
    "",
    "echo  [*] Demarrage d Ollama en arriere-plan...",
    "start /B """" $ollamaBatCmd serve >nul 2>&1",
    "set /a WAIT_COUNT=0",
    "",
    ":OLLAMA_WAIT",
    "timeout /t 2 /nobreak >nul",
    "set /a WAIT_COUNT+=2",
    "echo  [*] Attente d Ollama... %WAIT_COUNT%s",
    "curl -s http://localhost:59876/api/tags >nul 2>&1",
    "if not errorlevel 1 goto OLLAMA_OK",
    "if %WAIT_COUNT% LSS 30 goto OLLAMA_WAIT",
    "echo  [!!] Ollama n a pas demarre en 30s.",
    "echo  Essayez de lancer Ollama depuis le menu Demarrer.",
    "goto OLLAMA_DONE",
    "",
    ":OLLAMA_OK",
    "echo  [OK] Ollama en cours d execution",
    "",
    ":OLLAMA_DONE",
    "",
    ":: ---- Verifier Mistral ----",
    "echo  [*] Verification du modele Mistral...",
    "curl -s http://localhost:59876/api/tags 2>nul | findstr /i ""mistral"" >nul 2>nul",
    "if not errorlevel 1 goto MISTRAL_OK",
    "",
    "echo  [!!] Mistral non trouve. Telechargement en cours...",
    "echo  [!!] Cela peut prendre 10-30 min (4 Go). Patientez.",
    "echo.",
    "$ollamaBatCmd pull mistral",
    "echo  [OK] Mistral installe",
    "goto MISTRAL_DONE",
    "",
    ":MISTRAL_OK",
    "echo  [OK] Modele Mistral pret",
    "",
    ":MISTRAL_DONE",
    "echo.",
    "echo  ============================================",
    "echo   Tout est pret !",
    "echo   Demarrage du serveur Vosk...",
    "echo  ============================================",
    "echo.",
    "",
    ":: ---- Demarrer Vosk (bloquant) ----",
    "set WHISPER_PORT=$PORT",
    """$pythonExe"" ""$SERVER_PY""",
    "",
    "echo.",
    "echo  [!!] Le serveur Vosk s est arrete.",
    "echo  Appuyez sur une touche pour fermer...",
    "pause >nul"
)
$batLinesIA -join "`r`n" | Set-Content -Path $batPathIA -Encoding ASCII
Write-Ok "start-zerdatime-ia.bat cree"

try {
    $WshShell = New-Object -ComObject WScript.Shell
    $shortcutIA = $WshShell.CreateShortcut($shortcutPathIA)
    $shortcutIA.TargetPath = $batPathIA
    $shortcutIA.WorkingDirectory = $WHISPER_DIR
    $shortcutIA.Description = "Lancer Vosk + Ollama pour ZerdaTime"
    $shortcutIA.Save()
    Write-Ok "Raccourci cree sur le Bureau : ZerdaTime IA"
} catch {
    Write-Warn "Impossible de creer le raccourci"
    Write-Host "  Lancez manuellement : $batPathIA" -ForegroundColor White
}

# Supprimer l ancien raccourci Vosk seul s il existe
$oldVoskLnk = [Environment]::GetFolderPath("Desktop") + "\ZerdaTime Whisper.lnk"
if (Test-Path $oldVoskLnk) { Remove-Item $oldVoskLnk -Force -ErrorAction SilentlyContinue }

# ============================================================
# RESUME FINAL
# ============================================================

# ============================================================
# TEST RAPIDE MISTRAL
# ============================================================

if ($ollamaRunning) {
    Write-Step "Test rapide de Mistral..."
    try {
        $testBody = @{
            model = "mistral"
            prompt = "Reponds juste OK"
            stream = $false
        } | ConvertTo-Json

        $testResult = Invoke-WebRequest -Uri "http://localhost:59876/api/generate" -Method POST -Body $testBody -ContentType "application/json" -UseBasicParsing -TimeoutSec 60
        $testData = $testResult.Content | ConvertFrom-Json

        if ($testData.response) {
            Write-Ok "Mistral repond correctement !"
        } else {
            Write-Warn "Mistral n a pas repondu. Il est peut-etre encore en chargement."
        }
    } catch {
        Write-Warn "Test Mistral echoue (le modele est peut-etre en cours de chargement)."
        Write-Host "  Premiere utilisation : Mistral peut prendre 30-60s pour se charger en memoire." -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  Installation terminee !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Composants installes :" -ForegroundColor White
Write-Host "    - Python    : $pythonCmd" -ForegroundColor White
Write-Host "    - Vosk      : Transcription vocale temps reel (port $PORT)" -ForegroundColor White
Write-Host "    - Whisper   : Transcription haute precision (faster-whisper)" -ForegroundColor White
Write-Host "    - ffmpeg    : Conversion audio" -ForegroundColor White
Write-Host "    - Ollama    : Serveur IA local (port 59876)" -ForegroundColor White
Write-Host "    - Mistral   : Modele de structuration de texte" -ForegroundColor White
Write-Host ""
Write-Host "  Raccourci sur le Bureau :" -ForegroundColor White
Write-Host "    - ZerdaTime IA : Lance Vosk + Ollama ensemble" -ForegroundColor White
Write-Host ""
Write-Host "  Dossier d installation : $WHISPER_DIR" -ForegroundColor Gray
Write-Host ""
Write-Host "  Pour utiliser :" -ForegroundColor Cyan
Write-Host "    1. Double-cliquez sur 'ZerdaTime IA' sur le Bureau" -ForegroundColor Cyan
Write-Host "    2. Attendez que la fenetre affiche 'Serveur demarre'" -ForegroundColor Cyan
Write-Host "    3. Ouvrez ZerdaTime dans votre navigateur" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Le serveur Vosk va demarrer maintenant..." -ForegroundColor Gray
Write-Host ""

$env:WHISPER_PORT = $PORT
& $pythonExe $SERVER_PY
