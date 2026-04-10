<?php
require_once __DIR__ . '/../init.php';
$emsNom = Db::getOne("SELECT config_value FROM ems_config WHERE config_key = 'ems_nom'") ?: 'E.M.S. La Terrassière SA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Livre d'or — <?= h($emsNom) ?></title>
<meta name="description" content="Livre d'or de l'EMS La Terrassière. Témoignages des familles sur la prise en charge de leurs proches et la qualité du personnel.">
<meta name="robots" content="index, follow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/spocspace/assets/css/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="/spocspace/website/assets/css/website.css">
<?php include __DIR__ . '/includes/footer-styles.php'; ?>
<style>
:root {
  --lo-green: #2E7D32;
  --lo-green-hover: #1B5E20;
  --lo-green-pale: #81C784;
  --lo-green-bg: rgba(46,125,50,0.06);
  --lo-gold: #F9D835;
  --lo-gold-dark: #E6C220;
  --lo-bg: #FAFDF7;
  --lo-bg-alt: #F3F8EF;
  --lo-surface: #FFFFFF;
  --lo-border: #D8E8D0;
  --lo-text: #1A2E1A;
  --lo-text-secondary: #4A6548;
  --lo-text-muted: #7E9A7A;
  --lo-radius: 16px;
  --lo-radius-sm: 12px;
  --lo-shadow: 0 1px 3px rgba(46,125,50,0.05), 0 1px 2px rgba(0,0,0,0.02);
  --lo-shadow-md: 0 4px 14px rgba(46,125,50,0.10), 0 2px 4px rgba(0,0,0,0.04);
}

* { box-sizing: border-box; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--lo-bg);
  color: var(--lo-text);
  line-height: 1.6;
  margin: 0;
}

/* ─── Hero ─── */
.lo-hero {
  background-color: #D8E8CC;
  background-image:
    linear-gradient(135deg, rgba(186,214,168,0.65) 0%, rgba(216,232,204,0.60) 100%),
    url('/spocspace/website/assets/img/background-image-pattern-plus.spoc-space.svg');
  background-repeat: no-repeat, repeat;
  background-size: auto, 120px 120px;
  padding: 120px 20px 60px;
  text-align: center;
  border-bottom: 1px solid var(--lo-border);
  position: relative;
}
.lo-hero > * { position: relative; z-index: 1; }
.lo-hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 600;
  color: var(--lo-text);
  margin: 0 0 12px;
}
.lo-hero h1 i { color: var(--lo-gold-dark); }
.lo-hero p {
  font-size: 1.05rem;
  color: var(--lo-text-secondary);
  max-width: 640px;
  margin: 0 auto 28px;
}
.lo-stats {
  display: inline-flex; gap: 32px; flex-wrap: wrap; justify-content: center;
  background: var(--lo-surface);
  border: 1px solid var(--lo-border);
  border-radius: 100px;
  padding: 14px 32px;
  box-shadow: var(--lo-shadow);
}
.lo-stat { display: flex; flex-direction: column; align-items: center; }
.lo-stat-num {
  font-size: 1.4rem; font-weight: 700; color: var(--lo-green); line-height: 1;
}
.lo-stat-num small { font-size: .75rem; color: var(--lo-text-muted); font-weight: 500; }
.lo-stat-label { font-size: .72rem; color: var(--lo-text-muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
.lo-stars-big { color: var(--lo-gold-dark); font-size: 1.1rem; letter-spacing: 2px; }

/* ─── Section ─── */
.lo-section { padding: 60px 20px; max-width: 1200px; margin: 0 auto; }
.lo-section-title {
  text-align: center;
  margin-bottom: 36px;
}
.lo-section-title .lo-overtitle {
  display: inline-block;
  font-size: .8rem;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--lo-green);
  font-weight: 600;
  margin-bottom: 8px;
}
.lo-section-title h2 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  font-weight: 600;
  color: var(--lo-text);
  margin: 0;
}
.lo-section-title h2 em { color: var(--lo-green); font-style: italic; }

/* ─── MASONRY chronologique (style Google Keep) ─── */
.lo-grid {
  column-count: 3;
  column-gap: 24px;
}
@media (max-width: 1024px) { .lo-grid { column-count: 2; } }
@media (max-width: 640px)  { .lo-grid { column-count: 1; } }

