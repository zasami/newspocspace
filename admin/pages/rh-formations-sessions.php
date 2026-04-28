<?php
$nbSessions   = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions");
$nbOuvertes   = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'ouverte' AND date_debut >= CURDATE()");
$nbCompletes  = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE statut = 'complete'");
$nbPassees    = (int) Db::getOne("SELECT COUNT(*) FROM formation_sessions WHERE date_debut < CURDATE()");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-calendar3"></i> Sessions &amp; catalogue</h4>
  <div>
    <a href="?page=rh-formations-fegems" class="btn btn-sm btn-outline-secondary" data-page-link>
      <i class="bi bi-cloud-arrow-up"></i> Inscriptions
    </a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-teal"><i class="bi bi-calendar3"></i></div>
      <div><div class="stat-value"><?= $nbSessions ?></div><div class="stat-label">Sessions au total</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-green"><i class="bi bi-calendar-check"></i></div>
      <div><div class="stat-value"><?= $nbOuvertes ?></div><div class="stat-label">Ouvertes &amp; à venir</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-orange"><i class="bi bi-calendar-x"></i></div>
      <div><div class="stat-value"><?= $nbCompletes ?></div><div class="stat-label">Complètes</div></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="stat-icon bg-purple"><i class="bi bi-archive"></i></div>
      <div><div class="stat-value"><?= $nbPassees ?></div><div class="stat-label">Passées (archivées)</div></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
    <span class="small text-muted me-2 fw-bold" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem">Période</span>
    <button class="btn btn-sm rhs-filter active" data-periode="a_venir" style="font-size:.78rem;border-radius:99px;background:#2d4a43;color:#fff">À venir</button>
    <button class="btn btn-sm rhs-filter" data-periode="" style="font-size:.78rem;border-radius:99px;background:#fff">Toutes</button>
    <button class="btn btn-sm rhs-filter" data-periode="passees" style="font-size:.78rem;border-radius:99px;background:#fff">Passées</button>
    <span class="vr mx-2"></span>
    <span class="small text-muted me-2 fw-bold" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem">Statut</span>
    <button class="btn btn-sm rhs-filter-statut active" data-statut="" style="font-size:.78rem;border-radius:99px;background:#2d4a43;color:#fff">Tous</button>
    <button class="btn btn-sm rhs-filter-statut" data-statut="ouverte" style="font-size:.78rem;border-radius:99px;background:#fff">Ouvertes</button>
    <button class="btn btn-sm rhs-filter-statut" data-statut="complete" style="font-size:.78rem;border-radius:99px;background:#fff">Complètes</button>
    <button class="btn btn-sm rhs-filter-statut" data-statut="passee" style="font-size:.78rem;border-radius:99px;background:#fff">Passées</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0" style="font-weight:600">Sessions <span class="text-muted small fw-normal" id="rhsCount"></span></h5>
  </div>
  <div id="rhsList">
    <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm"></span></div>
  </div>
</div>

