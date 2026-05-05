<?php
/**
 * Répartition Admin — Spocspace Care
 *
 * Refonte fidèle à la maquette `files-repartition.zip` (Spocspace_Repartition.html).
 * Page autonome (données démo statiques) — à brancher ensuite sur les API
 * (admin_get_repartition, admin_save_repartition_cell, ...).
 *
 * Vendored : html2canvas + JSZip (assets/js/vendor/), pas de CDN externe.
 */
?>
<style<?= nonce() ?>>
/* ============================================================
   SPOCSPACE CARE — TOKENS
   ============================================================ */
.ss-rep-page{
  --bg:#f5f7f5;
  --surface:#ffffff;
  --surface-2:#fafbfa;
  --surface-3:#f3f6f5;

  --ink:#0d2a26;
  --ink-2:#324e4a;
  --ink-3:#4a6661;
  --muted:#6b8783;
  --muted-2:#8aa39f;

  --line:#e3ebe8;
  --line-2:#d4ddda;

  --teal-50:#ecf5f3;
  --teal-100:#d2e7e2;
  --teal-200:#a8d1c8;
  --teal-300:#7ab5ab;
  --teal-500:#2d8074;
  --teal-600:#1f6359;
  --teal-700:#164a42;
  --teal-900:#0d2a26;

  --ok:#3d8b6b;        --ok-bg:#e3f0ea;       --ok-line:#b8d4c5;
  --warn:#c97a2a;      --warn-bg:#fbf0e1;     --warn-line:#e8c897;
  --danger:#b8443a;    --danger-bg:#f7e3e0;   --danger-line:#e6b8b0;
  --info:#3a6a8a;      --info-bg:#e2ecf2;     --info-line:#b5cad8;

  /* SIDEBAR / sidebar widths */
  --sb-w:240px;
  --sb-w-collapsed:64px;

  /* Couleurs de modules — palette Care */
  --mod-rj:    #164a42;
  --mod-m1:    #1f6359;
  --mod-m2:    #2d4a6b;
  --mod-m3:    #8a5a1a;
  --mod-m4:    #5e3a78;
  --mod-pool:  #8a3a30;
  --mod-na:    #6b8783;

  /* HORAIRES — palette respect du thème, AUCUN vert
     (le vert est réservé aux états "validé/présent") */
  --sh-a-bg:#d2e7e2;       --sh-a-fg:#164a42;       /* A2/A3 — teal clair (matin/journée) */
  --sh-c-bg:#a8d1c8;       --sh-c-fg:#0d2a26;       /* C1/C2 — teal mid (journée) */
  --sh-d1-bg:#e2ecf2;      --sh-d1-fg:#3a6a8a;      /* D1 — info bleu (doublure) */
  --sh-d3-bg:#fbf0e1;      --sh-d3-fg:#8a5a1a;      /* D3 — ocre (longue journée) */
  --sh-d4-bg:#fde8e6;      --sh-d4-fg:#8a3a30;      /* D4 — terracotta (fin journée) */
  --sh-s-bg:#f0e8f5;       --sh-s-fg:#5e3a78;       /* S3/S4 — violet (soir) */
  --sh-n-bg:#0d2a26;       --sh-n-fg:#a8e6c9;       /* N1 — sombre/teal claire (nuit) */
  --sh-piquet-bg:#e6ecf2;  --sh-piquet-fg:#2d4a6b;  /* PIQUET — info foncé */

  --shadow-sm:0 1px 2px rgba(13,42,38,.04), 0 1px 1px rgba(13,42,38,.03);
  --shadow:0 4px 16px -4px rgba(13,42,38,.08), 0 2px 4px rgba(13,42,38,.04);
  --shadow-lg:0 16px 48px -12px rgba(13,42,38,.18), 0 4px 12px rgba(13,42,38,.06);

  --r-xs:4px; --r-sm:6px; --r:10px; --r-md:12px; --r-lg:16px; --r-pill:999px;

  --t-fast:.15s ease;
  --t:.2s ease;
}

.ss-rep-page,.ss-rep-page *{box-sizing:border-box}
.ss-rep-page{
  font-family:'Outfit',-apple-system,BlinkMacSystemFont,sans-serif;
  font-size:14px;color:var(--ink);background:var(--bg);
  -webkit-font-smoothing:antialiased;line-height:1.45;
  /* gomme l\'héritage du shell admin pour les éléments enfants */
  margin:0;
}
.ss-rep-page h1,.ss-rep-page h2,.ss-rep-page h3,.ss-rep-page h4{margin:0}
.ss-rep-page p,.ss-rep-page ul,.ss-rep-page ol{margin:0}
.ss-rep-page button{margin:0}
.serif{font-family:'Fraunces',Georgia,serif;letter-spacing:-.01em}
.mono{font-family:'JetBrains Mono',Menlo,monospace;font-variant-numeric:tabular-nums}

/* ============================================================
   LAYOUT
   ============================================================ */
.app{
  display:grid;
  grid-template-columns:var(--sb-w) 1fr;
  min-height:100vh;
  transition:grid-template-columns var(--t);
}
.app.sb-collapsed{grid-template-columns:var(--sb-w-collapsed) 1fr}

/* ============================================================
   SIDEBAR
   ============================================================ */
