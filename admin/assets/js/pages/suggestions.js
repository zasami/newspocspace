(function () {
    'use strict';

    const state = { search: '', service: '', categorie: '', urgence: '', statut: '' };
    let currentList = [];
    let currentDetailId = null;
    let bsModal = null;

    const urgLabels = {
        critique: ['Critique', 'sug-adm-urg-crit'],
        eleve:    ['Élevée',   'sug-adm-urg-eleve'],
        moyen:    ['Moyenne',  'sug-adm-urg-moy'],
        faible:   ['Faible',   'sug-adm-urg-faible'],
    };
    const stLabels = {
        nouvelle:  'Nouvelle', etudiee: 'Étudiée', planifiee: 'Planifiée',
        en_dev:    'En développement', livree: 'Livrée', refusee: 'Refusée',
    };
    const svcLabels = {
        aide_soignant: 'Aide-soignant', infirmier: 'Infirmier', infirmier_chef: 'Infirmier chef',
        animation: 'Animation', cuisine: 'Cuisine', technique: 'Technique',
        admin: 'Admin', rh: 'RH', direction: 'Direction', qualite: 'Qualité', autre: 'Autre',
    };
    const catLabels = {
        formulaire: 'Formulaire', fonctionnalite: 'Fonctionnalité', amelioration: 'Amélioration',
        alerte: 'Alerte', bug: 'Bug', question: 'Question',
    };
    const benefLabels = {
        gain_temps: 'Gain de temps', reduction_erreurs: 'Réduction d\'erreurs',
        tracabilite: 'Traçabilité', conformite: 'Conformité',
        confort_resident: 'Confort résident', securite: 'Sécurité',
    };
    const freqLabels = {
        multi_jour: 'Plusieurs fois/jour', quotidien: 'Quotidien',
        hebdo: 'Hebdomadaire', mensuel: 'Mensuel', ponctuel: 'Ponctuel',
    };

    // Init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPage);
    } else {
        initPage();
    }

    function initPage() {
        // Stat cards cliquables
        document.querySelectorAll('.sug-adm-stat').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.sug-adm-stat').forEach(x => x.classList.remove('active'));
                card.classList.add('active');
                state.statut = card.dataset.filterStatut || '';
                state.urgence = card.dataset.filterUrgence || '';
                // Reflect in selects
                const selSt = document.getElementById('sugAdmFilterUrgence');
                if (selSt) selSt.value = state.urgence || '';
                reload();
            });
        });

        // Filtres
        document.getElementById('sugAdmSearch')?.addEventListener('input', debounce(() => {
            state.search = document.getElementById('sugAdmSearch').value.trim();
            reload();
        }, 300));
        document.getElementById('sugAdmFilterService')?.addEventListener('change', e => { state.service = e.target.value; reload(); });
        document.getElementById('sugAdmFilterCategorie')?.addEventListener('change', e => { state.categorie = e.target.value; reload(); });
        document.getElementById('sugAdmFilterUrgence')?.addEventListener('change', e => { state.urgence = e.target.value; reload(); });

        // Modal Bootstrap
        const modalEl = document.getElementById('sugAdmModal');
        if (modalEl && window.bootstrap) {
            bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', () => { currentDetailId = null; });
        }

        // Delete button du footer (lié une seule fois, pas re-rendu)
        document.getElementById('sugAdmDelete')?.addEventListener('click', deleteSuggestion);

        // Flag switch
        document.getElementById('sugAdmFlag')?.addEventListener('change', async e => {
            const val = e.target.value;
            const r = await window.adminApiPost('admin_save_config', { values: { allow_feature_requests: val } });
            if (r.success) window.toast('Configuration enregistrée', 'success');
        });

        reload();
    }

    async function reload() {
        const tbody = document.getElementById('sugAdmTbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Chargement…</td></tr>';

        const r = await window.adminApiPost('admin_list_suggestions', state);
        if (!r.success) { tbody.innerHTML = '<tr><td colspan="9" class="text-danger py-3">Erreur de chargement</td></tr>'; return; }

        currentList = r.suggestions || [];
        renderTable(currentList);
        renderTop(r.top || []);
    }

    function renderTable(rows) {
        const tbody = document.getElementById('sugAdmTbody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Aucune suggestion</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(s => {
            const urg = urgLabels[s.urgence] || ['—', 'sug-adm-urg-moy'];
            const author = (s.auteur_prenom || '') + ' ' + (s.auteur_nom || '');
            const initials = ((s.auteur_prenom || '')[0] || '') + ((s.auteur_nom || '')[0] || '');
            return `
                <tr data-sug-row="${escapeHtml(s.id)}">
                    <td><span class="sug-adm-ref">${escapeHtml(s.reference_code)}</span></td>
                    <td><span class="sug-adm-title">${escapeHtml(s.titre)}</span></td>
                    <td>
                        <div class="sug-adm-author">
                            <span class="sug-adm-avatar">${escapeHtml(initials.toUpperCase())}</span>
                            <span>${escapeHtml(author.trim() || '—')}</span>
                        </div>
                    </td>
                    <td>${escapeHtml(svcLabels[s.service] || s.service)}</td>
                    <td>${escapeHtml(catLabels[s.categorie] || s.categorie)}</td>
                    <td><span class="sug-adm-badge ${urg[1]}">${urg[0]}</span></td>
                    <td><span class="sug-adm-badge sug-adm-st-${s.statut}">${escapeHtml(stLabels[s.statut] || s.statut)}</span></td>
                    <td><span class="sug-adm-votes"><i class="bi bi-hand-thumbs-up"></i> ${s.votes_count|0}</span></td>
                    <td class="text-muted small">${formatDate(s.created_at)}</td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-sug-row]').forEach(tr => {
            tr.addEventListener('click', () => openDetail(tr.dataset.sugRow));
        });
    }

    function renderTop(top) {
        const el = document.getElementById('sugAdmTop');
        if (!el) return;
        if (!top.length) { el.innerHTML = '<div class="text-muted small">Aucune suggestion ouverte.</div>'; return; }
        el.innerHTML = top.map(s => `
            <div class="sug-adm-top-item" data-sug-open="${escapeHtml(s.id)}">
                <span class="sug-adm-top-votes"><i class="bi bi-hand-thumbs-up"></i> ${s.votes_count|0}</span>
                <div><strong>${escapeHtml(s.titre)}</strong></div>
                <div class="small text-muted">${escapeHtml(s.reference_code)} · ${escapeHtml(stLabels[s.statut] || s.statut)}</div>
            </div>`).join('');
        el.querySelectorAll('[data-sug-open]').forEach(e => e.addEventListener('click', () => openDetail(e.dataset.sugOpen)));
    }

    async function openDetail(id) {
        currentDetailId = id;
        const body = document.getElementById('sugAdmModalBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
        bsModal?.show();

        const r = await window.adminApiPost('admin_get_suggestion', { id });
        if (!r.success) { body.innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>'; return; }
        renderDetail(r);
    }

    function renderDetail(r) {
        const s = r.suggestion;
        const body = document.getElementById('sugAdmModalBody');
        const urg = urgLabels[s.urgence] || ['—', 'sug-adm-urg-moy'];
        const benArr = s.benefices ? s.benefices.split(',').filter(Boolean) : [];
        const author = (s.auteur_prenom || '') + ' ' + (s.auteur_nom || '');
        document.getElementById('sugAdmModalTitle').innerHTML = `<i class="bi bi-lightbulb"></i> ${escapeHtml(s.reference_code)}`;

        const benefHtml = benArr.length
            ? `<div class="d-flex flex-wrap gap-1">${benArr.map(b => `<span class="badge bg-light text-dark border">${escapeHtml(benefLabels[b] || b)}</span>`).join('')}</div>`
            : '<span class="text-muted small">Aucun</span>';

        const attachHtml = (r.attachments || []).length
            ? r.attachments.map(a => {
                const url = `/newspocspace/admin/api.php?action=admin_download_suggestion_attachment&id=${encodeURIComponent(a.id)}`;
                const ico = a.kind === 'photo' ? 'bi-image' : a.kind === 'audio' ? 'bi-music-note' : 'bi-file-earmark';
                const audio = a.kind === 'audio' ? `<audio controls class="w-100 mt-1" src="${url}" preload="metadata"></audio>` : '';
                return `<div class="border rounded p-2 mb-1 small"><i class="bi ${ico}"></i> <a href="${url}" target="_blank">${escapeHtml(a.original_name)}</a> <span class="text-muted">(${Math.round(a.size_bytes/1024)} Ko)</span>${audio}</div>`;
            }).join('')
            : '<span class="text-muted small">Aucune pièce jointe</span>';

        const commentsHtml = (r.comments || []).map(c => {
            const internal = c.visibility === 'admin_only';
            const cls = internal ? 'sug-adm-comment internal' : (c.role === 'admin' ? 'sug-adm-comment admin' : 'sug-adm-comment');
            const who = ((c.prenom || '') + ' ' + (c.nom || '')).trim() || '—';
            return `
                <div class="${cls}">
                    <div class="sug-adm-comment-head">
                        <span><strong>${escapeHtml(who)}</strong>${c.role === 'admin' ? ' · Équipe' : ''}${internal ? ' · <span class="text-warning fw-bold">Interne</span>' : ''}</span>
                        <span>${formatDateTime(c.created_at)}</span>
                    </div>
                    <div class="sug-adm-comment-body">${c.content || ''}</div>
                </div>`;
        }).join('') || '<div class="text-muted small">Aucun commentaire</div>';

        const historyHtml = (r.history || []).map(h => {
            const who = ((h.prenom || '') + ' ' + (h.nom || '')).trim();
            return `
                <div class="small mb-1">
                    <strong>${escapeHtml(stLabels[h.new_statut] || h.new_statut)}</strong>
                    ${who ? ' · ' + escapeHtml(who) : ''}
                    <span class="text-muted">· ${formatDateTime(h.created_at)}</span>
                    ${h.motif ? '<div class="text-muted ps-3">' + h.motif + '</div>' : ''}
                </div>`;
        }).join('');

        const votersHtml = (r.voters || []).length
            ? r.voters.slice(0, 12).map(v => {
                const ini = ((v.prenom || '')[0] || '') + ((v.nom || '')[0] || '');
                return `<span class="badge bg-light text-dark border me-1 mb-1"><span class="sug-adm-avatar" style="width:18px;height:18px;font-size:.6rem;margin-right:4px;vertical-align:middle">${escapeHtml(ini.toUpperCase())}</span>${escapeHtml(v.prenom + ' ' + v.nom)}</span>`;
            }).join('') + (r.voters.length > 12 ? `<span class="text-muted small"> + ${r.voters.length - 12} autres</span>` : '')
            : '<span class="text-muted small">Aucun vote</span>';

        body.innerHTML = `
            <h3 class="h5">${escapeHtml(s.titre)}</h3>
            <div class="d-flex flex-wrap gap-2 mb-3 small text-muted align-items-center">
                <span><i class="bi bi-person"></i> ${escapeHtml(author.trim() || '—')}</span>
                <span>·</span>
                <span>${escapeHtml(svcLabels[s.service] || s.service)}</span>
                <span>·</span>
                <span>${escapeHtml(catLabels[s.categorie] || s.categorie)}</span>
                <span>·</span>
                ${s.frequence ? `<span>${escapeHtml(freqLabels[s.frequence] || s.frequence)}</span><span>·</span>` : ''}
                <span class="sug-adm-badge ${urg[1]}">${urg[0]}</span>
                <span class="sug-adm-badge sug-adm-st-${s.statut}">${escapeHtml(stLabels[s.statut] || s.statut)}</span>
                <span class="ms-auto"><i class="bi bi-hand-thumbs-up"></i> <strong>${s.votes_count|0}</strong> votes · <i class="bi bi-chat-dots"></i> ${s.comments_count|0}</span>
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Description</div>
                <div class="sug-adm-desc-box">${escapeHtml(s.description)}</div>
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Bénéfices attendus</div>
                ${benefHtml}
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Pièces jointes</div>
                ${attachHtml}
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Votants</div>
                ${votersHtml}
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Changer le statut / suivi</div>
                <div class="sug-adm-status-form">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="small mb-1">Statut</label>
                            <select id="sugAdmStatut">
                                ${Object.entries(stLabels).map(([k, v]) => `<option value="${k}" ${k === s.statut ? 'selected' : ''}>${v}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small mb-1">Sprint / Release</label>
                            <input type="text" id="sugAdmSprint" value="${escapeHtml(s.sprint || '')}" placeholder="Ex. Sprint 12">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button id="sugAdmSaveStatut" class="btn btn-sm btn-dark w-100"><i class="bi bi-check-lg"></i> Enregistrer</button>
                        </div>
                        <div class="col-12">
                            <label class="small mb-1">Motif / contexte (visible côté auteur)</label>
                            <div class="sug-rte-wrap">
                                <div class="sug-rte-toolbar">
                                    <button type="button" class="sug-rte-btn" data-cmd="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="sug-rte-btn" data-cmd="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="sug-rte-btn" data-cmd="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
                                    <button type="button" class="sug-rte-btn" data-cmd="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
                                    <span class="sug-rte-sep"></span>
                                    <button type="button" class="sug-rte-btn" data-cmd="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="sug-rte-btn" data-cmd="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                                    <span class="sug-rte-sep"></span>
                                    <button type="button" class="sug-rte-btn" id="sugAdmMotifEmojiBtn" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                                </div>
                                <div class="sug-rte-editor" contenteditable="true" id="sugAdmMotif" data-placeholder="Ex. raison du refus, contexte du sprint…">${s.motif_admin || ''}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Historique</div>
                ${historyHtml}
            </div>

            <div class="sug-adm-section">
                <div class="sug-adm-section-title">Commentaires</div>
                <div class="sug-adm-comments mb-2">${commentsHtml}</div>
                <div class="sug-rte-wrap">
                    <div class="sug-rte-toolbar">
                        <button type="button" class="sug-rte-btn" data-cmd="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
                        <button type="button" class="sug-rte-btn" data-cmd="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
                        <button type="button" class="sug-rte-btn" data-cmd="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
                        <button type="button" class="sug-rte-btn" data-cmd="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
                        <span class="sug-rte-sep"></span>
                        <button type="button" class="sug-rte-btn" data-cmd="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
                        <button type="button" class="sug-rte-btn" data-cmd="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
                        <span class="sug-rte-sep"></span>
                        <button type="button" class="sug-rte-btn" id="sugAdmEmojiBtn" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                    </div>
                    <div class="sug-rte-editor" contenteditable="true" id="sugAdmComment" data-placeholder="Ajouter un commentaire…"></div>
                    <div class="sug-rte-footer">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sugAdmCommentInternal">
                            <label class="form-check-label small" for="sugAdmCommentInternal">Commentaire interne (visible admin uniquement)</label>
                        </div>
                        <button id="sugAdmSendComment" class="btn btn-sm btn-dark ms-auto"><i class="bi bi-send"></i> Envoyer</button>
                    </div>
                </div>
            </div>

        `;

        document.getElementById('sugAdmSaveStatut')?.addEventListener('click', saveStatut);
        document.getElementById('sugAdmSendComment')?.addEventListener('click', sendComment);
        bindRte(document.getElementById('sugAdmComment'));
        bindRte(document.getElementById('sugAdmMotif'));
        initEmojiPicker(document.getElementById('sugAdmEmojiBtn'), document.getElementById('sugAdmComment'));
        initEmojiPicker(document.getElementById('sugAdmMotifEmojiBtn'), document.getElementById('sugAdmMotif'));
    }

    async function saveStatut() {
        if (!currentDetailId) return;
        const btn = document.getElementById('sugAdmSaveStatut');
        btn.disabled = true;
        try {
            const statut = document.getElementById('sugAdmStatut').value;
            const sprint = document.getElementById('sugAdmSprint').value.trim();
            const motifEd = document.getElementById('sugAdmMotif');
            const motif = (motifEd?.textContent || '').trim() ? (motifEd.innerHTML || '').trim() : '';
            const r = await window.adminApiPost('admin_update_suggestion_statut', { id: currentDetailId, statut, sprint, motif });
            if (!r.success) { window.toast(r.message || 'Erreur', 'error'); return; }
            window.toast('Statut mis à jour', 'success');
            await openDetail(currentDetailId); // recharge
            reload();
        } finally { btn.disabled = false; }
    }

    async function sendComment() {
        if (!currentDetailId) return;
        const ed = document.getElementById('sugAdmComment');
        const content = (ed?.innerHTML || '').trim();
        const plain = (ed?.textContent || '').trim();
        if (plain.length < 2) return;
        const internal = document.getElementById('sugAdmCommentInternal').checked ? 1 : 0;
        const btn = document.getElementById('sugAdmSendComment');
        btn.disabled = true;
        try {
            const r = await window.adminApiPost('admin_add_suggestion_comment', { id: currentDetailId, content, internal });
            if (!r.success) { window.toast(r.message || 'Erreur', 'error'); return; }
            ed.innerHTML = '';
            await openDetail(currentDetailId);
        } finally { btn.disabled = false; }
    }

    // ── Rich-text toolbar (execCommand) ──
    function bindRte(editor) {
        if (!editor) return;
        const toolbar = editor.parentElement.querySelector('.sug-rte-toolbar');
        toolbar?.addEventListener('mousedown', e => {
            const btn = e.target.closest('.sug-rte-btn');
            if (!btn) return;
            const cmd = btn.dataset.cmd;
            if (!cmd) return; // Emoji ou autre bouton sans commande — ignore
            e.preventDefault();
            editor.focus();
            document.execCommand(cmd, false, null);
        });
        // Paste → plain text
        editor.addEventListener('paste', e => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            document.execCommand('insertText', false, text);
        });
    }

    // ── Emoji picker (zkr-emoji style, mêmes classes CSS que alertes.php) ──
    const EMOJI_CATS = {
        smileys:    { icon: '😀', label: 'Smileys', emojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤌','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤙','💪','🙏'] },
        animals:    { icon: '🐻', label: 'Animaux & Nature', emojis: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🐺','🐴','🦄','🐝','🦋','🐌','🐞','🐢','🐍','🐙','🐠','🐟','🐬','🐳','🐋','🦈','🐘','🦒','🌲','🌳','🌴','🌱','🌿','🍀','💐','🌷','🌹','🌺','🌸','🌼','🌻','☀️','🌤️','⛅','🌈','❄️','🔥','💧','🌊'] },
        food:       { icon: '🍔', label: 'Nourriture', emojis: ['🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🍒','🍑','🥭','🍍','🥝','🍅','🥑','🥦','🥒','🌶️','🌽','🥕','🥔','🥐','🍞','🥖','🧀','🥚','🍳','🥞','🥓','🍗','🍖','🌭','🍔','🍟','🍕','🥪','🌮','🥗','🍝','🍜','🍣','🍱','🍦','🍰','🎂','🍩','🍪','☕','🍵','🥤','🍺','🍷','🥂'] },
        activities: { icon: '⚽', label: 'Activités', emojis: ['⚽','🏀','🎾','🏐','🎱','🏓','🏸','⛳','🏹','🎣','🥊','🥋','🏋️','🧘','🏊','🏄','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🎗️','🎪','🎭','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🎷','🎸','🎻','🎲','🎯','🎮','🧩'] },
        objects:    { icon: '💡', label: 'Objets', emojis: ['📱','💻','⌨️','🖥️','🖨️','📷','📹','📞','📺','📻','⏰','💡','🔦','💸','💰','💳','💎','🔧','🔨','⚙️','🔪','🛡️','🩹','🩺','💊','💉','🧬','🌡️','🧹','🧼','🔑','🚪','🛋️','🛏️','🎁','🎈','🎀','🎊','🎉','✉️','📦','📋','📁','📰','📓','📕','📗','📘','📚','🔖','📎','✂️','📌','📝','✏️','🔍','🔒'] },
        symbols:    { icon: '❤️', label: 'Symboles', emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','⚠️','🚨','🔴','🟡','🟢','🔵','📌','✅','❌','⭐','🌟','💯','❗','❓','‼️','⁉️','🔔','📢','📣','♻️','🚫','⛔','🛑'] },
    };
    let emojiPickerEl = null;
    let emojiCurrentCat = 'smileys';
    let emojiTargetEditor = null;
    let emojiOutsideHandler = null;

    function initEmojiPicker(trigger, editor) {
        if (!trigger || !editor) return;
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            emojiTargetEditor = editor;
            if (emojiPickerEl) {
                closeEmojiPicker();
            } else {
                openEmojiPicker(trigger);
            }
        });
    }

    function openEmojiPicker(anchor) {
        emojiPickerEl = createEmojiPicker();
        document.body.appendChild(emojiPickerEl);
        // Positionnement sous le bouton
        const rect = anchor.getBoundingClientRect();
        emojiPickerEl.style.left = Math.max(10, rect.left - 200) + 'px';
        emojiPickerEl.style.top  = (rect.bottom + 6) + 'px';
        // Fermer si clic ailleurs
        emojiOutsideHandler = (e) => {
            if (!e.target.closest('.zkr-emoji-picker') && e.target !== anchor && !anchor.contains(e.target)) {
                closeEmojiPicker();
            }
        };
        setTimeout(() => document.addEventListener('click', emojiOutsideHandler), 0);
    }

    function closeEmojiPicker() {
        emojiPickerEl?.remove();
        emojiPickerEl = null;
        if (emojiOutsideHandler) document.removeEventListener('click', emojiOutsideHandler);
        emojiOutsideHandler = null;
    }

    function createEmojiPicker() {
        const div = document.createElement('div');
        div.className = 'zkr-emoji-picker';
        div.style.position = 'fixed';
        div.style.zIndex = '10000';
        const tabs = Object.entries(EMOJI_CATS).map(([k, cat]) =>
            `<button class="zkr-emoji-tab ${k === emojiCurrentCat ? 'active' : ''}" data-cat="${k}" title="${cat.label}">${cat.icon}</button>`
        ).join('');
        div.innerHTML = `
            <div class="zkr-emoji-header">
                <div class="zkr-emoji-tabs">${tabs}</div>
                <div class="zkr-emoji-search">
                    <input type="text" class="zkr-emoji-search-input form-control" placeholder="Rechercher…">
                </div>
            </div>
            <div class="zkr-emoji-body">
                <div class="zkr-emoji-category-label">${EMOJI_CATS[emojiCurrentCat].label}</div>
                <div class="zkr-emoji-grid"></div>
            </div>`;
        div.querySelectorAll('.zkr-emoji-tab').forEach(t => {
            t.addEventListener('click', e => {
                e.preventDefault();
                emojiCurrentCat = t.dataset.cat;
                div.querySelectorAll('.zkr-emoji-tab').forEach(x => x.classList.remove('active'));
                t.classList.add('active');
                div.querySelector('.zkr-emoji-search-input').value = '';
                loadEmojiCategory(div, emojiCurrentCat);
            });
        });
        div.querySelector('.zkr-emoji-search-input').addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            if (!q) { loadEmojiCategory(div, emojiCurrentCat); return; }
            const all = Object.values(EMOJI_CATS).flatMap(c => c.emojis);
            div.querySelector('.zkr-emoji-category-label').textContent = 'Résultats';
            const grid = div.querySelector('.zkr-emoji-grid');
            grid.innerHTML = all.filter(em => em.includes(q)).map(em =>
                `<button class="zkr-emoji-btn" data-emoji="${em}">${em}</button>`
            ).join('') || '<div style="grid-column:1/-1;text-align:center;padding:18px;color:#999;font-size:.85rem">Aucun résultat</div>';
        });
        div.addEventListener('click', e => {
            const btn = e.target.closest('.zkr-emoji-btn');
            if (!btn || !emojiTargetEditor) return;
            emojiTargetEditor.focus();
            document.execCommand('insertText', false, btn.dataset.emoji);
            closeEmojiPicker();
        });
        loadEmojiCategory(div, emojiCurrentCat);
        return div;
    }

    function loadEmojiCategory(div, cat) {
        const grid = div.querySelector('.zkr-emoji-grid');
        div.querySelector('.zkr-emoji-category-label').textContent = EMOJI_CATS[cat].label;
        grid.innerHTML = EMOJI_CATS[cat].emojis.map(em =>
            `<button class="zkr-emoji-btn" data-emoji="${em}">${em}</button>`
        ).join('');
    }

    async function deleteSuggestion() {
        if (!currentDetailId) return;
        if (!confirm('Supprimer définitivement cette suggestion et toutes ses données ?')) return;
        const r = await window.adminApiPost('admin_delete_suggestion', { id: currentDetailId });
        if (!r.success) { window.toast(r.message || 'Erreur', 'error'); return; }
        window.toast('Suggestion supprimée', 'success');
        bsModal?.hide();
        reload();
    }

    // Helpers
    function escapeHtml(str) {
        if (str == null) return '';
        return String(str).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    }
    function formatDate(d) { if (!d) return ''; return new Date(d.replace(' ','T')).toLocaleDateString('fr-CH'); }
    function formatDateTime(d) { if (!d) return ''; return new Date(d.replace(' ','T')).toLocaleString('fr-CH'); }
    function debounce(fn, ms) { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); }; }
})();
