-- Cumulative staff KPI events (per employee, per tracking code, per month) — idempotent inserts.
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS staff_performance_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_username VARCHAR(120) NOT NULL,
  event_type ENUM('contacted', 'resolved') NOT NULL,
  tracking_code VARCHAR(128) NOT NULL,
  year_month CHAR(7) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_perf (employee_username, event_type, tracking_code, year_month),
  KEY idx_staff_perf_month (year_month, event_type),
  KEY idx_staff_perf_emp (employee_username, year_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
