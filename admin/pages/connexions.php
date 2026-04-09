<style>
.cnx-table-wrap { border-radius: 14px; overflow: hidden; border: 1.5px solid var(--cl-border-light, #F0EDE8); background: var(--cl-surface, #fff); }
.cnx-table { width: 100%; border-collapse: collapse; background: var(--cl-surface, #fff); }
.cnx-table th {
    font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
    color: var(--cl-text-muted); padding: 10px 14px;
    border-bottom: 1.5px solid var(--cl-border-light, #F0EDE8);
    text-align: left; background: var(--cl-bg, #F7F5F2);
}
.cnx-table th:first-child { border-top-left-radius: 14px; }
.cnx-table th:last-child { border-top-right-radius: 14px; }
.cnx-table td { padding: 10px 14px; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); vertical-align: middle; font-size: .88rem; }
.cnx-table tr:last-child td { border-bottom: none; }
.cnx-table tbody tr:hover td { background: var(--cl-bg, #FAFAF7); }

.cnx-role-badge { font-size: .68rem; padding: 2px 8px; border-radius: 8px; font-weight: 600; }
.cnx-role-admin     { background: #E2B8AE; color: #7B3B2C; }
.cnx-role-direction  { background: #D0C4D8; color: #5B4B6B; }
.cnx-role-responsable { background: #B8C9D4; color: #3B4F6B; }
.cnx-role-collaborateur { background: #bcd2cb; color: #2d4a43; }

.cnx-ip { font-family: monospace; font-size: .78rem; color: var(--cl-text-muted); background: var(--cl-bg, #F7F5F2); padding: 2px 8px; border-radius: 6px; }
.cnx-ua { font-size: .7rem; color: var(--cl-text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.cnx-period-tabs { display: flex; gap: 6px; }
.cnx-period-tab {
    padding: 6px 16px; border-radius: 10px; font-size: .82rem; font-weight: 600;
    cursor: pointer; border: 1.5px solid var(--cl-border-light, #F0EDE8);
    background: transparent; color: var(--cl-text-muted); transition: all .15s;
}
.cnx-period-tab:hover { border-color: var(--cl-border-hover); }
.cnx-period-tab.active { background: var(--cl-surface); border-color: var(--cl-accent); color: var(--cl-text); }

.cnx-chart-wrap { background: var(--cl-surface, #fff); border-radius: 14px; border: 1.5px solid var(--cl-border-light, #F0EDE8); padding: 20px; margin-bottom: 20px; }
.cnx-chart-bars { display: flex; align-items: flex-end; gap: 4px; height: 120px; }
.cnx-chart-bar { flex: 1; background: #bcd2cb; border-radius: 4px 4px 0 0; min-width: 8px; transition: height .3s; position: relative; cursor: default; }
.cnx-chart-bar:hover { background: #2d4a43; }
.cnx-chart-bar:hover .cnx-chart-tip { display: block; }
.cnx-chart-tip { display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1A1A1A; color: #fff; font-size: .68rem; padding: 2px 8px; border-radius: 6px; white-space: nowrap; margin-bottom: 4px; }
.cnx-chart-labels { display: flex; gap: 4px; margin-top: 4px; }
.cnx-chart-labels span { flex: 1; text-align: center; font-size: .6rem; color: var(--cl-text-muted); }

.cnx-top-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--cl-border-light, #F0EDE8); }
.cnx-top-item:last-child { border: none; }
.cnx-top-rank { width: 24px; height: 24px; border-radius: 50%; background: var(--cl-bg); display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; color: var(--cl-text-muted); }

.cnx-avatar {
  width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
  border: 1.5px solid var(--cl-border-light, #F0EDE8); flex-shrink: 0;
}
.cnx-avatar-initials {
  width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: .65rem; font-weight: 700; color: #fff;
  background: #bcd2cb;
}
.cnx-user-cell { display: flex; align-items: center; gap: 10px; }
.cnx-avatar-sm { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1.5px solid var(--cl-border-light, #F0EDE8); }
.cnx-avatar-sm-initials {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .58rem; font-weight: 700; color: #fff;
  background: #bcd2cb;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-person-check"></i> Connexions</h4>
  <div class="d-flex align-items-center gap-2">
    <input type="date" class="form-control form-control-sm" id="cnxDate" style="max-width:160px">
    <div class="cnx-period-tabs">
      <button class="cnx-period-tab active" data-period="day">Jour</button>
      <button class="cnx-period-tab" data-period="week">Semaine</button>
      <button class="cnx-period-tab" data-period="month">Mois</button>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon bg-teal"><i class="bi bi-box-arrow-in-right"></i></div>
      <div><div class="stat-value" id="cnxStatTotal">—</div><div class="stat-label">Connexions</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon bg-green"><i class="bi bi-people"></i></div>
      <div><div class="stat-value" id="cnxStatUsers">—</div><div class="stat-label">Utilisateurs uniques</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-icon bg-orange"><i class="bi bi-globe"></i></div>
      <div><div class="stat-value" id="cnxStatIps">—</div><div class="stat-label">Adresses IP uniques</div></div>
    </div>
  </div>
</div>

<!-- Chart + Top users -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="cnx-chart-wrap">
      <h6 class="fw-bold mb-3" style="font-size:.88rem"><i class="bi bi-bar-chart"></i> Connexions par heure</h6>
      <div class="cnx-chart-bars" id="cnxChart"></div>
      <div class="cnx-chart-labels" id="cnxChartLabels"></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="cnx-chart-wrap">
      <h6 class="fw-bold mb-3" style="font-size:.88rem"><i class="bi bi-trophy"></i> Top utilisateurs</h6>
      <div id="cnxTopUsers"></div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="cnx-table-wrap">
  <table class="cnx-table">
    <thead><tr>
      <th>Utilisateur</th><th>Rôle</th><th>Fonction</th><th>IP</th><th>Navigateur</th><th>Date & Heure</th>
    </tr></thead>
    <tbody id="cnxBody"><tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></td></tr></tbody>
  </table>
</div>

<script<?= nonce() ?>>
(function(){
    const dateInput = document.getElementById('cnxDate');
    let currentPeriod = 'day';
    dateInput.value = new Date().toISOString().split('T')[0];

    const roleLabels = { admin: 'Admin', direction: 'Direction', responsable: 'Responsable', collaborateur: 'Collaborateur' };

    function avatar(photo, prenom, nom, cls) {
        const sz = cls || '';
        if (photo) return '<img src="' + escapeHtml(photo) + '" class="cnx-avatar' + sz + '" alt="">';
        const ini = ((prenom?.[0] || '') + (nom?.[0] || '')).toUpperCase();
        return '<div class="cnx-avatar-initials' + sz + '">' + ini + '</div>';
    }

    function parseUA(ua) {
        if (!ua) return '—';
        if (ua.includes('iPhone') || ua.includes('iPad')) return '<i class="bi bi-phone"></i> iOS';
        if (ua.includes('Android')) return '<i class="bi bi-phone"></i> Android';
        if (ua.includes('Chrome')) return '<i class="bi bi-browser-chrome"></i> Chrome';
        if (ua.includes('Firefox')) return '<i class="bi bi-browser-firefox"></i> Firefox';
        if (ua.includes('Safari')) return '<i class="bi bi-browser-safari"></i> Safari';
        if (ua.includes('Edge')) return '<i class="bi bi-browser-edge"></i> Edge';
        return '<i class="bi bi-globe"></i> Autre';
    }

    async function load() {
        const q = document.getElementById('topbarSearchInput')?.value?.trim() || '';
        try {
        const r = await adminApiPost('admin_get_connexions', { date: dateInput.value, period: currentPeriod, search: q });
        console.log('connexions API:', r);
        if (!r.success) { document.getElementById('cnxBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Erreur: ' + (r.message || 'success=false') + '</td></tr>'; return; }

        // Stats
        const s = r.stats || {};
        document.getElementById('cnxStatTotal').textContent = s.total_connexions || 0;
        document.getElementById('cnxStatUsers').textContent = s.users_uniques || 0;
        document.getElementById('cnxStatIps').textContent = s.ips_uniques || 0;

        // Chart
        const parHeure = r.par_heure || [];
        const hours = {};
        for (let i = 0; i < 24; i++) hours[i] = 0;
        parHeure.forEach(h => hours[h.heure] = parseInt(h.nb));
        const max = Math.max(1, ...Object.values(hours));
        document.getElementById('cnxChart').innerHTML = Object.entries(hours).map(([h, nb]) =>
            '<div class="cnx-chart-bar" style="height:' + Math.max(2, (nb / max) * 100) + '%"><div class="cnx-chart-tip">' + h + 'h — ' + nb + '</div></div>'
        ).join('');
        document.getElementById('cnxChartLabels').innerHTML = Object.keys(hours).map(h =>
            '<span>' + (h % 3 === 0 ? h + 'h' : '') + '</span>'
        ).join('');

        // Top users
        const top = r.top_users || [];
        document.getElementById('cnxTopUsers').innerHTML = top.length
            ? top.map((u, i) =>
                '<div class="cnx-top-item"><div class="cnx-top-rank">' + (i + 1) + '</div>'
                + avatar(u.photo, u.prenom, u.nom, '-sm')
                + '<div style="flex:1"><strong class="small">' + escapeHtml(u.prenom + ' ' + u.nom) + '</strong></div>'
                + '<span class="small text-muted">' + u.nb + ' connexion' + (u.nb > 1 ? 's' : '') + '</span></div>'
            ).join('')
            : '<p class="text-muted small text-center py-2">Aucune connexion</p>';

        // Table
        const rows = r.connexions || [];
        const tbody = document.getElementById('cnxBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-person-check" style="font-size:1.5rem;opacity:.2"></i><br>Aucune connexion</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(c => {
            const dt = new Date(c.created_at);
            const time = dt.toLocaleDateString('fr-CH', {day:'numeric',month:'short'}) + ' ' + dt.toLocaleTimeString('fr-CH', {hour:'2-digit',minute:'2-digit'});
            return '<tr>'
                + '<td><div class="cnx-user-cell">' + avatar(c.photo, c.prenom, c.nom, '') + '<div><strong>' + escapeHtml(c.prenom + ' ' + c.nom) + '</strong><br><span class="text-muted" style="font-size:.72rem">' + escapeHtml(c.email) + '</span></div></div></td>'
                + '<td><span class="cnx-role-badge cnx-role-' + c.role + '">' + (roleLabels[c.role] || c.role) + '</span></td>'
                + '<td class="small">' + escapeHtml(c.fonction_nom || '—') + '</td>'
                + '<td><span class="cnx-ip">' + escapeHtml(c.ip_address) + '</span></td>'
                + '<td class="cnx-ua" title="' + escapeHtml(c.user_agent || '') + '">' + parseUA(c.user_agent) + '</td>'
                + '<td class="small text-muted">' + time + '</td>'
                + '</tr>';
        }).join('');
        } catch(e) {
            console.error('connexions load error', e);
            document.getElementById('cnxBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Erreur: ' + e.message + '</td></tr>';
        }
    }

    // Period tabs
    document.querySelectorAll('.cnx-period-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.cnx-period-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentPeriod = tab.dataset.period;
            load();
        });
    });

    // Date change
    dateInput.addEventListener('change', load);

    // Search
    let searchTO;
    document.getElementById('topbarSearchInput')?.addEventListener('input', () => { clearTimeout(searchTO); searchTO = setTimeout(load, 300); });

    load();
})();
</script>
