-- ============================================================================
--  PPDA e-Services — DEMO DATA  (≥ 20 rows in every es_* table)
--
--  For a populated system to click around. Re-runnable: it first deletes its
--  own rows (demo suppliers / registries cascade to their children) and tops
--  the lookup tables up with INSERT IGNORE.
--
--  Requires: 01_schema.sql + 02_seed.sql already loaded, and some rows in the
--  ememo `users` table (role assignments and *_by columns reference them;
--  they fall back to NULL if `users` is empty).
--
--    mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo < app/eservice/sql/03_demo_seed.sql
-- ============================================================================
SET NAMES utf8mb4;
SET SESSION sql_mode = '';
SET FOREIGN_KEY_CHECKS = 1;

-- 1..30 counter -------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS _seq;
CREATE TEMPORARY TABLE _seq (n INT PRIMARY KEY);
INSERT INTO _seq (n) VALUES
 (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),
 (16),(17),(18),(19),(20),(21),(22),(23),(24),(25),(26),(27),(28),(29),(30);

-- ---------------------------------------------------------------------------
--  Clean previous demo rows (children cascade via ON DELETE CASCADE)
-- ---------------------------------------------------------------------------
DELETE FROM es_supplier      WHERE name LIKE 'DEMO —%';
DELETE FROM es_bid_registry  WHERE serial_no LIKE 'DEMO/%';
DELETE FROM es_legacy_officer_map WHERE officer_name LIKE 'DEMO %';
DELETE FROM es_import_log     WHERE phase LIKE 'demo-%';

-- ---------------------------------------------------------------------------
--  Base lookups — also in 02_seed.sql, repeated here so 03 works standalone.
--  INSERT IGNORE = no-op if 02_seed.sql already loaded them.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_currency (code, name) VALUES
 ('MWK','Malawi Kwacha'),('USD','US Dollar'),('EUR','Euro'),('GBP','Pound Sterling'),
 ('ZAR','South African Rand'),('ZMW','Zambian Kwacha');

INSERT IGNORE INTO es_country (name, iso2) VALUES
 ('Malawi','MW'),('South Africa','ZA'),('Zambia','ZM'),('Tanzania','TZ'),('Mozambique','MZ'),
 ('Zimbabwe','ZW'),('Kenya','KE'),('United Kingdom','GB'),('United States','US'),('India','IN'),
 ('China','CN'),('Other','XX');

INSERT IGNORE INTO es_procurement_method (name, code) VALUES
 ('Open National Bidding','ONB'),('Open International Bidding','OIB'),('Restricted Bidding','RB'),
 ('Request for Quotations','RFQ'),('Request for Proposals','RFP'),('Single Source','SS'),
 ('Direct Procurement','DP'),('Framework Agreement','FA'),('Community Participation','CP');

INSERT IGNORE INTO es_review_type (name) VALUES
 ('Contract Award Review'),('Pre-Award Review'),('Post Review'),('Deviation Request'),
 ('Extension of Bid Validity'),('Complaint / Review of Decision');

INSERT IGNORE INTO es_pde (name, ref_code, email, address) VALUES
 ('Public Procurement and Disposal of Assets Authority','PPDA','info@ppda.mw','Lilongwe'),
 ('Ministry of Health','MOH','procurement@health.gov.mw','Lilongwe'),
 ('Ministry of Education','MOE','procurement@education.gov.mw','Lilongwe'),
 ('Roads Authority','RA','procurement@ra.org.mw','Lilongwe');

INSERT IGNORE INTO es_category (type, name, code, fee) VALUES
 ('goods','Office Supplies & Stationery','G-OSS',50000.00),
 ('goods','ICT Equipment & Accessories','G-ICT',75000.00),
 ('goods','Motor Vehicles & Spare Parts','G-MVS',100000.00),
 ('goods','Furniture & Fittings','G-FUR',50000.00),
 ('goods','Medical Supplies & Equipment','G-MED',100000.00),
 ('goods','Building & Construction Materials','G-BCM',75000.00),
 ('goods','Foodstuffs & Catering Supplies','G-FCS',50000.00),
 ('services','Consultancy - Management & Finance','S-CMF',150000.00),
 ('services','Consultancy - Engineering','S-CEN',150000.00),
 ('services','ICT Services & Software','S-ICT',100000.00),
 ('services','Security Services','S-SEC',75000.00),
 ('services','Cleaning & Fumigation','S-CLN',50000.00),
 ('services','Transport & Logistics','S-TRL',75000.00),
 ('services','Printing & Publishing','S-PRP',50000.00),
 ('works','Building Construction','W-BLD',200000.00),
 ('works','Road & Bridge Works','W-RBW',200000.00),
 ('works','Electrical Installations','W-ELE',150000.00),
 ('works','Water & Sanitation Works','W-WSW',150000.00),
 ('works','Maintenance & Refurbishment','W-MRF',100000.00);

