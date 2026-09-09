-- ============================================================================
--  PPDA e-Services — roles & permissions (RBAC)
--
--  Turns the fixed role list into editable data:
--    es_role            — role definitions (system roles can't be deleted)
--    es_permission      — the permission catalogue
--    es_role_permission — which permissions each role bundles
--    es_user_role.role  — widened to VARCHAR + FK to es_role
--
--  Idempotent: INSERT IGNORE + ADD COLUMN IF NOT EXISTS.
--    mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo < app/eservice/sql/05_rbac.sql
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS es_role (
  role_key     VARCHAR(40) NOT NULL,
  label        VARCHAR(80) NOT NULL,
  description  VARCHAR(255) DEFAULT NULL,
  is_system    TINYINT(1) NOT NULL DEFAULT 0,
  sort         SMALLINT NOT NULL DEFAULT 100,
  ts_create    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS es_permission (
  perm_key     VARCHAR(60) NOT NULL,
  label        VARCHAR(120) NOT NULL,
  grp          VARCHAR(40) NOT NULL DEFAULT 'General',
  sort         SMALLINT NOT NULL DEFAULT 100,
  PRIMARY KEY (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS es_role_permission (
  role_key VARCHAR(40) NOT NULL,
  perm_key VARCHAR(60) NOT NULL,
  PRIMARY KEY (role_key, perm_key),
  KEY ix_rp_perm (perm_key),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_key) REFERENCES es_role(role_key) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (perm_key) REFERENCES es_permission(perm_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- widen es_user_role.role  (ENUM -> VARCHAR, then FK)
ALTER TABLE es_user_role MODIFY role VARCHAR(40) NOT NULL;

-- ---------------------------------------------------------------------------
--  System roles
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_role (role_key, label, description, is_system, sort) VALUES
 ('registry',   'Registry',          'PPDA registry office — logs submissions and checks completeness.', 1, 10),
 ('pde',        'PDE representative', 'Files and tracks submissions for one procuring & disposing entity.', 1, 20),
 ('allocator',  'Allocator',         'Allocates checked submissions to technical officers.', 1, 30),
 ('officer',    'Technical officer', 'Runs the bid analysis and evaluation.', 1, 40),
 ('supervisor', 'Supervisor',        'Reviews and endorses the officer''s analysis.', 1, 50),
 ('director',   'Director',           'Directorate-level review of an analysis.', 1, 60),
 ('dg',         'Director General',   'Final decision on an analysis; can allocate.', 1, 70),
 ('board',      'Analysis Board',     'Board-level review.', 1, 80),
 ('admin',      'Administrator',      'Full access to every e-Services function.', 1, 999);

-- ---------------------------------------------------------------------------
--  Permission catalogue
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_permission (perm_key, label, grp, sort) VALUES
 -- Submissions ------------------------------------------------------------
 ('submission.view_all',        'View all submissions',                       'Submissions', 10),
 ('submission.create',          'Log a submission (on behalf of a PDE)',      'Submissions', 20),
 ('submission.registry_check',  'Registry completeness check',                'Submissions', 30),
 ('submission.allocate',        'Allocate a submission to an officer',        'Submissions', 40),
 ('submission.reassign',        'Reassign an allocated submission',           'Submissions', 45),
 ('submission.reprioritise',    'Change a submission''s priority',            'Submissions', 46),
 ('submission.withdraw',        'Return a submission to the PDE / close it',  'Submissions', 50),
 ('submission.track',           'Open the submission-tracking board',         'Submissions', 60),
 -- Analysis --------------------------------------------------------------
 ('analysis.start',             'Start an analysis',                          'Analysis', 10),
 ('analysis.submit',            'Submit an analysis for review',              'Analysis', 15),
 ('analysis.evaluate',          'Enter evaluation data',                      'Analysis', 20),
 ('analysis.export',            'Export the analysis report (PDF)',           'Analysis', 25),
 ('analysis.comment',           'Comment on an analysis',                     'Analysis', 30),
 ('analysis.archive',           'Archive / restore a review',                 'Analysis', 35),
 -- Reviews & approvals -------------------------------------------------
 ('analysis.review_supervisor', 'Supervisor: endorse to director / return',  'Reviews & approvals', 10),
 ('analysis.review_director',   'Director: endorse to DG / return',           'Reviews & approvals', 20),
 ('analysis.review_dg',         'DG: grant / withhold no-objection, send to board', 'Reviews & approvals', 30),
 ('analysis.review_board',      'Board: cast a determination vote',           'Reviews & approvals', 40),
 -- PDE responses ----------------------------------------------------
 ('response.write',             'Draft & save the PDE response letter',       'PDE responses', 10),
 ('response.publish',           'Publish / re-publish a response letter',     'PDE responses', 20),
 ('response.dispatch',          'Mark a response emailed / sent to the PDE',  'PDE responses', 30),
 -- Suppliers -------------------------------------------------------
 ('supplier.view',              'View the supplier register',                 'Suppliers', 10),
 -- Administration -----------------------------------------------
 ('refdata.manage',             'Manage reference data',                      'Administration', 10),
 ('users.manage',               'Assign user roles',                          'Administration', 20),
 ('rbac.manage',                'Manage roles & permissions',                 'Administration', 30),
 ('import.run',                 'Run legacy data imports',                    'Administration', 40);

-- keep older single-grouped rows tidy if this file is re-run on an old install
UPDATE es_permission SET grp = 'Reviews & approvals' WHERE perm_key LIKE 'analysis.review_%';
UPDATE es_permission SET grp = 'PDE responses'       WHERE perm_key LIKE 'response.%';
UPDATE es_permission SET label = 'Draft & save the PDE response letter' WHERE perm_key = 'response.write';

-- ---------------------------------------------------------------------------
--  Default role -> permission bundles  (only added if the role has NONE yet)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO es_role_permission (role_key, perm_key) VALUES
 ('registry','submission.view_all'),('registry','submission.create'),('registry','submission.registry_check'),
 ('registry','submission.withdraw'),('registry','submission.track'),('registry','response.dispatch'),
 ('allocator','submission.view_all'),('allocator','submission.allocate'),('allocator','submission.reassign'),
 ('allocator','submission.track'),
 ('officer','submission.view_all'),('officer','submission.track'),
 ('officer','analysis.start'),('officer','analysis.submit'),('officer','analysis.evaluate'),
 ('officer','analysis.comment'),('officer','analysis.export'),('officer','analysis.archive'),
 ('officer','response.write'),('officer','supplier.view'),
 ('supervisor','submission.view_all'),('supervisor','submission.track'),
 ('supervisor','analysis.review_supervisor'),('supervisor','analysis.comment'),('supervisor','analysis.export'),
 ('supervisor','analysis.archive'),('supervisor','response.write'),('supervisor','supplier.view'),
 ('director','submission.view_all'),('director','submission.track'),
 ('director','analysis.review_director'),('director','analysis.comment'),('director','analysis.export'),
 ('director','analysis.archive'),('director','response.write'),('director','supplier.view'),
 ('dg','submission.view_all'),('dg','submission.track'),('dg','submission.allocate'),('dg','submission.reassign'),
 ('dg','submission.reprioritise'),('dg','submission.withdraw'),
 ('dg','analysis.review_dg'),('dg','analysis.comment'),('dg','analysis.export'),('dg','analysis.archive'),
 ('dg','response.write'),('dg','response.publish'),('dg','response.dispatch'),('dg','supplier.view'),
 ('board','submission.view_all'),('board','submission.track'),
 ('board','analysis.review_board'),('board','analysis.comment'),('board','analysis.export'),
 ('board','response.write'),('board','supplier.view'),
 ('admin','refdata.manage'),('admin','users.manage'),('admin','rbac.manage'),('admin','import.run'),
 ('admin','submission.view_all'),('admin','supplier.view');

-- add the FK to es_user_role.role now that every referenced key exists
-- (skip silently if it is already there)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'es_user_role' AND CONSTRAINT_NAME = 'fk_ur_role_key');
SET @sql := IF(@fk = 0,
  'ALTER TABLE es_user_role ADD CONSTRAINT fk_ur_role_key FOREIGN KEY (role) REFERENCES es_role(role_key) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET FOREIGN_KEY_CHECKS = 1;
