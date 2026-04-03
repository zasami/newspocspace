/**
 * Website Admin — Interactive Section Editor
 */
(function() {
'use strict';

const API = '/spocspace/website/admin/api/sections.php';
let sections = window.__WA_SECTIONS || [];
const TYPES = window.__WA_TYPES || {};
const LABELS = window.__WA_LABELS || {};
let activeId = null;
let unsavedChanges = {};

// ── API helper ──
async function api(action, data = {}) {
    const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data })
    });
    return res.json();
}

// ── Toast ──
function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = 'wa-toast wa-toast-' + type;
    el.innerHTML = '<i class="bi ' + (type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle') + '"></i> ' + esc(msg);
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

// ── Section list click ──
document.getElementById('waSectionList').addEventListener('click', function(e) {
    const item = e.target.closest('.wa-section-item');
    if (!item) return;

    // Toggle visibility button
    if (e.target.closest('.wa-toggle-vis')) {
        e.stopPropagation();
        toggleVisibility(item.dataset.id);
        return;
    }

    selectSection(item.dataset.id);
});

function selectSection(id) {
    activeId = id;
    document.querySelectorAll('.wa-section-item').forEach(el => el.classList.toggle('active', el.dataset.id === id));
    document.getElementById('waEditorEmpty').style.display = 'none';
    document.getElementById('waEditorContent').style.display = '';
    renderEditor(id);
}

function getSection(id) {
    return sections.find(s => s.id === id);
}

// ── Toggle visibility ──
async function toggleVisibility(id) {
    const res = await api('toggle_visibility', { id });
    if (!res.success) return toast(res.message || 'Erreur', 'error');

    const s = getSection(id);
    if (s) s.is_visible = res.is_visible;

    const item = document.querySelector(`.wa-section-item[data-id="${id}"]`);
    if (item) {
        item.classList.toggle('wa-hidden-section', !res.is_visible);
        const icon = item.querySelector('.wa-toggle-vis i');
        if (icon) icon.className = 'bi ' + (res.is_visible ? 'bi-eye' : 'bi-eye-slash');
    }
    toast(res.is_visible ? 'Section visible' : 'Section masquée');
}

// ── Render editor for a section ──
function renderEditor(id) {
    const s = getSection(id);
    if (!s) return;

    const type = TYPES[s.section_type] || TYPES['custom'];
    const content = s.content || {};
    const container = document.getElementById('waEditorContent');

    let html = `
    <div class="wa-edit-header">
        <div class="wa-edit-header-left">
            <h2>${esc(LABELS[s.section_key] || s.section_key)}</h2>
            <span class="wa-edit-badge"><i class="bi ${type.icon}"></i> ${esc(type.label)}</span>
            <span class="wa-unsaved" id="waUnsaved" style="display:none">Non sauvegardé</span>
        </div>
        <div class="wa-edit-actions">
            <button class="wa-btn wa-btn-danger wa-btn-sm" onclick="WA.deleteSection('${id}')">
                <i class="bi bi-trash"></i> Supprimer
            </button>
            <button class="wa-btn wa-btn-primary" id="waSaveBtn" onclick="WA.save()">
                <i class="bi bi-check-lg"></i> Sauvegarder
            </button>
        </div>
    </div>`;

    // Common fields
    html += `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-type-h1"></i> En-tête de section</div>
        <div class="wa-form-group">
            <label>Badge — Icône</label>
            <div class="wa-icon-input">
                <div class="wa-icon-preview"><i class="bi ${s.badge_icon || 'bi-square'}"></i></div>
                <input type="text" class="wa-input wa-input-sm" value="${esc(s.badge_icon || '')}" data-field="badge_icon" placeholder="bi-feather">
            </div>
        </div>
        <div class="wa-form-group">
            <label>Badge — Texte</label>
            <input type="text" class="wa-input" value="${esc(s.badge_text || '')}" data-field="badge_text" placeholder="Ex: Notre engagement">
        </div>
        <div class="wa-form-group">
            <label>Titre de la section</label>
            <input type="text" class="wa-input" value="${esc(s.title || '')}" data-field="title" placeholder="Titre de la section">
        </div>
        <div class="wa-form-group">
            <label>Sous-titre / Description</label>
            <textarea class="wa-textarea" data-field="subtitle" rows="2" placeholder="Description sous le titre">${esc(s.subtitle || '')}</textarea>
        </div>
    </div>`;

    // Type-specific content
    html += renderTypeEditor(s);

    container.innerHTML = html;

    // Mark changes
    container.querySelectorAll('[data-field]').forEach(el => {
        el.addEventListener('input', () => markUnsaved());
    });
    container.querySelectorAll('[data-content]').forEach(el => {
        el.addEventListener('input', () => markUnsaved());
    });
}

// ── Type-specific editors ──
function renderTypeEditor(s) {
    const content = s.content || {};
    // Custom editors by section_key
    if (s.section_key === 'pinned') return renderPinnedEditor(content);
    switch (s.section_type) {
        case 'hero': return renderHeroEditor(content);
        case 'cards':
        case 'services':
        case 'values':
        case 'team': return renderCardsEditor(content);
        case 'timeline': return renderTimelineEditor(content);
        case 'quote': return renderQuoteEditor(content);
        case 'contact': return renderContactEditor(content);
        default: return renderJsonEditor(content);
    }
}

function renderPinnedEditor(content) {
    const title = content.title || '';
    const text = content.text || '';
    const signature = content.signature || '';
    const image = content.image || '';
    return `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-pin-angle"></i> Rubrique épinglée</div>
        <p style="font-size:.85rem;color:#888;margin:0 0 12px">Ce bloc s'affiche sur la page d'accueil du site, avant "Formation continue".</p>
        <div class="wa-form-group">
            <label>Titre</label>
            <input type="text" class="wa-input" value="${esc(title)}" data-content="title" placeholder="Ex: 🎓 Félicitations à nos diplômées ! 🎓">
        </div>
        <div class="wa-form-group">
            <label>Texte</label>
            <div class="wa-rte-toolbar">
                <button type="button" class="wa-rte-btn" data-cmd="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="wa-rte-btn" data-cmd="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="wa-rte-btn" data-cmd="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
                <span class="wa-rte-sep"></span>
                <button type="button" class="wa-rte-btn" data-cmd="insertUnorderedList" title="Liste"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="wa-rte-btn" data-cmd="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                <span class="wa-rte-sep"></span>
                <button type="button" class="wa-rte-btn" data-cmd="justifyLeft" title="Aligner à gauche"><i class="bi bi-text-left"></i></button>
                <button type="button" class="wa-rte-btn" data-cmd="justifyCenter" title="Centrer"><i class="bi bi-text-center"></i></button>
                <button type="button" class="wa-rte-btn" data-cmd="justifyRight" title="Aligner à droite"><i class="bi bi-text-right"></i></button>
                <span class="wa-rte-sep"></span>
                <button type="button" class="wa-rte-btn" data-cmd="formatBlock" data-val="BLOCKQUOTE" title="Citation"><i class="bi bi-blockquote-left"></i></button>
            </div>
            <div class="wa-rte-editor" contenteditable="true" id="waPinnedText" data-content="text">${text}</div>
        </div>
        <div class="wa-form-group">
            <label>Signature</label>
            <input type="text" class="wa-input" value="${esc(signature)}" data-content="signature" placeholder="Ex: Directrice">
        </div>
        <div class="wa-form-group">
            <label>Image (optionnelle)</label>
            <div class="wa-img-upload">
                <div class="wa-img-preview" id="waPinnedImgPreview">
                    ${image ? `<img src="${esc(image)}">` : '<i class="bi bi-image" style="font-size:1.5rem;color:#bbb"></i>'}
                </div>
                <div>
                    <label class="wa-btn wa-btn-sm wa-btn-ghost" style="cursor:pointer">
                        <i class="bi bi-upload"></i> Télécharger une image
                        <input type="file" id="waPinnedImgFile" accept="image/*" style="display:none">
                    </label>
                    ${image ? `<button class="wa-btn wa-btn-sm wa-btn-ghost" id="waPinnedImgClear" style="color:#e53e3e"><i class="bi bi-trash"></i></button>` : ''}
                    <input type="hidden" id="waPinnedImgUrl" data-content="image" value="${esc(image)}">
                </div>
            </div>
        </div>
    </div>`;
}

// ── Rich text editor toolbar ──
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.wa-rte-btn');
    if (!btn) return;
    e.preventDefault();
    const cmd = btn.dataset.cmd;
    const val = btn.dataset.val || null;
    document.execCommand(cmd, false, val);
    document.getElementById('waPinnedText')?.focus();
});

