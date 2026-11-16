-- Gmail IMAP hardening: OAuth 2.0 credentials and UIDVALIDITY-aware scans.
-- Every statement is idempotent because the migrator replays all files.

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'auth_method');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN auth_method ENUM(''app_password'',''oauth2'') NOT NULL DEFAULT ''app_password'' AFTER sender_whitelist',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'oauth_refresh_token_encrypted');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN oauth_refresh_token_encrypted MEDIUMTEXT NULL AFTER auth_method',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'oauth_access_token_encrypted');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN oauth_access_token_encrypted MEDIUMTEXT NULL AFTER oauth_refresh_token_encrypted',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'oauth_token_expires_at');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN oauth_token_expires_at DATETIME NULL AFTER oauth_access_token_encrypted',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'uidvalidity');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN uidvalidity BIGINT UNSIGNED NULL AFTER folder',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'last_uid');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN last_uid BIGINT UNSIGNED NULL AFTER uidvalidity',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'search_days');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN search_days SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_uid',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verification_accounts' AND COLUMN_NAME = 'mark_read');
SET @sql := IF(@has = 0,
  'ALTER TABLE email_verification_accounts ADD COLUMN mark_read TINYINT(1) NOT NULL DEFAULT 1 AFTER search_days',
  'DO 1');
PREPARE mf_stmt FROM @sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;