-- ---------------------------------------------------------------------------
--  Extra lookups — top up to ≥ 20 each
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_country (name, iso2) VALUES
 ('Botswana','BW'),('Namibia','NA'),('Rwanda','RW'),('Uganda','UG'),('Ethiopia','ET'),
 ('Nigeria','NG'),('Ghana','GH'),('Egypt','EG'),('Germany','DE'),('France','FR'),
 ('Italy','IT'),('Spain','ES'),('Netherlands','NL'),('United Arab Emirates','AE'),
 ('Turkey','TR'),('Japan','JP'),('Brazil','BR'),('Canada','CA'),('Australia','AU'),
 ('Portugal','PT'),('Belgium','BE'),('Sweden','SE');

INSERT IGNORE INTO es_currency (code, name) VALUES
 ('KES','Kenyan Shilling'),('TZS','Tanzanian Shilling'),('UGX','Ugandan Shilling'),
 ('NGN','Nigerian Naira'),('GHS','Ghanaian Cedi'),('BWP','Botswana Pula'),
 ('NAD','Namibian Dollar'),('EGP','Egyptian Pound'),('INR','Indian Rupee'),
 ('CNY','Chinese Yuan'),('JPY','Japanese Yen'),('AED','UAE Dirham'),
 ('CHF','Swiss Franc'),('CAD','Canadian Dollar'),('AUD','Australian Dollar'),
 ('SEK','Swedish Krona'),('BRL','Brazilian Real'),('TRY','Turkish Lira');

INSERT IGNORE INTO es_procurement_method (name, code) VALUES
 ('Two-Stage Bidding','TSB'),('Design Competition','DC'),('Force Account','FA2'),
 ('Micro Procurement','MP'),('Emergency Procurement','EP'),('E-Reverse Auction','ERA'),
 ('Pre-qualified Bidding','PQB'),('Competitive Negotiations','CN'),
 ('International Consultant Selection','ICS'),('National Consultant Selection','NCS'),
 ('Quality & Cost Based Selection','QCBS'),('Least Cost Selection','LCS'),
 ('Fixed Budget Selection','FBS');

INSERT IGNORE INTO es_review_type (name) VALUES
 ('Bid Document Review'),('Evaluation Report Review'),('Contract Variation Review'),
 ('Termination Review'),('Direct Procurement Approval'),('Single Source Approval'),
 ('Threshold Waiver'),('Standstill Complaint'),('Debarment Review'),
 ('Framework Call-off Review'),('Retendering Approval'),('Award Recommendation Review'),
 ('Emergency Procurement Approval'),('Consultancy Shortlist Review'),
 ('No-Objection Request'),('Contract Extension Review'),('Specification Review'),
 ('Lot Award Split Review');

INSERT IGNORE INTO es_category (type, name, code, fee) VALUES
 ('goods','Laboratory Equipment & Reagents','G-LAB',100000.00),
 ('goods','Agricultural Inputs & Machinery','G-AGR',75000.00),
 ('goods','Uniforms & Protective Clothing','G-UPC',50000.00),
 ('goods','Fuel, Oils & Lubricants','G-FOL',150000.00),
 ('services','Audit & Assurance','S-AUD',150000.00),
 ('services','Legal Services','S-LEG',150000.00),
 ('services','Media, Advertising & PR','S-MED2',75000.00),
 ('services','Training & Capacity Building','S-TRN',75000.00),
 ('works','Borehole Drilling','W-BOR',150000.00),
 ('works','Landscaping & Grounds','W-LND',75000.00);

INSERT IGNORE INTO es_pde (name, ref_code, email, address) VALUES
 ('Ministry of Finance','MOF','procurement@finance.gov.mw','Lilongwe'),
 ('Ministry of Agriculture','MOA','procurement@agric.gov.mw','Lilongwe'),
 ('Ministry of Transport','MOT','procurement@transport.gov.mw','Lilongwe'),
 ('Ministry of Lands','MOL','procurement@lands.gov.mw','Lilongwe'),
 ('Ministry of Local Government','MLG','procurement@localgov.gov.mw','Lilongwe'),
 ('Malawi Revenue Authority','MRA','procurement@mra.mw','Blantyre'),
 ('Electricity Supply Corporation of Malawi','ESCOM','procurement@escom.mw','Blantyre'),
 ('Malawi Water Board','MWB','procurement@mwb.mw','Lilongwe'),
 ('National Local Government Finance Committee','NLGFC','procurement@nlgfc.gov.mw','Lilongwe'),
 ('University of Malawi','UNIMA','procurement@unima.ac.mw','Zomba'),
 ('Malawi University of Business and Applied Sciences','MUBAS','procurement@mubas.ac.mw','Blantyre'),
 ('Kamuzu University of Health Sciences','KUHeS','procurement@kuhes.ac.mw','Blantyre'),
 ('Central Medical Stores Trust','CMST','procurement@cmst.mw','Lilongwe'),
 ('Road Fund Administration','RFA','procurement@roadfund.mw','Lilongwe'),
 ('Malawi Police Service','MPS','procurement@police.gov.mw','Lilongwe'),
 ('Malawi Defence Force','MDF','procurement@mdf.gov.mw','Lilongwe'),
 ('Airport Developments Limited','ADL','procurement@adl.mw','Lilongwe'),
 ('Blantyre Water Board','BWB','procurement@bwb.mw','Blantyre'),
 ('Lilongwe Water Board','LWB','procurement@lwb.mw','Lilongwe'),
 ('Malawi Housing Corporation','MHC','procurement@mhc.mw','Blantyre');

