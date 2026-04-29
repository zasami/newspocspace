<?php
/**
 * Spocspace Care — Helper SVG icons
 *
 * Remplace les <i class="bi bi-X"> par des SVG inline (style Lucide / Feather,
 * stroke-width 1.8). Pas de dépendance à bootstrap-icons.min.css.
 *
 *   <?= ss_icon('house') ?>
 *   <?= ss_icon('star', 'w-5 h-5 text-teal-600') ?>
 *
 * Si l'icône n'est pas dans la map, retourne un cercle de fallback.
 */
if (!function_exists('ss_icon')) {
function ss_icon(string $name, string $class = 'w-4 h-4 opacity-85 shrink-0'): string {
    $svg = match($name) {
        'house', 'home' =>
            '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'house-heart' =>
            '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M12 19c-1.5-1.5-3-2.7-3-4.5a1.8 1.8 0 013 0 1.8 1.8 0 013 0c0 1.8-1.5 3-3 4.5z"/>',
        'person', 'user' =>
            '<circle cx="12" cy="8" r="4"/><path d="M5 22a7 7 0 0114 0"/>',
        'person-circle' =>
            '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.5c.5-3 2.5-5 5-5s4.5 2 5 5"/>',
        'person-check' =>
            '<path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>',
        'person-badge' =>
            '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="2"/><path d="M9 16c.5-1.5 1.5-2 3-2s2.5.5 3 2"/><line x1="9" y1="6" x2="15" y2="6"/>',
        'person-lines-fill' =>
            '<circle cx="12" cy="7" r="3"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'person-rolodex' =>
            '<rect x="3" y="5" width="18" height="14" rx="1"/><circle cx="12" cy="12" r="3"/><path d="M9 18c.3-1.5 1.5-2.5 3-2.5s2.7 1 3 2.5"/>',
        'people', 'people-fill' =>
            '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'calendar3' =>
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'calendar-x' =>
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="14" x2="15" y2="20"/><line x1="15" y1="14" x2="9" y2="20"/>',
        'calendar-event' =>
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><circle cx="16" cy="16" r="1.5" fill="currentColor"/>',
        'calendar-week' =>
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="7" y1="15" x2="17" y2="15"/>',
        'star' =>
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'sun' =>
            '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'arrow-left-right' =>
            '<polyline points="17 11 21 7 17 3"/><line x1="21" y1="7" x2="9" y2="7"/><polyline points="7 21 3 17 7 13"/><line x1="3" y1="17" x2="15" y2="17"/>',
        'arrow-down-up' =>
            '<polyline points="7 17 12 22 17 17"/><line x1="12" y1="22" x2="12" y2="11"/><polyline points="17 7 12 2 7 7"/><line x1="12" y1="2" x2="12" y2="13"/>',
        'car-front' =>
            '<path d="M5 17h14a2 2 0 002-2v-4a2 2 0 00-.6-1.4L19 7H5l-1.4 2.6A2 2 0 003 11v4a2 2 0 002 2z"/><circle cx="7" cy="13" r="1"/><circle cx="17" cy="13" r="1"/>',
        'door-open' =>
            '<path d="M13 4h3a2 2 0 012 2v14"/><path d="M2 20h20"/><path d="M14 12v.01"/><path d="M2 20V8a2 2 0 012-2h3l4-2v18"/>',
        'chat-dots' =>
            '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><circle cx="9" cy="11" r="0.6" fill="currentColor"/><circle cx="12" cy="11" r="0.6" fill="currentColor"/><circle cx="15" cy="11" r="0.6" fill="currentColor"/>',
        'chat-square-heart' =>
            '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M12 14c-1.5-1.5-3-2.7-3-4.5a1.8 1.8 0 013 0 1.8 1.8 0 013 0c0 1.8-1.5 3-3 4.5z"/>',
        'chat-square-text' =>
            '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/>',
        'mortarboard' =>
            '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'journal-text' =>
            '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="14" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/>',
        'receipt' =>
            '<path d="M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-3-2-3 2-3-2-3 2-2-2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/>',
        'telephone' =>
            '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>',
        'megaphone' =>
            '<polygon points="3 11 11 11 16 6 16 18 11 13 3 13"/><path d="M19 8a4 4 0 010 8"/>',
        'hand-thumbs-up' =>
            '<path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3z"/><line x1="3" y1="22" x2="3" y2="11"/>',
        'file-earmark-text' =>
            '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'clipboard2-check' =>
            '<rect x="6" y="3" width="12" height="3" rx="1"/><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><polyline points="9 14 11 16 15 12"/>',
        'folder2' =>
            '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'folder2-open' =>
            '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v1H3V7z"/><path d="M2 11h20l-2 7a2 2 0 01-2 2H4a2 2 0 01-2-2v-7z"/>',
        'lightbulb' =>
            '<path d="M12 2a7 7 0 00-7 7c0 3 2 5 3 6.5V18h8v-2.5c1-1.5 3-3.5 3-6.5a7 7 0 00-7-7z"/><line x1="9" y1="22" x2="15" y2="22"/>',
        'book' =>
            '<path d="M4 4a2 2 0 012-2h10a2 2 0 012 2v18l-7-3-7 3z"/>',
        'palette' =>
            '<path d="M12 22a10 10 0 110-20 10 10 0 010 20z"/><circle cx="7" cy="11" r="1"/><circle cx="11" cy="7" r="1"/><circle cx="16" cy="9" r="1"/><circle cx="17" cy="14" r="1"/><path d="M12 22c1 0 2-1 2-2s-1-2 0-3 3-1 3-3"/>',
        'shield-check' =>
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'shield-lock' =>
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="12" r="2"/><line x1="12" y1="14" x2="12" y2="16"/>',
        'speedometer2' =>
            '<path d="M2 12a10 10 0 1120 0"/><line x1="12" y1="14" x2="16" y2="10"/><circle cx="12" cy="14" r="2"/>',
        'grid-3x3' =>
            '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/>',
        'grid-3x3-gap' =>
            '<rect x="3" y="3" width="5" height="5" rx="1"/><rect x="10" y="3" width="5" height="5" rx="1"/><rect x="17" y="3" width="4" height="5" rx="1"/><rect x="3" y="10" width="5" height="5" rx="1"/><rect x="10" y="10" width="5" height="5" rx="1"/><rect x="17" y="10" width="4" height="5" rx="1"/><rect x="3" y="17" width="5" height="4" rx="1"/><rect x="10" y="17" width="5" height="4" rx="1"/>',
        'building' =>
            '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/><path d="M9 22v-4h6v4"/>',
        'clock' =>
            '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'envelope' =>
            '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/>',
        'envelope-at' =>
            '<rect x="2" y="4" width="20" height="14" rx="2"/><path d="m22 7-10 5L2 7"/><circle cx="18" cy="18" r="3"/>',
        'envelope-paper' =>
            '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="m22 9-10 5L2 9"/><line x1="6" y1="2" x2="6" y2="6"/><line x1="18" y1="2" x2="18" y2="6"/>',
        'briefcase' =>
            '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>',
        'bullseye' =>
            '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2" fill="currentColor"/>',
        'check2-square' =>
            '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>',
        'cloud-arrow-up' =>
            '<path d="M12 13v8"/><polyline points="8 17 12 13 16 17"/><path d="M20 18a4 4 0 00-2-7.5 6 6 0 00-11.6 1.6A4 4 0 005 18z"/>',
        'cpu' =>
            '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>',
        'database-down' =>
            '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.7 4 3 9 3"/><path d="M3 11v6c0 1.7 4 3 9 3"/><polyline points="14 18 17 21 20 18"/><line x1="17" y1="13" x2="17" y2="21"/>',
        'diagram-3' =>
            '<rect x="9" y="2" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="16" y="16" width="6" height="6" rx="1"/><line x1="12" y1="8" x2="12" y2="12"/><polyline points="5 16 5 12 19 12 19 16"/>',
        'gear' =>
            '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 005 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 005 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 5a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09A1.65 1.65 0 0015 5c.6.25 1.27.13 1.74-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019 9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09A1.65 1.65 0 0019 15z"/>',
        'graph-up', 'graph-up-arrow' =>
            '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/>',
        'hospital' =>
            '<path d="M3 22h18"/><path d="M7 22V11"/><path d="M17 22V11"/><rect x="7" y="11" width="10" height="11" rx="1"/><line x1="12" y1="13" x2="12" y2="17"/><line x1="10" y1="15" x2="14" y2="15"/>',
        'list-ul' =>
            '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/>',
        'rocket-takeoff' =>
            '<path d="M5 13c0-5 5-10 12-10 0 7-5 12-10 12-1 0-2 1-2 2v3l-3-3 1-2c1 0 2-1 2-2z"/><circle cx="14" cy="8" r="1.5" fill="currentColor"/>',
        'sliders' =>
            '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="2" y1="14" x2="6" y2="14"/><line x1="10" y1="8" x2="14" y2="8"/><line x1="18" y1="16" x2="22" y2="16"/>',
        'box-arrow-up-right' =>
            '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'box-arrow-right' =>
            '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'heart-pulse' =>
            '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.29 1.51 4.04 3 5.5l7 7z"/><polyline points="3 13 7 13 9 10 12 16 14 13 21 13"/>',
        'keyboard' =>
            '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M7 10h.01M11 10h.01M15 10h.01M19 10h.01M7 14h10"/>',
        'layout-sidebar-inset' =>
            '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/>',
        'power' =>
            '<path d="M18.36 6.64a9 9 0 11-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
        'chevron-down' =>
            '<polyline points="6 9 12 15 18 9"/>',
        'bell' =>
            '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
        'search' =>
            '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus' =>
            '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'heart' =>
            '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
        'cup-hot' =>
            '<path d="M3 8h14a2 2 0 012 2v3a4 4 0 01-4 4H7a4 4 0 01-4-4V8z"/><path d="M17 10h3a2 2 0 010 4h-3"/><path d="M7 4c0 1 1 1 1 2s-1 1-1 2"/><path d="M11 3c0 1 1 1 1 2s-1 1-1 2"/>',
        'calendar-check' =>
            '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/>',
        'tag' =>
            '<path d="M20 12l-8 8a2 2 0 01-2.83 0L2 12.83V4a2 2 0 012-2h8.83L20 9.17a2 2 0 010 2.83z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor"/>',
        'chat-square-quote' =>
            '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><path d="M9 10h-.5a1 1 0 100 2h.5v1a1 1 0 01-1 1"/><path d="M14 10h-.5a1 1 0 100 2h.5v1a1 1 0 01-1 1"/>',
        default =>
            '<circle cx="12" cy="12" r="3" fill="currentColor"/>',
    };
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg>';
}
}
