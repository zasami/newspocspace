@echo off
title ZerdaTime IA - Vosk + Ollama
echo.
echo  ============================================
echo   ZerdaTime IA - Serveurs locaux
echo   Vosk (transcription) + Ollama (structuration)
echo   Ne fermez pas cette fenetre !
echo  ============================================
echo.

set "WHISPER_DIR=%LOCALAPPDATA%\ZerdaTimeWhisper"

:: Forcer Ollama sur le port 59876 (eviter conflit VS Code)
set "OLLAMA_HOST=127.0.0.1:59876"
:: Autoriser les requetes du navigateur (CORS)
set "OLLAMA_ORIGINS=*"

:: Ajouter ffmpeg au PATH si installe localement
if exist "%WHISPER_DIR%\ffmpeg\ffmpeg.exe" set "PATH=%WHISPER_DIR%\ffmpeg;%PATH%"

:: Trouver ollama.exe
set "OLLAMA_CMD="
if exist "%LOCALAPPDATA%\Programs\Ollama\ollama.exe" set "OLLAMA_CMD=%LOCALAPPDATA%\Programs\Ollama\ollama.exe"
if "%OLLAMA_CMD%"=="" if exist "%ProgramFiles%\Ollama\ollama.exe" set "OLLAMA_CMD=%ProgramFiles%\Ollama\ollama.exe"
if "%OLLAMA_CMD%"=="" set "OLLAMA_CMD=ollama"

:: Trouver python
set "PYTHON_CMD="
if exist "%WHISPER_DIR%\python\python.exe" set "PYTHON_CMD=%WHISPER_DIR%\python\python.exe"
if "%PYTHON_CMD%"=="" if exist "%WHISPER_DIR%\venv\Scripts\python.exe" set "PYTHON_CMD=%WHISPER_DIR%\venv\Scripts\python.exe"
if "%PYTHON_CMD%"=="" set "PYTHON_CMD=python"

set "SERVER_PY=%WHISPER_DIR%\whisper_server.py"

:: Verifier que les fichiers existent
if not exist "%SERVER_PY%" (
    echo  [ERREUR] whisper_server.py introuvable !
    echo  Chemin attendu : %SERVER_PY%
    echo  Relancez install.bat pour reinstaller.
    echo.
    pause
    exit /b 1
)

:: ============================================
:: ETAPE 1 : Arreter toute instance Ollama
:: ============================================
echo  [1/5] Nettoyage des anciennes instances Ollama...
taskkill /F /IM "ollama.exe" >nul 2>nul
taskkill /F /IM "ollama app.exe" >nul 2>nul
timeout /t 2 /nobreak >nul
echo  [OK] Nettoyage termine

:: ============================================
:: ETAPE 2 : Demarrer Ollama sur le port 59876
:: ============================================
echo  [2/5] Demarrage d Ollama (port 59876)...
start /MIN "" "%OLLAMA_CMD%" serve
set /a WAIT_COUNT=0

:OLLAMA_WAIT
timeout /t 2 /nobreak >nul
set /a WAIT_COUNT+=2
echo        Attente... %WAIT_COUNT%s
powershell -Command "try{$t=New-Object Net.Sockets.TcpClient;$t.Connect('127.0.0.1',59876);$t.Close();exit 0}catch{exit 1}" >nul 2>nul
if not errorlevel 1 goto OLLAMA_OK
if %WAIT_COUNT% LSS 30 goto OLLAMA_WAIT
echo  [!!] Ollama n a pas demarre en 30s.
echo  Verifiez votre installation d Ollama.
goto FIN

:OLLAMA_OK
echo  [OK] Ollama demarre sur le port 59876
echo.

:: ============================================
:: ETAPE 3 : Verifier les modeles IA
:: ============================================
echo  [3/5] Verification des modeles IA...
powershell -Command "try{$r=Invoke-WebRequest -Uri 'http://127.0.0.1:59876/api/tags' -UseBasicParsing -TimeoutSec 5;$d=$r.Content|ConvertFrom-Json;if($d.models.Count -gt 0){exit 0}else{exit 1}}catch{exit 1}" >nul 2>nul
if not errorlevel 1 goto MODELS_OK

:: Aucun modele detecte - redemarrer Ollama pour qu il relise les fichiers
echo        Aucun modele detecte. Redemarrage d Ollama...
taskkill /F /IM "ollama.exe" >nul 2>nul
timeout /t 3 /nobreak >nul
start /MIN "" "%OLLAMA_CMD%" serve
timeout /t 5 /nobreak >nul

