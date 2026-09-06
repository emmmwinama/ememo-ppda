<?php
/** Legacy-data import — one click. (Admin only.) */
require __DIR__ . '/inc/layout.php';
es_require_perm('import.run');

require __DIR__ . '/import/lib.php';
require __DIR__ . '/import/dump_reader.php';
require __DIR__ . '/import/import_lookups.php';
require __DIR__ . '/import/import_suppliers.php';
require __DIR__ . '/import/import_bids.php';

global $conn, $ES_UID;

$schemaOk   = (bool) $conn->query("SHOW TABLES LIKE 'es_bid_registry'")->num_rows;
$bundles    = [
    'suppliers' => [
        'file'   => ES_ROOT . '/sql/legacy_suppliers.sql',
        'label'  => 'Legacy suppliers',
        'phases' => ['lookups', 'suppliers'],
        'desc'   => 'Companies, shareholders, bank details, categories, certificates & attachments from the old e-Services register.',
    ],
];
$phaseFns = ['lookups' => 'es_import_lookups', 'suppliers' => 'es_import_suppliers', 'bids' => 'es_import_bids'];
$result   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && es_csrf_check() && $schemaOk) {
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
                $x->record($ES_UID, $p);
                $lines[] = "[$p] " . $x->summaryLine();
            }
            $result = implode("\n\n", $lines);
            flash($dry ? 'Dry-run complete — nothing written.' : 'Import complete.', 'success');
        } catch (Throwable $e) {
            flash('Import failed: ' . $e->getMessage(), 'error');
        }
    } else {
        flash('That bundle file is missing.', 'error');
    }
}

$log = db_all("SELECT * FROM es_import_log ORDER BY id DESC LIMIT 12");

es_layout_head('Legacy data import', 'import');
es_admin_nav('import');
?>

<div class="f-head">
  <h1 class="f-title">Legacy data import</h1>
  <p class="f-subtitle">Bring the old e-Services data into the <code>es_*</code> tables — one click, no setup</p>
</div>

<?php if (!$schemaOk): ?>
  <div class="f-state is-error"><i class="bi bi-database-x"></i><p>The <code>es_*</code> schema isn't installed yet. Load <code>app/eservice/sql/01_schema.sql</code> first.</p></div>
<?php else: ?>

  <?php foreach ($bundles as $key => $b):
    $exists = is_file($b['file']);
    $size   = $exists ? round(filesize($b['file']) / 1048576, 1) : 0; ?>
    <div class="f-panel mb-3" style="padding:1.25rem;">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <div class="fw-bold"><i class="bi bi-box-seam me-1"></i><?= e($b['label']) ?></div>
          <p class="text-muted small mb-1" style="max-width:640px;"><?= e($b['desc']) ?></p>
          <p class="text-muted small mb-0">
            Source: <code><?= e(str_replace(ES_ROOT . '/', '', $b['file'])) ?></code>
            <?= $exists ? "· {$size} MB" : '· <span class="text-danger">file not found</span>' ?>
          </p>
        </div>
        <?php if ($exists): ?>
          <div class="d-flex gap-2">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button name="dry" value="1" class="btn btn-outline-secondary btn-sm">Preview</button>
            </form>
            <form method="post" onsubmit="return confirm('Import <?= e($b['label']) ?> into the es_ tables now?');">
              <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Import now</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($result !== null && ($_POST['bundle'] ?? '') === $key): ?>
        <pre style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);padding:.9rem;font-size:.82rem;margin:.9rem 0 0;white-space:pre-wrap;"><?= e($result) ?></pre>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <p class="text-muted small">
    Re-running is safe — every row is matched on its <code>legacy_id</code> and updated in place, never duplicated.
    Categories &amp; country still resolve to <em>NULL</em> because the supplier bundle carries no catalogue tables — expected for a lookup register.
    Advanced (import from a live legacy database): <code>php app/eservice/import/run.php …</code>
  </p>

<?php endif; ?>

<div class="f-panel mt-3">
  <div class="f-panel-head"><i class="bi bi-clock-history"></i> Recent imports</div>
  <?php if (!$log): ?>
    <div class="f-state"><i class="bi bi-inbox"></i><p>No imports run yet.</p></div>
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

<?php es_layout_foot(); ?>
