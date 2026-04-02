<?php
require_once __DIR__ . '/../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'zerdaTime';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Famille — <?= h($emsNom) ?></title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/zerdatime/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/zerdatime/website/assets/css/famille.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
</head>
<body>
<div class="fam-shell">
<div class="fam-container">

  <a href="/zerdatime/website/" class="fam-back"><i class="bi bi-arrow-left"></i> Retour au site</a>

  <!-- ═══ LOGIN ═══ -->
  <div id="famLogin" class="fam-login-wrap">
    <div class="fam-login-card">
      <div style="text-align:center;margin-bottom:20px"><i class="bi bi-house-heart" style="font-size:2.5rem;color:var(--fam-green)"></i></div>
      <h1 class="fam-login-title">Espace Famille</h1>
      <p class="fam-login-desc">Connectez-vous pour accéder aux activités, au suivi médical et aux photos de votre proche.</p>
      <div class="fam-form-group">
        <label>Email du correspondant</label>
        <input type="email" class="fam-input" id="famEmail" placeholder="votre.email@exemple.com">
      </div>
      <div class="fam-form-group">
        <label>Code d'accès <small style="color:var(--fam-text-muted)">(date de naissance du résident : JJMMAAAA)</small></label>
        <input type="password" class="fam-input" id="famPassword" placeholder="Ex: 12031935">
      </div>
      <button class="fam-btn fam-btn-primary" id="famLoginBtn"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
      <div class="fam-error" id="famLoginError"></div>

      <!-- DEMO: comptes test -->
      <div style="margin-top:20px">
        <button type="button" class="fam-demo-toggle" id="famDemoToggle">
          <i class="bi bi-info-circle"></i> Comptes de démonstration
        </button>
        <div id="famDemoList" style="display:none;margin-top:10px">
          <div class="fam-demo-wrap"><table class="fam-demo-table">
            <thead><tr><th>Résident</th><th>Ch.</th><th>Email correspondant</th><th>Code</th><th></th></tr></thead>
            <tbody>
              <tr><td>Marguerite Dupont</td><td>101</td><td>jp.dupont@gmail.com</td><td><code>12031935</code></td>
                <td><button class="fam-demo-use" data-email="jp.dupont@gmail.com" data-pwd="12031935">Utiliser</button></td></tr>
              <tr><td>Jeanne Favre</td><td>102</td><td>michel.favre@bluewin.ch</td><td><code>22071938</code></td>
                <td><button class="fam-demo-use" data-email="michel.favre@bluewin.ch" data-pwd="22071938">Utiliser</button></td></tr>
              <tr><td>André Rochat</td><td>103</td><td>sophie.rochat@gmail.com</td><td><code>05111932</code></td>
                <td><button class="fam-demo-use" data-email="sophie.rochat@gmail.com" data-pwd="05111932">Utiliser</button></td></tr>
              <tr><td>Hélène Muller</td><td>104</td><td>thomas.muller@yahoo.fr</td><td><code>18011940</code></td>
                <td><button class="fam-demo-use" data-email="thomas.muller@yahoo.fr" data-pwd="18011940">Utiliser</button></td></tr>
              <tr><td>Robert Blanc</td><td>105</td><td>catherine.blanc@gmail.com</td><td><code>30091936</code></td>
                <td><button class="fam-demo-use" data-email="catherine.blanc@gmail.com" data-pwd="30091936">Utiliser</button></td></tr>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ DASHBOARD (hidden until login) ═══ -->
  <div id="famDashboard" style="display:none" class="fam-app">

    <!-- Sidebar fixe -->
    <aside class="fam-sidebar" id="famSidebar">
      <!-- Profil résident -->
      <div class="fam-sb-profile">
        <div class="fam-sb-avatar" id="famSbAvatar"></div>
        <div class="fam-sb-name" id="famSbName"></div>
        <div class="fam-sb-room" id="famSbRoom"></div>
      </div>

      <!-- Navigation grands carrés -->
      <nav class="fam-sb-nav">
        <button class="fam-sb-card active" data-pane="dashboard">
          <i class="bi bi-house"></i>
          <span>Accueil</span>
        </button>
        <button class="fam-sb-card" data-pane="activites">
          <i class="bi bi-calendar-event"></i>
          <span>Activités</span>
          <span class="fam-sb-count" id="famSbAct">0</span>
        </button>
        <button class="fam-sb-card" data-pane="medical">
          <i class="bi bi-heart-pulse"></i>
          <span>Suivi médical</span>
          <span class="fam-sb-count" id="famSbMed">0</span>
        </button>
        <button class="fam-sb-card" data-pane="galerie">
          <i class="bi bi-images"></i>
          <span>Galerie</span>
          <span class="fam-sb-count" id="famSbAlb">0</span>
        </button>
      </nav>

      <!-- Logout en bas -->
      <div class="fam-sb-footer">
        <button class="fam-sb-logout" id="famLogoutBtn"><i class="bi bi-box-arrow-left"></i> Déconnexion</button>
      </div>
    </aside>

    <!-- Contenu principal -->
    <main class="fam-main">
      <!-- Titre de page -->
      <div class="fam-page-header">
        <button class="fam-mobile-menu" id="famMobileMenu"><i class="bi bi-list"></i></button>
        <h4 class="fam-page-title" id="famPageTitle">Accueil</h4>
      </div>

      <!-- Dashboard -->
      <div class="fam-pane active" id="paneDashboard">
        <div class="fam-stats">
          <div class="fam-stat-card"><div class="fam-stat-num" id="famStatAct">0</div><div class="fam-stat-label">Activités</div></div>
          <div class="fam-stat-card"><div class="fam-stat-num" id="famStatMed">0</div><div class="fam-stat-label">Avis médicaux</div></div>
          <div class="fam-stat-card"><div class="fam-stat-num" id="famStatAlb">0</div><div class="fam-stat-label">Albums</div></div>
          <div class="fam-stat-card"><div class="fam-stat-num" id="famStatPho">0</div><div class="fam-stat-label">Photos</div></div>
        </div>
        <div class="fam-dash-sections">
          <div class="fam-dash-section">
            <h6><i class="bi bi-calendar-event"></i> Dernières activités</h6>
            <div id="famDashAct"></div>
          </div>
          <div class="fam-dash-section">
            <h6><i class="bi bi-heart-pulse"></i> Derniers avis médicaux</h6>
            <div id="famDashMed"></div>
          </div>
        </div>
      </div>

      <!-- Activités -->
      <div class="fam-pane" id="paneActivites">
        <div id="famActList" class="fam-act-list"></div>
        <div id="famActDetail" style="display:none"></div>
      </div>

      <!-- Médical -->
      <div class="fam-pane" id="paneMedical">
        <div id="famMedList" class="fam-med-list"></div>
      </div>

      <!-- Galerie -->
      <div class="fam-pane" id="paneGalerie">
        <div id="famGalFolders"></div>
        <div id="famGalAlbum" style="display:none"></div>
      </div>

    </main>

    <!-- Overlay mobile -->
    <div class="fam-sb-overlay" id="famSbOverlay"></div>

  </div>