.sidebar{
  background:linear-gradient(180deg,#0d2a26 0%,#164a42 100%);
  color:#cfe0db;padding:18px 14px;
  position:sticky;top:0;height:100vh;
  display:flex;flex-direction:column;gap:24px;
  overflow-y:auto;overflow-x:hidden;
  transition:padding var(--t);
}
.app.sb-collapsed .sidebar{padding:18px 8px}

.sb-header{
  display:flex;align-items:center;gap:10px;
  padding:0 4px;position:relative;
}
.brand{display:flex;align-items:center;gap:10px;flex:1;min-width:0;overflow:hidden}
.brand-mark{
  width:34px;height:34px;border-radius:9px;
  background:linear-gradient(135deg,#3da896,#7dd3a8);
  display:grid;place-items:center;
  font-family:'Fraunces',serif;font-weight:700;color:#0d2a26;font-size:18px;
  box-shadow:0 4px 12px rgba(125,211,168,.3);flex-shrink:0;
}
.brand-text{display:flex;flex-direction:column;min-width:0}
.brand-name{font-family:'Fraunces',serif;font-size:18px;font-weight:600;color:#fff;letter-spacing:-.02em;line-height:1.1;white-space:nowrap}
.brand-sub{font-size:9.5px;color:#7ea69c;letter-spacing:.12em;text-transform:uppercase;margin-top:1px;white-space:nowrap}
.app.sb-collapsed .brand-text{display:none}

.sb-toggle{
  width:26px;height:26px;border-radius:var(--r-sm);
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);
  color:#cfe0db;cursor:pointer;
  display:grid;place-items:center;flex-shrink:0;
  transition:all var(--t-fast);
}
.sb-toggle:hover{background:rgba(255,255,255,.12);color:#fff}
.app.sb-collapsed .sb-toggle svg{transform:rotate(180deg)}

.nav-section-title{
  font-size:10px;letter-spacing:.14em;text-transform:uppercase;
  color:#5d8077;padding:0 10px;margin-bottom:6px;
  white-space:nowrap;overflow:hidden;
}
.app.sb-collapsed .nav-section-title{
  text-align:center;padding:0;font-size:0;
  height:1px;background:rgba(255,255,255,.06);margin:6px 8px 8px;
}

.nav{display:flex;flex-direction:column;gap:2px}
.nav a{
  display:flex;align-items:center;gap:11px;padding:9px 10px;border-radius:var(--r-sm);
  color:#a8c4be;text-decoration:none;font-size:13px;transition:all var(--t-fast);
  white-space:nowrap;overflow:hidden;
}
.nav a:hover{background:rgba(255,255,255,.04);color:#e8f1ee}
.nav a.active{background:rgba(125,211,168,.12);color:#fff;font-weight:500;position:relative}
.nav a.active::before{content:"";position:absolute;left:-2px;width:3px;height:14px;background:#7dd3a8;border-radius:3px}
.app.sb-collapsed .nav a{justify-content:center;padding:10px 0}
.app.sb-collapsed .nav a span.lbl{display:none}
.app.sb-collapsed .nav a{position:relative}
.app.sb-collapsed .nav a:hover::after{
  content:attr(data-tip);
  position:absolute;left:calc(100% + 8px);top:50%;transform:translateY(-50%);
  background:var(--teal-900);color:#fff;
  padding:5px 10px;border-radius:var(--r-sm);font-size:12px;font-weight:500;
  white-space:nowrap;z-index:50;box-shadow:var(--shadow);
  pointer-events:none;
}
.ico{width:16px;height:16px;flex-shrink:0}

.ems-card{
  margin-top:auto;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  border-radius:var(--r);padding:12px 14px;
}
.app.sb-collapsed .ems-card{padding:10px 6px;text-align:center}
.ems-card .label{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;color:#7ea69c;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ems-card .name{color:#fff;font-weight:500;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ems-card .sub{color:#a8c4be;font-size:11px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.app.sb-collapsed .ems-card .name,
.app.sb-collapsed .ems-card .sub,
.app.sb-collapsed .ems-card .label{font-size:0;height:0;margin:0;overflow:hidden}
.app.sb-collapsed .ems-card::before{content:"⌂";font-size:18px;color:#7dd3a8}

/* ============================================================
   MAIN
   ============================================================ */
.main{display:flex;flex-direction:column;min-width:0}

/* ============================================================
   TOPBAR
   ============================================================ */
.topbar{
  display:flex;align-items:center;gap:14px;padding:11px 22px;
  background:var(--surface);border-bottom:1px solid var(--line);
  position:sticky;top:0;z-index:30;
}
.topbar-title{display:flex;align-items:baseline;gap:12px;flex-shrink:0;min-width:0}
.topbar-title h1{font-family:'Fraunces',serif;font-size:19px;font-weight:600;color:var(--teal-900);letter-spacing:-.01em;white-space:nowrap}
.topbar-title .sub{font-size:12px;color:var(--muted);white-space:nowrap}
.search{
  flex:1;max-width:340px;display:flex;align-items:center;gap:8px;
  background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r);
  padding:7px 12px;
}
.search input{flex:1;border:0;outline:0;background:transparent;font:inherit;font-size:13px;color:var(--ink);min-width:0}
.search input::placeholder{color:var(--muted-2)}
.kbd{font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--muted);background:var(--surface);border:1px solid var(--line);border-radius:var(--r-xs);padding:2px 6px}

.topbar-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.icon-btn{
  width:32px;height:32px;border-radius:var(--r-sm);
  border:1px solid var(--line);background:var(--surface);color:var(--ink-2);
  display:grid;place-items:center;cursor:pointer;transition:all var(--t-fast);
  position:relative;
}
.icon-btn:hover{background:var(--surface-2);border-color:var(--line-2)}
.icon-btn .badge{
  position:absolute;top:-3px;right:-3px;
  background:var(--danger);color:#fff;border:2px solid var(--surface);
  font-size:9px;font-weight:700;border-radius:99px;padding:1px 4px;min-width:16px;height:16px;
  display:grid;place-items:center;line-height:1;
}
.user-chip{display:flex;align-items:center;gap:9px;padding:4px 12px 4px 4px;border-radius:var(--r-pill);background:var(--surface-2);border:1px solid var(--line)}
.avatar{width:26px;height:26px;border-radius:50%;color:#fff;font-weight:600;font-size:11px;display:grid;place-items:center;flex-shrink:0;background:var(--teal-600)}

/* ============================================================
   SUBHEADER — navigation semaine + mode
   ============================================================ */
.subheader{
  display:flex;align-items:center;gap:12px;
  padding:12px 22px;background:var(--surface);
  border-bottom:1px solid var(--line);flex-wrap:wrap;
  position:sticky;top:55px;z-index:25;
}
.crumbs-l{display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted)}
.crumbs-l strong{color:var(--ink);font-weight:500}
.crumbs-l .sep{color:var(--line-2)}
.tag-final{background:var(--ok-bg);color:var(--ok);border:1px solid var(--ok-line);font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:var(--r-pill);letter-spacing:.04em;text-transform:lowercase}

.week-nav{display:inline-flex;align-items:center;gap:0;background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r);padding:3px}
.week-nav button{width:28px;height:28px;border:0;background:transparent;color:var(--ink-2);cursor:pointer;border-radius:var(--r-sm);display:grid;place-items:center;transition:all var(--t-fast)}
.week-nav button:hover{background:var(--surface)}
.week-label{display:inline-flex;align-items:center;gap:6px;padding:0 12px;font-size:12.5px;font-weight:600;color:var(--ink);font-family:'Fraunces',serif;letter-spacing:-.01em}
.week-label .num{color:var(--teal-600)}

.btn-today{padding:6px 11px;font:inherit;font-size:12px;font-weight:500;background:var(--teal-50);color:var(--teal-700);border:1px solid var(--teal-200);border-radius:var(--r-sm);cursor:pointer;transition:all var(--t-fast)}
.btn-today:hover{background:var(--teal-100)}

/* Toggle Vue Semaine / Jour */
.view-toggle{
  display:inline-flex;background:var(--surface-2);border:1px solid var(--line);
  border-radius:var(--r);padding:3px;
}
.view-toggle button{
  padding:6px 12px;border:0;background:transparent;
  font:inherit;font-size:12px;font-weight:500;color:var(--muted);
  cursor:pointer;border-radius:var(--r-sm);transition:all var(--t-fast);
  display:inline-flex;align-items:center;gap:5px;
}
.view-toggle button:hover{color:var(--ink-2)}
.view-toggle button.on{background:var(--surface);color:var(--teal-700);box-shadow:var(--shadow-sm);font-weight:600}

.subheader-spacer{flex:1}

.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:var(--r-sm);font:inherit;font-size:12.5px;font-weight:500;cursor:pointer;border:1px solid var(--line);background:var(--surface);color:var(--ink-2);transition:all var(--t-fast);white-space:nowrap}
.btn:hover{background:var(--surface-2);border-color:var(--line-2)}
.btn-primary{background:var(--teal-600);color:#fff;border-color:var(--teal-600)}
.btn-primary:hover{background:var(--teal-700);border-color:var(--teal-700)}
.btn-warn{background:var(--warn);color:#fff;border-color:var(--warn)}
.btn-warn:hover{background:#a86220;border-color:#a86220}
.btn-ghost{background:transparent;border-color:transparent;color:var(--ink-2)}
.btn-ghost:hover{background:var(--surface-2)}

.date-picker{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface);font-size:12.5px;color:var(--ink);font-family:'JetBrains Mono',monospace;font-weight:500;cursor:pointer}
.date-picker svg{color:var(--muted)}

/* Edit-mode banner */
.edit-banner{
  display:none;align-items:center;gap:10px;
  padding:9px 22px;background:var(--warn-bg);border-bottom:1px solid var(--warn-line);
  color:var(--warn);font-size:12.5px;font-weight:500;
  position:sticky;top:115px;z-index:24;
}
.app.editing .edit-banner{display:flex}
.edit-banner .ic{font-size:14px}
.edit-banner .spacer{flex:1}
.edit-banner button{
  padding:5px 10px;font:inherit;font-size:11.5px;font-weight:600;
  border:1px solid var(--warn);background:var(--warn);color:#fff;
  border-radius:var(--r-sm);cursor:pointer;
}

/* ============================================================
   CONTENT
   ============================================================ */
.content{padding:18px 22px 60px;display:flex;flex-direction:column;gap:18px}

/* MODULE FILTER (chips horizontaux) */
.mod-filter{
  background:var(--surface);border:1px solid var(--line);border-radius:var(--r);
  padding:8px 10px;display:flex;align-items:center;gap:6px;
  overflow-x:auto;box-shadow:var(--shadow-sm);
  scrollbar-width:thin;
}
.mod-filter::-webkit-scrollbar{height:5px}
.mod-filter::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:99px}
.mod-filter .lbl{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:700;padding:0 6px;flex-shrink:0}
.mf-chip{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 12px;background:var(--surface-2);border:1px solid var(--line);
  border-radius:var(--r-pill);
  font:inherit;font-size:12px;font-weight:500;color:var(--ink-2);
  cursor:pointer;transition:all var(--t-fast);white-space:nowrap;flex-shrink:0;
}
.mf-chip:hover{background:var(--surface);border-color:var(--line-2)}
.mf-chip .swatch{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.mf-chip .count{
  font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--muted);
  background:var(--surface);padding:1px 6px;border-radius:99px;border:1px solid var(--line);
}
.mf-chip.on{background:var(--teal-700);color:#fff;border-color:var(--teal-700)}
.mf-chip.on .count{background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.2)}

/* STATS BAR */
.stats-bar{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
.stat-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:11px 14px;box-shadow:var(--shadow-sm)}
.stat-card .lbl{font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:600;margin-bottom:4px}
.stat-card .v{font-family:'Fraunces',serif;font-size:19px;font-weight:600;color:var(--teal-900);line-height:1}
.stat-card .v small{font-size:11px;color:var(--muted);font-family:'Outfit',sans-serif;font-weight:400;margin-left:3px}
.stat-card .sub{font-size:10.5px;color:var(--muted);margin-top:3px}
.stat-card.ok .v{color:var(--ok)}
.stat-card.warn .v{color:var(--warn)}
.stat-card.danger .v{color:var(--danger)}
.stat-card.info .v{color:var(--info)}

/* ============================================================
   MODULE BLOCK
   ============================================================ */
.module{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;box-shadow:var(--shadow-sm)}
.module.hidden{display:none}
.module-head{
  display:flex;align-items:center;gap:12px;
  padding:11px 16px;color:#fff;
  background:var(--mod-color,var(--teal-600));
  position:relative;
}
.module-head::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.06) 0%,transparent 50%);pointer-events:none}
.module-head .ico{width:22px;height:22px;border-radius:var(--r-xs);background:rgba(255,255,255,.18);display:grid;place-items:center;flex-shrink:0;position:relative;z-index:1}
.module-head h2{font-family:'Fraunces',serif;font-size:16px;font-weight:600;letter-spacing:-.01em;flex:1;position:relative;z-index:1}
.module-head .count{font-size:11px;font-weight:600;font-family:'JetBrains Mono',monospace;background:rgba(255,255,255,.18);padding:3px 10px;border-radius:var(--r-pill);letter-spacing:.02em;position:relative;z-index:1}
.module-head .actions{display:flex;gap:6px;position:relative;z-index:1}
.module-head .actions button{width:26px;height:26px;border-radius:var(--r-xs);border:0;background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:grid;place-items:center;transition:all var(--t-fast)}
.module-head .actions button:hover{background:rgba(255,255,255,.22)}

.module.m-rj  .module-head{background:linear-gradient(135deg,var(--teal-700),var(--teal-600))}
.module.m-m1  .module-head{background:linear-gradient(135deg,#1f6359,#2d8074)}
.module.m-m2  .module-head{background:linear-gradient(135deg,#2d4a6b,#456b8e)}
.module.m-m3  .module-head{background:linear-gradient(135deg,#8a5a1a,#b07a35)}
.module.m-m4  .module-head{background:linear-gradient(135deg,#5e3a78,#7d5896)}
.module.m-pool .module-head{background:linear-gradient(135deg,#8a3a30,#a85850)}
.module.m-na  .module-head{background:linear-gradient(135deg,#4a6661,#6b8783)}

/* TABLE WRAP — scroll horizontal sur petits écrans */
.module-body{overflow-x:auto;scrollbar-width:thin}
.module-body::-webkit-scrollbar{height:8px}
.module-body::-webkit-scrollbar-track{background:var(--surface-2)}
.module-body::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:99px}
.module-body::-webkit-scrollbar-thumb:hover{background:var(--muted-2)}

table.repart{
  width:100%;border-collapse:separate;border-spacing:0;
  font-size:12.5px;table-layout:fixed;min-width:1820px;
}
table.repart th,table.repart td{
  border-right:1px solid var(--line);
  border-bottom:1px solid var(--line);
  vertical-align:middle;
}
table.repart tbody tr:last-child td{border-bottom:0}

/* HEAD - 2 lignes */
table.repart thead tr.day-row th{
  background:var(--surface-2);font-weight:600;color:var(--ink-2);
  font-size:12.5px;text-align:center;padding:9px 8px 7px;
  border-bottom:1px solid var(--line);
}
table.repart thead tr.day-row th .day-name{display:block;font-size:10px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;font-weight:600;margin-bottom:2px}
table.repart thead tr.day-row th .day-date{display:inline-flex;align-items:baseline;gap:4px;font-family:'Fraunces',serif;font-weight:600;font-size:14px;color:var(--teal-900);letter-spacing:-.005em}
table.repart thead tr.day-row th.today{background:var(--teal-50);box-shadow:inset 0 -2px 0 var(--teal-500)}
table.repart thead tr.day-row th.today .day-date{color:var(--teal-700)}
table.repart thead tr.day-row th.today .day-name{color:var(--teal-600)}
table.repart thead tr.day-row th.weekend{background:#f6f3ee}
table.repart thead tr.day-row th.weekend .day-name{color:#a87d3a}

table.repart thead tr.subhead-row th{
  background:var(--surface-3);font-size:9.5px;letter-spacing:.1em;text-transform:uppercase;
  color:var(--muted);font-weight:600;padding:6px 4px;text-align:center;
  border-bottom:1px solid var(--line);
}
table.repart thead tr.subhead-row th.today{background:var(--teal-50)}
table.repart thead tr.subhead-row th.weekend{background:#f6f3ee}

table.repart tbody td.weekend{background:#fdfcfa}
table.repart tbody td.weekend .cell-etage{background:#f0ebe1}

/* sub-headers : Horaire | Étage SÉPARÉS */
.sub-double{display:flex;align-items:stretch;height:100%}
.sub-double > span{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:6px 4px;
}
.sub-double > span:first-child{border-right:1px solid var(--line)}

/* COLONNES STICKY */
.col-fonction{
  position:sticky;left:0;z-index:5;
  background:var(--surface);
  background-clip:padding-box;
  width:90px;min-width:90px;max-width:90px;text-align:center;
  font-weight:600;color:var(--ink);font-size:11.5px;
  border-right:1px solid var(--line-2)!important;
  box-shadow:inset -1px 0 0 var(--line-2);
}
table.repart thead .col-fonction{z-index:7;background:var(--surface-2)}
table.repart .col-fonction .label{display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 4px}
table.repart .col-fonction .label small{font-size:9.5px;color:var(--muted);font-weight:500;letter-spacing:.04em}

.col-poste{
  position:sticky;left:90px;z-index:5;
  background:var(--surface);
  background-clip:padding-box;
  width:48px;min-width:48px;max-width:48px;text-align:center;
  font-family:'JetBrains Mono',monospace;font-size:11.5px;font-weight:600;color:var(--muted);
  border-right:1px solid var(--line-2)!important;
  box-shadow:2px 0 4px -2px rgba(13,42,38,.08);
}
table.repart thead .col-poste{z-index:7;background:var(--surface-2)}

.col-day{width:auto;min-width:240px}

/* ============================================================
   CELLULES
   ============================================================ */
table.repart tbody td{
  padding:0;height:40px;background:var(--surface);
  transition:background var(--t-fast);
}
table.repart tbody tr:hover .col-fonction,
table.repart tbody tr:hover .col-poste{background:var(--surface)}
table.repart tbody tr:hover td:not(.col-fonction):not(.col-poste){background:var(--surface-2)}

/* Cellule avec 2 sous-zones : nom+horaire | étage */
.cell{
  display:flex;align-items:stretch;height:100%;
  cursor:pointer;transition:all var(--t-fast);position:relative;
}
.cell-main{
  flex:1;display:flex;align-items:center;gap:6px;
  padding:4px 8px 4px 10px;min-width:0;
  border-right:1px solid var(--line);
}
.cell-main .name{
  flex:1;min-width:0;
  font-size:12.5px;color:var(--ink);font-weight:500;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.cell-main .horaire{flex-shrink:0}
.cell-etage{
  width:64px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--muted);
  white-space:nowrap;text-align:center;background:var(--surface-3);
}
.cell:hover .cell-main,.cell:hover .cell-etage{background:var(--teal-50)}

.cell.faded .cell-main .name{color:var(--muted-2);font-style:italic}

.cell.empty{cursor:pointer}
.cell.empty .cell-main{
  background:repeating-linear-gradient(45deg,transparent,transparent 6px,rgba(106,131,131,.04) 6px,rgba(106,131,131,.04) 7px);
}
.cell.empty:hover .cell-main{background:var(--teal-50);outline:1px dashed var(--teal-300);outline-offset:-1px}

/* État ABSENT : cellule rouge dans le ton du thème */
.cell.absent .cell-main{
  background:var(--danger-bg);
  border-right:1px solid var(--danger-line);
}
.cell.absent .cell-etage{
  background:#f0d4cf;color:var(--danger);font-weight:600;
  display:flex;align-items:center;justify-content:center;gap:4px;
}
.cell.absent .name{color:var(--danger);font-weight:600;text-decoration:line-through;text-decoration-color:rgba(184,68,58,.4);text-decoration-thickness:1px}
.cell.absent .absent-ico{
  width:14px;height:14px;border-radius:50%;background:var(--danger);color:#fff;
  display:grid;place-items:center;flex-shrink:0;font-size:9px;font-weight:700;
}
.cell.absent .horaire{opacity:.55;filter:saturate(.6)}

/* Drag & drop — actif uniquement en mode édition */
.app.editing .cell:not(.empty){cursor:grab}
.app.editing .cell:not(.empty):active{cursor:grabbing}
.cell.dragging{
  background:var(--teal-50);
  outline:2px dashed var(--teal-500);outline-offset:-2px;
  border-radius:var(--r-sm);opacity:.85;
}
.cell.drop-target .cell-main{
  background:var(--teal-100);
  outline:2px dashed var(--teal-600);outline-offset:-2px;
}

/* ============================================================
   HORAIRE BADGE — palette respect du thème, AUCUN vert
   ============================================================ */
.shift{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:30px;height:22px;padding:0 8px;
  font-family:'JetBrains Mono',monospace;font-size:10.5px;font-weight:700;
  border-radius:5px;letter-spacing:.02em;
  border:1px solid transparent;white-space:nowrap;
}
.shift.a2,.shift.a3{background:var(--sh-a-bg);color:var(--sh-a-fg);border-color:rgba(22,74,66,.12)}
.shift.c1,.shift.c2{background:var(--sh-c-bg);color:var(--sh-c-fg);border-color:rgba(13,42,38,.18)}
.shift.d1{background:var(--sh-d1-bg);color:var(--sh-d1-fg);border-color:rgba(58,106,138,.18)}
.shift.d3{background:var(--sh-d3-bg);color:var(--sh-d3-fg);border-color:rgba(138,90,26,.18)}
.shift.d4{background:var(--sh-d4-bg);color:var(--sh-d4-fg);border-color:rgba(138,58,48,.18)}
.shift.s3,.shift.s4{background:var(--sh-s-bg);color:var(--sh-s-fg);border-color:rgba(94,58,120,.16)}
.shift.n1{background:var(--sh-n-bg);color:var(--sh-n-fg);border-color:rgba(13,42,38,.4)}
.shift.piquet{background:var(--sh-piquet-bg);color:var(--sh-piquet-fg);border-color:rgba(45,74,107,.18)}

/* ============================================================
   LEGEND
   ============================================================ */
.legend-panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);padding:14px 18px;box-shadow:var(--shadow-sm)}
.legend-row{display:flex;flex-wrap:wrap;align-items:center;gap:14px}
.legend-label{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:700;margin-right:6px}
.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:var(--ink-2)}
.legend-item .shift{min-width:30px}

/* ============================================================
   VUE JOUR — détails étendus
   ============================================================ */
.day-view{display:none;flex-direction:column;gap:18px}
.app.view-day .day-view{display:flex}
.app.view-day .week-view{display:none}

.day-header{
  background:linear-gradient(135deg,var(--teal-700) 0%,var(--teal-600) 100%);
  border-radius:var(--r-md);padding:18px 22px;color:#fff;
  display:flex;align-items:center;gap:18px;flex-wrap:wrap;
  position:relative;overflow:hidden;
}
.day-header::after{content:"";position:absolute;right:-80px;top:-80px;width:280px;height:280px;background:repeating-radial-gradient(circle at center,rgba(255,255,255,.025) 0,rgba(255,255,255,.025) 1px,transparent 1px,transparent 14px);pointer-events:none}
.day-header .day-big{font-family:'Fraunces',serif;font-size:34px;font-weight:600;line-height:1;letter-spacing:-.02em;position:relative;z-index:1}
.day-header .day-info{display:flex;flex-direction:column;gap:3px;position:relative;z-index:1}
.day-header .day-info .name{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600}
.day-header .day-info .full{font-size:18px;font-weight:500}
.day-header .day-meta{display:flex;gap:24px;margin-left:auto;position:relative;z-index:1}
.day-header .day-meta .m{display:flex;flex-direction:column;gap:1px}
.day-header .day-meta .k{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#a8c4be;font-weight:600}
.day-header .day-meta .v{font-family:'Fraunces',serif;font-size:20px;font-weight:600;color:#fff;line-height:1.1}

/* Vue jour : tableau plus détaillé */
table.repart-day{
  width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px;
}
table.repart-day th,table.repart-day td{border-right:1px solid var(--line);border-bottom:1px solid var(--line);vertical-align:middle;padding:0}
table.repart-day tbody tr:last-child td{border-bottom:0}
table.repart-day thead th{
  background:var(--surface-2);font-weight:600;color:var(--ink-2);font-size:10.5px;
  letter-spacing:.1em;text-transform:uppercase;color:var(--muted);
  text-align:left;padding:10px 12px;
}
table.repart-day thead th.center{text-align:center}

table.repart-day .col-day-fonc{width:120px;background:var(--surface);position:sticky;left:0;z-index:3}
table.repart-day .col-day-poste{width:60px;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--muted);font-size:11.5px;font-weight:600}
table.repart-day .col-day-name{width:auto;min-width:180px}
table.repart-day .col-day-horaire{width:90px;text-align:center}
table.repart-day .col-day-time{width:140px;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--ink-2);font-size:11.5px}
table.repart-day .col-day-etage{width:80px;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--ink-2);font-size:11.5px}
table.repart-day .col-day-pause{width:80px;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--muted);font-size:11.5px}
table.repart-day .col-day-status{width:130px;padding:8px 10px;text-align:center}
table.repart-day .col-day-actions{width:90px;text-align:center}

table.repart-day tbody tr:hover td{background:var(--surface-2)}

table.repart-day td.col-day-fonc{
  font-weight:600;color:var(--ink);font-size:11.5px;padding:10px 12px;
  border-right:1px solid var(--line-2)!important;
}
table.repart-day td.col-day-poste,
table.repart-day td.col-day-horaire,
table.repart-day td.col-day-time,
table.repart-day td.col-day-etage,
table.repart-day td.col-day-pause{padding:10px 8px}

.day-collab{display:flex;align-items:center;gap:10px;padding:8px 12px}
.day-collab .av{width:30px;height:30px;border-radius:50%;color:#fff;font-weight:600;font-size:11px;display:grid;place-items:center;flex-shrink:0;background:var(--teal-600)}
.day-collab .info{display:flex;flex-direction:column;min-width:0}
.day-collab .info .name{font-size:13px;font-weight:500;color:var(--ink);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.day-collab .info .role{font-size:11px;color:var(--muted);margin-top:1px}

.day-status{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:var(--r-pill);font-size:11px;font-weight:600;border:1px solid}
.day-status.ok{background:var(--ok-bg);color:var(--ok);border-color:var(--ok-line)}
.day-status.warn{background:var(--warn-bg);color:var(--warn);border-color:var(--warn-line)}
.day-status.absent{background:var(--danger-bg);color:var(--danger);border-color:var(--danger-line)}
.day-status .b{width:5px;height:5px;border-radius:50%;background:currentColor}

.day-row-actions{display:flex;justify-content:center;gap:4px}
.day-row-actions button{
  width:26px;height:26px;border-radius:var(--r-xs);
  border:1px solid var(--line);background:var(--surface);color:var(--muted);
  display:grid;place-items:center;cursor:pointer;transition:all var(--t-fast);
}
.day-row-actions button:hover{background:var(--teal-50);border-color:var(--teal-200);color:var(--teal-700)}

/* ============================================================
   MODAL — refonte complète
   ============================================================ */
.modal-overlay{
  position:fixed;inset:0;
  background:rgba(13,42,38,.55);
  display:none;align-items:center;justify-content:center;
  z-index:100;backdrop-filter:blur(4px);
  padding:20px;
  animation:fadeIn .2s ease;
}
.modal-overlay.show{display:flex}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}

.modal{
  background:var(--surface);border-radius:var(--r-lg);
  box-shadow:var(--shadow-lg);
  width:520px;max-width:100%;max-height:calc(100vh - 40px);
  overflow:hidden;display:flex;flex-direction:column;
  animation:slideUp .25s ease;
}

/* === HERO du modal === */
.modal-hero{
  background:linear-gradient(135deg,var(--teal-700) 0%,var(--teal-600) 50%,var(--teal-500) 100%);
  padding:18px 22px;color:#fff;position:relative;overflow:hidden;
  flex-shrink:0;
}
.modal-hero::before{
  content:"";position:absolute;inset:0;
  background:radial-gradient(circle at 100% 0%,rgba(125,211,168,.18) 0%,transparent 55%);
  pointer-events:none;
}
.modal-hero::after{
  content:"";position:absolute;right:-60px;top:-60px;width:200px;height:200px;
  background:repeating-radial-gradient(circle at center,rgba(255,255,255,.025) 0,rgba(255,255,255,.025) 1px,transparent 1px,transparent 12px);
  pointer-events:none;
}
.modal-hero-top{
  display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
  position:relative;z-index:1;
}
.modal-hero-id{display:flex;gap:12px;align-items:center;min-width:0;flex:1}
.modal-avatar{
  width:46px;height:46px;border-radius:12px;
  background:linear-gradient(135deg,#3da896,#7dd3a8);
  display:grid;place-items:center;
  font-family:'Fraunces',serif;font-weight:600;color:#0d2a26;font-size:17px;
  box-shadow:0 6px 18px rgba(0,0,0,.2);flex-shrink:0;
}
.modal-id-text{min-width:0;flex:1}
.modal-id-text .label{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:2px}
.modal-id-text h3{font-family:'Fraunces',serif;font-size:19px;font-weight:600;letter-spacing:-.01em;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.modal-id-text .role{font-size:11.5px;color:#cfe0db;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.modal-close{
  width:30px;height:30px;border-radius:var(--r-sm);
  border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;
  cursor:pointer;display:grid;place-items:center;flex-shrink:0;
  transition:all var(--t-fast);
}
.modal-close:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.3)}

.modal-hero-meta{
  display:flex;gap:8px;margin-top:14px;
  position:relative;z-index:1;
  padding-top:12px;border-top:1px solid rgba(255,255,255,.14);
}
.modal-hero-meta .m{
  display:flex;align-items:center;gap:8px;
  flex:1;min-width:0;
  padding:6px 10px;border-radius:8px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);
}
.modal-hero-meta .m .ic{
  width:24px;height:24px;border-radius:6px;
  background:rgba(255,255,255,.14);
  display:grid;place-items:center;flex-shrink:0;
}
.modal-hero-meta .m .ic svg{width:12px;height:12px}
.modal-hero-meta .m .txt{display:flex;flex-direction:column;gap:0;min-width:0;flex:1}
.modal-hero-meta .m .k{font-size:8.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;line-height:1.2}
.modal-hero-meta .m .v{font-size:11.5px;color:#fff;font-weight:600;font-family:'JetBrains Mono',monospace;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* === BODY scrollable === */
.modal-body{
  padding:18px 22px;display:flex;flex-direction:column;gap:14px;
  overflow-y:auto;flex:1;min-height:0;
}

/* Section title dans le modal */
.modal-section-title{
  display:flex;align-items:center;gap:8px;
  font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--muted);font-weight:700;margin-bottom:-4px;
}
.modal-section-title::before{
  content:"";width:14px;height:1px;background:var(--line-2);
}
.modal-section-title::after{
  content:"";flex:1;height:1px;background:var(--line);
}

/* Champs */
.field{display:flex;flex-direction:column;gap:6px}
.field label{
  font-size:11px;letter-spacing:.06em;text-transform:uppercase;
  color:var(--muted);font-weight:600;
  display:flex;align-items:center;gap:6px;
}
.field label .req{color:var(--danger);font-weight:700}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

.input,.select{
  width:100%;padding:10px 12px;border:1px solid var(--line-2);
  border-radius:var(--r-sm);font:inherit;font-size:13px;color:var(--ink);
  background:var(--surface);transition:all var(--t-fast);
}
.select{
  background:var(--surface) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b8783' stroke-width='2'><path d='m6 9 6 6 6-6'/></svg>") right 12px center/12px no-repeat;
  appearance:none;-webkit-appearance:none;cursor:pointer;
  padding-right:34px;
}
.input:focus,.select:focus{outline:0;border-color:var(--teal-500);box-shadow:0 0 0 3px rgba(45,128,116,.15)}

/* === Sélecteur d'horaire visuel === */
.shift-grid{
  display:grid;grid-template-columns:repeat(4,1fr);gap:6px;
}
.shift-opt{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:9px 6px;border-radius:var(--r-sm);
  border:1.5px solid var(--line-2);background:var(--surface);
  cursor:pointer;transition:all var(--t-fast);
  font:inherit;
}
.shift-opt:hover{background:var(--surface-2);border-color:var(--line-2)}
.shift-opt.on{
  border-color:var(--teal-500);background:var(--teal-50);
  box-shadow:0 0 0 3px rgba(45,128,116,.12);
}
.shift-opt .shift{margin-bottom:2px;pointer-events:none}
.shift-opt .time{
  font-family:'JetBrains Mono',monospace;font-size:9.5px;
  color:var(--muted);font-weight:500;letter-spacing:.02em;
}

/* === Toggle Présent / Absent === */
.status-pick{
  display:grid;grid-template-columns:1fr 1fr;gap:8px;
}
.status-btn{
  display:flex;align-items:center;gap:10px;
  padding:12px 14px;border-radius:var(--r-sm);
  border:1.5px solid var(--line-2);background:var(--surface);
  cursor:pointer;transition:all var(--t-fast);
  font:inherit;text-align:left;
}
.status-btn:hover{background:var(--surface-2)}
.status-btn .ic{
  width:30px;height:30px;border-radius:8px;
  display:grid;place-items:center;flex-shrink:0;
  font-weight:700;font-size:14px;color:#fff;
}
.status-btn .info{display:flex;flex-direction:column;min-width:0}
.status-btn .t{font-size:12.5px;font-weight:600;color:var(--ink);line-height:1.2}
.status-btn .d{font-size:10.5px;color:var(--muted);margin-top:1px}

.status-btn.present .ic{background:var(--ok)}
.status-btn.present.on{border-color:var(--ok);background:var(--ok-bg);box-shadow:0 0 0 3px rgba(61,139,107,.12)}
.status-btn.present.on .t{color:var(--ok)}

.status-btn.absent .ic{background:var(--danger)}
.status-btn.absent.on{border-color:var(--danger);background:var(--danger-bg);box-shadow:0 0 0 3px rgba(184,68,58,.12)}
.status-btn.absent.on .t{color:var(--danger)}

/* Type d'absence (si absent) */
.absent-reasons{
  display:none;flex-direction:column;gap:8px;
  padding:12px 14px;background:var(--danger-bg);
  border:1px solid var(--danger-line);border-radius:var(--r-sm);
}
.modal.is-absent .absent-reasons{display:flex}
.absent-reasons .lbl{
  font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;
  color:var(--danger);font-weight:700;
}
.absent-reasons .reasons{display:flex;flex-wrap:wrap;gap:6px}
.reason-chip{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 11px;border-radius:99px;
  background:#fff;border:1px solid var(--danger-line);
  font:inherit;font-size:11.5px;font-weight:500;color:var(--danger);
  cursor:pointer;transition:all var(--t-fast);
}
.reason-chip:hover{background:#fef0ed}
.reason-chip.on{background:var(--danger);color:#fff;border-color:var(--danger)}

/* === FOOT === */
.modal-foot{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  padding:14px 20px;border-top:1px solid var(--line);background:var(--surface-2);
  flex-shrink:0;
}
.modal-foot-left{display:flex;gap:6px}
.modal-foot-right{display:flex;gap:8px}
.modal-foot .btn-icon{
  width:34px;height:34px;border-radius:var(--r-sm);
  border:1px solid var(--line);background:var(--surface);color:var(--ink-2);
  display:grid;place-items:center;cursor:pointer;transition:all var(--t-fast);
}
.modal-foot .btn-icon:hover{background:var(--teal-50);border-color:var(--teal-200);color:var(--teal-700)}
.modal-foot .btn-icon.danger:hover{background:var(--danger-bg);border-color:var(--danger-line);color:var(--danger)}

/* ============================================================
   EXPORT — dropdown + modal de sélection + overlay de progression
   ============================================================ */

/* Dropdown au clic sur "Exporter" */
.export-wrap{position:relative}
.export-menu{
  display:none;position:absolute;top:calc(100% + 6px);right:0;
  background:var(--surface);border:1px solid var(--line);
  border-radius:var(--r);box-shadow:var(--shadow-lg);
  min-width:300px;padding:6px;z-index:50;
  animation:slideUp .15s ease;
}
.export-menu.show{display:block}
.export-menu .group-title{
  font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--muted);font-weight:700;padding:8px 12px 4px;
}
.export-menu button{
  width:100%;display:flex;align-items:center;gap:11px;
  padding:9px 12px;border:0;background:transparent;
  font:inherit;font-size:13px;color:var(--ink-2);text-align:left;
  cursor:pointer;border-radius:var(--r-sm);transition:all var(--t-fast);
}
.export-menu button:hover{background:var(--teal-50);color:var(--teal-700)}
.export-menu button .ic{
  width:30px;height:30px;border-radius:var(--r-sm);
  background:var(--teal-50);color:var(--teal-700);
  display:grid;place-items:center;flex-shrink:0;
}
.export-menu button:hover .ic{background:var(--teal-100)}
.export-menu button .info{display:flex;flex-direction:column;flex:1;min-width:0}
.export-menu button .t{font-weight:500}
.export-menu button .d{font-size:11px;color:var(--muted);margin-top:1px}
.export-menu button:hover .d{color:var(--teal-600)}
.export-menu .sep{height:1px;background:var(--line);margin:4px 8px}

/* Overlay de progression pendant la génération */
.export-progress{
  position:fixed;inset:0;background:rgba(13,42,38,.65);
  display:none;align-items:center;justify-content:center;
  z-index:200;backdrop-filter:blur(4px);
}
.export-progress.show{display:flex}
.export-progress-card{
  background:var(--surface);border-radius:var(--r-lg);
  box-shadow:var(--shadow-lg);padding:28px 32px;
  width:440px;max-width:calc(100vw - 32px);
  display:flex;flex-direction:column;gap:16px;
}
.export-progress-card h3{
  font-family:'Fraunces',serif;font-size:18px;font-weight:600;
  color:var(--teal-900);letter-spacing:-.01em;
  display:flex;align-items:center;gap:10px;
}
.export-progress-card h3 .spin{
  width:18px;height:18px;border:2.5px solid var(--teal-100);
  border-top-color:var(--teal-600);border-radius:50%;
  animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.export-progress-card .current{
  font-size:12.5px;color:var(--ink-2);
  padding:10px 14px;background:var(--surface-2);
  border:1px solid var(--line);border-radius:var(--r-sm);
  font-family:'JetBrains Mono',monospace;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.export-progress-card .bar-wrap{
  height:8px;background:var(--surface-3);border-radius:99px;overflow:hidden;
}
.export-progress-card .bar-fill{
  height:100%;background:linear-gradient(90deg,var(--teal-500),var(--teal-600));
  border-radius:99px;transition:width .25s ease;width:0%;
}
.export-progress-card .stats{
  display:flex;justify-content:space-between;font-size:12px;color:var(--muted);
}
.export-progress-card .stats strong{color:var(--ink);font-family:'JetBrains Mono',monospace}
.export-progress-card .done-msg{
  display:none;align-items:center;gap:10px;
  padding:10px 14px;background:var(--ok-bg);border:1px solid var(--ok-line);
  border-radius:var(--r-sm);color:var(--ok);font-size:13px;font-weight:500;
}
.export-progress-card.done .done-msg{display:flex}
.export-progress-card.done h3 .spin{display:none}
.export-progress-card .close-btn{
  display:none;padding:8px 14px;border:1px solid var(--line);
  background:var(--surface);color:var(--ink-2);border-radius:var(--r-sm);
  font:inherit;font-size:13px;font-weight:500;cursor:pointer;align-self:flex-end;
}
.export-progress-card.done .close-btn{display:inline-block}

/* MODAL D'EXPORT — sélection des modules et jours */
.export-modal{
  background:var(--surface);border-radius:var(--r-lg);
  box-shadow:var(--shadow-lg);width:680px;max-width:calc(100vw - 32px);
  max-height:calc(100vh - 40px);
  overflow:hidden;display:flex;flex-direction:column;
  animation:slideUp .25s ease;
}
.export-hero{
  background:linear-gradient(135deg,var(--teal-700) 0%,var(--teal-600) 50%,var(--teal-500) 100%);
  padding:22px 26px;color:#fff;position:relative;overflow:hidden;
}
.export-hero::before{
  content:"";position:absolute;inset:0;
  background:radial-gradient(circle at 100% 0%,rgba(125,211,168,.18) 0%,transparent 55%);
  pointer-events:none;
}
.export-hero::after{
  content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;
  background:repeating-radial-gradient(circle at center,rgba(255,255,255,.025) 0,rgba(255,255,255,.025) 1px,transparent 1px,transparent 12px);
  pointer-events:none;
}
.export-hero-inner{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:14px}
.export-hero-id{display:flex;gap:14px;align-items:center}
.export-hero-icon{
  width:48px;height:48px;border-radius:12px;
  background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  display:grid;place-items:center;flex-shrink:0;
}
.export-hero-text .label{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:3px}
.export-hero-text h3{font-family:'Fraunces',serif;font-size:21px;font-weight:600;letter-spacing:-.01em;line-height:1.15}
.export-hero-text .sub{font-size:12px;color:#cfe0db;margin-top:2px}

.export-body{
  padding:20px 26px;display:grid;grid-template-columns:1fr 1fr;gap:22px;
  overflow-y:auto;flex:1;
}
.export-section .sec-head{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--muted);font-weight:700;margin-bottom:10px;
}
.export-section .sec-head .toggle-all{
  font:inherit;font-size:10.5px;letter-spacing:.04em;text-transform:none;
  font-weight:600;color:var(--teal-600);background:transparent;border:0;cursor:pointer;
  padding:2px 6px;border-radius:var(--r-xs);
}
.export-section .sec-head .toggle-all:hover{background:var(--teal-50)}

/* Liste de checkboxes */
.checklist{
  display:flex;flex-direction:column;gap:5px;
  background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r);
  padding:6px;max-height:280px;overflow-y:auto;
}
.checklist::-webkit-scrollbar{width:6px}
.checklist::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:99px}

.check-item{
  display:flex;align-items:center;gap:10px;
  padding:8px 10px;border-radius:var(--r-sm);
  cursor:pointer;transition:background var(--t-fast);
  user-select:none;
}
.check-item:hover{background:var(--surface)}
.check-item input[type="checkbox"]{
  appearance:none;-webkit-appearance:none;
  width:16px;height:16px;border:1.5px solid var(--line-2);
  border-radius:4px;background:var(--surface);cursor:pointer;
  display:grid;place-items:center;flex-shrink:0;
  transition:all var(--t-fast);
}
.check-item input[type="checkbox"]:checked{
  background:var(--teal-600);border-color:var(--teal-600);
}
.check-item input[type="checkbox"]:checked::after{
  content:"";width:8px;height:5px;border-left:2px solid #fff;border-bottom:2px solid #fff;
  transform:rotate(-45deg) translate(1px,-1px);
}
.check-item .swatch{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.check-item .ci-text{flex:1;font-size:13px;color:var(--ink);font-weight:500;min-width:0}
.check-item .ci-text small{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:1px}
.check-item .ci-tag{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  background:var(--surface);border:1px solid var(--line);
  padding:2px 7px;border-radius:99px;color:var(--muted);font-weight:600;
}
.check-item.weekend{opacity:.85}
.check-item.weekend .ci-tag{background:#fbf6ed;color:#c97a2a;border-color:#e8c897}

/* Format radio */
.format-pick{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.format-btn{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:var(--r-sm);
  border:1.5px solid var(--line-2);background:var(--surface);
  cursor:pointer;transition:all var(--t-fast);font:inherit;text-align:left;
}
.format-btn:hover{background:var(--surface-2)}
.format-btn.on{border-color:var(--teal-500);background:var(--teal-50);box-shadow:0 0 0 3px rgba(45,128,116,.12)}
.format-btn .ic{
  width:30px;height:30px;border-radius:8px;
  background:var(--teal-50);color:var(--teal-700);
  display:grid;place-items:center;flex-shrink:0;
}
.format-btn.on .ic{background:var(--teal-600);color:#fff}
.format-btn .info{display:flex;flex-direction:column}
.format-btn .t{font-size:12.5px;font-weight:600;color:var(--ink);line-height:1.2}
.format-btn .d{font-size:10.5px;color:var(--muted);margin-top:1px}

/* Récap */
.export-recap{
  padding:14px 26px;background:var(--surface-2);
  border-top:1px solid var(--line);border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;gap:14px;
}
.export-recap .stat{display:flex;flex-direction:column;gap:1px}
.export-recap .stat .k{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:700}
.export-recap .stat .v{font-family:'Fraunces',serif;font-size:18px;font-weight:600;color:var(--teal-900);line-height:1}
.export-recap .arrow{color:var(--muted-2)}
.export-recap .filename{
  flex:1;font-family:'JetBrains Mono',monospace;font-size:11.5px;
  color:var(--ink-2);background:var(--surface);
  padding:8px 12px;border:1px solid var(--line);border-radius:var(--r-sm);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.export-recap .filename .pkg{color:var(--teal-600);font-weight:700}

.export-foot{
  display:flex;align-items:center;justify-content:flex-end;gap:8px;
  padding:14px 26px;
}

/* Zone de capture cachée */
.capture-stage{
  position:fixed;left:-99999px;top:0;
  width:1240px;background:transparent;
  pointer-events:none;
}

@media (max-width:720px){
  .export-body{grid-template-columns:1fr}
}

/* responsive */
@media (max-width:1280px){
  .stats-bar{grid-template-columns:repeat(3,1fr)}
}
@media (max-width:980px){
  .app{grid-template-columns:0 1fr}
  .app:not(.sb-collapsed) .sidebar{position:fixed;width:240px;z-index:60;box-shadow:0 0 40px rgba(0,0,0,.4)}
  .app.sb-collapsed{grid-template-columns:0 1fr}
  .app.sb-collapsed .sidebar{display:none}
  .stats-bar{grid-template-columns:repeat(2,1fr)}
  .topbar-title .sub{display:none}
  .day-header{padding:14px 16px;gap:14px}
  .day-header .day-big{font-size:28px}
}
</style>

<div class="ss-rep-page">
    <div class="subheader">
      <div class="crumbs-l">
        <span>Planning(s)</span><span class="sep">›</span>
        <strong>2026-05</strong><span class="sep">›</span>
        <span class="tag-final">final</span>
      </div>

      <div class="week-nav">
        <button title="Précédent"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
        <span class="week-label" id="periodLabel">Semaine <span class="num">19</span> · 4 → 10 mai 2026</span>
        <button title="Suivant"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
      </div>

      <button class="btn-today">Aujourd'hui</button>

      <!-- Vue Jour / Semaine -->
      <div class="view-toggle" id="viewToggle">
        <button class="on" data-view="week">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          Semaine
        </button>
        <button data-view="day">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16h6"/></svg>
          Jour
        </button>
      </div>

      <div class="subheader-spacer"></div>

      <button class="btn btn-ghost" title="Imprimer">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
        Imprimer
      </button>

      <!-- BOUTON EXPORT avec dropdown -->
      <div class="export-wrap">
        <button class="btn" id="btnExport">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          Exporter
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-left:2px"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="export-menu" id="exportMenu">
          <div class="group-title">Format de sortie</div>
          <button data-rep-export-type="image">
            <div class="ic">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            </div>
            <div class="info">
              <span class="t">Image (PNG / JPEG)</span>
              <span class="d">1 fichier par module/jour · ZIP si plusieurs</span>
            </div>
          </button>
          <div class="sep"></div>
          <div class="group-title">Autres formats</div>
          <button data-rep-export-type="pdf">
            <div class="ic">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
            </div>
            <div class="info">
              <span class="t">PDF</span>
              <span class="d">Semaine complète · prêt à imprimer</span>
            </div>
          </button>
          <button data-rep-export-type="excel">
            <div class="ic">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
            </div>
            <div class="info">
              <span class="t">Excel (.xlsx)</span>
              <span class="d">Pour reporting et analyse</span>
            </div>
          </button>
        </div>
      </div>

      <button class="btn" id="btnEdit">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        <span id="editLabel">Éditer</span>
      </button>
      <div class="date-picker">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        04 . 05 . 2026
      </div>
      <button class="btn btn-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2zM17 21v-8H7v8M7 3v5h8"/></svg>
        Enregistrer
      </button>
    </div>

    <!-- BANNIÈRE MODE ÉDITION -->
    <div class="edit-banner">
      <span class="ic">✎</span>
      <span><strong>Mode édition activé</strong> — glissez-déposez les cellules pour les déplacer. Cliquez sur une cellule pour ouvrir l'éditeur.</span>
      <span class="spacer"></span>
      <button data-rep-edit-quit>Quitter l'édition</button>
    </div>

    <div class="content">

      <!-- MODULE FILTER -->
      <div class="mod-filter">
        <span class="lbl">Filtrer</span>
        <button class="mf-chip on" data-mod="all">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Tous les modules <span class="count">7</span>
        </button>
        <button class="mf-chip" data-mod="rj">
          <span class="swatch" style="background:#164a42"></span>RJ / RJN <span class="count">4</span>
        </button>
        <button class="mf-chip" data-mod="m1">
          <span class="swatch" style="background:#1f6359"></span>Module 1 <span class="count">24</span>
        </button>
        <button class="mf-chip" data-mod="m2">
          <span class="swatch" style="background:#2d4a6b"></span>Module 2 <span class="count">22</span>
        </button>
        <button class="mf-chip" data-mod="m3">
          <span class="swatch" style="background:#8a5a1a"></span>Module 3 <span class="count">22</span>
        </button>
        <button class="mf-chip" data-mod="m4">
          <span class="swatch" style="background:#5e3a78"></span>Module 4 <span class="count">15</span>
        </button>
        <button class="mf-chip" data-mod="pool">
          <span class="swatch" style="background:#8a3a30"></span>Pool <span class="count">2</span>
        </button>
        <button class="mf-chip" data-mod="na">
          <span class="swatch" style="background:#6b8783"></span>Non assigné <span class="count">2</span>
        </button>
      </div>

      <!-- STATS BAR -->
      <div class="stats-bar">
        <div class="stat-card"><div class="lbl">Postes assignés</div><div class="v">261<small> / 273</small></div><div class="sub">Semaine complète · 7j/7</div></div>
        <div class="stat-card warn"><div class="lbl">Postes vacants</div><div class="v">12</div><div class="sub">Pool sollicité</div></div>
        <div class="stat-card ok"><div class="lbl">Couverture min.</div><div class="v">95%</div><div class="sub">Seuil cible 90%</div></div>
        <div class="stat-card"><div class="lbl">Heures planifiées</div><div class="v">2'587<small> h</small></div><div class="sub">−18h vs S18</div></div>
        <div class="stat-card info"><div class="lbl">Pool en charge</div><div class="v">7<small> remplaçant·es</small></div><div class="sub">Joëlle, Eva +5</div></div>
        <div class="stat-card danger"><div class="lbl">Absents aujourd'hui</div><div class="v">2</div><div class="sub">B. Thomas, M. Rey</div></div>
      </div>

      <!-- ============================================== -->
      <!-- VUE SEMAINE                                    -->
      <!-- ============================================== -->
      <div class="week-view" id="weekView">

        <!-- RJ -->
        <div class="module m-rj" data-mod="rj">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg></div>
            <h2>RJ / RJN — Responsables</h2>
            <span class="count">4 postes</span>
            <div class="actions">
              <button title="Ajouter"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
              <button title="Plus"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction" rowspan="3"><div class="label">RJ / RJN<small>Responsable</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Anne Moreau" data-date="2026-05-04"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Anne Moreau" data-date="2026-05-05"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Anne Moreau" data-date="2026-05-06"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Anne Moreau" data-date="2026-05-07"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Anne Moreau" data-date="2026-05-08"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Anne Moreau" data-date="2026-05-09"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Anne Moreau" data-date="2026-05-10"><div class="cell-main"><span class="name">Anne Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell" data-name="Caroline Blanc" data-date="2026-05-04"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Caroline Blanc" data-date="2026-05-05"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Caroline Blanc" data-date="2026-05-06"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Caroline Blanc" data-date="2026-05-07"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Caroline Blanc" data-date="2026-05-08"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Caroline Blanc" data-date="2026-05-09"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Caroline Blanc" data-date="2026-05-10"><div class="cell-main"><span class="name">Caroline Blanc</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">3</td>
                  <td><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-04"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-05"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-06"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-07"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-08"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-09"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Alexandra Corpataux" data-date="2026-05-10"><div class="cell-main"><span class="name">Alexandra Corpataux</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
                <tr>
                  <td class="col-fonction"><div class="label">Resp. soins<small>extra</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Véronique Dupont" data-date="2026-05-04"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Véronique Dupont" data-date="2026-05-05"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Véronique Dupont" data-date="2026-05-06"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Véronique Dupont" data-date="2026-05-07"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Véronique Dupont" data-date="2026-05-08"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Véronique Dupont" data-date="2026-05-09"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Véronique Dupont" data-date="2026-05-10"><div class="cell-main"><span class="name">Véronique Dupont</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">—</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- MODULE 1 -->
        <div class="module m-m1" data-mod="m1">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 1 — Étages 1, 2</h2>
            <span class="count">24 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction" rowspan="8"><div class="label">Infirmière<small>8 postes</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Camille Bovey" data-date="2026-05-04"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Amandine Cosandey" data-date="2026-05-05"><div class="cell-main"><span class="name">Amandine Cosandey</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Benoît Thomas" data-date="2026-05-06"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-07"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell" data-name="Camille Bovey" data-date="2026-05-08"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Camille Bovey" data-date="2026-05-09"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-10"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell absent" data-name="Monique Rey" data-date="2026-05-04"><div class="cell-main"><span class="absent-ico">!</span><span class="name">Monique Rey</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93 19.07 19.07"/></svg> Abs.</div></div></td>
                  <td><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-05"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Céline Garcia" data-date="2026-05-06"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell" data-name="Aline Meylan" data-date="2026-05-07"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell" data-name="Benoît Thomas" data-date="2026-05-08"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Benoît Thomas" data-date="2026-05-09"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Aline Meylan" data-date="2026-05-10"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">3</td>
                  <td><div class="cell" data-name="Benoît Thomas" data-date="2026-05-04"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift a2 horaire">A2</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Pierre Piguet" data-date="2026-05-05"><div class="cell-main"><span class="name">Pierre Piguet</span><span class="shift d1 horaire">D1</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell absent" data-name="Thomas Moreau" data-date="2026-05-06"><div class="cell-main"><span class="absent-ico">!</span><span class="name">Thomas Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93 19.07 19.07"/></svg> Abs.</div></div></td>
                  <td><div class="cell" data-name="Monique Rey" data-date="2026-05-07"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Thomas Moreau" data-date="2026-05-08"><div class="cell-main"><span class="name">Thomas Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Thomas Moreau" data-date="2026-05-09"><div class="cell-main"><span class="name">Thomas Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Monique Rey" data-date="2026-05-10"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">4</td>
                  <td><div class="cell" data-name="Céline Garcia" data-date="2026-05-04"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell" data-name="Aline Meylan" data-date="2026-05-05"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Camille Bovey" data-date="2026-05-06"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Camille Bovey" data-date="2026-05-07"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-08"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-09"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Camille Bovey" data-date="2026-05-10"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">5</td>
                  <td><div class="cell" data-name="Aline Meylan" data-date="2026-05-04"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell faded" data-name="Benoît Thomas" data-date="2026-05-05"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell" data-name="Pierre Piguet" data-date="2026-05-06"><div class="cell-main"><span class="name">Pierre Piguet</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Thomas Moreau" data-date="2026-05-07"><div class="cell-main"><span class="name">Thomas Moreau</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Monique Rey" data-date="2026-05-08"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Monique Rey" data-date="2026-05-09"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Thomas Moreau" data-date="2026-05-10"><div class="cell-main"><span class="name">Thomas Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">6</td>
                  <td><div class="cell" data-name="Thomas Moreau" data-date="2026-05-04"><div class="cell-main"><span class="name">Thomas Moreau</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Camille Bovey" data-date="2026-05-05"><div class="cell-main"><span class="name">Camille Bovey</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Benoît Thomas" data-date="2026-05-06"><div class="cell-main"><span class="name">Benoît Thomas</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Céline Garcia" data-date="2026-05-07"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Aline Meylan" data-date="2026-05-08"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Aline Meylan" data-date="2026-05-09"><div class="cell-main"><span class="name">Aline Meylan</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Céline Garcia" data-date="2026-05-10"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">7</td>
                  <td><div class="cell" data-name="Émilie Jolivet" data-date="2026-05-04"><div class="cell-main"><span class="name">Émilie Jolivet</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell" data-name="Céline Garcia" data-date="2026-05-05"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Pierre Piguet" data-date="2026-05-07"><div class="cell-main"><span class="name">Pierre Piguet</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Pierre Piguet" data-date="2026-05-09"><div class="cell-main"><span class="name">Pierre Piguet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td><td class="weekend"><div class="cell" data-name="Céline Garcia" data-date="2026-05-10"><div class="cell-main"><span class="name">Céline Garcia</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td></tr>
                <tr>
                  <td class="col-poste">8</td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Monique Rey" data-date="2026-05-05"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift s4 horaire">S4</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Monique Rey" data-date="2026-05-09"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td><td class="weekend"><div class="cell" data-name="Monique Rey" data-date="2026-05-10"><div class="cell-main"><span class="name">Monique Rey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td></tr>

                <tr>
                  <td class="col-fonction" rowspan="4"><div class="label">ASSC<small>4 postes</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Alain Durand" data-date="2026-05-04"><div class="cell-main"><span class="name">Alain Durand</span><span class="shift d1 horaire">D1</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Alain Durand" data-date="2026-05-05"><div class="cell-main"><span class="name">Alain Durand</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Élodie Pittet" data-date="2026-05-06"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Anaïs Laurent" data-date="2026-05-07"><div class="cell-main"><span class="name">Anaïs Laurent</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell" data-name="Élodie Pittet" data-date="2026-05-08"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Élodie Pittet" data-date="2026-05-09"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Anaïs Laurent" data-date="2026-05-10"><div class="cell-main"><span class="name">Anaïs Laurent</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell" data-name="Anaïs Laurent" data-date="2026-05-04"><div class="cell-main"><span class="name">Anaïs Laurent</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Anaïs Laurent" data-date="2026-05-05"><div class="cell-main"><span class="name">Anaïs Laurent</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Alain Durand" data-date="2026-05-06"><div class="cell-main"><span class="name">Alain Durand</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Alain Durand" data-date="2026-05-09"><div class="cell-main"><span class="name">Alain Durand</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td><td class="weekend"><div class="cell" data-name="Anaïs Laurent" data-date="2026-05-10"><div class="cell-main"><span class="name">Anaïs Laurent</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td></tr>
                <tr>
                  <td class="col-poste">3</td>
                  <td><div class="cell" data-name="Élodie Pittet" data-date="2026-05-04"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Élodie Pittet" data-date="2026-05-09"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td><td class="weekend"><div class="cell" data-name="Élodie Pittet" data-date="2026-05-10"><div class="cell-main"><span class="name">Élodie Pittet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td></tr>
                <tr>
                  <td class="col-poste">4</td>
                  <td><div class="cell" data-name="Eva Bühler" data-date="2026-05-04"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Eva Bühler" data-date="2026-05-09"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td><td class="weekend"><div class="cell" data-name="Eva Bühler" data-date="2026-05-10"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td></tr>

                <tr>
                  <td class="col-fonction"><div class="label">Apprenti<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Aurélie Savary" data-date="2026-05-04"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Aurélie Savary" data-date="2026-05-05"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Aurélie Savary" data-date="2026-05-06"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Aurélie Savary" data-date="2026-05-07"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Aurélie Savary" data-date="2026-05-08"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Aurélie Savary" data-date="2026-05-09"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Aurélie Savary" data-date="2026-05-10"><div class="cell-main"><span class="name">Aurélie Savary</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                </tr>
                <tr>
                  <td class="col-fonction"><div class="label">Civiliste<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Samuel André" data-date="2026-05-04"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Samuel André" data-date="2026-05-05"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Samuel André" data-date="2026-05-06"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Samuel André" data-date="2026-05-08"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Samuel André" data-date="2026-05-09"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Samuel André" data-date="2026-05-10"><div class="cell-main"><span class="name">Samuel André</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-fonction"><div class="label">ASE / Anim.<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Clara Petit" data-date="2026-05-04"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell" data-name="Clara Petit" data-date="2026-05-05"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-1-A</div></div></td>
                  <td><div class="cell" data-name="Clara Petit" data-date="2026-05-06"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td>
                  <td><div class="cell" data-name="Clara Petit" data-date="2026-05-07"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Clara Petit" data-date="2026-05-09"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E1-2</div></div></td><td class="weekend"><div class="cell" data-name="Clara Petit" data-date="2026-05-10"><div class="cell-main"><span class="name">Clara Petit</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E2-2-A</div></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- MODULE 2 (compact - infirmières uniquement pour démo) -->
        <div class="module m-m2" data-mod="m2">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 2 — Étages 3, 4</h2>
            <span class="count">22 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction" rowspan="3"><div class="label">Infirmière<small>6 postes</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Philippe Dubois" data-date="2026-05-04"><div class="cell-main"><span class="name">Philippe Dubois</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td><div class="cell" data-name="Sandrine Lambert" data-date="2026-05-05"><div class="cell-main"><span class="name">Sandrine Lambert</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E4-2</div></div></td>
                  <td><div class="cell" data-name="Catherine Tchanen" data-date="2026-05-06"><div class="cell-main"><span class="name">Catherine Tchanen</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-2</div></div></td>
                  <td><div class="cell" data-name="Philippe Dubois" data-date="2026-05-07"><div class="cell-main"><span class="name">Philippe Dubois</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td><div class="cell" data-name="Alexandre Pochon" data-date="2026-05-08"><div class="cell-main"><span class="name">Alexandre Pochon</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E4-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Alexandre Pochon" data-date="2026-05-09"><div class="cell-main"><span class="name">Alexandre Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E4-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Philippe Dubois" data-date="2026-05-10"><div class="cell-main"><span class="name">Philippe Dubois</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E3-1</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell" data-name="Zoé Girard" data-date="2026-05-04"><div class="cell-main"><span class="name">Zoé Girard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E4-1</div></div></td>
                  <td><div class="cell" data-name="Gabriel Richard" data-date="2026-05-05"><div class="cell-main"><span class="name">Gabriel Richard</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E3-2</div></div></td>
                  <td><div class="cell" data-name="Sandrine Lambert" data-date="2026-05-06"><div class="cell-main"><span class="name">Sandrine Lambert</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E4-1</div></div></td>
                  <td><div class="cell" data-name="Gabriel Richard" data-date="2026-05-07"><div class="cell-main"><span class="name">Gabriel Richard</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E4-2</div></div></td>
                  <td><div class="cell" data-name="Sylvie Chapuis" data-date="2026-05-08"><div class="cell-main"><span class="name">Sylvie Chapuis</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Sylvie Chapuis" data-date="2026-05-09"><div class="cell-main"><span class="name">Sylvie Chapuis</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Gabriel Richard" data-date="2026-05-10"><div class="cell-main"><span class="name">Gabriel Richard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E4-2</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">3</td>
                  <td><div class="cell" data-name="Catherine Tchanen" data-date="2026-05-04"><div class="cell-main"><span class="name">Catherine Tchanen</span><span class="shift s3 horaire">S3</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td><div class="cell" data-name="Romain Michel" data-date="2026-05-05"><div class="cell-main"><span class="name">Romain Michel</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E4-1</div></div></td>
                  <td><div class="cell" data-name="Sylvie Chapuis" data-date="2026-05-06"><div class="cell-main"><span class="name">Sylvie Chapuis</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E3-1</div></div></td>
                  <td><div class="cell" data-name="Zoé Girard" data-date="2026-05-07"><div class="cell-main"><span class="name">Zoé Girard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-2</div></div></td>
                  <td><div class="cell" data-name="Sylvie Chapuis" data-date="2026-05-08"><div class="cell-main"><span class="name">Sylvie Chapuis</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E4-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Sylvie Chapuis" data-date="2026-05-09"><div class="cell-main"><span class="name">Sylvie Chapuis</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E4-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Zoé Girard" data-date="2026-05-10"><div class="cell-main"><span class="name">Zoé Girard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E3-2</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- MODULE 3 (compact) -->
        <div class="module m-m3" data-mod="m3">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 3 — Étages 5, 6</h2>
            <span class="count">22 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction" rowspan="3"><div class="label">Infirmière<small>6 postes</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Cédric Guex" data-date="2026-05-04"><div class="cell-main"><span class="name">Cédric Guex</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-1</div></div></td>
                  <td><div class="cell" data-name="Patricia Martin" data-date="2026-05-05"><div class="cell-main"><span class="name">Patricia Martin</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E6-2</div></div></td>
                  <td><div class="cell" data-name="Justine Chouinard" data-date="2026-05-06"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E5-2</div></div></td>
                  <td><div class="cell" data-name="Patricia Martin" data-date="2026-05-07"><div class="cell-main"><span class="name">Patricia Martin</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">E6-1</div></div></td>
                  <td><div class="cell" data-name="Pascal Pochon" data-date="2026-05-08"><div class="cell-main"><span class="name">Pascal Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Pascal Pochon" data-date="2026-05-09"><div class="cell-main"><span class="name">Pascal Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-1</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Patricia Martin" data-date="2026-05-10"><div class="cell-main"><span class="name">Patricia Martin</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E6-1</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell" data-name="Patricia Martin" data-date="2026-05-04"><div class="cell-main"><span class="name">Patricia Martin</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-2</div></div></td>
                  <td><div class="cell" data-name="Morgane Zimmermann" data-date="2026-05-05"><div class="cell-main"><span class="name">Morgane Zimmermann</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E6-1</div></div></td>
                  <td><div class="cell" data-name="Patricia Martin" data-date="2026-05-06"><div class="cell-main"><span class="name">Patricia Martin</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E6-2</div></div></td>
                  <td><div class="cell" data-name="Pascal Pochon" data-date="2026-05-07"><div class="cell-main"><span class="name">Pascal Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-1</div></div></td>
                  <td><div class="cell" data-name="Florence Monnier" data-date="2026-05-08"><div class="cell-main"><span class="name">Florence Monnier</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E6-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Florence Monnier" data-date="2026-05-09"><div class="cell-main"><span class="name">Florence Monnier</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E6-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Pascal Pochon" data-date="2026-05-10"><div class="cell-main"><span class="name">Pascal Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-1</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">3</td>
                  <td><div class="cell" data-name="Florence Monnier" data-date="2026-05-04"><div class="cell-main"><span class="name">Florence Monnier</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E6-1</div></div></td>
                  <td><div class="cell" data-name="Justine Chouinard" data-date="2026-05-05"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E5-1</div></div></td>
                  <td><div class="cell" data-name="Pascal Pochon" data-date="2026-05-06"><div class="cell-main"><span class="name">Pascal Pochon</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E6-1</div></div></td>
                  <td><div class="cell" data-name="Justine Chouinard" data-date="2026-05-07"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E5-2</div></div></td>
                  <td><div class="cell" data-name="Justine Chouinard" data-date="2026-05-08"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Justine Chouinard" data-date="2026-05-09"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-2</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Justine Chouinard" data-date="2026-05-10"><div class="cell-main"><span class="name">Justine Chouinard</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E5-2</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- MODULE 4 (compact) -->
        <div class="module m-m4" data-mod="m4">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 4 — Étage 7</h2>
            <span class="count">15 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction" rowspan="3"><div class="label">Infirmière<small>6 postes</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Lisa Cosendey" data-date="2026-05-04"><div class="cell-main"><span class="name">Lisa Cosendey</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Lisa Cosendey" data-date="2026-05-05"><div class="cell-main"><span class="name">Lisa Cosendey</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Michel Berset" data-date="2026-05-06"><div class="cell-main"><span class="name">Michel Berset</span><span class="shift c1 horaire">C1</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Olivier Moreau" data-date="2026-05-07"><div class="cell-main"><span class="name">Olivier Moreau</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Sabine Guex" data-date="2026-05-08"><div class="cell-main"><span class="name">Sabine Guex</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Sabine Guex" data-date="2026-05-09"><div class="cell-main"><span class="name">Sabine Guex</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Olivier Moreau" data-date="2026-05-10"><div class="cell-main"><span class="name">Olivier Moreau</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E7-B</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">2</td>
                  <td><div class="cell" data-name="Sabine Guex" data-date="2026-05-04"><div class="cell-main"><span class="name">Sabine Guex</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Isabelle Simon" data-date="2026-05-05"><div class="cell-main"><span class="name">Isabelle Simon</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Isabelle Simon" data-date="2026-05-06"><div class="cell-main"><span class="name">Isabelle Simon</span><span class="shift d3 horaire">D3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Michel Berset" data-date="2026-05-07"><div class="cell-main"><span class="name">Michel Berset</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Michel Berset" data-date="2026-05-08"><div class="cell-main"><span class="name">Michel Berset</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Michel Berset" data-date="2026-05-09"><div class="cell-main"><span class="name">Michel Berset</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Michel Berset" data-date="2026-05-10"><div class="cell-main"><span class="name">Michel Berset</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-A</div></div></td>
                </tr>
                <tr>
                  <td class="col-poste">N</td>
                  <td><div class="cell" data-name="Yannick Piguet" data-date="2026-05-04"><div class="cell-main"><span class="name">Yannick Piguet</span><span class="shift n1 horaire">N1</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Yannick Piguet" data-date="2026-05-05"><div class="cell-main"><span class="name">Yannick Piguet</span><span class="shift n1 horaire">N1</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Julien Martin" data-date="2026-05-06"><div class="cell-main"><span class="name">Julien Martin</span><span class="shift n1 horaire">N1</span></div><div class="cell-etage">E7-A</div></div></td>
                  <td><div class="cell" data-name="Julien Martin" data-date="2026-05-07"><div class="cell-main"><span class="name">Julien Martin</span><span class="shift n1 horaire">N1</span></div><div class="cell-etage">E7-B</div></div></td>
                  <td><div class="cell" data-name="Yannick Piguet" data-date="2026-05-08"><div class="cell-main"><span class="name">Yannick Piguet</span><span class="shift piquet horaire">PIQUET</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Yannick Piguet" data-date="2026-05-09"><div class="cell-main"><span class="name">Yannick Piguet</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Julien Martin" data-date="2026-05-10"><div class="cell-main"><span class="name">Julien Martin</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">E7-B</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- POOL -->
        <div class="module m-pool" data-mod="pool">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/></svg></div>
            <h2>Pool — Remplacements</h2>
            <span class="count">2 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction"><div class="label">ASSC<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Eva Bühler" data-date="2026-05-05"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">M1</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                <td class="weekend"><div class="cell" data-name="Eva Bühler" data-date="2026-05-09"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift c2 horaire">C2</span></div><div class="cell-etage">M1</div></div></td><td class="weekend"><div class="cell" data-name="Eva Bühler" data-date="2026-05-10"><div class="cell-main"><span class="name">Eva Bühler</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">M1</div></div></td></tr>
                <tr>
                  <td class="col-fonction"><div class="label">Concierge<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-04"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-05"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-06"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-07"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-08"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-09"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Joëlle Renaud" data-date="2026-05-10"><div class="cell-main"><span class="name">Joëlle Renaud</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- POOL/NA -->
        <div class="module m-na" data-mod="na">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></div>
            <h2>Pool / Non assigné</h2>
            <span class="count">2 postes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart">
              <colgroup><col style="width:90px"><col style="width:48px"><col><col><col><col><col><col><col></colgroup>
              <thead>
                <tr class="day-row">
                  <th class="col-fonction" rowspan="2"><div style="padding:8px 4px">Fonction</div></th>
                  <th class="col-poste" rowspan="2">Poste</th>
                  <th class="col-day"><span class="day-name">Lundi</span><span class="day-date">04 mai</span></th>
                  <th class="col-day"><span class="day-name">Mardi</span><span class="day-date">05 mai</span></th>
                  <th class="col-day"><span class="day-name">Mercredi</span><span class="day-date">06 mai</span></th>
                  <th class="col-day"><span class="day-name">Jeudi</span><span class="day-date">07 mai</span></th>
                  <th class="col-day"><span class="day-name">Vendredi</span><span class="day-date">08 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Samedi</span><span class="day-date">09 mai</span></th>
                  <th class="col-day weekend"><span class="day-name">Dimanche</span><span class="day-date">10 mai</span></th>
                </tr>
                <tr class="subhead-row">
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                  <th class="weekend"><div class="sub-double"><span>Nom · Horaire</span><span>Étage</span></div></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-fonction"><div class="label">Cuisinier<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Dominic Soupe" data-date="2026-05-04"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Dominic Soupe" data-date="2026-05-05"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Dominic Soupe" data-date="2026-05-06"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell empty"><div class="cell-main"></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Dominic Soupe" data-date="2026-05-08"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Dominic Soupe" data-date="2026-05-09"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Dominic Soupe" data-date="2026-05-10"><div class="cell-main"><span class="name">Dominic Soupe</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
                <tr>
                  <td class="col-fonction"><div class="label">Réception<small>1 poste</small></div></td>
                  <td class="col-poste">1</td>
                  <td><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-04"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-05"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-06"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-07"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-08"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-09"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                  <td class="weekend"><div class="cell" data-name="Sami Zaghlami" data-date="2026-05-10"><div class="cell-main"><span class="name">Sami Zaghlami</span><span class="shift a3 horaire">A3</span></div><div class="cell-etage">—</div></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ============================================== -->
      <!-- VUE JOUR — détails étendus                     -->
      <!-- ============================================== -->
      <div class="day-view" id="dayView">

        <div class="day-header">
          <div class="day-big">04</div>
          <div class="day-info">
            <div class="name">Lundi</div>
            <div class="full">mai 2026</div>
          </div>
          <div class="day-meta">
            <div class="m"><span class="k">Postes</span><span class="v">38</span></div>
            <div class="m"><span class="k">Présents</span><span class="v">36</span></div>
            <div class="m"><span class="k">Absents</span><span class="v" style="color:#fbb6ad">2</span></div>
            <div class="m"><span class="k">Heures</span><span class="v">312<span style="font-size:13px;font-weight:400">h</span></span></div>
          </div>
        </div>

        <div class="module m-m1" data-mod="m1">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 1 — Étages 1, 2 · Lundi 04 mai</h2>
            <span class="count">12 personnes</span>
            <div class="actions">
              <button><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg></button>
            </div>
          </div>
          <div class="module-body">
            <table class="repart-day">
              <thead>
                <tr>
                  <th class="col-day-fonc">Fonction</th>
                  <th class="col-day-poste center">Poste</th>
                  <th class="col-day-name">Collaborateur</th>
                  <th class="col-day-horaire center">Horaire</th>
                  <th class="col-day-time center">Plage horaire</th>
                  <th class="col-day-etage center">Étage</th>
                  <th class="col-day-pause center">Pause</th>
                  <th class="col-day-status center">Statut</th>
                  <th class="col-day-actions center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-day-fonc">Infirmière</td>
                  <td class="col-day-poste">1</td>
                  <td><div class="day-collab"><div class="av" style="background:#1f6359">CB</div><div class="info"><div class="name">Camille Bovey</div><div class="role">Infirmière dipl. ES · 80%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift a2">A2</span></td>
                  <td class="col-day-time">07:00 → 16:00</td>
                  <td class="col-day-etage">E1-1-A</td>
                  <td class="col-day-pause">12:00 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présente</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button title="Modifier"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button title="Plus"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">Infirmière</td>
                  <td class="col-day-poste">2</td>
                  <td><div class="day-collab"><div class="av" style="background:#8a3a30">MR</div><div class="info"><div class="name">Monique Rey</div><div class="role">Infirmière dipl. ES · 100%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift a2">A2</span></td>
                  <td class="col-day-time">07:00 → 16:00</td>
                  <td class="col-day-etage" style="color:var(--danger);font-weight:600">— absente —</td>
                  <td class="col-day-pause">—</td>
                  <td class="col-day-status"><span class="day-status absent"><span class="b"></span>Absente · Maladie</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button title="Modifier"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button title="Remplacer"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M3 21v-5h5"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">Infirmière</td>
                  <td class="col-day-poste">3</td>
                  <td><div class="day-collab"><div class="av" style="background:#5e3a78">BT</div><div class="info"><div class="name">Benoît Thomas</div><div class="role">Infirmière dipl. ES · 100%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift a2">A2</span></td>
                  <td class="col-day-time">07:00 → 16:00</td>
                  <td class="col-day-etage">E2-2-A</td>
                  <td class="col-day-pause">12:00 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présent</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">Infirmière</td>
                  <td class="col-day-poste">4</td>
                  <td><div class="day-collab"><div class="av" style="background:#2d4a6b">CG</div><div class="info"><div class="name">Céline Garcia</div><div class="role">Infirmière dipl. HES · 80%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift a3">A3</span></td>
                  <td class="col-day-time">08:00 → 16:30</td>
                  <td class="col-day-etage">E1-1-B</td>
                  <td class="col-day-pause">12:30 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présente</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">ASSC</td>
                  <td class="col-day-poste">1</td>
                  <td><div class="day-collab"><div class="av" style="background:#8a5a1a">AD</div><div class="info"><div class="name">Alain Durand</div><div class="role">ASSC · 100%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift d1">D1</span></td>
                  <td class="col-day-time">07:00 → 15:30</td>
                  <td class="col-day-etage">E1-1-A</td>
                  <td class="col-day-pause">11:30 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présent</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">ASSC</td>
                  <td class="col-day-poste">2</td>
                  <td><div class="day-collab"><div class="av" style="background:#1f6359">AL</div><div class="info"><div class="name">Anaïs Laurent</div><div class="role">ASSC · 80%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift c2">C2</span></td>
                  <td class="col-day-time">12:00 → 20:30</td>
                  <td class="col-day-etage">E1-2</td>
                  <td class="col-day-pause">15:30 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présente</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">Aide-soign.</td>
                  <td class="col-day-poste">5</td>
                  <td><div class="day-collab"><div class="av" style="background:#c46658">EL</div><div class="info"><div class="name">Estelle Laurent</div><div class="role">ASE · 100%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift c1">C1</span></td>
                  <td class="col-day-time">08:30 → 15:30</td>
                  <td class="col-day-etage">E1-1-A</td>
                  <td class="col-day-pause">12:00 · 30min</td>
                  <td class="col-day-status"><span class="day-status warn"><span class="b"></span>Retard 15min</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
                <tr>
                  <td class="col-day-fonc">Apprenti</td>
                  <td class="col-day-poste">1</td>
                  <td><div class="day-collab"><div class="av" style="background:#5a82a8">AS</div><div class="info"><div class="name">Aurélie Savary</div><div class="role">Apprentie ASE · 100%</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift a3">A3</span></td>
                  <td class="col-day-time">08:00 → 16:30</td>
                  <td class="col-day-etage">E1-2</td>
                  <td class="col-day-pause">12:30 · 30min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présente</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="module m-m4" data-mod="m4">
          <div class="module-head">
            <div class="ico"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <h2>Module 4 — Étage 7 · Lundi 04 mai</h2>
            <span class="count">8 personnes</span>
          </div>
          <div class="module-body">
            <table class="repart-day">
              <thead>
                <tr>
                  <th class="col-day-fonc">Fonction</th>
                  <th class="col-day-poste center">Poste</th>
                  <th class="col-day-name">Collaborateur</th>
                  <th class="col-day-horaire center">Horaire</th>
                  <th class="col-day-time center">Plage horaire</th>
                  <th class="col-day-etage center">Étage</th>
                  <th class="col-day-pause center">Pause</th>
                  <th class="col-day-status center">Statut</th>
                  <th class="col-day-actions center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="col-day-fonc">Infirmière</td>
                  <td class="col-day-poste">N</td>
                  <td><div class="day-collab"><div class="av" style="background:#0d2a26">YP</div><div class="info"><div class="name">Yannick Piguet</div><div class="role">Infirmier dipl. · Nuit fixe</div></div></div></td>
                  <td class="col-day-horaire"><span class="shift n1">N1</span></td>
                  <td class="col-day-time">20:15 → 07:15</td>
                  <td class="col-day-etage">E7-A</td>
                  <td class="col-day-pause">02:00 · 45min</td>
                  <td class="col-day-status"><span class="day-status ok"><span class="b"></span>Présent</span></td>
                  <td class="col-day-actions"><div class="day-row-actions"><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button><button><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- LÉGENDE -->
      <div class="legend-panel">
        <div class="legend-row">
          <span class="legend-label">Horaires</span>
          <span class="legend-item"><span class="shift a2">A2</span> 07:00-16:00</span>
          <span class="legend-item"><span class="shift a3">A3</span> 08:00-16:30</span>
          <span class="legend-item"><span class="shift c1">C1</span> 08:30-15:30</span>
          <span class="legend-item"><span class="shift c2">C2</span> 12:00-20:30</span>
          <span class="legend-item"><span class="shift d1">D1</span> 07:00-15:30</span>
          <span class="legend-item"><span class="shift d3">D3</span> 07:00-20:30</span>
          <span class="legend-item"><span class="shift d4">D4</span> 07:00-19:00</span>
          <span class="legend-item"><span class="shift s3">S3</span> 13:00-20:30</span>
          <span class="legend-item"><span class="shift s4">S4</span> 14:00-20:30</span>
          <span class="legend-item"><span class="shift n1">N1</span> 20:15-07:15</span>
          <span class="legend-item"><span class="shift piquet">PIQUET</span> astreinte 24/24</span>
        </div>
      </div>

    </div>
<!-- ============================================== -->
<!-- MODAL EXPORT — sélection modules + jours       -->
<!-- ============================================== -->
<div class="modal-overlay" id="exportModal">
  <div class="export-modal">
    <div class="export-hero">
      <div class="export-hero-inner">
        <div class="export-hero-id">
          <div class="export-hero-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          </div>
          <div class="export-hero-text">
            <div class="label">Exporter en image</div>
            <h3>Choisir modules et jours</h3>
            <div class="sub">Une image sera générée pour chaque module × chaque jour sélectionné</div>
          </div>
        </div>
        <button class="modal-close" title="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <div class="export-body">

      <!-- Modules -->
      <div class="export-section">
        <div class="sec-head">
          <span>Modules</span>
          <button class="toggle-all" data-rep-toggle-all="modules">Tout / Aucun</button>
        </div>
        <div class="checklist" id="moduleList">
          <label class="check-item"><input type="checkbox" data-mod="rj" checked><span class="swatch" style="background:#164a42"></span><span class="ci-text">RJ / RJN <small>Responsables</small></span><span class="ci-tag">4</span></label>
          <label class="check-item"><input type="checkbox" data-mod="m1" checked><span class="swatch" style="background:#1f6359"></span><span class="ci-text">Module 1 <small>Étages 1, 2</small></span><span class="ci-tag">24</span></label>
          <label class="check-item"><input type="checkbox" data-mod="m2" checked><span class="swatch" style="background:#2d4a6b"></span><span class="ci-text">Module 2 <small>Étages 3, 4</small></span><span class="ci-tag">22</span></label>
          <label class="check-item"><input type="checkbox" data-mod="m3" checked><span class="swatch" style="background:#8a5a1a"></span><span class="ci-text">Module 3 <small>Étages 5, 6</small></span><span class="ci-tag">22</span></label>
          <label class="check-item"><input type="checkbox" data-mod="m4" checked><span class="swatch" style="background:#5e3a78"></span><span class="ci-text">Module 4 <small>Étage 7</small></span><span class="ci-tag">15</span></label>
          <label class="check-item"><input type="checkbox" data-mod="pool" checked><span class="swatch" style="background:#8a3a30"></span><span class="ci-text">Pool <small>Remplacements</small></span><span class="ci-tag">2</span></label>
          <label class="check-item"><input type="checkbox" data-mod="na" checked><span class="swatch" style="background:#6b8783"></span><span class="ci-text">Pool / Non assigné</span><span class="ci-tag">2</span></label>
        </div>
      </div>

      <!-- Jours + format -->
      <div class="export-section">
        <div class="sec-head">
          <span>Jours</span>
          <button class="toggle-all" data-rep-toggle-all="days">Tout / Aucun</button>
        </div>
        <div class="checklist" id="dayList" style="max-height:180px">
          <label class="check-item"><input type="checkbox" data-day="0" checked><span class="ci-text">Lundi <small>04 mai 2026</small></span><span class="ci-tag">L</span></label>
          <label class="check-item"><input type="checkbox" data-day="1" checked><span class="ci-text">Mardi <small>05 mai 2026</small></span><span class="ci-tag">M</span></label>
          <label class="check-item"><input type="checkbox" data-day="2" checked><span class="ci-text">Mercredi <small>06 mai 2026</small></span><span class="ci-tag">M</span></label>
          <label class="check-item"><input type="checkbox" data-day="3" checked><span class="ci-text">Jeudi <small>07 mai 2026</small></span><span class="ci-tag">J</span></label>
          <label class="check-item"><input type="checkbox" data-day="4" checked><span class="ci-text">Vendredi <small>08 mai 2026</small></span><span class="ci-tag">V</span></label>
          <label class="check-item weekend"><input type="checkbox" data-day="5" checked><span class="ci-text">Samedi <small>09 mai 2026</small></span><span class="ci-tag">S</span></label>
          <label class="check-item weekend"><input type="checkbox" data-day="6" checked><span class="ci-text">Dimanche <small>10 mai 2026</small></span><span class="ci-tag">D</span></label>
        </div>

        <div class="sec-head" style="margin-top:14px">
          <span>Format</span>
        </div>
        <div class="format-pick">
          <button class="format-btn on" data-fmt="png">
            <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
            <div class="info"><span class="t">PNG</span><span class="d">Haute qualité</span></div>
          </button>
          <button class="format-btn" data-fmt="jpeg">
            <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
            <div class="info"><span class="t">JPEG</span><span class="d">Plus léger</span></div>
          </button>
        </div>
      </div>

    </div>

    <!-- Récap -->
    <div class="export-recap">
      <div class="stat"><span class="k">Images</span><span class="v" id="recapCount">0</span></div>
      <span class="arrow">→</span>
      <div class="filename" id="recapFilename">—</div>
    </div>

    <div class="export-foot">
      <button class="btn" data-rep-export-cancel>Annuler</button>
      <button class="btn btn-primary" id="btnLaunchExport">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Lancer l'export
      </button>
    </div>
  </div>
</div>


<!-- ============================================== -->
<div class="export-progress" id="exportProgress">
  <div class="export-progress-card" id="exportCard">
    <h3>
      <span class="spin"></span>
      <span id="exportTitle">Export en cours…</span>
    </h3>
    <div class="current" id="exportCurrent">Préparation…</div>
    <div class="bar-wrap">
      <div class="bar-fill" id="exportBar"></div>
    </div>
    <div class="stats">
      <span><strong id="exportDone">0</strong> / <strong id="exportTotal">0</strong> images générées</span>
      <span id="exportPct">0%</span>
    </div>
    <div class="done-msg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
      <span>Export terminé · les fichiers ont été téléchargés.</span>
    </div>
    <button class="close-btn">Fermer</button>
  </div>
</div>

<!-- Zone hors-écran pour la capture -->
<div class="capture-stage" id="captureStage" aria-hidden="true"></div>

<!-- ============================================== -->
<!-- ============================================== -->
<div class="modal-overlay" id="modal">
  <div class="modal" id="modalContent" role="dialog" aria-labelledby="modal-title">

    <!-- HERO -->
    <div class="modal-hero">
      <div class="modal-hero-top">
        <div class="modal-hero-id">
          <div class="modal-avatar" id="modal-avatar">CB</div>
          <div class="modal-id-text">
            <div class="label">Édition d'un poste</div>
            <h3 id="modal-name">Camille Bovey</h3>
            <div class="role">Infirmière dipl. ES · 80%</div>
          </div>
        </div>
        <button class="modal-close" title="Fermer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="modal-hero-meta">
        <div class="m">
          <div class="ic">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
          <div class="txt">
            <span class="k">Date</span>
            <span class="v" id="modal-date">2026-05-05</span>
          </div>
        </div>
        <div class="m">
          <div class="ic">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
          </div>
          <div class="txt">
            <span class="k">Module</span>
            <span class="v">M1 · Étages 1, 2</span>
          </div>
        </div>
        <div class="m">
          <div class="ic">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          </div>
          <div class="txt">
            <span class="k">Poste</span>
            <span class="v">N° 1</span>
          </div>
        </div>
      </div>
    </div>

    <!-- BODY -->
    <div class="modal-body">

      <!-- STATUT -->
      <div class="modal-section-title">Statut</div>
      <div class="status-pick">
        <button class="status-btn present on">
          <div class="ic">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <div class="info">
            <span class="t">Présent·e</span>
            <span class="d">Affecter à un poste</span>
          </div>
        </button>
        <button class="status-btn absent">
          <div class="ic">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93 19.07 19.07"/></svg>
          </div>
          <div class="info">
            <span class="t">Absent·e</span>
            <span class="d">Marquer une absence</span>
          </div>
        </button>
      </div>

      <!-- Motif d'absence (visible si absent) -->
      <div class="absent-reasons">
        <div class="lbl">Motif de l'absence</div>
        <div class="reasons">
          <button class="reason-chip">Maladie</button>
          <button class="reason-chip">Accident</button>
          <button class="reason-chip">Enfant malade</button>
          <button class="reason-chip">Vacances</button>
          <button class="reason-chip">Formation</button>
          <button class="reason-chip">Congé spécial</button>
          <button class="reason-chip">Autre</button>
        </div>
      </div>

      <!-- HORAIRE -->
      <div class="modal-section-title">Horaire</div>
      <div class="shift-grid">
        <button class="shift-opt"><span class="shift a2">A2</span><span class="time">07:00→16:00</span></button>
        <button class="shift-opt"><span class="shift a3">A3</span><span class="time">08:00→16:30</span></button>
        <button class="shift-opt"><span class="shift c1">C1</span><span class="time">08:30→15:30</span></button>
        <button class="shift-opt on"><span class="shift c2">C2</span><span class="time">12:00→20:30</span></button>
        <button class="shift-opt"><span class="shift d1">D1</span><span class="time">07:00→15:30</span></button>
        <button class="shift-opt"><span class="shift d3">D3</span><span class="time">07:00→20:30</span></button>
        <button class="shift-opt"><span class="shift d4">D4</span><span class="time">07:00→19:00</span></button>
        <button class="shift-opt"><span class="shift s3">S3</span><span class="time">13:00→20:30</span></button>
        <button class="shift-opt"><span class="shift s4">S4</span><span class="time">14:00→20:30</span></button>
        <button class="shift-opt"><span class="shift n1">N1</span><span class="time">20:15→07:15</span></button>
        <button class="shift-opt"><span class="shift piquet" style="font-size:9px">PIQ.</span><span class="time">24/24</span></button>
        <button class="shift-opt" style="border-style:dashed"><span class="shift" style="background:transparent;color:var(--muted);border-color:var(--line-2)">—</span><span class="time">aucun</span></button>
      </div>

      <!-- AFFECTATION -->
      <div class="modal-section-title">Affectation</div>
      <div class="field-row">
        <div class="field">
          <label>Module <span class="req">*</span></label>
          <select class="select">
            <option>—</option>
            <option selected>M1 — Étages 1, 2</option>
            <option>M2 — Étages 3, 4</option>
            <option>M3 — Étages 5, 6</option>
            <option>M4 — Étage 7</option>
            <option>POOL — Pool (remplacements)</option>
            <option>NUIT — Équipe de nuit</option>
          </select>
        </div>
        <div class="field">
          <label>Étage / Groupe</label>
          <select class="select">
            <option>—</option>
            <option selected>E1-1-A</option>
            <option>E1-1-B</option>
            <option>E2-2-A</option>
            <option>E2-2-B</option>
          </select>
        </div>
      </div>

      <!-- COMMENTAIRE -->
      <div class="field">
        <label>Commentaire (optionnel)</label>
        <input class="input" placeholder="Note interne, consigne particulière…">
      </div>

    </div>

    <!-- FOOT -->
    <div class="modal-foot">
      <div class="modal-foot-left">
        <button class="btn-icon" title="Affecter au pool">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M3 21c0-3.5 3-6 6-6s6 2.5 6 6"/></svg>
        </button>
        <button class="btn-icon" title="Dupliquer sur d'autres jours">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
        <button class="btn-icon danger" title="Supprimer le poste">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
      </div>
      <div class="modal-foot-right">
        <button class="btn" data-rep-modal-action="cancel">Annuler</button>
        <button class="btn btn-primary" data-rep-modal-action="validate">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg>
          Valider
        </button>
      </div>
    </div>
  </div>
</div>
</div>

<script<?= nonce() ?> src="/newspocspace/assets/js/vendor/html2canvas.min.js"></script>
<script<?= nonce() ?> src="/newspocspace/assets/js/vendor/jszip.min.js"></script>
<script<?= nonce() ?>>
(function(){
  const app = document.getElementById('app');

  // ===== SIDEBAR =====
  function toggleSidebar(){
    app.classList.toggle('sb-collapsed');
  }

  // ===== VUE Semaine / Jour =====
  function setView(v){
    if(v === 'day') app.classList.add('view-day'); else app.classList.remove('view-day');
    document.querySelectorAll('#viewToggle button').forEach(b => b.classList.toggle('on', b.dataset.view === v));
  }

  // ===== FILTRE MODULES =====
  function filterModule(btn){
    document.querySelectorAll('.mf-chip').forEach(c => c.classList.remove('on'));
    btn.classList.add('on');
    const mod = btn.dataset.mod;
    document.querySelectorAll('.module').forEach(m => {
      if(mod === 'all' || m.dataset.mod === mod) m.classList.remove('hidden');
      else m.classList.add('hidden');
    });
  }

  // ===== MODE ÉDITION =====
  function toggleEdit(){
    app.classList.toggle('editing');
    const isEditing = app.classList.contains('editing');
    document.getElementById('btnEdit').classList.toggle('btn-warn', isEditing);
    document.getElementById('editLabel').textContent = isEditing ? 'En édition' : 'Éditer';
  }

  // ===== MODAL =====
  function openModalFromCell(cell){
    if(cell.classList.contains('empty')) return;
    const name = cell.dataset.name || 'Collaborateur';
    const date = cell.dataset.date || '';
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-date').textContent = date || '—';
    // initiales pour l'avatar
    const initials = name.split(' ').filter(Boolean).slice(0,2).map(s => s[0].toUpperCase()).join('');
    document.getElementById('modal-avatar').textContent = initials || '?';
    // si la cellule est absente, on présélectionne le statut absent
    const modal = document.getElementById('modalContent');
    if(cell.classList.contains('absent')){
      modal.classList.add('is-absent');
      document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('on'));
      document.querySelector('.status-btn.absent').classList.add('on');
    } else {
      modal.classList.remove('is-absent');
      document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('on'));
      document.querySelector('.status-btn.present').classList.add('on');
    }
    document.getElementById('modal').classList.add('show');
  }
  function closeModal(){ document.getElementById('modal').classList.remove('show'); }

  function setStatus(btn, status){
    document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    document.getElementById('modalContent').classList.toggle('is-absent', status === 'absent');
  }

  function pickShift(btn){
    document.querySelectorAll('.shift-opt').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
  }

  function pickReason(btn){
    document.querySelectorAll('.reason-chip').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
  }

  document.getElementById('modal').addEventListener('click', e => { if(e.target === e.currentTarget) closeModal(); });
  document.addEventListener('keydown', e => { if(e.key === 'Escape') { closeModal(); closeExportMenu(); } });

  // ============================================================
  // EXPORT — modal de sélection + html2canvas + JSZip
  // ============================================================
  const DAYS = [
    { idx:0, name:'Lundi',    date:'04', month:'05', year:'2026' },
    { idx:1, name:'Mardi',    date:'05', month:'05', year:'2026' },
    { idx:2, name:'Mercredi', date:'06', month:'05', year:'2026' },
    { idx:3, name:'Jeudi',    date:'07', month:'05', year:'2026' },
    { idx:4, name:'Vendredi', date:'08', month:'05', year:'2026' },
    { idx:5, name:'Samedi',   date:'09', month:'05', year:'2026' },
    { idx:6, name:'Dimanche', date:'10', month:'05', year:'2026' },
  ];

  // Métadonnées des modules
  const MOD_META = {
    'rj':   { code:'RJ',   label:'RJ-RJN_Responsables',  full:'RJ / RJN — Responsables',   bg1:'#164a42', bg2:'#1f6359' },
    'm1':   { code:'M1',   label:'Module1_Etages_1-2',   full:'Module 1 — Étages 1, 2',   bg1:'#1f6359', bg2:'#2d8074' },
    'm2':   { code:'M2',   label:'Module2_Etages_3-4',   full:'Module 2 — Étages 3, 4',   bg1:'#2d4a6b', bg2:'#456b8e' },
    'm3':   { code:'M3',   label:'Module3_Etages_5-6',   full:'Module 3 — Étages 5, 6',   bg1:'#8a5a1a', bg2:'#b07a35' },
    'm4':   { code:'M4',   label:'Module4_Etage_7',      full:'Module 4 — Étage 7',       bg1:'#5e3a78', bg2:'#7d5896' },
    'pool': { code:'POOL', label:'Pool_Remplacements',   full:'Pool — Remplacements',     bg1:'#8a3a30', bg2:'#a85850' },
    'na':   { code:'NA',   label:'Pool_Non_assigne',     full:'Pool / Non assigné',       bg1:'#4a6661', bg2:'#6b8783' },
  };

  // Couleurs des badges horaires (pour le rendu inline)
  const SHIFT_STYLES = {
    'a2': { bg:'#d2e7e2', fg:'#164a42' }, 'a3': { bg:'#d2e7e2', fg:'#164a42' },
    'c1': { bg:'#a8d1c8', fg:'#0d2a26' }, 'c2': { bg:'#a8d1c8', fg:'#0d2a26' },
    'd1': { bg:'#e2ecf2', fg:'#3a6a8a' },
    'd3': { bg:'#fbf0e1', fg:'#8a5a1a' },
    'd4': { bg:'#fde8e6', fg:'#8a3a30' },
    's3': { bg:'#f0e8f5', fg:'#5e3a78' }, 's4': { bg:'#f0e8f5', fg:'#5e3a78' },
    'n1': { bg:'#0d2a26', fg:'#a8e6c9' },
    'piquet': { bg:'#e6ecf2', fg:'#2d4a6b' },
  };

  // ===== OUVERTURE / FERMETURE DROPDOWN =====
  function toggleExportMenu(e){
    e && e.stopPropagation();
    document.getElementById('exportMenu').classList.toggle('show');
  }
  function closeExportMenu(){
    document.getElementById('exportMenu').classList.remove('show');
  }
  document.addEventListener('click', e => {
    const w = document.querySelector('.export-wrap');
    if(w && !w.contains(e.target)) closeExportMenu();
  });

  // ===== AIGUILLAGE selon le type choisi =====
  function chooseExportType(type){
    closeExportMenu();
    if(type === 'image'){
      // Ouvre le modal de sélection (PNG/JPEG choisi à l'intérieur)
      openExportModal();
    } else if(type === 'pdf'){
      alert('Export PDF — fonctionnalité à venir.\nLa semaine complète sera générée en un seul fichier prêt à imprimer.');
    } else if(type === 'excel'){
      alert('Export Excel (.xlsx) — fonctionnalité à venir.\nLa répartition sera exportée en tableau pour analyse.');
    }
  }

  // ===== OUVERTURE / FERMETURE MODAL =====
  function openExportModal(){
    document.getElementById('exportModal').classList.add('show');
    updateRecap();
  }
  function closeExportModal(){
    document.getElementById('exportModal').classList.remove('show');
  }

  // ===== Toggle "Tout / Aucun" =====
  function toggleAllModules(){
    const items = document.querySelectorAll('#moduleList input[type="checkbox"]');
    const allChecked = Array.from(items).every(c => c.checked);
    items.forEach(c => c.checked = !allChecked);
    updateRecap();
  }
  function toggleAllDays(){
    const items = document.querySelectorAll('#dayList input[type="checkbox"]');
    const allChecked = Array.from(items).every(c => c.checked);
    items.forEach(c => c.checked = !allChecked);
    updateRecap();
  }

  // ===== Format =====
  let exportFormat = 'png';
  function pickFormat(btn){
    document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    exportFormat = btn.dataset.fmt;
    updateRecap();
  }

  // ===== Récap dynamique =====
  function getSelectedModules(){
    return Array.from(document.querySelectorAll('#moduleList input[type="checkbox"]:checked')).map(c => c.dataset.mod);
  }
  function getSelectedDayIndices(){
    return Array.from(document.querySelectorAll('#dayList input[type="checkbox"]:checked')).map(c => parseInt(c.dataset.day, 10));
  }

  function updateRecap(){
    const mods = getSelectedModules();
    const days = getSelectedDayIndices();
    const total = mods.length * days.length;
    document.getElementById('recapCount').textContent = total;

    const filenameEl = document.getElementById('recapFilename');
    const btn = document.getElementById('btnLaunchExport');
    const ext = exportFormat === 'jpeg' ? 'jpg' : 'png';

    if(total === 0){
      filenameEl.innerHTML = '<span style="color:var(--muted-2)">Aucun fichier — sélectionnez au moins un module et un jour</span>';
      btn.disabled = true;
      btn.style.opacity = .5;
      btn.style.cursor = 'not-allowed';
    } else if(total === 1){
      const m = MOD_META[mods[0]];
      const d = DAYS[days[0]];
      filenameEl.innerHTML = `<strong>1 fichier:</strong> ${m.label}_${d.name}_${d.date}_${d.year}.${ext}`;
      btn.disabled = false;
      btn.style.opacity = '';
      btn.style.cursor = '';
    } else {
      filenameEl.innerHTML = `<span class="pkg">📦</span> <strong>Spocspace_Repartition_S19.zip</strong> · ${total} fichier${total>1?'s':''} ${ext.toUpperCase()}`;
      btn.disabled = false;
      btn.style.opacity = '';
      btn.style.cursor = '';
    }
  }

  // Listener sur les checkboxes
  document.addEventListener('change', e => {
    if(e.target.matches('#moduleList input, #dayList input')) updateRecap();
  });

  // ===== Récupération des données d'un module pour un jour =====
  function getModuleDataForDay(modKey, day){
    const moduleEl = document.querySelector(`.week-view .module[data-mod="${modKey}"]`);
    if(!moduleEl) return [];

    const rows = [];
    const tbodyRows = moduleEl.querySelectorAll('tbody tr');
    let currentFonction = null;

    tbodyRows.forEach(tr => {
      const fonctionTd = tr.querySelector('td.col-fonction');
      if(fonctionTd){
        const lab = fonctionTd.querySelector('.label');
        currentFonction = lab ? lab.childNodes[0].textContent.trim() : fonctionTd.textContent.trim().split('\n')[0];
      }
      const posteTd = tr.querySelector('td.col-poste');
      if(!posteTd) return;
      const poste = posteTd.textContent.trim();

      const tds = tr.querySelectorAll('td');
      const dayCells = Array.from(tds).filter(t => !t.classList.contains('col-fonction') && !t.classList.contains('col-poste'));
      const targetTd = dayCells[day.idx];
      if(!targetTd) return;
      const cell = targetTd.querySelector('.cell');
      if(!cell || cell.classList.contains('empty')) return;

      const name = cell.dataset.name || '';
      const horaire = cell.querySelector('.shift');
      const horaireCode = horaire ? horaire.textContent.trim() : '';
      const horaireClass = horaire ? Array.from(horaire.classList).find(c => c !== 'shift' && c !== 'horaire') : '';
      const etage = cell.querySelector('.cell-etage');
      const etageText = etage ? etage.textContent.trim() : '—';
      const isAbsent = cell.classList.contains('absent');

      rows.push({ fonction: currentFonction || '—', poste, name, horaireCode, horaireClass, etageText, isAbsent });
    });

    return rows;
  }

  // ===== Construction du HTML de capture =====
  function monthName(mm){
    const months = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    return months[parseInt(mm,10)] || mm;
  }

  function shiftBadge(code, klass){
    if(!code || code === '—') return '';
    const s = SHIFT_STYLES[klass] || { bg:'#e3ebe8', fg:'#324e4a' };
    const isLong = code.length > 3;
    return `<span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:24px;padding:0 9px;background:${s.bg};color:${s.fg};font-family:'JetBrains Mono',monospace;font-size:${isLong?'9.5':'11'}px;font-weight:700;border-radius:5px;letter-spacing:.02em;border:1px solid rgba(0,0,0,.06)">${code}</span>`;
  }

  function buildCaptureCard(modKey, day, rows){
    const meta = MOD_META[modKey];
    const present = rows.filter(r => !r.isAbsent).length;
    const absent = rows.filter(r => r.isAbsent).length;

    const rowHTML = rows.map(i => {
      const bg = i.isAbsent ? '#f7e3e0' : '#ffffff';
      const nameColor = i.isAbsent ? '#b8443a' : '#0d2a26';
      const nameWeight = i.isAbsent ? '600' : '500';
      const nameDeco = i.isAbsent ? 'text-decoration:line-through;text-decoration-color:rgba(184,68,58,.4);text-decoration-thickness:1px;' : '';
      const etageBg = i.isAbsent ? '#f0d4cf' : '#f3f6f5';
      const etageColor = i.isAbsent ? '#b8443a' : '#6b8783';
      const etageText = i.isAbsent ? 'Absent·e' : i.etageText;

      return `<tr style="border-bottom:1px solid #e3ebe8">
        <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#0d2a26;border-right:1px solid #e3ebe8;background:#fafbfa;width:170px">${i.fonction}</td>
        <td style="padding:10px 8px;font-family:'JetBrains Mono',monospace;font-size:11.5px;color:#6b8783;font-weight:600;border-right:1px solid #e3ebe8;text-align:center;width:60px">${i.poste}</td>
        <td style="padding:10px 14px;background:${bg};border-right:1px solid #e3ebe8">
          <div style="display:flex;align-items:center;gap:10px">
            ${i.isAbsent ? '<span style="width:18px;height:18px;border-radius:50%;background:#b8443a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;line-height:1">!</span>' : ''}
            <span style="flex:1;font-size:13.5px;color:${nameColor};font-weight:${nameWeight};${nameDeco}">${i.name}</span>
            ${shiftBadge(i.horaireCode, i.horaireClass)}
          </div>
        </td>
        <td style="padding:10px 8px;font-family:'JetBrains Mono',monospace;font-size:11px;color:${etageColor};font-weight:600;text-align:center;background:${etageBg};width:110px">${etageText}</td>
      </tr>`;
    }).join('');

    const emptyState = rows.length === 0
      ? `<tr><td colspan="4" style="padding:50px;text-align:center;color:#6b8783;font-size:13px;font-style:italic;background:#fafbfa">Aucune affectation pour ce jour</td></tr>`
      : '';

    return `
      <div style="background:#fff;border-radius:14px;overflow:hidden;font-family:'Outfit',-apple-system,sans-serif;color:#0d2a26;width:1192px;border:1px solid #e3ebe8">

        <!-- HEADER -->
        <div style="background:linear-gradient(135deg,${meta.bg1} 0%,${meta.bg2} 100%);padding:22px 26px;color:#fff;position:relative">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:20px">
            <div style="display:flex;align-items:center;gap:14px">
              <div style="width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,#3da896,#7dd3a8);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',Georgia,serif;font-weight:700;color:#0d2a26;font-size:22px">S</div>
              <div>
                <div style="font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:3px">Spocspace · Répartition</div>
                <div style="font-family:'Fraunces',Georgia,serif;font-size:23px;font-weight:600;letter-spacing:-.01em;line-height:1.1">${meta.full}</div>
              </div>
            </div>
            <div style="text-align:right">
              <div style="font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:#a8e6c9;font-weight:600;margin-bottom:3px">${day.name}</div>
              <div style="font-family:'Fraunces',Georgia,serif;font-size:24px;font-weight:600;letter-spacing:-.01em;line-height:1.1">${day.date} ${monthName(day.month)} ${day.year}</div>
            </div>
          </div>
          <div style="display:flex;gap:30px;margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.18)">
            <div>
              <div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Postes</div>
              <div style="font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:600">${rows.length}</div>
            </div>
            <div>
              <div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Présent·es</div>
              <div style="font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:600">${present}</div>
            </div>
            <div>
              <div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Absent·es</div>
              <div style="font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:600;color:${absent>0?'#fbb6ad':'#fff'}">${absent}</div>
            </div>
            <div style="margin-left:auto;text-align:right">
              <div style="font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#a8c4be;font-weight:600;margin-bottom:2px">Module</div>
              <div style="font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:600">${meta.code}</div>
            </div>
          </div>
        </div>

        <!-- TABLE -->
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:#fafbfa;border-bottom:1px solid #e3ebe8">
              <th style="padding:11px 14px;text-align:left;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8;width:170px">Fonction</th>
              <th style="padding:11px 8px;text-align:center;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8;width:60px">Poste</th>
              <th style="padding:11px 14px;text-align:left;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;border-right:1px solid #e3ebe8">Collaborateur · Horaire</th>
              <th style="padding:11px 8px;text-align:center;font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;color:#6b8783;font-weight:700;width:110px">Étage</th>
            </tr>
          </thead>
          <tbody>${rowHTML}${emptyState}</tbody>
        </table>

        <!-- FOOTER -->
        <div style="padding:13px 26px;background:#fafbfa;border-top:1px solid #e3ebe8;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#6b8783">
          <div style="display:flex;align-items:center;gap:8px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b8783" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
            <span>Résidence Les Tilleuls · Plan-les-Ouates</span>
          </div>
          <div style="font-family:'JetBrains Mono',monospace">Généré le ${new Date().toLocaleDateString('fr-CH')} à ${new Date().toLocaleTimeString('fr-CH',{hour:'2-digit',minute:'2-digit'})}</div>
        </div>
      </div>`;
  }

  // ===== Génération d'un canvas pour 1 tâche =====
  async function captureOne(modKey, day){
    const stage = document.getElementById('captureStage');
    const rows = getModuleDataForDay(modKey, day);
    stage.innerHTML = buildCaptureCard(modKey, day, rows);
    await new Promise(r => setTimeout(r, 80));

    const canvas = await html2canvas(stage.firstElementChild, {
      backgroundColor: exportFormat === 'jpeg' ? '#ffffff' : null,
      scale: 2,
      useCORS: true,
      logging: false,
    });
    return canvas;
  }

  // ===== Téléchargement direct =====
  function downloadDataURL(dataURL, filename){
    const a = document.createElement('a');
    a.href = dataURL;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  function canvasToBlob(canvas, fmt){
    return new Promise(resolve => {
      canvas.toBlob(blob => resolve(blob), `image/${fmt}`, fmt === 'jpeg' ? 0.95 : 1.0);
    });
  }

  // ===== LANCEMENT DE L'EXPORT =====
  async function launchExport(){
    const mods = getSelectedModules();
    const dayIndices = getSelectedDayIndices();
    if(mods.length === 0 || dayIndices.length === 0) return;

    const fmt = exportFormat === 'jpeg' ? 'jpeg' : 'png';
    const ext = fmt === 'jpeg' ? 'jpg' : 'png';
    const days = dayIndices.map(i => DAYS[i]);

    const tasks = [];
    mods.forEach(modKey => days.forEach(day => tasks.push({ modKey, day })));

    closeExportModal();

    // Affichage de la progression
    const overlay = document.getElementById('exportProgress');
    const card = document.getElementById('exportCard');
    const bar = document.getElementById('exportBar');
    const current = document.getElementById('exportCurrent');
    const doneEl = document.getElementById('exportDone');
    const totalEl = document.getElementById('exportTotal');
    const pctEl = document.getElementById('exportPct');
    const titleEl = document.getElementById('exportTitle');
    const stage = document.getElementById('captureStage');

    card.classList.remove('done');
    overlay.classList.add('show');
    totalEl.textContent = tasks.length;
    doneEl.textContent = '0';
    bar.style.width = '0%';
    pctEl.textContent = '0%';
    titleEl.textContent = tasks.length === 1 ? 'Génération de l\'image…' : `Génération de ${tasks.length} images…`;

    // === CAS 1 : 1 seul fichier → téléchargement direct ===
    if(tasks.length === 1){
      const { modKey, day } = tasks[0];
      const meta = MOD_META[modKey];
      const filename = `${meta.label}_${day.name}_${day.date}_${day.year}.${ext}`;
      current.textContent = filename;

      try{
        const canvas = await captureOne(modKey, day);
        const dataURL = canvas.toDataURL(`image/${fmt}`, fmt === 'jpeg' ? 0.95 : 1.0);
        downloadDataURL(dataURL, filename);
      } catch(err){
        console.error(err);
        alert('Erreur lors de la génération.');
      }

      stage.innerHTML = '';
      doneEl.textContent = '1';
      bar.style.width = '100%';
      pctEl.textContent = '100%';
      card.classList.add('done');
      titleEl.textContent = 'Export terminé';
      current.textContent = filename;
      return;
    }

    // === CAS 2 : plusieurs fichiers → ZIP ===
    const zip = new JSZip();
    const folder = zip.folder('Spocspace_Repartition_S19');

    let done = 0;
    for(const task of tasks){
      const { modKey, day } = task;
      const meta = MOD_META[modKey];
      const filename = `${meta.label}_${day.name}_${day.date}_${day.year}.${ext}`;
      current.textContent = filename;

      try{
        const canvas = await captureOne(modKey, day);
        const blob = await canvasToBlob(canvas, fmt);
        folder.file(filename, blob);
      } catch(err){
        console.error('Erreur ' + filename, err);
      }

      done++;
      doneEl.textContent = done;
      const pct = Math.round((done / tasks.length) * 100);
      bar.style.width = pct + '%';
      pctEl.textContent = pct + '%';
      await new Promise(r => setTimeout(r, 30));
    }

    // Génération du ZIP
    titleEl.textContent = 'Compression du ZIP…';
    current.textContent = 'Spocspace_Repartition_S19.zip';
    const zipBlob = await zip.generateAsync({ type:'blob', compression:'DEFLATE', compressionOptions:{ level:6 }});
    const zipURL = URL.createObjectURL(zipBlob);
    downloadDataURL(zipURL, 'Spocspace_Repartition_S19.zip');
    setTimeout(() => URL.revokeObjectURL(zipURL), 5000);

    stage.innerHTML = '';
    card.classList.add('done');
    titleEl.textContent = 'Export terminé';
    current.textContent = `Spocspace_Repartition_S19.zip · ${tasks.length} fichiers`;
  }

  function closeExportProgress(){
    document.getElementById('exportProgress').classList.remove('show');
  }

  // Fermer les modals au clic dehors
  document.getElementById('exportModal').addEventListener('click', e => {
    if(e.target === e.currentTarget) closeExportModal();
  });

  // ===== CELL HANDLER : modal en mode lecture, drag&drop en mode édition =====
  let draggedCell = null;

  document.querySelectorAll('.cell').forEach(cell => {
    cell.addEventListener('click', e => {
      // En mode lecture : clic = ouvre modal
      // En mode édition : clic = ouvre aussi modal (édition)
      // Le drag-drop a son propre événement et n'interfère pas avec le clic simple
      openModalFromCell(cell);
    });

    // Drag & drop — actif seulement si mode édition
    cell.addEventListener('mousedown', e => {
      if(!app.classList.contains('editing')) return;
      if(cell.classList.contains('empty')) return;
      cell.draggable = true;
    });

    cell.addEventListener('dragstart', e => {
      if(!app.classList.contains('editing')){ e.preventDefault(); return; }
      draggedCell = cell;
      cell.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', cell.dataset.name || '');
    });

    cell.addEventListener('dragend', () => {
      cell.classList.remove('dragging');
      cell.draggable = false;
      document.querySelectorAll('.drop-target').forEach(c => c.classList.remove('drop-target'));
    });

    cell.addEventListener('dragover', e => {
      if(!app.classList.contains('editing') || !draggedCell || draggedCell === cell) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      cell.classList.add('drop-target');
    });

    cell.addEventListener('dragleave', () => {
      cell.classList.remove('drop-target');
    });

    cell.addEventListener('drop', e => {
      e.preventDefault();
      cell.classList.remove('drop-target');
      if(!draggedCell || draggedCell === cell) return;

      // Échange du contenu HTML entre les deux cellules
      const tmpHTML = cell.innerHTML;
      const tmpClass = cell.className;
      const tmpName = cell.dataset.name;

      cell.innerHTML = draggedCell.innerHTML;
      cell.className = draggedCell.className.replace('dragging','').trim();
      cell.dataset.name = draggedCell.dataset.name || '';

      draggedCell.innerHTML = tmpHTML;
      draggedCell.className = tmpClass.replace('dragging','').trim();
      draggedCell.dataset.name = tmpName || '';

      draggedCell = null;
    });
  });


  // ===== EVENT BINDINGS (CSP-safe — remplace les onclick="" du mockup) =====
  function on(sel, ev, fn){ document.querySelectorAll(sel).forEach(el => el.addEventListener(ev, fn)); }

  on('.mf-chip', 'click', e => filterModule(e.currentTarget));
  on('#viewToggle button[data-view]', 'click', e => setView(e.currentTarget.dataset.view));
  on('#btnEdit', 'click', toggleEdit);
  on('[data-rep-edit-quit]', 'click', toggleEdit);
  on('#btnExport', 'click', e => toggleExportMenu(e));
  on('[data-rep-export-type]', 'click', e => chooseExportType(e.currentTarget.dataset.repExportType));
  on('[data-rep-toggle-all="modules"]', 'click', toggleAllModules);
  on('[data-rep-toggle-all="days"]', 'click', toggleAllDays);
  on('.format-btn', 'click', e => pickFormat(e.currentTarget));
  on('#btnLaunchExport', 'click', launchExport);
  on('[data-rep-export-cancel]', 'click', closeExportModal);
  on('.export-progress .close-btn', 'click', closeExportProgress);
  on('.status-btn.present', 'click', e => setStatus(e.currentTarget, 'present'));
  on('.status-btn.absent', 'click', e => setStatus(e.currentTarget, 'absent'));
  on('.shift-opt', 'click', e => pickShift(e.currentTarget));
  on('.reason-chip', 'click', e => pickReason(e.currentTarget));
  on('[data-rep-modal-action]', 'click', closeModal);
  // X-buttons (modal-close) close their parent overlay
  on('.modal-close', 'click', e => {
    const overlay = e.currentTarget.closest('.modal-overlay');
    if (overlay) overlay.classList.remove('show');
  });
  // Sidebar toggle (was onclick="toggleSidebar()") — l'admin shell gère sa propre sidebar,
  // mais on garde la fonction au cas où le bouton interne du mockup soit visible.
  on('.sb-toggle', 'click', toggleSidebar);

})();
</script>
