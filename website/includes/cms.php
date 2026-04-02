<?php
/**
 * CMS Helper — Charge les sections depuis website_sections
 */

function ws_load_sections(string $page = 'index'): array {
    $rows = Db::fetchAll(
        "SELECT * FROM website_sections WHERE page = ? AND is_visible = 1 ORDER BY sort_order ASC",
        [$page]
    );
    $sections = [];
    foreach ($rows as $r) {
        $r['content'] = json_decode($r['content'], true) ?: [];
        $sections[$r['section_key']] = $r;
    }
    return $sections;
}

function ws_get(array $sections, string $key, string $field = null) {
    $s = $sections[$key] ?? null;
    if (!$s) return null;
    if ($field === null) return $s;
    if ($field === 'content') return $s['content'];
    return $s[$field] ?? null;
}

function ws_content(array $sections, string $key, string $contentKey = null) {
    $s = $sections[$key] ?? null;
    if (!$s) return null;
    $content = $s['content'];
    if ($contentKey === null) return $content;
    return $content[$contentKey] ?? null;
}

function ws_visible(array $sections, string $key): bool {
    return isset($sections[$key]);
}

function ws_render_cards(array $cards, string $colClass = 'col-md-4', string $cardClass = 'ws-card ws-card-icon'): string {
    $html = '';
    foreach ($cards as $c) {
        $html .= '<div class="' . $colClass . '">';
        $html .= '<div class="' . $cardClass . '">';
        if (!empty($c['icon'])) {
            $html .= '<div class="ws-card-ic"><i class="bi ' . h($c['icon']) . '"></i></div>';
        }
        $html .= '<h3>' . h($c['title'] ?? '') . '</h3>';
        $html .= '<p>' . h($c['text'] ?? '') . '</p>';
        $html .= '</div></div>';
    }
    return $html;
}
