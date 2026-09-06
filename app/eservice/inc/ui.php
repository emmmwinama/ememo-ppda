<?php
/** Small presentational helpers — status / priority / source badges. */

/** Submission workflow status → a dot badge. */
function es_status_badge(string $status): string
{
    static $map = [
        'pending_registry'   => ['Registry check',        '#b45309'],
        'returned_to_pde'    => ['Returned to PDE',       '#be123c'],
        'pending_allocation' => ['Awaiting allocation',   '#0369a1'],
        'assigned'           => ['Allocated',             '#0369a1'],
        'in_analysis'        => ['In analysis',           '#b45309'],
        'completed'          => ['Completed',             '#15803d'],
        'closed'             => ['Closed',                '#64748b'],
        'withdrawn'          => ['Withdrawn',             '#be123c'],
        // analysis stages, reused
        'draft'              => ['Draft',                 '#64748b'],
        'supervisor_review'  => ['Supervisor review',     '#b45309'],
        'director_review'    => ['Director review',        '#b45309'],
        'dg_review'          => ['DG review',             '#0369a1'],
        'board_review'       => ['Board review',          '#0369a1'],
        'approved'           => ['Approved',              '#15803d'],
        'rejected'           => ['Rejected',              '#be123c'],
        'returned'           => ['Returned',              '#be123c'],
    ];
    [$label, $color] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#64748b'];
    return '<span class="es-badge es-badge-status"><span class="es-dot" style="background:' . $color . '"></span>'
         . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}

/** Priority → icon badge. */
function es_priority_badge(string $p): string
{
    static $map = [
        'normal' => ['Normal', 'bi-flag',      '#64748b', '#eef1f4'],
        'high'   => ['High',   'bi-flag-fill',  '#b45309', '#fdefda'],
        'urgent' => ['Urgent', 'bi-exclamation-triangle-fill', '#be123c', '#fdecee'],
    ];
    [$label, $icon, $fg, $bg] = $map[$p] ?? $map['normal'];
    return '<span class="es-badge" style="color:' . $fg . ';background:' . $bg . '"><i class="bi ' . $icon . '"></i>'
         . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}

/** Submission origin → icon badge. */
function es_source_badge(string $origin): string
{
    if ($origin === 'pde') {
        return '<span class="es-badge" style="color:#0369a1;background:#e6f4fb"><i class="bi bi-cloud-arrow-up-fill"></i>PDE upload</span>';
    }
    return '<span class="es-badge" style="color:#475569;background:#eef1f4"><i class="bi bi-building-fill"></i>Registry</span>';
}

/** final_outcome → recommendation pill. */
function es_outcome_pill(?string $o): string
{
    static $m = [
        'compliant'     => ['Compliant',     't-green'],
        'no_objection'  => ['No objection',  't-green'],
        'non_compliant' => ['Non-compliant', 't-rose'],
        'objection'     => ['Objection',     't-rose'],
    ];
    if (!$o || !isset($m[$o])) return '';
    return '<span class="pill ' . $m[$o][1] . '">' . htmlspecialchars($m[$o][0], ENT_QUOTES) . '</span>';
}

/**
 * One analysis worklist card (shared by bid_analysis / bid_review / bid_board).
 * $r needs: subject, pde_name, serial_no, method_name, stage, final_outcome,
 *           officer_name, owner_name, submitted_ts, allocated_at, last_ts,
 *           notes, atts, analysis_id, registry_id
 * $o: open_url, open_label, extra_badges (html), extra_actions (html), note (html), sla_days (int)
 */
