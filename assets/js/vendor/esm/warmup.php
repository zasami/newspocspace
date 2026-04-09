<?php
/**
 * Zasamix - ESM Cache Warmup
 * Pre-downloads all required editor modules to local cache
 *
 * Usage: php warmup.php
 */

echo "=== Zasamix Editor Cache Warmup ===\n\n";

$GLOBALS['warmup_mode'] = true;
$GLOBALS['no_http_headers'] = true;

$v = '3.14.0';

$modules = [
    // Core
    "@tiptap/core@{$v}",
    "@tiptap/pm@{$v}/state",
    "@tiptap/pm@{$v}/view",
    "@tiptap/pm@{$v}/model",
    "@tiptap/pm@{$v}/transform",
    "@tiptap/pm@{$v}/keymap",
    "@tiptap/pm@{$v}/commands",
    "@tiptap/pm@{$v}/schema-list",
    "@tiptap/pm@{$v}/dropcursor",
    "@tiptap/pm@{$v}/gapcursor",
    "@tiptap/pm@{$v}/history",
    "@tiptap/core@{$v}/jsx-runtime",
    "@tiptap/starter-kit@{$v}",
    // Extensions
    "@tiptap/extension-text-align@{$v}",
    "@tiptap/extension-highlight@{$v}",
    "@tiptap/extension-placeholder@{$v}",
    "@tiptap/extension-image@{$v}",
    "@tiptap/extension-underline@{$v}",
    "@tiptap/extension-link@{$v}",
    "@tiptap/extension-table@{$v}",
    "@tiptap/extension-table-row@{$v}",
    "@tiptap/extension-table-cell@{$v}",
    "@tiptap/extension-table-header@{$v}",
    "@tiptap/pm@{$v}/tables",
    "@tiptap/extension-list@{$v}",
    "@tiptap/extensions@{$v}",
    // StarterKit sub-extensions
    "@tiptap/extension-blockquote@{$v}",
    "@tiptap/extension-bold@{$v}",
    "@tiptap/extension-bullet-list@{$v}",
    "@tiptap/extension-code@{$v}",
    "@tiptap/extension-code-block@{$v}",
    "@tiptap/extension-document@{$v}",
    "@tiptap/extension-dropcursor@{$v}",
    "@tiptap/extension-gapcursor@{$v}",
    "@tiptap/extension-hard-break@{$v}",
    "@tiptap/extension-heading@{$v}",
    "@tiptap/extension-history@{$v}",
    "@tiptap/extension-horizontal-rule@{$v}",
    "@tiptap/extension-italic@{$v}",
    "@tiptap/extension-list-item@{$v}",
    "@tiptap/extension-ordered-list@{$v}",
    "@tiptap/extension-paragraph@{$v}",
    "@tiptap/extension-strike@{$v}",
    "@tiptap/extension-text@{$v}",
    // ProseMirror
    "prosemirror-state@1.4.4",
    "prosemirror-view@1.38.1",
    "prosemirror-model@1.25.0",
    "prosemirror-transform@1.10.4",
    "prosemirror-commands@1.7.1",
    "prosemirror-keymap@1.2.3",
    "prosemirror-schema-list@1.5.1",
    "prosemirror-history@1.4.1",
    "prosemirror-dropcursor@1.8.2",
    "prosemirror-gapcursor@1.4.0",
    "prosemirror-inputrules@1.5.0",
    "prosemirror-tables@1.6.4",
    "prosemirror-tables@1.8.3",
    // Utilities
    "linkifyjs@4.3.2",
    "w3c-keyname@2.2.8",
    "rope-sequence@1.3.4",
    "orderedmap@2.1.1",
    "crelt@1.0.6",
];

$total = count($modules);
$ok = 0;
$fail = 0;

foreach ($modules as $i => $mod) {
    $num = $i + 1;
    echo "[{$num}/{$total}] {$mod} ... ";

    $_GET['m'] = $mod;
    ob_start();
    include __DIR__ . '/proxy.php';
    $output = ob_get_clean();

    if (strpos($output, '// ERROR') === 0 || strpos($output, '// Failed') === 0) {
        echo "FAIL\n";
        $fail++;
    } else {
        echo "OK (" . strlen($output) . " bytes)\n";
        $ok++;
    }
}

echo "\n=== Done: {$ok} OK, {$fail} FAIL ===\n";
if ($fail > 0) {
    echo "Re-run to retry failed modules (dependencies resolve on subsequent runs).\n";
}
