<?php
/**
 * Seed des menus midi + soir pour mai 2026 (31 jours = 62 menus).
 * Style EMS Genève — cuisine traditionnelle suisse/française variée.
 * Idempotent : supprime d'abord les menus existants de mai 2026.
 */
require_once __DIR__ . '/../init.php';

$admin = Db::fetch("SELECT id FROM users WHERE role IN ('admin','direction') ORDER BY created_at LIMIT 1");
if (!$admin) { fwrite(STDERR, "Aucun admin trouvé\n"); exit(1); }
$createdBy = $admin['id'];

$midi = [
    1  => ['entree' => 'Velouté de courgettes au basilic',           'plat' => 'Filet de perche meunière',                  'accompagnement' => 'Pommes vapeur et citron',         'dessert' => 'Salade de fruits frais'],
    2  => ['entree' => 'Salade de carottes râpées au persil',        'plat' => 'Émincé de veau zurichoise',                 'accompagnement' => 'Rösti maison',                    'dessert' => 'Crème caramel'],
    3  => ['entree' => 'Asperges vertes vinaigrette',                'plat' => 'Rôti de bœuf au four',                      'accompagnement' => 'Pommes dauphines, haricots verts','dessert' => 'Fraises Chantilly'],
    4  => ['entree' => 'Crème de poireaux',                          'plat' => 'Cuisses de poulet rôties au thym',          'accompagnement' => 'Riz pilaf et ratatouille',        'dessert' => 'Yaourt nature au miel'],
    5  => ['entree' => 'Salade verte aux croûtons',                  'plat' => 'Lasagnes maison à la bolognaise',           'accompagnement' => 'Salade mêlée',                    'dessert' => 'Tiramisu'],
    6  => ['entree' => 'Œuf mimosa',                                 'plat' => 'Cabillaud sauce hollandaise',               'accompagnement' => 'Pommes nature, brocolis',         'dessert' => 'Compote de pommes'],
    7  => ['entree' => 'Salade de tomates mozzarella',               'plat' => 'Escalope de veau cordon-bleu',              'accompagnement' => 'Pâtes au beurre',                 'dessert' => 'Mousse au chocolat'],
    8  => ['entree' => 'Soupe à l\'orge perlée',                     'plat' => 'Saucisse de Vienne',                        'accompagnement' => 'Purée de pommes de terre, moutarde','dessert' => 'Crème vanille'],
    9  => ['entree' => 'Carottes râpées aux raisins',                'plat' => 'Filets de poulet à la crème de morilles',   'accompagnement' => 'Nouilles au beurre',              'dessert' => 'Salade d\'oranges à la cannelle'],
    10 => ['entree' => 'Terrine de campagne et cornichons',          'plat' => 'Gigot d\'agneau aux herbes',                'accompagnement' => 'Flageolets, pommes boulangères',  'dessert' => 'Tarte aux pommes'],
    11 => ['entree' => 'Velouté Dubarry (chou-fleur)',               'plat' => 'Quenelles de brochet sauce Nantua',         'accompagnement' => 'Riz blanc',                       'dessert' => 'Yaourt aux fruits'],
    12 => ['entree' => 'Salade frisée aux lardons',                  'plat' => 'Bœuf bourguignon',                          'accompagnement' => 'Tagliatelles fraîches',           'dessert' => 'Île flottante'],
    13 => ['entree' => 'Concombre à la crème',                       'plat' => 'Truite aux amandes',                        'accompagnement' => 'Pommes vapeur, épinards',         'dessert' => 'Fromage blanc aux fruits rouges'],
    14 => ['entree' => 'Soupe de petits pois à la menthe',           'plat' => 'Rôti de porc au jus',                       'accompagnement' => 'Choucroute douce, pommes nature', 'dessert' => 'Riz au lait à la cannelle'],
    15 => ['entree' => 'Salade de betteraves rouges',                'plat' => 'Émincé de dinde au curry',                  'accompagnement' => 'Riz basmati, ananas',             'dessert' => 'Sorbet citron'],
    16 => ['entree' => 'Rillettes du Mans, cornichons',              'plat' => 'Filet mignon de porc moutarde',             'accompagnement' => 'Gratin dauphinois, courgettes',   'dessert' => 'Mille-feuille'],
    17 => ['entree' => 'Tomates farcies au thon',                    'plat' => 'Daurade à la provençale',                   'accompagnement' => 'Riz à la tomate',                 'dessert' => 'Salade de fruits'],
    18 => ['entree' => 'Velouté de potimarron',                      'plat' => 'Coq au vin',                                'accompagnement' => 'Pommes vapeur, champignons',      'dessert' => 'Crème brûlée'],
    19 => ['entree' => 'Crudités variées, sauce yaourt',             'plat' => 'Spaghetti carbonara',                       'accompagnement' => 'Salade verte',                    'dessert' => 'Tarte au citron meringuée'],
    20 => ['entree' => 'Œufs mayonnaise',                            'plat' => 'Sauté de veau Marengo',                     'accompagnement' => 'Polenta crémeuse, petits légumes','dessert' => 'Yaourt nature'],
    21 => ['entree' => 'Salade niçoise',                             'plat' => 'Filet de cabillaud à la dijonnaise',        'accompagnement' => 'Riz pilaf, haricots beurre',      'dessert' => 'Fromage blanc et miel'],
    22 => ['entree' => 'Soupe à l\'oignon gratinée',                 'plat' => 'Boudin noir aux pommes',                    'accompagnement' => 'Purée de pommes de terre',        'dessert' => 'Compote rhubarbe'],
    23 => ['entree' => 'Salade de pâtes au thon',                    'plat' => 'Rôti de dinde aux marrons',                 'accompagnement' => 'Haricots verts, pommes rissolées','dessert' => 'Mousse aux fruits rouges'],
    24 => ['entree' => 'Asperges sauce mousseline',                  'plat' => 'Tartare de bœuf préparé',                   'accompagnement' => 'Frites maison, salade verte',     'dessert' => 'Profiteroles'],
    25 => ['entree' => 'Crème de cresson',                           'plat' => 'Lapin à la moutarde',                       'accompagnement' => 'Tagliatelles fraîches',           'dessert' => 'Tarte tatin'],
    26 => ['entree' => 'Salade alsacienne (cervelas, fromage)',      'plat' => 'Choucroute garnie',                         'accompagnement' => 'Pommes nature, moutarde',         'dessert' => 'Kouglof maison'],
    27 => ['entree' => 'Salade composée méditerranéenne',            'plat' => 'Paëlla aux fruits de mer',                  'accompagnement' => 'Citron, persil',                  'dessert' => 'Flan pâtissier'],
    28 => ['entree' => 'Soupe paysanne aux légumes',                 'plat' => 'Pot-au-feu',                                'accompagnement' => 'Légumes du pot, gros sel',        'dessert' => 'Salade de fraises au sucre'],
    29 => ['entree' => 'Salade César au poulet',                     'plat' => 'Filet de saumon à l\'oseille',              'accompagnement' => 'Riz sauvage, asperges vertes',    'dessert' => 'Panna cotta vanille'],
    30 => ['entree' => 'Tarte fine à la tomate',                     'plat' => 'Carbonnade flamande',                       'accompagnement' => 'Frites belges',                   'dessert' => 'Crêpes Suzette'],
    31 => ['entree' => 'Velouté d\'asperges',                        'plat' => 'Selle d\'agneau rôtie',                     'accompagnement' => 'Flageolets, pommes Anna',         'dessert' => 'Fraisier maison'],
];

