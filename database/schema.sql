SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS resolved_reminders;
DROP TABLE IF EXISTS revoked_tokens;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS receipt_sequences;
DROP TABLE IF EXISTS invoice_sequences;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS financial_transactions;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS recurring_fees;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS domain_billing_periods;
DROP TABLE IF EXISTS financial_accounts;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff', 'Viewer') NOT NULL DEFAULT 'Staff',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failed_count INT NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_updated (updated_at)
);

CREATE TABLE revoked_tokens (
    jti CHAR(32) PRIMARY KEY,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_revoked_tokens_expires (expires_at)
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_code VARCHAR(50) NOT NULL UNIQUE,
    project_name VARCHAR(255) NOT NULL,
    project_type VARCHAR(100),
    description TEXT,
    status ENUM('New', 'In Progress', 'Waiting Payment', 'Delivered', 'Completed', 'Cancelled', 'On Hold') NOT NULL DEFAULT 'New',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    start_date DATE,
    delivery_date DATE,
    completion_date DATE,
    customer_company_name VARCHAR(255),
    contact_person VARCHAR(150),
    contact_phone VARCHAR(50),
    contact_email VARCHAR(150),
    customer_address TEXT,
    contract_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    upfront_required_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    payment_due_date DATE,
    currency VARCHAR(10) DEFAULT 'MMK',
    domain_name VARCHAR(255),
    domain_purchase_date DATE,
    domain_reminder_date DATE,
    domain_payment_date DATE,
    domain_server_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    hosting_provider VARCHAR(255),
    server_provider VARCHAR(255),
    server_ip VARCHAR(100),
    git_repository_url VARCHAR(255),
    admin_panel_url VARCHAR(255),
    production_url VARCHAR(255),
    technical_notes TEXT,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE financial_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE domain_billing_periods (
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
    customer_renewal_date DATE,
    renewal_reminder_date DATE,
    reminder_days_before_due INT NOT NULL DEFAULT 30,
    actual_registrar_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_historical_purchase TINYINT(1) NOT NULL DEFAULT 0,
    is_registrar_carryover TINYINT(1) NOT NULL DEFAULT 0,
    paid_from_account_id INT,
    registrar_provider VARCHAR(255),
    registrar_reference VARCHAR(150),
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (paid_from_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_type ENUM('Upfront', 'Progress Payment', 'Final Payment', 'Maintenance', 'Hosting', 'Domain', 'Server', 'Other') NOT NULL,
    payment_method ENUM('Cash', 'KPay', 'WavePay', 'Bank Transfer', 'AYA Pay', 'CB Pay', 'Other') NOT NULL,
    payment_scope ENUM('Project', 'Domain', 'Recurring') NOT NULL DEFAULT 'Project',
    domain_billing_period_id INT,
    is_historical TINYINT(1) NOT NULL DEFAULT 0,
    financial_account_id INT,
    reference_number VARCHAR(150),
    received_by INT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (financial_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE recurring_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    fee_name VARCHAR(255) NOT NULL,
    fee_type ENUM('Domain', 'Hosting', 'VPS', 'Server', 'SSL', 'Maintenance', 'API Subscription', 'Other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    billing_cycle ENUM('Monthly', 'Quarterly', 'Half Yearly', 'Yearly', 'One Time') NOT NULL,
    last_paid_date DATE,
    next_due_date DATE NOT NULL,
    reminder_days_before_due INT DEFAULT 7,
    status ENUM('Not Due', 'Due Soon', 'Due Today', 'Overdue', 'Paid', 'Cancelled') DEFAULT 'Not Due',
    auto_create_reminder TINYINT(1) DEFAULT 1,
    notes TEXT,
    source_type ENUM('Manual') NOT NULL DEFAULT 'Manual',
    source_key VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uq_recurring_project_source (project_id, source_key)
);

CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category_group ENUM('Staff & People', 'Software & Technology', 'Office & Operations', 'Business Administration', 'Other') NOT NULL DEFAULT 'Other',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    expense_date DATE NOT NULL,
    expense_scope ENUM('Project', 'Company') NOT NULL DEFAULT 'Project',
    expense_category_id INT,
    expense_category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    amount DECIMAL(15,2) NOT NULL,
    paid_to VARCHAR(255),
    payment_method ENUM('Cash', 'KPay', 'WavePay', 'Bank Transfer', 'AYA Pay', 'CB Pay', 'Other') DEFAULT 'Cash',
    financial_account_id INT,
    expense_status ENUM('Planned', 'Unpaid', 'Paid') NOT NULL DEFAULT 'Paid',
    expense_frequency ENUM('One Time', 'Recurring') NOT NULL DEFAULT 'One Time',
    billing_cycle ENUM('Monthly', 'Quarterly', 'Half Yearly', 'Yearly', 'Other'),
    billing_period VARCHAR(50),
    is_historical TINYINT(1) NOT NULL DEFAULT 0,
    reference_number VARCHAR(150),
    domain_billing_period_id INT UNIQUE,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (financial_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    project_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    invoice_type ENUM('Project Invoice', 'Upfront Invoice', 'Progress Invoice', 'Final Invoice', 'Hosting Invoice', 'Domain Invoice', 'Maintenance Invoice', 'Other') NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    project_total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    previously_paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    remaining_project_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('Draft', 'Sent', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Draft',
    header_note VARCHAR(500),
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

CREATE TABLE invoice_sequences (
    sequence_year SMALLINT PRIMARY KEY,
    current_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE receipt_sequences (
    sequence_year SMALLINT PRIMARY KEY,
    current_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    project_id INT NOT NULL,
    payment_id INT NOT NULL,
    receipt_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(100),
    received_from VARCHAR(255),
    received_by INT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('Receive', 'Use', 'Transfer') NOT NULL,
    from_account_id INT,
    to_account_id INT,
    amount DECIMAL(15,2) NOT NULL,
    notes VARCHAR(500),
    project_payment_id INT UNIQUE,
    domain_billing_period_id INT UNIQUE,
    expense_id INT UNIQUE,
    manual_use_type ENUM('Owner Withdrawal', 'Cash Adjustment', 'Loan Repayment', 'Non-expense Balance Correction', 'Other'),
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (to_account_id) REFERENCES financial_accounts(id),
    FOREIGN KEY (project_payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_billing_period_id) REFERENCES domain_billing_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    record_id INT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE resolved_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reminder_type VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    due_date DATE NOT NULL,
    resolved_by INT,
    resolved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resolved_reminder (reminder_type, record_id, due_date),
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_due ON projects(payment_due_date);
CREATE INDEX idx_projects_domain_reminder ON projects(domain_reminder_date);
CREATE INDEX idx_projects_created ON projects(created_at, id);
CREATE INDEX idx_payments_project_date ON payments(project_id, payment_date);
CREATE INDEX idx_payments_date_id ON payments(payment_date, id);
CREATE INDEX idx_payments_account_date ON payments(financial_account_id, payment_date);
CREATE INDEX idx_payments_domain_date ON payments(domain_billing_period_id, payment_date);
CREATE INDEX idx_expenses_project_date ON expenses(project_id, expense_date);
CREATE INDEX idx_expenses_date_id ON expenses(expense_date, id);
CREATE INDEX idx_expenses_scope_date ON expenses(expense_scope, expense_date);
CREATE INDEX idx_expenses_category_date ON expenses(expense_category_id, expense_date);
CREATE INDEX idx_expenses_account_date ON expenses(financial_account_id, expense_date);
CREATE INDEX idx_recurring_due ON recurring_fees(next_due_date, status);
CREATE INDEX idx_recurring_project_due ON recurring_fees(project_id, next_due_date);
CREATE INDEX idx_recurring_reminder ON recurring_fees(auto_create_reminder, status, next_due_date);
CREATE INDEX idx_invoices_status_due ON invoices(status, due_date);
CREATE INDEX idx_invoices_due_status ON invoices(due_date, status);
CREATE INDEX idx_invoices_project_date ON invoices(project_id, invoice_date);
CREATE INDEX idx_receipts_project_date ON receipts(project_id, receipt_date);
CREATE INDEX idx_financial_transactions_date ON financial_transactions(transaction_date, id);
CREATE INDEX idx_financial_transactions_from ON financial_transactions(from_account_id, transaction_date);
CREATE INDEX idx_financial_transactions_to ON financial_transactions(to_account_id, transaction_date);
CREATE INDEX idx_domain_billing_project ON domain_billing_periods(project_id, quote_date);
CREATE INDEX idx_domain_billing_renewal ON domain_billing_periods(renewal_reminder_date, purchase_status);
CREATE INDEX idx_domain_customer_renewal ON domain_billing_periods(customer_renewal_date, purchase_status);
CREATE INDEX idx_domain_purchase_due ON domain_billing_periods(purchase_status, customer_due_date);
CREATE INDEX idx_domain_project_name_quote ON domain_billing_periods(project_id, domain_name, quote_date);
CREATE INDEX idx_activity_module_record ON activity_logs(module, record_id, created_at);
CREATE INDEX idx_activity_created ON activity_logs(created_at, id);

INSERT INTO users (name, email, password_hash, role, status) VALUES
('Admin', 'admin@example.com', '$2y$10$LFP/NtrAmMCk5.KuxPcxl.qKTAssKdn1VreMkr.rnlKhrA56uE1zG', 'Admin', 'Active');

INSERT INTO expense_categories (name, category_group, sort_order) VALUES
('Staff Salary','Staff & People',10),('Contractor/Freelancer','Staff & People',20),('Bonus','Staff & People',30),('Employee Benefit','Staff & People',40),('Recruitment','Staff & People',50),('Training','Staff & People',60),('Developer Cost','Staff & People',70),('Design Cost','Staff & People',80),
('AI Tools/Agents','Software & Technology',110),('SaaS Subscription','Software & Technology',120),('API Cost','Software & Technology',130),('Development Tools','Software & Technology',140),('Domain Purchase','Software & Technology',150),('Hosting Purchase','Software & Technology',160),('VPS Purchase','Software & Technology',170),('Server Cost','Software & Technology',180),('SSL Cost','Software & Technology',190),('SMS Cost','Software & Technology',200),('Software License','Software & Technology',210),
('Office Rent','Office & Operations',310),('Electricity','Office & Operations',320),('Internet','Office & Operations',330),('Phone','Office & Operations',340),('Office Supplies','Office & Operations',350),('Equipment/Hardware','Office & Operations',360),('Repairs & Maintenance','Office & Operations',370),('Transport','Office & Operations',380),('Travel & Accommodation','Office & Operations',390),
('Marketing/Advertising','Business Administration',410),('Bank/Payment Fees','Business Administration',420),('Tax','Business Administration',430),('Government/License Fees','Business Administration',440),('Legal','Business Administration',450),('Accounting','Business Administration',460),('Insurance','Business Administration',470),('Customer Entertainment','Business Administration',480),('Other','Other',999);

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Your Company Name'),
('company_tagline', 'Building Solutions. Empowering Growth.'),
('company_phone', ''),
('company_telegram', ''),
('company_email', ''),
('company_website', ''),
('payment_method', 'KBZ Pay / Wave Pay'),
('payment_account', ''),
('currency', 'MMK'),
('invoice_prefix', 'INV'),
('receipt_prefix', 'REC'),
('project_prefix', 'PRJ');