.lo-grid > .lo-card,
.lo-grid > .lo-empty {
  break-inside: avoid;
  -webkit-column-break-inside: avoid;
  page-break-inside: avoid;
  margin-bottom: 24px;
  display: inline-block;
  width: 100%;
}

.lo-card {
  background: var(--lo-surface);
  border: 1px solid var(--lo-border);
  border-radius: var(--lo-radius);
  padding: 24px;
  box-shadow: var(--lo-shadow);
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 1;
  transform-origin: center center;
  transition: transform .45s cubic-bezier(.22, 1, .36, 1),
              box-shadow .45s cubic-bezier(.22, 1, .36, 1),
              border-color .35s ease,
              z-index 0s linear .35s;
}
.lo-card:hover {
  transform: scale(1.10) translateY(-6px);
  box-shadow:
    0 28px 60px rgba(46,125,50,.22),
    0 10px 24px rgba(0,0,0,.10);
  border-color: var(--lo-green-pale);
  z-index: 20;
  transition: transform .45s cubic-bezier(.22, 1, .36, 1),
              box-shadow .45s cubic-bezier(.22, 1, .36, 1),
              border-color .35s ease,
              z-index 0s linear 0s;
}

/* Flou léger sur les autres cartes quand l'une est survolée */
.lo-grid:has(.lo-card:hover) .lo-card:not(:hover) {
  filter: blur(2px) saturate(.85);
  opacity: .75;
  transition: filter .4s ease, opacity .4s ease;
}
.lo-card {
  transition: transform .45s cubic-bezier(.22, 1, .36, 1),
              box-shadow .45s cubic-bezier(.22, 1, .36, 1),
              border-color .35s ease,
              z-index 0s linear .35s,
              filter .4s ease,
              opacity .4s ease;
}
.lo-card-quote {
  position: absolute;
  top: 14px; right: 18px;
  font-size: 3rem;
  font-family: 'Playfair Display', serif;
  color: var(--lo-green-pale);
  opacity: .35;
  line-height: 1;
}
.lo-card-stars { color: var(--lo-gold-dark); font-size: 1rem; letter-spacing: 2px; margin-bottom: 10px; }
.lo-card-title {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--lo-text);
  margin: 0 0 8px;
}
.lo-card-text {
  color: var(--lo-text-secondary);
  font-size: .92rem;
  margin: 0 0 16px;
  white-space: pre-wrap;
}
.lo-card-footer {
  border-top: 1px solid var(--lo-border);
  padding-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.lo-card-author {
  font-size: .85rem;
  font-weight: 600;
  color: var(--lo-text);
}
.lo-card-author small {
  display: block;
  font-size: .72rem;
  font-weight: 400;
  color: var(--lo-text-muted);
  margin-top: 2px;
}
.lo-card-cible {
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .5px;
  background: var(--lo-green-bg);
  color: var(--lo-green);
  padding: 4px 10px;
  border-radius: 100px;
  font-weight: 600;
}
.lo-card.lo-pinned { border-color: var(--lo-gold-dark); background: linear-gradient(180deg, #FFFCEB 0%, #fff 60%); }
.lo-card.lo-pinned .lo-card-cible { background: rgba(230,194,32,.15); color: #B58E0E; }

/* ─── Form ─── */
.lo-form-wrap {
  max-width: 760px;
  margin: 0 auto;
  background: var(--lo-surface);
  border: 1px solid var(--lo-border);
  border-radius: var(--lo-radius);
  padding: 36px;
  box-shadow: var(--lo-shadow-md);
}
.lo-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
@media (max-width: 600px) { .lo-form-row { grid-template-columns: 1fr; } }
.lo-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.lo-form-group label {
  font-size: .85rem; font-weight: 500; color: var(--lo-text-secondary);
}
.lo-form-group label .lo-req { color: #d32f2f; }
.lo-input, .lo-textarea, .lo-select {
  padding: 11px 14px; border: 1.5px solid var(--lo-border); border-radius: 10px;
  font-size: .92rem; font-family: inherit; color: var(--lo-text);
  background: var(--lo-surface); transition: border-color .2s, box-shadow .2s;
}
.lo-input:focus, .lo-textarea:focus, .lo-select:focus {
  outline: none; border-color: var(--lo-green);
  box-shadow: 0 0 0 3px rgba(46,125,50,.10);
}
.lo-textarea { resize: vertical; min-height: 130px; }

/* ── Star rating input ── */
.lo-rating {
  display: inline-flex; gap: 6px; direction: rtl;
}
.lo-rating input { display: none; }
.lo-rating label {
  font-size: 2rem;
  color: #DEDEDE;
  cursor: pointer;
  transition: color .15s, transform .15s;
}
.lo-rating label:hover,
.lo-rating label:hover ~ label,
.lo-rating input:checked ~ label {
  color: var(--lo-gold-dark);
}
.lo-rating label:hover { transform: scale(1.1); }

.lo-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px 32px;
  background: var(--lo-green); color: #fff;
  border: none; border-radius: 10px;
  font-size: .95rem; font-weight: 600;
  cursor: pointer; transition: all .2s;
}
.lo-btn:hover { background: var(--lo-green-hover); transform: translateY(-1px); box-shadow: var(--lo-shadow-md); }
.lo-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

.lo-alert {
  padding: 14px 18px;
  border-radius: 10px;
  font-size: .9rem;
  margin-bottom: 20px;
  display: none;
}
.lo-alert.show { display: block; }
.lo-alert-success { background: rgba(46,125,50,.10); color: var(--lo-green); border: 1px solid rgba(46,125,50,.25); }
.lo-alert-error { background: rgba(211,47,47,.08); color: #c62828; border: 1px solid rgba(211,47,47,.20); }

.lo-empty {
  text-align: center; padding: 40px; color: var(--lo-text-muted);
}
.lo-empty i { font-size: 2.4rem; display: block; margin-bottom: 10px; opacity: .5; }

.lo-back-home {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--lo-green); text-decoration: none; font-size: .9rem; font-weight: 500;
  margin-bottom: 12px;
}
.lo-back-home:hover { color: var(--lo-green-hover); }

/* ─── Breadcrumb ─── */
.lo-breadcrumb-wrap {
  border-bottom: 1px solid #E5EAE078;
}
.lo-breadcrumb {
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .87rem;
  color: var(--lo-text-muted);
  flex-wrap: wrap;
}
.lo-breadcrumb a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--lo-text-secondary);
  text-decoration: none;
  transition: color .2s;
  font-weight: 500;
}
.lo-breadcrumb a:hover { color: var(--lo-green); }
.lo-breadcrumb a i { font-size: .95rem; }
.lo-breadcrumb-sep {
  color: #C8D4C2;
  font-size: .8rem;
  display: inline-flex;
  align-items: center;
}
.lo-breadcrumb-current {
  color: var(--lo-text);
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.lo-breadcrumb-current i { color: var(--lo-gold-dark); }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══ HERO ═══ -->
<section class="lo-hero">
  <div style="max-width:900px;margin:0 auto">
    <h1><i class="bi bi-journal-bookmark-fill"></i> Livre d'or</h1>
    <p>Les familles partagent leur expérience sur la prise en charge de leurs proches, la qualité du personnel et la vie au sein de l'<?= h($emsNom) ?>.</p>

    <div class="lo-stats" id="loStats" style="display:none">
      <div class="lo-stat">
        <div class="lo-stat-num" id="loStatTotal">0</div>
        <div class="lo-stat-label">Témoignages</div>
      </div>
      <div class="lo-stat">
        <div class="lo-stat-num"><span id="loStatMoy">0</span><small>/5</small></div>
        <div class="lo-stat-label">Note moyenne</div>
      </div>
      <div class="lo-stat">
        <div class="lo-stars-big" id="loStatStars">★★★★★</div>
        <div class="lo-stat-label">Satisfaction</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ BREADCRUMB ═══ -->
<nav class="lo-breadcrumb-wrap" aria-label="Fil d'Ariane">
  <ol class="lo-breadcrumb">
    <li><a href="/spocspace/website/"><i class="bi bi-house-door-fill"></i> Accueil</a></li>
    <li class="lo-breadcrumb-sep"><i class="bi bi-chevron-right"></i></li>
    <li class="lo-breadcrumb-current" aria-current="page"><i class="bi bi-journal-bookmark-fill"></i> Livre d'or</li>
  </ol>
</nav>

<!-- ═══ LISTE CHRONOLOGIQUE ═══ -->
<section class="lo-section" style="padding-top:50px">
  <div class="lo-section-title">
    <span class="lo-overtitle">Témoignages</span>
    <h2>Ce que disent <em>les familles</em></h2>
  </div>

  <div class="lo-grid" id="loTrack">
    <div class="lo-empty" style="grid-column:1/-1"><i class="bi bi-arrow-repeat"></i> Chargement des témoignages…</div>
  </div>
</section>

<!-- ═══ FORM ═══ -->
<section class="lo-section" style="background:var(--lo-bg-alt);max-width:none;margin:0;padding:80px 20px">
  <div style="max-width:760px;margin:0 auto">
    <div class="lo-section-title">
      <span class="lo-overtitle">Partager</span>
      <h2>Laissez votre <em>témoignage</em></h2>
    </div>

    <div class="lo-form-wrap">
      <div class="lo-alert lo-alert-success" id="loAlertSuccess"></div>
      <div class="lo-alert lo-alert-error" id="loAlertError"></div>

      <form id="loForm">
        <div class="lo-form-row">
          <div class="lo-form-group" style="margin:0">
            <label>Votre nom <span class="lo-req">*</span></label>
            <input type="text" class="lo-input" name="nom" required maxlength="120" placeholder="Marie D.">
          </div>
          <div class="lo-form-group" style="margin:0">
            <label>Email (non publié)</label>
            <input type="email" class="lo-input" name="email" maxlength="200" placeholder="vous@exemple.ch">
          </div>
        </div>

        <div class="lo-form-row">
          <div class="lo-form-group" style="margin:0">
            <label>Lien avec le résident</label>
            <input type="text" class="lo-input" name="lien_resident" maxlength="200" placeholder="Fille de M. Dupont">
          </div>
          <div class="lo-form-group" style="margin:0">
            <label>Concerne <span class="lo-req">*</span></label>
            <select class="lo-select" name="cible" required>
              <option value="ems">L'établissement en général</option>
              <option value="personnel">Le personnel soignant</option>
              <option value="prise_en_charge">La prise en charge de mon proche</option>
              <option value="vie">La vie sociale et les animations</option>
              <option value="autre">Autre</option>
            </select>
          </div>
        </div>

        <div class="lo-form-group">
          <label>Votre note <span class="lo-req">*</span></label>
          <div class="lo-rating" id="loRating">
            <input type="radio" id="lo-star5" name="note" value="5" checked><label for="lo-star5" title="Excellent">★</label>
            <input type="radio" id="lo-star4" name="note" value="4"><label for="lo-star4" title="Très bien">★</label>
            <input type="radio" id="lo-star3" name="note" value="3"><label for="lo-star3" title="Bien">★</label>
            <input type="radio" id="lo-star2" name="note" value="2"><label for="lo-star2" title="Passable">★</label>
            <input type="radio" id="lo-star1" name="note" value="1"><label for="lo-star1" title="Décevant">★</label>
          </div>
        </div>

        <div class="lo-form-group">
          <label>Titre (optionnel)</label>
          <input type="text" class="lo-input" name="titre" maxlength="200" placeholder="Une équipe formidable">
        </div>

        <div class="lo-form-group">
          <label>Votre témoignage <span class="lo-req">*</span></label>
          <textarea class="lo-textarea" name="message" required minlength="10" maxlength="3000"
                    placeholder="Partagez votre expérience : la prise en charge, l'écoute, les soins, la vie au quotidien…"></textarea>
        </div>

        <div style="text-align:center;margin-top:24px">
          <button type="submit" class="lo-btn" id="loSubmitBtn">
            <i class="bi bi-send"></i> Publier mon témoignage
          </button>
          <p style="font-size:.78rem;color:var(--lo-text-muted);margin-top:14px">
            Votre témoignage sera publié après validation par notre équipe.
          </p>
        </div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
  const TRACK = document.getElementById('loTrack');
  const STATS = document.getElementById('loStats');
  const STAT_TOTAL = document.getElementById('loStatTotal');
  const STAT_MOY = document.getElementById('loStatMoy');
  const STAT_STARS = document.getElementById('loStatStars');

  const CIBLE_LABELS = {
    ems: "Établissement",
    personnel: "Personnel",
    prise_en_charge: "Prise en charge",
    vie: "Vie sociale",
    autre: "Autre"
  };

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function stars(n) {
    const f = Math.round(n);
    return '★'.repeat(f) + '☆'.repeat(5 - f);
  }

  function buildCard(t) {
    const title = t.titre ? `<h3 class="lo-card-title">${escapeHtml(t.titre)}</h3>` : '';
    const lien = t.lien_resident ? `<small>${escapeHtml(t.lien_resident)}</small>` : '';
    return `
      <article class="lo-card ${t.epingle == 1 ? 'lo-pinned' : ''}">
        <div class="lo-card-quote">"</div>
        <div class="lo-card-stars" aria-label="${t.note}/5">${stars(t.note)}</div>
        ${title}
        <p class="lo-card-text">${escapeHtml(t.message)}</p>
        <div class="lo-card-footer">
          <div class="lo-card-author">${escapeHtml(t.nom)}${lien}</div>
          <span class="lo-card-cible">${escapeHtml(CIBLE_LABELS[t.cible] || t.cible)}</span>
        </div>
      </article>
    `;
  }

  async function loadTemoignages() {
    try {
      const res = await fetch('/spocspace/website/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'livre_or_get_approved', limit: 200, order: 'random' })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Erreur');

      // Stats
      if (data.stats && data.stats.total > 0) {
        STATS.style.display = 'inline-flex';
        STAT_TOTAL.textContent = data.stats.total;
        STAT_MOY.textContent = data.stats.moyenne.toFixed(1);
        STAT_STARS.textContent = stars(data.stats.moyenne);
      }

      const list = data.temoignages || [];
      if (list.length === 0) {
        TRACK.innerHTML = '<div class="lo-empty" style="grid-column:1/-1"><i class="bi bi-journal"></i><div>Soyez le premier à laisser un témoignage.</div></div>';
        return;
      }

      TRACK.innerHTML = list.map(buildCard).join('');
    } catch (e) {
      TRACK.innerHTML = '<div class="lo-empty" style="grid-column:1/-1"><i class="bi bi-exclamation-triangle"></i> Impossible de charger les témoignages.</div>';
    }
  }

  // Form submission
  const FORM = document.getElementById('loForm');
  const BTN = document.getElementById('loSubmitBtn');
  const ALERT_OK = document.getElementById('loAlertSuccess');
  const ALERT_ERR = document.getElementById('loAlertError');

  function showAlert(el, msg) {
    [ALERT_OK, ALERT_ERR].forEach(a => a.classList.remove('show'));
    el.textContent = msg;
    el.classList.add('show');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  FORM.addEventListener('submit', async (e) => {
    e.preventDefault();
    BTN.disabled = true;
    BTN.innerHTML = '<i class="bi bi-arrow-repeat"></i> Envoi…';

    const fd = new FormData(FORM);
    const payload = {
      action: 'livre_or_submit',
      nom: fd.get('nom'),
      email: fd.get('email'),
      lien_resident: fd.get('lien_resident'),
      note: parseInt(fd.get('note'), 10),
      titre: fd.get('titre'),
      message: fd.get('message'),
      cible: fd.get('cible')
    };

    try {
      const res = await fetch('/spocspace/website/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showAlert(ALERT_OK, data.message || 'Merci pour votre témoignage !');
        FORM.reset();
        document.getElementById('lo-star5').checked = true;
      } else {
        showAlert(ALERT_ERR, data.message || 'Une erreur est survenue.');
      }
    } catch (err) {
      showAlert(ALERT_ERR, 'Erreur réseau. Veuillez réessayer.');
    } finally {
      BTN.disabled = false;
      BTN.innerHTML = '<i class="bi bi-send"></i> Publier mon témoignage';
    }
  });

  loadTemoignages();
})();
</script>
</body>
</html>
