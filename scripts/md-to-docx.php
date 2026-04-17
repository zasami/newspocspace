<?php
/**
 * Convertisseur Markdown → DOCX (via PHPWord, API native).
 *
 * On n'utilise PAS Html::addHtml qui casse régulièrement sur du HTML non
 * strictement XML. À la place on parse le Markdown ligne à ligne et on
 * appelle addTitle/addText/addListItem/addTable directement.
 *
 * Usage : php scripts/md-to-docx.php <fichier.md> [fichier.docx]
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$argSrc = $argv[1] ?? 'docs/INSTALLATION.md';
$src = realpath(dirname(__DIR__) . '/' . $argSrc) ?: realpath($argSrc);
if (!$src || !is_file($src)) {
    fwrite(STDERR, "Fichier introuvable : $argSrc\n"); exit(1);
}
$dst = $argv[2] ?? (dirname($src) . '/' . pathinfo($src, PATHINFO_FILENAME) . '.docx');

$md    = file_get_contents($src);
$lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));

/* ─────────── PHPWord setup ─────────── */
$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);

// Titres
$phpWord->addTitleStyle(1, ['size' => 22, 'bold' => true, 'color' => '2D4A43'], ['spaceAfter' => 200, 'spaceBefore' => 200]);
$phpWord->addTitleStyle(2, ['size' => 17, 'bold' => true, 'color' => '2D4A43'], ['spaceAfter' => 160, 'spaceBefore' => 240]);
$phpWord->addTitleStyle(3, ['size' => 14, 'bold' => true, 'color' => '333333'], ['spaceAfter' => 120, 'spaceBefore' => 180]);
$phpWord->addTitleStyle(4, ['size' => 12, 'bold' => true, 'color' => '333333'], ['spaceAfter' => 80,  'spaceBefore' => 120]);
$phpWord->addTitleStyle(5, ['size' => 11, 'bold' => true], ['spaceAfter' => 60, 'spaceBefore' => 80]);

// Liste à puces
$phpWord->addNumberingStyle('ssBullet', [
    'type'   => 'hybridMultilevel',
    'levels' => [
        ['format' => 'bullet', 'text' => "\u{2022}", 'left' => 360,  'hanging' => 360, 'tabPos' => 360],
        ['format' => 'bullet', 'text' => "\u{25E6}", 'left' => 720,  'hanging' => 360, 'tabPos' => 720],
        ['format' => 'bullet', 'text' => "\u{25AA}", 'left' => 1080, 'hanging' => 360, 'tabPos' => 1080],
    ],
]);
// Liste numérotée
$phpWord->addNumberingStyle('ssOrdered', [
    'type'   => 'multilevel',
    'levels' => [
        ['format' => 'decimal', 'text' => '%1.', 'left' => 360,  'hanging' => 360, 'tabPos' => 360],
        ['format' => 'decimal', 'text' => '%2.', 'left' => 720,  'hanging' => 360, 'tabPos' => 720],
    ],
]);

// Code mono
$phpWord->addFontStyle('ssCode', ['name' => 'Courier New', 'size' => 9.5, 'color' => '333333']);
$phpWord->addParagraphStyle('ssCodeBlock', [
    'spaceBefore' => 80, 'spaceAfter' => 120,
    'indentation' => ['left' => 240],
    'shading'     => ['fill' => 'F5F3EF'],
]);
$phpWord->addParagraphStyle('ssPara', ['spaceAfter' => 100]);

$section = $phpWord->addSection([
    'marginLeft' => 1100, 'marginRight' => 1100,
    'marginTop'  => 1100, 'marginBottom' => 1100,
]);

/* ─────────── Helpers ─────────── */

/**
 * PHPWord bug workaround: addText() does NOT XML-escape <, >, &.
 * We pre-escape here so the resulting document.xml is well-formed.
 * Word renders the entities back as literal characters.
 */
function xml_text(string $s): string {
    return str_replace(
        ['&', '<', '>'],
        ['&amp;', '&lt;', '&gt;'],
        $s
    );
}

