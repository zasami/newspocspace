# SpocSpace - Roadmap / Todolist

> Classement par difficulte croissante. Cocher au fur et a mesure.

---

## 1. FACILE (1-2 jours chacun)

- [ ] **Statistiques : filtres temporels**
  Ajouter filtres par jour, semaine, mois, annee, periode personnalisee sur les stats existantes.

- [ ] **Planning : afficher absences/grossesse/vacances/accident**
  Marquer visuellement dans le planning les absences longues, conges maternite, vacances, accidents.

- [ ] **Export PDF / Word / Excel des statistiques**
  Boutons d'export sur les pages de stats (tableaux + graphiques).

- [ ] **Rubrique petites annonces**
  Page simple pour publier/consulter des annonces internes entre collegues.

- [ ] **Ajouter une annonce (alertes ameliorees)**
  Enrichir le systeme d'alertes existant pour servir d'annonces internes avec plus de visibilite.

- [ ] **Creation d'employes + attribution compte/mot de passe**
  Formulaire admin pour creer un employe, generer identifiants, envoyer par email.

- [ ] **Impression PDF/Word du menu cuisine**
  Export du menu de la semaine en PDF et Word depuis la page restauration.

---

## 2. MOYEN (3-5 jours chacun)

- [ ] **Statistiques d'interim, absences, grossesses**
  Tableaux + graphiques comparatifs sur plusieurs annees, par periode. Export PDF/Word/Excel.

- [ ] **Importation de contacts (Google, CSV, autre source)**
  Import de contacts pour chaque admin depuis Google Contacts, fichier CSV ou autre.

- [ ] **Agenda / prise de RDV**
  Calendrier pour fixer des rendez-vous, gerer les disponibilites selon vacances et planning.

- [ ] **Sauvegarde automatique BDD + page de restauration**
  Cron de backup quotidien (BDD + fichiers), page admin pour lister/restaurer les sauvegardes.

- [ ] **Script de mise a jour a distance**
  Script pour deployer les MAJ du code sur le serveur (git pull ou upload ZIP + migration auto).

- [ ] **Mur d'actualite / fil d'actu**
  Page "actualites" : posts, commentaires, likes. Confidentialite par role. Base du reseau interne.

- [ ] **Module Restauration / Cuisine**
  - Saisie des menus de la semaine par le cuisinier
  - Base de donnees de plats recurrents (selection rapide)
  - Affichage automatique sur le site (pas de double saisie)
  - Export PDF / Word du menu

- [ ] **Table VIP (residents)**
  Selectionner une liste de residents, notifier les modules concernes par alerte/notification.

---

## 3. DIFFICILE (1-2 semaines chacun)

- [ ] **Module Animation**
  - Saisie des activites par l'animateur
  - Animations repetitives (selection pour la semaine)
  - Affichage automatique sur le site
  - Objectifs animation lies aux modules

- [ ] **Proposition IA pour animations**
  IA qui suggere des idees d'animations selon : budget, faisabilite, securite, competences disponibles.

- [ ] **Rubrique Formation (pour Marceline)**
  - Catalogue de formations
  - Suivi des formations par employe
  - Lien avec les logiciels de formation utilises par l'EMS

- [ ] **Formulaire de postulation / repondre a une postulation**
  - Formulaire public de candidature
  - Interface admin pour gerer les candidatures

- [ ] **Suivi de dossier candidat**
  - Statuts : en cours / entretien / accepte / refuse / consulte
  - Appreciation et rapport du formateur
  - Points a ameliorer
  - Visibilite : admin (Marceline) + formateur voient tout, candidat voit certaines donnees
  - Fil d'actualite ou tchat direct candidat-formateur

- [ ] **Ameliorer la transcription de PV** _(haute importance, difficulte +++++)_
  - Ameliorer la qualite de reconnaissance vocale
  - Gestion des locuteurs multiples
  - Correction automatique du contexte EMS
  - Formatage intelligent des comptes-rendus

- [ ] **Confidentialite et chiffrement des donnees**
  - Revoir les niveaux d'acces par role
  - Chiffrement E2E pour les donnees sensibles (candidatures, appreciations, messages)
  - Audit de securite global

---

## Notes

- **Priorite haute** : Transcription PV, Statistiques, Planning absences
- **Dependances** : Le module Formation depend du suivi de dossier. L'IA animation depend du module Animation.
- **Marceline** : Admin principale pour Formation + Suivi candidats
