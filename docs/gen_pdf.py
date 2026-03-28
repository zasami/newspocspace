#!/usr/bin/env python3
"""Generate PDF version of Espace Famille guide"""
from fpdf import FPDF

class PDF(FPDF):
    def header(self):
        if self.page_no() > 1:
            self.set_font('Helvetica', 'I', 8)
            self.set_text_color(150)
            self.cell(0, 8, 'Espace Famille — Guide complet — zerdaTime', align='C')
            self.ln(10)

    def footer(self):
        self.set_y(-15)
        self.set_font('Helvetica', 'I', 8)
        self.set_text_color(150)
        self.cell(0, 10, f'Page {self.page_no()}/{{nb}}', align='C')

    def h1(self, txt):
        self.set_font('Helvetica', 'B', 18)
        self.set_text_color(46, 125, 50)
        self.ln(4)
        self.multi_cell(0, 10, txt)
        self.ln(2)

    def h2(self, txt):
        self.set_font('Helvetica', 'B', 14)
        self.set_text_color(46, 125, 50)
        self.ln(2)
        self.multi_cell(0, 8, txt)
        self.ln(1)

    def h3(self, txt):
        self.set_font('Helvetica', 'B', 12)
        self.set_text_color(60, 60, 60)
        self.ln(1)
        self.multi_cell(0, 7, txt)
        self.ln(1)

    def body_text(self, txt):
        self.set_font('Helvetica', '', 10)
        self.set_text_color(30, 30, 30)
        self.multi_cell(0, 6, txt)
        self.ln(2)

    def bullet(self, txt, bold_prefix=None):
        self.set_font('Helvetica', '', 10)
        self.set_text_color(30, 30, 30)
        x = self.get_x()
        self.cell(8, 6, chr(8226))
        if bold_prefix:
            self.set_font('Helvetica', 'B', 10)
            self.write(6, bold_prefix)
            self.set_font('Helvetica', '', 10)
            self.write(6, ' — ' + txt)
        else:
            self.write(6, txt)
        self.ln(7)

    def note(self, txt):
        self.set_font('Helvetica', 'I', 9)
        self.set_text_color(100, 100, 100)
        x = self.get_x()
        self.set_x(x + 10)
        self.multi_cell(170, 5, 'i  ' + txt)
        self.ln(2)

    def add_simple_table(self, headers, rows):
        col_w = (self.w - 20) / len(headers)
        self.set_font('Helvetica', 'B', 9)
        self.set_fill_color(46, 125, 50)
        self.set_text_color(255)
        for h in headers:
            self.cell(col_w, 8, h, border=1, fill=True, align='C')
        self.ln()
        self.set_font('Helvetica', '', 9)
        self.set_text_color(30, 30, 30)
        fill = False
        for row in rows:
            if fill:
                self.set_fill_color(245, 245, 240)
            for val in row:
                self.cell(col_w, 7, str(val), border=1, fill=fill)
            self.ln()
            fill = not fill
        self.ln(4)


pdf = PDF()
pdf.alias_nb_pages()
pdf.set_auto_page_break(auto=True, margin=20)

# ── Cover ──
pdf.add_page()
pdf.ln(50)
pdf.set_font('Helvetica', 'B', 36)
pdf.set_text_color(46, 125, 50)
pdf.cell(0, 20, 'Espace Famille', align='C', ln=True)
pdf.set_font('Helvetica', '', 16)
pdf.set_text_color(100)
pdf.cell(0, 10, 'Guide complet — Fonctionnement et administration', align='C', ln=True)
pdf.ln(10)
pdf.set_font('Helvetica', '', 12)
pdf.set_text_color(60)
pdf.cell(0, 8, 'zerdaTime — EMS La Terrassiere SA', align='C', ln=True)
pdf.cell(0, 8, 'Mars 2026', align='C', ln=True)

