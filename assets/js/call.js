/**
 * SpocSpace — WebRTC call engine
 * 1-to-1 audio/video calls via MySQL signaling + STUN
 */
import { apiPost } from './helpers.js';

const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun.cloudflare.com:3478' },
];

const POLL_INTERVAL = 2000;
const ICE_POLL_INTERVAL = 1000;

let _pc = null;
let _localStream = null;
let _remoteStream = null;
let _currentCall = null; // { id, role: 'caller'|'callee', peer: {id, prenom, nom, photo}, media }
let _icePollTimer = null;
let _callPollTimer = null;
let _durationTimer = null;
let _ringAudio = null;
let _uiCallbacks = {};

// ══════════════════════════════════════════════════════════════
// Public API
// ══════════════════════════════════════════════════════════════

export function setUICallbacks(callbacks) {
    _uiCallbacks = callbacks || {};
}

export function getCurrentCall() {
    return _currentCall;
}

export function isInCall() {
    return !!_currentCall;
}

// ══════════════════════════════════════════════════════════════
// Outgoing call (A → B)
// ══════════════════════════════════════════════════════════════

export async function makeCall(toUser, media = 'audio') {
    console.log('[call] makeCall start', toUser.id, media);
    if (_currentCall) {
        throw new Error('Un appel est déjà en cours');
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('WebRTC non supporté par ce navigateur');
    }

    // Get local media
    try {
        _localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: media === 'video',
        });
        console.log('[call] Got local media', _localStream.getTracks().map(t => t.kind));
    } catch (e) {
        console.error('[call] getUserMedia failed:', e);
        throw new Error('Micro/caméra non accessible : ' + e.message);
    }

    _currentCall = {
        id: null,
        role: 'caller',
        peer: toUser,
        media,
        state: 'dialing',
        startedAt: Date.now(),
    };

    _pc = _createPeerConnection();
    _localStream.getTracks().forEach(t => _pc.addTrack(t, _localStream));

    // Create offer
    const offer = await _pc.createOffer();
    await _pc.setLocalDescription(offer);

    // Wait for ICE gathering (up to 2s)
    await _waitIceGathering(_pc, 2000);

    // Send invite
    console.log('[call] Sending invite to', toUser.id);
    const res = await apiPost('call_invite', {
        to_user_id: toUser.id,
        sdp_offer: JSON.stringify(_pc.localDescription),
        media,
    });
    console.log('[call] invite response:', res);

    if (!res.success) {
        _cleanup();
        throw new Error(res.message || 'Impossible d\'appeler');
    }

    _currentCall.id = res.call_id;
    _uiCallbacks.onDialing?.(_currentCall);
    _playRingback();

    // Start polling for accept/reject
    _startPollingOutgoing();
    _startIcePoll();

    return _currentCall;
}

// ══════════════════════════════════════════════════════════════
// Accept incoming call
// ══════════════════════════════════════════════════════════════

export async function acceptCall(incoming) {
    if (_currentCall) {
        throw new Error('Un appel est déjà en cours');
    }

    try {
        _localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: incoming.media === 'video',
        });
    } catch (e) {
        // Reject if we can't get media
        await apiPost('call_reject', { call_id: incoming.id });
        throw new Error('Micro/caméra non accessible : ' + e.message);
    }

    _currentCall = {
        id: incoming.id,
        role: 'callee',
        peer: {
            id: incoming.from_user_id,
            prenom: incoming.prenom,
            nom: incoming.nom,
            photo: incoming.photo,
            fonction_nom: incoming.fonction_nom,
        },
        media: incoming.media,
        state: 'connecting',
        startedAt: Date.now(),
    };

    _stopRing();
    _pc = _createPeerConnection();
    _localStream.getTracks().forEach(t => _pc.addTrack(t, _localStream));

    // Set remote offer
    const offer = JSON.parse(incoming.sdp_offer);
    await _pc.setRemoteDescription(new RTCSessionDescription(offer));

    // Create answer
    const answer = await _pc.createAnswer();
    await _pc.setLocalDescription(answer);
    await _waitIceGathering(_pc, 2000);

    // Send answer
    await apiPost('call_accept', {
        call_id: incoming.id,
        sdp_answer: JSON.stringify(_pc.localDescription),
    });

    _uiCallbacks.onAccepted?.(_currentCall);
    _startIcePoll();
    _startDurationTimer();

    return _currentCall;
}

// ══════════════════════════════════════════════════════════════
// Reject incoming
// ══════════════════════════════════════════════════════════════

export async function rejectCall(callId) {
    _stopRing();
    await apiPost('call_reject', { call_id: callId });
    _uiCallbacks.onRejected?.();
}

// ══════════════════════════════════════════════════════════════
// End current call
// ══════════════════════════════════════════════════════════════

export async function endCall() {
    if (_currentCall?.id) {
        await apiPost('call_end', { call_id: _currentCall.id }).catch(() => {});
    }
    _cleanup();
    _uiCallbacks.onEnded?.();
}

// ══════════════════════════════════════════════════════════════
// Controls
// ══════════════════════════════════════════════════════════════

export function toggleMute() {
    if (!_localStream) return false;
    const track = _localStream.getAudioTracks()[0];
    if (!track) return false;
    track.enabled = !track.enabled;
    return !track.enabled; // returns "muted" state
}

export function toggleCamera() {
    if (!_localStream) return false;
    const track = _localStream.getVideoTracks()[0];
    if (!track) return false;
    track.enabled = !track.enabled;
    return !track.enabled;
}

