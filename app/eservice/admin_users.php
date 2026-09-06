<?php
/** Admin — users & their e-Services roles. */
require __DIR__ . '/inc/layout.php';
es_require_perm('users.manage');

global $conn;

$filter = $_GET['f'] ?? 'all';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 10;

$where = ['1=1'];
$types = '';
$args  = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = "%$q%"; $types .= 'sss'; array_push($args, $like, $like, $like);
}
switch ($filter) {
    case 'ppda':      $where[] = '(r.is_pde IS NULL OR r.is_pde = 0)'; break;
    case 'pde':       $where[] = 'r.is_pde = 1'; break;
    case 'withaccess':$where[] = 'r.roles IS NOT NULL'; break;
    case 'noaccess':  $where[] = 'r.roles IS NULL'; break;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$fromJoin =
   "FROM users u
    LEFT JOIN ( SELECT user_id,
                       GROUP_CONCAT(role ORDER BY role SEPARATOR ',') roles,
                       MAX(role = 'pde') is_pde,
                       MAX(pde_id) pde_id
                FROM es_user_role GROUP BY user_id ) r ON r.user_id = u.id
    LEFT JOIN es_pde p ON p.id = r.pde_id";

$total  = (int) (db_one("SELECT COUNT(*) c $fromJoin $whereSql", $types, $args)['c'] ?? 0);
$pages  = max(1, (int) ceil($total / $per));
$page   = min($page, $pages);
$rows   = db_all(
    "SELECT u.id, u.full_name, u.username, u.email, u.phone_number,
            r.roles, r.is_pde, p.name AS pde_name
     $fromJoin $whereSql
     ORDER BY (r.roles IS NULL), u.full_name
     LIMIT $per OFFSET " . (($page - 1) * $per),
    $types, $args
);

$counts = [];
foreach ([
    'all' => '1=1',
    'ppda' => "(r.is_pde IS NULL OR r.is_pde = 0)",
    'pde' => 'r.is_pde = 1',
    'withaccess' => 'r.roles IS NOT NULL',
    'noaccess' => 'r.roles IS NULL',
] as $k => $cond) {
    $counts[$k] = (int) (db_one("SELECT COUNT(*) c $fromJoin WHERE $cond")['c'] ?? 0);
}

es_layout_head('Users', 'users');
es_admin_nav('users');
?>

<div class="f-head">
  <h1 class="f-title">Users</h1>
  <p class="f-subtitle">Everyone with an ememo account — PPDA staff and PDE representatives — and their e-Services roles</p>
</div>

<div class="f-chips">
  <?php foreach (['all' => 'All', 'ppda' => 'PPDA staff', 'pde' => 'PDE reps', 'withaccess' => 'Has access', 'noaccess' => 'No access'] as $k => $lbl): ?>
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
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-people"></i><p>No users match this view.</p></div>
<?php else: ?>
  <div class="f-panel table-responsive">
    <table class="table table-borderless f-table align-middle mb-0">
      <thead><tr><th>Name</th><th>Contact</th><th>Identity</th><th>e-Services roles</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $u):
          $roles = $u['roles'] ? explode(',', $u['roles']) : []; ?>
          <tr>
            <td class="fw-semibold"><?= e($u['full_name'] ?: $u['username']) ?><br><span class="text-muted small">@<?= e($u['username']) ?></span></td>
            <td class="text-muted small"><?= e($u['email'] ?: '—') ?><?php if ($u['phone_number']): ?><br><?= e($u['phone_number']) ?><?php endif; ?></td>
            <td>
              <?php if ($u['is_pde']): ?>
                <span class="es-badge" style="color:#0369a1;background:#e6f4fb"><i class="bi bi-building"></i>PDE</span>
                <div class="text-muted small mt-1"><?= e($u['pde_name'] ?? '— unlinked —') ?></div>
              <?php else: ?>
                <span class="es-badge" style="color:#475569;background:#eef1f4"><i class="bi bi-person-badge"></i>PPDA</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($roles): foreach ($roles as $rk): ?><span class="pill t-sky"><?= e($rk) ?></span> <?php endforeach;
              else: ?><span class="text-muted small">no access</span><?php endif; ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-secondary"
                      data-drawer="admin_user_roles.php?uid=<?= (int) $u['id'] ?>&amp;partial=1"
                      data-drawer-title="Roles · <?= e($u['full_name'] ?: $u['username']) ?>">
                <i class="bi bi-pencil me-1"></i>Roles
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query(['f' => $filter, 'q' => $q, 'page' => $p]), $total, $per) ?>
<?php endif; ?>

<p class="text-muted small mt-3">User accounts (name, email, password) are managed in ememo. This screen assigns e-Services roles only.</p>

<?php es_layout_foot(); ?>
