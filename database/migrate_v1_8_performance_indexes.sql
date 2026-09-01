-- KSSPM Version 1.8 maintenance: indexes for growing reminder and audit data.
-- Safe to run more than once.

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND INDEX_NAME='idx_projects_domain_reminder');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_projects_domain_reminder ON projects(domain_reminder_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recurring_fees' AND INDEX_NAME='idx_recurring_reminder');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_recurring_reminder ON recurring_fees(auto_create_reminder, status, next_due_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_billing_periods' AND INDEX_NAME='idx_domain_purchase_due');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_domain_purchase_due ON domain_billing_periods(purchase_status, customer_due_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='domain_billing_periods' AND INDEX_NAME='idx_domain_project_name_quote');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_domain_project_name_quote ON domain_billing_periods(project_id, domain_name, quote_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND INDEX_NAME='idx_invoices_due_status');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_invoices_due_status ON invoices(due_date, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='activity_logs' AND INDEX_NAME='idx_activity_created');
SET @sql = IF(@has_index=0, 'CREATE INDEX idx_activity_created ON activity_logs(created_at, id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
