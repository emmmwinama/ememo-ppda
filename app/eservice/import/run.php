<?php
/**
 * CLI legacy-data importer.
 *
 *   php app/eservice/import/run.php all            # lookups -> suppliers -> bids
 *   php app/eservice/import/run.php bids           # one phase
 *   php app/eservice/import/run.php all --dry-run  # read + report, write nothing
 *
 * Reads legacy DB creds from import/config.php (copy of config.sample.php).
 * Idempotent — safe to re-run; existing rows are updated, not duplicated.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("run.php is CLI-only. Use import/index.php in the browser.\n");
}

require __DIR__ . '/lib.php';
require __DIR__ . '/dump_reader.php';
require __DIR__ . '/import_lookups.php';
require __DIR__ . '/import_suppliers.php';
require __DIR__ . '/import_bids.php';

$args   = array_slice($argv, 1);
$dry    = in_array('--dry-run', $args, true) || in_array('-n', $args, true);
$phase  = null;
$dumpFile = null;
foreach ($args as $a) {
    if (in_array($a, ['lookups', 'suppliers', 'bids', 'all'], true)) { $phase = $a; }
    elseif (str_starts_with($a, '--file=')) { $dumpFile = substr($a, 7); }
}
if (!$phase) {
    fwrite(STDERR, "Usage: php import/run.php [lookups|suppliers|bids|all] [--file=dump.sql] [--dry-run]\n");
    fwrite(STDERR, "  --file  read a .sql dump instead of a live legacy DB (import/config.php)\n");
    exit(1);
}

try {
    $dst    = es_import_target();
    $parsed = null;
    $cfg    = null;
    if ($dumpFile !== null) {
        if (!is_file($dumpFile)) throw new RuntimeException("dump file not found: $dumpFile");
        echo "Parsing $dumpFile …\n";
        $parsed = es_parse_dump($dumpFile);
        echo "  " . count($parsed) . " table(s): " . implode(', ', array_map(fn($t) => "$t(" . count($parsed[$t]) . ")", array_keys($parsed))) . "\n";
    } else {
        $cfg = es_import_src_config();
    }
} catch (Throwable $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    exit(1);
}

if (!$dst->query("SHOW TABLES LIKE 'es_bid_registry'")->num_rows) {
    fwrite(STDERR, "FATAL: es_ schema not installed. Run sql/01_schema.sql first.\n");
    exit(1);
}

$phases = $phase === 'all' ? ['lookups', 'suppliers', 'bids'] : [$phase];
$fn = ['lookups' => 'es_import_lookups', 'suppliers' => 'es_import_suppliers', 'bids' => 'es_import_bids'];

$grand = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0];
foreach ($phases as $p) {
    echo "\n=== phase: $p" . ($dry ? "  (DRY-RUN)" : "") . " ===\n";
    $x = new EsImport($dst, $cfg, $dry);
    if ($parsed !== null) $x->setDump($parsed);
    $t0 = microtime(true);
    try {
        $fn[$p]($x);
    } catch (Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $x->warn('phase aborted: ' . $e->getMessage());
    }
    $x->record(null, $p);
    echo "  " . str_replace("\n", "\n  ", $x->summaryLine()) . "\n";
    printf("  (%.1fs)\n", microtime(true) - $t0);
    foreach (['inserted', 'updated', 'skipped'] as $k) $grand[$k] += $x->counts[$k];
    $grand['warnings'] += count($x->warnings);
}

echo "\n--- total: +{$grand['inserted']} inserted, ~{$grand['updated']} updated, {$grand['skipped']} skipped, {$grand['warnings']} warning(s) ---\n";
