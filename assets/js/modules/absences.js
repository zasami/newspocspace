/**
 * Absences module
 */
import { apiPost, toast, escapeHtml, formatDate, statusBadge, absenceTypeBadge } from '../helpers.js';

let _lbListeners = [];
function _lbAdd(el, evt, fn, opts) { if (!el) return; el.addEventListener(evt, fn, opts); _lbListeners.push({ el, evt, fn, opts }); }
function _lbRemoveAll() { _lbListeners.forEach(l => l.el.removeEventListener(l.evt, l.fn, l.opts)); _lbListeners = []; }

let selectedFile = null;

export function init() {
    initDropZone();

    document.getElementById('absenceForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = document.getElementById('absenceType').value;
        const dateDebut = document.getElementById('absenceDateDebut').value;
        const dateFin = document.getElementById('absenceDateFin').value;
        const commentaire = document.getElementById('absenceCommentaire')?.value || '';

        if (!type || !dateDebut || !dateFin) return;

        const res = await apiPost('submit_absence', {
            type, date_debut: dateDebut, date_fin: dateFin, commentaire
        });

        if (res.success) {
            if (selectedFile && res.id) {
                await uploadJustificatif(res.id, selectedFile);
            }
            toast('Demande soumise');
            document.getElementById('absenceForm').reset();
            clearDropZone();
            await loadAbsences();
        } else {
            toast(res.message || 'Erreur');
        }
    });

    // Initial render from SSR data
    const ssrAbsences = window.__ZT_PAGE_DATA__?.absences || [];
    renderAbsences(ssrAbsences);
}

function initDropZone() {
    const dropZone = document.getElementById('absenceDropZone');
    const fileInput = document.getElementById('absenceJustificatif');
    if (!dropZone || !fileInput) return;

    // Click to browse
    dropZone.addEventListener('click', (e) => {
        if (e.target.closest('.abs-file-remove')) return;
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) setDropZoneFile(fileInput.files[0]);
    });

    // Drag & drop
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('abs-dropzone-drag'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('abs-dropzone-drag'); });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('abs-dropzone-drag');
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!allowed.includes(file.type)) { toast('Format non autorisé (JPG, PNG, PDF)'); return; }
        if (file.size > 10 * 1024 * 1024) { toast('Fichier trop volumineux (max 10 Mo)'); return; }
        setDropZoneFile(file);
    });
}

function setDropZoneFile(file) {
    selectedFile = file;
    const content = document.getElementById('absDropContent');
    if (!content) return;
    const ext = file.name.split('.').pop().toLowerCase();
    const icon = ext === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image';
    const size = file.size < 1024 * 1024
        ? (file.size / 1024).toFixed(0) + ' Ko'
        : (file.size / (1024 * 1024)).toFixed(1) + ' Mo';
    content.innerHTML = `<div class="abs-dropzone-file">
        <i class="bi ${icon}"></i>
        <div class="abs-file-info">
            <div class="abs-file-name">${escapeHtml(file.name)}</div>
            <div class="abs-file-size">${size}</div>
        </div>
        <button type="button" class="abs-file-remove" title="Supprimer"><i class="bi bi-x-lg"></i></button>
    </div>`;
    content.querySelector('.abs-file-remove').addEventListener('click', (e) => {
        e.stopPropagation();
        clearDropZone();
    });
}

function clearDropZone() {
    selectedFile = null;
    const fileInput = document.getElementById('absenceJustificatif');
    if (fileInput) fileInput.value = '';
    const content = document.getElementById('absDropContent');
    if (content) {
        content.innerHTML = `<i class="bi bi-cloud-arrow-up"></i>
            <span>Glissez ou cliquez ici pour uploader votre justificatif</span>
            <small>Certificat médical, attestation... (JPG, PNG, PDF, max 10 Mo)</small>`;
    }
}

async function uploadJustificatif(absenceId, file) {
    const fd = new FormData();
    fd.append('action', 'upload_absence_justificatif');
    fd.append('absence_id', absenceId);
    fd.append('file', file);

    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const headers = {};
        if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;

        const resp = await fetch('/zerdatime/api.php', {
            method: 'POST',
            headers,
            body: fd
        });
        const res = await resp.json();
        if (!res.success) {
            toast(res.message || 'Erreur upload justificatif');
        }
    } catch (err) {
        console.error('Upload justificatif error:', err);
    }
}

async function loadAbsences() {
    const res = await apiPost('get_mes_absences');
    renderAbsences(res.absences || []);
}

function renderAbsences(absences) {
    const tbody = document.getElementById('absencesTableBody');
    if (!tbody) return;

    if (!absences.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Aucune absence enregistrée</td></tr>';
        return;
    }

    tbody.innerHTML = absences.map(a => {
        const path = a.justificatif_path || '';
        const name = a.justificatif_name || 'Justificatif';
        const ext = path.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext);
        const isPdf = ext === 'pdf';
        const fileType = isImage ? 'image' : (isPdf ? 'pdf' : 'other');

        const justifCell = path
            ? `<a href="#" class="justif-link" data-url="${escapeHtml(path)}" data-name="${escapeHtml(name)}" data-type="${fileType}" title="${escapeHtml(name)}"><i class="bi bi-check-lg" style="color:#2d4a43;"></i></a>`
            : (a.justifie ? '<i class="bi bi-check-lg" style="color:#2d4a43;"></i>' : '<i class="bi bi-x-lg" style="color:#7B3B2C;"></i>');

        return `<tr>
        <td>${absenceTypeBadge(a.type)}</td>
        <td>${escapeHtml(formatDate(a.date_debut))}</td>
        <td>${escapeHtml(formatDate(a.date_fin))}</td>
        <td>${statusBadge(a.statut)}</td>
        <td>${justifCell}</td>
        <td>${a.remplacement_prenom ? escapeHtml(a.remplacement_prenom + ' ' + a.remplacement_nom) : '<span class="text-muted">—</span>'}</td>
      </tr>`;
    }).join('');

    // Attach lightbox click handlers
    tbody.querySelectorAll('.justif-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openFileViewer(link.dataset.url, link.dataset.name, link.dataset.type);
        });
    });
}

