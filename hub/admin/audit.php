<?php
/** Administration — security event log browser. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

if (!security_schema_ready($conn)) {
    hub_head('Audit log', 'audit');
    echo '<div class="f-state is-error"><i class="bi bi-database-exclamation"></i><p>Run <code>db/migrations/20260909_security.sql</code> first.</p></div>';
    hub_foot();
    exit;
}

$f = [
    'type'     => trim($_GET['type'] ?? ''),
    'severity' => trim($_GET['severity'] ?? ''),
    'user'     => trim($_GET['user'] ?? ''),
    'from'     => trim($_GET['from'] ?? ''),
    'to'       => trim($_GET['to'] ?? ''),
    'q'        => trim($_GET['q'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 50;

// CSV export of the current filter
if (($_GET['export'] ?? '') === 'csv') {
    $data = soc_recent($conn, $f + ['limit' => 5000, 'offset' => 0]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="security-events-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ts', 'app', 'event_type', 'severity', 'user_id', 'username', 'ip', 'route', 'detail']);
    foreach ($data['rows'] as $r) {
        fputcsv($out, [$r['ts'], $r['app'], $r['event_type'], $r['severity'], $r['user_id'], $r['username'], $r['ip'], $r['route'], $r['detail']]);
    }
    fclose($out);
    exit;
}

$res   = soc_recent($conn, $f + ['limit' => $per, 'offset' => ($page - 1) * $per]);
$rows  = $res['rows'];
$total = $res['total'];
$pages = max(1, (int) ceil($total / $per));

$types = array_column(db_all("SELECT DISTINCT event_type FROM security_event ORDER BY event_type"), 'event_type');
$sevTint = ['info' => 't-neutral', 'notice' => 't-sky', 'warning' => 't-amber', 'critical' => 't-rose'];
$qs = fn(array $extra = []) => '?' . http_build_query(array_merge($f, $extra));

hub_head('Audit log', 'audit', 'Every security event — authentication, access control, admin actions');
?>

<form class="f-toolbar" method="get">
  <div class="f-search"><i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="Search detail, route or IP…" autocomplete="off">
  </div>
  <select name="type" class="form-select form-select-sm f-control">
    <option value="">All event types</option>
    <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
  </select>
  <select name="severity" class="form-select form-select-sm f-control">
    <option value="">Any severity</option>
    <?php foreach (['info', 'notice', 'warning', 'critical'] as $s): ?><option value="<?= $s ?>" <?= $f['severity'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
  </select>
  <input type="text" name="user" value="<?= e($f['user']) ?>" class="form-control form-control-sm f-control" placeholder="username" style="max-width:140px;">
  <input type="date" name="from" value="<?= e($f['from']) ?>" class="form-control form-control-sm f-control">
  <input type="date" name="to" value="<?= e($f['to']) ?>" class="form-control form-control-sm f-control">
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Filter</button>
  <a class="btn btn-outline-secondary btn-sm" style="height:40px;" href="<?= e($qs(['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i>CSV</a>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-list-columns"></i><p>No events match this filter.</p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0" style="font-size:.83rem;">
      <thead><tr><th>When</th><th>App</th><th>Event</th><th>Severity</th><th>User</th><th>IP</th><th>Route</th><th>Detail</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-muted" style="white-space:nowrap;"><?= e(date('d M Y H:i:s', strtotime($r['ts']))) ?></td>
            <td class="text-muted"><?= e($r['app']) ?></td>
            <td class="fw-semibold"><?= e($r['event_type']) ?></td>
            <td><span class="pill <?= $sevTint[$r['severity']] ?? 't-neutral' ?>"><?= e($r['severity']) ?></span></td>
            <td><?= e($r['username'] ?? '—') ?></td>
            <td class="font-monospace" style="font-size:.76rem;"><?= e($r['ip'] ?? '') ?></td>
            <td class="text-muted font-monospace" style="font-size:.74rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($r['route'] ?? '') ?>"><?= e($r['route'] ?? '') ?></td>
            <td class="text-muted" style="max-width:280px;"><?= e($r['detail'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= hub_pager($page, $pages, fn(int $p) => $qs(['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php hub_foot(); ?>
