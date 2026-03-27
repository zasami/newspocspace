<?php
/**
 * Export planning hebdomadaire en Excel XML 2003
 * Reproduit le format exact du cahier des charges:
 *   - Sections: RS/RUV, POOL, Module 1..4, Nuit
 *   - Per day: 3 cols (Nom, Horaire, Etage)
 *   - Sous-groupes par fonction (INF, ASSC, AS, APP, CIV, ASE)
 *   - Couleurs par section, numéros d'étage
 */
require_once __DIR__ . '/../init.php';

if (empty($_SESSION['zt_user']) || !in_array($_SESSION['zt_user']['role'], ['admin', 'direction', 'responsable'])) {
    http_response_code(403);
    exit('Accès refusé');
}

$mois = $_GET['mois'] ?? date('Y-m');
$mode = $_GET['mode'] ?? 'week';
$weekStart = $_GET['week_start'] ?? null;

if (!preg_match('/^\d{4}-\d{2}$/', $mois)) { http_response_code(400); exit('Mois invalide'); }

[$year, $month] = explode('-', $mois);
$year = (int) $year;
$month = (int) $month;

// ── Determine days ──
$days = [];
if ($mode === 'week' && $weekStart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    $start = new DateTime($weekStart);
    for ($i = 0; $i < 7; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $days[] = $d;
    }
} else {
    // Default to current week of the month
    $first = new DateTime("$year-$month-01");
    $dow = (int) $first->format('N'); // 1=Mon
    $start = clone $first;
    $start->modify('-' . ($dow - 1) . ' days');
    for ($i = 0; $i < 7; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $days[] = $d;
    }
}

$weekNum = $days[0]->format('W');
$dateFrom = $days[0]->format('d.m');
$dateTo = $days[count($days) - 1]->format('d.m.y');

// ── Fetch data ──
$planning = Db::fetch("SELECT id, statut FROM plannings WHERE mois_annee = ?", [$mois]);
$assignations = [];
if ($planning) {
    $assignations = Db::fetchAll(
        "SELECT pa.*, ht.code AS horaire_code, ht.couleur
         FROM planning_assignations pa
         LEFT JOIN horaires_types ht ON ht.id = pa.horaire_type_id
         WHERE pa.planning_id = ?",
        [$planning['id']]
    );
}

$aIdx = [];
foreach ($assignations as $a) {
    $aIdx[$a['user_id'] . '_' . $a['date_jour']] = $a;
}

$users = Db::fetchAll(
    "SELECT u.id, u.nom, u.prenom, u.taux, u.employee_id,
            f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            GROUP_CONCAT(m.id ORDER BY um.is_principal DESC) AS module_ids,
            GROUP_CONCAT(m.code ORDER BY um.is_principal DESC) AS module_codes
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     GROUP BY u.id
     ORDER BY f.ordre, u.nom"
);

$modules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
$etages = Db::fetchAll("SELECT e.id, e.code, e.nom, e.module_id FROM etages e ORDER BY e.ordre");
$horaires = Db::fetchAll("SELECT id, code, heure_debut, heure_fin FROM horaires_types WHERE is_active = 1");

$horaireMap = [];
foreach ($horaires as $h) $horaireMap[$h['id']] = $h;

// Build etages list per module
$etagesByModule = [];
foreach ($etages as $e) {
    $etagesByModule[$e['module_id']][] = $e;
}

// Group users by principal module
$usersByModule = [];
foreach ($users as $u) {
    $modId = $u['module_ids'] ? explode(',', $u['module_ids'])[0] : '__none';
    $usersByModule[$modId][] = $u;
}

$joursFr = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// Module header colors (matching cahier des charges theme)
$moduleColors = [
    0 => '#8DB4E2', // M1 - blue
    1 => '#FAC090', // M2 - orange
    2 => '#92D050', // M3 - green
    3 => '#C4BD97', // M4 - brown/tan
];
$rsColor   = '#FFD9FF'; // RS/RUV - purple/pink
$poolColor = '#FD5D5D'; // POOL - red
$nuitColor = '#B1A0C7'; // Nuit - purple

