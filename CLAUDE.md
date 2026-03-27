# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

zerdaTime is an EMS (care home) staff scheduling application for EMS in Geneva. PHP+vanilla JS SPA for employees, server-rendered Bootstrap admin panel for managers. No build tools, no framework — plain PHP backend, ES modules frontend.

## Architecture

### Two separate apps sharing one backend

1. **Employee SPA** (`/zerdatime/`) — `index.php` shell + SPA router in `assets/js/app.js`
   - Pages loaded via `fetch()` from `pages/*.php`, JS modules from `assets/js/modules/*.js`
   - Each module exports `init()` and `destroy()`, dynamically imported via `moduleMap` in `app.js`
   - API calls go to `api.php` → `api_modules/_routes.php` → action function in module file

2. **Admin panel** (`/zerdatime/admin/`) — server-rendered, `index.php?page=dashboard`
   - Uses Bootstrap 5, pages in `admin/pages/*.php`
   - API calls go to `admin/api.php` → `admin/api_modules/_routes.php` → action function
   - Admin actions prefixed `admin_*` (e.g. `admin_get_users`, `admin_save_assignation`)

### Request flow

```
POST /zerdatime/api.php  { action: "get_planning_hebdo", ... }
  → api.php reads _routes.php to find module file
  → requires api_modules/{module}.php
  → calls the function named $action()
```

### Key conventions

- **DB**: `Db::fetch()`, `Db::fetchAll()`, `Db::exec()`, `Db::getOne()` — static methods, prepared statements built-in
- **IDs**: `Uuid::v4()` — all PKs are CHAR(36) UUIDs
- **Auth guards**: `require_auth()`, `require_responsable()`, `require_admin()` — defined in `init.php`
- **Responses**: `respond($data)`, `bad_request()`, `unauthorized()`, `forbidden()`, `not_found()`
- **Input**: `global $params;` in API action functions (merged from GET/POST/JSON body)
- **Sanitization**: `Sanitize::email()`, `Sanitize::text()`, `Sanitize::date()`, `Sanitize::int()`, etc.
- **HTML escaping**: `h($val)` (alias for `htmlspecialchars`)
- **CSRF**: Auto-sent via `X-CSRF-Token` header; `get_*` actions and auth flow are exempt
- **Session prefix**: `$_SESSION['zt_user']`, `$_SESSION['zt_csrf_token']`, `$_SESSION['zt_last_activity']`

### JS helpers

- **Employee**: `import { apiPost } from '../helpers.js'` — `apiPost('action_name', {data})`
- **Admin**: `adminApiPost('action_name', {data})` — global function in `admin/assets/js/helpers.js`
- Utilities: `escapeHtml()`, `toast()`, `formatDate()`, `statusBadge()`, `absenceTypeBadge()`, `debounce()`

### SPA navigation

Links use `data-link="pageId"` attribute. `app.js` intercepts clicks, calls `loadPage(pageId)` which fetches HTML from `pages/{pageId}.php` and dynamically imports `modules/{pageId}.js`.

### Roles

`collaborateur` < `responsable` < `admin`/`direction`. The admin panel requires `responsable`+.

### Config

- `config/config.php` — loads `.env` file, defines constants (`DB_*`, `APP_*`, pagination, rate limits)
- `APP_VERSION` used for cache-busting (`?v=` on CSS/JS)
- Domain-specific: `MAX_DESIRS_PAR_MOIS = 4`, desir submission window days 1-10

### Database schema

Tables: `users`, `fonctions`, `modules`, `etages`, `groupes`, `horaires_types`, `plannings`, `planning_assignations`, `desirs`, `absences`, `besoins_couverture`, `messages`, `rate_limits`. Migrations in `migrations/`.

### Adding a new API action

1. Add the function in the appropriate `api_modules/{module}.php` file
2. Register the action name in `api_modules/_routes.php` under the module key
3. For admin actions: same in `admin/api_modules/` with `admin_` prefix

### Adding a new SPA page

1. Create `pages/{name}.php` (HTML template)
2. Create `assets/js/modules/{name}.js` with `export function init()` and `export function destroy()`
3. Add entry to `moduleMap` in `assets/js/app.js`
4. Add nav link with `data-link="{name}"`

### Test data

- **Seed script**: `php migrations/003_seed_employees.php` — crée 100 employés fictifs + plannings + désirs + absences + messages + besoins couverture
- **Login admin**: `admin@terrassiere.ch` / `Admin2026!`
- **Login employé**: `{prenom}.{nom}@terrassiere.ch` / `Terr2026!`
- Le formulaire login a les identifiants admin pré-remplis (temporaire pour tests)

### Planning admin API

Actions dans `admin/api_modules/planning.php` :
- `admin_get_planning` — planning + assignations d'un mois
- `admin_create_planning` — créer planning vide (brouillon)
- `admin_generate_planning` — génération automatique basée sur besoins, taux, désirs, absences
- `admin_get_planning_stats` — stats heures/user, gaps couverture, totaux
- `admin_get_planning_refs` — données de référence (users, horaires, modules, fonctions)
- `admin_save_assignation` — upsert une cellule (horaire, module, statut)
- `admin_delete_assignation` — supprimer une assignation
- `admin_clear_planning` — vider un planning (ou un module)
- `admin_finalize_planning` — passer en provisoire/final

### Admin sidebar

Sidebar rétractable style zasamix : bouton hamburger desktop + mobile. État mini/full persisté dans `localStorage` (`zt_sidebar_mini`). Catégories collapsibles avec persistence (`zt_sidebar_cats`). Sur mobile : sidebar off-canvas + overlay.

### Config EMS

Table `ems_config` (clé-valeur). API dans `admin/api_modules/config.php` :
- `admin_get_config` — toutes les valeurs + modules avec responsables
- `admin_save_config` — batch update (clés whitelistées)
- `admin_assign_module_responsable` — assigner un responsable à un module
- `admin_generate_structure` — auto-crée N modules + N étages, répartition round-robin
- `admin_update_module_config` — modifier un module (code, nom, étages, responsable)

Page admin `etablissement` : identité EMS, direction, infirmière chef, RH, structure modules (générateur + cards individuelles), règles planning.

## Travail en cours

_(section vidée quand toutes les tâches sont terminées)_
