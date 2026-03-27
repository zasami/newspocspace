/**
 * zerdaTime - Fiches de salaire (employee view)
 */
import { apiPost, escapeHtml, toast } from '../helpers.js';
import { BASE } from '../app.js';

const MOIS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
let currentYear = new Date().getFullYear();

export async function init() {
    document.getElementById('fsYearLabel').textContent = currentYear;

    document.getElementById('fsPrevYear')?.addEventListener('click', () => {
        currentYear--;
        document.getElementById('fsYearLabel').textContent = currentYear;
        loadFiches();
    });
    document.getElementById('fsNextYear')?.addEventListener('click', () => {
        currentYear++;
        document.getElementById('fsYearLabel').textContent = currentYear;
        loadFiches();
    });

    await loadFiches();
}

async function loadFiches() {
    const grid = document.getElementById('fsGrid');
    grid.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Chargement...</div>';

    const res = await apiPost('get_mes_fiches_salaire');
    if (!res.success) {
        grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-exclamation-triangle" style="font-size:2rem;display:block;margin-bottom:0.5rem"></i>Erreur de chargement</div>';
        return;
    }

    const fiches = (res.fiches || []).filter(f => f.annee == currentYear);

    // Build a map month -> fiche
    const byMonth = {};
    fiches.forEach(f => { byMonth[f.mois] = f; });
    const now = new Date();
    const maxMonth = currentYear == now.getFullYear() ? now.getMonth() + 1 : 12;

    let html = '';
    for (let m = maxMonth; m >= 1; m--) {
        const fiche = byMonth[m];
        if (fiche) {
            html += `
            <div class="col-12 col-sm-6 col-md-4">
                <div class="fiche-card" data-id="${fiche.id}">
                    <div class="fiche-icon"><i class="bi bi-file-pdf-fill"></i></div>
                    <div class="fiche-info">
                        <div class="fiche-period">${MOIS[m]} ${currentYear}</div>
                        <div class="fiche-meta">${escapeHtml(fiche.original_name)} &middot; ${formatSize(fiche.size)}</div>
                    </div>
                    <i class="bi bi-download text-primary"></i>
                </div>
            </div>`;
        } else {
            html += `
            <div class="col-12 col-sm-6 col-md-4">
                <div class="fiche-empty-month">
                    <div class="fiche-icon"><i class="bi bi-file-pdf"></i></div>
                    <div>
                        <div style="font-weight:600;font-size:0.95rem">${MOIS[m]} ${currentYear}</div>
                        <div style="font-size:0.78rem;color:var(--zt-text-muted)">Non disponible</div>
                    </div>
                </div>
            </div>`;
        }
    }

    if (!html) {
        html = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-receipt" style="font-size:2rem;display:block;margin-bottom:0.5rem"></i>Aucune fiche de salaire</div>';
    }

    grid.innerHTML = html;

    // Click to open PDF
    grid.querySelectorAll('.fiche-card[data-id]').forEach(card => {
        card.addEventListener('click', () => {
            const id = card.dataset.id;
            window.open(`${BASE}/api.php?action=serve_fiche_salaire&id=${id}`, '_blank');
        });
    });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1048576).toFixed(1) + ' Mo';
}

export function destroy() {}