function openFileViewer(url, name, type) {
    const lb = document.getElementById('ztLightbox');
    const stage = document.getElementById('ztLbStage');
    const titleEl = document.getElementById('ztLbTitle');
    const toolbar = document.getElementById('ztLbToolbar');
    if (!lb || !stage) return;

    _lbRemoveAll();
    titleEl.textContent = name || 'Fichier';

    let scale = 1, tx = 0, ty = 0, dragging = false, lastX = 0, lastY = 0;
    let imgEl = null;

    if (type === 'image') {
        stage.innerHTML = `<img src="${url}" alt="${escapeHtml(name)}" draggable="false">`;
        imgEl = stage.querySelector('img');
        toolbar.style.display = '';
    } else if (type === 'pdf') {
        stage.innerHTML = `<iframe src="${url}#toolbar=1"></iframe>`;
        toolbar.style.display = 'none';
    } else {
        stage.innerHTML = `<div style="text-align:center;color:#fff;"><i class="bi bi-file-earmark" style="font-size:5rem;"></i><br><a href="${url}" target="_blank" style="color:#fff;text-decoration:underline;">Télécharger le fichier</a></div>`;
        toolbar.style.display = 'none';
    }

    function apply() {
        if (!imgEl) return;
        requestAnimationFrame(() => {
            imgEl.style.transform = `translate(${tx}px, ${ty}px) scale(${scale})`;
            document.getElementById('ztLbZoomLevel').textContent = Math.round(scale * 100) + '%';
            stage.classList.toggle('zt-zoomed', scale > 1.01);
        });
    }
    function resetZoom() { scale = 1; tx = 0; ty = 0; apply(); }
    function zoomAt(px, py, ns) {
        ns = Math.max(0.5, Math.min(10, ns));
        const rect = stage.getBoundingClientRect();
        const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
        const ox = px - cx, oy = py - cy;
        tx = ox - (ox - tx) * (ns / scale);
        ty = oy - (oy - ty) * (ns / scale);
        scale = ns;
        if (scale <= 1) { tx = 0; ty = 0; scale = 1; }
        apply();
    }
    function zoomBy(d) { const r = stage.getBoundingClientRect(); zoomAt(r.left + r.width / 2, r.top + r.height / 2, scale + d); }

    function closeLb() {
        lb.classList.add('zt-lightbox-hidden');
        document.body.style.overflow = '';
        stage.classList.remove('zt-zoomed', 'zt-dragging');
        _lbRemoveAll();
    }

    lb.classList.remove('zt-lightbox-hidden');
    document.body.style.overflow = 'hidden';

    _lbAdd(lb.querySelector('.zt-lightbox-close'), 'click', closeLb);
    _lbAdd(lb.querySelector('.zt-lightbox-overlay'), 'click', closeLb);
    _lbAdd(document.getElementById('ztLbZoomIn'), 'click', () => zoomBy(.25));
    _lbAdd(document.getElementById('ztLbZoomOut'), 'click', () => zoomBy(-.25));
    _lbAdd(document.getElementById('ztLbReset'), 'click', resetZoom);

    _lbAdd(document, 'keydown', (e) => {
        if (lb.classList.contains('zt-lightbox-hidden')) return;
        if (e.key === 'Escape') closeLb();
        else if (e.key === '+' || e.key === '=') zoomBy(.25);
        else if (e.key === '-') zoomBy(-.25);
        else if (e.key === '0') resetZoom();
    });

    if (imgEl) {
        _lbAdd(stage, 'wheel', (e) => { e.preventDefault(); zoomAt(e.clientX, e.clientY, scale * (1 + (e.deltaY > 0 ? -.15 : .15))); }, { passive: false });
        _lbAdd(stage, 'mousedown', (e) => { if (e.button !== 0 || scale <= 1.01) return; dragging = true; lastX = e.clientX; lastY = e.clientY; stage.classList.add('zt-dragging'); e.preventDefault(); });
        _lbAdd(document, 'mousemove', (e) => { if (!dragging) return; tx += e.clientX - lastX; ty += e.clientY - lastY; lastX = e.clientX; lastY = e.clientY; apply(); });
        _lbAdd(document, 'mouseup', () => { if (dragging) { dragging = false; stage.classList.remove('zt-dragging'); } });
        _lbAdd(stage, 'dblclick', (e) => { if (scale > 1.01) resetZoom(); else zoomAt(e.clientX, e.clientY, 2.5); });
    }
}

export function destroy() {
    _lbRemoveAll();
    selectedFile = null;
    const lb = document.getElementById('ztLightbox');
    if (lb) lb.classList.add('zt-lightbox-hidden');
    document.body.style.overflow = '';
}