export function getLocalStream() { return _localStream; }
export function getRemoteStream() { return _remoteStream; }

// ══════════════════════════════════════════════════════════════
// PeerConnection setup
// ══════════════════════════════════════════════════════════════

function _createPeerConnection() {
    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });

    pc.onicecandidate = (e) => {
        if (e.candidate && _currentCall?.id) {
            apiPost('call_ice', {
                call_id: _currentCall.id,
                candidate: JSON.stringify(e.candidate),
            }).catch(() => {});
        }
    };

    pc.ontrack = (e) => {
        _remoteStream = e.streams[0];
        _uiCallbacks.onRemoteStream?.(_remoteStream);
    };

    pc.onconnectionstatechange = () => {
        if (!_pc) return;
        if (pc.connectionState === 'connected') {
            _uiCallbacks.onConnected?.();
        }
        if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
            if (_currentCall?.state !== 'ended') endCall();
        }
    };

    return pc;
}

function _waitIceGathering(pc, timeout = 2000) {
    return new Promise(resolve => {
        if (pc.iceGatheringState === 'complete') return resolve();
        const t = setTimeout(resolve, timeout);
        const check = () => {
            if (pc.iceGatheringState === 'complete') {
                clearTimeout(t);
                pc.removeEventListener('icegatheringstatechange', check);
                resolve();
            }
        };
        pc.addEventListener('icegatheringstatechange', check);
    });
}

// ══════════════════════════════════════════════════════════════
// ICE polling (trickle ICE via MySQL)
// ══════════════════════════════════════════════════════════════

function _startIcePoll() {
    _stopIcePoll();
    _icePollTimer = setInterval(async () => {
        if (!_currentCall?.id || !_pc) return;
        try {
            const res = await apiPost('call_ice_poll', { call_id: _currentCall.id });
            if (res.candidates?.length) {
                for (const c of res.candidates) {
                    try {
                        const cand = JSON.parse(c);
                        await _pc.addIceCandidate(new RTCIceCandidate(cand));
                    } catch (e) { /* ignore invalid */ }
                }
            }
        } catch (e) { /* silent */ }
    }, ICE_POLL_INTERVAL);
}

function _stopIcePoll() {
    if (_icePollTimer) { clearInterval(_icePollTimer); _icePollTimer = null; }
}

// ══════════════════════════════════════════════════════════════
// Outgoing call polling (wait for accept/reject)
// ══════════════════════════════════════════════════════════════

function _startPollingOutgoing() {
    _stopCallPoll();
    _callPollTimer = setInterval(async () => {
        if (!_currentCall?.id || _currentCall.role !== 'caller') return;
        try {
            const res = await apiPost('call_poll');
            const mine = res.outgoing?.find(c => c.id === _currentCall.id);
            if (!mine) return;

            if (mine.status === 'accepted' && mine.sdp_answer) {
                _stopCallPoll();
                _stopRing();
                const answer = JSON.parse(mine.sdp_answer);
                await _pc.setRemoteDescription(new RTCSessionDescription(answer));
                _currentCall.state = 'connected';
                _uiCallbacks.onAccepted?.(_currentCall);
                _startDurationTimer();
            } else if (['rejected', 'ended', 'missed'].includes(mine.status)) {
                _stopCallPoll();
                _stopRing();
                _cleanup();
                _uiCallbacks.onRejected?.(mine.status);
            }
        } catch (e) { /* silent */ }
    }, POLL_INTERVAL);
}

function _stopCallPoll() {
    if (_callPollTimer) { clearInterval(_callPollTimer); _callPollTimer = null; }
}

// ══════════════════════════════════════════════════════════════
// Duration timer
// ══════════════════════════════════════════════════════════════

function _startDurationTimer() {
    _stopDurationTimer();
    const start = Date.now();
    _durationTimer = setInterval(() => {
        const sec = Math.floor((Date.now() - start) / 1000);
        _uiCallbacks.onDuration?.(sec);
    }, 1000);
}

function _stopDurationTimer() {
    if (_durationTimer) { clearInterval(_durationTimer); _durationTimer = null; }
}

// ══════════════════════════════════════════════════════════════
// Ringback (for caller)
// ══════════════════════════════════════════════════════════════

function _playRingback() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = 440;
        osc.connect(gain);
        gain.connect(ctx.destination);
        gain.gain.value = 0;
        osc.start();
        let beat = 0;
        const interval = setInterval(() => {
            const now = ctx.currentTime;
            gain.gain.cancelScheduledValues(now);
            if (beat % 4 < 2) gain.gain.setValueAtTime(0.08, now);
            else gain.gain.setValueAtTime(0, now);
            beat++;
        }, 400);
        _ringAudio = { ctx, osc, interval };
    } catch (e) { /* silent */ }
}

function _stopRing() {
    if (!_ringAudio) return;
    try {
        clearInterval(_ringAudio.interval);
        _ringAudio.osc.stop();
        _ringAudio.ctx.close();
    } catch {}
    _ringAudio = null;
}

// ══════════════════════════════════════════════════════════════
// Cleanup
// ══════════════════════════════════════════════════════════════

function _cleanup() {
    _stopCallPoll();
    _stopIcePoll();
    _stopDurationTimer();
    _stopRing();
    if (_localStream) {
        _localStream.getTracks().forEach(t => t.stop());
        _localStream = null;
    }
    if (_pc) {
        try { _pc.close(); } catch {}
        _pc = null;
    }
    _remoteStream = null;
    _currentCall = null;
}
