-- Subscription plans, merchant assignment and plan requests.
-- A plan_id of 0 / NULL means no plan assigned and therefore no plan limits.

CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  billing_interval ENUM('monthly','yearly','one_time') NOT NULL DEFAULT 'monthly',
  transaction_limit INT UNSIGNED NOT NULL DEFAULT 0, -- 0 = unlimited
  api_limit INT UNSIGNED NOT NULL DEFAULT 0,
  link_limit INT UNSIGNED NOT NULL DEFAULT 0,
  features TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plans_slug (slug),
  KEY idx_plans_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  merchant_note VARCHAR(500) NULL,
  admin_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_plan_requests_merchant (merchant_id, status, requested_at),
  KEY idx_plan_requests_status (status, requested_at),
  CONSTRAINT fk_plan_requests_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_requests_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_requests_admin FOREIGN KEY (reviewed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add assignment columns to merchants (idempotent).
SET @mf_col_plan := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchants' AND COLUMN_NAME = 'plan_id'
);
SET @mf_sql_plan := IF(
  @mf_col_plan = 0,
  'ALTER TABLE merchants
     ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER status,
     ADD COLUMN plan_started_at DATETIME NULL AFTER plan_id,
     ADD COLUMN plan_expires_at DATETIME NULL AFTER plan_started_at,
     ADD COLUMN plan_status ENUM(''active'',''pending'',''expired'',''cancelled'') NOT NULL DEFAULT ''active'' AFTER plan_expires_at,
     ADD KEY idx_merchants_plan (plan_id),
     ADD CONSTRAINT fk_merchants_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL',
  'DO 1'
);
PREPARE mf_stmt1 FROM @mf_sql_plan;
EXECUTE mf_stmt1;
DEALLOCATE PREPARE mf_stmt1;
