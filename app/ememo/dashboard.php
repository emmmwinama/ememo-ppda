<?php
// dashboard.php
require_once 'auth.php';
require_once 'db.php';

/**
 * Convert an “HH:MM:SS” into a human-friendly span showing only
 * the two largest nonzero units.
 */
function humanDuration(string $hms): string {
    $hms   = preg_replace('/\.\d+$/', '', $hms);
    list($h, $m, $s) = explode(':', $hms) + [0,0,0];
    $total = $h*3600 + $m*60 + $s;
    $units = [
      'year'=>365*24*3600, 'month'=>30*24*3600, 'week'=>7*24*3600,
      'day'=>24*3600, 'hr'=>3600, 'min'=>60, 'sec'=>1,
    ];
    $parts = [];
    foreach ($units as $name=>$secPer) {
      if ($total >= $secPer) {
        $v      = floor($total/$secPer);
        $total %= $secPer;
        $label  = $name . ($v>1 ? ($name==='hr'?'s':'s') : '');
        $parts[]= "$v $label";
      }
      if (count($parts)===2) break;
    }
    return $parts?implode(' ', $parts):'0 sec';
}

/** Relative "x ago" string, largest unit only. */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $units = ['y'=>'year','m'=>'month','w'=>'week','d'=>'day','h'=>'hour','i'=>'minute','s'=>'second'];
    $strings = [];
    foreach ($units as $key => $text) {
        if ($diff->$key) {
            $strings[] = $diff->$key . ' ' . $text . ($diff->$key > 1 ? 's' : '');
        }
    }
    if (!$full) $strings = array_slice($strings, 0, 1);
    return $strings ? implode(', ', $strings) . ' ago' : 'just now';
}

// 1) Core counts
$total     = (int)$conn->query("SELECT COUNT(*) FROM memos")->fetch_row()[0];
$drafted   = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status='Draft'")->fetch_row()[0];
$pending   = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status IN('Submitted','Under Review')")->fetch_row()[0];
$approved  = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status='Approved'")->fetch_row()[0];
$rejected  = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status='Rejected'")->fetch_row()[0];
$returned  = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status='Returned'")->fetch_row()[0];
$escalated = (int)$conn->query("SELECT COUNT(*) FROM memos WHERE status='Escalated'")->fetch_row()[0];


// 2) Average days in each status
$avgDays = $conn->query("
  SELECT status, ROUND(AVG(DATEDIFF(NOW(),created_at)),1) AS avg_days
    FROM memos
   WHERE status NOT IN('Draft','Finalized')
   GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// 3) Top 10 Overdue (>3 days, pending action, excluding Returned)
$overdue = $conn->query(<<<'SQL'
WITH overdue AS (
  SELECT
    m.id,
    m.memo_id,
    m.status,
    DATEDIFF(NOW(),m.created_at) AS days_open,
    COALESCE(
      (SELECT u.full_name
         FROM memo_clarifications c
         JOIN users u ON u.id=c.user_id
        WHERE c.memo_id=m.id
          AND c.status='Pending'
        ORDER BY c.id LIMIT 1),
      CASE
        WHEN m.status IN('Submitted','Under Review') THEN (
          SELECT u.full_name
            FROM memo_through_recipients r
            JOIN users u ON u.id=r.user_id
           WHERE r.memo_id=m.id
             AND r.endorsement_status='Pending'
           ORDER BY r.id LIMIT 1
        )
        WHEN m.status='Endorsed' THEN (
          SELECT full_name FROM users WHERE id=m.to_user_id
        )
        WHEN m.status='Escalated' THEN (
          SELECT u2.full_name
            FROM memo_clarifications c2
            JOIN users u2 ON u2.id=c2.requested_by
           WHERE c2.memo_id=m.id
             AND c2.status='Pending'
           ORDER BY c2.id LIMIT 1
        )
        ELSE NULL
      END
    ) AS next_actor
  FROM memos m
  WHERE m.status NOT IN(
      'Draft',
      'Approved',    -- now excluding Approved
      'Rejected',
      'Finalized',
      'Returned'
    )
    AND DATEDIFF(NOW(),m.created_at) > 3
),
last_move AS (
  SELECT
    mm.memo_id,
    mm.from_user_id,
    mm.action     AS last_action,
    mm.timestamp
  FROM memo_movements mm
  JOIN (
    SELECT memo_id, MAX(timestamp) AS ts
      FROM memo_movements
     GROUP BY memo_id
  ) t ON t.memo_id=mm.memo_id AND t.ts=mm.timestamp
)
SELECT
  o.memo_id    AS memo_id,
  o.status     AS status,
  o.days_open  AS days_open,
  o.next_actor AS next_actor,
  u.full_name  AS last_actor,
  lm.last_action,
  DATE_FORMAT(lm.timestamp,'%Y-%m-%d %H:%i') AS last_when
FROM overdue o
LEFT JOIN last_move lm ON lm.memo_id = o.id
LEFT JOIN users u       ON u.id        = lm.from_user_id
WHERE o.next_actor IS NOT NULL
ORDER BY o.days_open DESC
LIMIT 10;
SQL
)->fetch_all(MYSQLI_ASSOC);



// 4) Average transition durations
$avgSubmitToReview = $conn->query("
  SELECT IFNULL(SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND,m.created_at,ur.dt))),'00:00:00')
  FROM memos m
  JOIN (
    SELECT memo_id,MIN(timestamp) AS dt
      FROM memo_movements
     WHERE action='Under Review'
     GROUP BY memo_id
  ) ur ON ur.memo_id=m.id
