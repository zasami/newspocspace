/**
 * Zasamix - Rich Editor Module
 * Lightweight rich text editor with toolbar
 */

import { EmojiPicker } from './modules/emoji_picker.js';

const PROXY = '/zerdatime/assets/js/vendor/esm/proxy.php?m=';
const LIB_VERSION = '3.14.0';

// Library references (loaded once)
let EditorClass, StarterKit, TextAlign, Highlight, Placeholder, ImageExt, Underline, LinkExt;
let libLoaded = false;

// Track emoji picker instances for cleanup
const emojiPickers = new Map();

/**
 * Load editor library (lazy, once)
 */
async function loadLib() {
    if (libLoaded) return true;

    const enc = (m) => PROXY + encodeURIComponent(m);

    try {
        const [core, starter, textAlign, highlight, placeholder, image, underline, link] = await Promise.all([
            import(enc(`@tiptap/core@${LIB_VERSION}`)),
            import(enc(`@tiptap/starter-kit@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-text-align@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-highlight@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-placeholder@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-image@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-underline@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-link@${LIB_VERSION}`)),
        ]);

        EditorClass = core.Editor;
        StarterKit = starter.StarterKit || starter.default;
        TextAlign = textAlign.TextAlign || textAlign.default;
        Highlight = highlight.Highlight || highlight.default;
        Placeholder = placeholder.Placeholder || placeholder.default;
        ImageExt = image.Image || image.default;
        Underline = underline.Underline || underline.default;
        LinkExt = link.Link || link.default;

        libLoaded = true;
        return true;
    } catch (err) {
        console.error('[Editor] Failed to load library:', err);
        return false;
    }
}

/**
 * Build toolbar HTML
 */
function buildToolbar(mode = 'full') {
    const sep = '<span class="zs-ed-sep"></span>';

    const btn = (action, icon, title) =>
        `<button type="button" data-ed-action="${action}" title="${title}" class="zs-ed-btn"><i class="bi bi-${icon}"></i></button>`;

    if (mode === 'mini') {
        return `<div class="zs-ed-toolbar">
            ${btn('bold', 'type-bold', 'Bold')}
            ${btn('italic', 'type-italic', 'Italic')}
            ${btn('underline', 'type-underline', 'Underline')}
            ${sep}
            ${btn('bulletList', 'list-ul', 'List')}
            ${btn('link', 'link-45deg', 'Link')}
            ${sep}
            ${btn('emoji', 'emoji-smile', 'Emoji')}
        </div>`;
    }

    return `<div class="zs-ed-toolbar">
        ${btn('bold', 'type-bold', 'Bold')}
        ${btn('italic', 'type-italic', 'Italic')}
        ${btn('underline', 'type-underline', 'Underline')}
        ${btn('strikethrough', 'type-strikethrough', 'Strikethrough')}
        ${btn('highlight', 'brush', 'Highlight')}
        ${sep}
        ${btn('h2', 'type-h2', 'Heading 2')}
        ${btn('h3', 'type-h3', 'Heading 3')}
        ${sep}
        ${btn('bulletList', 'list-ul', 'Bullet list')}
        ${btn('orderedList', 'list-ol', 'Numbered list')}
        ${btn('blockquote', 'blockquote-left', 'Quote')}
        ${sep}
        ${btn('alignLeft', 'text-left', 'Align left')}
        ${btn('alignCenter', 'text-center', 'Align center')}
        ${btn('alignRight', 'text-right', 'Align right')}
        ${sep}
        ${btn('link', 'link-45deg', 'Link')}
        ${btn('image', 'image', 'Image')}
        ${sep}
        ${btn('emoji', 'emoji-smile', 'Emoji')}
        ${sep}
        ${btn('undo', 'arrow-counterclockwise', 'Undo')}
        ${btn('redo', 'arrow-clockwise', 'Redo')}
    </div>`;
}

/**
 * Attach toolbar button handlers
 */
