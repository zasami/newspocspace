# SpocSpace - Mode Hors-Ligne et Securite

## Table des matieres

1. [Vue d'ensemble](#1-vue-densemble)
2. [Architecture offline-first](#2-architecture-offline-first)
3. [Login et authentification](#3-login-et-authentification)
4. [Verrouillage automatique](#4-verrouillage-automatique)
5. [Donnees disponibles hors ligne](#5-donnees-disponibles-hors-ligne)
6. [File d'attente et synchronisation](#6-file-dattente-et-synchronisation)
7. [Resolution de conflits](#7-resolution-de-conflits)
8. [Securite des donnees locales](#8-securite-des-donnees-locales)
9. [Limites du mode hors-ligne](#9-limites-du-mode-hors-ligne)
10. [Schema recapitulatif](#10-schema-recapitulatif)

---

## 1. Vue d'ensemble

SpocSpace fonctionne comme une **application native** : une fois installee (PWA) ou visitee au moins une fois, l'application est **100% utilisable sans connexion internet**. Toutes les pages, donnees et fonctionnalites sont disponibles localement. Au retour de la connexion, les modifications sont synchronisees automatiquement avec le serveur.

### Principe fondamental

> L'utilisateur ne devrait pas pouvoir distinguer s'il est en ligne ou hors ligne pendant son utilisation normale. La seule difference visible est un petit indicateur de statut dans la barre superieure.

### Prerequis

- L'utilisateur doit s'etre connecte **au moins une fois** avec internet (premiere visite).
- Navigateur compatible : Chrome, Edge, Firefox, Safari (iOS 16.4+).
- La PWA peut etre installee sur l'ecran d'accueil (mobile) ou en tant qu'application desktop.

---

## 2. Architecture offline-first

### Composants techniques

| Composant | Role | Fichier |
|-----------|------|---------|
| **Service Worker** | Intercepte toutes les requetes reseau, sert le cache si offline | `sw.js` |
| **IndexedDB** | Base de donnees locale (20 stores) pour toutes les donnees | `assets/js/ss-db.js` |
| **Module Offline** | Gestion de la file d'attente, sync delta, indicateurs UI | `assets/js/modules/offline.js` |
| **Lock Screen** | Verrouillage automatique par inactivite | `assets/js/lockscreen.js` |
| **PWA Manifest** | Configuration de l'app installable | `manifest.json` |

### Strategies de cache (Service Worker)

| Type de ressource | Strategie | Detail |
|-------------------|-----------|--------|
| Assets statiques (CSS, JS, images) | **Cache-first** | Servies depuis le cache, mises a jour en arriere-plan |
| Pages PHP (28 fragments) | **Pre-cachees a l'installation** | Toutes les pages disponibles immediatement |
| Modules JS (28 fichiers) | **Pre-caches a l'installation** | Toute la logique applicative disponible |
| Requetes API (lecture) | **Network-first** | Essaie le reseau, cache fallback si offline |
| Requetes API (ecriture) | **File d'attente** | Mise en queue si offline, sync au retour |

### Stores IndexedDB (20 tables locales)

```
planning        messages        users           desirs
absences        vacances        notifications   changements
annonces        documents       votes           sondages
pv              wiki_pages      mur             collegues
covoiturage     cuisine_menus   sync_queue      meta
```

Le store `meta` contient : token d'authentification, hash du mot de passe, donnees du shell, timestamp de derniere synchronisation, timestamp de derniere activite, horaires types, categories wiki, fiches de salaire (metadonnees).

---

## 3. Login et authentification

### Trois scenarios de connexion

```
SCENARIO 1 : En ligne (cas normal)
  Utilisateur tape email + mot de passe
    -> Verification serveur (API login)
    -> Session PHP creee
    -> Token, hash mot de passe et donnees shell sauvegardes localement
    -> Redirection vers l'accueil

SCENARIO 2 : Hors ligne, activite recente (< 15 minutes)
  Utilisateur ouvre l'app
    -> Token local valide + activite < 15 min
    -> AUTO-LOGIN DIRECT (pas de formulaire)
    -> Donnees restaurees depuis IndexedDB
    -> Redirection vers l'accueil

SCENARIO 3 : Hors ligne, inactif depuis plus de 15 minutes
  Utilisateur ouvre l'app
    -> Token local valide MAIS activite > 15 min
    -> Formulaire de login affiche
    -> Mot de passe verifie LOCALEMENT (PBKDF2)
    -> Acces accorde sans internet
```

### Durees de validite

| Element | Duree | Apres expiration |
|---------|-------|-----------------|
| Token offline | **72 heures** | Login en ligne obligatoire |
| Auto-login sans mot de passe | **15 minutes** d'inactivite | Mot de passe requis (local ou serveur) |
| Session PHP serveur | Selon config serveur | Re-authentification au prochain sync |

### Stockage local des credentials

Le mot de passe n'est **jamais** stocke en clair. Il est transforme via :

- **Algorithme** : PBKDF2-SHA-256
- **Iterations** : 600 000 (recommandation OWASP 2024)
- **Salt** : 16 octets aleatoires + email de l'utilisateur
- **API** : WebCrypto (`crypto.subtle.deriveBits`) — natif navigateur, zero dependance

Comparaison des temps de brute-force :

| Methode | Vitesse d'attaque GPU | Temps pour un mot de passe 8 caracteres |
|---------|----------------------|----------------------------------------|
| SHA-256 simple | ~10 milliards/sec | < 1 seconde |
| **PBKDF2 600k iter** | **~10/sec** | **> 100 ans** |

---

## 4. Verrouillage automatique (Lock Screen)

### Fonctionnement

L'application se verrouille automatiquement apres **15 minutes d'inactivite**. Un ecran de verrouillage apparait par-dessus l'application (z-index maximum), empechant tout acces au contenu.

### Declencheurs

| Evenement | Action |
|-----------|--------|
| Aucune interaction pendant 15 min (click, scroll, touche, tap) | Lock screen |
| Retour sur l'onglet/app apres > 15 min en arriere-plan | Lock screen |
| Ouverture de l'app apres > 15 min | Lock screen |

### Ecran de verrouillage

L'ecran affiche :
- **Avatar et nom** de l'utilisateur connecte
- **Champ mot de passe** pour le deverrouillage
- **Indicateur en ligne/hors ligne**
- **Bouton "Se deconnecter"** pour changer d'utilisateur

### Deverrouillage

```
EN LIGNE :
  Mot de passe saisi
    -> Verification sur le serveur (API login)
    -> Session PHP rafraichie
    -> Hash local mis a jour
    -> App deverrouillee

HORS LIGNE :
  Mot de passe saisi
    -> Verification locale (PBKDF2 600k iterations, ~300-500ms)
    -> App deverrouillee si correct
    -> Shake + erreur si incorrect
```

### Suivi d'activite

L'activite de l'utilisateur est suivie via les evenements DOM :
- `click`, `keydown`, `scroll`, `touchstart`
- Ecriture en IndexedDB toutes les 30 secondes maximum (throttle pour performance)
- Verification toutes les 30 secondes + a chaque retour sur l'onglet (`visibilitychange`)

---

## 5. Donnees disponibles hors ligne

### Lecture (consultation)

Toutes les donnees consultees au moins une fois en ligne sont disponibles hors ligne :

| Donnee | Disponible offline | Fraicheur |
|--------|-------------------|-----------|
| Planning (semaine/mois) | Oui | Mois en cours, sync toutes les 5 min |
| Desirs | Oui | 60 derniers jours |
| Absences | Oui | 30 derniers jours |
| Vacances | Oui | Annee en cours + precedente |
| Messages internes | Oui | 30 derniers jours, 100 max |
| Notifications | Oui | 30 derniers jours, 50 max |
| Changements de shift | Oui | 30 derniers jours, 50 max |
| Collegues | Oui | Liste complete des actifs |
| Annonces | Oui | 60 derniers jours, 30 max |
| Documents (liste) | Oui | Metadonnees, 100 max |
| Votes | Oui | Propositions ouvertes |
| Sondages | Oui | Sondages ouverts |
| Proces-Verbaux | Oui | 6 derniers mois, 30 max |
| Wiki | Oui | Categories + pages publiees |
| Mur social | Oui | 14 derniers jours, 50 posts |
| Covoiturage | Oui | Buddies de l'utilisateur |
| Menus cuisine | Oui | 2 prochaines semaines |
| Fiches salaire (liste) | Oui | 24 dernieres metadonnees |
| Horaires types | Oui | Tous les actifs |

### Ecriture (actions)

Les actions d'ecriture sont mises en **file d'attente** et executees au retour de la connexion :

| Action | Queuable offline | Validation locale |
|--------|-----------------|-------------------|
| Soumettre un desir | Oui | date + horaire requis |
| Modifier/supprimer un desir | Oui | ID requis |
| Soumettre une absence | Oui | dates + type requis |
| Soumettre des vacances | Oui | dates requises |
| Envoyer un message | Oui | sujet + contenu requis |
| Marquer message lu | Oui | ID requis |
| Proposer un changement | Oui | destinataire + date requis |
| Confirmer/refuser changement | Oui | ID requis |
| Voter | Oui | proposal + choix requis |
| Repondre a un sondage | Oui | sondage_id requis |
| Poster sur le mur | Oui | contenu requis |
| Liker / commenter | Oui | post_id requis |
| Reserver un menu | Oui | menu_id requis |
| Noter un PV | Oui | pv_id + note requis |
| Modifier profil | Oui | - |
| Changer mot de passe | Oui | ancien + nouveau requis |

---

## 6. File d'attente et synchronisation

### Cycle de synchronisation

```
TOUTES LES 5 MINUTES (si en ligne) :
  1. Delta sync : le client demande au serveur "quoi de neuf depuis [timestamp] ?"
  2. Le serveur retourne uniquement les donnees modifiees
  3. Les donnees sont stockees dans IndexedDB
  4. Le timestamp de derniere sync est mis a jour

AU RETOUR EN LIGNE (apres une periode offline) :
  1. Execution de la file d'attente (actions queues pendant l'offline)
  2. Barre de progression : "Synchronisation en cours... X/Y"
  3. Chaque action est envoyee au serveur une par une
  4. Succes : retiree de la queue
  5. Conflit : retiree + notification toast
  6. Erreur : conservee avec message d'erreur pour retry
  7. Delta sync pour recuperer les dernieres donnees
```

### Indicateurs visuels

| Element | Emplacement | Signification |
|---------|-------------|---------------|
| Point vert | Barre superieure, a cote du logo | En ligne |
| Point rouge | Barre superieure, a cote du logo | Hors ligne |
| Badge chiffre | A cote du point de connexion | Nombre d'actions en file d'attente |
| Barre jaune (bas) | Bas de l'ecran | "Mode hors-ligne — les donnees seront synchronisees au retour" |
| Barre noire + progression | Bas de l'ecran | Synchronisation en cours |
| Barre verte | Bas de l'ecran | "X action(s) synchronisee(s)" |
| Barre orange | Bas de l'ecran | "X OK, Y erreur(s)" ou conflits |

---

## 7. Resolution de conflits

### Strategie : Last-Write-Wins (LWW) + notification

Quand un utilisateur modifie des donnees hors ligne et qu'un autre utilisateur (ou un responsable) a modifie les memes donnees entre-temps sur le serveur :

```
Utilisateur A (offline) modifie un desir a 14h00
Responsable B (online) modifie le meme desir a 14h30
Utilisateur A revient en ligne a 15h00
  -> Le serveur detecte que le desir a ete modifie a 14h30 (apres 14h00)
  -> CONFLIT : la version du serveur (14h30) est conservee
  -> L'utilisateur A recoit un toast : "Desir: modifie entre-temps — version serveur conservee"
  -> L'action est retiree de la file d'attente (pas de retry infini)
```

### Detection technique

Chaque action offline porte un timestamp `_queued_at`. Au moment de l'execution cote serveur :

1. Le serveur compare `_queued_at` avec `updated_at` de l'enregistrement
2. Si `updated_at` > `_queued_at` → conflit detecte
3. Le serveur retourne `{ conflict: true, message: "..." }`
4. Le client affiche un toast et retire l'action de la queue

### Actions protegees par la detection de conflit

- Modification de desirs
- Confirmation/refus de changements de shift
- (Extensible a toute action avec un champ `updated_at`)

### Pourquoi pas de merge automatique ?

Dans le contexte d'un EMS :
- Les conflits sont **rares** (un responsable planifie, les employes consultent)
- La complexite d'un merge automatique (type CRDT) n'est pas justifiee
- La transparence (notification) est preferee a un merge silencieux potentiellement incorrect

---

## 8. Securite des donnees locales

### Resume des mesures

| Mesure | Detail |
|--------|--------|
| Mot de passe | PBKDF2-SHA-256, 600 000 iterations, salt aleatoire 16 octets |
| Token d'authentification | SHA-256 avec secret aleatoire, expire apres 72h |
| Donnees IndexedDB | Stockees dans le sandbox du navigateur (meme origine) |
| CSRF | Token stocke et envoye avec chaque requete d'ecriture |
| Verrouillage automatique | 15 min d'inactivite, overlay z-index 99999 |
| Nettoyage | Messages > 30 jours supprimes automatiquement |
| Deconnexion | Supprime le token local, redirige vers login |

### Ce qui N'EST PAS stocke localement

- Le mot de passe en clair (jamais)
- Les fichiers (PDF fiches de salaire, justificatifs) — uniquement les metadonnees
- Les donnees d'autres utilisateurs (sauf ce qui est visible dans l'app : collegues, mur)
- Les donnees admin (routes admin non cachees)

### Scenarios d'attaque

| Scenario | Protection |
|----------|-----------|
| Vol de telephone deverrouille, app ouverte | Lock screen apres 15 min d'inactivite |
| Vol de telephone verrouille | PIN/Face ID du telephone + lock screen SpocSpace |
| Acces physique au PC, onglet ouvert | Lock screen apres 15 min |
| Lecture IndexedDB via DevTools | Mot de passe hache en PBKDF2 (incrackable en brute-force raisonnable) |
| Interception reseau | HTTPS obligatoire, CSRF token |
| Token vole | Expire en 72h, inutilisable sans la session PHP serveur |

---

## 9. Limites du mode hors-ligne

### Ce qui necessite internet

| Fonctionnalite | Raison |
|----------------|--------|
| **Premiere connexion** | Le serveur doit valider les identifiants et creer la session |
| **Envoi reel de messages** | Le destinataire doit recevoir (queue en attendant) |
| **Notifications push** | Necessite le serveur push |
| **Upload de fichiers** (avatar, justificatif) | Fichier trop lourd pour la queue |
| **Telechargement de documents** non caches | Le PDF n'est pas en cache local |
| **Recherche globale** | Necessite la base de donnees serveur |
| **Temps reel** | Pas de chat instantane sans reseau |

### Ce qui est degrade hors ligne

| Fonctionnalite | Comportement offline |
|----------------|---------------------|
| Nombre de messages non lus | Affiche 0 (pas de donnee temps reel) |
| Alertes en attente | Liste vide (pas de nouvelles alertes) |
| Photos/avatars jamais vus | Placeholder au lieu de l'image |
| Donnees de plus de 30 jours | Nettoyees automatiquement |

---

## 10. Schema recapitulatif

```
OUVERTURE DE L'APP
       |
       v
  Token local existe ?
       |
   NON |          OUI
       |            |
       v            v
  Page Login    Token expire (> 72h) ?
  (online        |
  obligatoire)  OUI |         NON
                 |            |
                 v            v
            Page Login    Activite recente (< 15 min) ?
            (online        |
            obligatoire)  OUI |         NON
                           |            |
                           v            v
                      AUTO-LOGIN    Formulaire Login
                      (direct)      (verification locale PBKDF2
                           |         si offline, serveur si online)
                           |            |
                           v            v
                     APP OUVERTE <------+
                           |
                           v
                  [ Utilisation normale ]
                           |
                    Inactivite > 15 min ?
                           |
                      OUI  |
                           v
                     LOCK SCREEN
                           |
                     Mot de passe
                           |
                  Online ? -> Serveur
                  Offline ? -> PBKDF2 local
                           |
                           v
                     APP DEVERROUILLEE
                           |
                           v
                  [ Sync en arriere-plan ]
                      Toutes les 5 min
                    Delta sync + queue
```

---

## Fichiers techniques de reference

| Fichier | Role |
|---------|------|
| `sw.js` | Service Worker — cache, interception reseau, sync queue |
| `assets/js/ss-db.js` | IndexedDB — 20 stores, auth, PBKDF2, activite |
| `assets/js/modules/offline.js` | Delta sync, file d'attente, indicateurs UI |
| `assets/js/lockscreen.js` | Verrouillage automatique par inactivite |
| `assets/js/modules/auth.js` | Login online + offline avec auto-login |
| `assets/js/app.js` | Boot offline, init lockscreen, persist shell |
| `api_modules/sync.php` | API delta sync — 15+ tables |
| `init.php` | Helpers `conflict_response()`, `check_offline_conflict()` |

---

*Document genere le 13 avril 2026 — SpocSpace v5 (offline-first)*
