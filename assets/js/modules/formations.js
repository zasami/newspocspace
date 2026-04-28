import { apiPost, toast } from '../helpers.js';

let data = { passees: [], a_venir: [], souhaits: [], catalogue: [] };
let modalCertif = null, modalSouhait = null;

export async function init() {
    // Init modals Bootstrap
    const certifEl = document.getElementById('certifModal');
    const souhaitEl = document.getElementById('souhaitModal');
    if (certifEl) modalCertif = new bootstrap.Modal(certifEl);
    if (souhaitEl) modalSouhait = new bootstrap.Modal(souhaitEl);

    // Load all lists
    await loadMesFormations();
    await loadCatalogue();

    // Tab handlers (refresh on switch)
    document.querySelectorAll('#formTabs .nav-link').forEach(t => {
        t.addEventListener('shown.bs.tab', e => {
            const target = e.target.dataset.bsTarget;
            if (target === '#tabSouhaits') renderSouhaits();
        });
    });

    // Upload certificat
    document.getElementById('certifUploadBtn')?.addEventListener('click', uploadCertif);
    // Souhait submit
    document.getElementById('souhaitSubmitBtn')?.addEventListener('click', submitSouhait);

    // Click delegate global
    document.addEventListener('click', handleClick);
}

export function destroy() {
    document.removeEventListener('click', handleClick);
}

// ─── Load API ────────────────────────────────────────────────────────────────

async function loadMesFormations() {
    try {
        const r = await apiPost('get_mes_formations');
        if (!r.success) {
            showError('formAVenirList', r.message || 'Erreur API');
            showError('formPasseesList', r.message || 'Erreur API');
            return;
        }
        data.passees = r.passees || [];
        data.a_venir = r.a_venir || [];
        data.souhaits = r.souhaits || [];
        renderAVenir();
        renderPassees();
    } catch (e) {
        console.error('loadMesFormations', e);
        showError('formAVenirList', 'Erreur réseau : ' + e.message);
        showError('formPasseesList', 'Erreur réseau : ' + e.message);
    }
}

async function loadCatalogue() {
    try {
        const r = await apiPost('get_catalogue_formations');
        if (!r.success) {
            showError('formCatalogueList', r.message || 'Erreur API');
            return;
        }
        data.catalogue = r.formations || [];
        data.secteur_user = r.secteur_user;
        renderCatalogue();
    } catch (e) {
        console.error('loadCatalogue', e);
        showError('formCatalogueList', 'Erreur réseau : ' + e.message);
    }
}

function showError(elId, msg) {
    const el = document.getElementById(elId);
    if (el) el.innerHTML = `<div class="fmt-empty"><i class="bi bi-exclamation-triangle text-danger"></i><p class="mb-0 text-danger">${escapeHtml(msg)}</p></div>`;
}

// ─── Renderers ───────────────────────────────────────────────────────────────

function renderAVenir() {
    const el = document.getElementById('formAVenirList');
    if (!el) return;
    if (!data.a_venir.length) {
        el.innerHTML = `<div class="fmt-empty"><i class="bi bi-calendar-x"></i>
            <p class="mb-0">Aucune formation planifiée pour vous.</p>
            <p class="small mt-2">Consultez le <a href="#" data-tab-switch="#tabCatalogue">catalogue</a> pour découvrir les formations proposées.</p></div>`;
        return;
    }
    el.innerHTML = data.a_venir.map(f => cardAVenir(f)).join('');
}

function renderPassees() {
    const el = document.getElementById('formPasseesList');
    if (!el) return;
    if (!data.passees.length) {
        el.innerHTML = `<div class="fmt-empty"><i class="bi bi-clipboard-x"></i>
            <p class="mb-0">Aucune formation passée pour le moment.</p></div>`;
        return;
    }
    el.innerHTML = data.passees.map(f => cardPassee(f)).join('');
}

function renderCatalogue() {
    const el = document.getElementById('formCatalogueList');
    if (!el) return;
    if (!data.catalogue.length) {
        el.innerHTML = `<div class="fmt-empty"><i class="bi bi-book"></i>
            <p class="mb-0">Aucune formation au catalogue pour le moment.</p></div>`;
        return;
    }
    el.innerHTML = data.catalogue.map(f => cardCatalogue(f)).join('');
}

function renderSouhaits() {
    const el = document.getElementById('formSouhaitsList');
    if (!el) return;
    if (!data.souhaits.length) {
        el.innerHTML = `<div class="fmt-empty"><i class="bi bi-star"></i>
            <p class="mb-0">Vous n'avez exprimé aucun souhait pour le moment.</p></div>`;
        return;
    }
    el.innerHTML = data.souhaits.map(s => cardSouhait(s)).join('');
}

// ─── Card templates ──────────────────────────────────────────────────────────