-- ---------------------------------------------------------------------------
--  System roles — also seeded by 05_rbac.sql, repeated here so 03 works
--  standalone (es_user_role.role has an FK to es_role.role_key).
--  INSERT IGNORE = no-op if 05_rbac.sql already loaded them.
--  NOTE: the permission catalogue + role→permission bundles live in
--  05_rbac.sql only — run that for the admin panel to work.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_role (role_key, label, description, is_system, sort) VALUES
 ('registry',   'Registry',           'PPDA registry office — logs submissions and checks completeness.', 1, 10),
 ('pde',        'PDE representative',  'Files and tracks submissions for one procuring & disposing entity.', 1, 20),
 ('allocator',  'Allocator',          'Allocates checked submissions to technical officers.', 1, 30),
 ('officer',    'Technical officer',  'Runs the bid analysis and evaluation.', 1, 40),
 ('supervisor', 'Supervisor',         'Reviews and endorses the officer''s analysis.', 1, 50),
 ('director',   'Director',            'Directorate-level review of an analysis.', 1, 60),
 ('dg',         'Director General',    'Final decision on an analysis; can allocate.', 1, 70),
 ('board',      'Analysis Board',      'Board-level review.', 1, 80),
 ('admin',      'Administrator',       'Full access to every e-Services function.', 1, 999);

-- ---------------------------------------------------------------------------
--  es_user_role — assign e-services roles to up to 20 ememo users
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_user_role (user_id, role)
SELECT u.id,
       ELT(1 + (u.rn % 7), 'registry','allocator','officer','supervisor','director','dg','board')
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS rn FROM users ORDER BY id LIMIT 20) u;
-- give the first user every workflow role so there is always a full chain
INSERT IGNORE INTO es_user_role (user_id, role)
SELECT MIN(id), r.role FROM users
CROSS JOIN (SELECT 'registry' role UNION SELECT 'allocator' UNION SELECT 'officer' UNION SELECT 'supervisor'
            UNION SELECT 'director' UNION SELECT 'dg' UNION SELECT 'board' UNION SELECT 'admin') r
GROUP BY r.role;
-- a couple of PDE users linked to the first two PDEs
INSERT INTO es_user_role (user_id, role, pde_id)
SELECT u.id, 'pde', (SELECT id FROM es_pde ORDER BY id LIMIT 1 OFFSET 0)
FROM (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 1) u
ON DUPLICATE KEY UPDATE pde_id = VALUES(pde_id);
INSERT INTO es_user_role (user_id, role, pde_id)
SELECT u.id, 'pde', (SELECT id FROM es_pde ORDER BY id LIMIT 1 OFFSET 1)
FROM (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 2) u
ON DUPLICATE KEY UPDATE pde_id = VALUES(pde_id);

-- ---------------------------------------------------------------------------
--  es_legacy_officer_map — 25 rows
-- ---------------------------------------------------------------------------
INSERT INTO es_legacy_officer_map (legacy_officer_id, user_id, officer_name, officer_email)
SELECT 9000 + q.n,
       (SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 0),
       CONCAT('DEMO Officer ', LPAD(q.n,2,'0')),
       CONCAT('demo.officer', q.n, '@ppda.mw')
FROM _seq q WHERE q.n BETWEEN 1 AND 25
ON DUPLICATE KEY UPDATE officer_name = VALUES(officer_name);

-- ---------------------------------------------------------------------------
--  Suppliers — 25 companies + one row per child table each
-- ---------------------------------------------------------------------------
INSERT INTO es_supplier
 (name, trading_name, email, website, business_phone, mobile_phone, postal_address,
  physical_address, city, country_id, tin, vat_number, date_registered, years_operations,
  num_employees, status, expire_date, source, legacy_status_int)
SELECT
  CONCAT('DEMO — Supplier ', LPAD(q.n,3,'0'), ' Ltd'),
  CONCAT('DemoCo ', q.n),
  CONCAT('supplier', q.n, '@example.mw'),
  CONCAT('www.democo', q.n, '.mw'),
  CONCAT('+265 1 ', LPAD((q.n*13) % 1000000, 6, '0')),
  CONCAT('+265 99 ', LPAD((q.n*77) % 1000000, 6, '0')),
  CONCAT('P.O. Box ', 100 + q.n, ', ', ELT(1+(q.n%3),'Lilongwe','Blantyre','Mzuzu')),
  CONCAT('Plot ', 1000 + q.n, ', Area ', 10 + (q.n % 30)),
  ELT(1 + (q.n % 5), 'Lilongwe','Blantyre','Mzuzu','Zomba','Kasungu'),
  ct.id,
  CONCAT('TIN', LPAD(q.n, 7, '0')),
  CONCAT('VAT', LPAD(q.n, 6, '0')),
  DATE_SUB(CURDATE(), INTERVAL (q.n + 2) YEAR),
  q.n % 20 + 1,
  q.n * 7 + 4,
  ELT(1 + (q.n % 5), 'active','active','active','expired','suspended'),
  DATE_ADD(CURDATE(), INTERVAL (q.n - 12) MONTH),
  'manual',
  q.n % 5