</div>
</div>

<!-- Lightbox -->
<div id="famLightbox" class="fam-lb fam-lb-hidden">
  <div class="fam-lb-overlay"></div>
  <div class="fam-lb-content">
    <button class="fam-lb-close"><i class="bi bi-x-lg"></i></button>
    <button class="fam-lb-nav fam-lb-prev"><i class="bi bi-chevron-left"></i></button>
    <button class="fam-lb-nav fam-lb-next"><i class="bi bi-chevron-right"></i></button>
    <div class="fam-lb-counter" id="famLbCounter"></div>
    <a class="fam-lb-download" id="famLbDownload" download title="Télécharger"><i class="bi bi-download"></i></a>
    <div id="famLbStage"></div>
    <div class="fam-lb-title" id="famLbTitle"></div>
  </div>
</div>

<script src="/zerdatime/website/assets/js/famille-crypto.js"></script>
<script>
(function() {
'use strict';

const API = '/zerdatime/website/api.php';
let token = localStorage.getItem('fam_token') || '';
let resident = null;
let aesKey = null; // decrypted AES key
let password = ''; // kept in memory for key derivation

// ── API helper ─────────────────────────────────────────────────────────────

async function api(action, data) {
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['X-Famille-Token'] = token;
    const res = await fetch(API, {
        method: 'POST',
        headers,
        body: JSON.stringify({ action, ...data })
    });
    const json = await res.json();
    if (res.status === 401) { logout(); return json; }
    return json;
}

async function fetchFile(fileId, type) {
    const url = API + '?action=famille_get_file&file_id=' + encodeURIComponent(fileId) + '&type=' + encodeURIComponent(type) + '&token=' + encodeURIComponent(token);
    const res = await fetch(url);
    if (!res.ok) return null;
    return res.arrayBuffer();
}

function esc(s) { const t = document.createElement('span'); t.textContent = s || ''; return t.innerHTML; }
function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    const m = ['jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];
    return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear();
}

// ── Login ──────────────────────────────────────────────────────────────────

const loginBtn = document.getElementById('famLoginBtn');
loginBtn?.addEventListener('click', doLogin);
document.getElementById('famPassword')?.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

async function doLogin() {
    const email = document.getElementById('famEmail').value.trim();
    const pwd = document.getElementById('famPassword').value.trim();
    const errEl = document.getElementById('famLoginError');
    errEl.style.display = 'none';

    if (!email || !pwd) { errEl.textContent = 'Veuillez remplir tous les champs.'; errEl.style.display = 'block'; return; }

    loginBtn.disabled = true;
    loginBtn.innerHTML = '<span class="fam-spinner"></span> Connexion…';

    const res = await api('famille_login', { email, password: pwd });

    if (!res.success) {
        errEl.textContent = res.message || 'Erreur de connexion';
        errEl.style.display = 'block';
        loginBtn.disabled = false;
        loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Se connecter';
        return;
    }

    token = res.token;
    resident = res.resident;
    password = pwd;
    localStorage.setItem('fam_token', token);

    // Unwrap encryption key
    if (res.encryption_key) {
        try {
            aesKey = await FamilleCrypto.unwrapKey(
                res.encryption_key.encrypted_key,
                res.encryption_key.salt,
                res.encryption_key.iv,
                pwd
            );
        } catch(e) {
            console.warn('Impossible de déchiffrer la clé E2EE', e);
        }
    }

    showDashboard();
}

// ── Session restore ────────────────────────────────────────────────────────

async function checkSession() {
    if (!token) return;
    const res = await api('famille_check_session');
    if (res.success) {
        resident = res.resident;
        // Can't restore AES key without password — user must re-login for encrypted content
        showDashboard();
    } else {
        localStorage.removeItem('fam_token');
        token = '';
    }
}

function logout() {
    api('famille_logout');
    token = '';
    password = '';
    aesKey = null;
    resident = null;
    localStorage.removeItem('fam_token');
    document.getElementById('famLogin').style.display = '';
    document.getElementById('famDashboard').style.display = 'none';
    document.querySelector('.fam-shell').classList.remove('fam-dashboard-mode');
}
document.getElementById('famLogoutBtn')?.addEventListener('click', logout);

// ── Dashboard ──────────────────────────────────────────────────────────────

function showDashboard() {
    document.getElementById('famLogin').style.display = 'none';
    document.getElementById('famDashboard').style.display = '';
    document.querySelector('.fam-shell').classList.add('fam-dashboard-mode');

    // Sidebar profile
    const initials = ((resident.prenom?.[0] || '') + (resident.nom?.[0] || '')).toUpperCase();
    const avatarEl = document.getElementById('famSbAvatar');
    if (resident.photo_url) {
        avatarEl.innerHTML = '<img src="' + esc(resident.photo_url) + '" alt="">';
    } else {
        avatarEl.textContent = initials;
    }
    document.getElementById('famSbName').textContent = resident.prenom + ' ' + resident.nom;
    document.getElementById('famSbRoom').textContent = 'Chambre ' + (resident.chambre || '—') + ' · Étage ' + (resident.etage || '—');

    loadDashboard();
}

async function loadDashboard() {
    const res = await api('famille_get_dashboard');
    if (!res.success) return;

    const s = res.stats || {};
    document.getElementById('famStatAct').textContent = s.activites || 0;
    document.getElementById('famStatMed').textContent = s.medical || 0;
    document.getElementById('famStatAlb').textContent = s.albums || 0;
    document.getElementById('famStatPho').textContent = s.photos || 0;

    // Sidebar badges
    document.getElementById('famSbAct').textContent = s.activites || 0;
    document.getElementById('famSbMed').textContent = s.medical || 0;
    document.getElementById('famSbAlb').textContent = s.albums || 0;

    // Dashboard recent activités
    const dashAct = document.getElementById('famDashAct');
    const recentAct = res.activites || [];
    if (recentAct.length) {
        dashAct.innerHTML = recentAct.slice(0, 4).map(a =>
            '<div class="fam-dash-item"><strong>' + esc(a.titre) + '</strong>' +
            (a.nb_photos > 0 ? ' <small style="color:var(--fam-green)"><i class="bi bi-images"></i> ' + a.nb_photos + '</small>' : '') +
            '<div class="fam-dash-item-date">' + fmtDate(a.date_activite) + '</div></div>'
        ).join('');
    } else {
        dashAct.innerHTML = '<p class="text-muted" style="font-size:.85rem">Aucune activité</p>';
    }

    // Dashboard recent médical
    const dashMed = document.getElementById('famDashMed');
    const recentMed = res.medical || [];
    if (recentMed.length) {
        const typeLabel = { avis: 'Avis', rapport: 'Rapport', ordonnance: 'Ordonnance', autre: 'Autre' };
        dashMed.innerHTML = recentMed.slice(0, 4).map(m =>
            '<div class="fam-dash-item"><strong>' + esc(m.titre) + '</strong> <small style="opacity:.6">' + (typeLabel[m.type] || m.type) + '</small>' +
            '<div class="fam-dash-item-date">' + fmtDate(m.date_avis) + '</div></div>'
        ).join('');
    } else {
        dashMed.innerHTML = '<p class="text-muted" style="font-size:.85rem">Aucun avis médical</p>';
    }

    // Pre-render activités list for when they navigate to that pane
    renderActivites(recentAct);
    renderMedical(null); // lazy load on sidebar click
    renderGalerieFolders(null); // lazy load on sidebar click
}

// ── Tabs ───────────────────────────────────────────────────────────────────

let tabsLoaded = { dashboard: true, activites: false, medical: false, galerie: false };

const paneLabels = { dashboard: 'Accueil', activites: 'Activités', medical: 'Suivi médical', galerie: 'Galerie photos' };

document.querySelectorAll('.fam-sb-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.fam-sb-card').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.fam-pane').forEach(p => p.classList.remove('active'));
        card.classList.add('active');
        const pane = card.dataset.pane;
        document.getElementById('pane' + pane.charAt(0).toUpperCase() + pane.slice(1)).classList.add('active');

        // Update title
        document.getElementById('famPageTitle').textContent = paneLabels[pane] || pane;

        if (pane === 'activites' && !tabsLoaded.activites) { tabsLoaded.activites = true; }
        if (pane === 'medical' && !tabsLoaded.medical) { tabsLoaded.medical = true; loadMedical(); }
        if (pane === 'galerie' && !tabsLoaded.galerie) { tabsLoaded.galerie = true; loadGalerie(); }

        // Close mobile sidebar
        document.getElementById('famSidebar').classList.remove('open');
        document.getElementById('famSbOverlay').classList.remove('visible');
    });
});

