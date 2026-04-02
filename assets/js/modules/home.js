/**
 * SpocSpace - Home module - Dashboard
 */
import { apiPost, escapeHtml, formatDateShort, formatDayName } from '../helpers.js';

let currentDate = null;   // selected day (drives the week view)
let currentMonday = null; // derived from currentDate
let menuMonday = null;
let menusCache = [];
let myReservationsCache = {};
let resModal = null;
let menuRefreshInterval = null;

const DAYS_FR = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const DAYS_SHORT = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const CHOIX_LABELS = { menu: 'Menu', salade: 'Salade' };
const PAIEMENT_LABELS = { salaire: 'Salaire', caisse: 'Cash', carte: 'Carte' };

export async function init() {
    const user = window.__SS__?.user;
    const nameEl = document.getElementById('homeUserName');
    if (nameEl && user) nameEl.textContent = user.prenom || '';

    currentDate = new Date();
    currentMonday = getMonday(currentDate);
    menuMonday = getMonday(new Date());

    document.getElementById('homePrevWeek')?.addEventListener('click', () => moveNav(-1));
    document.getElementById('homeNextWeek')?.addEventListener('click', () => moveNav(1));
    document.getElementById('menuPrevWeek')?.addEventListener('click', () => moveMenuWeek(-1));
    document.getElementById('menuNextWeek')?.addEventListener('click', () => moveMenuWeek(1));

    const modalEl = document.getElementById('menuReservationModal');
    if (modalEl) resModal = new bootstrap.Modal(modalEl);

    const ssrData = window.__SS_PAGE_DATA__ || {};
    const desirCount = ssrData.desir_count || 0;
    const maxDesirs = ssrData.max_desirs || 4;
    document.getElementById('statDesirs').textContent = desirCount + '/' + maxDesirs;
    if (user?.taux) document.getElementById('statVacances').textContent = '—';
    const unread = ssrData.unread_count || 0;
    document.getElementById('statMessages').textContent = unread;
    if (unread > 0) {
        const badge = document.getElementById('emailBadge');
        if (badge) badge.style.display = '';
    }

    await Promise.all([loadWeek(), loadNextShift(), loadMenus()]);

    // Auto-refresh menus every 60s so new menus from chef appear live
    menuRefreshInterval = setInterval(loadMenus, 60000);
}

/* ═══════════════════════════════════════════
   PLANNING
   ═══════════════════════════════════════════ */

function getCurrentMonth() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function getMonday(date) {
    const d = new Date(date);
    const day = d.getDay() || 7;
    d.setDate(d.getDate() - day + 1);
    d.setHours(0, 0, 0, 0);
    return d;
}

function fmtISO(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function getWeekDates(monday) {
    const dates = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        dates.push(fmtISO(d));
    }
    return dates;
}

function getMonthsForWeek(monday) {
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    const m1 = `${monday.getFullYear()}-${String(monday.getMonth() + 1).padStart(2, '0')}`;
    const m2 = `${sunday.getFullYear()}-${String(sunday.getMonth() + 1).padStart(2, '0')}`;
    return m1 === m2 ? [m1] : [m1, m2];
}

function weekNum(monday) {
    const thursd = new Date(monday);
    thursd.setDate(thursd.getDate() + 3);
    const yearStart = new Date(thursd.getFullYear(), 0, 1);
    return Math.ceil(((thursd - yearStart) / 86400000 + yearStart.getDay() + 1) / 7);
}