$soir = [
    1  => ['entree' => 'Bouillon de légumes',                        'plat' => 'Quiche lorraine',                           'accompagnement' => 'Salade verte',                    'dessert' => 'Compote de poires'],
    2  => ['entree' => 'Soupe vermicelle',                           'plat' => 'Croque-monsieur',                           'accompagnement' => 'Salade mêlée',                    'dessert' => 'Yaourt aux fruits'],
    3  => ['entree' => 'Velouté de carottes au cumin',               'plat' => 'Omelette aux fines herbes',                 'accompagnement' => 'Pommes sautées',                  'dessert' => 'Fromage blanc et confiture'],
    4  => ['entree' => 'Bouillon poule au pot',                      'plat' => 'Tarte flambée alsacienne',                  'accompagnement' => 'Salade verte',                    'dessert' => 'Mousse au chocolat'],
    5  => ['entree' => 'Soupe pistou',                               'plat' => 'Gratin de macaronis au jambon',             'accompagnement' => 'Salade de tomates',               'dessert' => 'Compote pommes-cannelle'],
    6  => ['entree' => 'Velouté de tomates',                         'plat' => 'Crêpes complètes (jambon-fromage-œuf)',     'accompagnement' => 'Salade verte',                    'dessert' => 'Crème vanille'],
    7  => ['entree' => 'Soupe à l\'oignon',                          'plat' => 'Tartine au fromage gratinée',               'accompagnement' => 'Salade frisée',                   'dessert' => 'Yaourt nature'],
    8  => ['entree' => 'Bouillon aux quenelles',                     'plat' => 'Hachis Parmentier',                         'accompagnement' => 'Salade verte',                    'dessert' => 'Compote rhubarbe'],
    9  => ['entree' => 'Crème de céleri',                            'plat' => 'Cannelloni aux épinards',                   'accompagnement' => 'Tomates provençales',             'dessert' => 'Île flottante'],
    10 => ['entree' => 'Soupe minestrone',                           'plat' => 'Pizza margherita maison',                   'accompagnement' => 'Salade roquette',                 'dessert' => 'Glace vanille-fraise'],
    11 => ['entree' => 'Velouté de champignons',                     'plat' => 'Cordon-bleu de volaille',                   'accompagnement' => 'Pommes vapeur, brocolis',         'dessert' => 'Salade de fruits'],
    12 => ['entree' => 'Bouillon julienne',                          'plat' => 'Soufflé au fromage',                        'accompagnement' => 'Salade verte',                    'dessert' => 'Crème caramel'],
    13 => ['entree' => 'Soupe paysanne',                             'plat' => 'Bricelets et raclette du Valais',           'accompagnement' => 'Pommes en robe, cornichons',      'dessert' => 'Yaourt aux fruits'],
    14 => ['entree' => 'Velouté brocolis',                           'plat' => 'Pâtes au pesto et tomates cerises',         'accompagnement' => 'Parmesan râpé',                   'dessert' => 'Compote de pommes'],
    15 => ['entree' => 'Crème Crécy (carottes)',                     'plat' => 'Galettes de sarrasin œuf-jambon',           'accompagnement' => 'Salade verte',                    'dessert' => 'Far breton'],
    16 => ['entree' => 'Soupe de poisson',                           'plat' => 'Filet de merlan pané',                      'accompagnement' => 'Pommes vapeur, épinards',         'dessert' => 'Fromage blanc miel'],
    17 => ['entree' => 'Bouillon vermicelles',                       'plat' => 'Pâté en croûte',                            'accompagnement' => 'Salade frisée, cornichons',       'dessert' => 'Tarte aux pommes'],
    18 => ['entree' => 'Velouté petits pois',                        'plat' => 'Quiche aux légumes',                        'accompagnement' => 'Salade composée',                 'dessert' => 'Yaourt nature'],
    19 => ['entree' => 'Soupe à l\'orge',                            'plat' => 'Saucisse aux choux vaudoise',               'accompagnement' => 'Polenta',                         'dessert' => 'Compote de coings'],
    20 => ['entree' => 'Crème Dubarry',                              'plat' => 'Croque-madame',                             'accompagnement' => 'Salade verte',                    'dessert' => 'Mousse au chocolat'],
    21 => ['entree' => 'Soupe à la tomate',                          'plat' => 'Boulettes de viande sauce tomate',          'accompagnement' => 'Spaghetti',                       'dessert' => 'Salade de fruits'],
    22 => ['entree' => 'Velouté potiron',                            'plat' => 'Tartiflette savoyarde',                     'accompagnement' => 'Salade verte',                    'dessert' => 'Yaourt aux fruits'],
    23 => ['entree' => 'Bouillon Madrilène',                         'plat' => 'Œufs cocotte aux champignons',              'accompagnement' => 'Mouillettes de pain grillé',      'dessert' => 'Crème vanille'],
    24 => ['entree' => 'Soupe paysanne légumes',                     'plat' => 'Galette de pommes de terre, salade',        'accompagnement' => 'Sauce ciboulette',                'dessert' => 'Fromage blanc fruits rouges'],
    25 => ['entree' => 'Velouté cresson',                            'plat' => 'Risotto aux asperges',                      'accompagnement' => 'Parmesan',                        'dessert' => 'Compote pommes-poires'],
    26 => ['entree' => 'Soupe de pois cassés',                       'plat' => 'Saucisson vaudois en brioche',              'accompagnement' => 'Salade pommes de terre',          'dessert' => 'Tarte aux fraises'],
    27 => ['entree' => 'Bouillon clair',                             'plat' => 'Gratin de courgettes',                      'accompagnement' => 'Riz nature',                      'dessert' => 'Yaourt aux fruits'],
    28 => ['entree' => 'Crème d\'asperges',                          'plat' => 'Tarte au thon',                             'accompagnement' => 'Salade composée',                 'dessert' => 'Mousse au citron'],
    29 => ['entree' => 'Soupe de courgettes',                        'plat' => 'Pâtes aux quatre fromages',                 'accompagnement' => 'Salade verte',                    'dessert' => 'Compote pommes-cassis'],
    30 => ['entree' => 'Velouté de fenouil',                         'plat' => 'Salade niçoise complète',                   'accompagnement' => 'Pain de campagne',                'dessert' => 'Glace pistache'],
    31 => ['entree' => 'Bouillon aux pâtes-lettres',                 'plat' => 'Croquettes de poisson, sauce tartare',      'accompagnement' => 'Riz, petits pois',                'dessert' => 'Tarte aux fraises'],
];

Db::exec("DELETE FROM menus WHERE date_jour BETWEEN '2026-05-01' AND '2026-05-31'");

$inserted = 0;
foreach ($midi as $day => $m) {
    $date = sprintf('2026-05-%02d', $day);
    Db::exec(
        "INSERT INTO menus (id, date_jour, repas, entree, plat, accompagnement, dessert, created_by) VALUES (?, ?, 'midi', ?, ?, ?, ?, ?)",
        [Uuid::v4(), $date, $m['entree'], $m['plat'], $m['accompagnement'], $m['dessert'], $createdBy]
    );
    $inserted++;
}
foreach ($soir as $day => $s) {
    $date = sprintf('2026-05-%02d', $day);
    Db::exec(
        "INSERT INTO menus (id, date_jour, repas, entree, plat, accompagnement, dessert, created_by) VALUES (?, ?, 'soir', ?, ?, ?, ?, ?)",
        [Uuid::v4(), $date, $s['entree'], $s['plat'], $s['accompagnement'], $s['dessert'], $createdBy]
    );
    $inserted++;
}

echo "Menus mai 2026 insérés : $inserted\n";
