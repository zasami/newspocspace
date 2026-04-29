/**
 * Documents — JS minimal (page SSR).
 * - Filtrage par service (navigation SPA)
 * - Lightbox viewer (PDF, images, Word)
 * - Highlight scroll depuis notification
 */
import { escapeHtml } from '../helpers.js';

export function init() {
    document.addEventListener('click', handleClick);
    document.addEventListener('keydown', handleKey);

    // Highlight scroll
    const hl = document.getElementById('docHighlight');
    if (hl) {
        setTimeout(() => {
            hl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => hl.classList.remove('doc-highlight'), 3000);
        }, 300);
    }
}

function handleClick(e) {
    // Fermer lightbox
    const closeLb = e.target.closest('.ss-lightbox-close');
    if (closeLb) { closeLb.closest('.ss-lightbox')?.remove(); return; }
    if (e.target.classList.contains('ss-lightbox')) { e.target.remove(); return; }

    // Fullscreen
    const fsBtn = e.target.closest('.ss-lightbox-fullscreen');
    if (fsBtn) {
        const wrap = fsBtn.closest('.ss-lightbox-wrap');
        if (wrap) {
            if (document.fullscreenElement) document.exitFullscreen();
            else wrap.requestFullscreen().catch(() => {});
        }
        return;
    }

    // Print
    const printBtn = e.target.closest('.ss-lightbox-print');
    if (printBtn) {
        const iframe = printBtn.closest('.ss-lightbox')?.querySelector('iframe');
        if (iframe) {
            try { iframe.contentWindow.print(); }
            catch { window.open(iframe.src, '_blank'); }
        }
        return;
    }

    // Service filter pills
    const pill = e.target.closest('[data-doc-filter]');
    if (pill) {
        e.preventDefault();
        const svc = pill.dataset.docFilter;
        const url = svc ? `/newspocspace/documents?service=${encodeURIComponent(svc)}` : '/newspocspace/documents';
        history.pushState({}, '', url);
        window.dispatchEvent(new PopStateEvent('popstate'));
        return;
    }

    // View document button
    const viewBtn = e.target.closest('[data-doc-view]');
    if (viewBtn) {
        e.stopPropagation();
        const card = viewBtn.closest('[data-doc-id]');
        if (card) {
            openDocLightbox(card.dataset.docUrl, card.dataset.docMime, card.dataset.docTitre);
        }
        return;
    }
}

function handleKey(e) {
    if (e.key === 'Escape') {
        document.getElementById('ssLightbox')?.remove();
    }
}

function openDocLightbox(url, mime, title) {
    document.getElementById('ssLightbox')?.remove();

    const isPdf = mime && mime.includes('pdf');
    const isImage = mime && mime.startsWith('image/');

    if (!isPdf && !isImage) {
        // Non-viewable: download
        const a = document.createElement('a');
        a.href = url; a.download = ''; a.click();
        return;
    }

    const lb = document.createElement('div');
    lb.id = 'ssLightbox';
    lb.className = 'ss-lightbox';

    if (isImage) {
        lb.innerHTML = `
            <div class="ss-lightbox-wrap">
                <div class="ss-lightbox-header">
                    <span class="ss-lightbox-title"><i class="bi bi-image"></i> ${escapeHtml(title || 'Image')}</span>
                    <button class="ss-lightbox-close" title="Fermer (Échap)"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="ss-lightbox-content ss-lightbox-content-img">
                    <img src="${escapeHtml(url)}" alt="${escapeHtml(title || '')}">
                </div>
                <div class="ss-lightbox-footer">
                    <a href="${escapeHtml(url)}" target="_blank" class="ss-lightbox-btn"><i class="bi bi-box-arrow-up-right"></i> Ouvrir</a>
                    <a href="${escapeHtml(url)}" download class="ss-lightbox-btn"><i class="bi bi-download"></i> Télécharger</a>
                </div>
            </div>
        `;
    } else {
        lb.innerHTML = `
            <div class="ss-lightbox-wrap">
                <div class="ss-lightbox-header">
                    <span class="ss-lightbox-title"><i class="bi bi-file-pdf"></i> ${escapeHtml(title || 'Document')}</span>
                    <button class="ss-lightbox-close" title="Fermer (Échap)"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="ss-lightbox-content">
                    <iframe src="${escapeHtml(url)}#toolbar=1&navpanes=0"></iframe>
                </div>
                <div class="ss-lightbox-footer">
                    <button class="ss-lightbox-btn ss-lightbox-fullscreen" title="Plein écran"><i class="bi bi-arrows-fullscreen"></i> Plein écran</button>
                    <button class="ss-lightbox-btn ss-lightbox-print" title="Imprimer"><i class="bi bi-printer"></i> Imprimer</button>
                    <a href="${escapeHtml(url)}" target="_blank" class="ss-lightbox-btn"><i class="bi bi-box-arrow-up-right"></i> Ouvrir</a>
                    <a href="${escapeHtml(url)}" download class="ss-lightbox-btn"><i class="bi bi-download"></i> Télécharger</a>
                </div>
            </div>
        `;
    }

    document.body.appendChild(lb);
}

export function destroy() {
    document.removeEventListener('click', handleClick);
    document.removeEventListener('keydown', handleKey);
    document.getElementById('ssLightbox')?.remove();
}
