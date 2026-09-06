<?php
/** Submission responses — the PDE response letters for finalised reviews.
 *  Only analyses finalised by the DG or the Board appear here. */
require __DIR__ . '/inc/layout.php';
es_require_role('registry', 'officer', 'supervisor', 'director', 'dg', 'board');

global $conn, $ES_UID;

$canDispatch = es_can('response.dispatch');

$q    = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 10;

// "latest response row for the analysis"
$respJoin = "LEFT JOIN es_pde_response pr ON pr.id = (SELECT MAX(x.id) FROM es_pde_response x WHERE x.analysis_id = a.id)";
$finalised = "a.stage IN ('approved','rejected')";

$TABS = [
    'unpublished' => ['Unpublished',     "$finalised AND (pr.id IS NULL OR pr.published = 0)"],
    'published'   => ['Newly published', "$finalised AND pr.published = 1 AND pr.sent_at IS NULL"],
    'sent'        => ['Sent',            "$finalised AND pr.published = 1 AND pr.sent_at IS NOT NULL"],
];
$tab = isset($_GET['tab'], $TABS[$_GET['tab']]) ? $_GET['tab'] : 'unpublished';
$carry = ['tab' => $tab, 'q' => $q];

$cnt = [];
foreach ($TABS as $k => [$lbl, $w]) {
    $cnt[$k] = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id $respJoin WHERE $w")['c'] ?? 0);
}

$where = [$TABS[$tab][1]];
$types = ''; $args = [];
if ($q !== '') { $where[] = '(r.serial_no LIKE ? OR r.subject LIKE ? OR p.name LIKE ?)'; $l = "%$q%"; $types .= 'sss'; array_push($args, $l, $l, $l); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int) (db_one("SELECT COUNT(*) c FROM es_bid_analysis a JOIN es_bid_registry r ON r.id = a.registry_id LEFT JOIN es_pde p ON p.id = r.pde_id $respJoin $whereSql", $types, $args)['c'] ?? 0);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$rows = db_all(
    "SELECT a.id AS analysis_id, a.stage, a.final_outcome, a.decided_at,
            r.serial_no, r.subject, p.name AS pde_name,
            pr.id AS response_id, pr.body AS response_body, pr.published, pr.published_at,
            pr.sent_at, pr.sent_method, sb.full_name AS sent_by_name,
            (SELECT COUNT(*) FROM es_response_access ra WHERE ra.response_id = pr.id AND ra.action = 'download') AS dl_count,
            (SELECT COUNT(DISTINCT ra.user_id) FROM es_response_access ra WHERE ra.response_id = pr.id AND ra.action IN ('download','view')) AS access_users
       FROM es_bid_analysis a
       JOIN es_bid_registry r ON r.id = a.registry_id
       LEFT JOIN es_pde p ON p.id = r.pde_id
       $respJoin
       LEFT JOIN users sb ON sb.id = pr.sent_by
       $whereSql
      ORDER BY COALESCE(pr.published_at, a.decided_at) DESC, a.id DESC
      LIMIT $per OFFSET $offset",
    $types, $args
);

$outLbl = ['no_objection' => 'No-objection granted', 'objection' => 'No-objection withheld',
           'compliant' => 'Compliant', 'non_compliant' => 'Non-compliant', 'pending' => '—'];

es_layout_head('Submission responses', 'responses');
?>

<div class="f-head">
  <h1 class="f-title">Submission responses</h1>
  <p class="f-subtitle">PDE response letters for reviews finalised by the DG or the Board</p>
</div>

<div class="f-stats">
  <div class="f-stat s-amber"><i class="bi bi-pencil-square"></i>
    <div class="f-stat-value"><?= number_format($cnt['unpublished']) ?></div><div class="f-stat-label">Unpublished</div></div>
  <div class="f-stat s-sky"><i class="bi bi-megaphone"></i>
    <div class="f-stat-value"><?= number_format($cnt['published']) ?></div><div class="f-stat-label">Published, not sent</div></div>
  <div class="f-stat s-green"><i class="bi bi-send-check"></i>
    <div class="f-stat-value"><?= number_format($cnt['sent']) ?></div><div class="f-stat-label">Sent to the PDE</div></div>
</div>

<div class="f-chips">
  <?php foreach ($TABS as $k => [$lbl]): ?>
    <a href="?<?= e(http_build_query(['tab' => $k] + $carry)) ?>" class="chip <?= $tab === $k ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="chip-count"><?= (int) $cnt[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form class="f-toolbar" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search serial, subject or PDE…" autocomplete="off">
  </div>
  <button class="btn btn-outline-secondary btn-sm" style="height:40px;">Search</button>
</form>

