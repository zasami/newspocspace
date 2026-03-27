# Cahier des Charges – Application Web de Gestion des Plannings

**Projet** : EMS Personnes Âgées – Genève

**Version** : 1.3 Finale

**Date** : 13 mars 2026

**Développeur** : Vous-même

**Nombre de collaborateurs** : 98

**Stack technique choisie** : PHP 8.2+ (Laravel 11 fortement recommandé), MySQL 8 / MariaDB, Frontend : Blade + Alpine.js ou Vue 3 + Inertia.js, Bootstrap 5

**Hébergement cible** : Suisse (Infomaniak, Hostpoint, Swisscom ou équivalent – conformité LPD/RGPD)

---

## 1. Contexte & Objectifs

Développement d'une application web interne pour optimiser la gestion des plannings dans un EMS de 98 collaborateurs (infirmières, ASCC, aides-soignants qualifiés/non qualifiés, stagiaires, civilistes).

**Taux d'activité variables** : 20 % à 100 %.

**Fonctionnalités clés** :

- Saisie et prise en compte des désirs (max 4 jours/mois : jour off ou horaire spécial).
- Gestion absences (vacances, maladie, accident…).
- Génération automatique du planning mensuel (lancement le 15 pour le mois suivant).
- Répartition par étages / modules avec vue hebdomadaire globale + export Excel.
- Gestion flexible des absences : remplacement interne, appel intérim, entraide entre collègues, ou poste vacant.
- Intégration semi-automatique avec Polypoint (import/export CSV ou API si accès obtenu).
- Portail sécurisé accessible à tous via email / mot de passe.

**Objectif global** : Remplacer ou compléter les outils actuels (Polypoint + Excel manuel) pour gagner du temps et réduire les erreurs.

---

## 2. Rôles & Permissions

Utiliser Laravel + Spatie Permission ou Gate simple.

### 2.1 Collaborateur (98 comptes)

- Profil
- Saisie désirs/vacances
- Historique personnel
- Vue demandes vacances collègues (nom + dates + statut + étage/module)

### 2.2 Responsable / Chef

- Validation désirs/vacances
- Gestion absences + choix remplacements
- Génération planning
- Vue répartition
- Stats basiques

### 2.3 Admin / Direction

- CRUD total (utilisateurs, contrats, types horaires, modules/étages)
- Stats détaillées
- Exports

---

## 3. Fonctionnalités Détaillées

### 3.1 Authentification & Profil (tous)

- Connexion : email + password (hash bcrypt), option "Mot de passe oublié" (email reset).
- Profil éditable : photo (upload), taux %, fonction, type contrat, durée, module/étage principal (multi possible), solde vacances, coordonnées.
- Historique complet : tous ses désirs, vacances, absences.
- Bouton "Écrire à la chef" → formulaire → envoi email + trace en base.

### 3.2 Espace Collaborateur

- Désirs mois M+1 : ouvert 1er–10 du mois → max 4 jours → choix "Jour off" ou "Horaire spécial (texte libre)" → soumission → attente validation.
- Demande vacances : dates début/fin, motif, commentaire → statut (en attente / validé / refusé).
- Page publique "Demandes vacances collègues" : tableau filtrable (nom, prénom, dates, statut, étage/module) – pas de motif médical (RGPD).

### 3.3 Espace Responsable / Admin

#### CRUD Collaborateurs

- Import initial CSV (98 lignes) : colonnes suggérées : EmployeeID, Nom, Prénom, Fonction, Taux, Contrat, ModuleEtagePrincipal, Email.
- Édition individuelle : ajout multi-étages/modules.

#### Modules / Étages (table dédiée)

- Exemple : Étage 1, Étage 2 → Module 1 (Étages 1+2), Module 2 (Étage 3), etc.
- CRUD admin.

#### Types d'horaires (CRUD)

- Code (A1, D3…), Heure début (HH:MM), Heure fin, Pauses payées (nb), Pauses non payées (nb), Durée effective calculée auto.

#### Gestion absences (maladie, accident, vacances, congé spécial…)

- Justification (oui/non).
- Options par absence :
  - Remplacer par collègue disponible (liste filtrée : compétences + dispo + étage différent).
  - Marquer "Intérim requis" → email auto à liste intérim (configurable).
  - Laisser vacant (alerte rouge dans planning).
  - Entraide → notification email/group aux collègues même module.

#### Génération planning (bouton le 15)

