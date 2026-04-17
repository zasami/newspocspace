<?php
/**
 * Admin API — Templates d'email automatiques (CMS)
 */
require_once __DIR__ . '/../../core/EmailTemplate.php';

function admin_list_email_templates()
{
    require_responsable();

    $defs = EmailTemplate::getDefinitions();
    $saved = Db::fetchAll("SELECT template_key, is_active, updated_at FROM email_templates");
    $savedMap = [];
    foreach ($saved as $s) $savedMap[$s['template_key']] = $s;

    $list = [];
    foreach ($defs as $key => $def) {
        $row = $savedMap[$key] ?? null;
        $list[] = [
            'key' => $key,
            'name' => $def['name'],
            'description' => $def['description'],
            'category' => $def['category'],
            'variables' => $def['variables'],
            'customized' => (bool) $row,
            'is_active' => $row ? (int) $row['is_active'] : 1,
            'updated_at' => $row ? $row['updated_at'] : null,
        ];
    }

    respond(['success' => true, 'templates' => $list]);
}

function admin_get_email_template()
{
    require_responsable();
    global $params;
    $key = $params['key'] ?? '';
    if (!$key) bad_request('key requis');

    $defs = EmailTemplate::getDefinitions();
    if (!isset($defs[$key])) not_found('Template inconnu');

    $tpl = EmailTemplate::getTemplate($key);
    $tpl['definition'] = $defs[$key];
    // Include defaults for reset
    $tpl['defaults'] = $defs[$key]['defaults'];

    respond(['success' => true, 'template' => $tpl]);
}

function admin_save_email_template()
{
    $user = require_responsable();
    global $params;

    $key = $params['key'] ?? '';
    if (!$key) bad_request('key requis');

    $defs = EmailTemplate::getDefinitions();
    if (!isset($defs[$key])) not_found('Template inconnu');

    $subject = Sanitize::text($params['subject'] ?? '', 255);
    $headerColor = Sanitize::text($params['header_color'] ?? '#2d4a43', 10);
    $headerTextColor = Sanitize::text($params['header_text_color'] ?? '#ffffff', 10);
    $showLogo = !empty($params['show_logo']) ? 1 : 0;
    $headerTitle = Sanitize::text($params['header_title'] ?? '', 255);
    $headerSubtitle = Sanitize::text($params['header_subtitle'] ?? '', 255);
    $footerText = $params['footer_text'] ?? '';
    $blocks = $params['blocks'] ?? [];
    $isActive = isset($params['is_active']) ? (int) $params['is_active'] : 1;

    if (!is_array($blocks)) bad_request('blocks doit être un tableau');

    // Sanitize blocks (allow HTML in content but reject scripts)
    $clean = [];
    foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        if (!in_array($type, ['paragraph', 'highlight', 'list', 'button', 'signature', 'divider', 'image'])) continue;
        $cleanBlock = ['type' => $type];
        if (isset($b['content'])) $cleanBlock['content'] = _sanitize_block_html($b['content']);
        if (isset($b['title'])) $cleanBlock['title'] = _sanitize_block_html($b['title']);
        if (isset($b['color'])) $cleanBlock['color'] = substr((string) $b['color'], 0, 10);
        if (isset($b['bg'])) $cleanBlock['bg'] = substr((string) $b['bg'], 0, 10);
        if (isset($b['label'])) $cleanBlock['label'] = _sanitize_block_html($b['label']);
        if (isset($b['url'])) $cleanBlock['url'] = substr((string) $b['url'], 0, 500);
        if (isset($b['alt'])) $cleanBlock['alt'] = substr((string) $b['alt'], 0, 255);
        if (isset($b['items']) && is_array($b['items'])) {
            $cleanBlock['items'] = array_map(fn($i) => _sanitize_block_html($i), $b['items']);
        }
        $clean[] = $cleanBlock;
    }

    $existing = Db::fetch("SELECT id FROM email_templates WHERE template_key = ?", [$key]);
    if ($existing) {
        Db::exec(
            "UPDATE email_templates SET name=?, description=?, subject=?, header_color=?, header_text_color=?, show_logo=?, header_title=?, header_subtitle=?, blocks=?, footer_text=?, is_active=?, updated_by=?
             WHERE id = ?",
            [
                $defs[$key]['name'], $defs[$key]['description'], $subject,
                $headerColor, $headerTextColor, $showLogo, $headerTitle, $headerSubtitle,
                json_encode($clean, JSON_UNESCAPED_UNICODE), $footerText, $isActive, $user['id'],
                $existing['id'],
            ]
        );
    } else {
        Db::exec(
            "INSERT INTO email_templates (id, template_key, name, description, subject, header_color, header_text_color, show_logo, header_title, header_subtitle, blocks, footer_text, is_active, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(), $key, $defs[$key]['name'], $defs[$key]['description'], $subject,
                $headerColor, $headerTextColor, $showLogo, $headerTitle, $headerSubtitle,
                json_encode($clean, JSON_UNESCAPED_UNICODE), $footerText, $isActive, $user['id'],
            ]
        );
    }

    respond(['success' => true, 'message' => 'Template enregistré']);
}