# ── TOC ──
pdf.add_page()
pdf.h1('Table des matieres')
toc = [
    '1. Presentation generale',
    '2. Architecture et securite E2EE',
    '3. Guide administration (staff)',
    '4. Guide famille (correspondants)',
    '5. Reservation restaurant (famille)',
    '6. Gestion des residents',
    '7. Stockage et capacite',
    '8. Securite detaillee',
    '9. FAQ / Depannage',
]
for t in toc:
    pdf.body_text(t)

# ── 1 ──
pdf.add_page()
pdf.h1('1. Presentation generale')
pdf.body_text("L'Espace Famille est un module de zerdaTime qui permet aux familles et correspondants des residents de suivre la vie quotidienne de leur proche au sein de l'EMS. Il offre trois volets principaux :")
pdf.bullet("Suivi des activites et animations (sorties, ateliers, fetes)", "Activites")
pdf.bullet("Consultation des avis medicaux, rapports et ordonnances", "Suivi medical")
pdf.bullet("Albums photo organises par date et par annee", "Galerie photos")
pdf.body_text("Tous les fichiers (photos, documents PDF/Word/Excel) sont chiffres de bout en bout (E2EE). Le serveur ne voit jamais les fichiers en clair. Seul le correspondant authentifie peut les dechiffrer dans son navigateur grace au code d'acces du resident.")
pdf.h2('Qui fait quoi ?')
pdf.add_simple_table(
    ['Role', 'Actions', 'Acces'],
    [
        ['Admin / Direction', 'Creer, uploader, gerer cles E2EE', 'Panel admin'],
        ['Responsable / Infirmier', 'Memes actions que admin', 'Panel admin'],
        ['Animateur', 'Creer activites + photos', 'Panel admin'],
        ['Famille', 'Consulter (lecture seule)', 'Site web'],
    ]
)

# ── 2 ──
pdf.add_page()
pdf.h1('2. Architecture et securite E2EE')
pdf.body_text("E2EE signifie End-to-End Encryption. Les fichiers sont chiffres dans le navigateur AVANT envoi au serveur, et dechiffres dans le navigateur APRES telechargement. Le serveur ne stocke que des donnees illisibles.")
pdf.h2('Algorithmes utilises')
pdf.bullet("AES-256-GCM pour le chiffrement des fichiers (standard militaire)", "Chiffrement")
pdf.bullet("PBKDF2 avec 100 000 iterations + SHA-256", "Derivation de cle")
pdf.bullet("IV (vecteur d'initialisation) unique par fichier", "Unicite")
pdf.h2('Flux de chiffrement')
steps = [
    "1. L'admin genere une cle AES-256 aleatoire pour un resident",
    "2. Cette cle est enveloppee (chiffree) avec le code d'acces du resident via PBKDF2",
    "3. La cle enveloppee est stockee sur le serveur (illisible sans le code)",
    "4. Lors d'un upload, le fichier est chiffre cote navigateur avec la cle AES",
    "5. Le fichier chiffre (.enc) est envoye au serveur",
    "6. La famille se connecte, saisit le code -> le navigateur derive la cle",
    "7. Le navigateur telecharge le fichier chiffre et le dechiffre localement",
    "8. L'image/document s'affiche — le serveur n'a jamais vu le contenu",
]
for s in steps:
    pdf.body_text(s)

