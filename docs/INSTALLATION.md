# SpocSpace — Guide d'installation client

Version : 2026-04-17

Ce document décrit la procédure complète pour installer SpocSpace chez un
nouveau client EMS, depuis une clé USB ou un dépôt git, jusqu'à la première
connexion admin.

---

## 1. Prérequis serveur

À vérifier avant de commencer :

| Élément | Version minimale | Notes |
|---|---|---|
| PHP | 8.1 | Requis : `pdo_mysql`, `mbstring`, `gd`, `zip`, `openssl`, `curl`, `fileinfo`, `json` |
| MySQL / MariaDB | 8.0 / 10.5 | Base UTF8MB4, collation `utf8mb4_unicode_ci` |
| Apache | 2.4+ | Avec `mod_rewrite`, `mod_headers`, `mod_authz_core` |
| HTTPS | obligatoire | Certificat valide (Let's Encrypt OK) |
| Accès SSH | oui | Indispensable pour l'étape 3 (activation installeur) |

Côté PHP, vérifie dans `php.ini` :
```
upload_max_filesize = 50M
post_max_size       = 55M
memory_limit        = 256M
max_execution_time  = 120
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
display_errors      = Off
log_errors          = On
```

## 2. Copie des fichiers sur le serveur client

### Option A — Clé USB / transfert manuel
1. Copier **l'intégralité** du dossier `spocspace/` sur la clé USB
   (mais **sans** `.env`, `storage/.installed`, `storage/.install-enabled`,
   `data/backups/`, `storage/documents/`, `storage/fiches_salaire/`,
   `storage/avatars/`, `storage/mur/`, `storage/emails/`, `storage/logos/`,
   `storage/pv/`, `uploads/famille/`, `uploads/residents/` — dossiers
   propres à chaque client).
2. Brancher la clé sur la machine du client (ou se connecter en SFTP).
3. Déposer le dossier `spocspace/` dans le document root du serveur web
   (ex: `/var/www/html/newspocspace/` ou `/home/clients/CLIENT/sites/DOMAINE/`).

### Option B — Depuis git (recommandé)
```bash
ssh user@serveur-client
cd /chemin/vers/document-root
git clone https://github.com/<org>/spocspace.git
cd spocspace
composer install --no-dev --optimize-autoloader
```

### Permissions (important)
```bash
# Les dossiers que le serveur doit pouvoir écrire
chown -R www-data:www-data storage data uploads assets/uploads
find storage data uploads assets/uploads -type d -exec chmod 755 {} \;
find storage data uploads assets/uploads -type f -exec chmod 644 {} \;
# .env.local est sensible : 600
touch .env.local && chmod 600 .env.local
```

## 3. Créer la base de données MySQL

Sur l'hébergement, via le panneau client ou en CLI :
```sql
CREATE DATABASE spocspace_XXX
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'spocspace_XXX'@'%' IDENTIFIED BY 'MDP_FORT_ALEATOIRE';
GRANT ALL PRIVILEGES ON spocspace_XXX.* TO 'spocspace_XXX'@'%';
FLUSH PRIVILEGES;
```

Note bien :
- Le nom de la base
- Le nom de l'utilisateur
- Le mot de passe
- L'hôte (souvent `localhost`, parfois un hôte distant selon l'hébergeur)

## 4. Activer l'installeur (obligatoire, via SSH)

**L'installeur est désactivé par défaut**. Pour y accéder, il faut générer
un token d'activation en SSH.

```bash
ssh user@serveur-client
cd /chemin/vers/spocspace

# Générer le token + activer
INSTALL_HOST=exemple-client.ch php scripts/enable-install.php
```

Le script affiche :
```
════════════════════════════════════════════════════════════════
  Installeur SpocSpace activé
════════════════════════════════════════════════════════════════

Token : 3b7c...8f2a (48 caractères hex)
Fichier : /chemin/newspocspace/storage/.install-enabled

Ouvre cette URL dans ton navigateur :
  https://exemple-client.ch/newspocspace/install.php?key=3b7c...8f2a
```

**Copier cette URL** — c'est la seule manière d'accéder à l'installeur.

