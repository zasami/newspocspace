@echo off
title SpocSpace - Fix Ollama CORS

echo.
echo  ========================================
echo   SpocSpace - Configuration Ollama CORS
echo  ========================================
echo.

echo  [1/4] Arret de tous les processus Ollama...
taskkill /f /im "ollama.exe" >nul 2>&1
taskkill /f /im "ollama app.exe" >nul 2>&1
taskkill /f /im "Ollama.exe" >nul 2>&1
timeout /t 2 /nobreak >nul
echo        OK
echo.

echo  [2/4] Configuration OLLAMA_ORIGINS = * ...
setx OLLAMA_ORIGINS "*" >nul 2>&1
set OLLAMA_ORIGINS=*
echo        OK
echo.

echo  [3/4] Demarrage Ollama...
set OLLAMA_ORIGINS=*
start "" "ollama" serve
timeout /t 5 /nobreak >nul
echo        OK
echo.

echo  [4/4] Test de connexion...
curl -s http://localhost:11434/api/tags >nul 2>&1
if %errorLevel% neq 0 (
    echo        Attente supplementaire...
    timeout /t 5 /nobreak >nul
    curl -s http://localhost:11434/api/tags >nul 2>&1
)
if %errorLevel% neq 0 (
    echo        ERREUR - Ollama ne repond pas
    pause
    exit /b 1
)
echo        OK - Ollama en ligne
echo.

echo  ========================================
echo   TERMINE !
echo   OLLAMA_ORIGINS = *
echo   Port : 11434
echo   Rafraichissez la page PV avec F5
echo   Le badge Ollama doit etre VERT
echo  ========================================
echo.
pause