async function loadWeek() {
    const weekDates = getWeekDates(currentMonday);
    const months = getMonthsForWeek(currentMonday);
    const sunday = new Date(currentMonday);
    sunday.setDate(currentMonday.getDate() + 6);

    const todayMonday = getMonday(new Date());
    const isCurrentWeek = currentMonday.getTime() === todayMonday.getTime();
    const label = document.getElementById('homeWeekLabel');
    const prevBtn = document.getElementById('homePrevWeek');
    const nextBtn = document.getElementById('homeNextWeek');

    if (isCurrentWeek) {
        const dayIdx = (currentDate.getDay() + 6) % 7;
        if (label) label.textContent = `${DAYS_FR[dayIdx]} · ${formatDateShort(fmtISO(currentDate))}`;
        if (prevBtn) prevBtn.title = 'Jour précédent';
        if (nextBtn) nextBtn.title = 'Jour suivant';
    } else {
        if (label) label.textContent = `S${weekNum(currentMonday)} · ${formatDateShort(fmtISO(currentMonday))} — ${formatDateShort(fmtISO(sunday))}`;
        if (prevBtn) prevBtn.title = 'Semaine précédente';
        if (nextBtn) nextBtn.title = 'Semaine suivante';
    }

    const results = await Promise.all(months.map(m => apiPost('get_mon_planning', { mois: m })));
    renderWeekPlanning(results.flatMap(r => r.assignations || []), weekDates, isCurrentWeek);
}

