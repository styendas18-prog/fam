-- MAXXfam schema (MySQL 8.0+ / 8.4)
-- All DATETIME values are stored in UTC. Presentation converts to Asia/Kolkata.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status, created_at),
  KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','support') NOT NULL DEFAULT 'admin',
  totp_secret TEXT NULL,
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email),
  KEY idx_admins_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(160) NOT NULL,
  merchant_code VARCHAR(24) NOT NULL,
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  website VARCHAR(255) NULL,
  support_email VARCHAR(190) NULL,
  support_phone VARCHAR(30) NULL,
  country CHAR(2) NOT NULL DEFAULT 'IN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchants_user (user_id),
  UNIQUE KEY uq_merchants_code (merchant_code),
  KEY idx_merchants_status (status, created_at),
  CONSTRAINT fk_merchants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  legal_name VARCHAR(190) NULL,
  business_type VARCHAR(60) NULL,
  address_line1 VARCHAR(190) NULL,
  address_line2 VARCHAR(190) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  gstin VARCHAR(20) NULL,
  pan VARCHAR(20) NULL,
  kyc_status ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted',
  kyc_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profiles_merchant (merchant_id),
  KEY idx_profiles_kyc (kyc_status),
  CONSTRAINT fk_profiles_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  last4 CHAR(4) NOT NULL,
  scopes JSON NOT NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  rotated_from BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_keys_hash (key_hash),
  KEY idx_api_keys_merchant (merchant_id, status),
  CONSTRAINT fk_api_keys_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
  CONSTRAINT fk_api_keys_rotated FOREIGN KEY (rotated_from) REFERENCES api_keys (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_providers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  driver VARCHAR(80) NOT NULL,
  base_url VARCHAR(255) NOT NULL,
  docs_url VARCHAR(255) NULL,
  description TEXT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  connection_status ENUM('not_configured','testing','connected','failed','disabled') NOT NULL DEFAULT 'not_configured',
  last_test_at DATETIME NULL,
  last_success_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_providers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NULL,
  encrypted_credentials MEDIUMTEXT NOT NULL,
  key_last4 CHAR(4) NOT NULL,
  connection_status ENUM('not_configured','testing','connected','failed','disabled') NOT NULL DEFAULT 'not_configured',
  last_test_at DATETIME NULL,
  last_test_message VARCHAR(500) NULL,
  last_success_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_merchant (provider_id, merchant_id),
  KEY idx_provider_credentials_status (connection_status),
  CONSTRAINT fk_provider_credentials_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id) ON DELETE CASCADE,
  CONSTRAINT fk_provider_credentials_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(64) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  amount DECIMAL(14,2) NULL,
  min_amount DECIMAL(14,2) NOT NULL DEFAULT 1.00,
  max_amount DECIMAL(14,2) NOT NULL DEFAULT 100000.00,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  customer_reference VARCHAR(120) NULL,
  success_redirect_url VARCHAR(500) NULL,
  expires_at DATETIME NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_links_slug (slug),
  KEY idx_payment_links_merchant (merchant_id, status, created_at),
  KEY idx_payment_links_deleted (deleted_at),
  CONSTRAINT fk_payment_links_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  checkout_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  link_id BIGINT UNSIGNED NULL,
  provider_order_id VARCHAR(64) NULL,
  customer_name VARCHAR(120) NULL,
  customer_email VARCHAR(190) NULL,
  customer_phone VARCHAR(30) NULL,
  customer_reference VARCHAR(120) NULL,
  description VARCHAR(500) NULL,
  amount DECIMAL(14,2) NOT NULL,
  payable_amount DECIMAL(14,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('pending','success','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
  qr_url VARCHAR(1000) NULL,
  checkout_url VARCHAR(1000) NULL,
  provider_checkout_url VARCHAR(1000) NULL,
  upi_intent VARCHAR(1500) NULL,
  upi_id VARCHAR(190) NULL,
  success_redirect_url VARCHAR(500) NULL,
  webhook_url VARCHAR(500) NULL,
  expires_at DATETIME NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  last_checked_at DATETIME NULL,
  verification_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  failure_code VARCHAR(60) NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_public_id (public_id),
  UNIQUE KEY uq_orders_checkout_token (checkout_token),
  UNIQUE KEY uq_orders_provider_order (provider_id, provider_order_id),
  KEY idx_orders_merchant (merchant_id, created_at),
  KEY idx_orders_status_expiry (status, expires_at),
  KEY idx_orders_link (link_id),
  KEY idx_orders_provider_status (provider_id, status),
  CONSTRAINT fk_orders_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_link FOREIGN KEY (link_id) REFERENCES payment_links (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  provider_transaction_id VARCHAR(100) NULL,
  utr VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  sender_name VARCHAR(190) NULL,
  status ENUM('success') NOT NULL DEFAULT 'success',
  refund_status ENUM('none','manual_pending','manual_completed') NOT NULL DEFAULT 'none',
  refund_note VARCHAR(500) NULL,
  verified_at DATETIME NOT NULL,
  verification_source VARCHAR(32) NOT NULL DEFAULT 'webhook',
  provider_payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transactions_order (order_id),
  UNIQUE KEY uq_transactions_utr (utr),
  UNIQUE KEY uq_transactions_provider_txn (provider_id, provider_transaction_id),
  KEY idx_transactions_merchant (merchant_id, verified_at),
  CONSTRAINT fk_transactions_order FOREIGN KEY (order_id) REFERENCES payment_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_transactions_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_transactions_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhooks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  url_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  secret_encrypted TEXT NOT NULL,
  events JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_delivery_at DATETIME NULL,
  last_status VARCHAR(20) NULL,
  failure_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhooks_url (merchant_id, url_hash),
  KEY idx_webhooks_merchant (merchant_id, is_active),
  CONSTRAINT fk_webhooks_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  webhook_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  event VARCHAR(60) NOT NULL,
  payload JSON NOT NULL,
  signature CHAR(64) NOT NULL,
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  response_code INT NULL,
  response_body TEXT NULL,
  next_attempt_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_deliveries_webhook (webhook_id, created_at),
  KEY idx_deliveries_due (status, next_attempt_at),
  KEY idx_deliveries_merchant (merchant_id, created_at),
  CONSTRAINT fk_deliveries_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks (id) ON DELETE CASCADE,
  CONSTRAINT fk_deliveries_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
  CONSTRAINT fk_deliveries_order FOREIGN KEY (order_id) REFERENCES payment_orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NULL,
  api_key_id BIGINT UNSIGNED NULL,
  request_id VARCHAR(40) NOT NULL,
  method VARCHAR(10) NOT NULL,
  path VARCHAR(255) NOT NULL,
  status_code INT NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  request_body MEDIUMTEXT NULL,
  response_body MEDIUMTEXT NULL,
  latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_logs_request (request_id),
  KEY idx_api_logs_merchant (merchant_id, created_at),
  KEY idx_api_logs_key (api_key_id, created_at),
  CONSTRAINT fk_api_logs_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE SET NULL,
  CONSTRAINT fk_api_logs_key FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(500) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(40) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  customer_name VARCHAR(120) NULL,
  customer_email VARCHAR(190) NULL,
  issued_at DATETIME NOT NULL,
  pdf_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (invoice_number),
  UNIQUE KEY uq_invoices_order (order_id),
  KEY idx_invoices_merchant (merchant_id, issued_at),
  CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES payment_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number VARCHAR(24) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  merchant_id BIGINT UNSIGNED NULL,
  guest_name VARCHAR(120) NULL,
  guest_email VARCHAR(190) NULL,
  subject VARCHAR(190) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'general',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  last_reply_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tickets_number (ticket_number),
  KEY idx_tickets_user (user_id, created_at),
  KEY idx_tickets_status (status, updated_at),
  CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  sender_type ENUM('user','admin','guest') NOT NULL,
  sender_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_ticket (ticket_id, created_at),
  CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(60) NULL,
  target_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_activity_admin (admin_id, created_at),
  KEY idx_admin_activity_target (target_type, target_id),
  CONSTRAINT fk_admin_activity_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  admin_id BIGINT UNSIGNED NULL,
  event VARCHAR(100) NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_security_event (event, created_at),
  KEY idx_security_severity (severity, created_at),
  CONSTRAINT fk_security_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_security_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  setting_value MEDIUMTEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_type ENUM('user','admin') NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  last_activity_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_login_sessions_hash (session_hash),
  KEY idx_login_sessions_user (user_type, user_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_type ENUM('user','admin') NOT NULL DEFAULT 'user',
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_hash (token_hash),
  KEY idx_password_resets_email (email, user_type, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('all','active_merchants') NOT NULL DEFAULT 'all',
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcements_published (is_published, published_at),
  CONSTRAINT fk_announcements_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id BIGINT UNSIGNED NOT NULL,
  provider_order_id VARCHAR(64) NOT NULL,
  event VARCHAR(60) NOT NULL,
  utr VARCHAR(32) NULL,
  payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status ENUM('received','processed','ignored','retry','failed_signature') NOT NULL DEFAULT 'received',
  note VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_webhook_hash (provider_id, payload_hash),
  KEY idx_provider_webhook_order (provider_order_id, received_at),
  CONSTRAINT fk_provider_webhook_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  to_email VARCHAR(190) NOT NULL,
  to_name VARCHAR(120) NULL,
  subject VARCHAR(255) NOT NULL,
  html_body MEDIUMTEXT NOT NULL,
  text_body MEDIUMTEXT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_due (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  user_type ENUM('user','admin') NOT NULL,
  ip VARCHAR(45) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_login_attempts_email (email, user_type, created_at),
  KEY idx_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (bucket),
  KEY idx_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
