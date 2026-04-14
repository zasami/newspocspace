/**
 * SpocSpace — Call UI controller
 * Modals : dialing (outgoing), incoming, in-call
 */
import * as call from './call.js';
import { apiPost, toast, escapeHtml } from './helpers.js';

let _incomingRingtone = null;
let _pollTimer = null;
let _currentIncomingId = null;

// ══════════════════════════════════════════════════════════════
// Public : init polling for incoming calls
// ══════════════════════════════════════════════════════════════

export function initIncomingPoll() {
    call.setUICallbacks({
        onDialing: showDialingModal,
        onAccepted: showInCallModal,
        onRejected: handleRejected,
        onRemoteStream: attachRemoteStream,
        onConnected: () => {},
        onDuration: updateDuration,
        onEnded: hideAllModals,
    });
    _startPoll();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') _startPoll();
    });
}

function _startPoll() {
    if (_pollTimer) return;
    console.log('[call-ui] Starting incoming poll (every 2s)');
    _pollTimer = setInterval(async () => {
        if (call.isInCall()) return;
        try {
            const res = await apiPost('call_poll');
            const inc = res.incoming?.[0];
            if (inc && inc.id !== _currentIncomingId) {
                console.log('[call-ui] Incoming call detected:', inc);
                _currentIncomingId = inc.id;
                showIncomingModal(inc);
            }
        } catch (e) {
            console.error('[call-ui] poll error:', e);
        }
    }, 2000);
}

// ══════════════════════════════════════════════════════════════
// Public : trigger outgoing call
// ══════════════════════════════════════════════════════════════

export async function startCall(user, media = 'audio') {
    console.log('[call-ui] startCall →', user, media);
    try {
        await call.makeCall(user, media);
    } catch (e) {
        console.error('[call-ui] startCall error:', e);
        toast(e.message || 'Erreur', 4000);
    }
}

// ══════════════════════════════════════════════════════════════
// Incoming call modal
// ══════════════════════════════════════════════════════════════

function showIncomingModal(inc) {
    hideAllModals();
    _startRingtone();

    const overlay = document.createElement('div');
    overlay.id = 'ssCallIncoming';
    overlay.className = 'ss-call-overlay ss-call-overlay--incoming';
    const fullName = [inc.prenom, inc.nom].filter(Boolean).join(' ');
    const initials = (inc.prenom?.[0] || '') + (inc.nom?.[0] || '');
    const avatar = inc.photo
        ? `<img src="${escapeHtml(inc.photo)}" class="ss-call-avatar-img" alt="">`
        : `<div class="ss-call-avatar-initials">${escapeHtml(initials.toUpperCase())}</div>`;

    overlay.innerHTML = `
        <div class="ss-call-card">
            <div class="ss-call-status-label pulsing">
                <i class="bi bi-telephone-inbound-fill"></i> Appel ${inc.media === 'video' ? 'vidéo' : 'audio'} entrant
            </div>
            <div class="ss-call-avatar">${avatar}</div>
            <div class="ss-call-name">${escapeHtml(fullName)}</div>
            ${inc.fonction_nom ? '<div class="ss-call-fonction">' + escapeHtml(inc.fonction_nom) + '</div>' : ''}
            <div class="ss-call-actions">
                <button class="ss-call-btn ss-call-btn-reject" id="ssCallReject" title="Refuser">
                    <i class="bi bi-telephone-x-fill"></i>
                </button>
                <button class="ss-call-btn ss-call-btn-accept" id="ssCallAccept" title="Accepter">
                    <i class="bi bi-telephone-fill"></i>
                </button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    let _accepting = false;
    const acceptBtn = document.getElementById('ssCallAccept');
    const handleAccept = async (e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (_accepting) return;
        _accepting = true;
        console.log('[call-ui] Accept button clicked');
        _stopRingtone();
        if (overlay.parentNode) overlay.remove();
        try {
            await call.acceptCall(inc);
            console.log('[call-ui] acceptCall success');
        } catch (err) {
            console.error('[call-ui] acceptCall error:', err);
            toast(err.message || 'Erreur', 4000);
        }
    };
    acceptBtn.addEventListener('pointerdown', handleAccept);
    let _rejecting = false;
    const rejectBtn = document.getElementById('ssCallReject');
    const handleReject = async (e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (_rejecting) return;
        _rejecting = true;
        console.log('[call-ui] Reject button clicked');
        _stopRingtone();
        if (overlay.parentNode) overlay.remove();
        await call.rejectCall(inc.id);
        _currentIncomingId = null;
    };
    rejectBtn.addEventListener('pointerdown', handleReject);
}

// ══════════════════════════════════════════════════════════════
// Dialing modal (outgoing)
// ══════════════════════════════════════════════════════════════

function showDialingModal(c) {
    hideAllModals();
    const p = c.peer;
    const fullName = [p.prenom, p.nom].filter(Boolean).join(' ');
    const initials = (p.prenom?.[0] || '') + (p.nom?.[0] || '');
    const avatar = p.photo
        ? `<img src="${escapeHtml(p.photo)}" class="ss-call-avatar-img" alt="">`
        : `<div class="ss-call-avatar-initials">${escapeHtml(initials.toUpperCase())}</div>`;

    const overlay = document.createElement('div');
    overlay.id = 'ssCallDialing';
    overlay.className = 'ss-call-overlay';
    overlay.innerHTML = `
        <div class="ss-call-card">
            <div class="ss-call-status-label pulsing">
                <i class="bi bi-telephone-outbound-fill"></i> Appel ${c.media === 'video' ? 'vidéo' : 'audio'} en cours...
            </div>
            <div class="ss-call-avatar">${avatar}</div>
            <div class="ss-call-name">${escapeHtml(fullName)}</div>
            <div class="ss-call-fonction">Sonnerie...</div>
            <div class="ss-call-actions">
                <button class="ss-call-btn ss-call-btn-reject" id="ssCallCancel" title="Annuler">
                    <i class="bi bi-telephone-x-fill"></i>
                </button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    document.getElementById('ssCallCancel').addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        console.log('[call-ui] Cancel dialing clicked');
        call.endCall();
    });
}

