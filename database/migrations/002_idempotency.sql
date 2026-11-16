-- API idempotency records. A client-supplied Idempotency-Key is stored as a
-- one-way hash together with a hash of the request payload. Replays return the
-- original response; the same key with a different payload is rejected.
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id BIGINT UNSIGNED NOT NULL,
  endpoint VARCHAR(80) NOT NULL,
  key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status_code INT NOT NULL DEFAULT 0,
  response_body MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_idempotency_key (merchant_id, endpoint, key_hash),
  KEY idx_idempotency_expiry (expires_at),
  CONSTRAINT fk_idempotency_merchant FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
