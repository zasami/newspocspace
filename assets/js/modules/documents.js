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
        const viewUrl = `/zerdatime/api.php?action=serve_document&id=${encodeURIComponent(d.id)}`;

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
                    <a href="${viewUrl}" target="_blank" class="doc-action-btn" title="Voir / Imprimer">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="${viewUrl}" download="${escapeHtml(d.original_name)}" class="doc-action-btn" title="Télécharger">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>`;
    }).join('');
}

export async function init() {
    // Use global topbar search
    document.getElementById('feSearchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadDocuments, 300);
    });

    // Use SSR data on first load to avoid extra round-trip
    const ssr = window.__ZT_PAGE_DATA__;
    if (ssr) {
        renderServices(ssr.services || []);
    } else {
        await loadServices();
    }
    await loadDocuments();
}

export function destroy() {
    clearTimeout(searchTimeout);
    // Clear global search when leaving documents page
    const globalSearch = document.getElementById('feSearchInput');
    if (globalSearch) globalSearch.value = '';
    services = [];
    currentFilter = '';
}
