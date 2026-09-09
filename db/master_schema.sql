-- ============================================================================
--  PPDA — MASTER SCHEMA + SEED
-- ============================================================================
--  One MariaDB/MySQL database (`ppda_u375699389_ememo`) holding both apps:
--
--    * e-Memo / correspondence     — un-prefixed tables (memos, users, ...)
--    * e-Services (bid analysis)   — `es_`-prefixed tables
--
--  This file is STRUCTURE + REFERENCE/SEED DATA ONLY. It carries no
--  production rows and no personal data. The `users` table gets a single
--  placeholder administrator so you can log in on a fresh install.
--
--  Rebuilt from:
--    ppda_u375699389_ememo (1).sql   (phpMyAdmin dump — structure only)
--    app/eservice/sql/01_schema.sql  (e-Services structure)
--    app/eservice/sql/02_seed.sql    (e-Services lookup data)
--    app/eservice/sql/05_rbac.sql    (e-Services roles & permissions data)
--
--  Safe to re-run: every table is dropped first. Load the whole file at once:
--    mysql -u <user> -p <database> < db/master_schema.sql
-- ============================================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;


-- ##########################################################################
-- #  PART 1 — e-MEMO CORE  (un-prefixed tables)
-- ##########################################################################

-- --- drop -------------------------------------------------------------------
DROP TABLE IF EXISTS `approval_stages`;
DROP TABLE IF EXISTS `attachments`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `direct_memos`;
DROP TABLE IF EXISTS `direct_memo_attachments`;
DROP TABLE IF EXISTS `direct_memo_recipients`;
DROP TABLE IF EXISTS `external_letters`;
DROP TABLE IF EXISTS `external_letter_actions`;
DROP TABLE IF EXISTS `external_letter_comments`;
DROP TABLE IF EXISTS `external_letter_delegation`;
DROP TABLE IF EXISTS `external_letter_files`;
DROP TABLE IF EXISTS `external_letter_trail`;
DROP TABLE IF EXISTS `forms`;
DROP TABLE IF EXISTS `form_events`;
DROP TABLE IF EXISTS `form_files`;
DROP TABLE IF EXISTS `form_instances`;
DROP TABLE IF EXISTS `form_signers`;
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `inbox_views`;
DROP TABLE IF EXISTS `instructions`;
DROP TABLE IF EXISTS `in_out_tray`;
DROP TABLE IF EXISTS `item_views`;
DROP TABLE IF EXISTS `memos`;
DROP TABLE IF EXISTS `memo_approvals`;
DROP TABLE IF EXISTS `memo_clarifications`;
DROP TABLE IF EXISTS `memo_comments`;
DROP TABLE IF EXISTS `memo_endorsements`;
DROP TABLE IF EXISTS `memo_escalations`;
DROP TABLE IF EXISTS `memo_instructions`;
DROP TABLE IF EXISTS `memo_movements`;
DROP TABLE IF EXISTS `memo_outgoing_letters`;
DROP TABLE IF EXISTS `memo_outgoing_letter_recipients`;
DROP TABLE IF EXISTS `memo_sequences`;
DROP TABLE IF EXISTS `memo_signatures`;
DROP TABLE IF EXISTS `memo_through_endorsements`;
DROP TABLE IF EXISTS `memo_through_recipients`;
DROP TABLE IF EXISTS `memo_trail`;
DROP TABLE IF EXISTS `memo_views`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `sections`;
DROP TABLE IF EXISTS `signatures`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_devices`;

-- --- table structure -----------------------------------------------------

CREATE TABLE `approval_stages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL,
  `is_final_stage` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `direct_memos` (
  `id` int(11) NOT NULL,
  `memo_id` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `memo_type` varchar(50) NOT NULL DEFAULT 'CIRCULAR',
  `status` enum('Draft','Submitted') DEFAULT 'Submitted',
  `signature_data` longtext DEFAULT NULL,
  `send_all` tinyint(1) DEFAULT 0,
  `reference_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `direct_memo_attachments` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `direct_memo_recipients` (
  `id` int(11) NOT NULL,
  `direct_memo_id` int(11) NOT NULL,
  `recipient_type` enum('user','group') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_role` enum('To','CC') NOT NULL DEFAULT 'To'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letters` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `received_from` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `received_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('pending','assigned','delegation_pending','in_progress','report_submitted','closed') DEFAULT 'pending',
  `current_assignee` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letter_actions` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `action_taken` text NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `report_file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letter_comments` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `response_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letter_delegation` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `delegated_by` int(11) NOT NULL,
  `delegated_to` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected','redelegated') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letter_files` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `external_letter_trail` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `instruction` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','completed','escalated') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `forms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `schema_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schema_json`)),
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings_json`)),
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `form_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `instance_id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(60) NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`event_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `form_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `instance_id` bigint(20) UNSIGNED NOT NULL,
  `field_key` varchar(120) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size` bigint(20) NOT NULL,
  `storage_path` varchar(512) NOT NULL,
  `sha256` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `form_instances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `form_id` bigint(20) UNSIGNED NOT NULL,
  `instance_code` varchar(60) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `responses_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`responses_json`)),
  `status` enum('draft','in_progress','awaiting_sign','completed','rejected','void') DEFAULT 'draft',
  `workflow_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`workflow_json`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `form_signers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `instance_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(120) DEFAULT NULL,
  `ordering` int(11) NOT NULL DEFAULT 1,
  `must_sign` tinyint(1) NOT NULL DEFAULT 1,
  `min_approvals_in_step` int(11) DEFAULT 1,
  `status` enum('pending','signed','rejected','skipped') DEFAULT 'pending',
  `signed_at` datetime DEFAULT NULL,
  `signature_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`signature_payload`)),
  `signature_hash` char(64) DEFAULT NULL,
  `signature_ts` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `added_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inbox_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `memo_type` enum('Workflow','Direct') NOT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `instructions` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `assignee_user_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `in_out_tray` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_type` enum('memo','external_letter') NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `action_taken` varchar(255) DEFAULT NULL,
  `status` enum('new','assigned','delegated','awaiting_approval','completed') NOT NULL DEFAULT 'new',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `item_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_type` enum('letter','memo') NOT NULL,
  `item_id` int(11) NOT NULL,
  `last_viewed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `memos` (
  `id` int(11) NOT NULL,
  `memo_id` varchar(50) NOT NULL,
  `communication_type` enum('Loose Minute','Memorandum') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `section_id` int(11) NOT NULL,
  `originator_id` int(11) NOT NULL,
  `status` enum('Draft','Submitted','Under Review','Endorsed','Approved','Rejected','Returned','Finalized') DEFAULT 'Draft',
  `current_stage_id` int(11) DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `date` date DEFAULT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_approvals` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `decision` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `comment` text DEFAULT NULL,
  `discussed` tinyint(1) DEFAULT 0,
  `decision_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_clarifications` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `clarification_comment` text DEFAULT NULL,
  `response_comment` text DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Responded') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_comments` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stage` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_endorsements` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `endorser_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `endorsed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_escalations` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `escalated_by_user_id` int(11) NOT NULL,
  `escalated_to_user_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_instructions` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `instruction` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `memo_movements` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `comments` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_outgoing_letters` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `letter_date` date NOT NULL DEFAULT curdate(),
  `letter_ref_no` varchar(50) NOT NULL,
  `letter_subject` varchar(255) NOT NULL,
  `letter_content` text NOT NULL,
  `letter_status` enum('Draft','Submitted','Approved') NOT NULL DEFAULT 'Draft',
  `drafted_by` int(11) NOT NULL,
  `signed_by` int(11) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `letter_signature` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_outgoing_letter_recipients` (
  `id` int(11) NOT NULL,
  `outgoing_letter_id` int(11) NOT NULL,
  `position` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_sequences` (
  `id` int(11) NOT NULL,
  `department` varchar(50) NOT NULL,
  `year` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_signatures` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('Originator','Endorser','Reviewer','Approver') NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `signature_path` varchar(255) NOT NULL,
  `signed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_through_endorsements` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `endorsed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_through_recipients` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `endorsement_status` enum('Pending','Endorsed','Rejected','Escalated') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_trail` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('Draft','Submitted','Endorsed','Approved','Rejected','Returned','Escalated','Finalized','Acknowledged') DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `action_time` timestamp NULL DEFAULT current_timestamp(),
  `comment` text DEFAULT NULL,
  `action_type` enum('Create','Save','Submit','Endorse','Approve','Reject','Return','Escalate','Finalize','Acknowledge') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `memo_views` (
  `id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` enum('letter_instruction','letter_delegation','letter_comment','memo_instruction','memo_clarification','memo_forward','memo_endorse','memo_approve','memo_reject','memo_return') NOT NULL,
  `object_id` int(11) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `history_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_code` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `signatures` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('originator','endorser','approver','admin') NOT NULL DEFAULT 'originator',
  `section_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_controlling_officer` tinyint(1) NOT NULL DEFAULT 0,
  `password_changed` tinyint(1) DEFAULT 0,
  `is_secretary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fcm_token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- keys, indexes & foreign keys --------------------------------------

ALTER TABLE `approval_stages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `direct_memos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_id` (`memo_id`),
  ADD KEY `from_user_id` (`from_user_id`);

ALTER TABLE `direct_memo_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`);

ALTER TABLE `direct_memo_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `direct_memo_id` (`direct_memo_id`);

ALTER TABLE `external_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`);

ALTER TABLE `external_letter_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`);

ALTER TABLE `external_letter_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`);

ALTER TABLE `external_letter_delegation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`);

ALTER TABLE `external_letter_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`);

ALTER TABLE `external_letter_trail`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `form_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instance_id` (`instance_id`);

ALTER TABLE `form_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instance_id` (`instance_id`);

ALTER TABLE `form_instances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `instance_code` (`instance_code`),
  ADD KEY `form_id` (`form_id`);

ALTER TABLE `form_signers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instance_id` (`instance_id`);

ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `group_members`
  ADD PRIMARY KEY (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `inbox_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`memo_id`,`memo_type`);

ALTER TABLE `instructions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`),
  ADD KEY `author_user_id` (`author_user_id`),
  ADD KEY `assignee_user_id` (`assignee_user_id`);

ALTER TABLE `in_out_tray`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_type` (`item_type`),
  ADD KEY `direction` (`direction`),
  ADD KEY `status` (`status`);

ALTER TABLE `item_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_item` (`user_id`,`item_type`,`item_id`),
  ADD KEY `idx_item` (`item_type`,`item_id`);

ALTER TABLE `memos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_id` (`memo_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `originator_id` (`originator_id`),
  ADD KEY `current_stage_id` (`current_stage_id`),
  ADD KEY `fk_to_user` (`to_user_id`);

ALTER TABLE `memo_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_id` (`memo_id`,`stage_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `approver_id` (`approver_id`);

ALTER TABLE `memo_clarifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `memo_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `memo_endorsements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_id` (`memo_id`,`endorser_id`),
  ADD KEY `endorser_id` (`endorser_id`);

ALTER TABLE `memo_escalations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `escalated_by_user_id` (`escalated_by_user_id`),
  ADD KEY `escalated_to_user_id` (`escalated_to_user_id`);

ALTER TABLE `memo_instructions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `recipient_id` (`recipient_id`);

ALTER TABLE `memo_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`);

ALTER TABLE `memo_outgoing_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_mol_memo_id` (`memo_id`),
  ADD KEY `fk_mol_drafted_by` (`drafted_by`),
  ADD KEY `fk_mol_signed_by` (`signed_by`);

ALTER TABLE `memo_outgoing_letter_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_molr_outgoing_letter_id` (`outgoing_letter_id`);

ALTER TABLE `memo_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department` (`department`,`year`);

ALTER TABLE `memo_signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `stage_id` (`stage_id`);

ALTER TABLE `memo_through_endorsements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `memo_through_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `memo_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `stage_id` (`stage_id`);

ALTER TABLE `memo_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`,`department_id`),
  ADD KEY `department_id` (`department_id`);

ALTER TABLE `signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_department` (`department_id`),
  ADD KEY `fk_section` (`section_id`),
  ADD KEY `fk_user_position` (`position_id`);

ALTER TABLE `user_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_token` (`user_id`,`fcm_token`),
  ADD KEY `fcm_token` (`fcm_token`);

ALTER TABLE `approval_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `direct_memos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `direct_memo_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `direct_memo_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letter_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letter_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letter_delegation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letter_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `external_letter_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `forms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `form_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `form_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `form_instances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `form_signers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `inbox_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `instructions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `in_out_tray`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `item_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_clarifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_endorsements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_escalations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_instructions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_outgoing_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_outgoing_letter_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_through_endorsements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_through_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `memo_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `signatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;

ALTER TABLE `direct_memos`
  ADD CONSTRAINT `direct_memos_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`);

ALTER TABLE `direct_memo_attachments`
  ADD CONSTRAINT `direct_memo_attachments_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `direct_memos` (`id`) ON DELETE CASCADE;

ALTER TABLE `direct_memo_recipients`
  ADD CONSTRAINT `direct_memo_recipients_ibfk_1` FOREIGN KEY (`direct_memo_id`) REFERENCES `direct_memos` (`id`);

ALTER TABLE `external_letter_actions`
  ADD CONSTRAINT `external_letter_actions_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `external_letters` (`id`);

ALTER TABLE `external_letter_comments`
  ADD CONSTRAINT `external_letter_comments_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `external_letters` (`id`);

ALTER TABLE `external_letter_delegation`
  ADD CONSTRAINT `external_letter_delegation_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `external_letters` (`id`);

ALTER TABLE `external_letter_files`
  ADD CONSTRAINT `external_letter_files_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `external_letters` (`id`) ON DELETE CASCADE;

ALTER TABLE `form_events`
  ADD CONSTRAINT `form_events_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `form_instances` (`id`);

ALTER TABLE `form_files`
  ADD CONSTRAINT `form_files_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `form_instances` (`id`);

ALTER TABLE `form_instances`
  ADD CONSTRAINT `form_instances_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`);

ALTER TABLE `form_signers`
  ADD CONSTRAINT `form_signers_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `form_instances` (`id`);

ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `instructions`
  ADD CONSTRAINT `instructions_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `external_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `instructions_ibfk_2` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `instructions_ibfk_3` FOREIGN KEY (`assignee_user_id`) REFERENCES `users` (`id`);

ALTER TABLE `memos`
  ADD CONSTRAINT `fk_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `memos_ibfk_2` FOREIGN KEY (`originator_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memos_ibfk_3` FOREIGN KEY (`current_stage_id`) REFERENCES `approval_stages` (`id`);

ALTER TABLE `memo_approvals`
  ADD CONSTRAINT `memo_approvals_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`),
  ADD CONSTRAINT `memo_approvals_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `approval_stages` (`id`),
  ADD CONSTRAINT `memo_approvals_ibfk_3` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`);

