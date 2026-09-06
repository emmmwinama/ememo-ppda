<?php
/**
 * Phase 2 — suppliers (lookup / host data).
 *   _supplier_details (+ _supplier_business_operations)  -> es_supplier
 *   _supplier_shareholders                               -> es_supplier_shareholder
 *   _supplier_categories                                 -> es_supplier_category
 *   _supplier_bank_details                               -> es_supplier_bank
 *   _supplier_certificates                               -> es_supplier_certificate
 *   _supplier_attachments                                -> es_supplier_attachment  (one row per file column)
 *
 * Called as: es_import_suppliers($x);
 */

function es_import_suppliers(EsImport $x): void
{
    if (!$x->srcHas('_supplier_details')) {
        $x->warn('legacy table _supplier_details not found — nothing to import');
        return;
    }

    // business operations keyed by supplier (ss_id)
    $ops = [];
    if ($x->srcHas('_supplier_business_operations')) {
        foreach ($x->srcAll("SELECT * FROM `_supplier_business_operations`") as $o) {
            $ops[(int) $o['ss_id']] = $o;   // last wins
        }
    }

    $statusSeen = [];
    foreach ($x->srcAll("SELECT * FROM `_supplier_details`") as $r) {
        $legacyId = (int) $r['id'];
        $op = $ops[$legacyId] ?? [];

        $expire = $x->d($r['expire_date'] ?? null);
        $sInt   = ($r['supplier_status'] === null || $r['supplier_status'] === '') ? null : (int) $r['supplier_status'];
        $statusSeen[$sInt] = ($statusSeen[$sInt] ?? 0) + 1;
        $status = 'active';
        if ($expire && strtotime($expire) < time()) $status = 'expired';

        $newId = $x->upsert('es_supplier', [
            'supplier_code'     => ($r['supplier_code'] === null || $r['supplier_code'] === '') ? null : (int) $r['supplier_code'],
            'name'              => trim((string) $r['supplier_name']) ?: ('Supplier #' . $legacyId),
            'trading_name'      => $x->nz(trim((string) ($op['trading_name'] ?? ''))),
            'email'             => $x->nz(trim((string) ($r['email_official'] ?? ''))),
            'website'           => $x->nz(trim((string) ($r['website'] ?? ''))),
            'business_phone'    => $x->nz(trim((string) ($r['business_telephone'] ?? ''))),
            'mobile_phone'      => $x->nz(trim((string) ($r['mobile_number'] ?? ''))),
            'postal_address'    => $x->nz(trim((string) ($r['postal_address'] ?? ''))),
            'physical_address'  => $x->nz(trim((string) ($r['business_premise'] ?? ''))),
            'city'              => $x->nz(trim((string) ($r['city'] ?? ''))),
            'country_id'        => $x->newId('es_country', $x->nz($r['country'] ?? null)),
            'tin'              => $x->nz(trim((string) ($op['company_tin'] ?? ''))),
            'vat_number'        => $x->nz(trim((string) ($op['vat_number'] ?? ''))),
            'ncic_number'       => $x->nz(trim((string) ($op['ncic_number'] ?? ''))),
            'company_number'    => $x->nz(trim((string) ($op['company_number'] ?? ''))),
            'date_registered'   => $x->d($op['date_of_registration'] ?? null),
            'years_operations'  => ($op['years_operations'] ?? null) !== null && $op['years_operations'] !== '' ? (int) $op['years_operations'] : null,
            'num_employees'     => ($op['number_employees_staff'] ?? null) !== null && $op['number_employees_staff'] !== '' ? (int) $op['number_employees_staff'] : null,
            'status'            => $status,
            'expire_date'       => $expire,
            'source'            => 'legacy',
            'legacy_status_int' => $sInt,
            'legacy_id'         => $legacyId,
            'created_by'        => $x->userByUsername($r['user'] ?? null),
            'ts_create'         => $x->dt($r['ts_create'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);
        if (!$newId && !$x->dry) continue;
    }
    if ($statusSeen) {
        $x->warn('legacy _supplier_details.supplier_status values (int => count): ' .
            implode(', ', array_map(fn($k) => (($k === '' ? 'null' : $k) . '=>' . $statusSeen[$k]), array_keys($statusSeen))));
    }

    // ---- shareholders -------------------------------------------------
    if ($x->srcHas('_supplier_shareholders')) {
        foreach ($x->srcAll("SELECT * FROM `_supplier_shareholders`") as $r) {
            $sup = $x->newId('es_supplier', (int) $r['ss_id']);
            if (!$sup) { $x->warn("shareholder #{$r['id']}: supplier {$r['ss_id']} not imported"); continue; }
            $g = (string) ($r['gender'] ?? '');
            $gender = $g === '1' ? 'male' : ($g === '2' ? 'female' : null);
            $x->upsert('es_supplier_shareholder', [
                'supplier_id'    => (int) $sup,
                'first_name'     => trim((string) $r['first_name']) ?: '—',
                'last_name'      => trim((string) $r['last_name']) ?: '—',
                'gender'         => $gender,
                'national_id'    => $x->nz(trim((string) ($r['national_id'] ?? ''))),
                'tin'            => $x->nz(trim((string) ($r['tin'] ?? ''))),
                'contact_number' => $x->nz(trim((string) ($r['contact_number'] ?? ''))),
                'email'          => $x->nz(trim((string) ($r['email'] ?? ''))),
                'percentage'     => ($r['percentage'] ?? null) !== null && $r['percentage'] !== '' ? (float) $r['percentage'] : null,
                'nationality_id' => $x->newId('es_country', $x->nz($r['origin'] ?? null)),
                'legacy_id'      => (int) $r['id'],
            ]);
        }
    }

    // ---- categories --------------------------------------------------
    if ($x->srcHas('_supplier_categories')) {
        foreach ($x->srcAll("SELECT * FROM `_supplier_categories`") as $r) {
            $sup = $x->newId('es_supplier', (int) $r['s_id']);
            if (!$sup) continue;
            foreach (['good' => 'good_category', 'service' => 'service_category', 'works' => 'works_category'] as $kind => $col) {
                $legacyCat = $x->nz($r[$col] ?? null);
                if ($legacyCat === null || (int) $legacyCat === 0) continue;
                $catId = $x->newId('es_category', (int) $legacyCat, 'legacy_id');
                if (!$catId) { $x->warn("supplier {$r['s_id']}: $kind category $legacyCat has no catalogue row — skipped"); continue; }
                // composite PK, no legacy_id column -> key on the pair
                $x->upsert('es_supplier_category',
                    ['supplier_id' => (int) $sup, 'category_id' => (int) $catId],
                    ['supplier_id' => (int) $sup, 'category_id' => (int) $catId]);
            }
        }
    }

    // ---- bank details ----------------------------------------------
    if ($x->srcHas('_supplier_bank_details')) {
        foreach ($x->srcAll("SELECT * FROM `_supplier_bank_details`") as $r) {
            $sup = $x->newId('es_supplier', (int) $r['ssd_id']);
            if (!$sup) continue;
            // es_currency PK is `code`; legacy `currency` is an int -> resolve code via legacy_id
            $curCode = null;
            if (($r['currency'] ?? '') !== '' && (int) $r['currency'] !== 0) {
                $lc = (int) $r['currency'];
                $q = $x->dst->query("SELECT code FROM es_currency WHERE legacy_id = $lc LIMIT 1");
                $curCode = ($q && ($row = $q->fetch_row())) ? $row[0] : null;
            }

            $x->upsert('es_supplier_bank', [
                'supplier_id'    => (int) $sup,
                'bank_name'      => trim((string) $r['bank_name']) ?: '—',
                'branch_name'    => $x->nz(trim((string) ($r['branch_name'] ?? ''))),
                'account_name'   => $x->nz(trim((string) ($r['account_name'] ?? ''))),
                'account_number' => $x->nz(trim((string) ($r['account_number'] ?? ''))),
                'account_type'   => $x->nz(trim((string) ($r['account_type'] ?? ''))),
                'currency_code'  => $curCode,
                'swift_code'     => $x->nz(trim((string) ($r['swift_code'] ?? ''))),
                'country_id'     => $x->newId('es_country', $x->nz($r['country'] ?? null)),
                'legacy_id'      => (int) $r['id'],
            ]);
        }
    }

    // ---- certificates --------------------------------------------
    if ($x->srcHas('_supplier_certificates')) {
        foreach ($x->srcAll("SELECT * FROM `_supplier_certificates`") as $r) {
            $sup = $x->newId('es_supplier', (int) $r['supplier_id']);
            if (!$sup) continue;
            $x->upsert('es_supplier_certificate', [
                'supplier_id' => (int) $sup,
                'issue_date'  => $x->d($r['date'] ?? null),
                'legacy_id'   => (int) $r['id'],
            ]);
        }
    }

    // ---- attachments -------------------------------------------------
    // Legacy columns hold a JSON manifest per document type, e.g.
    //   [{"name":"files\/x_ab12.pdf","usrName":"x.pdf","size":123,"type":"application\/pdf"}]
    // -> one es_supplier_attachment row per file entry.
    if ($x->srcHas('_supplier_attachments')) {
        $fileCols = [
            'business_register' => 'business_register',
            'mra_certificate'   => 'mra_certificate',
            'tax_clearance'     => 'tax_clearance',
            'bank_deposit_slip' => 'bank_proof',
            'other'             => 'other',
        ];
        foreach ($x->srcAll("SELECT * FROM `_supplier_attachments`") as $r) {
            $sup = $x->newId('es_supplier', (int) $r['s_a_id']);
            if (!$sup) continue;
            foreach ($fileCols as $col => $kind) {
                $raw = trim((string) ($r[$col] ?? ''));
                if ($raw === '' || $raw === '0' || $raw === '[]' || $raw === 'null') continue;

                $files = [];
                $j = json_decode($raw, true);
                if (is_array($j)) {
                    foreach ($j as $entry) {
                        if (is_array($entry) && !empty($entry['name'])) {
                            $files[] = ['path' => (string) $entry['name'], 'name' => (string) ($entry['usrName'] ?? basename((string) $entry['name']))];
                        }
                    }
                } else {
                    // plain path fallback
                    $files[] = ['path' => $raw, 'name' => basename($raw)];
                }

                foreach ($files as $i => $f) {
                    $lk = $r['id'] . ':' . $kind . ':' . $i;
                    $x->upsert('es_supplier_attachment', [
                        'supplier_id'   => (int) $sup,
                        'kind'          => $kind,
                        'file_path'     => mb_substr($f['path'], 0, 255),
                        'original_name' => mb_substr($f['name'], 0, 255),
                        'legacy_id'     => $lk,
                    ], ['legacy_id' => $lk]);
                }
            }
        }
    }
}