// Mobile menu toggle
document.getElementById('famMobileMenu')?.addEventListener('click', () => {
    document.getElementById('famSidebar').classList.toggle('open');
    document.getElementById('famSbOverlay').classList.toggle('visible');
});
document.getElementById('famSbOverlay')?.addEventListener('click', () => {
    document.getElementById('famSidebar').classList.remove('open');
    document.getElementById('famSbOverlay').classList.remove('visible');
});

// ── Activités ──────────────────────────────────────────────────────────────

function renderActivites(items) {
    const el = document.getElementById('famActList');
    if (!items.length) {
        el.innerHTML = '<div class="fam-empty"><i class="bi bi-calendar-event"></i><p>Aucune activité pour le moment</p></div>';
        return;
    }
    el.innerHTML = items.map(a => `
        <div class="fam-act-card" data-id="${esc(a.id)}">
          <div class="fam-act-date"><i class="bi bi-calendar3"></i> ${fmtDate(a.date_activite)}</div>
          <div class="fam-act-title">${esc(a.titre)}</div>
          ${a.description ? '<div class="fam-act-desc">' + esc(a.description) + '</div>' : ''}
          ${a.nb_photos > 0 ? '<div class="fam-act-photos-badge"><i class="bi bi-images"></i> ' + a.nb_photos + ' photo' + (a.nb_photos > 1 ? 's' : '') + '</div>' : ''}
        </div>
    `).join('');

    el.querySelectorAll('.fam-act-card').forEach(card => {
        card.addEventListener('click', () => loadActiviteDetail(card.dataset.id));
    });
}

