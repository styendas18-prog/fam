-- Gmail IMAP / FamPay email verification.
-- Credentials are encrypted with AES-256-GCM by the application; only the last
-- four characters of the Google App Password are stored in plaintext.

CREATE TABLE IF NOT EXISTS email_verification_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  gmail_address VARCHAR(190) NOT NULL,
  app_password_encrypted MEDIUMTEXT NOT NULL,
  app_password_last4 CHAR(4) NOT NULL,
  upi_id VARCHAR(190) NOT NULL,
  imap_host VARCHAR(190) NOT NULL DEFAULT 'imap.gmail.com',
  imap_port SMALLINT UNSIGNED NOT NULL DEFAULT 993,
  imap_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
  folder VARCHAR(100) NOT NULL DEFAULT 'INBOX',
  sender_whitelist VARCHAR(500) NOT NULL DEFAULT 'famapp.in,fampay.in',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  connection_status ENUM('not_configured','testing','connected','failed','disabled') NOT NULL DEFAULT 'not_configured',
  last_test_at DATETIME NULL,
  last_test_message VARCHAR(500) NULL,
  last_checked_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  total_messages INT UNSIGNED NOT NULL DEFAULT 0,
  total_verified INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_verification_merchant (merchant_id),
  KEY idx_email_verification_status (is_enabled, connection_status),
  CONSTRAINT fk_email_verification_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  message_uid BIGINT UNSIGNED NOT NULL,
  message_id_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  from_address VARCHAR(190) NULL,
  subject VARCHAR(255) NULL,
  received_at DATETIME NULL,
  body_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  parsed_amount DECIMAL(14,2) NULL,
  parsed_utr VARCHAR(32) NULL,
  parsed_upi_id VARCHAR(190) NULL,
  matched_order_id BIGINT UNSIGNED NULL,
  status ENUM('received','ignored','unparsed','unmatched','ambiguous','verified','duplicate','failed') NOT NULL DEFAULT 'received',
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_message_uid (account_id, message_uid),
  KEY idx_email_message_merchant (merchant_id, created_at),
  KEY idx_email_message_utr (parsed_utr),
  KEY idx_email_message_status (status, created_at),
  CONSTRAINT fk_email_message_account FOREIGN KEY (account_id) REFERENCES email_verification_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_email_message_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
  CONSTRAINT fk_email_message_order FOREIGN KEY (matched_order_id) REFERENCES payment_orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Force a password change at first administrator sign-in. Some accounts are
-- provisioned by the operator with a temporary password and must rotate it.
SET @mf_has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'must_change_password'
);
SET @mf_sql := IF(
  @mf_has_col = 0,
  'ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_enabled',
  'DO 1'
);
PREPARE mf_stmt FROM @mf_sql;
EXECUTE mf_stmt;
DEALLOCATE PREPARE mf_stmt;
