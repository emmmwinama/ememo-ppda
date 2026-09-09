<?php
/** Administration — all platform users. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

$hasEs = (bool) @$conn->query("SHOW TABLES LIKE 'es_user_role'")->num_rows;

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['f'] ?? 'all';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 15;

$where = ['1=1'];
$types = '';
$args  = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = "%$q%"; $types .= 'sss'; array_push($args, $like, $like, $like);
}
switch ($filter) {
    case 'admins':   $where[] = "u.role = 'admin'"; break;
    case 'inactive': $where[] = 'u.active = 0'; break;
    case 'locked':   $where[] = 'u.locked_until IS NOT NULL AND u.locked_until > NOW()'; break;
    case 'es':       $where[] = 'r.roles IS NOT NULL'; break;
    case 'noes':     $where[] = 'r.roles IS NULL'; break;
}
$wsql = 'WHERE ' . implode(' AND ', $where);

$fromJoin = $hasEs
   ? "FROM users u
      LEFT JOIN ( SELECT user_id, GROUP_CONCAT(role ORDER BY role SEPARATOR ',') roles
                    FROM es_user_role GROUP BY user_id ) r ON r.user_id = u.id"
   : "FROM users u LEFT JOIN ( SELECT NULL user_id, NULL roles ) r ON r.user_id = u.id";

$total = (int) (db_one("SELECT COUNT(*) c $fromJoin $wsql", $types, $args)['c'] ?? 0);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$rows  = db_all(
    "SELECT u.id, u.full_name, u.username, u.email, u.role, u.active,
            u.last_login_at, u.locked_until, r.roles AS es_roles
       $fromJoin $wsql
      ORDER BY u.active DESC, u.full_name
      LIMIT $per OFFSET " . (($page - 1) * $per),
    $types, $args
);

$counts = [];
foreach ([
    'all' => '1=1', 'admins' => "u.role='admin'", 'inactive' => 'u.active=0',
    'locked' => 'u.locked_until IS NOT NULL AND u.locked_until > NOW()',
    'es' => 'r.roles IS NOT NULL', 'noes' => 'r.roles IS NULL',
] as $k => $cond) {
    $counts[$k] = (int) (db_one("SELECT COUNT(*) c $fromJoin WHERE $cond")['c'] ?? 0);
}

$roleTint = ['admin' => 't-rose', 'approver' => 't-sky', 'endorser' => 't-amber', 'originator' => 't-neutral'];

hub_head('Users', 'users', 'Everyone with a platform account — profile, e-Memo role, e-Services roles and passwords');
?>

<div class="f-chips">
  <?php foreach (['all' => 'All', 'admins' => 'Administrators', 'es' => 'Has e-Services', 'noes' => 'e-Memo only', 'inactive' => 'Deactivated', 'locked' => 'Locked'] as $k => $lbl): ?>
    <a href="?<?= e(http_build_query(['f' => $k, 'q' => $q])) ?>" class="chip <?= $filter === $k ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="chip-count"><?= (int) $counts[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="f" value="<?= e($filter) ?>">
  <div class="f-search"><i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, username or email…" autocomplete="off">
  </div>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Search</button>
  <a href="user_edit.php" class="btn btn-success btn-sm" style="height:40px;"><i class="bi bi-person-plus me-1"></i>New user</a>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-people"></i><p>No users match this view.</p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0">
      <thead><tr><th>Name</th><th>Email</th><th>e-Memo role</th><th>e-Services</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $u):
          $es = $u['es_roles'] ? explode(',', $u['es_roles']) : [];
          $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
          <tr>
            <td class="fw-semibold"><?= e($u['full_name'] ?: $u['username']) ?><br><span class="text-muted small">@<?= e($u['username']) ?></span></td>
            <td class="text-muted small"><?= e($u['email'] ?: '—') ?></td>
            <td><span class="pill <?= $roleTint[$u['role']] ?? 't-neutral' ?>"><?= e($u['role']) ?></span></td>
            <td>
              <?php if ($es): foreach ($es as $rk): ?><span class="pill t-sky"><?= e($rk) ?></span> <?php endforeach;
              else: ?><span class="text-muted small">—</span><?php endif; ?>
            </td>
            <td class="text-muted small"><?= $u['last_login_at'] ? e(date('d M Y H:i', strtotime($u['last_login_at']))) : '<span class="text-muted">never</span>' ?></td>
            <td>
              <?php if (!$u['active']): ?><span class="pill t-dark">deactivated</span>
              <?php elseif ($locked): ?><span class="pill t-rose">locked</span>
              <?php else: ?><span class="pill t-green">active</span><?php endif; ?>
            </td>
            <td class="text-end" style="white-space:nowrap;">
              <a class="btn btn-sm btn-outline-secondary" href="user_edit.php?id=<?= (int) $u['id'] ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
              <a class="btn btn-sm btn-outline-secondary" href="user_edit.php?id=<?= (int) $u['id'] ?>#password"><i class="bi bi-key me-1"></i>Password</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= hub_pager($page, $pages, fn(int $p) => '?' . http_build_query(['f' => $filter, 'q' => $q, 'page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php hub_foot(); ?>
