/**
 * Documents module — Employee SPA
 */
import { apiPost, escapeHtml } from '../helpers.js';

let services = [];
let currentFilter = '';
let searchTimeout = null;

function fileIcon(mime) {
    if (mime?.includes('pdf')) return { cls: 'pdf', icon: 'bi-file-earmark-pdf-fill' };
    if (mime?.includes('word') || mime?.includes('document')) return { cls: 'word', icon: 'bi-file-earmark-word-fill' };
    if (mime?.includes('excel') || mime?.includes('sheet') || mime?.includes('csv')) return { cls: 'excel', icon: 'bi-file-earmark-excel-fill' };
    if (mime?.includes('presentation') || mime?.includes('powerpoint')) return { cls: 'ppt', icon: 'bi-file-earmark-ppt-fill' };
    if (mime?.includes('image')) return { cls: 'image', icon: 'bi-file-earmark-image-fill' };
    return { cls: 'other', icon: 'bi-file-earmark-fill' };
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('fr-CH', { day: 'numeric', month: 'short', year: 'numeric' });
}

function renderServices(data) {
    services = data || [];
    renderServicePills();
}

async function loadServices() {
    const res = await apiPost('get_document_services');
    if (!res.success) return;
    renderServices(res.services);
}

function renderServicePills() {
    const container = document.getElementById('docServiceCards');
    if (!container) return;

    let html = `<button class="doc-filter-pill ${!currentFilter ? 'active' : ''}" data-service="">
        <i class="bi bi-grid-fill pill-icon"></i> Tous
    </button>`;

    services.forEach(s => {
        html += `<button class="doc-filter-pill ${currentFilter === s.id ? 'active' : ''}" data-service="${escapeHtml(s.id)}">
            <i class="bi bi-${escapeHtml(s.icone)} pill-icon" style="${currentFilter === s.id ? '' : 'color:' + escapeHtml(s.couleur)}"></i>
            ${escapeHtml(s.nom)}
        </button>`;
    });

    container.innerHTML = html;

    container.querySelectorAll('.doc-filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            currentFilter = btn.dataset.service || '';
            renderServicePills();
            loadDocuments();
        });
    });
}

async function loadDocuments() {
    const search = document.getElementById('feSearchInput')?.value?.trim() || '';
    const grid = document.getElementById('docGrid');
    if (!grid) return;

    grid.innerHTML = '<div class="page-loading"><span class="spinner"></span></div>';

    const res = await apiPost('get_documents', { service_id: currentFilter, search });
    if (!res.success) {
        grid.innerHTML = '<div class="doc-empty-state"><i class="bi bi-exclamation-triangle"></i><p>Erreur de chargement</p></div>';
        return;
    }

    const docs = res.documents || [];
    if (!docs.length) {
        grid.innerHTML = `<div class="doc-empty-state">
            <i class="bi bi-folder2-open"></i>
            <p>${search ? 'Aucun document trouvé pour cette recherche' : 'Aucun document disponible'}</p>
        </div>`;
        return;
    }

    grid.innerHTML = docs.map(d => {
        const fi = fileIcon(d.mime_type);
        const svc = services.find(s => s.id === d.service_id) || { nom: d.service_nom, icone: d.service_icone, couleur: d.service_couleur };
        const viewUrl = `/spocspace/api.php?action=serve_document&id=${encodeURIComponent(d.id)}`;

        return `<div class="doc-card" data-id="${escapeHtml(d.id)}">
            <div class="doc-card-top">
                <div class="doc-icon-box ${fi.cls}"><i class="bi ${fi.icon}"></i></div>
                <div style="min-width:0;flex:1">
                    <div class="doc-card-title">${escapeHtml(d.titre)}</div>
                    <div class="doc-card-filename">${escapeHtml(d.original_name)}</div>
                </div>
            </div>
            <div class="doc-card-desc">${d.description ? escapeHtml(d.description) : '<span style="opacity:.4">Pas de description</span>'}</div>
            <div class="doc-card-footer">
                <div class="doc-card-meta">
                    <span class="doc-service-badge" style="background:${escapeHtml(svc.couleur)}15;color:${escapeHtml(svc.couleur)}">
                        <i class="bi bi-${escapeHtml(svc.icone)}"></i> ${escapeHtml(svc.nom)}
                    </span>
                    <span>${formatSize(d.size)}</span>
                    <span>${formatDate(d.created_at)}</span>
                </div>
                <div class="doc-card-actions">
                    <button type="button" class="doc-action-btn" title="Voir"
                        data-act="view"
                        data-url="${viewUrl}"
                        data-mime="${escapeHtml(d.mime_type || '')}"
                        data-titre="${escapeHtml(d.titre)}">
                        <i class="bi bi-eye"></i>
                    </button>
                    <a href="${viewUrl}" download="${escapeHtml(d.original_name)}" class="doc-action-btn" title="Télécharger">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>`;
    }).join('');

    grid.querySelectorAll('[data-act="view"]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            viewDoc(btn.dataset.url, btn.dataset.mime, btn.dataset.titre);
        });
    });
}

