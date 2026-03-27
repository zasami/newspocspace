@echo off
title ZerdaTime IA - Desinstallation
echo.
echo  ============================================
echo   ZerdaTime IA - Desinstallation complete
echo   Cela va supprimer TOUS les composants IA
echo  ============================================
echo.
echo  Composants qui seront supprimes :
echo    - Vosk (transcription vocale)
echo    - Ollama + tous les modeles IA
echo    - Python portable
echo    - Raccourcis Bureau
echo.

set /p CONFIRM="  Voulez-vous continuer ? (O/N) : "
if /i not "%CONFIRM%"=="O" (
    echo.
    echo  Desinstallation annulee.
    goto FIN
)

echo.
echo  [1/6] Arret des processus...
taskkill /F /IM "ollama.exe" >nul 2>nul
taskkill /F /IM "ollama app.exe" >nul 2>nul
taskkill /F /IM "python.exe" >nul 2>nul
timeout /t 3 /nobreak >nul
echo  [OK] Processus arretes

echo.
echo  [2/6] Suppression de ZerdaTimeWhisper...
set "WHISPER_DIR=%LOCALAPPDATA%\ZerdaTimeWhisper"
if exist "%WHISPER_DIR%" (
    rmdir /S /Q "%WHISPER_DIR%"
    echo  [OK] %WHISPER_DIR% supprime
) else (
    echo  [--] Deja absent
)

echo.
echo  [3/6] Desinstallation d Ollama...
set "OLLAMA_UNINSTALL=%LOCALAPPDATA%\Programs\Ollama\unins000.exe"
if exist "%OLLAMA_UNINSTALL%" (
    echo        Desinstallation en cours...
    start /wait "" "%OLLAMA_UNINSTALL%" /VERYSILENT /NORESTART
    timeout /t 3 /nobreak >nul
    echo  [OK] Ollama desinstalle
) else (
    echo  [--] Ollama non trouve
)

echo.
echo  [4/6] Suppression des donnees Ollama...
if exist "%USERPROFILE%\.ollama" (
    rmdir /S /Q "%USERPROFILE%\.ollama"
    echo  [OK] %USERPROFILE%\.ollama supprime
) else (
    echo  [--] Deja absent
)
if exist "%LOCALAPPDATA%\Programs\Ollama" (
    rmdir /S /Q "%LOCALAPPDATA%\Programs\Ollama"
    echo  [OK] Dossier Ollama supprime
)
if exist "%LOCALAPPDATA%\Ollama" (
    rmdir /S /Q "%LOCALAPPDATA%\Ollama"
    echo  [OK] Dossier Ollama local supprime
)

echo.
echo  [5/6] Suppression des raccourcis Bureau...
if exist "%USERPROFILE%\Desktop\ZerdaTime IA.lnk" (
    del /F "%USERPROFILE%\Desktop\ZerdaTime IA.lnk"
    echo  [OK] Raccourci ZerdaTime IA supprime
)
if exist "%USERPROFILE%\Desktop\ZerdaTime Whisper.lnk" (
    del /F "%USERPROFILE%\Desktop\ZerdaTime Whisper.lnk"
    echo  [OK] Raccourci ZerdaTime Whisper supprime
)

echo.
echo  [6/6] Verification...
set "CLEAN=1"
if exist "%WHISPER_DIR%" (
    echo  [!!] %WHISPER_DIR% existe encore
    set "CLEAN=0"
)
if exist "%USERPROFILE%\.ollama" (
    echo  [!!] .ollama existe encore
    set "CLEAN=0"
)
if exist "%LOCALAPPDATA%\Programs\Ollama" (
    echo  [!!] Ollama existe encore
    set "CLEAN=0"
)
if "%CLEAN%"=="1" (
    echo  [OK] Tout est propre !
)

echo.
echo  ============================================
echo   Desinstallation terminee.
echo   Pour reinstaller, lancez install.bat
echo  ============================================

:FIN
echo.
echo  Appuyez sur une touche pour fermer...
pause >nul
