-- ============================================================================
--  PPDA e-Services — fresh schema (es_ prefix), lives in the ememo database.
--  Redesigned from the 2021 PHPRunner app: modern types, InnoDB + utf8mb4,
--  explicit foreign keys, readable ENUM workflow states instead of the old
--  magic status integers, and a UNIQUE `legacy_id` on every importable table
--  so the legacy-data importers are safely re-runnable.
--
--  Safe to re-run: every table is dropped first. Run the whole file at once.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
--  Reference / lookup tables
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS es_country;
CREATE TABLE es_country (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  iso2          CHAR(2) DEFAULT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_id     INT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_country_name (name),
  UNIQUE KEY uq_country_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_currency;
CREATE TABLE es_currency (
  code          CHAR(3) NOT NULL,           -- ISO 4217, e.g. MWK, USD
  name          VARCHAR(60) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_id     INT DEFAULT NULL,
  PRIMARY KEY (code),
  UNIQUE KEY uq_currency_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_procurement_method;
CREATE TABLE es_procurement_method (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  code          VARCHAR(20) DEFAULT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_id     INT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm_name (name),
  UNIQUE KEY uq_pm_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_review_type;
CREATE TABLE es_review_type (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_id     INT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rt_name (name),
  UNIQUE KEY uq_rt_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procuring & Disposing Entities
DROP TABLE IF EXISTS es_pde;
CREATE TABLE es_pde (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(200) NOT NULL,
  ref_code      VARCHAR(30) DEFAULT NULL,
  email         VARCHAR(120) DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_id     INT DEFAULT NULL,
  ts_create     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pde_name (name),
  UNIQUE KEY uq_pde_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier category catalogue (goods / services / works) + registration fee
DROP TABLE IF EXISTS es_category;
CREATE TABLE es_category (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type          ENUM('goods','services','works') NOT NULL,
  name          VARCHAR(200) NOT NULL,
  code          VARCHAR(30) DEFAULT NULL,
  fee           DECIMAL(14,2) NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  legacy_kind   ENUM('good','service','works') DEFAULT NULL,  -- which legacy list it came from
  legacy_id     INT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_cat_type (type),
  UNIQUE KEY uq_cat_legacy (legacy_kind, legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  RBAC — roles, permissions, and role assignments.  Seeded by 05_rbac.sql.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS es_role_permission;
DROP TABLE IF EXISTS es_user_role;
DROP TABLE IF EXISTS es_permission;
DROP TABLE IF EXISTS es_role;

CREATE TABLE es_role (
  role_key     VARCHAR(40) NOT NULL,
  label        VARCHAR(80) NOT NULL,
  description  VARCHAR(255) DEFAULT NULL,
  is_system    TINYINT(1) NOT NULL DEFAULT 0,
  sort         SMALLINT NOT NULL DEFAULT 100,
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE es_permission (
  perm_key     VARCHAR(60) NOT NULL,
  label        VARCHAR(120) NOT NULL,
  grp          VARCHAR(40) NOT NULL DEFAULT 'General',
  sort         SMALLINT NOT NULL DEFAULT 100,
  PRIMARY KEY (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE es_role_permission (
  role_key VARCHAR(40) NOT NULL,
  perm_key VARCHAR(60) NOT NULL,
  PRIMARY KEY (role_key, perm_key),
  KEY ix_rp_perm (perm_key),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_key) REFERENCES es_role(role_key) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (perm_key) REFERENCES es_permission(perm_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- e-Services role assignments — independent of ememo's users.role.
CREATE TABLE es_user_role (
  user_id       INT NOT NULL,              -- FK -> ememo users.id
  role          VARCHAR(40) NOT NULL,      -- FK -> es_role.role_key
  pde_id        INT UNSIGNED DEFAULT NULL,
  ts_create     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role),
  KEY ix_ur_role (role),
  CONSTRAINT fk_ur_pde FOREIGN KEY (pde_id) REFERENCES es_pde(id) ON DELETE SET NULL,
  CONSTRAINT fk_ur_role_key FOREIGN KEY (role) REFERENCES es_role(role_key) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Maps a legacy PPDA-officer id to an ememo user (built by the importer).
DROP TABLE IF EXISTS es_legacy_officer_map;
CREATE TABLE es_legacy_officer_map (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  legacy_officer_id  INT NOT NULL,
  user_id            INT DEFAULT NULL,     -- ememo users.id, NULL if unresolved
  officer_name       VARCHAR(150) DEFAULT NULL,
  officer_email      VARCHAR(150) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lom_legacy (legacy_officer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Suppliers  (lookup / host module — imported legacy data lives here)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS es_supplier;
CREATE TABLE es_supplier (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_code      BIGINT UNSIGNED DEFAULT NULL,
  name               VARCHAR(255) NOT NULL,
  trading_name       VARCHAR(255) DEFAULT NULL,
  email              VARCHAR(120) DEFAULT NULL,
  website            VARCHAR(150) DEFAULT NULL,
  business_phone     VARCHAR(30) DEFAULT NULL,
  mobile_phone       VARCHAR(30) DEFAULT NULL,
  postal_address     VARCHAR(255) DEFAULT NULL,
  physical_address   VARCHAR(255) DEFAULT NULL,
  city               VARCHAR(80) DEFAULT NULL,
  country_id         SMALLINT UNSIGNED DEFAULT NULL,
  tin                VARCHAR(30) DEFAULT NULL,
  vat_number         VARCHAR(30) DEFAULT NULL,
  ncic_number        VARCHAR(30) DEFAULT NULL,
  company_number     VARCHAR(30) DEFAULT NULL,
  date_registered    DATE DEFAULT NULL,
  years_operations   SMALLINT UNSIGNED DEFAULT NULL,
  num_employees      INT UNSIGNED DEFAULT NULL,
  status             ENUM('pending','active','expired','suspended','blacklisted') NOT NULL DEFAULT 'active',
  expire_date        DATE DEFAULT NULL,
  source             ENUM('legacy','eservices','manual') NOT NULL DEFAULT 'legacy',
  legacy_status_int  INT DEFAULT NULL,      -- original _supplier_details.supplier_status
  legacy_id          INT DEFAULT NULL,
  created_by         INT DEFAULT NULL,
  ts_create          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sup_name (name),
  KEY ix_sup_code (supplier_code),
  KEY ix_sup_status (status),
  UNIQUE KEY uq_sup_legacy (legacy_id),
  CONSTRAINT fk_sup_country FOREIGN KEY (country_id) REFERENCES es_country(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_supplier_shareholder;
CREATE TABLE es_supplier_shareholder (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id    INT UNSIGNED NOT NULL,
  first_name     VARCHAR(80) NOT NULL,
  last_name      VARCHAR(80) NOT NULL,
  gender         ENUM('male','female','other') DEFAULT NULL,
  national_id    VARCHAR(30) DEFAULT NULL,
  tin            VARCHAR(30) DEFAULT NULL,
  contact_number VARCHAR(30) DEFAULT NULL,
  email          VARCHAR(120) DEFAULT NULL,
  percentage     DECIMAL(6,3) DEFAULT NULL,
  nationality_id SMALLINT UNSIGNED DEFAULT NULL,
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sh_supplier (supplier_id),
  UNIQUE KEY uq_sh_legacy (legacy_id),
  CONSTRAINT fk_sh_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE CASCADE,
  CONSTRAINT fk_sh_country FOREIGN KEY (nationality_id) REFERENCES es_country(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_supplier_category;
CREATE TABLE es_supplier_category (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id    INT UNSIGNED NOT NULL,
  category_id    INT UNSIGNED NOT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sc_pair (supplier_id, category_id),
  KEY ix_sc_cat (category_id),
  CONSTRAINT fk_sc_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE CASCADE,
  CONSTRAINT fk_sc_cat FOREIGN KEY (category_id) REFERENCES es_category(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_supplier_bank;
CREATE TABLE es_supplier_bank (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id    INT UNSIGNED NOT NULL,
  bank_name      VARCHAR(120) NOT NULL,
  branch_name    VARCHAR(120) DEFAULT NULL,
  account_name   VARCHAR(150) DEFAULT NULL,
  account_number VARCHAR(60) DEFAULT NULL,
  account_type   VARCHAR(40) DEFAULT NULL,
  currency_code  CHAR(3) DEFAULT NULL,
  swift_code     VARCHAR(20) DEFAULT NULL,
  country_id     SMALLINT UNSIGNED DEFAULT NULL,
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sb_supplier (supplier_id),
  UNIQUE KEY uq_sb_legacy (legacy_id),
  CONSTRAINT fk_sb_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_currency FOREIGN KEY (currency_code) REFERENCES es_currency(code) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_supplier_certificate;
CREATE TABLE es_supplier_certificate (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id      INT UNSIGNED NOT NULL,
  certificate_no   VARCHAR(60) DEFAULT NULL,
  issue_date       DATE DEFAULT NULL,
  expire_date      DATE DEFAULT NULL,
  file_path        VARCHAR(255) DEFAULT NULL,
  qr_payload       VARCHAR(255) DEFAULT NULL,
  legacy_id        INT DEFAULT NULL,
  ts_create        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_scert_supplier (supplier_id),
  UNIQUE KEY uq_scert_legacy (legacy_id),
  CONSTRAINT fk_scert_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_supplier_attachment;
CREATE TABLE es_supplier_attachment (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id      INT UNSIGNED NOT NULL,
  kind             VARCHAR(60) NOT NULL,
  file_path        VARCHAR(255) NOT NULL,
  original_name    VARCHAR(255) DEFAULT NULL,
  legacy_id        VARCHAR(64) DEFAULT NULL,   -- "<legacy row id>:<kind>:<index>"
  ts_create        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_satt_supplier (supplier_id),
  UNIQUE KEY uq_satt_legacy (legacy_id),
  CONSTRAINT fk_satt_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Bid Analysis  (the rebuilt workflow module)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS es_bid_registry;
CREATE TABLE es_bid_registry (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  serial_no            VARCHAR(60) NOT NULL,
  pde_id               INT UNSIGNED DEFAULT NULL,
  tender_number        VARCHAR(200) DEFAULT NULL,
  subject              VARCHAR(255) NOT NULL,
  submission_details   MEDIUMTEXT DEFAULT NULL,
  procurement_method_id SMALLINT UNSIGNED DEFAULT NULL,
  review_type_id       SMALLINT UNSIGNED DEFAULT NULL,
  channel              ENUM('physical','email','portal') NOT NULL DEFAULT 'physical',
  importance           ENUM('normal','high','urgent') NOT NULL DEFAULT 'normal',
  submission_signed    TINYINT(1) NOT NULL DEFAULT 0,
  date_of_signing      DATE DEFAULT NULL,
  ref_code_pde         VARCHAR(100) DEFAULT NULL,
  in_procurement_plan  TINYINT(1) NOT NULL DEFAULT 0,
  accompanied_documents SET('ipdc_minutes','evaluation_report','original_bidding_document',
                            'advert','contract','treasury_authorization','financial_proposal',
                            'technical_proposal','bidder_bid_documents','general_submission') DEFAULT NULL,
  origin               ENUM('registry','pde') NOT NULL DEFAULT 'registry',   -- who entered it
  received_by          INT DEFAULT NULL,                                     -- registry clerk / PDE user who keyed it
  registry_checked_by  INT DEFAULT NULL,
  registry_checked_at  DATETIME DEFAULT NULL,
  registry_comment     VARCHAR(255) DEFAULT NULL,
  allocated_by         INT DEFAULT NULL,
  allocated_at         DATETIME DEFAULT NULL,
  assigned_officer_id  INT DEFAULT NULL,
  status               ENUM('pending_registry','returned_to_pde','pending_allocation',
                            'assigned','in_analysis','completed','closed','withdrawn')
                          NOT NULL DEFAULT 'pending_allocation',
  legacy_id            INT DEFAULT NULL,
  ts_create            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reg_serial (serial_no),
  UNIQUE KEY uq_reg_legacy (legacy_id),
  KEY ix_reg_status (status),
  KEY ix_reg_pde (pde_id),
  KEY ix_reg_officer (assigned_officer_id),
  CONSTRAINT fk_reg_pde FOREIGN KEY (pde_id) REFERENCES es_pde(id) ON DELETE SET NULL,
  CONSTRAINT fk_reg_pm  FOREIGN KEY (procurement_method_id) REFERENCES es_procurement_method(id) ON DELETE SET NULL,
  CONSTRAINT fk_reg_rt  FOREIGN KEY (review_type_id) REFERENCES es_review_type(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- allocation / reassignment trail for a submission (who moved it to whom, when, why)
DROP TABLE IF EXISTS es_bid_allocation;
CREATE TABLE es_bid_allocation (
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

DROP TABLE IF EXISTS es_bid_analysis;
CREATE TABLE es_bid_analysis (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registry_id          INT UNSIGNED NOT NULL,
  officer_id           INT DEFAULT NULL,
  approved_proc_plan       TINYINT(1) NOT NULL DEFAULT 0,
  approved_workplan        TINYINT(1) NOT NULL DEFAULT 0,
  preferences_applied      TINYINT(1) NOT NULL DEFAULT 0,
  publication_done         TINYINT(1) NOT NULL DEFAULT 0,
  date_of_publication      DATE DEFAULT NULL,
  publication_source       VARCHAR(120) DEFAULT NULL,
  bid_opening_minutes_signed TINYINT(1) NOT NULL DEFAULT 0,
  bid_opening_minutes_date DATE DEFAULT NULL,
  evaluation_report_signed TINYINT(1) NOT NULL DEFAULT 0,
  evaluation_report_date   DATE DEFAULT NULL,
  bid_validity_days        SMALLINT UNSIGNED DEFAULT NULL,
  ipdc_minutes_signed      TINYINT(1) NOT NULL DEFAULT 0,
  ipdc_minutes_date        DATE DEFAULT NULL,
  comment_on_ipdc          MEDIUMTEXT DEFAULT NULL,
  all_bids_enclosed        TINYINT(1) NOT NULL DEFAULT 0,
  original_bid_enclosed    TINYINT(1) NOT NULL DEFAULT 0,
  bids_still_valid         TINYINT(1) NOT NULL DEFAULT 0,
  stage                ENUM('draft','supervisor_review','director_review','dg_review',
                            'board_review','approved','returned','rejected')
                          NOT NULL DEFAULT 'draft',
  current_owner_id     INT DEFAULT NULL,
  officer_feedback     MEDIUMTEXT DEFAULT NULL,
  dg_feedback          MEDIUMTEXT DEFAULT NULL,
  final_outcome        ENUM('pending','compliant','non_compliant','no_objection','objection')
                          NOT NULL DEFAULT 'pending',
  archived             TINYINT(1) NOT NULL DEFAULT 0,   -- parked / closed without a decision
  archived_at          DATETIME DEFAULT NULL,
  archived_by          INT DEFAULT NULL,
  archived_reason      VARCHAR(255) DEFAULT NULL,
  legacy_id            INT DEFAULT NULL,
  legacy_doc_id        INT DEFAULT NULL,    -- _bid_analysis.doc_id (children join on this)
  legacy_status_note   VARCHAR(255) DEFAULT NULL,
  submitted_at         DATETIME DEFAULT NULL,
  decided_at           DATETIME DEFAULT NULL,
  ts_create            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_an_registry (registry_id),
  KEY ix_an_stage (stage),
  KEY ix_an_owner (current_owner_id),
  KEY ix_an_doc (legacy_doc_id),
  UNIQUE KEY uq_an_legacy (legacy_id),
  CONSTRAINT fk_an_registry FOREIGN KEY (registry_id) REFERENCES es_bid_registry(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_lot;
CREATE TABLE es_bid_lot (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id    INT UNSIGNED NOT NULL,
  lot_number     INT NOT NULL,
  name           VARCHAR(255) NOT NULL,
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lot_an (analysis_id),
  UNIQUE KEY uq_lot_legacy (legacy_id),
  CONSTRAINT fk_lot_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_bidder;
CREATE TABLE es_bid_bidder (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id        INT UNSIGNED NOT NULL,
  lot_id             INT UNSIGNED DEFAULT NULL,
  supplier_id        INT UNSIGNED DEFAULT NULL,
  bidder_number      INT NOT NULL,
  name               VARCHAR(255) NOT NULL,
  currency_code      CHAR(3) DEFAULT NULL,
  read_out_price     DECIMAL(20,2) DEFAULT NULL,
  bid_status         ENUM('responsive','non_responsive','rejected','withdrawn') NOT NULL DEFAULT 'responsive',
  reason_rejection   MEDIUMTEXT DEFAULT NULL,
  remarks            VARCHAR(255) DEFAULT NULL,
  sort_order         INT DEFAULT NULL,
  legacy_id          INT DEFAULT NULL,
  ts_create          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bd_an (analysis_id),
  KEY ix_bd_lot (lot_id),
  KEY ix_bd_supplier (supplier_id),
  UNIQUE KEY uq_bd_legacy (legacy_id),
  CONSTRAINT fk_bd_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_bd_lot FOREIGN KEY (lot_id) REFERENCES es_bid_lot(id) ON DELETE SET NULL,
  CONSTRAINT fk_bd_supplier FOREIGN KEY (supplier_id) REFERENCES es_supplier(id) ON DELETE SET NULL,
  CONSTRAINT fk_bd_currency FOREIGN KEY (currency_code) REFERENCES es_currency(code) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_financial_eval;
CREATE TABLE es_bid_financial_eval (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id            INT UNSIGNED NOT NULL,
  bidder_id              INT UNSIGNED DEFAULT NULL,
  lot_id                 INT UNSIGNED DEFAULT NULL,
  currency_code          CHAR(3) DEFAULT NULL,
  bid_price              DECIMAL(20,2) DEFAULT NULL,
  computation_errors     DECIMAL(20,2) NOT NULL DEFAULT 0,
  corrected_bid_price    DECIMAL(20,2) DEFAULT NULL,
  exchange_rate          DECIMAL(16,6) NOT NULL DEFAULT 1,
  price_after_preferences DECIMAL(20,2) DEFAULT NULL,
  rank_position          INT DEFAULT NULL,
  preferred_bidder       TINYINT(1) NOT NULL DEFAULT 0,
  is_msme                TINYINT(1) NOT NULL DEFAULT 0,
  reasons_errors         MEDIUMTEXT DEFAULT NULL,
  legacy_id              INT DEFAULT NULL,
  ts_create              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_fe_an (analysis_id),
  KEY ix_fe_bidder (bidder_id),
  UNIQUE KEY uq_fe_legacy (legacy_id),
  CONSTRAINT fk_fe_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_fe_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_technical_eval;
CREATE TABLE es_bid_technical_eval (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id        INT UNSIGNED NOT NULL,
  bidder_id          INT UNSIGNED DEFAULT NULL,
  lot_id             INT UNSIGNED DEFAULT NULL,
  result             ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',
  score              DECIMAL(7,2) DEFAULT NULL,
  currency_code      CHAR(3) DEFAULT NULL,
  bid_price          DECIMAL(20,2) DEFAULT NULL,
  remarks            MEDIUMTEXT DEFAULT NULL,
  legacy_id          INT DEFAULT NULL,
  ts_create          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_te_an (analysis_id),
  KEY ix_te_bidder (bidder_id),
  UNIQUE KEY uq_te_legacy (legacy_id),
  CONSTRAINT fk_te_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_te_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_list_item;
CREATE TABLE es_bid_list_item (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id    INT UNSIGNED NOT NULL,
  lot_id         INT UNSIGNED DEFAULT NULL,   -- criteria can be per lot
  kind           ENUM('technical_criteria','assessment_criteria','bid_opening_observation',
                      'evaluation_observation','recommendation','post_qualification') NOT NULL,
  item_number    INT NOT NULL DEFAULT 1,
  body           MEDIUMTEXT NOT NULL,
  legacy_id      VARCHAR(64) DEFAULT NULL,   -- "<kind>:<legacy row id>"
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_li_an_kind (analysis_id, kind),
  KEY ix_li_lot (lot_id),
  UNIQUE KEY uq_li_legacy (legacy_id),
  CONSTRAINT fk_li_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_li_lot FOREIGN KEY (lot_id) REFERENCES es_bid_lot(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- preliminary (compliance) evaluation — one row per bidder; pass -> technical
DROP TABLE IF EXISTS es_bid_prelim_eval;
CREATE TABLE es_bid_prelim_eval (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id  INT UNSIGNED NOT NULL,
  bidder_id    INT UNSIGNED DEFAULT NULL,
  lot_id       INT UNSIGNED DEFAULT NULL,
  result       ENUM('pending','pass','fail') NOT NULL DEFAULT 'pending',
  remarks      MEDIUMTEXT DEFAULT NULL,
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pe (analysis_id, bidder_id),
  KEY ix_pe_an (analysis_id),
  CONSTRAINT fk_pe_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post-qualification evaluation — for the preferred bidder(s)
DROP TABLE IF EXISTS es_bid_postqual_eval;
CREATE TABLE es_bid_postqual_eval (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id  INT UNSIGNED NOT NULL,
  bidder_id    INT UNSIGNED DEFAULT NULL,
  lot_id       INT UNSIGNED DEFAULT NULL,
  result       ENUM('pending','pass','fail') NOT NULL DEFAULT 'pending',
  remarks      MEDIUMTEXT DEFAULT NULL,
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pq (analysis_id, bidder_id),
  KEY ix_pq_an (analysis_id),
  CONSTRAINT fk_pq_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE,
  CONSTRAINT fk_pq_bidder FOREIGN KEY (bidder_id) REFERENCES es_bid_bidder(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_routing;
CREATE TABLE es_bid_routing (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id    INT UNSIGNED NOT NULL,
  from_user_id   INT DEFAULT NULL,
  to_user_id     INT DEFAULT NULL,
  action         ENUM('submit','endorse','return','approve','reject','comment','assign','reopen','archive','unarchive') NOT NULL,
  from_stage     VARCHAR(30) DEFAULT NULL,
  to_stage       VARCHAR(30) DEFAULT NULL,
  comments       MEDIUMTEXT DEFAULT NULL,
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rt_an (analysis_id),
  UNIQUE KEY uq_rt_legacy (legacy_id),
  CONSTRAINT fk_rt_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- board (group) review — one vote per board member per analysis; majority decides
DROP TABLE IF EXISTS es_bid_board_vote;
CREATE TABLE es_bid_board_vote (
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

DROP TABLE IF EXISTS es_bid_message;
CREATE TABLE es_bid_message (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id    INT UNSIGNED NOT NULL,
  user_id        INT DEFAULT NULL,
  body           MEDIUMTEXT NOT NULL,
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_msg_an (analysis_id),
  UNIQUE KEY uq_msg_legacy (legacy_id),
  CONSTRAINT fk_msg_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_bid_attachment;
CREATE TABLE es_bid_attachment (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registry_id    INT UNSIGNED DEFAULT NULL,
  analysis_id    INT UNSIGNED DEFAULT NULL,
  kind           VARCHAR(60) DEFAULT NULL,
  file_path      VARCHAR(255) NOT NULL,
  original_name  VARCHAR(255) DEFAULT NULL,
  uploaded_by    INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_att_reg (registry_id),
  KEY ix_att_an (analysis_id),
  CONSTRAINT fk_att_reg FOREIGN KEY (registry_id) REFERENCES es_bid_registry(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_an  FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS es_pde_response;
CREATE TABLE es_pde_response (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  analysis_id    INT UNSIGNED NOT NULL,
  body           MEDIUMTEXT NOT NULL,
  published      TINYINT(1) NOT NULL DEFAULT 0,
  published_at   DATETIME DEFAULT NULL,
  created_by     INT DEFAULT NULL,
  sent_at        DATETIME DEFAULT NULL,          -- dispatched to the PDE (emailed / downloaded / handed over)
  sent_by        INT DEFAULT NULL,
  sent_method    VARCHAR(20) DEFAULT NULL,        -- email | download | manual
  legacy_id      INT DEFAULT NULL,
  ts_create      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ts_update      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pr_an (analysis_id),
  UNIQUE KEY uq_pr_legacy (legacy_id),
  CONSTRAINT fk_pr_an FOREIGN KEY (analysis_id) REFERENCES es_bid_analysis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- who viewed / downloaded / emailed a published response
DROP TABLE IF EXISTS es_response_access;
CREATE TABLE es_response_access (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id  INT UNSIGNED NOT NULL,
  user_id      INT DEFAULT NULL,
  action       ENUM('view','download','email') NOT NULL DEFAULT 'view',
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ra_resp (response_id),
  CONSTRAINT fk_ra_resp FOREIGN KEY (response_id) REFERENCES es_pde_response(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Import run history (written by import/run.php and import/index.php)
DROP TABLE IF EXISTS es_import_log;
CREATE TABLE es_import_log (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase         VARCHAR(40) NOT NULL,
  dry_run       TINYINT(1) NOT NULL DEFAULT 0,
  inserted      INT NOT NULL DEFAULT 0,
  updated       INT NOT NULL DEFAULT 0,
  skipped       INT NOT NULL DEFAULT 0,
  warnings      MEDIUMTEXT DEFAULT NULL,
  ran_by        INT DEFAULT NULL,
  ts_create     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_implog_phase (phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
