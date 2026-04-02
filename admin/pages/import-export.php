<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><i class="bi bi-arrow-down-up"></i> Import / Export</h5>
</div>

<div class="row g-3">
  <!-- EXPORT SECTION -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-download"></i> Exporter</div>
      <div class="card-body">
        <!-- Export Planning -->
        <div class="mb-4 p-3 bg-light rounded">
          <h6><i class="bi bi-calendar3"></i> Planning</h6>
          <div class="row g-2 mb-2">
            <div class="col">
              <input type="month" class="form-control form-control-sm" id="expPlanningMois">
            </div>
            <div class="col-auto">
              <div class="zs-select" id="expPlanningFormat" data-placeholder="Format"></div>
            </div>
          </div>
          <button class="btn btn-sm btn-outline-primary" id="expPlanningBtn">
            <i class="bi bi-download"></i> Exporter planning
          </button>
        </div>

        <!-- Export Users -->
        <div class="mb-4 p-3 bg-light rounded">
          <h6><i class="bi bi-people"></i> Collaborateurs</h6>
          <p class="text-muted small mb-2">Exporte tous les collaborateurs avec leur fonction, taux, modules</p>
          <button class="btn btn-sm btn-outline-primary" id="expUsersBtn">
            <i class="bi bi-download"></i> Exporter collaborateurs
          </button>
        </div>

        <!-- Export Absences -->
        <div class="p-3 bg-light rounded">
          <h6><i class="bi bi-calendar-x"></i> Absences & Vacances</h6>
          <div class="row g-2 mb-2">
            <div class="col">
              <input type="number" class="form-control form-control-sm" id="expAbsAnnee" placeholder="Année" min="2020" max="2100">
            </div>
            <div class="col">
              <div class="zs-select" id="expAbsType" data-placeholder="Tous types"></div>
            </div>
          </div>
          <button class="btn btn-sm btn-outline-primary" id="expAbsBtn">
            <i class="bi bi-download"></i> Exporter absences
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- IMPORT SECTION -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-upload"></i> Importer</div>
      <div class="card-body">
        <!-- Import Users -->
        <div class="mb-4 p-3 bg-light rounded">
          <h6><i class="bi bi-people"></i> Collaborateurs (CSV)</h6>
          <div class="ss-info-bar small mb-2">
            <i class="bi bi-info-circle"></i>
            Colonnes requises : <strong>nom, prenom, email</strong><br>
            Colonnes optionnelles : id, role, taux, fonction, contrat
          </div>
          <input type="file" class="form-control form-control-sm mb-2" id="impUsersFile" accept=".csv">
          <button class="btn btn-sm btn-outline-success" id="impUsersBtn" disabled>
            <i class="bi bi-upload"></i> Importer collaborateurs
          </button>
          <div id="impUsersResult" class="mt-2 d-none"></div>
        </div>

        <!-- Import Polypoint -->
        <div class="p-3 bg-light rounded">
          <h6><i class="bi bi-calendar3"></i> Planning Polypoint (CSV)</h6>
          <div class="ss-info-bar small mb-2">
            <i class="bi bi-info-circle"></i>
            Colonnes : <strong>email</strong> ou <strong>employee_id</strong>, <strong>date</strong> (YYYY-MM-DD), <strong>horaire</strong> (code), <strong>module</strong> (code)<br>
            Séparateur : <code>;</code> ou <code>,</code> (auto-détecté)
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <input type="month" class="form-control form-control-sm" id="impPolyMois">
            </div>
          </div>
          <input type="file" class="form-control form-control-sm mb-2" id="impPolyFile" accept=".csv">
          <button class="btn btn-sm btn-outline-success" id="impPolyBtn" disabled>
            <i class="bi bi-upload"></i> Importer planning
          </button>
          <div id="impPolyResult" class="mt-2 d-none"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script<?= nonce() ?>>
const now = new Date();
const curMois = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');

document.getElementById('expPlanningMois').value = curMois;
document.getElementById('expAbsAnnee').value = now.getFullYear();
document.getElementById('impPolyMois').value = curMois;

// Init zerdaSelect for format and absence type
zerdaSelect.init('#expPlanningFormat', [
    { value: 'csv', label: 'CSV standard' },
    { value: 'polypoint', label: 'Polypoint' }
], { value: 'csv' });

