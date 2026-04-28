# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Git — Règle obligatoire

**IMPORTANT** : Avant de modifier un fichier, toujours committer l'état actuel avec `git add -A && git commit -m "backup avant modif"` pour pouvoir revenir en arrière si besoin.

## Project Overview

SpocSpace is an EMS (care home) staff scheduling application for EMS in Geneva. PHP+vanilla JS SPA for employees, server-rendered Bootstrap admin panel for managers. No build tools, no framework — plain PHP backend, ES modules frontend.

## Architecture

### Two separate apps sharing one backend

1. **Employee SPA** (`/spocspace/`) — `index.php` shell + SPA router in `assets/js/app.js`
   - Pages loaded via `fetch()` from `pages/*.php`, JS modules from `assets/js/modules/*.js`
   - Each module exports `init()` and `destroy()`, dynamically imported via `moduleMap` in `app.js`
   - API calls go to `api.php` → `api_modules/_routes.php` → action function in module file

2. **Admin panel** (`/spocspace/admin/`) — server-rendered, `index.php?page=dashboard`
   - Uses Bootstrap 5, pages in `admin/pages/*.php`
   - API calls go to `admin/api.php` → `admin/api_modules/_routes.php` → action function
   - Admin actions prefixed `admin_*` (e.g. `admin_get_users`, `admin_save_assignation`)

### Request flow

```
POST /spocspace/api.php  { action: "get_planning_hebdo", ... }
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
- **Session prefix**: `$_SESSION['ss_user']`, `$_SESSION['ss_csrf_token']`, `$_SESSION['ss_last_activity']`

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

Sidebar rétractable style zasamix : bouton hamburger desktop + mobile. État mini/full persisté dans `localStorage` (`ss_sidebar_mini`). Catégories collapsibles avec persistence (`ss_sidebar_cats`). Sur mobile : sidebar off-canvas + overlay.

### Config EMS

Table `ems_config` (clé-valeur). API dans `admin/api_modules/config.php` :
- `admin_get_config` — toutes les valeurs + modules avec responsables
- `admin_save_config` — batch update (clés whitelistées)
- `admin_assign_module_responsable` — assigner un responsable à un module
- `admin_generate_structure` — auto-crée N modules + N étages, répartition round-robin
- `admin_update_module_config` — modifier un module (code, nom, étages, responsable)

Page admin `etablissement` : identité EMS, direction, infirmière chef, RH, structure modules (générateur + cards individuelles), règles planning.

## Architecture de rendu — Migration SSR (2026-04-15+)

Les nouvelles pages et migrations de la SPA employé utilisent le **pattern SSR** : PHP génère le HTML avec les données, JS uniquement pour les interactions. Plan détaillé : [docs/Migration_SSR_Plan.md](docs/Migration_SSR_Plan.md).

### Helpers PHP partagés (`pages/_partials/helpers.php`)
- `render_stat_card($label, $value, $icon, $variant, $sub=null)` — carte stat palette
- `render_page_header($title, $icon, $backLink, $backLabel, $actions)` — header + breadcrumb
- `render_statut_badge($statut)` / `render_type_badge($type)` — badges palette
- `render_empty_state($message, $icon, $hint=null)` — état vide
- `render_progress_bar($percent, $label)` — progression
- `fmt_date_fr($date)` / `fmt_relative($date)` — formatage dates

### Template standard page SPA migrée
```php
<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';
// Chargement DB + calculs ici
?>
<div class="page-wrap">
  <?= render_page_header('Titre', 'bi-icon', 'parent', 'Parent') ?>
  <div class="row g-3 mb-3">
    <?= render_stat_card('Label', $n, 'bi-check', 'teal') ?>
  </div>
  <?php foreach ($items as $i): ?>
    <div><?= h($i['nom']) ?> <?= render_statut_badge($i['statut']) ?></div>
  <?php endforeach ?>
  <?php if (!$items) echo render_empty_state('Aucun élément') ?>
