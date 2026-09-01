ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS domain_purchase_date DATE AFTER domain_name,
    ADD COLUMN IF NOT EXISTS domain_reminder_date DATE AFTER domain_purchase_date,
    ADD COLUMN IF NOT EXISTS domain_payment_date DATE AFTER domain_reminder_date,
    ADD COLUMN IF NOT EXISTS domain_server_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER domain_payment_date;

CREATE TABLE IF NOT EXISTS invoice_sequences (
    sequence_year SMALLINT PRIMARY KEY,
    current_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS receipt_sequences (
    sequence_year SMALLINT PRIMARY KEY,
    current_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS resolved_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reminder_type VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    due_date DATE NOT NULL,
    resolved_by INT,
    resolved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resolved_reminder (reminder_type, record_id, due_date),
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS financial_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE financial_accounts MODIFY COLUMN name VARCHAR(150) NOT NULL;

ALTER TABLE payments ADD COLUMN IF NOT EXISTS financial_account_id INT NULL AFTER payment_method;

SET @payment_account_fk_exists = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'financial_account_id' AND REFERENCED_TABLE_NAME = 'financial_accounts'
);
SET @payment_account_fk_sql = IF(@payment_account_fk_exists = 0, 'ALTER TABLE payments ADD CONSTRAINT fk_payment_financial_account FOREIGN KEY (financial_account_id) REFERENCES financial_accounts(id)', 'SELECT 1');
PREPARE payment_account_fk_stmt FROM @payment_account_fk_sql;
EXECUTE payment_account_fk_stmt;
DEALLOCATE PREPARE payment_account_fk_stmt;

CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('Receive', 'Use', 'Transfer') NOT NULL,
    from_account_id INT,
    to_account_id INT,
    amount DECIMAL(15,2) NOT NULL,
    notes VARCHAR(500),
    project_payment_id INT UNIQUE,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (to_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (project_payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

SET @activity_user_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);
SET @drop_activity_fk = IF(
    @activity_user_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE activity_logs DROP FOREIGN KEY `', REPLACE(@activity_user_fk, '`', '``'), '`')
);
PREPARE activity_fk_stmt FROM @drop_activity_fk;
EXECUTE activity_fk_stmt;
DEALLOCATE PREPARE activity_fk_stmt;
ALTER TABLE activity_logs
    ADD CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT INTO settings (setting_key, setting_value) VALUES
('company_tagline', 'Building Solutions. Empowering Growth.'),
('company_phone', ''),
('company_telegram', ''),
('company_email', ''),
('company_website', ''),
('payment_method', 'KBZ Pay / Wave Pay'),
('payment_account', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO invoice_sequences (sequence_year, current_value)
SELECT YEAR(CURDATE()), COALESCE(MAX(CAST(RIGHT(invoice_number, 4) AS UNSIGNED)), 0)
FROM invoices
WHERE invoice_number REGEXP CONCAT('^[A-Za-z]+-', YEAR(CURDATE()), '-[0-9]{4}$')
ON DUPLICATE KEY UPDATE current_value = GREATEST(current_value, VALUES(current_value));

INSERT INTO receipt_sequences (sequence_year, current_value)
SELECT YEAR(CURDATE()), COALESCE(MAX(CAST(RIGHT(receipt_number, 4) AS UNSIGNED)), 0)
FROM receipts
WHERE receipt_number REGEXP CONCAT('^[A-Za-z]+-', YEAR(CURDATE()), '-[0-9]{4}$')
ON DUPLICATE KEY UPDATE current_value = GREATEST(current_value, VALUES(current_value));

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_created') = 0, 'CREATE INDEX idx_projects_created ON projects(created_at, id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_date_id') = 0, 'CREATE INDEX idx_payments_date_id ON payments(payment_date, id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND INDEX_NAME = 'idx_expenses_date_id') = 0, 'CREATE INDEX idx_expenses_date_id ON expenses(expense_date, id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_fees' AND INDEX_NAME = 'idx_recurring_project_due') = 0, 'CREATE INDEX idx_recurring_project_due ON recurring_fees(project_id, next_due_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'idx_invoices_project_date') = 0, 'CREATE INDEX idx_invoices_project_date ON invoices(project_id, invoice_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'receipts' AND INDEX_NAME = 'idx_receipts_project_date') = 0, 'CREATE INDEX idx_receipts_project_date ON receipts(project_id, receipt_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_account_date') = 0, 'CREATE INDEX idx_payments_account_date ON payments(financial_account_id, payment_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_transactions' AND INDEX_NAME = 'idx_financial_transactions_date') = 0, 'CREATE INDEX idx_financial_transactions_date ON financial_transactions(transaction_date, id)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_transactions' AND INDEX_NAME = 'idx_financial_transactions_from') = 0, 'CREATE INDEX idx_financial_transactions_from ON financial_transactions(from_account_id, transaction_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;

SET @index_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_transactions' AND INDEX_NAME = 'idx_financial_transactions_to') = 0, 'CREATE INDEX idx_financial_transactions_to ON financial_transactions(to_account_id, transaction_date)', 'SELECT 1');
PREPARE index_stmt FROM @index_sql; EXECUTE index_stmt; DEALLOCATE PREPARE index_stmt;
