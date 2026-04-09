/**
 * Zasamix - Rich Editor Module
 * Lightweight rich text editor with toolbar
 */

import { EmojiPicker } from './modules/emoji_picker.js';

const PROXY = '/spocspace/assets/js/vendor/esm/proxy.php?m=';
const LIB_VERSION = '3.14.0';

// Library references (loaded once)
let EditorClass, StarterKit, TextAlign, Highlight, Placeholder, ImageExt, Underline, LinkExt;
let TableExt, TableRow, TableCell, TableHeader;
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
        const [core, starter, textAlign, highlight, placeholder, image, underline, link, table, tableRow, tableCell, tableHeader] = await Promise.all([
            import(enc(`@tiptap/core@${LIB_VERSION}`)),
            import(enc(`@tiptap/starter-kit@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-text-align@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-highlight@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-placeholder@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-image@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-underline@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-link@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-table@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-table-row@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-table-cell@${LIB_VERSION}`)),
            import(enc(`@tiptap/extension-table-header@${LIB_VERSION}`)),
        ]);

        EditorClass = core.Editor;
        StarterKit = starter.StarterKit || starter.default;
        TextAlign = textAlign.TextAlign || textAlign.default;
        Highlight = highlight.Highlight || highlight.default;
        Placeholder = placeholder.Placeholder || placeholder.default;
        ImageExt = image.Image || image.default;
        Underline = underline.Underline || underline.default;
        LinkExt = link.Link || link.default;
        TableExt = table.Table || table.default;
        TableRow = tableRow.TableRow || tableRow.default;
        TableCell = tableCell.TableCell || tableCell.default;
        TableHeader = tableHeader.TableHeader || tableHeader.default;

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
        ${btn('table', 'table', 'Tableau')}
        ${btn('tableDelRow', 'dash-lg', 'Suppr. ligne')}
        ${btn('tableDelCol', 'x', 'Suppr. colonne')}
        ${btn('tableAddRow', 'plus-lg', 'Ajouter ligne')}
        ${btn('tableAddCol', 'plus-square', 'Ajouter colonne')}
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
    // Prevent mousedown from stealing focus from editor
    toolbar.addEventListener('mousedown', (e) => {
        if (e.target.closest('[data-ed-action]')) e.preventDefault();
    });

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
                case 'table': editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); break;
                case 'tableAddRow': editor.chain().focus().addRowAfter().run(); break;
                case 'tableAddCol': editor.chain().focus().addColumnAfter().run(); break;
                case 'tableDelRow': editor.chain().focus().deleteRow().run(); break;
                case 'tableDelCol': editor.chain().focus().deleteColumn().run(); break;
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
 * Confirm modal — same style as care modals (header + body + footer)
 */
let confirmModalEl = null;

