# Configuration des Règles IA - Résumé de l'implémentation

## ✅ Changements effectués

### 1. **Base de données** 
- **Fichier**: `migrations/010_ia_human_rules.sql`
- **Table créée**: `ia_human_rules`
- **Colonnes**:
  - `id` (CHAR(36) - UUID)
  - `titre` (VARCHAR(255) - titre de la règle)
  - `description` (TEXT - langage humain pour l'IA)
  - `importance` (VARCHAR(20) - important/moyen/supprime)
  - `actif` (TINYINT(1) - booléen pour actif/inactif)
  - `created_at`, `updated_at` (timestamps auto)
  - `created_by` (CHAR(36) - qui a créé la règle)

### 2. **API Endpoints**
- **Fichier**: `admin/api_modules/config.php`
- **5 nouvelles fonctions**:
  - `admin_get_ia_rules()` - récupère toutes les règles
  - `admin_create_ia_rule()` - crée une nouvelle règle
  - `admin_update_ia_rule()` - met à jour une règle existante
  - `admin_delete_ia_rule()` - supprime une règle
  - `admin_toggle_ia_rule()` - active/désactive une règle

- **Fichier**: `admin/api_modules/_routes.php`
- **Toutes les actions enregistrées** sous la clé `'config'`

### 3. **Interface Admin**
- **Fichier**: `admin/pages/config-ia.php`
- **Nouvel onglet**: "Règles IA" après "Clés API"
- **Fonctionnalités**:
  - Liste vide au départ
  - Bouton "Ajouter une règle"
  - Modal pour créer/modifier une règle
  - Table avec affichage des règles existantes
  - Boutons Modifier/Supprimer pour chaque règle
  - Switch Actif/Inactif pour toggle le statut

### 4. **JavaScript**
- **Fichier**: `admin/pages/config-ia.php` (dans le script)
- **Fonctionnalités**:
  - `loadIaRules()` - charge les règles depuis l'API
  - `openRuleForm()` - ouvre le modal vide pour créer
  - `editRule()` - charge une règle existante dans le modal
  - `saveRule()` - enregistre (create ou update)
  - `deleteRule()` - supprime une règle
  - `toggleRuleStatus()` - bascule le statut actif/inactif

- **Fichier**: `admin/assets/js/helpers.js`
- **Ajout**: fonction `toast()` pour les notifications

## 🎯 Utilisation

### Ajouter une nouvelle règle
1. Aller sur la page admin: **Établissement > Règles IA** (onglet "Règles IA")
2. Cliquer sur **"Ajouter une règle"**
3. Dans le modal:
   - **Titre**: courte description de la règle (ex: "Favoriser les horaires du soir")
   - **Niveau d'importance**: Important / Moyen / Supprimé
   - **Règle**: description en langage humain que l'IA doit appliquer
4. Cliquer **"Valider"**

### Modifier une règle
1. Cliquer sur le bouton **Modifier** (crayon) d'une règle
2. Modifier les données dans le modal
3. Cliquer **"Valider"**

### Supprimer une règle
1. Cliquer sur le bouton **Supprimer** (poubelle) d'une règle
2. Confirmer la suppression

### Activer/Désactiver une règle
1. Utiliser le **Switch** à droite de chaque règle
2. La règle sera immédiatement activée ou désactivée

## 📋 Niveaux d'importance

| Niveau | Badge | Utilisation |
|--------|-------|------------|
| **Important** | 🔴 Rouge | Règle critique que l'IA doit absolument respecter |
| **Moyen** | 🔵 Bleu | Règle standard à considérer |
| **Supprimé** | ⚫ Gris | Règle désactivée (archived) |

## 🔧 Détails techniques

### Authentification requise
- Toutes les actions requièrent le rôle **responsable** (get) ou **admin** (create/update/delete)
- Les actions sont protégées par CSRF token automatique

### Format des données
- **Titre**: max 255 caractères
- **Description**: max 5000 caractères (suffisant pour des instructions IA détaillées)
- **Importance**: `'important'`, `'moyen'`, ou `'supprime'` (sensible à la casse)

### Tri et affichage
- Les règles sont triées par:
  1. Importance (Important > Moyen > Supprimé)
  2. Date de création (plus récentes en premier)

## 📱 État du switch
- **Actif (ON)**: la règle sera prise en charge lors de la génération de planning par l'IA
- **Inactif (OFF)**: la règle sera ignorée par l'IA

---

**Date de création**: 18 mars 2026
**Statut**: ✅ Production Ready