function es_analysis_card(array $r, array $o = []): string
{
    $e   = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $sla = (int) ($o['sla_days'] ?? 14);
    $subDays = !empty($r['submitted_ts']) ? (int) floor((time() - strtotime($r['submitted_ts'])) / 86400) : 0;
    $hot = $subDays > $sla ? ' m-hot' : '';
    $isDone = in_array($r['stage'] ?? '', ['approved', 'rejected'], true);
    $openUrl = $o['open_url'] ?? ('bid_analysis_view.php?id=' . (int) ($r['analysis_id'] ?? 0));
    $openLbl = $o['open_label'] ?? 'Open analysis';

    $h  = '<div class="f-panel rec">';
    $h .= '<div class="rec-head"><div class="rec-headmain">';
    $h .= '<div class="rec-title">' . $e($r['subject'] ?? '—') . '</div>';
    $h .= '<div class="rec-meta"><span>' . $e($r['pde_name'] ?? '—') . '</span>';
    $h .= '<span class="sep">·</span><span class="font-monospace">' . $e($r['serial_no'] ?? '') . '</span>';
    if (!empty($r['method_name'])) $h .= '<span class="sep">·</span><span>' . $e($r['method_name']) . '</span>';
    $h .= '<span class="sep">·</span><span>Reviewer: ' . $e($r['officer_name'] ?? '—') . '</span>';
    if (!$isDone && !empty($r['owner_name']) && ($r['owner_name'] !== ($r['officer_name'] ?? null)))
        $h .= '<span class="sep">·</span><span>with <strong>' . $e($r['owner_name']) . '</strong></span>';
    $h .= '</div></div><div class="rec-badges">';
    $h .= es_status_badge($r['stage'] ?? 'draft');
    $h .= es_outcome_pill($r['final_outcome'] ?? null);
    if (!empty($o['extra_badges'])) $h .= $o['extra_badges'];
    $h .= '</div></div>';

    if (!empty($o['note'])) $h .= '<div class="rec-note">' . $o['note'] . '</div>';

    if (!empty($o['turn'])) $h .= es_turn_strip($o['turn'], $r['stage'] ?? null);

    $h .= '<div class="rec-metrics">';
    $h .= '<span class="' . trim($hot) . '">since submission <b>' . $e(es_span($r['submitted_ts'] ?? null)) . '</b></span>';
    $h .= '<span class="sep">·</span><span>forwarded <b>' . $e(es_span($r['allocated_at'] ?? null)) . '</b></span>';
    $h .= '<span class="sep">·</span><span>last action <b>' . $e(es_span($r['last_ts'] ?? null)) . '</b></span>';
    $h .= '<span class="sep">·</span><span><i class="bi bi-chat-left-text"></i> <b>' . (int) ($r['notes'] ?? 0) . '</b></span>';
    $h .= '<span><i class="bi bi-paperclip"></i> <b>' . (int) ($r['atts'] ?? 0) . '</b></span>';
    $h .= '</div>';

    $h .= '<div class="rec-actions">';
    $h .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-drawer="bid_submission_peek.php?id='
        . (int) ($r['registry_id'] ?? 0) . '&amp;partial=1" data-drawer-title="Submission · ' . $e($r['serial_no'] ?? '') . '">'
        . '<i class="bi bi-file-earmark-text me-1"></i>View submission</button>';
    if (!empty($o['extra_actions'])) $h .= $o['extra_actions'];
    $h .= '<a href="' . $e($openUrl) . '" class="btn btn-sm btn-success">' . $e($openLbl) . '</a>';
    $h .= '</div></div>';
    return $h;
}

/* ── Turnaround (how long each stage took) ─────────────────────────────────
 * Shared across the tracking board and every review worklist. A "turn" array
 * per role is { secs, open(bool), n(rounds), avg(secs|null) }.
 */

