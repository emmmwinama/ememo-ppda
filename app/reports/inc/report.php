<?php
/** Reports — shared rendering + export helpers. Loaded by inc/layout.php. */

/**
 * Parse ?from / ?to (YYYY-MM-DD). Defaults to the last 90 days.
 * @return array{from:string,to:string,label:string,days:int}
 */
function rpt_range(): array {
    $today = date('Y-m-d');
    $to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')   ? $_GET['to']   : $today;
    $from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-90 days'));
    if ($from > $to) [$from, $to] = [$to, $from];
    $days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
    return ['from' => $from, 'to' => $to, 'days' => $days,
            'label' => date('j M Y', strtotime($from)) . ' – ' . date('j M Y', strtotime($to))];
}

/** SQL fragment: `col BETWEEN 'from 00:00' AND 'to 23:59'`. $col is trusted. */
function rpt_between(string $col, array $r): string {
    return "$col BETWEEN '{$r['from']} 00:00:00' AND '{$r['to']} 23:59:59'";
}

/** The date-range form + a CSV button. Call inside the page body. */
function rpt_toolbar(array $r, bool $csv = true): void {
    $qs = $_GET;
    $qs['export'] = 'csv';
    ?>
    <form class="f-toolbar" method="get">
      <?php foreach ($_GET as $k => $v): if (in_array($k, ['from', 'to', 'export'], true)) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v) ? '' : $v) ?>">
      <?php endforeach; ?>
      <div><label class="form-label mb-0 small text-muted">From</label>
        <input type="date" name="from" value="<?= e($r['from']) ?>" class="form-control form-control-sm f-control"></div>
      <div><label class="form-label mb-0 small text-muted">To</label>
        <input type="date" name="to" value="<?= e($r['to']) ?>" class="form-control form-control-sm f-control"></div>
      <button class="btn btn-outline-secondary btn-sm align-self-end" style="height:38px;">Apply</button>
      <?php if ($csv): ?>
        <a class="btn btn-outline-secondary btn-sm align-self-end" style="height:38px;"
           href="?<?= e(http_build_query($qs)) ?>"><i class="bi bi-download me-1"></i>CSV</a>
      <?php endif; ?>
    </form>
    <?php
}

/** KPI tiles. $tiles = [ [value, label, class?, icon?], ... ]. */
function rpt_kpis(array $tiles): void {
    echo '<div class="f-stats">';
    foreach ($tiles as $t) {
        $val = $t[0]; $lbl = $t[1]; $cls = $t[2] ?? 's-sky'; $icon = $t[3] ?? 'bi-bar-chart';
        echo '<div class="f-stat ' . e($cls) . '"><i class="bi ' . e($icon) . '"></i>'
           . '<div class="f-stat-value">' . e(is_numeric($val) ? number_format((float) $val, (floor($val) == $val ? 0 : 1)) : $val) . '</div>'
           . '<div class="f-stat-label">' . e($lbl) . '</div></div>';
    }
    echo '</div>';
}

/**
 * Render rows as a panel table.
 * $cols: ['key' => 'Label']  or  ['key' => ['label'=>, 'align'=>'end', 'fmt'=>fn($v,$row)=>string, 'raw'=>bool]]
 */
