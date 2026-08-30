USE ksspm;

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'domain_billing_periods'
      AND COLUMN_NAME = 'customer_renewal_date'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE domain_billing_periods ADD COLUMN customer_renewal_date DATE NULL AFTER coverage_end_date',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'domain_billing_periods'
      AND COLUMN_NAME = 'is_registrar_carryover'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE domain_billing_periods ADD COLUMN is_registrar_carryover TINYINT(1) NOT NULL DEFAULT 0 AFTER is_historical_purchase',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

UPDATE domain_billing_periods
SET customer_renewal_date = coverage_end_date
WHERE purchase_status = 'Purchased'
  AND customer_renewal_date IS NULL;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'domain_billing_periods'
      AND INDEX_NAME = 'idx_domain_customer_renewal'
);

SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX idx_domain_customer_renewal ON domain_billing_periods(customer_renewal_date, purchase_status)',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
