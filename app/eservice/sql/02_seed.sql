-- ============================================================================
--  PPDA e-Services — baseline lookup data.
--  Fresh values (the legacy DB shipped no data). Adjust freely; the app
--  reads these at runtime, nothing is hard-coded to an id.
--  Re-runnable: uses INSERT ... ON DUPLICATE KEY UPDATE where it can.
-- ============================================================================
SET NAMES utf8mb4;

-- --- Currencies -------------------------------------------------------------
INSERT INTO es_currency (code, name, active) VALUES
  ('MWK','Malawi Kwacha',1),
  ('USD','US Dollar',1),
  ('EUR','Euro',1),
  ('GBP','Pound Sterling',1),
  ('ZAR','South African Rand',1),
  ('ZMW','Zambian Kwacha',1)
ON DUPLICATE KEY UPDATE name = VALUES(name), active = VALUES(active);

-- --- Countries (short starter list; extend as needed) ---------------------
INSERT INTO es_country (name, iso2, active) VALUES
  ('Malawi','MW',1),
  ('South Africa','ZA',1),
  ('Zambia','ZM',1),
  ('Tanzania','TZ',1),
  ('Mozambique','MZ',1),
  ('Zimbabwe','ZW',1),
  ('Kenya','KE',1),
  ('United Kingdom','GB',1),
  ('United States','US',1),
  ('India','IN',1),
  ('China','CN',1),
  ('Other','XX',1)
ON DUPLICATE KEY UPDATE iso2 = VALUES(iso2), active = VALUES(active);

-- --- Procurement methods (Malawi PPA 2017) --------------------------------
INSERT INTO es_procurement_method (name, code, active) VALUES
  ('Open National Bidding','ONB',1),
  ('Open International Bidding','OIB',1),
  ('Restricted Bidding','RB',1),
  ('Request for Quotations','RFQ',1),
  ('Request for Proposals','RFP',1),
  ('Single Source','SS',1),
  ('Direct Procurement','DP',1),
  ('Framework Agreement','FA',1),
  ('Community Participation','CP',1)
ON DUPLICATE KEY UPDATE code = VALUES(code), active = VALUES(active);

-- --- Review types --------------------------------------------------------
INSERT INTO es_review_type (name, active) VALUES
  ('Contract Award Review',1),
  ('Pre-Award Review',1),
  ('Post Review',1),
  ('Deviation Request',1),
  ('Extension of Bid Validity',1),
  ('Complaint / Review of Decision',1)
ON DUPLICATE KEY UPDATE active = VALUES(active);

-- --- Supplier category catalogue (illustrative; replace with the real list) ---
INSERT INTO es_category (type, name, code, fee, active) VALUES
  ('goods','Office Supplies & Stationery','G-OSS',50000.00,1),
  ('goods','ICT Equipment & Accessories','G-ICT',75000.00,1),
  ('goods','Motor Vehicles & Spare Parts','G-MVS',100000.00,1),
  ('goods','Furniture & Fittings','G-FUR',50000.00,1),
  ('goods','Medical Supplies & Equipment','G-MED',100000.00,1),
  ('goods','Building & Construction Materials','G-BCM',75000.00,1),
  ('goods','Foodstuffs & Catering Supplies','G-FCS',50000.00,1),
  ('services','Consultancy - Management & Finance','S-CMF',150000.00,1),
  ('services','Consultancy - Engineering','S-CEN',150000.00,1),
  ('services','ICT Services & Software','S-ICT',100000.00,1),
  ('services','Security Services','S-SEC',75000.00,1),
  ('services','Cleaning & Fumigation','S-CLN',50000.00,1),
  ('services','Transport & Logistics','S-TRL',75000.00,1),
  ('services','Printing & Publishing','S-PRP',50000.00,1),
  ('works','Building Construction','W-BLD',200000.00,1),
  ('works','Road & Bridge Works','W-RBW',200000.00,1),
  ('works','Electrical Installations','W-ELE',150000.00,1),
  ('works','Water & Sanitation Works','W-WSW',150000.00,1),
  ('works','Maintenance & Refurbishment','W-MRF',100000.00,1);

-- --- A couple of sample PDEs -------------------------------------------------
INSERT INTO es_pde (name, ref_code, email, address, active) VALUES
  ('Public Procurement and Disposal of Assets Authority','PPDA','info@ppda.mw','Lilongwe',1),
  ('Ministry of Health','MOH','procurement@health.gov.mw','Lilongwe',1),
  ('Ministry of Education','MOE','procurement@education.gov.mw','Lilongwe',1),
  ('Roads Authority','RA','procurement@ra.org.mw','Lilongwe',1);

-- NOTE: seed es_user_role manually once you know which ememo users act as
-- e-services officers / supervisors / directors / dg / board, e.g.:
--   INSERT INTO es_user_role (user_id, role) VALUES (12,'officer'),(5,'supervisor'),(3,'dg');
