@echo off
echo.
echo  ============================================
echo   ZerdaTime - Installation IA locale
echo   Ne fermez pas cette fenetre !
echo  ============================================
echo.

set "BATDIR=%~dp0"
echo  [*] Dossier : %BATDIR%
echo.

:: Cas 1 : PS1 dans le meme dossier que le bat
if exist "%BATDIR%install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans le dossier courant
    powershell -ExecutionPolicy Bypass -File "%BATDIR%install-whisper.ps1"
    goto FIN
)

:: Cas 2 : sous-dossier ZerdaTime-IA-Install
if exist "%BATDIR%ZerdaTime-IA-Install\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans ZerdaTime-IA-Install\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%ZerdaTime-IA-Install\install-whisper.ps1"
    goto FIN
)

:: Cas 3 : sous-dossier ZerdaTime-IA-Install\ZerdaTime-IA
if exist "%BATDIR%ZerdaTime-IA-Install\ZerdaTime-IA\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans ZerdaTime-IA-Install\ZerdaTime-IA\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%ZerdaTime-IA-Install\ZerdaTime-IA\install-whisper.ps1"
    goto FIN
)

:: Cas 4 : sous-dossier ZerdaTime-IA
if exist "%BATDIR%ZerdaTime-IA\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans ZerdaTime-IA\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%ZerdaTime-IA\install-whisper.ps1"
    goto FIN
)

echo  [ERREUR] install-whisper.ps1 introuvable !
echo.
echo  Assurez-vous d'avoir extrait le ZIP avant de lancer install.bat.
echo  Le script cherche install-whisper.ps1 dans :
echo    - %BATDIR%
echo    - %BATDIR%ZerdaTime-IA-Install\
echo    - %BATDIR%ZerdaTime-IA\

:FIN
echo.
echo  ============================================
echo   Appuyez sur une touche pour fermer...
echo  ============================================
pause >nul