// ── Pinned image upload ──
document.addEventListener('change', async function(e) {
    if (e.target.id !== 'waPinnedImgFile' || !e.target.files[0]) return;
    const fd = new FormData();
    fd.append('image', e.target.files[0]);
    fd.append('action', 'admin_upload_pinned_image');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('/spocspace/admin/api.php', {
        method: 'POST', body: fd,
        headers: { 'X-CSRF-Token': csrf }
    }).then(r => r.json());
    e.target.value = '';
    if (res.success && res.image_url) {
        document.getElementById('waPinnedImgUrl').value = res.image_url;
        document.getElementById('waPinnedImgPreview').innerHTML = `<img src="${esc(res.image_url)}">`;
        markUnsaved();
        toast('Image téléchargée');
    } else {
        toast(res.error || 'Erreur upload', 'error');
    }
});
document.addEventListener('click', function(e) {
    if (e.target.closest('#waPinnedImgClear')) {
        document.getElementById('waPinnedImgUrl').value = '';
        document.getElementById('waPinnedImgPreview').innerHTML = '<i class="bi bi-image" style="font-size:1.5rem;color:#bbb"></i>';
        e.target.closest('#waPinnedImgClear')?.remove();
        markUnsaved();
    }
});

function renderHeroEditor(content) {
    const stats = content.stats || [];
    let html = `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-bar-chart"></i> Statistiques du hero</div>
        <div class="wa-stats-grid" id="waStatsGrid">`;
    stats.forEach((st, i) => {
        html += `
            <div class="wa-stat-edit" data-idx="${i}">
                <input type="text" class="wa-input wa-input-sm" value="${esc(st.num)}" data-content="stats.${i}.num" placeholder="Valeur">
                <input type="text" class="wa-input wa-input-sm" value="${esc(st.label)}" data-content="stats.${i}.label" placeholder="Label">
                <button class="wa-btn-icon" onclick="WA.removeStat(${i})" title="Supprimer"><i class="bi bi-x"></i></button>
            </div>`;
    });
    html += `</div>
        <button class="wa-add-card-btn" onclick="WA.addStat()" style="margin-top:8px">
            <i class="bi bi-plus-lg"></i> Ajouter une statistique
        </button>
    </div>`;

    // CTA buttons
    const ctaPrimary = content.cta_primary || {};
    const ctaSecondary = content.cta_secondary || {};
    html += `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-link-45deg"></i> Boutons d'action</div>
        <div class="wa-contact-grid">
            <div>
                <div class="wa-form-group">
                    <label>Bouton principal — Texte</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaPrimary.text || '')}" data-content="cta_primary.text">
                </div>
                <div class="wa-form-group">
                    <label>Bouton principal — Lien</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaPrimary.href || '')}" data-content="cta_primary.href">
                </div>
                <div class="wa-form-group">
                    <label>Bouton principal — Icône</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaPrimary.icon || '')}" data-content="cta_primary.icon">
                </div>
            </div>
            <div>
                <div class="wa-form-group">
                    <label>Bouton secondaire — Texte</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaSecondary.text || '')}" data-content="cta_secondary.text">
                </div>
                <div class="wa-form-group">
                    <label>Bouton secondaire — Lien</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaSecondary.href || '')}" data-content="cta_secondary.href">
                </div>
                <div class="wa-form-group">
                    <label>Bouton secondaire — Icône</label>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(ctaSecondary.icon || '')}" data-content="cta_secondary.icon">
                </div>
            </div>
        </div>
    </div>`;

    return html;
}