function cardAVenir(f) {
    const date = f.date_debut ? formatDateFR(f.date_debut) : '—';
    const thumb = f.image_url
        ? `<div class="fmt-thumb"><img src="${escapeHtml(f.image_url)}" alt=""></div>`
        : `<div class="fmt-thumb"><i class="bi bi-mortarboard"></i></div>`;
    return `
      <div class="fmt-card">
        <div class="fmt-card-row">
          ${thumb}
          <div class="fmt-info">
            <div class="fmt-title">${escapeHtml(f.titre)}</div>
            <div class="fmt-meta">
              <span><i class="bi bi-calendar3"></i> ${escapeHtml(date)}</span>
              ${f.lieu ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(f.lieu)}</span>` : ''}
              ${f.duree_heures ? `<span><i class="bi bi-clock"></i> ${f.duree_heures}h</span>` : ''}
              ${f.modalite ? `<span><i class="bi bi-tag"></i> ${escapeHtml(f.modalite)}</span>` : ''}
              <span><i class="bi bi-people"></i> ${f.nb_participants || 0} participant${(f.nb_participants > 1) ? 's' : ''}</span>
            </div>
          </div>
          <div class="fmt-actions">
            <button class="btn btn-sm btn-primary" data-formation-detail="${escapeHtml(f.id)}">
              <i class="bi bi-arrow-right"></i> Détails
            </button>
          </div>
        </div>
      </div>`;
}

function cardPassee(f) {
    const date = f.date_realisation || f.date_debut;
    const dateAffichee = date ? formatDateFR(date) : '—';
    const statutMap = {
        inscrit:  ['Inscrit',  'inscrit'],
        present:  ['Présent',  'present'],
        valide:   ['Validée',  'valide'],
        absent:   ['Absent',   'absent'],
    };
    const [statLbl, statCls] = statutMap[f.statut] || ['—', 'inscrit'];
    const thumb = f.image_url
        ? `<div class="fmt-thumb"><img src="${escapeHtml(f.image_url)}" alt=""></div>`
        : `<div class="fmt-thumb"><i class="bi bi-mortarboard"></i></div>`;

    const certifBtn = f.certificat_url
        ? `<button class="btn btn-sm fmt-certif-btn has-certif" data-view-certif="${escapeHtml(f.certificat_url)}" data-title="${escapeHtml(f.titre)}">
             <i class="bi bi-eye"></i> Voir certificat
           </button>
           <button class="btn btn-sm btn-outline-secondary fmt-certif-btn" data-upload-certif="${escapeHtml(f.participant_id)}" data-formation-titre="${escapeHtml(f.titre)}" title="Remplacer">
             <i class="bi bi-arrow-repeat"></i>
           </button>`
        : `<button class="btn btn-sm btn-outline-primary fmt-certif-btn" data-upload-certif="${escapeHtml(f.participant_id)}" data-formation-titre="${escapeHtml(f.titre)}">
             <i class="bi bi-cloud-upload"></i> Charger certificat
           </button>`;

    return `
      <div class="fmt-card">
        <div class="fmt-card-row">
          ${thumb}
          <div class="fmt-info">
            <div class="fmt-title">${escapeHtml(f.titre)}</div>
            <div class="fmt-meta">
              <span><i class="bi bi-calendar3"></i> ${escapeHtml(dateAffichee)}</span>
              ${f.lieu ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(f.lieu)}</span>` : ''}
              ${f.heures_realisees ? `<span><i class="bi bi-clock"></i> ${f.heures_realisees}h</span>` : (f.duree_heures ? `<span><i class="bi bi-clock"></i> ${f.duree_heures}h</span>` : '')}
              <span class="fmt-status-pill fmt-status-${statCls}">${escapeHtml(statLbl)}</span>
            </div>
          </div>
          <div class="fmt-actions">
            ${certifBtn}
          </div>
        </div>
      </div>`;
}

