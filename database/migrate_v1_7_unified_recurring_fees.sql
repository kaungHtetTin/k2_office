-- KSSPM Version 1.7: identify project-generated recurring fees.
-- Safe to run more than once.

USE ksspm;

SET @has_source_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_fees' AND COLUMN_NAME = 'source_type'
);
SET @sql = IF(
    @has_source_type = 0,
    'ALTER TABLE recurring_fees ADD COLUMN source_type ENUM(''Manual'', ''Project Generated'') NOT NULL DEFAULT ''Manual'' AFTER notes',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_source_key = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_fees' AND COLUMN_NAME = 'source_key'
);
SET @sql = IF(
    @has_source_key = 0,
    'ALTER TABLE recurring_fees ADD COLUMN source_key VARCHAR(50) NULL AFTER source_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_source_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_fees' AND INDEX_NAME = 'uq_recurring_project_source'
);
SET @sql = IF(
    @has_source_index = 0,
    'CREATE UNIQUE INDEX uq_recurring_project_source ON recurring_fees(project_id, source_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
