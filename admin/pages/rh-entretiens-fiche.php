<?php
$entretienId = $_GET['id'] ?? '';
$entretien = $entretienId ? Db::fetch(
    "SELECT e.*, u.prenom, u.nom, u.email, u.experience_fonction_annees, u.cct,
            ev.prenom AS eval_prenom, ev.nom AS eval_nom,
            fn.nom AS fonction, fn.secteur_fegems
     FROM entretiens_annuels e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN users ev ON ev.id = e.evaluator_id
     LEFT JOIN fonctions fn ON fn.id = u.fonction_id
     WHERE e.id = ?", [$entretienId]
) : null;

if ($entretien) {
    $synthese = Db::fetch(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN priorite = 'haute' THEN 1 ELSE 0 END) AS prio_haute,
                SUM(CASE WHEN priorite = 'moyenne' THEN 1 ELSE 0 END) AS prio_moyenne,
                SUM(CASE WHEN priorite = 'a_jour' THEN 1 ELSE 0 END) AS a_jour,
                ROUND(AVG(niveau_actuel), 2) AS niveau_moyen
         FROM competences_user
         WHERE user_id = ?",
        [$entretien['user_id']]
    );

    $objectifs = Db::fetchAll(
        "SELECT o.*, t.nom AS thematique_nom, t.couleur AS thematique_couleur
         FROM competences_objectifs_annuels o
         LEFT JOIN competences_thematiques t ON t.id = o.thematique_id_liee
         WHERE o.user_id = ? AND (o.entretien_origine_id = ? OR o.annee = ?)
         ORDER BY o.statut, o.ordre",
        [$entretien['user_id'], $entretienId, $entretien['annee']]
    );

    $thematiquesLib = Db::fetchAll(
        "SELECT id, nom FROM competences_thematiques WHERE actif = 1 ORDER BY ordre, nom"
    );

    $evaluateurs = Db::fetchAll(
        "SELECT id, prenom, nom FROM users
         WHERE is_active = 1 AND role IN ('responsable','admin','direction')
         ORDER BY nom"
    );
}

