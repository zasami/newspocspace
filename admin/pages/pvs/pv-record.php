<!-- PV Recording Page -->
<link rel="stylesheet" href="/terrassiere/admin/assets/css/editor.css">
<link rel="stylesheet" href="/terrassiere/admin/assets/css/emoji-picker.css">
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
      <i class="bi bi-mic-fill"></i> <span id="pvRecordTitle">Chargement...</span>
    </h1>
    <div class="d-flex gap-2 align-items-center">
      <button class="btn shadow-sm btn-pv-record" id="btnStartRecord">
        <i class="bi bi-mic-fill"></i> DÉMARRER LA DICTÉE
      </button>
      <button class="btn shadow-sm btn-pv-pause d-hidden" id="btnPauseRecord">
        <i class="bi bi-pause-fill"></i> PAUSE
      </button>
      <button class="btn shadow-sm btn-pv-stop d-hidden" id="btnStopRecord">
        <i class="bi bi-stop-circle-fill"></i> ARRÊTER L'ENREGISTREMENT
      </button>
      <button class="btn btn-pv-back" id="btnRecordBack">
        <i class="bi bi-arrow-left"></i> Retour
      </button>
    </div>
  </div>

  <!-- Recording Controls -->
  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title d-flex justify-content-between align-items-center">
            <span>Enregistrement & Transcription en Direct</span>
            <span class="badge bg-success small"><i class="bi bi-shield-lock"></i> IA Locale Active</span>
          </h5>
          
          <!-- Recording Status -->
          <div id="recordingStatus" class="alert mb-3 d-hidden pv-recording-status">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-circle-fill pv-indicator-active" id="recordingIndicator"></i>
                <strong id="recordingStatusText">Écoute en cours... Parlez distinctement.</strong>
                <div class="small mt-1 text-muted">Le texte s'affichera par blocs de quelques secondes grâce à l'IA locale.</div>
              </div>
              <div class="text-end">
                <span id="recordingTime" class="fw-bold fs-5 font-monospace d-block">00:00</span>
                <canvas id="audioVisualizer" width="120" height="30" class="mt-1 pv-audio-visualizer"></canvas>
              </div>
            </div>
          </div>

          <!-- Processing Status -->
          <div id="processingStatus" class="alert alert-info mb-3 py-2 small d-hidden">
            <span class="spinner-border spinner-border-sm me-2"></span>
            L'IA traduit votre voix en texte...
          </div>

          <!-- Transcript Area -->
          <div class="form-group mb-3">
            <label class="form-label"><strong>Contenu du PV</strong></label>
            <div id="pvEditorContainer" class="zs-editor-wrap form-control p-0 pv-editor-container">
                <!-- Tiptap Editor will be mounted here -->
            </div>
            <textarea id="pvTranscript" class="d-hidden"></textarea>
          </div>

          <!-- Audio Upload Alternative -->
          <div class="mb-3 border-top pt-3 mt-4">
            <label class="form-label small"><strong>Ou importer un fichier audio externe</strong> <small class="text-muted">(Sera transcrit en une seule fois)</small></label>
            <div class="d-flex gap-2">
                <input type="file" class="form-control form-control-sm" id="audioFileInput" accept="audio/*">
                <button class="btn btn-outline-primary btn-sm" id="btnUploadAudio">Importer et Transcrire</button>
            </div>
            
            <div id="audioPlaybackContainer" class="mt-3 d-hidden pv-audio-playback">
                <label class="form-label small mb-1"><strong><i class="bi bi-play-circle"></i> Audio associé à ce PV :</strong></label>
                <audio id="audioPlayback" controls class="w-100"></audio>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Side Panel -->
    <div class="col-lg-4">
      <!-- PV Info -->
      <div class="card mb-4">
        <div class="card-header">
          <h6 class="mb-0">Infos du PV</h6>
        </div>
        <div class="card-body small">
          <div class="mb-2">
            <strong>Titre:</strong><br>
            <span id="pvRecordTitleInfo">—</span>
          </div>
          <div class="mb-2">
            <strong>Créateur:</strong><br>
            <span id="pvRecordCreator">—</span>
          </div>
          <div class="mb-2">
            <strong>Module:</strong><br>
            <span id="pvRecordModule">—</span>
          </div>
          <div class="mb-2">
            <strong>Participants:</strong><br>
            <span id="pvRecordParticipants">—</span>
          </div>
          <div class="mb-3">
            <strong>Statut:</strong><br>
            <span class="badge pv-status-badge" id="pvRecordStatus">brouillon</span>
          </div>
          
          <div class="border-top pt-3">
            <div class="d-flex align-items-center justify-content-between">
              <label class="small fw-bold mb-0" for="pvAllowComments">Autoriser les commentaires et la note</label>
              <div class="form-check form-switch form-switch-sm mb-0">
                <input class="form-check-input" type="checkbox" id="pvAllowComments" checked>
              </div>
            </div>
            <small class="text-muted d-block mt-1">Si actif, les employés pourront noter ce PV (5 étoiles) et ajouter des commentaires via l'éditeur.</small>
          </div>
        </div>
      </div>

      <!-- Save PV -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Finaliser</h6>
        </div>
        <div class="card-body">
          <button class="btn w-100 btn-sm btn-pv-finalize" id="btnSavePv" data-action="save-pv">
            <i class="bi bi-check-lg"></i> Enregistrer et finaliser le PV
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
/* Ensure the editor content area expands */
.zs-ed-content { min-height: 250px; padding: 1rem; }
.zs-ed-content .ProseMirror { min-height: 250px; outline: none; }

