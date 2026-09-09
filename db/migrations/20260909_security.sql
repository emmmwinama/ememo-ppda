-- ============================================================================
--  Migration — platform security event log + login-hardening columns
--  Apply to an existing PPDA database (already has the ememo + es_ tables).
--  Idempotent-ish: uses IF NOT EXISTS where MariaDB 10.5 supports it.
--
--    mysql -u <user> -p <database> < db/migrations/20260909_security.sql
-- ============================================================================
SET NAMES utf8mb4;

-- 1) Security event log ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_event` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ts`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `app`        ENUM('hub','ememo','eservice','reports') NOT NULL DEFAULT 'hub',
  `event_type` VARCHAR(40) NOT NULL,
  `severity`   ENUM('info','notice','warning','critical') NOT NULL DEFAULT 'info',
  `user_id`    INT DEFAULT NULL,
  `username`   VARCHAR(100) DEFAULT NULL,
  `ip`         VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `route`      VARCHAR(190) DEFAULT NULL,
  `detail`     VARCHAR(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_se_ts` (`ts`),
  KEY `ix_se_type` (`event_type`),
  KEY `ix_se_user` (`user_id`),
  KEY `ix_se_sev` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Login-hardening columns on users ------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_login_at`  DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pwd_updated_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `failed_logins`  SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `locked_until`   DATETIME DEFAULT NULL;

-- Seed pwd_updated_at so the SOC "stale password" panel isn't 100% on day one.
UPDATE `users` SET `pwd_updated_at` = COALESCE(`pwd_updated_at`, `created_at`, NOW())
 WHERE `pwd_updated_at` IS NULL;