<!-- Modal détail session -->
<div class="modal fade" id="rhsSessModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rhsSessTitle">Session</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="rhsSessBody"></div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
(function() {
    let sessions = [];
    let currentPeriode = 'a_venir';
    let currentStatut = '';
    let modal = null;

    function escapeHtml(s) { const t = document.createElement('span'); t.textContent = s ?? ''; return t.innerHTML; }
    function fmtDate(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('fr-CH', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'; }

    function statutBadge(s) {
        const map = {
            ouverte: ['Ouverte', '#bcd2cb', '#2d4a43'],
            complete: ['Complète', '#D4C4A8', '#6B5B3E'],
            liste_attente: ['Liste attente', '#E2B8AE', '#7B3B2C'],
            annulee: ['Annulée', '#E2B8AE', '#7B3B2C'],
            passee: ['Passée', '#F0EDE8', '#6B6B6B'],
        };
        const [lbl, bg, fg] = map[s] || [s, '#F0EDE8', '#666'];
        return '<span style="display:inline-block;padding:2px 9px;border-radius:5px;font-size:.68rem;font-weight:600;background:' + bg + ';color:' + fg + '">' + escapeHtml(lbl) + '</span>';
    }

    function placesBar(s) {
        if (!s.capacite_max) return '<span class="text-muted small">—</span>';
        const pct = Math.min(100, Math.round((s.places_inscrites / s.capacite_max) * 100));
        const cls = pct >= 100 ? 'bg-danger' : (pct >= 80 ? 'bg-warning' : 'bg-success');
        return '<div class="d-flex align-items-center gap-2" style="min-width:120px">'
            + '<div class="progress flex-grow-1" style="height:5px"><div class="progress-bar ' + cls + '" style="width:' + pct + '%"></div></div>'
            + '<span class="small text-muted" style="font-family:monospace;white-space:nowrap">' + s.places_inscrites + '/' + s.capacite_max + '</span></div>';
    }

    function renderList() {
        const c = document.getElementById('rhsList');
        document.getElementById('rhsCount').textContent = sessions.length + ' résultat' + (sessions.length > 1 ? 's' : '');
        if (!sessions.length) {
            c.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:2rem;opacity:.3;display:block"></i>Aucune session</div>';
            return;
        }
        let html = '<table class="table table-hover mb-0" style="font-size:.85rem">';
        html += '<thead class="table-light"><tr><th style="width:80px">Date</th><th>Formation</th><th>Lieu / format</th><th>Capacité</th><th>Statut</th><th></th></tr></thead><tbody>';
        sessions.forEach(s => {
            html += '<tr>'
                + '<td style="font-family:monospace;font-weight:600">' + fmtDate(s.date_debut) + '</td>'
                + '<td><div class="fw-bold">' + escapeHtml(s.formation_titre) + '</div>'
                +   '<div class="small text-muted">' + escapeHtml(s.type) + ' · ' + (s.duree_heures ? s.duree_heures + 'h' : '—') + '</div></td>'
                + '<td><div>' + escapeHtml(s.lieu || '—') + '</div>'
                +   '<div class="small text-muted">' + escapeHtml(s.modalite) + '</div></td>'
                + '<td>' + placesBar(s) + '</td>'
                + '<td>' + statutBadge(s.statut) + '</td>'
                + '<td><button class="btn btn-sm btn-outline-secondary rhs-detail" data-id="' + s.id + '"><i class="bi bi-eye"></i></button></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        c.innerHTML = html;
        c.querySelectorAll('.rhs-detail').forEach(b => b.addEventListener('click', () => openDetail(b.dataset.id)));
    }

    function loadSessions() {
        adminApiPost('admin_get_formation_sessions', {
            statut: currentStatut, periode: currentPeriode
        }).then(r => {
            if (!r.success) return;
            sessions = r.sessions || [];
            renderList();
        });
    }

    function openDetail(id) {
        adminApiPost('admin_get_session_detail', { id }).then(r => {
            if (!r.success) return;
            const s = r.session;
            document.getElementById('rhsSessTitle').textContent = s.formation_titre;
            const parts = r.participants || [];
            const partList = parts.length
                ? '<table class="table table-sm mb-0"><thead><tr><th>Collaborateur</th><th>Fonction</th><th>Statut</th><th>Heures</th><th>Coût</th></tr></thead><tbody>'
                  + parts.map(p =>
                    '<tr><td><strong>' + escapeHtml(p.prenom + ' ' + p.nom) + '</strong><div class="small text-muted">' + escapeHtml(p.email) + '</div></td>'
                    + '<td>' + escapeHtml(p.fonction_nom || '—') + '</td>'
                    + '<td>' + escapeHtml(p.statut || '—') + '</td>'
                    + '<td>' + (p.heures_realisees ? p.heures_realisees + 'h' : '—') + '</td>'
                    + '<td>' + (p.cout_individuel > 0 ? 'CHF ' + p.cout_individuel : '—') + '</td></tr>'
                  ).join('') + '</tbody></table>'
                : '<div class="comp-empty"><i class="bi bi-people"></i>Aucun participant</div>';

            document.getElementById('rhsSessBody').innerHTML =
                '<div class="row g-3 mb-3 small">'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Date</div><div class="fw-bold">' + fmtDate(s.date_debut) + (s.date_fin && s.date_fin !== s.date_debut ? ' → ' + fmtDate(s.date_fin) : '') + '</div></div>'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Horaires</div><div class="fw-bold">' + (s.heure_debut ? s.heure_debut + ' - ' + s.heure_fin : '—') + '</div></div>'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Lieu</div><div class="fw-bold">' + escapeHtml(s.lieu || '—') + '</div></div>'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Modalité</div><div class="fw-bold">' + escapeHtml(s.modalite) + '</div></div>'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Capacité</div><div class="fw-bold">' + (s.capacite_max ? s.places_inscrites + ' / ' + s.capacite_max : 'Illimitée') + '</div></div>'
                +   '<div class="col-md-4"><div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.06em">Coût (membre)</div><div class="fw-bold">' + (s.cout_membre > 0 ? 'CHF ' + s.cout_membre : 'Gratuit') + '</div></div>'
                + '</div>'
                + '<h6 class="text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.08em">Participants (' + parts.length + ')</h6>'
                + partList;
            modal.show();
        });
    }

    function setActive(selector, val, attr) {
        document.querySelectorAll(selector).forEach(b => {
            const isActive = (b.dataset[attr] || '') === (val || '');
            b.classList.toggle('active', isActive);
            b.style.background = isActive ? '#2d4a43' : '#fff';
            b.style.color = isActive ? '#fff' : '';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const m = document.getElementById('rhsSessModal');
        if (m) modal = new bootstrap.Modal(m);

        document.querySelectorAll('.rhs-filter').forEach(b => {
            b.addEventListener('click', () => {
                currentPeriode = b.dataset.periode || '';
                setActive('.rhs-filter', currentPeriode, 'periode');
                loadSessions();
            });
        });
        document.querySelectorAll('.rhs-filter-statut').forEach(b => {
            b.addEventListener('click', () => {
                currentStatut = b.dataset.statut || '';
                setActive('.rhs-filter-statut', currentStatut, 'statut');
                loadSessions();
            });
        });

        loadSessions();
    });
})();
</script>
