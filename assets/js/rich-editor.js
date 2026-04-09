/**
 * Zasamix - Rich Editor Module
 * Lightweight rich text editor with toolbar
 */

import { EmojiPicker } from './modules/emoji_picker.js';

const PROXY = '/spocspace/assets/js/vendor/esm/proxy.php?m=';
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
        ${btn('alignJustify', 'justify', 'Justify')}
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
                case 'alignJustify': editor.chain().focus().setTextAlign('justify').run(); break;
                case 'undo': editor.chain().focus().undo().run(); break;
                case 'redo': editor.chain().focus().redo().run(); break;
                case 'link': {
                    showLinkModal(editor);
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
            case 'alignJustify': active = editor.isActive({ textAlign: 'justify' }); break;
            case 'link': active = editor.isActive('link'); break;
        }

        btn.classList.toggle('active', active);
    });
}

/**
 * Link modal — replaces browser prompt()
 */
let linkModalEl = null;

function ensureLinkModal() {
    if (linkModalEl) return linkModalEl;

    linkModalEl = document.createElement('div');
    linkModalEl.id = 'zsEdLinkModal';
    linkModalEl.innerHTML = `
        <div class="zs-link-overlay"></div>
        <div class="zs-link-dialog">
            <div class="zs-link-header">
                <span class="zs-link-title"><i class="bi bi-link-45deg"></i> Insérer un lien</span>
                <button type="button" class="zs-link-close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="zs-link-body">
                <label class="zs-link-label">URL</label>
                <input type="url" class="zs-link-input" id="zsLinkUrl" placeholder="https://..." autocomplete="off">
                <label class="zs-link-label" style="margin-top:10px">Texte (optionnel)</label>
                <input type="text" class="zs-link-input" id="zsLinkText" placeholder="Texte du lien" autocomplete="off">
            </div>
            <div class="zs-link-footer">
                <button type="button" class="zs-link-btn zs-link-btn-remove" id="zsLinkRemove" style="display:none"><i class="bi bi-trash3"></i> Supprimer</button>
                <span style="flex:1"></span>
                <button type="button" class="zs-link-btn zs-link-btn-cancel" id="zsLinkCancel">Annuler</button>
                <button type="button" class="zs-link-btn zs-link-btn-ok" id="zsLinkOk"><i class="bi bi-check-lg"></i> Appliquer</button>
            </div>
        </div>`;
    document.body.appendChild(linkModalEl);

    // Inject CSS once
    if (!document.getElementById('zsEdLinkCSS')) {
        const style = document.createElement('style');
        style.id = 'zsEdLinkCSS';
        style.textContent = `
            #zsEdLinkModal { position:fixed; inset:0; z-index:10000; display:none; align-items:center; justify-content:center; }
            #zsEdLinkModal.open { display:flex; }
            .zs-link-overlay { position:absolute; inset:0; background:rgba(0,0,0,.3); }
            .zs-link-dialog { position:relative; background:#fff; border-radius:12px; width:420px; max-width:90vw; box-shadow:0 12px 40px rgba(0,0,0,.15); animation:zsLinkIn .2s ease; }
            @keyframes zsLinkIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
            .zs-link-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #f0f0f0; }
            .zs-link-title { font-weight:600; font-size:.92rem; display:flex; align-items:center; gap:6px; }
            .zs-link-close { background:none; border:none; cursor:pointer; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#999; transition:all .15s; font-size:.85rem; }
            .zs-link-close:hover { background:#f0f0f0; color:#333; }
            .zs-link-body { padding:16px 18px; }
            .zs-link-label { font-size:.78rem; font-weight:600; color:#555; display:block; margin-bottom:4px; }
            .zs-link-input { width:100%; border:1.5px solid #dee2e6; border-radius:8px; padding:8px 12px; font-size:.88rem; outline:none; transition:border-color .15s; }
            .zs-link-input:focus { border-color:#2d4a43; }
            .zs-link-footer { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #f0f0f0; }
            .zs-link-btn { border:none; border-radius:8px; padding:7px 16px; font-size:.82rem; font-weight:600; cursor:pointer; transition:all .15s; }
            .zs-link-btn-ok { background:#2d4a43; color:#fff; }
            .zs-link-btn-ok:hover { background:#1a3a33; }
            .zs-link-btn-cancel { background:#f0f0f0; color:#555; }
            .zs-link-btn-cancel:hover { background:#e5e5e5; }
            .zs-link-btn-remove { background:none; color:#dc3545; padding:7px 12px; }
            .zs-link-btn-remove:hover { background:#fef2f2; }
        `;
        document.head.appendChild(style);
    }

    return linkModalEl;
}