async function loadActiviteDetail(id) {
    const res = await api('famille_get_activite_detail', { id });
    if (!res.success) return;

    document.getElementById('famActList').style.display = 'none';
    const detail = document.getElementById('famActDetail');
    detail.style.display = '';

    const a = res.activite;
    const photos = res.photos || [];

    let html = '<button class="fam-detail-back" id="famActBack"><i class="bi bi-arrow-left"></i> Retour</button>';
    html += '<div class="fam-detail-header"><h3>' + esc(a.titre) + '</h3><div class="fam-act-date">' + fmtDate(a.date_activite) + '</div>';
    if (a.description) html += '<p class="fam-act-desc" style="margin-top:8px">' + esc(a.description) + '</p>';
    html += '</div>';

    if (photos.length) {
        html += '<div class="fam-detail-grid">';
        photos.forEach((p, idx) => {
            html += '<div class="fam-detail-thumb" data-file-id="' + esc(p.id) + '" data-iv="' + esc(p.encrypted_iv) + '" data-name="' + esc(p.file_name) + '" data-idx="' + idx + '">'
                + '<div class="fam-thumb-loading"><span class="fam-spinner"></span></div></div>';
        });
        html += '</div>';
    }

    detail.innerHTML = html;

    detail.querySelector('#famActBack')?.addEventListener('click', () => {
        detail.style.display = 'none';
        document.getElementById('famActList').style.display = '';
    });

    // Lazy decrypt thumbnails
    if (aesKey && photos.length) {
        const thumbs = detail.querySelectorAll('.fam-detail-thumb');
        const lbPhotos = [];

        for (const thumb of thumbs) {
            const fileId = thumb.dataset.fileId;
            const iv = thumb.dataset.iv;
            const name = thumb.dataset.name;
            const idx = parseInt(thumb.dataset.idx);

            try {
                const encrypted = await fetchFile(fileId, 'activite_photo');
                if (!encrypted) continue;
                const decrypted = await FamilleCrypto.decryptFile(aesKey, encrypted, iv);
                const url = FamilleCrypto.createBlobUrl(decrypted, FamilleCrypto.guessMime(name));
                thumb.innerHTML = '<img src="' + url + '" alt="' + esc(name) + '">';
                lbPhotos[idx] = { url, name };
                thumb.addEventListener('click', () => openLightbox(lbPhotos, idx));
            } catch(e) {
                thumb.innerHTML = '<div class="fam-thumb-loading"><i class="bi bi-lock"></i></div>';
            }
        }
    }
}

