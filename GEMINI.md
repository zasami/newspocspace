# Projet Terrassière - Guide pour Gemini

Ce fichier contient les instructions et le contexte spécifiques pour l'utilisation de Gemini CLI sur le projet Terrassière.

## 🌍 Préférences Générales
- **Langue** : Français (Toutes les communications et explications doivent être en français).
- **Date actuelle** : Mercredi 18 mars 2026.

## 🏗️ Aperçu du Projet
Terrassière est une application de gestion de planning pour l'EMS Terrassière à Genève.
- **Backend** : PHP pur (sans framework).
- **Frontend Employé** : SPA (Single Page Application) en Vanilla JS / ES Modules.
- **Panel Admin** : Rendu côté serveur (SSR) avec Bootstrap 5.
- **Base de données** : MySQL/MariaDB avec IDs UUID (CHAR 36).

## 📂 Architecture des Fichiers

### 👤 Application Employé (`/terrassiere/`)
- `index.php` : Shell de la SPA.
- `assets/js/app.js` : Routeur et logique principale.
- `pages/*.php` : Fragments HTML chargés via `fetch()`.
- `assets/js/modules/*.js` : Modules JS correspondants (avec fonctions `init()` et `destroy()`).
- `api_modules/` : Logique API côté client.

### ⚙️ Panel Admin (`/terrassiere/admin/`)
- `admin/index.php` : Point d'entrée principal (SSR).
- `admin/pages/*.php` : Pages du panel admin.
- `admin/api_modules/` : Logique API spécifique à l'administration.
- `admin/assets/js/` : Helpers et scripts spécifiques à l'admin.

### 🛠️ Core & Config
- `config/config.php` : Chargement du `.env` et constantes.
- `core/` : Classes utilitaires (`Db`, `Auth`, `Sanitize`, `Uuid`).
- `init.php` : Initialisation globale et guards d'authentification.

## 📜 Conventions de Code

### Backend (PHP)
- **BDD** : Utiliser les méthodes statiques de `Db` (`fetch`, `fetchAll`, `exec`, `getOne`).
- **IDs** : Toujours générer des UUID avec `Uuid::v4()`.
- **Sécurité** : 
    - Utiliser `require_auth()`, `require_responsable()`, ou `require_admin()`.
    - Sanitiser les entrées avec la classe `Sanitize`.
    - Échapper le HTML avec la fonction alias `h()`.
- **API** : Les paramètres sont fusionnés dans la globale `$params`. Utiliser `respond($data)` pour les réponses JSON.

### Frontend (JavaScript)
- **Employé** : Utiliser `apiPost('action', {data})` depuis `helpers.js`.
- **Admin** : Utiliser `adminApiPost('action', {data})` depuis `admin/assets/js/helpers.js`.
- **SPA** : Les liens utilisent `data-link="pageId"`.

## 🤖 Règles IA (Human Rules)
Une table `ia_human_rules` permet de définir des contraintes de planning en langage naturel pour l'IA.
- **Table** : `id`, `titre`, `description` (instructions IA), `importance` (important/moyen/supprime), `actif`.
- **Gestion** : Accessible via le Panel Admin > Établissement > Règles IA.

## 🚀 Workflows Communs

### Ajouter une action API
1. Créer la fonction dans le fichier `api_modules/{module}.php` adéquat.
2. Enregistrer l'action dans `api_modules/_routes.php`.

### Ajouter une page SPA
1. Créer `pages/{nom}.php` (HTML).
2. Créer `assets/js/modules/{nom}.js` (JS avec `init` / `destroy`).
3. Ajouter l'entrée dans `moduleMap` de `assets/js/app.js`.

---
*Dernière mise à jour : 18 mars 2026*
