<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$sondageInitList = Db::fetchAll(
    "SELECT s.*, u.prenom, u.nom,
            (SELECT COUNT(*) FROM sondage_questions WHERE sondage_id = s.id) AS nb_questions,
            (SELECT COUNT(DISTINCT user_id) FROM sondage_reponses WHERE sondage_id = s.id) AS nb_repondants
     FROM sondages s
     LEFT JOIN users u ON u.id = s.created_by
     ORDER BY s.created_at DESC
     LIMIT 20"
);
?>
<!-- Admin Sondages Page -->
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0"><i class="bi bi-clipboard2-check"></i> Sondages</h5>
      <small class="text-muted">Créez et gérez les sondages pour les collaborateurs</small>
    </div>
    <button class="btn btn-primary btn-sm" id="btnNewSondage">
      <i class="bi bi-plus-lg"></i> Nouveau sondage
    </button>
  </div>

  <div class="row g-3">
    <!-- LEFT: List -->
    <div class="col-md-5 col-lg-4">
      <div class="card">
        <div class="card-header py-2">
          <div class="sondage-tabs" id="sondageFilterTabs">
            <div class="sondage-tabs-slider"></div>
            <button class="sondage-tab active" data-statut="">Tous</button>
            <button class="sondage-tab" data-statut="brouillon">Brouillon</button>
            <button class="sondage-tab" data-statut="ouvert">Ouvert</button>
            <button class="sondage-tab" data-statut="ferme">Fermé</button>
          </div>
        </div>
        <div id="sondageListContainer" class="admin-sondage-list">
          <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Detail / Edit -->
    <div class="col-md-7 col-lg-8">
      <div class="card" id="sondageDetailCard">
        <div class="card-body text-center text-muted py-5">
          <i class="bi bi-clipboard2-check admin-sondage-empty-icon"></i>
          <p class="mt-2">Sélectionnez un sondage ou créez-en un nouveau</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.admin-sondage-list { max-height: calc(100vh - 260px); overflow-y: auto; }
.admin-sondage-empty-icon { font-size: 3rem; opacity: 0.3; }
.admin-sondage-body { max-height: calc(100vh - 320px); overflow-y: auto; }