function cardCatalogue(f) {
    const date = f.date_debut ? formatDateFR(f.date_debut) : 'Date à confirmer';
    const thumb = f.image_url
        ? `<div class="fmt-thumb"><img src="${escapeHtml(f.image_url)}" alt=""></div>`
        : `<div class="fmt-thumb"><i class="bi bi-book"></i></div>`;
    const matchBadge = f.match_fonction
        ? `<span class="fmt-match-badge"><i class="bi bi-bullseye"></i> Pour vous</span>`
        : '';
    let actionBtn;
    if (f.souhait_id && f.souhait_statut === 'en_attente') {
        actionBtn = `<button class="btn btn-sm btn-warning" disabled><i class="bi bi-hourglass"></i> Souhait en attente</button>`;
    } else if (f.souhait_id && f.souhait_statut === 'accepte') {
        actionBtn = `<span class="fmt-status-pill fmt-status-valide"><i class="bi bi-check-circle"></i> Accepté</span>`;
    } else if (f.souhait_id && f.souhait_statut === 'refuse') {
        actionBtn = `<span class="fmt-status-pill fmt-status-absent"><i class="bi bi-x-circle"></i> Refusé</span>`;
    } else {
        actionBtn = `<button class="btn btn-sm btn-outline-primary" data-souhait-formation="${escapeHtml(f.id)}" data-formation-titre="${escapeHtml(f.titre)}">
                       <i class="bi bi-star"></i> Souhaite participer
                     </button>`;
    }
    return `
      <div class="fmt-card">
        <div class="fmt-card-row">
          ${thumb}
          <div class="fmt-info">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <div class="fmt-title mb-0">${escapeHtml(f.titre)}</div>
              ${matchBadge}
            </div>
            <div class="fmt-meta">
              <span><i class="bi bi-calendar3"></i> ${escapeHtml(date)}</span>
              ${f.lieu ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(f.lieu)}</span>` : ''}
              ${f.duree_heures ? `<span><i class="bi bi-clock"></i> ${f.duree_heures}h</span>` : ''}
              ${f.modalite ? `<span><i class="bi bi-tag"></i> ${escapeHtml(f.modalite)}</span>` : ''}
              ${f.nb_participants !== undefined && f.max_participants ? `<span><i class="bi bi-people"></i> ${f.nb_participants}/${f.max_participants}</span>` : ''}
            </div>
            ${f.description ? `<div class="text-muted small mt-2">${escapeHtml(f.description.slice(0, 200))}${f.description.length > 200 ? '…' : ''}</div>` : ''}
          </div>
          <div class="fmt-actions">
            ${actionBtn}
            <button class="btn btn-sm btn-outline-secondary" data-formation-detail="${escapeHtml(f.id)}">
              <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>`;
}

function cardSouhait(s) {
    const date = s.date_debut ? formatDateFR(s.date_debut) : '—';
    const statutMap = {
        en_attente: ['En attente RH', 'inscrit'],
        accepte:    ['Accepté',       'valide'],
        refuse:     ['Refusé',        'absent'],
        annule:     ['Annulé',        'absent'],
    };
    const [lbl, cls] = statutMap[s.souhait_statut] || ['—', 'inscrit'];
    const thumb = s.image_url
        ? `<div class="fmt-thumb"><img src="${escapeHtml(s.image_url)}" alt=""></div>`
        : `<div class="fmt-thumb"><i class="bi bi-star"></i></div>`;
    return `
      <div class="fmt-card">
        <div class="fmt-card-row">
          ${thumb}
          <div class="fmt-info">
            <div class="fmt-title">${escapeHtml(s.titre)}</div>
            <div class="fmt-meta">
              <span><i class="bi bi-calendar3"></i> ${escapeHtml(date)}</span>
              ${s.lieu ? `<span><i class="bi bi-geo-alt"></i> ${escapeHtml(s.lieu)}</span>` : ''}
              <span class="fmt-status-pill fmt-status-${cls}">${escapeHtml(lbl)}</span>
            </div>
            ${s.message ? `<div class="text-muted small mt-2"><i class="bi bi-quote"></i> ${escapeHtml(s.message)}</div>` : ''}
          </div>
          <div class="fmt-actions">
            <button class="btn btn-sm btn-outline-secondary" data-formation-detail="${escapeHtml(s.formation_id)}">
              <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>`;
}

// ─── Click handler ───────────────────────────────────────────────────────────

function handleClick(e) {
    // Switch tab via lien dans empty-state
    const tabSwitch = e.target.closest('[data-tab-switch]');
    if (tabSwitch) {
        e.preventDefault();
        const target = tabSwitch.dataset.tabSwitch;
        const btn = document.querySelector(`#formTabs [data-bs-target="${target}"]`);
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        return;
    }

    // Détails formation
    const detailBtn = e.target.closest('[data-formation-detail]');
    if (detailBtn) {
        const id = detailBtn.dataset.formationDetail;
        location.href = `/spocspace/formation-detail-emp?id=${encodeURIComponent(id)}`;
        return;
    }

    // Voir certificat → lightbox
    const viewCertif = e.target.closest('[data-view-certif]');
    if (viewCertif) {
        openCertifLightbox(viewCertif.dataset.viewCertif, viewCertif.dataset.title);
        return;
    }

    // Upload certificat → ouvre modal
    const upBtn = e.target.closest('[data-upload-certif]');
    if (upBtn) {
        document.getElementById('certifParticipantId').value = upBtn.dataset.uploadCertif;
        document.getElementById('certifFormationTitle').textContent = upBtn.dataset.formationTitre || '';
        document.getElementById('certifFile').value = '';
        modalCertif?.show();
        return;
    }

    // Souhait participation → ouvre modal
    const souBtn = e.target.closest('[data-souhait-formation]');
    if (souBtn) {
        document.getElementById('souhaitFormationId').value = souBtn.dataset.souhaitFormation;
        document.getElementById('souhaitFormationTitle').textContent = souBtn.dataset.formationTitre || '';
        document.getElementById('souhaitMessage').value = '';
        modalSouhait?.show();
        return;
    }

    // Lightbox close (clic fond ou bouton X)
    if (e.target.classList.contains('ss-lightbox') || e.target.closest('.ss-lightbox-close')) {
        document.querySelector('.ss-lightbox')?.remove();
    }
}

