#!/usr/bin/env python3
"""
Génère des fiches de salaire PDF de test pour tous les employés actifs.
Structure : docs/fiches_salaire/2025/ et docs/fiches_salaire/2026/
Nommage : NOM_Prenom_YYYY_M_V1.pdf (compatible avec la détection auto)
"""
import os
import sys
import json
import random
import subprocess

# Get users from DB via PHP
result = subprocess.run(
    ['php', '-r', '''
    require_once "init.php";
    $users = Db::fetchAll(
        "SELECT u.id, u.prenom, u.nom, u.email, f.nom AS fonction_nom, f.code AS fonction_code
         FROM users u
         LEFT JOIN fonctions f ON f.id = u.fonction_id
         WHERE u.is_active = 1 AND u.prenom != 'Admin'
         ORDER BY u.nom, u.prenom"
    );
    echo json_encode($users);
    '''],
    capture_output=True, text=True,
    cwd='/home/clients/c81789f8de36e992da19fb6856aa48f6/sites/zkriva.com/spocspace'
)

users = json.loads(result.stdout)
print(f"Found {len(users)} employees")

# ── fpdf2 ──
from fpdf import FPDF

BASE_DIR = '/home/clients/c81789f8de36e992da19fb6856aa48f6/sites/zkriva.com/spocspace/docs/fiches_salaire'
EMS_NAME = 'E.M.S. La Terrassière SA'
EMS_ADDR = 'Route de Chêne 95\n1224 Chêne-Bougeries\nGenève, Suisse'

# Swiss salary ranges by function
SALARY_RANGES = {
    'IDE': (5800, 7200),
    'ASSC': (4800, 5800),
    'ASA': (4200, 5000),
    'INF': (5800, 7200),
    'RESP': (6500, 8500),
    'DIR': (8000, 11000),
    'ADM': (4500, 5800),
    'CUI': (4200, 5200),
    'ANI': (4000, 5000),
    'LOG': (4000, 4800),
    'default': (4500, 5500),
}

MOIS_NOMS = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

AVS_RATE = 0.05275  # AVS/AI/APG employee
AC_RATE = 0.011     # Assurance chômage
LPP_RATE = 0.075    # 2ème pilier (approx)
AANP_RATE = 0.007   # Accident non prof