async function loadNextShift() {
    const today = fmtISO(new Date());
    const todayMonth = today.slice(0, 7);
    const nextMonth = (() => { const d = new Date(); d.setMonth(d.getMonth() + 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; })();
    const results = await Promise.all([apiPost('get_mon_planning', { mois: todayMonth }), apiPost('get_mon_planning', { mois: nextMonth })]);
    const next = results.flatMap(r => r.assignations || []).filter(a => a.date_jour >= today).sort((a, b) => a.date_jour.localeCompare(b.date_jour))[0];
    const el = document.getElementById('statNextShift');
    if (el && next) {
        const color = next.couleur || '#1a1a1a';
        el.innerHTML = `<span style="display:inline-block;background:${escapeHtml(color)};color:#fff;padding:0.1rem 0.5rem;border-radius:4px;font-size:0.85rem;font-weight:600">${escapeHtml(next.horaire_code || '—')}</span> <span style="font-size:0.85rem">${formatDateShort(next.date_jour)}</span>`;
    }
}

function moveNav(dir) {
    const todayMonday = getMonday(new Date());
    const isCurrentWeek = currentMonday.getTime() === todayMonday.getTime();

    currentDate = new Date(currentDate);
    if (isCurrentWeek) {
        // Day-by-day on current week
        currentDate.setDate(currentDate.getDate() + dir);
    } else {
        // Week-by-week on other weeks
        currentDate.setDate(currentDate.getDate() + dir * 7);
    }
    currentMonday = getMonday(currentDate);
    loadWeek();
}

function renderWeekPlanning(assignations, weekDates, isCurrentWeek = false) {
    const container = document.getElementById('homeWeekPlanning');
    const today = fmtISO(new Date());
    const selectedDay = isCurrentWeek ? fmtISO(currentDate) : null;
    let html = '<div style="display:flex;flex-direction:column;gap:0">';
    for (const dateStr of weekDates) {
        const a = assignations.find(x => x.date_jour === dateStr);
        const isToday = dateStr === today;
        const isSelected = selectedDay === dateStr;
        const isDimmed = isCurrentWeek && !isSelected;
        const isWeekend = (() => { const d = new Date(dateStr + 'T00:00:00'); return d.getDay() === 0 || d.getDay() === 6; })();
        const todayStyle = isSelected
            ? 'background:var(--ss-accent-bg, rgba(0,180,160,0.06));border-left:3px solid var(--ss-teal)'
            : (isToday ? 'background:var(--ss-accent-bg, rgba(0,180,160,0.03));border-left:3px solid #ccc' : '');
        const dimStyle = isDimmed ? 'opacity:0.38;' : '';

        if (a) {
            const color = a.couleur || '#1a1a1a';
            html += `
              <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0.5rem;border-bottom:1px solid var(--ss-border-light);${todayStyle}${dimStyle}">
                <div style="min-width:65px">
                  <strong${isWeekend ? ' class="text-muted"' : ''}>${escapeHtml(formatDayName(dateStr))}</strong><br>
                  <small class="text-muted">${escapeHtml(formatDateShort(dateStr))}</small>
                </div>
                <span style="background:${color};padding:0.25rem 0.6rem;border-radius:4px;color:#fff;font-weight:600;font-size:0.82rem">
                  ${escapeHtml(a.horaire_code || '—')}
                </span>
                <div>
                  <small class="text-muted">${escapeHtml(a.heure_debut?.slice(0, 5) || '')} - ${escapeHtml(a.heure_fin?.slice(0, 5) || '')}</small>
                  ${a.module_nom ? `<br><small class="text-muted">${escapeHtml(a.module_nom)}</small>` : ''}
                </div>
              </div>`;
        } else {
            html += `
              <div class="home-rest-day" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0.5rem;border-bottom:1px solid var(--ss-border-light);${todayStyle}${dimStyle}">
                <div style="min-width:65px">
                  <strong${isWeekend ? ' class="text-muted"' : ''}>${escapeHtml(formatDayName(dateStr))}</strong><br>
                  <small class="text-muted">${escapeHtml(formatDateShort(dateStr))}</small>
                </div>
                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.25rem 0.6rem;border-radius:4px;border:1.5px solid #bcd2cb;background:repeating-linear-gradient(45deg,rgba(188,210,203,0.18),rgba(188,210,203,0.18) 3px,transparent 3px,transparent 7px);color:#6b9e8f;font-weight:600;font-size:0.82rem">
                  <i class="bi bi-moon-stars" style="font-size:0.75rem"></i> Repos
                </span>
              </div>`;
        }
    }
    html += '</div>';
    container.innerHTML = html;
}

/* ═══════════════════════════════════════════
   MENU DU JOUR (midi 7j/7, choix menu/salade)
   ═══════════════════════════════════════════ */

async function loadMenus() {
    const res = await apiPost('get_menus_semaine', { date: fmtISO(menuMonday) });
    menusCache = res.menus || [];
    myReservationsCache = res.my_reservations || {};

    const label = document.getElementById('menuWeekLabel');
    if (label) label.textContent = `S${weekNum(menuMonday)}`;

    renderMenus();
}

function moveMenuWeek(dir) {
    menuMonday = new Date(menuMonday);
    menuMonday.setDate(menuMonday.getDate() + dir * 7);
    loadMenus();
}

function renderMenus() {
    const container = document.getElementById('homeMenus');

    const weekDates = getWeekDates(menuMonday);
    const today = fmtISO(new Date());
    const maxDate = fmtISO(new Date(Date.now() + 7 * 86400000));

    // Index menus by date
    const menuByDate = {};
    for (const m of menusCache) menuByDate[m.date_jour] = m;

    let html = '<div style="display:flex;flex-direction:column;gap:0">';

    for (const dateStr of weekDates) {
        const menu = menuByDate[dateStr];
        const isToday = dateStr === today;
        const isPast = dateStr < today;
        const canReserve = dateStr >= today && dateStr <= maxDate;
        const isWeekend = (() => { const d = new Date(dateStr + 'T00:00:00'); return d.getDay() === 0 || d.getDay() === 6; })();
        const todayBg = isToday ? 'background:var(--ss-accent-bg);border-left:3px solid var(--ss-teal);' : '';
        const pastOpacity = isPast ? 'opacity:0.45;' : '';

        html += `
        <div class="home-menu-row" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0.75rem;border-bottom:1px solid var(--ss-border-light);${todayBg}${pastOpacity}cursor:${menu ? 'pointer' : 'default'};transition:background 0.15s"${menu ? ` data-menu-id="${escapeHtml(menu.id)}"` : ''}>
          <div style="min-width:65px">
            <strong${isWeekend ? ' class="text-muted"' : ''}>${escapeHtml(formatDayName(dateStr))}</strong><br>
            <small class="text-muted">${escapeHtml(formatDateShort(dateStr))}</small>
          </div>`;

        if (menu) {
            const myRes = myReservationsCache[menu.id];
            html += `
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <i class="bi bi-egg-fried" style="font-size:0.75rem;color:var(--ss-orange);margin-right:0.2rem"></i>
              ${escapeHtml(menu.plat)}
            </div>
            ${menu.salade ? `<div style="font-size:0.78rem;color:var(--ss-text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <i class="bi bi-flower1" style="font-size:0.65rem;color:#16A34A;margin-right:0.2rem"></i>
              ${escapeHtml(menu.salade)}
            </div>` : ''}
          </div>
          <div style="flex-shrink:0">
            ${myRes
              ? `<span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.18rem 0.55rem;border-radius:6px;background:#bcd2cb;color:#2d4a43;font-size:0.75rem;font-weight:500">
                   <i class="bi bi-check-circle-fill" style="font-size:0.7rem"></i> ${myRes.choix === 'salade' ? 'Salade commandée' : 'Menu commandé'}
                 </span>`
              : canReserve
                ? `<button class="btn btn-sm btn-dark menu-reserve-btn" data-menu-id="${escapeHtml(menu.id)}" style="font-size:0.75rem;padding:0.15rem 0.5rem;white-space:nowrap;border-radius:8px">
                     <i class="bi bi-bookmark-plus"></i> Commander
                   </button>`
                : ''
            }
          </div>`;
        } else {
            html += `<div style="flex:1"><small class="text-muted" style="font-style:italic">Pas de menu</small></div>`;
        }

        html += '</div>';
    }

    html += '</div>';
    container.innerHTML = html;

    // Row click → detail
    container.querySelectorAll('.home-menu-row[data-menu-id]').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('.menu-reserve-btn')) return;
            openMenuDetail(row.dataset.menuId);
        });
    });
    // Reserve button
    container.querySelectorAll('.menu-reserve-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openMenuDetail(btn.dataset.menuId);
        });
    });
}