// ── Medical ────────────────────────────────────────────────────────────────

async function loadMedical() {
    const res = await api('famille_get_medical');
    if (!res.success) return;
    renderMedical(res.medical || []);
}

function renderMedical(items) {
    const el = document.getElementById('famMedList');
    if (items === null) { el.innerHTML = '<div class="fam-empty"><i class="bi bi-heart-pulse"></i><p>Chargement…</p></div>'; return; }
    if (!items.length) {
        el.innerHTML = '<div class="fam-empty"><i class="bi bi-heart-pulse"></i><p>Aucun avis médical</p></div>';
        return;
    }

    const typeIcons = { avis: 'bi-clipboard2-pulse', rapport: 'bi-file-medical', ordonnance: 'bi-prescription2', autre: 'bi-file-text' };
    const fileIcons = { pdf: 'bi-file-earmark-pdf-fill', doc: 'bi-file-earmark-word-fill', docx: 'bi-file-earmark-word-fill', xls: 'bi-file-earmark-excel-fill', xlsx: 'bi-file-earmark-excel-fill', jpg: 'bi-file-earmark-image-fill', jpeg: 'bi-file-earmark-image-fill', png: 'bi-file-earmark-image-fill' };
    const fileCls = { pdf: 'fam-med-file-pdf', doc: 'fam-med-file-doc', docx: 'fam-med-file-doc', xls: 'fam-med-file-xls', xlsx: 'fam-med-file-xls', jpg: 'fam-med-file-img', jpeg: 'fam-med-file-img', png: 'fam-med-file-img' };

    el.innerHTML = items.map(m => {
        let contentHtml = '';
        if (m.contenu_chiffre && aesKey) {
            contentHtml = '<div class="fam-med-content" data-cipher="' + esc(m.contenu_chiffre) + '" data-iv="' + esc(m.content_iv) + '"><em>Déchiffrement…</em></div>';
        }

        let filesHtml = '';
        if (m.fichiers?.length) {
            filesHtml = '<div class="fam-med-files">' + m.fichiers.map(f => {
                const ext = (f.file_type || '').toLowerCase();
                const icon = fileIcons[ext] || 'bi-file-earmark';
                const cls = fileCls[ext] || '';
                return '<div class="fam-med-file ' + cls + '" data-file-id="' + esc(f.id) + '" data-iv="' + esc(f.encrypted_iv) + '" data-name="' + esc(f.file_name) + '" data-type="' + esc(ext) + '">'
                    + '<i class="bi ' + icon + '"></i> ' + esc(f.file_name)
                    + '</div>';
            }).join('') + '</div>';
        }

        return '<div class="fam-med-card" data-type="' + esc(m.type) + '">'
            + '<div class="fam-med-header">'
            + '<div class="fam-med-icon ' + esc(m.type) + '"><i class="bi ' + (typeIcons[m.type] || 'bi-file-text') + '"></i></div>'
            + '<div><div class="fam-med-title">' + esc(m.titre) + '</div><div class="fam-med-date">' + fmtDate(m.date_avis) + '</div></div>'
            + '</div>'
            + contentHtml
            + filesHtml
            + '</div>';
    }).join('');

    // Decrypt text contents
    if (aesKey) {
        el.querySelectorAll('[data-cipher]').forEach(async (div) => {
            try {
                const text = await FamilleCrypto.decryptText(aesKey, div.dataset.cipher, div.dataset.iv);
                div.textContent = text;
            } catch(e) { div.innerHTML = '<em><i class="bi bi-lock"></i> Contenu chiffré</em>'; }
        });
    }

    // File click handlers
    el.querySelectorAll('.fam-med-file').forEach(file => {
        file.addEventListener('click', () => openMedicalFile(file.dataset.fileId, file.dataset.iv, file.dataset.name, file.dataset.type));
    });
}