function renderCardsEditor(content) {
    const cards = content.cards || [];
    let html = `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-grid-3x3-gap"></i> Cartes (${cards.length})</div>
        <div class="wa-card-grid" id="waCardGrid">`;
    cards.forEach((c, i) => {
        html += `
        <div class="wa-card-edit" data-idx="${i}">
            <div class="wa-card-edit-header">
                <div class="wa-icon-input">
                    <div class="wa-card-edit-icon"><i class="bi ${c.icon || 'bi-square'}"></i></div>
                    <input type="text" class="wa-input wa-input-sm" value="${esc(c.icon || '')}" data-content="cards.${i}.icon" placeholder="bi-icon" style="width:120px"
                        oninput="this.previousElementSibling.querySelector('i').className='bi '+this.value">
                </div>
                <button class="wa-btn-icon" onclick="WA.removeCard(${i})" title="Supprimer"><i class="bi bi-trash"></i></button>
            </div>
            <div class="wa-form-group">
                <label>Titre</label>
                <input type="text" class="wa-input wa-input-sm" value="${esc(c.title || '')}" data-content="cards.${i}.title">
            </div>
            <div class="wa-form-group">
                <label>Texte</label>
                <textarea class="wa-textarea" data-content="cards.${i}.text" rows="3">${esc(c.text || '')}</textarea>
            </div>
        </div>`;
    });
    html += `</div>
        <button class="wa-add-card-btn" onclick="WA.addCard()" style="margin-top:12px">
            <i class="bi bi-plus-lg"></i> Ajouter une carte
        </button>
    </div>`;
    return html;
}

