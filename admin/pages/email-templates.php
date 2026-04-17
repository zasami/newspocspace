<?php
require_responsable();
?>
<style>
.et-layout { display: grid; grid-template-columns: 280px 1fr; gap: 20px; }
@media (max-width: 992px) { .et-layout { grid-template-columns: 1fr; } }

/* ── Sidebar list ── */
.et-list { background: #fff; border: 1px solid var(--cl-border-light, #E8E4DE); border-radius: 12px; overflow: hidden; }
.et-list-cat { background: var(--cl-bg, #F7F5F2); padding: 8px 14px; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); border-bottom: 1px solid var(--cl-border-light); }
.et-list-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; cursor: pointer;
    border-bottom: 1px solid var(--cl-border-light, #F0EDE8);
    transition: background .15s;
}
.et-list-item:last-child { border-bottom: none; }
.et-list-item:hover { background: var(--cl-bg, #F7F5F2); }
.et-list-item.active { background: #f4f9f6; border-left: 3px solid #2d4a43; padding-left: 11px; }
.et-list-item-info { flex: 1; min-width: 0; }
.et-list-item-name { font-size: .86rem; font-weight: 600; color: var(--cl-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.et-list-item-meta { font-size: .7rem; color: var(--cl-text-muted); margin-top: 2px; }
.et-custom-dot { width: 8px; height: 8px; border-radius: 50%; background: #2d4a43; flex-shrink: 0; }

/* ── Editor pane ── */
.et-editor { background: #fff; border: 1px solid var(--cl-border-light, #E8E4DE); border-radius: 12px; padding: 20px; }
.et-editor-empty { text-align: center; padding: 60px 20px; color: var(--cl-text-muted); }
.et-editor-empty i { font-size: 2.5rem; opacity: .3; display: block; margin-bottom: 10px; }

.et-section { margin-bottom: 22px; }
.et-section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-muted); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }

.et-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.et-input, .et-select {
    padding: 8px 12px; border: 1.5px solid var(--cl-border-light, #E8E4DE); border-radius: 8px;
    font-size: .88rem; width: 100%; font-family: inherit;
}
.et-input:focus, .et-select:focus { outline: none; border-color: #2d4a43; }
.et-textarea { min-height: 80px; resize: vertical; }

.et-color-row { display: flex; align-items: center; gap: 10px; }
.et-color-row input[type="color"] { width: 42px; height: 36px; border: 1.5px solid var(--cl-border-light); border-radius: 8px; padding: 2px; cursor: pointer; }

.et-var-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.et-var-chip {
    font-size: .7rem; padding: 3px 8px; border-radius: 6px;
    background: #f0f4f8; color: #3B4F6B; cursor: pointer; font-family: Monaco, monospace;
    border: 1px solid transparent; transition: all .1s;
}
.et-var-chip:hover { background: #dce4ef; }

/* ── Blocks ── */
.et-blocks { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
.et-block {
    background: var(--cl-bg, #F7F5F2); border: 1.5px solid var(--cl-border-light, #E8E4DE); border-radius: 10px;
    padding: 12px 14px;
}
.et-block-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.et-block-type {
    font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
    padding: 3px 9px; border-radius: 6px; color: #fff;
}
.et-block-type.bt-paragraph { background: #3B4F6B; }
.et-block-type.bt-highlight { background: #2d4a43; }
.et-block-type.bt-list { background: #6B5B3E; }
.et-block-type.bt-button { background: #5B4B6B; }
.et-block-type.bt-signature { background: #7B3B2C; }
.et-block-type.bt-divider { background: #6c757d; }
.et-block-type.bt-image { background: #1565c0; }

.et-block-actions { margin-left: auto; display: flex; gap: 4px; }
.et-block-btn {
    width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;
    background: none; border: none; border-radius: 5px; color: var(--cl-text-muted); cursor: pointer;
    font-size: .82rem;
}
.et-block-btn:hover { background: rgba(0,0,0,.06); color: var(--cl-text); }
.et-block-btn.danger:hover { background: rgba(220,53,69,.1); color: #dc3545; }

.et-block-content textarea, .et-block-content input {
    background: #fff; border: 1.5px solid var(--cl-border-light); border-radius: 6px;
    padding: 6px 10px; width: 100%; font-size: .86rem; font-family: inherit;
}
.et-block-content textarea { min-height: 60px; resize: vertical; }

.et-add-block {
    display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;
    padding-top: 12px; border-top: 1px dashed var(--cl-border-light);
}
.et-add-btn {
    font-size: .76rem; padding: 5px 10px; border: 1px dashed var(--cl-border-light, #c8c2b8);
    background: #fff; border-radius: 6px; cursor: pointer; color: var(--cl-text-secondary);
    display: inline-flex; align-items: center; gap: 4px;
}
.et-add-btn:hover { border-color: #2d4a43; color: #2d4a43; }

.et-list-items { display: flex; flex-direction: column; gap: 4px; }
.et-list-item-edit { display: flex; gap: 6px; align-items: center; }
.et-list-item-edit input { flex: 1; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-envelope-paper me-2"></i>Templates d'email</h4>
        <p class="text-muted small mb-0">Personnalisez les emails envoyés automatiquement par SpocSpace</p>
    </div>
    <div class="d-flex gap-2" id="etToolbar" style="display:none !important">
        <button class="btn btn-outline-secondary btn-sm" id="etResetBtn"><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
        <button class="btn btn-outline-primary btn-sm" id="etTestBtn"><i class="bi bi-send"></i> Email de test</button>
        <button class="btn btn-outline-secondary btn-sm" id="etPreviewBtn"><i class="bi bi-eye"></i> Aperçu</button>
        <button class="btn btn-success btn-sm" id="etSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
    </div>
</div>

<div class="et-layout">
    <!-- List -->
    <div class="et-list" id="etList">
        <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>
    </div>

    <!-- Editor -->
    <div class="et-editor" id="etEditor">
        <div class="et-editor-empty">
            <i class="bi bi-envelope-paper"></i>
            <p class="mb-0">Sélectionnez un template à gauche pour l'éditer</p>
        </div>
    </div>
</div>

<!-- Preview modal -->
<div class="modal fade" id="etPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Aperçu — <span id="etPreviewSubject"></span></h5>
                <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
            </div>
            <div class="modal-body p-0" style="max-height:75vh;overflow:hidden">
                <iframe id="etPreviewFrame" style="width:100%;height:75vh;border:none"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Test email modal -->
<div class="modal fade" id="etTestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send me-2"></i>Envoyer un email de test</h5>
                <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;border:1px solid #dee2e6" data-bs-dismiss="modal"><i class="bi bi-x-lg" style="font-size:0.85rem"></i></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Un email avec des données d'exemple sera envoyé à l'adresse ci-dessous.</p>
                <label class="form-label">Email destinataire</label>
                <input type="email" class="form-control" id="etTestEmail" placeholder="vous@exemple.ch">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="etTestSend"><i class="bi bi-send"></i> Envoyer</button>
            </div>
        </div>
    </div>
</div>

<script<?= nonce() ?>>
(function() {
    let templates = [];
    let current = null;
    let currentDef = null;

    // ─── Load templates list ───
    async function loadList() {
        const r = await adminApiPost('admin_list_email_templates');
        if (!r.success) return;
        templates = r.templates;
        renderList();
    }

    function renderList() {
        const el = document.getElementById('etList');
        const byCat = {};
        templates.forEach(t => {
            if (!byCat[t.category]) byCat[t.category] = [];
            byCat[t.category].push(t);
        });
        let html = '';
        Object.entries(byCat).forEach(([cat, items]) => {
            html += `<div class="et-list-cat">${esc(cat)}</div>`;
            items.forEach(t => {
                html += `<div class="et-list-item ${current?.key === t.key ? 'active' : ''}" data-key="${t.key}">
                    <div class="et-list-item-info">
                        <div class="et-list-item-name">${esc(t.name)}</div>
                        <div class="et-list-item-meta">${esc(t.description || '')}</div>
                    </div>
                    ${t.customized ? '<div class="et-custom-dot" title="Personnalisé"></div>' : ''}
                </div>`;
            });
        });
        el.innerHTML = html;
        el.querySelectorAll('[data-key]').forEach(it => {
            it.addEventListener('click', () => selectTemplate(it.dataset.key));
        });
    }

    // ─── Select template ───
    async function selectTemplate(key) {
        const r = await adminApiPost('admin_get_email_template', { key });
        if (!r.success) return;
        current = r.template;
        currentDef = current.definition;
        renderList();
        renderEditor();
    }

    // ─── Render editor ───
    function renderEditor() {
        const el = document.getElementById('etEditor');
        const tbar = document.getElementById('etToolbar');
        tbar.style.removeProperty('display');

        const vars = currentDef.variables || [];

        el.innerHTML = `
            <div class="et-section">
                <div class="et-section-title"><i class="bi bi-card-heading"></i> Sujet de l'email</div>
                <input type="text" class="et-input" id="etSubject" value="${esc(current.subject || '')}">
                <div class="et-var-chips" id="etSubjectChips">
                    ${vars.map(v => `<span class="et-var-chip" data-var="${v}" data-target="etSubject">{{${v}}}</span>`).join('')}
                </div>
            </div>

            <div class="et-section">
                <div class="et-section-title"><i class="bi bi-palette"></i> En-tête coloré</div>
                <div class="et-grid-2" style="margin-bottom:10px">
                    <div>
                        <label class="small text-muted mb-1">Titre</label>
                        <input type="text" class="et-input" id="etHeaderTitle" value="${esc(current.header_title || '')}">
                    </div>
                    <div>
                        <label class="small text-muted mb-1">Sous-titre</label>
                        <input type="text" class="et-input" id="etHeaderSubtitle" value="${esc(current.header_subtitle || '')}">
                    </div>
                </div>
                <div class="et-grid-2">
                    <div>
                        <label class="small text-muted mb-1">Couleur de fond</label>
                        <div class="et-color-row">
                            <input type="color" id="etHeaderColor" value="${esc(current.header_color || '#2d4a43')}">
                            <input type="text" class="et-input" id="etHeaderColorHex" value="${esc(current.header_color || '#2d4a43')}" style="flex:1">
                        </div>
                    </div>
                    <div>
                        <label class="small text-muted mb-1">Couleur du texte</label>
                        <div class="et-color-row">
                            <input type="color" id="etHeaderTextColor" value="${esc(current.header_text_color || '#ffffff')}">
                            <input type="text" class="et-input" id="etHeaderTextColorHex" value="${esc(current.header_text_color || '#ffffff')}" style="flex:1">
                        </div>
                    </div>
                </div>
                <div style="margin-top:10px">
                    <label class="d-inline-flex align-items-center gap-2 small">
                        <input type="checkbox" id="etShowLogo" ${current.show_logo ? 'checked' : ''}>
                        Afficher le logo de l'EMS
                    </label>
                </div>
            </div>

            <div class="et-section">
                <div class="et-section-title"><i class="bi bi-layout-text-window"></i> Corps du message — Blocs</div>
                <div class="et-blocks" id="etBlocks"></div>
                <div class="et-add-block">
                    <span class="small text-muted me-2 d-flex align-items-center">Ajouter :</span>
                    <button class="et-add-btn" data-add="paragraph"><i class="bi bi-paragraph"></i> Paragraphe</button>
                    <button class="et-add-btn" data-add="highlight"><i class="bi bi-stickies"></i> Encart coloré</button>
                    <button class="et-add-btn" data-add="list"><i class="bi bi-list-ul"></i> Liste</button>
                    <button class="et-add-btn" data-add="button"><i class="bi bi-link-45deg"></i> Bouton lien</button>
                    <button class="et-add-btn" data-add="image"><i class="bi bi-image"></i> Image</button>
                    <button class="et-add-btn" data-add="divider"><i class="bi bi-dash"></i> Séparateur</button>
                    <button class="et-add-btn" data-add="signature"><i class="bi bi-pen"></i> Signature</button>
                </div>
            </div>

            <div class="et-section">
                <div class="et-section-title"><i class="bi bi-card-text"></i> Pied de page (mentions légales, RGPD...)</div>
                <textarea class="et-input et-textarea" id="etFooter" rows="2">${esc(current.footer_text || '')}</textarea>
            </div>

            <div class="et-section">
                <div class="et-section-title"><i class="bi bi-tag"></i> Variables disponibles pour ce template</div>
                <div class="et-var-chips">
                    ${vars.map(v => `<span class="et-var-chip" style="cursor:default">{{${v}}}</span>`).join('')}
                </div>
                <small class="text-muted d-block mt-1">Cliquez sur une variable dans un champ pour l'insérer</small>
            </div>
        `;

        renderBlocks();
        bindEditorEvents();
    }

    function renderBlocks() {
        const wrap = document.getElementById('etBlocks');
        if (!wrap) return;
        const blocks = current.blocks || [];
        if (!blocks.length) {
            wrap.innerHTML = '<div class="text-center text-muted small py-3">Aucun bloc — ajoutez-en un ci-dessous</div>';
            return;
        }
        wrap.innerHTML = blocks.map((b, i) => renderBlockEditor(b, i)).join('');

        // Bind events
        wrap.querySelectorAll('[data-block-field]').forEach(input => {
            input.addEventListener('input', (e) => {
                const idx = parseInt(e.target.dataset.blockIdx);
                const field = e.target.dataset.blockField;
                if (field === 'items') return; // handled separately
                current.blocks[idx][field] = e.target.value;
            });
        });

        wrap.querySelectorAll('[data-item-idx]').forEach(input => {
            input.addEventListener('input', (e) => {
                const bi = parseInt(e.target.dataset.blockIdx);
                const ii = parseInt(e.target.dataset.itemIdx);
                current.blocks[bi].items[ii] = e.target.value;
            });
        });

        wrap.querySelectorAll('[data-block-up]').forEach(btn => btn.addEventListener('click', e => moveBlock(parseInt(e.currentTarget.dataset.blockUp), -1)));
        wrap.querySelectorAll('[data-block-down]').forEach(btn => btn.addEventListener('click', e => moveBlock(parseInt(e.currentTarget.dataset.blockDown), 1)));
        wrap.querySelectorAll('[data-block-delete]').forEach(btn => btn.addEventListener('click', e => deleteBlock(parseInt(e.currentTarget.dataset.blockDelete))));
        wrap.querySelectorAll('[data-item-delete]').forEach(btn => btn.addEventListener('click', e => {
            const bi = parseInt(e.currentTarget.dataset.blockIdx);
            const ii = parseInt(e.currentTarget.dataset.itemDelete);
            current.blocks[bi].items.splice(ii, 1);
            renderBlocks();
        }));
        wrap.querySelectorAll('[data-item-add]').forEach(btn => btn.addEventListener('click', e => {
            const bi = parseInt(e.currentTarget.dataset.itemAdd);
            if (!current.blocks[bi].items) current.blocks[bi].items = [];
            current.blocks[bi].items.push('');
            renderBlocks();
        }));
    }

    function renderBlockEditor(b, i) {
        const typeLabels = { paragraph: 'Paragraphe', highlight: 'Encart', list: 'Liste', button: 'Bouton', signature: 'Signature', divider: 'Séparateur', image: 'Image' };
        let body = '';

        if (b.type === 'paragraph' || b.type === 'signature') {
            body = `<textarea data-block-idx="${i}" data-block-field="content" rows="3">${esc(b.content || '')}</textarea>`;
        } else if (b.type === 'highlight') {
            body = `
                <div class="et-grid-2" style="margin-bottom:6px">
                    <input type="text" placeholder="Titre" data-block-idx="${i}" data-block-field="title" value="${esc(b.title || '')}">
                    <div class="d-flex gap-1">
                        <input type="color" data-block-idx="${i}" data-block-field="color" value="${esc(b.color || '#2d4a43')}" style="width:36px;padding:2px;border-radius:6px">
                        <input type="color" data-block-idx="${i}" data-block-field="bg" value="${esc(b.bg || '#f4f9f6')}" style="width:36px;padding:2px;border-radius:6px" title="Fond">
                        <input type="text" placeholder="#couleur" data-block-idx="${i}" data-block-field="color" value="${esc(b.color || '#2d4a43')}" style="flex:1;font-size:.75rem">
                    </div>
                </div>
                <textarea data-block-idx="${i}" data-block-field="content" rows="2">${esc(b.content || '')}</textarea>`;
        } else if (b.type === 'list') {
            const items = b.items || [];
            body = '<div class="et-list-items">' + items.map((item, ii) => `
                <div class="et-list-item-edit">
                    <input type="text" data-block-idx="${i}" data-item-idx="${ii}" value="${esc(item)}">
                    <button class="et-block-btn danger" data-block-idx="${i}" data-item-delete="${ii}"><i class="bi bi-x"></i></button>
                </div>
            `).join('') + `<button class="et-add-btn" data-item-add="${i}" style="margin-top:4px"><i class="bi bi-plus"></i> Ajouter un élément</button></div>`;
        } else if (b.type === 'button') {
            body = `
                <div class="et-grid-2" style="margin-bottom:6px">
                    <input type="text" placeholder="Libellé du bouton" data-block-idx="${i}" data-block-field="label" value="${esc(b.label || '')}">
                    <div class="d-flex gap-1 align-items-center">
                        <input type="color" data-block-idx="${i}" data-block-field="color" value="${esc(b.color || '#2d4a43')}" style="width:36px;padding:2px;border-radius:6px">
                        <input type="text" placeholder="URL ou {{reset_link}}" data-block-idx="${i}" data-block-field="url" value="${esc(b.url || '')}" style="flex:1">
                    </div>
                </div>`;
        } else if (b.type === 'image') {
            body = `
                <input type="text" placeholder="URL de l'image" data-block-idx="${i}" data-block-field="url" value="${esc(b.url || '')}" style="margin-bottom:4px">
                <input type="text" placeholder="Texte alternatif" data-block-idx="${i}" data-block-field="alt" value="${esc(b.alt || '')}">`;
        } else if (b.type === 'divider') {
            body = '<div class="small text-muted">Ligne de séparation horizontale</div>';
        }

        return `<div class="et-block">
            <div class="et-block-head">
                <span class="et-block-type bt-${b.type}">${esc(typeLabels[b.type] || b.type)}</span>
                <div class="et-block-actions">
                    <button class="et-block-btn" data-block-up="${i}" ${i === 0 ? 'disabled style="opacity:.3"' : ''}><i class="bi bi-arrow-up"></i></button>
                    <button class="et-block-btn" data-block-down="${i}" ${i === current.blocks.length - 1 ? 'disabled style="opacity:.3"' : ''}><i class="bi bi-arrow-down"></i></button>
                    <button class="et-block-btn danger" data-block-delete="${i}"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="et-block-content">${body}</div>
        </div>`;
    }

    function moveBlock(idx, dir) {
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= current.blocks.length) return;
        [current.blocks[idx], current.blocks[newIdx]] = [current.blocks[newIdx], current.blocks[idx]];
        renderBlocks();
    }

    function deleteBlock(idx) {
        current.blocks.splice(idx, 1);
        renderBlocks();
    }

    function addBlock(type) {
        const defaults = {
            paragraph: { type: 'paragraph', content: '' },
            highlight: { type: 'highlight', title: '', content: '', color: '#2d4a43', bg: '#f4f9f6' },
            list: { type: 'list', items: [''] },
            button: { type: 'button', label: 'Cliquez ici', url: '', color: '#2d4a43' },
            signature: { type: 'signature', content: 'Cordialement,\n<strong>L\'équipe</strong>' },
            divider: { type: 'divider' },
            image: { type: 'image', url: '', alt: '' },
        };
        if (!current.blocks) current.blocks = [];
        current.blocks.push(defaults[type]);
        renderBlocks();
    }

    function bindEditorEvents() {
        // Sync simple fields
        const sync = (id, field) => {
            document.getElementById(id)?.addEventListener('input', e => current[field] = e.target.value);
        };
        sync('etSubject', 'subject');
        sync('etHeaderTitle', 'header_title');
        sync('etHeaderSubtitle', 'header_subtitle');
        sync('etFooter', 'footer_text');

        // Color pickers with hex sync
        const syncColor = (colorId, hexId, field) => {
            const c = document.getElementById(colorId);
            const h = document.getElementById(hexId);
            c?.addEventListener('input', e => { current[field] = e.target.value; if (h) h.value = e.target.value; });
            h?.addEventListener('input', e => { if (/^#[0-9a-f]{6}$/i.test(e.target.value)) { current[field] = e.target.value; if (c) c.value = e.target.value; } });
        };
        syncColor('etHeaderColor', 'etHeaderColorHex', 'header_color');
        syncColor('etHeaderTextColor', 'etHeaderTextColorHex', 'header_text_color');

        // Show logo
        document.getElementById('etShowLogo')?.addEventListener('change', e => current.show_logo = e.target.checked ? 1 : 0);

        // Variable chips inserter
        document.querySelectorAll('.et-var-chip[data-var]').forEach(chip => {
            chip.addEventListener('click', () => {
                const targetId = chip.dataset.target;
                const v = chip.dataset.var;
                const target = document.getElementById(targetId);
                if (target) {
                    const pos = target.selectionStart || target.value.length;
                    target.value = target.value.slice(0, pos) + '{{' + v + '}}' + target.value.slice(pos);
                    target.dispatchEvent(new Event('input'));
                    target.focus();
                }
            });
        });

        // Add block buttons
        document.querySelectorAll('[data-add]').forEach(btn => {
            btn.addEventListener('click', () => addBlock(btn.dataset.add));
        });
    }

    // ─── Save ───
    document.getElementById('etSaveBtn')?.addEventListener('click', async () => {
        if (!current) return;
        const btn = document.getElementById('etSaveBtn');
        btn.disabled = true;
        const r = await adminApiPost('admin_save_email_template', {
            key: current.template_key,
            subject: current.subject,
            header_color: current.header_color,
            header_text_color: current.header_text_color,
            show_logo: current.show_logo ? 1 : 0,
            header_title: current.header_title,
            header_subtitle: current.header_subtitle,
            blocks: current.blocks,
            footer_text: current.footer_text,
            is_active: 1,
        });
        btn.disabled = false;
        if (r.success) {
            showToast('Template enregistré', 'success');
            loadList();
        } else {
            showToast(r.message || 'Erreur', 'danger');
        }
    });

    // ─── Preview ───
    document.getElementById('etPreviewBtn')?.addEventListener('click', async () => {
        if (!current) return;
        const r = await adminApiPost('admin_preview_email_template', {
            key: current.template_key,
            subject: current.subject,
            header_color: current.header_color,
            header_text_color: current.header_text_color,
            show_logo: current.show_logo ? 1 : 0,
            header_title: current.header_title,
            header_subtitle: current.header_subtitle,
            blocks: current.blocks,
            footer_text: current.footer_text,
        });
        if (!r.success) return;
        document.getElementById('etPreviewSubject').textContent = r.subject;
        const frame = document.getElementById('etPreviewFrame');
        frame.srcdoc = r.html;
        new bootstrap.Modal(document.getElementById('etPreviewModal')).show();
    });

    // ─── Reset ───
    document.getElementById('etResetBtn')?.addEventListener('click', async () => {
        if (!current) return;
        if (!confirm('Réinitialiser ce template aux valeurs par défaut ?')) return;
        const r = await adminApiPost('admin_reset_email_template', { key: current.template_key });
        if (r.success) {
            showToast('Template réinitialisé', 'success');
            await loadList();
            await selectTemplate(current.template_key);
        }
    });

    // ─── Test email ───
    document.getElementById('etTestBtn')?.addEventListener('click', () => {
        if (!current) return;
        new bootstrap.Modal(document.getElementById('etTestModal')).show();
    });
    document.getElementById('etTestSend')?.addEventListener('click', async () => {
        const to = document.getElementById('etTestEmail').value.trim();
        if (!to) { showToast('Email requis', 'warning'); return; }

        // First save current state, then send
        await adminApiPost('admin_save_email_template', {
            key: current.template_key,
            subject: current.subject,
            header_color: current.header_color,
            header_text_color: current.header_text_color,
            show_logo: current.show_logo ? 1 : 0,
            header_title: current.header_title,
            header_subtitle: current.header_subtitle,
            blocks: current.blocks,
            footer_text: current.footer_text,
            is_active: 1,
        });

        const r = await adminApiPost('admin_send_test_email_template', { key: current.template_key, to });
        if (r.success) {
            showToast(r.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('etTestModal'))?.hide();
        } else {
            showToast(r.message || 'Erreur', 'danger');
        }
    });

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    loadList();
})();
</script>
