@echo off
echo.
echo  ============================================
echo   SpocSpace - Installation IA locale
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

:: Cas 2 : sous-dossier SpocSpace-IA-Install
if exist "%BATDIR%SpocSpace-IA-Install\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans SpocSpace-IA-Install\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%SpocSpace-IA-Install\install-whisper.ps1"
    goto FIN
)

:: Cas 3 : sous-dossier SpocSpace-IA-Install\SpocSpace-IA
if exist "%BATDIR%SpocSpace-IA-Install\SpocSpace-IA\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans SpocSpace-IA-Install\SpocSpace-IA\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%SpocSpace-IA-Install\SpocSpace-IA\install-whisper.ps1"
    goto FIN
)

:: Cas 4 : sous-dossier SpocSpace-IA
if exist "%BATDIR%SpocSpace-IA\install-whisper.ps1" (
    echo  [OK] install-whisper.ps1 trouve dans SpocSpace-IA\
    powershell -ExecutionPolicy Bypass -File "%BATDIR%SpocSpace-IA\install-whisper.ps1"
    goto FIN
)

echo  [ERREUR] install-whisper.ps1 introuvable !
echo.
echo  Assurez-vous d'avoir extrait le ZIP avant de lancer install.bat.
echo  Le script cherche install-whisper.ps1 dans :
echo    - %BATDIR%
echo    - %BATDIR%SpocSpace-IA-Install\
echo    - %BATDIR%SpocSpace-IA\

:FIN
echo.
echo  ============================================
echo   Appuyez sur une touche pour fermer...
echo  ============================================
pause >nul
