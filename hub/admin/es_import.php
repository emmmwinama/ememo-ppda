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
$SQLDIR   = __DIR__ . '/../../app/eservice/sql';
$REPOROOT = dirname(__DIR__, 2);

/** First existing path from the candidates, or the first candidate (for display). */
$pick = function (array $cands) {
    foreach ($cands as $c) if (is_file($c)) return $c;
    return $cands[0];
};

$bundles = [
    'suppliers' => [
        'file'   => $pick([$SQLDIR . '/legacy_suppliers.sql', $REPOROOT . '/ppda_e_services-online (1).sql']),
        'label'  => 'Legacy suppliers',
        'phases' => ['lookups', 'suppliers'],
        'desc'   => 'Companies, shareholders, bank details, categories, certificates & attachments from the old supplier register.',
    ],
    'bids' => [
        'file'   => $SQLDIR . '/legacy_bids.sql',
        'label'  => 'Legacy bid analyses',
        'phases' => ['lookups', 'bids'],
        'desc'   => 'Bid registry, analyses, evaluations, routing trail and PDE responses. Assemble the dump at app/eservice/sql/legacy_bids.sql first (structure + data).',
    ],
    'full' => [
        'file'   => $SQLDIR . '/legacy_full.sql',
        'label'  => 'Full legacy database',
        'phases' => ['lookups', 'suppliers', 'bids'],
        'desc'   => 'Everything, in order: lookups → suppliers → bids. Drop the whole legacy dump at app/eservice/sql/legacy_full.sql, then run one phase at a time.',
    ],
];
$phaseFns = ['lookups' => 'es_import_lookups', 'suppliers' => 'es_import_suppliers', 'bids' => 'es_import_bids'];

$result = null;
$resultKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaOk) {
    $key   = $_POST['bundle'] ?? '';
    $dry   = !empty($_POST['dry']);
    $only  = $_POST['phase'] ?? '__all__';
    $b     = $bundles[$key] ?? null;

    if (!$b) {
        hub_flash('Unknown bundle.', 'error');
    } elseif (!is_file($b['file'])) {
        hub_flash('That dump file is not on the server yet.', 'error');
    } else {
        $phases = $only === '__all__'
            ? $b['phases']
            : array_values(array_intersect($b['phases'], [$only]));
        if (!$phases) {
            hub_flash('That phase is not part of this bundle.', 'error');
        } else {
            @set_time_limit(0);
            @ini_set('memory_limit', '2048M');
            try {
                $t0 = microtime(true);
                $parsed = es_parse_dump($b['file']);
                $tables = array_map(fn($t) => "$t(" . count($parsed[$t]) . ')', array_keys($parsed));
                $lines  = [sprintf('parsed %s in %.1fs — %d table(s): %s',
                    basename($b['file']), microtime(true) - $t0, count($parsed),
                    implode(', ', array_slice($tables, 0, 30)) . (count($tables) > 30 ? ' …' : ''))];

                $grand = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0];
                foreach ($phases as $p) {
                    $pt = microtime(true);
                    $x  = new EsImport($conn, null, $dry);
                    $x->setDump($parsed);
                    try { $phaseFns[$p]($x); }
                    catch (Throwable $e) { $x->warn('phase aborted: ' . $e->getMessage()); }
                    $x->record($HUB_UID, $p);
                    $lines[] = sprintf("[%s]  %.1fs\n%s", $p, microtime(true) - $pt,
                        '  ' . str_replace("\n", "\n  ", $x->summaryLine()));
                    foreach (['inserted', 'updated', 'skipped'] as $k) $grand[$k] += $x->counts[$k];
                    $grand['warnings'] += count($x->warnings);
                }
                $lines[] = sprintf('TOTAL  %.1fs  ·  +%d inserted, ~%d updated, %d skipped, %d warning(s)',
                    microtime(true) - $t0, $grand['inserted'], $grand['updated'], $grand['skipped'], $grand['warnings']);

                $result = implode("\n\n", $lines);
                $resultKey = $key;
                $what = $only === '__all__' ? "bundle '$key'" : "bundle '$key' phase '$only'";
                hub_audit(($dry ? 'Previewed' : 'Ran') . " legacy import $what "
                        . "(+{$grand['inserted']} / ~{$grand['updated']})", $dry ? 'info' : 'notice');
                hub_flash($dry ? 'Dry-run complete — nothing written.' : 'Import step complete.', 'success');
            } catch (Throwable $e) {
                hub_flash('Import failed: ' . $e->getMessage(), 'error');
            }
        }
    }
}