function renderTimelineEditor(content) {
    const items = content.items || [];
    let html = `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-clock-history"></i> Éléments de la timeline (${items.length})</div>
        <div class="wa-timeline-list" id="waTimelineList">`;
    items.forEach((item, i) => {
        html += `
        <div class="wa-timeline-item-edit" data-idx="${i}">
            <input type="text" class="wa-input wa-input-sm" value="${esc(item.time || '')}" data-content="items.${i}.time" placeholder="Heure">
            <input type="text" class="wa-input wa-input-sm" value="${esc(item.title || '')}" data-content="items.${i}.title" placeholder="Titre">
            <input type="text" class="wa-input wa-input-sm" value="${esc(item.text || '')}" data-content="items.${i}.text" placeholder="Description">
            <button class="wa-btn-icon" onclick="WA.removeTimelineItem(${i})" title="Supprimer"><i class="bi bi-trash"></i></button>
        </div>`;
    });
    html += `</div>
        <button class="wa-add-card-btn" onclick="WA.addTimelineItem()" style="margin-top:8px">
            <i class="bi bi-plus-lg"></i> Ajouter un créneau
        </button>
    </div>`;
    return html;
}

function renderQuoteEditor(content) {
    return `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-quote"></i> Citation</div>
        <div class="wa-form-group">
            <label>Texte de la citation</label>
            <textarea class="wa-textarea" data-content="text" rows="3">${esc(content.text || '')}</textarea>
        </div>
        <div class="wa-form-group">
            <label>Vidéo de fond (chemin)</label>
            <input type="text" class="wa-input" value="${esc(content.video || '')}" data-content="video" placeholder="assets/video/fichier.mp4">
        </div>
    </div>`;
}

function renderContactEditor(content) {
    return `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-chat-dots"></i> Informations de contact</div>
        <div class="wa-contact-grid">
            <div class="wa-form-group">
                <label>Adresse</label>
                <textarea class="wa-textarea" data-content="address" rows="2">${esc(content.address || '')}</textarea>
            </div>
            <div class="wa-form-group">
                <label>Téléphone</label>
                <input type="text" class="wa-input" value="${esc(content.phone || '')}" data-content="phone">
            </div>
            <div class="wa-form-group">
                <label>Email</label>
                <input type="text" class="wa-input" value="${esc(content.email || '')}" data-content="email">
            </div>
            <div class="wa-form-group">
                <label>Horaires de visite</label>
                <input type="text" class="wa-input" value="${esc(content.hours || '')}" data-content="hours">
            </div>
            <div class="wa-form-group">
                <label>Note horaires</label>
                <input type="text" class="wa-input" value="${esc(content.hours_note || '')}" data-content="hours_note">
            </div>
        </div>
    </div>`;
}

function renderJsonEditor(content) {
    return `
    <div class="wa-section-box">
        <div class="wa-section-box-title"><i class="bi bi-code-slash"></i> Contenu avancé</div>
        <div class="wa-form-group">
            <label>Données (format JSON)</label>
            <textarea class="wa-textarea" data-content="__raw_json" rows="10" style="font-family:monospace;font-size:12px">${esc(JSON.stringify(content, null, 2))}</textarea>
        </div>
    </div>`;
}

