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
        ['key' => 'dashboard',   'href' => 'index.php',                    'icon' => 'bi-speedometer2',  'label' => 'Dashboard',          'group' => '',                    'roles' => []],
        ['key' => 'registry',    'href' => 'bid_registry.php',             'icon' => 'bi-journal-plus',  'label' => 'Submissions',        'group' => 'Bid workflow',        'roles' => ['registry', 'pde', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'allocations', 'href' => 'bid_allocations.php',          'icon' => 'bi-diagram-3',     'label' => 'Allocations',        'group' => 'Bid workflow',        'roles' => ['allocator', 'dg']],
        ['key' => 'analysis',    'href' => 'bid_analysis.php',             'icon' => 'bi-clipboard-data','label' => 'Bid analysis',       'group' => 'Reviews & approvals', 'roles' => ['officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'rev_supervisor', 'href' => 'bid_review.php?role=supervisor', 'icon' => 'bi-clipboard-check', 'label' => 'Supervisory review', 'group' => 'Reviews & approvals', 'roles' => ['supervisor']],
        ['key' => 'rev_director',   'href' => 'bid_review.php?role=director',   'icon' => 'bi-person-check',    'label' => 'Director review',    'group' => 'Reviews & approvals', 'roles' => ['director']],
        ['key' => 'rev_dg',         'href' => 'bid_review.php?role=dg',        'icon' => 'bi-award',          'label' => 'DG review',          'group' => 'Reviews & approvals', 'roles' => ['dg']],
        ['key' => 'rev_board',      'href' => 'bid_board.php',                 'icon' => 'bi-people-fill',    'label' => 'Board review',       'group' => 'Reviews & approvals', 'roles' => ['board']],
        ['key' => 'tracking',    'href' => 'bid_tracking.php',             'icon' => 'bi-signpost-split','label' => 'Submission tracking','group' => 'Reviews & approvals', 'roles' => ['registry', 'allocator', 'officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'responses',   'href' => 'bid_responses.php',            'icon' => 'bi-envelope-paper','label' => 'Submission responses','group' => 'Reviews & approvals', 'roles' => ['registry', 'officer', 'supervisor', 'director', 'dg', 'board']],
        ['key' => 'suppliers',   'href' => 'suppliers.php',                'icon' => 'bi-building',      'label' => 'Suppliers',          'group' => 'Registers',           'roles' => []],
        ['key' => 'admin',       'href' => 'admin.php',                    'icon' => 'bi-sliders',      'label' => 'Administration',      'group' => 'System',              'roles' => ['admin']],
    ];
}

/** The administration section's menu — shown in the sidebar while on an admin page. */
function es_admin_nav_items(): array {
    return [
        ['key' => 'users',   'href' => 'admin_users.php',   'icon' => 'bi-people',      'label' => 'Users',              'perm' => 'users.manage'],
        ['key' => 'roles',   'href' => 'admin_roles.php',   'icon' => 'bi-shield-lock', 'label' => 'Roles & permissions', 'perm' => 'rbac.manage'],
        ['key' => 'refdata', 'href' => 'admin_refdata.php', 'icon' => 'bi-table',       'label' => 'Reference data',      'perm' => 'refdata.manage'],
        ['key' => 'import',  'href' => 'import_legacy.php', 'icon' => 'bi-database-down','label' => 'Legacy import',       'perm' => 'import.run'],
    ];
}

/** Admin keys that switch the sidebar into "administration" mode. */
function es_is_admin_section(string $active): bool {
    return in_array($active, ['users', 'roles', 'refdata', 'import'], true);
}

/** Kept for older admin pages that still call it — the sub-nav is now in the sidebar. */
function es_admin_nav(string $active): void {}

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
  <link href="../../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <style>
    body { background: var(--bg); }
    .es-shell { display: flex; min-height: 100vh; }
    .es-side {
      width: 216px; flex-shrink: 0;
      background: var(--surface); border-right: 1px solid var(--border);
      display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh;
    }
    .es-brand-row {
      display: flex; align-items: center; height: 56px; padding: 0 .5rem 0 1rem;
      border-bottom: 1px solid var(--border);
    }
    .es-brand {
      display: flex; align-items: center; gap: .55rem; flex: 1 1 auto; min-width: 0;
      font-weight: 800; color: var(--text); text-decoration: none; font-size: .98rem;
    }
    .es-brand span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .es-brand .bi { color: var(--brand); font-size: 1.2rem; flex-shrink: 0; }
    .es-nav-toggle {
      flex-shrink: 0; width: 30px; height: 30px; border: 1px solid var(--border);
      border-radius: 8px; background: var(--surface); color: var(--muted);
      display: flex; align-items: center; justify-content: center; cursor: pointer;
    }
    .es-nav-toggle:hover { color: var(--text); border-color: var(--muted); }
    .es-nav { padding: .5rem 0 .6rem; flex: 1 1 auto; overflow-y: auto; }
    .es-nav-group {
      font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
      color: var(--muted); padding: .85rem .95rem .3rem;
    }
    .es-nav a {
      display: flex; align-items: center; gap: .7rem;
      margin: 1px .5rem; padding: .5rem .7rem; border-radius: var(--radius-md);
      color: var(--text); font-size: .84rem; font-weight: 600; text-decoration: none;
      white-space: nowrap; overflow: hidden;
    }
    .es-nav a .bi { font-size: 1.05rem; width: 1.2rem; text-align: center; color: var(--muted); flex-shrink: 0; }
    .es-nav a:hover { background: var(--brand-light); }
    .es-nav a.active { background: var(--brand-light); color: var(--brand-dark); }
    .es-nav a.active .bi { color: var(--brand); }
    .es-side-foot { border-top: 1px solid var(--border); padding: .5rem; }

    /* collapsed sidebar (persisted in localStorage) */
    .es-shell.nav-collapsed .es-side { width: 60px; }
    .es-shell.nav-collapsed .es-nav a span,
    .es-shell.nav-collapsed .es-hub-link span,
    .es-shell.nav-collapsed .es-nav-group { display: none; }
    .es-shell.nav-collapsed .es-brand { display: none; }
    .es-shell.nav-collapsed .es-brand-row { padding: 0; justify-content: center; }
    .es-shell.nav-collapsed .es-nav a { justify-content: center; padding: .55rem 0; margin: 1px .35rem; }
    .es-shell.nav-collapsed .es-hub-link { justify-content: center; }
    .es-shell.nav-collapsed .es-nav-toggle i { transform: rotate(180deg); }
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
      width: 100%; max-width: 1560px; margin-inline: auto;
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
    .es-content { max-width: 1560px; padding-inline: 2rem; padding-block: 2rem 3rem; }
    .es-topbar-inner { max-width: 1560px; padding-inline: 2rem; }
    @media (max-width: 1400px) {
      .es-content, .es-topbar-inner { padding-inline: 1.5rem; }
    }

    .es-shell .f-head { margin-bottom: 1.85rem; }
    .es-shell .f-title { font-size: 1.5rem; letter-spacing: -.01em; }
    .es-shell .f-subtitle { margin-top: .4rem; }

    .es-shell .f-toolbar,
    .es-shell .f-chips { margin-bottom: 1.5rem; gap: .6rem; }
    .es-shell .f-chips a,
    .es-shell .chip,
    .es-shell .chip:hover,
    .es-shell .chip:focus,
    .es-shell .chip:active { text-decoration: none !important; }

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

    /* ── Floating toasts (bottom-right) — replaces the top alert bar ──── */
    .es-toasts { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1400;
      display: flex; flex-direction: column; gap: .6rem; width: min(360px, calc(100vw - 2rem)); pointer-events: none; }
    .es-toast { pointer-events: auto; display: flex; align-items: flex-start; gap: .6rem;
      background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--muted);
      border-radius: 12px; padding: .8rem .9rem; box-shadow: 0 10px 34px rgba(15, 23, 42, .18);
      font-size: .86rem; color: var(--text);
      transform: translateX(120%); opacity: 0;
      transition: transform .28s cubic-bezier(.4, 0, .2, 1), opacity .28s ease; }
    .es-toast.is-in { transform: translateX(0); opacity: 1; }
    .es-toast.is-out { transform: translateX(120%); opacity: 0; }
    .es-toast > .bi { font-size: 1rem; flex-shrink: 0; margin-top: .05rem; color: var(--muted); }
    .es-toast .msg { flex: 1 1 auto; line-height: 1.42; word-break: break-word; }
    .es-toast .x { border: 0; background: none; color: var(--muted); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 .1rem; }
    .es-toast .x:hover { color: var(--text); }
    .es-toast.t-success { border-left-color: var(--brand); }
    .es-toast.t-success > .bi { color: var(--brand); }
    .es-toast.t-error { border-left-color: #be123c; }
    .es-toast.t-error > .bi { color: #be123c; }
    @media (max-width: 560px) {
      .es-toasts { right: .75rem; left: .75rem; bottom: .75rem; width: auto; }
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
    .f-agechip b { font-weight: 700; color: var(--text); }

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
    .rec-metrics { margin-top: .7rem; display: flex; gap: .35rem 1rem; flex-wrap: wrap;
      align-items: center; font-size: .76rem; color: var(--muted); }
    .rec-metrics b { font-weight: 700; color: var(--text); }
    .rec-metrics .m-hot b { color: #be123c; }
    .rec-metrics .sep { opacity: .4; }

    /* ── Turnaround circles (tracking board + review worklists) ─────────── */
    .rec-turn { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-top: .7rem; }
    .trk-name { display: flex; align-items: center; gap: .5rem; }
    .trk-item { display: inline-flex; align-items: center; gap: .3rem; }
    .trk-lbl { font-size: .58rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--muted); }
    .trk-dur {
      flex: none; display: inline-flex; align-items: center; justify-content: center;
      width: 2rem; height: 2rem; padding: 0; white-space: nowrap;
      font-size: .62rem; font-weight: 700; line-height: 1; color: var(--text);
      border-radius: 50%; border: 3px solid transparent;
    }
    .trk-dur.t-good { background: #dcfce7; border-color: #4ade80; }
    .trk-dur.t-ok   { background: #fef3c7; border-color: #fbbf24; }
    .trk-dur.t-poor { background: #ffe4e6; border-color: #fb7185; }
    .trk-dur.trk-open { border-style: dashed; }
    .trk-legend { color: var(--muted); }
    .trk-legend .trk-dur { width: 1.15rem; height: 1.15rem; border-width: 3px; vertical-align: middle; margin: 0 .1rem; }
    :root:not([data-theme="light"]) .trk-dur.t-good { background: rgba(34,197,94,.16); border-color: rgba(74,222,128,.7); }
    :root:not([data-theme="light"]) .trk-dur.t-ok   { background: rgba(245,158,11,.16); border-color: rgba(251,191,36,.7); }
    :root:not([data-theme="light"]) .trk-dur.t-poor { background: rgba(244,63,94,.16); border-color: rgba(251,113,133,.7); }

    @media (max-width: 720px) {
      .f-listrow { flex-wrap: wrap; }
      .f-listrow-rail { flex-direction: row; flex-wrap: wrap; align-items: center; width: 100%; }
      .rec-head { flex-direction: column; }
      .rec-badges { max-width: 100%; justify-content: flex-start; }
      .rec-actions .btn { width: 100%; }
    }

    @media (max-width: 800px) {
      .es-shell, .es-shell.nav-collapsed { flex-direction: column; }
      .es-shell.nav-collapsed .es-side,
      .es-side { width: 100%; height: auto; position: static; flex-direction: row; overflow-x: auto; }
      .es-nav { display: flex; padding: .4rem; }
      .es-shell.nav-collapsed .es-nav a span,
      .es-nav a span { display: inline; }
      .es-side-foot, .es-brand-row, .es-nav-group { display: none; }
    }
  </style>
</head>
<body>
<div class="es-shell" id="esShell">
  <script>try{if(localStorage.getItem('esNav')==='collapsed')document.getElementById('esShell').classList.add('nav-collapsed');}catch(e){}</script>
  <aside class="es-side">
    <div class="es-brand-row">
      <a class="es-brand" href="index.php"><i class="bi bi-diagram-3-fill"></i><span>PPDA e&#8209;Services</span></a>
      <button type="button" class="es-nav-toggle" id="esNavToggle" aria-label="Collapse menu" title="Collapse menu">
        <i class="bi bi-chevron-bar-left"></i>
      </button>
    </div>
    <nav class="es-nav">
      <?php if (es_is_admin_section($active)): ?>
        <div class="es-nav-group">Administration</div>
        <?php foreach (es_admin_nav_items() as $it): if (!es_can($it['perm'])) continue; ?>
          <a href="<?= e($it['href']) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
            <i class="bi <?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span>
          </a>
        <?php endforeach; ?>
        <a href="index.php" title="Back to e-Services" style="margin-top:.8rem;border-top:1px solid var(--border);padding-top:.9rem;border-radius:0;"><i class="bi bi-arrow-left-circle"></i><span>Back to e-Services</span></a>
      <?php else:
      $visible = array_filter(es_nav_items(), fn($it) => empty($it['roles']) || es_has_role(...$it['roles']));
      // fall back to matching the current URL (path + ?role=) so param pages
      // and detail pages still light up the right menu item
      if ($active === '' || !in_array($active, array_column($visible, 'key'), true)) {
        $selfName = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
        $curRole  = $_GET['role'] ?? '';
        foreach ($visible as $it) {
          $hp = basename(parse_url($it['href'], PHP_URL_PATH) ?: '');
          parse_str((string) parse_url($it['href'], PHP_URL_QUERY), $hq);
          if ($hp === $selfName && (!isset($hq['role']) || $hq['role'] === $curRole)) { $active = $it['key']; break; }
        }
      }
      $lastGroup = null;
      foreach ($visible as $it):
        $g = $it['group'] ?? '';
        if ($g !== $lastGroup):
          $lastGroup = $g;
          if ($g !== ''): ?><div class="es-nav-group"><?= e($g) ?></div><?php endif;
        endif; ?>
        <a href="<?= e($it['href']) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
          <i class="bi <?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <div class="es-side-foot">
      <a class="es-hub-link" href="<?= e(ES_HUB_URL) ?>" title="Back to Digital Hub"><i class="bi bi-grid-3x3-gap-fill"></i><span>Back to Digital Hub</span></a>
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
              <li><a class="dropdown-item" href="admin.php"><i class="bi bi-sliders me-2"></i>Administration</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?= e(ES_HUB_URL) ?>"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Digital Hub</a></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="es-content">
      <?php if ($subtitle !== ''): ?>
        <div class="f-head"><h1 class="f-title"><?= e($title) ?></h1><p class="f-subtitle"><?= e($subtitle) ?></p></div>
      <?php endif; ?>
      <?php $__flashes = take_flash(); if ($__flashes): ?>
        <script>window.__esFlash = (window.__esFlash || []).concat(<?= json_encode(array_map(fn($f) => ['msg' => (string) $f['msg'], 'type' => (string) $f['type']], $__flashes), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);</script>
      <?php endif; ?>
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

  <div class="es-toasts" id="esToasts" aria-live="polite" aria-atomic="true"></div>
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

(function () {
  var shell = document.getElementById('esShell');
  var btn = document.getElementById('esNavToggle');
  if (!shell || !btn) return;
  btn.addEventListener('click', function () {
    var on = shell.classList.toggle('nav-collapsed');
    try { localStorage.setItem('esNav', on ? 'collapsed' : 'expanded'); } catch (e) {}
    btn.title = on ? 'Expand menu' : 'Collapse menu';
  });
})();

/* Keep the scroll position across a form POST + redirect that lands back on the
   same page (workflow actions, inline row editors, checklists, votes, …). */
(function () {
  var KEY = 'esScroll';
  var path = location.pathname + location.search.replace(/[?&]page=\d+/, '');
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  try {
    var raw = sessionStorage.getItem(KEY);
    sessionStorage.removeItem(KEY);
    if (raw) {
      var s = JSON.parse(raw);
      if (s && s.p === path && typeof s.y === 'number') {
        var go = function () { window.scrollTo(0, s.y); };
        go();
        requestAnimationFrame(go);
        window.addEventListener('load', function () { requestAnimationFrame(go); });
        setTimeout(go, 120);
      }
    }
  } catch (e) {}

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f || (f.method && f.method.toLowerCase() !== 'post')) return;
    if (!f.getAttribute('action')) return;      // AJAX / JS-handled forms have no action
    if (f.hasAttribute('data-no-restore')) return;
    try { sessionStorage.setItem(KEY, JSON.stringify({ p: path, y: window.pageYOffset })); } catch (e) {}
  }, true);
})();

/* Floating toast notifications (bottom-right) — window.esToast(msg, type) */
(function () {
  var wrap = document.getElementById('esToasts');
  window.esToast = function (msg, type) {
    if (!wrap || !msg) return;
    var kind = (type === 'error' || type === 'danger' || type === 'warning') ? 'error' : 'success';
    var el = document.createElement('div');
    el.className = 'es-toast t-' + kind;
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    el.innerHTML = '<i class="bi bi-' + (kind === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill')
      + '"></i><div class="msg"></div><button class="x" type="button" aria-label="Dismiss">×</button>';
    el.querySelector('.msg').textContent = String(msg);
    wrap.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-in'); });
    var t = setTimeout(close, kind === 'error' ? 7000 : 4000);
    function close() { clearTimeout(t); el.classList.remove('is-in'); el.classList.add('is-out');
      setTimeout(function () { el.remove(); }, 320); }
    el.querySelector('.x').addEventListener('click', close);
    el.addEventListener('mouseenter', function () { clearTimeout(t); });
    el.addEventListener('mouseleave', function () { t = setTimeout(close, 2500); });
  };
  (window.__esFlash || []).forEach(function (f) { window.esToast(f.msg, f.type); });
  window.__esFlash = [];
})();
</script>
</body>
</html>
<?php }
