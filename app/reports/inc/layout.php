<?php
/** Reports shell — same .es-shell markup + assets as e-Services / the admin console. */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/../../../assets/partials/topbar.php';

function rpt_nav_items(): array {
    return [
        ['key' => 'overview',   'href' => 'index.php',            'icon' => 'bi-speedometer2',   'label' => 'Overview',        'group' => ''],

        ['key' => 'em_volume',  'href' => 'ememo_volume.php',     'icon' => 'bi-file-earmark-bar-graph', 'label' => 'Memo volume',    'group' => 'e-Memo'],
        ['key' => 'em_turn',    'href' => 'ememo_turnaround.php', 'icon' => 'bi-stopwatch',      'label' => 'Turnaround',      'group' => 'e-Memo'],
        ['key' => 'em_appr',    'href' => 'ememo_approvals.php',  'icon' => 'bi-check2-square',  'label' => 'Approvals',       'group' => 'e-Memo'],
        ['key' => 'em_letters', 'href' => 'ememo_letters.php',    'icon' => 'bi-envelope',       'label' => 'External letters','group' => 'e-Memo'],
        ['key' => 'em_work',    'href' => 'ememo_workload.php',   'icon' => 'bi-person-lines-fill','label' => 'User workload',  'group' => 'e-Memo'],

        ['key' => 'es_pipe',    'href' => 'es_pipeline.php',      'icon' => 'bi-funnel',         'label' => 'Submissions pipeline', 'group' => 'e-Services'],
        ['key' => 'es_out',     'href' => 'es_outcomes.php',      'icon' => 'bi-clipboard-check','label' => 'Analysis outcomes',    'group' => 'e-Services'],
        ['key' => 'es_sla',     'href' => 'es_sla.php',           'icon' => 'bi-hourglass-split','label' => 'Review SLAs',          'group' => 'e-Services'],
        ['key' => 'es_sup',     'href' => 'es_suppliers.php',     'icon' => 'bi-building',       'label' => 'Supplier register',    'group' => 'e-Services'],

        ['key' => 'x_activity', 'href' => 'activity.php',         'icon' => 'bi-activity',       'label' => 'User activity',    'group' => 'Cross-module'],
        ['key' => 'x_security', 'href' => 'security.php',         'icon' => 'bi-shield-lock',    'label' => 'Security',         'group' => 'Cross-module'],
    ];
}

function rpt_head(string $title, string $active = '', string $subtitle = ''): void {
    $u = rpt_user();
    $initials = strtoupper(substr($u['full_name'], 0, 1)
        . (strpos($u['full_name'], ' ') !== false ? substr(strrchr($u['full_name'], ' '), 1, 1) : ''));
    $asset = fn(string $p) => '../../assets/' . $p . '?v=' . (@filemtime(__DIR__ . '/../../../assets/' . $p) ?: time());
    $isAdmin = rpt_is_admin();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>try{var t=localStorage.getItem('esTheme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
  <title><?= e($title) ?> · PPDA Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= e($asset('css/theme.css')) ?>" rel="stylesheet">
  <link href="<?= e($asset('css/app.css')) ?>" rel="stylesheet">
  <style>@media print { .es-side, .es-topbar, .f-toolbar { display: none !important; } .es-content { padding: 0 !important; max-width: none !important; } .f-panel { break-inside: avoid; } }</style>
</head>
<body>
<div class="es-shell" id="esShell">
  <script>try{if(localStorage.getItem('esNav')==='collapsed')document.getElementById('esShell').classList.add('nav-collapsed');}catch(e){}</script>
  <aside class="es-side">
    <div class="es-brand-row">
      <a class="es-brand" href="index.php"><i class="bi bi-graph-up-arrow"></i><span>PPDA Reports</span></a>
      <button type="button" class="es-nav-toggle" id="esNavToggle" title="Collapse menu"><i class="bi bi-chevron-bar-left"></i></button>
    </div>
    <nav class="es-nav">
      <?php
      $lastGroup = null;
      foreach (rpt_nav_items() as $it):
          if ($it['key'] === 'x_security' && !$isAdmin) continue;
          $g = $it['group'] ?? '';
          if ($g !== $lastGroup) { $lastGroup = $g; if ($g !== '') echo '<div class="es-nav-group">' . e($g) . '</div>'; } ?>
        <a href="<?= e($it['href']) ?>" class="<?= $active === $it['key'] ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
          <i class="bi <?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="es-side-foot">
      <a class="es-hub-link" href="<?= e(RPT_HUB_URL) ?>" title="Back to Digital Hub"><i class="bi bi-grid-3x3-gap-fill"></i><span>Back to Digital Hub</span></a>
    </div>
  </aside>

  <div class="es-main">
    <?php
    ob_start(); ?>
      <li><span class="dropdown-item-text small text-muted"><?= e($u['email'] ?: $u['username']) ?></span></li>
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print this report</button></li>
      <li><a class="dropdown-item" href="../ememo/index.php"><i class="bi bi-file-earmark-text me-2"></i>e-Memo</a></li>
      <li><a class="dropdown-item" href="../eservice/index.php"><i class="bi bi-diagram-3 me-2"></i>e-Services</a></li>
      <li><a class="dropdown-item" href="<?= e(RPT_HUB_URL) ?>"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Digital Hub</a></li>
      <li><a class="dropdown-item text-danger" href="../../hub/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
    <?php
    render_es_topbar($title, $initials, $u['full_name'], ob_get_clean());
    ?>

    <main class="es-content">
      <?php if ($subtitle !== ''): ?>
        <div class="f-head"><h1 class="f-title"><?= e($title) ?></h1><p class="f-subtitle"><?= e($subtitle) ?></p></div>
      <?php endif; ?>
<?php }

function rpt_foot(): void {
    $asset = fn(string $p) => '../../assets/' . $p . '?v=' . (@filemtime(__DIR__ . '/../../../assets/' . $p) ?: time());
    ?>
    </main>
  </div>
  <div class="es-toasts" id="esToasts"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e($asset('js/app.js')) ?>"></script>
</body>
</html>
<?php }