FROM _seq q
JOIN (SELECT id, (ROW_NUMBER() OVER (ORDER BY id)) - 1 AS rk, COUNT(*) OVER () AS c FROM es_country) ct
  ON ct.rk = q.n % ct.c
WHERE q.n BETWEEN 1 AND 25;

-- numbered view of the demo suppliers for the child inserts
-- (re-derived in each statement; MariaDB has no persistent CTE)

INSERT INTO es_supplier_shareholder
 (supplier_id, first_name, last_name, gender, national_id, tin, contact_number, email, percentage, nationality_id)
SELECT sp.id,
       ELT(1+(sp.rn%6),'James','Grace','Peter','Mary','John','Alice'),
       ELT(1+(sp.rn%6),'Banda','Phiri','Mwale','Gondwe','Zulu','Kumwenda'),
       ELT(1+(sp.rn%2),'male','female'),
       CONCAT('NID', LPAD(sp.rn, 8, '0')),
       CONCAT('TIN', LPAD(sp.rn + 500, 7, '0')),
       CONCAT('+265 88 ', LPAD((sp.rn*91) % 1000000, 6, '0')),
       CONCAT('shareholder', sp.rn, '@example.mw'),
       100.000,
       (SELECT id FROM es_country ORDER BY id LIMIT 1)
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp;

INSERT INTO es_supplier_category (supplier_id, category_id)
SELECT sp.id, cat.id
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp
JOIN (SELECT id, (ROW_NUMBER() OVER (ORDER BY id)) - 1 AS rk, COUNT(*) OVER () AS c FROM es_category) cat
  ON cat.rk = sp.rn % cat.c;

INSERT INTO es_supplier_bank
 (supplier_id, bank_name, branch_name, account_name, account_number, account_type, currency_code, swift_code, country_id)
SELECT sp.id,
       ELT(1+(sp.rn%5),'National Bank of Malawi','Standard Bank','FDH Bank','NBS Bank','First Capital Bank'),
       ELT(1+(sp.rn%4),'Capital City','Blantyre Main','Mzuzu','Zomba'),
       CONCAT('DEMO — Supplier ', LPAD(sp.rn,3,'0'), ' Ltd'),
       CONCAT('01', LPAD((sp.rn*123457) % 100000000, 8, '0')),
       ELT(1+(sp.rn%2),'Current','Savings'),
       'MWK',
       CONCAT('DEMOMW', LPAD(sp.rn,2,'0')),
       (SELECT id FROM es_country ORDER BY id LIMIT 1)
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp;

INSERT INTO es_supplier_certificate (supplier_id, certificate_no, issue_date, expire_date)
SELECT sp.id,
       CONCAT('PPDA/CERT/2026/', LPAD(sp.rn, 4, '0')),
       DATE_SUB(CURDATE(), INTERVAL (sp.rn % 12) MONTH),
       DATE_ADD(CURDATE(), INTERVAL (12 - (sp.rn % 12)) MONTH)
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp;

INSERT INTO es_supplier_attachment (supplier_id, kind, file_path, original_name, legacy_id)
SELECT sp.id,
       ELT(1+(sp.rn%4),'business_register','mra_certificate','tax_clearance','bank_proof'),
       CONCAT('files/demo/supplier', sp.rn, '.pdf'),
       CONCAT('supplier-', sp.rn, '-document.pdf'),
       CONCAT('demo-att:', sp.rn)
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp;

-- ---------------------------------------------------------------------------
--  Role-holder pools (temp tables) so demo rows are assigned to real users
--  who actually hold the matching e-Services role.
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS _officers;
CREATE TEMPORARY TABLE _officers AS
SELECT user_id, (ROW_NUMBER() OVER (ORDER BY user_id)) - 1 AS rk, COUNT(*) OVER () AS c
FROM es_user_role WHERE role = 'officer';

SET @registry_uid  := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role = 'registry'),   (SELECT MIN(id) FROM users));
SET @allocator_uid := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role IN ('dg','allocator')), (SELECT MIN(id) FROM users));
SET @supervisor_uid := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role = 'supervisor'), (SELECT MIN(id) FROM users));
SET @director_uid  := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role = 'director'),   (SELECT MIN(id) FROM users));
SET @dg_uid        := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role = 'dg'),         (SELECT MIN(id) FROM users));
SET @board_uid     := COALESCE((SELECT MIN(user_id) FROM es_user_role WHERE role = 'board'),      (SELECT MIN(id) FROM users));

-- ---------------------------------------------------------------------------
--  Bid registry — 25 submissions, spread cleanly across the pipeline:
--    n 1-5   pending_registry     (no analysis)
--    n 6-8   returned_to_pde      (no analysis)
--    n 9-13  pending_allocation   (checked, not allocated)
--    n 14-18 assigned             (allocated to an officer, analysis NOT started) -> "To start"
--    n 19-22 in_analysis          (analysis in progress)
--    n 23-25 completed            (analysis approved / rejected)
-- ---------------------------------------------------------------------------
INSERT INTO es_bid_registry
 (serial_no, pde_id, tender_number, subject, submission_details, procurement_method_id,
  review_type_id, channel, importance, submission_signed, date_of_signing, ref_code_pde,
  in_procurement_plan, accompanied_documents, origin, received_by,
  registry_checked_by, registry_checked_at, registry_comment,
  allocated_by, allocated_at, assigned_officer_id, status, ts_create)