/* ═══════════════════════════════════════════
   MODAL detail / réservation
   ═══════════════════════════════════════════ */

function openMenuDetail(menuId) {
    const menu = menusCache.find(m => m.id === menuId);
    if (!menu) return;

    const myRes = myReservationsCache[menuId];
    const today = fmtISO(new Date());
    const maxDate = fmtISO(new Date(Date.now() + 7 * 86400000));
    const canReserve = menu.date_jour >= today && menu.date_jour <= maxDate;

    const d = new Date(menu.date_jour + 'T00:00:00');
    const dayIdx = (d.getDay() + 6) % 7;

    document.getElementById('menuReservationTitle').textContent = `${DAYS_FR[dayIdx]} ${formatDateShort(menu.date_jour)} — Midi`;

    let detail = '<div style="display:flex;flex-direction:column;gap:0.6rem">';
    if (menu.entree) detail += menuLine('Entrée', menu.entree, 'bi-cup-hot');
    detail += menuLine('Plat du jour', menu.plat, 'bi-egg-fried', true);
    if (menu.salade) detail += menuLine('Salade', menu.salade, 'bi-flower1');
    if (menu.accompagnement) detail += menuLine('Accompagnement', menu.accompagnement, 'bi-grid-3x3');
    if (menu.dessert) detail += menuLine('Dessert', menu.dessert, 'bi-cake2');
    if (menu.remarques) detail += `<div style="padding:0.5rem 0.75rem;background:var(--ss-accent-bg);border-radius:6px;font-size:0.85rem;color:var(--ss-text-secondary)"><i class="bi bi-info-circle"></i> ${escapeHtml(menu.remarques)}</div>`;
    detail += '</div>';
    document.getElementById('menuDetailContent').innerHTML = detail;

    const form = document.getElementById('menuReservationForm');
    const footer = document.getElementById('menuReservationFooter');
    if (myRes) {
        buildExistingReservationView(form, footer, menu, myRes);
    } else if (canReserve) {
        buildReservationForm(form, footer, menu);
    } else {
        form.innerHTML = '';
        footer.innerHTML = `<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>`;
    }

    if (resModal) resModal.show();
}

function menuLine(label, value, icon, bold) {
    return `<div>
        <small style="font-weight:600;color:var(--ss-text-muted);text-transform:uppercase;font-size:0.68rem;letter-spacing:0.5px"><i class="bi ${icon}" style="font-size:0.65rem"></i> ${label}</small>
        <div style="font-size:${bold ? '1.02rem' : '0.93rem'};${bold ? 'font-weight:600' : ''}">${escapeHtml(value)}</div>
    </div>`;
}