# ── 3 ──
pdf.add_page()
pdf.h1('3. Guide administration (staff)')
pdf.h2('3.1 Acces')
pdf.body_text("Panel admin -> Espace Famille dans la sidebar. Selectionnez un resident dans le menu deroulant.")
pdf.h2('3.2 Gestion des cles E2EE')
pdf.body_text("Avant d'uploader des fichiers, generez la cle E2EE du resident :")
pdf.bullet("Selectionnez le resident")
pdf.bullet("Cliquez sur 'Generer la cle E2EE'")
pdf.bullet("Le statut passe a 'Cle E2EE active' (badge vert)")
pdf.note("Necessite un code d'acces configure (auto-genere a la creation du resident).")
pdf.h2('3.3 Activites')
pdf.bullet("Cliquez 'Nouvelle activite' — titre + description", "Creer")
pdf.bullet("Enregistrez, puis '+Photos' pour ajouter des images", "Photos")
pdf.bullet("Conversion WebP + chiffrement automatique", "Conversion")
pdf.h2('3.4 Suivi medical')
pdf.bullet("'Nouvel avis' — titre, date, type (Avis/Rapport/Ordonnance/Autre)")
pdf.bullet("Le contenu texte est chiffre cote client")
pdf.bullet("'+Fichier' pour joindre PDF, Word, Excel, images")
pdf.h2('3.5 Galerie photos')
pdf.bullet("'Nouvel album' — titre, date, annee", "Creer")
pdf.bullet("'+Photos' pour ajouter des images", "Ajouter")
pdf.bullet("Premiere photo = couverture automatique", "Cover")
pdf.h2('3.6 Import par lot')
pdf.body_text("Le bouton 'Import par lot' dans l'onglet Galerie permet d'importer plusieurs photos. Le systeme regroupe par mois, cree un album par mois, convertit en WebP et chiffre automatiquement.")

# ── 4 ──
pdf.add_page()
pdf.h1('4. Guide famille (correspondants)')
pdf.h2('4.1 Connexion')
pdf.body_text("URL : https://zkriva.com/zerdatime/website/famille.php")
pdf.bullet("Email du correspondant")
pdf.bullet("Code d'acces = date de naissance JJMMAAAA (ex: 12031935)")
pdf.note("5 tentatives max par IP, blocage 15 minutes.")
pdf.h2('4.2 Tableau de bord')
pdf.body_text("La sidebar affiche la photo, nom et chambre du resident. L'accueil montre les statistiques et les dernieres activites/avis medicaux.")
pdf.h2('4.3 Activites')
pdf.bullet("Liste avec date, titre, description, badge photos")
pdf.bullet("Clic -> detail avec grille de photos dechiffrees")
pdf.bullet("Clic sur photo -> lightbox plein ecran")
pdf.h2('4.4 Suivi medical')
pdf.bullet("Timeline avec code couleur par type")
pdf.bullet("Contenu texte dechiffre automatiquement")
pdf.bullet("Fichiers joints : PDF en lightbox, autres en telechargement")
pdf.h2('4.5 Galerie photos')
pdf.bullet("Albums par annee avec couverture")
pdf.bullet("Clic album -> grille de miniatures")
pdf.bullet("Lightbox avec navigation fleches et clavier")

# ── 5 ──
pdf.add_page()
pdf.h1('5. Reservation restaurant (famille)')
pdf.h2('5.1 Acces et fonctionnement')
pdf.body_text("Le site web de l'EMS affiche les menus de la semaine en carousel. Chaque carte a un bouton 'Reserver un repas'.")
pdf.h2('5.2 Etapes de reservation')
pdf.body_text("1. Cliquer 'Reserver un repas' sur le jour souhaite")
pdf.body_text("2. Saisir email + code d'acces (date naissance JJMMAAAA)")
pdf.body_text("3. Le systeme identifie le resident")
pdf.body_text("4. Choisir Midi ou Soir")
pdf.body_text("5. Nombre de personnes (1-5)")
pdf.body_text("6. Remarques (allergies, regime...)")
pdf.body_text("7. Confirmer la reservation")
pdf.body_text("8. Ticket de confirmation affiche")
pdf.h3('Tarifs')
pdf.add_simple_table(['Repas', 'Prix'], [['Midi', 'CHF 14.50'], ['Soir', 'CHF 11.00']])
pdf.h3('Regles')
pdf.bullet("Impossible de reserver pour une date passee")
pdf.bullet("Une seule reservation par resident/repas/date")
pdf.h2('5.3 Menu de la semaine (carousel)')
pdf.bullet("28 jours affiches, 3 cartes visibles")
pdf.bullet("Fleches : avancer/reculer d'1 jour")
pdf.bullet("Passage automatique entre semaines")
pdf.bullet("Boutons semaine precedente/suivante")
pdf.bullet("Jour actuel surbrillance 'Aujourd'hui'")
pdf.h2('5.4 Administration des reservations')
pdf.body_text("Page admin 'Reservations repas' avec 2 onglets : Collaborateurs et Famille/Visiteurs. Bouton Imprimer pour la cuisine.")