function rpt_table(array $cols, array $rows, string $title = '', string $icon = 'bi-table', string $empty = 'No data in this range.'): void {
    echo '<div class="f-panel' . ($rows ? ' table-responsive' : '') . '">';
    if ($title !== '') echo '<div class="f-panel-head"><i class="bi ' . e($icon) . '"></i> ' . e($title) . '</div>';
    if (!$rows) { echo '<div class="f-panel-body"><p class="text-muted small mb-0">' . e($empty) . '</p></div></div>'; return; }
    echo '<table class="table table-borderless f-table align-middle mb-0"><thead><tr>';
    foreach ($cols as $c) {
        $spec = is_array($c) ? $c : ['label' => $c];
        $al = ($spec['align'] ?? '') === 'end' ? ' class="text-end"' : '';
        echo "<th$al>" . e($spec['label'] ?? '') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($cols as $key => $c) {
            $spec = is_array($c) ? $c : [];
            $al   = ($spec['align'] ?? '') === 'end' ? ' class="text-end"' : '';
            $v    = $row[$key] ?? null;
            if (isset($spec['fmt'])) {
                $out = (string) $spec['fmt']($v, $row);
                echo "<td$al>" . (!empty($spec['raw']) ? $out : e($out)) . '</td>';
            } else {
                echo "<td$al>" . e($v ?? '—') . '</td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/** Horizontal count bars for a small distribution. $rows = [ [label, count], ... ]. */
function rpt_bars(array $rows, string $title = '', string $icon = 'bi-bar-chart-steps'): void {
    $max = 0;
    foreach ($rows as $x) $max = max($max, (float) $x[1]);
    $max = max(1, $max);
    echo '<div class="f-panel"><div class="f-panel-head"><i class="bi ' . e($icon) . '"></i> ' . e($title) . '</div><div class="f-panel-body">';
    if (!$rows) { echo '<p class="text-muted small mb-0">No data.</p></div></div>'; return; }
    foreach ($rows as [$lbl, $n]) {
        $pct = round($n / $max * 100);
        echo '<div style="display:flex;align-items:center;gap:.7rem;margin:.35rem 0;font-size:.85rem;">'
           . '<div style="width:190px;flex-shrink:0;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . e($lbl) . '</div>'
           . '<div style="flex:1;background:var(--bg);border-radius:6px;height:16px;overflow:hidden;">'
           . '<div style="height:100%;width:' . $pct . '%;background:var(--brand-light);border-right:2px solid var(--brand);"></div></div>'
           . '<div style="width:56px;text-align:right;font-weight:700;">' . e(number_format((float) $n)) . '</div></div>';
    }
    echo '</div></div>';
}

/** Time-series column chart from [ ['d'=>'YYYY-MM', 'n'=>int], ... ] rows (dense-fill is caller's job). */
function rpt_timebars(array $series, string $title, string $icon = 'bi-graph-up'): void {
    $peak = 1;
    foreach ($series as $s) $peak = max($peak, (float) ($s['n'] ?? $s[1] ?? 0));
    echo '<div class="f-panel"><div class="f-panel-head"><i class="bi ' . e($icon) . '"></i> ' . e($title) . '</div><div class="f-panel-body">';
    if (!$series) { echo '<p class="text-muted small mb-0">No data.</p></div></div>'; return; }
    echo '<div class="bars">';
    foreach ($series as $s) {
        $n = (float) ($s['n'] ?? $s[1] ?? 0);
        echo '<div class="bar" style="height:' . max(2, round($n / $peak * 118)) . 'px" title="' . e(($s['d'] ?? $s[0] ?? '') . ': ' . $n) . '">'
           . ($n ? '<span>' . e(number_format($n)) . '</span>' : '') . '</div>';
    }
    echo '</div><div class="bars-x">';
    foreach ($series as $s) echo '<span>' . e($s['d'] ?? $s[0] ?? '') . '</span>';
    echo '</div></div></div>';
}

/**
 * If ?export=csv, stream $rows as CSV and exit. Call BEFORE rpt_head().
 * $cols: same shape as rpt_table (fmt is applied; raw HTML is stripped).
 */
function rpt_maybe_csv(string $filename, array $cols, array $rows): void {
    if (($_GET['export'] ?? '') !== 'csv') return;
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $filename) . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_map(fn($c) => is_array($c) ? ($c['label'] ?? '') : $c, array_values($cols)));
    foreach ($rows as $row) {
        $line = [];
        foreach ($cols as $key => $c) {
            $spec = is_array($c) ? $c : [];
            $v = $row[$key] ?? '';
            if (isset($spec['fmt'])) $v = trim(strip_tags((string) $spec['fmt']($v, $row)));
            $line[] = $v;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/** Format seconds as a compact duration for report cells. */
function rpt_dur($secs): string {
    $s = (float) $secs;
    if ($s <= 0) return '—';
    if ($s < 3600)    return round($s / 60) . 'm';
    if ($s < 86400)   return round($s / 3600, 1) . 'h';
    return round($s / 86400, 1) . 'd';
}
