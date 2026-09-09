<?php
/** Administration — legacy e-Services data import (wraps app/eservice/import engine). */
require __DIR__ . '/../inc/layout.php';
require __DIR__ . '/_guard.php';

$ENGINE = __DIR__ . '/../../app/eservice/import';
require $ENGINE . '/lib.php';
require $ENGINE . '/dump_reader.php';
require $ENGINE . '/import_lookups.php';
require $ENGINE . '/import_suppliers.php';
require $ENGINE . '/import_bids.php';

global $conn, $HUB_UID;

$schemaOk = (bool) @$conn->query("SHOW TABLES LIKE 'es_bid_registry'")->num_rows;

$SQLDIR  = __DIR__ . '/../../app/eservice/sql';
$bundles = [
    'suppliers' => [
        'file'   => $SQLDIR . '/legacy_suppliers.sql',
        'label'  => 'Legacy suppliers',
        'phases' => ['lookups', 'suppliers'],
        'desc'   => 'Companies, shareholders, bank details, categories, certificates & attachments from the old e-Services register.',
    ],
    'bids' => [
        'file'   => $SQLDIR . '/legacy_bids.sql',
        'label'  => 'Legacy bid analyses',
        'phases' => ['lookups', 'bids'],
        'desc'   => 'Bid registry, analyses, evaluations and PDE responses. Drop the assembled dump at app/eservice/sql/legacy_bids.sql first.',
    ],
];
$phaseFns = ['lookups' => 'es_import_lookups', 'suppliers' => 'es_import_suppliers', 'bids' => 'es_import_bids'];
$result   = null;
$resultKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaOk) {
    $key = $_POST['bundle'] ?? '';
    $dry = !empty($_POST['dry']);
    if (isset($bundles[$key]) && is_file($bundles[$key]['file'])) {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        try {
            $parsed = es_parse_dump($bundles[$key]['file']);
            $lines  = [];
            foreach ($bundles[$key]['phases'] as $p) {
                $x = new EsImport($conn, null, $dry);
                $x->setDump($parsed);
                try { $phaseFns[$p]($x); }
                catch (Throwable $e) { $x->warn('aborted: ' . $e->getMessage()); }
                $x->record($HUB_UID, $p);
                $lines[] = "[$p] " . $x->summaryLine();
            }
            $result = implode("\n\n", $lines);
            $resultKey = $key;
            hub_audit(($dry ? 'Previewed' : 'Ran') . " legacy import bundle '$key'", $dry ? 'info' : 'notice');
            hub_flash($dry ? 'Dry-run complete — nothing written.' : 'Import complete.', 'success');
        } catch (Throwable $e) {
            hub_flash('Import failed: ' . $e->getMessage(), 'error');
        }
    } else {
        hub_flash('That bundle file is missing.', 'error');
    }
}

$log = db_all("SELECT * FROM es_import_log ORDER BY id DESC LIMIT 12");

hub_head('Legacy import', 'es_import', 'Bring the old e-Services data into the es_* tables — idempotent, safe to re-run');
?>

<?php if (!$schemaOk): ?>
  <div class="f-state is-error"><i class="bi bi-database-x"></i><p>The <code>es_*</code> schema isn't installed. Load <code>db/master_schema.sql</code> (or <code>app/eservice/sql/01_schema.sql</code>) first.</p></div>
<?php else: ?>

  <?php foreach ($bundles as $key => $b):
    $exists = is_file($b['file']);
    $size   = $exists ? round(filesize($b['file']) / 1048576, 1) : 0; ?>
    <div class="f-panel mb-3">
      <div class="f-panel-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <div class="fw-bold"><i class="bi bi-box-seam me-1"></i><?= e($b['label']) ?></div>
          <p class="text-muted small mb-1" style="max-width:640px;"><?= e($b['desc']) ?></p>
          <p class="text-muted small mb-0">
            Source: <code>app/eservice/sql/<?= e(basename($b['file'])) ?></code>
            <?= $exists ? "· {$size} MB" : '· <span class="text-danger">file not found</span>' ?>
          </p>
        </div>
        <?php if ($exists): ?>
          <div class="d-flex gap-2">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button name="dry" value="1" class="btn btn-outline-secondary btn-sm">Preview</button>
            </form>
            <form method="post" onsubmit="return confirm('Import <?= e($b['label']) ?> into the es_ tables now?');">
              <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Import now</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($result !== null && $resultKey === $key): ?>
        <div class="f-panel-body pt-0"><pre style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);padding:.9rem;font-size:.82rem;margin:0;white-space:pre-wrap;"><?= e($result) ?></pre></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <p class="text-muted small">
    Re-running is safe — every row is matched on its <code>legacy_id</code> and updated in place, never duplicated.
    Large imports take a minute or two.
  </p>

<?php endif; ?>

<div class="f-panel">
  <div class="f-panel-head"><i class="bi bi-clock-history"></i> Recent imports</div>
  <?php if (!$log): ?>
    <div class="f-panel-body"><p class="text-muted small mb-0">No imports run yet.</p></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table table-borderless f-table align-middle mb-0" style="font-size:.85rem;">
      <thead><tr><th>When</th><th>Phase</th><th>Mode</th><th>Ins</th><th>Upd</th><th>Skip</th><th>Warnings</th></tr></thead>
      <tbody>
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="text-muted"><?= e(date('d M H:i', strtotime($l['ts_create']))) ?></td>
            <td class="fw-semibold"><?= e($l['phase']) ?></td>
            <td><?= $l['dry_run'] ? '<span class="pill t-neutral">dry</span>' : '<span class="pill t-green">live</span>' ?></td>
            <td><?= (int) $l['inserted'] ?></td>
            <td><?= (int) $l['updated'] ?></td>
            <td><?= (int) $l['skipped'] ?></td>
            <td class="text-muted"><?= $l['warnings'] ? e(mb_substr($l['warnings'], 0, 140)) . '…' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php hub_foot(); ?>