// ── Lightbox viewer (adapté de admin/pages/documents.php) ──
function viewDoc(url, mime, titre) {
    const isImage = mime && mime.startsWith('image/');
    const isPdf = mime && mime.includes('pdf');
    const isWord = mime && (mime.includes('word') || mime.includes('msword'));
    const isText = mime && (mime.includes('text/plain') || mime.includes('text/csv'));

    if (!isImage && !isPdf && !isWord && !isText) {
        const a = document.createElement('a');
        a.href = url; a.download = ''; a.click();
        return;
    }

    let lb = document.getElementById('docLightbox');
    if (!lb) {
        lb = document.createElement('div');
        lb.id = 'docLightbox';
        lb.className = 'doc-lb';
        lb.innerHTML = '<div class="doc-lb-overlay"></div>'
            + '<button class="doc-lb-close"><i class="bi bi-x-lg"></i></button>'
            + '<div class="doc-lb-title" id="docLbTitle"></div>'
            + '<button class="doc-lb-fs d-none" id="docLbFs" title="Plein écran"><i class="bi bi-arrows-fullscreen"></i></button>'
            + '<a class="doc-lb-dl" id="docLbDl" title="Télécharger"><i class="bi bi-download"></i></a>'
            + '<div class="doc-lb-stage" id="docLbStage"></div>';
        document.body.appendChild(lb);
    }

    const lbTitle = document.getElementById('docLbTitle');
    const lbClose = lb.querySelector('.doc-lb-close');
    const lbDl = document.getElementById('docLbDl');
    const fsBtn = document.getElementById('docLbFs');
    const stage = document.getElementById('docLbStage');

    lbTitle.textContent = titre;
    lbDl.href = url;
    lbDl.setAttribute('target', '_blank');
    lbTitle.style.display = '';
    lbClose.style.display = '';
    lbDl.style.display = '';

    if (isImage) {
        stage.innerHTML = '<img src="' + url + '" alt="' + escapeHtml(titre) + '" draggable="false">';
        fsBtn.classList.add('d-none');
    } else if (isPdf) {
        stage.innerHTML = '<div class="doc-lb-word-wrap">'
            + '<div class="doc-lb-word-header"><h6><i class="bi bi-file-earmark-pdf-fill" style="color:#7B3B2C"></i> ' + escapeHtml(titre) + '</h6>'
            + '<button class="doc-lb-word-close" id="docLbWordClose"><i class="bi bi-x-lg"></i></button></div>'
            + '<div style="flex:1;overflow:hidden"><iframe id="docLbIframe" sandbox="allow-same-origin allow-scripts" src="' + url + '#view=FitH" style="width:100%;height:100%;border:none"></iframe></div>'
            + '<div class="doc-lb-word-footer">'
            + '<div class="d-flex align-items-center gap-2 ms-auto">'
            + '<a class="btn btn-sm btn-outline-secondary" href="' + url + '" download><i class="bi bi-download"></i> Télécharger</a>'
            + '<button class="btn btn-sm btn-outline-secondary" id="docLbPdfFs"><i class="bi bi-arrows-fullscreen"></i> Plein écran</button>'
            + '<button class="btn btn-sm btn-light" id="docLbWordCloseFooter">Fermer</button></div>'
            + '</div></div>';
        fsBtn.classList.add('d-none');
        lbTitle.style.display = 'none';
        lbClose.style.display = 'none';
        lbDl.style.display = 'none';

        document.getElementById('docLbPdfFs')?.addEventListener('click', () => {
            const iframe = document.getElementById('docLbIframe');
            if (iframe?.requestFullscreen) iframe.requestFullscreen();
            else if (iframe?.webkitRequestFullscreen) iframe.webkitRequestFullscreen();
        });
    } else if (isWord) {
        stage.innerHTML = '<div class="doc-lb-word-wrap">'
            + '<div class="doc-lb-word-header"><h6><i class="bi bi-file-earmark-word-fill" style="color:#3B4F6B"></i> ' + escapeHtml(titre) + '</h6>'
            + '<button class="doc-lb-word-close" id="docLbWordClose"><i class="bi bi-x-lg"></i></button></div>'
            + '<div class="doc-lb-word-body" id="docWordBody"><div class="doc-lb-word-loading"><span class="spinner-border spinner-border-sm"></span> Chargement...</div></div>'
            + '<div class="doc-lb-word-footer">'
            + '<div class="d-flex align-items-center gap-2"><i class="bi bi-zoom-out small"></i><input type="range" min="50" max="200" value="100" step="10" id="docLbZoom" style="width:100px"><i class="bi bi-zoom-in small"></i><span class="small text-muted" id="docLbZoomVal">100%</span></div>'
            + '<div class="d-flex align-items-center gap-2 ms-auto">'
            + '<a class="btn btn-sm btn-outline-secondary" href="' + url + '" download><i class="bi bi-download"></i> Télécharger</a>'
            + '<button class="btn btn-sm btn-outline-secondary" id="docLbWordFs"><i class="bi bi-arrows-fullscreen"></i> Plein écran</button>'
            + '<button class="btn btn-sm btn-light" id="docLbWordCloseFooter">Fermer</button></div>'
            + '</div></div>';
        fsBtn.classList.add('d-none');
        lbTitle.style.display = 'none';
        lbClose.style.display = 'none';
        lbDl.style.display = 'none';

        document.getElementById('docLbWordFs')?.addEventListener('click', () => {
            const wrap = stage.querySelector('.doc-lb-word-wrap');
            if (!wrap) return;
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else if (wrap.requestFullscreen) {
                wrap.requestFullscreen();
            } else if (wrap.webkitRequestFullscreen) {
                wrap.webkitRequestFullscreen();
            }
        });

        document.getElementById('docLbZoom')?.addEventListener('input', function() {
            const z = this.value;
            document.getElementById('docLbZoomVal').textContent = z + '%';
            const wrapper = document.querySelector('#docWordBody .docx-wrapper') || document.querySelector('#docWordBody > div');
            if (wrapper) { wrapper.style.transform = 'scale(' + (z/100) + ')'; wrapper.style.transformOrigin = 'top center'; }
        });

        (async () => {
            try {
                if (!window.JSZip) {
                    await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/spocspace/assets/js/vendor/jszip.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                }
                if (!window.docx) {
                    await new Promise((res, rej) => { const s = document.createElement('script'); s.src = '/spocspace/assets/js/vendor/docx-preview.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
                }
                const resp = await fetch(url);
                const blob = await resp.blob();
                const container = document.getElementById('docWordBody');
                container.innerHTML = '';
                await window.docx.renderAsync(blob, container, null, {
                    className: 'docx-preview',
                    inWrapper: true,
                    ignoreWidth: false,
                    ignoreHeight: false,
                    ignoreFonts: false,
                    breakPages: true,
                    useBase64URL: true,
                });
            } catch(e) {
                console.error('docx-preview error:', e);
                document.getElementById('docWordBody').innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle"></i> Erreur de rendu — <a href="' + url + '" target="_blank">Télécharger le fichier</a></div>';
            }
        })();
    } else if (isText) {
        stage.innerHTML = '<div class="doc-lb-word-wrap"><div class="doc-lb-word-loading"><span class="spinner-border spinner-border-sm"></span></div></div>';
        fsBtn.classList.add('d-none');
        fetch(url).then(r => r.text()).then(text => {
            const wrap = stage.querySelector('.doc-lb-word-wrap');
            if (wrap) wrap.innerHTML = '<div class="doc-lb-word-content"><pre style="white-space:pre-wrap;font-size:.88rem">' + escapeHtml(text) + '</pre></div>';
        });
    }

    lb.classList.remove('doc-lb-hidden');
    document.body.style.overflow = 'hidden';

    const ac = new AbortController();
    const sig = { signal: ac.signal };
    function closeLb() { lb.classList.add('doc-lb-hidden'); document.body.style.overflow = ''; ac.abort(); }
    lb.querySelector('.doc-lb-close').addEventListener('click', closeLb, sig);
    lb.querySelector('.doc-lb-overlay').addEventListener('click', closeLb, sig);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLb(); }, sig);
    document.getElementById('docLbWordClose')?.addEventListener('click', closeLb, sig);
    document.getElementById('docLbWordCloseFooter')?.addEventListener('click', closeLb, sig);
    fsBtn.addEventListener('click', () => {
        const iframe = document.getElementById('docLbIframe');
        if (iframe) {
            if (iframe.requestFullscreen) iframe.requestFullscreen();
            else if (iframe.webkitRequestFullscreen) iframe.webkitRequestFullscreen();
        }
    }, sig);
}

export async function init() {
    // Use global topbar search
    document.getElementById('feSearchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadDocuments, 300);
    });

    // Use SSR data on first load to avoid extra round-trip
    const ssr = window.__SS_PAGE_DATA__;
    if (ssr) {
        renderServices(ssr.services || []);
    } else {
        await loadServices();
    }
    await loadDocuments();
}

export function destroy() {
    clearTimeout(searchTimeout);
    const globalSearch = document.getElementById('feSearchInput');
    if (globalSearch) globalSearch.value = '';
    document.getElementById('docLightbox')?.remove();
    document.body.style.overflow = '';
    services = [];
    currentFilter = '';
}
