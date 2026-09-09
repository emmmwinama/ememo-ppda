<?php
/** Administration — e-Memo reference data (departments, sections, positions, grades). */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

global $conn;

$defs = [
    'departments' => ['label' => 'Departments', 'table' => 'departments'],
    'sections'    => ['label' => 'Sections',    'table' => 'sections'],
    'positions'   => ['label' => 'Positions',   'table' => 'positions'],
    'grades'      => ['label' => 'Grades',      'table' => 'grades'],
];
$entity = isset($defs[$_GET['entity'] ?? '']) ? $_GET['entity'] : 'departments';

$counts = [];
foreach ($defs as $k => $d) {
    $counts[$k] = (int) ($conn->query("SELECT COUNT(*) FROM `{$d['table']}`")->fetch_row()[0] ?? 0);
}

$departments = db_all("SELECT id, name FROM departments ORDER BY name");
$editId = (int) ($_GET['edit'] ?? 0);

// rows for the active entity
switch ($entity) {
    case 'sections':
        $rows = db_all("SELECT s.id, s.name, s.department_id, d.name AS dept,
                               (SELECT COUNT(*) FROM users u WHERE u.section_id = s.id) AS in_use
                          FROM sections s LEFT JOIN departments d ON d.id = s.department_id
                         ORDER BY d.name, s.name");
        break;
    case 'positions':
        $rows = db_all("SELECT p.id, p.name, p.short_name, p.position_code,
                               (SELECT COUNT(*) FROM users u WHERE u.position_id = p.id) AS in_use
                          FROM positions p ORDER BY p.name");
        break;
    case 'grades':
        $rows = db_all("SELECT id, code FROM grades ORDER BY code");
        break;
    default:
        $rows = db_all("SELECT d.id, d.name,
                               (SELECT COUNT(*) FROM sections s WHERE s.department_id = d.id) AS n_sections,
                               (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS in_use
                          FROM departments d ORDER BY d.name");
}
$editRow = $editId ? db_one("SELECT * FROM `{$defs[$entity]['table']}` WHERE id = ?", 'i', [$editId]) : null;

hub_head('e-Memo reference data', 'ememo_ref', 'Departments, sections, positions and grades used across e-Memo');
?>

<div class="f-chips">
  <?php foreach ($defs as $k => $d): ?>
    <a href="?entity=<?= e($k) ?>" class="chip <?= $entity === $k ? 'active' : '' ?>"><?= e($d['label']) ?><span class="chip-count"><?= (int) $counts[$k] ?></span></a>
  <?php endforeach; ?>
</div>

<div class="f-panel mb-3">
  <div class="f-panel-head"><i class="bi bi-plus-lg"></i> <?= $editRow ? 'Edit' : 'Add' ?> <?= e(rtrim($defs[$entity]['label'], 's')) ?></div>
  <div class="f-panel-body">
    <form method="post" action="ememo_refdata_save.php" class="row g-2 align-items-end">
      <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
      <input type="hidden" name="entity" value="<?= e($entity) ?>">
      <input type="hidden" name="op" value="save">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>"><?php endif; ?>

      <?php if ($entity === 'grades'): ?>
        <div class="col-sm-4"><label class="form-label">Grade code</label>
          <input name="code" class="form-control" required maxlength="20" value="<?= e($editRow['code'] ?? '') ?>"></div>
      <?php elseif ($entity === 'positions'): ?>
        <div class="col-sm-5"><label class="form-label">Position name</label>
          <input name="name" class="form-control" required value="<?= e($editRow['name'] ?? '') ?>"></div>
        <div class="col-sm-3"><label class="form-label">Short name</label>
          <input name="short_name" class="form-control" maxlength="20" value="<?= e($editRow['short_name'] ?? '') ?>"></div>
        <div class="col-sm-2"><label class="form-label">Grade code</label>
          <input name="position_code" type="number" class="form-control" value="<?= e($editRow['position_code'] ?? '') ?>"></div>
      <?php elseif ($entity === 'sections'): ?>
        <div class="col-sm-5"><label class="form-label">Section name</label>
          <input name="name" class="form-control" required value="<?= e($editRow['name'] ?? '') ?>"></div>
        <div class="col-sm-4"><label class="form-label">Department</label>
          <select name="department_id" class="form-select" required>
            <option value="">— select —</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) ($editRow['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      <?php else: ?>
        <div class="col-sm-6"><label class="form-label">Department name</label>
          <input name="name" class="form-control" required value="<?= e($editRow['name'] ?? '') ?>"></div>
      <?php endif; ?>

      <div class="col-sm-3">
        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= $editRow ? 'Save' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="?entity=<?= e($entity) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="f-panel table-responsive">
  <table class="table table-borderless f-table align-middle mb-0">
    <?php if ($entity === 'sections'): ?>
      <thead><tr><th>Section</th><th>Department</th><th>In use</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['name']) ?></td>
          <td class="text-muted"><?= e($r['dept'] ?? '—') ?></td>
          <td class="text-muted"><?= (int) $r['in_use'] ?> user(s)</td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="?entity=sections&edit=<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i></a>
            <?= del_btn('sections', (int) $r['id'], (int) $r['in_use']) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php elseif ($entity === 'positions'): ?>
      <thead><tr><th>Position</th><th>Short</th><th>Grade</th><th>In use</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['name']) ?></td>
          <td class="text-muted"><?= e($r['short_name'] ?: '—') ?></td>
          <td class="text-muted"><?= e($r['position_code'] ?: '—') ?></td>
          <td class="text-muted"><?= (int) $r['in_use'] ?> user(s)</td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="?entity=positions&edit=<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i></a>
            <?= del_btn('positions', (int) $r['id'], (int) $r['in_use']) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php elseif ($entity === 'grades'): ?>
      <thead><tr><th>Code</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold font-monospace"><?= e($r['code']) ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="?entity=grades&edit=<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i></a>
            <?= del_btn('grades', (int) $r['id'], 0) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php else: ?>
      <thead><tr><th>Department</th><th>Sections</th><th>In use</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['name']) ?></td>
          <td class="text-muted"><?= (int) $r['n_sections'] ?></td>
          <td class="text-muted"><?= (int) $r['in_use'] ?> user(s)</td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="?entity=departments&edit=<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i></a>
            <?= del_btn('departments', (int) $r['id'], (int) $r['in_use'] + (int) $r['n_sections']) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    <?php endif; ?>
  </table>
</div>

<?php
function del_btn(string $entity, int $id, int $blocked): string {
    if ($blocked > 0) {
        return '<button class="btn btn-sm btn-outline-secondary" disabled title="In use — cannot delete"><i class="bi bi-trash"></i></button>';
    }
    return '<form method="post" action="ememo_refdata_save.php" class="d-inline" onsubmit="return confirm(\'Delete this record?\');">'
         . '<input type="hidden" name="_csrf" value="' . e(hub_csrf_token()) . '">'
         . '<input type="hidden" name="entity" value="' . e($entity) . '">'
         . '<input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="' . $id . '">'
         . '<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>';
}
hub_foot();
