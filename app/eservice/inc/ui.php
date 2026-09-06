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
