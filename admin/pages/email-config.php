<?php
require_once __DIR__ . '/../../core/Mailer.php';
$providers = Mailer::getProviders();
$userId = $_SESSION['zt_user']['id'] ?? '';
$existingConfig = Db::fetch(
    "SELECT id, provider, email_address, display_name, imap_host, imap_port, imap_encryption,
            smtp_host, smtp_port, smtp_encryption, username, signature, is_active, last_sync
     FROM email_externe_config WHERE user_id = ?",
    [$userId]
);
?>
<style>
.emc-card { background: var(--cl-surface); border: 1px solid var(--cl-border-light, #f0ede8); border-radius: 8px; padding: 24px; margin-bottom: 16px; }
.emc-card h6 { font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.emc-card h6 i { color: var(--cl-accent); }
.emc-test-result { margin-top: 12px; padding: 12px; border-radius: 6px; font-size: .85rem; display: none; }
.emc-test-ok { background: #E8F5E9; color: #2E7D32; display: block; }
.emc-test-fail { background: #FFEBEE; color: #C62828; display: block; }
.emc-test-loading { background: #F5F5F5; color: #666; display: flex; align-items: center; gap: 8px; }
.emc-provider-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-bottom: 16px; }
.emc-provider-btn { padding: 12px; border: 2px solid var(--cl-border); border-radius: 8px; background: var(--cl-bg); cursor: pointer; text-align: center; font-size: .85rem; font-weight: 600; transition: all .2s; }
.emc-provider-btn:hover { border-color: var(--cl-accent); }
.emc-provider-btn.active { border-color: var(--cl-accent); background: rgba(25,25,24,.04); }
.emc-provider-btn i { display: block; font-size: 1.5rem; margin-bottom: 4px; }
</style>

<h5 class="mb-3"><i class="bi bi-gear"></i> Configuration Email</h5>

<div class="emc-card">
  <h6><i class="bi bi-building"></i> Fournisseur</h6>
  <div class="emc-provider-grid" id="emcProviderGrid"></div>
</div>

<div class="emc-card">
  <h6><i class="bi bi-person"></i> Compte</h6>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Adresse email</label>
      <input type="email" class="form-control" id="emcEmail" placeholder="prenom.nom@domaine.ch">
    </div>
    <div class="col-md-6">
      <label class="form-label">Nom affiché</label>
      <input type="text" class="form-control" id="emcDisplayName" placeholder="Prénom Nom">
    </div>
    <div class="col-md-6">
      <label class="form-label">Nom d'utilisateur</label>
      <input type="text" class="form-control" id="emcUsername" placeholder="Souvent identique à l'email">
    </div>
    <div class="col-md-6">
      <label class="form-label">Mot de passe <small class="text-muted">(chiffré en base)</small></label>
      <input type="password" class="form-control" id="emcPassword" placeholder="••••••••">
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="emc-card">
      <h6><i class="bi bi-inbox"></i> Serveur IMAP (réception)</h6>
      <div class="row g-2">
        <div class="col-8"><label class="form-label">Serveur</label><input type="text" class="form-control" id="emcImapHost" placeholder="mail.domaine.ch"></div>
        <div class="col-4"><label class="form-label">Port</label><input type="number" class="form-control" id="emcImapPort" value="993"></div>
        <div class="col-12">
          <label class="form-label">Chiffrement</label>
          <select class="form-select" id="emcImapEnc"><option value="ssl">SSL</option><option value="tls">TLS</option><option value="none">Aucun</option></select>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="emc-card">
      <h6><i class="bi bi-send"></i> Serveur SMTP (envoi)</h6>
      <div class="row g-2">
        <div class="col-8"><label class="form-label">Serveur</label><input type="text" class="form-control" id="emcSmtpHost" placeholder="mail.domaine.ch"></div>
        <div class="col-4"><label class="form-label">Port</label><input type="number" class="form-control" id="emcSmtpPort" value="587"></div>
        <div class="col-12">
          <label class="form-label">Chiffrement</label>
          <select class="form-select" id="emcSmtpEnc"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">Aucun</option></select>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="emc-card">
  <h6><i class="bi bi-pen"></i> Signature email</h6>
  <textarea class="form-control" id="emcSignature" rows="3" placeholder="<p>Cordialement,<br>Prénom Nom</p>"></textarea>
  <small class="text-muted">HTML autorisé. Ajoutée automatiquement à chaque email envoyé.</small>
</div>

<div class="d-flex gap-2 mt-3">
  <button class="btn btn-primary" id="emcSaveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
  <button class="btn btn-outline-secondary" id="emcTestBtn"><i class="bi bi-lightning"></i> Tester la connexion</button>
</div>

<div id="emcTestResult" class="emc-test-result"></div>

<script<?= nonce() ?>>
(function() {
    const providers = <?= json_encode($providers, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    const existing = <?= json_encode($existingConfig, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    let selectedProvider = existing?.provider || 'infomaniak';

    const providerIcons = {
        infomaniak: 'bi-cloud', gmail: 'bi-google', outlook: 'bi-microsoft',
        ovh: 'bi-cloud-fill', gandi: 'bi-globe', ionos: 'bi-cloud-haze2',
        bluewin: 'bi-phone', hostpoint: 'bi-flag', custom: 'bi-sliders'
    };

    // Render provider grid
    const grid = document.getElementById('emcProviderGrid');
    Object.entries(providers).forEach(([key, p]) => {
        const btn = document.createElement('div');
        btn.className = 'emc-provider-btn' + (key === selectedProvider ? ' active' : '');
        btn.dataset.provider = key;
        btn.innerHTML = '<i class="bi ' + (providerIcons[key] || 'bi-cloud') + '"></i>' + escapeHtml(p.label);
        btn.addEventListener('click', () => selectProvider(key));
        grid.appendChild(btn);
    });

    function selectProvider(key) {
        selectedProvider = key;
        grid.querySelectorAll('.emc-provider-btn').forEach(b => b.classList.toggle('active', b.dataset.provider === key));
        const p = providers[key];
        if (p.imap_host) document.getElementById('emcImapHost').value = p.imap_host;
        if (p.imap_port) document.getElementById('emcImapPort').value = p.imap_port;
        if (p.imap_encryption) document.getElementById('emcImapEnc').value = p.imap_encryption;
        if (p.smtp_host) document.getElementById('emcSmtpHost').value = p.smtp_host;
        if (p.smtp_port) document.getElementById('emcSmtpPort').value = p.smtp_port;
        if (p.smtp_encryption) document.getElementById('emcSmtpEnc').value = p.smtp_encryption;
    }

    // Populate existing config
    if (existing) {
        document.getElementById('emcEmail').value = existing.email_address || '';
        document.getElementById('emcDisplayName').value = existing.display_name || '';
        document.getElementById('emcUsername').value = existing.username || '';
        document.getElementById('emcImapHost').value = existing.imap_host || '';
        document.getElementById('emcImapPort').value = existing.imap_port || 993;
        document.getElementById('emcImapEnc').value = existing.imap_encryption || 'ssl';
        document.getElementById('emcSmtpHost').value = existing.smtp_host || '';
        document.getElementById('emcSmtpPort').value = existing.smtp_port || 587;
        document.getElementById('emcSmtpEnc').value = existing.smtp_encryption || 'tls';
        document.getElementById('emcSignature').value = existing.signature || '';
    } else {
        // Default: infomaniak
        selectProvider('infomaniak');
    }

    function getFormData() {
        return {
            provider: selectedProvider,
            email_address: document.getElementById('emcEmail').value.trim(),
            display_name: document.getElementById('emcDisplayName').value.trim(),
            username: document.getElementById('emcUsername').value.trim(),
            password: document.getElementById('emcPassword').value,
            imap_host: document.getElementById('emcImapHost').value.trim(),
            imap_port: parseInt(document.getElementById('emcImapPort').value) || 993,
            imap_encryption: document.getElementById('emcImapEnc').value,
            smtp_host: document.getElementById('emcSmtpHost').value.trim(),
            smtp_port: parseInt(document.getElementById('emcSmtpPort').value) || 587,
            smtp_encryption: document.getElementById('emcSmtpEnc').value,
            signature: document.getElementById('emcSignature').value,
        };
    }

    // Save
    document.getElementById('emcSaveBtn')?.addEventListener('click', async () => {
        const data = getFormData();
        if (!data.email_address || !data.username || !data.imap_host || !data.smtp_host) {
            showToast('Remplissez tous les champs obligatoires', 'error');
            return;
        }
        const res = await adminApiPost('admin_email_ext_save_config', data);
        if (res.success) showToast('Configuration enregistrée', 'success');
        else showToast(res.message || 'Erreur', 'error');
    });

    // Test
    document.getElementById('emcTestBtn')?.addEventListener('click', async () => {
        const data = getFormData();
        const resultEl = document.getElementById('emcTestResult');
        resultEl.className = 'emc-test-result emc-test-loading';
        resultEl.style.display = 'flex';
        resultEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Test en cours...';

        const res = await adminApiPost('admin_email_ext_test', data);

        if (!res.success) {
            resultEl.className = 'emc-test-result emc-test-fail';
            resultEl.innerHTML = '<i class="bi bi-x-circle"></i> Erreur : ' + escapeHtml(res.message || 'Erreur inconnue');
            return;
        }

        let html = '';
        if (res.imap?.success) {
            html += '<div><i class="bi bi-check-circle"></i> <strong>IMAP :</strong> Connexion OK — ' + (res.imap.messages || 0) + ' message(s)</div>';
        } else {
            html += '<div style="color:#C62828"><i class="bi bi-x-circle"></i> <strong>IMAP :</strong> ' + escapeHtml(res.imap?.error || 'Échec') + '</div>';
        }
        if (res.smtp?.success) {
            html += '<div><i class="bi bi-check-circle"></i> <strong>SMTP :</strong> Connexion OK</div>';
        } else {
            html += '<div style="color:#C62828"><i class="bi bi-x-circle"></i> <strong>SMTP :</strong> ' + escapeHtml(res.smtp?.error || 'Échec') + '</div>';
        }

        const allOk = res.imap?.success && res.smtp?.success;
        resultEl.className = 'emc-test-result ' + (allOk ? 'emc-test-ok' : 'emc-test-fail');
        resultEl.innerHTML = html;
    });

    window.initEmailconfigPage = () => {};
})();
</script>