function showLinkModal(editor) {
    const modal = ensureLinkModal();
    const urlInput = modal.querySelector('#zsLinkUrl');
    const textInput = modal.querySelector('#zsLinkText');
    const removeBtn = modal.querySelector('#zsLinkRemove');
    const okBtn = modal.querySelector('#zsLinkOk');
    const cancelBtn = modal.querySelector('#zsLinkCancel');
    const closeBtn = modal.querySelector('.zs-link-close');
    const overlay = modal.querySelector('.zs-link-overlay');

    // Pre-fill with existing link
    const prev = editor.getAttributes('link').href || '';
    urlInput.value = prev;
    textInput.value = '';
    removeBtn.style.display = prev ? '' : 'none';

    modal.classList.add('open');
    setTimeout(() => urlInput.focus(), 50);

    function close() {
        modal.classList.remove('open');
        cleanup();
    }

    function apply() {
        const url = urlInput.value.trim();
        if (!url) {
            editor.chain().focus().unsetLink().run();
        } else {
            const text = textInput.value.trim();
            if (text && !editor.state.selection.empty) {
                editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
            } else if (text) {
                editor.chain().focus().insertContent(`<a href="${url}" target="_blank">${text}</a>`).run();
            } else {
                editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
            }
        }
        close();
    }

    function remove() {
        editor.chain().focus().unsetLink().run();
        close();
    }

    function onKey(e) {
        if (e.key === 'Escape') close();
        if (e.key === 'Enter') { e.preventDefault(); apply(); }
    }

    function cleanup() {
        okBtn.removeEventListener('click', apply);
        cancelBtn.removeEventListener('click', close);
        closeBtn.removeEventListener('click', close);
        overlay.removeEventListener('click', close);
        removeBtn.removeEventListener('click', remove);
        document.removeEventListener('keydown', onKey);
    }

    okBtn.addEventListener('click', apply);
    cancelBtn.addEventListener('click', close);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', close);
    removeBtn.addEventListener('click', remove);
    document.addEventListener('keydown', onKey);
}

/**
 * Confirm modal — replaces browser confirm()
 */
let confirmModalEl = null;

function ensureConfirmModal() {
    if (confirmModalEl) return confirmModalEl;

    confirmModalEl = document.createElement('div');
    confirmModalEl.id = 'zsEdConfirmModal';
    confirmModalEl.innerHTML = `
        <div class="zs-confirm-overlay"></div>
        <div class="zs-confirm-dialog">
            <div class="zs-confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <h6 class="zs-confirm-title"></h6>
            <p class="zs-confirm-text"></p>
            <div class="zs-confirm-footer">
                <button type="button" class="zs-link-btn zs-link-btn-cancel" id="zsConfirmCancel">Annuler</button>
                <button type="button" class="zs-link-btn" id="zsConfirmOk">Confirmer</button>
            </div>
        </div>`;
    document.body.appendChild(confirmModalEl);

    if (!document.getElementById('zsEdConfirmCSS')) {
        const style = document.createElement('style');
        style.id = 'zsEdConfirmCSS';
        style.textContent = `
            #zsEdConfirmModal { position:fixed; inset:0; z-index:10001; display:none; align-items:center; justify-content:center; }
            #zsEdConfirmModal.open { display:flex; }
            .zs-confirm-overlay { position:absolute; inset:0; background:rgba(0,0,0,.3); }
            .zs-confirm-dialog { position:relative; background:#fff; border-radius:14px; width:380px; max-width:90vw; box-shadow:0 12px 40px rgba(0,0,0,.15); text-align:center; padding:28px 24px 20px; animation:zsLinkIn .2s ease; }
            .zs-confirm-icon { width:52px; height:52px; border-radius:50%; background:#FFF3E0; color:#E65100; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin:0 auto 14px; }
            .zs-confirm-title { font-weight:700; font-size:.95rem; margin-bottom:6px; }
            .zs-confirm-text { font-size:.85rem; color:#6c757d; margin-bottom:18px; }
            .zs-confirm-footer { display:flex; justify-content:center; gap:8px; }
            .zs-confirm-danger { background:#dc3545; color:#fff; }
            .zs-confirm-danger:hover { background:#c82333; }
            .zs-confirm-warn { background:#E65100; color:#fff; }
            .zs-confirm-warn:hover { background:#BF360C; }
        `;
        document.head.appendChild(style);
    }

    return confirmModalEl;
}

export function editorConfirm({ title = 'Confirmer', text = '', okText = 'Confirmer', type = 'warn' } = {}) {
    return new Promise(resolve => {
        const modal = ensureConfirmModal();
        modal.querySelector('.zs-confirm-title').textContent = title;
        modal.querySelector('.zs-confirm-text').textContent = text;
        const okBtn = modal.querySelector('#zsConfirmOk');
        okBtn.textContent = okText;
        okBtn.className = `zs-link-btn zs-confirm-${type}`;
        modal.classList.add('open');

        function close(val) {
            modal.classList.remove('open');
            okBtn.removeEventListener('click', onOk);
            modal.querySelector('#zsConfirmCancel').removeEventListener('click', onCancel);
            modal.querySelector('.zs-confirm-overlay').removeEventListener('click', onCancel);
            document.removeEventListener('keydown', onKey);
            resolve(val);
        }
        function onOk() { close(true); }
        function onCancel() { close(false); }
        function onKey(e) { if (e.key === 'Escape') close(false); }

        okBtn.addEventListener('click', onOk);
        modal.querySelector('#zsConfirmCancel').addEventListener('click', onCancel);
        modal.querySelector('.zs-confirm-overlay').addEventListener('click', onCancel);
        document.addEventListener('keydown', onKey);
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

/**
 * Create a bare editor (no toolbar) for inline use
 * @param {HTMLElement} element - The element to mount the editor in
 * @param {Object} options
 * @param {string} options.placeholder - Placeholder text
 * @param {string} options.content - Initial HTML content
 * @returns {Object|null} editor instance
 */
export async function createBareEditor(element, options = {}) {
    const loaded = await loadLib();
    if (!loaded) return null;
    const { placeholder = '', content = '' } = options;
    return new EditorClass({
        element,
        extensions: [
            StarterKit.configure({ heading: false, codeBlock: false, blockquote: false }),
            Highlight,
            Placeholder.configure({ placeholder }),
        ],
        content,
        editable: true,
    });
}