// ── Collect form data ──
function collectData() {
    const s = getSection(activeId);
    if (!s) return null;

    const data = { id: activeId };
    const container = document.getElementById('waEditorContent');

    // Direct fields
    container.querySelectorAll('[data-field]').forEach(el => {
        data[el.dataset.field] = el.value;
    });

    // Content fields
    const content = JSON.parse(JSON.stringify(s.content || {}));
    container.querySelectorAll('[data-content]').forEach(el => {
        const path = el.dataset.content;

        if (path === '__raw_json') {
            try { Object.assign(content, JSON.parse(el.value)); } catch(e) {}
            return;
        }

        const val = el.hasAttribute('contenteditable') ? el.innerHTML : el.value;
        setNestedValue(content, path, val);
    });

    data.content = content;
    return data;
}

function setNestedValue(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (let i = 0; i < keys.length - 1; i++) {
        const k = isNaN(keys[i]) ? keys[i] : parseInt(keys[i]);
        if (current[k] === undefined) {
            current[k] = isNaN(keys[i + 1]) ? {} : [];
        }
        current = current[k];
    }
    const lastKey = isNaN(keys[keys.length - 1]) ? keys[keys.length - 1] : parseInt(keys[keys.length - 1]);
    current[lastKey] = value;
}

// ── Save ──
async function save() {
    const data = collectData();
    if (!data) return;

    const btn = document.getElementById('waSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sauvegarde...';

    const res = await api('save', data);

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Sauvegarder';

    if (!res.success) return toast(res.message || 'Erreur de sauvegarde', 'error');

    // Update local cache
    const s = getSection(activeId);
    if (s) {
        if (data.title !== undefined) s.title = data.title;
        if (data.subtitle !== undefined) s.subtitle = data.subtitle;
        if (data.badge_icon !== undefined) s.badge_icon = data.badge_icon;
        if (data.badge_text !== undefined) s.badge_text = data.badge_text;
        if (data.content) s.content = data.content;
    }

    document.getElementById('waUnsaved').style.display = 'none';
    toast('Section sauvegardée');
}

function markUnsaved() {
    const el = document.getElementById('waUnsaved');
    if (el) el.style.display = '';
}

// ── Add/Remove cards, timeline items, stats ──
function addCard() {
    const s = getSection(activeId);
    if (!s) return;
    if (!s.content.cards) s.content.cards = [];
    s.content.cards.push({ icon: 'bi-star', title: 'Nouveau', text: '' });
    renderEditor(activeId);
    markUnsaved();
}

function removeCard(idx) {
    const s = getSection(activeId);
    if (!s || !s.content.cards) return;
    s.content.cards.splice(idx, 1);
    renderEditor(activeId);
    markUnsaved();
}

function addTimelineItem() {
    const s = getSection(activeId);
    if (!s) return;
    if (!s.content.items) s.content.items = [];
    s.content.items.push({ time: '', title: '', text: '' });
    renderEditor(activeId);
    markUnsaved();
}

function removeTimelineItem(idx) {
    const s = getSection(activeId);
    if (!s || !s.content.items) return;
    s.content.items.splice(idx, 1);
    renderEditor(activeId);
    markUnsaved();
}

function addStat() {
    const s = getSection(activeId);
    if (!s) return;
    if (!s.content.stats) s.content.stats = [];
    s.content.stats.push({ num: '0', label: 'Label' });
    renderEditor(activeId);
    markUnsaved();
}

function removeStat(idx) {
    const s = getSection(activeId);
    if (!s || !s.content.stats) return;
    s.content.stats.splice(idx, 1);
    renderEditor(activeId);
    markUnsaved();
}

// ── Delete section ──
async function deleteSection(id) {
    if (!confirm('Supprimer cette section ? Cette action est irréversible.')) return;
    const res = await api('delete', { id });
    if (!res.success) return toast(res.message || 'Erreur', 'error');

    sections = sections.filter(s => s.id !== id);
    const item = document.querySelector(`.wa-section-item[data-id="${id}"]`);
    if (item) item.remove();

    if (activeId === id) {
        activeId = null;
        document.getElementById('waEditorEmpty').style.display = '';
        document.getElementById('waEditorContent').style.display = 'none';
    }

    toast('Section supprimée');
}

// ── Add section modal ──
document.getElementById('waAddSection')?.addEventListener('click', () => {
    document.getElementById('waAddModal').style.display = 'flex';
    document.getElementById('waNewKey').value = '';
    document.getElementById('waNewTitle').value = '';
    document.getElementById('waNewKey').focus();
});

document.getElementById('waAddCancel')?.addEventListener('click', closeAddModal);
document.getElementById('waAddModalClose')?.addEventListener('click', closeAddModal);
document.getElementById('waAddModal')?.addEventListener('click', e => {
    if (e.target.id === 'waAddModal') closeAddModal();
});

function closeAddModal() {
    document.getElementById('waAddModal').style.display = 'none';
}

document.getElementById('waAddConfirm')?.addEventListener('click', async () => {
    const key = document.getElementById('waNewKey').value.trim();
    const type = document.querySelector('input[name="waNewType"]:checked')?.value || 'text';
    const title = document.getElementById('waNewTitle').value.trim();

    if (!key) return toast('Clé requise', 'error');

    const res = await api('create', { section_key: key, section_type: type, title: title || null });
    if (!res.success) return toast(res.message || 'Erreur', 'error');

    sections.push(res.section);
    renderSidebarItem(res.section);
    closeAddModal();
    selectSection(res.section.id);
    toast('Section créée');
});

function renderSidebarItem(s) {
    const type = TYPES[s.section_type] || TYPES['custom'];
    const list = document.getElementById('waSectionList');
    const div = document.createElement('div');
    div.className = 'wa-section-item' + (s.is_visible ? '' : ' wa-hidden-section');
    div.dataset.id = s.id;
    div.dataset.key = s.section_key;
    div.dataset.type = s.section_type;
    div.draggable = true;
    div.innerHTML = `
        <div class="wa-section-drag"><i class="bi bi-grip-vertical"></i></div>
        <div class="wa-section-icon" style="color:${type.color}"><i class="bi ${type.icon}"></i></div>
        <div class="wa-section-info">
            <div class="wa-section-name">${esc(s.section_key)}</div>
            <div class="wa-section-type">${esc(type.label)}</div>
        </div>
        <div class="wa-section-actions">
            <button class="wa-btn-icon wa-toggle-vis" title="${s.is_visible ? 'Masquer' : 'Afficher'}">
                <i class="bi ${s.is_visible ? 'bi-eye' : 'bi-eye-slash'}"></i>
            </button>
        </div>`;
    list.appendChild(div);
}

// ── Drag & Drop reorder ──
const list = document.getElementById('waSectionList');
let draggedItem = null;

list.addEventListener('dragstart', e => {
    const item = e.target.closest('.wa-section-item');
    if (!item) return;
    draggedItem = item;
    item.style.opacity = '0.4';
    e.dataTransfer.effectAllowed = 'move';
});

list.addEventListener('dragend', e => {
    if (draggedItem) draggedItem.style.opacity = '';
    document.querySelectorAll('.wa-drag-over').forEach(el => el.classList.remove('wa-drag-over'));
    draggedItem = null;
});

list.addEventListener('dragover', e => {
    e.preventDefault();
    const item = e.target.closest('.wa-section-item');
    if (!item || item === draggedItem) return;
    e.dataTransfer.dropEffect = 'move';
    document.querySelectorAll('.wa-drag-over').forEach(el => el.classList.remove('wa-drag-over'));
    item.classList.add('wa-drag-over');
});

list.addEventListener('drop', async e => {
    e.preventDefault();
    const target = e.target.closest('.wa-section-item');
    if (!target || !draggedItem || target === draggedItem) return;

    // Reorder DOM
    const items = [...list.querySelectorAll('.wa-section-item')];
    const fromIdx = items.indexOf(draggedItem);
    const toIdx = items.indexOf(target);

    if (fromIdx < toIdx) {
        target.after(draggedItem);
    } else {
        target.before(draggedItem);
    }

    // Save order
    const newOrder = [...list.querySelectorAll('.wa-section-item')].map(el => el.dataset.id);
    const res = await api('reorder', { order: newOrder });

    // Update local sections order
    const orderMap = {};
    newOrder.forEach((id, i) => orderMap[id] = i + 1);
    sections.forEach(s => { if (orderMap[s.id]) s.sort_order = orderMap[s.id]; });
    sections.sort((a, b) => a.sort_order - b.sort_order);

    if (res.success) toast('Ordre mis à jour');
});

// ── Keyboard shortcut: Ctrl+S ──
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (activeId) save();
    }
});

// ── Expose to global for onclick handlers ──
window.WA = { save, deleteSection, addCard, removeCard, addTimelineItem, removeTimelineItem, addStat, removeStat };

})();