function attachToolbar(toolbar, editor) {
    toolbar.querySelectorAll('[data-ed-action]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const action = btn.dataset.edAction;

            switch (action) {
                case 'bold': editor.chain().focus().toggleBold().run(); break;
                case 'italic': editor.chain().focus().toggleItalic().run(); break;
                case 'underline': editor.chain().focus().toggleUnderline().run(); break;
                case 'strikethrough': editor.chain().focus().toggleStrike().run(); break;
                case 'highlight': editor.chain().focus().toggleHighlight().run(); break;
                case 'h2': editor.chain().focus().toggleHeading({ level: 2 }).run(); break;
                case 'h3': editor.chain().focus().toggleHeading({ level: 3 }).run(); break;
                case 'bulletList': editor.chain().focus().toggleBulletList().run(); break;
                case 'orderedList': editor.chain().focus().toggleOrderedList().run(); break;
                case 'blockquote': editor.chain().focus().toggleBlockquote().run(); break;
                case 'alignLeft': editor.chain().focus().setTextAlign('left').run(); break;
                case 'alignCenter': editor.chain().focus().setTextAlign('center').run(); break;
                case 'alignRight': editor.chain().focus().setTextAlign('right').run(); break;
                case 'undo': editor.chain().focus().undo().run(); break;
                case 'redo': editor.chain().focus().redo().run(); break;
                case 'link': {
                    const prev = editor.getAttributes('link').href || '';
                    const url = prompt('URL:', prev);
                    if (url === null) break;
                    if (url === '') {
                        editor.chain().focus().unsetLink().run();
                    } else {
                        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                    }
                    break;
                }
                case 'image': {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/jpeg,image/png,image/webp,image/gif';
                    input.onchange = () => {
                        const file = input.files[0];
                        if (!file) return;
                        const reader = new FileReader();
                        reader.onload = () => {
                            editor.chain().focus().setImage({ src: reader.result }).run();
                        };
                        reader.readAsDataURL(file);
                    };
                    input.click();
                    break;
                }
                case 'emoji': {
                    if (!emojiPickers.has(btn)) {
                        // Add trigger class so picker's outside-click detection excludes this button
                        btn.classList.add('zkr-emoji-trigger');
                        const picker = new EmojiPicker();
                        picker.init(btn, (emoji) => {
                            editor.chain().focus().insertContent(emoji).run();
                        });
                        emojiPickers.set(btn, picker);
                        // Open immediately on first click (init listener won't fire for current event)
                        picker.toggle();
                    }
                    // Subsequent clicks: picker's own click handler manages toggle
                    break;
                }
            }
        });
    });

    // Update active states on selection change
    editor.on('selectionUpdate', () => updateActive(toolbar, editor));
    editor.on('update', () => updateActive(toolbar, editor));
}

/**
 * Update active button states
 */
function updateActive(toolbar, editor) {
    toolbar.querySelectorAll('[data-ed-action]').forEach(btn => {
        const action = btn.dataset.edAction;
        let active = false;

        switch (action) {
            case 'bold': active = editor.isActive('bold'); break;
            case 'italic': active = editor.isActive('italic'); break;
            case 'underline': active = editor.isActive('underline'); break;
            case 'strikethrough': active = editor.isActive('strike'); break;
            case 'highlight': active = editor.isActive('highlight'); break;
            case 'h2': active = editor.isActive('heading', { level: 2 }); break;
            case 'h3': active = editor.isActive('heading', { level: 3 }); break;
            case 'bulletList': active = editor.isActive('bulletList'); break;
            case 'orderedList': active = editor.isActive('orderedList'); break;
            case 'blockquote': active = editor.isActive('blockquote'); break;
            case 'alignLeft': active = editor.isActive({ textAlign: 'left' }); break;
            case 'alignCenter': active = editor.isActive({ textAlign: 'center' }); break;
            case 'alignRight': active = editor.isActive({ textAlign: 'right' }); break;
            case 'link': active = editor.isActive('link'); break;
        }

        btn.classList.toggle('active', active);
    });
}

/**
 * Create a rich text editor
 * @param {HTMLElement} container - The wrapper element
 * @param {Object} options
 * @param {string} options.placeholder - Placeholder text
 * @param {string} options.content - Initial HTML content
 * @param {string} options.mode - 'full' (default) or 'mini' toolbar
 * @returns {Object|null} editor instance
 */
export async function createEditor(container, options = {}) {
    const loaded = await loadLib();
    if (!loaded) {
        container.innerHTML = '<div class="text-danger small p-2">Editor failed to load</div>';
        return null;
    }

    const { placeholder = '', content = '', mode = 'full' } = options;

    // Build structure
    container.innerHTML = buildToolbar(mode) + '<div class="zs-ed-content"></div>';

    const toolbarEl = container.querySelector('.zs-ed-toolbar');
    const contentEl = container.querySelector('.zs-ed-content');

    const editor = new EditorClass({
        element: contentEl,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
            }),
            Underline,
            Highlight,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
            Placeholder.configure({
                placeholder: placeholder,
            }),
            ImageExt.configure({
                inline: true,
                allowBase64: true,
            }),
            LinkExt.configure({
                openOnClick: false,
                HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
            }),
        ],
        content: content,
        editable: true,
    });

    attachToolbar(toolbarEl, editor);

    return editor;
}

/**
 * Get HTML content from editor
 */
export function getHTML(editor) {
    if (!editor) return '';
    const html = editor.getHTML();
    // Return empty string if editor only has empty paragraph
    if (html === '<p></p>') return '';
    return html;
}

/**
 * Set HTML content in editor
 */
export function setContent(editor, html) {
    if (!editor) return;
    editor.commands.setContent(html || '');
}

/**
 * Destroy editor instance
 */
export function destroyEditor(editor) {
    if (!editor) return;
    // Cleanup emoji pickers associated with this editor's toolbar
    for (const [btn, picker] of emojiPickers) {
        if (editor.options.element?.closest('.zs-editor-wrap')?.contains(btn)) {
            picker.destroy();
            emojiPickers.delete(btn);
        }
    }
    editor.destroy();
}