SELECT
  CONCAT('DEMO/BA/2026/', LPAD(q.n, 4, '0')),
  pde.id,
  CONCAT('T/DEMO/2026/', LPAD(q.n, 3, '0')),
  ELT(1 + (q.n % 8),
      'Supply and delivery of ICT equipment',
      'Construction of a district office block',
      'Consultancy services for a financial management audit',
      'Provision of security services for 24 months',
      'Supply of essential pharmaceuticals and medical sundries',
      'Rehabilitation of 12 km of feeder roads',
      'Supply and installation of solar power systems',
      'Framework agreement for office stationery'),
  CONCAT('Submission received from the procuring entity for post-procurement review. Item ', q.n, '.'),
  pm.id, rt.id,
  ELT(1 + (q.n % 3), 'physical','email','portal'),
  ELT(1 + (q.n % 3), 'normal','high','urgent'),
  q.n % 2,
  DATE_SUB(CURDATE(), INTERVAL (q.n) DAY),
  CONCAT('PDE/REF/', LPAD(q.n, 4, '0')),
  q.n % 2,
  ELT(1 + (q.n % 3),
      'ipdc_minutes,evaluation_report,advert',
      'evaluation_report,contract,financial_proposal,technical_proposal',
      'ipdc_minutes,evaluation_report,original_bidding_document,advert,contract,general_submission'),
  ELT(1 + (q.n % 2), 'registry','pde'),
  @registry_uid,
  IF(q.n >= 6, @registry_uid, NULL),
  IF(q.n >= 6, DATE_SUB(NOW(), INTERVAL (q.n + 14 + (q.n % 5)) DAY), NULL),   -- checked 2-6 days after receipt
  IF(q.n BETWEEN 6 AND 8, 'Please attach the signed evaluation report and re-submit.', NULL),
  IF(q.n >= 14, @allocator_uid, NULL),
  IF(q.n >= 14, DATE_SUB(NOW(), INTERVAL (q.n - 2) DAY), NULL),
  IF(q.n >= 14, o.user_id, NULL),
  CASE
    WHEN q.n <= 5  THEN 'pending_registry'
    WHEN q.n <= 8  THEN 'returned_to_pde'
    WHEN q.n <= 13 THEN 'pending_allocation'
    WHEN q.n <= 18 THEN 'assigned'
    WHEN q.n <= 22 THEN 'in_analysis'
    ELSE 'completed'
  END,
  DATE_SUB(NOW(), INTERVAL (q.n + 20) DAY)
FROM _seq q
JOIN (SELECT id,(ROW_NUMBER() OVER (ORDER BY id))-1 rk, COUNT(*) OVER () c FROM es_pde)                pde ON pde.rk = q.n % pde.c
JOIN (SELECT id,(ROW_NUMBER() OVER (ORDER BY id))-1 rk, COUNT(*) OVER () c FROM es_procurement_method) pm  ON pm.rk  = q.n % pm.c
JOIN (SELECT id,(ROW_NUMBER() OVER (ORDER BY id))-1 rk, COUNT(*) OVER () c FROM es_review_type)        rt  ON rt.rk  = q.n % rt.c
JOIN _officers o ON o.rk = q.n % o.c
WHERE q.n BETWEEN 1 AND 25;

-- ---------------------------------------------------------------------------
--  Bid analysis — only for submissions that reached analysis (status
--  in_analysis / completed). officer_id follows the submission's allocation.
-- ---------------------------------------------------------------------------
INSERT INTO es_bid_analysis
 (registry_id, officer_id, approved_proc_plan, approved_workplan, preferences_applied,
  publication_done, date_of_publication, bid_opening_minutes_signed, bid_opening_minutes_date,
  evaluation_report_signed, evaluation_report_date, bid_validity_days, ipdc_minutes_signed,
  comment_on_ipdc, all_bids_enclosed, original_bid_enclosed, bids_still_valid,
  stage, current_owner_id, officer_feedback, dg_feedback, final_outcome, submitted_at, decided_at, ts_create)
