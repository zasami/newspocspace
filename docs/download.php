<?php
$file = __DIR__ . '/Espace_Famille_Guide_Complet.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="Espace_Famille_Guide_Complet.docx"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
exit;
