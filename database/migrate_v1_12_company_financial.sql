-- KSSPM Version 1.12: company expenses, managed categories and linked account movements.
-- Safe to run more than once. Existing expenses are preserved as historical records.

CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category_group ENUM('Staff & People', 'Software & Technology', 'Office & Operations', 'Business Administration', 'Other') NOT NULL DEFAULT 'Other',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO expense_categories (name, category_group, sort_order) VALUES
('Staff Salary','Staff & People',10),('Contractor/Freelancer','Staff & People',20),('Bonus','Staff & People',30),('Employee Benefit','Staff & People',40),('Recruitment','Staff & People',50),('Training','Staff & People',60),('Developer Cost','Staff & People',70),('Design Cost','Staff & People',80),
('AI Tools/Agents','Software & Technology',110),('SaaS Subscription','Software & Technology',120),('API Cost','Software & Technology',130),('Development Tools','Software & Technology',140),('Domain Purchase','Software & Technology',150),('Hosting Purchase','Software & Technology',160),('VPS Purchase','Software & Technology',170),('Server Cost','Software & Technology',180),('SSL Cost','Software & Technology',190),('SMS Cost','Software & Technology',200),('Software License','Software & Technology',210),
('Office Rent','Office & Operations',310),('Electricity','Office & Operations',320),('Internet','Office & Operations',330),('Phone','Office & Operations',340),('Office Supplies','Office & Operations',350),('Equipment/Hardware','Office & Operations',360),('Repairs & Maintenance','Office & Operations',370),('Transport','Office & Operations',380),('Travel & Accommodation','Office & Operations',390),
('Marketing/Advertising','Business Administration',410),('Bank/Payment Fees','Business Administration',420),('Tax','Business Administration',430),('Government/License Fees','Business Administration',440),('Legal','Business Administration',450),('Accounting','Business Administration',460),('Insurance','Business Administration',470),('Customer Entertainment','Business Administration',480),('Other','Other',999);

ALTER TABLE expenses MODIFY project_id INT NULL;
ALTER TABLE expenses MODIFY expense_category VARCHAR(100) NOT NULL;
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS expense_scope ENUM('Project','Company') NOT NULL DEFAULT 'Project' AFTER expense_date,
    ADD COLUMN IF NOT EXISTS expense_category_id INT NULL AFTER expense_scope,
    ADD COLUMN IF NOT EXISTS subcategory VARCHAR(100) NULL AFTER expense_category,
    ADD COLUMN IF NOT EXISTS financial_account_id INT NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS expense_status ENUM('Planned','Unpaid','Paid') NOT NULL DEFAULT 'Paid' AFTER financial_account_id,
    ADD COLUMN IF NOT EXISTS expense_frequency ENUM('One Time','Recurring') NOT NULL DEFAULT 'One Time' AFTER expense_status,
    ADD COLUMN IF NOT EXISTS billing_cycle ENUM('Monthly','Quarterly','Half Yearly','Yearly','Other') NULL AFTER expense_frequency,
    ADD COLUMN IF NOT EXISTS billing_period VARCHAR(50) NULL AFTER billing_cycle,
    ADD COLUMN IF NOT EXISTS is_historical TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_period;

UPDATE expenses e JOIN expense_categories c ON c.name=e.expense_category
SET e.expense_category_id=c.id WHERE e.expense_category_id IS NULL;
UPDATE expenses SET expense_scope=IF(project_id IS NULL,'Company','Project');

ALTER TABLE financial_transactions
    ADD COLUMN IF NOT EXISTS expense_id INT NULL AFTER domain_billing_period_id,
    ADD COLUMN IF NOT EXISTS manual_use_type ENUM('Owner Withdrawal','Cash Adjustment','Loan Repayment','Non-expense Balance Correction','Other') NULL AFTER expense_id;

-- Link registrar expense rows to their already-existing account transaction.
UPDATE financial_transactions ft
JOIN expenses e ON e.domain_billing_period_id=ft.domain_billing_period_id
SET ft.expense_id=e.id
WHERE ft.expense_id IS NULL;

-- Reconstruct account linkage for existing registrar purchases when possible.
UPDATE expenses e
JOIN financial_transactions ft ON ft.expense_id=e.id
SET e.financial_account_id=ft.from_account_id, e.is_historical=0
WHERE ft.transaction_type='Use' AND ft.from_account_id IS NOT NULL;

-- Paid records without any linked account movement are imported history, not new cash activity.
UPDATE expenses e
LEFT JOIN financial_transactions ft ON ft.expense_id=e.id
SET e.is_historical=1
WHERE e.expense_status='Paid' AND e.financial_account_id IS NULL AND ft.id IS NULL;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expense_category');
SET @sql = IF(@has_fk=0, 'ALTER TABLE expenses ADD CONSTRAINT fk_expense_category FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_fk = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expense_account');
SET @sql = IF(@has_fk=0, 'ALTER TABLE expenses ADD CONSTRAINT fk_expense_account FOREIGN KEY (financial_account_id) REFERENCES financial_accounts(id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_fk = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='financial_transactions' AND CONSTRAINT_NAME='fk_transaction_expense');
SET @sql = IF(@has_fk=0, 'ALTER TABLE financial_transactions ADD CONSTRAINT fk_transaction_expense FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_transactions' AND INDEX_NAME='uq_financial_expense');
SET @sql = IF(@has_index=0, 'CREATE UNIQUE INDEX uq_financial_expense ON financial_transactions(expense_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND INDEX_NAME='idx_expenses_scope_date');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_expenses_scope_date ON expenses(expense_scope, expense_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND INDEX_NAME='idx_expenses_category_date');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_expenses_category_date ON expenses(expense_category_id, expense_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND INDEX_NAME='idx_expenses_account_date');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_expenses_account_date ON expenses(financial_account_id, expense_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
