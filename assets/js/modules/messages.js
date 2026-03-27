/**
 * Messages module
 */
import { apiPost, toast, escapeHtml } from '../helpers.js';

export async function init() {
    // Submit message
    document.getElementById('messageForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const sujet = document.getElementById('messageSujet').value.trim();
        const contenu = document.getElementById('messageContenu').value.trim();

        if (!sujet || !contenu) return;

        const res = await apiPost('send_message', { sujet, contenu });
        if (res.success) {
            toast('Message envoyé');
            document.getElementById('messageForm').reset();
            await loadMessages();
        } else {
            toast(res.message || 'Erreur');
        }
    });

    await loadMessages();
}

async function loadMessages() {
    const res = await apiPost('get_mes_messages');
    const container = document.getElementById('messagesListBody');
    if (!container) return;

    const messages = res.messages || [];
    if (!messages.length) {
        container.innerHTML = '<div class="empty-state"><i class="bi bi-envelope-open"></i><p>Aucun message</p></div>';
        return;
    }

    const userId = window.__ZT__?.user?.id;

    container.innerHTML = messages.map(m => {
        const isMine = m.from_user_id === userId;
        const date = new Date(m.created_at).toLocaleDateString('fr-CH', {
            day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        return `
          <div style="padding:0.75rem 0;border-bottom:1px solid var(--zt-border-light)${!m.lu && !isMine ? ';background:rgba(26,26,26,0.04);margin:0 -1.25rem;padding-left:1.25rem;padding-right:1.25rem' : ''}">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem">
              <strong style="font-size:0.88rem">${escapeHtml(m.sujet)}</strong>
              <small class="text-muted">${escapeHtml(date)}</small>
            </div>
            <div style="font-size:0.85rem;color:var(--zt-text-secondary);margin-bottom:0.25rem">
              ${isMine ? `<span class="text-muted">Envoyé à :</span> ${m.to_prenom ? escapeHtml(m.to_prenom + ' ' + m.to_nom) : 'Direction'}` :
                `<span class="text-muted">De :</span> ${escapeHtml(m.from_prenom + ' ' + m.from_nom)}`}
            </div>
            <div style="font-size:0.88rem">${escapeHtml(m.contenu)}</div>
          </div>
        `;
    }).join('');
}

export function destroy() {}