function admin_reset_email_template()
{
    require_responsable();
    global $params;
    $key = $params['key'] ?? '';
    if (!$key) bad_request('key requis');

    Db::exec("DELETE FROM email_templates WHERE template_key = ?", [$key]);
    respond(['success' => true, 'message' => 'Template réinitialisé aux valeurs par défaut']);
}

function admin_preview_email_template()
{
    require_responsable();
    global $params;
    $key = $params['key'] ?? '';
    if (!$key) bad_request('key requis');

    $defs = EmailTemplate::getDefinitions();
    if (!isset($defs[$key])) not_found('Template inconnu');

    // Build sample vars
    $samples = [
        'prenom' => 'Marie',
        'nom' => 'Dupont',
        'email' => 'marie.dupont@exemple.ch',
        'offre_titre' => 'Infirmier/ère diplômé(e) ES',
        'code_suivi' => 'TERR-2026-AB1234',
        'date' => date('d.m.Y'),
        'password' => 'Temp2026!',
        'url_login' => 'https://www.zkriva.com/spocspace/login',
        'reset_link' => 'https://www.zkriva.com/spocspace/reset/xxx',
        'expires_hours' => '24',
        'mois' => 'Avril',
        'annee' => date('Y'),
        'url_planning' => 'https://www.zkriva.com/spocspace/planning',
    ];

    // Use provided blocks if given (preview unsaved)
    if (!empty($params['blocks'])) {
        // Build a synthetic template from $params
        $synth = [
            'subject' => $params['subject'] ?? '',
            'header_color' => $params['header_color'] ?? '#2d4a43',
            'header_text_color' => $params['header_text_color'] ?? '#ffffff',
            'show_logo' => !empty($params['show_logo']) ? 1 : 0,
            'header_title' => $params['header_title'] ?? '',
            'header_subtitle' => $params['header_subtitle'] ?? '',
            'blocks' => $params['blocks'],
            'footer_text' => $params['footer_text'] ?? '',
            'is_active' => 1,
        ];
        // Temporary: inject into Db cache via reflection — simpler: render directly via helper
        $rendered = _render_preview($synth, $samples);
    } else {
        $rendered = EmailTemplate::render($key, $samples);
    }

    respond(['success' => true, 'subject' => $rendered['subject'], 'html' => $rendered['html']]);
}

function _render_preview(array $tpl, array $vars): array
{
    // Clone logic from EmailTemplate::render but using synth template
    $subject = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $tpl['subject']);
    $headerTitle = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $tpl['header_title']);
    $headerSubtitle = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $tpl['header_subtitle']);

    $vars['ems_nom'] = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'EMS La Terrassière';
    $headerTitle = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $headerTitle);
    $subject = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $subject);

    $logoUrl = '';
    if ($tpl['show_logo']) {
        $emsLogo = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_logo_url'") ?: '';
        if ($emsLogo) {
            $host = $_SERVER['HTTP_HOST'] ?? 'www.zkriva.com';
            $logoUrl = 'https://' . $host . $emsLogo;
        }
    }

    $logoHtml = $logoUrl
        ? '<td style="padding-right:14px;vertical-align:middle"><img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" style="width:52px;height:52px;border-radius:10px;background:#fff;padding:4px;object-fit:contain;display:block"></td>'
        : '';

    $blocksHtml = '';
    foreach ($tpl['blocks'] as $block) {
        $blocksHtml .= _render_preview_block($block, $vars);
    }
    $footer = preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], $tpl['footer_text']);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;max-width:620px;margin:0 auto;padding:20px;background:#f7f5f2">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:' . htmlspecialchars($tpl['header_color']) . ';border-radius:10px 10px 0 0">
        <tr><td style="padding:20px 24px">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                ' . $logoHtml . '
                <td style="vertical-align:middle;color:' . htmlspecialchars($tpl['header_text_color']) . '">
                    <h2 style="margin:0;font-size:18px;color:' . htmlspecialchars($tpl['header_text_color']) . ';font-family:Arial,sans-serif">' . $headerTitle . '</h2>
                    ' . ($headerSubtitle ? '<p style="margin:4px 0 0;font-size:13px;color:' . htmlspecialchars($tpl['header_text_color']) . ';opacity:.85;font-family:Arial,sans-serif">' . $headerSubtitle . '</p>' : '') . '
                </td>
            </tr></table>
        </td></tr>
    </table>
    <div style="background:#fff;border:1px solid #e9ecef;border-top:none;padding:24px;border-radius:0 0 10px 10px">
        ' . $blocksHtml . '
        ' . ($footer ? '<hr style="border:none;border-top:1px solid #e9ecef;margin:20px 0"><p style="font-size:11px;color:#999;margin:0">' . $footer . '</p>' : '') . '
    </div>
