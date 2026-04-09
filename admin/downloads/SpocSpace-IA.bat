@echo off
title SpocSpace IA
echo.
echo  ========================================
echo   SpocSpace IA - Demarrage
echo   Ne fermez pas cette fenetre !
echo  ========================================
echo.

:: ── Raccourci Bureau ──
if not exist "%USERPROFILE%\Desktop\SpocSpace IA.bat" (
    echo  Creation du raccourci Bureau...
    copy "%~f0" "%USERPROFILE%\Desktop\SpocSpace IA.bat" >nul 2>&1
    if exist "%USERPROFILE%\Desktop\SpocSpace IA.bat" echo        OK
    echo.
)

:: ── Trouver Ollama ──
echo  [1/3] Recherche d'Ollama...
set "OLLAMA_CMD="
if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" set "OLLAMA_CMD=%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
if not defined OLLAMA_CMD if exist "%ProgramFiles%\Ollama\ollama.exe" set "OLLAMA_CMD=%ProgramFiles%\Ollama\ollama.exe"
if not defined OLLAMA_CMD (
    echo        ERREUR - Ollama non trouve
    echo        Telechargez-le sur https://ollama.com/download
    echo.
    pause
    goto EOF
)
echo        OK

:: ── Verifier si Ollama tourne deja ──
echo  [2/3] Demarrage d'Ollama...
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 2 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 (
    echo        OK - deja en ligne
    goto MODELE
)

:: Lancer Ollama
set "OLLAMA_ORIGINS=*"
start /min "" "%OLLAMA_CMD%" serve
echo        Lancement...

:: Attendre 3s
timeout /t 3 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 3 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        3s...
timeout /t 3 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 3 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        6s...
timeout /t 3 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 3 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        9s...
timeout /t 3 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 3 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        12s...
timeout /t 5 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 5 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        17s...
timeout /t 5 /nobreak >nul
powershell -Command "try{Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 5 | Out-Null;exit 0}catch{exit 1}" >nul 2>&1
if %errorlevel% equ 0 goto OLLAMA_OK

echo        ERREUR - Ollama ne demarre pas
echo.
pause
goto EOF

:OLLAMA_OK
echo        OK - Ollama en ligne

:MODELE
:: ── Verifier modele ──
echo  [3/3] Verification du modele...
powershell -Command "$r=Invoke-WebRequest -Uri http://localhost:11434/api/tags -UseBasicParsing -TimeoutSec 5;if($r.Content -match 'gemma3'){exit 0}else{exit 1}" >nul 2>&1
if %errorlevel% neq 0 (
    echo        Telechargement gemma3:4b ...
    "%OLLAMA_CMD%" pull gemma3:4b
)
echo        OK
echo.

:: ── Vosk ──
set "WHISPER_DIR=%LOCALAPPDATA%\SpocSpaceWhisper"
set "HAS_VOSK=0"
set "PYTHON_CMD="
if exist "%WHISPER_DIR%\python\python.exe" set "PYTHON_CMD=%WHISPER_DIR%\python\python.exe"
if not defined PYTHON_CMD (
    where python >nul 2>&1 && set "PYTHON_CMD=python"
)
if defined PYTHON_CMD (
    if exist "%WHISPER_DIR%\whisper_server.py" set "HAS_VOSK=1"
)

echo  ========================================
echo.
echo   SpocSpace IA pret !
echo.
echo   Ollama : localhost:11434 (gemma3:4b)
if "%HAS_VOSK%"=="1" echo   Vosk   : localhost:5876
echo.
echo   Ouvrez SpocSpace dans votre navigateur
echo   NE FERMEZ PAS cette fenetre !
echo.
echo  ========================================
echo.

if "%HAS_VOSK%"=="1" (
    set "WHISPER_PORT=5876"
    set "VOSK_MODEL=%WHISPER_DIR%\vosk-model-fr-0.22"
    "%PYTHON_CMD%" "%WHISPER_DIR%\whisper_server.py"
)

:: Garder la fenetre ouverte
:BOUCLE
echo  Serveurs actifs. Fermez cette fenetre pour arreter.
timeout /t 3600 /nobreak >nul
goto BOUCLE

:EOF