zerdaSelect.init('#expAbsType', [
    { value: '', label: 'Tous types' },
    { value: 'vacances', label: 'Vacances' },
    { value: 'maladie', label: 'Maladie' },
    { value: 'accident', label: 'Accident' },
    { value: 'formation', label: 'Formation' },
    { value: 'autre', label: 'Autre' }
], { value: '' });

// Enable buttons when file selected
document.getElementById('impUsersFile')?.addEventListener('change', e => {
    document.getElementById('impUsersBtn').disabled = !e.target.files.length;
});
document.getElementById('impPolyFile')?.addEventListener('change', e => {
    document.getElementById('impPolyBtn').disabled = !e.target.files.length;
});

/* ── EXPORTS ── */

document.getElementById('expPlanningBtn')?.addEventListener('click', async () => {
    const mois = document.getElementById('expPlanningMois').value;
    const format = zerdaSelect.getValue('#expPlanningFormat');
    if (!mois) return alert('Sélectionnez un mois');
    const res = await adminApiPost('admin_export_planning', { mois, format });
    if (res.success) downloadCsv(res.rows, res.filename, format === 'polypoint' ? ';' : ',');
});

document.getElementById('expUsersBtn')?.addEventListener('click', async () => {
    const res = await adminApiPost('admin_export_users');
    if (res.success) downloadCsv(res.rows, res.filename);
});

document.getElementById('expAbsBtn')?.addEventListener('click', async () => {
    const annee = document.getElementById('expAbsAnnee').value;
    const type = zerdaSelect.getValue('#expAbsType');
    if (!annee) return alert('Sélectionnez une année');
    const res = await adminApiPost('admin_export_absences', { annee, type });
    if (res.success) downloadCsv(res.rows, res.filename);
});

/* ── IMPORTS ── */

document.getElementById('impUsersBtn')?.addEventListener('click', async () => {
    const file = document.getElementById('impUsersFile').files[0];
    if (!file) return;
    const btn = document.getElementById('impUsersBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';

    const fd = new FormData();
    fd.append('action', 'admin_import_users');
    fd.append('file', file);

    try {
        const resp = await fetch('/spocspace/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: fd,
        });
        const res = await resp.json();
        const div = document.getElementById('impUsersResult');
        showImportResult(div, res.success);
        if (res.success) {
            div.innerHTML = `<strong>${res.message}</strong>` +
                (res.errors?.length ? '<br>Erreurs:<ul class="mb-0">' + res.errors.map(e => `<li>${esc(e)}</li>`).join('') + '</ul>' : '');
        } else {
            div.textContent = res.error || 'Erreur';
        }
    } catch (e) {
        alert('Erreur réseau');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Importer collaborateurs';
    }
});

document.getElementById('impPolyBtn')?.addEventListener('click', async () => {
    const file = document.getElementById('impPolyFile').files[0];
    const mois = document.getElementById('impPolyMois').value;
    if (!file || !mois) return alert('Sélectionnez un mois et un fichier');

    const btn = document.getElementById('impPolyBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Import...';

    const fd = new FormData();
    fd.append('action', 'admin_import_polypoint');
    fd.append('mois', mois);
    fd.append('file', file);

    try {
        const resp = await fetch('/spocspace/admin/api.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: fd,
        });
        const res = await resp.json();
        const div = document.getElementById('impPolyResult');
        showImportResult(div, res.success);
        if (res.success) {
            div.innerHTML = `<strong>${res.message}</strong>` +
                (res.skipped?.length ? '<br>Lignes ignorées:<ul class="mb-0">' + res.skipped.map(s => `<li>${esc(s)}</li>`).join('') + '</ul>' : '');
        } else {
            div.textContent = res.error || 'Erreur';
        }
    } catch (e) {
        alert('Erreur réseau');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Importer planning';
    }
});

/* ── Helpers ── */

function showImportResult(div, success) {
    div.classList.remove('d-none', 'alert-success', 'alert-danger');
    div.classList.add('mt-2', 'alert', 'small', success ? 'alert-success' : 'alert-danger');
}

function downloadCsv(rows, filename, sep = ',') {
    const bom = '\uFEFF'; // UTF-8 BOM for Excel compatibility
    const csv = rows.map(row =>
        row.map(cell => {
            const s = String(cell ?? '');
            return s.includes(sep) || s.includes('"') || s.includes('\n')
                ? '"' + s.replace(/"/g, '""') + '"'
                : s;
        }).join(sep)
    ).join('\r\n');

    const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Export téléchargé', 'success');
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
</script>
