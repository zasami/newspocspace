<?php
$params = Db::fetchAll("SELECT * FROM parametres_formation WHERE visible = 1 ORDER BY categorie, ordre");
$grouped = [];
foreach ($params as $p) {
    $grouped[$p['categorie']][] = $p;
}

$catLabels = [
    'evaluation'    => ['Évaluation',         'bi-clipboard-check', 'teal'],
    'expirations'   => ['Expirations',        'bi-clock-history',   'orange'],
    'inscriptions'  => ['Inscriptions FEGEMS','bi-cloud-arrow-up',  'blue'],
    'entretiens'    => ['Entretiens',         'bi-chat-square-text','purple'],
    'budget'        => ['Budget',             'bi-cash-coin',       'green'],
    'referentiel'   => ['Référentiel',        'bi-book',            'sand'],
];

$nbModif = 0;
foreach ($params as $p) {
    if ($p['valeur'] !== $p['valeur_defaut']) $nbModif++;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><i class="bi bi-gear"></i> Paramètres formation</h4>
    <div class="text-muted small mt-1">
      <?= count($params) ?> paramètres · <?= count($grouped) ?> catégories
      <?php if ($nbModif): ?>
        · <span class="badge text-bg-warning"><?= $nbModif ?> modifié<?= $nbModif > 1 ? 's' : '' ?></span>
      <?php endif ?>
    </div>
  </div>
  <span id="rhfp-savestate" class="text-muted small"></span>
</div>

<style>
.rhfp-row { display: flex; align-items: flex-start; padding: 14px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); gap: 16px; }
.rhfp-row:last-child { border-bottom: 0; }
.rhfp-info { flex: 1; min-width: 0; }
.rhfp-libelle { font-weight: 600; font-size: .92rem; color: var(--cl-text); }
.rhfp-desc { font-size: .8rem; color: var(--cl-text-muted); margin-top: 2px; }
.rhfp-cle { font-size: .68rem; font-family: ui-monospace, Menlo, monospace; color: var(--cl-text-muted); margin-top: 4px; opacity: .65; }
.rhfp-widget { flex-shrink: 0; min-width: 200px; max-width: 320px; display: flex; align-items: center; gap: 8px; }
.rhfp-widget input[type="number"], .rhfp-widget input[type="text"], .rhfp-widget input[type="date"], .rhfp-widget select { font-size: .88rem; }
.rhfp-widget textarea { font-size: .82rem; font-family: ui-monospace, Menlo, monospace; }
.rhfp-modif { background: linear-gradient(180deg, rgba(212, 196, 168, .08), transparent); }
.rhfp-modif .rhfp-libelle::before { content: '●'; color: #D4A24C; margin-right: 6px; font-size: .7rem; vertical-align: middle; }
.rhfp-reset-btn { background: transparent; border: 0; color: var(--cl-text-muted); cursor: pointer; padding: 0 6px; font-size: .85rem; }
.rhfp-reset-btn:hover { color: var(--cl-red, #B45F4E); }
.form-switch .form-check-input { cursor: pointer; }
.rhfp-range-track { display: flex; align-items: center; gap: 10px; }
.rhfp-range-track input[type="range"] { flex: 1; cursor: pointer; }
.rhfp-range-val { min-width: 44px; text-align: right; font-weight: 600; font-size: .88rem; color: var(--cl-text); }
.rhfp-saving { animation: rhfp-pulse 1.2s infinite; }
@keyframes rhfp-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
</style>

<?php foreach ($grouped as $cat => $list):
    [$catLabel, $catIcon, $catColor] = $catLabels[$cat] ?? [ucfirst($cat), 'bi-gear', 'teal'];
?>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="<?= h($catIcon) ?>" style="color:var(--cl-<?= h($catColor) ?>, var(--cl-primary))"></i>
    <strong><?= h($catLabel) ?></strong>
    <span class="text-muted small ms-auto"><?= count($list) ?> paramètre<?= count($list) > 1 ? 's' : '' ?></span>
  </div>
  <div class="card-body py-2">
    <?php foreach ($list as $p):
        $val = $p['valeur'];
        $modif = ($val !== $p['valeur_defaut']);
        $opts = $p['options_json'] ? json_decode($p['options_json'], true) : null;
    ?>
    <div class="rhfp-row <?= $modif ? 'rhfp-modif' : '' ?>" data-cle="<?= h($p['cle']) ?>" data-defaut="<?= h($p['valeur_defaut'] ?? '') ?>">
      <div class="rhfp-info">
        <div class="rhfp-libelle"><?= h($p['libelle']) ?></div>
        <?php if ($p['description']): ?>
          <div class="rhfp-desc"><?= h($p['description']) ?></div>
        <?php endif ?>
        <div class="rhfp-cle"><?= h($p['cle']) ?></div>
      </div>
      <div class="rhfp-widget">
        <?php if ($p['type'] === 'bool'): ?>
          <div class="form-check form-switch flex-grow-1">
            <input class="form-check-input rhfp-input" type="checkbox" role="switch"
                   data-type="bool" <?= $val == '1' ? 'checked' : '' ?>>
            <label class="form-check-label small text-muted">
              <span class="rhfp-bool-label"><?= $val == '1' ? 'Activé' : 'Désactivé' ?></span>
            </label>
          </div>

        <?php elseif ($p['type'] === 'int' && $p['min_val'] !== null && $p['max_val'] !== null): ?>
          <div class="rhfp-range-track flex-grow-1">
            <input type="range" class="form-range rhfp-input"
                   min="<?= h((string)(int)$p['min_val']) ?>"
                   max="<?= h((string)(int)$p['max_val']) ?>"
                   value="<?= h($val) ?>"
                   data-type="int">
            <span class="rhfp-range-val"><?= h($val) ?></span>
          </div>

        <?php elseif ($p['type'] === 'int' || $p['type'] === 'decimal'): ?>
          <input type="number" class="form-control form-control-sm rhfp-input"
                 value="<?= h($val) ?>"
                 <?= $p['min_val'] !== null ? 'min="' . h((string)$p['min_val']) . '"' : '' ?>
                 <?= $p['max_val'] !== null ? 'max="' . h((string)$p['max_val']) . '"' : '' ?>
                 <?= $p['type'] === 'decimal' ? 'step="0.01"' : 'step="1"' ?>
                 data-type="<?= h($p['type']) ?>">

        <?php elseif ($p['type'] === 'string' && $opts): ?>
          <select class="form-select form-select-sm rhfp-input" data-type="string">
            <?php foreach ($opts as $key => $label): ?>
              <option value="<?= h(is_string($key) ? $key : $label) ?>" <?= $val === (is_string($key) ? $key : $label) ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach ?>
          </select>

        <?php elseif ($p['type'] === 'json'): ?>
          <textarea class="form-control form-control-sm rhfp-input" rows="3" data-type="json"><?= h($val) ?></textarea>

        <?php elseif ($p['type'] === 'date'): ?>
          <input type="date" class="form-control form-control-sm rhfp-input"
                 value="<?= h($val) ?>" data-type="date">

        <?php elseif ($p['type'] === 'string' && (strlen($val) > 60 || str_contains($p['cle'], 'signature') || str_contains($p['cle'], 'template'))): ?>
          <textarea class="form-control form-control-sm rhfp-input" rows="2" data-type="string"><?= h($val) ?></textarea>

        <?php else: ?>
          <input type="text" class="form-control form-control-sm rhfp-input"
                 value="<?= h($val) ?>" data-type="string">
        <?php endif ?>

        <?php if ($p['valeur_defaut'] !== null): ?>
          <button class="rhfp-reset-btn" title="Réinitialiser à la valeur par défaut"
                  data-defaut="<?= h($p['valeur_defaut']) ?>" type="button">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endforeach ?>

<script nonce="<?= nonce() ?>">
(function () {
  const saveState = document.getElementById('rhfp-savestate');
  let saveTimer = null;

  function setState(text, cls) {
    saveState.textContent = text;
    saveState.className = 'small ' + (cls || 'text-muted');
    if (saveTimer) clearTimeout(saveTimer);
    if (text) saveTimer = setTimeout(() => { saveState.textContent = ''; }, 2500);
  }

  async function saveParam(row, valeur) {
    const cle = row.dataset.cle;
    setState('Sauvegarde…', 'text-muted rhfp-saving');
    try {
      const r = await adminApiPost('save_parametre_formation', { cle, valeur });
      if (!r.success) throw new Error(r.message || 'Erreur');
      setState('✓ Enregistré', 'text-success');
      // Marquer comme modifié si différent du défaut
      const defaut = row.dataset.defaut || '';
      const curVal = String(r.valeur ?? valeur);
      if (curVal !== defaut) {
        row.classList.add('rhfp-modif');
      } else {
        row.classList.remove('rhfp-modif');
      }
    } catch (e) {
      setState('⚠ ' + (e.message || 'Erreur'), 'text-danger');
      console.error('Save param error', e);
    }
  }

  function readValue(input) {
    const t = input.dataset.type;
    if (t === 'bool') return input.checked ? '1' : '0';
    return input.value;
  }

  // Debounce pour éviter les saves intempestifs sur range/text
  const debouncers = new WeakMap();
  function debouncedSave(row, input, ms) {
    if (debouncers.has(input)) clearTimeout(debouncers.get(input));
    const tid = setTimeout(() => saveParam(row, readValue(input)), ms || 400);
    debouncers.set(input, tid);
  }

  document.querySelectorAll('.rhfp-row').forEach(row => {
    const input = row.querySelector('.rhfp-input');
    if (!input) return;

    if (input.dataset.type === 'bool') {
      input.addEventListener('change', () => {
        row.querySelector('.rhfp-bool-label').textContent = input.checked ? 'Activé' : 'Désactivé';
        saveParam(row, readValue(input));
      });
    } else if (input.type === 'range') {
      input.addEventListener('input', () => {
        row.querySelector('.rhfp-range-val').textContent = input.value;
        debouncedSave(row, input, 300);
      });
    } else if (input.tagName === 'SELECT' || input.type === 'date') {
      input.addEventListener('change', () => saveParam(row, readValue(input)));
    } else {
      input.addEventListener('input', () => debouncedSave(row, input, 600));
      input.addEventListener('blur', () => {
        if (debouncers.has(input)) clearTimeout(debouncers.get(input));
        saveParam(row, readValue(input));
      });
    }

    // Reset button
    const resetBtn = row.querySelector('.rhfp-reset-btn');
    if (resetBtn) {
      resetBtn.addEventListener('click', async () => {
        const ok = await ssConfirm({
          title: 'Réinitialiser le paramètre',
          message: 'Remettre ce paramètre à sa valeur par défaut ?',
          confirmText: 'Réinitialiser',
          icon: 'bi-arrow-counterclockwise'
        });
        if (!ok) return;
        const defaut = resetBtn.dataset.defaut || '';
        if (input.dataset.type === 'bool') {
          input.checked = defaut === '1';
          row.querySelector('.rhfp-bool-label').textContent = input.checked ? 'Activé' : 'Désactivé';
        } else if (input.type === 'range') {
          input.value = defaut;
          row.querySelector('.rhfp-range-val').textContent = defaut;
        } else {
          input.value = defaut;
        }
        try {
          const r = await adminApiPost('reset_parametre_formation', { cle: row.dataset.cle });
          if (r.success) {
            row.classList.remove('rhfp-modif');
            setState('✓ Réinitialisé', 'text-success');
          }
        } catch (e) {
          setState('⚠ Erreur', 'text-danger');
        }
      });
    }
  });
})();
</script>
