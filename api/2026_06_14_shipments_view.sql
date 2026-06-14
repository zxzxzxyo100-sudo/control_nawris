-- ─────────────────────────────────────────────────────────────────────────────
-- Migration: create shipments_view
-- Adds driver_name (joined from drivers) to every shipment row.
-- The JS calls sbAll('shipments_view') — this view makes that legal and useful.
-- Run once on production DB.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE OR REPLACE VIEW `shipments_view` AS
SELECT
    s.id,
    s.tracking_code,
    s.customer_name,
    s.customer_phone,
    s.driver_id,
    COALESCE(d.name, '') AS driver_name,
    s.branch_name,
    s.responsible_branch,
    s.region_name,
    s.status,
    s.delay_days,
    s.upload_date,
    s.api_source,
    s.external_id,
    s.created_at,
    s.updated_at
FROM `shipments` s
LEFT JOIN `drivers` d ON d.id = s.driver_id;
