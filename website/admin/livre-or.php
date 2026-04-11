<?php
/**
 * Website Admin — Modération du livre d'or
 */
require_once __DIR__ . '/../../init.php';

if (empty($_SESSION['ss_user']) || !in_array($_SESSION['ss_user']['role'], ['admin', 'direction', 'responsable'])) {
    header('Location: /spocspace/login');
    exit;
}

$user = $_SESSION['ss_user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modération Livre d'or — Site Vitrine</title>
<meta name="csrf-token" content="<?= h($_SESSION['ss_csrf_token'] ?? '') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<style>
* { box-sizing: border-box; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: #f5f7fa;
  color: #1f2937;
  margin: 0;
  line-height: 1.5;
}

/* Topbar */
.lo-topbar {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  padding: 14px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
}
.lo-topbar h1 {
  font-size: 1.05rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.lo-topbar h1 i { color: #E6C220; }
.lo-topbar-actions { display: flex; gap: 10px; align-items: center; }
.lo-btn-ghost {
  padding: 8px 16px;
  background: transparent;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: .85rem;
  color: #4b5563;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all .2s;
}
.lo-btn-ghost:hover { background: #f3f4f6; color: #111827; }

/* Container */
.lo-main { max-width: 1200px; margin: 0 auto; padding: 32px 28px; }

/* Stats cards */
.lo-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.lo-stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.lo-stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem;
}
.lo-stat-icon.lo-pending { background: rgba(245,158,11,.12); color: #d97706; }
.lo-stat-icon.lo-approved { background: rgba(16,185,129,.12); color: #059669; }
.lo-stat-icon.lo-rejected { background: rgba(239,68,68,.12); color: #dc2626; }
.lo-stat-info .lo-stat-num { font-size: 1.7rem; font-weight: 700; color: #111827; line-height: 1; }
.lo-stat-info .lo-stat-label { font-size: .82rem; color: #6b7280; margin-top: 4px; }

/* Tabs */
.lo-tabs {
  display: flex;
  gap: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 20px;
  width: fit-content;
}
.lo-tab {
  padding: 8px 18px;
  background: transparent;
  border: none;
  border-radius: 8px;
  font-size: .87rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all .2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.lo-tab:hover { color: #111827; background: #f9fafb; }
.lo-tab.active { background: #2E7D32; color: #fff; }
.lo-tab .lo-badge {
  background: rgba(255,255,255,.25);
  padding: 1px 8px;
  border-radius: 100px;
  font-size: .72rem;
  font-weight: 600;
}
.lo-tab:not(.active) .lo-badge { background: #e5e7eb; color: #374151; }

/* List */
.lo-list { display: flex; flex-direction: column; gap: 14px; }
.lo-item {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 22px;
  position: relative;
  transition: box-shadow .2s;
}
.lo-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,.04); }
.lo-item.lo-pinned { border-color: #E6C220; background: linear-gradient(180deg, #FFFCEB 0%, #fff 70%); }

.lo-item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.lo-item-meta { flex: 1; min-width: 0; }
.lo-item-author { font-size: 1rem; font-weight: 600; color: #111827; }
.lo-item-author small { color: #6b7280; font-weight: 400; margin-left: 8px; }
.lo-item-info {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: .8rem;
  color: #6b7280;
  margin-top: 6px;
}
.lo-item-info i { margin-right: 3px; }

.lo-stars { color: #E6C220; font-size: 1rem; letter-spacing: 1px; }

.lo-item-title { font-size: 1.02rem; font-weight: 600; color: #111827; margin: 4px 0 8px; }
.lo-item-message {
  color: #374151;
  font-size: .92rem;
  white-space: pre-wrap;
  background: #f9fafb;
  border-radius: 10px;
  padding: 14px 16px;
  border-left: 3px solid #2E7D32;
}

.lo-cible-badge {
  display: inline-flex;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .5px;
  background: #f3f4f6;
  color: #4b5563;
  padding: 4px 10px;
  border-radius: 100px;
  font-weight: 600;
}

.lo-item-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #f3f4f6;
}
.lo-action-btn {
  padding: 7px 14px;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  font-size: .8rem;
  font-weight: 500;
  color: #4b5563;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all .15s;
}
.lo-action-btn:hover { background: #f9fafb; }
.lo-action-btn.lo-approve { color: #059669; border-color: rgba(16,185,129,.3); }
.lo-action-btn.lo-approve:hover { background: rgba(16,185,129,.08); }
.lo-action-btn.lo-reject { color: #dc2626; border-color: rgba(239,68,68,.3); }
.lo-action-btn.lo-reject:hover { background: rgba(239,68,68,.08); }
.lo-action-btn.lo-pin { color: #d97706; border-color: rgba(245,158,11,.3); }
.lo-action-btn.lo-pin:hover { background: rgba(245,158,11,.08); }
.lo-action-btn.lo-delete { color: #6b7280; }
.lo-action-btn.lo-delete:hover { background: #fee2e2; color: #dc2626; }
.lo-action-btn.lo-edit { color: #2563eb; border-color: rgba(37,99,235,.3); }
.lo-action-btn.lo-edit:hover { background: rgba(37,99,235,.08); }
.lo-action-btn.lo-reply { color: #7c3aed; border-color: rgba(124,58,237,.3); }
.lo-action-btn.lo-reply:hover { background: rgba(124,58,237,.08); }

/* Status pill */
.lo-status {
  position: absolute; top: 18px; right: 22px;
  font-size: .68rem; text-transform: uppercase; letter-spacing: .5px;
  padding: 3px 10px; border-radius: 100px; font-weight: 600;
}
.lo-status.lo-en_attente { background: rgba(245,158,11,.12); color: #d97706; }
.lo-status.lo-approuve { background: rgba(16,185,129,.12); color: #059669; }
.lo-status.lo-rejete { background: rgba(239,68,68,.12); color: #dc2626; }

.lo-empty {
  text-align: center;
  padding: 80px 20px;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  color: #9ca3af;
}
.lo-empty i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: .6; }

.lo-loading { text-align: center; padding: 60px; color: #9ca3af; }
.lo-loading i { font-size: 2rem; animation: loSpin 1s linear infinite; display: block; margin-bottom: 10px; }
@keyframes loSpin { to { transform: rotate(360deg); } }

/* Toast */
.lo-toast {
  position: fixed; bottom: 24px; right: 24px;
  background: #111827; color: #fff;
  padding: 12px 20px; border-radius: 10px;
  font-size: .88rem; font-weight: 500;
  box-shadow: 0 10px 30px rgba(0,0,0,.2);
  transform: translateY(80px); opacity: 0;
  transition: all .3s; z-index: 1000;
}
.lo-toast.show { transform: translateY(0); opacity: 1; }
.lo-toast.lo-toast-error { background: #dc2626; }

/* Edit modal */
.lo-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(17,24,39,.5);
  display: none; align-items: center; justify-content: center;
  z-index: 200; padding: 20px;
}
.lo-modal-overlay.show { display: flex; }
.lo-modal {
  background: #fff; border-radius: 14px; max-width: 640px; width: 100%;
  max-height: calc(100vh - 40px);
  box-shadow: 0 25px 80px rgba(0,0,0,.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.lo-modal-header {
  padding: 18px 24px; border-bottom: 1px solid #e5e7eb;
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.lo-modal-header h3 { margin: 0; font-size: 1.05rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.lo-modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #6b7280; line-height: 1; }
.lo-modal-body {
  padding: 24px;
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1 1 auto;
  min-height: 0;
}
.lo-modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  flex-shrink: 0;
  background: #fff;
}
.lo-form-group { margin-bottom: 16px; }
.lo-form-group label { display: block; font-size: .82rem; font-weight: 500; color: #4b5563; margin-bottom: 6px; }
.lo-form-group input, .lo-form-group textarea, .lo-form-group select {
  width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px;
  font-size: .9rem; font-family: inherit; color: #111827;
}
.lo-form-group textarea { min-height: 140px; resize: vertical; }
.lo-form-group input:focus, .lo-form-group textarea:focus, .lo-form-group select:focus {
  outline: none; border-color: #2E7D32; box-shadow: 0 0 0 3px rgba(46,125,50,.1);
}
.lo-btn-primary {
  padding: 10px 22px; background: #2E7D32; color: #fff; border: none;
  border-radius: 8px; font-size: .88rem; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.lo-btn-primary:hover { background: #1B5E20; }
.lo-btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }
.lo-btn-primary:disabled:hover { background: #9ca3af; }
.lo-btn-ghost {
  padding: 10px 20px; background: transparent; border: 1px solid #e5e7eb;
  border-radius: 8px; font-size: .85rem; font-weight: 500; cursor: pointer;
  color: #4b5563;
}
.lo-btn-ghost:hover { background: #f9fafb; }

/* Reply modal styles */
.lo-reply-original {
  background: #f9fafb;
  border-left: 3px solid #7c3aed;
  border-radius: 0 10px 10px 0;
  padding: 14px 18px;
  margin-bottom: 18px;
}
.lo-reply-original-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  color: #4b5563;
  font-size: .88rem;
}
.lo-reply-original-header i {
  color: #7c3aed;
  font-size: 1.15rem;
}
.lo-reply-original-header strong {
  color: #111827;
  display: block;
  font-size: .92rem;
}
.lo-reply-original-header small {
  color: #9ca3af;
  font-size: .75rem;
}
.lo-reply-quote {
  color: #374151;
  font-style: italic;
  font-size: .88rem;
  line-height: 1.55;
  padding-left: 4px;
  border-left: 2px dashed #e5e7eb;
  margin-left: 4px;
  padding: 4px 0 4px 12px;
  max-height: 120px;
  overflow-y: auto;
  white-space: pre-wrap;
}

.lo-reply-info {
  display: flex;
  gap: 14px;
  padding: 14px 16px;
  background: linear-gradient(135deg, rgba(124,58,237,.06) 0%, rgba(249,216,53,.06) 100%);
  border: 1px solid rgba(124,58,237,.15);
  border-radius: 10px;
  margin-bottom: 18px;
}
.lo-reply-info-icon {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(124,58,237,.12);
  color: #7c3aed;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}
.lo-reply-info strong {
  display: block;
  font-size: .88rem;
  color: #111827;
  margin-bottom: 4px;
}
.lo-reply-info p {
  margin: 0;
  font-size: .82rem;
  color: #4b5563;
  line-height: 1.55;
}

.lo-reply-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 14px;
  padding: 12px 14px;
  background: #f9fafb;
  border-radius: 8px;
  opacity: .6;
}
.lo-check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: .85rem;
  color: #4b5563;
  cursor: not-allowed;
}
.lo-check input {
  width: 16px;
  height: 16px;
  accent-color: #7c3aed;
}
</style>
</head>
<body>

<header class="lo-topbar">
  <h1><i class="bi bi-journal-bookmark-fill"></i> Modération du livre d'or</h1>
  <div class="lo-topbar-actions">
    <a href="/spocspace/website/livre-or.php" target="_blank" class="lo-btn-ghost">
      <i class="bi bi-eye"></i> Voir la page publique
    </a>
    <a href="/spocspace/website/admin/" class="lo-btn-ghost">
      <i class="bi bi-arrow-left"></i> Admin Site
    </a>
  </div>
</header>

<main class="lo-main">

  <!-- Stats -->
  <div class="lo-stats">
    <div class="lo-stat-card">
      <div class="lo-stat-icon lo-pending"><i class="bi bi-hourglass-split"></i></div>
      <div class="lo-stat-info">
        <div class="lo-stat-num" id="loStPending">0</div>
        <div class="lo-stat-label">À modérer</div>
      </div>
    </div>
    <div class="lo-stat-card">
      <div class="lo-stat-icon lo-approved"><i class="bi bi-check-circle"></i></div>
      <div class="lo-stat-info">
        <div class="lo-stat-num" id="loStApproved">0</div>
        <div class="lo-stat-label">Publiés</div>
      </div>
    </div>
    <div class="lo-stat-card">
      <div class="lo-stat-icon lo-rejected"><i class="bi bi-x-circle"></i></div>
      <div class="lo-stat-info">
        <div class="lo-stat-num" id="loStRejected">0</div>
        <div class="lo-stat-label">Rejetés</div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="lo-tabs">
    <button class="lo-tab active" data-statut="en_attente">
      <i class="bi bi-hourglass-split"></i> À modérer <span class="lo-badge" id="loBPending">0</span>
    </button>
    <button class="lo-tab" data-statut="approuve">
      <i class="bi bi-check-circle"></i> Publiés <span class="lo-badge" id="loBApproved">0</span>
    </button>
    <button class="lo-tab" data-statut="rejete">
      <i class="bi bi-x-circle"></i> Rejetés <span class="lo-badge" id="loBRejected">0</span>
    </button>
    <button class="lo-tab" data-statut="all">
      <i class="bi bi-list"></i> Tous
    </button>
  </div>

  <!-- List -->
  <div id="loList">
    <div class="lo-loading"><i class="bi bi-arrow-repeat"></i> Chargement…</div>
  </div>

</main>

<!-- Edit modal -->
<div class="lo-modal-overlay" id="loEditModal">
  <div class="lo-modal">
    <div class="lo-modal-header">
      <h3><i class="bi bi-pencil"></i> Modifier le témoignage</h3>
      <button class="lo-modal-close" onclick="loCloseEdit()">&times;</button>
    </div>
    <div class="lo-modal-body">
      <input type="hidden" id="loEditId">
      <div class="lo-form-group">
        <label>Titre</label>
        <input type="text" id="loEditTitre" maxlength="200">
      </div>
      <div class="lo-form-group">
        <label>Note</label>
        <select id="loEditNote">
          <option value="5">★★★★★ (5)</option>
          <option value="4">★★★★☆ (4)</option>
          <option value="3">★★★☆☆ (3)</option>
          <option value="2">★★☆☆☆ (2)</option>
          <option value="1">★☆☆☆☆ (1)</option>
        </select>
      </div>
      <div class="lo-form-group">
        <label>Message</label>
        <textarea id="loEditMessage" maxlength="3000"></textarea>
      </div>
    </div>
    <div class="lo-modal-footer">
      <button class="lo-btn-ghost" onclick="loCloseEdit()">Annuler</button>
      <button class="lo-btn-primary" onclick="loSaveEdit()"><i class="bi bi-check-lg"></i> Enregistrer</button>
    </div>
  </div>
</div>

<!-- Reply modal -->
<div class="lo-modal-overlay" id="loReplyModal">
  <div class="lo-modal">
    <div class="lo-modal-header">
      <h3><i class="bi bi-reply"></i> Répondre au témoignage</h3>
      <button class="lo-modal-close" onclick="loCloseReply()">&times;</button>
    </div>
    <div class="lo-modal-body">
      <!-- Rappel du témoignage -->
      <div class="lo-reply-original">
        <div class="lo-reply-original-header">
          <i class="bi bi-chat-left-quote"></i>
          <div>
            <strong id="loReplyAuthor">Auteur</strong>
            <small id="loReplyEmail"></small>
          </div>
        </div>
        <div id="loReplyQuote" class="lo-reply-quote"></div>
      </div>

      <!-- Bannière info -->
      <div class="lo-reply-info">
        <div class="lo-reply-info-icon"><i class="bi bi-info-circle-fill"></i></div>
        <div>
          <strong>Fonctionnalité à venir</strong>
          <p>La réponse sera envoyée par email à l'auteur et affichée publiquement sous son témoignage sur le site, comme une réponse officielle de la direction.</p>
        </div>
      </div>

      <div class="lo-form-group">
        <label>Votre réponse <small style="color:#9ca3af;font-weight:400">(sera signée au nom de la direction)</small></label>
        <textarea id="loReplyMessage" maxlength="2000" rows="6" placeholder="Chère Madame, Cher Monsieur,&#10;&#10;Nous vous remercions chaleureusement pour votre témoignage..."></textarea>
      </div>

      <div class="lo-reply-options">
        <label class="lo-check">
          <input type="checkbox" id="loReplySendEmail" disabled> Envoyer par email à l'auteur
        </label>
        <label class="lo-check">
          <input type="checkbox" id="loReplyPublic" disabled checked> Afficher publiquement sur le site
        </label>
      </div>
    </div>
    <div class="lo-modal-footer">
      <button class="lo-btn-ghost" onclick="loCloseReply()">Annuler</button>
      <button class="lo-btn-primary" disabled title="Fonctionnalité à venir"><i class="bi bi-send"></i> Envoyer (bientôt)</button>
    </div>
  </div>
</div>

<div class="lo-toast" id="loToast"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const API = '/spocspace/website/admin/api/livre-or.php';

const CIBLE_LABELS = {
  ems: "Établissement", personnel: "Personnel", prise_en_charge: "Prise en charge",
  vie: "Vie sociale", autre: "Autre"
};
const STATUT_LABELS = {
  en_attente: "À modérer", approuve: "Publié", rejete: "Rejeté"
};

let currentStatut = 'en_attente';
let editingItem = null;

function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }
function fmtDate(s) {
  if (!s) return '';
  const d = new Date(s.replace(' ', 'T'));
  return d.toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function toast(msg, isError) {
  const t = document.getElementById('loToast');
  t.textContent = msg;
  t.classList.toggle('lo-toast-error', !!isError);
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

async function api(action, data = {}) {
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ action, ...data })
  });
  return res.json();
}

async function load() {
  const list = document.getElementById('loList');
  list.innerHTML = '<div class="lo-loading"><i class="bi bi-arrow-repeat"></i> Chargement…</div>';
  const data = await api('list', { statut: currentStatut });
  if (!data.success) {
    list.innerHTML = '<div class="lo-empty"><i class="bi bi-exclamation-triangle"></i> Erreur de chargement</div>';
    return;
  }

  // Stats
  document.getElementById('loStPending').textContent = data.stats.en_attente;
  document.getElementById('loStApproved').textContent = data.stats.approuve;
  document.getElementById('loStRejected').textContent = data.stats.rejete;
  document.getElementById('loBPending').textContent = data.stats.en_attente;
  document.getElementById('loBApproved').textContent = data.stats.approuve;
  document.getElementById('loBRejected').textContent = data.stats.rejete;

  if (!data.temoignages.length) {
    list.innerHTML = '<div class="lo-empty"><i class="bi bi-inbox"></i><div>Aucun témoignage</div></div>';
    return;
  }

  list.innerHTML = '<div class="lo-list">' + data.temoignages.map(renderItem).join('') + '</div>';
}

function renderItem(t) {
  const titre = t.titre ? `<div class="lo-item-title">${escHtml(t.titre)}</div>` : '';
  const lien = t.lien_resident ? `<small>· ${escHtml(t.lien_resident)}</small>` : '';
  const email = t.email ? `<span><i class="bi bi-envelope"></i>${escHtml(t.email)}</span>` : '';
  const isPending = t.statut === 'en_attente';
  const isApproved = t.statut === 'approuve';

  return `
    <div class="lo-item ${t.epingle == 1 ? 'lo-pinned' : ''}" data-id="${t.id}">
      <span class="lo-status lo-${t.statut}">${STATUT_LABELS[t.statut]}</span>
      <div class="lo-item-header">
        <div class="lo-item-meta">
          <div class="lo-item-author">${escHtml(t.nom)} ${lien}</div>
          <div class="lo-item-info">
            <span class="lo-stars">${stars(t.note)}</span>
            <span class="lo-cible-badge">${CIBLE_LABELS[t.cible] || t.cible}</span>
            <span><i class="bi bi-clock"></i>${fmtDate(t.created_at)}</span>
            ${email}
          </div>
        </div>
      </div>
      ${titre}
      <div class="lo-item-message">${escHtml(t.message)}</div>
      <div class="lo-item-actions">
        ${!isApproved ? `<button class="lo-action-btn lo-approve" onclick="loApprove('${t.id}')"><i class="bi bi-check-lg"></i> Publier</button>` : ''}
        ${isPending || isApproved ? `<button class="lo-action-btn lo-reject" onclick="loReject('${t.id}')"><i class="bi bi-x-lg"></i> Rejeter</button>` : ''}
        ${isApproved ? `<button class="lo-action-btn lo-pin" onclick="loTogglePin('${t.id}')"><i class="bi bi-pin-angle${t.epingle == 1 ? '-fill' : ''}"></i> ${t.epingle == 1 ? 'Désépingler' : 'Épingler'}</button>` : ''}
        <button class="lo-action-btn lo-edit" onclick='loOpenEdit(${JSON.stringify({id:t.id,titre:t.titre||"",note:t.note,message:t.message}).replace(/'/g,"&#39;")})'><i class="bi bi-pencil"></i> Modifier</button>
        <button class="lo-action-btn lo-reply" onclick='loOpenReply(${JSON.stringify({id:t.id,nom:t.nom,email:t.email||"",titre:t.titre||"",message:t.message}).replace(/'/g,"&#39;")})'><i class="bi bi-reply"></i> Répondre</button>
        <button class="lo-action-btn lo-delete" onclick="loDelete('${t.id}')"><i class="bi bi-trash"></i> Supprimer</button>
      </div>
    </div>
  `;
}

async function loApprove(id) {
  const r = await api('set_statut', { id, statut: 'approuve' });
  if (r.success) { toast('Témoignage publié'); load(); } else toast('Erreur', true);
}
async function loReject(id) {
  const r = await api('set_statut', { id, statut: 'rejete' });
  if (r.success) { toast('Témoignage rejeté'); load(); } else toast('Erreur', true);
}
async function loTogglePin(id) {
  const r = await api('toggle_pin', { id });
  if (r.success) { toast('Mis à jour'); load(); } else toast('Erreur', true);
}
async function loDelete(id) {
  if (!confirm('Supprimer définitivement ce témoignage ?')) return;
  const r = await api('delete', { id });
  if (r.success) { toast('Supprimé'); load(); } else toast('Erreur', true);
}

function loOpenEdit(data) {
  editingItem = data.id;
  document.getElementById('loEditId').value = data.id;
  document.getElementById('loEditTitre').value = data.titre || '';
  document.getElementById('loEditNote').value = data.note;
  document.getElementById('loEditMessage').value = data.message;
  document.getElementById('loEditModal').classList.add('show');
}
function loCloseEdit() {
  document.getElementById('loEditModal').classList.remove('show');
  editingItem = null;
}
async function loSaveEdit() {
  const r = await api('update', {
    id: editingItem,
    titre: document.getElementById('loEditTitre').value,
    note: parseInt(document.getElementById('loEditNote').value, 10),
    message: document.getElementById('loEditMessage').value
  });
  if (r.success) { toast('Modifié'); loCloseEdit(); load(); } else toast(r.message || 'Erreur', true);
}

// Tabs
document.querySelectorAll('.lo-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.lo-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentStatut = btn.dataset.statut;
    load();
  });
});

// Close edit modal on overlay click
document.getElementById('loEditModal').addEventListener('click', (e) => {
  if (e.target.id === 'loEditModal') loCloseEdit();
});

// ── Reply modal (fonctionnalité à venir) ──
function loOpenReply(data) {
  document.getElementById('loReplyAuthor').textContent = data.nom || '';
  document.getElementById('loReplyEmail').textContent = data.email ? '· ' + data.email : '(aucun email)';
  document.getElementById('loReplyQuote').textContent = data.message || '';
  document.getElementById('loReplyMessage').value = '';
  document.getElementById('loReplyModal').classList.add('show');
}
function loCloseReply() {
  document.getElementById('loReplyModal').classList.remove('show');
}
document.getElementById('loReplyModal').addEventListener('click', (e) => {
  if (e.target.id === 'loReplyModal') loCloseReply();
});

load();
</script>
</body>
</html>
