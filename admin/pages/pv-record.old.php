<!-- PV Recording Page -->
<link rel="stylesheet" href="/zerdatime/admin/assets/css/editor.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/zerdatime/admin/assets/css/emoji-picker.css?v=<?= APP_VERSION ?>">

<style>
/* Recording dot animation */
@keyframes pvr-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}
#recordingIndicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #DC2626;
  animation: pvr-pulse 1s infinite;
  flex-shrink: 0;
}
#recordingStatus.paused #recordingIndicator {
  background: var(--bs-secondary);
  animation: none;
}
/* Timer mono font */
#recordingTime {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 1.4rem;
  font-weight: 700;
  letter-spacing: 1px;
}
/* Editor min-height */
#pvEditorContainer { min-height: 300px; }
#pvEditorContainer .zs-ed-content { min-height: 280px; padding: 1rem; }
#pvEditorContainer .zs-ed-content .ProseMirror { min-height: 280px; outline: none; }
</style>

<?php $pvId = $_GET['id'] ?? ''; ?>

<!-- Header -->
<div class="card mb-3">
  <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap py-2">
    <h1 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
      <i class="bi bi-mic-fill"></i>
      <span id="pvRecordTitle">Chargement...</span>
    </h1>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <button class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1" id="btnStartRecord">
        <i class="bi bi-mic-fill"></i> Demarrer la dictee
      </button>
      <button class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" id="btnPauseRecord" style="display:none">
        <i class="bi bi-pause-fill"></i> Pause
      </button>
      <button class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1" id="btnStopRecord" style="display:none">
        <i class="bi bi-stop-circle-fill"></i> Arreter
      </button>
      <button class="btn btn-outline-secondary btn-sm" id="btnPrintPv" title="Imprimer">
        <i class="bi bi-printer"></i>
      </button>
      <button class="btn btn-outline-secondary btn-sm" id="btnExportPdf" title="PDF">
        <i class="bi bi-file-earmark-pdf"></i>
      </button>
      <a href="<?= admin_url('pv') ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Retour
      </a>
    </div>
  </div>
</div>

<!-- Recording Status Bar -->
<div class="card mb-3 d-none" id="recordingStatus">
  <div class="card-body d-flex align-items-center justify-content-between gap-3 py-2" style="border-left: 4px solid #D97757;">
    <div class="d-flex align-items-center gap-2 flex-grow-1">
      <div id="recordingIndicator"></div>
      <div>
        <div class="fw-medium small" id="recordingStatusText">Ecoute en cours... Parlez distinctement.</div>
        <div class="text-muted" style="font-size:0.78rem;">Le texte s'affichera par blocs grace a l'IA locale.</div>
      </div>
    </div>
    <div class="text-end flex-shrink-0">
      <div id="recordingTime">00:00</div>
      <canvas id="audioVisualizer" width="120" height="28" style="display:block;border-radius:4px;margin-top:4px;"></canvas>
    </div>
  </div>
</div>

<!-- Processing Status -->
<div class="alert alert-light border d-none align-items-center gap-2 mb-3 py-2 small" id="processingStatus">
  <span class="spinner-border spinner-border-sm"></span>
  L'IA traduit votre voix en texte...
</div>