async function openMedicalFile(fileId, iv, fileName, ext) {
    if (!aesKey) { alert('Clé de déchiffrement non disponible. Reconnectez-vous.'); return; }

    const encrypted = await fetchFile(fileId, 'medical_fichier');
    if (!encrypted) { alert('Fichier introuvable'); return; }

    try {
        const decrypted = await FamilleCrypto.decryptFile(aesKey, encrypted, iv);
        const mime = FamilleCrypto.guessMime(fileName);
        const url = FamilleCrypto.createBlobUrl(decrypted, mime);

        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            openLightbox([{ url, name: fileName }], 0);
        } else if (ext === 'pdf') {
            openLightboxPdf(url, fileName);
        } else {
            // Download
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        }
    } catch(e) {
        alert('Impossible de déchiffrer le fichier');
    }
}

// ── Galerie ────────────────────────────────────────────────────────────────

async function loadGalerie() {
    const res = await api('famille_get_galerie');
    if (!res.success) return;
    renderGalerieFolders(res.albums || []);
}

function renderGalerieFolders(albums) {
    const el = document.getElementById('famGalFolders');
    if (albums === null) { el.innerHTML = '<div class="fam-empty"><i class="bi bi-images"></i><p>Chargement…</p></div>'; return; }
    if (!albums.length) {
        el.innerHTML = '<div class="fam-empty"><i class="bi bi-images"></i><p>Aucun album photo</p></div>';
        return;
    }

    // Group by year
    const byYear = {};
    albums.forEach(a => {
        if (!byYear[a.annee]) byYear[a.annee] = [];
        byYear[a.annee].push(a);
    });

    const years = Object.keys(byYear).sort((a, b) => b - a);

    let html = '<div class="fam-gal-years">';
    years.forEach(year => {
        html += '<div><div class="fam-gal-year-label">' + esc(year) + '</div><div class="fam-gal-grid">';
        byYear[year].forEach(album => {
            html += '<div class="fam-gal-folder" data-album-id="' + esc(album.id) + '">'
                + '<div class="fam-gal-cover" data-cover-id="' + esc(album.cover?.id || '') + '" data-cover-iv="' + esc(album.cover?.encrypted_iv || '') + '" data-cover-name="' + esc(album.cover?.file_name || '') + '">'
                + '<div class="fam-gal-placeholder"><i class="bi bi-folder2-open"></i></div>'
                + '</div>'
                + '<div class="fam-gal-info"><h4>' + esc(album.titre) + '</h4><p>' + fmtDate(album.date_galerie) + ' · ' + album.nb_photos + ' photo' + (album.nb_photos > 1 ? 's' : '') + '</p></div>'
                + '</div>';
        });
        html += '</div></div>';
    });
    html += '</div>';

    el.innerHTML = html;

    // Load covers
    if (aesKey) {
        el.querySelectorAll('.fam-gal-cover[data-cover-id]').forEach(async (cover) => {
            const coverId = cover.dataset.coverId;
            const coverIv = cover.dataset.coverIv;
            const coverName = cover.dataset.coverName;
            if (!coverId || !coverIv) return;
            try {
                const encrypted = await fetchFile(coverId, 'galerie_photo');
                if (!encrypted) return;
                const decrypted = await FamilleCrypto.decryptFile(aesKey, encrypted, coverIv);
                const url = FamilleCrypto.createBlobUrl(decrypted, FamilleCrypto.guessMime(coverName));
                cover.innerHTML = '<img src="' + url + '">';
            } catch(e) {}
        });
    }

    // Folder click
    el.querySelectorAll('.fam-gal-folder').forEach(folder => {
        folder.addEventListener('click', () => openAlbum(folder.dataset.albumId));
    });
}

