<?php
/**
 * Administration console shell. Same markup + assets as the e-Services shell
 * (assets/css/app.css + assets/js/app.js) so the two areas look identical.
 *
 *   hub_head('Users', 'users', 'Everyone with a platform account');
 *   ... page body ...
 *   hub_foot();
 *
 * Pages live in hub/admin/, so asset paths are ../../assets/… .
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../assets/partials/topbar.php';

/** Sidebar model — grouped by the app each screen administers. */
function hub_nav_items(): array {
    return [
        ['key' => 'overview',     'href' => 'index.php',          'icon' => 'bi-speedometer2',   'label' => 'Overview',            'group' => ''],
        ['key' => 'users',        'href' => 'users.php',          'icon' => 'bi-people',         'label' => 'Users',               'group' => 'People'],
        ['key' => 'ememo_ref',    'href' => 'ememo_refdata.php',  'icon' => 'bi-diagram-2',      'label' => 'Reference data',      'group' => 'e-Memo'],
        ['key' => 'ememo_groups', 'href' => 'ememo_groups.php',   'icon' => 'bi-people-fill',    'label' => 'Groups',              'group' => 'e-Memo'],
        ['key' => 'es_roles',     'href' => 'es_roles.php',       'icon' => 'bi-shield-lock',    'label' => 'Roles & permissions', 'group' => 'e-Services'],
        ['key' => 'es_ref',       'href' => 'es_refdata.php',     'icon' => 'bi-table',          'label' => 'Reference data',      'group' => 'e-Services'],
        ['key' => 'es_import',    'href' => 'es_import.php',      'icon' => 'bi-database-down',  'label' => 'Legacy import',       'group' => 'e-Services'],
        ['key' => 'soc',          'href' => 'soc.php',            'icon' => 'bi-activity',       'label' => 'SOC dashboard',       'group' => 'Security'],
        ['key' => 'audit',        'href' => 'audit.php',          'icon' => 'bi-list-columns',   'label' => 'Audit log',           'group' => 'Security'],
    ];
}

function hub_head(string $title, string $active = '', string $subtitle = ''): void {
    $u = hub_user();
    $initials = strtoupper(substr($u['full_name'], 0, 1)
        . (strpos($u['full_name'], ' ') !== false ? substr(strrchr($u['full_name'], ' '), 1, 1) : ''));
    $asset = fn(string $p) => '../../assets/' . $p . '?v=' . (@filemtime(__DIR__ . '/../../assets/' . $p) ?: time());
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>try{var t=localStorage.getItem('esTheme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
  <title><?= e($title) ?> · PPDA Administration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= e($asset('css/theme.css')) ?>" rel="stylesheet">
  <link href="<?= e($asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="es-shell" id="esShell">
  <script>try{if(localStorage.getItem('esNav')==='collapsed')document.getElementById('esShell').classList.add('nav-collapsed');}catch(e){}</script>
  <aside class="es-side">
    <div class="es-brand-row">
      <a class="es-brand" href="index.php"><i class="bi bi-sliders"></i><span>PPDA Admin</span></a>
      <button type="button" class="es-nav-toggle" id="esNavToggle" aria-label="Collapse menu" title="Collapse menu">
        <i class="bi bi-chevron-bar-left"></i>
      </button>
    </div>
    <nav class="es-nav">
      <?php
      $lastGroup = null;
      foreach (hub_nav_items() as $it):
          $g = $it['group'] ?? '';
          if ($g !== $lastGroup):
              $lastGroup = $g;
              if ($g !== ''): ?><div class="es-nav-group"><?= e($g) ?></div><?php endif;
          endif; ?>
        <a href="<?= e($it['href']) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
          <i class="bi <?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="es-side-foot">
      <a class="es-hub-link" href="../index.php" title="Back to Digital Hub"><i class="bi bi-grid-3x3-gap-fill"></i><span>Back to Digital Hub</span></a>
    </div>
  </aside>

  <div class="es-main">
    <?php
    ob_start(); ?>
      <li><span class="dropdown-item-text small text-muted"><?= e($u['email'] ?: $u['username']) ?></span></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="../../app/ememo/index.php"><i class="bi bi-file-earmark-text me-2"></i>e-Memo</a></li>
      <li><a class="dropdown-item" href="../../app/eservice/index.php"><i class="bi bi-diagram-3 me-2"></i>e-Services</a></li>
      <li><a class="dropdown-item" href="../index.php"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Digital Hub</a></li>
      <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
    <?php
    render_es_topbar($title, $initials, $u['full_name'], ob_get_clean());
    ?>

    <main class="es-content">
      <?php if ($subtitle !== ''): ?>
        <div class="f-head"><h1 class="f-title"><?= e($title) ?></h1><p class="f-subtitle"><?= e($subtitle) ?></p></div>
      <?php endif; ?>
      <?php $__f = hub_take_flash(); if ($__f): ?>
        <script>window.__esFlash = (window.__esFlash || []).concat(<?= json_encode(array_map(fn($x) => ['msg' => (string) $x['msg'], 'type' => (string) $x['type']], $__f), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);</script>
      <?php endif; ?>
<?php }

function hub_foot(): void {
    $asset = fn(string $p) => '../../assets/' . $p . '?v=' . (@filemtime(__DIR__ . '/../../assets/' . $p) ?: time());
    ?>
    </main>
  </div>

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
<script src="<?= e($asset('js/app.js')) ?>"></script>
</body>
</html>
<?php }