/* ── Sliding pill toggle ── */
.sondage-tabs {
  display: flex;
  position: relative;
  background: var(--cl-bg);
  border-radius: 10px;
  padding: 3px;
  gap: 0;
}
.sondage-tabs-slider {
  position: absolute;
  top: 3px;
  left: 3px;
  width: calc(25% - 3px);
  height: calc(100% - 6px);
  background: var(--cl-surface);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 0;
  pointer-events: none;
}
.sondage-tab {
  flex: 1;
  background: none;
  border: none;
  padding: 6px 8px;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--cl-text-secondary);
  border-radius: 8px;
  cursor: pointer;
  transition: color 0.25s ease;
  position: relative;
  z-index: 1;
  text-align: center;
  white-space: nowrap;
}
.sondage-tab:hover { color: var(--cl-text); }
.sondage-tab.active {
  color: var(--cl-text);
  font-weight: 600;
}
.sondage-item {
  padding: 12px 16px;
  border-bottom: 1px solid var(--cl-border);
  cursor: pointer;
  transition: background var(--cl-transition);
}
.sondage-item:hover { background: var(--cl-accent-bg); }
.sondage-item.active { background: var(--cl-accent-bg); border-left: 4px solid var(--cl-accent); padding-left: 12px; }
.sondage-item-title { font-weight: 500; font-size: 0.95rem; }
.sondage-item-meta { font-size: 0.8rem; color: var(--cl-text-muted); margin-top: 2px; display: flex; justify-content: space-between; }
.result-bar { height: 28px; border-radius: 6px; background: #F2EDE8; position: relative; overflow: hidden; margin-bottom: 6px; }
.result-bar-fill { height: 100%; border-radius: 6px; transition: width 0.4s ease; }
.result-bar-label { position: absolute; left: 10px; top: 4px; font-size: 0.8rem; font-weight: 500; color: var(--cl-text); }
.result-bar-count { position: absolute; right: 10px; top: 4px; font-size: 0.8rem; font-weight: 600; color: var(--cl-text); }
</style>

<script<?= nonce() ?>>
(function() {
    const sondageInitData = <?= json_encode(array_values($sondageInitList), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let selectedId = null;
    let currentStatut = '';

    // ─── Init ───
    document.addEventListener('DOMContentLoaded', () => {
        // Auto-select sondage if returning from editor
        const urlParams = new URLSearchParams(window.location.search);
        const autoSelect = urlParams.get('selected');
        if (autoSelect) selectedId = autoSelect;

        renderList(sondageInitData);

        // Auto-load selected sondage after list is rendered
        if (autoSelect) loadDetail(autoSelect);

        // "Nouveau sondage" button
        const btnNew = document.getElementById('btnNewSondage');
        if (btnNew) btnNew.addEventListener('click', () => AdminURL.go('sondage-edit'));

        // Delegate action buttons inside detail card
        const detailCard = document.getElementById('sondageDetailCard');
        if (detailCard) {
            detailCard.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;
                const action = btn.dataset.action;
                if (action === 'toggle') window._sondageToggle(btn.dataset.statut);
                else if (action === 'edit') window._sondageEdit();
                else if (action === 'delete') window._sondageDelete();
            });
        }

        // Sliding pill toggle for status filter
        const tabs = document.querySelectorAll('.sondage-tab');
        const slider = document.querySelector('.sondage-tabs-slider');
        tabs.forEach((btn, idx) => {
            btn.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (slider) slider.style.transform = 'translateX(' + (idx * 100) + '%)';
                currentStatut = btn.dataset.statut;
                loadList();
            });
        });

        // Hijack topbar search for sondages
        const topInput = document.getElementById('topbarSearchInput');
        if (topInput) {
            topInput.placeholder = 'Rechercher un sondage...';
            topInput.addEventListener('input', debounce(loadList, 300));
        }
    });

    // ─── Render list ───
    function renderList(list) {
        const container = document.getElementById('sondageListContainer');

        if (!list || list.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted">Aucun sondage</div>';
            return;
        }

        container.innerHTML = list.map(s => {
            const date = new Date(s.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
            const statusBadge = s.statut === 'ouvert'
                ? '<span class="badge bg-success">Ouvert</span>'
                : s.statut === 'ferme'
                    ? '<span class="badge bg-secondary">Fermé</span>'
                    : '<span class="badge bg-warning text-dark">Brouillon</span>';
            return `<div class="sondage-item ${s.id === selectedId ? 'active' : ''}" data-id="${escapeHtml(s.id)}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="sondage-item-title">${escapeHtml(s.titre)}</div>
                    ${statusBadge}
                </div>
                <div class="sondage-item-meta">
                    <span>${escapeHtml(s.prenom || '')} ${escapeHtml(s.nom || '')} · ${date}</span>
                    <span>${s.nb_questions} Q · ${s.nb_repondants} rép.</span>
                </div>
            </div>`;
        }).join('');

        container.querySelectorAll('.sondage-item').forEach(item => {
            item.addEventListener('click', () => loadDetail(item.dataset.id));
        });
    }

    // ─── Load list ───
    async function loadList() {
        const container = document.getElementById('sondageListContainer');
        container.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></div>';

        const topInput = document.getElementById('topbarSearchInput');
        const result = await adminApiPost('admin_get_sondages', {
            search: topInput ? topInput.value : '',
            statut: currentStatut,
        });

        if (!result.success) {
            container.innerHTML = '<div class="text-center py-4 text-danger">Erreur de chargement</div>';
            return;
        }

        renderList(result.list);
    }

    // ─── Load detail ───
    async function loadDetail(id) {
        selectedId = id;
        // Highlight
        document.querySelectorAll('.sondage-item').forEach(el => el.classList.toggle('active', el.dataset.id === id));

        const card = document.getElementById('sondageDetailCard');
        card.innerHTML = '<div class="card-body text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';

        const result = await adminApiPost('admin_get_sondage', { id });
        if (!result.success) {
            card.innerHTML = '<div class="card-body text-danger">Erreur de chargement</div>';
            return;
        }

        const s = result.sondage;
        const questions = result.questions || [];
        const results = result.results || {};
        const nbR = result.nb_repondants || 0;

        const statusBadge = s.statut === 'ouvert'
            ? '<span class="badge bg-success">Ouvert</span>'
            : s.statut === 'ferme'
                ? '<span class="badge bg-secondary">Fermé</span>'
                : '<span class="badge bg-warning text-dark">Brouillon</span>';

        // Action buttons based on status
        let actions = '';
        if (s.statut === 'brouillon') {
            actions = `
                <button class="btn btn-sm me-1" style="background:#bcd2cb;color:#2d4a43" data-action="toggle" data-statut="ouvert"><i class="bi bi-play-fill"></i> Ouvrir</button>
                <button class="btn btn-outline-dark btn-sm me-1" data-action="edit"><i class="bi bi-pencil"></i> Modifier</button>
                <button class="btn btn-outline-danger btn-sm" data-action="delete"><i class="bi bi-trash"></i></button>`;
        } else if (s.statut === 'ouvert') {
            actions = `
                <button class="btn btn-sm me-1" style="background:#D4C4A8;color:#6B5B3E" data-action="toggle" data-statut="ferme"><i class="bi bi-stop-fill"></i> Fermer</button>
                <button class="btn btn-outline-danger btn-sm" data-action="delete"><i class="bi bi-trash"></i></button>`;
        } else {
            actions = `
                <button class="btn btn-sm me-1" style="background:#bcd2cb;color:#2d4a43" data-action="toggle" data-statut="ouvert"><i class="bi bi-play-fill"></i> Réouvrir</button>
                <button class="btn btn-outline-danger btn-sm" data-action="delete"><i class="bi bi-trash"></i></button>`;
        }

        // Questions + results
        let questionsHtml = '';
        questions.forEach((q, idx) => {
            const qResults = results[q.id] || [];
            let resultHtml = '';

            if (q.type === 'texte_libre') {
                if (qResults.length > 0) {
                    resultHtml = '<div class="mt-2">' + qResults.map(r =>
                        `<div class="border rounded p-2 mb-1 bg-white small">
                            ${s.is_anonymous == 0 ? '<strong>' + escapeHtml(r.prenom) + ' ' + escapeHtml(r.nom) + ':</strong> ' : ''}
                            ${escapeHtml(r.reponse)}
                        </div>`
                    ).join('') + '</div>';
                } else {
                    resultHtml = '<div class="text-muted small mt-2">Aucune réponse</div>';
                }
            } else {
                // Count per option
                const options = q.options || [];
                const counts = {};
                options.forEach(o => counts[o] = 0);

                qResults.forEach(r => {
                    if (q.type === 'choix_multiple') {
                        try {
                            const vals = JSON.parse(r.reponse);
                            vals.forEach(v => { if (counts[v] !== undefined) counts[v]++; });
                        } catch(e) {
                            if (counts[r.reponse] !== undefined) counts[r.reponse]++;
                        }
                    } else {
                        if (counts[r.reponse] !== undefined) counts[r.reponse]++;
                    }
                });

                const total = Math.max(1, qResults.length);
                resultHtml = '<div class="mt-2">' + options.map(o => {
                    const c = counts[o] || 0;
                    const pct = Math.round(c / total * 100);
                    // Beige that intensifies with percentage: light beige → warm brown-beige
                    const l = 90 - (pct * 0.40);   // lightness: 90% → 50%
                    const s = 15 + (pct * 0.20);    // saturation: 15% → 35%
                    const barBg = `hsl(35, ${s}%, ${l}%)`;
                    return `<div class="result-bar mb-1">
                        <div class="result-bar-fill" style="width:${pct}%;background:${barBg}"></div>
                        <span class="result-bar-label">${escapeHtml(o)}</span>
                        <span class="result-bar-count">${c} (${pct}%)</span>
                    </div>`;
                }).join('') + '</div>';
            }

            questionsHtml += `
                <div class="mb-3 p-3 border rounded bg-white">
                    <div class="d-flex justify-content-between">
                        <strong class="small">${idx + 1}. ${escapeHtml(q.question)}</strong>
                        <span class="badge bg-light text-dark">${q.type === 'choix_unique' ? 'Choix unique' : q.type === 'choix_multiple' ? 'Choix multiple' : 'Texte libre'}</span>
                    </div>
                    ${resultHtml}
                </div>`;
        });

        card.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-1">${escapeHtml(s.titre)} ${statusBadge}</h5>
                    <small class="text-muted">
                        Par ${escapeHtml(s.prenom || '')} ${escapeHtml(s.nom || '')}
                        · ${new Date(s.created_at).toLocaleDateString('fr-FR')}
                        · ${nbR} répondant${nbR > 1 ? 's' : ''}
                        ${s.is_anonymous == 1 ? ' · <i class="bi bi-incognito"></i> Anonyme' : ''}
                    </small>
                </div>
                <div class="d-flex">${actions}</div>
            </div>
            <div class="card-body admin-sondage-body">
                ${s.description ? '<div class="text-muted mb-3" style="font-size:.9rem;line-height:1.6">' + s.description + '</div>' : ''}
                ${questions.length > 0 ? questionsHtml : '<div class="text-muted text-center py-3">Aucune question</div>'}
            </div>`;
    }

    // ─── Toggle status ───
    window._sondageToggle = async function(statut) {
        if (!selectedId) return;
        const labels = { ouvert: 'ouvrir', ferme: 'fermer', brouillon: 'remettre en brouillon' };
        if (!confirm('Voulez-vous ' + (labels[statut] || statut) + ' ce sondage ?')) return;

        const r = await adminApiPost('admin_toggle_sondage', { id: selectedId, statut });
        if (r.success) {
            toast('Sondage mis à jour', 'success');
            loadList();
            loadDetail(selectedId);
        } else {
            toast(r.message || 'Erreur', 'error');
        }
    };

    // ─── Delete ───
    window._sondageDelete = async function() {
        if (!selectedId) return;
        if (!confirm('Supprimer définitivement ce sondage et ses réponses ?')) return;

        const r = await adminApiPost('admin_delete_sondage', { id: selectedId });
        if (r.success) {
            toast('Sondage supprimé', 'success');
            selectedId = null;
            document.getElementById('sondageDetailCard').innerHTML = '<div class="card-body text-center text-muted py-5"><i class="bi bi-clipboard2-check admin-sondage-empty-icon"></i><p class="mt-2">Sélectionnez un sondage</p></div>';
            loadList();
        } else {
            toast(r.message || 'Erreur', 'error');
        }
    };

    // ─── Edit — navigate to editor page ───
    window._sondageEdit = function() {
        if (!selectedId) return;
        window.location.href = AdminURL.page('sondage-edit', selectedId);
    };

    // ─── Utils ───
    function debounce(fn, delay) {
        let t;
        return function(...args) { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

})();
</script>
