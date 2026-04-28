<?php
// Stats SSR rapides
$nbProp     = (int) Db::getOne("SELECT COUNT(*) FROM inscription_propositions WHERE statut IN ('proposee','en_validation')");
$nbEnvoyees = (int) Db::getOne("SELECT COUNT(*) FROM inscription_propositions WHERE statut = 'envoyee'");
$nbSessions = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'ouverte' AND date_debut >= CURDATE()");
$cotisation = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.cotisation_fegems_active'") === '1';
$emailFegems = Db::getOne("SELECT valeur FROM parametres_formation WHERE cle = 'insc.email_destinataire_fegems'") ?: 'inscription@fegems.ch';
?>

<!-- Hero teal -->
<div class="comp-hero">
  <div class="comp-hero-inner">
    <div style="flex:1;min-width:280px">
      <div class="comp-hero-label">Module formations · Inscriptions automatisées</div>
      <h1>Inscriptions FEGEMS</h1>
      <div class="comp-hero-sub">SpocSpace identifie les écarts dans votre équipe et les croise avec le <strong style="color:#a8e6c9">catalogue FEGEMS</strong>. Validez les suggestions ou parcourez le catalogue complet.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
      <button class="btn-on-dark" id="rhfRegen" type="button">
        <i class="bi bi-arrow-repeat"></i> Resync. catalogue
      </button>
      <a href="?page=rh-formations-sessions" class="btn-on-light" data-page-link>
        <i class="bi bi-calendar3"></i> Catalogue complet
      </a>
    </div>
  </div>
</div>

<!-- Bannière intel -->
<div class="comp-intel" id="rhfIntel">
  <div class="comp-intel-head">
    <div class="comp-intel-icon"><i class="bi bi-stars"></i></div>
    <div>
      <div class="comp-intel-tag">Suggestions automatiques · <?= date('d.m.Y') ?></div>
      <h3 id="rhfIntelTitle"><?= $nbProp ?> inscriptions recommandées cette semaine</h3>
    </div>
  </div>
  <p>Détectées à partir des écarts de la cartographie d'équipe et du calendrier des sessions FEGEMS disponibles.<?php if ($cotisation): ?> EMS membre FEGEMS : formations gratuites incluses dans la cotisation.<?php endif ?></p>
  <div class="comp-intel-stats" id="rhfIntelStats">
    <div class="comp-intel-stat"><div class="v" id="rhfStatUrgent">—</div><div class="l">Urgentes (expiration)</div></div>
    <div class="comp-intel-stat"><div class="v" id="rhfStatInc">—</div><div class="l">Nouveaux collab. INC</div></div>
    <div class="comp-intel-stat"><div class="v" id="rhfStatCout">—</div><div class="l">Coût total estimé</div></div>
    <div class="comp-intel-stat"><div class="v"><?= $nbEnvoyees ?></div><div class="l">Déjà envoyées</div></div>
  </div>
</div>

<!-- Section header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0" style="font-weight:600">Suggestions prioritaires</h5>
  <span class="text-muted small">Triées par échéance · destinataire <code><?= h($emailFegems) ?></code></span>
</div>

<!-- Grille de suggestions -->
<div class="comp-sugg-grid" id="rhfSuggGrid">
  <div class="text-center text-muted py-5" style="grid-column:1/-1">
    <span class="spinner-border spinner-border-sm"></span> Chargement des suggestions…
  </div>
</div>

