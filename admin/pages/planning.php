<?php
// ─── Données serveur ──────────────────────────────────────────────────────────
$planningUsers = Db::fetchAll(
    "SELECT u.id, u.nom, u.prenom, u.taux, u.role, u.type_contrat,
            f.code AS fonction_code, f.nom AS fonction_nom, f.ordre AS fonction_ordre,
            GROUP_CONCAT(m.id ORDER BY um.is_principal DESC) AS module_ids,
            GROUP_CONCAT(m.code ORDER BY um.is_principal DESC) AS module_codes
     FROM users u
     LEFT JOIN fonctions f ON f.id = u.fonction_id
     LEFT JOIN user_modules um ON um.user_id = u.id
     LEFT JOIN modules m ON m.id = um.module_id
     WHERE u.is_active = 1
     GROUP BY u.id
     ORDER BY f.ordre, u.nom, u.prenom"
);
$planningHoraires = Db::fetchAll(
    "SELECT id, code, nom, heure_debut, heure_fin, duree_effective, couleur
     FROM horaires_types WHERE is_active = 1 ORDER BY code"
);
$planningModules = Db::fetchAll("SELECT id, code, nom, ordre FROM modules ORDER BY ordre");
$planningFonctions = Db::fetchAll("SELECT id, code, nom, ordre FROM fonctions ORDER BY ordre");
?>
<!-- Planning Admin — Toolbar -->
<div class="planning-toolbar" id="planningToolbar">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <input type="hidden" id="planningMois">
    <div class="pm-picker" id="pmPicker">
      <button type="button" class="pm-picker-btn" id="pmPickerBtn">
        <span class="pm-picker-icon" id="pmPickerIcon"></span>
        <span class="pm-picker-label" id="pmPickerLabel">Chargement...</span>
        <i class="bi bi-chevron-down pm-picker-chevron"></i>
      </button>
      <div class="pm-picker-dropdown" id="pmDropdown">
        <div class="pm-picker-nav">
          <button type="button" class="pm-nav-btn" id="pmPrevYear"><i class="bi bi-chevron-left"></i></button>
          <span class="pm-nav-year" id="pmYear"></span>
          <button type="button" class="pm-nav-btn" id="pmNextYear"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="pm-picker-grid" id="pmGrid"></div>
      </div>
    </div>
    <div class="zs-select w-input-view" id="planningViewMode" data-placeholder="Vue mois"></div>
    <span id="planningStatus" class="ms-2"></span>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <button class="btn btn-dark btn-sm" id="btnGeneratePlanning" title="Générer le planning">
      <i class="bi bi-calendar2-plus"></i><span class="d-none d-md-inline"> Générer planning</span>
    </button>
    <div class="btn-group btn-group-sm">
      <button class="btn btn-outline-secondary" id="btnCreatePlanning" title="Créer planning">
        <i class="bi bi-plus-lg"></i><span class="d-none d-md-inline"> Créer</span>
      </button>
      <button class="btn btn-outline-secondary" id="btnStatsPlanning" title="Statistiques">
        <i class="bi bi-graph-up"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnSettingsPlanning" title="Paramètres de génération">
        <i class="bi bi-sliders"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnClearPlanning" title="Vider le planning">
        <i class="bi bi-trash"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnFullscreen" title="Plein écran">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
    </div>
    <div class="btn-group btn-group-sm">
      <button class="btn btn-outline-secondary" id="btnPrintPlanning" title="Imprimer">
        <i class="bi bi-printer"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnExportPdf" title="Exporter PDF">
        <i class="bi bi-file-earmark-pdf"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnEmailPlanning" title="Envoyer par email">
        <i class="bi bi-envelope"></i>
      </button>
      <button class="btn btn-outline-secondary" id="btnExportCsv" title="Export PolyPoint (CSV)">
        <i class="bi bi-filetype-csv"></i>
      </button>
    </div>
    <div class="btn-group btn-group-sm">
      <button class="btn btn-outline-purple-custom" id="btnSaveProposal" title="Sauvegarder comme proposition de vote">
        <i class="bi bi-hand-thumbs-up"></i><span class="d-none d-md-inline"> Proposition</span>
      </button>
      <button class="btn btn-outline-purple-custom" id="btnViewProposals" title="Voir les propositions">
        <i class="bi bi-list-check"></i>
      </button>
    </div>
    <div class="btn-group btn-group-sm d-hidden" id="finalizeGroup">
      <button class="btn btn-info btn-sm" id="btnProvisoire">Provisoire</button>
      <button class="btn btn-success btn-sm" id="btnFinaliser">Finaliser</button>
    </div>
    <button class="btn btn-outline-secondary btn-sm d-hidden" id="btnViewPrompt" title="Voir le prompt IA">
      <i class="bi bi-code-slash"></i><span class="d-none d-md-inline"> Prompt</span>
    </button>
  </div>
</div>

<!-- Filter tabs carousel -->
<div class="module-switch mt-1 mb-1" id="moduleSwitch">
  <button class="module-switch-btn active" data-filter-type="all" data-filter-value="">Tous</button>
</div>

<!-- Hidden selects for backward compat -->
<div class="zs-select d-hidden" id="planningModuleFilter" data-placeholder="Tous les modules"></div>
<input type="hidden" id="planningFilterType" value="all">
<input type="hidden" id="planningFilterValue" value="">

<!-- Week nav (for week view) -->
<div class="planning-week-nav d-hidden" id="weekNav">
  <button class="btn btn-sm btn-outline-secondary" id="prevWeek"><i class="bi bi-chevron-left"></i></button>
  <span id="weekLabel" class="fw-600 mx-2"></span>
  <button class="btn btn-sm btn-outline-secondary" id="nextWeek"><i class="bi bi-chevron-right"></i></button>
</div>

<!-- Planning grid (fullscreen-able) -->
<div class="mt-1" id="planningCard">
  <div id="planningContent" class="planning-loading">
    <span class="admin-spinner"></span> Chargement...
  </div>
</div>

<!-- Stats modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-graph-up"></i> Statistiques du planning</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="statsContent" style="max-height:68vh;overflow-y:auto;padding:16px"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Cell edit modal -->
<div class="modal fade" id="cellModal" tabindex="-1">
  <div class="modal-dialog modal-info">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="cellModalTitle">Modifier assignation</h6>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center modal-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg icon-close-sm"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cellUserId">
        <input type="hidden" id="cellDate">
        <div class="mb-2">
          <label class="form-label form-label-sm mb-1">Horaire</label>
          <div class="ch-grid" id="cellHoraireGrid"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label form-label-sm mb-1">Module</label>
            <div class="zs-select" id="cellModule" data-placeholder="— Aucun —"></div>
          </div>
          <div class="col-6">
            <label class="form-label form-label-sm mb-1">Statut</label>
            <div class="zs-select" id="cellStatut" data-placeholder="Présent"></div>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm mb-1">Notes</label>
          <textarea class="form-control form-control-sm" id="cellNotes" maxlength="200" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer py-1">
        <button class="btn btn-sm btn-danger" id="cellDeleteBtn"><i class="bi bi-trash"></i></button>
        <div class="ms-auto d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
          <button class="btn btn-sm btn-primary" id="cellSaveBtn">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Email planning modal -->
<div class="modal fade" id="emailPlanningModal" tabindex="-1">
  <div class="modal-dialog modal-info">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope"></i> Envoyer le planning par email</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Destinataires</label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="emailDest" id="emailDestAll" value="all" checked>
            <label class="form-check-label" for="emailDestAll">Tous les collaborateurs du planning</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="emailDest" id="emailDestModule" value="module">
            <label class="form-check-label" for="emailDestModule">Module sélectionné uniquement</label>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Message (optionnel)</label>
          <textarea class="form-control" id="emailMessage" rows="3" placeholder="Bonjour, voici le planning du mois..."></textarea>
        </div>
        <div id="emailProgress" class="d-hidden">
          <div class="progress mb-2">
            <div class="progress-bar progress-bar-striped progress-bar-animated progress-w0" id="emailProgressBar"></div>
          </div>
          <p class="text-muted text-center text-sm-085" id="emailProgressText">Envoi en cours...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="btnSendEmail">
          <i class="bi bi-send"></i> Envoyer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Proposals modal -->
<div class="modal fade" id="proposalsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="confirm-modal-icon icon-primary"><i class="bi bi-hand-thumbs-up"></i></div>
          <h6 class="modal-title mb-0">Propositions de planning</h6>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center modal-close-btn-themed" data-bs-dismiss="modal"><i class="bi bi-x-lg icon-close-sm"></i></button>
      </div>
      <div class="modal-body" id="proposalsContent">
        <div class="text-center text-muted py-3"><span class="admin-spinner"></span> Chargement...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<!-- Prompt IA modal -->
<div class="modal fade" id="promptModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-code-slash me-1"></i> Prompt IA envoyé</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body p-0">
        <div id="promptSummary" class="px-3 py-2 border-bottom prompt-summary"></div>
        <pre id="promptContent" class="m-0 p-3 prompt-content"></pre>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-outline-secondary btn-sm" id="btnCopyPrompt"><i class="bi bi-clipboard"></i> Copier</button>
        <button class="btn btn-dark btn-sm" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Reusable utility classes ── */
.d-hidden { display: none; }
/* .w-input-month replaced by pm-picker */
.w-input-view { width: 140px; }

/* Month picker */
.pm-picker { position: relative; }
.pm-picker-btn {
  display: flex; align-items: center; gap: 8px; padding: 5px 12px;
  border: 1px solid var(--cl-border); border-radius: 10px; background: var(--cl-surface);
  cursor: pointer; font-size: .88rem; font-family: inherit; transition: all .15s;
  min-width: 180px;
}
.pm-picker-btn:hover { border-color: var(--cl-border-hover); background: var(--cl-bg); }
.pm-picker-icon {
  width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
  font-size: .85rem; flex-shrink: 0;
}
.pm-picker-label { font-weight: 600; flex: 1; text-align: left; }
.pm-picker-chevron { font-size: .7rem; color: var(--cl-text-muted); transition: transform .2s; }
.pm-picker.open .pm-picker-chevron { transform: rotate(180deg); }
.pm-picker-dropdown {
  position: absolute; top: calc(100% + 6px); left: 0; z-index: 100;
  background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 14px;
  box-shadow: 0 8px 30px rgba(0,0,0,.1); padding: 12px; width: 280px;
  display: none;
}
.pm-picker.open .pm-picker-dropdown { display: block; }
.pm-picker-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.pm-nav-btn { background: none; border: none; cursor: pointer; width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--cl-text-secondary); transition: background .12s; }
.pm-nav-btn:hover { background: var(--cl-bg); }
.pm-nav-year { font-weight: 700; font-size: .95rem; }
.pm-picker-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.pm-month-btn {
  position: relative;
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 8px 4px; border: 1.5px solid transparent; border-radius: 10px;
  background: var(--cl-bg); cursor: pointer; transition: all .15s; font-family: inherit;
}
.pm-month-btn:hover { border-color: var(--cl-border-hover); background: var(--cl-surface); }
.pm-month-btn.active {
  border-color: #1a1a1a;
  background: var(--cl-surface);
  animation: pmHaloPulse 2.4s ease-in-out infinite;
}
@keyframes pmHaloPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(46,125,50,.45), 0 0 0 1px #1a1a1a; }
  50%      { box-shadow: 0 0 0 6px rgba(46,125,50,0),   0 0 0 1px #1a1a1a; }
}
.pm-month-btn.today::after { content: ''; position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%); width: 4px; height: 4px; border-radius: 50%; background: var(--cl-accent); }
.pm-month-icon { font-size: .9rem; }
.pm-month-label { font-size: .72rem; font-weight: 600; color: var(--cl-text-secondary); }
.pm-month-num {
  position: absolute; top: 4px; right: 5px;
  font-size: .62rem; font-weight: 700; line-height: 1;
  color: var(--cl-text-secondary); opacity: .55;
  font-variant-numeric: tabular-nums;
  padding: 2px 4px; border-radius: 5px;
  background: rgba(0,0,0,.04);
}
.pm-month-btn.active .pm-month-num { color: var(--cl-accent); opacity: 1; background: rgba(46,125,50,.1); }