Sans ce fichier `storage/.install-enabled`, `install.php` renvoie 403 à tout
le monde, y compris à toi.

## 5. Parcourir l'assistant d'installation (navigateur)

Ouvre l'URL fournie par le script. L'assistant se déroule en 6 étapes :

### Étape 1 — Vérification des prérequis
L'assistant teste :
- Version PHP
- Extensions (`pdo_mysql`, `gd`, `zip`, `openssl`, `mbstring`, `curl`)
- Permissions en écriture sur `storage/`, `data/`, `uploads/`, `.env.local`

Si un check est rouge, corrige-le côté serveur avant de continuer.

### Étape 2 — Configuration base de données
Saisir :
- Hôte DB (ex: `localhost`)
- Port (défaut 3306)
- Nom de la base (créée étape 3)
- Utilisateur + mot de passe

Cliquer « Tester la connexion ». Si OK, `.env.local` est écrit automatiquement.

### Étape 3 — Exécution des migrations
Clic sur « Installer les tables ». L'installeur exécute tous les fichiers
`migrations/*.sql` puis `migrations/*.php` (création tables + seed données
de référence : fonctions, horaires types, services documents, templates
email, rules d'assignation).

Durée : ~30 secondes à 2 minutes selon le serveur.

### Étape 4 — Informations EMS
Saisir les informations de l'établissement :
- Nom (ex: « EMS La Terrassière »)
- Adresse, NPA, ville, canton
- Téléphone, email de contact
- Type (EMS, résidence, home, etc.)
- Nombre de lits (optionnel)

Ces infos sont stockées dans `ems_config` (clé/valeur) et modifiables
ensuite depuis l'admin → Établissement.

### Étape 5 — Création du compte administrateur
Saisir :
- Prénom, nom
- Email (sera utilisé pour se connecter)
- Mot de passe (min 8 car., maj + chiffre + spécial recommandés)

⚠ Note-le tout de suite. En cas d'oubli il faudra passer par SSH pour le
réinitialiser (voir section 8).

### Étape 6 — Finalisation
L'installeur :
- Crée le compte admin dans `users`
- Crée le fichier verrou `storage/.installed`
- **Supprime automatiquement** `storage/.install-enabled` (l'installeur
  devient inaccessible jusqu'à une éventuelle réactivation manuelle)

Bouton final : « Aller à la page de connexion ».

## 6. Premier login + test

1. Ouvrir `https://exemple-client.ch/newspocspace/login`
2. Se connecter avec l'email + mot de passe admin créés à l'étape 5
3. Vérifier l'accès au panneau admin (`/admin`)
4. Aller dans **Établissement** et compléter la structure (modules, étages)
5. Aller dans **Utilisateurs** et créer les premiers employés

## 7. Post-installation — checklist de sécurité

À faire dans les 24h après mise en prod :

- [ ] **Rotation du mot de passe MySQL** si créé par l'hébergeur
- [ ] **Certificat HTTPS valide** (Let's Encrypt ou autre)
- [ ] **Vérifier que `.env` et `.env.local` ne sont pas accessibles via
      navigateur** — tester `https://client.ch/newspocspace/.env` → doit
      renvoyer 403
- [ ] **Tester la sauvegarde** automatique : vérifier que `data/backups/`
      reçoit bien un fichier ZIP quotidien (cron à 3h). Si le cron n'est
      pas actif, l'ajouter :
      ```
      0 3 * * * cd /chemin/spocspace && php scripts/backup_daily.php > /dev/null 2>&1
      ```
- [ ] **Activer un moniteur uptime** (ex: UptimeRobot) sur
      `https://client.ch/newspocspace/api.php?action=me` (doit répondre 401,
      c'est normal)
- [ ] **Configurer SMTP** pour les emails (admin → Paramètres → Email
      externe) : sans SMTP, les emails sortants passent par `mail()` PHP
      qui finit souvent en spam
- [ ] **Former l'admin client** sur :
  - Création / modification d'utilisateurs
  - Génération automatique du planning
  - Sauvegardes / restauration (code d'accès global à définir)
- [ ] **Communiquer au client** le mot de passe admin + lui demander de
      le changer à la première connexion

## 8. Opérations courantes après installation

### Réinitialiser le mot de passe admin (via SSH)
```bash
php -r 'require "init.php"; $h = password_hash("NouveauMDP!2026", PASSWORD_BCRYPT, ["cost"=>12]); Db::exec("UPDATE users SET password=?, password_changed_at=NOW() WHERE email=?", [$h, "admin@client.ch"]); echo "OK\n";'
```

### Réinstaller (rare, ex: migration serveur)
```bash
# Supprimer le verrou + réactiver
rm storage/.installed
php scripts/enable-install.php
# Refaire les étapes 4 à 6
```

### Mettre à jour SpocSpace (nouvelle version)
```bash
cd /chemin/spocspace
git pull origin main
composer install --no-dev --optimize-autoloader
# Les nouvelles migrations s'appliquent au prochain démarrage (migrations/*.sql)
# OU en CLI :
php migrate.php 2>/dev/null || true   # si encore présent
# OU appliquer manuellement les nouveaux fichiers SQL dans migrations/
```

### Sauvegarde manuelle
Admin panel → **Sauvegardes** → « Créer une sauvegarde globale ».
Fichier ZIP dans `data/backups/global/`.

### Restauration
Admin panel → **Sauvegardes** → choisir un backup → « Comparer » puis
« Restaurer ». La restauration globale exige un **code d'accès spécial**
défini à l'installation (à saisir manuellement la première fois).

## 9. Structure des dossiers

```
spocspace/
├── admin/              Panneau admin server-rendered (Bootstrap)
├── api.php             Point d'entrée API employé SPA
├── api_modules/        Logique API employé (auth, planning, desirs, ...)
├── assets/             CSS / JS / images statiques
├── care/               Panneau care (soignants, résidents, hygiène, ...)
├── config/             config.php (charge .env et .env.local)
├── core/               Classes partagées (Db, Auth, Sanitize, FileSecurity, ...)
├── data/
│   └── backups/        Sauvegardes (protégé par .htaccess)
├── docs/               Documentation interne (ce fichier)
├── index.php           Shell SPA employé
├── init.php            Bootstrap (session, guards, security headers)
├── install.php         Installeur (désactivé sauf si .install-enabled présent)
├── landing/            Page publique (optionnel)
├── migrations/         SQL + PHP de migration (appliqués à l'installation)
├── pages/              Templates PHP de la SPA employé
├── scripts/
│   ├── backup_daily.php        Cron sauvegarde quotidienne
│   └── enable-install.php      Génère le token pour relancer l'installeur
├── storage/            Fichiers uploadés + verrou .installed (protégé)
├── uploads/            Photos résidents / famille (protégé)
├── website/            Site public vitrine (optionnel)
├── whisper-local/      Service Whisper local (optionnel, PV audio)
├── .env                Variables d'env (ne JAMAIS commit, ne JAMAIS servir via HTTP)
├── .env.local          Override par machine (créé par install.php)
├── .htaccess           Sécurité Apache : bloque dotfiles, -Indexes, nosniff
└── sw.js               Service Worker PWA
```

## 10. Diagnostic en cas de problème

| Symptôme | Vérifier |
|---|---|
| `install.php` → 403 « Installation désactivée » | Lancer `php scripts/enable-install.php` via SSH |
| Token invalide | L'URL doit contenir le `?key=...` complet (48 hex) |
| Déjà installé, veut réinstaller | `rm storage/.installed` puis `php scripts/enable-install.php` |
| 500 après install | Voir `tail -50 /chemin/serveur/php_error.log` |
| « Unknown column » | Il manque une migration — rejouer les `.sql` de `migrations/` |
| `.env` téléchargeable via HTTP | `.htaccess` racine absent ou `mod_authz_core` désactivé |
| Emails ne partent pas | Configurer SMTP dans l'admin (Paramètres → Email externe) |
| Sauvegardes vides | Vérifier que le cron `backup_daily.php` tourne (`ls -la data/backups/global/`) |

## 11. Contact

Pour toute question :
- Email : zaghbani.sami@gmail.com
- Docs techniques : voir `docs/` + `CLAUDE.md` à la racine
