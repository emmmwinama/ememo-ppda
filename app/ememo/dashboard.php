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
?>
<style>
  .stat-card { border: 1px solid var(--border); border-radius: var(--radius-md); }
  .stat-icon {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
  }
  .stuck-table tbody tr:hover { background: #fff3cd; }
</style>

<div class="page-header d-flex align-items-center mb-4">
  <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</h4>
</div>

    <!-- KPI Cards -->
    <div class="row g-4 mb-5">
      <?php foreach ([
        ['Total',$total,'success','journal-text'],
        ['Pending',$pending,'warning','hourglass-split'],
        ['Approved',$approved,'primary','check-circle'],
        ['Rejected',$rejected,'danger','x-circle'],
        ['Returned',$returned,'secondary','arrow-counterclockwise'],
        ['Escalated',$escalated,'info','exclamation-circle'],
      ] as list($label,$value,$color,$icon)): ?>
        <div class="col-6 col-sm-4 col-lg-2">
          <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="stat-icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>">
                <i class="bi bi-<?= $icon ?>"></i>
              </div>
              <div>
                <small class="text-muted d-block"><?= $label ?></small>
                <h4 class="mb-0 text-<?= $color ?>"><?= $value ?></h4>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts & Transition Times -->
    <div class="row mb-5 gx-4 gy-4">
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-light">📊 Status Distribution</div>
          <div class="card-body"><canvas id="statusPie"></canvas></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-light">⏳ Avg Days in Status</div>
          <div class="card-body"><canvas id="avgBar"></canvas></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-light">🚦 Avg Transition Time</div>
          <div class="card-body d-flex justify-content-around">
            <div class="text-center">
              <small class="text-muted">Submit → Review</small><br>
              <strong><?= $durSubmitReview ?></strong>
            </div>
            <div class="text-center">
              <small class="text-muted">Review → Approve</small><br>
              <strong><?= $durReviewApprove ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Overdue Memos -->
<?php
// helper at top of your view
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
?>

<div class="card mb-5 shadow-sm">
  <div class="card-header bg-danger text-white">🚨 Overdue Memos (no action for over 3 days)</div>
  <div class="table-responsive">
    <table class="table mb-0 stuck-table">
      <thead class="table-light">
        <tr>
          <th>Memo ID</th>
          <th>Status</th>
          <th>Days Open</th>
          <th>Next Actor</th>
          <th>Last Actor</th>
          <th>Last Action</th>
          <th>When</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($overdue)): ?>
          <tr><td colspan="7" class="text-center text-muted">None</td></tr>
        <?php else: foreach ($overdue as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['memo_id']) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= (int)$r['days_open'] ?></td>
            <td><?= htmlspecialchars($r['next_actor']) ?></td>
            <td><?= htmlspecialchars($r['last_actor']) ?></td>
            <td><?= htmlspecialchars($r['last_action']) ?></td>
            <td>
              <?= htmlspecialchars(time_elapsed_string($r['last_when'])) ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded',()=>{
    new Chart(
      document.getElementById('statusPie'),
      {
        type:'pie',
        data:{
          labels:['Pending','Approved','Rejected','Returned','Escalated'],
          datasets:[{
            data:[<?= $pending?>,<?= $approved?>,<?= $rejected?>,<?= $returned?>,<?= $escalated?>],
            backgroundColor:['#ffc107','#0d6efd','#dc3545','#6c757d','#0dcaf0']
          }]
        },
        options:{responsive:true,plugins:{legend:{position:'bottom'}}}
      }
    );
    new Chart(
      document.getElementById('avgBar'),
      {
        type:'bar',
        data:{
          labels:[<?= implode(',',array_map(fn($d)=>"'".$d['status']."'", $avgDays))?>],
          datasets:[{
            label:'Avg Days',
            data:[<?= implode(',',array_map(fn($d)=>$d['avg_days'], $avgDays))?>],
            backgroundColor:'#198754'
          }]
        },
        options:{
          indexAxis:'y',
          scales:{x:{title:{display:true,text:'Days'}}},
          plugins:{legend:{display:false}}
        }
      }
    );
  });
  </script>
