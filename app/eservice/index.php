<?php
/** e-Services dashboard — pipeline, turnaround and throughput at a glance. */
require __DIR__ . '/inc/layout.php';

global $conn, $ES_UID;

$installed = (bool) $conn->query("SHOW TABLES LIKE 'es_bid_registry'")->num_rows;

$D = [];        // data the charts read
$recent = [];

if ($installed) {
    $one = fn(string $sql) => db_one($sql) ?? [];

    /* ---- headline KPIs ------------------------------------------------ */
    $D['kpi'] = [
        'open'    => (int) ($one("SELECT COUNT(*) c FROM es_bid_registry
                                   WHERE status IN ('pending_registry','pending_allocation','assigned','in_analysis')")['c'] ?? 0),
        'review'  => (int) ($one("SELECT COUNT(*) c FROM es_bid_analysis WHERE stage NOT IN ('approved','rejected')")['c'] ?? 0),
        'overdue' => (int) ($one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id
                                   WHERE a.stage NOT IN ('approved','rejected')
                                     AND COALESCE(a.submitted_at, r.ts_create) < (NOW() - INTERVAL 14 DAY)")['c'] ?? 0),
        'done30'  => (int) ($one("SELECT COUNT(*) c FROM es_bid_analysis WHERE decided_at >= (NOW() - INTERVAL 30 DAY)")['c'] ?? 0),
        'e2e'     => (int) round((float) ($one("SELECT AVG(DATEDIFF(a.decided_at, r.ts_create)) d
                                   FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id
                                   WHERE a.decided_at IS NOT NULL")['d'] ?? 0)),
        'dispatch' => (int) ($one("SELECT COUNT(*) c FROM es_pde_response WHERE published = 1 AND sent_at IS NULL")['c'] ?? 0),
    ];

    /* ---- pipeline: where every submission sits ---------------------- */
    $rg = $one("SELECT
        SUM(status = 'pending_registry')   AS reg_check,
        SUM(status = 'returned_to_pde')    AS returned,
        SUM(status = 'pending_allocation') AS awaiting,
        SUM(status = 'assigned' AND NOT EXISTS (SELECT 1 FROM es_bid_analysis a WHERE a.registry_id = r.id)) AS allocated
      FROM es_bid_registry r");
    $an = $one("SELECT
        SUM(stage IN ('draft','returned') AND archived = 0) AS draft,
        SUM(stage = 'supervisor_review'   AND archived = 0) AS sup,
        SUM(stage = 'director_review'     AND archived = 0) AS dir,
        SUM(stage = 'dg_review'           AND archived = 0) AS dg,
        SUM(stage = 'board_review'        AND archived = 0) AS board,
        SUM(archived = 1)                                   AS archived
      FROM es_bid_analysis");
    // work in progress only — a 'completed' bar would eventually dwarf the rest
    $D['pipeline'] = [
        ['Registry check',          (int) $rg['reg_check'], 'amber'],
        ['Returned to PDE',         (int) $rg['returned'],  'rose'],
        ['Awaiting allocation',     (int) $rg['awaiting'],  'amber'],
        ['Allocated · not started', (int) $rg['allocated'], 'violet'],
        ['Draft analysis',          (int) $an['draft'],     'sky'],
        ['Supervisor review',       (int) $an['sup'],       'sky'],
        ['Director review',         (int) $an['dir'],       'sky'],
        ['DG review',               (int) $an['dg'],        'sky'],
        ['Board review',            (int) $an['board'],     'sky'],
        ['Archived',                (int) $an['archived'],  'slate'],
    ];

    /* ---- active workload per person, by review tier (archived still counts) --- */
    $loadByStage = fn(string $stage) => db_all(
        "SELECT COALESCE(u.full_name, 'In queue') AS name, COUNT(*) AS n
           FROM es_bid_analysis a
           LEFT JOIN users u ON u.id = COALESCE(
             (SELECT t.from_user_id FROM es_bid_routing t
                WHERE t.analysis_id = a.id AND t.from_stage = '$stage'
                  AND t.action IN ('submit','endorse','return','approve','reject','archive')
                ORDER BY t.id DESC LIMIT 1),
             CASE WHEN a.stage = '$stage' THEN a.current_owner_id END)
          WHERE a.stage NOT IN ('approved','rejected')
            AND (a.stage = '$stage' OR EXISTS (SELECT 1 FROM es_bid_routing t
                                               WHERE t.analysis_id = a.id AND t.from_stage = '$stage'))
          GROUP BY name ORDER BY (name = 'In queue'), n DESC, name LIMIT 12"
    );
    $D['load'] = [
        'reviewer'   => db_all("SELECT o.full_name AS name, COUNT(*) AS n
                                  FROM es_bid_analysis a JOIN users o ON o.id = a.officer_id
                                 WHERE a.stage NOT IN ('approved','rejected')
                                 GROUP BY o.id, o.full_name ORDER BY n DESC, name LIMIT 12"),
        'supervisor' => $loadByStage('supervisor_review'),
        'director'   => $loadByStage('director_review'),
    ];

    /* ---- average turnaround by stage (completed determinations) ----- */
    $aids = array_column(db_all("SELECT id FROM es_bid_analysis WHERE ts_create >= (NOW() - INTERVAL 12 MONTH)"), 'id');
    $tmap = $aids ? es_turnaround_map($aids) : [];
    $acc  = ['officer' => [], 'supervisor' => [], 'director' => [], 'dg' => [], 'board' => []];
    foreach ($tmap as $roles) {
        foreach ($roles as $role => $t) {
            if (empty($t['open']) && isset($acc[$role])) $acc[$role][] = (int) $t['secs'];
        }
    }
    $avgD = fn(array $a) => $a ? round(array_sum($a) / count($a) / 86400, 1) : 0.0;
    $regAvgD = round((float) ($one("SELECT AVG(TIMESTAMPDIFF(HOUR, ts_create, registry_checked_at)) / 24 d
                                     FROM es_bid_registry WHERE registry_checked_at IS NOT NULL")['d'] ?? 0), 1);
    // [label, avg-days, green-ceiling, amber-ceiling]
    $D['turn'] = [
        ['Registry check',   $regAvgD,                 3, 7],
        ['Officer analysis', $avgD($acc['officer']),    7, 14],
        ['Supervisor',       $avgD($acc['supervisor']), 3, 7],
        ['Director',         $avgD($acc['director']),   3, 7],
        ['DG',               $avgD($acc['dg']),         3, 7],
        ['Board',            $avgD($acc['board']),      5, 12],
    ];

    /* ---- throughput: received vs completed, last 6 months ---------- */
    $recBy = $doneBy = [];
    foreach (db_all("SELECT DATE_FORMAT(ts_create,'%Y-%m') ym, COUNT(*) n FROM es_bid_registry
                      WHERE ts_create >= DATE_FORMAT(NOW() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY ym") as $x) {
        $recBy[$x['ym']] = (int) $x['n'];
    }
    foreach (db_all("SELECT DATE_FORMAT(decided_at,'%Y-%m') ym, COUNT(*) n FROM es_bid_analysis
                      WHERE decided_at >= DATE_FORMAT(NOW() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY ym") as $x) {
        $doneBy[$x['ym']] = (int) $x['n'];
    }
    $D['flow'] = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i month"));
        $D['flow'][] = ['label' => date('M', strtotime("$ym-01")),
                        'received' => $recBy[$ym] ?? 0, 'completed' => $doneBy[$ym] ?? 0];
    }

    /* ---- age of open items --------------------------------------- */
    $ab = $one("SELECT
        SUM(d <= 7) b1, SUM(d BETWEEN 8 AND 14) b2, SUM(d BETWEEN 15 AND 30) b3, SUM(d > 30) b4
      FROM (SELECT DATEDIFF(NOW(), ts_create) d FROM es_bid_registry
             WHERE status NOT IN ('completed','closed','withdrawn','returned_to_pde')) x");
    $D['age'] = [(int) $ab['b1'], (int) $ab['b2'], (int) $ab['b3'], (int) $ab['b4']];

    /* ---- outcomes & intake mix --------------------------------- */
    $oc = $one("SELECT SUM(final_outcome = 'no_objection') g, SUM(final_outcome = 'objection') w
                 FROM es_bid_analysis WHERE stage IN ('approved','rejected')");
    $D['outcome'] = ['granted' => (int) $oc['g'], 'withheld' => (int) $oc['w']];

    $mix = $one("SELECT SUM(origin = 'pde') pde, SUM(origin = 'registry') reg,
                        SUM(importance = 'urgent') urgent, SUM(importance = 'high') high, SUM(importance = 'normal') normal
                 FROM es_bid_registry");
    $D['mix'] = array_map('intval', $mix ?: []);

    /* ---- volume by PDE ---------------------------------------- */
    $D['pde'] = db_all("SELECT p.name, COUNT(*) n FROM es_bid_registry r JOIN es_pde p ON p.id = r.pde_id
                         GROUP BY p.id, p.name ORDER BY n DESC, p.name LIMIT 8");

    $recent = db_all(
        "SELECT r.id, r.serial_no, r.subject, r.status, r.ts_update, p.name AS pde_name, c.full_name AS owner_name
           FROM es_bid_registry r
           LEFT JOIN es_pde p ON p.id = r.pde_id
           LEFT JOIN es_bid_analysis a ON a.registry_id = r.id
           LEFT JOIN users c ON c.id = a.current_owner_id
          ORDER BY r.ts_update DESC LIMIT 8"
    );
}

$mixTot  = max(1, ($D['mix']['pde'] ?? 0) + ($D['mix']['reg'] ?? 0));
$prioTot = max(1, ($D['mix']['urgent'] ?? 0) + ($D['mix']['high'] ?? 0) + ($D['mix']['normal'] ?? 0));

es_layout_head('Dashboard', 'dashboard');
?>

<style>
  .dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1rem; margin-top: 1rem; }
  .dash-grid > .f-panel { margin: 0 !important; }
  .dash-canvas { position: relative; height: 250px; }
  .dash-canvas.is-lg { height: 340px; }
  .dash-seg { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: var(--bg); margin: .35rem 0 .3rem; }
  .dash-seg i { display: block; height: 100%; }
  .dash-seg-key { display: flex; flex-wrap: wrap; gap: .3rem 1.1rem; font-size: .78rem; color: var(--muted); }
  .dash-seg-key b { color: var(--text); }
  .dash-mixlbl { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-top: 1rem; }
</style>

<div class="f-head">
  <h1 class="f-title">e-Services</h1>
  <p class="f-subtitle">Bid-analysis pipeline, turnaround and throughput</p>
</div>

<?php if (!$installed): ?>
  <div class="f-panel" style="padding:1.25rem; border-color:#f3ddb6; background:#fffdf5;">
    <h2 style="font-size:1rem; font-weight:800; margin:0 0 .4rem;">Schema not installed yet</h2>
    <p class="text-muted" style="margin:0 0 .6rem; font-size:.9rem;">Load the SQL files into the ememo database, then reload:</p>
    <pre style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-md); padding:.8rem; font-size:.82rem; margin:0;">mysql … &lt; app/eservice/sql/01_schema.sql
mysql … &lt; app/eservice/sql/03_demo_seed.sql</pre>
  </div>

<?php else: ?>

  <div class="f-stats">
    <div class="f-stat s-sky"><i class="bi bi-inboxes"></i>
      <div class="f-stat-value"><?= number_format($D['kpi']['open']) ?></div><div class="f-stat-label">Open in the pipeline</div></div>
    <div class="f-stat s-amber"><i class="bi bi-clipboard-data"></i>
      <div class="f-stat-value"><?= number_format($D['kpi']['review']) ?></div><div class="f-stat-label">In review</div></div>
    <div class="f-stat <?= $D['kpi']['overdue'] > 0 ? 's-rose' : 's-slate' ?>"><i class="bi bi-alarm"></i>
      <div class="f-stat-value"><?= number_format($D['kpi']['overdue']) ?></div><div class="f-stat-label">Overdue (&gt; 14d)</div></div>
    <div class="f-stat s-green"><i class="bi bi-check2-circle"></i>
      <div class="f-stat-value"><?= number_format($D['kpi']['done30']) ?></div><div class="f-stat-label">Decided (30 days)</div></div>
    <div class="f-stat s-violet"><i class="bi bi-hourglass-split"></i>
      <div class="f-stat-value"><?= $D['kpi']['e2e'] ?: '—' ?></div><div class="f-stat-label">Avg days, receipt&nbsp;&rarr;&nbsp;decision</div></div>
    <div class="f-stat s-amber"><i class="bi bi-envelope-paper"></i>
      <div class="f-stat-value"><?= number_format($D['kpi']['dispatch']) ?></div><div class="f-stat-label">Responses to dispatch</div></div>
  </div>

  <div class="f-panel" style="margin-top:1.6rem;">
    <div class="f-panel-head"><i class="bi bi-diagram-3"></i> Where every submission sits</div>
    <div class="f-panel-body"><div class="dash-canvas is-lg"><canvas id="pipelineChart"></canvas></div></div>
  </div>

  <div class="dash-grid">
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-speedometer2"></i> Average turnaround by stage</div>
      <div class="f-panel-body"><div class="dash-canvas"><canvas id="turnChart"></canvas></div></div>
    </div>
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-bar-chart-line"></i> Received vs decided — 6 months</div>
      <div class="f-panel-body"><div class="dash-canvas"><canvas id="flowChart"></canvas></div></div>
    </div>
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-clock-history"></i> Age of open items</div>
      <div class="f-panel-body"><div class="dash-canvas"><canvas id="ageChart"></canvas></div></div>
    </div>
    <div class="f-panel">
      <div class="f-panel-head"><i class="bi bi-pie-chart"></i> Outcomes &amp; intake</div>
      <div class="f-panel-body">
        <div class="dash-canvas" style="height:170px;"><canvas id="outcomeChart"></canvas></div>

        <div class="dash-mixlbl">Intake source</div>
        <div class="dash-seg">
          <i style="width:<?= round(($D['mix']['pde'] ?? 0) / $mixTot * 100) ?>%;background:#0ea5e9"></i>
          <i style="width:<?= round(($D['mix']['reg'] ?? 0) / $mixTot * 100) ?>%;background:#94a3b8"></i>
        </div>
        <div class="dash-seg-key">
          <span><b><?= (int) ($D['mix']['pde'] ?? 0) ?></b> PDE upload</span>
          <span><b><?= (int) ($D['mix']['reg'] ?? 0) ?></b> registry-entered</span>
        </div>

        <div class="dash-mixlbl">Priority mix</div>
        <div class="dash-seg">
          <i style="width:<?= round(($D['mix']['urgent'] ?? 0) / $prioTot * 100) ?>%;background:#f43f5e"></i>
          <i style="width:<?= round(($D['mix']['high'] ?? 0) / $prioTot * 100) ?>%;background:#f59e0b"></i>
          <i style="width:<?= round(($D['mix']['normal'] ?? 0) / $prioTot * 100) ?>%;background:#94a3b8"></i>
        </div>
        <div class="dash-seg-key">
          <span><b><?= (int) ($D['mix']['urgent'] ?? 0) ?></b> urgent</span>
          <span><b><?= (int) ($D['mix']['high'] ?? 0) ?></b> high</span>
          <span><b><?= (int) ($D['mix']['normal'] ?? 0) ?></b> normal</span>
        </div>
      </div>
    </div>
  </div>

  <div class="dash-grid" style="margin-top:1rem;">
    <?php foreach ([
      ['reviewer',   'bi-person-badge',  'Active reviews per reviewer'],
      ['supervisor', 'bi-person-check',  'Active reviews per supervisor'],
      ['director',   'bi-person-vcard',  'Active reviews per director'],
    ] as [$k, $icon, $title]): ?>
      <div class="f-panel">
        <div class="f-panel-head"><i class="bi <?= $icon ?>"></i> <?= $title ?></div>
        <div class="f-panel-body">
          <?php if (!$D['load'][$k]): ?>
            <p class="text-muted small mb-0" style="padding:1.5rem 0;text-align:center;">Nothing active at this tier.</p>
          <?php else: ?>
            <div class="dash-canvas" style="height:<?= max(150, count($D['load'][$k]) * 30) ?>px;"><canvas id="load-<?= $k ?>"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($D['pde']): ?>
    <div class="f-panel" style="margin-top:1rem;">
      <div class="f-panel-head"><i class="bi bi-building"></i> Submissions by procuring entity</div>
      <div class="f-panel-body"><div class="dash-canvas" style="height:<?= max(180, count($D['pde']) * 34) ?>px;"><canvas id="pdeChart"></canvas></div></div>
    </div>
  <?php endif; ?>

  <div class="f-panel" style="margin-top:1rem;">
    <div class="f-panel-head"><i class="bi bi-activity"></i> Recent registry activity</div>
    <?php if (!$recent): ?>
      <div class="f-state"><i class="bi bi-journal"></i><p>Nothing in the registry yet.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-borderless f-table align-middle mb-0">
          <thead><tr><th>Serial</th><th>Subject</th><th>PDE</th><th>Status</th><th>With</th><th>Updated</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td class="font-monospace">
                  <a href="bid_registry_view.php?id=<?= (int) $r['id'] ?>" class="text-decoration-none"
                     data-drawer="bid_submission_peek.php?id=<?= (int) $r['id'] ?>&amp;partial=1"
                     data-drawer-title="Submission &middot; <?= e($r['serial_no']) ?>"><?= e($r['serial_no']) ?></a>
                </td>
                <td class="fw-semibold"><?= e($r['subject']) ?></td>
                <td class="text-muted"><?= e($r['pde_name'] ?? '—') ?></td>
                <td><?= es_status_badge($r['status']) ?></td>
                <td class="text-muted"><?= e($r['owner_name'] ?? '—') ?></td>
                <td class="text-muted"><?= e(date('d M Y', strtotime($r['ts_update']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
  (function () {
    if (typeof Chart === 'undefined') return;
    var D = <?= json_encode($D, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var $ = function (id) { return document.getElementById(id); };
    var css = getComputedStyle(document.documentElement);
    var v = function (n, f) { return (css.getPropertyValue(n) || '').trim() || f; };

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = v('--muted', '#6b7280');
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 14;

    var GRID = v('--border', '#e3e7ea');
    var SURF = v('--surface', '#ffffff');
    var P = { green: '#2f9e44', sky: '#0ea5e9', amber: '#f59e0b', rose: '#f43f5e', violet: '#8b5cf6', slate: '#94a3b8', orange: '#ea7317' };
    var hBar = function () { return { indexAxis: 'y', maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } },
                y: { grid: { display: false }, border: { display: false } } } }; };
    var vBar = function () { return { maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } } } }; };

    new Chart($('pipelineChart'), {
      type: 'bar',
      data: { labels: D.pipeline.map(function (r) { return r[0]; }),
        datasets: [{ data: D.pipeline.map(function (r) { return r[1]; }),
          backgroundColor: D.pipeline.map(function (r) { return P[r[2]]; }), borderRadius: 5, barThickness: 15 }] },
      options: Object.assign(hBar(), { plugins: { legend: { display: false },
        tooltip: { callbacks: { label: function (c) { return c.parsed.x + (c.parsed.x === 1 ? ' submission' : ' submissions'); } } } } })
    });

    var tc = D.turn.map(function (r) { return r[1] <= r[2] ? P.green : (r[1] <= r[3] ? P.amber : P.rose); });
    new Chart($('turnChart'), {
      type: 'bar',
      data: { labels: D.turn.map(function (r) { return r[0]; }),
        datasets: [{ data: D.turn.map(function (r) { return r[1]; }), backgroundColor: tc, borderRadius: 5, barThickness: 15 }] },
      options: { indexAxis: 'y', maintainAspectRatio: false,
        plugins: { legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.parsed.x + ' days on average'; } } } },
        scales: { x: { beginAtZero: true, title: { display: true, text: 'days' }, grid: { color: GRID }, border: { display: false } },
                  y: { grid: { display: false }, border: { display: false } } } }
    });

    new Chart($('flowChart'), {
      type: 'bar',
      data: { labels: D.flow.map(function (r) { return r.label; }),
        datasets: [
          { label: 'Received', data: D.flow.map(function (r) { return r.received; }), backgroundColor: P.sky, borderRadius: 4, categoryPercentage: 0.6, barPercentage: 0.8 },
          { label: 'Decided', data: D.flow.map(function (r) { return r.completed; }), backgroundColor: P.green, borderRadius: 4, categoryPercentage: 0.6, barPercentage: 0.8 }
        ] },
      options: Object.assign(vBar(), { plugins: { legend: { position: 'bottom' } } })
    });

    new Chart($('ageChart'), {
      type: 'bar',
      data: { labels: ['0–7d', '8–14d', '15–30d', '30d+'],
        datasets: [{ data: D.age, backgroundColor: [P.green, P.amber, P.orange, P.rose], borderRadius: 5, barThickness: 40 }] },
      options: vBar()
    });

    var oEl = $('outcomeChart');
    if (D.outcome.granted + D.outcome.withheld > 0) {
      new Chart(oEl, {
        type: 'doughnut',
        data: { labels: ['No-objection granted', 'No-objection withheld'],
          datasets: [{ data: [D.outcome.granted, D.outcome.withheld], backgroundColor: [P.green, P.rose], borderWidth: 2, borderColor: SURF }] },
        options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } }
      });
    } else if (oEl) {
      oEl.parentNode.innerHTML = '<p class="text-muted small" style="text-align:center;padding:2.5rem 0 1rem;margin:0;">No decisions issued yet.</p>';
    }

    if ($('pdeChart')) {
      new Chart($('pdeChart'), {
        type: 'bar',
        data: { labels: D.pde.map(function (r) { return r.name; }),
          datasets: [{ data: D.pde.map(function (r) { return +r.n; }), backgroundColor: P.sky, borderRadius: 5, barThickness: 14 }] },
        options: hBar()
      });
    }

    ['reviewer', 'supervisor', 'director'].forEach(function (k) {
      var el = $('load-' + k), rows = D.load[k] || [];
      if (!el || !rows.length) return;
      new Chart(el, {
        type: 'bar',
        data: { labels: rows.map(function (r) { return r.name; }),
          datasets: [{ data: rows.map(function (r) { return +r.n; }),
            backgroundColor: rows.map(function (r) { return r.name === 'In queue' ? P.slate : P.violet; }),
            borderRadius: 5, barThickness: 14 }] },
        options: Object.assign(hBar(), { plugins: { legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.parsed.x + (c.parsed.x === 1 ? ' active review' : ' active reviews'); } } } } })
      });
    });
  })();
  </script>

<?php endif; ?>

<?php es_layout_foot(); ?>