$log = db_all("SELECT * FROM es_import_log ORDER BY id DESC LIMIT 15");

hub_head('Legacy import', 'es_import', 'Bring the old e-Services data into the es_* tables — idempotent, safe to re-run');
?>

<?php if (!$schemaOk): ?>
  <div class="f-state is-error"><i class="bi bi-database-x"></i><p>The <code>es_*</code> schema isn't installed. Load <code>db/master_schema.sql</code> first.</p></div>
<?php else: ?>

  <div class="pde-hint mb-3" style="background:#eef4ff;border-color:#cfe0ff;color:#1e40af;">
    <i class="bi bi-info-circle-fill"></i>
    <span>Every importable table carries a unique <code>legacy_id</code>, so imports are <strong>idempotent</strong> —
      re-running updates rows in place and never duplicates. For the full database, run
      <strong>lookups → suppliers → bids</strong> as separate steps: each request is bounded and you see
      timings and counts after each one. <em>Preview</em> is a dry run that writes nothing.</span>
  </div>

  <?php foreach ($bundles as $key => $b):
    $exists = is_file($b['file']);
    $size   = $exists ? round(filesize($b['file']) / 1048576, 1) : 0;
    $shown  = $exists
        ? ltrim(str_replace($REPOROOT, '', realpath($b['file'])), '/\\')
        : 'app/eservice/sql/' . basename($b['file']); ?>
    <div class="f-panel mb-3">
      <div class="f-panel-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <div class="fw-bold"><i class="bi bi-box-seam me-1"></i><?= e($b['label']) ?></div>
            <p class="text-muted small mb-1" style="max-width:680px;"><?= e($b['desc']) ?></p>
            <p class="text-muted small mb-0">
              Source: <code><?= e($shown) ?></code>
              <?= $exists ? "· {$size} MB" : '· <span class="text-danger">not found — drop the dump here first</span>' ?>
            </p>
          </div>
        </div>

        <?php if ($exists): ?>
          <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
            <span class="text-muted small me-1">Phases:</span>
            <?php foreach ($b['phases'] as $p): ?>
              <span class="d-inline-flex gap-1 border rounded" style="padding:.2rem .3rem;">
                <span class="pill t-neutral align-self-center"><?= e($p) ?></span>
                <form method="post" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
                  <input type="hidden" name="bundle" value="<?= e($key) ?>">
                  <input type="hidden" name="phase" value="<?= e($p) ?>">
                  <button name="dry" value="1" class="btn btn-link btn-sm p-1 text-decoration-none">preview</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Run the <?= e($p) ?> phase of <?= e($b['label']) ?> now?');">
                  <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
                  <input type="hidden" name="bundle" value="<?= e($key) ?>">
                  <input type="hidden" name="phase" value="<?= e($p) ?>">
                  <button class="btn btn-sm btn-outline-success p-1 px-2">run</button>
                </form>
              </span>
            <?php endforeach; ?>
            <span class="vr mx-1"></span>
            <form method="post" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button name="dry" value="1" class="btn btn-outline-secondary btn-sm">Preview all</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Run ALL phases of <?= e($b['label']) ?> now? For a large dump, run phases individually instead.');">
              <input type="hidden" name="_csrf" value="<?= e(hub_csrf_token()) ?>">
              <input type="hidden" name="bundle" value="<?= e($key) ?>">
              <button class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Run all</button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($result !== null && $resultKey === $key): ?>
        <div class="f-panel-body pt-0">
          <pre style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);padding:.9rem;font-size:.8rem;margin:0;white-space:pre-wrap;"><?= e($result) ?></pre>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

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
            <td class="text-muted"><?= $l['warnings'] ? e(mb_substr($l['warnings'], 0, 160)) . '…' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php hub_foot(); ?>
