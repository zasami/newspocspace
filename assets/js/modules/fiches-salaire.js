/**
 * Fiches de salaire — JS minimal (page SSR).
 * - Ouvrir le PDF en lightbox
 * - Navigation entre années
 * - Highlight scroll si ?highlight=id
 */
const BASE = '/spocspace';

export function init() {
    document.addEventListener('click', handleClick);
    document.addEventListener('keydown', handleKey);

    // Highlight scroll
    const hl = document.getElementById('ficheHighlight');
    if (hl) {
        setTimeout(() => {
            hl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => hl.classList.remove('fiche-highlight'), 3000);
        }, 300);
    }
}

function handleClick(e) {
    // Fermer lightbox
    const closeLb = e.target.closest('.ss-lightbox-close');
    if (closeLb) { closeLb.closest('.ss-lightbox')?.remove(); return; }
    // Clic sur fond sombre
    if (e.target.classList.contains('ss-lightbox')) { e.target.remove(); return; }

    // Fullscreen lightbox
    const fsBtn = e.target.closest('.ss-lightbox-fullscreen');
    if (fsBtn) {
        const wrap = fsBtn.closest('.ss-lightbox-wrap');
        if (wrap) {
            if (document.fullscreenElement) document.exitFullscreen();
            else wrap.requestFullscreen().catch(() => {});
        }
        return;
    }

    // Imprimer lightbox
    const printBtn = e.target.closest('.ss-lightbox-print');
    if (printBtn) {
        const iframe = printBtn.closest('.ss-lightbox')?.querySelector('iframe');
        if (iframe) {
            try { iframe.contentWindow.print(); }
            catch { window.open(iframe.src, '_blank'); }
        }
        return;
    }

    // Navigation année
    const yearBtn = e.target.closest('[data-fs-year]');
    if (yearBtn) {
        const year = yearBtn.dataset.fsYear;
        history.pushState({}, '', `${BASE}/fiches-salaire?annee=${year}`);
        window.dispatchEvent(new PopStateEvent('popstate'));
        return;
    }

    // Ouvrir fiche en lightbox
    const card = e.target.closest('[data-fiche-id]');
    if (card) {
        openPdfLightbox(card.dataset.ficheId, card.querySelector('.fiche-period')?.textContent?.trim());
    }
}

function handleKey(e) {
    if (e.key === 'Escape') {
        document.getElementById('ssLightbox')?.remove();
    }
}

function openPdfLightbox(id, title) {
    // Supprimer lightbox existante
    document.getElementById('ssLightbox')?.remove();

    const url = `${BASE}/api.php?action=serve_fiche_salaire&id=${encodeURIComponent(id)}`;

    const lb = document.createElement('div');
    lb.id = 'ssLightbox';
    lb.className = 'ss-lightbox';
    lb.innerHTML = `
        <div class="ss-lightbox-wrap">
            <div class="ss-lightbox-header">
                <span class="ss-lightbox-title"><i class="bi bi-file-pdf"></i> ${escapeHtml(title || 'Fiche de salaire')}</span>
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

    document.body.appendChild(lb);
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

export function destroy() {
    document.removeEventListener('click', handleClick);
    document.removeEventListener('keydown', handleKey);
    document.getElementById('ssLightbox')?.remove();
}
