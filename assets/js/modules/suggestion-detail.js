import { apiPost, toast, escapeHtml, ssConfirm } from '../helpers.js';
import { navigateTo } from '../app.js';
import { EmojiPicker } from './emoji_picker.js';

let emojiPicker = null;

export function init() {
    // Vote
    document.querySelector('[data-sug-vote]')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const id = btn.dataset.sugVote;
        btn.disabled = true;
        try {
            const r = await apiPost('toggle_suggestion_vote', { id });
            if (!r.success) { toast(r.message || 'Erreur'); return; }
            btn.classList.toggle('voted', r.voted);
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'bi bi-hand-thumbs-up' + (r.voted ? '-fill' : '');
            const count = btn.querySelector('.sug-vote-count');
            if (count) count.textContent = r.votes_count;
        } finally { btn.disabled = false; }
    });

    // Edit
    document.querySelector('[data-sgd-edit]')?.addEventListener('click', (e) => {
        const id = e.currentTarget.dataset.sgdEdit;
        navigateTo('suggestion-new', '');
        history.replaceState({}, '', `/spocspace/suggestion-new?id=${encodeURIComponent(id)}`);
        location.reload();
    });

    // Delete
    document.querySelector('[data-sgd-del]')?.addEventListener('click', async (e) => {
        const id = e.currentTarget.dataset.sgdDel;
        const ok = await ssConfirm('Supprimer définitivement cette suggestion ?', { variant: 'danger', okText: 'Supprimer' });
        if (!ok) return;
        const r = await apiPost('delete_suggestion', { id });
        if (!r.success) { toast(r.message || 'Erreur'); return; }
        toast('Suggestion supprimée');
        navigateTo('suggestions');
    });

    // Rich-text toolbar
    bindRte();

    // Emoji picker (module partagé)
    const emojiBtn = document.getElementById('sgdEmojiBtn');
    const editor   = document.getElementById('sgdCommentEditor');
    if (emojiBtn && editor) {
        emojiPicker = new EmojiPicker();
        emojiPicker.init(emojiBtn, (emoji) => {
            editor.focus();
            document.execCommand('insertText', false, emoji);
        });
    }

    // Comment form
    const form = document.getElementById('sgdCommentForm');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = form.dataset.sugId;
        const ed = document.getElementById('sgdCommentEditor');
        const content = (ed?.innerHTML || '').trim();
        const plain = (ed?.textContent || '').trim();
        if (plain.length < 2) return;
        const btn = form.querySelector('.sgd-comment-send');
        btn.disabled = true;
        try {
            const r = await apiPost('add_suggestion_comment', { id, content });
            if (!r.success) { toast(r.message || 'Erreur'); return; }
            appendComment({
                content,
                prenom: window.__SS__?.user?.prenom,
                nom: window.__SS__?.user?.nom,
                created_at: new Date().toISOString(),
                role: ['admin','direction'].includes(window.__SS__?.user?.role) ? 'admin' : 'user',
            });
            ed.innerHTML = '';
        } finally { btn.disabled = false; }
    });
}

export function destroy() {
    try { emojiPicker?.close?.(); } catch(_) {}
    emojiPicker = null;
}

function bindRte() {
    const editor = document.getElementById('sgdCommentEditor');
    if (!editor) return;
    const toolbar = editor.parentElement.querySelector('.sgd-rte-toolbar');
    toolbar?.addEventListener('mousedown', e => {
        const btn = e.target.closest('.sgd-rte-btn');
        if (!btn || btn.id === 'sgdEmojiBtn') return;
        e.preventDefault();
        editor.focus();
        const cmd = btn.dataset.cmd;
        if (cmd) document.execCommand(cmd, false, null);
    });
    editor.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        document.execCommand('insertText', false, text);
    });
}

function appendComment(c) {
    const list = document.getElementById('sgdComments');
    if (!list) return;
    const empty = list.querySelector('.text-muted');
    if (empty && empty.textContent.startsWith('Aucun')) empty.remove();

    const who = escapeHtml([c.prenom, c.nom].filter(Boolean).join(' ') || '—');
    const isAdmin = c.role === 'admin';
    const div = document.createElement('div');
    div.className = 'sgd-comment' + (isAdmin ? ' admin' : '');
    // content vient d'un contenteditable local → on affiche tel quel (backend re-sanitizera sur le prochain load)
    div.innerHTML = `
        <div class="sgd-comment-head">
            <span><strong>${who}</strong>${isAdmin ? '<span class="sgd-admin-tag"> · Équipe</span>' : ''}</span>
            <span>à l'instant</span>
        </div>
        <div class="sgd-comment-body"></div>`;
    div.querySelector('.sgd-comment-body').innerHTML = c.content;
    list.appendChild(div);
}
