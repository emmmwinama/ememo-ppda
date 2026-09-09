<?php
/** Administration console — overview. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

$one = function (string $sql) use ($conn) {
    $r = @$conn->query($sql);
    return $r ? (int) ($r->fetch_row()[0] ?? 0) : 0;
};

$stats = [
    'users'      => $one("SELECT COUNT(*) FROM users"),
    'active'     => $one("SELECT COUNT(*) FROM users WHERE active = 1"),
    'admins'     => $one("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
    'es_roles'   => $one("SELECT COUNT(*) FROM es_role"),
    'pdes'       => $one("SELECT COUNT(*) FROM es_pde WHERE active = 1"),
    'critical7'  => $one("SELECT COUNT(*) FROM security_event WHERE severity = 'critical' AND ts >= NOW() - INTERVAL 7 DAY"),
    'locked'     => $one("SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()"),
];

$lastImport   = db_one("SELECT phase, dry_run, inserted, updated, ts_create FROM es_import_log ORDER BY id DESC LIMIT 1");
$recentAdmin  = db_all("SELECT ts, username, detail FROM security_event WHERE event_type = 'admin_action' ORDER BY id DESC LIMIT 8");
$recentLogins = db_all("SELECT ts, username, ip FROM security_event WHERE event_type = 'login_success' ORDER BY id DESC LIMIT 8");
$secReady     = security_schema_ready($conn);

hub_head('Administration', 'overview', 'Users, reference data, roles and platform security in one place');
?>

<?php if (!$secReady): ?>
  <div class="f-state is-error" style="padding:2rem 1rem;margin-bottom:1.4rem;">
    <i class="bi bi-database-exclamation"></i>
    <p>The security schema isn't installed. Run
      <code>db/migrations/20260909_security.sql</code> against the database to enable login
      auditing and the SOC dashboard.</p>
  </div>
<?php endif; ?>

<div class="f-stats">
  <a class="f-stat s-green" href="users.php" style="text-decoration:none;">
    <i class="bi bi-people"></i>
    <div class="f-stat-value"><?= number_format($stats['users']) ?></div>
    <div class="f-stat-label"><?= number_format($stats['active']) ?> active · <?= number_format($stats['admins']) ?> admins</div>
  </a>
  <a class="f-stat s-sky" href="es_roles.php" style="text-decoration:none;">
    <i class="bi bi-shield-lock"></i>
    <div class="f-stat-value"><?= number_format($stats['es_roles']) ?></div>
    <div class="f-stat-label">e-Services roles</div>
  </a>
  <a class="f-stat s-violet" href="es_refdata.php" style="text-decoration:none;">
    <i class="bi bi-buildings"></i>
    <div class="f-stat-value"><?= number_format($stats['pdes']) ?></div>
    <div class="f-stat-label">Active PDEs</div>
  </a>
  <a class="f-stat <?= $stats['critical7'] || $stats['locked'] ? 's-rose' : 's-amber' ?>" href="soc.php" style="text-decoration:none;">
    <i class="bi bi-activity"></i>
    <div class="f-stat-value"><?= number_format($stats['critical7']) ?></div>
    <div class="f-stat-label"><?= number_format($stats['locked']) ?> account(s) locked now</div>
  </a>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-box-arrow-in-down"></i> Legacy import</div>
      <div class="f-panel-body">
        <?php if ($lastImport): ?>
          <div class="kv">
            <dt>Last run</dt><dd><?= e(date('d M Y H:i', strtotime($lastImport['ts_create']))) ?></dd>
            <dt>Phase</dt><dd><?= e($lastImport['phase']) ?> <?= $lastImport['dry_run'] ? '<span class="pill t-neutral">dry</span>' : '<span class="pill t-green">live</span>' ?></dd>
            <dt>Rows</dt><dd>+<?= (int) $lastImport['inserted'] ?> new · ~<?= (int) $lastImport['updated'] ?> updated</dd>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-2">No imports run yet.</p>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary mt-2" href="es_import.php">Open import</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-clock-history"></i> Recent admin actions</div>
      <?php if (!$recentAdmin): ?>
        <div class="f-panel-body"><p class="text-muted small mb-0">Nothing logged yet.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
          <tbody>
          <?php foreach ($recentAdmin as $a): ?>
            <tr>
              <td class="text-muted" style="white-space:nowrap;"><?= e(date('d M H:i', strtotime($a['ts']))) ?></td>
              <td class="fw-semibold"><?= e($a['username'] ?? '—') ?></td>
              <td class="text-muted"><?= e($a['detail'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-door-open"></i> Recent sign-ins</div>
      <?php if (!$recentLogins): ?>
        <div class="f-panel-body"><p class="text-muted small mb-0">Nothing logged yet.</p></div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
          <tbody>
          <?php foreach ($recentLogins as $l): ?>
            <tr>
              <td class="text-muted" style="white-space:nowrap;"><?= e(date('d M H:i', strtotime($l['ts']))) ?></td>
              <td class="fw-semibold"><?= e($l['username'] ?? '—') ?></td>
              <td class="text-muted font-monospace" style="font-size:.78rem;"><?= e($l['ip'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php hub_foot(); ?>