$STATUT_BADGE = [
    'planifie' => ['Planifié', '#B8C9D4', '#3D5A6B'],
    'realise'  => ['Réalisé',  '#bcd2cb', '#2d4a43'],
    'reporte'  => ['Reporté',  '#E8C9A0', '#7B5B2C'],
    'annule'   => ['Annulé',   '#E2B8AE', '#7B3B2C'],
];
$OBJ_STATUT = [
    'a_definir'  => ['À définir', '#F0EDE8', '#7A6E58'],
    'en_cours'   => ['En cours',  '#B8C9D4', '#3D5A6B'],
    'atteint'    => ['Atteint',   '#bcd2cb', '#2d4a43'],
    'reporte'    => ['Reporté',   '#E8C9A0', '#7B5B2C'],
    'abandonne'  => ['Abandonné', '#E2B8AE', '#7B3B2C'],
];
$SECTEUR_LABELS = [
    'soins' => 'Soins', 'socio_culturel' => 'Socio-culturel', 'hotellerie' => 'Hôtellerie',
    'maintenance' => 'Maintenance', 'administration' => 'Administration', 'management' => 'Management',
];
?>
<style>
.rhef-hero { background: linear-gradient(135deg, var(--cl-teal, #2d4a43), #1f3530); color: #fff; border-radius: 14px; padding: 22px; margin-bottom: 18px; }
.rhef-hero h3 { margin: 0; font-size: 1.5rem; font-weight: 700; }
.rhef-hero-meta { font-size: .88rem; opacity: .85; margin-top: 4px; }
.rhef-hero-stats { display: flex; gap: 18px; margin-top: 14px; }
.rhef-hero-stat { background: rgba(255,255,255,.1); border-radius: 10px; padding: 8px 12px; }
.rhef-hero-stat strong { display: block; font-size: 1.2rem; font-weight: 700; }
.rhef-hero-stat span { font-size: .72rem; opacity: .8; }
.rhef-card { background: #fff; border: 1px solid var(--cl-border-light, #F0EDE8); border-radius: 14px; padding: 18px; margin-bottom: 16px; }
.rhef-card-title { font-size: .88rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--cl-text-muted); margin: 0 0 12px; display: flex; align-items: center; gap: 6px; }
.rhef-meta-row { display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); font-size: .85rem; }
.rhef-meta-row:last-child { border-bottom: 0; }
.rhef-meta-row > div { flex: 1; min-width: 120px; }
.rhef-meta-row label { display: block; font-size: .72rem; color: var(--cl-text-muted); margin-bottom: 2px; }
.rhef-meta-row strong { font-weight: 600; color: var(--cl-text); }
.rhef-objectif { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.rhef-objectif:last-child { border-bottom: 0; }
.rhef-obj-tri { font-size: .68rem; padding: 2px 8px; border-radius: 8px; font-weight: 600; background: var(--cl-bg, #F7F5F2); color: var(--cl-text-muted); white-space: nowrap; }
.rhef-obj-libelle { flex: 1; font-weight: 500; color: var(--cl-text); font-size: .9rem; }
.rhef-obj-them { font-size: .72rem; color: var(--cl-text-muted); margin-top: 2px; }
.rhef-obj-statut { font-size: .72rem; padding: 3px 10px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
.rhef-obj-actions { display: flex; gap: 4px; }
.rhef-obj-actions button { background: transparent; border: 0; color: var(--cl-text-muted); cursor: pointer; padding: 2px 6px; border-radius: 4px; }
.rhef-obj-actions button:hover { background: var(--cl-bg, #F7F5F2); color: var(--cl-text); }
.rhef-status-bar { display: flex; gap: 6px; flex-wrap: wrap; }
.rhef-status-btn { padding: 5px 12px; border-radius: 8px; border: 1px solid var(--cl-border-light, #F0EDE8); background: #fff; cursor: pointer; font-size: .82rem; font-weight: 500; transition: all .15s; }
.rhef-status-btn:hover { background: var(--cl-bg, #F7F5F2); }
.rhef-status-btn.active { color: #fff; border-color: transparent; }
.rhef-status-btn.active.s-planifie { background: #3D5A6B; }
.rhef-status-btn.active.s-realise { background: #2d4a43; }
.rhef-status-btn.active.s-reporte { background: #7B5B2C; }
.rhef-status-btn.active.s-annule { background: #7B3B2C; }
.rhef-saving { animation: pulse 1.2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
.rhef-empty-objectifs { text-align: center; padding: 24px 12px; color: var(--cl-text-muted); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="bi bi-chat-square-text"></i> Fiche entretien
  </h4>
  <a href="?page=rh-entretiens" class="btn btn-sm btn-outline-secondary" data-page-link>
    <i class="bi bi-arrow-left"></i> Retour
  </a>
</div>

<?php if (!$entretien): ?>
  <div class="rhef-card text-center text-muted py-5">
    <i class="bi bi-clipboard-x" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:12px"></i>
    <p class="mb-0">Aucun entretien sélectionné. Cette page s'ouvre depuis la <a href="?page=rh-entretiens" data-page-link>liste des entretiens</a>.</p>
  </div>
<?php else:
  [$badgeLabel, $badgeBg, $badgeFg] = $STATUT_BADGE[$entretien['statut']] ?? ['?', '#ddd', '#333'];
  $initials = strtoupper(substr($entretien['prenom'], 0, 1) . substr($entretien['nom'], 0, 1));
?>
  <!-- Hero -->
  <div class="rhef-hero">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h3><?= h($entretien['prenom'] . ' ' . $entretien['nom']) ?></h3>
        <div class="rhef-hero-meta">
          <?= h($entretien['fonction'] ?: '—') ?>
          <?php if ($entretien['secteur_fegems']): ?>
            · <?= h($SECTEUR_LABELS[$entretien['secteur_fegems']] ?? $entretien['secteur_fegems']) ?>
          <?php endif ?>
          · Année <?= h($entretien['annee']) ?>
        </div>
        <div class="rhef-hero-stats">
          <div class="rhef-hero-stat">
            <strong><?= number_format($synthese['niveau_moyen'] ?? 0, 1) ?></strong>
            <span>Niveau moyen</span>
          </div>
          <div class="rhef-hero-stat">
            <strong><?= (int) ($synthese['prio_haute'] ?? 0) ?></strong>
            <span>Priorité haute</span>
          </div>
          <div class="rhef-hero-stat">
            <strong><?= (int) ($synthese['a_jour'] ?? 0) ?>/<?= (int) ($synthese['total'] ?? 0) ?></strong>
            <span>Compétences à jour</span>
          </div>
          <div class="rhef-hero-stat">
            <strong><?= count($objectifs) ?></strong>
            <span>Objectifs</span>
          </div>
        </div>
      </div>
      <div class="text-end">
        <a href="<?= h(admin_url('rh-collab-competences', $entretien['user_id'])) ?>"
           class="btn btn-sm btn-light" data-page-link>
          <i class="bi bi-bullseye"></i> Voir cartographie
        </a>
        <span class="ms-auto"></span>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Métadonnées -->
      <div class="rhef-card">
        <h5 class="rhef-card-title"><i class="bi bi-info-circle"></i> Détails</h5>

        <div class="mb-3">
          <label class="form-label small text-muted">Statut</label>
          <div class="rhef-status-bar">
            <?php foreach ($STATUT_BADGE as $s => [$lbl, $bg, $fg]): ?>
              <button class="rhef-status-btn s-<?= $s ?> <?= $entretien['statut'] === $s ? 'active' : '' ?>" data-statut="<?= h($s) ?>">
                <?= h($lbl) ?>
              </button>
            <?php endforeach ?>
          </div>
        </div>

        <div class="rhef-meta-row">
          <div>
            <label>Date entretien</label>
            <input type="date" class="form-control form-control-sm rhef-edit" data-field="date_entretien"
                   value="<?= h($entretien['date_entretien'] ?? '') ?>">
          </div>
          <div>
            <label>Année</label>
            <input type="number" class="form-control form-control-sm rhef-edit" data-field="annee"
                   value="<?= h($entretien['annee']) ?>" min="2020" max="2100">
          </div>
          <div>
            <label>Évaluateur·trice</label>
            <select class="form-select form-select-sm rhef-edit" data-field="evaluator_id">
              <option value="">— Aucun —</option>
              <?php foreach ($evaluateurs as $ev): ?>
                <option value="<?= h($ev['id']) ?>" <?= $entretien['evaluator_id'] === $ev['id'] ? 'selected' : '' ?>>
                  <?= h($ev['nom'] . ' ' . $ev['prenom']) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <div class="rhef-meta-row">
          <div>
            <label>Email collaborateur</label>
            <strong><?= h($entretien['email'] ?: '—') ?></strong>
          </div>
          <div>
            <label>Ancienneté fonction</label>
            <strong><?= $entretien['experience_fonction_annees'] ? $entretien['experience_fonction_annees'] . ' an(s)' : '—' ?></strong>
          </div>
          <div>
            <label>CCT</label>
            <strong><?= h($entretien['cct'] ?: '—') ?></strong>
          </div>
        </div>
        <div class="rhef-meta-row">
          <div>
            <label>Signé le</label>
            <strong><?= $entretien['signed_at'] ? DateTime::createFromFormat('Y-m-d H:i:s', $entretien['signed_at'])->format('d/m/Y H:i') : '—' ?></strong>
          </div>
          <div>
            <label>Créé le</label>
            <strong><?= $entretien['created_at'] ? DateTime::createFromFormat('Y-m-d H:i:s', $entretien['created_at'])->format('d/m/Y') : '—' ?></strong>
          </div>
          <div></div>
        </div>
      </div>

      <!-- Notes -->
      <div class="rhef-card">
        <h5 class="rhef-card-title"><i class="bi bi-pencil-square"></i> Notes manager</h5>
        <textarea class="form-control rhef-edit" data-field="notes_manager" rows="6"
                  placeholder="Bilan de l'année, points forts, axes d'amélioration, soutien proposé…"><?= h($entretien['notes_manager'] ?? '') ?></textarea>
      </div>

      <div class="rhef-card">
        <h5 class="rhef-card-title"><i class="bi bi-chat-left-text"></i> Notes collaborateur (auto-évaluation)</h5>
        <textarea class="form-control rhef-edit" data-field="notes_collaborateur" rows="5"
                  placeholder="Ressenti de l'année, souhaits de formation, projets professionnels…"><?= h($entretien['notes_collaborateur'] ?? '') ?></textarea>
      </div>

      <!-- Objectifs -->
      <div class="rhef-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="rhef-card-title mb-0"><i class="bi bi-flag"></i> Objectifs <?= h($entretien['annee']) ?></h5>
          <button class="btn btn-sm btn-outline-primary" id="rhef-btn-add-obj">
            <i class="bi bi-plus-lg"></i> Ajouter
          </button>
        </div>
        <div id="rhef-objectifs-list">
          <?php if ($objectifs): foreach ($objectifs as $o):
            [$objLbl, $objBg, $objFg] = $OBJ_STATUT[$o['statut']] ?? ['?', '#ddd', '#333'];
          ?>
            <div class="rhef-objectif" data-id="<?= h($o['id']) ?>">
              <span class="rhef-obj-tri"><?= h($o['trimestre_cible']) ?></span>
              <div style="flex:1">
                <div class="rhef-obj-libelle"><?= h($o['libelle']) ?></div>
                <?php if ($o['thematique_nom']): ?>
                  <div class="rhef-obj-them"><i class="bi bi-tag"></i> <?= h($o['thematique_nom']) ?></div>
                <?php endif ?>
              </div>
              <span class="rhef-obj-statut" style="background:<?= $objBg ?>;color:<?= $objFg ?>"><?= h($objLbl) ?></span>
              <div class="rhef-obj-actions">
                <button class="rhef-obj-edit" title="Modifier"><i class="bi bi-pencil"></i></button>
                <button class="rhef-obj-delete" title="Supprimer"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          <?php endforeach; else: ?>
            <div class="rhef-empty-objectifs">
              <i class="bi bi-flag" style="font-size:1.6rem;opacity:.3;display:block;margin-bottom:6px"></i>
              <p class="mb-0 small">Aucun objectif défini.</p>
            </div>
          <?php endif ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="rhef-card">
        <h5 class="rhef-card-title"><i class="bi bi-gear"></i> Actions</h5>
        <div class="d-grid gap-2">
          <button class="btn btn-outline-secondary btn-sm" id="rhef-btn-mark-realise">
            <i class="bi bi-check-circle"></i> Marquer comme réalisé
          </button>
          <button class="btn btn-outline-warning btn-sm" id="rhef-btn-reporter">
            <i class="bi bi-arrow-clockwise"></i> Reporter
          </button>
          <button class="btn btn-outline-danger btn-sm" id="rhef-btn-delete">
            <i class="bi bi-trash"></i> Supprimer
          </button>
        </div>
        <div class="text-muted small mt-3" id="rhef-savestate"></div>
      </div>

      <div class="rhef-card">
        <h5 class="rhef-card-title"><i class="bi bi-bullseye"></i> Synthèse compétences</h5>
        <div class="small">
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Total thématiques</span>
            <strong><?= (int) ($synthese['total'] ?? 0) ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1" style="border-top:1px solid var(--cl-border-light, #F0EDE8)">
            <span style="color:#7B3B2C">Priorité haute</span>
            <strong style="color:#7B3B2C"><?= (int) ($synthese['prio_haute'] ?? 0) ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span style="color:#7B5B2C">Priorité moyenne</span>
            <strong style="color:#7B5B2C"><?= (int) ($synthese['prio_moyenne'] ?? 0) ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span style="color:#2d4a43">À jour</span>
            <strong style="color:#2d4a43"><?= (int) ($synthese['a_jour'] ?? 0) ?></strong>
          </div>
        </div>
        <a href="<?= h(admin_url('rh-collab-competences', $entretien['user_id'])) ?>"
           class="btn btn-sm btn-outline-primary w-100 mt-3" data-page-link>
          <i class="bi bi-arrow-right"></i> Détail compétences
        </a>
      </div>
    </div>
  </div>

  <!-- Modal objectif -->
  <div class="modal fade" id="rhef-obj-modal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-flag"></i> Objectif</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="rhef-obj-form">
            <input type="hidden" name="id" value="">
            <div class="mb-3">
              <label class="form-label small">Libellé</label>
              <input type="text" name="libelle" class="form-control" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small">Trimestre cible</label>
                <select name="trimestre_cible" class="form-select">
                  <option value="annuel">Annuel</option>
                  <option value="Q1">Q1</option>
                  <option value="Q2">Q2</option>
                  <option value="Q3">Q3</option>
                  <option value="Q4">Q4</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small">Statut</label>
                <select name="statut" class="form-select">
                  <option value="a_definir">À définir</option>
                  <option value="en_cours">En cours</option>
                  <option value="atteint">Atteint</option>
                  <option value="reporte">Reporté</option>
                  <option value="abandonne">Abandonné</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small">Thématique liée (optionnel)</label>
              <select name="thematique_id_liee" class="form-select">
                <option value="">— Aucune —</option>
                <?php foreach ($thematiquesLib as $t): ?>
                  <option value="<?= h($t['id']) ?>"><?= h($t['nom']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small">Description (optionnel)</label>
              <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary btn-sm" id="rhef-obj-save">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>

  <script nonce="<?= nonce() ?>">
  (function () {
    const ENT_ID  = <?= json_encode($entretienId) ?>;
    const USER_ID = <?= json_encode($entretien['user_id']) ?>;
    const ANNEE   = <?= json_encode((int) $entretien['annee']) ?>;
    const saveState = document.getElementById('rhef-savestate');
    const debouncers = new WeakMap();

    function setState(t, cls) {
      saveState.textContent = t;
      saveState.className = 'small ' + (cls || 'text-muted');
      if (t) setTimeout(() => { if (saveState.textContent === t) saveState.textContent = ''; }, 2500);
    }

    async function saveEntretien(payload) {
      setState('Sauvegarde…', 'text-muted rhef-saving');
      try {
        const r = await adminApiPost('save_entretien', { id: ENT_ID, user_id: USER_ID, ...payload });
        if (!r.success) throw new Error(r.message || 'Erreur');
        setState('✓ Enregistré', 'text-success');
        return r;
      } catch (e) {
        setState('⚠ ' + e.message, 'text-danger');
        throw e;
      }
    }

    // Inline edit fields
    document.querySelectorAll('.rhef-edit').forEach(input => {
      const save = () => {
        const v = input.value;
        const field = input.dataset.field;
        saveEntretien({ [field]: v });
      };
      if (input.tagName === 'TEXTAREA' || input.type === 'text' || input.type === 'number') {
        input.addEventListener('input', () => {
          if (debouncers.has(input)) clearTimeout(debouncers.get(input));
          debouncers.set(input, setTimeout(save, 800));
        });
        input.addEventListener('blur', () => {
          if (debouncers.has(input)) clearTimeout(debouncers.get(input));
          save();
        });
      } else {
        input.addEventListener('change', save);
      }
    });

    // Status buttons
    document.querySelectorAll('.rhef-status-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const newStatut = btn.dataset.statut;
        await saveEntretien({ statut: newStatut });
        document.querySelectorAll('.rhef-status-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });

    // Quick actions
    document.getElementById('rhef-btn-mark-realise')?.addEventListener('click', async () => {
      const ok = await ssConfirm({
        title: 'Marquer comme réalisé',
        message: 'Confirmer la réalisation de cet entretien ? La date prochaine sera recalculée selon la fréquence configurée.',
        confirmText: 'Marquer réalisé',
        confirmClass: 'btn-success',
        icon: 'bi-check-circle'
      });
      if (!ok) return;
      await saveEntretien({ statut: 'realise' });
      setTimeout(() => location.reload(), 600);
    });

    document.getElementById('rhef-btn-reporter')?.addEventListener('click', async () => {
      const ok = await ssConfirm({
        title: 'Reporter l\'entretien',
        message: 'Marquer cet entretien comme reporté ? Vous pourrez en planifier un nouveau ensuite.',
        confirmText: 'Reporter',
        confirmClass: 'btn-warning',
        icon: 'bi-arrow-clockwise'
      });
      if (!ok) return;
      await saveEntretien({ statut: 'reporte' });
      setTimeout(() => location.reload(), 600);
    });

    document.getElementById('rhef-btn-delete')?.addEventListener('click', async () => {
      const ok = await ssConfirm({
        title: 'Supprimer cet entretien',
        message: 'Cette action est irréversible. Les objectifs liés seront aussi supprimés.',
        confirmText: 'Supprimer',
        confirmClass: 'btn-danger',
        icon: 'bi-trash'
      });
      if (!ok) return;
      try {
        const r = await adminApiPost('delete_entretien', { id: ENT_ID });
        if (r.success) {
          showToast('Entretien supprimé', 'success');
          setTimeout(() => location.href = '?page=rh-entretiens', 400);
        }
      } catch (e) {
        showToast('Erreur : ' + e.message, 'danger');
      }
    });

    // Objectifs
    const objModalEl = document.getElementById('rhef-obj-modal');
    const objModal = new bootstrap.Modal(objModalEl);
    const objForm = document.getElementById('rhef-obj-form');

    document.getElementById('rhef-btn-add-obj')?.addEventListener('click', () => {
      objForm.reset();
      objForm.querySelector('[name="id"]').value = '';
      objModal.show();
    });

    document.querySelectorAll('.rhef-obj-edit').forEach(btn => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.rhef-objectif');
        const id = row.dataset.id;
        objForm.reset();
        objForm.querySelector('[name="id"]').value = id;
        // Pré-remplit depuis l'affichage (libelle uniquement, le reste sera saisi)
        const libelle = row.querySelector('.rhef-obj-libelle').textContent.trim();
        objForm.querySelector('[name="libelle"]').value = libelle;
        objModal.show();
      });
    });

    document.querySelectorAll('.rhef-obj-delete').forEach(btn => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.rhef-objectif');
        const id = row.dataset.id;
        const ok = await ssConfirm({
          title: 'Supprimer cet objectif',
          message: 'Cette action est irréversible.',
          confirmText: 'Supprimer',
          confirmClass: 'btn-danger',
          icon: 'bi-trash'
        });
        if (!ok) return;
        try {
          const r = await adminApiPost('delete_objectif_annuel', { id });
          if (r.success) { row.remove(); showToast('Objectif supprimé', 'success'); }
        } catch (e) {
          showToast('Erreur : ' + e.message, 'danger');
        }
      });
    });

    document.getElementById('rhef-obj-save')?.addEventListener('click', async () => {
      const fd = new FormData(objForm);
      const payload = Object.fromEntries(fd.entries());
      payload.user_id = USER_ID;
      payload.annee = ANNEE;
      payload.entretien_origine_id = ENT_ID;
      if (!payload.libelle.trim()) {
        showToast('Libellé requis', 'danger');
        return;
      }
      try {
        const r = await adminApiPost('save_objectif_annuel', payload);
        if (r.success) {
          showToast('Objectif enregistré', 'success');
          objModal.hide();
          setTimeout(() => location.reload(), 400);
        }
      } catch (e) {
        showToast('Erreur : ' + e.message, 'danger');
      }
    });
  })();
  </script>
<?php endif ?>
