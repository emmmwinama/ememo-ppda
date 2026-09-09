<?php
/** Administration — e-Memo distribution groups and their members. */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

$groups = db_all("SELECT g.id, g.name, g.description,
                         (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS n
                    FROM `groups` g ORDER BY g.name");
$sel = (int) ($_GET['g'] ?? ($groups[0]['id'] ?? 0));
$current = null;
foreach ($groups as $g) if ((int) $g['id'] === $sel) $current = $g;

$members = $current ? db_all(
    "SELECT u.id, u.full_name, u.username, u.email
       FROM group_members gm JOIN users u ON u.id = gm.user_id
      WHERE gm.group_id = ? ORDER BY u.full_name", 'i', [$sel]
) : [];
$memberIds = array_column($members, 'id');
$candidates = db_all("SELECT id, full_name, username FROM users WHERE active = 1 ORDER BY full_name");

hub_head('e-Memo groups', 'ememo_groups', 'Named recipient groups used when addressing memos and circulars');
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="f-panel mb-3">
      <div class="f-panel-head"><i class="bi bi-plus-lg"></i> New group</div>
      <div class="f-panel-body">
        <form method="post" action="ememo_groups_save.php">
          <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
          <input type="hidden" name="op" value="create_group">
          <div class="mb-2"><input name="name" class="form-control" placeholder="Group name" required></div>
          <div class="mb-2"><input name="description" class="form-control" placeholder="Description (optional)"></div>
          <button class="btn btn-success btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Create</button>
        </form>
      </div>
    </div>
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-people"></i> Groups</div>
      <div class="list-group list-group-flush">
        <?php if (!$groups): ?>
          <div class="f-panel-body"><p class="text-muted small mb-0">No groups yet.</p></div>
        <?php else: foreach ($groups as $g): ?>
          <a href="?g=<?= (int) $g['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (int) $g['id'] === $sel ? 'active' : '' ?>">
            <span><?= e($g['name']) ?></span><span class="badge bg-secondary rounded-pill"><?= (int) $g['n'] ?></span>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <?php if (!$current): ?>
      <div class="f-state"><i class="bi bi-people"></i><p>Select or create a group.</p></div>
    <?php else: ?>
      <div class="f-panel mb-3">
        <div class="f-panel-head">
          <i class="bi bi-people-fill"></i> <?= e($current['name']) ?>
          <span class="ms-auto">
            <form method="post" action="ememo_groups_save.php" class="d-inline" onsubmit="return confirm('Delete the whole group?');">
              <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
              <input type="hidden" name="op" value="delete_group">
              <input type="hidden" name="group_id" value="<?= (int) $current['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete group</button>
            </form>
          </span>
        </div>
        <div class="f-panel-body">
          <?php if ($current['description']): ?><p class="text-muted small"><?= e($current['description']) ?></p><?php endif; ?>
          <form method="post" action="ememo_groups_save.php" class="d-flex gap-2">
            <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
            <input type="hidden" name="op" value="add_member">
            <input type="hidden" name="group_id" value="<?= (int) $current['id'] ?>">
            <select name="user_id" class="form-select" required>
              <option value="">— add a member —</option>
              <?php foreach ($candidates as $c): if (in_array((int) $c['id'], $memberIds, true)) continue; ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['full_name'] ?: $c['username']) ?> (@<?= e($c['username']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success btn-sm" style="white-space:nowrap;"><i class="bi bi-person-plus me-1"></i>Add</button>
          </form>
        </div>
      </div>

      <div class="f-panel table-responsive">
        <table class="table table-borderless f-table align-middle mb-0">
          <thead><tr><th>Name</th><th>Username</th><th>Email</th><th></th></tr></thead>
          <tbody>
            <?php if (!$members): ?>
              <tr><td colspan="4" class="text-muted text-center py-4">No members yet.</td></tr>
            <?php else: foreach ($members as $m): ?>
              <tr>
                <td class="fw-semibold"><?= e($m['full_name'] ?: $m['username']) ?></td>
                <td class="text-muted">@<?= e($m['username']) ?></td>
                <td class="text-muted small"><?= e($m['email'] ?: '—') ?></td>
                <td class="text-end">
                  <form method="post" action="ememo_groups_save.php" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
                    <input type="hidden" name="op" value="remove_member">
                    <input type="hidden" name="group_id" value="<?= (int) $current['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php hub_foot(); ?>