function buildExistingReservationView(form, footer, menu, myRes) {
    const today = fmtISO(new Date());
    form.innerHTML = `
        <div style="padding:0.9rem;background:#bcd2cb22;border:1.5px solid #bcd2cb;border-radius:8px">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem">
                <i class="bi bi-check-circle-fill" style="color:#2d4a43;font-size:1.1rem"></i>
                <strong style="color:#2d4a43">Commande confirmée</strong>
            </div>
            <div style="font-size:0.88rem;color:var(--ss-text-secondary);display:flex;flex-wrap:wrap;gap:0.5rem 1.2rem">
                <span><i class="bi ${myRes.choix === 'salade' ? 'bi-flower1' : 'bi-egg-fried'}"></i> ${escapeHtml(CHOIX_LABELS[myRes.choix] || 'Menu')}</span>
                <span><i class="bi bi-people"></i> ${myRes.nb_personnes > 1 ? myRes.nb_personnes + ' pers.' : '1 pers.'}</span>
                <span><i class="bi bi-wallet2"></i> ${escapeHtml(PAIEMENT_LABELS[myRes.paiement] || myRes.paiement)}</span>
                ${myRes.remarques ? `<span style="display:block;width:100%;font-style:italic"><i class="bi bi-chat-text"></i> ${escapeHtml(myRes.remarques)}</span>` : ''}
            </div>
        </div>
    `;
    footer.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
        ${menu.date_jour >= today ? `<button type="button" class="btn btn-sm btn-outline-danger btn-cancel-res"><i class="bi bi-x-lg"></i> Annuler la commande</button>` : ''}
    `;
    footer.querySelector('.btn-cancel-res')?.addEventListener('click', () => {
        const cancelModalEl = document.getElementById('menuCancelModal');
        if (!cancelModalEl) return;
        const cancelModal = new bootstrap.Modal(cancelModalEl);
        const confirmBtn = document.getElementById('menuCancelConfirmBtn');
        const handler = async () => {
            confirmBtn.disabled = true;
            await apiPost('annuler_reservation_menu', { reservation_id: myRes.id });
            cancelModal.hide();
            closeModal();
            await loadMenus();
            confirmBtn.disabled = false;
        };
        confirmBtn.replaceWith(confirmBtn.cloneNode(true));
        document.getElementById('menuCancelConfirmBtn').addEventListener('click', handler);
        cancelModal.show();
    });
}

function buildReservationForm(form, footer, menu) {
    const hasSalade = !!menu.salade;
    form.innerHTML = `
        <input type="hidden" name="menuId" value="${escapeHtml(menu.id)}">
        <div style="margin-bottom:1rem">
            <label class="form-label" style="font-weight:600">Votre choix</label>
            <div style="display:flex;gap:0.5rem">
                <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-teal);border-radius:10px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
                    <input type="radio" name="resChoix" value="menu" checked style="display:none">
                    <i class="bi bi-egg-fried" style="font-size:1.2rem;color:var(--ss-orange)"></i>
                    <div><div style="font-weight:700;font-size:0.9rem">Menu du jour</div><small class="text-muted" style="font-size:0.78rem">${escapeHtml(menu.plat)}</small></div>
                </label>
                ${hasSalade ? `
                <label class="menu-choix-option" style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.7rem 1rem;border:2px solid var(--ss-border);border-radius:10px;cursor:pointer;transition:all 0.15s">
                    <input type="radio" name="resChoix" value="salade" style="display:none">
                    <i class="bi bi-flower1" style="font-size:1.2rem;color:#16A34A"></i>
                    <div><div style="font-weight:700;font-size:0.9rem">Salade</div><small class="text-muted" style="font-size:0.78rem">${escapeHtml(menu.salade)}</small></div>
                </label>` : ''}
            </div>
        </div>
        <div style="margin-bottom:1rem">
            <label class="form-label" style="font-weight:600">Nombre de personnes</label>
            <select class="form-select" name="resNb">
                <option value="1">1 personne</option><option value="2">2 personnes</option><option value="3">3 personnes</option><option value="4">4 personnes</option><option value="5">5 personnes</option>
            </select>
        </div>
        <div style="margin-bottom:1rem">
            <label class="form-label" style="font-weight:600">Mode de paiement</label>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-teal);border-radius:8px;cursor:pointer;transition:all 0.15s;background:var(--ss-accent-bg)">
                    <input type="radio" name="resPaiement" value="salaire" checked style="accent-color:var(--ss-teal)"> <i class="bi bi-wallet2"></i> Retenue salaire
                </label>
                <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
                    <input type="radio" name="resPaiement" value="caisse" style="accent-color:var(--ss-teal)"> <i class="bi bi-cash-coin"></i> Cash caisse
                </label>
                <label class="menu-pay-option" style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 0.9rem;border:1.5px solid var(--ss-border);border-radius:8px;cursor:pointer;transition:all 0.15s">
                    <input type="radio" name="resPaiement" value="carte" style="accent-color:var(--ss-teal)"> <i class="bi bi-credit-card"></i> Carte
                </label>
            </div>
        </div>
        <div style="margin-bottom:0">
            <label class="form-label" style="font-weight:600">Demande spéciale <small class="text-muted">(optionnel)</small></label>
            <input type="text" class="form-control" name="resRemarques" placeholder="Ex: sans viande, sans huile, allergie noix..." maxlength="500">
            <div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-top:0.5rem">
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans viande">Sans viande</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans porc">Sans porc</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans poisson">Sans poisson</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans gluten">Sans gluten</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans lactose">Sans lactose</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Sans crevettes">Sans crevettes</button>
                <button type="button" class="btn btn-sm btn-outline-secondary menu-quick-tag" data-tag="Végétarien">Végétarien</button>
            </div>
        </div>
    `;

    footer.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-sm btn-dark" id="resSubmitBtn"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
    `;

    form.querySelectorAll('.menu-choix-option').forEach(opt => {
        opt.querySelector('input').addEventListener('change', () => {
            form.querySelectorAll('.menu-choix-option').forEach(o => { o.style.borderColor = 'var(--ss-border)'; o.style.background = ''; });
            opt.style.borderColor = 'var(--ss-teal)'; opt.style.background = 'var(--ss-accent-bg)';
        });
    });
    form.querySelectorAll('input[name="resPaiement"]').forEach(radio => {
        radio.addEventListener('change', () => {
            form.querySelectorAll('.menu-pay-option').forEach(el => { el.style.borderColor = 'var(--ss-border)'; el.style.background = ''; });
            if (radio.checked) { radio.closest('.menu-pay-option').style.borderColor = 'var(--ss-teal)'; radio.closest('.menu-pay-option').style.background = 'var(--ss-accent-bg)'; }
        });
    });
    form.querySelectorAll('.menu-quick-tag').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const input = form.querySelector('[name="resRemarques"]');
            const tag = btn.dataset.tag;
            if (input.value.toLowerCase().includes(tag.toLowerCase())) return;
            input.value = input.value.trim() ? input.value.trim() + ', ' + tag : tag;
            btn.style.background = 'var(--ss-accent-bg)'; btn.style.borderColor = 'var(--ss-teal)';
        });
    });
    footer.querySelector('#resSubmitBtn')?.addEventListener('click', () => {
        form.requestSubmit();
    });
    form.addEventListener('submit', handleReservation);
}

async function handleReservation(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('#resSubmitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Envoi...'; }

    try {
        const res = await apiPost('reserver_menu', {
            menu_id: form.querySelector('[name="menuId"]')?.value,
            choix: form.querySelector('input[name="resChoix"]:checked')?.value || 'menu',
            nb_personnes: parseInt(form.querySelector('[name="resNb"]')?.value || 1),
            remarques: form.querySelector('[name="resRemarques"]')?.value || '',
            paiement: form.querySelector('input[name="resPaiement"]:checked')?.value || 'salaire',
        });
        if (res.success) { closeModal(); await loadMenus(); }
        else alert(res.message || 'Erreur');
    } catch { alert('Erreur réseau'); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Confirmer'; } }
}

function closeModal() { if (resModal) resModal.hide(); }

export function destroy() {
    if (menuRefreshInterval) { clearInterval(menuRefreshInterval); menuRefreshInterval = null; }
    resModal = null;
    currentDate = null;
    currentMonday = null;
    menuMonday = null;
    menusCache = [];
    myReservationsCache = {};
}