// ─── Upload certificat ───────────────────────────────────────────────────────

async function uploadCertif() {
    const fileInput = document.getElementById('certifFile');
    const partId = document.getElementById('certifParticipantId').value;
    if (!fileInput.files[0]) { toast('Sélectionnez un fichier', 'danger'); return; }

    const fd = new FormData();
    fd.append('action', 'upload_formation_certificat');
    fd.append('participant_id', partId);
    fd.append('certificat', fileInput.files[0]);

    const btn = document.getElementById('certifUploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Téléversement…';

    try {
        const csrf = window.__SS__?.csrfToken || '';
        const res = await fetch('/spocspace/api.php', {
            method: 'POST',
            headers: csrf ? { 'X-CSRF-Token': csrf } : {},
            body: fd,
        });
        const r = await res.json();
        if (!r.success) throw new Error(r.message || 'Erreur');
        toast('Certificat téléversé', 'success');
        modalCertif?.hide();
        await loadMesFormations();
    } catch (err) {
        toast('Erreur : ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-upload"></i> Téléverser';
    }
}

// ─── Souhait participation ──────────────────────────────────────────────────

async function submitSouhait() {
    const formId = document.getElementById('souhaitFormationId').value;
    const message = document.getElementById('souhaitMessage').value;

    const btn = document.getElementById('souhaitSubmitBtn');
    btn.disabled = true;

    try {
        const r = await apiPost('submit_souhait_formation', { formation_id: formId, message });
        if (!r.success) throw new Error(r.message || 'Erreur');
        toast(r.message || 'Souhait envoyé', 'success');
        modalSouhait?.hide();
        await loadMesFormations();
        await loadCatalogue();
    } catch (err) {
        toast('Erreur : ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
    }
}

// ─── Lightbox certificat (image OU PDF iframe) ──────────────────────────────

function openCertifLightbox(url, title) {
    const lb = document.createElement('div');
    lb.className = 'ss-lightbox';
    const isImage = /\.(jpe?g|png|gif|webp)$/i.test(url);
    const isPdf   = /\.pdf$/i.test(url);

    let body;
    if (isImage) {
        body = `<img src="${escapeHtml(url)}" alt="${escapeHtml(title || '')}"
                     style="max-width:100%;max-height:80vh;display:block;margin:0 auto;border-radius:6px">`;
    } else if (isPdf) {
        body = `<iframe src="${escapeHtml(url)}#toolbar=1&navpanes=0"
                        style="width:100%;height:80vh;border:0;background:#fff;border-radius:6px"></iframe>`;
    } else {
        // DOC/DOCX → lien direct
        body = `<div style="text-align:center;padding:60px 20px;background:#fff;border-radius:6px">
                  <i class="bi bi-file-earmark-word" style="font-size:3rem;color:#2d4a43"></i>
                  <p class="mt-3 mb-2">Fichier Word — non visualisable directement.</p>
                  <a href="${escapeHtml(url)}" target="_blank" class="btn btn-primary">
                    <i class="bi bi-download"></i> Télécharger le certificat
                  </a>
                </div>`;
    }

    lb.innerHTML = `
      <div class="ss-lightbox-wrap">
        <div class="ss-lightbox-header">
          <span class="ss-lightbox-title"><i class="bi bi-patch-check"></i> ${escapeHtml(title || 'Certificat')}</span>
          <button class="ss-lightbox-close" title="Fermer (Échap)"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ss-lightbox-content">${body}</div>
        <div class="ss-lightbox-footer">
          <a href="${escapeHtml(url)}" target="_blank" class="ss-lightbox-btn"><i class="bi bi-box-arrow-up-right"></i> Ouvrir</a>
          <a href="${escapeHtml(url)}" download class="ss-lightbox-btn"><i class="bi bi-download"></i> Télécharger</a>
        </div>
      </div>`;
    document.body.appendChild(lb);

    // Échap pour fermer
    const escHandler = e => {
        if (e.key === 'Escape') {
            lb.remove();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    const t = document.createElement('span');
    t.textContent = String(s);
    return t.innerHTML;
}

function formatDateFR(d) {
    if (!d) return '';
    try {
        const dt = new Date(d);
        return dt.toLocaleDateString('fr-CH', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch { return d; }
}
