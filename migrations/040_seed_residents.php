<?php
/**
 * Seed 50 residents with correspondent info + access codes
 * Code d'accès = nom_resident + chambre (lowercase, no spaces)
 * Mot de passe = date de naissance format DDMMYYYY
 */
require_once __DIR__ . '/../init.php';

$residents = [
    ['Dupont', 'Marguerite', '1935-03-12', '101', '1', 'Dupont', 'Jean-Pierre', 'jp.dupont@gmail.com', '+41 79 123 4501'],
    ['Favre', 'Jeanne', '1938-07-22', '102', '1', 'Favre', 'Michel', 'michel.favre@bluewin.ch', '+41 79 123 4502'],
    ['Rochat', 'André', '1932-11-05', '103', '1', 'Rochat', 'Sophie', 'sophie.rochat@gmail.com', '+41 79 123 4503'],
    ['Muller', 'Hélène', '1940-01-18', '104', '1', 'Muller', 'Thomas', 'thomas.muller@yahoo.fr', '+41 79 123 4504'],
    ['Blanc', 'Robert', '1936-09-30', '105', '1', 'Blanc', 'Catherine', 'catherine.blanc@gmail.com', '+41 79 123 4505'],
    ['Perrin', 'Lucienne', '1934-04-14', '106', '1', 'Perrin', 'Daniel', 'daniel.perrin@outlook.com', '+41 79 123 4506'],
    ['Martin', 'Georges', '1931-12-25', '107', '1', 'Martin', 'Isabelle', 'isabelle.martin@gmail.com', '+41 79 123 4507'],
    ['Bonvin', 'Yvette', '1939-06-08', '108', '1', 'Bonvin', 'Pascal', 'pascal.bonvin@bluewin.ch', '+41 79 123 4508'],
    ['Chevalley', 'Maurice', '1933-08-17', '109', '1', 'Chevalley', 'Anne', 'anne.chevalley@gmail.com', '+41 79 123 4509'],
    ['Reymond', 'Suzanne', '1937-02-28', '110', '1', 'Reymond', 'Luc', 'luc.reymond@yahoo.fr', '+41 79 123 4510'],
    ['Berset', 'Louis', '1930-05-20', '201', '2', 'Berset', 'Marie', 'marie.berset@gmail.com', '+41 79 123 4511'],
    ['Gauthier', 'Madeleine', '1941-10-03', '202', '2', 'Gauthier', 'Philippe', 'philippe.gauthier@bluewin.ch', '+41 79 123 4512'],
    ['Vuille', 'Henri', '1935-07-11', '203', '2', 'Vuille', 'Claudine', 'claudine.vuille@gmail.com', '+41 79 123 4513'],
    ['Neri', 'Rosa', '1938-03-26', '204', '2', 'Neri', 'Marco', 'marco.neri@outlook.com', '+41 79 123 4514'],
    ['Pittet', 'Albert', '1932-09-14', '205', '2', 'Pittet', 'Françoise', 'francoise.pittet@gmail.com', '+41 79 123 4515'],
    ['Corthay', 'Germaine', '1936-12-07', '206', '2', 'Corthay', 'Yves', 'yves.corthay@bluewin.ch', '+41 79 123 4516'],
    ['Sandoz', 'Fernand', '1934-01-31', '207', '2', 'Sandoz', 'Brigitte', 'brigitte.sandoz@gmail.com', '+41 79 123 4517'],
    ['Jolivet', 'Thérèse', '1940-08-22', '208', '2', 'Jolivet', 'Alain', 'alain.jolivet@yahoo.fr', '+41 79 123 4518'],
    ['Descloux', 'Marcel', '1931-06-15', '209', '2', 'Descloux', 'Monique', 'monique.descloux@gmail.com', '+41 79 123 4519'],
    ['Widmer', 'Berthe', '1939-11-09', '210', '2', 'Widmer', 'René', 'rene.widmer@bluewin.ch', '+41 79 123 4520'],
    ['Magnin', 'Paul', '1933-04-03', '301', '3', 'Magnin', 'Christine', 'christine.magnin@gmail.com', '+41 79 123 4521'],
    ['Demont', 'Alice', '1937-08-18', '302', '3', 'Demont', 'Gérard', 'gerard.demont@outlook.com', '+41 79 123 4522'],
    ['Clément', 'Raymond', '1935-02-10', '303', '3', 'Clément', 'Sylvie', 'sylvie.clement@gmail.com', '+41 79 123 4523'],
    ['Rossier', 'Simone', '1941-05-27', '304', '3', 'Rossier', 'Pierre', 'pierre.rossier@bluewin.ch', '+41 79 123 4524'],
    ['Baud', 'Léon', '1930-10-16', '305', '3', 'Baud', 'Véronique', 'veronique.baud@gmail.com', '+41 79 123 4525'],
    ['Moret', 'Elise', '1938-01-05', '306', '3', 'Moret', 'Didier', 'didier.moret@yahoo.fr', '+41 79 123 4526'],
    ['Chapuis', 'Edmond', '1932-07-23', '307', '3', 'Chapuis', 'Nathalie', 'nathalie.chapuis@gmail.com', '+41 79 123 4527'],
    ['Vaucher', 'Marthe', '1936-03-09', '308', '3', 'Vaucher', 'Laurent', 'laurent.vaucher@bluewin.ch', '+41 79 123 4528'],
    ['Pilloud', 'Emile', '1934-09-21', '309', '3', 'Pilloud', 'Corinne', 'corinne.pilloud@gmail.com', '+41 79 123 4529'],
    ['Derivaz', 'Renée', '1940-12-12', '310', '3', 'Derivaz', 'Eric', 'eric.derivaz@outlook.com', '+41 79 123 4530'],
    ['Ducret', 'Charles', '1931-04-07', '401', '4', 'Ducret', 'Martine', 'martine.ducret@gmail.com', '+41 79 123 4531'],
    ['Evéquoz', 'Blanche', '1939-08-30', '402', '4', 'Evéquoz', 'Claude', 'claude.evequoz@bluewin.ch', '+41 79 123 4532'],
    ['Grandjean', 'Roger', '1933-02-14', '403', '4', 'Grandjean', 'Dominique', 'dominique.grandjean@gmail.com', '+41 79 123 4533'],
    ['Huguenin', 'Gabrielle', '1937-06-19', '404', '4', 'Huguenin', 'Christophe', 'christophe.huguenin@yahoo.fr', '+41 79 123 4534'],
    ['Jaccard', 'René', '1935-10-28', '405', '4', 'Jaccard', 'Annick', 'annick.jaccard@gmail.com', '+41 79 123 4535'],
    ['Knecht', 'Odette', '1941-03-16', '406', '4', 'Knecht', 'Bruno', 'bruno.knecht@bluewin.ch', '+41 79 123 4536'],
    ['Lugon', 'Gustave', '1930-07-04', '407', '4', 'Lugon', 'Patricia', 'patricia.lugon@gmail.com', '+41 79 123 4537'],
    ['Métrailler', 'Paulette', '1938-11-21', '408', '4', 'Métrailler', 'Thierry', 'thierry.metrailler@outlook.com', '+41 79 123 4538'],
    ['Nicollier', 'Joseph', '1932-05-13', '409', '4', 'Nicollier', 'Béatrice', 'beatrice.nicollier@gmail.com', '+41 79 123 4539'],
    ['Oppliger', 'Léontine', '1936-08-06', '410', '4', 'Oppliger', 'Roland', 'roland.oppliger@bluewin.ch', '+41 79 123 4540'],
    ['Paccolat', 'Victor', '1934-12-29', '501', '5', 'Paccolat', 'Sandra', 'sandra.paccolat@gmail.com', '+41 79 123 4541'],
    ['Quartenoud', 'Yvonne', '1940-04-17', '502', '5', 'Quartenoud', 'Fabrice', 'fabrice.quartenoud@yahoo.fr', '+41 79 123 4542'],
    ['Rappaz', 'Armand', '1931-09-08', '503', '5', 'Rappaz', 'Nicole', 'nicole.rappaz@gmail.com', '+41 79 123 4543'],
    ['Savary', 'Elisabeth', '1939-01-24', '504', '5', 'Savary', 'Jacques', 'jacques.savary@bluewin.ch', '+41 79 123 4544'],
    ['Thévenaz', 'Emilienne', '1933-06-11', '505', '5', 'Thévenaz', 'Marc', 'marc.thevenaz@gmail.com', '+41 79 123 4545'],
    ['Udry', 'François', '1937-10-02', '506', '5', 'Udry', 'Chantal', 'chantal.udry@outlook.com', '+41 79 123 4546'],
    ['Vauthier', 'Cécile', '1935-03-25', '507', '5', 'Vauthier', 'Serge', 'serge.vauthier@gmail.com', '+41 79 123 4547'],
    ['Wenger', 'Alfred', '1941-07-15', '508', '5', 'Wenger', 'Josiane', 'josiane.wenger@bluewin.ch', '+41 79 123 4548'],
    ['Zufferey', 'Adrienne', '1930-11-30', '509', '5', 'Zufferey', 'Patrick', 'patrick.zufferey@gmail.com', '+41 79 123 4549'],
    ['Amacher', 'Edouard', '1938-05-07', '510', '5', 'Amacher', 'Sylviane', 'sylviane.amacher@yahoo.fr', '+41 79 123 4550'],
];

$count = 0;
foreach ($residents as $r) {
    [$nom, $prenom, $ddn, $chambre, $etage, $corrNom, $corrPrenom, $corrEmail, $corrTel] = $r;

    // Code d'accès = nom lowercase + chambre (ex: dupont101)
    $code = strtolower(preg_replace('/[^a-zA-Z]/', '', $nom)) . $chambre;

    // Check if already exists
    $existing = Db::fetch("SELECT id FROM residents WHERE nom = ? AND prenom = ?", [$nom, $prenom]);
    if ($existing) continue;

    $id = Uuid::v4();
    Db::exec(
        "INSERT INTO residents (id, nom, prenom, chambre, etage, date_naissance, correspondant_nom, correspondant_prenom, correspondant_email, correspondant_telephone, code_acces, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
        [$id, $nom, $prenom, $chambre, $etage, $ddn, $corrNom, $corrPrenom, $corrEmail, $corrTel, $code]
    );
    $count++;
}

echo "$count residents created.\n";
echo "Example: dupont101 / 12031935 (DDMMYYYY)\n";
