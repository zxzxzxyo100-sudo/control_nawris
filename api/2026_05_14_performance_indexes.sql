-- ══════════════════════════════════════════════════════════════════════════
-- Performance Indexes Migration — logistics_crm
-- Safe to run multiple times (all use IF NOT EXISTS or CREATE INDEX ... IGNORE)
-- ══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. shipments
--    Queries in shipments_api.php use: api_source, external_id, status, id
--    Upsert in shipment_sync_service.php conflicts on: tracking_code
-- ──────────────────────────────────────────────────────────────────────────

-- Unique key — makes ON DUPLICATE KEY UPDATE instant
ALTER TABLE `shipments`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_shipments_tracking_code` (`tracking_code`);

-- cleanup_non_api: WHERE api_source IS NULL OR external_id IS NULL / status NOT IN (...)
ALTER TABLE `shipments`
    ADD INDEX IF NOT EXISTS `idx_shipments_api_source` (`api_source`),
    ADD INDEX IF NOT EXISTS `idx_shipments_external_id` (`external_id`),
    ADD INDEX IF NOT EXISTS `idx_shipments_status` (`status`);

-- Composite: prune_missing_from_api uses api_source + external_id + status together
ALTER TABLE `shipments`
    ADD INDEX IF NOT EXISTS `idx_shipments_api_prune` (`api_source`, `external_id`, `status`);

-- delay_days used in every KPI count
ALTER TABLE `shipments`
    ADD INDEX IF NOT EXISTS `idx_shipments_delay_days` (`delay_days`);

-- ──────────────────────────────────────────────────────────────────────────
-- 2. package_logs
--    get_logs.php: WHERE DATE(created_at) = X  →  full table scan (function on col)
--    Fix: use range query. Index covers the range scan.
-- ──────────────────────────────────────────────────────────────────────────

-- Existing index covers (package_id, created_at) — good.
-- Add plain created_at index for date-only queries without package_id.
ALTER TABLE `package_logs`
    ADD INDEX IF NOT EXISTS `idx_package_logs_created_at_plain` (`created_at`);

-- tracking_code column was added later — index it
ALTER TABLE `package_logs`
    ADD INDEX IF NOT EXISTS `idx_package_logs_tracking_code` (`tracking_code`);

-- ──────────────────────────────────────────────────────────────────────────
-- 3. shipment_sync_jobs
--    worker does: WHERE status='pending' AND available_at <= NOW() LIMIT 1 FOR UPDATE
--    Existing idx_jobs_status_available(status, available_at) covers this — verify.
-- ──────────────────────────────────────────────────────────────────────────

-- Add job_type to index for future routing queries
ALTER TABLE `shipment_sync_jobs`
    ADD INDEX IF NOT EXISTS `idx_jobs_status_available_type` (`status`, `available_at`);

-- ──────────────────────────────────────────────────────────────────────────
-- 4. shipment_kpi_events  (already indexed — add composite for dashboard queries)
-- ──────────────────────────────────────────────────────────────────────────

-- Reports: GROUP BY employee + month range + status
ALTER TABLE `shipment_kpi_events`
    ADD INDEX IF NOT EXISTS `idx_kpi_emp_month_status` (`employee_username`, `processed_at`, `new_status`);

-- ──────────────────────────────────────────────────────────────────────────
-- 5. shipments_cleanup_backup  (created dynamically — pre-create with indexes)
-- ──────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `shipments_cleanup_backup` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(120) NOT NULL,
    record_id BIGINT UNSIGNED NOT NULL,
    payload LONGTEXT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    deleted_by_employee_id BIGINT UNSIGNED NOT NULL,
    deleted_by_employee_name VARCHAR(255) NOT NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cleanup_table_record` (table_name, record_id),
    INDEX `idx_cleanup_deleted_at` (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