ALTER TABLE `memo_clarifications`
  ADD CONSTRAINT `memo_clarifications_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_clarifications_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_clarifications_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `memo_comments`
  ADD CONSTRAINT `memo_comments_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `memo_endorsements`
  ADD CONSTRAINT `memo_endorsements_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`),
  ADD CONSTRAINT `memo_endorsements_ibfk_2` FOREIGN KEY (`endorser_id`) REFERENCES `users` (`id`);

ALTER TABLE `memo_escalations`
  ADD CONSTRAINT `memo_escalations_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_escalations_ibfk_2` FOREIGN KEY (`escalated_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memo_escalations_ibfk_3` FOREIGN KEY (`escalated_to_user_id`) REFERENCES `users` (`id`);

ALTER TABLE `memo_instructions`
  ADD CONSTRAINT `fk_instr_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_mi_memo` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `memo_movements`
  ADD CONSTRAINT `memo_movements_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_movements_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memo_movements_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`);

ALTER TABLE `memo_outgoing_letters`
  ADD CONSTRAINT `fk_mol_drafted_by` FOREIGN KEY (`drafted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_mol_memo` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mol_signed_by` FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`);

ALTER TABLE `memo_outgoing_letter_recipients`
  ADD CONSTRAINT `fk_molr_outgoing_letter` FOREIGN KEY (`outgoing_letter_id`) REFERENCES `memo_outgoing_letters` (`id`) ON DELETE CASCADE;