- Inputs : besoins couverture par poste/jour/étage (table configurable).
- Priorités : désirs validés > vacances/absences > taux % > règles suisses (repos 11h, max heures…).
- Équilibrage auto effectifs par module/étage.
- Résultat provisoire (table éditable) → validation → finalisation.

#### Répartition hebdomadaire globale (nouvelle page)

- Tableau : Semaine X → Jours → Étages/Modules → Collaborateurs assignés, horaires, statut (présent / absent / remplacé / intérim / entraide).
- Filtres : semaine, étage, fonction.
- Export Excel (.xlsx) via PhpSpreadsheet : une feuille par semaine ou global.

### 3.4 Statistiques (Direction)

- Taux absence global / par personne / par mois.
- Absences justifiées / non justifiées.
- % désirs respectés.
- Remplacements internes vs intérim vs vacant.
- Répartition effectifs par étage/module.
- Graphiques simples (Chart.js).

---

## 4. Intégration Polypoint (priorité moyenne)

**Option optimale** : API REST (https://api.polypointservices.ch – docs existent, mais accès client requis → contacter support@polypoint.ch ou developer@polypoint.ch).

**Fallback réaliste** : CSV import/export manuel ou via dossier partagé/SFTP.

### Format CSV planning suggéré (à valider auprès de Polypoint) :

```
textEmployeeID,LastName,FirstName,Date,ShiftCode,StartTime,EndTime,PaidBreaks,UnpaidBreaks,AbsenceType,ModuleEtage,RemplacantID,Notes
EMP001,Dupont,Jean,2026-04-01,A1,07:30,16:00,1,1,,Module1 - Étage1+2,,
EMP002,Martin,Anne,2026-04-02,, , , , ,MALADIE,Module2 - Étage3,,Arrêt justifié
EMP003,Smith,Paul,2026-04-15,D3,07:00,20:30,2,1,VACANCES,Module1 - Étage1+2,EMP001,Remplacé par Dupont
```

- Import : lire CSV Polypoint → remplir base (collaborateurs, absences existantes).
- Export : générer CSV compatible pour ré-injection dans Polypoint.

---

## 5. Structure Base de Données (MySQL) – Tables principales suggérées

- `users` : id, email, password, nom, prénom, taux, fonction_id, photo, remember_token…
- `roles` / `permissions` (Spatie ou manuel)
- `modules_etages` : id, nom (ex. "Module 1 - Étages 1+2")
- `user_modules` : pivot (user_id ↔ module_id)
- `horaires_types` : id, code, debut, fin, pauses_payees, pauses_non_payees
- `desirs` : user_id, date, type (off/special), detail, statut, created_at
- `absences` : user_id, date_debut, date_fin, type, justifie, commentaire, remplacement_user_id, interim_requis, entraide_notifie
- `plannings` : id, mois_annee, statut (provisoire/final), json_data ou table détaillée
- `planning_assignations` : planning_id, user_id, date, horaires_type_id, module_id, notes…
- `besoins_couverture` : module_id, jour_semaine, poste, nb_requis

---

## 6. Exigences Techniques & Bonnes Pratiques

- Laravel 11 : auth, queues (pour emails/crons), jobs.
- Frontend responsive (mobile first – collaborateurs sur téléphone).
- Sécurité : HTTPS, validation inputs, RGPD (logs suppressibles, consentement).
- Exports : PhpSpreadsheet pour XLSX.
- Emails : Mailgun / SMTP suisse ou Laravel Mail.
- Cron : nightly import Polypoint si API/CSV auto.

---

## 7. Planning de Développement Personnel (estimation réaliste)

1. **Semaine 1–2** : Setup Laravel, auth, profils, CRUD users + modules/étages.
2. **Semaine 3** : Types horaires, désirs + validation.
3. **Semaine 4** : Vacances, page publique vacances.
4. **Semaine 5–6** : Absences + logique remplacement/entraide/intérim.
5. **Semaine 7–8** : Génération planning + algorithme basique (priorités + équilibrage simple).
6. **Semaine 9** : Vue répartition hebdo + export Excel.
7. **Semaine 10** : Stats + Chart.js.
8. **Semaine 11–12** : Tests, responsive, imports/exports CSV, documentation.
9. **Semaine 13+** : Peaufinage, déploiement, formation utilisateurs.

---

## 8. Prochaines Actions Immédiates

1. Installer Laravel + Sanctum ou Breeze pour auth.
2. Créer base MySQL + migrations pour tables ci-dessus.
3. Contacter Polypoint (developer@polypoint.ch) pour docs API / template CSV exact.
4. Commencer par l'auth + profils (base solide).
