<?php
/**
 * Phase 3 — bid analysis.
 *   _bid_registry              -> es_bid_registry
 *   _bid_analysis              -> es_bid_analysis      (stage derived, see notes)
 *   _bids_lots                 -> es_bid_lot
 *   _bid_assessment            -> es_bid_bidder
 *   _bid_financial_evaluation  -> es_bid_financial_eval
 *   _bid_techncial_list        -> es_bid_technical_eval
 *   _bid_technical_criteria / _bid_assessment_criteria / _bid_opening_observations /
 *   _bid_evaluation_observations / _bid_recommedation_ / _bid_post_qualification -> es_bid_list_item
 *   _bid_routing               -> es_bid_routing
 *   _bid_analysis_messaging    -> es_bid_message
 *   _bid_pde_response          -> es_pde_response
 *
 * Legacy status integers have no lookup table in the dump, so es_bid_analysis.stage
 * is derived structurally and the raw codes are kept in legacy_status_note.
 *
 * Called as: es_import_bids($x);
 */

function es_import_bids(EsImport $x): void
{
    // ---- registry ---------------------------------------------------
    if (!$x->srcHas('_bid_registry')) {
        $x->warn('legacy table _bid_registry not found — nothing to import');
        return;
    }

    $impMap = ['normal' => 'normal', 'high' => 'high', 'urgent' => 'urgent', 'critical' => 'urgent', 'low' => 'normal'];
    foreach ($x->srcAll("SELECT * FROM `_bid_registry`") as $r) {
        $legacyId = (int) $r['id'];
        $details  = trim((string) ($r['submission_details'] ?? ''));
        $subject  = $x->firstNonEmpty($r, ['tender_number'])
                    ?: (mb_substr(preg_replace('/\s+/', ' ', strip_tags($details)), 0, 180)
                        ?: ('Submission ' . ($r['serial_no'] ?: $legacyId)));

        $serial = trim((string) ($r['serial_no'] ?? ''));
        if ($serial === '') $serial = 'LEG/' . $legacyId;
        // guarantee uniqueness against any other row
        $chk = $x->dst->query("SELECT id FROM es_bid_registry WHERE serial_no = '" . $x->dst->real_escape_string($serial) . "' AND (legacy_id <> $legacyId OR legacy_id IS NULL) LIMIT 1");
        if ($chk && $chk->num_rows) $serial .= '/L' . $legacyId;

        $x->upsert('es_bid_registry', [
            'serial_no'           => $serial,
            'pde_id'              => $x->newId('es_pde', $x->nz($r['pde'] ?? null)),
            'tender_number'       => $x->nz(trim((string) ($r['tender_number'] ?? ''))),
            'subject'             => $subject !== '' ? $subject : ('Submission ' . $legacyId),
            'submission_details'  => $x->nz($details),
            'channel'             => 'physical',
            'importance'          => $impMap[strtolower(trim((string) ($r['level_importance'] ?? 'normal')))] ?? 'normal',
            'submission_signed'   => $x->yn($r['submission_signed'] ?? 0),
            'date_of_signing'     => $x->d($r['date_of_signing'] ?? null),
            'ref_code_pde'        => $x->nz(trim((string) ($r['ref_code_pde'] ?? ''))),
            'in_procurement_plan' => $x->yn($r['is_part_of_procure_plan'] ?? 0),
            'received_by'         => $x->userByUsername($r['user'] ?? null),
            // legacy rows are historical; the analysis step below re-opens the
            // ones that still have an active review. ('received' is not a valid
            // es_bid_registry.status enum value.)
            'status'              => 'completed',
            'legacy_id'           => $legacyId,
            'ts_create'           => $x->dt($r['ts_create'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);
    }

    // ---- analyses -------------------------------------------------
    if (!$x->srcHas('_bid_analysis')) { $x->warn('_bid_analysis not found — registry imported, analyses skipped'); return; }

    foreach ($x->srcAll("SELECT * FROM `_bid_analysis`") as $r) {
        $regNew = $x->newId('es_bid_registry', (int) $r['r_id']);
        if (!$regNew) { $x->warn("analysis #{$r['id']}: registry {$r['r_id']} not imported — skipped"); $x->counts['skipped']++; continue; }

        $officerUid = null;
        if (($r['officer'] ?? '') !== '') {
            $q = $x->dst->query("SELECT user_id FROM es_legacy_officer_map WHERE legacy_officer_id = " . (int) $r['officer'] . " LIMIT 1");
            $officerUid = ($q && ($row = $q->fetch_row()) && $row[0] !== null) ? (int) $row[0] : null;
        }
        $officerUid = $officerUid ?? $x->userByUsername($r['user'] ?? ($r['a_user'] ?? null));

        // ---- derive stage ----
        $vs = (int) ($r['validation_status'] ?? 25);
        $dg = (int) ($r['dg_approval'] ?? 28);
        $bs = (int) ($r['board_status'] ?? 53);
        $archive = (int) ($r['archive'] ?? 0);
        $sup = (int) ($r['supervisor'] ?? 0);

        if ($archive === 1)          $stage = 'approved';
        elseif ($dg !== 28)          $stage = 'dg_review';
        elseif ($bs !== 53)          $stage = 'board_review';
        elseif ($vs !== 25)          $stage = 'director_review';
        elseif ($sup > 0)            $stage = 'supervisor_review';
        else                        $stage = 'draft';

        $outcome = $stage === 'approved' ? 'no_objection' : 'pending';
        $note = "legacy: validation_status=$vs, dg_approval=$dg, board_status=$bs, archive=$archive, bid_final_status=" . (int) ($r['bid_final_status'] ?? 0);
        $owner = in_array($stage, ['approved', 'rejected'], true) ? null : $officerUid;

        $newAn = $x->upsert('es_bid_analysis', [
            'registry_id'                => (int) $regNew,
            'officer_id'                 => $officerUid,
            'approved_proc_plan'         => $x->yn($r['have_approved_procurement_plan'] ?? 0),
            'approved_workplan'          => $x->yn($r['procurement_approved_workplan'] ?? 0),
            'preferences_applied'        => $x->yn($r['preferences_applied'] ?? 0),
            'publication_done'           => $x->yn($r['publication_bid'] ?? 0),
            'date_of_publication'        => $x->d($r['date_of_publication'] ?? null),
            'publication_source'         => $x->nz(trim((string) ($r['publication_source'] ?? ''))),
            'bid_opening_minutes_signed' => $x->yn($r['bid_opening_minutes_signed'] ?? 0),
            'bid_opening_minutes_date'   => $x->d($r['bid_opening_minutes_date'] ?? null),
            'evaluation_report_signed'   => $x->yn($r['evaluation_report_signed'] ?? 0),
            'evaluation_report_date'     => $x->d($r['evaluation_report_date'] ?? null),
            'bid_validity_days'          => ((int) ($r['bid_validity'] ?? 0)) ?: null,
            'ipdc_minutes_signed'        => $x->yn($r['ipdc_minutes_signed'] ?? 0),
            'ipdc_minutes_date'          => $x->d($r['ipdc_minutes_date'] ?? null),
            'comment_on_ipdc'            => $x->nz(trim((string) ($r['comment_on_ipdc'] ?? ''))),
            'all_bids_enclosed'          => $x->yn($r['all_bids_enclosed'] ?? 0),
            'original_bid_enclosed'      => $x->yn($r['original_bid_enclosed'] ?? 0),
            'bids_still_valid'           => $x->yn($r['bids_still_valid'] ?? 0),
            'stage'                      => $stage,
            'current_owner_id'           => $owner,
            'officer_feedback'           => $x->nz(trim((string) ($r['feedback'] ?? ''))),
            'dg_feedback'                => $x->nz(trim((string) ($r['dg_feedback'] ?? ''))),
            'final_outcome'              => $outcome,
            'legacy_id'                  => (int) $r['id'],
            'legacy_doc_id'              => ($r['doc_id'] ?? null) !== null && $r['doc_id'] !== '' ? (int) $r['doc_id'] : null,
            'legacy_status_note'         => $note,
            'submitted_at'               => $stage !== 'draft' ? ($x->dt($r['ts_create'] ?? null)) : null,
            'decided_at'                 => $stage === 'approved' ? ($x->dt($r['ts_update'] ?? null)) : null,
            'ts_create'                  => $x->dt($r['ts_create'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);

        // bump the parent registry status
        if (!$x->dry && $regNew) {
            $rs = in_array($stage, ['approved', 'rejected'], true) ? 'completed' : 'in_analysis';
            $x->dst->query("UPDATE es_bid_registry SET status = '$rs', assigned_officer_id = " .
                ($officerUid ? (int) $officerUid : 'assigned_officer_id') . " WHERE id = " . (int) $regNew);
        }
    }
    $x->forgetCache();  // analysis ids are now queryable by legacy_doc_id

    // resolve an analysis new-id from a legacy doc_id (children join on doc_id, fall back to analysis id)
    $anByDoc = function ($docId) use ($x): ?int {
        if ($docId === null || $docId === '') return null;
        $d = (int) $docId;
        $q = $x->dst->query("SELECT id FROM es_bid_analysis WHERE legacy_doc_id = $d OR legacy_id = $d ORDER BY (legacy_doc_id = $d) DESC LIMIT 1");
        return ($q && ($row = $q->fetch_row())) ? (int) $row[0] : null;
    };

    // ---- lots ----------------------------------------------------
    if ($x->srcHas('_bids_lots')) {
        foreach ($x->srcAll("SELECT * FROM `_bids_lots`") as $r) {
            $an = $x->newId('es_bid_analysis', (int) $r['bid_analysis_id']) ?? $anByDoc($r['bid_analysis_id']);
            if (!$an) continue;
            $x->upsert('es_bid_lot', [
                'analysis_id' => (int) $an,
                'lot_number'  => (int) ($r['bid_number'] ?? 0),
                'name'        => trim((string) $r['name_of_lot']) ?: ('Lot ' . (int) ($r['bid_number'] ?? 0)),
                'legacy_id'   => (int) $r['id'],
            ]);
        }
    }
    $lotIdFor = function (int $analysisId, $lotNumber) use ($x): ?int {
        if ($lotNumber === null || $lotNumber === '' || (int) $lotNumber === 0) return null;
        $q = $x->dst->query("SELECT id FROM es_bid_lot WHERE analysis_id = $analysisId AND lot_number = " . (int) $lotNumber . " LIMIT 1");
        return ($q && ($row = $q->fetch_row())) ? (int) $row[0] : null;
    };
    $cur = fn($v) => (strlen(trim((string) $v)) === 3) ? strtoupper(trim((string) $v)) : null;

    // ---- bidders (assessment) -------------------------------------
    if ($x->srcHas('_bid_assessment')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_assessment`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->warn("assessment #{$r['id']}: analysis doc_id {$r['doc_id']} not found"); $x->counts['skipped']++; continue; }
            $bstat = 'responsive';
            if (trim((string) ($r['reason_rejection'] ?? '')) !== '') $bstat = 'rejected';
            $x->upsert('es_bid_bidder', [
                'analysis_id'      => (int) $an,
                'lot_id'           => $lotIdFor((int) $an, $r['lot_number'] ?? null),
                'bidder_number'    => (int) ($r['bidder_number'] ?? 0),
                'name'             => trim((string) $r['name_bidder']) ?: ('Bidder ' . (int) ($r['bidder_number'] ?? 0)),
                'currency_code'    => $cur($r['currency'] ?? ''),
                'read_out_price'   => ($r['read_out_price'] ?? null) !== null && $r['read_out_price'] !== '' ? (float) $r['read_out_price'] : null,
                'bid_status'       => $bstat,
                'reason_rejection' => $x->nz(trim((string) ($r['reason_rejection'] ?? ''))),
                'remarks'          => $x->nz(trim((string) ($r['remarks'] ?? ''))),
                'sort_order'       => ($r['order'] ?? null) !== null && $r['order'] !== '' ? (int) $r['order'] : null,
                'legacy_id'        => (int) $r['id'],
            ]);
        }
    }
    // resolve new bidder id from a legacy assessment id, or by (analysis, bidder_number)
    $bidderIdFor = function (int $analysisId, $legacyBidderRef) use ($x): ?int {
        if ($legacyBidderRef === null || $legacyBidderRef === '') return null;
        $ref = (int) $legacyBidderRef;
        $q = $x->dst->query("SELECT id FROM es_bid_bidder WHERE legacy_id = $ref LIMIT 1");
        if ($q && ($row = $q->fetch_row())) return (int) $row[0];
        $q = $x->dst->query("SELECT id FROM es_bid_bidder WHERE analysis_id = $analysisId AND bidder_number = $ref LIMIT 1");
        return ($q && ($row = $q->fetch_row())) ? (int) $row[0] : null;
    };

    // ---- financial evaluation ---------------------------------
    if ($x->srcHas('_bid_financial_evaluation')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_financial_evaluation`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $msme = trim((string) ($r['msme'] ?? ''));
            $x->upsert('es_bid_financial_eval', [
                'analysis_id'             => (int) $an,
                'bidder_id'               => $bidderIdFor((int) $an, $r['bidder'] ?? null),
                'lot_id'                  => $lotIdFor((int) $an, $r['lot_number'] ?? null),
                'currency_code'           => $cur($r['currency'] ?? ''),
                'bid_price'               => ($r['bid_price'] ?? null) !== '' ? (float) $r['bid_price'] : null,
                'computation_errors'      => (float) ($r['computation_errors'] ?? 0),
                'corrected_bid_price'     => is_numeric($r['corrected_bid_price'] ?? '') ? (float) $r['corrected_bid_price'] : null,
                'exchange_rate'           => (float) ($r['rate'] ?? 1) ?: 1.0,
                'price_after_preferences' => ($r['price_after_preferences'] ?? null) !== '' ? (float) $r['price_after_preferences'] : null,
                'rank_position'           => ($r['rank'] ?? null) !== null && $r['rank'] !== '' ? (int) $r['rank'] : null,
                'preferred_bidder'        => $x->yn($r['preferred_bidder'] ?? 0),
                'is_msme'                 => ($msme !== '' && $msme !== '0') ? 1 : 0,
                'reasons_errors'          => $x->nz(trim((string) ($r['reasons_errors'] ?? ''))),
                'legacy_id'               => (int) $r['id'],
            ]);
        }
    }

    // ---- technical evaluation -------------------------------
    if ($x->srcHas('_bid_techncial_list')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_techncial_list`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $s = (string) ($r['status'] ?? '');
            $result = $s === '1' ? 'pass' : ($s === '' ? 'pending' : 'fail');
            $x->upsert('es_bid_technical_eval', [
                'analysis_id'   => (int) $an,
                'bidder_id'     => $bidderIdFor((int) $an, $r['bidder'] ?? null),
                'result'        => $result,
                'currency_code' => $cur($r['currency'] ?? ''),
                'bid_price'     => ($r['bid_price'] ?? null) !== '' ? (float) $r['bid_price'] : null,
                'remarks'       => $x->nz(trim((string) ($r['remarks'] ?? ''))),
                'legacy_id'     => (int) $r['id'],
            ]);
        }
    }

    // ---- list items (criteria / observations / recommendations) --
    $listSrc = [
        'technical_criteria'       => ['_bid_technical_criteria',      'criteria',        'criteria_number'],
        'assessment_criteria'      => ['_bid_assessment_criteria',     'criteria',        'criteria_number'],
        'bid_opening_observation'  => ['_bid_opening_observations',    'observation',     null],
        'evaluation_observation'   => ['_bid_evaluation_observations', 'observation',     'observation_number'],
        'recommendation'           => ['_bid_recommedation_',          'recommendations', 'recommedation_number'],
        'post_qualification'       => ['_bid_post_qualification',      'criteria',        'post_number'],
    ];
    foreach ($listSrc as $kind => [$table, $bodyCol, $numCol]) {
        if (!$x->srcHas($table)) continue;
        $n = 0;
        foreach ($x->srcAll("SELECT * FROM `$table`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $n++;
            $x->upsert('es_bid_list_item', [
                'analysis_id' => (int) $an,
                'kind'        => $kind,
                'item_number' => $numCol && ($r[$numCol] ?? '') !== '' ? (int) $r[$numCol] : $n,
                'body'        => trim((string) ($r[$bodyCol] ?? '')) ?: '—',
                'legacy_id'   => "$kind:{$r['id']}",
            ], ['legacy_id' => "$kind:{$r['id']}"]);
        }
    }

    // ---- routing trail ------------------------------------
    if ($x->srcHas('_bid_routing')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_routing`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $cmt = trim((string) ($r['comments'] ?? ''));
            $cmt = ('[legacy action ' . (int) ($r['action'] ?? 0) . ']' . ($cmt !== '' ? ' ' . $cmt : ''));
            $x->upsert('es_bid_routing', [
                'analysis_id'  => (int) $an,
                'from_user_id' => $x->userByUsername($r['user'] ?? null),
                'action'       => 'comment',
                'comments'     => $cmt,
                'legacy_id'    => (int) $r['id'],
                'ts_create'    => $x->dt($r['ts_create'] ?? ($r['date'] ?? null)) ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ---- discussion messages -----------------------------
    if ($x->srcHas('_bid_analysis_messaging')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_analysis_messaging`") as $r) {
            $an = $x->newId('es_bid_analysis', (int) $r['ss_id_m']) ?? $anByDoc($r['ss_id_m']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $x->upsert('es_bid_message', [
                'analysis_id' => (int) $an,
                'user_id'     => $x->userByUsername($r['user'] ?? null),
                'body'        => trim((string) ($r['message'] ?? '')) ?: '—',
                'legacy_id'   => (int) $r['id'],
                'ts_create'   => $x->dt($r['ts_create'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ---- PDE feedback letters ---------------------------
    if ($x->srcHas('_bid_pde_response')) {
        foreach ($x->srcAll("SELECT * FROM `_bid_pde_response`") as $r) {
            $an = $anByDoc($r['doc_id']);
            if (!$an) { $x->counts['skipped']++; continue; }
            $pub = ((string) ($r['publish'] ?? '') !== '' && (int) $r['publish'] > 0) ? 1 : 0;
            $x->upsert('es_pde_response', [
                'analysis_id'  => (int) $an,
                'body'         => trim((string) ($r['feedback'] ?? '')) ?: '—',
                'published'    => $pub,
                'published_at' => $pub ? ($x->dt($r['ts_update'] ?? null)) : null,
                'legacy_id'    => (int) $r['id'],
                'ts_create'    => $x->dt($r['ts_create'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
        }
    }
}