<?php if (!$rows): ?>
  <div class="f-state"><i class="bi bi-envelope-paper"></i><p>Nothing in <em><?= e(strtolower($TABS[$tab][0])) ?></em>.</p></div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($rows as $r):
        $rid = (int) $r['response_id'];
        $letterUrl = $rid ? 'response_letter.php?id=' . $rid : '';
    ?>
      <div class="f-panel rec">
        <div class="rec-head">
          <div class="rec-headmain">
            <div class="rec-title"><?= e($r['subject']) ?></div>
            <div class="rec-meta">
              <span><?= e($r['pde_name'] ?? '—') ?></span>
              <span class="sep">·</span><span class="font-monospace"><?= e($r['serial_no']) ?></span>
              <span class="sep">·</span><span><?= e($outLbl[$r['final_outcome']] ?? $r['final_outcome']) ?></span>
              <?php if ($r['decided_at']): ?><span class="sep">·</span><span>finalised <?= e(date('d M Y', strtotime($r['decided_at']))) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="rec-badges">
            <?php if (!$rid || !$r['published']): ?>
              <span class="pill t-amber">unpublished</span>
            <?php elseif (!$r['sent_at']): ?>
              <span class="pill t-sky">published</span>
            <?php else: ?>
              <span class="pill t-green">sent</span>
            <?php endif; ?>
          </div>
        </div>

        <?php
          $peek = 'bid_response_peek.php?analysis_id=' . (int) $r['analysis_id']
                . '&amp;from=responses&amp;tab=' . e($tab) . '&amp;partial=1';
        ?>
        <?php if ($rid && $r['published']): ?>
          <div class="rec-metrics">
            <span>published <b><?= e(date('d M Y', strtotime($r['published_at']))) ?></b></span>
            <?php if ($r['sent_at']): ?>
              <span class="sep">·</span><span>sent <b><?= e(date('d M Y', strtotime($r['sent_at']))) ?></b> via <?= e($r['sent_method'] ?? '—') ?><?= $r['sent_by_name'] ? ' by ' . e($r['sent_by_name']) : '' ?></span>
            <?php endif; ?>
            <span class="sep">·</span><span><i class="bi bi-download"></i> <b><?= (int) $r['dl_count'] ?></b> download<?= (int) $r['dl_count'] === 1 ? '' : 's' ?></span>
          </div>

          <?php $log = db_all("SELECT ra.action, ra.ts_create, u.full_name FROM es_response_access ra LEFT JOIN users u ON u.id = ra.user_id WHERE ra.response_id = ? ORDER BY ra.id DESC LIMIT 25", 'i', [$rid]); ?>
          <?php if ($log): ?>
            <details class="rec-hist">
              <summary><?= count($log) ?> access record<?= count($log) === 1 ? '' : 's' ?></summary>
              <div class="rec-hist-body">
                <?php foreach ($log as $lg): ?>
                  <div class="rec-hist-item">
                    <i class="bi bi-<?= $lg['action'] === 'download' ? 'download' : ($lg['action'] === 'email' ? 'envelope' : 'eye') ?> text-muted me-1"></i>
                    <strong><?= e($lg['full_name'] ?? 'someone') ?></strong> <?= e($lg['action'] === 'email' ? 'marked sent' : $lg['action'] . 'ed') ?>
                    <span class="rec-hist-reason"><?= e(date('d M Y H:i', strtotime($lg['ts_create']))) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>

          <div class="rec-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-drawer="<?= $peek ?>" data-drawer-title="Response letter &middot; <?= e($r['serial_no']) ?>">
              <i class="bi bi-envelope-paper me-1"></i>View response
            </button>
            <a href="<?= $letterUrl ?>&amp;format=pdf" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
            <?php if (!$r['sent_at'] && $canDispatch): ?>
              <form method="post" action="response_dispatch.php" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
                <input type="hidden" name="response_id" value="<?= $rid ?>">
                <input type="hidden" name="method" value="email">
                <button class="btn btn-sm btn-success"><i class="bi bi-envelope-check me-1"></i>Mark as emailed</button>
              </form>
              <form method="post" action="response_dispatch.php" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= e(es_csrf_token()) ?>">
                <input type="hidden" name="response_id" value="<?= $rid ?>">
                <input type="hidden" name="method" value="manual">
                <button class="btn btn-sm btn-outline-success">Mark as sent</button>
              </form>
            <?php endif; ?>
          </div>

        <?php else: /* unpublished */ ?>
          <div class="rec-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-drawer="<?= $peek ?>" data-drawer-title="Response letter &middot; <?= e($r['serial_no']) ?>">
              <i class="bi bi-envelope-paper me-1"></i>View response
            </button>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?= es_pager($page, $pages, fn(int $p) => '?' . http_build_query($carry + ['page' => $p]), $total, $per) ?>
<?php endif; ?>

<?php es_layout_foot(); ?>
