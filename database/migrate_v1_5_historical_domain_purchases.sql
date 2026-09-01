SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'domain_billing_periods'
      AND COLUMN_NAME = 'is_historical_purchase'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE domain_billing_periods ADD COLUMN is_historical_purchase TINYINT(1) NOT NULL DEFAULT 0 AFTER actual_registrar_cost',
    'SELECT 1'
);

PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