/* Button styles */
.btn-pv-record {
  background-color: #D97757;
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 8px;
}
.btn-pv-record:hover { background-color: #c4684b; color: white; }

.btn-pv-pause {
  background-color: #F3F1EC;
  border: 1px solid #D1CDC4;
  color: #1E1E1E;
  font-weight: 500;
  border-radius: 8px;
}
.btn-pv-pause:hover { background-color: #e8e5df; }

.btn-pv-stop {
  background-color: #1E1E1E;
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 8px;
}
.btn-pv-stop:hover { background-color: #333; color: white; }

.btn-pv-back {
  background-color: #F3F1EC;
  border: 1px solid #D1CDC4;
  color: #1E1E1E;
  font-weight: 500;
  border-radius: 8px;
}
.btn-pv-back:hover { background-color: #e8e5df; }

.btn-pv-finalize {
  background-color: #16A34A;
  border: none;
  color: white;
  font-weight: 600;
}
.btn-pv-finalize:hover { background-color: #15803D; color: white; }

/* Recording status */
.pv-recording-status {
  background-color: #FFF8E1;
  border-color: #FFC107;
  border-left: 4px solid #FFC107;
  padding: 12px;
  transition: all 0.3s;
}
.pv-recording-status--paused {
  background-color: #f8f9fa;
  border-color: #dee2e6;
  border-left-color: #dee2e6;
}

/* Recording indicator */
.pv-indicator-active { color: #DC2626; animation: pulse 1s infinite; }
.pv-indicator-paused { color: #6c757d; animation: none; }

/* Audio visualizer */
.pv-audio-visualizer { border-radius: 2px; }

/* Editor container */
.pv-editor-container { min-height: 300px; }

/* Status badge */
.pv-status-badge { background-color: #FFC107; color: #1B2A4A; font-weight: 600; }

/* Audio playback */
.pv-audio-playback { background: #F8F9FA; padding: 10px; border-radius: 6px; }

/* Visibility helpers */
.d-hidden { display: none; }
.d-inline-block { display: inline-block; }
.d-block-visible { display: block; }
</style>

<script type="module">
import { createEditor, getHTML, setContent } from '/terrassiere/assets/js/rich-editor.js';

let isRecording = false;
let isPaused = false;
let totalRecordingTime = 0;
let recordingInterval = null;
let pvId = null;
let pvData = null;
let audioStream = null;

// Enregistreur principal (pour sauvegarder le fichier final complet)
let mainRecorder = null;
let recordedChunks = [];

// Enregistreur en direct (pour la transcription temps réel)
let liveRecorder = null;
let liveInterval = null;
let transcribingQueue = Promise.resolve();

// Variables globales pour l'IA
let transcriber = null;
let isAiReady = false;

// Variables pour le visualiseur audio
let visAudioContext = null;
let visAnalyser = null;
let visDataArray = null;
let visAnimationId = null;

// Instance de l'éditeur Tiptap
let editorInstance = null;

const adminApiPost = window.adminApiPost;
const toast = window.toast;

// Initialize
async function initPvRecord() {
  pvId = AdminURL.currentId();

  if (!pvId) {
    toast('PV non trouvé', 'error');
    window.history.back();
    return;
  }

  const result = await adminApiPost('admin_get_pv', { id: pvId });
  if (!result.success) {
    toast('Erreur: ' + result.message, 'error');
    window.history.back();
    return;
  }

  pvData = result.pv;
  updatePvInfo();

  // Initialize Tiptap editor
  editorInstance = await createEditor(document.getElementById('pvEditorContainer'), {
      placeholder: "Le texte transcrit s'affichera ici en temps réel. Vous pouvez corriger les éventuelles erreurs manuellement...",
      content: pvData.contenu || '',
      mode: 'full'
  });

  setupRecordingControls();
  setupFileUpload();

  initLocalAI();
}

// Run init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPvRecord);
} else {
    initPvRecord();
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
      showAudioPlayback('/terrassiere/admin/api.php?action=admin_serve_pv_audio&id=' + pvData.id);
  }
}

function showAudioPlayback(url) {
    const container = document.getElementById('audioPlaybackContainer');
    const player = document.getElementById('audioPlayback');
    player.src = url + '?t=' + new Date().getTime();
    container.classList.remove('d-hidden');
}

// ----------------------------------------------------
// IA LOCALE (Transformers.js)
// ----------------------------------------------------
async function initLocalAI() {
    try {
        const { pipeline, env } = await import('/terrassiere/assets/ai/js/transformers.min.js');
        
        env.allowLocalModels = true;
        env.allowRemoteModels = false;
        env.localModelPath = '/terrassiere/assets/ai/models/';
        env.backends.onnx.wasm.wasmPaths = '/terrassiere/assets/ai/js/';
        
        console.log("Chargement du modèle Whisper en mémoire...");
        
        transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-tiny');
        isAiReady = true;
        
        console.log("Modèle IA prêt !");
    } catch (e) {
        console.error("Erreur d'initialisation de l'IA locale:", e);
        toast("L'IA locale n'a pas pu démarrer. Vérifiez les fichiers.", "error");
    }
}

async function liveTranscribeBlob(blob) {
    if (!isAiReady || !transcriber) return;
    
    document.getElementById('processingStatus').classList.remove('d-hidden');

    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        const float32Array = audioBuffer.getChannelData(0);

        if (float32Array.length < 8000) return;

        const output = await transcriber(float32Array, {
            language: 'french',
            task: 'transcribe',
            return_timestamps: false
        });

        if (output.text && output.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
                const el = editorInstance.view.dom;
                el.scrollTop = el.scrollHeight;
            }
        }
    } catch (e) {
        console.error("Erreur de transcription en direct:", e);
    } finally {
        if (!isRecording || isPaused) document.getElementById('processingStatus').classList.add('d-hidden');
    }
}

async function transcribeFullAudioBlob(blob) {
    if (!isAiReady || !transcriber) return;
    document.getElementById('processingStatus').classList.remove('d-hidden');
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        const float32Array = audioBuffer.getChannelData(0);

        const output = await transcriber(float32Array, {
            chunk_length_s: 30,
            stride_length_s: 5,
            language: 'french',
            task: 'transcribe',
            return_timestamps: false
        });

        if (output.text && output.text.trim().length > 0) {
            if (editorInstance) {
                editorInstance.commands.insertContent(output.text.trim() + ' ');
            }
        }
        toast("Transcription complète terminée !");
    } catch (e) {
        console.error("Erreur de transcription:", e);
        toast("Erreur lors de la transcription complète.", "error");
    } finally {
        document.getElementById('processingStatus').classList.add('d-hidden');
    }
}

// ----------------------------------------------------
// ENREGISTREMENT ET UPLOAD
// ----------------------------------------------------
function setupRecordingControls() {
    const btnStart = document.getElementById('btnStartRecord');
    const btnStop = document.getElementById('btnStopRecord');
    const btnPause = document.getElementById('btnPauseRecord');

    if (btnStart) btnStart.addEventListener('click', startRecording);
    if (btnStop) btnStop.addEventListener('click', stopRecording);
    if (btnPause) btnPause.addEventListener('click', togglePauseRecording);
}

function startVisualizer() {
    const canvas = document.getElementById('audioVisualizer');
    if (!canvas) return;
    const canvasCtx = canvas.getContext('2d');

    function draw() {
        if (!isRecording) return;
        visAnimationId = requestAnimationFrame(draw);

        if (!isPaused) {
            visAnalyser.getByteFrequencyData(visDataArray);
        } else {
            visDataArray.fill(0); // Flat line when paused
        }

        canvasCtx.fillStyle = isPaused ? '#f8f9fa' : '#FFF8E1'; 
        canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

        const barWidth = (canvas.width / visAnalyser.frequencyBinCount) * 2;
        let x = 0;

        for (let i = 0; i < visAnalyser.frequencyBinCount; i++) {
            const barHeight = (visDataArray[i] / 255) * canvas.height;
            // Brun du thème
            canvasCtx.fillStyle = `rgba(93, 64, 55, ${Math.max(0.4, visDataArray[i] / 255)})`;
            canvasCtx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
            x += barWidth + 2;
        }
    }
    draw();
}

async function startRecording() {
    if (!isAiReady) {
        toast("Veuillez patienter quelques secondes, le modèle d'IA est en cours de chargement...", "warning");
        return;
    }

    try {
        audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        recordedChunks = [];
        totalRecordingTime = 0;
        isPaused = false;
        
        // Setup Visualizer
        visAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        visAnalyser = visAudioContext.createAnalyser();
        const visSource = visAudioContext.createMediaStreamSource(audioStream);
        visSource.connect(visAnalyser);
        visAnalyser.fftSize = 64;
        visDataArray = new Uint8Array(visAnalyser.frequencyBinCount);
        
        mainRecorder = new MediaRecorder(audioStream);
        mainRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recordedChunks.push(e.data);
        };
        mainRecorder.onstop = async () => {
            if (recordedChunks.length > 0) {
                const blob = new Blob(recordedChunks, { type: 'audio/webm' });
                await uploadAudioBlob(blob, 'recorded_pv.webm'); 
            }
        };
        mainRecorder.start();

        startLiveTranscriber();

        isRecording = true;
        
        startVisualizer();

        document.getElementById('btnStartRecord').classList.add('d-hidden');
        document.getElementById('btnPauseRecord').classList.remove('d-hidden');
        document.getElementById('btnPauseRecord').classList.add('d-inline-block');
        document.getElementById('btnStopRecord').classList.remove('d-hidden');
        document.getElementById('btnStopRecord').classList.add('d-inline-block');
        document.getElementById('recordingStatus').classList.remove('d-hidden');

        recordingInterval = setInterval(() => {
            if (isRecording && !isPaused) {
                totalRecordingTime++;
                const m = String(Math.floor(totalRecordingTime / 60)).padStart(2, '0');
                const s = String(totalRecordingTime % 60).padStart(2, '0');
                document.getElementById('recordingTime').textContent = `${m}:${s}`;
            }
        }, 1000);

    } catch (err) {
        toast('Erreur d\'accès au microphone : ' + err.message, 'error');
        console.error("Microphone access error", err);
    }
}

function startLiveTranscriber() {
    liveRecorder = new MediaRecorder(audioStream);
    liveRecorder.ondataavailable = (e) => {
        if (isPaused) return; // Ignorer le chunk si en pause
        if (e.data.size > 0) {
            const blob = new Blob([e.data], { type: 'audio/webm' });
            transcribingQueue = transcribingQueue.then(() => liveTranscribeBlob(blob));
        }
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
    const recordingStatus = document.getElementById('recordingStatus');
    const indicator = document.getElementById('recordingIndicator');
    const statusText = document.getElementById('recordingStatusText');
    
    if (isPaused) {
        if (mainRecorder && mainRecorder.state === 'recording') mainRecorder.pause();
        if (liveRecorder && liveRecorder.state === 'recording') liveRecorder.pause();

        btnPause.innerHTML = '<i class="bi bi-play-fill"></i> REPRENDRE';
        btnPause.classList.remove('btn-warning');
        btnPause.classList.add('btn-success');

        recordingStatus.classList.add('pv-recording-status--paused');
        indicator.classList.remove('pv-indicator-active');
        indicator.classList.add('pv-indicator-paused');
        statusText.textContent = 'Dictée en pause...';
    } else {
        if (mainRecorder && mainRecorder.state === 'paused') mainRecorder.resume();
        if (liveRecorder && liveRecorder.state === 'paused') liveRecorder.resume();

        btnPause.innerHTML = '<i class="bi bi-pause-fill"></i> PAUSE';
        btnPause.classList.remove('btn-success');
        btnPause.classList.add('btn-warning');

        recordingStatus.classList.remove('pv-recording-status--paused');
        indicator.classList.remove('pv-indicator-paused');
        indicator.classList.add('pv-indicator-active');
        statusText.textContent = 'Écoute en cours... Parlez distinctement.';
    }
}

function stopRecording() {
    if (!isRecording) return;
    
    if (mainRecorder && mainRecorder.state !== 'inactive') mainRecorder.stop();
    
    clearInterval(liveInterval);
    if (liveRecorder && liveRecorder.state !== 'inactive') liveRecorder.stop();

    if (audioStream) {
        audioStream.getTracks().forEach(track => track.stop());
    }

    if (visAudioContext) {
        visAudioContext.close();
        visAudioContext = null;
    }
    if (visAnimationId) {
        cancelAnimationFrame(visAnimationId);
    }
    const canvas = document.getElementById('audioVisualizer');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    clearInterval(recordingInterval);
    isRecording = false;

    document.getElementById('btnStartRecord').classList.remove('d-hidden');
    document.getElementById('btnPauseRecord').classList.add('d-hidden');
    document.getElementById('btnPauseRecord').classList.remove('d-inline-block');
    document.getElementById('btnStopRecord').classList.add('d-hidden');
    document.getElementById('btnStopRecord').classList.remove('d-inline-block');
    document.getElementById('recordingStatus').classList.add('d-hidden');
    document.getElementById('recordingTime').textContent = '00:00';

    transcribingQueue.then(() => {
        document.getElementById('processingStatus').classList.add('d-hidden');
    });
}

function setupFileUpload() {
    const fileInput = document.getElementById('audioFileInput');
    const btnUpload = document.getElementById('btnUploadAudio');

    if (btnUpload) btnUpload.addEventListener('click', async () => {
        if (!fileInput.files.length) {
            toast('Veuillez sélectionner un fichier', 'error');
            return;
        }
        const file = fileInput.files[0];
        btnUpload.disabled = true;
        btnUpload.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Traitement...';
        
        const uploadSuccess = await uploadAudioBlob(file, file.name);
        
        if (uploadSuccess) {
            await transcribeFullAudioBlob(file);
        }
        
        btnUpload.disabled = false;
        btnUpload.textContent = 'Importer et Transcrire';
        fileInput.value = '';
    });
}

async function uploadAudioBlob(blob, filename) {
    const formData = new FormData();
    formData.append('action', 'admin_upload_pv_audio');
    formData.append('id', pvId);
    formData.append('audio', blob, filename);

    try {
        const headers = {};
        if (window.__ZT_ADMIN__?.csrfToken) {
            headers['X-CSRF-Token'] = window.__ZT_ADMIN__.csrfToken;
        }

        const res = await fetch('/terrassiere/admin/api.php', {
            method: 'POST',
            headers: headers,
            body: formData
        });

        const json = await res.json();
        if (json.csrf && window.__ZT_ADMIN__) window.__ZT_ADMIN__.csrfToken = json.csrf;

        if (json.success) {
            if (json.audio_path) showAudioPlayback('/terrassiere/admin/api.php?action=admin_serve_pv_audio&id=' + currentPvId);
            return true;
        } else {
            toast('Erreur: ' + (json.message || 'Échec de la sauvegarde audio'), 'error');
            return false;
        }
    } catch (e) {
        toast('Erreur réseau lors de la sauvegarde audio', 'error');
        return false;
    }
}

// Save button via event delegation
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="save-pv"]');
    if (btn) savePv();
});

async function savePv() {
  const transcript = editorInstance ? getHTML(editorInstance) : '';
  const allowComments = document.getElementById('pvAllowComments').checked ? 1 : 0;
  
  if (!transcript || transcript === '<p></p>') {
      toast('Veuillez dicter ou écrire du contenu', 'error');
      return;
  }

  const btn = document.getElementById('btnSavePv');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

  try {
    const r = await adminApiPost('admin_update_pv', {
      id: pvId,
      contenu: transcript,
      allow_comments: allowComments
    });

    if (r.success) {
      const f = await adminApiPost('admin_finalize_pv', { id: pvId });
      if (f.success) {
        toast('PV enregistré et finalisé !');
        setTimeout(() => {
          window.location.href = AdminURL.page('pv');
        }, 1500);
      } else {
        toast('Erreur lors de la finalisation', 'error');
        resetSaveBtn(btn);
      }
    } else {
      toast('Erreur lors de la sauvegarde du contenu', 'error');
      resetSaveBtn(btn);
    }
  } catch (e) {
    toast('Erreur: ' + e.message, 'error');
    resetSaveBtn(btn);
  }
}

function resetSaveBtn(btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer et finaliser le PV';
}
</script>