SELECT
  r.id,
  r.assigned_officer_id,
  1,1, r.rn % 2, 1,
  DATE_SUB(CURDATE(), INTERVAL (40 + r.rn) DAY),
  1, DATE_SUB(CURDATE(), INTERVAL (25 + r.rn) DAY),
  1, DATE_SUB(CURDATE(), INTERVAL (12 + r.rn) DAY),
  90, r.rn % 2,
  'IPDC minutes reviewed; no material issues noted.',
  1,1,1,
  r.stage,
  CASE r.stage
    WHEN 'draft'             THEN r.assigned_officer_id
    WHEN 'returned'          THEN r.assigned_officer_id
    WHEN 'supervisor_review' THEN @supervisor_uid
    WHEN 'director_review'   THEN @director_uid
    WHEN 'dg_review'         THEN @dg_uid
    WHEN 'board_review'      THEN @board_uid
    ELSE r.assigned_officer_id
  END,
  CONCAT('Officer review notes for submission ', r.rn, '. The evaluation broadly complied with the Act.'),
  IF(r.stage IN ('approved','rejected'), 'Decision issued by the Director General.', ''),
  CASE r.stage WHEN 'approved' THEN 'no_objection' WHEN 'rejected' THEN 'objection' ELSE 'pending' END,
  IF(r.stage = 'draft', NULL, DATE_SUB(NOW(), INTERVAL (r.rn + 6) DAY)),
  IF(r.stage IN ('approved','rejected'), DATE_SUB(NOW(), INTERVAL r.rn DAY), NULL),
  DATE_SUB(NOW(), INTERVAL (r.rn + 14) DAY)
FROM (
  SELECT id, assigned_officer_id, rn,
         CASE
           WHEN status = 'completed' THEN ELT(1 + ((srank - 1) % 2), 'approved', 'rejected')
           ELSE ELT(1 + ((srank - 1) % 4),
                    'supervisor_review', 'director_review', 'dg_review', 'board_review')
         END AS stage
  FROM (
    SELECT id, assigned_officer_id, status,
           ROW_NUMBER() OVER (ORDER BY id) AS rn,
           ROW_NUMBER() OVER (PARTITION BY status ORDER BY id) AS srank
    FROM es_bid_registry
    WHERE serial_no LIKE 'DEMO/%' AND status IN ('in_analysis','completed')
  ) x
) r;

-- helper: numbered demo analyses
--   (SELECT id, rn FROM es_bid_analysis a JOIN es_bid_registry r ... serial LIKE 'DEMO/%')

