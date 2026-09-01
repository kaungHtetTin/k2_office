CREATE TABLE IF NOT EXISTS domain_billing_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    domain_name VARCHAR(255),
    period_label VARCHAR(50),
    quote_date DATE NOT NULL,
    customer_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    customer_due_date DATE,
    purchase_status ENUM('Quoted', 'Purchased', 'Cancelled') NOT NULL DEFAULT 'Quoted',
    purchase_date DATE,
    coverage_start_date DATE,
    coverage_end_date DATE,
    renewal_reminder_date DATE,
    reminder_days_before_due INT NOT NULL DEFAULT 30,
    actual_registrar_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_from_account_id INT,
    registrar_provider VARCHAR(255),
    registrar_reference VARCHAR(150),
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_domain_billing_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_domain_billing_account FOREIGN KEY (paid_from_account_id) REFERENCES financial_accounts(id),
    CONSTRAINT fk_domain_billing_user FOREIGN KEY (created_by) REFERENCES users(id)
);

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS payment_scope ENUM('Project', 'Domain', 'Recurring') NOT NULL DEFAULT 'Project' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS domain_billing_period_id INT NULL AFTER payment_scope;
ALTER TABLE payments MODIFY COLUMN payment_scope ENUM('Project', 'Domain', 'Recurring') NOT NULL DEFAULT 'Project';

ALTER TABLE expenses ADD COLUMN IF NOT EXISTS domain_billing_period_id INT NULL AFTER reference_number;
ALTER TABLE financial_transactions ADD COLUMN IF NOT EXISTS domain_billing_period_id INT NULL AFTER project_payment_id;

SET @fk_sql = IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='domain_billing_period_id' AND REFERENCED_TABLE_NAME='domain_billing_periods')=0, 'ALTER TABLE payments ADD CONSTRAINT fk_payment_domain_billing FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE fk_stmt FROM @fk_sql; EXECUTE fk_stmt; DEALLOCATE PREPARE fk_stmt;

SET @fk_sql = IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND COLUMN_NAME='domain_billing_period_id' AND REFERENCED_TABLE_NAME='domain_billing_periods')=0, 'ALTER TABLE expenses ADD CONSTRAINT fk_expense_domain_billing FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE fk_stmt FROM @fk_sql; EXECUTE fk_stmt; DEALLOCATE PREPARE fk_stmt;

SET @fk_sql = IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_transactions' AND COLUMN_NAME='domain_billing_period_id' AND REFERENCED_TABLE_NAME='domain_billing_periods')=0, 'ALTER TABLE financial_transactions ADD CONSTRAINT fk_transaction_domain_billing FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE fk_stmt FROM @fk_sql; EXECUTE fk_stmt; DEALLOCATE PREPARE fk_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND INDEX_NAME='uq_expense_domain_billing')=0, 'CREATE UNIQUE INDEX uq_expense_domain_billing ON expenses(domain_billing_period_id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_transactions' AND INDEX_NAME='uq_transaction_domain_billing')=0, 'CREATE UNIQUE INDEX uq_transaction_domain_billing ON financial_transactions(domain_billing_period_id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND INDEX_NAME='idx_payments_domain_date')=0, 'CREATE INDEX idx_payments_domain_date ON payments(domain_billing_period_id, payment_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_billing_periods' AND INDEX_NAME='idx_domain_billing_project')=0, 'CREATE INDEX idx_domain_billing_project ON domain_billing_periods(project_id, quote_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_billing_periods' AND INDEX_NAME='idx_domain_billing_renewal')=0, 'CREATE INDEX idx_domain_billing_renewal ON domain_billing_periods(renewal_reminder_date, purchase_status)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;
