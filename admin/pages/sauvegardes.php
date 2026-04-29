<style>
.bk-hero { background:linear-gradient(135deg,#f0f6f4 0%,#f8f4ed 100%); border:1px solid #d4e5df; border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:1rem; }
.bk-hero-icon { width:52px; height:52px; border-radius:14px; background:#2d4a43; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.bk-hero-title { margin:0; font-size:1.05rem; font-weight:700; color:#1a1a1a; }
.bk-hero-sub { font-size:.82rem; color:#6b7280; margin-top:.15rem; }

.bk-tabs { display:flex; gap:6px; margin-bottom:1.25rem; }
.bk-tab {
    padding:8px 20px; border-radius:10px; font-size:.85rem; font-weight:600;
    cursor:pointer; border:1.5px solid var(--cl-border-light,#F0EDE8);
    background:transparent; color:var(--cl-text-muted); transition:all .15s;
}
.bk-tab:hover { border-color:var(--cl-border-hover); }
.bk-tab.active { background:var(--cl-surface); border-color:#2d4a43; color:#2d4a43; }
.bk-tab .bi-lock-fill { font-size:.7rem; }

.bk-card { background:#fff; border:1px solid #f0ede8; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1rem; }
.bk-card h6 { font-size:.95rem; font-weight:700; margin:0 0 .25rem 0; display:flex; align-items:center; gap:.5rem; }
.bk-card-desc { font-size:.82rem; color:#6b7280; margin-bottom:1rem; }

.bk-list { list-style:none; padding:0; margin:0; }
.bk-item {
    display:flex; align-items:center; gap:1rem; padding:.85rem 0;
    border-bottom:1px solid #f4f1ec;
}
.bk-item:last-child { border-bottom:none; }
.bk-item-icon { width:40px; height:40px; border-radius:10px; background:#bcd2cb; color:#2d4a43; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.bk-item-icon.global { background:#E2B8AE; color:#7B3B2C; }
.bk-item-body { flex:1; min-width:0; }
.bk-item-title { font-weight:600; font-size:.9rem; color:#1a1a1a; }
.bk-item-meta { font-size:.75rem; color:#6b7280; margin-top:2px; display:flex; gap:12px; flex-wrap:wrap; }
.bk-item-actions { display:flex; gap:6px; flex-shrink:0; }

.bk-btn { padding:5px 12px; border-radius:8px; font-size:.78rem; font-weight:500; border:1.5px solid; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:4px; }
.bk-btn-primary { background:#2d4a43; color:#fff; border-color:#2d4a43; }
.bk-btn-primary:hover { background:#1f3530; }
.bk-btn-outline { background:transparent; color:#2d4a43; border-color:#d4e5df; }
.bk-btn-outline:hover { background:#f0f6f4; }
.bk-btn-danger { background:transparent; color:#cc3333; border-color:#f5d5d5; }
.bk-btn-danger:hover { background:#fef2f2; }
.bk-btn-download { background:transparent; color:#2B6CB0; border-color:#c6dff5; }
.bk-btn-download:hover { background:#eff6ff; }

.bk-empty { text-align:center; padding:3rem 1rem; color:#9ca3af; }
.bk-empty i { font-size:2.5rem; display:block; margin-bottom:.75rem; }

.bk-diff-table { width:100%; border-collapse:collapse; font-size:.85rem; margin-top:1rem; }
.bk-diff-table th { font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:var(--cl-text-muted); padding:8px 12px; border-bottom:1.5px solid #f0ede8; text-align:left; }
.bk-diff-table td { padding:8px 12px; border-bottom:1px solid #f4f1ec; }
.bk-diff-added { color:#16a34a; font-weight:600; }
.bk-diff-removed { color:#cc3333; font-weight:600; }
.bk-diff-same { color:#9ca3af; }

.bk-danger-box { background:#fef2f2; border:2px solid #cc3333; border-radius:12px; padding:1.25rem; margin:1rem 0; }
.bk-danger-box h6 { color:#cc3333; margin:0 0 .5rem 0; font-size:.95rem; }
.bk-danger-box p { font-size:.85rem; color:#7f1d1d; margin:0 0 .75rem 0; }
.bk-confirm-input { font-family:monospace; font-size:1rem; text-align:center; border:2px solid #fca5a5; border-radius:8px; padding:.5rem; width:100%; max-width:280px; }
.bk-confirm-input:focus { outline:none; border-color:#cc3333; }

.bk-code-box { background:#fff; border:1.5px solid #f0ede8; border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
.bk-code-label { font-size:.78rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.bk-code-input { font-family:monospace; }

.bk-progress { position:relative; height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden; margin-top:.5rem; }
.bk-progress-bar { height:100%; background:#2d4a43; border-radius:3px; transition:width .3s; }

.bk-spinner { display:inline-block; width:16px; height:16px; border:2px solid #d4e5df; border-top-color:#2d4a43; border-radius:50%; animation:bkSpin .6s linear infinite; }
@keyframes bkSpin { to { transform:rotate(360deg); } }

/* Modal overlay */
.bk-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1050; display:flex; align-items:center; justify-content:center; }
.bk-modal { background:#fff; border-radius:16px; padding:1.5rem; max-width:560px; width:95%; max-height:80vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.bk-modal-close { float:right; background:none; border:none; font-size:1.2rem; cursor:pointer; color:#6b7280; padding:4px; }
.bk-modal-close:hover { color:#1a1a1a; }
</style>

<!-- Hero -->
<div class="bk-hero">
    <div class="bk-hero-icon"><i class="bi bi-database-down"></i></div>
    <div>
        <h5 class="bk-hero-title">Sauvegarde & Restauration</h5>
        <div class="bk-hero-sub">Protegez vos donnees — sauvegardes manuelles et automatiques avec restauration securisee</div>
    </div>
</div>

<!-- Tabs -->
<div class="bk-tabs">
    <button class="bk-tab active" data-tab="user">Mes sauvegardes</button>
    <button class="bk-tab" data-tab="global">Global <i class="bi bi-lock-fill"></i></button>
    <button class="bk-tab" data-tab="config">Configuration</button>
</div>

<!-- Tab: User backups -->
<div id="tabUser" class="bk-tab-content">
    <div class="bk-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6><i class="bi bi-archive"></i> Mes sauvegardes</h6>
            <button class="bk-btn bk-btn-primary" id="btnCreateUserBackup">
                <i class="bi bi-plus-lg"></i> Creer une sauvegarde
            </button>
        </div>
        <div class="bk-card-desc">Sauvegarde de vos documents, messages et emails. Maximum 5 sauvegardes (rotation automatique).</div>
        <div id="userBackupList">
            <div class="bk-empty"><span class="bk-spinner"></span><br>Chargement...</div>
        </div>
    </div>
</div>

<!-- Tab: Global backups -->
<div id="tabGlobal" class="bk-tab-content" style="display:none">
    <div id="globalLockScreen" class="bk-card" style="text-align:center; padding:3rem 1.5rem;">
        <i class="bi bi-shield-lock" style="font-size:3rem; color:#2d4a43; display:block; margin-bottom:1rem;"></i>
        <h6 style="justify-content:center;">Acces protege</h6>
        <p class="bk-card-desc">Saisissez le code d'acces special pour voir et gerer les sauvegardes globales.</p>
        <div class="d-flex justify-content-center gap-2">
            <input type="password" id="globalCodeInput" class="form-control form-control-sm bk-code-input" placeholder="Code d'acces" style="max-width:220px">
            <button class="bk-btn bk-btn-primary" id="btnUnlockGlobal"><i class="bi bi-unlock"></i> Deverrouiller</button>
        </div>
        <div id="globalCodeError" style="color:#cc3333; font-size:.82rem; margin-top:.5rem; display:none;"></div>
        <div id="globalCodeNotSet" style="display:none; margin-top:1rem;">
            <p style="font-size:.82rem; color:#C54A3A;">Aucun code d'acces configure. Allez dans l'onglet Configuration pour en definir un.</p>
        </div>
    </div>

    <div id="globalUnlockedContent" style="display:none;">
        <div class="bk-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6><i class="bi bi-globe2"></i> Sauvegardes globales</h6>
                <button class="bk-btn bk-btn-primary" id="btnCreateGlobalBackup">
                    <i class="bi bi-plus-lg"></i> Sauvegarde globale
                </button>
            </div>
            <div class="bk-card-desc">Sauvegarde complete de toutes les donnees du systeme. Retention : 14 jours quotidiens + 8 hebdomadaires.</div>
            <div id="globalBackupList">
                <div class="bk-empty"><span class="bk-spinner"></span><br>Chargement...</div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Config -->
<div id="tabConfig" class="bk-tab-content" style="display:none">
    <div class="bk-card">
        <h6><i class="bi bi-key"></i> Code d'acces global</h6>
        <div class="bk-card-desc">Ce code est requis pour restaurer une sauvegarde globale. Il est distinct du mot de passe administrateur.</div>
        <div class="bk-code-box">
            <div class="bk-code-label">Nouveau code d'acces (minimum 6 caracteres)</div>
            <div class="d-flex gap-2 mt-2">
                <input type="password" id="newAccessCode" class="form-control form-control-sm bk-code-input" placeholder="Nouveau code" style="max-width:280px">
                <input type="password" id="confirmAccessCode" class="form-control form-control-sm bk-code-input" placeholder="Confirmer" style="max-width:280px">
                <button class="bk-btn bk-btn-primary" id="btnSaveAccessCode"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </div>
            <div id="accessCodeStatus" style="font-size:.82rem; margin-top:.5rem;"></div>
        </div>
    </div>
    <div class="bk-card">
        <h6><i class="bi bi-info-circle"></i> Informations</h6>
        <div class="bk-card-desc">Parametres actuels du systeme de sauvegarde.</div>
        <table class="bk-diff-table">
            <tr><td style="font-weight:600; width:40%;">Max sauvegardes par utilisateur</td><td>5</td></tr>
            <tr><td style="font-weight:600;">Retention quotidienne (global)</td><td>14 jours</td></tr>
            <tr><td style="font-weight:600;">Retention hebdomadaire (global)</td><td>8 semaines</td></tr>
            <tr><td style="font-weight:600;">Format</td><td>ZIP (ZipArchive PHP natif)</td></tr>
            <tr><td style="font-weight:600;">Integrite</td><td>SHA-256 checksum</td></tr>
            <tr><td style="font-weight:600;">Cron automatique</td><td>Quotidien a 3h00</td></tr>
        </table>
    </div>
</div>

<!-- Modal container -->
<div id="bkModalContainer"></div>

<script<?= nonce() ?>>
(function() {
    let globalAccessCode = '';

    // ─── Tab switching ───
    document.querySelectorAll('.bk-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.bk-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.bk-tab-content').forEach(c => c.style.display = 'none');
            const target = tab.dataset.tab;
            if (target === 'user') { document.getElementById('tabUser').style.display = ''; loadUserBackups(); }
            if (target === 'global') { document.getElementById('tabGlobal').style.display = ''; checkGlobalAccess(); }
            if (target === 'config') { document.getElementById('tabConfig').style.display = ''; }
        });
    });

    // ─── Helpers ───
    function showModal(html) {
        const c = document.getElementById('bkModalContainer');
        c.innerHTML = '<div class="bk-modal-overlay"><div class="bk-modal">' + html + '</div></div>';
        c.querySelector('.bk-modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
    }
    function closeModal() { document.getElementById('bkModalContainer').innerHTML = ''; }
    function fmtDate(d) { if (!d) return '—'; const dt = new Date(d); return dt.toLocaleDateString('fr-CH', {day:'2-digit',month:'2-digit',year:'numeric'}) + ' ' + dt.toLocaleTimeString('fr-CH', {hour:'2-digit',minute:'2-digit'}); }

    function renderBackupItem(b, isGlobal) {
        const counts = b.row_counts || {};
        const metaParts = [];
        for (const [k, v] of Object.entries(counts)) {
            if (v > 0) metaParts.push(k + ': ' + v);
        }
        const metaStr = metaParts.slice(0, 4).join(' | ') + (metaParts.length > 4 ? ' ...' : '');
        const iconClass = isGlobal ? 'bk-item-icon global' : 'bk-item-icon';
        const iconName = isGlobal ? 'bi-globe2' : 'bi-archive';

        return '<div class="bk-item" data-id="' + b.id + '">'
            + '<div class="' + iconClass + '"><i class="bi ' + iconName + '"></i></div>'
            + '<div class="bk-item-body">'
            + '  <div class="bk-item-title">' + fmtDate(b.created_at) + '</div>'
            + '  <div class="bk-item-meta">'
            + '    <span><i class="bi bi-hdd"></i> ' + (b.file_size_human || '—') + '</span>'
            + '    <span>' + metaStr + '</span>'
            + (b.prenom ? '<span>par ' + b.prenom + ' ' + b.nom + '</span>' : '')
            + '  </div>'
            + '</div>'
            + '<div class="bk-item-actions">'
            + '  <button class="bk-btn bk-btn-outline" onclick="bkCompare(\'' + b.id + '\',' + isGlobal + ')"><i class="bi bi-arrow-left-right"></i> Comparer</button>'
            + '  <button class="bk-btn bk-btn-outline" onclick="bkRestore(\'' + b.id + '\',' + isGlobal + ')"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</button>'
            + '  <button class="bk-btn bk-btn-download" onclick="bkDownload(\'' + b.id + '\')"><i class="bi bi-download"></i></button>'
            + '  <button class="bk-btn bk-btn-danger" onclick="bkDelete(\'' + b.id + '\',' + isGlobal + ')"><i class="bi bi-trash3"></i></button>'
            + '</div>'
            + '</div>';
    }

    // ─── Load user backups ───
    async function loadUserBackups() {
        const c = document.getElementById('userBackupList');
        c.innerHTML = '<div class="bk-empty"><span class="bk-spinner"></span><br>Chargement...</div>';
        const r = await adminApiPost('admin_list_backups', { type: 'user' });
        if (!r.success) { c.innerHTML = '<div class="bk-empty"><i class="bi bi-exclamation-triangle"></i> Erreur</div>'; return; }
        if (!r.backups.length) {
            c.innerHTML = '<div class="bk-empty"><i class="bi bi-archive"></i>Aucune sauvegarde<br><small>Cliquez sur "Creer une sauvegarde" pour commencer</small></div>';
            return;
        }
        c.innerHTML = '<ul class="bk-list">' + r.backups.map(b => '<li>' + renderBackupItem(b, false) + '</li>').join('') + '</ul>';
    }

    // ─── Load global backups ───
    async function loadGlobalBackups() {
        const c = document.getElementById('globalBackupList');
        c.innerHTML = '<div class="bk-empty"><span class="bk-spinner"></span><br>Chargement...</div>';
        const r = await adminApiPost('admin_list_backups', { type: 'global' });
        if (!r.success) { c.innerHTML = '<div class="bk-empty"><i class="bi bi-exclamation-triangle"></i> Erreur</div>'; return; }
        if (!r.backups.length) {
            c.innerHTML = '<div class="bk-empty"><i class="bi bi-globe2"></i>Aucune sauvegarde globale</div>';
            return;
        }
        c.innerHTML = '<ul class="bk-list">' + r.backups.map(b => '<li>' + renderBackupItem(b, true) + '</li>').join('') + '</ul>';
    }

    // ─── Check global access ───
    async function checkGlobalAccess() {
        const r = await adminApiPost('admin_check_backup_access_code', {});
        if (!r.configured) {
            document.getElementById('globalCodeNotSet').style.display = '';
            document.getElementById('globalCodeInput').style.display = 'none';
            document.getElementById('btnUnlockGlobal').style.display = 'none';
        }
    }

    // ─── Unlock global ───
    document.getElementById('btnUnlockGlobal').addEventListener('click', () => {
        const code = document.getElementById('globalCodeInput').value.trim();
        if (!code) return;
        globalAccessCode = code;
        document.getElementById('globalLockScreen').style.display = 'none';
        document.getElementById('globalUnlockedContent').style.display = '';
        loadGlobalBackups();
    });

    // ─── Create user backup ───
    document.getElementById('btnCreateUserBackup').addEventListener('click', async () => {
        const btn = document.getElementById('btnCreateUserBackup');
        btn.disabled = true;
        btn.innerHTML = '<span class="bk-spinner"></span> Creation en cours...';
        const r = await adminApiPost('admin_create_backup', {});
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Creer une sauvegarde';
        if (r.success) {
            toast('Sauvegarde creee avec succes', 'success');
            loadUserBackups();
        } else {
            toast(r.message || 'Erreur', 'danger');
        }
    });

    // ─── Create global backup ───
    document.getElementById('btnCreateGlobalBackup').addEventListener('click', async () => {
        const btn = document.getElementById('btnCreateGlobalBackup');
        btn.disabled = true;
        btn.innerHTML = '<span class="bk-spinner"></span> Creation en cours...';
        const r = await adminApiPost('admin_create_global_backup', {});
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Sauvegarde globale';
        if (r.success) {
            toast('Sauvegarde globale creee', 'success');
            loadGlobalBackups();
        } else {
            toast(r.message || 'Erreur', 'danger');
        }
    });

    // ─── Compare ───
    window.bkCompare = async function(id, isGlobal) {
        showModal('<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button>'
            + '<h6><i class="bi bi-arrow-left-right"></i> Comparaison en cours...</h6>'
            + '<div class="bk-empty"><span class="bk-spinner"></span></div>');

        const r = await adminApiPost('admin_compare_backup', { backup_id: id });
        if (!r.success) { showModal('<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button><p style="color:#cc3333">' + (r.message||'Erreur') + '</p>'); return; }

        let rows = '';
        for (const [table, info] of Object.entries(r.diff)) {
            let cls = 'bk-diff-same';
            let symbol = '=';
            if (info.status === 'added') { cls = 'bk-diff-added'; symbol = '+' + Math.abs(info.delta); }
            else if (info.status === 'removed') { cls = 'bk-diff-removed'; symbol = '-' + Math.abs(info.delta); }
            rows += '<tr><td>' + table + '</td><td>' + info.backup_count + '</td><td>' + info.current_count + '</td><td class="' + cls + '">' + symbol + '</td></tr>';
        }

        // Compatibility info
        let compatHtml = '';
        if (r.compatibility) {
            const c = r.compatibility;
            const vInfo = '<div style="font-size:.78rem; margin-bottom:.75rem; padding:8px 12px; border-radius:8px; '
                + (c.version_match ? 'background:#f0fdf4; color:#166534' : (c.compatible ? 'background:#fffbeb; color:#92400e' : 'background:#fef2f2; color:#991b1b'))
                + '">'
                + '<strong><i class="bi bi-' + (c.version_match ? 'check-circle' : (c.compatible ? 'exclamation-triangle' : 'x-circle')) + '"></i> '
                + 'Schema v' + c.backup_schema_version + ' → v' + c.current_schema_version + '</strong>'
                + (c.version_match ? ' — Compatible' : (c.compatible ? ' — Adaptation automatique' : ' — INCOMPATIBLE'))
                + '</div>';
            compatHtml += vInfo;

            if (c.warnings && c.warnings.length) {
                compatHtml += '<div style="font-size:.75rem; color:#92400e; margin-bottom:.75rem;">';
                c.warnings.forEach(w => { compatHtml += '<div><i class="bi bi-exclamation-triangle"></i> ' + w + '</div>'; });
                compatHtml += '</div>';
            }
            if (c.errors && c.errors.length) {
                compatHtml += '<div style="font-size:.75rem; color:#991b1b; margin-bottom:.75rem;">';
                c.errors.forEach(e => { compatHtml += '<div><i class="bi bi-x-circle-fill"></i> ' + e + '</div>'; });
                compatHtml += '</div>';
            }

            // Table structure diffs
            if (c.table_diffs && Object.keys(c.table_diffs).length) {
                compatHtml += '<details style="font-size:.78rem; margin-bottom:.75rem;"><summary style="cursor:pointer; font-weight:600;"><i class="bi bi-diagram-3"></i> Differences de schema (' + Object.keys(c.table_diffs).length + ' tables)</summary><div style="margin-top:.5rem;">';
                for (const [tbl, diff] of Object.entries(c.table_diffs)) {
                    compatHtml += '<div style="margin-bottom:.4rem;"><strong>' + tbl + '</strong>';
                    if (diff.added_columns && diff.added_columns.length) compatHtml += ' <span style="color:#16a34a;">+' + diff.added_columns.join(', +') + '</span>';
                    if (diff.removed_columns && diff.removed_columns.length) compatHtml += ' <span style="color:#cc3333;">-' + diff.removed_columns.join(', -') + '</span>';
                    if (diff.type_changes) { for (const [col, ch] of Object.entries(diff.type_changes)) { compatHtml += ' <span style="color:#92400e;">' + col + ': ' + ch.from + '→' + ch.to + '</span>'; } }
                    compatHtml += '</div>';
                }
                compatHtml += '</div></details>';
            }
        }

        const restoreDisabled = r.compatibility && !r.compatibility.compatible;

        showModal(
            '<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button>'
            + '<h6><i class="bi bi-arrow-left-right"></i> Comparaison — ' + fmtDate(r.backup_date) + '</h6>'
            + compatHtml
            + '<table class="bk-diff-table"><thead><tr><th>Table</th><th>Sauvegarde</th><th>Actuel</th><th>Diff</th></tr></thead><tbody>' + rows + '</tbody></table>'
            + '<div class="d-flex gap-2 mt-3">'
            + '  <button class="bk-btn bk-btn-primary" onclick="bkRestoreMerge(\'' + id + '\',' + isGlobal + ')"' + (restoreDisabled ? ' disabled title="Version incompatible"' : '') + '><i class="bi bi-plus-circle"></i> Restaurer les differences</button>'
            + '  <button class="bk-btn bk-btn-outline" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">Fermer</button>'
            + '</div>'
        );
    };

    // ─── Restore merge from compare ───
    window.bkRestoreMerge = async function(id, isGlobal) {
        if (isGlobal) {
            bkRestore(id, true, 'merge');
            return;
        }
        closeModal();
        const r = await adminApiPost('admin_restore_backup', { backup_id: id, mode: 'merge' });
        if (r.success) {
            toast(r.message, 'success');
            loadUserBackups();
        } else {
            toast(r.message || 'Erreur', 'danger');
        }
    };

    // ─── Restore (overwrite modal) ───
    window.bkRestore = function(id, isGlobal, forceMode) {
        const mode = forceMode || 'overwrite';
        let extraField = '';
        if (isGlobal) {
            extraField = '<div class="bk-code-box" style="margin-bottom:1rem;"><div class="bk-code-label">Code d\'acces global</div><input type="password" id="restoreGlobalCode" class="form-control form-control-sm bk-code-input mt-1" placeholder="Code d\'acces"></div>';
        }

        if (mode === 'overwrite') {
            showModal(
                '<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button>'
                + '<div class="bk-danger-box">'
                + '  <h6><i class="bi bi-exclamation-triangle-fill"></i> DANGER — Restauration par ecrasement</h6>'
                + '  <p>Cette operation va <strong>ecraser TOUTES les donnees actuelles</strong> ' + (isGlobal ? 'de TOUS les utilisateurs du systeme' : '') + ' avec celles de la sauvegarde selectionnee.</p>'
                + '  <p style="font-weight:700;">Les donnees non sauvegardees seront DEFINITIVEMENT PERDUES.</p>'
                + '  <p>Une sauvegarde automatique de l\'etat actuel sera creee avant l\'ecrasement.</p>'
                + '</div>'
                + extraField
                + '<div style="margin-bottom:1rem;"><label style="font-size:.82rem; font-weight:600;">Tapez <strong>RESTAURER</strong> pour confirmer :</label>'
                + '<input type="text" id="restoreConfirmInput" class="bk-confirm-input mt-1" autocomplete="off" spellcheck="false"></div>'
                + '<div class="d-flex gap-2">'
                + '  <button class="bk-btn bk-btn-danger" id="btnConfirmRestore" disabled><i class="bi bi-exclamation-triangle"></i> Confirmer la restauration</button>'
                + '  <button class="bk-btn bk-btn-outline" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">Annuler</button>'
                + '</div>'
            );
            const inp = document.getElementById('restoreConfirmInput');
            const btn = document.getElementById('btnConfirmRestore');
            inp.addEventListener('input', () => { btn.disabled = inp.value.trim() !== 'RESTAURER'; });
            btn.addEventListener('click', () => doRestore(id, isGlobal, mode));
        } else {
            // merge with global code
            showModal(
                '<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button>'
                + '<h6><i class="bi bi-arrow-counterclockwise"></i> Restauration partielle (merge)</h6>'
                + '<p style="font-size:.85rem;">Les elements manquants seront ajoutes sans modifier les donnees existantes.</p>'
                + extraField
                + '<div class="d-flex gap-2">'
                + '  <button class="bk-btn bk-btn-primary" id="btnConfirmRestore"><i class="bi bi-plus-circle"></i> Restaurer les differences</button>'
                + '  <button class="bk-btn bk-btn-outline" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">Annuler</button>'
                + '</div>'
            );
            document.getElementById('btnConfirmRestore').addEventListener('click', () => doRestore(id, isGlobal, mode));
        }
    };

    async function doRestore(id, isGlobal, mode) {
        const btn = document.getElementById('btnConfirmRestore');
        btn.disabled = true;
        btn.innerHTML = '<span class="bk-spinner"></span> Restauration...';

        let r;
        if (isGlobal) {
            const code = document.getElementById('restoreGlobalCode')?.value || globalAccessCode;
            r = await adminApiPost('admin_restore_global_backup', { backup_id: id, access_code: code, mode: mode });
        } else {
            r = await adminApiPost('admin_restore_backup', { backup_id: id, mode: mode });
        }

        closeModal();
        if (r.success) {
            toast(r.message, 'success');
            if (isGlobal) loadGlobalBackups(); else loadUserBackups();
        } else {
            toast(r.message || 'Erreur de restauration', 'danger');
        }
    }

    // ─── Download ───
    window.bkDownload = function(id) {
        window.open('/newspocspace/admin/api.php?action=admin_download_backup&backup_id=' + id, '_blank');
    };

    // ─── Delete ───
    window.bkDelete = async function(id, isGlobal) {
        showModal(
            '<button class="bk-modal-close" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">&times;</button>'
            + '<h6><i class="bi bi-trash3"></i> Supprimer cette sauvegarde ?</h6>'
            + '<p style="font-size:.85rem;">Cette action est irreversible. Le fichier ZIP sera supprime du serveur.</p>'
            + '<div class="d-flex gap-2">'
            + '  <button class="bk-btn bk-btn-danger" id="btnConfirmDelete"><i class="bi bi-trash3"></i> Supprimer</button>'
            + '  <button class="bk-btn bk-btn-outline" onclick="document.getElementById(\'bkModalContainer\').innerHTML=\'\'">Annuler</button>'
            + '</div>'
        );
        document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
            closeModal();
            const r = await adminApiPost('admin_delete_backup', { backup_id: id });
            if (r.success) {
                toast('Sauvegarde supprimee', 'success');
                if (isGlobal) loadGlobalBackups(); else loadUserBackups();
            } else {
                toast(r.message || 'Erreur', 'danger');
            }
        });
    };

    // ─── Save access code ───
    document.getElementById('btnSaveAccessCode').addEventListener('click', async () => {
        const code = document.getElementById('newAccessCode').value.trim();
        const confirm = document.getElementById('confirmAccessCode').value.trim();
        const status = document.getElementById('accessCodeStatus');

        if (code.length < 6) { status.innerHTML = '<span style="color:#cc3333">Minimum 6 caracteres</span>'; return; }
        if (code !== confirm) { status.innerHTML = '<span style="color:#cc3333">Les codes ne correspondent pas</span>'; return; }

        const r = await adminApiPost('admin_set_backup_access_code', { code: code });
        if (r.success) {
            status.innerHTML = '<span style="color:#16a34a"><i class="bi bi-check-circle"></i> Code enregistre</span>';
            document.getElementById('newAccessCode').value = '';
            document.getElementById('confirmAccessCode').value = '';
        } else {
            status.innerHTML = '<span style="color:#cc3333">' + (r.message||'Erreur') + '</span>';
        }
    });

    // ─── Init ───
    loadUserBackups();
})();
</script>