async function openAlbum(albumId) {
    const res = await api('famille_get_album_photos', { album_id: albumId });
    if (!res.success) return;

    document.getElementById('famGalFolders').style.display = 'none';
    const albumEl = document.getElementById('famGalAlbum');
    albumEl.style.display = '';

    const album = res.album;
    const photos = res.photos || [];

    let html = '<div class="fam-album-header">'
        + '<button class="fam-detail-back" id="famAlbumBack"><i class="bi bi-arrow-left"></i> Retour</button>'
        + '<h3>' + esc(album.titre) + '</h3>'
        + '<span style="color:var(--fam-text-muted);font-size:.85rem">' + fmtDate(album.date_galerie) + ' · ' + photos.length + ' photos</span>'
        + '</div>';

    if (photos.length) {
        html += '<div class="fam-album-grid">';
        photos.forEach((p, idx) => {
            html += '<div class="fam-album-thumb" data-file-id="' + esc(p.id) + '" data-iv="' + esc(p.encrypted_iv) + '" data-name="' + esc(p.file_name) + '" data-legend="' + esc(p.legende || '') + '" data-idx="' + idx + '">'
                + '<div class="fam-thumb-loading"><span class="fam-spinner"></span></div>'
                + '</div>';
        });
        html += '</div>';
    }

    albumEl.innerHTML = html;

    albumEl.querySelector('#famAlbumBack')?.addEventListener('click', () => {
        albumEl.style.display = 'none';
        document.getElementById('famGalFolders').style.display = '';
    });

    // Lazy decrypt
    if (aesKey && photos.length) {
        const thumbs = albumEl.querySelectorAll('.fam-album-thumb');
        const lbPhotos = [];

        for (const thumb of thumbs) {
            const fileId = thumb.dataset.fileId;
            const iv = thumb.dataset.iv;
            const name = thumb.dataset.name;
            const legend = thumb.dataset.legend;
            const idx = parseInt(thumb.dataset.idx);

            try {
                const encrypted = await fetchFile(fileId, 'galerie_photo');
                if (!encrypted) continue;
                const decrypted = await FamilleCrypto.decryptFile(aesKey, encrypted, iv);
                const url = FamilleCrypto.createBlobUrl(decrypted, FamilleCrypto.guessMime(name));
                let imgHtml = '<img src="' + url + '" alt="' + esc(name) + '">';
                if (legend) imgHtml += '<div class="fam-thumb-legend">' + esc(legend) + '</div>';
                thumb.innerHTML = imgHtml;
                lbPhotos[idx] = { url, name: legend || name };
                thumb.addEventListener('click', () => openLightbox(lbPhotos, idx));
            } catch(e) {
                thumb.innerHTML = '<div class="fam-thumb-loading"><i class="bi bi-lock"></i></div>';
            }
        }
    }
}

