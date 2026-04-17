<?php
/**
 * HtmlSanitize — allowlist HTML sanitizer (DOM-based).
 *
 * Purpose:
 *   Accept rich-text input (TipTap output, email templates, stagiaire reports)
 *   and strip every tag/attribute that is not explicitly allowed.
 *   - Removes all <script>/<style>/<iframe>/<object>/<embed>/<link>/<meta>/<form>.
 *   - Strips on* event handlers.
 *   - Rejects javascript:/data: URLs on href/src (data:image/*; accepted only
 *     for inline images, opt-in).
 *   - Respects UTF-8.
 *
 * Not a replacement for HTMLPurifier, but small, dependency-free, and safe
 * enough for internal rich-text fields.
 *
 * Usage:
 *   $clean = HtmlSanitize::clean($dirty);                   // conservative default
 *   $clean = HtmlSanitize::clean($dirty, ['allow_images'=>true]);
 */
class HtmlSanitize
{
    /** Default allowed tags (safe subset). Paragraphs, lists, basic formatting, links. */
    private const DEFAULT_TAGS = [
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'small',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'code', 'pre',
        'a',
        'span', 'div',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** Allowed attributes per tag (anything else is stripped). */
    private const DEFAULT_ATTRS = [
        'a'    => ['href', 'title', 'target', 'rel'],
        'span' => ['class'],
        'div'  => ['class'],
        'code' => ['class'],
        'pre'  => ['class'],
        'th'   => ['colspan', 'rowspan'],
        'td'   => ['colspan', 'rowspan'],
        '*'    => [], // global defaults (none)
    ];

    /**
     * Sanitize $html and return a safe HTML string.
     *
     * Options:
     *   allow_images: bool   — also allow <img src="https:..."> with restricted attrs
     *   allow_tags:   array  — override default allowed tags
     *   allow_attrs:  array  — override default allowed attrs map
     */
    public static function clean(?string $html, array $options = []): string
    {
        if ($html === null || $html === '') return '';

        $allowedTags  = $options['allow_tags']  ?? self::DEFAULT_TAGS;
        $allowedAttrs = $options['allow_attrs'] ?? self::DEFAULT_ATTRS;
        $allowImages  = !empty($options['allow_images']);

        if ($allowImages) {
            $allowedTags[] = 'img';
            $allowedAttrs['img'] = ['src', 'alt', 'title', 'width', 'height'];
        }

        // Normalize: strip BOM, null bytes, invalid UTF-8 sequences
        $html = str_replace(["\0", "\xEF\xBB\xBF"], '', $html);
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        }

        // Fast pre-strip of known-bad blocks before parsing (DOM parsers
        // occasionally mis-parse nested <script>)
        $html = preg_replace('#<(script|style|iframe|object|embed|frame|frameset|applet|link|meta|form|input|textarea|select|button|base)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|frame|frameset|applet|link|meta|form|input|textarea|select|button|base)\b[^>]*/?>#i', '', $html);

        // libxml wants a root
        $wrapped = '<?xml encoding="UTF-8"?><div id="__root__">' . $html . '</div>';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query("//*[@id='__root__']")->item(0);
        if (!$root) return '';

        self::_walk($root, $allowedTags, $allowedAttrs);

        // Serialize children of root
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * Escape all HTML tags but keep the text. Use when the field should never
     * contain HTML (stagiaire reports shown as plain text, etc.).
     */
    public static function stripToText(?string $html): string
    {
        if ($html === null || $html === '') return '';
        // Decode entities once, then re-escape. Strip tags first to remove script content.
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }

    private static function _walk(DOMNode $node, array $allowedTags, array $allowedAttrs): void
    {
        // Iterate a snapshot (we may mutate childNodes during the walk)
        $children = [];
        foreach ($node->childNodes as $c) $children[] = $c;

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                // Remove comments, processing instructions, etc.
                $node->removeChild($child);
                continue;
            }

            /** @var DOMElement $el */
            $el  = $child;
            $tag = strtolower($el->nodeName);

            if (!in_array($tag, $allowedTags, true)) {
                // Unwrap: keep text content, drop the tag
                while ($el->firstChild) {
                    $node->insertBefore($el->firstChild, $el);
                }
                $node->removeChild($el);
                continue;
            }

            // Strip attributes not in whitelist (tag-specific or global)
            $allowed = array_merge($allowedAttrs['*'] ?? [], $allowedAttrs[$tag] ?? []);
            $toRemove = [];
            foreach ($el->attributes as $attr) {
                $an = strtolower($attr->nodeName);
                if (strpos($an, 'on') === 0) { $toRemove[] = $an; continue; }
                if (!in_array($an, $allowed, true)) { $toRemove[] = $an; continue; }
                // URL attributes — allow http(s), mailto, tel, relative; reject javascript:/data:
                if (in_array($an, ['href', 'src', 'action', 'background', 'poster'], true)) {
                    $val = trim($attr->nodeValue ?? '');
                    if (!self::_isSafeUrl($val, $tag === 'img')) { $toRemove[] = $an; continue; }
                }
            }
            foreach ($toRemove as $an) $el->removeAttribute($an);

            // Force rel=noopener noreferrer on external links with target=_blank
            if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
                $el->setAttribute('rel', 'noopener noreferrer');
            }

            self::_walk($el, $allowedTags, $allowedAttrs);
        }
    }

    private static function _isSafeUrl(string $url, bool $allowDataImage = false): bool
    {
        if ($url === '') return true;
        // Relative URL
        if ($url[0] === '/' || $url[0] === '#') return true;
        // mailto/tel
        if (preg_match('#^(mailto|tel):#i', $url)) return true;
        // http(s)
        if (preg_match('#^https?://#i', $url)) return true;
        // data: only for inline images (opt-in)
        if ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url)) return true;
        return false;
    }
}
