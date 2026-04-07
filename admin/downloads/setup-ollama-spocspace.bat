@echo off
chcp 65001 >nul 2>&1
title SpocSpace IA — Configuration Ollama
color 0B

echo.
echo  ╔══════════════════════════════════════════════╗
echo  ║   SpocSpace IA — Configuration automatique   ║
echo  ║          Ollama + Gemma 3 (4B)               ║
echo  ╚══════════════════════════════════════════════╝
echo.

:: ── Vérifier les droits admin ──
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo  [!] Ce script necessite les droits administrateur.
    echo  [!] Clic droit ^> Executer en tant qu'administrateur
    echo.
    pause
    exit /b 1
)

:: ── 1. Vérifier que Ollama est installé ──
echo  [1/5] Verification d'Ollama...
where ollama >nul 2>&1
if %errorLevel% neq 0 (
    echo  [!] Ollama n'est pas installe ou pas dans le PATH.
    echo  [!] Telechargez-le sur : https://ollama.com/download
    echo.
    pause
    exit /b 1
)
echo        OK — Ollama trouve.
echo.

:: ── 2. Arrêter Ollama si en cours ──
echo  [2/5] Arret d'Ollama en cours...
taskkill /f /im ollama.exe >nul 2>&1
taskkill /f /im "ollama app.exe" >nul 2>&1
timeout /t 2 /nobreak >nul
echo        OK — Ollama arrete.
echo.

:: ── 3. Configurer les variables d'environnement ──
echo  [3/5] Configuration du port 59876 + CORS...
setx OLLAMA_HOST "0.0.0.0:59876" >nul 2>&1
setx OLLAMA_ORIGINS "*" >nul 2>&1

:: Aussi pour la session courante
set OLLAMA_HOST=0.0.0.0:59876
set OLLAMA_ORIGINS=*

echo        OK — OLLAMA_HOST = 0.0.0.0:59876
echo        OK — OLLAMA_ORIGINS = * (CORS autorise)
echo.

:: ── 4. Démarrer Ollama et télécharger gemma3:4b ──
echo  [4/5] Demarrage d'Ollama + telechargement de gemma3:4b...
echo        (cela peut prendre quelques minutes la premiere fois)
echo.

start /b ollama serve >nul 2>&1
timeout /t 3 /nobreak >nul

ollama pull gemma3:4b
if %errorLevel% neq 0 (
    echo.
    echo  [!] Erreur lors du telechargement de gemma3:4b
    echo  [!] Verifiez votre connexion internet et reessayez.
    pause
    exit /b 1
)
echo.
echo        OK — gemma3:4b pret.
echo.

:: ── 5. Test de connexion ──
echo  [5/5] Test de connexion...
timeout /t 2 /nobreak >nul
curl -s http://localhost:59876/api/tags >nul 2>&1
if %errorLevel% neq 0 (
    echo  [!] Ollama ne repond pas sur le port 59876.
    echo  [!] Essayez de redemarrer votre PC puis relancez ce script.
    pause
    exit /b 1
)
echo        OK — Ollama repond sur http://localhost:59876
echo.

:: ── Terminé ──
echo.
echo  ╔══════════════════════════════════════════════╗
echo  ║          Configuration terminee !             ║
echo  ╠══════════════════════════════════════════════╣
echo  ║                                              ║
echo  ║  Ollama tourne sur : localhost:59876          ║
echo  ║  Modele :            gemma3:4b                ║
echo  ║  CORS :              active                   ║
echo  ║                                              ║
echo  ║  Ouvrez SpocSpace Admin ^> PV pour tester     ║
echo  ║  Le badge Ollama doit etre VERT               ║
echo  ║                                              ║
echo  ╚══════════════════════════════════════════════╝
echo.
echo  Appuyez sur une touche pour fermer...
pause >nul
