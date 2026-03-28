<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$iaConfigRows = Db::fetchAll("SELECT config_key, config_value FROM ems_config ORDER BY config_key");
$iaConfig = [];
foreach ($iaConfigRows as $r) { $iaConfig[$r['config_key']] = $r['config_value']; }
?>
<style>
/* ─── Pick cards (horaires & modules) ─── */
.ia-pick-card {
    display:inline-flex; flex-direction:column; align-items:center; justify-content:center;
    min-width:72px; padding:10px 14px; border-radius:10px; cursor:pointer;
    border:2px solid var(--pick-bg, var(--pick-color, #6c757d));
    background:var(--pick-bg, color-mix(in srgb, var(--pick-color) 10%, transparent));
    color:var(--pick-color, #6c757d); position:relative; user-select:none; transition:all .25s ease; text-align:center;
}
.ia-pick-card:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.08); }
.ia-pick-card.chosen {
    background:var(--pick-color, var(--cl-text, #1a1a1a));
    border-color:transparent; color:#fff;
    outline:2px solid var(--cl-text, #1a1a1a); outline-offset:2px;
    box-shadow:none;
}
.ia-pick-code { font-weight:700; font-size:.9rem; line-height:1.2; }
.ia-pick-sub { font-size:.7rem; opacity:.8; margin-top:2px; }
.ia-pick-check {
    position:absolute; top:4px; right:5px; font-size:.75rem; opacity:0; transition:opacity .15s;
}
.ia-pick-card.chosen .ia-pick-check { opacity:1; color:#fff; }

/* ─── Importance sliding toggle ─── */
.ia-imp-toggle {
    display:inline-flex; position:relative; background:var(--cl-bg-card, #f3f2ee);
    border:1px solid var(--cl-border, #e5e7eb); border-radius:10px; padding:3px; gap:0;
}
.ia-imp-opt {
    position:relative; z-index:1; border:none; background:none; cursor:pointer;
    padding:8px 20px; border-radius:8px; font-size:.85rem; font-weight:500;
    color:var(--cl-text-muted, #6b7280); transition:color .3s ease; display:flex; align-items:center; gap:6px;
    white-space:nowrap;
}
.ia-imp-opt.active { color:#fff; }
.ia-imp-opt:hover:not(.active) { color:var(--cl-text, #1a1a1a); }
.ia-imp-slider {
    position:absolute; top:3px; left:3px; height:calc(100% - 6px);
    border-radius:8px; transition:all .35s cubic-bezier(.4, 0, .2, 1);
    z-index:0; pointer-events:none;
}
.ia-imp-toggle[data-value="moyen"] .ia-imp-slider {
    background:#3B4F6B; width:var(--slider-w1); transform:translateX(0);
}
.ia-imp-toggle[data-value="important"] .ia-imp-slider {
    background:#7B3B2C; width:var(--slider-w2); transform:translateX(var(--slider-x2));
}

/* ─── Rule list cards ─── */
.ia-rule-card {
    position:relative; padding:16px 16px 16px 20px; border-radius:10px;
    background:var(--cl-bg-card, #fff); border:1px solid var(--cl-border, #e5e7eb);
    transition:all .2s ease; overflow:hidden;
}
.ia-rule-card::before {
    content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
    background:var(--rule-accent, #6c757d); border-radius:10px 0 0 10px;
}
.ia-rule-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.06); border-color:var(--rule-accent, #adb5bd); }
.ia-rule-card.ia-rule-inactive { opacity:.5; }
.ia-rule-card.ia-rule-inactive:hover { opacity:.7; }
.ia-rule-icon {
    width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0;
}
.ia-rule-title { font-weight:500; font-size:.92rem; color:#1a1a1a; }
.ia-rule-inactive .ia-rule-title { color:var(--cl-text-muted, #6b7280); }
.ia-rule-meta { font-size:.78rem; color:var(--cl-text-muted, #6b7280); }
.ia-rule-meta i { opacity:.6; }
.ia-rule-imp {
    display:inline-flex; align-items:center; gap:4px;
    font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em;
    padding:2px 8px; border-radius:20px;
}
.ia-rule-imp-important { background:#E2B8AE; color:#7B3B2C; }
.ia-rule-imp-moyen { background:#B8C9D4; color:#3B4F6B; }
.ia-rule-actions { display:flex; gap:6px; align-items:center; }
.ia-rule-actions .btn { width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:8px; }

/* ─── Reusable icon circles ─── */
.ia-icon-circle { display:flex; align-items:center; justify-content:center; border-radius:50%; flex-shrink:0; }
.ia-icon-circle-lg { width:48px; height:48px; }
.ia-icon-circle-sm { width:36px; height:36px; }

/* ─── Color palette: backgrounds & text ─── */
.ia-bg-green { background:#bcd2cb; }
.ia-text-green { color:#2d4a43; }
.ia-bg-red { background:#E2B8AE; }
.ia-text-red { color:#7B3B2C; }
.ia-bg-blue { background:#B8C9D4; }
.ia-text-blue { color:#3B4F6B; }
.ia-bg-violet { background:#D0C4D8; }
.ia-text-violet { color:#5B4B6B; }
.ia-bg-beige { background:#D4C4A8; }
.ia-text-beige { color:#6B5B3E; }

/* ─── Badge pill (file type markers) ─── */
.ia-badge-pill { font-weight:700; font-size:.7rem; }

/* ─── Reusable badge (component tags) ─── */
.ia-badge { font-size:0.85rem; }
.ia-badge-lg { font-size:0.9rem; }
.ia-badge-sm { font-size:.75rem; }
.ia-badge-xs { font-size:.65rem; }

/* ─── Info boxes ─── */
.ia-box { padding:12px; border-radius:10px; border-width:1px; border-style:solid; }
.ia-box-green { background:#f0f7f4; border-color:#bcd2cb; }
.ia-box-neutral { background:#f9fafb; border-color:#e5e7eb; }
.ia-box-red { background:#fef2f2; border:1px solid #e8b4b4; }
.ia-box-warn { background:#fff8f0; border:1px solid #e5c9a0; }
.ia-box-blue { background:#f0f4f7; border-color:#B8C9D4; }

/* ─── Text sizes ─── */
.ia-fs-xs { font-size:.7rem; }
.ia-fs-sm { font-size:.78rem; }
.ia-fs-md { font-size:.82rem; }
.ia-fs-base { font-size:.85rem; }
.ia-fs-lg { font-size:.9rem; }
.ia-fs-icon { font-size:.9rem; }
.ia-fs-icon-lg { font-size:1.1rem; }
.ia-fs-modal { font-size:0.88rem; }
.ia-fs-empty { font-size:2.5rem; opacity:.25; margin-bottom:8px; }

/* ─── Code tag styling ─── */
.ia-code { background:#f3f2ee; padding:4px 10px; border-radius:6px; font-size:.85rem; }

/* ─── Button download ─── */
.ia-btn-dl { font-weight:500; border-radius:8px; }

/* ─── Table header ─── */
.ia-thead-bg { background:#f9fafb; }
.ia-th-size { width:90px; text-align:right; }
.ia-th-action { width:140px; text-align:center; }
.ia-table-sm-text { font-size:.85rem; }
.ia-table-endpoint { font-size:.82rem; }
.ia-usb-tree { font-size:.82rem; }

/* ─── Engine card label ─── */
.ia-engine-label { border-radius:10px; }
.ia-engine-desc { font-size:.78rem; color:#6b7280; }

/* ─── Server status badge ─── */
.ia-srv-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:20px; font-size:.7rem; font-weight:600; }
.ia-srv-dot { width:7px; height:7px; border-radius:50%; }

/* ─── Server status colors ─── */
.ia-srv-online { background:#e8f5e9; color:#2e7d32; }
.ia-srv-online .ia-srv-dot { background:#4caf50; }
.ia-srv-offline { background:#ffeaea; color:#c62828; }
.ia-srv-offline .ia-srv-dot { background:#ef5350; }
.ia-srv-offline-red { background:#fef2f2; color:#7B3B2C; }
.ia-srv-offline-red .ia-srv-dot { background:#ef5350; }
.ia-srv-checking { background:#f0eeea; color:#8a8680; }
.ia-srv-checking .ia-srv-dot { background:#bbb8b2; }

/* ─── Benchmark result badge ─── */
.ia-bench-ok { background:#e8f5e9; color:#2e7d32; }
.ia-bench-reco { background:#e8f5e9; color:#2e7d32; }

/* ─── Visibility toggles ─── */
.ia-hidden { display:none; }
.ia-visible { display:block; }

/* ─── Disabled overlay ─── */
.ia-disabled { opacity:.4; pointer-events:none; }

/* ─── Key masking ─── */
.ia-key-masked { -webkit-text-security:disc; -moz-text-security:disc; text-security:disc; }
.ia-key-visible { -webkit-text-security:none; -moz-text-security:none; text-security:none; }

/* ─── User list in rule modal ─── */
.ia-user-list { max-height:200px; overflow-y:auto; border:1px solid var(--cl-border); border-radius:var(--cl-radius-sm); padding:4px; }
.ia-user-label { cursor:pointer; font-size:.85rem; }
.ia-user-chk-inline { margin:0; }

/* ─── Heading color ─── */
.ia-heading-dark { color:#1a1a1a; }

/* ─── Inline-style replacements ─── */
.ia-box-warn-alt { background:#fff8f0; border:1px solid #e5c9a0; border-radius:10px; padding:12px; }
.ia-text-beige-dark { color:#6B5B3E; }
.ia-btn-dl-green { background:#bcd2cb; color:#2d4a43; font-weight:500; border-radius:8px; }
.ia-btn-dl-beige { background:#D4C4A8; color:#6B5B3E; font-weight:500; border-radius:8px; }
.ia-btn-dl-red { background:#E2B8AE; color:#7B3B2C; font-weight:500; border-radius:8px; }
.ia-badge-component { font-size:0.85rem; }
.ia-badge-component-green { background:#bcd2cb; color:#2d4a43; }
.ia-badge-component-violet { background:#D0C4D8; color:#5B4B6B; }
.ia-badge-component-blue { background:#B8C9D4; color:#3B4F6B; }
.ia-badge-sm-green { background:#bcd2cb; color:#2d4a43; font-size:.75rem; }
.ia-badge-sm-violet { background:#D0C4D8; color:#5B4B6B; font-size:.75rem; }
.ia-icon-circle-blue { background:#B8C9D4; }
.ia-icon-blue { color:#3B4F6B; font-size:.9rem; }
.ia-icon-green { color:#2d4a43; font-size:1.1rem; }
.ia-icon-violet { color:#5B4B6B; font-size:1.1rem; }
.ia-box-blue-alt { background:#f0f4f7; border:1px solid #B8C9D4; border-radius:10px; padding:12px; }
.ia-box-red-alt { background:#fef2f2; border:1px solid #e8b4b4; }
.ia-engine-label-radius { border-radius:10px; }
.ia-badge-reco-green { background:#bcd2cb; color:#2d4a43; font-size:.7rem; }
.ia-badge-reco-violet { background:#D0C4D8; color:#5B4B6B; font-size:.7rem; }
.ia-question-icon { font-size:.9rem; }
.ia-spinner-xs { width:12px; height:12px; }
.ia-recent-date { font-size:0.75rem; }
</style>

<ul class="nav nav-tabs mb-3" id="iaConfigTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAlgo">
      <i class="bi bi-cpu"></i> Algorithme
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApiKeys">
      <i class="bi bi-key"></i> Clés API
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRules">
      <i class="bi bi-file-text"></i> Règles IA
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTranscription">
      <i class="bi bi-mic"></i> Transcription PV
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDepenses">
      <i class="bi bi-graph-up"></i> Dépenses
    </button>
  </li>
</ul>

<div class="tab-content">

<!-- Tab Transcription -->
<div class="tab-pane fade" id="tabTranscription">
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="ia-icon-circle ia-icon-circle-lg ia-bg-green">
              <i class="bi bi-shield-lock-fill fs-5 ia-text-green"></i>
            </div>
            <div>
              <h5 class="fw-semibold mb-1">Python + Whisper (serveur local)</h5>
              <p class="text-muted small mb-0">La transcription vocale tourne sur l'ordinateur du client via un serveur Python local. Aucun audio ne quitte la machine.</p>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="fw-medium mb-2">Installation requise :</h6>
            <div class="badge mb-2 px-3 py-2 ia-badge-lg ia-bg-beige ia-text-beige">
              <i class="bi bi-usb-drive me-2"></i> Clé USB ZerdaTime IA — exécution unique par poste
            </div>
            <p class="small text-muted mb-3">Chaque ordinateur qui fera de la transcription doit exécuter l'installation une seule fois depuis la clé USB fournie. Elle installe Vosk (transcription), Ollama + 3 modèles IA (structuration) et crée un raccourci Bureau.</p>

            <!-- Contenu clé USB -->
            <div class="ia-box ia-box-green mb-3">
              <p class="small fw-semibold mb-2 ia-text-green"><i class="bi bi-usb-drive me-1"></i> Contenu de la clé USB :</p>
              <div class="d-flex flex-column gap-1 ia-fs-md">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-green ia-text-green">ZIP</span>
                  <code>ZerdaTime-IA-Install.zip</code> <span class="text-muted">— ~11 Go (tout inclus)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-beige ia-text-beige">BAT</span>
                  <code>install.bat</code> <span class="text-muted">— lance l'installation</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-violet ia-text-violet">BAT</span>
                  <code>start-zerdatime-ia.bat</code> <span class="text-muted">— lanceur serveurs (copié auto)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-red ia-text-red">BAT</span>
                  <code>uninstall.bat</code> <span class="text-muted">— désinstallation complète</span>
                </div>
              </div>
            </div>

            <!-- Instructions installation -->
            <div class="ia-box ia-box-neutral mb-3">
              <p class="small fw-semibold mb-2 ia-heading-dark"><i class="bi bi-play-circle me-1"></i> Installation (une seule fois par poste) :</p>
              <ol class="small text-muted ps-3 mb-0 ia-fs-md">
                <li class="mb-1">Branchez la <strong>clé USB ZerdaTime IA</strong> sur le poste</li>
                <li class="mb-1">Double-cliquez sur <code>install.bat</code> directement depuis la clé</li>
                <li>Attendez la fin — un raccourci <strong>« ZerdaTime IA »</strong> apparaît sur le Bureau</li>
              </ol>
            </div>

            <!-- Utilisation quotidienne -->
            <div class="ia-box ia-box-green mb-3">
              <p class="small fw-semibold mb-2 ia-text-green"><i class="bi bi-arrow-repeat me-1"></i> Utilisation quotidienne :</p>
              <ol class="small text-muted ps-3 mb-0 ia-fs-md">
                <li class="mb-1">Double-cliquez sur le raccourci <strong>« ZerdaTime IA »</strong> sur le Bureau</li>
                <li class="mb-1">Les serveurs Vosk + Ollama démarrent automatiquement</li>
                <li>Ouvrez ZerdaTime dans le navigateur et commencez la dictée</li>
              </ol>
              <p class="small text-muted mb-0 mt-2 ia-fs-sm"><i class="bi bi-info-circle me-1"></i> La clé USB n'est <strong>plus nécessaire</strong> après l'installation. Conservez-la pour installer d'autres postes ou réinstaller.</p>
            </div>

            <!-- Désinstallation -->
            <div class="ia-box ia-box-red mb-3">
              <p class="small fw-semibold mb-2 ia-text-red"><i class="bi bi-trash3 me-1"></i> Désinstallation complète :</p>
              <p class="small text-muted mb-2 ia-fs-md">Supprime Ollama, les 3 modèles IA, Vosk, Python et les raccourcis Bureau.</p>
              <ol class="small text-muted ps-3 mb-2 ia-fs-md">
                <li class="mb-1">Branchez la clé USB ou <a href="/zerdatime/whisper-local/download.php?file=uninstall.bat" class="fw-medium ia-text-red">téléchargez uninstall.bat</a></li>
                <li>Double-cliquez sur <code>uninstall.bat</code> et confirmez avec <strong>O</strong></li>
              </ol>
              <p class="small text-muted mb-0 ia-fs-sm">Pour réinstaller après désinstallation, relancez <code>install.bat</code> depuis la clé USB.</p>
            </div>

            <!-- Dépannage -->
            <div class="ia-box-warn-alt">
              <p class="small fw-semibold mb-2 ia-text-beige-dark"><i class="bi bi-wrench me-1"></i> Dépannage — Le raccourci Bureau ne démarre pas ?</p>
              <p class="small text-muted mb-2 ia-fs-md">Téléchargez ce fichier et copiez-le dans <code>%LOCALAPPDATA%\ZerdaTimeWhisper\</code> pour remplacer l'ancien :</p>
              <a href="/zerdatime/whisper-local/download.php?file=start-zerdatime-ia.bat" class="btn btn-sm d-inline-flex align-items-center gap-1 ia-btn-dl-beige"><i class="bi bi-download"></i> start-zerdatime-ia.bat</a>
            </div>

            <!-- Téléchargements admin -->
            <div class="mt-3">
              <p class="small fw-semibold mb-2 ia-heading-dark"><i class="bi bi-cloud-download me-1"></i> Téléchargements (préparer la clé USB) :</p>
              <table class="table table-sm align-middle mb-0 ia-table-sm-text">
                <thead>
                  <tr class="ia-thead-bg">
                    <th>Fichier</th>
                    <th class="ia-th-size">Taille</th>
                    <th class="ia-th-action">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>ZerdaTime-IA-Install.zip</strong><br><small class="text-muted">Scripts, Ollama, 3 modèles IA, Vosk, Python, ffmpeg</small></td>
                    <td class="text-end text-muted">~11 Go</td>
                    <td class="text-center"><a href="/zerdatime/whisper-local/download.php?file=ZerdaTime-IA-Install.zip" class="btn btn-sm d-inline-flex align-items-center gap-1 ia-btn-dl-green"><i class="bi bi-download"></i> ZIP</a></td>
                  </tr>
                  <tr>
                    <td><strong>install.bat</strong><br><small class="text-muted">Lanceur d'installation</small></td>
                    <td class="text-end text-muted">~1 Ko</td>
                    <td class="text-center"><a href="/zerdatime/whisper-local/download.php?file=install.bat" class="btn btn-sm d-inline-flex align-items-center gap-1 ia-btn-dl-beige"><i class="bi bi-terminal"></i> BAT</a></td>
                  </tr>
                  <tr>
                    <td><strong>uninstall.bat</strong><br><small class="text-muted">Désinstallation complète</small></td>
                    <td class="text-end text-muted">~1 Ko</td>
                    <td class="text-center"><a href="/zerdatime/whisper-local/download.php?file=uninstall.bat" class="btn btn-sm d-inline-flex align-items-center gap-1 ia-btn-dl-red"><i class="bi bi-trash3"></i> BAT</a></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="fw-medium mb-2">Composants installés :</h6>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <div class="badge px-3 py-2 ia-badge-component ia-badge-component-green">
                <i class="bi bi-lightning-charge me-1"></i> Vosk — Transcription rapide (CPU)
              </div>
              <div class="badge px-3 py-2 ia-badge-component ia-badge-component-violet">
                <i class="bi bi-stars me-1"></i> Whisper — Transcription précise (optionnel)
              </div>
              <div class="badge px-3 py-2 ia-badge-component ia-badge-component-blue">
                <i class="bi bi-magic me-1"></i> Ollama + 3 modèles IA — Structuration PV
              </div>
            </div>
            <p class="small text-muted">Vosk ou Whisper transcrit la voix en texte brut. Ollama restructure le texte en PV professionnel. Tout reste en local, aucune donnée ne quitte la machine.</p>
          </div>

          <div class="mb-0">
            <h6 class="fw-medium mb-2">Serveurs locaux :</h6>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <code class="ia-code">localhost:5876</code>
              <span class="badge ia-badge-sm-green">Vosk</span>
              <code class="ia-code">localhost:59876</code>
              <span class="badge ia-badge-sm-violet">Ollama</span>
            </div>
            <p class="small text-muted mb-0">Les serveurs doivent être lancés avant de commencer une dictée. Le raccourci « ZerdaTime IA » lance les deux ensemble.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <!-- Mode serveur externe -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
              <div class="ia-icon-circle ia-icon-circle-sm ia-icon-circle-blue">
                <i class="bi bi-cloud-fill ia-icon-blue"></i>
              </div>
              <h6 class="fw-semibold mb-0">Mode serveur externe</h6>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="pvExternalMode">
            </div>
          </div>

          <p class="text-muted small mb-3">Utilise des API cloud au lieu du serveur local. Pas besoin d'installer quoi que ce soit sur le poste.</p>

          <div id="externalModeDetails" class="ia-hidden">
            <div class="ia-box-blue-alt mb-3">
              <div class="d-flex flex-column gap-2 ia-fs-md">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-blue ia-text-blue">1</span>
                  <span><strong>Transcription</strong> — Deepgram Nova-2 (cloud, $200 offerts)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill ia-badge-pill ia-bg-violet ia-text-violet">2</span>
                  <span><strong>Structuration</strong> — Claude ou Gemini (selon config Clés API)</span>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-medium" for="deepgramApiKey">
                <i class="bi bi-key me-1"></i> Clé API Deepgram <small class="text-muted">(transcription audio)</small>
              </label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control ia-key-masked" id="deepgramApiKey" placeholder="dg_...">
                <button class="btn btn-outline-secondary btn-toggle-key" type="button" data-target="deepgramApiKey"><i class="bi bi-eye"></i></button>
              </div>
              <div class="form-text ia-fs-xs">
                Obtenez votre clé sur <a href="https://console.deepgram.com/signup" target="_blank" rel="noopener">console.deepgram.com</a>.
                <strong>$200 de crédit offerts</strong> à l'inscription (~770h de transcription).
              </div>
            </div>

            <div class="p-2 rounded small mb-3 ia-box-warn">
              <i class="bi bi-exclamation-triangle me-1 ia-text-beige-dark"></i>
              <span class="text-muted">En mode externe, l'audio est envoyé à Deepgram et le texte à Claude/Gemini. Les données <strong>quittent la machine</strong>.</span>
            </div>

            <button class="btn btn-primary btn-sm" id="externalModeSaveBtn">
              <i class="bi bi-check-lg me-1"></i>Enregistrer le mode externe
            </button>
          </div>

          <div id="externalModeOff" class="small text-muted">
            <i class="bi bi-info-circle me-1"></i> Activez le mode externe pour utiliser des API cloud (OpenAI + Claude/Gemini) sans serveur local.
          </div>
        </div>
      </div>

      <!-- Moteur de transcription (local) -->
      <div class="card mb-3" id="localEngineCard">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <h6 class="fw-semibold mb-0"><i class="bi bi-soundwave"></i> Moteur de transcription local</h6>
            <span class="ia-srv-badge ia-srv-checking" id="badgeTranscriptionEngine" data-status="checking">
              <span class="ia-srv-dot"></span>
              <span class="srv-status">…</span>
            </span>
          </div>
          <p class="text-muted small mb-3">Choisissez le moteur de transcription selon la puissance de l'ordinateur. Le serveur local supporte les deux moteurs.</p>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <input type="radio" class="btn-check" name="transcription_engine" value="vosk" id="engine-vosk" checked>
              <label class="btn btn-outline-secondary w-100 text-start p-3 ia-engine-label" for="engine-vosk">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-lightning-charge-fill ia-icon-green"></i>
                  <strong class="ia-fs-lg">Vosk</strong>
                </div>
                <div class="ia-engine-desc">
                  <div class="mb-1"><i class="bi bi-speedometer2 me-1"></i> Rapide, temps réel</div>
                  <div class="mb-1"><i class="bi bi-cpu me-1"></i> Léger (fonctionne sur tout PC)</div>
                  <div><i class="bi bi-translate me-1"></i> Qualité correcte</div>
                </div>
                <div class="mt-2">
                  <span class="badge ia-badge-reco-green">Recommandé</span>
                </div>
              </label>
            </div>
            <div class="col-6">
              <input type="radio" class="btn-check" name="transcription_engine" value="whisper" id="engine-whisper">
              <label class="btn btn-outline-secondary w-100 text-start p-3 ia-engine-label" for="engine-whisper">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-stars ia-icon-violet"></i>
                  <strong class="ia-fs-lg">Whisper</strong>
                </div>
                <div class="ia-engine-desc">
                  <div class="mb-1"><i class="bi bi-trophy me-1"></i> Haute précision</div>
                  <div class="mb-1"><i class="bi bi-gpu-card me-1"></i> Nécessite plus de puissance</div>
                  <div><i class="bi bi-clock-history me-1"></i> Plus lent (non temps réel)</div>
                </div>
                <div class="mt-2">
                  <span class="badge ia-badge-reco-violet">Meilleure qualité</span>
                </div>
              </label>
            </div>
          </div>

          <div id="whisperWarning" class="p-2 rounded small mb-3 ia-box-warn ia-hidden">
            <i class="bi bi-exclamation-triangle me-1 ia-text-beige-dark"></i>
            <span class="text-muted">Whisper nécessite le package <code>faster-whisper</code> installé sur le poste. Le premier appel télécharge le modèle (~140 Mo).</span>
          </div>

          <div id="whisperNotInstalled" class="p-2 rounded small mb-3 ia-box-red ia-hidden">
            <i class="bi bi-x-circle me-1 ia-text-red"></i>
            <span class="ia-text-red">Whisper n'est pas installé sur ce poste. Seul Vosk est disponible.</span>
          </div>

          <button class="btn btn-primary btn-sm" id="engineSaveBtn">
            <i class="bi bi-check-lg me-1"></i>Enregistrer le moteur
          </button>
        </div>
      </div>

      <div class="card">
        <div class="card-body small text-muted">
          <h6 class="fw-semibold text-dark mb-2">Comment ça marche ?</h6>
          <ol class="ps-3 mb-3">
            <li class="mb-2">L'administrateur prépare une <strong>clé USB ZerdaTime IA</strong> avec le ZIP extrait + <code>install.bat</code> + <code>uninstall.bat</code>.</li>
            <li class="mb-2">Sur chaque poste, brancher la clé et double-cliquer sur <code>install.bat</code> — tout s'installe automatiquement (Python, ffmpeg, Ollama, 3 modèles IA, Vosk). Un raccourci <strong>« ZerdaTime IA »</strong> est créé sur le Bureau.</li>
            <li class="mb-2">Retirer la clé USB — elle n'est plus nécessaire. La conserver pour les prochaines installations.</li>
            <li class="mb-2">Avant de dicter, l'utilisateur double-clique sur le raccourci Bureau — les deux serveurs démarrent automatiquement.</li>
            <li class="mb-2">L'enregistrement capture l'audio, Vosk transcrit en temps réel, le texte s'affiche dans l'éditeur.</li>
            <li>Clic sur <strong>« Structurer le PV »</strong> — le modèle IA (via Ollama) reformate le texte brut en PV professionnel avec titres, sections et points clés.</li>
          </ol>
          <h6 class="fw-semibold text-dark mb-2">Endpoints des serveurs</h6>
          <table class="table table-sm mb-0 ia-table-endpoint">
            <tr><td><code>GET :5876/health</code></td><td>Vérifie que Vosk tourne</td></tr>
            <tr><td><code>POST :5876/transcribe</code></td><td>Envoie l'audio, reçoit le texte</td></tr>
            <tr><td><code>GET :59876/api/tags</code></td><td>Vérifie qu'Ollama tourne + modèles disponibles</td></tr>
            <tr><td><code>POST :59876/api/generate</code></td><td>Envoie le texte brut, reçoit le PV structuré</td></tr>
          </table>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body small">
          <h6 class="fw-semibold text-dark mb-2"><i class="bi bi-usb-drive"></i> Structure de la clé USB</h6>
          <div class="text-muted ia-usb-tree">
            <div class="mb-1"><code>📁 Clé USB/</code></div>
            <div class="mb-1 ps-3"><code>install.bat</code> — Lance l'installation</div>
            <div class="mb-1 ps-3"><code>uninstall.bat</code> — Désinstallation complète</div>
            <div class="mb-1 ps-3"><code>📁 ZerdaTime-IA/</code> — Dossier extrait du ZIP</div>
            <div class="mb-1 ps-4"><code>install-whisper.ps1</code> — Script PowerShell</div>
            <div class="mb-1 ps-4"><code>whisper_server.py</code> — Serveur Vosk</div>
            <div class="mb-1 ps-4"><code>OllamaSetup.exe</code> — Installeur Ollama</div>
            <div class="mb-1 ps-4"><code>📁 ollama-model/</code> — 3 modèles pré-téléchargés</div>
            <div class="mb-1 ps-4"><code>📁 vosk-model-fr-0.22/</code> — Modèle vocal français</div>
            <div class="mb-1 ps-4"><code>python-3.11.9-embed-amd64.zip</code></div>
            <div class="ps-4"><code>ffmpeg.zip</code></div>
          </div>
          <hr class="my-2">
          <div class="text-muted ia-usb-tree">
            <div class="fw-medium mb-1 ia-heading-dark">Après installation sur le poste :</div>
            <div><code>%LOCALAPPDATA%\ZerdaTimeWhisper\</code> — Tout est copié ici</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /tabTranscription -->

<!-- Tab Algorithme -->
<div class="tab-pane fade show active" id="tabAlgo">
<p class="text-muted mb-3 ia-fs-base">Paramètres de l'algorithme de génération automatique du planning</p>
<div class="row g-3">
  <!-- Heures & Jours -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-clock"></i> Calcul des heures cibles</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="heures"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <p class="text-muted ia-fs-md">
          Heures cibles mensuelles = <strong>jours ouvrés × heures/jour × taux%</strong>
        </p>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Jours ouvrés / mois</label>
            <input type="number" class="form-control" id="iaJoursOuvres" step="0.1" min="15" max="30" value="21.7">
            <div class="form-text">Moy. jours travaillés/mois</div>
          </div>
          <div class="col-6">
            <label class="form-label">Heures / jour</label>
            <input type="number" class="form-control" id="iaHeuresJour" step="0.1" min="4" max="14" value="8.4">
            <div class="form-text">Durée moy. journée de travail</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Repos & limites -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-shield-check"></i> Repos & Limites</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="repos"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Jours consécutifs max</label>
            <input type="number" class="form-control" id="iaConsecutifMax" min="3" max="10" value="5">
            <div class="form-text">Repos obligatoire après X jours</div>
          </div>
          <div class="col-6">
            <label class="form-label">Consécutif max (besoins)</label>
            <input type="number" class="form-control" id="iaConsecutifMaxBesoins" min="3" max="10" value="6">
            <div class="form-text">Limite dans la boucle besoins</div>
          </div>
        </div>
        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" id="iaDirectionWeekendOff" checked>
          <label class="form-check-label" for="iaDirectionWeekendOff">Direction & responsables off le weekend</label>
        </div>
      </div>
    </div>
  </div>

  <!-- Scoring -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-bar-chart"></i> Scoring des candidats</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="scoring"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <p class="text-muted ia-fs-md">
          Score = <strong>(heures_cibles − heures_actuelles) + bonus_module + aléatoire</strong>
        </p>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Bonus module principal</label>
            <input type="number" class="form-control" id="iaBonusPrincipal" min="0" max="50" value="10">
            <div class="form-text">Points bonus si module principal</div>
          </div>
          <div class="col-6">
            <label class="form-label">Facteur aléatoire max</label>
            <input type="number" class="form-control" id="iaRandomMax" min="0" max="20" value="3">
            <div class="form-text">rand(0, X) ajouté au score</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weekend -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-calendar-week"></i> Gestion du weekend</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="weekend"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Prob. repos weekend</label>
            <input type="number" class="form-control" id="iaWeekendSkipProb" min="0" max="100" value="66" step="1">
            <div class="form-text">% de chance de repos le weekend</div>
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="text-muted ia-fs-md">
              Plus le % est élevé, moins d'employés travaillent le weekend (hors besoins obligatoires).
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Désirs -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-star"></i> Désirs employés</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="desirs"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-4">
            <label class="form-label">Max désirs / mois</label>
            <input type="number" class="form-control" id="iaDesirMax" min="1" max="15" value="4">
          </div>
          <div class="col-4">
            <label class="form-label">Ouverture (jour)</label>
            <input type="number" class="form-control" id="iaDesirOuverture" min="1" max="28" value="1">
            <div class="form-text">Jour du mois</div>
          </div>
          <div class="col-4">
            <label class="form-label">Fermeture (jour)</label>
            <input type="number" class="form-control" id="iaDesirFermeture" min="1" max="28" value="10">
            <div class="form-text">Jour du mois</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Classification horaires -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex align-items-center"><h6 class="mb-0"><i class="bi bi-clock-history"></i> Classification horaires</h6><button class="btn btn-sm p-0 ms-auto text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="horaires"><i class="bi bi-question-circle ia-question-icon"></i></button></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Seuil soir (heure début ≥)</label>
            <input type="number" class="form-control" id="iaSeuilSoir" min="10" max="18" value="13">
            <div class="form-text">Début ≥ Xh → horaire du soir</div>
          </div>
          <div class="col-6">
            <label class="form-label">Seuil nuit (durée ≥)</label>
            <input type="number" class="form-control" id="iaSeuilNuit" min="8" max="16" step="0.5" value="10">
            <div class="form-text">Durée ≥ Xh → horaire de nuit</div>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Code horaire admin/direction</label>
          <input type="text" class="form-control" id="iaAdminShiftCode" maxlength="10" value="A6" placeholder="A6">
          <div class="form-text">Code horaire attribué à la direction & responsables</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="mt-3">
  <button class="btn btn-primary" id="iaSaveBtn">
    <i class="bi bi-check-lg"></i> Enregistrer les paramètres
  </button>
  <button class="btn btn-outline-secondary ms-2" id="iaResetBtn">
    <i class="bi bi-arrow-counterclockwise"></i> Valeurs par défaut
  </button>
</div>
</div><!-- /tabAlgo -->

<!-- Tab API Keys -->
<div class="tab-pane fade" id="tabApiKeys">
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fw-semibold mb-0">Configuration IA</h5>
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="api"><i class="bi bi-question-circle ia-question-icon"></i></button>
              <span class="badge bg-secondary" id="apiKeyStatus">Chargement...</span>
            </div>
          </div>

          <!-- Provider toggle -->
          <div class="mb-3">
            <label class="form-label small fw-medium">Fournisseur actif</label>
            <div class="d-flex gap-2">
              <input type="radio" class="btn-check" name="ai_provider" value="gemini" id="prov-gemini">
              <label class="btn btn-outline-primary btn-sm flex-fill" for="prov-gemini">
                <i class="bi bi-stars me-1"></i>Google Gemini
              </label>
              <input type="radio" class="btn-check" name="ai_provider" value="claude" id="prov-claude">
              <label class="btn btn-outline-primary btn-sm flex-fill" for="prov-claude">
                <i class="bi bi-robot me-1"></i>Anthropic Claude
              </label>
            </div>
          </div>

          <hr>

          <!-- Gemini section -->
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <h6 class="fw-semibold mb-0"><i class="bi bi-stars"></i> Google Gemini</h6>
              <span class="badge ia-badge-xs" id="geminiKeyBadge"></span>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-medium">Clé API Gemini</label>
              <div class="input-group">
                <input type="text" class="form-control ia-key-masked" id="geminiApiKey" placeholder="AIza..." autocomplete="new-password" data-1p-ignore data-lpignore="true" data-form-type="other">
                <button type="button" class="btn btn-outline-secondary btn-toggle-key" data-target="geminiApiKey">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">Obtenez votre clé sur <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com</a></div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-medium">Modèle Gemini</label>
              <div class="zs-select" id="geminiModel" data-placeholder="Modèle Gemini"></div>
            </div>
          </div>

          <hr>

          <!-- Claude section -->
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <h6 class="fw-semibold mb-0"><i class="bi bi-robot"></i> Anthropic Claude</h6>
              <span class="badge ia-badge-xs" id="claudeKeyBadge"></span>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-medium">Clé API Anthropic</label>
              <div class="input-group">
                <input type="text" class="form-control ia-key-masked" id="anthropicApiKey" placeholder="sk-ant-..." autocomplete="new-password" data-1p-ignore data-lpignore="true" data-form-type="other">
                <button type="button" class="btn btn-outline-secondary btn-toggle-key" data-target="anthropicApiKey">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">Obtenez votre clé sur <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a></div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-medium">Modèle Claude</label>
              <div class="zs-select" id="anthropicModel" data-placeholder="Modèle Claude"></div>
            </div>
          </div>

          <button class="btn btn-primary btn-sm" id="apiKeySaveBtn">
            <i class="bi bi-check-lg me-1"></i>Enregistrer la configuration
          </button>
        </div>
      </div>

      <!-- Ollama Local Model -->
      <div class="card mt-3">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <h6 class="fw-semibold mb-0"><i class="bi bi-cpu"></i> Modèle IA local (Ollama)</h6>
            <span class="ia-srv-badge ia-srv-checking" id="badgeOllamaConfig" data-status="checking">
              <span class="ia-srv-dot"></span>
              <span class="srv-status">…</span>
            </span>
          </div>
          <p class="text-muted small mb-3">Modèle utilisé pour la structuration des PV. Le benchmark teste chaque modèle installé et recommande le plus adapté à votre machine.</p>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-medium">Modèle actif</label>
              <div class="zs-select" id="ollamaModel" data-placeholder="Modèle Ollama"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-medium">Benchmark</label>
              <button class="btn btn-outline-secondary btn-sm w-100" id="btnBenchmark">
                <i class="bi bi-speedometer2 me-1"></i> Tester les performances
              </button>
            </div>
          </div>

          <!-- Benchmark results -->
          <div id="benchmarkResults" class="ia-hidden">
            <div class="small fw-medium mb-2">Résultats du benchmark :</div>
            <div id="benchmarkList"></div>
            <div id="benchmarkRecommendation" class="mt-2 p-2 rounded small fw-medium ia-bench-reco ia-hidden"></div>
          </div>

          <button class="btn btn-primary btn-sm mt-2" id="ollamaModelSaveBtn">
            <i class="bi bi-check-lg me-1"></i>Enregistrer le modèle
          </button>
        </div>
      </div>

      <!-- Pricing -->
      <div class="card mt-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Coût estimé par génération de planning</h6>
          <div class="small text-muted">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span><i class="bi bi-stars me-1"></i>Gemini 2.0 Flash Lite</span>
              <span class="fw-medium text-success">~$0.001</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span><i class="bi bi-stars me-1"></i>Gemini 2.5 Flash</span>
              <span class="fw-medium text-success">~$0.002</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span><i class="bi bi-robot me-1"></i>Claude Haiku 4.5</span>
              <span class="fw-medium">~$0.008</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span><i class="bi bi-robot me-1"></i>Claude Sonnet 4.5</span>
              <span class="fw-medium">~$0.025</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span><i class="bi bi-robot me-1"></i>Claude Opus 4.6</span>
              <span class="fw-medium text-danger">~$0.120</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h5 class="fw-semibold mb-3">Comment ça fonctionne</h5>
          <div class="text-muted ia-fs-base">
            <p><strong>L'IA assiste la génération de planning</strong> en analysant les contraintes complexes :</p>
            <ul>
              <li>Équilibrage des heures entre employés selon leur taux</li>
              <li>Respect des désirs validés (jours off, horaires spéciaux)</li>
              <li>Couverture optimale des besoins par module et fonction</li>
              <li>Gestion des repos consécutifs et rotations weekend</li>
            </ul>
            <p class="mb-0">L'algorithme actuel fonctionne <strong>sans IA</strong> (règles déterministes + scoring). L'intégration IA permettra une optimisation plus fine avec résolution de conflits intelligente.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /tabApiKeys -->

<!-- Tab Règles IA -->
<div class="tab-pane fade" id="tabRules">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <p class="text-muted mb-0 ia-fs-base">Gérez les règles de langue humaine utilisées par l'IA pour la génération de planning</p>
      <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="modal" data-bs-target="#infoModal" data-info="regles"><i class="bi bi-question-circle ia-question-icon"></i></button>
    </div>
    <button class="btn btn-primary btn-sm" id="iaRuleAddBtn">
      <i class="bi bi-plus-lg"></i> Ajouter une règle
    </button>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div id="iaRulesContainer">
        <div class="text-center text-muted py-4">
          <span class="spinner-border spinner-border-sm me-2"></span>Chargement...
        </div>
      </div>
    </div>
  </div>
</div><!-- /tabRules -->

<!-- Tab Dépenses -->
<div class="tab-pane fade" id="tabDepenses">
  <div class="row g-3 mb-4">
    <!-- Stat cards -->
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-muted small mb-1">Générations ce mois</div>
          <div class="fs-3 fw-bold" id="depMonthCount">—</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-muted small mb-1">Coût ce mois</div>
          <div class="fs-3 fw-bold text-primary" id="depMonthCost">—</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-muted small mb-1">Générations cette année</div>
          <div class="fs-3 fw-bold" id="depYearCount">—</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body text-center">
          <div class="text-muted small mb-1">Coût annuel <span id="depYear"></span></div>
          <div class="fs-3 fw-bold text-danger" id="depYearCost">—</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Monthly breakdown table -->
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-calendar3"></i> Détail mensuel</h6>
          <button class="btn btn-outline-secondary btn-sm" id="depRefreshBtn">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th>Mois</th>
                  <th class="text-center">Générations</th>
                  <th class="text-center">Assignations</th>
                  <th class="text-center">Tokens (IN/OUT)</th>
                  <th class="text-end">Coût</th>
                  <th class="text-center">Durée moy.</th>
                </tr>
              </thead>
              <tbody id="depMonthlyBody">
                <tr><td colspan="6" class="text-center text-muted py-3">Chargement...</td></tr>
              </tbody>
              <tfoot id="depMonthlyFoot" class="table-light fw-bold">
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent generations -->
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-clock-history"></i> Dernières générations</h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush" id="depRecentList">
            <div class="list-group-item text-center text-muted py-3">Chargement...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /tabDepenses -->

</div><!-- /tab-content -->

<!-- Info Modal -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-semibold" id="infoModalTitle"></h6>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body ia-fs-modal" id="infoModalBody"></div>
    </div>
  </div>
</div>

<!-- Rule Modal -->
<div class="modal fade" id="iaRuleModal" tabindex="-1" aria-labelledby="iaRuleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="iaRuleModalLabel"><i class="bi bi-file-text me-2"></i>Ajouter une règle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <form id="iaRuleForm">
          <!-- Titre -->
          <div class="mb-3">
            <label class="form-label fw-medium">Titre</label>
            <input type="text" class="form-control" id="iaRuleTitre" placeholder="Ex: Équipe nuit — horaires nuit seulement" maxlength="255" required>
          </div>

          <!-- Importance — sliding toggle -->
          <div class="mb-3">
            <label class="form-label fw-medium">Importance</label>
            <div class="ia-imp-toggle" id="iaRuleImpToggle">
              <div class="ia-imp-slider" id="iaImpSlider"></div>
              <button type="button" class="ia-imp-opt" data-value="moyen">
                <i class="bi bi-info-circle"></i> Moyen
              </button>
              <button type="button" class="ia-imp-opt active" data-value="important">
                <i class="bi bi-exclamation-triangle-fill"></i> Important
              </button>
            </div>
          </div>

          <!-- Rule type — radio cards -->
          <div class="mb-3">
            <label class="form-label fw-medium">Type de règle</label>
            <div class="d-flex flex-wrap gap-2" id="iaRuleTypeCards">
              <div class="ia-pick-card chosen" data-value="shift_only" data-group="ruletype" style="--pick-color:#2d4a43;--pick-bg:#bcd2cb">
                <span class="ia-pick-code"><i class="bi bi-clock"></i></span>
                <span class="ia-pick-sub">Horaires autorisés</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="shift_exclude" data-group="ruletype" style="--pick-color:#6B5B3E;--pick-bg:#D4C4A8">
                <span class="ia-pick-code"><i class="bi bi-clock-history"></i></span>
                <span class="ia-pick-sub">Horaires interdits</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="module_only" data-group="ruletype" style="--pick-color:#3B4F6B;--pick-bg:#B8C9D4">
                <span class="ia-pick-code"><i class="bi bi-building"></i></span>
                <span class="ia-pick-sub">Modules autorisés</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="module_exclude" data-group="ruletype" style="--pick-color:#7B3B2C;--pick-bg:#E2B8AE">
                <span class="ia-pick-code"><i class="bi bi-building-x"></i></span>
                <span class="ia-pick-sub">Modules interdits</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="no_weekend" data-group="ruletype" style="--pick-color:#5B4B6B;--pick-bg:#D0C4D8">
                <span class="ia-pick-code"><i class="bi bi-calendar-x"></i></span>
                <span class="ia-pick-sub">Pas de weekend</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="max_days_week" data-group="ruletype" style="--pick-color:#4A5548;--pick-bg:#C8D4C0">
                <span class="ia-pick-code"><i class="bi bi-7-circle"></i></span>
                <span class="ia-pick-sub">Max jours/sem</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="" data-group="ruletype" style="--pick-color:#5A5550;--pick-bg:#C8C4BE">
                <span class="ia-pick-code"><i class="bi bi-chat-text"></i></span>
                <span class="ia-pick-sub">Texte libre (IA)</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
            </div>
          </div>

          <!-- Params: shift codes -->
          <div class="mb-3 d-none" id="iaRuleParamsShift">
            <label class="form-label fw-medium">Horaires</label>
            <div class="d-flex flex-wrap gap-2" id="iaRuleShiftChecks"></div>
          </div>

          <!-- Params: module ids -->
          <div class="mb-3 d-none" id="iaRuleParamsModule">
            <label class="form-label fw-medium">Modules</label>
            <div class="d-flex flex-wrap gap-2" id="iaRuleModuleChecks"></div>
          </div>

          <!-- Params: max days — pick cards 1-7 -->
          <div class="mb-3 d-none" id="iaRuleParamsMaxDays">
            <label class="form-label fw-medium">Maximum de jours par semaine</label>
            <div class="d-flex flex-wrap gap-2" id="iaRuleMaxDaysCards">
              <div class="ia-pick-card" data-value="1" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">1</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card" data-value="2" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">2</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card" data-value="3" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">3</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card chosen" data-value="4" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">4</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card" data-value="5" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">5</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card" data-value="6" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">6</span><i class="bi bi-check-lg ia-pick-check"></i></div>
              <div class="ia-pick-card" data-value="7" data-group="maxdays" style="--pick-color:#20c997"><span class="ia-pick-code">7</span><i class="bi bi-check-lg ia-pick-check"></i></div>
            </div>
          </div>

          <!-- Target — radio cards -->
          <div class="mb-3">
            <label class="form-label fw-medium">Appliquer à</label>
            <div class="d-flex flex-wrap gap-2" id="iaRuleTargetCards">
              <div class="ia-pick-card chosen" data-value="all" data-group="target" style="--pick-color:#0d9488">
                <span class="ia-pick-code"><i class="bi bi-people"></i></span>
                <span class="ia-pick-sub">Tous</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="fonction" data-group="target" style="--pick-color:#e67e22">
                <span class="ia-pick-code"><i class="bi bi-person-badge"></i></span>
                <span class="ia-pick-sub">Par fonction</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
              <div class="ia-pick-card" data-value="users" data-group="target" style="--pick-color:#8b5cf6">
                <span class="ia-pick-code"><i class="bi bi-person-check"></i></span>
                <span class="ia-pick-sub">Collaborateurs</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
              </div>
            </div>
          </div>

          <!-- Target: fonction -->
          <div class="mb-3 d-none" id="iaRuleTargetFonctionWrap">
            <label class="form-label fw-medium">Fonction</label>
            <div class="zs-select" id="iaRuleTargetFonctionCode" data-placeholder="— Choisir —"></div>
          </div>

          <!-- Target: users picker -->
          <div class="mb-3 d-none" id="iaRuleTargetUsersWrap">
            <input type="text" class="form-control form-control-sm mb-2" id="iaRuleUserSearch" placeholder="Rechercher un collaborateur...">
            <div id="iaRuleUserList" class="ia-user-list"></div>
            <small class="text-muted" id="iaRuleUserCount">0 sélectionné(s)</small>
          </div>

          <!-- Notes / description -->
          <div class="mb-3" id="iaRuleDescWrap">
            <label class="form-label fw-medium" id="iaRuleDescLabel">Notes (optionnel)</label>
            <textarea class="form-control" id="iaRuleDescription" placeholder="Contexte ou précisions supplémentaires..." rows="2"></textarea>
          </div>

          <input type="hidden" id="iaRuleId">
          <input type="hidden" id="iaRuleType" value="shift_only">
          <input type="hidden" id="iaRuleImportance" value="important">
          <input type="hidden" id="iaRuleMaxDays" value="4">
          <input type="hidden" name="iaRuleTargetMode" id="iaRuleTargetModeInput" value="all">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-primary" id="iaRuleSaveBtn">
          <i class="bi bi-check-lg me-1"></i>Valider
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let iaRuleModal = null;
    let refData = null; // { horaires, modules, fonctions, users }
    let cachedRules = [];

    const TYPE_LABELS = {
        shift_only: 'Horaires autorisés',
        shift_exclude: 'Horaires interdits',
        module_only: 'Modules autorisés',
        module_exclude: 'Modules interdits',
        no_weekend: 'Pas de weekend',
        max_days_week: 'Max jours/sem',
    };
    const TYPE_COLORS = {
        shift_only: 'bg-primary', shift_exclude: 'bg-warning text-dark',
        module_only: 'bg-success', module_exclude: 'bg-danger',
        no_weekend: 'bg-info', max_days_week: 'bg-secondary',
    };
    const TYPE_ACCENTS = {
        shift_only: '#2d4a43', shift_exclude: '#6B5B3E',
        module_only: '#3B4F6B', module_exclude: '#7B3B2C',
        no_weekend: '#5B4B6B', max_days_week: '#4A5548',
    };
    const TYPE_BG = {
        shift_only: '#bcd2cb', shift_exclude: '#D4C4A8',
        module_only: '#B8C9D4', module_exclude: '#E2B8AE',
        no_weekend: '#D0C4D8', max_days_week: '#C8D4C0',
    };
    const TYPE_ICONS = {
        shift_only: 'bi-clock', shift_exclude: 'bi-clock-history',
        module_only: 'bi-building', module_exclude: 'bi-building-x',
        no_weekend: 'bi-calendar-x', max_days_week: 'bi-7-circle',
    };

    async function loadRefData() {
        if (refData) return;
        const res = await adminApiPost('admin_get_planning_refs', {});
        if (!res?.success) return;
        refData = {
            horaires: (res.horaires || []).filter(h => h.code !== 'PIQUET'),
            modules: res.modules || [],
            fonctions: res.fonctions || [],
            users: res.users || [],
        };
    }

    // ── Pick-card radio wiring ──
    // Makes a group of pick-cards behave as radio (single select) and syncs a hidden input
    function wirePickRadio(containerId, hiddenId, onChange) {
        const wrap = document.getElementById(containerId);
        const hidden = document.getElementById(hiddenId);
        if (!wrap) return;
        wrap.querySelectorAll('.ia-pick-card').forEach(card => {
            card.addEventListener('click', () => {
                wrap.querySelectorAll('.ia-pick-card').forEach(c => c.classList.remove('chosen'));
                card.classList.add('chosen');
                if (hidden) hidden.value = card.dataset.value;
                if (onChange) onChange();
            });
        });
    }

    // ── Importance sliding toggle ──
    function initImpToggle() {
        const toggle = document.getElementById('iaRuleImpToggle');
        if (!toggle) return;
        const btns = toggle.querySelectorAll('.ia-imp-opt');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                setImpToggle(btn.dataset.value);
            });
        });
        // Initial sizing after DOM is ready
        requestAnimationFrame(() => sizeImpSlider());
    }

    function setImpToggle(value) {
        const toggle = document.getElementById('iaRuleImpToggle');
        if (!toggle) return;
        toggle.dataset.value = value;
        toggle.setAttribute('data-value', value);
        document.getElementById('iaRuleImportance').value = value;
        toggle.querySelectorAll('.ia-imp-opt').forEach(b => {
            b.classList.toggle('active', b.dataset.value === value);
        });
        sizeImpSlider();
    }

    function sizeImpSlider() {
        const toggle = document.getElementById('iaRuleImpToggle');
        if (!toggle) return;
        const btns = [...toggle.querySelectorAll('.ia-imp-opt')];
        if (btns.length < 2) return;
        const w1 = btns[0].offsetWidth;
        const w2 = btns[1].offsetWidth;
        toggle.style.setProperty('--slider-w1', w1 + 'px');
        toggle.style.setProperty('--slider-w2', w2 + 'px');
        toggle.style.setProperty('--slider-x2', w1 + 'px');
    }

    function initIaRules() {
        const modalEl = document.getElementById('iaRuleModal');
        if (modalEl) iaRuleModal = new bootstrap.Modal(modalEl);

        loadIaRules();

        document.getElementById('iaRuleAddBtn')?.addEventListener('click', openRuleForm);
        document.getElementById('iaRuleSaveBtn')?.addEventListener('click', saveRule);

        // Wire pick-card groups as radio selectors
        wirePickRadio('iaRuleTypeCards', 'iaRuleType', onTypeChange);
        wirePickRadio('iaRuleTargetCards', 'iaRuleTargetModeInput', onTargetModeChange);
        wirePickRadio('iaRuleMaxDaysCards', 'iaRuleMaxDays', null);

        // Wire importance sliding toggle
        initImpToggle();

        // User search filter
        document.getElementById('iaRuleUserSearch')?.addEventListener('input', filterUsers);

        const rulesTab = document.querySelector('[data-bs-target="#tabRules"]');
        if (rulesTab) rulesTab.addEventListener('shown.bs.tab', () => loadIaRules());
    }

    function onTypeChange() {
        const type = document.getElementById('iaRuleType').value;
        document.getElementById('iaRuleParamsShift').classList.toggle('d-none', !['shift_only', 'shift_exclude'].includes(type));
        document.getElementById('iaRuleParamsModule').classList.toggle('d-none', !['module_only', 'module_exclude'].includes(type));
        document.getElementById('iaRuleParamsMaxDays').classList.toggle('d-none', type !== 'max_days_week');
        // Free-text: description becomes required
        const isCustom = !type;
        document.getElementById('iaRuleDescLabel').textContent = isCustom ? 'Règle (langage humain)' : 'Notes (optionnel)';
        document.getElementById('iaRuleDescription').placeholder = isCustom
            ? 'Décrivez en langage humain la règle que l\'IA doit appliquer...'
            : 'Contexte ou précisions supplémentaires...';
        document.getElementById('iaRuleDescription').rows = isCustom ? 4 : 2;
    }

    function onTargetModeChange() {
        const mode = document.getElementById('iaRuleTargetModeInput')?.value || 'all';
        document.getElementById('iaRuleTargetFonctionWrap').classList.toggle('d-none', mode !== 'fonction');
        document.getElementById('iaRuleTargetUsersWrap').classList.toggle('d-none', mode !== 'users');
    }

    async function populateFormSelectors() {
        await loadRefData();
        if (!refData) return;

        // Shift checkboxes
        const shiftWrap = document.getElementById('iaRuleShiftChecks');
        shiftWrap.innerHTML = refData.horaires.map(h =>
            `<div class="ia-pick-card" data-value="${escapeHtml(h.code)}" data-group="shift" style="--pick-color:${h.couleur || '#6c757d'}">
                <span class="ia-pick-code">${escapeHtml(h.code)}</span>
                <span class="ia-pick-sub">${h.duree_effective}h</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
            </div>`
        ).join('');
        shiftWrap.querySelectorAll('.ia-pick-card').forEach(card => {
            card.addEventListener('click', () => { card.classList.toggle('chosen'); });
        });

        // Module cards — cycle through stats palette
        const modPalette = [
            {bg:'#bcd2cb',fg:'#2d4a43'}, {bg:'#D4C4A8',fg:'#6B5B3E'}, {bg:'#B8C9D4',fg:'#3B4F6B'},
            {bg:'#E2B8AE',fg:'#7B3B2C'}, {bg:'#D0C4D8',fg:'#5B4B6B'}, {bg:'#C8D4C0',fg:'#4A5548'},
            {bg:'#C8C4BE',fg:'#5A5550'},
        ];
        const modWrap = document.getElementById('iaRuleModuleChecks');
        modWrap.innerHTML = refData.modules.map((m, i) => {
            const pal = modPalette[i % modPalette.length];
            return `<div class="ia-pick-card" data-value="${m.id}" data-group="module" style="--pick-color:${pal.fg};--pick-bg:${pal.bg}">
                <span class="ia-pick-code">${escapeHtml(m.code)}</span>
                <span class="ia-pick-sub">${escapeHtml(m.nom)}</span>
                <i class="bi bi-check-lg ia-pick-check"></i>
            </div>`;
        }).join('');
        modWrap.querySelectorAll('.ia-pick-card').forEach(card => {
            card.addEventListener('click', () => { card.classList.toggle('chosen'); });
        });

        // Fonction dropdown
        zerdaSelect.destroy('#iaRuleTargetFonctionCode');
        zerdaSelect.init('#iaRuleTargetFonctionCode',
            [{ value: '', label: '— Choisir —' }].concat(
                refData.fonctions.map(f => ({ value: f.code, label: f.code + ' — ' + f.nom }))
            ), { value: '', search: refData.fonctions.length > 5 });

        // Users list
        renderUserList();
    }

    function renderUserList(filter = '') {
        if (!refData) return;
        const wrap = document.getElementById('iaRuleUserList');
        const q = filter.toLowerCase();
        const filtered = refData.users.filter(u =>
            !q || (u.prenom + ' ' + u.nom).toLowerCase().includes(q) || (u.fonction_code || '').toLowerCase().includes(q)
        );
        wrap.innerHTML = filtered.map(u =>
            `<label class="d-flex align-items-center gap-2 px-2 py-1 ia-user-label">
                <input class="form-check-input ia-user-chk ia-user-chk-inline" type="checkbox" value="${u.id}">
                <span>${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</span>
                <span class="text-muted ms-auto">${escapeHtml(u.fonction_code || '')} · ${u.taux}%</span>
            </label>`
        ).join('');

        // Re-check previously selected
        wrap.querySelectorAll('.ia-user-chk').forEach(chk => {
            chk.addEventListener('change', updateUserCount);
        });
    }

    function filterUsers() {
        const q = document.getElementById('iaRuleUserSearch').value;
        // Save checked state
        const checked = new Set();
        document.querySelectorAll('.ia-user-chk:checked').forEach(c => checked.add(c.value));
        renderUserList(q);
        // Restore checked state
        document.querySelectorAll('.ia-user-chk').forEach(c => {
            if (checked.has(c.value)) c.checked = true;
        });
        updateUserCount();
    }

    function updateUserCount() {
        const count = document.querySelectorAll('.ia-user-chk:checked').length;
        document.getElementById('iaRuleUserCount').textContent = count + ' sélectionné(s)';
    }

    // ── Load rules list ──
    async function loadIaRules() {
        const container = document.getElementById('iaRulesContainer');
        if (!container) return;

        try {
            const res = await adminApiPost('admin_get_ia_rules', {});
            cachedRules = res?.rules || [];

            if (cachedRules.length === 0) {
                container.innerHTML = `<div class="text-center py-5">
                    <div class="ia-fs-empty"><i class="bi bi-file-text"></i></div>
                    <p class="text-muted mb-0">Aucune règle pour le moment</p>
                    <p class="text-muted small">Créez la première avec le bouton ci-dessus</p>
                </div>`;
                return;
            }

            let html = '<div class="d-flex flex-column gap-2 p-3">';
            cachedRules.forEach(rule => {
                const accent = TYPE_ACCENTS[rule.rule_type] || '#5A5550';
                const accentBg = TYPE_BG[rule.rule_type] || '#C8C4BE';
                const icon = TYPE_ICONS[rule.rule_type] || 'bi-chat-text';
                const typeLabel = TYPE_LABELS[rule.rule_type] || 'Texte libre';
                const inactiveClass = rule.actif ? '' : ' ia-rule-inactive';

                // Importance pill
                const impClass = rule.importance === 'important' ? 'ia-rule-imp-important' : 'ia-rule-imp-moyen';
                const impLabel = rule.importance === 'important' ? 'Important' : 'Moyen';
                const impIcon = rule.importance === 'important' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';

                // Params summary
                let paramsSummary = '';
                if (rule.rule_params) {
                    if (rule.rule_params.shift_codes?.length) paramsSummary = rule.rule_params.shift_codes.join(', ');
                    if (rule.rule_params.module_ids?.length) paramsSummary = rule.rule_params.module_ids.length + ' module(s)';
                    if (rule.rule_params.max_days) paramsSummary = 'Max ' + rule.rule_params.max_days + 'j/sem';
                }

                // Target summary
                let targetIcon = 'bi-people-fill';
                let targetSummary = 'Tous les collaborateurs';
                if (rule.target_mode === 'fonction' && rule.target_fonction_code) {
                    targetIcon = 'bi-person-badge-fill';
                    targetSummary = 'Fonction ' + rule.target_fonction_code;
                } else if (rule.target_mode === 'users' && rule.targeted_users?.length) {
                    targetIcon = 'bi-person-check-fill';
                    targetSummary = rule.targeted_users.length + ' collaborateur(s)';
                }

                html += `
                  <div class="ia-rule-card${inactiveClass}" style="--rule-accent:${accent}">
                    <div class="d-flex align-items-center gap-3">
                      <div class="ia-rule-icon" style="background:${accentBg};color:${accent}">
                        <i class="bi ${icon}"></i>
                      </div>
                      <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                          <span class="ia-rule-title text-truncate">${escapeHtml(rule.titre)}</span>
                          <span class="ia-rule-imp ${impClass}"><i class="bi ${impIcon}"></i> ${impLabel}</span>
                        </div>
                        <div class="ia-rule-meta d-flex gap-3 mt-1 flex-wrap">
                          <span><i class="bi bi-tag"></i> ${escapeHtml(typeLabel)}</span>
                          <span><i class="bi ${targetIcon}"></i> ${escapeHtml(targetSummary)}</span>
                          ${paramsSummary ? `<span><i class="bi bi-sliders2"></i> ${escapeHtml(paramsSummary)}</span>` : ''}
                          ${rule.description ? `<span title="${escapeHtml(rule.description)}"><i class="bi bi-chat-left-text"></i> Notes</span>` : ''}
                        </div>
                      </div>
                      <div class="ia-rule-actions flex-shrink-0">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input ia-rule-toggle" type="checkbox" role="switch" ${rule.actif ? 'checked' : ''} data-rule-id="${rule.id}">
                        </div>
                        <button class="btn btn-sm btn-light ia-rule-edit" data-rule-id="${rule.id}" title="Modifier">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-light text-danger ia-rule-delete" data-rule-id="${rule.id}" title="Supprimer">
                          <i class="bi bi-trash3"></i>
                        </button>
                      </div>
                    </div>
                  </div>`;
            });
            html += '</div>';
            container.innerHTML = html;

            // Bind event listeners (no onclick inline — CSP safe)
            container.querySelectorAll('.ia-rule-toggle').forEach(el => {
                el.addEventListener('change', () => toggleRuleStatus(el.dataset.ruleId, el.checked, el));
            });
            container.querySelectorAll('.ia-rule-edit').forEach(el => {
                el.addEventListener('click', () => editRule(el.dataset.ruleId));
            });
            container.querySelectorAll('.ia-rule-delete').forEach(el => {
                el.addEventListener('click', () => deleteRule(el.dataset.ruleId));
            });
        } catch (e) {
            console.error('loadIaRules error:', e);
            container.innerHTML = '<div class="text-center text-danger py-4">Erreur: ' + e.message + '</div>';
        }
    }

    // ── Open form ──
    async function openRuleForm() {
        await populateFormSelectors();
        document.getElementById('iaRuleForm').reset();
        document.getElementById('iaRuleId').value = '';
        document.getElementById('iaRuleModalLabel').textContent = 'Ajouter une règle';
        // Reset all pick cards
        document.querySelectorAll('#iaRuleModal .ia-pick-card.chosen').forEach(c => c.classList.remove('chosen'));
        document.querySelectorAll('.ia-user-chk').forEach(c => { c.checked = false; });
        // Set defaults via pick cards
        setPickCard('iaRuleTypeCards', 'shift_only');
        setPickCard('iaRuleTargetCards', 'all');
        setPickCard('iaRuleMaxDaysCards', '4');
        setImpToggle('important');
        // Sync hidden inputs
        document.getElementById('iaRuleType').value = 'shift_only';
        document.getElementById('iaRuleTargetModeInput').value = 'all';
        document.getElementById('iaRuleMaxDays').value = '4';
        onTypeChange();
        onTargetModeChange();
        updateUserCount();
        if (iaRuleModal) iaRuleModal.show();
    }

    // Helper: select a pick card by value in a container
    function setPickCard(containerId, value) {
        const wrap = document.getElementById(containerId);
        if (!wrap) return;
        wrap.querySelectorAll('.ia-pick-card').forEach(c => {
            c.classList.toggle('chosen', c.dataset.value === value);
        });
    }

    // ── Edit rule ──
    async function editRule(ruleId) {
        await populateFormSelectors();
        const rule = cachedRules.find(r => r.id === ruleId);
        if (!rule) return;

        // Reset all pick cards first
        document.querySelectorAll('#iaRuleModal .ia-pick-card.chosen').forEach(c => c.classList.remove('chosen'));

        document.getElementById('iaRuleId').value = rule.id;
        document.getElementById('iaRuleTitre').value = rule.titre;
        document.getElementById('iaRuleDescription').value = rule.description || '';

        // Set importance toggle
        setImpToggle(rule.importance || 'important');

        // Set type pick card + hidden
        setPickCard('iaRuleTypeCards', rule.rule_type || '');
        document.getElementById('iaRuleType').value = rule.rule_type || '';

        // Set params — toggle .chosen on matching cards
        if (rule.rule_params?.shift_codes) {
            document.querySelectorAll('.ia-pick-card[data-group="shift"]').forEach(c => {
                c.classList.toggle('chosen', rule.rule_params.shift_codes.includes(c.dataset.value));
            });
        }
        if (rule.rule_params?.module_ids) {
            document.querySelectorAll('.ia-pick-card[data-group="module"]').forEach(c => {
                c.classList.toggle('chosen', rule.rule_params.module_ids.includes(c.dataset.value));
            });
        }
        if (rule.rule_params?.max_days) {
            setPickCard('iaRuleMaxDaysCards', String(rule.rule_params.max_days));
            document.getElementById('iaRuleMaxDays').value = rule.rule_params.max_days;
        }

        // Set target mode pick card + hidden
        const targetMode = rule.target_mode || 'all';
        setPickCard('iaRuleTargetCards', targetMode);
        document.getElementById('iaRuleTargetModeInput').value = targetMode;

        if (rule.target_fonction_code) {
            zerdaSelect.setValue('#iaRuleTargetFonctionCode', rule.target_fonction_code);
        }

        // Set targeted users
        if (rule.targeted_users?.length) {
            const userIds = new Set(rule.targeted_users.map(u => u.id));
            document.querySelectorAll('.ia-user-chk').forEach(c => {
                c.checked = userIds.has(c.value);
            });
        }

        onTypeChange();
        onTargetModeChange();
        updateUserCount();

        document.getElementById('iaRuleModalLabel').textContent = 'Modifier la règle';
        if (iaRuleModal) iaRuleModal.show();
    }

    // ── Save rule ──
    async function saveRule() {
        const id = document.getElementById('iaRuleId').value;
        const titre = document.getElementById('iaRuleTitre').value.trim();
        const description = document.getElementById('iaRuleDescription').value.trim();
        const importance = document.getElementById('iaRuleImportance').value;
        const ruleType = document.getElementById('iaRuleType').value || null;

        if (!titre) { toast('Titre requis', 'error'); return; }
        if (!ruleType && !description) { toast('Description requise pour les règles texte libre', 'error'); return; }

        // Collect params from chosen cards
        let ruleParams = null;
        if (['shift_only', 'shift_exclude'].includes(ruleType)) {
            const codes = [...document.querySelectorAll('.ia-pick-card[data-group="shift"].chosen')].map(c => c.dataset.value);
            if (!codes.length) { toast('Sélectionnez au moins un horaire', 'error'); return; }
            ruleParams = { shift_codes: codes };
        } else if (['module_only', 'module_exclude'].includes(ruleType)) {
            const ids = [...document.querySelectorAll('.ia-pick-card[data-group="module"].chosen')].map(c => c.dataset.value);
            if (!ids.length) { toast('Sélectionnez au moins un module', 'error'); return; }
            ruleParams = { module_ids: ids };
        } else if (ruleType === 'max_days_week') {
            const maxDays = parseInt(document.getElementById('iaRuleMaxDays').value) || 5;
            ruleParams = { max_days: Math.max(1, Math.min(7, maxDays)) };
        }

        // Collect target
        const targetMode = document.getElementById('iaRuleTargetModeInput')?.value || 'all';
        let targetFonctionCode = null;
        let userIds = [];

        if (targetMode === 'fonction') {
            targetFonctionCode = zerdaSelect.getValue('#iaRuleTargetFonctionCode');
            if (!targetFonctionCode) { toast('Sélectionnez une fonction', 'error'); return; }
        } else if (targetMode === 'users') {
            userIds = [...document.querySelectorAll('.ia-user-chk:checked')].map(c => c.value);
            if (!userIds.length) { toast('Sélectionnez au moins un collaborateur', 'error'); return; }
        }

        const data = {
            titre, description, importance,
            rule_type: ruleType,
            rule_params: ruleParams,
            target_mode: targetMode,
            target_fonction_code: targetFonctionCode,
            user_ids: userIds,
        };
        if (id) data.id = id;

        const btn = document.getElementById('iaRuleSaveBtn');
        btn.disabled = true;

        try {
            const action = id ? 'admin_update_ia_rule' : 'admin_create_ia_rule';
            const res = await adminApiPost(action, data);
            if (res?.success) {
                if (iaRuleModal) iaRuleModal.hide();
                toast(id ? 'Règle mise à jour' : 'Règle créée', 'success');
                await loadIaRules();
            } else {
                toast(res?.message || 'Erreur', 'error');
            }
        } catch (e) {
            toast('Erreur: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // ── Delete rule ──
    async function deleteRule(ruleId) {
        if (!confirm('Supprimer cette règle ?')) return;
        try {
            const res = await adminApiPost('admin_delete_ia_rule', { id: ruleId });
            if (res?.success) {
                toast('Règle supprimée', 'success');
                await loadIaRules();
            } else {
                toast(res?.message || 'Erreur', 'error');
            }
        } catch (e) {
            toast('Erreur: ' + e.message, 'error');
        }
    }

    // ── Toggle active ──
    async function toggleRuleStatus(ruleId, newStatus, el) {
        try {
            const res = await adminApiPost('admin_toggle_ia_rule', { id: ruleId });
            if (!res?.success) {
                toast(res?.message || 'Erreur', 'error');
                el.checked = !newStatus;
            }
        } catch (e) {
            toast('Erreur: ' + e.message, 'error');
            el.checked = !newStatus;
        }
    }

    // Initialize when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initIaRules);
    } else {
        initIaRules();
    }
})();
</script>

<script<?= nonce() ?>>
(function() {
    const ssrIaConfig = <?= json_encode($iaConfig, JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    // Info modal content mapping
    const infoContents = {
        heures: {
            title: '<i class="bi bi-clock me-2"></i>Calcul des heures cibles',
            body: 'Les heures cibles mensuelles sont calculées par&nbsp;: <strong>jours ouvrés &times; heures/jour &times; taux%</strong>.<br><br>Exemple&nbsp;: 21.7 &times; 8.4 &times; 80% = <strong>145.7h</strong>.<br><br>Ces valeurs sont utilisées par l\'algorithme pour équilibrer la charge de travail entre les employés selon leur taux d\'activité.'
        },
        repos: {
            title: '<i class="bi bi-shield-check me-2"></i>Repos & Limites',
            body: 'Définit le <strong>nombre maximum de jours consécutifs travaillés</strong>. Au-delà, un repos est imposé automatiquement.<br><br>Le <em>max besoins</em> permet une exception si la couverture l\'exige (manque de personnel).<br><br>L\'option <em>direction weekend</em> exclut la direction et les responsables du travail le weekend.'
        },
        scoring: {
            title: '<i class="bi bi-bar-chart me-2"></i>Scoring des candidats',
            body: 'Lors de l\'assignation automatique, chaque employé reçoit un <strong>score</strong> calculé ainsi&nbsp;:<br><br><code>score = (heures_cibles &minus; heures_actuelles) + bonus_module + aléatoire</code><br><br>Le <em>bonus principal</em> favorise les employés rattachés au module concerné. Le <em>facteur aléatoire</em> (0 à max) introduit de la variété pour éviter les plannings trop répétitifs.'
        },
        weekend: {
            title: '<i class="bi bi-calendar-week me-2"></i>Gestion du weekend',
            body: 'Probabilité (en %) qu\'un employé soit <strong>exclu du weekend</strong>. Plus le % est élevé, moins d\'employés travaillent le weekend.<br><br>Exemple&nbsp;: <strong>66%</strong> = environ 1/3 des employés travaillent chaque weekend. Les besoins obligatoires de couverture sont toujours respectés.'
        },
        desirs: {
            title: '<i class="bi bi-star me-2"></i>Désirs employés',
            body: 'Nombre max de <strong>désirs par mois</strong> par employé. Les désirs permettent aux employés d\'exprimer leurs préférences (jours off, horaires souhaités).<br><br>Les jours d\'<em>ouverture</em> et de <em>fermeture</em> définissent la fenêtre de soumission&nbsp;: ex. du <strong>1er au 10</strong> du mois précédent.'
        },
        horaires: {
            title: '<i class="bi bi-clock-history me-2"></i>Classification horaires',
            body: 'Seuils pour classifier automatiquement les horaires en <strong>matin / soir / nuit</strong>.<br><br>Un horaire commençant après le <em>seuil soir</em> (ex. 13h) est classé <strong>soir</strong>. Un horaire dont la durée dépasse le <em>seuil nuit</em> (ex. 10h) est classé <strong>nuit</strong>.<br><br>Le <em>code admin</em> (ex. A6) est l\'horaire spécial attribué automatiquement à la direction et à l\'administration.'
        },
        api: {
            title: '<i class="bi bi-key me-2"></i>Clés API',
            body: 'Clés API pour les services d\'intelligence artificielle utilisés par le générateur de planning.<br><br><strong>Google Gemini</strong>&nbsp;: modèles rapides et économiques, idéal pour les générations fréquentes. Obtenez une clé sur <em>aistudio.google.com</em>.<br><br><strong>Anthropic Claude</strong>&nbsp;: modèles plus avancés en raisonnement, meilleur pour les plannings complexes. Obtenez une clé sur <em>console.anthropic.com</em>.<br><br>Seul le fournisseur <strong>actif</strong> (sélectionné via les boutons radio) sera utilisé lors de la génération. Vous pouvez configurer les deux et basculer à tout moment.'
        },
        regles: {
            title: '<i class="bi bi-file-text me-2"></i>Règles IA',
            body: 'Les règles IA sont des instructions en <strong>langage humain</strong> envoyées au modèle lors de la génération.<br><br>Exemples&nbsp;:<ul><li>«&nbsp;Favoriser les horaires du matin pour les seniors&nbsp;»</li><li>«&nbsp;Ne jamais planifier X et Y le même jour&nbsp;»</li><li>«&nbsp;Alterner les weekends équitablement&nbsp;»</li></ul>Chaque règle a un <em>niveau d\'importance</em> (moyen / important / supprimé) et peut être activée/désactivée individuellement. Les règles supprimées ou désactivées sont ignorées par le générateur.'
        }
    };

    // Wire up info modal
    const infoModal = document.getElementById('infoModal');
    if (infoModal) {
        infoModal.addEventListener('show.bs.modal', function(e) {
            const key = e.relatedTarget?.dataset?.info;
            const info = infoContents[key];
            if (info) {
                document.getElementById('infoModalTitle').innerHTML = info.title;
                document.getElementById('infoModalBody').innerHTML = info.body;
            }
        });
    }
    const fields = {
        ia_jours_ouvres:           { el: 'iaJoursOuvres',           def: '21.7' },
        ia_heures_jour:            { el: 'iaHeuresJour',            def: '8.4' },
        ia_consecutif_max:         { el: 'iaConsecutifMax',         def: '5' },
        ia_consecutif_max_besoins: { el: 'iaConsecutifMaxBesoins',  def: '6' },
        ia_direction_weekend_off:  { el: 'iaDirectionWeekendOff',   def: '1', type: 'check' },
        ia_bonus_principal:        { el: 'iaBonusPrincipal',        def: '10' },
        ia_random_max:             { el: 'iaRandomMax',             def: '3' },
        ia_weekend_skip_prob:      { el: 'iaWeekendSkipProb',       def: '66' },
        planning_desirs_max_mois:  { el: 'iaDesirMax',              def: '4' },
        planning_desirs_ouverture_jour: { el: 'iaDesirOuverture',   def: '1' },
        planning_desirs_fermeture_jour: { el: 'iaDesirFermeture',   def: '10' },
        ia_seuil_soir:             { el: 'iaSeuilSoir',             def: '13' },
        ia_seuil_nuit:             { el: 'iaSeuilNuit',             def: '10' },
        ia_admin_shift_code:       { el: 'iaAdminShiftCode',        def: 'A6', type: 'text' },
    };

    function initConfigiaPage() {
        // Expenses tab — attach FIRST to avoid being blocked by errors below
        const depTab = document.querySelector('[data-bs-target="#tabDepenses"]');
        if (depTab) {
            depTab.addEventListener('shown.bs.tab', () => loadExpenses());
        }
        document.getElementById('depRefreshBtn')?.addEventListener('click', () => loadExpenses());

        // Save handlers
        document.getElementById('iaSaveBtn')?.addEventListener('click', saveConfig);
        document.getElementById('iaResetBtn')?.addEventListener('click', resetDefaults);
        document.getElementById('apiKeySaveBtn')?.addEventListener('click', saveApiKeys);
        document.getElementById('ollamaModelSaveBtn')?.addEventListener('click', saveOllamaModel);
        document.getElementById('btnBenchmark')?.addEventListener('click', runBenchmark);
        document.getElementById('engineSaveBtn')?.addEventListener('click', saveTranscriptionEngine);
        document.getElementById('externalModeSaveBtn')?.addEventListener('click', saveExternalMode);
        checkOllamaStatus();
        checkTranscriptionEngines();

        // Init zerdaSelect components
        zerdaSelect.init('#geminiModel', [
            { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash — 0,15$/0,60$ par M tokens (recommandé)' },
            { value: 'gemini-2.5-pro', label: 'Gemini 2.5 Pro — 1,25$/10$ par M tokens (plus performant)' },
            { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash — 0,10$/0,40$ par M tokens' },
            { value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash Lite — 0,075$/0,30$ par M tokens' }
        ], { value: 'gemini-2.5-flash' });

        zerdaSelect.init('#anthropicModel', [
            { value: 'claude-haiku-4-5-20251001', label: 'Haiku 4.5 — 1$/5$ par M tokens (rapide, économique)' },
            { value: 'claude-sonnet-4-5-20250929', label: 'Sonnet 4.5 — 3$/15$ par M tokens (équilibré)' },
            { value: 'claude-opus-4-6', label: 'Opus 4.6 — 15$/75$ par M tokens (plus performant)' }
        ], { value: 'claude-sonnet-4-5-20250929' });

        zerdaSelect.init('#ollamaModel', [
            { value: 'tinyllama', label: 'TinyLlama — 1.1 Go (très rapide, qualité basique)' },
            { value: 'phi3:mini', label: 'Phi-3 Mini — 2.3 Go (rapide, bonne qualité)' },
            { value: 'mistral', label: 'Mistral 7B — 4.4 Go (lent sur CPU, meilleure qualité)' }
        ], { value: 'mistral' });

        // External mode toggle
        const extToggle = document.getElementById('pvExternalMode');
        if (extToggle) {
            extToggle.addEventListener('change', () => {
                const on = extToggle.checked;
                document.getElementById('externalModeDetails').classList.toggle('ia-hidden', !on);
                document.getElementById('externalModeOff').classList.toggle('ia-hidden', on);
                document.getElementById('localEngineCard').classList.toggle('ia-disabled', on);
            });
        }

        // Show/hide whisper warning when switching engine
        document.querySelectorAll('input[name="transcription_engine"]').forEach(r => {
            r.addEventListener('change', () => {
                document.getElementById('whisperWarning').classList.toggle('ia-hidden', r.value !== 'whisper');
            });
        });

        // Toggle key visibility (uses CSS text-security instead of type=password to avoid Firefox save-password prompt)
        document.querySelectorAll('.btn-toggle-key').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = document.getElementById(this.dataset.target);
                const icon = this.querySelector('i');
                const isHidden = input.classList.contains('ia-key-masked');
                if (isHidden) {
                    input.classList.remove('ia-key-masked');
                    input.classList.add('ia-key-visible');
                    icon.className = 'bi bi-eye-slash';
                } else {
                    input.classList.remove('ia-key-visible');
                    input.classList.add('ia-key-masked');
                    icon.className = 'bi bi-eye';
                }
            });
        });

        // Config injected by PHP
        {
            const config = ssrIaConfig;

            // Algo fields
            for (const [key, f] of Object.entries(fields)) {
                const el = document.getElementById(f.el);
                if (!el) continue;
                const val = config[key] ?? f.def;
                if (f.type === 'check') {
                    el.checked = val === '1' || val === 1;
                } else {
                    el.value = val;
                }
            }

            // API Keys tab
            const provider = config.ai_provider || 'gemini';
            const provRadio = document.getElementById(`prov-${provider}`);
            if (provRadio) provRadio.checked = true;

            const geminiKeyEl = document.getElementById('geminiApiKey');
            const anthropicKeyEl = document.getElementById('anthropicApiKey');
            if (geminiKeyEl) geminiKeyEl.value = config.gemini_api_key || '';
            zerdaSelect.setValue('#geminiModel', config.gemini_model || 'gemini-2.5-flash');
            if (anthropicKeyEl) anthropicKeyEl.value = config.anthropic_api_key || '';
            zerdaSelect.setValue('#anthropicModel', config.anthropic_model || 'claude-sonnet-4-5-20250929');

            // Ollama model
            zerdaSelect.setValue('#ollamaModel', config.ollama_model || 'mistral');

            // Transcription engine
            const engine = config.transcription_engine || 'vosk';
            const engineRadio = document.getElementById(`engine-${engine}`);
            if (engineRadio) engineRadio.checked = true;
            if (engine === 'whisper') {
                document.getElementById('whisperWarning').classList.remove('ia-hidden');
            }

            // External mode
            const extOn = config.pv_external_mode === '1';
            const extToggle = document.getElementById('pvExternalMode');
            if (extToggle) {
                extToggle.checked = extOn;
                document.getElementById('externalModeDetails').classList.toggle('ia-hidden', !extOn);
                document.getElementById('externalModeOff').classList.toggle('ia-hidden', extOn);
                document.getElementById('localEngineCard').classList.toggle('ia-disabled', extOn);
            }
            const deepgramKeyEl = document.getElementById('deepgramApiKey');
            if (deepgramKeyEl) deepgramKeyEl.value = config.deepgram_api_key || '';

            updateApiKeyBadges(config);
        }
    }

    function updateApiKeyBadges(config) {
        const geminiKey = config.gemini_api_key || '';
        const claudeKey = config.anthropic_api_key || '';
        const provider = config.ai_provider || 'gemini';

        const gemBadge = document.getElementById('geminiKeyBadge');
        const clBadge = document.getElementById('claudeKeyBadge');
        const statusBadge = document.getElementById('apiKeyStatus');

        if (gemBadge) {
            gemBadge.className = 'badge ' + (geminiKey ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning');
            gemBadge.textContent = geminiKey ? 'Clé définie' : 'Pas de clé';
        }

        if (clBadge) {
            clBadge.className = 'badge ' + (claudeKey ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning');
            clBadge.textContent = claudeKey ? 'Clé définie' : 'Pas de clé';
        }

        if (statusBadge) {
            const isConfigured = (provider === 'gemini' && geminiKey) || (provider === 'claude' && claudeKey);
            statusBadge.className = 'badge ' + (isConfigured ? 'bg-success' : 'bg-danger');
            statusBadge.innerHTML = isConfigured
                ? '<i class="bi bi-check-circle me-1"></i>Actif'
                : '<i class="bi bi-x-circle me-1"></i>Non configuré';
        }
    }

    async function saveConfig() {
        const btn = document.getElementById('iaSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';

        const values = {};
        for (const [key, f] of Object.entries(fields)) {
            const el = document.getElementById(f.el);
            if (!el) continue;
            values[key] = f.type === 'check' ? (el.checked ? '1' : '0') : el.value;
        }

        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) {
            showToast(`${res.saved} paramètres enregistrés`, 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer les paramètres';
    }

    function resetDefaults() {
        for (const [key, f] of Object.entries(fields)) {
            const el = document.getElementById(f.el);
            if (!el) continue;
            if (f.type === 'check') {
                el.checked = f.def === '1';
            } else {
                el.value = f.def;
            }
        }
        showToast('Valeurs par défaut restaurées', 'success');
    }

    async function saveApiKeys() {
        const btn = document.getElementById('apiKeySaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const provider = document.querySelector('input[name="ai_provider"]:checked')?.value || 'gemini';
        const values = {
            ai_provider: provider,
            gemini_api_key: document.getElementById('geminiApiKey').value,
            gemini_model: zerdaSelect.getValue('#geminiModel'),
            anthropic_api_key: document.getElementById('anthropicApiKey').value,
            anthropic_model: zerdaSelect.getValue('#anthropicModel'),
        };

        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) {
            showToast('Configuration IA enregistrée', 'success');
            updateApiKeyBadges(values);
        } else {
            showToast(res.message || 'Erreur', 'error');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer la configuration';
    }

    const moisNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    function formatCost(usd) {
        const v = parseFloat(usd) || 0;
        return v === 0 ? 'Gratuit' : '$' + v.toFixed(4);
    }

    function formatDuration(ms) {
        const v = parseInt(ms) || 0;
        if (v < 1000) return v + 'ms';
        return (v / 1000).toFixed(1) + 's';
    }

    async function loadExpenses() {
        try {
        const res = await adminApiPost('admin_get_ia_usage');
        if (!res.success) {
            document.getElementById('depMonthlyBody').innerHTML =
                `<tr><td colspan="6" class="text-center text-danger py-3">${escapeHtml(res.message || 'Erreur de chargement')}</td></tr>`;
            document.getElementById('depRecentList').innerHTML =
                `<div class="list-group-item text-center text-danger py-3">${escapeHtml(res.message || 'Erreur')}</div>`;
            return;
        }

        const { year, monthly, annual, month_stats, recent } = res;

        // Stat cards
        document.getElementById('depYear').textContent = year;
        document.getElementById('depMonthCount').textContent = month_stats?.nb_generations || 0;
        document.getElementById('depMonthCost').textContent = formatCost(month_stats?.total_cost);
        document.getElementById('depYearCount').textContent = annual?.nb_generations || 0;
        document.getElementById('depYearCost').textContent = formatCost(annual?.total_cost);

        // Monthly table
        const tbody = document.getElementById('depMonthlyBody');
        const tfoot = document.getElementById('depMonthlyFoot');

        if (!monthly || !monthly.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Aucune génération enregistrée</td></tr>';
            tfoot.innerHTML = '';
        } else {
            tbody.innerHTML = monthly.map(m => {
                const [y, mo] = m.mois.split('-');
                const moisLabel = moisNames[parseInt(mo)] + ' ' + y;
                return `<tr>
                    <td>${escapeHtml(moisLabel)}</td>
                    <td class="text-center">${m.nb_generations}</td>
                    <td class="text-center">${m.total_assignations || 0}</td>
                    <td class="text-center">${(parseInt(m.total_tokens_in)||0).toLocaleString()} / ${(parseInt(m.total_tokens_out)||0).toLocaleString()}</td>
                    <td class="text-end">${formatCost(m.total_cost)}</td>
                    <td class="text-center">${formatDuration(m.avg_duration_ms)}</td>
                </tr>`;
            }).join('');

            tfoot.innerHTML = `<tr>
                <td>Total ${year}</td>
                <td class="text-center">${annual.nb_generations || 0}</td>
                <td class="text-center">${annual.total_assignations || 0}</td>
                <td class="text-center">${(parseInt(annual.total_tokens_in)||0).toLocaleString()} / ${(parseInt(annual.total_tokens_out)||0).toLocaleString()}</td>
                <td class="text-end">${formatCost(annual.total_cost)}</td>
                <td class="text-center">${formatDuration(annual.avg_duration_ms)}</td>
            </tr>`;
        }

        // Recent list
        const list = document.getElementById('depRecentList');
        if (!recent || !recent.length) {
            list.innerHTML = '<div class="list-group-item text-center text-muted py-3">Aucune génération</div>';
        } else {
            list.innerHTML = recent.map(r => {
                const date = new Date(r.created_at);
                const dateStr = date.toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                const admin = r.admin_prenom ? (r.admin_prenom + ' ' + r.admin_nom) : '—';
                return `<div class="list-group-item d-flex justify-content-between align-items-start py-2">
                    <div>
                        <div class="fw-medium small">${escapeHtml(r.mois_annee)}</div>
                        <div class="text-muted" class="ia-recent-date">${escapeHtml(dateStr)} · ${escapeHtml(admin)}</div>
                    </div>
                    <div class="text-end">
                        <div class="small">${r.nb_assignations} assign.</div>
                        <div class="text-muted" class="ia-recent-date">${formatDuration(r.duration_ms)} · ${formatCost(r.cost_usd)}</div>
                    </div>
                </div>`;
            }).join('');
        }
        } catch (e) {
            console.error('loadExpenses error:', e);
            document.getElementById('depMonthlyBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-3">Erreur JS: ' + e.message + '</td></tr>';
        }
    }

    // ── Ollama local model ──
    const OLLAMA_URL = 'http://localhost:59876';

    function updateOllamaBadge(status) {
        const badge = document.getElementById('badgeOllamaConfig');
        if (!badge) return;
        badge.dataset.status = status;
        const st = badge.querySelector('.srv-status');
        badge.classList.remove('ia-srv-online', 'ia-srv-offline', 'ia-srv-checking');
        if (status === 'online') {
            badge.classList.add('ia-srv-online');
            if (st) st.textContent = 'Connecté';
        } else if (status === 'offline') {
            badge.classList.add('ia-srv-offline');
            if (st) st.textContent = 'Hors ligne';
        } else {
            badge.classList.add('ia-srv-checking');
            if (st) st.textContent = '…';
        }
    }

    async function checkOllamaStatus() {
        try {
            const r = await fetch(OLLAMA_URL + '/api/tags', { signal: AbortSignal.timeout(3000) });
            updateOllamaBadge(r.ok ? 'online' : 'offline');
            return r.ok;
        } catch {
            updateOllamaBadge('offline');
            return false;
        }
    }
    setInterval(checkOllamaStatus, 10000);

    async function saveOllamaModel() {
        const btn = document.getElementById('ollamaModelSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const values = { ollama_model: zerdaSelect.getValue('#ollamaModel') };
        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) showToast('Modèle Ollama enregistré', 'success');
        else showToast(res.message || 'Erreur', 'error');

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer le modèle';
    }

    async function runBenchmark() {
        const btn = document.getElementById('btnBenchmark');
        const resultsDiv = document.getElementById('benchmarkResults');
        const listDiv = document.getElementById('benchmarkList');
        const recoDiv = document.getElementById('benchmarkRecommendation');

        const online = await checkOllamaStatus();
        if (!online) {
            showToast('Ollama hors ligne. Lancez le raccourci « ZerdaTime IA » sur votre Bureau.', 'error');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Benchmark en cours...';
        resultsDiv.classList.remove('ia-hidden');
        listDiv.innerHTML = '<div class="text-muted small">Récupération des modèles installés...</div>';
        recoDiv.classList.add('ia-hidden');

        // Get installed models
        let models = [];
        try {
            const r = await fetch(OLLAMA_URL + '/api/tags', { signal: AbortSignal.timeout(5000) });
            const data = await r.json();
            models = (data.models || []).map(m => m.name);
        } catch {
            listDiv.innerHTML = '<div class="text-danger small">Impossible de lire les modèles installés.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-speedometer2 me-1"></i> Tester les performances';
            return;
        }

        if (models.length === 0) {
            listDiv.innerHTML = '<div class="text-warning small">Aucun modèle installé. Lancez l\'installation.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-speedometer2 me-1"></i> Tester les performances';
            return;
        }

        const testPrompt = 'Réponds en une phrase : quel jour sommes-nous ?';
        const results = [];
        listDiv.innerHTML = '';

        for (const model of models) {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
            row.innerHTML = `<span class="small fw-medium">${escapeHtml(model)}</span><span class="small text-muted"><span class="spinner-border spinner-border-sm ia-spinner-xs"></span> Test...</span>`;
            listDiv.appendChild(row);

            try {
                const t0 = performance.now();
                const res = await fetch(OLLAMA_URL + '/api/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ model, prompt: testPrompt, stream: false }),
                    signal: AbortSignal.timeout(120000)
                });
                const elapsed = Math.round(performance.now() - t0);
                const data = await res.json();
                const tokens = (data.response || '').split(/\s+/).length;
                const tokPerSec = elapsed > 0 ? (tokens / (elapsed / 1000)).toFixed(1) : '?';

                results.push({ model, elapsed, tokens, tokPerSec: parseFloat(tokPerSec), ok: true });
                row.querySelector('span:last-child').innerHTML = `<span class="badge ia-bench-ok">${(elapsed/1000).toFixed(1)}s</span> <span class="text-muted">${tokPerSec} tok/s</span>`;
            } catch (e) {
                results.push({ model, elapsed: 999999, ok: false });
                row.querySelector('span:last-child').innerHTML = '<span class="badge bg-danger-subtle text-danger">Timeout / Erreur</span>';
            }
        }

        // Recommendation
        const okResults = results.filter(r => r.ok).sort((a, b) => {
            // Score: balance speed and quality (bigger models = better quality)
            const sizeOrder = { 'tinyllama:latest': 1, 'tinyllama': 1, 'phi3:mini': 2, 'phi3:latest': 2, 'mistral:latest': 3, 'mistral': 3 };
            const qualA = sizeOrder[a.model] || 2;
            const qualB = sizeOrder[b.model] || 2;
            // Prefer models that respond in < 30s with best quality
            if (a.elapsed < 30000 && b.elapsed < 30000) return qualB - qualA;
            if (a.elapsed < 30000) return -1;
            if (b.elapsed < 30000) return 1;
            return a.elapsed - b.elapsed;
        });

        if (okResults.length > 0) {
            const best = okResults[0];
            const modelKey = best.model.replace(':latest', '');
            recoDiv.classList.remove('ia-hidden');
            recoDiv.innerHTML = `<i class="bi bi-trophy me-1"></i> Recommandé pour votre machine : <strong>${escapeHtml(best.model)}</strong> (${(best.elapsed/1000).toFixed(1)}s, ${best.tokPerSec} tok/s)`;

            // Auto-select in dropdown
            const ollamaOpts = [
                { value: 'tinyllama', label: 'TinyLlama' },
                { value: 'phi3:mini', label: 'Phi-3 Mini' },
                { value: 'mistral', label: 'Mistral 7B' }
            ];
            for (const opt of ollamaOpts) {
                if (modelKey === opt.value || modelKey.startsWith(opt.value) || opt.value.startsWith(modelKey)) {
                    zerdaSelect.setValue('#ollamaModel', opt.value);
                    break;
                }
            }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-speedometer2 me-1"></i> Tester les performances';
    }

    // ── Transcription engine ──
    const TRANSCRIPTION_URL = 'http://localhost:5876';

    function updateTranscriptionBadge(status, engines) {
        const badge = document.getElementById('badgeTranscriptionEngine');
        if (!badge) return;
        const st = badge.querySelector('.srv-status');
        badge.classList.remove('ia-srv-online', 'ia-srv-offline-red', 'ia-srv-checking');
        if (status === 'online') {
            badge.classList.add('ia-srv-online');
            if (st) st.textContent = engines ? engines.join(' + ') : 'Connecté';
        } else if (status === 'offline') {
            badge.classList.add('ia-srv-offline-red');
            if (st) st.textContent = 'Hors ligne';
        } else {
            badge.classList.add('ia-srv-checking');
            if (st) st.textContent = '…';
        }
    }

    async function checkTranscriptionEngines() {
        try {
            const r = await fetch(TRANSCRIPTION_URL + '/health', { signal: AbortSignal.timeout(3000) });
            if (r.ok) {
                const data = await r.json();
                const engines = data.engines || ['vosk'];
                updateTranscriptionBadge('online', engines.map(e => e.charAt(0).toUpperCase() + e.slice(1)));

                // If whisper not available, disable the radio and show warning
                const whisperRadio = document.getElementById('engine-whisper');
                const notInstalledDiv = document.getElementById('whisperNotInstalled');
                if (!engines.includes('whisper')) {
                    if (whisperRadio) whisperRadio.disabled = true;
                    if (notInstalledDiv) notInstalledDiv.classList.remove('ia-hidden');
                } else {
                    if (whisperRadio) whisperRadio.disabled = false;
                    if (notInstalledDiv) notInstalledDiv.classList.add('ia-hidden');
                }
            } else {
                updateTranscriptionBadge('offline');
            }
        } catch {
            updateTranscriptionBadge('offline');
        }
    }
    setInterval(checkTranscriptionEngines, 10000);

    async function saveTranscriptionEngine() {
        const btn = document.getElementById('engineSaveBtn');
        const selected = document.querySelector('input[name="transcription_engine"]:checked');
        if (!selected) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const values = { transcription_engine: selected.value };
        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) showToast('Moteur de transcription enregistré : ' + selected.value.charAt(0).toUpperCase() + selected.value.slice(1), 'success');
        else showToast(res.message || 'Erreur', 'error');

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer le moteur';
    }

    async function saveExternalMode() {
        const btn = document.getElementById('externalModeSaveBtn');
        const extOn = document.getElementById('pvExternalMode').checked;
        const deepgramKey = document.getElementById('deepgramApiKey').value.trim();

        if (extOn && !deepgramKey) {
            showToast('Veuillez saisir la clé API Deepgram', 'error');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const values = {
            pv_external_mode: extOn ? '1' : '0',
            deepgram_api_key: deepgramKey
        };
        const res = await adminApiPost('admin_save_config', { values });
        if (res.success) showToast('Mode externe ' + (extOn ? 'activé' : 'désactivé'), 'success');
        else showToast(res.message || 'Erreur', 'error');

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer le mode externe';
    }

    window.initConfigiaPage = initConfigiaPage;
})();
</script>
