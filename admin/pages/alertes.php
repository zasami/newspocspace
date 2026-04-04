<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$alerts = Db::fetchAll(
    "SELECT a.*, u.prenom AS creator_prenom, u.nom AS creator_nom,
            (SELECT COUNT(*) FROM alert_reads ar WHERE ar.alert_id = a.id) AS read_count,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) AS total_users
     FROM alerts a
     JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC
     LIMIT 30"
);
$modules   = Db::fetchAll("SELECT id, code, nom FROM modules ORDER BY code");
$fonctions = Db::fetchAll("SELECT code, nom FROM fonctions ORDER BY code");
?>
<link rel="stylesheet" href="/spocspace/admin/assets/css/emoji-picker.css">
<style>
.al-col-prio    { width: 5%; }
.al-col-titre   { width: 30%; }
.al-col-cible   { width: 15%; }
.al-col-createur{ width: 10%; }
.al-col-date    { width: 10%; }
.al-col-expire  { width: 10%; }
.al-col-lu      { width: 10%; }
.al-col-actions { width: 10%; text-align: center; }

.al-badge-red    { background: #E2B8AE; color: #7B3B2C; }
.al-badge-green  { background: #bcd2cb; color: #2d4a43; }
.al-badge-grey   { background: #C8C4BE; color: #5A5550; }
.al-badge-blue   { background: #B8C9D4; color: #3B4F6B; }

.al-color-green  { color: #2d4a43; }
.al-color-red    { color: #7B3B2C; }

.al-row-clickable { cursor: pointer; }
.al-row-unread    { background: #fdf8f6; }

.al-progress-track { flex: 1; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
.al-progress-fill  { height: 100%; border-radius: 3px; }
.al-progress-fill--green { background: #bcd2cb; }
.al-progress-fill--beige { background: #D4C4A8; }
.al-progress-fill--red   { background: #E2B8AE; }
.al-progress-label { min-width: 32px; }

.al-actions-cell { text-align: center; }
.al-btn-icon {
    width: 30px; height: 30px; border-radius: 50%;
    border: 1px solid #e5e7eb; background: #f9fafb;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0; transition: all .2s;
}
.al-btn-close-circle { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.al-icon-sm { font-size: .85rem; }
.al-icon-sm-muted { font-size: .85rem; color: #666; }
.al-modal-scroll { overflow-y: auto; max-height: 65vh; }
.al-info-banner { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.al-info-text   { font-size: .82rem; }
.al-border-bottom { border-bottom: 1px solid #e5e7eb; }
.al-filter-pill { border-radius: 2rem; font-size: .8rem; }
.al-filter-badge { font-size: .65rem; }
.al-search-input { max-width: 200px; font-size: .8rem; }
.al-detail-table { font-size: .82rem; }
.al-detail-thead { position: sticky; top: 0; background: #fff; z-index: 1; }
.al-col-collab  { width: 35%; }
.al-col-fonct   { width: 20%; }
.al-col-login   { width: 20%; }
.al-col-lecture { width: 25%; }
.al-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.al-avatar-initials {
    width: 32px; height: 32px; border-radius: 50%;
    background: #B8C9D4; color: #3B4F6B;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 600; flex-shrink: 0;
}
.al-text-xs  { font-size: .75rem; }
.al-text-sm  { font-size: .85rem; }
.al-text-empty { font-size: .85rem; }
.al-msg-box {
    margin-top: 0; padding: 0 1rem;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    font-size: .85rem; line-height: 1.6; color: #333; white-space: pre-wrap;
    max-height: 0; overflow: hidden; opacity: 0; border-width: 0;
    transition: max-height .35s ease, opacity .3s ease, margin-top .35s ease, padding .35s ease, border-width .35s ease;
}
.al-msg-box.is-open { opacity: 1; margin-top: .75rem; padding: .75rem 1rem; border-width: 1px; }
/* ── Priority slider tabs ── */
.al-prio-tabs {
    display: flex; position: relative; border-radius: 10px; padding: 3px; gap: 0;
    background: var(--cl-bg, #F7F5F2); min-width: 200px;
}
.al-prio-slider {
    position: absolute; top: 3px; left: 3px;
    width: calc(50% - 3px); height: calc(100% - 6px);
    border-radius: 8px; z-index: 0; pointer-events: none;
    background: #bcd2cb; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s;
}
.al-prio-tabs[data-value="haute"] .al-prio-slider {
    transform: translateX(100%); background: #E2B8AE;
}
.al-prio-tab {
    flex: 1; background: none; border: none; padding: 6px 12px;
    font-size: .78rem; font-weight: 500; border-radius: 8px; cursor: pointer;
    color: var(--cl-text-secondary, #888); position: relative; z-index: 1;
    text-align: center; white-space: nowrap;
    transition: color .25s, font-weight .25s;
}
.al-prio-tab:hover { color: var(--cl-text, #333); }
.al-prio-tab.active { font-weight: 700; }
.al-prio-tabs[data-value="normale"] .al-prio-tab.active { color: #2d4a43; }
.al-prio-tabs[data-value="haute"] .al-prio-tab.active { color: #7B3B2C; }

.al-hidden { display: none; }

/* ── Rich text editor ── */
.al-rte-toolbar {
    display: flex; align-items: center; gap: 2px; padding: 6px 8px; flex-wrap: wrap;
    border: 1px solid var(--cl-border, #E8E5E0); border-bottom: none;
    border-radius: var(--cl-radius-sm, 8px) var(--cl-radius-sm, 8px) 0 0;
    background: var(--cl-bg, #F7F5F2);
}
.al-rte-btn {
    width: 30px; height: 30px; border: none; background: none; border-radius: 6px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--cl-text-muted, #888); transition: all .15s;
}
.al-rte-btn:hover { background: var(--cl-surface, #fff); color: var(--cl-text, #333); }
.al-rte-btn.active { background: var(--cl-surface, #fff); color: var(--cl-primary, #2E7D32); box-shadow: 0 0 0 1.5px var(--cl-primary, #2E7D32); }
.al-rte-sep { width: 1px; height: 18px; background: var(--cl-border, #E8E5E0); margin: 0 3px; }
.al-rte-editor {
    width: 100%; min-height: 140px; max-height: 300px; padding: 12px 14px; overflow-y: auto;
    border: 1px solid var(--cl-border, #E8E5E0); border-radius: 0 0 var(--cl-radius-sm, 8px) var(--cl-radius-sm, 8px);
    font-size: .88rem; font-family: inherit; line-height: 1.7; color: var(--cl-text, #333);
    background: var(--cl-surface, #fff); outline: none;
}
.al-rte-editor:focus { border-color: var(--cl-border, #E8E5E0); box-shadow: none; }
.al-rte-editor p { margin: 0 0 6px; }
.al-rte-editor ul, .al-rte-editor ol { margin: 4px 0; padding-left: 20px; }
.al-rte-editor blockquote { border-left: 3px solid var(--cl-border, #D4C4A8); margin: 6px 0; padding: 6px 14px; background: rgba(212,196,168,.08); border-radius: 0 6px 6px 0; font-style: italic; }

/* emoji picker uses zkr-emoji-picker from emoji-picker.css */
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><i class="bi bi-megaphone"></i> Alertes & Annonces</h5>
  <button type="button" class="btn btn-primary btn-sm" id="btnNewAlert">
    <i class="bi bi-plus-lg"></i> Nouvelle alerte
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead>
        <tr>
          <th class="al-col-prio">Prio.</th>
          <th class="al-col-titre">Titre</th>
          <th class="al-col-cible">Cible</th>
          <th class="al-col-createur">Créé par</th>
          <th class="al-col-date">Date</th>
          <th class="al-col-expire">Expire</th>
          <th class="al-col-lu">Lu par</th>
          <th class="al-col-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="alertsBody">
        <?php if (empty($alerts)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">Aucune alerte</td></tr>
        <?php else: ?>
        <?php foreach ($alerts as $a):
            $isHaute   = $a['priority'] === 'haute';
            $active    = (bool)$a['is_active'];
            $rowClass  = $active ? '' : 'text-muted text-decoration-line-through';
            $readCount = (int)$a['read_count'];
            $totalUsers= (int)$a['total_users'];
            $readPct   = $totalUsers > 0 ? round($readCount / $totalUsers * 100) : 0;
            $barClass  = $readPct > 80 ? 'al-progress-fill--green' : ($readPct > 40 ? 'al-progress-fill--beige' : 'al-progress-fill--red');
            $targetLabel = $a['target'] === 'all' ? 'Tous'
                : ($a['target'] === 'module' ? 'Module: ' . h($a['target_value'] ?? '?')
                : 'Fonction: ' . h($a['target_value'] ?? '?'));
            $createdAt = $a['created_at'] ? date('d/m/Y', strtotime($a['created_at'])) : '—';
            $expiresAt = $a['expires_at'] ? date('d/m/Y', strtotime($a['expires_at'])) : '—';
        ?>
        <tr class="<?= $rowClass ?> al-row-clickable" data-action="detail" data-id="<?= h($a['id']) ?>">
          <td>
            <?php if ($isHaute): ?>
              <span class="badge al-badge-red">Haute</span>
            <?php else: ?>
              <span class="badge al-badge-grey">Normale</span>
            <?php endif; ?>
          </td>
          <td><strong><?= h($a['title']) ?></strong></td>
          <td><small><?= $targetLabel ?></small></td>
          <td><small><?= h($a['creator_prenom'] . ' ' . $a['creator_nom']) ?></small></td>
          <td><small><?= $createdAt ?></small></td>
          <td><small><?= $expiresAt ?></small></td>
          <td>
            <div class="d-flex align-items-center gap-1">
              <div class="al-progress-track">
                <div class="al-progress-fill <?= $barClass ?>" style="width:<?= $readPct ?>%"></div>
              </div>
              <small class="al-progress-label"><?= $readCount ?>/<?= $totalUsers ?></small>
            </div>
          </td>
          <td class="al-actions-cell">
            <button type="button" class="btn btn-xs btn-outline-<?= $active ? 'warning' : 'success' ?> me-1"
                    data-action="toggle" data-id="<?= h($a['id']) ?>"
                    title="<?= $active ? 'Désactiver' : 'Activer' ?>">
              <i class="bi bi-<?= $active ? 'pause' : 'play' ?>"></i>
            </button>
            <button type="button" class="btn btn-xs btn-outline-danger"
                    data-action="delete" data-id="<?= h($a['id']) ?>" title="Supprimer">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="alertDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center">
        <h6 class="modal-title mb-0" id="alertDetailTitle"><i class="bi bi-megaphone"></i> Détail alerte</h6>
        <button type="button" class="btn btn-sm ms-2 al-btn-icon" id="btnToggleAlertMsg" title="Voir le message"><i class="bi bi-info-lg al-icon-sm-muted"></i></button>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center al-btn-close-circle" data-bs-dismiss="modal"><i class="bi bi-x-lg al-icon-sm"></i></button>
      </div>
      <div class="modal-body p-0 al-modal-scroll">
        <div id="alertDetailInfo" class="px-3 py-3 al-info-banner"></div>
        <div class="d-flex align-items-center gap-2 px-3 py-2 al-border-bottom">
          <button class="btn btn-sm py-1 px-3 active al-filter-pill" data-filter="all">Tous <span id="countAll" class="badge rounded-pill ms-1 al-filter-badge al-badge-grey">0</span></button>
          <button class="btn btn-sm py-1 px-3 al-filter-pill" data-filter="read">Lu <span id="countRead" class="badge rounded-pill ms-1 al-filter-badge al-badge-green">0</span></button>
          <button class="btn btn-sm py-1 px-3 al-filter-pill" data-filter="unread">Non lu <span id="countUnread" class="badge rounded-pill ms-1 al-filter-badge al-badge-red">0</span></button>
          <input type="text" class="form-control form-control-sm ms-auto al-search-input" id="alertDetailSearch" placeholder="Rechercher...">
        </div>
        <div id="alertDetailUsers" class="p-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Create modal -->
<div class="modal fade" id="alertModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-megaphone"></i> Nouvelle alerte</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-end gap-3 mb-3">
          <div class="flex-grow-1">
            <label class="form-label fw-bold">Titre *</label>
            <input type="text" class="form-control form-control-sm" id="alertTitle" maxlength="255">
          </div>
          <div class="flex-shrink-0">
            <label class="form-label fw-bold" style="font-size:.75rem">Priorité</label>
            <div class="al-prio-tabs" id="alertPrioToggle" data-value="normale">
              <div class="al-prio-slider"></div>
              <button type="button" class="al-prio-tab active" data-prio="normale">Normale</button>
              <button type="button" class="al-prio-tab" data-prio="haute">🔴 Haute</button>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Message *</label>
          <div style="position:relative">
          <div class="al-rte-toolbar">
            <button type="button" class="al-rte-btn" data-cmd="bold" title="Gras"><i class="bi bi-type-bold"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="italic" title="Italique"><i class="bi bi-type-italic"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="underline" title="Souligné"><i class="bi bi-type-underline"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="strikeThrough" title="Barré"><i class="bi bi-type-strikethrough"></i></button>
            <span class="al-rte-sep"></span>
            <button type="button" class="al-rte-btn" data-cmd="hiliteColor" data-val="#FFF9C4" title="Surligner"><i class="bi bi-highlighter"></i></button>
            <span class="al-rte-sep"></span>
            <button type="button" class="al-rte-btn" data-cmd="justifyLeft" title="Gauche"><i class="bi bi-text-left"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="justifyCenter" title="Centrer"><i class="bi bi-text-center"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="justifyRight" title="Droite"><i class="bi bi-text-right"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="justifyFull" title="Justifier"><i class="bi bi-justify"></i></button>
            <span class="al-rte-sep"></span>
            <button type="button" class="al-rte-btn" data-cmd="insertUnorderedList" title="Liste à puces"><i class="bi bi-list-ul"></i></button>
            <button type="button" class="al-rte-btn" data-cmd="insertOrderedList" title="Liste numérotée"><i class="bi bi-list-ol"></i></button>
            <span class="al-rte-sep"></span>
            <button type="button" class="al-rte-btn" data-cmd="formatBlock" data-val="BLOCKQUOTE" title="Citation"><i class="bi bi-blockquote-left"></i></button>
            <span class="al-rte-sep"></span>
            <button type="button" class="al-rte-btn" id="alEmojiBtn" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
          </div>
          <div class="al-rte-editor" contenteditable="true" id="alertMessage"></div>
          <!-- Emoji picker (zkr-emoji style) -->
          <div id="alEmojiAnchor"></div>
          </div><!-- /relative -->
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label">Expire le</label>
            <input type="date" class="form-control form-control-sm" id="alertExpires" style="height:38px">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cible</label>
            <div class="zs-select" id="alertTarget" data-placeholder="Cible"></div>
          </div>
          <div class="col-md-3 al-hidden" id="alertTargetValueGroup">
            <label class="form-label">Valeur</label>
            <div class="zs-select" id="alertTargetValue" data-placeholder="Valeur"></div>
          </div>
        </div>
        <div class="ss-info-bar small al-hidden" id="alertHauteInfo">
          <i class="bi bi-exclamation-triangle"></i>
          Les alertes de <strong>haute importance</strong> affichent un modal rouge obligatoire à chaque connexion.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-primary" id="alertSaveBtn">
          <i class="bi bi-send"></i> Envoyer l'alerte
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    // ── Données injectées par PHP ─────────────────────────────────────────────
    const modulesData   = <?= json_encode(array_values($modules),   JSON_HEX_TAG) ?>;
    const fonctionsData = <?= json_encode(array_values($fonctions), JSON_HEX_TAG) ?>;

    // ── Toggle / Delete / Détail — délégation ────────────────────────────────
    document.getElementById('alertsBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="toggle"], [data-action="delete"]');
        const row = e.target.closest('[data-action="detail"]');

        if (btn) {
            e.stopPropagation();
            const id = btn.dataset.id;
            if (btn.dataset.action === 'toggle') {
                btn.disabled = true;
                await adminApiPost('admin_toggle_alert', { id });
                location.reload();
            } else if (btn.dataset.action === 'delete') {
                if (!await adminConfirm({
                    title: "Supprimer l'alerte",
                    text: 'Cette action est irréversible. Tous les accusés de lecture seront supprimés.',
                    icon: 'bi-trash', type: 'danger', okText: 'Supprimer'
                })) return;
                btn.disabled = true;
                const res = await adminApiPost('admin_delete_alert', { id });
                if (res.success) { showToast('Alerte supprimée', 'success'); location.reload(); }
            }
        } else if (row) {
            openAlertDetail(row.dataset.id);
        }
    });

    // ── Create modal ─────────────────────────────────────────────────────────
    const modal = new bootstrap.Modal(document.getElementById('alertModal'));

    // Priority slider tabs
    const prioToggle = document.getElementById('alertPrioToggle');
    prioToggle?.addEventListener('click', (e) => {
        const tab = e.target.closest('.al-prio-tab');
        if (!tab) return;
        const val = tab.dataset.prio;
        prioToggle.dataset.value = val;
        prioToggle.querySelectorAll('.al-prio-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('alertHauteInfo').classList.toggle('al-hidden', val !== 'haute');
    });

    zerdaSelect.init('#alertTarget', [
        { value: 'all',      label: 'Tous les collaborateurs' },
        { value: 'module',   label: 'Par module' },
        { value: 'fonction', label: 'Par fonction' }
    ], { value: 'all', onSelect: (val) => {
        const group = document.getElementById('alertTargetValueGroup');
        if (val === 'module') {
            group.classList.remove('al-hidden');
            zerdaSelect.destroy('#alertTargetValue');
            zerdaSelect.init('#alertTargetValue',
                modulesData.map(m => ({ value: m.id, label: escapeHtml(m.code) + ' — ' + escapeHtml(m.nom) })),
                { value: modulesData[0]?.id || '' });
        } else if (val === 'fonction') {
            group.classList.remove('al-hidden');
            zerdaSelect.destroy('#alertTargetValue');
            zerdaSelect.init('#alertTargetValue',
                fonctionsData.map(f => ({ value: f.code, label: escapeHtml(f.code) + ' — ' + escapeHtml(f.nom) })),
                { value: fonctionsData[0]?.code || '' });
        } else {
            group.classList.add('al-hidden');
        }
    }});

    zerdaSelect.init('#alertTargetValue', [], { value: '' });

    document.getElementById('btnNewAlert').addEventListener('click', () => {
        document.getElementById('alertTitle').value   = '';
        document.getElementById('alertMessage').innerHTML = '';
        document.getElementById('alertPrioToggle').dataset.value = 'normale';
        document.querySelectorAll('.al-prio-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.al-prio-tab[data-prio="normale"]').classList.add('active');
        document.getElementById('alertExpires').value = '';
        zerdaSelect.setValue('#alertTarget', 'all');
        document.getElementById('alertTargetValueGroup').classList.add('al-hidden');
        document.getElementById('alertHauteInfo').classList.add('al-hidden');
        modal.show();
    });

    document.getElementById('alertSaveBtn').addEventListener('click', async () => {
        const title    = document.getElementById('alertTitle').value.trim();
        const message  = document.getElementById('alertMessage').innerHTML.trim();
        const priority = document.getElementById('alertPrioToggle').dataset.value;
        const target   = zerdaSelect.getValue('#alertTarget');
        const targetValue = zerdaSelect.getValue('#alertTargetValue');
        const expiresAt   = document.getElementById('alertExpires').value;

        if (!title || !message) { showToast('Titre et message requis', 'error'); return; }

        const btn = document.getElementById('alertSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const res = await adminApiPost('admin_create_alert', {
            title, message, priority, target,
            target_value: target !== 'all' ? targetValue : '',
            expires_at: expiresAt || null,
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Envoyer l\'alerte';

        if (res.success) {
            showToast('Alerte envoyée', 'success');
            modal.hide();
            location.reload();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // ── Detail modal (reste AJAX — données par alerte) ───────────────────────
    const detailModal = new bootstrap.Modal(document.getElementById('alertDetailModal'));
    let detailUsersData = [];
    let detailFilter = 'all';

    async function openAlertDetail(id) {
        const container = document.getElementById('alertDetailUsers');
        container.innerHTML = '<div class="text-center py-4"><span class="admin-spinner"></span></div>';
        document.getElementById('alertDetailSearch').value = '';
        detailFilter = 'all';
        document.querySelectorAll('#alertDetailModal [data-filter]').forEach(b => b.classList.remove('active'));
        document.querySelector('#alertDetailModal [data-filter="all"]').classList.add('active');
        detailModal.show();

        const res = await adminApiPost('admin_get_alert_reads', { id });
        if (!res.success) { container.innerHTML = '<div class="text-center py-4 text-muted">Erreur</div>'; return; }

        const a = res.alert;
        const isHaute = a.priority === 'haute';
        const targetLabel = a.target === 'all' ? 'Tous les collaborateurs'
            : a.target === 'module' ? 'Module : ' + (a.target_value || '?')
            : 'Fonction : ' + (a.target_value || '?');

        document.getElementById('alertDetailTitle').innerHTML = '<i class="bi bi-megaphone"></i> ' + escapeHtml(a.title);
        document.getElementById('alertDetailInfo').innerHTML = `
            <div class="d-flex flex-wrap gap-3 align-items-center al-info-text">
                <span>${isHaute ? '<span class="badge al-badge-red"><i class="bi bi-exclamation-triangle"></i> Haute</span>' : '<span class="badge al-badge-grey">Normale</span>'}</span>
                <span class="text-muted"><i class="bi bi-bullseye"></i> ${escapeHtml(targetLabel)}</span>
                <span class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(a.creator_prenom + ' ' + a.creator_nom)}</span>
                <span class="ms-auto fw-semibold ${res.read_count === res.total_users ? 'al-color-green' : 'al-color-red'}">
                    <i class="bi bi-eye"></i> ${res.read_count} / ${res.total_users} lu${res.read_count > 1 ? 's' : ''}
                </span>
            </div>
            <div id="alertDetailMsgBox" class="al-msg-box" data-open="0">${a.message || ''}</div>`;

        detailUsersData = res.users;
        document.getElementById('countAll').textContent    = res.total_users;
        document.getElementById('countRead').textContent   = res.read_count;
        document.getElementById('countUnread').textContent = res.total_users - res.read_count;
        renderDetailUsers();
    }

    function renderDetailUsers() {
        const search    = document.getElementById('alertDetailSearch').value.toLowerCase().trim();
        const container = document.getElementById('alertDetailUsers');
        let filtered = detailUsersData;
        if (detailFilter === 'read')   filtered = filtered.filter(u => u.read_at);
        if (detailFilter === 'unread') filtered = filtered.filter(u => !u.read_at);
        if (search) filtered = filtered.filter(u =>
            (u.prenom + ' ' + u.nom + ' ' + u.email + ' ' + (u.fonction_nom || '')).toLowerCase().includes(search)
        );
        if (!filtered.length) { container.innerHTML = '<div class="text-center py-4 text-muted al-text-empty">Aucun collaborateur trouvé</div>'; return; }

        container.innerHTML = '<table class="table table-sm table-hover mb-0 al-detail-table">'
            + '<thead class="al-detail-thead"><tr>'
            + '<th class="al-col-collab">Collaborateur</th><th class="al-col-fonct">Fonction</th>'
            + '<th class="al-col-login">Dernière connexion</th><th class="al-col-lecture">Lecture alerte</th>'
            + '</tr></thead><tbody>'
            + filtered.map(u => {
                const hasRead   = !!u.read_at;
                const readBadge = hasRead
                    ? `<span class="badge al-badge-green"><i class="bi bi-check-circle"></i> Lu</span><span class="text-muted ms-1">${fmtDt(u.read_at)}</span>`
                    : '<span class="badge al-badge-red"><i class="bi bi-x-circle"></i> Non lu</span>';
                const initials = (u.prenom?.[0] || '') + (u.nom?.[0] || '');
                const avatar   = u.photo
                    ? `<img src="${escapeHtml(u.photo)}" class="al-avatar">`
                    : `<div class="al-avatar-initials">${escapeHtml(initials.toUpperCase())}</div>`;
                return `<tr class="${!hasRead ? 'al-row-unread' : ''}">
                    <td><div class="d-flex align-items-center gap-2">${avatar}<div>
                        <div class="fw-medium">${escapeHtml(u.prenom + ' ' + u.nom)}</div>
                        <div class="text-muted al-text-xs">${escapeHtml(u.email)}</div>
                    </div></div></td>
                    <td>${u.fonction_nom ? escapeHtml(u.fonction_nom) : '<span class="text-muted">—</span>'}</td>
                    <td>${u.last_login ? fmtDt(u.last_login) : '<span class="text-muted">Jamais</span>'}</td>
                    <td>${readBadge}</td>
                </tr>`;
            }).join('') + '</tbody></table>';
    }

    function fmtDt(str) {
        if (!str) return '—';
        const d = new Date(str);
        return d.toLocaleDateString('fr-FR', {day:'numeric',month:'short'})
            + ' ' + d.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'});
    }

    document.getElementById('btnToggleAlertMsg').addEventListener('click', () => {
        const box = document.getElementById('alertDetailMsgBox');
        if (!box) return;
        const isOpen = box.dataset.open === '1';
        if (isOpen) { box.classList.remove('is-open'); box.style.maxHeight = '0'; box.dataset.open = '0'; }
        else { box.style.maxHeight = box.scrollHeight + 24 + 'px'; box.classList.add('is-open'); box.dataset.open = '1'; }
    });

    document.getElementById('alertDetailModal').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-filter]');
        if (!btn) return;
        detailFilter = btn.dataset.filter;
        document.querySelectorAll('#alertDetailModal [data-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderDetailUsers();
    });

    document.getElementById('alertDetailSearch').addEventListener('input', () => renderDetailUsers());

    // ═══ Rich Text Editor — Toolbar ═══
    document.querySelector('.al-rte-toolbar')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.al-rte-btn');
        if (!btn || btn.id === 'alEmojiBtn') return;
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.val || null;
        document.execCommand(cmd, false, val);
        document.getElementById('alertMessage')?.focus();
    });

    // ═══ Emoji Picker (zkr-emoji style) ═══
    const EMOJI_CATS = {
        smileys:    { icon: '😀', label: 'Smileys', emojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤌','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤙','💪','🙏'] },
        animals:    { icon: '🐻', label: 'Animaux & Nature', emojis: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🐺','🐴','🦄','🐝','🦋','🐌','🐞','🐢','🐍','🐙','🐠','🐟','🐬','🐳','🐋','🦈','🐘','🦒','🌲','🌳','🌴','🌱','🌿','🍀','💐','🌷','🌹','🌺','🌸','🌼','🌻','☀️','🌤️','⛅','🌈','❄️','🔥','💧','🌊'] },
        food:       { icon: '🍔', label: 'Nourriture', emojis: ['🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🍒','🍑','🥭','🍍','🥝','🍅','🥑','🥦','🥒','🌶️','🌽','🥕','🥔','🥐','🍞','🥖','🧀','🥚','🍳','🥞','🥓','🍗','🍖','🌭','🍔','🍟','🍕','🥪','🌮','🥗','🍝','🍜','🍣','🍱','🍦','🍰','🎂','🍩','🍪','☕','🍵','🥤','🍺','🍷','🥂'] },
        travel:     { icon: '✈️', label: 'Voyages', emojis: ['🚗','🚕','🚌','🏎️','🚑','🚒','🚲','🏍️','✈️','🚀','🛶','⛵','🚢','🏠','🏢','🏥','🏫','⛪','🏰','🗼','🗽','⛲','🏖️','🏔️','🌅','🌇','🌃','🌉','🎡','🎢'] },
        activities: { icon: '⚽', label: 'Activités', emojis: ['⚽','🏀','🎾','🏐','🎱','🏓','🏸','⛳','🏹','🎣','🥊','🥋','🏋️','🧘','🏊','🏄','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🎗️','🎪','🎭','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🎷','🎸','🎻','🎲','🎯','🎮','🧩'] },
        objects:    { icon: '💡', label: 'Objets', emojis: ['📱','💻','⌨️','🖥️','🖨️','📷','📹','📞','📺','📻','⏰','💡','🔦','💸','💰','💳','💎','🔧','🔨','⚙️','🔪','🛡️','🩹','🩺','💊','💉','🧬','🌡️','🧹','🧼','🔑','🚪','🛋️','🛏️','🎁','🎈','🎀','🎊','🎉','✉️','📦','📋','📁','📰','📓','📕','📗','📘','📚','🔖','📎','✂️','📌','📝','✏️','🔍','🔒'] },
        symbols:    { icon: '❤️', label: 'Symboles', emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','⚠️','🚨','🔴','🟡','🟢','🔵','📌','✅','❌','⭐','🌟','💯','❗','❓','‼️','⁉️','🔔','📢','📣','♻️','🚫','⛔','🛑'] },
        flags:      { icon: '🏁', label: 'Drapeaux', emojis: ['🏁','🚩','🇨🇭','🇫🇷','🇩🇪','🇮🇹','🇪🇸','🇬🇧','🇺🇸','🇧🇪','🇵🇹','🇧🇷','🇯🇵','🇨🇳','🇰🇷','🇮🇳','🇹🇷','🇷🇺','🇸🇦','🇦🇪','🇲🇦','🇹🇳','🇩🇿','🇸🇳','🇨🇮','🇨🇩','🇨🇲','🇪🇺','🏳️‍🌈'] }
    };

    let emojiPicker = null;
    let emojiCurrentCat = 'smileys';

    function createEmojiPicker() {
        const div = document.createElement('div');
        div.className = 'zkr-emoji-picker';
        div.style.position = 'absolute';
        div.style.zIndex = '10000';

        let tabsHtml = Object.entries(EMOJI_CATS).map(([key, cat]) =>
            `<button class="zkr-emoji-tab ${key === emojiCurrentCat ? 'active' : ''}" data-cat="${key}" title="${escapeHtml(cat.label)}">${cat.icon}</button>`
        ).join('');

        div.innerHTML = `
            <div class="zkr-emoji-header">
                <div class="zkr-emoji-tabs">${tabsHtml}</div>
                <div class="zkr-emoji-search">
                    <input type="text" class="zkr-emoji-search-input form-control" placeholder="Rechercher...">
                    <i class="bi bi-search zkr-emoji-search-icon"></i>
                </div>
            </div>
            <div class="zkr-emoji-body">
                <div class="zkr-emoji-category-label">${EMOJI_CATS[emojiCurrentCat].label}</div>
                <div class="zkr-emoji-grid"></div>
            </div>`;

        // Tab clicks
        div.querySelectorAll('.zkr-emoji-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                emojiCurrentCat = tab.dataset.cat;
                div.querySelectorAll('.zkr-emoji-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                div.querySelector('.zkr-emoji-search-input').value = '';
                loadEmojiCategory(div, emojiCurrentCat);
            });
        });

        // Search
        div.querySelector('.zkr-emoji-search-input').addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            if (!q) { loadEmojiCategory(div, emojiCurrentCat); return; }
            let all = [];
            Object.values(EMOJI_CATS).forEach(cat => all.push(...cat.emojis));
            const grid = div.querySelector('.zkr-emoji-grid');
            div.querySelector('.zkr-emoji-category-label').textContent = 'Résultats';
            grid.innerHTML = all.filter(e => e.includes(q)).map(e =>
                `<button class="zkr-emoji-btn" data-emoji="${e}">${e}</button>`
            ).join('') || '<div style="grid-column:1/-1;text-align:center;padding:20px;color:#999;font-size:.85rem">Aucun résultat</div>';
        });

        // Emoji click
        div.addEventListener('click', (e) => {
            const btn = e.target.closest('.zkr-emoji-btn');
            if (!btn) return;
            const editor = document.getElementById('alertMessage');
            editor.focus();
            document.execCommand('insertText', false, btn.dataset.emoji);
            closeEmojiPicker();
        });

        loadEmojiCategory(div, emojiCurrentCat);
        return div;
    }

    function loadEmojiCategory(picker, catKey) {
        const cat = EMOJI_CATS[catKey];
        picker.querySelector('.zkr-emoji-category-label').textContent = cat.label;
        picker.querySelector('.zkr-emoji-grid').innerHTML = cat.emojis.map(e =>
            `<button class="zkr-emoji-btn" data-emoji="${e}">${e}</button>`
        ).join('');
    }

    function openEmojiPicker() {
        if (emojiPicker) { closeEmojiPicker(); return; }
        emojiPicker = createEmojiPicker();
        const anchor = document.getElementById('alEmojiAnchor');
        anchor.appendChild(emojiPicker);
        // Position below the toolbar
        emojiPicker.style.right = '0';
        emojiPicker.style.top = '4px';
        setTimeout(() => emojiPicker.querySelector('.zkr-emoji-search-input')?.focus(), 50);
    }

    function closeEmojiPicker() {
        if (emojiPicker) { emojiPicker.remove(); emojiPicker = null; }
    }

    document.getElementById('alEmojiBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openEmojiPicker();
    });

    document.addEventListener('click', (e) => {
        if (emojiPicker && !e.target.closest('.zkr-emoji-picker') && !e.target.closest('#alEmojiBtn')) {
            closeEmojiPicker();
        }
    });

})();
</script>