// ── Lightbox ───────────────────────────────────────────────────────────────

let lbPhotos = [];
let lbIndex = 0;

function openLightbox(photos, idx) {
    lbPhotos = photos.filter(Boolean);
    lbIndex = idx;
    if (!lbPhotos.length) return;

    const lb = document.getElementById('famLightbox');
    lb.classList.remove('fam-lb-hidden');
    document.body.style.overflow = 'hidden';
    renderLbSlide();
}

function openLightboxPdf(url, name) {
    lbPhotos = [{ url, name, isPdf: true }];
    lbIndex = 0;
    const lb = document.getElementById('famLightbox');
    lb.classList.remove('fam-lb-hidden');
    document.body.style.overflow = 'hidden';
    renderLbSlide();
}

function renderLbSlide() {
    const item = lbPhotos[lbIndex];
    if (!item) return;

    const stage = document.getElementById('famLbStage');
    const counter = document.getElementById('famLbCounter');
    const title = document.getElementById('famLbTitle');
    const dl = document.getElementById('famLbDownload');

    if (item.isPdf) {
        stage.innerHTML = '<iframe class="fam-lb-iframe" src="' + item.url + '"></iframe>';
    } else {
        stage.innerHTML = '<img class="fam-lb-img" src="' + item.url + '" alt="">';
    }

    counter.textContent = (lbIndex + 1) + ' / ' + lbPhotos.length;
    title.textContent = item.name || '';
    dl.href = item.url;
    dl.download = item.name || 'fichier';
}

function closeLb() {
    document.getElementById('famLightbox').classList.add('fam-lb-hidden');
    document.body.style.overflow = '';
    document.getElementById('famLbStage').innerHTML = '';
}

document.querySelector('.fam-lb-close')?.addEventListener('click', closeLb);
document.querySelector('.fam-lb-overlay')?.addEventListener('click', closeLb);
document.querySelector('.fam-lb-prev')?.addEventListener('click', () => {
    if (lbIndex > 0) { lbIndex--; renderLbSlide(); }
});
document.querySelector('.fam-lb-next')?.addEventListener('click', () => {
    if (lbIndex < lbPhotos.length - 1) { lbIndex++; renderLbSlide(); }
});
document.addEventListener('keydown', (e) => {
    if (document.getElementById('famLightbox').classList.contains('fam-lb-hidden')) return;
    if (e.key === 'Escape') closeLb();
    else if (e.key === 'ArrowLeft' && lbIndex > 0) { lbIndex--; renderLbSlide(); }
    else if (e.key === 'ArrowRight' && lbIndex < lbPhotos.length - 1) { lbIndex++; renderLbSlide(); }
});

// ── Init ───────────────────────────────────────────────────────────────────

// Demo toggle + buttons
document.getElementById('famDemoToggle')?.addEventListener('click', () => {
    const list = document.getElementById('famDemoList');
    list.style.display = list.style.display === 'none' ? '' : 'none';
});
document.querySelectorAll('.fam-demo-use').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('famEmail').value = btn.dataset.email;
        document.getElementById('famPassword').value = btn.dataset.pwd;
    });
});

checkSession();

})();
</script>


</body>
</html>