function ensureConfirmModal() {
    if (confirmModalEl) return confirmModalEl;

    confirmModalEl = document.createElement('div');
    confirmModalEl.id = 'zsEdConfirmModal';
    confirmModalEl.innerHTML = `
        <div class="zs-confirm-overlay"></div>
        <div class="zs-confirm-dialog">
            <div class="zs-confirm-header">
                <h5 class="zs-confirm-title"></h5>
                <button type="button" class="zs-confirm-close" id="zsConfirmClose"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="zs-confirm-body">
                <div class="zs-confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <p class="zs-confirm-text"></p>
            </div>
            <div class="zs-confirm-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="zsConfirmCancel">Annuler</button>
                <button type="button" class="btn btn-sm" id="zsConfirmOk">Confirmer</button>
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
            .zs-confirm-dialog { position:relative; background:#fff; border-radius:12px; width:420px; max-width:90vw; box-shadow:0 12px 40px rgba(0,0,0,.15); overflow:hidden; animation:zsLinkIn .2s ease; }
            .zs-confirm-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #f0eeea; }
            .zs-confirm-title { font-weight:600; font-size:.95rem; margin:0; }
            .zs-confirm-close { width:32px; height:32px; border-radius:50%; border:1px solid #dee2e6; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#6c757d; font-size:.85rem; transition:all .15s; }
            .zs-confirm-close:hover { background:#f0f0f0; color:#333; }
            .zs-confirm-body { padding:20px 18px; text-align:center; }
            .zs-confirm-icon { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin:0 auto 14px; }
            .zs-confirm-icon-warn { background:#FFF3E0; color:#E65100; }
            .zs-confirm-icon-danger { background:#FFEBEE; color:#dc3545; }
            .zs-confirm-text { font-size:.88rem; color:#6c757d; margin:0; }
            .zs-confirm-footer { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid #f0eeea; }
            .zs-confirm-btn-warn { background:#E65100; color:#fff; border:none; }
            .zs-confirm-btn-warn:hover { background:#BF360C; color:#fff; }
            .zs-confirm-btn-danger { background:#dc3545; color:#fff; border:none; }
            .zs-confirm-btn-danger:hover { background:#c82333; color:#fff; }
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
        const iconEl = modal.querySelector('.zs-confirm-icon');
        iconEl.className = 'zs-confirm-icon zs-confirm-icon-' + type;
        const okBtn = modal.querySelector('#zsConfirmOk');
        okBtn.textContent = okText;
        okBtn.className = `btn btn-sm zs-confirm-btn-${type}`;
        modal.classList.add('open');

        function close(val) {
            modal.classList.remove('open');
            cleanup();
            resolve(val);
        }
        function onOk() { close(true); }
        function onCancel() { close(false); }
        function onKey(e) { if (e.key === 'Escape') close(false); }
        function cleanup() {
            okBtn.removeEventListener('click', onOk);
            modal.querySelector('#zsConfirmCancel').removeEventListener('click', onCancel);
            modal.querySelector('#zsConfirmClose').removeEventListener('click', onCancel);
            modal.querySelector('.zs-confirm-overlay').removeEventListener('click', onCancel);
            document.removeEventListener('keydown', onKey);
        }

        okBtn.addEventListener('click', onOk);
        modal.querySelector('#zsConfirmCancel').addEventListener('click', onCancel);
        modal.querySelector('#zsConfirmClose').addEventListener('click', onCancel);
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
                inline: false,
                allowBase64: true,
                HTMLAttributes: { class: 'zs-ed-img' },
            }),
            LinkExt.configure({
                openOnClick: false,
                HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
            }),
            TableExt.configure({
                resizable: true,
                HTMLAttributes: { class: 'zs-ed-table' },
            }),
            TableRow,
            TableCell,
            TableHeader,
        ],
        content: content,
        editable: true,
    });

    // Inject table + image CSS once
    if (!document.getElementById('zsEdTableCSS')) {
        const style = document.createElement('style');
        style.id = 'zsEdTableCSS';
        style.textContent = `
            /* Table styling — target all tables in editor */
            .zs-ed-content table,
            .zs-ed-table { border-collapse:collapse; width:100%; margin:12px 0; border:1px solid #dee2e6; }
            .zs-ed-content table td,
            .zs-ed-content table th,
            .zs-ed-table td,
            .zs-ed-table th { border:1px solid #dee2e6; padding:8px 10px; vertical-align:top; min-width:80px; position:relative; }
            .zs-ed-content table th,
            .zs-ed-table th { background:#f8f9fa; font-weight:600; font-size:.88rem; }
            .zs-ed-content table td,
            .zs-ed-table td { font-size:.88rem; }
            .zs-ed-content .tableWrapper { overflow-x:auto; margin:12px 0; }
            .zs-ed-content table .selectedCell { background:rgba(45,74,67,.1); }
            .zs-ed-content table .column-resize-handle { position:absolute; right:-2px; top:0; bottom:-2px; width:4px; background:#2d4a43; pointer-events:auto; cursor:col-resize; }
            .zs-ed-content table p { margin:0; }
            /* Blockquote */
            .zs-ed-content blockquote { border-left:3px solid #2d4a43; padding-left:14px; margin:12px 0; color:#6c757d; font-style:italic; }
            /* Image */
            .zs-ed-img { max-width:100%; height:auto; border-radius:6px; margin:8px 0; cursor:pointer; transition:outline .15s; }
            .zs-ed-img.ProseMirror-selectednode { outline:2px solid #2d4a43; outline-offset:2px; }
            .zs-ed-content img { max-width:100%; height:auto; }
        `;
        document.head.appendChild(style);
    }

    // Image resize on click — show size controls
    contentEl.addEventListener('click', (e) => {
        const img = e.target.closest('img.zs-ed-img');
        // Remove any existing resize bar
        contentEl.querySelectorAll('.zs-img-resize-bar').forEach(b => b.remove());
        if (!img) return;

        const bar = document.createElement('div');
        bar.className = 'zs-img-resize-bar';
        bar.innerHTML = ['25','50','75','100'].map(s =>
            `<button type="button" class="zs-img-size-btn" data-size="${s}">${s}%</button>`
        ).join('');
        img.parentNode.insertBefore(bar, img.nextSibling);

        bar.querySelectorAll('.zs-img-size-btn').forEach(btn => {
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                img.style.width = btn.dataset.size + '%';
                img.setAttribute('width', btn.dataset.size + '%');
                bar.remove();
                editor.commands.focus();
            });
        });
    });

    // Inject image resize bar CSS
    if (!document.getElementById('zsEdImgResizeCSS')) {
        const s = document.createElement('style');
        s.id = 'zsEdImgResizeCSS';
        s.textContent = `
            .zs-img-resize-bar { display:flex; gap:4px; justify-content:center; margin:4px 0 8px; }
            .zs-img-size-btn { border:1px solid #dee2e6; background:#fff; border-radius:6px; padding:3px 10px; font-size:.75rem; cursor:pointer; transition:all .12s; font-weight:600; color:#6c757d; }
            .zs-img-size-btn:hover { background:#2d4a43; color:#fff; border-color:#2d4a43; }
        `;
        document.head.appendChild(s);
    }

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

/**
 * Create a read-only viewer (same extensions as editor, no toolbar, not editable)
 * Ensures tables/blockquotes/images render identically to the editor
 * @param {HTMLElement} element - The element to mount the viewer in
 * @param {string} content - HTML content to display
 * @returns {Object|null} editor instance (call destroyEditor to cleanup)
 */
export async function createViewer(element, content = '') {
    const loaded = await loadLib();
    if (!loaded) { element.innerHTML = content; return null; }

    // Inject table/image CSS (same as editor)
    if (!document.getElementById('zsEdTableCSS')) {
        const style = document.createElement('style');
        style.id = 'zsEdTableCSS';
        style.textContent = `
            .zs-ed-content table, .zs-ed-table { border-collapse:collapse; width:100%; margin:12px 0; border:1px solid #dee2e6; }
            .zs-ed-content table td, .zs-ed-content table th { border:1px solid #dee2e6; padding:8px 10px; vertical-align:top; min-width:80px; position:relative; }
            .zs-ed-content table th { background:#f8f9fa; font-weight:600; font-size:.88rem; }
            .zs-ed-content table td { font-size:.88rem; }
            .zs-ed-content .tableWrapper { overflow-x:auto; margin:12px 0; }
            .zs-ed-content table p { margin:0; }
            .zs-ed-content blockquote { border-left:3px solid #2d4a43; padding-left:14px; margin:12px 0; color:#6c757d; font-style:italic; }
            .zs-ed-content img { max-width:100%; height:auto; border-radius:6px; margin:8px 0; }
        `;
        document.head.appendChild(style);
    }

    element.classList.add('zs-ed-content');

    const viewer = new EditorClass({
        element,
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] } }),
            Underline,
            Highlight,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            ImageExt.configure({ inline: false, allowBase64: true }),
            LinkExt.configure({ openOnClick: true, HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' } }),
            TableExt.configure({ resizable: false }),
            TableRow,
            TableCell,
            TableHeader,
        ],
        content,
        editable: false,
    });

    return viewer;
}
