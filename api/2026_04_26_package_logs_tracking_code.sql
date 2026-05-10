-- Optional: store full API tracking code on package_logs (for alphanumeric codes).
-- Run once; if the column already exists, skip (MySQL will error — safe to ignore).
ALTER TABLE package_logs
  ADD COLUMN tracking_code VARCHAR(128) NULL AFTER package_id;
