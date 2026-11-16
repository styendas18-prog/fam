-- Safe core seed. No users, admins, transactions, or fabricated statistics.
INSERT INTO payment_providers (code, name, driver, base_url, docs_url, description, is_enabled, connection_status)
VALUES (
  'famgateway',
  'FamGateway UPI',
  'FamGatewayProvider',
  'https://famgateway.in',
  'https://famgateway.in/docs.php',
  'Peer-to-peer UPI order creation, QR, hosted checkout and HMAC webhooks. Requires an authorized FamGateway merchant API key. Funds settle directly to the merchant UPI account. Refund APIs are not offered by the provider.',
  1,
  'not_configured'
)
ON DUPLICATE KEY UPDATE name = VALUES(name), driver = VALUES(driver), base_url = VALUES(base_url), docs_url = VALUES(docs_url), description = VALUES(description);

INSERT INTO system_settings (setting_key, setting_value, is_secret) VALUES
  ('site_name', 'MAXXfam', 0),
  ('site_tagline', 'Smart Payments. Real Verification. Complete Control.', 0),
  ('site_support_email', 'support@example.com', 0),
  ('maintenance_mode', '0', 0),
  ('maintenance_message', 'MAXXfam is undergoing scheduled maintenance. Please try again shortly.', 0),
  ('registration_enabled', '1', 0),
  ('default_currency', 'INR', 0),
  ('min_payment_amount', '1.00', 0),
  ('max_payment_amount', '100000.00', 0),
  ('default_link_expiry_days', '30', 0),
  ('payment_expiry_seconds', '300', 0),
  ('api_rate_limit_per_minute', '60', 0),
  ('provider_poll_interval_seconds', '4', 0),
  ('allow_platform_default_credentials', '0', 0),
  ('invoice_prefix', 'MF', 0),
  ('webhook_retry_schedule', '[60,300,900,3600,21600]', 0),
  ('terms_version', '1.0', 0),
  ('privacy_version', '1.0', 0),
  ('mail_from_name', 'MAXXfam', 0),
  ('mail_from_address', '', 0),
  ('smtp_host', '', 0),
  ('smtp_port', '587', 0),
  ('smtp_encryption', 'tls', 0),
  ('smtp_username', '', 0),
  ('smtp_password', '', 1),
  ('smtp_last_test_at', '', 0),
  ('smtp_last_test_result', '', 0)
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- Gmail IMAP / FamPay email verification. Accounts are configured per merchant;
-- these are the platform defaults and safety limits.
INSERT INTO system_settings (setting_key, setting_value, is_secret) VALUES
  ('email_verification_enabled', '1', 0),
  ('email_verification_default_senders', 'famapp.in,fampay.in', 0),
  ('email_verification_lookback_hours', '24', 0),
  ('email_verification_max_per_run', '20', 0),
  ('email_verification_require_sender_match', '1', 0),
  ('email_verification_require_upi_match', '1', 0)
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