")->fetch_row()[0] ?? '00:00:00';

$avgReviewToApprove = $conn->query("
  SELECT IFNULL(SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND,ur.dt,ap.dt))),'00:00:00')
  FROM (
    SELECT memo_id,MIN(timestamp) AS dt
      FROM memo_movements
     WHERE action='Under Review'
     GROUP BY memo_id
  ) ur
  JOIN (
    SELECT memo_id,MIN(timestamp) AS dt
      FROM memo_movements
     WHERE action='Approved'
     GROUP BY memo_id
  ) ap ON ap.memo_id=ur.memo_id
")->fetch_row()[0] ?? '00:00:00';

$durSubmitReview  = humanDuration($avgSubmitToReview);
$durReviewApprove = humanDuration($avgReviewToApprove);

// Tint class per status pill in the overdue table
$pillFor = function (string $status): string {
    return [
      'Submitted'    => 't-amber',
      'Under Review' => 't-amber',
      'Endorsed'     => 't-sky',
      'Escalated'    => 't-sky',
      'Approved'     => 't-green',
      'Rejected'     => 't-rose',
      'Returned'     => 't-slate',
    ][$status] ?? 't-neutral';
};
?>
<style>
  /* ── Dashboard (Farmis-style clean surface) ─────────────────────── */
  .dash { --gap: 1rem; }
  .dash-head { margin-bottom: 1.4rem; }
  .dash-title { font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text); }
  .dash-subtitle { color: var(--muted); font-size: .9rem; margin: .2rem 0 0; }

  .kpi-row { display: flex; flex-wrap: wrap; gap: var(--gap); margin-bottom: 1.6rem; }
  .kpi {
    flex: 1 1 150px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1rem 1.1rem;
    box-shadow: var(--shadow-flat);
    transition: box-shadow .18s ease, transform .18s ease;
  }
  .kpi:hover { box-shadow: var(--shadow-flat-hover); transform: translateY(-1px); }
  .kpi-icon {
    width: 38px; height: 38px; border-radius: 11px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.05rem; margin-bottom: .6rem;
  }
  .kpi-value { font-size: 1.6rem; font-weight: 800; line-height: 1; color: var(--text); }
  .kpi-label { font-size: .8rem; color: var(--muted); margin-top: .35rem; }

  .panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-flat);
    height: 100%;
  }
  .panel-head {
    display: flex; align-items: center; gap: .5rem;
    padding: .85rem 1.1rem;
    font-weight: 600; font-size: .95rem; color: var(--text);
    border-bottom: 1px solid var(--border);
  }
  .panel-head .bi { color: var(--muted); font-size: 1rem; }
  .panel-head .count-pill { margin-left: auto; }
  .panel-body { padding: 1.1rem; }
  .panel-body canvas { max-height: 230px; }

  .mini-row { display: flex; }
  .mini { flex: 1; text-align: center; padding: .4rem .5rem; }
  .mini + .mini { border-left: 1px solid var(--border); }
  .mini-label { font-size: .78rem; color: var(--muted); }
  .mini-value { font-size: 1.15rem; font-weight: 700; color: var(--text); margin-top: .25rem; }

  .overdue-table { margin: 0; font-size: .9rem; }
  .overdue-table thead th {
    background: var(--bg); color: var(--muted);
    font-weight: 600; border-bottom: 1px solid var(--border); white-space: nowrap;
  }
  .overdue-table tbody td { vertical-align: middle; }
  .overdue-table tbody tr:hover { background: #fff7ed; }
  .overdue-table .memo-ref { font-weight: 600; color: var(--text); }

  .pill {
    display: inline-block; padding: .15rem .6rem;
    border-radius: var(--radius-pill); font-size: .74rem; font-weight: 600;
  }
  .count-pill {
    display: inline-block; min-width: 1.5rem; text-align: center;
    padding: .1rem .5rem; border-radius: var(--radius-pill);
    font-size: .78rem; font-weight: 700;
  }

  /* Tint tokens — soft background + readable foreground */
  .t-dark    { background: #e8ebee; color: #1f2937; }
  .t-neutral { background: #eef1f4; color: #475569; }
  .t-slate   { background: #eef1f4; color: #475569; }
  .t-green   { background: var(--brand-light); color: var(--brand-dark); }
  .t-amber   { background: #fdefda; color: #b45309; }
  .t-rose    { background: #fdecee; color: #be123c; }
  .t-sky     { background: #e6f4fb; color: #0369a1; }

  @media (max-width: 575.98px) {
    .kpi { flex-basis: 42%; }
  }
</style>

<div class="dash">

  <div class="dash-head">
    <h1 class="dash-title">Dashboard</h1>
    <p class="dash-subtitle">Overview of memo activity and workflow health</p>
  </div>

  <!-- KPI cards -->
  <div class="kpi-row">
    <?php foreach ([
      ['Total memos', $total,     't-dark',    'journal-text'],
      ['Drafts',      $drafted,   't-neutral', 'pencil-square'],
      ['Pending',     $pending,   't-amber',   'hourglass-split'],
      ['Approved',    $approved,  't-green',   'check2-circle'],
      ['Rejected',    $rejected,  't-rose',    'x-circle'],
      ['Returned',    $returned,  't-slate',   'arrow-counterclockwise'],
      ['Escalated',   $escalated, 't-sky',     'exclamation-triangle'],
    ] as [$label, $value, $tint, $icon]): ?>
      <div class="kpi">
        <div class="kpi-icon <?= $tint ?>"><i class="bi bi-<?= $icon ?>"></i></div>
        <div class="kpi-value"><?= number_format($value) ?></div>
        <div class="kpi-label"><?= $label ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts & transition times -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-pie-chart"></i> Status distribution</div>
        <div class="panel-body"><canvas id="statusPie"></canvas></div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-bar-chart"></i> Avg days in status</div>
        <div class="panel-body"><canvas id="avgBar"></canvas></div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-stopwatch"></i> Avg transition time</div>
        <div class="panel-body">
          <div class="mini-row">
            <div class="mini">
              <div class="mini-label">Submit → Review</div>
              <div class="mini-value"><?= htmlspecialchars($durSubmitReview) ?></div>
            </div>
            <div class="mini">
              <div class="mini-label">Review → Approve</div>
              <div class="mini-value"><?= htmlspecialchars($durReviewApprove) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overdue memos -->
  <div class="panel">
    <div class="panel-head">
      <i class="bi bi-exclamation-triangle" style="color:#b45309;"></i>
      Overdue memos
      <span class="text-muted fw-normal ms-1" style="font-size:.82rem;">no action for over 3 days</span>
      <span class="count-pill t-amber"><?= count($overdue) ?></span>
    </div>
    <?php if (empty($overdue)): ?>
      <div class="f-state">
        <i class="bi bi-check2-circle"></i>
        <p>Nothing overdue — every memo has moved in the last 3 days.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-borderless overdue-table">
        <thead>
          <tr>
            <th>Memo ID</th>
            <th>Status</th>
            <th>Days open</th>
            <th>Next actor</th>
            <th>Last actor</th>
            <th>Last action</th>
            <th>When</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($overdue as $r): ?>
            <tr>
              <td class="memo-ref"><?= htmlspecialchars($r['memo_id']) ?></td>
              <td><span class="pill <?= $pillFor($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
              <td><span class="pill <?= (int)$r['days_open'] > 7 ? 't-rose' : 't-amber' ?>"><?= (int)$r['days_open'] ?>d</span></td>
              <td><?= htmlspecialchars($r['next_actor']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($r['last_actor'] ?? '—') ?></td>
              <td class="text-muted"><?= htmlspecialchars($r['last_action'] ?? '—') ?></td>
              <td class="text-muted"><?= $r['last_when'] ? htmlspecialchars(time_elapsed_string($r['last_when'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font.family = "'Segoe UI', system-ui, -apple-system, sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.color       = '#6b7280';

  const tint = {
    amber: '#E8A13C', green: '#2A8F2E', rose: '#E0566B',
    slate: '#94A3B8', sky: '#4CA9D9',
  };

  new Chart(document.getElementById('statusPie'), {
    type: 'doughnut',
    data: {
      labels: ['Pending', 'Approved', 'Rejected', 'Returned', 'Escalated'],
      datasets: [{
        data: [<?= $pending ?>, <?= $approved ?>, <?= $rejected ?>, <?= $returned ?>, <?= $escalated ?>],
        backgroundColor: [tint.amber, tint.green, tint.rose, tint.slate, tint.sky],
        borderWidth: 2,
        borderColor: '#ffffff',
      }]
    },
    options: {
      responsive: true,
      cutout: '62%',
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 14, usePointStyle: true } }
      }
    }
  });

  new Chart(document.getElementById('avgBar'), {
    type: 'bar',
    data: {
      labels: [<?= implode(',', array_map(fn($d) => "'".addslashes($d['status'])."'", $avgDays)) ?>],
      datasets: [{
        label: 'Avg days',
        data: [<?= implode(',', array_map(fn($d) => $d['avg_days'], $avgDays)) ?>],
        backgroundColor: '#2A8F2E',
        borderRadius: 5,
        barThickness: 16,
      }]
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: {
        x: {
          title: { display: true, text: 'Days' },
          grid: { color: '#eef1f4' },
          border: { display: false }
        },
        y: { grid: { display: false }, border: { display: false } }
      }
    }
  });
});
</script>