</body></html>';

    return ['subject' => $subject, 'html' => $html];
}

function _render_preview_block(array $block, array $vars): string
{
    $interp = fn($s) => preg_replace_callback('/\{\{(\w+)\}\}/', fn($m) => $vars[$m[1]] ?? $m[0], (string) $s);
    $type = $block['type'] ?? 'paragraph';
    switch ($type) {
        case 'paragraph': return '<p style="margin:0 0 14px">' . $interp($block['content'] ?? '') . '</p>';
        case 'highlight':
            $title = $interp($block['title'] ?? '');
            $content = $interp($block['content'] ?? '');
            return '<div style="background:' . htmlspecialchars($block['bg'] ?? '#f4f9f6') . ';border-left:3px solid ' . htmlspecialchars($block['color'] ?? '#2d4a43') . ';padding:12px 16px;border-radius:4px;margin:16px 0">' . ($title ? '<strong>' . $title . '</strong><br>' : '') . $content . '</div>';
        case 'list':
            $lis = '';
            foreach (($block['items'] ?? []) as $it) $lis .= '<li>' . $interp($it) . '</li>';
            return '<ul style="padding-left:20px;margin:0 0 14px">' . $lis . '</ul>';
        case 'button':
            return '<div style="text-align:center;margin:20px 0"><a href="' . htmlspecialchars($interp($block['url'] ?? '#')) . '" style="display:inline-block;background:' . htmlspecialchars($block['color'] ?? '#2d4a43') . ';color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">' . $interp($block['label'] ?? 'Cliquez') . '</a></div>';
        case 'signature':
            $lines = explode("\n", $interp($block['content'] ?? ''));
            return '<p style="margin:24px 0 0">' . implode('<br>', $lines) . '</p>';
        case 'divider': return '<hr style="border:none;border-top:1px solid #e9ecef;margin:20px 0">';
        case 'image':
            return '<div style="text-align:center;margin:16px 0"><img src="' . htmlspecialchars($interp($block['url'] ?? '')) . '" alt="' . htmlspecialchars($block['alt'] ?? '') . '" style="max-width:100%;border-radius:8px"></div>';
        default: return '';
    }
}

function _sanitize_block_html(string $html): string
{
    // Strip script/style tags, allow safe formatting
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*/?>#is', '', $html);
    // Remove on* event handlers
    $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);
    return $html;
}

/**
 * Send a test email to the current admin
 */
function admin_send_test_email_template()
{
    $user = require_responsable();
    global $params;
    $key = $params['key'] ?? '';
    $toEmail = Sanitize::email($params['to'] ?? $user['email']);
    if (!$key) bad_request('key requis');
    if (!$toEmail) bad_request('Email destinataire invalide');

    // Use sample data
    $samples = [
        'prenom' => $user['prenom'] ?? 'Marie',
        'nom' => $user['nom'] ?? 'Dupont',
        'email' => $toEmail,
        'offre_titre' => 'Infirmier/ère diplômé(e) ES',
        'code_suivi' => 'TEST-' . strtoupper(substr(md5(uniqid()), 0, 6)),
        'date' => date('d.m.Y'),
        'password' => 'TestPwd2026!',
        'url_login' => 'https://www.zkriva.com/spocspace/login',
        'reset_link' => 'https://www.zkriva.com/spocspace/reset/test',
        'expires_hours' => '24',
        'mois' => date('F'),
        'annee' => date('Y'),
        'url_planning' => 'https://www.zkriva.com/spocspace/planning',
    ];

    $ok = EmailTemplate::send($key, $toEmail, $samples, $user['id']);
    if ($ok) respond(['success' => true, 'message' => "Email de test envoyé à $toEmail"]);
    else bad_request("Impossible d'envoyer (config email manquante ?)");
}