/* IA Rule cards */
.gs-rule-card {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  border-radius: 10px; margin-bottom: 8px;
  background: var(--cl-bg, #F7F5F2); border: 1px solid var(--cl-border-light, #F0EDE8);
  transition: background .15s, border-color .15s, box-shadow .15s;
}
.gs-rule-card:hover {
  background: var(--cl-surface, #fff);
  border-color: var(--cl-border-hover, #D4D0CA);
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
/* User schedule sections */
.us-section { margin-bottom: 14px; padding: 12px; border-radius: 10px; background: var(--cl-bg, #F7F5F2); border: 1px solid var(--cl-border-light, #F0EDE8); }
.us-section-title { font-size: .82rem; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; color: var(--cl-text, #333); }
.us-section-title i { font-size: .9rem; }
.us-day-label {
    display: inline-flex; align-items: center; justify-content: center;
    width: 52px; height: 38px; border-radius: 10px; cursor: pointer; user-select: none;
    border: 1.5px solid var(--cl-border, #E8E5E0); font-size: .78rem; font-weight: 600;
    color: var(--cl-text-secondary, #888); transition: all .15s; background: var(--cl-surface, #fff);
}
.us-day-label input { display: none; }
.us-day-label:hover { border-color: var(--cl-primary, #1a1a1a); }
.us-day-active { background: var(--cl-primary, #1a1a1a) !important; color: #fff !important; border-color: var(--cl-primary, #1a1a1a) !important; }

.btn-outline-purple-custom { border-color: #D0C4D8; color: #5B4B6B; }
.btn-outline-purple-custom:hover { background: #D0C4D8; color: #5B4B6B; border-color: #D0C4D8; }
.modal-close-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.modal-close-btn-themed { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--cl-border, #e5e7eb); }
.icon-close-sm { font-size: .85rem; }
.text-sm-085 { font-size: 0.85rem; }
.text-sm-082 { font-size: 0.82rem; }
.text-sm-078 { font-size: .78rem; }
.text-sm-075 { font-size: .75rem; }
.text-sm-07 { font-size: .7rem; }
.text-sm-068 { font-size: 0.68rem; }
.prompt-summary { background: var(--cl-surface, #f9f7f4); font-size: .82rem; }
.prompt-content { white-space: pre-wrap; word-break: break-word; font-size: .78rem; line-height: 1.5; max-height: 65vh; overflow-y: auto; background: #1e1e2e; color: #cdd6f4; border-radius: 0; }
.gen-mode-card { cursor: pointer; transition: all .15s; }
.gen-mode-icon { font-size: 2rem; }
.gen-mode-icon-local { color: #6c757d; }
.gen-mode-icon-hybrid { color: #1a1a1a; }
.gen-mode-icon-ai { color: #9B51E0; }
.gen-mode-title { font-size: .9rem; }
.gen-mode-desc { font-size: .75rem; line-height: 1.3; }
.gen-rules-list { line-height: 1.8; color: #6c757d; }
.proposal-modal-narrow { max-width: 520px; }
.proposal-alert-bg { background: var(--cl-accent-bg); border-color: var(--cl-border) !important; }
.btn-accent { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: 8px; }
.btn-accent:hover { opacity: 0.9; color: #fff; }
.empty-icon-lg { font-size: 3rem; opacity: 0.3; }
.stats-scroll-300 { max-height: 300px; overflow-y: auto; }
.stats-scroll-200 { max-height: 200px; overflow-y: auto; }

/* ── Progress bar ── */
.progress-w0 { width: 0%; }
.progress-w10 { width: 10%; }
.progress-w100 { width: 100%; }

/* ── Coverage row font ── */
.coverage-cell-label { font-size: 0.68rem; }

/* ── Empty state icon ── */
.empty-icon-calendar { font-size: 3rem; opacity: 0.3; }

/* ── Proposal modal ── */
.proposal-modal-close-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; }
.proposal-alert-info { background: var(--cl-accent-bg); border-color: var(--cl-border) !important; }
.btn-proposal-ok { background: var(--cl-accent, #191918); color: #fff; font-weight: 600; border-radius: 8px; }
.btn-proposal-ok:hover { opacity: 0.9; color: #fff; }

/* ── Proposal cards (template) ── */
.prop-card-tmpl { border-color: var(--cl-border, #e5e7eb); border-radius: var(--cl-radius-sm, 10px); }
.prop-label-tmpl { color: var(--cl-text, #1A1A18); }
.prop-status-tmpl { font-size: .75rem; border-radius: 20px; background: var(--cl-accent-bg); color: var(--cl-text-secondary); }
.prop-status-dot-tmpl { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.prop-votes-block { font-size: 0.82rem; }
.prop-btns-block { font-size: 0.82rem; }

/* ── Vote detail table ── */
.vote-detail-table { font-size: 0.82rem; }
.vote-detail-text { font-size: 0.85rem; }
.vote-detail-hidden { display: none; }

/* ── Gen anim dynamic columns ── */
.gen-anim-grid-dynamic { gap: 1px; background: var(--cl-border, #e8e6dd); filter: blur(0.5px); opacity: .6; display: grid; }

/* ── Fullscreen helpers ── */
.fs-sidebar-hidden { display: none !important; }
.fs-topbar-hidden { display: none !important; }
.fs-main-no-margin { margin-left: 0 !important; }

/* ── Print heading ── */
.print-heading { text-align: center; margin-bottom: 10px; }

/* ── Purple button (dynamic inject replacement) ── */
.btn-purple { background: #D0C4D8; border-color: #D0C4D8; color: #5B4B6B; }
.btn-purple:hover { background: #C0B0CC; border-color: #C0B0CC; color: #5B4B6B; }

/* ── Gen mode card selected states ── */
.gen-mode-card-selected { box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
.gen-mode-card-selected-local { border-color: #198754 !important; background: #19875408; }
.gen-mode-card-selected-hybrid { border-color: #1a1a1a !important; background: #1a1a1a08; }
.gen-mode-card-selected-ai { border-color: #9B51E0 !important; background: #9B51E008; }

/* Proposal card styles */
.prop-card { border-color: var(--cl-border, #e5e7eb); border-radius: var(--cl-radius-sm, 10px); }
.prop-label { color: var(--cl-text, #1A1A18); }
.prop-status-badge { font-size: .75rem; border-radius: 20px; background: var(--cl-accent-bg); color: var(--cl-text-secondary); }
.prop-status-dot { width: 7px; height: 7px; border-radius: 50%; }
.prop-votes-pour { background: #bcd2cb; color: #2d4a43; font-weight: 600; }
.prop-votes-contre { background: #E2B8AE; color: #7B3B2C; font-weight: 600; }
.prop-progress-bar { height: 5px; background: var(--cl-accent-bg, #F5F3EE); border-radius: 3px; overflow: hidden; }
.prop-progress-fill { height: 100%; background: #2d4a43; border-radius: 3px; }
.prop-btn-action { border: 1px solid var(--cl-border); color: var(--cl-text-secondary); border-radius: 8px; transition: all .2s; }
.prop-btn-validate { background: #2d4a43; color: #fff; font-weight: 600; border-radius: 8px; transition: all .2s; }
.prop-btn-delete-color { border: 1px solid var(--cl-border); color: #7B3B2C; border-radius: 8px; transition: all .2s; }

/* Fullscreen hide helpers */
.fs-hidden { display: none !important; }

/* Proposal buttons hover */
.prop-btn-votes:hover { background:#2d4a43 !important; color:#fff !important; border-color:#2d4a43 !important; }
.prop-btn-delete:hover { background:#7B3B2C !important; color:#fff !important; border-color:#7B3B2C !important; }
.prop-btn-secondary:hover { background:var(--cl-accent,#191918) !important; color:#fff !important; border-color:var(--cl-accent,#191918) !important; }
.prop-btn-validate:hover { background:#1e3530 !important; }

/* Horaire card picker */
.ch-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.ch-card {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 8px 10px; border-radius: 10px; border: 1.5px solid var(--cl-border, #e5e7eb);
  background: #FAFAF8; cursor: pointer; transition: all .2s; min-width: 62px; text-align: center;
}
.ch-card:hover { border-color: #C4BBA8; background: #F5F3EE; }
.ch-card { position: relative; }
.ch-card.active { border-color: var(--cl-accent, #191918); background: #fff; box-shadow: 0 2px 8px rgba(25,25,24,.1); }
.ch-card .ch-check { position: absolute; top: -4px; right: -4px; width: 16px; height: 16px; border-radius: 50%; background: var(--cl-accent, #191918); color: #fff; font-size: .55rem; display: none; align-items: center; justify-content: center; }
.ch-card.active .ch-check { display: flex; }
.ch-card.active .ch-color { position: relative; }
.ch-card.active .ch-color::before,
.ch-card.active .ch-color::after {
  content: ''; position: absolute; inset: 0; border-radius: inherit;
  border: 2px solid var(--ch-glow);
  animation: chRing 2s ease-out infinite;
}
.ch-card.active .ch-color::after { animation-delay: 0.4s; }
@keyframes chRing {
  0% { transform: scale(1); opacity: .7; }
  100% { transform: scale(2.8); opacity: 0; }
}
.ch-color { width: 18px; height: 18px; border-radius: 6px; flex-shrink: 0; }
.ch-code { font-weight: 700; font-size: .82rem; line-height: 1; }
.ch-time { font-size: .65rem; color: var(--cl-text-muted, #999); line-height: 1; }
.ch-dur { font-size: .65rem; font-weight: 600; color: var(--cl-text-secondary, #666); line-height: 1; }

/* Remove content padding for planning page */
.admin-content { padding: 0.5rem 0.75rem !important; }

/* ── Planning grid ── */
.planning-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.25rem;
}
.planning-week-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0;
}
.planning-loading {
  text-align: center;
  padding: 0;
  color: var(--cl-text-muted);
}

/* ── Generate animation ── */
.gen-anim {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  border: 1px solid var(--cl-border, #e8e6dd);
}
.gen-anim-grid {
  display: grid;
  grid-template-columns: 120px repeat(31, 1fr);
  gap: 1px;
  background: var(--cl-border, #e8e6dd);
  filter: blur(0.5px);
  opacity: .6;
}
.gen-anim-grid .ga-hdr {
  background: var(--cl-surface, #f5f3ef);
  padding: 6px 4px;
  font-size: .6rem;
  font-weight: 700;
  color: var(--cl-text-muted, #999);
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
}
.gen-anim-grid .ga-hdr:first-child {
  text-align: left;
  padding-left: 8px;
}
.gen-anim-grid .ga-name {
  background: #fff;
  padding: 5px 8px;
  font-size: .62rem;
  color: #bbb;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
}
.gen-anim-grid .ga-cell {
  background: #fff;
  padding: 5px 2px;
  text-align: center;
  min-height: 28px;
  transition: background .4s, box-shadow .4s;
}
.gen-anim-grid .ga-cell .ga-code {
  display: inline-block;
  width: 22px;
  height: 16px;
  border-radius: 3px;
  opacity: 0;
  transform: scale(0.5);
  transition: opacity .35s, transform .35s;
}
.gen-anim-grid .ga-cell.filled .ga-code {
  opacity: 1;
  transform: scale(1);
}
.gen-anim-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 5;
  background: rgba(255,255,255,.3);
  backdrop-filter: blur(1px);
}
.gen-anim-overlay .ga-icon {
  font-size: 2rem;
  margin-bottom: .75rem;
  animation: ga-pulse 1.5s ease-in-out infinite;
}
.gen-anim-overlay .ga-label {
  font-size: .95rem;
  font-weight: 600;
  color: var(--cl-text, #1a1a1a);
  margin-bottom: .25rem;
}
.gen-anim-overlay .ga-sub {
  font-size: .78rem;
  color: var(--cl-text-muted, #888);
}
@keyframes ga-pulse {
  0%, 100% { transform: scale(1); opacity: .8; }
  50% { transform: scale(1.15); opacity: 1; }
}

/* Stats modal */
.st-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
.st-card {
  padding: 10px 12px; border-radius: 10px; text-align: center;
}
.st-card .st-val { font-size: 1.2rem; font-weight: 700; line-height: 1.2; }
.st-card .st-lbl { font-size: .68rem; color: inherit; opacity: .7; margin-top: 2px; }
.st-card-teal   { background: #bcd2cb; color: #2d4a43; }
.st-card-blue   { background: #B8C9D4; color: #3B4F6B; }
.st-card-orange { background: #D4C4A8; color: #6B5B3E; }
.st-card-red    { background: #E2B8AE; color: #7B3B2C; }
.st-card-green  { background: #bcd2cb; color: #2d4a43; }

.st-section { margin-bottom: 14px; }
.st-section-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--cl-text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.st-scroll { max-height: 280px; overflow-y: auto; overflow-x: auto; border: 1px solid var(--cl-border); border-radius: 14px; background: var(--cl-surface); }
.st-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
.st-table thead th {
  padding: 9px 12px; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
  background: var(--cl-bg, #F7F5F2); color: var(--cl-text-muted); border-bottom: 1.5px solid var(--cl-border);
  position: sticky; top: 0; z-index: 1;
}
.st-table thead th:first-child { border-top-left-radius: 14px; }
.st-table thead th:last-child { border-top-right-radius: 14px; }
.st-table tbody td { padding: 7px 12px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.st-table tbody tr:last-child td { border-bottom: none; }
.st-table tbody tr:last-child td:first-child { border-bottom-left-radius: 14px; }
.st-table tbody tr:last-child td:last-child { border-bottom-right-radius: 14px; }
.st-table tbody tr:hover td { background: rgba(25,25,24,.02); }
.st-table .st-name { font-weight: 600; }
.st-ecart-over  { color: #7B3B2C; font-weight: 600; }
.st-ecart-under { color: #3B4F6B; font-weight: 600; }
.st-ecart-ok    { color: #2d4a43; }
.st-gap-row td { background: #FEF3C7; }
.st-gap-val { color: #7B3B2C; font-weight: 700; }

/* Responsive */
@media (max-width: 768px) {
  .planning-toolbar { flex-direction: column; align-items: stretch; }
  .tr-grid { font-size: 0.68rem; }
  .tr-grid .dc { min-width: 28px; }
}
</style>

<!-- Generate IA modal -->
<div class="modal fade" id="generateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-info">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-calendar2-plus me-1"></i> Générer le planning</h6>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body pt-0">

        <!-- Mode selection cards -->
        <p class="text-muted small mb-3">Choisissez le mode de génération :</p>
        <div class="row g-3 mb-3">
          <!-- Algorithme local -->
          <div class="col-md-4">
            <div class="card h-100 gen-mode-card border-2" data-gen-mode="local" role="button">
              <div class="card-body text-center p-3">
                <div class="mb-2 gen-mode-icon gen-mode-icon-local"><i class="bi bi-cpu"></i></div>
                <h6 class="card-title mb-1 gen-mode-title">Algorithme local</h6>
                <p class="text-muted mb-2 gen-mode-desc">Rapide et gratuit. Génère le planning avec l'algorithme EMS interne.</p>
                <span class="badge bg-success-subtle text-success">Gratuit</span>
                <span class="badge bg-secondary-subtle text-secondary">~1s</span>
              </div>
            </div>
          </div>
          <!-- Hybride -->
          <div class="col-md-4">
            <div class="card h-100 gen-mode-card border-2" data-gen-mode="hybrid" role="button">
              <div class="card-body text-center p-3">
                <div class="mb-2 gen-mode-icon gen-mode-icon-hybrid"><i class="bi bi-stars"></i></div>
                <h6 class="card-title mb-1 gen-mode-title">Hybride</h6>
                <p class="text-muted mb-2 gen-mode-desc">Algorithme local + optimisation IA. Résout les conflits et équilibre les heures.</p>
                <span class="badge bg-primary-subtle text-primary">~$0.01</span>
                <span class="badge bg-secondary-subtle text-secondary">~10s</span>
              </div>
            </div>
          </div>
          <!-- IA directe -->
          <div class="col-md-4">
            <div class="card h-100 gen-mode-card border-2" data-gen-mode="ai" role="button">
              <div class="card-body text-center p-3">
                <div class="mb-2 gen-mode-icon gen-mode-icon-ai"><i class="bi bi-robot"></i></div>
                <h6 class="card-title mb-1 gen-mode-title">IA directe</h6>
                <p class="text-muted mb-2 gen-mode-desc">L'IA génère tout le planning. Plus lent, résultats créatifs.</p>
                <span class="badge bg-warning-subtle text-warning">~$0.05</span>
                <span class="badge bg-secondary-subtle text-secondary">~30s</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Provider info (shown for hybrid/ai modes) -->
        <div id="genProviderInfo" class="alert alert-light border py-2 small mb-3 d-hidden">
          <div class="d-flex align-items-center justify-content-between">
            <span><i class="bi bi-plug me-1"></i> Provider : <strong id="genProviderName">—</strong> · Modèle : <strong id="genModelName">—</strong></span>
            <a href="<?= admin_url('config-ia') ?>" class="text-decoration-none small">Configurer <i class="bi bi-gear"></i></a>
          </div>
        </div>

        <!-- Règles EMS recap -->
        <div id="genRulesCollapse" class="mb-3">
          <a class="small text-muted text-decoration-none" data-bs-toggle="collapse" href="#genRulesDetail" role="button">
            <i class="bi bi-info-circle me-1"></i>Règles de génération <i class="bi bi-chevron-down text-sm-07"></i>
          </a>
          <div class="collapse mt-2" id="genRulesDetail">
            <ul class="small mb-0 gen-rules-list">
              <li><strong>AS par étage</strong> : 2 minimum, 7j/7, max 3 jours consécutifs, horaires D1/D3/D4/S3/S4</li>
              <li><strong>INF + ASSC</strong> : 1 par module, 7j/7</li>
              <li><strong>Couverture</strong> : 7h–20h30, paires matin/soir par étage</li>
              <li><strong>Heures hebdo</strong> : respect du taux contractuel (±2h tolérance)</li>
              <li><strong>Absences & désirs</strong> : congés validés et jours off respectés</li>
            </ul>
          </div>
        </div>

        <!-- Module filter -->
        <div class="mb-3">
          <label class="form-label small fw-bold">Module à générer</label>
          <div class="zs-select" id="genModuleFilter" data-placeholder="Tous les modules"></div>
        </div>

        <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-exclamation-triangle"></i> Les assignations existantes du module sélectionné seront <strong>remplacées</strong>.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm me-auto" id="genSettingsBtn"><i class="bi bi-sliders"></i> Paramètres</button>
        <button class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-dark btn-sm" id="genConfirmBtn" disabled>
          <i class="bi bi-play-fill"></i> <span id="genConfirmLabel">Sélectionnez un mode</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Paramètres Génération (Règles IA) -->
<div class="modal fade" id="genSettingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gsModalTitle"><i class="bi bi-sliders"></i> Règles de génération</h5>
        <button type="button" class="confirm-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" id="genSettingsBody" style="max-height:65vh;overflow-y:auto">
        <p class="text-muted small">Chargement...</p>
      </div>
      <div class="modal-footer" id="gsModalFooter">
        <a href="<?= admin_url('config-ia') ?>" class="btn btn-sm btn-outline-secondary me-auto"><i class="bi bi-gear"></i> Config IA avancée</a>
        <button class="btn btn-sm btn-primary" id="gsAddRuleBtn"><i class="bi bi-plus-lg"></i> Ajouter une règle</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Proposition -->
<div class="modal fade" id="proposalLabelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered proposal-modal-narrow">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="confirm-modal-icon icon-primary"><i class="bi bi-bookmark-plus"></i></div>
          <h6 class="modal-title mb-0">Nouvelle proposition</h6>
        </div>
        <button type="button" class="btn btn-sm btn-light ms-auto d-flex align-items-center justify-content-center proposal-modal-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg icon-close-sm"></i></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light border d-flex align-items-start gap-2 py-2 mb-3 proposal-alert-info">
          <i class="bi bi-info-circle text-muted mt-1"></i>
          <span class="small text-muted">Les collaborateurs pourront consulter et voter sur cette proposition de planning.</span>
        </div>
        <label class="form-label small fw-bold" for="proposalLabelInput">Titre de la proposition</label>
        <input type="text" class="form-control" id="proposalLabelInput" placeholder="Ex: Choix 1 planning janvier 2026">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-proposal-ok" id="proposalLabelOk">
          <i class="bi bi-check-lg"></i> Créer la proposition
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
// ── Planning Admin Controller ──
(function() {
    let planning = null;
    let assignations = [];
    let absencesData = [];
    let absIdx = {};
    let formationsData = [];
    let formIdx = {}; // userId_date → formation info
    let refs = {
        success: true,
        users: <?= json_encode(array_values($planningUsers), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
        horaires: <?= json_encode(array_values($planningHoraires), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
        modules: <?= json_encode(array_values($planningModules), JSON_HEX_TAG | JSON_HEX_APOS) ?>,
        fonctions: <?= json_encode(array_values($planningFonctions), JSON_HEX_TAG | JSON_HEX_APOS) ?>
    };
    let currentWeekStart = null;
    let cellModal = null;
    let statsModal = null;
    let proposalLabelModal = null;
    let isFullscreen = false;

    // ── Init ──
    // ── Month Picker ──
    const pmMonthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const pmMonthIcons = ['❄️','💧','🌱','🌸','🌿','☀️','🌻','🏖️','🍂','🎃','🍁','🎄'];
    const pmMonthColors = [
        '#B8C9D4','#B8C9D4','#bcd2cb','#D0C4D8','#bcd2cb','#D4C4A8',
        '#D4C4A8','#E2B8AE','#D4C4A8','#E2B8AE','#D4C4A8','#B8C9D4'
    ];
    const pmMonthTextColors = [
        '#3B4F6B','#3B4F6B','#2d4a43','#5B4B6B','#2d4a43','#6B5B3E',
        '#6B5B3E','#7B3B2C','#6B5B3E','#7B3B2C','#6B5B3E','#3B4F6B'
    ];
    let pmYear = new Date().getFullYear();
    let pmOpen = false;

    function pmSetValue(yyyy, mm) {
        const val = `${yyyy}-${String(mm).padStart(2,'0')}`;
        document.getElementById('planningMois').value = val;
        const idx = mm - 1;
        document.getElementById('pmPickerIcon').textContent = pmMonthIcons[idx];
        document.getElementById('pmPickerIcon').style.background = pmMonthColors[idx];
        document.getElementById('pmPickerIcon').style.color = pmMonthTextColors[idx];
        document.getElementById('pmPickerLabel').textContent = pmMonthNames[idx] + ' ' + yyyy;
        pmRenderGrid();
    }

    function pmRenderGrid() {
        const curVal = document.getElementById('planningMois').value;
        const now = new Date();
        const todayKey = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
        document.getElementById('pmYear').textContent = pmYear;
        const grid = document.getElementById('pmGrid');
        grid.innerHTML = '';
        for (let m = 1; m <= 12; m++) {
            const key = `${pmYear}-${String(m).padStart(2,'0')}`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pm-month-btn' + (key === curVal ? ' active' : '') + (key === todayKey ? ' today' : '');
            btn.innerHTML = '<span class="pm-month-num">' + String(m).padStart(2,'0') + '</span>'
                + '<span class="pm-month-icon" style="background:' + pmMonthColors[m-1] + ';color:' + pmMonthTextColors[m-1] + ';width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.8rem">' + pmMonthIcons[m-1] + '</span>'
                + '<span class="pm-month-label">' + pmMonthNames[m-1].substring(0,3) + '</span>';
            btn.addEventListener('click', () => {
                pmSetValue(pmYear, m);
                pmClose();
                reload();
            });
            grid.appendChild(btn);
        }
    }

    function pmToggle() { pmOpen ? pmClose() : pmOpenFn(); }
    function pmOpenFn() {
        pmOpen = true;
        document.getElementById('pmPicker').classList.add('open');
        pmRenderGrid();
        setTimeout(() => document.addEventListener('click', pmOutsideClick), 10);
    }
    function pmClose() {
        pmOpen = false;
        document.getElementById('pmPicker').classList.remove('open');
        document.removeEventListener('click', pmOutsideClick);
    }
    function pmOutsideClick(e) {
        if (!document.getElementById('pmPicker').contains(e.target)) pmClose();
    }

    document.getElementById('pmPickerBtn').addEventListener('click', pmToggle);
    document.getElementById('pmPrevYear').addEventListener('click', (e) => { e.stopPropagation(); pmYear--; pmRenderGrid(); });
    document.getElementById('pmNextYear').addEventListener('click', (e) => { e.stopPropagation(); pmYear++; pmRenderGrid(); });

    async function initPlanningPage() {
        const now = new Date();
        const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        pmYear = nextMonth.getFullYear();
        pmSetValue(nextMonth.getFullYear(), nextMonth.getMonth() + 1);

        cellModal = new bootstrap.Modal(document.getElementById('cellModal'));
        statsModal = new bootstrap.Modal(document.getElementById('statsModal'));
        proposalLabelModal = new bootstrap.Modal(document.getElementById('proposalLabelModal'));

        // Event listeners (planningMois change is triggered by pmSetValue + reload)
        zerdaSelect.init(document.getElementById('planningModuleFilter'), [{value:'', label:'Tous les modules'}], { value: '', onSelect: renderGrid });
        zerdaSelect.init(document.getElementById('planningViewMode'), [{value:'week', label:'Vue semaine'},{value:'month', label:'Vue mois'}], { value: 'month', onSelect: renderGrid });
        document.getElementById('btnCreatePlanning').addEventListener('click', createPlanning);
        document.getElementById('btnGeneratePlanning').addEventListener('click', generatePlanning);
        document.getElementById('btnStatsPlanning').addEventListener('click', showStats);
        document.getElementById('btnClearPlanning').addEventListener('click', clearPlanning);
        document.getElementById('btnProvisoire').addEventListener('click', () => finalize('provisoire'));
        document.getElementById('btnFinaliser').addEventListener('click', () => finalize('final'));
        document.getElementById('prevWeek').addEventListener('click', () => moveWeek(-1));
        document.getElementById('nextWeek').addEventListener('click', () => moveWeek(1));
        document.getElementById('cellSaveBtn').addEventListener('click', saveCell);
        document.getElementById('cellDeleteBtn').addEventListener('click', deleteCell);
        document.getElementById('btnFullscreen').addEventListener('click', toggleFullscreen);
        document.getElementById('btnPrintPlanning').addEventListener('click', printPlanning);
        document.getElementById('btnExportPdf').addEventListener('click', exportPdf);
        document.getElementById('btnEmailPlanning').addEventListener('click', emailPlanning);
        document.getElementById('btnExportCsv').addEventListener('click', exportCsv);
        document.getElementById('btnViewPrompt').addEventListener('click', showPromptModal);
        document.getElementById('btnCopyPrompt').addEventListener('click', () => {
            const text = document.getElementById('promptContent').textContent;
            navigator.clipboard.writeText(text).then(() => showToast('Prompt copié', 'success'));
        });
        document.getElementById('btnSaveProposal').addEventListener('click', saveProposal);
        document.getElementById('btnViewProposals').addEventListener('click', viewProposals);

        // ESC to exit fullscreen (only if no modal is currently open)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isFullscreen && !document.querySelector('.modal.show')) toggleFullscreen();
        });

        // Refs already injected by PHP
        populateFilters();
        buildModuleSwitch();

        await reload();
    }

    function populateFilters() {
        const modOpts = [{value:'', label:'Tous les modules'}];
        (refs.modules || []).forEach(m => {
            modOpts.push({value: m.id, label: m.code + ' — ' + m.nom});
        });
        zerdaSelect.destroy(document.getElementById('planningModuleFilter'));
        zerdaSelect.init(document.getElementById('planningModuleFilter'), modOpts, { value: '', onSelect: renderGrid });

        // Cell modal — horaire cards
        const grid = document.getElementById('cellHoraireGrid');
        grid.innerHTML = (refs.horaires || []).map(h => {
            const color = h.couleur || '#ccc';
            return `<label class="ch-card" data-horaire-id="${h.id}">
                <input type="radio" name="cellHoraireRadio" value="${h.id}" class="d-none">
                <span class="ch-check"><i class="bi bi-check-lg"></i></span>
                <span class="ch-color" style="background:${color};--ch-glow:${color}"></span>
                <span class="ch-code">${escapeHtml(h.code)}</span>
                <span class="ch-time">${escapeHtml(h.heure_debut?.substring(0,5) || '')}–${escapeHtml(h.heure_fin?.substring(0,5) || '')}</span>
                <span class="ch-dur">${h.duree_effective}h</span>
            </label>`;
        }).join('');

        grid.querySelectorAll('.ch-card').forEach(card => {
            card.addEventListener('click', () => {
                grid.querySelectorAll('.ch-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            });
        });

        // Module zerdaSelect
        zerdaSelect.init('#cellModule', [
            { value: '', label: '— Aucun —' },
            ...(refs.modules || []).map(m => ({ value: m.id, label: m.code + ' — ' + m.nom }))
        ]);

        // Statut zerdaSelect
        zerdaSelect.init('#cellStatut', [
            { value: 'present', label: 'Présent', dot: '#16a34a' },
            { value: 'absent', label: 'Absent', dot: '#dc2626' },
            { value: 'repos', label: 'Repos', dot: '#6b7280' },
            { value: 'remplace', label: 'Remplacé', dot: '#f59e0b' },
            { value: 'interim', label: 'Intérim', dot: '#8b5cf6' },
            { value: 'entraide', label: 'Entraide', dot: '#0ea5e9' },
            { value: 'vacant', label: 'Vacant', dot: '#ef4444' },
        ], { dots: true, value: 'present' });
    }

    async function reload() {
        const mois = document.getElementById('planningMois').value;
        if (!mois) return;

        document.getElementById('planningContent').innerHTML =
            '<div class="planning-loading"><span class="admin-spinner"></span> Chargement...</div>';

        const res = await adminApiPost('admin_get_planning', { mois });
        planning = res.planning || null;
        assignations = res.assignations || [];
        absencesData = res.absences || [];

        // Build absence lookup: userId_date → type
        absIdx = {};
        absencesData.forEach(a => {
            const start = new Date(a.date_debut + 'T00:00:00');
            const end = new Date(a.date_fin + 'T00:00:00');
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = a.user_id + '_' + fmtISO(d);
                absIdx[key] = a.type;
            }
        });

        // Build formation lookup: userId_date → details
        formationsData = res.formations || [];
        formIdx = {};
        formationsData.forEach(f => {
            const start = new Date(f.date_debut + 'T00:00:00');
            const end = new Date((f.date_fin || f.date_debut) + 'T00:00:00');
            const days = Math.max(1, Math.round((end - start) / 86400000) + 1);
            const heuresJour = f.duree_heures > 0 ? Math.round((f.duree_heures / days) * 10) / 10 : 8.4;
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = f.user_id + '_' + fmtISO(d);
                formIdx[key] = {
                    titre: f.titre,
                    type: f.type,
                    is_obligatoire: parseInt(f.is_obligatoire) === 1,
                    duree_heures: parseFloat(f.duree_heures) || 0,
                    heures_jour: heuresJour,
                    date_debut: f.date_debut,
                    date_fin: f.date_fin || f.date_debut,
                    nb_jours: days,
                    heure_debut: f.heure_debut,
                    heure_fin: f.heure_fin,
                    lieu: f.lieu,
                    modalite: f.modalite,
                    formation_id: f.formation_id,
                    contact: f.contact_inscription_email,
                    statut: f.participant_statut,
                };
            }
        });

        updateToolbar();

        // Default week start to 1st Monday of month
        const first = new Date(mois + '-01T00:00:00');
        const dow = first.getDay() || 7;
        currentWeekStart = new Date(first);
        currentWeekStart.setDate(first.getDate() - dow + 1);

        renderGrid();
    }

    function updateToolbar() {
        const statusEl = document.getElementById('planningStatus');
        const finalGrp = document.getElementById('finalizeGroup');
        if (!planning) {
            statusEl.innerHTML = '<span class="badge bg-secondary">Aucun planning</span>';
            finalGrp.classList.add('d-hidden');
            return;
        }
        const cls = planning.statut === 'final' ? 'success' : planning.statut === 'provisoire' ? 'info' : 'secondary';
        statusEl.innerHTML = `<span class="badge bg-${cls}">${escapeHtml(planning.statut)}</span>
            <small class="text-muted ms-1">${assignations.length} assignations</small>`;
        finalGrp.classList.toggle('d-hidden', planning.statut === 'final');
    }

    // ── Render grid ──
    function renderGrid() {
        const content = document.getElementById('planningContent');
        if (!planning) {
            content.innerHTML = `<div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-plus empty-icon-calendar"></i>
                <p class="mt-2">Aucun planning pour ce mois. Cliquez sur <strong>Créer</strong>.</p>
            </div>`;
            return;
        }

        const mois = document.getElementById('planningMois').value;
        const moduleFilter = zerdaSelect.getValue('#planningModuleFilter');
        const viewMode = zerdaSelect.getValue('#planningViewMode');

        // Build days array
        const firstDay = new Date(mois + '-01T00:00:00');
        const daysInMonth = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0).getDate();
        let days = [];

        if (viewMode === 'week') {
            document.getElementById('weekNav').classList.remove('d-hidden');
            for (let i = 0; i < 7; i++) {
                const d = new Date(currentWeekStart);
                d.setDate(d.getDate() + i);
                // Only include days in this month
                if (d.getMonth() === firstDay.getMonth() && d.getFullYear() === firstDay.getFullYear()) {
                    days.push(d);
                }
            }
            document.getElementById('weekLabel').textContent =
                `Semaine du ${fmtDate(currentWeekStart)}`;
        } else {
            document.getElementById('weekNav').classList.add('d-hidden');
            for (let d = 1; d <= daysInMonth; d++) {
                days.push(new Date(firstDay.getFullYear(), firstDay.getMonth(), d));
            }
        }

        // Index assignations by user+date
        const aIdx = {};
        assignations.forEach(a => {
            const key = a.user_id + '_' + a.date_jour;
            aIdx[key] = a;
        });

        // Filter users based on active tab
        const filterType = document.getElementById('planningFilterType').value;
        const filterValue = document.getElementById('planningFilterValue').value;
        let users = refs.users || [];

        if (filterType === 'module' && filterValue) {
            users = users.filter(u => (u.module_ids || '').split(',').includes(filterValue));
        } else if (filterType === 'fonction' && filterValue) {
            users = users.filter(u => u.fonction_code === filterValue);
        } else if (filterType === 'fonctions' && filterValue) {
            const codes = filterValue.split(',');
            users = users.filter(u => codes.includes(u.fonction_code));
        } else if (moduleFilter) {
            // Backward compat
            users = users.filter(u => (u.module_ids || '').split(',').includes(moduleFilter));
        }

        // Group by principal module
        const moduleGroups = {};
        const moduleOrder = {};
        (refs.modules || []).forEach((m, i) => { moduleOrder[m.id] = i; });

        users.forEach(u => {
            const principalMod = (u.module_ids || '').split(',')[0] || 'none';
            if (!moduleGroups[principalMod]) moduleGroups[principalMod] = [];
            moduleGroups[principalMod].push(u);
        });

        // Sort groups by module order
        const sortedGroups = Object.entries(moduleGroups).sort((a, b) => {
            return (moduleOrder[a[0]] ?? 99) - (moduleOrder[b[0]] ?? 99);
        });

        // Day names
        const dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        // Horaire color map
        const hColorMap = {};
        (refs.horaires || []).forEach(h => { hColorMap[h.id] = h.couleur; });

        // Build HTML — uses .tr-grid common classes (same style as répartition)
        let html = '<div class="tr-grid-wrap"><table class="tr-grid">';

        // Header
        html += '<thead><tr><th class="col-user">Collaborateur</th><th class="col-taux">%</th>';
        days.forEach(d => {
            const dow = d.getDay();
            const isWe = (dow === 0 || dow === 6);
            html += `<th class="${isWe ? 'th-we' : ''}">${dayNames[dow]}<br>${d.getDate()}</th>`;
        });
        html += '<th class="col-total">Heures</th></tr></thead>';

        html += '<tbody>';

        sortedGroups.forEach(([modId, groupUsers]) => {
            const mod = (refs.modules || []).find(m => m.id === modId);
            const modLabel = mod ? `${mod.code} — ${mod.nom}` : 'Sans module';

            // Module header row
            html += `<tr class="mod-sep"><td colspan="${days.length + 3}">${escapeHtml(modLabel)} <span class="badge bg-light text-dark ms-2">${groupUsers.length} emp.</span></td></tr>`;

            // Group by fonction
            const byFn = {};
            groupUsers.forEach(u => {
                const fc = u.fonction_code || 'Autre';
                if (!byFn[fc]) byFn[fc] = [];
                byFn[fc].push(u);
            });

            for (const [fc, fUsers] of Object.entries(byFn)) {
                html += `<tr class="fn-sep"><td colspan="${days.length + 3}">${escapeHtml(fc)}</td></tr>`;

                fUsers.forEach(u => {
                    let totalHours = 0;

                    html += `<tr><td class="col-user">
                        <span class="fn-badge">${escapeHtml(u.fonction_code || '?')}</span>
                        <span class="col-user-link" data-user-id="${u.id}">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</span>
                    </td><td class="col-taux">${Math.round(u.taux)}</td>`;

                    days.forEach(d => {
                        const dateStr = fmtISO(d);
                        const key = u.id + '_' + dateStr;
                        const a = aIdx[key];
                        const dow = d.getDay();
                        const isWe = (dow === 0 || dow === 6);
                        let cellClass = 'dc' + (isWe ? ' td-we' : '');
                        let cellContent = '';

                        // Check absence for this cell
                        const absType = absIdx[key];

                        if (absType) {
                            // Absence icon by type
                            const absIcons = {
                                vacances: '<img src="/spocspace/assets/webp/vacances_1.webp" class="dc-abs-icon" title="Vacances">',
                                maladie: '<i class="bi bi-thermometer-half dc-abs-bi" style="color:#DC2626" title="Maladie"></i>',
                                accident: '<i class="bi bi-bandaid dc-abs-bi" style="color:#E65100" title="Accident"></i>',
                                conge_special: '<i class="bi bi-calendar-heart dc-abs-bi" style="color:#7C3AED" title="Congé spécial"></i>',
                                formation: '<i class="bi bi-mortarboard dc-abs-bi" style="color:#1565C0" title="Formation"></i>',
                                maternite: '<i class="bi bi-balloon-heart dc-abs-bi" style="color:#EC4899" title="Maternité"></i>',
                                paternite: '<i class="bi bi-balloon dc-abs-bi" style="color:#3B82F6" title="Paternité"></i>',
                                autre: '<i class="bi bi-dash-circle dc-abs-bi" style="color:#888" title="Autre"></i>',
                            };
                            cellClass += ' dc-absence';
                            cellContent = absIcons[absType] || absIcons.autre;
                        } else if (a) {
                            if (a.statut === 'formation') {
                                cellClass += ' dc-formation';
                                const rawNotes = a.notes || '';
                                const hMatch = rawNotes.match(/\[([\d.,]+)h\]\s*$/);
                                const heuresJour = hMatch ? parseFloat(hMatch[1].replace(',', '.')) : 8.4;
                                cellContent = `<span class="dc-formation-icon" data-form-uid="${u.id}" data-form-date="${dateStr}"><i class="bi bi-mortarboard-fill"></i></span>`;
                                totalHours += heuresJour;
                            } else {
                                if (a.statut === 'absent') cellClass += ' dc-absent';
                                else if (a.statut === 'repos') cellClass += ' dc-repos';
                                else if (a.statut === 'vacant') cellClass += ' dc-vacant';

                                if (a.horaire_code) {
                                    const color = a.couleur || hColorMap[a.horaire_type_id] || '#6c757d';
                                    const desirIcon = (a.notes && a.notes.includes('desir')) ? '<i class="bi bi-emoji-smile" style="font-size:.55rem;color:#e8a838;position:absolute;top:-2px;right:-2px"></i>' : '';
                                    cellContent = `<span class="shift-code" style="background:${color};position:relative">${escapeHtml(a.horaire_code)}${desirIcon}</span>`;
                                    if (a.statut === 'present') {
                                        const h = (refs.horaires || []).find(h => h.id === a.horaire_type_id);
                                        if (h) totalHours += parseFloat(h.duree_effective) || 0;
                                    }
                                } else if (a.statut !== 'present') {
                                    const statusIcons = { repos: '·', absent: '✕', vacant: '?', remplace: 'R', interim: 'I', entraide: 'E' };
                                    cellContent = `<span class="text-muted">${statusIcons[a.statut] || a.statut.charAt(0).toUpperCase()}</span>`;
                                }
                            }
                        }

                        html += `<td class="${cellClass}" data-uid="${u.id}" data-date="${dateStr}"
                                     title="${escapeHtml(u.prenom + ' ' + u.nom)} — ${dateStr}">${cellContent}</td>`;
                    });

                    // Hours total
                    const targetHours = Math.round(21.7 * 8.4 * (u.taux / 100));
                    const ecart = Math.round((totalHours - targetHours) * 10) / 10;
                    let hoursClass = 'hours-ok';
                    if (ecart < -10) hoursClass = 'hours-under';
                    else if (ecart > 10) hoursClass = 'hours-over';

                    html += `<td class="col-total"><span class="${hoursClass}">${Math.round(totalHours)}h</span>
                        <br><small class="text-muted">${targetHours}h · ${ecart > 0 ? '+' : ''}${ecart}</small></td>`;

                    html += '</tr>';
                });
            }

            // Coverage summary row for this module
            if (mod) {
                html += `<tr class="coverage-row"><td class="col-user coverage-cell-label"><i class="bi bi-shield-check"></i> Couverture</td><td class="col-taux"></td>`;
                days.forEach(d => {
                    const dateStr = fmtISO(d);
                    let present = 0;
                    groupUsers.forEach(u => {
                        const a = aIdx[u.id + '_' + dateStr];
                        if (a && a.statut === 'present') present++;
                    });
                    const cls = present === 0 ? 'coverage-bad' : present < 3 ? 'coverage-warn' : 'coverage-ok';
                    html += `<td><span class="${cls}">${present}</span></td>`;
                });
                html += '<td class="col-total"></td></tr>';
            }
        });

        html += '</tbody></table></div>';

        if (users.length === 0) {
            html = `<div class="text-center py-4 text-muted">
                <p>Aucun collaborateur à afficher pour ce filtre.</p>
            </div>`;
        }

        content.innerHTML = html;

        // Add click handlers on cells
        content.querySelectorAll('.dc').forEach(cell => {
            cell.addEventListener('click', () => openCellModal(cell));
        });

        // Hover popover on formation cells
        content.querySelectorAll('.dc-formation-icon').forEach(el => {
            el.addEventListener('mouseenter', e => showFormationPopover(el));
            el.addEventListener('mouseleave', e => hideFormationPopoverWithDelay());
        });

        // Click on user cell → open user-detail + row hover
        content.querySelectorAll('td.col-user').forEach(td => {
            const link = td.querySelector('.col-user-link');
            if (!link) return;
            td.classList.add('col-user-clickable');
            const tr = td.closest('tr');
            td.addEventListener('mouseenter', () => tr.classList.add('row-hover'));
            td.addEventListener('mouseleave', () => tr.classList.remove('row-hover'));
            td.addEventListener('click', (e) => {
                e.stopPropagation();
                const uid = link.dataset.userId;
                if (uid) window.location.href = '/spocspace/admin/user-detail/' + encodeURIComponent(uid);
            });
        });

        // Drag-to-scroll on grid
        const gridWrap = content.querySelector('.tr-grid-wrap');
        if (gridWrap) {
            let gDown = false, gStartX, gScrollL;
            gridWrap.addEventListener('mousedown', e => {
                if (e.target.closest('a,button,input,select,.col-user,.dc')) return;
                gDown = true; gStartX = e.pageX - gridWrap.offsetLeft; gScrollL = gridWrap.scrollLeft;
                gridWrap.classList.add('grabbing');
            });
            gridWrap.addEventListener('mousemove', e => {
                if (!gDown) return; e.preventDefault();
                gridWrap.scrollLeft = gScrollL - (e.pageX - gridWrap.offsetLeft - gStartX);
            });
            const gStop = () => { gDown = false; gridWrap.classList.remove('grabbing'); };
            gridWrap.addEventListener('mouseup', gStop);
            gridWrap.addEventListener('mouseleave', gStop);
        }
    }

    // ── Cell modal ──
    function openCellModal(cell) {
        // DEV MODE: autoriser modification même finalisé — TODO: réactiver après dev
        // if (planning?.statut === 'final') {
        //     showToast('Planning finalisé — lecture seule', 'error');
        //     return;
        // }
        const userId = cell.dataset.uid;
        const date = cell.dataset.date;
        const key = userId + '_' + date;
        const a = assignations.find(x => x.user_id === userId && x.date_jour === date);
        const user = (refs.users || []).find(u => u.id === userId);

        document.getElementById('cellModalTitle').textContent =
            `${user ? user.prenom + ' ' + user.nom : ''} — ${date}`;
        document.getElementById('cellUserId').value = userId;
        document.getElementById('cellDate').value = date;
        // Store updated_at for optimistic locking
        document.getElementById('cellUserId').dataset.updatedAt = a?.updated_at || '';

        // Set horaire card active
        document.querySelectorAll('#cellHoraireGrid .ch-card').forEach(c => {
            const radio = c.querySelector('input');
            const isActive = radio.value === (a?.horaire_type_id || '');
            c.classList.toggle('active', isActive);
            radio.checked = isActive;
        });
        const defaultModule = a?.module_id || (user?.module_ids?.split(',')[0]) || '';
        zerdaSelect.setValue('#cellModule', defaultModule);
        zerdaSelect.setValue('#cellStatut', a?.statut || 'present');
        document.getElementById('cellNotes').value = a?.notes || '';

        document.getElementById('cellDeleteBtn').classList.toggle('d-hidden', !a);

        cellModal.show();
    }

    async function saveCell() {
        const userId = document.getElementById('cellUserId').value;
        const date = document.getElementById('cellDate').value;
        const horaireRadio = document.querySelector('input[name="cellHoraireRadio"]:checked');
        const horaireId = horaireRadio?.value || null;
        const moduleId = zerdaSelect.getValue('#cellModule') || null;
        const statut = zerdaSelect.getValue('#cellStatut') || 'present';
        const notes = document.getElementById('cellNotes').value;

        const expectedUpdatedAt = document.getElementById('cellUserId').dataset.updatedAt || null;

        const res = await adminApiPost('admin_save_assignation', {
            planning_id: planning.id,
            user_id: userId,
            date_jour: date,
            horaire_type_id: horaireId,
            module_id: moduleId,
            statut: statut,
            notes: notes,
            expected_updated_at: expectedUpdatedAt || undefined,
        });

        if (res.conflict) {
            showToast('⚠ Conflit : cette cellule a été modifiée par un autre utilisateur. Le planning va se recharger.', 'error');
            cellModal.hide();
            await reload();
        } else if (res.success) {
            cellModal.hide();
            await reload();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    async function deleteCell() {
        const userId = document.getElementById('cellUserId').value;
        const date = document.getElementById('cellDate').value;
        const a = assignations.find(x => x.user_id === userId && x.date_jour === date);
        if (!a) return;

        if (!await adminConfirm({ title: 'Supprimer l\'assignation', text: 'Cette assignation sera définitivement supprimée.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
        const res = await adminApiPost('admin_delete_assignation', { id: a.id });
        if (res.success) {
            cellModal.hide();
            await reload();
        }
    }

    // ── Actions ──
    async function createPlanning() {
        const mois = document.getElementById('planningMois').value;
        if (!mois) return;
        if (planning) {
            showToast('Un planning existe déjà pour ce mois', 'error');
            return;
        }
        const res = await adminApiPost('admin_create_planning', { mois });
        if (res.success) {
            showToast('Planning créé', 'success');
            await reload();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    let _genAnimInterval = null;
    function showGenAnimation(container, label, icon) {
        const colors = ['#4dabf7','#51cf66','#fcc419','#ff6b6b','#cc5de8','#20c997','#ff922b','#845ef7'];
        const codes = ['M','S1','S2','N','J','C','R','V','L','F'];
        const names = ['Martin D.','Dupont L.','Favre A.','Rochat M.','Berset C.','Giroud N.','Blanc P.','Morel S.','Neri F.','Rossi G.','Lutz H.','Kern B.'];
        const days = new Date(2026, 4, 0).getDate(); // ~30
        const rows = 10;

        let html = '<div class="gen-anim">';
        html += '<div class="gen-anim-grid" id="gaGrid" style="grid-template-columns:120px repeat(' + days + ',1fr)">'; // dynamic columns must stay inline

        // Header row
        html += '<div class="ga-hdr">Collaborateur</div>';
        for (let d = 1; d <= days; d++) html += `<div class="ga-hdr">${d}</div>`;

        // Data rows
        for (let r = 0; r < rows; r++) {
            html += `<div class="ga-name">${names[r % names.length]}</div>`;
            for (let d = 0; d < days; d++) {
                const c = colors[Math.floor(Math.random() * colors.length)];
                html += `<div class="ga-cell" data-r="${r}" data-d="${d}"><span class="ga-code" style="background:${c}"></span></div>`;
            }
        }
        html += '</div>';

        // Overlay
        html += `<div class="gen-anim-overlay">
            <div class="ga-icon">${icon || '⚙️'}</div>
            <div class="ga-label">${label || 'Génération en cours'}</div>
            <div class="ga-sub">Analyse des contraintes, désirs et besoins…</div>
        </div>`;
        html += '</div>';
        container.innerHTML = html;

        // Animate cells — start empty, progressively fill
        const cells = container.querySelectorAll('.ga-cell');
        const cellArr = Array.from(cells);
        const total = cellArr.length;
        let fillTarget = 0;
        let filled = 0;
        const maxFill = Math.floor(total * 0.75);

        if (_genAnimInterval) clearInterval(_genAnimInterval);

        // Phase 1: gradually fill from 0 to ~75%
        _genAnimInterval = setInterval(() => {
            // Increase target progressively
            if (fillTarget < maxFill) {
                fillTarget += 1 + Math.floor(Math.random() * 3);
                if (fillTarget > maxFill) fillTarget = maxFill;
            }

            // Fill new cells to reach target
            while (filled < fillTarget) {
                const empty = cellArr.filter(c => !c.classList.contains('filled'));
                if (!empty.length) break;
                const cell = empty[Math.floor(Math.random() * empty.length)];
                const code = cell.querySelector('.ga-code');
                if (code) code.style.background = colors[Math.floor(Math.random() * colors.length)];
                cell.classList.add('filled');
                filled++;
            }

            // Random shuffle: swap a few cells (remove some, add others) for liveliness
            if (filled > 10) {
                const swaps = 1 + Math.floor(Math.random() * 2);
                for (let i = 0; i < swaps; i++) {
                    const filledCells = cellArr.filter(c => c.classList.contains('filled'));
                    const emptyCells = cellArr.filter(c => !c.classList.contains('filled'));
                    if (filledCells.length && emptyCells.length) {
                        const rem = filledCells[Math.floor(Math.random() * filledCells.length)];
                        rem.classList.remove('filled');
                        const add = emptyCells[Math.floor(Math.random() * emptyCells.length)];
                        const code = add.querySelector('.ga-code');
                        if (code) code.style.background = colors[Math.floor(Math.random() * colors.length)];
                        add.classList.add('filled');
                    }
                }
            }
        }, 200);
    }

    function stopGenAnimation() {
        if (_genAnimInterval) { clearInterval(_genAnimInterval); _genAnimInterval = null; }
    }

    function showPromptModal() {
        if (!window._lastIaPrompt) { showToast('Aucun prompt disponible. Lancez une génération IA d\'abord.', 'warning'); return; }
        const modeLabels = { local: 'Algorithme local', hybrid: 'Hybride', ai: 'IA directe' };
        let summary = `<strong>Mode :</strong> ${modeLabels[window._lastIaMode] || window._lastIaMode}`;
        if (window._lastIaCost > 0) summary += ` · <strong>Coût :</strong> $${window._lastIaCost.toFixed(4)}`;
        if (window._lastIaSummary) summary += `<br><strong>Résumé IA :</strong> ${window._lastIaSummary}`;
        document.getElementById('promptSummary').innerHTML = summary;
        document.getElementById('promptContent').textContent = window._lastIaPrompt;
        new bootstrap.Modal(document.getElementById('promptModal')).show();
    }

    async function generatePlanning() {
        if (!planning) {
            showToast('Créez d\'abord le planning', 'error');
            return;
        }

        // Populate module filter in generate modal
        const genModEl = document.getElementById('genModuleFilter');
        if (genModEl && refs.modules) {
            const curFilter = zerdaSelect.getValue('#planningModuleFilter');
            const genOpts = [{value:'', label:'Tous les modules'}];
            refs.modules.forEach(m => {
                genOpts.push({value: m.id, label: m.code + ' — ' + m.nom});
            });
            zerdaSelect.destroy(genModEl);
            zerdaSelect.init(genModEl, genOpts, { value: curFilter || '' });
        }

        // Reset mode selection
        let selectedMode = null;
        const cards = document.querySelectorAll('.gen-mode-card');
        const confirmBtn = document.getElementById('genConfirmBtn');
        const confirmLabel = document.getElementById('genConfirmLabel');
        const providerInfo = document.getElementById('genProviderInfo');

        cards.forEach(c => { c.classList.remove('gen-mode-card-selected', 'gen-mode-card-selected-local', 'gen-mode-card-selected-hybrid', 'gen-mode-card-selected-ai'); });
        confirmBtn.disabled = true;
        confirmLabel.textContent = 'Sélectionnez un mode';
        providerInfo.classList.add('d-hidden');

        // Mode labels & colors
        const modeConfig = {
            local:  { label: 'Générer (algorithme local)', icon: 'bi-cpu',   color: '#198754', btnClass: 'btn-success' },
            hybrid: { label: 'Générer (hybride + IA)',     icon: 'bi-stars', color: '#1a1a1a', btnClass: 'btn-primary' },
            ai:     { label: 'Générer (IA directe)',       icon: 'bi-robot', color: '#9B51E0', btnClass: 'btn-purple'  },
        };

        // Card click handlers
        cards.forEach(card => {
            const newCard = card.cloneNode(true);
            card.parentNode.replaceChild(newCard, card);
            newCard.addEventListener('click', async () => {
                selectedMode = newCard.dataset.genMode;
                const mc = modeConfig[selectedMode];

                // Visual: highlight selected card
                document.querySelectorAll('.gen-mode-card').forEach(c => {
                    c.classList.remove('gen-mode-card-selected', 'gen-mode-card-selected-local', 'gen-mode-card-selected-hybrid', 'gen-mode-card-selected-ai');
                });
                newCard.classList.add('gen-mode-card-selected', 'gen-mode-card-selected-' + selectedMode);

                // Update confirm button
                confirmBtn.disabled = false;
                confirmBtn.className = 'btn btn-sm ' + mc.btnClass;
                confirmLabel.innerHTML = `<i class="bi ${mc.icon} me-1"></i> ${mc.label}`;

                // Show provider info for IA modes
                if (selectedMode === 'hybrid' || selectedMode === 'ai') {
                    const cfgRes = await adminApiPost('admin_get_config');
                    const cfg = cfgRes.config || {};
                    const prov = cfg.ai_provider || 'gemini';
                    const provName = prov === 'gemini' ? 'Google Gemini' : 'Anthropic Claude';
                    const model = prov === 'gemini' ? (cfg.gemini_model || 'gemini-2.5-flash') : (cfg.anthropic_model || 'claude-haiku-4-5');
                    const hasKey = prov === 'gemini' ? !!cfg.gemini_api_key : !!cfg.anthropic_api_key;

                    document.getElementById('genProviderName').textContent = provName;
                    document.getElementById('genModelName').textContent = model;
                    providerInfo.classList.remove('d-hidden');

                    if (!hasKey) {
                        providerInfo.className = 'alert alert-danger border py-2 small mb-3';
                        providerInfo.querySelector('span').innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Aucune clé API configurée pour <strong>${provName}</strong>`;
                        confirmBtn.disabled = true;
                    } else {
                        providerInfo.className = 'alert alert-light border py-2 small mb-3';
                    }
                } else {
                    providerInfo.classList.add('d-hidden');
                }
            });
        });

        // Purple button style now in <style> block (btn-purple class)

        // Show modal
        const genModal = new bootstrap.Modal(document.getElementById('generateModal'));
        genModal.show();

        // One-time click handler for confirm
        const handler = async () => {
            confirmBtn.removeEventListener('click', handler);
            genModal.hide();

            if (!selectedMode) return;

            const mois = document.getElementById('planningMois').value;
            const moduleFilter = zerdaSelect.getValue('#genModuleFilter') || '';

            const loadingLabels = {
                local:  'Génération en cours',
                hybrid: 'Génération hybride en cours',
                ai:     'Génération IA en cours',
            };
            const loadingIcons = { local: '⚙️', hybrid: '✨', ai: '🤖' };
            showGenAnimation(
                document.getElementById('planningContent'),
                loadingLabels[selectedMode],
                loadingIcons[selectedMode]
            );

            const data = { mois, mode: selectedMode };
            if (moduleFilter) data.module_id = moduleFilter;

            const res = await adminApiPost('admin_generate_planning', data);
            stopGenAnimation();
            if (res.success) {
                let msg = res.message;
                if (res.nb_conflicts > 0) {
                    msg += ` (${res.nb_conflicts} manques de couverture)`;
                }
                showToast(msg, 'success');
                // Store prompt for display
                if (res.ia_prompt) {
                    window._lastIaPrompt = res.ia_prompt;
                    window._lastIaSummary = res.ia_summary || null;
                    window._lastIaCost = res.ia_cost || 0;
                    window._lastIaMode = selectedMode;
                    document.getElementById('btnViewPrompt').classList.remove('d-hidden');
                }
                await reload();
            } else {
                showToast(res.message || 'Erreur', 'error');
                await reload();
            }
        };
        confirmBtn.addEventListener('click', handler);
    }

    // ── Generation Settings Modal (IA Rules) ──
    let gsRules = [];
    let gsView = 'list'; // 'list' | 'form'
    let gsEditId = null;
    const gsModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('genSettingsModal'));

    async function openSettingsModal() {
        gsView = 'list'; gsEditId = null;
        gsModal().show();
        await gsLoadRules();
    }

    async function gsLoadRules() {
        const body = document.getElementById('genSettingsBody');
        body.innerHTML = '<p class="text-muted small text-center py-3"><span class="spinner-border spinner-border-sm"></span> Chargement...</p>';

        const res = await adminApiPost('admin_get_ia_rules');
        if (!res.success) { body.innerHTML = '<p class="text-danger small">Erreur</p>'; return; }
        gsRules = res.rules || [];
        gsRenderList();
    }

    const gsRuleTypeLabels = {
        user_schedule: 'Collaborateur horaire unique',
        shift_only: 'Horaires autorisés',
        shift_exclude: 'Horaires exclus',
        days_only: 'Jours autorisés',
        module_only: 'Modules autorisés',
        module_exclude: 'Modules exclus',
        no_weekend: 'Pas de weekend',
        max_days_week: 'Max jours/semaine',
    };
    const gsImportanceColors = {
        important: 'danger', moyen: 'warning', faible: 'secondary',
    };

    function gsRenderList() {
        const body = document.getElementById('genSettingsBody');
        const title = document.getElementById('gsModalTitle');
        const footer = document.getElementById('gsModalFooter');
        title.innerHTML = '<i class="bi bi-sliders"></i> Règles de génération';
        footer.innerHTML = '<a href="<?= admin_url('config-ia') ?>" class="btn btn-sm btn-outline-secondary me-auto"><i class="bi bi-gear"></i> Config IA avancée</a>'
            + '<button class="btn btn-sm btn-primary" id="gsAddRuleBtn"><i class="bi bi-plus-lg"></i> Ajouter une règle</button>';
        footer.querySelector('#gsAddRuleBtn').addEventListener('click', () => { gsView = 'form'; gsEditId = null; gsRenderForm(); });

        if (!gsRules.length) {
            body.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-sliders" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>Aucune règle configurée<br><small>Ajoutez des règles pour personnaliser la génération</small></div>';
            return;
        }

        body.innerHTML = gsRules.map(r => {
            const typeLabel = gsRuleTypeLabels[r.rule_type] || r.rule_type || 'Texte libre';
            const impColor = gsImportanceColors[r.importance] || 'secondary';
            let targetLabel = 'Tout le monde';
            if (r.target_mode === 'fonction') targetLabel = 'Fonction: ' + (r.target_fonction_code || '?');
            else if (r.target_mode === 'module') {
                const mIds = r.rule_params?.target_module_ids || [];
                targetLabel = 'Module: ' + mIds.map(id => { const m = (refs.modules||[]).find(x => x.id === id); return m ? m.code : id.substring(0,8); }).join(', ');
            }
            else if (r.target_mode === 'users') targetLabel = (r.targeted_users || []).map(u => u.name).join(', ') || 'Utilisateurs ciblés';
            const params = r.rule_params || {};
            let detail = '';
            if (params.shift_codes) detail = params.shift_codes.join(', ');
            else if (params.max_days) detail = params.max_days + ' jours max';

            return '<div class="gs-rule-card">'
                + '<div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input gs-toggle" data-id="' + r.id + '"' + (r.actif ? ' checked' : '') + '></div>'
                + '<div style="flex:1;min-width:0">'
                + '<div class="d-flex align-items-center gap-2 mb-1">'
                + '<strong class="small">' + escapeHtml(r.titre) + '</strong>'
                + '<span class="badge bg-' + impColor + '" style="font-size:.65rem">' + escapeHtml(r.importance) + '</span>'
                + '<span class="badge bg-light text-dark border" style="font-size:.65rem">' + typeLabel + '</span>'
                + (detail ? '<code style="font-size:.7rem">' + escapeHtml(detail) + '</code>' : '')
                + '</div>'
                + '<div style="font-size:.75rem;color:var(--cl-text-muted)">' + escapeHtml(targetLabel) + (r.description ? ' — ' + escapeHtml(r.description.substring(0, 80)) : '') + '</div>'
                + '</div>'
                + '<div class="d-flex gap-1">'
                + '<button class="btn btn-sm btn-outline-secondary gs-edit" data-id="' + r.id + '" title="Modifier"><i class="bi bi-pencil"></i></button>'
                + '<button class="btn btn-sm btn-outline-danger gs-del" data-id="' + r.id + '" title="Supprimer"><i class="bi bi-trash3"></i></button>'
                + '</div>'
                + '</div>';
        }).join('');

        // Events
        body.querySelectorAll('.gs-toggle').forEach(el => {
            el.addEventListener('change', async () => {
                await adminApiPost('admin_toggle_ia_rule', { id: el.dataset.id });
            });
        });
        body.querySelectorAll('.gs-edit').forEach(el => {
            el.addEventListener('click', () => { gsEditId = el.dataset.id; gsView = 'form'; gsRenderForm(); });
        });
        body.querySelectorAll('.gs-del').forEach(el => {
            el.addEventListener('click', async () => {
                const r = gsRules.find(x => x.id === el.dataset.id);
                const ok = await adminConfirm({ title: 'Supprimer cette règle ?', text: escapeHtml(r?.titre || ''), type: 'danger', icon: 'bi-trash3', okText: 'Supprimer' });
                if (!ok) return;
                const res = await adminApiPost('admin_delete_ia_rule', { id: el.dataset.id });
                if (res.success) { showToast('Règle supprimée', 'success'); gsLoadRules(); }
            });
        });
    }

    function gsRenderForm() {
        const body = document.getElementById('genSettingsBody');
        const title = document.getElementById('gsModalTitle');
        const footer = document.getElementById('gsModalFooter');
        const r = gsEditId ? gsRules.find(x => x.id === gsEditId) : null;
        const isEdit = !!r;
        const params = r?.rule_params || {};

        title.innerHTML = '<button class="btn btn-sm btn-link p-0 me-2" id="gsBackBtn"><i class="bi bi-arrow-left"></i></button> ' + (isEdit ? 'Modifier la règle' : 'Nouvelle règle');
        title.querySelector('#gsBackBtn').addEventListener('click', () => { gsView = 'list'; gsRenderList(); });

        body.innerHTML =
            '<div class="mb-3"><label class="form-label small fw-bold">Titre *</label><input class="form-control form-control-sm" id="gsrTitre" value="' + escapeHtml(r?.titre || '') + '"></div>'
            + '<div class="row g-2 mb-3">'
            + '<div class="col-md-4"><label class="form-label small fw-bold">Type de règle</label><div class="zs-select" id="gsrType" data-placeholder="Choisir..."></div></div>'
            + '<div class="col-md-4"><label class="form-label small fw-bold">Importance</label><div class="zs-select" id="gsrImportance" data-placeholder="Moyen"></div></div>'
            + '<div class="col-md-4"><label class="form-label small fw-bold">Cible</label><div class="zs-select" id="gsrTarget" data-placeholder="Tout le monde"></div></div>'
            + '</div>'
            + '<div id="gsrTargetDetail" class="mb-3"></div>'
            + '<div id="gsrParamsDetail" class="mb-3"></div>'
            + '<div class="mb-3"><label class="form-label small fw-bold">Description / règle en texte libre</label>'
            + '<textarea class="form-control form-control-sm" id="gsrDesc" rows="3" placeholder="Décrivez la règle en langage naturel. Ex: «Marie ne doit jamais travailler le mercredi» ou «Les AS du module M1 ne font pas de D3 le weekend»">' + escapeHtml(r?.description || '') + '</textarea>'
            + '<small class="text-muted">Cette description est transmise à l\'IA pour les modes hybride et IA directe</small></div>';

        // Init zerda-selects
        const typeOpts = [
            { value: '', label: 'Texte libre (IA)' },
            ...Object.entries(gsRuleTypeLabels).map(([k, v]) => ({ value: k, label: v })),
        ];
        zerdaSelect.init('#gsrType', typeOpts, {
            value: r?.rule_type || '',
            onSelect: (val) => {
                updateParamsDetail();
                // Auto-set target to "users" for user_schedule
                if (val === 'user_schedule' && zerdaSelect.getValue('#gsrTarget') !== 'users') {
                    zerdaSelect.setValue('#gsrTarget', 'users');
                    updateTargetDetail();
                }
            },
        });

        zerdaSelect.init('#gsrImportance', [
            { value: 'important', label: 'Important' },
            { value: 'moyen', label: 'Moyen' },
            { value: 'faible', label: 'Faible' },
        ], { value: r?.importance || 'moyen' });

        zerdaSelect.init('#gsrTarget', [
            { value: 'all', label: 'Tout le monde' },
            { value: 'module', label: 'Par module' },
            { value: 'fonction', label: 'Par fonction' },
            { value: 'users', label: 'Utilisateurs spécifiques' },
        ], {
            value: r?.target_mode || 'all',
            onSelect: updateTargetDetail,
        });

        // Build options from refs (must be before updateTargetDetail which uses them)
        const fonctionOpts = (refs.users || []).reduce((acc, u) => {
            if (u.fonction_code && !acc.find(o => o.value === u.fonction_code)) {
                acc.push({ value: u.fonction_code, label: u.fonction_code + ' — ' + (u.fonction_nom || '') });
            }
            return acc;
        }, []);
        const horaireOpts = (refs.horaires || []).map(h => ({ value: h.code, label: h.code + ' (' + h.heure_debut?.substring(0,5) + '-' + h.heure_fin?.substring(0,5) + ')' }));
        const moduleOpts = (refs.modules || []).map(m => ({ value: m.id, label: m.code + ' — ' + m.nom }));

        function updateTargetDetail() {
            const target = zerdaSelect.getValue('#gsrTarget');
            const det = document.getElementById('gsrTargetDetail');
            det.innerHTML = '';
            if (target === 'module') {
                det.innerHTML = '<label class="form-label small fw-bold">Modules</label><div class="zs-select" id="gsrModuleTarget" data-placeholder="Ajouter un module"></div>'
                    + '<div id="gsrModuleTargetTags" class="d-flex flex-wrap gap-1 mt-1"></div>';
                window._gsSelectedTargetModules = (r?.rule_params?.target_module_ids || []).slice();
                zerdaSelect.init('#gsrModuleTarget', moduleOpts, {
                    search: true,
                    onSelect: (val) => {
                        if (val && !window._gsSelectedTargetModules.includes(val)) {
                            window._gsSelectedTargetModules.push(val);
                            gsRenderTargetModuleTags();
                        }
                        zerdaSelect.setValue('#gsrModuleTarget', '');
                    }
                });
                gsRenderTargetModuleTags();
            } else if (target === 'fonction') {
                det.innerHTML = '<label class="form-label small fw-bold">Fonction</label><div class="zs-select" id="gsrFonctionCode" data-placeholder="Choisir une fonction"></div>';
                zerdaSelect.init('#gsrFonctionCode', fonctionOpts, { value: r?.target_fonction_code || '', search: true });
            } else if (target === 'users') {
                const userOpts = (refs.users || []).map(u => ({
                    value: u.id,
                    label: u.prenom + ' ' + u.nom + (u.fonction_code ? ' (' + u.fonction_code + ')' : ''),
                    searchText: u.prenom + ' ' + u.nom + ' ' + (u.fonction_code || ''),
                }));
                det.innerHTML = '<label class="form-label small fw-bold">Utilisateurs</label><div class="zs-select" id="gsrUserSelect" data-placeholder="Choisir un utilisateur"></div>'
                    + '<div id="gsrUserTags" class="d-flex flex-wrap gap-1 mt-1"></div>';
                // Multi-select via tags
                window._gsSelectedUsers = (r?.targeted_users || []).map(u => u.id);
                zerdaSelect.init('#gsrUserSelect', userOpts, {
                    search: true,
                    onSelect: (val) => {
                        if (val && !window._gsSelectedUsers.includes(val)) {
                            window._gsSelectedUsers.push(val);
                            gsRenderUserTags();
                        }
                        zerdaSelect.setValue('#gsrUserSelect', '');
                    }
                });
                gsRenderUserTags();
            }
        }
        updateTargetDetail();

        function gsRenderUserTags() {
            const container = document.getElementById('gsrUserTags');
            if (!container) return;
            container.innerHTML = window._gsSelectedUsers.map(uid => {
                const u = (refs.users || []).find(x => x.id === uid);
                if (!u) return '';
                return '<span class="badge bg-light text-dark border" style="font-size:.78rem">' + escapeHtml(u.prenom + ' ' + u.nom)
                    + ' <button type="button" style="background:none;border:none;cursor:pointer;padding:0 2px;font-size:.9rem;color:var(--cl-text-muted)" data-rm="' + uid + '">&times;</button></span>';
            }).join('');
            container.querySelectorAll('[data-rm]').forEach(btn => {
                btn.addEventListener('click', () => {
                    window._gsSelectedUsers = window._gsSelectedUsers.filter(x => x !== btn.dataset.rm);
                    gsRenderUserTags();
                });
            });
        }

        function gsRenderTargetModuleTags() {
            const c = document.getElementById('gsrModuleTargetTags');
            if (!c) return;
            c.innerHTML = (window._gsSelectedTargetModules || []).map(id => {
                const m = (refs.modules || []).find(x => x.id === id);
                return '<span class="badge bg-light text-dark border" style="font-size:.78rem">' + escapeHtml(m?.code || id) + ' — ' + escapeHtml(m?.nom || '')
                    + ' <button type="button" style="background:none;border:none;cursor:pointer;padding:0 2px;font-size:.9rem;color:var(--cl-text-muted)" data-rm="' + id + '">&times;</button></span>';
            }).join('');
            c.querySelectorAll('[data-rm]').forEach(btn => {
                btn.addEventListener('click', () => { window._gsSelectedTargetModules = window._gsSelectedTargetModules.filter(x => x !== btn.dataset.rm); gsRenderTargetModuleTags(); });
            });
        }

        function updateParamsDetail() {
            const type = zerdaSelect.getValue('#gsrType');
            const det = document.getElementById('gsrParamsDetail');
            det.innerHTML = '';

            if (type === 'user_schedule') {
                const dayNames = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
                const dayFull = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
                const selectedDays = params.days || [];
                const excludedShifts = params.exclude_shift_codes || [];

                det.innerHTML =
                    // Section 1: Jours
                    '<div class="us-section">'
                    + '<div class="us-section-title"><i class="bi bi-calendar-week"></i> Jours de travail</div>'
                    + '<div class="d-flex flex-wrap gap-2" id="gsrDaysWrap">'
                    + dayFull.map((name, i) => {
                        const dow = i + 1; const checked = selectedDays.includes(dow);
                        return '<label class="us-day-label' + (checked ? ' us-day-active' : '') + '">'
                            + '<input type="checkbox" class="gsr-day-cb" value="' + dow + '"' + (checked ? ' checked' : '') + '>'
                            + '<span class="us-day-short">' + dayNames[i] + '</span></label>';
                    }).join('') + '</div>'
                    + '</div>'

                    // Section 2: Horaires autorisés
                    + '<div class="us-section">'
                    + '<div class="us-section-title"><i class="bi bi-check-circle"></i> Horaires autorisés <small class="text-muted fw-normal">(laisser vide = tous)</small></div>'
                    + '<div class="zs-select" id="gsrShiftSelect" data-placeholder="Ajouter un horaire"></div>'
                    + '<div id="gsrShiftTags" class="d-flex flex-wrap gap-1 mt-1"></div>'
                    + '</div>'

                    // Section 3: Horaires exclus
                    + '<div class="us-section">'
                    + '<div class="us-section-title"><i class="bi bi-x-circle"></i> Horaires interdits <small class="text-muted fw-normal">(optionnel)</small></div>'
                    + '<div class="zs-select" id="gsrExcludeShiftSelect" data-placeholder="Exclure un horaire"></div>'
                    + '<div id="gsrExcludeShiftTags" class="d-flex flex-wrap gap-1 mt-1"></div>'
                    + '</div>';

                // Day checkboxes
                det.querySelectorAll('.gsr-day-cb').forEach(cb => {
                    cb.addEventListener('change', () => { cb.closest('.us-day-label').classList.toggle('us-day-active', cb.checked); });
                });

                // Shift allowed
                window._gsSelectedShifts = (params.shift_codes || []).slice();
                zerdaSelect.init('#gsrShiftSelect', horaireOpts, { search: true, onSelect: (val) => {
                    if (val && !window._gsSelectedShifts.includes(val)) { window._gsSelectedShifts.push(val); gsRenderShiftTags(); }
                    zerdaSelect.setValue('#gsrShiftSelect', '');
                }});
                gsRenderShiftTags();

                // Shift excluded
                window._gsExcludedShifts = excludedShifts.slice();
                zerdaSelect.init('#gsrExcludeShiftSelect', horaireOpts, { search: true, onSelect: (val) => {
                    if (val && !window._gsExcludedShifts.includes(val)) { window._gsExcludedShifts.push(val); gsRenderExcludeShiftTags(); }
                    zerdaSelect.setValue('#gsrExcludeShiftSelect', '');
                }});
                gsRenderExcludeShiftTags();

            } else if (type === 'shift_only' || type === 'shift_exclude') {
                det.innerHTML = '<label class="form-label small fw-bold">Horaires</label><div class="zs-select" id="gsrShiftSelect" data-placeholder="Ajouter un horaire"></div>'
                    + '<div id="gsrShiftTags" class="d-flex flex-wrap gap-1 mt-1"></div>';
                window._gsSelectedShifts = (params.shift_codes || []).slice();
                zerdaSelect.init('#gsrShiftSelect', horaireOpts, {
                    search: true,
                    onSelect: (val) => {
                        if (val && !window._gsSelectedShifts.includes(val)) {
                            window._gsSelectedShifts.push(val);
                            gsRenderShiftTags();
                        }
                        zerdaSelect.setValue('#gsrShiftSelect', '');
                    }
                });
                gsRenderShiftTags();
            } else if (type === 'days_only') {
                const dayNames = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
                const selectedDays = params.days || [];
                det.innerHTML = '<label class="form-label small fw-bold">Jours autorisés</label>'
                    + '<div class="d-flex flex-wrap gap-2" id="gsrDaysWrap">'
                    + dayNames.map((name, i) => {
                        const dow = i + 1;
                        const checked = selectedDays.includes(dow);
                        return '<label style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;border:1.5px solid ' + (checked ? 'var(--cl-primary,#1a1a1a)' : 'var(--cl-border,#E8E5E0)') + ';cursor:pointer;font-size:.82rem;font-weight:500;background:' + (checked ? 'var(--cl-primary-light,#f0f0f0)' : '') + ';transition:all .15s">'
                            + '<input type="checkbox" class="gsr-day-cb" value="' + dow + '"' + (checked ? ' checked' : '') + ' style="accent-color:var(--cl-primary,#1a1a1a)">' + name + '</label>';
                    }).join('')
                    + '</div>';
                det.querySelectorAll('.gsr-day-cb').forEach(cb => {
                    cb.addEventListener('change', () => {
                        const label = cb.closest('label');
                        label.style.borderColor = cb.checked ? 'var(--cl-primary,#1a1a1a)' : 'var(--cl-border,#E8E5E0)';
                        label.style.background = cb.checked ? 'var(--cl-primary-light,#f0f0f0)' : '';
                    });
                });
            } else if (type === 'max_days_week') {
                det.innerHTML = '<label class="form-label small fw-bold">Max jours par semaine</label><input type="number" class="form-control form-control-sm" id="gsrMaxDays" min="1" max="7" value="' + (params.max_days || 5) + '">';
            } else if (type === 'module_only' || type === 'module_exclude') {
                det.innerHTML = '<label class="form-label small fw-bold">Modules</label><div class="zs-select" id="gsrModuleSelect" data-placeholder="Ajouter un module"></div>'
                    + '<div id="gsrModuleTags" class="d-flex flex-wrap gap-1 mt-1"></div>';
                window._gsSelectedModules = (params.module_ids || []).slice();
                zerdaSelect.init('#gsrModuleSelect', moduleOpts, {
                    search: true,
                    onSelect: (val) => {
                        if (val && !window._gsSelectedModules.includes(val)) {
                            window._gsSelectedModules.push(val);
                            gsRenderModuleTags();
                        }
                        zerdaSelect.setValue('#gsrModuleSelect', '');
                    }
                });
                gsRenderModuleTags();
            }
            // Texte libre: no extra params — description field is always visible
        }
        updateParamsDetail();

        function gsRenderShiftTags() {
            const c = document.getElementById('gsrShiftTags');
            if (!c) return;
            c.innerHTML = window._gsSelectedShifts.map(code => {
                const h = (refs.horaires || []).find(x => x.code === code);
                return '<span class="badge" style="background:' + escapeHtml(h?.couleur || '#666') + ';color:#fff;font-size:.78rem">' + escapeHtml(code)
                    + ' <button type="button" style="background:none;border:none;cursor:pointer;padding:0 2px;color:#fff;font-size:.9rem" data-rm="' + code + '">&times;</button></span>';
            }).join('');
            c.querySelectorAll('[data-rm]').forEach(btn => {
                btn.addEventListener('click', () => { window._gsSelectedShifts = window._gsSelectedShifts.filter(x => x !== btn.dataset.rm); gsRenderShiftTags(); });
            });
        }

        function gsRenderExcludeShiftTags() {
            const c = document.getElementById('gsrExcludeShiftTags');
            if (!c) return;
            c.innerHTML = (window._gsExcludedShifts || []).map(code => {
                const h = (refs.horaires || []).find(x => x.code === code);
                return '<span class="badge" style="background:#dc3545;color:#fff;font-size:.78rem"><i class="bi bi-x-circle me-1"></i>' + escapeHtml(code)
                    + ' <button type="button" style="background:none;border:none;cursor:pointer;padding:0 2px;color:#fff;font-size:.9rem" data-rm="' + code + '">&times;</button></span>';
            }).join('');
            c.querySelectorAll('[data-rm]').forEach(btn => {
                btn.addEventListener('click', () => { window._gsExcludedShifts = window._gsExcludedShifts.filter(x => x !== btn.dataset.rm); gsRenderExcludeShiftTags(); });
            });
        }

        function gsRenderModuleTags() {
            const c = document.getElementById('gsrModuleTags');
            if (!c) return;
            c.innerHTML = window._gsSelectedModules.map(id => {
                const m = (refs.modules || []).find(x => x.id === id);
                return '<span class="badge bg-light text-dark border" style="font-size:.78rem">' + escapeHtml(m?.code || id)
                    + ' <button type="button" style="background:none;border:none;cursor:pointer;padding:0 2px;color:var(--cl-text-muted);font-size:.9rem" data-rm="' + id + '">&times;</button></span>';
            }).join('');
            c.querySelectorAll('[data-rm]').forEach(btn => {
                btn.addEventListener('click', () => { window._gsSelectedModules = window._gsSelectedModules.filter(x => x !== btn.dataset.rm); gsRenderModuleTags(); });
            });
        }

        footer.innerHTML = '<button class="btn btn-sm btn-outline-secondary" id="gsCancelForm">Annuler</button>'
            + '<button class="btn btn-sm btn-primary" id="gsSaveForm"><i class="bi bi-check-lg"></i> ' + (isEdit ? 'Modifier' : 'Créer') + '</button>';
        footer.querySelector('#gsCancelForm').addEventListener('click', () => { gsView = 'list'; gsRenderList(); });
        footer.querySelector('#gsSaveForm').addEventListener('click', gsSaveRule);
    }

    async function gsSaveRule() {
        const titre = document.getElementById('gsrTitre')?.value.trim();
        if (!titre) { showToast('Titre requis', 'error'); return; }

        const ruleType = zerdaSelect.getValue('#gsrType') || null;
        const importance = zerdaSelect.getValue('#gsrImportance') || 'moyen';
        const targetMode = zerdaSelect.getValue('#gsrTarget') || 'all';
        const description = document.getElementById('gsrDesc')?.value.trim() || '';
        const targetFonctionCode = zerdaSelect.getValue('#gsrFonctionCode') || document.getElementById('gsrFonctionCode')?.value?.trim() || '';
        const userIds = window._gsSelectedUsers || [];

        let ruleParams = {};
        if (ruleType === 'user_schedule') {
            ruleParams.shift_codes = window._gsSelectedShifts || [];
            ruleParams.exclude_shift_codes = window._gsExcludedShifts || [];
            ruleParams.days = [...document.querySelectorAll('.gsr-day-cb:checked')].map(cb => parseInt(cb.value));
        } else if (ruleType === 'shift_only' || ruleType === 'shift_exclude') {
            ruleParams.shift_codes = window._gsSelectedShifts || [];
        } else if (ruleType === 'days_only') {
            ruleParams.days = [...document.querySelectorAll('.gsr-day-cb:checked')].map(cb => parseInt(cb.value));
        } else if (ruleType === 'max_days_week') {
            ruleParams.max_days = parseInt(document.getElementById('gsrMaxDays')?.value || 5);
        } else if (ruleType === 'module_only' || ruleType === 'module_exclude') {
            ruleParams.module_ids = window._gsSelectedModules || [];
        }

        const targetModuleIds = window._gsSelectedTargetModules || [];
        const data = { titre, description, importance, rule_type: ruleType, rule_params: JSON.stringify(ruleParams), target_mode: targetMode, target_fonction_code: targetFonctionCode, user_ids: userIds, target_module_ids: targetModuleIds };

        const action = gsEditId ? 'admin_update_ia_rule' : 'admin_create_ia_rule';
        if (gsEditId) data.id = gsEditId;

        const res = await adminApiPost(action, data);
        if (res.success) {
            showToast(gsEditId ? 'Règle modifiée' : 'Règle créée', 'success');
            gsView = 'list'; gsEditId = null;
            gsLoadRules();
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    }

    document.getElementById('genSettingsBtn')?.addEventListener('click', openSettingsModal);
    document.getElementById('btnSettingsPlanning')?.addEventListener('click', openSettingsModal);

    async function clearPlanning() {
        if (!planning) return;
        // DEV MODE: autoriser vider même un planning finalisé
        // TODO: remettre le blocage après dev
        // if (planning.statut === 'final') {
        //     showToast('Planning finalisé', 'error');
        //     return;
        // }
        const moduleFilter = zerdaSelect.getValue('#planningModuleFilter');
        const label = moduleFilter
            ? (refs.modules || []).find(m => m.id === moduleFilter)?.code
            : 'toutes les assignations';
        if (!await adminConfirm({ title: 'Vider le planning', text: `Vider <strong>${label}</strong> ? Cette action est irréversible.`, icon: 'bi-trash', type: 'danger', okText: 'Vider' })) return;

        const data = { planning_id: planning.id };
        if (moduleFilter) data.module_id = moduleFilter;

        const res = await adminApiPost('admin_clear_planning', data);
        if (res.success) {
            showToast(res.message, 'success');
            await reload();
        }
    }

    async function finalize(statut) {
        if (!planning) return;
        if (!await adminConfirm({ title: 'Changer le statut', text: `Passer le planning en <strong>${statut}</strong> ?`, icon: 'bi-check-circle', type: statut === 'final' ? 'success' : 'info', okText: 'Confirmer' })) return;
        const res = await adminApiPost('admin_finalize_planning', { id: planning.id, statut });
        if (res.success) {
            showToast(res.message, 'success');
            await reload();
        }
    }

    async function showStats() {
        const mois = document.getElementById('planningMois').value;
        document.getElementById('statsContent').innerHTML =
            '<div class="text-center py-3"><span class="admin-spinner"></span></div>';
        statsModal.show();

        const res = await adminApiPost('admin_get_planning_stats', { mois });
        if (!res.stats) {
            document.getElementById('statsContent').innerHTML = '<p class="text-muted">Aucun planning.</p>';
            return;
        }

        const s = res.stats;
        const t = s.totals;
        let html = '';

        // Summary cards
        html += '<div class="st-cards">';
        html += '<div class="st-card st-card-teal"><div class="st-val">' + t.nb_employes + '</div><div class="st-lbl">Employés</div></div>';
        html += '<div class="st-card st-card-blue"><div class="st-val">' + t.nb_assignations + '</div><div class="st-lbl">Assignations</div></div>';
        html += '<div class="st-card st-card-orange"><div class="st-val">' + Math.round(t.total_heures) + 'h</div><div class="st-lbl">Heures totales</div></div>';
        html += '<div class="st-card ' + (s.nb_gaps > 0 ? 'st-card-red' : 'st-card-green') + '"><div class="st-val">' + s.nb_gaps + '</div><div class="st-lbl">Manques</div></div>';
        html += '</div>';

        // Hours per user table
        html += '<div class="st-section">';
        html += '<div class="st-section-title"><i class="bi bi-people"></i> Heures par collaborateur</div>';
        html += '<div class="st-scroll">';
        html += '<table class="st-table"><thead><tr>';
        html += '<th>Collaborateur</th><th>Fonction</th><th>Taux</th><th>Jours</th><th>Heures</th><th>Cible</th><th>Écart</th>';
        html += '</tr></thead><tbody>';
        (s.heures_par_user || []).forEach(u => {
            const ecartCls = u.ecart > 5 ? 'st-ecart-over' : u.ecart < -5 ? 'st-ecart-under' : 'st-ecart-ok';
            html += '<tr>'
                + '<td class="st-name">' + escapeHtml(u.prenom) + ' ' + escapeHtml(u.nom) + '</td>'
                + '<td>' + escapeHtml(u.fonction_code || '') + '</td>'
                + '<td>' + Math.round(u.taux) + '%</td>'
                + '<td><span style="color:#2d4a43">' + u.jours_presents + 'P</span> <span style="color:#7B3B2C">' + u.jours_absents + 'A</span> <span style="color:#6B5B3E">' + u.jours_repos + 'R</span></td>'
                + '<td><strong>' + Math.round(u.total_heures) + 'h</strong></td>'
                + '<td>' + u.heures_cibles + 'h</td>'
                + '<td class="' + ecartCls + '">' + (u.ecart > 0 ? '+' : '') + u.ecart + 'h</td>'
                + '</tr>';
        });
        html += '</tbody></table></div></div>';

        // Gaps
        if (s.gaps && s.gaps.length > 0) {
            html += '<div class="st-section">';
            html += '<div class="st-section-title" style="color:#7B3B2C"><i class="bi bi-exclamation-triangle"></i> Manques de couverture</div>';
            html += '<div class="st-scroll" style="max-height:180px">';
            html += '<table class="st-table"><thead><tr>';
            html += '<th>Date</th><th>Module</th><th>Fonction</th><th>Requis</th><th>Présent</th><th>Manque</th>';
            html += '</tr></thead><tbody>';
            s.gaps.forEach(g => {
                html += '<tr class="st-gap-row">'
                    + '<td>' + g.date + '</td><td>' + escapeHtml(g.module_code) + '</td>'
                    + '<td>' + escapeHtml(g.fonction_code) + '</td>'
                    + '<td>' + g.requis + '</td><td>' + g.present + '</td>'
                    + '<td class="st-gap-val">-' + g.manque + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table></div></div>';
        }

        document.getElementById('statsContent').innerHTML = html;
    }

    // ── Week navigation ──
    function moveWeek(dir) {
        currentWeekStart.setDate(currentWeekStart.getDate() + dir * 7);
        renderGrid();
    }

    // ── Filter tabs carousel ──
    let tabsConfig = null;

    async function buildModuleSwitch() {
        const container = document.getElementById('moduleSwitch');

        // Load tabs config from ems_config
        try {
            const cfgRes = await adminApiPost('admin_get_config');
            const cfg = cfgRes.config || {};
            if (cfg.planning_tabs_config) {
                tabsConfig = JSON.parse(cfg.planning_tabs_config);
            }
        } catch(e) {}

        // Default tabs: Tous + all modules
        if (!tabsConfig || !tabsConfig.tabs || !tabsConfig.tabs.length) {
            tabsConfig = { tabs: [
                { type: 'all', label: 'Tous', value: '' },
                ...(refs.modules || []).map(m => ({ type: 'module', label: m.code, value: m.id })),
                ...(refs.fonctions || []).map(f => ({ type: 'fonction', label: f.code, value: f.code }))
            ]};
        }

        let html = '<div class="module-switch-indicator"></div>';
        tabsConfig.tabs.forEach((tab, i) => {
            const active = i === 0 ? ' active' : '';
            html += `<button class="module-switch-btn${active}" data-filter-type="${tab.type}" data-filter-value="${tab.value}">${escapeHtml(tab.label)}</button>`;
        });
        container.innerHTML = html;

        // Sliding indicator helper
        const indicator = container.querySelector('.module-switch-indicator');
        function moveIndicator(btn) {
            if (!indicator || !btn) return;
            indicator.style.left = btn.offsetLeft + 'px';
            indicator.style.width = btn.offsetWidth + 'px';
        }
        // Position on first active button
        requestAnimationFrame(() => {
            const activeBtn = container.querySelector('.module-switch-btn.active');
            if (activeBtn) moveIndicator(activeBtn);
        });

        container.querySelectorAll('.module-switch-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                container.querySelectorAll('.module-switch-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                moveIndicator(btn);

                document.getElementById('planningFilterType').value = btn.dataset.filterType;
                document.getElementById('planningFilterValue').value = btn.dataset.filterValue;
                // Backward compat
                zerdaSelect.setValue('#planningModuleFilter', btn.dataset.filterType === 'module' ? btn.dataset.filterValue : '');
                renderGrid();
            });
        });
    }

    // ── Fullscreen toggle ──
    // Modal IDs to move in/out of the fullscreen container
    const _fsModalIds = ['statsModal','cellModal','emailPlanningModal','proposalsModal','generateModal','promptModal','genSettingsModal','confirmModal'];
    let _fsBackdropObserver = null;

    function toggleFullscreen() {
        const card = document.getElementById('planningCard');
        const toolbar = document.getElementById('planningToolbar');
        const moduleSwitch = document.getElementById('moduleSwitch');
        const weekNav = document.getElementById('weekNav');
        const btn = document.getElementById('btnFullscreen');
        const sidebar = document.getElementById('adminSidebar');
        const topbar = document.querySelector('.admin-topbar');

        isFullscreen = !isFullscreen;

        if (isFullscreen) {
            // Enter fullscreen
            card.classList.add('fullscreen-active');
            // Move toolbar and switch inside the card at top
            card.insertBefore(weekNav, card.firstChild);
            card.insertBefore(moduleSwitch, card.firstChild);
            card.insertBefore(toolbar, card.firstChild);
            // Move all modals inside the fullscreen container so they render above it
            _fsModalIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) card.appendChild(el);
            });
            // Observe body for Bootstrap backdrops (.modal-backdrop) and move them into the container
            _fsBackdropObserver = new MutationObserver(mutations => {
                mutations.forEach(m => {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1 && node.classList && node.classList.contains('modal-backdrop')) {
                            card.appendChild(node);
                        }
                    });
                });
            });
            _fsBackdropObserver.observe(document.body, { childList: true });
            // Move any already-present backdrops
            document.body.querySelectorAll(':scope > .modal-backdrop').forEach(b => card.appendChild(b));
            // Hide sidebar + topbar
            if (sidebar) sidebar.classList.add('fs-sidebar-hidden');
            if (topbar) topbar.classList.add('fs-topbar-hidden');
            document.querySelector('.admin-main').classList.add('fs-main-no-margin');
            btn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
            btn.title = 'Quitter plein écran';
        } else {
            // Exit fullscreen
            card.classList.remove('fullscreen-active');
            // Stop observing backdrops
            if (_fsBackdropObserver) { _fsBackdropObserver.disconnect(); _fsBackdropObserver = null; }
            // Move modals back to body so Bootstrap manages them normally
            _fsModalIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) document.body.appendChild(el);
            });
            // Move any backdrops back to body
            card.querySelectorAll('.modal-backdrop').forEach(b => document.body.appendChild(b));
            // Move elements back
            const content = document.getElementById('adminContent');
            content.insertBefore(toolbar, content.firstChild);
            toolbar.after(moduleSwitch);
            moduleSwitch.after(weekNav);
            // Restore sidebar + topbar
            if (sidebar) sidebar.classList.remove('fs-sidebar-hidden');
            if (topbar) topbar.classList.remove('fs-topbar-hidden');
            document.querySelector('.admin-main').classList.remove('fs-main-no-margin');
            btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
            btn.title = 'Plein écran';
        }
    }

    // ── Export CSV (PolyPoint) ──
    function exportCsv() {
        if (!planning || !assignations.length) {
            showToast('Aucune donnée à exporter', 'error');
            return;
        }

        const mois = document.getElementById('planningMois').value;
        const [year, month] = mois.split('-').map(Number);
        const daysInMonth = new Date(year, month, 0).getDate();
        const joursSemaine = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];

        // Build horaire map
        const horaireMap = {};
        (refs.horaires || []).forEach(h => { horaireMap[h.id] = h; });

        // Build module map
        const moduleMap = {};
        (refs.modules || []).forEach(m => { moduleMap[m.id] = m; });

        // Build user map
        const userMap = {};
        (refs.users || []).forEach(u => { userMap[u.id] = u; });

        // Build assignation index: userMap[userId][date] = assignation
        const assignIdx = {};
        assignations.forEach(a => {
            if (!assignIdx[a.user_id]) assignIdx[a.user_id] = {};
            assignIdx[a.user_id][a.date_jour] = a;
        });

        // Header row: Employee ID, Nom, Prenom, Module, 1, 2, ..., N, Total heures
        let headers = ['ID','Nom','Prénom','Module','Fonction'];
        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month - 1, d);
            headers.push(`${joursSemaine[date.getDay()]} ${d}`);
        }
        headers.push('Total heures');

        const rows = [headers];

        // Group users by module
        const users = refs.users || [];
        users.forEach(u => {
            const userAssign = assignIdx[u.id] || {};
            const row = [
                u.employee_id || '',
                u.nom,
                u.prenom,
                u.principal_module_code || '',
                u.fonction_code || '',
            ];

            let totalHours = 0;
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const a = userAssign[dateStr];
                if (a && a.horaire_type_id) {
                    const h = horaireMap[a.horaire_type_id];
                    row.push(h ? h.code : '');
                    totalHours += h ? parseFloat(h.duree_effective || 0) : 0;
                } else {
                    row.push('');
                }
            }
            row.push(totalHours.toFixed(1));
            rows.push(row);
        });

        // Generate CSV
        const csv = rows.map(r =>
            r.map(c => {
                const s = String(c).replace(/"/g, '""');
                return /[,;\n"]/.test(s) ? `"${s}"` : s;
            }).join(';')
        ).join('\n');

        // BOM for Excel compatibility
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `planning_${mois}_polypoint.csv`;
        a.click();
        URL.revokeObjectURL(url);

        showToast('Export CSV téléchargé', 'success');
    }

    // ── Proposals ──
    async function saveProposal() {
        const mois = document.getElementById('planningMois').value;
        if (!mois) { showToast('Sélectionnez un mois', 'error'); return; }
        if (!planning) { showToast('Chargez d\'abord un planning', 'warning'); return; }

        const [y, m] = mois.split('-');
        const moisNoms = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        const moisLabel = moisNoms[parseInt(m)] + ' ' + y;

        // Compter les propositions existantes pour ce mois
        const res = await adminApiPost('admin_get_proposals', { mois });
        const count = (res.proposals || []).length;
        const choixNum = count + 1;

        const input = document.getElementById('proposalLabelInput');
        input.value = 'Choix ' + choixNum + ' – planning ' + moisLabel;
        input.placeholder = 'Ex: Choix ' + choixNum + ' – planning ' + moisLabel;

        proposalLabelModal.show();

        document.getElementById('proposalLabelModal').addEventListener('shown.bs.modal', function onShown() {
            document.getElementById('proposalLabelModal').removeEventListener('shown.bs.modal', onShown);
            input.focus();
            input.select();
        });
    }

    // Validation du modal proposition
    document.getElementById('proposalLabelOk').addEventListener('click', async () => {
        const input = document.getElementById('proposalLabelInput');
        const label = input.value.trim();
        if (!label) { input.classList.add('is-invalid'); input.focus(); return; }
        input.classList.remove('is-invalid');

        const mois = document.getElementById('planningMois').value;
        proposalLabelModal.hide();

        const res = await adminApiPost('admin_create_proposal', { mois, label });
        if (res.success) {
            showToast('Proposition « ' + label + ' » créée — les employés peuvent voter', 'success');
        } else {
            showToast(res.message || 'Erreur', 'error');
        }
    });

    // Enter pour valider
    document.getElementById('proposalLabelInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('proposalLabelOk').click(); }
    });

    let proposalsModalInstance = null;
    async function viewProposals() {
        const mois = document.getElementById('planningMois').value;
        if (!proposalsModalInstance) {
            proposalsModalInstance = new bootstrap.Modal(document.getElementById('proposalsModal'));
        }
        proposalsModalInstance.show();

        const content = document.getElementById('proposalsContent');
        content.innerHTML = '<div class="text-center text-muted py-3"><span class="admin-spinner"></span></div>';

        const res = await adminApiPost('admin_get_proposals', { mois });
        const proposals = res.proposals || [];

        if (!proposals.length) {
            content.innerHTML = '<div class="text-center text-muted py-3">Aucune proposition pour ce mois</div>';
            return;
        }

        content.innerHTML = proposals.map(p => {
            const total = p.votes_pour + p.votes_contre;
            const pctPour = total > 0 ? Math.round((p.votes_pour / total) * 100) : 0;
            const statutDots = { ouvert: '#2d4a43', ferme: 'var(--cl-text-secondary,#6B6B69)', valide: 'var(--cl-accent,#191918)', rejete: '#7B3B2C' };
            const statutLabels = { ouvert: 'Ouvert', ferme: 'Fermé', valide: 'Validé', rejete: 'Rejeté' };

            return `<div class="card mb-2 prop-card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <strong class="prop-label">${escapeHtml(p.label)}</strong>
                            <span class="d-inline-flex align-items-center gap-1 px-2 py-0 prop-status-badge">
                                <span class="prop-status-dot" style="background:${statutDots[p.statut] || '#999'}"></span>
                                ${statutLabels[p.statut] || p.statut}
                            </span>
                        </div>
                        <div class="d-flex gap-2 align-items-center prop-votes-block">
                            <span class="d-inline-flex align-items-center gap-1 px-2 rounded-pill prop-votes-pour">${p.votes_pour} <i class="bi bi-hand-thumbs-up-fill"></i></span>
                            <span class="d-inline-flex align-items-center gap-1 px-2 rounded-pill prop-votes-contre">${p.votes_contre} <i class="bi bi-hand-thumbs-down-fill"></i></span>
                            <span class="text-muted">(${total})</span>
                        </div>
                    </div>
                    <div class="prop-progress-bar">
                        <div class="prop-progress-fill" style="width:${pctPour}%"></div>
                    </div>
                    <div class="d-flex gap-1 justify-content-end mt-2 prop-btns-block">
                        ${p.statut === 'ouvert' ? `
                            <button class="btn btn-sm prop-btn-action prop-btn-secondary" data-toggle-vote="${p.id}" data-action="ferme">Fermer votes</button>
                            <button class="btn btn-sm prop-btn-validate" data-validate-prop="${p.id}"><i class="bi bi-check-lg"></i> Valider</button>
                        ` : ''}
                        ${p.statut === 'ferme' ? `
                            <button class="btn btn-sm prop-btn-action prop-btn-secondary" data-toggle-vote="${p.id}" data-action="ouvert">Rouvrir</button>
                            <button class="btn btn-sm prop-btn-validate" data-validate-prop="${p.id}"><i class="bi bi-check-lg"></i> Valider</button>
                        ` : ''}
                        <button class="btn btn-sm prop-btn-action prop-btn-votes" data-view-votes="${p.id}"><i class="bi bi-eye"></i> Votes</button>
                        ${p.statut !== 'valide' ? `<button class="btn btn-sm prop-btn-delete-color prop-btn-delete" data-delete-prop="${p.id}"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                    <div id="voteDetail-${p.id}" class="mt-2 vote-detail-hidden"></div>
                </div>
            </div>`;
        }).join('');

        // Toggle vote status
        content.querySelectorAll('[data-toggle-vote]').forEach(btn => {
            btn.addEventListener('click', async () => {
                await adminApiPost('admin_toggle_vote_status', { proposal_id: btn.dataset.toggleVote, statut: btn.dataset.action });
                showToast('Statut mis à jour', 'success');
                viewProposals();
            });
        });

        // Validate
        content.querySelectorAll('[data-validate-prop]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!await adminConfirm({ title: 'Appliquer la proposition', text: 'Valider cette proposition et l\'appliquer au planning ?', icon: 'bi-check2-all', type: 'success', okText: 'Appliquer' })) return;
                const res = await adminApiPost('admin_validate_proposal', { proposal_id: btn.dataset.validateProp });
                if (res.success) {
                    showToast('Proposition validée et appliquée', 'success');
                    proposalsModalInstance.hide();
                    await reload();
                } else {
                    showToast(res.message || 'Erreur', 'error');
                }
            });
        });

        // Delete
        content.querySelectorAll('[data-delete-prop]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!await adminConfirm({ title: 'Supprimer la proposition', text: 'Cette proposition sera supprimée.', icon: 'bi-trash', type: 'danger', okText: 'Supprimer' })) return;
                await adminApiPost('admin_delete_proposal', { proposal_id: btn.dataset.deleteProp });
                showToast('Proposition supprimée', 'success');
                viewProposals();
            });
        });

        // View vote details
        content.querySelectorAll('[data-view-votes]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const div = document.getElementById(`voteDetail-${btn.dataset.viewVotes}`);
                if (!div) return;
                if (!div.classList.contains('vote-detail-hidden')) { div.classList.add('vote-detail-hidden'); return; }

                div.innerHTML = '<div class="text-center text-muted"><span class="admin-spinner"></span></div>';
                div.classList.remove('vote-detail-hidden');

                const res = await adminApiPost('admin_get_proposal_votes', { proposal_id: btn.dataset.viewVotes });
                const votes = res.votes || [];

                if (!votes.length) {
                    div.innerHTML = '<div class="text-muted vote-detail-text">Aucun vote</div>';
                    return;
                }

                div.innerHTML = `<table class="table table-sm mb-0 vote-detail-table">
                    <thead><tr><th>Employé</th><th>Vote</th><th>Commentaire</th></tr></thead>
                    <tbody>${votes.map(v => `<tr>
                        <td>${escapeHtml(v.prenom)} ${escapeHtml(v.nom)}${v.fonction_code ? ` <span class="text-muted">(${escapeHtml(v.fonction_code)})</span>` : ''}</td>
                        <td><span class="badge bg-${v.vote === 'pour' ? 'success' : 'danger'}">${v.vote}</span></td>
                        <td><small>${escapeHtml(v.commentaire || '')}</small></td>
                    </tr>`).join('')}</tbody>
                </table>`;
            });
        });
    }

    // ── Print ──
    function printPlanning() {
        const content = document.getElementById('planningContent');
        if (!content) return;
        const mois = document.getElementById('planningMois').value;

        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head><title>Planning ${mois}</title>
        <link href="/spocspace/admin/assets/css/vendor/bootstrap.min.css" rel="stylesheet">
        <style>
          body { font-size: 10px; padding: 10px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { border: 1px solid #ccc; padding: 2px 4px; text-align: center; font-size: 9px; }
          th { background: #f0f0f0; }
          .badge { font-size: 8px; padding: 1px 4px; border-radius: 3px; color: #fff; display: inline-block; }
          .planning-user-cell { text-align: left; font-weight: 600; white-space: nowrap; font-size: 9px; }
          .planning-hours-cell { font-weight: 700; }
          .module-header td { background: #2c3e50; color: #fff; font-weight: 700; font-size: 10px; }
          @media print { body { margin: 0; } }
        </style></head><body>`);
        win.document.write(`<h4 style="text-align:center;margin-bottom:10px">Planning — ${mois}</h4>`); // separate window, no access to page CSS
        win.document.write(content.innerHTML);
        win.document.write('</body></html>');
        win.document.close();
        setTimeout(() => { win.print(); }, 500);
    }

    // ── Export PDF (via print dialog) ──
    function exportPdf() {
        showToast('Utilisez "Enregistrer en PDF" dans la boîte de dialogue d\'impression', 'info');
        printPlanning();
    }

    // ── Email planning ──
    let emailModalInstance = null;
    function emailPlanning() {
        if (!planning) { showToast('Aucun planning chargé', 'error'); return; }
        if (!emailModalInstance) {
            emailModalInstance = new bootstrap.Modal(document.getElementById('emailPlanningModal'));
            document.getElementById('btnSendEmail')?.addEventListener('click', sendEmailPlanning);
        }
        // Reset state
        document.getElementById('emailProgress').classList.add('d-hidden');
        document.getElementById('btnSendEmail').disabled = false;
        emailModalInstance.show();
    }

    async function sendEmailPlanning() {
        const btn = document.getElementById('btnSendEmail');
        const progress = document.getElementById('emailProgress');
        const bar = document.getElementById('emailProgressBar');
        const text = document.getElementById('emailProgressText');
        const dest = document.querySelector('input[name="emailDest"]:checked')?.value || 'all';
        const moduleId = zerdaSelect.getValue('#planningModuleFilter');
        const message = document.getElementById('emailMessage').value;
        const mois = document.getElementById('planningMois').value;

        btn.disabled = true;
        progress.classList.remove('d-hidden');
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated progress-w10';
        text.textContent = 'Préparation...';

        const res = await adminApiPost('admin_send_planning_email', {
            planning_id: planning.id,
            mois,
            dest,
            module_id: dest === 'module' ? moduleId : null,
            message,
        });

        if (res.success) {
            bar.classList.remove('progress-bar-animated', 'progress-w10');
            bar.classList.add('bg-success', 'progress-w100');
            text.textContent = `${res.sent || 0} email(s) envoyé(s)`;
            showToast(`${res.sent || 0} email(s) envoyé(s)`, 'success');
            setTimeout(() => emailModalInstance.hide(), 2000);
        } else {
            bar.classList.add('bg-danger');
            text.textContent = res.message || 'Erreur lors de l\'envoi';
            showToast(res.message || 'Erreur', 'error');
            btn.disabled = false;
        }
    }

    // ── Helpers ──
    function fmtDate(d) {
        return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
    }
    function fmtISO(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    // ── Formation popover (hover) ──
    let _fpEl = null;
    let _fpHideTimer = null;
    let _fpAnchor = null;
    let _fpScrollBound = false;

    function ensureFormationPopover() {
        if (_fpEl) return _fpEl;
        _fpEl = document.createElement('div');
        _fpEl.className = 'formation-popover';
        _fpEl.style.cssText = 'position:fixed;display:none;z-index:9999;pointer-events:auto;';
        document.body.appendChild(_fpEl);
        _fpEl.addEventListener('mouseenter', () => clearTimeout(_fpHideTimer));
        _fpEl.addEventListener('mouseleave', () => hideFormationPopoverWithDelay());

        // Une seule fois : binder scroll/resize/wheel pour cacher
        if (!_fpScrollBound) {
            const hide = () => hideFormationPopoverImmediate();
            window.addEventListener('scroll', hide, { passive: true, capture: true });
            window.addEventListener('wheel', hide, { passive: true });
            window.addEventListener('resize', hide, { passive: true });
            // Scroll dans le wrapper grille (horizontal/vertical)
            const gridWrap = document.querySelector('.tr-grid-wrap');
            if (gridWrap) gridWrap.addEventListener('scroll', hide, { passive: true });
            _fpScrollBound = true;
        }
        return _fpEl;
    }

    function hideFormationPopoverImmediate() {
        clearTimeout(_fpHideTimer);
        if (_fpEl) _fpEl.style.display = 'none';
        _fpAnchor = null;
    }

    // Replace ancienne fonction de positionnement avec calcul plus complet
    function positionFormationPopover(iconEl) {
        const pop = _fpEl;
        if (!pop) return;
        const PAD = 10;             // marge écran
        const ARROW_GAP = 12;       // espace entre cellule et popover
        // Force display=block pour mesurer
        pop.style.visibility = 'hidden';
        pop.style.display = 'block';
        const rect = iconEl.getBoundingClientRect();
        const popW = pop.offsetWidth;
        const popH = pop.offsetHeight;
        const winW = window.innerWidth;
        const winH = window.innerHeight;

        const spaceBelow = winH - rect.bottom;
        const spaceAbove = rect.top;
        const spaceRight = winW - rect.right;
        const spaceLeft  = rect.left;

        // Vertical : préfère en bas, sinon en haut, sinon meilleur compromis
        let placeAbove = false;
        if (spaceBelow < popH + ARROW_GAP + PAD && spaceAbove > spaceBelow) {
            placeAbove = true;
        }

        let top = placeAbove
            ? rect.top - popH - ARROW_GAP
            : rect.bottom + ARROW_GAP;
        // Clamp vertical (cas extrême : pas assez de place ni en haut ni en bas)
        if (top < PAD) top = PAD;
        if (top + popH > winH - PAD) top = winH - popH - PAD;

        // Horizontal : centré sur l'icône, clamped
        let left = rect.left + (rect.width / 2) - (popW / 2);
        if (left + popW > winW - PAD) left = winW - popW - PAD;
        if (left < PAD) left = PAD;

        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        pop.classList.toggle('fp-above', placeAbove);

        // Position de la flèche : recentrer sur l'icône
        const arrow = pop.querySelector('.fp-arrow');
        if (arrow) {
            const iconCenter = rect.left + rect.width / 2;
            const arrowOffset = Math.max(14, Math.min(popW - 14, iconCenter - left));
            arrow.style.left = arrowOffset + 'px';
            arrow.style.transform = placeAbove
                ? 'translateX(-50%) rotate(45deg)'
                : 'translateX(-50%) rotate(45deg)';
        }
        pop.style.visibility = 'visible';
    }

    function showFormationPopover(iconEl) {
        clearTimeout(_fpHideTimer);
        const uid = iconEl.dataset.formUid;
        const date = iconEl.dataset.formDate;
        const info = formIdx[uid + '_' + date];
        if (!info) return;

        const pop = ensureFormationPopover();

        // Type & icône : colorisation par catégorie
        const typeMeta = {
            'interne':    { lbl: 'Interne',    bg: '#e3f0ea', fg: '#3d8b6b', ico: 'bi-building' },
            'externe':    { lbl: 'Externe',    bg: '#e2ecf2', fg: '#3a6a8a', ico: 'bi-cloud-arrow-up' },
            'e-learning': { lbl: 'E-learning', bg: '#f3eef0', fg: '#7a3a5d', ico: 'bi-laptop' },
            'certificat': { lbl: 'Certificat', bg: '#ecf5f3', fg: '#1f6359', ico: 'bi-award' },
        };
        const tm = typeMeta[info.type] || typeMeta['externe'];

        // Dates affichage
        const fmtDate = d => {
            const dt = new Date(d + 'T00:00:00');
            return dt.toLocaleDateString('fr-CH', { day:'2-digit', month:'short', year:'numeric' });
        };
        const dateRange = info.date_debut === info.date_fin
            ? fmtDate(info.date_debut)
            : `${fmtDate(info.date_debut)} → ${fmtDate(info.date_fin)}`;
        const horaires = (info.heure_debut && info.heure_fin)
            ? `${info.heure_debut.slice(0,5)} – ${info.heure_fin.slice(0,5)}`
            : null;

        const obligBadge = info.is_obligatoire
            ? '<span class="fp-tag fp-tag-oblig"><i class="bi bi-shield-check"></i> Obligatoire</span>'
            : '<span class="fp-tag fp-tag-opt">Optionnelle</span>';
        const statutLbl = {
            'inscrit': '<span class="fp-tag fp-tag-info"><i class="bi bi-pencil-square"></i> Inscrit</span>',
            'present': '<span class="fp-tag fp-tag-ok"><i class="bi bi-check-circle"></i> Présent</span>',
            'valide':  '<span class="fp-tag fp-tag-ok"><i class="bi bi-check-circle-fill"></i> Validé</span>',
        }[info.statut] || '';

        pop.innerHTML = `
            <div class="fp-arrow"></div>
            <div class="fp-head" style="background:linear-gradient(135deg, #1f6359, #2d8074)">
                <div class="fp-head-ico" style="background:rgba(255,255,255,.18)"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="fp-head-body">
                    <div class="fp-eyebrow">FORMATION FEGEMS</div>
                    <div class="fp-titre">${escapeHtml(info.titre)}</div>
                </div>
            </div>
            <div class="fp-body">
                <div class="fp-tags">
                    <span class="fp-tag" style="background:${tm.bg};color:${tm.fg}"><i class="bi ${tm.ico}"></i> ${tm.lbl}</span>
                    ${obligBadge}
                    ${statutLbl}
                </div>
                <div class="fp-rows">
                    <div class="fp-row">
                        <i class="bi bi-calendar3 fp-row-ico"></i>
                        <div><div class="fp-row-lbl">Date${info.nb_jours > 1 ? 's' : ''}</div><div class="fp-row-val">${dateRange}${info.nb_jours > 1 ? ` <span class="fp-mono">· ${info.nb_jours} jours</span>` : ''}</div></div>
                    </div>
                    ${horaires ? `<div class="fp-row"><i class="bi bi-clock fp-row-ico"></i><div><div class="fp-row-lbl">Horaires</div><div class="fp-row-val fp-mono">${horaires}</div></div></div>` : ''}
                    <div class="fp-row">
                        <i class="bi bi-hourglass-split fp-row-ico"></i>
                        <div><div class="fp-row-lbl">Durée</div><div class="fp-row-val fp-mono">${info.heures_jour}h / jour${info.duree_heures > 0 ? ` <span style="color:#6b8783">· ${info.duree_heures}h total</span>` : ''}</div></div>
                    </div>
                    ${info.lieu ? `<div class="fp-row"><i class="bi bi-geo-alt fp-row-ico"></i><div><div class="fp-row-lbl">Lieu</div><div class="fp-row-val">${escapeHtml(info.lieu)}</div></div></div>` : ''}
                    ${info.modalite ? `<div class="fp-row"><i class="bi bi-display fp-row-ico"></i><div><div class="fp-row-lbl">Modalité</div><div class="fp-row-val">${escapeHtml(info.modalite[0].toUpperCase() + info.modalite.slice(1))}</div></div></div>` : ''}
                </div>
                <div class="fp-foot">
                    <div class="fp-foot-info"><i class="bi bi-info-circle"></i> Heures comptées comme travaillées</div>
                </div>
            </div>
        `;

        _fpAnchor = iconEl;
        positionFormationPopover(iconEl);
    }

    function hideFormationPopoverWithDelay() {
        clearTimeout(_fpHideTimer);
        _fpHideTimer = setTimeout(() => {
            if (_fpEl) _fpEl.style.display = 'none';
        }, 180);
    }

    window.initPlanningPage = initPlanningPage;
})();
</script>
