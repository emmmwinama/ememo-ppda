<?php
/**
 * e-Services shared shell. Uses the ememo theme (theme.css) + Farmis .f-*
 * components — no styling borrowed from the legacy PHPRunner app.
 *
 *   es_layout_head('Bid registry', 'registry', 'Intake of PDE submissions');
 *   ... page body ...
 *   es_layout_foot();
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ui.php';

function es_nav_items(): array {
    return [
        ['key' => 'dashboard', 'href' => 'index.php',        'icon' => 'bi-speedometer2', 'label' => 'Dashboard',    'roles' => []],
        ['key' => 'registry',  'href' => 'bid_registry.php', 'icon' => 'bi-journal-plus', 'label' => 'Submissions',   'roles' => ['registry', 'pde', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'allocations', 'href' => 'bid_allocations.php', 'icon' => 'bi-diagram-3', 'label' => 'Allocations', 'roles' => ['allocator', 'dg']],
        ['key' => 'analysis',  'href' => 'bid_analysis.php', 'icon' => 'bi-clipboard-data','label' => 'Bid analysis', 'roles' => ['officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'suppliers', 'href' => 'suppliers.php',    'icon' => 'bi-building',      'label' => 'Suppliers',    'roles' => []],
    ];
}

/** Admin panel sub-navigation (chips). */
function es_admin_nav(string $active): void {
    $items = [
        ['k' => 'users',   'href' => 'admin_users.php',   'label' => 'Users',    'perm' => 'users.manage'],
        ['k' => 'roles',   'href' => 'admin_roles.php',   'label' => 'Roles & permissions', 'perm' => 'rbac.manage'],
        ['k' => 'refdata', 'href' => 'admin_refdata.php', 'label' => 'Reference data', 'perm' => 'refdata.manage'],
        ['k' => 'import',  'href' => 'import_legacy.php', 'label' => 'Legacy import',  'perm' => 'import.run'],
    ];
    echo '<div class="f-chips" style="margin-bottom:1.5rem;">';
    foreach ($items as $it) {
        if (!es_can($it['perm'])) continue;
        $cls = $active === $it['k'] ? 'chip active' : 'chip';
        echo '<a class="' . $cls . '" href="' . e($it['href']) . '">' . e($it['label']) . '</a>';
    }
    echo '</div>';
}