<div class="row g-3">
  <!-- Main content -->
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between py-2">
        <h6 class="mb-0 fw-bold small d-flex align-items-center gap-1"><i class="bi bi-file-text"></i> Contenu du PV</h6>
        <span class="badge text-bg-success d-inline-flex align-items-center gap-1" style="font-size:0.7rem;"><i class="bi bi-shield-lock"></i> IA Locale</span>
      </div>
      <div class="card-body p-0">
        <div id="pvEditorContainer"></div>
        <textarea id="pvTranscript" style="display:none;"></textarea>
      </div>

      <!-- Audio upload -->
      <div class="card-footer bg-light">
        <div class="fw-semibold text-secondary small mb-2">Ou importer un fichier audio externe</div>
        <div class="d-flex gap-2 align-items-center">
          <input type="file" class="form-control form-control-sm" id="audioFileInput" accept="audio/*" style="max-width:320px;">
          <button class="btn btn-outline-secondary btn-sm" id="btnUploadAudio">Importer</button>
        </div>
        <div class="mt-2 p-2 bg-white border rounded d-none" id="audioPlaybackContainer">
          <div class="fw-semibold text-secondary small mb-1"><i class="bi bi-play-circle"></i> Audio associe :</div>
          <audio id="audioPlayback" controls style="width:100%;"></audio>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-lg-4">
    <!-- PV Info -->
    <div class="card mb-3">
      <div class="card-header py-2 small fw-bold text-secondary text-uppercase d-flex align-items-center gap-1" style="letter-spacing:0.4px;">
        <i class="bi bi-info-circle"></i> Infos du PV
      </div>
      <div class="card-body">
        <div class="list-group list-group-flush">
          <div class="list-group-item px-0">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;letter-spacing:0.3px;">Titre</div>
            <div class="small" id="pvRecordTitleInfo">—</div>
          </div>
          <div class="list-group-item px-0">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;letter-spacing:0.3px;">Createur</div>
            <div class="small" id="pvRecordCreator">—</div>
          </div>
          <div class="list-group-item px-0">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;letter-spacing:0.3px;">Module</div>
            <div class="small" id="pvRecordModule">—</div>
          </div>
          <div class="list-group-item px-0">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;letter-spacing:0.3px;">Participants</div>
            <div class="small" id="pvRecordParticipants">—</div>
          </div>
          <div class="list-group-item px-0">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.72rem;letter-spacing:0.3px;">Statut</div>
            <div><span class="badge text-bg-warning" id="pvRecordStatus" style="font-size:0.75rem;">brouillon</span></div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-2 pt-3 mt-2 border-top">
          <div class="form-check form-switch flex-shrink-0">
            <input class="form-check-input" type="checkbox" id="pvAllowComments" checked>
          </div>
          <div>
            <div class="small fw-semibold">Commentaires & notes</div>
            <div class="text-muted" style="font-size:0.75rem;">Les employes pourront noter (5 etoiles) et commenter ce PV.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Finalize -->
    <div class="card mb-3">
      <div class="card-header py-2 small fw-bold text-secondary text-uppercase d-flex align-items-center gap-1" style="letter-spacing:0.4px;">
        <i class="bi bi-check-circle"></i> Finaliser
      </div>
      <div class="card-body">
        <button class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-1" id="btnSavePv">
          <i class="bi bi-check-lg"></i> Enregistrer et finaliser
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let editorModule = null;

    let isRecording = false;
    let isPaused = false;
    let totalRecordingTime = 0;
    let recordingInterval = null;
    let pvId = null;
    let pvData = null;
    let audioStream = null;
    let mainRecorder = null;
    let recordedChunks = [];
    let liveRecorder = null;
    let liveInterval = null;
    let transcribingQueue = Promise.resolve();
    let transcriber = null;
    let isAiReady = false;
    let visAudioContext = null;
    let visAnalyser = null;
    let visDataArray = null;
    let visAnimationId = null;
    let editorInstance = null;

    // ── Initialize ──
    async function initPvrecordPage() {
        editorModule = await import('/zerdatime/assets/js/rich-editor.js');

        pvId = AdminURL.currentId();
        if (!pvId) { toast('PV non trouve', 'error'); window.history.back(); return; }

        const result = await adminApiPost('admin_get_pv', { id: pvId });
        if (!result.success) { toast('Erreur: ' + result.message, 'error'); window.history.back(); return; }

        pvData = result.pv;
        updatePvInfo();

        editorInstance = await editorModule.createEditor(document.getElementById('pvEditorContainer'), {
            placeholder: "Le texte transcrit s'affichera ici en temps reel...",
            content: pvData.contenu || '',
            mode: 'full'
        });

        setupRecordingControls();
        setupFileUpload();
        setupPrintExport();
        initLocalAI();

        // Save button
        document.getElementById('btnSavePv')?.addEventListener('click', savePv);
    }

    function updatePvInfo() {
        document.getElementById('pvRecordTitle').textContent = pvData.titre;
        document.getElementById('pvRecordTitleInfo').textContent = pvData.titre;
        document.getElementById('pvRecordCreator').textContent = (pvData.creator_prenom || '') + ' ' + (pvData.creator_nom || '');
        document.getElementById('pvRecordModule').textContent = pvData.module_nom || '—';
        document.getElementById('pvAllowComments').checked = pvData.allow_comments != 0;

        const participants = pvData.participants || [];
        document.getElementById('pvRecordParticipants').textContent =
            participants.length > 0 ? participants.map(p => p.prenom + ' ' + p.nom).join(', ') : '—';

        if (pvData.audio_path) {
            showAudioPlayback('/zerdatime/admin/api.php?action=admin_serve_pv_audio&id=' + pvData.id);
        }
    }

    function showAudioPlayback(url) {
        const container = document.getElementById('audioPlaybackContainer');
        const player = document.getElementById('audioPlayback');
        player.src = url + '?t=' + Date.now();
        container.classList.remove('d-none');
    }

    // ── Local AI (Transformers.js) ──
    async function initLocalAI() {
        try {
            const { pipeline, env } = await import('/zerdatime/assets/ai/js/transformers.min.js');
            env.allowLocalModels = true;
            env.allowRemoteModels = false;
            env.localModelPath = '/zerdatime/assets/ai/models/';
            env.backends.onnx.wasm.wasmPaths = '/zerdatime/assets/ai/js/';
            transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-tiny');
            isAiReady = true;
            console.log('Modele IA pret');
        } catch (e) {
            console.error('Erreur IA locale:', e);
            toast("L'IA locale n'a pas pu demarrer.", 'error');
        }
    }

    async function liveTranscribeBlob(blob) {
        if (!isAiReady || !transcriber) return;
        document.getElementById('processingStatus').style.display = 'flex';
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            const arrayBuffer = await blob.arrayBuffer();
            const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
            const float32Array = audioBuffer.getChannelData(0);
            if (float32Array.length < 8000) return;
            const output = await transcriber(float32Array, { language: 'french', task: 'transcribe', return_timestamps: false });
            if (output.text && output.text.trim().length > 0 && editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
                editorInstance.view.dom.scrollTop = editorInstance.view.dom.scrollHeight;
            }
        } catch (e) {
            console.error('Erreur transcription:', e);
        } finally {
            if (!isRecording || isPaused) document.getElementById('processingStatus').style.display = 'none';
        }
    }

    async function transcribeFullAudioBlob(blob) {
        if (!isAiReady || !transcriber) return;
        document.getElementById('processingStatus').style.display = 'flex';
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            const arrayBuffer = await blob.arrayBuffer();
            const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
            const float32Array = audioBuffer.getChannelData(0);
            const output = await transcriber(float32Array, { chunk_length_s: 30, stride_length_s: 5, language: 'french', task: 'transcribe', return_timestamps: false });
            if (output.text && output.text.trim().length > 0 && editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
            }
            toast('Transcription terminee !');
        } catch (e) {
            console.error('Erreur transcription:', e);
            toast('Erreur de transcription.', 'error');
        } finally {
            document.getElementById('processingStatus').style.display = 'none';
        }
    }

    // ── Recording controls ──
    function setupRecordingControls() {
        document.getElementById('btnStartRecord')?.addEventListener('click', startRecording);
        document.getElementById('btnStopRecord')?.addEventListener('click', stopRecording);
        document.getElementById('btnPauseRecord')?.addEventListener('click', togglePauseRecording);
    }

    function startVisualizer() {
        const canvas = document.getElementById('audioVisualizer');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        function draw() {
            if (!isRecording) return;
            visAnimationId = requestAnimationFrame(draw);
            if (!isPaused) { visAnalyser.getByteFrequencyData(visDataArray); } else { visDataArray.fill(0); }
            ctx.fillStyle = isPaused ? '#F7F5F2' : '#FFF8F4';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            const barWidth = (canvas.width / visAnalyser.frequencyBinCount) * 2;
            let x = 0;
            for (let i = 0; i < visAnalyser.frequencyBinCount; i++) {
                const barHeight = (visDataArray[i] / 255) * canvas.height;
                ctx.fillStyle = `rgba(217, 119, 87, ${Math.max(0.3, visDataArray[i] / 255)})`;
                ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
                x += barWidth + 2;
            }
        }
        draw();
    }

    async function startRecording() {
        if (!isAiReady) { toast("Modele IA en cours de chargement...", 'error'); return; }
        try {
            audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            recordedChunks = [];
            totalRecordingTime = 0;
            isPaused = false;

            visAudioContext = new (window.AudioContext || window.webkitAudioContext)();
            visAnalyser = visAudioContext.createAnalyser();
            visAudioContext.createMediaStreamSource(audioStream).connect(visAnalyser);
            visAnalyser.fftSize = 64;
            visDataArray = new Uint8Array(visAnalyser.frequencyBinCount);

            mainRecorder = new MediaRecorder(audioStream);
            mainRecorder.ondataavailable = (e) => { if (e.data.size > 0) recordedChunks.push(e.data); };
            mainRecorder.onstop = async () => {
                if (recordedChunks.length > 0) {
                    await uploadAudioBlob(new Blob(recordedChunks, { type: 'audio/webm' }), 'recorded_pv.webm');
                }
            };
            mainRecorder.start();
            startLiveTranscriber();
            isRecording = true;
            startVisualizer();

            document.getElementById('btnStartRecord').style.display = 'none';
            document.getElementById('btnPauseRecord').style.display = 'inline-flex';
            document.getElementById('btnStopRecord').style.display = 'inline-flex';
            const bar = document.getElementById('recordingStatus');
            bar.classList.remove('d-none');
            bar.classList.remove('paused');

            recordingInterval = setInterval(() => {
                if (isRecording && !isPaused) {
                    totalRecordingTime++;
                    const m = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
                    const s = String(totalRecordingTime % 60).padStart(2, '0');
                    document.getElementById('recordingTime').textContent = m + ':' + s;
                }
            }, 1000);
        } catch (err) {
            toast("Erreur micro : " + err.message, 'error');
        }
    }

    function startLiveTranscriber() {
        liveRecorder = new MediaRecorder(audioStream);
        liveRecorder.ondataavailable = (e) => {
            if (isPaused || e.data.size === 0) return;
            transcribingQueue = transcribingQueue.then(() => liveTranscribeBlob(new Blob([e.data], { type: 'audio/webm' })));
        };
        liveRecorder.start();
        liveInterval = setInterval(() => {
            if (isRecording && !isPaused && liveRecorder.state === 'recording') {
                liveRecorder.stop();
                liveRecorder.start();
            }
        }, 5000);
    }

    function togglePauseRecording() {
        if (!isRecording) return;
        isPaused = !isPaused;
        const btnPause = document.getElementById('btnPauseRecord');
        const bar = document.getElementById('recordingStatus');
        const statusText = document.getElementById('recordingStatusText');

        if (isPaused) {
            if (mainRecorder?.state === 'recording') mainRecorder.pause();
            if (liveRecorder?.state === 'recording') liveRecorder.pause();
            btnPause.innerHTML = '<i class="bi bi-play-fill"></i> Reprendre';
            bar.classList.add('paused');
            statusText.textContent = 'Dictee en pause...';
        } else {
            if (mainRecorder?.state === 'paused') mainRecorder.resume();
            if (liveRecorder?.state === 'paused') liveRecorder.resume();
            btnPause.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
            bar.classList.remove('paused');
            statusText.textContent = 'Ecoute en cours... Parlez distinctement.';
        }
    }

    function stopRecording() {
        if (!isRecording) return;
        if (mainRecorder?.state !== 'inactive') mainRecorder.stop();
        clearInterval(liveInterval);
        if (liveRecorder?.state !== 'inactive') liveRecorder.stop();
        if (audioStream) audioStream.getTracks().forEach(t => t.stop());
        if (visAudioContext) { visAudioContext.close(); visAudioContext = null; }
        if (visAnimationId) cancelAnimationFrame(visAnimationId);
        const canvas = document.getElementById('audioVisualizer');
        if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        clearInterval(recordingInterval);
        isRecording = false;

        document.getElementById('btnStartRecord').style.display = 'inline-flex';
        document.getElementById('btnPauseRecord').style.display = 'none';
        document.getElementById('btnStopRecord').style.display = 'none';
        document.getElementById('recordingStatus').classList.add('d-none');
        document.getElementById('recordingStatus').classList.remove('paused');
        document.getElementById('recordingTime').textContent = '00:00';
        transcribingQueue.then(() => { document.getElementById('processingStatus').style.display = 'none'; });
    }

    // ── File upload ──
    function setupFileUpload() {
        document.getElementById('btnUploadAudio')?.addEventListener('click', async () => {
            const input = document.getElementById('audioFileInput');
            if (!input?.files.length) { toast('Selectionnez un fichier', 'error'); return; }
            const file = input.files[0];
            const btn = document.getElementById('btnUploadAudio');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            const ok = await uploadAudioBlob(file, file.name);
            if (ok) await transcribeFullAudioBlob(file);
            btn.disabled = false;
            btn.textContent = 'Importer';
            input.value = '';
        });
    }

    async function uploadAudioBlob(blob, filename) {
        const fd = new FormData();
        fd.append('action', 'admin_upload_pv_audio');
        fd.append('id', pvId);
        fd.append('audio', blob, filename);
        try {
            const headers = {};
            if (window.__ZT_ADMIN__?.csrfToken) headers['X-CSRF-Token'] = window.__ZT_ADMIN__.csrfToken;
            const res = await fetch('/zerdatime/admin/api.php', { method: 'POST', headers, body: fd });
            const json = await res.json();
            if (json.csrf && window.__ZT_ADMIN__) window.__ZT_ADMIN__.csrfToken = json.csrf;
            if (json.success) {
                if (json.audio_path) showAudioPlayback('/zerdatime/admin/api.php?action=admin_serve_pv_audio&id=' + pvId);
                return true;
            }
            toast('Erreur: ' + (json.message || 'Echec'), 'error');
            return false;
        } catch (e) {
            toast('Erreur reseau', 'error');
            return false;
        }
    }

    // ── Save & Finalize ──
    async function savePv() {
        const transcript = editorInstance && editorModule ? editorModule.getHTML(editorInstance) : '';
        const allowComments = document.getElementById('pvAllowComments').checked ? 1 : 0;
        if (!transcript || transcript === '<p></p>') { toast('Ecrivez ou dictez du contenu', 'error'); return; }

        const btn = document.getElementById('btnSavePv');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';
        try {
            const r = await adminApiPost('admin_update_pv', { id: pvId, contenu: transcript, allow_comments: allowComments });
            if (r.success) {
                const f = await adminApiPost('admin_finalize_pv', { id: pvId });
                if (f.success) {
                    toast('PV enregistre et finalise !');
                    setTimeout(() => { window.location.href = AdminURL.page('pv'); }, 1200);
                    return;
                }
                toast('Erreur finalisation', 'error');
            } else {
                toast('Erreur sauvegarde', 'error');
            }
        } catch (e) {
            toast('Erreur: ' + e.message, 'error');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer et finaliser';
    }

    // ── Print / Export ──
    function setupPrintExport() {
        function getPrintHtml() {
            const title = escapeHtml(document.getElementById('pvRecordTitle')?.textContent || 'Proces-verbal');
            const content = document.querySelector('#pvEditorContainer .ProseMirror')?.innerHTML
                         || document.querySelector('#pvEditorContainer .zs-ed-content')?.innerHTML || '';
            return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
              '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;color:#333;line-height:1.6}' +
              'h1{font-size:1.4rem;border-bottom:2px solid #333;padding-bottom:8px}' +
              '.info{color:#666;font-size:0.85rem;margin-bottom:1.5rem}' +
              '@media print{body{margin:20px}}</style></head>' +
              '<body><h1>' + title + '</h1>' +
              '<div class="info">Date: ' + new Date().toLocaleDateString('fr-CH') + ' — zerdaTime</div>' +
              content + '</body></html>';
        }
        document.getElementById('btnPrintPv')?.addEventListener('click', () => {
            const win = window.open('', '_blank');
            win.document.write(getPrintHtml());
            win.document.close();
            win.focus();
            win.print();
        });
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const win = window.open('', '_blank');
            win.document.write(getPrintHtml());
            win.document.close();
            win.focus();
            win.print();
        });
    }

    // ── Expose for admin.js init ──
    window.initPvrecordPage = initPvrecordPage;
})();
</script>