</div>
```

### Règles strictes
- **`h()` systématique** sur variables user (alias htmlspecialchars)
- **`nonce="<?= CSP_NONCE ?>"`** sur chaque `<script>`
- **Check session + role** en tête de chaque page PHP
- **Prepared statements** uniquement (`Db::fetch`, `Db::fetchAll`, `Db::exec`)
- **JS minimal** : handlers d'interaction ; après mutation → `location.reload()` (le SW re-cache la page rendue)
- **API JSON** conservée pour : mutations POST, polling live, long-polling notifications

### Ordre de migration
1. **Phase 1 simples** : mon-stage (prototype), profile, annuaire, fiches-salaire, annonces, documents
2. **Phase 2 moyennes** : mes-stagiaires, stagiaire-detail, collegues, covoiturage, desirs, vacances, absences, changements, votes, sondages, pv
3. **Phase 3 complexes** : planning, emails, mur, wiki, report-edit (garde TipTap), repartition, cuisine-*, home

### Sauvegardes & Restauration

Système de backup/restore à deux niveaux : per-user (admin) et global.

**Table DB** : `backups` (id, user_id NULL=global, type ENUM user/global, filename, file_size, tables_included JSON, row_counts JSON, checksum_sha256, created_at, created_by)

**Stockage** : `data/backups/users/{user_id}/` et `data/backups/global/` — protégé par `.htaccess` Deny from all

**Format** : ZIP via `ZipArchive` natif PHP. Chaque ZIP contient :
- `manifest.json` (métadonnées, date, version, checksums)
- `*.sql` (INSERT statements par table)
- `files/` (documents uploadés)
- `checksum.sha256` (intégrité)

**Per-user** (admin) :
- Déclenchement manuel (bouton)
- Max 5 par user (rotation auto)
- Scope : documents, messages, emails de l'utilisateur

**Global** :
- Automatique : cron quotidien 3h (`scripts/backup_daily.php`)
- Manuel : bouton admin
- Rétention : 14 jours quotidiens + 8 hebdomadaires
- Restauration protégée par code spécial (hashé dans `ems_config`, rate-limited 3 tentatives/h)

**API admin** (`admin/api_modules/backups.php`) :
- `admin_create_backup` — créer ZIP per-user
- `admin_list_backups` — lister par user ou global
- `admin_compare_backup` — diff backup vs état actuel
- `admin_restore_backup` — restauration (merge ou écrasement)
- `admin_delete_backup` — suppression manuelle
- `admin_create_global_backup` — dump complet
- `admin_restore_global_backup` — restauration totale (code spécial requis)

**Page admin** : `sauvegardes` — 3 onglets (Mes sauvegardes / Global 🔒 / Configuration)

**Compatibilité de version** :
- Table `schema_migrations` : historique de toutes les migrations appliquées
- `ems_config.schema_version` : numéro de migration courant (ex: 072)
- Chaque backup contient dans `manifest.json` : `schema_version` + `schema_snapshot` (colonnes de chaque table)
- A la restauration : comparaison automatique du schéma backup vs actuel
  - Version backup > actuelle → **refusé** (mettre à jour SpocSpace d'abord)
  - Version backup < actuelle → **adaptation auto** (colonnes supprimées retirées, nouvelles colonnes = valeurs par défaut)
  - Version identique → restauration directe
- Le modal Comparer affiche le rapport de compatibilité (vert/orange/rouge) avec détail des différences de colonnes

**Restauration UX** :
- Comparer : affiche diff (+ajoutés, -supprimés, ~modifiés) → restaurer seulement les différences
- Écraser : avertissement DANGER rouge, confirmation par saisie "RESTAURER"
- Global : double confirmation (code spécial + saisie "RESTAURER")

## Cron quotidien

Script wrapper : [scripts/cron_daily.php](scripts/cron_daily.php) — exécute en séquence :
1. Génération propositions inscription FEGEMS (si `insc.auto_proposer` = 1)
2. Auto-création entretiens à échéance (si `entr.auto_creer_a_echeance` = 1)
3. Recalcul priorités compétences (refresh STORED columns)

Lock file : `data/cron_daily.lock` (évite exécutions concurrentes).

**Ligne crontab Infomaniak** (panel d'admin → Cron) :
```
0 3 * * *  /usr/bin/php /home/clients/c81789f8de36e992da19fb6856aa48f6/sites/zkriva.com/spocspace/scripts/cron_daily.php >> /home/clients/c81789f8de36e992da19fb6856aa48f6/logs/cron_spocspace.log 2>&1
```

Pour tester manuellement : `php scripts/cron_daily.php`

## Travail en cours

_(section vidée quand toutes les tâches sont terminées)_
