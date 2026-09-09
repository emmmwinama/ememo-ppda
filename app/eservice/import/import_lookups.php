<?php
/**
 * Phase 1 — lookups.
 *   _bid_ppdes            -> es_pde
 *   _bid_ppda_officers    -> es_legacy_officer_map   (legacy officer id -> ememo user)
 *   optional legacy catalogues (country / currency / category) -> es_country / es_currency / es_category
 *
 * Called by run.php / index.php as: es_import_lookups($x);
 */

function es_import_lookups(EsImport $x): void
{
    // ---- PDEs -----------------------------------------------------------
    if ($x->srcHas('_bid_ppdes')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_ppdes`") as $r) {
            $x->upsert('es_pde', [
                'name'      => trim((string) $r['pde_name']) ?: ('PDE #' . $r['id']),
                'ref_code'  => $x->nz(trim((string) ($r['ref_code'] ?? ''))),
                'email'     => $x->nz(trim((string) ($r['pde_email'] ?? ''))),
                'address'   => $x->nz(trim((string) ($r['address'] ?? ''))),
                'active'    => 1,
                'legacy_id' => (int) $r['id'],
            ]);
        }
    } else {
        $x->warn('legacy table _bid_ppdes not found — no PDEs imported');
    }

    // ---- PPDA officer -> ememo user map -------------------------------
    if ($x->srcHas('_bid_ppda_officers')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_ppda_officers`") as $r) {
            $uid = $x->userByEmail($r['officer_email'] ?? null);
            if (!$uid) {
                // fall back: match "First Last" against users.full_name
                $name = trim((string) ($r['officer_name'] ?? ''));
                if ($name !== '') {
                    $st = $x->dst->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
                    $st->bind_param('s', $name);
                    $st->execute();
                    $row = $st->get_result()->fetch_row();
                    $st->close();
                    if ($row) $uid = (int) $row[0];
                }
            }
            if (!$uid) $x->warn("officer '{$r['officer_name']}' <{$r['officer_email']}> has no matching ememo user");
            $x->upsert('es_legacy_officer_map', [
                'legacy_officer_id' => (int) $r['id'],
                'user_id'           => $uid,
                'officer_name'      => $x->nz(trim((string) ($r['officer_name'] ?? ''))),
                'officer_email'     => $x->nz(trim((string) ($r['officer_email'] ?? ''))),
            ], ['legacy_officer_id' => (int) $r['id']]);
        }
    }

    // ---- optional legacy catalogues -------------------------------
    // Countries
    foreach (['_countries', 'countries', '_country', 'country'] as $t) {
        if (!$x->srcHas($t)) continue;
        foreach ($x->srcAll("SELECT * FROM `$t`") as $r) {   // $t is from a hard-coded whitelist
            $name = $x->firstNonEmpty($r, ['name', 'country_name', 'country', 'title', 'label']);
            if (!$name) continue;
            $x->upsert('es_country', ['name' => $name, 'active' => 1, 'legacy_id' => (int) ($r['id'] ?? 0)], ['legacy_id' => (int) ($r['id'] ?? 0)]);
        }
        break;
    }
    // Currencies
    foreach (['_currency', '_currencies', 'currencies', 'currency'] as $t) {
        if (!$x->srcHas($t)) continue;
        foreach ($x->srcAll("SELECT * FROM `$t`") as $r) {   // $t is from a hard-coded whitelist
            $code = strtoupper(trim((string) ($x->firstNonEmpty($r, ['code', 'currency_code', 'iso', 'symbol']) ?? '')));
            $name = $x->firstNonEmpty($r, ['name', 'currency_name', 'currency', 'title']) ?: $code;
            if (strlen($code) !== 3) { $x->warn("currency row #{$r['id']} has no 3-letter code — skipped"); continue; }
            $x->upsert('es_currency', ['code' => $code, 'name' => $name, 'active' => 1, 'legacy_id' => (int) ($r['id'] ?? 0)], ['code' => $code]);
        }
        break;
    }
    // Category catalogues (goods / services / works)
    $catMap = [
        'good'    => ['_good_category', '_goods_category', '_category_goods', 'good_category'],
        'service' => ['_service_category', '_services_category', '_category_services', 'service_category'],
        'works'   => ['_works_category', '_work_category', '_category_works', 'works_category'],
    ];
    $typeOf = ['good' => 'goods', 'service' => 'services', 'works' => 'works'];
    foreach ($catMap as $kind => $cands) {
        foreach ($cands as $t) {
            if (!$x->srcHas($t)) continue;
            foreach ($x->srcAll("SELECT * FROM `$t`") as $r) {   // $t is from a hard-coded whitelist
                $name = $x->firstNonEmpty($r, ['name', 'category', 'category_name', 'description', 'title', 'label']);
                if (!$name) continue;
                $fee = 0.0;
                foreach (['fee', 'amount', 'price', 'category_fee'] as $fc) if (isset($r[$fc]) && is_numeric($r[$fc])) { $fee = (float) $r[$fc]; break; }
                $x->upsert('es_category', [
                    'type'        => $typeOf[$kind],
                    'name'        => $name,
                    'fee'         => $fee,
                    'active'      => 1,
                    'legacy_kind' => $kind,
                    'legacy_id'   => (int) ($r['id'] ?? 0),
                ], ['legacy_kind' => $kind, 'legacy_id' => (int) ($r['id'] ?? 0)]);
            }
            break;
        }
    }

    // ---- report _parameters sections (help an admin reconcile codes) --
    if ($x->srcHas('_parameters')) {
        $rows = $x->srcAll("SELECT section_description, COUNT(*) c FROM `_parameters` GROUP BY section_description ORDER BY 1");
        $x->warn('legacy _parameters sections present: ' . implode(', ', array_map(fn($r) => $r['section_description'] . "({$r['c']})", $rows)));
    }
}