INSERT INTO es_bid_lot (analysis_id, lot_number, name)
SELECT a.id, 1, CONCAT('Lot 1 — ', ELT(1+(a.rn%4),'Goods','Works','Services','Consultancy'))
FROM (SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a
UNION ALL
SELECT a.id, 2, 'Lot 2 — Ancillary items'
FROM (SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a
WHERE a.rn % 2 = 0;

INSERT INTO es_bid_bidder
 (analysis_id, lot_id, supplier_id, bidder_number, name, currency_code, read_out_price, bid_status, remarks, sort_order)
SELECT a.id,
       (SELECT l.id FROM es_bid_lot l WHERE l.analysis_id = a.id ORDER BY l.lot_number LIMIT 1),
       sp.id,
       b.k,
       CONCAT('DEMO — Supplier ', LPAD(((a.rn + b.k) % 25) + 1, 3, '0'), ' Ltd'),
       'MWK',
       ROUND(50000000 + (a.rn * 1000000) + (b.k * 3750000), 2),
       ELT(1 + ((a.rn + b.k) % 4),'responsive','responsive','non_responsive','rejected'),
       CONCAT('Read-out at bid opening, position ', b.k),
       b.k
FROM (SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a
CROSS JOIN (SELECT 1 k UNION ALL SELECT 2 UNION ALL SELECT 3) b
JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY id) rn FROM es_supplier WHERE name LIKE 'DEMO —%') sp
  ON sp.rn = ((a.rn + b.k) % 25) + 1;

INSERT INTO es_bid_financial_eval
 (analysis_id, bidder_id, currency_code, bid_price, computation_errors, corrected_bid_price,
  exchange_rate, price_after_preferences, rank_position, preferred_bidder, is_msme, reasons_errors)
SELECT bd.analysis_id, bd.id, 'MWK', bd.read_out_price,
       ROUND(bd.read_out_price * 0.01, 2),
       ROUND(bd.read_out_price * 1.01, 2),
       1.000000,
       ROUND(bd.read_out_price * 0.985, 2),
       bd.bidder_number,
       IF(bd.bidder_number = 1, 1, 0),
       bd.bidder_number % 2,
       'Minor arithmetic correction applied to the unit-rate schedule.'
FROM es_bid_bidder bd
JOIN es_bid_analysis a ON a.id = bd.analysis_id
JOIN es_bid_registry r ON r.id = a.registry_id
WHERE r.serial_no LIKE 'DEMO/%';

-- preliminary evaluation: bidders 1 & 2 pass, 3 fails
INSERT INTO es_bid_prelim_eval (analysis_id, bidder_id, lot_id, result, remarks)
SELECT bd.analysis_id, bd.id, bd.lot_id,
       IF(bd.bidder_number <= 2, 'pass', 'fail'),
       IF(bd.bidder_number <= 2, 'Meets the preliminary requirements.', 'Missing a mandatory document.')
FROM es_bid_bidder bd
JOIN es_bid_analysis a ON a.id = bd.analysis_id
JOIN es_bid_registry r ON r.id = a.registry_id
WHERE r.serial_no LIKE 'DEMO/%';

-- technical evaluation: only the bidders that passed preliminary
INSERT INTO es_bid_technical_eval (analysis_id, bidder_id, lot_id, result, score, currency_code, bid_price, remarks)
SELECT bd.analysis_id, bd.id, bd.lot_id,
       IF(bd.bidder_number = 1, 'pass', 'pending'),
       ROUND(60 + (bd.bidder_number * 7) % 40, 2),
       'MWK', bd.read_out_price,
       'Technical scoring against the stated evaluation criteria.'
FROM es_bid_bidder bd
JOIN es_bid_prelim_eval pe ON pe.bidder_id = bd.id AND pe.result = 'pass'
JOIN es_bid_analysis a ON a.id = bd.analysis_id
JOIN es_bid_registry r ON r.id = a.registry_id
WHERE r.serial_no LIKE 'DEMO/%';

-- post-qualification: the preferred bidder
INSERT INTO es_bid_postqual_eval (analysis_id, bidder_id, lot_id, result, remarks)
SELECT fe.analysis_id, fe.bidder_id, bd.lot_id, 'pass', 'Post-qualification requirements confirmed.'
FROM es_bid_financial_eval fe
JOIN es_bid_bidder bd ON bd.id = fe.bidder_id
JOIN es_bid_analysis a ON a.id = fe.analysis_id
JOIN es_bid_registry r ON r.id = a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND fe.preferred_bidder = 1;

INSERT INTO es_bid_list_item (analysis_id, kind, item_number, body)
SELECT a.id, k.kind, k.k,
       CONCAT(REPLACE(k.kind,'_',' '), ' #', k.k, ' for submission ', a.rn,
              ': the committee noted this point during the review.')
FROM (SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a
CROSS JOIN (
  SELECT 'technical_criteria' kind, 1 k UNION ALL
  SELECT 'assessment_criteria', 1 UNION ALL
  SELECT 'evaluation_observation', 1 UNION ALL
  SELECT 'recommendation', 1
) k;

-- routing: an "assign" hop for every analysis, plus a "return" hop carrying the
-- comment the officer sees, for analyses sitting at stage = returned.
INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments, ts_create)
SELECT a.id, @allocator_uid, a.officer_id, 'assign', 'draft', 'draft',
       'Allocated for analysis.', DATE_SUB(NOW(), INTERVAL (a.rn + 12) DAY)
FROM (SELECT a.id, a.officer_id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a
UNION ALL
SELECT a.id, @supervisor_uid, a.officer_id, 'return', 'supervisor_review', 'returned',
       'Please reconcile the read-out prices with the corrected bid schedule and re-submit.',
       DATE_SUB(NOW(), INTERVAL (a.rn + 3) DAY)
FROM (SELECT a.id, a.officer_id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
      WHERE r.serial_no LIKE 'DEMO/%' AND a.stage = 'returned') a;

-- forward hops: every analysis past 'draft' gets the trail it walked through
-- (officer -> supervisor -> director -> DG -> board / decision), so the tracking
-- board and the "forwarded by me" worklists have real data.
-- timestamps hang off submitted_at so the tracking board shows believable
-- turnaround (officer ~ receipt->submit, then ~2 days per review stage).
INSERT INTO es_bid_routing (analysis_id, from_user_id, to_user_id, action, from_stage, to_stage, comments, ts_create)
SELECT a.id, a.officer_id, NULL, 'submit', 'draft', 'supervisor_review',
       'Submitted for supervisory review.', a.submitted_at
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.submitted_at IS NOT NULL
  AND a.stage IN ('returned','supervisor_review','director_review','dg_review','board_review','approved','rejected')
UNION ALL
SELECT a.id, @supervisor_uid, NULL, 'submit', 'supervisor_review', 'director_review',
       'Endorsed by the supervisor.', DATE_ADD(a.submitted_at, INTERVAL 2 DAY)
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.submitted_at IS NOT NULL
  AND a.stage IN ('director_review','dg_review','board_review','approved','rejected')
UNION ALL
SELECT a.id, @director_uid, NULL, 'submit', 'director_review', 'dg_review',
       'Endorsed by the director.', DATE_ADD(a.submitted_at, INTERVAL 4 DAY)
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.submitted_at IS NOT NULL
  AND a.stage IN ('dg_review','board_review','approved','rejected')
UNION ALL
SELECT a.id, @dg_uid, NULL, 'submit', 'dg_review', 'board_review',
       'Referred to the PPDA Board for determination.', DATE_ADD(a.submitted_at, INTERVAL 6 DAY)
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.submitted_at IS NOT NULL AND a.stage = 'board_review'
UNION ALL
SELECT a.id, @dg_uid, NULL, IF(a.stage='approved','approve','reject'), 'dg_review', a.stage,
       IF(a.stage='approved','No-objection granted by the Director General.',
                             'No-objection withheld by the Director General.'),
       DATE_ADD(a.submitted_at, INTERVAL 6 DAY)
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.submitted_at IS NOT NULL AND a.stage IN ('approved','rejected');

INSERT INTO es_bid_message (analysis_id, user_id, body)
SELECT a.id, a.officer_id,
       CONCAT('Discussion note ', a.rn, ': please confirm the corrected bid prices before forwarding.')
FROM (SELECT a.id, a.officer_id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a;

-- one early board vote on each board_review analysis (from the lowest-id board member)
INSERT INTO es_bid_board_vote (analysis_id, user_id, decision, comment)
SELECT a.id, (SELECT MIN(user_id) FROM es_user_role WHERE role='board'), 'approve',
       'Recommendation is sound; I support granting the no-objection.'
FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id
WHERE r.serial_no LIKE 'DEMO/%' AND a.stage = 'board_review';

INSERT INTO es_bid_attachment (registry_id, analysis_id, kind, file_path, original_name, uploaded_by)
SELECT r.id, NULL, 'submission_pack',
       CONCAT('files/demo/submission-', r.rn, '.pdf'),
       CONCAT('submission-', r.rn, '.pdf'),
       (SELECT id FROM users ORDER BY id LIMIT 1)
FROM (SELECT id, ROW_NUMBER() OVER (ORDER BY id) rn FROM es_bid_registry WHERE serial_no LIKE 'DEMO/%') r;

INSERT INTO es_pde_response (analysis_id, body, published, published_at, created_by)
SELECT a.id,
       CONCAT('Following our review of the above submission, the Authority has no objection to the ',
              'proposed award, subject to the arithmetic corrections noted. The procuring entity may proceed ',
              'in line with the Public Procurement and Disposal of Assets Act. (Demo letter ', a.rn, ')'),
       IF(a.rn % 2 = 0, 1, 0),
       IF(a.rn % 2 = 0, DATE_SUB(CURDATE(), INTERVAL a.rn DAY), NULL),
       (SELECT id FROM users ORDER BY id LIMIT 1)
FROM (SELECT a.id, ROW_NUMBER() OVER (ORDER BY a.id) rn
      FROM es_bid_analysis a JOIN es_bid_registry r ON r.id=a.registry_id WHERE r.serial_no LIKE 'DEMO/%') a;

-- ---------------------------------------------------------------------------
--  es_import_log — 25 demo rows
-- ---------------------------------------------------------------------------
INSERT INTO es_import_log (phase, dry_run, inserted, updated, skipped, warnings, ran_by, ts_create)
SELECT CONCAT('demo-', ELT(1 + (q.n % 3),'lookups','suppliers','bids')),
       q.n % 2,
       q.n * 10, q.n, q.n % 4,
       IF(q.n % 4 = 0, CONCAT('demo warning sample for run ', q.n), NULL),
       (SELECT id FROM users ORDER BY id LIMIT 1),
       DATE_SUB(NOW(), INTERVAL q.n HOUR)
FROM _seq q WHERE q.n BETWEEN 1 AND 25;

DROP TEMPORARY TABLE IF EXISTS _seq;

-- ---------------------------------------------------------------------------
--  Row-count check
-- ---------------------------------------------------------------------------
SELECT 'es_country' t, COUNT(*) n FROM es_country
UNION ALL SELECT 'es_currency', COUNT(*) FROM es_currency
UNION ALL SELECT 'es_procurement_method', COUNT(*) FROM es_procurement_method
UNION ALL SELECT 'es_review_type', COUNT(*) FROM es_review_type
UNION ALL SELECT 'es_pde', COUNT(*) FROM es_pde
UNION ALL SELECT 'es_category', COUNT(*) FROM es_category
UNION ALL SELECT 'es_user_role', COUNT(*) FROM es_user_role
UNION ALL SELECT 'es_legacy_officer_map', COUNT(*) FROM es_legacy_officer_map
UNION ALL SELECT 'es_supplier', COUNT(*) FROM es_supplier
UNION ALL SELECT 'es_supplier_shareholder', COUNT(*) FROM es_supplier_shareholder
UNION ALL SELECT 'es_supplier_category', COUNT(*) FROM es_supplier_category
UNION ALL SELECT 'es_supplier_bank', COUNT(*) FROM es_supplier_bank
UNION ALL SELECT 'es_supplier_certificate', COUNT(*) FROM es_supplier_certificate
UNION ALL SELECT 'es_supplier_attachment', COUNT(*) FROM es_supplier_attachment
UNION ALL SELECT 'es_bid_registry', COUNT(*) FROM es_bid_registry
UNION ALL SELECT 'es_bid_analysis', COUNT(*) FROM es_bid_analysis
UNION ALL SELECT 'es_bid_lot', COUNT(*) FROM es_bid_lot
UNION ALL SELECT 'es_bid_bidder', COUNT(*) FROM es_bid_bidder
UNION ALL SELECT 'es_bid_financial_eval', COUNT(*) FROM es_bid_financial_eval
UNION ALL SELECT 'es_bid_technical_eval', COUNT(*) FROM es_bid_technical_eval
UNION ALL SELECT 'es_bid_list_item', COUNT(*) FROM es_bid_list_item
UNION ALL SELECT 'es_bid_routing', COUNT(*) FROM es_bid_routing
UNION ALL SELECT 'es_bid_message', COUNT(*) FROM es_bid_message
UNION ALL SELECT 'es_bid_attachment', COUNT(*) FROM es_bid_attachment
UNION ALL SELECT 'es_pde_response', COUNT(*) FROM es_pde_response
UNION ALL SELECT 'es_import_log', COUNT(*) FROM es_import_log;