// ── Build Excel XML ──
$filename = "Planning_hebdo_Sem_{$weekNum}_du_{$dateFrom}_au_{$dateTo}.xls";
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Helper to output XML cell
function xmlCell($val, $type = 'String', $styleId = 'Default', $mergeAcross = 0, $mergeDown = 0) {
    $attrs = " ss:StyleID=\"$styleId\"";
    if ($mergeAcross > 0) $attrs .= " ss:MergeAcross=\"$mergeAcross\"";
    if ($mergeDown > 0) $attrs .= " ss:MergeDown=\"$mergeDown\"";
    $val = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
    return "   <Cell{$attrs}><Data ss:Type=\"$type\">{$val}</Data></Cell>\n";
}

function xmlEmptyCell($styleId = 'Default') {
    return "   <Cell ss:StyleID=\"$styleId\"/>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">

<Styles>
 <Style ss:ID="Default"><Alignment ss:Vertical="Center"/><Font ss:Size="11"/></Style>

 <!-- Title -->
 <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>

 <!-- Legend -->
 <Style ss:ID="LegBold"><Font ss:Bold="1" ss:Size="10"/><Alignment ss:Vertical="Center"/></Style>
 <Style ss:ID="LegVal"><Font ss:Size="10"/><Alignment ss:Vertical="Center"/></Style>
 <Style ss:ID="LegINF"><Font ss:Bold="1" ss:Size="10"/><Interior ss:Color="#A6F2FC" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>

 <!-- Section headers (RS/RUV) -->
 <Style ss:ID="SecRS">
  <Font ss:Bold="1" ss:Size="18"/>
  <Interior ss:Color="<?= $rsColor ?>" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
 </Style>

 <!-- Section headers (POOL) -->
 <Style ss:ID="SecPOOL">
  <Font ss:Bold="1" ss:Size="18"/>
  <Interior ss:Color="<?= $poolColor ?>" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
 </Style>

 <!-- Section headers (Nuit) -->
 <Style ss:ID="SecNUIT">
  <Font ss:Bold="1" ss:Size="18"/>
  <Interior ss:Color="<?= $nuitColor ?>" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>
 </Style>

 <!-- Module headers (dynamic colors via inline) -->
<?php for ($i = 0; $i < 4; $i++): ?>
 <Style ss:ID="SecM<?= $i ?>">
  <Font ss:Bold="1" ss:Size="20"/>
  <Interior ss:Color="<?= $moduleColors[$i] ?>" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/></Borders>
 </Style>
<?php endfor; ?>

 <!-- Fonction sub-header -->
 <Style ss:ID="Fonc">
  <Font ss:Bold="1" ss:Size="18"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
 </Style>

 <!-- Day header in section header row -->
 <Style ss:ID="DayH">
  <Font ss:Bold="1" ss:Size="14"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
 </Style>

 <!-- Employee name cell (with INF highlight) -->
 <Style ss:ID="NameINF">
  <Font ss:Bold="1" ss:Size="18"/>
  <Interior ss:Color="#A6F2FC" ss:Pattern="Solid"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
 </Style>
 <Style ss:ID="Name">
  <Font ss:Bold="1" ss:Size="18"/>
  <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
 </Style>

 <!-- Horaire code cell -->
 <Style ss:ID="Shift">
  <Font ss:Bold="1" ss:Size="18"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
 </Style>

 <!-- Etage cell -->
 <Style ss:ID="Etage">
  <Font ss:Bold="1" ss:Size="18"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
 </Style>

 <!-- Employee ID merged cell -->
 <Style ss:ID="EmpId">
  <Font ss:Size="10" ss:Color="#888888"/>
  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
 </Style>

 <!-- Spacer -->
 <Style ss:ID="Spacer"><Font ss:Size="4"/></Style>
</Styles>

<Worksheet ss:Name="Planning">
<Table>
 <!-- Column widths: A=label, then per day: B/C/D, E/F/G, etc. -->
 <Column ss:Width="170"/><!-- A: Category/Fonction -->
<?php for ($d = 0; $d < 7; $d++): ?>
 <Column ss:Width="140"/><!-- Name -->
 <Column ss:Width="38"/><!-- Horaire -->
 <Column ss:Width="50"/><!-- Etage -->
<?php endfor; ?>

 <!-- Row 1: Title -->
 <Row ss:Height="30">
<?= xmlCell("Planning hebdomadaire", 'String', 'Title', 10) ?>
<?= xmlCell("du", 'String', 'Default') ?>
  <Cell ss:StyleID="Default"/>
<?= xmlCell($days[0]->format('d.m.Y'), 'String', 'Title') ?>
<?= xmlCell("au", 'String', 'Default') ?>
  <Cell ss:StyleID="Default"/>
<?= xmlCell($days[6]->format('d.m.Y'), 'String', 'Title') ?>
 </Row>

 <!-- Row 2: empty -->
 <Row ss:Height="12"><Cell/></Row>

 <!-- Row 3-5: Legend -->
 <Row ss:Height="25">
<?= xmlCell("INDICATIONS", 'String', 'LegBold') ?>
<?= xmlCell("HORAIRES", 'String', 'LegBold') ?>
  <Cell ss:StyleID="Default"/>
<?= xmlCell("INDICATIONS", 'String', 'LegBold') ?>
<?= xmlCell("HORAIRES", 'String', 'LegBold') ?>
  <Cell ss:StyleID="Default"/>
<?= xmlCell("INDICATIONS", 'String', 'LegBold') ?>
<?= xmlCell("HORAIRES", 'String', 'LegBold') ?>
 </Row>
<?php
// Output 2 legend rows with horaire definitions
$legendHoraires = array_slice($horaires, 0, 12);
$legendRows = array_chunk($legendHoraires, 3);
foreach ($legendRows as $chunk): ?>
 <Row ss:Height="25">
<?php foreach ($chunk as $lh): ?>
<?= xmlCell($lh['code'], 'String', 'LegBold') ?>
<?= xmlCell(substr($lh['heure_debut'],0,5) . ' - ' . substr($lh['heure_fin'],0,5), 'String', 'LegVal') ?>
  <Cell ss:StyleID="Default"/>
<?php endforeach; ?>
 </Row>
<?php endforeach; ?>

 <!-- Row 6: spacer -->
 <Row ss:Height="9"><Cell/></Row>

<?php
// ═══════════════════════════════════════════
// SECTIONS: RS/RUV, POOL, Modules 1-4, Nuit
// ═══════════════════════════════════════════
$sections = [];

// Find RS/RUV users (by fonction)
$rsUsers = array_filter($users, fn($u) => in_array($u['fonction_code'], ['RS', 'RUV']));
if (!empty($rsUsers)) {
    $sections[] = ['type' => 'rs', 'label' => 'RS / RUVs', 'style' => 'SecRS', 'users' => $rsUsers, 'hasEtage' => false];
}

// POOL module
$poolMod = null;
$nuitMod = null;
foreach ($modules as $m) {
    if ($m['code'] === 'POOL') $poolMod = $m;
    if ($m['code'] === 'NUIT') $nuitMod = $m;
}
if ($poolMod && !empty($usersByModule[$poolMod['id']])) {
    $sections[] = ['type' => 'pool', 'label' => 'POOL', 'style' => 'SecPOOL', 'users' => $usersByModule[$poolMod['id']], 'hasEtage' => true, 'module' => $poolMod];
}

// Regular modules (M1-M4)
$modIdx = 0;
foreach ($modules as $m) {
    if (in_array($m['code'], ['POOL', 'NUIT'])) continue;
    $mUsers = $usersByModule[$m['id']] ?? [];
    $sections[] = [
        'type' => 'module',
        'label' => $m['code'],
        'fullLabel' => $m['nom'],
        'style' => 'SecM' . min($modIdx, 3),
        'users' => $mUsers,
        'hasEtage' => true,
        'module' => $m,
        'modIdx' => $modIdx,
    ];
    $modIdx++;
}

// Nuit
if ($nuitMod && !empty($usersByModule[$nuitMod['id']])) {
    $sections[] = ['type' => 'nuit', 'label' => 'Nuit', 'style' => 'SecNUIT', 'users' => $usersByModule[$nuitMod['id']], 'hasEtage' => true, 'module' => $nuitMod];
}

// Fonction order and labels
$fonctionOrder = ['RS' => 0, 'RUV' => 1, 'INF' => 2, 'ASSC' => 3, 'AS' => 4, 'APP' => 5, 'CIV' => 6, 'ASE' => 7];
$fonctionLabels = [
    'RS' => 'Responsable des soins', 'RUV' => 'RUVs', 'INF' => 'Infirmières',
    'ASSC' => 'ASSC', 'AS' => 'AS', 'APP' => 'Apprentis / Stagiaire',
    'CIV' => 'Civiliste', 'ASE' => 'ASE / Anim'
];

// Build etage labels: for each module, etages are numbered like 1-a, 1-b, 2-a, 2-b
function getEtageLabel($moduleId, $etagesByModule) {
    $etages = $etagesByModule[$moduleId] ?? [];
    $labels = [];
    foreach ($etages as $i => $e) {
        $num = $i + 1;
        $labels[$e['id']] = ['a' => "$num-a", 'b' => "$num-b"];
    }
    return $labels;
}

foreach ($sections as $sec):
    $sectionUsers = is_array($sec['users']) ? array_values($sec['users']) : [];
    $hasEtage = $sec['hasEtage'];
    $totalCols = 1 + (7 * ($hasEtage ? 3 : 2)); // A + 7 days × (name+horaire[+etage])

    // Section header row
?>
 <Row ss:Height="38">
<?= xmlCell($sec['label'], 'String', $sec['style']) ?>
<?php for ($d = 0; $d < 7; $d++):
    $day = $days[$d];
    $dayN = (int) $day->format('N'); // 1=Mon
    $dayLabel = $joursFr[$dayN] . ' ' . $day->format('d.m');
?>
<?= xmlCell($dayLabel, 'String', $sec['style']) ?>
<?= xmlCell("Horaire", 'String', 'DayH') ?>
<?php if ($hasEtage): ?>
<?= xmlCell("Etage", 'String', 'DayH') ?>
<?php endif; ?>
<?php endfor; ?>
 </Row>

<?php
    // Group users by fonction
    $byFonction = [];
    foreach ($sectionUsers as $u) {
        $fc = $u['fonction_code'] ?: 'Autre';
        $byFonction[$fc][] = $u;
    }
    // Sort by fonctionOrder
    uksort($byFonction, function($a, $b) use ($fonctionOrder) {
        return ($fonctionOrder[$a] ?? 99) - ($fonctionOrder[$b] ?? 99);
    });

    $moduleId = $sec['module']['id'] ?? null;
    $modEtages = $moduleId ? ($etagesByModule[$moduleId] ?? []) : [];

    foreach ($byFonction as $fc => $fUsers):
        $fcLabel = $fonctionLabels[$fc] ?? $fc;

        // Determine how many rows this fonction needs (for merged A cell)
        // For AS: 2 rows per etage (a/b), for others: 1 row per user
        $isAS = ($fc === 'AS');
        $rowCount = count($fUsers);
?>
 <!-- Fonction: <?= $fc ?> -->
<?php
        // Compute rows per etage for AS
        // In the cahier: AS rows have etage labels like 1-a, 1-b
        $etageIdx = 0;
        $subLabel = 'a';

        foreach ($fUsers as $ui => $u):
            // Determine etage label
            $etageLabel = '';
            if ($hasEtage && $isAS && !empty($modEtages)) {
                $eIdx = intdiv($ui, 2);
                $sub = ($ui % 2 === 0) ? 'a' : 'b';
                if (isset($modEtages[$eIdx])) {
                    $eNum = $eIdx + 1;
                    $etageLabel = "$eNum-$sub";
                }
            }

            // First user of this fonction gets the function label in col A
            $showFonction = ($ui === 0);
            // For employees with employee_id, show it in the merged row below (like the Excel)
            $nameStyle = ($fc === 'INF') ? 'NameINF' : 'Name';
?>
 <Row ss:Height="22">
<?php if ($showFonction && $rowCount > 1): ?>
<?= xmlCell($fcLabel, 'String', 'Fonc', 0, $rowCount - 1) ?>
<?php elseif ($showFonction): ?>
<?= xmlCell($fcLabel, 'String', 'Fonc') ?>
<?php else: ?>
  <Cell/>
<?php endif; ?>
<?php
            for ($d = 0; $d < 7; $d++):
                $day = $days[$d];
                $dateStr = $day->format('Y-m-d');
                $key = $u['id'] . '_' . $dateStr;
                $a = $aIdx[$key] ?? null;

                $name = '';
                $horCode = '';

                if ($a && !empty($a['horaire_code'])) {
                    $name = $u['prenom'];
                    $horCode = $a['horaire_code'];
                }
?>
<?= xmlCell($name, 'String', $nameStyle) ?>
<?= xmlCell($horCode, 'String', 'Shift') ?>
<?php if ($hasEtage): ?>
<?= xmlCell($etageLabel, 'String', 'Etage') ?>
<?php endif; ?>
<?php endfor; ?>
 </Row>
<?php endforeach; // users ?>

<?php
        // After the users, show employee IDs row if there are any
        $empIds = [];
        foreach ($fUsers as $u) {
            if ($u['employee_id']) $empIds[] = $u['employee_id'];
        }
        if (!empty($empIds)):
?>
 <Row ss:Height="16">
<?= xmlCell(implode(' - ', $empIds), 'String', 'EmpId') ?>
<?php for ($d = 0; $d < 7; $d++): ?>
  <Cell/>
  <Cell/>
<?php if ($hasEtage): ?>
  <Cell/>
<?php endif; ?>
<?php endfor; ?>
 </Row>
<?php endif; ?>

<?php endforeach; // fonctions ?>

<?php
    // ASE/Anim fixed hours rows (like the original)
    $aseUsers = $byFonction['ASE'] ?? [];
    if (!empty($aseUsers)):
?>
 <Row ss:Height="22">
<?= xmlCell("ASE / Anim", 'String', 'Fonc') ?>
<?php for ($d = 0; $d < 7; $d++): ?>
<?= xmlCell('', 'String', 'Name') ?>
<?= xmlCell("8h - 17h", 'String', 'Shift', 1) ?>
<?php if ($hasEtage): ?>
  <Cell/>
<?php endif; ?>
<?php endfor; ?>
 </Row>
 <Row ss:Height="22">
  <Cell/>
<?php for ($d = 0; $d < 7; $d++): ?>
<?= xmlCell('', 'String', 'Name') ?>
<?= xmlCell("9h - 18h", 'String', 'Shift', 1) ?>
<?php if ($hasEtage): ?>
  <Cell/>
<?php endif; ?>
<?php endfor; ?>
 </Row>
<?php endif; ?>

 <!-- Section spacer -->
 <Row ss:Height="9"><Cell ss:StyleID="Spacer"/></Row>

<?php endforeach; // sections ?>

</Table>

<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
 <FrozenNoSplit/>
 <SplitHorizontal>7</SplitHorizontal>
 <TopRowBottomPane>7</TopRowBottomPane>
 <SplitVertical>1</SplitVertical>
 <LeftColumnRightPane>1</LeftColumnRightPane>
 <FreezePanes/>
 <FitToPage/>
 <Print>
  <PaperSizeIndex>9</PaperSizeIndex>
  <Scale>45</Scale>
  <Gridlines/>
 </Print>
 <PageSetup>
  <Layout x:Orientation="Landscape"/>
  <PageMargins x:Left="0.3" x:Right="0.3" x:Top="0.4" x:Bottom="0.4"/>
 </PageSetup>
</WorksheetOptions>
</Worksheet>
</Workbook>
