<?php
/**
 * SpocCare — Admissions
 * Gestion des demandes d'admission soumises via le formulaire public
 */

$stats = Db::fetch(
    "SELECT
       COUNT(*) AS total,
       SUM(statut = 'demande_envoyee') AS en_attente,
       SUM(statut = 'en_examen') AS en_examen,
       SUM(statut = 'etape1_validee') AS validees,
       SUM(statut = 'info_manquante') AS info_manquante,
       SUM(statut = 'refuse') AS refusees,
       SUM(statut = 'acceptee_liste_attente') AS acceptees,
       SUM(type_demande = 'urgente' AND statut IN ('demande_envoyee','en_examen')) AS urgentes_actives
     FROM admissions_candidats"
) ?: [];
?>
<div class="container-fluid py-3">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0"><i class="bi bi-file-earmark-person"></i> Admissions</h5>
      <small class="text-muted">Demandes d'inscription en ligne (étape 1 du dossier)</small>
    </div>
    <div class="d-flex gap-2">
      <a href="/newspocspace/website/admissions.php#formulaire" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right"></i> Voir formulaire public
      </a>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-lg">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="text-muted small">Total</div>
          <div class="fs-4 fw-semibold"><?= (int)($stats['total'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg">
      <div class="card border-0 shadow-sm" style="background:var(--cl-orange-bg)">
        <div class="card-body p-3">
          <div class="small" style="color:var(--cl-orange-fg)">En attente</div>
          <div class="fs-4 fw-semibold" style="color:var(--cl-orange-fg)"><?= (int)($stats['en_attente'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg">
      <div class="card border-0 shadow-sm" style="background:var(--cl-green-bg)">
        <div class="card-body p-3">
          <div class="small" style="color:var(--cl-green-fg)">En examen</div>
          <div class="fs-4 fw-semibold" style="color:var(--cl-green-fg)"><?= (int)($stats['en_examen'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg">
      <div class="card border-0 shadow-sm" style="background:var(--cl-teal-bg)">
        <div class="card-body p-3">
          <div class="small" style="color:var(--cl-teal-fg)">Acceptées</div>
          <div class="fs-4 fw-semibold" style="color:var(--cl-teal-fg)"><?= (int)($stats['acceptees'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg">
      <div class="card border-0 shadow-sm" style="background:var(--cl-red-bg)">
        <div class="card-body p-3">
          <div class="small" style="color:var(--cl-red-fg)">Urgentes actives</div>
          <div class="fs-4 fw-semibold" style="color:var(--cl-red-fg)"><?= (int)($stats['urgentes_actives'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <div class="card mb-3">
    <div class="card-body p-3">
      <div class="row g-2">
        <div class="col-md-6">
          <input type="text" class="form-control form-control-sm" id="admFilterSearch" placeholder="Rechercher (nom résident, référent, email)…">
        </div>
        <div class="col-md-4">
          <select class="form-select form-select-sm" id="admFilterStatut">
            <option value="">Tous les statuts</option>
            <option value="demande_envoyee">Demande envoyée</option>
            <option value="en_examen">En examen</option>
            <option value="etape1_validee">Étape 1 validée</option>
            <option value="info_manquante">Informations manquantes</option>
            <option value="acceptee_liste_attente">Acceptée — liste d'attente</option>
            <option value="refuse">Refusée</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-sm btn-outline-secondary w-100" id="admBtnReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Liste -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="admTable">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Résident</th>
            <th>Personne de référence</th>
            <th>Contact</th>
            <th>Statut</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody id="admTbody">
          <tr><td colspan="7" class="text-center text-muted py-4">Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal fiche -->
<div class="modal fade" id="admModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-person"></i> Dossier d'admission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="admModalBody">
        <div class="text-center text-muted py-4">Chargement…</div>
      </div>
    </div>
  </div>
</div>

<script<?= function_exists('nonce') ? nonce() : '' ?>>
(function(){
  const statutLabels = {
    'demande_envoyee': ['Demande envoyée', 'bg-warning-subtle text-warning-emphasis'],
    'en_examen': ['En examen', 'bg-info-subtle text-info-emphasis'],
    'etape1_validee': ['Étape 1 validée', 'bg-success-subtle text-success-emphasis'],
    'info_manquante': ['Info manquante', 'bg-warning-subtle text-warning-emphasis'],
    'refuse': ['Refusée', 'bg-danger-subtle text-danger-emphasis'],
    'acceptee_liste_attente': ['Acceptée — liste d\'attente', 'bg-success-subtle text-success-emphasis']
  };
  const situationLabels = {
    'domicile': 'À son domicile',
    'trois_chenes': 'Hôpital des Trois-Chênes',
    'hug': 'Aux HUG',
    'autre': 'Autre'
  };

  let currentList = [];

  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleDateString('fr-CH', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  function fmtDateTime(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleString('fr-CH', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function renderList(rows) {
    const tb = document.getElementById('admTbody');
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Aucune demande trouvée</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r => {
      const st = statutLabels[r.statut] || [r.statut, 'bg-secondary-subtle'];
      const typeBadge = r.type_demande === 'urgente'
        ? '<span class="badge bg-danger-subtle text-danger-emphasis">Urgente</span>'
        : '<span class="badge bg-info-subtle text-info-emphasis">Préventive</span>';
      return `
        <tr data-id="${escapeHtml(r.id)}" style="cursor:pointer">
          <td class="small text-muted">${fmtDate(r.created_at)}</td>
          <td>${typeBadge}</td>
          <td><strong>${escapeHtml(r.nom_prenom)}</strong>${r.date_naissance ? `<div class="small text-muted">né(e) le ${fmtDate(r.date_naissance)}</div>` : ''}</td>
          <td>${escapeHtml(r.ref_nom_prenom)}</td>
          <td class="small">
            <div>${escapeHtml(r.ref_email)}</div>
            ${r.ref_telephone ? `<div class="text-muted">${escapeHtml(r.ref_telephone)}</div>` : ''}
          </td>
          <td><span class="badge ${st[1]}">${escapeHtml(st[0])}</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary btn-view" data-id="${escapeHtml(r.id)}">
              <i class="bi bi-eye"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  async function loadList() {
    const search = document.getElementById('admFilterSearch').value.trim();
    const statut = document.getElementById('admFilterStatut').value;
    const r = await adminApiPost('admin_get_admissions', { search, statut });
    if (!r.success) { alert(r.message || 'Erreur'); return; }
    currentList = r.admissions || [];
    renderList(currentList);
  }

  async function openAdmission(id) {
    const body = document.getElementById('admModalBody');
    body.innerHTML = '<div class="text-center text-muted py-4">Chargement…</div>';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('admModal'));
    modal.show();

    const r = await adminApiPost('admin_get_admission', { id });
    if (!r.success) { body.innerHTML = `<div class="alert alert-danger">${escapeHtml(r.message || 'Erreur')}</div>`; return; }

    const c = r.candidat;
    const hist = r.historique || [];
    const st = statutLabels[c.statut] || [c.statut, 'bg-secondary-subtle'];
    const typeBadge = c.type_demande === 'urgente'
      ? '<span class="badge bg-danger-subtle text-danger-emphasis">Urgente</span>'
      : '<span class="badge bg-info-subtle text-info-emphasis">Préventive</span>';

    const aspects = [];
    if (+c.ref_aspect_administratifs) aspects.push('Administratifs');
    if (+c.ref_aspect_soins) aspects.push('Soins');
    if (+c.ref_curateur) aspects.push('Curateur');

    const kv = (label, val) => val ? `<dt class="col-sm-4 text-muted">${escapeHtml(label)}</dt><dd class="col-sm-8">${escapeHtml(val)}</dd>` : '';
    const kvMulti = (label, val) => val ? `<dt class="col-sm-4 text-muted">${escapeHtml(label)}</dt><dd class="col-sm-8" style="white-space:pre-line">${escapeHtml(val)}</dd>` : '';
    const fmtAdresse = (prefix) => {
      const rue   = (c[prefix + 'adresse_rue'] || '').trim();
      const comp  = (c[prefix + 'adresse_complement'] || '').trim();
      const cp    = (c[prefix + 'adresse_cp'] || '').trim();
      const ville = (c[prefix + 'adresse_ville'] || '').trim();
      const lines = [];
      if (rue) lines.push(rue);
      if (comp) lines.push(comp);
      if (cp || ville) lines.push((cp + ' ' + ville).trim());
      if (lines.length) return lines.join('\n');
      return (c[prefix + 'adresse_postale'] || '').trim();
    };

    body.innerHTML = `
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <div class="small text-muted">Soumise le ${fmtDateTime(c.created_at)}</div>
          <h4 class="mb-1">${escapeHtml(c.nom_prenom)} ${typeBadge}</h4>
        </div>
        <span class="badge fs-6 ${st[1]}">${escapeHtml(st[0])}</span>
      </div>

      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#admTab1">Dossier</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#admTab2">Gestion</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#admTab3">Historique (${hist.length})</a></li>
      </ul>
      <div class="tab-content pt-3">

        <div class="tab-pane fade show active" id="admTab1">
          <h6 class="text-uppercase small text-muted mt-2">Personne concernée</h6>
          <dl class="row mb-3">
            ${kv('Nom et prénom', c.nom_prenom)}
            ${kv('Date de naissance', c.date_naissance ? fmtDate(c.date_naissance) : '')}
            ${kvMulti('Adresse postale', fmtAdresse(''))}
            ${kv('Email', c.email)}
            ${kv('Téléphone', c.telephone)}
            ${kv('Situation', (situationLabels[c.situation] || c.situation) + (c.situation === 'autre' && c.situation_autre ? ' — ' + c.situation_autre : ''))}
          </dl>

          <h6 class="text-uppercase small text-muted">Personne de référence</h6>
          <dl class="row mb-3">
            ${kv('Nom et prénom', c.ref_nom_prenom)}
            ${kv('Aspects', aspects.join(' · '))}
            ${kv('Lien de parenté', c.ref_lien_parente)}
            ${kv('Autre', c.ref_autre)}
            ${kvMulti('Adresse postale', fmtAdresse('ref_'))}
            ${kv('Email', c.ref_email)}
            ${kv('Téléphone', c.ref_telephone)}
          </dl>

          ${c.med_nom || c.med_email || c.med_telephone ? `
          <h6 class="text-uppercase small text-muted">Médecin traitant</h6>
          <dl class="row mb-3">
            ${kv('Nom', c.med_nom)}
            ${kvMulti('Adresse postale', fmtAdresse('med_'))}
            ${kv('Email', c.med_email)}
            ${kv('Téléphone', c.med_telephone)}
          </dl>` : ''}

          <div class="small text-muted">
            <i class="bi bi-link-45deg"></i> Lien de suivi famille :
            <a href="/newspocspace/website/admissions-suivi.php?token=${escapeHtml(c.token_acces)}" target="_blank">ouvrir</a>
            · IP : ${escapeHtml(c.ip_soumission || 'n/a')}
          </div>
        </div>

        <div class="tab-pane fade" id="admTab2">
          <div class="mb-3">
            <label class="form-label">Statut du dossier</label>
            <select class="form-select" id="admStatutSel">
              <option value="demande_envoyee" ${c.statut==='demande_envoyee'?'selected':''}>Demande envoyée</option>
              <option value="en_examen" ${c.statut==='en_examen'?'selected':''}>En examen</option>
              <option value="etape1_validee" ${c.statut==='etape1_validee'?'selected':''}>Étape 1 validée</option>
              <option value="info_manquante" ${c.statut==='info_manquante'?'selected':''}>Informations manquantes</option>
              <option value="acceptee_liste_attente" ${c.statut==='acceptee_liste_attente'?'selected':''}>Acceptée — liste d'attente</option>
              <option value="refuse" ${c.statut==='refuse'?'selected':''}>Refusée</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Commentaire (visible uniquement en interne)</label>
            <textarea class="form-control" id="admStatutCom" rows="2" placeholder="Motif du changement…"></textarea>
          </div>
          <button class="btn btn-primary btn-sm" id="admBtnSaveStatut">
            <i class="bi bi-check-lg"></i> Enregistrer le statut
          </button>

          <hr class="my-4">

          <div class="mb-3">
            <label class="form-label">Note interne (non communiquée à la famille)</label>
            <textarea class="form-control" id="admNoteInterne" rows="5">${escapeHtml(c.note_interne || '')}</textarea>
          </div>
          <button class="btn btn-outline-secondary btn-sm" id="admBtnSaveNote">
            <i class="bi bi-save"></i> Enregistrer la note
          </button>

          <hr class="my-4">

          <button class="btn btn-outline-danger btn-sm" id="admBtnDelete">
            <i class="bi bi-trash"></i> Supprimer ce dossier
          </button>
        </div>

        <div class="tab-pane fade" id="admTab3">
          ${hist.length ? hist.map(h => `
            <div class="border-start border-3 border-secondary-subtle ps-3 pb-3 mb-2">
              <div class="small text-muted">${fmtDateTime(h.created_at)}${h.admin_prenom ? ' · ' + escapeHtml(h.admin_prenom + ' ' + h.admin_nom) : ''}</div>
              <div class="fw-semibold">${escapeHtml((h.action || '').replace(/_/g,' '))}</div>
              ${h.from_status && h.to_status ? `<div class="small"><code>${escapeHtml(h.from_status)}</code> → <code>${escapeHtml(h.to_status)}</code></div>` : ''}
              ${h.commentaire ? `<div class="small text-muted mt-1">${escapeHtml(h.commentaire)}</div>` : ''}
            </div>
          `).join('') : '<div class="text-muted">Aucun événement</div>'}
        </div>

      </div>
    `;

    document.getElementById('admBtnSaveStatut').onclick = async () => {
      const statut = document.getElementById('admStatutSel').value;
      const commentaire = document.getElementById('admStatutCom').value;
      const r2 = await adminApiPost('admin_update_admission_status', { id, statut, commentaire });
      if (r2.success) { modal.hide(); loadList(); }
      else alert(r2.message || 'Erreur');
    };
    document.getElementById('admBtnSaveNote').onclick = async () => {
      const note_interne = document.getElementById('admNoteInterne').value;
      const r2 = await adminApiPost('admin_update_admission_note', { id, note_interne });
      if (r2.success) alert('Note enregistrée');
      else alert(r2.message || 'Erreur');
    };
    document.getElementById('admBtnDelete').onclick = async () => {
      if (!confirm('Supprimer définitivement ce dossier d\'admission ?')) return;
      const r2 = await adminApiPost('admin_delete_admission', { id });
      if (r2.success) { modal.hide(); loadList(); }
      else alert(r2.message || 'Erreur');
    };
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-view');
    if (btn) { e.stopPropagation(); openAdmission(btn.dataset.id); return; }
    const row = e.target.closest('tr[data-id]');
    if (row) openAdmission(row.dataset.id);
  });

  let searchTimer;
  document.getElementById('admFilterSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 300);
  });
  document.getElementById('admFilterStatut').addEventListener('change', loadList);
  document.getElementById('admBtnReset').onclick = () => {
    document.getElementById('admFilterSearch').value = '';
    document.getElementById('admFilterStatut').value = '';
    loadList();
  };

  loadList();
})();
</script>
