/**
 * PV (Procès-Verbaux) module - Split View
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';
import { createEditor, getHTML, destroyEditor } from '../rich-editor.js';

const _c = [];
function on(el, ev, fn, o) { if (!el) return; el.addEventListener(ev, fn, o); _c.push(() => el.removeEventListener(ev, fn, o)); }

let refs = null;
let selectedPvId = null;
let commentEditor = null;

export async function init() {
    // Load references from SSR data
    refs = { success: true, modules: window.__ZT_PAGE_DATA__?.modules || [] };

    // Fill module filter (clear first to avoid duplicates on SPA re-init)
    const modFilter = document.getElementById('pvModuleFilter');
    if (modFilter && refs.modules) {
        // Keep only the first "Tous les modules" option
        while (modFilter.options.length > 1) modFilter.remove(1);
        refs.modules.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.code + ' — ' + m.nom;
            modFilter.appendChild(opt);
        });
    }

    // Event listeners — use global topbar search
    const globalSearch = document.getElementById('feSearchInput');
    on(globalSearch, 'input', debounce(loadPvList, 300));
    on(document.getElementById('pvModuleFilter'), 'change', loadPvList);

    // Initial load
    await loadPvList();
}

function debounce(fn, delay) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

async function loadPvList() {
    const filters = {
        module_id: document.getElementById('pvModuleFilter')?.value || '',
        search: document.getElementById('feSearchInput')?.value?.trim() || '',
    };

    const result = await apiPost('get_pv_list', filters);
    if (!result?.success) {
        toast('Erreur de chargement');
        return;
    }

    renderPvList(result.list || []);
    document.getElementById('pvCount').textContent = result.list?.length || 0;

    // Auto-select the latest PV if none selected
    if (!selectedPvId && result.list?.length > 0) {
        selectPv(result.list[0].id);
    }
}

function renderPvList(list) {
    const container = document.getElementById('pvListContainer');
    
    if (!list || list.length === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Aucun PV trouvé</div>';
        return;
    }

    const html = list.map(pv => createPvListItem(pv)).join('');
    container.innerHTML = html;

    // Add click handlers
    document.querySelectorAll('[data-pv-id]').forEach(item => {
        on(item, 'click', () => {
            const pvId = item.dataset.pvId;
            selectPv(pvId);
        });
    });
}

function createPvListItem(pv) {
    const date = new Date(pv.created_at).toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
    const isSelected = pv.id === selectedPvId ? 'selected' : '';
    
    return `
        <div class="pv-item ${isSelected}" data-pv-id="${pv.id}">
            <div class="pv-item-title">${escapeHtml(pv.titre)}</div>
            <div class="pv-item-meta">
                <div>${escapeHtml(pv.prenom || '?')} ${escapeHtml(pv.nom || '')}</div>
                <div style="color: #bbb;">${date}</div>
            </div>
        </div>
    `;
}

async function selectPv(pvId) {
    selectedPvId = pvId;
    
    // Update selection style
    document.querySelectorAll('[data-pv-id]').forEach(item => {
        item.classList.toggle('selected', item.dataset.pvId === pvId);
    });
    
    const panel = document.getElementById('pvDetailPanel');
    panel.innerHTML = '<div style="text-align: center; padding: 40px;"><span class="spinner-border spinner-border-sm text-primary"></span></div>';

    // Fetch full detail (with comments)
    const result = await apiPost('get_pv', { id: pvId });
    if (!result.success) {
        panel.innerHTML = '<div class="alert alert-danger m-4">Erreur de chargement</div>';
        return;
    }

    displayPvDetail(result.pv, result.comments || []);
}

async function displayPvDetail(pv, comments) {
    const panel = document.getElementById('pvDetailPanel');
    
    // Cleanup old editor
    if (commentEditor) {
        destroyEditor(commentEditor);
        commentEditor = null;
    }
    
    const statusClass = pv.statut === 'finalisé' ? 'success' : pv.statut === 'enregistrement' ? 'warning' : 'secondary';
    const date = new Date(pv.created_at).toLocaleString('fr-FR');
    
    const participants = pv.participants && Array.isArray(pv.participants) ? pv.participants : [];
    const participantsHtml = participants.length > 0 
        ? participants.map(p => '<span class="pv-participant-badge">' + escapeHtml(p.prenom) + ' ' + escapeHtml(p.nom) + '</span>').join('')
        : '<span style="color: #999;">Aucun participant</span>';
    
    // Rating UI
    let ratingHtml = '';
    if (pv.allow_comments != 0) {
        const currentNote = pv.note ? parseInt(pv.note) : 0;
        ratingHtml = `
            <div class="mt-3 pt-3 border-top d-flex align-items-center justify-content-between">
                <span class="fw-bold small text-muted text-uppercase">Évaluer ce PV</span>
                <div class="pv-rating-stars" id="pvRatingStars">
                    ${[1,2,3,4,5].map(i => `<i class="bi bi-star-fill ${i <= currentNote ? 'active' : ''}" data-note="${i}"></i>`).join('')}
                </div>
            </div>
        `;
    }

    // Comments UI
    let commentsHtml = '';
    if (pv.allow_comments == 0) {
        commentsHtml = `
            <div class="alert alert-secondary mt-4 mb-0 text-center small">
                <i class="bi bi-info-circle me-1"></i> Les commentaires ont été désactivés par l'administrateur pour ce PV.
            </div>
        `;
    } else {
        const commentsList = comments.length > 0
            ? comments.map(c => {
                const initials = ((c.prenom||'')[0] + (c.nom||'')[0]).toUpperCase();
                const avatarHtml = c.photo
                    ? `<img src="${c.photo}" class="pv-comment-avatar" alt="">`
                    : `<div class="pv-comment-avatar-initials">${escapeHtml(initials)}</div>`;

                const likes = c.likes || [];
                const likedByMe = c.liked_by_me;
                const likeAvatars = likes.slice(0, 5).map(l => {
                    const li = ((l.prenom||'')[0] + (l.nom||'')[0]).toUpperCase();
                    return l.photo
                        ? `<img src="${l.photo}" title="${escapeHtml(l.prenom + ' ' + l.nom)}">`
                        : `<div class="pv-like-av-init" title="${escapeHtml(l.prenom + ' ' + l.nom)}">${escapeHtml(li)}</div>`;
                }).join('');
                const likeCount = likes.length;

                return `<div class="pv-comment-box">
                    <div class="pv-comment-top">
                        ${avatarHtml}
                        <div class="pv-comment-content">
                            <div class="pv-comment-header">
                                <span class="pv-comment-author">${escapeHtml(c.prenom)} ${escapeHtml(c.nom)} <small class="text-muted fw-normal">(${escapeHtml(c.fonction_code || '?')})</small></span>
                                <span class="pv-comment-date">${new Date(c.created_at).toLocaleString('fr-FR', {dateStyle: 'short', timeStyle: 'short'})}</span>
                            </div>
                            <div class="pv-comment-body">${c.contenu}</div>
                            <div class="pv-comment-footer">
                                <button class="pv-like-btn${likedByMe ? ' liked' : ''}" data-like-comment="${c.id}">
                                    <i class="bi bi-heart${likedByMe ? '-fill' : ''}"></i> ${likeCount || ''}
                                </button>
                                ${likeCount > 0 ? `<div class="pv-like-avatars">${likeAvatars}</div>` : ''}
                                ${likeCount > 5 ? `<span class="pv-like-count">+${likeCount - 5}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('')
            : '<div class="text-muted text-center small mb-4 py-3 border rounded bg-white">Soyez le premier à commenter ce PV.</div>';

        commentsHtml = `
            <div class="mt-4 pt-2">
                <h4 class="h6 fw-bold mb-3"><i class="bi bi-chat-text"></i> Commentaires (${comments.length})</h4>
                <div class="mb-4">
                    ${commentsList}
                </div>
                
                <div class="pv-editor-container mb-2">
                    <div id="pvCommentEditor"></div>
                </div>
                <div class="text-end">
                    <button class="btn btn-sm btn-primary px-4" id="btnSubmitComment">
                        <i class="bi bi-send"></i> Envoyer
                    </button>
                </div>
            </div>
        `;
    }
    
    panel.innerHTML = `
        <div class="pv-detail-card mb-4">
            <div class="pv-detail-header">
                <h2 class="pv-detail-title">${escapeHtml(pv.titre)}</h2>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="btnPrintPvFe" title="Imprimer"><i class="bi bi-printer"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnExportPvFe" title="Exporter PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <span class="badge pv-detail-status bg-${statusClass}">${pv.statut}</span>
                </div>
            </div>
            
            <div class="pv-detail-meta">
                <div class="pv-meta-item">
                    <strong>Créateur</strong>
                    ${escapeHtml(pv.prenom || '?')} ${escapeHtml(pv.nom || '')}
                </div>
                <div class="pv-meta-item">
                    <strong>Date</strong>
                    ${date}
                </div>
                <div class="pv-meta-item">
                    <strong>Module</strong>
                    ${pv.module_nom ? escapeHtml(pv.module_nom) : '—'}
                </div>
                <div class="pv-meta-item">
                    <strong>Participants</strong>
                    ${participants.length}
                </div>
            </div>
            
            ${pv.description ? '<div><div class="pv-section-title">Description</div><div style="padding: 10px; background: #f9f9f9; border-radius: 4px; margin-bottom: 15px;">' + escapeHtml(pv.description) + '</div></div>' : ''}
            
            ${pv.participants && participants.length > 0 ? '<div><div class="pv-section-title">Participants (' + participants.length + ')</div><div class="pv-participants" style="margin-bottom: 20px;">' + participantsHtml + '</div></div>' : ''}
            
            <div>
                <div class="pv-section-title">Contenu</div>
                <div class="pv-content-box">${pv.contenu || '<span class="text-muted">(Pas de contenu)</span>'}</div>
            </div>

            ${ratingHtml}
        </div>
        
        ${commentsHtml}
    `;

    // Print / Export PDF handlers
    const printPvContent = () => {
        const title = escapeHtml(pv.titre);
        const content = document.querySelector('.pv-content-box')?.innerHTML || '';
        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
          <style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;color:#333;line-height:1.6}
          h1{font-size:1.4rem;border-bottom:2px solid #333;padding-bottom:8px}
          .info{color:#666;font-size:0.85rem;margin-bottom:1.5rem}
          @media print{body{margin:20px}}</style></head>
          <body><h1>${title}</h1>
          <div class="info">Date: ${new Date(pv.created_at).toLocaleDateString('fr-CH')} — zerdaTime</div>
          ${content}</body></html>`);
        win.document.close();
        win.print();
    };
    document.getElementById('btnPrintPvFe')?.addEventListener('click', printPvContent);
    document.getElementById('btnExportPvFe')?.addEventListener('click', printPvContent);

    // Attach rating handlers
    const ratingStars = document.getElementById('pvRatingStars');
    if (ratingStars) {
        ratingStars.querySelectorAll('i').forEach(star => {
            on(star, 'click', async (e) => {
                const note = e.target.dataset.note;
                const r = await apiPost('rate_pv', { pv_id: pv.id, note: note });
                if (r.success) {
                    toast('Note enregistrée');
                    // Update visual immediately
                    ratingStars.querySelectorAll('i').forEach(s => {
                        if (parseInt(s.dataset.note) <= parseInt(note)) s.classList.add('active');
                        else s.classList.remove('active');
                    });
                } else {
                    toast(r.message || 'Erreur', 'error');
                }
            });
        });
    }

    // Init Tiptap
    const edContainer = document.getElementById('pvCommentEditor');
    if (edContainer) {
        commentEditor = await createEditor(edContainer, {
            placeholder: 'Ajouter un commentaire ou une remarque...',
            mode: 'mini'
        });

        const btnSubmit = document.getElementById('btnSubmitComment');
        on(btnSubmit, 'click', async () => {
            const html = getHTML(commentEditor);
            if (!html || html === '<p></p>') {
                toast('Commentaire vide', 'error');
                return;
            }

            btnSubmit.disabled = true;
            const r = await apiPost('comment_pv', { pv_id: pv.id, contenu: html });
            if (r.success) {
                toast('Commentaire ajouté !');
                selectPv(pv.id); // Reload to show new comment
            } else {
                toast(r.message || 'Erreur', 'error');
                btnSubmit.disabled = false;
            }
        });
    }

    // Like buttons
    document.querySelectorAll('[data-like-comment]').forEach(btn => {
        on(btn, 'click', async () => {
            const commentId = btn.dataset.likeComment;
            const r = await apiPost('toggle_pv_comment_like', { comment_id: commentId });
            if (r.success) {
                selectPv(pv.id); // Reload to refresh likes
            }
        });
    });
}

export function destroy() {
    if (commentEditor) {
        destroyEditor(commentEditor);
        commentEditor = null;
    }
    // Clear global search when leaving PV page
    const globalSearch = document.getElementById('feSearchInput');
    if (globalSearch) globalSearch.value = '';
    _c.forEach(f => f());
    _c.length = 0;
}