/** Batch-compute the per-role turnaround for a set of analysis ids. */
function es_turnaround_map(array $analysisIds): array
{
    global $conn;
    $ids = array_values(array_unique(array_filter(array_map('intval', $analysisIds))));
    if (!$ids) return [];
    $in = implode(',', $ids);

    $meta = [];
    if ($res = $conn->query("SELECT id, stage, UNIX_TIMESTAMP(ts_create) ts FROM es_bid_analysis WHERE id IN ($in)")) {
        while ($row = $res->fetch_assoc()) $meta[(int) $row['id']] = $row;
    }
    $ev = [];
    if ($res = $conn->query("SELECT analysis_id, from_stage, to_stage, action, UNIX_TIMESTAMP(ts_create) ts
                               FROM es_bid_routing WHERE analysis_id IN ($in) ORDER BY analysis_id, id")) {
        while ($row = $res->fetch_assoc()) $ev[(int) $row['analysis_id']][] = $row;
    }

    $decide     = ['submit', 'endorse', 'return', 'approve', 'reject'];
    $roleStages = ['officer' => ['draft', 'returned'], 'supervisor' => ['supervisor_review'],
                   'director' => ['director_review'], 'dg' => ['dg_review'], 'board' => ['board_review']];
    $curRole    = ['draft' => 'officer', 'returned' => 'officer', 'supervisor_review' => 'supervisor',
                   'director_review' => 'director', 'dg_review' => 'dg', 'board_review' => 'board'];

    $out = [];
    foreach ($ids as $aid) {
        $m = $meta[$aid] ?? null;
        $evs = $ev[$aid] ?? [];
        foreach ($roleStages as $role => $ss) {
            $inTs = ($role === 'officer' && $m) ? (int) $m['ts'] : null;
            $cycles = [];
            foreach ($evs as $x) {
                if ($inTs !== null && in_array($x['from_stage'], $ss, true) && in_array($x['action'], $decide, true)) {
                    $cycles[] = max(0, (int) $x['ts'] - $inTs);
                    $inTs = null;
                }
                if (in_array($x['to_stage'], $ss, true) && $x['action'] !== 'comment') $inTs = (int) $x['ts'];
            }
            $avg = $cycles ? (int) round(array_sum($cycles) / count($cycles)) : null;
            if ($inTs !== null) {
                $out[$aid][$role] = ['secs' => time() - $inTs, 'open' => true, 'n' => count($cycles), 'avg' => $avg];
            } elseif ($avg !== null) {
                $out[$aid][$role] = ['secs' => $avg, 'open' => false, 'n' => count($cycles)];
            }
        }
        $cr = $m ? ($curRole[$m['stage']] ?? null) : null;
        if ($cr && empty($out[$aid][$cr])) {
            $last = 0;
            foreach ($evs as $x) $last = max($last, (int) $x['ts']);
            if (!$last && $m) $last = (int) $m['ts'];
            if ($last) $out[$aid][$cr] = ['secs' => time() - $last, 'open' => true, 'n' => 0, 'avg' => null];
        }
    }
    return $out;
}

/** One turnaround circle. Green <= $goodDays, amber <= $okDays, red beyond; dashed = still running. */
function es_turn_chip(?array $t, int $goodDays, int $okDays): string
{
    if (!$t) return '';
    $s = (int) $t['secs'];
    $days = $s / 86400;
    $lbl = $s >= 86400 ? round($days) . 'd' : ($s >= 3600 ? round($s / 3600) . 'h' : max(1, round($s / 60)) . 'm');
    $tier = $days <= $goodDays ? 't-good' : ($days <= $okDays ? 't-ok' : 't-poor');
    $open = !empty($t['open']);
    if ($open) {
        $why = 'in this stage now';
        if (!empty($t['avg'])) $why .= ' — earlier rounds averaged ' . max(1, round($t['avg'] / 86400)) . 'd';
    } else {
        $why = ($t['n'] ?? 1) > 1 ? 'average of ' . (int) $t['n'] . ' rounds' : 'receipt → determination';
    }
    return '<span class="trk-dur ' . $tier . ($open ? ' trk-open' : '') . '" title="'
         . htmlspecialchars($why, ENT_QUOTES) . '">' . $lbl . '</span>';
}

/** Horizontal strip of labelled turnaround circles for a worklist card. */
function es_turn_strip(array $turn, ?string $stage): string
{
    if (!$turn) return '';
    $rank = ['draft' => 0, 'returned' => 0, 'supervisor_review' => 1, 'director_review' => 2,
             'dg_review' => 3, 'board_review' => 4, 'approved' => 5, 'rejected' => 5][$stage ?? ''] ?? 0;
    $items = [
        ['Rev', $turn['officer']    ?? null, 7, 14, true],
        ['Sup', $turn['supervisor'] ?? null, 3, 7,  $rank >= 1],
        ['Dir', $turn['director']   ?? null, 3, 7,  $rank >= 2],
        ['DG',  $turn['dg']         ?? null, 3, 7,  $rank >= 3],
        ['Bd',  $turn['board']      ?? null, 5, 12, $rank >= 4],
    ];
    $h = '';
    foreach ($items as [$tag, $t, $g, $o, $show]) {
        if (!$show) continue;
        $chip = es_turn_chip($t, $g, $o);
        if ($chip === '') continue;
        $h .= '<span class="trk-item"><span class="trk-lbl">' . $tag . '</span>' . $chip . '</span>';
    }
    return $h === '' ? '' : '<div class="rec-turn">' . $h . '</div>';
}

/** The shared legend explaining the turnaround circles. */
function es_turnaround_legend(): string
{
    $ring = fn($c) => '<span class="trk-dur ' . $c . '"></span>';
    return '<span class="trk-legend">The circle is that stage\'s turnaround — receipt&nbsp;&rarr;&nbsp;determination; '
         . 'ring colour shows how good the time was (' . $ring('t-good') . ' fast, ' . $ring('t-ok') . ' ok, '
         . $ring('t-poor') . ' slow), and a dashed ring means still deciding.</span>';
}

/**
 * A button that opens the read-only submission (registry record) in the
 * right-side slide-over — the system-wide way to peek at a registry record.
 */
function es_submission_button(int $registryId, string $serial = '', string $label = 'Submission view', string $cls = 'btn btn-outline-secondary btn-sm'): string
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    return '<button type="button" class="' . $e($cls) . '"'
         . ' data-drawer="bid_submission_peek.php?id=' . $registryId . '&amp;partial=1"'
         . ' data-drawer-title="Submission' . ($serial !== '' ? ' &middot; ' . $e($serial) : '') . '">'
         . '<i class="bi bi-file-earmark-text me-1"></i>' . $e($label) . '</button>';
}