:: Re-verifier
powershell -Command "try{$r=Invoke-WebRequest -Uri 'http://127.0.0.1:59876/api/tags' -UseBasicParsing -TimeoutSec 10;$d=$r.Content|ConvertFrom-Json;if($d.models.Count -gt 0){exit 0}else{exit 1}}catch{exit 1}" >nul 2>nul
if not errorlevel 1 goto MODELS_OK
echo  [!!] Aucun modele IA detecte. Relancez install.bat.
goto MODELS_DONE

:MODELS_OK
:: Afficher les modeles disponibles
powershell -Command "try{$r=Invoke-WebRequest -Uri 'http://127.0.0.1:59876/api/tags' -UseBasicParsing -TimeoutSec 5;$d=$r.Content|ConvertFrom-Json;foreach($m in $d.models){$s=[math]::Round($m.size/1GB,1);Write-Host \"        $($m.name) (${s} Go)\"}}catch{}" 2>nul
echo  [OK] Modeles IA prets

:MISTRAL_DONE
echo.

:: ============================================
:: ETAPE 4 : Verifier / telecharger modele Vosk
:: ============================================
echo  [4/5] Verification du modele Vosk...
set "VOSK_MODEL_DIR=%WHISPER_DIR%\vosk-model-fr-0.22"

if exist "%VOSK_MODEL_DIR%\am\final.mdl" goto VOSK_OK
if exist "%VOSK_MODEL_DIR%\conf\model.conf" goto VOSK_OK

:: Chercher le modele dans d'autres emplacements
set "VOSK_FOUND="
set "BATDIR=%~dp0"
if exist "%BATDIR%vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%BATDIR%vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%BATDIR%ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%BATDIR%ZerdaTime-IA\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%BATDIR%ZerdaTime-IA-Install\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%BATDIR%ZerdaTime-IA-Install\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%BATDIR%ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%BATDIR%ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%USERPROFILE%\Downloads\ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%USERPROFILE%\Downloads\ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%USERPROFILE%\Downloads\ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%USERPROFILE%\Downloads\ZerdaTime-IA\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%USERPROFILE%\Desktop\ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%USERPROFILE%\Desktop\ZerdaTime-IA\vosk-model-fr-0.22"
if "%VOSK_FOUND%"=="" if exist "%WHISPER_DIR%\ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22\conf\model.conf" set "VOSK_FOUND=%WHISPER_DIR%\ZerdaTime-IA-Install\ZerdaTime-IA\vosk-model-fr-0.22"

if not "%VOSK_FOUND%"=="" (
    echo        Modele Vosk trouve dans %VOSK_FOUND%
    echo        Copie vers %VOSK_MODEL_DIR% ...
    if not exist "%VOSK_MODEL_DIR%" mkdir "%VOSK_MODEL_DIR%"
    xcopy /E /Y /Q "%VOSK_FOUND%\*" "%VOSK_MODEL_DIR%\" >nul 2>nul
    echo        Copie terminee.
    goto VOSK_OK
)

:: Pas trouve localement - telecharger via Python
echo        Modele Vosk non trouve. Telechargement automatique...
echo        (~1.4 Go, une seule fois, patientez...)
"%PYTHON_CMD%" -c "import urllib.request,zipfile,os;d='%WHISPER_DIR%'.replace(chr(92),'/');u='https://alphacephei.com/vosk/models/vosk-model-fr-0.22.zip';z=d+'/vosk-model-fr-0.22.zip';print('[Vosk] Telechargement...');urllib.request.urlretrieve(u,z);print('[Vosk] Extraction...');zipfile.ZipFile(z,'r').extractall(d);os.unlink(z);print('[Vosk] Modele installe !')"
if errorlevel 1 (
    echo  [!!] Echec du telechargement du modele Vosk.
    echo  Verifiez votre connexion internet et reessayez.
    goto FIN
)

:VOSK_OK
echo  [OK] Modele Vosk pret
echo.

:: ============================================
:: ETAPE 5 : Demarrer Vosk
:: ============================================
echo  [5/5] Demarrage du serveur Vosk...
echo.
echo  ============================================
echo   Tout est pret ! Les serveurs tournent :
echo     Vosk    : http://localhost:5876
echo     Ollama  : http://localhost:59876
echo   Ne fermez pas cette fenetre !
echo  ============================================
echo.

set WHISPER_PORT=5876
set "VOSK_MODEL=%VOSK_MODEL_DIR%"
"%PYTHON_CMD%" "%SERVER_PY%"

echo.
echo  [!!] Le serveur Vosk s est arrete.

:FIN
echo.
echo  Appuyez sur une touche pour fermer...
pause >nul