function es_layout_head(string $title, string $active = '', string $subtitle = ''): void {
    $u = current_user();
    $initials = strtoupper(substr($u['full_name'], 0, 1) . (strpos($u['full_name'], ' ') !== false ? substr(strrchr($u['full_name'], ' '), 1, 1) : ''));
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · PPDA e-Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../../assets/css/theme.css" rel="stylesheet">
  <style>
    body { background: var(--bg); }
    .es-shell { display: flex; min-height: 100vh; }
    .es-side {
      width: 216px; flex-shrink: 0;
      background: var(--surface); border-right: 1px solid var(--border);
      display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh;
    }
    .es-brand {
      display: flex; align-items: center; gap: .55rem;
      height: 56px; padding: 0 1rem; border-bottom: 1px solid var(--border);
      font-weight: 800; color: var(--text); text-decoration: none; font-size: .98rem;
    }
    .es-brand .bi { color: var(--brand); font-size: 1.2rem; }
    .es-nav { padding: .6rem 0; flex: 1 1 auto; overflow-y: auto; }
    .es-nav a {
      display: flex; align-items: center; gap: .7rem;
      margin: 1px .5rem; padding: .5rem .7rem; border-radius: var(--radius-md);
      color: var(--text); font-size: .84rem; font-weight: 600; text-decoration: none;
    }
    .es-nav a .bi { font-size: 1.05rem; width: 1.2rem; text-align: center; color: var(--muted); }
    .es-nav a:hover { background: var(--brand-light); }
    .es-nav a.active { background: var(--brand-light); color: var(--brand-dark); }
    .es-nav a.active .bi { color: var(--brand); }
    .es-side-foot { border-top: 1px solid var(--border); padding: .5rem; }
    .es-hub-link { display: flex; align-items: center; gap: .5rem; padding: .5rem .6rem;
      border-radius: var(--radius-md); color: var(--muted); font-size: .8rem; font-weight: 600; text-decoration: none; }
    .es-hub-link:hover { background: var(--bg); color: var(--text); }

    .es-main { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
    .es-topbar {
      height: 56px; flex-shrink: 0;
      display: flex; align-items: center;
      background: var(--surface); border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 1020;
    }
    /* Shared centred column for the top bar and the page body */
    .es-topbar-inner, .es-content {
      width: 100%; max-width: 1180px; margin-inline: auto;
      padding-inline: 1.75rem;
    }
    .es-topbar-inner { display: flex; align-items: center; gap: 1rem; }
    .es-topbar .es-page-name { font-weight: 700; color: var(--text); }
    .es-user { margin-left: auto; }
    .es-user-btn { display: flex; align-items: center; gap: .5rem; background: none; border: 1px solid transparent;
      border-radius: var(--radius-pill); padding: .25rem .6rem .25rem .25rem; color: var(--text); }
    .es-user-btn:hover { background: var(--bg); border-color: var(--border); }
    .es-user-btn .bi-chevron-down { color: var(--muted); }

    /* status / priority / source badges */
    .es-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem;
      border-radius: var(--radius-pill); font-size: .74rem; font-weight: 600; white-space: nowrap; line-height: 1.4; }
    .es-badge .bi { font-size: .8rem; }
    .es-badge-status { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
    .es-badge .es-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .es-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: #fff;
      display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .78rem; }
    .es-user-name { font-size: .85rem; color: var(--text); font-weight: 500; }

    .es-content { padding-block: 1.6rem 2.5rem; }

    /* Windowed pager (es_pager()) */
    .es-pg-info { text-align: center; color: var(--muted); font-size: .82rem; margin-top: 1rem; }
    .es-pg { display: flex; justify-content: center; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
    .es-pg-b {
      min-width: 34px; height: 34px; padding: 0 .6rem;
      display: inline-flex; align-items: center; justify-content: center;
      border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius-sm);
      font-size: .82rem; font-weight: 600; color: var(--muted); text-decoration: none;
    }
    .es-pg-b:hover:not(.is-off):not(.is-cur) { border-color: var(--brand); color: var(--brand); }
    .es-pg-b.is-cur { background: var(--brand); border-color: var(--brand); color: #fff; }
    .es-pg-b.is-off { opacity: .45; cursor: default; }
    .es-pg-gap { align-self: center; color: var(--muted); padding: 0 .2rem; }

    /* ── Modern spacing pass (scoped to e-Services, ememo untouched) ───── */
    .es-content { max-width: 1200px; padding-inline: 2rem; padding-block: 2rem 3rem; }
    .es-topbar-inner { max-width: 1200px; padding-inline: 2rem; }

    .es-shell .f-head { margin-bottom: 1.85rem; }
    .es-shell .f-title { font-size: 1.5rem; letter-spacing: -.01em; }
    .es-shell .f-subtitle { margin-top: .4rem; }

    .es-shell .f-toolbar,
    .es-shell .f-chips { margin-bottom: 1.5rem; gap: .6rem; }

    .es-shell .f-panel,
    .es-shell .f-card { border-radius: 16px; box-shadow: 0 1px 2px rgba(16,24,32,.05), 0 1px 3px rgba(16,24,32,.04); }
    .es-shell .f-card { padding: 1.4rem 1.5rem; }
    .es-shell .f-panel-head { padding: 1.05rem 1.4rem; font-size: .95rem; }
    .es-shell .f-panel-body { padding: 1.4rem; }
    .es-shell .f-panel + .f-panel,
    .es-shell .f-panel + .f-pager,
    .es-shell .row + .f-panel,
    .es-shell .f-panel + .row { margin-top: 1.4rem; }

    /* comfortable table rows + breathing room at the panel edges */
    .es-shell .f-table > :not(caption) > * > * { padding: .8rem .9rem; }
    .es-shell .f-table thead th { padding-top: .95rem; padding-bottom: .95rem; }
    .es-shell .f-table > :not(caption) > * > *:first-child { padding-left: 1.4rem; }
    .es-shell .f-table > :not(caption) > * > *:last-child { padding-right: 1.4rem; }

    .es-shell .f-state { padding: 3.75rem 1rem; }
    .es-shell .form-label { margin-bottom: .35rem; font-size: .84rem; font-weight: 600; }
    .es-shell .form-control, .es-shell .form-select { border-radius: 9px; }
    .es-shell .btn { border-radius: 9px; }
    .es-shell .btn-sm { --bs-btn-padding-y: .34rem; --bs-btn-padding-x: .72rem; }
    .es-shell .pill { padding: .18rem .62rem; }
    .es-shell .kpi-row { gap: 1.1rem !important; margin-bottom: 1.9rem !important; }

    /* ── Right-side slide-over drawer ─────────────────────────────────── */
    .es-drawer { position: fixed; inset: 0; z-index: 1200; }
    .es-drawer[hidden] { display: none; }
    .es-drawer-backdrop {
      position: absolute; inset: 0; background: rgba(15,23,42,.34);
      opacity: 0; transition: opacity .2s ease;
    }
    .es-drawer.is-open .es-drawer-backdrop { opacity: 1; }
    .es-drawer-panel {
      position: absolute; top: 0; right: 0; height: 100%;
      width: min(560px, 100%);
      display: flex; flex-direction: column;
      background: var(--surface); border-left: 1px solid var(--border);
      box-shadow: -12px 0 40px rgba(15,23,42,.16);
      transform: translateX(100%); transition: transform .24s cubic-bezier(.4,0,.2,1);
    }
    .es-drawer.is-open .es-drawer-panel { transform: translateX(0); }
    .es-drawer-head {
      flex-shrink: 0; display: flex; align-items: center; gap: 1rem;
      padding: 1rem 1.4rem; border-bottom: 1px solid var(--border); background: var(--bg);
    }
    .es-drawer-title { font-weight: 800; font-size: 1rem; color: var(--text); }
    .es-drawer-x {
      margin-left: auto; width: 32px; height: 32px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--muted);
    }
    .es-drawer-x:hover { color: var(--text); }
    .es-drawer-body { flex: 1 1 auto; overflow-y: auto; }
    .es-drawer-loading { display: flex; justify-content: center; padding: 4rem 0; color: var(--muted); }
    /* form rendered inside the drawer */
    .es-drawer-form { display: flex; flex-direction: column; min-height: 100%; }
    .es-drawer-fields { padding: 1.4rem 1.4rem 1rem; }
    .es-drawer-foot {
      position: sticky; bottom: 0; margin-top: auto;
      display: flex; justify-content: flex-end; gap: .6rem;
      padding: 1rem 1.4rem; border-top: 1px solid var(--border); background: var(--surface);
    }

    /* ── Blended worklist: bold stat panels + rich list rows ──────────── */
    .f-stats { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.6rem; }
    .f-stat {
      flex: 1 1 200px; position: relative; overflow: hidden;
      border-radius: 16px; padding: 1.15rem 1.35rem; color: #fff;
      background: linear-gradient(135deg, #64748b, #475569);
    }
    .f-stat.s-green { background: linear-gradient(135deg, #2f9e44, #1f6b23); }
    .f-stat.s-sky   { background: linear-gradient(135deg, #0ea5e9, #0369a1); }
    .f-stat.s-amber { background: linear-gradient(135deg, #f59e0b, #b45309); }
    .f-stat.s-rose  { background: linear-gradient(135deg, #f43f5e, #be123c); }
    .f-stat.s-violet{ background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
    .f-stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
    .f-stat-label { font-size: .82rem; font-weight: 600; opacity: .93; margin-top: .45rem; }
    .f-stat > .bi { position: absolute; right: .7rem; top: .55rem; font-size: 2.6rem; opacity: .2; }

    .f-list { border: 1px solid var(--border); border-radius: 16px; background: var(--surface); overflow: hidden;
      box-shadow: 0 1px 2px rgba(16,24,32,.05), 0 1px 3px rgba(16,24,32,.04); }
    .f-listrow { display: flex; gap: 1.1rem; padding: 1.05rem 1.3rem 1.05rem 1.45rem;
      border-top: 1px solid var(--border); position: relative; }
    .f-listrow:first-child { border-top: none; }
    .f-listrow:hover { background: var(--bg); }
    .f-listrow::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
      background: var(--rail, transparent); }
    .f-listrow-main { flex: 1 1 auto; min-width: 0; }
    .f-listrow-title { font-weight: 700; color: var(--text); font-size: .95rem; }
    .f-listrow-sub { font-size: .9rem; color: var(--text); margin-top: .15rem; }
    .f-listrow-meta { font-size: .77rem; color: var(--muted); margin-top: .4rem;
      display: flex; gap: .3rem .85rem; flex-wrap: wrap; align-items: center; }
    .f-listrow-meta .sep { opacity: .4; }
    .f-listrow-note { font-size: .82rem; margin-top: .55rem; padding: .4rem .65rem; border-radius: 8px;
      background: #fdefda; color: #b45309; }
    .f-listrow-note.is-return { background: #fdecee; color: #be123c; }
    .f-listrow-rail { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: .45rem; }
    .f-listrow-actions { flex: 0 0 auto; display: flex; align-items: flex-start; gap: .3rem; }
    .f-count { display: inline-flex; align-items: center; gap: .3rem; font-size: .74rem; font-weight: 600;
      color: var(--muted); }
    .f-count .bi { font-size: .82rem; }
    .f-agechip { font-size: .72rem; color: var(--muted); white-space: nowrap; }

    /* ── Record card (allocations, queues) ───────────────────────────── */
    .rec { padding: 1.15rem 1.3rem; }
    .rec-head { display: flex; align-items: flex-start; gap: .75rem; }
    .rec-headmain { flex: 1 1 auto; min-width: 0; }
    .rec-title { font-weight: 700; font-size: .98rem; color: var(--text); }
    .rec-meta { font-size: .78rem; color: var(--muted); margin-top: .3rem;
      display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .rec-meta .sep { opacity: .4; }
    .rec-badges { flex-shrink: 0; display: flex; gap: .4rem; flex-wrap: wrap; justify-content: flex-end; max-width: 45%; }
    .rec-status { margin-top: .8rem; display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; font-size: .88rem; }
    .rec-status .lbl { color: var(--muted); }
    .rec-status .who { font-weight: 600; color: var(--text); }
    .rec-note { margin-top: .65rem; font-size: .82rem; padding: .45rem .7rem; border-radius: 8px;
      background: #fdefda; color: #b45309; }
    .rec-hist { margin-top: .55rem; font-size: .8rem; }
    .rec-hist > summary { cursor: pointer; color: var(--muted); list-style: none; }
    .rec-hist > summary::-webkit-details-marker { display: none; }
    .rec-hist > summary::before { content: "▸ "; }
    .rec-hist[open] > summary::before { content: "▾ "; }
    .rec-hist-body { margin-top: .4rem; display: flex; flex-direction: column; }
    .rec-hist-item { padding: .4rem 0; border-top: 1px dashed var(--border); }
    .rec-hist-item:first-child { border-top: none; padding-top: 0; }
    .rec-hist-reason { color: var(--muted); margin-top: .1rem; }
    .rec-actions { margin-top: 1rem; padding-top: .95rem; border-top: 1px solid var(--border);
      display: flex; gap: .65rem; align-items: flex-end; flex-wrap: wrap; }
    .rec-actions .fld { flex: 1 1 190px; min-width: 150px; }
    .rec-actions .fld--wide { flex: 2 1 240px; }
    .rec-actions label { font-size: .72rem; font-weight: 600; color: var(--muted);
      display: block; margin-bottom: .28rem; text-transform: uppercase; letter-spacing: .04em; }
    .rec-actions .btn { flex: 0 0 auto; }

    @media (max-width: 720px) {
      .f-listrow { flex-wrap: wrap; }
      .f-listrow-rail { flex-direction: row; flex-wrap: wrap; align-items: center; width: 100%; }
      .rec-head { flex-direction: column; }
      .rec-badges { max-width: 100%; justify-content: flex-start; }
      .rec-actions .btn { width: 100%; }
    }

    @media (max-width: 800px) {
      .es-shell { flex-direction: column; }
      .es-side { width: 100%; height: auto; position: static; flex-direction: row; overflow-x: auto; }
      .es-nav { display: flex; padding: .4rem; }
      .es-side-foot, .es-brand { display: none; }
    }
  </style>
</head>
<body>
<div class="es-shell">
  <aside class="es-side">
    <a class="es-brand" href="index.php"><i class="bi bi-diagram-3-fill"></i><span>PPDA e&#8209;Services</span></a>
    <nav class="es-nav">
      <?php foreach (es_nav_items() as $it):
        if (!empty($it['roles']) && !es_has_role(...$it['roles'])) continue; ?>
        <a href="<?= e($it['href']) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>">
          <i class="bi <?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="es-side-foot">
      <a class="es-hub-link" href="<?= e(ES_HUB_URL) ?>"><i class="bi bi-grid-3x3-gap-fill"></i>Back to Digital Hub</a>
    </div>
  </aside>

  <div class="es-main">
    <header class="es-topbar">
      <div class="es-topbar-inner">
        <span class="es-page-name"><?= e($title) ?></span>
        <div class="dropdown es-user">
          <button type="button" class="es-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="es-avatar"><?= e($initials ?: 'U') ?></span>
            <span class="es-user-name d-none d-md-inline"><?= e($u['full_name']) ?></span>
            <i class="bi bi-chevron-down small"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end mt-2">
            <li><span class="dropdown-item-text small text-muted"><?= e($u['email'] ?: $u['username']) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <?php if (es_can('users.manage') || es_can('rbac.manage') || es_can('refdata.manage') || es_can('import.run')): ?>
              <li><a class="dropdown-item" href="admin_users.php"><i class="bi bi-sliders me-2"></i>Administration</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?= e(ES_HUB_URL) ?>"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Digital Hub</a></li>
            <li><a class="dropdown-item text-danger" href="../../hub/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="es-content">
      <?php if ($subtitle !== ''): ?>
        <div class="f-head"><h1 class="f-title"><?= e($title) ?></h1><p class="f-subtitle"><?= e($subtitle) ?></p></div>
      <?php endif; ?>
      <?php foreach (take_flash() as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($f['msg']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
<?php }

function es_layout_foot(): void { ?>
    </main>
  </div>

  <!-- right-side slide-over -->
  <div class="es-drawer" id="esDrawer" hidden>
    <div class="es-drawer-backdrop" data-drawer-close></div>
    <aside class="es-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="esDrawerTitle">
      <header class="es-drawer-head">
        <span class="es-drawer-title" id="esDrawerTitle">Details</span>
        <button type="button" class="es-drawer-x" data-drawer-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
      </header>
      <div class="es-drawer-body"></div>
    </aside>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var dw = document.getElementById('esDrawer');
  if (!dw) return;
  var body  = dw.querySelector('.es-drawer-body');
  var title = dw.querySelector('.es-drawer-title');

  function open(url, label) {
    title.textContent = label || 'Details';
    body.innerHTML = '<div class="es-drawer-loading"><span class="spinner-border spinner-border-sm"></span></div>';
    dw.hidden = false;
    requestAnimationFrame(function () { dw.classList.add('is-open'); });
    document.body.style.overflow = 'hidden';
    fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.text(); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () { body.innerHTML = '<div class="f-state is-error" style="padding:3rem 1rem;"><p>Could not load the form.</p></div>'; });
  }
  function close() {
    dw.classList.remove('is-open');
    document.body.style.overflow = '';
    setTimeout(function () { dw.hidden = true; body.innerHTML = ''; }, 240);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-drawer]');
    if (t) { e.preventDefault(); open(t.getAttribute('data-drawer'), t.getAttribute('data-drawer-title')); return; }
    if (e.target.closest('[data-drawer-close]')) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dw.hidden) close(); });
})();
</script>
</body>
</html>
<?php }
