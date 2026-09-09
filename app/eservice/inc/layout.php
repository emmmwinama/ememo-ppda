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
        ['key' => 'admin',       'href' => '../../hub/admin/index.php',     'icon' => 'bi-sliders',      'label' => 'Administration',      'group' => 'System',              'roles' => ['admin']],
    ];
}

/** Sidebar for a PDE-representative user — a short, plain-language portal menu. */
function es_pde_nav_items(): array {
    return [
        ['key' => 'dashboard', 'href' => 'index.php',             'icon' => 'bi-house-door',   'label' => 'Home'],
        ['key' => 'registry',  'href' => 'bid_registry.php',      'icon' => 'bi-folder2-open', 'label' => 'My submissions'],
        ['key' => 'new',       'href' => 'bid_registry_form.php', 'icon' => 'bi-plus-circle',  'label' => 'New submission'],
        ['key' => 'suppliers', 'href' => 'suppliers.php',         'icon' => 'bi-building',     'label' => 'Supplier register'],
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
    // A PDE-representative account (no internal role) gets the lighter portal skin + menu.
    $pdePortal = function_exists('es_pde_scope') && es_pde_scope() !== null;
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · PPDA <?= $pdePortal ? 'PDE Portal' : 'e-Services' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <link href="../../assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../../../assets/css/app.css') ?: time() ?>" rel="stylesheet">
  <?php if ($pdePortal): ?>
  <link href="../../assets/css/pde.css?v=<?= @filemtime(__DIR__ . '/../../../assets/css/pde.css') ?: time() ?>" rel="stylesheet">
  <?php endif; ?>
  <style>
    /* e-Services-only tweaks live here; the shared shell is in assets/css/app.css */
    body { background: var(--bg); }
  </style>
</head>
<body>
<div class="es-shell<?= $pdePortal ? ' pde-portal' : '' ?>" id="esShell">
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
              <li><a class="dropdown-item" href="../../hub/admin/index.php"><i class="bi bi-sliders me-2"></i>Administration</a></li>
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
<script src="../../assets/js/app.js?v=<?= @filemtime(__DIR__ . "/../../../assets/js/app.js") ?: time() ?>"></script>
</body>
</html>
<?php }