ALTER TABLE `memo_signatures`
  ADD CONSTRAINT `memo_signatures_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`),
  ADD CONSTRAINT `memo_signatures_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memo_signatures_ibfk_3` FOREIGN KEY (`stage_id`) REFERENCES `approval_stages` (`id`);

ALTER TABLE `memo_through_endorsements`
  ADD CONSTRAINT `memo_through_endorsements_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_through_endorsements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `memo_through_recipients`
  ADD CONSTRAINT `memo_through_recipients_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_through_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `memo_trail`
  ADD CONSTRAINT `memo_trail_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`),
  ADD CONSTRAINT `memo_trail_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memo_trail_ibfk_3` FOREIGN KEY (`stage_id`) REFERENCES `approval_stages` (`id`);

ALTER TABLE `memo_views`
  ADD CONSTRAINT `memo_views_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `direct_memos` (`id`),
  ADD CONSTRAINT `memo_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

ALTER TABLE `signatures`
  ADD CONSTRAINT `signatures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`);

ALTER TABLE `user_devices`
  ADD CONSTRAINT `user_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --- reference data ---------------------------------------------------

INSERT INTO `approval_stages` (`id`, `name`, `level`, `is_final_stage`) VALUES
(1, 'Draft', 1, 0),
(2, 'Director Approval', 2, 0),
(3, 'DG Approval', 3, 1);

INSERT INTO `departments` (`id`, `name`) VALUES
(11, 'Monitoring and Compliance'),
(12, 'Internal Audit'),
(13, 'Finance'),
(14, 'e-Procurement'),
(15, 'Human Resource and Administration'),
(16, 'Procurement'),
(17, 'Director General''s Office'),
(18, 'Information Communication Technology'),
(19, 'Regulatory Review'),
(20, 'Capacity Development and Reforms'),
(21, 'Planning and Research'),
(22, 'Regulatory Review and Monitoring Compliance'),
(23, 'Public Relations and Communication'),
(24, 'Information Communication Technology Directorate'),
(25, 'Finance Directorate'),
(26, 'Capacity Development and Reforms Directorate');

INSERT INTO `positions` (`id`, `position_code`, `name`, `short_name`, `created_at`) VALUES
(26, 7, 'Monitoring Compliance Enforcement Officer', 'MCEO', '2025-06-30 14:18:41'),
(27, 7, 'Internal Auditor', 'IA', '2025-06-30 14:18:41'),
(28, 5, 'Chief Revenue Account', 'CRA', '2025-06-30 14:18:41'),
(29, 6, 'Senior Programmer', 'SP', '2025-06-30 14:18:41'),
(30, 10, 'Cleric Officer', 'CO', '2025-06-30 14:18:41'),
(31, 5, 'Chief Procurement Officer', 'CPO', '2025-06-30 14:18:41'),
(32, 6, 'Senior Monitoring Compliance Enforcement Officer', 'SMCEO', '2025-06-30 14:18:41'),
(33, 1, 'Director General', 'DG', '2025-06-30 14:18:41'),
(34, 5, 'Chief Human Resource Management Officer', 'CHRMO', '2025-06-30 14:18:41'),
(35, 5, 'Chief Information Technology Communication Officer', 'CICTO', '2025-06-30 14:18:41'),
(36, 5, 'Chief Regulatory Review Officer', 'CRRO', '2025-06-30 14:18:41'),
(37, 5, 'Chief Capacity Development and Reforms Officer', 'CCDRO', '2025-06-30 14:18:41'),
(38, 4, 'Regulatory and Review Manager', 'RRM', '2025-06-30 14:18:41'),
(39, 4, 'Finance Manager', 'FM', '2025-06-30 14:18:41'),
(40, 7, 'Monitoring and Evaluation Officer', 'MEO', '2025-06-30 14:18:41'),
(41, 4, 'Information Communication Technology Manager', 'ICTM', '2025-06-30 14:18:41'),
(42, 6, 'Senior Administrative Assistant', 'SAA', '2025-06-30 14:18:41'),
(43, 3, 'Director of Regulatory Review and Monitoring Compliance', 'DRRM', '2025-06-30 14:18:41'),
(44, 6, 'Senior Regulatory Officer', 'SRO', '2025-06-30 14:18:41'),
(45, 7, 'Graphic Designer', 'GD', '2025-06-30 14:18:41'),
(46, 4, 'Capacity Development and Reforms Manager', 'CDRM', '2025-06-30 14:18:41'),
(47, 4, 'Internal Audit and Risk Manager', 'IARM', '2025-06-30 14:18:41'),
(48, 9, 'Senior Records Assistant', 'SRA', '2025-06-30 14:18:41'),
(49, 6, 'Senior Information Communication Technology Officer', 'SICTO', '2025-06-30 14:18:41'),
(50, 9, 'Assistant Human Resource Officer', 'AHRO', '2025-06-30 14:18:41'),
(51, 7, 'Procurement Officer', 'PO', '2025-06-30 14:18:41'),
(52, 5, 'Chief Monitoring Compliance Enforcement Officer', 'CMCEO', '2025-06-30 14:18:41'),
(53, 7, 'Administrative Assistant', 'AA', '2025-06-30 14:18:41'),
(54, 4, 'Public Relations and Communications Manager', 'PRCM', '2025-06-30 14:18:41'),
(55, 7, 'Administration Officer', 'AO', '2025-06-30 14:18:41'),
(56, 4, 'Monitoring and Compliance Manager', 'MCM', '2025-06-30 14:18:41'),
(57, 4, 'Planning and Research Manager', 'PRM', '2025-06-30 14:18:41'),
(58, 9, 'Data Entry Clerk', 'DEC', '2025-06-30 14:18:41'),
(59, 3, 'Director of Information Communication and Technology', 'DICT', '2025-06-30 14:18:41'),
(60, 11, 'PBX Operator', 'PBXO', '2025-06-30 14:18:41'),
(61, 6, 'Senior Administration Officer', 'SAO', '2025-06-30 14:18:41'),
(62, 7, 'Revenue Accountant', 'RA', '2025-06-30 14:18:41'),
(63, 3, 'Director of Finance', 'DOF', '2025-06-30 14:18:41'),
(64, 7, 'Financial Accountant', 'FA', '2025-06-30 14:18:41'),
(65, 3, 'Director of Capacity Development and Reforms', 'DCDR', '2025-06-30 14:18:41'),
(66, 7, 'Information Technology Auditor', 'ITA', '2025-06-30 14:18:41'),
(67, 8, 'Stores Supervisor', 'SS', '2025-06-30 14:18:41'),
(68, 4, 'Human Resources and Administration Manager', 'HRAM', '2025-06-30 14:18:41'),
(69, 4, 'e-Procurement Manager', 'ePM', '2025-06-30 14:18:41'),
(70, 12, 'Helpdesk Intern', 'HI', '2025-06-30 14:18:41'),
(71, 8, 'Assistant Financial Accountant', 'AFA', '2025-09-11 09:27:06'),
(72, 1, 'Acting Director General', 'ADG', '2026-02-05 16:08:37');

INSERT INTO `sections` (`id`, `name`, `department_id`) VALUES
(22, 'Capacity Development and Reforms Department', 20),
(28, 'Capacity Development and Reforms Directorate', 26),
(18, 'Director General\'s Office', 17),
(15, 'e-Procurement Department', 14),
(14, 'Finance Department', 13),
(27, 'Finance Directorate', 25),
(19, 'Human Resource and Administration Department', 15),
(20, 'Information Communication Technology Department', 18),
(26, 'Information Communication Technology Directorate', 24),
(13, 'Internal Audit Department', 12),
(12, 'Monitoring and Compliance Department', 11),
(23, 'Planning and Research Department', 21),
(17, 'Procurement Division', 16),
(25, 'Public Relations and Communication Department', 23),
(16, 'Registry Division', 15),
(24, 'Regulatory Review and Monitoring Compliance Directorate', 22),
(21, 'Regulatory Review Department', 19);

-- --- placeholder administrator (change the password immediately) ------
-- login: admin / admin123   (bcrypt hash below)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `section_id`,
  `department_id`, `position_id`, `email`, `phone_number`, `active`,
  `is_controlling_officer`, `password_changed`, `is_secretary`) VALUES
(1, 'admin', '$2y$12$RKi2tKXFu4iOH/ckZGIL6eQmCXcy.EOfKKj/qGrEBcscjyZv0STme', 'System Administrator',
 'admin', NULL, NULL, NULL, 'admin@example.com', NULL, 1, 1, 0, 1);


-- ##########################################################################
-- #  PART 2 — e-SERVICES  (es_-prefixed tables)
-- ##########################################################################

-- --- structure (from app/eservice/sql/01_schema.sql) -------------------

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

-- --- lookup data (from app/eservice/sql/02_seed.sql) ------------------

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

-- --- roles & permissions (data from app/eservice/sql/05_rbac.sql) ----

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

UPDATE es_permission SET grp = 'Reviews & approvals' WHERE perm_key LIKE 'analysis.review_%';

UPDATE es_permission SET grp = 'PDE responses'       WHERE perm_key LIKE 'response.%';

UPDATE es_permission SET label = 'Draft & save the PDE response letter' WHERE perm_key = 'response.write';

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

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

