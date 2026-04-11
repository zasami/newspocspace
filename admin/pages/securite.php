<?php
// Read current config
$cfg = [];
foreach (Db::fetchAll("SELECT config_key, config_value FROM ems_config WHERE config_key LIKE 'security_%' OR config_key LIKE 'virustotal_%'") as $r) {
    $cfg[$r['config_key']] = $r['config_value'];
}
$vtKey      = $cfg['virustotal_api_key']     ?? '';
$vtEnabled  = ($cfg['virustotal_enabled']    ?? '0') === '1';
$localScan  = ($cfg['security_local_scan']   ?? '1') === '1';

// Masked preview
$vtMasked = $vtKey ? (substr($vtKey, 0, 4) . str_repeat('•', max(0, strlen($vtKey) - 8)) . substr($vtKey, -4)) : '';
?>
<style>
.sec-hero { background:linear-gradient(135deg,#f0f6f4 0%,#f8f4ed 100%); border:1px solid #d4e5df; border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:1rem; }
.sec-hero-icon { width:52px; height:52px; border-radius:14px; background:#2d4a43; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.sec-hero-title { margin:0; font-size:1.05rem; font-weight:700; color:#1a1a1a; }
.sec-hero-sub { font-size:.82rem; color:#6b7280; margin-top:.15rem; }

.sec-card { background:#fff; border:1px solid #f0ede8; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1rem; }
.sec-card h6 { font-size:.95rem; font-weight:700; margin:0 0 .25rem 0; display:flex; align-items:center; gap:.5rem; }
.sec-card .sec-card-desc { font-size:.82rem; color:#6b7280; margin-bottom:1rem; }

.sec-layer-row { display:flex; align-items:flex-start; gap:1rem; padding:.85rem 0; border-top:1px solid #f4f1ec; }
.sec-layer-row:first-of-type { border-top:none; padding-top:0; }
.sec-layer-badge { flex-shrink:0; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; }
.sec-layer-badge.on  { background:#bcd2cb; color:#2d4a43; }
.sec-layer-badge.off { background:#f3f4f6; color:#9ca3af; }
.sec-layer-body { flex:1; min-width:0; }
.sec-layer-title { font-weight:600; font-size:.9rem; color:#1a1a1a; display:flex; align-items:center; gap:.5rem; }
.sec-layer-desc  { font-size:.78rem; color:#6b7280; margin-top:.2rem; line-height:1.5; }
.sec-state-pill { font-size:.7rem; font-weight:600; padding:3px 10px; border-radius:20px; }
.sec-state-pill.on  { background:#bcd2cb; color:#2d4a43; }
.sec-state-pill.off { background:#f3f4f6; color:#6b7280; }

.sec-form-group { margin-top:1rem; }
.sec-form-group label { font-size:.78rem; font-weight:600; color:#374151; display:block; margin-bottom:.35rem; }
.sec-form-group .form-text { font-size:.72rem; color:#9ca3af; margin-top:.25rem; }
.sec-input-wrap { position:relative; }
.sec-input-wrap input { padding-right:90px; font-family:monospace; }
.sec-input-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6b7280; font-size:.75rem; cursor:pointer; padding:4px 8px; border-radius:6px; }
.sec-input-toggle:hover { background:#f3f4f6; }

.sec-link { font-size:.78rem; color:#2d4a43; text-decoration:none; display:inline-flex; align-items:center; gap:.3rem; }
.sec-link:hover { text-decoration:underline; color:#1f3530; }

.sec-btn-save { background:#2d4a43; color:#fff; border:none; border-radius:8px; padding:.5rem 1.25rem; font-weight:500; font-size:.85rem; }
.sec-btn-save:hover { background:#1f3530; color:#fff; }
.sec-btn-test { background:#fff; border:1.5px solid #2d4a43; color:#2d4a43; border-radius:8px; padding:.45rem 1rem; font-weight:500; font-size:.82rem; }
.sec-btn-test:hover { background:#2d4a43; color:#fff; }
</style>

<div class="sec-hero">
  <div class="sec-hero-icon"><i class="bi bi-shield-check"></i></div>
  <div>
    <h5 class="sec-hero-title">Sécurité & Antivirus</h5>
    <div class="sec-hero-sub">Protection des fichiers uploadés (candidatures, documents) — conforme LPD suisse</div>
  </div>
</div>

<!-- État actuel -->
<div class="sec-card">
  <h6><i class="bi bi-layers"></i> Couches de protection actives</h6>
  <div class="sec-card-desc">Chaque fichier uploadé passe par les couches activées ci-dessous.</div>

  <div class="sec-layer-row">
    <div class="sec-layer-badge on">1</div>
    <div class="sec-layer-body">
      <div class="sec-layer-title">
        Validation locale (magic bytes, re-encodage images, scan PDF)
        <span class="sec-state-pill on"><i class="bi bi-check-circle-fill"></i> Activée</span>
      </div>
      <div class="sec-layer-desc">Vérification des signatures binaires réelles, re-encodage complet des images (casse EXIF/steganographie/polyglots), détection de JavaScript et d'actions automatiques dans les PDF. Gratuit, instantané, aucune donnée ne quitte le serveur.</div>
    </div>
  </div>

  <div class="sec-layer-row">
    <div class="sec-layer-badge <?= $vtEnabled ? 'on' : 'off' ?>">2</div>
    <div class="sec-layer-body">
      <div class="sec-layer-title">
        VirusTotal (vérification par hash SHA-256)
        <span class="sec-state-pill <?= $vtEnabled ? 'on' : 'off' ?>"><i class="bi bi-<?= $vtEnabled ? 'check-circle-fill' : 'dash-circle' ?>"></i> <?= $vtEnabled ? 'Activée' : 'Désactivée' ?></span>
      </div>
      <div class="sec-layer-desc">Interrogation de la base VirusTotal (70+ moteurs antivirus) uniquement via le hash SHA-256 du fichier. <strong>Aucune donnée personnelle ne sort du serveur.</strong> Si le hash est connu comme malveillant, le fichier est rejeté immédiatement. Gratuit jusqu'à 500 requêtes/jour.</div>
    </div>
  </div>

  <div class="sec-layer-row">
    <div class="sec-layer-badge on">3</div>
    <div class="sec-layer-body">
      <div class="sec-layer-title">
        Isolation du stockage (.htaccess)
        <span class="sec-state-pill on"><i class="bi bi-check-circle-fill"></i> Activée</span>
      </div>
      <div class="sec-layer-desc">Le dossier <code>storage/candidatures/</code> bloque tout accès HTTP direct et désactive l'exécution PHP/CGI. Les fichiers ne sont téléchargeables que via l'API admin authentifiée.</div>
    </div>
  </div>
</div>

<!-- Config VirusTotal -->
<div class="sec-card">
  <h6><i class="bi bi-key"></i> Clé API VirusTotal</h6>
  <div class="sec-card-desc">
    Enregistrement gratuit sur
    <a href="https://www.virustotal.com/gui/my-apikey" target="_blank" rel="noopener" class="sec-link">
      virustotal.com/gui/my-apikey <i class="bi bi-box-arrow-up-right"></i>
    </a>
    — la clé est stockée chiffrée dans <code>ems_config</code>.
  </div>

  <form id="secForm" onsubmit="return false">
    <div class="sec-form-group">
      <label for="vtKey">Clé API (format : 64 caractères hexadécimaux)</label>
      <div class="sec-input-wrap">
        <input type="password" id="vtKey" class="form-control form-control-sm" autocomplete="off"
               placeholder="<?= $vtKey ? h($vtMasked) : 'Collez votre clé VirusTotal ici' ?>" spellcheck="false">
        <button type="button" class="sec-input-toggle" id="vtKeyToggle" title="Afficher / masquer">
          <i class="bi bi-eye"></i>
        </button>
      </div>
      <div class="form-text">Laissez vide pour conserver la clé actuelle. Ne partagez jamais cette clé.</div>
    </div>

    <div class="sec-form-group">
      <label class="d-flex align-items-center gap-2" style="cursor:pointer">
        <input type="checkbox" id="vtEnabled" <?= $vtEnabled ? 'checked' : '' ?>>
        <span>Activer la vérification VirusTotal au moment de l'upload</span>
      </label>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="sec-btn-save" id="secSaveBtn">
        <i class="bi bi-check-lg"></i> Enregistrer
      </button>
      <button type="button" class="sec-btn-test" id="secTestBtn">
        <i class="bi bi-play-circle"></i> Tester la clé
      </button>
    </div>
  </form>
</div>

<script<?= nonce() ?>>
(function() {
    const vtKeyInput = document.getElementById('vtKey');
    const vtToggle   = document.getElementById('vtKeyToggle');
    const vtEnabled  = document.getElementById('vtEnabled');

    vtToggle.addEventListener('click', () => {
        const isPw = vtKeyInput.type === 'password';
        vtKeyInput.type = isPw ? 'text' : 'password';
        vtToggle.innerHTML = isPw ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });

    document.getElementById('secSaveBtn').addEventListener('click', async () => {
        const values = { virustotal_enabled: vtEnabled.checked ? '1' : '0' };
        const k = (vtKeyInput.value || '').trim();
        if (k) values.virustotal_api_key = k;

        const r = await adminApiPost('admin_save_config', { values });
        if (r.success) {
            showToast('Paramètres de sécurité enregistrés', 'success');
            vtKeyInput.value = '';
        } else {
            showToast(r.message || 'Erreur', 'danger');
        }
    });

    document.getElementById('secTestBtn').addEventListener('click', async () => {
        const btn = document.getElementById('secTestBtn');
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Test en cours...';
        const r = await adminApiPost('admin_test_virustotal', {});
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (r.success) showToast('Clé VirusTotal valide — quota : ' + (r.quota || 'OK'), 'success');
        else showToast(r.message || 'Échec du test', 'danger');
    });
})();
</script>
