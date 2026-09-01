ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS is_historical TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_billing_period_id;

ALTER TABLE payments
    MODIFY COLUMN financial_account_id INT NULL;
