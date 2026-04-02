/**
 * Collègues absences module
 */
import { escapeHtml, formatDate, absenceTypeBadge, statusBadge } from '../helpers.js';

export function init() {
    const absences = window.__SS_PAGE_DATA__?.absences || [];
    const tbody = document.getElementById('colleguesTableBody');
    if (!tbody) return;

    if (!absences.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Aucune absence en cours</td></tr>';
        return;
    }

    tbody.innerHTML = absences.map(a => `
      <tr>
        <td><strong>${escapeHtml(a.prenom)} ${escapeHtml(a.nom)}</strong></td>
        <td>${absenceTypeBadge(a.type)}</td>
        <td>${escapeHtml(formatDate(a.date_debut))}</td>
        <td>${escapeHtml(formatDate(a.date_fin))}</td>
        <td>${escapeHtml(a.module_nom || '—')}</td>
        <td>${statusBadge(a.statut)}</td>
      </tr>
    `).join('');
}

export function destroy() {}
