<?php
/**
 * Convertisseur Markdown → DOCX (via PHPWord).
 *
 * Usage : php scripts/md-to-docx.php <fichier.md> [fichier.docx]
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../vendor/autoload.php';

$argSrc = $argv[1] ?? 'docs/INSTALLATION.md';
$src = realpath(dirname(__DIR__) . '/' . $argSrc) ?: realpath($argSrc);
if (!$src || !is_file($src)) {
    fwrite(STDERR, "Fichier introuvable : $argSrc\n"); exit(1);
}
$dst = $argv[2] ?? (dirname($src) . '/' . pathinfo($src, PATHINFO_FILENAME) . '.docx');

$md = file_get_contents($src);

/**
 * Convertit un Markdown simple en un HTML minimaliste que PHPWord sait lire.
 * Couvre : titres (#..######), listes (-/1.), tables GFM, fences ```, gras
 * **...**, italique *...*, code `...`, liens [txt](url), paragraphes.
 */
function md_to_simple_html(string $md): string {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));
    $out = [];
    $i = 0;
    $n = count($lines);

    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $inlineMd = function (string $s) use ($esc): string {
        // 1. Protéger les codes inline avec des placeholders
        $codes = [];
        $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
            $token = "\x01CODE" . count($codes) . "\x01";
            $codes[] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return $token;
        }, $s);

        // 2. Échapper le reste (sauf les placeholders)
        $s = $esc($s);

        // 3. Liens [txt](url) — sur le texte échappé
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            return '<a href="' . htmlspecialchars(htmlspecialchars_decode($m[2], ENT_QUOTES), ENT_QUOTES, 'UTF-8') . '">' . $m[1] . '</a>';
        }, $s);

        // 4. Gras puis italique
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<b>$1</b>', $s);
        $s = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<i>$1</i>', $s);

        // 5. Restaurer les codes
        foreach ($codes as $idx => $html) {
            $s = str_replace("\x01CODE{$idx}\x01", $html, $s);
        }
        return $s;
    };

    while ($i < $n) {
        $line = $lines[$i];

        // Lignes vides : séparateur
        if (trim($line) === '') { $i++; continue; }

        // Fence ```
        if (preg_match('/^```/', $line)) {
            $i++; $buf = [];
            while ($i < $n && !preg_match('/^```/', $lines[$i])) { $buf[] = $lines[$i]; $i++; }
            if ($i < $n) $i++; // consommer la closing fence
            $out[] = '<pre><code>' . $esc(implode("\n", $buf)) . '</code></pre>';
            continue;
        }

        // Séparateur horizontal
        if (preg_match('/^\s*---\s*$/', $line)) { $out[] = '<hr />'; $i++; continue; }

        // Titres
        if (preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
            $lvl = strlen($m[1]);
            $out[] = "<h$lvl>" . $inlineMd($m[2]) . "</h$lvl>";
            $i++; continue;
        }

        // Table GFM : ligne header | ... | ... suivie de ligne séparateur |---|---|
        if (strpos($line, '|') !== false && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?\s*$/', $lines[$i + 1])) {
            $splitRow = function ($row) {
                $row = trim($row);
                if ($row[0] === '|') $row = substr($row, 1);
                if (str_ends_with($row, '|')) $row = substr($row, 0, -1);
                return array_map('trim', explode('|', $row));
            };
            $header = $splitRow($line);
            $i += 2; // skip header + separator
            $rows = [];
            while ($i < $n && strpos($lines[$i], '|') !== false && trim($lines[$i]) !== '') {
                $rows[] = $splitRow($lines[$i]);
                $i++;
            }
            $html = '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse">';
            $html .= '<thead><tr>';
            foreach ($header as $h) $html .= '<th>' . $inlineMd($h) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $html .= '<tr>';
                for ($c = 0, $lc = count($header); $c < $lc; $c++) {
                    $html .= '<td>' . $inlineMd($r[$c] ?? '') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $out[] = $html;
            continue;
        }

        // Listes
        if (preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $line, $m)) {
            $ordered = preg_match('/\d+\./', $m[2]);
            $tag = $ordered ? 'ol' : 'ul';
            $items = [];
            while ($i < $n && preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $lines[$i], $m2)) {
                $items[] = $inlineMd($m2[3]);
                $i++;
            }
            $out[] = "<$tag>" . implode('', array_map(fn($it) => "<li>$it</li>", $items)) . "</$tag>";
            continue;
        }

        // Paragraphe : agrège les lignes jusqu'à une vide / un bloc
        $buf = [$line]; $i++;
        while ($i < $n && trim($lines[$i]) !== ''
            && !preg_match('/^(#{1,6}\s|\s*---\s*$|```|\s*\|)/', $lines[$i])
            && !preg_match('/^(\s*)([-*+]|\d+\.)\s+/', $lines[$i])) {
            $buf[] = $lines[$i]; $i++;
        }
        $out[] = '<p>' . $inlineMd(implode(' ', array_map('trim', $buf))) . '</p>';
    }

    return implode("\n", $out);
}

$html = md_to_simple_html($md);

// Wrap HTML minimal pour PHPWord
$wrapped = '<html><body>' . $html . '</body></html>';

$phpWord = new \PhpOffice\PhpWord\PhpWord();
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);

// Styles de titres
$phpWord->addTitleStyle(1, ['size' => 18, 'bold' => true, 'color' => '2d4a43'], ['spaceAfter' => 120]);
$phpWord->addTitleStyle(2, ['size' => 15, 'bold' => true, 'color' => '2d4a43'], ['spaceAfter' => 100]);
$phpWord->addTitleStyle(3, ['size' => 13, 'bold' => true], ['spaceAfter' => 80]);
$phpWord->addTitleStyle(4, ['size' => 12, 'bold' => true], ['spaceAfter' => 60]);

$section = $phpWord->addSection([
    'marginLeft'   => 1000,
    'marginRight'  => 1000,
    'marginTop'    => 1000,
    'marginBottom' => 1000,
]);

// Cover
$section->addText(
    'SpocSpace — Guide d\'installation client',
    ['size' => 22, 'bold' => true, 'color' => '2d4a43'],
    ['alignment' => 'center', 'spaceAfter' => 200]
);
$section->addText(
    'Version ' . date('Y-m-d'),
    ['size' => 11, 'italic' => true, 'color' => '777777'],
    ['alignment' => 'center', 'spaceAfter' => 400]
);

try {
    \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);
} catch (\Throwable $e) {
    fwrite(STDERR, "Erreur conversion HTML : " . $e->getMessage() . "\n");
    // Fallback : insert as raw text
    $section->addText(strip_tags($html));
}

$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($dst);

echo "✓ DOCX généré : $dst\n";
echo "  Taille : " . number_format(filesize($dst) / 1024, 1) . " Ko\n";
