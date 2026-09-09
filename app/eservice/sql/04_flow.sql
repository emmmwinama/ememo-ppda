-- ============================================================================
--  PPDA e-Services — submission-flow migration
--
--  Adds the registry-check / DG-allocation flow to an EXISTING database.
--  (A fresh 01_schema.sql already contains all of this.)
--  MariaDB 10.5 — uses ADD COLUMN IF NOT EXISTS.
--
--    mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo < app/eservice/sql/04_flow.sql
-- ============================================================================
SET NAMES utf8mb4;

-- 1) e-Services roles: widen `role` so new keys (pde, allocator, …) fit.
--    Superseded by 05_rbac.sql, which turns `role` into VARCHAR + FK to
--    es_role. Skip this step entirely once that FK is in place, otherwise
--    just widen to VARCHAR (NOT a bigger ENUM) so 05_rbac.sql can follow.
SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'es_user_role'
                  AND CONSTRAINT_NAME = 'fk_ur_role_key');
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE es_user_role MODIFY role VARCHAR(40) NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) registry: origin + registry-check + allocation columns
ALTER TABLE es_bid_registry
  ADD COLUMN IF NOT EXISTS origin ENUM('registry','pde') NOT NULL DEFAULT 'registry' AFTER in_procurement_plan,
  ADD COLUMN IF NOT EXISTS registry_checked_by INT DEFAULT NULL AFTER received_by,
  ADD COLUMN IF NOT EXISTS registry_checked_at DATETIME DEFAULT NULL AFTER registry_checked_by,
  ADD COLUMN IF NOT EXISTS registry_comment VARCHAR(255) DEFAULT NULL AFTER registry_checked_at,
  ADD COLUMN IF NOT EXISTS allocated_by INT DEFAULT NULL AFTER registry_comment,
  ADD COLUMN IF NOT EXISTS allocated_at DATETIME DEFAULT NULL AFTER allocated_by,
  ADD COLUMN IF NOT EXISTS accompanied_documents
    SET('ipdc_minutes','evaluation_report','original_bidding_document','advert','contract',
        'treasury_authorization','financial_proposal','technical_proposal','bidder_bid_documents',
        'general_submission') DEFAULT NULL AFTER in_procurement_plan;

-- 4) allocation / reassignment trail
CREATE TABLE IF NOT EXISTS es_bid_allocation (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registry_id     INT UNSIGNED NOT NULL,
  action          ENUM('allocate','reassign') NOT NULL DEFAULT 'allocate',
  from_officer_id INT DEFAULT NULL,
  to_officer_id   INT DEFAULT NULL,
  by_user_id      INT DEFAULT NULL,
  reason          VARCHAR(500) DEFAULT NULL,
  ts_create       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_alloc_reg (registry_id),
  CONSTRAINT fk_alloc_reg FOREIGN KEY (registry_id) REFERENCES es_bid_registry(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) widen the status enum, keeping current rows valid, then remap old values
ALTER TABLE es_bid_registry
  MODIFY status ENUM('received','assigned','in_analysis','completed','closed','withdrawn',
                     'pending_registry','returned_to_pde','pending_allocation') NOT NULL DEFAULT 'pending_allocation';

UPDATE es_bid_registry SET status = 'pending_allocation' WHERE status = 'received';

ALTER TABLE es_bid_registry
  MODIFY status ENUM('pending_registry','returned_to_pde','pending_allocation',
                     'assigned','in_analysis','completed','closed','withdrawn') NOT NULL DEFAULT 'pending_allocation';

-- 5) board voting + broaden the response-letter permission
CREATE TABLE IF NOT EXISTS es_bid_board_vote (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id  INT UNSIGNED NOT NULL,
  user_id      INT NOT NULL,
  decision     ENUM('approve','return') NOT NULL,
  comment      MEDIUMTEXT DEFAULT NULL,
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bv (analysis_id, user_id),
  KEY ix_bv_an (analysis_id),
  CONSTRAINT fk_bv_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO es_role_permission (role_key, perm_key) VALUES
 ('supervisor','response.write'),('director','response.write'),('board','response.write');

-- 6) evaluation restructure: per-lot criteria + preliminary / post-qualification evaluation
ALTER TABLE es_bid_list_item
  ADD COLUMN IF NOT EXISTS lot_id INT UNSIGNED DEFAULT NULL AFTER analysis_id,
  ADD KEY IF NOT EXISTS ix_li_lot (lot_id);

CREATE TABLE IF NOT EXISTS es_bid_prelim_eval (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, analysis_id INT UNSIGNED NOT NULL,
  bidder_id INT UNSIGNED DEFAULT NULL, lot_id INT UNSIGNED DEFAULT NULL,
  result ENUM('pending','pass','fail') NOT NULL DEFAULT 'pending', remarks MEDIUMTEXT DEFAULT NULL,
  ts_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_pe (analysis_id, bidder_id), KEY ix_pe_an (analysis_id),
  CONSTRAINT fk_pe_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS es_bid_postqual_eval (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, analysis_id INT UNSIGNED NOT NULL,
  bidder_id INT UNSIGNED DEFAULT NULL, lot_id INT UNSIGNED DEFAULT NULL,
  result ENUM('pending','pass','fail') NOT NULL DEFAULT 'pending', remarks MEDIUMTEXT DEFAULT NULL,
  ts_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_pq (analysis_id, bidder_id), KEY ix_pq_an (analysis_id),
  CONSTRAINT fk_pq_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_pq_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) submission-response dispatch tracking
ALTER TABLE es_pde_response
  ADD COLUMN IF NOT EXISTS sent_at DATETIME DEFAULT NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS sent_by INT DEFAULT NULL AFTER sent_at,
  ADD COLUMN IF NOT EXISTS sent_method VARCHAR(20) DEFAULT NULL AFTER sent_by;

CREATE TABLE IF NOT EXISTS es_response_access (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id INT UNSIGNED NOT NULL,
  user_id INT DEFAULT NULL,
  action ENUM('view','download','email') NOT NULL DEFAULT 'view',
  ts_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ix_ra_resp (response_id),
  CONSTRAINT fk_ra_resp FOREIGN KEY (response_id) REFERENCES es_pde_response(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) archive: park a review without a decision (still counts as active work)
ALTER TABLE es_bid_analysis
  ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS archived_by INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS archived_reason VARCHAR(255) DEFAULT NULL;
ALTER TABLE es_bid_analysis ADD KEY IF NOT EXISTS ix_an_archived (archived);
ALTER TABLE es_bid_routing
  MODIFY action ENUM('submit','endorse','return','approve','reject','comment','assign','reopen','archive','unarchive') NOT NULL;