# ── 6 ──
pdf.add_page()
pdf.h1('6. Gestion des residents')
pdf.body_text("Page admin 'Residents' — liste avec photo, nom, chambre, correspondant, code acces, statut VIP.")
pdf.h2('Photo du resident')
pdf.bullet("Clic sur une ligne -> modal d'edition")
pdf.bullet("Zone photo ronde cliquable pour upload")
pdf.bullet("Conversion WebP automatique")
pdf.bullet("Affichee dans la liste ET l'espace famille")
pdf.h2('Recherche')
pdf.body_text("Barre de recherche topbar (live search) par nom, prenom ou chambre.")

# ── 7 ──
pdf.add_page()
pdf.h1('7. Stockage et capacite')
pdf.add_simple_table(
    ['Parametre', 'Valeur'],
    [
        ['Taille moyenne WebP', '150-300 Ko'],
        ['Photos/jour (94 residents)', '~940'],
        ['Espace/mois', '~7 Go'],
        ['Espace/an', '~86 Go'],
        ['Duree avec 1 To', '~11 ans'],
        ['En pratique (2-3/res/jour)', '30+ ans'],
    ]
)
pdf.h2('Organisation fichiers')
pdf.body_text("uploads/famille/{resident_id}/activites/ | medical/ | galerie/")
pdf.note("Fichiers sous nom UUID + .enc. Nom original en base de donnees.")

# ── 8 ──
pdf.add_page()
pdf.h1('8. Securite detaillee')
pdf.add_simple_table(
    ['Mesure', 'Description'],
    [
        ['AES-256-GCM', 'Fichiers chiffres cote client'],
        ['PBKDF2 100K', 'Derivation de cle robuste'],
        ['Sessions token 96 car.', 'Expire en 24h'],
        ['Rate limiting', '5 tentatives / 15 min'],
        ['.htaccess Deny', 'Acces direct fichiers bloque'],
        ['CSP blob:', 'Images dechiffrees uniquement'],
        ['Cache-Control no-store', 'Pas de cache navigateur'],
        ['UUID fichiers', 'Noms originaux masques'],
        ['Verif resident_id', 'Isolation par correspondant'],
        ['CSRF token', 'Protection actions admin'],
        ['WebP conversion', 'Supprime metadonnees EXIF'],
    ]
)

# ── 9 ──
pdf.add_page()
pdf.h1('9. FAQ / Depannage')
faqs = [
    ("La famille ne peut pas se connecter",
     "Verifier email correspondant + code acces (JJMMAAAA). 5 tentatives max, blocage 15 min."),
    ("Photos non affichees (icone cadenas)",
     "Cle E2EE non generee. Admin -> Espace Famille -> Generer la cle."),
    ("Message 'Cle non disponible'",
     "Se deconnecter et se reconnecter avec le code d'acces."),
    ("Code d'acces change",
     "Regenerer la cle E2EE. ATTENTION : anciens fichiers illisibles, re-uploader."),
    ("Combien de photos par upload ?",
     "Pas de limite. Import par lot gere des centaines. ~1-2 sec/photo."),
    ("PDF medicaux securises ?",
     "Oui, meme AES-256-GCM. Serveur ne stocke que .enc illisibles."),
    ("Acces depuis telephone ?",
     "Oui, interface responsive. Sidebar -> menu hamburger sur mobile."),
]
for q, a in faqs:
    pdf.set_font('Helvetica', 'B', 10)
    pdf.set_text_color(30, 30, 30)
    pdf.multi_cell(0, 6, 'Q : ' + q)
    pdf.set_font('Helvetica', '', 10)
    pdf.multi_cell(0, 6, 'R : ' + a)
    pdf.ln(4)

# ── Save ──
output = '/home/clients/c81789f8de36e992da19fb6856aa48f6/sites/zkriva.com/Espace_Famille_Guide.pdf'
pdf.output(output)
print(f'PDF saved: {output}')