def generate_payslip(user, year, month):
    """Generate one payslip PDF, return the file path."""
    nom = user['nom'].upper()
    prenom = user['prenom']
    fonction = user['fonction_nom'] or 'Collaborateur'
    code = user['fonction_code'] or 'default'

    lo, hi = SALARY_RANGES.get(code, SALARY_RANGES['default'])
    # Consistent salary per user (seed on user id)
    random.seed(hash(user['id'] + 'salary'))
    base_salary = round(random.uniform(lo, hi) / 50) * 50
    random.seed()  # reset

    # Taux activité between 60-100%
    random.seed(hash(user['id'] + 'taux'))
    taux = random.choice([60, 70, 80, 90, 100])
    random.seed()

    brut_100 = base_salary
    brut = round(brut_100 * taux / 100, 2)

    # 13ème mois provisionné
    treizieme = round(brut / 12, 2)
    total_brut = round(brut + treizieme, 2)

    # Deductions
    avs = round(total_brut * AVS_RATE, 2)
    ac = round(total_brut * AC_RATE, 2)
    lpp = round(total_brut * LPP_RATE, 2)
    aanp = round(total_brut * AANP_RATE, 2)
    total_ded = round(avs + ac + lpp + aanp, 2)

    net = round(total_brut - total_ded, 2)

    # PDF
    pdf = FPDF()
    pdf.add_page()
    pdf.set_auto_page_break(auto=False)

    # Header bar
    pdf.set_fill_color(45, 74, 67)  # #2D4A43
    pdf.rect(0, 0, 210, 38, 'F')
    pdf.set_text_color(255, 255, 255)
    pdf.set_font('Helvetica', 'B', 18)
    pdf.set_xy(15, 8)
    pdf.cell(0, 10, EMS_NAME, ln=True)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_x(15)
    pdf.cell(0, 5, 'Route de Chene 95, 1224 Chene-Bougeries, Geneve')

    # FICHE DE SALAIRE title
    pdf.set_text_color(45, 74, 67)
    pdf.set_font('Helvetica', 'B', 14)
    pdf.set_xy(15, 48)
    pdf.cell(0, 8, f'FICHE DE SALAIRE - {MOIS_NOMS[month]} {year}')

    # Employee info box
    pdf.set_draw_color(200, 210, 200)
    pdf.set_fill_color(248, 252, 248)
    pdf.rect(15, 62, 180, 32, 'DF')
    pdf.set_text_color(80, 80, 80)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_xy(20, 65)
    pdf.cell(45, 5, 'Collaborateur :', 0, 0)
    pdf.set_font('Helvetica', 'B', 10)
    pdf.set_text_color(30, 30, 30)
    pdf.cell(0, 5, f'{prenom} {nom}', 0, 1)

    pdf.set_text_color(80, 80, 80)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_xy(20, 72)
    pdf.cell(45, 5, 'Fonction :', 0, 0)
    pdf.set_font('Helvetica', '', 10)
    pdf.set_text_color(30, 30, 30)
    pdf.cell(0, 5, fonction, 0, 1)

    pdf.set_text_color(80, 80, 80)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_xy(20, 79)
    pdf.cell(45, 5, 'Taux d\'activite :', 0, 0)
    pdf.set_font('Helvetica', '', 10)
    pdf.set_text_color(30, 30, 30)
    pdf.cell(0, 5, f'{taux}%', 0, 1)

    pdf.set_text_color(80, 80, 80)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_xy(110, 65)
    pdf.cell(30, 5, 'N. AVS :', 0, 0)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_text_color(30, 30, 30)
    random.seed(hash(user['id'] + 'avs'))
    avs_no = f"756.{random.randint(1000,9999)}.{random.randint(1000,9999)}.{random.randint(10,99)}"
    random.seed()
    pdf.cell(0, 5, avs_no, 0, 1)

    pdf.set_text_color(80, 80, 80)
    pdf.set_font('Helvetica', '', 9)
    pdf.set_xy(110, 72)
    pdf.cell(30, 5, 'Periode :', 0, 0)
    pdf.set_text_color(30, 30, 30)
    pdf.cell(0, 5, f'01.{month:02d}.{year} - {28 + (month % 3):02d}.{month:02d}.{year}', 0, 1)

    # ── Table GAINS ──
    y = 104
    pdf.set_fill_color(45, 74, 67)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font('Helvetica', 'B', 9)
    pdf.set_xy(15, y)
    pdf.cell(110, 7, '  DESIGNATION', 1, 0, 'L', True)
    pdf.cell(35, 7, 'BASE', 1, 0, 'C', True)
    pdf.cell(35, 7, 'MONTANT', 1, 1, 'C', True)

    pdf.set_text_color(30, 30, 30)
    pdf.set_font('Helvetica', '', 9)
    y += 7

    def row(label, base, amount, bold=False):
        nonlocal y
        pdf.set_xy(15, y)
        if bold:
            pdf.set_font('Helvetica', 'B', 9)
        else:
            pdf.set_font('Helvetica', '', 9)
        pdf.cell(110, 6.5, f'  {label}', 'LR', 0, 'L')
        pdf.cell(35, 6.5, base, 'LR', 0, 'R')
        pdf.cell(35, 6.5, f'{amount:>10.2f} CHF  ', 'LR', 1, 'R')
        y += 6.5

    row(f'Salaire de base ({taux}% de {brut_100:.0f})', f'{taux}%', brut)
    row('13eme salaire (provision 1/12)', '', treizieme)

    # Random primes occasionally
    random.seed(hash(user['id'] + str(year) + str(month) + 'prime'))
    if random.random() < 0.15:
        prime = round(random.choice([100, 150, 200, 250]), 2)
        row('Prime de nuit / weekend', '', prime)
        total_brut = round(total_brut + prime, 2)
        net = round(net + prime, 2)
    random.seed()

    # Separator
    pdf.set_xy(15, y)
    pdf.set_font('Helvetica', 'B', 9)
    pdf.set_fill_color(232, 245, 232)
    pdf.cell(110, 7, '  SALAIRE BRUT', 1, 0, 'L', True)
    pdf.cell(35, 7, '', 1, 0, 'C', True)
    pdf.cell(35, 7, f'{total_brut:>10.2f} CHF  ', 1, 1, 'R', True)
    y += 9

    # ── Table DEDUCTIONS ──
    pdf.set_fill_color(180, 60, 50)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font('Helvetica', 'B', 9)
    pdf.set_xy(15, y)
    pdf.cell(110, 7, '  COTISATIONS SOCIALES', 1, 0, 'L', True)
    pdf.cell(35, 7, 'TAUX', 1, 0, 'C', True)
    pdf.cell(35, 7, 'MONTANT', 1, 1, 'C', True)
    y += 7

    pdf.set_text_color(30, 30, 30)

    def row_ded(label, rate_str, amount):
        nonlocal y
        pdf.set_xy(15, y)
        pdf.set_font('Helvetica', '', 9)
        pdf.cell(110, 6.5, f'  {label}', 'LR', 0, 'L')
        pdf.cell(35, 6.5, rate_str, 'LR', 0, 'R')
        pdf.set_text_color(180, 60, 50)
        pdf.cell(35, 6.5, f'- {amount:>8.2f} CHF  ', 'LR', 1, 'R')
        pdf.set_text_color(30, 30, 30)
        y += 6.5

    row_ded('AVS / AI / APG', '5.275%', avs)
    row_ded('Assurance chomage (AC)', '1.10%', ac)
    row_ded('LPP (2eme pilier)', '~7.50%', lpp)
    row_ded('AANP (accident non prof.)', '0.70%', aanp)

    pdf.set_xy(15, y)
    pdf.set_font('Helvetica', 'B', 9)
    pdf.set_fill_color(255, 235, 235)
    pdf.cell(110, 7, '  TOTAL DEDUCTIONS', 1, 0, 'L', True)
    pdf.cell(35, 7, '', 1, 0, 'C', True)
    pdf.set_text_color(180, 60, 50)
    pdf.cell(35, 7, f'- {total_ded:>8.2f} CHF  ', 1, 1, 'R', True)
    pdf.set_text_color(30, 30, 30)
    y += 12

    # ── NET ──
    pdf.set_fill_color(45, 74, 67)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font('Helvetica', 'B', 12)
    pdf.set_xy(15, y)
    pdf.cell(145, 10, '  SALAIRE NET A PAYER', 1, 0, 'L', True)
    pdf.cell(35, 10, f'{net:>10.2f} CHF  ', 1, 1, 'R', True)
    y += 14

    # Payment info
    pdf.set_text_color(100, 100, 100)
    pdf.set_font('Helvetica', '', 8)
    pdf.set_xy(15, y)
    random.seed(hash(user['id'] + 'iban'))
    iban = f"CH{random.randint(10,99)} {random.randint(1000,9999)} {random.randint(1000,9999)} {random.randint(1000,9999)} {random.randint(1000,9999)} {random.randint(0,9)}"
    random.seed()
    pdf.cell(0, 4, f'Virement bancaire sur IBAN : {iban}', 0, 1)
    pdf.set_x(15)
    pdf.cell(0, 4, f'Date de paiement : 25.{month:02d}.{year}', 0, 1)

    # Footer
    pdf.set_xy(15, 270)
    pdf.set_font('Helvetica', 'I', 7)
    pdf.set_text_color(160, 160, 160)
    pdf.cell(0, 4, f'{EMS_NAME} - Document confidentiel - {MOIS_NOMS[month]} {year}', 0, 0, 'C')

    # Clean name for file
    clean_nom = nom.replace(' ', '').replace("'", '')
    clean_prenom = prenom.replace(' ', '').replace("'", '')
    filename = f'{clean_nom}_{clean_prenom}_{year}_{month}_V1.pdf'

    year_dir = os.path.join(BASE_DIR, str(year))
    os.makedirs(year_dir, exist_ok=True)
    filepath = os.path.join(year_dir, filename)
    pdf.output(filepath)
    return filepath


# ── Generate ──
count = 0
for year in [2025, 2026]:
    months = range(1, 13) if year == 2025 else range(1, 5)  # 2026: jan-apr
    for month in months:
        for user in users:
            generate_payslip(user, year, month)
            count += 1
        print(f"  {year}/{MOIS_NOMS[month]:>10} : {len(users)} fiches")

print(f"\nTotal : {count} fiches generees dans {BASE_DIR}/")
