/**
 * famille-crypto.js — AES-256-GCM E2EE for Espace Famille
 *
 * Flow:
 * 1. Admin creates a random AES-256 key for a resident
 * 2. That key is wrapped (encrypted) using PBKDF2(code_acces) and stored server-side
 * 3. Famille user logs in with code_acces → derives wrapping key → unwraps the AES key
 * 4. All files are encrypted/decrypted client-side with that AES key
 */
const FamilleCrypto = (function() {
    'use strict';

    const PBKDF2_ITERATIONS = 100000;
    const AES_KEY_BITS = 256;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function ab2b64(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer)));
    }

    function b642ab(b64) {
        const bin = atob(b64);
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }

    function ab2hex(buffer) {
        return [...new Uint8Array(buffer)].map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function hex2ab(hex) {
        const bytes = new Uint8Array(hex.length / 2);
        for (let i = 0; i < bytes.length; i++) bytes[i] = parseInt(hex.substr(i * 2, 2), 16);
        return bytes.buffer;
    }

    // ── Key derivation from password (code_acces) ───────────────────────────

    async function deriveWrappingKey(password, salt) {
        const enc = new TextEncoder();
        const keyMaterial = await crypto.subtle.importKey(
            'raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
            keyMaterial,
            { name: 'AES-GCM', length: AES_KEY_BITS },
            false,
            ['encrypt', 'decrypt']
        );
    }

    // ── Generate a new random AES-256 key for a resident ────────────────────

    async function generateResidentKey() {
        return crypto.subtle.generateKey(
            { name: 'AES-GCM', length: AES_KEY_BITS },
            true, // extractable so we can wrap it
            ['encrypt', 'decrypt']
        );
    }

    // ── Wrap (encrypt) the resident key with the password-derived key ───────

    async function wrapKey(residentKey, password) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const wrappingKey = await deriveWrappingKey(password, salt);

        // Export raw key then encrypt it
        const rawKey = await crypto.subtle.exportKey('raw', residentKey);
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            wrappingKey,
            rawKey
        );

        return {
            encrypted_key: ab2b64(encrypted),
            salt: ab2hex(salt),
            iv: ab2hex(iv)
        };
    }

    // ── Unwrap (decrypt) the resident key using the password ────────────────

    async function unwrapKey(encryptedKeyB64, saltHex, ivHex, password) {
        const salt = hex2ab(saltHex);
        const iv = hex2ab(ivHex);
        const wrappingKey = await deriveWrappingKey(password, salt);

        const encryptedKey = b642ab(encryptedKeyB64);
        const rawKey = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: new Uint8Array(iv) },
            wrappingKey,
            encryptedKey
        );

        return crypto.subtle.importKey(
            'raw', rawKey, { name: 'AES-GCM', length: AES_KEY_BITS },
            false, ['encrypt', 'decrypt']
        );
    }

    // ── Encrypt a file (ArrayBuffer → encrypted ArrayBuffer + iv) ───────────

    async function encryptFile(aesKey, fileBuffer) {
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            aesKey,
            fileBuffer
        );
        return { data: encrypted, iv: ab2hex(iv) };
    }

    // ── Decrypt a file (encrypted ArrayBuffer + iv → original ArrayBuffer) ──

    async function decryptFile(aesKey, encryptedBuffer, ivHex) {
        const iv = hex2ab(ivHex);
        return crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: new Uint8Array(iv) },
            aesKey,
            encryptedBuffer
        );
    }

    // ── Encrypt text (string → base64 ciphertext + iv) ─────────────────────

    async function encryptText(aesKey, text) {
        const enc = new TextEncoder();
        const result = await encryptFile(aesKey, enc.encode(text));
        return { ciphertext: ab2b64(result.data), iv: result.iv };
    }

    // ── Decrypt text (base64 ciphertext + iv → string) ─────────────────────

    async function decryptText(aesKey, ciphertextB64, ivHex) {
        const decrypted = await decryptFile(aesKey, b642ab(ciphertextB64), ivHex);
        return new TextDecoder().decode(decrypted);
    }

    // ── Create object URL from decrypted buffer ────────────────────────────

    function createBlobUrl(buffer, mimeType) {
        const blob = new Blob([buffer], { type: mimeType || 'application/octet-stream' });
        return URL.createObjectURL(blob);
    }

    // ── Guess MIME type from file name ─────────────────────────────────────

    function guessMime(fileName) {
        const ext = (fileName || '').split('.').pop().toLowerCase();
        const map = {
            jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif',
            webp: 'image/webp', svg: 'image/svg+xml',
            pdf: 'application/pdf',
            doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            txt: 'text/plain', csv: 'text/csv'
        };
        return map[ext] || 'application/octet-stream';
    }

    // ── Public API ─────────────────────────────────────────────────────────

    return {
        generateResidentKey,
        wrapKey,
        unwrapKey,
        encryptFile,
        decryptFile,
        encryptText,
        decryptText,
        createBlobUrl,
        guessMime,
        ab2b64,
        b642ab
    };
})();

if (typeof window !== 'undefined') window.FamilleCrypto = FamilleCrypto;