// ══════════════════════════════════════════════════════════════
// In-call modal
// ══════════════════════════════════════════════════════════════

function showInCallModal(c) {
    hideAllModals();
    const p = c.peer;
    const fullName = [p.prenom, p.nom].filter(Boolean).join(' ');
    const initials = (p.prenom?.[0] || '') + (p.nom?.[0] || '');
    const avatar = p.photo
        ? `<img src="${escapeHtml(p.photo)}" class="ss-call-avatar-img" alt="">`
        : `<div class="ss-call-avatar-initials">${escapeHtml(initials.toUpperCase())}</div>`;
    const isVideo = c.media === 'video';

    const overlay = document.createElement('div');
    overlay.id = 'ssCallActive';
    overlay.className = 'ss-call-overlay ss-call-overlay--active';
    overlay.innerHTML = `
        ${isVideo ? `
            <video id="ssCallRemoteVideo" class="ss-call-remote-video" autoplay playsinline></video>
            <video id="ssCallLocalVideo" class="ss-call-local-video" autoplay playsinline muted></video>
        ` : `
            <audio id="ssCallRemoteAudio" autoplay playsinline></audio>
        `}
        <div class="ss-call-active-top">
            <div class="ss-call-active-info">
                <div class="ss-call-avatar ss-call-avatar-sm">${avatar}</div>
                <div>
                    <div class="ss-call-active-name">${escapeHtml(fullName)}</div>
                    <div class="ss-call-active-duration" id="ssCallDuration">00:00</div>
                </div>
            </div>
        </div>
        <div class="ss-call-active-controls">
            <button class="ss-call-ctrl" id="ssCallMute" title="Muet">
                <i class="bi bi-mic-fill"></i>
            </button>
            ${isVideo ? `
            <button class="ss-call-ctrl" id="ssCallCam" title="Caméra">
                <i class="bi bi-camera-video-fill"></i>
            </button>
            ` : ''}
            <button class="ss-call-ctrl ss-call-ctrl-end" id="ssCallHangup" title="Raccrocher">
                <i class="bi bi-telephone-x-fill"></i>
            </button>
        </div>`;
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    // Attach local stream
    const localStream = call.getLocalStream();
    if (localStream && isVideo) {
        const lv = document.getElementById('ssCallLocalVideo');
        if (lv) lv.srcObject = localStream;
    }
    // Attach remote stream if already available
    const remoteStream = call.getRemoteStream();
    if (remoteStream) attachRemoteStream(remoteStream);

    // Controls
    document.getElementById('ssCallHangup').addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        console.log('[call-ui] Hangup clicked');
        call.endCall();
    });

    document.getElementById('ssCallMute').addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        const muted = call.toggleMute();
        const btn = e.currentTarget;
        btn.classList.toggle('active', muted);
        btn.querySelector('i').className = muted ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill';
    });

    document.getElementById('ssCallCam')?.addEventListener('pointerdown', (e) => {
        e.preventDefault(); e.stopPropagation();
        const off = call.toggleCamera();
        const btn = e.currentTarget;
        btn.classList.toggle('active', off);
        btn.querySelector('i').className = off ? 'bi bi-camera-video-off-fill' : 'bi bi-camera-video-fill';
    });
}

function attachRemoteStream(stream) {
    const v = document.getElementById('ssCallRemoteVideo');
    const a = document.getElementById('ssCallRemoteAudio');
    if (v) v.srcObject = stream;
    if (a) a.srcObject = stream;
}

function updateDuration(sec) {
    const el = document.getElementById('ssCallDuration');
    if (!el) return;
    const m = Math.floor(sec / 60).toString().padStart(2, '0');
    const s = (sec % 60).toString().padStart(2, '0');
    el.textContent = m + ':' + s;
}

// ══════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════

function handleRejected(status) {
    hideAllModals();
    const msg = {
        rejected: 'Appel refusé',
        ended: 'Appel terminé',
        missed: 'Pas de réponse',
    }[status] || 'Appel terminé';
    toast(msg, 3000);
}

function hideAllModals() {
    ['ssCallIncoming', 'ssCallDialing', 'ssCallActive'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }
    });
    _stopRingtone();
    _currentIncomingId = null;
}

// Ringtone (WebAudio — no file needed)
function _startRingtone() {
    _stopRingtone();
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 800;
        osc.connect(gain);
        gain.connect(ctx.destination);
        gain.gain.value = 0;
        osc.start();
        let beat = 0;
        const interval = setInterval(() => {
            const now = ctx.currentTime;
            gain.gain.cancelScheduledValues(now);
            // Brrrr-brrrr pattern
            if (beat % 6 < 2) gain.gain.setValueAtTime(0.15, now);
            else gain.gain.setValueAtTime(0, now);
            osc.frequency.value = (beat % 2) ? 800 : 1000;
            beat++;
        }, 180);
        _incomingRingtone = { ctx, osc, interval };
    } catch (e) { /* silent */ }
}

function _stopRingtone() {
    if (!_incomingRingtone) return;
    try {
        clearInterval(_incomingRingtone.interval);
        _incomingRingtone.osc.stop();
        _incomingRingtone.ctx.close();
    } catch {}
    _incomingRingtone = null;
}