<!-- ═══ Modal aperçu email ═══ -->
<div class="modal fade" id="rhfEmailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--comp-teal-700),var(--comp-teal-500));color:#fff">
        <div>
          <div style="font-size:.65rem;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:700">Brouillon prêt à envoyer</div>
          <h5 class="modal-title mt-1" id="rhfEmailTitle">Email d'inscription</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="row g-0">
          <div class="col-lg-8">
            <div class="p-4">
              <div class="mb-3 pb-3 border-bottom small">
                <div class="row g-2">
                  <div class="col-3 text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">De</div>
                  <div class="col-9 fw-bold" id="rhfEmailFrom">—</div>
                  <div class="col-3 text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">À</div>
                  <div class="col-9 fw-bold" id="rhfEmailTo">—</div>
                  <div class="col-3 text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Cc</div>
                  <div class="col-9 text-muted" id="rhfEmailCc">—</div>
                  <div class="col-3 text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Objet</div>
                  <div class="col-9 fw-bold" style="font-size:.95rem" id="rhfEmailSubject">—</div>
                </div>
              </div>
              <div id="rhfEmailBody" style="line-height:1.6;font-size:.88rem"></div>
            </div>
          </div>
          <div class="col-lg-4" style="background:var(--cl-bg)">
            <div class="p-4">
              <h6 class="text-uppercase text-muted" style="font-size:.65rem;letter-spacing:.08em;font-weight:700">Workflow d'inscription</h6>
              <div class="bg-white border rounded p-3 small mt-2">
                <div class="d-flex align-items-center gap-2 py-1"><span class="badge bg-success">✓</span> Suggestion générée</div>
                <div class="d-flex align-items-center gap-2 py-1"><span class="badge bg-success">✓</span> Collaborateurs vérifiés</div>
                <div class="d-flex align-items-center gap-2 py-1"><span class="badge" style="background:var(--comp-teal-600);color:#fff">3</span> <strong>Email à envoyer</strong></div>
                <div class="d-flex align-items-center gap-2 py-1 text-muted"><span class="badge bg-light text-muted border">4</span> Confirmation FEGEMS</div>
                <div class="d-flex align-items-center gap-2 py-1 text-muted"><span class="badge bg-light text-muted border">5</span> Suivi & attestation</div>
              </div>

              <h6 class="text-uppercase text-muted mt-4" style="font-size:.65rem;letter-spacing:.08em;font-weight:700">Après envoi</h6>
              <ul class="small text-muted mt-2 ps-3" style="line-height:1.6">
                <li>Statut "En attente" sur les fiches employés</li>
                <li>Rappel auto à J-7 de la session</li>
                <li>Upload attestation à J+3 après session</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="rhfEmailReject">
          <i class="bi bi-x-circle"></i> Rejeter la suggestion
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="rhfEmailCopy">
          <i class="bi bi-clipboard"></i> Copier le corps
        </button>
        <a href="#" class="btn btn-sm btn-outline-primary" id="rhfEmailMailto">
          <i class="bi bi-envelope"></i> Ouvrir client mail
        </a>
        <button type="button" class="btn btn-sm btn-success" id="rhfEmailMarkSent">
          <i class="bi bi-check2-all"></i> Marquer comme envoyée
        </button>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let propositions = [];
    let modal = null;
    let currentPropId = null;

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function initials(p, n) { return ((p?.[0] ?? '') + (n?.[0] ?? '')).toUpperCase(); }
    function avatarColor(seed) {
        const colors = ['#1f6359', '#c46658', '#5a82a8', '#9268b3', '#d49039', '#a89a3d', '#6b9558'];
        let h = 0; for (let i = 0; i < (seed || '').length; i++) h = (h * 31 + seed.charCodeAt(i)) % 1000;
        return colors[h % colors.length];
    }

    function fmtDate(d) { return new Date(d + 'T00:00:00').toLocaleDateString('fr-CH', { day: '2-digit', month: '2-digit', year: 'numeric' }); }
    function daysUntil(d) { return Math.ceil((new Date(d + 'T00:00:00') - new Date()) / 86400000); }

    function classFor(motif) {
        if (motif === 'renouvellement_expire') return ['urgent', 'urgent', '⚠ Urgent'];
        if (motif === 'inc_nouveau')          return ['warn',   'soon',   'À planifier'];
        if (motif === 'plan_cantonal')        return ['info',   'opt',    'Plan cantonal'];
        return ['info', 'opt', 'Recommandé'];
    }

    function renderSuggestions() {
        const grid = document.getElementById('rhfSuggGrid');
        if (!propositions.length) {
            grid.innerHTML = '<div class="comp-empty" style="grid-column:1/-1"><i class="bi bi-stars"></i>Aucune suggestion en attente. La cartographie est-elle à jour ?</div>';
            return;
        }
        grid.innerHTML = propositions.map(p => {
            const [cardCls, tagCls, tagLabel] = classFor(p.type_motif);
            const days = daysUntil(p.deadline_action || p.date_debut);
            const cost = (p.cout_membre == 0 || (window.__rhfMembre)) ? 'CHF 0 (membre FEGEMS)' : 'CHF ' + parseFloat(p.cout_non_membre || 0).toFixed(0);
            const candidatsAvatars = (p.candidats || []).slice(0, 5).map(c =>
                '<div class="av" style="background:' + avatarColor(c.id) + '">' + initials(c.prenom, c.nom) + '</div>'
            ).join('') + (p.nb_candidats > 5 ? '<div class="more">+' + (p.nb_candidats - 5) + '</div>' : '');

            return '<div class="comp-sugg-card ' + cardCls + '">'
                + '<div class="comp-sugg-head">'
                +   '<span class="comp-sugg-tag ' + tagCls + '">' + tagLabel + '</span>'
                +   '<div class="comp-sugg-deadline"><strong>' + fmtDate(p.date_debut) + '</strong>'
                +     'dans ' + days + ' jour' + (days > 1 ? 's' : '') + '</div>'
                + '</div>'
                + '<div class="comp-sugg-body">'
                +   '<h4>' + escapeHtml(p.formation_titre) + '</h4>'
                +   '<div class="meta">'
                +     '<span class="pip"><i class="bi bi-geo-alt"></i> ' + escapeHtml(p.lieu || '—') + '</span>'
                +     '<span class="dot"></span>'
                +     '<span class="pip"><i class="bi bi-clock"></i> ' + (p.duree_heures ? p.duree_heures + 'h' : '—') + '</span>'
                +     '<span class="dot"></span>'
                +     '<span class="pip">' + escapeHtml(p.modalite || '—') + '</span>'
                +   '</div>'
                + '</div>'
                + '<div class="comp-sugg-rationale">'
                +   '<strong>Pourquoi ?</strong> ' + escapeHtml(p.libelle_motif || '')
                + '</div>'
                + '<div class="comp-sugg-collabs">'
                +   '<div class="lbl">' + p.nb_candidats + ' collaborateur' + (p.nb_candidats > 1 ? 's' : '') + ' concerné' + (p.nb_candidats > 1 ? 's' : '') + '</div>'
                +   '<div class="comp-sugg-stack">' + candidatsAvatars + '</div>'
                + '</div>'
                + '<div class="comp-sugg-foot">'
                +   '<div class="comp-sugg-cost">Coût · <strong>' + cost + '</strong></div>'
                +   '<button class="comp-sugg-action" data-prop="' + p.id + '">Préparer email <i class="bi bi-arrow-right"></i></button>'
                + '</div>'
                + '</div>';
        }).join('');

        grid.querySelectorAll('.comp-sugg-action').forEach(b => {
            b.addEventListener('click', () => openEmailModal(b.dataset.prop));
        });
    }

    function loadPropositions() {
        adminApiPost('admin_get_inscriptions_propositions', {}).then(r => {
            if (!r.success) return;
            propositions = r.propositions || [];
            window.__rhfMembre = r.stats?.membre_fegems;
            const stats = r.stats || {};
            document.getElementById('rhfIntelTitle').textContent =
                propositions.length + ' inscription' + (propositions.length > 1 ? 's' : '') + ' recommandée' + (propositions.length > 1 ? 's' : '');
            document.getElementById('rhfStatUrgent').textContent = stats.urgent || 0;
            document.getElementById('rhfStatInc').textContent = stats.inc || 0;
            document.getElementById('rhfStatCout').textContent = stats.membre_fegems
                ? 'CHF 0'
                : 'CHF ' + Number(stats.cout_total || 0).toLocaleString('fr-CH');
            renderSuggestions();
        });
    }

    function openEmailModal(propId) {
        currentPropId = propId;
        const p = propositions.find(x => x.id === propId);
        if (!p) return;

        document.getElementById('rhfEmailTitle').textContent = 'Inscription · ' + p.formation_titre;

        adminApiPost('admin_send_inscription_email', { proposition_id: propId, dry_run: 1 }).then(r => {
            // Note : on appelle send qui crée déjà la trace ; on affichera le contenu généré
            if (!r.success) { showToast(r.error || 'Erreur', 'danger'); return; }
            document.getElementById('rhfEmailFrom').textContent = '<?= h($_SESSION['ss_user']['email'] ?? '—') ?>';
            document.getElementById('rhfEmailTo').textContent = r.destinataire;
            document.getElementById('rhfEmailCc').textContent = r.cc || '(aucun)';
            document.getElementById('rhfEmailSubject').textContent = r.sujet;
            document.getElementById('rhfEmailBody').innerHTML = r.corps_html;
            // Mailto link
            const mailto = 'mailto:' + encodeURIComponent(r.destinataire)
                + (r.cc ? '?cc=' + encodeURIComponent(r.cc) + '&' : '?')
                + 'subject=' + encodeURIComponent(r.sujet)
                + '&body=' + encodeURIComponent(r.corps_html.replace(/<[^>]+>/g, ''));
            document.getElementById('rhfEmailMailto').href = mailto;
            modal.show();
            // Reload propositions pour refléter le statut "envoyee"
            setTimeout(loadPropositions, 500);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('rhfEmailModal');
        if (modalEl) modal = new bootstrap.Modal(modalEl);

        document.getElementById('rhfRegen')?.addEventListener('click', () => {
            const btn = document.getElementById('rhfRegen');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Resync...';
            adminApiPost('admin_regenerer_propositions', {}).then(r => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Resync. catalogue';
                if (!r.success) { showToast(r.error || 'Erreur', 'danger'); return; }
                showToast('Suggestions regénérées', 'success');
                loadPropositions();
            });
        });

        document.getElementById('rhfEmailReject')?.addEventListener('click', async () => {
            if (!currentPropId) return;
            const ok = await ssConfirm({
                title: 'Rejeter la suggestion',
                message: 'Elle ne sera plus proposée pour cette session.',
                confirmText: 'Rejeter',
                confirmClass: 'btn-warning',
                icon: 'bi-x-circle'
            });
            if (!ok) return;
            adminApiPost('admin_reject_proposition', { id: currentPropId }).then(r => {
                if (!r.success) return;
                modal.hide();
                showToast('Suggestion rejetée', 'success');
                loadPropositions();
            });
        });

        document.getElementById('rhfEmailMarkSent')?.addEventListener('click', () => {
            if (!currentPropId) return;
            // Sans dry_run : enregistre la trace + bascule la proposition en 'envoyee'
            adminApiPost('admin_send_inscription_email', { proposition_id: currentPropId }).then(r => {
                if (!r.success) { showToast(r.error || 'Erreur', 'danger'); return; }
                modal.hide();
                showToast('Email marqué comme envoyé', 'success');
                loadPropositions();
            });
        });

        document.getElementById('rhfEmailCopy')?.addEventListener('click', () => {
            const html = document.getElementById('rhfEmailBody').innerHTML;
            const text = document.getElementById('rhfEmailBody').innerText;
            navigator.clipboard.writeText(text).then(() => showToast('Copié dans le presse-papier', 'success'));
        });

        loadPropositions();
    });
})();
</script>