/**
 * Ajoute du texte inline (gras/italique/code/liens) dans un conteneur
 * (paragraphe ou cell). Parse les marqueurs Markdown dans l'ordre :
 *   `code`   → police Courier
 *   **bold** → gras
 *   *italic* → italique
 *   [txt](url) → lien hypertexte
 */
function add_inline($container, string $text, array $baseFont = []): void {
    $tokens = tokenize_inline($text);
    foreach ($tokens as $tok) {
        [$type, $content, $meta] = $tok;
        $style = $baseFont;
        if ($type === 'code') {
            $style = array_merge($style, ['name' => 'Courier New', 'size' => 9.5, 'color' => '444444']);
        }
        if ($type === 'bold' || (isset($meta['bold']) && $meta['bold'])) {
            $style['bold'] = true;
        }
        if ($type === 'italic' || (isset($meta['italic']) && $meta['italic'])) {
            $style['italic'] = true;
        }
        if ($type === 'link') {
            $container->addLink($meta['url'], xml_text($content), ['color' => '1565C0', 'underline' => 'single']);
            continue;
        }
        $container->addText(xml_text($content), $style);
    }
}

/** Tokenize simple: returns array of [type, content, meta]. */
function tokenize_inline(string $s): array {
    $out = [];
    $len = mb_strlen($s, 'UTF-8');
    $i = 0;
    $buf = '';

    $flush = function () use (&$buf, &$out) {
        if ($buf !== '') { $out[] = ['text', $buf, []]; $buf = ''; }
    };

    while ($i < $len) {
        $ch = mb_substr($s, $i, 1, 'UTF-8');
        $rest = mb_substr($s, $i, null, 'UTF-8');

        // Code `...`
        if ($ch === '`') {
            $flush();
            $end = mb_strpos($rest, '`', 1, 'UTF-8');
            if ($end !== false) {
                $out[] = ['code', mb_substr($rest, 1, $end - 1, 'UTF-8'), []];
                $i += $end + 1;
                continue;
            }
        }

        // Bold **...**
        if (mb_substr($rest, 0, 2, 'UTF-8') === '**') {
            $flush();
            $end = mb_strpos($rest, '**', 2, 'UTF-8');
            if ($end !== false) {
                $out[] = ['bold', mb_substr($rest, 2, $end - 2, 'UTF-8'), []];
                $i += $end + 2;
                continue;
            }
        }

        // Italic *...* (pas ** déjà traité)
        if ($ch === '*') {
            $flush();
            $end = mb_strpos($rest, '*', 1, 'UTF-8');
            if ($end !== false && $end > 1) {
                $out[] = ['italic', mb_substr($rest, 1, $end - 1, 'UTF-8'), []];
                $i += $end + 1;
                continue;
            }
        }

        // Link [txt](url)
        if ($ch === '[') {
            if (preg_match('/^\[([^\]]+)\]\(([^)]+)\)/u', $rest, $m)) {
                $flush();
                $out[] = ['link', $m[1], ['url' => $m[2]]];
                $i += mb_strlen($m[0], 'UTF-8');
                continue;
            }
        }

        $buf .= $ch;
        $i++;
    }
    $flush();
    return $out;
}

/* ─────────── Cover ─────────── */

$section->addText(
    "SpocSpace",
    ['size' => 32, 'bold' => true, 'color' => '2D4A43'],
    ['alignment' => 'center', 'spaceAfter' => 100]
);
$section->addText(
    "Guide d'installation client",
    ['size' => 18, 'color' => '555555'],
    ['alignment' => 'center', 'spaceAfter' => 300]
);
$section->addText(
    "Version " . date('Y-m-d'),
    ['size' => 10, 'italic' => true, 'color' => '999999'],
    ['alignment' => 'center', 'spaceAfter' => 600]
);

/* ─────────── Parsing Markdown ligne par ligne ─────────── */
$n = count($lines);
$i = 0;

while ($i < $n) {
    $line = $lines[$i];

    // Blank
    if (trim($line) === '') { $i++; continue; }

    // HR
    if (preg_match('/^\s*---\s*$/', $line)) {
        $section->addText(str_repeat("\u{2500}", 40), ['color' => 'CCCCCC'], ['alignment' => 'center', 'spaceAfter' => 200, 'spaceBefore' => 100]);
        $i++;
        continue;
    }

    // Fence ```
    if (preg_match('/^```/', $line)) {
        $i++;
        $buf = [];
        while ($i < $n && !preg_match('/^```/', $lines[$i])) { $buf[] = $lines[$i]; $i++; }
        if ($i < $n) $i++;
        foreach ($buf as $cl) {
            $p = $section->addTextRun('ssCodeBlock');
            $p->addText(xml_text($cl !== '' ? $cl : ' '), 'ssCode');
        }
        continue;
    }

    // Titres # ######
    if (preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
        $lvl = min(5, strlen($m[1]));
        $section->addTitle(xml_text($m[2]), $lvl);
        $i++;
        continue;
    }

    // Table GFM
    if (strpos($line, '|') !== false && isset($lines[$i + 1])
        && preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?\s*$/', $lines[$i + 1])) {

        $splitRow = function ($row) {
            $row = trim($row);
            if ($row !== '' && $row[0] === '|') $row = substr($row, 1);
            if (str_ends_with($row, '|')) $row = substr($row, 0, -1);
            return array_map('trim', explode('|', $row));
        };
        $header = $splitRow($line);
        $i += 2;
        $rows = [];
        while ($i < $n && strpos($lines[$i], '|') !== false && trim($lines[$i]) !== '') {
            $rows[] = $splitRow($lines[$i]);
            $i++;
        }

        $tableStyle = [
            'borderColor' => 'C9C4BB',
            'borderSize'  => 4,
            'cellMargin'  => 80,
        ];
        $phpWord->addTableStyle('ssTbl_' . uniqid(), $tableStyle);
        $table = $section->addTable($tableStyle);

        $table->addRow(400);
        foreach ($header as $h) {
            $cell = $table->addCell(null, ['bgColor' => '2D4A43']);
            $run = $cell->addTextRun();
            add_inline($run, $h, ['bold' => true, 'color' => 'FFFFFF']);
        }
        foreach ($rows as $r) {
            $table->addRow();
            $cols = count($header);
            for ($c = 0; $c < $cols; $c++) {
                $cell = $table->addCell();
                $run = $cell->addTextRun();
                add_inline($run, $r[$c] ?? '');
            }
        }
        $section->addTextBreak(1);
        continue;
    }

    // Liste (unordered / ordered / checkbox)
    if (preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $line, $m)) {
        $isOrdered = preg_match('/\d+\./', $m[2]);
        $listName = $isOrdered ? 'ssOrdered' : 'ssBullet';

        while ($i < $n && preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $lines[$i], $m2)) {
            $indent = strlen($m2[1]);
            $depth = min(2, intdiv($indent, 2));
            $itemText = $m2[3];

            // [x] ou [ ] checkbox → préfixe symbole
            if (preg_match('/^\[( |x|X)\]\s+(.*)$/u', $itemText, $cb)) {
                $mark = ($cb[1] === 'x' || $cb[1] === 'X') ? "\u{2611} " : "\u{2610} ";
                $itemText = $mark . $cb[2];
            }

            $p = $section->addListItemRun($depth, $listName);
            add_inline($p, $itemText);
            $i++;
        }
        continue;
    }

    // Paragraphe : agrège jusqu'à une ligne spéciale
    $buf = [trim($line)];
    $i++;
    while ($i < $n && trim($lines[$i]) !== ''
        && !preg_match('/^(#{1,6}\s|\s*---\s*$|```|\s*\|)/', $lines[$i])
        && !preg_match('/^(\s*)([-*+]|\d+\.)\s+/', $lines[$i])) {
        $buf[] = trim($lines[$i]);
        $i++;
    }
    $p = $section->addTextRun('ssPara');
    add_inline($p, implode(' ', $buf));
}

/* ─────────── Save ─────────── */
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($dst);

echo "✓ DOCX généré : $dst\n";
echo "  Taille : " . number_format(filesize($dst) / 1024, 1) . " Ko\n";
